<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Sector;
use App\Models\User;

class SectorPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->role === UserRole::Manager;
    }

    public function create(User $user): bool
    {
        return $user->role === UserRole::Manager;
    }

    public function update(User $user, Sector $sector): bool
    {
        return $user->role === UserRole::Manager;
    }

    public function delete(User $user, Sector $sector): bool
    {
        return $user->role === UserRole::Manager;
    }
}
