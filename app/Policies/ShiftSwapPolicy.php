<?php

namespace App\Policies;

use App\Enums\ShiftStatus;
use App\Enums\ShiftSwapStatus;
use App\Enums\UserRole;
use App\Models\ShiftSwap;
use App\Models\User;

class ShiftSwapPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, ShiftSwap $shiftSwap): bool
    {
        return $user->role === UserRole::Manager
            || $shiftSwap->requester_id === $user->id
            || $shiftSwap->substitute_id === $user->id;
    }

    public function create(User $user): bool
    {
        return $user->role === UserRole::Professional
            && $user->category_id !== null
            && $user->sector_id !== null
            && ! $user->hasReachedSwapLimit(now()->month, now()->year);
    }

    public function accept(User $user, ShiftSwap $shiftSwap): bool
    {
        if ($user->role !== UserRole::Professional
            || $shiftSwap->substitute_id !== $user->id
            || $shiftSwap->status !== ShiftSwapStatus::PendingProfessional) {
            return false;
        }

        $shiftSwap->loadMissing(['requester', 'shift']);

        if (! $shiftSwap->requester || ! $shiftSwap->shift) {
            return false;
        }

        $matchesAssignment = $user->category_id === $shiftSwap->requester->category_id
            && $user->sector_id === $shiftSwap->requester->sector_id
            && $user->category_id === $shiftSwap->shift->category_id
            && $user->sector_id === $shiftSwap->shift->sector_id;

        $referenceDate = $shiftSwap->created_at ?? now();

        return $matchesAssignment
            && $shiftSwap->shift->user_id === $shiftSwap->requester_id
            && $shiftSwap->shift->status === ShiftStatus::Scheduled
            && ! $user->hasReachedSwapLimit($referenceDate->month, $referenceDate->year);
    }

    public function approve(User $user, ShiftSwap $shiftSwap): bool
    {
        if ($user->role !== UserRole::Manager
            || $shiftSwap->status !== ShiftSwapStatus::PendingManager) {
            return false;
        }

        $shiftSwap->loadMissing(['requester', 'substitute', 'shift']);
        $referenceDate = $shiftSwap->created_at ?? now();

        return $shiftSwap->requester !== null
            && $shiftSwap->substitute !== null
            && $shiftSwap->shift !== null
            && $shiftSwap->shift->user_id === $shiftSwap->requester_id
            && $shiftSwap->shift->status === ShiftStatus::Scheduled
            && ! $shiftSwap->requester->hasReachedSwapLimit($referenceDate->month, $referenceDate->year)
            && ! $shiftSwap->substitute->hasReachedSwapLimit($referenceDate->month, $referenceDate->year);
    }

    public function reject(User $user, ShiftSwap $shiftSwap): bool
    {
        return $user->role === UserRole::Manager
            && $shiftSwap->status === ShiftSwapStatus::PendingManager;
    }
}
