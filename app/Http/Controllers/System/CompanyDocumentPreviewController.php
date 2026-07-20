<?php

namespace App\Http\Controllers\System;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Xem trước tài liệu công ty đa định dạng: ảnh, PDF, TXT/CSV, Excel, DOCX (kể cả convert qua LibreOffice).
 */
class CompanyDocumentPreviewController extends Controller
{
    /**
     * Tìm file theo id/tên rồi render trang xem trước phù hợp với từng định dạng.
     */
    public function __invoke(Request $request, $file)
    {
        ini_set('memory_limit', '1024M');
        set_time_limit(180);

        $id = (int) $file;
        $desiredName = trim((string) $request->query('name', ''));

        $found = $this->findFile($id, $desiredName);

        if (! $found) {
            return $this->page('Không tìm thấy file', $this->notice('Không tìm thấy file ID #'.$id.' trong database/storage.'));
        }

        $name = $found['name'] ?: ('File #'.$id);
        $real = $found['real'] ?? null;
        $url = $found['url'] ?? null;

        $header = $this->header($name, $id);

        if ($url) {
            return $this->page($name, $header.'<iframe src="'.e($url).'"></iframe>');
        }

        if (! $real || ! is_file($real)) {
            return $this->page($name, $header.$this->notice('Không tìm thấy file gốc trong storage.'));
        }

        $ext = strtolower(pathinfo($name ?: $real, PATHINFO_EXTENSION));
        if ($ext === '') {
            $ext = strtolower(pathinfo($real, PATHINFO_EXTENSION));
        }

        if (in_array($ext, ['png', 'jpg', 'jpeg', 'gif', 'webp', 'svg'], true)) {
            $mime = @mime_content_type($real) ?: 'image/'.($ext === 'jpg' ? 'jpeg' : $ext);
            $data = base64_encode(file_get_contents($real));

            return $this->page($name, $header.'<div class="preview-body"><img class="preview-img" src="data:'.e($mime).';base64,'.$data.'"></div>');
        }

        if ($ext === 'pdf') {
            $data = base64_encode(file_get_contents($real));

            return $this->page($name, $header.'<iframe src="data:application/pdf;base64,'.$data.'#toolbar=1"></iframe>');
        }

        if (in_array($ext, ['txt', 'log'], true)) {
            $text = htmlspecialchars((string) file_get_contents($real), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

            return $this->page($name, $header.'<div class="preview-body"><pre class="text-preview">'.$text.'</pre></div>');
        }

        if ($ext === 'csv') {
            return $this->page($name, $header.'<div class="preview-body"><div class="excel-wrap">'.$this->csvToTable($real).'</div></div>');
        }

        if (in_array($ext, ['xlsx', 'xls', 'ods'], true)) {
            return $this->excelPreview($name, $real, $header);
        }

        if ($ext === 'docx') {
            $html = $this->docxToHtml($real);

            if ($html) {
                return $this->page($name, $header.'<div class="preview-body">'.$html.'</div>');
            }

            $pdf = $this->convertOfficeToPdf($real);

            if ($pdf && is_file($pdf)) {
                $data = base64_encode(file_get_contents($pdf));

                return $this->page($name, $header.'<iframe src="data:application/pdf;base64,'.$data.'#toolbar=1"></iframe>');
            }

            return $this->page($name, $header.$this->notice('Không đọc được file DOCX này. Anh kiểm tra file có bị lỗi hoặc đặt lại tên không dấu rồi thử lại.'));
        }

        if ($ext === 'doc') {
            $pdf = $this->convertOfficeToPdf($real);

            if ($pdf && is_file($pdf)) {
                $data = base64_encode(file_get_contents($pdf));

                return $this->page($name, $header.'<iframe src="data:application/pdf;base64,'.$data.'#toolbar=1"></iframe>');
            }

            return $this->page($name, $header.$this->notice('File .doc cũ cần LibreOffice trên server để xem trước. DOCX hiện đã hỗ trợ xem trực tiếp.'));
        }

        return $this->page($name, $header.$this->notice('Định dạng này chưa hỗ trợ xem trước: .'.e($ext)));
    }

    /**
     * Dò tìm bản ghi file theo id trên nhiều bảng khả dĩ, chấm điểm để chọn kết quả khớp nhất.
     */
    protected function findFile(int $id, string $desiredName = ''): ?array
    {
        $tables = [];

        try {
            foreach (DB::select('SHOW TABLES') as $row) {
                $arr = (array) $row;
                $tables[] = reset($arr);
            }
        } catch (\Throwable $e) {
            $tables = [];
        }

        $priority = [
            'company_document_files',
            'company_documents',
            'company_document_uploads',
            'company_files',
            'document_files',
            'documents',
            'files',
            'media',
            'attachments',
        ];

        $tables = array_values(array_unique(array_merge($priority, $tables)));

        $desiredBase = mb_strtolower(pathinfo($desiredName, PATHINFO_FILENAME), 'UTF-8');
        $desiredExt = mb_strtolower(pathinfo($desiredName, PATHINFO_EXTENSION), 'UTF-8');

        $found = [];

        foreach ($tables as $table) {
            try {
                if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'id')) {
                    continue;
                }

                $row = DB::table($table)->where('id', $id)->first();

                if (! $row) {
                    continue;
                }

                $values = (array) $row;
                $allText = mb_strtolower(implode(' ', array_map('strval', $values)), 'UTF-8');

                $score = 0;

                if (stripos($table, 'company') !== false) {
                    $score += 300;
                }
                if (stripos($table, 'document') !== false) {
                    $score += 250;
                }
                if (stripos($table, 'file') !== false) {
                    $score += 150;
                }

                if ($desiredBase !== '' && strpos($allText, $desiredBase) !== false) {
                    $score += 700;
                }
                if ($desiredExt !== '' && strpos($allText, '.'.$desiredExt) !== false) {
                    $score += 500;
                }

                $paths = [];

                foreach ([
                    'path',
                    'file_path',
                    'filepath',
                    'storage_path',
                    'stored_path',
                    'full_path',
                    'url',
                    'file',
                    'attachment',
                    'filename',
                    'file_name',
                    'original_name',
                    'name',
                ] as $field) {
                    if (! empty($values[$field])) {
                        $paths[] = trim((string) $values[$field]);
                    }
                }

                foreach ($values as $value) {
                    $value = trim((string) $value);

                    if ($value !== '' && preg_match('/\.(docx?|xlsx?|ods|csv|pdf|png|jpg|jpeg|webp|gif|svg)($|\?)/i', $value)) {
                        $paths[] = $value;
                    }
                }

                $paths = array_values(array_unique($paths));

                foreach ($paths as $path) {
                    $resolved = $this->resolvePath($path);

                    if (! $resolved) {
                        continue;
                    }

                    $name = $this->pickName($values, $path, $desiredName);
                    $ext = mb_strtolower(pathinfo($name ?: $path, PATHINFO_EXTENSION), 'UTF-8');
                    $localScore = $score;

                    if ($desiredExt !== '' && $ext === $desiredExt) {
                        $localScore += 1000;
                    }

                    $found[] = [
                        'score' => $localScore,
                        'table' => $table,
                        'row' => $row,
                        'name' => $name,
                        'path' => $path,
                        'url' => $resolved['url'],
                        'real' => $resolved['real'],
                    ];
                }
            } catch (\Throwable $e) {
                continue;
            }
        }

