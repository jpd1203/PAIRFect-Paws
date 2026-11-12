<?php

namespace App\Support;

use App\Models\Pet;
use Database\Seeders\FreshStartPetsSeeder;

/** Explicit fictional inputs, never classification outcomes or observed measurements. */
final class PrototypePetFixtures
{
    public static function marker(string $key): string
    {
        return "[PAIRFECT-PROTOTYPE:{$key}:v1]";
    }

    public static function sampleMeasurements(): array
    {
        return [
            'Luna' => ['species' => 'Cat', 'age' => 24, 'weight_kg' => '3.000'],
            'Buddy' => ['species' => 'Dog', 'age' => 36, 'weight_kg' => '20.000'],
            'Mochi' => ['species' => 'Cat', 'age' => 12, 'weight_kg' => '3.000'],
            'Max' => ['species' => 'Dog', 'age' => 48, 'weight_kg' => '12.000'],
            'Coco' => ['species' => 'Dog', 'age' => 24, 'weight_kg' => '7.000'],
            'Oliver' => ['species' => 'Cat', 'age' => 36, 'weight_kg' => '4.000'],
        ];
    }

    public static function pets(): array
    {
        $fixtures = [];
        // Representative fictional points, not a second definition of the size bands.
        foreach (['Dog' => ['2.000', '7.000', '12.000', '20.000', '32.000'],
            'Cat' => ['2.000', '3.500', '4.500', '5.500', '7.000']] as $species => $weights) {
            foreach ($weights as $index => $weight) {
                $key = strtolower($species).'-adult-'.($index + 1);
                $fixtures[$key] = self::fixture($key, $species, 36, $weight);
            }
        }
        $fixtures['dog-juvenile'] = self::fixture('dog-juvenile', 'Dog', 23, '12.000');
        $fixtures['cat-age-unknown'] = self::fixture('cat-age-unknown', 'Cat', null, '4.500');
        $fixtures['dog-weight-missing'] = self::fixture('dog-weight-missing', 'Dog', 36, null);
        $fixtures['cat-incomplete'] = self::fixture('cat-incomplete', 'Cat', 36, '4.500');
        $fixtures['cat-incomplete']['observers'] = 2;
        $fixtures['dog-unavailable'] = self::fixture('dog-unavailable', 'Dog', 36, '12.000');
        $fixtures['dog-unavailable']['pet']['availability_status'] = 'Assessing';

        return $fixtures;
    }

    private static function fixture(string $key, string $species, ?int $age, ?string $weight): array
    {
        return ['observers' => 3, 'pet' => [
            'name' => 'Prototype '.str_replace('-', ' ', $key), 'species' => $species,
            'breed' => 'Fictional Mixed Breed', 'sex' => 'Female', 'age' => $age, 'weight_kg' => $weight,
            'description' => "Fictional Phase 15C prototype fixture {$key}; not a real animal.",
            'behavioral_notes' => self::marker($key), 'availability_status' => 'Available',
            'medical_needs' => 2, 'life_stage' => $age === null ? null : ($age < 24 ? 'young' : 'adult'),
            'has_aggression_history' => false, 'high_vocalization' => false,
        ]];
    }

    /** A name or a plausible size alone is never evidence of a designated fixture. */
    public static function identify(Pet $pet): ?array
    {
        $speciesValue = $pet->getAttributes()['species'] ?? null;
        foreach (self::pets() as $key => $fixture) {
            if ($pet->name === $fixture['pet']['name'] && $speciesValue === $fixture['pet']['species']
                && $pet->description === $fixture['pet']['description'] && $pet->behavioral_notes === self::marker($key)) {
                return ['key' => 'prototype:'.$key, 'measurements' => array_intersect_key($fixture['pet'], array_flip(['species', 'age', 'weight_kg']))];
            }
        }
        foreach (FreshStartPetsSeeder::normalizationFixtures() as $name => $fixture) {
            foreach (['' => 'fresh-start:', 'Demo ' => 'pilot:'] as $prefix => $source) {
                if ($pet->name === $prefix.$name && $speciesValue === $fixture['species']
                    && $pet->breed === $fixture['breed'] && $pet->sex === $fixture['sex']
                    && $pet->description === $fixture['description']
                    && ($prefix === '' || str_contains($pet->behavioral_notes ?? '', '[PAIRFECT-PILOT-ASSESSED-15-V1]'))) {
                    return ['key' => $source.$name, 'measurements' => array_intersect_key($fixture, array_flip(['species', 'age', 'weight_kg']))];
                }
            }
        }
        for ($i = 1; $i <= 12; $i++) {
            $species = $i <= 6 ? 'Dog' : 'Cat';
            if ($pet->name === "Matching Demo {$species} {$i}" && $speciesValue === $species
                && $pet->description === 'Synthetic matching QA record; not a real shelter animal.') {
                return ['key' => 'matching:'.$species.':'.$i, 'measurements' => [
                    'species' => $species, 'age' => $i === 6 ? 120 : 36,
                    'weight_kg' => ($species === 'Dog' ? ['1.000', '7.000', '12.000', '20.000', '30.000']
                        : ['2.000', '3.000', '4.000', '5.000', '6.500'])[($i - 1) % 5],
                ]];
            }
        }
        foreach (self::sampleMeasurements() as $name => $measurements) {
            if ($pet->name === $name && $speciesValue === $measurements['species']
                && $pet->behavioral_notes === self::marker('sample-'.strtolower($name))) {
                return ['key' => 'sample:'.$name, 'measurements' => $measurements];
            }
        }

        return null;
    }
}
