<?php

declare(strict_types=1);

namespace App\Models\Technical;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Ảnh / file minh chứng của một báo cáo ngày.
 *
 * File được lưu trên disk PRIVATE (`local` → storage/app/private), không nằm
 * dưới public/storage, nên không thể tải bằng cách đoán đường dẫn. Mọi lượt
 * tải đều đi qua route có kiểm tra quyền.
 */
class TechnicalDailyReportFile extends Model
{
    protected $table = 'technical_daily_report_files';

    protected $fillable = [
        'report_id',
        'disk',
        'path',
        'original_name',
        'mime_type',
        'size',
        'uploaded_by',
    ];

    protected $casts = [
        'size' => 'integer',
    ];

    public function report(): BelongsTo
    {
        return $this->belongsTo(TechnicalDailyReport::class, 'report_id');
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function isImage(): bool
    {
        return str_starts_with((string) $this->mime_type, 'image/');
    }

    public function humanSize(): string
    {
        $bytes = (int) $this->size;

        if ($bytes < 1024) {
            return $bytes.' B';
        }

        if ($bytes < 1024 * 1024) {
            return round($bytes / 1024, 1).' KB';
        }

        return round($bytes / (1024 * 1024), 1).' MB';
    }
}
