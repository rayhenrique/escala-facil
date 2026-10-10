<?php

namespace App\Support;

use Carbon\CarbonImmutable;

/** Intervalo em minutos, preservando a interpretação histórica dos horários. */
final readonly class ShiftPeriod
{
    public CarbonImmutable $start;

    public CarbonImmutable $end;

    public int $durationMinutes;

    public function __construct(string $date, string $startTime, string $endTime, ?string $endDate = null)
    {
        $startTime = substr($startTime, 0, 5);
        $endTime = substr($endTime, 0, 5);
        $this->start = CarbonImmutable::parse($date.' '.$startTime, config('app.timezone'));
        $end = CarbonImmutable::parse(($endDate ?? $date).' '.$endTime, config('app.timezone'));
        $this->end = $endDate === null && $end <= $this->start ? $end->addDay() : $end;
        $minutes = ShiftCoverage::toMinutes($endTime) - ShiftCoverage::toMinutes($startTime);
        $days = (int) $this->start->startOfDay()->diffInDays($this->end->startOfDay());
        $this->durationMinutes = $minutes + $days * ShiftCoverage::DAY_MINUTES;
    }

    public function crossesMidnight(): bool
    {
        return $this->end->toDateString() !== $this->start->toDateString();
    }

    public function durationLabel(): string
    {
        $hours = intdiv($this->durationMinutes, 60);
        $minutes = $this->durationMinutes % 60;

        return $hours === 0 ? $minutes.' min' : $hours.'h'.($minutes ? ' '.$minutes.' min' : '');
    }

    public function endLabel(): string
    {
        return $this->end->format('H:i').($this->crossesMidnight()
            ? ' (dia seguinte, '.$this->end->format('d/m/Y').')' : '');
    }

    public function timeLabel(): string
    {
        return $this->start->format('H:i').'–'.$this->endLabel().' · '.$this->durationLabel();
    }

    public function datedLabel(): string
    {
        return $this->start->format('d/m/Y').', das '.$this->start->format('H:i').' às '.$this->endLabel().' · '.$this->durationLabel();
    }

    /** Sobreposição usa [início, fim); turnos consecutivos só contam como dobra. */
    public function overlaps(self $other, bool $includeAdjacent = false): bool
    {
        return $includeAdjacent
            ? $this->start <= $other->end && $other->start <= $this->end
            : $this->start < $other->end && $other->start < $this->end;
    }

    /** @return list<array{int, int}> */
    public function intervalsOnDate(string $date): array
    {
        if ($date === $this->start->toDateString()) {
            $start = ShiftCoverage::toMinutes($this->start->format('H:i'));
            $end = $this->crossesMidnight() ? ShiftCoverage::DAY_MINUTES : ShiftCoverage::toMinutes($this->end->format('H:i'));

            return [[$start, $end]];
        }

        $endMinutes = ShiftCoverage::toMinutes($this->end->format('H:i'));

        return $date === $this->end->toDateString() && $endMinutes > 0 ? [[0, $endMinutes]] : [];
    }
}
