<?php

namespace App\Services;

use App\Contracts\Repositories\CustomerRepositoryInterface;
use App\Contracts\Repositories\LeadRepositoryInterface;
use App\Contracts\Repositories\OrderRepositoryInterface;
use App\Contracts\Repositories\PaymentRepositoryInterface;
use App\Models\CRM\Orders\Order;
use App\Models\Payments\Payment;
use App\Models\User;
use Carbon\Carbon;

/**
 * Service tổng hợp số liệu dashboard chung (đơn hàng, doanh thu, lead, thanh toán).
 */
class DashboardService
{
    protected $orderRepo;

    protected $leadRepo;

    protected $customerRepo;

    protected $paymentRepo;

    /**
     * Khởi tạo service với các repository đơn hàng, lead, khách hàng, thanh toán.
     */
    public function __construct(
        OrderRepositoryInterface $orderRepo,
        LeadRepositoryInterface $leadRepo,
        CustomerRepositoryInterface $customerRepo,
        PaymentRepositoryInterface $paymentRepo
    ) {
        $this->orderRepo = $orderRepo;
        $this->leadRepo = $leadRepo;
        $this->customerRepo = $customerRepo;
        $this->paymentRepo = $paymentRepo;
    }

    /**
     * Phân tích khoảng ngày từ bộ lọc, mặc định là tháng hiện tại.
     */
    private function parseDateRange(array $filters): array
    {
        $fromRaw = $filters['from'] ?? null; // YYYY-MM-DD
        $toRaw = $filters['to'] ?? null;   // YYYY-MM-DD

        $from = $fromRaw ? Carbon::parse($fromRaw)->startOfDay() : now()->startOfMonth();
        $to = $toRaw ? Carbon::parse($toRaw)->endOfDay() : now()->endOfMonth();

        if ($from->greaterThan($to)) {
            [$from, $to] = [$to, $from];
        }

        return [$from, $to];
    }

    /**
     * Lấy top sales theo doanh thu trong khoảng thời gian (chỉ role admin/sales/sales_manager).
     */
    private function topSalesByRevenue(Carbon $from, Carbon $to, int $limit = 5)
    {
        // ✅ chỉ tính cho role admin/sales/sales_manager
        $roles = ['admin', 'sales', 'sales_manager'];

        // Spatie role tables: roles, model_has_roles
        // Nếu bạn dùng hệ role khác thì báo mình, mình sửa join theo schema đó.
        return User::query()
            ->whereHas('roles', fn ($q) => $q->whereIn('name', $roles))
            ->where('users.name', '!=', 'Ngọc Trân')
            ->leftJoin('crm_orders as o', function ($join) use ($from, $to) {
                $join->on('o.created_by', '=', 'users.id')
                    ->whereBetween('o.order_date', [$from->toDateString(), $to->toDateString()]);
            })
            ->groupBy('users.id', 'users.name')
            ->selectRaw('users.id, users.name, COALESCE(SUM(o.total_amount),0) as revenue')
            ->orderByDesc('revenue')
            ->limit($limit)
            ->get();
    }

    /**
     * Tổng hợp toàn bộ dữ liệu dashboard theo bộ lọc ngày.
     */
    public function getDashboardData(array $filters = []): array
    {
        [$from, $to] = $this->parseDateRange($filters);

        // ✅ Đơn hàng theo bộ lọc
        $total_orders = Order::query()
            ->whereBetween('order_date', [$from->toDateString(), $to->toDateString()])
            ->count();

        // ✅ Doanh thu theo tháng (lọc theo order_date)
        $revenue_by_month = Order::query()
            ->whereBetween('order_date', [$from->toDateString(), $to->toDateString()])
            ->selectRaw("DATE_FORMAT(order_date, '%Y-%m') as month, SUM(total_amount) as revenue")
            ->groupBy('month')
            ->orderBy('month')
            ->get();

        if ($revenue_by_month->isEmpty()) {
            $revenue_by_month = collect([['month' => 'N/A', 'revenue' => 0]]);
        }

        // ✅ Orders by month (nếu view dùng)
        $orders_by_month = Order::query()
            ->whereBetween('order_date', [$from->toDateString(), $to->toDateString()])
            ->selectRaw("DATE_FORMAT(order_date, '%Y-%m') as month, COUNT(*) as total")
            ->groupBy('month')
            ->orderBy('month')
            ->get();

        // ✅ Tổng thanh toán theo bộ lọc (lọc theo payment_date)
        $total_payments = Payment::query()
            ->whereBetween('payment_date', [$from->toDateString(), $to->toDateString()])
            ->sum('amount');

        // ✅ TOP sales theo doanh thu (đã lọc role)
        $top_sales = $this->topSalesByRevenue($from, $to, 5);

        /**
         * Các số liệu dưới đây phụ thuộc schema repo hiện tại.
         * Nếu bạn muốn chúng cũng lọc theo ngày, mình cần biết repo đang query theo cột nào.
         * Tạm thời giữ nguyên như bản cũ (không filter) để không vỡ màn hình.
         */
        $order_status_chart = $this->orderRepo->orderStatusCounts();
        $lead_sources = $this->leadRepo->leadSourceCounts();

        return [
            // customer/lead: hiện repo đang không hỗ trợ filter -> giữ nguyên
            'total_customers' => $this->customerRepo->count(),
            'total_customers_buy' => $this->customerRepo->countPurchased(),
            'total_leads' => $this->leadRepo->count(),

            // ✅ các cái này đã lọc theo from/to
            'total_orders' => $total_orders,
            'total_payments' => $total_payments,
            'revenue_by_month' => $revenue_by_month,
            'orders_by_month' => $orders_by_month,
            'top_sales' => $top_sales,

            // giữ nguyên
            'recent_leads' => $this->leadRepo->getRecent(5),
            'order_status_chart' => $order_status_chart,
            'lead_sources' => $lead_sources,
        ];
    }
}
