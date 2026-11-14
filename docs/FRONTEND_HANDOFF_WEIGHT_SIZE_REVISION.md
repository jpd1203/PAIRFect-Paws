# Frontend handoff: current measured-weight size revision

Base: ee3f407ad00a5afeb5a2a3337fcf736e91237fdd (integrated master, including main's
2296d4e UI fixes). Branch: feat/current-weight-size-revision. No redesign, CSS changes,
new input controls, layout adjustments or changes to questionnaires/navigation.

## Exact changed frontend files

| Path/component | Old behavior | New behavior / location to inspect |
| --- | --- | --- |
| resources/views/partials/pet-calculated-size.blade.php | Read-only label said calculated from adult weight. | Label is Current physical size (calculated from measured weight); input IDs/classes/read-only/no submitted size name remain. Used by staff create/edit and intake modals. |
| resources/views/partials/pet-size-guidance.blade.php | Required known adult age and described juvenile size as unavailable. | Text explains species/current weight regardless of age, separate life stage, no adult-size prediction, explicit historical update and no healthy-weight claim. Same paragraph/classes/position. |
| resources/views/recommendation/results.blade.php | Accessible size label/help said adult weight. | Accessible label is Current measured-weight size; live help explains current ranges, not predicted adult size. Same native selects, names, form method, IDs, server JSON and pagination. |
| public/js/recommendation-size-filters.js | Live helper used adult-weight wording. | Wording mirrors current-size policy. Selection/reset/pageshow/popstate behavior is unchanged; no thresholds added to JavaScript. |
| resources/views/animal/_pet-modal-content.blade.php | Displayed raw physical_size or Unknown even if historical/untrusted. | Size cell uses escaped size_display, including accurate missing-weight/update-needed states. Existing row/layout unchanged. |
| resources/views/pets/show.blade.php | Raw physical_size in public details. | Existing size detail uses escaped size_display, not a historical manual label as trusted current size. |
| resources/views/admin/assessment/_summary-modal-content.blade.php | Raw physical_size in staff summary. | Existing summary cell uses escaped size_display. Assessment completeness/safety warnings are preserved. |

Related presentation backends: Pet::getSizeDisplayAttribute(), MatchPresenter::reason()
and Admin/AssessmentController::summary() use accurate current-weight wording. There
are no new frontend API endpoints. Stored pet age/life_stage are NOT relabeled adult.

## Technical contracts — do not break

- PetSizeClassifier owns weight bands and rule version. Current size is species +
  measured kg; never expected adult size, breed-based size or healthy-weight status.
- Render size_display for untrusted/missing/stale states. Do not restore raw historical
  physical_size as a verified result or turn an unavailable classification into
  an assertion that the animal cannot be adopted.
- Read-only size input has no submitted physical_size name. Never add editable
  classification status/version/history fields. Server ignores forged HTTP metadata.
- Keep age_years/age_months and weight_kg/height_cm names, units/precision, old input,
  error feedback, CSRF, staff permissions and optimistic pet version fields.
- recommendationSpecies, recommendationSize, recommendationSizeHelp,
  recommendationSizeOptions and recommendationFilters IDs bind existing scripts.
  Species values remain empty/Dog/Cat. Size values remain empty or the five exact
  category names; kg ranges are labels only, supplied by backend JSON.
- All Species permits All Sizes only. Species changes reset size; native keyboard,
  browser Back/Forward and restored GET state remain supported. Invalid requests
  still fail server-side. Preserve include_ineligible and pagination query parameters.
- A current size does not bypass medical, three-distinct-assessor, life-stage/safety,
  availability, household or verified-adopter rules. KNN calculation is server-owned.
- Valid v1 adults stay readable during upgrade. Historical records require explicit
  operator normalization, not a frontend checkbox or ordinary form-save bypass.

## Browser evidence and styling review

Bounded Chromium QA used a NEW disposable SQLite fixture database and array mail:
fictional adopter login -> existing completed intake -> recommendations (11 eligible),
Dog/Medium filter (2 results including the juvenile), keyboard Dog -> Cat reset with
cat ranges, Apply, Back to Dog/Medium and Forward to Cat/All Sizes. Browser console:
zero errors/warnings. All Species disabled-size state was observed before filtering.

390x844 screenshot, captured and visually reviewed, is local ignored evidence:
output/playwright/current-weight-dog-medium-390.png. CLI snapshots are under the
same worktree's ignored .playwright-cli directory; screenshots are NOT in the commit.
The existing scroll container means this screenshot covers the visible viewport,
not every offscreen card. Juvenile inclusion is proven by rendered snapshots and
backend/browser filter results, not by claiming the screenshot shows all content.

No spacing, dimensions, fonts, colors or responsive rules were changed. Groupmates
may restyle these existing cells/help text while preserving labels, IDs, escaping,
field values and backend guards. Check wrapping of long missing-weight/update-needed
messages in pet modals and staff summaries at small widths. No broad accessibility
or physical-device certification is claimed. No unrelated mobile readiness/header
work was performed; the base already includes the group's integrated UI changes.

See [implementation, validation and operator runbook](CURRENT_WEIGHT_SIZE_REVISION.md).
