<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

/**
 * Xem trước chứng từ phiếu đề nghị thanh toán: ảnh, PDF, TXT, CSV, Office (convert PDF qua LibreOffice).
 */
class EgoPaymentRequestAttachmentPreviewController extends Controller
{
    /**
     * Tìm chứng từ của phiếu và trả về trang xem trước theo định dạng file.
     */
    public function show($paymentRequest, $attachment)
    {
        ini_set('memory_limit', '1024M');
        set_time_limit(180);

        abort_unless(Schema::hasTable('payment_requests'), 404, 'Không thấy bảng payment_requests.');
        abort_unless(Schema::hasTable('payment_attachments'), 404, 'Không thấy bảng payment_attachments.');

        $paymentRequestId = (int) $paymentRequest;
        $attachmentId = (int) $attachment;

        $pr = DB::table('payment_requests')->where('id', $paymentRequestId)->first();
        abort_unless($pr, 404, 'Không tìm thấy phiếu đề nghị thanh toán.');

        $att = DB::table('payment_attachments')
            ->where('id', $attachmentId)
            ->where('payment_request_id', $paymentRequestId)
            ->first();

        abort_unless($att, 404, 'Không tìm thấy chứng từ đính kèm.');

        if (empty($att->path) || ! Storage::disk('public')->exists($att->path)) {
            return $this->page('Không tìm thấy file', $this->notice('Không tìm thấy file gốc trong storage.'));
        }

        $real = Storage::disk('public')->path($att->path);
        $name = $att->original_name ?: basename($att->path);
        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        $mime = $att->mime_type ?: (@mime_content_type($real) ?: '');

        $header = $this->header($name, $paymentRequestId, $attachmentId);

        if (str_starts_with((string) $mime, 'image/') || in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg'], true)) {
            $mime = $mime ?: 'image/'.($ext === 'jpg' ? 'jpeg' : $ext);
            $data = base64_encode(file_get_contents($real));

            return $this->page(
                $name,
                $header.'<div class="preview-body image-body"><img class="preview-image" src="data:'.e($mime).';base64,'.$data.'"></div>'
            );
        }

        if ($ext === 'pdf' || $mime === 'application/pdf') {
            $data = base64_encode(file_get_contents($real));

            return $this->page(
                $name,
                $header.'<iframe src="data:application/pdf;base64,'.$data.'#toolbar=1"></iframe>'
            );
        }

        if (in_array($ext, ['txt', 'log'], true)) {
            $text = htmlspecialchars((string) file_get_contents($real), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

            return $this->page(
                $name,
                $header.'<div class="preview-body"><pre class="text-preview">'.$text.'</pre></div>'
            );
        }

        if ($ext === 'csv') {
            return $this->page(
                $name,
                $header.'<div class="preview-body"><div class="table-wrap">'.$this->csvTable($real).'</div></div>'
            );
        }

        if (in_array($ext, ['doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx'], true)) {
            $pdf = $this->convertOfficeToPdf($real);

            if ($pdf && is_file($pdf)) {
                $data = base64_encode(file_get_contents($pdf));

                return $this->page(
                    $name,
                    $header.'<iframe src="data:application/pdf;base64,'.$data.'#toolbar=1"></iframe>'
                );
            }

            return $this->page(
                $name,
                $header.$this->notice('File Office cần LibreOffice trên server để xem trước. Anh vẫn có thể bấm Tải xuống để mở file.')
            );
        }

        return $this->page(
            $name,
            $header.$this->notice('Định dạng này chưa hỗ trợ xem trước: .'.e($ext))
        );
    }

    /**
     * Convert file Office sang PDF bằng LibreOffice headless, có cache theo hash file.
     */
    protected function convertOfficeToPdf(string $real): ?string
    {
        $bin = trim((string) shell_exec('command -v libreoffice 2>/dev/null || command -v soffice 2>/dev/null'));

        if ($bin === '') {
            return null;
        }

        $cacheDir = storage_path('app/preview-cache/payment-request-attachments');

        if (! is_dir($cacheDir)) {
            @mkdir($cacheDir, 0775, true);
        }

        $hash = md5($real.'|'.@filemtime($real).'|'.@filesize($real));
        $pdfPath = $cacheDir.'/'.$hash.'.pdf';

        if (is_file($pdfPath) && filesize($pdfPath) > 0) {
            return $pdfPath;
        }

        $workDir = $cacheDir.'/work-'.$hash;
        $profileDir = $cacheDir.'/lo-profile';

        @mkdir($workDir, 0775, true);
        @mkdir($profileDir, 0775, true);

        $ext = strtolower(pathinfo($real, PATHINFO_EXTENSION)) ?: 'bin';
        $tmpInput = $workDir.'/input.'.$ext;

        if (! @copy($real, $tmpInput)) {
            return null;
        }

        $profileUrl = 'file://'.str_replace('%2F', '/', rawurlencode($profileDir));

        $cmd = 'HOME='.escapeshellarg($profileDir).' '
            .escapeshellarg($bin)
            .' --headless --nologo --nofirststartwizard --nolockcheck --nodefault '
            .escapeshellarg('--env:UserInstallation='.$profileUrl)
            .' --convert-to pdf --outdir '
            .escapeshellarg($workDir).' '
            .escapeshellarg($tmpInput)
            .' 2>&1';

        @exec($cmd, $out, $code);

        $generated = glob($workDir.'/*.pdf') ?: [];

        if (! empty($generated[0]) && is_file($generated[0]) && filesize($generated[0]) > 0) {
            @rename($generated[0], $pdfPath);

            foreach (glob($workDir.'/*') ?: [] as $f) {
                if (is_file($f)) {
                    @unlink($f);
                }
            }

            @rmdir($workDir);

            return $pdfPath;
        }

        return null;
    }

    /**
     * Đọc file CSV và dựng bảng HTML (dòng đầu làm header).
     */
    protected function csvTable(string $real): string
    {
        $handle = fopen($real, 'r');

        if (! $handle) {
            return '<div class="notice">Không đọc được CSV.</div>';
        }

        $html = '<table class="preview-table">';
        $rowIndex = 0;

        while (($row = fgetcsv($handle)) !== false) {
            $html .= '<tr>';

            foreach ($row as $cell) {
                $tag = $rowIndex === 0 ? 'th' : 'td';
                $html .= '<'.$tag.'>'.htmlspecialchars((string) $cell, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8').'</'.$tag.'>';
            }

            $html .= '</tr>';
            $rowIndex++;
        }

        fclose($handle);

        return $html.'</table>';
    }

    /**
     * Thanh tiêu đề trang xem trước kèm nút tải xuống chứng từ.
     */
    protected function header(string $name, int $paymentRequestId, int $attachmentId): string
    {
        $download = url('/payment-requests/'.$paymentRequestId.'/attachments-thao/'.$attachmentId.'/download');

        return '<div class="preview-top">
            <div class="preview-title">'.e($name).'</div>
            <a class="preview-download" href="'.e($download).'">Tải xuống</a>
        </div>';
    }

    /**
     * Dựng khối thông báo (lỗi/không hỗ trợ) trong trang xem trước.
     */
    protected function notice(string $message): string
    {
        return '<div class="preview-body"><div class="notice">'.e($message).'</div></div>';
    }

    /**
     * Bọc nội dung xem trước vào trang HTML hoàn chỉnh kèm CSS chung.
     */
    protected function page(string $title, string $body)
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
            .preview-image{display:block;width:auto;height:auto;max-width:min(100%,860px);max-height:calc(100vh - 90px);object-fit:contain;border-radius:12px;background:#fff;box-shadow:0 14px 38px rgba(15,23,42,.16)}
            .notice{max-width:720px;margin:70px auto;padding:22px;border:1px solid #dbe3ef;border-radius:18px;background:#fff;color:#475569;line-height:1.6;text-align:center;box-shadow:0 18px 45px rgba(15,23,42,.08)}
            .text-preview{white-space:pre-wrap;background:#fff;border:1px solid #dbe3ef;border-radius:14px;padding:16px;line-height:1.6}
            .table-wrap{background:#fff;border:1px solid #dbe3ef;border-radius:14px;overflow:auto;box-shadow:0 12px 30px rgba(15,23,42,.06)}
            .preview-table{border-collapse:collapse;width:100%}
            .preview-table td,.preview-table th{border:1px solid #d7dee8;padding:7px 9px;font-size:13px;vertical-align:top}
        </style>';

        return response('<!doctype html><html><head><meta charset="utf-8"><title>'.e($title).'</title>'.$css.'</head><body>'.$body.'</body></html>');
    }
}
