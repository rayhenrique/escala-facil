<?php

namespace App\Support;

use App\Enums\ShiftSwapEvent;
use App\Enums\ShiftSwapStatus;
use App\Models\ShiftSwap;

class ShiftSwapReceipt
{
    /** Resolve only valid receipt references; never consult or rewrite historical data. */
    public static function swapId(mixed $receipt): ?int
    {
        if (! is_array($receipt)) {
            return null;
        }
        $id = $receipt['shift_swap_id'] ?? null;
        if (is_int($id) || is_string($id)) {
            $valid = filter_var($id, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            if ($valid !== false) {
                return $valid;
            }
        }
        $reference = $receipt['reference'] ?? null;
        if (is_string($reference) && preg_match('/^TROCA-0*([1-9]\d*)$/D', $reference, $matches)) {
            $valid = filter_var($matches[1], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

            return $valid !== false ? $valid : null;
        }

        return null;
    }

    /** Consulta da situação atual; não substitui comprovantes de eventos anteriores. */
    public function current(ShiftSwap $swap): array
    {
        return $this->make($swap, match ($swap->status) {
            ShiftSwapStatus::PendingProfessional => ShiftSwapEvent::Requested,
            ShiftSwapStatus::PendingManager => ShiftSwapEvent::Accepted,
            ShiftSwapStatus::Approved => ShiftSwapEvent::Approved,
            ShiftSwapStatus::Rejected => ShiftSwapEvent::Rejected,
            ShiftSwapStatus::Reverted => ShiftSwapEvent::Reverted,
        });
    }

    /** Enriquecimento apenas para leitura de comprovantes antigos, sem regravar notificações. */
    public static function withTimeLabels(array $receipt): array
    {
        foreach (['' => 'shift_date', 'counterpart_' => 'counterpart_shift_date'] as $prefix => $dateKey) {
            if (empty($receipt[$dateKey])) {
                continue;
            }
            $period = new ShiftPeriod($receipt[$dateKey], $receipt[$prefix.'start_time'], $receipt[$prefix.'end_time'], $receipt[$prefix.'end_date'] ?? null);
            $receipt[$prefix.'end_date'] = $period->end->format('Y-m-d');
            $receipt[$prefix.'start_at'] = $period->start->toIso8601String();
            $receipt[$prefix.'end_at'] = $period->end->toIso8601String();
            $receipt[$prefix.'duration_minutes'] = $period->durationMinutes;
            $receipt[$prefix.'time_label'] = $period->timeLabel();
            $receipt[$prefix.'end_time_label'] = $period->endLabel().' · '.$period->durationLabel();
        }

        return $receipt;
    }

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
                $shiftSwap->substituteShift->period()->endLabel().' · '.$shiftSwap->substituteShift->period()->durationLabel(),
                $shiftSwap->substituteShift->sector->name,
                $shiftSwap->substituteShift->category->name,
            )
            : null;

        $substituteMovement = sprintf(
            '%s vai para o plantão do dia %s, das %s às %s (%s · %s)',
            $shiftSwap->substitute->name,
            $shiftSwap->shift->date->format('d/m/Y'),
            substr((string) $shiftSwap->shift->start_time, 0, 5),
            $shiftSwap->shift->period()->endLabel().' · '.$shiftSwap->shift->period()->durationLabel(),
            $shiftSwap->shift->sector->name,
            $shiftSwap->shift->category->name,
        );

        $reversal = $event === ShiftSwapEvent::Reverted ? $shiftSwap->reversal : null;
        if ($event === ShiftSwapEvent::Reverted) {
            $requesterMovement = $shiftSwap->requester->name.' voltou ao plantão #'.$shiftSwap->shift_id.': '.$shiftSwap->shift->period()->datedLabel().'.';
            $substituteMovement = $hasCounterpart
                ? $shiftSwap->substitute->name.' voltou ao plantão #'.$shiftSwap->substitute_shift_id.': '.$shiftSwap->substituteShift->period()->datedLabel().'.'
                : $shiftSwap->substitute->name.' deixou a cobertura do plantão #'.$shiftSwap->shift_id.'.';
        }

        return self::withTimeLabels([
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
            'end_date' => $shiftSwap->shift->period()->end->format('Y-m-d'),
            'sector' => $shiftSwap->shift->sector->name,
            'category' => $shiftSwap->shift->category->name,
            'has_counterpart' => $hasCounterpart,
            'counterpart_shift_date' => $shiftSwap->substituteShift?->date->format('Y-m-d'),
            'counterpart_shift_date_label' => $shiftSwap->substituteShift?->date->format('d/m/Y'),
            'counterpart_start_time' => $shiftSwap->substituteShift ? substr((string) $shiftSwap->substituteShift->start_time, 0, 5) : null,
            'counterpart_end_time' => $shiftSwap->substituteShift ? substr((string) $shiftSwap->substituteShift->end_time, 0, 5) : null,
            'counterpart_end_date' => $shiftSwap->substituteShift?->period()->end->format('Y-m-d'),
            'counterpart_sector' => $shiftSwap->substituteShift?->sector->name,
            'counterpart_category' => $shiftSwap->substituteShift?->category->name,
            'requester_movement' => $requesterMovement,
            'substitute_movement' => $substituteMovement,
            'rejection_reason' => $shiftSwap->rejection_reason,
            'reversion_reason' => $reversal?->reason,
            'reversal_id' => $reversal?->id,
            'requester' => $shiftSwap->requester->name,
            'substitute' => $shiftSwap->substitute->name,
            'manager' => $shiftSwap->manager?->name,
        ]);
    }
}
