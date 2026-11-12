# Existing-site upgrade controls — Phase 18A

This is preparation, not permission to publish, dispatch, migrate or deploy. The
existing Azure app remains **pairfectpaws / Production** with the same three OIDC
identity secret references. No new app, slot, database or cloud resource is needed.
The actual deployed SHA, schema, settings and storage mode remain UNKNOWN until an
authorized operator establishes them. Remote master is not evidence of live state.

## Behavior and approval boundary

- Master pushes and PRs targeting main/master run validation only. This deliberately
  replaces the previous automatic master deployment with a manual, master-only upgrade.
- A manual dispatch requires the exact 40-character current master SHA and completed
  schema-evidence JSON. Selecting another ref, another repository or mismatched SHA
  cannot enter runtime/deploy. Validation failures prevent both dependent jobs.
- Only deploy receives Azure/OIDC permission. PRs do not use production credentials.
- Deploy jobs use one existing-site concurrency group, without cancelling a running
  upgrade. GitHub does not guarantee FIFO; current-master checks after approval and
  immediately before activation reject stale queued releases.
- Runtime archive digest travels from the same run's build output, is verified before
  extraction, and every extracted file is verified against RELEASE.json. Reviewers
  approve the exact run/SHA/digest, not an unspecified branch.
- Deployment requires an independently protected environment AND actual recorded
  approval for that run/environment. API failure, missing protection, wrong branch
  policy, bypass without recorded independent approval or stale evidence blocks it.
  These are read-only GitHub checks, not Azure inspection.

**Limits:** GitHub approval is human attestation, not cryptographic proof of a backup
or completed migration. Evidence IDs/checksums alone cannot establish those facts.
The release owner must inspect private operational evidence before approval.
Neither GitHub nor this script locks Azure and the Git branch atomically. Freeze
master promotions and all other deployment channels for the full approved window;
do not use SSH/manual deploys concurrently. A last-millisecond ref change after the
final check cannot be eliminated by a YAML concurrency group. Runtime/deploy reject
run attempts beyond the first; a retry requires a NEW authorized dispatch and
independent approval of fresh evidence/digest and review of any prior deployment.

## Required external repository settings — NOT configured here

1. Separately authorize publication/review of this branch. Protect master and the
   workflow/deployment scripts (CODEOWNERS and required reviews/status checks).
   Recheck current main/master and reconcile newer commits in an isolated branch.
   Do not force-push or silently drop groupmate changes.
2. Before any authorized dispatch, create/configure environment
   **prototype-existing-site**: required release-owner reviewers, prevent self-review,
   disable administrator bypass, and exactly one deployment branch rule of type
   branch named master (no tags, wildcards or other branches).
   Merely declaring the environment in YAML does not create reviewer protection.
   Verify the repository visibility/plan supports required reviewers. If unavailable,
   deployment is blocked: do not substitute a plain variable or remove the checks.
3. Set environment variable PAIRFECT_EXISTING_SITE_URL to the operator-verified
   existing HTTPS origin, either https://www.pairfectpaws.app or
   https://pairfectpaws.app. No arbitrary URL/redirect target is accepted.
4. Retain the existing three Azure secret references; do not paste their values in
   source/logs. Verify actual Azure federated trust accepts the protected-environment
   subject for this repository. Adding an environment changes the OIDC subject;
   existing branch-based trust may reject it. Any Azure trust change needs separate
   approval and is NOT made by this task. Verify no other credential/hook bypasses it.
5. Give deploy only contents:read, actions:read and id-token:write; other jobs keep
   contents:read. GitHub environment/review-history API reads must succeed or the
   gate blocks. Never add a broad PAT to bypass a read failure.
6. Confirm external/Deployment Center hooks, branch policies and other workflows do
   not auto-deploy around this gate. Existing source cannot prove that.
7. Pin/review third-party action revisions under repository supply-chain policy
   before activation. Existing action major versions/Node26 expectations are retained;
   actionlint is pinned by image digest. No lockfile/dependency upgrade is performed.

