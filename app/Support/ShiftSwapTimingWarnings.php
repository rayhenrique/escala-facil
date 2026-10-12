<?php

namespace App\Support;

use App\Enums\ShiftStatus;
use App\Models\Shift;
use App\Models\User;

class ShiftSwapTimingWarnings
{
    public function make(Shift $shift, User $substitute, ?Shift $counterpart, User $requester): array
    {
        $warnings = [];
        foreach ([[$substitute, $shift], [$requester, $counterpart]] as [$recipient, $candidate]) {
            if (! $candidate) {
                continue;
            }
            $pool = Shift::query()->where('user_id', $recipient->id)->where('status', ShiftStatus::Scheduled)
                ->whereBetween('date', [$candidate->period()->start->subDay()->toDateString(), $candidate->period()->end->addDay()->endOfDay()])
                ->whereNotIn('id', array_filter([$shift->id, $counterpart?->id]))->orderBy('id')->get();
            foreach ($pool as $existing) {
                if (! ShiftCoverage::findDoubleShiftIn(collect([$existing]), $recipient->id, $candidate)) {
                    continue;
                }
                $kind = $candidate->period()->overlaps($existing->period()) ? 'Conflito de horários' : 'Dobra';
                $warnings[] = $kind.': '.$recipient->name.' já tem o plantão #'.$existing->id.' em '.ShiftCoverage::doubleShiftLabel($existing).'. Revise os plantões; a gerência decidirá com essa informação.';
            }
        }

        return $warnings;
    }
}
