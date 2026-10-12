<?php

namespace App\Actions\ShiftSwaps;

use App\Models\ShiftSwap;
use App\Models\User;

class DeclineShiftSwapAction
{
    public function execute(User $substitute, int $shiftSwapId, ?string $reason = null, ?string $expectedSnapshot = null): ShiftSwap
    {
        return app(RespondToShiftSwapAction::class)->execute($substitute, $shiftSwapId, 'decline', $reason, $expectedSnapshot);
    }
}
