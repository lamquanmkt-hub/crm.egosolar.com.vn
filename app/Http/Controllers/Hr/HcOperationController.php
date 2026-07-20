<?php

namespace App\Http\Controllers\Hr;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Controller vận hành hành chính (HC): chi phí, tài sản, nhà cung cấp, công việc phát sinh và bảo trì thiết bị.
 */
class HcOperationController extends Controller
{
    private array $maintenanceTypes = [
        'Bảo trì trang thiết bị',
        'Bảo trì thiết bị',
        'Sửa chữa thiết bị',
        'Bảo trì / sửa chữa',
    ];

    /**
     * Hiển thị trang vận hành HC theo tab (tổng quan, chi phí, tài sản, NCC, công việc, bảo trì) kèm thống kê.
     */
    public function index(Request $request)
    {
        $allowedTabs = ['overview', 'expenses', 'assets', 'suppliers', 'tasks', 'maintenance'];
        $tab = $request->get('tab', 'overview');

        if (! in_array($tab, $allowedTabs, true)) {
            $tab = 'overview';
        }

        $expenses = Schema::hasTable('hr_operation_expenses')
            ? DB::table('hr_operation_expenses')->orderByDesc('id')->get()
            : collect();

        $assets = Schema::hasTable('hr_operation_assets')
            ? DB::table('hr_operation_assets')->orderByDesc('id')->get()
            : collect();

        $suppliers = Schema::hasTable('hr_operation_suppliers')
            ? DB::table('hr_operation_suppliers')->orderByDesc('id')->get()
            : collect();

        $maintenanceTasks = Schema::hasTable('hr_operation_tasks')
            ? DB::table('hr_operation_tasks')
                ->whereIn('task_type', $this->maintenanceTypes)
                ->orderByDesc('id')
                ->get()
            : collect();

        $tasks = Schema::hasTable('hr_operation_tasks')
            ? DB::table('hr_operation_tasks')
                ->where(function ($query) {
                    $query->whereNull('task_type')
                        ->orWhereNotIn('task_type', $this->maintenanceTypes);
                })
                ->orderByDesc('id')
                ->get()
            : collect();

        $documentHandovers = collect();
        if (Schema::hasTable('hr_document_handovers')) {
            $handoverQuery = DB::table('hr_document_handovers as h');

            if (Schema::hasTable('users')) {
                $handoverQuery
                    ->leftJoin('users as creator', 'creator.id', '=', 'h.created_by')
                    ->leftJoin('users as assignee', 'assignee.id', '=', 'h.assigned_to')
                    ->select('h.*', 'creator.name as sender_name', 'assignee.name as receiver_name');
            } else {
                $handoverQuery->select('h.*');
            }

            $documentHandovers = $handoverQuery->orderByDesc('h.id')->limit(8)->get();
        }

        $monthExpense = Schema::hasTable('hr_operation_expenses')
            ? DB::table('hr_operation_expenses')
                ->whereMonth('expense_date', now()->month)
                ->whereYear('expense_date', now()->year)
                ->sum('amount')
            : 0;

        $fixedExpense = Schema::hasTable('hr_operation_expenses')
            ? DB::table('hr_operation_expenses')
                ->whereMonth('expense_date', now()->month)
                ->whereYear('expense_date', now()->year)
                ->where('category', 'Chi phí cố định')
                ->sum('amount')
            : 0;

        $arisingExpense = Schema::hasTable('hr_operation_expenses')
            ? DB::table('hr_operation_expenses')
                ->whereMonth('expense_date', now()->month)
                ->whereYear('expense_date', now()->year)
                ->where('category', 'Chi phí phát sinh')
                ->sum('amount')
            : 0;

        $processingTasks = $tasks->whereIn('status', ['pending', 'processing'])->count()
            + $maintenanceTasks->whereIn('status', ['pending', 'processing'])->count();

        $overdueTasks = $tasks->filter(function ($row) {
            return ! empty($row->deadline)
                && $row->deadline < now()->toDateString()
                && in_array($row->status, ['pending', 'processing'], true);
        })->count()
            + $maintenanceTasks->filter(function ($row) {
                return ! empty($row->deadline)
                    && $row->deadline < now()->toDateString()
                    && in_array($row->status, ['pending', 'processing'], true);
            })->count();

        $stats = [
            'total_rows' => $expenses->count() + $assets->count() + $suppliers->count() + $tasks->count() + $maintenanceTasks->count() + $documentHandovers->count(),
            'month_expense' => $monthExpense,
            'fixed_expense' => $fixedExpense,
            'arising_expense' => $arisingExpense,
            'assets' => $assets->count(),
            'processing_tasks' => $processingTasks,
            'overdue_tasks' => $overdueTasks,
            'handovers' => $documentHandovers->count(),
            'maintenance' => $maintenanceTasks->count(),
        ];

        $expenseCategories = ['Chi phí cố định', 'Chi phí phát sinh', 'Nước', 'Internet', 'Văn phòng phẩm', 'Sửa chữa', 'Dịch vụ', 'Khác'];
        $conditions = ['Mới', 'Cũ'];
        $maintenanceConditions = ['Tốt', 'Cần kiểm tra', 'Hư hỏng', 'Cần thay thế'];
        $paymentStatuses = ['Chưa thanh toán', 'Đã thanh toán', 'Đề nghị thanh toán', 'Quá hạn'];
        $assetStatuses = ['Đang sử dụng', 'Hư hỏng', 'Bảo trì', 'Thanh lý'];
        $taskTypes = ['Cần mua VPP', 'Cần sửa máy lạnh', 'Cần làm thẻ nhân viên', 'Đặt xe / Công tác', 'Họp', 'Khác'];
        $departments = ['HCNS', 'Kinh doanh', 'Kỹ thuật', 'Marketing', 'Kế toán', 'Kho', 'Ban giám đốc', 'Khác'];
        $priorities = [
            'urgent' => 'Khẩn cấp',
            'normal' => 'Thường',
            'low' => 'Không gấp',
        ];
        $taskStatuses = [
            'pending' => 'Chưa xử lý',
            'processing' => 'Đang xử lý',
            'done' => 'Hoàn thành',
            'cancelled' => 'Huỷ / Từ chối',
        ];

        return view('hr.operations.index', compact(
            'tab',
            'expenses',
            'assets',
            'suppliers',
            'tasks',
            'maintenanceTasks',
            'documentHandovers',
            'stats',
            'conditions',
            'maintenanceConditions',
            'expenseCategories',
            'paymentStatuses',
            'assetStatuses',
            'taskTypes',
            'departments',
            'priorities',
            'taskStatuses'
        ));
    }

