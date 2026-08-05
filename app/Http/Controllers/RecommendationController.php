<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreAdopterProfileRequest;
use App\Models\AdopterProfile;
use App\Models\Pet;
use App\Services\PetMatchService;
use App\Support\ApplicationOptions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class RecommendationController extends Controller
{
    private const DEFAULT_SLIDERS = ['energy' => 3, 'independence' => 3, 'trainability' => 3, 'medical' => 3, 'temperament' => 3];

    /** GET /recommendation — the lifestyle intake form shown before "Start Matching". */
    public function intake()
    {
        $existing = AdopterProfile::where('user_id', Auth::id())->first();

        return view('recommendation.intake', [
            'options' => ApplicationOptions::class,
            'profile' => $existing,
        ]);
    }

    /** POST /recommendation — saves the intake answers, then redirects to the live results screen. */
    public function startMatching(StoreAdopterProfileRequest $request)
    {
        AdopterProfile::updateOrCreate(
            ['user_id' => Auth::id()],
            $request->validated()
        );

        return redirect()->route('recommendation.results');
    }

    /** GET /recommendation/results — the compatibility-sliders screen. */
    public function results(PetMatchService $matcher)
    {
        $profile = AdopterProfile::where('user_id', Auth::id())->first();

        if (!$profile) {
            return redirect()->route('recommendation.intake')
                ->with('toast', ['type' => 'error', 'message' => 'Please fill in your adopter profile first.']);
        }

        $sliders = self::DEFAULT_SLIDERS;
        $adopter = ['housing_type' => $profile->housing_type, 'household_composition' => $profile->household_composition];

        $pets = Pet::where('status', 'Available')->orderBy('name')->get();
        $matches = $pets->map(fn (Pet $pet) => [
            'pet' => $pet,
            'result' => $matcher->score($sliders, $adopter, $pet),
        ])->sortByDesc(fn ($m) => $m['result']['overall'])->values();

        return view('recommendation.results', [
            'profile' => $profile,
            'sliders' => $sliders,
            'matches' => $matches,
            'matcher' => $matcher,
        ]);
    }

    /**
     * AJAX endpoint: recompute compatibility for all pets as the user drags a slider.
     * Read-only computation — auth required; inputs whitelisted to 1-5 integers.
     */
    public function recompute(Request $request, PetMatchService $matcher)
    {
        $validated = $request->validate([
            'energy' => ['required', 'integer', 'between:1,5'],
            'independence' => ['required', 'integer', 'between:1,5'],
            'trainability' => ['required', 'integer', 'between:1,5'],
            'medical' => ['required', 'integer', 'between:1,5'],
            'temperament' => ['required', 'integer', 'between:1,5'],
        ]);

        $profile = AdopterProfile::where('user_id', Auth::id())->firstOrFail();
        $adopter = ['housing_type' => $profile->housing_type, 'household_composition' => $profile->household_composition];

        $pets = Pet::where('status', 'Available')->orderBy('name')->get();
        $matches = $pets->map(function (Pet $pet) use ($matcher, $validated, $adopter) {
            $result = $matcher->score($validated, $adopter, $pet);

            return [
                'id' => $pet->id,
                'overall' => $result['overall'],
                'rows' => $result['rows'],
                'match_label' => $matcher->matchLabel($result['overall']),
            ];
        })->sortByDesc('overall')->values();

        return response()->json(['matches' => $matches]);
    }
}
