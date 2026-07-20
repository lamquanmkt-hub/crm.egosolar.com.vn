<?php

namespace App\Services;

use App\Contracts\Services\MediaUploadServiceInterface;
use App\Models\Media\MediaFile;
use App\Models\Media\MediaMetadata;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\ImageManagerStatic as Image;

/**
 * Service upload và quản lý file media (media_files + media_metadata).
 */
class MediaUploadService implements MediaUploadServiceInterface
{
    protected string $disk = 'public';

    /**
     * Upload file, create media_files + media_metadata
     * Returns MediaFile instance.
     */
    public function upload(UploadedFile $file, array $options = []): MediaFile
    {
        return DB::transaction(function () use ($file, $options) {
            $path = $file->store('uploads/media', $this->disk);
            $media = MediaFile::create([
                'file_name' => $file->hashName(),
                'file_path' => $path,
                'mime_type' => $file->getClientMimeType(),
                'file_size' => $file->getSize(),
                'alt_text' => $options['alt_text'] ?? null,
                'title' => $options['title'] ?? null,
                'caption' => $options['caption'] ?? null,
                'uploaded_by' => $options['uploaded_by'] ?? auth()->id(),
            ]);

            // Try to gather basic metadata (image width/height) — optional (Intervention)
            $meta = [
                'width' => null,
                'height' => null,
                'duration' => null,
                'bitrate' => null,
            ];

            try {
                if (str_starts_with($file->getClientMimeType(), 'image/')) {
                    // If Intervention is available
                    if (class_exists(\Intervention\Image\ImageManagerStatic::class)) {
                        $img = Image::make($file->getRealPath());
                        $meta['width'] = $img->width();
                        $meta['height'] = $img->height();
                    }
                }
            } catch (\Throwable $e) {
                // ignore metadata extraction failure
            }

            MediaMetadata::create([
                'media_id' => $media->id,
                'width' => $meta['width'],
                'height' => $meta['height'],
                'duration' => $meta['duration'],
                'bitrate' => $meta['bitrate'],
                'metadata' => $options['extra'] ?? null,
            ]);

            return $media;
        });
    }

    /**
     * Upload and attach to $model (Eloquent model) in single call.
     */
    public function uploadAndAttach(UploadedFile $file, $model, string $usage = 'gallery', array $options = [])
    {
        $media = $this->upload($file, $options);

        return $model->attachExistingMedia($media, $usage, $options['sort_order'] ?? 0, $options['uploaded_by'] ?? auth()->id());
    }

    /**
     * Delete physical file + DB records
     */
    public function delete(MediaFile $media): bool
    {
        return DB::transaction(function () use ($media) {
            // delete physical file if exists
            if (Storage::disk($this->disk)->exists($media->file_path)) {
                Storage::disk($this->disk)->delete($media->file_path);
            }

            // delete metadata & relations cascade handled by DB foreign key
            return $media->delete();
        });
    }
    //	/**
    //	 * @param UploadedFile $file
    //	 *
    //	 * @return MediaFile
    //	 */
    //	public function upload(UploadedFile $file): MediaFile
    //	{
    //		// 1. Lưu file vào storage
    //		$path = $file->store('uploads/media', 'public');
    //		// 2. Tạo media_files
    //		$media = MediaFile::create([
    //			'file_name' => $file->hashName(),
    //			'file_path' => $path,
    //			'mime_type' => $file->getMimeType(),
    //			'file_size' => $file->getSize(),
    //			'uploaded_by' => auth()->id(),
    //		]);
    //		// 3. Tạo metadata (tách riêng đúng chuẩn)
    //		MediaMetadata::create([
    //			'media_id'      => $media->id,
    //			'file_name'     => $file->hashName(),
    //			'original_name' => $file->getClientOriginalName(),
    //			'mime_type'     => $file->getMimeType(),
    //			'file_size'     => $file->getSize(),
    //			'disk'          => 'public',
    //			'path'          => $path,
    //			'url'           => Storage::disk('public')->url($path),
    //			'metadata'      => [
    //				'width'  => null, // hoặc auto detect nếu cần
    //				'height' => null,
    //			],
    //		]);
    //		return $media;
    //	}
    //
    //	/**
    //	 * @param MediaFile $media
    //	 *
    //	 * @return bool
    //	 */
    //	public function delete(MediaFile $media): bool
    //	{
    //		// xóa file physical
    //		if ($media->file_path && Storage::disk('public')->exists($media->file_path)) {
    //			Storage::disk('public')->delete($media->file_path);
    //		}
    //
    //		// xóa DB (metadata & relations sẽ cascade)
    //		return $media->delete();
    //	}
}
