<?php

namespace App\Models;

use App\Support\IssueReportModules;
use Database\Factories\IssueReportFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IssueReport extends Model
{
    /** @use HasFactory<IssueReportFactory> */
    use HasFactory;

    protected $fillable = [
        'protocol',
        'user_id',
        'module',
        'description',
        'expected_behavior',
        'attachment_path',
    ];

    protected function casts(): array
    {
        return [
            'id' => 'integer',
            'user_id' => 'integer',
        ];
    }

    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function moduleLabel(): string
    {
        return IssueReportModules::label($this->module);
    }
}
