# Transport investigations and overdue follow-up (Phase 6)

Baseline: `b10d7c6ccc6aec586d54730b1ee4d53191c670f8` (committed Phase 5).

## Findings and workflow

Previously, `not_received` displayed **Delivery failed**, although the system had only the adopter's report. Delivery window end +3 hours produced a staff alert/marker but no structured case, assignee, follow-up plan or resolution. Reopening cleared the current attempt's details. Investigation now has its own persistent, attempt-linked record; it is not a replacement handover state machine.

- A delivery non-receipt submission creates an **Open** case in the same locked transaction as the existing outcome/history/notification. The original report note, reporting adopter and recorded report timestamp are retained. The existing issue alert is reused; no second case-open alert is sent.
- At the existing +3-hour escalation, an overdue-delivery case is created in the reminder transaction. The existing staff follow-up alert/outbox and marker are reused. Existing +1-hour receipt reminder, +3-hour follow-up, +1-hour missed pickup, and pre-window reminders are unchanged.
- Staff can separately report any of the seven categories on a recorded delivery release. Categories are allegations/concerns, never automatic findings. One case per handover/attempt/source/category prevents duplicate submissions. Further information belongs in that case rather than duplicate reports.
- Administrators assign an active, verified administrator/volunteer and record a follow-up in Philippine time. Assigned volunteers can record progress and supporting evidence; they cannot reassign or resolve cases. Only administrators resolve cases, matching the existing administrator-only welfare-resolution convention.
- States: `open`, `under_investigation`, `resolved`. Investigation requires an eligible assignee. Open/investigation updates require documented progress and a future next-follow-up. Resolved is terminal; stale or duplicate updates fail rather than overwriting history.
- Outcomes: `adopter_confirmed_receipt`, `animal_returned_to_shelter`, `delivery_failure_confirmed`, `other_documented_resolution`. All require findings and resolution notes. The receipt outcome additionally requires the **same attempt's existing protected receipt confirmation and proof**. A later attempt's receipt cannot validate an earlier attempt.
- Closing a case never marks receipt, changes application/pet status, records a lifecycle return, cancels an adoption or starts monitoring. A recorded shelter-return finding is not the post-adoption return workflow. Any subsequent lifecycle change needs a separately authorized process. Only the existing adopter receipt/proof process activates 3-3-3 monitoring.
- Existing non-receipt confirmation is terminal for that release attempt. This implementation does not bypass it to let staff confirm receipt. Legitimate new handovers still use the existing reopen/schedule/identity/clearance/release/receipt workflow.
- Reopening does not delete or resolve incidents. Their release context and evidence remain attached to the original attempt. Old cases can still receive assigned investigation updates and alerts.

## Staff visibility and operational timing

The existing admin handover detail page includes Investigation / Follow-Up, current handover state, categories, source report date, case status, assignee, due follow-up, private evidence, notes and versioned case history. The existing list has unresolved-investigation and overdue-delivery filters, with assignee/follow-up summaries. The overdue indicator is computed at window end +3 hours even when the scheduler is behind; it does not claim an alert was delivered.

`handovers:process-transport-cases` runs every fifteen minutes under the existing scheduler. It recovers unresolved legacy non-receipt/+3-hour flagged records without fabricating findings or re-sending their original alert. It emits one due-follow-up alert per recorded due date. Changing the next-follow-up resets its alert marker. Closed cases are not re-opened by timers; historical completed handovers are not backfilled.

### Maximum pending duration: optional shelter policy

There is **no default additional deadline**, statutory claim, grace period or automatic cancellation. `HANDOVER_TRANSPORT_MAX_PENDING_HOURS` may be set to a positive whole number only after documented shelter approval. Empty, zero, negative, fractional and invalid values disable it. It is read through `config('handover.transport_max_pending_hours')`.

