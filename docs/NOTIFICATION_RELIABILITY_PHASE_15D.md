# Phase 15D: notification reliability and operator verification

## Scope and confirmed findings

Source-only audit and synthetic failure injection from Phase 15C `82c54a7b170d284b200fbb763f5a0108c38e04de`. No Azure access, real database connection or SMTP message was used.

1. EmailNotificationService, ReservationQueueService status mail and MonitoringController welfare receipts caught enqueue exceptions and only logged them. Those messages had no retained retry job. This also missed failures raised by an after-commit queue callback outside the original try block.
2. Both authentication notifications intentionally force the sync connection. Transport rejection/timeouts propagated through registration, verification resend or forgot-password after creating an account/token. A provider outage could therefore fail the HTTP request.
3. Handover dispatch recovery selected only queued_at=NULL. A queue acknowledging a job that was later lost, or a worker disappearing without its failed callback, left queued_at set forever despite the existing one-hour claim expiry.

## Bounded fixes, no schema or frontend changes

RecoverableEmailDispatcher wraps the existing non-handover mail producers and immediate authentication first attempt. It catches dispatch errors inside the after-commit callback, not just when registering it. On failure it retains an encrypted recovery job in Laravel's existing failed_jobs store, using Laravel's own queue-payload/encryption implementation. Recovery is **manual** through queue:retry; this is not a new general-purpose transactional outbox or a new scheduler task. A failed_jobs row is pending operator work, not a delivered email.

Original mail/notification classes, templates, subject/body wording, recipient selection, immediate auth first attempt, signed-link verification, throttles and password-broker rules remain unchanged. Recovered jobs recheck current user activity, bound email address and (for ordinary mail) role/verified status. Verification recovery skips already verified users; password recovery checks the original token against the existing broker, so expired/consumed/replaced tokens are not revived or extended. The worker logs only sanitized exception classes in new recovery failures. Full recipient/body/token data is encrypted inside the standard failed-job command payload; protect APP_KEY and database access.

Handover recovery now reclaims queued-but-unaccepted claims when the existing dispatch lease expires, including the exact expiry boundary. A new token makes old queued/failed jobs inert. Live claims, accepted and suppressed rows are not re-enqueued. Existing transactional staging, per-event identity, current-state eligibility, stale-notice suppression, reminder thresholds and schedule timings remain intact. The recovery scan is still every minute; the dispatch lease remains one hour. This is not proof that a queued job was lost: a long backlog may also cause token rotation, with old jobs safely skipped.

No original user-facing copy was changed. Existing "sent"/"queued" auth status messages describe the request attempt, not confirmed relay or inbox delivery; inspect failed-job recovery state for outages. The user remains unverified until the normal signed link is completed. Both deferred mobile layout issues remain deferred; no frontend handoff document is needed.

## Delivery states and unavoidable limits

- Queued: the configured queue acknowledged the enqueue; not evidence a worker ran.
- Retained failed job: an encrypted manual recovery record exists; not queued/sent/accepted.
- transport_accepted_at on a handover outbox: the transport returned a non-null successful result and that state committed; not inbox delivery, spam acceptance, reading or acknowledgment by the adopter.
- Array transport / fake mail acceptance proves only local code behavior. A real test inbox and provider evidence are separate operator checks.

There is NO exactly-once external SMTP guarantee. Tests deliberately demonstrate two fake relay acceptances if the database write fails after the first acceptance, followed by a retry. A timeout can likewise happen after the provider accepted the message. Stable existing Resend idempotency keys survive replay, but provider-specific idempotency is not universal SMTP deduplication. Check provider/inbox evidence before retrying an uncertain send; never assert that Gmail/plain SMTP will deduplicate.

