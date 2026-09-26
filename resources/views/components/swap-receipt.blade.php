@props(['receipt'])

@php
    $recordedAt = \Carbon\CarbonImmutable::parse($receipt['recorded_at']);
    $shiftDate = \Carbon\CarbonImmutable::parse($receipt['shift_date']);
@endphp

<article {{ $attributes->class(['swap-receipt']) }} aria-label="Comprovante {{ $receipt['reference'] }}">
    <div class="swap-receipt-heading">
        <div>
            <p class="eyebrow">Comprovante da troca</p>
            <h2>{{ $receipt['event_label'] }}</h2>
        </div>
        <div class="text-left sm:text-right">
            <span class="meta-label">Referência</span>
            <p class="mt-1 font-mono text-sm font-semibold text-primary">{{ $receipt['reference'] }}</p>
        </div>
    </div>

    <div class="swap-receipt-grid">
        <div class="swap-receipt-primary">
            <span class="meta-label">Plantão</span>
            <p>{{ $shiftDate->format('d/m/Y') }} · {{ $receipt['start_time'] }}–{{ $receipt['end_time'] }}</p>
            <small>{{ $receipt['sector'] }} · {{ $receipt['category'] }}</small>
        </div>
        <div>
            <span class="meta-label">Solicitante</span>
            <p>{{ $receipt['requester'] }}</p>
        </div>
        <div>
            <span class="meta-label">Substituto</span>
            <p>{{ $receipt['substitute'] }}</p>
        </div>
        <div>
            <span class="meta-label">Situação</span>
            <div class="mt-2"><x-status-badge :status="$receipt['status']" :label="$receipt['status_label']" /></div>
        </div>
        <div>
            <span class="meta-label">Registrado em</span>
            <p>{{ $recordedAt->format('d/m/Y H:i') }}</p>
        </div>
        @if ($receipt['manager'])
            <div>
                <span class="meta-label">Responsável pela decisão</span>
                <p>{{ $receipt['manager'] }}</p>
            </div>
        @endif
    </div>
</article>
