<?php

namespace Tests\Feature;

use App\Models\Pet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicPageTitleTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_titles_keep_pairfect_paws_brand_when_app_name_is_laravel(): void
    {
        config()->set('app.name', 'Laravel');

        $this->get(route('landing'))
            ->assertOk()
            ->assertSee('<title>PAIRfect Paws - Compassion Make Us Human</title>', false);

        $this->get(route('donate'))
            ->assertOk()
            ->assertSee('<title>Donate - PAIRfect Paws</title>', false);

        $this->get(route('pets.index'))
            ->assertOk()
            ->assertSee('<title>Available Pets - PAIRfect Paws</title>', false);

        $pet = Pet::create([
            'name' => 'Title Test Pet',
            'species' => 'Dog',
            'availability_status' => 'Available',
        ]);

        $this->get(route('pets.show', $pet))
            ->assertOk()
            ->assertSee('<title>Title Test Pet - PAIRfect Paws</title>', false);
    }
}
