<?php

namespace Tests\Feature;

use App\Models\Pet;
use App\Services\Matching\ApplicationMatchService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsMatchingFixtures;
use Tests\TestCase;

class LandingPageFeaturedPetsTest extends TestCase
{
    use BuildsMatchingFixtures, RefreshDatabase;

    public function test_see_more_card_is_hidden_when_there_are_no_available_pets(): void
    {
        // No pets in database
        $response = $this->get(route('landing'));

        $response->assertOk();
        $response->assertSee('No pets currently listed');
        $response->assertDontSee('See more available pets');
        $response->assertDontSee('id="prevPetsBtn"', false);
        $response->assertDontSee('id="nextPetsBtn"', false);
    }

    public function test_see_more_card_is_displayed_when_there_are_available_pets(): void
    {
        $pet = $this->matchingPet(['name' => 'Featured Pup']);
        app(ApplicationMatchService::class)->refreshPetSummary($pet);

        $response = $this->get(route('landing'));

        $response->assertOk();
        $response->assertSee('Featured Pup');
        $response->assertSee('See more available pets');
        $response->assertSee('1 pets are waiting for a home');
        $response->assertSee('prevPetsBtn');
        $response->assertSee('nextPetsBtn');
        $response->assertDontSee('No pets currently listed');
    }
}
