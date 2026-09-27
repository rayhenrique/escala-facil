@php
    $successMessage = session('status');
    $errorMessage = session('error');
@endphp

@if ($successMessage || $errorMessage)
    <div class="toast-region" aria-live="polite" aria-atomic="true">
        @if ($successMessage)
            <div
                x-data="{ visible: true }"
                x-show="visible"
                x-transition.opacity.duration.200ms
                x-init="setTimeout(() => visible = false, 5500)"
                class="toast-message toast-success"
                role="status"
            >
                <span class="toast-icon" aria-hidden="true"><x-nav-icon name="check-circle" class="size-5" /></span>
                <div class="min-w-0 flex-1">
                    <strong>Ação concluída</strong>
                    <p>{{ $successMessage }}</p>
                </div>
                <button type="button" @click="visible = false" class="toast-close" aria-label="Fechar mensagem de sucesso">
                    <x-nav-icon name="close" class="size-4" />
                </button>
            </div>
        @endif

        @if ($errorMessage)
            <div
                x-data="{ visible: true }"
                x-show="visible"
                x-transition.opacity.duration.200ms
                class="toast-message toast-error"
                role="alert"
            >
                <span class="toast-icon" aria-hidden="true"><x-nav-icon name="alert-triangle" class="size-5" /></span>
                <div class="min-w-0 flex-1">
                    <strong>Não foi possível concluir</strong>
                    <p>{{ $errorMessage }}</p>
                </div>
                <button type="button" @click="visible = false" class="toast-close" aria-label="Fechar mensagem de erro">
                    <x-nav-icon name="close" class="size-4" />
                </button>
            </div>
        @endif
    </div>
@endif
