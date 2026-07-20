<?php

namespace App\Http\Controllers\System;

use App\Contracts\Services\MediaUploadServiceInterface;
use App\Http\Controllers\Controller;
use App\Models\Media\MediaFile;
use Illuminate\Http\Request;

/**
 * Quản lý file media: liệt kê, upload và xóa qua AJAX.
 */
class MediaController extends Controller
{
    protected MediaUploadServiceInterface $uploader;

    /**
     * Khởi tạo controller với service upload media và yêu cầu đăng nhập.
     */
    public function __construct(MediaUploadServiceInterface $uploader)
    {
        $this->middleware('auth');
        $this->uploader = $uploader;
    }

    // Danh sách media JSON
    /**
     * Trả danh sách media mới nhất dạng JSON (id, tên, URL, mime, dung lượng).
     */
    public function list()
    {
        $items = MediaFile::with('metadata')
            ->latest()
            ->get()
            ->map(fn ($m) => [
                'id' => $m->id,
                'file_name' => $m->file_name,
                'url' => optional($m->metadata)->url ?? asset('storage/'.$m->file_path),
                'mime_type' => $m->mime_type,
                'size' => $m->file_size,
            ]);

        return response()->json($items);
    }

    // Upload file (AJAX). trả về media object
    /**
     * Upload một file media (tối đa 50MB) và trả thông tin file dạng JSON.
     */
    public function upload(Request $request)
    {
        $request->validate(['file' => 'required|file|max:51200']); // max 50MB la example
        $file = $request->file('file');
        $media = $this->uploader->upload($file);

        return response()->json([
            'id' => $media->id,
            'url' => $media->url(),
            'file_name' => $media->file_name,
        ]);
    }

    // Xóa media
    /**
     * Xóa một file media qua service upload.
     */
    public function destroy(MediaFile $media)
    {
        $this->uploader->delete($media);

        return response()->json(['success' => true]);
    }
}
