<?php

namespace App\Actions\Shifts;

use App\Http\Requests\SaveShiftRequest;
use App\Models\Shift;
use App\Models\User;
use App\Support\OperationalAuditContext;
use App\Support\ShiftReallocation;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class UpdateShiftAction
{
    public function execute(User $manager, int $id, array $payload, string $expected): Shift
    {
        return app(OperationalAuditContext::class)->transaction($manager, function () use ($manager, $id, $payload, $expected): Shift {
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
            $shift->update($validated);

            return $shift;
        }, attempts: 3);
    }
}
