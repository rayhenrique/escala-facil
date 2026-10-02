@props(['receipt'])

@php
    $recordedAt = \Carbon\CarbonImmutable::parse($receipt['recorded_at']);
    $shiftDate = \Carbon\CarbonImmutable::parse($receipt['shift_date']);
    $hasCounterpart = ! empty($receipt['has_counterpart']) && ! empty($receipt['counterpart_shift_date']);
    $counterpartDate = $hasCounterpart ? \Carbon\CarbonImmutable::parse($receipt['counterpart_shift_date']) : null;
@endphp

<article {{ $attributes->class(['swap-receipt']) }} aria-label="Comprovante {{ $receipt['reference'] }}">
    <div class="swap-receipt-heading">
        <div>
            <p class="eyebrow">Comprovante da troca</p>
            <h2>{{ $receipt['event_label'] }}</h2>
        </div>
        <div class="flex flex-wrap items-center gap-3 text-left sm:text-right">
            <div>
                <span class="meta-label">Referência</span>
                <p class="mt-1 font-mono text-sm font-semibold text-primary">{{ $receipt['reference'] }}</p>
            </div>
            <button
                type="button"
                onclick="window.print()"
                class="button-secondary print:hidden ml-auto min-h-9 px-3 py-1.5 text-xs inline-flex items-center gap-1.5"
                title="Imprimir comprovante"
            >
                <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6.72 13.829c-.24.03-.48.062-.72.096m.72-.096a42.415 42.415 0 0 1 10.56 0m-10.56 0L6.34 18m10.94-4.171c.24.03.48.062.72.096m-.72-.096L17.66 18m0 0 .229 2.523a1.125 1.125 0 0 1-1.12 1.227H7.231c-.662 0-1.18-.568-1.12-1.227L6.34 18m11.318 0h1.091A2.25 2.25 0 0 0 21 15.75V9.456c0-1.081-.768-2.015-1.837-2.175a48.055 48.055 0 0 0-1.913-.247M6.34 18H5.25A2.25 2.25 0 0 1 3 15.75V9.456c0-1.081.768-2.015 1.837-2.175a48.041 48.041 0 0 1 1.913-.247m10.5 0a48.536 48.536 0 0 0-10.5 0m10.5 0V3.75A2.25 2.25 0 0 0 14.25 1.5h-4.5A2.25 2.25 0 0 0 7.5 3.75v3.456" />
                </svg>
                Imprimir
            </button>
        </div>
    </div>

    <div class="swap-receipt-grid">
        <div class="swap-receipt-primary">
            <span class="meta-label">Plantão original (substituído)</span>
            <p>{{ $shiftDate->format('d/m/Y') }} · {{ $receipt['start_time'] }}–{{ $receipt['end_time'] }}</p>
            <small>{{ $receipt['sector'] }} · {{ $receipt['category'] }}</small>
            <p class="mt-2 text-xs font-semibold text-primary">Cedido por: {{ $receipt['requester'] }}</p>
        </div>

        @if ($hasCounterpart && $counterpartDate)
            <div class="swap-receipt-primary bg-primary-soft/30">
                <span class="meta-label">Plantão de contrapartida</span>
                <p>{{ $counterpartDate->format('d/m/Y') }} · {{ $receipt['counterpart_start_time'] }}–{{ $receipt['counterpart_end_time'] }}</p>
                <small>{{ $receipt['counterpart_sector'] }} · {{ $receipt['counterpart_category'] }}</small>
                <p class="mt-2 text-xs font-semibold text-primary">Cedido por: {{ $receipt['substitute'] }}</p>
            </div>
        @endif

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
        <div @class(['lg:col-span-2' => ! $receipt['manager'] && ! $hasCounterpart])>
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

    {{-- Resumo da movimentação (Troca geral) --}}
    <div class="border-t border-line bg-white p-5 sm:p-6">
        <p class="meta-label">Movimentação detalhada da escala</p>
        <div class="mt-3 space-y-2 text-sm text-ink">
            @if ($hasCounterpart && $counterpartDate)
                <div class="flex items-start gap-2.5 rounded-lg border border-line bg-canvas p-3">
                    <span class="font-bold text-coverage">⇄</span>
                    <div>
                        <strong>{{ $receipt['requester'] }}</strong> foi para o plantão do dia <strong>{{ $counterpartDate->format('d/m/Y') }}</strong>, das <strong>{{ $receipt['counterpart_start_time'] }} às {{ $receipt['counterpart_end_time'] }}</strong> ({{ $receipt['counterpart_sector'] }} · {{ $receipt['counterpart_category'] }}).
                    </div>
                </div>
                <div class="flex items-start gap-2.5 rounded-lg border border-line bg-canvas p-3">
                    <span class="font-bold text-coverage">⇄</span>
                    <div>
                        <strong>{{ $receipt['substitute'] }}</strong> foi para o plantão do dia <strong>{{ $shiftDate->format('d/m/Y') }}</strong>, das <strong>{{ $receipt['start_time'] }} às {{ $receipt['end_time'] }}</strong> ({{ $receipt['sector'] }} · {{ $receipt['category'] }}).
                    </div>
                </div>
            @else
                <div class="flex items-start gap-2.5 rounded-lg border border-line bg-canvas p-3">
                    <span class="font-bold text-primary">→</span>
                    <div>
                        <strong>{{ $receipt['substitute'] }}</strong> vai substituir <strong>{{ $receipt['requester'] }}</strong> no plantão do dia <strong>{{ $shiftDate->format('d/m/Y') }}</strong>, das <strong>{{ $receipt['start_time'] }} às {{ $receipt['end_time'] }}</strong> ({{ $receipt['sector'] }} · {{ $receipt['category'] }}).
                    </div>
                </div>
            @endif
        </div>
    </div>

    @if (! empty($receipt['rejection_reason']))
        <div class="border-t border-red-200 bg-red-50/70 p-5 sm:p-6">
            <span class="meta-label text-danger">Justificativa da gerência</span>
            <p class="mt-2 text-sm font-medium text-danger">{{ $receipt['rejection_reason'] }}</p>
        </div>
    @endif
</article>
