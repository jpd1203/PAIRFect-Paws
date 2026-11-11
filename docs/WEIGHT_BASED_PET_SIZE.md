# Adult weight-based pet size, Phase 11A

Rule version: `weight-v1`. Application-defined categories, NOT healthy-weight assessments or veterinary diagnoses. Breed, height and life_stage cannot override the calculation.

| Size | Dogs, kg | Cats, kg | Existing KNN level |
| --- | --- | --- | --- |
| Extra Small | 0 < w < 6.5 | 0 < w < 3.0 | 1 |
| Small | 6.5 <= w < 9.0 | 3.0 <= w < 4.0 | 2 |
| Medium | 9.0 <= w < 15.0 | 4.0 <= w < 5.0 | 3 |
| Large | 15.0 <= w < 30.0 | 5.0 <= w < 6.5 | 4 |
| Extra Large | w >= 30.0 | w >= 6.5 | 5 |

Dogs require known age >=24 months; cats >=12 months. Unknown age is NULL, never inferred from breed or life_stage. Known zero months is juvenile. Existing catalog age-group labels are unchanged and do not determine eligibility. Plain positive DECIMAL(10,3) kilograms are compared as integer grams, never binary floats. Existing technical precision limits apply; no clinical minimum/maximum is invented.

## Writes and age contract

PetSizeClassifier owns thresholds and statuses. New model saves calculate size. Existing saves recalculate changed species, age, weight or physical_size. Authorized HTTP updates explicitly invoke the same operation before the version-checked query update, which does not run model events. Controller input cannot supply physical_size, metadata or history overrides. No manual/veterinarian exception exists.

The age column remains integer months. Forms submit age_years and age_months (0..11 additional months). Omitted age on updates retains it; both blank fields clear it; a provided component uses zero for an omitted component. The ambiguous old age request field is rejected. The years maximum is a technical signed integer storage bound, not shelter age policy. Historical erroneous/unknown ages cannot be reconstructed automatically.

Read-only size controls show the saved server result/unavailable state and explicitly explain recalculation on save. They submit no size and duplicate no thresholds in JavaScript. Existing form structure/classes and unrelated frontend work are preserved. Auth, status protections, audit logging and optimistic locking remain intact.

## Schema and history

Additive columns: legacy_physical_size (nullable string); size_classification_status (string, default legacy_unclassified); size_rule_version (nullable string, weight-v1 after evaluation).

Evaluated statuses: classified, age_unknown, juvenile, weight_unavailable, unsupported_species. Only current-version classified size consistent with the actual species, age and weight is trusted for matching. Metadata is not mass-assignable from form input.

The migration snapshots old physical_size into legacy_physical_size, changing NO existing size, age, weight, timestamp, assessment or application score. It never classifies history. A subsequent authorized edit establishes the new canonical result, retaining the old manual label separately. Later calculations cannot overwrite that historical label. Quiet imports/direct SQL must use the classifier when intentionally creating new classified records; merely supplying a historical size does not make them eligible.

## Dry-run historical impact report

Before proposing any historical reclassification: `php artisan pets:size-impact --json`.

There is deliberately no apply/backfill option. The command includes archived pets and reports current/proposed size/status, eligibility, age and weight by ID. No pets, matching results, audit or notification records are written. Treat actual reports as private operational data.

Synthetic impact example verified in isolated tests:

| Historical input | Old manual size | Proposed result | Action now |
| --- | --- | --- | --- |
| Dog, 36 months, 15.000 kg | Small | Large, classified | Report only |
| Dog, unknown age, 15.000 kg | Small | Unavailable, age_unknown | Staff must verify age |
| Cat, 11 months, 15.000 kg | Small | Unavailable, juvenile | No adult size |

Actual historical population impact is NOT measured: this task does not access real development/production databases. The trust gate can substantially reduce recommendations until historical profiles are explicitly reviewed, even when manual and proposed labels agree. Public catalog visibility is not subject to this size gate. No automatic historical backfill is authorized.

Migration risks: additive DDL/history-copy UPDATE can lock or scan a large pets table; review backup/maintenance timing. Code requires migration before new requests. Down drops provenance/history columns, does not reverse later authorized classifications, and is not a lossless production rollback. No production/development migration was performed here.

## Matching preservation and Phase 11B contract

KNN formula, six dimensions, weights, numeric size levels, penalties, scoring version, observer and safety rules are unchanged. Mapper supplies NULL for unclassified/stale/invalid size, retaining PET_SIZE_NOT_ASSESSED with a new adult-weight explanation. Same-band edits preserve matching vectors/fingerprints; cross-band/eligibility changes refresh nonterminal applications using the existing service. Terminal score history remains intact. Fully assessed juveniles, unknown-age and historical pets remain browseable under existing availability/archive rules.

Latest main RecommendationController, pagination, include_ineligible behavior and config/matching.php are unchanged. Opt-in MatchingDemoSeeder uses explicit synthetic weights reproducing its former five levels; other historical data/imports are not backfilled.

New fictional DefenseDemo and FreshStart/Pilot records also receive explicit synthetic weights. Existing stored animals are not changed. FreshStart/Pilot retain their original ages: Koko (18 months), Daisy (14) and Tala (10) are juvenile dogs, so all 15 remain fully behavior-assessed/browseable but only 12 meet adult-size matching eligibility. No fake adult ages or manual exceptions are introduced to keep an old recommendation count.

Phase 11B should consume species (Dog/Cat), weight_kg decimal string, age integer months/null, size_classification_status, size_rule_version, and the existing five physical_size names only when trusted. Backend authority is hasClassifiedPhysicalSize(); computed size_display is presentation-friendly. A raw historical physical_size is not proof of current classification. Existing PET_SIZE_NOT_ASSESSED remains the compatibility exclusion contract.

Species-first adopter controls, preference/cross-species filtering changes and any new public API belong to Phase 11B and are NOT implemented. Never invent a numeric fallback, manual override, healthy-weight label or seventh matching dimension.
