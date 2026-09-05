<?php

namespace App\Support;

final class PhilippineAddress
{
    public const COMPONENTS = [
        'region',
        'province',
        'city_municipality',
        'barangay',
        'street_address',
        'zip_code',
    ];

    /**
     * Extract and normalize address components from an attributes array.
     *
     * @return array<string, string|null>
     */
    public static function fromAttributes(array $attributes, string $prefix = ''): array
    {
        $prefix = $prefix === '' ? '' : rtrim($prefix, '_').'_';

        $components = [];

        foreach (self::COMPONENTS as $component) {
            $components[$component] = $attributes[$prefix.$component] ?? null;
        }

        return self::normalize($components);
    }

    /**
     * Normalize a structured Philippine address into a predictable shape.
     *
     * @return array<string, string|null>
     */
    public static function normalize(array $components): array
    {
        $normalized = [];

        foreach (self::COMPONENTS as $component) {
            $value = $components[$component] ?? null;

            if (! is_scalar($value)) {
                $normalized[$component] = null;

                continue;
            }

            $value = trim((string) $value);
            $value = preg_replace('/\s+/u', ' ', $value) ?? $value;
            $normalized[$component] = $value === '' ? null : $value;
        }

        return $normalized;
    }

    public static function format(array $components): ?string
    {
        $components = self::normalize($components);

        $barangay = $components['barangay'];
        if ($barangay !== null && ! preg_match('/^(?:barangay|brgy\.?)(?:\s|$)/iu', $barangay)) {
            $barangay = 'Barangay '.$barangay;
        }

        $parts = array_filter([
            $components['street_address'],
            $barangay,
            $components['city_municipality'],
            $components['province'],
            $components['region'],
            $components['zip_code'],
        ], static fn (?string $part): bool => $part !== null);

        return $parts === [] ? null : implode(', ', $parts);
    }

    /**
     * The mandatory OCR identity anchors. PSGC divisions are checked separately
     * because many Philippine IDs omit barangay, province, region, or ZIP.
     */
    public static function ocrCore(array $components): ?string
    {
        $components = self::normalize($components);
        $parts = array_filter([
            $components['street_address'],
            $components['city_municipality'],
        ], static fn (?string $part): bool => $part !== null);

        return count($parts) === 2 ? implode(', ', $parts) : null;
    }

    /**
     * Return the canonical address and its individual fields for OCR matching.
     *
     * @return array{address: string|null, address_components: array<string, string|null>}
     */
    public static function ocrPayload(array $components): array
    {
        $components = self::normalize($components);

        return [
            'address' => self::format($components),
            'address_components' => $components,
        ];
    }
}
