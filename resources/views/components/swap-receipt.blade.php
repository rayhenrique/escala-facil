@props(['receipt'])

@php
    $recordedAt = \Carbon\CarbonImmutable::parse($receipt['recorded_at']);
    $shiftDate = \Carbon\CarbonImmutable::parse($receipt['shift_date']);
    $hasCounterpart = ! empty($receipt['has_counterpart']) && ! empty($receipt['counterpart_shift_date']);
    $counterpartDate = $hasCounterpart ? \Carbon\CarbonImmutable::parse($receipt['counterpart_shift_date']) : null;
    $swapId = $receipt['shift_swap_id'] ?? null;
    if (! $swapId && ! empty($receipt['reference']) && preg_match('/(\d+)/', $receipt['reference'], $matches)) {
        $swapId = (int) $matches[1];
    }
@endphp

<article {{ $attributes->class(['swap-receipt']) }} aria-label="Comprovante {{ $receipt['reference'] }}">
    <div class="swap-receipt-heading">
        <div>
            <p class="eyebrow">Comprovante da troca</p>
            <h2>{{ $receipt['event_label'] }}</h2>
        </div>
        <div class="flex flex-wrap items-center gap-2.5 text-left sm:text-right">
            <div>
                <span class="meta-label">Referência</span>
                <p class="mt-1 font-mono text-sm font-semibold text-primary">{{ $receipt['reference'] }}</p>
            </div>
            @if ($swapId)
                <a
                    href="{{ route('swaps.receipt.pdf', $swapId) }}"
                    class="button-secondary print:hidden ml-auto sm:ml-0 min-h-9 px-3 py-1.5 text-xs inline-flex items-center gap-1.5 text-ink hover:text-primary transition-colors"
                    title="Baixar comprovante oficial em PDF"
                >
                    <svg class="size-4 text-muted" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" />
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 14.25l3 3m0 0l3-3m-3 3V9" />
                    </svg>
                    <span>Salvar PDF</span>
                </a>
            @endif
            <button
                type="button"
                onclick="window.printSwapReceipt ? window.printSwapReceipt(this) : window.print()"
                class="button-secondary print:hidden {{ ! $swapId ? 'ml-auto' : '' }} min-h-9 px-3 py-1.5 text-xs inline-flex items-center gap-1.5 text-ink hover:text-primary transition-colors"
                title="Imprimir apenas este comprovante"
            >
                <svg class="size-4 text-muted" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6.72 13.829c-.24.03-.48.062-.72.096m.72-.096a42.415 42.415 0 0 1 10.56 0m-10.56 0L6.34 18m10.94-4.171c.24.03.48.062.72.096m-.72-.096L17.66 18m0 0 .229 2.523a1.125 1.125 0 0 1-1.12 1.227H7.231c-.662 0-1.18-.568-1.12-1.227L6.34 18m11.318 0h1.091A2.25 2.25 0 0 0 21 15.75V9.456c0-1.081-.768-2.015-1.837-2.175a48.055 48.055 0 0 0-1.913-.247M6.34 18H5.25A2.25 2.25 0 0 1 3 15.75V9.456c0-1.081.768-2.015 1.837-2.175a48.041 48.041 0 0 1 1.913-.247m10.5 0a48.536 48.536 0 0 0-10.5 0m10.5 0V3.75A2.25 2.25 0 0 0 14.25 1.5h-4.5A2.25 2.25 0 0 0 7.5 3.75v3.456" />
                </svg>
                <span>Imprimir</span>
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
            @if ($receipt['status'] === 'rejected')
                <div class="flex items-start gap-2.5 rounded-lg border border-red-200 bg-red-50/70 p-3 text-red-800">
                    <span class="font-bold text-danger">✕</span>
                    <div>
                        <strong>Solicitação rejeitada pela gerência</strong> — A escala original foi mantida sem alterações.
                    </div>
                </div>
            @elseif ($receipt['status'] === 'reverted')
                <div class="flex items-start gap-2.5 rounded-lg border border-stone-200 bg-stone-50 p-3 text-stone-700">
                    <span class="font-bold text-muted">↺</span>
                    <div>
                        <strong>Troca desfeita pela gerência</strong> — A escala original foi restabelecida.
                    </div>
                </div>
            @elseif ($hasCounterpart && $counterpartDate)
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

