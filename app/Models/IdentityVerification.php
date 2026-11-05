<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IdentityVerification extends Model
{
    public const CHECKS = ['government_id_presented', 'applicant_matches_id_photo', 'name_matches_application', 'submitted_document_consistent', 'no_material_discrepancy'];

    protected $guarded = ['id'];

    protected $hidden = ['discrepancy_note', 'document_fingerprint'];

    protected function casts(): array
    {
        return [
            ...array_fill_keys(self::CHECKS, 'boolean'),
            'verified_at' => 'datetime', 'release_attempt' => 'integer',
        ];
    }

    public function application()
    {
        return $this->belongsTo(AdoptionApplication::class, 'application_id');
    }

    public function verifier()
    {
        return $this->belongsTo(User::class, 'verified_by_user_id');
    }
}
