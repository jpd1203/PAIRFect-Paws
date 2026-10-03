<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use RuntimeException;

class AdminSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        if (app()->isProduction()) {
            throw new RuntimeException('The sample administrator cannot be seeded in production.');
        }

        $admin = \App\Models\User::firstOrCreate(
            ['email' => 'admin@pairfectpaws.com'],
            [
                'name' => 'System Administrator',
                'password' => 'password',
            ]
        );

        $adminRole = \App\Models\Role::where('name', 'Admin')->first();
        if ($adminRole && !$admin->roles()->where('role_id', $adminRole->id)->exists()) {
            $admin->roles()->attach($adminRole->id);
        }
    }
}
