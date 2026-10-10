<?php

namespace App\Actions\ShiftSwaps;

use App\Enums\ShiftStatus;
use App\Enums\ShiftSwapStatus;
use App\Http\Requests\RejectShiftSwapRequest;
use App\Models\Shift;
use App\Models\ShiftSwap;
use App\Models\User;
use App\Services\ShiftSwapNotificationService;
use App\Support\ShiftSwapAnalysis;
use Illuminate\Database\DetectsConcurrencyErrors;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class DecideShiftSwapAction
{
    use DetectsConcurrencyErrors;

    public function __construct(private readonly ShiftSwapNotificationService $notifications) {}

    public function approve(User $manager, int $shiftSwapId, ?string $expectedSnapshot = null): ShiftSwap
    {
        return $this->decide($manager, $shiftSwapId, 'approve', null, $expectedSnapshot);
    }

    public function reject(User $manager, int $shiftSwapId, ?string $reason = null, ?string $expectedSnapshot = null): ShiftSwap
    {
        (new RejectShiftSwapRequest)->validatePayload(['rejectionReason' => $reason]);

        return $this->decide($manager, $shiftSwapId, 'reject', $reason, $expectedSnapshot);
    }

    private function decide(User $manager, int $id, string $decision, ?string $reason, ?string $expected): ShiftSwap
    {
        $reference = ShiftSwap::query()->findOrFail($id);

        try {
            return DB::transaction(function () use ($manager, $id, $decision, $reason, $expected, $reference): ShiftSwap {
                // Stable ordering serializes shared participants (including their monthly quota),
                // then both assignments. Retry deadlocks involving other operational actions.
                $users = User::query()->whereKey([$manager->id, $reference->requester_id, $reference->substitute_id])
                    ->orderBy('id')->lockForUpdate()->get()->keyBy('id');
                $shifts = Shift::query()->whereKey(array_filter([$reference->shift_id, $reference->substitute_shift_id]))
                    ->orderBy('id')->lockForUpdate()->get()->keyBy('id');
                $swap = ShiftSwap::query()->lockForUpdate()->findOrFail($id);
                if ($reference->only(['shift_id', 'substitute_shift_id', 'requester_id', 'substitute_id']) !== $swap->only(['shift_id', 'substitute_shift_id', 'requester_id', 'substitute_id'])) {
                    throw ValidationException::withMessages(['decision' => 'O pedido mudou. Feche e abra a análise novamente.']);
                }
                $manager = $users->get($manager->id);
                abort_unless($manager, 403);
                Gate::forUser($manager)->authorize('viewAny', ShiftSwap::class);
                $swap->setRelation('shift', $shifts->get($swap->shift_id));
                $swap->setRelation('substituteShift', $shifts->get($swap->substitute_shift_id));
                $swap->setRelation('requester', $users->get($swap->requester_id));
                $swap->setRelation('substitute', $users->get($swap->substitute_id));
                if ($expected !== null) {
                    $current = app(ShiftSwapAnalysis::class)->make($swap, $manager);
                    if (! hash_equals($expected, $current['fingerprint'])) {
                        throw ValidationException::withMessages(['decision' => 'O pedido, os plantões ou os alertas mudaram. Feche e abra a análise novamente antes de decidir.']);
                    }
                }
                Gate::forUser($manager)->authorize($decision, $swap);
                $swap->update([
                    'status' => $decision === 'approve' ? ShiftSwapStatus::Approved : ShiftSwapStatus::Rejected,
                    'manager_id' => $manager->id,
                    'rejection_reason' => $reason,
                ]);
                if ($decision === 'approve') {
                    $swap->shift->update(['user_id' => $swap->substitute_id, 'status' => ShiftStatus::Scheduled]);
                    $swap->substituteShift?->update(['user_id' => $swap->requester_id, 'status' => ShiftStatus::Scheduled]);
                }
                $swap = $swap->refresh();
                if ($decision === 'approve') {
                    $this->notifications->approved($swap);
                } else {
                    $this->notifications->rejected($swap);
                }

                return $swap;
            }, attempts: 3);
        } catch (QueryException $exception) {
            if (! $this->causedByConcurrencyError($exception)) {
                throw $exception;
            }
            throw ValidationException::withMessages(['decision' => 'Outra operação está alterando estes registros. Feche e abra a análise novamente para conferir o estado do pedido.']);
        }
    }
}
