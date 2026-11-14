# Current measured-weight pet size

Current rule: weight-v2. Species and recorded CURRENT weight determine physical size,
independently of age/life stage. This is neither expected adult size nor a healthy-weight
assessment. Breed, height and veterinary exceptions do not override these categories.

| Size | Dogs, kg | Cats, kg | Existing KNN level |
| --- | --- | --- | --- |
| Extra Small | 0 < w < 6.5 | 0 < w < 3 | 1 |
| Small | 6.5 <= w < 9 | 3 <= w < 4 | 2 |
| Medium | 9 <= w < 15 | 4 <= w < 5 | 3 |
| Large | 15 <= w < 30 | 5 <= w < 6.5 | 4 |
| Extra Large | w >= 30 | w >= 6.5 | 5 |

PetSizeClassifier remains the sole authority, comparing integer grams with the existing
positive DECIMAL(10,3) input rules. Invalid/missing weight yields no size; unknown age
does not. Dog 6 months/5kg is Extra Small; dog 18 months/12kg is Medium; cat 8 months/4kg
is Medium; dog 36 months/20kg is Large. Age is never inferred or altered.

Life stage and all behavioral, medical, household and availability safeguards remain
separate. Unknown life stage can still prevent matching for safety-information reasons.
Forms retain age_years/age_months, canonical stored months, kg/cm units and read-only
derived size. Model saves and version-checked staff updates use the same classifier.
New/nonlegacy pets recalculate on changed measurements/species/age. Persisted
legacy_unclassified cannot be promoted by ordinary edits or forged metadata.

## Version transition and history

The age-independent policy warrants weight-v2; KNN's algorithm version is unchanged.
Valid CLASSIFIED weight-v1 adults remain trusted on read without writes: exact
metadata/category/species, original v1 eligible age (dog >=24, cat >=12 months), and
matching actual weight band are required. These ages are backward-read compatibility
checks ONLY, not v2 restrictions. A v1 juvenile/age_unknown status, unknown version,
manual label or inconsistent band is not automatically trusted on deployment.

New/changed measurements calculate v2. Explicit normalization upgrades selected
historical records using existing species/weight only. It preserves the earliest
legacy_physical_size snapshot, or snapshots the previous manual label when a legacy
record has no snapshot. No destructive migration, auto-backfill or history rewrite.
See [implementation and operator runbook](CURRENT_WEIGHT_SIZE_REVISION.md).

Metadata/history remain non-mass-assignable; original null history is retained on
ordinary edits. Age forms still reject the ambiguous legacy age request field.
No seventh matching dimension, guessed measurements or numeric size fallback exists.

## Matching and filters

Pet::hasClassifiedPhysicalSize() validates stored provenance and recalculated category.
Size-specific SQL uses server weight bands/exact strings, permitting v2 at any age
and valid v1 adults. The existing per-row trust check runs before scoring, counting
and pagination. All Sizes retains include_ineligible behavior. Current size does not
mean a pet is available for adoption.

Six features, numeric size levels, weights, distances, penalties, scores, sorting,
pagination, distinct-assessor requirements and terminal history are unchanged.
Qualified juveniles can match; missing weight, unknown life stage, incomplete
assessments, safety exclusions and unavailable pets still fail their respective checks.

FreshStart/Pilot preserve original fictional ages/weights; their three formerly
age-excluded dogs now classify, making all 15 size candidates. PrototypePetSeeder
still creates 15 pets/44 assessments: its complete juvenile becomes eligible
(11 eligible for the seeded profile), unknown-age cat classifies but retains the
unknown-life-stage safety exclusion, and missing-weight/incomplete/unavailable
examples remain deliberate. Existing deployed records are NOT assumed to match seeds.

pets:size-impact --json stays read-only. The separate opt-in pets:normalize-current-size
requires explicit IDs and fictional-dataset attestation; apply additionally needs a
reviewed plan hash, exact DB confirmation and backup reference. No live database was
inspected or changed. [Frontend handoff](FRONTEND_HANDOFF_WEIGHT_SIZE_REVISION.md).
