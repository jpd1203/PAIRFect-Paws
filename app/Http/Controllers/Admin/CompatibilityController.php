<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Pet;
use App\Services\AdopterHistoryService;
use App\Services\Matching\ApplicantRankingService;
use Illuminate\Support\Collection;

class CompatibilityController extends Controller
{
    public function index(ApplicantRankingService $ranking, AdopterHistoryService $history)
    {
        $applications = $this->withPetRanks($ranking->rankApplicants(), $ranking);
        return view('admin.compatibility.index', [
            'applications' => $applications, 'pet' => null, 'ranking' => $ranking,
            'historySummaries' => $history->summariesForApplications($applications),
        ]);
    }

    public function forPet(Pet $pet, ApplicantRankingService $ranking, AdopterHistoryService $history)
    {
        $applications = $this->withPetRanks($ranking->rankApplicants($pet), $ranking);
        return view('admin.compatibility.index', [
            'applications' => $applications, 'pet' => $pet, 'ranking' => $ranking,
            'historySummaries' => $history->summariesForApplications($applications),
        ]);
    }

    private function withPetRanks(Collection $applications, ApplicantRankingService $ranking): Collection
    {
        $applications->groupBy('pet_id')->each(function ($petApplications) use ($ranking) {
            $position = 0;
            $ranking->sort($petApplications)->each(function ($application) use ($ranking, &$position) {
                if ($ranking->isEligible($application)) {
                    $application->setAttribute('queue_position', ++$position);
                }
            });
        });

        return $applications;
    }
}
