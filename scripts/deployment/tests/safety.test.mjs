import assert from 'node:assert/strict';
import { execFileSync } from 'node:child_process';
import { mkdtempSync, mkdirSync, readFileSync, rmSync, writeFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import path from 'node:path';
import { createServer } from 'node:http';
import { test } from 'node:test';
import { allowedEvent, environment, migrationDigest, repository, sha256,
    validateEnvironment, validateEvidence, validateRemote } from '../gate.mjs';
import { allowed, stageRuntime, unpack, verifyRuntime } from '../artifact.mjs';
import { smoke } from '../smoke.mjs';

const sha = 'a'.repeat(40);
const digest = 'b'.repeat(64);
const now = new Date('2026-11-12T04:00:00Z');
const keys = ['deployed_revision', 'schema_inventory', 'backup', 'restore_rehearsal',
    'uploads_preserved', 'pending_migrations', 'schema_completed', 'compatibility', 'recovery',
    'maintenance_and_workers_held', 'deployment_storage_review'];
const evidence = () => ({ release_sha: sha, migration_digest: digest, deployed_sha: 'c'.repeat(40),
    target: 'pairfectpaws/Production', operator: 'synthetic-operator', completed_at: now.toISOString(),
    evidence: Object.fromEntries(keys.map(k => [k, { reference: 'private-evidence/' + k, sha256: digest }])) });
const workflow = readFileSync(new URL('../../../.github/workflows/master_pairfectpaws.yml', import.meta.url), 'utf8');
const conditions = [...workflow.matchAll(/^    if: (github.event_name[^\n]+)/gm)].map(m => m[1]);
for (const [event, ref, release, repo, expected] of [
    ['push', 'master', sha, repository, false],
    ['pull_request', 'master', sha, repository, false],
    ['pull_request_target', 'master', sha, repository, false],
    ['workflow_dispatch', 'main', sha, repository, false],
    ['workflow_dispatch', 'feature/demo', sha, repository, false],
    ['workflow_dispatch', 'master', 'd'.repeat(40), repository, false],
    ['workflow_dispatch', 'master', sha, 'someone/fork', false],
    ['workflow_dispatch', 'master', sha, repository, true],
]) test('Actual workflow guards: ' + [event, ref, release[0], repo].join('/'), () => {
    assert.equal(conditions.length, 2);
    const github = { event_name: event, ref: 'refs/heads/' + ref, sha, repository: repo, run_attempt: 1 };
    const inputs = { release_sha: release };
    for (const condition of conditions) {
        assert.equal(Function('github', 'inputs', 'return (' + condition + ')')(github, inputs), expected);
    }
    assert.equal(allowedEvent(event, github.ref, sha, release, repo), expected);
});
test('Reruns cannot reuse a previous run approval or deploy artifact', () => {
    const github = { event_name: 'workflow_dispatch', ref: 'refs/heads/master', sha,
        repository, run_attempt: 2 };
    for (const condition of conditions) {
        assert.equal(Function('github', 'inputs', 'return (' + condition + ')')(github, { release_sha: sha }), false);
    }
});
test('Validation dependencies, permissions and serial database suite order', () => {
    const deploy = workflow.indexOf('\n  deploy:');
    assert.ok(!workflow.slice(0, deploy).includes('id-token: write'));
    assert.equal((workflow.match(/id-token: write/g) ?? []).length, 1);
    assert.ok(workflow.includes('needs: [validate, runtime]'));
    assert.ok(workflow.includes('needs: validate'));
    assert.ok(workflow.indexOf('npm run build') < workflow.indexOf('php artisan test'));
    assert.ok(workflow.indexOf('Complete SQLite') < workflow.indexOf('Complete MySQL'));
    assert.ok(workflow.includes('cancel-in-progress: false'));
    assert.ok(workflow.includes('environment: prototype-existing-site'));
    assert.ok(!/migrate:fresh|queue:retry|workflow_call|pull_request_target/.test(workflow));
    assert.equal((workflow.match(/node scripts\/deployment\/gate.mjs remote/g) ?? []).length, 2);
    assert.ok(workflow.includes("clean: 'false'"));
});
test('Complete exact-release evidence accepted structurally', () => {
    assert.equal(validateEvidence(evidence(), sha, digest, now).release_sha, sha);
});
for (const key of keys) test('Missing evidence fails closed: ' + key, () => {
    const data = evidence(); delete data.evidence[key];
    assert.throws(() => validateEvidence(data, sha, digest, now));
});
test('Wrong SHA, schema digest, expired and future evidence rejected', () => {
    for (const patch of [{ release_sha: 'd'.repeat(40) }, { migration_digest: 'e'.repeat(64) },
        { deployed_sha: 'unknown' }, { completed_at: '2026-11-11T00:00:00Z' },
        { completed_at: '2026-11-13T00:00:00Z' }]) {
        assert.throws(() => validateEvidence({ ...evidence(), ...patch }, sha, digest, now));
    }
});
test('Environment declaration alone is not an approval gate', () => {
    const env = { name: environment, id: 42, protection_rules: [{ type: 'required_reviewers',
        prevent_self_review: true, reviewers: [{ type: 'User', reviewer: { login: 'owner' } }] }],
    deployment_branch_policy: { custom_branch_policies: true } };
    validateEnvironment(env);
    assert.throws(() => validateEnvironment({ name: environment }));
    assert.throws(() => validateEnvironment({ ...env, protection_rules: [] }));
    assert.throws(() => validateEnvironment({ ...env, protection_rules: [{
        type: 'required_reviewers', prevent_self_review: false, reviewers: [{}] }] }));
});
test('Stale queued release, tag policies and unapproved/bypassed runs fail closed', async () => {
    const metadata = {
        'git/ref/heads/master': { object: { sha } },
        ['environments/' + environment]: { name: environment, id: 42,
            protection_rules: [{ type: 'required_reviewers', prevent_self_review: true, reviewers: [{}] }],
            deployment_branch_policy: { custom_branch_policies: true } },
        ['environments/' + environment + '/deployment-branch-policies?per_page=100']: {
            total_count: 1, branch_policies: [{ name: 'master', type: 'branch' }] },
        'actions/runs/123/approvals': [{ state: 'approved', user: { login: 'reviewer' },
            environments: [{ id: 42, name: environment }] }],
    };
    const request = async route => metadata[route];
    await validateRemote(request, sha, '123', 'dispatcher');
    await assert.rejects(validateRemote(async () => { throw Error('Synthetic GitHub API unavailable'); },
        sha, '123', 'dispatcher'));
    await assert.rejects(validateRemote(request, 'f'.repeat(40), '123', 'dispatcher'));
    await assert.rejects(validateRemote(request, sha, '123', 'reviewer'));
    metadata['actions/runs/123/approvals'] = [];
    await assert.rejects(validateRemote(request, sha, '123', 'dispatcher'));
    metadata['environments/' + environment + '/deployment-branch-policies?per_page=100']
        .branch_policies[0].type = 'tag';
    await assert.rejects(validateRemote(request, sha, '123', 'dispatcher'));
});
function fixture(t) {
    const base = mkdtempSync(path.join(tmpdir(), 'pairfect-artifact-'));
    t.after(() => rmSync(base, { recursive: true, force: true }));
    const source = path.join(base, 'source'); mkdirSync(source);
    const put = (file, text = 'synthetic') => {
        mkdirSync(path.dirname(path.join(source, file)), { recursive: true });
        writeFileSync(path.join(source, file), text);
    };
    for (const file of ['artisan', 'bootstrap/app.php', 'bootstrap/providers.php', 'app/Test.php',
        'config/test.php', 'database/migrations/001.php', 'public/index.php', 'public/.htaccess',
        'resources/views/test.blade.php', 'routes/web.php', 'azure/uploads.ini']) put(file);
    put('composer.json', '{}'); put('composer.lock', JSON.stringify({ 'packages-dev': [{ name: 'test/dev' }] }));
    put('vendor/composer/installed.json', JSON.stringify({ dev: false, packages: [{ name: 'test/runtime' }] }));
    put('vendor/autoload.php'); put('vendor/test/runtime/index.php');
    put('public/build/manifest.json', JSON.stringify({ app: { isEntry: true, file: 'assets/app.js' } }));
    put('public/build/assets/app.js', 'synthetic-built-asset');
    for (const file of ['.env', '.env.example', 'auth.json', 'output/report.md', 'tests/test.php',
        'database/test.sqlite', 'database/backup.sql', 'public/storage/photo.jpg', 'public/uploads/private.jpg',
        'storage/logs/laravel.log', 'storage/framework/sessions/test', 'bootstrap/cache/config.php',
        'public/hot', 'public/build/assets/app.js.map']) put(file, 'SYNTHETIC-EXCLUDED-CANARY');
    execFileSync('git', ['init', '-q'], { cwd: source });
    execFileSync('git', ['add', '.'], { cwd: source });
    return { base, source, put, dest: path.join(base, 'runtime') };
}
test('Artifact allowlist, manifest, empty runtime directories and tar roundtrip', t => {
    const f = fixture(t);
    const manifest = stageRuntime(f.source, f.dest, sha);
    assert.equal(manifest.migration_digest, migrationDigest(f.source));
    for (const file of Object.keys(manifest.files)) {
        assert.ok(!readFileSync(path.join(f.dest, file), 'utf8').includes('EXCLUDED-CANARY'), file);
    }
    assert.ok(manifest.files['public/.htaccess']);
    const archive = path.join(f.base, 'runtime.tar.gz');
    execFileSync('tar', ['-czf', archive, '-C', f.dest, '.']);
    unpack(archive, path.join(f.base, 'unpacked'), sha256(readFileSync(archive)), sha);
    assert.throws(() => unpack(archive, path.join(f.base, 'wrong'), digest, sha));
    writeFileSync(path.join(f.dest, '.env'), 'SYNTHETIC-EXTRA');
    assert.throws(() => verifyRuntime(f.dest, sha));
    rmSync(path.join(f.dest, '.env'));
    writeFileSync(path.join(f.dest, 'public/index.php'), 'changed');
    assert.throws(() => verifyRuntime(f.dest, sha));
});
test('Runtime refuses dev dependencies, missing assets and private key material', t => {
    const f = fixture(t);
    f.put('vendor/composer/installed.json', JSON.stringify({ dev: true, packages: [] }));
    assert.throws(() => stageRuntime(f.source, f.dest, sha));
    f.put('vendor/composer/installed.json', JSON.stringify({ dev: false, packages: [] }));
    f.put('app/Test.php', '-----BEGIN PRIVATE KEY-----\n' + 'A'.repeat(100) + '\n-----END PRIVATE KEY-----');
    assert.throws(() => stageRuntime(f.source, f.dest, sha));
});
test('Missing build assets and injected extra files are rejected', t => {
    const f = fixture(t);
    rmSync(path.join(f.source, 'public/build/manifest.json'));
    assert.throws(() => stageRuntime(f.source, f.dest, sha));
});
test('Path traversal and credentials are never allowlisted', () => {
    for (const file of ['app/../.env', '/app/test.php', 'public/../../.env', 'vendor/auth.json',
        'config/service-account.json', 'public/test.key', 'app\\test.php', 'public/.env', 'C:/app/test.php']) {
        assert.equal(allowed(file), false, file);
    }
});
test('Bounded smoke uses synthetic HTTP responses, detects stale assets and refuses arbitrary sites', async t => {
    const f = fixture(t); stageRuntime(f.source, f.dest, sha);
    let stale = false;
    const server = createServer((req, res) => {
        if (req.url === '/' || req.url === '/login' || req.url === '/privacy-notice') res.end('PAIRfect Paws synthetic');
        else if (req.url === '/build/assets/app.js') res.end(stale ? 'old-asset' : 'synthetic-built-asset');
        else if (req.url === '/admin/handover') { res.writeHead(302, { location: '/login' }); res.end(); }
        else { res.writeHead(404); res.end(); }
    });
    await new Promise(resolve => server.listen(0, '127.0.0.1', resolve));
    t.after(() => { server.closeAllConnections(); server.close(); });
    const request = (url, options) => fetch('http://127.0.0.1:' + server.address().port + url.pathname, options);
    await smoke('https://www.pairfectpaws.app', f.dest, request);
    stale = true;
    await assert.rejects(smoke('https://www.pairfectpaws.app', f.dest, request));
    await assert.rejects(smoke('https://arbitrary.invalid', f.dest, request));
});
