<?php

namespace App\Enums;

enum AbsenceStatus: string
{
    case Pending = 'pending';
    case Acknowledged = 'acknowledged';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Aguardando ciência',
            self::Acknowledged => 'Ciente',
        };
    }
}