GitHub documents environment reviewers and their plan limitations in
[environments](https://docs.github.com/en/actions/reference/workflows-and-actions/deployments-and-environments).
The gate uses read-only [environment](https://docs.github.com/en/rest/deployments/environments)
and [run approval-history](https://docs.github.com/en/rest/actions/workflow-runs#get-the-review-history-for-a-workflow-run)
APIs. Concurrency is a single-writer control, not guaranteed queue ordering
([GitHub concurrency](https://docs.github.com/en/actions/how-tos/write-workflows/choose-when-workflows-run/control-workflow-concurrency)).

## Schema-first gate — operator sequence, separately authorized

There is NO automatic live migration, seeding, reset, cache purge or database
connection in deployment scripts. Schema preparation must already be complete:

1. Establish actual active revision/artifact/file hashes and actual database identity,
   MySQL version, schema/indexes/FKs/defaults/triggers and applied migration rows.
   Compare the entire deployed-to-release delta, not just the last commit.
2. Preserve existing accounts, uploads, documents/private proofs, APP_KEY, secrets,
   jobs/outbox and application history. Securely back up database + uploads + old
   artifact/config references; prove restoration in an authorized private disposable
   target. Record recovery point/time and reconciled counts/files/encryption.
3. Approve the actual pending migration list and SQL from the exact candidate.
   Source inventory has 50 migration files at the Phase16.1 base; seven are new versus
   the reviewed old master, but that is NOT the live pending list. Check partial DDL
   and drift. MySQL DDL can implicitly commit and wait on metadata locks.
4. Obtain maintenance/write-freeze approval; pause actual schedulers/producers and
   managed workers, not merely queue:restart. Stage exact candidate CLI code privately
   outside the active/public root on the existing host. Preserve old deployment.
5. Only with separate migration authorization, apply the reviewed pending migrations
   from that staged code. No migrate:fresh, reset, broad seeder or prototype normalizer
   apply against the existing site. Verify full schema and retained history. A
   migrations-table row alone does not prove a multi-statement migration completed.
6. Demonstrate old/new code/schema compatibility, private/public storage preservation
   for the actual Azure package mode, platform/media/OCR prerequisites and a tested
   recovery option. F1 staging slots/Always On are not assumed.
7. Record sanitized evidence references/checksums below. Reconcile any intervening
   source/schema drift and choose a new release approval if it changes.
8. Dispatch the exact master SHA only after authorization. In the pending environment
   review, independently inspect all private evidence and the same-run runtime SHA-256
   from the runtime job summary. Approve only when every gate is truly complete.

### schema_evidence JSON contract

Do not submit secrets, backup contents, real user identifiers, signed URLs, tokens
or private file paths. Inputs/review summaries are visible to repository readers.
Use nonsecret evidence IDs; store actual evidence under authorized private custody.
No example claims a backup happened.

Fields (all required):

- release_sha: exact current master commit.
- deployed_sha: actual operator-established current deployed commit. If this cannot
  be established, STOP; do not invent one.
- migration_digest: SHA-256 of sorted filename:file-sha256 lines, with no trailing
  newline, for all release database/migrations/*.php. Locally compute with:
  node --input-type=module -e "import {migrationDigest} from './scripts/deployment/gate.mjs'; console.log(migrationDigest())"
- target: pairfectpaws/Production.
- operator: nonsecret operator identifier.
- completed_at: ISO8601 timestamp after the actual gate completed; no future date.
  Evidence expires after six hours. Expiry does not change live dates or permit a
  bypass; review/reverify and obtain a newly authorized gate if necessary.
- evidence: an object with each key below containing a nonsecret reference (at least
  12 characters) and sha256 (64 lowercase hex) of the corresponding private evidence:
  deployed_revision, schema_inventory, backup, restore_rehearsal, uploads_preserved,
  pending_migrations, schema_completed, compatibility, recovery,
  maintenance_and_workers_held, deployment_storage_review.

An empty pending list is valid only when private evidence proves it is actually empty.
Checks validate shape/binding/freshness, NOT the truth of the external operations.
Human independent approval remains blocking. Do not use a repository boolean as
backup evidence or alter APP_ENV to evade a production check.

## Validation and runtime packaging

CI uses PHP8.4, required extensions, locked Composer/npm installs and MySQL8.0.46.
Vite builds before layout-rendering features. SQLite main suite, SQLite destructive
seeder, MySQL main suite and MySQL destructive seeder run serially in separate
processes. They share fake-file roots; a parallel matrix is unsafe even with separate
databases. Mail=array, queue=sync, synthetic key/password and disposable loopback
MySQL pairfect_phase15c_ci prevent real data/mail usage.

All tracked PHP is linted; Pint covers PHP changed against PR base / push-before SHA,
or the reviewed Phase16.1 baseline for manual dispatch. Fourteen inherited failing
paths are excepted ONLY when their committed contents still exactly match that
baseline. The initial two were MonitoringController and ReservationQueueService;
an actual old-master-to-release scope rehearsal identified twelve more. Exact paths:

- app/Http/Controllers/MonitoringController.php
- app/Services/ReservationQueueService.php
- app/Http/Controllers/Admin/HandoverController.php
- app/Http/Controllers/HandoverConfirmationController.php
- app/Models/Handover.php
- config/matching.php
- routes/web.php
- tests/Feature/AdoptionPipelineStateMachineTest.php
- tests/Feature/HandoverTrackingTest.php
- tests/Feature/HandoverWorkflowTest.php
- tests/Feature/LandingPageFeaturedPetsTest.php
- tests/Feature/RecommendationEligibilityTest.php
- tests/Feature/ReservationQueueTest.php
- tests/Feature/VolunteerRbacTest.php

Inherited findings include import order, whitespace, strict type names, trait order,
quote style and empty-body/brace/operator formatting. They were reproduced using
Pint --test, not reformatted. Any modification makes Pint mandatory;
all other changed PHP is checked. Missing diff bases block, not skip quality checks.
There is no PHP source change in Phase18A. Frontend tests, deployment-tool tests,
actionlint (including ShellCheck), Blade compilation and Vite are required.

Runtime builds in a fresh runner after validation, with composer install --no-dev.
Allowed source: app/config/resources/routes/azure, bootstrap app/providers, artisan,
Composer manifests, required database PHP migrations/seeders/factories and tracked
public assets; separately generated public/build and production vendor files.
The normalization fixture catalog remains available; this does NOT authorize seeding.
Vendor test/docs/examples folders and Markdown/reStructuredText documentation are
omitted. Copyright/license files are retained. No dev package may be installed.

Excluded: .env including examples, auth/service-account/private-key files, database
files/dumps, tests, node_modules, Git metadata, output/reports/screenshots, untracked
files, all actual storage content, public storage/uploads/proofs, hot files/source
maps and bootstrap caches. Package scans private-key headers but is not a complete
secret scanner; protected source review/credential rotation remain separate.

Storage/app/private, storage/app/public, framework cache/data/sessions/views, logs and
bootstrap/cache are created EMPTY. Symlinks/nonregular entries fail packaging.
No existing public/storage link target is followed. Runtime tar preserves modes;
the verified extracted folder is passed to the existing Azure deploy action with
clean:false. This is NOT proof that Azure preserves live uploads. Operator review
of deployment/Oryx/run-from-package/mount semantics and backups is mandatory.
Builds must not regenerate APP_KEY, install dev packages or migrate on Azure boot.
The tar preserves local permissions, but the Azure folder-to-package activation
boundary still needs operator executable/media checks; it is not proven here.

RELEASE.json (outside public) records exact SHA, migration digest and every packaged
file hash. Runtime.tar.gz digest is passed as same-run job output; upload-artifact
adds its own transport integrity. Deploy verifies digest before extraction, then
rejects missing, changed, extra or forbidden files. Archives/reports stay separate.
Never package from a dirty developer checkout or include live uploads as code.

## Acceptance, operations and recovery

The smoke job makes bounded anonymous HTTPS GETs only: branded landing/login/privacy,
one exact Vite entry asset hash, denied environment/proof paths and protected staff
handover route. It rejects redirects for public checks, bounds each request to ten
seconds and two megabytes, and never sends email or submits forms. It cannot prove
PHP source parity, every route, OCR, media, queue continuity or actual inbox delivery.
The nonexistent proof canary is not evidence of real-file ACL enforcement: an
authorized owned-file check is still required. If maintenance serves HTTP503, smoke
fails; authorize a controlled acceptance/read-only window separately rather than
silently treating maintenance responses as a pass or auto-reopening writes.
Do not interpret Azure upload success OR these narrow smoke checks as final acceptance.

Before reopening, an authorized operator must correlate active code/RELEASE.json,
schema and private storage, targeted cache rebuilds at the final path, PHP/FPM and
FFPROBE/ADC configuration, queue:failed/email:health/schedule:list and actual
managed processes. Observe recurring scheduler invocation, worker restart/recovery
and approved inbox tests separately. These commands touch real systems and are not
authorized by this document. F1 background continuity remains an operational blocker.
Do not run optimize:clear blindly against shared live cache/mutex/demo state.

If upload/smoke fails: mark upgrade NOT accepted; retain the approved maintenance
and worker hold, inspect active code/schema/storage/logs and preserve failure state.
Do not automatically restore, run down migrations, rerun DDL, purge/retry jobs or
replay uncertain SMTP messages. Before code activation, unchanged old code may remain.
After additive schema, old code recovery requires demonstrated compatibility.
After new writes, prefer reviewed forward repair; restoring requires explicit
recovery-point/data-loss approval and consistent DB/uploads/key/code restoration.
Down migration loses new evidence/provenance/outbox/history and is NOT lossless.
SMTP acceptance is not inbox delivery; no exactly-once SMTP guarantee is claimed.

Frontend handoffs remain unchanged. 360px readiness clipping, 390px fixed-header
overlap and duplicate releaseFormSection ID remain assigned to frontend groupmates.
No Phase18A UI handoff is needed because no UI file is modified.

Local locked npm installation/audit reported three existing dependency advisories
(two high, one moderate: Vite, source-map-js, postcss-selector-parser). No automatic
audit fix or lockfile upgrade was performed. These are build-tool findings, not
proof of a live exploit; the security/dependency owner must assess and separately
approve remediation before release. node_modules is never in the runtime artifact.
