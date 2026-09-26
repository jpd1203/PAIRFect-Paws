<?php

namespace Tests\Feature;

use App\Enums\ApplicationStatus;
use App\Enums\AvailabilityStatus;
use App\Enums\Role;
use App\Models\AdoptionApplication;
use App\Models\Handover;
use App\Models\HandoverNotification;
use App\Models\Pet;
use App\Models\PostAdoptionLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HandoverWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_adopter_handover_status_backfills_their_approved_application_and_shows_the_status_view(): void
    {
        $adopter = $this->adopter('owner@example.test');
        $application = $this->approvedApplication($adopter);

        $response = $this->actingAs($adopter)->get(route('adopter.handover.my'));

        $handover = Handover::where('application_id', $application->id)->firstOrFail();
        $response->assertRedirect(route('adopter.handover.status', $handover));
        $this->assertSame($adopter->id, $handover->user_id);
        $this->assertDatabaseHas('handover_notifications', [
            'handover_id' => $handover->id,
            'user_id' => $adopter->id,
            'title' => 'Your adoption has been approved',
        ]);

        $this->actingAs($adopter)
            ->get(route('adopter.handover.status', $handover))
            ->assertOk()
            ->assertSee('Handover Status');
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
        $adopter = $this->adopter('owner@example.test');
        $application = $this->approvedApplication($adopter);
        $handover = $this->handoverFor($application);

        $this->assertSame(0, PostAdoptionLog::where('application_id', $application->id)->count());

        $this->actingAs($adopter)
            ->from(route('adopter.confirm', $handover))
            ->post(route('adopter.confirm.submit', $handover), ['outcome' => 'received'])
            ->assertRedirect(route('adopter.confirm', $handover));

        $this->assertSame('received', $handover->fresh()->adopter_outcome);
        $this->assertCount(3, PostAdoptionLog::where('application_id', $application->id)->get());
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
