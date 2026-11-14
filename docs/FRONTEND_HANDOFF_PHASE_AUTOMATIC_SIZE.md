# Frontend handoff — automatic size recalculation

This supersedes the checkbox workflow described in
`FRONTEND_HANDOFF_HISTORICAL_SIZE_REVIEW.md`.

## Exact UI files changed

- `resources/views/partials/pet-calculated-size.blade.php`: removed the historical
  review checkbox and its error block. Size remains read-only and is never submitted.
- `resources/views/partials/pet-size-guidance.blade.php`: explains that saving a
  changed species or measured weight updates size, independently of age. Retains
  the distinction from health assessment and predicted adult size.
- `resources/views/admin/animal/_view-modal.blade.php`: removed the modal's duplicate
  review checkbox; existing species, weight and save controls remain unchanged.
- `public/js/admin/animal.js`: removed checkbox state and required-weight toggling.
  Shows the server-classified size or `Not calculated`, never the raw historical label.

## Backend purpose and contracts

`PetSizeClassifier::changes()` detects actual species/weight changes for both model
saves and version-checked controller updates. Equivalent numeric weights do not
reclassify historical records. Unrelated edits retain historical classification.
Changed measurements use the existing `weight-v2` species thresholds. Missing
weights never generate a size. The original manual label remains in
`legacy_physical_size`; staff recalculation adds an audit entry. Authorization,
optimistic concurrency, KNN formula and recommendation safety checks are unchanged.

The size value is refreshed from the backend after save, not calculated in JavaScript.
Keep `weight_kg`, `species` and `version` field names, decimal precision, server
validation feedback and read-only/no-name size input. The existing modal payload's
`has_classified_size` flag is authoritative for whether `physical_size` may be shown.

## Frontend review

Inspect the size field below weight in Animal Profile / Health & Status and the
standalone pet edit form. Verify no review checkbox remains, and that save/reopen
shows the recalculated category. Invalid weights and stale submissions must still
show validation errors. Unchanged historical measurements remain `Not calculated`.

No CSS, layout, dimensions, colors or responsive rules changed. The team may restyle
helper text without changing these contracts. Browser screenshots were not captured
for this revision; automated frontend checks cover modal population and controls.
