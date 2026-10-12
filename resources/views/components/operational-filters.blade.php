@props(['selected' => false, 'kind' => 'shifts', 'advancedSwaps' => false])
@php
    $fields = ['search', 'filterCategoryId', 'dateFrom', 'dateTo', $kind === 'swaps' ? 'swapStatusFilter' : 'filter'];
    if ($kind !== 'personal') $fields[] = 'filterSectorId';
    if ($advancedSwaps) $fields = array_merge($fields, ['requesterSearch', 'substituteSearch', 'sort']);
    $labels = ['search' => 'Nome', 'filterSectorId' => 'Setor', 'filterCategoryId' => 'Categoria', 'dateFrom' => 'De', 'dateTo' => 'Até', 'filter' => 'Situação', 'swapStatusFilter' => 'Situação', 'requesterSearch' => 'Solicitante', 'substituteSearch' => 'Substituto', 'sort' => 'Ordem'];
    $defaults = $kind === 'swaps' ? ['swapStatusFilter' => $this->listView === 'pending' ? 'pending_manager' : '', 'sort' => $this->listView === 'pending' ? 'oldest' : 'newest'] : [];
@endphp
<x-filter-panel id="operational-filter-sheet" :fields="$fields" :labels="$labels" :defaults="$defaults" :disabled="$selected">
    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
        <div>
            <label for="list-search" class="field-label">{{ $kind !== 'shifts' ? 'Solicitante ou substituto' : 'Profissional' }}</label>
            <input id="list-search" type="search" autocomplete="off" enterkeyhint="search" x-model="draft.search" data-filter-field="search" @input.debounce.350ms="changeFilter('search', $event)" maxlength="100" placeholder="Pesquisar por nome" class="field-input" @error('search') aria-invalid="true" aria-describedby="list-search-error" @enderror>
            @error('search')<p id="list-search-error" class="field-error">{{ $message }}</p>@enderror
        </div>
        <div>
            <label for="list-sector" class="field-label">Setor</label>
            <select @disabled($kind === 'personal') id="list-sector" @if ($kind !== 'personal') x-model="draft.filterSectorId" data-filter-field="filterSectorId" @change="changeFilter('filterSectorId', $event)" @endif class="field-input" @error('filterSectorId') aria-invalid="true" aria-describedby="list-sector-error" @enderror>
                @if ($kind !== 'personal')<option value="">Todos os setores</option>@else<option value="{{ $this->filterSectorId }}">{{ $this->sectors->first()?->name ?? 'Sem setor' }}</option>@endif
                @foreach ($this->sectors as $sector)<option value="{{ $sector->id }}">{{ $sector->name }}</option>@endforeach
            </select>
            @error('filterSectorId')<p id="list-sector-error" class="field-error">{{ $message }}</p>@enderror
        </div>
        <div>
            <label for="list-category" class="field-label">Categoria</label>
            <select id="list-category" x-model="draft.filterCategoryId" data-filter-field="filterCategoryId" @change="changeFilter('filterCategoryId', $event)" class="field-input" @error('filterCategoryId') aria-invalid="true" aria-describedby="list-category-error" @enderror>
                <option value="">Todas as categorias</option>
                @foreach ($this->categories as $category)<option value="{{ $category->id }}">{{ $category->name }}</option>@endforeach
            </select>
            @error('filterCategoryId')<p id="list-category-error" class="field-error">{{ $message }}</p>@enderror
        </div>
        <div>
            <label for="list-from" class="field-label">Data de início: de</label>
            <input id="list-from" type="date" x-model="draft.dateFrom" data-filter-field="dateFrom" @change="changeFilter('dateFrom', $event)" class="field-input" @error('dateFrom') aria-invalid="true" aria-describedby="list-from-error" @enderror>
            @error('dateFrom')<p id="list-from-error" class="field-error">{{ $message }}</p>@enderror
        </div>
        <div>
            <label for="list-to" class="field-label">Data de início: até</label>
            <input id="list-to" type="date" x-model="draft.dateTo" data-filter-field="dateTo" @change="changeFilter('dateTo', $event)" class="field-input" @error('dateTo') aria-invalid="true" aria-describedby="list-to-error" @enderror>
            @error('dateTo')<p id="list-to-error" class="field-error">{{ $message }}</p>@enderror
        </div>
        <div>
            <label for="list-status" class="field-label">{{ $kind === 'personal' ? 'Situação dos plantões' : 'Situação' }}</label>
            @if ($kind === 'swaps')
                <select id="list-status" x-model="draft.swapStatusFilter" data-filter-field="swapStatusFilter" @change="changeFilter('swapStatusFilter', $event)" class="field-input" @error('swapStatusFilter') aria-invalid="true" aria-describedby="list-status-error" @enderror>
                    <option value="">Todas as situações</option>
                    @foreach (\App\Enums\ShiftSwapStatus::cases() as $status)<option value="{{ $status->value }}">{{ $status->label() }}</option>@endforeach
                </select>
                @error('swapStatusFilter')<p id="list-status-error" class="field-error">{{ $message }}</p>@enderror
            @else
                <select id="list-status" x-model="draft.filter" data-filter-field="filter" @change="changeFilter('filter', $event)" class="field-input" @error('filter') aria-invalid="true" aria-describedby="list-status-error" @enderror>
                    <option value="">Todos no mês / período</option>
                    <option value="scheduled">Agendados</option>
                    <option value="absence_reported">Ausência informada</option>
                    @if ($kind !== 'personal')<option value="uncovered_month">Descobertos no mês / período</option>
                    <option value="uncovered">Descobertos ativos ou futuros (todos os meses)</option>@else<option value="uncovered">Descobertos</option>@endif
                </select>
                @error('filter')<p id="list-status-error" class="field-error">{{ $message }}</p>@enderror
            @endif
        </div>
    </div>
    @if ($advancedSwaps)
        <div class="mt-4 grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
            @foreach (['requesterSearch' => ['list-requester', 'Solicitante'], 'substituteSearch' => ['list-substitute', 'Substituto']] as $field => [$id, $label])
                <div>
                    <label for="{{ $id }}" class="field-label">{{ $label }}</label>
                    <input id="{{ $id }}" type="search" autocomplete="off" enterkeyhint="search" x-model="draft['{{ $field }}']" data-filter-field="{{ $field }}" @input.debounce.350ms="changeFilter('{{ $field }}', $event)" maxlength="100" placeholder="Pesquisar por nome" class="field-input" @error($field) aria-invalid="true" aria-describedby="{{ $id }}-error" @enderror>
                    @error($field)<p id="{{ $id }}-error" class="field-error">{{ $message }}</p>@enderror
                </div>
            @endforeach
            <div>
                <label for="list-sort" class="field-label">Ordenar por solicitação</label>
                <select id="list-sort" x-model="draft.sort" data-filter-field="sort" @change="changeFilter('sort', $event)" class="field-input" @error('sort') aria-invalid="true" aria-describedby="list-sort-error" @enderror>
                    <option value="oldest">Mais antigas primeiro</option><option value="newest">Mais recentes primeiro</option>
                </select>
                @error('sort')<p id="list-sort-error" class="field-error">{{ $message }}</p>@enderror
            </div>
        </div>
    @endif
    <div class="mt-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <p class="text-sm text-muted">{{ $kind === 'personal' ? 'A pesquisa por nome filtra as trocas. Categoria e datas filtram suas listas; as datas substituem o mês dos plantões.' : ($kind === 'swaps' ? 'Setor, categoria e período se referem ao plantão solicitado.' : 'Ao informar datas, o período substitui o mês exibido.') }} As duas datas estão incluídas.</p>
    </div>
</x-filter-panel>
