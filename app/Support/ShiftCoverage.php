<?php

namespace App\Support;

use App\Enums\ShiftStatus;
use App\Models\Shift;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

class ShiftCoverage
{
    public const DAY_MINUTES = 1440;

    public static function toMinutes(string $time): int
    {
        [$hour, $minute] = array_map('intval', explode(':', substr($time, 0, 5)));

        return $hour * 60 + $minute;
    }

    /**
     * Porções de um plantão que cobrem a data informada (Y-m-d).
     *
     * @return list<array{int, int}>
     */
    public static function intervalsOnDate(Shift $shift, string $date): array
    {
        return $shift->period()->intervalsOnDate($date);
    }

    /**
     * @param  Collection<int, Shift>  $shifts
     */
    public static function coveredMinutes(Collection $shifts, string $date): int
    {
        $intervals = [];

        foreach ($shifts as $shift) {
            if ($shift->status !== ShiftStatus::Scheduled || $shift->user_id === null) {
                continue;
            }

            foreach (self::intervalsOnDate($shift, $date) as [$start, $end]) {
                $intervals[] = [$start, $end];
            }
        }

        if ($intervals === []) {
            return 0;
        }

        sort($intervals);

        [$currentStart, $currentEnd] = $intervals[0];
        $total = 0;

        foreach (array_slice($intervals, 1) as [$start, $end]) {
            if ($start > $currentEnd) {
                $total += $currentEnd - $currentStart;
                [$currentStart, $currentEnd] = [$start, $end];
            } else {
                $currentEnd = max($currentEnd, $end);
            }
        }

        return $total + $currentEnd - $currentStart;
    }

    /**
     * @param  Collection<int, Shift>  $shifts
     */
    public static function isDayFullyCovered(Collection $shifts, string $date): bool
    {
        return self::coveredMinutes($shifts, $date) >= self::DAY_MINUTES;
    }

    /**
     * Encontra um plantão do profissional que emenda (sobrepõe ou encosta)
     * no plantão candidato — a "dobra". Retorna null quando não há dobra.
     */
    public static function findDoubleShift(User $substitute, Shift $candidate): ?Shift
    {
        $date = $candidate->date->format('Y-m-d');
        $previous = CarbonImmutable::parse($date)->subDay()->format('Y-m-d');
        $next = CarbonImmutable::parse($date)->addDay()->format('Y-m-d');

        $existing = Shift::query()
            ->where('user_id', $substitute->getKey())
            ->where('status', ShiftStatus::Scheduled)
            ->whereBetween('date', [$previous, $next.' 23:59:59'])
            ->where('id', '!=', $candidate->getKey())
            ->orderBy('date')
            ->orderBy('start_time')
            ->get();

        return self::findDoubleShiftIn($existing, (int) $substitute->getKey(), $candidate);
    }

    /**
     * @param  Collection<int, Shift>  $shifts
     */
    public static function findDoubleShiftIn(Collection $shifts, int $substituteId, Shift $candidate): ?Shift
    {
        $candidatePeriod = $candidate->period();

        foreach ($shifts as $shift) {
            if ($shift->user_id !== $substituteId
                || $shift->status !== ShiftStatus::Scheduled
                || $shift->getKey() === $candidate->getKey()) {
                continue;
            }

            if ($candidatePeriod->overlaps($shift->period(), includeAdjacent: true)) {
                return $shift;
            }
        }

        return null;
    }

    public static function doubleShiftLabel(Shift $shift): string
    {
        return $shift->period()->datedLabel();
    }
}
