<?php

namespace App\Services\Matching;

use InvalidArgumentException;

final class CompatibilityScorer
{
    public function __construct(private EuclideanDistanceCalculator $distance) {}

    public function fromDistance(float $adjustedDistance): float
    {
        if (! is_finite($adjustedDistance) || $adjustedDistance < 0) {
            throw new InvalidArgumentException('Matching distance must be finite and non-negative.');
        }

        return max(0.0, min(100.0, 100.0 * (1.0 - $adjustedDistance / $this->distance->maximum())));
    }
}
