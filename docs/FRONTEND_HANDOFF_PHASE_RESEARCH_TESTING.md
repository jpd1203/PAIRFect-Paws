# Frontend handoff: shared research testing

| Changed file | Study-specific behavior |
| --- | --- |
| `resources/views/components/navbar.blade.php` | The public navigation no longer includes the research catalog sentence. Navbar links and layout are unchanged. |
| `resources/views/layouts/app.blade.php` | Keeps the authenticated research notice when the mode and dedicated database are active; retain explicit simulation labels on research flows. |
| `resources/views/partials/email-verification-banner.blade.php` | Suppress the verification banner only for active research adopters. |
| `resources/views/partials/sidebar.blade.php` | My Applications/Handover lead to the research scenarios. The normal unavailable toast no longer intercepts the research handover link. |
| `resources/views/recommendation/intake.blade.php`, `resources/views/recommendation/results.blade.php` | Remove misleading verification guidance for research adopters and describe simulated review. |
| `resources/views/recommendation/_match-card.blade.php`, `resources/views/animal/_pet-modal-content.blade.php` | Distinguish Simulate Adoption and Join Waitlist. Retain the existing eligibility reasons and disabled actions. |
| `resources/views/research/apply.blade.php` | Submit an application or join a waitlist. No document upload or real handover data. |
| `resources/views/research/scenarios.blade.php` | Status, ranked waitlist position, Withdraw, primary-only Continue, confirmation, and three successive practice reports. |
| `resources/views/admin/animal/_view-modal.blade.php` | Support Unknown vaccination and explain that photo/record edits do not change research availability. |

Preserve the existing register form, CSRF and consent checkboxes. Keep the study labels, POST methods, ownership checks, route parameters, and form names: check-ins submit only `milestone`, `appetite`, and `adjustment`. Owner, eligibility, ranking, approval, and reservation status are all server-controlled. Never enable Continue for a waitlisted respondent or real handover actions for simulated records. Staff verification is unchanged.

No JS or CSS files changed. Groupmates can adjust layout, spacing, or typography while retaining those contracts. Local Chromium evidence: 1440×1000 registration and questionnaire through reservation; a separate 390×844 session joined that pet's waitlist, was promoted after withdrawal, and completed the simulated receipt and all three reports. This wasn't a real-device compatibility audit.
