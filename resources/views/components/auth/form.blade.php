@props(['action', 'actionUrl' => null])

<form {{ $attributes->class(['auth-form']) }} method="post" @if ($actionUrl) action="{{ $actionUrl }}" @endif wire:submit="{{ $action }}" data-auth-form novalidate wire:loading.attr="aria-busy" wire:target="{{ $action }}">
    @csrf
    <div class="auth-request-error" data-auth-request-error hidden role="alert" tabindex="-1">
        <p data-auth-request-message></p>
        <button type="button" class="mt-2 min-h-10 font-semibold underline underline-offset-4" data-auth-reload hidden>Atualizar página</button>
    </div>
    {{ $slot }}
    <x-operational-state inline />
</form>
