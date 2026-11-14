# Frontend handoff — historical current-size review in Pet Management

## User-visible correction

In the existing staff **Animal Profile → Health & Status** modal, the read-only size
field previously displayed a long historical status sentence, clipping inside its
single-line input. The normal pet edit form could save a corrected weight but could
not convert an old `legacy_unclassified` record into a trusted current-size
classification. A staff member had to use an operator command elsewhere.

The staff edit forms now use a compact field value: **Needs size review** for
historical records, **Not calculated** for other untrusted states, or the actual
current size for trusted records. Older records show **Verify recorded weight and
calculate current size when saving**, an opt-in checkbox. Saving with this option
validates the submitted species/weight, computes the approved `weight-v2` band,
preserves the original manual-size history, updates the trusted classification,
and adds an audit record. Without the checkbox, ordinary edits retain the
historical safety guard. New/current pets continue automatic classification.

The checkbox is staff-only. It is not a manual size override, does not predict adult
size, and does not change life stage or other matching safety rules. Invalid/missing
weight and stale revision errors do not promote a record.

## Exact frontend files to inspect

| File | Change | Keep intact |
| --- | --- | --- |
| `resources/views/partials/pet-calculated-size.blade.php` | Short input text; one-time review checkbox on standalone historical edit page. | Read-only derived size; no `physical_size` form submission. |
| `resources/views/partials/pet-size-guidance.blade.php` | Brief helper copy, so it fits the existing field. | Species/weight policy; not a health or adult-size prediction. |
| `resources/views/admin/animal/_view-modal.blade.php` | Hidden review block below the size field; visible only for legacy records. | Existing form, tabs, spacing, buttons and names. |
| `resources/views/admin/animal/index.blade.php` | Two server-owned metadata fields added to the existing modal JSON payload. | Escaped server data, authorization and existing payload fields. |
| `public/js/admin/animal.js` | Displays concise size state and resets/shows the review checkbox for the selected pet. | Modal population, species/weight controls, photo handling and tab events. |

No CSS, fonts, colors, global responsive styles or modal dimensions were changed.
There are no new endpoints or migrations. Backend authority is in
`PetController::update()`; `PetSizeClassifier` remains the only source of thresholds.

## Frontend QA checklist

- Open legacy pet with a valid weight (e.g. fictional Maple, cat 3.000 kg). Size
  should read **Needs size review**, with a visible unchecked review option.
- Switching to a trusted pet hides/disables the review control and displays its
  calculated size. Switching back does not inherit the previous checkbox state.
- On an approved historical review, save should show success; reopening should
  display the calculated size (cat 3.000 kg = **Small**).
- For a missing/invalid weight, the form shows a local validation error and does
  not silently reclassify the pet.
- Check that helper and checkbox text wrap at narrow modal widths. No other mobile
  readiness/header work is included.

Backend regression tests: `HistoricalPetSizeSafetyTest` for explicit opt-in,
missing weight, stale revision, forged size input, preserved history and permissions.
Frontend test: `tests/Frontend/admin-animal-size-review.test.js`.
