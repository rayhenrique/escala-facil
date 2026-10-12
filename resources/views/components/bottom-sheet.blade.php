@props(['requestErrorMessage' => 'Não foi possível concluir a solicitação. Confira os dados e tente novamente.',
    'show' => false, 'id', 'title', 'eyebrow' => null, 'closeAction', 'submitAction' => null, 'maxWidth' => 'lg', 'trackChanges' => false, 'initialFocus' => null, 'returnFocus' => null])
{{-- Responsive sheet: the action-modal owns geometry, focus and lifecycle. --}}
<x-action-modal :request-error-message="$requestErrorMessage" :show="$show" :id="$id" :title="$title" :eyebrow="$eyebrow" :close-action="$closeAction" :submit-action="$submitAction" :max-width="$maxWidth" :track-changes="$trackChanges" :initial-focus="$initialFocus" :return-focus="$returnFocus" {{ $attributes }}>
    @isset($heading)
        <x-slot:heading>{{ $heading }}</x-slot:heading>
    @endisset
    {{ $slot }}
    <x-slot:actions>{{ $actions }}</x-slot:actions>
</x-action-modal>
