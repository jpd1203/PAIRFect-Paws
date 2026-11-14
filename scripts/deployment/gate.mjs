import assert from 'node:assert/strict';
import { createHash } from 'node:crypto';
import { readFileSync, readdirSync } from 'node:fs';
import { pathToFileURL } from 'node:url';

export const sha256 = value => createHash('sha256').update(value).digest('hex');
export const repository = 'jpd1203/PAIRFect-Paws';
export const environment = 'prototype-existing-site';
export function approvedSite(base) {
    const url = new URL(base);
    assert.ok(url.protocol === 'https:' && ['www.pairfectpaws.app', 'pairfectpaws.app'].includes(url.hostname)
        && !url.username && !url.password && !url.port && url.pathname === '/' && !url.search && !url.hash,
    'Configure the verified existing HTTPS site URL in the protected environment');
    return url;
}
export function allowedEvent(event, ref, sha, release, repo) {
    return event === 'workflow_dispatch' && ref === 'refs/heads/master'
        && /^[a-f0-9]{40}$/.test(release ?? '') && release === sha && repo === repository;
}
export function migrationDigest(root = '.') {
    return sha256(readdirSync(root + '/database/migrations').filter(f => f.endsWith('.php')).sort()
        .map(f => f + ':' + sha256(readFileSync(root + '/database/migrations/' + f))).join('\n'));
}
export function validateEvidence(data, release, digest, now = new Date()) {
    assert.match(release, /^[a-f0-9]{40}$/);
    assert.equal(data.release_sha, release, 'Evidence belongs to a different release');
    assert.equal(data.migration_digest, digest, 'Migration inventory changed');
    assert.match(data.deployed_sha ?? '', /^[a-f0-9]{40}$/, 'Actual deployed SHA must be established');
    assert.equal(data.target, 'pairfectpaws/Production');
    assert.ok(typeof data.operator === 'string' && data.operator.trim().length >= 3);
    const completed = Date.parse(data.completed_at);
    assert.ok(Number.isFinite(completed) && completed <= now.getTime()
        && now.getTime() - completed <= 6 * 3600_000, 'Evidence expired, future dated or incomplete');
    for (const key of ['deployed_revision', 'schema_inventory', 'backup', 'restore_rehearsal',
        'uploads_preserved', 'pending_migrations', 'schema_completed', 'compatibility',
        'recovery', 'maintenance_and_workers_held', 'deployment_storage_review']) {
        const proof = data.evidence?.[key];
        assert.ok(proof && typeof proof.reference === 'string' && proof.reference.trim().length >= 12,
            'Missing private operator evidence reference: ' + key);
        assert.match(proof.sha256 ?? '', /^[a-f0-9]{64}$/, 'Missing evidence checksum: ' + key);
    }
    // Presence/checksums do NOT prove the referenced live operations happened.
    // The independently configured environment reviewer must inspect them.
    return data;
}
export function validateEnvironment(data) {
    assert.equal(data.name, environment);
    assert.ok(Number.isInteger(data.id) && data.id > 0, 'Missing environment identity');
    const reviewers = data.protection_rules?.find(r => r.type === 'required_reviewers');
    assert.ok(reviewers?.reviewers?.length > 0, 'Environment lacks real required reviewers');
    assert.equal(reviewers.prevent_self_review, true, 'Independent approval is required');
    assert.equal(data.deployment_branch_policy?.custom_branch_policies, true,
        'Configure master-only environment branch policy externally');
}
async function github(path) {
    assert.ok(process.env.GH_TOKEN, 'Missing GitHub read token');
    const response = await fetch('https://api.github.com/repos/' + repository + '/' + path, {
        headers: { Authorization: 'Bearer ' + process.env.GH_TOKEN,
            Accept: 'application/vnd.github+json', 'X-GitHub-Api-Version': '2022-11-28' },
        redirect: 'error', signal: AbortSignal.timeout(15_000),
    });
    assert.equal(response.status, 200, 'Cannot verify GitHub gate: HTTP ' + response.status);
    return response.json();
}
export async function validateRemote(request, release, runId, actor) {
    assert.match(runId ?? '', /^\d+$/);
    assert.ok(typeof actor === 'string' && actor.length > 0, 'Missing dispatch actor');
    const branch = await request('git/ref/heads/master');
    assert.equal(branch.object.sha, release, 'Stale queued release: master has moved');
    const protectedEnvironment = await request('environments/' + environment);
    validateEnvironment(protectedEnvironment);
    const policies = await request('environments/' + environment + '/deployment-branch-policies?per_page=100');
    assert.equal(policies.total_count, 1, 'Allow only the master branch');
    assert.equal(policies.branch_policies?.[0]?.name, 'master');
    assert.equal(policies.branch_policies?.[0]?.type, 'branch');
    const approvals = await request('actions/runs/' + runId + '/approvals');
    assert.ok(Array.isArray(approvals) && approvals.some(a => a.state === 'approved'
        && a.user?.login && a.user.login.toLowerCase() !== actor.toLowerCase()
        && a.environments?.some(e => e.name === environment && e.id === protectedEnvironment.id)),
    'No independent approval recorded for this run and environment');
}
export async function main(mode) {
    const env = process.env;
    assert.equal(env.GITHUB_RUN_ATTEMPT, '1', 'Use a new dispatch and approval, not a rerun');
    assert.ok(allowedEvent(env.GITHUB_EVENT_NAME, env.GITHUB_REF, env.GITHUB_SHA,
        env.RELEASE_SHA, env.GITHUB_REPOSITORY), 'Unsafe event/ref/release/repository');
    const evidence = validateEvidence(JSON.parse(env.SCHEMA_EVIDENCE ?? ''), env.RELEASE_SHA, migrationDigest());
    if (mode === 'remote') {
        approvedSite(env.SITE_URL);
        assert.match(env.EXPECTED_DIGEST ?? '', /^[a-f0-9]{64}$/, 'Missing same-run archive digest');
        await validateRemote(github, env.RELEASE_SHA, env.GITHUB_RUN_ID, env.GITHUB_ACTOR);
    } else assert.equal(mode, 'evidence');
    console.log('Release gate structural checks passed; human approval must verify the private evidence.');
}
if (process.argv[1] && import.meta.url === pathToFileURL(process.argv[1]).href) {
    main(process.argv[2]).catch(error => { console.error(error.message); process.exitCode = 1; });
}
