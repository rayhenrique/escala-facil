<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\AccountInvitation;
use App\Models\User;

class AccountInvitationPolicy
{
    public function create(User $manager): bool
    {
        return app(UserPolicy::class)->create($manager) && User::whereKey($manager->id)->where('role', UserRole::Manager)->exists();
    }

    public function update(User $manager, AccountInvitation $invitation): bool
    {
        return $this->create($manager) && $invitation->accepted_at === null && $invitation->user?->requires_activation
            && $invitation->user->role === UserRole::Professional;
    }
}
