<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Tệp đính kèm của đợt bảo trì điện mặt trời (ảnh hiện trường, biên bản, hồ sơ...).
 */
class SolarMaintenanceAttachment extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'maintenance_schedule_id',
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

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class, 'site_id');
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
