<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class RetiredDonationModuleTest extends TestCase
{
    use RefreshDatabase;

    public function test_qr_donation_page_exists_without_payment_or_ledger_endpoints(): void
    {
        $this->assertTrue(Route::has('donate'));

        foreach (['donate.store', 'community-impact', 'admin.funds.index', 'admin.funds.store', 'admin.funds.update', 'admin.funds.destroy'] as $name) {
            $this->assertFalse(Route::has($name), "Route {$name} should be removed.");
        }

        $this->get('/donate')->assertOk()
            ->assertSee('Support Red Cubs Pet Patrol')
            ->assertSee('images/qr/gcash.png')
            ->assertSee('images/qr/maya.png')
            ->assertSee('images/qr/bpi.png')
            ->assertSee('images/qr/landbank.png');
        $this->post('/donate')->assertStatus(405);
        $this->get('/community-impact')->assertNotFound();
        $this->post('/api/donations')->assertNotFound();

        $admin = User::create([
            'first_name' => 'Admin',
            'last_name' => 'Tester',
            'email' => 'retired-funds-admin@example.test',
            'password' => 'password123',
            'role' => Role::Administrator->value,
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        $this->actingAs($admin)
            ->get('/admin/funds')
            ->assertNotFound();
    }

    public function test_landing_links_to_qr_page_and_admin_dashboard_has_no_fund_management(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('bg-cream-100')
            ->assertSee('href="'.route('donate').'"', false)
            ->assertDontSee('/community-impact')
            ->assertDontSee('TOTAL COMMUNITY DONATIONS');

        $admin = User::create([
            'first_name' => 'Admin',
            'last_name' => 'Tester',
            'email' => 'retired-funds-dashboard@example.test',
            'password' => 'password123',
            'role' => Role::Administrator->value,
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        $this->actingAs($admin)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertDontSee('Manage Funds');
    }
}
