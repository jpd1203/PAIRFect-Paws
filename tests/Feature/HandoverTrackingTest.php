<?php

namespace Tests\Feature;

use App\Enums\ApplicationStatus;
use App\Enums\AvailabilityStatus;
use App\Enums\Role;
use App\Mail\TransactionalMail;
use App\Models\AdoptionApplication;
use App\Models\AuditLog;
use App\Models\Handover;
use App\Models\Pet;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class HandoverTrackingTest extends TestCase
{
    use RefreshDatabase;
    use \Tests\Concerns\PreparesVerifiedHandovers;

    #[DataProvider('invalidDeliveryData')]
    public function test_delivery_requires_courier_reference_and_a_valid_https_link(array $override, string $field): void
    {
        Mail::fake();
        [$owner, $staff, $handover] = $this->placement();

        $this->actingAs($staff)->post(route('admin.handover.release', $handover), $this->releaseData($staff, $override))
            ->assertSessionHasErrors($field);

        $this->assertNull($handover->fresh()->released_at);
        $this->assertNull($handover->fresh()->tracking_url);
        $this->assertDatabaseCount('handover_notifications', 0);
        Mail::assertNothingQueued();
    }

    public static function invalidDeliveryData(): array
    {
        return [
            'missing provider' => [['courier_provider' => null], 'courier_provider'],
            'other provider' => [['courier_provider' => 'other'], 'courier_provider'],
            'custom provider' => [['courier_provider' => 'custom_courier'], 'courier_provider'],
            'display name instead of key' => [['courier_provider' => 'Pet To Go Express'], 'courier_provider'],
            'provider array' => [['courier_provider' => ['pet_to_go_express']], 'courier_provider'],
            'manual name without provider' => [['courier_provider' => null, 'courier' => 'Pet To Go Express'], 'courier_provider'],
            'missing reference' => [['tracking_number' => null], 'tracking_number'],
            'missing link' => [['tracking_url' => null], 'tracking_url'],
            'empty link' => [['tracking_url' => '   '], 'tracking_url'],
            'malformed link' => [['tracking_url' => 'not a url'], 'tracking_url'],
            'missing host' => [['tracking_url' => 'https://'], 'tracking_url'],
            'javascript' => [['tracking_url' => 'javascript:alert(1)'], 'tracking_url'],
            'data' => [['tracking_url' => 'data:text/html,<script>alert(1)</script>'], 'tracking_url'],
            'file' => [['tracking_url' => 'file:///etc/passwd'], 'tracking_url'],
            'protocol relative' => [['tracking_url' => '//courier.example.test/track/1'], 'tracking_url'],
            'http' => [['tracking_url' => 'http://courier.example.test/track/1'], 'tracking_url'],
            'ftp' => [['tracking_url' => 'ftp://courier.example.test/track/1'], 'tracking_url'],
            'array input' => [['tracking_url' => ['https://courier.example.test/track/1']], 'tracking_url'],
            'too long' => [['tracking_url' => 'https://courier.example.test/track/'.str_repeat('x', 2048)], 'tracking_url'],
        ];
    }

    #[DataProvider('approvedProviders')]
    public function test_approved_provider_key_is_mapped_to_its_display_name(array $provider): void
    {
        Mail::fake();
        [$owner, $staff, $handover] = $this->placement();

        $this->actingAs($staff)->post(route('admin.handover.release', $handover), $this->releaseData($staff, [
            'courier_provider' => $provider['key'], 'courier' => 'Tampered browser name',
        ]))->assertSessionHas('toast.type', 'success');
        $this->assertSame($provider['label'], $handover->fresh()->courier);

        foreach (['adopter.handover.status', 'adopter.confirm'] as $route) {
            $this->actingAs($owner)->get(route($route, $handover))->assertOk()
                ->assertSee('Live tracking is provided by '.$provider['label'])
                ->assertDontSee('Tampered browser name');
        }
    }

    public static function approvedProviders(): array
    {
        return [
            'Pet To Go Express' => [['key' => 'pet_to_go_express', 'label' => 'Pet To Go Express']],
            'Xpress Pet Taxi' => [['key' => 'xpress_pet_taxi', 'label' => 'Xpress Pet Taxi']],
            'GrabPet Philippines' => [['key' => 'grabpet_philippines', 'label' => 'GrabPet Philippines']],
        ];
    }

    public function test_release_form_has_only_approved_provider_options_and_preserves_selection_after_validation_error(): void
    {
        [$owner, $staff, $handover] = $this->placement();
        $page = route('admin.handover.show', $handover);
        $this->actingAs($staff)->get($page)->assertOk()
            ->assertSee('name="courier_provider"', false)->assertDontSee('name="courier"', false)
            ->assertSee('value="pet_to_go_express"', false)
            ->assertSee('value="xpress_pet_taxi"', false)
            ->assertSee('value="grabpet_philippines"', false)
            ->assertDontSee('Other Courier')->assertDontSee('Custom Courier');

        $this->from($page)->post(route('admin.handover.release', $handover), $this->releaseData($staff, [
            'courier_provider' => 'xpress_pet_taxi', 'tracking_url' => 'http://courier.example.test/track/1',
        ]))->assertRedirect($page)->assertSessionHasErrors('tracking_url');
        $this->get($page)->assertOk()
            ->assertSee('value="xpress_pet_taxi" data-note="'.e(config('handover.delivery_providers.xpress_pet_taxi.note')).'" selected', false)
            ->assertSee('value="http://courier.example.test/track/1"', false);
    }

    public function test_delivery_stores_long_trimmed_link_and_preserves_release_guards_and_existing_proof_storage(): void
    {
        Mail::fake();
        Storage::fake('local');
        Storage::fake('public');
        [$owner, $staff, $handover] = $this->placement();
        $url = 'https://courier.example.test/track/1?token='.str_repeat('x', 600).'&booking=123';
        $data = $this->releaseData($staff, [
            'tracking_url' => '  '.$url.'  ',
            'courier' => 'An unapproved browser-supplied courier name',
            'proof' => UploadedFile::fake()->image('release.jpg'),
        ]);

        $this->actingAs($staff)->post(route('admin.handover.release', $handover), $data)
            ->assertSessionHas('toast.type', 'success');
        $handover->refresh();
        $this->assertSame($url, $handover->tracking_url);
        $this->assertSame('Pet To Go Express', $handover->courier);
        $this->assertArrayNotHasKey('tracking_url', $handover->toArray());
        $this->assertSame('PTG-239123', $handover->tracking_number);
        $this->assertNull($handover->adopter_outcome);
        $this->assertDatabaseCount('post_adoption_logs', 0);
        $this->assertNotNull($handover->proof_url);
        $this->assertCount(1, Storage::disk('public')->allFiles('proofs'));

        $notification = $handover->notifications()->where('kind', 'released')->firstOrFail();
        $this->assertSame(route('adopter.handover.status', $handover), $notification->action_url);
        $this->assertStringContainsString('Live courier tracking is available', $notification->body);
        $this->assertStringNotContainsString($url, $notification->toJson());
        $this->assertStringNotContainsString($url, json_encode($handover->history));
        $this->assertStringNotContainsString($url, AuditLog::where('entity_name', 'Handover')->where('entity_id', $handover->id)->get()->toJson());
        Mail::assertQueued(TransactionalMail::class, fn (TransactionalMail $mail): bool => $mail->hasTo($owner->email)
            && $mail->actionUrl === route('adopter.handover.status', $handover)
            && ! str_contains($mail->render(), $url));

        $this->post(route('admin.handover.release', $handover), $this->releaseData($staff, [
            'tracking_url' => 'https://courier.example.test/track/replay',
        ]))->assertSessionHasErrors('release_method');
        $this->assertSame($url, $handover->fresh()->tracking_url);
        $this->assertSame(1, $handover->notifications()->where('kind', 'released')->count());
        $this->assertCount(1, Storage::disk('public')->allFiles('proofs'));
        Mail::assertQueuedCount(1);

        foreach (['adopter.handover.status', 'adopter.confirm'] as $route) {
            $this->actingAs($owner)->get(route($route, $handover))->assertOk()
                ->assertSee('Track Live Delivery')->assertSee(e($url), false)
                ->assertSee('target="_blank" rel="noopener noreferrer"', false);
        }
        $this->assertNull($handover->fresh()->adopter_outcome);
        $this->assertDatabaseCount('post_adoption_logs', 0);

        $this->actingAs($owner)->post(route('adopter.confirm.submit', $handover), [
            'outcome' => 'received', 'receipt_proof' => UploadedFile::fake()->image('receipt.jpg'),
        ])->assertSessionHas('toast.type', 'success');
        $this->assertDatabaseCount('post_adoption_logs', 3);
        $this->assertSame('received', $handover->fresh()->adopter_outcome);
        foreach (['adopter.handover.status', 'adopter.confirm'] as $route) {
            $this->get(route($route, $handover))->assertOk()->assertDontSee('Track Live Delivery')->assertDontSee(e($url), false);
        }
    }

    #[DataProvider('pickupData')]
    public function test_pickup_ignores_tracking_input_and_clears_all_delivery_data(array $override): void
    {
        Mail::fake();
        [$owner, $staff, $handover] = $this->placement();
        $handover->update(['courier' => 'Old courier', 'tracking_number' => 'OLD', 'tracking_url' => 'https://courier.example.test/old']);
        $this->prepareHandover($handover, $staff, 'pickup');

        $this->actingAs($staff)->post(route('admin.handover.release', $handover), [
            'release_method' => 'pickup', 'release_date' => today()->toDateString(),
            'release_time' => '10:30', 'staff_id' => $staff->id, ...$override,
        ])->assertSessionHas('toast.type', 'success');

        $handover->refresh();
        $this->assertNull($handover->courier);
        $this->assertNull($handover->tracking_number);
        $this->assertNull($handover->tracking_url);
        $this->assertSame(route('adopter.confirm', $handover), $handover->notifications()->where('kind', 'released')->firstOrFail()->action_url);
    }

    public static function pickupData(): array
    {
        return [
            'no delivery fields' => [[]],
            'supplied delivery fields' => [[
                'courier' => 'Ignored courier', 'tracking_number' => 'IGNORED',
                'tracking_url' => 'https://courier.example.test/ignore',
            ]],
            'invalid tracking input ignored' => [['tracking_url' => 'javascript:alert(1)']],
            'invalid delivery fields ignored' => [['courier_provider' => 'unapproved', 'tracking_number' => ['invalid'], 'tracking_url' => ['invalid']]],
        ];
    }

    #[DataProvider('presentationStates')]
    public function test_tracking_visibility_matches_release_method_and_lifecycle(array $state, bool $visible): void
    {
        [$owner, $staff, $handover] = $this->placement();
        $handover->update([
            'release_method' => 'delivery', 'released_at' => now(),
            'adopter_confirmed_at' => now(), 'received_at' => now(),
            'tracking_url' => 'https://courier.example.test/track/1', ...$state,
        ]);
        foreach (['adopter.handover.status', 'adopter.confirm'] as $route) {
            $response = $this->actingAs($owner)->get(route($route, $handover))->assertOk();
            if ($visible) {
                $response->assertSee('Track Live Delivery');
            } else {
                $response->assertDontSee('Track Live Delivery')->assertDontSee('https://courier.example.test/track/1');
            }
        }
    }

    public static function presentationStates(): array
    {
        return [
            'released delivery' => [[], true],
            'legacy delivery without link' => [['tracking_url' => null], false],
            'pickup with stale link' => [['release_method' => 'pickup'], false],
            'not released' => [['released_at' => null], false],
            'received' => [['adopter_outcome' => 'received'], false],
        ];
    }

    public function test_only_owner_and_authorized_staff_can_access_tracking_link(): void
    {
        [$owner, $staff, $handover] = $this->placement();
        $url = 'https://courier.example.test/track/private?token=signed-token';
        $handover->update(['release_method' => 'delivery', 'released_at' => now(), 'tracking_url' => $url]);

        foreach (['adopter.handover.status', 'adopter.confirm'] as $route) {
            $this->get(route($route, $handover))->assertRedirect(route('login'))->assertDontSee(e($url), false);
        }
        $this->actingAs($staff)->get(route('admin.handover.show', $handover))->assertOk()
            ->assertSee('Open Live Tracking')->assertSee(e($url), false)
            ->assertSee('target="_blank" rel="noopener noreferrer"', false);

        $other = $this->user(Role::Adopter, 'other');
        foreach (['adopter.handover.status', 'adopter.confirm'] as $route) {
            $this->actingAs($other)->get(route($route, $handover))->assertForbidden()->assertDontSee(e($url), false);
        }
        $this->get(route('admin.handover.show', $handover))->assertRedirect(route('access-denied'))->assertDontSee(e($url), false);
        $this->actingAs($owner)->get(route('pets.show', $handover->pet))->assertOk()->assertDontSee(e($url), false);
    }

    public function test_reopen_clears_tracking_link_and_does_not_expose_it_until_new_release(): void
    {
        Mail::fake();
        [$owner, $staff, $handover] = $this->placement();
        $url = 'https://courier.example.test/track/old';
        $handover->update(['release_method' => 'delivery', 'released_at' => now(), 'tracking_url' => $url]);
        $this->actingAs($staff)->post(route('admin.handover.reopen', $handover), ['reason' => 'Arrange another transfer'])
            ->assertSessionHas('toast.type', 'success');
        $this->assertNull($handover->fresh()->tracking_url);
        $this->assertNull($handover->fresh()->released_at);
        $this->assertStringNotContainsString($url, json_encode($handover->fresh()->history));
        $this->actingAs($owner)->get(route('adopter.handover.status', $handover))->assertOk()->assertDontSee('Track Live Delivery');
        $this->prepareHandover($handover->refresh(), $staff);
        $this->actingAs($staff)->post(route('admin.handover.release', $handover), $this->releaseData($staff))
            ->assertSessionHas('toast.type', 'success');
        $this->assertSame('https://courier.example.test/track/1', $handover->fresh()->tracking_url);
    }

    public function test_completed_delivery_cannot_be_released_again_or_reopened(): void
    {
        Mail::fake();
        [$owner, $staff, $handover] = $this->placement();
        $url = 'https://courier.example.test/track/completed';
        $handover->update([
            'release_method' => 'delivery', 'released_at' => now(),
            'adopter_outcome' => 'received', 'received_at' => now(), 'tracking_url' => $url,
        ]);

        $this->actingAs($staff)->post(route('admin.handover.release', $handover), $this->releaseData($staff))
            ->assertSessionHasErrors('release_method');
        $this->post(route('admin.handover.reopen', $handover), ['reason' => 'Cannot reset a completed placement'])
            ->assertSessionHasErrors('reason');

        $this->assertSame('received', $handover->fresh()->adopter_outcome);
        $this->assertSame($url, $handover->fresh()->tracking_url);
        $this->assertDatabaseCount('handover_notifications', 0);
        Mail::assertNothingQueued();
    }

    private function user(Role $role, string $name): User
    {
        return User::create([
            'first_name' => $name, 'last_name' => 'Tracking Tester', 'email' => $name.'@example.test',
            'password' => 'password123', 'role' => $role->value, 'email_verified_at' => now(), 'is_active' => true,
        ]);
    }

    private function placement(): array
    {
        $owner = $this->user(Role::Adopter, 'owner');
        $staff = $this->user(Role::Volunteer, 'staff');
        $pet = Pet::create(['name' => 'Bruno', 'species' => 'Dog', 'availability_status' => AvailabilityStatus::Adopted->value]);
        $application = AdoptionApplication::create([
            'user_id' => $owner->id, 'pet_id' => $pet->id, 'status' => ApplicationStatus::Approved->value, 'adopted_at' => now(),
        ]);
        $handover = Handover::create([
            'code' => 'HV-TRACK-'.$application->id, 'application_id' => $application->id,
            'pet_id' => $pet->id, 'user_id' => $owner->id, 'adopter_name' => $owner->full_name,
            'approved_at' => now(), 'history' => [], 'reminders' => [],
        ]);

        $this->prepareHandover($handover, $staff);
        return [$owner, $staff, $handover];
    }

    private function releaseData(User $staff, array $override = []): array
    {
        return [
            'release_method' => 'delivery', 'release_date' => today()->toDateString(),
            'release_time' => '10:30', 'staff_id' => $staff->id, 'courier_provider' => 'pet_to_go_express',
            'tracking_number' => 'PTG-239123', 'tracking_url' => 'https://courier.example.test/track/1', ...$override,
        ];
    }
}
