<?php

namespace Tests\Feature;

use App\Enums\AvailabilityStatus;
use App\Models\AssessmentRecord;
use App\Models\Pet;
use App\Services\KnnRecommendationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RecommendationEligibilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_fully_assessed_pets_are_recommended(): void
    {
        $phantom = $this->pet('Phantom', [
            'assessment_count' => 3,
            'energy_level' => 3,
            'trainability' => 3,
            'independence' => 3,
            'temperament' => 3,
            'medical_needs' => 3,
            'physical_size' => 'Medium',
        ]);

        $incomplete = $this->pet('Incomplete', [
            'energy_level' => 3,
            'trainability' => 3,
            'independence' => 3,
            'temperament' => 3,
            'medical_needs' => 3,
            'physical_size' => null,
        ]);
        $this->addThreeAssessments($incomplete);

        $eligible = $this->pet('Eligible', [
            'energy_level' => 3,
            'trainability' => 3,
            'independence' => 3,
            'temperament' => 3,
            'medical_needs' => 2,
            'physical_size' => 'Small',
        ]);
        $this->addThreeAssessments($eligible);

        $matches = app(KnnRecommendationService::class)->recompute([
            'energy' => 3,
            'trainability' => 3,
            'medical' => 2,
            'independence' => 3,
            'temperament' => 3,
        ]);

        $recommendedIds = $matches->pluck('pet.id')->all();
        $this->assertSame([$eligible->id], $recommendedIds);
        $this->assertNotContains($phantom->id, $recommendedIds);
        $this->assertNotContains($incomplete->id, $recommendedIds);
    }

    private function pet(string $name, array $attributes = []): Pet
    {
        return Pet::create(array_merge([
            'name' => $name,
            'species' => 'Dog',
            'availability_status' => AvailabilityStatus::Available->value,
        ], $attributes));
    }

    private function addThreeAssessments(Pet $pet): void
    {
        foreach (range(1, 3) as $number) {
            AssessmentRecord::create([
                'pet_id' => $pet->id,
                'energy_level' => 3,
                'trainability' => 3,
                'independence' => 3,
                'temperament' => 3,
            ]);
        }
    }
}
