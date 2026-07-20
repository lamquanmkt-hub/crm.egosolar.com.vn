<?php

namespace App\Http\Controllers\Projects;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

/**
 * Controller quản lý đề xuất nội bộ: tạo, duyệt, từ chối, xóa.
 */
class ProposalController extends Controller
{
    /**
     * Kiểm tra bảng có tồn tại trong database.
     */
    private function tableExists(string $table): bool
    {
        return Schema::hasTable($table);
    }

    /**
     * Lọc dữ liệu chỉ giữ các cột có trong bảng.
     */
    private function filterColumns(string $table, array $data): array
    {
        if (! Schema::hasTable($table)) {
            return $data;
        }

        return collect($data)
            ->only(Schema::getColumnListing($table))
            ->toArray();
    }

    /**
     * Kiểm tra người dùng có quyền duyệt đề xuất.
     */
    private function isApprover(): bool
    {
        $user = auth()->user();

        if (! $user) {
            return false;
        }

        if (method_exists($user, 'hasAnyRole')) {
            return $user->hasAnyRole([
                'admin',
                'manager',
                'accounting',
                'sales_manager',
                'marketing_manager',
            ]);
        }

        return false;
    }

    /**
     * Danh sách loại đề xuất.
     */
    private function types(): array
    {
        return [
            'mua_sam' => 'Mua sắm',
            'tam_ung' => 'Tạm ứng',
            'sua_chua' => 'Sửa chữa',
            'nhan_su' => 'Nhân sự',
            'cong_viec' => 'Công việc',
            'quy_trinh' => 'Quy trình',
            'khac' => 'Khác',
        ];
    }

    /**
     * Danh sách mức độ ưu tiên.
     */
    private function priorities(): array
    {
        return [
            'low' => 'Thấp',
            'normal' => 'Bình thường',
            'high' => 'Cao',
            'urgent' => 'Gấp',
        ];
    }

    /**
     * Lấy tên phòng ban của người dùng hiện tại.
     */
    private function currentUserDepartment(): string
    {
        $user = auth()->user();

        if (! $user) {
            return '';
        }

        if (isset($user->department) && is_string($user->department)) {
            return $user->department;
        }

        if (Schema::hasTable('departments') && Schema::hasColumn('users', 'department_id')) {
            $department = DB::table('users')
                ->leftJoin('departments', 'departments.id', '=', 'users.department_id')
                ->where('users.id', $user->id)
                ->select('departments.name')
                ->first();

            return $department->name ?? '';
        }

        return '';
    }

    /**
     * Hiển thị danh sách đề xuất kèm bộ lọc và thống kê.
     */
    public function index(Request $request)
    {
        if (! $this->tableExists('proposals')) {
            return back()->with('error', 'Chưa có bảng proposals trong database.');
        }

        $types = $this->types();
        $priorities = $this->priorities();
        $canApprove = $this->isApprover();

        $query = DB::table('proposals')
            ->leftJoin('users', 'users.id', '=', 'proposals.user_id')
            ->select(
                'proposals.*',
                DB::raw('COALESCE(users.name, "Không rõ") as employee_name')
            )
            ->orderByDesc('proposals.id');

        if (! $canApprove) {
            $query->where('proposals.user_id', auth()->id());
        }

        if ($request->filled('status')) {
            $query->where('proposals.status', $request->status);
        }

        if ($request->filled('type')) {
            $query->where('proposals.proposal_type', $request->type);
        }

        if ($request->filled('priority')) {
            $query->where('proposals.priority', $request->priority);
        }

        if ($request->filled('q')) {
            $q = trim($request->q);

            $query->where(function ($sub) use ($q) {
                $sub->where('proposals.title', 'like', "%{$q}%")
                    ->orWhere('proposals.content', 'like', "%{$q}%")
                    ->orWhere('proposals.reason', 'like', "%{$q}%")
                    ->orWhere('users.name', 'like', "%{$q}%");
            });
        }

        $proposals = $query->limit(300)->get();

        $summaryQuery = DB::table('proposals');

        if (! $canApprove) {
            $summaryQuery->where('user_id', auth()->id());
        }

        $summaryData = $summaryQuery->get();

        $summary = [
            'total' => $summaryData->count(),
            'pending' => $summaryData->where('status', 'pending')->count(),
            'approved' => $summaryData->where('status', 'approved')->count(),
            'rejected' => $summaryData->where('status', 'rejected')->count(),
            'amount_pending' => $summaryData->where('status', 'pending')->sum('amount'),
        ];

        return view('proposals.index', compact(
            'proposals',
            'summary',
            'types',
            'priorities',
            'canApprove'
        ));
    }

    /**
     * Hiển thị form tạo đề xuất.
     */
    public function create()
    {
        return view('proposals.create', [
            'types' => $this->types(),
            'priorities' => $this->priorities(),
            'departmentName' => $this->currentUserDepartment(),
        ]);
    }

