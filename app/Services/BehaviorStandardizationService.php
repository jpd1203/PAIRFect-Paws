<?php

namespace App\Services;

class BehaviorStandardizationService
{
    /**
     * Standardizes C-BARQ/Fe-BARQ (Pets) and BFI-2 (Adopters) into a 1-5 scale.
     *
     * @param array $rawScores
     * @return array Standardized scores [1-5]
     */
    public function standardizeScores(array $rawScores): array
    {
        // Example: Convert raw C-BARQ energy level (e.g., 0-100) to 1-5 scale
        $standardized = [];
        foreach ($rawScores as $trait => $score) {
            // Stub logic for scaling
            $standardized[$trait] = max(1, min(5, (int) round($score / 20)));
        }
        
        return $standardized;
    }
}