        usort($found, fn ($a, $b) => $b['score'] <=> $a['score']);

        return $found[0] ?? null;
    }

    /**
     * Chọn tên hiển thị của file từ các cột tên trong bản ghi hoặc từ đường dẫn.
     */
    protected function pickName(array $values, string $path, string $desiredName = ''): string
    {
        $name = $desiredName ?: basename(parse_url($path, PHP_URL_PATH) ?: $path);

        foreach (['original_name', 'file_name', 'filename', 'name', 'title'] as $field) {
            if (! empty($values[$field]) && preg_match('/\.(docx?|xlsx?|ods|csv|pdf|png|jpg|jpeg|webp|gif|svg)$/i', (string) $values[$field])) {
                $name = basename((string) $values[$field]);
                break;
            }
        }

        if (pathinfo($name, PATHINFO_EXTENSION) === '' && pathinfo($path, PATHINFO_EXTENSION) !== '') {
            $name = basename(parse_url($path, PHP_URL_PATH) ?: $path);
        }

        return $name;
    }

    /**
     * Quy đổi đường dẫn lưu trong DB thành URL hoặc đường dẫn thật trên đĩa (thử nhiều vị trí).
     */
    protected function resolvePath(string $path): ?array
    {
        $path = trim(str_replace('\\', '/', $path));

        if ($path === '') {
            return null;
        }

        if (filter_var($path, FILTER_VALIDATE_URL)) {
            return ['url' => $path, 'real' => null];
        }

        if (is_file($path)) {
            return ['url' => null, 'real' => $path];
        }

        $clean = ltrim(urldecode($path), '/');
        $cleanNoStorage = str_starts_with($clean, 'storage/') ? substr($clean, 8) : $clean;
        $cleanNoPublic = str_starts_with($clean, 'public/') ? substr($clean, 7) : $clean;
        $base = basename(parse_url($clean, PHP_URL_PATH) ?: $clean);

        $candidates = [
            base_path($clean),
            storage_path('app/'.$clean),
            storage_path('app/public/'.$clean),
            storage_path('app/public/'.$cleanNoStorage),
            storage_path('app/public/'.$cleanNoPublic),
            public_path($clean),
            public_path('storage/'.$clean),
            public_path('storage/'.$cleanNoStorage),

            storage_path('app/public/company-documents/'.$base),
            storage_path('app/public/company_documents/'.$base),
            storage_path('app/company-documents/'.$base),
            storage_path('app/company_documents/'.$base),

            public_path('storage/company-documents/'.$base),
            public_path('storage/company_documents/'.$base),
            public_path('company-documents/'.$base),
            public_path('company_documents/'.$base),

            storage_path('app/public/uploads/'.$base),
            public_path('uploads/'.$base),
        ];

        foreach (array_unique($candidates) as $candidate) {
            if ($candidate && is_file($candidate)) {
                return ['url' => null, 'real' => $candidate];
            }
        }

        return null;
    }

    /**
     * Đọc file DOCX (zip XML) và dựng HTML xem trước: đoạn văn, bảng, hình ảnh.
     */
    protected function docxToHtml(string $real): ?string
    {
        if (! class_exists(\ZipArchive::class)) {
            return null;
        }

        $zip = new \ZipArchive;

        if ($zip->open($real) !== true) {
            return null;
        }

        $documentXml = $zip->getFromName('word/document.xml');

        if (! $documentXml) {
            $zip->close();

            return null;
        }

        $rels = $this->docxRelationships($zip);

        $dom = new \DOMDocument;
        $dom->preserveWhiteSpace = false;

        if (! @$dom->loadXML($documentXml)) {
            $zip->close();

            return null;
        }

        $xp = new \DOMXPath($dom);
        $this->registerDocxNamespaces($xp);

        $body = $xp->query('//w:body')->item(0);

        if (! $body) {
            $zip->close();

            return null;
        }

        $html = '<div class="docx-page">';

        foreach ($body->childNodes as $node) {
            if (! $node instanceof \DOMElement) {
                continue;
            }

            if ($node->localName === 'p') {
                $html .= $this->renderDocxParagraph($node, $xp, $zip, $rels);
            } elseif ($node->localName === 'tbl') {
                $html .= $this->renderDocxTable($node, $xp, $zip, $rels);
            }
        }

        $html .= '</div>';

        $zip->close();

        return $html;
    }

    /**
     * Đọc bảng ánh xạ relationship (rId => target) của file DOCX để lấy ảnh nhúng.
     */
    protected function docxRelationships(\ZipArchive $zip): array
    {
        $relsXml = $zip->getFromName('word/_rels/document.xml.rels');
        $rels = [];

        if (! $relsXml) {
            return $rels;
        }

        $dom = new \DOMDocument;

        if (! @$dom->loadXML($relsXml)) {
            return $rels;
        }

        foreach ($dom->getElementsByTagName('Relationship') as $rel) {
            $id = $rel->getAttribute('Id');
            $target = $rel->getAttribute('Target');

            if ($id && $target) {
                $rels[$id] = $target;
            }
        }

        return $rels;
    }

    /**
     * Đăng ký các namespace XML của DOCX cho DOMXPath.
     */
    protected function registerDocxNamespaces(\DOMXPath $xp): void
    {
        $xp->registerNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');
        $xp->registerNamespace('a', 'http://schemas.openxmlformats.org/drawingml/2006/main');
        $xp->registerNamespace('r', 'http://schemas.openxmlformats.org/officeDocument/2006/relationships');
        $xp->registerNamespace('wp', 'http://schemas.openxmlformats.org/drawingml/2006/wordprocessingDrawing');
    }

    /**
     * Dựng HTML cho một đoạn văn DOCX (heading, căn lề, các run bên trong).
     */
    protected function renderDocxParagraph(\DOMElement $p, \DOMXPath $xp, \ZipArchive $zip, array $rels): string
    {
        $styleVal = '';
        $pStyle = $xp->query('./w:pPr/w:pStyle', $p)->item(0);

        if ($pStyle instanceof \DOMElement) {
            $styleVal = $pStyle->getAttributeNS('http://schemas.openxmlformats.org/wordprocessingml/2006/main', 'val');
        }

        $align = '';
        $jc = $xp->query('./w:pPr/w:jc', $p)->item(0);

        if ($jc instanceof \DOMElement) {
            $val = $jc->getAttributeNS('http://schemas.openxmlformats.org/wordprocessingml/2006/main', 'val');
            if (in_array($val, ['center', 'right', 'both'], true)) {
                $align = $val === 'both' ? 'text-align:justify;' : 'text-align:'.$val.';';
            }
        }

        $inner = '';

        foreach ($xp->query('./w:r|./w:hyperlink/w:r', $p) as $run) {
            if ($run instanceof \DOMElement) {
                $inner .= $this->renderDocxRun($run, $xp, $zip, $rels);
            }
        }

        if (trim(strip_tags($inner)) === '' && strpos($inner, '<img') === false) {
            $inner = '<br>';
        }

        $tag = 'p';

        if (preg_match('/heading1|title/i', $styleVal)) {
            $tag = 'h1';
        } elseif (preg_match('/heading2/i', $styleVal)) {
            $tag = 'h2';
        } elseif (preg_match('/heading3/i', $styleVal)) {
            $tag = 'h3';
        }

        return '<'.$tag.' style="'.e($align).'">'.$inner.'</'.$tag.'>';
    }

    /**
     * Dựng HTML cho một run DOCX: text, tab, xuống dòng, ảnh, kèm định dạng đậm/nghiêng/màu/cỡ chữ.
     */
    protected function renderDocxRun(\DOMElement $run, \DOMXPath $xp, \ZipArchive $zip, array $rels): string
    {
        $content = '';

        foreach ($xp->query('.//w:t|.//w:tab|.//w:br', $run) as $node) {
            if (! $node instanceof \DOMElement) {
                continue;
            }

            if ($node->localName === 't') {
                $content .= htmlspecialchars($node->textContent, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            } elseif ($node->localName === 'tab') {
                $content .= '&nbsp;&nbsp;&nbsp;&nbsp;';
            } elseif ($node->localName === 'br') {
                $content .= '<br>';
            }
        }

        foreach ($xp->query('.//a:blip', $run) as $blip) {
            if (! $blip instanceof \DOMElement) {
                continue;
            }

            $rid = $blip->getAttributeNS('http://schemas.openxmlformats.org/officeDocument/2006/relationships', 'embed');

            if ($rid && isset($rels[$rid])) {
                $content .= $this->docxImageHtml($zip, $rels[$rid]);
            }
        }

        if ($content === '') {
            return '';
        }

        $style = '';

        if ($xp->query('./w:rPr/w:b', $run)->length) {
            $style .= 'font-weight:700;';
        }
        if ($xp->query('./w:rPr/w:i', $run)->length) {
            $style .= 'font-style:italic;';
        }
        if ($xp->query('./w:rPr/w:u', $run)->length) {
            $style .= 'text-decoration:underline;';
        }

        $color = $xp->query('./w:rPr/w:color', $run)->item(0);
        if ($color instanceof \DOMElement) {
            $val = $color->getAttributeNS('http://schemas.openxmlformats.org/wordprocessingml/2006/main', 'val');
            if ($val && strtolower($val) !== 'auto' && preg_match('/^[0-9a-fA-F]{6}$/', $val)) {
                $style .= 'color:#'.$val.';';
            }
        }

        $size = $xp->query('./w:rPr/w:sz', $run)->item(0);
        if ($size instanceof \DOMElement) {
            $val = (int) $size->getAttributeNS('http://schemas.openxmlformats.org/wordprocessingml/2006/main', 'val');
            if ($val > 0) {
                $style .= 'font-size:'.max(8, round($val / 2)).'pt;';
            }
        }

        return $style ? '<span style="'.e($style).'">'.$content.'</span>' : $content;
    }

    /**
     * Trích ảnh nhúng trong DOCX và trả thẻ img dạng base64 (hoặc URL ngoài).
     */
    protected function docxImageHtml(\ZipArchive $zip, string $target): string
    {
        $target = str_replace('\\', '/', $target);

        if (preg_match('/^https?:\/\//i', $target)) {
            return '<img class="docx-img" src="'.e($target).'">';
        }

        $paths = [
            'word/'.ltrim($target, '/'),
            ltrim($target, '/'),
        ];

        $data = false;
        $pathUsed = '';

        foreach ($paths as $path) {
            $data = $zip->getFromName($path);
            if ($data !== false) {
                $pathUsed = $path;
                break;
            }
        }

        if ($data === false) {
            return '';
        }

        $ext = strtolower(pathinfo($pathUsed, PATHINFO_EXTENSION));
        $mime = match ($ext) {
            'jpg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
            'svg' => 'image/svg+xml',
            default => 'image/png',
        };

        return '<img class="docx-img" src="data:'.e($mime).';base64,'.base64_encode($data).'">';
    }

    /**
     * Dựng HTML cho bảng trong DOCX (hỗ trợ bảng lồng nhau).
     */
    protected function renderDocxTable(\DOMElement $tbl, \DOMXPath $xp, \ZipArchive $zip, array $rels): string
    {
        $html = '<table class="docx-table">';

        foreach ($xp->query('./w:tr', $tbl) as $tr) {
            if (! $tr instanceof \DOMElement) {
                continue;
            }

            $html .= '<tr>';

            foreach ($xp->query('./w:tc', $tr) as $tc) {
                if (! $tc instanceof \DOMElement) {
                    continue;
                }

                $cell = '';

                foreach ($tc->childNodes as $child) {
                    if (! $child instanceof \DOMElement) {
                        continue;
                    }

                    if ($child->localName === 'p') {
                        $cell .= $this->renderDocxParagraph($child, $xp, $zip, $rels);
                    } elseif ($child->localName === 'tbl') {
                        $cell .= $this->renderDocxTable($child, $xp, $zip, $rels);
                    }
                }

                $html .= '<td>'.$cell.'</td>';
            }

            $html .= '</tr>';
        }

        $html .= '</table>';

        return $html;
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

        $cacheDir = storage_path('app/preview-cache/company-documents');

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

        if (! is_dir($workDir)) {
            @mkdir($workDir, 0775, true);
        }

        if (! is_dir($profileDir)) {
            @mkdir($profileDir, 0775, true);
        }

        $ext = strtolower(pathinfo($real, PATHINFO_EXTENSION));

        if ($ext === '') {
            $mime = @mime_content_type($real) ?: '';

            if (str_contains($mime, 'spreadsheet') || str_contains($mime, 'excel')) {
                $ext = 'xls';
            } elseif (str_contains($mime, 'word')) {
                $ext = 'docx';
            } else {
                $ext = 'bin';
            }
        }

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
     * Xem trước Excel bằng PhpSpreadsheet (chọn sheet, render HTML), lỗi thì fallback PDF LibreOffice.
     */
    protected function excelPreview(string $name, string $real, string $header)
    {
        if (! class_exists(\PhpOffice\PhpSpreadsheet\IOFactory::class)) {
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
                $header.$this->notice('Server chưa có PhpSpreadsheet/LibreOffice nên chưa xem trước được file Excel này. Anh vẫn có thể bấm Tải xuống để mở file.')
            );
        }

        $oldReporting = error_reporting();
        $iconvHandler = function ($severity, $message, $file = null, $line = null) {
            $msg = strtolower((string) $message);

            if (
                strpos($msg, 'iconv') !== false ||
                strpos($msg, 'incomplete multibyte') !== false ||
                strpos($msg, 'illegal character') !== false ||
                strpos($msg, 'detected an incomplete') !== false
            ) {
                return true;
            }

            return false;
        };

        try {
            $sheetIndex = max(0, (int) request()->query('sheet', 0));

            $reader = \PhpOffice\PhpSpreadsheet\IOFactory::createReaderForFile($real);

            // Quan trọng: .xls cũ phải đọc dataOnly để tránh lỗi iconv/style encoding.
            $reader->setReadDataOnly(true);

            if (method_exists($reader, 'setReadEmptyCells')) {
                $reader->setReadEmptyCells(false);
            }

            $sheetNames = [];

            try {
                set_error_handler($iconvHandler);
                $sheetNames = $reader->listWorksheetNames($real);
                restore_error_handler();
            } catch (\Throwable $e) {
                restore_error_handler();
                $sheetNames = [];
            }

            if (! empty($sheetNames)) {
                if ($sheetIndex >= count($sheetNames)) {
                    $sheetIndex = 0;
                }

                if (method_exists($reader, 'setLoadSheetsOnly')) {
                    $reader->setLoadSheetsOnly($sheetNames[$sheetIndex]);
                }
            }

            try {
                set_error_handler($iconvHandler);
                $spreadsheet = $reader->load($real);
                restore_error_handler();
            } catch (\Throwable $e) {
                restore_error_handler();

                // Nếu PhpSpreadsheet vẫn lỗi, thử convert PDF bằng LibreOffice.
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
                    $header.$this->notice('Không đọc được Excel. Chi tiết: '.$e->getMessage())
                );
            }

            if (empty($sheetNames)) {
                $sheetNames = [];

                for ($i = 0; $i < $spreadsheet->getSheetCount(); $i++) {
                    $sheetNames[] = $spreadsheet->getSheet($i)->getTitle();
                }
            }

            if (empty($sheetNames)) {
                return $this->page($name, $header.$this->notice('File Excel không có sheet để hiển thị.'));
            }

            $tabs = '<div class="excel-tabs">';

            foreach ($sheetNames as $i => $sheetName) {
                $href = request()->fullUrlWithQuery(['sheet' => $i]);

                $tabs .= '<a class="excel-tab '.($i === $sheetIndex ? 'active' : '').'" href="'.e($href).'">'.e($sheetName).'</a>';
            }

            $tabs .= '</div>';

            $writer = new \PhpOffice\PhpSpreadsheet\Writer\Html($spreadsheet);

            // Nếu đã load riêng 1 sheet thì index trong workbook tạm là 0.
            $writer->setSheetIndex(! empty($sheetNames) && method_exists($reader, 'setLoadSheetsOnly') ? 0 : $sheetIndex);
            $writer->setUseInlineCss(true);

            ob_start();
            $writer->save('php://output');
            $html = ob_get_clean();

            $html = preg_replace('/<!DOCTYPE[^>]*>/i', '', $html);
            $html = preg_replace('/<html[^>]*>|<\/html>|<head[^>]*>.*?<\/head>|<body[^>]*>|<\/body>/is', '', $html);

            $spreadsheet->disconnectWorksheets();

            $extraCss = '<style>
                .excel-preview-body{
                    padding:0 !important;
                    background:#f8fafc;
                }

                .excel-tabs{
                    position:sticky;
                    top:0;
                    z-index:20;
                    display:flex;
                    align-items:center;
                    gap:8px;
                    flex-wrap:wrap;
                    padding:10px 12px;
                    background:#ffffff;
                    border-bottom:1px solid #dbe3ef;
                    box-shadow:0 6px 16px rgba(15,23,42,.05);
                }

                .excel-tab{
                    display:inline-flex;
                    align-items:center;
                    justify-content:center;
                    padding:7px 12px;
                    border-radius:999px;
                    border:1px solid #dbe3ef;
                    background:#f8fafc;
                    color:#0f172a;
                    text-decoration:none;
                    font-size:12px;
                    font-weight:900;
                    max-width:220px;
                    white-space:nowrap;
                    overflow:hidden;
                    text-overflow:ellipsis;
                }

                .excel-tab.active{
                    background:#2563eb;
                    border-color:#2563eb;
                    color:#fff;
                }

                .excel-wrap{
                    margin:12px;
                    max-width:none;
                    overflow:auto;
                }

                .excel-wrap table{
                    min-width:100%;
                }
            </style>';

            error_reporting($oldReporting);

            return $this->page(
                $name,
                $header.'<div class="preview-body excel-preview-body">'.$extraCss.$tabs.'<div class="excel-wrap">'.$html.'</div></div>'
            );
        } catch (\Throwable $e) {
            error_reporting($oldReporting);

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
                $header.$this->notice('Không đọc được Excel. Chi tiết: '.$e->getMessage())
            );
        }
    }

    /**
     * Đọc file CSV và dựng bảng HTML (dòng đầu làm header).
     */
    protected function csvToTable(string $real): string
    {
        $handle = fopen($real, 'r');

        if (! $handle) {
            return '<div class="notice">Không đọc được CSV.</div>';
        }

        $html = '<table class="csv-table">';
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
     * Header trang xem trước (hiện trả rỗng vì modal bên ngoài đã có tiêu đề).
     */
    protected function header(string $name, int $id): string
    {
        // Modal bên ngoài đã có tiêu đề + nút tải xuống, tránh hiện trùng header trong iframe.
        return '';
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
            iframe{width:100%;height:100vh;border:0;background:#fff}
            .preview-top{position:sticky;top:0;z-index:50;background:linear-gradient(90deg,#0f3b78,#0796b8);color:#fff}
            .preview-bar{display:flex;align-items:center;justify-content:space-between;gap:12px;padding:12px 16px}
            .preview-title{font-size:14px;font-weight:900;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
            .preview-download{display:inline-flex;align-items:center;justify-content:center;border:1px solid rgba(255,255,255,.35);background:rgba(255,255,255,.12);color:#fff;text-decoration:none;border-radius:10px;padding:8px 12px;font-weight:800;font-size:13px}
            .preview-body{padding:18px;overflow:auto}
            .notice{max-width:720px;margin:70px auto;padding:22px;border:1px solid #dbe3ef;border-radius:18px;background:#fff;color:#475569;line-height:1.6;text-align:center;box-shadow:0 18px 45px rgba(15,23,42,.08)}
            .preview-img{display:block;max-width:100%;height:auto;margin:0 auto;background:#fff;border-radius:12px;box-shadow:0 12px 30px rgba(15,23,42,.08)}
            .text-preview{white-space:pre-wrap;background:#fff;border:1px solid #dbe3ef;border-radius:14px;padding:16px;line-height:1.6}
            .excel-wrap{background:#fff;border:1px solid #dbe3ef;border-radius:14px;overflow:auto;box-shadow:0 12px 30px rgba(15,23,42,.06)}
            .excel-wrap table,.csv-table{border-collapse:collapse;width:100%;background:#fff}
            .excel-wrap td,.excel-wrap th,.csv-table td,.csv-table th{border:1px solid #d7dee8;padding:7px 9px;font-size:13px;vertical-align:top}
            .docx-page{max-width:980px;margin:0 auto;background:#fff;border:1px solid #e2e8f0;border-radius:16px;padding:38px 46px;box-shadow:0 18px 45px rgba(15,23,42,.08);line-height:1.55}
            .docx-page p{margin:0 0 10px}
            .docx-page h1{font-size:24px;margin:0 0 16px;font-weight:900}
            .docx-page h2{font-size:20px;margin:18px 0 12px;font-weight:900}
            .docx-page h3{font-size:17px;margin:14px 0 10px;font-weight:900}
            .docx-table{border-collapse:collapse;width:100%;margin:12px 0}
            .docx-table td{border:1px solid #94a3b8;padding:8px;vertical-align:top}
            .docx-table p{margin:0 0 6px}
            .docx-img{display:block;max-width:100%;height:auto;margin:10px auto}
        </style>';

        return response('<!doctype html><html><head><meta charset="utf-8"><title>'.e($title).'</title>'.$css.'</head><body>'.$body.'</body></html>');
    }
}
