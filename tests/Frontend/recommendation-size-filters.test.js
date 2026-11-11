import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import vm from 'node:vm';

const source = readFileSync(new URL('../../public/js/recommendation-size-filters.js', import.meta.url), 'utf8');
// Arbitrary serialized labels prove that JavaScript displays policy data, not its own thresholds.
const policy = {
    Dog: [{ value: 'Small', label: 'Small (dog range from server)' }, { value: 'Medium', label: 'Medium (dog range from server)' }],
    Cat: [{ value: 'Small', label: 'Small (cat range from server)' }, { value: 'Medium', label: 'Medium (cat range from server)' }],
};

function fixture(search = '', readyState = 'complete') {
    const events = {};
    const documentEvents = {};
    const species = { value: '', addEventListener(name, fn) { events['species:' + name] = fn; } };
    const size = { value: 'Medium', disabled: false, options: [], replaceChildren(...items) { this.options = items; } };
    const help = { textContent: '' };
    const fields = { recommendationFilters: {}, recommendationSpecies: species, recommendationSize: size,
        recommendationSizeHelp: help, recommendationSizeOptions: { textContent: JSON.stringify(policy) } };
    const window = { location: { href: 'http://localhost/recommendation/results' + search },
        addEventListener(name, fn) { events[name] = fn; } };
    const document = { readyState, getElementById: id => fields[id] ?? null, createElement: () => ({}),
        addEventListener(name, fn) { documentEvents[name] = fn; } };
    vm.runInNewContext(source, { window, document, URL });
    return { window, documentEvents, fields, species, size, help, events };
}

test('All Species only offers All Sizes and disables the size control', () => {
    const { size, help } = fixture();
    assert.equal(size.disabled, true);
    assert.equal(size.value, '');
    assert.equal(size.options.length, 1);
    assert.equal(size.options[0].textContent, 'All Sizes');
    assert.match(help.textContent, /Select Dog or Cat/);
});

test('restored query parameters select raw category values using species-specific server labels', () => {
    for (const selected of ['Dog', 'Cat']) {
        const { species, size } = fixture('?species=' + selected + '&size=Medium');
        assert.equal(species.value, selected);
        assert.equal(size.disabled, false);
        assert.equal(size.value, 'Medium');
        assert.equal(size.options[2].textContent, policy[selected][1].label);
        assert.equal(size.options[2].value, 'Medium');
    }
});

test('native change events reset even a category valid for both species', () => {
    const { species, size, help, events } = fixture('?species=Dog&size=Medium');
    species.value = 'Cat';
    events['species:change']();
    assert.equal(size.value, '');
    assert.equal(size.options[2].textContent, policy.Cat[1].label);
    assert.match(help.textContent, /reset to All Sizes/);
    species.value = '';
    events['species:change']();
    assert.equal(size.disabled, true);
    assert.equal(size.options.length, 1);
});

test('pageshow overrides unsent restored edits with canonical GET state', () => {
    const { species, size, events } = fixture('?species=Dog&size=Small');
    species.value = 'Cat';
    size.value = 'Medium';
    events.pageshow({ persisted: true });
    assert.equal(species.value, 'Dog');
    assert.equal(size.value, 'Small');
    assert.equal(size.options[1].textContent, policy.Dog[0].label);
});

test('back/forward popstate rebuilds ranges for the restored species', () => {
    const { window, species, size, events } = fixture('?species=Dog&size=Small');
    window.location.href = 'http://localhost/recommendation/results?species=Cat&size=Medium&page=2';
    events.popstate();
    assert.equal(species.value, 'Cat');
    assert.equal(size.value, 'Medium');
    assert.equal(size.options[2].textContent, policy.Cat[1].label);
    window.location.href = 'http://localhost/recommendation/results';
    events.popstate();
    assert.equal(size.disabled, true);
    assert.equal(size.value, '');
});

test('tampered restored state cannot select an unavailable species/category', () => {
    const all = fixture('?species=__proto__&size=Medium');
    assert.equal(all.species.value, '');
    assert.equal(all.size.disabled, true);
    const dog = fixture('?species=Dog&size=Giant');
    assert.equal(dog.size.value, '');
});

test('initialization waits for DOM readiness and is harmless on unrelated pages', () => {
    const { size, documentEvents } = fixture('?species=Cat', 'loading');
    assert.equal(size.options.length, 0);
    documentEvents.DOMContentLoaded();
    assert.equal(size.options[1].textContent, policy.Cat[0].label);
    assert.doesNotThrow(() => vm.runInNewContext(source, { document: { readyState: 'complete', getElementById: () => null } }));
});
