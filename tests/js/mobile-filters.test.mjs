import test from 'node:test';
import assert from 'node:assert/strict';
import { mobileFilterPanel } from '../../resources/js/mobile-filters.js';

function harness() {
    const state = { search: 'Ana', status: '' }, calls = [], staged = {};
    const ui = mobileFilterPanel({ fields: ['search', 'status'], labels: { search: 'Nome', status: 'Situação' } });
    ui.$wire = { $get: key => state[key], $set: (key, value, live = true) => {
        calls.push([key, value, live]);
        if (live) state[key] = value; else staged[key] = value;
    }, $commit: async () => { calls.push('commit'); Object.assign(state, staged); } };
    ui.$el = { querySelector: () => null }; ui.$nextTick = async () => {};
    ui.filterRoot = ui.$el;
    ui.filterMobile = true; ui.syncDraft();
    return { ui, state, calls };
}

test('mobile edits and clear are drafts; cancellation keeps committed filters', async () => {
    const { ui, state, calls } = harness();
    ui.openFilters(); ui.draft.search = 'Bruno'; await ui.changeFilter('search');
    assert.equal(state.search, 'Ana'); assert.deepEqual(calls, []);
    ui.clearDraft(); assert.equal(ui.draft.search, ''); assert.equal(state.search, 'Ana');
    ui.cancelFilters(); assert.equal(ui.draft.search, 'Ana'); assert.equal(ui.filtersOpen, false);
});

test('combined filters are sent in one commit and duplicate application is blocked', async () => {
    const { ui, state, calls } = harness();
    ui.openFilters(); ui.draft.search = 'Bruno'; ui.draft.status = 'approved';
    await Promise.all([ui.applyFilters(), ui.applyFilters()]);
    assert.equal(calls.filter(call => call === 'commit').length, 1);
    assert.deepEqual(state, { search: 'Bruno', status: 'approved' });
    assert.equal(ui.filtersOpen, false); assert.equal(ui.applying, false);
});

test('validation and connection failures retain the open sheet and its input', async () => {
    const { ui } = harness(); let focused = false;
    ui.openFilters(); ui.draft.search = 'Revisar';
    ui.$el.querySelector = selector => selector.includes('aria-invalid') ? { focus: () => focused = true } : null;
    await ui.applyFilters(); assert(focused); assert(ui.filtersOpen);
    ui.$wire.$commit = async () => { throw new Error('offline'); };
    await ui.applyFilters(); assert(ui.filterError); assert(ui.filtersOpen); assert.equal(ui.draft.search, 'Revisar');
});

test('desktop preserves immediate filtering, IME composition and removable chips', async () => {
    const { ui, state, calls } = harness(); ui.filterMobile = false;
    ui.draft.search = 'Bruno'; await ui.changeFilter('search', { isComposing: true });
    assert.deepEqual(calls, []); await ui.changeFilter('search', { isComposing: false });
    assert.equal(state.search, 'Bruno'); assert.equal(ui.activeFilters()[0].label, 'Nome: Bruno');
    await ui.removeFilter('search'); assert.equal(state.search, ''); assert.equal(ui.activeFilters().length, 0);
});

test('empty Blade defaults cannot expose Array prototype methods as filter values', () => {
    const ui = mobileFilterPanel({ fields: ['filter'], defaults: [], labels: { filter: 'Situação' } });
    ui.clearDraft(); assert.deepEqual(ui.draft, { filter: '' });
    assert.equal(ui.defaultValue('filter'), '');
});