    /**
     * Thêm mới một khoản chi phí vận hành văn phòng.
     */
    public function storeExpense(Request $request)
    {
        $data = $request->validate([
            'item_condition' => ['nullable', 'string', 'max:50'],
            'category' => ['nullable', 'string', 'max:100'],
            'content' => ['required', 'string', 'max:255'],
            'amount' => ['nullable', 'numeric'],
            'supplier_name' => ['nullable', 'string', 'max:255'],
            'person_in_charge' => ['nullable', 'string', 'max:255'],
            'payment_status' => ['nullable', 'string', 'max:100'],
            'expense_date' => ['nullable', 'date'],
            'note' => ['nullable', 'string'],
        ]);

        $data['amount'] = $data['amount'] ?? 0;
        $data['expense_date'] = $data['expense_date'] ?: now()->toDateString();
        $data['created_at'] = now();
        $data['updated_at'] = now();

        DB::table('hr_operation_expenses')->insert($data);

        return $this->backTo('expenses', 'Đã thêm chi phí văn phòng.');
    }

    /**
     * Cập nhật khoản chi phí vận hành theo ID.
     *
     * @param  int|string  $id  ID khoản chi
     */
    public function updateExpense(Request $request, $id)
    {
        $data = $request->validate([
            'item_condition' => ['nullable', 'string', 'max:50'],
            'category' => ['nullable', 'string', 'max:100'],
            'content' => ['required', 'string', 'max:255'],
            'amount' => ['nullable', 'numeric'],
            'supplier_name' => ['nullable', 'string', 'max:255'],
            'person_in_charge' => ['nullable', 'string', 'max:255'],
            'payment_status' => ['nullable', 'string', 'max:100'],
            'expense_date' => ['nullable', 'date'],
            'note' => ['nullable', 'string'],
        ]);

        $data['amount'] = $data['amount'] ?? 0;
        $data['updated_at'] = now();

        DB::table('hr_operation_expenses')->where('id', (int) $id)->update($data);

        return $this->backTo('expenses', 'Đã cập nhật chi phí văn phòng.');
    }

    /**
     * Xoá khoản chi phí vận hành theo ID.
     *
     * @param  int|string  $id  ID khoản chi
     */
    public function destroyExpense($id)
    {
        DB::table('hr_operation_expenses')->where('id', (int) $id)->delete();

        return $this->backTo('expenses', 'Đã xoá chi phí văn phòng.');
    }

