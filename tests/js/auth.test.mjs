import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';
import vm from 'node:vm';
import { reportRequestFailure } from '../../resources/js/operational-feedback.js';

const source = readFileSync(new URL('../../resources/js/auth.js', import.meta.url), 'utf8').replace(/^import .*;\r?\n/, '').replace('export function', 'function');

function boot() {
    const handlers = {};
    const cleanups = [];
    const updates = [];
    const message = { textContent: '' };
    const reload = { hidden: true };
    const alert = { hidden: true, focus() { this.focused = true; }, querySelector: (selector) => selector.includes('message') ? message : reload };
    const invalid = { focus() { this.focused = true; } };
    const fields = ['email', 'password', 'remember'].map((name) => ({ type: name === 'remember' ? 'checkbox' : name === 'password' ? 'password' : 'email', value: `fixture-${name}`, checked: true, getAttribute: () => `form.${name}` }));
    const form = { querySelector: (selector) => selector.includes('aria-invalid') ? invalid : alert, querySelectorAll: () => fields, addEventListener: (name, cb, options) => { handlers[name] = cb; handlers.signal = options.signal; } };
    const component = { el: { isConnected: true, querySelector: () => form }, $wire: {
        $set: (...args) => updates.push(args),
        $interceptRequest: (cb) => { cb({ onFailure: (fn) => { handlers.failure = fn; }, onError: (fn) => { handlers.error = fn; } }); return () => { handlers.requestCleaned = true; }; },
    } };
    const context = vm.createContext({ reportRequestFailure, AbortController, requestAnimationFrame: (cb) => cb(), window: { location: { reload: () => { handlers.reloaded = true; } } } });
    vm.runInContext(source, context);
    context.registerAuthUi({
        hook: (_, callback) => callback({ component, cleanup: (fn) => cleanups.push(fn) }),
        interceptMessage: (cb) => {
            cb({ message: { component }, onSuccess: (fn) => { handlers.success = fn; } });
            handlers.otherComponent = () => cb({ message: { component: {} }, onSuccess: () => { handlers.leaked = true; } });
            return () => { handlers.messageCleaned = true; };
        },
    });
    return { handlers, cleanups, updates, fields, message, reload, alert, invalid, component };
}

test('autofill values and remember checkbox are synchronized locally before submission without extra requests', () => {
    const ui = boot();
    ui.handlers.submit();
    assert.deepEqual(ui.updates, [['form.email', 'fixture-email', false], ['form.password', 'fixture-password', false], ['form.remember', true, false]]);
});

test('rendered validation focuses the first invalid field and lifecycle cleanup releases listeners', () => {
    const ui = boot();
    ui.handlers.success({ onRender: (cb) => cb() });
    assert.equal(ui.invalid.focused, true);
    ui.handlers.otherComponent();
    assert.equal(ui.handlers.leaked, undefined);
    ui.cleanups.forEach((fn) => fn());
    assert.equal(ui.handlers.signal.aborted, true);
    assert.equal(ui.handlers.requestCleaned, true);
    assert.equal(ui.handlers.messageCleaned, true);
});

test('request failure preserves values, expiration requires deliberate reload, and detached pages are ignored', () => {
    const ui = boot();
    const before = ui.fields.map((field) => field.value);
    ui.handlers.failure();
    assert.equal(ui.alert.hidden, false);
    assert.equal(ui.reload.hidden, true);
    let prevented = false;
    ui.handlers.error({ response: { status: 419 }, preventDefault: () => { prevented = true; } });
    assert.equal(prevented, true);
    assert.equal(ui.reload.hidden, false);
    assert.equal(ui.handlers.reloaded, undefined);
    assert.deepEqual(ui.fields.map((field) => field.value), before);
    ui.handlers.click({ target: { closest: () => ui.reload } });
    assert.equal(ui.handlers.reloaded, true);
    ui.handlers.submit();
    ui.component.el.isConnected = false;
    ui.handlers.failure();
    assert.equal(ui.alert.hidden, true);
});
