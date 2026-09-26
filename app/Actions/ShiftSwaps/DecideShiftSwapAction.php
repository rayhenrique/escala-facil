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

class DecideShiftSwapAction
{
    public function __construct(private readonly ShiftSwapNotificationService $notifications) {}

    public function approve(User $manager, int $shiftSwapId): ShiftSwap
    {
        $shiftSwap = DB::transaction(function () use ($manager, $shiftSwapId): ShiftSwap {
            $shiftId = ShiftSwap::query()->whereKey($shiftSwapId)->firstOrFail(['shift_id'])->shift_id;
            $shift = Shift::query()->lockForUpdate()->findOrFail($shiftId);
            $shiftSwap = ShiftSwap::query()
                ->with(['requester', 'substitute', 'shift'])
                ->lockForUpdate()
                ->findOrFail($shiftSwapId);

            $participants = User::query()
                ->whereKey([$shiftSwap->requester_id, $shiftSwap->substitute_id])
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            $shiftSwap->setRelation('shift', $shift);
            $shiftSwap->setRelation('requester', $participants->get($shiftSwap->requester_id));
            $shiftSwap->setRelation('substitute', $participants->get($shiftSwap->substitute_id));

            Gate::forUser($manager)->authorize('approve', $shiftSwap);

            $shiftSwap->update([
                'status' => ShiftSwapStatus::Approved,
                'manager_id' => $manager->id,
            ]);

            $shift->update([
                'user_id' => $shiftSwap->substitute_id,
                'status' => ShiftStatus::Scheduled,
            ]);

            return $shiftSwap->refresh();
        });

        $this->notifications->approved($shiftSwap);

        return $shiftSwap;
    }

    public function reject(User $manager, int $shiftSwapId): ShiftSwap
    {
        $shiftSwap = DB::transaction(function () use ($manager, $shiftSwapId): ShiftSwap {
            $shiftSwap = ShiftSwap::query()->lockForUpdate()->findOrFail($shiftSwapId);

            Gate::forUser($manager)->authorize('reject', $shiftSwap);

            $shiftSwap->update([
                'status' => ShiftSwapStatus::Rejected,
                'manager_id' => $manager->id,
            ]);

            return $shiftSwap->refresh();
        });

        $this->notifications->rejected($shiftSwap);

        return $shiftSwap;
    }
}
