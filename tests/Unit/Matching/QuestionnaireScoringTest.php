<?php

namespace Tests\Unit\Matching;

use App\Services\Matching\BehaviorScorer;
use App\Services\Matching\BfiScorer;
use App\Services\Matching\MatchingConfiguration;
use App\Services\Matching\PetProfileAggregator;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class QuestionnaireScoringTest extends TestCase
{
    private MatchingConfiguration $config;

    protected function setUp(): void
    {
        $this->config = new MatchingConfiguration(require dirname(__DIR__, 3).'/config/matching.php');
    }

    private function bfiAnswers(int $value = 3): array
    {
        return array_fill_keys(array_merge(...array_values($this->config->values['bfi']['dimensions'])), $value);
    }

    private function behaviorAnswers(string $species, int $value = 2): array
    {
        return array_map(fn ($items) => array_fill_keys(array_keys($items), $value), $this->config->values['items'][$species]);
    }

    public function test_twenty_item_bfi_reverse_keying_and_precision(): void
    {
        $answers = $this->bfiAnswers(1);
        $this->assertCount(20, $answers);
        $means = (new BfiScorer($this->config))->score($answers);
        $this->assertSame(['extraversion' => 3.0, 'conscientiousness' => 3.0, 'neuroticism' => 3.0, 'openness' => 3.0], $means);
        $answers['EX1'] = 2;
        $this->assertEqualsWithDelta(19 / 6, (new BfiScorer($this->config))->score($answers)['extraversion'], 1e-12);
        $this->assertSame('Has few artistic interests', $this->config->values['bfi']['prompts']['O4']);
    }

    public function test_each_reverse_key_is_configuration_driven(): void
    {
        $answers = $this->bfiAnswers(3);
        foreach ($this->config->values['bfi']['reverse'] as $key) {
            $answers[$key] = 1;
        }
        $result = (new BfiScorer($this->config))->score($answers);
        $this->assertSame(4.0, $result['extraversion']);
        $this->assertSame(4.0, $result['neuroticism']);
        $changed = $this->config->values;
        $changed['bfi']['reverse'] = [];
        $this->assertSame(2.0, (new BfiScorer(new MatchingConfiguration($changed)))->score($answers)['extraversion']);
    }

    public function test_bfi_missing_and_tampered_answers_are_rejected(): void
    {
        foreach ([null, 0, 6, 2.5, true, 'bad'] as $bad) {
            $answers = $this->bfiAnswers();
            $answers['EX1'] = $bad;
            try {
                (new BfiScorer($this->config))->score($answers);
                $this->fail('Invalid BFI answer accepted.');
            } catch (InvalidArgumentException $e) {
                $this->assertNotEmpty($e->getMessage());
            }
        }
        $this->expectException(InvalidArgumentException::class);
        (new BfiScorer($this->config))->score([]);
    }

    public function test_dog_and_cat_behavior_means_reverse_items_and_temperament(): void
    {
        $scorer = new BehaviorScorer($this->config);
        $dog = $this->behaviorAnswers('dog', 0);
        $dog['energy'] = ['E1' => 1, 'E2' => 2, 'E3' => 4];
        $dog['stranger_fear'] = array_fill_keys(array_keys($dog['stranger_fear']), 4);
        $score = $scorer->score('Dog', $dog);
        $this->assertEqualsWithDelta(7 / 3, $score['energy'], 1e-12);
        $this->assertSame(1.5, $score['trainability']);
        $this->assertSame(4.0, $score['independence']);
        $this->assertSame(2.0, $score['temperament']);

        $cat = $this->behaviorAnswers('cat', 1);
        $cat['attention_seeking'] = array_fill_keys(array_keys($cat['attention_seeking']), 3);
        $score = $scorer->score('Cat', $cat);
        $this->assertSame(1.0, $score['energy']);
        $this->assertSame(1.0, $score['trainability']);
        $this->assertSame(2.0, $score['independence']);
        $this->assertSame(1.0, $score['temperament']);
    }

    public function test_missing_subscales_never_become_zero_and_eighty_percent_threshold_is_respected(): void
    {
        $scorer = new BehaviorScorer($this->config);
        $dog = $this->behaviorAnswers('dog');
        unset($dog['trainability']['T1']); // 7/8 is sufficient.
        $this->assertNotNull($scorer->score('Dog', $dog)['trainability']);
        unset($dog['trainability']['T2']); // 6/8 is insufficient.
        $this->assertNull($scorer->score('Dog', $dog)['trainability']);
        $this->assertNull($scorer->score('Dog', [])['energy']);
        $dog['stranger_fear'] = [];
        $this->assertNull($scorer->score('Dog', $dog)['temperament']);
    }

    public function test_independence_flag_for_both_species_and_tamper_validation(): void
    {
        $changed = $this->config->values;
        $changed['independence']['reverse_attachment'] = false;
        foreach (['dog', 'cat'] as $species) {
            $answers = $this->behaviorAnswers($species, 1);
            $this->assertSame(3.0, (new BehaviorScorer($this->config))->score($species, $answers)['independence']);
            $this->assertSame(1.0, (new BehaviorScorer(new MatchingConfiguration($changed)))->score($species, $answers)['independence']);
        }
        $answers = $this->behaviorAnswers('dog');
        $answers['trainability']['T5'] = 5;
        $this->expectException(InvalidArgumentException::class);
        (new BehaviorScorer($this->config))->score('Dog', $answers);
    }

    public function test_aggregation_requires_distinct_observers_and_preserves_fractional_scores(): void
    {
        $aggregator = new PetProfileAggregator($this->config, new BehaviorScorer($this->config));
        $records = [];
        foreach ([1, 2, 3] as $observer) {
            $responses = $this->behaviorAnswers('dog', $observer - 1);
            $records[] = ['id' => $observer, 'observer_id' => $observer, 'responses' => $responses];
        }
        $this->assertTrue($aggregator->aggregate('Dog', $records)['complete']);
        $this->assertFalse($aggregator->aggregate('Dog', array_slice($records, 0, 2))['complete']);
        $duplicates = array_map(fn ($r) => array_replace($r, ['observer_id' => 1]), $records);
        $this->assertSame(1, $aggregator->aggregate('Dog', $duplicates)['observer_count']);
        $this->assertFalse($aggregator->aggregate('Dog', $duplicates)['complete']);
        $records[0]['responses']['energy']['E1'] = 1;
        $result = $aggregator->aggregate('Dog', $records);
        $this->assertEqualsWithDelta(2 + 1 / 9, $result['features']['energy'], 1e-12);
        $records[0]['responses']['energy'] = [];
        $this->assertSame(2.5, $aggregator->aggregate('Dog', $records)['features']['energy']);
        foreach ($records as &$record) {
            $record['responses']['energy'] = [];
        }
        $this->assertNull($aggregator->aggregate('Dog', $records)['features']['energy']);
    }
}
