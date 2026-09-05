<?php

namespace App\Services;

use Illuminate\Validation\ValidationException;
use RuntimeException;

class PhilippineLocationService
{
    public const DIRECT_REGION_VALUE = '__direct__';

    /** @var array<string, array> */
    private array $loadedFiles = [];

    public static function validationRules(): array
    {
        return [
            'region_code' => ['required', 'string', 'max:10'],
            'province_code' => ['nullable', 'string', 'max:10'],
            'city_municipality_code' => ['required', 'string', 'max:10'],
            'barangay_code' => ['required', 'string', 'max:10'],
            'street_address' => ['required', 'string', 'max:1000'],
            'zip_code' => ['required', 'string', 'regex:/^\d{4}$/'],
        ];
    }

    public function metadata(): array
    {
        return $this->readJson('meta.json');
    }

    public function regions(): array
    {
        return $this->sorted($this->readJson('regions.json'));
    }

    public function provinces(string $regionCode): array
    {
        return $this->sorted($this->readJson('provinces_by_region.json')[$regionCode] ?? []);
    }

    public function directLocalities(string $regionCode): array
    {
        return $this->sorted($this->readJson('direct_localities_by_region.json')[$regionCode] ?? []);
    }

    public function localities(string $regionCode, ?string $provinceCode): array
    {
        if ($provinceCode === null || $provinceCode === '' || $provinceCode === self::DIRECT_REGION_VALUE) {
            return $this->directLocalities($regionCode);
        }

        return $this->sorted($this->readJson('localities_by_province.json')[$provinceCode] ?? []);
    }

    public function barangays(string $localityCode): array
    {
        $regionPrefix = substr($localityCode, 0, 2);
        if (! preg_match('/^\d{2}$/', $regionPrefix)) {
            return [];
        }

        return $this->sorted(
            $this->readJson("barangays_by_region/{$regionPrefix}.json")[$localityCode] ?? [],
        );
    }

    /**
     * Resolve a submitted PSGC-code hierarchy to the canonical names persisted by the app.
     * The client-provided labels are intentionally ignored.
     *
     * @return array{region:string,province:?string,city_municipality:string,barangay:string,street_address:string,zip_code:string}
     */
    public function resolveAddress(array $input): array
    {
        $regionCode = trim((string) ($input['region_code'] ?? ''));
        $provinceCode = trim((string) ($input['province_code'] ?? ''));
        $localityCode = trim((string) ($input['city_municipality_code'] ?? ''));
        $barangayCode = trim((string) ($input['barangay_code'] ?? ''));

        $region = $this->findByCode($this->regions(), $regionCode);
        if ($region === null) {
            $this->invalid('region_code', 'Please select a valid Philippine region.');
        }

        $province = null;
        $usesDirectLocality = $provinceCode === '' || $provinceCode === self::DIRECT_REGION_VALUE;
        if (! $usesDirectLocality) {
            $province = $this->findByCode($this->provinces($regionCode), $provinceCode);
            if ($province === null) {
                $this->invalid('province_code', 'Please select a province that belongs to the selected region.');
            }
        }

        $locality = $this->findByCode(
            $this->localities($regionCode, $usesDirectLocality ? self::DIRECT_REGION_VALUE : $provinceCode),
            $localityCode,
        );
        if ($locality === null) {
            $this->invalid(
                'city_municipality_code',
                $usesDirectLocality
                    ? 'Please select a city or municipality directly administered by the selected region.'
                    : 'Please select a city or municipality that belongs to the selected province.',
            );
        }

        $barangay = $this->findByCode($this->barangays($localityCode), $barangayCode);
        if ($barangay === null) {
            $this->invalid('barangay_code', 'Please select a barangay that belongs to the selected city or municipality.');
        }

        return [
            'region' => $region['name'],
            'province' => $province['name'] ?? null,
            'city_municipality' => $locality['name'],
            'barangay' => $barangay['name'],
            'street_address' => trim((string) ($input['street_address'] ?? '')),
            'zip_code' => trim((string) ($input['zip_code'] ?? '')),
        ];
    }

    private function readJson(string $relativePath): array
    {
        if (isset($this->loadedFiles[$relativePath])) {
            return $this->loadedFiles[$relativePath];
        }

        $path = resource_path('data/psgc/'.$relativePath);
        if (! is_file($path)) {
            throw new RuntimeException("The local PSGC dataset file is missing: {$relativePath}");
        }

        $contents = file_get_contents($path);
        if ($contents === false) {
            throw new RuntimeException("The local PSGC dataset file could not be read: {$relativePath}");
        }

        try {
            $decoded = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            throw new RuntimeException(
                "The local PSGC dataset file is invalid: {$relativePath}",
                previous: $exception,
            );
        }
        if (! is_array($decoded)) {
            throw new RuntimeException("The local PSGC dataset file is invalid: {$relativePath}");
        }

        return $this->loadedFiles[$relativePath] = $decoded;
    }

    private function findByCode(array $items, string $code): ?array
    {
        foreach ($items as $item) {
            if ((string) ($item['code'] ?? '') === $code) {
                return $item;
            }
        }

        return null;
    }

    private function sorted(array $items): array
    {
        usort($items, fn (array $left, array $right): int => strnatcasecmp(
            (string) ($left['name'] ?? ''),
            (string) ($right['name'] ?? ''),
        ));

        return array_values($items);
    }

    private function invalid(string $field, string $message): never
    {
        throw ValidationException::withMessages([$field => $message]);
    }
}
