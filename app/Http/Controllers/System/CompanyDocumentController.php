<?php

namespace App\Http\Controllers\System;

use App\Http\Controllers\Controller;
use App\Models\CompanyDocumentFile;
use App\Models\CompanyDocumentFolder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Kho tài liệu công ty theo phòng ban: folder, upload, xem trước, tải xuống, sao chép/di chuyển.
 */
class CompanyDocumentController extends Controller
{
    private array $departments = [
        'sales' => ['label' => 'Phòng Sales', 'roles' => ['sales', 'sales_manager']],
        'marketing' => ['label' => 'Marketing', 'roles' => ['marketing', 'marketing_manager']],
        'technical' => ['label' => 'Kỹ thuật', 'roles' => ['ky_thuat']],
        'accounting' => ['label' => 'Kế toán', 'roles' => ['accounting']],
        'assistant' => ['label' => 'Trợ lý', 'roles' => ['assistant', 'tro_ly', 'management']],
        'warehouse' => ['label' => 'Kho', 'roles' => ['warehouse', 'kho']],
        'hr' => ['label' => 'Nhân sự', 'roles' => ['hr', 'hr_manager', 'human_resources', 'nhan_su']],
        'media' => ['label' => 'Tư liệu hình ảnh', 'roles' => ['*']],
    ];

    /**
     * Duyệt tài liệu theo phòng ban và folder hiện tại (kiểm tra quyền phòng ban).
     */
    public function index(Request $request)
    {
        $allowed = $this->allowedDepartments();
        abort_if(empty($allowed), 403);

        $department = $request->query('department', $allowed[0]);
        abort_unless(in_array($department, $allowed, true), 403);

        $folderId = $request->integer('folder');
        $currentFolder = null;
        if ($folderId) {
            $currentFolder = CompanyDocumentFolder::where('department', $department)->findOrFail($folderId);
        }

        $folders = CompanyDocumentFolder::where('department', $department)
            ->where('parent_id', $currentFolder?->id)
            ->orderBy('name')
            ->get();

        $files = CompanyDocumentFile::where('department', $department)
            ->where('folder_id', $currentFolder?->id)
            ->latest()
            ->get();

        return view('company-documents.index', [
            'departments' => $this->departments,
            'allowed' => $allowed,
            'department' => $department,
            'currentFolder' => $currentFolder,
            'folders' => $folders,
            'files' => $files,
        ]);
    }

    /**
     * Tạo folder mới trong phòng ban (folder cha phải cùng phòng ban).
     */
    public function storeFolder(Request $request)
    {
        $data = $request->validate([
            'department' => ['required', 'string'],
            'parent_id' => ['nullable', 'integer'],
            'name' => ['required', 'string', 'max:255'],
        ]);

        abort_unless(in_array($data['department'], $this->allowedDepartments(), true), 403);

        if (! empty($data['parent_id'])) {
            CompanyDocumentFolder::where('department', $data['department'])->findOrFail($data['parent_id']);
        }

        CompanyDocumentFolder::create([
            'department' => $data['department'],
            'parent_id' => $data['parent_id'] ?? null,
            'name' => trim($data['name']),
            'created_by' => auth()->id(),
        ]);

        return back()->with('success', 'Đã tạo folder.');
    }

