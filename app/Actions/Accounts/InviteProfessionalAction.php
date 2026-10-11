<?php

namespace App\Actions\Accounts;

use App\Actions\Accounts\Concerns\HandlesAccountConcurrency;
use App\Http\Requests\CreateInvitationRequest;
use App\Models\AccountInvitation;
use App\Models\User;
use App\Notifications\AccountInvitationNotification;
use App\Support\InvitationDelivery;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

class InviteProfessionalAction
{
    use HandlesAccountConcurrency;

    public function create(User $manager, array $payload): AccountInvitation
    {
        Gate::forUser($manager)->authorize('create', AccountInvitation::class);
        $validated = (new CreateInvitationRequest)->validatePayload($payload, $manager);
        $this->throttle($manager);
        InvitationDelivery::mailer();

        return $this->accountWrite($manager, function () use ($manager, $validated): AccountInvitation {
            $manager = User::query()->lockForUpdate()->findOrFail($manager->id);
            Gate::forUser($manager)->authorize('create', AccountInvitation::class);
            // Recheck uniqueness and references at the effective backend write.
            $validated = (new CreateInvitationRequest)->validatePayload($validated, $manager);
            $user = User::create(Arr::only($validated, ['name', 'email', 'role', 'sector_id', 'category_id']) + ['password' => bin2hex(random_bytes(48)), 'requires_activation' => true]);

            return $this->issue($manager, $user);
        });
    }

    public function resend(User $manager, int $userId): AccountInvitation
    {
        return $this->change($manager, $userId, false);
    }

    public function revoke(User $manager, int $userId): AccountInvitation
    {
        return $this->change($manager, $userId, true);
    }

    private function change(User $manager, int $userId, bool $revoke): AccountInvitation
    {
        Gate::forUser($manager)->authorize('create', AccountInvitation::class);
        $this->throttle($manager);

        if (! $revoke) {
            InvitationDelivery::mailer();
        }

        return $this->accountWrite($manager, function () use ($manager, $userId, $revoke): AccountInvitation {
            $users = User::whereKey([$manager->id, $userId])->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            $manager = $users->get($manager->id);
            $user = $users->get($userId);
            abort_unless($manager && $user, 404);
            $invitation = AccountInvitation::where('user_id', $userId)->lockForUpdate()->firstOrFail();
            $invitation->setRelation('user', $user);
            Gate::forUser($manager)->authorize('update', $invitation);
            if ($revoke) {
                $invitation->update(['revoked_at' => now(), 'token_hash' => null]);

                return $invitation;
            }

            return $this->issue($manager, $user, $invitation);
        });
    }

    private function issue(User $manager, User $user, ?AccountInvitation $invitation = null): AccountInvitation
    {
        $token = bin2hex(random_bytes(32));
        $data = ['user_id' => $user->id, 'invited_by' => $manager->id, 'token_hash' => hash('sha256', $token),
            'email_hash' => hash('sha256', $user->email), 'generation' => ($invitation?->generation ?? 0) + 1,
            'expires_at' => now()->addHours(max(1, config('invitations.expire_hours'))), 'last_sent_at' => now(), 'revoked_at' => null];
        if ($invitation) {
            $invitation->update($data);
        } else {
            $invitation = AccountInvitation::create($data);
        }
        $user->notify(new AccountInvitationNotification($invitation->id, $token));

        return $invitation;
    }

    private function throttle(User $manager): void
    {
        $key = 'account-invitation-manager:'.$manager->id;
        if (RateLimiter::tooManyAttempts($key, 20)) {
            throw ValidationException::withMessages(['invitation' => 'Aguarde um minuto antes de enviar outro convite.']);
        }
        RateLimiter::hit($key, 60);
    }
}
