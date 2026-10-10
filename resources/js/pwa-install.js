let initialized = false;
let deferredPrompt = null;
let installing = false;
let feedback = '';
let installationCompleted = false;

export function initPwaInstall() {
    if (initialized) return;
    initialized = true;
    const displayMode = window.matchMedia('(display-mode: standalone)');
    const installed = () => installationCompleted || displayMode.matches || navigator.standalone === true;
    const ios = () => /iPhone|iPad|iPod/.test(navigator.userAgent)
        || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);
    const update = () => {
        const canGuideIos = window.isSecureContext && 'serviceWorker' in navigator && ios() && !installed();
        document.querySelectorAll('[data-pwa-install]').forEach((button) => {
            button.hidden = installed() || (!deferredPrompt && !installing);
            button.disabled = installing;
        });
        document.querySelectorAll('[data-pwa-ios]').forEach((element) => { element.hidden = !canGuideIos; });
        document.querySelectorAll('[data-pwa-install-status]').forEach((element) => {
            element.textContent = feedback;
            element.hidden = installed() || !feedback;
        });
        document.querySelectorAll('[data-pwa-support]').forEach((element) => {
            element.hidden = installed() || (!deferredPrompt && !installing && !canGuideIos && !feedback);
        });
    };
    window.addEventListener('beforeinstallprompt', (event) => {
        event.preventDefault();
        deferredPrompt = event;
        feedback = '';
        update();
    });
    window.addEventListener('appinstalled', () => {
        installationCompleted = true;
        deferredPrompt = null;
        feedback = '';
        update();
    });
    displayMode.addEventListener('change', update);
    document.addEventListener('livewire:navigated', update);
    document.addEventListener('DOMContentLoaded', update, { once: true });
    document.addEventListener('click', async (event) => {
        const button = event.target.closest?.('[data-pwa-install]');
        if (!button || !deferredPrompt || installing || installed()) return;
        const prompt = deferredPrompt;
        deferredPrompt = null;
        installing = true;
        feedback = '';
        update();
        try {
            await prompt.prompt();
            await prompt.userChoice;
        } catch {
            feedback = 'Não foi possível abrir a instalação. Você pode continuar pelo navegador.';
        } finally {
            installing = false;
            update();
        }
    });
    update();
}
