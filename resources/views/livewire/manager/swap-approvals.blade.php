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
                            <span class="font-mono text-xs text-muted">Pedido #{{ $swap->id }}</span>
                        </div>
                        <h2 class="mt-4 text-xl font-semibold">{{ $swap->shift->date->format('d/m/Y') }} · {{ substr($swap->shift->start_time, 0, 5) }}–{{ substr($swap->shift->end_time, 0, 5) }}</h2>
                        <p class="mt-2 text-sm text-muted">{{ $swap->shift->sector->name }} · {{ $swap->shift->category->name }}</p>
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
</div>
