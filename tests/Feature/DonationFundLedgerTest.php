<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\FundRecord;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DonationFundLedgerTest extends TestCase
{
    use RefreshDatabase;

    public function test_ledger_aggregates_donations_and_expenses_and_hides_private_records_publicly(): void
    {
        $admin = $this->user(Role::Administrator, 'ledger-admin@example.test');
        $generalDonation = FundRecord::create([
            'user_id' => $admin->id,
            'amount' => 1000.00,
            'transaction_type' => 'Donation',
            'source_or_destination' => 'General Donation',
            'description' => 'Unrestricted shelter support.',
            'is_public' => true,
        ]);
        $sponsorship = FundRecord::create([
            'user_id' => $admin->id,
            'amount' => 500.00,
            'transaction_type' => 'Donation',
            'source_or_destination' => 'Oreo Medical Sponsorship',
            'description' => 'Restricted sponsorship for Oreo.',
            'is_public' => true,
        ]);
        $expense = FundRecord::create([
            'user_id' => $admin->id,
            'amount' => 400.00,
            'transaction_type' => 'Expense',
            'source_or_destination' => 'Veterinary Clinic',
            'description' => 'Medical treatment expense.',
            'is_public' => true,
        ]);

        $this->assertDatabaseCount('fund_records', 3);
        $this->assertDatabaseHas('fund_records', [
            'id' => $sponsorship->id,
            'transaction_type' => 'Donation',
            'source_or_destination' => 'Oreo Medical Sponsorship',
            'is_public' => true,
        ]);

        $adminLedger = $this->actingAs($admin)
            ->get(route('admin.funds.index'))
            ->assertOk();
        $totalDonations = (float) $adminLedger->viewData('totalDonations');
        $totalSpent = (float) $adminLedger->viewData('totalSpent');

        $this->assertSame(1500.0, $totalDonations);
        $this->assertSame(400.0, $totalSpent);
        $this->assertSame(1100.0, (float) $adminLedger->viewData('availableFunds'));
        $adminLedger->assertSee('Available Funds')->assertSee('1,100.00');

        $sponsorship->update(['is_public' => false]);

        $publicLedger = $this->get(route('community-impact'))->assertOk();
        $publicRecords = $publicLedger->viewData('donations');

        $this->assertTrue($publicRecords->contains('id', $generalDonation->id));
        $this->assertTrue($publicRecords->contains('id', $expense->id));
        $this->assertFalse($publicRecords->contains('id', $sponsorship->id));
        $this->assertSame(1000.0, (float) $publicLedger->viewData('totalDonated'));
        $this->assertSame(400.0, (float) $publicLedger->viewData('totalSpent'));
    }

    public function test_administrator_can_update_visibility_amount_and_description_then_delete_a_record(): void
    {
        $admin = $this->user(Role::Administrator, 'fund-manager@example.test');
        $fund = FundRecord::create([
            'user_id' => $admin->id,
            'amount' => 250.00,
            'transaction_type' => 'Donation',
            'source_or_destination' => 'Initial Entry',
            'description' => 'Incorrect details.',
            'is_public' => true,
        ]);

        $this->actingAs($admin)
            ->patch(route('admin.funds.update', $fund), [
                'amount' => 275.50,
                'description' => 'Corrected sponsorship details.',
                'is_public' => false,
            ])
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success', 'Fund record updated.');

        $this->assertDatabaseHas('fund_records', [
            'id' => $fund->id,
            'amount' => 275.50,
            'description' => 'Corrected sponsorship details.',
            'is_public' => false,
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $admin->id,
            'action' => 'Fund Record Updated',
            'entity_name' => 'FundRecord',
            'entity_id' => $fund->id,
        ]);
        $this->assertFalse(
            $this->get(route('community-impact'))
                ->assertOk()
                ->viewData('donations')
                ->contains('id', $fund->id),
        );

        $this->actingAs($admin)
            ->delete(route('admin.funds.destroy', $fund))
            ->assertSessionHas('success', 'Fund record deleted.');

        $this->assertDatabaseMissing('fund_records', ['id' => $fund->id]);
        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $admin->id,
            'action' => 'Fund Record Deleted',
            'entity_name' => 'FundRecord',
            'entity_id' => $fund->id,
        ]);
    }

    public function test_adopter_cannot_view_create_update_or_delete_backend_fund_records(): void
    {
        $adopter = $this->user(Role::Adopter, 'ledger-adopter@example.test');
        $admin = $this->user(Role::Administrator, 'protected-fund-owner@example.test');
        $fund = FundRecord::create([
            'user_id' => $admin->id,
            'amount' => 300.00,
            'transaction_type' => 'Donation',
            'source_or_destination' => 'Protected Entry',
            'is_public' => true,
        ]);

        $this->actingAs($adopter)
            ->get(route('admin.funds.index'))
            ->assertRedirect(route('access-denied'));

        $this->actingAs($adopter)
            ->post(route('admin.funds.store'), [
                'recorded_date' => '2026-09-02',
                'entry_type' => 'donation',
                'activity' => 'Unauthorized Financial Entry',
                'amount' => 9999.99,
            ])
            ->assertRedirect(route('access-denied'));

        $this->assertDatabaseMissing('fund_records', [
            'source_or_destination' => 'Unauthorized Financial Entry',
        ]);

        $this->actingAs($adopter)
            ->patch(route('admin.funds.update', $fund), [
                'amount' => 1.00,
                'description' => 'Unauthorized edit.',
                'is_public' => false,
            ])
            ->assertRedirect(route('access-denied'));
        $this->actingAs($adopter)
            ->delete(route('admin.funds.destroy', $fund))
            ->assertRedirect(route('access-denied'));

        $this->assertDatabaseHas('fund_records', [
            'id' => $fund->id,
            'amount' => 300.00,
            'source_or_destination' => 'Protected Entry',
            'is_public' => true,
        ]);
    }

    public function test_volunteer_can_view_but_cannot_update_or_delete_fund_records(): void
    {
        $admin = $this->user(Role::Administrator, 'volunteer-protected-owner@example.test');
        $volunteer = $this->user(Role::Volunteer, 'ledger-volunteer@example.test');
        $fund = FundRecord::create([
            'user_id' => $admin->id,
            'amount' => 450.00,
            'transaction_type' => 'Donation',
            'source_or_destination' => 'Administrator Controlled Entry',
            'is_public' => true,
        ]);

        $this->actingAs($volunteer)
            ->get(route('admin.funds.index'))
            ->assertOk();
        $this->actingAs($volunteer)
            ->patch(route('admin.funds.update', $fund), ['amount' => 1.00])
            ->assertRedirect(route('access-denied'));
        $this->actingAs($volunteer)
            ->delete(route('admin.funds.destroy', $fund))
            ->assertRedirect(route('access-denied'));

        $this->assertDatabaseHas('fund_records', [
            'id' => $fund->id,
            'amount' => 450.00,
        ]);
    }

    private function user(Role $role, string $email): User
    {
        return User::create([
            'first_name' => 'Ledger',
            'last_name' => $role->value,
            'email' => $email,
            'password' => bcrypt('password'),
            'role' => $role->value,
            'email_verified_at' => now(),
            'is_active' => true,
        ]);
    }
}