    /**
     * Lưu đề xuất mới kèm file đính kèm.
     */
    public function store(Request $request)
    {
        if (! $this->tableExists('proposals')) {
            return back()->with('error', 'Chưa có bảng proposals trong database.');
        }

        $request->validate([
            'title' => 'required|string|max:255',
            'proposal_type' => 'nullable|string|max:100',
            'priority' => 'nullable|string|max:30',
            'department_name' => 'nullable|string|max:255',
            'amount' => 'nullable|numeric|min:0',
            'needed_date' => 'nullable|date',
            'content' => 'nullable|string',
            'reason' => 'nullable|string',
            'expected_result' => 'nullable|string',
            'attachments.*' => 'nullable|file',
        ]);

        $data = [
            'user_id' => auth()->id(),
            'title' => $request->title,
            'proposal_type' => $request->proposal_type ?: 'khac',
            'priority' => $request->priority ?: 'normal',
            'department_name' => $request->department_name ?: $this->currentUserDepartment(),
            'amount' => (float) $request->input('amount', 0),
            'needed_date' => $request->needed_date,
            'content' => $request->content,
            'reason' => $request->reason,
            'expected_result' => $request->expected_result,
            'status' => 'pending',
            'created_at' => now(),
            'updated_at' => now(),
        ];

        $proposalId = DB::table('proposals')
            ->insertGetId($this->filterColumns('proposals', $data));

        if ($request->hasFile('attachments') && $this->tableExists('proposal_attachments')) {
            foreach ($request->file('attachments') as $file) {
                if (! $file) {
                    continue;
                }

                $path = $file->store('proposal_attachments/'.$proposalId, 'public');

                DB::table('proposal_attachments')->insert([
                    'proposal_id' => $proposalId,
                    'uploaded_by' => auth()->id(),
                    'file_name' => $file->getClientOriginalName(),
                    'file_path' => $path,
                    'file_mime' => $file->getClientMimeType(),
                    'file_size' => $file->getSize(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        return redirect()
            ->route('de-xuat.show', $proposalId)
            ->with('success', 'Đã gửi đề xuất cho sếp duyệt.');
    }

    /**
     * Hiển thị chi tiết đề xuất và file đính kèm.
     */
    public function show($id)
    {
        if (! $this->tableExists('proposals')) {
            abort(404);
        }

        $proposal = DB::table('proposals')
            ->leftJoin('users', 'users.id', '=', 'proposals.user_id')
            ->leftJoin('users as approver', 'approver.id', '=', 'proposals.approved_by')
            ->where('proposals.id', $id)
            ->select(
                'proposals.*',
                DB::raw('COALESCE(users.name, "Không rõ") as employee_name'),
                DB::raw('COALESCE(approver.name, "") as approved_name')
            )
            ->first();

        abort_if(! $proposal, 404);

        $canApprove = $this->isApprover();

        if (! $canApprove && (int) $proposal->user_id !== (int) auth()->id()) {
            abort(403);
        }

        $attachments = $this->tableExists('proposal_attachments')
            ? DB::table('proposal_attachments')
                ->where('proposal_id', $id)
                ->orderByDesc('id')
                ->get()
            : collect();

        return view('proposals.show', [
            'proposal' => $proposal,
            'attachments' => $attachments,
            'types' => $this->types(),
            'priorities' => $this->priorities(),
            'canApprove' => $canApprove,
        ]);
    }

    /**
     * Duyệt đề xuất kèm ghi chú.
     */
    public function approve(Request $request, $id)
    {
        if (! $this->isApprover()) {
            abort(403);
        }

        $request->validate([
            'approved_note' => 'nullable|string|max:2000',
        ]);

        $data = [
            'status' => 'approved',
            'approved_by' => auth()->id(),
            'approved_at' => now(),
            'approved_note' => $request->approved_note,
            'reject_reason' => null,
            'updated_at' => now(),
        ];

        DB::table('proposals')
            ->where('id', $id)
            ->update($this->filterColumns('proposals', $data));

        return back()->with('success', 'Đã duyệt đề xuất.');
    }

    /**
     * Từ chối đề xuất kèm lý do.
     */
    public function reject(Request $request, $id)
    {
        if (! $this->isApprover()) {
            abort(403);
        }

        $request->validate([
            'reject_reason' => 'nullable|string|max:2000',
        ]);

        $data = [
            'status' => 'rejected',
            'approved_by' => auth()->id(),
            'approved_at' => now(),
            'reject_reason' => $request->reject_reason,
            'updated_at' => now(),
        ];

        DB::table('proposals')
            ->where('id', $id)
            ->update($this->filterColumns('proposals', $data));

        return back()->with('success', 'Đã từ chối đề xuất.');
    }

    /**
     * Xóa đề xuất và toàn bộ file đính kèm.
     */
    public function destroy($id)
    {
        if (! $this->tableExists('proposals')) {
            abort(404);
        }

        $proposal = DB::table('proposals')
            ->where('id', $id)
            ->first();

        abort_if(! $proposal, 404);

        $canDelete = $this->isApprover()
            || ((int) $proposal->user_id === (int) auth()->id() && $proposal->status === 'pending');

        if (! $canDelete) {
            abort(403);
        }

        if ($this->tableExists('proposal_attachments')) {
            $attachments = DB::table('proposal_attachments')
                ->where('proposal_id', $id)
                ->get();

            foreach ($attachments as $file) {
                Storage::disk('public')->delete($file->file_path);
            }

            DB::table('proposal_attachments')
                ->where('proposal_id', $id)
                ->delete();
        }

        DB::table('proposals')
            ->where('id', $id)
            ->delete();

        return redirect()
            ->route('de-xuat.index')
            ->with('success', 'Đã xóa đề xuất.');
    }
}
