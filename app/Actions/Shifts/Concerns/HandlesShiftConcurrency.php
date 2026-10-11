<?php

namespace App\Actions\Shifts\Concerns;

use App\Models\User;
use App\Support\OperationalAuditContext;
use Closure;
use Illuminate\Database\DetectsConcurrencyErrors;
use Illuminate\Database\QueryException;
use Illuminate\Validation\ValidationException;

trait HandlesShiftConcurrency
{
    use DetectsConcurrencyErrors;

    private function writeShift(User $manager, Closure $write): mixed
    {
        try {
            return app(OperationalAuditContext::class)->transaction($manager, $write, attempts: 3);
        } catch (QueryException $exception) {
            if (! $this->causedByConcurrencyError($exception)) {
                throw $exception;
            }
            throw ValidationException::withMessages(['shift' => 'Outra operação está alterando a escala. Revise os dados e os alertas antes de confirmar novamente.']);
        }
    }
}
