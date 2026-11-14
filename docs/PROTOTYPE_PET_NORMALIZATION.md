# Phase 15C: fictional prototype pet normalization

This is test-data preparation, not veterinary certification, a healthy-weight assessment or a staff measurement-attestation workflow. Fictional inputs may be assigned intentionally. Actual deployed contents have NOT been inspected and must not be assumed to match any seeder.

## Fixture audit and recommended sources

| Source | Findings at Phase 15B base | Phase 15C treatment |
| --- | --- | --- |
| DatabaseSeeder (6 pets) | Age values 1-4 were stored as months; no weights, no raw assessments, incomplete safety profiles. Not usable as trusted recommendations. | Fresh samples use canonical fictional month/weight inputs from the shared catalog and explicit fixture markers. All 6 classify; none becomes recommendation-ready without real matching-profile requirements. Existing unmarked copies are NOT claimed by name. |
| FreshStartPetsSeeder / pilot:seed-assessed-pets (15) | Canonical month ages, synthetic weights, three distinct full raw assessments each. 12 adult-size candidates; Koko/Daisy/Tala are intentionally juvenile. | Original inputs/scenarios retained. Expose shared fictional measurement definitions without changing age thresholds or inventing adult ages. Recognizable legacy copies may be normalized; pilot requires its existing batch marker too. |
| MatchingDemoSeeder (12 pets, 4 matching profiles, 3 observers) | Already has explicit synthetic age/weight inputs across all 5 dog/cat bands, scored BFI and full independent assessments. Safety/financial/housing exclusions vary by adopter. | No seeder behavior change. Its exact fictional description + name + species are recognized for narrow legacy normalization. Raw answers and profile history are not overwritten by the normalizer. |
| DefenseDemoSeeder (8 pets, fictional branch/accounts, applications/handovers/check-ins) | Adult age 24 months and explicit weights already classify. Two-observer Mochi, aggression, reserved/adopted pets and historical placements are deliberate. Existing deterministic lifecycle tests pass. | Left intact; NOT in automatic normalization allowlist. Preserve this linked historical scenario rather than silently rewriting it. |
| AdopterDummyDataSeeder | Mostly canonical month ages, missing weights, old 4-feature summaries, incomplete modern BFI profiles and synthetic application/handover/check-in snapshots. Common-name firstOrCreate can reuse unrelated pets. | Legacy, not recommended for new matching demonstrations; not automatically recognized/repaired. Never use it as a repair tool. |
| HandoverSeeder | Tiny ages 1-3 months, missing weights/assessments, hardcoded historical handovers; firstOrCreate uses common names. | Legacy lifecycle-only fixtures, not trusted matching data. Not normalized by name; no handover logic or historical state changed. |
| DemoPetsSeeder | Random ages/species/statuses, no weights/raw assessments, external image downloads and a destructive prior-demo deletion query. Its generated names do not reliably match that deletion prefix. | Legacy/unreliable; do NOT run for normalization. No network image downloads introduced. |
| AssessmentSeeder | Deletes prior assessments for ALL pets and resets availability/safety values, including unknown records. No measurement inputs. | Not a repair tool; do NOT run on an established database. This preexisting behavior was not expanded or used by the new process. |
| AdminSeeder / RoleSeeder | Staff bootstrap / role data, not evidence that any pet is fictional or assessed. | Unchanged. No staff role or verification changes. |

Recommended fresh matching demonstration: opt-in `PrototypePetSeeder`. It is not added to DatabaseSeeder, startup, deployment or the scheduler. It is allowed only in local/testing environments. It creates 15 explicitly marked fictional pets, 44 scored raw assessments from 3 distinct fictional observers, and 1 matching-complete fictional adopter (20 BFI answers scored by the existing service). Ten adult pets span every approved category for both species. The five intentional exclusions are juvenile, unknown-age, missing-weight, two-observer incomplete and Assessing/unavailable. No applications or handovers are fabricated for these new pets.

Four accounts use `prototype-...@example.test` and the public demo password `PrototypeDemo2026!`; these are local fictional credentials, never production bootstrap credentials. Existing accounts/profiles/pets/assessments are not rewritten on rerun. Unrecognized name/account collisions abort and roll back the fixture transaction. Rerunning setup is a no-op, including timestamps; it does not silently repair later edits or fill intentionally incomplete records.

## Before/after eligibility (synthetic test evidence, NOT deployment counts)

- Default six: before 0 trusted classifications / 0 recommendation-ready pets; fresh Phase 15C 6 trusted classifications / still 0 recommendation-ready pets (no fabricated assessments or safety completion).
- FreshStart/Pilot: fresh current-policy behavior remains 12 candidates + 3 juveniles per batch. Synthetic legacy copies: 0 -> 12 candidates per batch after explicit normalization; all 45 assessments preserved per batch.
- MatchingDemo: fresh classification already compatible. Synthetic legacy copies: 0 -> 12 trusted adult classifications; adopter-specific safety/budget eligibility remains governed by the unchanged algorithm.
- New PrototypePetSeeder: 15 records, 12 trusted adult sizes, 10 eligible recommendations for its complete adopter. The incomplete and unavailable adult fixtures are classified but not eligible. Five exclusions remain visible through All Sizes + include_ineligible.
- All-legacy synthetic Prototype batch: 0 -> 10 recommendation-ready pets, with juvenile/unknown-age/missing-weight exclusions retained, 44 raw assessments and all old manual labels preserved.

No classification status, version or size result is hardcoded in the fixture definitions. Age is canonical months and weights are decimal kilogram inputs. The existing Pet saving event / PetSizeClassifier calculates results. Representative weights are fictional points, not a duplicate threshold table. Species-first labels, SQL trust, integer-gram comparisons, adult ages, weight-v1, KNN formula/six features/weights/penalties/sorting and pagination are unchanged.

