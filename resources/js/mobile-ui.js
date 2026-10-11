// Shared interaction only. Persistence and permissions remain in Livewire/Actions.
export function actionDialog(options = {}) {
    return {
        busy: false, dirty: false, discardRequested: false,
        origin: null, initialValues: '',
        init() {
            this.origin = document.activeElement;
            this.$nextTick(() => {
                this.initialValues = this.values();
                this.focusInitial();
            });
        },
        values() {
            return JSON.stringify([...this.$el.querySelectorAll('input, select, textarea')]
                .filter(el => el.name !== '_token' && !el.disabled)
                .map(el => [el.id || el.name, el.type === 'checkbox' || el.type === 'radio' ? el.checked : el.type === 'file' ? [...el.files].map(file => [file.name, file.size, file.lastModified]) : el.value]));
        },
        changed() {
            if (options.trackChanges) this.dirty = this.values() !== this.initialValues;
        },
        focusInitial() {
            const target = this.$el.querySelector(options.initialFocus || '[data-confirm-cancel], [x-ref="firstField"], [data-dialog-body]');
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
        destroy() {
            if (options.restoreFocus === false) return;
            const origin = this.origin;
            requestAnimationFrame(() => {
                const active = document.activeElement;
                if (active !== document.body && !this.$el.contains(active)) return;
                const target = origin?.isConnected ? origin : (options.returnFocus && document.querySelector(options.returnFocus));
                target?.focus({ preventScroll: true });
            });
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

const registered = new WeakSet();
export function registerMobileUi(Livewire, Alpine) {
    if (registered.has(Livewire)) return;
    registered.add(Livewire);
    Alpine.data('actionDialog', actionDialog);
    Alpine.data('toastMessage', toastMessage);
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
            const field = dialog()?.querySelector('[aria-invalid="true"]');
            field?.scrollIntoView({ block: 'nearest' });
            field?.focus({ preventScroll: true });
        })));
        onFailure(() => {
            const alert = dialog()?.querySelector('[data-ui-request-error]');
            if (alert) { alert.hidden = false; alert.focus(); }
        });
    });
}
