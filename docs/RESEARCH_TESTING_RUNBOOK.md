# Research cutover — operator checklist, not automatic deployment

**Stop gate:** the operator reports a downloaded SQL export of `pairfect_paws` containing 23 tables. Its completeness and restorability are unknown. Do not replace data, redirect traffic or enable research until a rehearsal succeeds. Keep the original database intact. The pre-survey tag and branch preserve code at `9813ddb7243ed947f7e23a7904d0c28ff636c3d9`, not runtime state.

1. Get shelter/data-owner approval for consent, participant invitations, retention, authorized access, manual photos and the cutover window. Keep a manifest of UTC snapshot time, deployed RELEASE.json SHA, schema/table inventory and row counts, migration ledger, upload counts/checksums, and configuration/secret references. Under a coordinated pause of HTTP writes, queues and scheduler, take a consistent final backup. Back up private/public uploads, any external storage and the original APP_KEY securely. Keep exports encrypted, restricted and outside Git/webroot.
2. Restore the export with a restricted account on an **isolated MySQL instance**. Inspect dump database-selection statements first; never run it on the live server. Compare schema, foreign keys, row counts per table and the migration ledger against the source manifest; check representative DB/file associations without exposing private records. Verify the original app starts against the restored DB, with email and all background jobs isolated. A count of 23 tables alone is not proof. Resolve any mismatch before cutover.
3. Provision a **new empty schema** (example: `pairfect_research_survey01`) and a credential restricted to that schema, never the original application's credential. Under DBA approval: `CREATE DATABASE pairfect_research_survey01 CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;`. Do not use IF NOT EXISTS, DROP, TRUNCATE, migrate:fresh, migrate:refresh or the default DatabaseSeeder. Rehearse the following steps on an isolated copy of the approved candidate first.
4. Only after explicit release approval, submit the reviewed branch as a PR to master. Merging triggers the existing validate/package/Azure workflow. Use infrastructure-level access restriction or approved downtime throughout cutover so no traffic or background workers use a half-configured installation. Do not rely solely on a Laravel maintenance file surviving package activation. No workflow edits or automatic database migration are required.
5. On the existing App Service, record original settings. Set `APP_ENV=production`, `APP_DEBUG=false`, `RESEARCH_MODE=true`, `DB_CONNECTION=mysql`, `DB_DATABASE=pairfect_research_survey01`, the research-only DB username/password and actual TLS CA/host/port. Remove conflicting DB_URL. Isolate uploads (persistent separate mount/storage for storage/app/private and storage/app/public), session cookie, cache namespace, queue and scheduler from the old environment. Use a separate securely generated research APP_KEY and cookie name; invalidate previous sessions on cutover. Verify private storage stays private. Do not reuse old queued messages. Keep ordinary scheduler/worker processes paused until deliberately configured for this database. Research adopter registration sends no verification email and requires no operator approval. Set MAIL_MAILER=log (or an approved non-forwarding sink) for the study; do not configure live delivery unless separately approved. Do not seed shared login credentials. Keep the public study rollout gated until the study owner approves the consent process and access. Accounts still record terms/privacy consent, but that is not a substitute for research consent.
6. After the workflow succeeds, in Azure SSH, with traffic still paused:

```sh
cd /home/site/wwwroot
php -r 'echo json_decode(file_get_contents("RELEASE.json"),true)["release_sha"], PHP_EOL;'
php artisan config:clear
php artisan tinker --execute='dump(app()->environment(),config("app.debug"),config("research.enabled"),DB::selectOne("select database() as db, version() as version"));'
```

Stop unless SHA matches the reviewed deployment, environment is production, debug false, mode true and the selected database **exactly** matches the newly provisioned empty research schema. Verify the credential cannot write to the original schema. Confirm there are zero tables (`SHOW TABLES` on the research connection). Then:

```sh
php artisan migrate --force
php artisan db:seed --class=ResearchCatalogSeeder --force
php artisan migrate:status
php artisan tinker --execute='dump(DB::table("research_catalog")->count(), App\Models\Pet::fullyAssessed()->count(), DB::table("assessment_records")->count(), DB::table("research_placements")->count(), DB::table("adoption_applications")->count());'
php artisan config:cache
```

Expect **70 / 70 / 210 / 0 / 0** before use. Stop on errors. The catalog transaction refuses a database with users or pets; it never clears one. For a failed rehearsal or a new wave, provision a different empty research schema and repeat, retaining the previous one. Do not retry initialization by deleting rows. No generated pet images. For survey 01, use the administrator bootstrap below, then upload approved fictional pet photos via Animal Records. Synthetic observers cannot log in.

