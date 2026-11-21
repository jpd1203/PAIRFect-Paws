# Frontend handoff: main UI integration

## Scope and provenance

Integrates the UI patch from main commit `03a53a15175f3979c008908cc20e214a9edf7d6d`
onto master `a2d1e1f81d7f66d0bc4ebe9668d9b03416916041`.
This is not a replacement of master with main. No backend rules, routes,
migrations, dependencies, or deployment workflows change.

## Exact frontend inventory

| File | Functional change / review location |
| --- | --- |
| `public/js/admin/application.js` | Apply button runs existing combined filters and updates URL parameters. |
| `resources/js/admin.js` | Generic row filtering leaves the application's dedicated date/status filtering alone. |
| `resources/views/admin/application/_decision-modal.blade.php` | Restore standard decision-button styling classes. |
| `resources/views/admin/application/_identity-form.blade.php` | Restore identity button icon using the installed Free icon set. |
| `resources/views/admin/application/_review-modal.blade.php` | Restore action icons; preserve existing role gates. |
| `resources/views/admin/application/_schedule-modal.blade.php` | Restore confirmation icons and alignment. |
| `resources/views/admin/application/index.blade.php` | Responsive filter controls, date-picker affordance, Apply interaction, Manila-local row date. |
| `resources/views/admin/handover/_adopter_confirmation.blade.php` | Restore reopen icon/alignment. |
| `resources/views/admin/handover/_transport-investigations.blade.php` | Compact spacing, right-aligned evidence/actions, mobile-width controls. |
| `resources/views/admin/handover/index.blade.php` | Scrollable filter tabs with labeled arrow buttons. |
| `resources/views/admin/monitoring/index.blade.php` | Restore reminder/flag/outcome icons and outcome alignment. |
| `resources/views/handover/confirm.blade.php` | Prefer existing legacy proof URL when present; label profile-photo fallback accurately. |
| `resources/views/handover/status.blade.php` | Same proof/profile presentation as receipt page. |
| `resources/views/recommendation/results.blade.php` | Responsive species/size/Apply controls; preserve current-weight wording. |

Regression coverage: `tests/Frontend/main-ui-integration.test.js` executes the
actual application filter implementation with synthetic DOM fixtures and checks
export, keyboard submission, Clear, filter isolation, size language, and icon selection.

## Contracts to preserve

- Keep application filter IDs, form field names, status values, export action,
  dedicated filter ownership, and URL parameters unchanged.
- Keep recommendation species/size values, current measured-weight policy,
  classification guards, eligibility reasons, ranking, and pagination intact.
- Keep master’s compact handover pipeline, collapsed reference sections,
  unresolved-first ordering, resolved-case disclosures, and overdue warnings.
- Preserve CSRF, authorization, static receipt confirmation, and release gates.
- Proof presentation adds no access route: current private `proof_path` remains
  staff-only. Only an already available legacy `proof_url` takes precedence;
  otherwise the existing profile-photo fallback remains.
- Keep accessible labels, keyboard-native controls, and scroll-arrow labels.

Frontend developers may restyle these controls while retaining those contracts.
No unrelated layout or questionnaire changes are included.

## Browser evidence and limits

Synthetic local SQLite data and array mail only. At 390px, combined application
date/status filtering returned the intended row without document overflow.
Desktop export downloaded a CSV containing that filtered synthetic application.
Handover tabs overflow within their scroll container rather than the page.
The identity icon rendered with `fa-shield-halved`; main's `fa-shield-check`
was unavailable in the installed Font Awesome Free set.

Ignored local screenshots: `output/playwright/application-filters-mobile.png`,
`application-desktop.png`, `handover-mobile.png`, `handover-tabs-mobile.png`.
These are bounded checks, not full accessibility or device certification.
Existing deferred mobile issues are not addressed. Private proof delivery and
other backend changes require a separate authorized task.
