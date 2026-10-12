@props([
    'requestErrorMessage' => 'Não foi possível concluir a solicitação. Confira os dados e tente novamente.',
    'show' => false,
    'id',
    'title',
    'eyebrow' => 'Confirmação necessária',
    'closeAction',
    'tone' => 'danger',
    'busyAction' => null,
    'trapFocus' => true,
])

<x-action-modal :request-error-message="$requestErrorMessage" :show="$show" :id="$id" :title="$title" :close-action="$closeAction" role="alertdialog" :busy-action="$busyAction" :restore-focus="$busyAction === null" initial-focus="[data-confirm-cancel]" class="confirmation-overlay">
    <x-slot:heading>
        <div class="flex items-start gap-4">
            <span class="confirmation-icon confirmation-icon-{{ $tone }}" aria-hidden="true">
                <x-nav-icon :name="$tone === 'danger' ? 'alert-triangle' : 'help'" class="size-6" />
            </span>
            <div><p class="eyebrow">{{ $eyebrow }}</p><h2 id="{{ $id }}-title">{{ $title }}</h2></div>
        </div>
    </x-slot:heading>
    <div class="confirmation-copy">{{ $slot }}</div>
    <x-slot:actions>{{ $actions }}</x-slot:actions>
</x-action-modal>
