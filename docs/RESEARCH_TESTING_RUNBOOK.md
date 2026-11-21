# Research cutover — operator checklist, not automatic deployment

**Stop gate:** the operator reports a downloaded SQL export of `pairfect_paws` containing 23 tables. Its completeness and restorability are unknown. Do not replace data, redirect traffic or enable research until a rehearsal succeeds. Keep the original database intact. The pre-survey tag and branch preserve code at `9813ddb7243ed947f7e23a7904d0c28ff636c3d9`, not runtime state.

1. Get shelter/data-owner approval for consent, enrollment, retention, authorized access, manual photos and the cutover window. Keep a manifest of UTC snapshot time, deployed RELEASE.json SHA, schema/table inventory and row counts, migration ledger, upload counts/checksums, and configuration/secret references. Under a coordinated pause of HTTP writes, queues and scheduler, take a consistent final backup. Back up private/public uploads, any external storage and the original APP_KEY securely. Keep exports encrypted, restricted and outside Git/webroot.
2. Restore the export with a restricted account on an **isolated MySQL instance**. Inspect dump database-selection statements first; never run it on the live server. Compare schema, foreign keys, row counts per table and the migration ledger against the source manifest; check representative DB/file associations without exposing private records. Verify the original app starts against the restored DB, with email and all background jobs isolated. A count of 23 tables alone is not proof. Resolve any mismatch before cutover.
3. Provision a **new empty schema** (example: `pairfect_research_survey01`) and a credential restricted to that schema, never the original application's credential. Under DBA approval: `CREATE DATABASE pairfect_research_survey01 CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;`. Do not use IF NOT EXISTS, DROP, TRUNCATE, migrate:fresh, migrate:refresh or the default DatabaseSeeder. Rehearse the following steps on an isolated copy of the approved candidate first.
4. Only after explicit release approval, submit the reviewed branch as a PR to master. Merging triggers the existing validate/package/Azure workflow. Use infrastructure-level access restriction or approved downtime throughout cutover so no traffic or background workers use a half-configured installation. Do not rely solely on a Laravel maintenance file surviving package activation. No workflow edits or automatic database migration are required.
5. On the existing App Service, record original settings. Set `APP_ENV=production`, `APP_DEBUG=false`, `RESEARCH_MODE=true`, `DB_CONNECTION=mysql`, `DB_DATABASE=pairfect_research_survey01`, the research-only DB username/password and actual TLS CA/host/port. Remove conflicting DB_URL. Isolate uploads (persistent separate mount/storage for storage/app/private and storage/app/public), session cookie, cache namespace, queue and scheduler from the old environment. Use a separate securely generated research APP_KEY and cookie name; invalidate previous sessions on cutover. Verify private storage stays private. Do not reuse old queued messages. Keep ordinary scheduler/worker processes paused until deliberately configured for this database. Configure an approved verification mail channel for participants; registration still requires verification. Do not seed shared login credentials.
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
php artisan tinker --execute='dump(DB::table("research_catalog")->count(), App\Models\Pet::fullyAssessed()->count(), DB::table("assessment_records")->count(), DB::table("research_placements")->count());'
php artisan config:cache
```

Expect **50 / 50 / 150 / 0** before use. Stop on errors. The catalog transaction refuses a database with users or pets; it never clears one. For a failed rehearsal or a new wave, provision a different empty research schema and repeat, retaining the previous one. Do not retry initialization by deleting rows. No generated pet images. An authorized operator may provision a new named research administrator through an audited account-provisioning process, then upload approved fictional pet photos via Animal Records. Synthetic observers cannot log in.

7. Have each consenting participant register and verify their own adopter account. Confirm their identity against the consent roster before entering the numeric **research DB** user ID in SSH:

```sh
php artisan research:enroll 123
```

Replace 123; there is no public self-enrollment or email-verification bypass. Before opening enrollment, test verified and unenrolled accounts, an incomplete questionnaire, a complete eligible match, two accounts selecting the same animal, receipt and all three successive practice reports. Check that formal applications/handovers/logs remain empty and there are no real placement emails. Review the consent and authentication email delivery/privacy configuration separately. The simulated pathway intentionally does not test live OCR, actual interviews, courier integration, receipt evidence, camera uploads, reminder delivery or emergency escalation.

8. **Recovery:** restrict traffic; pause research jobs, take an access-controlled research data backup under the approved retention policy, then restore the recorded original DB connection, original APP_KEY, session/cache settings, uploads and mail/worker configuration. Redeploy the pre-survey source through a reviewed recovery commit/PR, not a force push. Clear/rebuild configuration cache; verify RELEASE.json, schema compatibility, uploads, login, and queues before admitting normal traffic. Do not run down migrations on the original DB. Turning the flag off alone does not switch databases. Never roll research rows into real shelter history.
