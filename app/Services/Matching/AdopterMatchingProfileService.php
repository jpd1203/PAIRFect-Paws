<?php

namespace App\Services\Matching;

use App\Models\AdopterProfile;
use App\Models\User;
use App\Support\ApplicationOptions;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

final class AdopterMatchingProfileService
{
    public function __construct(private BfiScorer $bfi, private MatchingConfiguration $config) {}

    public function save(User $user, array $input): AdopterProfile
    {
        $rules = [
            'physical_activity_level' => ['sometimes', 'nullable', Rule::in(ApplicationOptions::PHYSICAL_ACTIVITY_LEVELS)],
            'time_availability' => ['sometimes', 'nullable', Rule::in(ApplicationOptions::TIME_AVAILABILITY_OPTIONS)],
            'prior_pet_experience' => ['sometimes', 'nullable', Rule::in(ApplicationOptions::PRIOR_EXPERIENCE_OPTIONS)],
            'housing_type' => ['required', Rule::in(ApplicationOptions::HOUSING_TYPES)],
            'household_composition' => ['sometimes', 'nullable', Rule::in(ApplicationOptions::HOUSEHOLD_COMPOSITIONS)],
            'monthly_income_range' => ['required', Rule::in(ApplicationOptions::INCOME_RANGES)],
            'has_existing_pets' => ['required', 'boolean'],
            'has_children' => ['required', 'boolean'],
            'bfi_responses' => ['required', 'array:'.implode(',', array_keys($this->config->values['bfi']['prompts']))],
        ];
        foreach ($this->config->values['bfi']['dimensions'] as $keys) {
            foreach ($keys as $key) {
                $rules["bfi_responses.{$key}"] = ['required', 'integer', 'between:1,5'];
            }
        }
        $validated = Validator::make($input, $rules)->validate();
        $scores = $this->bfi->score($validated['bfi_responses']);

        return $user->adopterProfile()->updateOrCreate([], [
            ...$validated, ...$scores,
            'financial_readiness' => $this->config->values['financial_levels'][$validated['monthly_income_range']],
            'bfi_completed_at' => now(),
        ]);
    }
}
