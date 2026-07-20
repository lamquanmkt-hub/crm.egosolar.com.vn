<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\Repositories\OrderRepositoryInterface;
use App\Contracts\Services\NotificationServiceInterface;
use App\Contracts\Services\OrderServiceInterface;
use App\Enums\OrderDepartment;
use App\Enums\OrderStatusCode;
use App\Models\CRM\Customers\CustomerDebt;
use App\Models\CRM\Leads\Lead;
use App\Models\CRM\Leads\LeadStatus;
use App\Models\CRM\Orders\Order;
use App\Models\CRM\Orders\OrderApproval;
use App\Models\CRM\Orders\OrderNotification;
use App\Models\CRM\Orders\OrderStatusHistory;
use App\Models\CRM\Orders\OrderStatusType;
use App\Models\Payments\Payment;
use App\Services\Order\OrderApprovalHandler;
use App\Services\Order\OrderInventoryHandler;
use App\Services\Order\OrderItemCalculator;
use App\Services\Order\OrderPaymentHandler;
use App\Validators\OrderValidator;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Service xử lý nghiệp vụ đơn hàng (Order): truy vấn, tạo/sửa, duyệt, xuất kho, thanh toán.
 */
class OrderService implements OrderServiceInterface
{
    /**
     * Khởi tạo service với repository và các handler nghiệp vụ đơn hàng.
     */
    public function __construct(
        protected OrderRepositoryInterface $orderRepo,
        protected \App\Contracts\Services\ProductStockServiceInterface $stockService,
        protected NotificationServiceInterface $notificationService,
        protected \App\Contracts\Services\PricingServiceInterface $pricingService,
        protected OrderApprovalHandler $approvalHandler,
        protected OrderInventoryHandler $inventoryHandler,
        protected OrderPaymentHandler $paymentHandler,
        protected OrderItemCalculator $itemCalculator,
    ) {}

    // =========================================================================
    // QUERIES
    // =========================================================================

    /**
     * Đếm tổng số đơn hàng.
     */
    public function count(): int
    {
        return $this->orderRepo->count();
    }

    /**
     * Lấy danh sách đơn hàng mới nhất.
     */
    public function getRecentOrders(int $limit = 5): Collection
    {
        return $this->orderRepo->getRecent($limit);
    }

    /**
     * Danh sách đơn hàng theo quyền user.
     */
    public function getOrdersByUserRole($user, array $filters = []): LengthAwarePaginator
    {
        $query = $this->buildOrderIndexQuery($user, $filters);

        return $query
            ->orderByDesc('order_date')
            ->orderByDesc('id')
            ->paginate(20)
            ->appends($filters);
    }

