<?php

namespace Tests\Unit;

use App\Enums\Species;
use App\Services\PetLifeStageClassifier;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class PetLifeStageClassifierTest extends TestCase
{
    public static function boundaries(): array
    {
        return [
            ['Dog', 0, 'young'], ['Dog', 23, 'young'], ['Dog', 24, 'adult'], ['Dog', 95, 'adult'],
            ['Dog', 96, 'senior'], ['Dog', 200, 'senior'],
            ['Cat', 0, 'young'], ['Cat', 11, 'young'], ['Cat', 12, 'adult'], ['Cat', 131, 'adult'],
            ['Cat', 132, 'senior'], ['Cat', 200, 'senior'],
            ['Dog', null, null], ['Cat', null, null], ['Bird', 24, null], [null, 24, null],
        ];
    }

    #[DataProvider('boundaries')]
    public function test_central_species_specific_boundaries(?string $species, ?int $age, ?string $expected): void
    {
        $this->assertSame($expected, (new PetLifeStageClassifier)->classify($species, $age));
    }

    public static function invalidAges(): array
    {
        return array_map(fn ($value) => [$value], [-1, '-1', '', '1.5', 1.5, true, false, [], INF, NAN, '1e2', '2147483648', '9999999999999999']);
    }

    #[DataProvider('invalidAges')]
    public function test_invalid_ages_are_rejected_not_guessed(mixed $age): void
    {
        $classifier = new PetLifeStageClassifier;
        $this->assertNull($classifier->fromStored('Dog', $age));
        $this->expectException(InvalidArgumentException::class);
        $classifier->classify('Dog', $age);
    }

    public function test_enum_and_persisted_integer_strings_are_supported(): void
    {
        $this->assertSame('adult', (new PetLifeStageClassifier)->classify(Species::Cat, '012'));
    }
}
