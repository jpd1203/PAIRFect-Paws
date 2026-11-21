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
            foreach (ResearchPets::profiles() as $fixture) {
                $pet = Pet::create($fixture['pet']);
                DB::table('research_catalog')->insert(['pet_id' => $pet->id]);
                $s = strtolower($pet->species->value);
                foreach ($observers as $n => $observer) {
                    $answers = [];
                    foreach (config("matching.items.{$s}") as $group => $items) {
                        // Keep variation within the intended trait, including reverse-coded items.
                        $value = $fixture['scores'][$group];
                        if ($group === 'trainability' && $n !== 1 && $value > 1 && $value < 4) {
                            $value += $n - 1;
                        }
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
