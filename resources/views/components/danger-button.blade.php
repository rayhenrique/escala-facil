@props(['type' => 'submit'])
<x-action-button variant="danger" :type="$type" {{ $attributes }}>{{ $slot }}</x-action-button>
