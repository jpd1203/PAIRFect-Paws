# Current-weight size revision — implementation and operator runbook

Prepared November 14, 2026 (Asia/Singapore). Local implementation only; no publication,
Azure access, production/development DB access, real mail or deployment performed.

## Source, scope and root cause

- Repository: jpd1203/PAIRFect-Paws.
- Exact base: ee3f407ad00a5afeb5a2a3337fcf736e91237fdd (origin/master at start).
- origin/main: 2296d4edd8127d482737040e35a58a80b49ca304; fully included in master.
  Master was 4 commits ahead, 0 behind. Its extra changes implement prototype
  automatic deployment/documentation, not missing groupmate application changes.
- Branch: feat/current-weight-size-revision.
- Isolated worktree: C:/Users/jpdol/Desktop/Capstone-PAIRfect_Paws/output/weight-size-revision-2026-11-14.
- The local commit containing this report identifies the exact revision; obtain its
  SHA with git rev-parse HEAD. The final delivery message supplies the full SHA.

The old classifier rejected dog ages below 24 months, cat ages below 12 months and
unknown ages before considering weight. MatchingProfileMapper required trusted size;
recommendationEligible and withClassifiedSize independently required adult-policy
metadata/age. Thus qualified puppies/kittens could not enter normal results. Several
messages also incorrectly described size as adult-only. Public/staff size cells could
display raw historical labels without indicating untrusted classification.

The fix removes age from current size calculation and query gating for weight-v2.
All weight bands, decimal grammar, integer-gram comparisons, input validation and
read-only derived controls are preserved. Life stage is unchanged and independent.
No breed override, healthy-weight claim or adult-size prediction is introduced.

KnnRecommendationService, RecommendationController, MatchingProfileMapper, MatchFilters,
KnnMatcher, six features, weights, distance, penalties, scores, sorting and paginator
are unchanged. Their existing trust interface now accepts qualified current-size
juveniles. Behavioral/medical/safety/availability checks still apply. In particular,
unknown life stage remains SAFETY_INFORMATION_INCOMPLETE; low care capacity can
still exclude young pets under the existing financial safety rule.

## Version and historical-record behavior

weight-v2 identifies the changed policy, without changing KNN algorithm version or
schema. New classifications use v2. Valid classified weight-v1 adults remain trusted
without writes, using exact strings, original adult ages and recalculated weight band.
Tests verify identical dog AND cat match results across valid v1/v2 metadata.

Old juvenile/age_unknown, stale or inconsistent metadata remains untrusted until
explicitly upgraded; deploying code does not attest old measurements. Ordinary
legacy_unclassified edits still preserve provenance/labels, even when measurements
or incoming metadata change. Forged HTTP metadata is not a supported write path.
Existing v1 adults therefore need NO eager normalization to keep recommendations.

The new pets:normalize-current-size command is independent of the older Phase 15C
fixture repair command. It never substitutes manifest ages or fictional weights.

- Default is read-only, requiring 1–500 explicit positive IDs and dataset attestation
  pairfect-fictional-prototype. No ranges, all-records switch or automatic name match.
  Duplicate IDs are deduplicated. Every selected ID must exist; archived IDs are
  included only when explicitly selected, never unarchived by the operation.
- IDs are an explicit OPERATOR designation of fictional data. The attestation is not
  automatic proof that a database contains no real animals; operator review is mandatory.
- Classifies recorded species/weight only. Missing, zero, invalid or unsupported
  measurements are reported/skipped, never filled or replaced. Reports contain counts,
  per-record before/after changes, revision and source snapshot hashes.
- Preserves nonnull legacy_physical_size. For a legacy_unclassified record without a
  snapshot, copies its original manual physical_size before applying the new result.
  Already classified records with null history do not acquire fabricated manual history.
- Changes only physical_size, legacy_physical_size where necessary, classification
  status/version, pet optimistic version and updated_at, plus an audit entry.
  No age, weight, life stage, safety, availability, assessment, application, handover,
  check-in, queue or notification records are rewritten by this command.
- Apply requires explicit --apply, prior plan hash, exact DB name/path confirmation
  and backup-reference string. The reference is an operator attestation, not a backup
  service or verified restore implementation. A hash binds source rows AND DB target.
- Replans selected rows under one transaction/row locks and rejects stale snapshots;
  version-checked updates and audit inserts share that transaction. A second-row audit
  failure test proves the first-row update/audit also roll back. Repeating a fresh
  no-change plan produces zero writes/audits. SQLite tests do NOT prove MySQL locking.
- No automatic job, migration, deployment hook, SMTP or background enqueue is added.
  Normal request-time matching refresh behavior remains; terminal score history is
  immutable. The command itself does not bulk-refresh even pending snapshots.

## Validation — actually executed

