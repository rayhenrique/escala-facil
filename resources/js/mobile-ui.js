import { mobileFilterPanel } from './mobile-filters.js';

// Shared interaction only. Persistence and permissions remain in Livewire/Actions.
export function actionDialog(options = {}) {
    return {
        busy: false, dirty: false, discardRequested: false,
        origin: null, dialogRoot: null, initialValues: '',
        opened: false, viewport: null, viewportChanged: null,
        init() {
            // Alpine $el follows the event handler element; keep the dialog root stable.
            this.dialogRoot = this.$el;
            if (options.fitViewport && globalThis.visualViewport) {
                this.viewport = globalThis.visualViewport;
                this.viewportChanged = () => {
                    // Keep this sheet above overlay keyboards; zoom retains native behavior.
                    if (this.viewport.scale !== 1) return;
                    this.dialogRoot.style.setProperty('--dialog-viewport-height', `${this.viewport.height}px`);
                    this.dialogRoot.style.setProperty('height', `${this.viewport.height}px`);
                    this.dialogRoot.style.setProperty('top', `${this.viewport.offsetTop}px`);
                    this.dialogRoot.style.setProperty('bottom', 'auto');
                };
                this.viewport.addEventListener('resize', this.viewportChanged);
                this.viewport.addEventListener('scroll', this.viewportChanged);
                this.viewportChanged();
            }
            if (options.active) {
                this.$watch(() => options.active(), value => value ? this.begin() : this.restore());
                if (options.active()) this.begin();
            } else this.begin();
        },
        begin() {
            this.opened = true;
            this.origin = document.activeElement;
            this.$nextTick(() => {
                this.initialValues = this.values();
                this.focusInitial();
            });
        },
        values() {
            return JSON.stringify([...this.dialogRoot.querySelectorAll('input, select, textarea')]
                .filter(el => el.name !== '_token' && !el.disabled)
                .map(el => [el.id || el.name, el.type === 'checkbox' || el.type === 'radio' ? el.checked : el.type === 'file' ? [...el.files].map(file => [file.name, file.size, file.lastModified]) : el.value]));
        },
        changed() {
            if (options.trackChanges) this.dirty = this.values() !== this.initialValues;
        },
        focusInitial() {
            const target = this.dialogRoot.querySelector(options.initialFocus || '[data-confirm-cancel], [x-ref="firstField"], [data-dialog-body]');
            target?.focus({ preventScroll: true });
        },
        requestClose() {
            if (this.busy) return;
            if (this.dirty) {
                this.discardRequested = true;
                this.$nextTick(() => this.$refs.keepEditing.focus());
                return;
            }
            options.close?.();
        },
        keepEditing() {
            this.discardRequested = false;
            this.$nextTick(() => this.focusInitial());
        },
        discard() {
            if (!this.busy) options.close?.();
        },
        restore() {
            if (!this.opened) return;
            this.opened = false;
            if (options.restoreFocus === false) return;
            const origin = this.origin;
            requestAnimationFrame(() => {
                const active = document.activeElement;
                if (active !== document.body && !this.dialogRoot.contains(active)) return;
                const target = origin?.isConnected && origin !== document.body ? origin : (options.returnFocus && document.querySelector(options.returnFocus));
                target?.focus({ preventScroll: true });
            });
        },
        destroy() {
            this.viewport?.removeEventListener('resize', this.viewportChanged);
            this.viewport?.removeEventListener('scroll', this.viewportChanged);
            this.restore();
        },
    };
}

export function toastMessage({ duration = 5500 } = {}) {
    return {
        visible: true, timer: null, remaining: duration, started: 0,
        init() { this.resume(); },
        pause() {
            if (!this.timer) return;
            clearTimeout(this.timer);
            this.timer = null;
            this.remaining = Math.max(0, this.remaining - (Date.now() - this.started));
        },
        resume() {
            if (!this.visible || !duration || this.timer || this.$el.matches(':hover') || this.$el.contains(document.activeElement)) return;
            this.started = Date.now();
            this.timer = setTimeout(() => { this.visible = false; this.timer = null; }, this.remaining);
        },
        dismiss() { this.pause(); this.visible = false; },
        destroy() { this.pause(); },
    };
}

