<?php

namespace Tests\Feature;

use App\Models\AssessmentRecord;
use App\Services\Matching\MatchingProfileMapper;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\BuildsMatchingFixtures;
use Tests\TestCase;

class MatchingDataTest extends TestCase
{
    use BuildsMatchingFixtures, RefreshDatabase;

    public function test_matching_mapper_uses_raw_assessments_without_rounding_or_extra_queries(): void
    {
        $profile = $this->matchingAdopter();
        $pet = $this->matchingPet();
        $record = $pet->assessmentRecords->first();
        $responses = $record->responses;
        $responses['answers']['energy']['E1'] = 3;
        $record->update(['responses' => $responses]);
        $pet->load('assessmentRecords');
        $mapper = app(MatchingProfileMapper::class);
        DB::enableQueryLog();
        DB::flushQueryLog();
        $this->assertTrue($mapper->adopterIsComplete($profile));
        $this->assertEqualsWithDelta(3 + 1 / 9, $mapper->pet($pet)->features['energy'], 1e-12);
        $this->assertSame(3, $mapper->pet($pet)->observerCount);
        $this->assertSame(3.0, $mapper->adopter($profile)->personality['extraversion']);
        $this->assertCount(0, DB::getQueryLog());
        DB::disableQueryLog();
    }

    public function test_legacy_assessments_do_not_fabricate_a_new_behavior_profile(): void
    {
        $pet = $this->matchingPet([], 0);
        for ($i = 0; $i < 3; $i++) {
            AssessmentRecord::create([
                'pet_id' => $pet->id, 'assessor_id' => $this->matchingUser()->id,
                'energy_level' => 4.2, 'trainability' => 4.2, 'independence' => 4.2, 'temperament' => 4.2,
            ]);
        }
        $data = app(MatchingProfileMapper::class)->pet($pet->load('assessmentRecords'));
        $this->assertSame(0, $data->observerCount);
        $this->assertNull($data->features['energy']);
        $this->assertDatabaseCount('assessment_records', 3);
    }
}
