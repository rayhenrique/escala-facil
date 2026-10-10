import './bootstrap';

import { Livewire } from '../../vendor/livewire/livewire/dist/livewire.esm';
import { initLandingPage } from './landing';
import { registerAuthUi } from './auth';
import { initPwaInstall } from './pwa-install';
import { initOperationalNavigation } from './operational-navigation';

registerAuthUi(Livewire);
initPwaInstall();
document.addEventListener('livewire:navigated', initOperationalNavigation);
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initOperationalNavigation);
} else {
    initOperationalNavigation();
}
Livewire.start();

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initLandingPage);
} else {
    initLandingPage();
}

document.addEventListener('livewire:navigated', initLandingPage);

if ('serviceWorker' in navigator) {
    window.addEventListener('load', () => {
        navigator.serviceWorker.register('/sw.js', { scope: '/', updateViaCache: 'none' }).catch(() => {
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
        data: { url: '/notifications' },
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

export const printSwapReceipt = (triggerElement) => {
    if (typeof window.printSwapReceipt === 'function') {
        window.printSwapReceipt(triggerElement);
    }
};

window.syncAppBadge = syncAppBadge;
window.requestDeviceNotificationPermission = requestDeviceNotificationPermission;

const initErrorReporting = () => {
    if (! document.body.hasAttribute('data-error-reporting') || window.__escalaFacilErrorReporting) {
        return;
    }
    window.__escalaFacilErrorReporting = true;

    let sentCount = 0;
    const maxPerLoad = 5;

    const sameOriginPath = (value) => {
        try {
            const url = new URL(String(value), window.location.origin);
            if (url.origin !== window.location.origin) {
                return '';
            }
            return url.pathname.slice(0, 255);
        } catch {
            return '';
        }
    };

    const sendClientError = (message, source, line, column) => {
        if (sentCount >= maxPerLoad) {
            return;
        }
        sentCount += 1;

        try {
            const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
            const payload = new FormData();
            payload.append('_token', token);
            payload.append('message', String(message ?? 'Erro JavaScript sem mensagem').slice(0, 500));
            payload.append('source', String(source ?? '').slice(0, 255));
            payload.append('page', window.location.pathname.slice(0, 500));
            if (Number.isInteger(line)) {
                payload.append('line', String(line));
            }
            if (Number.isInteger(column)) {
                payload.append('column', String(column));
            }

            if (! navigator.sendBeacon('/support/client-error', payload)) {
                fetch('/support/client-error', { method: 'POST', body: payload, keepalive: true }).catch(() => {});
            }
        } catch {
            // A captura de erros nunca pode quebrar a aplicação.
        }
    };

    window.addEventListener('error', (event) => {
        if (event.filename && sameOriginPath(event.filename) === '' && event.message === 'Script error.') {
            return;
        }
        sendClientError(event.message, sameOriginPath(event.filename || ''), event.lineno, event.colno);
    });

    window.addEventListener('unhandledrejection', (event) => {
        const reason = event.reason instanceof Error ? event.reason.message : event.reason;
        sendClientError(reason, '', undefined, undefined);
    });
};

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initErrorReporting);
} else {
    initErrorReporting();
}

document.addEventListener('livewire:navigated', initErrorReporting);

