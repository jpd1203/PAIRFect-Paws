<?php

namespace App\Http\Controllers;

use App\Enums\ApplicationStatus;
use App\Enums\AvailabilityStatus;
use App\Enums\DocumentVerificationStatus;
use App\Models\AdoptionApplication;
use App\Models\Pet;
use App\Services\AuditLogService;
use App\Services\DocumentVerificationNotificationService;
use App\Services\DocumentVerificationService;
use App\Services\EmailNotificationService;
use App\Services\KnnRecommendationService;
use App\Services\Matching\ApplicationMatchService;
use App\Services\Matching\MatchingProfileMapper;
use App\Services\Matching\MatchPresenter;
use App\Services\PhilippineLocationService;
use App\Support\ManilaTime;
use App\Support\PhilippineAddress;
use App\ValueObjects\DocumentVerificationResult;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class ApplicationController extends Controller
{
    public function __construct(
        private DocumentVerificationService $documentVerification,
        private DocumentVerificationNotificationService $documentNotifications,
        private EmailNotificationService $emailNotifications,
        private PhilippineLocationService $locations,
        private ApplicationMatchService $matching,
        private MatchingProfileMapper $matchingProfiles,
        private KnnRecommendationService $matcher,
    ) {}

    public function index()
    {
        $applications = AdoptionApplication::with('pet')
            // Adopter responses never load staff-only interview, decision, ranking,
            // OCR, or audit notes from the application record.
            ->select([
                'id', 'user_id', 'pet_id', 'status', 'created_at', 'updated_at',
                'document_verification_status', 'document_verification_reasons',
                'document_reupload_count', 'interview_date', 'conducted_by',
                'reschedule_status', 'reschedule_options',
            ])
            ->where('user_id', Auth::id())
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get();

        return view('application.index', compact('applications'));
    }

    public function requestReschedule(Request $request, AdoptionApplication $application)
    {
        abort_unless($application->user_id === $request->user()->id, 404);

        $validated = $request->validate([
            'reason' => 'nullable|string|max:1000',
            'options' => 'required|array|min:1|max:3',
            'options.*.date' => 'nullable|date_format:Y-m-d',
            'options.*.time' => 'nullable|date_format:H:i',
        ]);

        $options = [];
        foreach ($validated['options'] as $option) {
            $date = $option['date'] ?? null;
            $time = $option['time'] ?? null;
            if (! $date && ! $time) {
                continue;
            }
            if (! $date || ! $time) {
                throw ValidationException::withMessages(['options' => 'Enter both a date and a time for each preferred option.']);
            }
            $preferredAt = Carbon::createFromFormat('Y-m-d H:i', "{$date} {$time}", ManilaTime::timezone());
            if (! $preferredAt || $preferredAt->lte(ManilaTime::now())) {
                throw ValidationException::withMessages(['options' => 'Every preferred date and time must be in the future.']);
            }
            if (in_array(['date' => $date, 'time' => $time], $options, true)) {
                throw ValidationException::withMessages(['options' => 'Choose different dates or times for each option.']);
            }
            $options[] = ['date' => $date, 'time' => $time];
        }
        if ($options === []) {
            throw ValidationException::withMessages(['options' => 'Enter at least one preferred date and time.']);
        }

        DB::transaction(function () use ($application, $validated, $options): void {
            $current = AdoptionApplication::whereKey($application->id)->lockForUpdate()->firstOrFail();
            if ($current->status !== ApplicationStatus::InterviewScheduled
                || ! $current->is_primary_candidate
                || ! $current->interview_date
                || $current->interview_date->lte(now())) {
                throw ValidationException::withMessages(['application' => 'Only an upcoming interview for your active application can be rescheduled.']);
            }
            if ($current->reschedule_status === 'pending') {
                throw ValidationException::withMessages(['application' => 'A reschedule request is already awaiting staff review.']);
            }
            $current->update([
                'reschedule_options' => $options,
                'reschedule_reason' => trim((string) ($validated['reason'] ?? '')) ?: null,
                'reschedule_status' => 'pending',
                'reschedule_requested_at' => now(),
                'reschedule_reviewed_at' => null,
            ]);
            AuditLogService::log($current->user_id, 'Interview Reschedule Requested', 'AdoptionApplication', $current->id);
        });

        $this->emailNotifications->staff(
            "Interview reschedule requested - application #{$application->id}",
            'Interview reschedule requested',
            ["The adopter for application #{$application->id} has requested a different interview time.", 'Review their preferred times in the protected application queue.'],
            'Review Applications',
            route('admin.applications.index', ['highlight' => $application->id]),
            false,
            'interview_reschedule_requested',
            $application->id,
        );

        return back()->with('success', 'Your reschedule request was sent to shelter staff. Your current interview time stays in place until staff confirms a change.');
    }

    public function create(Request $request, Pet $pet)
    {
        abort_if(
            $pet->availability_status !== AvailabilityStatus::Available,
            422,
            'This pet is processing an active application and is not accepting new applications.'
        );

        $profile = $request->user()->adopterProfile()->first();
        if (! $profile || ! $this->matchingProfiles->adopterIsComplete($profile)) {
            return redirect()->route('recommendation.intake', ['return_pet' => $pet->id])
                ->with('warning', 'Complete your reusable personality and household profile before applying for this pet.');
        }

        $draftKey = 'application_drafts.'.$pet->id;
        $draft = $request->session()->get($draftKey);
        if (is_array($draft) && ($draft['expires_at'] ?? 0) > now()->timestamp) {
            $request->session()->flashInput(array_merge($draft['fields'], $request->old()));
        } else {
            $request->session()->forget($draftKey);
        }

        return view('application.apply', compact('pet', 'profile'));
    }

    public function store(Request $request)
    {
        $petId = $request->validate(['pet_id' => 'required|integer|exists:pets,id'])['pet_id'];
        $pet = Pet::findOrFail($petId);
        $user = $request->user();
        $profile = $user->adopterProfile()->first();
        if (! $profile || ! $this->matchingProfiles->adopterIsComplete($profile)) {
            $this->preserveDraft($request, $pet->id);

            return redirect()->route('recommendation.intake', ['return_pet' => $pet->id])
                ->with('warning', 'Complete your reusable personality assessment before submitting. Your entered details will be restored when you return.');
        }

        $validated = $request->validate([
            'pet_id' => 'required|exists:pets,id',
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'phone_number' => 'required|string|max:50',
            'motivation_statement' => 'required|string|max:2000',
            'physical_activity_level' => 'required|string|max:255',
            'time_availability' => 'required|string|max:255',
            'prior_pet_experience' => 'required|string|max:255',
            'household_composition' => 'required|string|max:255',
            'document' => 'required|file|mimes:pdf,jpg,jpeg,png|max:10240',
            'agreed_to_terms' => 'accepted',
            ...PhilippineLocationService::validationRules(),
        ]);

        $address = $this->locations->resolveAddress($validated);

        if ($pet->availability_status !== AvailabilityStatus::Available) {
            return back()->withErrors([
                'pet_id' => 'This pet is processing an active application and is not accepting new applications.',
            ])->withInput();
        }

        $preflight = $this->matcher->calculateMatch($profile, $pet);
        if (! $preflight->eligible || $preflight->compatibilityScore === null) {
            throw ValidationException::withMessages([
                'pet_id' => MatchPresenter::reason($preflight->exclusionReason),
            ]);
        }

        $document = $request->file('document');
        $documentDisk = 'local';
        $documentPath = $document->store('adoption-documents', $documentDisk);
        try {
            $verification = $this->documentVerification->verify(
                $documentDisk,
                $documentPath,
                $document->getMimeType() ?: $document->getClientMimeType(),
                [
                    'first_name' => $validated['first_name'],
                    'last_name' => $validated['last_name'],
                    ...PhilippineAddress::ocrPayload($address),
                ]
            );

            $knnInputs = [
                'physical_activity_level' => $validated['physical_activity_level'],
                'time_availability' => $validated['time_availability'],
                'prior_pet_experience' => $validated['prior_pet_experience'],
                'household_composition' => $validated['household_composition'],
            ];

            $application = DB::transaction(function () use (
                $validated, $user, $document, $documentDisk,
                $documentPath, $verification, $address, $knnInputs
            ) {
                // This row lock makes the availability check and insert one atomic operation.
                $lockedPet = Pet::withoutGlobalScope('notArchived')
                    ->whereKey($validated['pet_id'])
                    ->lockForUpdate()
                    ->firstOrFail();

                if ($lockedPet->availability_status !== AvailabilityStatus::Available) {
                    throw ValidationException::withMessages([
                        'pet_id' => 'This pet is processing an active application and is not accepting new applications.',
                    ]);
                }

                $activeApplicationExists = AdoptionApplication::where('user_id', $user->id)
                    ->where('pet_id', $lockedPet->id)
                    ->whereNotIn('status', [
                        ApplicationStatus::Approved->value,
                        ApplicationStatus::Rejected->value,
                        ApplicationStatus::Withdrawn->value,
                        ApplicationStatus::NoShow->value,
                        ApplicationStatus::Closed->value,
                    ])->exists();

                if ($activeApplicationExists) {
                    throw ValidationException::withMessages([
                        'pet_id' => 'You already have an active application for this pet.',
                    ]);
                }

                $matchingProfile = $user->adopterProfile()->lockForUpdate()->first();
                if (! $matchingProfile || ! $this->matchingProfiles->adopterIsComplete($matchingProfile)) {
                    throw ValidationException::withMessages([
                        'profile' => 'Your personality assessment needs an update before applying.',
                    ]);
                }
                $match = $this->matcher->calculateMatch($matchingProfile, $lockedPet);
                if (! $match->eligible || $match->compatibilityScore === null) {
                    throw ValidationException::withMessages([
                        'pet_id' => MatchPresenter::reason($match->exclusionReason),
                    ]);
                }

                $matchingProfile->update([
                    'physical_activity_level' => $knnInputs['physical_activity_level'],
                    'time_availability' => $knnInputs['time_availability'],
                    'prior_pet_experience' => $knnInputs['prior_pet_experience'],
                    'household_composition' => $knnInputs['household_composition'],
                ]);

                $application = AdoptionApplication::create([
                    'user_id' => $user->id,
                    'pet_id' => $lockedPet->id,
                    'applicant_first_name' => $validated['first_name'],
                    'applicant_last_name' => $validated['last_name'],
                    'applicant_email' => $validated['email'],
                    'applicant_phone' => $validated['phone_number'],
                    ...$this->applicationAddressAttributes($address),
                    'status' => $this->applicationStatusFor($verification->status, $lockedPet),
                    'motivation_statement' => $validated['motivation_statement'],
                    'housing_type' => $matchingProfile->housing_type,
                    'income_range' => $matchingProfile->monthly_income_range,
                    'physical_activity_level' => $validated['physical_activity_level'],
                    'time_availability' => $validated['time_availability'],
                    'prior_pet_experience' => $validated['prior_pet_experience'],
                    'household_composition' => $validated['household_composition'],
                    'document_path' => $documentPath,
                    'document_disk' => $documentDisk,
                    'document_original_name' => $this->safeOriginalName($document->getClientOriginalName()),
                    'document_mime_type' => $document->getMimeType() ?: $document->getClientMimeType(),
                    ...$this->verificationAttributes($verification),
                ]);

                $this->matching->refresh($application);
                if ($application->knn_score === null || ! ($application->compatibility_result['eligible'] ?? false)) {
                    throw ValidationException::withMessages(['pet_id' => 'This pet cannot currently be matched with your profile. Please refresh and try again.']);
                }

                AuditLogService::log(
                    $user->id,
                    'Adoption Application Submitted',
                    'AdoptionApplication',
                    $application->id,
                    "Application for pet ID {$application->pet_id}; OCR status {$verification->status->value}."
                );

                return $application;
            });
        } catch (\Throwable $exception) {
            Storage::disk($documentDisk)->delete($documentPath);
            throw $exception;
        }

        $application->loadMissing(['user', 'pet']);
        $this->emailNotifications->user(
            $application->user,
            "Application received for {$application->pet?->name}",
            'We received your adoption application',
            [
                "Your application #{$application->id} for {$application->pet?->name} was received successfully.",
                'We will notify you as document verification and shelter review progress.',
            ],
            'View My Applications',
            route('application.index'),
            'application_received',
            $application->id,
        );
        $this->emailNotifications->staff(
            "New adoption application #{$application->id}",
            'A new adoption application was received',
            [
                "Application #{$application->id} for {$application->pet?->name} is now in the intake workflow.",
                'Sign in to review its current document-verification state.',
            ],
            'Review Applications',
            route('admin.applications.index'),
            true,
            'application_received_staff',
            $application->id,
        );
        $this->documentNotifications->send($application);
        $request->session()->forget('application_drafts.'.$application->pet_id);

        [$flashType, $message] = match ($verification->status) {
            DocumentVerificationStatus::Verified => [
                'success',
                'Your document was verified and the application is pending shelter review.',
            ],
            DocumentVerificationStatus::NeedsResubmission => [
                'warning',
                'Your application was saved, but OCR could not verify the document. Open the verification notice below and upload a clearer replacement.',
            ],
            default => [
                'warning',
                'Your application was saved. Automatic OCR is unavailable, so authorized shelter staff will review the private document. You do not need to reupload it now.',
            ],
        };

        return redirect()->route('application.index')->with($flashType, $message);
    }

    public function replaceDocument(Request $request, AdoptionApplication $application)
    {
        abort_unless($application->user_id === Auth::id(), 403);

        if ($application->document_verification_status !== DocumentVerificationStatus::NeedsResubmission
            || $application->status !== ApplicationStatus::DocumentFlagged) {
            throw ValidationException::withMessages([
                'document' => 'This application is not currently requesting a replacement document.',
            ]);
        }

        $maximumReuploads = (int) config('document_verification.maximum_adopter_reuploads', 1);
        if ($application->document_reupload_count >= $maximumReuploads) {
            throw ValidationException::withMessages([
                'document' => 'A follow-up document has already been submitted. Shelter staff must review the application before any further action.',
            ]);
        }

        $validated = $request->validate([
            'document' => 'required|file|mimes:pdf,jpg,jpeg,png|max:10240',
        ]);
        $document = $validated['document'];
        $newDisk = 'local';
        $newPath = $document->store('adoption-documents', $newDisk);
        $verification = $this->documentVerification->verify(
            $newDisk,
            $newPath,
            $document->getMimeType() ?: $document->getClientMimeType(),
            [
                'first_name' => $application->first_name,
                'last_name' => $application->last_name,
                ...$application->ocr_address_payload,
            ]
        );

        $oldDisk = $application->document_disk ?: 'public';
        $oldPath = $application->document_path;

        try {
            DB::transaction(function () use (
                $application,
                $document,
                $newDisk,
                $newPath,
                $oldDisk,
                $oldPath,
                $verification,
                $maximumReuploads
            ) {
                $locked = AdoptionApplication::lockForUpdate()->findOrFail($application->id);
                abort_unless($locked->user_id === Auth::id(), 403);
                $pet = Pet::withoutGlobalScope('notArchived')->lockForUpdate()->findOrFail($locked->pet_id);

                if ($locked->status !== ApplicationStatus::DocumentFlagged
                    || $locked->document_verification_status !== DocumentVerificationStatus::NeedsResubmission) {
                    throw ValidationException::withMessages(['document' => 'The application state changed. Please refresh and try again.']);
                }
                if ($locked->document_path !== $oldPath
                    || ($locked->document_disk ?: 'public') !== $oldDisk) {
                    throw ValidationException::withMessages(['document' => 'The document changed while OCR was running. Please refresh and try again.']);
                }
                if ($locked->document_reupload_count >= $maximumReuploads) {
                    throw ValidationException::withMessages([
                        'document' => 'A follow-up document has already been submitted. Shelter staff must review the application before any further action.',
                    ]);
                }

                $locked->update([
                    'status' => $this->applicationStatusFor($verification->status, $pet),
                    'document_path' => $newPath,
                    'document_disk' => $newDisk,
                    'document_original_name' => $this->safeOriginalName($document->getClientOriginalName()),
                    'document_mime_type' => $document->getMimeType() ?: $document->getClientMimeType(),
                    'document_reupload_count' => $locked->document_reupload_count + 1,
                    ...$this->verificationAttributes($verification),
                ]);

                AuditLogService::log(
                    Auth::id(),
                    'Adoption Document Replaced',
                    'AdoptionApplication',
                    $locked->id,
                    "Replacement OCR status: {$verification->status->value}."
                );
            });
        } catch (\Throwable $exception) {
            Storage::disk($newDisk)->delete($newPath);
            throw $exception;
        }

        if ($oldPath && ($oldDisk !== $newDisk || $oldPath !== $newPath)) {
            Storage::disk($oldDisk)->delete($oldPath);
        }

        $application->refresh();
        $this->documentNotifications->send($application);

        return back()->with(
            $verification->isVerified() ? 'success' : 'warning',
            match ($verification->status) {
                DocumentVerificationStatus::Verified => 'The replacement document was verified. Your application can now proceed.',
                DocumentVerificationStatus::NeedsResubmission => 'The one allowed follow-up document was saved but could not be verified. Shelter staff will review it; no additional uploads are permitted.',
                default => 'The replacement was saved. Automatic OCR is unavailable, so authorized shelter staff will review it. You do not need to upload it again now.',
            }
        );
    }

    public function mine()
    {
        return redirect()->route('application.index');
    }

    private function verificationAttributes(DocumentVerificationResult $result): array
    {
        return [
            'document_verification_status' => $result->status->value,
            'document_type' => $result->documentType,
            'ocr_extracted_text' => $result->extractedText,
            'ocr_confidence' => $result->ocrConfidence,
            'document_match_score' => $result->matchScore,
            'document_verification_reasons' => $result->reasons,
            'document_uploaded_at' => now(),
            'document_verified_at' => $result->isVerified() ? now() : null,
        ];
    }

    private function applicationAddressAttributes(array $address): array
    {
        return [
            'applicant_region' => $address['region'],
            'applicant_province' => $address['province'],
            'applicant_city_municipality' => $address['city_municipality'],
            'applicant_barangay' => $address['barangay'],
            'applicant_street_address' => $address['street_address'],
            'applicant_zip_code' => $address['zip_code'],
        ];
    }

    private function applicationStatusFor(DocumentVerificationStatus $status, Pet $pet): string
    {
        if ($status === DocumentVerificationStatus::NeedsResubmission) {
            return ApplicationStatus::DocumentFlagged->value;
        }

        $hasPrimary = AdoptionApplication::where('pet_id', $pet->id)
            ->where('is_primary_candidate', true)
            ->exists();

        return $hasPrimary || $pet->availability_status === AvailabilityStatus::SoftReserved
            ? ApplicationStatus::Waitlisted->value
            : ApplicationStatus::Pending->value;
    }

    private function safeOriginalName(string $name): string
    {
        $basename = pathinfo(str_replace('\\', '/', $name), PATHINFO_BASENAME);

        return mb_substr((string) preg_replace('/[^\pL\pN._ -]+/u', '_', $basename), 0, 255);
    }

    private function preserveDraft(Request $request, int $petId): void
    {
        $fields = $request->only([
            'first_name', 'last_name', 'email', 'phone_number', 'region_code', 'province_code',
            'city_municipality_code', 'barangay_code', 'street_address', 'zip_code',
            'motivation_statement', 'physical_activity_level', 'time_availability',
            'prior_pet_experience', 'household_composition',
        ]);
        $request->session()->put('application_drafts.'.$petId, [
            'fields' => array_filter($fields, fn ($value) => is_string($value) && mb_strlen($value) <= 2000),
            'expires_at' => now()->addMinutes(30)->timestamp,
        ]);
    }
}
