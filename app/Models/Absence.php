<?php

namespace App\Models;

use App\Enums\AbsenceStatus;
use Database\Factories\AbsenceFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Absence extends Model
{
    /** @use HasFactory<AbsenceFactory> */
    use HasFactory;

    protected $fillable = [
        'shift_id',
        'user_id',
        'reason',
        'attachment_path',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'id' => 'integer',
            'shift_id' => 'integer',
            'user_id' => 'integer',
            'status' => AbsenceStatus::class,
        ];
    }

    public function shift(): BelongsTo
    {
        return $this->belongsTo(Shift::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
