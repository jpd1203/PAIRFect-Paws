import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import vm from 'node:vm';

const source = readFileSync(new URL('../../public/js/admin/animal.js', import.meta.url), 'utf8');

function fixture() {
    const animals = [
        { id: 1, species: 'Dog', physical_size: 'Medium', size_display: 'Medium', weight_kg: '12.500', height_cm: '38.125' },
        { id: 2, species: 'Cat', physical_size: 'Extra Large', size_display: 'Unavailable: classification needs staff review', weight_kg: null, height_cm: null },
        { id: 3, species: 'Dog', physical_size: 'Large', weight_kg: '12.500', height_cm: null },
    ];
    const fields = Object.fromEntries(['vSpecies', 'vSize', 'vWeightKg', 'vHeightCm'].map(id => [id, { value: '' }]));
    const context = {
        window: {},
        document: {
            getElementById(id) {
                return id === 'animalData' ? { textContent: JSON.stringify(animals) } : fields[id] ?? null;
            },
            addEventListener() {},
        },
        openModal() {},
    };
    vm.runInNewContext(source, context);
    return { fields, open: context.window.openViewAnimalModal };
}

test('animal editor loads precise canonical measurements without recalculating size', () => {
    const { fields, open } = fixture();
    open(1);
    assert.equal(fields.vWeightKg.value, '12.500');
    assert.equal(fields.vHeightCm.value, '38.125');
    assert.equal(fields.vSize.value, 'Medium');
    assert.equal(fields.vSpecies.value, 'Dog');
});

test('switching to a historical pet clears prior measurements instead of fabricating values', () => {
    const { fields, open } = fixture();
    open(1);
    open(2);
    assert.equal(fields.vWeightKg.value, '');
    assert.equal(fields.vHeightCm.value, '');
    assert.equal(fields.vSize.value, 'Unavailable: classification needs staff review');
    assert.equal(fields.vSpecies.value, 'Cat');
});

test('raw historical size is never shown as a current calculated result', () => {
    const { fields, open } = fixture();
    open(3);
    assert.equal(fields.vSize.value, 'Unavailable: classification needs staff review');
});

test('calculated size has no submitted name or editable selector', () => {
    const template = readFileSync(new URL('../../resources/views/partials/pet-calculated-size.blade.php', import.meta.url), 'utf8');
    assert.match(template, /readonly/);
    assert.match(template, /aria-describedby=/);
    assert.doesNotMatch(template, /<select|name="physical_size"/);
});
