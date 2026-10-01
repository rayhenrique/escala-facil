<?php

namespace App\Actions\ShiftSwaps;

use App\Enums\ShiftStatus;
use App\Enums\ShiftSwapStatus;
use App\Models\Shift;
use App\Models\ShiftSwap;
use App\Models\User;
use App\Services\ShiftSwapNotificationService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class RevertShiftSwapAction
{
    public function __construct(private readonly ShiftSwapNotificationService $notifications) {}

    public function execute(User $manager, int $shiftSwapId): ShiftSwap
    {
        return DB::transaction(function () use ($manager, $shiftSwapId): ShiftSwap {
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
