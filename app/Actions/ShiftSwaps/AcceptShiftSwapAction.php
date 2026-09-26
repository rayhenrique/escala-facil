<?php

namespace App\Actions\ShiftSwaps;

use App\Enums\ShiftSwapStatus;
use App\Models\ShiftSwap;
use App\Models\User;
use App\Services\ShiftSwapNotificationService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class AcceptShiftSwapAction
{
    public function __construct(private readonly ShiftSwapNotificationService $notifications) {}

    public function execute(User $substitute, int $shiftSwapId): ShiftSwap
    {
        $shiftSwap = DB::transaction(function () use ($substitute, $shiftSwapId): ShiftSwap {
            $shiftSwap = ShiftSwap::query()
                ->with(['requester', 'substitute', 'shift'])
                ->lockForUpdate()
                ->findOrFail($shiftSwapId);

            Gate::forUser($substitute)->authorize('accept', $shiftSwap);

            $shiftSwap->update(['status' => ShiftSwapStatus::PendingManager]);

            return $shiftSwap->refresh();
        });

        $this->notifications->accepted($shiftSwap);

        return $shiftSwap;
    }
}
