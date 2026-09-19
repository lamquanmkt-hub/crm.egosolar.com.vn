<?php

namespace App\Http\Controllers\Projects;

use App\Http\Controllers\Controller;
use App\Models\ProjectTest\History;
use App\Models\ProjectTest\Project;
use App\Models\ProjectTest\ProjectExpense;
use App\Models\User;
use App\Support\EgoCompanyLock;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class ProjectExpenseController extends Controller
{
    private const CATEGORIES = [
        'transport',
        'external_labor',
        'commission',
        'loading',
        'equipment_rental',
        'subcontractor',
        'travel',
        'fees',
        'other',
    ];

    public function store(Request $request, Project $project)
    {
        $this->authorizeExpenseAccess($request, $project);
        abort_unless($this->canCreateExpense($request->user(), $project), 403, 'Bạn không được nhập chi phí cho công trình này.');

        $data = $this->validateExpense($request);
        $proofPath = $this->storeProof($request, $project);
        $autoConfirm = $this->canReviewExpense($request->user());

        $expense = DB::transaction(function () use ($data, $project, $request, $proofPath, $autoConfirm): ProjectExpense {
            $expense = $project->expenses()->create([
                'expense_code' => $this->nextExpenseCode($project),
                'expense_date' => $data['expense_date'],
                'category' => $data['category'],
                'description' => trim($data['description']),
                'amount' => (float) $data['amount'],
                'payee_name' => $data['payee_name'] ?? null,
                'note' => $data['note'] ?? null,
                'proof_path' => $proofPath,
                'status' => $autoConfirm ? 'confirmed' : 'pending',
                'created_by' => $request->user()->id,
                'confirmed_by' => $autoConfirm ? $request->user()->id : null,
                'confirmed_at' => $autoConfirm ? now() : null,
            ]);

            $this->history($project, ($autoConfirm ? 'Ghi nhận chi phí đã xác nhận ' : 'Gửi chi phí chờ xác nhận ').$expense->expense_code, [
                'expense_id' => $expense->id,
                'category' => $expense->category,
                'amount' => (float) $expense->amount,
                'status' => $expense->status,
            ]);

            return $expense;
        });

        return back()->with(
            'success',
            $expense->status === 'confirmed'
                ? 'Đã ghi nhận chi phí '.$expense->expense_code.'.'
                : 'Đã gửi chi phí '.$expense->expense_code.' chờ Admin/Kế toán xác nhận.'
        );
    }

    public function update(Request $request, Project $project, ProjectExpense $expense)
    {
        $this->authorizeExpenseAccess($request, $project);
        $this->assertExpenseBelongsToProject($expense, $project);
        abort_unless($expense->status === 'pending', 422, 'Chỉ khoản chi phí đang chờ xác nhận mới được sửa.');

        $user = $request->user();
        abort_unless(
            (int) $expense->created_by === (int) $user->id || $this->canReviewExpense($user),
            403,
            'Bạn không được sửa khoản chi phí này.'
        );

        $data = $this->validateExpense($request);
        $newProof = $this->storeProof($request, $project);

        DB::transaction(function () use ($data, $project, $expense, $newProof): void {
            $before = [
                'expense_date' => optional($expense->expense_date)->format('Y-m-d'),
                'category' => $expense->category,
                'description' => $expense->description,
                'amount' => (float) $expense->amount,
                'payee_name' => $expense->payee_name,
            ];

            $expense->expense_date = $data['expense_date'];
            $expense->category = $data['category'];
            $expense->description = trim($data['description']);
            $expense->amount = (float) $data['amount'];
            $expense->payee_name = $data['payee_name'] ?? null;
            $expense->note = $data['note'] ?? null;
            if ($newProof) {
                $expense->proof_path = $newProof;
            }
            $expense->save();

            $this->history($project, 'Cập nhật chi phí '.$expense->expense_code, [
                'expense_id' => $expense->id,
                'before' => $before,
                'after' => [
                    'expense_date' => optional($expense->expense_date)->format('Y-m-d'),
                    'category' => $expense->category,
                    'description' => $expense->description,
                    'amount' => (float) $expense->amount,
                    'payee_name' => $expense->payee_name,
                ],
            ]);
        });

        return back()->with('success', 'Đã cập nhật khoản chi phí '.$expense->expense_code.'.');
    }

    public function confirm(Request $request, Project $project, ProjectExpense $expense)
    {
        $this->authorizeExpenseAccess($request, $project);
        abort_unless($this->canReviewExpense($request->user()), 403, 'Chỉ Admin/Kế toán/Quản lý được xác nhận chi phí.');
        $this->assertExpenseBelongsToProject($expense, $project);
        abort_unless($expense->status === 'pending', 422, 'Khoản chi phí không còn ở trạng thái chờ xác nhận.');

        DB::transaction(function () use ($request, $project, $expense): void {
            $expense->status = 'confirmed';
            $expense->confirmed_by = $request->user()->id;
            $expense->confirmed_at = now();
            $expense->rejected_by = null;
            $expense->rejected_at = null;
            $expense->rejection_reason = null;
            $expense->save();

            $this->history($project, 'Xác nhận chi phí '.$expense->expense_code, [
                'expense_id' => $expense->id,
                'amount' => (float) $expense->amount,
            ]);
        });

        return back()->with('success', 'Đã xác nhận chi phí '.$expense->expense_code.'.');
    }

    public function reject(Request $request, Project $project, ProjectExpense $expense)
    {
        $this->authorizeExpenseAccess($request, $project);
        abort_unless($this->canReviewExpense($request->user()), 403, 'Chỉ Admin/Kế toán/Quản lý được từ chối chi phí.');
        $this->assertExpenseBelongsToProject($expense, $project);
        abort_unless($expense->status === 'pending', 422, 'Khoản chi phí không còn ở trạng thái chờ xác nhận.');

        $data = $request->validate([
            'reason' => ['required', 'string', 'max:2000'],
        ]);

        $expense->status = 'rejected';
        $expense->rejected_by = $request->user()->id;
        $expense->rejected_at = now();
        $expense->rejection_reason = $data['reason'];
        $expense->save();

        $this->history($project, 'Từ chối chi phí '.$expense->expense_code, [
            'expense_id' => $expense->id,
            'reason' => $data['reason'],
        ]);

        return back()->with('success', 'Đã từ chối chi phí '.$expense->expense_code.'.');
    }

    public function cancel(Request $request, Project $project, ProjectExpense $expense)
    {
        $this->authorizeExpenseAccess($request, $project);
        $this->assertExpenseBelongsToProject($expense, $project);
        abort_unless($expense->status === 'pending', 422, 'Chỉ khoản chi phí đang chờ xác nhận mới được hủy trực tiếp.');

        $user = $request->user();
        abort_unless(
            (int) $expense->created_by === (int) $user->id || $this->canReviewExpense($user),
            403,
            'Bạn không được hủy khoản chi phí này.'
        );

        $data = $request->validate([
            'reason' => ['required', 'string', 'max:2000'],
        ]);

        $expense->status = 'cancelled';
        $expense->cancelled_by = $user->id;
        $expense->cancelled_at = now();
        $expense->cancellation_reason = $data['reason'];
        $expense->save();

        $this->history($project, 'Hủy chi phí '.$expense->expense_code, [
            'expense_id' => $expense->id,
            'reason' => $data['reason'],
        ]);

        return back()->with('success', 'Đã hủy khoản chi phí '.$expense->expense_code.'.');
    }

    public function proof(Request $request, Project $project, ProjectExpense $expense)
    {
        $this->authorizeExpenseAccess($request, $project);
        $this->assertExpenseBelongsToProject($expense, $project);
        abort_unless($expense->proof_path && Storage::disk('local')->exists($expense->proof_path), 404);

        return Storage::disk('local')->download($expense->proof_path, basename($expense->proof_path));
    }

    private function validateExpense(Request $request): array
    {
        return $request->validate([
            'expense_date' => ['required', 'date'],
            'category' => ['required', Rule::in(self::CATEGORIES)],
            'description' => ['required', 'string', 'max:500'],
            'amount' => ['required', 'numeric', 'gt:0', 'max:999999999999'],
            'payee_name' => ['nullable', 'string', 'max:255'],
            'note' => ['nullable', 'string', 'max:5000'],
            'proof_file' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf,xlsx,xls,doc,docx', 'max:15360'],
        ]);
    }

    private function storeProof(Request $request, Project $project): ?string
    {
        if (! $request->hasFile('proof_file')) {
            return null;
        }

        return $request->file('proof_file')->store('project-expenses/'.$project->id, 'local');
    }

    private function authorizeExpenseAccess(Request $request, Project $project): void
    {
        $user = $request->user();
        abort_unless($user, 403);

        $isWarehouse = $user->hasAnyRole(['warehouse', 'kho']);
        if ($isWarehouse) {
            $visible = Project::query()
                ->whereKey($project->id)
                ->where('company_id', EgoCompanyLock::id())
                ->whereHas('materialRequests', function ($query): void {
                    $query->whereIn('status', ['warehouse_check', 'pending_manager', 'approved', 'preparing', 'issued', 'revision']);
                })
                ->exists();
        } else {
            $visible = Project::query()->visibleTo($user)->whereKey($project->id)->exists();
        }

        abort_unless($visible, 403);
        abort_unless(($project->request_source ?? 'sales') === 'sales', 403, 'Công trình nội bộ Kỹ thuật không dùng sổ chi phí Sales/Kho.');

        abort_unless(
            $this->canCreateExpense($user, $project) || $this->canReviewExpense($user),
            403,
            'Bạn không được truy cập sổ chi phí công trình này.'
        );
    }

    private function canCreateExpense(User $user, Project $project): bool
    {
        if ($user->hasAnyRole(['admin', 'management', 'manager', 'accounting', 'sales_manager', 'warehouse', 'kho'])) {
            return true;
        }

        return $user->hasAnyRole(['sales', 'sales_staff'])
            && (
                (int) $project->sales_user_id === (int) $user->id
                || (int) $project->created_by === (int) $user->id
            );
    }

    private function canReviewExpense(User $user): bool
    {
        return $user->hasAnyRole(['admin', 'management', 'manager', 'accounting']);
    }

    private function assertExpenseBelongsToProject(ProjectExpense $expense, Project $project): void
    {
        abort_unless((int) $expense->project_id === (int) $project->id, 404);
    }

    private function nextExpenseCode(Project $project): string
    {
        $prefix = 'CP-'.$project->id.'-'.now()->format('Ymd').'-';
        $last = ProjectExpense::query()
            ->where('expense_code', 'like', $prefix.'%')
            ->lockForUpdate()
            ->orderByDesc('id')
            ->value('expense_code');
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
