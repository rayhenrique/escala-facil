@props(['label' => 'Carregando…'])
<div {{ $attributes->class(['flex items-center gap-2 text-sm text-muted']) }} role="status">
    <x-nav-icon name="clock" class="size-5 shrink-0" />
    <span>{{ $label }}</span>
</div>
