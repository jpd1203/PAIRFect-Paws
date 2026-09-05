# Philippine address data

`psgc/` is the local, normalized Philippine Standard Geographic Code (PSGC)
snapshot used by PAIRfect Paws address forms. It is deliberately stored with the
application so registration and adoption applications do not depend on a
third-party API at runtime. Barangays are split into one file per region so a
request never has to decode the complete national dataset into PHP memory.

## Release and provenance

- Release: **PSGC Q2 2026**, as of 30 June 2026.
- Official publisher: [Philippine Statistics Authority (PSA)](https://psa.gov.ph/classification/psgc).
- Official publication: [PSGC 2Q 2026 Publication Datafile](https://psa.gov.ph/system/files/scd/PSGC-2Q-2026-Publication-Datafile.xlsx).
- Q2 change notice: [PSA reference 2026-219](https://psa.gov.ph/classification/psgc/node/1684083815).
- Transport base: [`psgc` Python package 2026.4.13.0](https://pypi.org/project/psgc/2026.4.13.0/), which contains the PSA Q1 2026 hierarchy and all 42,010 current barangays.

The PSA file CDN rejected automated downloads when this snapshot was built.
The generator therefore reads the cited Q1 transport package and applies every
name-only Q2 correction from the official PSA notice by its unchanged PSGC code.
It refuses to generate a file if expected source names, hierarchy links, or PSA
counts do not match.

PSA website data is identified by the publisher as
[CC BY 4.0](https://creativecommons.org/licenses/by/4.0/). The transport package
is MIT licensed.

The generated dataset SHA-256 is
`57402380d2e40149384f7516f6315f692e5779b6266f3f500f595adf5c0a8cb8`.
The `meta.json` SHA-256 is
`3814ca6f49994c2b739f29835bd90f6b1bf3907fb724c39fb76aeb4aced65a98`.
`meta.json` contains the SHA-256 and byte size of every other snapshot file. The
dataset hash is calculated from the sorted `<relative-path> <file-sha256>`
manifest lines; `meta.json` is excluded to avoid a circular checksum.

## Schema

The `psgc/` directory contains:

- `meta.json`: release, source, license, validated counts, the region-to-file
  lookup, and the file checksum manifest.
- `regions.json`: `{code, name}` records.
- `provinces_by_region.json`: official provinces keyed by region PSGC code.
- `direct_localities_by_region.json`: NCR localities, highly urbanized cities, and
  special geographic-area localities that do not have an official province
  parent.
- `localities_by_province.json`: cities and municipalities keyed by official province
  PSGC code. Each locality contains `{code, name, type}`.
- `barangays_by_region/01.json` through the 18 present region prefixes: each
  regional file is a map of city/municipality PSGC code to its barangay list.
  Each barangay contains `{code, name}` and an optional `status`.

Manila's 14 PSGC sub-municipalities are not exposed as another form level. Their
barangays are aggregated under `City of Manila`, matching the required
Region -> Province -> City/Municipality -> Barangay form flow.

The snapshot contains 18 regions, 82 official provinces, 149 cities, 1,493
municipalities, and 42,010 barangays. Forty-three localities are represented as
direct-to-region records rather than being attached to fabricated provinces.

ZIP codes are not inferred in this data file. They are not part of PSGC and are
not one-to-one with a city or barangay, so `zip_code` remains a user-entered form
field.

## Regeneration

After obtaining the `psgc` 2026.4.13.0 source distribution and extracting it,
run:

```text
php scripts/build_psgc_snapshot.php path/to/psgc/data/core resources/data/psgc
```

For a later PSA release, update the input source, release metadata, corrections,
and asserted counts in the generator before replacing the checked-in snapshot.
