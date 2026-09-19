<?php

declare(strict_types=1);

namespace App\Services\SmartSearch;

use App\Contracts\Services\PageAccessServiceInterface;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Search thông minh dạng hội thoại, chỉ đọc dữ liệu CRM.
 *
 * Nguyên tắc bảo mật:
 * - Không dùng AI và không tự suy đoán số liệu.
 * - Chỉ tra cứu module mà user được phép truy cập.
 * - Giữ nguyên phạm vi dữ liệu theo role (sales chỉ dữ liệu của mình,
 *   nhân viên chỉ công việc/chấm công của mình, quản lý mới xem toàn đội).
 * - Khóa dữ liệu công ty về EGO Quốc Tế (company_id = 2).
 */
final class SmartSearchService
{
    private const COMPANY_ID = 2;

    /** @var array<string, bool> */
    private array $tableCache = [];

    /** @var array<string, bool> */
    private array $columnCache = [];

    public function __construct(
        private readonly PageAccessServiceInterface $pageAccess
    ) {}

    public function bootstrap(User $user, string $path): array
    {
        $modules = $this->allowedModules($user);

        return [
            'ok' => true,
            'available' => collect($modules)->contains(true),
            'title' => 'Tìm kiếm thông minh',
            'subtitle' => 'Tra cứu dữ liệu CRM theo đúng quyền tài khoản',
            'modules' => array_keys(array_filter($modules)),
            'suggestions' => $this->suggestions($user, $path, $modules),
            'scope' => $this->scopeLabel($user),
        ];
    }

    public function search(User $user, string $query, string $path): array
    {
        $normalized = $this->normalize($query);
        $modules = $this->allowedModules($user);

        if (! collect($modules)->contains(true)) {
            return $this->emptyResponse(
                $query,
                'Tài khoản chưa được cấp quyền tra cứu module nào.'
            );
        }

        if ($this->containsAny($normalized, [
            'doanh thu', 'da thu', 'tien da thu', 'cong no', 'con no',
            'dong tien', 'thu tien',
        ]) && $modules['orders']) {
            return $this->orderOverview($user, $query, $normalized);
        }

        if ($this->containsAny($normalized, [
            'cong viec', 'viec cua toi', 'viec qua han', 'task',
        ]) && $modules['tasks']) {
            return $this->taskOverview($user, $query, $normalized);
        }

        if ($this->containsAny($normalized, [
            'nhan su', 'cham cong', 'check in', 'checkin', 'di muon',
            'vang mat', 'chua cham cong',
        ]) && $modules['attendance']) {
            return $this->attendanceOverview($user, $query, $normalized);
        }

        if ($this->containsAny($normalized, [
            'ton kho', 'san pham', 'sku', 'het hang', 'sap het', 'kho ',
        ]) && $modules['products']) {
            return $this->productOverview($user, $query, $normalized);
        }

        if ($this->containsAny($normalized, [
            'cong trinh', 'bao tri', 'bao hanh', 'thi cong',
        ]) && $modules['sites']) {
            return $this->siteOverview($user, $query, $normalized);
        }

        if ($this->containsAny($normalized, [
            'de nghi thanh toan', 'dntt', 'phieu thanh toan',
        ]) && $modules['payment_requests']) {
            return $this->paymentRequestSearch($user, $query, $normalized);
        }

        if ($this->containsAny($normalized, [
            'don hang', 'don nao', 'ord', 'chua xuat kho', 'giao tre',
        ]) && $modules['orders']) {
            return $this->orderSearch($user, $query, $normalized);
        }

        if ($this->containsAny($normalized, [
            'khach hang', 'khach ', 'so dien thoai', 'mst',
        ]) && $modules['customers']) {
            return $this->customerSearch($user, $query);
        }

        return $this->globalSearch($user, $query, $normalized, $modules, $path);
    }

    /**
     * @return array<string, bool>
     */
    private function allowedModules(User $user): array
    {
        return [
            'orders' => $this->canPage($user, 'page.orders'),
            'customers' => $this->canPage($user, 'page.customers'),
            'products' => $this->canPage($user, 'page.products')
                || $this->canPage($user, 'page.warehouses'),
            'tasks' => $this->canPage($user, 'page.tasks'),
            'sites' => $this->canUseLegacyModule(
                $user,
                'page.sites',
                ['ky_thuat', 'accounting', 'admin', 'warehouse', 'kho', 'sales', 'management']
            ),
            'payment_requests' => $this->canPage($user, 'page.payment_requests'),
            'attendance' => $this->canManageHr($user)
                || Route::has('hr.attendance.my'),
        ];
    }

    private function canPage(User $user, string $permission): bool
    {
        try {
            return $this->pageAccess->canAccess($user, $permission);
        } catch (\Throwable) {
            return false;
        }
    }

    private function canUseLegacyModule(User $user, string $permission, array $roles): bool
    {
        if (! $this->canPage($user, $permission)) {
            return false;
        }

        if ($this->hasAnyRole($user, $roles)) {
            return true;
        }

        try {
            return $this->pageAccess->pageControlEnabled($user)
                && $user->can($permission);
        } catch (\Throwable) {
            return false;
        }
    }

