<?php

namespace App\Enums;

enum ShiftSwapEvent: string
{
    case Requested = 'requested';
    case Accepted = 'accepted';
    case Approved = 'approved';
    case Rejected = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::Requested => 'Solicitação enviada',
            self::Accepted => 'Solicitação aceita',
            self::Approved => 'Troca aprovada',
            self::Rejected => 'Troca rejeitada',
        };
    }
}
