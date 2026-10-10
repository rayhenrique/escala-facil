import assert from 'node:assert/strict';
import test from 'node:test';
import { registerSwapAnalysis } from '../../resources/js/swap-analysis.js';

function harness(triggerAction = 'openSwapAnalysis(1)') {
    const events = [];
    const alert = { hidden: true, textContent: '', focused: false, focus() { this.focused = true; } };
    const trigger = { isConnected: true, focused: false, getAttribute: () => triggerAction, focus() { this.focused = true; } };
    let visible = false;
    const overlay = { querySelector: () => alert, dispatchEvent: (event) => events.push(event.detail) };
    const component = { el: { querySelector: () => visible ? overlay : null, querySelectorAll: () => [trigger] } };
    globalThis.document = { activeElement: trigger };
    globalThis.requestAnimationFrame = (callback) => callback();
    let intercept;
    registerSwapAnalysis({ interceptMessage(callback) { intercept = callback; } });
    function message(name) {
        const hooks = {};
        intercept({ message: { component, actions: [{ name }] },
            onSend: (cb) => hooks.send = cb, onFinish: (cb) => hooks.finish = cb,
            onFailure: (cb) => hooks.failure = cb, onError: (cb) => hooks.error = cb });
        return hooks;
    }
    return { alert, trigger, events, message, show: (value) => visible = value };
}

test('busy state blocks escape until server completion and returns focus after closing', () => {
    const h = harness();
    const open = h.message('openSwapAnalysis');
    open.send(); h.show(true); open.finish();
    const confirm = h.message('confirmDecision');
    confirm.send();
    assert.equal(h.events.at(-1), true);
    confirm.finish();
    assert.equal(h.events.at(-1), false);
    const close = h.message('cancelDecision');
    h.show(false); close.finish();
    assert.equal(h.trigger.focused, true);
});

test('failed requests retain review and explain revalidation instead of claiming success', () => {
    const h = harness(); h.show(true);
    const request = h.message('confirmDecision');
    request.send(); request.failure(); request.finish();
    assert.equal(h.alert.hidden, false);
    assert.equal(h.alert.focused, true);
    assert.match(h.alert.textContent, /conferir o estado do registro/);
    assert.equal(h.events.at(-1), false);
    let prevented = false;
    request.error({ response: { status: 419 }, preventDefault() { prevented = true; } });
    assert.equal(prevented, true);
    assert.match(h.alert.textContent, /sessão expirou/);
});

test('reallocation shares busy feedback and returns focus to the original control', () => {
    const h = harness('openReallocation(1)');
    const opening = h.message('openReallocation');
    opening.send(); h.show(true); opening.finish();
    const request = h.message('confirmReallocation');
    request.send();
    assert.equal(h.events.at(-1), true);
    h.show(false); request.finish();
    assert.equal(h.trigger.focused, true);
});
