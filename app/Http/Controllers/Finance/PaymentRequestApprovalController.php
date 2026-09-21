<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Models\Payments\PaymentRequest;
use App\Models\Payments\PaymentRequestApproval;
use App\Models\Payments\PaymentRequestEditLog;
use App\Services\Finance\PaymentAdvanceService;
use App\Services\Payments\PaymentRequestAuditLogger;
use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Controller xử lý luồng phê duyệt phiếu đề nghị thanh toán.
 */
class PaymentRequestApprovalController extends Controller
{
    /**
     * Xác định Admin (tương thích nhiều kiểu hệ thống: role column / is_admin / spatie)
     */
    private function isAdmin($user): bool
    {
        return $user !== null
            && method_exists($user, 'isAdmin')
            && $user->isAdmin();
    }

    /**
     * Chặn tự duyệt phiếu do chính mình tạo.
     *
     * Admin/Giám đốc được phép tự duyệt (yêu cầu nghiệp vụ); nhân sự khác
     * thì không, kể cả khi có role duyệt.
     */
    private function abortIfSelfApproval(PaymentRequest $item): void
    {
        $user = auth()->user();

        if ($user && ! $this->isAdmin($user) && (int) $item->created_by === (int) $user->id) {
            abort(403, 'Bạn không được tự duyệt phiếu do chính mình tạo.');
        }
    }

    /**
     * Xác định Kế toán (tương thích spatie hoặc role column)
     */
    private function isAccounting($user): bool
    {
        if (! $user) {
            return false;
        }

        $roles = ['accounting', 'ketoan', 'ke_toan'];

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

        $rawRole = strtolower((string) ($user->role ?? ''));
        $email = strtolower((string) ($user->email ?? ''));

        return in_array($rawRole, $roles, true) || str_contains($email, 'ketoan');
    }


    /**
     * Quản lý tài chính: Admin/Management hoặc role quản lý tài chính chuyên biệt.
     */
    private function isFinanceManager($user): bool
    {
        if (! $user) {
            return false;
        }

        if ($this->isAdmin($user)) {
            return true;
        }

        $roles = ['management', 'finance_manager', 'accounting_manager', 'financial_manager', 'quan_ly_tai_chinh', 'quanlytaichinh'];

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

        return in_array(strtolower((string) ($user->role ?? '')), $roles, true);
    }

    /* EGO_PAYMENT_ADVANCE_REDIRECT_V1 */
    private function redirectToItem(PaymentRequest $item)
    {
        if ($item->doc_type === 'advance' && Route::has('payment_advances.show')) {
            return redirect()->route('payment_advances.show', $item->id);
        }
        if ($item->doc_type === 'salary_advance' && Route::has('salary_advances.show')) {
            return redirect()->route('salary_advances.show', $item->id);
        }
        return redirect()->route('payment_requests.show', $item->id);
    }
    // User gửi duyệt
    /**
     * Chủ phiếu gửi phiếu đề nghị thanh toán đi duyệt.
     */
    public function submit(Request $request, $id)
    {
        $item = PaymentRequest::findOrFail($id);
        $user = auth()->user();

        // chỉ chủ phiếu được submit
        if ((int) $item->created_by !== (int) $user->id) {
            abort(403);
        }

        // chỉ submit khi draft hoặc bị reject
        if (! in_array($item->status, ['draft', 'admin_rejected', 'accounting_rejected'], true)) {
            // web -> quay lại trang trước
            return back()->with('error', 'Không thể gửi duyệt ở trạng thái này');
        }

        $item->update(['status' => 'submitted']);

        PaymentRequestApproval::create([
            'payment_request_id' => $item->id,
            'actor_id' => $user->id,
            'step' => 'submit',
            'action' => 'submitted',
            'note' => null,
        ]);

        // ✅ QUAN TRỌNG: redirect về trang chi tiết để "view"
        return $this->redirectToItem($item)
            ->with('success', 'Đã gửi Quản lý tài chính duyệt');
    }