    /**
     * Thêm mới một tài sản văn phòng.
     */
    public function storeAsset(Request $request)
    {
        $data = $request->validate([
            'item_condition' => ['nullable', 'string', 'max:50'],
            'asset_name' => ['required', 'string', 'max:255'],
            'asset_type' => ['nullable', 'string', 'max:255'],
            'purchase_date' => ['nullable', 'date'],
            'value' => ['nullable', 'numeric'],
            'assigned_to' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', 'string', 'max:100'],
            'note' => ['nullable', 'string'],
        ]);

        $data['value'] = $data['value'] ?? 0;
        $data['created_at'] = now();
        $data['updated_at'] = now();

        DB::table('hr_operation_assets')->insert($data);

        return $this->backTo('assets', 'Đã thêm tài sản.');
    }

    /**
     * Cập nhật thông tin tài sản theo ID.
     *
     * @param  int|string  $id  ID tài sản
     */
    public function updateAsset(Request $request, $id)
    {
        $data = $request->validate([
            'item_condition' => ['nullable', 'string', 'max:50'],
            'asset_name' => ['required', 'string', 'max:255'],
            'asset_type' => ['nullable', 'string', 'max:255'],
            'purchase_date' => ['nullable', 'date'],
            'value' => ['nullable', 'numeric'],
            'assigned_to' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', 'string', 'max:100'],
            'note' => ['nullable', 'string'],
        ]);

        $data['value'] = $data['value'] ?? 0;
        $data['updated_at'] = now();

        DB::table('hr_operation_assets')->where('id', (int) $id)->update($data);

        return $this->backTo('assets', 'Đã cập nhật tài sản.');
    }

    /**
     * Xoá tài sản theo ID.
     *
     * @param  int|string  $id  ID tài sản
     */
    public function destroyAsset($id)
    {
        DB::table('hr_operation_assets')->where('id', (int) $id)->delete();

        return $this->backTo('assets', 'Đã xoá tài sản.');
    }

    /**
     * Thêm mới nhà cung cấp dịch vụ văn phòng.
     */
    public function storeSupplier(Request $request)
    {
        $data = $request->validate([
            'item_condition' => ['nullable', 'string', 'max:50'],
            'supplier_name' => ['required', 'string', 'max:255'],
            'contact_name' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:100'],
            'service' => ['nullable', 'string', 'max:255'],
            'payment_status' => ['nullable', 'string', 'max:100'],
            'note' => ['nullable', 'string'],
        ]);

        $data['created_at'] = now();
        $data['updated_at'] = now();

        DB::table('hr_operation_suppliers')->insert($data);

        return $this->backTo('suppliers', 'Đã thêm nhà cung cấp.');
    }

    /**
     * Cập nhật thông tin nhà cung cấp theo ID.
     *
     * @param  int|string  $id  ID nhà cung cấp
     */
    public function updateSupplier(Request $request, $id)
    {
        $data = $request->validate([
            'item_condition' => ['nullable', 'string', 'max:50'],
            'supplier_name' => ['required', 'string', 'max:255'],
            'contact_name' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:100'],
            'service' => ['nullable', 'string', 'max:255'],
            'payment_status' => ['nullable', 'string', 'max:100'],
            'note' => ['nullable', 'string'],
        ]);

        $data['updated_at'] = now();

        DB::table('hr_operation_suppliers')->where('id', (int) $id)->update($data);

        return $this->backTo('suppliers', 'Đã cập nhật nhà cung cấp.');
    }

    /**
     * Xoá nhà cung cấp theo ID.
     *
     * @param  int|string  $id  ID nhà cung cấp
     */
    public function destroySupplier($id)
    {
        DB::table('hr_operation_suppliers')->where('id', (int) $id)->delete();

        return $this->backTo('suppliers', 'Đã xoá nhà cung cấp.');
    }

    /**
     * Thêm mới công việc hành chính phát sinh.
     */
    public function storeTask(Request $request)
    {
        $data = $request->validate([
            'item_condition' => ['nullable', 'string', 'max:50'],
            'task_type' => ['nullable', 'string', 'max:255'],
            'content' => ['required', 'string', 'max:255'],
            'department' => ['nullable', 'string', 'max:255'],
            'priority' => ['nullable', 'string', 'max:50'],
            'deadline' => ['nullable', 'date'],
            'status' => ['nullable', 'string', 'max:50'],
            'assignee' => ['nullable', 'string', 'max:255'],
            'note' => ['nullable', 'string'],
        ]);

        $data['priority'] = $data['priority'] ?? 'normal';
        $data['status'] = $data['status'] ?? 'pending';
        $data['created_at'] = now();
        $data['updated_at'] = now();

        DB::table('hr_operation_tasks')->insert($data);

        return $this->backTo('tasks', 'Đã thêm việc HC phát sinh.');
    }