    private function orderOverview(User $user, string $query, string $normalized): array
    {
        if (! $this->hasTable('crm_orders')) {
            return $this->emptyResponse($query, 'Chưa có bảng dữ liệu đơn hàng.');
        }

        [$from, $to, $periodLabel] = $this->resolvePeriod($normalized);
        $orderQuery = $this->baseOrderQuery($user)
            ->whereBetween('o.order_date', [$from->toDateString(), $to->toDateString()]);

        $paidSub = $this->hasTable('crm_payments')
            ? DB::table('crm_payments')
                ->selectRaw('order_id, COALESCE(SUM(amount), 0) AS paid_amount')
                ->groupBy('order_id')
            : null;

        if ($paidSub) {
            $orderQuery->leftJoinSub($paidSub, 'pay', 'pay.order_id', '=', 'o.id');
        }

        $total = (float) (clone $orderQuery)->sum('o.total_amount');
        $count = (int) (clone $orderQuery)->count('o.id');
        $paid = $paidSub
            ? (float) (clone $orderQuery)->sum(DB::raw('COALESCE(pay.paid_amount, 0)'))
            : 0.0;
        $debt = max(0.0, $total - $paid);

        $items = (clone $orderQuery)
            ->select([
                'o.id', 'o.order_code', 'o.order_date', 'o.total_amount',
                'o.current_department', 'o.shipping_status',
                'c.name as customer_name', 'u.name as creator_name',
            ])
            ->when($paidSub, fn (Builder $builder) => $builder->addSelect(
                DB::raw('COALESCE(pay.paid_amount, 0) AS paid_amount')
            ))
            ->orderByDesc('o.total_amount')
            ->limit(6)
            ->get()
            ->map(fn ($row) => [
                'type' => 'order',
                'icon' => 'bi-receipt',
                'title' => (string) ($row->order_code ?: 'Đơn hàng #'.$row->id),
                'subtitle' => (string) ($row->customer_name ?: 'Chưa có tên khách hàng'),
                'meta' => $this->money((float) $row->total_amount)
                    .' · '.$this->orderStatusLabel((string) ($row->current_department ?? '')),
                'badge' => isset($row->paid_amount)
                    && (float) $row->paid_amount >= (float) $row->total_amount
                        ? 'Đã thu đủ'
                        : 'Còn công nợ',
                'tone' => isset($row->paid_amount)
                    && (float) $row->paid_amount >= (float) $row->total_amount
                        ? 'success'
                        : 'warning',
                'url' => $this->routeUrl('orders.show', ['id' => $row->id], '/orders/'.$row->id),
            ])
            ->values()
            ->all();

        return $this->response(
            $query,
            'Tổng quan doanh thu '.$periodLabel,
            $count > 0
                ? 'Số liệu được lấy trực tiếp từ các đơn hàng mà tài khoản được phép xem.'
                : 'Không có đơn hàng trong khoảng thời gian này.',
            [
                $this->metric('Doanh thu', $this->money($total), 'bi-graph-up-arrow', 'primary'),
                $this->metric('Đã thu', $this->money($paid), 'bi-check2-circle', 'success'),
                $this->metric('Còn phải thu', $this->money($debt), 'bi-wallet2', $debt > 0 ? 'danger' : 'success'),
                $this->metric('Số đơn', number_format($count), 'bi-receipt-cutoff', 'info'),
            ],
            $items,
            'Đơn hàng · Thanh toán',
            $periodLabel
        );
    }

    private function orderSearch(User $user, string $query, string $normalized): array
    {
        $builder = $this->baseOrderQuery($user);

        if ($this->containsAny($normalized, ['chua xuat kho', 'chua giao'])) {
            $builder->where(function (Builder $scope): void {
                if ($this->hasColumn('crm_orders', 'inventory_issued')) {
                    $scope->where('o.inventory_issued', 0)
                        ->orWhereNull('o.inventory_issued');
                } elseif ($this->hasColumn('crm_orders', 'is_shipped')) {
                    $scope->where('o.is_shipped', 0)
                        ->orWhereNull('o.is_shipped');
                }
            });
        } elseif ($this->containsAny($normalized, ['giao tre', 'tre han'])) {
            if ($this->hasColumn('crm_orders', 'estimated_delivery')) {
                $builder->whereDate('o.estimated_delivery', '<', now()->toDateString())
                    ->where(function (Builder $scope): void {
                        $scope->whereNull('o.is_shipped')->orWhere('o.is_shipped', 0);
                    });
            }
        } else {
            $keyword = $this->extractKeyword($query, [
                'đơn hàng', 'đơn', 'tìm', 'kiểm tra', 'trạng thái',
            ]);
            if ($keyword !== '') {
                $builder->where(function (Builder $scope) use ($keyword): void {
                    $like = '%'.$keyword.'%';
                    $scope->where('o.order_code', 'like', $like)
                        ->orWhere('c.name', 'like', $like)
                        ->orWhere('c.phone', 'like', $like)
                        ->orWhere('o.receiver_name', 'like', $like)
                        ->orWhere('o.receiver_phone', 'like', $like);
                });
            }
        }

        $rows = $builder
            ->select([
                'o.id', 'o.order_code', 'o.order_date', 'o.total_amount',
                'o.current_department', 'o.shipping_status', 'o.estimated_delivery',
                'c.name as customer_name', 'u.name as creator_name',
            ])
            ->orderByDesc('o.order_date')
            ->limit(12)
            ->get();

        $items = $rows->map(fn ($row) => [
            'type' => 'order',
            'icon' => 'bi-receipt',
            'title' => (string) ($row->order_code ?: 'Đơn hàng #'.$row->id),
            'subtitle' => trim((string) ($row->customer_name ?: 'Chưa có khách hàng')),
            'meta' => $this->money((float) $row->total_amount)
                .' · '.$this->orderStatusLabel((string) ($row->current_department ?? '')),
            'badge' => $this->shippingLabel((string) ($row->shipping_status ?? '')),
            'tone' => in_array((string) ($row->shipping_status ?? ''), ['shipped', 'delivered'], true)
                ? 'success' : 'info',
            'url' => $this->routeUrl('orders.show', ['id' => $row->id], '/orders/'.$row->id),
        ])->values()->all();

        return $this->response(
            $query,
            'Kết quả đơn hàng',
            $rows->count().' đơn hàng phù hợp trong phạm vi tài khoản.',
            [$this->metric('Kết quả', number_format($rows->count()), 'bi-receipt', 'primary')],
            $items,
            'Đơn hàng',
            $this->scopeLabel($user)
        );
    }

