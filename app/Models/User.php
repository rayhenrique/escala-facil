<?php

namespace App\Models;

use App\Enums\ShiftSwapStatus;
use App\Enums\UserRole;
use App\Models\Concerns\HasOperationalAudit;
use Carbon\CarbonImmutable;
use Database\Factories\UserFactory;
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

    public function hasReachedSwapLimit(int $month, int $year, bool $lockForUpdate = false): bool
    {
        return $this->approvedSwapCount($month, $year, $lockForUpdate) >= self::MONTHLY_SWAP_LIMIT;
    }

    public function approvedSwapCount(int $month, int $year, bool $lockForUpdate = false): int
    {
        $start = CarbonImmutable::create($year, $month, 1, 0, 0, 0);
        $query = ShiftSwap::query()
            ->where('status', ShiftSwapStatus::Approved)
            ->where(function ($query) {
                $query->where('requester_id', $this->getKey())
                    ->orWhere('substitute_id', $this->getKey());
            })
            ->where('created_at', '>=', $start)
            ->where('created_at', '<', $start->addMonth());

        $participations = $lockForUpdate
            ? $query->lockForUpdate()->pluck('id')->count()
            : $query->count();

        return $participations;
    }
}