    /**
     * Upload nhiều file (tối đa 50MB/file) vào folder của phòng ban.
     */
    public function upload(Request $request)
    {
        $data = $request->validate([
            'department' => ['required', 'string'],
            'folder_id' => ['nullable', 'integer'],
            'files' => ['required'],
            // Allow-list phần mở rộng + dung lượng, theo đúng quy ước đang
            // dùng ở Finance\PaymentAdvanceController / SupplierDebtController
            // (bổ sung thêm các định dạng tài liệu công ty thường dùng).
            'files.*' => ['file', 'max:51200', 'mimes:jpg,jpeg,png,webp,gif,pdf,doc,docx,xls,xlsx,ppt,pptx,csv,txt,zip,rar'],
        ]);

        abort_unless(in_array($data['department'], $this->allowedDepartments(), true), 403);

        $folderId = $data['folder_id'] ?? null;
        if ($folderId) {
            CompanyDocumentFolder::where('department', $data['department'])->findOrFail($folderId);
        }

        foreach ($request->file('files', []) as $file) {
            $safeName = Str::slug(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME));
            $ext = $file->getClientOriginalExtension();
            $storedName = now()->format('YmdHis').'_'.Str::random(8).'_'.$safeName.($ext ? '.'.$ext : '');
            $path = $file->storeAs('company-documents/'.$data['department'], $storedName, 'public');

            CompanyDocumentFile::create([
                'department' => $data['department'],
                'folder_id' => $folderId,
                'name' => $safeName ?: $file->getClientOriginalName(),
                'original_name' => $file->getClientOriginalName(),
                'path' => $path,
                'mime' => $file->getClientMimeType(),
                'size' => $file->getSize(),
                'uploaded_by' => auth()->id(),
            ]);
        }

