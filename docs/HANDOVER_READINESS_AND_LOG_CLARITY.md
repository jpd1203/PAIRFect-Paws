# Phase 12: handover readiness feedback and staff history labels

Base: origin/main c1011b0e6357f33f9f3f655f78ea75304bd292de, fetched and inspected before implementation. No newer main commits were present.

## Reproduction and scope

With a synthetic approved delivery handover, the readiness route accepted valid contact and correctly persisted an unavailable outcome. The button was intentionally disabled when prerequisites were missing; this was not a broken POST/route/controller. The UI showed generic prerequisite text, placed server errors above the scheduling section, discarded rejected selections, and continued to show `Current readiness not established` after unavailability was recorded. These states made a functioning operation look unsuccessful.

Changes are confined to the readiness controller's feedback/redirect, its existing Blade section, the staff timeline label binding, a narrow presentation adapter, tests and this note. No service transactions, authorization rules, schema, notifications, outbox, timing rules or unrelated interfaces were changed. Existing styles/wrappers/buttons and native form controls are retained; no CSS or JavaScript was added.

## Readiness feedback

Disabled controls explain the actual unmet prerequisites: verify the current destination; obtain adopter confirmation of a delivery schedule; or replace an expired confirmed window. The server continues to enforce these requirements under locks, even if a disabled button is bypassed. Early contact/release behavior is unchanged; no start-window restriction was introduced.

Readiness web POST success and validation failures return to `#deliveryReadiness`. Standard validation keys, flashed old input, error bag, CSRF and JSON 422 responses are preserved. Rejected contact method, availability and notes remain selected/escaped. A nearby summary and linked field-level error messages explain failures, including stale versions/fingerprints and required notes for Other. Native required controls remain intact.

Success is shown in the affected section with a status announcement. Confirmed availability records in-app/email intent, not courier release or proof of email delivery. Unavailability is explicitly labeled as the last recorded contact and does not imply current clearance. A stale positive confirmation is identified as no longer covering the current agreement. No stale data is automatically replayed or resubmitted.

## History presentation, not evidence rewriting

`HandoverHistoryLabel::forStaff()` recognizes only exact action-label formats emitted by the existing delivery-clearance, schedule and reminder services. It maps those known events for display in the staff timeline. Unknown/free-text entries remain unchanged and Blade-escaped; no broad underscore replacement, truncation or version-stripping rule is applied.

Examples:

- `address verified (clearance version 3)` -> Delivery address verified
- `address needs review (clearance version 4)` -> Delivery address marked for further review
- `readiness confirmed (clearance version 5)` -> Adopter availability confirmed
- `recipient unavailable (clearance version 6)` -> Adopter reported unavailable
- `schedule.method_selected (schedule version 0)` -> Adopter selected handover method
- `handover.24h_reminder (schedule version 2)` -> 24-hour handover reminder recorded

The needs-review action is deliberately not always called "verification withdrawn": historical data cannot establish that the address had previously been verified. The label describes the actual review action without inventing a prior state. Reminder wording describes a recorded event, not successful transport/delivery.

Stored history, technical audit action/notes, version numbers and destination fingerprints are unchanged. Actor, reverse chronological ordering and Manila-local timestamps are unchanged. Revision/fingerprint hidden form fields remain necessary for server validation; they are not ordinary timeline descriptions.

## Validation and limitations

New tests exercise disabled guidance, field errors/restored input, false and true outcomes, contact types, stale forms, JSON validation, volunteer authorization/database rechecking, invalid placements, duplicate idempotency, outbox/audit intent and read-only history rendering/escaping. Existing handover suites additionally cover unauthorized actors, transaction rollback, queue recovery, rescheduling, identity, courier rules, expiry-only releases, receipt evidence and monitoring.

Actual browser QA uses a separate synthetic SQLite fixture and named Playwright session. Full SQLite/MySQL validation must use disposable databases; MatchingDemoSeederTest runs in a separate process because its migrate:fresh behavior is destructive to shared test state.

No real SMTP delivery is attempted. An actual provider/worker deployment check and a complete mobile/assistive-technology browser matrix remain operational QA tasks. Without JavaScript this enhancement still works: it uses native forms, server validation and fragment redirects. Conditional Other notes are enforced server-side, with visible existing field instructions; no new conditional-field JavaScript was introduced.
