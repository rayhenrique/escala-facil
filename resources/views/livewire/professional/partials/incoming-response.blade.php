<x-bottom-sheet :show="$incomingDetailId !== null && $pendingConfirmation === null" id="incoming-response" title="Detalhes do pedido recebido" eyebrow="Sua resposta" close-action="closeIncomingDetails" max-width="2xl" initial-focus="#incoming-response-title" return-focus="[data-response-return]" request-error-message="Não foi possível consultar o pedido. Atualize os detalhes para conferir a situação atual.">
    @if ($incomingDetailId && $pendingConfirmation === null && $this->incomingReview)
        @php($review = $this->incomingReview)
        <div class="flex flex-wrap items-center gap-2"><x-status-badge :status="$review['status']" :label="$review['status_label']" /><span class="text-sm font-mono">{{ $review['reference'] }}</span><span class="text-sm text-muted">{{ $review['counterpart'] ? 'Troca mútua' : 'Troca simples' }}</span></div>
        <div class="mt-5 space-y-4">
            <div class="rounded-lg border border-line p-4"><h3 class="font-semibold">Plantão que você assumirá</h3><p class="mt-2 font-semibold">{{ $review['shift'] }}</p><p class="mt-1 text-sm text-muted">{{ $review['location'] }}</p><p class="mt-2 text-sm">Solicitante: <strong>{{ $review['requester'] }}</strong></p></div>
            @if ($review['counterpart'])<div class="rounded-lg border border-line p-4"><h3 class="font-semibold">Seu plantão que o solicitante assumirá</h3><p class="mt-2 font-semibold">{{ $review['counterpart'] }}</p><p class="mt-1 text-sm text-muted">{{ $review['counterpart_location'] }}</p></div>@endif
            <p class="text-sm text-muted">Sua cota de trocas aprovadas: {{ $review['quota'] }}. O aceite encaminha o pedido à gerência e ainda não consome a cota.</p>
            @foreach ($review['warnings'] as $warning)<p class="rounded-lg border border-line p-3 text-sm" role="status">{{ $warning }}</p>@endforeach
            @foreach ($review['blocks'] as $block)<p class="field-error" role="alert">{{ $block }}</p>@endforeach
            @if (! $review['warnings'] && ! $review['blocks'])<p class="text-sm">Nenhuma dobra, sobreposição ou incompatibilidade identificada nesta consulta. As condições serão conferidas novamente ao confirmar.</p>@endif
            <p class="text-sm">Aceitar encaminha o pedido para aprovação gerencial. Recusar encerra este pedido e mantém os plantões originais.</p>
            <x-action-button wire:click="refreshIncomingDetails" target="refreshIncomingDetails">Atualizar detalhes</x-action-button>
            <a href="{{ route('swaps.receipt.pdf', $incomingDetailId) }}" class="button-secondary">Comprovante oficial em PDF</a>
        </div>
    @endif
    <x-slot:actions>
        <x-action-button wire:click="closeIncomingDetails" target="closeIncomingDetails, requestAcceptSwap, requestDeclineSwap">Fechar</x-action-button>
        @if ($incomingDetailId && $pendingConfirmation === null && $this->incomingReview)
            <x-action-button wire:click="requestDeclineSwap({{ $incomingDetailId }})" target="requestDeclineSwap, requestAcceptSwap" :disabled="! $this->incomingReview['can_decline']">Recusar</x-action-button>
            <x-action-button wire:click="requestAcceptSwap({{ $incomingDetailId }})" target="requestAcceptSwap, requestDeclineSwap" variant="primary" :disabled="! $this->incomingReview['can_accept']" class="col-span-2 sm:col-span-1">Aceitar e enviar à gerência</x-action-button>
        @endif
    </x-slot:actions>
</x-bottom-sheet>
