# Delivery destination verification and pre-dispatch readiness

Phase 5; baseline `2675751e5ddeca220342c72520a45177b71a3be2`.

## Workflow

1. Approval creates the existing adopter-owned handover. Its `adopter_address` is the approved application's address snapshot. This change does not substitute the current account/profile address or add an address-edit endpoint.
2. Staff and adopter agree a delivery schedule through the existing proposal/confirmation workflow.
3. An active, email-verified administrator or volunteer reviews the exact destination displayed in **Delivery Verification / Pre-Dispatch Readiness** and records either `verified` or `needs_review`. A missing destination cannot be verified. Staff must resolve such a record through a separately approved correction process, not type an arbitrary replacement into this module.
4. After destination verification and a confirmed, nonexpired delivery schedule, staff directly contact the adopter. They record the contact method and the adopter's explicit availability outcome. Sending an email/reminder is not evidence of an availability response. Other contact methods require notes.
5. Positive readiness creates one adopter in-app preparation notice and encrypted durable email intent in the same transaction as readiness, handover history and audit log. Unavailability creates no positive dispatch notice.
6. Release retains the existing staff, approval, interview identity, schedule confirmation/matching method, courier allowlist and HTTPS tracking requirements. Delivery additionally requires current address verification and current readiness. Pickup has no delivery-clearance requirement and retains its final in-person identity check.
7. Release is blocked **only after** the scheduled end time; release before the start remains allowed. The final expiry check and file rollback remain unchanged.
8. Release does not activate monitoring. Receipt confirmation still requires the existing private proof photo. Only confirmed receipt activates the 3-day, 3-week and 3-month monitoring milestones. Not-received reporting/reopening retain their existing behavior.

## Freshness, concurrency and authorization

- Exact destination reference: SHA-256 of the persisted handover ID, adopter user ID and exact address snapshot. No address normalization silently changes what staff reviewed.
- Address verification checks its recorded status, verifier, server timestamp and exact destination reference.
- Readiness also binds the schedule revision, existing schedule fingerprint (method/window/confirmation), exact destination, release attempt and delivery method. A pending reschedule advances the revision and requires renewed readiness even though the old agreed schedule itself remains in place. This does not change the existing reschedule approval/confirmation rules.
- No 30-minute/hour/day readiness timeout is invented. An unchanged agreement can retain readiness; an expired delivery window still blocks release.
- Eloquent address changes clear address verification and availability and advance the clearance revision, including change-and-restore. Read-time destination fingerprints independently detect direct SQL changes to a different address. No application endpoint added here allows such edits.
- Forms carry both schedule and clearance revisions plus the displayed destination fingerprint. Locked server-side comparisons reject stale forms; the fingerprint is a stale-form reference, not a bearer authorization secret.
- Mutations lock the application, handover and current staff actor. Staff authorization is reloaded from the database inside the transaction; client verifier IDs/times are ignored. The release transaction performs the same current-actor authorization check.
- An identical fresh resubmission is a no-op: no timestamp/actor rewrite, counter advance or duplicate notice. Replaying an older revision is rejected. A materially new availability/contact record is a new audited event.
- Privileged clearance fields are deliberately absent from the model's public mass-assignment allowlist; the service uses explicit `forceFill` on whitelisted server-calculated values. Notes and fingerprints are hidden from ordinary model serialization. Notes render as escaped staff-only text.
- Routes use the existing authenticated, active-staff, verified middleware group and CSRF-protected POST forms. Adopters/guests cannot self-verify or access admin destination details.
- Current clearance metadata lives on the single handover; there is no duplicate placement, verification service for identity, courier API or recipient-delegation table. Existing history/audit mechanisms record actor, server time, action and destination/revision reference without logging the full address or contact notes.
- The existing admin handover log now uses the same Manila-time formatter as the new clearance timestamps; stored UTC history values and event contents are not rewritten.

## Data Dictionary additions

Migration: `2026_11_05_000002_add_delivery_clearance_to_handovers_table.php`.

All fields below are on `handovers`. Its existing `id` primary key identifies the associated placement/destination. No new table or unique business key is introduced.

| Column | Type | Nullable / default | Meaning |
| --- | --- | --- | --- |
| delivery_clearance_version | unsigned integer | NOT NULL, default 0 | Optimistic concurrency/event generation; zero does not mean cleared |
| delivery_address_status | varchar(20) | nullable, no default | Application-validated `verified` / `needs_review`; NULL means no recorded current review; not a database ENUM |
| delivery_address_verified_by_user_id | unsigned bigint FK users.id | nullable, no default; ON DELETE SET NULL | Actual reviewing staff actor |
| delivery_address_verified_at | datetime | nullable, no default | Server-recorded review instant (UTC storage), including a needs-review decision |
| delivery_address_fingerprint | varchar(64) | nullable, no default | SHA-256 reference to exact reviewed snapshot |
| delivery_contact_method | varchar(30) | nullable, no default | Application-validated `phone`, `email`, `other`; not a database ENUM |
| delivery_contacted_by_user_id | unsigned bigint FK users.id | nullable, no default; ON DELETE SET NULL | Staff who recorded direct contact |
| delivery_contacted_at | datetime | nullable, no default | Server-recorded contact-attestation instant (UTC storage), not a client-supplied retrospective timestamp |
| delivery_recipient_available | boolean | nullable, no default | NULL unknown/invalidated; false explicitly unavailable; true is only a candidate for current readiness |
| delivery_readiness_schedule_version | unsigned integer | nullable, no default | Exact associated schedule revision |
| delivery_readiness_schedule_fingerprint | varchar(64) | nullable, no default | Agreed method/window/confirmation fingerprint |
| delivery_readiness_destination_fingerprint | varchar(64) | nullable, no default | Exact destination to which availability applied |
| delivery_readiness_method | varchar(20) | nullable, no default | Confirmed delivery method snapshot |
| delivery_readiness_release_attempt | unsigned integer | nullable, no default | Associated reopen/release attempt |
| delivery_readiness_notes | text | nullable, no default | Optional staff-only contact notes; request limit 1,000 characters; required for `other` |

