# PAIRfect Paws prototype: automatic master deployment

This release-only policy was requested by the project owner on 2026-11-14 to return to the previous **push to master, deploy to Azure, then migrate** testing workflow. It intentionally replaces the manual `prototype-existing-site` reviewer/evidence gates in the Phase 18A workflow. The original stricter policy remains documented in `docs/DEPLOYMENT_SAFETY.md`; it is **not the active deployment mechanism** if this release branch is promoted to master.

## What a master push does

1. Runs the locked PHP 8.4, SQLite, MySQL 8, frontend, lint, Blade, build and deployment-tool test checks using synthetic data.
2. Only if validation succeeds, builds and verifies a minimal runtime package, excluding credentials, tests, local uploads, databases and caches.
3. Rejects a run if master has moved to a newer commit, then logs in using the existing branch-based Azure OIDC secret references.
4. Deploys to the existing Azure App Service `pairfectpaws`, `Production`, with `clean: false`.
5. Prints the operator's **manual migration** handoff in the run summary.

The workflow does **not** migrate, seed, reset, automatically restore, or replay emails. It does not automatically run post-deployment smoke checks before the schema is ready. `workflow_dispatch` is no longer configured for deployment.

## Confirmed Azure observation before preparing this release

A project operator opened Azure SSH at `/home/site/wwwroot`, confirmed Laravel Framework 13.34.0, and ran `php artisan migrate:status`. Exactly one old-code migration, `2026_10_05_000004_add_follow_up_fields_to_post_adoption_logs`, was pending; the operator ran `php artisan migrate --force` successfully and confirmed it was `[4] Ran`. This does **not** apply or verify the seven newer migration files introduced by the current development branch.

## Before promoting this branch to master

- Keep an Azure SSH session available and able to access the **correct existing database**.
- Preserve a recoverable database backup and desired uploaded pet/document/proof files. All records may be fictional, but losing them unintentionally is still avoidable.
- Verify the live Azure deployment, PHP runtime, startup command, credentials, storage and database connection. Do not display credentials in shared logs.
- Check this release branch's PR validation; the two newest UI commits followed the earlier full-suite run.
- Confirm the new migrations are compatible with the existing database. An after-deploy migration means a short period of website errors may occur until the schema update completes.
- Coordinate a brief prototype testing interruption. Do not force-push or overwrite unrelated commits.

## Immediately after a successful Azure code deployment

In the **existing production App Service SSH session**:

```bash
cd /home/site/wwwroot
php artisan migrate:status
php artisan migrate --force
php artisan migrate:status
```

Expect the current source's newly added migrations to change from Pending to Ran. **Stop if any migration fails; do not reset or fresh-migrate the database.** Confirm logs and schema before taking another action. If stale cached configuration/views are demonstrated, review and run only the necessary Laravel cache-clearing command; do not regenerate APP_KEY or indiscriminately clear business data.

Then test `https://pairfectpaws.app`: homepage, login, animal images, Dog/Cat size filtering, recommendations, application submission, admin handover, tracking links, registered email and Google OCR behavior. Verify uploads survived the package deployment. The existing source smoke script can be used separately after migrations when its assumptions (public pages return 200 directly) are met.

## On failure

A green GitHub deploy step means code was uploaded, **not** that database migration or end-to-end acceptance succeeded. Inspect GitHub Actions, Azure Log Stream and the exact current `migrations` table. Avoid rerunning a partially applied schema change without review. Reverting code may not revert schema, so select recovery deliberately.

This branch is preparation only. Do not merge into master until the operator is positioned to run the pending migrations.
