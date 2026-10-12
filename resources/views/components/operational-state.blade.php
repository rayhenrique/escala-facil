@props(['inline' => false])
@php $scope = isset($this) && $this instanceof \Livewire\Component ? $this->getId() : ''; @endphp
<div x-data x-show="$store.operationalFeedback.visible(@js($scope), @js($inline)){{ $inline ? ' && (! $el.closest(\'.filter-dialog-inline\') || !! $el.closest(\'[aria-modal=true]\'))' : '' }}" x-cloak {{ $attributes->class(['toast-message toast-error' => ! $inline, 'mt-4 rounded-lg border border-line bg-canvas p-3' => $inline]) }} @if ($inline) data-operational-inline @endif>
    <div class="min-w-0 space-y-3">
        <p tabindex="-1" role="alert" data-operation-error class="text-sm leading-6 text-ink" x-text="$store.operationalFeedback.offline ? 'A conexão está indisponível. Seus dados continuam nesta tela. Nenhuma operação será reenviada automaticamente.' : $store.operationalFeedback.failure?.message"></p>
        <p x-show="$store.operationalFeedback.connectionChecked" class="text-sm text-muted" role="status">A conexão respondeu. Confira o resultado da operação e revise as informações antes de continuar.</p>
        <div class="flex flex-wrap gap-2">
            <button type="button" @click="$store.operationalFeedback.checkConnection()" :disabled="$store.operationalFeedback.checking" class="button-secondary min-h-11"><span x-text="$store.operationalFeedback.checking ? 'Verificando…' : 'Verificar conexão'">Verificar conexão</span></button>
            <button type="button" x-show="$store.operationalFeedback.failure && ! $store.operationalFeedback.failure.mutable && ! ['expired', 'forbidden'].includes($store.operationalFeedback.failure.kind)" @click="$store.operationalFeedback.refreshQuery()" class="button-secondary min-h-11">Tentar atualizar consulta</button>
            <button type="button" x-show="$store.operationalFeedback.failure?.kind === 'expired'" @click="window.location.reload()" class="button-secondary min-h-11">Atualizar página para entrar</button>
        </div>
    </div>
</div>
