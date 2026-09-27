import './bootstrap';

import { Livewire } from '../../vendor/livewire/livewire/dist/livewire.esm';

Livewire.start();

let deferredInstallPrompt = null;

const isRunningAsInstalledApp = () => window.matchMedia('(display-mode: standalone)').matches
    || window.navigator.standalone === true;

const updateInstallButtons = () => {
    document.querySelectorAll('[data-pwa-install]').forEach((button) => {
        button.hidden = deferredInstallPrompt === null || isRunningAsInstalledApp();
    });
};

window.addEventListener('beforeinstallprompt', (event) => {
    event.preventDefault();
    deferredInstallPrompt = event;
    updateInstallButtons();
});

window.addEventListener('appinstalled', () => {
    deferredInstallPrompt = null;
    updateInstallButtons();
});

document.addEventListener('click', async (event) => {
    const installButton = event.target.closest('[data-pwa-install]');

    if (! installButton || deferredInstallPrompt === null) {
        return;
    }

    installButton.disabled = true;
    await deferredInstallPrompt.prompt();
    await deferredInstallPrompt.userChoice;
    deferredInstallPrompt = null;
    installButton.disabled = false;
    updateInstallButtons();
});

document.addEventListener('livewire:navigated', updateInstallButtons);

if ('serviceWorker' in navigator) {
    window.addEventListener('load', () => {
        navigator.serviceWorker.register('/sw.js', { scope: '/' }).catch(() => {
            // A aplicação continua funcional quando o navegador bloqueia service workers.
        });
    }, { once: true });
}
