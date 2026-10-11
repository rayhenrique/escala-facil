@props(['variant' => 'secondary', 'type' => 'button', 'icon' => null, 'label' => null, 'target' => null, 'busyLabel' => 'Processando…'])
@php $buttonClass = ['primary' => 'button-primary', 'secondary' => 'button-secondary', 'danger' => 'button-danger'][$variant] ?? 'button-secondary'; @endphp
<button {{ $attributes->class([$buttonClass, 'gap-2'])->merge(['type' => $type]) }} @if ($label) aria-label="{{ $label }}" @endif @if ($target) wire:loading.attr="disabled" wire:target="{{ $target }}" @endif>
    @if ($icon)<x-nav-icon :name="$icon" class="size-5 shrink-0" />@endif
    @if ($target)
        <span class="inline-grid">
            <span class="col-start-1 row-start-1" wire:loading.class="invisible" wire:target="{{ $target }}">{{ $slot }}</span>
            <span class="invisible col-start-1 row-start-1" wire:loading.class.remove="invisible" wire:target="{{ $target }}" role="status">{{ $busyLabel }}</span>
        </span>
    @else
        {{ $slot }}
    @endif
</button>
