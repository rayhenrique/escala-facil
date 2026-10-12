<?php

namespace App\Policies;

use App\Enums\ShiftSwapStatus;
use App\Enums\UserRole;
use App\Models\ShiftSwap;
use App\Models\User;
use App\Support\ShiftSwapApproval;
use App\Support\ShiftSwapResponse;
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

    public function viewResponse(User $user, ShiftSwap $shiftSwap): bool
    {
        return $user->role === UserRole::Professional && $shiftSwap->substitute_id === $user->id;
    }

    public function decline(User $user, ShiftSwap $shiftSwap): bool
    {
        return $this->viewResponse($user, $shiftSwap) && $shiftSwap->status === ShiftSwapStatus::PendingProfessional;
    }

    public function accept(User $user, ShiftSwap $shiftSwap): Response
    {
        if (! $this->viewResponse($user, $shiftSwap)) {
            return Response::deny('Somente o profissional destinatário pode aceitar este pedido.');
        }
        $blocks = app(ShiftSwapResponse::class)->inspect($shiftSwap, $user)['blocks'];

        return $blocks === [] ? Response::allow() : Response::deny(implode(' ', $blocks));
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