| Check | Result |
| --- | --- |
| Final complete SQLite suite, excluding destructive seeder | 935 cases: 927 passed, 8 MySQL-only skips, 0 failures/errors; 8,073 assertions; 71.967s reported runner duration |
| MatchingDemoSeederTest in a separate serial process | 1 passed, 23 assertions |
| Combined SQLite coverage | 928 passed, 8 skips; 8,096 assertions |
| Final focused revision/species-filter tests | 65 passed, 503 assertions |
| Frontend Node tests | 17 passed, including current-weight helper text, species reset, restored queries/pageshow/popstate and tampered state |
| PHP lint | 423 tracked/new PHP/Blade files passed |
| Scoped Pint | Passed on changed/new PHP files; only the reported test-import formatting was corrected, no broad inherited formatting |
| Blade cache/clear | Passed |
| Vite production build | Passed; final frontend build 1.28s; no dependency upgrades |
| Git diff checks and unchanged contracts | Passed; migrations, dependency manifests/locks, matching config and core scoring/filter files unchanged |
| Full MySQL 8 / MySQL seeder | NOT RUN: Docker CLI exists but Linux-engine daemon pipe is unavailable; no reusable disposable native server was established |

Earlier targeted failures were old adult-only expectations, updated to assert the
new requested policy while retaining unrelated safeguards. The first full run then
found one more old unknown-age size assertion; final full run has no failures.
No tests were disabled to hide those policy changes. MySQL-only skips remain explicit.
No real DB was used, no system software installed, no existing MySQL service reused.
Existing npm install reports 3 inherited advisories (2 high, 1 moderate); no audit-fix
or lockfile changes were made. This report does not claim launch readiness.

Actual Chromium browser QA, synthetic DB and array mail only:

- Fictional adopter login, existing completed profile -> recommendation generation.
- 11 eligible fixture pets instead of 10; Dog/Medium returns adult plus juvenile.
- Current-size accessible label/helper, dog ranges and cat ranges from backend JSON.
- Keyboard species switch resets Medium to All Sizes; Apply uses Cat/empty size.
- Browser Back restores Dog/Medium; Forward restores Cat/All Sizes.
- Initial All Species disables size; 390x844 screenshot captured/visually reviewed.
- Console: 0 errors/warnings. Browser and temporary port-8021 server stopped.

The browser pass caught a remaining adult-only accessible label; it was corrected
and feature-tested before the final full suite. The seven UI files/technical contracts
are in [frontend handoff](FRONTEND_HANDOFF_WEIGHT_SIZE_REVISION.md). Screenshots/logs
are ignored local evidence, not shipped source. No physical-device/accessibility
certification or unrelated mobile/header fixes are claimed.

## Deployment readiness — separate approval required

**Conditional only:** full disposable MySQL 8 regression, operator backup/restore
evidence, actual deployed source/schema verification and explicit deployment approval
are still required. This phase does NOT authorize a master push. The inspected
workflow deploys on master pushes; even publication there would affect live Azure.

### Required ordering

1. Review the local diff and complete full MySQL 8 + separately isolated seeder tests
   once disposable infrastructure is available; verify exact SQL/PHP trust agreement
   under MySQL collations and genuine transaction/locking behavior. Do not use MariaDB
   as proof of MySQL 8. Never reuse a development/production DB for tests.
2. With separate scoped authorization, identify actual deployed revision/schema and
   compare the COMPLETE upgrade, not just this commit. Preserve accounts, uploads,
   application/handover/check-in state and queues. Record trusted adult control IDs,
   current classification metadata and expected match scores securely for comparison.
3. Back up the actual database and uploads. Rehearse restoration into an isolated DB
   and verify schema, counts, representative linkage, terminal scores and media paths.
   Document backup ID, time, responsible operator and restore results. A file existing
   is not proof it can be restored. Agree maintenance/write-hold and recovery windows.
4. This revision adds NO migrations. Required existing size columns come from
   2026_11_11_000001_add_weight_size_classification_to_pets. If they are missing, STOP
   and plan that separately approved additive schema-first upgrade. Do not run fresh,
   reset, seed, down or an automatic backfill on the deployed database.
5. After explicit approval, deploy the reviewed code/assets through the existing
   process, then clear/rebuild caches and restart workers as appropriate for that
   approved release. Do not run normalization before v2-compatible code is active.
6. BEFORE normalization, verify existing consistent classified v1 adults still appear
   in recommendation and size-specific results with unchanged scores/ranking (unless
   another legitimate safety/availability condition changed). No rule-wide outage is
   expected or acceptable. Verify backend and public UI use the reviewed revision.
7. Select only explicitly confirmed FICTIONAL pet IDs. Run and review the dry run
   below. Reconcile counts, preserve missing-weight cases, inspect changes and verify
   the exact DB target. Do NOT use the older prototype:pets-normalize manifest repair,
   a seeder, or a broad direct SQL update as a substitute.
8. Obtain approval of that exact plan/backup; apply explicitly. Regenerate/review any
   stale plan, never bypass its guard. Check audit evidence and run another dry run
   expecting zero changes for valid normalized records. Missing-weight skips persist.
