<?php

namespace App\Http\Controllers\Technical;

use App\Http\Controllers\Controller;
use App\Http\Requests\Technical\SolarMaintenanceAttachmentRequest;
use App\Models\Site;
use App\Models\SolarMaintenanceAttachment;
use App\Models\SolarMaintenanceSchedule;
use App\Models\SolarSiteDocument;
use App\Support\EgoCompanyScope;
use App\Support\SolarMaintenanceAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Quản lý file đính kèm của lịch bảo trì điện mặt trời và hồ sơ công trình.
 */
class SolarMaintenanceAttachmentController extends Controller
{
    /**
     * Tải lên các file đính kèm cho một đợt bảo trì và ghi audit log.
     */
    public function storeSchedule(
        SolarMaintenanceAttachmentRequest $request,
        SolarMaintenanceSchedule $schedule
    ): RedirectResponse {
        $this->authorize('uploadAttachment', $schedule);

        foreach ($request->file('files', []) as $file) {
            $extension = strtolower((string) $file->getClientOriginalExtension());
            $fileName = Str::uuid()->toString() . ($extension ? '.' . $extension : '');
            $directory = 'solar-maintenance/private/' . ($schedule->company_id ?: 'unknown')
                . '/schedules/' . $schedule->id;
            $path = $file->storeAs($directory, $fileName, 'local');

            $schedule->attachments()->create([
                'site_id' => $schedule->site_id,
                'company_id' => $schedule->company_id,
                'category' => $request->string('category')->toString(),
                'disk' => 'local',
                'file_name' => $fileName,
                'original_name' => mb_substr($file->getClientOriginalName(), 0, 255),
                'file_path' => $path,
                'mime_type' => $file->getMimeType(),
                'file_size' => $file->getSize() ?: 0,
                'description' => $request->input('description'),
                'uploaded_by' => $request->user()->id,
                'is_customer_visible' => $request->boolean('is_customer_visible'),
            ]);
        }

        $schedule->auditLogs()->create([
            'action' => 'attachments_uploaded',
            'new_values' => ['count' => count($request->file('files', []))],
            'user_id' => $request->user()->id,
            'ip_address' => $request->ip(),
            'user_agent' => mb_substr((string) $request->userAgent(), 0, 1000),
            'created_at' => now(),
        ]);

        return back()->with('success', 'Đã tải hồ sơ của đợt bảo trì lên hệ thống.');
    }

    /**
     * Tải lên hồ sơ (tài liệu) gắn trực tiếp với công trình điện mặt trời.
     */
    public function storeSite(SolarMaintenanceAttachmentRequest $request, int $site): RedirectResponse
    {
        abort_unless(SolarMaintenanceAccess::isTechnician($request->user())
            || SolarMaintenanceAccess::isManager($request->user()), 403);

        $siteModel = Site::withoutGlobalScopes()->findOrFail($site);
        $this->assertSiteCompany($siteModel);

        foreach ($request->file('files', []) as $file) {
            $extension = strtolower((string) $file->getClientOriginalExtension());
            $fileName = Str::uuid()->toString() . ($extension ? '.' . $extension : '');
            $directory = 'solar-maintenance/private/' . ($siteModel->company_id ?: 'unknown')
                . '/sites/' . $siteModel->id;
            $path = $file->storeAs($directory, $fileName, 'local');

            SolarSiteDocument::create([
                'site_id' => $siteModel->id,
                'company_id' => $siteModel->company_id,
                'category' => $request->string('category')->toString(),
                'disk' => 'local',
                'file_name' => $fileName,
                'original_name' => mb_substr($file->getClientOriginalName(), 0, 255),
                'file_path' => $path,
                'mime_type' => $file->getMimeType(),
                'file_size' => $file->getSize() ?: 0,
                'description' => $request->input('description'),
                'uploaded_by' => $request->user()->id,
            ]);
        }

        return back()->with('success', 'Đã tải hồ sơ công trình lên hệ thống.');
    }

    /**
     * Xem trước (inline) file đính kèm của đợt bảo trì.
     */
    public function previewSchedule(Request $request, SolarMaintenanceAttachment $attachment): BinaryFileResponse|StreamedResponse
    {
        $attachment->load('schedule');
        $this->authorize('view', $attachment->schedule);
        return $this->serve($attachment->disk, $attachment->file_path, $attachment->original_name, true);
    }

    /**
     * Tải xuống file đính kèm của đợt bảo trì.
     */
    public function downloadSchedule(Request $request, SolarMaintenanceAttachment $attachment): StreamedResponse
    {
        $attachment->load('schedule');
        $this->authorize('view', $attachment->schedule);
        return Storage::disk($attachment->disk)->download($attachment->file_path, $attachment->original_name);
    }

