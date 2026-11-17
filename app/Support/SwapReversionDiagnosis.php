<?php

namespace App\Support;

use App\Models\OperationalAuditEvent;
use App\Models\ShiftSwap;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Throwable;

/** Observations only. The existing review remains the sole source of business blockers. */
class SwapReversionDiagnosis
{
    private const SHIFT_FIELDS = ['date', 'start_time', 'end_time', 'end_date', 'sector_id', 'category_id', 'user_id', 'status'];

    public function inspect(ShiftSwap $swap, ?User $manager = null, ?string $expectedSnapshot = null): array
    {
        $swap->loadMissing(['shift', 'substituteShift']);
        $ids = array_values(array_filter([$swap->shift_id, $swap->substitute_shift_id]));
        $swapEvents = OperationalAuditEvent::query()->where('entity_type', 'swap')->where('entity_id', $swap->id)->orderBy('id')->get();
        $shiftEvents = OperationalAuditEvent::query()->where('entity_type', 'shift')->whereIn('entity_id', $ids)->orderBy('id')->get();
        $approvals = $swapEvents->where('action', 'approved')->values();
        $approval = $approvals->count() === 1 ? $approvals->first() : null;
        $approvalChanges = is_array($approval?->changes) ? $approval->changes : [];
        $embedded = $approvalChanges['approval_state']['after'] ?? null;
        $review = null;
        $error = null;
        try {
            $review = app(ShiftSwapReversion::class)->inspect($swap);
        } catch (Throwable $exception) {
            // A malformed historical payload must be visible as an error, never
            // reported as a clean bill of health or permission to change data.
            $error = ['class' => $exception::class, 'message' => $exception->getMessage()];
        }
        $state = $review['approval_state'] ?? null;
        $source = match (true) {
            $approvals->count() === 0 => 'missing_approval_event',
            $approvals->count() > 1 => 'multiple_approval_events',
            $embedded !== null => 'embedded',
            $state !== null => 'reconstructed_legacy',
            default => 'legacy_unavailable',
        };
        $shifts = [];
        foreach (['shift' => $swap->shift_id, 'substituteShift' => $swap->substitute_shift_id] as $relation => $id) {
            if ($id === null) {
                continue;
            }
            $shift = $swap->getRelation($relation);
            $current = $shift ? app(OperationalAudit::class)->snapshot($shift) : null;
            $expected = $state['after'][$id] ?? null;
            $differences = [];
            if (is_array($expected) && $current !== null) {
                foreach (array_unique([...array_keys($expected), ...array_keys($current)]) as $field) {
                    if (! array_key_exists($field, $expected) || ! array_key_exists($field, $current) || $expected[$field] !== $current[$field]) {
                        $differences[$field] = ['expected' => $expected[$field] ?? null, 'current' => $current[$field] ?? null];
                    }
                }
            }
            $shifts[] = [
                'id' => $id, 'exists' => $shift !== null, 'current' => $current,
                'expected_before' => $state['before'][$id] ?? null, 'expected_after' => $expected,
                'strict_differences' => $differences,
                'review_snapshot_matches' => $expected !== null && $current !== null ? $current == $expected : null,
                'legacy_trail' => $this->trail($shiftEvents->where('entity_id', $id), $approval?->id, self::SHIFT_FIELDS),
            ];
        }
        $permissions = ['manager_id' => $manager?->id, 'evaluated' => $manager !== null];
        if ($manager) {
            foreach (['viewAny', 'reviewReversion'] as $ability) {
                $permission = Gate::forUser($manager)->inspect($ability, $ability === 'viewAny' ? ShiftSwap::class : $swap);
                $permissions[$ability] = ['allowed' => $permission->allowed(), 'message' => $permission->message()];
            }
        }

        return [
            'read_only' => true, 'version' => config('version.current'), 'timezone' => config('app.timezone'), 'observed_at' => now()->toIso8601String(),
            'swap' => $swap->only(['id', 'status', ...ShiftSwapReversion::BINDINGS, 'manager_id', 'approved_at', 'created_at', 'updated_at']),
            'authorization' => $permissions,
            'approval' => [
                'count' => $approvals->count(), 'source' => $source,
                'embedded_key_present' => array_key_exists('approval_state', $approvalChanges),
                'embedded_type' => get_debug_type($embedded),
                'embedded_bindings' => is_array($embedded) ? ($embedded['bindings'] ?? null) : null,
                'embedded_shape' => $this->shape($embedded, $ids),
                'current_bindings' => $swap->only(ShiftSwapReversion::BINDINGS),
                'legacy_swap_trail' => $this->trail($swapEvents, $approval?->id, ShiftSwapReversion::BINDINGS),
                'events' => $approvals->map(fn ($event) => [
                    'id' => $event->id, 'occurred_at' => $event->getRawOriginal('occurred_at'), 'operation_id' => $event->operation_id,
                ])->all(),
            ],
            'shifts' => $shifts,
            'shift_events' => $shiftEvents->map(fn ($event) => [
                'id' => $event->id, 'shift_id' => $event->entity_id, 'action' => $event->action,
                'occurred_at' => $event->getRawOriginal('occurred_at'), 'operation_id' => $event->operation_id,
                'relation_to_approval' => ! $approval ? 'no_unique_approval' : ($event->id < $approval->id ? 'before_approval' : ($event->operation_id === $approval->operation_id ? 'same_approval_operation' : 'later_other_operation')),
                'changes' => array_intersect_key(is_array($event->changes) ? $event->changes : [], array_flip(self::SHIFT_FIELDS)),
            ])->values()->all(),
            'review' => [
                'can_revert' => $review['can_revert'] ?? null, 'blocks' => $review['blocks'] ?? [], 'warnings' => $review['warnings'] ?? [],
                'later_events' => $review['later_events'] ?? [], 'fingerprint' => $review['fingerprint'] ?? null,
                'expected_snapshot_matches' => $expectedSnapshot !== null && $review ? hash_equals($review['fingerprint'], $expectedSnapshot) : null,
                'error' => $error,
            ],
            'limitations' => ['Resultado de leitura sem locks de confirmação; não executa RevertShiftSwapAction.', 'Sem ID de gerente, autorização não foi avaliada.', 'Não determina fatos ausentes do histórico nem substitui comprovantes originais.'],
        ];
    }

    private function shape(mixed $state, array $ids): array
    {
        $result = [];
        foreach (['before', 'after'] as $point) {
            $section = is_array($state) ? ($state[$point] ?? null) : null;
            foreach ($ids as $id) {
                $snapshot = is_array($section) ? ($section[$id] ?? null) : null;
                $result[$point][$id] = ['type' => get_debug_type($snapshot),
                    'missing_fields' => array_values(array_diff(self::SHIFT_FIELDS, is_array($snapshot) ? array_keys($snapshot) : []))];
            }
        }

        return $result;
    }

    private function trail(Collection $events, ?int $approvalId, array $fields): array
    {
        $prior = $approvalId ? $events->filter(fn ($event) => $event->id < $approvalId)->values() : collect();
        $values = [];
        foreach ($prior as $event) {
            foreach ($fields as $field) {
                if (isset($event->changes[$field])) {
                    $values[$field] = $event->changes[$field]['after'] ?? null;
                }
            }
        }

        return ['event_ids' => $prior->pluck('id')->all(), 'starts_with_created' => $prior->first()?->action === 'created',
            'last_observed_values' => $values, 'unobserved_fields' => array_values(array_diff($fields, array_keys($values)))];
    }
}
