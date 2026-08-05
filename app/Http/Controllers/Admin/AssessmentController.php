<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Pet;
use App\Models\PetAssessment;
use App\Support\AssessmentQuestions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AssessmentController extends Controller
{
    /** Assessment Record — every pet, its assessment progress, and a Summary/Assess action. */
    public function record(Request $request)
    {
        $pets = Pet::with(['assessments' => fn ($q) => $q->latest()])
            ->orderByDesc('last_assessed_at')
            ->get();

        return view('admin.assessment.record', compact('pets'));
    }

    /** GET the dog or cat question form for this pet's NEXT assessment. */
    public function create(Pet $animal)
    {
        if ($animal->assessment_count >= PetAssessment::MAX_ASSESSMENTS_PER_PET) {
            return redirect()->route('admin.assessments.record')
                ->with('toast', ['type' => 'error', 'message' => "{$animal->name} has already reached the maximum of 3 assessments."]);
        }

        $categories = AssessmentQuestions::forSpecies($animal->species);
        $assessmentNumber = $animal->assessment_count + 1;

        return view('admin.assessment.form', compact('animal', 'categories', 'assessmentNumber'));
    }

    public function store(Request $request, Pet $animal)
    {
        if ($animal->assessment_count >= PetAssessment::MAX_ASSESSMENTS_PER_PET) {
            abort(422, 'Maximum assessments reached for this pet.');
        }

        $categories = AssessmentQuestions::forSpecies($animal->species);

        // Build validation rules dynamically: one 0-4 integer per question key
        $rules = [];
        foreach ($categories as $catKey => $cat) {
            foreach ($this->flattenQuestionKeys($catKey, $cat) as $key) {
                $rules[$key] = ['required', 'integer', 'between:0,4'];
            }
        }
        $validated = $request->validate($rules);

        // Roll each category's answers up into its average
        $averages = [];
        foreach ($categories as $catKey => $cat) {
            $keys = $this->flattenQuestionKeys($catKey, $cat);
            $values = array_map(fn ($k) => (int) $validated[$k], $keys);
            $averages[$catKey] = count($values) ? round(array_sum($values) / count($values), 2) : 0;
        }

        $assessment = PetAssessment::create([
            'pet_id' => $animal->id,
            'assessed_by_id' => Auth::id(),
            'assessed_by_name' => Auth::user()->full_name,
            'assessment_number' => $animal->assessment_count + 1,
            'species' => $animal->species,
            'energy_level_avg' => $averages['energy_level'] ?? 0,
            'trainability_avg' => $averages['trainability'] ?? 0,
            'independence_avg' => $averages['independence'] ?? 0,
            'temperament_avg' => $averages['temperament'] ?? 0,
            'answers' => $validated,
        ]);

        $animal->increment('assessment_count');
        $animal->update([
            'last_assessed_at' => now(),
            'last_assessed_by' => Auth::user()->full_name,
            // Convert the 0-4 behavioral averages onto the adopter-facing 1-5 matching scale
            'energy_level' => $this->toMatchScale($averages['energy_level'] ?? 0),
            'trainability' => $this->toMatchScale($averages['trainability'] ?? 0),
            'independence_level' => $this->toMatchScale(4 - ($averages['independence'] ?? 0)), // invert: low attachment = high independence
            'temperament' => $this->toMatchScale(4 - ($averages['temperament'] ?? 0)), // invert: low fearfulness = high temperament score
        ]);

        // Once fully assessed (3x), the pet is ready to be listed for adoption
        if ($animal->assessment_count >= PetAssessment::MAX_ASSESSMENTS_PER_PET && $animal->status === 'Assessing') {
            $animal->update(['status' => 'Available']);
        }

        AuditLog::record(Auth::user(), "completed assessment #{$assessment->assessment_number} for {$animal->name}");

        return redirect()->route('admin.assessments.record')
            ->with('toast', ['type' => 'success', 'message' => "Assessment #{$assessment->assessment_number} saved for {$animal->name}."]);
    }

    /** Assessment Summary modal content — averages ACROSS all of this pet's assessments. */
    public function summary(Pet $animal)
    {
        $assessments = $animal->assessments;

        $avg = fn (string $col) => $assessments->isEmpty() ? 0 : round($assessments->avg($col), 2);

        $summary = [
            'energy_level' => $avg('energy_level_avg'),
            'trainability' => $avg('trainability_avg'),
            'independence' => $avg('independence_avg'),
            'temperament' => $avg('temperament_avg'),
        ];

        return view('admin.assessment._summary-modal-content', compact('animal', 'summary'));
    }

    private function toMatchScale(float $avgOutOf4): int
    {
        // 0-4 -> 1-5, rounded
        return max(1, min(5, (int) round(($avgOutOf4 / 4) * 4) + 1));
    }

    private function flattenQuestionKeys(string $catKey, array $cat): array
    {
        if ($cat['type'] === 'flat') {
            return array_map(fn ($i) => "{$catKey}_{$i}", array_keys($cat['items']));
        }

        $keys = [];
        $colNum = 0;
        foreach ($cat['columns'] as $items) {
            foreach (array_keys($items) as $i) {
                $keys[] = "{$catKey}_{$colNum}_{$i}";
            }
            $colNum++;
        }
        return $keys;
    }
}
