<?php

namespace App\Http\Controllers;

use App\Enums\ApplicationStatus;
use App\Models\Pet;
use App\Models\ResearchPlacement;
use App\Services\KnnRecommendationService;
use App\Services\Matching\ApplicantRankingService;
use App\Services\ReservationQueueService;
use App\Support\ResearchMode;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

final class ResearchScenarioController extends Controller
{
    public function index(Request $request, ApplicantRankingService $ranking)
    {
        $placements = ResearchPlacement::with('pet')->where('user_id', $request->user()->id)->latest('id')->get();
        $submitted = DB::table('research_checkins')->whereIn('placement_id', $placements->pluck('id'))->get()->groupBy('placement_id');
        $positions = $placements->where('status', ApplicationStatus::Waitlisted)->mapWithKeys(function (ResearchPlacement $p) use ($ranking) {
            $offset = $ranking->eligibleResearchForPet($p->pet, locked: false)->search(fn ($candidate) => $candidate->id === $p->id);

            return [$p->id => $offset === false ? null : $offset + 1];
        });

        return view('research.scenarios', compact('placements', 'submitted', 'positions'));
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

        return view('research.apply', compact('pet', 'match'));
    }

    public function apply(Request $request, Pet $pet, ReservationQueueService $queue)
    {
        $queue->submitResearch($request->user(), $pet);

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

    public function checkin(Request $request, int $placement)
    {
        $data = $request->validate(['milestone' => ['required', Rule::in(ResearchMode::MILESTONES)],
            'appetite' => ['required', Rule::in(['normal', 'reduced'])], 'adjustment' => ['required', Rule::in(['settling', 'struggling'])]]);
        DB::transaction(function () use ($request, $placement, $data) {
            $p = DB::table('research_placements')->where(['id' => $placement, 'user_id' => $request->user()->id])->lockForUpdate()->first();
            abort_unless($p, 404);
            abort_unless($p->received_at, 409, 'Confirm simulated receipt before recording a check-in.');
            $logs = DB::table('research_checkins')->where('placement_id', $placement);
            if ((clone $logs)->where('milestone', $data['milestone'])->exists()) {
                return;
            }
            abort_unless((ResearchMode::MILESTONES[$logs->count()] ?? null) === $data['milestone'], 409, 'Complete the earlier simulated check-in first.');
            $logs->insert(['placement_id' => $placement, 'milestone' => $data['milestone'], 'answers' => json_encode(array_intersect_key($data, array_flip(['appetite', 'adjustment'])), JSON_THROW_ON_ERROR), 'submitted_at' => now()]);
        });

        return to_route('research.index');
    }
}
