<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Pet;
use App\Services\KnnRecommendationService;
use Database\Seeders\ResearchCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\BuildsMatchingFixtures;
use Tests\TestCase;

final class ResearchScenarioTest extends TestCase
{
    use BuildsMatchingFixtures, RefreshDatabase;

    public function test_catalog_matching_and_independent_simulated_lifecycles(): void
    {
        config(['research.enabled' => true]);
        $this->seed(ResearchCatalogSeeder::class);
        $this->assertDatabaseCount('research_catalog', 50);
        $this->assertDatabaseCount('assessment_records', 150);
        $this->assertSame(50, Pet::fullyAssessed()->count());
        $this->assertSame(25, Pet::where('species', 'Cat')->count());
        $this->assertCount(5, Pet::distinct()->pluck('physical_size'));
        $this->assertCount(3, Pet::distinct()->pluck('life_stage'));
        $this->assertSame(0, Pet::whereNotNull('photo_path')->count());
        $a = $this->matchingUser();
        $b = $this->matchingUser();
        $pet = Pet::firstOrFail();
        $apply = route('research.apply', $pet);
        $this->actingAs($a)->post($apply)->assertForbidden();
        foreach ([$a, $b] as $u) {
            $this->artisan('research:enroll', ['user_id' => $u->id])->assertSuccessful();
            $this->actingAs($u)->post($apply, ['eligible' => true, 'compatibility_score' => 100])->assertUnprocessable();
            $profile = $this->completeMatchingProfile($u);
            $this->actingAs($u->fresh());
            $this->assertCount(50, app(KnnRecommendationService::class)->recommendPets($profile));
            $this->get(route('application.apply', $pet))->assertRedirect(route('research.preview', $pet));
            $this->get(route('research.preview', $pet))->assertOk()->assertSee('Submit simulated');
            $this->post($apply)->assertRedirect(route('research.index'));
            $this->post($apply)->assertRedirect(route('research.index'));
            $this->post(route('application.submit'), ['pet_id' => $pet->id])->assertForbidden();
        }
        $this->assertDatabaseCount('research_placements', 2);
        $id = DB::table('research_placements')->where('user_id', $a->id)->value('id');
        $receive = route('research.receive', $id);
        $report = route('research.checkin', $id);
        $sample = ['appetite' => 'normal', 'adjustment' => 'settling'];
        $this->post($receive)->assertNotFound();
        $this->post($report, $sample + ['milestone' => '3-day'])->assertNotFound();
        $this->actingAs($a)->post($report, $sample + ['milestone' => '3-day'])->assertConflict();
        $this->post($receive)->assertRedirect();
        $this->post($receive)->assertRedirect();
        $this->post($report, $sample + ['milestone' => '3-month'])->assertConflict();
        $this->post($report, ['milestone' => '3-day'])->assertSessionHasErrors(['appetite', 'adjustment']);
        foreach (['3-day', '3-week', '3-month'] as $milestone) {
            $this->post($report, $sample + ['milestone' => $milestone])->assertRedirect();
            $this->post($report, $sample + ['milestone' => $milestone])->assertRedirect();
        }
        $this->get(route('research.index'))->assertOk()->assertSee('3-month simulated report filed');
        $this->actingAs($b)->get(route('research.index'))->assertDontSee('3-month simulated report filed');
        $this->assertDatabaseCount('research_checkins', 3);
        $this->assertDatabaseCount('adoption_applications', 0);
        $this->assertDatabaseCount('handovers', 0);
        $this->assertDatabaseCount('post_adoption_logs', 0);
        $this->assertSame('Available', $pet->fresh()->availability_status->value);
        $other = Pet::orderByDesc('id')->first();
        $other->update(['availability_status' => 'Assessing']);
        $this->post(route('research.apply', $other), ['eligible' => true])->assertUnprocessable();
        $this->assertDatabaseCount('research_placements', 2);
        config(['research.enabled' => false]);
        $this->post($receive)->assertNotFound();
        $this->get(route('application.apply', $pet))->assertOk()->assertViewIs('application.apply');
    }

    public function test_setup_and_enrollment_fail_closed(): void
    {
        $u = $this->matchingUser(Role::Volunteer);
        $this->artisan('research:enroll', ['user_id' => $u->id])->assertFailed();
        config(['research.enabled' => true]);
        $this->artisan('research:enroll', ['user_id' => $u->id])->assertFailed();
        $unverified = $this->matchingUser();
        $unverified->forceFill(['email_verified_at' => null])->save();
        $this->artisan('research:enroll', ['user_id' => $unverified->id])->assertFailed();
        $this->actingAs($unverified)->get(route('research.index'))->assertRedirect(route('verification.notice'));
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('fresh research database');
        $this->seed(ResearchCatalogSeeder::class);
    }
}
