<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Contracts\Services\CustomerServiceInterface;
use App\Contracts\Services\OrderServiceInterface;
use App\Contracts\Services\PricingServiceInterface;
use App\Contracts\Services\ProductServiceInterface;
use App\Contracts\Services\WarehouseServiceInterface;
use App\Http\Requests\OrderRequest;
use App\Models\Core\Company;
use App\Models\CRM\Orders\Order;
use App\Models\CRM\Orders\OrderEditHistory;
use App\Models\CRM\Orders\OrderNotification;
use App\Models\Inventory\Pricing\PriceTier;
use App\Models\Payments\PaymentMethod;
use App\Models\User;
use App\Services\Order\OrderExcelExporter;
use App\Services\Order\OrderProductPickerService;
use App\Services\Order\OrderSerialWarrantyService;
use App\Services\Order\OrderStockGuard;
use App\Traits\HandleException;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Controller quản lý đơn hàng: CRUD, duyệt đơn, thanh toán, hóa đơn, giao hàng, xuất kho và các API hỗ trợ form đơn.
 */
class OrderController extends Controller
{
    use HandleException;

    /**
     * Khởi tạo controller, inject các service đơn hàng, khách hàng, sản phẩm và kho.
     */
    public function __construct(
        protected OrderServiceInterface $orderService,
        protected CustomerServiceInterface $customerService,
        protected ProductServiceInterface $productService,
        protected WarehouseServiceInterface $warehouseService,
        protected PricingServiceInterface $pricingService,
        protected OrderProductPickerService $productPicker,
        protected OrderStockGuard $stockGuard,
        protected OrderSerialWarrantyService $serialWarrantyService,
        protected OrderExcelExporter $orderExcelExporter,
    ) {}

    // =========================================================================
    // CRUD
    // =========================================================================

    /**
     * Danh sách đơn hàng.
     */
    public function index(Request $request): View
    {
        $filters = $request->all();

        $orders = $this->orderService->getOrdersByUserRole(Auth::user(), $filters);

        // EGO_ORDERS_UI_PRO_V2_INDEX_LOAD
        if (method_exists($orders, 'getCollection')) {
            $orders->getCollection()->loadMissing([
                'lead.customer', 'payments', 'currentStatusType', 'creator', 'company', 'warehouse',
                'returns.receivingWarehouse', 'returns.refunds',
            ]);
        }

        /*
        |--------------------------------------------------------------------------
        | Tổng doanh thu / đã thu / còn nợ / công nợ trên 30 ngày
        |--------------------------------------------------------------------------
        | Tính từ OrderService theo TOÀN BỘ kết quả lọc,
        | không phải chỉ 20 dòng trang hiện tại.
        */
        $orderSummary = $this->orderService->getOrderIndexSummary(Auth::user(), $filters);

        $companies = Company::query()
            ->where(function ($q) {
                $q->where('is_active', 1)
                    ->orWhereNull('is_active');
            })
            ->orderBy('name')
            ->get();

        $creatorIds = DB::table('crm_orders')
            ->whereNotNull('created_by')
            ->distinct()
            ->pluck('created_by');

        $creators = User::query()
            ->select('id', 'name')
            ->whereIn('id', $creatorIds)
            ->orderBy('name')
            ->get();

        return view('orders.index', compact(
            'orders',
            'orderSummary',
            'companies',
            'creators'
        ));
    }

    /**
     * Form tạo đơn hàng mới.
     */
    public function create(): View
    {
        $this->authorize('create', Order::class);

        $oldCustomerId = (int) session()->getOldInput('customer_id');

        $selectedCustomer = $oldCustomerId
            ? $this->customerService->getCustomerOptionForOrderSelect($oldCustomerId)
            : null;

        return view('orders.create', [
            'selectedCustomer' => $selectedCustomer,
            'companies' => Company::orderBy('name')->get(),
            'warehouses' => $this->warehouseService->getActiveWarehouses(),
            'paymentMethods' => PaymentMethod::where('is_active', true)->get(),
            'priceTiers' => PriceTier::where('is_active', true)->orderBy('priority')->orderBy('name')->get(),
        ]);
    }

    /**
     * Lưu đơn hàng mới.
     */
    public function store(OrderRequest $request): RedirectResponse
    {
        $this->authorize('create', Order::class);

        try {
            $data = $request->validatedWithProcessing();
            $data = $this->resolveLeadId($data);
            $order = $this->orderService->createOrder($data);

            $this->dispatchOrderCreatedNotifications($order);

            return redirect()
                ->route('orders.show', $order->id)
                ->with('success', "Đơn hàng đã được tạo thành công. Mã đơn: {$order->order_code}");
        } catch (\Throwable $e) {
            $this->logError($e, 'store');

            return back()
                ->withInput()
                ->with('error', $this->getUserFriendlyMessage($e));
        }
    }

