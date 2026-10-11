<?php

namespace App\Actions\Shifts;

use App\Actions\Shifts\Concerns\HandlesShiftConcurrency;
use App\Http\Requests\SaveShiftRequest;
use App\Models\Shift;
use App\Models\User;
use App\Support\ShiftAssignmentAnalysis;
use Illuminate\Support\Facades\Gate;

class CreateShiftAction
{
    use HandlesShiftConcurrency;

    public function execute(User $manager, array $payload, ?string $expected = null): Shift
    {
        return $this->writeShift($manager, function () use ($manager, $payload, $expected): Shift {
            $users = User::query()->whereKey(array_filter([$manager->id, $payload['user_id'] ?? null]))
                ->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            $manager = $users->get($manager->id);
            abort_unless($manager, 403);
            Gate::forUser($manager)->authorize('create', Shift::class);
            $validated = (new SaveShiftRequest)->validatePayload($payload, $manager);
            $candidate = new Shift($validated);
            $analysis = app(ShiftAssignmentAnalysis::class);
            $analysis->validateConfirmation($analysis->preview($candidate, lockForUpdate: true), $expected);

            return Shift::query()->create($validated);
        });
    }
}
