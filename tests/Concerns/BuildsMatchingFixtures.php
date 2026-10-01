<?php

namespace Tests\Concerns;

use App\Enums\Role;
use App\Models\AdopterProfile;
use App\Models\AssessmentRecord;
use App\Models\Pet;
use App\Models\User;
use Illuminate\Support\Str;

trait BuildsMatchingFixtures
{
    protected function matchingUser(Role $role = Role::Adopter): User
    {
        return User::create([
            'first_name' => 'Matching', 'last_name' => 'Tester', 'email' => Str::uuid().'@example.test',
            'password' => 'Testing123!', 'role' => $role->value, 'email_verified_at' => now(), 'is_active' => true,
        ]);
    }

    protected function bfiResponses(int $value = 3): array
    {
        return array_fill_keys(array_merge(...array_values(config('matching.bfi.dimensions'))), $value);
    }

    protected function behaviorResponses(string $species = 'dog', int $value = 2): array
    {
        return array_map(fn ($items) => array_fill_keys(array_keys($items), $value), config("matching.items.{$species}"));
    }

    protected function matchingAdopter(array $overrides = []): AdopterProfile
    {
        return AdopterProfile::create(array_replace([
            'user_id' => $this->matchingUser()->id,
            'bfi_responses' => $this->bfiResponses(),
            'extraversion' => 3, 'conscientiousness' => 3, 'neuroticism' => 3, 'openness' => 3,
            'bfi_completed_at' => now(), 'has_existing_pets' => false, 'has_children' => false,
            'financial_readiness' => 3, 'housing_type' => 'Single Family Home (Fenced Yard)',
        ], $overrides));
    }

    protected function completeMatchingProfile(User $user, array $overrides = []): AdopterProfile
    {
        return AdopterProfile::updateOrCreate(['user_id' => $user->id], array_replace([
            'bfi_responses' => $this->bfiResponses(),
            'extraversion' => 3, 'conscientiousness' => 3, 'neuroticism' => 3, 'openness' => 3,
            'bfi_completed_at' => now(), 'has_existing_pets' => false, 'has_children' => false,
            'financial_readiness' => 3, 'housing_type' => 'Single Family Home (Fenced Yard)',
            'monthly_income_range' => '₱30,000 - ₱50,000',
        ], $overrides));
    }

    protected function completePetAssessment(Pet $pet): Pet
    {
        $pet->forceFill([
            'physical_size' => 'Medium', 'medical_needs' => 2, 'life_stage' => 'adult',
            'has_aggression_history' => false, 'aggression_history_verified_at' => now(), 'high_vocalization' => false,
        ])->saveQuietly();
        for ($i = 0; $i < 3; $i++) {
            AssessmentRecord::create([
                'pet_id' => $pet->id, 'assessor_id' => $this->matchingUser(Role::Volunteer)->id,
                'responses' => [
                    'species' => strtolower($pet->species->value),
                    'answers' => $this->behaviorResponses(strtolower($pet->species->value)),
                ],
                'scoring_version' => config('matching.algorithm_version'),
            ]);
        }

        return $pet->fresh()->load('assessmentRecords');
    }

    protected function matchingPet(array $overrides = [], int $observers = 3): Pet
    {
        $pet = Pet::create(array_replace([
            'name' => 'Matching Pet', 'species' => 'Dog', 'age' => 36, 'sex' => 'Male',
            'availability_status' => 'Available', 'physical_size' => 'Medium',
            'medical_needs' => 3, 'life_stage' => 'adult', 'has_aggression_history' => false,
            'high_vocalization' => false,
        ], $overrides));
        for ($i = 0; $i < $observers; $i++) {
            AssessmentRecord::create([
                'pet_id' => $pet->id, 'assessor_id' => $this->matchingUser(Role::Volunteer)->id,
                'responses' => ['species' => strtolower($pet->species->value), 'answers' => $this->behaviorResponses(strtolower($pet->species->value))],
                'scoring_version' => config('matching.algorithm_version'),
            ]);
        }

        return $pet->fresh()->load('assessmentRecords');
    }
}
