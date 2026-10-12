@props(['message', 'tone' => 'success', 'clientMessage' => null, 'clientTone' => null, 'clientId' => null])
@php $isError = $tone === 'error'; @endphp
<div x-data="toastMessage({ duration: {{ $clientTone ? $clientTone." === 'error' ? 0 : 5500" : ($isError ? 0 : 5500) }}{{ $clientId ? ', onDismiss: () => $store.operationalFeedback.dismiss('.$clientId.')' : '' }} })" x-show="visible" x-transition.opacity.duration.150ms
    @mouseenter="pause()" @mouseleave="resume()" @focusin="pause()" @focusout="$nextTick(() => resume())"
    {{ $attributes->class(['toast-message', $clientTone ? '' : ($isError ? 'toast-error' : 'toast-success')]) }} role="{{ $isError ? 'alert' : 'status' }}" @if ($clientTone) :class="{{ $clientTone }} === 'error' ? 'toast-error' : 'toast-success'" :role="{{ $clientTone }} === 'error' ? 'alert' : 'status'" @endif>
    <span class="toast-icon" aria-hidden="true">
        @if ($clientTone)<x-nav-icon name="alert-triangle" class="size-5" x-show="{{ $clientTone }} === 'error'" /><x-nav-icon name="check-circle" class="size-5" x-show="{{ $clientTone }} !== 'error'" />
        @else<x-nav-icon :name="$isError ? 'alert-triangle' : 'check-circle'" class="size-5" />@endif
    </span>
    <div class="min-w-0 flex-1"><strong @if ($clientTone) x-text="{{ $clientTone }} === 'error' ? 'Não foi possível concluir' : 'Ação concluída'" @endif>{{ $isError ? 'Não foi possível concluir' : 'Ação concluída' }}</strong><p @if ($clientMessage) x-text="{{ $clientMessage }}" @endif>{{ $message }}</p></div>
    <button type="button" @click="dismiss()" class="toast-close" aria-label="{{ $isError ? 'Fechar mensagem de erro' : 'Fechar mensagem de sucesso' }}" @if ($clientTone) :aria-label="{{ $clientTone }} === 'error' ? 'Fechar mensagem de erro' : 'Fechar mensagem de sucesso'" @endif><x-nav-icon name="close" class="size-4" /></button>
</div>