    /**
     * Chi tiết đơn hàng.
     */
    public function show(string $id): View
    {
        $order = $this->orderService->findWithDetails($id);

        $this->authorize('view', $order);

        $order->loadMissing([
            'lead.customer.customerType',
            'items.product',
            'items.warehouse',
            'company',
            'warehouse',
            'creator',
            'approver',
            'approvals.approver',
            'payments.method',
            'payments.recordedBy',
            'currentStatusType',
            'debt',
            'returns.items.product',
            'returns.items.orderItem',
            'returns.items.serials.serialUnit.identifiers.serialIdentifier',
            'returns.attachments',
            'returns.approvals.approver',
            'returns.histories.user',
            'returns.refunds',
            'returns.requester',
            'returns.receivingWarehouse',
        ]);

        $itemIds = $order->items->pluck('id')->map(fn ($value) => (int) $value)->all();
        $returnAvailable = [];
        $returnSerialsByItem = [];

        foreach ($order->items as $item) {
            $used = Schema::hasTable('order_return_items')
                ? (int) DB::table('order_return_items as ri')
                    ->join('order_returns as r', 'r.id', '=', 'ri.order_return_id')
                    ->where('ri.order_item_id', $item->id)
                    ->whereNotIn('r.status', ['rejected', 'cancelled'])
                    ->sum('ri.accepted_quantity')
                : 0;

            $pending = Schema::hasTable('order_return_items')
                ? (int) DB::table('order_return_items as ri')
                    ->join('order_returns as r', 'r.id', '=', 'ri.order_return_id')
                    ->where('ri.order_item_id', $item->id)
                    ->whereNotIn('r.status', ['completed', 'rejected', 'cancelled'])
                    ->sum('ri.requested_quantity')
                : 0;

            $returnAvailable[$item->id] = max(0, (int) $item->quantity - $used - $pending);

            $returnSerialsByItem[$item->id] = collect();
            if (
                Schema::hasTable('crm_order_item_serial_units') &&
                Schema::hasTable('crm_serial_units')
            ) {
                $serialQuery = DB::table('crm_order_item_serial_units as oi')
                    ->join('crm_serial_units as su', 'su.id', '=', 'oi.serial_unit_id')
                    ->where('oi.order_item_id', $item->id);

                if (Schema::hasTable('crm_serial_unit_identifiers')) {
                    $serialQuery->leftJoin('crm_serial_unit_identifiers as sui', 'sui.serial_unit_id', '=', 'su.id');
                }
                if (Schema::hasTable('crm_serial_identifiers')) {
                    $serialQuery->leftJoin('crm_serial_identifiers as si', 'si.id', '=', 'sui.serial_identifier_id');
                }
                if (Schema::hasTable('crm_serial_unit_states')) {
                    $serialQuery->leftJoin('crm_serial_unit_states as st', 'st.serial_unit_id', '=', 'su.id');
                }

                $returnSerialsByItem[$item->id] = $serialQuery
                    ->select(
                        'su.id',
                        DB::raw("COALESCE(MAX(si.code), CONCAT('#', su.id)) as code"),
                        DB::raw("COALESCE(MAX(st.state), 'unknown') as state")
                    )
                    ->groupBy('su.id')
                    ->get();
            }
        }

        $returnWarehouses = Schema::hasTable('crm_warehouses')
            ? DB::table('crm_warehouses')
                ->when($order->company_id, fn ($query) => $query->where('company_id', $order->company_id))
                ->orderBy('name')
                ->get()
            : collect();

        $stockAllocations = collect();
        if (Schema::hasTable('crm_order_item_stock_allocations')) {
            $allocationQuery = DB::table('crm_order_item_stock_allocations as allocation')
                ->where('allocation.order_id', $order->id);

            if (Schema::hasTable('crm_product_stock_lots')) {
                $allocationQuery->leftJoin('crm_product_stock_lots as lot', 'lot.id', '=', 'allocation.stock_lot_id');
            }
            if (Schema::hasTable('crm_product_catalog')) {
                $allocationQuery->leftJoin('crm_product_catalog as product', 'product.id', '=', 'allocation.product_id');
            }
            if (Schema::hasTable('crm_warehouses')) {
                $allocationQuery->leftJoin('crm_warehouses as warehouse', 'warehouse.id', '=', 'allocation.warehouse_id');
            }

            $stockAllocations = $allocationQuery
                ->select(
                    'allocation.*',
                    DB::raw('lot.lot_code as lot_code'),
                    DB::raw('product.name as product_name'),
                    DB::raw('warehouse.name as warehouse_name')
                )
                ->orderBy('allocation.id')
                ->get();
        }

        $orderSerials = collect();
        if ($itemIds && Schema::hasTable('crm_order_item_serial_units') && Schema::hasTable('crm_serial_units')) {
            $serialQuery = DB::table('crm_order_item_serial_units as link')
                ->join('crm_serial_units as unit', 'unit.id', '=', 'link.serial_unit_id')
                ->whereIn('link.order_item_id', $itemIds)
                ->leftJoin('crm_order_items as item', 'item.id', '=', 'link.order_item_id');

            if (Schema::hasTable('crm_product_catalog')) {
                $serialQuery->leftJoin('crm_product_catalog as product', 'product.id', '=', 'unit.product_id');
            }
            if (Schema::hasTable('crm_warehouses')) {
                $serialQuery->leftJoin('crm_warehouses as warehouse', 'warehouse.id', '=', 'unit.warehouse_id');
            }
            if (Schema::hasTable('crm_serial_unit_identifiers')) {
                $serialQuery->leftJoin('crm_serial_unit_identifiers as sui', 'sui.serial_unit_id', '=', 'unit.id');
            }
            if (Schema::hasTable('crm_serial_identifiers')) {
                $serialQuery->leftJoin('crm_serial_identifiers as identifier', 'identifier.id', '=', 'sui.serial_identifier_id');
            }
            if (Schema::hasTable('crm_serial_unit_states')) {
                $serialQuery->leftJoin('crm_serial_unit_states as state', 'state.serial_unit_id', '=', 'unit.id');
            }

            $orderSerials = $serialQuery
                ->select(
                    'link.order_item_id',
                    'unit.id as serial_unit_id',
                    'unit.product_id',
                    DB::raw("COALESCE(MAX(product.name), MAX(item.product_name), CONCAT('SP #', unit.product_id)) as product_name"),
                    DB::raw("COALESCE(MAX(identifier.code), CONCAT('#', unit.id)) as serial_code"),
                    DB::raw("COALESCE(MAX(state.state), 'unknown') as state"),
                    DB::raw('MAX(warehouse.name) as warehouse_name')
                )
                ->groupBy('link.order_item_id', 'unit.id', 'unit.product_id')
                ->orderBy('link.order_item_id')
                ->get();
        }

        $stockMovements = collect();
        if (Schema::hasTable('crm_stock_movements')) {
            $movementQuery = DB::table('crm_stock_movements as movement')
                ->where(function ($query) use ($order) {
                    $query->where('movement.reference_id', $order->id)
                        ->orWhere('movement.reason', 'like', '%'.$order->order_code.'%');
                });

            if (Schema::hasTable('crm_product_catalog')) {
                $movementQuery->leftJoin('crm_product_catalog as product', 'product.id', '=', 'movement.product_id');
            }

            $stockMovements = $movementQuery
                ->select('movement.*', DB::raw('product.name as product_name'))
                ->latest('movement.id')
                ->limit(100)
                ->get();
        }

        $orderDocuments = Schema::hasTable('crm_order_documents')
            ? DB::table('crm_order_documents')->where('order_id', $order->id)->latest('id')->get()
            : collect();

        $documentTypes = [
            'payment_request' => 'ĐNTT',
            'purchase_contract' => 'Hợp đồng mua bán',
            'agency_contract' => 'Hợp đồng đại lý',
            'deposit' => 'Phiếu đặt cọc',
            'quotation' => 'Báo giá',
            'invoice' => 'Hóa đơn / UNC',
            'delivery' => 'Vận chuyển / Biên bản giao nhận',
            'warranty' => 'Bảo hành',
            'return' => 'Biên bản hoàn trả',
            'refund' => 'Chứng từ hoàn tiền',
            'other' => 'Khác',
        ];

        return view('orders.show', [
            'order' => $order,
            'timeline' => $this->orderService->getOrderTimeline($id),
            'notifications' => $this->orderService->getOrderNotifications($id),
            'paymentMethods' => PaymentMethod::where('is_active', true)->get(),
            'editHistories' => OrderEditHistory::where('order_id', $order->id)->latest()->get(),
            'currentApprovalLevel' => $this->orderService->getCurrentApprovalLevel($order),
            'returnAvailable' => $returnAvailable,
            'returnSerialsByItem' => $returnSerialsByItem,
            'returnWarehouses' => $returnWarehouses,
            'stockAllocations' => $stockAllocations,
            'orderSerials' => $orderSerials,
            'stockMovements' => $stockMovements,
            'orderDocuments' => $orderDocuments,
            'documentTypes' => $documentTypes,
        ]);
    }

