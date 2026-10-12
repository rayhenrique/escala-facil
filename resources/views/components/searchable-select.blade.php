@props(['id', 'label', 'searchModel', 'model', 'options', 'invalid' => false, 'emptyLabel' => 'Selecione', 'describedBy' => null])
<div class="space-y-2">
    <label for="{{ $id }}-search" class="text-sm text-muted">Pesquisar {{ mb_strtolower($label) }}</label>
    <input id="{{ $id }}-search" type="search" wire:model.live.debounce.350ms="{{ $searchModel }}" maxlength="100" autocomplete="off" enterkeyhint="search" class="field-input" aria-controls="{{ $id }}" placeholder="Digite parte do nome">
    <select id="{{ $id }}" wire:model="{{ $model }}" class="field-input" @if ($invalid) aria-invalid="true" aria-describedby="{{ $describedBy }}" @endif>
        <option value="">{{ $emptyLabel }}</option>
        @foreach ($options as $option)<option value="{{ $option->id }}">{{ $option->name }}</option>@endforeach
    </select>
    <p class="text-xs leading-5 text-muted" role="status">{{ $options->count() }} opção(ões) encontrada(s), até 50 por busca. A seleção atual é mantida. Pesquise para encontrar outros nomes.</p>
    <span wire:loading.delay wire:target="{{ $searchModel }}" class="text-sm text-muted" role="status">Pesquisando…</span>
</div>
