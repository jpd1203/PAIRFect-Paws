# Phase 16.1 frontend handoff: handover time and OCR accuracy

## Scope

Base: `4f681c3b68e5f5c415fb0edbf2c962a08ec1451e`. Branch: `fix/phase16-timezone-ocr-accuracy`.

This phase corrects functional presentation only. No controller, model, timezone configuration, migration, route, CSS, JavaScript, dependency or questionnaire is changed. Existing designs, controls and lifecycle rules remain. Nine Blade files are changed, with two new feature-test files and this handoff document.

## Exact changed frontend files and locations

| File | Location / functional change |
| --- | --- |
| `resources/views/admin/handover/_release_form.blade.php` | Initial `@php` defaults and labels for `#release-date` / `#release-time`. Capture one `ManilaTime::now()` instant for both defaults instead of PHP `date()`. Labels explicitly say `(Asia/Manila)`. Preserve saved and flashed wall-clock values. |
| `resources/views/admin/handover/_adopter_confirmation.blade.php` | Receipt outcome timestamp now uses `ManilaTime::format()`. Non-response last-reminder metadata uses offset-aware `parseAndFormat()` with `Time not available` fallback. |
| `resources/views/admin/handover/_recorded_release.blade.php` | Canonical `released_at` fallback now formats in Manila. The explicit staff-entered `release_date` + `release_time` pair retains precedence and is not converted again. |
| `resources/views/admin/handover/index.blade.php` | Approval date uses the Manila calendar date at the UTC boundary. Queue ordering/filtering is unchanged. |
| `resources/views/admin/handover/show.blade.php` | Header approval date uses the same Manila calendar as the queue/status. Existing history formatting and presentation adapter are untouched. |
| `resources/views/handover/status.blade.php` | Approval date and canonical release timestamp fallback use Manila. Existing local release date/time fields are not shifted. |
| `resources/views/handover/confirm.blade.php` | Canonical release timestamp fallback uses Manila. Static negative confirmation, submitter outcomes, proof requirements and protected evidence links remain unchanged. |
| `resources/views/handover/notifications.blade.php` | Absolute notice timestamp uses Manila; relative `diffForHumans()` remains relative. Read state/actions/privacy are unchanged. |
| `resources/views/admin/application/document-verification.blade.php` | Top heading becomes `Document Review Status` rather than `OCR Result`. Extracted-text section distinguishes recorded text from a verification decision, replaces unproven provider-completion statements and preserves recorded failure/unavailability notes. |

## Time contracts and backend dependencies

- Use existing `App\Support\ManilaTime` for canonical instants. App timezone remains UTC; display timezone remains the existing configured Asia/Manila setting. No system clock or global application timezone is changed.
- `released_at`, `adopter_confirmed_at`, `received_at`, `approved_at` and notice timestamps are canonical instants. Convert at the presentation boundary, not by adding eight hours or rewriting database values.
- `release_date` (date only) and `release_time` (staff-entered wall clock) are different from canonical timestamps. They already represent the local input. Do not apply another UTC-to-Manila shift, reinterpret them as a UTC instant, or replace the controller's canonical release timestamp with them.
- Saved form values and validation-flashed old input take precedence over a new default. The two default controls must derive from one captured instant so midnight cannot split their date/time.
- ISO/RFC reminder metadata can already contain an offset. The helper respects that offset and safely handles missing/invalid historical timestamps. Do not pass it through server-local `strtotime()`/`date()` again.
- Native form input names/IDs/types, labels, CSRF, action/method, staff selection and provider/evidence controls remain intact. The `(Asia/Manila)` labels clarify the existing input contract; no new values are submitted.
- Controller locking, optimistic concurrency, confirmed schedule, expiry-only blocking, existing early release for pickup/delivery, identity, clearance, courier, notification intent and receipt/monitoring gates are unchanged. Styling must not weaken those server-enforced rules.

## OCR/document-status contracts and wording

The existing schema stores verification status, extracted text, confidence/match values, timestamps, reasons and separate audits. It does **not** store a reliable per-document provider-completion flag/attempt history. Manual decisions overwrite the current reasons/status while preserving separately stored audit history. A current environment provider setting or `Verified` status is not historical evidence of successful Google processing.

| Recorded state | New presentation |
| --- | --- |
| Nonblank extracted text | `Extracted OCR text is recorded below. Text extraction does not establish identity or document authenticity.` Text remains escaped and staff-only. No provider attribution is invented. |
| Verified, no text | `The document is marked verified. This may reflect a manual staff decision; no extracted OCR text is recorded. Verification status alone does not confirm OCR completion.` |
| ManualReview, no text | `No completed OCR result is recorded.` Review recorded notes for failure or provider availability; staff review/retry may be required. |
| LegacyReview, no text | `No OCR extraction result is recorded for this legacy document.` This does not claim OCR has never been attempted. |
| Other no-text states | `No readable OCR text is recorded.` The status alone cannot establish whether extraction ran/completed. |

