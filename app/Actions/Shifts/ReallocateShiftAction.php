<?php

namespace App\Actions\Shifts;

use App\Enums\ShiftStatus;
use App\Http\Requests\ReallocateShiftRequest;
use App\Models\Shift;
use App\Models\ShiftReallocation as Audit;
use App\Models\User;
use App\Support\OperationalAuditContext;
use App\Support\ShiftReallocation;
use Illuminate\Database\DetectsConcurrencyErrors;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class ReallocateShiftAction
{
    use DetectsConcurrencyErrors;

    public function execute(User $manager, int $shiftId, int $professionalId, string $expectedSnapshot): Audit
    {
        try {
            return app(OperationalAuditContext::class)->transaction($manager, function () use ($manager, $shiftId, $professionalId, $expectedSnapshot): Audit {
                $users = User::query()->whereKey([$manager->id, $professionalId])->orderBy('id')->lockForUpdate()->get()->keyBy('id');
                $manager = $users->get($manager->id);
                abort_unless($manager, 403);
                Gate::forUser($manager)->authorize('viewAny', Shift::class);
                $shift = Shift::query()->lockForUpdate()->findOrFail($shiftId);
                Gate::forUser($manager)->authorize('reallocate', $shift);
                (new ReallocateShiftRequest)->validatePayload(['reallocationUserId' => $professionalId], $manager);
                $professional = $users->get($professionalId);
                if (! $professional) {
                    throw ValidationException::withMessages(['reallocationUserId' => 'O cadastro do profissional mudou. Abra a realocação novamente.']);
                }
                $analysis = app(ShiftReallocation::class);
                $preview = $analysis->preview($shift, $professional, lockForUpdate: true);
                if (! hash_equals($expectedSnapshot, $preview['fingerprint'])) {
                    throw ValidationException::withMessages(['reallocation' => 'O plantão, o cadastro ou a disponibilidade mudou. Feche e abra a realocação novamente antes de confirmar.']);
                }
                $before = $analysis->snapshot($shift);
                $shift->update(['user_id' => $professionalId, 'status' => ShiftStatus::Scheduled]);
                $shift->setRelation('user', $professional);

                return Audit::query()->create([
                    'shift_id' => $shiftId, 'professional_id' => $professionalId,
                    'manager_id' => $manager->id, 'manager_name' => $manager->name,
                    'performed_at' => now(), 'before' => $before, 'after' => $analysis->snapshot($shift),
                ]);
            }, attempts: 3);
        } catch (QueryException $exception) {
            if (! $this->causedByConcurrencyError($exception)) {
                throw $exception;
            }
            throw ValidationException::withMessages(['reallocation' => 'Outra operação está alterando estes registros. Abra a realocação novamente para conferir o estado do plantão.']);
        }
    }
}
