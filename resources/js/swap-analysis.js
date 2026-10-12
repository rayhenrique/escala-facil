import { reportRequestFailure } from './operational-feedback.js';
// Feedback only: authorization, stale snapshots and deduplication stay in the Action.
export function registerSwapAnalysis(Livewire) {
    const origins = new WeakMap();
    const pending = new WeakMap();
    Livewire.interceptMessage(({ message, onSend, onFinish, onFailure, onError, onSuccess }) => {
        const dialog = () => message.component.el.querySelector('[data-decision-component]');
        const hadDialog = !!dialog();
        const opening = [...message.actions].some((action) => {
            const name = action.name ?? action.method;
            return ['openSwapAnalysis', 'requestDecision', 'openReallocation', 'reallocateCalendarDetail', 'requestRevert'].includes(name)
                || (message.component.el.hasAttribute('data-shift-assignment-flow') && ['openCreateModal', 'edit'].includes(name))
                || (message.component.el.hasAttribute('data-account-invitation-flow') && ['openInvitationModal', 'requestInvitationRevocation'].includes(name));
        });
        if (!dialog() && !opening) return;
        if (!dialog()) {
            const active = document.activeElement;
            origins.set(message.component, { element: active, action: active?.getAttribute('wire:click') });
        }
        const setBusy = (busy) => dialog()?.dispatchEvent(new CustomEvent('swap-decision-busy', { detail: busy, bubbles: true }));
        const failure = (expired = false) => { if (!globalThis.Alpine?.store?.('operationalFeedback')) reportRequestFailure(message.component, expired ? 419 : null, true); };
        onSend(() => {
            pending.set(message.component, (pending.get(message.component) ?? 0) + 1);
            setBusy(true);
        });
        if (hadDialog && [...message.actions].some((action) => ['reviewReallocation', 'returnToReallocation', 'requestDecision', 'returnToSwapAnalysis', 'returnToShiftForm', 'openSwapAnalysis', 'refreshSwapAnalysis', 'confirmDecision', 'confirmRevert', 'refreshReversion', 'analyzeAdministrativeCorrection'].includes(action.name ?? action.method))) {
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
            const count = Math.max(0, (pending.get(message.component) ?? 1) - 1);
            pending.set(message.component, count);
            setBusy(count > 0);
            if (dialog() || count > 0) return;
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
