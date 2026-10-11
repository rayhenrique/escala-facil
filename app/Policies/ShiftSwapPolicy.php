<?php

namespace App\Policies;

use App\Enums\ShiftStatus;
use App\Enums\ShiftSwapStatus;
use App\Enums\UserRole;
use App\Models\ShiftSwap;
use App\Models\User;
use App\Support\ShiftSwapApproval;
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

    public function revert(User $user, ShiftSwap $shiftSwap): Response
    {
        if ($user->role !== UserRole::Manager) {
            return Response::deny('Somente gerentes podem desfazer trocas.');
        }
        if ($shiftSwap->status !== ShiftSwapStatus::Approved) {
            return Response::deny('Somente trocas aprovadas podem ser desfeitas.');
        }

        $shiftSwap->loadMissing('shift');

        $shift = $shiftSwap->shift;

        if (! $shift) {
            return Response::deny('O plantão deste pedido não está disponível.');
        }
        if ($shift->user_id !== $shiftSwap->substitute_id) {
            return Response::deny('A atribuição do plantão foi alterada; o substituto deste pedido não é o profissional atual.');
        }
        if ($shift->status !== ShiftStatus::Scheduled) {
            return Response::deny('O plantão não está agendado. Confira sua situação em Escalas.');
        }
        if ($shift->date->isBefore(today())) {
            return Response::deny('A data do plantão já passou. A regra atual não permite desfazer esta troca.');
        }
        // A listagem carrega os mesmos EXISTS em lote; a ação sob lock consulta
        // os dados atuais, sem esses atributos, preservando a revalidação.
        $attributes = $shift->getAttributes();
        $hasPendingSwap = array_key_exists('pending_swaps_exists', $attributes)
            ? (bool) $attributes['pending_swaps_exists']
            : $shift->swaps()->whereIn('status', [ShiftSwapStatus::PendingProfessional, ShiftSwapStatus::PendingManager])->exists();
        if ($hasPendingSwap) {
            return Response::deny('Existe outro pedido pendente para este plantão. Resolva o pedido antes de desfazer a troca.');
        }
        $hasAbsence = array_key_exists('absences_exists', $attributes)
            ? (bool) $attributes['absences_exists'] : $shift->absences()->exists();
        if ($hasAbsence) {
            return Response::deny('Existe um registro de ausência neste plantão. Confira a ausência antes de desfazer a troca.');
        }

        return Response::allow();
    }
}
