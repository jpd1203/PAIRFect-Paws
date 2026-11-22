<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Pet;
use App\Services\AdopterHistoryService;
use App\Services\Matching\ApplicantRankingService;
use App\Support\ResearchAdminRecords;
use App\Support\ResearchMode;
use Illuminate\Support\Collection;

class CompatibilityController extends Controller
{
    public function index(ApplicantRankingService $ranking, AdopterHistoryService $history, ResearchAdminRecords $research)
    {
        return $this->render(null, $ranking, $history, $research);
    }

    public function forPet(Pet $pet, ApplicantRankingService $ranking, AdopterHistoryService $history, ResearchAdminRecords $research)
    {
        return $this->render($pet, $ranking, $history, $research);
    }

    private function render(?Pet $pet, ApplicantRankingService $ranking, AdopterHistoryService $history, ResearchAdminRecords $research)
    {
        $workflowReadOnly = ResearchMode::ready();
        $applications = $workflowReadOnly
            ? $research->rankForDisplay($research->applications()->when($pet, fn ($q) => $q->where('pet_id', $pet->id))->latest()->get(), $ranking)
            : $this->withPetRanks($ranking->rankApplicants($pet), $ranking);

        return view('admin.compatibility.index', [
            'applications' => $applications, 'pet' => $pet, 'ranking' => $ranking,
            'historySummaries' => $workflowReadOnly ? [] : $history->summariesForApplications($applications),
            'workflowReadOnly' => $workflowReadOnly,
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
