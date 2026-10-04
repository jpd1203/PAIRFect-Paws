<?php

namespace Tests\Feature;

use App\Enums\ApplicationStatus;
use App\Enums\AvailabilityStatus;
use App\Enums\Role;
use App\Models\AdoptionApplication;
use App\Models\Handover;
use App\Models\AuditLog;
use App\Models\HandoverNotification;
use App\Models\Pet;
use App\Models\PostAdoptionLog;
use App\Models\User;
use App\Mail\TransactionalMail;
use App\Services\HandoverService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class HandoverWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_adopter_without_approved_adoption_sees_handover_unavailable_notice(): void
    {
        $adopter = $this->adopter('new-owner@example.test');

        $this->actingAs($adopter)
            ->get(route('animal.index'))
            ->assertOk()
            ->assertSee('data-handover-unavailable', false);

        $this->get(route('adopter.handover.my'))
            ->assertRedirect(route('application.index'))
            ->assertSessionHas('toast', [
                'type' => 'error',
                'message' => 'You have no pending handovers. Handover Status becomes available after an adoption is approved.',
            ]);
    }

    public function test_adopter_handover_status_backfills_their_approved_application_and_shows_the_status_view(): void
    {
        $adopter = $this->adopter('owner@example.test');
        $application = $this->approvedApplication($adopter);

        $response = $this->actingAs($adopter)->get(route('adopter.handover.my'));

        $this->get(route('animal.index'))
            ->assertOk()
            ->assertDontSee('data-handover-unavailable', false);

        $handover = Handover::where('application_id', $application->id)->firstOrFail();
        $response->assertRedirect(route('adopter.handover.status', $handover));
        $this->assertSame($adopter->id, $handover->user_id);
        $this->assertDatabaseHas('handover_notifications', [
            'handover_id' => $handover->id,
            'user_id' => $adopter->id,
            'title' => 'Your adoption has been approved',
        ]);

        $status = $this->actingAs($adopter)
            ->get(route('adopter.handover.status', $handover))
            ->assertOk()
            ->assertSee('Handover Status')
            ->assertSee('main-content-header', false)
            ->assertDontSee(route('adopter.handover.notifications', $handover));

        $this->assertSame(1, substr_count($status->getContent(), 'class="notification-bell'));

        $confirmation = $this->actingAs($adopter)
            ->get(route('adopter.confirm', $handover))
            ->assertOk()
            ->assertDontSee(route('adopter.handover.notifications', $handover));

        $this->assertSame(1, substr_count($confirmation->getContent(), 'class="notification-bell'));
    }

    public function test_new_handover_queues_one_preparation_email_even_if_requested_again(): void
    {
        Mail::fake();
        $adopter = $this->adopter('prepared-owner@example.test');
        $application = $this->approvedApplication($adopter);

        $handovers = app(HandoverService::class);
        $first = $handovers->forApprovedApplication($application);
        $second = $handovers->forApprovedApplication($application);

        $this->assertSame($first->id, $second->id);
        Mail::assertQueued(TransactionalMail::class, fn (TransactionalMail $mail): bool =>
            $mail->hasTo($adopter->email) && $mail->subjectLine === 'Your adoption has been approved');
        Mail::assertQueuedCount(1);
    }

    public function test_handover_pages_and_actions_are_limited_to_the_record_owner(): void
    {
        $owner = $this->adopter('owner@example.test');
        $otherAdopter = $this->adopter('other@example.test');
        $application = $this->approvedApplication($owner);
        $handover = $this->handoverFor($application);
        $notification = HandoverNotification::create([
            'handover_id' => $handover->id,
            'user_id' => $owner->id,
            'kind' => 'prepared',
            'title' => 'Private handover update',
            'body' => 'Only the adopter who owns this handover may view this update.',
            'channels' => ['In-app'],
            'read' => false,
        ]);

        $this->actingAs($otherAdopter)
            ->get(route('adopter.handover.status', $handover))
            ->assertForbidden();
        $this->actingAs($otherAdopter)
            ->get(route('adopter.confirm', $handover))
            ->assertForbidden();
        $this->actingAs($otherAdopter)
            ->post(route('adopter.confirm.submit', $handover), ['outcome' => 'received'])
            ->assertForbidden();
        $this->actingAs($otherAdopter)
            ->post(route('adopter.handover.notification.read', $notification))
            ->assertForbidden();
        $this->assertFalse($notification->fresh()->read);
    }

    public function test_confirmation_uses_the_current_post_adoption_logs_instead_of_the_removed_checkin_model(): void
    {
        Mail::fake();
        Storage::fake('local');
        $adopter = $this->adopter('owner@example.test');
        $application = $this->approvedApplication($adopter);
        $handover = $this->handoverFor($application);
        $handover->update(['released_at' => now()]);

        $this->assertSame(0, PostAdoptionLog::where('application_id', $application->id)->count());

        $this->actingAs($adopter)
            ->from(route('adopter.confirm', $handover))
            ->post(route('adopter.confirm.submit', $handover), [
                'outcome' => 'received', 'receipt_proof' => UploadedFile::fake()->image('receipt.jpg'),
            ])
            ->assertRedirect(route('adopter.confirm', $handover));

        $this->assertSame('received', $handover->fresh()->adopter_outcome);
        $this->assertSame(1, $adopter->inAppNotifications()->where('kind', 'completed')->count());
        $this->assertNotNull($handover->fresh()->received_at);
        Storage::disk('local')->assertExists($handover->fresh()->receipt_proof_path);
        $this->assertSame(
            hash('sha256', Storage::disk('local')->get($handover->fresh()->receipt_proof_path)),
            $handover->fresh()->receipt_proof_hash
        );
        $this->assertSame(1, AuditLog::where('entity_name', 'Handover')->where('entity_id', $handover->id)->where('action', 'handover.received')->count());
        $this->assertCount(3, PostAdoptionLog::where('application_id', $application->id)->get());
        Mail::assertQueued(TransactionalMail::class, fn (TransactionalMail $mail): bool =>
            $mail->hasTo($adopter->email) && str_starts_with($mail->subjectLine, 'Welcome home,'));
    }

    public function test_receipt_requires_release_and_a_valid_photo_and_can_only_be_confirmed_once(): void
    {
        Mail::fake();
        Storage::fake('local');
        $adopter = $this->adopter('receipt-owner@example.test');
        $handover = $this->handoverFor($this->approvedApplication($adopter));
        $url = route('adopter.confirm.submit', $handover);

        $this->actingAs($adopter)->post($url, [
            'outcome' => 'received', 'receipt_proof' => UploadedFile::fake()->image('early.jpg'),
        ])->assertSessionHasErrors('outcome');
        $this->assertNull($handover->fresh()->adopter_outcome);
        $this->assertSame([], Storage::disk('local')->allFiles());

        $handover->update(['released_at' => now()]);
        $this->post($url, ['outcome' => 'received'])->assertSessionHasErrors('receipt_proof');
        $this->post($url, ['outcome' => 'received', 'receipt_proof' => UploadedFile::fake()->create('not-image.php', 1, 'application/x-php')])
            ->assertSessionHasErrors('receipt_proof');
        $this->post($url, ['outcome' => 'received', 'receipt_proof' => UploadedFile::fake()->image('too-big.jpg')->size(5121)])
            ->assertSessionHasErrors('receipt_proof');
        $this->assertNull($handover->fresh()->adopter_outcome);

        $this->post($url, ['outcome' => 'received', 'receipt_proof' => UploadedFile::fake()->image('valid.png')])
            ->assertSessionHas('toast.type', 'success');
        $firstPath = $handover->fresh()->receipt_proof_path;
        $this->post($url, ['outcome' => 'received', 'receipt_proof' => UploadedFile::fake()->image('second.png')])
            ->assertSessionHasErrors('outcome');
        $this->assertSame($firstPath, $handover->fresh()->receipt_proof_path);
        $this->assertCount(1, Storage::disk('local')->allFiles());
        $this->assertSame(1, HandoverNotification::where('handover_id', $handover->id)->where('kind', 'completed')->count());
        $this->assertSame(1, AuditLog::where('entity_name', 'Handover')->where('entity_id', $handover->id)->where('action', 'handover.received')->count());
    }

    public function test_receipt_proof_is_private_to_the_owner_and_verified_staff(): void
    {
        Mail::fake();
        Storage::fake('local');
        $owner = $this->adopter('proof-owner@example.test');
        $other = $this->adopter('proof-other@example.test');
        $staff = $this->staff('proof-staff@example.test');
        $admin = User::create([
            'first_name' => 'Proof', 'last_name' => 'Admin', 'email' => 'proof-admin@example.test',
            'password' => bcrypt('password'), 'role' => Role::Administrator->value,
            'email_verified_at' => now(), 'is_active' => true,
        ]);
        $handover = $this->handoverFor($this->approvedApplication($owner));
        $handover->update(['released_at' => now()]);
        $this->post(route('adopter.confirm.submit', $handover), [
            'outcome' => 'received', 'receipt_proof' => UploadedFile::fake()->image('receipt.png'),
        ])->assertRedirect(); // Guest cannot submit.
        $this->assertNull($handover->fresh()->adopter_outcome);

        $this->actingAs($other)->post(route('adopter.confirm.submit', $handover), [
            'outcome' => 'received', 'receipt_proof' => UploadedFile::fake()->image('wrong-owner.png'),
        ])->assertForbidden();
        $this->assertNull($handover->fresh()->adopter_outcome);

        $this->actingAs($owner)->post(route('adopter.confirm.submit', $handover), [
            'outcome' => 'received', 'receipt_proof' => UploadedFile::fake()->image('receipt.png'),
        ])->assertSessionHas('toast.type', 'success');

        $proofUrl = route('handover.receipt-proof', $handover);
        $this->actingAs($owner)->get($proofUrl)->assertOk();
        $this->actingAs($other)->get($proofUrl)->assertForbidden();
        $this->actingAs($staff)->get($proofUrl)->assertOk();
        $this->actingAs($admin)->get($proofUrl)->assertOk();
        $this->actingAs($admin)->get(route('admin.handover.show', $handover))
            ->assertOk()->assertSee('View adopter Proof of Receipt photo');
    }

    public function test_staff_can_mark_handover_released_with_the_selected_active_staff_member(): void
    {
        Mail::fake();
        $adopter = $this->adopter('handover-owner@example.test');
        $application = $this->approvedApplication($adopter);
        $handover = $this->handoverFor($application);
        $volunteer = User::create([
            'first_name' => 'Assigned',
            'last_name' => 'Volunteer',
            'email' => 'assigned-volunteer@example.test',
            'password' => bcrypt('password'),
            'role' => Role::Volunteer->value,
            'email_verified_at' => now(),
            'is_active' => true,
        ]);

        $this->actingAs($volunteer)
            ->from(route('admin.handover.show', $handover))
            ->post(route('admin.handover.release', $handover), [
                'release_method' => 'pickup',
                'release_date' => today()->toDateString(),
                'release_time' => '10:30',
                'staff_id' => $volunteer->id,
            ])
            ->assertRedirect(route('admin.handover.show', $handover))
            ->assertSessionHas('toast.type', 'success');

        $this->assertSame($volunteer->full_name, $handover->refresh()->staff_name);
        $this->assertNotNull($handover->released_at);
        $this->assertDatabaseHas('handover_notifications', [
            'handover_id' => $handover->id,
            'user_id' => $adopter->id,
            'kind' => 'released',
        ]);
        Mail::assertQueued(TransactionalMail::class, fn (TransactionalMail $mail): bool =>
            $mail->hasTo($adopter->email) && str_contains($mail->subjectLine, 'has been released'));
    }

    public function test_release_rejects_a_non_staff_selection_without_changing_the_handover(): void
    {
        $adopter = $this->adopter('handover-owner@example.test');
        $application = $this->approvedApplication($adopter);
        $handover = $this->handoverFor($application);
        $staff = User::create([
            'first_name' => 'Handover',
            'last_name' => 'Staff',
            'email' => 'handover-staff@example.test',
            'password' => bcrypt('password'),
            'role' => Role::Volunteer->value,
            'email_verified_at' => now(),
            'is_active' => true,
        ]);

        $this->actingAs($staff)
            ->post(route('admin.handover.release', $handover), [
                'release_method' => 'pickup',
                'release_date' => today()->toDateString(),
                'release_time' => '10:30',
                'staff_id' => $adopter->id,
            ])
            ->assertSessionHasErrors('staff_id');

        $this->assertNull($handover->refresh()->released_at);
    }

    public function test_email_reminder_is_queued_once_and_unverified_adopters_are_not_told_it_was_sent(): void
    {
        Mail::fake();
        $adopter = $this->adopter('reminder-owner@example.test');
        $handover = $this->handoverFor($this->approvedApplication($adopter));
        $staff = $this->staff('reminder-staff@example.test');

        $this->actingAs($staff)
            ->post(route('admin.handover.reminder', $handover), ['channel' => 'Email'])
            ->assertSessionHas('toast.type', 'success');

        Mail::assertQueuedCount(1);
        Mail::assertQueued(TransactionalMail::class, fn (TransactionalMail $mail): bool =>
            $mail->hasTo($adopter->email) && str_starts_with($mail->subjectLine, 'Reminder:'));
        $this->assertSame(['In-app', 'Email'], $handover->notifications()->where('kind', 'reminder')->firstOrFail()->channels);

        $adopter->update(['email_verified_at' => null]);
        $this->post(route('admin.handover.reminder', $handover), ['channel' => 'Email'])
            ->assertSessionHas('toast.type', 'error');
        Mail::assertQueuedCount(1);
        $this->assertSame(1, $handover->notifications()->where('kind', 'reminder')->count());
    }

    public function test_reopened_handover_emails_adopter_and_failed_delivery_alerts_staff(): void
    {
        Mail::fake();
        config()->set('mail.staff_alert_address', null);
        $adopter = $this->adopter('reopen-owner@example.test');
        $handover = $this->handoverFor($this->approvedApplication($adopter));
        $staff = $this->staff('reopen-staff@example.test');
        $handover->update(['released_at' => now(), 'release_method' => 'delivery']);

        $this->actingAs($staff)->post(route('admin.handover.reopen', $handover), [
            'reason' => 'Delivery could not be completed.',
        ])->assertSessionHas('toast.type', 'success');
        Mail::assertQueued(TransactionalMail::class, fn (TransactionalMail $mail): bool =>
            $mail->hasTo($adopter->email) && str_starts_with($mail->subjectLine, 'A new handover'));

        Mail::fake();
        $this->actingAs($adopter)->post(route('adopter.confirm.submit', $handover), [
            'outcome' => 'not_received',
        ])->assertSessionHas('toast.type', 'warning');
        Mail::assertQueued(TransactionalMail::class, fn (TransactionalMail $mail): bool =>
            $mail->hasTo($staff->email) && str_starts_with($mail->subjectLine, 'Handover issue reported'));
    }

    private function staff(string $email): User
    {
        return User::create([
            'first_name' => 'Shelter', 'last_name' => 'Staff', 'email' => $email,
            'password' => bcrypt('password'), 'role' => Role::Volunteer->value,
            'email_verified_at' => now(), 'is_active' => true,
        ]);
    }

    private function adopter(string $email): User
    {
        return User::create([
            'first_name' => 'Adopter',
            'last_name' => 'User',
            'email' => $email,
            'password' => bcrypt('password'),
            'role' => Role::Adopter->value,
            'email_verified_at' => now(),
            'is_active' => true,
        ]);
    }

    private function approvedApplication(User $adopter): AdoptionApplication
    {
        $pet = Pet::create([
            'name' => 'Pet '.$adopter->id,
            'species' => 'Dog',
            'availability_status' => AvailabilityStatus::Adopted->value,
        ]);

        return AdoptionApplication::create([
            'user_id' => $adopter->id,
            'pet_id' => $pet->id,
            'applicant_first_name' => $adopter->first_name,
            'applicant_last_name' => $adopter->last_name,
            'applicant_email' => $adopter->email,
            'status' => ApplicationStatus::Approved->value,
            'adopted_at' => now(),
            'queue_closed_at' => now(),
        ]);
    }

    private function handoverFor(AdoptionApplication $application): Handover
    {
        return Handover::create([
            'code' => 'HV-'.str_pad((string) $application->id, 6, '0', STR_PAD_LEFT),
            'application_id' => $application->id,
            'pet_id' => $application->pet_id,
            'user_id' => $application->user_id,
            'adopter_name' => $application->first_name.' '.$application->last_name,
            'adopter_email' => $application->email,
            'approved_at' => $application->adopted_at,
            'history' => [],
            'reminders' => [],
        ]);
    }
}
