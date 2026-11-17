@props(['receipt'])
<article {{ $attributes->class(['rounded-lg border border-line bg-white p-4 sm:p-6']) }} data-administrative-receipt>
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div><p class="eyebrow">{{ $receipt['reference'] }}</p><h2 class="text-lg font-semibold">Retificada administrativamente</h2><p class="text-sm text-muted">{{ $receipt['kind_label'] }}</p></div>
        <a class="button-secondary min-h-11" href="{{ route('swaps.rectification.receipt.pdf', $receipt['shift_swap_id']) }}">Salvar PDF administrativo</a>
    </div>
    <p class="mt-4 rounded-lg border border-line bg-canvas p-3 text-sm font-semibold">{{ $receipt['effect'] }}</p>
    <dl class="mt-4 grid gap-4 text-sm sm:grid-cols-2">
        <div><dt class="meta-label">Aprovação original preservada</dt><dd>{{ $receipt['status_label'] }} @if($receipt['approved_at']) · {{ \Carbon\CarbonImmutable::parse($receipt['approved_at'])->format('d/m/Y H:i') }}@endif</dd></div>
        <div><dt class="meta-label">Registrado por</dt><dd>{{ $receipt['author'] }} · {{ \Carbon\CarbonImmutable::parse($receipt['recorded_at'])->format('d/m/Y H:i') }}</dd></div>
        <div><dt class="meta-label">Solicitante / substituto</dt><dd>{{ $receipt['requester'] }} / {{ $receipt['substitute'] }}</dd></div>
        <div><dt class="meta-label">Plantões referenciados</dt><dd>#{{ $receipt['shift_id'] }} @if($receipt['substitute_shift_id']) / #{{ $receipt['substitute_shift_id'] }}@endif</dd></div>
        <div class="sm:col-span-2"><dt class="meta-label">Justificativa</dt><dd class="whitespace-pre-wrap break-words">{{ $receipt['reason'] }}</dd></div>
        <div class="sm:col-span-2"><dt class="meta-label">Evidências e referências</dt><dd class="whitespace-pre-wrap break-words">{{ $receipt['evidence_notes'] }}</dd></div>
    </dl>
</article>
