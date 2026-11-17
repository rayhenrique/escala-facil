<?php

namespace App\Support;

use App\Enums\ShiftSwapStatus;
use App\Models\OperationalAuditEvent;
use App\Models\ShiftSwap;

class AdministrativeSwapCorrection
{
    public function review(ShiftSwap $swap): array
    {
        $review = app(ShiftSwapReversion::class)->inspect($swap);
        $swap->loadMissing('rectification');
        $approvalIds = OperationalAuditEvent::query()->where('entity_type', 'swap')->where('entity_id', $swap->id)->where('action', 'approved')->orderBy('id')->pluck('id')->all();
        $ids = array_filter([$swap->shift_id, $swap->substitute_shift_id]);
        $auditEvents = OperationalAuditEvent::query()->where(fn ($query) => $query
            ->where(fn ($swapEvents) => $swapEvents->where('entity_type', 'swap')->where('entity_id', $swap->id))
            ->orWhere(fn ($shiftEvents) => $shiftEvents->where('entity_type', 'shift')->whereIn('entity_id', $ids)))
            ->orderBy('id')->get(['id', 'entity_type', 'entity_id', 'action', 'occurred_at', 'operation_id']);
        $pendingIds = ShiftSwap::query()->whereKeyNot($swap->id)->whereIn('status', [ShiftSwapStatus::PendingProfessional, ShiftSwapStatus::PendingManager])
            ->where(fn ($query) => $query->whereIn('shift_id', $ids)->orWhereIn('substitute_shift_id', $ids))->orderBy('id')->pluck('id')->all();
        $evidence = [
            'timezone' => config('app.timezone'),
            'swap' => $swap->only(['id', ...ShiftSwapReversion::BINDINGS, 'status', 'manager_id', 'approved_at', 'created_at', 'updated_at']),
            'approval_event_ids' => $approvalIds, 'approval_state' => $review['approval_state'],
            'audit_events' => $auditEvents->toArray(), 'pending_swap_ids' => $pendingIds,
            'blockers' => $review['blocks'], 'later_events' => $review['later_events'],
            'shifts' => collect([$swap->shift, $swap->substituteShift])->filter()->map(fn ($shift) => ['id' => $shift->id] + app(OperationalAudit::class)->snapshot($shift))->values()->all(),
        ];

        return ['can_register' => $swap->status === ShiftSwapStatus::Approved && ! $review['can_revert'] && ! $swap->rectification,
            'existing_record' => $swap->rectification?->id, 'evidence' => $evidence,
            'fingerprint' => hash('sha256', json_encode([$review['fingerprint'], $swap->rectification?->id, $auditEvents->pluck('id')->all(), $pendingIds], JSON_THROW_ON_ERROR))];
    }
}
