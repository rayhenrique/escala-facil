@props(['type' => 'submit'])
<x-action-button variant="primary" :type="$type" {{ $attributes }}>{{ $slot }}</x-action-button>