7. Share the website only with approved participants and obtain study consent before they register. They register using the normal form, are signed in immediately, complete the household/personality profile, and can apply without email verification or any SSH action. Existing terms/privacy consent and normal staff verification remain required. The research registration limit is 10 attempts/minute per IP; stagger registrations if respondents share a network. Provide synthetic IDs and safe practice media guidance; tell participants they can skip the ID and must not submit another person's document, real handover evidence, or unnecessary personal data. Use the study-approved data-minimization instructions.

   In two browser contexts, pick the same eligible pet: A applies and gets **Primary Candidate**; B sees **Soft-Reserved** and joins the waitlist. B cannot continue. Withdraw A and refresh B to verify promotion. On another pet, have A continue while B is waitlisted: A is approved, B sees Closed, and a new application is rejected. Test two submits against the same pet together (and two overlapping Continue clicks); confirm only one primary and one approval per pet. The current primary isn't displaced by new applicants; promotion sorts by current KNN score descending, then created time and ID ascending. Select a handover method, confirm the schedule, confirm receipt, and submit 3-day, 3-week, then 3-month practice reports without delay. Normal applications, handovers, courier actions, and production monitoring records/notifications must remain absent. Existing recommendation restrictions still apply; being assessed is not a guarantee of a match.

   Check remaining availability from MySQL on the research connection:

   ```sql
   SELECT p.species, p.availability_status, COUNT(*) AS pets
   FROM research_catalog r JOIN pets p ON p.id = r.pet_id
   GROUP BY p.species, p.availability_status;
   SELECT status, COUNT(*) AS applications FROM research_placements GROUP BY status;
   ```

   Initial availability: 57 Available (29 dogs, 28 cats), 11 On Hold and 2 Assessing. No automatic restocking. A withdrawn primary reopens the pet if nobody is eligible for promotion; Adopted is terminal for this batch.

   For a new batch, require explicit administrator/study-owner approval, close enrollment and pause writes, export and restore-test the outgoing research schema and archive it securely with its photos/configuration. Preserve users, consent, questionnaire answers and scenario history there. Provision a different empty `pairfect_research_*` schema and repeat initialization; do not carry old sessions or seed over the preserved responses. The earlier 50-pet prototype batch (if used) must be retained separately, not migrated in place.

8. **Recovery:** restrict traffic; pause research jobs, take an access-controlled research data backup under the approved retention policy, then restore the recorded original DB connection, original APP_KEY, session/cache settings, uploads and mail/worker configuration. Redeploy the pre-survey source through a reviewed recovery commit/PR, not a force push. Clear/rebuild configuration cache; verify RELEASE.json, schema compatibility, uploads, login, and queues before admitting normal traffic. Do not run down migrations on the original DB. Set RESEARCH_MODE=false during the controlled switch back; turning the flag off alone does not switch databases. Never resume normal traffic with the research database still connected. Never roll research rows into real shelter history.

## Upgrade an already active survey to the full participant interface

Do not provision a new schema, reseed, run the administrator bootstrap again, or rerun the fresh setup for an existing survey. Prior submissions remain in place. This earlier upgrade removed IDs from research applications; the workflow-restoration upgrade below supersedes that part by restoring an optional initial upload, not replacements. Normal adoption still requires an ID.

The app no longer presents Study Information or records study participation consent. Before distributing access, collect voluntary study consent, provide example application details, and explain outside the app that no real placement or delivery occurs and how to withdraw. Do not treat the application's data-processing agreement as study consent. This release adds no migration and does not change earlier consent or document status records; the additive migration below remains required if it was not already applied.

1. Confirm study-owner approval, restore-tested backups of the **research** schema and its necessary configuration, and availability of the existing study APP_KEY. Pause participant traffic during deploy and migration. Keep research-only database credentials in use and leave the original database untouched.
2. After explicit authorization, publish a reviewed PR targeting master. Wait for validation and the existing automatic deployment. In Azure SSH:

```sh
cd /home/site/wwwroot
php -r 'echo json_decode(file_get_contents("RELEASE.json"), true)["release_sha"], PHP_EOL;'
php artisan config:clear
php artisan tinker --execute='dump(app()->environment(),config("research.enabled"),DB::selectOne("select database() as name", [], false)->name);'
php artisan migrate:status
```

Stop unless the release SHA matches the approved deployment, environment is production, Research Mode is on, and the actual database is `pairfect_research_survey01`. Review pending migrations. If the following migration is still pending, apply only that migration:

