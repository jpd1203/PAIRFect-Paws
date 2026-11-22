<?php

namespace App\Models;

use App\Enums\ApplicationStatus;
use App\Enums\DocumentVerificationStatus;

/**
 * Research application and simulated handover state. Real application queries,
 * staff tools, and background jobs continue to use the production table.
 */
final class ResearchPlacement extends AdoptionApplication
{
    protected $table = 'research_placements';

    protected $fillable = ['user_id', 'pet_id', 'status', 'is_primary_candidate', 'compatibility_result', 'compatibility_score', 'algorithm_version', 'queue_promoted_at', 'queue_closed_at', 'approved_at', 'released_at',
        'application_details', 'document_verification_status', 'document_verification_reasons', 'document_consent_at', 'document_consent_version', 'document_reupload_count'];

    protected $hidden = ['application_details'];

    protected function casts(): array
    {
        return [...parent::casts(), 'application_details' => 'encrypted:array', 'document_consent_at' => 'datetime'];
    }

    public function acceptsResearchReplacement(): bool
    {
        return ! in_array($this->status, [ApplicationStatus::Closed, ApplicationStatus::Withdrawn], true)
            && in_array($this->document_verification_status, [DocumentVerificationStatus::NeedsResubmission, DocumentVerificationStatus::ManualReview], true)
            && is_array($this->application_details)
            && $this->document_reupload_count < (int) config('document_verification.maximum_adopter_reuploads', 1);
    }
}
