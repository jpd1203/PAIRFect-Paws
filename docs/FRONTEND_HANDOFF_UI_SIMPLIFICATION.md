# Frontend handoff — admin UI simplification

## Workspace and scope

- Base: `origin/master` at `93d1d98d7efa0a1d3c00bdc8212de1c47215396d`, fetched before implementation.
- Local branch: `ui/admin-handover-simplification-2026-11-16`.
- Worktree: `output/admin-ui-simplification-2026-11-16` under the original checkout.
- Initial implementation and QA were completed locally without committing, pushing,
  merging or deploying. Subsequent authorized publication is recorded in Git history.
- Final preservation check: all **31 pre-existing worktrees**, their HEAD/status values and
  **120 changed-file SHA-256 hashes** matched the initial snapshot.
- No controllers, services, models, routes, migrations, configuration or dependency manifests changed.
- QA used only in-memory SQLite and this worktree's separate synthetic `database/ui-demo.sqlite`.
  Mail used the array transport, never real SMTP. Existing databases were not accessed.

## Exact frontend inventory

| File | Component and change |
| --- | --- |
| `resources/views/admin/handover/show.blade.php` | Page hierarchy; read-only current-state/next-action presentation; status badge; error summary; expandable adopter details and handover log; responsive two-column/stacked layout; disclosure script include. |
| `resources/views/admin/handover/_next-action.blade.php` (new) | Concise next action and deep link, with prominent expiry/transport concern warnings and one monitoring-lock explanation. |
| `resources/views/admin/handover/_pipeline.blade.php` | Four compact stages using existing model states, completion/check/lock icons, `aria-current`, two mobile columns and four desktop columns. |
| `resources/views/admin/handover/_schedule.blade.php` | Current schedule remains prominent; secondary native proposal disclosure; opens for missing/expired/unconfirmed schedules or proposal errors; compact interview identity and expandable final pickup identity; corrected reschedule-review heading. |
| `resources/views/admin/handover/_delivery-clearance.blade.php` | Replaces repeated cards with destination/availability summary, expandable metadata and completed forms, open incomplete forms, compact requirement count/list and field-level errors. Approved address remains accessible. |
| `resources/views/admin/handover/_transport-investigations.blade.php` | Compact no-active-issues disclosure; opens for active/overdue concerns and failed incident submissions; unresolved cases shown first; resolved case bodies, release context and reference timelines expandable. Resolved-only history is no longer called active. |
| `resources/views/admin/handover/_release_form.blade.php` | Expandable blocked/available release form; visible prerequisites when expanded; shorter method/tracking guidance; field-associated errors; preserved courier controls, staff selection, proof and release action. Method mismatch disables submission with a rescheduling explanation. |
| `resources/views/admin/handover/_adopter_confirmation.blade.php` | Stable deep-link destination and concise received/awaiting wording. Existing receipt/reminder/reopening actions remain. |
| `resources/views/admin/application/_identity-form.blade.php` | Adds one missing closing `div` so the existing form nests correctly inside the pickup disclosure. No identity fields or policies changed. |
| `public/js/admin/handover-disclosure.js` (new) | Opens ancestor disclosures for deep links, restored navigation, native validation and failed submissions; adds accessible plaintext errors without duplicating existing field errors. Attaches only on the marked handover page. |
| `resources/views/admin/animal/_add-modal.blade.php` | Compact Health & Status guidance and one optional technical-details section; all controls, values, names and IDs preserved. |
| `resources/views/admin/animal/_view-modal.blade.php` | Same concise guidance and optional details in the existing edit tab; saving/version behavior unchanged. |
| `resources/views/admin/animal/_health-field-guidance.blade.php` (new) | Native “About these fields” disclosure containing classification, documented-history, measurement and veterinary caveats. |
| `resources/views/partials/pet-calculated-size.blade.php` | Optional compact field label; size remains read-only and unsubmitted. |
| `resources/views/partials/pet-size-guidance.blade.php` | Optional “Automatically calculated from species and weight.” Full guidance remains the default outside the modal. |
| `resources/views/partials/pet-life-stage.blade.php` | Optional compact label/help; backend-owned threshold data and preview hooks unchanged. |
| `resources/views/partials/pet-measurement-inputs.blade.php` | Optional short kg/cm instructions; numeric bounds, precision, required-weight behavior and errors unchanged. |

## Before / after by section

