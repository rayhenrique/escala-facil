<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\IssueReport;
use App\Models\User;

class IssueReportPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->role === UserRole::Manager;
    }

    public function view(User $user, IssueReport $report): bool
    {
        return $user->role === UserRole::Manager;
    }

    public function create(User $user): bool
    {
        return in_array($user->role, [UserRole::Manager, UserRole::Professional], true);
    }
}
