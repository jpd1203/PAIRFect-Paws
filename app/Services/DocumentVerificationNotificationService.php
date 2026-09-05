<?php

namespace App\Services;

use App\Enums\ApplicationStatus;
use App\Enums\DocumentVerificationStatus;
use App\Models\AdoptionApplication;

class DocumentVerificationNotificationService
{
    public function __construct(private EmailNotificationService $notifications) {}

    public function send(AdoptionApplication $application): void
    {
        $application->loadMissing(['user', 'pet']);

        if ($application->document_verification_status === DocumentVerificationStatus::Verified) {
            $isWaitlisted = $application->status === ApplicationStatus::Waitlisted;
            $this->notifications->user(
                $application->user,
                $isWaitlisted
                    ? "Document verified - application #{$application->id} is waitlisted"
                    : "Document verified for application #{$application->id}",
                $isWaitlisted ? 'Your document was verified and you are on the waitlist' : 'Your supporting document was verified',
                [
                    "The supporting document for your application for {$application->pet?->name} matched the details you provided.",
                    $isWaitlisted
                        ? 'Another primary application is already active, so yours remains eligible in the first-come, first-served waitlist.'
                        : 'Your application can now proceed to shelter review.',
                ],
                'View My Applications',
                route('application.index'),
                'document_verified_adopter',
                $application->id,
            );
            $this->notifications->staff(
                "Verified adoption document - application #{$application->id}",
                'An application is ready for staff review',
                [
                    "Document verification passed for application #{$application->id} for {$application->pet?->name}.",
                    'Sign in to review it. Sensitive OCR data is available only in the protected staff area.',
                ],
                'Review Applications',
                route('admin.applications.index'),
                true,
                'document_verified_staff',
                $application->id,
            );

            return;
        }

        if ($application->document_verification_status === DocumentVerificationStatus::NeedsResubmission) {
            $reasons = implode(' ', $application->document_verification_reasons ?? []);

            if ($application->document_reupload_count >= config('document_verification.maximum_adopter_reuploads', 1)) {
                $this->notifications->user(
                    $application->user,
                    'Adoption document awaiting staff review',
                    'Your follow-up document needs staff review',
                    array_values(array_filter([
                        "The one allowed follow-up document for your application for {$application->pet?->name} was received but could not be automatically verified.",
                        $reasons,
                        'Additional uploads are blocked while authorized shelter staff review it.',
                    ])),
                    'View Verification Notice',
                    route('application.index'),
                    'document_followup_manual_review_adopter',
                    $application->id,
                );
                $this->notifications->staff(
                    "Follow-up document needs staff review - application #{$application->id}",
                    'A follow-up document needs manual review',
                    [
                        "Application #{$application->id} used its one follow-up upload and still needs document review.",
                        'Open the protected verification screen to review the document and OCR notes.',
                    ],
                    'Review Applications',
                    route('admin.applications.index'),
                    true,
                    'document_followup_manual_review_staff',
                    $application->id,
                );

                return;
            }

            $this->notifications->user(
                $application->user,
                'Updated adoption document required',
                'Please replace your supporting document',
                array_values(array_filter([
                    "We could not verify the document for your application for {$application->pet?->name}.",
                    $reasons,
                    'Confirm that the name and address entered are correct, then upload one clear replacement from My Applications. Contact shelter staff if the application details need correction.',
                ])),
                'Update Document',
                route('application.index'),
                'document_replacement_required',
                $application->id,
            );

            return;
        }

        if ($application->document_verification_status === DocumentVerificationStatus::ManualReview) {
            $this->notifications->staff(
                "Manual document review required - application #{$application->id}",
                'Automatic document verification was unavailable',
                [
                    "Application #{$application->id} requires manual document review.",
                    'Sign in to the protected verification screen; document contents are intentionally not included in email.',
                ],
                'Review Applications',
                route('admin.applications.index'),
                true,
                'document_manual_review_staff',
                $application->id,
            );
        }
    }
}
