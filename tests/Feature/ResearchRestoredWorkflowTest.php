<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Pet;
use App\Models\ResearchCaptureChallenge;
use App\Models\ResearchPlacement;
use App\Services\ReservationQueueService;
use Database\Seeders\ResearchCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\BuildsMatchingFixtures;
use Tests\Concerns\SubmitsResearchCheckIns;
use Tests\TestCase;

final class ResearchRestoredWorkflowTest extends TestCase
{
    use BuildsMatchingFixtures, RefreshDatabase, SubmitsResearchCheckIns;

    public function test_delivery_schedule_reschedule_and_negative_receipt_allow_one_successful_attempt(): void
    {
        $placement = $this->approvedPlacement();
        $disk = Storage::fake('research-private');
        Mail::fake();
        Queue::fake();
        $staff = $this->matchingUser(Role::Administrator);
        $stranger = $this->matchingUser();
        $owner = $placement->user;
        $handover = route('research.handover', $placement);
        $method = route('research.handover.method', $placement);
        $confirmation = route('research.handover.confirm', $placement);
        $receive = route('research.receive', $placement);
        $this->assertNull($placement->released_at);
        // Previously approved rows may have an automatic ready date without an agreement.
        $placement->update(['released_at' => now()->subDay()]);
        $this->actingAs($stranger)->get($handover)->assertNotFound();
        $this->post($method, ['method' => 'delivery', 'schedule_version' => 0])->assertNotFound();
        $this->actingAs($owner)->post($receive, ['outcome' => 'received', 'schedule_version' => 0])->assertConflict();
        $this->get($handover)->assertOk()->assertSee('Third-party delivery')->assertSee('Pickup at shelter');
        $this->post($method, ['method' => 'delivery', 'schedule_version' => 0])->assertRedirect($handover);
        $this->get($handover)->assertOk()->assertSee('Confirm Proposed Schedule')->assertSee('Third-Party Delivery');
        $this->post($confirmation, ['schedule_version' => 0])->assertConflict();
        $this->post($confirmation, ['schedule_version' => 1])->assertRedirect($handover);
        $options = [['date' => now('Asia/Manila')->addDay()->toDateString(), 'start_time' => '10:00', 'end_time' => '11:00']];
        $this->post(route('research.handover.reschedule', $placement), ['schedule_version' => 2, 'options' => $options, 'reason' => 'Alternative time'])->assertRedirect($handover);
        $this->post($receive, ['outcome' => 'received', 'schedule_version' => 2])->assertConflict();
        $this->post($confirmation, ['schedule_version' => 3])->assertRedirect($handover);
        $this->get(route('research.receipt', $placement))->assertOk()->assertSee('receiptProofPreview', false)
            ->assertSee("I haven't received my pet", false);
        $this->post($receive, ['outcome' => 'not_received', 'schedule_version' => 4, 'note' => 'First response'])->assertRedirect();
        $this->assertNull($placement->fresh()->received_at);
        $this->postJson(route('research.capture-challenge', [$placement, '3-day']))->assertConflict();
        $this->get($handover)->assertOk()->assertSee('Start a New Handover Attempt');
        $this->actingAs($staff)->get(route('admin.handover.show', $placement->id))->assertOk()
            ->assertSee('Reported not received')->assertSee('Alternative time')->assertSee('Third-Party Delivery');
        $this->actingAs($owner)->post(route('research.handover.retry', $placement), ['schedule_version' => 4])->assertRedirect($handover);
        $this->post($confirmation, ['schedule_version' => 5])->assertRedirect($handover);
        $this->post($receive, [
            'outcome' => 'received', 'schedule_version' => 6, 'receipt_proof' => UploadedFile::fake()->image('practice.png'),
        ])->assertRedirect();
        $this->post($receive, ['outcome' => 'received', 'schedule_version' => 6])->assertConflict();
        $row = $placement->fresh();
        $this->assertNotNull($row->received_at);
        $this->assertSame('First response', $row->handover_receipt['previous_not_received']['note']);
        $disk->assertExists($row->handover_receipt['path']);
        $this->get(route('research.receipt.photo', $row))->assertOk()->assertHeader('Content-Type', 'image/png');
        $this->get(route('research.report', $row))->assertOk()->assertSee('Start Camera');
        $this->actingAs($stranger)->get(route('research.receipt.photo', $row))->assertNotFound();
        $this->actingAs($staff)->get(route('admin.research.receipt-photo', $row))->assertOk();
        $this->assertDatabaseCount('handovers', 0);
        $this->assertDatabaseCount('handover_transport_incidents', 0);
        Queue::assertNothingPushed();
        Mail::assertNothingOutgoing();
    }

