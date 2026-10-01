<?php

namespace App\Services\Matching;

use InvalidArgumentException;

final class BehaviorScorer
{
    public function __construct(private MatchingConfiguration $config) {}

    public function score(string $species, array $responses): array
    {
        $species = strtolower($species);
        $groups = $this->config->values['items'][$species] ?? null;
        if (! $groups) {
            throw new InvalidArgumentException('Behavior scoring supports Dog and Cat only.');
        }
        if (array_diff(array_keys($responses), array_keys($groups))) {
            throw new InvalidArgumentException('Unknown behavior subscale.');
        }
        $means = [];
        foreach ($groups as $group => $items) {
            $answers = $responses[$group] ?? [];
            if (! is_array($answers) || array_diff(array_keys($answers), array_keys($items))) {
                throw new InvalidArgumentException("Unknown behavior item in {$group}.");
            }
            $scored = [];
            foreach ($items as $key => $text) {
                $value = $answers[$key] ?? null;
                if ($value === null || $value === '') {
                    continue;
                }
                if (is_bool($value) || filter_var($value, FILTER_VALIDATE_INT) === false || $value < 0 || $value > 4) {
                    throw new InvalidArgumentException("Behavior response {$species}.{$group}.{$key} must be an integer from 0 to 4.");
                }
                $scored[] = in_array("{$species}.{$group}.{$key}", $this->config->values['behavior_reverse'], true)
                    ? 4 - (int) $value : (int) $value;
            }
            $means[$group] = count($scored) / count($items) >= $this->config->values['min_answered_ratio']
                ? (float) array_sum($scored) / count($scored) : null;
        }
        $attachment = $species === 'dog'
            ? $means['attachment'] : $this->pairMean($means['sociability'], $means['attention_seeking']);
        // The current capstone item semantics indicate that higher raw attachment/attention-seeking
        // values represent lower independence. Confirm this interpretation against the finalized assessment specification.
        $independence = $attachment === null ? null
            : ($this->config->values['independence']['reverse_attachment'] ? 4.0 - $attachment : $attachment);

        return [
            'energy' => $means['energy'], 'trainability' => $means['trainability'],
            'independence' => $independence,
            'temperament' => $species === 'dog'
                ? $this->pairMean($means['stranger_fear'], $means['non_social_fear'])
                : $means['temperament'],
            'subscales' => $means,
        ];
    }

    private function pairMean(?float $first, ?float $second): ?float
    {
        return $first === null || $second === null ? null : ($first + $second) / 2.0;
    }
}
