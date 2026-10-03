import './bootstrap';

import { Livewire } from '../../vendor/livewire/livewire/dist/livewire.esm';
import { initLandingPage } from './landing';

Livewire.start();

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initLandingPage);
} else {
    initLandingPage();
}

document.addEventListener('livewire:navigated', initLandingPage);

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

let lastNotifiedUnreadCount = null;

const showDeviceNotification = async (unreadCount) => {
    if (! ('Notification' in window) || Notification.permission !== 'granted') {
        return;
    }

    const title = 'Escala Fácil';
    const body = unreadCount === 1
        ? 'Você tem 1 nova notificação sobre sua escala.'
        : `Você tem ${unreadCount} novas notificações sobre sua escala.`;

    const options = {
        body,
        icon: '/icons/pwa-192x192.png',
        badge: '/icons/pwa-192x192.png',
        tag: 'escala-facil-notif',
        data: { url: '/notificacoes' },
    };

    try {
        if ('serviceWorker' in navigator) {
            const registration = await navigator.serviceWorker.ready;
            if (registration && 'showNotification' in registration) {
                await registration.showNotification(title, options);
                return;
            }
        }
        new Notification(title, options);
    } catch {
        // Ignora silenciosamente se o navegador bloquear notificações em segundo plano.
    }
};

export const syncAppBadge = (count) => {
    const numericCount = parseInt(count, 10) || 0;

    if ('setAppBadge' in navigator) {
        if (numericCount > 0) {
            navigator.setAppBadge(numericCount).catch(() => {});
        } else {
            navigator.clearAppBadge().catch(() => {});
        }
    }

    if (lastNotifiedUnreadCount !== null && numericCount > lastNotifiedUnreadCount) {
        showDeviceNotification(numericCount);
    }
    lastNotifiedUnreadCount = numericCount;
};

export const requestDeviceNotificationPermission = async () => {
    if (! ('Notification' in window)) {
        return 'unsupported';
    }

    try {
        const permission = await Notification.requestPermission();
        if (permission === 'granted') {
            const countEl = document.querySelector('[data-unread-count]');
            const count = countEl ? parseInt(countEl.getAttribute('data-unread-count'), 10) : 0;
            if (count > 0) {
                showDeviceNotification(count);
            }
        }
        return permission;
    } catch {
        return 'denied';
    }
};

window.syncAppBadge = syncAppBadge;
window.requestDeviceNotificationPermission = requestDeviceNotificationPermission;