    public function test_video_is_required_private_and_cannot_be_replayed_between_milestones(): void
    {
        $placement = $this->approvedPlacement();
        $disk = Storage::fake('research-private');
        $owner = $placement->user;
        $this->actingAs($owner);
        $this->post(route('research.handover.method', $placement), ['method' => 'pickup', 'schedule_version' => 0])->assertRedirect();
        $this->post(route('research.handover.confirm', $placement), ['schedule_version' => 1])->assertRedirect();
        $this->post(route('research.receive', $placement), ['outcome' => 'received', 'schedule_version' => 2])->assertRedirect();
        $url = route('research.checkin', $placement);
        $answers = ['milestone' => '3-day', 'pet_current_status' => 'Good', 'living_conditions' => 'Indoor Only',
            'eating_habits' => 'Normal', 'behavioral_observations' => 'Still adjusting'];
        $this->post($url, $answers)->assertSessionHasErrors('video');
        $this->probeResearchVideo(5);
        $invalid = $this->researchCapture($placement, '3-day', 'long');
        $this->post($url, $answers + $invalid)->assertSessionHasErrors('video');
        $this->assertNull(ResearchCaptureChallenge::findOrFail($invalid['capture_challenge_id'])->consumed_at);
        $this->probeResearchVideo();
        $expired = $this->researchCapture($placement, '3-day', 'expired');
        ResearchCaptureChallenge::findOrFail($expired['capture_challenge_id'])->update(['expires_at' => now()->subSecond()]);
        $this->post($url, $answers + $expired)->assertSessionHasErrors('capture_challenge');
        $foreignSession = $this->researchCapture($placement, '3-day', 'session-bound');
        $this->withSession(['post_adoption_capture_session_binding' => str_repeat('b', 64)])
            ->post($url, $answers + $foreignSession)->assertSessionHasErrors('capture_challenge');
        $first = $this->researchCapture($placement, '3-day', 'same-bytes');
        $this->postJson($url, $answers + $first)->assertOk();
        $this->assertNotNull(ResearchCaptureChallenge::findOrFail($first['capture_challenge_id'])->consumed_at);
        $second = $this->researchCapture($placement, '3-week', 'same-bytes');
        $this->post($url, array_replace($answers, ['milestone' => '3-week']) + $second)->assertSessionHasErrors('video');
        $this->assertNull(ResearchCaptureChallenge::findOrFail($second['capture_challenge_id'])->consumed_at);
        $this->assertDatabaseCount('research_checkins', 1);
        $report = DB::table('research_checkins')->first();
        $disk->assertExists($report->video_path);
        $video = route('admin.research.video', $report->id);
        $this->get($video)->assertRedirect(route('access-denied'));
        $this->actingAs($this->matchingUser(Role::Administrator))->get($video)->assertOk()
            ->assertHeader('Content-Type', 'video/webm')->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->get(route('admin.monitoring.index'))->assertOk()->assertSee($video)->assertSee('not welfare evidence');
        $this->get('/storage/'.$report->video_path)->assertForbidden();
        $this->assertDatabaseCount('post_adoption_logs', 0);
    }

    private function approvedPlacement(): ResearchPlacement
    {
        config(['research.enabled' => true]);
        $this->seed(ResearchCatalogSeeder::class);
        $adopter = $this->matchingUser();
        $this->completeMatchingProfile($adopter);
        $queue = app(ReservationQueueService::class);
        $queue->submitResearch($adopter, Pet::firstOrFail());
        $placement = ResearchPlacement::sole();
        $queue->approveResearch($adopter, $placement);

        return $placement->fresh();
    }
}