When approved/enabled, it triggers a one-time staff review of an unresolved investigation at that many hours from its original recorded release. It can create a review case for a still-unconfirmed delivery even before the standard +3-hour escalation. An existing case is reused; a closed case is not automatically reopened. Unresolved older-attempt investigations remain reviewable after reopening. This operational investigation policy does not change reminder intervals or establish delivery failure, missing-animal status, receipt, return or cancellation. Staff must still document investigation and resolution.

There is no invented automatic follow-up interval after the initial escalation. New/unassigned cases explicitly show **Needs administrator assignment / Needs follow-up scheduling**. Staff must set a responsible person and next action; the system cannot ensure human response by itself. Scheduler scans have up to one interval of normal latency; uptime/backlog can add delay.

## Data dictionary

Migration: `2026_11_05_000003_create_handover_transport_incidents_table.php`. New table only; no old rows/columns are rewritten.

Table: `handover_transport_incidents`.

| Field | Type | Nullable / default |
| --- | --- | --- |
| id | unsigned bigint primary key | NOT NULL, auto increment |
| handover_id | unsigned bigint FK handovers.id | NOT NULL, ON DELETE RESTRICT |
| release_attempt | unsigned integer | NOT NULL |
| source | varchar(30) | NOT NULL; service values `staff_report`, `adopter_non_receipt`, `overdue_delivery` |
| category | varchar(50) | NOT NULL; application-validated categories below |
| description | text | NOT NULL; request maximum 3,000 characters |
| reported_by_user_id | unsigned bigint FK users.id | nullable, ON DELETE SET NULL; system escalations have no reporter |
| reported_at | datetime | NOT NULL; server/recorded historical report time |
| assigned_to_user_id | unsigned bigint FK users.id | nullable, ON DELETE SET NULL |
| status | varchar(30) | NOT NULL, default `open`; not a database ENUM |
| version | unsigned integer | NOT NULL, default 1 |
| next_follow_up_at | datetime | nullable |
| follow_up_notified_at | datetime | nullable; durable alert intent marker, not delivery proof |
| max_pending_notified_at | datetime | nullable; durable policy review intent marker |
| findings | text | nullable; request maximum 3,000 characters |
| resolution_outcome | varchar(40) | nullable; application-validated values above |
| resolution_notes | text | nullable; request maximum 3,000 characters |
| resolved_by_user_id | unsigned bigint FK users.id | nullable, ON DELETE SET NULL |
| resolved_at | datetime | nullable, server timestamp |
| evidence_path | varchar(255) | nullable; private local supporting image |
| evidence_sha256 | varchar(64) | nullable; server-calculated hash |
| release_context | JSON | NOT NULL |
| history | JSON | NOT NULL |
| created_at, updated_at | timestamp | nullable Laravel timestamps |

Unique: `(handover_id, release_attempt, source, category)` named `transport_incident_attempt_source_unique`. Indexes: `(status,next_follow_up_at)` and `(assigned_to_user_id,status)`, plus primary/FK support indexes. No soft deletes or public case deletion route. Removing staff preserves the case/evidence and nulls the FK; reassign ineligible/deleted assignees. Deleting a parent handover is restricted, not cascaded into lost case evidence.

Category keys: `pet_not_received`, `delayed_delivery`, `incorrect_destination`, `suspected_mishandling`, `injury_or_illness`, `animal_distress`, `other_transport_concern`. Labels qualify reports/suspicions. Values are centrally configured as model constants and request-validated, not SQL ENUMs.

`release_context`: original released_at, scheduled_start_at/end_at, schedule_version, release_method, private release_proof_path, and original adopter_report_note for non-receipt. No duplicated destination or raw courier URL. `history`: append-only application snapshots of event, UTC at, actor ID, version, status, assigned user ID, next follow-up, findings and resolution outcome/notes. The existing handover/audit history gets only a case/attempt/version transition pointer; it does not duplicate private notes/addresses. Dates are stored as UTC and displayed/input as Asia/Manila.

