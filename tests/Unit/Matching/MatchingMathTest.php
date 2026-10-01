<?php

namespace Tests\Unit\Matching;

use App\Services\Matching\CompatibilityScorer;
use App\Services\Matching\DTOs\AdopterData;
use App\Services\Matching\DTOs\PetData;
use App\Services\Matching\EuclideanDistanceCalculator;
use App\Services\Matching\HousingPenaltyCalculator;
use App\Services\Matching\IdealPetProfileBuilder;
use App\Services\Matching\KnnMatcher;
use App\Services\Matching\MatchFilters;
use App\Services\Matching\MatchingConfiguration;
use App\Services\Matching\PetFeatureBuilder;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class MatchingMathTest extends TestCase
{
    private MatchingConfiguration $config;

    private EuclideanDistanceCalculator $distance;

    private KnnMatcher $matcher;

    protected function setUp(): void
    {
        $this->config = new MatchingConfiguration(require dirname(__DIR__, 3).'/config/matching.php');
        $this->distance = new EuclideanDistanceCalculator($this->config);
        $adopters = new IdealPetProfileBuilder;
        $pets = new PetFeatureBuilder;
        $this->matcher = new KnnMatcher(
            $this->config, new MatchFilters($this->config, $adopters, $pets),
            $adopters, $pets, $this->distance, new HousingPenaltyCalculator($this->config),
            new CompatibilityScorer($this->distance),
        );
    }

    private function adopter(array $overrides = []): AdopterData
    {
        return new AdopterData(...array_replace([
            'id' => 1, 'personality' => ['extraversion' => 4.0, 'conscientiousness' => 4.0, 'neuroticism' => 2.0, 'openness' => 3.0],
            'housing' => 'house_with_yard', 'existingPets' => false, 'hasChildren' => false,
            'financialReadiness' => 4.0, 'bfiCompleted' => true,
        ], $overrides));
    }

    private function pet(array $overrides = []): PetData
    {
        return new PetData(...array_replace([
            'id' => 1, 'features' => array_combine(MatchingConfiguration::FEATURES, [4, 4, 3, 4, 4, 4]),
            'species' => 'Dog', 'availability' => 'Available', 'observerCount' => 3,
            'lifeStage' => 'adult', 'aggressionHistory' => false, 'highVocalization' => false,
        ], $overrides));
    }

    public function test_worked_example_and_feature_explanations(): void
    {
        $a = $this->matcher->calculateMatch($this->adopter(), $this->pet());
        $this->assertTrue($a->eligible);
        $this->assertSame([4.0, 4.0, 3.0, 4.0, 3.5, 4.0], array_values($a->adopterVector));
        $this->assertSame(0.5, $a->baseDistance);
        $this->assertEqualsWithDelta(94.8968963692, $a->compatibilityScore, 1e-9);
        $this->assertSame(0.25, $a->featureBreakdown['physical_size']['contribution']);
        $b = $this->matcher->calculateMatch($this->adopter(), $this->pet([
            'features' => array_combine(MatchingConfiguration::FEATURES, [1, 2, 5, 2, 1, 1]),
        ]));
        $this->assertEqualsWithDelta(sqrt(36.25), $b->baseDistance, 1e-12);
        $this->assertGreaterThan($b->compatibilityScore, $a->compatibilityScore);
    }

    public function test_transformations_preserve_precision_boundaries_and_source_values(): void
    {
        $builder = new IdealPetProfileBuilder;
        foreach ([1.0, 5.0] as $value) {
            foreach ($builder->build(array_fill_keys(['extraversion', 'conscientiousness', 'neuroticism', 'openness'], $value)) as $feature) {
                $this->assertGreaterThanOrEqual(1, $feature);
                $this->assertLessThanOrEqual(5, $feature);
            }
        }
        $source = ['extraversion' => 3.1, 'conscientiousness' => 3.25, 'neuroticism' => 2.15, 'openness' => 4.2];
        $vector = $builder->build($source);
        $this->assertSame(3.1, $vector['energy']);
        $this->assertEqualsWithDelta(3.075, $vector['independence'], 1e-12);
        $this->assertEqualsWithDelta(3.65, $vector['physical_size'], 1e-12);
        $this->assertSame(2.15, $source['neuroticism']);
    }

    public function test_distance_and_score_boundaries(): void
    {
        $one = array_fill_keys(MatchingConfiguration::FEATURES, 1.0);
        $five = array_fill_keys(MatchingConfiguration::FEATURES, 5.0);
        $this->assertSame(0.0, $this->distance->calculate($one, $one));
        $this->assertSame(sqrt(96), $this->distance->calculate($one, $five));
        $scorer = new CompatibilityScorer($this->distance);
        $this->assertSame(100.0, $scorer->fromDistance(0));
        $this->assertSame(0.0, $scorer->fromDistance(sqrt(96)));
        $this->assertSame(0.0, $scorer->fromDistance(100));
    }

    public function test_weight_only_changes_its_feature_contribution_and_maximum(): void
    {
        $values = $this->config->values;
        $values['weights']['energy'] = 2.0;
        $calculator = new EuclideanDistanceCalculator(new MatchingConfiguration($values));
        $a = array_fill_keys(MatchingConfiguration::FEATURES, 1.0);
        $p = array_fill_keys(MatchingConfiguration::FEATURES, 2.0);
        $breakdown = $calculator->breakdown($a, $p);
        $this->assertSame(2.0, $breakdown['energy']['contribution']);
        $this->assertSame(1.0, $breakdown['medical_needs']['contribution']);
        $this->assertSame(sqrt(7), $calculator->calculate($a, $p));
        $this->assertSame(sqrt(112), $calculator->maximum());
    }

    public function test_invalid_weight_configuration_is_rejected(): void
    {
        foreach ([['energy' => 0], ['energy' => -1], ['energy' => INF], ['extra' => 1]] as $changes) {
            $values = $this->config->values;
            $values['weights'] = array_replace($values['weights'], $changes);
            try {
                new MatchingConfiguration($values);
                $this->fail('Invalid weights were accepted.');
            } catch (InvalidArgumentException $e) {
                $this->assertStringContainsString('weight', $e->getMessage());
            }
        }
        $values = $this->config->values;
        unset($values['weights']['energy']);
        $this->expectException(InvalidArgumentException::class);
        new MatchingConfiguration($values);
    }

    public function test_hard_filters_return_no_distance_or_fake_score(): void
    {
        $cases = [
            [['existingPets' => true], [], 'EXCLUDED_EXISTING_PETS_REACTIVE'],
            [['hasChildren' => true], ['aggressionHistory' => true], 'EXCLUDED_CHILD_SAFETY'],
            [['financialReadiness' => 2], ['lifeStage' => 'senior'], 'EXCLUDED_FINANCIAL_READINESS'],
            [['financialReadiness' => 2], ['lifeStage' => 'young'], 'EXCLUDED_FINANCIAL_READINESS'],
            [['financialReadiness' => 2], [], 'EXCLUDED_FINANCIAL_READINESS'],
            [[], ['availability' => 'SoftReserved'], 'PET_UNAVAILABLE'],
            [[], ['availability' => 'Adopted'], 'PET_UNAVAILABLE'],
            [[], ['archived' => true], 'PET_UNAVAILABLE'],
            [[], ['observerCount' => 2], 'PET_PROFILE_INCOMPLETE'],
            [[], ['features' => array_replace($this->pet()->features, ['physical_size' => null])], 'PET_SIZE_NOT_ASSESSED'],
            [[], ['features' => array_replace($this->pet()->features, ['medical_needs' => null])], 'MEDICAL_NEEDS_NOT_ASSESSED'],
            [[], ['features' => array_replace($this->pet()->features, ['energy' => null])], 'PET_PROFILE_INCOMPLETE'],
            [['hasChildren' => true], ['aggressionHistory' => null], 'SAFETY_INFORMATION_INCOMPLETE'],
            [['housing' => 'apartment'], ['highVocalization' => null], 'SAFETY_INFORMATION_INCOMPLETE'],
            [[], ['lifeStage' => null], 'SAFETY_INFORMATION_INCOMPLETE'],
            [['personality' => []], [], 'ADOPTER_PROFILE_INCOMPLETE'],
            [['bfiCompleted' => false], [], 'ADOPTER_PROFILE_INCOMPLETE'],
            [['existingPets' => null], [], 'ADOPTER_PROFILE_INCOMPLETE'],
        ];
        foreach ($cases as [$adopter, $pet, $reason]) {
            $result = $this->matcher->calculateMatch($this->adopter($adopter), $this->pet($pet));
            $this->assertSame($reason, $result->exclusionReason);
            $this->assertFalse($result->eligible);
            $this->assertNull($result->baseDistance);
            $this->assertNull($result->compatibilityScore);
        }
        $pet = $this->pet()->features;
        $pet['medical_needs'] = 2;
        $this->assertTrue($this->matcher->calculateMatch(
            $this->adopter(['financialReadiness' => 2]), $this->pet(['features' => $pet]),
        )->eligible);
        $adult = $this->matcher->calculateMatch($this->adopter(), $this->pet(['lifeStage' => 'adult']));
        $young = $this->matcher->calculateMatch($this->adopter(), $this->pet(['lifeStage' => 'young']));
        $this->assertSame($adult->petVector, $young->petVector);
        $this->assertSame($adult->baseDistance, $young->baseDistance);
    }

    public function test_top_k_and_minimum_observers_must_be_whole_numbers(): void
    {
        foreach (['top_k', 'min_observers'] as $key) {
            $values = $this->config->values;
            $values[$key] = 1.5;
            try {
                new MatchingConfiguration($values);
                $this->fail('Fractional count accepted.');
            } catch (InvalidArgumentException $e) {
                $this->assertStringContainsString($key, $e->getMessage());
            }
        }
    }

    public function test_housing_penalty_is_applied_once_before_percentage_conversion(): void
    {
        foreach (['apartment', 'condo'] as $housing) {
            foreach ([false, true] as $vocal) {
                $result = $this->matcher->calculateMatch(
                    $this->adopter(['housing' => $housing]), $this->pet(['highVocalization' => $vocal]),
                );
                $this->assertTrue($result->eligible);
                $this->assertSame(1.0, $result->penalty);
                $this->assertSame(1.5, $result->adjustedDistance);
                $this->assertEqualsWithDelta(100 * (1 - 1.5 / sqrt(96)), $result->compatibilityScore, 1e-12);
            }
        }
        $features = $this->pet()->features;
        $features['energy'] = 2;
        $vocal = $this->pet(['features' => $features, 'highVocalization' => true]);
        $this->assertSame(1.0, $this->matcher->calculateMatch($this->adopter(['housing' => 'apartment']), $vocal)->penalty);
        $quiet = $this->pet(['features' => $features, 'highVocalization' => false]);
        $this->assertSame(0.0, $this->matcher->calculateMatch($this->adopter(['housing' => 'apartment']), $quiet)->penalty);
        $unknown = $this->pet(['features' => $features, 'highVocalization' => null]);
        $this->assertSame('SAFETY_INFORMATION_INCOMPLETE', $this->matcher->calculateMatch($this->adopter(['housing' => 'apartment']), $unknown)->exclusionReason);
        $this->assertSame(0.0, $this->matcher->calculateMatch($this->adopter(), $vocal)->penalty);
    }
}
