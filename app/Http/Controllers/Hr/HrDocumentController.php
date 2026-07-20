<?php

namespace App\Http\Controllers\Hr;

use App\Http\Controllers\Controller;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Controller quản lý dữ liệu vận hành HC (nhóm, trạng thái, item) và kho tài liệu / hồ sơ theo folder.
 */
class HrDocumentController extends Controller
{
    /**
     * Hiển thị trang dữ liệu vận hành: item theo nhóm, trạng thái và tổng tiền.
     */
    public function operations()
    {
        $this->ensureOperationItemsTable();
        $this->ensureOperationStatusesTable();

        $groups = $this->operationGroups();
        $statuses = $this->operationStatuses();

        $items = DB::table('hr_operation_items')
            ->orderByDesc('id')
            ->get();

        $groupedItems = $items->groupBy('group_key');
        $statusCounts = $items->groupBy('status')->map->count();

        return view('hr.operations.index', [
            'stats' => $this->stats(true),
            'groups' => $groups,
            'statuses' => $statuses,
            'items' => $items,
            'groupedItems' => $groupedItems,
            'statusCounts' => $statusCounts,
            'totalAmount' => (float) $items->sum('amount'),
            'operationGroupsRaw' => DB::table('hr_operation_groups')->orderBy('sort_order')->orderBy('id')->get(),
            'operationStatusesRaw' => DB::table('hr_operation_statuses')->orderBy('sort_order')->orderBy('id')->get(),
        ]);
    }

