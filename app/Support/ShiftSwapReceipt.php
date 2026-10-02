<?php

namespace App\Support;

use App\Enums\ShiftSwapEvent;
use App\Models\ShiftSwap;

class ShiftSwapReceipt
{
    /** @return array<string, mixed> */
    public function make(ShiftSwap $shiftSwap, ShiftSwapEvent $event): array
    {
        $shiftSwap->loadMissing([
            'shift.category',
            'shift.sector',
            'substituteShift.category',
            'substituteShift.sector',
            'requester',
            'substitute',
            'manager',
        ]);

        $hasCounterpart = $shiftSwap->substituteShift !== null;

        $requesterMovement = $hasCounterpart
            ? sprintf(
                '%s vai para o plantão do dia %s, das %s às %s (%s · %s)',
                $shiftSwap->requester->name,
                $shiftSwap->substituteShift->date->format('d/m/Y'),
                substr((string) $shiftSwap->substituteShift->start_time, 0, 5),
                substr((string) $shiftSwap->substituteShift->end_time, 0, 5),
                $shiftSwap->substituteShift->sector->name,
                $shiftSwap->substituteShift->category->name,
            )
            : null;

        $substituteMovement = sprintf(
            '%s vai para o plantão do dia %s, das %s às %s (%s · %s)',
            $shiftSwap->substitute->name,
            $shiftSwap->shift->date->format('d/m/Y'),
            substr((string) $shiftSwap->shift->start_time, 0, 5),
            substr((string) $shiftSwap->shift->end_time, 0, 5),
            $shiftSwap->shift->sector->name,
            $shiftSwap->shift->category->name,
        );

        return [
            'reference' => sprintf('TROCA-%06d', $shiftSwap->id),
            'shift_swap_id' => $shiftSwap->id,
            'event' => $event->value,
            'event_label' => $event->label(),
            'status' => $shiftSwap->status->value,
            'status_label' => $shiftSwap->status->label(),
            'recorded_at' => $shiftSwap->updated_at->toIso8601String(),
            'shift_date' => $shiftSwap->shift->date->format('Y-m-d'),
            'shift_date_label' => $shiftSwap->shift->date->format('d/m/Y'),
            'start_time' => substr((string) $shiftSwap->shift->start_time, 0, 5),
            'end_time' => substr((string) $shiftSwap->shift->end_time, 0, 5),
            'sector' => $shiftSwap->shift->sector->name,
            'category' => $shiftSwap->shift->category->name,
            'has_counterpart' => $hasCounterpart,
            'counterpart_shift_date' => $shiftSwap->substituteShift?->date->format('Y-m-d'),
            'counterpart_shift_date_label' => $shiftSwap->substituteShift?->date->format('d/m/Y'),
            'counterpart_start_time' => $shiftSwap->substituteShift ? substr((string) $shiftSwap->substituteShift->start_time, 0, 5) : null,
            'counterpart_end_time' => $shiftSwap->substituteShift ? substr((string) $shiftSwap->substituteShift->end_time, 0, 5) : null,
            'counterpart_sector' => $shiftSwap->substituteShift?->sector->name,
            'counterpart_category' => $shiftSwap->substituteShift?->category->name,
            'requester_movement' => $requesterMovement,
            'substitute_movement' => $substituteMovement,
            'rejection_reason' => $shiftSwap->rejection_reason,
            'requester' => $shiftSwap->requester->name,
            'substitute' => $shiftSwap->substitute->name,
            'manager' => $shiftSwap->manager?->name,
        ];
    }
}