    private function customerSearch(User $user, string $query): array
    {
        if (! $this->hasTable('crm_customers')) {
            return $this->emptyResponse($query, 'Chưa có dữ liệu khách hàng.');
        }

        $keyword = $this->extractKeyword($query, ['khách hàng', 'khách', 'tìm']);
        $builder = DB::table('crm_customers as c')
            ->leftJoin('users as u', 'u.id', '=', 'c.owner_id');

        if ($this->hasColumn('crm_customers', 'company_id')) {
            $builder->where(function (Builder $scope): void {
                $scope->whereNull('c.company_id')
                    ->orWhere('c.company_id', self::COMPANY_ID);
            });
        }

        if ($this->isSalesOnly($user)) {
            $builder->where('c.owner_id', $user->id);
        }

        if ($keyword !== '') {
            $like = '%'.$keyword.'%';
            $builder->where(function (Builder $scope) use ($like): void {
                $scope->where('c.name', 'like', $like)
                    ->orWhere('c.phone', 'like', $like)
                    ->orWhere('c.email', 'like', $like)
                    ->orWhere('c.billing_tax_code', 'like', $like)
                    ->orWhere('c.billing_company_name', 'like', $like);
            });
        }

        $rows = $builder
            ->select([
                'c.id', 'c.name', 'c.phone', 'c.email', 'c.customer_status',
                'c.billing_tax_code', 'u.name as owner_name',
            ])
            ->orderBy('c.name')
            ->limit(12)
            ->get();

        $items = $rows->map(fn ($row) => [
            'type' => 'customer',
            'icon' => 'bi-person-vcard',
            'title' => (string) ($row->name ?: 'Khách hàng #'.$row->id),
            'subtitle' => trim(implode(' · ', array_filter([
                (string) ($row->phone ?? ''),
                (string) ($row->email ?? ''),
            ]))),
            'meta' => $row->owner_name ? 'Phụ trách: '.$row->owner_name : 'Chưa có người phụ trách',
            'badge' => $this->customerStatusLabel((string) ($row->customer_status ?? '')),
            'tone' => 'info',
            'url' => $this->routeUrl('customers.show', ['customer' => $row->id], '/customers/'.$row->id),
        ])->values()->all();

        return $this->response(
            $query,
            'Kết quả khách hàng',
            $rows->count().' khách hàng phù hợp.',
            [$this->metric('Kết quả', number_format($rows->count()), 'bi-people', 'primary')],
            $items,
            'Khách hàng',
            $this->scopeLabel($user)
        );
    }

    private function productOverview(User $user, string $query, string $normalized): array
    {
        if (! $this->hasTable('crm_product_catalog')) {
            return $this->emptyResponse($query, 'Chưa có dữ liệu sản phẩm.');
        }

        $builder = DB::table('crm_product_catalog as p');
        $hasStock = $this->hasTable('crm_product_stock');

        if ($hasStock) {
            $stockSub = DB::table('crm_product_stock')
                ->selectRaw('product_id, COALESCE(SUM(qty), 0) AS stock_qty')
                ->where('company_id', self::COMPANY_ID)
                ->groupBy('product_id');
            $builder->leftJoinSub($stockSub, 's', 's.product_id', '=', 'p.id');
        }

        $keyword = $this->extractKeyword($query, [
            'sản phẩm', 'san pham', 'tồn kho', 'ton kho', 'kiểm tra',
            'sku', 'sắp hết', 'sap het', 'hết hàng', 'het hang',
        ]);

        if ($keyword !== '') {
            $like = '%'.$keyword.'%';
            $builder->where(function (Builder $scope) use ($like): void {
                $scope->where('p.name', 'like', $like)
                    ->orWhere('p.sku', 'like', $like)
                    ->orWhere('p.barcode', 'like', $like);
            });
        }

        if ($hasStock && $this->containsAny($normalized, ['sap het', 'sắp hết'])) {
            $builder->whereRaw('COALESCE(s.stock_qty, 0) BETWEEN 1 AND 5');
        } elseif ($hasStock && $this->containsAny($normalized, ['het hang', 'hết hàng', 'ton bang 0'])) {
            $builder->whereRaw('COALESCE(s.stock_qty, 0) <= 0');
        }

        $rows = $builder
            ->select([
                'p.id', 'p.name', 'p.sku', 'p.unit', 'p.price_retail', 'p.is_active',
            ])
            ->when($hasStock, fn (Builder $queryBuilder) => $queryBuilder->addSelect(
                DB::raw('COALESCE(s.stock_qty, 0) AS stock_qty')
            ))
            ->orderBy($hasStock ? 'stock_qty' : 'p.name')
            ->limit(15)
            ->get();

        $items = $rows->map(fn ($row) => [
            'type' => 'product',
            'icon' => 'bi-box-seam',
            'title' => (string) ($row->name ?: 'Sản phẩm #'.$row->id),
            'subtitle' => 'SKU: '.((string) ($row->sku ?: 'Chưa có SKU')),
            'meta' => $hasStock
                ? 'Tồn kho: '.number_format((float) ($row->stock_qty ?? 0)).' '.((string) ($row->unit ?: ''))
                : 'Chưa có dữ liệu tồn kho',
            'badge' => $hasStock && (float) ($row->stock_qty ?? 0) <= 0
                ? 'Hết hàng'
                : ($hasStock && (float) ($row->stock_qty ?? 0) <= 5 ? 'Sắp hết' : 'Còn hàng'),
            'tone' => $hasStock && (float) ($row->stock_qty ?? 0) <= 0
                ? 'danger'
                : ($hasStock && (float) ($row->stock_qty ?? 0) <= 5 ? 'warning' : 'success'),
            'url' => $this->routeUrl('products.show', ['product' => $row->id], '/products/'.$row->id),
        ])->values()->all();

        $totalQty = $hasStock ? (float) $rows->sum('stock_qty') : 0.0;

        return $this->response(
            $query,
            'Kết quả kho & sản phẩm',
            $rows->count().' sản phẩm phù hợp trong kho Quốc Tế EGO.',
            [
                $this->metric('Sản phẩm', number_format($rows->count()), 'bi-box-seam', 'primary'),
                $this->metric('Tổng tồn trong kết quả', number_format($totalQty), 'bi-boxes', 'success'),
            ],
            $items,
            'Danh mục sản phẩm · Tồn kho',
            'Công ty Quốc Tế EGO'
        );
    }

