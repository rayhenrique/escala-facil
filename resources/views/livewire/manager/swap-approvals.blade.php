<div>
    <p class="eyebrow">Gerência</p>
    <h1 class="page-title">Trocas</h1>
    <p class="page-description">Analise os pedidos pendentes e consulte as trocas de qualquer período.</p>

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

    @if ($request !== null && $this->from !== 'swaps')
        <a href="{{ $this->from === 'swaps' ? $this->backNavigation()['url'] : route('manager.swaps', array_diff_key($this->navigationContext(), ['request' => true])) }}" wire:navigate class="text-link mt-4 inline-flex min-h-10 items-center">Ver lista de trocas</a>
    @endif

    @unless ($focusedSwap)
        <nav class="mt-5 flex flex-wrap gap-2" aria-label="Consulta de trocas">
            <button type="button" wire:click="showPending" aria-pressed="{{ $listView === 'pending' ? 'true' : 'false' }}" class="{{ $listView === 'pending' ? 'button-primary' : 'button-secondary' }}">Aguardando decisão</button>
            <button type="button" wire:click="showHistory" aria-pressed="{{ $listView === 'history' ? 'true' : 'false' }}" class="{{ $listView === 'history' ? 'button-primary' : 'button-secondary' }}">Histórico de trocas</button>
        </nav>
        @error('listView')<p class="field-error" role="alert">{{ $message }}</p>@enderror
    @endunless
    @unless ($focusedSwap)
        <x-operational-filters kind="swaps" advanced-swaps />
        <p class="mt-4 text-sm text-muted" role="status">{{ $swaps->total() }} pedido(s) encontrado(s).</p>
    @endunless

    @if ($swapReceipt)
        <x-swap-receipt :receipt="$swapReceipt" class="mt-6" />
    @endif

    <section class="mt-8 space-y-4" aria-labelledby="swap-list-title">
        <div class="section-heading">
            <div>
                <h2 id="swap-list-title" class="text-xl font-semibold" tabindex="-1">{{ $focusedSwap ? 'Detalhes do pedido #'.$focusedSwap->id : ($listView === 'pending' ? 'Aguardando decisão' : 'Histórico de trocas') }}</h2>
                <p class="mt-2 text-sm text-muted">{{ $focusedSwap ? 'Dados do pedido e atribuições atuais dos plantões. Situação: '.$focusedSwap->status->label().'.' : ($listView === 'pending' ? 'Pedidos aceitos pelo substituto que aguardam decisão da gerência.' : 'Consulte pedidos de qualquer data e situação usando os filtros acima.') }}</p>
            </div>
        </div>
        @forelse ($swaps as $swap)
            <x-manager-swap-card :swap="$swap" :context="$this->navigationContext()" :selected="$request === $swap->id" :revert-permission="$revertPermissions[$swap->id] ?? null" :double-shift="$doubleShifts[$swap->id] ?? null" />
        @empty
            <div class="empty-state"><p class="font-semibold text-ink">Nenhuma troca corresponde aos filtros.</p><p class="mt-2 text-sm">{{ $listView === 'pending' ? 'Não há pedidos aguardando decisão com estes filtros. Consulte o Histórico de trocas para encontrar outros pedidos.' : 'Não há pedidos neste recorte. Ajuste a pesquisa ou limpe os filtros para consultar todo o histórico.' }}</p></div>
        @endforelse
    </section>
    @unless ($focusedSwap)<div class="mt-6">{{ $swaps->links() }}</div>@endunless
    @if ($focusedReceipt)
        <details class="panel mt-6">
            <summary class="min-h-10 cursor-pointer font-semibold text-primary">Consultar comprovante da situação atual</summary>
            <p class="mt-2 text-sm text-muted">Esta consulta usa a situação atual do pedido. Os comprovantes enviados nas notificações anteriores permanecem preservados.</p>
            <x-swap-receipt :receipt="$focusedReceipt" class="mt-4" />
        </details>
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
            @if ($pendingRevertSummary['counterpart'])<p>Plantão de contrapartida: <strong>{{ $pendingRevertSummary['counterpart'] }}</strong>. Voltará ao substituto deste pedido.</p>@endif
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
