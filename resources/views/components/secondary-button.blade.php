@props(['type' => 'button'])
<x-action-button variant="secondary" :type="$type" {{ $attributes }}>{{ $slot }}</x-action-button>
