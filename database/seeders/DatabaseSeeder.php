<?php

namespace Database\Seeders;

use App\Enums\Role;
use App\Models\Branch;
use App\Models\Pet;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // ─── Branches ─────────────────────────────────────────────────────────

        $branchMain = Branch::create([
            'name' => 'Main Shelter',
            'address' => '123 Shelter Street, Quezon City',
            'contact_number' => '(02) 8123-4567',
        ]);

        $branchNorth = Branch::create([
            'name' => 'North Branch',
            'address' => '456 North Ave, Caloocan City',
            'contact_number' => '(02) 8765-4321',
        ]);

        $branchSouth = Branch::create([
            'name' => 'South Branch',
            'address' => '789 South Blvd, Paranaque City',
            'contact_number' => '(02) 8234-5678',
        ]);

        // ─── Staff Accounts ───────────────────────────────────────────────────

        User::create([
            'first_name' => 'Admin',
            'last_name' => 'User',
            'email' => 'admin@pairfectpaws.com',
            'email_verified_at' => now(),
            'password' => Hash::make('password'),
            'role' => Role::Administrator->value,
            'is_active' => true,
            'branch_id' => $branchMain->id,
        ]);

        User::create([
            'first_name' => 'Volunteer',
            'last_name' => 'Staff',
            'email' => 'volunteer@pairfectpaws.com',
            'email_verified_at' => now(),
            'password' => Hash::make('password'),
            'role' => Role::Volunteer->value,
            'is_active' => true,
            'branch_id' => $branchNorth->id,
        ]);

        // ─── Sample Adopter ───────────────────────────────────────────────────

        User::create([
            'first_name' => 'Jane',
            'last_name' => 'Doe',
            'email' => 'adopter@example.com',
            'password' => Hash::make('password'),
            'role' => Role::Adopter->value,
            'is_active' => true,
        ]);

        // ─── Sample Pets ──────────────────────────────────────────────────────

        $pets = [
            ['name' => 'Luna',    'species' => 'Cat', 'breed' => 'Siamese',        'age' => 2, 'sex' => 'Female', 'health_status' => 'Excellent', 'branch_id' => $branchMain->id],
            ['name' => 'Buddy',   'species' => 'Dog', 'breed' => 'Golden Retriever', 'age' => 3, 'sex' => 'Male',   'health_status' => 'Good',      'branch_id' => $branchMain->id],
            ['name' => 'Mochi',   'species' => 'Cat', 'breed' => 'Persian',        'age' => 1, 'sex' => 'Female', 'health_status' => 'Good',      'branch_id' => $branchNorth->id],
            ['name' => 'Max',     'species' => 'Dog', 'breed' => 'Labrador',       'age' => 4, 'sex' => 'Male',   'health_status' => 'Good',      'branch_id' => $branchNorth->id],
            ['name' => 'Coco',    'species' => 'Dog', 'breed' => 'Shih Tzu',       'age' => 2, 'sex' => 'Female', 'health_status' => 'Excellent', 'branch_id' => $branchSouth->id],
            ['name' => 'Oliver',  'species' => 'Cat', 'breed' => 'Tabby',          'age' => 3, 'sex' => 'Male',   'health_status' => 'Good',      'branch_id' => $branchSouth->id],
        ];

        foreach ($pets as $petData) {
            Pet::create(array_merge($petData, [
                'availability_status' => 'Available',
                'intake_date' => now()->subDays(rand(1, 90))->toDateString(),
                'is_archived' => false,
                'version' => 1,
            ]));
        }
    }
}
