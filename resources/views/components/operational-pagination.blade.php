@props(['paginator', 'label' => 'Paginação das pendências', 'controls' => 'attention-list', 'heading' => 'attention-title'])

@if ($paginator->hasPages())
    <nav class="flex flex-wrap items-center justify-between gap-3" aria-label="{{ $label }}">
        <button type="button" wire:click="previousPage('{{ $paginator->getPageName() }}')" wire:loading.attr="disabled" class="button-secondary disabled:cursor-not-allowed disabled:opacity-50" @disabled($paginator->onFirstPage()) aria-controls="{{ $controls }}" x-on:click="document.getElementById(@js($heading))?.scrollIntoView({ block: 'start' })">Anterior</button>
        <p class="text-sm text-muted" role="status">Página <strong class="text-ink">{{ $paginator->currentPage() }}</strong> de {{ $paginator->lastPage() }}</p>
        <button type="button" wire:click="nextPage('{{ $paginator->getPageName() }}')" wire:loading.attr="disabled" class="button-secondary disabled:cursor-not-allowed disabled:opacity-50" @disabled(! $paginator->hasMorePages()) aria-controls="{{ $controls }}" x-on:click="document.getElementById(@js($heading))?.scrollIntoView({ block: 'start' })">Próxima</button>
    </nav>
@endif
