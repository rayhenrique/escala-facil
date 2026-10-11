import assert from 'node:assert/strict';
import test from 'node:test';
import { bootLivewire } from '../../resources/js/livewire-runtime.js';

function harness() {
    const calls = [];
    const window = { location: { reload: () => calls.push('reload') } };
    const options = { window, asset: '/app-v1.js', load: async () => {
        calls.push('import'); window.Livewire = {}; window.Alpine = {};
        return { Livewire: window.Livewire };
    }, initialize: ({ Livewire }) => { assert.equal(Livewire, window.Livewire); calls.push('start'); } };
    return { options, calls, window };
}

test('concurrent and repeated startup imports and starts the runtime once', async () => {
    const h = harness();
    const first = bootLivewire(h.options);
    assert.equal(bootLivewire(h.options), first);
    assert.equal(await first, true);
    assert.equal(await bootLivewire(h.options), true);
    assert.deepEqual(h.calls, ['import', 'start']);
});

test('a changed asset reloads before importing or overwriting existing runtimes', async () => {
    const h = harness(); await bootLivewire(h.options);
    const runtime = h.window.Livewire; const alpine = h.window.Alpine;
    assert.equal(await bootLivewire({ ...h.options, asset: '/app-v2.js' }), false);
    await bootLivewire({ ...h.options, asset: '/app-v3.js' });
    assert.deepEqual(h.calls, ['import', 'start', 'reload']);
    assert.equal(h.window.Livewire, runtime); assert.equal(h.window.Alpine, alpine);
});

test('a version open before the guard was installed reloads without a second import', async () => {
    for (const name of ['Livewire', 'Alpine']) {
        const h = harness(); const existing = {}; h.window[name] = existing;
        assert.equal(await bootLivewire(h.options), false);
        assert.deepEqual(h.calls, ['reload']); assert.equal(h.window[name], existing);
    }
});

test('failed loading is reported and never retries an uncertain runtime automatically', async () => {
    const h = harness(); h.options.load = () => { h.calls.push('import'); throw new Error('offline'); };
    const first = bootLivewire(h.options);
    await assert.rejects(first, /offline/);
    assert.equal(bootLivewire(h.options), first);
    assert.deepEqual(h.calls, ['import']);
});
