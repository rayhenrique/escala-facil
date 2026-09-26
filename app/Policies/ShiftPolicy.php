<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Shift;
use App\Models\User;

class ShiftPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->role === UserRole::Manager;
    }

    public function create(User $user): bool
    {
        return $user->role === UserRole::Manager;
    }

    public function update(User $user, Shift $shift): bool
    {
        return $user->role === UserRole::Manager;
    }

    public function delete(User $user, Shift $shift): bool
    {
        return $user->role === UserRole::Manager;
    }
}
