<div>
    <div class="flex flex-col gap-5 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <p class="eyebrow">Acompanhamento</p>
            <h1 class="page-title">Notificações</h1>
            <p class="page-description">Acompanhe solicitações, aceites e decisões com o comprovante de cada movimentação.</p>
        </div>
        @if ($unreadCount)
            <button type="button" wire:click="requestMarkAllAsRead" wire:loading.attr="disabled" class="button-secondary w-full sm:w-auto">
                Marcar todas como lidas
            </button>
        @endif
    </div>

    <x-flash-notifications />

    <div class="mt-6 flex items-center gap-3" aria-live="polite">
        <span class="count-badge">{{ $unreadCount }}</span>
        <p class="text-sm text-muted">{{ $unreadCount === 1 ? 'notificação não lida' : 'notificações não lidas' }}</p>
    </div>

    <div
        x-data="{
            supported: 'Notification' in window,
            permission: 'Notification' in window ? Notification.permission : 'denied',
            requestNotification() {
                if (typeof window.requestDeviceNotificationPermission === 'function') {
                    window.requestDeviceNotificationPermission().then(p => this.permission = p);
                }
            }
        }"
        x-show="supported && permission === 'default'"
        x-cloak
        class="mt-6 flex flex-col gap-3 rounded-xl border border-primary/20 bg-primary-soft/50 p-4 sm:flex-row sm:items-center sm:justify-between"
    >
        <div>
            <p class="text-sm font-semibold text-ink">Notificações no celular ou computador</p>
            <p class="text-xs text-muted">Ative para receber avisos imediatos na tela do seu aparelho quando houver pedidos ou respostas de troca.</p>
        </div>
        <button type="button" @click="requestNotification" class="button-primary min-h-9 px-3.5 py-1.5 text-xs shrink-0">
            Ativar avisos no aparelho
        </button>
    </div>

    <section class="mt-8 space-y-4" aria-label="Histórico de notificações">
        @forelse ($notifications as $notification)
            @php
                $receipt = $notification->data['receipt'] ?? null;
                $swapId = is_array($receipt) ? ($receipt['shift_swap_id'] ?? null) : null;
                if (! is_numeric($swapId) && is_array($receipt) && ! empty($receipt['reference']) && preg_match('/(\d+)/', (string) $receipt['reference'], $swapMatches)) {
                    $swapId = (int) $swapMatches[1];
                }
                $swapId = is_numeric($swapId) ? (int) $swapId : null;
                $currentSwap = $swapId ? (($swapStatuses ?? [])[$swapId] ?? null) : null;
                $recordedStatus = is_array($receipt) ? ($receipt['status'] ?? null) : null;
                $showsCurrentStatus = $currentSwap && $recordedStatus && $currentSwap['status'] !== $recordedStatus;
            @endphp
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

                @if ($showsCurrentStatus)
                    <div class="mt-4 rounded-xl border border-primary/20 bg-primary-soft/40 p-4" role="status" aria-live="polite">
                        <p class="text-sm font-semibold text-ink">Situação atual da troca: {{ $currentSwap['status_label'] }}</p>
                        @if (! empty($currentSwap['manager']))
                            <p class="mt-1 text-xs text-muted">Responsável pela decisão: {{ $currentSwap['manager'] }}</p>
                        @endif
                        @if (! empty($currentSwap['rejection_reason']))
                            <p class="mt-1 text-xs text-muted">Justificativa: {{ $currentSwap['rejection_reason'] }}</p>
                        @endif
                        <p class="mt-2 text-xs text-muted">Este comprovante preserva o evento original.</p>
                    </div>
                @endif

                @if (is_array($receipt))
                    <x-swap-receipt :receipt="$receipt" class="mt-5" />
                @endif
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

    <x-confirmation-modal :show="$showMarkAllConfirmation" id="mark-all-read" title="Marcar todas como lidas?" close-action="cancelMarkAllAsRead" tone="primary">
        <p>As <strong>{{ $unreadCount }} notificações não lidas</strong> continuarão no histórico, mas deixarão de aparecer no contador da navegação.</p>
        <x-slot:actions>
            <button type="button" wire:click="cancelMarkAllAsRead" class="button-secondary w-full sm:w-auto" data-confirm-cancel>Cancelar</button>
            <button type="button" wire:click="markAllAsRead" wire:loading.attr="disabled" wire:target="markAllAsRead" class="button-primary w-full sm:w-auto">Confirmar leitura</button>
        </x-slot:actions>
    </x-confirmation-modal>
</div>
