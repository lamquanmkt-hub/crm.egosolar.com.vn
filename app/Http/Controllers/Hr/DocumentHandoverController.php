<?php

namespace App\Http\Controllers\Hr;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

class DocumentHandoverController extends Controller
{
    protected array $statuses = [
        'created'   => 'Tạo hồ sơ',
        'assigned'  => 'Giao HS',
        'received'  => 'Nhận HS',
        'sent'      => 'Gửi HS',
        'returned'  => 'Trả HS',
        'completed' => 'Hoàn tất',
        'archived'  => 'Lưu trữ HS',
    ];

    protected array $priorities = [
        'low'    => 'Thấp',
        'normal' => 'Bình thường',
        'high'   => 'Cao',
        'urgent' => 'Gấp',
    ];

    protected array $nextStatuses = [
        'created'   => 'assigned',
        'assigned'  => 'received',
        'received'  => 'sent',
        'sent'      => 'returned',
        'returned'  => 'completed',
        'completed' => 'archived',
    ];

    public function index(Request $request)
    {
        $this->ensureTables();

        $query = DB::table('hr_document_handovers as h')
            ->leftJoin('users as creator', 'creator.id', '=', 'h.created_by')
            ->leftJoin('users as assignee', 'assignee.id', '=', 'h.assigned_to')
            ->select('h.*', 'creator.name as creator_name', 'assignee.name as assignee_name');

        if ($request->filled('q')) {
            $q = trim((string) $request->q);

            $query->where(function ($x) use ($q) {
                $x->where('h.code', 'like', '%' . $q . '%')
                    ->orWhere('h.title', 'like', '%' . $q . '%')
                    ->orWhere('h.customer_name', 'like', '%' . $q . '%')
                    ->orWhere('h.department_name', 'like', '%' . $q . '%')
                    ->orWhere('h.document_type', 'like', '%' . $q . '%');
            });
        }

        if ($request->filled('status') && array_key_exists($request->status, $this->statuses)) {
            $query->where('h.status', $request->status);
        }

        if ($request->filled('priority') && array_key_exists($request->priority, $this->priorities)) {
            $query->where('h.priority', $request->priority);
        }

        $items = $query
            ->orderByRaw("FIELD(h.status, 'created','assigned','received','sent','returned','completed','archived')")
            ->orderByDesc('h.updated_at')
            ->paginate(12)
            ->withQueryString();

        $stats = [
            'total' => DB::table('hr_document_handovers')->count(),
            'processing' => DB::table('hr_document_handovers')->whereNotIn('status', ['completed', 'archived'])->count(),
            'completed' => DB::table('hr_document_handovers')->where('status', 'completed')->count(),
            'archived' => DB::table('hr_document_handovers')->where('status', 'archived')->count(),
        ];

        return view('hr.document-handovers.index', [
            'items' => $items,
            'stats' => $stats,
            'users' => $this->users(),
            'statuses' => $this->statuses,
            'priorities' => $this->priorities,
        ]);
    }

    public function show($id)
    {
        $this->ensureTables();

        $item = DB::table('hr_document_handovers as h')
            ->leftJoin('users as creator', 'creator.id', '=', 'h.created_by')
            ->leftJoin('users as assignee', 'assignee.id', '=', 'h.assigned_to')
            ->select('h.*', 'creator.name as creator_name', 'assignee.name as assignee_name')
            ->where('h.id', (int) $id)
            ->first();

        abort_unless($item, 404);

        $histories = DB::table('hr_document_handover_histories as his')
            ->leftJoin('users as u', 'u.id', '=', 'his.user_id')
            ->select('his.*', 'u.name as user_name')
            ->where('his.handover_id', (int) $id)
            ->orderByDesc('his.created_at')
            ->get();

        $files = DB::table('hr_document_handover_files as f')
            ->leftJoin('users as u', 'u.id', '=', 'f.uploaded_by')
            ->select('f.*', 'u.name as uploader_name')
            ->where('f.handover_id', (int) $id)
            ->orderByDesc('f.created_at')
            ->get();

        return view('hr.document-handovers.show', [
            'item' => $item,
            'histories' => $histories,
            'files' => $files,
            'users' => $this->users(),
            'statuses' => $this->statuses,
            'priorities' => $this->priorities,
            'nextStatuses' => $this->nextStatuses,
        ]);
    }

