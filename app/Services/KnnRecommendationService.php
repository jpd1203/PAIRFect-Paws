<?php

namespace App\Services;

class KnnRecommendationService
{
    /**
     * Executes the KNN algorithm using Euclidean distance.
     *
     * @param array $petScores [energy, sociability, trainability, adaptability]
     * @param array $adopterScores [activity, patience, experience, flexibility]
     * @return float The calculated distance (lower is better compatibility).
     */
    public function calculateDistance(array $petScores, array $adopterScores): float
    {
        $sumOfSquares = 0;

        foreach ($petScores as $trait => $petScore) {
            $adopterScore = $adopterScores[$trait] ?? 3; // Default to neutral if missing
            $sumOfSquares += pow($petScore - $adopterScore, 2);
        }

        return sqrt($sumOfSquares);
    }
}
