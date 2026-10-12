<?php

namespace App\Actions\ShiftSwaps;

use App\Models\ShiftSwap;
use App\Models\User;

class AcceptShiftSwapAction
{
    public function execute(User $substitute, int $shiftSwapId, ?string $expectedSnapshot = null): ShiftSwap
    {
        return app(RespondToShiftSwapAction::class)->execute($substitute, $shiftSwapId, 'accept', expected: $expectedSnapshot);
    }
}
