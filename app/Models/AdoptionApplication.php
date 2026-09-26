<?php

namespace App\Models;

use App\Enums\ApplicationStatus;
use App\Enums\DocumentVerificationStatus;
use App\Support\ManilaTime;
use App\Support\PhilippineAddress;
use Illuminate\Database\Eloquent\Model;

class AdoptionApplication extends Model
{
    protected $fillable = [
        'user_id', 'pet_id', 'applicant_first_name', 'applicant_last_name',
        'applicant_email', 'applicant_phone', 'applicant_region',
        'applicant_province', 'applicant_city_municipality', 'applicant_barangay',
        'applicant_street_address', 'applicant_zip_code',
        'status', 'motivation_statement',
        'housing_type', 'income_range', 'knn_score', 'compatibility_result', 'document_path',
        'physical_activity_level', 'time_availability', 'prior_pet_experience',
        'household_composition', 'document_disk', 'document_original_name',
        'document_mime_type', 'document_verification_status', 'document_type',
        'ocr_extracted_text', 'ocr_confidence', 'document_match_score',
        'document_verification_reasons', 'document_uploaded_at',
        'document_verified_at', 'document_reupload_count',
        'interview_date', 'interview_notes', 'conducted_by', 'decision_remarks', 'version',
        'is_primary_candidate', 'queue_promoted_at', 'admin_review_flagged_at',
        'queue_closed_at', 'adopted_at', 'override_reason',
    ];

    protected function casts(): array
    {
        return [
            'status' => ApplicationStatus::class,
            'interview_date' => 'datetime',
            'knn_score' => 'float',
            'compatibility_result' => 'array',
            'is_primary_candidate' => 'boolean',
            'queue_promoted_at' => 'datetime',
            'admin_review_flagged_at' => 'datetime',
            'queue_closed_at' => 'datetime',
            'adopted_at' => 'datetime',
            'document_verification_status' => DocumentVerificationStatus::class,
            'ocr_extracted_text' => 'encrypted',
            'ocr_confidence' => 'float',
            'document_match_score' => 'float',
            'document_verification_reasons' => 'array',
            'document_uploaded_at' => 'datetime',
            'document_verified_at' => 'datetime',
            'document_reupload_count' => 'integer',
        ];
    }

    public function canUploadReplacementDocument(): bool
    {
        return $this->document_verification_status === DocumentVerificationStatus::NeedsResubmission
            && $this->status === ApplicationStatus::DocumentFlagged
            && $this->document_reupload_count < config('document_verification.maximum_adopter_reuploads', 1);
    }

    // Relationships
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function pet()
    {
        // Applications and post-adoption records are historical records. Their
        // pet must remain available even after the catalog entry is archived.
        return $this->belongsTo(Pet::class)->withoutGlobalScope('notArchived');
    }

    public function postAdoptionLogs()
    {
        return $this->hasMany(PostAdoptionLog::class, 'application_id');
    }

    public function handover()
    {
        return $this->hasOne(Handover::class, 'application_id');
    }

    // ─── Constants used by frontend Blade templates ────────────────────────

    const STATUS_PENDING = 0;

    const STATUS_SCHEDULED = 1;

    const STATUS_UNDER_REVIEW = 2;

    const STATUS_APPROVED = 3;

    const STATUS_REJECTED = 4;

    // ─── Accessors used by frontend Blade templates ────────────────────────

    public function getFirstNameAttribute(): string
    {
        return $this->applicant_first_name ?? $this->user?->first_name ?? 'Unknown';
    }

    public function getLastNameAttribute(): string
    {
        return $this->applicant_last_name ?? $this->user?->last_name ?? '';
    }

    public function getEmailAttribute(): string
    {
        return $this->applicant_email ?? $this->user?->email ?? '';
    }

    public function getStatusSlugAttribute(): string
    {
        $val = $this->status?->value ?? $this->attributes['status'] ?? 'Pending';
        if ($val === ApplicationStatus::InterviewScheduled->value) {
            return 'scheduled';
        }

        return strtolower(str_replace([' ', '_'], '', $val));
    }

    public function getStatusDisplayAttribute(): string
    {
        $val = $this->status?->value ?? $this->attributes['status'] ?? 'Pending';

        return match ($val) {
            'InterviewScheduled' => 'Scheduled',
            'UnderReview' => 'Under Review',
            'DocumentFlagged' => 'Document Update Required',
            'PrimaryCandidate' => 'Primary Candidate',
            'NoShow' => 'No Show',
            'Closed' => 'Closed — Pet Adopted',
            default => $val,
        };
    }

    public function getStatusBadgeClassAttribute(): string
    {
        return 'badge-'.$this->status_slug;
    }

    public function getSubmittedOnAttribute()
    {
        return $this->created_at;
    }

    public function getLastUpdatedAttribute()
    {
        return $this->updated_at;
    }

    // Stub accessors for fields the frontend references
    public function getPhoneNumberAttribute(): ?string
    {
        return $this->applicant_phone;
    }

    /** @return array<string, string|null> */
    public function getAddressComponentsAttribute(): array
    {
        return PhilippineAddress::fromAttributes($this->attributes, 'applicant');
    }

    public function getAddressAttribute(): ?string
    {
        return PhilippineAddress::format($this->getAddressComponentsAttribute());
    }

    /** @return array{address: string|null, address_components: array<string, string|null>} */
    public function getOcrAddressPayloadAttribute(): array
    {
        return PhilippineAddress::ocrPayload($this->getAddressComponentsAttribute());
    }

    public function getHousingTypeDisplayAttribute(): ?string
    {
        return $this->housing_type;
    }

    public function getMonthlyIncomeRangeAttribute(): ?string
    {
        return $this->income_range;
    }

    // public function getCompatibilityResultAttribute()
    // {
    //     return null;
    // }

    public function getPriorHistoryAttribute()
    {
        if (!$this->user_id) return 0;
        return $this->user->adoptionApplications()
            ->where('status', \App\Enums\ApplicationStatus::Approved->value)
            ->where('id', '!=', $this->id)
            ->count();
    }

    public function getInterviewDateDisplayAttribute(): ?string
    {
        return $this->interview_date
            ? ManilaTime::format($this->interview_date, 'F j, Y')
            : null;
    }

    public function getInterviewTimeDisplayAttribute(): ?string
    {
        return $this->interview_date
            ? ManilaTime::format($this->interview_date, 'g:i A')
            : null;
    }
}
