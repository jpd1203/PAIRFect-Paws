<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\AssessmentRecord;
use App\Models\Pet;
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

    public function test_three_records_from_one_staff_account_do_not_make_a_pet_fully_assessed(): void
    {
        $pet = $this->matchingPet(['name' => 'Repeated Observer'], 0);
        $staff = $this->matchingUser(Role::Volunteer);
        for ($i = 0; $i < 3; $i++) {
            AssessmentRecord::create([
                'pet_id' => $pet->id,
                'assessor_id' => $staff->id,
                'responses' => ['species' => 'dog', 'answers' => $this->behaviorResponses('dog')],
                'scoring_version' => config('matching.algorithm_version'),
            ]);
        }

        app(ApplicationMatchService::class)->refreshPetSummary($pet);
        $this->assertSame(1, $pet->fresh()->assessment_count);
        $this->assertFalse(Pet::fullyAssessed()->whereKey($pet->id)->exists());
        $this->get(route('pets.index'))->assertOk()->assertDontSee('Repeated Observer');
    }

    public function test_browse_pets_filters_by_young_adult_senior_and_does_not_contain_baby(): void
    {
        $matches = app(ApplicationMatchService::class);

        $youngKitten = $this->matchingPet(['name' => 'Kitten Young', 'age' => 2]);
        $youngDog = $this->matchingPet(['name' => 'Dog Young', 'age' => 24]);
        $adultCat = $this->matchingPet(['name' => 'Cat Adult', 'age' => 36]);
        $seniorDog = $this->matchingPet(['name' => 'Dog Senior', 'age' => 96]);

        foreach ([$youngKitten, $youngDog, $adultCat, $seniorDog] as $pet) {
            $matches->refreshPetSummary($pet);
        }

        // Verify Model age_group logic
        $this->assertSame('Young', $youngKitten->age_group);
        $this->assertSame('Young', $youngDog->age_group);
        $this->assertSame('Adult', $adultCat->age_group);
        $this->assertSame('Senior', $seniorDog->age_group);

        $user = $this->matchingUser();

        // 1. Verify UI does not display 'Baby' option
        $response = $this->actingAs($user)->get(route('animal.index'));
        $response->assertOk();
        $response->assertDontSee('<option value="Baby"', false);
        $response->assertSee('<option value="Young"', false);
        $response->assertSee('<option value="Adult"', false);
        $response->assertSee('<option value="Senior"', false);

        // 2. Filter by 'Young' returns both <= 6 mo and 7-24 mo pets
        $responseYoung = $this->actingAs($user)->get(route('animal.index', ['age' => 'Young']));
        $responseYoung->assertOk()
            ->assertSee('Kitten Young')
            ->assertSee('Dog Young')
            ->assertDontSee('Cat Adult')
            ->assertDontSee('Dog Senior');

        // 3. Filter by 'Adult' returns 25-84 mo pets
        $responseAdult = $this->actingAs($user)->get(route('animal.index', ['age' => 'Adult']));
        $responseAdult->assertOk()
            ->assertDontSee('Kitten Young')
            ->assertDontSee('Dog Young')
            ->assertSee('Cat Adult')
            ->assertDontSee('Dog Senior');

        // 4. Filter by 'Senior' returns >= 85 mo pets
        $responseSenior = $this->actingAs($user)->get(route('animal.index', ['age' => 'Senior']));
        $responseSenior->assertOk()
            ->assertDontSee('Kitten Young')
            ->assertDontSee('Dog Young')
            ->assertDontSee('Cat Adult')
            ->assertSee('Dog Senior');

        // 5. Public /pets catalog behaves identically
        $publicYoung = $this->get(route('pets.index', ['age' => 'Young']));
        $publicYoung->assertOk()
            ->assertSee('Kitten Young')
            ->assertSee('Dog Young')
            ->assertDontSee('Cat Adult')
            ->assertDontSee('Dog Senior')
            ->assertDontSee('<option value="Baby"', false);
    }
}
