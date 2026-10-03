<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccountProfileUpdateTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_name_is_persisted_and_visible_after_refresh(): void
    {
        $admin = $this->user(Role::Administrator, 'Admin', 'Original');

        $this->actingAs($admin)->patch(route('account.profile.update'), [
            'full_name' => 'Updated Administrator',
        ])->assertRedirect(route('account.settings'))->assertSessionHas('success', 'Profile updated.');

        $this->assertDatabaseHas('users', [
            'id' => $admin->id,
            'first_name' => 'Updated',
            'last_name' => 'Administrator',
        ]);
        $this->get(route('account.settings'))->assertOk()->assertSee('value="Updated Administrator"', false);
    }

    public function test_adopter_phone_and_structured_address_are_persisted(): void
    {
        $adopter = $this->user(Role::Adopter, 'Adopter', 'Original');

        $this->actingAs($adopter)->patch(route('account.profile.update'), [
            'full_name' => 'Adopter Original',
            'phone_number' => '+63 917 123 4567',
            ...$this->address(),
        ])->assertRedirect(route('account.settings'))->assertSessionHas('success');

        $this->assertDatabaseHas('users', [
            'id' => $adopter->id,
            'phone_number' => '+63 917 123 4567',
            'region' => 'National Capital Region (NCR)',
            'street_address' => '4489 V. Francisco St. Sta. Mesa',
            'zip_code' => '1016',
        ]);
        $this->get(route('account.settings'))
            ->assertOk()
            ->assertSee('value="+63 917 123 4567"', false)
            ->assertSee('4489 V. Francisco St. Sta. Mesa');
    }

    public function test_invalid_profile_input_cannot_update_data_or_flash_success(): void
    {
        $adopter = $this->user(Role::Adopter, 'Adopter', 'Original');

        $this->actingAs($adopter)->from(route('account.settings'))
            ->patch(route('account.profile.update'), [
                'full_name' => 'Adopter Changed',
                'phone_number' => '<script>',
                ...$this->address(),
                'barangay_code' => 'invalid',
            ])
            ->assertRedirect(route('account.settings'))
            ->assertSessionHasErrors(['phone_number'])
            ->assertSessionMissing('success');

        $this->assertDatabaseHas('users', [
            'id' => $adopter->id,
            'first_name' => 'Adopter',
            'last_name' => 'Original',
            'phone_number' => null,
        ]);

        $this->actingAs($adopter)->from(route('account.settings'))
            ->patch(route('account.profile.update'), [
                'full_name' => 'Adopter Changed',
                'phone_number' => '09171234567',
                ...$this->address(),
                'barangay_code' => 'invalid',
            ])
            ->assertSessionHasErrors(['barangay_code'])
            ->assertSessionMissing('success');
    }

    public function test_profile_update_targets_only_the_authenticated_user(): void
    {
        $owner = $this->user(Role::Administrator, 'Owner', 'Original');
        $other = $this->user(Role::Administrator, 'Other', 'Original');

        $this->actingAs($owner)->patch(route('account.profile.update'), [
            'full_name' => 'Owner Changed',
            'user_id' => $other->id,
            'role' => Role::Adopter->value,
            'email' => 'hijacked@example.test',
        ])->assertSessionHas('success');

        $this->assertDatabaseHas('users', [
            'id' => $other->id,
            'first_name' => 'Other',
            'last_name' => 'Original',
            'email' => $other->email,
            'role' => Role::Administrator->value,
        ]);
        $this->assertDatabaseHas('users', [
            'id' => $owner->id,
            'first_name' => 'Owner',
            'last_name' => 'Changed',
            'role' => Role::Administrator->value,
        ]);
    }

    private function user(Role $role, string $firstName, string $lastName): User
    {
        return User::create([
            'first_name' => $firstName,
            'last_name' => $lastName,
            'email' => strtolower($firstName).'-'.strtolower($lastName).'-'.uniqid().'@example.test',
            'password' => 'Password123!',
            'role' => $role->value,
            'email_verified_at' => now(),
            'is_active' => true,
        ]);
    }

    private function address(): array
    {
        return [
            'region_code' => '1300000000',
            'province_code' => '__direct__',
            'city_municipality_code' => '1380600000',
            'barangay_code' => '1380606197',
            'street_address' => '4489 V. Francisco St. Sta. Mesa',
            'zip_code' => '1016',
        ];
    }
}
