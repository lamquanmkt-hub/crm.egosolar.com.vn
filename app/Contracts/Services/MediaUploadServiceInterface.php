<?php

declare(strict_types=1);

namespace App\Contracts\Services;

use App\Models\Media\MediaFile;
use Illuminate\Http\UploadedFile;

/**
 * Hợp đồng Tải lên, gắn và xoá tệp media (seam ra filesystem/storage).
 *
 * Sinh từ implementation MediaUploadService của chính dự án này (KHÔNG copy từ crm-shop —
 * signature hai codebase đã phân kỳ).
 */
interface MediaUploadServiceInterface
{
    public function upload(UploadedFile $file, array $options = []): MediaFile;

    public function uploadAndAttach(UploadedFile $file, $model, string $usage = 'gallery', array $options = []);

    public function delete(MediaFile $media): bool;
}
