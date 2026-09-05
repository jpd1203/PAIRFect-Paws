<?php

namespace App\Http\Controllers;

use App\Services\KnnRecommendationService;
use App\Support\ApplicationOptions;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class RecommendationController extends Controller
{
    public function __construct(private KnnRecommendationService $knn) {}

    /**
     * GET /recommendation
     * Show the intake form.
     */
    public function intake()
    {
        return view('recommendation.intake', [
            'options' => ApplicationOptions::class,
            'profile' => auth()->check() ? auth()->user()->adopterProfile : null,
        ]);
    }

    /**
     * POST /recommendation/start
     * Validate inputs, run KNN, return results view.
     */
    public function start(Request $request)
    {
        $validated = $request->validate([
            'physical_activity_level' => ['required', 'string', Rule::in(ApplicationOptions::PHYSICAL_ACTIVITY_LEVELS)],
            'time_availability' => ['required', 'string', Rule::in(ApplicationOptions::TIME_AVAILABILITY_OPTIONS)],
            'prior_pet_experience' => ['required', 'string', Rule::in(ApplicationOptions::PRIOR_EXPERIENCE_OPTIONS)],
            'housing_type' => ['required', 'string', Rule::in(ApplicationOptions::HOUSING_TYPES)],
            'household_composition' => ['required', 'string', Rule::in(ApplicationOptions::HOUSEHOLD_COMPOSITIONS)],
            'monthly_income_range' => ['required', 'string', Rule::in(ApplicationOptions::INCOME_RANGES)],
        ]);

        if ($request->user()?->isAdopter()) {
            $request->user()->adopterProfile()->updateOrCreate([], [
                'physical_activity_level' => $validated['physical_activity_level'],
                'time_availability' => $validated['time_availability'],
                'prior_pet_experience' => $validated['prior_pet_experience'],
                'housing_type' => $validated['housing_type'],
                'household_composition' => $validated['household_composition'],
                'monthly_income_range' => $validated['monthly_income_range'],
            ]);
        }

        // Derive has_existing_pets from prior_pet_experience — no extra form field needed
        $validated['has_existing_pets'] = in_array($validated['prior_pet_experience'], [
            'Currently own pets',
            'Experienced with rescue/special needs animals',
        ]) ? 'yes' : 'no';

        $matches = $this->knn->run($validated);

        // Default slider values — reflect the adopter's encoded profile
        $sliders = [
            'energy' => $this->encodeActivity($validated['physical_activity_level']),
            'independence' => $this->encodeTime($validated['time_availability']),
            'trainability' => $this->encodeExperience($validated['prior_pet_experience']),
            'temperament' => $this->encodeComposition($validated['household_composition']),
            'medical' => $this->encodeIncome($validated['monthly_income_range']),
        ];

        return view('recommendation.results', [
            'matches' => $matches,
            'sliders' => $sliders,
            'matcher' => $this->knn,
        ]);
    }

    /**
     * POST /recommendation/recompute  (AJAX)
     * Re-rank all available pets against slider values.
     * Returns JSON: { matches: [ {id, overall, match_label, rows:[{label, percent}]} ] }
     */
    public function recompute(Request $request)
    {
        // Sliders are sent as a JSON body from the frontend
        $raw = $request->json()->all();

        $sliders = [
            'energy' => (int) ($raw['energy'] ?? 3),
            'trainability' => (int) ($raw['trainability'] ?? 3),
            'medical' => (int) ($raw['medical'] ?? 3),
            'independence' => (int) ($raw['independence'] ?? 3),
            'temperament' => (int) ($raw['temperament'] ?? 3),
        ];

        $matches = $this->knn->recompute($sliders);

        $payload = $matches->map(fn ($m) => [
            'id' => $m['pet']->id,
            'overall' => $m['result']['overall'],
            'match_label' => $this->knn->matchLabel($m['result']['overall']),
            'rows' => $m['result']['rows'],
        ])->values()->all();

        return response()->json(['matches' => $payload]);
    }

    // ─── Private encoding helpers (mirrors KnnRecommendationService) ──────────

    private function encodeActivity(string $v): int
    {
        return match ($v) {
            'Low (Sedentary, short walks)' => 1,
            'Moderate (Daily walks, occasional play)' => 3,
            'High (Active, jogging, hiking)' => 5,
            default => 3,
        };
    }

    private function encodeTime(string $v): int
    {
        return match ($v) {
            'Less than 2 hours/day' => 5,
            '2-4 hours/day' => 4,
            '4-8 hours/day' => 2,
            'More than 8 hours/day (Work from home / Retired)' => 1,
            default => 3,
        };
    }

    private function encodeExperience(string $v): int
    {
        return match ($v) {
            'First-time owner' => 5,
            'Have owned pets in the past' => 4,
            'Currently own pets' => 3,
            'Experienced with rescue/special needs animals' => 1,
            default => 3,
        };
    }

    private function encodeComposition(string $v): int
    {
        return match ($v) {
            'Living with children (under 12)' => 5,
            'Living with teenagers' => 4,
            'Living with adults only' => 3,
            'Living alone' => 2,
            default => 3,
        };
    }

    private function encodeIncome(string $v): int
    {
        return match ($v) {
            'Below ₱15,000' => 1,
            '₱15,000 - ₱30,000' => 2,
            '₱30,000 - ₱50,000' => 3,
            '₱50,000 - ₱80,000' => 4,
            'Above ₱80,000' => 5,
            default => 3,
        };
    }
}