    private function taskOverview(User $user, string $query, string $normalized): array
    {
        if (! $this->hasTable('tasks')) {
            return $this->emptyResponse($query, 'Chưa có dữ liệu công việc.');
        }

        $builder = DB::table('tasks as t')
            ->leftJoin('users as a', 'a.id', '=', 't.assignee_id')
            ->leftJoin('users as r', 'r.id', '=', 't.requester_id');

        if (! $this->canManageTasks($user)) {
            $builder->where('t.assignee_id', $user->id);
        }

        if ($this->containsAny($normalized, ['qua han', 'quá hạn'])) {
            $builder->whereNotNull('t.due_at')
                ->where('t.due_at', '<', now())
                ->whereNotIn('t.status', ['approved', 'completed', 'done']);
        } elseif ($this->containsAny($normalized, ['cho duyet', 'chờ duyệt'])) {
            $builder->where('t.status', 'submitted');
        } else {
            $keyword = $this->extractKeyword($query, ['công việc', 'việc', 'task', 'tìm']);
            if ($keyword !== '') {
                $like = '%'.$keyword.'%';
                $builder->where(function (Builder $scope) use ($like): void {
                    $scope->where('t.title', 'like', $like)
                        ->orWhere('t.description', 'like', $like)
                        ->orWhere('a.name', 'like', $like);
                });
            }
        }

        $rows = $builder
            ->select([
                't.id', 't.title', 't.status', 't.priority', 't.due_at',
                'a.name as assignee_name', 'r.name as requester_name',
            ])
            ->orderByRaw('CASE WHEN t.due_at IS NULL THEN 1 ELSE 0 END')
            ->orderBy('t.due_at')
            ->limit(15)
            ->get();

        $overdue = $rows->filter(fn ($row) => $row->due_at
            && Carbon::parse($row->due_at)->isPast()
            && ! in_array((string) $row->status, ['approved', 'completed', 'done'], true))->count();

        $items = $rows->map(fn ($row) => [
            'type' => 'task',
            'icon' => 'bi-list-check',
            'title' => (string) ($row->title ?: 'Công việc #'.$row->id),
            'subtitle' => $row->assignee_name ? 'Phụ trách: '.$row->assignee_name : 'Chưa phân công',
            'meta' => $row->due_at
                ? 'Hạn: '.Carbon::parse($row->due_at)->format('d/m/Y H:i')
                : 'Không có hạn hoàn thành',
            'badge' => $this->taskStatusLabel((string) ($row->status ?? '')),
            'tone' => $row->due_at
                && Carbon::parse($row->due_at)->isPast()
                && ! in_array((string) $row->status, ['approved', 'completed', 'done'], true)
                    ? 'danger' : 'info',
            'url' => $this->routeUrl('tasks.show', ['task' => $row->id], '/chat/tasks/'.$row->id),
        ])->values()->all();

        return $this->response(
            $query,
            $this->canManageTasks($user) ? 'Tình hình công việc' : 'Công việc của tôi',
            $rows->count().' công việc phù hợp với phạm vi tài khoản.',
            [
                $this->metric('Công việc', number_format($rows->count()), 'bi-list-check', 'primary'),
                $this->metric('Quá hạn trong kết quả', number_format($overdue), 'bi-exclamation-triangle', $overdue > 0 ? 'danger' : 'success'),
            ],
            $items,
            'Công việc',
            $this->canManageTasks($user) ? 'Phạm vi quản lý' : 'Chỉ việc được giao cho bạn'
        );
    }

    private function attendanceOverview(User $user, string $query, string $normalized): array
    {
        if (! $this->hasTable('attendance_records')) {
            return $this->emptyResponse($query, 'Chưa có dữ liệu chấm công.');
        }

        $today = now()->toDateString();

        if (! $this->canManageHr($user)) {
            $row = DB::table('attendance_records')
                ->where('user_id', $user->id)
                ->whereDate('work_date', $today)
                ->first();

            $items = [[
                'type' => 'attendance',
                'icon' => 'bi-fingerprint',
                'title' => $row ? 'Đã có dữ liệu chấm công hôm nay' : 'Chưa chấm công hôm nay',
                'subtitle' => $row
                    ? 'Trạng thái: '.$this->attendanceStatusLabel((string) $row->status)
                    : 'Tài khoản chưa có bản ghi chấm công ngày '.now()->format('d/m/Y'),
                'meta' => $row && $row->check_in_at
                    ? 'Check-in: '.Carbon::parse($row->check_in_at)->format('H:i')
                    : 'Chưa ghi nhận giờ vào',
                'badge' => $row ? 'Của tôi' : 'Cần xử lý',
                'tone' => $row ? 'success' : 'warning',
                'url' => $this->routeUrl('hr.attendance.my', [], '/nhan-su/cham-cong-cua-toi'),
            ]];

            return $this->response(
                $query,
                'Chấm công của tôi',
                'Tài khoản thường chỉ được xem dữ liệu chấm công của chính mình.',
                [$this->metric('Hôm nay', $row ? 'Đã ghi nhận' : 'Chưa ghi nhận', 'bi-fingerprint', $row ? 'success' : 'warning')],
                $items,
                'Chấm công cá nhân',
                'Chỉ dữ liệu của bạn'
            );
        }

        $activeUsers = DB::table('users as u')
            ->when($this->hasColumn('users', 'is_active'), fn (Builder $builder) => $builder->where('u.is_active', 1));

        $headcount = (int) (clone $activeUsers)->count('u.id');
        $checkedIn = (int) DB::table('attendance_records as ar')
            ->whereDate('ar.work_date', $today)
            ->whereNotNull('ar.check_in_at')
            ->distinct('ar.user_id')
            ->count('ar.user_id');
        $late = (int) DB::table('attendance_records as ar')
            ->whereDate('ar.work_date', $today)
            ->where(function (Builder $scope): void {
                $scope->where('ar.late_minutes', '>', 0)->orWhere('ar.status', 'late');
            })
            ->count();
        $absent = max(0, $headcount - $checkedIn);

        $rowsBuilder = (clone $activeUsers)
            ->leftJoin('attendance_records as ar', function ($join) use ($today): void {
                $join->on('ar.user_id', '=', 'u.id')
                    ->whereDate('ar.work_date', '=', $today);
            });

        if ($this->hasTable('departments')) {
            $rowsBuilder->leftJoin('departments as d', 'd.id', '=', 'u.department_id');
        }

        $rows = $rowsBuilder
            ->when($this->containsAny($normalized, ['chua cham cong', 'chưa chấm công', 'vang mat', 'vắng mặt']),
                fn (Builder $builder) => $builder->whereNull('ar.id'))
            ->select(array_values(array_filter([
                'u.id', 'u.name', 'u.email',
                $this->hasTable('departments') ? 'd.name as department_name' : null,
                'ar.status', 'ar.check_in_at', 'ar.late_minutes',
            ])))
            ->orderByRaw('CASE WHEN ar.id IS NULL THEN 0 ELSE 1 END')
            ->orderBy('u.name')
            ->limit(15)
            ->get();

        $items = $rows->map(fn ($row) => [
            'type' => 'employee',
            'icon' => 'bi-person-badge',
            'title' => (string) ($row->name ?: 'Nhân sự #'.$row->id),
            'subtitle' => (string) ($row->department_name ?: 'Chưa có phòng ban'),
            'meta' => $row->check_in_at
                ? 'Check-in: '.Carbon::parse($row->check_in_at)->format('H:i')
                : 'Chưa check-in hôm nay',
            'badge' => $row->status ? $this->attendanceStatusLabel((string) $row->status) : 'Chưa chấm công',
            'tone' => ! $row->check_in_at ? 'warning' : ((int) ($row->late_minutes ?? 0) > 0 ? 'danger' : 'success'),
            'url' => $this->routeUrl('hr.employees.show', ['employee' => $row->id], '/nhan-su/employees/'.$row->id),
        ])->values()->all();

        return $this->response(
            $query,
            'Tình hình nhân sự hôm nay',
            'Chỉ người có quyền Nhân sự/Ban quản lý mới xem được dữ liệu toàn công ty.',
            [
                $this->metric('Nhân sự hoạt động', number_format($headcount), 'bi-people', 'primary'),
                $this->metric('Đã check-in', number_format($checkedIn), 'bi-person-check', 'success'),
                $this->metric('Chưa check-in', number_format($absent), 'bi-person-x', $absent > 0 ? 'warning' : 'success'),
                $this->metric('Đi muộn', number_format($late), 'bi-clock-history', $late > 0 ? 'danger' : 'success'),
            ],
            $items,
            'Nhân sự · Chấm công',
            'Dữ liệu hôm nay'
        );
    }

