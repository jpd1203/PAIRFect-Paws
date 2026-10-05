<?php

namespace Tests\Feature;

use App\Enums\ApplicationStatus;
use App\Enums\AvailabilityStatus;
use App\Enums\Milestone;
use App\Enums\Role;
use App\Mail\TransactionalMail;
use App\Mail\WelfareReportReceiptMail;
use App\Models\AdoptionApplication;
use App\Models\Handover;
use App\Models\Pet;
use App\Models\PostAdoptionCaptureChallenge;
use App\Models\PostAdoptionLog;
use App\Models\User;
use App\Services\PostAdoptionCaptureChallengeService;
use App\Services\PostAdoptionClock;
use App\Services\VideoDurationProbe;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Testing\File;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class PostAdoptionMonitoringTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->bindProbeDurations(array_fill(0, 20, 3000));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }

    public function test_report_page_uses_live_video_capture_without_a_file_picker(): void
    {
        [$adopter, , $log] = $this->monitoringLog();

        $response = $this->actingAs($adopter)->get(route('monitoring.create', $log));

        $response->assertOk()
            ->assertSee('data-camera-video', false)
            ->assertSee('data-camera-preview', false)
            ->assertSee('data-camera-record', false)
            ->assertSee('data-camera-recording-duration', false)
            ->assertSee('data-camera-challenge-url', false)
            ->assertSee('five-minute, single-use challenge')
            ->assertSee('3-second welfare video');
        $this->assertStringNotContainsString('type="file"', $response->getContent());
        $this->assertStringNotContainsString('<canvas', $response->getContent());
    }

    public function test_owner_receives_a_five_minute_challenge_without_persisting_its_secret(): void
    {
        $this->freezeUtcTime('2026-08-21T04:00:00+00:00');
        [$adopter, , $log] = $this->monitoringLog();

        $response = $this->actingAs($adopter)
            ->postJson(route('monitoring.capture-challenge', $log));

        $response->assertCreated()
            ->assertHeader('Cache-Control', 'no-store, private');
        $this->assertEquals(300, $response->json('expires_in_seconds'));
        $this->assertEquals(3, $response->json('required_duration_seconds'));
        $this->assertEquals(0.75, $response->json('duration_tolerance_seconds'));

        $challenge = PostAdoptionCaptureChallenge::findOrFail($response->json('challenge_id'));
        $this->assertTrue(Str::isUuid($challenge->id));
        $this->assertNotSame($response->json('challenge_token'), $challenge->token_hash);
        $this->assertSame(64, strlen($response->json('challenge_token')));
        $this->assertSame(64, strlen($challenge->session_hash));
        $this->assertNotSame($challenge->token_hash, $challenge->session_hash);
        $this->assertSame($adopter->id, $challenge->user_id);
        $this->assertSame($log->id, $challenge->post_adoption_log_id);
        $this->assertEquals(300, $challenge->created_at->diffInSeconds($challenge->expires_at));
    }

    public function test_challenge_is_session_bound_hashed_and_strictly_single_use(): void
    {
        $this->freezeUtcTime('2026-08-21T04:00:00+00:00');
        Storage::fake('local');
        Mail::fake();
        [$adopter, , $log] = $this->monitoringLog();

        $issued = $this->actingAs($adopter)
            ->postJson(route('monitoring.capture-challenge', $log))
            ->assertCreated();
        $challengeId = $issued->json('challenge_id');
        $token = $issued->json('challenge_token');
        $originalSessionBinding = session('post_adoption_capture_session_binding');
        $challenge = PostAdoptionCaptureChallenge::findOrFail($challengeId);

        $this->assertTrue(Str::isUuid($challengeId));
        $this->assertIsString($originalSessionBinding);
        $this->assertSame(64, strlen($originalSessionBinding));

        $wrongSessionPayload = $this->validReportPayload([
            'capture_challenge_id' => $challengeId,
            'capture_challenge_token' => $token,
            'video' => $this->fakeVideo('webm', 'wrong-session-attempt'),
        ]);
        $this->withSession([
            'post_adoption_capture_session_binding' => str_repeat('f', 64),
        ])->actingAs($adopter)
            ->postJson(route('monitoring.submit', $log), $wrongSessionPayload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('capture_challenge');

        $this->assertNull($challenge->fresh()->consumed_at);
        $this->assertNull($log->fresh()->submitted_date);
        $this->assertSame([], Storage::disk('local')->allFiles());

        $video = $this->fakeVideo('webm', 'valid-session-recording');
        $expectedSha256 = hash_file('sha256', $video->getRealPath());
        $validPayload = $this->validReportPayload([
            'capture_challenge_id' => $challengeId,
            'capture_challenge_token' => $token,
            'video' => $video,
        ]);
        $this->withSession([
            'post_adoption_capture_session_binding' => $originalSessionBinding,
        ])->actingAs($adopter)
            ->postJson(route('monitoring.submit', $log), $validPayload)
            ->assertCreated();

        $log->refresh();
        $challenge->refresh();
        $this->assertSame($expectedSha256, $log->video_sha256);
        $this->assertSame($expectedSha256, $challenge->video_sha256);
        $this->assertNotNull($challenge->consumed_at);

        try {
            app(PostAdoptionCaptureChallengeService::class)->validate(
                $challengeId,
                $token,
                $log,
                $adopter,
                $originalSessionBinding,
                CarbonImmutable::now('UTC'),
            );
            $this->fail('A consumed capture challenge was accepted for reuse.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('capture_challenge', $exception->errors());
        }
    }

    public function test_forged_and_expired_challenges_are_rejected_before_storage(): void
    {
        $this->freezeUtcTime('2026-08-21T04:00:00+00:00');
        Storage::fake('local');
        [$adopter, , $log] = $this->monitoringLog();
        $payload = $this->validReportPayloadFor($adopter, $log);
        $challengeId = $payload['capture_challenge_id'];

        $forged = $payload;
        $forged['capture_challenge_token'] = str_repeat('a', 64);
        $this->actingAs($adopter)
            ->postJson(route('monitoring.submit', $log), $forged)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('capture_challenge');

        $this->freezeUtcTime('2026-08-21T04:05:01+00:00');
        $payload['camera_captured_at'] = CarbonImmutable::now('UTC')->toIso8601String();
        $payload['video'] = $this->fakeVideo('mp4', 'expired-attempt');
        $this->actingAs($adopter)
            ->postJson(route('monitoring.submit', $log), $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('capture_challenge');

        $this->assertNull($log->fresh()->submitted_date);
        $this->assertNull(PostAdoptionCaptureChallenge::findOrFail($challengeId)->consumed_at);
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_new_capture_session_invalidates_the_previous_unused_challenge(): void
    {
        [$adopter, , $log] = $this->monitoringLog();

        $first = $this->actingAs($adopter)
            ->postJson(route('monitoring.capture-challenge', $log))
            ->assertCreated();
        $second = $this->actingAs($adopter)
            ->postJson(route('monitoring.capture-challenge', $log))
            ->assertCreated();

        $this->assertDatabaseMissing('post_adoption_capture_challenges', [
            'id' => $first->json('challenge_id'),
        ]);
        $this->assertDatabaseHas('post_adoption_capture_challenges', [
            'id' => $second->json('challenge_id'),
            'post_adoption_log_id' => $log->id,
            'consumed_at' => null,
        ]);
    }

    public function test_only_owner_of_an_approved_due_application_can_capture_a_report(): void
    {
        [$owner, , $log] = $this->monitoringLog();
        $otherAdopter = $this->adopter('other-owner');

        $this->actingAs($otherAdopter)
            ->postJson(route('monitoring.capture-challenge', $log))
            ->assertForbidden();

        [, , $pendingLog] = $this->monitoringLog(ApplicationStatus::UnderReview);
        $this->actingAs($pendingLog->adoptionApplication->user)
            ->postJson(route('monitoring.capture-challenge', $pendingLog))
            ->assertUnprocessable();

        $log->update([
            'scheduled_date' => app(PostAdoptionClock::class)->today()->addDay()->toDateString(),
        ]);
        $this->actingAs($owner)
            ->postJson(route('monitoring.capture-challenge', $log))
            ->assertUnprocessable();
    }

    public function test_video_submission_is_private_and_records_hash_duration_metadata_mail_and_audit(): void
    {
        $this->freezeUtcTime('2026-08-21T04:00:00+00:00');
        Storage::fake('local');
        Storage::fake('public');
        Mail::fake();
        [$adopter, , $log] = $this->monitoringLog();

        $response = $this->actingAs($adopter)->postJson(
            route('monitoring.submit', $log),
            $this->validReportPayloadFor($adopter, $log, [
                'recording_duration_ms' => 3012,
                'video' => $this->fakeVideo('mp4', 'successful-private-recording'),
            ]),
        );

        $response->assertCreated()
            ->assertJsonPath('redirect', route('monitoring.my-checkins'));

        $log->refresh();
        $verification = $log->survey_data['_verification'];
        $this->assertNotNull($log->submitted_date);
        $this->assertSame('server_capture_challenge+video_sha256', $verification['method']);
        $this->assertSame('video/mp4', $verification['video_mime_type']);
        $this->assertSame(3000, $verification['required_duration_ms']);
        $this->assertSame(750, $verification['duration_tolerance_ms']);
        $this->assertSame(3012, $verification['declared_duration_ms']);
        $this->assertSame(3000, $verification['verified_duration_ms']);
        $this->assertSame('verified', $verification['duration_verification_status']);
        $this->assertSame($log->video_sha256, $verification['video_sha256']);
        $this->assertNotEmpty($verification['challenge_consumed_at']);
        $this->assertSame(3000, $log->video_duration_ms);
        $this->assertSame('video/mp4', $log->video_mime_type);
        $this->assertStringStartsWith("post-adoption-videos/{$log->application_id}/", $log->video_path);
        $this->assertStringEndsWith('.mp4', $log->video_path);
        $this->assertNull($log->photo_path);
        Storage::disk('local')->assertExists($log->video_path);
        $this->assertSame([], Storage::disk('public')->allFiles());

        $challenge = PostAdoptionCaptureChallenge::findOrFail($verification['capture_challenge_id']);
        $this->assertNotNull($challenge->consumed_at);
        $this->assertSame($log->video_sha256, $challenge->video_sha256);

        Mail::assertQueued(WelfareReportReceiptMail::class, function ($mail) use ($adopter, $log): bool {
            return $mail->hasTo($adopter->email) && $mail->log->is($log);
        });
        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $adopter->id,
            'action' => 'Post-Adoption Welfare Report Submitted',
            'entity_name' => 'PostAdoptionLog',
            'entity_id' => $log->id,
        ]);
    }

    public function test_verified_browser_webm_with_negative_survey_is_flagged(): void
    {
        Storage::fake('local');
        Mail::fake();
        [$adopter, , $log] = $this->monitoringLog();
        $staff = User::create([
            'first_name' => 'Welfare',
            'last_name' => 'Reviewer',
            'email' => 'welfare-reviewer@example.test',
            'password' => 'password',
            'role' => Role::Volunteer->value,
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        $admin = User::create([
            'first_name' => 'Welfare', 'last_name' => 'Admin',
            'email' => 'welfare-admin@example.test', 'password' => 'password',
            'role' => Role::Administrator->value, 'is_active' => true, 'email_verified_at' => now(),
        ]);

        $this->actingAs($adopter)->postJson(
            route('monitoring.submit', $log),
            $this->validReportPayloadFor($adopter, $log, [
                'video' => $this->fakeVideo('webm', 'browser-webm-recording'),
                'pet_current_status' => 'Poor',
                'concerns' => 'The pet currently has no access to clean water.',
            ]),
        )->assertCreated();

        $log->refresh();
        $this->assertSame('video/webm', $log->video_mime_type);
        $this->assertStringEndsWith('.webm', $log->video_path);
        $this->assertSame('verified', $log->survey_data['_verification']['duration_verification_status']);
        $this->assertTrue($log->is_flagged);
        $this->assertSame(1, $adopter->inAppNotifications()->where('kind', 'welfare_report_received')->count());
        $this->assertSame(1, $admin->inAppNotifications()->where('kind', 'welfare_report_staff_alert')->count());
        $this->assertSame(0, $staff->inAppNotifications()->where('kind', 'welfare_report_staff_alert')->count());
        $this->assertContains('survey_welfare_concern', array_column($log->flag_reasons, 'code'));
        Storage::disk('local')->assertExists($log->video_path);
        Mail::assertQueued(TransactionalMail::class, fn (TransactionalMail $mail): bool => $mail->hasTo($staff->email));
    }

    public function test_missing_ffprobe_rejects_video_without_consuming_the_challenge(): void
    {
        Storage::fake('local');
        config()->set('post_adoption.capture.ffprobe_path', '');
        $this->app->instance(VideoDurationProbe::class, new VideoDurationProbe);
        [$adopter, , $log] = $this->monitoringLog();
        $payload = $this->validReportPayloadFor($adopter, $log);

        $this->actingAs($adopter)
            ->postJson(route('monitoring.submit', $log), $payload)
            ->assertStatus(503);

        $this->assertNull($log->fresh()->submitted_date);
        $this->assertNull(PostAdoptionCaptureChallenge::findOrFail($payload['capture_challenge_id'])->consumed_at);
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_invalid_container_is_rejected_without_consuming_challenge_or_storing_media(): void
    {
        Storage::fake('local');
        [$adopter, , $log] = $this->monitoringLog();
        $payload = $this->validReportPayloadFor($adopter, $log, [
            'video' => UploadedFile::fake()->create('forged.mp4', 1, 'video/mp4'),
        ]);

        $this->actingAs($adopter)
            ->postJson(route('monitoring.submit', $log), $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('video');

        $this->assertNull($log->fresh()->submitted_date);
        $this->assertNull(
            PostAdoptionCaptureChallenge::findOrFail($payload['capture_challenge_id'])->consumed_at,
        );
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_non_video_oversized_and_out_of_range_declared_duration_are_rejected(): void
    {
        Storage::fake('local');

        $cases = [
            ['video', ['video' => UploadedFile::fake()->image('gallery-photo.jpg', 120, 120)]],
            ['video', ['video' => UploadedFile::fake()->create('too-large.webm', 12_289, 'video/webm')]],
            ['recording_duration_ms', ['recording_duration_ms' => 2249]],
            ['recording_duration_ms', ['recording_duration_ms' => 3751]],
        ];

        foreach ($cases as $index => [$errorField, $overrides]) {
            [$adopter, , $log] = $this->monitoringLog();
            $overrides['video'] ??= $this->fakeVideo('mp4', "invalid-case-{$index}");
            $payload = $this->validReportPayloadFor($adopter, $log, $overrides);

            $this->actingAs($adopter)
                ->postJson(route('monitoring.submit', $log), $payload)
                ->assertUnprocessable()
                ->assertJsonValidationErrors($errorField);

            $this->assertNull($log->fresh()->submitted_date);
            $this->assertNull(
                PostAdoptionCaptureChallenge::findOrFail($payload['capture_challenge_id'])->consumed_at,
            );
        }

        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_probed_duration_boundaries_are_accepted_and_override_declared_duration(): void
    {
        Storage::fake('local');
        Mail::fake();
        $this->bindProbeDurations([2250, 3750]);

        foreach ([2250, 3750] as $durationMs) {
            [$adopter, , $log] = $this->monitoringLog();

            $this->actingAs($adopter)->postJson(
                route('monitoring.submit', $log),
                $this->validReportPayloadFor($adopter, $log, [
                    'video' => $this->fakeVideo('mp4', "probed-boundary-{$durationMs}"),
                ]),
            )->assertCreated();

            $log->refresh();
            $this->assertSame($durationMs, $log->video_duration_ms);
            $this->assertSame($durationMs, $log->survey_data['_verification']['verified_duration_ms']);
            $this->assertSame('verified', $log->survey_data['_verification']['duration_verification_status']);
        }
    }

    public function test_browser_webm_without_container_duration_uses_video_packet_timestamps(): void
    {
        Storage::fake('local');
        Mail::fake();
        $this->bindProbeDurations([3000, 1500], true);

        [$adopter, , $log] = $this->monitoringLog();
        $this->actingAs($adopter)->postJson(
            route('monitoring.submit', $log),
            $this->validReportPayloadFor($adopter, $log, [
                'video' => $this->fakeVideo('webm', 'webm-without-container-duration'),
            ]),
        )->assertCreated();
        $this->assertSame(3000, $log->fresh()->video_duration_ms);

        [$otherAdopter, , $shortLog] = $this->monitoringLog();
        $this->actingAs($otherAdopter)->postJson(
            route('monitoring.submit', $shortLog),
            $this->validReportPayloadFor($otherAdopter, $shortLog, [
                'video' => $this->fakeVideo('webm', 'short-webm-without-container-duration'),
            ]),
        )->assertUnprocessable()->assertJsonValidationErrors('video');
        $this->assertNull($shortLog->fresh()->submitted_date);
    }

    public function test_out_of_range_probed_duration_is_rejected_without_consuming_challenge(): void
    {
        Storage::fake('local');
        $this->bindProbeDuration(2249);
        [$adopter, , $log] = $this->monitoringLog();
        $payload = $this->validReportPayloadFor($adopter, $log);

        $this->actingAs($adopter)
            ->postJson(route('monitoring.submit', $log), $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('video');

        $this->assertNull($log->fresh()->submitted_date);
        $this->assertNull(
            PostAdoptionCaptureChallenge::findOrFail($payload['capture_challenge_id'])->consumed_at,
        );
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_same_video_bytes_cannot_be_replayed_for_another_check_in(): void
    {
        Storage::fake('local');
        [$adopter, $application, $log] = $this->monitoringLog();
        $priorVideo = $this->fakeVideo('webm', 'replayed-browser-video');

        PostAdoptionLog::create([
            'application_id' => $application->id,
            'milestone' => Milestone::ThreeWeeks,
            'scheduled_date' => app(PostAdoptionClock::class)->today()->toDateString(),
            'video_sha256' => hash_file('sha256', $priorVideo->getRealPath()),
        ]);

        $payload = $this->validReportPayloadFor($adopter, $log, [
            'video' => $this->fakeVideo('webm', 'replayed-browser-video'),
        ]);
        $this->actingAs($adopter)
            ->postJson(route('monitoring.submit', $log), $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('video');

        $this->assertNull($log->fresh()->submitted_date);
        $this->assertNull(
            PostAdoptionCaptureChallenge::findOrFail($payload['capture_challenge_id'])->consumed_at,
        );
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_unique_video_hash_index_is_authoritative_for_concurrent_replay_safety(): void
    {
        $this->assertTrue(Schema::hasIndex(
            'post_adoption_logs',
            'post_adoption_logs_video_sha256_unique',
        ));

        [, $application, $firstLog] = $this->monitoringLog();
        $hash = hash('sha256', 'same-concurrent-video-bytes');
        $firstLog->update(['video_sha256' => $hash]);
        $secondLog = PostAdoptionLog::create([
            'application_id' => $application->id,
            'milestone' => Milestone::ThreeWeeks,
            'scheduled_date' => app(PostAdoptionClock::class)->today()->toDateString(),
        ]);

        try {
            $secondLog->update(['video_sha256' => $hash]);
            $this->fail('The database accepted a duplicate welfare-video SHA-256 value.');
        } catch (UniqueConstraintViolationException) {
            $this->assertNull($secondLog->fresh()->video_sha256);
        }
    }

    public function test_public_video_disk_fails_closed_without_consuming_challenge(): void
    {
        Storage::fake('public');
        config()->set('post_adoption.video_disk', 'public');
        [$adopter, , $log] = $this->monitoringLog();
        $payload = $this->validReportPayloadFor($adopter, $log);

        $this->actingAs($adopter)
            ->postJson(route('monitoring.submit', $log), $payload)
            ->assertStatus(503);

        $this->assertNull($log->fresh()->submitted_date);
        $this->assertNull(
            PostAdoptionCaptureChallenge::findOrFail($payload['capture_challenge_id'])->consumed_at,
        );
        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    public function test_already_submitted_check_in_cannot_be_overwritten(): void
    {
        Storage::fake('local');
        [$adopter, , $log] = $this->monitoringLog();
        $log->update([
            'submitted_date' => now(),
            'video_path' => 'post-adoption-videos/existing.webm',
            'video_sha256' => hash('sha256', 'existing-submission'),
            'video_mime_type' => 'video/webm',
            'video_duration_ms' => 3000,
        ]);

        $this->actingAs($adopter)->postJson(
            route('monitoring.submit', $log),
            $this->validReportPayload(),
        )->assertUnprocessable()->assertJsonValidationErrors('report');

        $this->assertSame('post-adoption-videos/existing.webm', $log->fresh()->video_path);
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    /** @return array{User, AdoptionApplication, PostAdoptionLog} */
    private function monitoringLog(ApplicationStatus $status = ApplicationStatus::Approved): array
    {
        $suffix = str_replace('.', '', uniqid('', true));
        $adopter = $this->adopter("monitoring-{$suffix}");
        $pet = Pet::create([
            'name' => "Monitoring Pet {$suffix}",
            'species' => 'Dog',
            'availability_status' => AvailabilityStatus::Adopted->value,
        ]);
        $application = AdoptionApplication::create([
            'user_id' => $adopter->id,
            'pet_id' => $pet->id,
            'status' => $status->value,
            'queue_closed_at' => now(),
        ]);
        if ($status === ApplicationStatus::Approved) {
            Handover::create([
                'code' => 'HV-TEST-'.$application->id,
                'application_id' => $application->id,
                'pet_id' => $pet->id,
                'user_id' => $adopter->id,
                'released_at' => now(),
                'adopter_outcome' => 'received',
                'received_at' => now(),
            ]);
        }
        $log = PostAdoptionLog::create([
            'application_id' => $application->id,
            'milestone' => Milestone::ThreeDays,
            'scheduled_date' => app(PostAdoptionClock::class)->today()->toDateString(),
        ]);

        return [$adopter, $application, $log];
    }

    private function adopter(string $identity): User
    {
        return User::create([
            'first_name' => 'Monitoring',
            'last_name' => 'Adopter',
            'email' => "{$identity}@example.test",
            'password' => 'password',
            'role' => Role::Adopter->value,
            'email_verified_at' => now(),
            'is_active' => true,
        ]);
    }

    /** @param array<string, mixed> $overrides */
    private function validReportPayload(array $overrides = []): array
    {
        return array_replace([
            'pet_current_status' => 'Good',
            'behavioral_observations' => 'Calm and playful.',
            'living_conditions' => 'Safe indoor home.',
            'eating_habits' => 'Eating regular balanced meals.',
            'vet_visit_details' => 'No urgent treatment needed.',
            'concerns' => 'None.',
            'camera_captured_at' => CarbonImmutable::now('UTC')->toIso8601String(),
            'recording_duration_ms' => 3000,
            'video' => $this->fakeVideo('mp4', uniqid('live-recording-', true)),
        ], $overrides);
    }

    /** @param array<string, mixed> $overrides */
    private function validReportPayloadFor(User $adopter, PostAdoptionLog $log, array $overrides = []): array
    {
        $challenge = $this->actingAs($adopter)
            ->postJson(route('monitoring.capture-challenge', $log))
            ->assertCreated();

        return $this->validReportPayload(array_replace([
            'capture_challenge_id' => $challenge->json('challenge_id'),
            'capture_challenge_token' => $challenge->json('challenge_token'),
        ], $overrides));
    }

    private function fakeVideo(string $container = 'mp4', string $nonce = 'recording'): File
    {
        $suffix = hash('sha256', $nonce, true);

        if ($container === 'webm') {
            $header = hex2bin(
                '1a45dfa39f4286810142f7810142f2810442f381084282847765626d4287810242858102',
            );

            return UploadedFile::fake()->createWithContent(
                "live-checkin-{$nonce}.webm",
                $header.$suffix,
            )->mimeType('video/webm');
        }

        $header = hex2bin('00000018667479706d703432000000006d70343269736f6d');
        $freeAtom = pack('N', 8 + strlen($suffix)).'free'.$suffix;

        return UploadedFile::fake()->createWithContent(
            "live-checkin-{$nonce}.mp4",
            $header.$freeAtom,
        )->mimeType('video/mp4');
    }

    private function bindProbeDuration(int $durationMs): void
    {
        $this->bindProbeDurations([$durationMs]);
    }

    /** @param list<int> $durationMilliseconds */
    private function bindProbeDurations(array $durationMilliseconds, bool $withoutContainerDuration = false): void
    {
        config()->set('post_adoption.capture.ffprobe_path', PHP_BINARY);
        $remainingDurations = $durationMilliseconds;

        $this->app->instance(
            VideoDurationProbe::class,
            new VideoDurationProbe(
                static function (array $command) use (&$remainingDurations, $withoutContainerDuration): Process {
                    if ($withoutContainerDuration) {
                        self::assertContains('-show_packets', $command);
                        self::assertContains('stream=codec_type:format=duration:packet=pts_time,duration_time', $command);
                    }

                    $durationMs = array_shift($remainingDurations);
                    $output = json_encode([
                        'streams' => [['codec_type' => 'video']],
                        'format' => $withoutContainerDuration ? [] : ['duration' => $durationMs / 1000],
                        'packets' => $withoutContainerDuration ? [
                            ['pts_time' => '0', 'duration_time' => '0.033'],
                            ['pts_time' => (string) (($durationMs - 33) / 1000), 'duration_time' => '0.033'],
                        ] : [],
                    ], JSON_THROW_ON_ERROR);
                    $code = 'fwrite(STDOUT, '.var_export($output, true).');';

                    return new Process([PHP_BINARY, '-r', $code]);
                },
            ),
        );
    }

    private function freezeUtcTime(string $dateTime): void
    {
        $now = CarbonImmutable::parse($dateTime);
        Carbon::setTestNow($now);
        CarbonImmutable::setTestNow($now);
    }
}
