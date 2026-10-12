import { reportRequestFailure } from './operational-feedback.js';
// Apenas apresentação: autenticação, limites e sessão continuam no servidor.
export function registerAuthUi(Livewire) {
    Livewire.hook('component.init', ({ component, cleanup }) => {
        const form = component.el.querySelector('[data-auth-form]');
        if (!form) return;
        const listeners = new AbortController();
        const currentForm = () => component.el.querySelector('[data-auth-form]');
        const showFailure = (expired = false) => { if (!globalThis.Alpine?.store?.('operationalFeedback')) reportRequestFailure(component, expired ? 419 : null, true); };
        form.addEventListener('submit', () => {
            form.querySelector('[data-auth-request-error]').hidden = true;
            // Alguns gerenciadores preenchem sem emitir input/change. Sincroniza
            // os valores atuais antes do wire:submit, sem enviar outra requisição.
            form.querySelectorAll('input[wire\\:model]').forEach((input) => {
                component.$wire.$set(input.getAttribute('wire:model'), input.type === 'checkbox' ? input.checked : input.value, false);
            });
        }, { capture: true, signal: listeners.signal });
        form.addEventListener('click', (event) => {
            if (event.target.closest('[data-auth-reload]')) window.location.reload();
        }, { signal: listeners.signal });
        cleanup(component.$wire.$interceptRequest(({ onFailure, onError }) => {
            onFailure(() => showFailure());
            onError(({ response, preventDefault }) => {
                if (!component.el.isConnected) return;
                // A expiração continua exigindo recarga deliberada; nenhum
                // token, prazo ou política de sessão é alterado aqui.
                preventDefault();
                showFailure(response.status === 419);
            });
        }));
        // O registro global com filtro usa o descarte suportado pela versão
        // instalada; o descarte do interceptor por componente pode falhar.
        cleanup(Livewire.interceptMessage(({ message, onSuccess }) => {
            if (message.component !== component) return;
            onSuccess(({ onRender }) => onRender(() => {
                requestAnimationFrame(() => {
                    const invalid = currentForm()?.querySelector('[aria-invalid="true"]');
                    if (component.el.isConnected && invalid) invalid.focus();
                });
            }));
        }));
        cleanup(() => listeners.abort());
    });
}
