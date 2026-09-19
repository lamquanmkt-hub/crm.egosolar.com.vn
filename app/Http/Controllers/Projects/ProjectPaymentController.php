<?php

namespace App\Http\Controllers\Projects;

use App\Http\Controllers\Controller;
use App\Models\ProjectTest\History;
use App\Models\ProjectTest\PaymentAdjustment;
use App\Models\ProjectTest\PaymentMilestone;
use App\Models\ProjectTest\PaymentTransaction;
use App\Models\ProjectTest\Project;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class ProjectPaymentController extends Controller
{
    public function storeMilestone(Request $request, Project $project)
    {
        $this->authorizeFinancialAccess($request, $project);
        $this->authorizePlanManager($request->user(), $project);

        $data = $this->validateMilestone($request);

        // EGO_PROJECT_FINANCE_FIRST_PLAN_UX_V3_1
        $wasFirstMilestone = ! $project->paymentMilestones()->exists();

        $milestone = DB::transaction(function () use ($data, $project, $request): PaymentMilestone {
            $sequence = (int) $project->paymentMilestones()->max('sequence') + 1;

            $milestone = $project->paymentMilestones()->create([
                ...$data,
                'sequence' => $sequence,
                'status' => 'pending',
                'created_by' => $request->user()->id,
                'updated_by' => $request->user()->id,
            ]);

            $this->history($project, 'Tạo đợt thanh toán: '.$milestone->title, [
                'milestone_id' => $milestone->id,
                'amount' => $milestone->amount,
                'due_date' => optional($milestone->due_date)->format('Y-m-d'),
            ]);

            return $milestone;
        });

        $response = back()->with('success', 'Đã thêm đợt thanh toán '.$milestone->title.'.');

        if ($wasFirstMilestone) {
            $response->with('finance_auto_open_payment', true);
            $response->with('finance_auto_milestone_id', $milestone->id);
        }

        return $response;
    }

    public function updateMilestone(Request $request, Project $project, PaymentMilestone $milestone)
    {
        $this->authorizeFinancialAccess($request, $project);
        $this->authorizePlanManager($request->user(), $project);
        $this->assertMilestoneBelongsToProject($milestone, $project);

        $data = $this->validateMilestone($request);
        $committed = $this->milestoneCommittedAmount($milestone);
        abort_if(
            (float) $data['amount'] + 0.01 < $committed,
            422,
            'Không thể giảm đợt xuống '.number_format((float) $data['amount'], 0, ',', '.').' đ vì đã có '.number_format($committed, 0, ',', '.').' đ giao dịch đang chờ/đã xác nhận.'
        );

        DB::transaction(function () use ($data, $project, $request, $milestone): void {
            $milestone->fill($data);
            $milestone->updated_by = $request->user()->id;
            $milestone->save();

            $this->refreshMilestoneStatus($milestone);
            $this->history($project, 'Cập nhật đợt thanh toán: '.$milestone->title, [
                'milestone_id' => $milestone->id,
                'amount' => $milestone->amount,
                'due_date' => optional($milestone->due_date)->format('Y-m-d'),
            ]);
        });

        return back()->with('success', 'Đã cập nhật đợt thanh toán.');
    }

    public function destroyMilestone(Request $request, Project $project, PaymentMilestone $milestone)
    {
        $this->authorizeFinancialAccess($request, $project);
        $this->authorizePlanManager($request->user(), $project);
        $this->assertMilestoneBelongsToProject($milestone, $project);

        abort_if($milestone->transactions()->exists(), 422, 'Đợt thanh toán đã có giao dịch nên không thể xóa.');

        $title = $milestone->title;
        $milestone->delete();
        $this->history($project, 'Xóa đợt thanh toán: '.$title);

        return back()->with('success', 'Đã xóa đợt thanh toán.');
    }

    public function storeTransaction(Request $request, Project $project)
    {
        $this->authorizeFinancialAccess($request, $project);
        $this->authorizeTransactionRecorder($request->user(), $project);

        $data = $request->validate([
            'milestone_id' => ['nullable', 'integer'],
            'paid_at' => ['required', 'date'],
            'amount' => ['required', 'numeric', 'gt:0', 'max:999999999999'],
            'payment_method' => ['required', Rule::in(['bank_transfer', 'cash', 'card', 'offset', 'other'])],
            'receiving_account' => ['nullable', 'string', 'max:255'],
            'reference_no' => ['nullable', 'string', 'max:255'],
            'payer_name' => ['nullable', 'string', 'max:255'],
            'proof_file' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:10240'],
            'note' => ['nullable', 'string', 'max:5000'],
        ]);

        $milestone = null;
        if (! empty($data['milestone_id'])) {
            $milestone = $project->paymentMilestones()->whereKey((int) $data['milestone_id'])->first();
            abort_unless($milestone, 422, 'Đợt thanh toán không thuộc công trình này.');

            $committed = $this->milestoneCommittedAmount($milestone);
            $remaining = max(0, (float) $milestone->amount - $committed);
            abort_if(
                (float) $data['amount'] > $remaining + 0.01,
                422,
                'Đợt "'.$milestone->title.'" chỉ còn '.number_format($remaining, 0, ',', '.').' đ chưa phân bổ. Nếu khách thanh toán vượt/thu trước, hãy chọn “Chưa gắn đợt” để lưu khoản thu chưa phân bổ.'
            );
        }

        $proofPath = null;
        if ($request->hasFile('proof_file')) {
            $proofPath = $request->file('proof_file')->store('project-payments/'.$project->id, 'local');
        }

        $canConfirm = $this->canConfirmTransactions($request->user());

        $transaction = DB::transaction(function () use ($data, $project, $request, $milestone, $proofPath, $canConfirm): PaymentTransaction {
            $transaction = $project->paymentTransactions()->create([
                'milestone_id' => $milestone?->id,
                'transaction_code' => $this->nextTransactionCode($project),
                'paid_at' => $data['paid_at'],
                'amount' => (float) $data['amount'],
                'payment_method' => $data['payment_method'],
                'receiving_account' => $data['receiving_account'] ?? null,
                'reference_no' => $data['reference_no'] ?? null,
                'payer_name' => $data['payer_name'] ?? null,
                'proof_path' => $proofPath,
                'note' => $data['note'] ?? null,
                'status' => $canConfirm ? 'confirmed' : 'pending',
                'recorded_by' => $request->user()->id,
                'confirmed_by' => $canConfirm ? $request->user()->id : null,
                'confirmed_at' => $canConfirm ? now() : null,
            ]);

            $this->syncProjectCollected($project);
            if ($milestone) {
                $this->refreshMilestoneStatus($milestone);
            }

            $this->history($project, $canConfirm ? 'Ghi nhận và xác nhận thu tiền' : 'Sales ghi nhận khách đã thanh toán', [
                'transaction_id' => $transaction->id,
                'transaction_code' => $transaction->transaction_code,
                'milestone_id' => $milestone?->id,
                'amount' => $transaction->amount,
                'status' => $transaction->status,
            ]);

            return $transaction;
        });

        return back()->with(
            'success',
            $transaction->status === 'confirmed'
                ? 'Đã ghi nhận và xác nhận khoản thu '.$transaction->transaction_code.'.'
                : 'Đã ghi nhận giao dịch '.$transaction->transaction_code.', đang chờ Kế toán/Admin xác nhận.'
        );
    }


    /**
     * Sửa trực tiếp số tiền giao dịch.
     * Chỉ Admin / Management / Manager.
     *
     * Giá trị nhập vào là số tiền hiệu lực cuối cùng của giao dịch.
     */
    public function updateTransactionAmount(
        Request $request,
        Project $project,
        PaymentTransaction $transaction
    ) {
        $this->authorizeFinancialAccess(
            $request,
            $project
        );

        $this->assertTransactionBelongsToProject(
            $transaction,
            $project
        );

        abort_unless(
            $this->canConfirmTransactions(
                $request->user()
            ),
            403,
            'Chỉ Admin/Quản lý được sửa trực tiếp số tiền giao dịch.'
        );

        abort_if(
            in_array(
                $transaction->status,
                ['cancelled', 'rejected'],
                true
            ),
            422,
            'Không thể sửa giao dịch đã hủy hoặc bị từ chối.'
        );

        $data = $request->validate([
            'amount' => [
                'required',
                'numeric',
                'gt:0',
                'max:999999999999',
            ],

            'reason' => [
                'required',
                'string',
                'max:2000',
            ],
        ]);

        $requestedAmount =
            (float) $data['amount'];

        /*
         * Nếu trước đây đã có điều chỉnh được duyệt,
         * amount giao dịch gốc phải bù phần delta để
         * số tiền hiệu lực cuối cùng đúng bằng số vừa nhập.
         */
        $approvedDelta =
            (float) PaymentAdjustment::query()
                ->where(
                    'transaction_id',
                    $transaction->id
                )
                ->where(
                    'status',
                    'approved'
                )
                ->sum('delta_amount');

        $newBaseAmount =
            $requestedAmount - $approvedDelta;

        abort_if(
            $newBaseAmount < 0,
            422,
            'Số tiền mới nhỏ hơn tổng điều chỉnh đã duyệt nên không thể cập nhật trực tiếp.'
        );

        /*
         * Không cho sửa khi đang có yêu cầu điều chỉnh chờ duyệt.
         */
        $hasPendingAdjustment =
            PaymentAdjustment::query()
                ->where(
                    'transaction_id',
                    $transaction->id
                )
                ->where(
                    'status',
                    'pending'
                )
                ->exists();

        abort_if(
            $hasPendingAdjustment,
            422,
            'Giao dịch đang có yêu cầu điều chỉnh chờ duyệt. Hãy xử lý yêu cầu đó trước.'
        );

        $oldBaseAmount =
            (float) $transaction->amount;

        $oldEffectiveAmount =
            $oldBaseAmount + $approvedDelta;

        abort_if(
            abs(
                $oldEffectiveAmount
                - $requestedAmount
            ) < 0.01,
            422,
            'Số tiền mới không thay đổi.'
        );

        if ($transaction->milestone) {
            $otherCommitted = $this->milestoneCommittedAmount($transaction->milestone, (int) $transaction->id);
            $remainingForThis = max(0, (float) $transaction->milestone->amount - $otherCommitted);
            abort_if(
                $requestedAmount > $remainingForThis + 0.01,
                422,
                'Số tiền mới vượt phần còn lại của đợt thanh toán ('.number_format($remainingForThis, 0, ',', '.').' đ). Hãy tách phần vượt sang giao dịch “Chưa gắn đợt”.'
            );
        }

        DB::transaction(
            function () use (
                $project,
                $transaction,
                $request,
                $data,
                $oldBaseAmount,
                $oldEffectiveAmount,
                $newBaseAmount,
                $requestedAmount,
                $approvedDelta
            ): void {

                $transaction->amount =
                    $newBaseAmount;

                $transaction->save();

                /*
                 * Cập nhật tổng thu công trình.
                 */
                $this->syncProjectCollected(
                    $project
                );

                /*
                 * Cập nhật trạng thái đợt thanh toán.
                 */
                if ($transaction->milestone) {
                    $this->refreshMilestoneStatus(
                        $transaction->milestone
                    );
                }

                /*
                 * Ghi lịch sử đầy đủ.
                 */
                $this->history(
                    $project,
                    'Sửa số tiền giao dịch '
                    .$transaction->transaction_code,
                    [
                        'transaction_id' =>
                            $transaction->id,

                        'old_base_amount' =>
                            $oldBaseAmount,

                        'old_effective_amount' =>
                            $oldEffectiveAmount,

                        'new_base_amount' =>
                            $newBaseAmount,

                        'new_effective_amount' =>
                            $requestedAmount,

                        'approved_adjustment_delta' =>
                            $approvedDelta,

                        'reason' =>
                            $data['reason'],

                        'updated_by' =>
                            $request->user()->id,
                    ]
                );
            }
        );

        return back()->with(
            'success',
            'Đã cập nhật số tiền giao dịch '
            .$transaction->transaction_code
            .' thành '
            .number_format(
                $requestedAmount,
                0,
                ',',
                '.'
            )
            .' đ.'
        );
    }
    public function confirmTransaction(Request $request, Project $project, PaymentTransaction $transaction)
    {
        $this->authorizeFinancialAccess($request, $project);
        abort_unless($this->canConfirmTransactions($request->user()), 403, 'Chỉ Kế toán/Admin được xác nhận tiền đã vào tài khoản.');
        $this->assertTransactionBelongsToProject($transaction, $project);
        abort_unless($transaction->status === 'pending', 422, 'Chỉ giao dịch đang chờ mới được xác nhận.');

        if ($transaction->milestone) {
            $otherCommitted = $this->milestoneCommittedAmount($transaction->milestone, (int) $transaction->id);
            $remainingForThis = max(0, (float) $transaction->milestone->amount - $otherCommitted);
            abort_if(
                (float) $transaction->amount > $remainingForThis + 0.01,
                422,
                'Không thể xác nhận vì giao dịch vượt phần còn lại của đợt ('.number_format($remainingForThis, 0, ',', '.').' đ). Hãy điều chỉnh hoặc chuyển phần vượt sang giao dịch chưa phân bổ.'
            );
        }

        DB::transaction(function () use ($project, $request, $transaction): void {
            $transaction->status = 'confirmed';
            $transaction->confirmed_by = $request->user()->id;
            $transaction->confirmed_at = now();
            $transaction->rejection_reason = null;
            $transaction->save();

            $this->syncProjectCollected($project);
            if ($transaction->milestone) {
                $this->refreshMilestoneStatus($transaction->milestone);
            }

            $this->history($project, 'Xác nhận giao dịch thu tiền '.$transaction->transaction_code, [
                'transaction_id' => $transaction->id,
                'amount' => $transaction->amount,
            ]);
        });

        return back()->with('success', 'Đã xác nhận giao dịch thu tiền.');
    }

    public function rejectTransaction(Request $request, Project $project, PaymentTransaction $transaction)
    {
        $this->authorizeFinancialAccess($request, $project);
        abort_unless($this->canConfirmTransactions($request->user()), 403, 'Chỉ Kế toán/Admin được từ chối giao dịch.');
        $this->assertTransactionBelongsToProject($transaction, $project);
        abort_unless($transaction->status === 'pending', 422, 'Chỉ giao dịch đang chờ mới được từ chối.');

        $data = $request->validate([
            'reason' => ['required', 'string', 'max:2000'],
        ]);

        $transaction->status = 'rejected';
        $transaction->rejection_reason = $data['reason'];
        $transaction->save();

        if ($transaction->milestone) {
            $this->refreshMilestoneStatus($transaction->milestone);
        }

        $this->history($project, 'Từ chối giao dịch thu tiền '.$transaction->transaction_code, [
            'transaction_id' => $transaction->id,
            'reason' => $data['reason'],
        ]);

        return back()->with('success', 'Đã từ chối giao dịch và lưu lý do.');
    }

    // EGO_PROJECT_FINANCE_FLOW_V3_ADJUSTMENTS
    public function cancelTransaction(Request $request, Project $project, PaymentTransaction $transaction)
    {
        $this->authorizeFinancialAccess($request, $project);
        $this->assertTransactionBelongsToProject($transaction, $project);

        abort_unless(
            $transaction->status === 'pending',
            422,
            'Giao dịch đã được xử lý nên không thể sửa hoặc hủy trực tiếp. Hãy lập yêu cầu điều chỉnh riêng.'
        );

        $user = $request->user();
        $canCancel = $this->canConfirmTransactions($user)
            || (int) $transaction->recorded_by === (int) $user->id;
        abort_unless($canCancel, 403, 'Bạn không được hủy giao dịch này.');

        $data = $request->validate([
            'reason' => ['required', 'string', 'max:2000'],
        ]);

        DB::transaction(function () use ($data, $project, $request, $transaction): void {
            $transaction->status = 'cancelled';
            $transaction->cancelled_by = $request->user()->id;
            $transaction->cancelled_at = now();
            $transaction->cancellation_reason = $data['reason'];
            $transaction->save();

            if ($transaction->milestone) {
                $this->refreshMilestoneStatus($transaction->milestone);
            }

            $this->history($project, 'Hủy giao dịch chờ xác nhận '.$transaction->transaction_code, [
                'transaction_id' => $transaction->id,
                'amount' => $transaction->amount,
                'reason' => $data['reason'],
            ]);
        });

        return back()->with('success', 'Đã hủy giao dịch chờ xác nhận. Lịch sử vẫn được giữ để đối soát.');
    }

    public function storeAdjustment(Request $request, Project $project)
    {
        $this->authorizeFinancialAccess($request, $project);
        $this->authorizeTransactionRecorder($request->user(), $project);

        $data = $request->validate([
            'transaction_id' => ['required', 'integer'],
            'correct_amount' => ['required', 'numeric', 'min:0', 'max:999999999999'],
            'reason' => ['required', 'string', 'max:3000'],
        ]);

        $transaction = $project->paymentTransactions()
            ->whereKey((int) $data['transaction_id'])
            ->first();

        abort_unless($transaction, 422, 'Giao dịch không thuộc công trình này.');
        abort_unless($transaction->status === 'confirmed', 422, 'Chỉ giao dịch đã xác nhận mới được lập điều chỉnh.');

        $hasPending = PaymentAdjustment::query()
            ->where('transaction_id', $transaction->id)
            ->where('status', 'pending')
            ->exists();
        abort_if($hasPending, 422, 'Giao dịch này đang có một yêu cầu điều chỉnh chờ duyệt.');

        $approvedDelta = (float) PaymentAdjustment::query()
            ->where('transaction_id', $transaction->id)
            ->where('status', 'approved')
            ->sum('delta_amount');
        $currentAmount = max(0, (float) $transaction->amount + $approvedDelta);
        $correctAmount = max(0, (float) $data['correct_amount']);
        $delta = $correctAmount - $currentAmount;

        abort_if(abs($delta) < 0.01, 422, 'Số tiền đúng không thay đổi so với giá trị hiện tại.');

        $adjustment = DB::transaction(function () use ($project, $transaction, $request, $data, $currentAmount, $correctAmount, $delta): PaymentAdjustment {
            $adjustment = PaymentAdjustment::create([
                'project_id' => $project->id,
                'transaction_id' => $transaction->id,
                'adjustment_code' => $this->nextAdjustmentCode($project),
                'original_amount' => $currentAmount,
                'correct_amount' => $correctAmount,
                'delta_amount' => $delta,
                'reason' => $data['reason'],
                'status' => 'pending',
                'requested_by' => $request->user()->id,
            ]);

            $this->history($project, 'Tạo yêu cầu điều chỉnh '.$adjustment->adjustment_code, [
                'adjustment_id' => $adjustment->id,
                'transaction_id' => $transaction->id,
                'delta_amount' => $delta,
                'reason' => $data['reason'],
            ]);

            return $adjustment;
        });

        return back()->with('success', 'Đã tạo yêu cầu điều chỉnh '.$adjustment->adjustment_code.', chờ Kế toán/Admin duyệt.');
    }

    public function approveAdjustment(Request $request, Project $project, PaymentAdjustment $adjustment)
    {
        $this->authorizeFinancialAccess($request, $project);
        abort_unless($this->canConfirmTransactions($request->user()), 403, 'Chỉ Kế toán/Admin được duyệt điều chỉnh.');
        $this->assertAdjustmentBelongsToProject($adjustment, $project);
        abort_unless($adjustment->status === 'pending', 422, 'Yêu cầu điều chỉnh đã được xử lý.');

        DB::transaction(function () use ($project, $request, $adjustment): void {
            $adjustment->status = 'approved';
            $adjustment->reviewed_by = $request->user()->id;
            $adjustment->reviewed_at = now();
            $adjustment->review_note = null;
            $adjustment->save();

            $this->syncProjectCollected($project);
            if ($adjustment->transaction?->milestone) {
                $this->refreshMilestoneStatus($adjustment->transaction->milestone);
            }

            $this->history($project, 'Duyệt điều chỉnh '.$adjustment->adjustment_code, [
                'adjustment_id' => $adjustment->id,
                'transaction_id' => $adjustment->transaction_id,
                'delta_amount' => $adjustment->delta_amount,
            ]);
        });

        return back()->with('success', 'Đã duyệt điều chỉnh và cập nhật lại công nợ.');
    }

    public function rejectAdjustment(Request $request, Project $project, PaymentAdjustment $adjustment)
    {
        $this->authorizeFinancialAccess($request, $project);
        abort_unless($this->canConfirmTransactions($request->user()), 403, 'Chỉ Kế toán/Admin được từ chối điều chỉnh.');
        $this->assertAdjustmentBelongsToProject($adjustment, $project);
        abort_unless($adjustment->status === 'pending', 422, 'Yêu cầu điều chỉnh đã được xử lý.');

        $data = $request->validate([
            'review_note' => ['required', 'string', 'max:3000'],
        ]);

        $adjustment->status = 'rejected';
        $adjustment->reviewed_by = $request->user()->id;
        $adjustment->reviewed_at = now();
        $adjustment->review_note = $data['review_note'];
        $adjustment->save();

        $this->history($project, 'Từ chối điều chỉnh '.$adjustment->adjustment_code, [
            'adjustment_id' => $adjustment->id,
            'reason' => $data['review_note'],
        ]);

        return back()->with('success', 'Đã từ chối yêu cầu điều chỉnh.');
    }

    public function proof(Request $request, Project $project, PaymentTransaction $transaction)
    {
        $this->authorizeFinancialAccess($request, $project);
        $this->assertTransactionBelongsToProject($transaction, $project);
        abort_unless($transaction->proof_path && Storage::disk('local')->exists($transaction->proof_path), 404);

        return Storage::disk('local')->download(
            $transaction->proof_path,
            basename($transaction->proof_path)
        );
    }

    private function validateMilestone(Request $request): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'percentage' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'amount' => ['required', 'numeric', 'gt:0', 'max:999999999999'],
            'due_date' => ['nullable', 'date'],
            'condition_text' => ['nullable', 'string', 'max:3000'],
            'note' => ['nullable', 'string', 'max:3000'],
        ]);
    }

    /* EGO_PROJECT_WORKFLOW_360_V2_FINANCE_ACCESS */
    private function authorizeFinancialAccess(Request $request, Project $project): void
    {
        $user = $request->user();
        abort_unless($user, 403);
        abort_unless(Project::query()->visibleTo($user)->whereKey($project->id)->exists(), 403);
        abort_unless(
            ($project->request_source ?? 'sales') === 'sales',
            403,
            'Công trình Kỹ thuật nội bộ không có quản lý doanh thu.'
        );

        $isAdmin = $user->hasAnyRole(['admin', 'management', 'manager']);
        $isSalesManager = $user->hasRole('sales_manager');
        $isAssignedSales = $user->hasAnyRole(['sales', 'sales_staff'])
            && (
                (int) $project->sales_user_id === (int) $user->id
                || (int) $project->created_by === (int) $user->id
            );

        abort_unless(
            $isAdmin || $isSalesManager || $isAssignedSales,
            403,
            'Chỉ Sales phụ trách và Admin được truy cập doanh thu công trình.'
        );
    }
    private function authorizePlanManager(User $user, Project $project): void
    {
        $isAdmin = $user->hasAnyRole(['admin', 'management', 'manager']);
        $isSalesManager = $user->hasRole('sales_manager');
        $isAssignedSales = $user->hasAnyRole(['sales', 'sales_staff'])
            && (
                (int) $project->sales_user_id === (int) $user->id
                || (int) $project->created_by === (int) $user->id
            );

        abort_unless(
            $isAdmin || $isSalesManager || $isAssignedSales,
            403,
            'Chỉ Sales phụ trách và Admin được quản lý kế hoạch thanh toán.'
        );
    }
    private function authorizeTransactionRecorder(User $user, Project $project): void
    {
        $this->authorizePlanManager($user, $project);
    }

    private function canConfirmTransactions(User $user): bool
    {
        return $user->hasAnyRole(['admin', 'management', 'manager']);
    }

    private function assertMilestoneBelongsToProject(PaymentMilestone $milestone, Project $project): void
    {
        abort_unless((int) $milestone->project_id === (int) $project->id, 404);
    }

    private function assertAdjustmentBelongsToProject(PaymentAdjustment $adjustment, Project $project): void
    {
        abort_unless((int) $adjustment->project_id === (int) $project->id, 404);
    }
    private function assertTransactionBelongsToProject(PaymentTransaction $transaction, Project $project): void
    {
        abort_unless((int) $transaction->project_id === (int) $project->id, 404);
    }

    private function syncProjectCollected(Project $project): void
    {
        $confirmed = (float) $project->paymentTransactions()
            ->where('status', 'confirmed')
            ->sum('amount');
        $adjusted = (float) PaymentAdjustment::query()
            ->where('project_id', $project->id)
            ->where('status', 'approved')
            ->sum('delta_amount');

        $project->amount_collected = max(0, $confirmed + $adjusted);
        $project->financial_updated_by = auth()->id();
        $project->financial_updated_at = now();
        $project->save();
    }

    private function refreshMilestoneStatus(PaymentMilestone $milestone): void
    {
        $confirmed = (float) $milestone->transactions()
            ->where('status', 'confirmed')
            ->sum('amount');
        $adjusted = (float) PaymentAdjustment::query()
            ->where('project_id', $milestone->project_id)
            ->where('status', 'approved')
            ->whereHas('transaction', function ($query) use ($milestone): void {
                $query->where('milestone_id', $milestone->id);
            })
            ->sum('delta_amount');
        $confirmed += $adjusted;
        $amount = (float) $milestone->amount;

        if ($amount > 0 && $confirmed >= $amount) {
            $status = 'paid';
        } elseif ($confirmed > 0) {
            $status = 'partial';
        } elseif ($milestone->due_date && $milestone->due_date->isPast()) {
            $status = 'overdue';
        } else {
            $status = 'pending';
        }

        if ($milestone->status !== $status) {
            $milestone->status = $status;
            $milestone->save();
        }
    }

    private function milestoneCommittedAmount(PaymentMilestone $milestone, ?int $excludeTransactionId = null): float
    {
        $baseQuery = $milestone->transactions()->whereIn('status', ['pending', 'confirmed']);
        if ($excludeTransactionId) {
            $baseQuery->where('id', '!=', $excludeTransactionId);
        }
        $base = (float) $baseQuery->sum('amount');

        $adjustmentQuery = PaymentAdjustment::query()
            ->where('project_id', $milestone->project_id)
            ->where('status', 'approved')
            ->whereHas('transaction', function ($query) use ($milestone, $excludeTransactionId): void {
                $query->where('milestone_id', $milestone->id)->where('status', 'confirmed');
                if ($excludeTransactionId) {
                    $query->where('id', '!=', $excludeTransactionId);
                }
            });

        return max(0, $base + (float) $adjustmentQuery->sum('delta_amount'));
    }

    private function nextAdjustmentCode(Project $project): string
    {
        $prefix = 'DC-'.$project->id.'-'.now()->format('Ymd').'-';
        $last = PaymentAdjustment::query()
            ->where('adjustment_code', 'like', $prefix.'%')
            ->lockForUpdate()
            ->orderByDesc('id')
            ->value('adjustment_code');
        $number = $last ? ((int) substr($last, -3)) + 1 : 1;

        return $prefix.str_pad((string) $number, 3, '0', STR_PAD_LEFT);
    }
    private function nextTransactionCode(Project $project): string
    {
        $prefix = 'PT-'.$project->id.'-'.now()->format('Ymd').'-';
        $last = PaymentTransaction::query()
            ->where('transaction_code', 'like', $prefix.'%')
            ->lockForUpdate()
            ->orderByDesc('id')
            ->value('transaction_code');
        $number = $last ? ((int) substr($last, -3)) + 1 : 1;

        return $prefix.str_pad((string) $number, 3, '0', STR_PAD_LEFT);
    }

    private function history(Project $project, string $action, array $meta = []): void
    {
        History::create([
            'project_id' => $project->id,
            'user_id' => auth()->id(),
            'action' => $action,
            'from_status' => $project->status,
            'to_status' => $project->status,
            'note' => $action,
            'meta' => $meta ?: null,
        ]);
    }
}
