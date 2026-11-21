import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import vm from 'node:vm';

const applicationSource = readFileSync(new URL('../../public/js/admin/application.js', import.meta.url), 'utf8');
const genericSource = readFileSync(new URL('../../resources/js/admin.js', import.meta.url), 'utf8');
const start = applicationSource.indexOf('function initInteractiveApplicationFilters()');
const controller = applicationSource.slice(start, applicationSource.indexOf('/** Overflow scroll arrow controls for filter bar */', start));
const genericStart = genericSource.indexOf('function applyRowFilters(scope)');
const genericController = genericSource.slice(genericStart, genericSource.indexOf('function initSearch()', genericStart));

function fixture() {
    const rows = [
        { dataset: { date: '2026-11-20', statusRaw: 'Pending', status: 'pending', searchText: 'Synthetic Alpha dog' }, style: {} },
        { dataset: { date: '2026-11-21', statusRaw: 'InterviewScheduled', status: 'scheduled', searchText: 'Synthetic Beta cat' }, style: {} },
        { dataset: { date: '2026-11-22', statusRaw: 'InterviewScheduled', status: 'scheduled', searchText: 'Synthetic Gamma dog' }, style: {} },
    ];
    const nodes = new Map();
    for (const id of ['applicationFilterForm', 'appSearchInput', 'appFromDate', 'appToDate', 'appStatusSelect', 'clearFiltersBtn', 'applyFiltersBtn', 'visibleAppCount', 'noFilterResultsRow']) {
        nodes.set(id, { value: '', textContent: '', style: {}, listeners: {}, addEventListener(type, callback) { this.listeners[type] = callback; } });
    }
    nodes.set('applicationTableBody', { querySelectorAll: () => rows });
    const history = [];
    const window = { location: { pathname: '/admin/applications', search: '' }, history: { replaceState: (...args) => history.push(args[2]) } };
    const document = { getElementById: id => nodes.get(id), querySelector: () => null };
    vm.runInNewContext(`function initFilterBarScrollArrows() {}\n${controller}\ninitInteractiveApplicationFilters();`, { document, window, URLSearchParams });
    return { rows, nodes, window, history };
}

test('Apply combines date, status and search filters and updates the URL', () => {
    const f = fixture();
    f.nodes.get('appFromDate').value = '2026-11-21';
    f.nodes.get('appToDate').value = '2026-11-21';
    f.nodes.get('appStatusSelect').value = 'InterviewScheduled';
    f.nodes.get('appSearchInput').value = 'Beta';
    let prevented = false;
    f.nodes.get('applyFiltersBtn').listeners.click({ preventDefault: () => { prevented = true; } });
    assert.equal(prevented, true);
    assert.deepEqual(f.rows.map(row => row.style.display), ['none', '', 'none']);
    assert.equal(f.nodes.get('visibleAppCount').textContent, 1);
    const params = new URL(f.history.at(-1), 'https://example.test').searchParams;
    assert.equal(params.get('from'), '2026-11-21'); assert.equal(params.get('to'), '2026-11-21');
    assert.equal(params.get('status'), 'InterviewScheduled'); assert.equal(params.get('search'), 'Beta');
});

test('Export still submits naturally to its server endpoint', () => {
    const f = fixture();
    let prevented = false;
    f.nodes.get('applicationFilterForm').listeners.submit({
        submitter: { getAttribute: () => '/admin/applications/export' }, preventDefault: () => { prevented = true; },
    });
    assert.equal(prevented, false); assert.equal(f.history.length, 0);
});

test('keyboard form submission retains filtering and Clear restores every row', () => {
    const f = fixture();
    f.nodes.get('appSearchInput').value = 'not present';
    f.nodes.get('applicationFilterForm').listeners.submit({ preventDefault() {}, submitter: null });
    assert.equal(f.nodes.get('visibleAppCount').textContent, 0);
    assert.equal(f.nodes.get('noFilterResultsRow').style.display, '');
    f.window.location.search = '?search=not+present';
    f.nodes.get('clearFiltersBtn').listeners.click();
    assert.equal(f.nodes.get('visibleAppCount').textContent, 3);
    assert.deepEqual(f.rows.map(row => row.style.display), ['', '', '']);
    assert.equal(f.history.at(-1), '/admin/applications');
});

test('generic row filters cannot overwrite application-specific date filtering', () => {
    const scope = { id: 'applicationTableBody', querySelectorAll() { throw new Error('Generic filter touched application rows.'); } };
    const context = { document: { querySelectorAll() { throw new Error('Generic filter was not bypassed.'); } }, scope };
    vm.runInNewContext(`${genericController}\napplyRowFilters(scope);`, context);
});

test('generic filtering still works for non-application tables', () => {
    const rows = [{ dataset: { searchText: 'Alpha', status: 'active' }, style: {} }, { dataset: { searchText: 'Beta', status: 'active' }, style: {} }];
    const scope = { id: 'otherTable', querySelectorAll: () => rows, querySelector: () => null };
    const input = { dataset: { searchScope: 'otherTable' }, value: 'alpha' };
    const context = { document: { querySelectorAll: selector => selector === '[data-search-input]' ? [input] : [] }, scope };
    vm.runInNewContext(`${genericController}\napplyRowFilters(scope);`, context);
    assert.deepEqual(rows.map(row => row.style.display), ['', 'none']);
});

test('mobile recommendation changes retain current-weight language and canonical filter names', () => {
    const view = readFileSync(new URL('../../resources/views/recommendation/results.blade.php', import.meta.url), 'utf8');
    assert.match(view, /Current measured-weight size/);
    assert.match(view, /not predicted adult size/);
    assert.doesNotMatch(view, /Adult weight-based size|adult weight ranges/);
    assert.match(view, /id="recommendationSpecies" name="species"/);
    assert.match(view, /id="recommendationSize" name="size" class="w-full/);
    assert.match(view, /flex-col sm:flex-row/);
});

test('identity button uses an icon provided by the installed Font Awesome Free set', () => {
    const view = readFileSync(new URL('../../resources/views/admin/application/_identity-form.blade.php', import.meta.url), 'utf8');
    assert.match(view, /fa-solid fa-shield-halved/);
    assert.doesNotMatch(view, /fa-shield-check/);
});
