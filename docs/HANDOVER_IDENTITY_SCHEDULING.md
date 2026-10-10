# Identity verification and scheduled handovers

Implementation and local verification: October 10, 2026.

Working copy: `PAIRfect-Paws-frontend-v2`, branch
`feature/interview-type-2026-10-05`, based on `193bfe1`.
Remote master was fetched and compared (`8b6ba70`). No broad master merge,
commit, push, or Azure deployment was performed. Existing local changes were
preserved. The courier work already present locally is integrated into this flow.

## Staff and adopter workflow

1. Keep the existing document/OCR and interview workflow. From the application
   review modal, open **Identity Verification**. Staff record the five checks,
   method and determination. This is staff-assisted identity assurance, not
   government authentication or an automated identity check.
2. Approval requires the latest interview identity event to be verified, with
   all checks true and the current application/document fingerprint matching.
   The existing document requirement remains enforced. A replacement document
   or a later adverse identity event invalidates the previous positive result.
3. Open the approved placement in **Handover & Release**. Propose pickup or
   delivery, date, start time and end time. Planned fields are separate from
   actual release fields. Input and display use Asia/Manila; storage uses UTC.
4. The authenticated owner confirms the proposal or supplies one to three
   distinct future alternative windows. Completely blank rows are ignored;
   partial, duplicate, past or reversed windows are rejected.
5. Staff approve one requested window or decline. A currently confirmed window
   remains active while the request is pending and after a decline. If no
   confirmed window exists, declining requires another staff proposal.
6. Both release methods require a confirmed, owner-agreed schedule. Pickup
   additionally requires a final in-person identity event for the current
   release attempt and agreed window. Changing the agreed window or reopening
   invalidates that final check; merely requesting a replacement does not.
7. Delivery requires an approved provider, separate tracking reference, and
   HTTPS tracking URL. Only Pet To Go Express, Xpress Pet Taxi and GrabPet
   Philippines are selectable. Provider labels are mapped server-side.
   GrabPet's transport-requirements notice remains displayed; no Grab API exists.
8. Staff record actual release. The authenticated adopter uses the existing
   receipt proof/confirmation workflow. Received remains terminal; only this
   receipt confirmation activates post-adoption monitoring.

Already-approved, unreleased legacy placements may record the missing interview
identity event without reversing approval. Legacy handovers retain unscheduled
defaults: no fabricated confirmation/date, no automatic reminder. They need a
new agreed schedule before another release. Existing released records remain
accessible and may complete the existing receipt flow without retroactive checks.

## Reminders and follow-up

`handovers:process-schedule-reminders` is registered every 15 minutes with
Asia/Manila timezone and `withoutOverlapping()`.

| Condition | Action, once per agreed window/attempt |
| --- | --- |
| Confirmed window starts within 24 hours, but more than 2 hours away | Pickup/delivery reminder to adopter |
| Confirmed window starts within 2 hours, before its start | Final availability/ID reminder to adopter |
| Delivery released, no outcome, window ended at least 1 hour ago | Receipt reminder to adopter |
| Delivery released, no outcome, window ended at least 3 hours ago | Staff follow-up flag, administrator bell notification and staff email |
| Pickup unreleased, no outcome, window ended at least 1 hour ago | Missed/uncompleted pickup marker; adopter and staff notified |

These are operational safeguards, not statutory deadlines. A late scheduler run
does not send both pre-handover reminders together. Reminder/escalation processing
uses persistent sent-at markers, row locks, transactions and conditional updates.
Approved replacement schedules and reopened attempts reset the markers. Received
or otherwise resolved transfers are excluded. The old two-day urgency display was
replaced by these markers; the legacy `days_waiting` accessor remains unused by
handover views. Manual staff reminders remain available with neutral wording.

Escalation does not automatically fail delivery, declare a pet missing, cancel
the adoption, reopen the handover, or start monitoring. A missed pickup does not
release the pet or set an interview NoShow status. Staff must investigate.

## Authorization and privacy

