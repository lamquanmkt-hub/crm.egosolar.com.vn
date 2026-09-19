<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class EgoOrderDocumentController extends Controller
{
    private array $types = [
        'payment_request' => 'ĐNTT',
        'purchase_contract' => 'Hợp đồng mua bán',
        'agency_contract' => 'Hợp đồng đại lý',
        'deposit' => 'Phiếu đặt cọc',
        'quotation' => 'Báo giá',
        'invoice' => 'Hóa đơn / UNC',
        'delivery' => 'Vận chuyển / Biên bản giao nhận',
        'warranty' => 'Bảo hành',
        'other' => 'Khác',
    ];

    private array $allowedExt = [
        'pdf', 'doc', 'docx', 'xls', 'xlsx', 'csv',
        'jpg', 'jpeg', 'png', 'webp', 'gif', 'txt',
        'zip', 'rar', '7z'
    ];

    public function store(Request $request, $order)
    {
        $orderId = (int) $order;
        $this->getOrder($orderId);

        $request->validate([
            'document_type' => ['nullable', 'string', 'max:80'],
            'title' => ['nullable', 'string', 'max:255'],
            'note' => ['nullable', 'string', 'max:2000'],
            'documents' => ['required'],
            'documents.*' => ['file', 'max:51200'],
        ], [
            'documents.required' => 'Vui lòng chọn file cần upload.',
            'documents.*.max' => 'Mỗi file tối đa 50MB.',
        ]);

        $files = $request->file('documents', []);

        if (!is_array($files)) {
            $files = [$files];
        }

        [$customerId, $profileId] = $this->resolveCustomerProfile($orderId);

        $count = 0;

        foreach ($files as $file) {
            if (!$file || !$file->isValid()) {
                continue;
            }

            $original = $file->getClientOriginalName();
            $ext = strtolower((string) $file->getClientOriginalExtension());

            if (!in_array($ext, $this->allowedExt, true)) {
                return back()->withErrors(['documents' => 'Định dạng .' . $ext . ' chưa được hỗ trợ.']);
            }

            $base = pathinfo($original, PATHINFO_FILENAME);
            $safeBase = Str::slug(Str::ascii($base));

            if ($safeBase === '') {
                $safeBase = 'file';
            }

            $storedName = now()->format('Ymd_His') . '_' . Str::random(8) . '_' . $safeBase . '.' . $ext;
            $storedPath = $file->storeAs('order-documents/' . $orderId, $storedName, 'public');

            DB::table('crm_order_documents')->insert([
                'order_id' => $orderId,
                'customer_profile_id' => $profileId,
                'customer_id' => $customerId,
                'document_type' => $request->input('document_type') ?: 'other',
                'title' => $request->input('title') ?: ($this->types[$request->input('document_type')] ?? 'Giấy tờ đơn hàng'),
                'original_name' => $original,
                'file_path' => $storedPath,
                'mime_type' => $file->getClientMimeType(),
                'size_bytes' => $file->getSize() ?: 0,
                'note' => $request->input('note'),
                'uploaded_by' => auth()->id(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $count++;
        }

        return back()->with('success', 'Đã upload ' . $count . ' file vào đơn hàng. Hồ sơ đại lý liên quan sẽ tự hiện file này.');
    }

    public function download($order, $document)
    {
        $doc = $this->getDocument((int) $order, (int) $document);

        abort_unless(Storage::disk('public')->exists($doc->file_path), 404, 'Không tìm thấy file.');

        return Storage::disk('public')->download($doc->file_path, $doc->original_name ?: basename($doc->file_path));
    }

    public function preview($order, $document)
    {
        ini_set('memory_limit', '1024M');
        set_time_limit(180);

        $orderId = (int) $order;
        $documentId = (int) $document;
        $doc = $this->getDocument($orderId, $documentId);

        if (empty($doc->file_path) || !Storage::disk('public')->exists($doc->file_path)) {
            return $this->page('Không tìm thấy file', $this->notice('Không tìm thấy file gốc trong storage.'));
        }

        $real = Storage::disk('public')->path($doc->file_path);
        $name = $doc->original_name ?: basename($doc->file_path);
        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        $mime = $doc->mime_type ?: (@mime_content_type($real) ?: '');
        $header = $this->previewHeader($name, $orderId, $documentId);

        if (str_starts_with((string) $mime, 'image/') || in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg'], true)) {
            $mime = $mime ?: 'image/' . ($ext === 'jpg' ? 'jpeg' : $ext);
            $data = base64_encode(file_get_contents($real));

            return $this->page($name, $header . '<div class="preview-body image-body"><img class="preview-image" src="data:' . e($mime) . ';base64,' . $data . '"></div>');
        }

        if ($ext === 'pdf' || $mime === 'application/pdf') {
            $data = base64_encode(file_get_contents($real));

            return $this->page($name, $header . '<iframe src="data:application/pdf;base64,' . $data . '#toolbar=1"></iframe>');
        }

        if (in_array($ext, ['txt', 'log'], true)) {
            $text = htmlspecialchars((string) file_get_contents($real), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

            return $this->page($name, $header . '<div class="preview-body"><pre class="text-preview">' . $text . '</pre></div>');
        }

        if ($ext === 'csv') {
            return $this->page($name, $header . '<div class="preview-body"><div class="table-wrap">' . $this->csvTable($real) . '</div></div>');
        }

        if (in_array($ext, ['doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx'], true)) {
            $pdf = $this->convertOfficeToPdf($real);

            if ($pdf && is_file($pdf)) {
                $data = base64_encode(file_get_contents($pdf));

                return $this->page($name, $header . '<iframe src="data:application/pdf;base64,' . $data . '#toolbar=1"></iframe>');
            }

            return $this->page($name, $header . $this->notice('File Word/Excel cần LibreOffice trên server để xem trước. Anh vẫn có thể bấm Tải xuống để mở file.'));
        }

        return $this->page($name, $header . $this->notice('Định dạng này chưa hỗ trợ xem trước: .' . e($ext)));
    }

    public function destroy($order, $document)
    {
        $doc = $this->getDocument((int) $order, (int) $document);

        if (!empty($doc->file_path) && Storage::disk('public')->exists($doc->file_path)) {
            Storage::disk('public')->delete($doc->file_path);
        }

        DB::table('crm_order_documents')->where('id', (int) $document)->delete();

        return back()->with('success', 'Đã xóa giấy tờ khỏi đơn hàng.');
    }

    private function getOrder(int $orderId)
    {
        abort_unless(Schema::hasTable('crm_orders'), 404, 'Không thấy bảng crm_orders.');
        abort_unless(Schema::hasTable('crm_order_documents'), 500, 'Chưa có bảng crm_order_documents. Vui lòng chạy migrate.');

        $order = DB::table('crm_orders')->where('id', $orderId)->first();

        abort_unless($order, 404, 'Không tìm thấy đơn hàng.');

        return $order;
    }

    private function getDocument(int $orderId, int $documentId)
    {
        $this->getOrder($orderId);

        $doc = DB::table('crm_order_documents')
            ->where('id', $documentId)
            ->where('order_id', $orderId)
            ->first();

        abort_unless($doc, 404, 'Không tìm thấy giấy tờ đơn hàng.');

        return $doc;
    }

    private function resolveCustomerProfile(int $orderId): array
    {
        $order = DB::table('crm_orders')->where('id', $orderId)->first();

        $customerId = null;

        if ($order && Schema::hasColumn('crm_orders', 'customer_id') && !empty($order->customer_id)) {
            $customerId = (int) $order->customer_id;
        }

        if (!$customerId && $order && !empty($order->lead_id) && Schema::hasTable('crm_leads') && Schema::hasColumn('crm_leads', 'customer_id')) {
            $customerId = DB::table('crm_leads')->where('id', (int) $order->lead_id)->value('customer_id');
            $customerId = $customerId ? (int) $customerId : null;
        }

        $profileId = null;

        if (Schema::hasTable('customer_profiles')) {
            if ($customerId && Schema::hasColumn('customer_profiles', 'customer_id')) {
                $profileId = DB::table('customer_profiles')->where('customer_id', $customerId)->value('id');
                $profileId = $profileId ? (int) $profileId : null;
            }

            if (!$profileId && $customerId && Schema::hasTable('crm_customers')) {
                $customer = DB::table('crm_customers')->where('id', $customerId)->first();

                if ($customer && !empty($customer->phone) && Schema::hasColumn('customer_profiles', 'phone')) {
                    $profileId = DB::table('customer_profiles')->where('phone', $customer->phone)->value('id');
                    $profileId = $profileId ? (int) $profileId : null;
                }

                if (!$profileId && $customer && !empty($customer->name) && Schema::hasColumn('customer_profiles', 'agent_name')) {
                    $profileId = DB::table('customer_profiles')->where('agent_name', $customer->name)->value('id');
                    $profileId = $profileId ? (int) $profileId : null;
                }
            }
        }

        return [$customerId, $profileId];
    }

    private function convertOfficeToPdf(string $real): ?string
    {
        if (!function_exists('shell_exec') || !function_exists('exec')) {
            return null;
        }

        $bin = trim((string) shell_exec('command -v libreoffice 2>/dev/null || command -v soffice 2>/dev/null'));

        if ($bin === '') {
            return null;
        }

        $cacheDir = storage_path('app/preview-cache/order-documents');

        if (!is_dir($cacheDir)) {
            @mkdir($cacheDir, 0775, true);
        }

        $hash = md5($real . '|' . @filemtime($real) . '|' . @filesize($real));
        $pdfPath = $cacheDir . '/' . $hash . '.pdf';

        if (is_file($pdfPath) && filesize($pdfPath) > 0) {
            return $pdfPath;
        }

        $workDir = $cacheDir . '/work-' . $hash;
        $profileDir = $cacheDir . '/lo-profile';

        @mkdir($workDir, 0775, true);
        @mkdir($profileDir, 0775, true);

        $ext = strtolower(pathinfo($real, PATHINFO_EXTENSION)) ?: 'bin';
        $tmpInput = $workDir . '/input.' . $ext;

        if (!@copy($real, $tmpInput)) {
            return null;
        }

        $profileUrl = 'file://' . str_replace('%2F', '/', rawurlencode($profileDir));

        $cmd = 'HOME=' . escapeshellarg($profileDir) . ' '
            . escapeshellarg($bin)
            . ' --headless --nologo --nofirststartwizard --nolockcheck --nodefault '
            . escapeshellarg('--env:UserInstallation=' . $profileUrl)
            . ' --convert-to pdf --outdir '
            . escapeshellarg($workDir) . ' '
            . escapeshellarg($tmpInput)
            . ' 2>&1';

        @exec($cmd, $out, $code);

        $generated = glob($workDir . '/*.pdf') ?: [];

        if (!empty($generated[0]) && is_file($generated[0]) && filesize($generated[0]) > 0) {
            @rename($generated[0], $pdfPath);

            foreach (glob($workDir . '/*') ?: [] as $file) {
                if (is_file($file)) {
                    @unlink($file);
                }
            }

            @rmdir($workDir);

            return $pdfPath;
        }

        return null;
    }

    private function csvTable(string $real): string
    {
        $handle = fopen($real, 'r');

        if (!$handle) {
            return '<div class="notice">Không đọc được CSV.</div>';
        }

        $html = '<table class="preview-table">';
        $rowIndex = 0;

        while (($row = fgetcsv($handle)) !== false) {
            $html .= '<tr>';

            foreach ($row as $cell) {
                $tag = $rowIndex === 0 ? 'th' : 'td';
                $html .= '<' . $tag . '>' . htmlspecialchars((string) $cell, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</' . $tag . '>';
            }

            $html .= '</tr>';
            $rowIndex++;
        }

        fclose($handle);

        return $html . '</table>';
    }

    private function previewHeader(string $name, int $orderId, int $documentId): string
    {
        $download = url('/orders/' . $orderId . '/documents-ego/' . $documentId . '/download');

        return '<div class="preview-top"><div class="preview-title">' . e($name) . '</div><a class="preview-download" href="' . e($download) . '">Tải xuống</a></div>';
    }

    private function notice(string $message): string
    {
        return '<div class="preview-body"><div class="notice">' . e($message) . '</div></div>';
    }

    private function page(string $title, string $body)
    {
        $css = '<style>
            *{box-sizing:border-box}
            html,body{margin:0;min-height:100%;font-family:Arial,"DejaVu Sans",sans-serif;background:#f8fafc;color:#0f172a}
            iframe{width:100%;height:calc(100vh - 48px);border:0;background:#fff}
            .preview-top{height:48px;display:flex;align-items:center;justify-content:space-between;gap:12px;padding:8px 12px;background:linear-gradient(90deg,#0f3b78,#0891b2);color:#fff}
            .preview-title{font-size:14px;font-weight:900;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
            .preview-download{display:inline-flex;align-items:center;justify-content:center;border:1px solid rgba(255,255,255,.38);border-radius:10px;padding:8px 12px;color:#fff;text-decoration:none;font-size:13px;font-weight:900;background:rgba(255,255,255,.14)}
            .preview-body{padding:16px;overflow:auto}
            .image-body{display:flex;align-items:center;justify-content:center;min-height:calc(100vh - 48px);padding:14px;background:#f8fafc;overflow:auto}
            .preview-image{display:block;width:auto;height:auto;max-width:min(100%,920px);max-height:calc(100vh - 90px);object-fit:contain;border-radius:12px;background:#fff;box-shadow:0 14px 38px rgba(15,23,42,.16)}
            .notice{max-width:760px;margin:70px auto;padding:22px;border:1px solid #dbe3ef;border-radius:18px;background:#fff;color:#475569;line-height:1.6;text-align:center;box-shadow:0 18px 45px rgba(15,23,42,.08)}
            .text-preview{white-space:pre-wrap;background:#fff;border:1px solid #dbe3ef;border-radius:14px;padding:16px;line-height:1.6}
            .table-wrap{background:#fff;border:1px solid #dbe3ef;border-radius:14px;overflow:auto;box-shadow:0 12px 30px rgba(15,23,42,.06)}
            .preview-table{border-collapse:collapse;width:100%}
            .preview-table td,.preview-table th{border:1px solid #d7dee8;padding:7px 9px;font-size:13px;vertical-align:top}
        </style>';

        return response('<!doctype html><html><head><meta charset="utf-8"><title>' . e($title) . '</title>' . $css . '</head><body>' . $body . '</body></html>');
    }
}
