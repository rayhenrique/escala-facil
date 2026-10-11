<?php

namespace App\Models;

use App\Models\Concerns\ImmutableAuditBuilder;
use Illuminate\Database\Eloquent\Model;
use LogicException;

class ShiftSwapReversal extends Model
{
    public $timestamps = false;

    protected $dateFormat = 'Y-m-d H:i:s.u';

    protected $fillable = ['shift_swap_id', 'author_id', 'approval_event_id', 'occurred_at', 'operation_id', 'reason', 'before', 'after'];

    public function newEloquentBuilder($query)
    {
        return new ImmutableAuditBuilder($query);
    }

    protected function casts(): array
    {
        return ['shift_swap_id' => 'integer', 'author_id' => 'integer', 'approval_event_id' => 'integer', 'before' => 'array', 'after' => 'array', 'occurred_at' => 'immutable_datetime'];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('A reversão registrada não pode ser editada.'));
        static::deleting(fn () => throw new LogicException('A reversão registrada não pode ser excluída.'));
    }
}
