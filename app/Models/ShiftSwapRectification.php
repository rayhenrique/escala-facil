<?php

namespace App\Models;

use App\Models\Concerns\ImmutableAuditBuilder;
use Illuminate\Database\Eloquent\Model;
use LogicException;

class ShiftSwapRectification extends Model
{
    public $timestamps = false;

    protected $dateFormat = 'Y-m-d H:i:s.u';

    protected $fillable = ['shift_swap_id', 'author_id', 'occurred_at', 'operation_id', 'kind', 'reason', 'evidence_notes', 'evidence', 'receipt'];

    public function newEloquentBuilder($query)
    {
        return new ImmutableAuditBuilder($query);
    }

    protected function casts(): array
    {
        return ['shift_swap_id' => 'integer', 'author_id' => 'integer', 'occurred_at' => 'immutable_datetime', 'evidence' => 'array', 'receipt' => 'array'];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('O registro administrativo não pode ser editado.'));
        static::deleting(fn () => throw new LogicException('O registro administrativo não pode ser excluído.'));
    }
}
