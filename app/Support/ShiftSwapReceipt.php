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
            'requester',
            'substitute',
            'manager',
        ]);

        return [
            'reference' => sprintf('TROCA-%06d', $shiftSwap->id),
            'shift_swap_id' => $shiftSwap->id,
            'event' => $event->value,
            'event_label' => $event->label(),
            'status' => $shiftSwap->status->value,
            'status_label' => $shiftSwap->status->label(),
            'recorded_at' => $shiftSwap->updated_at->toIso8601String(),
            'shift_date' => $shiftSwap->shift->date->format('Y-m-d'),
            'start_time' => substr((string) $shiftSwap->shift->start_time, 0, 5),
            'end_time' => substr((string) $shiftSwap->shift->end_time, 0, 5),
            'sector' => $shiftSwap->shift->sector->name,
            'category' => $shiftSwap->shift->category->name,
            'requester' => $shiftSwap->requester->name,
            'substitute' => $shiftSwap->substitute->name,
            'manager' => $shiftSwap->manager?->name,
        ];
    }
}
