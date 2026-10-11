<?php

namespace App\Support;

use App\Models\User;
use Closure;
use Illuminate\Database\DeadlockException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class OperationalAuditContext
{
    private ?int $actor = null;

    private ?string $operation = null;

    public function authorId(): ?int
    {
        return $this->actor ?? Auth::id();
    }

    public function operationId(): string
    {
        return $this->operation ?? (string) Str::uuid();
    }

    /** Explicit action actors also work in jobs/CLI, without changing session identity. */
    public function transaction(User $actor, Closure $callback, int $attempts = 1): mixed
    {
        $operation = $this->operation ?? (string) Str::uuid();

        try {
            return DB::transaction(function () use ($actor, $callback, $operation) {
                $previous = [$this->actor, $this->operation];
                [$this->actor, $this->operation] = [$actor->id, $operation];
                try {
                    return $callback();
                } finally {
                    [$this->actor, $this->operation] = $previous;
                }
            }, $attempts);
        } catch (DeadlockException $exception) {
            // Nested model savepoints wrap query failures. Preserve the Actions' established error handling.
            $previous = $exception->getPrevious();
            if ($previous instanceof QueryException) {
                throw $previous;
            }
            throw $exception;
        }
    }
}
