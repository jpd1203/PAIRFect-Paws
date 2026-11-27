# Frontend handoff: Research document OCR text

| File | Change and what to review |
| --- | --- |
| `resources/views/admin/application/index.blade.php` | In the Applications modal payload, the existing Document Verification **Details** link points to the protected Research OCR route when retention is enabled and the current administrator can use it. The applications page never receives raw text. |
| `resources/views/admin/application/research-document-ocr.blade.php` | Reuses the admin layout and existing review styles. Shows status, document type, match score, reasons and line-wrapped, HTML-escaped OCR text. Distinguishes older uploads with no retention from new uploads without a result. |
| `resources/views/application/apply.blade.php` | Adds a short disclosure to the existing Research Mode document consent, only when OCR and retention are both enabled. The same checkbox is reused. A hidden consent version and its error message prevent stale forms from authorizing retention. |
| `resources/views/legal/privacy-content.blade.php` | The research-only document paragraph explains conditional encrypted retention, restricted access and deletion requests. |

No JavaScript, stylesheets, or responsive breakpoints changed for this feature. Check the long-text wrapping on the details page and disclosure wrapping at mobile widths before any visual refinements. Keep the `document_consent` checkbox, version field, error handling, conditional rendering, escaped text, and dedicated no-store, admin-only route. Never pass raw OCR text to the modal JSON, CSV, or a public page. Feature tests use synthetic OCR responses; screenshots should use a synthetic ID, never a participant's upload.
