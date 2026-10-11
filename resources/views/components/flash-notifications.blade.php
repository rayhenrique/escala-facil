@php
    $successMessage = session('status');
    $errorMessage = session('error');
@endphp
@if ($successMessage || $errorMessage)
    <div class="toast-region">
        @if ($successMessage)<x-toast :message="$successMessage" wire:key="toast-success-{{ md5($successMessage) }}" />@endif
        @if ($errorMessage)<x-toast :message="$errorMessage" tone="error" wire:key="toast-error-{{ md5($errorMessage) }}" />@endif
    </div>
@endif
