@props(['swap', 'context' => [], 'selected' => false, 'revertPermission' => null, 'doubleShift' => null])
<article class="panel break-words {{ $selected ? 'context-record' : '' }}" data-context-record="{{ $selected ? 'swap-'.$swap->id : '' }}" data-swap-record="{{ $swap->id }}" wire:key="swap-approval-{{ $swap->id }}">
    <div class="flex flex-wrap items-center gap-3">
        <x-status-badge :status="$swap->status" :label="$swap->status === \App\Enums\ShiftSwapStatus::PendingManager ? 'Aguardando decisão' : $swap->status->label()" />
        @if ($doubleShift)<x-status-badge status="double_shift" label="Dobra" />@endif
        <span class="font-mono text-xs text-muted">Pedido #{{ $swap->id }}</span>
        <span class="text-sm font-semibold">{{ $swap->substituteShift ? 'Troca mútua' : 'Cobertura de plantão' }}</span>
        <x-operational-audit-link entity="swap" :record="$swap->id" from="swaps" :context="$context" />
    </div>
    <div class="mt-4 grid gap-4 lg:grid-cols-2">
        <div class="min-w-0">
            <p class="meta-label">Plantão solicitado #{{ $swap->shift_id }}</p>
            <h3 class="mt-1 font-semibold">{{ $swap->shift->date->format('d/m/Y') }} · {{ $swap->shift->time_label }}</h3>
            <p class="mt-1 text-sm text-muted">{{ $swap->shift->sector->name }} · {{ $swap->shift->category->name }}</p>
            <dl class="mt-3 space-y-1 text-sm">
                <div><dt class="inline text-muted">Solicitante:</dt> <dd class="inline font-semibold">{{ $swap->requester->name }}</dd></div>
                <div><dt class="inline text-muted">Substituto previsto:</dt> <dd class="inline font-semibold">{{ $swap->substitute->name }}</dd></div>
                <div><dt class="inline text-muted">Profissional atual:</dt> <dd class="inline">{{ $swap->shift->user?->name ?? 'Sem profissional alocado' }}</dd></div>
            </dl>
        </div>
        @if ($swap->substituteShift)
            <div class="min-w-0 border-t border-line pt-4 lg:border-l lg:border-t-0 lg:pl-4 lg:pt-0">
                <p class="meta-label">Plantão de contrapartida #{{ $swap->substitute_shift_id }}</p>
                <h3 class="mt-1 font-semibold">{{ $swap->substituteShift->date->format('d/m/Y') }} · {{ $swap->substituteShift->time_label }}</h3>
                <p class="mt-1 text-sm text-muted">{{ $swap->substituteShift->sector->name }} · {{ $swap->substituteShift->category->name }}</p>
                <dl class="mt-3 space-y-1 text-sm">
                    <div><dt class="inline text-muted">Titular no pedido:</dt> <dd class="inline font-semibold">{{ $swap->substitute->name }}</dd></div>
                    <div><dt class="inline text-muted">Profissional previsto:</dt> <dd class="inline font-semibold">{{ $swap->requester->name }}</dd></div>
                    <div><dt class="inline text-muted">Profissional atual:</dt> <dd class="inline">{{ $swap->substituteShift->user?->name ?? 'Sem profissional alocado' }}</dd></div>
                </dl>
            </div>
        @endif
    </div>
    <p class="mt-4 text-xs text-muted">Solicitado em {{ $swap->created_at->format('d/m/Y H:i') }}. Os profissionais previstos descrevem o pedido; a situação indica se a troca foi realizada.</p>
    @if ($doubleShift)<p class="mt-2 border-l-2 border-primary pl-3 text-sm text-ink">Dobra: emenda com o plantão do substituto em {{ $doubleShift }}.</p>@endif
    @if ($selected)
        <div class="mt-4 border-t border-line pt-4 text-sm">
            <p>Última atualização: {{ $swap->updated_at->format('d/m/Y H:i') }}{{ $swap->manager ? ' · Gerente: '.$swap->manager->name : '' }}.</p>
            @if ($swap->rejection_reason)<p class="mt-2 break-words">Justificativa: {{ $swap->rejection_reason }}</p>@endif
        </div>
    @endif
    <div class="mt-5 flex flex-wrap gap-2">
        @unless ($selected)
            <a href="{{ \App\Support\OperationalNavigation::url('swaps', ['request' => $swap->id], 'swaps', $context) }}" wire:navigate class="button-secondary" aria-label="Detalhes do pedido #{{ $swap->id }}">Detalhes</a>
        @endunless
        <a href="{{ route('swaps.receipt.pdf', $swap->id) }}" class="button-secondary" aria-label="Comprovante do pedido #{{ $swap->id }} em PDF">Comprovante PDF</a>
        @if ($swap->status === \App\Enums\ShiftSwapStatus::PendingManager)
            <button type="button" wire:click="openSwapAnalysis({{ $swap->id }})" wire:loading.attr="disabled" class="button-secondary" aria-label="Analisar pedido #{{ $swap->id }}">Analisar</button>
            <button type="button" wire:click="requestDecision({{ $swap->id }}, 'reject')" wire:loading.attr="disabled" class="button-secondary">Rejeitar</button>
            <button type="button" wire:click="requestDecision({{ $swap->id }}, 'approve')" wire:loading.attr="disabled" class="button-primary">Aprovar e transferir</button>
        @endif
        @if ($revertPermission['allowed'] ?? false)
            <button type="button" wire:click="requestRevert({{ $swap->id }})" wire:loading.attr="disabled" class="button-secondary text-danger">Desfazer troca</button>
        @endif
    </div>
    @if ($revertPermission && ! $revertPermission['allowed'])
        <p class="mt-3 text-sm text-muted" data-revert-blocker>Não é possível desfazer: {{ $revertPermission['reason'] }}</p>
    @endif
</article>
