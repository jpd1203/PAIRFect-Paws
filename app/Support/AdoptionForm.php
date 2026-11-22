<?php

namespace App\Support;

use App\Services\PhilippineLocationService;

final class AdoptionForm
{
    public const DOCUMENT_RULE = 'file|mimes:pdf,jpg,jpeg,png|max:10240';

    public static function rules(): array
    {
        return [
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'phone_number' => 'required|string|max:50',
            'motivation_statement' => 'required|string|max:2000',
            'physical_activity_level' => 'required|string|max:255',
            'time_availability' => 'required|string|max:255',
            'prior_pet_experience' => 'required|string|max:255',
            'household_composition' => 'required|string|max:255',
            'document' => 'required|'.self::DOCUMENT_RULE,
            'agreed_to_terms' => 'accepted',
            ...PhilippineLocationService::validationRules(),
        ];
    }
}
