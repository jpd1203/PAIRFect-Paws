<?php

declare(strict_types=1);

/**
 * Build the compact PSGC snapshot consumed by PAIRfect Paws.
 *
 * Usage:
 *   php scripts/build_psgc_snapshot.php <psgc-package-core-directory> [output-directory]
 *
 * The input directory must contain the regions.json, provinces.json, cities.json,
 * and barangays.json files bundled with psgc 2026.4.13.0. That package contains
 * PSA Q1 2026 data. The name-only Q2 2026 changes published by PSA are applied
 * below; PSGC codes and parent relationships did not change in that release.
 */
ini_set('memory_limit', '512M');

$sourceDirectory = $argv[1] ?? null;
$outputDirectory = $argv[2] ?? dirname(__DIR__).DIRECTORY_SEPARATOR.'resources'.DIRECTORY_SEPARATOR.'data'.DIRECTORY_SEPARATOR.'psgc';

if (! is_string($sourceDirectory) || $sourceDirectory === '') {
    fwrite(STDERR, "Missing PSGC package core directory.\n");
    exit(1);
}

/** @return list<array<string, mixed>> */
function readCollection(string $path): array
{
    if (! is_file($path)) {
        throw new RuntimeException("Required source file not found: {$path}");
    }

    $contents = file_get_contents($path);

    if ($contents === false) {
        throw new RuntimeException("Unable to read source file: {$path}");
    }

    $decoded = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);

    if (! is_array($decoded) || ! array_is_list($decoded)) {
        throw new RuntimeException("Expected a JSON list in: {$path}");
    }

    return $decoded;
}

/** @param array<string, list<array<string, mixed>>> $groups */
function sortGroups(array &$groups): void
{
    ksort($groups, SORT_STRING);

    foreach ($groups as &$items) {
        usort($items, static function (array $left, array $right): int {
            $byName = strnatcasecmp((string) $left['name'], (string) $right['name']);

            return $byName !== 0 ? $byName : strcmp((string) $left['code'], (string) $right['code']);
        });
    }
    unset($items);
}

/**
 * Write deterministic, compact JSON and return its manifest attributes.
 *
 * @return array{sha256:string,bytes:int}
 */
function writeJsonDocument(string $path, mixed $payload): array
{
    $directory = dirname($path);

    if (! is_dir($directory) && ! mkdir($directory, 0777, true) && ! is_dir($directory)) {
        throw new RuntimeException("Unable to create snapshot directory: {$directory}");
    }

    $encoded = json_encode(
        $payload,
        JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR,
    ).PHP_EOL;

    if (file_put_contents($path, $encoded, LOCK_EX) === false) {
        throw new RuntimeException("Unable to write snapshot file: {$path}");
    }

    return [
        'sha256' => hash('sha256', $encoded),
        'bytes' => strlen($encoded),
    ];
}

/**
 * Apply a name-only PSA release correction and fail if the base data is not the
 * exact release this generator expects.
 *
 * @param  list<array<string, mixed>>  $records
 */
function renameByCode(array &$records, string $code, string $oldName, string $newName): void
{
    foreach ($records as &$record) {
        if (($record['psgc_code'] ?? null) !== $code) {
            continue;
        }

        if (($record['name'] ?? null) !== $oldName) {
            throw new RuntimeException(
                "Unexpected source name for {$code}: expected '{$oldName}', found '".($record['name'] ?? '')."'."
            );
        }

        $record['name'] = $newName;

        return;
    }
    unset($record);

    throw new RuntimeException("PSGC code not found while applying Q2 2026 correction: {$code}");
}

