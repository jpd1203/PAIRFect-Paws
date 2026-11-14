import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';
import { runInNewContext } from 'node:vm';

const template = readFileSync(new URL('../../resources/views/handover/confirm.blade.php', import.meta.url), 'utf8');
const button = template.match(/<button\b[^>]*\bvalue="not_received"[^>]*>/)?.[0];
const handler = button?.match(/\bonclick="([^"]*)"/)?.[1];
const message = 'Are you sure you want to report that your pet has not arrived? Staff will be alerted immediately.';

test('negative receipt confirmation uses only static code and the correct submit outcome', () => {
    assert.ok(button, 'Non-receipt submit button must exist');
    assert.match(button, /type="submit"/);
    assert.match(button, /name="outcome"/);
    assert.equal(handler, `return confirm('${message}');`);
    assert.doesNotMatch(handler, /\{\{|\{!!|\$record/);
});

for (const accepted of [true, false]) {
    test(`confirmation ${accepted ? 'accepted permits' : 'canceled prevents'} default form submission`, () => {
        const calls = [];
        const result = runInNewContext(`(function () { ${handler} })()`, {
            confirm: (text) => { calls.push(text); return accepted; },
        });
        assert.deepEqual(calls, [message]);
        assert.equal(result, accepted);
    });
}
