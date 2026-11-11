<?php

namespace Tests\Unit;

use App\Services\PetSizeClassifier;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class PetSizeClassifierTest extends TestCase
{
    public static function boundaries(): array
    {
        $cases = [];
        foreach (['Dog' => ['6.499', '6.500', '8.999', '9.000', '14.999', '15.000', '29.999', '30.000'],
            'Cat' => ['2.999', '3.000', '3.999', '4.000', '4.999', '5.000', '6.499', '6.500']] as $species => $weights) {
            foreach (array_combine($weights, ['Extra Small', 'Small', 'Small', 'Medium', 'Medium', 'Large', 'Large', 'Extra Large']) as $weight => $label) {
                $cases[$species.' '.$weight] = [$species, $weight, $label];
            }
            $cases[$species.' minimum'] = [$species, '0.001', 'Extra Small'];
            $cases[$species.' maximum'] = [$species, '9999999.999', 'Extra Large'];
        }
        $cases['leading zeroes'] = ['Dog', '006.500', 'Small'];
        $cases['native integer'] = ['Dog', 9, 'Medium'];
        $cases['native float'] = ['Cat', 6.5, 'Extra Large'];

        return $cases;
    }

    #[DataProvider('boundaries')]
    public function test_exact_decimal_boundaries(string $species, mixed $weight, string $expected): void
    {
        $result = (new PetSizeClassifier)->classify($species, $weight, 24);
        $this->assertSame($expected, $result['physical_size']);
        $this->assertSame('classified', $result['size_classification_status']);
        $this->assertSame('weight-v1', $result['size_rule_version']);
    }

    public static function unavailable(): array
    {
        return [
            ['Dog', '9', 23, 'juvenile'], ['Cat', '4', 11, 'juvenile'],
            ['Dog', '9', null, 'age_unknown'], ['Cat', '4', null, 'age_unknown'],
            ['Dog', '9', 0, 'juvenile'], ['Dog', '9', -1, 'age_unknown'],
            ['Bird', '9', 24, 'unsupported_species'], [null, '9', 24, 'unsupported_species'],
            ...array_map(fn ($weight) => ['Dog', $weight, 24, 'weight_unavailable'], [null, '', 0, -1, 'NaN', NAN, INF, '1e2', '6.5001', [], true, '10000000']),
        ];
    }

    #[DataProvider('unavailable')]
    public function test_unavailable_values_do_not_fabricate_a_size(?string $species, mixed $weight, ?int $age, string $status): void
    {
        $result = (new PetSizeClassifier)->classify($species, $weight, $age);
        $this->assertNull($result['physical_size']);
        $this->assertSame($status, $result['size_classification_status']);
    }

    public function test_exact_adult_eligibility_boundaries(): void
    {
        $classifier = new PetSizeClassifier;
        $this->assertSame('Medium', $classifier->classify('Dog', '9.000', 24)['physical_size']);
        $this->assertSame('Medium', $classifier->classify('Cat', '4.000', 12)['physical_size']);
    }
}
