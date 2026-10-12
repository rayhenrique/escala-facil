// Presentation only. No request is replayed and no success is inferred here.
const registered = new WeakSet();
const mutationNames = new Set(['save', 'delete', 'approve', 'reject', 'confirmDecision', 'confirmRevert', 'confirmReallocation', 'confirmSwapRequest', 'confirmAcceptSwap', 'confirmDeclineSwap', 'confirmAbsence', 'reportAbsence', 'requestSwap', 'acceptSwap', 'revokeInvitation', 'import', 'importShifts', 'confirmImport', 'confirmCopy', 'resendInvitation', 'confirmInvitationRevocation', 'markAsRead', 'markAllAsRead', 'submit', 'login', 'sendResetLink', 'resetPassword', 'activate']);
export const isMutation = name => mutationNames.has(name);

export function failureDescription(status, mutable = false) {
    if ([401, 419].includes(status)) return { kind: 'expired', message: 'A sessão expirou. Seus dados continuam nesta tela. Copie o que precisar antes de atualizar a página e entrar novamente.' };
    if (status === 403) return { kind: 'forbidden', message: 'Seu acesso não permite esta operação. Confira o perfil utilizado ou peça orientação à gerência.' };
    if ([404, 409].includes(status)) return { kind: 'conflict', message: 'As informações deste registro podem ter mudado. Atualize e revise os detalhes antes de continuar.' };
    if (status === 422) return { kind: 'validation', message: 'Confira os campos indicados e corrija os dados antes de continuar.' };
    return { kind: status ? 'server' : 'connection', message: mutable
        ? 'Não foi possível confirmar a resposta. Seus dados foram mantidos. É necessário conferir o estado do registro antes de tentar novamente; a operação pode ter sido recebida.'
        : 'Não foi possível carregar as informações. Confira sua conexão e tente atualizar a consulta.' };
}

export function feedbackState({ probe = () => fetch('/up', { cache: 'no-store', credentials: 'same-origin' }) } = {}) {
    return {
        messages: [], seen: [], failure: null, offline: globalThis.navigator?.onLine === false,
        checking: false, connectionChecked: false, pending: {}, modalOpen: false, authInline: false,
        notify(item) {
            if (!item.id || this.seen.includes(item.id)) return;
            this.seen.push(item.id); if (this.seen.length > 128) this.seen.shift();
            this.messages.push(item);
        },
        dismiss(id) { this.messages = this.messages.filter(item => item.id !== id); },
        fail(component, status, mutable) {
            const next = { ...failureDescription(status, mutable), scope: component.id, mutable };
            this.connectionChecked = false;
            if (this.failure?.scope === next.scope && this.failure.kind === next.kind && this.failure.message === next.message) return;
            this.failure = next;
            requestAnimationFrame(() => {
                const alerts = component.el.querySelectorAll?.('[data-operational-inline] [data-operation-error]') ?? [];
                const alert = [...alerts].find(el => el.getClientRects().length && !el.closest('[inert], [aria-hidden="true"]'));
                alert?.focus({ preventScroll: true });
            });
        },
        clear(scope) { if (this.failure?.scope === scope) this.failure = null; },
        visible(scope, inline) {
            if (!inline && (this.modalOpen || this.authInline)) return false;
            if (inline && this.failure && this.failure.scope !== scope) return this.offline;
            return this.offline || !!this.failure;
        },
        async checkConnection() {
            if (this.checking) return;
            this.checking = true; this.connectionChecked = false;
            try { const response = await probe(); this.offline = !response.ok; this.connectionChecked = response.ok; }
            catch { this.offline = true; }
            finally { this.checking = false; }
        },
        async refreshQuery() {
            const failure = this.failure;
            if (!failure || failure.mutable || ['expired', 'forbidden'].includes(failure.kind) || this.checking) return;
            const component = globalThis.Livewire?.find(failure.scope);
            if (!component) return;
            // Explicit read-only refresh; never repeats the original action.
            await component.$refresh();
        },
    };
}

export function reportRequestFailure(component, status = null, mutable = false) {
    if (!component.el.isConnected && component.el.isConnected !== undefined) return;
    const state = globalThis.Alpine?.store?.('operationalFeedback');
    if (state) { state.fail(component, status, mutable); return; }
    // Retain the established local fallback if the shared outlet is unavailable.
    const root = component.el.querySelector('[data-auth-form]') ?? component.el;
    const alert = root.querySelector('[data-ui-request-error]') ?? root.querySelector('[data-decision-request-error]') ?? root.querySelector('[data-auth-request-error]');
    if (!alert) return;
    const message = alert.querySelector?.('[data-auth-request-message]') ?? alert;
    message.textContent = failureDescription(status, mutable).message;
    const reload = alert.querySelector?.('[data-auth-reload]');
    if (reload) reload.hidden = ![401, 419].includes(status);
    alert.hidden = false; alert.focus();
}

export function registerOperationalFeedback(Livewire, Alpine) {
    if (!Alpine.store || registered.has(Livewire)) return;
    registered.add(Livewire);
    Alpine.store('operationalFeedback', feedbackState());
    const state = Alpine.store('operationalFeedback');
    const activeMutations = new WeakSet();
    // Squash duplicate UI activations before they reach the request queue.
    Livewire.interceptAction?.(({ action, onFinish }) => {
        if (!isMutation(action.name) || action.name === 'markAsRead') return;
        if (activeMutations.has(action.component)) { action.cancel(); return; }
        activeMutations.add(action.component);
        let finished = false;
        onFinish(() => { if (!finished) { finished = true; activeMutations.delete(action.component); } });
    });
    Livewire.interceptMessage(({ message, onSend, onFinish, onSuccess, onFailure, onError }) => {
        const mutable = [...message.actions].some(action => isMutation(action.name ?? action.method));
        let sent = false, finished = false;
        onSend(() => {
            sent = true;
            if (mutable) state.pending[message.component.id] = (state.pending[message.component.id] ?? 0) + 1;
        });
        onFinish(() => {
            if (finished || !sent) return; finished = true;
            if (mutable) {
                const count = Math.max(0, (state.pending[message.component.id] ?? 0) - 1);
                if (count) state.pending[message.component.id] = count; else delete state.pending[message.component.id];
            }
        });
        onSuccess(({ onRender }) => onRender(() => {
            if ([...message.actions].some(action => !String(action.name ?? action.method).startsWith('$')) || !state.failure?.mutable) state.clear(message.component.id);
        }));
        onFailure(() => reportRequestFailure(message.component, null, mutable));
        onError(({ response, preventDefault }) => {
            preventDefault(); // Never expose a server HTML/debug response in product UI.
            reportRequestFailure(message.component, response.status, mutable);
        });
    });
    window.addEventListener('offline', () => { state.offline = true; state.connectionChecked = false; });
    window.addEventListener('online', () => { state.offline = false; state.connectionChecked = false; });
    document.addEventListener('livewire:navigating', () => { state.messages = []; state.failure = null; });
    const syncOutlet = () => {
        state.modalOpen = [...document.querySelectorAll('[aria-modal="true"]')].some(el => el.getClientRects().length > 0);
        state.authInline = !!document.querySelector('[data-auth-form]');
    };
    syncOutlet();
    if (globalThis.MutationObserver) new MutationObserver(syncOutlet).observe(document.body, { subtree: true, childList: true, attributes: true, attributeFilter: ['style', 'aria-modal', 'hidden'] });
}
