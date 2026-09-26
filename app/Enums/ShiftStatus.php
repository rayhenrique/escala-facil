<?php

namespace App\Enums;

enum ShiftStatus: string
{
    case Scheduled = 'scheduled';
    case AbsenceReported = 'absence_reported';
    case Uncovered = 'uncovered';

    public function label(): string
    {
        return match ($this) {
            self::Scheduled => 'Agendado',
            self::AbsenceReported => 'Ausência informada',
            self::Uncovered => 'Descoberto',
        };
    }
}
