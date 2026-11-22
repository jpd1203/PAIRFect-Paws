<?php

namespace App\Http\Controllers;

use App\Enums\DocumentVerificationStatus;
use App\Models\Pet;
use App\Models\ResearchPlacement;
use App\Services\DocumentVerificationService;
use App\Services\KnnRecommendationService;
use App\Services\Matching\MatchPresenter;
use App\Services\PhilippineLocationService;
use App\Services\ResearchPrivateMedia;
use App\Services\ReservationQueueService;
use App\Support\AdoptionForm;
use App\Support\PhilippineAddress;
use App\Support\ResearchMode;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;
use Throwable;

final class ResearchScenarioController extends Controller
{
    public function index(Request $request)
    {
        return match ($request->query('section')) {
            'handover' => to_route('adopter.handover.my'),
            'check-ins' => to_route('monitoring.my-checkins'),
            default => to_route('application.index'),
        };
    }

    public function preview(Request $request, Pet $pet, KnnRecommendationService $matcher)
    {
        abort_unless(ResearchMode::catalogPet($pet), 404);
        $existing = ResearchPlacement::where('user_id', $request->user()->id)->where('pet_id', $pet->id)->exists();
        if ($existing) {
            return to_route('application.index');
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

    public function apply(Request $request, Pet $pet, ReservationQueueService $queue, KnnRecommendationService $matcher, PhilippineLocationService $locations, DocumentVerificationService $ocr, ResearchPrivateMedia $media)
    {
        abort_unless(ResearchMode::catalogPet($pet), 404);
        $user = $request->user();
        if (ResearchPlacement::where('user_id', $user->id)->where('pet_id', $pet->id)->exists()) {
            return to_route('application.index');
        }
        $match = $matcher->calculateMatch($user->adopterProfile, $pet, existingApplication: true);
        if (! $match->eligible) {
            throw ValidationException::withMessages(['pet_id' => MatchPresenter::reason($match->exclusionReason)]);
        }
        $rules = array_replace(AdoptionForm::rules(), ['document' => 'nullable|'.AdoptionForm::DOCUMENT_RULE]);
        if ($request->hasFile('document')) {
            $rules['document_consent'] = 'accepted';
        }
        $validated = $request->validate($rules, [
            'document_consent.accepted' => 'Accept the document-processing terms before uploading an ID.',
            'agreed_to_terms.accepted' => 'Accept the Adoption and Data Processing Agreement to submit your application.',
        ]);
        $details = Arr::only($validated, [
            'first_name', 'last_name', 'email', 'phone_number', 'motivation_statement', 'physical_activity_level',
            'time_availability', 'prior_pet_experience', 'household_composition',
        ]);
        $details['address'] = $locations->resolveAddress($validated);
        $details['agreed_at'] = now()->toISOString();
        $attributes = ['application_details' => $details];
        $path = null;
        try {
            if ($file = $validated['document'] ?? null) {
                $mime = $file->getMimeType();
                $path = $media->store($file, 'documents', $user->id);
                $result = config('research.ocr_enabled') === true ? $ocr->verify(ResearchPrivateMedia::DISK, $path, $mime, [
                    'first_name' => $details['first_name'], 'last_name' => $details['last_name'],
                    ...PhilippineAddress::ocrPayload($details['address']),
                ]) : null;
                $status = $result?->status ?? DocumentVerificationStatus::ManualReview;
                $attributes += [
                    'document_path' => $path, 'document_mime_type' => $mime,
                    'document_type' => $result?->documentType, 'document_match_score' => $result?->matchScore,
                    'document_uploaded_at' => now(), 'document_consent_at' => now(),
                    'document_consent_version' => 'research-document-v1', 'document_verification_status' => $status,
                    'document_verification_reasons' => $status === DocumentVerificationStatus::ManualReview
                        ? ['Automatic document checking was unavailable. No identity was verified.'] : ($result?->reasons ?? []),
                ];
            }
            if (! $queue->submitResearch($user, $pet, $attributes)) {
                $media->forget($path);
            }
        } catch (Throwable $exception) {
            $media->forget($path);
            throw $exception;
        }

        return to_route('application.index')->with('success', 'Application submitted.');
    }

    public function continueAdoption(Request $request, ResearchPlacement $placement, ReservationQueueService $queue)
    {
        $queue->approveResearch($request->user(), $placement);

        return to_route('application.index');
    }

    public function withdraw(Request $request, ResearchPlacement $placement, ReservationQueueService $queue)
    {
        $queue->withdrawResearch($request->user(), $placement);

        return to_route('application.index');
    }
}
