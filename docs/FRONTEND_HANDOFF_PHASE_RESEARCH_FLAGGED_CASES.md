# Frontend handoff: research Flagged Cases

The original navigation, Monitoring tables, Flagged Cases cards, return dialog, report modal, and styles are reused. No CSS file, bundled camera code, or standalone JavaScript file changed.

| File | Functional change and review location |
| --- | --- |
| `resources/views/admin/monitoring/index.blade.php` | Both monitoring table layouts show the original Flag/Review buttons for submitted research reports. The report modal uses the research resolution endpoint, private video link, and a plain-text case history. Future milestones are not advertised after a recorded return. |
| `resources/views/admin/monitoring/flagged.blade.php` | Original flagged cards render research reasons, attribution/history, follow-up, private video, and the original resolution form. Unscheduled research reports do not show a fabricated due date or reminder count. |
| `resources/views/admin/monitoring/_resolution-fields.blade.php`, `resources/views/admin/monitoring/_return-confirmation.blade.php` | Original choices, required fields and confirmation dialog. Only return-related wording changes in Research Mode: this records a scenario outcome, not a physical custody transfer. |
| `resources/views/partials/sidebar.blade.php`, `resources/views/monitoring/flagged-notice.blade.php` | Original Flagged Notice entry and count after a completed research handover. The notice links adopters to the check-in report to reply. |
| `resources/views/monitoring/my-checkins.blade.php`, `resources/views/monitoring/_report-view-modal-content.blade.php` | Existing badges and owned report modal show outcomes and follow-up. The existing follow-up form uses the research-only endpoint. A recorded return stops future check-ins but leaves earlier reports visible. |

Keep form method, CSRF, state, allowed field names, and the regular five outcome options. Preserve `data-resolution-form`, `data-return-fields`, and `confirm_return`; the backend requires confirmation, a valid Manila calendar date, reason, condition, and an active staff handler for the return outcome. Keep the alternate research action URLs; numeric IDs from normal and research records can overlap. Never resolve a research URL via a normal monitoring ID or expose reasons/URLs from another adopter. Maintain escaped Blade output and DOM text binding for notes and history.

Keep the current desktop/mobile structure. Spacing and wrapping can be reviewed in the original components; no unrelated mobile or styling changes were made. Desktop Chromium evidence from the synthetic browser review lives in ignored `output/playwright/`: `research-flagged-admin.png`, `research-flagged-followup.png`, `research-flagged-adopter-modal.png`, `research-flagged-return-confirmation.png`, and `research-flagged-returned-checkins.png`.