If enqueue fails AND failed-job storage is unavailable/disabled, the new dispatcher logs a sanitized critical error and throws rather than silently claiming recoverability. If a process dies between an independently committed non-handover business update and dispatch/recovery persistence, intent can still be lost. Generic workflows are not made atomically transactional by this fix. Existing in-app-only notification persistence exceptions and arbitrary non-handover job loss also require broader intent/outbox design if full automatic recovery is required. That is a separately scoped future phase, not implemented here.

## Commands and evidence: local or separately authorized Azure operator

All commands below are a runbook, NOT commands executed against Azure in this phase. Azure changes and real test emails require separate approval. Do not paste .env, credentials, payloads, reset tokens, signed links or raw provider exceptions into chat or shared logs.

From the deployment root (typically `/home/site/wwwroot`):

### 1. Effective configuration and database queue

Read-only:

```sh
php artisan email:health
php artisan schedule:list
php artisan tinker --execute='dump(["queue"=>config("queue.default"),"retry_after"=>config("queue.connections.database.retry_after"),"failed_driver"=>config("queue.failed.driver"),"mail_transport"=>config("mail.mailers.".config("mail.default").".transport")]);'
```

Record the effective connection, durable failed driver, queue names, mailer/transport and timeout configuration without exposing passwords. email:health reports configuration/backlogs; it does NOT inspect Azure worker or scheduler liveness. It can show staff addresses: keep output within authorized personnel. Log/array mailers are not real delivery. A sync queue does not retain ordinary queued work. Preserve APP_KEY across restarts; key loss makes encrypted jobs unreadable.

### 2. Workers and backlog

Read-only process and metadata checks:

```sh
ps -eo pid,etime,args | grep -E '[a]rtisan (queue:work|schedule:work)'
php artisan queue:failed
php artisan tinker --execute='dump(Illuminate\Support\Facades\DB::table("jobs")->selectRaw("queue, COUNT(*) AS pending, MIN(created_at) AS oldest_created_at, MIN(reserved_at) AS oldest_reserved_at")->groupBy("queue")->get()->toArray());'
php artisan tinker --execute='dump(Illuminate\Support\Facades\DB::table("failed_jobs")->select("uuid","connection","queue","failed_at")->orderByDesc("failed_at")->limit(25)->get()->toArray());'
```

Observe counts/oldest age over multiple samples, not just one empty queue. Capture PID, start time, queue connection and `emails,default`, successful job processing and failures. A process listing alone does not prove progress. Inspect daemon logs privately; historical exception fields may contain sensitive provider output even though the new recovery errors are sanitized. Do not dump payload/exception columns.

Where separately approved and managed by the chosen process supervisor, the repository's worker contract is:

```sh
php artisan queue:work database --queue=emails,default --sleep=3 --tries=3 --backoff=60 --timeout=60 --no-interaction
```

The queue connection must match effective configuration/failed_jobs.connection; adapt `database` only to a verified persistent connection. Do not run queue:work sync: sync executes inline and has no durable queue to poll. Job-specific tries/timeouts/backoff still apply. retry_after must remain greater than the longest job timeout plus operational headroom (repository database default 180 seconds; mail jobs 30, check-in jobs 60). Do not start duplicate unmanaged workers just to make a screenshot. The supplied Supervisor file is an example using `/var/www/...`, not proof it is installed or wired into Azure. Metadata queries show the repository's default table names; adapt them only if effective queue configuration explicitly uses different tables.

### 3. Scheduler invocation

Read-only schedule:list proves registration only. The repository uses every-minute handover outbox recovery, 15-minute handover reminders/transport-case processing, daily check-ins at 08:00 Asia/Manila and the existing hourly/cleanup tasks. withoutOverlapping needs a working cache mutex; multiple hosts require a shared reliable store and an approved single scheduling topology.

The operator must verify a reliable every-minute invocation of `php artisan schedule:run` (or a deliberately supervised `php artisan schedule:work`) with timestamped successful runs, last/next execution and restart/idle recovery. `crontab -l` can inspect an existing cron configuration, but do not install one blindly in App Service. `php artisan schedule:run --verbose` is an ACTION that may enqueue real reminders, flags and other due work: execute only with separate authorization and reviewed recipients. A single manual run proves command invocation, not ongoing automation.

