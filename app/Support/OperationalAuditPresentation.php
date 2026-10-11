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
            'approval_state' => 'Atribuições e horários da aprovação original',
            'requires_activation' => 'Ativação pendente', 'invited_by' => 'Gerente do convite (ID)', 'generation' => 'Emissão do convite',
            'expires_at' => 'Expiração', 'last_sent_at' => 'Envio solicitado', 'accepted_at' => 'Aceite', 'revoked_at' => 'Revogação',
        ][$field] ?? $field;
    }

    public static function value(string $field, mixed $value): string
    {
        if ($field === 'approval_state' && is_array($value)) {
            return 'Atribuições e horários originais preservados para revisão segura (plantões '.implode(', ', array_keys($value['before'] ?? [])).').';
        }
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
        if (in_array($field, ['expires_at', 'last_sent_at', 'accepted_at', 'revoked_at'], true)) {
            return CarbonImmutable::parse($value)->setTimezone(config('app.timezone'))->format('d/m/Y H:i:s');
        }
        if (str_ends_with($field, '_id') || $field === 'invited_by') {
            return '#'.$value;
        }

        return (string) $value;
    }
}
