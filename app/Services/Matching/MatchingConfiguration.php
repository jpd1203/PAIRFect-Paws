<?php

namespace App\Services\Matching;

use InvalidArgumentException;

final readonly class MatchingConfiguration
{
    public const FEATURES = ['energy', 'trainability', 'independence', 'temperament', 'physical_size', 'medical_needs'];

    public array $values;

    public array $weights;

    public function __construct(?array $values = null)
    {
        $values ??= config('matching');
        $weights = $values['weights'] ?? [];
        if (array_diff(self::FEATURES, array_keys($weights)) || array_diff(array_keys($weights), self::FEATURES)) {
            throw new InvalidArgumentException('Matching weights must contain exactly the six matching features.');
        }
        foreach ($weights as $weight) {
            if (! is_numeric($weight) || ! is_finite((float) $weight) || $weight <= 0) {
                throw new InvalidArgumentException('Every matching weight must be finite and strictly greater than zero.');
            }
        }
        if (($values['scale'] ?? []) !== ['min' => 1.0, 'max' => 5.0]) {
            throw new InvalidArgumentException('Matching requires a continuous 1.0–5.0 scale.');
        }
        foreach (['spatial_penalty' => [0, PHP_FLOAT_MAX], 'min_answered_ratio' => [0.01, 1],
            'min_observers' => [1, PHP_INT_MAX], 'top_k' => [1, PHP_INT_MAX]] as $key => [$min, $max]) {
            $number = $values[$key] ?? null;
            if (! is_numeric($number) || ! is_finite((float) $number) || $number < $min || $number > $max) {
                throw new InvalidArgumentException("Invalid matching configuration: {$key}.");
            }
        }
        foreach (['reactive_threshold', 'low_financial_level', 'healthy_max_medical', 'high_energy_threshold'] as $key) {
            if (! self::validValue($values['filters'][$key] ?? null)) {
                throw new InvalidArgumentException("Invalid matching filter: {$key}.");
            }
        }
        foreach (['min_observers', 'top_k'] as $key) {
            if (! is_int($values[$key])) {
                throw new InvalidArgumentException("Matching configuration {$key} must be an integer.");
            }
        }
        if (empty($values['algorithm_version'])) {
            throw new InvalidArgumentException('A matching algorithm version is required.');
        }
        $this->values = $values;
        $this->weights = array_map('floatval', $weights);
    }

    public static function validValue(mixed $value): bool
    {
        return is_numeric($value) && is_finite((float) $value) && $value >= 1 && $value <= 5;
    }

    public function version(): string
    {
        return $this->values['algorithm_version'];
    }

    public function minObservers(): int
    {
        return (int) ($this->values['min_observers'] ?? 3);
    }
}
