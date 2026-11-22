# Frontend handoff: research participant experience

| File | What to review |
| --- | --- |
| `resources/views/application/apply.blade.php` | Both modes use the original required Government ID field and Adoption and Data Processing Agreement checkbox/modal. The study agreement discloses Google Vision and describes its actual storage and processing, without claiming real adoption obligations. |
| `resources/views/research/{_document-consent,unavailable}.blade.php` | Explicit consent for replacement-document processing and normal-style availability feedback. The research-only apply and Study Information templates were removed. |
| `resources/views/research/scenarios.blade.php` | My Applications uses existing card and status styling; preserves waitlist position, primary-only Continue, withdrawal, document status/replacement and links to owned handover/check-ins. |
| `resources/views/research/handover.blade.php` | Handover Status uses the same handover cards and receipt controls for the sidebar list and individual records. The list has an empty state and no application or document controls. Preserve the receipt form action, CSRF, and link to the matching check-ins. No actual delivery, physical proof, schedule or contact details. |
| `resources/views/monitoring/create.blade.php` | The same welfare fields and inline errors in both modes. Study milestones are accelerated and the study branch does not prompt for a camera or store videos. |
| `resources/views/layouts/app.blade.php`, `resources/views/partials/sidebar.blade.php` | No persistent research banner or Study Information link. Browse Pets is the default research entry after authentication. The sidebar distinguishes My Applications, Handover Status, and My Check-Ins on the shared research route, including `aria-current` for the active link. |
| `resources/views/recommendation/{results,_match-card}.blade.php`, `resources/views/animal/_pet-modal-content.blade.php` | Normal Adopt/Join Waitlist actions and no repeated study banner. Preserve KNN eligibility messaging. |

The normal form names and cascading-address hooks are unchanged. Preserve CSRF, required ID upload and `agreed_to_terms` on initial applications, explicit `document_consent` on replacement uploads, and the matching/ownership/ranking gates. Keep the example-ID guidance and the OCR and retention disclosures next to the upload or agreement. Do not label a text match as proof of identity or substitute a real release/camera action in study screens. Template spacing and typography can be adjusted without changing these contracts.

No new CSS or JavaScript. Bounded Chromium QA used desktop 1440×1000 and mobile 390×844 with synthetic data. Local screenshots are in the ignored `output/playwright/` directory; no real participant data was used. Review narrower viewport controls and terminology with the study team before the survey.
