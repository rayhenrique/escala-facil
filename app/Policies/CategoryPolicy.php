<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Category;
use App\Models\User;

class CategoryPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->role === UserRole::Manager;
    }

    public function create(User $user): bool
    {
        return $user->role === UserRole::Manager;
    }

    public function update(User $user, Category $category): bool
    {
        return $user->role === UserRole::Manager;
    }

    public function delete(User $user, Category $category): bool
    {
        return $user->role === UserRole::Manager;
    }
}
