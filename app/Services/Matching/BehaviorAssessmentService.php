<?php

namespace App\Services\Matching;

use App\Models\AssessmentRecord;
use App\Models\Pet;
use App\Models\User;
use App\Services\AuditLogService;
use Illuminate\Support\Facades\DB;

final class BehaviorAssessmentService
{
    public function __construct(
        private BehaviorScorer $scorer,
        private MatchingConfiguration $config,
        private ApplicationMatchService $matches,
    ) {}

    public function record(Pet $pet, User $staff, array $input): bool
    {
        return DB::transaction(function () use ($pet, $staff, $input) {
            $pet = Pet::whereKey($pet->id)->lockForUpdate()->firstOrFail();
            if ($pet->assessmentRecords()->where('assessor_id', $staff->id)->exists()) {
                return false;
            }

            $scores = $this->scorer->score($pet->species->value, $input['responses']);
            $record = new AssessmentRecord([
                'pet_id' => $pet->id, 'assessor_id' => $staff->id,
                'responses' => ['species' => strtolower($pet->species->value), 'answers' => $input['responses']],
                'scoring_version' => $this->config->version(),
            ]);
            foreach (['energy' => 'energy_level', 'trainability' => 'trainability', 'independence' => 'independence', 'temperament' => 'temperament'] as $feature => $column) {
                $record->{$column} = $scores[$feature] === null ? null : $scores[$feature] + 1.0;
            }
            $record->saveQuietly();
            $pet->fill(['last_assessed_at' => now(), 'last_assessed_by' => $staff->full_name])->saveQuietly();
            $this->matches->refreshPetSummary($pet);
            AuditLogService::log($staff->id, 'Pet Assessed', 'Pet', $pet->id, "Recorded assessment for {$pet->name}; one assessment per staff account is allowed for this pet.");

            return true;
        });
    }
}
