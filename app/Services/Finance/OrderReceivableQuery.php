<?php

declare(strict_types=1);

namespace App\Services\Finance;

use App\Services\OrderReturnFinancialService;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Nguồn số dư công nợ thương mại chuẩn, dùng chung cho Dashboard và báo cáo Tài chính.
 *
 * Số dư đơn = max(crm_orders.total_amount − giá trị hàng khách đã trả lại − tổng crm_payments.amount của đơn, 0).
 * Hàng trả lại = phiếu return/recall đã nhập kho (xem OrderReturnFinancialService::returnCreditSql).
 * Bảng crm_customer_debts CHỈ được dùng làm metadata (hạn thanh toán), không dùng
 * debt_amount/paid_amount vì bảng này có thể lệch so với thanh toán thực tế.
 *
 * Query gốc trả về đúng 1 dòng / 1 đơn với alias:
 * - `o`   crm_orders
 * - `pay` tổng thanh toán theo đơn (paid_amount)
 * - `due` hạn thanh toán sớm nhất theo đơn (due_date), nếu có bảng công nợ
 * - `c`   khách hàng qua lead (customer_id, name), nếu có bảng lead/khách
 */
final class OrderReceivableQuery
{
    /** Giá trị current_department coi là đơn đã hủy. */
    public const CANCELLED_DEPARTMENTS = ['cancelled', 'canceled', 'da_huy', 'huy'];

    /** Biểu thức tổng đã trả của đơn (chưa chặn trần). */
    public const PAID_EXPR = 'COALESCE(pay.paid_amount, 0)';

    /** Tổng đơn phải thu = tổng đơn − hàng khách đã trả lại (không âm). */
    public static function netTotalExpr(): string
    {
        return 'GREATEST(o.total_amount - '.OrderReturnFinancialService::returnCreditSql('o.id').', 0)';
    }

    /** Biểu thức số tiền đã trả được tính vào đơn (không vượt tổng đơn phải thu). */
    public static function paidCappedExpr(): string
    {
        return 'LEAST(COALESCE(pay.paid_amount, 0), '.self::netTotalExpr().')';
    }

    /** Biểu thức số dư chuẩn của đơn (đã trừ hàng trả lại). */
    public static function balanceExpr(): string
    {
        return 'GREATEST('.self::netTotalExpr().' - COALESCE(pay.paid_amount, 0), 0)';
    }

    /**
     * Tổng thanh toán gom theo order_id (gom trước khi join để không nhân bản đơn).
     */
    public function paymentsByOrder(): Builder
    {
        return DB::table('crm_payments')
            ->select('order_id')
            ->selectRaw('SUM(amount) AS paid_amount')
            ->groupBy('order_id');
    }

    /**
     * Đơn thương mại hợp lệ (chưa hủy, chưa xoá mềm) kèm tổng đã thanh toán.
     * Chưa lọc xuất kho/số dư — dùng cho doanh thu ghi nhận và bảng hiệu quả sales.
     */
    public function orders(): Builder
    {
        $query = DB::table('crm_orders as o')
            ->leftJoinSub($this->paymentsByOrder(), 'pay', 'pay.order_id', '=', 'o.id');

        if (Schema::hasColumn('crm_orders', 'deleted_at')) {
            $query->whereNull('o.deleted_at');
        }

        if (Schema::hasColumn('crm_orders', 'current_department')) {
            $query->where(function (Builder $sub): void {
                $sub->whereNull('o.current_department')
                    ->orWhereNotIn('o.current_department', self::CANCELLED_DEPARTMENTS);
            });
        }

        return $query;
    }

    /**
     * Đơn đã phát sinh công nợ (đã xuất kho) và còn số dư > 0, kèm hạn thanh toán và khách hàng.
     *
     * Công nợ thương mại phát sinh khi Kho xuất hàng (OrderInventoryHandler), nên đơn
     * chưa xuất kho — kể cả đang chờ duyệt — không phải khoản phải thu.
     */
    public function outstanding(): Builder
    {
        $query = $this->orders()
            ->where('o.inventory_issued', 1)
            ->whereRaw(self::balanceExpr().' > 0');

        if (Schema::hasTable('crm_customer_debts') && Schema::hasColumn('crm_customer_debts', 'due_date')) {
            // Một đơn có thể có nhiều dòng công nợ: gom về 1 dòng/đơn, lấy hạn sớm nhất.
            $dueDates = DB::table('crm_customer_debts')
                ->select('order_id')
                ->selectRaw('MIN(due_date) AS due_date')
                ->groupBy('order_id');

            $query->leftJoinSub($dueDates, 'due', 'due.order_id', '=', 'o.id');
        } else {
            $query->crossJoinSub(DB::query()->selectRaw('NULL AS due_date'), 'due');
        }

        if (Schema::hasTable('crm_leads') && Schema::hasTable('crm_customers')) {
            $query->leftJoin('crm_leads as l', 'l.id', '=', 'o.lead_id')
                ->leftJoin('crm_customers as c', 'c.id', '=', 'l.customer_id');
        } else {
            $query->crossJoinSub(DB::query()->selectRaw('NULL AS id, NULL AS name'), 'c');
        }

        return $query;
    }
}