@once
<script>
window.printSwapReceipt = function (triggerElement) {
    var receipt = triggerElement ? triggerElement.closest('.swap-receipt') : document.querySelector('.swap-receipt');
    if (!receipt) {
        window.print();
        return;
    }

    var existingFrame = document.getElementById('receipt-print-iframe');
    if (existingFrame) {
        existingFrame.remove();
    }

    var iframe = document.createElement('iframe');
    iframe.id = 'receipt-print-iframe';
    iframe.style.position = 'fixed';
    iframe.style.right = '0';
    iframe.style.bottom = '0';
    iframe.style.width = '0';
    iframe.style.height = '0';
    iframe.style.border = '0';
    iframe.style.visibility = 'hidden';
    document.body.appendChild(iframe);

    var doc = iframe.contentWindow.document;
    doc.open();

    var stylesHtml = '';
    document.querySelectorAll('link[rel="stylesheet"]').forEach(function (link) {
        stylesHtml += '<link rel="stylesheet" href="' + link.href + '">';
    });
    document.querySelectorAll('style').forEach(function (style) {
        stylesHtml += style.outerHTML;
    });

    var clone = receipt.cloneNode(true);
    clone.querySelectorAll('.print\\:hidden, button, a').forEach(function (el) {
        el.remove();
    });

    var now = new Date();
    var dateStr = now.toLocaleDateString('pt-BR');
    var timeStr = now.toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' });

    var printContent = '<!DOCTYPE html>' +
        '<html lang="pt-BR">' +
        '<head>' +
        '<meta charset="utf-8">' +
        '<title>Comprovante de Troca · Escala Fácil</title>' +
        stylesHtml +
        '<style>' +
        '@page { size: a4 portrait; margin: 15mm; }' +
        '* { box-sizing: border-box; -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; }' +
        'body { background: #ffffff !important; color: #12333a !important; font-family: "Instrument Sans", system-ui, -apple-system, sans-serif !important; margin: 0 !important; padding: 0 !important; font-size: 13px !important; }' +
        '.swap-receipt { border: 1px solid #c9dad7 !important; border-radius: 8px !important; background-color: #f3f8f7 !important; overflow: hidden !important; width: 100% !important; margin-bottom: 24px !important; box-shadow: none !important; }' +
        '.swap-receipt-heading { padding: 16px 20px !important; border-bottom: 2px solid #0f6674 !important; background: #ffffff !important; display: flex !important; justify-content: space-between !important; align-items: center !important; }' +
        '.swap-receipt-grid { display: grid !important; grid-template-columns: repeat(3, minmax(0, 1fr)) !important; gap: 1px !important; background-color: #c9dad7 !important; }' +
        '.swap-receipt-grid > div { background-color: #f3f8f7 !important; padding: 12px 16px !important; }' +
        '.swap-receipt-primary { grid-column: span 1 !important; }' +
        '.print-official-header { border-bottom: 2px solid #005f56; padding-bottom: 12px; margin-bottom: 20px; display: flex; justify-content: space-between; align-items: flex-end; }' +
        '.print-signatures { margin-top: 40px; display: grid; grid-template-columns: repeat(3, 1fr); gap: 24px; text-align: center; }' +
        '.print-sign-line { border-top: 1px solid #12333a; padding-top: 6px; font-size: 11px; font-weight: bold; color: #12333a; }' +
        '.print-sign-role { font-size: 9px; color: #5e7377; margin-top: 2px; }' +
        '.print-official-footer { margin-top: 30px; padding-top: 10px; border-top: 1px dashed #c9dad7; font-size: 10px; color: #5e7377; text-align: center; }' +
        '</style>' +
        '</head>' +
        '<body>' +
        '<div class="print-official-header">' +
        '<div>' +
        '<div style="font-size: 20px; font-weight: bold; color: #005f56; letter-spacing: -0.02em;">Escala Fácil</div>' +
        '<div style="font-size: 11px; color: #5e7377; margin-top: 2px;">Comprovante Oficial de Troca de Plantão</div>' +
        '</div>' +
        '<div style="text-align: right; font-size: 11px; color: #5e7377;">' +
        '<div>Documento para conferência operacional</div>' +
        '<div style="margin-top: 2px; font-weight: 600;">Impresso em ' + dateStr + ' às ' + timeStr + '</div>' +
        '</div>' +
        '</div>' +
        clone.outerHTML +
        '<div class="print-signatures">' +
        '<div><div class="print-sign-line">Plantonista Solicitante</div><div class="print-sign-role">Assinatura</div></div>' +
        '<div><div class="print-sign-line">Plantonista Substituto</div><div class="print-sign-role">Assinatura</div></div>' +
        '<div><div class="print-sign-line">Gerência / Coordenação</div><div class="print-sign-role">Visto da Chefia</div></div>' +
        '</div>' +
        '<div class="print-official-footer">Escala Fácil · Documento eletrônico oficial gerado pelo sistema · Autenticação operacional</div>' +
        '</body></html>';

    doc.write(printContent);
    doc.close();

    setTimeout(function () {
        try {
            iframe.contentWindow.focus();
            iframe.contentWindow.print();
        } catch (e) {
            console.error('Erro na impressão por iframe:', e);
            window.print();
        }
    }, 250);
};
</script>
@endonce