Relationships: `Handover::deliveryAddressVerifiedBy()` and `Handover::deliveryContactedBy()` each belong to `User`. MySQL creates supporting indexes for the two foreign keys. No extra scan/index is needed for these ID-addressed mutations. Removing a referenced staff account nulls its FK and makes affected unreleased clearance fail closed. No cascading deletion of handovers is added.

Computed, not persisted: `addressIsVerified()`, `scheduleIsConfirmed()`, `readinessIsCurrent()` and the pre-dispatch notification fingerprint. The existing outbox stores the latter in its existing `schedule_fingerprint` field **only for** the new `delivery_pre_dispatch` kind. Other message kinds retain their existing fingerprint and reminder rules.

## Notifications and courier-booking boundary

There was no separate booking/dispatch-preparation event: provider/reference/URL are saved by the existing release request. The new positive-readiness event is a genuine **pre-dispatch preparation/communication event**, not a claimed courier booking. Its message explicitly says the pet has not yet been marked released. Staff direct contact is the communication evidence; the automatic notice is supplemental.

- Correct recipient is the handover's account owner, not an arbitrary submitted email/recipient or cached address.
- Notice links to the authenticated adopter's protected Handover Status route. No raw courier tracking URL, full destination, contact note or destination hash is included in mail/in-app text.
- In-app notice + email intent + readiness/history/audit commit atomically. Queue enqueue/transport failures use the existing Phase 3 recovery/retry path.
- Only one notice is created for a successful readiness mutation under the handover lock. The outbox retains its unique event keys, encrypted payload/jobs, claim tokens and duplicate-send guards.
- Before transport, the new kind is suppressed if recipient eligibility changes, the agreement/destination/clearance changes, availability is withdrawn, the window expires, release/receipt is recorded or the handover is reopened. It is never sent later as if post-release communication were pre-dispatch.
- Schedule reminder thresholds and cadence are untouched.
- Recorded/queued/transport-accepted are distinct from actual inbox delivery or reading. Release is not conditioned on an email acceptance acknowledgment; the task supplies no such policy. Durable queue backlog can cause the supplemental pre-dispatch email to be suppressed after release. The in-app preparation history and recorded direct contact remain available.
- Existing SMTP acknowledgment-crash/exactly-once limitations still apply. Do not automatically replay old unrelated pre-outbox jobs.

## Policy decisions still needed

- There is no verified third-party recipient authorization workflow in the baseline. This implementation supports the adopter only and does not invent delegation authority. Shelter policy and an audited delegation workflow are required before adding another recipient.
- There is no new destination-editing workflow. Resolve destination corrections through a separately designed, authorized process; never copy the current profile address over an approved snapshot automatically.
- Human staff decide how to authenticate/confirm the destination during review and assess actual availability. This is not geocoding, GPS proof, courier integration, biometric verification or a guarantee of truthfulness.
- Any contact-age timeout or requirement to wait for email acknowledgment requires an explicit shelter policy. Neither is invented here.

## Deployment considerations (not executed against production)

- Back up and review the actual target database. Deploy only with approval; this worktree is not committed/pushed.
- After compatible code is present, run the additive migration under the normal controlled deployment procedure. All new evidence fields remain NULL for existing rows; there is no data-fabricating backfill. Existing received/closed records retain their lifecycle/history. Unreleased deliveries must satisfy clearance before future release.
- Phase 5 depends on the integrated tracking, identity/schedule and outbox migrations. If the target has not received Phase 4B, review and apply all pending migrations in their normal timestamp order, not the Phase 5 migration alone.
- Application code and the migration must be coordinated; do not serve new code against a schema missing these columns. Clearing cache does not replace running migrations.
- Keep the existing APP_KEY stable so queued/outbox encrypted payloads remain readable; review any key rotation separately.
- Restart supervised queue workers after the code update. Continue processing the emails/default queues and running the scheduler, including minute-by-minute outbox recovery. No scheduler interval change is required.
- Verify actual Azure/MySQL TLS, queue and scheduler uptime, SMTP acceptance/inbox receipt, recipient access, staff procedure and real-phone/desktop presentation in an approved environment. Local fake/array transports prove behavior, not production email delivery.

## Test approach

`HandoverDeliveryClearanceTest` exercises real HTTP staff actions, actor/time spoof resistance, denied actors, snapshot preservation, stale references, address change/revert, direct SQL agreement changes, explicit unavailability, repeated forms, early/end/expired release, pickup exemption, private escaped notes, atomic rollback, enqueue/transport recovery, duplicate jobs, obsolete messages, pending reschedule revisions and receipt/monitoring safeguards.

Existing handover regression fixtures explicitly construct already-cleared synthetic deliveries where delivery release is the precondition of another test. They do not bypass the production guard and do not assert that legacy data is automatically cleared. No old tests or assertions are deleted; dedicated new tests cover the real clearance actions and missing-clearance rejection.

Use only disposable databases. For MySQL, run the destructive `MatchingDemoSeederTest` in a separate PHP process, as documented in Phase 4B; its migrate:fresh/seed behavior otherwise contaminates subsequent tests. Supply the exact disposable `PAIRFECT_DISPOSABLE_MYSQL_DB` safety variable. Do not change production/development database environment files to run these checks.
