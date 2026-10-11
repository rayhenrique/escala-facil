<?php

namespace App\Actions\ShiftSwaps;

use App\Enums\ShiftStatus;
use App\Enums\ShiftSwapStatus;
use App\Models\Shift;
use App\Models\ShiftSwap;
use App\Models\User;
use App\Services\ShiftSwapNotificationService;
use App\Support\OperationalAuditContext;
use Illuminate\Support\Facades\Gate;

class RevertShiftSwapAction
{
    public function __construct(private readonly ShiftSwapNotificationService $notifications) {}

    public function execute(User $manager, int $shiftSwapId): ShiftSwap
    {
        return app(OperationalAuditContext::class)->transaction($manager, function () use ($manager, $shiftSwapId): ShiftSwap {
            $shiftSwap = ShiftSwap::query()
                ->with(['requester', 'substitute'])
                ->lockForUpdate()
                ->findOrFail($shiftSwapId);

            $shift = Shift::query()->lockForUpdate()->findOrFail($shiftSwap->shift_id);
            $shiftSwap->setRelation('shift', $shift);

            Gate::forUser($manager)->authorize('revert', $shiftSwap);

            $shift->update([
                'user_id' => $shiftSwap->requester_id,
                'status' => ShiftStatus::Scheduled,
            ]);

            if ($shiftSwap->substitute_shift_id) {
                $substituteShift = Shift::query()->lockForUpdate()->findOrFail($shiftSwap->substitute_shift_id);
                $substituteShift->update([
                    'user_id' => $shiftSwap->substitute_id,
                    'status' => ShiftStatus::Scheduled,
                ]);
                $shiftSwap->setRelation('substituteShift', $substituteShift);
            }

            $shiftSwap->update([
                'status' => ShiftSwapStatus::Reverted,
                'manager_id' => $manager->id,
            ]);

            $shiftSwap = $shiftSwap->refresh();
            $this->notifications->reverted($shiftSwap);

            return $shiftSwap;
        });
    }
}
