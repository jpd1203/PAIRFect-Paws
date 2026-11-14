import assert from 'node:assert/strict';
import { execFileSync } from 'node:child_process';
import { appendFileSync, chmodSync, copyFileSync, existsSync, lstatSync, mkdirSync,
    readFileSync, readdirSync, writeFileSync } from 'node:fs';
import path from 'node:path';
import { pathToFileURL } from 'node:url';
import { migrationDigest, sha256 } from './gate.mjs';

const roots = ['app', 'config', 'resources', 'routes', 'azure', 'vendor'];
const singles = ['artisan', 'composer.json', 'composer.lock', 'bootstrap/app.php', 'bootstrap/providers.php'];
export const writable = ['bootstrap/cache', 'storage/app/private', 'storage/app/public',
    'storage/framework/cache/data', 'storage/framework/sessions', 'storage/framework/views', 'storage/logs'];
function safeName(name) {
    return name !== '' && !name.includes('\\') && !name.includes(':') && !/[\x00-\x1f]/.test(name) && !name.startsWith('/')
        && name.split('/').every(p => p !== '..' && p !== '.' && p !== '');
}
export function allowed(name) {
    if (!safeName(name)) return false;
    const parts = name.toLowerCase().split('/');
    if (parts.some(p => p === '.git' || p === 'node_modules' || p === '.env' || p.startsWith('.env.')
        || p === 'auth.json' || p.includes('credential') || p.includes('service-account')
        || /\.(sqlite\d*|sql|dump|pem|p12|pfx|key|log)$/.test(p))) return false;
    if (name === 'RELEASE.json') return true;
    if (name.startsWith('vendor/')) {
        return !parts.some(p => ['tests', 'test', 'docs', '.github', '.gitlab', 'examples'].includes(p))
            && !/\.(md|rst)$/i.test(name);
    }
    if (name.startsWith('public/')) {
        return !/^public\/(storage|uploads|proofs)(\/|$)/.test(name)
            && name !== 'public/hot' && !name.endsWith('.map');
    }
    if (/^database\/(migrations|seeders|factories)\/.+\.php$/.test(name)) return true;
    return singles.includes(name) || roots.filter(r => r !== 'vendor').some(r => name.startsWith(r + '/'));
}
export function walk(root) {
    const files = [];
    function visit(relative) {
        for (const item of readdirSync(path.join(root, relative), { withFileTypes: true })) {
            const name = relative ? relative + '/' + item.name : item.name;
            assert.ok(!item.isSymbolicLink(), 'Symlink in runtime tree: ' + name);
            if (item.isDirectory()) visit(name);
            else { assert.ok(item.isFile(), 'Non-regular runtime entry'); files.push(name); }
        }
    }
    visit('');
    return files.sort();
}
export function verifyRuntime(root, release) {
    const manifest = JSON.parse(readFileSync(path.join(root, 'RELEASE.json')));
    assert.equal(manifest.release_sha, release);
    const files = walk(root).filter(n => n !== 'RELEASE.json');
    assert.deepEqual(files, Object.keys(manifest.files).sort(), 'Unlisted/missing runtime file');
    for (const file of files) {
        assert.ok(allowed(file), 'Forbidden runtime path: ' + file);
        assert.equal(sha256(readFileSync(path.join(root, file))), manifest.files[file], 'Runtime file changed: ' + file);
    }
    for (const dir of writable) assert.ok(existsSync(path.join(root, dir)), 'Missing writable directory');
    assert.equal(migrationDigest(root), manifest.migration_digest);
    assert.ok(files.includes('vendor/autoload.php') && files.includes('public/build/manifest.json'));
    return manifest;
}
export function stageRuntime(source, dest, release) {
    assert.match(release, /^[a-f0-9]{40}$/);
    assert.ok(!existsSync(dest), 'Use a fresh runtime destination');
    const installed = JSON.parse(readFileSync(path.join(source, 'vendor/composer/installed.json')));
    assert.equal(installed.dev, false, 'Runtime vendor must come from composer install --no-dev');
    const locked = JSON.parse(readFileSync(path.join(source, 'composer.lock')));
    const devNames = new Set(locked['packages-dev'].map(p => p.name));
    assert.ok(installed.packages.every(p => !devNames.has(p.name)), 'Development dependency present');
    const tracked = execFileSync('git', ['ls-files', '-z'], { cwd: source }).toString().split('\0').filter(Boolean);
    const candidates = new Set(tracked.filter(n => !n.startsWith('public/build/') && allowed(n)));
    for (const root of ['vendor', 'public/build']) for (const name of walk(path.join(source, root))) {
        const file = root + '/' + name;
        if (allowed(file)) candidates.add(file);
    }
    const files = {};
    for (const name of [...candidates].sort()) {
        assert.ok(lstatSync(path.join(source, name)).isFile(), 'Symlink or missing source: ' + name);
        const bytes = readFileSync(path.join(source, name));
        assert.ok(!/-----BEGIN (?:RSA |EC |OPENSSH )?PRIVATE KEY-----\r?\n[A-Za-z0-9+/=\r\n]{80,}-----END (?:RSA |EC |OPENSSH )?PRIVATE KEY-----/.test(bytes.toString())
            && !/"private_key"\s*:\s*"-----BEGIN/.test(bytes.toString()),
            'Private key material in runtime: ' + name);
        mkdirSync(path.dirname(path.join(dest, name)), { recursive: true });
        copyFileSync(path.join(source, name), path.join(dest, name));
        chmodSync(path.join(dest, name), lstatSync(path.join(source, name)).mode & 0o777);
        files[name] = sha256(bytes);
    }
    for (const dir of writable) mkdirSync(path.join(dest, dir), { recursive: true, mode: 0o775 });
    const manifest = { release_sha: release, migration_digest: migrationDigest(source), files };
    writeFileSync(path.join(dest, 'RELEASE.json'), JSON.stringify(manifest, null, 2) + '\n');
    verifyRuntime(dest, release);
    return manifest;
}
export function unpack(archive, dest, digest, release) {
    assert.match(digest ?? '', /^[a-f0-9]{64}$/);
    assert.equal(sha256(readFileSync(archive)), digest, 'Archive digest mismatch');
    assert.ok(!existsSync(dest), 'Extraction destination must be fresh');
    const names = execFileSync('tar', ['-tzf', archive]).toString().trim().split(/\r?\n/);
    assert.ok(names.every(n => n === './' || safeName(n.replace(/^\.\//, '').replace(/\/$/, ''))),
        'Unsafe archive path');
    // Archive integrity is bound to the trusted same-run build output before extraction.
    mkdirSync(dest, { recursive: true });
    execFileSync('tar', ['-xzf', archive, '-C', dest]);
    return verifyRuntime(dest, release);
}
export function main(mode) {
    const base = path.resolve('output/release');
    const runtime = path.join(base, 'runtime');
    const archive = path.join(base, 'runtime.tar.gz');
    const release = process.env.RELEASE_SHA;
    if (mode === 'pack') {
        assert.equal(execFileSync('git', ['rev-parse', 'HEAD']).toString().trim(), release,
            'Package only the exact checked-out release');
        assert.equal(execFileSync('git', ['status', '--porcelain', '--untracked-files=all']).toString().trim(), '',
            'Package only a clean checkout (ignored dependencies/build/output are permitted)');
        stageRuntime(process.cwd(), runtime, release);
        execFileSync('tar', ['-czf', archive, '-C', runtime, '.']);
        const digest = sha256(readFileSync(archive));
        writeFileSync(path.join(base, 'runtime.sha256'), digest + '\n');
        if (process.env.GITHUB_OUTPUT) appendFileSync(process.env.GITHUB_OUTPUT, 'digest=' + digest + '\n');
        if (process.env.GITHUB_STEP_SUMMARY) appendFileSync(process.env.GITHUB_STEP_SUMMARY,
            'Release: ' + release + '\n\nRuntime SHA-256: ' + digest
            + '\n\nIndependent reviewer must verify the schema-first evidence before approving this exact run.\n');
        console.log('Runtime verified; release=' + release + ' digest=' + digest);
    } else {
        assert.equal(mode, 'unpack');
        unpack(archive, runtime, process.env.EXPECTED_DIGEST, release);
        console.log('Runtime digest and every manifest entry verified.');
    }
}
if (process.argv[1] && import.meta.url === pathToFileURL(process.argv[1]).href) main(process.argv[2]);
