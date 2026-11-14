# Automatic pet life stage

Completed November 15, 2026 (Asia/Singapore). Branch feat/automatic-pet-life-stage.
Base: 3673aa13c3bafe00eea1794ca7ea2fbb3fcde0fa (latest origin/master at start).
origin/main 2296d4e was already included; no groupmate changes were merged/overwritten.
Worktree: C:/Users/jpdol/Desktop/Capstone-PAIRfect_Paws/output/life-stage-classification-2026-11-14.
The delivery message supplies the local commit SHA; git rev-parse HEAD identifies it.

## Central policy / behavior

| Species | young | adult | senior |
| --- | --- | --- | --- |
| Dog | 0–<24 months | 24–<96 months | >=96 months |
| Cat | 0–<12 months | 12–<132 months | >=132 months |

PetLifeStageClassifier::THRESHOLDS is the only policy table. Version life-stage-v1
is included in reports/audits, not a new database column. Fixed application categories
are not veterinary determinations; breed/size-dependent maturation is not inferred.
No previously approved species-specific senior thresholds were found; this implements
the newly requested policy instead of the old shared <=24/25–84/>=85 display ranges.

Classifier accepts supported species/enum plus nonnegative whole recorded months;
unknown ages and unsupported species return null. Invalid ages are rejected for
writes. Read-only interpretation of invalid historical ages is Unknown, not Adult.
Existing HTTP year/month validation, units and storage bounds are retained.

- Pet saving calculates stored life_stage every time; supported create/edit paths
  recalculate on species/age changes and clearing age. Unrelated saves remain consistent.
- life_stage is no longer mass-assignable or an accepted staff form input; older or
  forged values are ignored. The version-checked HTTP query-update path explicitly
  calculates life stage because query-builder updates do not fire model events.
- Pet::life_stage and age_group derive from the same species/recorded-age classifier.
  Historical stale storage cannot change displayed/filter/KNN stage. Reads do not
  mutate DB snapshots: use getRawOriginal('life_stage') when auditing old values.
- Both /pets and /animal filtering use the shared species-specific SQL scope, excluding
  unknown/invalid ages from named stages. Admin row filter labels use age_group too.
- Normal model seed/import writes inherit the saving hook. The quiet matching-demo
  import explicitly calls the classifier. Prototype fixture repair recalculates stage
  when its explicitly fictional age inputs change, comparing raw old stage for audits.
  Arbitrary direct SQL/saveQuietly writes bypass model events: they must explicitly
  derive stage if they change species/age. This is not an unguarded public import API.

KNN mapper still reads Pet::life_stage, now canonical. No six-feature vector, weights,
math, penalty, score, sorting or safety rule changed. Existing low-financial-readiness
exclusion still rejects young/senior pets (or higher medical needs). Boundary changes
therefore intentionally change eligibility, e.g. cat 11 ->12 months becomes adult;
cat 132 and dog 96 months become senior. Aggression/children, existing pets, medical,
assessment, verification and availability restrictions remain intact. Existing active
fingerprints refresh through normal workflows; every terminal application snapshot
remains untouched. Weight-v2 and species-first size filtering remain independent.

Age is a RECORDED number, not a clock. This change does not advance age with time,
invent birthdays or add scheduler jobs. Continuous aging needs separately approved
estimated DOB/age-reference-date data and policy.

## Historical fictional data — explicit normalization only

New pets use automatic writes. Historical rows read consistently immediately, but
stored cached life_stage may require an explicit operator-approved normalization.
No migration or deployment hook backfills it automatically.

pets:normalize-life-stage reuses CurrentWeightSizeNormalizer's explicit 1–500 ID
selection, dataset/DB targeting, row snapshots and locking, but applies ONLY life_stage
(plus optimistic version/updated_at) and audit entries. It never changes recorded
age, species, weight, size provenance, safety facts, applications or lifecycle history.
Unknown age clears a contradictory cached stage to null. Invalid age/unsupported
species is reported/skipped, not corrected or guessed. Selection is an OPERATOR
attestation of fictional data, not automatic proof that arbitrary records are fake.