    private function siteOverview(User $user, string $query, string $normalized): array
    {
        if (! $this->hasTable('sites')) {
            return $this->emptyResponse($query, 'Chưa có dữ liệu công trình.');
        }

        $builder = DB::table('sites as s')
            ->where('s.company_id', self::COMPANY_ID);

        if ($this->isSalesOnly($user) && $this->hasColumn('sites', 'created_by')) {
            $builder->where('s.created_by', $user->id);
        }

        if ($this->containsAny($normalized, ['cham tien do', 'chậm tiến độ', 'qua han', 'quá hạn'])) {
            $builder->whereNotIn('s.status', ['done', 'completed'])
                ->where(function (Builder $scope): void {
                    $scope->whereNotNull('s.deployment_started_at')
                        ->whereDate('s.deployment_started_at', '<', now()->subDays(30)->toDateString());
                });
        } else {
            $keyword = $this->extractKeyword($query, ['công trình', 'cong trinh', 'tìm', 'thi công', 'bảo trì', 'bảo hành']);
            if ($keyword !== '') {
                $like = '%'.$keyword.'%';
                $builder->where(function (Builder $scope) use ($like): void {
                    $scope->where('s.name', 'like', $like)
                        ->orWhere('s.address', 'like', $like)
                        ->orWhere('s.contact_name', 'like', $like)
                        ->orWhere('s.contact_phone', 'like', $like)
                        ->orWhere('s.quote_customer_company', 'like', $like);
                });
            }
        }

        $rows = $builder
            ->select([
                's.id', 's.name', 's.status', 's.address', 's.contact_name',
                's.contact_phone', 's.contract_amount', 's.stage',
            ])
            ->orderByDesc('s.id')
            ->limit(12)
            ->get();

        $items = $rows->map(fn ($row) => [
            'type' => 'site',
            'icon' => 'bi-building-gear',
            'title' => (string) ($row->name ?: 'Công trình #'.$row->id),
            'subtitle' => (string) ($row->address ?: ($row->contact_name ?: 'Chưa có địa chỉ')),
            'meta' => $row->contract_amount
                ? 'Giá trị: '.$this->money((float) $row->contract_amount)
                : 'Chưa ghi nhận giá trị hợp đồng',
            'badge' => $this->siteStatusLabel((string) ($row->status ?? '')),
            'tone' => (string) ($row->status ?? '') === 'done' ? 'success' : 'info',
            'url' => $this->routeUrl('sites.show', ['id' => $row->id], '/cong-trinh/'.$row->id),
        ])->values()->all();

        return $this->response(
            $query,
            'Kết quả công trình',
            $rows->count().' công trình phù hợp trong phạm vi tài khoản.',
            [$this->metric('Công trình', number_format($rows->count()), 'bi-building-gear', 'primary')],
            $items,
            'Công trình',
            $this->scopeLabel($user)
        );
    }

    private function paymentRequestSearch(User $user, string $query, string $normalized): array
    {
        if (! $this->hasTable('payment_requests')) {
            return $this->emptyResponse($query, 'Chưa có dữ liệu đề nghị thanh toán.');
        }

        $builder = DB::table('payment_requests as pr')
            ->leftJoin('users as u', 'u.id', '=', 'pr.created_by')
            ->where('pr.company_id', self::COMPANY_ID);

        if (! $this->canViewAllPaymentRequests($user)) {
            $builder->where('pr.created_by', $user->id);
        }

        $keyword = $this->extractKeyword($query, [
            'đề nghị thanh toán', 'de nghi thanh toan', 'dntt', 'phiếu thanh toán', 'phieu thanh toan', 'tìm',
        ]);
        if ($keyword !== '') {
            $like = '%'.$keyword.'%';
            $builder->where(function (Builder $scope) use ($like): void {
                $scope->where('pr.code', 'like', $like)
                    ->orWhere('pr.receiver_name', 'like', $like)
                    ->orWhere('pr.reason', 'like', $like)
                    ->orWhere('pr.payment_content', 'like', $like);
            });
        }

        if ($this->containsAny($normalized, ['cho duyet', 'chờ duyệt'])) {
            $builder->whereIn('pr.status', ['submitted', 'admin_approved']);
        }

        $rows = $builder
            ->select([
                'pr.id', 'pr.code', 'pr.receiver_name', 'pr.reason', 'pr.amount',
                'pr.status', 'pr.payment_due_date', 'u.name as creator_name',
            ])
            ->orderByDesc('pr.id')
            ->limit(12)
            ->get();

        $items = $rows->map(fn ($row) => [
            'type' => 'payment_request',
            'icon' => 'bi-cash-stack',
            'title' => (string) ($row->code ?: 'ĐNTT #'.$row->id),
            'subtitle' => (string) ($row->receiver_name ?: ($row->reason ?: 'Chưa có nội dung')),
            'meta' => $this->money((float) $row->amount)
                .($row->payment_due_date ? ' · Hạn '.Carbon::parse($row->payment_due_date)->format('d/m/Y') : ''),
            'badge' => $this->paymentRequestStatusLabel((string) ($row->status ?? '')),
            'tone' => str_contains((string) ($row->status ?? ''), 'rejected') ? 'danger' : 'info',
            'url' => $this->routeUrl('payment_requests.show', ['id' => $row->id], '/payment-requests/'.$row->id),
        ])->values()->all();

        return $this->response(
            $query,
            'Kết quả đề nghị thanh toán',
            $rows->count().' phiếu phù hợp.',
            [$this->metric('Kết quả', number_format($rows->count()), 'bi-cash-stack', 'primary')],
            $items,
            'Đề nghị thanh toán',
            $this->canViewAllPaymentRequests($user) ? 'Phạm vi được cấp quyền' : 'Chỉ phiếu do bạn tạo'
        );
    }

