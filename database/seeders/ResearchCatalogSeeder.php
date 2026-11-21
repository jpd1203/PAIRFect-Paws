<?php

namespace Database\Seeders;

use App\Enums\Role;
use App\Models\Pet;
use App\Models\User;
use App\Services\Matching\BehaviorAssessmentService;
use App\Support\ResearchMode;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

final class ResearchCatalogSeeder extends Seeder
{
    public function run(): void
    {
        if (! ResearchMode::ready()) {
            throw new RuntimeException('Research mode requires a dedicated pairfect_research_* database.');
        }
        DB::transaction(function () {
            if (Pet::withoutGlobalScopes()->exists() || User::query()->exists()) {
                throw new RuntimeException('Initialize only a fresh research database before creating accounts. Nothing changed.');
            }
            $observers = collect(range(1, 3))->map(fn ($n) => User::create([
                'first_name' => 'Fictional Observer', 'last_name' => (string) $n, 'email' => "observer-{$n}@example.invalid",
                'role' => Role::Volunteer, 'password' => Str::random(64), 'is_active' => false,
            ]));
            $names = explode(' ', 'Amihan Bayani Cora Dalisay Ember Fenn Ginto Habi Isla Juno Kiko Lira Mako Nilo Ora Pippa Quinn Rosa Sago Tala Ube Vera Wren Xavi Yumi Zora Aster Bituin Clover Dune Echo Flora Gale Hazel Indigo Jasper Kiwi Lumen Mango Nova Olive Pearl Quill River Sage Tofu Uma Vito Willow Zuri');
            foreach ($names as $i => $name) {
                $dog = $i < 25;
                $species = $dog ? 'Dog' : 'Cat';
                $idx = $i % 25;
                $age = [7, 18, 36, 66, 132][intdiv($idx, 5)];
                $weight = ($dog ? [4, 7, 12, 20, 32] : [2.8, 3.5, 4.5, 5.5, 6.7])[$idx % 5];
                $needs = $age >= 120 ? 3 : ($idx % 3 === 0 ? 2 : 1);
                $energy = $age >= 120 ? 1 : (1 + $idx % 3);
                $pet = Pet::create(['name' => $name, 'species' => $species, 'breed' => $dog ? 'Mixed-breed dog' : 'Domestic mixed-breed cat',
                    'age' => $age, 'weight_kg' => $weight, 'sex' => $i % 2 ? 'Female' : 'Male', 'availability_status' => 'Available',
                    'description' => 'Fictional research scenario; not an animal offered for actual adoption.',
                    'health_status' => [1 => 'Healthy', 2 => 'Stable; routine skin care', 3 => 'Stable; ongoing joint support'][$needs],
                    'vaccination_record_status' => $idx % 6 === 0 ? 'Incomplete' : 'Complete', 'medical_needs' => $needs,
                    'behavioral_notes' => ['', 'Generally low activity', 'Moderately active', 'Frequently active'][$energy],
                    'high_vocalization' => false, 'has_aggression_history' => false]);
                DB::table('research_catalog')->insert(['pet_id' => $pet->id]);
                $s = strtolower($species);
                foreach ($observers as $n => $observer) {
                    $answers = [];
                    foreach (config("matching.items.{$s}") as $group => $items) {
                        $value = match ($group) {
                            'energy' => $energy,
                            'stranger_fear','non_social_fear','temperament' => ($idx + $n) % 3,
                            default => ($idx + $n + strlen($group)) % 4,
                        };
                        foreach (array_keys($items) as $item) {
                            $answers[$group][$item] = in_array("{$s}.{$group}.{$item}", config('matching.behavior_reverse'), true) ? 4 - $value : $value;
                        }
                    }
                    if (! app(BehaviorAssessmentService::class)->record($pet, $observer, ['responses' => $answers])) {
                        throw new RuntimeException('Incomplete fictional assessment; rolling back catalog.');
                    }
                }
            }
        });
    }
}
