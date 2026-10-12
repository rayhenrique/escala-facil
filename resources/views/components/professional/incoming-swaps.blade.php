@props(['incomingSwaps', 'swapLimitReached', 'headingId'])
@if ($incomingSwaps->isNotEmpty())
        <section {{ $attributes->class(['professional-incoming mt-6 lg:mt-8']) }} id="{{ $headingId }}-list" aria-labelledby="{{ $headingId }}">
            <div class="flex items-end justify-between gap-4"><div><p class="eyebrow">Sua resposta</p><h2 id="{{ $headingId }}" tabindex="-1" class="mt-2 text-xl font-semibold">Pedidos para você cobrir</h2></div><span class="count-badge">{{ $incomingSwaps->total() }}</span></div>
            <div class="mt-4 grid gap-4 lg:grid-cols-2">
                @foreach ($incomingSwaps as $swap)
                    <article class="panel professional-request !p-4" wire:key="{{ $headingId }}-swap-{{ $swap->id }}">
                        <div class="flex flex-wrap items-center gap-2"><x-status-badge :status="$swap->status" label="Aguardando você" /><span class="text-xs text-muted">{{ sprintf('TROCA-%06d', $swap->id) }}</span></div>
                        <p class="mt-3 text-sm text-muted">{{ $swap->substitute_shift_id ? 'Troca mútua' : 'Troca simples' }} · Solicitante: <strong class="text-ink">{{ $swap->requester?->name ?? 'Profissional indisponível' }}</strong></p>
                        <h3 class="mt-2 text-base font-semibold">{{ $swap->shift?->period()->datedLabel() ?? 'Plantão indisponível' }}</h3>
                        <p class="mt-1 text-sm text-muted">{{ $swap->shift?->sector?->name ?? 'Setor não informado' }} · {{ $swap->shift?->category?->name ?? 'Categoria não informada' }}</p>
                        <div class="mt-4 flex flex-wrap gap-2">
                            <x-action-button id="{{ $headingId }}-details-{{ $swap->id }}" wire:click="openIncomingDetails({{ $swap->id }})" target="openIncomingDetails" x-on:click="responseOpener = $el.id" x-bind:data-response-return="responseOpener === $el.id ? '' : null">Ver detalhes</x-action-button>
                            <x-action-button id="{{ $headingId }}-accept-{{ $swap->id }}" wire:click="requestAcceptSwap({{ $swap->id }})" target="requestAcceptSwap" variant="primary" x-on:click="responseOpener = $el.id" x-bind:data-response-return="responseOpener === $el.id ? '' : null">Aceitar</x-action-button>
                            <x-action-button id="{{ $headingId }}-decline-{{ $swap->id }}" wire:click="requestDeclineSwap({{ $swap->id }})" target="requestDeclineSwap" x-on:click="responseOpener = $el.id" x-bind:data-response-return="responseOpener === $el.id ? '' : null">Recusar</x-action-button>
                        </div>
                    </article>
                @endforeach
            </div>
            <div class="mt-4">
                @if ($headingId === 'incoming-title')
                    <x-operational-pagination :paginator="$incomingSwaps" label="Paginação dos pedidos recebidos" :controls="$headingId.'-list'" :heading="$headingId" />
                @else
                    {{ $incomingSwaps->links() }}
                @endif
            </div>
        </section>
    @endif
