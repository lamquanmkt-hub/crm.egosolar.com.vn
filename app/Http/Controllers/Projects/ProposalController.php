<?php

namespace App\Http\Controllers\Projects;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Quản lý đề xuất nội bộ.
 *
 * Phạm vi nâng cấp:
 * - Chỉ thay đổi module Đề xuất.
 * - Khi duyệt đề xuất có số tiền > 0, tự tạo một ĐNTT ở trạng thái nháp.
 * - Không chỉnh sửa Controller, View, Route hoặc schema của module ĐNTT.
 */
class ProposalController extends Controller
{
    private function tableExists(string $table): bool
    {
        return Schema::hasTable($table);
    }

    private function filterColumns(string $table, array $data): array
    {
        if (! Schema::hasTable($table)) {
            return $data;
        }

        return collect($data)
            ->only(Schema::getColumnListing($table))
            ->toArray();
    }

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

        if (method_exists($user, 'hasRole')) {
            foreach ([
                'admin',
                'manager',
                'accounting',
                'sales_manager',
                'marketing_manager',
            ] as $role) {
                if ($user->hasRole($role)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Quyền xem toàn bộ đề xuất.
     *
     * HR chỉ được mở rộng phạm vi XEM, không được kế thừa quyền
     * duyệt / từ chối / xóa của người có thẩm quyền.
     */
    private function canViewAll(): bool
    {
        $user = auth()->user();

        if (! $user) {
            return false;
        }

        if ($this->isApprover()) {
            return true;
        }

        if (method_exists($user, 'hasAnyRole')) {
            return $user->hasAnyRole(['hr']);
        }

        if (method_exists($user, 'hasRole')) {
            return $user->hasRole('hr');
        }

        if (isset($user->role)) {
            return (string) $user->role === 'hr';
        }

        return false;
    }

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

    private function priorities(): array
    {
        return [
            'low' => 'Thấp',
            'normal' => 'Bình thường',
            'high' => 'Cao',
            'urgent' => 'Gấp',
        ];
    }

    private function paymentStatusLabels(): array
    {
        return [
            'draft' => 'Nháp – chờ bổ sung',
            'submitted' => 'Đã gửi duyệt',
            'admin_approved' => 'Quản lý tài chính đã duyệt',
            'admin_rejected' => 'Quản lý tài chính từ chối',
            'accounting_approved' => 'Kế toán đã chi',
            'accounting_rejected' => 'Kế toán từ chối',
        ];
    }

    private function currentUserDepartment(): string
    {
        $user = auth()->user();

        if (! $user) {
            return '';
        }

        if (isset($user->department) && is_string($user->department)) {
            return $user->department;
        }

        if (
            Schema::hasTable('departments')
            && Schema::hasColumn('users', 'department_id')
        ) {
            $department = DB::table('users')
                ->leftJoin(
                    'departments',
                    'departments.id',
                    '=',
                    'users.department_id'
                )
                ->where('users.id', $user->id)
                ->select('departments.name')
                ->first();

            return $department->name ?? '';
        }

        return '';
    }

    private function activeCompanyId(): ?int
    {
        try {
            if (
                class_exists(\App\Support\EgoCompanyLock::class)
                && method_exists(\App\Support\EgoCompanyLock::class, 'id')
            ) {
                $id = (int) \App\Support\EgoCompanyLock::id();

                return $id > 0 ? $id : null;
            }
        } catch (\Throwable $e) {
            report($e);
        }

        return null;
    }

    private function activeCompanyName(): string
    {
        try {
            if (
                class_exists(\App\Support\EgoCompanyLock::class)
                && method_exists(\App\Support\EgoCompanyLock::class, 'name')
            ) {
                $name = trim((string) \App\Support\EgoCompanyLock::name());

                if ($name !== '') {
                    return $name;
                }
            }
        } catch (\Throwable $e) {
            report($e);
        }

        $sessionName = trim((string) session(
            'active_company_name',
            ''
        ));

        return $sessionName !== ''
            ? $sessionName
            : 'Công ty TNHH TMKT Quốc Tế EGO';
    }

    /**
     * Tạo đúng một ĐNTT nháp từ đề xuất.
     *
     * Hàm này chỉ ghi dữ liệu theo cấu trúc hiện tại của payment_requests,
     * không thay đổi bất kỳ file hay bảng nào thuộc module ĐNTT.
     */
    private function createPaymentRequestFromProposal(
        object $proposal
    ): array {
        if (
            ! $this->tableExists('payment_requests')
            || (float) ($proposal->amount ?? 0) <= 0
        ) {
            return [
                'id' => null,
                'code' => null,
                'created' => false,
            ];
        }

        if (
            Schema::hasColumn('proposals', 'payment_request_id')
            && ! empty($proposal->payment_request_id)
        ) {
            $existing = DB::table('payment_requests')
                ->where('id', (int) $proposal->payment_request_id)
                ->first(['id', 'code']);

            if ($existing) {
                return [
                    'id' => (int) $existing->id,
                    'code' => (string) $existing->code,
                    'created' => false,
                ];
            }
        }

        $creator = DB::table('users')
            ->where('id', (int) $proposal->user_id)
            ->first(['id', 'name']);

        $creatorId = (int) ($creator->id ?? $proposal->user_id);
        $receiverName = trim((string) ($creator->name ?? ''));

        if ($receiverName === '') {
            $receiverName = 'Chưa cập nhật';
        }

        $contentParts = array_filter([
            trim((string) ($proposal->content ?? '')),
            trim((string) ($proposal->reason ?? '')),
            trim((string) ($proposal->expected_result ?? '')),
        ]);

        $reason = 'Tự động tạo từ Đề xuất nội bộ #'
            .(int) $proposal->id
            .'.';

        if ($contentParts !== []) {
            $reason .= "\n\n".implode("\n\n", $contentParts);
        }

        $docType = ($proposal->proposal_type ?? '') === 'tam_ung'
            ? 'advance'
            : 'payment_request';

        $columns = Schema::getColumnListing('payment_requests');
        $now = now();

        $data = [
            'code' => 'TMP-'.(string) Str::uuid(),
            'doc_type' => $docType,
            'created_by' => $creatorId,
            'receiver_name' => $receiverName,
            'department' => $proposal->department_name ?: null,
            'company' => $this->activeCompanyName(),
            'reason' => $reason,
            'amount' => max(0, (int) round((float) $proposal->amount)),
            'payment_due_date' => $proposal->needed_date ?: null,
            'payment_content' => trim((string) $proposal->title),
            'bank_info' => null,
            'status' => 'draft',
            'cost_type' => $proposal->proposal_type ?: null,
            'company_id' => $this->activeCompanyId(),
            'created_at' => $now,
            'updated_at' => $now,
        ];

        $insert = array_intersect_key(
            $data,
            array_flip($columns)
        );

        $paymentRequestId = DB::table('payment_requests')
            ->insertGetId($insert);

        $code = 'PR-'
            .now()->format('Y')
            .'-'
            .str_pad(
                (string) $paymentRequestId,
                5,
                '0',
                STR_PAD_LEFT
            );

        $update = ['code' => $code];

        if (in_array('updated_at', $columns, true)) {
            $update['updated_at'] = $now;
        }

        DB::table('payment_requests')
            ->where('id', $paymentRequestId)
            ->update($update);

        return [
            'id' => (int) $paymentRequestId,
            'code' => $code,
            'created' => true,
        ];
    }

    public function index(Request $request)
    {
        if (! $this->tableExists('proposals')) {
            return back()->with(
                'error',
                'Chưa có bảng proposals trong database.'
            );
        }

        $types = $this->types();
        $priorities = $this->priorities();
        $paymentStatusLabels = $this->paymentStatusLabels();
        $canApprove = $this->isApprover();
        $canViewAll = $this->canViewAll();

        $query = DB::table('proposals')
            ->leftJoin(
                'users',
                'users.id',
                '=',
                'proposals.user_id'
            );

        if (
            Schema::hasColumn('proposals', 'payment_request_id')
            && $this->tableExists('payment_requests')
        ) {
            $query->leftJoin(
                'payment_requests',
                'payment_requests.id',
                '=',
                'proposals.payment_request_id'
            );
        }

        $select = [
            'proposals.*',
            DB::raw(
                'COALESCE(users.name, "Không rõ") '
                .'as employee_name'
            ),
        ];

        if (
            Schema::hasColumn('proposals', 'payment_request_id')
            && $this->tableExists('payment_requests')
        ) {
            $select[] = 'payment_requests.code as payment_request_code';
            $select[] = 'payment_requests.status as payment_request_status';
        } else {
            $select[] = DB::raw(
                'NULL as payment_request_code'
            );
            $select[] = DB::raw(
                'NULL as payment_request_status'
            );
        }

        $query->select($select)
            ->orderByDesc('proposals.id');

        if (! $canViewAll) {
            $query->where(
                'proposals.user_id',
                auth()->id()
            );
        }

        if ($request->filled('status')) {
            $query->where(
                'proposals.status',
                $request->status
            );
        }

        if ($request->filled('type')) {
            $query->where(
                'proposals.proposal_type',
                $request->type
            );
        }

        if ($request->filled('priority')) {
            $query->where(
                'proposals.priority',
                $request->priority
            );
        }

        if ($request->filled('q')) {
            $q = trim((string) $request->q);

            $query->where(function ($sub) use ($q) {
                $sub->where(
                    'proposals.title',
                    'like',
                    "%{$q}%"
                )
                    ->orWhere(
                        'proposals.content',
                        'like',
                        "%{$q}%"
                    )
                    ->orWhere(
                        'proposals.reason',
                        'like',
                        "%{$q}%"
                    )
                    ->orWhere(
                        'users.name',
                        'like',
                        "%{$q}%"
                    );
            });
        }

        $proposals = $query
            ->limit(300)
            ->get();

        $summaryQuery = DB::table('proposals');

        if (! $canViewAll) {
            $summaryQuery->where(
                'user_id',
                auth()->id()
            );
        }

        $summaryData = $summaryQuery->get();

        $summary = [
            'total' => $summaryData->count(),
            'pending' => $summaryData
                ->where('status', 'pending')
                ->count(),
            'approved' => $summaryData
                ->where('status', 'approved')
                ->count(),
            'rejected' => $summaryData
                ->where('status', 'rejected')
                ->count(),
            'amount_pending' => $summaryData
                ->where('status', 'pending')
                ->sum('amount'),
            'auto_payment' => Schema::hasColumn(
                'proposals',
                'payment_request_id'
            )
                ? $summaryData
                    ->whereNotNull('payment_request_id')
                    ->count()
                : 0,
        ];

        return view('proposals.index', compact(
            'proposals',
            'summary',
            'types',
            'priorities',
            'paymentStatusLabels',
            'canApprove',
            'canViewAll'
        ));
    }

    public function create()
    {
        return view('proposals.create', [
            'types' => $this->types(),
            'priorities' => $this->priorities(),
            'departmentName' => $this->currentUserDepartment(),
        ]);
    }

    public function store(Request $request)
    {
        if (! $this->tableExists('proposals')) {
            return back()->with(
                'error',
                'Chưa có bảng proposals trong database.'
            );
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
            'attachments.*' => 'nullable|file|max:20480',
        ], [
            'title.required' => 'Vui lòng nhập tiêu đề đề xuất.',
            'amount.numeric' => 'Số tiền dự kiến không hợp lệ.',
            'amount.min' => 'Số tiền không được nhỏ hơn 0.',
            'attachments.*.max' => 'Mỗi file không được vượt quá 20MB.',
        ]);

        $rawAmount = $request->input('amount', 0);

        if (is_string($rawAmount)) {
            $rawAmount = preg_replace(
                '/[^0-9]/',
                '',
                $rawAmount
            );
        }

        $data = [
            'user_id' => auth()->id(),
            'title' => trim((string) $request->title),
            'proposal_type' => $request->proposal_type ?: 'khac',
            'priority' => $request->priority ?: 'normal',
            'department_name' => $request->department_name
                ?: $this->currentUserDepartment(),
            'amount' => (float) ($rawAmount ?: 0),
            'needed_date' => $request->needed_date,
            'content' => $request->content,
            'reason' => $request->reason,
            'expected_result' => $request->expected_result,
            'status' => 'pending',
            'created_at' => now(),
            'updated_at' => now(),
        ];

        $proposalId = DB::table('proposals')
            ->insertGetId(
                $this->filterColumns(
                    'proposals',
                    $data
                )
            );

        if (
            $request->hasFile('attachments')
            && $this->tableExists(
                'proposal_attachments'
            )
        ) {
            foreach (
                $request->file('attachments')
                as $file
            ) {
                if (! $file || ! $file->isValid()) {
                    continue;
                }

                $path = $file->store(
                    'proposal_attachments/'.$proposalId,
                    'public'
                );

                DB::table('proposal_attachments')->insert([
                    'proposal_id' => $proposalId,
                    'uploaded_by' => auth()->id(),
                    'file_name' => $file
                        ->getClientOriginalName(),
                    'file_path' => $path,
                    'file_mime' => $file
                        ->getClientMimeType(),
                    'file_size' => $file->getSize(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        return redirect()
            ->route(
                'de-xuat.show',
                $proposalId
            )
            ->with(
                'success',
                'Đã gửi đề xuất cho cấp duyệt.'
            );
    }

    public function show($id)
    {
        if (! $this->tableExists('proposals')) {
            abort(404);
        }

        $query = DB::table('proposals')
            ->leftJoin(
                'users',
                'users.id',
                '=',
                'proposals.user_id'
            )
            ->leftJoin(
                'users as approver',
                'approver.id',
                '=',
                'proposals.approved_by'
            )
            ->where(
                'proposals.id',
                $id
            );

        if (
            Schema::hasColumn(
                'proposals',
                'payment_request_id'
            )
            && $this->tableExists('payment_requests')
        ) {
            $query->leftJoin(
                'payment_requests',
                'payment_requests.id',
                '=',
                'proposals.payment_request_id'
            );
        }

        $select = [
            'proposals.*',
            DB::raw(
                'COALESCE(users.name, "Không rõ") '
                .'as employee_name'
            ),
            DB::raw(
                'COALESCE(approver.name, "") '
                .'as approved_name'
            ),
        ];

        if (
            Schema::hasColumn(
                'proposals',
                'payment_request_id'
            )
            && $this->tableExists('payment_requests')
        ) {
            $select[] = 'payment_requests.code as payment_request_code';
            $select[] = 'payment_requests.status as payment_request_status';
            $select[] = 'payment_requests.amount as payment_request_amount';
        } else {
            $select[] = DB::raw(
                'NULL as payment_request_code'
            );
            $select[] = DB::raw(
                'NULL as payment_request_status'
            );
            $select[] = DB::raw(
                'NULL as payment_request_amount'
            );
        }

        $proposal = $query
            ->select($select)
            ->first();

        abort_if(! $proposal, 404);

        $canApprove = $this->isApprover();
        $canViewAll = $this->canViewAll();

        if (
            ! $canViewAll
            && (int) $proposal->user_id
                !== (int) auth()->id()
        ) {
            abort(403);
        }

        $attachments = $this->tableExists(
            'proposal_attachments'
        )
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
            'paymentStatusLabels' => $this->paymentStatusLabels(),
            'canApprove' => $canApprove,
            'canViewAll' => $canViewAll,
        ]);
    }

    /**
     * Duyệt đề xuất.
     *
     * Cùng một transaction:
     * 1. Khóa đề xuất.
     * 2. Duyệt đề xuất.
     * 3. Nếu số tiền > 0, tạo đúng một ĐNTT nháp.
     * 4. Lưu payment_request_id trên đề xuất.
     */
    public function approve(Request $request, $id)
    {
        if (! $this->isApprover()) {
            abort(403);
        }

        $request->validate([
            'approved_note' => 'nullable|string|max:2000',
        ]);

        try {
            $result = DB::transaction(
                function () use ($request, $id) {
                    $proposal = DB::table('proposals')
                        ->where('id', $id)
                        ->lockForUpdate()
                        ->first();

                    abort_if(! $proposal, 404);

                    $updateData = [
                        'status' => 'approved',
                        'approved_by' => auth()->id(),
                        'approved_at' => now(),
                        'approved_note' => $request
                            ->approved_note,
                        'reject_reason' => null,
                        'updated_at' => now(),
                    ];

                    DB::table('proposals')
                        ->where('id', $id)
                        ->update(
                            $this->filterColumns(
                                'proposals',
                                $updateData
                            )
                        );

                    $proposal = DB::table('proposals')
                        ->where('id', $id)
                        ->first();

                    $paymentResult = [
                        'id' => null,
                        'code' => null,
                        'created' => false,
                    ];

                    if (
                        (float) ($proposal->amount ?? 0) > 0
                    ) {
                        if (
                            ! Schema::hasColumn(
                                'proposals',
                                'payment_request_id'
                            )
                        ) {
                            throw new \RuntimeException(
                                'Chưa chạy migration liên kết ĐNTT.'
                            );
                        }

                        $paymentResult =
                            $this->createPaymentRequestFromProposal(
                                $proposal
                            );

                        if (! empty($paymentResult['id'])) {
                            DB::table('proposals')
                                ->where('id', $id)
                                ->update([
                                    'payment_request_id'
                                        => $paymentResult['id'],
                                    'payment_request_created_at'
                                        => now(),
                                    'updated_at'
                                        => now(),
                                ]);
                        }
                    }

                    return $paymentResult;
                },
                3
            );

            if (! empty($result['code'])) {
                $message = $result['created']
                    ? 'Đã duyệt đề xuất và tự động tạo ĐNTT '
                        .$result['code'].'.'
                    : 'Đề xuất đã được duyệt. ĐNTT '
                        .$result['code'].' đã tồn tại.';
            } else {
                $message = 'Đã duyệt đề xuất. '
                    .'Đề xuất không phát sinh ĐNTT vì số tiền bằng 0.';
            }

            return back()->with('success', $message);
        } catch (\Throwable $e) {
            report($e);

            return back()->with(
                'error',
                'Không thể duyệt đề xuất hoặc tạo ĐNTT: '
                .$e->getMessage()
            );
        }
    }

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
            ->update(
                $this->filterColumns(
                    'proposals',
                    $data
                )
            );

        return back()->with(
            'success',
            'Đã từ chối đề xuất.'
        );
    }

    public function destroy($id)
    {
        if (! $this->tableExists('proposals')) {
            abort(404);
        }

        $proposal = DB::table('proposals')
            ->where('id', $id)
            ->first();

        abort_if(! $proposal, 404);

        if (
            Schema::hasColumn(
                'proposals',
                'payment_request_id'
            )
            && ! empty($proposal->payment_request_id)
        ) {
            return back()->with(
                'error',
                'Đề xuất đã liên kết ĐNTT nên không thể xóa.'
            );
        }

        $canDelete = $this->isApprover()
            || (
                (int) $proposal->user_id
                    === (int) auth()->id()
                && $proposal->status === 'pending'
            );

        if (! $canDelete) {
            abort(403);
        }

        if ($this->tableExists('proposal_attachments')) {
            $attachments = DB::table(
                'proposal_attachments'
            )
                ->where('proposal_id', $id)
                ->get();

            foreach ($attachments as $file) {
                Storage::disk('public')
                    ->delete($file->file_path);
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
            ->with(
                'success',
                'Đã xóa đề xuất.'
            );
    }
}