try {
    $sourceDirectory = rtrim($sourceDirectory, '\\/');
    $regions = readCollection($sourceDirectory.DIRECTORY_SEPARATOR.'regions.json');
    $provinces = readCollection($sourceDirectory.DIRECTORY_SEPARATOR.'provinces.json');
    $localities = readCollection($sourceDirectory.DIRECTORY_SEPARATOR.'cities.json');
    $barangays = readCollection($sourceDirectory.DIRECTORY_SEPARATOR.'barangays.json');

    // PSA Q2 2026: one municipality and one barangay were renamed.
    renameByCode($localities, '1102324000', 'San Isidro', 'Sawata');
    renameByCode($barangays, '1102324013', 'Sawata', 'Poblacion');

    // PSA Q2 2026: one municipality and fourteen barangay names were corrected.
    renameByCode($localities, '1004217000', 'Don Victoriano Chiongbian', 'Don Victoriano');

    $barangayCorrections = [
        '0300809015' => ['Parang Parang', 'Parang-Parang'],
        '1001307002' => ['Baborawon', 'Barorawon'],
        '1001307007' => ['Maca-opao', 'Macaopao'],
        '1001309002' => ['Balukbukan', 'Balocbocan'],
        '1001312018' => ['Indalaza', 'Indalasa'],
        '1001312021' => ['Kabalabag', 'Kibalabag'],
        '1001317019' => ['Merangerang', 'Merangeran'],
        '1001317034' => ['Santa Cruz', 'Sta. Cruz'],
        '1001319002' => ['Culasi', 'Kulasi'],
        '1001320016' => ['Santo Niño', 'Sto. Niño'],
        '1001321022' => ['Nabago', 'Nabag-o'],
        '1001321011' => ['Kahapunan', 'Kahaponan'],
        '1001321017' => ['Lurogan', 'Lurugan'],
        '1001313009' => ['Santa Ines', 'Sta. Ines'],
    ];

    foreach ($barangayCorrections as $code => [$oldName, $newName]) {
        renameByCode($barangays, (string) $code, $oldName, $newName);
    }

    $regionItems = [];
    $regionCodes = [];
    $provincesByRegion = [];
    $directLocalitiesByRegion = [];

    foreach ($regions as $region) {
        $code = (string) ($region['psgc_code'] ?? '');
        $name = (string) ($region['name'] ?? '');

        if ($code === '' || $name === '' || isset($regionCodes[$code])) {
            throw new RuntimeException("Invalid or duplicate region record: {$code}");
        }

        $regionCodes[$code] = true;
        $regionItems[] = ['code' => $code, 'name' => $name];
        $provincesByRegion[$code] = [];
        $directLocalitiesByRegion[$code] = [];
    }

    $provinceCodes = [];
    $pseudoProvinceCodes = [];
    $localitiesByProvince = [];

    foreach ($provinces as $province) {
        $code = (string) ($province['psgc_code'] ?? '');
        $name = (string) ($province['name'] ?? '');
        $regionCode = (string) ($province['region_code'] ?? '');

        if ($code === '' || $name === '' || ! isset($regionCodes[$regionCode])) {
            throw new RuntimeException("Invalid province or package-group record: {$code}");
        }

        if (($province['is_pseudo'] ?? false) === true) {
            $pseudoProvinceCodes[$code] = true;

            continue;
        }

        if (isset($provinceCodes[$code])) {
            throw new RuntimeException("Duplicate official province code: {$code}");
        }

        $provinceCodes[$code] = true;
        $provincesByRegion[$regionCode][] = ['code' => $code, 'name' => $name];
        $localitiesByProvince[$code] = [];
    }

    $subMunicipalityParents = [];
    $sourceLocalityCodes = [];

    foreach ($localities as $locality) {
        $code = (string) ($locality['psgc_code'] ?? '');

        if ($code === '' || isset($sourceLocalityCodes[$code])) {
            throw new RuntimeException("Invalid or duplicate locality record: {$code}");
        }

        $sourceLocalityCodes[$code] = true;

        if (($locality['geographic_level'] ?? null) === 'SubMun') {
            $subMunicipalityParents[$code] = substr($code, 0, 5).'00000';
        }
    }

    $outputLocalityCodes = [];
    $localityRegionCodes = [];
    $cityCount = 0;
    $municipalityCount = 0;

    foreach ($localities as $locality) {
        $level = (string) ($locality['geographic_level'] ?? '');

        if ($level === 'SubMun') {
            continue;
        }

        $code = (string) ($locality['psgc_code'] ?? '');
        $name = (string) ($locality['name'] ?? '');
        $regionCode = (string) ($locality['region_code'] ?? '');
        $provinceCode = (string) ($locality['province_code'] ?? '');

        if ($name === '' || ! isset($regionCodes[$regionCode])) {
            throw new RuntimeException("Invalid city/municipality record: {$code}");
        }

        $type = match ($level) {
            'City' => 'city',
            'Mun' => 'municipality',
            default => throw new RuntimeException("Unsupported locality level '{$level}' for {$code}"),
        };

        $cityCount += $type === 'city' ? 1 : 0;
        $municipalityCount += $type === 'municipality' ? 1 : 0;
        $outputLocalityCodes[$code] = true;
        $localityRegionCodes[$code] = $regionCode;
        $item = ['code' => $code, 'name' => $name, 'type' => $type];

        if (isset($pseudoProvinceCodes[$provinceCode])) {
            $directLocalitiesByRegion[$regionCode][] = $item;
        } elseif (isset($provinceCodes[$provinceCode])) {
            $localitiesByProvince[$provinceCode][] = $item;
        } else {
            throw new RuntimeException("Locality {$code} has an unknown province/package-group {$provinceCode}");
        }
    }

    foreach ($subMunicipalityParents as $subMunicipalityCode => $parentCode) {
        if (! isset($outputLocalityCodes[$parentCode])) {
            throw new RuntimeException(
                "Sub-municipality {$subMunicipalityCode} cannot be assigned to parent city {$parentCode}"
            );
        }
    }

    $barangaysByLocality = [];
    $barangayCodes = [];

    foreach (array_keys($outputLocalityCodes) as $localityCode) {
        $barangaysByLocality[$localityCode] = [];
    }

    foreach ($barangays as $barangay) {
        $code = (string) ($barangay['psgc_code'] ?? '');
        $name = (string) ($barangay['name'] ?? '');
        $sourceCityCode = (string) ($barangay['city_code'] ?? '');
        $regionCode = (string) ($barangay['region_code'] ?? '');
        $localityCode = $subMunicipalityParents[$sourceCityCode] ?? $sourceCityCode;

        if ($code === '' || $name === '' || isset($barangayCodes[$code])) {
            throw new RuntimeException("Invalid or duplicate barangay record: {$code}");
        }

        if (! isset($barangaysByLocality[$localityCode])) {
            throw new RuntimeException("Barangay {$code} has an unknown locality {$sourceCityCode}");
        }

        if (($localityRegionCodes[$localityCode] ?? null) !== $regionCode) {
            throw new RuntimeException("Barangay {$code} has a region that conflicts with locality {$localityCode}");
        }

        $barangayCodes[$code] = true;
        $item = ['code' => $code, 'name' => $name];
        $status = trim((string) ($barangay['status'] ?? ''));

        if ($status !== '') {
            $item['status'] = $status;
        }

        $barangaysByLocality[$localityCode][] = $item;
    }

    usort($regionItems, static function (array $left, array $right): int {
        return strnatcasecmp((string) $left['name'], (string) $right['name']);
    });
    sortGroups($provincesByRegion);
    sortGroups($directLocalitiesByRegion);
    sortGroups($localitiesByProvince);
    sortGroups($barangaysByLocality);

    $directLocalityCount = array_sum(array_map('count', $directLocalitiesByRegion));
    $provincialLocalityCount = array_sum(array_map('count', $localitiesByProvince));

    $expectedCounts = [
        'regions' => 18,
        'provinces' => 82,
        'cities' => 149,
        'municipalities' => 1493,
        'localities' => 1642,
        'barangays' => 42010,
    ];

    $actualCounts = [
        'regions' => count($regionItems),
        'provinces' => count($provinceCodes),
        'cities' => $cityCount,
        'municipalities' => $municipalityCount,
        'localities' => count($outputLocalityCodes),
        'barangays' => count($barangayCodes),
    ];

    if ($actualCounts !== $expectedCounts) {
        throw new RuntimeException(
            'Snapshot counts do not match PSA Q2 2026: '.json_encode($actualCounts, JSON_THROW_ON_ERROR)
        );
    }

    foreach ($barangaysByLocality as $localityCode => $items) {
        if ($items === []) {
            throw new RuntimeException("Locality {$localityCode} has no barangays");
        }
    }

    $barangaysByRegion = [];
    $barangayFilesByRegion = [];
    $regionCodeByPrefix = [];

    foreach (array_keys($regionCodes) as $regionCode) {
        $regionCode = (string) $regionCode;

        if (preg_match('/^\d{10}$/', $regionCode) !== 1) {
            throw new RuntimeException("Region has an invalid 10-digit PSGC code: {$regionCode}");
        }

        $prefix = substr($regionCode, 0, 2);

        if (isset($regionCodeByPrefix[$prefix])) {
            throw new RuntimeException("Region prefix {$prefix} is not unique");
        }

        $regionCodeByPrefix[$prefix] = $regionCode;
        $barangaysByRegion[$prefix] = [];
        $barangayFilesByRegion[$regionCode] = "barangays_by_region/{$prefix}.json";
    }

    foreach ($barangaysByLocality as $localityCode => $items) {
        $regionCode = $localityRegionCodes[$localityCode] ?? null;

        if (! is_string($regionCode)) {
            throw new RuntimeException("Locality {$localityCode} has no region mapping");
        }

        $prefix = substr($regionCode, 0, 2);

        if (substr((string) $localityCode, 0, 2) !== $prefix) {
            throw new RuntimeException("Locality {$localityCode} does not share its region PSGC prefix {$prefix}");
        }

        $barangaysByRegion[$prefix][(string) $localityCode] = $items;
    }

    ksort($barangaysByRegion, SORT_STRING);
    ksort($barangayFilesByRegion, SORT_STRING);

    foreach ($barangaysByRegion as &$regionalBarangays) {
        ksort($regionalBarangays, SORT_STRING);
    }
    unset($regionalBarangays);

    $outputDirectory = rtrim($outputDirectory, '\\/');

    if ($outputDirectory === '' || (file_exists($outputDirectory) && ! is_dir($outputDirectory))) {
        throw new RuntimeException("Invalid snapshot output directory: {$outputDirectory}");
    }

    if (! is_dir($outputDirectory) && ! mkdir($outputDirectory, 0777, true) && ! is_dir($outputDirectory)) {
        throw new RuntimeException("Unable to create snapshot output directory: {$outputDirectory}");
    }

    $fileManifest = [];
    $writeDocument = static function (
        string $relativePath,
        mixed $payload,
        int $records,
        ?int $groups = null,
    ) use ($outputDirectory, &$fileManifest): void {
        $path = $outputDirectory.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
        $attributes = writeJsonDocument($path, $payload);
        $entry = [
            'sha256' => $attributes['sha256'],
            'bytes' => $attributes['bytes'],
            'records' => $records,
        ];

        if ($groups !== null) {
            $entry['groups'] = $groups;
        }

        $fileManifest[$relativePath] = $entry;
    };

    $writeDocument('regions.json', $regionItems, count($regionItems));
    $writeDocument(
        'provinces_by_region.json',
        $provincesByRegion,
        count($provinceCodes),
        count($provincesByRegion),
    );
    $writeDocument(
        'direct_localities_by_region.json',
        $directLocalitiesByRegion,
        $directLocalityCount,
        count($directLocalitiesByRegion),
    );
    $writeDocument(
        'localities_by_province.json',
        $localitiesByProvince,
        $provincialLocalityCount,
        count($localitiesByProvince),
    );

    foreach ($barangaysByRegion as $prefix => $regionalBarangays) {
        $writeDocument(
            "barangays_by_region/{$prefix}.json",
            $regionalBarangays,
            array_sum(array_map('count', $regionalBarangays)),
            count($regionalBarangays),
        );
    }

    ksort($fileManifest, SORT_STRING);
    $datasetHashLines = [];

    foreach ($fileManifest as $relativePath => $attributes) {
        $datasetHashLines[] = $relativePath.' '.$attributes['sha256'];
    }

    $datasetSha256 = hash('sha256', implode("\n", $datasetHashLines)."\n");
    $counts = $actualCounts + [
        'direct_localities' => $directLocalityCount,
        'provincial_localities' => $provincialLocalityCount,
    ];
    $metadata = [
        'schema_version' => 2,
        'release' => 'PSGC Q2 2026',
        'as_of' => '2026-06-30',
        'retrieved_at' => '2026-08-14',
        'source' => 'Philippine Statistics Authority (PSA)',
        'source_url' => 'https://psa.gov.ph/classification/psgc',
        'publication_url' => 'https://psa.gov.ph/system/files/scd/PSGC-2Q-2026-Publication-Datafile.xlsx',
        'update_notice_url' => 'https://psa.gov.ph/classification/psgc/node/1684083815',
        'transport' => [
            'name' => 'psgc Python package 2026.4.13.0',
            'url' => 'https://pypi.org/project/psgc/2026.4.13.0/',
            'source_release' => 'PSGC Q1 2026',
            'source_archive_sha256' => 'c5d4a9b5dd0b3a35260a57e167d1bc8577693c0d3d65ace1c2db488e7c535958',
        ],
        'license' => [
            'psa_data' => 'CC BY 4.0',
            'transport_package' => 'MIT',
        ],
        'notes' => [
            'The Q1 2026 hierarchy was transported from the cited package because the PSA spreadsheet CDN rejected automated downloads.',
            'All name-only Q2 2026 changes listed in the official PSA update notice are applied by PSGC code.',
            'NCR, highly urbanized cities, and special geographic areas remain direct-to-region localities instead of being assigned to fabricated provinces.',
            'Manila sub-municipality barangays are aggregated under City of Manila for the Region > Province > City/Municipality > Barangay form flow.',
            'ZIP codes are intentionally not inferred because they are not part of PSGC and are not one-to-one with cities or barangays.',
            'Barangays are split by two-digit region prefix so request-time decoding remains within constrained PHP memory limits.',
        ],
        'counts' => $counts,
        'barangay_files_by_region' => $barangayFilesByRegion,
        'manifest' => [
            'hash_algorithm' => 'sha256',
            'dataset_sha256' => $datasetSha256,
            'dataset_hash_format' => '<relative-path> <file-sha256>\\n, sorted by relative path; meta.json excluded',
            'total_bytes' => array_sum(array_column($fileManifest, 'bytes')),
            'files' => $fileManifest,
        ],
    ];

    $metaAttributes = writeJsonDocument($outputDirectory.DIRECTORY_SEPARATOR.'meta.json', $metadata);

    fwrite(
        STDOUT,
        "Wrote split snapshot to {$outputDirectory} (".(count($fileManifest) + 1)." files)\n".
        "Dataset SHA-256: {$datasetSha256}\n".
        "meta.json SHA-256: {$metaAttributes['sha256']}\n".
        'Counts: '.json_encode($counts, JSON_THROW_ON_ERROR)."\n"
    );
} catch (Throwable $exception) {
    fwrite(STDERR, $exception->getMessage()."\n");
    exit(1);
}
