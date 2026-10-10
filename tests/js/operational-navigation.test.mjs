import assert from 'node:assert/strict';
import test from 'node:test';
import { initOperationalNavigation } from '../../resources/js/operational-navigation.js';

test('context focus tolerates ordinary lists, runs once per destination and returns focus on a new navigation', () => {
    let target = null;
    globalThis.document = { querySelector: () => target, querySelectorAll: () => [] };
    assert.doesNotThrow(initOperationalNavigation);
    let focus = 0;
    let scroll = 0;
    const element = () => ({ focus: (options) => { assert.deepEqual(options, { preventScroll: true }); focus++; }, scrollIntoView: (options) => { assert.equal(options.behavior, 'auto'); scroll++; } });
    target = element();
    initOperationalNavigation();
    initOperationalNavigation();
    assert.equal(focus, 1);
    assert.equal(scroll, 1);
    target = element();
    initOperationalNavigation();
    assert.equal(focus, 2);
    const attributes = {};
    const record = { ...element(), dataset: { contextRecord: 'shift-1' }, getClientRects: () => [{}], setAttribute: (key, value) => attributes[key] = value };
    const hidden = { ...record, getClientRects: () => [] };
    document.querySelectorAll = () => [hidden, record];
    initOperationalNavigation();
    initOperationalNavigation();
    assert.equal(focus, 3);
    assert.deepEqual(attributes, { tabindex: '-1', 'aria-labelledby': 'context-selected-record' });
    delete globalThis.document;
});
