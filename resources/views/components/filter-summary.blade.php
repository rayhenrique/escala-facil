@props(['filters', 'focusId' => null])
@if (collect($filters)->contains(fn ($filter) => $filter['value'] !== ''))
    <div class="mt-4 flex flex-wrap items-center gap-2" role="group" aria-label="Filtros ativos" x-data @click="document.getElementById(@js($focusId))?.focus()">
        @foreach ($filters as $field => $filter)
            @if ($filter['value'] !== '')
                <button type="button" wire:click="$set('{{ $field }}', '')" wire:loading.attr="disabled" class="filter-chip" aria-label="Remover filtro {{ $filter['label'] }}: {{ $filter['display'] ?? $filter['value'] }}"><span>{{ $filter['label'] }}: {{ $filter['display'] ?? $filter['value'] }}</span><x-nav-icon name="close" class="size-4 shrink-0" /></button>
            @endif
        @endforeach
        <button type="button" wire:click="clearFilters" wire:loading.attr="disabled" class="button-secondary min-h-11">Limpar filtros</button>
    </div>
@endif
