<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Pet;
use App\Models\User;
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
            ->assertSee('<title>PAIRfect Paws</title>', false);

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

        $this->get(route('access-denied'))
            ->assertOk()
            ->assertSee('<title>Access Denied | PAIRfect Paws</title>', false);
    }

    public function test_admin_page_titles_add_the_brand_only_when_it_is_missing(): void
    {
        $admin = User::create([
            'first_name' => 'Title',
            'last_name' => 'Admin',
            'email' => 'title-admin@example.test',
            'password' => 'password123',
            'role' => Role::Administrator->value,
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        $this->actingAs($admin)
            ->get(route('admin.volunteers.create'))
            ->assertOk()
            ->assertSee('<title>Create Staff Account | PAIRfect Paws</title>', false);

        $this->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('<title>Dashboard - PAIRfect Paws Admin</title>', false);
    }
}
