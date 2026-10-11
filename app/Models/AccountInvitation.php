<?php

namespace App\Models;

use App\Enums\UserRole;
use App\Models\Concerns\HasOperationalAudit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AccountInvitation extends Model
{
    use HasOperationalAudit;

    protected $guarded = ['id'];

    protected $hidden = ['token_hash', 'email_hash'];

    protected function casts(): array
    {
        return ['id' => 'integer', 'user_id' => 'integer', 'invited_by' => 'integer', 'generation' => 'integer', 'expires_at' => 'immutable_datetime', 'last_sent_at' => 'immutable_datetime', 'accepted_at' => 'immutable_datetime', 'revoked_at' => 'immutable_datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function status(User $user): string
    {
        if ($this->accepted_at) {
            return 'Aceito';
        }
        if ($this->revoked_at || ! hash_equals($this->email_hash, hash('sha256', $user->email)) || $user->role !== UserRole::Professional) {
            return 'Revogado';
        }

        return $this->expires_at->isFuture() ? 'Pendente' : 'Expirado';
    }

    public function usable(User $user): bool
    {
        return $user->requires_activation && $this->token_hash !== null && $this->status($user) === 'Pendente';
    }
}