Recorded provider errors/unavailability remain visible verbatim as escaped verification notes. Thus an actual recorded authorization failure remains distinguishable from a configured-unavailable provider, without inferring either from a generic status. Successful empty extraction and an unsupported-file/no-attempt outcome cannot reliably be distinguished from current metadata alone; neutral wording is intentional. No new database fields or audit lookups are introduced.

Preserve the existing status value/decision controls, cross-reference checks, retry eligibility, one-follow-up limit, protected document route and staff access controls. Do not restyle `Verified` into a claim of biometric identity, document authenticity, Google success or OCR-only approval. Do not infer completed OCR from the current deployment configuration, composite score, or absence of text. Keep notes and extracted text escaped; do not expose them to adopters/public pages.

## Styling and responsive changes

No CSS, layout, modal dimensions, navigation, button behavior or responsive rules were modified. Existing status-box classes are reused; the new no-text verified message uses the existing neutral style. Timezone suffixes may naturally wrap in the existing three-column form; no spacing overrides were added.

Frontend developers may safely adjust wrapping, spacing, typography, contrast and the placement of explanatory text, preserving all field bindings, escaping, semantic distinction between review and extraction, native keyboard access and server contracts above.

## Executed browser evidence

Dedicated headless Chrome sessions, actual viewport 1280x720, against a newly created disposable MySQL 8.0.46 database. Test-only router froze Carbon at `2026-12-31 16:05:00 UTC` / `2027-01-01 00:05:00 Asia/Manila`; it did not change the system clock. Database-backed sessions were used because a future test clock invalidates file-session mtime assumptions. No production SMTP or live Google call occurred.

- Staff release form displayed January 1, `00:05`. Submitting unchanged controls through the real button stored `release_date=2027-01-01`, `release_time=00:05`, **`released_at=2026-12-31 16:05:00`**. Schedule remained confirmed and early release remained allowed.
- Staff receipt card, canonical release fallback and history displayed `Jan 1, 2027 · 12:05 AM` consistently. Adopter status and receipt pages displayed the same canonical fallback.
- Actual manual approval through the protected review form kept status Verified, null extracted text and one manual-decision audit, while showing the accurate neutral message.
- Browser read states covered synthetic recorded text, configured unavailability, fake provider authorization failure, fake successful-empty extraction and legacy no-result state. Google responses were faked during fixture generation, not proof of real API access. No fabricated identity assurance was asserted.
- Current staff/adopter console checks reported zero errors/warnings. Screenshots were visually inspected for the corrected defaults, manual status and confirmation card. No mobile compatibility or accessibility certification is claimed.

Local screenshots/snapshots remain under ignored `output/playwright/` (not committed; share separately):

- `phase161-release-defaults.png`, `phase161-release-submit.txt`
- `phase161-confirmation-time.png`, `phase161-confirmation-time.txt`
- `phase161-manually-verified.png`, `phase161-manually-verified.txt`
- `phase161-ocr-recorded-text.png`, `phase161-ocr-unavailable.png`, `phase161-ocr-rejected.png`, `phase161-ocr-empty.png`, `phase161-ocr-legacy.png`
- `phase161-adopter-status-time.png`, `phase161-adopter-confirm-time.png`, corresponding `.txt` snapshots, and `phase161-document-states.txt`
- Database field/audit readback: `output/browser/after-ui-submit.json`.

## Deferred / unchanged concerns

1. **Do not treat this phase as fixing the two assigned mobile issues:** 360px readiness clipping and 390px fixed-header/fragment overlap remain deferred to frontend groupmates. `_delivery-clearance.blade.php`, CSS and mobile layout rules are untouched.
2. Browser automation found an inherited duplicate `releaseFormSection` ID on the staff page (identity section and release form wrapper). It selected unique `#handoverForm` for this QA; no broad DOM cleanup was performed. Frontend owners can review unique anchors in a separate scoped change while preserving navigation/notification contracts.
3. Neutral OCR messages intentionally do not conclusively label all historic approvals as manual or all no-text responses as failed Google calls. Reliable per-attempt attribution would be a separate metadata/policy decision, not a wording inference or automatic migration.
4. Real deployment timezone settings, actual Google/provider availability, SMTP/inbox delivery and physical-device/assistive-technology checks remain outside this isolated phase.
5. Historical staff-entered wall-clock fields are preserved, even if an old form default was incorrect. Their intended local time cannot safely be inferred by adding eight hours or replacing them with the transaction timestamp. No historical data repair or automatic backfill is performed.

Regression contracts are covered by `tests/Feature/HandoverTimezonePresentationTest.php` (16 cases) and `tests/Feature/DocumentVerificationPresentationTest.php` (11 cases), plus the existing lifecycle/document suites.
