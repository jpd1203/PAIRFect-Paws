<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/** Plain positive decimals that round-trip exactly through DECIMAL(10,3). */
final class PositivePetMeasurement implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ((! is_string($value) && ! is_int($value) && ! is_float($value))
            || (is_float($value) && ! is_finite($value))
            || ! preg_match('/\A[0-9]{1,7}(?:\.[0-9]{1,3})?\z/', (string) $value)
            || (float) $value <= 0) {
            $unit = $attribute === 'weight_kg' ? 'kg' : 'cm';
            $fail("Enter a positive measurement in {$unit} using up to 7 whole-number digits and 3 decimal places. Do not include units, commas or scientific notation.");
        }
    }
}
