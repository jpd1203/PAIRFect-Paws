<?php

namespace Tests\Feature;

use App\Enums\ApplicationStatus;
use App\Enums\AvailabilityStatus;
use App\Enums\Milestone;
use App\Enums\ResolutionOutcome;
use App\Enums\Role;
use App\Models\AdoptionApplication;
use App\Models\PostAdoptionLog;
use App\Models\ResearchCheckIn;
use App\Models\ResearchPlacement;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\BuildsMatchingFixtures;
use Tests\Concerns\SubmitsResearchCheckIns;
use Tests\TestCase;

final class ResearchWelfareCasesTest extends TestCase
{
    use BuildsMatchingFixtures, RefreshDatabase, SubmitsResearchCheckIns;

    protected function setUp(): void
    {
        parent::setUp();
        config(['research.enabled' => true]);
        Mail::fake();
        Queue::fake();
    }

    public function test_valid_camera_submissions_use_normal_welfare_criteria_and_do_not_repeat_flags(): void
    {
        $placement = $this->placement();
        $this->actingAs($placement->user);
        $this->probeResearchVideo();
        $disk = Storage::fake('research-private');
        $endpoint = route('research.checkin', $placement);
        $normal = $this->answers() + ['milestone' => '3-day'];
        $this->post($endpoint, $normal + $this->researchCapture($placement, '3-day', 'healthy-clip'))->assertRedirect();
        $healthy = $placement->researchCheckIns()->sole();
        $this->assertFalse($healthy->is_flagged);
        $this->assertNotNull($healthy->welfare_evaluated_at);

        $answers = $this->answers([
            'pet_current_status' => 'Poor', 'behavioral_observations' => 'Aggressive',
            'eating_habits' => 'Reduced Appetite', 'concerns' => 'An untreated wound and no access to clean water.',
        ]) + ['milestone' => '3-week'];
        $capture = $this->researchCapture($placement, '3-week', 'concerning-clip');
        $this->post($endpoint, $answers + $capture)->assertRedirect();
        $this->post($endpoint, $answers + $capture)->assertConflict();
        $report = $placement->researchCheckIns()->where('milestone', '3-week')->sole();
        $disk->assertExists($report->video_path);
        $this->assertTrue($report->is_flagged);
        $reasonText = implode(' ', array_column($report->welfare_case['flag_reasons'], 'message'));
        foreach (['status as poor', 'behavioral observations', 'eating habits', 'untreated wound', 'no access to clean water'] as $fragment) {
            $this->assertStringContainsString($fragment, $reasonText);
        }
        $this->assertCount(1, $report->welfare_case['history']);
        $this->assertDatabaseHas('audit_logs', ['entity_name' => 'ResearchCheckIn', 'entity_id' => $report->id, 'action' => 'Research Check-in Flagged']);
        $this->get(route('monitoring.flagged-notice'))->assertOk()->assertSee('1 active notice')
            ->assertDontSee('untreated wound')->assertDontSee('behavioral observations');
        $this->get(route('monitoring.my-checkins'))->assertOk()->assertSee('Under Review')->assertSee('3-Month');

        $this->actingAs($this->matchingUser(Role::Administrator))->get(route('admin.monitoring.flagged'))
            ->assertOk()->assertSee('1 unresolved')->assertSee('untreated wound')
            ->assertSee(route('admin.research.video', $report->id))->assertDontSee('reminders sent');
        $this->get(route('admin.dashboard'))->assertOk()->assertViewHas('flaggedMonitoring', 1);
        $this->assertDatabaseCount('post_adoption_logs', 0);
        Mail::assertNothingOutgoing();
        Queue::assertNothingPushed();
    }

