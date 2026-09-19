<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class SolarMaintenanceWorkItem extends Model
{
    use SoftDeletes;

    protected $table = 'solar_maintenance_work_items';

    protected $fillable = [
        'maintenance_schedule_id', 'company_id', 'title', 'description',
        'assignee_id', 'status', 'progress_percent', 'started_at', 'completed_at',
        'estimated_minutes', 'actual_minutes', 'result_note', 'sort_order',
        'created_by', 'updated_by',
    ];

    protected $casts = [
        'progress_percent' => 'integer',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'estimated_minutes' => 'integer',
        'actual_minutes' => 'integer',
        'sort_order' => 'integer',
    ];

    public const STATUSES = [
        'pending' => 'Chưa bắt đầu',
        'in_progress' => 'Đang thực hiện',
        'blocked' => 'Đang vướng',
        'completed' => 'Hoàn thành',
        'cancelled' => 'Đã hủy',
    ];

    public function schedule(): BelongsTo
    {
        return $this->belongsTo(SolarMaintenanceSchedule::class, 'maintenance_schedule_id');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assignee_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(SolarMaintenanceAttachment::class, 'maintenance_work_item_id')->latest('id');
    }
}