        return back()->with('success', 'Đã upload file.');
    }

    /**
     * Xem trước file: inline với ảnh/PDF/text, định dạng khác hiển thị trang gợi ý tải xuống.
     */
    public function preview(CompanyDocumentFile $file)
    {
        abort_unless(in_array($file->department, $this->allowedDepartments(), true), 403);
        abort_unless(Storage::disk('public')->exists($file->path), 404);

        $disk = Storage::disk('public');
        $realPath = $disk->path($file->path);
        $name = $file->original_name ?: basename($file->path);
        $mime = $file->mime ?: (@mime_content_type($realPath) ?: 'application/octet-stream');
        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));

        $safeName = str_replace(['"', "\r", "\n"], '', $name);

        if (str_starts_with((string) $mime, 'image/') && ! in_array($ext, ['svg'], true)) {
            return response()->file($realPath, [
                'Content-Type' => $mime,
                'Content-Disposition' => 'inline; filename="'.$safeName.'"',
                'X-Content-Type-Options' => 'nosniff',
            ]);
        }

        if ($mime === 'application/pdf' || $ext === 'pdf') {
            return response()->file($realPath, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'inline; filename="'.$safeName.'"',
                'X-Content-Type-Options' => 'nosniff',
            ]);
        }

        if (
            str_starts_with((string) $mime, 'text/')
            || in_array($ext, ['txt', 'csv', 'log', 'md'], true)
        ) {
            $content = file_get_contents($realPath);

            return response($content, 200, [
                'Content-Type' => 'text/plain; charset=utf-8',
                'Content-Disposition' => 'inline; filename="'.$safeName.'"',
                'X-Content-Type-Options' => 'nosniff',
            ]);
        }

        $downloadUrl = route('company-documents.files.download', $file);

        return response('<!doctype html>
<html lang="vi">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Không hỗ trợ xem trước</title>
<style>
    body{margin:0;min-height:100vh;display:flex;align-items:center;justify-content:center;font-family:Arial,sans-serif;background:#f8fafc;color:#0f172a}
    .box{max-width:520px;margin:20px;padding:28px;border:1px solid #dbe3ef;border-radius:22px;background:#fff;box-shadow:0 18px 46px rgba(15,23,42,.08);text-align:center}
    .icon{width:72px;height:72px;border-radius:22px;background:#eff6ff;color:#2563eb;display:flex;align-items:center;justify-content:center;margin:0 auto 14px;font-size:34px}
    h1{font-size:22px;margin:0 0 8px}
    p{color:#64748b;line-height:1.55;margin:0 0 18px}
    a{display:inline-flex;align-items:center;justify-content:center;height:42px;padding:0 18px;border-radius:13px;background:#2563eb;color:#fff;text-decoration:none;font-weight:800}
</style>
</head>
<body>
    <div class="box">
        <div class="icon">📄</div>
        <h1>Định dạng này chưa hỗ trợ xem trước</h1>
        <p>CRM hiện xem trực tiếp tốt nhất với ảnh, PDF, TXT/CSV. Với file Office/ZIP, vui lòng tải xuống để mở.</p>
        <a href="'.e($downloadUrl).'">Tải file xuống</a>
    </div>
</body>
</html>', 200, [
            'Content-Type' => 'text/html; charset=utf-8',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /**
     * Tải xuống file tài liệu với tên gốc.
     */
    public function download(CompanyDocumentFile $file)
    {
        abort_unless(in_array($file->department, $this->allowedDepartments(), true), 403);
        abort_unless(Storage::disk('public')->exists($file->path), 404);

        return Storage::disk('public')->download($file->path, $file->original_name ?: basename($file->path));
    }

    /**
     * Xóa một file tài liệu (cả file vật lý và bản ghi).
     */
    public function destroyFile(CompanyDocumentFile $file)
    {
        abort_unless(in_array($file->department, $this->allowedDepartments(), true), 403);
        Storage::disk('public')->delete($file->path);
        $file->delete();

        return back()->with('success', 'Đã xóa file.');
    }

    /**
     * Xóa folder cùng toàn bộ file và folder con bên trong (đệ quy).
     */
    public function destroyFolder(CompanyDocumentFolder $folder)
    {
        abort_unless(in_array($folder->department, $this->allowedDepartments(), true), 403);

        foreach ($folder->files as $file) {
            Storage::disk('public')->delete($file->path);
            $file->delete();
        }
        foreach ($folder->children as $child) {
            $this->deleteFolderTree($child);
        }
        $folder->delete();

        return redirect()->route('company-documents.index', ['department' => $folder->department])->with('success', 'Đã xóa folder.');
    }

    /**
     * Xóa đệ quy một cây folder: file vật lý, bản ghi file và các folder con.
     */
    private function deleteFolderTree(CompanyDocumentFolder $folder): void
    {
        foreach ($folder->files as $file) {
            Storage::disk('public')->delete($file->path);
            $file->delete();
        }
        foreach ($folder->children as $child) {
            $this->deleteFolderTree($child);
        }
        $folder->delete();
    }

    /**
     * Ghi file/folder vào clipboard phiên làm việc để sao chép hoặc di chuyển.
     */
    public function setClipboard(Request $request)
    {
        $data = $request->validate([
            'object_type' => ['required', 'in:file,folder'],
            'object_id' => ['required', 'integer'],
            'action' => ['required', 'in:copy,move'],
        ]);

        if ($data['object_type'] === 'folder') {
            $item = CompanyDocumentFolder::findOrFail((int) $data['object_id']);
            abort_unless(in_array($item->department, $this->allowedDepartments(), true), 403);
            $name = $item->name;
        } else {
            $item = CompanyDocumentFile::findOrFail((int) $data['object_id']);
            abort_unless(in_array($item->department, $this->allowedDepartments(), true), 403);
            $name = $item->original_name ?: $item->name;
        }

        session()->put('company_doc_clipboard', [
            'object_type' => $data['object_type'],
            'object_id' => (int) $data['object_id'],
            'action' => $data['action'],
            'name' => $name,
            'department' => $item->department,
        ]);

        return back()->with('success', 'Đã chọn '.($data['action'] === 'copy' ? 'sao chép' : 'di chuyển').': '.$name);
    }

    /**
     * Hủy thao tác sao chép/di chuyển đang chờ trong clipboard.
     */
    public function clearClipboard()
    {
        session()->forget('company_doc_clipboard');

        return back()->with('success', 'Đã hủy thao tác sao chép/di chuyển.');
    }

    /**
     * Dán file/folder từ clipboard vào vị trí đích: sao chép hoặc di chuyển, tự đổi tên khi trùng.
     */
    public function paste(Request $request)
    {
        $data = $request->validate([
            'department' => ['required', 'string'],
            'folder_id' => ['nullable', 'integer'],
        ]);

        abort_unless(in_array($data['department'], $this->allowedDepartments(), true), 403);

        $clip = session('company_doc_clipboard');

        if (! $clip) {
            return back()->withErrors(['clipboard' => 'Chưa chọn file/folder để dán.']);
        }

        $targetDepartment = $data['department'];
        $targetFolderId = $data['folder_id'] ?: null;

        if ($targetFolderId) {
            CompanyDocumentFolder::where('department', $targetDepartment)->findOrFail($targetFolderId);
        }

        $uniqueFolderName = function ($department, $parentId, $name, $ignoreId = null) {
            $base = trim((string) $name) ?: 'Folder';
            $candidate = $base;
            $i = 2;

            while (
                CompanyDocumentFolder::where('department', $department)
                    ->where('parent_id', $parentId)
                    ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
                    ->where('name', $candidate)
                    ->exists()
            ) {
                $candidate = $base.' ('.$i.')';
                $i++;
            }

            return $candidate;
        };

        $uniqueFileName = function ($department, $folderId, $name, $ignoreId = null) {
            $base = pathinfo((string) $name, PATHINFO_FILENAME) ?: 'file';
            $ext = pathinfo((string) $name, PATHINFO_EXTENSION);
            $candidate = $base.($ext ? '.'.$ext : '');
            $i = 2;

            while (
                CompanyDocumentFile::where('department', $department)
                    ->where('folder_id', $folderId)
                    ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
                    ->where('original_name', $candidate)
                    ->exists()
            ) {
                $candidate = $base.' ('.$i.')'.($ext ? '.'.$ext : '');
                $i++;
            }

            return $candidate;
        };

        $copyFile = function ($file, $department, $folderId) use ($uniqueFileName) {
            abort_unless(Storage::disk('public')->exists($file->path), 404);

            $newName = $uniqueFileName($department, $folderId, $file->original_name ?: basename($file->path));
            $ext = pathinfo($newName, PATHINFO_EXTENSION);
            $safe = Str::slug(pathinfo($newName, PATHINFO_FILENAME)) ?: 'file';
            $newPath = 'company-documents/'.$department.'/'.now()->format('YmdHis').'_'.Str::random(8).'_'.$safe.($ext ? '.'.$ext : '');

            Storage::disk('public')->copy($file->path, $newPath);

            CompanyDocumentFile::create([
                'department' => $department,
                'folder_id' => $folderId,
                'name' => pathinfo($newName, PATHINFO_FILENAME),
                'original_name' => $newName,
                'path' => $newPath,
                'mime' => $file->mime,
                'size' => $file->size,
                'uploaded_by' => auth()->id(),
            ]);
        };

        $copyFolder = null;
        $copyFolder = function ($folder, $department, $parentId) use (&$copyFolder, $copyFile, $uniqueFolderName) {
            $newFolder = CompanyDocumentFolder::create([
                'department' => $department,
                'parent_id' => $parentId,
                'name' => $uniqueFolderName($department, $parentId, $folder->name),
                'created_by' => auth()->id(),
            ]);

            foreach ($folder->files as $file) {
                $copyFile($file, $department, $newFolder->id);
            }

            foreach ($folder->children as $child) {
                $copyFolder($child, $department, $newFolder->id);
            }
        };

        $syncFolderDepartment = null;
        $syncFolderDepartment = function ($folder, $department) use (&$syncFolderDepartment) {
            CompanyDocumentFile::where('folder_id', $folder->id)->update([
                'department' => $department,
                'updated_at' => now(),
            ]);

            foreach ($folder->children as $child) {
                $child->department = $department;
                $child->save();
                $syncFolderDepartment($child, $department);
            }
        };

        $isSameOrChild = function ($sourceFolderId, $targetFolderId) {
            if (! $targetFolderId) {
                return false;
            }

            $current = CompanyDocumentFolder::find($targetFolderId);

            while ($current) {
                if ((int) $current->id === (int) $sourceFolderId) {
                    return true;
                }

                $current = $current->parent_id ? CompanyDocumentFolder::find($current->parent_id) : null;
            }

            return false;
        };

        if ($clip['object_type'] === 'file') {
            $file = CompanyDocumentFile::findOrFail((int) $clip['object_id']);
            abort_unless(in_array($file->department, $this->allowedDepartments(), true), 403);

            if ($clip['action'] === 'move') {
                $file->department = $targetDepartment;
                $file->folder_id = $targetFolderId;
                $file->original_name = $uniqueFileName($targetDepartment, $targetFolderId, $file->original_name ?: basename($file->path), $file->id);
                $file->name = pathinfo($file->original_name, PATHINFO_FILENAME);
                $file->save();

                session()->forget('company_doc_clipboard');

                return redirect()->route('company-documents.index', ['department' => $targetDepartment, 'folder' => $targetFolderId])
                    ->with('success', 'Đã di chuyển file.');
            }

            $copyFile($file, $targetDepartment, $targetFolderId);

            return redirect()->route('company-documents.index', ['department' => $targetDepartment, 'folder' => $targetFolderId])
                ->with('success', 'Đã sao chép file.');
        }

        $folder = CompanyDocumentFolder::findOrFail((int) $clip['object_id']);
        abort_unless(in_array($folder->department, $this->allowedDepartments(), true), 403);

        if ($clip['action'] === 'move') {
            if ($isSameOrChild($folder->id, $targetFolderId)) {
                return back()->withErrors(['clipboard' => 'Không thể di chuyển folder vào chính nó hoặc folder con của nó.']);
            }

            $folder->department = $targetDepartment;
            $folder->parent_id = $targetFolderId;
            $folder->name = $uniqueFolderName($targetDepartment, $targetFolderId, $folder->name, $folder->id);
            $folder->save();

            $syncFolderDepartment($folder, $targetDepartment);

            session()->forget('company_doc_clipboard');

            return redirect()->route('company-documents.index', ['department' => $targetDepartment, 'folder' => $targetFolderId])
                ->with('success', 'Đã di chuyển folder.');
        }

        $copyFolder($folder, $targetDepartment, $targetFolderId);

        return redirect()->route('company-documents.index', ['department' => $targetDepartment, 'folder' => $targetFolderId])
            ->with('success', 'Đã sao chép folder.');
    }

    /**
     * Danh sách phòng ban người dùng hiện tại được phép truy cập theo role.
     */
    private function allowedDepartments(): array
    {
        $user = auth()->user();
        if (! $user) {
            return [];
        }

        /* EGO_COMPANY_DOCS_ALL_AUTH_ROLES_V2 */
        return array_keys($this->departments);

        if ($this->hasAnyRole($user, ['admin', 'management', 'warehouse', 'kho'])) {
            return array_keys($this->departments);
        }

        $allowed = [];
        foreach ($this->departments as $key => $meta) {
            $roles = $meta['roles'] ?? [];

            if (in_array('*', $roles, true)) {
                $allowed[] = $key;

                continue;
            }

            if ($this->hasAnyRole($user, $roles)) {
                $allowed[] = $key;
            }
        }

        return array_values(array_unique($allowed));
    }

    /**
     * Kiểm tra người dùng có một trong các role (tương thích nhiều cách lưu role).
     */
    private function hasAnyRole($user, array $roles): bool
    {
        if (method_exists($user, 'hasAnyRole')) {
            return $user->hasAnyRole($roles);
        }
        if (method_exists($user, 'hasRole')) {
            foreach ($roles as $role) {
                if ($user->hasRole($role)) {
                    return true;
                }
            }
        }

        return isset($user->role) && in_array((string) $user->role, $roles, true);
    }
}