    public function test_backfill_is_opt_in_and_idempotent_without_changing_submitted_data_or_an_existing_outcome(): void
    {
        $placement = $this->placement();
        $old = $this->report($placement, '3-day', ['concerns' => 'No shelter and an untreated injury.']);
        $healthy = $this->report($placement, '3-week');
        $closed = $this->report($placement, '3-month', ['pet_current_status' => 'Poor']);
        $closed->forceFill([
            'is_flagged' => true, 'resolved_at' => now(), 'resolution_outcome' => ResolutionOutcome::Resolved,
            'welfare_case' => ['resolution_note' => 'Earlier result', 'history' => [['event' => 'resolution_recorded']]],
        ])->save();
        $snapshot = $old->only('answers', 'submitted_at', 'video_path', 'video_sha256');
        $originalState = $closed->fresh()->welfare_case;
        $schema = DB::getDatabaseName();
        $this->artisan('research:evaluate-welfare', ['--database' => 'pairfect_paws', '--apply' => true])->assertFailed();
        $this->artisan('research:evaluate-welfare', ['--database' => $schema])->expectsOutput('Dry run: 3 reports reviewed; 1 reports would receive new welfare reasons. No notifications sent.')->assertSuccessful();
        $this->assertNull($old->fresh()->welfare_evaluated_at);

        $this->artisan('research:evaluate-welfare', ['--database' => $schema, '--apply' => true])->assertSuccessful();
        $this->assertTrue($old->fresh()->is_flagged);
        $this->assertFalse($healthy->fresh()->is_flagged);
        $this->assertNotNull($healthy->fresh()->welfare_evaluated_at);
        $this->assertEquals($snapshot, $old->fresh()->only('answers', 'submitted_at', 'video_path', 'video_sha256'));
        $this->assertSame($originalState, $closed->fresh()->welfare_case);
        $this->assertSame(ResolutionOutcome::Resolved, $closed->fresh()->resolution_outcome);
        $this->assertSame('historical_survey_flagged', $old->fresh()->welfare_case['history'][0]['event']);
        $state = $old->fresh()->welfare_case;
        $this->artisan('research:evaluate-welfare', ['--database' => $schema, '--apply' => true])
            ->expectsOutput('Applied: 0 reports reviewed; 0 reports received new welfare reasons. No notifications sent.')->assertSuccessful();
        $this->assertSame($state, $old->fresh()->welfare_case);
        $this->assertDatabaseCount('handover_notifications', 0);
        config(['research.enabled' => false]);
        $this->artisan('research:evaluate-welfare', ['--database' => $schema, '--apply' => true])->assertFailed();
        Mail::assertNothingOutgoing();
    }

