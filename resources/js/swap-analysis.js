// Feedback only: authorization, stale snapshots and deduplication stay in the Action.
export function registerSwapAnalysis(Livewire) {
    const origins = new WeakMap();
    Livewire.interceptMessage(({ message, onSend, onFinish, onFailure, onError }) => {
        const dialog = () => message.component.el.querySelector('[data-decision-component]');
        const opening = [...message.actions].some((action) => ['openSwapAnalysis', 'requestDecision'].includes(action.name ?? action.method));
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
                ? 'A sessão expirou. Atualize a página e confira o estado do pedido antes de decidir.'
                : 'Não foi possível confirmar a resposta. Feche e abra a análise para conferir o estado do pedido antes de tentar novamente.';
            alert.hidden = false;
            alert.focus();
        };
        onSend(() => setBusy(true));
        onFinish(() => {
            setBusy(false);
            if (dialog()) return;
            const origin = origins.get(message.component);
            origins.delete(message.component);
            requestAnimationFrame(() => {
                const replacement = origin?.action && [...message.component.el.querySelectorAll('button')].find((button) => button.getAttribute('wire:click') === origin.action);
                const target = origin?.element?.isConnected ? origin.element : (replacement ?? message.component.el.querySelector('#attention-title'));
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