    /**
     * Tính tổng theo TOÀN BỘ kết quả lọc, không phải chỉ 20 dòng trang hiện tại.
     *
     * Không tính đơn đã hủy vào:
     * - Tổng doanh thu
     * - Đã thu
     * - Còn nợ
     * - Công nợ trên 30 ngày
     */
    public function getOrderIndexSummary($user, array $filters = []): array
    {
        $query = $this->buildOrderIndexQuery($user, $filters);

        $this->excludeCancelledOrders($query);

        $paymentTable = (new Payment)->getTable();

        $paidSql = "
            (
                SELECT COALESCE(SUM({$paymentTable}.amount), 0)
                FROM {$paymentTable}
                WHERE {$paymentTable}.order_id = crm_orders.id
            )
        ";

        $debtSql = "GREATEST(crm_orders.total_amount - {$paidSql}, 0)";

        $debt30Date = now()->subDays(30)->toDateString();

        $summary = (clone $query)
            ->selectRaw("
                COALESCE(SUM(crm_orders.total_amount), 0) AS total_amount,
                COALESCE(SUM({$paidSql}), 0) AS total_paid,
                COALESCE(SUM({$debtSql}), 0) AS total_debt,
                COALESCE(SUM(
                    CASE
                        WHEN {$debtSql} > 0
                             AND DATE(crm_orders.order_date) <= ?
                        THEN {$debtSql}
                        ELSE 0
                    END
                ), 0) AS debt_over_30
            ", [$debt30Date])
            ->first();

        return [
            'total_amount' => (float) ($summary->total_amount ?? 0),
            'total_paid' => (float) ($summary->total_paid ?? 0),
            'total_debt' => (float) ($summary->total_debt ?? 0),
            'debt_over_30' => (float) ($summary->debt_over_30 ?? 0),
        ];
    }

    /**
     * Query dùng chung cho danh sách và thống kê.
     */
    private function buildOrderIndexQuery($user, array $filters = [])
    {
        $query = Order::query()
            ->with([
                'lead.customer',
                'warehouse',
                'items.product',
                'items.warehouse',
                'payments',
                'creator',
                'currentStatusType',
            ]);

        if (
            $user
            && $user->hasRole('sales')
            && ! $user->hasRole(['admin', 'management', 'accounting', 'sales_manager', 'warehouse'])
        ) {
            $query->where('created_by', $user->id);
        }

        $this->applyOrderIndexFilters($query, $filters);

        return $query;
    }

    /**
     * Bộ lọc trang danh sách đơn hàng.
     */
    private function applyOrderIndexFilters($query, array $filters): void
    {
        $search = trim((string) ($filters['search'] ?? ''));

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('order_code', 'like', "%{$search}%")
                    ->orWhereHas('lead.customer', function ($customerQuery) use ($search) {
                        $customerQuery
                            ->where('name', 'like', "%{$search}%")
                            ->orWhere('phone', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%");
                    })
                    ->orWhereHas('items', function ($itemQuery) use ($search) {
                        $itemQuery->where('product_name', 'like', "%{$search}%");
                    });
            });
        }

        if (! empty($filters['status'])) {
            $status = $filters['status'];

            $statusMap = [
                'sales' => ['sales'],
                'ketoan' => ['accounting', 'ketoan'],
                'duyet1' => ['sales_manager', 'duyet1'],
                'duyet2' => ['management', 'director', 'duyet2'],
                'kho' => ['warehouse', 'kho'],
                'completed' => ['completed'],
                'cancelled' => ['cancelled', 'canceled', 'da_huy', 'huy'],
            ];

            $query->whereIn('current_department', $statusMap[$status] ?? [$status]);
        }

        if (! empty($filters['company_id'])) {
            $companyId = (int) $filters['company_id'];

            $query->where(function ($q) use ($companyId) {
                if (DB::getSchemaBuilder()->hasColumn('crm_orders', 'company_id')) {
                    $q->where('company_id', $companyId);
                }

                $q->orWhereHas('warehouse', function ($warehouseQuery) use ($companyId) {
                    $warehouseQuery->where('company_id', $companyId);
                });

                $q->orWhereHas('items.warehouse', function ($warehouseQuery) use ($companyId) {
                    $warehouseQuery->where('company_id', $companyId);
                });
            });
        }

        if (! empty($filters['created_by'])) {
            $query->where('created_by', (int) $filters['created_by']);
        }

        if (! empty($filters['from_date'])) {
            $query->whereDate('order_date', '>=', $filters['from_date']);
        }

        if (! empty($filters['to_date'])) {
            $query->whereDate('order_date', '<=', $filters['to_date']);
        }

