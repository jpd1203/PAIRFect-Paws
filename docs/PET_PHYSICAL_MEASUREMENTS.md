# Pet physical measurements and size (Phase 7 / Tool Defense Group 8)

Historical Phase 7 report: its manual-size policy below is superseded by [Phase 11A adult weight-based classification](WEIGHT_BASED_PET_SIZE.md). The measurement precision/unit rules remain in force.

Baseline: committed Phase 6 `485ecb0b39ecc5dc981688601f348d7eba412065`.

## Existing schema and implementation audit

Before Phase 7, pet weight and height had no database fields, casts, validation or form controls. There are no existing stored measurements to convert or backfill. `pets.physical_size` already exists as a nullable string, separate from breed/species, and is manually entered by staff from veterinary assessment. The existing dog/cat species enum casts the stored string; breed is optional text.

The established matching configuration contains **five** labels, not just the conceptual Small/Medium/Large set: Extra Small=1, Small=2, Medium=3, Large=4, Extra Large=5. All five remain valid for both species. No existing stored size value is changed. Current repository/configuration/matching documentation contains no approved numerical dog or cat weight thresholds.

Writes use PetController through the existing authenticated staff/verified route aliases (`admin.animals.*`, `admin.pets.*`). The primary UI is the Animal Records three-step Add/View-Edit modal with inline JSON data and public/js/admin/animal.js. Separately routed pets/create and pets/edit forms also remain supported. Public/adopter and recommendation View details share animal/_pet-modal-content; full detail uses pets/show.

## Data Dictionary

Migration: `2026_11_06_000001_add_physical_measurements_to_pets_table.php`.

| Table.field | Type | Unit | Nullable/default | Application rule |
| --- | --- | --- | --- | --- |
| pets.weight_kg | DECIMAL(10,3) | kilograms | nullable, no non-null default | Required on new staff HTTP creation; optional/nullable on updates for historical unknowns; positive when supplied |
| pets.height_cm | DECIMAL(10,3) | centimeters | nullable, no non-null default | Optional on creation/update; positive when supplied |

Only these two columns are added. No FK, index, unique constraint, relationship or existing size column is changed. There is no historical backfill or destructive field conversion. The down migration drops only these new columns; production rollback would lose measurements and needs a reviewed backup/rollback plan.

Both fields are explicitly fillable for validated staff writes and cast as `decimal:3`, returning decimal strings rather than floating-point model values. New computed `weight_display`/`height_display` accessors trim insignificant decimal zeroes, append kg/cm, and return **Not recorded** for NULL. These display strings are not stored and are not automatically appended to unrelated JSON responses.

## Numeric validation and storage semantics

PositivePetMeasurement accepts finite plain decimal numbers with one to seven whole-number digits and zero to three fractional digits. Values must be greater than zero. It rejects malformed text, zero/negative numbers, arrays/booleans, unit strings, commas, string scientific notation, NaN/infinities, excessive precision and values outside the exact DECIMAL(10,3) representation. Native numeric inputs are validated after normal request decoding. No pounds field/conversion is introduced.

The smallest positive representable value is 0.001 and the largest is 9,999,999.999. These are **database precision/representation limits**, not approved biological/species limits. No dog/cat-specific minimum or maximum weight/height is invented. Over-precision is rejected instead of silently rounded to a different measurement. Inputs, validation messages and displays identify the canonical units.

- New staff HTTP pet creation requires an actual positive weight. Height and veterinary size may remain unknown at intake. Existing controlled synthetic seeders directly creating historical/demo models are unchanged and do not invent weights.
- Existing-record edits may retain missing weight/height, so an unrelated name/status/story correction is not blocked by historical unknown measurements.
- Omitted update fields retain their recorded values. Explicitly blank/null measurements intentionally clear them to unknown. This uses the existing version-checked update, not a public mass-update endpoint.
- Pet photo behavior, reservation-status restrictions, optimistic concurrency and assessment handling remain unchanged. A stale measurement edit cannot replace a newer version.
- Server write actions additionally reload the authenticated account's current active/verified staff authority. Guest/adopter, inactive/unverified and stale in-memory staff identities cannot bypass the checks.

