<?php

namespace App\Models;

use App\Enums\ShiftSwapStatus;
use Database\Factories\ShiftSwapFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShiftSwap extends Model
{
    /** @use HasFactory<ShiftSwapFactory> */
    use HasFactory;

    protected $fillable = [
        'shift_id',
        'substitute_shift_id',
        'requester_id',
        'substitute_id',
        'status',
        'manager_id',
        'rejection_reason',
    ];

    protected function casts(): array
    {
        return [
            'id' => 'integer',
            'shift_id' => 'integer',
            'substitute_shift_id' => 'integer',
            'requester_id' => 'integer',
            'substitute_id' => 'integer',
            'manager_id' => 'integer',
            'status' => ShiftSwapStatus::class,
        ];
    }

    public function shift(): BelongsTo
    {
        return $this->belongsTo(Shift::class);
    }

    public function substituteShift(): BelongsTo
    {
        return $this->belongsTo(Shift::class, 'substitute_shift_id');
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requester_id');
    }

    public function substitute(): BelongsTo
    {
        return $this->belongsTo(User::class, 'substitute_id');
    }

    public function manager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'manager_id');
    }
}
