<?php

namespace App\Support;

use App\Enums\ShiftStatus;
use App\Enums\ShiftSwapStatus;
use App\Enums\UserRole;
use Carbon\CarbonImmutable;

class OperationalAuditPresentation
{
    public static function field(string $field): string
    {
        return [
            'date' => 'Data de início', 'end_date' => 'Data de término', 'start_time' => 'Início', 'end_time' => 'Término',
            'sector_id' => 'Setor (ID)', 'category_id' => 'Categoria (ID)', 'user_id' => 'Profissional (ID)', 'status' => 'Situação',
            'name' => 'Nome', 'role' => 'Perfil', 'email_changed' => 'Endereço de acesso alterado (conteúdo não registrado)',
            'shift_id' => 'Plantão (ID)', 'substitute_shift_id' => 'Contrapartida (ID)', 'requester_id' => 'Solicitante (ID)',
            'substitute_id' => 'Substituto (ID)', 'manager_id' => 'Gerente (ID)',
        ][$field] ?? $field;
    }

    public static function value(string $field, mixed $value): string
    {
        if ($value === null) {
            return 'Sem valor';
        }
        if (is_bool($value)) {
            return $value ? 'Sim' : 'Não';
        }
        if ($field === 'status') {
            return ShiftStatus::tryFrom($value)?->label() ?? ShiftSwapStatus::tryFrom($value)?->label() ?? $value;
        }
        if ($field === 'role') {
            return UserRole::tryFrom($value)?->label() ?? $value;
        }
        if (in_array($field, ['date', 'end_date'], true)) {
            return CarbonImmutable::parse($value)->format('d/m/Y');
        }
        if (str_ends_with($field, '_id')) {
            return '#'.$value;
        }

        return (string) $value;
    }
}
