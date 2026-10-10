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
        $swap->loadMissing(['shift.sector', 'shift.category', 'substituteShift.sector', 'substituteShift.category', 'requester', 'substitute']);
        $targets = collect([[$swap->substitute, $swap->shift], [$swap->requester, $swap->substituteShift]])
            ->filter(fn ($pair) => $pair[1] !== null);
        $dates = $targets->map(fn ($pair) => $pair[1]->period()->start)->sort();
        $pool = Shift::query()->whereIn('user_id', [$swap->requester_id, $swap->substitute_id])
            ->where('status', ShiftStatus::Scheduled)
            ->whereBetween('date', [$dates->first()->subDay()->toDateString(), $dates->last()->addDay()->endOfDay()])
            ->whereNotIn('id', array_filter([$swap->shift_id, $swap->substitute_shift_id]))
            ->orderBy('id')->get();
        $reference = $swap->created_at ?? now();
        $counts = ShiftSwap::query()->where('status', ShiftSwapStatus::Approved)
            ->whereMonth('created_at', $reference->month)->whereYear('created_at', $reference->year)
            ->where(fn ($query) => $query->whereIn('requester_id', [$swap->requester_id, $swap->substitute_id])
                ->orWhereIn('substitute_id', [$swap->requester_id, $swap->substitute_id]))
            ->get(['id', 'requester_id', 'substitute_id']);
        $warnings = [];
        foreach ($targets as [$recipient, $candidate]) {
            foreach ($pool->where('user_id', $recipient->id) as $existing) {
                if (! $candidate->period()->overlaps($existing->period(), includeAdjacent: true)) {
                    continue;
                }
                $kind = $candidate->period()->overlaps($existing->period()) ? 'Conflito de horários' : 'Dobra';
                $warnings[] = $kind.': '.$recipient->name.' já tem o plantão #'.$existing->id.' em '.ShiftCoverage::doubleShiftLabel($existing).'. '.($kind === 'Dobra' ? 'Aprove somente se a dobra for autorizada.' : 'Avalie a cobertura antes de aprovar.');
            }
        }
        $quotas = [];
        foreach ([$swap->requester, $swap->substitute] as $participant) {
            $count = $counts->filter(fn ($item) => $item->requester_id === $participant->id || $item->substitute_id === $participant->id)->count();
            $quotas[] = $participant->name.': '.$count.' de '.User::MONTHLY_SWAP_LIMIT.' trocas aprovadas em '.$reference->format('m/Y').'.';
            if ($count >= User::MONTHLY_SWAP_LIMIT) {
                $warnings[] = 'Limite mensal atingido por '.$participant->name.'. A aprovação está indisponível.';
            }
        }

        return [
            'shift' => $swap->shift->period()->datedLabel(),
            'location' => $swap->shift->sector->name.' · '.$swap->shift->category->name,
            'requester' => $swap->requester->name,
            'substitute' => $swap->substitute->name,
            'counterpart' => $swap->substituteShift?->period()->datedLabel(),
            'counterpart_location' => $swap->substituteShift ? $swap->substituteShift->sector->name.' · '.$swap->substituteShift->category->name : null,
            'warnings' => $warnings,
            'quotas' => $quotas,
            'can_approve' => Gate::forUser($manager)->allows('approve', $swap),
            'fingerprint' => hash('sha256', json_encode([
                $swap->getAttributes(), $swap->shift->getAttributes(), $swap->substituteShift?->getAttributes(),
                $swap->requester->getAttributes(), $swap->substitute->getAttributes(),
                $pool->map->getAttributes()->all(), $counts->sortBy('id')->map->getAttributes()->values()->all(),
            ], JSON_THROW_ON_ERROR)),
        ];
    }
}
