<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\User;

class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->role === UserRole::Manager;
    }

    public function create(User $user): bool
    {
        return $user->role === UserRole::Manager;
    }

    public function update(User $user, User $model): bool
    {
        return $user->role === UserRole::Manager;
    }

    public function delete(User $user, User $model): bool
    {
        return $user->role === UserRole::Manager;
    }
}
