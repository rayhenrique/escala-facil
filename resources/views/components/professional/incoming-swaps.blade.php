@props(['incomingSwaps', 'swapLimitReached', 'headingId'])
@if ($incomingSwaps->isNotEmpty())
        <section {{ $attributes->class(['professional-incoming mt-6 lg:mt-8']) }} id="{{ $headingId }}-list" aria-labelledby="{{ $headingId }}">
            <div class="flex items-end justify-between gap-4"><div><p class="eyebrow">Sua resposta</p><h2 id="{{ $headingId }}" tabindex="-1" class="mt-2 text-xl font-semibold">Pedidos para você cobrir</h2></div><span class="count-badge">{{ $incomingSwaps->total() }}</span></div>
            <div class="mt-4 grid gap-4 lg:grid-cols-2">
                @foreach ($incomingSwaps as $swap)
                    <article class="panel professional-request" wire:key="{{ $headingId }}-swap-{{ $swap->id }}">
                        <div class="flex flex-wrap items-center gap-2"><x-status-badge :status="$swap->status" label="Aguardando você" /><span class="text-xs text-muted">{{ sprintf('TROCA-%06d', $swap->id) }}</span></div>
                        <h3 class="mt-4 text-lg font-semibold">{{ $swap->shift->date->format('d/m/Y') }} · {{ $swap->shift->time_label }}</h3>
                        <p class="mt-2 text-sm text-muted">{{ $swap->shift->sector?->name ?? 'Setor não informado' }} · {{ $swap->shift->category?->name ?? 'Categoria não informada' }}</p>
                        @if ($swap->substituteShift)
                            <p class="mt-2 text-xs font-semibold text-coverage">Contrapartida: você cederá seu plantão de {{ $swap->substituteShift->date->format('d/m/Y') }} ({{ $swap->substituteShift->time_label }}).</p>
                        @endif
                        <p class="mt-4 text-sm"><span class="text-muted">Solicitado por</span> <strong>{{ $swap->requester?->name ?? 'Profissional indisponível' }}</strong></p>
                        <button type="button" wire:click="requestAcceptSwap({{ $swap->id }})" wire:loading.attr="disabled" class="button-primary mt-5 inline-flex w-full sm:w-auto" @disabled($swapLimitReached)>{{ $swapLimitReached ? 'Cota mensal atingida' : 'Aceitar e enviar à gerência' }}</button>
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
