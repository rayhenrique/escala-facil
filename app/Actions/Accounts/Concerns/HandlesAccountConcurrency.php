<?php

namespace App\Actions\Accounts\Concerns;

use App\Actions\Accounts\ActivateAccountAction;
use App\Models\User;
use App\Support\OperationalAuditContext;
use Closure;
use Illuminate\Database\DeadlockException;
use Illuminate\Database\DetectsConcurrencyErrors;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

trait HandlesAccountConcurrency
{
    use DetectsConcurrencyErrors;

    private function accountWrite(?User $manager, Closure $write): mixed
    {
        try {
            return $manager ? app(OperationalAuditContext::class)->transaction($manager, $write, attempts: 3)
                : DB::transaction($write, attempts: 3);
        } catch (QueryException|DeadlockException $exception) {
            if (! $this->causedByConcurrencyError($exception)) {
                throw $exception;
            }
            throw ValidationException::withMessages(['invitation' => $manager
                ? 'Outra operação alterou este convite. Atualize a lista e tente novamente.' : ActivateAccountAction::INVALID]);
        }
    }
}
