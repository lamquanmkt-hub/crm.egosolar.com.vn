<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

class EgoCustomerProfileDocumentPreviewController extends Controller
{
    public function __invoke($customerProfile, $document)
    {
        ini_set('memory_limit', '1024M');
        set_time_limit(180);

        $profileId = (int) $customerProfile;
        $documentId = (int) $document;

        abort_unless(Schema::hasTable('customer_profiles'), 404);
        abort_unless(Schema::hasTable('customer_profile_documents'), 404);

        $profile = DB::table('customer_profiles')->where('id', $profileId)->first();
        abort_unless($profile, 404);

        $doc = DB::table('customer_profile_documents')
            ->where('id', $documentId)
            ->where('customer_profile_id', $profileId)
            ->first();

        abort_unless($doc, 404);

        $path = ltrim((string) $doc->file_path, '/');

        $real = null;

        if ($path && Storage::disk('public')->exists($path)) {
            $real = Storage::disk('public')->path($path);
        }

        if (!$real || !is_file($real)) {
            $candidates = [
                storage_path('app/public/' . $path),
                storage_path('app/' . $path),
                public_path('storage/' . $path),
                public_path($path),
            ];

            foreach ($candidates as $candidate) {
                if (is_file($candidate)) {
                    $real = $candidate;
                    break;
                }
            }
        }

        if (!$real || !is_file($real)) {
            return $this->page('Không tìm thấy file', $this->notice('Không tìm thấy file gốc trong storage.'));
        }

        $name = $doc->original_name ?: basename($real);
        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        $mime = $doc->mime_type ?: (@mime_content_type($real) ?: 'application/octet-stream');

        if ($ext === 'pdf' || $mime === 'application/pdf') {
            return response()->file($real, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'inline; filename="' . addslashes($name) . '"',
            ]);
        }

        if (str_starts_with((string) $mime, 'image/') || in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg'], true)) {
            return response()->file($real, [
                'Content-Type' => $mime,
                'Content-Disposition' => 'inline; filename="' . addslashes($name) . '"',
            ]);
        }

        if (in_array($ext, ['txt', 'log'], true)) {
            $text = htmlspecialchars((string) file_get_contents($real), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

            return $this->page($name, '<div class="ego-preview-top"><b>' . e($name) . '</b></div><pre class="ego-text-preview">' . $text . '</pre>');
        }

        if ($ext === 'csv') {
            return $this->page($name, '<div class="ego-preview-top"><b>' . e($name) . '</b></div><div class="ego-table-wrap">' . $this->csvTable($real) . '</div>');
        }

        if (in_array($ext, ['doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx'], true)) {
            $pdf = $this->convertOfficeToPdf($real);

            if ($pdf && is_file($pdf)) {
                return response()->file($pdf, [
                    'Content-Type' => 'application/pdf',
                    'Content-Disposition' => 'inline; filename="' . addslashes(pathinfo($name, PATHINFO_FILENAME) . '.pdf') . '"',
                ]);
            }

            return $this->page($name, $this->notice('File Word/Excel cần LibreOffice trên server để xem trước. Anh vẫn có thể bấm Tải file để mở.'));
        }

        return $this->page($name, $this->notice('Định dạng này chưa hỗ trợ xem trước: .' . e($ext)));
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

        $cacheDir = storage_path('app/preview-cache/customer-profile-documents');

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
            return '<div class="ego-notice">Không đọc được CSV.</div>';
        }

        $html = '<table class="ego-preview-table">';
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

    private function notice(string $message): string
    {
        return '<div class="ego-notice">' . e($message) . '</div>';
    }

    private function page(string $title, string $body)
    {
        $css = '<style>
            *{box-sizing:border-box}
            html,body{margin:0;min-height:100%;font-family:Arial,"DejaVu Sans",sans-serif;background:#f8fafc;color:#0f172a}
            .ego-preview-top{height:46px;display:flex;align-items:center;padding:0 14px;background:linear-gradient(135deg,#0891b2,#0f766e);color:#fff}
            .ego-notice{max-width:680px;margin:70px auto;padding:20px;border:1px solid #dbe3ef;border-radius:18px;background:#fff;color:#475569;text-align:center;line-height:1.6;box-shadow:0 16px 36px rgba(15,23,42,.08)}
            .ego-text-preview{margin:14px;background:#fff;border:1px solid #dbe3ef;border-radius:14px;padding:16px;white-space:pre-wrap;line-height:1.6}
            .ego-table-wrap{padding:14px;overflow:auto}
            .ego-preview-table{width:100%;border-collapse:collapse;background:#fff}
            .ego-preview-table th,.ego-preview-table td{border:1px solid #d7dee8;padding:8px 10px;font-size:13px;vertical-align:top}
        </style>';

        return response('<!doctype html><html><head><meta charset="utf-8"><title>' . e($title) . '</title>' . $css . '</head><body>' . $body . '</body></html>');
    }
}
