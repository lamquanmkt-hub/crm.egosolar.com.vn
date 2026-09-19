<?php
namespace App\Http\Controllers;
use App\Models\Media\MediaFile;
use App\Services\MediaUploadService;
use Illuminate\Http\Request;
class MediaController extends Controller
{
	protected MediaUploadService $uploader;
	public function __construct(MediaUploadService $uploader)
	{
		$this->middleware('auth');
		$this->uploader = $uploader;
	}
	// Danh sách media JSON
	public function list()
	{
		$items = MediaFile::with('metadata')
		                  ->latest()
		                  ->get()
		                  ->map(fn($m) => [
			                  'id' => $m->id,
			                  'file_name' => $m->file_name,
			                  'url' => optional($m->metadata)->url ?? asset('storage/' . $m->file_path),
			                  'mime_type' => $m->mime_type,
			                  'size' => $m->file_size,
		                  ]);
		return response()->json($items);
	}
	// Upload file (AJAX). trả về media object
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
	public function destroy(MediaFile $media)
	{
		$this->uploader->delete($media);
		return response()->json(['success' => true]);
	}
}