## Size policy: existing versus pending

Size remains a **staff-entered veterinary classification**, not a weight-derived result. Dog classification concerns that dog's build; cat classification concerns that cat's build. Canine numerical cutoffs are not applied to cats. The UI explicitly says no numerical thresholds are approved in the current implementation and measurements do not automatically choose size.

No numerical threshold table, new classifier or historical reclassification exists. Shelter/project approval is still needed for separate dog/cat definitions, treatment of the established Extra Small/Extra Large labels, and any future migration/algorithm-version implications. Preserving the existing five-level scale does not claim the classifications have clinically validated weight definitions.

Height stays optional. Standing shoulder height is proposed as a consistent future shelter procedure, clearly labeled **approval pending**, not described as an already approved standard. The shelter should define posture, anatomical reference and recording procedure before relying on height for comparisons. No height is inferred from breed or weight; no measured-at/provenance history was invented in this task.

## Interface and public display

- Existing Add/View-Edit Health & Status step includes labeled kg/cm controls, technical decimal precision, optional-height/pending-method guidance, server field errors and existing tab/button validation behavior.
- View/Edit populates exact saved decimal values from animalData, and opening another pet clears prior values if its measurements are unknown. No JavaScript weight-to-size linkage exists.
- Separately routed create/edit forms include the same shared controls and the existing five-label size selection/guidance. Existing keyboard semantics, labels and image controls are preserved.
- Public pet detail and the shared browse/recommendation detail modal show recorded weight/height with units or Not recorded. No arbitrary values or admin-only data are added.
- Recommendation cards are not cluttered with new rows; their existing modal gives measurements. Listing filters/pagination and existing profile descriptions remain unchanged.

## Matching/KNN impact

No matching service, configuration, distance formula, weight, normalization, compatibility threshold, algorithm version or eligibility rule changes.

MatchingProfileMapper still maps physical_size through the same five-label configuration and produces the same six-dimensional pet vector. Neither new field enters the DTO, matching fingerprint, filters, penalties or scoring. A measured pet with missing size/assessments is not magically eligible. Height is never required for matching or adoption.

PetController keeps its existing summary/result refresh call after a profile edit. With unchanged canonical matching data, source fingerprints remain equal and persisted application scores, distances, penalties, breakdowns, algorithm versions and computation timestamps are not rewritten. Regression tests compare the entire match result, recommendation order, six-feature vector, stored application attributes and raw assessment records before/after a measurement-only HTTP edit. Three distinct assessors, complete recommendation pagination and all eligibility gates remain intact.

## Validation and deployment boundaries

Use only synthetic fixtures and disposable databases. Run the full Laravel suite, targeted pet/matching/permission regressions, Node frontend tests, PHP lint, Pint on changed PHP, Vite build and complete-source whitespace checks. For MySQL, isolate destructive MatchingDemoSeederTest in its own PHP process using the exact disposable database safety variable. Do not point QA at development/production databases or copy a real environment file.

Fresh migration and upgrade checks must preserve original pet rows/size and matching data, leave historical weight/height NULL, and verify precise decimal roundtrips on real MySQL 8.0. SQLite tests exercise application behavior; SQLite's numeric affinity is not proof of native MySQL DECIMAL semantics.

Existing successful HTTP-creation fixtures in three test files gain synthetic required weights; their original assertions and negative image/status/RBAC tests are retained. Seeders/factories and historical demo measurements are not rewritten.

Production deployment is not performed by this task. Before an authorized rollout: review/back up the actual target, coordinate schema/code, review all pending Phase 1-6 migrations, then apply the additive Phase 7 migration under the normal approved procedure. New pages/writes require these columns; cache clearing is not a substitute for migration. Build assets with the source change and review normal application/worker cache refresh procedures. No new environment variables or external APIs are required.

Real staff measurement technique, accuracy, separate species size definitions, private operational procedures, production MySQL/TLS/storage and broader accessibility/device testing remain policy/operational verification tasks. No source implementation or local test proves those production outcomes.
