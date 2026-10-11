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
            <p class="text-xs text-ink">Ative para receber avisos imediatos na tela do seu aparelho quando houver pedidos ou respostas de troca.</p>
        </div>
        <button type="button" @click="requestNotification" class="button-primary min-h-9 px-3.5 py-1.5 text-xs shrink-0">
            Ativar avisos no aparelho
        </button>
    </div>

    <x-loading-state wire:loading.flex.delay wire:target="gotoPage, nextPage, previousPage" class="mt-4" label="Carregando notificações…" />
    <section class="mt-6 space-y-3" aria-label="Histórico de notificações">
        @forelse ($notifications as $notification)
            @php
                $receipt = $notification->data['receipt'] ?? null;
                $swapId = \App\Support\ShiftSwapReceipt::swapId($receipt);
                $currentSwap = $swapId ? (($swapStatuses ?? [])[$swapId] ?? null) : null;
            @endphp
            <article data-notification-swap="{{ $swapId }}" class="notification-card {{ $notification->read_at ? '' : 'notification-card-unread' }}" wire:key="notification-{{ $notification->id }}" x-data="{ expanded: false }">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <div class="flex flex-wrap items-center gap-2">
                        @unless ($notification->read_at)<span class="status-badge status-pending_professional">Nova · não lida</span>@endunless
                        <time class="font-mono text-xs text-muted" datetime="{{ $notification->created_at->toIso8601String() }}">{{ $notification->created_at->format('d/m/Y H:i') }}</time>
                    </div>
                    @if (is_array($receipt))
                        <div class="flex flex-wrap items-center gap-2" data-current-swap-status role="group" aria-label="Situação atual da troca: {{ $currentSwap['status_label'] ?? 'Indisponível para consulta' }}">
                            <span class="text-xs text-muted">Situação atual</span>
                            @if ($currentSwap)
                                <x-status-badge :status="$currentSwap['status']" :label="$currentSwap['status_label']" />
                            @else
                                <span class="text-sm font-semibold">Indisponível para consulta</span>
                            @endif
                        </div>
                    @endif
                </div>
                <div class="mt-3 flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-muted">
                    <span>{{ $receipt['event_label'] ?? 'Aviso' }} · evento histórico @if (is_array($receipt)) · {{ empty($receipt['has_counterpart']) ? 'Substituição' : 'Troca mútua' }}@endif</span>
                    @if ($swapId)<span class="font-mono font-semibold text-primary">{{ $receipt['reference'] ?? sprintf('TROCA-%06d', $swapId) }}</span>@endif
                </div>
                <h2 class="mt-1 text-base font-semibold text-ink">{{ $notification->data['title'] }}</h2>
                @if (is_array($receipt))
                    @foreach (\App\Support\ShiftSwapReceipt::compactSummary($receipt) as $line)
                        <p class="mt-1 text-sm leading-5 text-muted">{{ $line }}</p>
                    @endforeach
                @else
                    <p class="mt-1 text-sm leading-5 text-muted">{{ $notification->data['message'] }}</p>
                @endif
                <div class="mt-3 flex flex-wrap items-center gap-2">
                    <button type="button" id="notification-toggle-{{ $notification->id }}" x-ref="detailsToggle"
                        @click="expanded = !expanded" aria-expanded="false" :aria-expanded="expanded.toString()"
                        aria-controls="notification-details-{{ $notification->id }}" class="button-secondary min-h-10 px-3 py-2 text-xs"
                        :aria-label="(expanded ? 'Recolher detalhes' : 'Ver detalhes') + ' — {{ $swapId ? sprintf('TROCA-%06d', $swapId) : 'notificação' }}'">
                        <span x-text="expanded ? 'Recolher detalhes' : 'Ver detalhes'">Ver detalhes</span>
                        <svg class="size-4 transition-transform" :class="expanded && 'rotate-180'" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path d="m6 9 6 6 6-6" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" /></svg>
                    </button>
                    @unless ($notification->read_at)
                        <button type="button" wire:click="markAsRead('{{ $notification->id }}')" wire:loading.attr="disabled" wire:target="markAsRead('{{ $notification->id }}')" class="button-secondary min-h-10 px-3 py-2 text-xs">
                            <span wire:loading.remove wire:target="markAsRead('{{ $notification->id }}')">Marcar como lida</span>
                            <span wire:loading wire:target="markAsRead('{{ $notification->id }}')">Marcando…</span>
                        </button>
                    @endunless
                    <a href="{{ $notification->data['target_url'] }}" wire:navigate class="button-secondary min-h-10 px-3 py-2 text-xs">Abrir módulo</a>
                </div>
                <div id="notification-details-{{ $notification->id }}" role="region" aria-labelledby="notification-toggle-{{ $notification->id }}"
                    x-show="expanded" x-cloak x-transition.opacity.duration.150ms class="mt-4 border-t border-line pt-4" data-notification-details>
                    <p class="meta-label">Evento histórico desta notificação</p>
                    <p class="mt-2 text-sm leading-6 text-muted">{{ $notification->data['message'] }}</p>
                    @if (is_array($receipt))
                        @if ($currentSwap)
                            @if (! empty($currentSwap['manager']))<p class="mt-2 text-sm text-muted">Responsável pela decisão atual: {{ $currentSwap['manager'] }}</p>@endif
                            @if (! empty($currentSwap['rejection_reason']))<p class="mt-1 text-sm text-muted">Justificativa da decisão atual: {{ $currentSwap['rejection_reason'] }}</p>@endif
                        @else
                            <p class="mt-2 text-sm text-muted">O registro histórico permanece disponível. A situação atual não pode ser consultada nesta conta.</p>
                        @endif
                        <x-swap-receipt :receipt="$receipt" historical :allow-official-pdf="$currentSwap !== null" class="mt-4" />
                    @endif
                    <button type="button" @click="expanded = false; $refs.detailsToggle.focus()" class="button-secondary mt-3 min-h-10 px-3 py-2 text-xs">Recolher detalhes</button>
                </div>
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