| Section | Before | Now |
| --- | --- | --- |
| Overview | Four descriptive stage cards plus repeated monitoring explanation. | Compact four-stage status indicator and one current-action panel. |
| Schedule | Confirmed schedule and complete proposal form visible together. | Confirmed schedule shown; “Propose another schedule” expands the form. Missing/expired schedules prioritize proposing a window. |
| Identity | Repeated heading, explanatory copy and review button. | Verification status plus View Verification; instructions appear when incomplete. Completed pickup verification form collapses but remains available. |
| Delivery | Multiple repeated status/metadata cards and both forms always visible. | Two status lines; approved address and reviewer/contact metadata in details; only incomplete or errored forms open. Successful availability is visible without reopening the completed form. |
| Release requirements | Six full requirement cards, including completed items. | Count of existing requirements on record, missing items first, full list expandable. This is not a new score or release authorization. |
| Investigation | Entire empty/history/policy section visible; resolved history could show an active label. | Compact closed no-issue summary; current concerns/overdue follow-up visible and actionable; reference/history details secondary. |
| Release | Large form even when prerequisites fail; repeated explanations. | Blocked summary and actionable prerequisites; ready form opens; all existing submission fields remain. Receipt remains separate. |
| Additional details | Full adopter card and whole log always displayed. | Separate native disclosures; newest-first history, actor, Manila timestamps and escaping preserved. |
| Animal Health & Status | Long helper paragraphs under individual fields. | Short hints, with technical explanations in one optional section. |

## Technical contracts — do not break during redesign

1. The backend remains authoritative. The next-action panel and disabled button are presentation,
   not replacement authorization. Do not bypass server validation, add a grace period or require
   time to be after the scheduled start. Expiry uses existing Manila helpers and the stored window end.
2. Keep the existing pipeline model keys, `current_step`, `completed_steps` and `monitoring_locked`.
   Status wording must not imply that address/availability alone authorize release.
3. Keep all existing POST routes, CSRF, `schedule_version`, `clearance_version`,
   `destination_fingerprint`, `recipient=adopter`, incident versions and release-attempt fields.
   Never render fingerprint values as ordinary status text or remove them from protected forms.
4. Keep schedule inputs `method`, `date`, `start_time`, `end_time`, `option_index`, `decision`
   and IDs `schedule-date`, `schedule-start`, `schedule-end`. A changed proposal needs new consent.
5. Keep readiness IDs `deliveryContactMethod`, `deliveryAvailable`, `deliveryReadinessNotes`
   and their corresponding `...Error` IDs; keep input names `contact_method`, `available`, `notes`.
   Direct adopter contact only; “Other” still requires notes server-side.
6. Keep deep-link IDs `scheduleProposal`, `rescheduleReviewSection`, `identityVerificationSection`,
   `pickupIdentityDetails`, `deliveryAddressReview`, `deliveryReadiness`, `releaseFormSection`,
   `adopterConfirmationSection`, `transportInvestigations`, `transport-case-<id>` and `handoverLog`.
   `deliveryReadiness` now anchors the readonly status; its form disclosure is `deliveryContactUpdate`.
   Successful redirect therefore shows the status without forcing a completed form open.
7. Keep `handoverForm`, `courierFields`, provider/tracking/date/time/staff inputs, proof controls
   and their existing JS functions. Delivery fields remain hidden/disabled for pickup.
   The release method must still match the confirmed agreement.
8. `handover-disclosure.js` depends on `[data-handover-page]`, escaped JSON error metadata and
   stable form IDs. `_ui_form` is an untrusted UI-only submission marker, used only to reopen
   the affected form. It is not added to validated business payloads or used for permission checks.
   Error text uses `textContent`, never `innerHTML`. Native details/summary work without JS;
   incomplete/errored forms also open server-side using existing old-input state.
9. Keep size/life-stage fields read-only with no submitted manual value; keep `weight_kg`,
   `height_cm`, age components, `version`, existing `v*`/`add*` IDs and `data-life-stage-*` hooks.
   The optional `compactGuidance` flag changes copy only; default shared-partial behavior remains.
10. Keep the approved application address snapshot, not the adopter's current profile address.
    Preserve audit/history data and the escaped `HandoverHistoryLabel::forStaff` presentation.
    Queued notification intent must not be described as confirmed email delivery.

## Styling and responsiveness

- No global CSS, colors, fonts, modal dimensions or application layout files changed.
- Uses existing cards/buttons/status colors, native details markers, responsive grids,
  `min-w-0`, wrapping text and smaller section padding.