    /**
     * Form chỉnh sửa đơn hàng.
     */
    public function edit(string $id): View
    {
        $order = $this->orderService->findWithDetails($id);

        $this->authorize('update', $order);

        $selectedCustomer = $this->resolveSelectedCustomer($order);

        return view('orders.edit', [
            'order' => $order,
            'selectedCustomer' => $selectedCustomer,
            'companies' => Company::orderBy('name')->get(),
            'warehouses' => $this->warehouseService->getActiveWarehouses(),
            'paymentMethods' => PaymentMethod::where('is_active', true)->get(),
            'priceTiers' => PriceTier::where('is_active', true)->orderBy('priority')->orderBy('name')->get(),
            'customers' => $this->customerService->getCustomersForSelect(),
        ]);
    }

    /**
     * Cập nhật đơn hàng.
     */
    public function update(OrderRequest $request, string $id): RedirectResponse
    {
        $order = $this->orderService->findWithDetails($id);

        $this->authorize('update', $order);

        try {
            $data = $request->validatedWithProcessing();

            $data['invoice_company_name'] = $request->input('invoice_company_name');
            $data['invoice_tax_code'] = $request->input('invoice_tax_code');
            $data['invoice_address'] = $request->input('invoice_address');
            $data['invoice_email'] = $request->input('invoice_email');

            if (empty($data['lead_id']) && ! empty($order->lead_id)) {
                $data['lead_id'] = $order->lead_id;
            }

            $oldSnapshot = $this->snapshotOrderData($order);

            $this->orderService->updateOrder($id, $data);

            $this->egoSyncOrderItemsAndTotalFromRequest($request, (int) $id);

            $freshOrder = $this->orderService->findWithDetails($id);
            $newSnapshot = $this->snapshotOrderData($freshOrder);

            $this->recordEditHistory($order, $oldSnapshot, $newSnapshot);

            return redirect('/orders/'.$order->id)
                ->with('success', 'Đơn hàng đã được cập nhật');
        } catch (\Throwable $e) {
            return back()
                ->withInput()
                ->with('error', 'Có lỗi xảy ra: '.$e->getMessage());
        }
    }

    /**
     * Xóa đơn hàng vĩnh viễn.
     */
    public function destroy(string $id): RedirectResponse
    {
        $order = $this->orderService->find($id);

        $this->authorize('delete', $order);

        try {
            $this->orderService->deleteOrder($id);

            return redirect()
                ->route('orders.index')
                ->with('success', 'Đơn hàng đã được xóa vĩnh viễn');
        } catch (\Throwable $e) {
            return back()->with('error', 'Không thể xóa đơn: '.$e->getMessage());
        }
    }

    /**
     * Hủy đơn hàng.
     */
    public function cancelOrder(string $id): RedirectResponse
    {
        $order = $this->orderService->find($id);

        $this->authorize('update', $order);

        try {
            $this->orderService->cancelOrder($id);

            return redirect()->back()->with('success', 'Đơn hàng đã được hủy.');
        } catch (\Throwable $e) {
            $this->logError($e, 'cancelOrder');

            return redirect()->back()->with('error', $this->getUserFriendlyMessage($e));
        }
    }

    // =========================================================================
    // APPROVAL WORKFLOW
    // =========================================================================

    /**
     * Gửi đơn đi duyệt.
     */
    public function submitForApproval(string $id): RedirectResponse
    {
        $order = $this->orderService->find($id);

        $this->authorize('update', $order);

        try {
            $this->orderService->submitForApproval($id);

            return redirect()->back()->with('success', 'Đã gửi duyệt đơn hàng.');
        } catch (\Throwable $e) {
            $this->logError($e, 'submitForApproval');

            return redirect()->back()->with('error', $this->getUserFriendlyMessage($e));
        }
    }

    /**
     * Form duyệt đơn hàng.
     */
    public function approvalForm(string $id): View
    {
        $order = $this->orderService->findWithDetails($id);

        $this->authorize('approve', $order);

        return view('orders.approval-form', [
            'order' => $order,
            'currentLevel' => $this->orderService->getCurrentApprovalLevel($order),
        ]);
    }

    /**
     * Xử lý duyệt/từ chối đơn hàng.
     */
    public function processApproval(Request $request, string $id): RedirectResponse
    {
        $order = $this->orderService->find($id);
        $action = $request->input('action', 'approve');

        $this->authorize($action === 'reject' ? 'reject' : 'approve', $order);

        try {
            $data = $this->buildApprovalData($request, $action);

            $department = strtolower(trim((string) ($order->current_department ?? '')));

            if ($action !== 'reject' && in_array($department, ['warehouse', 'kho'], true)) {
                DB::transaction(function () use ($id, $data) {
                    $this->stockGuard->assertOrderStockAvailable((int) $id, true);
                    $this->orderService->processApproval($id, $data);
                });
            } else {
                $this->orderService->processApproval($id, $data);
            }

            return redirect()
                ->route('orders.show', ['id' => $id, 'tab' => 'approval'])
                ->with('success', 'Đã xử lý duyệt đơn hàng.');
        } catch (\Throwable $e) {
            $this->logError($e, 'processApproval');

            return back()->with('error', $this->getUserFriendlyMessage($e));
        }
    }

    // =========================================================================
    // PAYMENT
    // =========================================================================

