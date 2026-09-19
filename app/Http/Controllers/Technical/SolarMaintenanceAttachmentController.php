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
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Reader\IReadFilter;
use PhpOffice\PhpSpreadsheet\Writer\Html as SpreadsheetHtmlWriter;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

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

        if (in_array($schedule->status, ['pending_approval', 'approved', 'completed'], true)) {
            return back()->with('error', 'Hồ sơ đang chờ duyệt hoặc đã duyệt nên không thể thêm minh chứng. Hãy yêu cầu bổ sung hoặc mở lại công việc trước.');
        }

        foreach ($request->file('files', []) as $file) {
            $extension = strtolower((string) $file->getClientOriginalExtension());
            $fileName = Str::uuid()->toString().($extension ? '.'.$extension : '');
            $directory = 'solar-maintenance/private/'.($schedule->company_id ?: 'unknown')
                .'/schedules/'.$schedule->id;
            $path = $file->storeAs($directory, $fileName, 'local');

            if (! $path) {
                return back()->with(
                    'error',
                    'Máy chủ không ghi được file vào storage. Vui lòng kiểm tra quyền storage/app/private rồi thử lại.'
                );
            }

            $schedule->attachments()->create([
                'checklist_item_id' => $request->integer('checklist_item_id') ?: null,
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
            $fileName = Str::uuid()->toString().($extension ? '.'.$extension : '');
            $directory = 'solar-maintenance/private/'.($siteModel->company_id ?: 'unknown')
                .'/sites/'.$siteModel->id;
            $path = $file->storeAs($directory, $fileName, 'local');

            if (! $path) {
                return back()->with(
                    'error',
                    'Máy chủ không ghi được file vào storage. Vui lòng kiểm tra quyền storage/app/private rồi thử lại.'
                );
            }

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
    public function previewSchedule(Request $request, SolarMaintenanceAttachment $attachment): BinaryFileResponse|StreamedResponse|Response
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

        if (in_array($attachment->schedule->status, ['pending_approval', 'approved', 'completed'], true)) {
            return back()->with('error', 'Không thể xóa minh chứng của hồ sơ đang chờ duyệt hoặc đã duyệt. Hãy yêu cầu bổ sung hoặc mở lại công việc trước.');
        }

        Storage::disk($attachment->disk)->delete($attachment->file_path);
        $attachment->delete();

        return back()->with('success', 'Đã xóa file khỏi hồ sơ đợt bảo trì.');
    }

    /**
     * Xem trước (inline) hồ sơ công trình.
     */
    public function previewSite(Request $request, SolarSiteDocument $document): BinaryFileResponse|StreamedResponse|Response
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
    private function serve(string $disk, string $path, string $name, bool $inline): BinaryFileResponse|StreamedResponse|Response
    {
        abort_unless(Storage::disk($disk)->exists($path), 404, 'File không còn tồn tại trên máy chủ.');

        $mime = Storage::disk($disk)->mimeType($path) ?: 'application/octet-stream';
        $extension = strtolower((string) pathinfo($name, PATHINFO_EXTENSION));

        if ($inline && in_array($extension, ['xls', 'xlsx'], true)) {
            return $this->serveSpreadsheetPreview(Storage::disk($disk)->path($path), $name);
        }

        $canInline = $inline && (str_starts_with($mime, 'image/')
            || str_starts_with($mime, 'video/')
            || $mime === 'application/pdf');

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
     * V14.6: dựng bản xem nhanh an toàn cho Excel, giới hạn 200 dòng và 30 cột đầu.
     */
    private function serveSpreadsheetPreview(string $absolutePath, string $name): Response
    {
        if (! class_exists(IOFactory::class) || filesize($absolutePath) > 20 * 1024 * 1024) {
            return $this->spreadsheetPreviewMessage($name, 'File quá lớn hoặc máy chủ chưa hỗ trợ dựng bảng xem nhanh.');
        }

        try {
            $reader = IOFactory::createReaderForFile($absolutePath);
            $reader->setReadDataOnly(true);
            $reader->setReadFilter(new class implements IReadFilter
            {
                public function readCell(string $columnAddress, int $row, string $worksheetName = ''): bool
                {
                    if ($row < 1 || $row > 200) {
                        return false;
                    }

                    $column = 0;
                    foreach (str_split(strtoupper($columnAddress)) as $letter) {
                        $column = ($column * 26) + (ord($letter) - 64);
                    }

                    return $column >= 1 && $column <= 30;
                }
            });

            $sheetNames = $reader->listWorksheetNames($absolutePath);
            if ($sheetNames !== []) {
                // Nạp tối đa 12 sheet để công thức ở sheet đầu có thể tham chiếu dữ liệu liên quan.
                $reader->setLoadSheetsOnly(array_slice($sheetNames, 0, 12));
            }

            $spreadsheet = $reader->load($absolutePath);
            $writer = new SpreadsheetHtmlWriter($spreadsheet);
            $writer->setSheetIndex(0);
            $writer->setPreCalculateFormulas(true);

            ob_start();
            $writer->save('php://output');
            $html = (string) ob_get_clean();
            $spreadsheet->disconnectWorksheets();

            $tableStyles = '<style>html,body{margin:0;background:#fff;font-family:Arial,sans-serif;color:#172b3f}body{padding:12px;overflow:auto}table{border-collapse:collapse!important;table-layout:auto!important}td,th{border:1px solid #d8e1e9!important;padding:6px 8px!important;min-width:70px;max-width:420px;white-space:normal!important;vertical-align:top}tr:first-child td,tr:first-child th{background:#edf5fb;font-weight:700}</style>';
            $html = str_contains($html, '</head>')
                ? str_replace('</head>', $tableStyles.'</head>', $html)
                : $tableStyles.$html;

            return response($html)
                ->header('Content-Type', 'text/html; charset=UTF-8')
                ->header('Content-Disposition', 'inline; filename="'.addslashes($name).'.html"')
                ->header('Content-Security-Policy', "default-src 'none'; style-src 'unsafe-inline'; img-src data:")
                ->header('X-Content-Type-Options', 'nosniff');
        } catch (Throwable $exception) {
            report($exception);

            return $this->spreadsheetPreviewMessage($name, 'Không thể dựng nội dung Excel. Bạn vẫn có thể tải file gốc xuống.');
        }
    }

    private function spreadsheetPreviewMessage(string $name, string $message): Response
    {
        $safeName = e($name);
        $safeMessage = e($message);
        $html = '<!doctype html><html lang="vi"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
            .'<style>body{font-family:Arial,sans-serif;margin:0;background:#f4f7fa;color:#173047}.box{max-width:680px;margin:12vh auto;background:#fff;border:1px solid #dce5ed;border-radius:14px;padding:28px;box-shadow:0 8px 28px #1d3c5614}h2{font-size:18px;margin:0 0 8px}p{font-size:14px;color:#64798d;margin:0}</style>'
            .'</head><body><div class="box"><h2>'.$safeName.'</h2><p>'.$safeMessage.'</p></div></body></html>';

        return response($html)
            ->header('Content-Type', 'text/html; charset=UTF-8')
            ->header('Content-Security-Policy', "default-src 'none'; style-src 'unsafe-inline'")
            ->header('X-Content-Type-Options', 'nosniff');
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