export function mobileNavigation(options = {}) {
    return {
        menuOpen: false, keyboardOpen: false, hash: '', disposers: [],
        init() {
            let baselineHeight = window.innerHeight;
            let baselineWidth = window.innerWidth;
            const sync = () => {
                this.hash = window.location.hash;
                if (this.menuOpen && window.innerWidth >= 1024) this.menuOpen = false;
                if (window.innerWidth !== baselineWidth) {
                    baselineWidth = window.innerWidth;
                    baselineHeight = window.innerHeight;
                }
                const focused = document.activeElement;
                const editing = focused?.matches('input:not([type="checkbox"]):not([type="radio"]):not([type="button"]):not([type="submit"]), textarea, select, [contenteditable="true"]');
                const height = window.visualViewport?.height ?? window.innerHeight;
                this.keyboardOpen = window.innerWidth < 1024 && !!editing && Math.max(window.innerHeight, baselineHeight) - height > 120;
                document.body.classList.toggle('mobile-keyboard-open', this.keyboardOpen);
                if (!editing && !this.keyboardOpen) baselineHeight = window.innerHeight;
            };
            const focusDestination = () => {
                sync();
                if (!options.professional) return;
                const ids = ['#professional-home', '#schedule-title', '#my-swaps-title', '#incoming-title'];
                if (!ids.includes(this.hash)) return;
                requestAnimationFrame(() => {
                    const target = document.querySelector(this.hash);
                    target?.focus({ preventScroll: true });
                    target?.scrollIntoView({ block: 'start' });
                });
            };
            const listen = (target, event, handler) => {
                target?.addEventListener(event, handler);
                if (target) this.disposers.push(() => target.removeEventListener(event, handler));
            };
            listen(window, 'resize', sync);
            listen(window.visualViewport, 'resize', sync);
            listen(document, 'focusin', sync);
            listen(document, 'focusout', () => this.$nextTick(sync));
            listen(window, 'hashchange', focusDestination);
            listen(document, 'livewire:navigated', focusDestination);
            this.$nextTick(focusDestination);
        },
        isActive(key) {
            let group = options.group;
            if (options.professional && group === 'home') {
                if (this.hash === '#schedule-title') group = 'schedule';
                else if (['#my-swaps-title', '#incoming-title'].includes(this.hash)) group = 'swaps';
            }
            return key === group;
        },
        destroy() {
            this.disposers.forEach(dispose => dispose());
            document.body.classList.remove('mobile-keyboard-open');
        },
    };
}

const registered = new WeakSet();
export function registerMobileUi(Livewire, Alpine) {
    if (registered.has(Livewire)) return;
    registered.add(Livewire);
    Alpine.data('actionDialog', actionDialog);
    Alpine.data('toastMessage', toastMessage);
    Alpine.data('mobileNavigation', mobileNavigation);
    Alpine.data('mobileFilterPanel', mobileFilterPanel);
    const pending = new WeakMap();
    Livewire.interceptMessage(({ message, onSend, onFinish, onSuccess, onFailure }) => {
        const dialog = () => message.component.el.querySelector('[data-ui-dialog-component]');
        if (!dialog()) return;
        const busy = value => dialog()?.dispatchEvent(new CustomEvent('ui-dialog-busy', { detail: value }));
        onSend(() => { pending.set(message.component, (pending.get(message.component) || 0) + 1); busy(true); });
        onFinish(() => {
            const count = Math.max(0, (pending.get(message.component) || 0) - 1);
            pending.set(message.component, count);
            busy(count > 0);
        });
        onSuccess(({ onRender }) => onRender(() => requestAnimationFrame(() => {
            const field = dialog()?.querySelector('[aria-invalid="true"], [data-validation-error]');
            field?.scrollIntoView({ block: 'nearest' });
            field?.focus({ preventScroll: true });
        })));
        onFailure(() => {
            const alert = dialog()?.querySelector('[data-ui-request-error]');
            if (alert) { alert.hidden = false; alert.focus(); }
        });
    });
}
