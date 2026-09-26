<?php

namespace App\Actions\ShiftSwaps;

use App\Enums\ShiftSwapStatus;
use App\Http\Requests\StoreShiftSwapRequest;
use App\Models\Shift;
use App\Models\ShiftSwap;
use App\Models\User;
use App\Services\ShiftSwapNotificationService;
use Illuminate\Support\Facades\DB;

class CreateShiftSwapAction
{
    public function __construct(private readonly ShiftSwapNotificationService $notifications) {}

    /** @param array{shift_id: int|string, substitute_id: int|string} $payload */
    public function execute(User $requester, array $payload): ShiftSwap
    {
        return DB::transaction(function () use ($requester, $payload): ShiftSwap {
            Shift::query()->lockForUpdate()->findOrFail($payload['shift_id']);

            $validated = (new StoreShiftSwapRequest)->validatePayload($payload, $requester);

            $shiftSwap = ShiftSwap::query()->create([
                ...$validated,
                'requester_id' => $requester->id,
                'status' => ShiftSwapStatus::PendingProfessional,
            ]);

            $this->notifications->requested($shiftSwap);

            return $shiftSwap;
        });
    }
}
