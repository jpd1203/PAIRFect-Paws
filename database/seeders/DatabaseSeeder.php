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
            'email_verified_at' => now(),
            'password' => Hash::make('password'),
            'role' => Role::Adopter->value,
            'is_active' => true,
        ]);

        // ─── Sample Pets ──────────────────────────────────────────────────────

        $pets = [
            ['name' => 'Luna',    'species' => 'Cat', 'breed' => 'Siamese',        'age' => 2, 'sex' => 'Female', 'health_status' => 'Excellent', 'branch_id' => $branchMain->id, 'description' => 'Luna is an elegant and curious Siamese cat who loves conversing with her humans and curling up in sunny spots.'],
            ['name' => 'Buddy',   'species' => 'Dog', 'breed' => 'Golden Retriever', 'age' => 3, 'sex' => 'Male',   'health_status' => 'Good',      'branch_id' => $branchMain->id, 'description' => 'Buddy is a happy-go-lucky Golden Retriever who enjoys playing fetch, swimming, and making friends with everyone he meets.'],
            ['name' => 'Mochi',   'species' => 'Cat', 'breed' => 'Persian',        'age' => 1, 'sex' => 'Female', 'health_status' => 'Good',      'branch_id' => $branchNorth->id, 'description' => 'Mochi is a sweet and gentle Persian kitten with a calm demeanor who enjoys feather toys and cozy laps.'],
            ['name' => 'Max',     'species' => 'Dog', 'breed' => 'Labrador',       'age' => 4, 'sex' => 'Male',   'health_status' => 'Good',      'branch_id' => $branchNorth->id, 'description' => 'Max is a loyal, well-trained Labrador who loves outdoor adventures and has a wonderfully gentle temperament.'],
            ['name' => 'Coco',    'species' => 'Dog', 'breed' => 'Shih Tzu',       'age' => 2, 'sex' => 'Female', 'health_status' => 'Excellent', 'branch_id' => $branchSouth->id, 'description' => 'Coco is a playful, cheerful Shih Tzu who loves being pampered, going on leisurely walks, and greeting new guests.'],
            ['name' => 'Oliver',  'species' => 'Cat', 'breed' => 'Tabby',          'age' => 3, 'sex' => 'Male',   'health_status' => 'Good',      'branch_id' => $branchSouth->id, 'description' => 'Oliver is an adventurous and affectionate Tabby cat who loves climbing cat trees and purring happily when brushed.'],
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
