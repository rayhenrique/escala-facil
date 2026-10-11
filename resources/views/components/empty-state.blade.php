@props(['title', 'description' => null, 'icon' => null])
<div {{ $attributes->class(['empty-state']) }}>
    @if ($icon)<x-nav-icon :name="$icon" class="mx-auto mb-3 size-7 text-primary" />@endif
    <p class="font-semibold text-ink">{{ $title }}</p>
    @if ($description)<p class="mt-2 text-sm">{{ $description }}</p>@endif
    {{ $slot }}
</div>
