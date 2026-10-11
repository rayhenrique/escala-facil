@props(['entity', 'record', 'from' => null, 'context' => []])
<a href="{{ \App\Support\OperationalNavigation::url('audit', ['entity' => $entity, 'record' => $record], $from, $context) }}" wire:navigate class="table-action min-h-10" aria-label="Histórico de {{ mb_strtolower(\App\Models\OperationalAuditEvent::ENTITIES[$entity]) }} #{{ $record }}">Histórico</a>