- The handover page stacks on mobile; pipeline uses two columns at narrow widths.
- Native disclosures support keyboard Enter/Space and expose expanded state to browsers.
- Frontend developers may restyle spacing, summaries, typography and responsive arrangement,
  but must preserve the contracts, disclosure/error behavior and all actionable warnings above.

## Automated validation

- Final complete disposable SQLite run: **1,003 passed, 8 skipped**; **8,468 assertions**.
  The eight skips are MySQL-only tests, not concurrency evidence.
- Separate destructive `MatchingDemoSeederTest`, on its own in-memory database:
  **1 passed, 23 assertions**. Combined: **1,004 passed**, eight skips.
- **32 frontend tests passed**, including new disclosure/hash/validation/plaintext-error cases.
- Pint passed for the two changed PHP test files; PHP lint passed for those files.
- Blade compilation passed; all **174 compiled Blade PHP files** passed syntax lint.
- Vite production build and `git diff --check` passed.
- No MySQL suite was run for this local-only UI task. No production behavior or external
  mail delivery is claimed by these results.

Test source inventory:

- `tests/Feature/AdminHandoverPresentationTest.php` (new): missing/proposed/confirmed/expired
  windows; wrong confirmer; pickup identity; unavailable adopter; active/resolved/overdue cases;
  receipt separation; pending rescheduling; errors; escaping; read-only render integrity.
- `tests/Feature/HandoverReadinessFeedbackTest.php`: timeline selector changed to its new
  stable `details#handoverLog`; existing evidence/escaping/order assertions remain intact.
- `tests/Frontend/handover-disclosure.test.js` (new): deep links, native invalid events,
  submit marker, associated plaintext errors and duplicate-error prevention.

## Actual browser QA

Chromium via the Playwright CLI, with synthetic accounts/records only:

- Desktop **1440×1000**, mobile **390×844**.
- Confirmed future delivery: completed proposal/address/contact forms closed; release action
  available for collecting required courier fields. Expired schedule: proposal open, release disabled.
- Destination review submitted successfully. “Other” direct contact without notes rejected;
  affected form exposed, field marked invalid and described by its error, with no duplicate error.
- Valid contact submitted successfully: status Confirmed, completed form closed; wording separates
  notification intent from inbox delivery and from release.
- Delivery released through the real UI before the future window start using synthetic courier
  details: accepted, release form removed, Awaiting adopter receipt shown; monitoring stayed locked.
- Overdue investigation opened with concern/follow-up content available.
- Deep-link `#handoverLog` and browser back/forward revealed/focused the log.
- Health & Status modal checked at both widths; Enter closed and Space reopened its native
  guidance disclosure. Synthetic weight edited from **12.000 to 7.000 kg** through Save Changes;
  server response stored **Small**, kept Adult life stage and incremented the record version.
- Measured mobile document/main widths **390/390px**, animal modal **375/375px**:
  no horizontal overflow on the checked views. Contact validation page console: zero errors/warnings.

Screenshots are local, ignored QA artifacts (not shipped):

- `output/playwright/handover-ready-desktop.png`
- `output/playwright/handover-ready-mobile.png`
- `output/playwright/handover-expired-mobile.png`
- `output/playwright/handover-validation-mobile.png`
- `output/playwright/handover-concern-mobile.png`
- `output/playwright/animal-health-desktop.png`
- `output/playwright/animal-health-mobile.png`

## Remaining frontend / operational review

- Browser results are bounded Chromium checks, not Safari/Firefox coverage or accessibility certification.
  Real screen-reader testing, wider device coverage and slow-network behavior remain unverified.
- Native select values may truncate longer option wording in the desktop three-column animal grid;
  the full choices remain available in the dropdown. The team may shorten display labels without
  changing option values or replacing Unknown with an assumed No.
- The existing shared schedule label omits the end date for a window crossing midnight.
  No date/time policy or shared adopter view was changed here; consider a separate presentation review.
- Existing prototype fixtures can contain blank health/vaccination values. Required health selection
  remains enforced; the refinement does not invent health findings or backfill data.
- Other inherited app-wide mobile/header styling was not redesigned. Long Health & Status content
  remains scrollable inside the existing modal body above its footer.
- Any deployment, production-data verification or real email test requires separate authorization.

Local preview: `http://127.0.0.1:8016/login`, synthetic account
`ui-admin@example.test`, password `UIPrototype2026!`. These are disposable QA credentials,
not production credentials. The local runtime files, database, dependencies and screenshots are ignored.