    public function test_staff_roles_follow_up_ownership_and_reopening_do_not_touch_same_number_normal_records(): void
    {
        $placement = $this->placement();
        $owner = $placement->user;
        $other = $this->matchingUser();
        $volunteer = $this->matchingUser(Role::Volunteer);
        $admin = $this->matchingUser(Role::Administrator);
        $report = $this->report($placement, '3-day');
        $normalApplication = AdoptionApplication::create([
            'user_id' => $owner->id, 'pet_id' => $placement->pet_id, 'status' => ApplicationStatus::Approved,
        ]);
        $normalLog = new PostAdoptionLog([
            'application_id' => $normalApplication->id, 'milestone' => Milestone::ThreeDays, 'scheduled_date' => today(),
        ]);
        $normalLog->id = $report->id;
        $normalLog->save();
        $this->assertSame($normalLog->id, $report->id);
        $flag = route('admin.research.monitoring.flag', $report->id);
        $resolve = route('admin.research.monitoring.resolve', $report->id);
        $reply = route('research.checkin.follow-up', $report->id);
        $followUp = ['resolution_outcome' => 'follow_up_required', 'resolution_note' => 'Please share the care update.'];

        $this->actingAs($owner)->post($flag, ['reason' => 'Wrong role'])->assertRedirect(route('access-denied'));
        $this->actingAs($other)->get(route('research.checkin.modal', $report->id))->assertNotFound();
        $this->postJson($reply, ['follow_up_notes' => 'Wrong adopter'])->assertNotFound();
        $this->actingAs($volunteer)->post($flag)->assertSessionHasErrors('reason');
        $this->post(route('admin.monitoring.flag', $normalLog), ['reason' => 'Do not use normal routes'])->assertNotFound();
        $this->post($flag, ['reason' => 'Private staff-only detail.'])->assertRedirect();
        $this->post($flag, ['reason' => 'Private staff-only detail.'])->assertRedirect();
        $this->assertCount(1, $report->fresh()->welfare_case['flag_reasons']);
        $this->assertSame($volunteer->id, $report->fresh()->welfare_case['flag_reasons'][0]['flagged_by_user_id']);
        $this->post($resolve, $followUp)->assertRedirect(route('access-denied'));
        $this->get(route('admin.monitoring.flagged'))->assertOk()->assertDontSee('Record Outcome');

        $this->actingAs($admin)->post($resolve, $followUp)->assertRedirect();
        $this->assertNull($report->fresh()->resolved_at);
        $this->assertSame($admin->id, $report->fresh()->welfare_case['decision_by_user_id']);
        $this->assertDatabaseHas('handover_notifications', ['user_id' => $owner->id, 'kind' => 'research_welfare_outcome', 'entity_type' => 'ResearchCheckIn']);

        $this->actingAs($owner)->get(route('research.checkin.modal', $report->id))->assertOk()
            ->assertSee('Submit Follow-up')->assertSee($reply)->assertDontSee('Private staff-only detail.');
        $this->postJson($reply, ['follow_up_notes' => ''])->assertUnprocessable()->assertJsonValidationErrors('follow_up_notes');
        $this->postJson($reply, ['follow_up_notes' => 'Practiced the care update.'])->assertOk()->assertJsonPath('success', true);
        $this->postJson($reply, ['follow_up_notes' => 'Practiced the care update.'])->assertOk();
        $this->postJson($reply, ['follow_up_notes' => 'Added a second update.'])->assertOk();

        $this->actingAs($admin)->get(route('admin.monitoring.flagged'))->assertOk()->assertSee('Added a second update.');
        $this->post($resolve, ['resolution_outcome' => 'resolved', 'resolution_note' => 'Review complete.'])->assertRedirect();
        $this->assertNotNull($report->fresh()->resolved_at);
        $this->get(route('admin.dashboard'))->assertViewHas('flaggedMonitoring', 0);
        $this->get(route('admin.monitoring.flagged'))->assertSee('No unresolved flags');
        $this->actingAs($owner)->postJson($reply, ['follow_up_notes' => 'Stale response'])->assertConflict();

        $this->actingAs($volunteer)->post($flag, ['reason' => 'New information received.'])->assertRedirect();
        $reopened = $report->fresh();
        $this->assertNull($reopened->resolution_outcome);
        $this->assertNull($reopened->resolved_at);
        $this->assertSame(['manual_flag', 'resolution_recorded', 'adopter_follow_up', 'adopter_follow_up', 'resolution_recorded', 'manual_flag'], array_column($reopened->welfare_case['history'], 'event'));
        $this->assertFalse($normalLog->fresh()->is_flagged);
        config(['research.enabled' => false]);
        $this->post($flag, ['reason' => 'Wrong mode'])->assertNotFound();
        Mail::assertNothingOutgoing();
        Queue::assertNothingPushed();
    }

