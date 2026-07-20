<?php

namespace App\Models\Tasks;

use Illuminate\Database\Eloquent\Model;

class WeeklyTask extends Model
{
    protected $table = 'weekly_tasks';

    protected $fillable = [
        'title',
        'priority',
        'category',
        'assignee',     // legacy string
        'assignees',    // json
        'start_date',
        'due_date',
        'status',
        'progress',
        'note',
        'links',        // json
        'attachments',  // json
    ];

    protected $casts = [
        'assignees' => 'array',
        'links' => 'array',
        'attachments' => 'array',
        'start_date' => 'date',
        'due_date' => 'date',
        'progress' => 'integer',
    ];
}
