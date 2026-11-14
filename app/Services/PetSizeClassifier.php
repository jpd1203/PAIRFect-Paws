<?php

namespace App\Services;

use App\Enums\Species;
use App\Models\Pet;

/** Current measured-weight bands; neither adult-size prediction nor a health assessment. */
final class PetSizeClassifier
{
    public const VERSION = 'weight-v2';

    public const COMPATIBLE_VERSIONS = ['weight-v2', 'weight-v1'];

    private const BANDS = [
        'Dog' => [6500, 9000, 15000, 30000],
        'Cat' => [3000, 4000, 5000, 6500],
    ];

    // Read compatibility ONLY: v1 could classify adults, never juveniles/unknown ages.
    public const LEGACY_MINIMUM_AGES = ['Dog' => 24, 'Cat' => 12];

    private const SIZES = ['Extra Small', 'Small', 'Medium', 'Large', 'Extra Large'];

    /** Bounds and display labels for filters, derived from the classification policy. */
    public function bandsFor(string $species): array
    {
        if (! isset(self::BANDS[$species])) {
            return [];
        }
        $bands = [];
        $lower = 0;
        foreach (self::SIZES as $index => $size) {
            $upper = self::BANDS[$species][$index] ?? null;
            $minimum = $this->kilograms($lower);
            $maximum = $upper === null ? null : $this->kilograms($upper);
            $range = $lower === 0 ? "below {$maximum} kg"
                : ($maximum === null ? "{$minimum} kg and above" : "{$minimum} to under {$maximum} kg");
            $bands[$size] = ['minimum_kg' => $minimum, 'maximum_kg' => $maximum,
                'label' => "{$size} ({$range})"];
            $lower = $upper ?? $lower;
        }

        return $bands;
    }

    public function filterOptions(): array
    {
        $options = [];
        foreach (array_keys(self::BANDS) as $species) {
            foreach ($this->bandsFor($species) as $size => $band) {
                $options[$species][] = ['value' => $size, 'label' => $band['label']];
            }
        }

        return $options;
    }

    private function kilograms(int $grams): string
    {
        $fraction = rtrim(str_pad((string) ($grams % 1000), 3, '0', STR_PAD_LEFT), '0');

        return (string) intdiv($grams, 1000).($fraction === '' ? '' : '.'.$fraction);
    }

    public function classify(Species|string|null $species, mixed $weightKg, ?int $ageMonths = null): array
    {
        $species = $species instanceof Species ? $species->value : $species;
        $status = match (true) {
            ! isset(self::BANDS[$species ?? '']) => 'unsupported_species',
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
            $size = self::SIZES[$index];
        }

        return ['physical_size' => $size, 'size_classification_status' => $status, 'size_rule_version' => self::VERSION];
    }

    /** Persisted provenance identifies records that have not yet been recalculated. */
    public function requiresHistoricalReview(Pet $pet): bool
    {
        return $pet->exists && $pet->getRawOriginal('size_classification_status') === 'legacy_unclassified';
    }

    /** Same operation for model events and the version-checked query-builder path. */
    public function changes(Pet $pet, array $overrides = []): array
    {
        $values = array_replace($pet->getAttributes(), $overrides);
        $historical = $this->requiresHistoricalReview($pet);
        $species = $values['species'] ?? null;
        $species = $species instanceof Species ? $species->value : $species;
        $measurementChanged = $species !== $pet->getRawOriginal('species')
            || $this->grams($values['weight_kg'] ?? null) !== $this->grams($pet->getRawOriginal('weight_kg'));
        if ($historical && ! $measurementChanged) {
            // Unrelated edits and equivalent weight formatting must not promote old data.
            return [
                'physical_size' => $pet->getRawOriginal('physical_size'),
                'legacy_physical_size' => $pet->getRawOriginal('legacy_physical_size'),
                'size_classification_status' => 'legacy_unclassified',
                'size_rule_version' => $pet->getRawOriginal('size_rule_version'),
            ];
        }

        $result = $this->classify($values['species'] ?? null, $values['weight_kg'] ?? null,
            isset($values['age']) ? (int) $values['age'] : null);
        if ($pet->exists && ($historical || $pet->getRawOriginal('size_rule_version') === null)) {
            $result['legacy_physical_size'] = $pet->getRawOriginal('legacy_physical_size') ?? $pet->getRawOriginal('physical_size');
        }

        return $result;
    }

    public function isCurrent(Pet $pet): bool
    {
        return ! $this->requiresHistoricalReview($pet)
            && $pet->size_classification_status === 'classified'
            && ($pet->size_rule_version === self::VERSION
                || ($pet->size_rule_version === 'weight-v1'
                    && $pet->age !== null
                    && isset(self::LEGACY_MINIMUM_AGES[$pet->getAttributes()['species'] ?? ''])
                    && $pet->age >= self::LEGACY_MINIMUM_AGES[$pet->getAttributes()['species']]))
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
