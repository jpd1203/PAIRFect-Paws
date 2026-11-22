# Frontend handoff: research workflow restoration

Existing adopter and administrator layouts, classes and components were reused. No CSS files or production camera JavaScript were changed.

| Files | What to review |
| --- | --- |
| `resources/views/application/apply.blade.php`, `resources/views/research/scenarios.blade.php`, `resources/views/legal/privacy-content.blade.php` | Optional government ID control inside the original application; conditional, separate document-processing consent and field errors; truthful result and no replacement form. The linked Privacy Notice explains the processor and retention contact. |
| `resources/views/admin/application/index.blade.php`, `resources/views/admin/application/_review-modal.blade.php`, `public/js/admin/application.js` | Existing Documents panel receives research OCR reasons and file metadata; uses text binding and shows the protected original only if an admin has access. |
| `resources/views/handover/status.blade.php`, `resources/views/handover/_schedule-actions.blade.php`, `resources/views/handover/confirm.blade.php`, `resources/views/research/handover.blade.php` | Original handover method cards, schedule controls, progress, receipt photo preview and notes, and safe retry after Not Received. Inspect cards, the reschedule form, and receipt actions at narrow widths. |
| `resources/views/admin/handover/_pipeline.blade.php`, `resources/views/admin/handover/show.blade.php`, `resources/views/admin/handover/_adopter_confirmation.blade.php` | Existing list and details show the actual research schedule, outcomes, and protected supporting image; no production release or courier actions. |
| `resources/views/monitoring/create.blade.php`, `resources/views/admin/monitoring/index.blade.php` | Original recording, countdown, preview and errors; original staff report dialog uses the protected video and shows the verification result and practice context. Legacy reports have no player. |
| `tests/Frontend/handover-receipt-confirmation.test.js` | Verifies that both static negative-receipt prompts preserve the same accept/cancel behavior and never interpolate a pet name. |

Keep the original form CSRF, `multipart/form-data`, error and label associations, and buttons' submit names/values. Keep `document` optional only in Research Mode, `document_consent` required if that file is provided, and `agreed_to_terms` independent. Preserve the disclosure link and never change a no-upload or failed OCR status to Verified. The OCR details in the admin modal must remain text, not HTML. Do not use a public asset helper for any research media URL.

Keep `schedule_version` on every handover transition, including receipt. The backend owns schedule state; approving an application is not release, a reschedule requires confirmation, and only `outcome=received` unlocks monitoring. For receipt preserve optional `receipt_proof` / `note` and the static confirmation behavior for `outcome=not_received`.

Keep the existing camera elements, data attributes, module import, and `FormData` submission contract (milestone, challenge credentials, capture timestamp, and recorded blob). Do not add a gallery-picker fallback. Preserve status announcements and error visibility. The device permission prompt, protected media headers and capture verification are not styling concerns. See the release runbook for Azure dependencies and private storage.

Frontend review may safely refine spacing, responsive stacking and wrapping of form/report controls without changing those contracts. Local Chromium QA used a synthetic camera, a desktop viewport and a 390 × 844 viewport. Evidence in ignored `output/playwright/`: `research-camera-desktop.png`, `research-camera-mobile.png`, `research-handover-mobile.png` and `research-admin-playback.png`. Physical-device and live-provider acceptance remain operator checks.
