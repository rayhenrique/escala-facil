<div>
    <div class="flex flex-col gap-5 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <p class="eyebrow">Acompanhamento</p>
            <h1 id="notifications-title" tabindex="-1" class="page-title">Notificações</h1>
            <p class="page-description">Acompanhe solicitações, aceites e decisões com o comprovante de cada movimentação.</p>
        </div>
        @if ($unreadCount)
            <button type="button" wire:click="requestMarkAllAsRead" wire:loading.attr="disabled" class="button-secondary min-h-11 w-full sm:w-auto">
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
        class="mt-4 rounded-lg border border-line bg-white"
    >
        <details>
            <summary class="flex min-h-11 cursor-pointer items-center gap-2 px-3 py-2 text-sm font-semibold text-ink"><x-nav-icon name="bell" class="size-4 shrink-0" />Receber avisos neste aparelho</summary>
            <div class="space-y-3 border-t border-line p-3">
                <p class="text-sm text-muted">Ative para receber avisos de pedidos e respostas de troca neste aparelho.</p>
                <button type="button" @click="requestNotification" class="button-secondary min-h-11 w-full px-3 py-2 text-sm sm:w-auto">Ativar avisos no aparelho</button>
            </div>
        </details>
    </div>

    <div wire:loading.delay wire:target="gotoPage, nextPage, previousPage" class="mt-4"><x-loading-state label="Carregando notificações…" /></div>
    <section class="mt-6 space-y-3" aria-label="Histórico de notificações">
        @forelse ($notifications as $notification)
            @php
                $receipt = $notification->data['receipt'] ?? null;
                $swapId = \App\Support\ShiftSwapReceipt::swapId($receipt);
                $currentSwap = $swapId ? (($swapStatuses ?? [])[$swapId] ?? null) : null;
            @endphp
            <article data-notification-swap="{{ $swapId }}" class="notification-card {{ $notification->read_at ? '' : 'notification-card-unread' }}" wire:key="notification-{{ $notification->id }}" x-data="{ expanded: false, marking: false, readError: false,
                async markRead(id) {
                    if (this.marking) return;
                    this.marking = true;
                    this.readError = false;
                    try {
                        await this.$wire.markAsRead(id);
                        this.$nextTick(() => this.$refs.detailsToggle.focus());
                    } catch {
                        this.readError = true;
                        this.$nextTick(() => this.$refs.readError.focus());
                    } finally { this.marking = false; }
                }
            }">
                <div class="flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-center sm:justify-between">
                    <div class="flex flex-wrap items-center gap-2">
                        @unless ($notification->read_at)<span class="status-badge status-pending_professional">Nova · não lida</span>@endunless
                        <time class="font-mono text-xs text-muted" datetime="{{ $notification->created_at->toIso8601String() }}">{{ $notification->created_at->format('d/m/Y H:i') }}</time>
                    </div>
                    @if (is_array($receipt))
                        <div class="flex flex-wrap items-center justify-between gap-2 rounded-lg border border-line bg-canvas p-2.5 sm:justify-start" data-current-swap-status role="group" aria-label="Situação atual da troca: {{ $currentSwap['status_label'] ?? 'Indisponível para consulta' }}">
                            <span class="text-xs text-muted">Situação atual</span>
                            @if ($currentSwap)
                                <x-status-badge :status="$currentSwap['status']" :label="$currentSwap['status_label']" />
                            @else
                                <span class="text-sm font-semibold">Indisponível para consulta</span>
                            @endif
                        </div>
                    @endif
                </div>
                <div class="mt-3 space-y-1 text-xs text-muted">
                    <p class="flex items-start gap-1.5"><x-nav-icon name="clock" class="size-4 shrink-0" /><span class="min-w-0">{{ $receipt['event_label'] ?? 'Aviso' }} · evento histórico @if (is_array($receipt)) · {{ empty($receipt['has_counterpart']) ? 'Substituição' : 'Troca mútua' }}@endif</span></p>
                    @if ($swapId)<span class="block font-mono font-semibold text-primary">{{ $receipt['reference'] ?? sprintf('TROCA-%06d', $swapId) }}</span>@endif
                </div>
                <h2 class="mt-1 text-base font-semibold text-ink">{{ $notification->data['title'] }}</h2>
                @if (is_array($receipt))
                    @foreach (\App\Support\ShiftSwapReceipt::compactSummary($receipt) as $line)
                        <p class="mt-1 text-sm leading-6 text-muted">{{ $line }}</p>
                    @endforeach
                @else
                    <p class="mt-1 text-sm leading-6 text-muted">{{ $notification->data['message'] }}</p>
                @endif
                <div class="mt-3 flex flex-wrap items-center gap-2">
                    <button type="button" id="notification-toggle-{{ $notification->id }}" x-ref="detailsToggle"
                        @click="expanded = !expanded" aria-expanded="false" :aria-expanded="expanded.toString()"
                        aria-controls="notification-details-{{ $notification->id }}" class="button-secondary min-h-11 w-full gap-2 px-3 py-2 text-sm sm:w-auto"
                        :aria-label="(expanded ? 'Recolher detalhes' : 'Ver detalhes') + ' — {{ $swapId ? sprintf('TROCA-%06d', $swapId) : 'notificação' }}'">
                        <span x-text="expanded ? 'Recolher detalhes' : 'Ver detalhes'">Ver detalhes</span>
                        <svg class="size-4 transition-transform" :class="expanded && 'rotate-180'" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path d="m6 9 6 6 6-6" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" /></svg>
                    </button>

                </div>
                <div id="notification-details-{{ $notification->id }}" role="region" aria-labelledby="notification-toggle-{{ $notification->id }}"
                    x-show="expanded" x-cloak x-transition.opacity.duration.150ms class="mt-4 border-t border-line pt-4" data-notification-details>
                    <div class="mb-4 grid grid-cols-2 items-stretch gap-2 sm:flex sm:flex-wrap" data-notification-secondary-actions>
                        <button type="button" @click="markRead(@js($notification->id))" :disabled="marking || @js($notification->read_at !== null)" :aria-busy="marking.toString()" @disabled($notification->read_at) class="button-secondary min-h-11 gap-2 px-3 py-2 text-sm">
                            <x-nav-icon name="check-circle" class="size-4 shrink-0" />
                            <span x-show="!marking">{{ $notification->read_at ? 'Notificação lida' : 'Marcar como lida' }}</span>
                            <span x-show="marking" x-cloak>Marcando…</span>
                        </button>
                        <a href="{{ $notification->data['target_url'] }}" wire:navigate class="button-secondary min-h-11 px-3 py-2 text-sm">Abrir módulo</a>
                    </div>
                    <p x-show="readError" x-cloak x-ref="readError" tabindex="-1" role="alert" class="field-error mb-3">Não foi possível confirmar a leitura. Atualize a página para conferir o estado da notificação antes de tentar novamente.</p>
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
                    <button type="button" @click="expanded = false; $refs.detailsToggle.focus()" class="button-secondary mt-4 min-h-11 w-full gap-2 px-3 py-2 text-sm sm:w-auto">Recolher detalhes</button>
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

    <x-confirmation-modal :show="$showMarkAllConfirmation" id="mark-all-read" title="Marcar todas como lidas?" close-action="cancelMarkAllAsRead" tone="primary" return-focus="#notifications-title">
        <p>As <strong>{{ $unreadCount }} notificações não lidas</strong> continuarão no histórico, mas deixarão de aparecer no contador da navegação.</p>
        <x-slot:actions>
            <button type="button" wire:click="cancelMarkAllAsRead" x-bind:disabled="busy" class="button-secondary w-full sm:w-auto" data-confirm-cancel>Cancelar</button>
            <button type="button" wire:click="markAllAsRead" x-bind:disabled="busy" wire:loading.attr="disabled" wire:target="markAllAsRead" class="button-primary w-full sm:w-auto">Confirmar leitura</button>
        </x-slot:actions>
    </x-confirmation-modal>
</div>
