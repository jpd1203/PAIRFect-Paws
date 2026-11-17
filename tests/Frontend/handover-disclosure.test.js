import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import vm from 'node:vm';

const source = readFileSync(new URL('../../public/js/admin/handover-disclosure.js', import.meta.url), 'utf8');

function fixture({ hash = '#target', failed = false, absent = false, existingError = false } = {}) {
    const events = {};
    const listeners = {};
    const nodes = new Map();
    const attributes = {};
    const outer = { tagName: 'DETAILS', open: false };
    const inner = { tagName: 'DETAILS', open: false, parentElement: outer };
    const target = { parentElement: inner, hasAttribute: () => false, setAttribute: () => {}, focus: () => { target.focused = true; } };
    const input = { name: 'notes', type: 'textarea', parentElement: inner,
        setAttribute: (key, value) => { attributes[key] = value; }, getAttribute: key => attributes[key] || '',
        after: node => { input.message = node; } };
    const form = { id: 'handoverForm', tagName: 'FORM', parentElement: inner,
        addEventListener: (type, callback) => { listeners[type] = callback; },
        querySelector: () => null, append: node => { form.marker = node; }, querySelectorAll: () => [input] };
    const summary = { focus: () => { summary.focused = true; } };
    const page = { contains: node => !!node, dataset: { failedForm: failed ? form.id : '', validationErrors: JSON.stringify({ notes: ['Synthetic <script>neverExecute()</script>'] }) },
        querySelectorAll: selector => selector === 'form' ? [form] : [],
        querySelector: () => failed ? summary : null,
        addEventListener: (type, callback) => { events[type] = callback; } };
    nodes.set('target', target); nodes.set(form.id, form);
    const renderedError = { id: 'notesError', textContent: 'Server-rendered error' };
    if (existingError) {
        attributes['aria-describedby'] = 'notesError';
        nodes.set('notesError', renderedError);
    }
    const document = { readyState: 'complete', querySelector: () => absent ? null : page,
        getElementById: id => nodes.get(id), createElement: () => ({}) };
    const window = { location: { hash }, addEventListener: (type, callback) => { listeners[type] = callback; } };
    vm.runInNewContext(source, { document, window });
    return { events, listeners, outer, inner, target, form, input, attributes, summary, renderedError };
}

test('deep-link opens nested details and focuses its destination', () => {
    const f = fixture();
    assert.equal(f.outer.open, true); assert.equal(f.inner.open, true); assert.equal(f.target.focused, true);
});

test('back/forward and restored-page events reveal their hash destination', () => {
    const f = fixture({ hash: '' });
    f.inner.open = false;
    f.listeners.hashchange();
    assert.equal(f.inner.open, false);
    assert.equal(typeof f.listeners.pageshow, 'function');
});

test('next-action anchor reveals details before native navigation', () => {
    const f = fixture({ hash: '' });
    f.events.click({ target: { closest: () => ({ getAttribute: () => '#target' }) } });
    assert.equal(f.outer.open, true); assert.equal(f.inner.open, true);
});

test('native invalid event exposes the required input without suppressing validation', () => {
    const f = fixture({ hash: '' });
    f.events.invalid({ target: f.input });
    assert.equal(f.inner.open, true);
});

test('submit marker identifies only the form and does not alter backend field names', () => {
    const f = fixture({ hash: '' });
    f.listeners.submit();
    assert.equal(f.form.marker.name, '_ui_form'); assert.equal(f.form.marker.value, 'handoverForm'); assert.equal(f.form.marker.type, 'hidden');
    assert.equal(f.input.name, 'notes');
});

test('failed form opens with associated errors bound only as plain text', () => {
    const f = fixture({ hash: '', failed: true });
    assert.equal(f.inner.open, true); assert.equal(f.summary.focused, true);
    assert.equal(f.attributes['aria-invalid'], 'true');
    assert.match(f.attributes['aria-describedby'], /handoverForm-error-notes/);
    assert.equal(f.input.message.textContent, 'Synthetic <script>neverExecute()</script>');
    assert.equal(f.input.message.innerHTML, undefined);
});

test('malformed hashes and unrelated pages are harmless', () => {
    assert.doesNotThrow(() => fixture({ hash: '#%zz' }));
    assert.doesNotThrow(() => fixture({ absent: true }));
});

test('server-rendered field error is reused rather than duplicated', () => {
    const f = fixture({ hash: '', failed: true, existingError: true });
    assert.equal(f.input.message, undefined);
    assert.equal(f.renderedError.textContent, 'Synthetic <script>neverExecute()</script>');
    assert.equal(f.attributes['aria-describedby'], 'notesError');
});
