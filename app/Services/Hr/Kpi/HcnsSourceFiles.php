<?php

declare(strict_types=1);

namespace App\Services\Hr\Kpi;

use App\Support\SchemaCache;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Tệp minh chứng riêng tư cho hồ sơ nguồn KPI HCNS chưa có kho tệp riêng (phiếu cấp phát VPP, đợt kiểm kê).
 * Lưu disk "local" (không có URL public); xem/tải qua route kiểm quyền của từng module.
 */
final class HcnsSourceFiles
{
    public const MAX_KB = 20480;

    public const MIMES = 'jpg,jpeg,png,webp,heic,pdf,doc,docx,xls,xlsx,csv';

    public static function ready(): bool
    {
        return SchemaCache::hasTable('hr_kpi_source_files');
    }

    public function list(string $sourceType, int $sourceId): Collection
    {
        if (! self::ready()) {
            return collect();
        }

        return DB::table('hr_kpi_source_files as f')->leftJoin('users as u', 'u.id', '=', 'f.uploaded_by')
            ->where('f.source_type', $sourceType)->where('f.source_id', $sourceId)->orderBy('f.id')
            ->get(['f.*', 'u.name as uploader_name']);
    }

    public function find(string $sourceType, int $sourceId, int $fileId): ?object
    {
        return DB::table('hr_kpi_source_files')->where('id', $fileId)->where('source_type', $sourceType)->where('source_id', $sourceId)->first();
    }

    /** @param  UploadedFile[]  $files */
    public function store(string $sourceType, int $sourceId, array $files, int $userId): int
    {
        $count = 0;
        foreach ($files as $file) {
            if (! $file || ! $file->isValid()) {
                continue;
            }
            $path = $file->store('hcns-kpi/'.$sourceType.'/'.$sourceId, 'local');
            DB::table('hr_kpi_source_files')->insert([
                'source_type' => $sourceType, 'source_id' => $sourceId, 'disk' => 'local', 'path' => $path,
                'original_name' => mb_substr($file->getClientOriginalName(), 0, 255), 'mime_type' => $file->getClientMimeType(),
                'size' => $file->getSize(), 'uploaded_by' => $userId, 'created_at' => now(), 'updated_at' => now(),
            ]);
            $count++;
        }

        return $count;
    }

    public function delete(object $file): void
    {
        Storage::disk($file->disk ?: 'local')->delete($file->path);
        DB::table('hr_kpi_source_files')->where('id', $file->id)->delete();
    }

    /** Danh sách tệp dạng chuỗi ổn định để đưa vào fingerprint (đổi/thêm/xóa tệp → phải kiểm lại). */
    public function fingerprintPart(string $sourceType, int $sourceId): array
    {
        return $this->list($sourceType, $sourceId)->map(fn ($f) => $f->id.':'.$f->original_name.':'.$f->size)->all();
    }

    /** Trả response xem (inline) hoặc tải tệp. */
    public function respond(object $file, bool $download)
    {
        $disk = Storage::disk($file->disk ?: 'local');
        abort_unless($disk->exists($file->path), 404);
        $name = (string) ($file->original_name ?: basename($file->path));
        // Tệp xem nhanh/tải không phải một trang: không lưu làm “trang trước” của phiên (StartSession bỏ qua request
        // prefetch), để thông báo lỗi/“quay lại” sau khi xem tệp trở về đúng hồ sơ thay vì mở tệp.
        request()->headers->set('Purpose', 'prefetch');

        return $download
            ? $disk->download($file->path, $name)
            : $disk->response($file->path, $name, ['Content-Type' => $file->mime_type ?: 'application/octet-stream', 'X-Content-Type-Options' => 'nosniff']);
    }
}
