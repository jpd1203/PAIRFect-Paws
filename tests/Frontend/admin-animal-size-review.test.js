import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import vm from 'node:vm';

const script = readFileSync(new URL('../../public/js/admin/animal.js', import.meta.url), 'utf8');
const modal = readFileSync(new URL('../../resources/views/admin/animal/_view-modal.blade.php', import.meta.url), 'utf8');
const sizeField = readFileSync(new URL('../../resources/views/partials/pet-calculated-size.blade.php', import.meta.url), 'utf8');

function openPet(pet) {
    const elements = new Map();
    function element(id) {
        if (!elements.has(id)) {
            const classes = new Set(['hidden']);
            elements.set(id, {
                value: '', checked: false, disabled: false, textContent: '', style: {},
                classList: { add: name => classes.add(name), remove: name => classes.delete(name),
                    toggle: (name, shouldAdd) => (shouldAdd ? classes.add(name) : classes.delete(name)),
                    contains: name => classes.has(name) },
                querySelector: () => null, dispatchEvent: () => {}, scrollTo: () => {},
            });
        }
        return elements.get(id);
    }
    const document = {
        getElementById: id => id === 'animalData' ? { textContent: JSON.stringify([pet]) } : element(id),
        addEventListener: () => {},
    };
    const context = { document, window: {}, Event: class { constructor(type) { this.type = type; } }, openModal: () => {} };
    vm.runInNewContext(script, context);
    context.window.openViewAnimalModal(pet.id);
    return element;
}

test('legacy pet gets short size status and one-time review control', () => {
    const e = openPet({ id: 1, name: 'Maple', species: 'Cat', weight_kg: '3.000',
        size_classification_status: 'legacy_unclassified', has_classified_size: false,
        size_display: 'This pet has a very long historical classification message' });
    assert.equal(e('vSize').value, 'Needs size review');
    assert.equal(e('vSizeReviewContainer').classList.contains('hidden'), false);
    assert.equal(e('vSizeReview').disabled, false);
    assert.equal(e('vSizeReview').checked, false);
});

test('trusted pet shows its calculated size and cannot submit a stale review', () => {
    const e = openPet({ id: 2, name: 'Milo', species: 'Dog', weight_kg: '12.000',
        physical_size: 'Medium', size_classification_status: 'classified', has_classified_size: true });
    assert.equal(e('vSize').value, 'Medium');
    assert.equal(e('vSizeReviewContainer').classList.contains('hidden'), true);
    assert.equal(e('vSizeReview').disabled, true);
});

test('review input is explicit and size remains unsubmitted/read-only', () => {
    assert.match(modal, /name="review_current_size" value="1" disabled/);
    assert.match(sizeField, /readonly/);
    assert.doesNotMatch(sizeField, /name="physical_size"/);
    assert.match(sizeField, /Needs size review/);
});