    // =========================
    // Admin duyệt
    // =========================
    /**
     * Quản lý tài chính duyệt phiếu đang ở trạng thái submitted.
     */
    public function adminApprove(Request $request, $id)
    {
        abort_unless($this->isFinanceManager(auth()->user()), 403);

        $item = PaymentRequest::findOrFail($id);

        $this->abortIfSelfApproval($item);

        if ($item->status !== 'submitted') {
            return response()->json(['message' => 'Chỉ duyệt khi đang chờ Quản lý tài chính'], 422);
        }

        $note = $request->input('note');
        $statusBefore = (string) $item->status;

        // NGUYÊN TỬ: đổi trạng thái + lịch sử duyệt + nhật ký cùng transaction.
        DB::transaction(function () use ($item, $note, $statusBefore): void {
            $item->update([
                'status' => 'admin_approved',
                'admin_approved_by' => auth()->id(),
                'admin_approved_at' => now(),
                'admin_note' => $note,
            ]);

            PaymentRequestApproval::create([
                'payment_request_id' => $item->id,
                'actor_id' => auth()->id(),
                'step' => 'admin',
                'action' => 'approved',
                'note' => $note,
            ]);

            PaymentRequestAuditLogger::logAction(
                (int) $item->id,
                (string) $item->code,
                PaymentRequestEditLog::ACTION_APPROVE,
                $statusBefore,
                (string) $item->status,
                $note,
            );
        });

        return $this->redirectToItem($item);

    }

    // =========================
    // Admin từ chối
    // =========================
    /**
     * Quản lý tài chính từ chối phiếu kèm lý do.
     */
    public function adminReject(Request $request, $id)
    {
        abort_unless($this->isFinanceManager(auth()->user()), 403);

        $item = PaymentRequest::findOrFail($id);

        if ($item->status !== 'submitted') {
            return response()->json(['message' => 'Chỉ từ chối khi đang chờ Quản lý tài chính'], 422);
        }

        $validated = $request->validate([
            'note' => ['required', 'string', 'min:2', 'max:2000'],
        ], [
            'note.required' => 'Vui lòng nhập lý do từ chối.',
            'note.min' => 'Lý do từ chối quá ngắn.',
            'note.max' => 'Lý do từ chối không được quá 2000 ký tự.',
        ]);

        $note = trim($validated['note']);

        $statusBefore = (string) $item->status;

        // NGUYÊN TỬ: đổi trạng thái + lịch sử duyệt + nhật ký cùng transaction.
        DB::transaction(function () use ($item, $note, $statusBefore): void {
            $item->update([
                'status' => 'admin_rejected',
                'admin_approved_by' => auth()->id(),
                'admin_approved_at' => now(),
                'admin_note' => $note,
            ]);

            PaymentRequestApproval::create([
                'payment_request_id' => $item->id,
                'actor_id' => auth()->id(),
                'step' => 'admin',
                'action' => 'rejected',
                'note' => $note,
            ]);

            PaymentRequestAuditLogger::logAction(
                (int) $item->id,
                (string) $item->code,
                PaymentRequestEditLog::ACTION_REJECT,
                $statusBefore,
                (string) $item->status,
                $note,
            );
        });

        return $this->redirectToItem($item);

    }

    // =========================
    // Kế toán duyệt
    // =========================
    /**
     * Kế toán xác nhận đã chi phiếu đã được quản lý duyệt.
     */
    public function accApprove(Request $request, $id)
    {
        abort_unless($this->isAccounting(auth()->user()), 403);

        $item = PaymentRequest::findOrFail($id);

        $this->abortIfSelfApproval($item);

        if ($item->status !== 'admin_approved') {
            return response()->json(['message' => 'Chỉ kế toán duyệt khi admin_approved'], 422);
        }

        $note = $request->input('note');
        $statusBefore = (string) $item->status;

        // NGUYÊN TỬ: đổi trạng thái + lịch sử duyệt + nhật ký cùng transaction.
        DB::transaction(function () use ($item, $note, $statusBefore): void {
            $item->update([
                'status' => 'accounting_approved',
                'accounting_approved_by' => auth()->id(),
                'accounting_approved_at' => now(),
                'accounting_note' => $note,
            ]);

            PaymentRequestApproval::create([
                'payment_request_id' => $item->id,
                'actor_id' => auth()->id(),
                'step' => 'accounting',
                'action' => 'approved',
                'note' => $note,
            ]);

            PaymentRequestAuditLogger::logAction(
                (int) $item->id,
                (string) $item->code,
                PaymentRequestEditLog::ACTION_APPROVE,
                $statusBefore,
                (string) $item->status,
                $note,
            );

            /* EGO_PAYMENT_ADVANCE_AFTER_ACCOUNTING_V1 */
            PaymentAdvanceService::afterAccountingApproved($item);
        });

        return $this->redirectToItem($item);
    }

