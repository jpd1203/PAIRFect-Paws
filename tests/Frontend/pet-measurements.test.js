import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import vm from 'node:vm';

const source = readFileSync(new URL('../../public/js/admin/animal.js', import.meta.url), 'utf8');

function fixture() {
    const animals = [
        { id: 1, species: 'Dog', physical_size: 'Small', weight_kg: '12.500', height_cm: '38.125' },
        { id: 2, species: 'Cat', physical_size: 'Extra Large', weight_kg: null, height_cm: null },
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
    assert.equal(fields.vSize.value, 'Small');
    assert.equal(fields.vSpecies.value, 'Dog');
});

test('switching to a historical pet clears prior measurements instead of fabricating values', () => {
    const { fields, open } = fixture();
    open(1);
    open(2);
    assert.equal(fields.vWeightKg.value, '');
    assert.equal(fields.vHeightCm.value, '');
    assert.equal(fields.vSize.value, 'Extra Large');
    assert.equal(fields.vSpecies.value, 'Cat');
});
