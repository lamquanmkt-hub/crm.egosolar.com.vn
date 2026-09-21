<?php

declare(strict_types=1);

namespace App\Services\Warranty;

use App\Models\SolarWarrantyClaim;
use App\Models\User;
use App\Support\SchemaCache;
use App\Support\SolarMaintenanceAccess;
use App\Support\Warranty\WarrantyException;
use App\Support\Warranty\WarrantyFlow;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * File minh chứng: lưu PRIVATE disk (không có URL công khai), tên ngẫu nhiên,
 * kiểm MIME thật (theo nội dung), giới hạn số lượng/dung lượng, ghi audit upload/xóa.
 */
final class EvidenceStore
{
    private const EXT = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'application/pdf' => 'pdf'];

    /** @param array<int, UploadedFile|null> $files */
    public function store(array $files, SolarWarrantyClaim $claim, User $user, ?string $step = null): int
    {
        $files = array_values(array_filter($files));
        if (! $files) {
            return 0;
        }
        if (! SchemaCache::hasTable('solar_warranty_claim_attachments')) {
            // KHÔNG được im lặng bỏ qua file (lỗi P0 cũ).
            throw new WarrantyException('Hệ thống chưa có bảng lưu minh chứng (chưa chạy migration). Vui lòng liên hệ quản trị.');
        }
        if (! WarrantyFlow::isOpen($claim->status)) {
            throw new WarrantyException('Phiếu đã đóng/hủy — không thể thêm minh chứng.');
        }
        $max = (int) config('warranty.evidence_max_files', 8);
        $current = DB::table('solar_warranty_claim_attachments')->where('warranty_claim_id', $claim->id)->whereNull('deleted_at')->count();
        if (count($files) > $max || $current + count($files) > $max * 4) {
            throw new WarrantyException('Tối đa '.$max.' tệp mỗi lần tải lên.');
        }

        $disk = (string) config('warranty.evidence_disk', 'local');
        $allowed = (array) config('warranty.evidence_mimes', array_keys(self::EXT));
        $maxBytes = (int) config('warranty.evidence_max_kb', 20480) * 1024;
        $n = 0;
        foreach ($files as $file) {
            $mime = (string) $file->getMimeType(); // sniff theo nội dung, không tin đuôi file
            if (! in_array($mime, $allowed, true)) {
                throw new WarrantyException('Minh chứng chỉ nhận JPG, PNG, WEBP hoặc PDF (tệp “'.$file->getClientOriginalName().'” không hợp lệ).');
            }
            if ($file->getSize() > $maxBytes) {
                throw new WarrantyException('Tệp “'.$file->getClientOriginalName().'” vượt quá '.((int) ($maxBytes / 1048576)).'MB.');
            }
            $ext = self::EXT[$mime] ?? 'bin';
            $path = 'warranty-claims/'.$claim->id.'/'.Str::random(40).'.'.$ext;
            Storage::disk($disk)->put($path, file_get_contents($file->getRealPath()));
            $id = (int) DB::table('solar_warranty_claim_attachments')->insertGetId([
                'warranty_claim_id' => $claim->id, 'category' => 'evidence', 'file_path' => $path,
                'original_name' => Str::limit(basename((string) $file->getClientOriginalName()), 200, ''),
                'mime_type' => $mime, 'file_size' => (int) $file->getSize(), 'uploaded_by' => $user->id,
                'disk' => $disk, 'step' => $step, 'created_at' => now(), 'updated_at' => now(),
            ]);
            WarrantyAudit::log((int) $claim->id, 'evidence_upload', null, null, null, ['attachment_id' => $id, 'name' => $file->getClientOriginalName(), 'mime' => $mime], null, (int) $user->id, 'attachment', $id);
            $n++;
        }

        return $n;
    }

    public function find(SolarWarrantyClaim $claim, int $attachmentId): object
    {
        $row = DB::table('solar_warranty_claim_attachments')
            ->where('id', $attachmentId)->where('warranty_claim_id', $claim->id)->whereNull('deleted_at')->first();
        abort_unless($row, 404);

        return $row;
    }

    public function absolutePath(object $row): string
    {
        $disk = (string) ($row->disk ?? 'public');
        abort_unless(Storage::disk($disk)->exists((string) $row->file_path), 404);

        return Storage::disk($disk)->path((string) $row->file_path);
    }

    public function delete(SolarWarrantyClaim $claim, int $attachmentId, User $user, ?string $reason): void
    {
        $row = $this->find($claim, $attachmentId);
        if (! WarrantyFlow::isOpen($claim->status)) {
            throw new WarrantyException('Phiếu đã đóng/hủy — không được xóa minh chứng.');
        }
        $isLead = SolarMaintenanceAccess::isTechnicalLead($user);
        // sau khi đã gửi duyệt chỉ Trưởng phòng/Admin được gỡ; trước đó người tải lên được gỡ file của mình
        if (! $isLead && (int) $row->uploaded_by !== (int) $user->id) {
            throw new WarrantyException('Bạn chỉ được gỡ minh chứng do chính mình tải lên.');
        }
        if (! $isLead && ! in_array($claim->status, ['pending_approval', 'needs_more_information', 'diagnosing', 'quotation_draft'], true)) {
            throw new WarrantyException('Phiếu đã qua bước duyệt — chỉ Trưởng phòng/Admin được gỡ minh chứng.');
        }

        DB::table('solar_warranty_claim_attachments')->where('id', $row->id)->update([
            'deleted_at' => now(), 'deleted_by' => $user->id, 'deleted_reason' => $reason, 'updated_at' => now(),
        ]);
        WarrantyAudit::log((int) $claim->id, 'evidence_delete', null, null, ['attachment_id' => $row->id, 'name' => $row->original_name], null, $reason, (int) $user->id, 'attachment', (int) $row->id);
    }
}