    /**
     * Xóa file đính kèm của đợt bảo trì (lịch đã hoàn thành chỉ Trưởng phòng/Admin được xóa).
     */
    public function destroySchedule(Request $request, SolarMaintenanceAttachment $attachment): RedirectResponse
    {
        $attachment->load('schedule');
        abort_unless($request->user()->can('uploadAttachment', $attachment->schedule), 403);

        if ($attachment->schedule->status === 'completed' && !SolarMaintenanceAccess::isManager($request->user())) {
            abort(403, 'Chỉ Trưởng phòng kỹ thuật hoặc Admin được xóa file của lịch đã hoàn thành.');
        }

        Storage::disk($attachment->disk)->delete($attachment->file_path);
        $attachment->delete();

        return back()->with('success', 'Đã xóa file khỏi hồ sơ đợt bảo trì.');
    }

    /**
     * Xem trước (inline) hồ sơ công trình.
     */
    public function previewSite(Request $request, SolarSiteDocument $document): BinaryFileResponse|StreamedResponse
    {
        abort_unless(SolarMaintenanceAccess::canViewAny($request->user()), 403);
        $this->assertDocumentCompany($document);
        return $this->serve($document->disk, $document->file_path, $document->original_name, true);
    }

    /**
     * Tải xuống hồ sơ công trình.
     */
    public function downloadSite(Request $request, SolarSiteDocument $document): StreamedResponse
    {
        abort_unless(SolarMaintenanceAccess::canViewAny($request->user()), 403);
        $this->assertDocumentCompany($document);
        return Storage::disk($document->disk)->download($document->file_path, $document->original_name);
    }

    /**
     * Xóa hồ sơ công trình (chỉ quản lý hoặc chính người tải lên).
     */
    public function destroySite(Request $request, SolarSiteDocument $document): RedirectResponse
    {
        abort_unless(SolarMaintenanceAccess::isManager($request->user())
            || (int) $document->uploaded_by === (int) $request->user()->id, 403);
        $this->assertDocumentCompany($document);

        Storage::disk($document->disk)->delete($document->file_path);
        $document->delete();

        return back()->with('success', 'Đã xóa hồ sơ công trình.');
    }

    /**
     * Trả file về trình duyệt: inline với ảnh/PDF, ngược lại buộc tải xuống.
     */
    private function serve(string $disk, string $path, string $name, bool $inline): BinaryFileResponse|StreamedResponse
    {
        abort_unless(Storage::disk($disk)->exists($path), 404, 'File không còn tồn tại trên máy chủ.');

        $mime = Storage::disk($disk)->mimeType($path) ?: 'application/octet-stream';
        $canInline = $inline && (str_starts_with($mime, 'image/') || $mime === 'application/pdf');

        if (!$canInline) {
            return Storage::disk($disk)->download($path, $name);
        }

        return response()->file(Storage::disk($disk)->path($path), [
            'Content-Type' => $mime,
            'Content-Disposition' => 'inline; filename="' . addslashes($name) . '"',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /**
     * Chặn truy cập nếu công trình không thuộc công ty đang làm việc.
     */
    private function assertSiteCompany(Site $site): void
    {
        $user = request()->user();
        if (SolarMaintenanceAccess::isAdmin($user)) {
            return;
        }

        $companyId = EgoCompanyScope::currentId();
        $siteCompanyId = (int) ($site->company_id ?? 0);

        if ($companyId <= 0) {
            return;
        }

        if ($siteCompanyId <= 0 && SolarMaintenanceAccess::isManager($user)) {
            return;
        }

        abort_unless($siteCompanyId === $companyId, 403, 'Công trình không thuộc công ty đang làm việc.');
    }

    /**
     * Chặn truy cập nếu hồ sơ thuộc công ty khác với công ty đang làm việc.
     */
    private function assertDocumentCompany(SolarSiteDocument $document): void
    {
        $user = request()->user();
        if (SolarMaintenanceAccess::isAdmin($user)) {
            return;
        }

        $companyId = EgoCompanyScope::currentId();
        $documentCompanyId = (int) ($document->company_id ?? 0);

        if ($companyId <= 0) {
            return;
        }

        if ($documentCompanyId <= 0 && SolarMaintenanceAccess::isManager($user)) {
            return;
        }

        abort_unless($documentCompanyId === $companyId, 403, 'Không có quyền truy cập hồ sơ của công ty khác.');
    }
}
