<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Hồ sơ / tài liệu lưu trữ theo công trình điện mặt trời (hợp đồng, bản vẽ, nghiệm thu...).
 */
class SolarSiteDocument extends Model
{
    use SoftDeletes;

    protected $fillable = [
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
        'deleted_at',
    ];

    protected $casts = [
        'file_size' => 'integer',
    ];

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class, 'site_id');
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
