@props(['status', 'label' => null])

@php
    $value = $status instanceof \BackedEnum ? $status->value : (string) $status;
    $resolvedLabel = $label ?? (method_exists($status, 'label') ? $status->label() : $value);
@endphp

<span {{ $attributes->class(['status-badge', 'status-'.$value]) }}>{{ $resolvedLabel }}</span>
