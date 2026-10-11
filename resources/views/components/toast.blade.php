@props(['message', 'tone' => 'success'])
@php $isError = $tone === 'error'; @endphp
<div x-data="toastMessage({ duration: {{ $isError ? 0 : 5500 }} })" x-show="visible" x-transition.opacity.duration.150ms
    @mouseenter="pause()" @mouseleave="resume()" @focusin="pause()" @focusout="$nextTick(() => resume())"
    {{ $attributes->class(['toast-message', $isError ? 'toast-error' : 'toast-success']) }} role="{{ $isError ? 'alert' : 'status' }}">
    <span class="toast-icon" aria-hidden="true"><x-nav-icon :name="$isError ? 'alert-triangle' : 'check-circle'" class="size-5" /></span>
    <div class="min-w-0 flex-1"><strong>{{ $isError ? 'Não foi possível concluir' : 'Ação concluída' }}</strong><p>{{ $message }}</p></div>
    <button type="button" @click="dismiss()" class="toast-close" aria-label="{{ $isError ? 'Fechar mensagem de erro' : 'Fechar mensagem de sucesso' }}"><x-nav-icon name="close" class="size-4" /></button>
</div>
