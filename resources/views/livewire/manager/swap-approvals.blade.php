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
                <h2 id="swap-list-title" data-decision-return class="text-xl font-semibold" tabindex="-1">{{ $focusedSwap ? 'Detalhes do pedido #'.$focusedSwap->id : ($listView === 'pending' ? 'Aguardando decisão' : 'Histórico de trocas') }}</h2>
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

    <x-confirmation-modal :show="$pendingRevertSwapId !== null" id="revert-swap"
        :title="$administrativeAnalysis ? 'Análise de correção administrativa' : 'Revisar desfazimento do pedido #'.$pendingRevertSwapId"
        close-action="cancelRevert" tone="danger" busy-action="confirmRevert" trap-focus>
        @if ($pendingRevertSummary)
            <p class="font-semibold">Situação atual → Situação após desfazer</p>
            @foreach ($pendingRevertSummary['rows'] as $row)
                <div class="rounded-lg border border-line p-3">
                    <p><strong>Plantão #{{ $row['id'] }} · {{ $row['period'] }}</strong></p><p>{{ $row['location'] }}</p>
                    <dl class="mt-2 grid gap-2 sm:grid-cols-2">
                        <div><dt class="meta-label">Situação atual</dt><dd>{{ $row['current'] }} · {{ $row['current_status'] }}</dd></div>
                        <div><dt class="meta-label">Após desfazer, quando permitido</dt><dd>{{ $row['after'] }} · Agendado</dd></div>
                    </dl>
                </div>
            @endforeach
            @foreach ($pendingRevertSummary['blocks'] as $block)<p class="field-error">{{ $block }}</p>@endforeach
            @foreach ($pendingRevertSummary['warnings'] as $warning)<p class="text-sm text-ink">{{ $warning }}</p>@endforeach
            @if ($administrativeAnalysis)
                <div class="rounded-lg border border-line p-3">
                    <p class="font-semibold">Conferência administrativa separada</p>
                    <p>Compare a aprovação original, os comprovantes e as alterações posteriores. Identifique solicitações dependentes e confirme com os profissionais o que efetivamente ocorreu.</p>
                    <p>Esta análise é somente para consulta. Plantões passados, concluídos ou modificados precisam de uma correção documentada, conforme orientação institucional; esta tela não altera o histórico nem força atribuições.</p>
                    <a wire:navigate class="text-link" href="{{ \App\Support\OperationalNavigation::url('audit', ['entity' => 'swap', 'record' => $pendingRevertSwapId], 'swaps', $this->navigationContext()) }}">Consultar histórico do pedido</a>
                    @foreach ($pendingRevertSummary['rows'] as $row)
                        <a wire:navigate class="text-link block" href="{{ \App\Support\OperationalNavigation::url('audit', ['entity' => 'shift', 'record' => $row['id']], 'swaps', $this->navigationContext()) }}">Histórico do plantão #{{ $row['id'] }}</a>
                    @endforeach
                </div>
            @elseif ($pendingRevertSummary['can_revert'])
                <p>A confirmação restaura os plantões originais, registra a justificativa, libera a cota desta troca e envia um novo comprovante aos dois profissionais. Os comprovantes anteriores serão preservados.</p>
                <div>
                    <label for="reversion-reason" class="field-label">Justificativa administrativa</label>
                    <textarea id="reversion-reason" wire:model="reversionReason" rows="3" maxlength="1000" required class="field-input" aria-invalid="{{ $errors->has('reversionReason') ? 'true' : 'false' }}" @error('reversionReason') aria-describedby="reversion-reason-error" @enderror></textarea>
                    @error('reversionReason')<p id="reversion-reason-error" class="field-error" role="alert">{{ $message }}</p>@enderror
                </div>
            @endif
            @error('reversion')<p class="field-error" role="alert" tabindex="-1" data-swap-decision-error>{{ $message }}</p>@enderror
            @error('reversionSnapshot')<p class="field-error" role="alert">{{ $message }}</p>@enderror
            <p hidden tabindex="-1" role="alert" class="field-error" data-decision-request-error></p>
        @endif
        <x-slot:actions>
            <button type="button" wire:click="cancelRevert" x-bind:disabled="busy" class="button-secondary w-full sm:w-auto" data-confirm-cancel>Manter troca</button>
            <button type="button" wire:click="refreshReversion" x-bind:disabled="busy" class="button-secondary w-full sm:w-auto">Atualizar revisão</button>
            @if ($pendingRevertSummary && $pendingRevertSummary['can_revert'] && ! $administrativeAnalysis)
                <button type="button" wire:click="confirmRevert" x-bind:disabled="busy" wire:loading.attr="disabled" wire:target="confirmRevert" class="button-danger w-full sm:w-auto">
                    <span wire:loading.remove wire:target="confirmRevert">Confirmar desfazer</span><span wire:loading wire:target="confirmRevert">Desfazendo…</span>
                </button>
            @elseif (! $administrativeAnalysis)
                <button type="button" wire:click="analyzeAdministrativeCorrection" x-bind:disabled="busy" class="button-primary w-full sm:w-auto">Analisar correção administrativa</button>
            @endif
        </x-slot:actions>
    </x-confirmation-modal>
</div>
