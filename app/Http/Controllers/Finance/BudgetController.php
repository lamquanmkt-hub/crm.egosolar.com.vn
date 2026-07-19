<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class BudgetController extends Controller
{
    public function index(Request $request)
    {
        /*
        |--------------------------------------------------------------------------
        | Tháng đang xem
        |--------------------------------------------------------------------------
        */
        $month = $request->input('month', now()->format('Y-m'));
        $monthStart = $month . '-01';
        $monthEnd = date('Y-m-t', strtotime($monthStart));

        /*
        |--------------------------------------------------------------------------
        | Lấy danh sách chi phí đã duyệt từ payment_requests
        | Đây là số "đã chi" thật để so với ngân sách
        |--------------------------------------------------------------------------
        */
        $approvedRows = collect();
        $approvedExpenseAmount = 0;
        $approvedExpenseCount = 0;

        if (
            Schema::hasTable('payment_requests') &&
            Schema::hasColumn('payment_requests', 'amount') &&
            Schema::hasColumn('payment_requests', 'status')
        ) {
            $dateColumn = null;

            if (Schema::hasColumn('payment_requests', 'accounting_approved_at')) {
                $dateColumn = 'accounting_approved_at';
            } elseif (Schema::hasColumn('payment_requests', 'updated_at')) {
                $dateColumn = 'updated_at';
            } elseif (Schema::hasColumn('payment_requests', 'created_at')) {
                $dateColumn = 'created_at';
            }

            $query = DB::table('payment_requests')
                ->where('status', 'accounting_approved');

            if ($dateColumn) {
                $query->whereDate($dateColumn, '>=', $monthStart)
                    ->whereDate($dateColumn, '<=', $monthEnd);
            }

            $approvedRows = $query->get();

            $approvedExpenseAmount = (float) $approvedRows->sum(function ($row) {
                return (float) ($row->amount ?? 0);
            });

            $approvedExpenseCount = $approvedRows->count();
        }

        /*
        |--------------------------------------------------------------------------
        | Gom chi phí đã duyệt theo hạng mục
        | Ưu tiên: cost_type -> department -> doc_type -> Chưa phân loại
        |--------------------------------------------------------------------------
        */
        $approvedByCategory = $approvedRows
            ->groupBy(function ($row) {
                $costType = trim((string) ($row->cost_type ?? ''));
                $department = trim((string) ($row->department ?? ''));
                $docType = trim((string) ($row->doc_type ?? ''));

                if ($costType !== '') {
                    return mb_strtolower($costType);
                }

                if ($department !== '') {
                    return mb_strtolower($department);
                }

                if ($docType !== '') {
                    return mb_strtolower($docType);
                }

                return 'chưa phân loại';
            })
            ->map(function ($rows, $categoryKey) {
                return (object) [
                    'category_key' => $categoryKey,
                    'category' => $rows->first()->cost_type
                        ?? $rows->first()->department
                        ?? $rows->first()->doc_type
                        ?? 'Chưa phân loại',
                    'total_amount' => (float) $rows->sum(function ($row) {
                        return (float) ($row->amount ?? 0);
                    }),
                    'total_count' => $rows->count(),
                ];
            });

        /*
        |--------------------------------------------------------------------------
        | Ngân sách tài chính do mình nhập
        |--------------------------------------------------------------------------
        */
        $financeBudgets = collect();

        if (Schema::hasTable('finance_budgets')) {
            $financeBudgets = DB::table('finance_budgets')
                ->where('month', $monthStart)
                ->orderBy('category')
                ->get()
                ->map(function ($item) use ($approvedByCategory) {
                    $categoryKey = mb_strtolower(trim((string) $item->category));

                    $spent = 0;

                    if ($approvedByCategory->has($categoryKey)) {
                        $spent = (float) $approvedByCategory[$categoryKey]->total_amount;
                    }

                    $budget = (float) ($item->budget_amount ?? 0);
                    $remain = $budget - $spent;
                    $usageRate = $budget > 0 ? round(($spent / $budget) * 100, 1) : 0;

                    if ($usageRate >= 100) {
                        $status = 'Vượt ngân sách';
                        $statusClass = 'danger';
                    } elseif ($usageRate >= 80) {
                        $status = 'Sắp chạm ngưỡng';
                        $statusClass = 'warning';
                    } else {
                        $status = 'An toàn';
                        $statusClass = 'success';
                    }

                    $item->spent_amount = $spent;
                    $item->remain_amount = $remain;
                    $item->usage_rate = $usageRate;
                    $item->status = $status;
                    $item->status_class = $statusClass;

                    return $item;
                });
        }

        /*
        |--------------------------------------------------------------------------
        | Tổng hợp ngân sách
        |--------------------------------------------------------------------------
        */
        $totalBudget = (float) $financeBudgets->sum('budget_amount');
        $totalSpent = (float) $financeBudgets->sum('spent_amount');
        $budgetRemain = $totalBudget - $totalSpent;
        $budgetUsageRate = $totalBudget > 0 ? round(($totalSpent / $totalBudget) * 100, 1) : 0;

        /*
        |--------------------------------------------------------------------------
        | Chi phí đã duyệt nhưng chưa khớp với hạng mục ngân sách
        |--------------------------------------------------------------------------
        */
        $budgetCategoryKeys = $financeBudgets
            ->pluck('category')
            ->map(function ($category) {
                return mb_strtolower(trim((string) $category));
            })
            ->values()
            ->all();

        $unmatchedExpenses = $approvedByCategory
            ->filter(function ($item, $categoryKey) use ($budgetCategoryKeys) {
                return !in_array($categoryKey, $budgetCategoryKeys, true);
            })
            ->sortByDesc('total_amount')
            ->values();

        $unmatchedExpenseAmount = (float) $unmatchedExpenses->sum('total_amount');

        /*
        |--------------------------------------------------------------------------
        | Đề nghị thanh toán đang chờ xử lý
        |--------------------------------------------------------------------------
        */
        $pendingRequestsAmount = 0;
        $pendingRequestsCount = 0;

        if (
            Schema::hasTable('payment_requests') &&
            Schema::hasColumn('payment_requests', 'amount') &&
            Schema::hasColumn('payment_requests', 'status')
        ) {
            $pendingQuery = DB::table('payment_requests')
                ->whereIn('status', ['pending', 'submitted', 'admin_approved']);

            if (Schema::hasColumn('payment_requests', 'created_at')) {
                $pendingQuery->whereDate('created_at', '>=', $monthStart)
                    ->whereDate('created_at', '<=', $monthEnd);
            }

            $pendingRequestsAmount = (float) (clone $pendingQuery)->sum('amount');
            $pendingRequestsCount = (clone $pendingQuery)->count();
        }

        /*
        |--------------------------------------------------------------------------
        | Dữ liệu tương thích với giao diện hiện tại
        |--------------------------------------------------------------------------
        */
        $budgetCategories = $financeBudgets->map(function ($item) {
            return (object) [
                'id' => $item->id,
                'platform' => $item->category,
                'category' => $item->category,
                'note' => $item->note,
                'total_budget' => $item->budget_amount,
                'total_spent' => $item->spent_amount,
                'remain' => $item->remain_amount,
                'usage_rate' => $item->usage_rate,
                'status' => $item->status,
                'status_class' => $item->status_class,
            ];
        });

        $recentBudgets = collect();

        if (Schema::hasTable('finance_budgets')) {
            $recentBudgets = DB::table('finance_budgets')
                ->orderByDesc('month')
                ->orderByDesc('id')
                ->limit(20)
                ->get()
                ->map(function ($item) {
                    return (object) [
                        'id' => $item->id,
                        'month' => $item->month,
                        'platform' => $item->category,
                        'category' => $item->category,
                        'budget' => $item->budget_amount,
                        'actual_spent' => 0,
                        'remain' => $item->budget_amount,
                        'note' => $item->note,
                        'status' => 'Đã tạo',
                        'status_class' => 'primary',
                    ];
                });
        }

        $paymentCategories = $approvedByCategory
            ->sortByDesc('total_amount')
            ->values()
            ->map(function ($item) {
                return (object) [
                    'category' => $item->category ?: 'Chưa phân loại',
                    'total_amount' => $item->total_amount,
                    'total_count' => $item->total_count,
                ];
            });

        /*
        |--------------------------------------------------------------------------
        | Các biến cũ để view không bị lỗi
        | Sau bước sau mình sẽ sửa view để bỏ các card không cần thiết
        |--------------------------------------------------------------------------
        */
        $accountBalance = 0;
        $totalReceipts = 0;
        $totalPayments = $approvedExpenseAmount;

        $cashFlow = [
            'receipts' => 0,
            'payments' => $approvedExpenseAmount,
            'net' => 0 - $approvedExpenseAmount,
        ];

        return view('finance.budget', compact(
            'month',
            'monthStart',
            'monthEnd',

            'financeBudgets',
            'budgetCategories',
            'recentBudgets',
            'paymentCategories',

            'totalBudget',
            'totalSpent',
            'budgetRemain',
            'budgetUsageRate',

            'approvedExpenseAmount',
            'approvedExpenseCount',
            'unmatchedExpenses',
            'unmatchedExpenseAmount',

            'pendingRequestsAmount',
            'pendingRequestsCount',

            'accountBalance',
            'totalReceipts',
            'totalPayments',
            'cashFlow'
        ));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'month' => ['required', 'date_format:Y-m'],
            'category' => ['required', 'string', 'max:255'],
            'budget_amount' => ['required', 'numeric', 'min:0'],
            'note' => ['nullable', 'string'],
        ]);

        DB::table('finance_budgets')->insert([
            'month' => $data['month'] . '-01',
            'category' => trim($data['category']),
            'budget_amount' => $data['budget_amount'],
            'note' => $data['note'] ?? null,
            'created_by' => auth()->id(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return redirect()
            ->route('finance.budget', ['month' => $data['month']])
            ->with('success', 'Đã thêm ngân sách thành công.');
    }

    public function update(Request $request, $id)
    {
        $data = $request->validate([
            'month' => ['required', 'date_format:Y-m'],
            'category' => ['required', 'string', 'max:255'],
            'budget_amount' => ['required', 'numeric', 'min:0'],
            'note' => ['nullable', 'string'],
        ]);

        DB::table('finance_budgets')
            ->where('id', $id)
            ->update([
                'month' => $data['month'] . '-01',
                'category' => trim($data['category']),
                'budget_amount' => $data['budget_amount'],
                'note' => $data['note'] ?? null,
                'updated_at' => now(),
            ]);

        return redirect()
            ->route('finance.budget', ['month' => $data['month']])
            ->with('success', 'Đã cập nhật ngân sách thành công.');
    }

    public function destroy($id)
    {
        $budget = DB::table('finance_budgets')->where('id', $id)->first();

        DB::table('finance_budgets')
            ->where('id', $id)
            ->delete();

        $month = $budget && $budget->month
            ? date('Y-m', strtotime($budget->month))
            : now()->format('Y-m');

        return redirect()
            ->route('finance.budget', ['month' => $month])
            ->with('success', 'Đã xóa ngân sách.');
    }
}
