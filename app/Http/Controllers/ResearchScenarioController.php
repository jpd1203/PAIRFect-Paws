<?php

namespace App\Http\Controllers;

use App\Enums\ApplicationStatus;
use App\Models\Pet;
use App\Models\ResearchPlacement;
use App\Services\KnnRecommendationService;
use App\Services\Matching\ApplicantRankingService;
use App\Services\Matching\MatchPresenter;
use App\Services\PhilippineLocationService;
use App\Services\ResearchDocumentVerification;
use App\Services\ReservationQueueService;
use App\Support\AdoptionForm;
use App\Support\ResearchMode;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

final class ResearchScenarioController extends Controller
{
    public function intro()
    {
        abort_unless(ResearchMode::ready(), 404);

        return view('research.study');
    }

    public function consent(Request $request)
    {
        abort_unless(ResearchMode::ready(), 404);
        $request->validate(['study_consent' => 'accepted']);
        DB::table('research_study_consents')->insertOrIgnore([
            'user_id' => $request->user()->id, 'version' => ResearchMode::CONSENT_VERSION, 'accepted_at' => now(),
        ]);

        return to_route($request->user()->matching_onboarding_pending ? 'recommendation.onboarding' : 'animal.index');
    }

    public function index(Request $request, ApplicantRankingService $ranking)
    {
        $section = $request->query('section');
        $placements = ResearchPlacement::with('pet')->where('user_id', $request->user()->id)
            ->when($section === 'handover', fn ($query) => $query->where('status', ApplicationStatus::Approved)->whereNotNull('released_at'))
            ->when($section === 'check-ins', fn ($query) => $query->whereNotNull('received_at'))
            ->latest('id')->get();
        $submitted = DB::table('research_checkins')->whereIn('placement_id', $placements->pluck('id'))->get()->groupBy('placement_id');
        $positions = $placements->where('status', ApplicationStatus::Waitlisted)->mapWithKeys(function (ResearchPlacement $p) use ($ranking) {
            $offset = $ranking->eligibleResearchForPet($p->pet, locked: false)->search(fn ($candidate) => $candidate->id === $p->id);

            return [$p->id => $offset === false ? null : $offset + 1];
        });

        return view('research.scenarios', compact('placements', 'submitted', 'positions', 'section'));
    }

    public function preview(Request $request, Pet $pet, KnnRecommendationService $matcher)
    {
        abort_unless(ResearchMode::catalogPet($pet), 404);
        $existing = ResearchPlacement::where('user_id', $request->user()->id)->where('pet_id', $pet->id)->exists();
        if ($existing) {
            return to_route('research.index');
        }
        $match = $matcher->calculateMatch($request->user()->adopterProfile, $pet, existingApplication: true);
        if ($match->exclusionReason === 'ADOPTER_PROFILE_INCOMPLETE') {
            return redirect()->route('recommendation.intake', ['return_pet' => $pet->id]);
        }

        $profile = $request->user()->adopterProfile;

        return $match->eligible
            ? view('application.apply', compact('pet', 'match', 'profile') + ['research' => true])
            : view('research.unavailable', compact('pet', 'match'));
    }

    public function apply(Request $request, Pet $pet, ReservationQueueService $queue, KnnRecommendationService $matcher, PhilippineLocationService $locations, ResearchDocumentVerification $documents)
    {
        abort_unless(ResearchMode::catalogPet($pet), 404);
        $user = $request->user();
        if (ResearchPlacement::where('user_id', $user->id)->where('pet_id', $pet->id)->exists()) {
            return to_route('research.index');
        }
        $match = $matcher->calculateMatch($user->adopterProfile, $pet, existingApplication: true);
        if (! $match->eligible) {
            throw ValidationException::withMessages(['pet_id' => MatchPresenter::reason($match->exclusionReason)]);
        }
        $validated = $request->validate(AdoptionForm::rules(), [
            'document.required' => 'Upload a government ID showing your name and residential address.',
            'agreed_to_terms.accepted' => 'Accept the Adoption and Data Processing Agreement to submit your application.',
        ]);
        $details = Arr::only($validated, [
            'first_name', 'last_name', 'email', 'phone_number', 'motivation_statement', 'physical_activity_level',
            'time_availability', 'prior_pet_experience', 'household_composition',
        ]);
        $details['address'] = $locations->resolveAddress($validated);
        $details['agreed_at'] = now()->toISOString();
        $attributes = $documents->process($validated['document'], $details);
        $queue->submitResearch($user, $pet, ['application_details' => $details, ...$attributes]);

        return to_route('research.index')->with('success', 'Application submitted.');
    }

