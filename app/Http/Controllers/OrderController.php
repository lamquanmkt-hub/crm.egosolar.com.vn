<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\OrderRequest;
use App\Models\Core\Company;
use App\Models\CRM\Orders\Order;
use App\Models\CRM\Orders\OrderEditHistory;
use App\Models\CRM\Orders\OrderNotification;
use App\Models\Inventory\Pricing\PriceTier;
use App\Models\Payments\PaymentMethod;
use App\Models\User;
use App\Services\CustomerService;
use App\Services\OrderService;
use App\Services\PricingService;
use App\Services\ProductService;
use App\Services\WarehouseService;
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

class OrderController extends Controller
{
    use HandleException;

    public function __construct(
        protected OrderService $orderService,
        protected CustomerService $customerService,
        protected ProductService $productService,
        protected WarehouseService $warehouseService,
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
            'companies'        => Company::orderBy('name')->get(),
            'warehouses'       => $this->warehouseService->getActiveWarehouses(),
            'paymentMethods'   => PaymentMethod::where('is_active', true)->get(),
            'priceTiers'       => PriceTier::where('is_active', true)->orderBy('priority')->orderBy('name')->get(),
        ]);
    }

    /**
     * Lưu đơn hàng mới.
     */
    public function store(OrderRequest $request): RedirectResponse
    {
        $this->authorize('create', Order::class);

        try {
            $data  = $request->validatedWithProcessing();
            $data  = $this->resolveLeadId($data);
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

        return view('orders.show', [
            'order'          => $order,
            'timeline'       => $this->orderService->getOrderTimeline($id),
            'notifications'  => $this->orderService->getOrderNotifications($id),
            'paymentMethods' => PaymentMethod::where('is_active', true)->get(),
            'editHistories'  => OrderEditHistory::where('order_id', $order->id)->latest()->get(),
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
            'order'            => $order,
            'selectedCustomer' => $selectedCustomer,
            'companies'        => Company::orderBy('name')->get(),
            'warehouses'       => $this->warehouseService->getActiveWarehouses(),
            'paymentMethods'   => PaymentMethod::where('is_active', true)->get(),
            'priceTiers'       => PriceTier::where('is_active', true)->orderBy('priority')->orderBy('name')->get(),
            'customers'        => $this->customerService->getCustomersForSelect(),
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
            $data['invoice_tax_code']     = $request->input('invoice_tax_code');
            $data['invoice_address']      = $request->input('invoice_address');
            $data['invoice_email']        = $request->input('invoice_email');

            if (empty($data['lead_id']) && !empty($order->lead_id)) {
                $data['lead_id'] = $order->lead_id;
            }

            $oldSnapshot = $this->snapshotOrderData($order);

            $this->orderService->updateOrder($id, $data);

            $this->egoSyncOrderItemsAndTotalFromRequest($request, (int) $id);

            $freshOrder  = $this->orderService->findWithDetails($id);
            $newSnapshot = $this->snapshotOrderData($freshOrder);

            $this->recordEditHistory($order, $oldSnapshot, $newSnapshot);

            return redirect('/orders/' . $order->id)
                ->with('success', 'Đơn hàng đã được cập nhật');
        } catch (\Throwable $e) {
            return back()
                ->withInput()
                ->with('error', 'Có lỗi xảy ra: ' . $e->getMessage());
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
            return back()->with('error', 'Không thể xóa đơn: ' . $e->getMessage());
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
            'order'        => $order,
            'currentLevel' => $this->orderService->getCurrentApprovalLevel($order),
        ]);
    }

    /**
     * Xử lý duyệt/từ chối đơn hàng.
     */
    public function processApproval(Request $request, string $id): RedirectResponse
    {
        $order  = $this->orderService->find($id);
        $action = $request->input('action', 'approve');

        $this->authorize($action === 'reject' ? 'reject' : 'approve', $order);

        try {
            $data = $this->buildApprovalData($request, $action);

            $department = strtolower(trim((string) ($order->current_department ?? '')));

            if ($action !== 'reject' && in_array($department, ['warehouse', 'kho'], true)) {
                DB::transaction(function () use ($id, $data) {
                    $this->assertOrderStockAvailable((int) $id, true);
                    $this->orderService->processApproval($id, $data);
                });
            } else {
                $this->orderService->processApproval($id, $data);
            }

            return redirect()
                ->route('orders.show', $id)
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
            'amount'       => ['required', 'numeric', 'min:0.01'],
            'method_id'    => ['required', 'integer', 'exists:crm_payment_methods,id'],
            'payment_date' => ['required', 'date'],
            'note'         => ['nullable', 'string', 'max:5000'],
        ]);

        try {
            $this->orderService->recordPayment($id, $data);

            return back()->with('success', 'Đã ghi nhận thanh toán.');
        } catch (\Throwable $e) {
            $this->logError($e, 'recordPayment');

            return back()->with('error', $this->getUserFriendlyMessage($e));
        }
    }

    public function updatePayment(Request $request, string $paymentId): RedirectResponse
    {
        try {
            $payment = \App\Models\Payments\Payment::findOrFail($paymentId);

            $order = $this->orderService->find((int) $payment->order_id);

            $this->authorize('recordPayment', $order);

            $data = $request->validate([
                'amount'       => ['required', 'numeric', 'min:0.01'],
                'method_id'    => ['required', 'integer', 'exists:crm_payment_methods,id'],
                'payment_date' => ['required', 'date'],
                'note'         => ['nullable', 'string', 'max:5000'],
            ]);

            $payment->update($data);

            return redirect()
                ->route('orders.show', $payment->order_id)
                ->with('success', 'Cập nhật thanh toán thành công.');
        } catch (\Throwable $e) {
            $this->logError($e, 'updatePayment');

            return back()->with('error', $this->getUserFriendlyMessage($e));
        }
    }

    public function destroyPayment(string $paymentId): RedirectResponse
    {
        try {
            $payment = \App\Models\Payments\Payment::findOrFail($paymentId);

            $order = $this->orderService->find((int) $payment->order_id);

            $this->authorize('recordPayment', $order);

            $payment->delete();

            return redirect()
                ->route('orders.show', $payment->order_id)
                ->with('success', 'Đã xóa giao dịch thanh toán.');
        } catch (\Throwable $e) {
            $this->logError($e, 'destroyPayment');

            return back()->with('error', $this->getUserFriendlyMessage($e));
        }
    }

    public function updateInvoice(Request $request, string $order): RedirectResponse
    {
        $orderModel = $this->orderService->findWithDetails($order);

        $this->authorize('view', $orderModel);

        $data = $request->validate([
            'invoice_status'       => ['nullable', 'in:none,pending,issued'],
            'invoice_company_name' => ['nullable', 'string', 'max:255'],
            'invoice_tax_code'     => ['nullable', 'string', 'max:100'],
            'invoice_address'      => ['nullable', 'string', 'max:2000'],
            'invoice_email'        => ['nullable', 'email', 'max:255'],
            'invoice_file'         => ['nullable', 'file', 'max:5120'],
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

                if (!$hasInvoiceInfo) {
                    $invoiceStatus = 'none';
                }

                $updateData = [
                    'invoice_status'       => $invoiceStatus,
                    'invoice_company_name' => $data['invoice_company_name'] ?: null,
                    'invoice_tax_code'     => $data['invoice_tax_code'] ?: null,
                    'invoice_address'      => $data['invoice_address'] ?: null,
                    'invoice_email'        => $data['invoice_email'] ?: null,
                ];

                if ($request->hasFile('invoice_file')) {
                    $updateData['invoice_file'] = $request->file('invoice_file')->store('orders/invoices', 'public');
                }

                $orderModel->update($updateData);

                $customerId = 0;

                if (!empty($orderModel->lead_id)) {
                    $customerId = (int) DB::table('crm_leads')
                        ->where('id', $orderModel->lead_id)
                        ->value('customer_id');
                }

                if ($customerId > 0) {
                    DB::table('crm_customers')
                        ->where('id', $customerId)
                        ->update([
                            'billing_company_name' => $updateData['invoice_company_name'],
                            'billing_tax_code'     => $updateData['invoice_tax_code'],
                            'billing_address'      => $updateData['invoice_address'],
                            'billing_email'        => $updateData['invoice_email'],
                        ]);
                }
            });

            return redirect()
                ->route('orders.show', $orderModel->id)
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

    public function updateShippingInfo(Request $request, string $id): RedirectResponse
    {
        $order = $this->orderService->find((int) $id);

        $this->authorize('updateShippingInfo', $order);

        $data = $request->validate([
            'shipping_carrier' => ['nullable', 'string', 'max:255'],
            'tracking_number'  => ['nullable', 'string', 'max:255'],
            'receiver_name'    => ['nullable', 'string', 'max:255'],
            'receiver_phone'   => ['nullable', 'string', 'max:50'],
            'estimated_delivery' => ['nullable', 'date'],
            'shipping_address' => ['nullable', 'string', 'max:2000'],
            'shipping_note'    => ['nullable', 'string', 'max:5000'],
            'shipping_fee_warehouse_to_station' => ['nullable', 'numeric', 'min:0'],
            'shipping_fee_station_to_customer' => ['nullable', 'numeric', 'min:0'],
            'shipping_fee_payer' => ['nullable', 'in:company,customer'],
        ]);

        try {
            DB::transaction(function () use ($order, $data) {
                $updateData = array_intersect_key($data, $order->getAttributes());

                $hasShippingInput =
                    !empty($data['shipping_carrier']) ||
                    !empty($data['tracking_number']) ||
                    !empty($data['receiver_name']) ||
                    !empty($data['receiver_phone']) ||
                    !empty($data['estimated_delivery']) ||
                    !empty($data['shipping_address']) ||
                    !empty($data['shipping_note']) ||
            !empty($data['shipping_fee_warehouse_to_station']) ||
            !empty($data['shipping_fee_station_to_customer']);

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

    public function markShipped(Request $request, string $id): RedirectResponse
    {
        $order = $this->orderService->find((int) $id);

        $this->authorize('markShipped', $order);

        if (!in_array($order->current_department, ['warehouse', 'completed'], true)) {
            return redirect()->back()->with('error', 'Chỉ có thể đánh dấu vận chuyển khi đơn đã ở bước Kho hoặc Hoàn tất.');
        }

        try {
            $order->update([
                'is_shipped'      => true,
                'shipped_at'      => now(),
                'shipping_status' => 'shipped',
            ]);

            return redirect()->back()->with('success', 'Đơn hàng đã được đánh dấu là đã vận chuyển.');
        } catch (\Throwable $e) {
            $this->logError($e, 'markShipped');

            return redirect()->back()->with('error', $this->getUserFriendlyMessage($e));
        }
    }

    public function approveWarehouseIssue(Request $request, string $id): RedirectResponse
    {
        $order = $this->orderService->find((int) $id);

        $this->authorize('warehouseIssue', $order);

        $data = $request->validate([
            'actual_ship_date' => ['required', 'date'],
            'shipping_note'    => ['nullable', 'string', 'max:5000'],
            'serials'          => ['nullable', 'array'],
            'warranty_months'  => ['nullable', 'integer', 'min:1', 'max:240'],
        ]);

        try {
            DB::transaction(function () use ($id, $data) {
                $this->assertOrderStockAvailable((int) $id, true);

                $this->egoValidateOrderSerialSelection((int) $id, $data['serials'] ?? []);

                $this->orderService->shipOrder((int) $id, [
                    'shipping_note' => $data['shipping_note'] ?? null,
                    'serials'       => $data['serials'] ?? [],
                ]);

                $this->egoActivateSerialWarrantyForOrder(
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
                'error'    => $e->getMessage(),
            ]);

            $message = $e->getMessage();

            return back()
                ->with('error', $message)
                ->with('stock_error_popup', $message);
        }
    }

    public function getShipSerials(string $id): JsonResponse
    {
        return response()->json([
            'ok' => true,
            'serials' => [
                'items' => $this->egoGetOrderSerialPayload((int) $id),
            ],
        ]);
    }

    private function egoGetOrderSerialPayload(int $orderId): array
    {
        if (!Schema::hasTable('crm_order_items') || !Schema::hasTable('crm_serial_units')) {
            return [];
        }

        $order = DB::table('crm_orders')->where('id', $orderId)->first();

        $items = DB::table('crm_order_items as oi')
            ->leftJoin('crm_product_catalog as p', 'p.id', '=', 'oi.product_id')
            ->where('oi.order_id', $orderId)
            ->select(
                'oi.id as order_item_id',
                'oi.product_id',
                'oi.product_name',
                'oi.quantity',
                'oi.warehouse_id',
                'p.name as catalog_name',
                'p.sku',
                'p.is_serialized'
            )
            ->get();

        $payload = [];

        foreach ($items as $item) {
            $warehouseId = (int) ($item->warehouse_id ?: ($order->warehouse_id ?? 0));

            $serialQ = DB::table('crm_serial_units as su')
                ->join('crm_serial_unit_identifiers as sui', 'sui.serial_unit_id', '=', 'su.id')
                ->join('crm_serial_identifiers as si', 'si.id', '=', 'sui.serial_identifier_id')
                ->leftJoin('crm_serial_unit_states as st', 'st.serial_unit_id', '=', 'su.id')
                ->leftJoin('crm_warehouses as w', 'w.id', '=', 'st.warehouse_id')
                ->where('su.product_id', (int) $item->product_id)
                ->where('sui.is_primary', 1)
                ->where('st.state', 'in_stock');

            if ($warehouseId > 0) {
                $serialQ->where('st.warehouse_id', $warehouseId);
            }

            $available = $serialQ
                ->select('su.id', 'si.code', 'st.warehouse_id', 'w.name as warehouse_name')
                ->orderBy('si.code')
                ->get();

            if ((int) ($item->is_serialized ?? 0) !== 1 && $available->isEmpty()) {
                continue;
            }

            $payload[] = [
                'order_item_id' => (int) $item->order_item_id,
                'product_id' => (int) $item->product_id,
                'product_name' => $item->product_name ?: ($item->catalog_name ?: ('Sản phẩm #' . $item->product_id)),
                'sku' => $item->sku,
                'quantity' => (int) $item->quantity,
                'warehouse_id' => $warehouseId,
                'available_serials' => $available->map(fn ($r) => [
                    'id' => (int) $r->id,
                    'code' => $r->code,
                    'warehouse_id' => (int) $r->warehouse_id,
                    'warehouse_name' => $r->warehouse_name,
                ])->values()->all(),
            ];
        }

        return $payload;
    }

    private function egoValidateOrderSerialSelection(int $orderId, array $serials): void
    {
        $items = $this->egoGetOrderSerialPayload($orderId);

        foreach ($items as $item) {
            $selected = array_values(array_filter((array) ($serials[$item['order_item_id']] ?? [])));
            $need = (int) $item['quantity'];

            if (count($selected) !== $need) {
                throw new \Exception('Sản phẩm "' . $item['product_name'] . '" cần chọn đúng ' . $need . ' serial. Hiện đang chọn ' . count($selected) . '.');
            }

            $availableIds = collect($item['available_serials'])->pluck('id')->map(fn ($id) => (int) $id)->all();

            foreach ($selected as $sid) {
                if (!in_array((int) $sid, $availableIds, true)) {
                    throw new \Exception('Serial đã chọn không còn trong kho hoặc không đúng sản phẩm: #' . $sid);
                }
            }
        }
    }

    private function egoActivateSerialWarrantyForOrder(int $orderId, array $serials, string $shipDate, int $months, ?string $note = null): void
    {
        if (!Schema::hasTable('crm_serial_warranties')) {
            return;
        }

        $order = DB::table('crm_orders')->where('id', $orderId)->first();
        $customerId = $this->egoCustomerIdFromOrder($order);
        $start = \Carbon\Carbon::parse($shipDate)->startOfDay();

        foreach ($serials as $orderItemId => $unitIds) {
            $orderItem = DB::table('crm_order_items')->where('id', (int) $orderItemId)->first();

            foreach ((array) $unitIds as $unitId) {
                $unitId = (int) $unitId;

                if ($unitId <= 0) {
                    continue;
                }

                $code = DB::table('crm_serial_unit_identifiers as sui')
                    ->join('crm_serial_identifiers as si', 'si.id', '=', 'sui.serial_identifier_id')
                    ->where('sui.serial_unit_id', $unitId)
                    ->where('sui.is_primary', 1)
                    ->value('si.code');

                $oldState = DB::table('crm_serial_unit_states')->where('serial_unit_id', $unitId)->first();

                DB::table('crm_serial_unit_states')->updateOrInsert(
                    ['serial_unit_id' => $unitId],
                    [
                        'warehouse_id' => null,
                        'state' => 'sold',
                        'synced_at' => now(),
                    ]
                );

                if (Schema::hasColumn('crm_serial_units', 'warehouse_id')) {
                    DB::table('crm_serial_units')->where('id', $unitId)->update([
                        'warehouse_id' => null,
                        'updated_at' => now(),
                    ]);
                }

                if (Schema::hasTable('crm_order_item_serial_units')) {
                    DB::table('crm_order_item_serial_units')->updateOrInsert(
                        ['serial_unit_id' => $unitId],
                        [
                            'order_item_id' => (int) $orderItemId,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]
                    );
                }

                DB::table('crm_serial_warranties')->updateOrInsert(
                    ['serial_unit_id' => $unitId],
                    [
                        'customer_id' => $customerId ?: null,
                        'order_id' => $orderId,
                        'order_item_id' => (int) $orderItemId,
                        'sold_at' => $start->toDateString(),
                        'warranty_months' => $months,
                        'warranty_start_at' => $start->toDateString(),
                        'warranty_end_at' => $start->copy()->addMonths($months)->toDateString(),
                        'status' => 'active',
                        'note' => $note,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]
                );

                if (Schema::hasTable('crm_serial_warranty_events')) {
                    DB::table('crm_serial_warranty_events')->insert([
                        'serial_unit_id' => $unitId,
                        'serial_code' => $code,
                        'event_type' => 'issue',
                        'from_state' => $oldState->state ?? 'in_stock',
                        'to_state' => 'sold',
                        'from_warehouse_id' => $oldState->warehouse_id ?? null,
                        'to_warehouse_id' => null,
                        'customer_id' => $customerId ?: null,
                        'order_id' => $orderId,
                        'created_by' => auth()->id(),
                        'note' => $note,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }
        }
    }

    private function egoCustomerIdFromOrder($order): int
    {
        if (!$order) {
            return 0;
        }

        if (isset($order->customer_id) && (int) $order->customer_id > 0) {
            return (int) $order->customer_id;
        }

        if (!empty($order->lead_id) && Schema::hasTable('crm_leads')) {
            return (int) DB::table('crm_leads')->where('id', (int) $order->lead_id)->value('customer_id');
        }

        return 0;
    }

    // =========================================================================
    // PDF
    // =========================================================================

    public function pdf(Order $order)
    {
        $this->authorize('view', $order);

        return $this->generateOrderPdf($order)
            ->stream('DONHANG_' . $order->order_code . '.pdf');
    }

    public function pdfPreview(Order $order)
    {
        $this->authorize('view', $order);

        return $this->generateOrderPdf($order)
            ->stream('DONHANG_' . $order->order_code . '.pdf');
    }

    // =========================================================================
    // API METHODS
    // =========================================================================

    public function getWarehousesByCompany(Request $request): JsonResponse
    {
        $companyId = (int) $request->input('company_id');

        if (!$companyId) {
            return response()->json(['warehouses' => []]);
        }

        $warehouses = DB::table('crm_warehouses as w')
            ->join('company_warehouse as cw', 'cw.warehouse_id', '=', 'w.id')
            ->where('cw.company_id', $companyId)
            ->orderBy('w.name')
            ->select('w.id', 'w.name')
            ->get();

        return response()->json(['warehouses' => $warehouses]);
    }

    public function searchCustomers(Request $request): JsonResponse
    {
        $term  = trim((string) $request->input('q', ''));
        $limit = min((int) $request->input('limit', 30), 50);

        $options = $this->customerService->searchCustomersForOrderSelect($term, $limit);

        return response()->json(['options' => $options]);
    }

    public function getProductsByWarehouse(Request $request): JsonResponse
    {
        $warehouseId = (int) $request->input('warehouse_id');

        if (!$warehouseId) {
            return response()->json(['products' => []]);
        }

        return response()->json([
            // Sales vẫn nhìn thấy cả sản phẩm tồn 0 để tạo đơn và yêu cầu điều hàng.
            // Việc chặn âm kho được xử lý riêng tại bước kho duyệt/xuất kho.
            'products' => $this->getProductsForOrderSelectIncludingZeroStock($warehouseId, $request),
        ]);
    }

    public function getCustomerInfo(int $customerId): JsonResponse
    {
        try {
            $user     = auth()->user();
            $customer = $this->customerService->find($customerId);

            if ($user && $user->hasRole('sales') && $customer->owner_id != $user->id) {
                return response()->json(['error' => 'Unauthorized'], 403);
            }

            $customer = $this->customerService->findWithDetails($customerId);

            return response()->json([
                'customer'      => $customer,
                'latest_orders' => $customer->orders()->latest()->take(5)->get(),
                'total_debt'    => $customer->debts()->sum('total_amount') - $customer->debts()->sum('paid_amount'),
            ]);
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function getProductPrice(Request $request, int $productId): JsonResponse
    {
        $schema = \Illuminate\Support\Facades\Schema::class;

        if (!$schema::hasTable('crm_product_catalog')) {
            return response()->json([
                'price'            => 0,
                'vat_percent'      => 0,
                'price_before_vat' => 0,
            ]);
        }

        $columns = $schema::getColumnListing('crm_product_catalog');
        $has = fn (string $col): bool => in_array($col, $columns, true);

        $selects = ['id'];

        foreach ([
            'price',
            'price_agent',
            'price_agent_vat',
            'price_retail',
            'price_retail_vat',
            'vat_percent',
        ] as $col) {
            $selects[] = $has($col) ? $col : DB::raw('NULL as ' . $col);
        }

        $product = DB::table('crm_product_catalog')
            ->where('id', $productId)
            ->select($selects)
            ->first();

        if (!$product) {
            return response()->json([
                'price'            => 0,
                'vat_percent'      => 0,
                'price_before_vat' => 0,
            ]);
        }

        $priceTierId    = $request->integer('price_tier_id') ?: null;
        $customerTypeId = $request->integer('customer_type_id') ?: null;

        $vat = $this->egoResolveVatPercentForOrderItem((int) $product->id, $priceTierId);

        $beforeVat = 0.0;
        $afterVat  = 0.0;

        if ($priceTierId && $schema::hasTable('crm_product_prices')) {
            $priceCols = $schema::getColumnListing('crm_product_prices');

            if (
                in_array('product_id', $priceCols, true)
                && in_array('price_tier_id', $priceCols, true)
                && in_array('price', $priceCols, true)
            ) {
                $tierRow = DB::table('crm_product_prices')
                    ->where('product_id', (int) $product->id)
                    ->where('price_tier_id', $priceTierId)
                    ->first();

                if ($tierRow) {
                    $beforeVat = (float) ($tierRow->price ?? 0);

                    foreach (['vat_percent', 'vat', 'tax_percent'] as $vatCol) {
                        if (in_array($vatCol, $priceCols, true) && (float) ($tierRow->{$vatCol} ?? 0) > 0) {
                            $vat = (float) $tierRow->{$vatCol};
                            break;
                        }
                    }

                    if ($beforeVat > 0) {
                        $afterVat = round($beforeVat * (1 + max(0, $vat) / 100), 0);
                    }
                }
            }
        }

        if ($afterVat <= 0) {
            if ((int) $customerTypeId === 1) {
                $beforeVat = (float) ($product->price_agent ?? 0);
                $afterVat  = (float) ($product->price_agent_vat ?? 0);
            } else {
                $beforeVat = (float) ($product->price_retail ?? ($product->price ?? 0));
                $afterVat  = (float) ($product->price_retail_vat ?? 0);
            }

            if ($afterVat <= 0 && $beforeVat > 0) {
                $afterVat = round($beforeVat * (1 + max(0, $vat) / 100), 0);
            }
        }

        return response()->json([
            'price'            => (int) round($afterVat),
            'vat_percent'      => (float) $vat,
            'price_before_vat' => (int) round($beforeVat),
        ]);
    }


    /**
     * Xuất Excel danh sách đơn hàng.
     */

    /**
     * Xuất Excel danh sách đơn hàng.
     */
    public function exportExcel(Request $request)
    {
        $filters = $request->all();
        $user = Auth::user();

        $query = Order::query()
            ->with([
                'lead.customer',
                'company',
                'warehouse',
                'items.product',
                'items.warehouse',
                'payments.method',
                'payments.recordedBy',
                'creator',
                'currentStatusType',
            ])
            ->orderByDesc('order_date')
            ->orderByDesc('id');

        if ($user && method_exists($user, 'hasRole') && $user->hasRole('sales')) {
            $query->where('created_by', $user->id);
        }

        if (!empty($filters['search'])) {
            $keyword = trim((string) $filters['search']);

            $query->where(function ($q) use ($keyword) {
                $q->where('order_code', 'like', '%' . $keyword . '%')
                    ->orWhere('receiver_name', 'like', '%' . $keyword . '%')
                    ->orWhere('receiver_phone', 'like', '%' . $keyword . '%')
                    ->orWhereHas('lead.customer', function ($customerQuery) use ($keyword) {
                        $customerQuery->where('name', 'like', '%' . $keyword . '%')
                            ->orWhere('phone', 'like', '%' . $keyword . '%');
                    });
            });
        }

        if (!empty($filters['company_id']) && \Illuminate\Support\Facades\Schema::hasColumn('crm_orders', 'company_id')) {
            $query->where('company_id', $filters['company_id']);
        }

        if (!empty($filters['created_by'])) {
            $query->where('created_by', $filters['created_by']);
        }

        if (!empty($filters['from_date'])) {
            $query->whereDate('order_date', '>=', $filters['from_date']);
        }

        if (!empty($filters['to_date'])) {
            $query->whereDate('order_date', '<=', $filters['to_date']);
        }

        if (!empty($filters['status'])) {
            $status = (string) $filters['status'];

            if ($status === 'sales') {
                $query->where('current_department', 'sales');
            } elseif ($status === 'ketoan') {
                $query->whereIn('current_department', ['accounting', 'ketoan']);
            } elseif ($status === 'duyet1') {
                $query->whereIn('current_department', ['sales_manager', 'duyet1']);
            } elseif ($status === 'duyet2') {
                $query->whereIn('current_department', ['management', 'director', 'duyet2']);
            } elseif ($status === 'kho') {
                $query->whereIn('current_department', ['warehouse', 'kho']);
            } elseif ($status === 'completed') {
                $query->where('current_department', 'completed');
            } elseif ($status === 'cancelled') {
                $query->where(function ($q) {
                    $q->whereIn('current_department', ['cancelled', 'canceled', 'da_huy', 'huy'])
                        ->orWhereIn('status', ['cancelled', 'canceled', 'da_huy', 'huy']);
                });
            }
        }

        $orders = $query->get();

        if (!empty($filters['payment_filter'])) {
            $paymentFilter = (string) $filters['payment_filter'];

            $orders = $orders->filter(function ($order) use ($paymentFilter) {
                $total = (float) ($order->total_amount ?? 0);
                $paid = (float) collect($order->payments ?? [])->sum('amount');
                $debt = max($total - $paid, 0);

                if ($paymentFilter === 'paid') {
                    return $total > 0 && $debt <= 0;
                }

                if ($paymentFilter === 'unpaid') {
                    return $paid <= 0 && $total > 0;
                }

                if ($paymentFilter === 'debt') {
                    return $debt > 0;
                }

                if ($paymentFilter === 'debt_30') {
                    if ($debt <= 0 || empty($order->order_date)) {
                        return false;
                    }

                    try {
                        return \Carbon\Carbon::parse($order->order_date)->lte(now()->subDays(30));
                    } catch (\Throwable $e) {
                        return false;
                    }
                }

                return true;
            })->values();
        }

        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $spreadsheet->getProperties()
            ->setCreator(config('app.name', 'CRM'))
            ->setTitle('Danh sách đơn hàng');

        $headerStyle = [
            'font' => ['bold' => true, 'color' => ['rgb' => '0F172A']],
            'fill' => [
                'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                'startColor' => ['rgb' => 'EAF6FF'],
            ],
            'borders' => [
                'allBorders' => [
                    'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                    'color' => ['rgb' => 'CBD5E1'],
                ],
            ],
            'alignment' => [
                'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
                'vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER,
            ],
        ];

        $sectionStyle = [
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => [
                'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                'startColor' => ['rgb' => '0891B2'],
            ],
        ];

        $cellStyle = [
            'borders' => [
                'allBorders' => [
                    'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                    'color' => ['rgb' => 'E2E8F0'],
                ],
            ],
            'alignment' => [
                'vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER,
            ],
        ];

        $autoSize = function ($sheet, string $lastColumn) {
            foreach (range('A', $lastColumn) as $column) {
                $sheet->getColumnDimension($column)->setAutoSize(true);
            }
        };

        $moneyColumns = function ($sheet, array $columns, int $startRow, int $endRow) {
            if ($endRow < $startRow) {
                return;
            }

            foreach ($columns as $column) {
                $sheet->getStyle($column . $startRow . ':' . $column . $endRow)
                    ->getNumberFormat()
                    ->setFormatCode('#,##0');
            }
        };

        $departmentLabel = function ($department) {
            return match ((string) $department) {
                'sales' => 'Sales',
                'sales_manager' => 'Sales Manager',
                'accounting', 'ketoan' => 'Kế toán',
                'management', 'director' => 'Giám đốc',
                'warehouse', 'kho' => 'Kho',
                'shipping' => 'Vận chuyển',
                'completed' => 'Hoàn tất',
                'cancelled', 'canceled' => 'Đã hủy',
                default => $department ?: '-',
            };
        };

        $statusLabel = function ($order, float $paid, float $remain) {
            $rawStatus = strtolower(trim((string) ($order->status ?? '')));
            $rawDept = strtolower(trim((string) ($order->current_department ?? '')));
            $displayStatusRaw = strtolower(trim((string) optional($order->currentStatusType)->name));

            $cancelValues = ['cancelled', 'canceled', 'da_huy', 'huy'];

            $isCancelled =
                in_array($rawStatus, $cancelValues, true) ||
                in_array($rawDept, $cancelValues, true) ||
                str_contains($displayStatusRaw, 'hủy') ||
                str_contains($displayStatusRaw, 'cancel');

            if ($isCancelled) {
                return 'Đã hủy';
            }

            if ((int) ($order->inventory_issued ?? 0) === 1) {
                return $remain > 0 ? 'Công nợ' : 'Hoàn tất';
            }

            if (in_array($rawDept, ['warehouse', 'kho'], true)) {
                return 'Sẵn sàng xuất kho';
            }

            return optional($order->currentStatusType)->name ?? '-';
        };

        $cleanSheetTitle = function ($name, $usedTitles) {
            $name = preg_replace('/[\[\]\:\*\?\/\\\\]/', ' ', (string) $name);
            $name = trim(preg_replace('/\s+/', ' ', $name));

            if ($name === '') {
                $name = 'Don hang';
            }

            $base = function_exists('mb_substr') ? mb_substr($name, 0, 31) : substr($name, 0, 31);
            $title = $base;
            $i = 2;

            while (in_array($title, $usedTitles, true)) {
                $suffix = ' ' . $i;
                $limit = 31 - strlen($suffix);
                $shortBase = function_exists('mb_substr') ? mb_substr($base, 0, $limit) : substr($base, 0, $limit);
                $title = $shortBase . $suffix;
                $i++;
            }

            return $title;
        };

        $calculateTaxTotals = function ($order) {
            $beforeTax = 0;
            $taxAmount = 0;
            $afterTax = 0;

            foreach ($order->items ?? [] as $item) {
                $qty = (int) ($item->quantity ?? 0);
                $unitPrice = (float) ($item->unit_price ?? 0);
                $discountPercent = (float) ($item->discount_percent ?? 0);
                $discountAmount = (float) ($item->discount_amount ?? 0);
                $vatPercent = (float) (optional($item->product)->vat_percent ?? 0);

                $subtotal = $qty * $unitPrice;
                $discount = $discountAmount > 0
                    ? ($discountAmount * $qty)
                    : ($subtotal * $discountPercent / 100);

                $lineTotal = max($subtotal - $discount, 0);

                if ($vatPercent > 0) {
                    $lineBeforeTax = $lineTotal / (1 + ($vatPercent / 100));
                    $lineTax = $lineTotal - $lineBeforeTax;
                } else {
                    $lineBeforeTax = $lineTotal;
                    $lineTax = 0;
                }

                $beforeTax += $lineBeforeTax;
                $taxAmount += $lineTax;
                $afterTax += $lineTotal;
            }

            if ($afterTax <= 0 && (float) ($order->total_amount ?? 0) > 0) {
                $afterTax = (float) $order->total_amount;
                $beforeTax = $afterTax;
                $taxAmount = 0;
            }

            return (object) [
                'before_tax' => round($beforeTax),
                'tax_amount' => round($taxAmount),
                'after_tax' => round($afterTax),
            ];
        };

        $usedSheetTitles = ['Tong hop don hang'];
        $detailSheetTitles = [];

        foreach ($orders as $order) {
            $sheetTitle = $cleanSheetTitle($order->order_code ?: ('Don ' . $order->id), $usedSheetTitles);
            $usedSheetTitles[] = $sheetTitle;
            $detailSheetTitles[(int) $order->id] = $sheetTitle;
        }

        /*
        |--------------------------------------------------------------------------
        | Sheet 1: Tổng hợp đơn hàng
        |--------------------------------------------------------------------------
        */
        $summarySheet = $spreadsheet->getActiveSheet();
        $summarySheet->setTitle('Tong hop don hang');

        $summarySheet->mergeCells('A1:S1');
        $summarySheet->setCellValue('A1', 'DANH SÁCH ĐƠN HÀNG');
        $summarySheet->getStyle('A1')->getFont()->setBold(true)->setSize(16);
        $summarySheet->getStyle('A1')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);

        $summarySheet->mergeCells('A2:S2');
        $summarySheet->setCellValue('A2', 'Xuất lúc: ' . now()->format('d/m/Y H:i') . ' | Số đơn: ' . $orders->count());
        $summarySheet->getStyle('A2')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);

        $headers = [
            'STT',
            'Mã đơn',
            'Ngày đặt',
            'Khách hàng',
            'SĐT',
            'Công ty',
            'Người tạo',
            'Tổng trước thuế',
            'Thuế VAT',
            'Tổng sau thuế',
            'Đã thu',
            'Còn nợ',
            'Trạng thái',
            'Bộ phận hiện tại',
            'Hóa đơn',
            'Mã số thuế',
            'Xuất kho',
            'Vận chuyển',
            'Ghi chú',
        ];

        $summarySheet->fromArray($headers, null, 'A4');
        $summarySheet->getStyle('A4:S4')->applyFromArray($headerStyle);

        $rowNumber = 5;

        foreach ($orders as $index => $order) {
            $customer = optional(optional($order->lead)->customer);
            $paid = (float) collect($order->payments ?? [])->sum('amount');
            $total = (float) ($order->total_amount ?? 0);
            $remain = max($total - $paid, 0);
            $taxTotals = $calculateTaxTotals($order);
            $sheetTitle = $detailSheetTitles[(int) $order->id] ?? null;

            $summarySheet->fromArray([
                $index + 1,
                $order->order_code,
                $order->order_date ? \Carbon\Carbon::parse($order->order_date)->format('d/m/Y') : '',
                $customer->name ?? '-',
                $customer->phone ?? '-',
                optional($order->company)->name ?? '-',
                optional($order->creator)->name ?? '-',
                $taxTotals->before_tax,
                $taxTotals->tax_amount,
                round($total),
                round($paid),
                round($remain),
                $statusLabel($order, $paid, $remain),
                $departmentLabel($order->current_department),
                match ($order->invoice_status ?? 'none') {
                    'issued' => 'Đã xuất',
                    'pending' => 'Chờ xuất',
                    default => 'Không yêu cầu',
                },
                $order->invoice_tax_code ?: '',
                (int) ($order->inventory_issued ?? 0) === 1 ? 'Đã xuất kho' : 'Chưa xuất kho',
                $order->shipping_status ?: '',
                $order->note ?: '',
            ], null, 'A' . $rowNumber);

            if ($sheetTitle) {
                $escapedSheetTitle = str_replace("'", "''", $sheetTitle);
                $cell = 'B' . $rowNumber;

                $summarySheet->getCell($cell)
                    ->getHyperlink()
                    ->setUrl("sheet://'{$escapedSheetTitle}'!A1");

                $summarySheet->getStyle($cell)->getFont()
                    ->setUnderline(\PhpOffice\PhpSpreadsheet\Style\Font::UNDERLINE_SINGLE)
                    ->getColor()
                    ->setRGB('0563C1');
            }

            $rowNumber++;
        }

        $lastDataRow = $rowNumber - 1;

        if ($lastDataRow >= 5) {
            $totalRow = $rowNumber + 1;
            $summarySheet->setCellValue('A' . $totalRow, 'TỔNG');
            $summarySheet->mergeCells('A' . $totalRow . ':G' . $totalRow);
            $summarySheet->setCellValue('H' . $totalRow, '=SUM(H5:H' . $lastDataRow . ')');
            $summarySheet->setCellValue('I' . $totalRow, '=SUM(I5:I' . $lastDataRow . ')');
            $summarySheet->setCellValue('J' . $totalRow, '=SUM(J5:J' . $lastDataRow . ')');
            $summarySheet->setCellValue('K' . $totalRow, '=SUM(K5:K' . $lastDataRow . ')');
            $summarySheet->setCellValue('L' . $totalRow, '=SUM(L5:L' . $lastDataRow . ')');

            $summarySheet->getStyle('A4:S' . $totalRow)->applyFromArray($cellStyle);
            $summarySheet->getStyle('A' . $totalRow . ':S' . $totalRow)->getFont()->setBold(true);
            $moneyColumns($summarySheet, ['H', 'I', 'J', 'K', 'L'], 5, $totalRow);
            $summarySheet->getStyle('S5:S' . $lastDataRow)->getAlignment()->setWrapText(true);
        }

        $summarySheet->freezePane('A5');
        $autoSize($summarySheet, 'S');

        /*
        |--------------------------------------------------------------------------
        | Sheet từng đơn hàng
        |--------------------------------------------------------------------------
        */
        foreach ($orders as $order) {
            $customer = optional(optional($order->lead)->customer);
            $paid = (float) collect($order->payments ?? [])->sum('amount');
            $total = (float) ($order->total_amount ?? 0);
            $remain = max($total - $paid, 0);
            $taxTotals = $calculateTaxTotals($order);

            $sheetTitle = $detailSheetTitles[(int) $order->id] ?? $cleanSheetTitle($order->order_code ?: ('Don ' . $order->id), []);
            $sheet = $spreadsheet->createSheet();
            $sheet->setTitle($sheetTitle);

            $sheet->mergeCells('A1:I1');
            $sheet->setCellValue('A1', 'CHI TIẾT ĐƠN HÀNG - ' . ($order->order_code ?? $order->id));
            $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(15);
            $sheet->getStyle('A1')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);

            $sheet->setCellValue('A2', '← Về tổng hợp');
            $sheet->getCell('A2')->getHyperlink()->setUrl("sheet://'Tong hop don hang'!A1");
            $sheet->getStyle('A2')->getFont()
                ->setUnderline(\PhpOffice\PhpSpreadsheet\Style\Font::UNDERLINE_SINGLE)
                ->getColor()
                ->setRGB('0563C1');

            $currentRow = 4;

            $writeSection = function ($title, array $items) use ($sheet, &$currentRow, $headerStyle, $sectionStyle, $cellStyle) {
                $sheet->mergeCells('A' . $currentRow . ':I' . $currentRow);
                $sheet->setCellValue('A' . $currentRow, $title);
                $sheet->getStyle('A' . $currentRow . ':I' . $currentRow)->applyFromArray($sectionStyle);
                $currentRow++;

                $sheet->fromArray(['Hạng mục', 'Giá trị', 'Ghi chú', '', '', '', '', '', ''], null, 'A' . $currentRow);
                $sheet->getStyle('A' . $currentRow . ':I' . $currentRow)->applyFromArray($headerStyle);
                $currentRow++;

                $startItemRow = $currentRow;

                foreach ($items as $item) {
                    $sheet->fromArray([
                        $item[0] ?? '',
                        $item[1] ?? '',
                        $item[2] ?? '',
                        '',
                        '',
                        '',
                        '',
                        '',
                        '',
                    ], null, 'A' . $currentRow);

                    $currentRow++;
                }

                $sheet->getStyle('A' . ($startItemRow - 2) . ':I' . ($currentRow - 1))->applyFromArray($cellStyle);
                $currentRow++;
            };

            $writeSection('1. Thông tin đơn hàng', [
                ['Mã đơn', $order->order_code, ''],
                ['Ngày đặt', $order->order_date ? \Carbon\Carbon::parse($order->order_date)->format('d/m/Y') : '', ''],
                ['Công ty', optional($order->company)->name ?? '-', ''],
                ['Người tạo', optional($order->creator)->name ?? '-', ''],
                ['Trạng thái', $statusLabel($order, $paid, $remain), ''],
                ['Bộ phận hiện tại', $departmentLabel($order->current_department), ''],
                ['Xuất kho', (int) ($order->inventory_issued ?? 0) === 1 ? 'Đã xuất kho' : 'Chưa xuất kho', ''],
                ['Vận chuyển', $order->shipping_status ?: '', ''],
            ]);

            $writeSection('2. Khách hàng & giao hàng', [
                ['Khách hàng', $customer->name ?? '-', ''],
                ['Số điện thoại', $customer->phone ?? '-', ''],
                ['Người nhận', $order->receiver_name ?: '', ''],
                ['SĐT người nhận', $order->receiver_phone ?: '', ''],
                ['Địa chỉ giao hàng', $order->shipping_address ?: '', ''],
                ['Đơn vị vận chuyển', $order->shipping_carrier ?: '', ''],
                ['Mã vận đơn', $order->tracking_number ?: '', ''],
                ['Ghi chú giao hàng', $order->shipping_note ?: '', ''],
            ]);

            $writeSection('3. Thanh toán, thuế & hóa đơn', [
                ['Tổng trước thuế', $taxTotals->before_tax, 'Tính theo VAT sản phẩm nếu có'],
                ['Thuế VAT', $taxTotals->tax_amount, 'Tổng tiền thuế VAT'],
                ['Tổng sau thuế', round($total), 'Tổng tiền đơn hàng'],
                ['Đã thu', round($paid), ''],
                ['Còn nợ', round($remain), ''],
                ['Trạng thái hóa đơn', match ($order->invoice_status ?? 'none') {
                    'issued' => 'Đã xuất',
                    'pending' => 'Chờ xuất',
                    default => 'Không yêu cầu',
                }, ''],
                ['Tên công ty xuất HĐ', $order->invoice_company_name ?: '', ''],
                ['Mã số thuế', $order->invoice_tax_code ?: '', ''],
                ['Địa chỉ hóa đơn', $order->invoice_address ?: '', ''],
                ['Email nhận hóa đơn', $order->invoice_email ?: '', ''],
            ]);

            /*
            |--------------------------------------------------------------------------
            | Bảng sản phẩm
            |--------------------------------------------------------------------------
            */
            $sheet->mergeCells('A' . $currentRow . ':I' . $currentRow);
            $sheet->setCellValue('A' . $currentRow, '4. Sản phẩm trong đơn');
            $sheet->getStyle('A' . $currentRow . ':I' . $currentRow)->applyFromArray($sectionStyle);
            $currentRow++;

            $sheet->fromArray([
                'STT',
                'Sản phẩm',
                'Kho',
                'Số lượng',
                'Đơn giá',
                'VAT %',
                'Trước thuế',
                'Thuế VAT',
                'Thành tiền',
            ], null, 'A' . $currentRow);

            $sheet->getStyle('A' . $currentRow . ':I' . $currentRow)->applyFromArray($headerStyle);
            $currentRow++;

            $itemStartRow = $currentRow;

            foreach ($order->items ?? [] as $itemIndex => $item) {
                $qty = (int) ($item->quantity ?? 0);
                $unitPrice = (float) ($item->unit_price ?? 0);
                $discountPercent = (float) ($item->discount_percent ?? 0);
                $discountAmount = (float) ($item->discount_amount ?? 0);
                $vatPercent = (float) (optional($item->product)->vat_percent ?? 0);

                $subtotal = $qty * $unitPrice;
                $discount = $discountAmount > 0
                    ? ($discountAmount * $qty)
                    : ($subtotal * $discountPercent / 100);

                $lineTotal = max($subtotal - $discount, 0);

                if ($vatPercent > 0) {
                    $lineBeforeTax = $lineTotal / (1 + ($vatPercent / 100));
                    $lineTax = $lineTotal - $lineBeforeTax;
                } else {
                    $lineBeforeTax = $lineTotal;
                    $lineTax = 0;
                }

                $sheet->fromArray([
                    $itemIndex + 1,
                    optional($item->product)->name ?? ('SP #' . ($item->product_id ?? '')),
                    optional($item->warehouse)->name ?? '-',
                    $qty,
                    round($unitPrice),
                    $vatPercent,
                    round($lineBeforeTax),
                    round($lineTax),
                    round($lineTotal),
                ], null, 'A' . $currentRow);

                $currentRow++;
            }

            if ($currentRow === $itemStartRow) {
                $sheet->mergeCells('A' . $currentRow . ':I' . $currentRow);
                $sheet->setCellValue('A' . $currentRow, 'Không có sản phẩm.');
                $currentRow++;
            }

            $sheet->getStyle('A' . ($itemStartRow - 1) . ':I' . ($currentRow - 1))->applyFromArray($cellStyle);
            $moneyColumns($sheet, ['E', 'G', 'H', 'I'], $itemStartRow, $currentRow - 1);
            $currentRow++;

            /*
            |--------------------------------------------------------------------------
            | Bảng thanh toán
            |--------------------------------------------------------------------------
            */
            $sheet->mergeCells('A' . $currentRow . ':I' . $currentRow);
            $sheet->setCellValue('A' . $currentRow, '5. Lịch sử thanh toán');
            $sheet->getStyle('A' . $currentRow . ':I' . $currentRow)->applyFromArray($sectionStyle);
            $currentRow++;

            $sheet->fromArray([
                'STT',
                'Ngày thanh toán',
                'Phương thức',
                'Số tiền',
                'Người ghi nhận',
                'Ghi chú',
                '',
                '',
                '',
            ], null, 'A' . $currentRow);

            $sheet->getStyle('A' . $currentRow . ':I' . $currentRow)->applyFromArray($headerStyle);
            $currentRow++;

            $paymentStartRow = $currentRow;

            foreach ($order->payments ?? [] as $paymentIndex => $payment) {
                $sheet->fromArray([
                    $paymentIndex + 1,
                    $payment->payment_date ? \Carbon\Carbon::parse($payment->payment_date)->format('d/m/Y') : '',
                    optional($payment->method)->name ?? '-',
                    round((float) ($payment->amount ?? 0)),
                    optional($payment->recordedBy)->name ?? '-',
                    $payment->note ?: '',
                    '',
                    '',
                    '',
                ], null, 'A' . $currentRow);

                $currentRow++;
            }

            if ($currentRow === $paymentStartRow) {
                $sheet->mergeCells('A' . $currentRow . ':I' . $currentRow);
                $sheet->setCellValue('A' . $currentRow, 'Chưa có thanh toán.');
                $currentRow++;
            }

            $sheet->getStyle('A' . ($paymentStartRow - 1) . ':I' . ($currentRow - 1))->applyFromArray($cellStyle);
            $moneyColumns($sheet, ['D'], $paymentStartRow, $currentRow - 1);

            $sheet->getStyle('B1:I' . $currentRow)
                ->getAlignment()
                ->setWrapText(true);

            $sheet->freezePane('A4');
            $autoSize($sheet, 'I');
        }

        $spreadsheet->setActiveSheetIndex(0);

        $fileName = 'danh-sach-don-hang-' . now()->format('Ymd-His') . '.xlsx';

        return response()->streamDownload(function () use ($spreadsheet) {
            $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
            $writer->save('php://output');
        }, $fileName, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Cache-Control' => 'max-age=0',
        ]);
    }



    // =========================================================================
    // PRIVATE HELPERS
    // =========================================================================

    private function resolveLeadId(array $data): array
    {
        $customerId = (int) ($data['customer_id'] ?? 0);
        $leadId     = (int) ($data['lead_id'] ?? 0);

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
                'customer_id'  => $customerId,
                'assigned_to'  => Auth::id(),
                'contact_date' => now(),
                'status_id'    => 1,
                'note'         => 'Auto tạo lead từ tạo đơn hàng',
                'created_by'   => Auth::id(),
                'created_at'   => now(),
                'updated_at'   => now(),
            ]);
        }

        $data['lead_id'] = $leadId;

        return $data;
    }

    private function dispatchOrderCreatedNotifications(Order $order): void
    {
        try {
            $actor     = Auth::user();
            $actorName = $actor?->name ?? 'Sales';
            $title     = 'Đơn hàng mới cần xử lý';
            $message   = "{$actorName} đã tạo đơn hàng mới #{$order->order_code}";

            $recipientIds = User::query()
                ->whereHas('roles', fn ($q) => $q->whereIn('name', ['admin', 'accounting', 'warehouse']))
                ->pluck('id')
                ->reject(fn ($id) => (int) $id === (int) $actor?->id);

            foreach ($recipientIds as $uid) {
                OrderNotification::create([
                    'order_id' => $order->id,
                    'user_id'  => $uid,
                    'type'     => 'department_notification',
                    'title'    => $title,
                    'message'  => $message,
                    'is_read'  => false,
                ]);
            }
        } catch (\Throwable $e) {
            Log::warning('Create notification failed: ' . $e->getMessage());
        }
    }

    private function resolveSelectedCustomer(Order $order): ?array
    {
        $customerId = (int) ($order->customer_id ?? 0);

        if ($customerId <= 0 && !empty($order->lead_id)) {
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

    private function snapshotOrderData(Order $order): array
    {
        return [
            'lead_id'            => $order->lead_id,
            'order_date'         => optional($order->order_date)->format('Y-m-d'),
            'warehouse_id'       => $order->warehouse_id,
            'price_tier_id'      => $order->price_tier_id ?? null,
            'note'               => $order->note ?? null,
            'total_amount'       => (float) (
                ($order->total_amount ?? 0)
                - (
                    (($order->shipping_fee_payer ?? 'company') === 'company')
                        ? ((float) ($order->shipping_fee_warehouse_to_station ?? 0) + (float) ($order->shipping_fee_station_to_customer ?? 0))
                        : 0
                )
            ),
            'current_department' => $order->current_department,
            'items'              => collect($order->items ?? [])->map(function ($item) {
                $qty      = (int) ($item->quantity ?? 0);
                $price    = (float) ($item->unit_price ?? 0);
                $discPct  = (float) ($item->discount_percent ?? 0);
                $discAmt  = (float) ($item->discount_amount ?? 0);
                $subtotal = $qty * $price;
                $discount = $discAmt > 0 ? ($discAmt * $qty) : ($subtotal * $discPct / 100);

                return [
                    'product_id'       => $item->product_id,
                    'warehouse_id'     => $item->warehouse_id,
                    'price_tier_id'    => $item->price_tier_id,
                    'quantity'         => $qty,
                    'unit_price'       => $price,
                    'discount_percent' => $discPct,
                    'discount_amount'  => $discAmt,
                    'line_total'       => max(0, $subtotal - $discount),
                ];
            })->values()->toArray(),
        ];
    }

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
            'order_id'  => $order->id,
            'user_id'   => $user?->id,
            'user_name' => $user?->name,
            'user_role' => method_exists($user, 'getRoleNames') ? $user->getRoleNames()->implode(', ') : null,
            'changes'   => $changes,
            'note'      => 'Chỉnh sửa đơn hàng',
        ]);
    }

    private function buildApprovalData(Request $request, string $action): array
    {
        $data = [
            'action' => $action,
            'note'   => $request->input('note'),
        ];

        if ($action === 'reject') {
            $data['rejection_reason'] = $request->input('rejection_reason');
        }

        if (auth()->user()->hasRole('accounting')) {
            $data['debt_checked'] = (bool) $request->input('debt_checked');
            $data['debt_note']    = $request->input('debt_note');
            $data['is_paid']      = (bool) $request->input('is_paid');
        }

        return $data;
    }


    private function egoResolveVatPercentForOrderItem(int $productId, ?int $priceTierId = null): float
    {
        $schema = \Illuminate\Support\Facades\Schema::class;

        $vat = 0.0;

        if ($productId > 0 && $priceTierId && $schema::hasTable('crm_product_prices')) {
            $priceCols = $schema::getColumnListing('crm_product_prices');

            if (in_array('product_id', $priceCols, true) && in_array('price_tier_id', $priceCols, true)) {
                foreach (['vat_percent', 'vat', 'tax_percent'] as $vatCol) {
                    if (in_array($vatCol, $priceCols, true)) {
                        $vat = (float) DB::table('crm_product_prices')
                            ->where('product_id', $productId)
                            ->where('price_tier_id', $priceTierId)
                            ->value($vatCol);

                        if ($vat > 0) {
                            return min(100, max(0, $vat));
                        }
                    }
                }
            }
        }

        if ($productId > 0 && $schema::hasTable('crm_product_catalog')) {
            foreach (['vat_percent', 'cost_vat_percent'] as $vatCol) {
                if ($schema::hasColumn('crm_product_catalog', $vatCol)) {
                    $vat = (float) DB::table('crm_product_catalog')
                        ->where('id', $productId)
                        ->value($vatCol);

                    if ($vat > 0) {
                        return min(100, max(0, $vat));
                    }
                }
            }
        }

        return 0.0;
    }


    private function resolveBasePrice(Request $request, object $product): float
    {
        $priceTierId    = $request->integer('price_tier_id') ?: null;
        $customerTypeId = $request->integer('customer_type_id') ?: null;

        if ($priceTierId) {
            try {
                $tierPrice = (float) (app(PricingService::class)->getUnitPrice(
                    $product->id,
                    $priceTierId,
                    $request->input('at')
                ) ?? 0);

                if ($tierPrice > 0) {
                    return $tierPrice;
                }
            } catch (\Throwable $e) {
                Log::warning('getProductPrice tier failed: ' . $e->getMessage());
            }
        }

        $basePrice = (float) ($product->price_retail ?? 0);

        if ((int) $customerTypeId === 1) {
            $agentPrice = (float) ($product->price_agent ?? 0);

            if ($agentPrice > 0) {
                $basePrice = $agentPrice;
            }
        }

        return $basePrice;
    }

    /**
     * Dropdown tạo đơn: hiển thị cả sản phẩm tồn 0 theo kho.
     * Bước tạo đơn không trừ tồn; bước kho xuất hàng mới bắt buộc kiểm tra tồn.
     */
    private function getProductsForOrderSelectIncludingZeroStock(int $warehouseId, Request $request): array
    {
        $schema = \Illuminate\Support\Facades\Schema::class;

        if (!$schema::hasTable('crm_product_catalog')) {
            return [];
        }

        $term = trim((string) $request->input('q', $request->input('term', '')));
        $limit = (int) $request->input('limit', 1000);
        $limit = max(50, min($limit, 2000));

        $productColumns = $schema::getColumnListing('crm_product_catalog');
        $hasProductColumn = function (string $column) use ($productColumns): bool {
            return in_array($column, $productColumns, true);
        };

        $selects = ['p.id'];
        foreach (['name', 'sku', 'price', 'price_agent', 'price_agent_vat', 'price_retail', 'price_retail_vat', 'vat_percent', 'quantity'] as $column) {
            $selects[] = $hasProductColumn($column)
                ? "p.{$column}"
                : DB::raw('NULL as ' . $column);
        }

        $hasBrandJoin = $schema::hasTable('crm_brands')
            && $hasProductColumn('brand_id')
            && $schema::hasColumn('crm_brands', 'id')
            && $schema::hasColumn('crm_brands', 'name');

        if ($hasBrandJoin) {
            $selects[] = 'b.name as brand_name';
        } else {
            $selects[] = DB::raw('NULL as brand_name');
        }

        $productsQuery = DB::table('crm_product_catalog as p')
            ->select($selects);

        if ($hasBrandJoin) {
            $productsQuery->leftJoin('crm_brands as b', 'b.id', '=', 'p.brand_id');
        }

        if ($hasProductColumn('deleted_at')) {
            $productsQuery->whereNull('p.deleted_at');
        }

        if ($hasProductColumn('is_active')) {
            $productsQuery->where(function ($q) {
                $q->where('p.is_active', 1)->orWhereNull('p.is_active');
            });
        }

        if ($term !== '') {
            $productsQuery->where(function ($q) use ($term, $hasProductColumn) {
                if ($hasProductColumn('name')) {
                    $q->where('p.name', 'like', '%' . $term . '%');
                }

                if ($hasProductColumn('sku')) {
                    $q->orWhere('p.sku', 'like', '%' . $term . '%');
                }
            });
        }

        $products = $productsQuery
            ->orderBy($hasProductColumn('sku') ? 'p.sku' : 'p.id')
            ->orderBy($hasProductColumn('name') ? 'p.name' : 'p.id')
            ->limit($limit)
            ->get();

        // Load đủ giá theo từng loại giá để màn tạo đơn hiển thị giống màn sửa sản phẩm.
        // Giá trong crm_product_prices đang lưu trước VAT, nên trả về JS giá sau VAT.
        $productIds = $products->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->values()
            ->all();

        $tierPricesMap = [];
        $tierVatMap = [];
        if (!empty($productIds) && $schema::hasTable('crm_product_prices')) {
            $priceTableColumns = $schema::getColumnListing('crm_product_prices');

            if (in_array('product_id', $priceTableColumns, true)
                && in_array('price_tier_id', $priceTableColumns, true)
                && in_array('price', $priceTableColumns, true)) {
                $tierRows = DB::table('crm_product_prices')
                    ->whereIn('product_id', $productIds)
                    ->select('product_id', 'price_tier_id', 'price', 'vat_percent')
                    ->get();

                foreach ($tierRows as $tierRow) {
                    $productId = (int) ($tierRow->product_id ?? 0);
                    $tierId = (int) ($tierRow->price_tier_id ?? 0);
                    $priceBeforeVat = (float) ($tierRow->price ?? 0);

                    if ($productId > 0 && $tierId > 0 && $priceBeforeVat > 0) {
                        $tierPricesMap[$productId][$tierId] = $priceBeforeVat;
                        $tierVatMap[$productId][$tierId] = (float) ($tierRow->vat_percent ?? 0);
                    }
                }
            }
        }

        $tierVatMap = $tierVatMap ?? [];
        $stockByProduct = $this->getWarehouseStockByProduct($warehouseId);

        return $products->map(function ($product) use ($stockByProduct, $tierPricesMap, $tierVatMap) {
            $productId = (int) ($product->id ?? 0);
            $stock = max(0, (int) ($stockByProduct[$productId] ?? 0));
            $vatPercent = max(0, (float) ($product->vat_percent ?? 0));

            $agentBase = (float) ($product->price_agent ?? 0);
            $retailBase = (float) ($product->price_retail ?? ($product->price ?? 0));

            $agentAfterVat = (float) ($product->price_agent_vat ?? 0);
            if ($agentAfterVat <= 0 && $agentBase > 0) {
                $agentAfterVat = round($agentBase * (1 + $vatPercent / 100), 0);
            }

            $retailAfterVat = (float) ($product->price_retail_vat ?? 0);
            if ($retailAfterVat <= 0 && $retailBase > 0) {
                $retailAfterVat = round($retailBase * (1 + $vatPercent / 100), 0);
            }

            $defaultPrice = $retailAfterVat > 0 ? $retailAfterVat : (float) ($product->price ?? 0);

            $tierPrices = new \stdClass();
            $tierVats = new \stdClass();

            foreach (($tierPricesMap[$productId] ?? []) as $tierId => $priceBeforeVat) {
                if ((float) $priceBeforeVat > 0) {
                    $tierVat = (float) ($tierVatMap[$productId][$tierId] ?? $vatPercent);
                    $tierPriceAfterVat = round((float) $priceBeforeVat * (1 + max(0, $tierVat) / 100), 0);
                    $tierPrices->{$tierId} = (int) round($tierPriceAfterVat, 0);
                    $tierVats->{$tierId} = $tierVat;
                }
            }

            $name = trim((string) ($product->name ?? ('Sản phẩm #' . $productId)));
            $sku = trim((string) ($product->sku ?? ''));
            $brand = trim((string) ($product->brand_name ?? ''));

            $parts = [$name];
            if ($sku !== '') {
                $parts[] = 'SKU: ' . $sku;
            }
            if ($brand !== '') {
                $parts[] = 'Thương hiệu: ' . $brand;
            }
            $parts[] = 'Tồn: ' . $stock;

            $text = implode(' - ', $parts);

            return [
                'id'               => $productId,
                'value'            => $productId,
                'product_id'       => $productId,
                'name'             => $name,
                'sku'              => $sku,
                'brand'            => $brand,
                'brand_name'       => $brand,
                'text'             => $text,
                'label'            => $text,
                'stock'            => $stock,
                'qty'              => $stock,
                'quantity'         => $stock,
                'inventory'        => $stock,
                'available_stock'  => $stock,
                'is_out_of_stock'  => $stock <= 0,
                'disabled'         => false,
                'price'            => (int) round($defaultPrice),
                'price_agent'      => (int) round($agentAfterVat > 0 ? $agentAfterVat : $defaultPrice),
                'price_retail'     => (int) round($retailAfterVat > 0 ? $retailAfterVat : $defaultPrice),
                'price_before_vat' => (int) round($retailBase > 0 ? $retailBase : (float) ($product->price ?? 0)),
                'vat_percent'      => $vatPercent,
                'tier_prices'      => $tierPrices,
                'tier_vats'        => $tierVats,
                'tier_vats'        => $tierVats,
            ];
        })->values()->all();
    }

    private function getWarehouseStockByProduct(int $warehouseId): array
    {
        $schema = \Illuminate\Support\Facades\Schema::class;

        if ($schema::hasTable('crm_product_stock')) {
            return DB::table('crm_product_stock')
                ->where('warehouse_id', $warehouseId)
                ->selectRaw('product_id, COALESCE(SUM(qty), 0) as stock_qty')
                ->groupBy('product_id')
                ->pluck('stock_qty', 'product_id')
                ->map(fn ($qty) => (int) $qty)
                ->all();
        }

        if ($schema::hasTable('crm_product_stock_lots')) {
            return DB::table('crm_product_stock_lots')
                ->where('warehouse_id', $warehouseId)
                ->selectRaw('product_id, COALESCE(SUM(qty_remaining), 0) as stock_qty')
                ->groupBy('product_id')
                ->pluck('stock_qty', 'product_id')
                ->map(fn ($qty) => (int) $qty)
                ->all();
        }

        return [];
    }

    /**
     * Chặn xuất kho nếu tồn hiện tại không đủ. Tạo đơn vẫn cho phép tồn 0.
     */
    private function assertOrderStockAvailable(int $orderId, bool $lockRows = false): void
    {
        $schema = \Illuminate\Support\Facades\Schema::class;

        if (!$schema::hasTable('crm_orders') || !$schema::hasTable('crm_order_items')) {
            return;
        }

        $order = DB::table('crm_orders')->where('id', $orderId)->first();
        if (!$order) {
            return;
        }

        $itemColumns = $schema::getColumnListing('crm_order_items');
        $hasItemWarehouse = in_array('warehouse_id', $itemColumns, true);

        $itemsQuery = DB::table('crm_order_items')
            ->where('order_id', $orderId)
            ->select(['id', 'product_id', 'quantity']);

        if ($hasItemWarehouse) {
            $itemsQuery->addSelect('warehouse_id');
        }

        $requiredRows = [];
        foreach ($itemsQuery->get() as $item) {
            $productId = (int) ($item->product_id ?? 0);
            $warehouseId = $hasItemWarehouse
                ? (int) ($item->warehouse_id ?? 0)
                : (int) ($order->warehouse_id ?? 0);
            $quantity = (int) ($item->quantity ?? 0);

            if ($productId <= 0 || $quantity <= 0) {
                continue;
            }

            $key = $productId . ':' . $warehouseId;
            if (!isset($requiredRows[$key])) {
                $requiredRows[$key] = [
                    'product_id'   => $productId,
                    'warehouse_id' => $warehouseId,
                    'quantity'     => 0,
                ];
            }

            $requiredRows[$key]['quantity'] += $quantity;
        }

        $errors = [];
        foreach ($requiredRows as $row) {
            $productId = (int) $row['product_id'];
            $warehouseId = (int) $row['warehouse_id'];
            $requiredQty = (int) $row['quantity'];

            if ($warehouseId <= 0) {
                $errors[] = $this->stockProductLabel($productId) . ' chưa chọn kho xuất.';
                continue;
            }

            $availableQty = $this->currentWarehouseStock($productId, $warehouseId, $lockRows);

            if ($availableQty < $requiredQty) {
                if ($availableQty <= 0) {
                    $errors[] = sprintf(
                        '%s tại %s đã hết hàng. Vui lòng điều hàng/nhập kho trước khi xuất kho.',
                        $this->stockProductLabel($productId),
                        $this->stockWarehouseLabel($warehouseId)
                    );
                } else {
                    $errors[] = sprintf(
                        '%s tại %s không đủ tồn: chỉ còn %s, cần xuất %s. Vui lòng điều hàng/nhập kho trước khi xuất kho.',
                        $this->stockProductLabel($productId),
                        $this->stockWarehouseLabel($warehouseId),
                        number_format($availableQty, 0, ',', '.'),
                        number_format($requiredQty, 0, ',', '.')
                    );
                }
            }
        }

        if (!empty($errors)) {
            throw new \RuntimeException("Không đủ tồn kho để xuất kho:\n- " . implode("\n- ", $errors));
        }
    }

    private function currentWarehouseStock(int $productId, int $warehouseId, bool $lockRows = false): int
    {
        $schema = \Illuminate\Support\Facades\Schema::class;

        if ($schema::hasTable('crm_product_stock')) {
            $query = DB::table('crm_product_stock')
                ->where('product_id', $productId)
                ->where('warehouse_id', $warehouseId);

            if ($lockRows) {
                $query->lockForUpdate();
            }

            return max(0, (int) $query->sum('qty'));
        }

        if ($schema::hasTable('crm_product_stock_lots')) {
            $query = DB::table('crm_product_stock_lots')
                ->where('product_id', $productId)
                ->where('warehouse_id', $warehouseId);

            if ($lockRows) {
                $query->lockForUpdate();
            }

            return max(0, (int) $query->sum('qty_remaining'));
        }

        if ($schema::hasTable('crm_product_catalog') && $schema::hasColumn('crm_product_catalog', 'quantity')) {
            return max(0, (int) DB::table('crm_product_catalog')->where('id', $productId)->value('quantity'));
        }

        return 0;
    }

    private function stockProductLabel(int $productId): string
    {
        $schema = \Illuminate\Support\Facades\Schema::class;

        if (!$schema::hasTable('crm_product_catalog')) {
            return 'Sản phẩm #' . $productId;
        }

        $product = DB::table('crm_product_catalog')
            ->where('id', $productId)
            ->select(['id', 'name', 'sku'])
            ->first();

        if (!$product) {
            return 'Sản phẩm #' . $productId;
        }

        $name = trim((string) ($product->name ?? 'Sản phẩm #' . $productId));
        $sku = trim((string) ($product->sku ?? ''));

        return $sku !== '' ? ($name . ' (' . $sku . ')') : $name;
    }

    private function stockWarehouseLabel(int $warehouseId): string
    {
        $schema = \Illuminate\Support\Facades\Schema::class;

        if (!$schema::hasTable('crm_warehouses')) {
            return 'Kho #' . $warehouseId;
        }

        $name = DB::table('crm_warehouses')->where('id', $warehouseId)->value('name');

        return $name ? (string) $name : ('Kho #' . $warehouseId);
    }

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

        $paid   = $order->payments->sum('amount');
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

    private function egoSyncOrderItemsAndTotalFromRequest(Request $request, int $orderId): void
    {
        $items = $request->input('items', []);

        if (!is_array($items) || count($items) === 0) {
            return;
        }

        $schema = \Illuminate\Support\Facades\Schema::class;

        $orderTable = $schema::hasTable('crm_orders') ? 'crm_orders' : ($schema::hasTable('orders') ? 'orders' : null);
        $itemTable = $schema::hasTable('crm_order_items') ? 'crm_order_items' : ($schema::hasTable('order_items') ? 'order_items' : null);

        if (!$orderTable || !$itemTable) {
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
            if (!is_array($row)) {
                continue;
            }

            $itemId = (int) ($row['id'] ?? 0);
            $warehouseId = (int) ($row['warehouse_id'] ?? 0);
            $productId = (int) ($row['product_id'] ?? 0);
            $priceTierId = !empty($row['price_tier_id']) ? (int) $row['price_tier_id'] : null;

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
                $cacheKey = $productId . ':' . (int) ($priceTierId ?? 0);

                if (!array_key_exists($cacheKey, $productVatCache)) {
                    $productVatCache[$cacheKey] = $this->egoResolveVatPercentForOrderItem($productId, $priceTierId);
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
                    return !in_array((int) $it->id, $usedItemIds, true)
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

        if (!empty($orderPayload)) {
            DB::table($orderTable)
                ->where('id', $orderId)
                ->update($orderPayload);
        }
    }
    /* EGO_ORDER_TOTAL_SYNC_END */

}