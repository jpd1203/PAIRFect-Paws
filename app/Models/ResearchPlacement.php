<?php

namespace App\Models;

use App\Enums\ResolutionOutcome;
use App\Support\PhilippineAddress;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Research application and simulated handover state. Real application queries,
 * staff tools, and background jobs continue to use the production table.
 */
final class ResearchPlacement extends AdoptionApplication
{
    protected $table = 'research_placements';

    protected $fillable = ['user_id', 'pet_id', 'status', 'is_primary_candidate', 'compatibility_result', 'compatibility_score', 'algorithm_version', 'queue_promoted_at', 'queue_closed_at', 'approved_at', 'released_at',
        'application_details', 'document_verification_status', 'document_verification_reasons', 'document_consent_at', 'document_consent_version', 'document_reupload_count',
        'document_path', 'document_mime_type', 'document_type', 'document_uploaded_at', 'document_match_score',
        'handover_schedule', 'handover_receipt'];

    protected $hidden = ['application_details', 'handover_receipt', 'document_path'];

    protected function casts(): array
    {
        return [
            ...parent::casts(), 'application_details' => 'encrypted:array', 'document_consent_at' => 'datetime',
            'approved_at' => 'datetime', 'released_at' => 'datetime', 'received_at' => 'datetime',
            'handover_schedule' => 'array', 'handover_receipt' => 'encrypted:array',
        ];
    }

    public function getFirstNameAttribute(): string
    {
        return $this->application_details['first_name'] ?? parent::getFirstNameAttribute();
    }

    public function getLastNameAttribute(): string
    {
        return $this->application_details['last_name'] ?? parent::getLastNameAttribute();
    }

    public function getEmailAttribute(): string
    {
        return $this->application_details['email'] ?? parent::getEmailAttribute();
    }

    public function getPhoneNumberAttribute(): ?string
    {
        return $this->application_details['phone_number'] ?? null;
    }

    public function getAddressComponentsAttribute(): array
    {
        return PhilippineAddress::normalize($this->application_details['address'] ?? []);
    }

    public function getKnnScoreAttribute(): ?float
    {
        return $this->compatibility_score !== null ? (float) $this->compatibility_score : null;
    }

    public function canUploadReplacementDocument(): bool
    {
        return false;
    }

    public function getSubmittedOnAttribute()
    {
        return $this->created_at ?? $this->approved_at;
    }

    public function getLastUpdatedAttribute()
    {
        return $this->updated_at ?? $this->received_at ?? $this->released_at ?? $this->approved_at ?? $this->created_at;
    }

    public function researchCheckIns(): HasMany
    {
        return $this->hasMany(ResearchCheckIn::class, 'placement_id');
    }

    public function hasScenarioReturn(): bool
    {
        return $this->researchCheckIns()->where('resolution_outcome', ResolutionOutcome::PetReturned->value)->exists();
    }
}
