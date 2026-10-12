<?php

namespace App\Support;

use App\Enums\ShiftStatus;
use App\Enums\ShiftSwapStatus;
use App\Models\Shift;
use App\Models\ShiftSwap;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

class ShiftSwapAnalysis
{
    /** The same snapshot is used for review and again inside the decision transaction. */
    public function make(ShiftSwap $swap, User $manager): array
    {
        Gate::forUser($manager)->authorize('viewAny', ShiftSwap::class);
        $swap->loadMissing(['shift.sector', 'shift.category', 'substituteShift.sector', 'substituteShift.category', 'requester', 'substitute']);
        $eligibility = app(ShiftSwapApproval::class)->inspect($swap);
        $targets = collect([[$swap->substitute, $swap->shift], [$swap->requester, $swap->substituteShift]])
            ->filter(fn ($pair) => $pair[0] !== null && $pair[1] !== null);
        $dates = $targets->map(fn ($pair) => $pair[1]->period()->start)->sort();
        $pool = $dates->isEmpty() ? collect() : Shift::query()->whereIn('user_id', [$swap->requester_id, $swap->substitute_id])
            ->where('status', ShiftStatus::Scheduled)
            ->whereBetween('date', [$dates->first()->subDay()->toDateString(), $dates->last()->addDay()->endOfDay()])
            ->whereNotIn('id', array_filter([$swap->shift_id, $swap->substitute_shift_id]))
            ->orderBy('id')->get();
        $warnings = [];
        foreach ($targets as [$recipient, $candidate]) {
            foreach ($pool->where('user_id', $recipient->id) as $existing) {
                if (! $candidate->period()->overlaps($existing->period(), includeAdjacent: true)) {
                    continue;
                }
                $kind = $candidate->period()->overlaps($existing->period()) ? 'Conflito de horários' : 'Dobra';
                $warnings[] = $kind.': '.$recipient->name.' receberia o plantão #'.$candidate->id.' e já tem o plantão #'.$existing->id.' em '.ShiftCoverage::doubleShiftLabel($existing).'. '.($kind === 'Dobra' ? 'Aprove somente se a dobra for autorizada.' : 'Avalie a cobertura antes de aprovar.');
            }
        }
        $quotas = [];
        foreach ($eligibility['quotas'] as $quota) {
            $quotas[] = $quota['name'].': '.$quota['count'].' de '.$quota['limit'].' trocas aprovadas em '.$quota['month'].'.'
                .($swap->status === ShiftSwapStatus::PendingManager && $quota['count'] < $quota['limit'] ? ' Esta aprovação acrescenta 1 participação: '.($quota['count'] + 1).' de '.$quota['limit'].'.' : '');
        }

        // Session, password and read-notification changes cannot invalidate a decision.
        $shiftFields = ['id', 'date', 'end_date', 'start_time', 'end_time', 'sector_id', 'category_id', 'user_id', 'status'];
        $userFields = ['id', 'name', 'role', 'sector_id', 'category_id', 'requires_activation'];

        return [
            'status' => $swap->status->value,
            'status_label' => $swap->status->label(),
            'reference' => sprintf('TROCA-%06d', $swap->id),
            'shift' => $swap->shift?->period()->datedLabel() ?? 'Plantão #'.$swap->shift_id.' indisponível',
            'location' => ($swap->shift?->sector?->name ?? 'Setor indisponível').' · '.($swap->shift?->category?->name ?? 'Categoria indisponível'),
            'requester' => $swap->requester?->name ?? 'Solicitante indisponível',
            'substitute' => $swap->substitute?->name ?? 'Substituto indisponível',
            'counterpart' => $swap->substitute_shift_id ? ($swap->substituteShift?->period()->datedLabel() ?? 'Plantão #'.$swap->substitute_shift_id.' indisponível') : null,
            'counterpart_location' => $swap->substitute_shift_id ? ($swap->substituteShift?->sector?->name ?? 'Setor indisponível').' · '.($swap->substituteShift?->category?->name ?? 'Categoria indisponível') : null,
            'warnings' => $warnings,
            'quotas' => $quotas,
            'assignments' => $eligibility['assignments'],
            'approval_blockers' => $eligibility['blockers'],
            'can_approve' => $eligibility['blockers'] === [],
            'can_reject' => Gate::forUser($manager)->allows('reject', $swap),
            'fingerprint' => hash('sha256', json_encode([
                $swap->only(['id', 'shift_id', 'substitute_shift_id', 'requester_id', 'substitute_id', 'status', 'manager_id', 'created_at']),
                $swap->shift?->only($shiftFields), $swap->substituteShift?->only($shiftFields),
                $swap->requester?->only($userFields), $swap->substitute?->only($userFields),
                $pool->map(fn (Shift $shift) => $shift->only($shiftFields))->all(), $eligibility['quotas'],
            ], JSON_THROW_ON_ERROR)),
        ];
    }
}
