<?php

namespace App\Http\Controllers\Synced\Technical;

use App\Http\Controllers\Controller;
use App\Http\Requests\Synced\Technical\SolarMaintenanceAttachmentRequest;
use App\Models\Site;
use App\Models\SolarMaintenanceAttachment;
use App\Models\SolarMaintenanceSchedule;
use App\Models\SolarSiteDocument;
use App\Support\Synced\EgoCompanyScope;
use App\Support\SolarMaintenanceAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
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

        $workItemId = $request->filled('work_item_id') ? (int) $request->input('work_item_id') : null;
        $checklistItemId = $request->filled('checklist_item_id') ? (int) $request->input('checklist_item_id') : null;
        if ($workItemId && $checklistItemId) {
            throw ValidationException::withMessages([
                'files' => 'Chỉ được gắn minh chứng vào một hạng mục.',
            ]);
        }
        if ($checklistItemId && ! $schedule->checklistItems()->whereKey($checklistItemId)->exists()) {
            throw ValidationException::withMessages([
                'checklist_item_id' => 'Hạng mục checklist không thuộc đợt bảo trì này.',
            ]);
        }
        if ($workItemId && ! $schedule->workItems()->whereKey($workItemId)->exists()) {
            throw ValidationException::withMessages([
                'work_item_id' => 'Công việc được chọn không thuộc đợt bảo trì này.',
            ]);
        }

        if ($workItemId) {
            $workItem = $schedule->workItems()->withCount('attachments')->findOrFail($workItemId);
            $description = (string) $workItem->description;
            $configuration = str_starts_with($description, '__ego_checklist__')
                ? json_decode(substr($description, 17), true)
                : [];
            $maximum = (int) ($configuration['max'] ?? 0);
            $incomingFiles = $request->file('files', []);
            if ($maximum > 0 && $workItem->attachments_count + count($incomingFiles) > $maximum) {
                throw ValidationException::withMessages([
                    'files' => 'Hạng mục này chỉ cho phép tối đa '.$maximum.' tệp.',
                ]);
            }

            $allowedExtensions = array_values(array_filter(array_map('trim', explode(',', strtolower((string) ($configuration['extensions'] ?? ''))))));
            if ($allowedExtensions !== []) {
                foreach ($incomingFiles as $incomingFile) {
                    if (! in_array(strtolower((string) $incomingFile->getClientOriginalExtension()), $allowedExtensions, true)) {
                        throw ValidationException::withMessages([
                            'files' => 'Chỉ được tải các định dạng: '.implode(', ', $allowedExtensions).'.',
                        ]);
                    }
                }
            }
        }

        foreach ($request->file('files', []) as $file) {
            $extension = strtolower((string) $file->getClientOriginalExtension());
            $fileName = Str::uuid()->toString().($extension ? '.'.$extension : '');
            $directory = 'solar-maintenance/private/'.($schedule->company_id ?: 'unknown')
                .'/schedules/'.$schedule->id;
            $path = $file->storeAs($directory, $fileName, 'local');

            $schedule->attachments()->create([
                'maintenance_work_item_id' => $workItemId,
                'checklist_item_id' => $checklistItemId,
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
            'new_values' => ['count' => count($request->file('files', [])), 'work_item_id' => $workItemId, 'checklist_item_id' => $checklistItemId],
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

        $category = $request->string('category')->toString();
        if ($this->isFinancialDocumentCategory($category)) {
            abort_unless($this->canAccessFinancialDocuments($request->user()), 403, 'Bạn không có quyền tải hồ sơ tài chính.');
        }

        foreach ($request->file('files', []) as $file) {
            $extension = strtolower((string) $file->getClientOriginalExtension());
            $fileName = Str::uuid()->toString().($extension ? '.'.$extension : '');
            $directory = 'solar-maintenance/private/'.($siteModel->company_id ?: 'unknown')
                .'/sites/'.$siteModel->id;
            $path = $file->storeAs($directory, $fileName, 'local');

            SolarSiteDocument::create([
                'site_id' => $siteModel->id,
                'company_id' => $siteModel->company_id,
                'category' => $category,
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

        if ($attachment->schedule->status === 'completed' && ! SolarMaintenanceAccess::isManager($request->user())) {
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
        $this->assertFinancialDocumentAccess($request, $document);

        return $this->serve($document->disk, $document->file_path, $document->original_name, true);
    }

    /**
     * Tải xuống hồ sơ công trình.
     */
    public function downloadSite(Request $request, SolarSiteDocument $document): StreamedResponse
    {
        abort_unless(SolarMaintenanceAccess::canViewAny($request->user()), 403);
        $this->assertDocumentCompany($document);
        $this->assertFinancialDocumentAccess($request, $document);

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
        $this->assertFinancialDocumentAccess($request, $document);

        Storage::disk($document->disk)->delete($document->file_path);
        $document->delete();

        return back()->with('success', 'Đã xóa hồ sơ công trình.');
    }

    private function assertFinancialDocumentAccess(Request $request, SolarSiteDocument $document): void
    {
        if (! $this->isFinancialDocumentCategory((string) ($document->category ?? ''))) {
            return;
        }

        abort_unless(
            $this->canAccessFinancialDocuments($request->user()),
            403,
            'Bạn không có quyền truy cập hồ sơ tài chính.'
        );
    }

    private function isFinancialDocumentCategory(string $category): bool
    {
        return in_array(strtolower(trim($category)), ['invoice', 'payment', 'financial', 'accounting'], true);
    }

    private function canAccessFinancialDocuments($user): bool
    {
        if (! $user) {
            return false;
        }

        if ((int) ($user->is_admin ?? 0) === 1 || SolarMaintenanceAccess::isAdmin($user)) {
            return true;
        }

        $roles = collect();

        if (method_exists($user, 'getRoleNames')) {
            try {
                $roles = $roles->merge($user->getRoleNames());
            } catch (\Throwable $exception) {
                // Tiếp tục đọc các trường role cũ.
            }
        }

        foreach (['role', 'role_name', 'user_role', 'type'] as $field) {
            if (! empty($user->{$field}) && is_scalar($user->{$field})) {
                $roles->push((string) $user->{$field});
            }
        }

        $allowedRoles = [
            'admin', 'super_admin',
            'management', 'director', 'general_director', 'ban_giam_doc', 'giam_doc',
            'accounting', 'ketoan', 'ke_toan', 'chief_accountant', 'ke_toan_truong',
            'accounting_manager', 'finance', 'finance_manager',
        ];

        if ($roles
            ->map(fn ($role) => strtolower(trim((string) $role)))
            ->intersect($allowedRoles)
            ->isNotEmpty()) {
            return true;
        }

        if (method_exists($user, 'can')) {
            foreach (['page.finance', 'finance.view', 'projects.finance.view'] as $permission) {
                try {
                    if ($user->can($permission)) {
                        return true;
                    }
                } catch (\Throwable $exception) {
                    // Permission chưa tồn tại thì bỏ qua.
                }
            }
        }

        return false;
    }

    /**
     * Trả file về trình duyệt: inline với ảnh/PDF, ngược lại buộc tải xuống.
     */
    private function serve(string $disk, string $path, string $name, bool $inline): BinaryFileResponse|StreamedResponse
    {
        abort_unless(Storage::disk($disk)->exists($path), 404, 'File không còn tồn tại trên máy chủ.');

        $mime = Storage::disk($disk)->mimeType($path) ?: 'application/octet-stream';
        $canInline = $inline && (str_starts_with($mime, 'image/') || $mime === 'application/pdf');

        if (! $canInline) {
            return Storage::disk($disk)->download($path, $name);
        }

        return response()->file(Storage::disk($disk)->path($path), [
            'Content-Type' => $mime,
            'Content-Disposition' => 'inline; filename="'.addslashes($name).'"',
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
