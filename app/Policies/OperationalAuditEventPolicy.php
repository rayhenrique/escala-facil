<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\OperationalAuditEvent;
use App\Models\User;

class OperationalAuditEventPolicy
{
    public function viewAny(User $user): bool
    {
        return User::query()->whereKey($user->id)->where('role', UserRole::Manager)->exists();
    }

    public function view(User $user, OperationalAuditEvent $event): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, OperationalAuditEvent $event): bool
    {
        return false;
    }

    public function delete(User $user, OperationalAuditEvent $event): bool
    {
        return false;
    }
}