    // =========================
    // Kế toán từ chối
    // =========================
    /**
     * Kế toán từ chối phiếu kèm lý do.
     */
    public function accReject(Request $request, $id)
    {
        abort_unless($this->isAccounting(auth()->user()), 403);

        $item = PaymentRequest::findOrFail($id);

        if ($item->status !== 'admin_approved') {
            return response()->json(['message' => 'Chỉ kế toán từ chối khi admin_approved'], 422);
        }

        $validated = $request->validate([
            'note' => ['required', 'string', 'min:2', 'max:2000'],
        ], [
            'note.required' => 'Vui lòng nhập lý do từ chối.',
            'note.min' => 'Lý do từ chối quá ngắn.',
            'note.max' => 'Lý do từ chối không được quá 2000 ký tự.',
        ]);

        $note = trim($validated['note']);

        $statusBefore = (string) $item->status;

        // NGUYÊN TỬ: đổi trạng thái + lịch sử duyệt + nhật ký cùng transaction.
        DB::transaction(function () use ($item, $note, $statusBefore): void {
            $item->update([
                'status' => 'accounting_rejected',
                'accounting_approved_by' => auth()->id(),
                'accounting_approved_at' => now(),
                'accounting_note' => $note,
            ]);

            PaymentRequestAuditLogger::logAction(
                (int) $item->id,
                (string) $item->code,
                PaymentRequestEditLog::ACTION_REJECT,
                $statusBefore,
                (string) $item->status,
                $note,
            );

            PaymentRequestApproval::create([
                'payment_request_id' => $item->id,
                'actor_id' => auth()->id(),
                'step' => 'accounting',
                'action' => 'rejected',
                'note' => $note,
            ]);
        });

        return $this->redirectToItem($item);
    }

