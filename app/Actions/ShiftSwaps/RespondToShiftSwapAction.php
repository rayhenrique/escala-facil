<?php

namespace App\Actions\ShiftSwaps;

use App\Enums\ShiftSwapStatus;
use App\Http\Requests\DeclineShiftSwapRequest;
use App\Models\Shift;
use App\Models\ShiftSwap;
use App\Models\User;
use App\Services\ShiftSwapNotificationService;
use App\Support\OperationalAuditContext;
use App\Support\ShiftSwapResponse;
use Illuminate\Database\DetectsConcurrencyErrors;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class RespondToShiftSwapAction
{
    use DetectsConcurrencyErrors;

    public function execute(User $actor, int $id, string $decision, ?string $reason = null, ?string $expected = null): ShiftSwap
    {
        abort_unless(in_array($decision, ['accept', 'decline'], true), 422);
        $reference = ShiftSwap::query()->findOrFail($id);
        Gate::forUser($actor)->authorize('viewResponse', $reference);
        if ($decision === 'decline') {
            (new DeclineShiftSwapRequest)->validatePayload(['declineReason' => $reason], $actor);
        }
        try {
            return app(OperationalAuditContext::class)->transaction($actor, function () use ($actor, $id, $decision, $reason, $expected, $reference): ShiftSwap {
                $users = User::query()->whereKey([$actor->id, $reference->requester_id, $reference->substitute_id])->orderBy('id')->lockForUpdate()->get()->keyBy('id');
                $shifts = Shift::query()->whereKey(array_filter([$reference->shift_id, $reference->substitute_shift_id]))->orderBy('id')->lockForUpdate()->get()->keyBy('id');
                $swap = ShiftSwap::query()->lockForUpdate()->findOrFail($id);
                $actor = $users->get($actor->id);
                abort_unless($actor, 403);
                Gate::forUser($actor)->authorize('viewResponse', $swap);
                if ($reference->only(['shift_id', 'substitute_shift_id', 'requester_id', 'substitute_id']) !== $swap->only(['shift_id', 'substitute_shift_id', 'requester_id', 'substitute_id'])) {
                    throw ValidationException::withMessages(['response' => 'O pedido foi modificado. Atualize os detalhes antes de responder.']);
                }
                $swap->setRelation('shift', $shifts->get($swap->shift_id));
                $swap->setRelation('substituteShift', $shifts->get($swap->substitute_shift_id));
                $swap->setRelation('requester', $users->get($swap->requester_id));
                $swap->setRelation('substitute', $users->get($swap->substitute_id));
                $review = app(ShiftSwapResponse::class)->inspect($swap, $actor);
                if ($swap->status !== ShiftSwapStatus::PendingProfessional) {
                    throw ValidationException::withMessages(['response' => 'Este pedido já foi respondido ('.$swap->status->label().'). Atualize os detalhes para conferir a situação atual.']);
                }
                if ($expected !== null && ! hash_equals($expected, $review['fingerprint'])) {
                    throw ValidationException::withMessages(['response' => 'A revisão está desatualizada. '.implode(' ', $review['blocks']).' Atualize os detalhes para conferir os horários, cotas e alertas antes de responder.']);
                }
                Gate::forUser($actor)->authorize($decision, $swap);
                $swap->update(['status' => $decision === 'accept' ? ShiftSwapStatus::PendingManager : ShiftSwapStatus::Rejected,
                    'professional_response' => $decision === 'accept' ? 'accepted' : 'declined', 'professional_responded_at' => now(),
                    ...($decision === 'decline' ? ['rejection_reason' => trim((string) $reason) ?: null] : []),
                ]);
                $swap = $swap->refresh();
                $notifications = app(ShiftSwapNotificationService::class);
                if ($decision === 'accept') {
                    $notifications->accepted($swap);
                } else {
                    $notifications->declined($swap);
                }

                return $swap;
            }, attempts: 3);
        } catch (QueryException $exception) {
            if (! $this->causedByConcurrencyError($exception)) {
                throw $exception;
            }
            throw ValidationException::withMessages(['response' => 'Outra operação está respondendo ou alterando este pedido. Atualize os detalhes e confira a situação antes de tentar novamente.']);
        }
    }
}
