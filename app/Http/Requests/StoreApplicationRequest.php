<?php

namespace App\Http\Requests;

use App\Support\ApplicationOptions;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreApplicationRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Only authenticated adopters may submit; ownership of the pet
        // record itself is checked in the controller (findOrFail + status).
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'pet_id' => ['required', 'integer', 'exists:pets,id'],
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:190'],
            'phone_number' => ['required', 'string', 'max:20', 'regex:/^[0-9+\-\s()]+$/'],
            'address' => ['required', 'string', 'max:255'],

            'housing_type' => ['required', Rule::in(ApplicationOptions::HOUSING_TYPES)],
            'other_pets' => ['required', Rule::in(ApplicationOptions::OTHER_PETS_OPTIONS)],
            'household_size' => ['required', Rule::in(ApplicationOptions::HOUSEHOLD_SIZES)],
            'monthly_income_range' => ['required', Rule::in(ApplicationOptions::INCOME_RANGES)],
            'prior_pet_experience' => ['required', Rule::in(ApplicationOptions::PRIOR_EXPERIENCE_OPTIONS)],

            // document upload: allow-list mime types, cap size (5MB), never trust client extension alone
            'document' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],

            'agreed_to_animal_welfare_act' => ['accepted'],
        ];
    }

    public function messages(): array
    {
        return [
            'agreed_to_animal_welfare_act.accepted' => 'You must agree to the Animal Welfare Act (RA 8485) to submit an application.',
            'document.mimes' => 'Please upload a PDF, JPG, or PNG file.',
            'document.max' => 'The uploaded file must not exceed 5MB.',
        ];
    }
}
