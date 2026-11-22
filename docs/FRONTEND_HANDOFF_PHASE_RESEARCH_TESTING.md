# Frontend handoff: research participant experience

| File | What to review |
| --- | --- |
| `resources/views/application/apply.blade.php` | Both modes use the Adoption and Data Processing Agreement checkbox/modal. Only normal adoption has a required Government ID field. Research applications do not collect documents and describe how application and matching data are used. |
| `resources/views/research/unavailable.blade.php` | Normal-style availability feedback. The research-only apply and Study Information templates were removed. |
| `resources/views/research/scenarios.blade.php` | My Applications uses existing card and status styling; preserves waitlist position, primary-only Continue, withdrawal and links to owned handover/check-ins. Document status and replacement controls are no longer shown. The old `research/_document-consent.blade.php` partial was removed. |
| `resources/views/research/handover.blade.php` | Handover Status uses the same handover cards and receipt controls for the sidebar list and individual records. The list has an empty state and no application or document controls. Preserve the receipt form action, CSRF, and link to the matching check-ins. No actual delivery, physical proof, schedule or contact details. |
| `resources/views/monitoring/create.blade.php` | The same welfare fields and inline errors in both modes. Study milestones are accelerated and the study branch does not prompt for a camera or store videos. |
| `resources/views/layouts/app.blade.php`, `resources/views/partials/sidebar.blade.php` | No persistent research banner or Study Information link. Browse Pets is the default research entry after authentication. The sidebar distinguishes My Applications, Handover Status, and My Check-Ins on the shared research route, including `aria-current` for the active link. |
| `resources/views/recommendation/{results,_match-card}.blade.php`, `resources/views/animal/_pet-modal-content.blade.php` | Normal Adopt/Join Waitlist actions and no repeated study banner. Preserve KNN eligibility messaging. |

The normal form names and cascading-address hooks are unchanged. Preserve CSRF, the required `agreed_to_terms` agreement and the matching, ownership, and ranking gates in both modes. Keep ID upload and OCR disclosures in the normal flow only. The research backend rejects `document` uploads and no longer has a replacement endpoint. Do not substitute a real release/camera action in study screens. Template spacing and typography can be adjusted without changing these contracts.

No new CSS or JavaScript. Bounded Chromium QA used desktop 1440×1000 and mobile 390×844 with synthetic data. Local screenshots are in the ignored `output/playwright/` directory; no real participant data was used. Review narrower viewport controls and terminology with the study team before the survey.