    private function globalSearch(User $user, string $query, string $normalized, array $modules, string $path): array
    {
        $groups = [];

        if ($modules['orders']) {
            $groups[] = $this->compactOrderResults($user, $query);
        }
        if ($modules['customers']) {
            $groups[] = $this->compactCustomerResults($user, $query);
        }
        if ($modules['products']) {
            $groups[] = $this->compactProductResults($query);
        }
        if ($modules['tasks']) {
            $groups[] = $this->compactTaskResults($user, $query);
        }
        if ($modules['sites']) {
            $groups[] = $this->compactSiteResults($user, $query);
        }
        if ($modules['payment_requests']) {
            $groups[] = $this->compactPaymentRequestResults($user, $query);
        }

        $items = collect($groups)
            ->filter()
            ->flatten(1)
            ->take(20)
            ->values()
            ->all();

        return $this->response(
            $query,
            'Kết quả tìm kiếm',
            count($items) > 0
                ? count($items).' kết quả phù hợp từ các module bạn được phép xem.'
                : 'Không tìm thấy dữ liệu phù hợp. Hãy thử mã đơn, tên khách hàng, SKU hoặc tên công việc.',
            [$this->metric('Kết quả', number_format(count($items)), 'bi-search', count($items) > 0 ? 'primary' : 'warning')],
            $items,
            'Tìm kiếm đa module',
            $this->scopeLabel($user)
        );
    }

    private function compactOrderResults(User $user, string $keyword): array
    {
        if (! $this->hasTable('crm_orders')) {
            return [];
        }

        $like = '%'.$keyword.'%';
        return $this->baseOrderQuery($user)
            ->where(function (Builder $scope) use ($like): void {
                $scope->where('o.order_code', 'like', $like)
                    ->orWhere('c.name', 'like', $like)
                    ->orWhere('c.phone', 'like', $like);
            })
            ->select(['o.id', 'o.order_code', 'o.total_amount', 'c.name as customer_name'])
            ->limit(5)
            ->get()
            ->map(fn ($row) => [
                'type' => 'order', 'icon' => 'bi-receipt',
                'title' => (string) ($row->order_code ?: 'Đơn #'.$row->id),
                'subtitle' => (string) ($row->customer_name ?: 'Đơn hàng'),
                'meta' => $this->money((float) $row->total_amount),
                'badge' => 'Đơn hàng', 'tone' => 'info',
                'url' => $this->routeUrl('orders.show', ['id' => $row->id], '/orders/'.$row->id),
            ])->all();
    }

    private function compactCustomerResults(User $user, string $keyword): array
    {
        if (! $this->hasTable('crm_customers')) {
            return [];
        }

        $like = '%'.$keyword.'%';
        $builder = DB::table('crm_customers as c')
            ->where(function (Builder $scope) use ($like): void {
                $scope->where('c.name', 'like', $like)
                    ->orWhere('c.phone', 'like', $like)
                    ->orWhere('c.email', 'like', $like)
                    ->orWhere('c.billing_tax_code', 'like', $like);
            });
        if ($this->isSalesOnly($user)) {
            $builder->where('c.owner_id', $user->id);
        }

        return $builder->select(['c.id', 'c.name', 'c.phone'])
            ->limit(5)->get()->map(fn ($row) => [
                'type' => 'customer', 'icon' => 'bi-person-vcard',
                'title' => (string) $row->name,
                'subtitle' => (string) ($row->phone ?: 'Khách hàng'),
                'meta' => 'Hồ sơ khách hàng', 'badge' => 'Khách hàng', 'tone' => 'info',
                'url' => $this->routeUrl('customers.show', ['customer' => $row->id], '/customers/'.$row->id),
            ])->all();
    }

    private function compactProductResults(string $keyword): array
    {
        if (! $this->hasTable('crm_product_catalog')) {
            return [];
        }
        $like = '%'.$keyword.'%';
        return DB::table('crm_product_catalog as p')
            ->where(function (Builder $scope) use ($like): void {
                $scope->where('p.name', 'like', $like)
                    ->orWhere('p.sku', 'like', $like)
                    ->orWhere('p.barcode', 'like', $like);
            })
            ->select(['p.id', 'p.name', 'p.sku'])
            ->limit(5)->get()->map(fn ($row) => [
                'type' => 'product', 'icon' => 'bi-box-seam',
                'title' => (string) $row->name,
                'subtitle' => 'SKU: '.((string) ($row->sku ?: '—')),
                'meta' => 'Hồ sơ sản phẩm', 'badge' => 'Kho', 'tone' => 'success',
                'url' => $this->routeUrl('products.show', ['product' => $row->id], '/products/'.$row->id),
            ])->all();
    }

    private function compactTaskResults(User $user, string $keyword): array
    {
        if (! $this->hasTable('tasks')) {
            return [];
        }
        $like = '%'.$keyword.'%';
        $builder = DB::table('tasks as t')
            ->where(function (Builder $scope) use ($like): void {
                $scope->where('t.title', 'like', $like)
                    ->orWhere('t.description', 'like', $like);
            });
        if (! $this->canManageTasks($user)) {
            $builder->where('t.assignee_id', $user->id);
        }
        return $builder->select(['t.id', 't.title', 't.status'])
            ->limit(5)->get()->map(fn ($row) => [
                'type' => 'task', 'icon' => 'bi-list-check',
                'title' => (string) $row->title,
                'subtitle' => $this->taskStatusLabel((string) $row->status),
                'meta' => 'Công việc', 'badge' => 'Công việc', 'tone' => 'warning',
                'url' => $this->routeUrl('tasks.show', ['task' => $row->id], '/chat/tasks/'.$row->id),
            ])->all();
    }

