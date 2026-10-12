import test from 'node:test';
import assert from 'node:assert/strict';
import { feedbackState, failureDescription, registerOperationalFeedback } from '../../resources/js/operational-feedback.js';

test('confirmed notification identities deduplicate re-renders, while identical new operations remain visible', () => {
    const state = feedbackState();
    state.notify({ id: 'confirmed-1', tone: 'success', message: 'Salvo.' });
    state.notify({ id: 'confirmed-1', tone: 'success', message: 'Salvo.' });
    assert.equal(state.messages.length, 1);
    state.dismiss('confirmed-1'); state.notify({ id: 'confirmed-1', message: 'Salvo.' });
    assert.equal(state.messages.length, 0);
    state.notify({ id: 'confirmed-2', message: 'Salvo.' }); assert.equal(state.messages.length, 1);
    for (let i = 0; i < 150; i++) state.notify({ id: `next-${i}` });
    assert.equal(state.seen.length, 128);
});

test('connection probing is explicit, does not replay decisions and retains uncertain completion', async () => {
    let probes = 0, refreshes = 0;
    const state = feedbackState({ probe: async () => { probes++; return { ok: true }; } });
    state.failure = { scope: 'request', kind: 'connection', mutable: true };
    globalThis.Livewire = { find: () => ({ $refresh: async () => refreshes++ }) };
    await state.refreshQuery(); assert.equal(refreshes, 0);
    await Promise.all([state.checkConnection(), state.checkConnection()]);
    assert.equal(probes, 1); assert.equal(state.connectionChecked, true); assert.equal(state.failure.mutable, true);
    state.failure.mutable = false; await state.refreshQuery(); assert.equal(refreshes, 1);
    state.failure.kind = 'expired'; await state.refreshQuery(); assert.equal(refreshes, 1);
    delete globalThis.Livewire;
});

test('failed probes and status messages never expose raw server details or infer success', async () => {
    const state = feedbackState({ probe: async () => { throw new Error('private server data'); } });
    await state.checkConnection(); assert.equal(state.offline, true); assert.equal(state.checking, false);
    for (const code of [null, 401, 403, 404, 409, 419, 422, 500]) {
        const description = failureDescription(code, true);
        assert(description.message.length > 30); assert(!/private|SQL|token|stack/i.test(description.message));
    }
    assert.match(failureDescription(null, true).message, /pode ter sido recebida/);
    assert.equal(state.messages.length, 0);
});

test('one registration, duplicate activation and repeated response completion are safe', () => {
    const callbacks = {}, listeners = [], states = {};
    globalThis.window = { addEventListener: (name, fn) => listeners.push([name, fn]) };
    globalThis.document = { addEventListener: (name, fn) => listeners.push([name, fn]), querySelectorAll: () => [], querySelector: () => null };
    globalThis.requestAnimationFrame = fn => fn();
    const Alpine = { store: (key, value) => value ? states[key] = value : states[key] };
    globalThis.Alpine = Alpine;
    const Livewire = { interceptMessage: fn => callbacks.message = fn, interceptAction: fn => callbacks.action = fn };
    registerOperationalFeedback(Livewire, Alpine); registerOperationalFeedback(Livewire, Alpine);
    assert.equal(listeners.length, 3);
    const state = states.operationalFeedback;
    const component = { id: 'component', el: { isConnected: true, querySelector: () => null } };
    let cancelled = 0, finish;
    callbacks.action({ action: { name: 'confirmDecision', component, cancel: () => cancelled++ }, onFinish: fn => finish = fn });
    callbacks.action({ action: { name: 'confirmDecision', component, cancel: () => cancelled++ }, onFinish: () => {} });
    assert.equal(cancelled, 1); finish(); finish();
    callbacks.action({ action: { name: 'confirmDecision', component, cancel: () => cancelled++ }, onFinish: () => {} });
    assert.equal(cancelled, 1);
    const h = {};
    callbacks.message({ message: { component, actions: [{ name: 'confirmDecision' }] }, onSend: fn => h.send = fn, onFinish: fn => h.finish = fn, onSuccess: fn => h.success = fn, onFailure: fn => h.failure = fn, onError: fn => h.error = fn });
    h.send(); assert.equal(state.pending.component, 1); h.failure(); assert.equal(state.failure.mutable, true);
    h.finish(); h.finish(); assert.equal(state.pending.component, undefined); assert.equal(state.messages.length, 0);
    let prevented = false;
    h.error({ response: { status: 419 }, preventDefault: () => prevented = true });
    assert(prevented); assert.equal(state.failure.kind, 'expired');
    listeners.find(([name]) => name === 'online')[1](); assert.equal(state.failure.kind, 'expired');
    state.modalOpen = true; assert.equal(state.visible('component', false), false); assert.equal(state.visible('component', true), true);
    delete globalThis.Alpine;
});
