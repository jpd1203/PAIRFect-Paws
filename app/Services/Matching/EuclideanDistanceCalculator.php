<?php

namespace App\Services\Matching;

final class EuclideanDistanceCalculator
{
    public function __construct(private MatchingConfiguration $config) {}

    public function breakdown(array $adopter, array $pet): array
    {
        $builder = new PetFeatureBuilder;
        $adopter = $builder->build($adopter);
        $pet = $builder->build($pet);
        $breakdown = [];
        foreach (MatchingConfiguration::FEATURES as $feature) {
            $diff = $adopter[$feature] - $pet[$feature];
            $weight = $this->config->weights[$feature];
            $breakdown[$feature] = [
                'variable' => $feature, 'ideal' => $adopter[$feature], 'pet' => $pet[$feature],
                'diff' => $diff, 'weight' => $weight, 'contribution' => $weight * ($diff ** 2),
            ];
        }

        return $breakdown;
    }

    public function calculate(array $adopter, array $pet): float
    {
        return sqrt(array_sum(array_column($this->breakdown($adopter, $pet), 'contribution')));
    }

    public function maximum(): float
    {
        return sqrt(array_sum($this->config->weights) * 16.0);
    }
}
