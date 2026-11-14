# Frontend handoff — automatic life stage

Completed November 15, 2026 (Asia/Singapore). Base 3673aa13c3bafe00eea1794ca7ea2fbb3fcde0fa;
branch feat/automatic-pet-life-stage. No redesign, CSS, typography, color or spacing
changes. Life stage is a simplified application category, not a veterinary diagnosis.

## Exact frontend inventory

| File | Element / change |
| --- | --- |
| resources/views/partials/pet-life-stage.blade.php (new) | Shared labelled read-only text input with no name, unique ID/help ID, backend policy data and Unknown state. Uses existing form-control styling. |
| resources/js/pet-life-stage.js (new) | Preview reads backend-provided JSON thresholds; reacts to species/age input/change, reset, pageshow and editor-prefill event. No independent threshold table. |
| resources/js/app.js | Imports preview for standalone create/edit pages. |
| resources/js/admin.js | Imports the same preview for staff modal forms. Binding guard prevents duplicate handlers. |
| resources/views/admin/animal/_add-modal.blade.php | Manual Life Stage select replaced in its existing Health & Status cell by addLifeStage read-only partial. |
| resources/views/admin/animal/_view-modal.blade.php | Manual select replaced in the same cell; existing vLifeStage ID retained. |
| resources/views/pets/create.blade.php | Adds shared createLifeStage output in a form-group after measurements; existing age fields unchanged. |
| resources/views/pets/edit.blade.php | Adds shared editLifeStage output with saved server value; age fields unchanged. |
| public/js/admin/animal.js | After filling species/year/month values, dispatches pairfect:pet-fields-loaded on viewAnimalForm rather than inserting an editable/manual stage. |
| resources/views/admin/assessment/_summary-modal-content.blade.php | Displays the canonical age_group label; Unknown replaces the misleading Not verified wording for unknown age. |

Frontend tests: tests/Frontend/pet-life-stage.test.js (new); admin-toast.test.js only
updates its VM import stripping for the new module, retaining every toast assertion.
No navigation, questionnaires, recommendation dropdowns or other modals redesigned.

## Behavior and contracts to preserve

- Before: staff could independently submit life_stage; age_group used inconsistent
  uniform ranges. After: backend derives young/adult/senior from recorded species/age.
- Dogs: young <24 months, adult 24–<96, senior >=96. Cats: young <12, adult 12–<132,
  senior >=132. These thresholds live ONLY in PetLifeStageClassifier::THRESHOLDS.
- Unknown age/species yields Unknown/null, never Adult. Both blank age fields mean
  unknown; recorded 0 years/0 months is a known young animal. Invalid inputs remain
  backend validation errors; previews do not grant permission to submit invalid ages.
- Input names species, age_years, age_months and optimistic version are unchanged.
  life_stage has NO form name. Older/forged manual values are ignored server-side,
  including arrays; they cannot overwrite the calculated stage.
- Keep data-life-stage-output/data-life-stage-policy, field labels, read-only state,
  IDs and help association, or coordinate every script/test caller when changing them.
  Each output resolves inputs inside its own form, not another modal's age controls.
- Preserve pairfect:pet-fields-loaded after programmatic editor prefill. Setting
  .value alone does not emit input/change. Retain reset/pageshow recalculation and
  binding guard. The server remains authoritative without JavaScript; no-JS forms
  show saved/initial values and recalculate on save, not an unsaved live preview.
- Pet::life_stage and age_group now derive from the same policy on reads, including
  historical contradictory rows, without writing those rows. Raw stored snapshots
  must be read with getRawOriginal('life_stage') for audits, not the computed accessor.
- Keep CSRF, role/active/verified guards, old input, error feedback and optimistic
  locking. Weight-v2 remains independent of age and these life-stage labels.
- Life stage affects existing financial/safety exclusions, not distance features.
  Unknown age is still incomplete safety information; terminal scores are immutable.

## Browser evidence / visual review

Dedicated Chromium + fictional local staff, new disposable SQLite DB and array mail:
create-form Dog 23 months -> Young; changing species to Cat -> Adult; Cat 132 months
-> Senior. DOM checks proved readOnly=true and name="". Actual edit modal initially
showed Adult from prefill, then Young after changing Dog age to 23 months. No browser
form was submitted. Console had zero errors/warnings. Browser/server stopped afterward.

Captured and visually reviewed local ignored screenshot:
output/playwright/life-stage-edit-young.png. It shows the existing Health & Status
grid and Young output. Evidence is NOT committed; request the local file if needed.
No physical-device, broad mobile or accessibility certification is claimed.

Groupmates may restyle the output/help within existing cells; retain technical
bindings and backend contracts. Review long helper wrapping on narrow screens.
Existing physical-size placeholder text still mentions age in the standalone blank
state; that inherited wording is a separate frontend follow-up, not changed here.
No unrelated readiness clipping/header work was undertaken.

See [backend policy and normalization](PET_LIFE_STAGE.md).
