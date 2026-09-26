<?php

namespace App\Policies;

use App\Enums\ShiftStatus;
use App\Enums\UserRole;
use App\Models\Absence;
use App\Models\Shift;
use App\Models\User;

class AbsencePolicy
{
    public function view(User $user, Absence $absence): bool
    {
        return $user->role === UserRole::Manager || $absence->user_id === $user->id;
    }

    public function create(User $user, Shift $shift): bool
    {
        return $user->role === UserRole::Professional
            && $shift->user_id === $user->id
            && $shift->status === ShiftStatus::Scheduled
            && ! $shift->date->isBefore(today())
            && ! $shift->absences()->exists();
    }
}
