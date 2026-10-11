<?php

namespace App\Actions\ShiftSwaps;

use App\Enums\ShiftSwapStatus;
use App\Models\Shift;
use App\Models\ShiftSwap;
use App\Models\User;
use App\Services\ShiftSwapNotificationService;
use App\Support\OperationalAuditContext;
use Illuminate\Support\Facades\Gate;

class AcceptShiftSwapAction
{
    public function __construct(private readonly ShiftSwapNotificationService $notifications) {}

    public function execute(User $substitute, int $shiftSwapId): ShiftSwap
    {
        return app(OperationalAuditContext::class)->transaction($substitute, function () use ($substitute, $shiftSwapId): ShiftSwap {
            $shiftId = ShiftSwap::query()->whereKey($shiftSwapId)->firstOrFail(['shift_id'])->shift_id;

            Shift::query()
                ->lockForUpdate()
                ->findOrFail($shiftId);

            $shiftSwap = ShiftSwap::query()
                ->with(['requester', 'substitute', 'shift', 'substituteShift'])
                ->lockForUpdate()
                ->findOrFail($shiftSwapId);

            if ($shiftSwap->substitute_shift_id) {
                Shift::query()
                    ->lockForUpdate()
                    ->findOrFail($shiftSwap->substitute_shift_id);
            }

            Gate::forUser($substitute)->authorize('accept', $shiftSwap);

            $shiftSwap->update(['status' => ShiftSwapStatus::PendingManager]);

            $shiftSwap = $shiftSwap->refresh();
            $this->notifications->accepted($shiftSwap);

            return $shiftSwap;
        });
    }
}
