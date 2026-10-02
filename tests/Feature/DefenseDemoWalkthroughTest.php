<?php

namespace Tests\Feature;

use App\Enums\ApplicationStatus;
use App\Enums\AvailabilityStatus;
use App\Enums\Role;
use App\Models\AdoptionApplication;
use App\Models\Pet;
use App\Models\User;
use App\Services\AdopterHistoryService;
use App\Services\Matching\ApplicantRankingService;
use App\Services\Matching\BehaviorAssessmentService;
use App\Services\Matching\MatchingProfileMapper;
use App\Services\KnnRecommendationService;
use App\Services\ReservationQueueService;
use Carbon\Carbon;
use Database\Seeders\DefenseDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

final class DefenseDemoWalkthroughTest extends TestCase
{
    use RefreshDatabase;

    public function test_fictional_demo_snapshot_and_staff_export_are_consistent(): void
    {
        $this->seed(DefenseDemoSeeder::class);

        $admin = User::where('email', 'evelyn.cruz@defense.pairfectpaws.test')->firstOrFail();
        $volunteer = User::where('email', 'rafael.dizon@defense.pairfectpaws.test')->firstOrFail();
        $thirdObserver = User::where('email', 'nina.bautista@defense.pairfectpaws.test')->firstOrFail();
        $carlo = User::where('email', 'carlo.villanueva@defense.pairfectpaws.test')->firstOrFail();
        $bea = User::where('email', 'bea.navarro@defense.pairfectpaws.test')->firstOrFail();
        $this->assertSame(Role::Administrator, $admin->role);
        $this->assertSame(Role::Volunteer, $volunteer->role);
        $this->assertTrue($admin->hasVerifiedEmail());
        $this->post(route('login.store'), [
            'email' => $admin->email, 'password' => DefenseDemoSeeder::PASSWORD,
        ])->assertRedirect(route('admin.dashboard'));
        $this->assertAuthenticatedAs($admin);

        $bruno = Pet::where('name', 'Bruno')->firstOrFail();
        $mochi = Pet::where('name', 'Mochi')->firstOrFail();
        $tala = Pet::where('name', 'Tala')->firstOrFail();
        $pepper = Pet::where('name', 'Pepper')->firstOrFail();
        $this->assertSame(3, $bruno->assessment_count);
        $this->assertSame('complete', $bruno->assessment_status);
        $this->assertSame(2, $mochi->assessment_count);
        $this->assertSame('pending', $mochi->assessment_status);
        $this->assertTrue(app(MatchingProfileMapper::class)->adopterIsComplete($carlo->adopterProfile));

        $applications = AdoptionApplication::where('pet_id', $bruno->id)->get();
        $this->assertCount(3, $applications);
        $this->assertTrue($applications->every(fn ($application) => $application->knn_score !== null && $application->knn_computed_at !== null));
        $this->assertCount(3, $applications->pluck('knn_score')->unique());
        $ranking = app(ApplicantRankingService::class)->rankApplicants($bruno)->where('pet_id', $bruno->id)->values();
        $this->assertTrue($ranking->every(fn ($application) => app(ApplicantRankingService::class)->isEligible($application)));
        $this->assertSame($ranking->pluck('knn_score')->sortDesc()->values()->all(), $ranking->pluck('knn_score')->all());
        $this->assertTrue(AdoptionApplication::with(['user', 'pet'])->whereIn('pet_id', Pet::where('branch_id', $bruno->branch_id)->pluck('id'))->get()
            ->every(fn ($application) => $application->user->created_at->lte($application->created_at)
                && $application->pet->created_at->lte($application->created_at)));

        $unsafe = AdoptionApplication::where('user_id', $bea->id)->where('pet_id', $tala->id)->firstOrFail();
        $this->assertFalse($unsafe->compatibility_result['eligible']);
        $this->assertFalse(app(KnnRecommendationService::class)->recommendPets($bea->adopterProfile, 20)->pluck('pet.id')->contains($tala->id));
        $this->assertSame(AvailabilityStatus::SoftReserved, $pepper->availability_status);
        $this->assertFalse(app(KnnRecommendationService::class)->recommendPets($carlo->adopterProfile, 20)->pluck('pet.id')->contains($pepper->id));

        $luna = Pet::where('name', 'Luna')->firstOrFail();
        $approved = AdoptionApplication::where('pet_id', $luna->id)->firstOrFail();
        $this->assertSame(ApplicationStatus::Approved, $approved->status);
        $this->assertSame(AvailabilityStatus::Adopted, $luna->availability_status);
        $this->assertGreaterThanOrEqual(80, $approved->knn_score);
        $this->assertTrue($luna->intake_date->lte($approved->adopted_at));
        $this->assertNotNull($approved->adopted_at);
        $this->assertCount(3, $approved->postAdoptionLogs);
        $this->assertSame(3, $approved->postAdoptionLogs->whereNotNull('submitted_date')->count());
        $this->assertSame('received', $approved->handover->adopter_outcome);
        $this->assertSame('no_recorded_concerns', app(AdopterHistoryService::class)->getSummary($approved->user)['review_status']);
        $this->assertNotSame('no_recorded_concerns', app(AdopterHistoryService::class)->getSummary($carlo)['review_status']);
        foreach (['Pending', 'InterviewScheduled', 'UnderReview', 'Approved', 'Rejected'] as $status) {
            $this->assertTrue(AdoptionApplication::where('status', $status)->exists(), "No {$status} demo application exists.");
        }

        $this->actingAs($admin)->get(route('admin.dashboard'))->assertOk()->assertSee('Nala');
        $this->actingAs($admin)->get(route('admin.applications.index'))->assertOk()->assertSee('Export Applications CSV');
        $this->actingAs($thirdObserver)->get(route('admin.assessments.create', $mochi))->assertOk();
        $this->actingAs($carlo)->get(route('recommendation.results'))->assertSuccessful();

        // Exercise a harmless real maintenance write, then finish the third distinct observation.
        $this->actingAs($admin)->put(route('admin.animals.update', $bruno), [
            'name' => $bruno->name, 'species' => 'Dog', 'status' => 'Available',
            'description' => 'Bruno enjoys supervised play and structured walks.',
            'version' => $bruno->version,
        ])->assertRedirect(route('admin.animals.index'));
        $this->assertSame('Bruno enjoys supervised play and structured walks.', $bruno->fresh()->description);
        $answers = [];
        foreach (config('matching.items.cat') as $group => $items) {
            foreach ($items as $key => $prompt) {
                $answers[$group][$key] = 2;
            }
        }
        app(BehaviorAssessmentService::class)->record($mochi, $thirdObserver, ['responses' => $answers]);
        $this->assertSame('complete', $mochi->fresh()->assessment_status);

        $export = $this->actingAs($admin)->get(route('admin.applications.export'))->assertOk()->assertDownload();
        $this->assertStringContainsString('Application ID', $export->streamedContent());
        $this->assertStringContainsString('Bruno', $export->streamedContent());
        $approvedExport = $this->actingAs($admin)->get(route('admin.applications.export', ['status' => 'Approved']))->assertOk();
        $this->assertStringContainsString('Luna', $approvedExport->streamedContent());
        $this->assertStringNotContainsString('Bruno', $approvedExport->streamedContent());
        $lunaSubmitted = $approved->created_at->setTimezone('Asia/Manila')->toDateString();
        $datedExport = $this->actingAs($admin)->get(route('admin.applications.export', [
            'from' => $lunaSubmitted, 'to' => $lunaSubmitted,
        ]))->assertOk();
        $this->assertStringContainsString('Luna', $datedExport->streamedContent());
        $this->assertStringNotContainsString('Bruno', $datedExport->streamedContent());
        $this->actingAs($carlo)->get(route('admin.applications.export'))->assertRedirect();
        $this->actingAs($volunteer)->get(route('admin.applications.export'))->assertOk();

        Mail::fake();
        app(ReservationQueueService::class)->schedule(
            $ranking->first(), Carbon::now('Asia/Manila')->addDays(2)->setTime(10, 0), $volunteer, $admin->id
        );
        $this->assertSame(AvailabilityStatus::SoftReserved, $bruno->fresh()->availability_status);
        $this->assertSame(ApplicationStatus::InterviewScheduled, $ranking->first()->fresh()->status);
        $this->assertSame(2, AdoptionApplication::where('pet_id', $bruno->id)->where('status', ApplicationStatus::Waitlisted->value)->count());
    }
}
