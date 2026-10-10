import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';
import vm from 'node:vm';

const source = readFileSync(new URL('../../resources/js/landing.js', import.meta.url), 'utf8')
    .replace(/^import .*;\r?\n/gm, '')
    .replace('export function', 'function');

function boot({ reduced = false, fail = false } = {}) {
    const root = { querySelectorAll: () => [] };
    const events = {};
    const state = { lenis: 0, destroyed: 0, ticker: new Set(), triggers: [], reverted: 0 };
    const cards = [{ id: 1 }, { id: 2 }, { id: 3 }];
    const motion = { matches: reduced, addEventListener: (_, fn) => { events.motion = fn; } };
    const gsap = {
        registerPlugin() {},
        ticker: { add: (fn) => state.ticker.add(fn), remove: (fn) => state.ticker.delete(fn) },
        matchMedia: () => ({
            add: (query, fn) => { if (query.includes('min-width')) fn(); },
            revert: () => { state.reverted++; },
        }),
        utils: { toArray: () => cards },
        from: (target, config) => state.triggers.push({ target, config }),
        fromTo: (_, from, to) => {
            assert.equal(from.opacity, undefined);
            assert.equal(to.opacity, undefined);
        },
    };
    const context = vm.createContext({
        gsap, AbortController, console,
        ScrollTrigger: { update() {}, refresh() {} },
        Lenis: class {
            constructor() { state.lenis++; if (fail) throw new Error('Lenis unavailable'); }
            on() {} raf() {} scrollTo() {}
            destroy() { state.destroyed++; }
        },
        window: { matchMedia: () => motion, addEventListener() {} },
        document: {
            getElementById: () => root,
            querySelector: (selector) => selector.includes('sim') ? null : {},
            querySelectorAll: () => [],
            addEventListener: (event, fn) => { events[event] = fn; },
        },
    });
    vm.runInContext(source, context);
    return { state, events, motion, cards, init: () => context.initLandingPage() };
}

test('duplicate lifecycle events keep one Lenis and ticker; navigation releases both', () => {
    const { state, events, init } = boot();
    init(); init();
    assert.equal(state.lenis, 1);
    assert.equal(state.ticker.size, 1);
    events['livewire:navigating']();
    assert.equal(state.destroyed, 1);
    assert.equal(state.ticker.size, 0);
    init();
    assert.equal(state.lenis, 2);
});

test('content tweens never hide targets and cards use individual deferred triggers', () => {
    const { state, cards, init } = boot();
    init();
    for (const { target, config } of state.triggers) {
        assert.equal(config.opacity, undefined);
        assert.equal(config.immediateRender, false);
        if (cards.includes(target)) assert.equal(config.scrollTrigger.trigger, target);
    }
    assert.equal(state.triggers.filter(({ target }) => cards.includes(target)).length, 3);
});

test('reduced motion bypasses enhancements and changing it cleans up the running scroll', () => {
    const { state, events, motion, init } = boot({ reduced: true });
    init();
    assert.equal(state.lenis, 0);
    motion.matches = false;
    events.motion();
    assert.equal(state.lenis, 1);
    motion.matches = true;
    events.motion();
    assert.equal(state.destroyed, 1);
    assert.equal(state.ticker.size, 0);
});

test('enhancement failure releases resources without throwing', () => {
    const { state, init } = boot({ fail: true });
    assert.doesNotThrow(init);
    assert.equal(state.ticker.size, 0);
    assert.equal(state.reverted, 1);
});
