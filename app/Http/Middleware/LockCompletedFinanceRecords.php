<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\Response;

class LockCompletedFinanceRecords
{
    private string $allowedEmail = 'buibichthao@egosolar.vn';

    private array $completedStatuses = [
        'accounting_approved',
        'paid',
        'completed',
        'complete',
        'done',
        'closed',
        'da_chi',
        'da_thanh_toan',
        'ke_toan_da_chi',
        'ketoan_da_chi',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        if (!$this->isWriteRequest($request)) {
            return $next($request);
        }

        $userEmail = strtolower((string) optional($request->user())->email);

        if ($userEmail === strtolower($this->allowedEmail)) {
            return $next($request);
        }

        try {
            if ($this->touchesCompletedPaymentRequest($request)) {
                return $this->deny($request, 'Phiếu đề nghị thanh toán đã hoàn thành. Chỉ user buibichthao@egosolar.vn được sửa.');
            }

            if ($this->touchesCompletedSupplierDebt($request)) {
                return $this->deny($request, 'Công nợ/đợt thanh toán đã hoàn thành. Chỉ user buibichthao@egosolar.vn được sửa.');
            }
        } catch (\Throwable $e) {
            report($e);
        }

        return $next($request);
    }

    private function isWriteRequest(Request $request): bool
    {
        return in_array(strtoupper($request->method()), ['POST', 'PUT', 'PATCH', 'DELETE'], true);
    }

    private function deny(Request $request, string $message): Response
    {
        if ($request->expectsJson()) {
            abort(403, $message);
        }

        return redirect()
            ->back()
            ->withErrors(['error' => $message])
            ->withInput();
    }

    private function touchesCompletedPaymentRequest(Request $request): bool
    {
        $path = trim($request->path(), '/');

        if (!preg_match('#^payment-requests/([0-9]+)(?:/|$)#', $path, $m)) {
            return false;
        }

        return $this->isPaymentRequestCompleted((int) $m[1]);
    }

    private function touchesCompletedSupplierDebt(Request $request): bool
    {
        $path = trim($request->path(), '/');

        if (preg_match('#^finance/supplier-debts/payment-rounds/([0-9]+)(?:/|$)#', $path, $m)) {
            return $this->isSupplierPaymentRoundCompleted((int) $m[1]);
        }

        if (preg_match('#^finance/supplier-debts/([0-9]+)(?:/|$)#', $path, $m)) {
            return $this->isSupplierDebtCompleted((int) $m[1]);
        }

        return false;
    }

    private function isPaymentRequestCompleted(int $id): bool
    {
        if ($id <= 0 || !Schema::hasTable('payment_requests')) {
            return false;
        }

        $query = DB::table('payment_requests')->where('id', $id);

        if (Schema::hasColumn('payment_requests', 'deleted_at')) {
            $query->whereNull('deleted_at');
        }

        $row = $query->first();

        if (!$row) {
            return false;
        }

        $status = strtolower(trim((string) ($row->status ?? '')));

        return in_array($status, $this->completedStatuses, true);
    }

    private function isSupplierPaymentRoundCompleted(int $roundId): bool
    {
        if ($roundId <= 0 || !Schema::hasTable('finance_supplier_debt_payments')) {
            return false;
        }

        $round = DB::table('finance_supplier_debt_payments')->where('id', $roundId)->first();

        if (!$round) {
            return false;
        }

        $roundStatus = strtolower(trim((string) ($round->status ?? '')));

        if (in_array($roundStatus, $this->completedStatuses, true)) {
            return true;
        }

        if (!empty($round->payment_request_id) && $this->isPaymentRequestCompleted((int) $round->payment_request_id)) {
            return true;
        }

        if (!empty($round->supplier_debt_id) && $this->isSupplierDebtCompleted((int) $round->supplier_debt_id)) {
            return true;
        }

        return false;
    }

    private function isSupplierDebtCompleted(int $debtId): bool
    {
        if ($debtId <= 0 || !Schema::hasTable('finance_supplier_debts')) {
            return false;
        }

        $debt = DB::table('finance_supplier_debts')->where('id', $debtId)->first();

        if (!$debt) {
            return false;
        }

        $status = strtolower(trim((string) ($debt->status ?? '')));

        if (in_array($status, $this->completedStatuses, true)) {
            return true;
        }

        $total = $this->firstNumber($debt, [
            'total_amount',
            'amount',
            'debt_amount',
            'payable_amount',
            'total_payable',
        ]);

        $remaining = $this->firstNumber($debt, [
            'remaining_amount',
            'remain_amount',
            'balance',
            'balance_due',
            'unpaid_amount',
        ]);

        if ($total > 0 && $remaining !== null && $remaining <= 0) {
            return true;
        }

        $paid = $this->firstNumber($debt, [
            'paid_amount',
            'paid_total',
            'total_paid',
            'payment_amount',
        ]);

        if ($total > 0 && $paid !== null && $paid >= $total) {
            return true;
        }

        $paidFromRounds = $this->sumPaidSupplierDebtRounds($debtId);

        return $total > 0 && $paidFromRounds >= $total;
    }

    private function sumPaidSupplierDebtRounds(int $debtId): float
    {
        if (!Schema::hasTable('finance_supplier_debt_payments')) {
            return 0.0;
        }

        $query = DB::table('finance_supplier_debt_payments as p')
            ->where('p.supplier_debt_id', $debtId);

        if (Schema::hasTable('payment_requests') && Schema::hasColumn('finance_supplier_debt_payments', 'payment_request_id')) {
            $query->leftJoin('payment_requests as pr', 'pr.id', '=', 'p.payment_request_id');

            if (Schema::hasColumn('payment_requests', 'deleted_at')) {
                $query->where(function ($q) {
                    $q->whereNull('pr.deleted_at')->orWhereNull('pr.id');
                });
            }

            $query->where(function ($q) {
                $q->whereIn(DB::raw('LOWER(COALESCE(p.status, ""))'), $this->completedStatuses)
                    ->orWhereIn(DB::raw('LOWER(COALESCE(pr.status, ""))'), $this->completedStatuses);
            });
        } else {
            $query->whereIn(DB::raw('LOWER(COALESCE(p.status, ""))'), $this->completedStatuses);
        }

        return (float) $query->sum('p.amount');
    }

    private function firstNumber(object $row, array $columns): ?float
    {
        foreach ($columns as $column) {
            if (property_exists($row, $column) && $row->{$column} !== null && $row->{$column} !== '') {
                return (float) $row->{$column};
            }
        }

        return null;
    }
}