    /**
     * Cập nhật công việc hành chính phát sinh theo ID.
     *
     * @param  int|string  $id  ID công việc
     */
    public function updateTask(Request $request, $id)
    {
        $data = $request->validate([
            'item_condition' => ['nullable', 'string', 'max:50'],
            'task_type' => ['nullable', 'string', 'max:255'],
            'content' => ['required', 'string', 'max:255'],
            'department' => ['nullable', 'string', 'max:255'],
            'priority' => ['nullable', 'string', 'max:50'],
            'deadline' => ['nullable', 'date'],
            'status' => ['nullable', 'string', 'max:50'],
            'assignee' => ['nullable', 'string', 'max:255'],
            'note' => ['nullable', 'string'],
        ]);

        $data['updated_at'] = now();

        DB::table('hr_operation_tasks')->where('id', (int) $id)->update($data);

        return $this->backTo('tasks', 'Đã cập nhật việc HC phát sinh.');
    }

    /**
     * Xoá công việc hành chính phát sinh theo ID.
     *
     * @param  int|string  $id  ID công việc
     */
    public function destroyTask($id)
    {
        DB::table('hr_operation_tasks')->where('id', (int) $id)->delete();

        return $this->backTo('tasks', 'Đã xoá việc HC phát sinh.');
    }

    /**
     * Thêm mới yêu cầu bảo trì thiết bị (task loại "Bảo trì trang thiết bị").
     */
    public function storeMaintenance(Request $request)
    {
        $data = $request->validate([
            'item_condition' => ['nullable', 'string', 'max:50'],
            'content' => ['required', 'string', 'max:255'],
            'department' => ['nullable', 'string', 'max:255'],
            'priority' => ['nullable', 'string', 'max:50'],
            'deadline' => ['nullable', 'date'],
            'status' => ['nullable', 'string', 'max:50'],
            'assignee' => ['nullable', 'string', 'max:255'],
            'note' => ['nullable', 'string'],
        ]);

        $data['task_type'] = 'Bảo trì trang thiết bị';
        $data['priority'] = $data['priority'] ?? 'normal';
        $data['status'] = $data['status'] ?? 'pending';
        $data['created_at'] = now();
        $data['updated_at'] = now();

        DB::table('hr_operation_tasks')->insert($data);

        return $this->backTo('maintenance', 'Đã thêm yêu cầu bảo trì thiết bị.');
    }

    /**
     * Cập nhật yêu cầu bảo trì thiết bị theo ID.
     *
     * @param  int|string  $id  ID yêu cầu bảo trì
     */
    public function updateMaintenance(Request $request, $id)
    {
        $data = $request->validate([
            'item_condition' => ['nullable', 'string', 'max:50'],
            'content' => ['required', 'string', 'max:255'],
            'department' => ['nullable', 'string', 'max:255'],
            'priority' => ['nullable', 'string', 'max:50'],
            'deadline' => ['nullable', 'date'],
            'status' => ['nullable', 'string', 'max:50'],
            'assignee' => ['nullable', 'string', 'max:255'],
            'note' => ['nullable', 'string'],
        ]);

        $data['task_type'] = 'Bảo trì trang thiết bị';
        $data['updated_at'] = now();

        DB::table('hr_operation_tasks')->where('id', (int) $id)->update($data);

        return $this->backTo('maintenance', 'Đã cập nhật yêu cầu bảo trì thiết bị.');
    }

    /**
     * Xoá yêu cầu bảo trì thiết bị theo ID.
     *
     * @param  int|string  $id  ID yêu cầu bảo trì
     */
    public function destroyMaintenance($id)
    {
        DB::table('hr_operation_tasks')->where('id', (int) $id)->delete();

        return $this->backTo('maintenance', 'Đã xoá yêu cầu bảo trì thiết bị.');
    }

    /**
     * Redirect về trang vận hành HC đúng tab kèm thông báo thành công.
     *
     * @param  string  $tab  Tab cần quay về
     * @param  string  $message  Thông báo hiển thị
     */
    private function backTo($tab, $message)
    {
        return redirect()
            ->route('hr.operations.index', ['tab' => $tab])
            ->with('success', $message);
    }
}