## Existing fictional records: dry-run-first process

`php artisan prototype:pets-normalize --json`

Default mode is read-only. The JSON contains connection driver/database name (no credentials), rule version, plan hash, every recognized fixture ID/key/name/revision, raw-state hash, exact proposed field differences, preserved legacy label and skipped IDs/reasons. No timestamps or audit rows are written by a dry run. Identical database contents produce an identical plan hash.

Recognition requires an explicit source identity, never a plausible size or name alone:

- New Prototype records: exact generated name, species, fictional description and versioned fixture marker.
- New six samples: exact name/species and versioned sample marker. Older unmarked common-name sample copies are skipped.
- FreshStart: exact name/species/breed/sex AND the seeder's exact explicitly synthetic description. Pilot copies additionally require `Demo ` prefix and the established pilot batch marker.
- MatchingDemo: exact numbered name/species AND its exact synthetic QA description.

Duplicate identities are skipped as ambiguous. Unrecognized records and altered identities are not claimed by a loose prefix. Recognized archived fictional records may have their measurement fields normalized; their archive flag and lifecycle/history records stay untouched. The normalizer does not insert pets, accounts or assessments and never deletes, resets or truncates anything.

### Disposable apply mode only

After reviewing a dry run, on a genuinely disposable test database:

`php artisan prototype:pets-normalize --apply --plan-hash=<reviewed-64-character-hash> --confirm-disposable=<exact-database-name> --json`

The service itself (not just the CLI) requires APP_ENV=testing and exact database-name confirmation. SQLite must be `:memory:` (file databases are refused). MySQL must be loopback 127.0.0.1, have a `pairfect_phase15c_*` database name, and match the explicit `PAIRFECT_DISPOSABLE_MYSQL_DB` environment marker. Connection URLs are refused to prevent a URL overriding the guarded target. Production and local environments are refused even with all flags. Naming/flags are operator safeguards, not independent proof of infrastructure provenance: the operator must actually create a disposable container/database. Never relabel a real database/environment to satisfy these checks.

The plan is recalculated under row locks and its hash must still match. Each changed pet is version-checked. Canonical fictional age/weight and pure classifier outputs are written deliberately only within this allowlisted test-data operation; the general legacy-save guard is NOT disabled or modified. `legacy_physical_size` is preserved exactly, including null. Changed rows increment version and stamp updated_at. An audit row records fixture key, reviewed plan hash, exact before/after field values and revision; any failure rolls back the complete batch and audits.

Repeat the dry run after applying. It must report zero changes. Applying that new zero-change plan is a no-op and creates no extra audits. Reusing the pre-apply hash is correctly rejected as stale; do not bypass that check.

Applications (including pending/terminal scores), assessments, handovers, post-adoption and account/profile history remain untouched by normalization. Personalized recommendations calculate against current trusted inputs. Existing application screens/services may refresh NONTERMINAL KNN snapshots through the normal lifecycle; inspect pending rankings through those existing services before a presentation. Terminal results are never rewritten by this tool. It sends no mail or bell notifications.

## Separately authorized deployed-prototype operator procedure

There is intentionally NO production apply switch in this phase. Do not run fixture seeders, migrations, the legacy assessment seeder, resets or truncation on the established deployment. Do not set the deployed APP_ENV to testing or change its connection to bypass guards.

1. Obtain explicit authorization for the specific deployed fictional IDs, maintenance window and data-only update. Confirm the branch/code is separately approved; this phase neither pushes nor deploys it.
2. Take a protected full database backup and prove restore in a new disposable MySQL 8 instance. Keep credentials and exports out of Git; preserve all dependent application/assessment/handover/history tables and original IDs.
3. With separately authorized read-only access, run/review the dry-run report against the actual target, including skipped/ambiguous records. Never assume fixture counts match the deployment. Unmarked legacy records require a separately reviewed ID + expected-current-state designation; do not bulk-tag names to make them eligible.
4. Rehearse on the restored disposable copy using the guarded apply procedure and a dry run from THAT copy (the hash includes its database name). Review exact pet deltas/audits, unchanged histories, preserved exclusions and current recommendations. No policy or clinical certification is needed for explicitly declared fictional inputs.
5. Obtain separate approval for a reviewed, narrowly scoped production DATA patch generated from the approved real-target plan. This is NOT implemented as a production-capable command here. The patch must recheck source identity, all expected before-values/raw-state hashes and revisions under locks, compute outputs with the same classifier version, preserve manual snapshots, update only approved pet fields/revisions/timestamps and insert equivalent audits atomically. Abort on drift or an unknown record. Do not import the disposable database wholesale or overwrite lifecycle history.
6. An authorized operator executes that separately reviewed patch during the approved window, rechecks fresh dry-run results, recommendation filters/exclusions, pending ranking refresh through existing services and history invariants. Preserve reports/audit evidence. If incorrect, restore from the verified backup or use an approved expected-state-checked reverse DATA patch; no reset or blanket classification rollback.

This phase supplies the safe local rehearsal mechanism and operator runbook, not permission or code to mutate production. No deployed data was accessed or updated.

## Frontend and remaining limitations

Zero frontend/UI files changed. No frontend handoff document is needed. The two existing mobile handover issues remain deferred. Existing range labels and interactions are exercised by the existing species-first tests, not redesigned.

The new normalizer intentionally does not fix missing behavioral assessments, incomplete BFI profiles, unknown marker-less records, or old linked Defense/AdopterDummy/Handover snapshots. Those require explicit fixture-specific decisions rather than silent replacement. Previously promoted genuine records remain a separate concern. Tools/model safeguards do not stop a privileged operator from issuing arbitrary raw SQL; database access must remain controlled.
