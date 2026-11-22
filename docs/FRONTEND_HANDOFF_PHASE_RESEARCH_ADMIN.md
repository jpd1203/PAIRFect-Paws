# Research workflow in the existing admin interface

No new admin navigation, routes, stylesheets or layout. The existing participant-facing screens and their no-ID policy are unchanged.

| Files | What changed / what to inspect |
| --- | --- |
| `resources/views/admin/application/index.blade.php`, `_review-modal.blade.php`, `public/js/admin/application.js` | Keep the current table, filters and View modal. Rows show the same status and queue data as the adopter view. The modal exposes recorded applicant details and primary-assignment time; automatic approval is identified as system activity. The existing interview button is disabled and incompatible modal actions are hidden for read-only records. |
| `resources/views/admin/compatibility/index.blade.php` | Keep the original cards and score breakdown. Research ranks use the complete eligible waitlist, even if another screen filters rows out. Document links are absent where no private document is available. Historical outcomes may display only when stored. |
| `resources/views/admin/handover/{index,show,_pipeline,_next-action,_recorded_release,_adopter_confirmation}.blade.php` | Keep the list, filters, badges, pipeline, detail cards and history. Actual approval, ready and confirmation timestamps are shown. Never present automated readiness as verified physical release or render staff delivery, identity, reminder or reopening controls for these records. |
| `resources/views/admin/monitoring/index.blade.php` | Keep pet/adopter grouping, search, filters, expandable rows and the report modal. Use actual answers and submission times. Pending milestones have no due date; all staff-side interventions are disabled. |
| `resources/views/admin/animal/_view-modal.blade.php` | Same edit modal and availability field; neutral queue-managed availability hint. Photo editing remains unchanged. |

Do not remove or bypass the server-side research action guard when restyling. The `workflowReadOnly` flag (serialized as `read_only` in the applications modal) determines which links/actions to expose. Numeric IDs from research projections are not IDs for real release, evidence or report intervention endpoints. Keep the existing CSRF and staff/verification protections. Only render a document or physical action link if its real record exists and the workflow permits it. Use the supplied effective statuses and dates rather than synthesizing approval, due dates, media, or staff confirmation.

Playwright evidence from the isolated synthetic MySQL environment is in ignored `output/playwright/`: dashboard, application review, handover detail, and submitted report. Chrome was checked at 1440×1000 and 390×844; staff login, application search/status filtering and review, handover, and the report modal were exercised. No real respondent information was used. No styling or responsive overrides were introduced; visual follow-ups belong to the frontend team.
