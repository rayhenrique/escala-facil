import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';
import vm from 'node:vm';

const source = readFileSync(new URL('../../resources/js/pwa-install.js', import.meta.url), 'utf8').replace('export function', 'function');

function boot({ ios = false, ipad = false, standalone = false, secure = true } = {}) {
    const events = {};
    const buttons = [{ hidden: true }, { hidden: true }];
    const guide = { hidden: true };
    const support = { hidden: true };
    const status = { hidden: true, textContent: '' };
    const mode = { matches: standalone, addEventListener: (name, cb) => { events.mode = cb; } };
    const add = (name, cb) => { (events[name] ??= []).push(cb); };
    const context = vm.createContext({
        navigator: { userAgent: ios ? 'iPhone' : 'Desktop', platform: ipad ? 'MacIntel' : 'Win32', maxTouchPoints: ipad ? 5 : 0, serviceWorker: {} },
        window: { matchMedia: () => mode, isSecureContext: secure, addEventListener: add },
        document: {
            addEventListener: add,
            querySelectorAll: (selector) => ({ '[data-pwa-install]': buttons, '[data-pwa-ios]': [guide], '[data-pwa-support]': [support], '[data-pwa-install-status]': [status] })[selector],
        },
    });
    vm.runInContext(source, context);
    const init = () => context.initPwaInstall();
    init();
    const dispatch = (name, event = {}) => Promise.all((events[name] ?? []).map((fn) => fn(event)));
    const offer = (prompt = async () => {}, userChoice = Promise.resolve({ outcome: 'dismissed' })) => dispatch('beforeinstallprompt', { preventDefault() {}, prompt, userChoice });
    const click = () => dispatch('click', { target: { closest: () => buttons[0] } });
    return { buttons, guide, support, status, mode, events, init, dispatch, offer, click };
}

test('unsupported browser hides installation and repeated initialization does not duplicate handlers', () => {
    const ui = boot();
    ui.init();
    assert.equal(ui.support.hidden, true);
    assert.equal(ui.buttons[0].hidden, true);
    assert.equal(ui.guide.hidden, true);
    assert.equal(ui.events.beforeinstallprompt.length, 1);
    assert.equal(ui.events.click.length, 1);
});

test('iPhone and desktop-mode iPad receive guidance, while installed or insecure contexts hide it', () => {
    for (const options of [{ ios: true }, { ipad: true }]) {
        const ui = boot(options);
        assert.equal(ui.guide.hidden, false);
        assert.equal(ui.support.hidden, false);
        assert.equal(ui.buttons[0].hidden, true);
        ui.mode.matches = true;
        ui.events.mode();
        assert.equal(ui.guide.hidden, true);
        assert.equal(ui.support.hidden, true);
    }
    assert.equal(boot({ ios: true, standalone: true }).guide.hidden, true);
    assert.equal(boot({ ios: true, secure: false }).guide.hidden, true);
});

test('one native offer is consumed once, all controls block duplicate clicks, and appinstalled can arrive during await', async () => {
    const ui = boot();
    let prompts = 0;
    let finish;
    const choice = new Promise((resolve) => { finish = resolve; });
    await ui.offer(async () => { prompts++; }, choice);
    const pending = ui.click();
    assert.equal(ui.buttons.every((button) => button.disabled && !button.hidden), true);
    await ui.click();
    assert.equal(prompts, 1);
    await ui.dispatch('appinstalled');
    assert.equal(ui.support.hidden, true);
    finish({ outcome: 'accepted' });
    await pending;
    assert.equal(ui.buttons.every((button) => button.hidden && !button.disabled), true);
    assert.equal(ui.status.textContent, '');
});

test('cancelled offer remains consumed and a later browser offer enables installation again', async () => {
    const ui = boot();
    let prompts = 0;
    await ui.offer(async () => { prompts++; });
    await ui.click();
    await ui.click();
    assert.equal(prompts, 1);
    assert.equal(ui.buttons[0].hidden, true);
    await ui.offer();
    assert.equal(ui.buttons[0].hidden, false);
    await ui.dispatch('livewire:navigated');
    assert.equal(ui.buttons[1].hidden, false);
});

test('failed install recovers disabled controls and reports a non-blocking alternative', async () => {
    const ui = boot();
    await ui.offer(async () => { throw new Error('blocked'); });
    await ui.click();
    assert.equal(ui.buttons.every((button) => !button.disabled), true);
    assert.equal(ui.status.hidden, false);
    assert.match(ui.status.textContent, /continuar pelo navegador/);
    await ui.offer();
    assert.equal(ui.status.hidden, true);
    assert.equal(ui.buttons[0].hidden, false);
});