- Approval and release gates are enforced server-side, not just in templates.
- Identity actor and time are generated server-side; submitted verifier/time
  fields are ignored. No facial recognition, biometric records, duplicate ID
  files or adopter-holding-ID screenshots were added.
- Courier riders do no identity work and have no new login, OTP or OCR access.
- Only the owner and authorized staff can view tracking through protected
  handover pages. Links open with `target="_blank"` and
  `rel="noopener noreferrer"`. Tracking URLs are hidden from model serialization.
- Notifications link to authenticated pages, never the raw tracking URL. New
  identity/scheduling audits contain IDs, states and revision information, not
  OCR text, government-ID contents, staff discrepancy notes or tracking URLs.
- No iframe, scraping, proxy, GPS collection or courier API integration was added.
- Document private storage and private receipt-proof/hash protection were retained.
  **Existing staff release photos still use this branch's public `proof_url`
  storage. This feature does not make those older release photos private.**
- Existing receipt transactions, release/reopen locks and Received terminal
  guards remain. Schedule forms reject stale revisions. Mail uses the existing
  after-commit notification infrastructure.

## Migrations

New additive migrations, both applied to the existing local MariaDB without
resetting records, and repeatedly run against disposable SQLite test databases:

- `database/migrations/2026_10_10_000001_add_tracking_url_to_handovers_table.php`
  adds nullable TEXT `tracking_url` (courier work already present).
- `database/migrations/2026_10_10_000002_add_identity_verifications_and_handover_schedules.php`
  adds `identity_verifications` and separate schedule, confirmation, reschedule,
  revision and reminder-marker fields on `handovers`, with foreign keys/indexes.

No old migration was edited. KNN, BFI, queue ranking, behavioral assessment,
OCR extraction, welfare-video verification and the 3-3-3 milestones were unchanged.

## Added routes

All are under existing authenticated, verified-email and role middleware.
Owner checks additionally protect adopter mutations.

| Method and path | Name |
| --- | --- |
| GET `/admin/applications/{application}/identity-verification` | `admin.applications.identity` |
| POST same path | `admin.applications.identity.store` |
| POST `/admin/handover/{handover}/schedule` | `admin.handover.schedule` |
| POST `/admin/handover/{handover}/reschedule/review` | `admin.handover.reschedule.review` |
| POST `/adopter/{handover}/schedule/confirm` | `adopter.handover.schedule.confirm` |
| POST `/adopter/{handover}/reschedule` | `adopter.handover.reschedule` |

Existing release, reopen, receipt, protected document and proof routes were retained.

## Changed files

New identity/scheduling files:

- `app/Models/IdentityVerification.php`
- `app/Services/IdentityVerificationService.php`
- `app/Services/HandoverScheduleService.php`
- `app/Services/HandoverReminderService.php`
- `app/Http/Controllers/Admin/IdentityVerificationController.php`
- `app/Http/Controllers/HandoverScheduleController.php`
- `app/Console/Commands/ProcessHandoverScheduleReminders.php`
- `database/migrations/2026_10_10_000002_add_identity_verifications_and_handover_schedules.php`
- `resources/views/admin/application/_identity-form.blade.php`
- `resources/views/admin/application/identity-verification.blade.php`
- `resources/views/admin/handover/_schedule.blade.php`
- `resources/views/handover/_schedule-summary.blade.php`
- `resources/views/handover/_schedule-actions.blade.php`
- `tests/Concerns/PreparesVerifiedHandovers.php`
- `tests/Feature/HandoverSchedulingIdentityTest.php`
- `docs/HANDOVER_IDENTITY_SCHEDULING.md`

Modified identity/scheduling integrations:

