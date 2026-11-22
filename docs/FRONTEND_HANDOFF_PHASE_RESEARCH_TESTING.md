# Frontend handoff: research participant experience

| File | What to review |
| --- | --- |
| `resources/views/application/apply.blade.php` | Both modes require a file. The study accepts a supporting PDF/JPG/PNG without requiring a government ID, and requires separate document-processing consent. Live adoption still uses its original ID wording. |
| `resources/views/research/{study,_document-consent,unavailable}.blade.php` | One-time study introduction, explicit consent for document processing, and normal-style availability feedback. The original research-only apply template was removed. |
| `resources/views/research/scenarios.blade.php` | My Applications uses existing card and status styling; preserves waitlist position, primary-only Continue, withdrawal, document status/replacement and links to owned handover/check-ins. |
| `resources/views/research/handover.blade.php` | Normal handover card/header and receipt confirmation structure; no actual delivery, physical proof, schedule or contact details. |
| `resources/views/monitoring/create.blade.php` | The same welfare fields and inline errors in both modes. Study milestones are accelerated and the study branch does not prompt for a camera or store videos. |
| `resources/views/layouts/app.blade.php`, `resources/views/partials/sidebar.blade.php` | No persistent research banner. A small Study Information link remains and Handover and Check-ins lead to the appropriate records. |
| `resources/views/recommendation/{results,_match-card}.blade.php`, `resources/views/animal/_pet-modal-content.blade.php` | Normal Adopt/Join Waitlist actions and no repeated study banner. Preserve KNN eligibility messaging. |

The normal form names and cascading-address hooks are unchanged. Always preserve CSRF, the independent `study_consent` and `document_consent` inputs, and the matching/ownership/ranking gates. Do not label a text match as proof of identity or substitute a real release/camera action in study screens. Template spacing and typography can be adjusted without changing these contracts.

No new CSS or JavaScript. Bounded Chromium QA used desktop 1440×1000 and mobile 390×844 with synthetic data. Local screenshots are in the ignored `output/playwright/` directory; no real participant data was used. Review narrower viewport controls and terminology with the study team before the survey.
