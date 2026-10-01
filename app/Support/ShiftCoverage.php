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
        $shiftDate = $shift->date->format('Y-m-d');
        $start = self::toMinutes((string) $shift->start_time);
        $end = self::toMinutes((string) $shift->end_time);

        if ($shiftDate === $date) {
            return $end > $start ? [[$start, $end]] : [[$start, self::DAY_MINUTES]];
        }

        $previous = CarbonImmutable::parse($date)->subDay()->format('Y-m-d');

        if ($shiftDate === $previous && $end <= $start && $end > 0) {
            return [[0, $end]];
        }

        return [];
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

        $candidateIntervals = self::intervalsOnDate($candidate, $date);

        $existing = Shift::query()
            ->where('user_id', $substitute->getKey())
            ->where('status', ShiftStatus::Scheduled)
            ->whereIn('date', [$date, $previous])
            ->where('id', '!=', $candidate->getKey())
            ->orderBy('date')
            ->orderBy('start_time')
            ->get();

        foreach ($existing as $shift) {
            foreach (self::intervalsOnDate($shift, $date) as [$start, $end]) {
                foreach ($candidateIntervals as [$candidateStart, $candidateEnd]) {
                    if ($start <= $candidateEnd && $candidateStart <= $end) {
                        return $shift;
                    }
                }
            }
        }

        return null;
    }

    public static function doubleShiftLabel(Shift $shift): string
    {
        return sprintf(
            '%s, das %s às %s',
            $shift->date->format('d/m/Y'),
            substr((string) $shift->start_time, 0, 5),
            substr((string) $shift->end_time, 0, 5),
        );
    }
}
