<?php

namespace App\Actions\Accounts;

use App\Actions\Accounts\Concerns\HandlesAccountConcurrency;
use App\Http\Requests\ActivateAccountRequest;
use App\Models\AccountInvitation;
use App\Models\User;
use App\Support\OperationalAuditContext;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ActivateAccountAction
{
    use HandlesAccountConcurrency;

    public const INVALID = 'Não foi possível usar este convite. Solicite um novo link à gerência.';

    /** Only a server-side session grant is accepted by the interface, never a client-provided user ID. */
    public function execute(#[\SensitiveParameter] string $tokenHash, #[\SensitiveParameter] array $payload): User
    {
        $validated = (new ActivateAccountRequest)->validatePayload($payload);

        return $this->accountWrite(null, function () use ($tokenHash, $validated): User {
            $reference = preg_match('/^[a-f0-9]{64}$/', $tokenHash) ? AccountInvitation::where('token_hash', $tokenHash)->first() : null;
            if (! $reference) {
                throw ValidationException::withMessages(['invitation' => self::INVALID]);
            }
            $user = User::query()->lockForUpdate()->find($reference->user_id);
            $invitation = AccountInvitation::query()->lockForUpdate()->find($reference->id);
            if (! $user || ! $invitation || ! $invitation->usable($user) || ! hash_equals($invitation->token_hash, $tokenHash)) {
                throw ValidationException::withMessages(['invitation' => self::INVALID]);
            }

            return app(OperationalAuditContext::class)->transaction($user, function () use ($user, $invitation, $validated): User {
                $user->forceFill(['password' => $validated['password'], 'requires_activation' => false, 'remember_token' => Str::random(60)])->save();
                $invitation->update(['accepted_at' => now(), 'token_hash' => null]);
                Password::deleteToken($user);
                DB::afterCommit(fn () => event(new Registered($user)));

                return $user;
            });
        });
    }
}