    #[DataProvider('closingOutcomes')]
    public function test_closing_outcomes_record_the_original_choice_without_real_return_effects(string $value): void
    {
        $placement = $this->placement();
        $report = $this->report($placement, '3-day');
        $admin = $this->matchingUser(Role::Administrator);
        $volunteer = $this->matchingUser(Role::Volunteer);
        $this->actingAs($admin)->post(route('admin.research.monitoring.flag', $report->id), ['reason' => 'Review required.'])->assertRedirect();
        $path = route('admin.research.monitoring.resolve', $report->id);
        $resolution = ['resolution_outcome' => $value, 'resolution_note' => 'Recorded as selected.'];
        $return = $value === 'pet_returned';
        if ($return) {
            $this->post($path, $resolution)->assertSessionHasErrors(['return_date', 'return_reason', 'return_condition', 'return_handled_by_user_id', 'confirm_return']);
            $resolution += [
                'return_date' => today('Asia/Manila')->toDateString(), 'return_reason' => 'Recorded for the exercise.',
                'return_condition' => 'As described in the exercise.', 'return_handled_by_user_id' => $volunteer->id, 'confirm_return' => '1',
            ];
            $this->post($path, array_replace($resolution, ['return_date' => today('Asia/Manila')->addDay()->toDateString()]))->assertSessionHasErrors('return_date');
        }
        $this->post($path, $resolution)->assertRedirect();
        $this->assertSame(ResolutionOutcome::from($value), $report->fresh()->resolution_outcome);
        $this->assertNotNull($report->fresh()->resolved_at);
        $this->assertSame(AvailabilityStatus::Adopted, $placement->pet->fresh()->availability_status);
        $this->assertSame(ApplicationStatus::Approved, $placement->fresh()->status);
        $this->assertDatabaseCount('handovers', 0);
        $this->assertDatabaseCount('post_adoption_logs', 0);
        $this->get(route('admin.monitoring.index'))->assertOk()->assertSee(ResolutionOutcome::from($value)->label())
            ->assertSee('Recorded as selected.');
        $this->get(route('admin.dashboard'))->assertViewHas('activeMonitoring', $return ? 0 : 1);
        if ($return) {
            $this->assertSame($volunteer->id, $report->fresh()->welfare_case['return']['handled_by_user_id']);
            $this->post(route('admin.research.monitoring.flag', $report->id), ['reason' => 'Cannot reopen'])->assertSessionHasErrors('reason');
            $second = $this->report($placement, '3-week');
            $this->post(route('admin.research.monitoring.flag', $second->id), ['reason' => 'Separate case'])->assertRedirect();
            $this->post(route('admin.research.monitoring.resolve', $second->id), $resolution)->assertSessionHasErrors('resolution_outcome');
        }
        $this->actingAs($placement->user)->get(route('monitoring.my-checkins'))->assertOk()
            ->assertSee($return ? 'Placement ended' : 'Submit Now');
        if ($return) {
            $this->get(route('research.report', $placement))->assertRedirect(route('monitoring.my-checkins'));
            $this->postJson(route('research.capture-challenge', [$placement, '3-month']))->assertConflict();
        }
        Mail::assertNothingOutgoing();
        Queue::assertNothingPushed();
    }

    public static function closingOutcomes(): array
    {
        return [['resolved'], ['veterinary_attention'], ['return_recommended'], ['pet_returned']];
    }

    private function placement(): ResearchPlacement
    {
        $adopter = $this->matchingUser();
        $pet = $this->matchingPet(['availability_status' => AvailabilityStatus::Adopted->value]);
        DB::table('research_participants')->insert(['user_id' => $adopter->id, 'enrolled_at' => now()]);
        DB::table('research_catalog')->insert(['pet_id' => $pet->id]);

        $placement = ResearchPlacement::create([
            'user_id' => $adopter->id, 'pet_id' => $pet->id, 'status' => ApplicationStatus::Approved,
            'compatibility_score' => 65, 'algorithm_version' => '2.0', 'approved_at' => now(), 'released_at' => now(),
        ]);
        $placement->forceFill(['received_at' => now()])->save();

        return $placement;
    }

    private function report(ResearchPlacement $placement, string $milestone, array $answers = []): ResearchCheckIn
    {
        return $placement->researchCheckIns()->create([
            'milestone' => $milestone, 'answers' => $this->answers($answers), 'submitted_at' => now()->subDay(),
        ]);
    }

    private function answers(array $overrides = []): array
    {
        return array_replace([
            'pet_current_status' => 'Good', 'living_conditions' => 'Indoor Only',
            'eating_habits' => 'Normal', 'behavioral_observations' => 'Still adjusting',
        ], $overrides);
    }
}
