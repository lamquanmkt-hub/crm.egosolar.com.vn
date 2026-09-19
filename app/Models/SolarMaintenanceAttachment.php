<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\LockedToEgoInternational;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Tệp đính kèm của đợt bảo trì điện mặt trời (ảnh hiện trường, biên bản, hồ sơ...).
 */
class SolarMaintenanceAttachment extends Model
{
    use LockedToEgoInternational;
    use SoftDeletes;

    protected $fillable = [
        'maintenance_schedule_id',
        'maintenance_work_item_id',
        'checklist_item_id',
        'site_id',
        'company_id',
        'category',
        'disk',
        'file_name',
        'original_name',
        'file_path',
        'mime_type',
        'file_size',
        'description',
        'uploaded_by',
        'is_customer_visible',
        'deleted_at',
    ];

    protected $casts = [
        'is_customer_visible' => 'boolean',
        'file_size' => 'integer',
    ];

    public function schedule(): BelongsTo
    {
        return $this->belongsTo(SolarMaintenanceSchedule::class, 'maintenance_schedule_id');
    }

    public function workItem(): BelongsTo
    {
        return $this->belongsTo(SolarMaintenanceWorkItem::class, 'maintenance_work_item_id');
    }

    public function checklistItem(): BelongsTo
    {
        return $this->belongsTo(SolarMaintenanceChecklistItem::class, 'checklist_item_id');
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class, 'site_id');
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