Relationships: Handover hasMany transportIncidents; incident belongsTo Handover, reportedBy User, assignedTo User, resolvedBy User. No new placement or recipient authority. `isOverdue`, maximum-pending configuration and notice eligibility are computed, not persisted handover outcomes.

## Security, privacy and concurrency

- New staff routes remain under auth/staff/verified middleware, with CSRF-protected POSTs. Every mutation reloads active/verified staff authority inside the handover lock. Case updates lock handover then case then actor; client revision must match. Assignee eligibility is checked server-side. Reporter, timestamp, release context and resolution actor are never trusted from request input.
- Adopters/guests cannot access admin case data/evidence. Active verified staff may inspect operational cases; assigned volunteers may update only their cases. This preserves shared staff handover visibility without granting all volunteers case closure authority.
- Private image evidence only (JPEG/PNG/WebP, <=5 MB), random server filenames, SHA-256, fixed local private disk. No public symlink/URL or arbitrary storage path is returned. Evidence downloads require current staff authorization and parent-case matching, use attachment/no-store/nosniff headers, and reject paths outside allowed prefixes. New supporting evidence cannot replace an existing image.
- Original private release-proof references are snapshotted before reopening clears the current release fields. Legacy public proofs are not newly exposed or copied. Existing proof-privatization procedures remain separate.
- Evidence storage is compensated on rejected/failed/duplicate writes. A process crash between file storage and DB commit can still leave an unreferenced private file; no unsafe automatic deletion sweep is introduced. Retention/backups and authorized deletion are shelter policy.
- Report/progress/resolution notes and case history are escaped in Blade and hidden from ordinary model serialization. They are protected by RBAC, not newly encrypted at rest. Platform/database encryption and authorized-staff conduct remain operational responsibilities.

## Notification integration

New manual reports, progress/assignment and due/policy follow-up events create transactional in-app notices and Phase 3 encrypted email outbox intents. Recipients are eligible administrators, the currently assigned eligible staff member, and the configured shelter alert inbox. Unassigned volunteers do not receive new case-specific alerts. Existing initial non-receipt/+3-hour staff alert conventions are preserved.

Event keys include case ID, revision and event; recipient/event uniqueness and handover locks prevent duplicate intents. New outbox kind `transport_case_attention` stores `transport_case_id` and `transport_case_version` in its existing encrypted payload (no outbox schema change). Before mail transport, existing account/address/active/verification checks apply, and case state/revision/current admin-or-assignee authority are rechecked. Old-attempt case alerts remain actionable after reopening; closed/superseded/reassigned alerts are suppressed. Existing initial issue/+3-hour mail is also suppressed if its corresponding case was resolved.

Notice/mail content contains case references and a protected handover link, not report/findings, destination, raw courier URL or evidence URL. Outbox recovery/retries/claim tokens and duplicate-send protection are reused. Existing SMTP acknowledgment/crash limitations remain: durable intent or transport acceptance is not proof of inbox receipt/reading or universal exactly-once delivery.

## Deployment considerations (not executed in production)

Review/back up the target and coordinate code/schema under an approved deployment. All Phase 1-5 integration migrations, then the new Phase 6 migration, must exist before new code serves handover pages/reminders. This task does not run production migrations, backfill production, deploy, push or commit.

Keep APP_KEY stable for encrypted outbox data. Continue supervised emails/default queue workers, minute-by-minute outbox recovery and the scheduler; the additional case command uses the scheduler's existing fifteen-minute cadence. Review policy before setting the optional environment value. Restart workers/refresh cached configuration only under the normal authorized rollout.

Verify Azure/MySQL TLS, private persistent storage/backups, queue/scheduler uptime, real SMTP/inbox delivery, role assignments, accessibility/device behavior and actual shelter follow-up procedures in an approved environment. Local fake/array transport and disposable DB tests are not production verification.
