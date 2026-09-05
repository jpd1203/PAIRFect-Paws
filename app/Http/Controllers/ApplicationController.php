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
use App\Services\PhilippineLocationService;
use App\Support\PhilippineAddress;
use App\ValueObjects\DocumentVerificationResult;
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
        private KnnRecommendationService $knn,
    ) {}

    public function index()
    {
        $applications = AdoptionApplication::with('pet')
            ->where('user_id', Auth::id())
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get();

        return view('application.index', compact('applications'));
    }

    public function create(Pet $pet)
    {
        abort_if(
            $pet->availability_status !== AvailabilityStatus::Available,
            422,
            'This pet is processing an active application and is not accepting new applications.'
        );

        return view('application.apply', compact('pet'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'pet_id' => 'required|exists:pets,id',
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'phone_number' => 'required|string|max:50',
            'motivation_statement' => 'required|string|max:2000',
            'housing_type' => 'required|string|max:255',
            'physical_activity_level' => 'required|string|max:255',
            'time_availability' => 'required|string|max:255',
            'prior_pet_experience' => 'required|string|max:255',
            'household_composition' => 'required|string|max:255',
            'monthly_income_range' => 'nullable|required_without:income_range|string|max:255',
            'income_range' => 'nullable|required_without:monthly_income_range|string|max:255',
            'document' => 'required|file|mimes:pdf,jpg,jpeg,png|max:10240',
            'agreed_to_animal_welfare_act' => 'accepted',
            ...PhilippineLocationService::validationRules(),
        ]);

        $address = $this->locations->resolveAddress($validated);

        $user = Auth::user();
        $pet = Pet::findOrFail($validated['pet_id']);
        $incomeRange = $validated['monthly_income_range'] ?? $validated['income_range'];

        if ($pet->availability_status !== AvailabilityStatus::Available) {
            return back()->withErrors([
                'pet_id' => 'This pet is processing an active application and is not accepting new applications.',
            ])->withInput();
        }

        $document = $request->file('document');
        $documentDisk = 'local';
        $documentPath = $document->store('adoption-documents', $documentDisk);
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
            'housing_type' => $validated['housing_type'],
            'household_composition' => $validated['household_composition'],
            'monthly_income_range' => $incomeRange,
            'has_existing_pets' => in_array($validated['prior_pet_experience'], [
                'Currently own pets',
                'Experienced with rescue/special needs animals',
            ], true) ? 'yes' : 'no',
        ];
        $isRecommendationEligible = Pet::recommendationEligible()->whereKey($pet->id)->exists();
        $knnScore = $isRecommendationEligible
            ? $this->knn->distanceFor($pet, $knnInputs)
            : null;

        try {
            $application = DB::transaction(function () use (
                $validated, $user, $knnScore, $document, $documentDisk,
                $documentPath, $incomeRange, $verification, $address, $knnInputs
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

                $user->adopterProfile()->updateOrCreate([], [
                    'physical_activity_level' => $knnInputs['physical_activity_level'],
                    'time_availability' => $knnInputs['time_availability'],
                    'prior_pet_experience' => $knnInputs['prior_pet_experience'],
                    'housing_type' => $knnInputs['housing_type'],
                    'household_composition' => $knnInputs['household_composition'],
                    'monthly_income_range' => $knnInputs['monthly_income_range'],
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
                    'housing_type' => $validated['housing_type'],
                    'income_range' => $incomeRange,
                    'physical_activity_level' => $validated['physical_activity_level'],
                    'time_availability' => $validated['time_availability'],
                    'prior_pet_experience' => $validated['prior_pet_experience'],
                    'household_composition' => $validated['household_composition'],
                    'knn_score' => $knnScore,
                    'document_path' => $documentPath,
                    'document_disk' => $documentDisk,
                    'document_original_name' => $this->safeOriginalName($document->getClientOriginalName()),
                    'document_mime_type' => $document->getMimeType() ?: $document->getClientMimeType(),
                    ...$this->verificationAttributes($verification),
                ]);

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

        [$flashType, $message] = match ($verification->status) {
            DocumentVerificationStatus::Verified => [
                'success',
                'Your document was verified and the application is now under review.',
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
        if ($status !== DocumentVerificationStatus::Verified) {
            return ApplicationStatus::Pending->value;
        }

        $hasPrimary = AdoptionApplication::where('pet_id', $pet->id)
            ->where('is_primary_candidate', true)
            ->exists();

        return $hasPrimary || $pet->availability_status === AvailabilityStatus::SoftReserved
            ? ApplicationStatus::Waitlisted->value
            : ApplicationStatus::UnderReview->value;
    }

    private function safeOriginalName(string $name): string
    {
        $basename = pathinfo(str_replace('\\', '/', $name), PATHINFO_BASENAME);

        return mb_substr((string) preg_replace('/[^\pL\pN._ -]+/u', '_', $basename), 0, 255);
    }
}
