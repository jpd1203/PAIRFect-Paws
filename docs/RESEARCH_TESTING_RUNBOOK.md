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

7. Share the website only with approved participants and obtain study consent before they register. They register using the normal form, are signed in immediately, complete the household/personality profile, and can apply without email verification or any SSH action. Existing terms/privacy consent and normal staff verification remain required. The research registration limit is 10 attempts/minute per IP; stagger registrations if respondents share a network. Tell participants not to upload IDs, addresses beyond the existing registration requirements, or real handover media. Use the study-approved data-minimization instructions.

   In two browser contexts, pick the same eligible pet: A applies and gets **Primary Candidate**; B sees **Soft-Reserved** and joins the waitlist. B cannot continue. Withdraw A and refresh B to verify promotion. On another pet, have A continue while B is waitlisted: A sees simulated release, B sees Closed, and a new application is rejected. Test two submits against the same pet together (and two overlapping Continue clicks); confirm only one primary and one approved release per pet. The current primary isn't displaced by new applicants; promotion sorts by current KNN score descending, then created time and ID ascending. Confirm receipt manually and submit 3-day, 3-week, then 3-month practice reports without delay. Normal application, handover, OCR, courier, and production monitoring records/notifications must remain absent. Existing recommendation restrictions still apply; being assessed is not a guarantee of a match.

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

Do not provision a new schema, reseed, run the administrator bootstrap again, or rerun the fresh setup for an existing survey. Prior submissions remain in place. New research applications require the Adoption and Data Processing Agreement, but do not collect or replace IDs. Normal adoption still requires ID upload.

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

Research applications no longer send data to Google Vision, and the old `RESEARCH_OCR_ENABLED` setting has no effect. Do not change the normal application's provider settings for this release. Previously recorded verification outcomes and consent metadata are retained.

An earlier release used `storage/app/research-ocr`, a private, non-served disk. Do not expose it or include it in a backup. The hourly scheduler or the following dedicated command still prunes abandoned files older than one hour. If research does not run the ordinary scheduler, use this command to clear any leftover generated files:

```sh
php artisan research:purge-ocr-temp
```

Verify the purge on every instance that previously used local temporary files. Escalate cleanup failures to the study owner without sharing uploads. Study application details remain encrypted. At the study retention deadline, an authorized operator must revoke access, remove study exports and temporary files, and dispose of research-only records/backups under the approved plan, including any retained encryption keys when no longer needed. App-side cleanup does not affect documents that an earlier release sent to a provider.

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
