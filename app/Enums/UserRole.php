<?php

namespace App\Enums;

enum UserRole: string
{
    case Manager = 'manager';
    case Professional = 'professional';

    public function label(): string
    {
        return match ($this) {
            self::Manager => 'Gerente',
            self::Professional => 'Profissional',
        };
    }
}
