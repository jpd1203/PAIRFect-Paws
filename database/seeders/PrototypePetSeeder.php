<?php

namespace Database\Seeders;

use App\Enums\Role;
use App\Models\Pet;
use App\Models\User;
use App\Services\Matching\AdopterMatchingProfileService;
use App\Services\Matching\BehaviorAssessmentService;
use App\Support\ApplicationOptions;
use App\Support\PrototypePetFixtures;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/** Opt-in fictional fixtures. No reset, history replacement or outbound messages. */
final class PrototypePetSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new RuntimeException('Prototype fixtures may only be seeded locally or in disposable tests.');
        }
        DB::transaction(function (): void {
            $observers = collect(range(1, 3))->map(fn ($i) => $this->account('observer-'.$i, Role::Volunteer));
            $adopter = $this->account('adopter', Role::Adopter);
            if (! $adopter->adopterProfile) {
                app(AdopterMatchingProfileService::class)->save($adopter, [
                    'bfi_responses' => array_fill_keys(array_keys(config('matching.bfi.prompts')), 3),
                    'housing_type' => 'Single Family Home (Fenced Yard)',
                    'monthly_income_range' => ApplicationOptions::INCOME_RANGES[3],
                    'has_children' => false, 'has_existing_pets' => false,
                ]);
            }
            foreach (PrototypePetFixtures::pets() as $key => $fixture) {
                $existing = Pet::withoutGlobalScopes()->where('name', $fixture['pet']['name'])->get();
                if ($existing->isNotEmpty()) {
                    if ($existing->count() !== 1 || (PrototypePetFixtures::identify($existing->first())['key'] ?? null) !== 'prototype:'.$key) {
                        throw new RuntimeException('Ambiguous or unrecognized prototype name; no fixtures were changed.');
                    }

                    // Never rewrite existing pets, profiles, assessments or lifecycle history.
                    continue;
                }
                $pet = Pet::create($fixture['pet']);
                foreach ($observers->take($fixture['observers']) as $observer) {
                    $answers = [];
                    $species = strtolower($pet->species->value);
                    foreach (config("matching.items.{$species}") as $group => $items) {
                        $value = in_array($group, ['energy', 'trainability', 'attachment', 'sociability', 'attention_seeking'], true) ? 2 : 1;
                        foreach (array_keys($items) as $item) {
                            $answers[$group][$item] = in_array("{$species}.{$group}.{$item}", config('matching.behavior_reverse'), true) ? 4 - $value : $value;
                        }
                    }
                    if (! app(BehaviorAssessmentService::class)->record($pet, $observer, ['responses' => $answers])) {
                        throw new RuntimeException('Could not record a distinct synthetic assessment.');
                    }
                }
            }
        });
    }

    private function account(string $key, Role $role): User
    {
        $email = "prototype-{$key}@example.test";
        $existing = User::where('email', $email)->first();
        if ($existing) {
            if ($existing->first_name !== 'Prototype Fictional' || $existing->last_name !== $key || $existing->role !== $role) {
                throw new RuntimeException('Unrecognized prototype account collision; refusing to overwrite it.');
            }

            return $existing;
        }

        return User::create(['email' => $email, 'first_name' => 'Prototype Fictional', 'last_name' => $key,
            'password' => 'PrototypeDemo2026!', 'role' => $role->value, 'is_active' => true, 'email_verified_at' => now()]);
    }
}
