@props(['id', 'fields', 'labels', 'defaults' => [], 'disabled' => false, 'title' => 'Pesquisar e filtrar'])
<div class="mt-5" x-data="mobileFilterPanel({ fields: @js($fields), labels: @js($labels), defaults: @js($defaults) })" data-filter-panel>
    <button type="button" @click="openFilters()" @disabled($disabled) :disabled="applying || @js($disabled)" class="button-secondary min-h-11 w-full lg:hidden" aria-haspopup="dialog" aria-controls="{{ $id }}" :aria-expanded="filtersOpen.toString()">Pesquisar e filtrar <span x-show="activeFilters().length" x-text="'(' + activeFilters().length + ')'" class="ml-2"></span></button>
    <div class="mt-2 flex flex-wrap gap-2" role="group" aria-label="Filtros ativos">
        <template x-for="filter in activeFilters()" :key="filter.key"><button type="button" @click="removeFilter(filter.key)" :disabled="applying || @js($disabled)" :aria-label="'Remover filtro ' + filter.label" class="filter-chip"><span x-text="filter.label"></span><x-nav-icon name="close" class="size-4 shrink-0" /></button></template>
    </div>
    <x-action-modal :show="true" :id="$id" :title="$title" client-state="filtersOpen" close-event="close-filters" inline-desktop fit-viewport initial-focus="[data-filter-title]" class="lg:mt-2" @close-filters="cancelFilters()">
        <h3 tabindex="-1" data-filter-title class="sr-only">{{ $title }}</h3>
        <fieldset @disabled($disabled) :disabled="applying || @js($disabled)" class="min-w-0 space-y-4">
            <legend class="sr-only">{{ $title }}</legend>
            {{ $slot }}
        </fieldset>
        <p x-show="filterMobile" class="mt-4 text-sm text-muted">As alterações serão usadas ao aplicar os filtros. Limpar prepara a busca sem filtros; Cancelar mantém a consulta atual.</p>
        <p x-show="filterError" x-cloak role="alert" class="field-error mt-3">Não foi possível confirmar os filtros. Confira a conexão e tente novamente.</p>
        <x-slot:actions>
            <button type="button" @click="filterMobile ? clearDraft() : $wire.clearFilters()" :disabled="applying || @js($disabled)" class="button-secondary min-h-11">Limpar filtros</button>
            <button type="button" @click="cancelFilters()" :disabled="applying" class="button-secondary min-h-11 lg:hidden">Cancelar</button>
            <button type="button" @click="applyFilters()" :disabled="applying || @js($disabled)" :aria-busy="applying.toString()" class="button-primary min-h-11 lg:hidden"><span x-text="applying ? 'Aplicando…' : 'Aplicar filtros'">Aplicar filtros</span></button>
        </x-slot:actions>
    </x-action-modal>
</div>
