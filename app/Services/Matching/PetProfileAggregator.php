<?php

namespace App\Services\Matching;

final class PetProfileAggregator
{
    public function __construct(private MatchingConfiguration $config, private BehaviorScorer $scorer) {}

    /**
     * Latest assessment per distinct observer; no database calls or intermediate rounding.
     *
     * @param  array<int, array{observer_id: ?int, responses: array, id?: int, recorded_at?: string}>  $records
     */
    public function aggregate(string $species, array $records): array
    {
        usort($records, fn ($a, $b) => [
            $a['recorded_at'] ?? '', $a['id'] ?? 0,
        ] <=> [$b['recorded_at'] ?? '', $b['id'] ?? 0]);
        $observers = [];
        foreach ($records as $record) {
            if ($record['observer_id'] !== null) {
                $observers[$record['observer_id']] = $this->scorer->score($species, $record['responses']);
            }
        }
        $features = [];
        foreach (['energy', 'trainability', 'independence', 'temperament'] as $feature) {
            $values = array_values(array_filter(array_column($observers, $feature), fn ($value) => $value !== null));
            $features[$feature] = $values ? array_sum($values) / count($values) + 1.0 : null;
        }

        return [
            'observer_count' => count($observers),
            'features' => $features,
            'complete' => count($observers) >= $this->config->values['min_observers'] && ! in_array(null, $features, true),
        ];
    }
}
