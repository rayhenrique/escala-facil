<?php

namespace App\Actions\ShiftSwaps;

use App\Enums\ShiftStatus;
use App\Enums\ShiftSwapStatus;
use App\Http\Requests\RevertShiftSwapRequest;
use App\Models\Shift;
use App\Models\ShiftSwap;
use App\Models\ShiftSwapReversal;
use App\Models\User;
use App\Services\ShiftSwapNotificationService;
use App\Support\OperationalAudit;
use App\Support\OperationalAuditContext;
use App\Support\ShiftSwapReversion;
use Illuminate\Database\DetectsConcurrencyErrors;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class RevertShiftSwapAction
{
    use DetectsConcurrencyErrors;

    public function __construct(private readonly ShiftSwapNotificationService $notifications) {}

    public function execute(User $manager, int $shiftSwapId, ?string $reason = null, ?string $expectedSnapshot = null): ShiftSwap
    {
        Gate::forUser($manager)->authorize('viewAny', ShiftSwap::class);
        $reason = trim($reason ?? '');
        (new RevertShiftSwapRequest)->validatePayload(['reversionReason' => $reason, 'reversionSnapshot' => $expectedSnapshot]);
        $reference = ShiftSwap::query()->findOrFail($shiftSwapId);
        $reference->loadMissing(['shift', 'substituteShift']);
        $periods = collect([$reference->shift, $reference->substituteShift])->filter()->map(fn ($shift) => $shift->period());
        if ($periods->isEmpty()) {
            throw ValidationException::withMessages(['reversion' => 'Os plantões originais não estão disponíveis. Consulte a análise administrativa.']);
        }
        $start = $periods->min(fn ($period) => $period->start)->subDay()->toDateString();
        $end = $periods->max(fn ($period) => $period->end)->addDay()->toDateString();
        try {
            return app(OperationalAuditContext::class)->transaction($manager, function () use ($manager, $reference, $reason, $expectedSnapshot, $start, $end): ShiftSwap {
                // Same order as approval/assignment: participants, assignments, then request.
                // Participant locks also serialize new conflicting assignments and requests.
                $users = User::query()->whereKey([$manager->id, $reference->requester_id, $reference->substitute_id])
                    ->orderBy('id')->lockForUpdate()->get()->keyBy('id');
                $ids = array_filter([$reference->shift_id, $reference->substitute_shift_id]);
                $shifts = Shift::query()->where(fn ($q) => $q->whereIn('id', $ids)
                    ->orWhere(fn ($q) => $q->whereIn('user_id', [$reference->requester_id, $reference->substitute_id])->where('status', ShiftStatus::Scheduled)
                        ->whereDate('date', '<=', $end)->where(fn ($q) => $q->whereDate('end_date', '>=', $start)
                        ->orWhere(fn ($q) => $q->whereNull('end_date')->whereDate('date', '>=', $start)))))
                    ->orderBy('id')->lockForUpdate()->get()->keyBy('id');
                $swap = ShiftSwap::query()->lockForUpdate()->findOrFail($reference->id);
                $manager = $users->get($manager->id);
                abort_unless($manager, 403);
                Gate::forUser($manager)->authorize('viewAny', ShiftSwap::class);
                if ($reference->only(ShiftSwapReversion::BINDINGS) !== $swap->only(ShiftSwapReversion::BINDINGS)) {
                    throw ValidationException::withMessages(['reversion' => 'Os participantes ou plantões vinculados mudaram. Feche e abra a revisão novamente.']);
                }
                $swap->setRelation('shift', $shifts->get($swap->shift_id));
                $swap->setRelation('substituteShift', $shifts->get($swap->substitute_shift_id));
                $swap->setRelation('requester', $users->get($swap->requester_id));
                $swap->setRelation('substitute', $users->get($swap->substitute_id));
                $review = app(ShiftSwapReversion::class)->inspect($swap, $shifts);
                if (! $review['can_revert'] || ! hash_equals($review['fingerprint'], $expectedSnapshot)) {
                    throw ValidationException::withMessages(['reversion' => 'Revisão desatualizada ou reversão indisponível. '.implode(' ', $review['blocks']).' Atualize a revisão antes de confirmar.']);
                }
                Gate::forUser($manager)->authorize('reviewReversion', $swap);
                $before = [];
                $after = [];
                foreach ($shifts->whereIn('id', $ids) as $shift) {
                    $before[$shift->id] = app(OperationalAudit::class)->snapshot($shift);
                    $shift->update(['user_id' => $review['approval_state']['before'][$shift->id]['user_id']]);
                    $after[$shift->id] = app(OperationalAudit::class)->snapshot($shift);
                }
                $swap->update(['status' => ShiftSwapStatus::Reverted, 'manager_id' => $manager->id]);
                ShiftSwapReversal::query()->create([
                    'shift_swap_id' => $swap->id, 'author_id' => $manager->id,
                    'approval_event_id' => $review['approval_event_id'], 'occurred_at' => now(),
                    'operation_id' => app(OperationalAuditContext::class)->operationId(),
                    'reason' => $reason, 'before' => $before, 'after' => $after,
                ]);
                $swap = $swap->refresh();
                // Database notifications become visible with the same commit; failure rolls everything back.
                $this->notifications->reverted($swap);

                return $swap;
            }, attempts: 3);
        } catch (QueryException $exception) {
            if (! $this->causedByConcurrencyError($exception)) {
                throw $exception;
            }
            throw ValidationException::withMessages(['reversion' => 'Outra operação está alterando estes registros. Abra a revisão novamente para conferir o estado atual.']);
        }
    }
}
