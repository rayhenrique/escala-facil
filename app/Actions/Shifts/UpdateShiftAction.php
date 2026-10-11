<?php

namespace App\Actions\Shifts;

use App\Actions\Shifts\Concerns\HandlesShiftConcurrency;
use App\Http\Requests\SaveShiftRequest;
use App\Models\Shift;
use App\Models\User;
use App\Support\ShiftAssignmentAnalysis;
use App\Support\ShiftReallocation;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class UpdateShiftAction
{
    use HandlesShiftConcurrency;

    public function execute(User $manager, int $id, array $payload, string $expected, ?string $expectedAnalysis = null): Shift
    {
        return $this->writeShift($manager, function () use ($manager, $id, $payload, $expected, $expectedAnalysis): Shift {
            $users = User::query()->whereKey(array_filter([$manager->id, $payload['user_id'] ?? null]))
                ->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            $manager = $users->get($manager->id);
            abort_unless($manager, 403);
            $shift = Shift::query()->lockForUpdate()->findOrFail($id);
            Gate::forUser($manager)->authorize('update', $shift);
            if (! hash_equals($expected, app(ShiftReallocation::class)->shiftSignature($shift))) {
                throw ValidationException::withMessages(['shift' => 'Este plantão foi alterado por outra operação. Feche o formulário e abra o registro novamente antes de salvar.']);
            }
            $validated = (new SaveShiftRequest)->validatePayload($payload, $manager);
            $candidate = new Shift($validated);
            $candidate->id = $shift->id;
            $analysis = app(ShiftAssignmentAnalysis::class);
            $analysis->validateConfirmation($analysis->preview($candidate, lockForUpdate: true), $expectedAnalysis);
            $shift->update($validated);

            return $shift;
        });
    }
}