Default read-only plan lists IDs, raw old/proposed stages, source ages, counts and
hash. Apply requires exact dataset, reviewed hash, DB confirmation and backup reference.
Hash binds source/target/policy; changed rows reject as stale. Selected updates/audits
are atomic with revision checks. Re-run dry-run after apply; already consistent records
produce zero writes/audits. Tests inject second-row audit failure to prove rollback.

Only after separate deployment approval, verified backup/restore and approved IDs:

~~~sh
php artisan pets:normalize-life-stage --pet-ids="<APPROVED_IDS_CSV>" --dataset=pairfect-fictional-prototype --json
php artisan pets:normalize-life-stage --pet-ids="<SAME_IDS_CSV>" --dataset=pairfect-fictional-prototype --apply --plan-hash="<REVIEWED_HASH>" --confirm-database="<EXACT_DATABASE>" --backup-reference="<VERIFIED_BACKUP_REFERENCE>" --json
~~~

Commands are prepared, NOT run on Azure. Do not substitute the older fictional
fixture repair command, which may restore synthetic ages/weights. Do not reset/seed
an established DB. Backup strings are operator evidence references, not an automatic
backup/restore service. Recover via approved audit-backed cached-stage repair or
verified backup with reconciliation of any intervening writes; never wipe lifecycle
history or assume code-only rollback reverses derived-stage policy/data changes.

## Validation

- Focused SQLite: 172 passed; 1,344 assertions.
- Full SQLite regression: 980 passed, 8 MySQL-only skips; separate seeder 1 passed.
- Genuine disposable MySQL 8.0.46: 216 focused passed, 1,768 assertions; separate
  destructive-seeder process 1 passed, 25 assertions. Full MySQL suite not rerun.
- Frontend: 21 passed, including server-policy preview, invalid/blank/zero age,
  species changes, reset/restoration, programmatic editor prefill and read-only input.
- PHP lint: 430 files passed; scoped Pint passed. Formatter-only import/whitespace
  normalization in touched routes/seeder did not change other endpoints or permissions.
- Vite production build and Blade compilation checked. Browser evidence and exact
  frontend contracts are in [handoff](FRONTEND_HANDOFF_LIFE_STAGE.md).
- Synthetic-only DBs, fake mail. No existing DB access, SMTP, Azure change, push or
  deployment. Temporary QA server/MySQL instance stopped. Full source checks retain
  known npm advisories; no dependency upgrades or unrelated UI fixes.

No new migration is required: existing nullable life_stage and canonical age columns
are reused. Approved code/assets must be deployed before using the new command.
Actual deployed schema/recorded age accuracy and backup evidence remain operator checks.

## Exact changed-file inventory

- app/Console/Commands/NormalizePetLifeStage.php
- app/Http/Controllers/Admin/AssessmentController.php
- app/Http/Controllers/PetController.php
- app/Models/Pet.php
- app/Services/PetLifeStageClassifier.php
- app/Services/PetLifeStageNormalizer.php
- app/Services/PrototypePetNormalizer.php
- app/Support/PrototypePetFixtures.php
- database/seeders/FreshStartPetsSeeder.php
- database/seeders/MatchingDemoSeeder.php
- docs/FRONTEND_HANDOFF_LIFE_STAGE.md
- docs/PET_LIFE_STAGE.md
- docs/matching-algorithm.md
- public/js/admin/animal.js
- resources/js/admin.js
- resources/js/app.js
- resources/js/pet-life-stage.js
- resources/views/admin/animal/_add-modal.blade.php
- resources/views/admin/animal/_view-modal.blade.php
- resources/views/admin/assessment/_summary-modal-content.blade.php
- resources/views/partials/pet-life-stage.blade.php
- resources/views/pets/create.blade.php
- resources/views/pets/edit.blade.php
- routes/web.php
- tests/Feature/AssessmentKnnIntegrationTest.php
- tests/Feature/MatchingWorkflowTest.php
- tests/Feature/PetCatalogAssessmentVisibilityTest.php
- tests/Feature/PetLifeStageNormalizationTest.php
- tests/Feature/PetLifeStageTest.php
- tests/Feature/SpeciesFirstSizeFilterTest.php
- tests/Feature/WeightBasedPetSizeTest.php
- tests/Frontend/admin-toast.test.js
- tests/Frontend/pet-life-stage.test.js
- tests/Unit/PetLifeStageClassifierTest.php