```sh
php artisan migrate --force --path=database/migrations/2026_11_23_000001_add_research_consent_and_application_details.php
php artisan migrate:status
php artisan config:cache
```

3. Check that an existing respondent can open Browse Pets without an interstitial, submit the adoption form without an ID and see retained applications. Verify the form rejects a missing agreement or an attempted document upload, and that there are no replacement controls. Confirm reservation, next applicant promotion, continuation, receipt confirmation and the shared welfare form. Test the required ID and document verification workflow separately with Research Mode off on a disposable environment. Use a reviewed code rollback if needed; leave the additive columns in place to retain study submissions.

### Cleanup of temporary files from earlier releases

Between the removal and restoration of research ID upload, `RESEARCH_OCR_ENABLED` had no effect. The restored workflow below uses this flag, off by default. Previously recorded verification outcomes and consent metadata are retained.

An earlier release used `storage/app/research-ocr`, a private, non-served disk. Do not expose it or include it in a backup. The hourly scheduler or the following dedicated command still prunes abandoned files older than one hour. If research does not run the ordinary scheduler, use this command to clear any leftover generated files:

```sh
php artisan research:purge-ocr-temp
```

Verify the purge on every instance that previously used local temporary files. Escalate cleanup failures to the study owner without sharing uploads. Study application details remain encrypted. At the study retention deadline, an authorized operator must revoke access, remove study exports and temporary files, and dispose of research-only records/backups under the approved plan, including any retained encryption keys when no longer needed. App-side cleanup does not affect documents that an earlier release sent to a provider.

### Upgrade the original administrator pages

This upgrade introduces no migrations, new tables, seeders, or Azure settings. It reads the existing research placements and check-ins through the original staff pages. Future automatic approvals create a system audit row in the same transaction. Earlier handovers are presented from their stored timestamps; they are not backfilled into real workflow tables.

1. Before approval, verify a recoverable backup of **the active research database** and preserve its encryption key and uploaded files. Keep `pairfect_paws` untouched. Confirm the previous research schema migrations are already applied. Use the release preflight above to check environment, actual schema name, and migration status; do not run migrations or seeders for this upgrade.
2. Put the isolated branch through a reviewed PR to `master`. After explicit authorization, merge only after GitHub validation passes. Watch the existing deployment run, then check `RELEASE.json` on App Service against the exact merged commit. Leave the existing research connection, flags, and storage settings in place.
3. In a staff browser session, inspect existing study records on Applications, Compatibility, Handover & Release and Monitoring. Check a current waitlist rank against the adopter-side rank. Confirm the report shows its actual answers/time without an inferred due date, and staff cannot trigger real actions. For a workflow canary that writes records, use a separate disposable clone; the active study does not need new test submissions.
4. If rollback is required, deploy a reviewed revert of this release through the same workflow. Do not roll back database migrations or reset the active research schema. Existing research data and the new audit events remain intact.

## Upgrade an existing survey: camera, optional document OCR, and handover

1. Rehearse the exact release and migration on a disposable restore. Get data-owner approval for the maintenance window, participant instructions/consent, private storage and retention. Reserve an approved private, writable persistent mount such as `/home/pairfect-research-private` and set `RESEARCH_PRIVATE_ROOT` to it. It must stay outside the webroot, public storage symlink, and any CDN mapping; use restricted access, encryption at rest and backups permitted by the retention plan. Preserve the current research APP_KEY, database credentials, upload locations and account/session settings; no key rotation is needed. Keep `RESEARCH_OCR_ENABLED=false` initially.
2. Verify HTTPS, PHP `proc_open`, and a trusted Linux `ffprobe` binary on the persistent App Service runtime. Set `FFPROBE_PATH` to its absolute executable path and verify it runs as the web application user after restart. Ensure PHP and the request gateway accept multipart bodies with room above the 12 MB video limit. The research path rejects captures when metadata/duration verification is unavailable. Real mobile devices and permissions should be checked with operators before opening access.
3. Use a reviewed PR targeting `master` and wait for all GitHub checks. After explicit authorization, pause participant traffic and any workers that write into the survey DB. Verify a recoverable encrypted backup of **pairfect_research_survey01** (including schema/metadata and private files where allowed), tested on an isolated restore with the matching key. Confirm the research DB account has no permission on **pairfect_paws**. Merge the reviewed PR; the existing workflow deploys the `master` artifact automatically and does **not** run migrations. Keep participants out until the schema and application are both verified.
4. In Azure App Service SSH, confirm the runtime is the expected release and that the writer is the exact initialized research database. Abort for any mismatch or unrelated pending migrations.

