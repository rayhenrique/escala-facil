import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';
import vm from 'node:vm';

const source = readFileSync(new URL('../../public/sw.js', import.meta.url), 'utf8');
const current = source.match(/const CACHE_VERSION = '([^']+)';/)[1];

test('activation removes previous release caches and preserves current and unrelated caches', async () => {
    const events = {};
    const deleted = [];
    let claimed = false;
    vm.runInNewContext(source, {
        self: {
            addEventListener: (name, fn) => { events[name] = fn; },
            clients: { claim: async () => { claimed = true; } },
        },
        caches: {
            keys: async () => [current, 'escala-facil-static-v0.0.1', 'another-app'],
            delete: async (key) => { deleted.push(key); },
        },
    });
    let finished;
    events.activate({ waitUntil: (promise) => { finished = promise; } });
    await finished;
    assert.deepEqual(deleted, ['escala-facil-static-v0.0.1']);
    assert.equal(claimed, true);
});

test('navigation always uses network and offline fallback without storing old HTML', async () => {
    const events = {};
    let cacheWrites = 0;
    let networkCalls = 0;
    let offline = false;
    const page = { content: 'installed HTML' };
    const fallback = { content: 'offline' };
    vm.runInNewContext(source, {
        URL,
        self: { location: { origin: 'https://example.test' }, addEventListener: (name, fn) => { events[name] = fn; } },
        fetch: async () => { networkCalls++; if (offline) throw new Error('offline'); return page; },
        caches: {
            match: async (key) => { assert.equal(key, '/offline.html'); return fallback; },
            open: async () => ({ put: () => { cacheWrites++; } }),
        },
    });
    const request = { method: 'GET', url: 'https://example.test/manager/dashboard', mode: 'navigate' };
    let response;
    events.fetch({ request, respondWith: (promise) => { response = promise; } });
    assert.equal(await response, page);
    offline = true;
    events.fetch({ request, respondWith: (promise) => { response = promise; } });
    assert.equal(await response, fallback);
    assert.equal(networkCalls, 2);
    assert.equal(cacheWrites, 0);
});