    public function store(Request $request)
    {
        $this->ensureTables();

        $data = $this->validateMain($request);

        $priority = $data['priority'] ?? 'normal';
        if (!array_key_exists($priority, $this->priorities)) {
            $priority = 'normal';
        }

        $assignedTo = $data['assigned_to'] ?? null;
        $assigneeName = $assignedTo ? $this->userName((int) $assignedTo) : null;

        $id = DB::table('hr_document_handovers')->insertGetId([
            'code' => $this->makeCode(),
            'title' => $data['title'],
            'document_type' => $data['document_type'] ?? null,
            'customer_name' => $data['customer_name'] ?? null,
            'department_name' => $data['department_name'] ?? null,
            'priority' => $priority,
            'status' => 'created',
            'created_by' => Auth::id(),
            'assigned_to' => $assignedTo,
            'current_holder' => $data['current_holder'] ?? $assigneeName,
            'due_date' => $data['due_date'] ?? null,
            'description' => $data['description'] ?? null,
            'note' => $data['note'] ?? null,
            'storage_location' => $data['storage_location'] ?? null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->storeFiles($request, $id);
        $this->history($id, 'created', 'Tạo hồ sơ', $data['note'] ?? null);

        return redirect()
            ->route('hr.document-handovers.show', $id)
            ->with('success', 'Đã tạo hồ sơ mới.');
    }

    public function update(Request $request, $id)
    {
        $this->ensureTables();

        $row = DB::table('hr_document_handovers')->where('id', (int) $id)->first();
        abort_unless($row, 404);

        $data = $this->validateMain($request);

        $priority = $data['priority'] ?? 'normal';
        if (!array_key_exists($priority, $this->priorities)) {
            $priority = 'normal';
        }

        DB::table('hr_document_handovers')
            ->where('id', (int) $id)
            ->update([
                'title' => $data['title'],
                'document_type' => $data['document_type'] ?? null,
                'customer_name' => $data['customer_name'] ?? null,
                'department_name' => $data['department_name'] ?? null,
                'priority' => $priority,
                'assigned_to' => $data['assigned_to'] ?? null,
                'current_holder' => $data['current_holder'] ?? null,
                'due_date' => $data['due_date'] ?? null,
                'description' => $data['description'] ?? null,
                'note' => $data['note'] ?? null,
                'storage_location' => $data['storage_location'] ?? null,
                'updated_at' => now(),
            ]);

        $this->storeFiles($request, (int) $id);
        $this->history((int) $id, $row->status, 'Cập nhật thông tin hồ sơ', $data['note'] ?? null);

        return redirect()
            ->route('hr.document-handovers.show', $id)
            ->with('success', 'Đã cập nhật hồ sơ.');
    }

    public function uploadFiles(Request $request, $id)
    {
        $this->ensureTables();

        $item = DB::table('hr_document_handovers')->where('id', (int) $id)->first();
        abort_unless($item, 404);

        $request->validate([
            'attachments' => ['required', 'array'],
            'attachments.*' => ['file', 'max:51200'],
        ]);

        $this->storeFiles($request, (int) $id);
        $this->history((int) $id, $item->status, 'Thêm file hồ sơ', 'Upload file đính kèm.');

        return redirect()
            ->route('hr.document-handovers.show', $id)
            ->with('success', 'Đã thêm file hồ sơ.');
    }

    public function changeStatus(Request $request, $id)
    {
        $this->ensureTables();

        $row = DB::table('hr_document_handovers')->where('id', (int) $id)->first();
        abort_unless($row, 404);

        $data = $request->validate([
            'status' => ['required', 'string', 'max:50'],
            'note' => ['nullable', 'string'],
            'assigned_to' => ['nullable', 'integer'],
            'current_holder' => ['nullable', 'string', 'max:190'],
            'storage_location' => ['nullable', 'string', 'max:255'],
        ]);

        $status = $data['status'];
        abort_unless(array_key_exists($status, $this->statuses), 422);

        $update = [
            'status' => $status,
            'updated_at' => now(),
        ];

        $timeFields = [
            'assigned' => 'assigned_at',
            'received' => 'received_at',
            'sent' => 'sent_at',
            'returned' => 'returned_at',
            'completed' => 'completed_at',
            'archived' => 'archived_at',
        ];

        if (isset($timeFields[$status])) {
            $update[$timeFields[$status]] = now();
        }

        if ($request->filled('assigned_to')) {
            $update['assigned_to'] = (int) $data['assigned_to'];
            $update['current_holder'] = $this->userName((int) $data['assigned_to']);
        }

        if ($request->filled('current_holder')) {
            $update['current_holder'] = $data['current_holder'];
        }

        if ($request->filled('storage_location')) {
            $update['storage_location'] = $data['storage_location'];
        }

        if ($request->filled('note')) {
            $update['note'] = $data['note'];
        }

        DB::table('hr_document_handovers')
            ->where('id', (int) $id)
            ->update($update);

        $this->history((int) $id, $status, $this->statuses[$status] ?? 'Cập nhật trạng thái', $data['note'] ?? null);

        return redirect()
            ->route('hr.document-handovers.show', $id)
            ->with('success', 'Đã chuyển trạng thái: ' . ($this->statuses[$status] ?? $status));
    }

    public function destroy($id)
    {
        $this->ensureTables();

        $files = DB::table('hr_document_handover_files')->where('handover_id', (int) $id)->get();

        DB::transaction(function () use ($id, $files) {
            foreach ($files as $file) {
                if (!empty($file->path)) {
                    Storage::disk('public')->delete($file->path);
                }
            }

            DB::table('hr_document_handover_files')->where('handover_id', (int) $id)->delete();
            DB::table('hr_document_handover_histories')->where('handover_id', (int) $id)->delete();
            DB::table('hr_document_handovers')->where('id', (int) $id)->delete();
        });

        return redirect()
            ->route('hr.document-handovers.index')
            ->with('success', 'Đã xóa hồ sơ.');
    }

    public function deleteFile($id, $fileId)
    {
        $this->ensureTables();

        $file = DB::table('hr_document_handover_files')
            ->where('id', (int) $fileId)
            ->where('handover_id', (int) $id)
            ->first();

        abort_unless($file, 404);

        if (!empty($file->path)) {
            Storage::disk('public')->delete($file->path);
        }

        DB::table('hr_document_handover_files')->where('id', (int) $fileId)->delete();

        $item = DB::table('hr_document_handovers')->where('id', (int) $id)->first();
        if ($item) {
            $this->history((int) $id, $item->status, 'Xóa file hồ sơ', $file->original_name ?? null);
        }

        return back()->with('success', 'Đã xóa file hồ sơ.');
    }

    public function downloadFile($id, $fileId)
    {
        $this->ensureTables();

        $file = DB::table('hr_document_handover_files')
            ->where('id', (int) $fileId)
            ->where('handover_id', (int) $id)
            ->first();

        abort_unless($file, 404);
        abort_unless(Storage::disk('public')->exists($file->path), 404);

        return Storage::disk('public')->download($file->path, $file->original_name ?: basename($file->path));
    }

    public function previewFile($id, $fileId)
    {
        $this->ensureTables();

        $file = DB::table('hr_document_handover_files')
            ->where('id', (int) $fileId)
            ->where('handover_id', (int) $id)
            ->first();

        abort_unless($file, 404);
        abort_unless(Storage::disk('public')->exists($file->path), 404);

        $real = Storage::disk('public')->path($file->path);
        $name = $file->original_name ?: basename($file->path);
        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        $mime = $file->mime_type ?: (@mime_content_type($real) ?: 'application/octet-stream');

        $downloadUrl = route('hr.document-handovers.files.download', [$id, $fileId]);
        $header = '<div class="pv-top"><strong>' . e($name) . '</strong><a href="' . e($downloadUrl) . '">Tải xuống</a></div>';

        if (str_starts_with($mime, 'image/') || in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'gif', 'svg'], true)) {
            $data = base64_encode(file_get_contents($real));
            return response($this->previewPage($name, $header . '<div class="pv-body pv-img-wrap"><img src="data:' . e($mime) . ';base64,' . $data . '"></div>'));
        }

        if ($ext === 'pdf' || $mime === 'application/pdf') {
            $data = base64_encode(file_get_contents($real));
            return response($this->previewPage($name, $header . '<iframe src="data:application/pdf;base64,' . $data . '#toolbar=1"></iframe>'));
        }

        if (in_array($ext, ['txt', 'log', 'csv'], true)) {
            $text = htmlspecialchars((string) file_get_contents($real), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            return response($this->previewPage($name, $header . '<div class="pv-body"><pre>' . $text . '</pre></div>'));
        }

        return response($this->previewPage($name, $header . '<div class="pv-body"><div class="pv-notice">Định dạng này chưa hỗ trợ xem trước. Anh bấm Tải xuống để mở file.</div></div>'));
    }

    protected function validateMain(Request $request): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'document_type' => ['nullable', 'string', 'max:120'],
            'customer_name' => ['nullable', 'string', 'max:190'],
            'department_name' => ['nullable', 'string', 'max:190'],
            'priority' => ['nullable', 'string', 'max:50'],
            'assigned_to' => ['nullable', 'integer'],
            'current_holder' => ['nullable', 'string', 'max:190'],
            'due_date' => ['nullable', 'date'],
            'description' => ['nullable', 'string'],
            'note' => ['nullable', 'string'],
            'storage_location' => ['nullable', 'string', 'max:255'],
            'attachments' => ['nullable', 'array'],
            'attachments.*' => ['file', 'max:51200'],
        ]);
    }

    protected function storeFiles(Request $request, int $handoverId): void
    {
        if (!$request->hasFile('attachments')) {
            return;
        }

        foreach ((array) $request->file('attachments') as $file) {
            if (!$file || !$file->isValid()) {
                continue;
            }

            $path = $file->store('hr-document-handovers/' . $handoverId, 'public');

            DB::table('hr_document_handover_files')->insert([
                'handover_id' => $handoverId,
                'path' => $path,
                'original_name' => $file->getClientOriginalName(),
                'mime_type' => $file->getClientMimeType(),
                'size' => $file->getSize(),
                'uploaded_by' => Auth::id(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    protected function ensureTables(): void
    {
        if (!Schema::hasTable('hr_document_handovers')) {
            Schema::create('hr_document_handovers', function ($table) {
                $table->id();
                $table->string('code', 80)->nullable()->index();
                $table->string('title', 255);
                $table->string('document_type', 120)->nullable();
                $table->string('customer_name', 190)->nullable();
                $table->string('department_name', 190)->nullable();
                $table->string('priority', 50)->default('normal')->index();
                $table->string('status', 50)->default('created')->index();
                $table->unsignedBigInteger('created_by')->nullable()->index();
                $table->unsignedBigInteger('assigned_to')->nullable()->index();
                $table->string('current_holder', 190)->nullable();
                $table->date('due_date')->nullable();
                $table->text('description')->nullable();
                $table->text('note')->nullable();
                $table->string('storage_location', 255)->nullable();
                $table->timestamp('assigned_at')->nullable();
                $table->timestamp('received_at')->nullable();
                $table->timestamp('sent_at')->nullable();
                $table->timestamp('returned_at')->nullable();
                $table->timestamp('completed_at')->nullable();
                $table->timestamp('archived_at')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('hr_document_handover_histories')) {
            Schema::create('hr_document_handover_histories', function ($table) {
                $table->id();
                $table->unsignedBigInteger('handover_id')->index();
                $table->string('status', 50)->nullable()->index();
                $table->string('action', 120)->nullable();
                $table->text('note')->nullable();
                $table->unsignedBigInteger('user_id')->nullable()->index();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('hr_document_handover_files')) {
            Schema::create('hr_document_handover_files', function ($table) {
                $table->id();
                $table->unsignedBigInteger('handover_id')->index();
                $table->string('path', 500);
                $table->string('original_name', 255)->nullable();
                $table->string('mime_type', 190)->nullable();
                $table->unsignedBigInteger('size')->nullable();
                $table->unsignedBigInteger('uploaded_by')->nullable()->index();
                $table->timestamps();
            });
        }
    }

    protected function makeCode(): string
    {
        $prefix = 'HS-' . now()->format('Y') . '-';
        $count = DB::table('hr_document_handovers')->where('code', 'like', $prefix . '%')->count() + 1;
        return $prefix . str_pad((string) $count, 5, '0', STR_PAD_LEFT);
    }

    protected function history(int $id, string $status, string $action, ?string $note = null): void
    {
        DB::table('hr_document_handover_histories')->insert([
            'handover_id' => $id,
            'status' => $status,
            'action' => $action,
            'note' => $note,
            'user_id' => Auth::id(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    protected function users()
    {
        return Schema::hasTable('users')
            ? DB::table('users')->select('id', 'name', 'email')->orderBy('name')->get()
            : collect();
    }

    protected function userName(int $id): ?string
    {
        return Schema::hasTable('users')
            ? DB::table('users')->where('id', $id)->value('name')
            : null;
    }

    protected function previewPage(string $title, string $body): string
    {
        return '<!doctype html><html><head><meta charset="utf-8"><title>' . e($title) . '</title>
        <style>
            *{box-sizing:border-box}
            html,body{margin:0;min-height:100%;font-family:Arial,"DejaVu Sans",sans-serif;background:#f8fafc;color:#0f172a}
            iframe{width:100%;height:calc(100vh - 54px);border:0;background:#fff}
            .pv-top{height:54px;display:flex;align-items:center;justify-content:space-between;gap:12px;padding:10px 14px;background:linear-gradient(90deg,#0f3b78,#0891b2);color:#fff}
            .pv-top strong{font-size:14px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
            .pv-top a{border:1px solid rgba(255,255,255,.35);border-radius:10px;padding:8px 12px;color:#fff;text-decoration:none;font-weight:900;font-size:13px;background:rgba(255,255,255,.13)}
            .pv-body{padding:16px;overflow:auto}
            .pv-img-wrap{display:flex;align-items:center;justify-content:center;min-height:calc(100vh - 54px)}
            .pv-img-wrap img{max-width:min(100%,920px);max-height:calc(100vh - 90px);object-fit:contain;border-radius:14px;box-shadow:0 18px 45px rgba(15,23,42,.16);background:#fff}
            pre{white-space:pre-wrap;background:#fff;border:1px solid #dbe3ef;border-radius:14px;padding:16px;line-height:1.6}
            .pv-notice{max-width:680px;margin:70px auto;padding:24px;border-radius:18px;border:1px solid #dbe3ef;background:#fff;text-align:center;color:#475569;font-weight:800}
        </style></head><body>' . $body . '</body></html>';
    }
}
