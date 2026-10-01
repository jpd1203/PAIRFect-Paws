<?php

namespace App\Services\Matching;

use InvalidArgumentException;

final class BfiScorer
{
    public function __construct(private MatchingConfiguration $config) {}

    public function score(array $responses): array
    {
        $mapping = $this->config->values['bfi']['dimensions'];
        $keys = array_merge(...array_values($mapping));
        if (array_diff(array_keys($responses), $keys) || array_diff($keys, array_keys($responses))) {
            throw new InvalidArgumentException('Complete every BFI questionnaire item using the configured item keys.');
        }
        $means = [];
        foreach ($mapping as $dimension => $items) {
            $sum = 0.0;
            foreach ($items as $item) {
                $raw = $responses[$item];
                if (is_bool($raw) || filter_var($raw, FILTER_VALIDATE_INT) === false || $raw < 1 || $raw > 5) {
                    throw new InvalidArgumentException("BFI response {$item} must be an integer from 1 to 5.");
                }
                $sum += in_array($item, $this->config->values['bfi']['reverse'], true) ? 6 - (int) $raw : (int) $raw;
            }
            $means[$dimension] = $sum / count($items);
        }

        return $means;
    }
}
