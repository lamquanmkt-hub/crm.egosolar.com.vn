<?php

namespace App\Models\Tasks;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class Task extends Model
{
    protected $table = 'tasks';

    protected $fillable = [
        'title',
        'description',
        'task_type',
        'project_id',
        'work_item',
        'work_location',
        'requester_id',
        'assignee_id',
        'approver_id',
        'priority',
        'status',
        'due_at',
        'link_url',
        'attachment_path',
        'progress_percent',
        'actual_minutes',
        'result_note',
        'issue_note',
        'result_attachment_path',
        'completed_at',
    ];

    protected $casts = [
        'due_at' => 'datetime',
        'completed_at' => 'datetime',
        'actual_minutes' => 'integer',
        'progress_percent' => 'integer',
    ];

    public function requester()
    {
        return $this->belongsTo(User::class, 'requester_id');
    }

    public function project()
    {
        return $this->belongsTo(\App\Models\ProjectTest\Project::class, 'project_id');
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approver_id');
    }

    public function assignee()
    {
        return $this->belongsTo(User::class, 'assignee_id');
    }
}
