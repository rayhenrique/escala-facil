@props(['action', 'label', 'busy'])

{{-- wire:submit já bloqueia o envio e restaura o botão, inclusive em falhas. --}}
<button type="submit" class="button-primary w-full" wire:target="{{ $action }}">
    <span wire:loading.remove wire:target="{{ $action }}">{{ $label }}</span>
    <span wire:loading wire:target="{{ $action }}" role="status">{{ $busy }}</span>
</button>
