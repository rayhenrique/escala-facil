@props(['back', 'label' => null, 'links' => []])

<nav class="context-navigation" aria-label="Navegação contextual">
    @if ($back)<a href="{{ $back['url'] }}" wire:navigate class="text-link inline-flex min-h-10 items-center">← {{ $back['label'] }}</a>@endif
    @if ($label)<p id="context-selected-record" class="text-sm font-semibold text-primary" tabindex="-1" data-context-target>{{ $label }}</p>@endif
    @foreach ($links as $name => $url)
        <a href="{{ $url }}" wire:navigate class="text-link inline-flex min-h-10 items-center">{{ $name }}</a>
    @endforeach
</nav>