- `app/Models/AdoptionApplication.php`
- `app/Models/Handover.php`
- `app/Services/ReservationQueueService.php`
- `app/Services/HandoverNotificationService.php`
- `app/Http/Controllers/Admin/HandoverController.php`
- `routes/web.php`
- `routes/console.php`
- `public/js/admin/application.js`
- `resources/views/admin/application/_review-modal.blade.php`
- `resources/views/admin/application/index.blade.php`
- `resources/views/admin/handover/show.blade.php`
- `resources/views/admin/handover/index.blade.php`
- `resources/views/admin/handover/_adopter_confirmation.blade.php`
- `resources/views/admin/handover/_release_form.blade.php`
- `resources/views/handover/status.blade.php`
- `resources/views/handover/confirm.blade.php`
- `tests/Feature/HandoverTrackingTest.php`
- `tests/Feature/HandoverWorkflowTest.php`
- `tests/Feature/ReservationQueueTest.php`
- `tests/Feature/AdoptionPipelineStateMachineTest.php`
- `tests/Feature/VolunteerRbacTest.php`
- `docs/AZURE_F1_PILOT.md`

Existing uncommitted courier files integrated/preserved:

- `config/handover.php`
- `database/migrations/2026_10_10_000001_add_tracking_url_to_handovers_table.php`
- `resources/views/handover/_live_tracking.blade.php`
- `resources/views/admin/handover/_recorded_release.blade.php`
- Courier portions of the shared model/controller/notification/forms/tests above.

Unrelated existing changes to `resources/views/recommendation/_match-card.blade.php`,
`resources/views/recommendation/results.blade.php`, and
`tests/Feature/RecommendationEligibilityTest.php`, and the untracked `get())` file
were left untouched. Ignored QA artifacts are under `output/playwright/`; neither
the real `.env` nor credentials were changed.

## Verification results

- `php artisan test`: **352 passed, 3,113 assertions**, no failures.
- New identity/scheduling suite: **22 passed, 177 assertions**. Includes approval
  gates, checks, actor spoofing, document replacement, adverse events, pickup
  rechecks/window changes/reopen, proposal ownership and stale forms, 1–3 options,
  invalid rows, pending/declined behavior, frozen-time 24h/2h/1h/3h notifications,
  staff escalation, repeat-run idempotence and terminal/legacy exclusions.
- Courier regression tests cover provider allowlists, invalid schemes, long
  trimmed URLs, pickup clearing, lifecycle visibility, owner/staff authorization,
  release proof preservation and Received/reopen protections.
- `npm.cmd run test:frontend`: **3 passed**.
- `npm.cmd run build`: **PASS**.
- Pint check on new identity/scheduling PHP files: **PASS**.
- `git diff --check`: **PASS**.
- `artisan migrate:status`: both new migrations **Ran** locally.
- `artisan schedule:list`: the every-15-minute reminder task is registered.
- Browser QA: staff recorded interview identity and proposed a delivery window;
  owner confirmed, requested an alternative while the old slot stayed active;
  staff accepted and owner saw the new confirmed date. No console errors or
  warnings; owner page fits a 390px viewport without horizontal overflow.
  QA used a disposable SQLite database and disabled outbound email delivery.
  Real courier tracking availability and production SMTP delivery were not tested.
- `php artisan release:check`: **FAIL** on existing local configuration:
  non-production APP_ENV, non-production HTTPS APP_URL, enabled time travel,
  missing valid privacy contact, missing executable ffprobe and synthetic pets.
  This is not a production-readiness certification or a MySQL 8 validation.

## Future Azure deployment steps (not executed)

After approval to deploy this code, back up the production database, deploy
the code/build, then run in App Service SSH:

```sh
cd /home/site/wwwroot
php artisan migrate --force
php artisan optimize:clear
php artisan schedule:list
php artisan email:health
```

Do not run `migrate:fresh`, seeders or database resets in production.

Azure F1 does not automatically run Laravel's scheduler. A reliable external
trigger must execute `php artisan schedule:run` every minute. The new task runs
on its registered 15-minute intervals; actual notifications can be up to one
interval later than their threshold. A one-off SSH command is not automation.
Database-queued mail also needs a reliably running queue worker. Sync mail does
not require a worker but remains non-durable. No external scheduler, Azure
resource, worker provisioning, credit expenditure or deployment was performed.

For a deliberate manual reminder test, after confirming the correct production
records and timing, `php artisan handovers:process-schedule-reminders` processes
all due confirmed handovers and may send real emails. It is not a dry run.
