<?php

namespace App\Http\Controllers\Hr;

use App\Contracts\Services\PageAccessServiceInterface;
use App\Http\Controllers\CompanyDocumentPreviewController;
use App\Services\Hr\RecruitmentAttachmentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\IOFactory;

class RecruitmentAttachmentController extends CompanyDocumentPreviewController
{
    private function attachment(Request $request, string $entity, int $record, int $file): object
    {
        abort_unless($request->user() && app(PageAccessServiceInterface::class)->canAccess($request->user(), 'page.hr'), 403);
        abort_unless(isset(RecruitmentAttachmentService::TABLES[$entity]), 404);
        abort_unless(DB::table(RecruitmentAttachmentService::TABLES[$entity])->where('id', $record)->exists(), 404);
        $attachment = DB::table('hr_recruitment_attachments')->where('id', $file)->where('entity', $entity)->where('record_id', $record)->first();
        abort_unless($attachment && Storage::disk('local')->exists($attachment->path), 404, 'Tệp không còn trên hệ thống.');
        return $attachment;
    }

    public function preview(Request $request, string $entity, int $record, int $file)
    {
        $attachment = $this->attachment($request, $entity, $record, $file);
        if ($entity === 'hires') {
            // Chứng cứ lượt tuyển (KPI HC01/HC03): quay lại đúng trang lượt tuyển.
            $back = route('hr.recruitment.hires.show', $record);

            return response()->view('hr.recruitment.attachment-preview', compact('attachment', 'entity', 'record', 'back'))->header('Cache-Control', 'private, no-store');
        }
        $allowed = ['requests'=>['requests'], 'candidates'=>['screening','archives'], 'interviews'=>['interviews','evaluations'], 'offers'=>['offers','onboarding']];
        $stage = in_array($request->query('stage'), $allowed[$entity], true) ? $request->query('stage') : $allowed[$entity][0];
        $back = route('hr.recruitment.'.$stage).'#'.($entity === 'requests' ? 'rec-request-'.$record : 'rec-workflow-'.$stage.'-'.$record);
        return response()->view('hr.recruitment.attachment-preview', compact('attachment', 'entity', 'record', 'back'))->header('Cache-Control', 'private, no-store');
    }

    public function download(Request $request, string $entity, int $record, int $file)
    {
        $attachment = $this->attachment($request, $entity, $record, $file);
        return Storage::disk('local')->download($attachment->path, $attachment->original_name, ['Cache-Control'=>'private, no-store', 'X-Content-Type-Options'=>'nosniff']);
    }

    public function content(Request $request, string $entity, int $record, int $file)
    {
        $attachment = $this->attachment($request, $entity, $record, $file);
        $real = Storage::disk('local')->path($attachment->path);
        $headers = ['Cache-Control'=>'private, no-store', 'X-Content-Type-Options'=>'nosniff', 'X-Frame-Options'=>'SAMEORIGIN'];
        $ext = $attachment->extension;
        if (in_array($ext, ['pdf','jpg','jpeg','png','webp'], true)) {
            $mime = ['pdf'=>'application/pdf','jpg'=>'image/jpeg','jpeg'=>'image/jpeg','png'=>'image/png','webp'=>'image/webp'][$ext];
            return response()->file($real, $headers + ['Content-Type'=>$mime]);
        }
        try {
            $body = match ($ext) {
                'docx' => $this->docxToHtml($real) ?: '<p>Không đọc được nội dung Word. Vui lòng kiểm tra tệp gốc.</p>',
                'xls', 'xlsx', 'csv' => $this->spreadsheetHtml($real, $ext, $request),
                'txt' => '<pre>'.e(file_get_contents($real, false, null, 0, 1024 * 1024)).'</pre><p>Hiển thị tối đa 1 MB văn bản UTF-8.</p>',
                default => '<p>Định dạng này chưa hỗ trợ xem trước.</p>',
            };
        } catch (\Throwable $e) {
            report($e);
            $body = '<p>Không đọc được nội dung tệp này. Bạn có thể tải xuống để kiểm tra tệp gốc.</p>';
        }
        return response()->view('hr.recruitment.attachment-content', compact('body'), 200, $headers + [
            'Content-Security-Policy'=>"default-src 'none'; img-src data:; style-src 'unsafe-inline'; base-uri 'none'; form-action 'none'; frame-ancestors 'self'; sandbox allow-same-origin",
        ]);
    }

    protected function docxImageHtml(\ZipArchive $zip, string $target): string
    {
        if (preg_match('~^[a-z]+:|\.\.|^/~i', $target) || !in_array(strtolower(pathinfo($target, PATHINFO_EXTENSION)), ['jpg','jpeg','png','webp','gif'], true)) return '';
        return parent::docxImageHtml($zip, $target);
    }

    private function spreadsheetHtml(string $real, string $ext, Request $request): string
    {
        $reader = IOFactory::createReader($ext === 'csv' ? 'Csv' : ($ext === 'xls' ? 'Xls' : 'Xlsx'));
        $reader->setReadDataOnly(true);
        $reader->setReadFilter(new class implements \PhpOffice\PhpSpreadsheet\Reader\IReadFilter {
            public function readCell(string $columnAddress, int $row, string $worksheetName = ''): bool
            {
                return $row <= 200 && \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($columnAddress) <= 30;
            }
        });
        $names = array_slice($reader->listWorksheetNames($real), 0, 10);
        $index = max(0, min(count($names) - 1, (int) $request->query('sheet', 0)));
        $reader->setLoadSheetsOnly($names[$index]);
        $book = $reader->load($real);
        try {
            $html = '<nav>';
            foreach ($names as $i => $name) $html .= '<a href="'.e($request->fullUrlWithQuery(['sheet'=>$i])).'">'.e($name).'</a> ';
            $html .= '</nav><p>Xem nội dung tối đa 200 dòng × 30 cột mỗi sheet; tải xuống để xem đầy đủ định dạng và công thức.</p><div class="sheet"><table>';
            $sheet = $book->getSheet(0);
            $lastRow = min(200, $sheet->getHighestDataRow());
            $lastCol = min(30, \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($sheet->getHighestDataColumn()));
            for ($r = 1; $r <= $lastRow; $r++) {
                $html .= '<tr>';
                for ($c = 1; $c <= $lastCol; $c++) $html .= '<td>'.e((string) $sheet->getCell([$c, $r])->getValue()).'</td>';
                $html .= '</tr>';
            }
            return $html.'</table></div>';
        } finally { $book->disconnectWorksheets(); }
    }
}