        if (! empty($filters['payment_filter'])) {
            $this->applyOrderPaymentFilter($query, (string) $filters['payment_filter']);
        }
    }

    /**
     * Lọc công nợ/thanh toán.
     */
    private function applyOrderPaymentFilter($query, string $paymentFilter): void
    {
        $this->excludeCancelledOrders($query);

        $paymentTable = (new Payment)->getTable();

        $paidSql = "
            (
                SELECT COALESCE(SUM({$paymentTable}.amount), 0)
                FROM {$paymentTable}
                WHERE {$paymentTable}.order_id = crm_orders.id
            )
        ";

        if ($paymentFilter === 'paid') {
            $query->whereRaw("{$paidSql} >= crm_orders.total_amount")
                ->where('crm_orders.total_amount', '>', 0);

            return;
        }

        if ($paymentFilter === 'debt') {
            $query->whereRaw("{$paidSql} < crm_orders.total_amount")
                ->where('crm_orders.total_amount', '>', 0);

            return;
        }

        if ($paymentFilter === 'debt_30') {
            $query->whereRaw("{$paidSql} < crm_orders.total_amount")
                ->where('crm_orders.total_amount', '>', 0)
                ->whereDate('crm_orders.order_date', '<=', now()->subDays(30)->toDateString());

            return;
        }

        if ($paymentFilter === 'unpaid') {
            $query->whereRaw("{$paidSql} = 0")
                ->where('crm_orders.total_amount', '>', 0);
        }
    }

    /**
     * Không tính đơn đã hủy.
     */
    private function excludeCancelledOrders($query): void
    {
        $cancelValues = [
            'cancelled',
            'canceled',
            'da_huy',
            'huy',
        ];

        $query->where(function ($q) use ($cancelValues) {
            $q->whereNull('crm_orders.current_department')
                ->orWhereNotIn('crm_orders.current_department', $cancelValues);
        });

        if (DB::getSchemaBuilder()->hasColumn('crm_orders', 'status')) {
            $query->where(function ($q) use ($cancelValues) {
                $q->whereNull('crm_orders.status')
                    ->orWhereNotIn('crm_orders.status', $cancelValues);
            });
        }
    }

    /**
     * Lấy danh sách đơn hàng phân trang theo bộ lọc.
     */
    public function getAllOrders(array $filters = []): LengthAwarePaginator
    {
        return $this->orderRepo->search($filters);
    }

    /**
     * Tìm đơn hàng theo ID.
     */
    public function find($id): ?Order
    {
        return $this->orderRepo->find($id);
    }

    /**
     * Lấy chi tiết đơn hàng kèm items, thanh toán và khách hàng.
     */
    public function findWithDetails($id): Order
    {
        return Order::query()
            ->with([
                'items' => fn ($q) => $q->with(['product', 'warehouse']),
                'payments' => fn ($q) => $q->with(['method', 'recordedBy'])->orderByDesc('payment_date'),
                'customer',
            ])
            ->findOrFail($id);
    }

    /**
     * Lấy bộ phận đang xử lý đơn hàng.
     */
    public function getCurrentApprovalLevel(Order $order): ?string
    {
        return $order->current_department;
    }

    /**
     * Lấy timeline lịch sử trạng thái của đơn hàng.
     */
    public function getOrderTimeline($orderId)
    {
        return OrderStatusHistory::where('order_id', $orderId)
            ->with('changedBy', 'statusType')
            ->orderBy('changed_at', 'asc')
            ->get();
    }

    /**
     * Lấy các thông báo của đơn dành cho người tạo đơn.
     */
    public function getOrderNotifications($orderId)
    {
        $order = $this->orderRepo->find($orderId);

        return OrderNotification::where('order_id', $orderId)
            ->where('user_id', $order->created_by)
            ->orderByDesc('created_at')
            ->get();
    }

    /**
     * Thống kê đơn hàng và doanh thu của một sales.
     */
    public function getSalesStatistics(int $salesId): array
    {
        return [
            'total_orders' => Order::where('created_by', $salesId)->count(),
            'pending' => Order::where('created_by', $salesId)->where('current_department', '!=', 'completed')->count(),
            'completed' => Order::where('created_by', $salesId)->where('current_department', 'completed')->count(),
            'total_revenue' => (float) Order::where('created_by', $salesId)->where('payment_recorded', true)->sum('total_amount'),
        ];
    }

    /**
     * Lấy các đơn hàng gần đây của một sales.
     */
    public function getRecentOrdersBySales(int $salesId, int $limit = 10)
    {
        return Order::where('created_by', $salesId)
            ->with(['lead.customer', 'warehouse', 'currentStatusType'])
            ->latest()
            ->take($limit)
            ->get();
    }

    /**
     * Lấy các thông báo chưa đọc của sales.
     */
    public function getPendingNotifications(int $salesId)
    {
        return OrderNotification::where('user_id', $salesId)
            ->where('is_read', false)
            ->with('order')
            ->latest()
            ->get();
    }

    // =========================================================================
    // COMMANDS
    // =========================================================================

    /**
     * Tạo đơn hàng mới: đảm bảo có lead, tạo items, approval ban đầu và ghi lịch sử.
     */
    public function createOrder(array $data): Order
    {
        return DB::transaction(function () use ($data) {
            $data = $this->ensureLeadExists($data);

            $order = $this->createOrderRecord($data);
            $this->itemCalculator->createOrderItems($order, $data['items'], $this->pricingService);
            $this->approvalHandler->createInitialApproval($order);
            $this->recordStatusHistory($order, null, OrderDepartment::SALES->value, 'Đơn hàng được tạo bởi Sales');

            return $order->fresh(['items.product', 'lead.customer']);
        });
    }

    /**
     * Cập nhật đơn hàng: kiểm tra quyền, chuẩn hóa dữ liệu và đồng bộ items.
     */
    public function updateOrder($id, array $data): Order
    {
        return DB::transaction(function () use ($id, $data) {
            $order = $this->orderRepo->find($id);
            $user = Auth::user();

            $this->guardUpdatePermission($order, $user);

            $data = $this->preserveLeadId($data, $order);
            $data = $this->normalizeOrderDate($data);

            if (! empty($data['items'])) {
                $data['company_id'] = $this->resolveCompanyIdFromItems($data);
            }

            $this->updateOrderRecord($order, $data);
            $this->itemCalculator->updateOrderItems($order, $data['items'] ?? []);

            return $order->fresh(['items.product', 'lead.customer']);
        });
    }

    /**
     * Gửi đơn từ Sales sang Sales Manager duyệt.
     */
    public function submitForApproval($id): void
    {
        DB::transaction(function () use ($id) {
            $order = $this->orderRepo->find($id);
            OrderValidator::canSubmit($order);

            $this->approvalHandler->transitionToDepartment($order, OrderDepartment::SALES_MANAGER, OrderDepartment::SALES->statusCode());
            $this->approvalHandler->createApprovalRecord($order, OrderDepartment::SALES_MANAGER->value);
            $this->recordStatusHistory($order, OrderDepartment::SALES->value, OrderDepartment::SALES_MANAGER->value, 'Sales gửi đơn chờ Sales Manager duyệt');
            $this->notificationService->notifyDepartment('sales_manager', $order, 'Đơn hàng mới cần Sales Manager duyệt');
        });
    }

    /**
     * Điều phối hành động duyệt/từ chối; ở bước kho thì chuyển sang xuất kho.
     */
    public function processApproval(string $id, array $data): void
    {
        $action = $data['action'] ?? 'approve';
        $order = $this->orderRepo->find($id);

        if ($action === 'approve' && $order->current_department === OrderDepartment::WAREHOUSE->value) {
            $this->shipOrder($id, [
                'shipping_note' => $data['note'] ?? null,
                'serials' => $data['serials'] ?? [],
            ]);

            return;
        }

        if ($action === 'approve') {
            $this->approveOrder($id, $data);
        } else {
            $this->rejectOrder($id, $data);
        }
    }

    /**
     * Duyệt đơn ở bộ phận hiện tại và chuyển sang bộ phận kế tiếp.
     */
    public function approveOrder($id, array $data): void
    {
        DB::transaction(function () use ($id, $data) {
            $order = $this->orderRepo->find($id);
            $user = Auth::user();
            $currentDept = OrderDepartment::from($order->current_department);
            $nextDept = $currentDept->nextDepartment();

            $this->approvalHandler->updateCurrentApproval($order, $currentDept, $user, $data);
            $this->approvalHandler->transitionToDepartment($order, $nextDept, $currentDept->statusCode());

            if ($currentDept === OrderDepartment::MANAGEMENT) {
                $this->approvalHandler->finalizeApproval($order, $user);
            }

            if ($nextDept !== OrderDepartment::COMPLETED) {
                $this->approvalHandler->createApprovalRecord($order, $nextDept->value);
            }

            $note = "Đã duyệt bởi {$user->name}".(isset($data['note']) ? " - GC: {$data['note']}" : '');
            $this->recordStatusHistory($order, $currentDept->value, $nextDept->value, $note);
            $this->sendApprovalNotifications($order, $currentDept, $nextDept);
        });
    }

    /**
     * Từ chối đơn và trả về Sales kèm lý do.
     */
    public function rejectOrder($id, array $data): void
    {
        DB::transaction(function () use ($id, $data) {
            $order = $this->orderRepo->find($id);
            $user = Auth::user();
            $currentDept = OrderDepartment::from($order->current_department);

            $this->approvalHandler->updateRejectedApproval($order, $currentDept->value, $user, $data);
            $this->approvalHandler->transitionToDepartment($order, OrderDepartment::SALES, OrderStatusCode::REJECTED);

            $note = "Từ chối bởi {$user->name}: ".($data['rejection_reason'] ?? '');
            $this->recordStatusHistory($order, $currentDept->value, OrderDepartment::SALES->value, $note);

            $this->notificationService->notifySales(
                $order->created_by,
                $order,
                'rejected',
                "Đơn #{$order->order_code} bị từ chối bởi {$currentDept->label()}: {$data['rejection_reason']}"
            );
        });
    }

    /**
     * Hủy đơn hàng (chỉ khi còn ở bộ phận Sales).
     */
    public function cancelOrder($id): void
    {
        DB::transaction(function () use ($id) {
            $order = $this->orderRepo->find($id);

            if ($order->current_department !== OrderDepartment::SALES->value) {
                throw new \Exception('Chỉ được hủy đơn hàng khi đang ở bộ phận Sales.');
            }

            $this->approvalHandler->transitionToDepartment($order, null, OrderStatusCode::CANCELLED, 'cancelled');

            $this->recordStatusHistory($order, OrderDepartment::SALES->value, 'cancelled', 'Đơn hàng được hủy bởi '.Auth::user()->name);
            $this->notificationService->notifyDepartment(
                'sales_manager',
                $order,
                "Đơn hàng #{$order->order_code} đã bị hủy bởi {$order->creator->name}"
            );
        });
    }

    /**
     * Xóa đơn hàng cùng toàn bộ dữ liệu liên quan (trừ đơn đã hoàn tất).
     */
    public function deleteOrder($id): void
    {
        DB::transaction(function () use ($id) {
            $order = $this->orderRepo->find($id);

            if ($order->current_department === OrderDepartment::COMPLETED->value) {
                throw new \Exception('Không thể xóa đơn hàng đã hoàn tất.');
            }

            $deletedByUser = Auth::user();
            $orderCode = $order->order_code;

            $order->items()->delete();
            OrderApproval::where('order_id', $order->id)->delete();
            Payment::where('order_id', $order->id)->delete();
            CustomerDebt::where('order_id', $order->id)->delete();
            OrderNotification::where('order_id', $order->id)->delete();
            OrderStatusHistory::where('order_id', $order->id)->delete();

            Log::info('Order deleted', [
                'order_id' => $order->id,
                'order_code' => $orderCode,
                'deleted_by' => $deletedByUser->id,
                'deleted_by_name' => $deletedByUser->name,
                'original_department' => $order->current_department,
            ]);

            $this->notificationService->notifySales(
                $order->created_by,
                $order,
                'deleted',
                "Đơn hàng #{$orderCode} đã bị xóa bởi {$deletedByUser->name}"
            );

            $order->delete();
        });
    }

    /**
     * Xuất kho đơn hàng thông qua inventory handler.
     */
    public function shipOrder($id, array $data): void
    {
        $this->inventoryHandler->shipOrder($id, $data, $this->orderRepo, $this);
    }

    /**
     * Ghi nhận thanh toán cho đơn thông qua payment handler.
     */
    public function recordPayment($id, array $data): void
    {
        $this->paymentHandler->recordPayment($id, $data, $this->orderRepo, $this->notificationService);
    }

    /**
     * Lấy payload serial phục vụ modal xuất kho.
     */
    public function getShipSerialsPayload(Order $order): array
    {
        return $this->inventoryHandler->getShipSerialsPayload($order);
    }

    // =========================================================================
    // HELPERS
    // =========================================================================

    /**
     * Đảm bảo dữ liệu có lead_id, tự tạo lead mới nếu thiếu.
     */
    private function ensureLeadExists(array $data): array
    {
        if (! empty($data['lead_id'])) {
            return $data;
        }

        $newStatus = LeadStatus::where('name', 'Mới')->first();

        $lead = Lead::create([
            'customer_id' => $data['customer_id'],
            'status_id' => $newStatus?->id,
            'created_by' => Auth::id(),
        ]);

        $data['lead_id'] = $lead->id;

        return $data;
    }

    /**
     * Tạo bản ghi đơn hàng với mã đơn, tổng tiền và bộ phận khởi tạo.
     */
    private function createOrderRecord(array $data): Order
    {
        $initialStatus = OrderStatusType::where('code', 'PENDING_APPROVAL')->first();
        $finalTotal = $this->itemCalculator->calcOrderTotalFromItems($data['items'] ?? []);

        $warehouseIds = collect($data['items'] ?? [])
            ->pluck('warehouse_id')
            ->filter()
            ->unique()
            ->values();

        $companyId = $this->resolveCompanyIdFromItems($data);

        return $this->orderRepo->create([
            'order_code' => $this->generateOrderCode(),
            'lead_id' => $data['lead_id'],
            'order_date' => $data['order_date'],
            'warehouse_id' => $warehouseIds->count() === 1 ? (int) $warehouseIds->first() : null,
            'company_id' => $companyId,
            'price_tier_id' => $data['price_tier_id'] ?? null,
            'total_amount' => $finalTotal,
            'created_by' => Auth::id(),
            'current_department' => OrderDepartment::SALES->value,
            'current_status_type_id' => $initialStatus?->id,
        ]);
    }

    /**
     * Cập nhật các trường cho phép của bản ghi đơn hàng.
     */
    private function updateOrderRecord(Order $order, array $data): void
    {
        $fillable = ['lead_id', 'order_date', 'warehouse_id', 'note', 'company_id', 'price_tier_id'];
        $payload = [];

        foreach ($fillable as $field) {
            if (array_key_exists($field, $data)) {
                $payload[$field] = $data[$field];
            }
        }

        if (! empty($payload)) {
            $order->update($payload);
        }
    }

    /**
     * Chặn sửa đơn đã gửi duyệt nếu user không có quyền đặc biệt.
     */
    private function guardUpdatePermission(Order $order, $user): void
    {
        $isPrivileged = $user && $user->hasRole(['admin', 'super_admin', 'accounting']);

        if (! $isPrivileged && ($order->current_department ?? 'sales') !== OrderDepartment::SALES->value) {
            throw new \Exception('Đơn hàng đã gửi duyệt, không thể chỉnh sửa lúc này.');
        }
    }

    /**
     * Giữ lead_id cũ của đơn nếu request không gửi lên.
     */
    private function preserveLeadId(array $data, Order $order): array
    {
        if (empty($data['lead_id']) && ! empty($order->lead_id)) {
            $data['lead_id'] = $order->lead_id;
        }

        return $data;
    }

    /**
     * Chuẩn hóa ngày đơn hàng về định dạng Y-m-d.
     */
    private function normalizeOrderDate(array $data): array
    {
        if (empty($data['order_date'])) {
            return $data;
        }

        try {
            $data['order_date'] = Carbon::parse($data['order_date'])->toDateString();
        } catch (\Throwable) {
            try {
                $data['order_date'] = Carbon::createFromFormat('d/m/Y', $data['order_date'])->toDateString();
            } catch (\Throwable) {
                // giữ nguyên nếu không parse được
            }
        }

        return $data;
    }

    /**
     * Suy ra company_id từ các kho trong items, chặn đơn lấy hàng từ nhiều công ty.
     */
    private function resolveCompanyIdFromItems(array $data): ?int
    {
        if (! empty($data['company_id'])) {
            return (int) $data['company_id'];
        }

        $warehouseIds = collect($data['items'] ?? [])
            ->pluck('warehouse_id')
            ->filter()
            ->unique()
            ->values();

        if ($warehouseIds->isEmpty()) {
            return null;
        }

        $companyIds = DB::table('crm_warehouses')
            ->whereIn('id', $warehouseIds)
            ->pluck('company_id')
            ->filter()
            ->unique()
            ->values();

        if ($companyIds->count() > 1) {
            throw new \Exception('Một đơn hàng không được lấy hàng từ nhiều công ty khác nhau.');
        }

        if ($companyIds->count() === 1) {
            return (int) $companyIds->first();
        }

        $pivotCompanyIds = DB::table('company_warehouse')
            ->whereIn('warehouse_id', $warehouseIds)
            ->pluck('company_id')
            ->filter()
            ->unique()
            ->values();

        if ($pivotCompanyIds->count() > 1) {
            throw new \Exception('Một đơn hàng không được lấy hàng từ nhiều công ty khác nhau.');
        }

        return $pivotCompanyIds->count() === 1
            ? (int) $pivotCompanyIds->first()
            : null;
    }

    /**
     * Sinh mã đơn hàng tăng dần theo ngày (ORDyyyymmddNNNN).
     */
    protected function generateOrderCode(): string
    {
        $prefix = 'ORD'.now()->format('Ymd');

        $lastCode = Order::where('order_code', 'like', $prefix.'%')
            ->lockForUpdate()
            ->orderByDesc('order_code')
            ->value('order_code');

        $next = 1;

        if ($lastCode) {
            $next = (int) substr($lastCode, -4) + 1;
        }

        return sprintf('%s%04d', $prefix, $next);
    }

    /**
     * Ghi lịch sử chuyển trạng thái/bộ phận của đơn.
     */
    public function recordStatusHistory(Order $order, ?string $from, string $to, string $note): void
    {
        OrderStatusHistory::create([
            'order_id' => $order->id,
            'from_department' => $from,
            'to_department' => $to,
            'note' => $note,
            'changed_by' => Auth::id(),
            'changed_at' => now(),
            'status_type_id' => $order->current_status_type_id,
        ]);
    }

    /**
     * Gửi thông báo cho sales và bộ phận kế tiếp sau khi duyệt.
     */
    private function sendApprovalNotifications(Order $order, OrderDepartment $from, OrderDepartment $to): void
    {
        $this->notificationService->notifySales(
            $order->created_by,
            $order,
            'status_change',
            "Đơn hàng #{$order->order_code} đã được {$from->label()} duyệt, chuyển sang {$to->label()}"
        );

        if ($to !== OrderDepartment::COMPLETED) {
            $msg = ($to === OrderDepartment::MANAGEMENT)
                ? "Đơn hàng #{$order->order_code} đã được Kế toán duyệt, chờ Giám đốc phê duyệt."
                : "Đơn #{$order->order_code} cần xử lý.";

            $this->notificationService->notifyDepartment($to->value, $order, $msg);
        }
    }
}