9. Verify qualified juvenile dog/cat candidates match in All Sizes AND their correct
   species/size band, while keeping juvenile life stage. Verify unavailable, missing
   medical/safety data, incomplete assessment and household exclusions remain. Check
   pagination and protected application entry, terminal score/history preservation,
   and before/after adult controls. Never interpret unknown-size as adoption status.

### Operator commands — prepared only, NOT executed on Azure

Replace the angle-bracket placeholders with reviewed values; IDs below are not a
live-record selection. Keep reports private. Approval/backup must precede APPLY.

~~~sh
php artisan pets:normalize-current-size --pet-ids="<APPROVED_IDS_CSV>" --dataset=pairfect-fictional-prototype --json
~~~

Review the complete JSON and its plan_hash. Then, only after separate approval:

~~~sh
php artisan pets:normalize-current-size --pet-ids="<SAME_APPROVED_IDS_CSV>" --dataset=pairfect-fictional-prototype --apply --plan-hash="<REVIEWED_64_HEX_HASH>" --confirm-database="<EXACT_DATABASE_NAME_OR_SQLITE_PATH>" --backup-reference="<VERIFIED_BACKUP_AND_RESTORE_REFERENCE>" --json
~~~

ID examples use comma-separated positive integers without spaces/ranges, not names.
The DB confirmation/hash/backup guard cannot establish that data are fictional on
their own; the operator must verify scope. There is no apply-all option or guessing.

### Recovery

- Stale plan/validation failure: no writes; correct inputs and review a fresh plan.
- Transaction/audit failure: all selected pet changes roll back; diagnose, regenerate
  the plan and retry. Missing/invalid weights are left alone, not a recovery error.
- Code deployment failure before normalization: follow the approved code/assets/cache
  rollback plan, preserving DB/uploads; verify the actual prior release requirements.
- After normalization or new v2 writes, **code-only downgrade to v1 is NOT lossless**:
  old code rejects v2 metadata. Preserve the new DB and prefer a tested forward fix,
  or restore the verified pre-change DB/upload snapshot within the agreed write-hold
  window. If writes continued, reconcile them explicitly before restoration; do not
  erase intervening adoptions. A narrowly reviewed audit-backed classification restore
  is an operator recovery task, not an unconditional reverse command supplied here.
- Never relabel juvenile v2 records as v1 to force an old application to accept them.
  Do not drop provenance columns or reset terminal scores. No destructive down migration
  is needed or authorized by this revision.

## Preservation and remaining limits

28 preexisting worktrees (including frontend-v2 and the stopped master preview) and
120 original modified/untracked file hashes were verified unchanged. No groupmate
edits were imported or overwritten. Only this isolated branch is committed; no merge,
push, workflow dispatch, Azure setting change or live-data normalization occurred.

MySQL 8 is the main unverified regression gate. Lock ordering/version guards exist,
but SQLite cannot certify MySQL concurrency. Operator-designated prototype scope and
backup verification require human review. Matching still scores/filter-paginates an
in-memory result collection; no performance redesign was attempted. Existing unrelated
UI issues and build-tool security advisories are not silently fixed by this task.

The exact changed-file inventory is included below in this commit. Runtime dependencies,
.env, databases, screenshots, test logs and preservation snapshots remain ignored.

## Exact changed-file inventory

- app/Console/Commands/NormalizeCurrentWeightSize.php
- app/Console/Commands/PetSizeImpact.php
- app/Http/Controllers/Admin/AssessmentController.php
- app/Models/Pet.php
- app/Services/CurrentWeightSizeNormalizer.php
- app/Services/Matching/MatchPresenter.php
- app/Services/PetSizeClassifier.php
- docs/CURRENT_WEIGHT_SIZE_REVISION.md
- docs/FRONTEND_HANDOFF_WEIGHT_SIZE_REVISION.md
- docs/HISTORICAL_PET_CLASSIFICATION_SAFETY.md
- docs/PROTOTYPE_PET_NORMALIZATION.md
- docs/SPECIES_FIRST_SIZE_FILTER.md
- docs/WEIGHT_BASED_PET_SIZE.md
- public/js/recommendation-size-filters.js
- resources/views/admin/assessment/_summary-modal-content.blade.php
- resources/views/animal/_pet-modal-content.blade.php
- resources/views/partials/pet-calculated-size.blade.php
- resources/views/partials/pet-size-guidance.blade.php
- resources/views/pets/show.blade.php
- resources/views/recommendation/results.blade.php
- tests/Feature/AssessmentKnnIntegrationTest.php
- tests/Feature/CurrentWeightSizeRevisionTest.php
- tests/Feature/FreshStartPetsSeederTest.php
- tests/Feature/HistoricalPetSizeSafetyTest.php
- tests/Feature/PetPhysicalMeasurementsTest.php
- tests/Feature/PilotAssessedPetsTest.php
- tests/Feature/PrototypePetNormalizationTest.php
- tests/Feature/SpeciesFirstSizeFilterTest.php
- tests/Feature/WeightBasedPetSizeTest.php
- tests/Frontend/recommendation-size-filters.test.js
- tests/Unit/PetSizeClassifierTest.php
