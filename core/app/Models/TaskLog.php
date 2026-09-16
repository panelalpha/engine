<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TaskLog extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'task_id',
        'log',
    ];

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }
}
