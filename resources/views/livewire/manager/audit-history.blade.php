<div>
    <p class="eyebrow">Gerência</p>
    <h1 class="page-title">Histórico operacional</h1>
    <p class="page-description">Consulte quem alterou os registros, quando e quais informações mudaram.</p>
    <x-context-navigation :back="$this->backNavigation()" />
    <fieldset class="panel mt-6">
        <legend class="px-2 text-sm font-semibold">Filtrar histórico</legend>
        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
            <div>
                <label for="audit-entity" class="field-label">Tipo de registro</label>
                <select id="audit-entity" wire:model.live="entity" class="field-input" @error('entity') aria-invalid="true" aria-describedby="audit-entity-error" @enderror>
                    <option value="">Todos os tipos</option>
                    @foreach (\App\Models\OperationalAuditEvent::ENTITIES as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach
                </select>
                @error('entity')<p id="audit-entity-error" class="field-error">{{ $message }}</p>@enderror
            </div>
            <div>
                <label for="audit-record" class="field-label">ID do registro</label>
                <input id="audit-record" type="number" min="1" step="1" wire:model.live.debounce.350ms="record" class="field-input" @error('record') aria-invalid="true" aria-describedby="audit-record-error" @enderror>
                @error('record')<p id="audit-record-error" class="field-error">{{ $message }}</p>@enderror
            </div>
            <div>
                <label for="audit-author" class="field-label">Nome do autor</label>
                <input id="audit-author" type="search" maxlength="100" wire:model.live.debounce.350ms="author" class="field-input" @error('author') aria-invalid="true" aria-describedby="audit-author-error" @enderror>
                @error('author')<p id="audit-author-error" class="field-error">{{ $message }}</p>@enderror
            </div>
            <div>
                <label for="audit-action" class="field-label">Ação</label>
                <select id="audit-action" wire:model.live="action" class="field-input" @error('action') aria-invalid="true" aria-describedby="audit-action-error" @enderror>
                    <option value="">Todas as ações</option>
                    @foreach (\App\Models\OperationalAuditEvent::ACTIONS as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach
                </select>
                @error('action')<p id="audit-action-error" class="field-error">{{ $message }}</p>@enderror
            </div>
            <div>
                <label for="audit-from" class="field-label">Alterado a partir de</label>
                <input id="audit-from" type="date" wire:model.live="dateFrom" class="field-input" @error('dateFrom') aria-invalid="true" aria-describedby="audit-from-error" @enderror>
                @error('dateFrom')<p id="audit-from-error" class="field-error">{{ $message }}</p>@enderror
            </div>
            <div>
                <label for="audit-to" class="field-label">Alterado até</label>
                <input id="audit-to" type="date" wire:model.live="dateTo" class="field-input" @error('dateTo') aria-invalid="true" aria-describedby="audit-to-error" @enderror>
                @error('dateTo')<p id="audit-to-error" class="field-error">{{ $message }}</p>@enderror
            </div>
        </div>
        <div class="mt-4 flex flex-wrap items-center justify-between gap-3">
            <p class="text-sm text-muted">Datas incluem o dia inteiro. Combine tipo e ID para consultar um registro específico.</p>
            <button type="button" wire:click="clearFilters" class="button-secondary">Limpar filtros</button>
        </div>
    </fieldset>
    <p class="mt-5 text-sm text-muted" role="status">{{ $events->total() }} evento(s) encontrado(s). O histórico começa na instalação desta versão.</p>
    <section class="mt-5 space-y-4" aria-label="Eventos do histórico">
        @forelse ($events as $event)
            <article class="panel" wire:key="audit-event-{{ $event->id }}">
                <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                        <h2 class="text-lg font-semibold">{{ \App\Models\OperationalAuditEvent::ACTIONS[$event->action] }} · {{ \App\Models\OperationalAuditEvent::ENTITIES[$event->entity_type] }} #{{ $event->entity_id }}</h2>
                        <p class="mt-1 text-sm text-muted">{{ $event->author?->name ?? ($event->author_id ? 'Conta removida #'.$event->author_id : 'Sistema / operação sem sessão') }}@if ($event->author_id && $event->author)<span class="font-mono text-xs"> · #{{ $event->author_id }}</span>@endif</p>
                    </div>
                    <time datetime="{{ $event->occurred_at->toIso8601String() }}" class="font-mono text-xs text-muted">{{ $event->occurred_at->format('d/m/Y H:i:s') }} ({{ config('app.timezone') }})</time>
                </div>
                <dl class="mt-4 divide-y divide-line">
                    @foreach ($event->changes as $field => $change)
                        <div class="grid gap-2 py-3 sm:grid-cols-3">
                            <dt class="text-sm font-semibold">{{ \App\Support\OperationalAuditPresentation::field($field) }}</dt>
                            <dd class="min-w-0 break-words text-sm"><span class="meta-label">Anterior</span><p class="mt-1">{{ \App\Support\OperationalAuditPresentation::value($field, $change['before']) }}</p></dd>
                            <dd class="min-w-0 break-words text-sm"><span class="meta-label">Posterior</span><p class="mt-1">{{ \App\Support\OperationalAuditPresentation::value($field, $change['after']) }}</p></dd>
                        </div>
                    @endforeach
                </dl>
                @if ($event->entity_type === 'swap' && $event->action === 'reverted' && $event->reversal)
                    <p class="mt-3 text-sm"><strong>Justificativa administrativa:</strong> {{ $event->reversal->reason }}</p>
                    <p class="mt-2 text-sm text-muted">Reversão #{{ $event->reversal->id }} · Aprovação original #{{ $event->reversal->approval_event_id }}</p>
                @endif
                <p class="mt-3 break-all font-mono text-xs text-muted">Operação {{ $event->operation_id }}</p>
            </article>
        @empty
            <div class="empty-state"><p class="font-semibold text-ink">Nenhuma alteração corresponde aos filtros.</p><p class="mt-2 text-sm">Ajuste os filtros. Registros anteriores à instalação não recebem um histórico inventado.</p></div>
        @endforelse
    </section>
    <div class="mt-5">{{ $events->links() }}</div>
</div>
