import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';
import { runInNewContext } from 'node:vm';

const source = readFileSync(new URL('../../resources/js/admin.js', import.meta.url), 'utf8')
    .replace("import '../css/admin.css';", '');

function element(tagName) {
    const classes = new Set();
    return {
        tagName,
        children: [],
        textContent: '',
        set className(value) {
            classes.clear();
            value.split(/\s+/).forEach((name) => classes.add(name));
        },
        classList: {
            add: (...names) => names.forEach((name) => classes.add(name)),
            remove: (...names) => names.forEach((name) => classes.delete(name)),
            contains: (name) => classes.has(name),
        },
        set innerHTML(_) {
            assert.fail('Toast content must not be interpreted as HTML.');
        },
        appendChild(child) { this.children.push(child); },
        replaceChildren(...children) { this.children = children; },
        querySelector(selector) {
            return this.children.find((child) => child.classList.contains(selector.slice(1)));
        },
    };
}

function setup(hasHost = true) {
    const host = element('div');
    const timers = new Map();
    let timerId = 0;
    const window = {};
    runInNewContext(source, {
        window,
        document: {
            getElementById: () => hasHost ? host : null,
            createElement: element,
            addEventListener() {},
        },
        setTimeout(callback, delay) {
            timers.set(++timerId, { callback, delay });
            return timerId;
        },
        clearTimeout: (id) => timers.delete(id),
    });
    return { host, timers, showToast: window.showToast };
}

test('admin toast renders untrusted message content as plain text', () => {
    const { host, showToast } = setup();
    const message = 'Pet <img src=x onerror=alert(1)> & friends updated.';
    showToast(message);
    const toast = host.children[0];
    assert.deepEqual(toast.children.map((child) => child.tagName), ['i', 'span']);
    assert.equal(toast.children[1].textContent, message);
    assert.ok(toast.children[0].classList.contains('fa-circle-check'));
    assert.ok(toast.classList.contains('bg-status-success-text'));
    assert.ok(toast.classList.contains('show'));
});

test('admin toast reuses its container and preserves error styling and expiry', () => {
    const { host, timers, showToast } = setup();
    showToast('Saved');
    const toast = host.children[0];
    showToast('Could not save', 'error');
    assert.equal(host.children.length, 1);
    assert.equal(host.children[0], toast);
    assert.equal(toast.children[1].textContent, 'Could not save');
    assert.ok(toast.children[0].classList.contains('fa-circle-exclamation'));
    assert.ok(toast.classList.contains('bg-status-danger-text'));
    assert.equal(toast.classList.contains('bg-status-success-text'), false);
    assert.equal(timers.size, 1);
    const timer = [...timers.values()][0];
    assert.equal(timer.delay, 3500);
    timer.callback();
    assert.equal(toast.classList.contains('show'), false);
});

test('empty messages and missing toast host remain harmless', () => {
    const { host, timers, showToast } = setup();
    showToast('');
    assert.equal(host.children.length, 0);
    assert.equal(timers.size, 0);
    assert.doesNotThrow(() => setup(false).showToast('Saved'));
});
