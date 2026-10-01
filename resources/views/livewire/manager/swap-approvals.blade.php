<div>
    <p class="eyebrow">Gerência</p>
    <h1 class="page-title">Aprovação de trocas</h1>
    <p class="page-description">Confira os participantes e transfira o plantão somente após o aceite do substituto.</p>

    <x-flash-notifications />

    @if ($swapReceipt)
        <x-swap-receipt :receipt="$swapReceipt" class="mt-6" />
    @endif

    <section class="mt-8 space-y-4" aria-label="Trocas aguardando decisão">
        @forelse ($swaps as $swap)
            <article class="panel" wire:key="swap-approval-{{ $swap->id }}">
                <div class="flex flex-col gap-6 lg:flex-row lg:items-center lg:justify-between">
                    <div class="min-w-0">
                        <div class="flex flex-wrap items-center gap-3">
                            <x-status-badge :status="$swap->status" label="Aguardando decisão" />
                            @if (! empty($doubleShifts[$swap->id]))
                                <x-status-badge status="double_shift" label="Dobra" />
                            @endif
                            <span class="font-mono text-xs text-muted">Pedido #{{ $swap->id }}</span>
                        </div>
                        <h2 class="mt-4 text-xl font-semibold">{{ $swap->shift->date->format('d/m/Y') }} · {{ substr($swap->shift->start_time, 0, 5) }}–{{ substr($swap->shift->end_time, 0, 5) }}</h2>
                        <p class="mt-2 text-sm text-muted">{{ $swap->shift->sector->name }} · {{ $swap->shift->category->name }}</p>
                        @if (! empty($doubleShifts[$swap->id]))
                            <p class="mt-2 text-sm font-semibold text-amber-700">Dobra: emenda com o plantão do substituto em {{ $doubleShifts[$swap->id] }}.</p>
                        @endif
                        <div class="mt-5 grid gap-4 sm:grid-cols-2">
                            <div><span class="meta-label">Solicitante</span><p class="mt-1 font-semibold">{{ $swap->requester->name }}</p></div>
                            <div><span class="meta-label">Substituto</span><p class="mt-1 font-semibold">{{ $swap->substitute->name }}</p></div>
                        </div>
                    </div>
                    <div class="flex shrink-0 flex-col gap-2 sm:flex-row">
                        <button type="button" wire:click="requestDecision({{ $swap->id }}, 'reject')" wire:loading.attr="disabled" class="button-secondary">Rejeitar</button>
                        <button type="button" wire:click="requestDecision({{ $swap->id }}, 'approve')" wire:loading.attr="disabled" class="button-primary inline-flex">Aprovar e transferir</button>
                    </div>
                </div>
            </article>
        @empty
            <div class="empty-state"><p class="font-semibold text-ink">Nenhuma troca aguarda aprovação.</p><p class="mt-2 text-sm">Novos pedidos aparecerão aqui após o aceite do substituto.</p></div>
        @endforelse
    </section>

    <div class="mt-6">{{ $swaps->links() }}</div>

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
                    <h3 class="mt-2 font-semibold">{{ $swap->shift->date->format('d/m/Y') }} · {{ substr($swap->shift->start_time, 0, 5) }}–{{ substr($swap->shift->end_time, 0, 5) }}</h3>
                    <p class="mt-1 text-sm text-muted">{{ $swap->requester->name }} → {{ $swap->substitute->name }} · {{ $swap->shift->sector->name }}</p>
                    <div class="mt-3">
                        <button type="button" wire:click="requestRevert({{ $swap->id }})" class="table-action text-danger">Desfazer troca</button>
                    </div>
                </article>
            @empty
                <div class="empty-state compact-empty"><p class="font-semibold text-ink">Nenhuma troca aprovada futura para desfazer.</p></div>
            @endforelse
        </div>
    </section>

    <x-confirmation-modal
        :show="$pendingDecisionSwapId !== null"
        id="decide-swap"
        :title="$pendingDecision === 'approve' ? 'Aprovar e transferir plantão?' : 'Rejeitar solicitação de troca?'"
        close-action="cancelDecision"
        :tone="$pendingDecision === 'approve' ? 'primary' : 'danger'"
    >
        @if ($pendingDecisionSummary)
            <p><strong>{{ $pendingDecisionSummary['shift'] }}</strong></p>
            <p>{{ $pendingDecisionSummary['location'] }}</p>
            <p>Solicitante: <strong>{{ $pendingDecisionSummary['requester'] }}</strong><br>Substituto: <strong>{{ $pendingDecisionSummary['substitute'] }}</strong></p>
            @if (! empty($pendingDecisionSummary['double_shift']))
                <p class="font-semibold text-amber-700">Dobra: o substituto emenda com o plantão de {{ $pendingDecisionSummary['double_shift'] }}. Aprove somente se a dobra for autorizada.</p>
            @endif
            <p>
                {{ $pendingDecision === 'approve'
                    ? 'O plantão será transferido ao substituto e ambos receberão um comprovante da decisão.'
                    : 'O plantão permanecerá com o solicitante e ambos receberão um comprovante da rejeição.' }}
            </p>
        @endif
        <x-slot:actions>
            <button type="button" wire:click="cancelDecision" class="button-secondary w-full sm:w-auto" data-confirm-cancel>Voltar</button>
            <button
                type="button"
                wire:click="confirmDecision"
                wire:loading.attr="disabled"
                wire:target="confirmDecision"
                class="{{ $pendingDecision === 'approve' ? 'button-primary' : 'button-danger' }} w-full sm:w-auto"
            >
                <span wire:loading.remove wire:target="confirmDecision">{{ $pendingDecision === 'approve' ? 'Confirmar aprovação' : 'Confirmar rejeição' }}</span>
                <span wire:loading wire:target="confirmDecision">Processando…</span>
            </button>
        </x-slot:actions>
    </x-confirmation-modal>

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
