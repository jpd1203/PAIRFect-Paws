import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { pathToFileURL } from 'node:url';
import { approvedSite, sha256 } from './gate.mjs';

export async function check(response, expected, text, hash) {
    assert.equal(response.status, expected, 'Unexpected HTTP status');
    const chunks = [];
    let length = 0;
    for await (const chunk of response.body) {
        length += chunk.length;
        assert.ok(length <= 2_000_000, 'Unexpected response size');
        chunks.push(chunk);
    }
    const body = Buffer.concat(chunks);
    if (text) assert.ok(body.toString().includes(text), 'Missing expected page content');
    if (hash) assert.equal(sha256(body), hash, 'Deployed asset differs from approved artifact');
}
export async function smoke(base, root, request = fetch) {
    const url = approvedSite(base);
    const manifest = JSON.parse(readFileSync(root + '/RELEASE.json'));
    const assets = JSON.parse(readFileSync(root + '/public/build/manifest.json'));
    const asset = Object.values(assets).find(v => v.isEntry && v.file)?.file;
    assert.ok(asset && manifest.files['public/build/' + asset], 'No approved entry asset');
    const get = route => request(new URL(route, base), {
        redirect: 'manual', signal: AbortSignal.timeout(10_000), headers: { 'Cache-Control': 'no-cache' },
    });
    await check(await get('/'), 200, 'PAIRfect Paws');
    await check(await get('/login'), 200, 'PAIRfect Paws');
    await check(await get('/privacy-notice'), 200, 'PAIRfect Paws');
    await check(await get('/build/' + asset), 200, null, manifest.files['public/build/' + asset]);
    for (const route of ['/.env', '/storage/proofs/phase18a-nonexistent-canary']) {
        const response = await get(route);
        assert.ok([403, 404].includes(response.status), 'Protected path not denied: ' + route);
        await response.body?.cancel();
    }
    const response = await get('/admin/handover');
    assert.ok([302, 303, 401, 403].includes(response.status), 'Anonymous staff route was not blocked');
    if ([302, 303].includes(response.status)) {
        const target = new URL(response.headers.get('location'), base);
        assert.equal(target.origin, url.origin);
        assert.equal(target.pathname, '/login');
    }
    await response.body?.cancel();
    console.log('Bounded smoke passed: landing, login, privacy, exact asset and anonymous-route checks.');
}
if (process.argv[1] && import.meta.url === pathToFileURL(process.argv[1]).href) {
    smoke(process.env.SITE_URL, 'output/release/runtime').catch(error => {
        console.error(error.message); process.exitCode = 1;
    });
}
