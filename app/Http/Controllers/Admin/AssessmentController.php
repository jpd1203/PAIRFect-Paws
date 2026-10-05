<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StorePetAssessmentRequest;
use App\Models\Pet;
use App\Services\Matching\BehaviorAssessmentService;
use App\Services\Matching\MatchingConfiguration;
use App\Services\Matching\MatchingProfileMapper;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class AssessmentController extends Controller
{
    public function index(Request $request, MatchingProfileMapper $mapper)
    {
        $pets = Pet::with('assessmentRecords')->orderByDesc('last_assessed_at')->orderByDesc('created_at')->orderByDesc('id')->get();
        foreach ($pets as $pet) {
            $summary = $mapper->aggregate($pet);
            $pet->setAttribute('matching_observer_count', $summary['observer_count']);
            $pet->setAttribute('matching_behavior_complete', $summary['complete']);
            $pet->setAttribute('assessment_records_count', $summary['observer_count']);
            $pet->setAttribute('assessed_by_current_user', $pet->assessmentRecords->contains(
                fn ($record) => $record->assessor_id === $request->user()->id
            ));
        }

        return view('admin.assessment.record', compact('pets'));
    }

    public function create(Request $request, Pet $pet, MatchingProfileMapper $mapper)
    {
        if ($pet->assessmentRecords()->where('assessor_id', $request->user()->id)->exists()) {
            return redirect()->route('admin.assessments.record')
                ->withErrors(['assessment' => "You have already assessed {$pet->name}. Each staff account can assess a pet only once."]);
        }

        $pet->load('assessmentRecords');
        $groups = config('matching.items.'.strtolower($pet->species->value));
        $categories = collect($groups)->mapWithKeys(fn (array $items, string $key) => [
            $key => ['type' => 'flat', 'label' => Str::headline($key), 'items' => $items],
        ])->all();
        $assessmentNumber = min(config('matching.min_observers'), $mapper->aggregate($pet)['observer_count'] + 1);

        return view('admin.assessment.form', [
            'animal' => $pet, 'categories' => $categories, 'assessmentNumber' => $assessmentNumber,
        ]);
    }

    public function store(StorePetAssessmentRequest $request, Pet $pet, BehaviorAssessmentService $assessments)
    {
        if (! $assessments->record($pet, $request->user(), $request->validated())) {
            $message = "You have already assessed {$pet->name}, or this pet already has enough distinct observers.";

            return $request->expectsJson()
                ? response()->json(['message' => $message], 409)
                : redirect()->route('admin.assessments.record')->withErrors(['assessment' => $message]);
        }

        return redirect()->route('admin.assessments.record')->with('success', 'Assessment saved. Each pet requires observations from three distinct staff accounts.');
    }

    public function summary(Pet $pet, MatchingProfileMapper $mapper)
    {
        $pet->load('assessmentRecords');

        $missingInformation = [];
        if (! array_key_exists((string) $pet->physical_size, config('matching.size_levels'))) {
            $missingInformation[] = 'veterinary physical size';
        }
        if (! MatchingConfiguration::validValue($pet->medical_needs)) {
            $missingInformation[] = 'veterinary medical-needs level';
        }
        if (! in_array($pet->life_stage, ['young', 'adult', 'senior'], true)) {
            $missingInformation[] = 'verified life stage';
        }
        if ($pet->has_aggression_history === null || $pet->aggression_history_verified_at === null) {
            $missingInformation[] = 'verified aggression history';
        }
        if ($pet->high_vocalization === null) {
            $missingInformation[] = 'verified vocalization status';
        }

        return view('admin.assessment._summary-modal-content', [
            'animal' => $pet, 'summary' => $mapper->aggregate($pet),
            'missingInformation' => $missingInformation,
        ]);
    }
}
