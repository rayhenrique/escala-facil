<a
    href="{{ route('notifications') }}"
    wire:navigate
    wire:poll.30s
    title="Notificações{{ $unreadCount ? ' — '.$unreadCount.' não lida(s)' : '' }}"
    class="sidebar-nav-link {{ request()->routeIs('notifications') ? 'sidebar-nav-link-active' : '' }}"
    @if(request()->routeIs('notifications')) aria-current="page" @endif
    data-unread-count="{{ $unreadCount }}"
    x-init="if (typeof window.syncAppBadge === 'function') window.syncAppBadge({{ $unreadCount }})"
    x-effect="if (typeof window.syncAppBadge === 'function') window.syncAppBadge({{ $unreadCount }})"
>
    <span class="relative shrink-0">
        <x-nav-icon name="bell" class="sidebar-nav-icon" />
        @if ($unreadCount)
            <span class="notification-indicator" aria-hidden="true"></span>
        @endif
    </span>
    <span class="sidebar-label min-w-0 flex-1">Notificações</span>
    @if ($unreadCount)
        <span class="notification-count" aria-label="{{ $unreadCount }} notificação(ões) não lida(s)">{{ min($unreadCount, 99) }}</span>
    @endif
</a>
