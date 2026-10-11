<?php

namespace App\Policies;

use App\Enums\ShiftStatus;
use App\Enums\ShiftSwapStatus;
use App\Enums\UserRole;
use App\Models\ShiftSwap;
use App\Models\User;
use App\Support\ShiftSwapApproval;
use App\Support\ShiftSwapReversion;
use Illuminate\Auth\Access\Response;

class ShiftSwapPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->role === UserRole::Manager;
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

    public function approve(User $user, ShiftSwap $shiftSwap): Response
    {
        if ($user->role !== UserRole::Manager) {
            return Response::deny('Somente gerentes podem aprovar pedidos de troca.');
        }
        $blockers = app(ShiftSwapApproval::class)->inspect($shiftSwap)['blockers'];

        return $blockers === [] ? Response::allow() : Response::deny(implode(' ', array_column($blockers, 'message')));
    }

    public function reject(User $user, ShiftSwap $shiftSwap): Response
    {
        if ($user->role !== UserRole::Manager) {
            return Response::deny('Somente gerentes podem rejeitar pedidos de troca.');
        }
        $reason = ShiftSwapApproval::statusReason($shiftSwap);

        return $reason === null ? Response::allow() : Response::deny($reason);
    }

    public function reviewReversion(User $user, ShiftSwap $shiftSwap): Response
    {
        if ($user->role !== UserRole::Manager) {
            return Response::deny('Somente gerentes podem revisar o desfazimento de trocas.');
        }

        return $shiftSwap->status === ShiftSwapStatus::Approved
            ? Response::allow() : Response::deny('Somente trocas aprovadas podem ser revisadas para desfazimento.');
    }

    public function revert(User $user, ShiftSwap $shiftSwap): Response
    {
        $permission = $this->reviewReversion($user, $shiftSwap);
        if ($permission->denied()) {
            return $permission;
        }
        $review = app(ShiftSwapReversion::class)->inspect($shiftSwap);

        return $review['can_revert'] ? Response::allow() : Response::deny(implode(' ', $review['blocks']));
    }
}
