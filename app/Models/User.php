<?php

namespace App\Models;

use App\Enums\ShiftSwapStatus;
use App\Enums\UserRole;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    public const MONTHLY_SWAP_LIMIT = 4;

    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'category_id',
        'sector_id',
        'last_seen_version',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'id' => 'integer',
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

    public function hasReachedSwapLimit(int $month, int $year): bool
    {
        return ShiftSwap::query()
            ->where('status', ShiftSwapStatus::Approved)
            ->where(function ($query) {
                $query->where('requester_id', $this->getKey())
                    ->orWhere('substitute_id', $this->getKey());
            })
            ->whereMonth('created_at', $month)
            ->whereYear('created_at', $year)
            ->count() >= self::MONTHLY_SWAP_LIMIT;
    }
}
