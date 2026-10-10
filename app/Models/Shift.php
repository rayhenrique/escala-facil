<?php

namespace App\Models;

use App\Enums\ShiftStatus;
use App\Support\ShiftPeriod;
use Database\Factories\ShiftFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Shift extends Model
{
    /** @use HasFactory<ShiftFactory> */
    use HasFactory;

    protected $fillable = [
        'date',
        'start_time',
        'end_time',
        'end_date',
        'sector_id',
        'category_id',
        'user_id',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'id' => 'integer',
            'date' => 'immutable_date',
            'end_date' => 'immutable_date',
            'sector_id' => 'integer',
            'category_id' => 'integer',
            'user_id' => 'integer',
            'status' => ShiftStatus::class,
        ];
    }

    public function period(): ShiftPeriod
    {
        return new ShiftPeriod($this->date->format('Y-m-d'), (string) $this->start_time, (string) $this->end_time, $this->end_date?->format('Y-m-d'));
    }

    public function getTimeLabelAttribute(): string
    {
        return $this->period()->timeLabel();
    }

    public function sector(): BelongsTo
    {
        return $this->belongsTo(Sector::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function swaps(): HasMany
    {
        return $this->hasMany(ShiftSwap::class);
    }

    public function absences(): HasMany
    {
        return $this->hasMany(Absence::class);
    }

    public function latestAbsence(): HasOne
    {
        return $this->hasOne(Absence::class)->ofMany(['created_at' => 'max', 'id' => 'max']);
    }
}