    private function compactSiteResults(User $user, string $keyword): array
    {
        if (! $this->hasTable('sites')) {
            return [];
        }
        $like = '%'.$keyword.'%';
        $builder = DB::table('sites as s')
            ->where('s.company_id', self::COMPANY_ID)
            ->where(function (Builder $scope) use ($like): void {
                $scope->where('s.name', 'like', $like)
                    ->orWhere('s.address', 'like', $like)
                    ->orWhere('s.contact_name', 'like', $like);
            });
        if ($this->isSalesOnly($user) && $this->hasColumn('sites', 'created_by')) {
            $builder->where('s.created_by', $user->id);
        }
        return $builder->select(['s.id', 's.name', 's.status'])
            ->limit(5)->get()->map(fn ($row) => [
                'type' => 'site', 'icon' => 'bi-building-gear',
                'title' => (string) $row->name,
                'subtitle' => $this->siteStatusLabel((string) $row->status),
                'meta' => 'Công trình', 'badge' => 'Công trình', 'tone' => 'info',
                'url' => $this->routeUrl('sites.show', ['id' => $row->id], '/cong-trinh/'.$row->id),
            ])->all();
    }

    private function compactPaymentRequestResults(User $user, string $keyword): array
    {
        if (! $this->hasTable('payment_requests')) {
            return [];
        }
        $like = '%'.$keyword.'%';
        $builder = DB::table('payment_requests as pr')
            ->where('pr.company_id', self::COMPANY_ID)
            ->where(function (Builder $scope) use ($like): void {
                $scope->where('pr.code', 'like', $like)
                    ->orWhere('pr.receiver_name', 'like', $like)
                    ->orWhere('pr.reason', 'like', $like);
            });
        if (! $this->canViewAllPaymentRequests($user)) {
            $builder->where('pr.created_by', $user->id);
        }
        return $builder->select(['pr.id', 'pr.code', 'pr.receiver_name', 'pr.amount'])
            ->limit(5)->get()->map(fn ($row) => [
                'type' => 'payment_request', 'icon' => 'bi-cash-stack',
                'title' => (string) ($row->code ?: 'ĐNTT #'.$row->id),
                'subtitle' => (string) ($row->receiver_name ?: 'Đề nghị thanh toán'),
                'meta' => $this->money((float) $row->amount),
                'badge' => 'ĐNTT', 'tone' => 'info',
                'url' => $this->routeUrl('payment_requests.show', ['id' => $row->id], '/payment-requests/'.$row->id),
            ])->all();
    }

    private function baseOrderQuery(User $user): Builder
    {
        $builder = DB::table('crm_orders as o')
            ->leftJoin('crm_leads as l', 'l.id', '=', 'o.lead_id')
            ->leftJoin('crm_customers as c', 'c.id', '=', 'l.customer_id')
            ->leftJoin('users as u', 'u.id', '=', 'o.created_by')
            ->where('o.company_id', self::COMPANY_ID);

        if ($this->hasColumn('crm_orders', 'deleted_at')) {
            $builder->whereNull('o.deleted_at');
        }

        if ($this->isSalesOnly($user)) {
            $builder->where('o.created_by', $user->id);
        }

        return $builder;
    }

    private function suggestions(User $user, string $path, array $modules): array
    {
        $suggestions = [];
        $path = '/'.ltrim($path, '/');

        if (str_contains($path, '/orders') && $modules['orders']) {
            $suggestions = ['Đơn chưa xuất kho', 'Doanh thu tháng này', 'Đơn còn công nợ'];
        } elseif ((str_contains($path, '/products') || str_contains($path, '/warehouses')) && $modules['products']) {
            $suggestions = ['Sản phẩm sắp hết hàng', 'Sản phẩm tồn kho bằng 0', 'Tìm SKU'];
        } elseif (str_contains($path, '/chat/tasks') && $modules['tasks']) {
            $suggestions = ['Công việc quá hạn', 'Công việc chờ duyệt', 'Công việc của tôi'];
        } elseif (str_contains($path, '/nhan-su') && $modules['attendance']) {
            $suggestions = $this->canManageHr($user)
                ? ['Ai chưa chấm công hôm nay?', 'Nhân sự đi muộn hôm nay', 'Tình hình nhân sự hôm nay']
                : ['Chấm công của tôi hôm nay'];
        }

        $fallback = [];
        if ($modules['orders']) {
            $fallback[] = 'Doanh thu tháng này';
            $fallback[] = 'Đơn chưa xuất kho';
        }
        if ($modules['products']) {
            $fallback[] = 'Sản phẩm sắp hết hàng';
        }
        if ($modules['tasks']) {
            $fallback[] = $this->canManageTasks($user) ? 'Công việc quá hạn' : 'Công việc của tôi';
        }
        if ($modules['attendance']) {
            $fallback[] = $this->canManageHr($user) ? 'Ai chưa chấm công hôm nay?' : 'Chấm công của tôi hôm nay';
        }
        if ($modules['sites']) {
            $fallback[] = 'Công trình đang thi công';
        }

        return collect(array_merge($suggestions, $fallback))
            ->unique()
            ->take(8)
            ->values()
            ->all();
    }

    private function response(
        string $query,
        string $title,
        string $summary,
        array $metrics,
        array $items,
        string $source,
        string $scope
    ): array {
        return [
            'ok' => true,
            'query' => $query,
            'title' => $title,
            'summary' => $summary,
            'metrics' => $metrics,
            'items' => $items,
            'source' => $source,
            'scope' => $scope,
            'updated_at' => now()->format('H:i, d/m/Y'),
        ];
    }

    private function emptyResponse(string $query, string $message): array
    {
        return $this->response(
            $query,
            'Chưa có kết quả',
            $message,
            [],
            [],
            'CRM EGO Solar',
            'Theo quyền tài khoản'
        );
    }

    private function metric(string $label, string $value, string $icon, string $tone): array
    {
        return compact('label', 'value', 'icon', 'tone');
    }

    private function resolvePeriod(string $normalized): array
    {
        $now = now();

        if ($this->containsAny($normalized, ['hom nay', 'hôm nay'])) {
            return [$now->copy()->startOfDay(), $now->copy()->endOfDay(), 'hôm nay'];
        }
        if ($this->containsAny($normalized, ['tuan nay', 'tuần này'])) {
            return [$now->copy()->startOfWeek(), $now->copy()->endOfWeek(), 'tuần này'];
        }
        if ($this->containsAny($normalized, ['thang truoc', 'tháng trước'])) {
            $previous = $now->copy()->subMonthNoOverflow();
            return [$previous->startOfMonth(), $previous->copy()->endOfMonth(), 'tháng trước'];
        }
        if ($this->containsAny($normalized, ['quy nay', 'quý này'])) {
            return [$now->copy()->startOfQuarter(), $now->copy()->endOfQuarter(), 'quý này'];
        }
        if ($this->containsAny($normalized, ['nam nay', 'năm nay'])) {
            return [$now->copy()->startOfYear(), $now->copy()->endOfYear(), 'năm nay'];
        }

        return [$now->copy()->startOfMonth(), $now->copy()->endOfMonth(), 'tháng này'];
    }

