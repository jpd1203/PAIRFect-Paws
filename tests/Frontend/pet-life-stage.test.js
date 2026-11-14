import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import vm from 'node:vm';

const source = readFileSync(new URL('../../resources/js/pet-life-stage.js', import.meta.url), 'utf8');
function fixture() {
    const events = {}, formEvents = {}, windowEvents = {};
    const field = name => ({ value: '', addEventListener(event, callback) { events[name + ':' + event] = callback; } });
    const species = field('species'), years = field('years'), months = field('months');
    const form = { querySelector: selector => ({ '[name="species"]': species, '[name="age_years"]': years, '[name="age_months"]': months })[selector],
        addEventListener(event, callback) { formEvents[event] = callback; } };
    // Deliberately arbitrary server-provided thresholds prove the preview has no hidden policy table.
    const output = { value: '', dataset: { lifeStagePolicy: JSON.stringify({ Dog: { adult: 7, senior: 19 }, Cat: { adult: 5, senior: 17 } }) }, closest: () => form };
    const document = { readyState: 'complete', querySelectorAll: () => [output] };
    vm.runInNewContext(source, { document, window: { addEventListener(event, callback) { windowEvents[event] = callback; } }, queueMicrotask: callback => callback() });
    return { output, species, years, months, events, formEvents, windowEvents };
}

test('life-stage preview uses backend policy and updates on age and species events', () => {
    const f = fixture();
    assert.equal(f.output.value, 'Unknown');
    f.species.value = 'Dog'; f.months.value = '6'; f.events['months:input']();
    assert.equal(f.output.value, 'Young');
    f.months.value = '7'; f.events['months:change']();
    assert.equal(f.output.value, 'Adult');
    f.years.value = '1'; f.events['years:input']();
    assert.equal(f.output.value, 'Senior');
    f.years.value = ''; f.months.value = '5'; f.species.value = 'Cat'; f.events['species:change']();
    assert.equal(f.output.value, 'Adult');
});

test('unknown, invalid and cleared ages never default to Adult', () => {
    const f = fixture(); f.species.value = 'Dog';
    for (const value of ['-1', '1.5', 'NaN', '12', '2147483648']) {
        f.months.value = value; f.events['months:input'](); assert.equal(f.output.value, 'Unknown');
    }
    f.months.value = '0'; f.events['months:input'](); assert.equal(f.output.value, 'Young');
    f.months.value = ''; f.events['months:input'](); assert.equal(f.output.value, 'Unknown');
    f.months.value = '5'; f.species.value = 'Bird'; f.events['species:change'](); assert.equal(f.output.value, 'Unknown');
});

test('editor prefill, reset and restored-page events recalculate the correct record', () => {
    const f = fixture(); f.species.value = 'Dog'; f.years.value = '2';
    f.formEvents['pairfect:pet-fields-loaded'](); assert.equal(f.output.value, 'Senior');
    f.species.value = 'Cat'; f.years.value = ''; f.months.value = '1';
    f.formEvents['pairfect:pet-fields-loaded'](); assert.equal(f.output.value, 'Young');
    f.months.value = ''; f.formEvents.reset(); assert.equal(f.output.value, 'Unknown');
    f.months.value = '5'; f.windowEvents.pageshow(); assert.equal(f.output.value, 'Adult');
});

test('life stage is read-only, unsubmitted and absent-script safe', () => {
    const template = readFileSync(new URL('../../resources/views/partials/pet-life-stage.blade.php', import.meta.url), 'utf8');
    assert.match(template, /readonly/); assert.match(template, /aria-describedby/);
    assert.doesNotMatch(template, /name="life_stage"|<select/);
    assert.doesNotThrow(() => vm.runInNewContext(source, { document: { readyState: 'complete', querySelectorAll: () => [] } }));
});
