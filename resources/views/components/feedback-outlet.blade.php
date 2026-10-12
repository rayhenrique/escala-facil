<div x-data class="toast-region" data-feedback-outlet>
    <div class="toast-message" x-show="Object.values($store.operationalFeedback.pending).some(count => count > 0) && ! $store.operationalFeedback.modalOpen && ! $store.operationalFeedback.authInline" x-cloak><x-loading-state label="Processando solicitação…" /></div>
    <x-operational-state />
    <template x-for="item in $store.operationalFeedback.messages" :key="item.id">
        <x-toast message="" client-message="item.message" client-tone="item.tone" client-id="item.id" />
    </template>
</div>
