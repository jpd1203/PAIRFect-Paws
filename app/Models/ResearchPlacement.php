<?php

namespace App\Models;

use App\Support\PhilippineAddress;

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
        return [
            ...parent::casts(), 'application_details' => 'encrypted:array', 'document_consent_at' => 'datetime',
            'approved_at' => 'datetime', 'released_at' => 'datetime', 'received_at' => 'datetime',
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
}
