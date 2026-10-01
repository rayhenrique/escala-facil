<?php

namespace App\Enums;

enum ShiftSwapStatus: string
{
    case PendingProfessional = 'pending_professional';
    case PendingManager = 'pending_manager';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Reverted = 'reverted';

    public function label(): string
    {
        return match ($this) {
            self::PendingProfessional => 'Aguardando profissional',
            self::PendingManager => 'Aguardando gerência',
            self::Approved => 'Aprovada',
            self::Rejected => 'Rejeitada',
            self::Reverted => 'Desfeita',
        };
    }
}