    /**
     * Ghi nhận thanh toán cho đơn hàng.
     */
    public function recordPayment(Request $request, string $id): RedirectResponse
    {
        $order = $this->orderService->find((int) $id);

        $this->authorize('recordPayment', $order);

        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01'],
            'method_id' => ['required', 'integer', 'exists:crm_payment_methods,id'],
            'payment_date' => ['required', 'date'],
            'note' => ['nullable', 'string', 'max:5000'],
        ]);

        try {
            $this->orderService->recordPayment($id, $data);

            return back()->with('success', 'Đã ghi nhận thanh toán.');
        } catch (\Throwable $e) {
            $this->logError($e, 'recordPayment');

            return back()->with('error', $this->getUserFriendlyMessage($e));
        }
    }

    /**
     * Cập nhật một giao dịch thanh toán của đơn hàng.
     */
    public function updatePayment(Request $request, string $paymentId): RedirectResponse
    {
        try {
            $payment = \App\Models\Payments\Payment::findOrFail($paymentId);

            $order = $this->orderService->find((int) $payment->order_id);

            $this->authorize('recordPayment', $order);

            $data = $request->validate([
                'amount' => ['required', 'numeric', 'min:0.01'],
                'method_id' => ['required', 'integer', 'exists:crm_payment_methods,id'],
                'payment_date' => ['required', 'date'],
                'note' => ['nullable', 'string', 'max:5000'],
            ]);

            $payment->update($data);

            return redirect()
                ->route('orders.show', ['id' => $payment->order_id, 'tab' => 'payments'])
                ->with('success', 'Cập nhật thanh toán thành công.');
        } catch (\Throwable $e) {
            $this->logError($e, 'updatePayment');

            return back()->with('error', $this->getUserFriendlyMessage($e));
        }
    }

    /**
     * Xóa một giao dịch thanh toán của đơn hàng.
     */
    public function destroyPayment(string $paymentId): RedirectResponse
    {
        try {
            $payment = \App\Models\Payments\Payment::findOrFail($paymentId);

            $order = $this->orderService->find((int) $payment->order_id);

            $this->authorize('recordPayment', $order);

            $payment->delete();

            return redirect()
                ->route('orders.show', ['id' => $payment->order_id, 'tab' => 'payments'])
                ->with('success', 'Đã xóa giao dịch thanh toán.');
        } catch (\Throwable $e) {
            $this->logError($e, 'destroyPayment');

            return back()->with('error', $this->getUserFriendlyMessage($e));
        }
    }

    /**
     * Lưu thông tin hóa đơn của đơn hàng và đồng bộ thông tin xuất hóa đơn về hồ sơ khách hàng.
     */
    public function updateInvoice(Request $request, string $order): RedirectResponse
    {
        $orderModel = $this->orderService->findWithDetails($order);

        $this->authorize('view', $orderModel);

        $data = $request->validate([
            'invoice_status' => ['nullable', 'in:none,pending,issued'],
            'invoice_company_name' => ['nullable', 'string', 'max:255'],
            'invoice_tax_code' => ['nullable', 'string', 'max:100'],
            'invoice_address' => ['nullable', 'string', 'max:2000'],
            'invoice_email' => ['nullable', 'email', 'max:255'],
            'invoice_file' => ['nullable', 'file', 'max:5120'],
        ]);

        try {
            DB::transaction(function () use ($request, $orderModel, $data) {
                $hasInvoiceInfo =
                    filled($data['invoice_company_name'] ?? null) ||
                    filled($data['invoice_tax_code'] ?? null) ||
                    filled($data['invoice_address'] ?? null) ||
                    filled($data['invoice_email'] ?? null) ||
                    $request->hasFile('invoice_file') ||
                    filled($orderModel->invoice_file ?? null);

                $invoiceStatus = $data['invoice_status'] ?? ($orderModel->invoice_status ?? 'none');

                if ($hasInvoiceInfo && $invoiceStatus === 'none') {
                    $invoiceStatus = 'pending';
                }

                if (! $hasInvoiceInfo) {
                    $invoiceStatus = 'none';
                }

                $updateData = [
                    'invoice_status' => $invoiceStatus,
                    'invoice_company_name' => $data['invoice_company_name'] ?: null,
                    'invoice_tax_code' => $data['invoice_tax_code'] ?: null,
                    'invoice_address' => $data['invoice_address'] ?: null,
                    'invoice_email' => $data['invoice_email'] ?: null,
                ];

                if ($request->hasFile('invoice_file')) {
                    $updateData['invoice_file'] = $request->file('invoice_file')->store('orders/invoices', 'public');
                }

                $orderModel->update($updateData);

                $customerId = 0;

                if (! empty($orderModel->lead_id)) {
                    $customerId = (int) DB::table('crm_leads')
                        ->where('id', $orderModel->lead_id)
                        ->value('customer_id');
                }

                if ($customerId > 0) {
                    DB::table('crm_customers')
                        ->where('id', $customerId)
                        ->update([
                            'billing_company_name' => $updateData['invoice_company_name'],
                            'billing_tax_code' => $updateData['invoice_tax_code'],
                            'billing_address' => $updateData['invoice_address'],
                            'billing_email' => $updateData['invoice_email'],
                        ]);
                }
            });

            return redirect()
                ->route('orders.show', ['id' => $orderModel->id, 'tab' => 'invoice'])
                ->with('success', 'Đã lưu thông tin hóa đơn.');
        } catch (\Throwable $e) {
            $this->logError($e, 'updateInvoice');

            return back()
                ->withInput()
                ->with('error', $this->getUserFriendlyMessage($e));
        }
    }

    // =========================================================================
    // SHIPPING & WAREHOUSE
    // =========================================================================

    /**
     * Lưu thông tin giao hàng (đơn vị vận chuyển, mã vận đơn, người nhận, phí ship...) của đơn hàng.
     */
    public function updateShippingInfo(Request $request, string $id): RedirectResponse
    {
        $order = $this->orderService->find((int) $id);

        $this->authorize('updateShippingInfo', $order);

        $data = $request->validate([
            'shipping_carrier' => ['nullable', 'string', 'max:255'],
            'tracking_number' => ['nullable', 'string', 'max:255'],
            'receiver_name' => ['nullable', 'string', 'max:255'],
            'receiver_phone' => ['nullable', 'string', 'max:50'],
            'estimated_delivery' => ['nullable', 'date'],
            'shipping_address' => ['nullable', 'string', 'max:2000'],
            'shipping_note' => ['nullable', 'string', 'max:5000'],
            'shipping_fee_warehouse_to_station' => ['nullable', 'numeric', 'min:0'],
            'shipping_fee_station_to_customer' => ['nullable', 'numeric', 'min:0'],
            'shipping_fee_payer' => ['nullable', 'in:company,customer'],
        ]);

        try {
            DB::transaction(function () use ($order, $data) {
                $updateData = array_intersect_key($data, $order->getAttributes());

                $hasShippingInput =
                    ! empty($data['shipping_carrier']) ||
                    ! empty($data['tracking_number']) ||
                    ! empty($data['receiver_name']) ||
                    ! empty($data['receiver_phone']) ||
                    ! empty($data['estimated_delivery']) ||
                    ! empty($data['shipping_address']) ||
                    ! empty($data['shipping_note']) ||
            ! empty($data['shipping_fee_warehouse_to_station']) ||
            ! empty($data['shipping_fee_station_to_customer']);

                if ($hasShippingInput && ($order->shipping_status ?? null) !== 'shipped') {
                    $updateData['shipping_status'] = 'ready';
                }

                $order->forceFill($updateData)->save();
            });

            return redirect()->back()->with('success', 'Đã lưu thông tin giao hàng.');
        } catch (\Throwable $e) {
            $this->logError($e, 'updateShippingInfo');

            return redirect()->back()->with('error', $this->getUserFriendlyMessage($e));
        }
    }

    /**
     * Đánh dấu đơn hàng đã vận chuyển (chỉ khi đơn ở bước Kho hoặc Hoàn tất).
     */
    public function markShipped(Request $request, string $id): RedirectResponse
    {
        $order = $this->orderService->find((int) $id);

        $this->authorize('markShipped', $order);

        if (! in_array($order->current_department, ['warehouse', 'completed'], true)) {
            return redirect()->back()->with('error', 'Chỉ có thể đánh dấu vận chuyển khi đơn đã ở bước Kho hoặc Hoàn tất.');
        }

        try {
            $order->update([
                'is_shipped' => true,
                'shipped_at' => now(),
                'shipping_status' => 'shipped',
            ]);

            return redirect()->back()->with('success', 'Đơn hàng đã được đánh dấu là đã vận chuyển.');
        } catch (\Throwable $e) {
            $this->logError($e, 'markShipped');

            return redirect()->back()->with('error', $this->getUserFriendlyMessage($e));
        }
    }

    /**
     * Duyệt xuất kho: kiểm tra tồn, xác thực serial đã chọn, trừ kho và kích hoạt bảo hành serial.
     */
    public function approveWarehouseIssue(Request $request, string $id): RedirectResponse
    {
        $order = $this->orderService->find((int) $id);

        $this->authorize('warehouseIssue', $order);

        $data = $request->validate([
            'actual_ship_date' => ['required', 'date'],
            'shipping_note' => ['nullable', 'string', 'max:5000'],
            'serials' => ['nullable', 'array'],
            'warranty_months' => ['nullable', 'integer', 'min:1', 'max:240'],
        ]);

        try {
            DB::transaction(function () use ($id, $data) {
                $this->stockGuard->assertOrderStockAvailable((int) $id, true);

                $this->serialWarrantyService->validateSerialSelection((int) $id, $data['serials'] ?? []);

                $this->orderService->shipOrder((int) $id, [
                    'shipping_note' => $data['shipping_note'] ?? null,
                    'serials' => $data['serials'] ?? [],
                ]);

                $this->serialWarrantyService->activateWarrantyForOrder(
                    (int) $id,
                    $data['serials'] ?? [],
                    $data['actual_ship_date'] ?? now()->toDateString(),
                    (int) ($data['warranty_months'] ?? 60),
                    $data['shipping_note'] ?? null
                );
            });

            return back()->with('success', 'Đã duyệt xuất kho, trừ tồn kho và hoàn tất đơn hàng.');
        } catch (\Throwable $e) {
            Log::error('Warehouse issue failed', [
                'order_id' => $id,
                'error' => $e->getMessage(),
            ]);

            $message = $e->getMessage();

            return back()
                ->with('error', $message)
                ->with('stock_error_popup', $message);
        }
    }

    /**
     * API JSON trả về danh sách serial khả dụng theo từng dòng hàng của đơn.
     */
    public function getShipSerials(string $id): JsonResponse
    {
        return response()->json([
            'ok' => true,
            'serials' => [
                'items' => $this->serialWarrantyService->getSerialPayload((int) $id),
            ],
        ]);
    }

    // =========================================================================
    // PDF
    // =========================================================================

    /**
     * Xuất file PDF đơn hàng và stream về trình duyệt.
     */
    public function pdf(Order $order)
    {
        $this->authorize('view', $order);

        return $this->generateOrderPdf($order)
            ->stream('DONHANG_'.$order->order_code.'.pdf');
    }

    /**
     * Xem trước PDF đơn hàng (stream trực tiếp, không tải xuống).
     */
    public function pdfPreview(Order $order)
    {
        $this->authorize('view', $order);

        return $this->generateOrderPdf($order)
            ->stream('DONHANG_'.$order->order_code.'.pdf');
    }

    // =========================================================================
    // API METHODS
    // =========================================================================

    /**
     * API JSON danh sách kho theo company cho form đơn hàng.
     */
    public function getWarehousesByCompany(Request $request): JsonResponse
    {
        $warehouses = $this->productPicker->warehousesByCompany((int) $request->input('company_id'));

        return response()->json(['warehouses' => $warehouses]);
    }

    /**
     * API JSON tìm kiếm khách hàng cho ô chọn khách trên form đơn hàng.
     */
    public function searchCustomers(Request $request): JsonResponse
    {
        $term = trim((string) $request->input('q', ''));
        $limit = min((int) $request->input('limit', 30), 50);

        $options = $this->customerService->searchCustomersForOrderSelect($term, $limit);

        return response()->json(['options' => $options]);
    }

    /**
     * API JSON catalog sản phẩm cho ô chọn sản phẩm trên form đơn hàng.
     */
    public function getProductCatalog(Request $request): JsonResponse
    {
        $this->authorize('create', Order::class);

        $products = $this->productPicker->productCatalog(
            trim((string) $request->input('q', '')),
            (int) $request->input('id', 0),
            max(1, min((int) $request->input('limit', 60), 100)),
        );

        return response()->json(['products' => $products]);
    }

    /**
     * API JSON danh sách kho khả dụng (kèm tồn) cho 1 sản phẩm đã chọn.
     */
    public function getProductWarehouses(Request $request, int $productId): JsonResponse
    {
        $this->authorize('create', Order::class);

        $payload = $this->productPicker->warehousesForProduct(
            $productId,
            (int) $request->input('company_id', 0),
        );

        if ($payload === null) {
            return response()->json(['warehouses' => []], 404);
        }

        return response()->json($payload);
    }

    /**
     * API JSON lấy danh sách sản phẩm theo kho (bao gồm cả sản phẩm tồn 0) cho form tạo đơn.
     */
    public function getProductsByWarehouse(Request $request): JsonResponse
    {
        $warehouseId = (int) $request->input('warehouse_id');

        if (! $warehouseId) {
            return response()->json(['products' => []]);
        }

        return response()->json([
            // Sales vẫn nhìn thấy cả sản phẩm tồn 0 để tạo đơn và yêu cầu điều hàng.
            // Việc chặn âm kho được xử lý riêng tại bước kho duyệt/xuất kho.
            'products' => $this->productPicker->productsForWarehouseSelect(
                $warehouseId,
                trim((string) $request->input('q', $request->input('term', ''))),
                max(50, min((int) $request->input('limit', 1000), 2000)),
            ),
        ]);
    }

    /**
     * API JSON trả về thông tin khách hàng, 5 đơn gần nhất và tổng công nợ; sales chỉ xem được khách của mình.
     */
    public function getCustomerInfo(int $customerId): JsonResponse
    {
        try {
            $user = auth()->user();
            $customer = $this->customerService->find($customerId);

            if ($user && $user->hasRole('sales') && $customer->owner_id != $user->id) {
                return response()->json(['error' => 'Unauthorized'], 403);
            }

            $customer = $this->customerService->findWithDetails($customerId);

            return response()->json([
                'customer' => $customer,
                'latest_orders' => $customer->orders()->latest()->take(5)->get(),
                'total_debt' => $customer->debts()->sum('total_amount') - $customer->debts()->sum('paid_amount'),
            ]);
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * API JSON tính giá bán (trước/sau VAT) của sản phẩm theo bảng giá và loại khách hàng.
     */
    public function getProductPrice(Request $request, int $productId): JsonResponse
    {
        return response()->json($this->productPicker->productPrice(
            $productId,
            $request->integer('price_tier_id') ?: null,
            $request->integer('customer_type_id') ?: null,
        ));
    }

    /**
     * Xuất Excel danh sách đơn hàng (dựng file trong OrderExcelExporter).
     */
    public function exportExcel(Request $request): StreamedResponse
    {
        return $this->orderExcelExporter->download($request->all(), Auth::user());
    }

    // =========================================================================
    // PRIVATE HELPERS
    // =========================================================================

    /**
     * Xác định lead_id hợp lệ theo customer_id; tự tạo lead mới nếu khách chưa có lead.
     */
    private function resolveLeadId(array $data): array
    {
        $customerId = (int) ($data['customer_id'] ?? 0);
        $leadId = (int) ($data['lead_id'] ?? 0);

        if ($customerId <= 0) {
            return $data;
        }

        if ($leadId > 0) {
            $leadCustomer = (int) DB::table('crm_leads')
                ->where('id', $leadId)
                ->value('customer_id');

            if ($leadCustomer !== $customerId) {
                $leadId = 0;
            }
        }

        if ($leadId <= 0) {
            $leadId = (int) DB::table('crm_leads')
                ->where('customer_id', $customerId)
                ->orderByDesc('created_at')
                ->value('id');
        }

        if ($leadId <= 0) {
            $leadId = (int) DB::table('crm_leads')->insertGetId([
                'customer_id' => $customerId,
                'assigned_to' => Auth::id(),
                'contact_date' => now(),
                'status_id' => 1,
                'note' => 'Auto tạo lead từ tạo đơn hàng',
                'created_by' => Auth::id(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $data['lead_id'] = $leadId;

        return $data;
    }

    /**
     * Gửi thông báo "đơn hàng mới" tới các user có vai trò admin/kế toán/kho.
     */
    private function dispatchOrderCreatedNotifications(Order $order): void
    {
        try {
            $actor = Auth::user();
            $actorName = $actor?->name ?? 'Sales';
            $title = 'Đơn hàng mới cần xử lý';
            $message = "{$actorName} đã tạo đơn hàng mới #{$order->order_code}";

            $recipientIds = User::query()
                ->whereHas('roles', fn ($q) => $q->whereIn('name', ['admin', 'accounting', 'warehouse']))
                ->pluck('id')
                ->reject(fn ($id) => (int) $id === (int) $actor?->id);

            foreach ($recipientIds as $uid) {
                OrderNotification::create([
                    'order_id' => $order->id,
                    'user_id' => $uid,
                    'type' => 'department_notification',
                    'title' => $title,
                    'message' => $message,
                    'is_read' => false,
                ]);
            }
        } catch (\Throwable $e) {
            Log::warning('Create notification failed: '.$e->getMessage());
        }
    }

    /**
     * Lấy option khách hàng đang gắn với đơn (qua customer_id hoặc lead) để hiển thị trên form sửa đơn.
     */
    private function resolveSelectedCustomer(Order $order): ?array
    {
        $customerId = (int) ($order->customer_id ?? 0);

        if ($customerId <= 0 && ! empty($order->lead_id)) {
            $customerId = (int) DB::table('crm_leads')
                ->where('id', (int) $order->lead_id)
                ->value('customer_id');
        }

        if ($customerId <= 0) {
            return null;
        }

        $selectedCustomer = $this->customerService->getCustomerOptionForOrderSelect($customerId);

        if (is_array($selectedCustomer)) {
            $selectedCustomer['lead_id'] = (int) ($order->lead_id ?? ($selectedCustomer['lead_id'] ?? 0));
        }

        return $selectedCustomer;
    }

    /**
     * Chụp snapshot dữ liệu đơn hàng (thông tin chính và các dòng hàng) để so sánh khi ghi lịch sử chỉnh sửa.
     */
    private function snapshotOrderData(Order $order): array
    {
        return [
            'lead_id' => $order->lead_id,
            'order_date' => optional($order->order_date)->format('Y-m-d'),
            'warehouse_id' => $order->warehouse_id,
            'price_tier_id' => $order->price_tier_id ?? null,
            'note' => $order->note ?? null,
            'total_amount' => (float) (
                ($order->total_amount ?? 0)
                - (
                    (($order->shipping_fee_payer ?? 'company') === 'company')
                        ? ((float) ($order->shipping_fee_warehouse_to_station ?? 0) + (float) ($order->shipping_fee_station_to_customer ?? 0))
                        : 0
                )
            ),
            'current_department' => $order->current_department,
            'items' => collect($order->items ?? [])->map(function ($item) {
                $qty = (int) ($item->quantity ?? 0);
                $price = (float) ($item->unit_price ?? 0);
                $discPct = (float) ($item->discount_percent ?? 0);
                $discAmt = (float) ($item->discount_amount ?? 0);
                $subtotal = $qty * $price;
                $discount = $discAmt > 0 ? ($discAmt * $qty) : ($subtotal * $discPct / 100);

                return [
                    'product_id' => $item->product_id,
                    'warehouse_id' => $item->warehouse_id,
                    'price_tier_id' => $item->price_tier_id,
                    'quantity' => $qty,
                    'unit_price' => $price,
                    'discount_percent' => $discPct,
                    'discount_amount' => $discAmt,
                    'line_total' => max(0, $subtotal - $discount),
                ];
            })->values()->toArray(),
        ];
    }

    /**
     * So sánh snapshot cũ/mới và ghi lịch sử chỉnh sửa đơn hàng nếu có thay đổi.
     */
    private function recordEditHistory(Order $order, array $oldSnapshot, array $newSnapshot): void
    {
        $changes = [];

        foreach ($oldSnapshot as $field => $oldValue) {
            $newValue = $newSnapshot[$field] ?? null;

            if ($field === 'items') {
                if ($oldValue != $newValue) {
                    $changes[$field] = ['old' => $oldValue, 'new' => $newValue];
                }

                continue;
            }

            if ((string) $oldValue !== (string) $newValue) {
                $changes[$field] = ['old' => $oldValue, 'new' => $newValue];
            }
        }

        if (empty($changes)) {
            return;
        }

        $user = auth()->user();

        OrderEditHistory::create([
            'order_id' => $order->id,
            'user_id' => $user?->id,
            'user_name' => $user?->name,
            'user_role' => method_exists($user, 'getRoleNames') ? $user->getRoleNames()->implode(', ') : null,
            'changes' => $changes,
            'note' => 'Chỉnh sửa đơn hàng',
        ]);
    }

    /**
     * Chuẩn bị dữ liệu duyệt/từ chối đơn theo hành động và vai trò người duyệt (kế toán có thêm thông tin công nợ).
     */
    private function buildApprovalData(Request $request, string $action): array
    {
        $data = [
            'action' => $action,
            'note' => $request->input('note'),
        ];

        if ($action === 'reject') {
            $data['rejection_reason'] = $request->input('rejection_reason');
        }

        if (auth()->user()->hasRole('accounting')) {
            $data['debt_checked'] = (bool) $request->input('debt_checked');
            $data['debt_note'] = $request->input('debt_note');
            $data['is_paid'] = (bool) $request->input('is_paid');
        }

        return $data;
    }

    /**
     * Dựng đối tượng PDF đơn hàng (DomPDF, khổ A4 dọc) với đầy đủ quan hệ liên quan.
     */
    private function generateOrderPdf(Order $order)
    {
        $order->load([
            'lead.customer.customerType',
            'lead.customer.region',
            'company',
            'warehouse.company',
            'items.product',
            'items.warehouse.company',
            'payments.method',
            'payments.recordedBy',
            'creator',
            'approver',
            'currentStatusType',
        ]);

        $paid = $order->payments->sum('amount');
        $remain = $order->total_amount - $paid;

        $company = $order->company
            ?? $order->warehouse?->company
            ?? $order->items->first()?->warehouse?->company
            ?? Company::where('code', 'EGO_INT')->first()
            ?? Company::first();

        return Pdf::loadView('orders.pdf', compact('order', 'paid', 'remain', 'company'))
            ->setPaper('a4', 'portrait');
    }

    /* EGO_ORDER_TOTAL_SYNC_START */
    /**
     * Chuyển chuỗi tiền tệ định dạng Việt Nam (vd: "1.234.567 đ") về số float.
     */
    private function egoParseMoney($value): float
    {
        if (is_int($value) || is_float($value)) {
            return (float) $value;
        }

        $value = trim((string) $value);

        if ($value === '') {
            return 0.0;
        }

        $value = str_replace(["\xc2\xa0", ' ', 'VND', 'vnd', 'VNĐ', 'vnđ', 'đ', 'd'], '', $value);
        $value = preg_replace('/[^0-9,\.\-]/', '', $value);

        if ($value === '' || $value === '-') {
            return 0.0;
        }

        $hasComma = strpos($value, ',') !== false;
        $hasDot = strpos($value, '.') !== false;

        if ($hasComma && $hasDot) {
            if (strrpos($value, ',') > strrpos($value, '.')) {
                $value = str_replace('.', '', $value);
                $value = str_replace(',', '.', $value);
            } else {
                $value = str_replace(',', '', $value);
            }
        } elseif ($hasComma) {
            if (preg_match('/,\d{3}$/', $value)) {
                $value = str_replace(',', '', $value);
            } else {
                $value = str_replace(',', '.', $value);
            }
        } elseif ($hasDot) {
            if (preg_match('/\.\d{3}(\.\d{3})*$/', $value)) {
                $value = str_replace('.', '', $value);
            }
        }

        return is_numeric($value) ? (float) $value : 0.0;
    }

    /**
     * Đồng bộ các dòng hàng và tổng tiền của đơn từ dữ liệu request (tự resolve VAT, quy đổi giá trước/sau VAT).
     */
    private function egoSyncOrderItemsAndTotalFromRequest(Request $request, int $orderId): void
    {
        $items = $request->input('items', []);

        if (! is_array($items) || count($items) === 0) {
            return;
        }

        $schema = \Illuminate\Support\Facades\Schema::class;

        $orderTable = $schema::hasTable('crm_orders') ? 'crm_orders' : ($schema::hasTable('orders') ? 'orders' : null);
        $itemTable = $schema::hasTable('crm_order_items') ? 'crm_order_items' : ($schema::hasTable('order_items') ? 'order_items' : null);

        if (! $orderTable || ! $itemTable) {
            return;
        }

        $currentItems = DB::table($itemTable)
            ->where('order_id', $orderId)
            ->get()
            ->keyBy('id');

        $usedItemIds = [];
        $totalAmount = 0.0;
        $totalDiscount = 0.0;
        $totalTaxAmount = 0.0;
        $productVatCache = [];

        foreach ($items as $row) {
            if (! is_array($row)) {
                continue;
            }

            $itemId = (int) ($row['id'] ?? 0);
            $warehouseId = (int) ($row['warehouse_id'] ?? 0);
            $productId = (int) ($row['product_id'] ?? 0);
            $priceTierId = ! empty($row['price_tier_id']) ? (int) $row['price_tier_id'] : null;

            $quantity = (int) $this->egoParseMoney($row['quantity'] ?? 1);
            if ($quantity < 1) {
                $quantity = 1;
            }

            $unitPrice = $this->egoParseMoney($row['unit_price'] ?? 0);
            $discountPercent = $this->egoParseMoney($row['discount_percent'] ?? 0);
            $discountAmount = $this->egoParseMoney($row['discount_amount'] ?? 0);

            if ($discountPercent < 0) {
                $discountPercent = 0;
            }

            if ($discountPercent > 100) {
                $discountPercent = 100;
            }

            if ($discountAmount < 0) {
                $discountAmount = 0;
            }

            $vatPercent = $this->egoParseMoney($row['vat_percent'] ?? 0);

            if ($vatPercent <= 0 && $productId > 0) {
                $cacheKey = $productId.':'.(int) ($priceTierId ?? 0);

                if (! array_key_exists($cacheKey, $productVatCache)) {
                    $productVatCache[$cacheKey] = $this->pricingService->resolveVatPercentForOrderItem($productId, $priceTierId);
                }

                $vatPercent = (float) ($productVatCache[$cacheKey] ?? 0);

                if ($vatPercent <= 0 && $productId > 0 && $schema::hasTable('crm_product_prices')) {
                    $vatPercent = (float) DB::table('crm_product_prices')
                        ->where('product_id', $productId)
                        ->where('vat_percent', '>', 0)
                        ->orderBy('price_tier_id')
                        ->value('vat_percent');
                }
            }

            if ($vatPercent < 0) {
                $vatPercent = 0;
            }

            if ($vatPercent > 100) {
                $vatPercent = 100;
            }

            // EGO FIX: Khi tạo đơn, form có thể gửi giá TRƯỚC VAT từ bảng giá đại lý.
            // Bảng crm_order_items.unit_price đang được màn chi tiết đơn hiểu là giá SAU VAT.
            // Vì vậy nếu unit_price khớp giá trước VAT trong crm_product_prices thì đổi sang giá sau VAT.
            if ($productId > 0 && $priceTierId && $schema::hasTable('crm_product_prices')) {
                $priceColsForVatFix = $schema::getColumnListing('crm_product_prices');

                if (
                    in_array('product_id', $priceColsForVatFix, true)
                    && in_array('price_tier_id', $priceColsForVatFix, true)
                    && in_array('price', $priceColsForVatFix, true)
                ) {
                    $tierPriceRowForVatFix = DB::table('crm_product_prices')
                        ->where('product_id', $productId)
                        ->where('price_tier_id', $priceTierId)
                        ->first();

                    if ($tierPriceRowForVatFix) {
                        $tierPriceBeforeVatForVatFix = (float) ($tierPriceRowForVatFix->price ?? 0);

                        $tierVatForVatFix = 0.0;
                        foreach (['vat_percent', 'vat', 'tax_percent'] as $vatColumnForVatFix) {
                            if (in_array($vatColumnForVatFix, $priceColsForVatFix, true)) {
                                $tierVatForVatFix = (float) ($tierPriceRowForVatFix->{$vatColumnForVatFix} ?? 0);
                                if ($tierVatForVatFix > 0) {
                                    break;
                                }
                            }
                        }

                        if ($vatPercent <= 0 && $tierVatForVatFix > 0) {
                            $vatPercent = min(100, max(0, $tierVatForVatFix));
                        }

                        if ($tierPriceBeforeVatForVatFix > 0 && $vatPercent > 0) {
                            $tierPriceAfterVatForVatFix = round($tierPriceBeforeVatForVatFix * (1 + $vatPercent / 100), 0);

                            // Nếu form gửi đúng giá trước VAT, đổi sang giá sau VAT để lưu đơn.
                            if (abs($unitPrice - $tierPriceBeforeVatForVatFix) <= 1) {
                                $unitPrice = $tierPriceAfterVatForVatFix;
                            }
                        }
                    }
                }
            }

            // EGO FINAL FIX CREATE ORDER PRICE/VAT:
            // Form có thể gửi unit_price là giá TRƯỚC VAT từ crm_product_prices.
            // Màn chi tiết đơn hàng đang hiểu crm_order_items.unit_price là giá SAU VAT.
            // Vì vậy trước khi tính line_total/lưu DB, nếu unit_price khớp giá trước VAT
            // thì đổi sang giá sau VAT và set VAT theo tầng giá.
            if ($productId > 0 && $priceTierId && $schema::hasTable('crm_product_prices')) {
                $priceColsForEgoVatFix = $schema::getColumnListing('crm_product_prices');

                if (
                    in_array('product_id', $priceColsForEgoVatFix, true)
                    && in_array('price_tier_id', $priceColsForEgoVatFix, true)
                    && in_array('price', $priceColsForEgoVatFix, true)
                ) {
                    $tierPriceRowForEgoVatFix = DB::table('crm_product_prices')
                        ->where('product_id', $productId)
                        ->where('price_tier_id', $priceTierId)
                        ->first();

                    if ($tierPriceRowForEgoVatFix) {
                        $tierPriceBeforeVatForEgoVatFix = (float) ($tierPriceRowForEgoVatFix->price ?? 0);

                        $tierVatForEgoVatFix = 0.0;
                        foreach (['vat_percent', 'vat', 'tax_percent'] as $egoVatColumn) {
                            if (in_array($egoVatColumn, $priceColsForEgoVatFix, true)) {
                                $tierVatForEgoVatFix = (float) ($tierPriceRowForEgoVatFix->{$egoVatColumn} ?? 0);
                                if ($tierVatForEgoVatFix > 0) {
                                    break;
                                }
                            }
                        }

                        if ($vatPercent <= 0 && $tierVatForEgoVatFix > 0) {
                            $vatPercent = min(100, max(0, $tierVatForEgoVatFix));
                        }

                        if ($tierPriceBeforeVatForEgoVatFix > 0 && $vatPercent > 0) {
                            $tierPriceAfterVatForEgoVatFix = round($tierPriceBeforeVatForEgoVatFix * (1 + $vatPercent / 100), 0);

                            // Nếu form gửi 18.000.000 cho Đại lý 3, đổi thành 19.440.000.
                            if (abs($unitPrice - $tierPriceBeforeVatForEgoVatFix) <= 1) {
                                $unitPrice = $tierPriceAfterVatForEgoVatFix;
                            }
                        }
                    }
                }
            }

            $lineSubtotal = $unitPrice * $quantity;
            $lineDiscount = ($lineSubtotal * $discountPercent / 100) + $discountAmount;
            if ($lineDiscount > $lineSubtotal) {
                $lineDiscount = $lineSubtotal;
            }

            $lineTotal = max(0, $lineSubtotal - $lineDiscount);

            if ($vatPercent > 0 && $lineTotal > 0) {
                $lineBeforeVat = $lineTotal / (1 + ($vatPercent / 100));
                $totalTaxAmount += max(0, $lineTotal - $lineBeforeVat);
            }

            if ($unitPrice <= 0 && $lineTotal > 0 && $quantity > 0) {
                $unitPrice = round($lineTotal / $quantity, 2);
            }

            if ($productId <= 0 || $warehouseId <= 0 || $unitPrice <= 0) {
                continue;
            }

            $payload = [];

            $set = function (string $column, $value) use (&$payload, $itemTable, $schema) {
                if ($schema::hasColumn($itemTable, $column)) {
                    $payload[$column] = $value;
                }
            };

            $set('warehouse_id', $warehouseId);
            $set('product_id', $productId);
            $set('price_tier_id', $priceTierId);
            $set('quantity', $quantity);
            $set('unit_price', $unitPrice);
            $set('vat_percent', $vatPercent);
            $set('discount_percent', $discountPercent);
            $set('discount_amount', $discountAmount);

            if ($schema::hasColumn($itemTable, 'updated_at')) {
                $payload['updated_at'] = now();
            }

            if ($itemId > 0 && $currentItems->has($itemId)) {
                DB::table($itemTable)
                    ->where('id', $itemId)
                    ->where('order_id', $orderId)
                    ->update($payload);

                $usedItemIds[] = $itemId;
            } else {
                $matched = $currentItems->first(function ($it) use ($productId, $warehouseId, $usedItemIds) {
                    return ! in_array((int) $it->id, $usedItemIds, true)
                        && (int) ($it->product_id ?? 0) === $productId
                        && (int) ($it->warehouse_id ?? 0) === $warehouseId;
                });

                if ($matched) {
                    DB::table($itemTable)
                        ->where('id', $matched->id)
                        ->where('order_id', $orderId)
                        ->update($payload);

                    $usedItemIds[] = (int) $matched->id;
                } else {
                    $payload['order_id'] = $orderId;

                    if ($schema::hasColumn($itemTable, 'created_at')) {
                        $payload['created_at'] = now();
                    }

                    $newId = DB::table($itemTable)->insertGetId($payload);
                    $usedItemIds[] = (int) $newId;
                }
            }

            $totalAmount += $lineTotal;
            $totalDiscount += $lineDiscount;
        }

        if ($totalAmount <= 0) {
            return;
        }

        $orderPayload = [];

        if ($schema::hasColumn($orderTable, 'total_amount')) {
            $orderPayload['total_amount'] = $totalAmount;
        }

        if ($schema::hasColumn($orderTable, 'discount_amount')) {
            $orderPayload['discount_amount'] = $totalDiscount;
        }

        if ($schema::hasColumn($orderTable, 'tax_amount')) {
            $orderPayload['tax_amount'] = round($totalTaxAmount, 2);
        }

        if ($schema::hasColumn($orderTable, 'updated_at')) {
            $orderPayload['updated_at'] = now();
        }

        if (! empty($orderPayload)) {
            DB::table($orderTable)
                ->where('id', $orderId)
                ->update($orderPayload);
        }
    }
    /* EGO_ORDER_TOTAL_SYNC_END */

}
