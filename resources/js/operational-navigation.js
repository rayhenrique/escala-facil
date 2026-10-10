const visited = new WeakSet();

export function initOperationalNavigation() {
    const label = document.querySelector('[data-context-target]');
    if (!label) return;
    const record = [...document.querySelectorAll('[data-context-record]')].find((element) => element.dataset.contextRecord && element.getClientRects().length);
    const target = record ?? label;
    if (!target || visited.has(target)) return;
    visited.add(target);
    if (record) {
        target.setAttribute('tabindex', '-1');
        target.setAttribute('aria-labelledby', 'context-selected-record');
    }
    target.focus({ preventScroll: true });
    target.scrollIntoView({ block: 'start', behavior: 'auto' });
}