    /**
     * Duyệt nhiều đề nghị thanh toán trong một lần.
     *
     * - Quản lý tài chính/Admin: submitted -> admin_approved
     * - Kế toán: admin_approved -> accounting_approved
     * - Người có cả hai quyền có thể xử lý các phiếu ở cả hai bước,
     *   nhưng mỗi phiếu chỉ tiến đúng một bước theo trạng thái ban đầu.
     */
    public function bulkApprove(Request $request)
    {
        $user = auth()->user();
        $canAdminApprove = $this->isFinanceManager($user);
        $canAccountingApprove = $this->isAccounting($user);

        abort_unless($canAdminApprove || $canAccountingApprove, 403);

        $validated = $request->validate([
            'ids' => ['required', 'array', 'min:1', 'max:200'],
            'ids.*' => ['required', 'integer', 'distinct', 'min:1'],
            'note' => ['nullable', 'string', 'max:2000'],
        ], [
            'ids.required' => 'Vui lòng chọn ít nhất một đề nghị thanh toán.',
            'ids.array' => 'Danh sách đề nghị thanh toán không hợp lệ.',
            'ids.min' => 'Vui lòng chọn ít nhất một đề nghị thanh toán.',
            'ids.max' => 'Mỗi lần chỉ được duyệt tối đa 200 đề nghị thanh toán.',
            'ids.*.integer' => 'Mã đề nghị thanh toán không hợp lệ.',
            'ids.*.distinct' => 'Danh sách đề nghị thanh toán bị trùng.',
            'note.max' => 'Ghi chú không được dài quá 2000 ký tự.',
        ]);

        $ids = collect($validated['ids'])
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->values();

        $note = trim((string) ($validated['note'] ?? ''));
        $note = $note !== '' ? $note : null;

        $result = DB::transaction(function () use (
            $ids,
            $note,
            $canAdminApprove,
            $canAccountingApprove,
            $user
        ) {
            $items = PaymentRequest::query()
                ->whereIn('id', $ids->all())
                ->lockForUpdate()
                ->get();

            $adminApproved = 0;
            $accountingApproved = 0;
            $processedIds = [];
            $approvedAt = now();

            foreach ($items as $item) {
                // Không được tự duyệt phiếu do chính mình tạo (trừ Admin/Giám đốc).
                if (! $this->isAdmin($user) && (int) $item->created_by === (int) $user->id) {
                    continue;
                }

                $bulkStatusBefore = (string) $item->status;

                // Không cho một phiếu nhảy qua hai bước trong cùng một lần bấm.
                if ($item->status === 'submitted' && $canAdminApprove) {
                    $item->update([
                        'status' => 'admin_approved',
                        'admin_approved_by' => $user->id,
                        'admin_approved_at' => $approvedAt,
                        'admin_note' => $note,
                    ]);

                    PaymentRequestApproval::create([
                        'payment_request_id' => $item->id,
                        'actor_id' => $user->id,
                        'step' => 'admin',
                        'action' => 'approved',
                        'note' => $note,
                    ]);

                    PaymentRequestAuditLogger::logAction(
                        (int) $item->id,
                        (string) $item->code,
                        PaymentRequestEditLog::ACTION_APPROVE,
                        $bulkStatusBefore,
                        (string) $item->status,
                        $note,
                    );

                    $adminApproved++;
                    $processedIds[] = (int) $item->id;

                    continue;
                }

                if ($item->status === 'admin_approved' && $canAccountingApprove) {
                    $item->update([
                        'status' => 'accounting_approved',
                        'accounting_approved_by' => $user->id,
                        'accounting_approved_at' => $approvedAt,
                        'accounting_note' => $note,
                    ]);

                    PaymentRequestApproval::create([
                        'payment_request_id' => $item->id,
                        'actor_id' => $user->id,
                        'step' => 'accounting',
                        'action' => 'approved',
                        'note' => $note,
                    ]);

                    /* EGO_PAYMENT_ADVANCE_BULK_AFTER_ACCOUNTING_V1 */
                    PaymentAdvanceService::afterAccountingApproved($item);

                    PaymentRequestAuditLogger::logAction(
                        (int) $item->id,
                        (string) $item->code,
                        PaymentRequestEditLog::ACTION_APPROVE,
                        $bulkStatusBefore,
                        (string) $item->status,
                        $note,
                    );

                    $accountingApproved++;
                    $processedIds[] = (int) $item->id;
                }
            }

            return [
                'admin_approved' => $adminApproved,
                'accounting_approved' => $accountingApproved,
                'processed_ids' => array_values(array_unique($processedIds)),
            ];
        });

        $processedCount = count($result['processed_ids']);
        $skippedCount = max(0, $ids->count() - $processedCount);

        if ($processedCount === 0) {
            return back()->with(
                'error',
                'Không có phiếu nào được duyệt. Có thể trạng thái đã thay đổi hoặc bạn không có quyền xử lý các phiếu đã chọn.'
            );
        }

        $details = [];

        if ($result['admin_approved'] > 0) {
            $details[] = 'quản lý duyệt '.$result['admin_approved'].' phiếu';
        }

        if ($result['accounting_approved'] > 0) {
            $details[] = 'kế toán xác nhận đã chi '.$result['accounting_approved'].' phiếu';
        }

        $message = 'Đã xử lý thành công '.$processedCount.' phiếu ('.implode(', ', $details).').';

        if ($skippedCount > 0) {
            $message .= ' Bỏ qua '.$skippedCount.' phiếu do trạng thái đã thay đổi hoặc không đúng bước duyệt.';
        }

        return back()->with('success', $message);
    }
}
