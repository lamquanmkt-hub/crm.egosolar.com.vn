<?php

namespace App\Services\Hr;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class RecruitmentAttachmentService
{
    public const TABLES = ['requests' => 'hr_recruitment_requests', 'candidates' => 'hr_recruitment_candidates', 'interviews' => 'hr_recruitment_interviews', 'offers' => 'hr_recruitment_offers', 'hires' => 'hr_recruitment_hires'];
    public const EXTENSIONS = ['pdf', 'jpg', 'jpeg', 'png', 'webp', 'docx', 'xlsx', 'xls', 'csv', 'txt'];

    public static function maxKilobytes(): int
    {
        return min(10240, (int) (ini_parse_quantity(ini_get('upload_max_filesize')) / 1024));
    }

    public static function maxBatchBytes(): int
    {
        return min(80 * 1024 * 1024, max(1, ini_parse_quantity(ini_get('post_max_size')) - 512 * 1024));
    }

    public function validate(Request $request): void
    {
        $request->validate([
            'attachments' => ['nullable', 'array', 'max:8'],
            'attachments.*' => ['required', 'file', 'max:'.self::maxKilobytes(), 'extensions:'.implode(',', self::EXTENSIONS), function ($attribute, $file, $fail) {
                $ext = strtolower($file->getClientOriginalExtension());
                $allowed = match ($ext) {
                    'pdf'=>['application/pdf'], 'jpg','jpeg'=>['image/jpeg'], 'png'=>['image/png'], 'webp'=>['image/webp'],
                    'docx'=>['application/vnd.openxmlformats-officedocument.wordprocessingml.document','application/zip','application/x-zip'],
                    'xlsx'=>['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet','application/zip','application/x-zip'],
                    'xls'=>['application/vnd.ms-excel','application/x-ole-storage','application/CDFV2'],
                    'csv'=>['text/plain','text/csv','application/csv'], 'txt'=>['text/plain'], default=>[],
                };
                if (!in_array($file->getMimeType(), $allowed, true)) $fail('Nội dung tệp không khớp định dạng được hỗ trợ.');
            }],
        ], [
            'attachments.max' => 'Mỗi lần chọn tối đa 8 tệp.',
            'attachments.*.max' => 'Tệp vượt dung lượng cho phép: '.(self::maxKilobytes() / 1024).' MB/tệp.',
            'attachments.*.mimes' => 'Hỗ trợ PDF, ảnh JPG/PNG/WebP, Word DOCX, Excel XLS/XLSX, CSV và TXT.',
            'attachments.*.extensions' => 'Định dạng tệp chưa được hỗ trợ. Với Word .doc, hãy lưu thành .docx hoặc PDF.',
            'attachments.*.file' => 'Tệp tải lên không hợp lệ.',
        ]);
        if (array_sum(array_map(fn ($file) => $file->getSize(), $request->file('attachments', []))) > self::maxBatchBytes()) {
            throw ValidationException::withMessages(['attachments'=>'Tổng dung lượng quá lớn. Chia tệp thành nhiều lần lưu.']);
        }
        // Bound Office expansion before parsing compressed user documents.
        foreach ($request->file('attachments', []) as $index => $file) {
            $ext = strtolower($file->getClientOriginalExtension());
            if (!in_array($ext, ['docx', 'xlsx'], true)) continue;
            $zip = new \ZipArchive();
            $valid = $zip->open($file->getRealPath()) === true;
            if ($valid) {
                $size = 0;
                for ($i = 0; $i < $zip->numFiles; $i++) {
                    $size += $zip->statIndex($i)['size'];
                    if ($size > 50 * 1024 * 1024 || $zip->numFiles > 3000) { $valid = false; break; }
                }
                if ($zip->locateName($ext === 'docx' ? 'word/document.xml' : 'xl/workbook.xml') === false) $valid = false;
                $zip->close();
            }
            if (!$valid) throw ValidationException::withMessages(['attachments.'.$index => 'Tệp Office bị lỗi hoặc quá lớn khi giải nén. Hãy xuất thành PDF rồi tải lại.']);
        }
    }

    public function save(Request $request, string $entity, ?int $id, array $data): int
    {
        $paths = [];
        try {
            return DB::transaction(function () use ($request, $entity, $id, $data, &$paths) {
                $table = self::TABLES[$entity];
                if ($id !== null) {
                    abort_unless(DB::table($table)->where('id', $id)->lockForUpdate()->exists(), 404);
                    DB::table($table)->where('id', $id)->update($data);
                } else {
                    $id = DB::table($table)->insertGetId($data);
                }
                foreach ($request->file('attachments', []) as $file) {
                    $path = $file->store('hr/recruitment/'.$entity.'/'.$id, 'local');
                    if (!$path) throw new \RuntimeException('Không lưu được tệp. Vui lòng thử lại.');
                    $paths[] = $path;
                    DB::table('hr_recruitment_attachments')->insert([
                        'entity' => $entity, 'record_id' => $id, 'path' => $path,
                        'original_name' => mb_substr(basename(str_replace('\\', '/', $file->getClientOriginalName())), 0, 255),
                        'extension' => strtolower($file->getClientOriginalExtension()),
                        'mime_type' => $file->getMimeType(), 'size' => $file->getSize(),
                        'uploaded_by' => $request->user()->id, 'created_at' => now(), 'updated_at' => now(),
                    ]);
                }
                return $id;
            });
        } catch (\Throwable $e) {
            Storage::disk('local')->delete($paths);
            throw $e;
        }
    }

    /** Thêm tệp (đã validate) vào một bản ghi có sẵn, không đổi dữ liệu bản ghi. Trả số tệp đã lưu. */
    public function attach(Request $request, string $entity, int $id): int
    {
        abort_unless(isset(self::TABLES[$entity]) && DB::table(self::TABLES[$entity])->where('id', $id)->exists(), 404);
        $before = DB::table('hr_recruitment_attachments')->where('entity', $entity)->where('record_id', $id)->count();
        $this->save($request, $entity, $id, ['updated_at' => now()]);

        return DB::table('hr_recruitment_attachments')->where('entity', $entity)->where('record_id', $id)->count() - $before;
    }

    /** Xóa một tệp của bản ghi (dữ liệu bản ghi giữ nguyên). */
    public function deleteOne(string $entity, int $id, int $fileId): ?object
    {
        $file = DB::table('hr_recruitment_attachments')->where('id', $fileId)->where('entity', $entity)->where('record_id', $id)->first();
        if ($file) {
            DB::table('hr_recruitment_attachments')->where('id', $file->id)->delete();
            Storage::disk('local')->delete($file->path);
        }

        return $file;
    }

    public function delete(string $entity, int $id): void
    {
        $paths = DB::transaction(function () use ($entity, $id) {
            $query = DB::table('hr_recruitment_attachments')->where('entity', $entity)->where('record_id', $id);
            $paths = $query->pluck('path')->all();
            $query->delete();
            DB::table(self::TABLES[$entity])->where('id', $id)->delete();
            return $paths;
        });
        Storage::disk('local')->delete($paths);
    }
}
