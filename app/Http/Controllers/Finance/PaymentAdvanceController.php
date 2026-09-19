<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Models\Payments\PaymentRequest;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class PaymentAdvanceController extends Controller
{
    private function hasAnyRole($user, array $roles): bool
    {
        if (! $user) {
            return false;
        }

        if (method_exists($user, 'hasAnyRole') && $user->hasAnyRole($roles)) {
            return true;
        }

        if (method_exists($user, 'hasRole')) {
            foreach ($roles as $role) {
                if ($user->hasRole($role)) {
                    return true;
                }
            }
        }

        $rawRole = strtolower(trim((string) ($user->role ?? '')));
        return in_array($rawRole, $roles, true);
    }

    private function isAdmin($user): bool
    {
        return $user && (
            $this->hasAnyRole($user, ['admin'])
            || ((int) ($user->is_admin ?? 0) === 1)
        );
    }

    private function isAccounting($user): bool
    {
        if (! $user) {
            return false;
        }

        $roles = ['accounting', 'ketoan', 'ke_toan'];
        $email = strtolower((string) ($user->email ?? ''));

        return $this->hasAnyRole($user, $roles) || str_contains($email, 'ketoan');
    }

    private function isFinanceManager($user): bool
    {
        if ($this->isAdmin($user)) {
            return true;
        }

        return $this->hasAnyRole($user, [
            'management',
            'finance_manager',
            'accounting_manager',
            'financial_manager',
            'quan_ly_tai_chinh',
            'quanlytaichinh',
        ]);
    }

    private function canViewAll($user): bool
    {
        return $this->isFinanceManager($user) || $this->isAccounting($user);
    }

    private function companyName(): string
    {
        if (class_exists(\App\Support\EgoCompanyLock::class)) {
            try {
                $name = trim((string) \App\Support\EgoCompanyLock::name());
                if ($name !== '') {
                    return $name;
                }
            } catch (\Throwable $e) {
                // fall through to session value
            }
        }

        $name = trim((string) session('active_company_name', ''));
        return $name !== '' ? $name : 'Công ty TNHH TMKT Quốc Tế EGO';
    }

    private function activeCompanyId(): ?int
    {
        if (! class_exists(\App\Support\EgoCompanyLock::class)) {
            return null;
        }

        try {
            $id = (int) \App\Support\EgoCompanyLock::id();
            return $id > 0 ? $id : null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function applyCompanyScope($query, string $alias = 'pr'): void
    {
        if (! Schema::hasColumn('payment_requests', 'company_id')) {
            return;
        }

        $companyId = $this->activeCompanyId();
        if ($companyId) {
            $query->where($alias.'.company_id', $companyId);
        }
    }

    private function normalizeAmount($value): float
    {
        if (is_numeric($value)) {
            return (float) $value;
        }

        $clean = preg_replace('/[^0-9-]/', '', (string) $value);
        return $clean === '' ? 0 : (float) $clean;
    }

    private function createPaymentRequest(array $payload, string $prefix): int
    {
        $columns = Schema::getColumnListing('payment_requests');
        $now = now();
        $data = [
            'code' => 'TMP-'.Str::uuid(),
            'doc_type' => $payload['doc_type'],
            'created_by' => $payload['created_by'],
            'receiver_name' => $payload['receiver_name'] ?: 'Chưa cập nhật',
            'department' => $payload['department'] ?? null,
            'company' => $payload['company'] ?? $this->companyName(),
            'reason' => $payload['reason'] ?: ($payload['payment_content'] ?? 'Đề nghị tạm ứng'),
            'amount' => $payload['amount'] ?? 0,
            'payment_due_date' => $payload['payment_due_date'] ?? null,
            'payment_content' => $payload['payment_content'] ?? ($payload['reason'] ?? 'Đề nghị tạm ứng'),
            'bank_info' => $payload['bank_info'] ?? null,
            'status' => 'draft',
            'created_at' => $now,
            'updated_at' => $now,
        ];

        if (in_array('company_id', $columns, true)) {
            $companyId = $this->activeCompanyId();
            if ($companyId) {
                $data['company_id'] = $companyId;
            }
        }

        $id = (int) DB::table('payment_requests')->insertGetId(
            array_intersect_key($data, array_flip($columns))
        );

        DB::table('payment_requests')->where('id', $id)->update([
            'code' => $prefix.'-'.now()->format('Ymd').'-'.str_pad((string) $id, 5, '0', STR_PAD_LEFT),
            'updated_at' => $now,
        ]);

        return $id;
    }

    private function saveAttachment(int $paymentRequestId, $file): void
    {
        if (! $file || ! $file->isValid() || ! Schema::hasTable('payment_attachments')) {
            return;
        }

        $path = $file->store("payment_requests/{$paymentRequestId}", 'public');
        $columns = Schema::getColumnListing('payment_attachments');
        $data = [
            'payment_request_id' => $paymentRequestId,
            'original_name' => $file->getClientOriginalName(),
            'path' => $path,
            'mime_type' => $file->getClientMimeType(),
            'size' => $file->getSize(),
            'created_at' => now(),
            'updated_at' => now(),
        ];

        DB::table('payment_attachments')->insert(array_intersect_key($data, array_flip($columns)));
    }

    private function authorizePaymentRequest(object $item): void
    {
        $user = auth()->user();
        abort_unless($user, 403);

        if ($this->canViewAll($user)) {
            return;
        }

        abort_unless((int) $item->created_by === (int) $user->id, 403);
    }

    private function paymentStatusLabels(): array
    {
        return [
            'draft' => 'Nháp',
            'submitted' => 'Chờ QL tài chính duyệt',
            'admin_approved' => 'Chờ kế toán chi',
            'admin_rejected' => 'QL tài chính từ chối',
            'accounting_approved' => 'Đã chi tạm ứng',
            'accounting_rejected' => 'Kế toán từ chối chi',
        ];
    }

    private function settlementStatusLabels(): array
    {
        return [
            'waiting_payment' => 'Chờ kế toán chi',
            'waiting_settlement' => 'Cần hoàn ứng',
            'draft' => 'Hoàn ứng nháp',
            'submitted' => 'Chờ kế toán đối soát',
            'accounting_checked' => 'Chờ QL tài chính duyệt hoàn ứng',
            'management_approved' => 'Chờ kế toán đối soát (luồng cũ)',
            'returned' => 'Cần bổ sung hoàn ứng',
            'completed' => 'Đã quyết toán',
        ];
    }

    /**
     * Một hồ sơ thuộc module khi đã có marker advance_settlements.
     * Nhờ vậy có thể tạo Hoàn ứng trực tiếp từ một ĐNTT lịch sử đã được Kế toán chi,
     * mà không phải sửa mã phiếu/doc_type của ĐNTT nguồn.
     */
    private function moduleAdvanceRequest($id): ?object
    {
        $query = DB::table('payment_requests as pr')
            ->join('advance_settlements as s', 's.payment_request_id', '=', 'pr.id')
            ->where('pr.id', $id)
            ->select('pr.*');

        $this->applyCompanyScope($query, 'pr');

        return $query->first();
    }

    private function redirectLegacyPaymentRequest($id)
    {
        return redirect()->route('payment_requests.show', $id)
            ->with('info', 'Phiếu này thuộc Đề nghị thanh toán, không phải hồ sơ tạm ứng - hoàn ứng.');
    }

    public function advanceIndex(Request $request)
    {
        abort_unless(Schema::hasTable('advance_settlements'), 503, 'Module tạm ứng chưa được migrate.');

        $user = auth()->user();
        $canViewAll = $this->canViewAll($user);

        $q = DB::table('payment_requests as pr')
            ->leftJoin('users as u', 'u.id', '=', 'pr.created_by')
            ->join('advance_settlements as s', 's.payment_request_id', '=', 'pr.id')
            ->select([
                'pr.*',
                'u.name as creator_name',
                's.id as settlement_id',
                's.status as settlement_status',
                's.actual_spent_amount',
                's.difference_amount',
                's.settlement_due_date',
                's.settlement_reason',
                's.submitted_at as settlement_submitted_at',
                's.management_approved_at',
                's.accounting_checked_at',
                's.completed_at',
            ]);

        $this->applyCompanyScope($q, 'pr');

        if (! $canViewAll) {
            $q->where('pr.created_by', $user->id);
        }

        if ($request->filled('q')) {
            $keyword = trim((string) $request->q);
            $q->where(function ($x) use ($keyword) {
                $x->where('pr.code', 'like', "%{$keyword}%")
                    ->orWhere('pr.receiver_name', 'like', "%{$keyword}%")
                    ->orWhere('pr.reason', 'like', "%{$keyword}%")
                    ->orWhere('pr.payment_content', 'like', "%{$keyword}%")
                    ->orWhere('u.name', 'like', "%{$keyword}%");
            });
        }

        if ($request->filled('status')) {
            $status = (string) $request->status;
            if (str_starts_with($status, 'settlement:')) {
                $q->where('s.status', substr($status, strlen('settlement:')));
            } elseif ($status === 'need_settlement') {
                $q->where('pr.status', 'accounting_approved')
                    ->whereIn('s.status', ['waiting_settlement', 'draft', 'returned']);
            } elseif ($status === 'completed') {
                $q->where('s.status', 'completed');
            } else {
                $q->where('pr.status', $status);
            }
        }

        if ($request->filled('date_from')) {
            $q->whereDate('pr.created_at', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $q->whereDate('pr.created_at', '<=', $request->date_to);
        }

        $items = $q->orderByDesc('pr.id')->paginate(20)->withQueryString();

        $statsQ = DB::table('payment_requests as pr')
            ->join('advance_settlements as s', 's.payment_request_id', '=', 'pr.id')
            ->where('pr.doc_type', 'advance')
            ->where('pr.code', 'like', 'DNTU-%');
        $this->applyCompanyScope($statsQ, 'pr');
        if (! $canViewAll) {
            $statsQ->where('pr.created_by', $user->id);
        }

        $pendingQ = clone $statsQ;
        $pendingQ->where(function ($x) {
            $x->whereIn('pr.status', ['submitted', 'admin_approved'])
                ->orWhere(function ($y) {
                    $y->where('pr.status', 'accounting_approved')
                        ->whereIn('s.status', ['submitted', 'accounting_checked', 'management_approved']);
                });
        });

        $needQ = clone $statsQ;
        $needQ->where('pr.status', 'accounting_approved')
            ->whereIn('s.status', ['waiting_settlement', 'draft', 'returned']);

        $overdueQ = clone $needQ;
        $overdueQ->whereNotNull('s.settlement_due_date')
            ->whereDate('s.settlement_due_date', '<', now()->toDateString());

        $stats = [
            'total' => (clone $statsQ)->count(),
            'pending' => $pendingQ->count(),
            'need_settlement' => $needQ->count(),
            'overdue_settlement' => $overdueQ->count(),
            'completed' => (clone $statsQ)->where('s.status', 'completed')->count(),
            'amount' => (float) (clone $statsQ)->sum('pr.amount'),
        ];

        // ĐNTT đã được kế toán chi nhưng chưa có hồ sơ hoàn ứng.
        // Người thường chỉ thấy phiếu mình tạo; QL tài chính/Kế toán thấy toàn công ty.
        $eligiblePaymentRequests = DB::table('payment_requests as pr')
            ->leftJoin('users as u', 'u.id', '=', 'pr.created_by')
            ->where('pr.status', 'accounting_approved')
            ->where(function ($x) {
                $x->whereNull('pr.doc_type')
                    ->orWhere('pr.doc_type', '<>', 'salary_advance');
            })
            ->whereNotExists(function ($x) {
                $x->select(DB::raw(1))
                    ->from('advance_settlements as axs')
                    ->whereColumn('axs.payment_request_id', 'pr.id');
            })
            ->select('pr.id', 'pr.code', 'pr.receiver_name', 'pr.amount', 'pr.payment_content', 'pr.reason', 'pr.created_by', 'pr.created_at', 'u.name as creator_name')
            ->orderByDesc('pr.id')
            ->limit(200);
        $this->applyCompanyScope($eligiblePaymentRequests, 'pr');
        if (! $canViewAll) {
            $eligiblePaymentRequests->where('pr.created_by', $user->id);
        }
        $eligiblePaymentRequests = $eligiblePaymentRequests->get();

        return view('payment_advances.index', [
            'items' => $items,
            'stats' => $stats,
            'statusLabels' => $this->paymentStatusLabels(),
            'settlementLabels' => $this->settlementStatusLabels(),
            'canViewAll' => $canViewAll,
            'isFinanceManager' => $this->isFinanceManager($user),
            'isAccounting' => $this->isAccounting($user),
            'eligiblePaymentRequests' => $eligiblePaymentRequests,
        ]);
    }

    public function advanceStore(Request $request)
    {
        $user = auth()->user();
        abort_unless($user, 403);
        abort_unless(Schema::hasTable('advance_settlements'), 503, 'Module tạm ứng chưa được migrate.');

        $request->merge(['amount' => $this->normalizeAmount($request->amount)]);
        $data = $request->validate([
            'receiver_name' => ['required', 'string', 'max:500'],
            'department' => ['nullable', 'string', 'max:255'],
            'payment_content' => ['required', 'string', 'max:5000'],
            'amount' => ['required', 'numeric', 'min:1'],
            'needed_date' => ['nullable', 'date'],
            'settlement_due_date' => ['nullable', 'date'],
            'bank_info' => ['nullable', 'string', 'max:3000'],
            'attachments' => ['nullable', 'array', 'max:10'],
            'attachments.*' => ['nullable', 'file', 'max:20480', 'mimes:jpg,jpeg,png,webp,pdf,doc,docx,xls,xlsx'],
        ]);

        $id = DB::transaction(function () use ($data, $request, $user) {
            $id = $this->createPaymentRequest([
                'doc_type' => 'advance',
                'created_by' => (int) $user->id,
                'receiver_name' => $data['receiver_name'],
                'department' => $data['department'] ?? ($user->department ?? null),
                'company' => $this->companyName(),
                'reason' => $data['payment_content'],
                'payment_content' => $data['payment_content'],
                'amount' => $data['amount'],
                'payment_due_date' => $data['needed_date'] ?? null,
                'bank_info' => $data['bank_info'] ?? null,
            ], 'DNTU');

            DB::table('advance_settlements')->insert([
                'payment_request_id' => $id,
                'created_by' => $user->id,
                'status' => 'waiting_payment',
                'settlement_due_date' => $data['settlement_due_date'] ?? null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            foreach ((array) $request->file('attachments', []) as $file) {
                $this->saveAttachment($id, $file);
            }

            return $id;
        });

        return redirect()->route('payment_advances.show', $id)
            ->with('success', 'Đã tạo Đề nghị tạm ứng. Hãy gửi Quản lý tài chính duyệt.');
    }

    /**
     * Tạo hồ sơ Hoàn ứng từ một ĐNTT đã được Kế toán xác nhận chi.
     * Không nhân bản ĐNTT và không đổi doc_type: chỉ gắn marker advance_settlements.
     */
    public function settlementImportFromPaymentRequest(Request $request)
    {
        abort_unless(Schema::hasTable('advance_settlements'), 503, 'Module hoàn ứng chưa được migrate.');

        $user = auth()->user();
        abort_unless($user, 403);

        $validated = $request->validate([
            'payment_request_id' => ['required', 'integer', 'min:1'],
            'settlement_due_date' => ['nullable', 'date'],
        ]);

        $query = DB::table('payment_requests as pr')
            ->where('pr.id', (int) $validated['payment_request_id'])
            ->where('pr.status', 'accounting_approved')
            ->where(function ($x) {
                $x->whereNull('pr.doc_type')
                    ->orWhere('pr.doc_type', '<>', 'salary_advance');
            });
        $this->applyCompanyScope($query, 'pr');
        if (! $this->canViewAll($user)) {
            $query->where('pr.created_by', $user->id);
        }

        $payment = $query->first();
        abort_unless($payment, 404, 'Không tìm thấy ĐNTT đã chi hoặc bạn không có quyền hoàn ứng phiếu này.');

        abort_if(
            DB::table('advance_settlements')->where('payment_request_id', $payment->id)->exists(),
            422,
            'ĐNTT này đã có hồ sơ tạm ứng/hoàn ứng.'
        );

        DB::table('advance_settlements')->insert([
            'payment_request_id' => $payment->id,
            'created_by' => $user->id,
            'status' => 'waiting_settlement',
            'settlement_due_date' => $validated['settlement_due_date'] ?? null,
            'actual_spent_amount' => 0,
            'difference_amount' => (float) ($payment->amount ?? 0),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return redirect(route('payment_advances.show', $payment->id).'#hoan-ung')
            ->with('success', 'Đã tạo hồ sơ hoàn ứng từ ĐNTT '.$payment->code.'.');
    }

    public function advanceShow($id)
    {
        $moduleItem = $this->moduleAdvanceRequest($id);
        if (! $moduleItem) {
            $legacy = DB::table('payment_requests')->where('id', $id)->first(['id']);
            abort_unless($legacy, 404);
            return $this->redirectLegacyPaymentRequest($id);
        }

        $query = DB::table('payment_requests as pr')
            ->join('advance_settlements as s', 's.payment_request_id', '=', 'pr.id')
            ->leftJoin('users as u', 'u.id', '=', 'pr.created_by')
            ->where('pr.id', $id)
            ->select('pr.*', 'u.name as creator_name');
        $this->applyCompanyScope($query, 'pr');
        $item = $query->first();
        abort_unless($item, 404);
        $this->authorizePaymentRequest($item);

        $settlement = DB::table('advance_settlements')->where('payment_request_id', $id)->first();
        abort_unless($settlement, 404);

        $settlementItems = Schema::hasTable('advance_settlement_items')
            ? DB::table('advance_settlement_items')->where('advance_settlement_id', $settlement->id)->orderBy('id')->get()
            : collect();

        $approvals = Schema::hasTable('payment_request_approvals')
            ? DB::table('payment_request_approvals as a')
                ->leftJoin('users as u', 'u.id', '=', 'a.actor_id')
                ->where('a.payment_request_id', $id)
                ->select('a.*', 'u.name as actor_name')
                ->orderBy('a.id')
                ->get()
            : collect();

        $attachments = Schema::hasTable('payment_attachments')
            ? DB::table('payment_attachments')->where('payment_request_id', $id)->orderBy('id')->get()
            : collect();

        $settlementAttachments = collect();
        $decoded = json_decode((string) ($settlement->attachments ?? '[]'), true);
        if (is_array($decoded)) {
            $settlementAttachments = collect($decoded);
        }

        $user = auth()->user();
        return view('payment_advances.show', [
            'item' => $item,
            'settlement' => $settlement,
            'settlementItems' => $settlementItems,
            'approvals' => $approvals,
            'attachments' => $attachments,
            'settlementAttachments' => $settlementAttachments,
            'statusLabels' => $this->paymentStatusLabels(),
            'settlementLabels' => $this->settlementStatusLabels(),
            'isFinanceManager' => $this->isFinanceManager($user),
            'isAccounting' => $this->isAccounting($user),
            'isOwner' => (int) $item->created_by === (int) $user->id,
        ]);
    }

    public function settlementStore(Request $request, $id)
    {
        $item = $this->moduleAdvanceRequest($id);
        abort_unless($item, 404);
        abort_unless((int) $item->created_by === (int) auth()->id() || $this->isFinanceManager(auth()->user()), 403);
        abort_unless($item->status === 'accounting_approved', 422, 'Chỉ hoàn ứng sau khi Kế toán đã chi tiền tạm ứng.');

        $settlement = DB::table('advance_settlements')->where('payment_request_id', $id)->first();
        abort_unless($settlement, 404);
        abort_unless(in_array($settlement->status, ['waiting_settlement', 'draft', 'returned'], true), 422, 'Hồ sơ hoàn ứng không còn ở trạng thái cho phép chỉnh sửa.');

        $request->merge(['actual_spent_amount' => $this->normalizeAmount($request->actual_spent_amount)]);
        $validated = $request->validate([
            'actual_spent_amount' => ['required', 'numeric', 'min:0'],
            'settlement_reason' => ['required', 'string', 'max:5000'],
            'settlement_note' => ['nullable', 'string', 'max:5000'],
            'submit_now' => ['nullable', 'in:0,1'],
            'attachments' => ['nullable', 'array', 'max:10'],
            'attachments.*' => ['nullable', 'file', 'max:10240', 'mimes:jpg,jpeg,png,webp,pdf,doc,docx,xls,xlsx'],
        ]);

        $submitNow = (string) ($validated['submit_now'] ?? '1') === '1';
        $oldFiles = json_decode((string) ($settlement->attachments ?? '[]'), true);
        $oldFiles = is_array($oldFiles) ? $oldFiles : [];

        $newFiles = [];
        foreach ((array) $request->file('attachments', []) as $file) {
            if (! $file || ! $file->isValid()) {
                continue;
            }
            $path = $file->store("payment_requests/{$id}/settlement", 'public');
            $newFiles[] = [
                'name' => $file->getClientOriginalName(),
                'path' => $path,
                'mime' => $file->getClientMimeType(),
                'size' => $file->getSize(),
            ];
        }

        $allFiles = array_values(array_merge($oldFiles, $newFiles));
        abort_if($submitNow && count($allFiles) === 0, 422, 'Vui lòng đính kèm ít nhất một chứng từ trước khi gửi duyệt.');

        $total = (float) $validated['actual_spent_amount'];
        $difference = (float) $item->amount - $total;

        DB::table('advance_settlements')->where('id', $settlement->id)->update([
            'status' => $submitNow ? 'submitted' : 'draft',
            'actual_spent_amount' => $total,
            'difference_amount' => $difference,
            'settlement_reason' => trim($validated['settlement_reason']),
            'settlement_note' => trim((string) ($validated['settlement_note'] ?? '')) ?: null,
            'attachments' => json_encode($allFiles, JSON_UNESCAPED_UNICODE),
            'submitted_by' => $submitNow ? auth()->id() : null,
            'submitted_at' => $submitNow ? now() : null,
            'management_approved_by' => null,
            'management_approved_at' => null,
            'management_note' => null,
            'accounting_checked_by' => null,
            'accounting_checked_at' => null,
            'accounting_note' => null,
            'completed_by' => null,
            'completed_at' => null,
            'complete_note' => null,
            'updated_at' => now(),
        ]);

        return back()->with('success', $submitNow
            ? 'Đã gửi hồ sơ hoàn ứng cho Kế toán đối soát.'
            : 'Đã lưu nháp hồ sơ hoàn ứng.');
    }

    public function settlementManagementApprove(Request $request, $id)
    {
        abort_unless($this->moduleAdvanceRequest($id), 404);
        abort_unless($this->isFinanceManager(auth()->user()), 403);

        $settlement = DB::table('advance_settlements')->where('payment_request_id', $id)->first();
        abort_unless($settlement && $settlement->status === 'accounting_checked', 422, 'Hồ sơ chưa được Kế toán đối soát.');

        $note = trim((string) $request->input('note')) ?: null;
        DB::table('advance_settlements')->where('id', $settlement->id)->update([
            'status' => 'completed',
            'management_approved_by' => auth()->id(),
            'management_approved_at' => now(),
            'management_note' => $note,
            'completed_by' => auth()->id(),
            'completed_at' => now(),
            'complete_note' => $note,
            'checked_by' => auth()->id(),
            'checked_at' => now(),
            'check_note' => $note,
            'updated_at' => now(),
        ]);

        return back()->with('success', 'Quản lý tài chính đã duyệt hoàn ứng. Hồ sơ đã hoàn tất.');
    }

    public function settlementManagementReject(Request $request, $id)
    {
        abort_unless($this->moduleAdvanceRequest($id), 404);
        abort_unless($this->isFinanceManager(auth()->user()), 403);
        $validated = $request->validate(['note' => ['required', 'string', 'min:2', 'max:2000']]);

        $settlement = DB::table('advance_settlements')->where('payment_request_id', $id)->first();
        abort_unless($settlement && $settlement->status === 'accounting_checked', 422);

        DB::table('advance_settlements')->where('id', $settlement->id)->update([
            'status' => 'returned',
            'returned_by' => auth()->id(),
            'returned_at' => now(),
            'return_note' => trim($validated['note']),
            'management_note' => trim($validated['note']),
            'updated_at' => now(),
        ]);

        return back()->with('success', 'Đã trả hồ sơ hoàn ứng để nhân viên bổ sung.');
    }

    public function settlementAccountingCheck(Request $request, $id)
    {
        abort_unless($this->moduleAdvanceRequest($id), 404);
        abort_unless($this->isAccounting(auth()->user()), 403);

        $settlement = DB::table('advance_settlements')->where('payment_request_id', $id)->first();
        abort_unless($settlement && in_array($settlement->status, ['submitted', 'management_approved'], true), 422, 'Hồ sơ chưa ở bước Kế toán đối soát.');

        $note = trim((string) $request->input('note')) ?: null;

        // Hồ sơ cũ đã được QL tài chính duyệt trước khi đổi quy trình: cho Kế toán hoàn tất để không bị kẹt.
        if ($settlement->status === 'management_approved') {
            DB::table('advance_settlements')->where('id', $settlement->id)->update([
                'status' => 'completed',
                'accounting_checked_by' => auth()->id(),
                'accounting_checked_at' => now(),
                'accounting_note' => $note,
                'completed_by' => auth()->id(),
                'completed_at' => now(),
                'complete_note' => $note,
                'updated_at' => now(),
            ]);

            return back()->with('success', 'Kế toán đã đối soát và hoàn tất hồ sơ theo luồng cũ.');
        }

        DB::table('advance_settlements')->where('id', $settlement->id)->update([
            'status' => 'accounting_checked',
            'accounting_checked_by' => auth()->id(),
            'accounting_checked_at' => now(),
            'accounting_note' => $note,
            'updated_at' => now(),
        ]);

        return back()->with('success', 'Kế toán đã đối soát. Chuyển Quản lý tài chính duyệt hoàn ứng.');
    }

    public function settlementAccountingReturn(Request $request, $id)
    {
        abort_unless($this->moduleAdvanceRequest($id), 404);
        abort_unless($this->isAccounting(auth()->user()), 403);
        $validated = $request->validate(['note' => ['required', 'string', 'min:2', 'max:2000']]);

        $settlement = DB::table('advance_settlements')->where('payment_request_id', $id)->first();
        abort_unless($settlement && in_array($settlement->status, ['submitted', 'management_approved'], true), 422);

        DB::table('advance_settlements')->where('id', $settlement->id)->update([
            'status' => 'returned',
            'returned_by' => auth()->id(),
            'returned_at' => now(),
            'return_note' => trim($validated['note']),
            'accounting_note' => trim($validated['note']),
            'updated_at' => now(),
        ]);

        return back()->with('success', 'Kế toán đã trả hồ sơ hoàn ứng để bổ sung chứng từ/thông tin.');
    }

    // Tương thích route cũ.
    public function settlementCheck(Request $request, $id)
    {
        return $this->settlementAccountingCheck($request, $id);
    }

    public function settlementReturn(Request $request, $id)
    {
        $settlement = DB::table('advance_settlements')->where('payment_request_id', $id)->first();
        abort_unless($settlement, 404);

        if ($settlement->status === 'accounting_checked' && $this->isFinanceManager(auth()->user())) {
            return $this->settlementManagementReject($request, $id);
        }

        return $this->settlementAccountingReturn($request, $id);
    }

    public function settlementComplete(Request $request, $id)
    {
        $settlement = DB::table('advance_settlements')->where('payment_request_id', $id)->first();
        abort_unless($settlement, 404);

        if ($settlement->status === 'accounting_checked') {
            return $this->settlementManagementApprove($request, $id);
        }

        return $this->settlementAccountingCheck($request, $id);
    }

    public function salaryIndex(Request $request)
    {
        $user = auth()->user();
        $canViewAll = $this->canViewAll($user);

        $q = DB::table('salary_advance_requests as sa')
            ->join('payment_requests as pr', 'pr.id', '=', 'sa.payment_request_id')
            ->leftJoin('users as u', 'u.id', '=', 'sa.user_id')
            ->where('pr.code', 'like', 'DNTUL-%')
            ->select('sa.*', 'pr.code', 'pr.status', 'pr.amount', 'pr.bank_info', 'pr.created_at as payment_created_at', 'u.name as employee_name');

        if (! $canViewAll) {
            $q->where('sa.user_id', $user->id);
        }

        if ($request->filled('q')) {
            $keyword = trim((string) $request->q);
            $q->where(function ($x) use ($keyword) {
                $x->where('pr.code', 'like', "%{$keyword}%")
                    ->orWhere('u.name', 'like', "%{$keyword}%")
                    ->orWhere('sa.reason', 'like', "%{$keyword}%");
            });
        }
        if ($request->filled('month')) {
            $q->where('sa.payroll_month', $request->month);
        }
        if ($request->filled('status')) {
            $q->where('pr.status', $request->status);
        }

        $items = $q->orderByDesc('sa.id')->paginate(20)->withQueryString();

        return view('salary_advances.index', [
            'items' => $items,
            'statusLabels' => $this->paymentStatusLabels(),
            'canViewAll' => $canViewAll,
        ]);
    }

    public function salaryStore(Request $request)
    {
        $user = auth()->user();
        abort_unless($user, 403);

        $request->merge(['amount' => $this->normalizeAmount($request->amount)]);
        $data = $request->validate([
            'payroll_month' => ['required', 'date_format:Y-m'],
            'amount' => ['required', 'numeric', 'min:1'],
            'reason' => ['required', 'string', 'max:5000'],
            'needed_date' => ['nullable', 'date'],
            'bank_info' => ['nullable', 'string', 'max:3000'],
            'attachment' => ['nullable', 'file', 'max:20480', 'mimes:jpg,jpeg,png,webp,pdf,doc,docx,xls,xlsx'],
        ]);

        $id = DB::transaction(function () use ($data, $request, $user) {
            $paymentRequestId = $this->createPaymentRequest([
                'doc_type' => 'salary_advance',
                'created_by' => (int) $user->id,
                'receiver_name' => (string) ($user->name ?? 'Nhân viên'),
                'department' => $user->department ?? null,
                'company' => $this->companyName(),
                'reason' => $data['reason'],
                'payment_content' => 'Tạm ứng lương '.$data['payroll_month'].' - '.$data['reason'],
                'amount' => $data['amount'],
                'payment_due_date' => $data['needed_date'] ?? null,
                'bank_info' => $data['bank_info'] ?? null,
            ], 'DNTUL');

            DB::table('salary_advance_requests')->insert([
                'payment_request_id' => $paymentRequestId,
                'user_id' => $user->id,
                'payroll_month' => $data['payroll_month'],
                'requested_amount' => $data['amount'],
                'reason' => $data['reason'],
                'needed_date' => $data['needed_date'] ?? null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $this->saveAttachment($paymentRequestId, $request->file('attachment'));
            return $paymentRequestId;
        });

        return redirect()->route('salary_advances.show', $id)
            ->with('success', 'Đã tạo Đề nghị tạm ứng lương. Phiếu đang ở trạng thái nháp.');
    }

    public function salaryShow($id)
    {
        $modulePayment = DB::table('payment_requests')
            ->where('id', $id)
            ->where('doc_type', 'salary_advance')
            ->where('code', 'like', 'DNTUL-%')
            ->first(['id']);
        if (! $modulePayment) {
            $legacy = DB::table('payment_requests')->where('id', $id)->first(['id']);
            abort_unless($legacy, 404);
            return $this->redirectLegacyPaymentRequest($id);
        }

        $item = DB::table('salary_advance_requests as sa')
            ->join('payment_requests as pr', 'pr.id', '=', 'sa.payment_request_id')
            ->leftJoin('users as u', 'u.id', '=', 'sa.user_id')
            ->where('pr.id', $id)
            ->where('pr.code', 'like', 'DNTUL-%')
            ->select('sa.*', 'pr.code', 'pr.doc_type', 'pr.created_by', 'pr.receiver_name', 'pr.department', 'pr.company', 'pr.amount', 'pr.payment_due_date', 'pr.payment_content', 'pr.bank_info', 'pr.status', 'pr.admin_note', 'pr.accounting_note', 'pr.created_at as payment_created_at', 'u.name as employee_name')
            ->first();
        abort_unless($item, 404);
        $this->authorizePaymentRequest($item);

        $approvals = Schema::hasTable('payment_request_approvals')
            ? DB::table('payment_request_approvals as a')
                ->leftJoin('users as u', 'u.id', '=', 'a.actor_id')
                ->where('a.payment_request_id', $id)
                ->select('a.*', 'u.name as actor_name')
                ->orderBy('a.id')
                ->get()
            : collect();
        $attachments = Schema::hasTable('payment_attachments')
            ? DB::table('payment_attachments')->where('payment_request_id', $id)->orderBy('id')->get()
            : collect();

        $user = auth()->user();
        return view('salary_advances.show', [
            'item' => $item,
            'approvals' => $approvals,
            'attachments' => $attachments,
            'statusLabels' => $this->paymentStatusLabels(),
            'isAdmin' => $this->isAdmin($user),
            'isAccounting' => $this->isAccounting($user),
            'isOwner' => (int) $item->created_by === (int) $user->id,
        ]);
    }
}
