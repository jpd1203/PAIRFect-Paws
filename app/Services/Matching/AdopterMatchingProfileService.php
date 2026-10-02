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
        $messages = [
            'housing_type.required' => 'Please select your housing type.',
            'monthly_income_range.required' => 'Please select your monthly income range.',
            'has_existing_pets.required' => 'Please indicate if you currently have pets at home.',
            'has_children.required' => 'Please indicate if children live in or regularly stay in your home.',
            'bfi_responses.required' => 'Please answer all 20 personality questions before starting matching.',
        ];
        $attributes = [
            'housing_type' => 'Housing Type',
            'monthly_income_range' => 'Monthly Income Range',
            'has_existing_pets' => 'Existing Pets',
            'has_children' => 'Children in Home',
            'bfi_responses' => 'Personality Questionnaire',
        ];

        $i = 1;
        foreach ($this->config->values['bfi']['prompts'] as $key => $prompt) {
            $attributes["bfi_responses.{$key}"] = "Question {$i} (\"{$prompt}\")";
            $messages["bfi_responses.{$key}.required"] = "Please answer question {$i}: {$prompt}.";
            $messages["bfi_responses.{$key}.between"] = "Question {$i} must have a response from 1 to 5.";
            $messages["bfi_responses.{$key}.integer"] = "Question {$i} must have a response from 1 to 5.";
            $i++;
        }

        foreach ($this->config->values['bfi']['dimensions'] as $keys) {
            foreach ($keys as $key) {
                $rules["bfi_responses.{$key}"] = ['required', 'integer', 'between:1,5'];
            }
        }
        $validated = Validator::make($input, $rules, $messages, $attributes)->validate();
        $scores = $this->bfi->score($validated['bfi_responses']);

        return $user->adopterProfile()->updateOrCreate([], [
            ...$validated, ...$scores,
            'financial_readiness' => $this->config->values['financial_levels'][$validated['monthly_income_range']],
            'bfi_completed_at' => now(),
        ]);
    }
}
