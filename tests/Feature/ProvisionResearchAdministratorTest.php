<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Pet;
use App\Models\User;
use Database\Seeders\ResearchCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

final class ProvisionResearchAdministratorTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_refuses_non_production_or_disabled_research(): void
    {
        config(['research.enabled' => true]);
        $this->artisan('research:provision-admin')->expectsOutputToContain('production environment')->assertFailed();

        $this->productionMode();
        config(['research.enabled' => false]);
        $this->artisan('research:provision-admin')->expectsOutputToContain('enabled Research Mode')->assertFailed();
        $this->assertDatabaseCount('users', 0);
    }

    public function test_command_refuses_other_databases(): void
    {
        if (DB::getDatabaseName() === 'pairfect_research_survey01') {
            $this->markTestSkipped('Wrong-database refusal is verified on the default CI database.');
        }
        $this->productionMode();
        $this->artisan('research:provision-admin')->expectsOutputToContain('active MySQL database must be')->assertFailed();
        $this->assertDatabaseCount('users', 0);
    }

    public function test_bootstrap_login_catalog_upload_and_access_boundaries(): void
    {
        if (DB::getDriverName() !== 'mysql' || DB::connection()->getConfig('host') !== '127.0.0.1'
            || DB::getDatabaseName() !== 'pairfect_research_survey01'
            || env('PAIRFECT_DISPOSABLE_MYSQL_DB') !== DB::getDatabaseName()) {
            $this->markTestSkipped('Requires explicitly disposable loopback MySQL with the exact research schema name.');
        }
        $this->productionMode();
        $this->artisan('research:provision-admin')->expectsOutputToContain('initialize the shared research catalog')->assertFailed();
        $this->artisan('db:seed', ['--class' => ResearchCatalogSeeder::class, '--force' => true])->assertSuccessful();
        $this->artisan('research:provision-admin', ['--no-interaction' => true])
            ->expectsOutputToContain('interactive SSH terminal')->assertFailed();

        $adopter = User::factory()->create(['role' => Role::Adopter, 'email_verified_at' => null]);
        $this->form($adopter->email)->expectsOutputToContain('already in use')->assertFailed();
        $email = 'bootstrap@example.test';
        $attestation = 'Have you independently confirmed that the named administrator controls this email address? This will mark it verified.';
        $this->form($email)->expectsConfirmation($attestation, 'no')->assertFailed();

        $secret = fake()->regexify('[A-Za-z0-9]{32}');
        $this->form($email)->expectsConfirmation($attestation, 'yes')
            ->expectsQuestion('Password (at least 16 characters, no more than 72 bytes)', $secret)
            ->expectsQuestion('Confirm password', 'different-confirmation')->assertFailed();
        $this->assertDatabaseMissing('users', ['email' => $email]);

        $this->form(strtoupper($email))->expectsConfirmation($attestation, 'yes')
            ->expectsQuestion('Password (at least 16 characters, no more than 72 bytes)', $secret)
            ->expectsQuestion('Confirm password', $secret)
            ->doesntExpectOutput($secret)->expectsOutputToContain('User ID:')->assertSuccessful();
        $admin = User::where('email', $email)->sole();
        $this->assertSame(Role::Administrator, $admin->role);
        $this->assertTrue($admin->is_active);
        $this->assertTrue($admin->hasVerifiedEmail());
        $this->assertTrue(Hash::check($secret, $admin->password));
        $this->assertFalse($admin->adopterProfile()->exists());
        $this->assertDatabaseHas('audit_logs', ['action' => 'Research Administrator Provisioned', 'entity_id' => $admin->id]);
        $this->artisan('research:provision-admin')->expectsOutputToContain('already exists')->assertFailed();

        $this->app->detectEnvironment(fn () => 'testing');
        $this->get(route('landing'))->assertOk()->assertSee('Main navigation')
            ->assertDontSee('Research testing: this catalog is fictional.');
        $this->post(route('login.store'), ['email' => $email, 'password' => $secret])->assertRedirect(route('admin.dashboard'));
        $this->assertAuthenticatedAs($admin);
        $this->get(route('admin.dashboard'))->assertOk();
        $this->get(route('admin.animals.index'))->assertOk()->assertViewHas('pets', fn ($pets) => $pets->count() === 70);
        $pet = Pet::firstOrFail();
        Storage::fake('public');
        $this->put(route('admin.animals.update', $pet), [
            'name' => $pet->name, 'species' => $pet->species->value,
            'status' => $pet->availability_status->value, 'version' => $pet->version,
            'photo' => UploadedFile::fake()->image('fictional-pet.png'),
        ])->assertRedirect(route('admin.animals.index'));
        Storage::disk('public')->assertExists($pet->fresh()->photo_path);

        $this->actingAs($adopter)->get(route('admin.dashboard'))->assertRedirect(route('access-denied'));
        $this->get(route('admin.animals.index'))->assertRedirect(route('access-denied'));
        $this->get(route('research.index'))->assertOk()->assertSee('fictional pets and simulated outcomes only.');
        $admin->update(['email_verified_at' => null]);
        $this->actingAs($admin)->get(route('admin.dashboard'))->assertRedirect(route('verification.notice'));
    }

    private function productionMode(): void
    {
        $this->app->detectEnvironment(fn () => 'production');
        config(['app.env' => 'production', 'research.enabled' => true]);
    }

    private function form(string $email)
    {
        return $this->artisan('research:provision-admin')->expectsQuestion('First name', 'Synthetic')
            ->expectsQuestion('Last name', 'Administrator')->expectsQuestion('Email address', $email);
    }
}
