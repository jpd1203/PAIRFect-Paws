<?php

namespace Tests\Feature;

use App\Services\Matching\ApplicationMatchService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsMatchingFixtures;
use Tests\TestCase;

class PetCatalogAssessmentVisibilityTest extends TestCase
{
    use BuildsMatchingFixtures, RefreshDatabase;

    public function test_browse_and_public_listings_show_only_fully_assessed_pets(): void
    {
        $complete = $this->matchingPet(['name' => 'Fully Assessed']);
        $partial = $this->matchingPet(['name' => 'Partially Assessed'], 2);
        $phantom = $this->matchingPet(['name' => 'No Assessment Records'], 0);
        $reserved = $this->matchingPet(['name' => 'Assessed Reserved', 'availability_status' => 'Soft-Reserved']);

        $matches = app(ApplicationMatchService::class);
        foreach ([$complete, $partial, $reserved] as $pet) {
            $matches->refreshPetSummary($pet);
        }
        $phantom->forceFill([
            'assessment_scoring_version' => config('matching.algorithm_version'),
            'assessment_count' => 3,
            'energy_level' => 3,
            'trainability' => 3,
            'independence' => 3,
            'temperament' => 3,
        ])->save();

        foreach ([route('landing'), route('pets.index')] as $url) {
            $this->get($url)->assertOk()
                ->assertSee('Fully Assessed')
                ->assertDontSee('Partially Assessed')
                ->assertDontSee('No Assessment Records')
                ->assertDontSee('Assessed Reserved');
        }

        $this->actingAs($this->matchingUser())->get(route('animal.index'))->assertOk()
            ->assertSee('Fully Assessed')
            ->assertSee('Assessed Reserved')
            ->assertDontSee('Partially Assessed')
            ->assertDontSee('No Assessment Records');
    }
}
