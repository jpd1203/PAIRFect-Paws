<?php

namespace Tests\Feature;

use App\Models\AssessmentRecord;
use App\Models\Pet;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PilotAssessedPetsTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        $this->app->instance('env', 'testing');

        parent::tearDown();
    }

    public function test_pilot_import_preserves_six_existing_pets_and_is_idempotent(): void
    {
        $this->seed(DatabaseSeeder::class);
        $originalIds = Pet::orderBy('id')->pluck('id')->all();
        $this->assertCount(6, $originalIds);

        $this->app->instance('env', 'production');
        config()->set('app.debug', false);

        $this->artisan('pilot:seed-assessed-pets', ['--confirm-pilot' => true])->assertSuccessful();

        $this->assertSame(21, Pet::count());
        $this->assertSame($originalIds, Pet::whereIn('id', $originalIds)->orderBy('id')->pluck('id')->all());
        $pilotPets = Pet::where('behavioral_notes', 'like', '%[PAIRFECT-PILOT-ASSESSED-15-V1]%')->get();
        $this->assertCount(15, $pilotPets);
        $this->assertSame(45, AssessmentRecord::whereIn('pet_id', $pilotPets->modelKeys())->count());
        $this->assertTrue($pilotPets->every(fn (Pet $pet): bool => $pet->assessment_count === 3));
        $this->assertSame(15, Pet::recommendationEligible()->whereIn('id', $pilotPets->modelKeys())->count());
        $this->assertSame(3, User::where('email', 'like', 'pilot-assessor-%@example.invalid')->where('is_active', false)->count());

        $this->artisan('pilot:seed-assessed-pets', ['--confirm-pilot' => true])->assertSuccessful();
        $this->assertSame(21, Pet::count());
        $this->assertSame(45, AssessmentRecord::count());
    }

    public function test_pilot_import_requires_confirmation_and_exact_existing_count(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->artisan('pilot:seed-assessed-pets', ['--confirm-pilot' => true])->assertFailed();
        $this->app->instance('env', 'production');

        $this->artisan('pilot:seed-assessed-pets', ['--confirm-pilot' => true])->assertFailed();
        config()->set('app.debug', false);

        $this->artisan('pilot:seed-assessed-pets')->assertFailed();
        $this->artisan('pilot:seed-assessed-pets', [
            '--confirm-pilot' => true,
            '--expect-pets' => 5,
        ])->assertFailed();

        $this->assertSame(6, Pet::count());
        $this->assertDatabaseCount('assessment_records', 0);
        $this->assertSame(0, User::where('email', 'like', 'pilot-assessor-%@example.invalid')->count());
    }
}
