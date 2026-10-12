<?php

namespace App\Enums;

enum ShiftSwapEvent: string
{
    case Requested = 'requested';
    case Accepted = 'accepted';
    case Declined = 'declined';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Reverted = 'reverted';

    public function label(): string
    {
        return match ($this) {
            self::Requested => 'Solicitação enviada',
            self::Accepted => 'Solicitação aceita',
            self::Declined => 'Solicitação recusada pelo profissional',
            self::Approved => 'Troca aprovada',
            self::Rejected => 'Troca rejeitada',
            self::Reverted => 'Troca desfeita',
        };
    }
}