    public function replaceDocument(Request $request, ResearchPlacement $placement, ResearchDocumentVerification $documents)
    {
        $this->owned($request, $placement);
        abort_unless($placement->acceptsResearchReplacement(), 409);
        $validated = $request->validate(['document' => 'required|'.AdoptionForm::DOCUMENT_RULE, 'document_consent' => 'accepted']);
        $attributes = $documents->process($validated['document'], $placement->application_details);
        DB::transaction(function () use ($placement, $attributes): void {
            $locked = ResearchPlacement::lockForUpdate()->findOrFail($placement->id);
            abort_unless($locked->acceptsResearchReplacement() && $locked->document_reupload_count === $placement->document_reupload_count, 409);
            $locked->update([...$attributes, 'document_reupload_count' => $locked->document_reupload_count + 1]);
        });

        return to_route('research.index');
    }

    public function continueAdoption(Request $request, ResearchPlacement $placement, ReservationQueueService $queue)
    {
        $queue->approveResearch($request->user(), $placement);

        return to_route('research.index');
    }

    public function withdraw(Request $request, ResearchPlacement $placement, ReservationQueueService $queue)
    {
        $queue->withdrawResearch($request->user(), $placement);

        return to_route('research.index');
    }

    public function receive(Request $request, int $placement)
    {
        $owned = DB::table('research_placements')->where(['id' => $placement, 'user_id' => $request->user()->id])->where('status', ApplicationStatus::Approved->value)->whereNotNull('released_at');
        abort_unless((clone $owned)->exists(), 404);
        $owned->whereNull('received_at')->update(['received_at' => now()]);

        return to_route('research.index');
    }

    public function handover(Request $request, ResearchPlacement $placement)
    {
        $this->owned($request, $placement);
        abort_unless($placement->status === ApplicationStatus::Approved && $placement->released_at, 404);

        return view('research.handover', compact('placement'));
    }

    public function report(Request $request, ResearchPlacement $placement)
    {
        $this->owned($request, $placement);
        abort_unless($placement->received_at, 409, 'Confirm receipt before recording a check-in.');
        $milestone = ResearchMode::MILESTONES[DB::table('research_checkins')->where('placement_id', $placement->id)->count()] ?? null;
        if (! $milestone) {
            return to_route('research.index');
        }

        return view('monitoring.create', ['researchPlacement' => $placement, 'researchMilestone' => $milestone]);
    }

    private function owned(Request $request, ResearchPlacement $placement): void
    {
        abort_unless((int) $placement->user_id === (int) $request->user()->id, 404);
    }

    public function checkin(Request $request, int $placement)
    {
        $data = $request->validate([
            'milestone' => ['required', Rule::in(ResearchMode::MILESTONES)],
            'pet_current_status' => ['required', Rule::in(['Excellent', 'Good', 'Fair', 'Poor'])],
            'living_conditions' => ['required', Rule::in(['Indoor Only', 'Outdoor Only', 'Indoor and Outdoor'])],
            'eating_habits' => ['required', Rule::in(['Normal', 'Reduced Appetite', 'Not Eating'])],
            'behavioral_observations' => ['required', Rule::in(['Well-adjusted', 'Still adjusting', 'Anxious/Stressed', 'Aggressive'])],
            'vet_visit_details' => ['nullable', 'string', 'max:2000'],
            'concerns' => ['nullable', 'string', 'max:2000'],
        ]);
        DB::transaction(function () use ($request, $placement, $data) {
            $p = DB::table('research_placements')->where(['id' => $placement, 'user_id' => $request->user()->id])->lockForUpdate()->first();
            abort_unless($p, 404);
            abort_unless($p->received_at, 409, 'Confirm receipt before recording a check-in.');
            $logs = DB::table('research_checkins')->where('placement_id', $placement);
            if ((clone $logs)->where('milestone', $data['milestone'])->exists()) {
                return;
            }
            abort_unless((ResearchMode::MILESTONES[$logs->count()] ?? null) === $data['milestone'], 409, 'Complete the earlier check-in first.');
            $logs->insert(['placement_id' => $placement, 'milestone' => $data['milestone'], 'answers' => json_encode(Arr::except($data, 'milestone'), JSON_THROW_ON_ERROR), 'submitted_at' => now()]);
        });

        return to_route('research.index');
    }
}