    private function extractKeyword(string $query, array $phrases): string
    {
        $keyword = trim($query);
        foreach ($phrases as $phrase) {
            $keyword = preg_replace('/'.preg_quote($phrase, '/').'/iu', ' ', $keyword) ?? $keyword;
        }
        $keyword = preg_replace('/\s+/u', ' ', $keyword) ?? $keyword;
        return trim($keyword, " \t\n\r\0\x0B:,-?");
    }

    private function normalize(string $value): string
    {
        return strtolower(Str::ascii(trim(preg_replace('/\s+/u', ' ', $value) ?? $value)));
    }

    private function containsAny(string $haystack, array $needles): bool
    {
        foreach ($needles as $needle) {
            if (str_contains($haystack, $this->normalize($needle))) {
                return true;
            }
        }
        return false;
    }

    private function hasAnyRole(User $user, array $roles): bool
    {
        try {
            return $user->hasAnyRole($roles);
        } catch (\Throwable) {
            return false;
        }
    }

    private function isSalesOnly(User $user): bool
    {
        return $this->hasAnyRole($user, ['sales', 'sale', 'sales_staff'])
            && ! $this->hasAnyRole($user, [
                'admin', 'management', 'manager', 'director', 'accounting',
                'sales_manager', 'warehouse', 'kho',
            ]);
    }

    private function canManageTasks(User $user): bool
    {
        return $this->hasAnyRole($user, [
            'admin', 'management', 'manager', 'director', 'general_director',
            'ban_giam_doc', 'giam_doc', 'sales_manager', 'marketing_manager',
            'technical_manager', 'accounting',
        ]);
    }

    private function canManageHr(User $user): bool
    {
        return $this->canPage($user, 'page.hr')
            && $this->hasAnyRole($user, [
                'admin', 'management', 'manager', 'director', 'general_director',
                'ban_giam_doc', 'giam_doc', 'hr', 'human_resource',
                'nhan_su', 'hanh_chinh_nhan_su',
            ]);
    }

    private function canViewAllPaymentRequests(User $user): bool
    {
        return $this->hasAnyRole($user, [
            'admin', 'management', 'manager', 'director', 'accounting',
        ]);
    }

    private function scopeLabel(User $user): string
    {
        if ($this->hasAnyRole($user, ['admin', 'management', 'manager', 'director'])) {
            return 'Phạm vi quản lý · Công ty Quốc Tế EGO';
        }
        if ($this->isSalesOnly($user)) {
            return 'Chỉ dữ liệu Sales của bạn · Công ty Quốc Tế EGO';
        }
        return 'Theo quyền role hiện tại · Công ty Quốc Tế EGO';
    }

    private function routeUrl(string $name, array $parameters, string $fallback): string
    {
        try {
            return Route::has($name) ? route($name, $parameters) : url($fallback);
        } catch (\Throwable) {
            return url($fallback);
        }
    }

    private function hasTable(string $table): bool
    {
        return $this->tableCache[$table] ??= Schema::hasTable($table);
    }

    private function hasColumn(string $table, string $column): bool
    {
        $key = $table.'.'.$column;
        return $this->columnCache[$key] ??= ($this->hasTable($table) && Schema::hasColumn($table, $column));
    }

    private function money(float $value): string
    {
        return number_format($value, 0, ',', '.').' đ';
    }

    private function orderStatusLabel(string $status): string
    {
        return match ($status) {
            'sales' => 'Sales xử lý',
            'sales_manager', 'duyet1' => 'Chờ quản lý Sales',
            'accounting', 'ketoan' => 'Kế toán xử lý',
            'management', 'director', 'duyet2' => 'Chờ Ban Giám đốc',
            'warehouse', 'kho' => 'Kho xử lý',
            'completed' => 'Hoàn tất',
            'cancelled', 'canceled', 'da_huy', 'huy' => 'Đã hủy',
            default => $status !== '' ? Str::headline($status) : 'Chưa xác định',
        };
    }

    private function shippingLabel(string $status): string
    {
        return match ($status) {
            'shipped' => 'Đã giao',
            'delivered' => 'Đã nhận',
            'processing' => 'Đang xử lý',
            default => 'Chưa giao',
        };
    }

    private function customerStatusLabel(string $status): string
    {
        return match ($status) {
            'member' => 'Khách thành viên',
            'lead' => 'Khách tiềm năng',
            default => $status !== '' ? Str::headline($status) : 'Khách hàng',
        };
    }

    private function taskStatusLabel(string $status): string
    {
        return match ($status) {
            'new' => 'Mới giao',
            'in_progress' => 'Đang làm',
            'submitted' => 'Chờ duyệt',
            'revision' => 'Cần sửa',
            'rejected' => 'Bị từ chối',
            'approved' => 'Đã duyệt',
            default => $status !== '' ? Str::headline($status) : 'Chưa xác định',
        };
    }

    private function attendanceStatusLabel(string $status): string
    {
        return match ($status) {
            'checked_in' => 'Đã check-in',
            'completed' => 'Đã hoàn tất',
            'late' => 'Đi muộn',
            'early_leave' => 'Về sớm',
            'absent' => 'Vắng mặt',
            default => $status !== '' ? Str::headline($status) : 'Chưa xác định',
        };
    }

    private function siteStatusLabel(string $status): string
    {
        return match ($status) {
            'planning' => 'Đang chuẩn bị',
            'in_progress', 'deploying' => 'Đang thi công',
            'done', 'completed' => 'Hoàn tất',
            'warranty' => 'Bảo hành',
            default => $status !== '' ? Str::headline($status) : 'Chưa xác định',
        };
    }

    private function paymentRequestStatusLabel(string $status): string
    {
        return match ($status) {
            'draft' => 'Bản nháp',
            'submitted' => 'Chờ duyệt',
            'admin_approved' => 'Admin đã duyệt',
            'accounting_approved' => 'Kế toán đã duyệt',
            'admin_rejected', 'accounting_rejected' => 'Bị từ chối',
            default => $status !== '' ? Str::headline($status) : 'Chưa xác định',
        };
    }
}
