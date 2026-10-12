@props([
    'requestErrorMessage' => 'Não foi possível concluir a solicitação. Confira os dados e tente novamente.',
    'show' => false, 'id', 'title', 'eyebrow' => null, 'closeAction' => null, 'clientState' => null, 'closeEvent' => null,
    'submitAction' => null, 'role' => 'dialog', 'maxWidth' => 'lg',
    'trackChanges' => false, 'initialFocus' => null, 'returnFocus' => null,
    'restoreFocus' => true, 'busyAction' => null, 'fitViewport' => false,
])
@php
    if ($clientState !== null && ! preg_match('/^[a-zA-Z][a-zA-Z0-9_]*$/', $clientState)) throw new InvalidArgumentException('Estado local da janela inválido.');
    $width = ['sm' => 'max-w-sm', 'md' => 'max-w-md', 'lg' => 'max-w-lg', 'xl' => 'max-w-xl', '2xl' => 'max-w-2xl'][$maxWidth] ?? 'max-w-lg';
@endphp
@if ($show)
    <div {{ $attributes->class(['modal-overlay']) }}
        x-data="actionDialog({ close: () => {{ $closeEvent ? '$dispatch(' : '$wire.call(' }}@js($closeEvent ?? $closeAction)), active: {{ $clientState ? '() => '.$clientState : 'null' }}, trackChanges: @js($trackChanges), initialFocus: @js($initialFocus), returnFocus: @js($returnFocus), restoreFocus: @js($restoreFocus), fitViewport: @js($fitViewport) })"
        x-trap.inert.noscroll.noautofocus.noreturn="{{ $clientState ?? 'true' }}"
        @if ($clientState) x-show="{{ $clientState }}" x-cloak @endif
        @keydown.escape.stop.prevent="requestClose()" @input="changed()" @change="changed()"
        @ui-dialog-busy="busy = $event.detail"
        @if ($busyAction) data-decision-component="{{ $this->getId() }}" x-on:swap-decision-busy.window="busy = $event.detail" @endif
        @if (! $busyAction && isset($this) && $this instanceof \Livewire\Component) data-ui-dialog-component="{{ $this->getId() }}" @endif
    >
        <div class="modal-backdrop" aria-hidden="true" @click="requestClose()"></div>
        <section id="{{ $id }}" class="modal-dialog {{ $width }} {{ $role === 'alertdialog' ? 'confirmation-dialog' : '' }}" role="{{ $role }}" aria-modal="true" aria-labelledby="{{ $id }}-title" aria-describedby="{{ $id }}-description" :aria-busy="busy.toString()"
            >
            @if ($submitAction)
                <form class="modal-form" method="post" wire:submit="{{ $submitAction }}" novalidate>
                    @csrf
            @endif
            <header class="modal-header">
                @isset($heading)
                    {{ $heading }}
                @else
                    <div>@if ($eyebrow)<p class="eyebrow">{{ $eyebrow }}</p>@endif<h2 id="{{ $id }}-title" tabindex="-1">{{ $title }}</h2></div>
                    <button type="button" @click="requestClose()" :disabled="busy" class="modal-close-button" aria-label="Fechar janela"><x-nav-icon name="close" class="size-5" /></button>
                @endisset
            </header>
            <div id="{{ $id }}-description" class="modal-body" tabindex="0" data-dialog-body>
                {{ $slot }}
                <p hidden tabindex="-1" role="alert" class="field-error mt-3" data-ui-request-error>{{ $requestErrorMessage }}</p>
            </div>
            <footer class="modal-footer" x-show="!discardRequested">
                {{ $actions }}
            </footer>
            <div class="modal-footer" x-show="discardRequested" x-cloak>
                <p class="col-span-full text-sm text-ink sm:mr-auto" role="status">Há alterações não salvas. Deseja descartá-las?</p>
                <button type="button" x-ref="keepEditing" @click="keepEditing()" class="button-secondary">Continuar editando</button>
                <button type="button" @click="discard()" :disabled="busy" class="button-danger">Descartar alterações</button>
            </div>
            @if ($submitAction)
                </form>
            @endif
        </section>
    </div>
@endif
