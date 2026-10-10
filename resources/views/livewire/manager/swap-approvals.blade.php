<div>
    <p class="eyebrow">Gerência</p>
    <h1 class="page-title">Aprovação de trocas</h1>
    <p class="page-description">Confira os participantes e transfira o plantão somente após o aceite do substituto.</p>

    <x-flash-notifications />

    @php
        $contextLinks = [];
        if ($focusedSwap) {
            $contextLinks = [
                'Abrir plantão' => \App\Support\OperationalNavigation::url('shifts', ['focus' => $focusedSwap->shift_id], 'swaps', $this->navigationContext()),
                'Ver no calendário' => \App\Support\OperationalNavigation::url('calendar', ['month' => $focusedSwap->shift->date->format('Y-m'), 'selectedDate' => $focusedSwap->shift->date->format('Y-m-d'), 'sectorId' => $focusedSwap->shift->sector_id], 'swaps', $this->navigationContext()),
                'Ver relatório' => \App\Support\OperationalNavigation::url('reports', ['month' => $focusedSwap->created_at->format('Y-m'), 'tab' => 'swaps', 'sectorId' => $focusedSwap->shift->sector_id], 'swaps', $this->navigationContext()),
            ];
            if ($focusedSwap->substitute_shift_id) $contextLinks['Abrir contrapartida'] = \App\Support\OperationalNavigation::url('shifts', ['focus' => $focusedSwap->substitute_shift_id], 'swaps', $this->navigationContext());
        }
    @endphp
    <x-context-navigation :back="$this->backNavigation()" :label="$focusedSwap ? 'Pedido #'.$focusedSwap->id.' selecionado' : null" :links="$contextLinks" />

    @if ($request !== null)
        <a href="{{ route('manager.swaps') }}" wire:navigate class="text-link mt-4 inline-flex min-h-10 items-center">Ver lista de trocas</a>
    @endif

    @if ($swapReceipt)
        <x-swap-receipt :receipt="$swapReceipt" class="mt-6" />
    @endif

    <section class="mt-8 space-y-4" aria-label="Trocas aguardando decisão">
        @forelse ($swaps as $swap)
            <article class="panel {{ $request === $swap->id ? 'context-record' : '' }}" data-context-record="{{ $request === $swap->id ? 'swap-'.$swap->id : '' }}" wire:key="swap-approval-{{ $swap->id }}">
                <div class="flex flex-col gap-6 lg:flex-row lg:items-center lg:justify-between">
                    <div class="min-w-0">
                        <div class="flex flex-wrap items-center gap-3">
                            <x-status-badge :status="$swap->status" :label="$swap->status === \App\Enums\ShiftSwapStatus::PendingManager ? 'Aguardando decisão' : $swap->status->label()" />
                            @if (! empty($doubleShifts[$swap->id]))
                                <x-status-badge status="double_shift" label="Dobra" />
                            @endif
                            <span class="font-mono text-xs text-muted">Pedido #{{ $swap->id }}</span>
                        </div>
                        <h2 class="mt-4 text-xl font-semibold">{{ $swap->shift->date->format('d/m/Y') }} · {{ $swap->shift->time_label }}</h2>
                        <p class="mt-2 text-sm text-muted">{{ $swap->shift->sector->name }} · {{ $swap->shift->category->name }}</p>

                        @if ($swap->substituteShift)
                            <div class="mt-3 rounded-lg border border-line bg-canvas p-3 text-xs leading-5">
                                <p class="font-semibold text-primary">Troca mútua (permuta de plantões):</p>
                                <p class="mt-1">
                                    • <strong>{{ $swap->requester->name }}</strong> vai para o dia <strong>{{ $swap->substituteShift->date->format('d/m/Y') }}</strong>, das {{ substr($swap->substituteShift->start_time, 0, 5) }} às {{ $swap->substituteShift->period()->endLabel() }} · {{ $swap->substituteShift->period()->durationLabel() }} ({{ $swap->substituteShift->sector->name }}).
                                </p>
                                <p>
                                    • <strong>{{ $swap->substitute->name }}</strong> vai para o dia <strong>{{ $swap->shift->date->format('d/m/Y') }}</strong>, das {{ substr($swap->shift->start_time, 0, 5) }} às {{ $swap->shift->period()->endLabel() }} · {{ $swap->shift->period()->durationLabel() }} ({{ $swap->shift->sector->name }}).
                                </p>
                            </div>
                        @else
                            <div class="mt-3 rounded-lg border border-line bg-canvas p-3 text-xs leading-5">
                                <p class="font-semibold text-primary">Cobertura de plantão:</p>
                                <p class="mt-1">
                                    • <strong>{{ $swap->substitute->name }}</strong> vai substituir <strong>{{ $swap->requester->name }}</strong> no dia <strong>{{ $swap->shift->date->format('d/m/Y') }}</strong>, das {{ substr($swap->shift->start_time, 0, 5) }} às {{ $swap->shift->period()->endLabel() }} · {{ $swap->shift->period()->durationLabel() }}.
                                </p>
                            </div>
                        @endif

                        @if (! empty($doubleShifts[$swap->id]))
                            <p class="mt-2 text-sm font-semibold text-amber-700">Dobra: emenda com o plantão do substituto em {{ $doubleShifts[$swap->id] }}.</p>
                        @endif
                        <div class="mt-5 grid gap-4 sm:grid-cols-2">
                            <div><span class="meta-label">Solicitante (cedente)</span><p class="mt-1 font-semibold">{{ $swap->requester->name }}</p></div>
                            <div><span class="meta-label">Substituto (assume)</span><p class="mt-1 font-semibold">{{ $swap->substitute->name }}</p></div>
                        </div>
                    </div>
                    @if ($swap->status === \App\Enums\ShiftSwapStatus::PendingManager)
                    <div class="flex shrink-0 flex-col gap-2 sm:flex-row">
                        <button type="button" wire:click="requestDecision({{ $swap->id }}, 'reject')" wire:loading.attr="disabled" class="button-secondary">Rejeitar</button>
                        <button type="button" wire:click="requestDecision({{ $swap->id }}, 'approve')" wire:loading.attr="disabled" class="button-primary inline-flex">Aprovar e transferir</button>
                    </div>
                    @else
                        <p class="text-sm text-muted">Este pedido está {{ mb_strtolower($swap->status->label()) }}. A consulta não altera sua situação.</p>
                    @endif
                </div>
            </article>
        @empty
            <div class="empty-state"><p class="font-semibold text-ink">Nenhuma troca aguarda aprovação.</p><p class="mt-2 text-sm">Novos pedidos aparecerão aqui após o aceite do substituto.</p></div>
        @endforelse
    </section>

    <div class="mt-6">{{ $swaps->links() }}</div>

    @if ($request === null)
    <section class="mt-10" aria-labelledby="approved-swaps-title">
        <div class="section-heading">
            <div>
                <p class="eyebrow">Histórico reversível</p>
                <h2 id="approved-swaps-title">Trocas aprovadas futuras</h2>
            </div>
            <span class="text-sm text-muted">{{ $approvedSwaps->count() }} troca(s)</span>
        </div>
        <div class="mt-4 space-y-4">
            @forelse ($approvedSwaps as $swap)
                <article class="record-card" wire:key="swap-approved-{{ $swap->id }}">
                    <div class="flex flex-wrap items-center gap-3">
                        <x-status-badge :status="$swap->status" />
                        <span class="font-mono text-xs text-muted">Pedido #{{ $swap->id }}</span>
                    </div>
                    <h3 class="mt-2 font-semibold">
                        {{ $swap->shift->date->format('d/m/Y') }} · {{ $swap->shift->time_label }}
                        @if ($swap->substituteShift)
                            ⇄ {{ $swap->substituteShift->date->format('d/m/Y') }} · {{ $swap->substituteShift->time_label }}
                        @endif
                    </h3>
                    <p class="mt-1 text-sm text-muted">
                        {{ $swap->requester->name }} ⇄ {{ $swap->substitute->name }} · {{ $swap->shift->sector->name }}
                    </p>
                    <div class="mt-3">
                        <button type="button" wire:click="requestRevert({{ $swap->id }})" class="table-action text-danger">Desfazer troca</button>
                    </div>
                </article>
            @empty
                <div class="empty-state compact-empty"><p class="font-semibold text-ink">Nenhuma troca aprovada futura para desfazer.</p></div>
            @endforelse
        </div>
    </section>
    @endif

    @include('livewire.manager.partials.swap-analysis')

    <x-confirmation-modal
        :show="$pendingRevertSwapId !== null"
        id="revert-swap"
        title="Desfazer esta troca?"
        close-action="cancelRevert"
        tone="danger"
    >
        @if ($pendingRevertSummary)
            <p><strong>{{ $pendingRevertSummary['shift'] }}</strong></p>
            <p>{{ $pendingRevertSummary['location'] }}</p>
            <p>Solicitante: <strong>{{ $pendingRevertSummary['requester'] }}</strong><br>Substituto atual: <strong>{{ $pendingRevertSummary['substitute'] }}</strong></p>
            <p>O plantão voltará ao solicitante, a troca contará como desfeita (liberando a cota mensal) e ambos receberão um comprovante.</p>
        @endif
        <x-slot:actions>
            <button type="button" wire:click="cancelRevert" class="button-secondary w-full sm:w-auto" data-confirm-cancel>Manter troca</button>
            <button type="button" wire:click="confirmRevert" wire:loading.attr="disabled" wire:target="confirmRevert" class="button-danger w-full sm:w-auto">
                <span wire:loading.remove wire:target="confirmRevert">Confirmar desfazer</span>
                <span wire:loading wire:target="confirmRevert">Desfazendo…</span>
            </button>
        </x-slot:actions>
    </x-confirmation-modal>
</div>
