<?php

namespace Tests\Feature;

use App\Models\AdopterProfile;
use App\Models\Pet;
use App\Services\KnnRecommendationService;
use Database\Seeders\MatchingDemoSeeder;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class MatchingDemoSeederTest extends TestCase
{
    public function test_fresh_scratch_database_and_repeatable_demo_seed_produce_ranked_recommendations(): void
    {
        Mail::fake();
        // Never allow this destructive QA command to target the developer's real database.
        $this->assertSame('sqlite', config('database.default'));
        $this->assertSame(':memory:', config('database.connections.sqlite.database'));
        $this->artisan('migrate:fresh', ['--seed' => true, '--force' => true])->assertSuccessful();
        $this->seed(MatchingDemoSeeder::class);
        $this->seed(MatchingDemoSeeder::class);
        $this->assertSame(12, Pet::where('name', 'like', 'Matching Demo %')->count());
        $this->assertDatabaseCount('adopter_profiles', 4);
        $this->assertDatabaseCount('assessment_records', 36);
        foreach (AdopterProfile::all() as $profile) {
            $results = app(KnnRecommendationService::class)->recommendPets($profile);
            $this->assertCount(5, $results);
            $scores = $results->map(fn ($row) => $row['match']->compatibilityScore)->all();
            $sorted = $scores;
            rsort($sorted);
            $this->assertSame($sorted, $scores);
            if ($profile->has_children) {
                $this->assertFalse($results->contains(fn ($row) => $row['pet']->has_aggression_history));
            }
            if ($profile->financial_readiness <= 2) {
                $this->assertTrue($results->every(fn ($row) => $row['pet']->life_stage === 'adult' && $row['pet']->medical_needs <= 2));
            }
        }
    }
}
