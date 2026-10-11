<?php

namespace App\Actions\ShiftSwaps;

use App\Enums\ShiftSwapStatus;
use App\Http\Requests\StoreShiftSwapRequest;
use App\Models\Shift;
use App\Models\ShiftSwap;
use App\Models\User;
use App\Services\ShiftSwapNotificationService;
use App\Support\OperationalAuditContext;

class CreateShiftSwapAction
{
    public function __construct(private readonly ShiftSwapNotificationService $notifications) {}

    /** @param array{shift_id: int|string, substitute_id: int|string, substitute_shift_id?: int|string|null} $payload */
    public function execute(User $requester, array $payload): ShiftSwap
    {
        return app(OperationalAuditContext::class)->transaction($requester, function () use ($requester, $payload): ShiftSwap {
            Shift::query()->lockForUpdate()->findOrFail($payload['shift_id']);

            if (! empty($payload['substitute_shift_id'])) {
                Shift::query()->lockForUpdate()->findOrFail($payload['substitute_shift_id']);
            }

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
