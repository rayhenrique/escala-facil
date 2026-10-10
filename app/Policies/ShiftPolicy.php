<?php

namespace App\Policies;

use App\Enums\ShiftStatus;
use App\Enums\UserRole;
use App\Models\Shift;
use App\Models\User;

class ShiftPolicy
{
    public function viewCalendar(User $user): bool
    {
        return in_array($user->role, [UserRole::Manager, UserRole::Professional], true);
    }

    public function viewAny(User $user): bool
    {
        return $user->role === UserRole::Manager;
    }

    public function view(User $user, Shift $shift): bool
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

    public function reallocate(User $user, Shift $shift): bool
    {
        return $this->update($user, $shift)
            && $shift->status === ShiftStatus::Uncovered
            && $shift->period()->end->isAfter(now());
    }

    public function delete(User $user, Shift $shift): bool
    {
        return $user->role === UserRole::Manager;
    }
}
