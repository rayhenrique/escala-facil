import assert from 'node:assert/strict';
import test from 'node:test';
import { registerSwapAnalysis } from '../../resources/js/swap-analysis.js';

function harness(triggerAction = 'openSwapAnalysis(1)', shiftFlow = ['openCreateModal', 'edit'].includes(triggerAction), invitationFlow = false) {
    const events = [];
    const alert = { hidden: true, textContent: '', focused: false, focus() { this.focused = true; }, querySelector() { return null; } };
    const trigger = { isConnected: true, focused: false, getAttribute: () => triggerAction, focus() { this.focused = true; } };
    let visible = false;
    let invalidField = null;
    const body = { scrollTop: 100, focused: false, focus() { this.focused = true; }, querySelector: selector => selector === '[aria-invalid="true"]' ? invalidField : null };
    let decisionError = null;
    const overlay = { querySelector: selector => selector === '.modal-body' ? body : selector === '[data-swap-decision-error]' ? decisionError : alert, dispatchEvent: (event) => events.push(event.detail) };
    const component = { el: { hasAttribute: key => key === 'data-shift-assignment-flow' ? shiftFlow : invitationFlow, querySelector: () => visible ? overlay : null, querySelectorAll: () => [trigger] } };
    globalThis.document = { activeElement: trigger };
    globalThis.requestAnimationFrame = (callback) => callback();
    let intercept;
    registerSwapAnalysis({ interceptMessage(callback) { intercept = callback; } });
    function message(name) {
        const hooks = {};
        intercept({ message: { component, actions: [{ name }] },
            onSend: (cb) => hooks.send = cb, onFinish: (cb) => hooks.finish = cb, onSuccess: (cb) => hooks.success = cb,
            onFailure: (cb) => hooks.failure = cb, onError: (cb) => hooks.error = cb });
        return hooks;
    }
    return { alert, body, trigger, events, message, show: (value) => visible = value, decisionError: value => { decisionError = value; }, invalidField: value => { invalidField = value; } };
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

test('explicit analysis refresh restores body focus and stale decisions focus their specific reason', () => {
    const h = harness(); h.show(true);
    const refresh = h.message('openSwapAnalysis');
    refresh.success({ onRender(callback) { callback(); } });
    assert.equal(h.body.scrollTop, 0);
    assert.equal(h.body.focused, true);
    const error = { scrolled: false, focused: false, scrollIntoView() { this.scrolled = true; }, focus() { this.focused = true; } };
    h.decisionError(error);
    const confirm = h.message('confirmDecision');
    confirm.success({ onRender(callback) { callback(); } });
    assert.equal(error.scrolled, true);
    assert.equal(error.focused, true);
});

test('rejection validation brings the invalid justification field into view and focuses it', () => {
    const h = harness(); h.show(true);
    const field = { scrolled: false, focused: false, scrollIntoView() { this.scrolled = true; }, focus() { this.focused = true; } };
    h.invalidField(field);
    h.message('confirmDecision').success({ onRender(callback) { callback(); } });
    assert.equal(field.scrolled, true);
    assert.equal(field.focused, true);
    assert.equal(h.body.focused, false);
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

test('shift creation and editing share busy feedback, failure recovery and focus restoration', () => {
    for (const action of ['openCreateModal', 'edit']) {
        const h = harness(action);
        const opening = h.message(action);
        opening.send(); h.show(true); opening.finish();
        const saving = h.message('save');
        saving.send();
        assert.equal(h.events.at(-1), true);
        saving.failure(); saving.finish();
        assert.equal(h.alert.hidden, false);
        assert.equal(h.events.at(-1), false);
        const closing = h.message('closeFormModal');
        h.show(false); closing.finish();
        assert.equal(h.trigger.focused, true);
    }
});

test('ordinary admin forms retain their existing lifecycle', () => {
    for (const action of ['openCreateModal', 'edit']) {
        const h = harness(action, false);
        assert.equal(h.message(action).send, undefined);
        assert.equal(h.trigger.focused, false);
    }
});

test('invitation forms and revocation share occupied feedback and recoverable errors', () => {
    for (const action of ['openInvitationModal', 'requestInvitationRevocation']) {
        const h = harness(action, false, true);
        const opening = h.message(action);
        opening.send(); h.show(true); opening.finish();
        const saving = h.message(action === 'openInvitationModal' ? 'save' : 'confirmInvitationRevocation');
        saving.send(); saving.failure(); saving.finish();
        assert.equal(h.alert.hidden, false);
        assert.equal(h.events.at(-1), false);
    }
});