```sh
cd /home/site/wwwroot
cat RELEASE.json
test -x "$FFPROBE_PATH" && "$FFPROBE_PATH" -version | head -n 1
php artisan config:clear
php artisan tinker --execute='dump(app()->environment(), config("app.debug"), config("research.enabled"), DB::selectOne("select database() as name", [], false)->name, DB::table("research_catalog")->count());'
php artisan migrate:status
```

   Expect `production`, `false`, `true`, `pairfect_research_survey01`, and 70 catalog rows. Confirm the existing research and consent migrations are already applied. The only new migration for this release is additive; run it once while traffic remains restricted:

```sh
php artisan migrate --force --path=database/migrations/2026_11_24_000001_add_research_workflow_artifacts.php
php artisan migrate:status
php artisan config:cache
```

5. Check old placements, completed receipts and reports (including legacy surveys without a recording) on the existing staff pages. Use a disposable clone with a synthetic adopter to confirm pickup and delivery, reschedule, Not Received/retry, Received, all three camera reports, and private playback. Check that an adopter without permission cannot retrieve the staff file URLs. Before enabling OCR, confirm Cloud Vision API/billing and scoped Application Default Credentials (ADC), TLS/egress, processor/retention disclosures, and approval to transmit the uploaded file. Set `DOCUMENT_OCR_PROVIDER=google_vision` and `RESEARCH_OCR_ENABLED=true` only if approved; refresh the configuration cache/restart through the existing Azure procedure. Exercise one approved synthetic ID canary with the same runtime settings, and confirm that mismatch/unavailable outcomes still allow simulated approval. Keep OCR disabled if any prerequisite fails. Do not print credentials or raw OCR text.

### Optional administrator view of retained OCR text

- OCR and OCR text retention are separate settings. Leave `RESEARCH_OCR_RETAIN_TEXT=false` until the data owner approves retaining extracted text, its retention/deletion schedule, access and backup handling. Apply `2026_11_27_000001_add_ocr_extracted_text_to_research_placements_table.php` to the verified research database before enabling retention. Preserve `APP_KEY` so encrypted data remains readable. Do not run the migration against the original preserved database as part of this research rollout.
- With both `RESEARCH_OCR_ENABLED=true` and `RESEARCH_OCR_RETAIN_TEXT=true`, the upload agreement discloses retention. Only new, consented uploads with actual extracted text retain it. Older results cannot be reconstructed from the stored status or score; do not bulk reprocess existing participant documents.
- For screenshots, use a fresh synthetic applicant and ID with fictional information only. Sign in as an active, verified administrator and open **Applications → View → Document Verification → Details**. Verify the score and text, including a separate case with no readable text. Do not use actual participant or government IDs in screenshots.
- Disabling `RESEARCH_OCR_RETAIN_TEXT` stops capture and hides access; it does not erase previously retained ciphertext or backups. Include those in the approved deletion procedure. Leave the column in place if rolling back code; dropping it discards the retained text.
6. Reopen traffic only after verification. If the new application cannot be made healthy, deploy a reviewed revert of the release and leave its additive schema and data in place. A migration failure can leave partial MySQL DDL; inspect the schema and ledger under the maintenance window, and recover with an approved corrective migration or verified restore. Do not replay seeds, reset either database, or run the down migration. The other database and original uploads are not part of this upgrade.

### Retention for restored research uploads

Give participants the approved processor and retention information before testing; the application-specific checkbox authorizes the optional file processing but is not research participation consent. Use provided fictional IDs and practice media by default. Research OCR sends the file to Vision only after file-specific consent and only when the operator has enabled it. The app retains the file and outcome, not raw extracted text. Admins alone can download original IDs; authorized staff may review practice receipt photos and recordings. Restricted links stop serving the file once it is removed.

Before enabling uploads, the data owner must assign a retention deadline and an operator. At withdrawal or expiry, use the authorized research record IDs to resolve only that person's generated relative paths from the research database; record the deletion job by internal IDs, not payloads, names, or OCR text. Verify the exact research DB and canonical private disk root, then remove the selected files from that disk and applicable backup/replica copies per policy. Do not delete a whole directory with mixed participants. Clear the associated media reference through an authorized maintenance operation if required by policy; preserve consent and recorded outcome/history only where their retention is approved. Verify the authorized URL now returns no file. Check for orphaned generated files against the same schema after interrupted uploads before purging; do not log file contents or attach uploaded IDs to support tickets. Backups and any processor-side retention follow their own approved access and expiry procedures. The legacy `research:purge-ocr-temp` command is not a retention tool for these uploads.

