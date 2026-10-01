<?php

namespace App\Http\Controllers;

use App\Services\KnnRecommendationService;
use App\Services\Matching\AdopterMatchingProfileService;
use App\Services\Matching\MatchingProfileMapper;
use App\Support\ApplicationOptions;
use App\Models\Pet;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class RecommendationController extends Controller
{
    public function __construct(
        private KnnRecommendationService $knn,
        private AdopterMatchingProfileService $profiles,
        private MatchingProfileMapper $mapper,
    ) {}

    public function onboarding(Request $request)
    {
        return $this->intake($request);
    }

    public function intake(Request $request)
    {
        $isOnboarding = $request->routeIs('recommendation.onboarding');
        $returnPetId = $request->integer('return_pet') ?: ($isOnboarding ? (int) $request->session()->get('matching_return_pet') : 0);
        $returnPet = $returnPetId > 0
            ? Pet::whereKey($returnPetId)->where('availability_status', 'Available')->first()
            : null;
        if ($returnPet) {
            $request->session()->put('matching_return_pet', $returnPet->id);
        } else {
            $request->session()->forget('matching_return_pet');
        }

        return view('recommendation.intake', [
            'options' => ApplicationOptions::class,
            'profile' => $request->user()->adopterProfile,
            'returnPet' => $returnPet,
            'isOnboarding' => $isOnboarding,
        ]);
    }

    public function skipOnboarding(Request $request)
    {
        $request->user()->forceFill(['matching_onboarding_pending' => false])->save();
        $request->session()->forget(['matching_return_pet', 'url.intended']);

        return redirect()->route('animal.index')->with('success', 'You can browse pets now. Complete your assessment when you are ready to get recommendations or apply.');
    }

    public function start(Request $request)
    {
        $this->profiles->save($request->user(), $request->all());
        $request->user()->forceFill(['matching_onboarding_pending' => false])->save();
        $request->session()->forget('url.intended');

        $returnPetId = $request->session()->pull('matching_return_pet');
        if ($returnPetId && Pet::whereKey($returnPetId)->where('availability_status', 'Available')->exists()) {
            return redirect()->route('application.apply', $returnPetId)
                ->with('success', 'Personality assessment saved. Continue your application for the selected pet.');
        }

        return redirect()->route('recommendation.results');
    }

    public function results(Request $request)
    {
        $profile = $request->user()->adopterProfile;
        if (! $profile || ! $this->mapper->adopterIsComplete($profile)) {
            return redirect()->route('recommendation.intake')->withErrors(['profile' => 'Complete your personality and household profile to see recommendations.']);
        }

        return view('recommendation.results', [
            'matches' => $this->knn->recommendPets($profile, preferences: $this->preferences($request)),
            'matcher' => $this->knn,
        ]);
    }

    public function recompute(Request $request)
    {
        $profile = $request->user()->adopterProfile;
        abort_unless($profile && $this->mapper->adopterIsComplete($profile), 422, 'Complete your matching profile first.');
        $matches = $this->knn->recommendPets($profile, preferences: $this->preferences($request));

        return response()->json(['matches' => $matches->map(fn ($item) => [
            'id' => $item['pet']->id, 'pet_name' => $item['pet']->name,
            'overall' => $item['result']['overall'],
            'compatibility_score' => $item['result']['overall'],
            'status' => $item['pet']->availability_status->value,
            'match_label' => $this->knn->matchLabel($item['result']['overall']),
        ])]);
    }

    private function preferences(Request $request): array
    {
        return $request->validate([
            'species' => ['nullable', Rule::in(['Dog', 'Cat'])],
            'size' => ['nullable', Rule::in(array_keys(config('matching.size_levels')))],
            'score' => 'prohibited', 'knn_score' => 'prohibited', 'distance' => 'prohibited',
            'energy' => 'prohibited', 'trainability' => 'prohibited', 'temperament' => 'prohibited',
            'medical' => 'prohibited', 'independence' => 'prohibited', 'profile' => 'prohibited',
        ]);
    }
}
