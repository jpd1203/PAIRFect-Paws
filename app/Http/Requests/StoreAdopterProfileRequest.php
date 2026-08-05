<?php

namespace App\Http\Requests;

use App\Support\ApplicationOptions;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAdopterProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'physical_activity_level' => ['required', Rule::in(ApplicationOptions::PHYSICAL_ACTIVITY_LEVELS)],
            'time_availability' => ['required', Rule::in(ApplicationOptions::TIME_AVAILABILITY_OPTIONS)],
            'prior_pet_experience' => ['required', Rule::in(ApplicationOptions::PRIOR_EXPERIENCE_OPTIONS)],
            'housing_type' => ['required', Rule::in(ApplicationOptions::HOUSING_TYPES)],
            'household_composition' => ['required', Rule::in(ApplicationOptions::HOUSEHOLD_COMPOSITIONS)],
            'monthly_income_range' => ['required', Rule::in(ApplicationOptions::INCOME_RANGES)],
        ];
    }
}
