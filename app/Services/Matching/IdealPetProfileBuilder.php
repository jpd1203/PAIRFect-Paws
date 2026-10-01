<?php

namespace App\Services\Matching;

use InvalidArgumentException;

final class IdealPetProfileBuilder
{
    public function build(array $personality): array
    {
        foreach (['extraversion', 'conscientiousness', 'neuroticism', 'openness'] as $dimension) {
            if (! MatchingConfiguration::validValue($personality[$dimension] ?? null)) {
                throw new InvalidArgumentException('ADOPTER_PROFILE_INCOMPLETE');
            }
        }
        $e = (float) $personality['extraversion'];
        $c = (float) $personality['conscientiousness'];
        $n = (float) $personality['neuroticism'];
        $o = (float) $personality['openness'];

        return [
            'energy' => $e, 'trainability' => $c,
            'independence' => max(1.0, min(5.0, ($c + (6.0 - $e)) / 2.0)),
            'temperament' => max(1.0, min(5.0, 6.0 - $n)),
            'physical_size' => max(1.0, min(5.0, ($o + $e) / 2.0)),
            'medical_needs' => max(1.0, min(5.0, ($c + (6.0 - $n)) / 2.0)),
        ];
    }
}