Repository inspection shows local PowerShell launchers and a Supervisor example. The Azure workflow deploys pushes to master (plus manual workflow_dispatch); the documented Azure startup command reloads Nginx. Neither file proves that an Azure worker/cron is provisioned. The existing F1 pilot document warns against assuming background automation. Verify actual hosting/process persistence through the authorized Azure operator; no cloud settings were inspected here.

### 4. Handover outbox recovery

Read-only metadata, omitting encrypted payload and enqueue token:

```sh
php artisan tinker --execute='dump(App\Models\HandoverEmailOutbox::select("id","kind","queue_attempts","delivery_attempts","queued_at","dispatch_lease_expires_at","transport_accepted_at","suppressed_at","suppression_reason")->orderByDesc("id")->limit(25)->get()->toArray());'
```

For an approved synthetic test case, first record the row/event/version, queue state and provider evidence. Only after authorization to potentially send real notices:

```sh
php artisan handovers:dispatch-pending-email
```

Confirm pending or expired-lease intents advance to a new claim once, repeated immediate dispatch adds no claim, stale-token jobs do nothing, accepted/suppressed rows remain terminal, and the worker records acceptance/suppression or a retryable error. A future lease is deliberately not stolen. Older queued rows with no lease are not guessed to be abandoned; inspect them separately. Do not manually edit tokens or acceptance fields to force delivery.

### 5. Manual non-handover/auth recovery

After resolving the queue/provider problem and reviewing whether acceptance was uncertain, retry only a reviewed UUID:

```sh
php artisan queue:retry REVIEWED_UUID
```

For a persistent connection, confirm its failed record is removed, the target queue receives the encrypted job, a matching worker processes it, and provider/inbox evidence or suppression is recorded. For a failed record whose connection is sync, queue:retry executes the handler immediately in that CLI process; no jobs-table row/worker is expected. Run that action only after reviewing acceptance uncertainty and send authorization. A removed failed record is not delivery proof. Do not bulk retry --all: handover jobs may have replacement claims, auth links may be obsolete, and historical application updates may no longer be useful. Failed jobs are not replayed automatically by this phase's scheduler. Avoid pruning them before triage. If a reset token expired, have the user request a new link through the normal throttled flow; do not extend it in SQL.

### 6. Restart and actual test inbox

In a separately approved synthetic presentation scenario, enqueue known work, observe its UUID/count, restart the managed worker and demonstrate processing from durable storage. `php artisan queue:restart` signals workers through cache; it is an action, and successful execution does not prove a supervisor restarted them. Capture the new PID, recovered backlog and no unexpected duplicate send. An abandoned reservation becomes retryable after retry_after; a lost acknowledged handover job becomes reclaimable after its existing outbox lease.

After explicit permission to send one real message to an owned test inbox:

```sh
php artisan email:test APPROVED_PRESENTATION_INBOX --audience=staff
```

Correlate command result, job UUID/worker log, provider acceptance/message identifier if available, actual inbox/spam receipt time and recipient confirmation. Do not treat "Queued" as "delivered". Provider dashboards, SMTP credentials, DNS/sender authorization, actual inbox receipt, Azure daemon/cron continuity, persistent storage/cache, process restart after deployment and idle behavior all require operator/infrastructure access and were NOT proven locally.

## Regression evidence and frontend

Targeted tests cover queue failure retention, encryption/redaction, registration/forgot-password failure, expired token and changed-recipient suppression, actual disposable database worker replay, abandoned reservations, transient provider errors, duplicate replay, scheduler registration/command repetition, lost acknowledged handover jobs, obsolete notices and SMTP acceptance uncertainty. Existing atomic release/reopening and post-adoption lifecycle suites remain required full regressions. No UI files changed; no frontend handoff document was created. Local tests do not certify Azure operations or production readiness.
