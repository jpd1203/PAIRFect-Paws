<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\AssessmentSeeder;
use Database\Seeders\DemoPetsSeeder;
use Database\Seeders\FreshStartPetsSeeder;
use Database\Seeders\HandoverSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class ProductionReleaseSafeguardsTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        $this->app->instance('env', 'testing');

        parent::tearDown();
    }

    public function test_privacy_contact_is_configurable_without_a_public_placeholder(): void
    {
        config()->set('release.privacy_contact_email', 'privacy@example.test');

        $this->get(route('legal.privacy'))
            ->assertOk()
            ->assertSee('mailto:privacy@example.test', false)
            ->assertDontSee('[INSERT OFFICIAL PRIVACY CONTACT / EMAIL]');
    }

    public function test_production_with_missing_privacy_contact_fails_closed(): void
    {
        $this->app->instance('env', 'production');
        config()->set('release.privacy_contact_email', null);

        $this->get(route('legal.privacy'))->assertStatus(503);
    }

    public function test_time_travel_is_hidden_in_production_even_if_its_flag_is_enabled(): void
    {
        $this->app->instance('env', 'production');
        config()->set('post_adoption.time_travel.enabled', true);
        $admin = User::create([
            'first_name' => 'Production', 'last_name' => 'Admin',
            'email' => 'production-admin@example.test', 'password' => 'Password123!',
            'role' => Role::Administrator->value, 'email_verified_at' => now(),
        ]);

        $this->actingAs($admin)->get('/timetravel')->assertNotFound();
        $this->get(route('admin.dashboard'))->assertOk()->assertDontSee('Test Time Travel');
    }

    public function test_production_default_seeding_creates_no_sample_pets_or_accounts(): void
    {
        $this->app->instance('env', 'production');

        (new DatabaseSeeder())->run();

        $this->assertDatabaseCount('pets', 0);
        $this->assertDatabaseCount('users', 0);
    }

    public function test_explicit_synthetic_pet_seeding_is_rejected_in_production(): void
    {
        $this->app->instance('env', 'production');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Synthetic pet records cannot be seeded in production.');
        (new FreshStartPetsSeeder())->run();
    }

    public function test_legacy_demo_pet_seeding_is_rejected_before_writing_in_production(): void
    {
        $this->app->instance('env', 'production');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Demo pets may only be seeded in local or testing environments.');
        (new DemoPetsSeeder())->run();
    }

    public function test_synthetic_assessments_are_rejected_before_deleting_records_in_production(): void
    {
        $this->app->instance('env', 'production');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Synthetic assessments may only be seeded in local or testing environments.');
        (new AssessmentSeeder())->run();
    }

    public function test_fictional_handovers_are_rejected_before_writing_in_production(): void
    {
        $this->app->instance('env', 'production');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Fictional handovers may only be seeded in local or testing environments.');
        (new HandoverSeeder())->run();
    }

    public function test_release_check_reports_missing_production_configuration(): void
    {
        config()->set('app.debug', true);
        config()->set('app.url', 'https://temporary.ngrok-free.dev');
        config()->set('release.privacy_contact_email', null);
        config()->set('post_adoption.time_travel.enabled', true);
        config()->set('post_adoption.capture.ffprobe_path', '');

        $this->artisan('release:check')
            ->assertFailed();
    }
}
