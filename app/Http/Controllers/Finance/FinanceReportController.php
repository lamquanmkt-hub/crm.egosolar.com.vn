<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class FinanceReportController extends Controller
{
    public function index(Request $request)
    {
        /*
        |--------------------------------------------------------------------------
        | Bộ lọc tháng
        |--------------------------------------------------------------------------
        */
        $month = $request->input('month', now()->format('Y-m'));
        $monthStart = $month . '-01';
        $monthEnd = date('Y-m-t', strtotime($monthStart));

        /*
        |--------------------------------------------------------------------------
        | Số dư quỹ / tài khoản
        | Đã bỏ khỏi báo cáo theo yêu cầu
        |--------------------------------------------------------------------------
        */
        $accountBalance = 0;
        $accounts = collect();

        /*
        |--------------------------------------------------------------------------
        | Tiền khách đã thanh toán
        | Nguồn chuẩn: crm_payments
        |--------------------------------------------------------------------------
        */
        $totalReceipts = 0;
        $receiptCount = 0;
        $receiptsByCategory = collect();

        if (
            Schema::hasTable('crm_payments') &&
            Schema::hasColumn('crm_payments', 'amount') &&
            Schema::hasColumn('crm_payments', 'payment_date')
        ) {
            $crmPaymentQuery = DB::table('crm_payments')
                ->whereBetween('payment_date', [$monthStart, $monthEnd]);

            $totalReceipts = (float) (clone $crmPaymentQuery)->sum('amount');
            $receiptCount = (clone $crmPaymentQuery)->count();

            if (
                Schema::hasTable('crm_payment_methods') &&
                Schema::hasColumn('crm_payments', 'method_id') &&
                Schema::hasColumn('crm_payment_methods', 'method_name')
            ) {
                $receiptsByCategory = DB::table('crm_payments')
                    ->leftJoin('crm_payment_methods', 'crm_payments.method_id', '=', 'crm_payment_methods.id')
                    ->whereBetween('crm_payments.payment_date', [$monthStart, $monthEnd])
                    ->selectRaw("COALESCE(NULLIF(crm_payment_methods.method_name, ''), CONCAT('Phương thức #', crm_payments.method_id), 'Chưa phân loại') as category")
                    ->selectRaw('SUM(crm_payments.amount) as total_amount')
                    ->selectRaw('COUNT(crm_payments.id) as total_count')
                    ->groupBy('crm_payments.method_id', 'crm_payment_methods.method_name')
                    ->orderByDesc('total_amount')
                    ->get();
            } elseif (Schema::hasColumn('crm_payments', 'method_id')) {
                $receiptsByCategory = DB::table('crm_payments')
                    ->whereBetween('payment_date', [$monthStart, $monthEnd])
                    ->selectRaw("COALESCE(CONCAT('Phương thức #', method_id), 'Chưa phân loại') as category")
                    ->selectRaw('SUM(amount) as total_amount')
                    ->selectRaw('COUNT(id) as total_count')
                    ->groupBy('method_id')
                    ->orderByDesc('total_amount')
                    ->get();
            } else {
                $receiptsByCategory = collect([
                    (object) [
                        'category' => 'Khách thanh toán',
                        'total_amount' => $totalReceipts,
                        'total_count' => $receiptCount,
                    ],
                ]);
            }
        }

        /*
        |--------------------------------------------------------------------------
        | Chi phí đã duyệt
        | Nguồn chuẩn: payment_requests status = accounting_approved
        | Gom nhóm bằng PHP để tránh lỗi ONLY_FULL_GROUP_BY của MySQL
        |--------------------------------------------------------------------------
        */
        $totalPayments = 0;
        $paymentCount = 0;
        $paymentsByCategory = collect();

        if (
            Schema::hasTable('payment_requests') &&
            Schema::hasColumn('payment_requests', 'amount') &&
            Schema::hasColumn('payment_requests', 'status')
        ) {
            $approvedDateColumn = null;

            if (Schema::hasColumn('payment_requests', 'accounting_approved_at')) {
                $approvedDateColumn = 'accounting_approved_at';
            } elseif (Schema::hasColumn('payment_requests', 'updated_at')) {
                $approvedDateColumn = 'updated_at';
            } elseif (Schema::hasColumn('payment_requests', 'created_at')) {
                $approvedDateColumn = 'created_at';
            }

            $approvedExpenseQuery = DB::table('payment_requests')
                ->where('status', 'accounting_approved');

            if ($approvedDateColumn) {
                $approvedExpenseQuery
                    ->whereDate($approvedDateColumn, '>=', $monthStart)
                    ->whereDate($approvedDateColumn, '<=', $monthEnd);
            }

            $approvedRows = $approvedExpenseQuery->get();

            $totalPayments = (float) $approvedRows->sum(function ($row) {
                return (float) ($row->amount ?? 0);
            });

            $paymentCount = $approvedRows->count();

            $paymentsByCategory = $approvedRows
                ->groupBy(function ($row) {
                    $costType = trim((string) ($row->cost_type ?? ''));
                    $department = trim((string) ($row->department ?? ''));
                    $docType = trim((string) ($row->doc_type ?? ''));

                    if ($costType !== '') {
                        return $costType;
                    }

                    if ($department !== '') {
                        return $department;
                    }

                    if ($docType !== '') {
                        return $docType;
                    }

                    return 'Chưa phân loại';
                })
                ->map(function ($rows, $category) {
                    return (object) [
                        'category' => $category,
                        'total_amount' => (float) $rows->sum(function ($row) {
                            return (float) ($row->amount ?? 0);
                        }),
                        'total_count' => $rows->count(),
                    ];
                })
                ->sortByDesc('total_amount')
                ->values();
        }

        /*
        |--------------------------------------------------------------------------
        | Ngân sách tài chính
        | Nguồn: finance_budgets
        |--------------------------------------------------------------------------
        */
        $totalBudget = 0;
        $budgetCount = 0;
        $budgets = collect();

        if (Schema::hasTable('finance_budgets')) {
            $budgets = DB::table('finance_budgets')
                ->where('month', $monthStart)
                ->orderBy('category')
                ->get();

            $totalBudget = (float) $budgets->sum('budget_amount');
            $budgetCount = $budgets->count();
        }

        /*
        |--------------------------------------------------------------------------
        | Đề nghị thanh toán đang chờ
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
                ->whereIn('status', ['submitted', 'admin_approved', 'pending']);

            $pendingRequestsAmount = (float) (clone $pendingQuery)->sum('amount');
            $pendingRequestsCount = (clone $pendingQuery)->count();
        }

        /*
        |--------------------------------------------------------------------------
        | Đề nghị đã duyệt trong tháng
        |--------------------------------------------------------------------------
        */
        $approvedRequestsAmount = $totalPayments;
        $approvedRequestsCount = $paymentCount;

        /*
        |--------------------------------------------------------------------------
        | Công nợ khách hàng
        | Nguồn chuẩn: crm_customer_debts
        |--------------------------------------------------------------------------
        */
        $customerDebtTotal = 0;
        $customerPaidTotal = 0;
        $customerRemainTotal = 0;

        if (Schema::hasTable('crm_customer_debts')) {
            if (Schema::hasColumn('crm_customer_debts', 'total_amount')) {
                $customerDebtTotal = (float) DB::table('crm_customer_debts')->sum('total_amount');
            }

            if (Schema::hasColumn('crm_customer_debts', 'paid_amount')) {
                $customerPaidTotal = (float) DB::table('crm_customer_debts')->sum('paid_amount');
            }

            if (Schema::hasColumn('crm_customer_debts', 'debt_amount')) {
                $customerRemainTotal = (float) DB::table('crm_customer_debts')->sum('debt_amount');
            } else {
                $customerRemainTotal = max($customerDebtTotal - $customerPaidTotal, 0);
            }
        } elseif (Schema::hasTable('crm_orders')) {
            if (Schema::hasColumn('crm_orders', 'total_amount')) {
                $customerDebtTotal = (float) DB::table('crm_orders')->sum('total_amount');
            }

            $customerPaidTotal = $totalReceipts;
            $customerRemainTotal = max($customerDebtTotal - $customerPaidTotal, 0);
        }

        /*
        |--------------------------------------------------------------------------
        | Đơn hàng trong tháng
        |--------------------------------------------------------------------------
        */
        $monthlyOrderAmount = 0;
        $monthlyOrderCount = 0;

        if (
            Schema::hasTable('crm_orders') &&
            Schema::hasColumn('crm_orders', 'total_amount')
        ) {
            $orderQuery = DB::table('crm_orders');

            if (Schema::hasColumn('crm_orders', 'order_date')) {
                $orderQuery->whereBetween('order_date', [$monthStart, $monthEnd]);
            } elseif (Schema::hasColumn('crm_orders', 'created_at')) {
                $orderQuery
                    ->whereDate('created_at', '>=', $monthStart)
                    ->whereDate('created_at', '<=', $monthEnd);
            }

            $monthlyOrderAmount = (float) (clone $orderQuery)->sum('total_amount');
            $monthlyOrderCount = (clone $orderQuery)->count();
        }

        /*
        |--------------------------------------------------------------------------
        | Tổng hợp
        |--------------------------------------------------------------------------
        */
        $netCashFlow = $totalReceipts - $totalPayments;
        $budgetRemain = $totalBudget - $totalPayments;

        $budgetUsageRate = $totalBudget > 0
            ? round(($totalPayments / $totalBudget) * 100, 1)
            : 0;

        $summary = [
            'account_balance' => $accountBalance,

            'total_receipts' => $totalReceipts,
            'receipt_count' => $receiptCount,

            'total_payments' => $totalPayments,
            'payment_count' => $paymentCount,

            'net_cash_flow' => $netCashFlow,

            'total_budget' => $totalBudget,
            'budget_count' => $budgetCount,
            'budget_remain' => $budgetRemain,
            'budget_usage_rate' => $budgetUsageRate,

            'pending_requests_amount' => $pendingRequestsAmount,
            'pending_requests_count' => $pendingRequestsCount,

            'approved_requests_amount' => $approvedRequestsAmount,
            'approved_requests_count' => $approvedRequestsCount,

            'customer_debt_total' => $customerDebtTotal,
            'customer_paid_total' => $customerPaidTotal,
            'customer_remain_total' => $customerRemainTotal,

            'monthly_order_amount' => $monthlyOrderAmount,
            'monthly_order_count' => $monthlyOrderCount,
        ];

        return view('finance.reports', compact(
            'month',
            'monthStart',
            'monthEnd',
            'summary',
            'accounts',
            'budgets',
            'receiptsByCategory',
            'paymentsByCategory'
        ));
    }
}
