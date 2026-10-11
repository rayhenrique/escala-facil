// Feedback only: authorization, stale snapshots and deduplication stay in the Action.
export function registerSwapAnalysis(Livewire) {
    const origins = new WeakMap();
    Livewire.interceptMessage(({ message, onSend, onFinish, onFailure, onError, onSuccess }) => {
        const dialog = () => message.component.el.querySelector('[data-decision-component]');
        const hadDialog = !!dialog();
        const opening = [...message.actions].some((action) => {
            const name = action.name ?? action.method;
            return ['openSwapAnalysis', 'requestDecision', 'openReallocation', 'requestRevert'].includes(name)
                || (message.component.el.hasAttribute('data-shift-assignment-flow') && ['openCreateModal', 'edit'].includes(name))
                || (message.component.el.hasAttribute('data-account-invitation-flow') && ['openInvitationModal', 'requestInvitationRevocation'].includes(name));
        });
        if (!dialog() && !opening) return;
        if (!dialog()) {
            const active = document.activeElement;
            origins.set(message.component, { element: active, action: active?.getAttribute('wire:click') });
        }
        const setBusy = (busy) => dialog()?.dispatchEvent(new CustomEvent('swap-decision-busy', { detail: busy, bubbles: true }));
        const failure = (expired = false) => {
            const alert = dialog()?.querySelector('[data-decision-request-error]');
            if (!alert) return;
            alert.textContent = expired
                ? 'A sessão expirou. Atualize a página e confira o estado do registro antes de decidir.'
                : 'Não foi possível confirmar a resposta. Feche e abra a análise para conferir o estado do registro antes de tentar novamente.';
            alert.hidden = false;
            alert.focus();
        };
        onSend(() => setBusy(true));
        if (hadDialog && [...message.actions].some((action) => ['reviewReallocation', 'returnToReallocation', 'requestDecision', 'returnToSwapAnalysis', 'returnToShiftForm', 'openSwapAnalysis', 'confirmDecision', 'confirmRevert', 'refreshReversion', 'analyzeAdministrativeCorrection'].includes(action.name ?? action.method))) {
            onSuccess(({ onRender }) => onRender(() => {
                requestAnimationFrame(() => {
                    const body = dialog()?.querySelector('.modal-body');
                    if (!body) return;
                    const decisionError = dialog()?.querySelector('[data-swap-decision-error]');
                    if (decisionError) {
                        decisionError.scrollIntoView({ block: 'nearest' });
                        decisionError.focus({ preventScroll: true });
                        return;
                    }
                    const invalidField = body.querySelector('[aria-invalid="true"]');
                    if (invalidField) {
                        invalidField.scrollIntoView({ block: 'nearest' });
                        invalidField.focus({ preventScroll: true });
                        return;
                    }
                    body.scrollTop = 0;
                    (body.querySelector('[x-ref="firstField"]') ?? body).focus({ preventScroll: true });
                });
            }));
        }
        onFinish(() => {
            setBusy(false);
            if (dialog()) return;
            const origin = origins.get(message.component);
            origins.delete(message.component);
            requestAnimationFrame(() => {
                const replacement = origin?.action && [...message.component.el.querySelectorAll('button')].find((button) => button.getAttribute('wire:click') === origin.action);
                const target = origin?.element?.isConnected ? origin.element : (replacement ?? message.component.el.querySelector('[data-decision-return], #attention-title'));
                target?.focus();
            });
        });
        onFailure(() => failure());
        onError(({ response, preventDefault }) => {
            preventDefault();
            failure(response.status === 419);
        });
    });
}
