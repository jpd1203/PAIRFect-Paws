<?php

namespace App\Services;

use App\Enums\Species;
use Illuminate\Database\Eloquent\Builder;
use InvalidArgumentException;

/** Simplified application categories, not veterinary diagnoses or biological guarantees. */
final class PetLifeStageClassifier
{
    public const VERSION = 'life-stage-v1';

    public const MAXIMUM_AGE_MONTHS = 2147483647;

    public const THRESHOLDS = [
        'Dog' => ['adult' => 24, 'senior' => 96],
        'Cat' => ['adult' => 12, 'senior' => 132],
    ];

    public const LABELS = ['young' => 'Young', 'adult' => 'Adult', 'senior' => 'Senior'];

    public function classify(Species|string|null $species, mixed $ageMonths): ?string
    {
        if ($ageMonths !== null && ((! is_int($ageMonths) && ! is_string($ageMonths))
            || ! preg_match('/\A[0-9]+\z/', (string) $ageMonths)
            || strlen(ltrim((string) $ageMonths, '0')) > 10 || (float) $ageMonths > self::MAXIMUM_AGE_MONTHS)) {
            throw new InvalidArgumentException('Recorded age must be null or a nonnegative integer number of months within storage limits.');
        }
        $species = $species instanceof Species ? $species->value : $species;
        if ($ageMonths === null || ! isset(self::THRESHOLDS[$species ?? ''])) {
            return null;
        }
        $age = (int) $ageMonths;
        $threshold = self::THRESHOLDS[$species];

        return $age < $threshold['adult'] ? 'young' : ($age < $threshold['senior'] ? 'adult' : 'senior');
    }

    /** Read invalid historical ages as unknown, never fabricate an adult stage. */
    public function fromStored(Species|string|null $species, mixed $ageMonths): ?string
    {
        try {
            return $this->classify($species, $ageMonths);
        } catch (InvalidArgumentException) {
            return null;
        }
    }

    public function constrain(Builder $query, string $stage): Builder
    {
        if (! isset(self::LABELS[$stage])) {
            return $query->whereRaw('1 = 0');
        }

        return $query->where(function (Builder $speciesQuery) use ($stage): void {
            foreach (self::THRESHOLDS as $species => $threshold) {
                $speciesQuery->orWhere(function (Builder $band) use ($species, $threshold, $stage): void {
                    $band->where('pets.species', $species)
                        ->whereBetween('pets.age', [0, self::MAXIMUM_AGE_MONTHS])
                        ->whereRaw('pets.age = ROUND(pets.age, 0)');
                    if ($stage === 'young') {
                        $band->where('pets.age', '<', $threshold['adult']);
                    } elseif ($stage === 'adult') {
                        $band->where('pets.age', '>=', $threshold['adult'])->where('pets.age', '<', $threshold['senior']);
                    } else {
                        $band->where('pets.age', '>=', $threshold['senior']);
                    }
                });
            }
        });
    }
}
