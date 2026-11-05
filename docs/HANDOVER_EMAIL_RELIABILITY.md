# Handover email reliability

## Scope and durable state

Handover email uses the `handover_email_outbox` table. Other transactional email
flows retain their existing implementation. No reminder thresholds or adoption
transitions have changed.

The existing bell-notification table cannot represent a configured alert inbox,
per-recipient queue failures, worker attempts, or transport acceptance. Its
in-app read flag is not a delivery receipt. The handover-only outbox is therefore
written in the same transaction as the notification event and reminder marker.
Failure to store the outbox rolls back that event. Queueing happens after commit;
a queue outage never rolls back an already-committed adoption action.

The handover marker means **event durably created**, not emailed or delivered.
Per-recipient outbox fields distinguish:

- `created_at`: mail intent recorded, with a unique logical event/recipient key.
- `queue_attempts`, `last_queue_error`: dispatch attempts and safe exception class.
- `queued_at`: dispatch acknowledged, not recipient delivery.
- `delivery_attempts`, `last_transport_error`: worker transport attempts.
- `transport_accepted_at`: the configured transport returned a sent message.
- `suppressed_at`, `suppression_reason`: stale or unauthorized mail skipped.

There is deliberately no `delivered_at`: no provider delivery webhook is
implemented. An array/log transport accepting a message does not send real mail.
An SMTP acceptance does not prove inbox delivery.

Payloads (including addresses) are encrypted at rest. Queue jobs remain encrypted.
Keep APP_KEY stable and manage key rotation using Laravel's previous-key support.
Outbox payloads and queue tokens are hidden from model serialization. Application
logs contain only outbox IDs and exception classes, not SMTP messages, adopter
details, or private courier URLs. Worker transport errors are sanitized before
being rethrown; failed-job payloads retain the existing queue encryption.

## Scheduling and recovery

Existing thresholds remain 24h and 2h before start; 1h after delivery end for
receipt; 3h after delivery end for escalation; and 1h after pickup end for a
missed pickup. The scheduler still creates these events every fifteen minutes.
The additional every-minute command retries mail intent only:

```text
php artisan handovers:dispatch-pending-email
```

Dispatch claims are serialized per outbox row. Successful queued entries are not
automatically queued again just because a worker is backlogged. Workers retain
the existing three tries, [60, 300, 900] backoff, and 30-second timeout. Exhausted
jobs remain in `failed_jobs`; their failure hook makes the canonical outbox intent
dispatchable again. After fixing the cause, the command above (or its scheduler)
queues a replacement with a new token. An old failed job retried with
`queue:retry` then becomes a harmless no-op rather than a second email.

A crash between claiming dispatch and recording queue acknowledgement leaves a
one-hour dispatch lease. After expiry the recovery command can issue a new token.
Older tokens cannot send. This internal recovery lease is not a handover window
extension and does not change any operational reminder threshold. Missing or
manually deleted acknowledged queue jobs require operator review; they are not
blindly duplicated. Ordinary worker crashes use Laravel's reservation retry.

Worker execution locks the current handover and outbox row while validating and
calling the mail transport. This prevents a parallel reschedule or receipt update
from racing that send, but can briefly delay those actions during SMTP I/O. Use a
bounded transport timeout and keep queue retry_after greater than worker timeout.

No application can guarantee exactly-once SMTP delivery across a process crash
after provider acceptance but before the local transaction commits. Stable event
idempotency keys are reused where supported by the provider; that does not add
idempotency support to Gmail SMTP. The implementation prevents normal duplicate
scheduling and repeated successful worker sends, not this distributed-systems
ambiguity. Tests on SQLite do not establish MySQL concurrency behavior.

## Worker revalidation and recipients

Workers recheck application approval, release attempt, owner/staff eligibility,
verification, current email address, the configured alert address, schedule
fingerprint/revision, event timing, release and receipt state. Stale reminder
jobs are suppressed even on retry. A pending reschedule changes the bookkeeping
revision but leaves the agreed method/window/confirmation intact; valid reminders
for that unchanged agreement are preserved. A replacement proposal or approved
replacement window invalidates the old agreement fingerprint.

Operational bell notices remain administrator-only. Operational email preserves
the existing active, verified administrator/volunteer recipient policy, excluding
placeholder addresses and deduplicating a configured staff inbox. Recipient
authorization is rechecked before transport. Configure a real monitored
STAFF_ALERT_EMAIL and run email:health: a staff event with no eligible email
recipients cannot invent a mailbox. No new volunteer permissions are granted.

Handover channel labels now advertise only implemented In-app/Email channels,
not unimplemented SMS or Push. They describe intended channels, not delivery.

## Deployment verification (not performed automatically)

After review/authorization, migrate the new table, clear caches, and restart
long-lived workers. Keep the existing scheduler and `emails,default` worker
running. Verify mail and queue configuration with `email:health`. Test queue
outage recovery, worker restart and rescheduling with controlled test recipients
on the deployment's actual MySQL/queue/mail infrastructure. Verify inbox/provider
delivery separately; local tests use fakes and the array transport only.

Legacy jobs queued before this change have no outbox ID, revision, or handover
identifier in their serialized mail. They cannot be safely retrofitted by
guessing from message wording. Review/drain or selectively quarantine obsolete
legacy handover jobs during rollout; do not flush unrelated mail. Already-created
reminder markers also cannot reconstruct historically lost email intent: assess
those records explicitly rather than bulk-resetting markers or emailing adopters
unexpectedly. No production jobs, markers, or records are changed by this task.
