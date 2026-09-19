<?php

$root = '/home/crmegoso/public_html';

function p($path) {
    global $root;
    return $root . '/' . ltrim($path, '/');
}

function backup_file($path) {
    $full = p($path);

    if (!file_exists($full)) {
        exit("Không tìm thấy file: {$full}\n");
    }

    $backup = $full . '.bak_advanced_filter_' . date('Ymd_His');
    copy($full, $backup);

    echo "Backup: {$backup}\n";
}

/*
|--------------------------------------------------------------------------
| 1. Patch OrderController@index
|--------------------------------------------------------------------------
*/
$controllerPath = 'app/Http/Controllers/OrderController.php';
$controllerFile = p($controllerPath);

backup_file($controllerPath);

$controller = file_get_contents($controllerFile);

$indexStart = strpos($controller, '    public function index(Request $request): View');
$createStart = strpos($controller, '    /**' . "\n" . '     * Form tạo đơn hàng mới.', $indexStart);

if ($indexStart === false || $createStart === false) {
    exit("Không tìm thấy đúng vị trí hàm index trong OrderController.php\n");
}

$newIndex = <<'PHP_CODE'
    public function index(Request $request): View
    {
        $orders = $this->getAdvancedFilteredOrders($request);

        $warehouses = DB::table('crm_warehouses')
            ->select('id', 'name')
            ->orderBy('name')
            ->get();

        $companies = Company::query()
            ->select('id', 'name', 'code')
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

        $statusTypes = collect();

        if (class_exists(\App\Models\CRM\Orders\OrderStatusType::class)) {
            try {
                $statusTypes = \App\Models\CRM\Orders\OrderStatusType::query()
                    ->select('id', 'name', 'code', 'color')
                    ->orderBy('name')
                    ->get();
            } catch (\Throwable $e) {
                $statusTypes = collect();
            }
        }

        return view('orders.index', compact(
            'orders',
            'warehouses',
            'companies',
            'creators',
            'statusTypes'
        ));
    }

    private function getAdvancedFilteredOrders(Request $request)
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

        $user = Auth::user();

        if (
            $user
            && method_exists($user, 'hasRole')
            && $user->hasRole('sales')
            && !$user->hasRole(['admin', 'management', 'accounting', 'sales_manager', 'warehouse'])
        ) {
            $query->where('created_by', $user->id);
        }

        $search = trim((string) $request->input('search', ''));

        if ($search !== '') {
            $like = '%' . addcslashes($search, '%_\\') . '%';

            $query->where(function ($q) use ($like) {
                $q->where('order_code', 'like', $like);

                if (\Illuminate\Support\Facades\Schema::hasColumn('crm_orders', 'invoice_company_name')) {
                    $q->orWhere('invoice_company_name', 'like', $like);
                }

                if (\Illuminate\Support\Facades\Schema::hasColumn('crm_orders', 'invoice_tax_code')) {
                    $q->orWhere('invoice_tax_code', 'like', $like);
                }

                if (\Illuminate\Support\Facades\Schema::hasColumn('crm_orders', 'receiver_name')) {
                    $q->orWhere('receiver_name', 'like', $like);
                }

                if (\Illuminate\Support\Facades\Schema::hasColumn('crm_orders', 'receiver_phone')) {
                    $q->orWhere('receiver_phone', 'like', $like);
                }

                $q->orWhereHas('lead.customer', function ($customerQuery) use ($like) {
                    $customerQuery
                        ->where('name', 'like', $like)
                        ->orWhere('phone', 'like', $like)
                        ->orWhere('email', 'like', $like)
                        ->orWhere('tax_code', 'like', $like);
                });

                $q->orWhereHas('items', function ($itemQuery) use ($like) {
                    $itemQuery->where('product_name', 'like', $like);
                });

                $q->orWhereHas('items.product', function ($productQuery) use ($like) {
                    $productQuery
                        ->where('name', 'like', $like)
                        ->orWhere('sku', 'like', $like);
                });
            });
        }

        if ($request->filled('order_code')) {
            $query->where('order_code', 'like', '%' . addcslashes($request->input('order_code'), '%_\\') . '%');
        }

        if ($request->filled('company_id')) {
            $companyId = (int) $request->input('company_id');

            $query->where(function ($q) use ($companyId) {
                if (\Illuminate\Support\Facades\Schema::hasColumn('crm_orders', 'company_id')) {
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

        if ($request->filled('warehouse_id')) {
            $warehouseId = (int) $request->input('warehouse_id');

            $query->where(function ($q) use ($warehouseId) {
                $q->where('warehouse_id', $warehouseId)
                    ->orWhereHas('items', function ($itemQuery) use ($warehouseId) {
                        $itemQuery->where('warehouse_id', $warehouseId);
                    });
            });
        }

        if ($request->filled('created_by')) {
            $query->where('created_by', (int) $request->input('created_by'));
        }

        if ($request->filled('department')) {
            $department = $request->input('department');

            $departmentMap = [
                'sales'         => ['sales'],
                'sales_manager' => ['sales_manager', 'duyet1'],
                'accounting'    => ['accounting', 'ketoan'],
                'management'    => ['management', 'director', 'duyet2'],
                'warehouse'     => ['warehouse', 'kho'],
                'shipping'      => ['shipping'],
                'completed'     => ['completed'],
                'cancelled'     => ['cancelled', 'canceled', 'da_huy', 'huy'],
            ];

            $query->whereIn('current_department', $departmentMap[$department] ?? [$department]);
        } elseif ($request->filled('status')) {
            $status = $request->input('status');

            $statusMap = [
                'sales'     => ['sales'],
                'ketoan'    => ['accounting', 'ketoan'],
                'duyet1'    => ['sales_manager', 'duyet1'],
                'duyet2'    => ['management', 'director', 'duyet2'],
                'kho'       => ['warehouse', 'kho'],
                'completed' => ['completed'],
                'cancelled' => ['cancelled', 'canceled', 'da_huy', 'huy'],
            ];

            $query->whereIn('current_department', $statusMap[$status] ?? [$status]);
        }

        if ($request->filled('status_type_id')) {
            $query->where('current_status_type_id', (int) $request->input('status_type_id'));
        }

        if ($request->filled('invoice_status') && \Illuminate\Support\Facades\Schema::hasColumn('crm_orders', 'invoice_status')) {
            $invoiceStatus = $request->input('invoice_status');

            if ($invoiceStatus === 'none') {
                $query->where(function ($q) {
                    $q->whereNull('invoice_status')
                        ->orWhere('invoice_status', '')
                        ->orWhere('invoice_status', 'none')
                        ->orWhere('invoice_status', 'no_invoice');
                });
            } else {
                $query->where('invoice_status', $invoiceStatus);
            }
        }

        if ($request->filled('shipping_status') && \Illuminate\Support\Facades\Schema::hasColumn('crm_orders', 'shipping_status')) {
            $query->where('shipping_status', $request->input('shipping_status'));
        }

        if ($request->filled('inventory_issued') && \Illuminate\Support\Facades\Schema::hasColumn('crm_orders', 'inventory_issued')) {
            $query->where('inventory_issued', (int) $request->input('inventory_issued'));
        }

        if ($request->filled('amount_min')) {
            $query->where('total_amount', '>=', (float) str_replace(',', '', $request->input('amount_min')));
        }

        if ($request->filled('amount_max')) {
            $query->where('total_amount', '<=', (float) str_replace(',', '', $request->input('amount_max')));
        }

        $this->applyPaymentStatusFilter($query, $request);
        $this->applyDateFilter($query, $request);

        $sortBy = $request->input('sort_by', 'created_at');
        $sortDir = $request->input('sort_dir', 'desc') === 'asc' ? 'asc' : 'desc';

        $allowedSorts = [
            'created_at',
            'order_date',
            'total_amount',
            'order_code',
            'updated_at',
            'estimated_delivery',
        ];

        if (!in_array($sortBy, $allowedSorts, true)) {
            $sortBy = 'created_at';
        }

        if (!\Illuminate\Support\Facades\Schema::hasColumn('crm_orders', $sortBy)) {
            $sortBy = 'created_at';
        }

        $perPage = (int) $request->input('per_page', 20);
        $perPage = in_array($perPage, [10, 20, 50, 100], true) ? $perPage : 20;

        return $query
            ->orderBy($sortBy, $sortDir)
            ->orderByDesc('id')
            ->paginate($perPage)
            ->appends($request->query());
    }

    private function applyDateFilter($query, Request $request): void
    {
        $dateType = $request->input('date_type', 'order_date');

        $dateColumns = [
            'order_date'         => 'order_date',
            'created_at'         => 'created_at',
            'approved_at'        => 'approved_at',
            'estimated_delivery' => 'estimated_delivery',
        ];

        $dateColumn = $dateColumns[$dateType] ?? 'order_date';

        if (!\Illuminate\Support\Facades\Schema::hasColumn('crm_orders', $dateColumn)) {
            $dateColumn = 'created_at';
        }

        $fromDate = $request->input('from_date');
        $toDate = $request->input('to_date');

        if ($request->filled('quick_range')) {
            [$fromDate, $toDate] = $this->resolveQuickDateRange($request->input('quick_range'));
        }

        if (!empty($fromDate)) {
            $query->whereDate($dateColumn, '>=', $fromDate);
        }

        if (!empty($toDate)) {
            $query->whereDate($dateColumn, '<=', $toDate);
        }
    }

    private function resolveQuickDateRange(string $range): array
    {
        $now = \Illuminate\Support\Carbon::now();

        return match ($range) {
            'today'      => [$now->copy()->toDateString(), $now->copy()->toDateString()],
            'yesterday'  => [$now->copy()->subDay()->toDateString(), $now->copy()->subDay()->toDateString()],
            'this_week'  => [$now->copy()->startOfWeek()->toDateString(), $now->copy()->endOfWeek()->toDateString()],
            'last_week'  => [$now->copy()->subWeek()->startOfWeek()->toDateString(), $now->copy()->subWeek()->endOfWeek()->toDateString()],
            'this_month' => [$now->copy()->startOfMonth()->toDateString(), $now->copy()->endOfMonth()->toDateString()],
            'last_month' => [$now->copy()->subMonth()->startOfMonth()->toDateString(), $now->copy()->subMonth()->endOfMonth()->toDateString()],
            default      => [null, null],
        };
    }

    private function applyPaymentStatusFilter($query, Request $request): void
    {
        if (!$request->filled('payment_status')) {
            return;
        }

        if (!class_exists(\App\Models\Payments\Payment::class)) {
            return;
        }

        $paymentTable = (new \App\Models\Payments\Payment())->getTable();

        if (!\Illuminate\Support\Facades\Schema::hasTable($paymentTable)) {
            return;
        }

        $paidSql = "(SELECT COALESCE(SUM({$paymentTable}.amount),0) FROM {$paymentTable} WHERE {$paymentTable}.order_id = crm_orders.id)";

        match ($request->input('payment_status')) {
            'unpaid'  => $query->whereRaw("{$paidSql} = 0"),
            'partial' => $query->whereRaw("{$paidSql} > 0 AND {$paidSql} < crm_orders.total_amount"),
            'paid'    => $query->whereRaw("{$paidSql} >= crm_orders.total_amount AND crm_orders.total_amount > 0"),
            'debt'    => $query->whereRaw("{$paidSql} < crm_orders.total_amount"),
            default   => null,
        };
    }

PHP_CODE;

$controller = substr($controller, 0, $indexStart) . $newIndex . substr($controller, $createStart);
file_put_contents($controllerFile, $controller);

echo "Đã patch OrderController@index\n";

/*
|--------------------------------------------------------------------------
| 2. Patch orders/index.blade.php
|--------------------------------------------------------------------------
*/
$viewPath = 'resources/views/orders/index.blade.php';
$viewFile = p($viewPath);

backup_file($viewPath);

$view = file_get_contents($viewFile);

$formStart = strpos($view, '        <form action="{{ route(\'orders.index\') }}" method="GET" class="mb-3">');
$tableStart = strpos($view, '        <div class="d-none d-md-block">', $formStart);

if ($formStart === false || $tableStart === false) {
    exit("Không tìm thấy đúng form bộ lọc trong orders/index.blade.php\n");
}

$newForm = <<'BLADE'
        @php
            $filterKeys = [
                'search',
                'order_code',
                'company_id',
                'warehouse_id',
                'created_by',
                'department',
                'status_type_id',
                'invoice_status',
                'shipping_status',
                'payment_status',
                'inventory_issued',
                'date_type',
                'quick_range',
                'from_date',
                'to_date',
                'amount_min',
                'amount_max',
                'sort_by',
                'sort_dir',
                'per_page',
            ];

            $hasFilter = collect(request()->only($filterKeys))
                ->filter(fn($value) => is_array($value) ? count(array_filter($value)) > 0 : filled($value))
                ->isNotEmpty();
        @endphp

        <form action="{{ route('orders.index') }}" method="GET" class="mb-3">
            <div class="card-glass">
                <div class="card-head">
                    <div>
                        <p class="card-title mb-0">
                            <i class="bi bi-funnel"></i> Bộ lọc nâng cao
                        </p>
                        <div class="card-sub">
                            Lọc theo công ty, kho, người tạo, trạng thái, thanh toán, hóa đơn, vận chuyển, tổng tiền…
                        </div>
                    </div>

                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-ego">
                            <i class="bi bi-search"></i> Lọc
                        </button>

                        @if($hasFilter)
                            <a href="{{ route('orders.index') }}" class="btn btn-ghost">
                                <i class="bi bi-x-circle"></i> Xóa lọc
                            </a>
                        @endif
                    </div>
                </div>

                <div class="card-body-modern">
                    <div class="row g-2 align-items-end">
                        <div class="col-12 col-md-3">
                            <label class="form-label small">Tìm kiếm chung</label>
                            <input type="text" name="search" class="form-control form-control-sm"
                                   value="{{ request('search') }}"
                                   placeholder="Mã đơn, khách hàng, SĐT, email, MST, sản phẩm...">
                        </div>

                        <div class="col-6 col-md-2">
                            <label class="form-label small">Mã đơn</label>
                            <input type="text" name="order_code" class="form-control form-control-sm"
                                   value="{{ request('order_code') }}"
                                   placeholder="ORD...">
                        </div>

                        <div class="col-6 col-md-2">
                            <label class="form-label small">Công ty</label>
                            <select name="company_id" class="form-select form-select-sm">
                                <option value="">-- Tất cả --</option>
                                @foreach($companies ?? [] as $company)
                                    <option value="{{ $company->id }}" {{ (string)request('company_id') === (string)$company->id ? 'selected' : '' }}>
                                        {{ $company->code ? $company->code . ' - ' : '' }}{{ $company->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-6 col-md-2">
                            <label class="form-label small">Kho</label>
                            <select name="warehouse_id" class="form-select form-select-sm">
                                <option value="">-- Tất cả --</option>
                                @foreach($warehouses ?? [] as $wh)
                                    <option value="{{ $wh->id }}" {{ (string)request('warehouse_id') === (string)$wh->id ? 'selected' : '' }}>
                                        {{ $wh->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-6 col-md-3">
                            <label class="form-label small">Người tạo</label>
                            <select name="created_by" class="form-select form-select-sm">
                                <option value="">-- Tất cả --</option>
                                @foreach($creators ?? [] as $creator)
                                    <option value="{{ $creator->id }}" {{ (string)request('created_by') === (string)$creator->id ? 'selected' : '' }}>
                                        {{ $creator->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-6 col-md-2">
                            <label class="form-label small">Bộ phận hiện tại</label>
                            <select name="department" class="form-select form-select-sm">
                                <option value="">-- Tất cả --</option>
                                <option value="sales" {{ request('department') == 'sales' ? 'selected' : '' }}>Sales</option>
                                <option value="sales_manager" {{ request('department') == 'sales_manager' ? 'selected' : '' }}>Sales Manager</option>
                                <option value="accounting" {{ request('department') == 'accounting' ? 'selected' : '' }}>Kế toán</option>
                                <option value="management" {{ request('department') == 'management' ? 'selected' : '' }}>Giám đốc</option>
                                <option value="warehouse" {{ request('department') == 'warehouse' ? 'selected' : '' }}>Kho</option>
                                <option value="shipping" {{ request('department') == 'shipping' ? 'selected' : '' }}>Vận chuyển</option>
                                <option value="completed" {{ request('department') == 'completed' ? 'selected' : '' }}>Hoàn tất</option>
                                <option value="cancelled" {{ request('department') == 'cancelled' ? 'selected' : '' }}>Đã hủy</option>
                            </select>
                        </div>

                        <div class="col-6 col-md-2">
                            <label class="form-label small">Trạng thái hệ thống</label>
                            <select name="status_type_id" class="form-select form-select-sm">
                                <option value="">-- Tất cả --</option>
                                @foreach($statusTypes ?? [] as $statusType)
                                    <option value="{{ $statusType->id }}" {{ (string)request('status_type_id') === (string)$statusType->id ? 'selected' : '' }}>
                                        {{ $statusType->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-6 col-md-2">
                            <label class="form-label small">Hóa đơn</label>
                            <select name="invoice_status" class="form-select form-select-sm">
                                <option value="">-- Tất cả --</option>
                                <option value="none" {{ request('invoice_status') == 'none' ? 'selected' : '' }}>Không yêu cầu</option>
                                <option value="pending" {{ request('invoice_status') == 'pending' ? 'selected' : '' }}>Chờ xuất</option>
                                <option value="issued" {{ request('invoice_status') == 'issued' ? 'selected' : '' }}>Đã xuất</option>
                            </select>
                        </div>

                        <div class="col-6 col-md-2">
                            <label class="form-label small">Thanh toán</label>
                            <select name="payment_status" class="form-select form-select-sm">
                                <option value="">-- Tất cả --</option>
                                <option value="unpaid" {{ request('payment_status') == 'unpaid' ? 'selected' : '' }}>Chưa thu</option>
                                <option value="partial" {{ request('payment_status') == 'partial' ? 'selected' : '' }}>Thu một phần</option>
                                <option value="paid" {{ request('payment_status') == 'paid' ? 'selected' : '' }}>Đã thu đủ</option>
                                <option value="debt" {{ request('payment_status') == 'debt' ? 'selected' : '' }}>Còn công nợ</option>
                            </select>
                        </div>

                        <div class="col-6 col-md-2">
                            <label class="form-label small">Vận chuyển</label>
                            <select name="shipping_status" class="form-select form-select-sm">
                                <option value="">-- Tất cả --</option>
                                <option value="ready" {{ request('shipping_status') == 'ready' ? 'selected' : '' }}>Chờ vận chuyển</option>
                                <option value="shipped" {{ request('shipping_status') == 'shipped' ? 'selected' : '' }}>Đã vận chuyển</option>
                                <option value="pending" {{ request('shipping_status') == 'pending' ? 'selected' : '' }}>Chưa xử lý</option>
                            </select>
                        </div>

                        <div class="col-6 col-md-2">
                            <label class="form-label small">Xuất kho</label>
                            <select name="inventory_issued" class="form-select form-select-sm">
                                <option value="">-- Tất cả --</option>
                                <option value="1" {{ request('inventory_issued') === '1' ? 'selected' : '' }}>Đã xuất kho</option>
                                <option value="0" {{ request('inventory_issued') === '0' ? 'selected' : '' }}>Chưa xuất kho</option>
                            </select>
                        </div>

                        <div class="col-6 col-md-2">
                            <label class="form-label small">Lọc theo ngày</label>
                            <select name="date_type" class="form-select form-select-sm">
                                <option value="order_date" {{ request('date_type', 'order_date') == 'order_date' ? 'selected' : '' }}>Ngày đặt</option>
                                <option value="created_at" {{ request('date_type') == 'created_at' ? 'selected' : '' }}>Ngày tạo</option>
                                <option value="approved_at" {{ request('date_type') == 'approved_at' ? 'selected' : '' }}>Ngày duyệt</option>
                                <option value="estimated_delivery" {{ request('date_type') == 'estimated_delivery' ? 'selected' : '' }}>Dự kiến giao</option>
                            </select>
                        </div>

                        <div class="col-6 col-md-2">
                            <label class="form-label small">Kỳ nhanh</label>
                            <select name="quick_range" class="form-select form-select-sm">
                                <option value="">-- Tùy chọn --</option>
                                <option value="today" {{ request('quick_range') == 'today' ? 'selected' : '' }}>Hôm nay</option>
                                <option value="yesterday" {{ request('quick_range') == 'yesterday' ? 'selected' : '' }}>Hôm qua</option>
                                <option value="this_week" {{ request('quick_range') == 'this_week' ? 'selected' : '' }}>Tuần này</option>
                                <option value="last_week" {{ request('quick_range') == 'last_week' ? 'selected' : '' }}>Tuần trước</option>
                                <option value="this_month" {{ request('quick_range') == 'this_month' ? 'selected' : '' }}>Tháng này</option>
                                <option value="last_month" {{ request('quick_range') == 'last_month' ? 'selected' : '' }}>Tháng trước</option>
                            </select>
                        </div>

                        <div class="col-6 col-md-2">
                            <label class="form-label small">Từ ngày</label>
                            <input type="date" name="from_date" class="form-control form-control-sm"
                                   value="{{ request('from_date') }}">
                        </div>

                        <div class="col-6 col-md-2">
                            <label class="form-label small">Đến ngày</label>
                            <input type="date" name="to_date" class="form-control form-control-sm"
                                   value="{{ request('to_date') }}">
                        </div>

                        <div class="col-6 col-md-2">
                            <label class="form-label small">Tổng tiền từ</label>
                            <input type="number" name="amount_min" class="form-control form-control-sm"
                                   value="{{ request('amount_min') }}"
                                   placeholder="VD: 1000000">
                        </div>

                        <div class="col-6 col-md-2">
                            <label class="form-label small">Tổng tiền đến</label>
                            <input type="number" name="amount_max" class="form-control form-control-sm"
                                   value="{{ request('amount_max') }}"
                                   placeholder="VD: 50000000">
                        </div>

                        <div class="col-6 col-md-2">
                            <label class="form-label small">Sắp xếp theo</label>
                            <select name="sort_by" class="form-select form-select-sm">
                                <option value="created_at" {{ request('sort_by', 'created_at') == 'created_at' ? 'selected' : '' }}>Ngày tạo</option>
                                <option value="order_date" {{ request('sort_by') == 'order_date' ? 'selected' : '' }}>Ngày đặt</option>
                                <option value="total_amount" {{ request('sort_by') == 'total_amount' ? 'selected' : '' }}>Tổng tiền</option>
                                <option value="order_code" {{ request('sort_by') == 'order_code' ? 'selected' : '' }}>Mã đơn</option>
                                <option value="estimated_delivery" {{ request('sort_by') == 'estimated_delivery' ? 'selected' : '' }}>Dự kiến giao</option>
                            </select>
                        </div>

                        <div class="col-6 col-md-1">
                            <label class="form-label small">Chiều</label>
                            <select name="sort_dir" class="form-select form-select-sm">
                                <option value="desc" {{ request('sort_dir', 'desc') == 'desc' ? 'selected' : '' }}>Giảm</option>
                                <option value="asc" {{ request('sort_dir') == 'asc' ? 'selected' : '' }}>Tăng</option>
                            </select>
                        </div>

                        <div class="col-6 col-md-1">
                            <label class="form-label small">Dòng</label>
                            <select name="per_page" class="form-select form-select-sm">
                                <option value="10" {{ request('per_page') == '10' ? 'selected' : '' }}>10</option>
                                <option value="20" {{ request('per_page', 20) == '20' ? 'selected' : '' }}>20</option>
                                <option value="50" {{ request('per_page') == '50' ? 'selected' : '' }}>50</option>
                                <option value="100" {{ request('per_page') == '100' ? 'selected' : '' }}>100</option>
                            </select>
                        </div>
                    </div>

                    @if($hasFilter)
                        <div class="mt-3 small text-muted">
                            <i class="bi bi-info-circle"></i> Đang áp dụng bộ lọc. Kết quả và phân trang sẽ giữ nguyên điều kiện lọc.
                        </div>
                    @endif
                </div>
            </div>
        </form>

BLADE;

$view = substr($view, 0, $formStart) . $newForm . substr($view, $tableStart);

$view = str_replace(
    "{{ \$orders->links('pagination::bootstrap-5') }}",
    "{{ \$orders->appends(request()->query())->links('pagination::bootstrap-5') }}",
    $view
);

file_put_contents($viewFile, $view);

echo "Đã patch orders/index.blade.php\n";

echo "\nDONE\n";
