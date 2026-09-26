<div>
    <div class="flex flex-col gap-5 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <p class="eyebrow">Acompanhamento</p>
            <h1 class="page-title">Notificações</h1>
            <p class="page-description">Acompanhe solicitações, aceites e decisões com o comprovante de cada movimentação.</p>
        </div>
        @if ($unreadCount)
            <button type="button" wire:click="markAllAsRead" wire:loading.attr="disabled" class="button-secondary w-full sm:w-auto">
                Marcar todas como lidas
            </button>
        @endif
    </div>

    <div class="mt-6 flex items-center gap-3" aria-live="polite">
        <span class="count-badge">{{ $unreadCount }}</span>
        <p class="text-sm text-muted">{{ $unreadCount === 1 ? 'notificação não lida' : 'notificações não lidas' }}</p>
    </div>

    <section class="mt-8 space-y-4" aria-label="Histórico de notificações">
        @forelse ($notifications as $notification)
            <article class="notification-card {{ $notification->read_at ? '' : 'notification-card-unread' }}" wire:key="notification-{{ $notification->id }}">
                <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                    <div class="min-w-0">
                        <div class="flex flex-wrap items-center gap-2">
                            @unless ($notification->read_at)<span class="status-badge status-pending_professional">Nova</span>@endunless
                            <time class="font-mono text-xs text-muted" datetime="{{ $notification->created_at->toIso8601String() }}">{{ $notification->created_at->format('d/m/Y H:i') }}</time>
                        </div>
                        <h2 class="mt-3 text-lg font-semibold">{{ $notification->data['title'] }}</h2>
                        <p class="mt-2 text-sm leading-6 text-muted">{{ $notification->data['message'] }}</p>
                    </div>
                    <div class="flex shrink-0 flex-col gap-2 sm:flex-row">
                        @unless ($notification->read_at)
                            <button type="button" wire:click="markAsRead('{{ $notification->id }}')" wire:loading.attr="disabled" class="button-secondary">Marcar como lida</button>
                        @endunless
                        <a href="{{ $notification->data['target_url'] }}" wire:navigate class="button-secondary">Abrir módulo</a>
                    </div>
                </div>

                <x-swap-receipt :receipt="$notification->data['receipt']" class="mt-5" />
            </article>
        @empty
            <div class="empty-state">
                <x-nav-icon name="bell" class="mx-auto size-8 text-primary" />
                <p class="mt-4 font-semibold text-ink">Nenhuma notificação por enquanto.</p>
                <p class="mt-2 text-sm">Solicitações, aceites e decisões de troca aparecerão aqui.</p>
            </div>
        @endforelse
    </section>

    <div class="mt-6">{{ $notifications->links() }}</div>
</div>
