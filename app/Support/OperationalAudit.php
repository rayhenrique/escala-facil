<?php

namespace App\Support;

use App\Enums\ShiftSwapStatus;
use App\Models\AccountInvitation;
use App\Models\Category;
use App\Models\OperationalAuditEvent;
use App\Models\Sector;
use App\Models\Shift;
use App\Models\ShiftSwap;
use App\Models\User;
use BackedEnum;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;

class OperationalAudit
{
    private const FIELDS = [
        Shift::class => ['date', 'start_time', 'end_time', 'end_date', 'sector_id', 'category_id', 'user_id', 'status'],
        ShiftSwap::class => ['shift_id', 'substitute_shift_id', 'requester_id', 'substitute_id', 'status', 'manager_id'],
        User::class => ['name', 'role', 'sector_id', 'category_id', 'requires_activation'],
        AccountInvitation::class => ['user_id', 'invited_by', 'generation', 'expires_at', 'last_sent_at', 'accepted_at', 'revoked_at'],
        Sector::class => ['name'], Category::class => ['name'],
    ];

    public function snapshot(Model $model): array
    {
        $result = [];
        foreach (self::FIELDS[$model::class] as $field) {
            $value = $model->getAttribute($field);
            $result[$field] = match (true) {
                $value instanceof BackedEnum => $value->value,
                $value instanceof DateTimeInterface => $value->format($model instanceof AccountInvitation ? 'c' : 'Y-m-d'),
                in_array($field, ['start_time', 'end_time'], true) => substr((string) $value, 0, 5),
                default => $value,
            };
        }

        return $result;
    }

    public function record(Model $model, string $action, array $before, array $after, bool $emailChanged = false): void
    {
        $changes = [];
        foreach (array_unique([...array_keys($before), ...array_keys($after)]) as $field) {
            if (($before[$field] ?? null) !== ($after[$field] ?? null)) {
                $changes[$field] = ['before' => $before[$field] ?? null, 'after' => $after[$field] ?? null];
            }
        }
        // An email change matters, but its content is not needed in the audit copy.
        if ($model instanceof User && $action === 'updated' && $emailChanged) {
            $changes['email_changed'] = ['before' => false, 'after' => true];
        }
        if ($changes === []) {
            return;
        }
        if ($model instanceof Shift && $action === 'updated' && isset($changes['user_id']) && ($after['user_id'] ?? null) !== null) {
            $action = 'reallocated';
        }
        if ($model instanceof ShiftSwap && $action === 'updated' && isset($changes['status'])) {
            $action = match ($after['status']) {
                ShiftSwapStatus::Approved->value => 'approved', ShiftSwapStatus::Rejected->value => 'rejected',
                ShiftSwapStatus::Reverted->value => 'reverted', ShiftSwapStatus::PendingManager->value => 'accepted',
                default => $action,
            };
            if ($action === 'approved') {
                $changes['approval_state'] = ['before' => null, 'after' => app(ShiftSwapReversion::class)->approvalState($model)];
            }
        }
        if ($model instanceof AccountInvitation && $action === 'updated') {
            $action = match (true) {
                isset($changes['accepted_at']) => 'activated', isset($changes['revoked_at']) && $after['revoked_at'] !== null => 'revoked',
                isset($changes['generation']) => 'resent', default => $action,
            };
        }
        $entity = match ($model::class) {
            Shift::class => 'shift', ShiftSwap::class => 'swap', User::class => 'user',
            Sector::class => 'sector', Category::class => 'category',
            AccountInvitation::class => 'invitation',
        };
        $context = app(OperationalAuditContext::class);
        OperationalAuditEvent::on($model->getConnectionName())->create([
            'author_id' => $context->authorId(), 'entity_type' => $entity, 'entity_id' => $model->getKey(),
            'action' => $action, 'occurred_at' => now(), 'operation_id' => $context->operationId(), 'changes' => $changes,
        ]);
    }
}
