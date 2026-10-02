<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\AssessmentRecord;
use App\Models\Pet;
use App\Models\User;
use Database\Seeders\FreshStartPetsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FreshStartPetsSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_seeds_fifteen_available_demo_pets_with_three_distinct_complete_assessments_each(): void
    {
        foreach (range(1, 3) as $index) {
            User::create([
                'first_name' => "Staff {$index}",
                'last_name' => 'Tester',
                'email' => "fresh-start-staff-{$index}@example.test",
                'password' => 'password',
                'role' => $index === 1 ? Role::Administrator->value : Role::Volunteer->value,
                'is_active' => true,
            ]);
        }

        $this->seed(FreshStartPetsSeeder::class);

        $this->assertSame(15, Pet::count());
        $this->assertSame(45, AssessmentRecord::count());
        $this->assertSame(15, Pet::recommendationEligible()->count());
        $this->assertSame(0, Pet::where('assessment_count', '!=', 3)->count());
        $this->assertSame(0, AssessmentRecord::whereNull('responses')->count());
        $this->assertSame(15, AssessmentRecord::select('pet_id')->groupBy('pet_id')->havingRaw('COUNT(DISTINCT assessor_id) = 3')->get()->count());
    }
}