## Upgrade an existing survey: Flagged Cases and follow-up

Rehearse this upgrade on an isolated restore of the research database. Before an approved merge to `master`, verify a recoverable backup of `pairfect_research_survey01`, keep the existing APP_KEY and private upload storage, and pause participant/staff writes and background writers. Wait for the existing Azure deployment workflow to succeed; it does not run migrations. Do not run these commands on `pairfect_paws`.

In Azure SSH, verify the active release SHA is the approved merge commit and the writer is the exact research schema:

```sh
cd /home/site/wwwroot
php -r '$r=json_decode(file_get_contents("RELEASE.json"),true); echo $r["release_sha"],PHP_EOL;'
php artisan config:clear
php artisan tinker --execute='dump(app()->environment(), config("app.debug"), config("research.enabled"), DB::selectOne("SELECT DATABASE() AS name", [], false)->name);'
php artisan migrate:status
```

Expect `production`, `false`, `true`, and `pairfect_research_survey01`. Stop if the SHA or database differs, or if any previous migration is pending. While writes are still paused, apply only the new migration, then review the non-mutating evaluation:

```sh
php artisan migrate --force --path=database/migrations/2026_11_26_000001_add_welfare_cases_to_research_checkins.php
php artisan research:evaluate-welfare --database=pairfect_research_survey01
```

If the dry-run totals are expected, apply the first-time evaluations and verify a second dry run has nothing left to evaluate:

```sh
php artisan research:evaluate-welfare --database=pairfect_research_survey01 --apply
php artisan research:evaluate-welfare --database=pairfect_research_survey01
php artisan config:cache
```

The command records only welfare metadata, attributes historical flags to the system, and does not replay notifications. It leaves answers, timestamps, recordings, and existing outcomes intact. Check the administrator Dashboard and Flagged Cases unresolved totals against each other. Use an authorized synthetic case on the isolated restore to verify manual flagging, administrator-only resolution, adopter follow-up, and private playback. A recorded return ends only that research placement's remaining check-ins; it does not mark any pet physically returned or alter pet availability. Review and reopen after the checks pass. Do not reset, truncate, or reseed either database. For recovery, deploy a reviewed code revert and keep the additive schema and recorded history; do not run the down migration.

## First administrator in the already initialized survey 01

Deploy the reviewed release containing the command first; **do not reinitialize the research database** and do not run an admin/demo seeder. In Azure App Service SSH:

```sh
cd /home/site/wwwroot
php artisan list research
php artisan tinker --execute='dump(app()->environment(), config("research.enabled"), DB::connection()->getDatabaseName(), DB::selectOne("select database() as name", [], false)->name);'
php artisan research:provision-admin
```

Stop if the command is absent or the preflight doesn't report `production`, `true`, and `pairfect_research_survey01` twice. Do not point the app at `pairfect_paws` to run this. If configuration is stale, diagnose it before proceeding; do not pass credentials or configuration overrides on the command line.

Enter the operator-approved first and last names and email for the named administrator. Answer yes only after confirming independently that the person controls that address. This deliberately uses the same privileged verification convention as the existing staff creation UI; the `verified` middleware is not bypassed. Use a password manager, enter the password at both hidden prompts, and keep it out of command arguments, chat and logs. The command refuses to overwrite or promote any user, to run without a terminal, or to create a second administrator. It creates no profile, sends no email, and records an audit event. For later accounts use the existing staff management screen.

On success, note the printed numeric user ID. Replace `123` below with that ID; this shows role and access state without selecting credentials:

```sh
php artisan tinker --execute='dump(DB::selectOne("select database() as name", [], false)->name, App\Models\User::findOrFail(123, ["id", "role", "is_active", "email_verified_at"])->toArray(), DB::table("research_catalog")->count());'
```

Expect the survey database, `Administrator`, an active account with a verification timestamp, and 70 catalog records. Sign in at `/login`, open `/admin/dashboard` and `/admin/animals`, and save one approved fictional image through an Animal Records edit. Refresh and confirm it loads. In a separate adopter session, visit both admin URLs and confirm access is denied. Confirm the release and SSH verification were run with the research-only credential; the original database should not be connected to or written to during this procedure. No provisioning migration is needed for an already initialized shared research schema.
