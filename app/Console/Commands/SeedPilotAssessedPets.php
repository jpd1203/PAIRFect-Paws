<?php

namespace App\Console\Commands;

use App\Enums\Role;
use App\Models\AssessmentRecord;
use App\Models\Pet;
use App\Models\User;
use Database\Seeders\FreshStartPetsSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

final class SeedPilotAssessedPets extends Command
{
    private const MARKER = '[PAIRFECT-PILOT-ASSESSED-15-V1]';

    protected $signature = 'pilot:seed-assessed-pets {--expect-pets=6 : Existing pet count required before insertion} {--confirm-pilot : Confirm this is a synthetic-data pilot}';

    protected $description = 'Add 15 labeled synthetic pets and 3 assessments each to a pilot database without changing existing pets';

    public function handle(): int
    {
        if (! $this->option('confirm-pilot') || ! app()->isProduction() || config('app.debug')) {
            $this->error('This command requires --confirm-pilot, APP_ENV=production, and APP_DEBUG=false.');

            return self::FAILURE;
        }

        $existingPilotPets = Pet::withoutGlobalScope('notArchived')
            ->where('behavioral_notes', 'like', '%'.self::MARKER.'%')
            ->get();

        if ($existingPilotPets->isNotEmpty()) {
            $complete = $existingPilotPets->count() === 15
                && AssessmentRecord::whereIn('pet_id', $existingPilotPets->modelKeys())->count() === 45
                && $existingPilotPets->every(fn (Pet $pet): bool => $pet->assessment_count === 3);

            if ($complete) {
                $this->info('The 15 pilot pets and 45 assessments already exist; nothing was changed.');

                return self::SUCCESS;
            }

            $this->error('A partial pilot batch exists. No records were changed; inspect it manually.');

            return self::FAILURE;
        }

        $expected = filter_var($this->option('expect-pets'), FILTER_VALIDATE_INT);
        $current = Pet::withoutGlobalScope('notArchived')->count();
        if ($expected === false || $expected < 0 || $current !== $expected) {
            $this->error("Expected {$this->option('expect-pets')} existing pets, found {$current}. No records were changed.");

            return self::FAILURE;
        }

        foreach (range(1, 3) as $number) {
            if (User::where('email', $this->assessorEmail($number))->exists()) {
                $this->error('A pilot assessor account already exists without a complete pet batch. No records were changed.');

                return self::FAILURE;
            }
        }

        try {
            DB::transaction(function (): void {
                $assessors = collect();
                foreach (range(1, 3) as $number) {
                    $assessors->push(User::create([
                        'first_name' => 'Demo',
                        'last_name' => "Assessor {$number}",
                        'email' => $this->assessorEmail($number),
                        'password' => Str::random(64),
                        'role' => Role::Volunteer->value,
                        'is_active' => false,
                    ]));
                }

                FreshStartPetsSeeder::seedWithAssessors($assessors, 'Demo ', self::MARKER);
            });
        } catch (Throwable $exception) {
            $this->error('Pilot import failed and was rolled back: '.$exception->getMessage());

            return self::FAILURE;
        }

        $this->info('Added 15 synthetic pilot pets and 45 assessments; existing pets were preserved.');

        return self::SUCCESS;
    }

    private function assessorEmail(int $number): string
    {
        return "pilot-assessor-{$number}@example.invalid";
    }
}
