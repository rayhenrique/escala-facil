<?php

namespace App\Models;

use App\Enums\UserRole;
use App\Models\Concerns\HasOperationalAudit;
use Carbon\CarbonImmutable;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    use HasOperationalAudit;

    public const MONTHLY_SWAP_LIMIT = 4;

    public const SWAP_LIMIT_MESSAGE = 'Você atingiu o limite de quatro trocas aprovadas neste mês. Novas solicitações e aceites serão liberados no próximo mês.';

    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'category_id',
        'sector_id',
        'last_seen_version',
        'requires_activation',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'id' => 'integer',
            'requires_activation' => 'boolean',
            'password' => 'hashed',
            'role' => UserRole::class,
            'category_id' => 'integer',
            'sector_id' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::deleting(function (User $user): void {
            $user->notifications()->delete();
        });
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function invitation(): HasOne
    {
        return $this->hasOne(AccountInvitation::class);
    }

    public function sector(): BelongsTo
    {
        return $this->belongsTo(Sector::class);
    }

    public function shifts(): HasMany
    {
        return $this->hasMany(Shift::class);
    }

    public function requestedSwaps(): HasMany
    {
        return $this->hasMany(ShiftSwap::class, 'requester_id');
    }

    public function substituteSwaps(): HasMany
    {
        return $this->hasMany(ShiftSwap::class, 'substitute_id');
    }

    public function managedSwaps(): HasMany
    {
        return $this->hasMany(ShiftSwap::class, 'manager_id');
    }

    public function absences(): HasMany
    {
        return $this->hasMany(Absence::class);
    }

    public function hasReachedSwapLimit(?int $month = null, ?int $year = null, bool $lockForUpdate = false): bool
    {
        return $this->approvedSwapCount($month, $year, $lockForUpdate) >= self::MONTHLY_SWAP_LIMIT;
    }

    public function approvedSwapCount(?int $month = null, ?int $year = null, bool $lockForUpdate = false): int
    {
        $now = CarbonImmutable::now(config('app.timezone'));

        return $this->swapCountForMonth($now->startOfMonth()->setDate($year ?? $now->year, $month ?? $now->month, 1), $lockForUpdate);
    }

    public function swapCountForMonth(CarbonImmutable $reference, bool $lockForUpdate = false): int
    {
        $query = ShiftSwap::query()->approvedInMonth($reference)
            ->where(fn (Builder $query) => $query->where('requester_id', $this->getKey())->orWhere('substitute_id', $this->getKey()));

        return $lockForUpdate ? $query->lockForUpdate()->pluck('id')->count() : $query->count();
    }

    public function scopeWithAvailableSwapQuota(Builder $query): Builder
    {
        return $query->where(self::approvedSwapCountForUsers(CarbonImmutable::now(config('app.timezone'))), '<', self::MONTHLY_SWAP_LIMIT);
    }

    /** Count participations for the users selected by the outer query. */
    public static function approvedSwapCountForUsers(CarbonImmutable $reference): Builder
    {
        return ShiftSwap::query()->approvedInMonth($reference)
            ->selectRaw('COUNT(*)')
            ->where(fn (Builder $query) => $query->whereColumn('requester_id', 'users.id')->orWhereColumn('substitute_id', 'users.id'));
    }
}
