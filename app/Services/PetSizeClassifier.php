<?php

namespace App\Services;

use App\Enums\Species;
use App\Models\Pet;

/** Application-defined adult weight bands; never a healthy-weight assessment. */
final class PetSizeClassifier
{
    public const VERSION = 'weight-v1';

    private const BANDS = [
        'Dog' => [6500, 9000, 15000, 30000],
        'Cat' => [3000, 4000, 5000, 6500],
    ];

    public function classify(Species|string|null $species, mixed $weightKg, ?int $ageMonths): array
    {
        $species = $species instanceof Species ? $species->value : $species;
        $status = match (true) {
            ! isset(self::BANDS[$species ?? '']) => 'unsupported_species',
            $ageMonths === null || $ageMonths < 0 => 'age_unknown',
            $ageMonths < ($species === 'Dog' ? 24 : 12) => 'juvenile',
            default => 'classified',
        };
        $grams = $this->grams($weightKg);
        if ($status === 'classified' && $grams === null) {
            $status = 'weight_unavailable';
        }
        $size = null;
        if ($status === 'classified') {
            $index = 0;
            foreach (self::BANDS[$species] as $boundary) {
                if ($grams >= $boundary) {
                    $index++;
                }
            }
            $size = ['Extra Small', 'Small', 'Medium', 'Large', 'Extra Large'][$index];
        }

        return ['physical_size' => $size, 'size_classification_status' => $status, 'size_rule_version' => self::VERSION];
    }

    /** Same operation for model events and the version-checked query-builder path. */
    public function changes(Pet $pet, array $overrides = []): array
    {
        $values = array_replace($pet->getAttributes(), $overrides);
        $result = $this->classify($values['species'] ?? null, $values['weight_kg'] ?? null,
            isset($values['age']) ? (int) $values['age'] : null);
        if ($pet->exists && ($pet->size_classification_status === 'legacy_unclassified' || $pet->size_rule_version === null)) {
            $result['legacy_physical_size'] = $pet->legacy_physical_size ?? $pet->getRawOriginal('physical_size');
        }

        return $result;
    }

    public function isCurrent(Pet $pet): bool
    {
        return $pet->size_classification_status === 'classified'
            && $pet->size_rule_version === self::VERSION
            && $pet->physical_size !== null
            && $this->classify($pet->getAttributes()['species'] ?? null, $pet->getAttributes()['weight_kg'] ?? null, $pet->age)['physical_size'] === $pet->physical_size;
    }

    private function grams(mixed $weight): ?int
    {
        if ((! is_string($weight) && ! is_int($weight) && ! is_float($weight))
            || (is_float($weight) && ! is_finite($weight))
            || ! preg_match('/\A([0-9]{1,7})(?:\.([0-9]{1,3}))?\z/', (string) $weight, $parts)) {
            return null;
        }
        // Integers only: 6.499 and 6.500 cannot drift across a binary-float boundary.
        $grams = (int) $parts[1] * 1000 + (int) str_pad($parts[2] ?? '', 3, '0');

        return $grams > 0 ? $grams : null;
    }
}