    /**
     * Thêm mới một dòng dữ liệu vận hành sau khi kiểm tra nhóm / trạng thái hợp lệ.
     */
    public function storeOperationItem(Request $request)
    {
        $this->ensureOperationItemsTable();

        $groups = $this->operationGroups();

        $data = $request->validate([
            'group_key' => ['required', 'string', 'max:80'],
            'title' => ['required', 'string', 'max:190'],
            'amount' => ['nullable'],
            'status' => ['nullable', 'string', 'max:50'],
            'owner' => ['nullable', 'string', 'max:190'],
            'due_date' => ['nullable', 'date'],
            'note' => ['nullable', 'string', 'max:2000'],
        ]);

        $statuses = $this->operationStatuses();

        abort_unless(array_key_exists($data['group_key'], $groups), 422);
        abort_unless(array_key_exists($data['status'] ?: 'new', $statuses), 422);

        DB::table('hr_operation_items')->insert([
            'group_key' => $data['group_key'],
            'title' => trim($data['title']),
            'amount' => $this->moneyToNumber($data['amount'] ?? 0),
            'status' => $data['status'] ?: 'new',
            'owner' => $data['owner'] ?? null,
            'due_date' => $data['due_date'] ?? null,
            'note' => $data['note'] ?? null,
            'created_by' => auth()->id(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return back()->with('success', 'Đã thêm dữ liệu vận hành.');
    }

    /**
     * Cập nhật một dòng dữ liệu vận hành theo ID.
     *
     * @param  int  $item  ID dòng dữ liệu
     */
    public function updateOperationItem(Request $request, int $item)
    {
        $this->ensureOperationItemsTable();

        $groups = $this->operationGroups();

        $data = $request->validate([
            'group_key' => ['required', 'string', 'max:80'],
            'title' => ['required', 'string', 'max:190'],
            'amount' => ['nullable'],
            'status' => ['nullable', 'string', 'max:50'],
            'owner' => ['nullable', 'string', 'max:190'],
            'due_date' => ['nullable', 'date'],
            'note' => ['nullable', 'string', 'max:2000'],
        ]);

        $statuses = $this->operationStatuses();

        abort_unless(array_key_exists($data['group_key'], $groups), 422);
        abort_unless(array_key_exists($data['status'] ?: 'new', $statuses), 422);

        DB::table('hr_operation_items')
            ->where('id', $item)
            ->update([
                'group_key' => $data['group_key'],
                'title' => trim($data['title']),
                'amount' => $this->moneyToNumber($data['amount'] ?? 0),
                'status' => $data['status'] ?: 'new',
                'owner' => $data['owner'] ?? null,
                'due_date' => $data['due_date'] ?? null,
                'note' => $data['note'] ?? null,
                'updated_at' => now(),
            ]);

        return back()->with('success', 'Đã cập nhật dữ liệu vận hành.');
    }

    /**
     * Xoá một dòng dữ liệu vận hành theo ID.
     *
     * @param  int  $item  ID dòng dữ liệu
     */
    public function deleteOperationItem(int $item)
    {
        $this->ensureOperationItemsTable();

        DB::table('hr_operation_items')->where('id', $item)->delete();

        return back()->with('success', 'Đã xóa dữ liệu vận hành.');
    }

    /**
     * Hiển thị trang kho hồ sơ nhân sự (category records) theo folder.
     */
    public function records()
    {
        return view('hr.records.index', [
            'stats' => $this->stats(false),
            'category' => 'records',
            'folders' => $this->folders('records'),
        ]);
    }

    /**
     * Tạo folder tài liệu mới trong một category (operations / records).
     */
    public function storeFolder(Request $request, string $category)
    {
        $this->checkCategory($category);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:190'],
        ]);

        DB::table('hr_document_folders')->insert([
            'category' => $category,
            'name' => trim($data['name']),
            'created_by' => auth()->id(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return back()->with('success', 'Đã tạo folder.');
    }

    /**
     * Upload file vào folder tài liệu của category, lưu với tên an toàn.
     */
    public function uploadFile(Request $request, string $category)
    {
        $this->checkCategory($category);

        $data = $request->validate([
            'folder_id' => ['required', 'integer'],
            'file' => ['required', 'file'],
        ]);

        $folder = DB::table('hr_document_folders')
            ->where('id', (int) $data['folder_id'])
            ->where('category', $category)
            ->first();

        abort_unless($folder, 404);

        $file = $request->file('file');
        $original = $file->getClientOriginalName();
        $ext = $file->getClientOriginalExtension();
        $safeName = now()->format('YmdHis').'_'.Str::random(12).($ext ? '.'.$ext : '');

        $path = $file->storeAs('public/hr-documents/'.$category.'/'.$folder->id, $safeName);

        DB::table('hr_document_files')->insert([
            'folder_id' => $folder->id,
            'category' => $category,
            'original_name' => $original,
            'stored_path' => $path,
            'mime_type' => $file->getClientMimeType(),
            'size_bytes' => $file->getSize(),
            'uploaded_by' => auth()->id(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return back()->with('success', 'Đã upload file.');
    }

    /**
     * Tải xuống file tài liệu theo ID.
     *
     * @param  int  $file  ID file
     */
    public function downloadFile(int $file)
    {
        $row = DB::table('hr_document_files')->where('id', $file)->first();

        abort_unless($row, 404);
        abort_unless(Storage::exists($row->stored_path), 404);

        return Storage::download($row->stored_path, $row->original_name);
    }

    /**
     * Xoá file tài liệu (cả file vật lý và bản ghi DB).
     *
     * @param  int  $file  ID file
     */
    public function deleteFile(int $file)
    {
        $row = DB::table('hr_document_files')->where('id', $file)->first();

        abort_unless($row, 404);

        if (! empty($row->stored_path) && Storage::exists($row->stored_path)) {
            Storage::delete($row->stored_path);
        }

        DB::table('hr_document_files')->where('id', $file)->delete();

        return back()->with('success', 'Đã xóa file.');
    }

    /**
     * Xoá folder tài liệu cùng toàn bộ file bên trong.
     *
     * @param  int  $folder  ID folder
     */
    public function deleteFolder(int $folder)
    {
        $files = DB::table('hr_document_files')->where('folder_id', $folder)->get();

        foreach ($files as $file) {
            if (! empty($file->stored_path) && Storage::exists($file->stored_path)) {
                Storage::delete($file->stored_path);
            }
        }

        DB::table('hr_document_files')->where('folder_id', $folder)->delete();
        DB::table('hr_document_folders')->where('id', $folder)->delete();

        return back()->with('success', 'Đã xóa folder.');
    }

    /**
     * Lấy danh sách folder của category kèm file bên trong mỗi folder.
     */
    private function folders(string $category)
    {
        if (! Schema::hasTable('hr_document_folders') || ! Schema::hasTable('hr_document_files')) {
            return collect();
        }

        $folders = DB::table('hr_document_folders')
            ->where('category', $category)
            ->orderByDesc('id')
            ->get();

        $files = DB::table('hr_document_files')
            ->where('category', $category)
            ->orderByDesc('id')
            ->get()
            ->groupBy('folder_id');

        return $folders->map(function ($folder) use ($files) {
            $folder->files = $files->get($folder->id, collect());

            return $folder;
        });
    }

    /**
     * Tính thống kê chung (số nhân viên, phòng ban, chức vụ, chấm công hôm nay) cho các trang tài liệu.
     *
     * @param  bool  $attendance  Có tính số liệu chấm công hôm nay hay không
     */
    private function stats(bool $attendance = false): array
    {
        $stats = [
            'employees' => 0,
            'departments' => 0,
            'positions' => 0,
            'attendance_today' => 0,
        ];

        try {
            if (Schema::hasTable('users')) {
                $q = DB::table('users');

                if (Schema::hasColumn('users', 'deleted_at')) {
                    $q->whereNull('deleted_at');
                }

                if ($attendance && Schema::hasColumn('users', 'is_active')) {
                    $q->where('is_active', 1);
                }

                $stats['employees'] = (int) $q->count();
            }

            if (Schema::hasTable('departments')) {
                $stats['departments'] = (int) DB::table('departments')->count();
            }

            if (Schema::hasTable('positions')) {
                $stats['positions'] = (int) DB::table('positions')->count();
            }

            if ($attendance && Schema::hasTable('attendance_records') && Schema::hasColumn('attendance_records', 'work_date')) {
                $stats['attendance_today'] = (int) DB::table('attendance_records')
                    ->whereDate('work_date', now()->toDateString())
                    ->count();
            }
        } catch (\Throwable $e) {
        }

        return $stats;
    }

    /**
     * Thêm nhóm dữ liệu vận hành mới với group_key duy nhất.
     */
    public function storeOperationGroup(Request $request)
    {
        $this->ensureOperationGroupsTable();

        $data = $request->validate([
            'name' => ['required', 'string', 'max:190'],
        ]);

        $name = trim($data['name']);
        $baseKey = $this->makeGroupKey($name);
        $key = $baseKey;
        $i = 2;

        while (DB::table('hr_operation_groups')->where('group_key', $key)->exists()) {
            $key = $baseKey.'_'.$i;
            $i++;
        }

        $maxSort = (int) DB::table('hr_operation_groups')->max('sort_order');

        DB::table('hr_operation_groups')->insert([
            'group_key' => $key,
            'name' => $name,
            'sort_order' => $maxSort + 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return back()->with('success', 'Đã thêm nhóm dữ liệu.');
    }

    /**
     * Cập nhật tên / thứ tự nhóm dữ liệu vận hành.
     *
     * @param  int  $group  ID nhóm
     */
    public function updateOperationGroup(Request $request, int $group)
    {
        $this->ensureOperationGroupsTable();

        $data = $request->validate([
            'name' => ['required', 'string', 'max:190'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ]);

        DB::table('hr_operation_groups')
            ->where('id', $group)
            ->update([
                'name' => trim($data['name']),
                'sort_order' => (int) ($data['sort_order'] ?? 0),
                'updated_at' => now(),
            ]);

        return back()->with('success', 'Đã cập nhật nhóm dữ liệu.');
    }

    /**
     * Xoá nhóm dữ liệu vận hành nếu không còn dữ liệu sử dụng.
     *
     * @param  int  $group  ID nhóm
     */
    public function deleteOperationGroup(int $group)
    {
        $this->ensureOperationGroupsTable();
        $this->ensureOperationItemsTable();

        $row = DB::table('hr_operation_groups')->where('id', $group)->first();
        abort_unless($row, 404);

        $used = DB::table('hr_operation_items')
            ->where('group_key', $row->group_key)
            ->exists();

        if ($used) {
            return back()->withErrors(['group' => 'Không thể xóa nhóm đang có dữ liệu. Hãy xóa/chuyển dữ liệu trong nhóm trước.']);
        }

        DB::table('hr_operation_groups')->where('id', $group)->delete();

        return back()->with('success', 'Đã xóa nhóm dữ liệu.');
    }

    /**
     * Thêm trạng thái dữ liệu vận hành mới với status_key duy nhất và màu hợp lệ.
     */
    public function storeOperationStatus(Request $request)
    {
        $this->ensureOperationStatusesTable();

        $data = $request->validate([
            'name' => ['required', 'string', 'max:190'],
            'color' => ['nullable', 'string', 'max:30'],
        ]);

        $name = trim($data['name']);
        $baseKey = $this->makeGroupKey($name);
        $key = $baseKey;
        $i = 2;

        while (DB::table('hr_operation_statuses')->where('status_key', $key)->exists()) {
            $key = $baseKey.'_'.$i;
            $i++;
        }

        $maxSort = (int) DB::table('hr_operation_statuses')->max('sort_order');

        DB::table('hr_operation_statuses')->insert([
            'status_key' => $key,
            'name' => $name,
            'color' => $this->safeStatusColor($data['color'] ?? 'slate'),
            'sort_order' => $maxSort + 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return back()->with('success', 'Đã thêm trạng thái dữ liệu.');
    }

    /**
     * Cập nhật tên / màu / thứ tự trạng thái dữ liệu vận hành.
     *
     * @param  int  $status  ID trạng thái
     */
    public function updateOperationStatus(Request $request, int $status)
    {
        $this->ensureOperationStatusesTable();

        $data = $request->validate([
            'name' => ['required', 'string', 'max:190'],
            'color' => ['nullable', 'string', 'max:30'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ]);

        DB::table('hr_operation_statuses')
            ->where('id', $status)
            ->update([
                'name' => trim($data['name']),
                'color' => $this->safeStatusColor($data['color'] ?? 'slate'),
                'sort_order' => (int) ($data['sort_order'] ?? 0),
                'updated_at' => now(),
            ]);

        return back()->with('success', 'Đã cập nhật trạng thái dữ liệu.');
    }

    /**
     * Xoá trạng thái dữ liệu vận hành nếu không còn dữ liệu sử dụng.
     *
     * @param  int  $status  ID trạng thái
     */
    public function deleteOperationStatus(int $status)
    {
        $this->ensureOperationStatusesTable();
        $this->ensureOperationItemsTable();

        $row = DB::table('hr_operation_statuses')->where('id', $status)->first();
        abort_unless($row, 404);

        $used = DB::table('hr_operation_items')
            ->where('status', $row->status_key)
            ->exists();

        if ($used) {
            return back()->withErrors(['status' => 'Không thể xóa trạng thái đang được sử dụng. Hãy chuyển dữ liệu sang trạng thái khác trước.']);
        }

        DB::table('hr_operation_statuses')->where('id', $status)->delete();

        return back()->with('success', 'Đã xóa trạng thái dữ liệu.');
    }

    /**
     * Lấy danh sách nhóm dữ liệu vận hành (group_key => tên), tự seed mặc định nếu trống.
     *
     * @return array<string, string>
     */
    private function operationGroups(): array
    {
        $this->ensureOperationGroupsTable();

        $rows = DB::table('hr_operation_groups')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        if ($rows->isEmpty()) {
            $this->seedOperationGroups();

            $rows = DB::table('hr_operation_groups')
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get();
        }

        return $rows->pluck('name', 'group_key')->toArray();
    }

    /**
     * Lấy danh sách trạng thái dữ liệu vận hành (status_key => tên), tự seed mặc định nếu trống.
     *
     * @return array<string, string>
     */
    private function operationStatuses(): array
    {
        $this->ensureOperationStatusesTable();

        $rows = DB::table('hr_operation_statuses')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        if ($rows->isEmpty()) {
            $this->seedOperationStatuses();

            $rows = DB::table('hr_operation_statuses')
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get();
        }

        return $rows->pluck('name', 'status_key')->toArray();
    }

    /**
     * Tạo bảng hr_operation_items nếu chưa tồn tại.
     */
    private function ensureOperationItemsTable(): void
    {
        if (Schema::hasTable('hr_operation_items')) {
            return;
        }

        Schema::create('hr_operation_items', function (Blueprint $table) {
            $table->id();
            $table->string('group_key', 80)->index();
            $table->string('title');
            $table->decimal('amount', 18, 2)->default(0);
            $table->string('status', 50)->default('new')->index();
            $table->string('owner')->nullable();
            $table->date('due_date')->nullable()->index();
            $table->text('note')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Tạo bảng hr_operation_groups nếu chưa có và seed nhóm mặc định khi trống.
     */
    private function ensureOperationGroupsTable(): void
    {
        if (! Schema::hasTable('hr_operation_groups')) {
            Schema::create('hr_operation_groups', function (Blueprint $table) {
                $table->id();
                $table->string('group_key', 80)->unique();
                $table->string('name', 190);
                $table->unsignedInteger('sort_order')->default(0);
                $table->timestamps();
            });
        }

        if (DB::table('hr_operation_groups')->count() === 0) {
            $this->seedOperationGroups();
        }
    }

    /**
     * Tạo bảng hr_operation_statuses nếu chưa có, bổ sung cột color và seed trạng thái mặc định khi trống.
     */
    private function ensureOperationStatusesTable(): void
    {
        if (! Schema::hasTable('hr_operation_statuses')) {
            Schema::create('hr_operation_statuses', function (Blueprint $table) {
                $table->id();
                $table->string('status_key', 80)->unique();
                $table->string('name', 190);
                $table->string('color', 30)->default('slate');
                $table->unsignedInteger('sort_order')->default(0);
                $table->timestamps();
            });
        }

        if (Schema::hasTable('hr_operation_statuses') && ! Schema::hasColumn('hr_operation_statuses', 'color')) {
            Schema::table('hr_operation_statuses', function (Blueprint $table) {
                $table->string('color', 30)->default('slate')->after('name');
            });
        }

        if (DB::table('hr_operation_statuses')->count() === 0) {
            $this->seedOperationStatuses();
        }
    }

    /**
     * Seed các trạng thái vận hành mặc định (Mới, Đang xử lý, Hoàn thành, Hủy).
     */
    private function seedOperationStatuses(): void
    {
        $defaults = [
            ['status_key' => 'new', 'name' => 'Mới', 'color' => 'cyan', 'sort_order' => 1],
            ['status_key' => 'processing', 'name' => 'Đang xử lý', 'color' => 'amber', 'sort_order' => 2],
            ['status_key' => 'done', 'name' => 'Hoàn thành', 'color' => 'emerald', 'sort_order' => 3],
            ['status_key' => 'cancelled', 'name' => 'Hủy', 'color' => 'rose', 'sort_order' => 4],
        ];

        foreach ($defaults as $row) {
            if (! DB::table('hr_operation_statuses')->where('status_key', $row['status_key'])->exists()) {
                DB::table('hr_operation_statuses')->insert([
                    'status_key' => $row['status_key'],
                    'name' => $row['name'],
                    'color' => $row['color'],
                    'sort_order' => $row['sort_order'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    /**
     * Seed các nhóm dữ liệu vận hành mặc định (chi phí, tài sản, NCC, công việc HC).
     */
    private function seedOperationGroups(): void
    {
        $defaults = [
            ['group_key' => 'office_expense', 'name' => 'Chi phí văn phòng', 'sort_order' => 1],
            ['group_key' => 'asset', 'name' => 'Quản lý tài sản', 'sort_order' => 2],
            ['group_key' => 'supplier', 'name' => 'Quản lý đơn vị cung cấp', 'sort_order' => 3],
            ['group_key' => 'admin_task', 'name' => 'Công việc HC vận hành', 'sort_order' => 4],
        ];

        foreach ($defaults as $row) {
            if (! DB::table('hr_operation_groups')->where('group_key', $row['group_key'])->exists()) {
                DB::table('hr_operation_groups')->insert([
                    'group_key' => $row['group_key'],
                    'name' => $row['name'],
                    'sort_order' => $row['sort_order'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    /**
     * Sinh key dạng slug (a-z0-9_) từ tên nhóm / trạng thái.
     */
    private function makeGroupKey(string $name): string
    {
        $key = Str::slug($name, '_');
        $key = preg_replace('/[^a-z0-9_]/', '', strtolower($key));
        $key = trim($key, '_');

        return $key !== '' ? substr($key, 0, 70) : 'group_'.time();
    }

    /**
     * Chuyển chuỗi tiền tệ (có đ, dấu phẩy, khoảng trắng) về số float.
     *
     * @param  mixed  $value  Giá trị tiền nhập vào
     */
    private function moneyToNumber($value): float
    {
        if (is_numeric($value)) {
            return (float) $value;
        }

        $value = trim((string) $value);
        $value = str_replace(['đ', ' ', ','], ['', '', ''], $value);
        $value = preg_replace('/[^0-9.]/', '', $value);

        return $value === '' ? 0 : (float) $value;
    }

    /**
     * Chuẩn hoá màu trạng thái về danh sách cho phép, mặc định 'slate'.
     */
    private function safeStatusColor(?string $color): string
    {
        $color = trim((string) $color);
        $allowed = ['slate', 'blue', 'cyan', 'amber', 'emerald', 'rose', 'violet'];

        return in_array($color, $allowed, true) ? $color : 'slate';
    }

    /**
     * Kiểm tra category hợp lệ (operations / records), abort 404 nếu sai.
     */
    private function checkCategory(string $category): void
    {
        abort_unless(in_array($category, ['operations', 'records'], true), 404);
    }
}
