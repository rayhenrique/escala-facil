<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ShiftReallocation extends Model
{
    protected $fillable = ['shift_id', 'manager_id', 'professional_id', 'manager_name', 'performed_at', 'before', 'after'];

    protected function casts(): array
    {
        return ['before' => 'array', 'after' => 'array', 'performed_at' => 'immutable_datetime'];
    }
}
