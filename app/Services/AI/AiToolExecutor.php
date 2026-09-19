<?php

declare(strict_types=1);

namespace App\Services\AI;

use App\Models\User;
use App\Support\EgoCompanyLock;
use Carbon\Carbon;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

final class AiToolExecutor
{
    /** @var array<string, bool> */
    private array $tableCache = [];

    /** @var array<string, bool> */
    private array $columnCache = [];

    public function __construct(private readonly AiAccessService $access) {}

    /**
     * @param array{from:Carbon,to:Carbon,label:string,explicit:bool,key:string} $period
     * @return array<string, mixed>
     */
    public function execute(string $tool, User $user, string $query, array $period, string $scope): array
    {
        return match ($tool) {
            'orders' => $this->orders($user, $query, $period, $scope),
            'customers' => $this->customers($user, $query, $period, $scope),
            'inventory' => $this->inventory($user, $query, $period, $scope),
            'tasks' => $this->tasks($user, $query, $period, $scope),
            'sites' => $this->sites($user, $query, $period, $scope),
            'payment_requests' => $this->paymentRequests($user, $query, $period, $scope),
            'attendance' => $this->attendance($user, $query, $period, $scope),
            'marketing' => $this->marketing($user, $query, $period, $scope),
            'hr' => $this->hr($user, $query, $period, $scope),
            default => $this->empty($tool, 'Không có công cụ CRM phù hợp.', $scope, $period),
        };
    }

    /** @param array<string, mixed> $period */
    private function orders(User $user, string $query, array $period, string $scope): array
    {
        if (! $this->hasTable('crm_orders')) {
            return $this->empty('orders', 'Chưa có bảng dữ liệu đơn hàng.', $scope, $period);
        }

        $builder = DB::table('crm_orders as o')
            ->leftJoin('crm_leads as l', 'l.id', '=', 'o.lead_id')
            ->leftJoin('crm_customers as c', 'c.id', '=', 'l.customer_id')
            ->leftJoin('users as u', 'u.id', '=', 'o.created_by');

        $this->applyCompany($builder, 'crm_orders', 'o');
        $this->applyUserScope($builder, $user, $scope, [
            'self' => ['o.created_by', 'l.assigned_to', 'c.owner_id'],
            'department' => ['o.created_by', 'l.assigned_to', 'c.owner_id'],
        ]);

        if ($this->hasColumn('crm_orders', 'deleted_at')) {
            $builder->whereNull('o.deleted_at');
        }

        if ($this->hasColumn('crm_orders', 'order_date')) {
            $builder->whereBetween('o.order_date', [
                $period['from']->toDateString(),
                $period['to']->toDateString(),
            ]);
        }

        $normalized = $this->normalize($query);
        $keyword = $this->keyword($query, [
            'đơn hàng', 'don hang', 'doanh thu', 'công nợ', 'cong no', 'đã thu', 'da thu',
            'tháng này', 'thang nay', 'tháng trước', 'thang truoc', 'hôm nay', 'hom nay',
            'hôm qua', 'hom qua', 'tóm tắt', 'tom tat', 'kiểm tra', 'kiem tra',
        ]);

        if ($keyword !== '') {
            $like = '%'.$keyword.'%';
            $builder->where(function (Builder $q) use ($like): void {
                $q->where('o.order_code', 'like', $like)
                    ->orWhere('c.name', 'like', $like)
                    ->orWhere('c.phone', 'like', $like)
                    ->orWhere('o.receiver_name', 'like', $like);
            });
        }

        if (str_contains($normalized, 'chua xuat kho') || str_contains($normalized, 'chua giao')) {
            $builder->where(function (Builder $q): void {
                $q->where('o.inventory_issued', 0)->orWhereNull('o.inventory_issued');
            });
        }

        if (str_contains($normalized, 'giao tre') && $this->hasColumn('crm_orders', 'estimated_delivery')) {
            $builder->whereDate('o.estimated_delivery', '<', now()->toDateString())
                ->whereNotIn('o.shipping_status', ['shipped', 'delivered']);
        }

        $paidSub = $this->hasTable('crm_payments')
            ? DB::table('crm_payments')->selectRaw('order_id, COALESCE(SUM(amount), 0) paid_amount')->groupBy('order_id')
            : null;

        if ($paidSub) {
            $builder->leftJoinSub($paidSub, 'pay', 'pay.order_id', '=', 'o.id');
        }

        $total = (float) (clone $builder)->sum('o.total_amount');
        $count = (int) (clone $builder)->distinct()->count('o.id');
        $paid = $paidSub ? (float) (clone $builder)->sum(DB::raw('COALESCE(pay.paid_amount, 0)')) : 0.0;
        $debt = max(0, $total - $paid);

        $rows = (clone $builder)
            ->select([
                'o.id', 'o.order_code', 'o.order_date', 'o.total_amount', 'o.current_department',
                'o.shipping_status', 'o.inventory_issued', 'c.name as customer_name', 'u.name as creator_name',
            ])
            ->when($paidSub, fn (Builder $q) => $q->addSelect(DB::raw('COALESCE(pay.paid_amount, 0) AS paid_amount')))
            ->orderByDesc('o.order_date')
            ->orderByDesc('o.id')
            ->limit(12)
            ->get();

        $items = $rows->map(function ($row): array {
            $paidAmount = (float) ($row->paid_amount ?? 0);
            $totalAmount = (float) ($row->total_amount ?? 0);
            return [
                'type' => 'order',
                'icon' => 'bi-receipt',
                'title' => (string) ($row->order_code ?: 'Đơn hàng #'.$row->id),
                'subtitle' => (string) ($row->customer_name ?: 'Chưa có tên khách'),
                'meta' => $this->money($totalAmount).' · Đã thu '.$this->money($paidAmount),
                'badge' => $paidAmount >= $totalAmount && $totalAmount > 0 ? 'Đã thu đủ' : 'Còn '.$this->money(max(0, $totalAmount - $paidAmount)),
                'tone' => $paidAmount >= $totalAmount && $totalAmount > 0 ? 'success' : 'warning',
                'url' => $this->routeUrl('orders.show', ['id' => $row->id], '/orders/'.$row->id),
            ];
        })->values()->all();

        return $this->context(
            'orders',
            'Đơn hàng & công nợ '.$period['label'],
            $count > 0 ? "Có {$count} đơn trong phạm vi được phép xem." : 'Không có đơn hàng phù hợp trong phạm vi được phép xem.',
            [
                $this->metric('Doanh thu', $this->money($total), 'bi-graph-up-arrow', 'primary'),
                $this->metric('Đã thu', $this->money($paid), 'bi-check-circle', 'success'),
                $this->metric('Còn phải thu', $this->money($debt), 'bi-wallet2', $debt > 0 ? 'danger' : 'success'),
                $this->metric('Số đơn', number_format($count, 0, ',', '.'), 'bi-receipt', 'info'),
            ],
            $items,
            $scope,
            $period
        );
    }

    /** @param array<string, mixed> $period */
    private function customers(User $user, string $query, array $period, string $scope): array
    {
        if (! $this->hasTable('crm_customers')) {
            return $this->empty('customers', 'Chưa có dữ liệu khách hàng.', $scope, $period);
        }

        $builder = DB::table('crm_customers as c')->leftJoin('users as u', 'u.id', '=', 'c.owner_id');
        $this->applyCompany($builder, 'crm_customers', 'c', true);
        $this->applyUserScope($builder, $user, $scope, [
            'self' => ['c.owner_id'],
            'department' => ['c.owner_id'],
        ]);

        $keyword = $this->keyword($query, [
            'khách hàng', 'khach hang', 'khách', 'khach', 'tìm', 'tim', 'đại lý', 'dai ly',
            'số điện thoại', 'so dien thoai', 'mã số thuế', 'ma so thue',
        ]);
        if ($keyword !== '') {
            $like = '%'.$keyword.'%';
            $builder->where(function (Builder $q) use ($like): void {
                $q->where('c.name', 'like', $like)
                    ->orWhere('c.phone', 'like', $like)
                    ->orWhere('c.email', 'like', $like)
                    ->orWhere('c.billing_company_name', 'like', $like)
                    ->orWhere('c.billing_tax_code', 'like', $like);
            });
        }

        $count = (int) (clone $builder)->count('c.id');
        $rows = $builder
            ->select(['c.id', 'c.name', 'c.phone', 'c.email', 'c.customer_status', 'c.billing_company_name', 'u.name as owner_name'])
            ->orderByDesc('c.updated_at')
            ->limit(12)
            ->get();

        $items = $rows->map(fn ($row) => [
            'type' => 'customer',
            'icon' => 'bi-person-vcard',
            'title' => (string) ($row->name ?: 'Khách hàng #'.$row->id),
            'subtitle' => trim(implode(' · ', array_filter([$this->maskPhone((string) ($row->phone ?? '')), (string) ($row->billing_company_name ?? '')]))),
            'meta' => $row->owner_name ? 'Phụ trách: '.$row->owner_name : 'Chưa có người phụ trách',
            'badge' => $this->customerStatus((string) ($row->customer_status ?? 'lead')),
            'tone' => 'info',
            'url' => $this->routeUrl('customers.show', ['customer' => $row->id], '/customers/'.$row->id),
        ])->values()->all();

        return $this->context(
            'customers',
            'Khách hàng trong phạm vi tài khoản',
            $count > 0 ? "Tìm thấy {$count} khách hàng phù hợp." : 'Không có khách hàng phù hợp hoặc nằm ngoài phạm vi role.',
            [$this->metric('Kết quả', number_format($count, 0, ',', '.'), 'bi-people', 'primary')],
            $items,
            $scope,
            $period
        );
    }

    /** @param array<string, mixed> $period */
    private function inventory(User $user, string $query, array $period, string $scope): array
    {
        if (! $this->hasTable('crm_product_catalog')) {
            return $this->empty('inventory', 'Chưa có dữ liệu sản phẩm.', $scope, $period);
        }

        $stockSub = $this->hasTable('crm_product_stock')
            ? DB::table('crm_product_stock')
                ->selectRaw('product_id, COALESCE(SUM(qty), 0) stock_qty')
                ->when($this->hasColumn('crm_product_stock', 'company_id'), fn (Builder $q) => $q->where('company_id', EgoCompanyLock::id()))
                ->groupBy('product_id')
            : null;

        $builder = DB::table('crm_product_catalog as p');
        $this->applyCompany($builder, 'crm_product_catalog', 'p', true);
        if ($stockSub) {
            $builder->leftJoinSub($stockSub, 's', 's.product_id', '=', 'p.id');
        }

        $keyword = $this->keyword($query, [
            'tồn kho', 'ton kho', 'sản phẩm', 'san pham', 'sku', 'serial', 'kho hàng', 'kho hang',
            'hết hàng', 'het hang', 'sắp hết', 'sap het', 'kiểm tra', 'kiem tra',
        ]);
        if ($keyword !== '') {
            $like = '%'.$keyword.'%';
            $builder->where(function (Builder $q) use ($like): void {
                $q->where('p.name', 'like', $like)->orWhere('p.sku', 'like', $like)->orWhere('p.barcode', 'like', $like);
            });
        }

        $normalized = $this->normalize($query);
        if ($stockSub && (str_contains($normalized, 'sap het') || str_contains($normalized, 'sắp hết'))) {
            $builder->whereRaw('COALESCE(s.stock_qty, 0) BETWEEN 1 AND 5');
        }
        if ($stockSub && (str_contains($normalized, 'het hang') || str_contains($normalized, 'hết hàng'))) {
            $builder->whereRaw('COALESCE(s.stock_qty, 0) <= 0');
        }

        $rows = $builder
            ->select(['p.id', 'p.name', 'p.sku', 'p.unit', 'p.price_agent', 'p.price_retail', 'p.quantity', 'p.is_active'])
            ->when($stockSub, fn (Builder $q) => $q->addSelect(DB::raw('COALESCE(s.stock_qty, 0) AS stock_qty')))
            ->orderByRaw($stockSub ? 'COALESCE(s.stock_qty, 0) ASC' : 'p.name ASC')
            ->limit(15)
            ->get();

        $totalProducts = (int) DB::table('crm_product_catalog')->when($this->hasColumn('crm_product_catalog', 'company_id'), fn (Builder $q) => $q->where(function (Builder $x): void {
            $x->whereNull('company_id')->orWhere('company_id', EgoCompanyLock::id());
        }))->count();
        $outOfStock = $stockSub ? (int) (clone $builder)->whereRaw('COALESCE(s.stock_qty, 0) <= 0')->count('p.id') : 0;
        $lowStock = $stockSub ? (int) (clone $builder)->whereRaw('COALESCE(s.stock_qty, 0) BETWEEN 1 AND 5')->count('p.id') : 0;

        $items = $rows->map(function ($row) use ($stockSub): array {
            $stock = $stockSub ? (int) ($row->stock_qty ?? 0) : (int) ($row->quantity ?? 0);
            return [
                'type' => 'product',
                'icon' => 'bi-box-seam',
                'title' => (string) $row->name,
                'subtitle' => 'SKU: '.(string) $row->sku,
                'meta' => 'Tồn: '.number_format($stock, 0, ',', '.').' '.(string) ($row->unit ?? ''),
                'badge' => $stock <= 0 ? 'Hết hàng' : ($stock <= 5 ? 'Sắp hết' : 'Còn hàng'),
                'tone' => $stock <= 0 ? 'danger' : ($stock <= 5 ? 'warning' : 'success'),
                'url' => $this->routeUrl('products.show', ['product' => $row->id], '/products/'.$row->id),
            ];
        })->values()->all();

        return $this->context(
            'inventory',
            'Sản phẩm & tồn kho',
            $rows->count().' sản phẩm được trả về theo từ khóa và quyền truy cập.',
            [
                $this->metric('Tổng sản phẩm', number_format($totalProducts, 0, ',', '.'), 'bi-box-seam', 'primary'),
                $this->metric('Sắp hết', number_format($lowStock, 0, ',', '.'), 'bi-exclamation-triangle', 'warning'),
                $this->metric('Hết hàng', number_format($outOfStock, 0, ',', '.'), 'bi-x-octagon', 'danger'),
            ],
            $items,
            $scope,
            $period
        );
    }

    /** @param array<string, mixed> $period */
    private function tasks(User $user, string $query, array $period, string $scope): array
    {
        $table = $this->hasTable('tasks') ? 'tasks' : ($this->hasTable('crm_tasks') ? 'crm_tasks' : null);
        if (! $table) {
            return $this->empty('tasks', 'Chưa có dữ liệu công việc.', $scope, $period);
        }

        $isTasks = $table === 'tasks';
        $builder = DB::table($table.' as t');
        if ($isTasks && $this->hasColumn($table, 'company_id')) {
            $this->applyCompany($builder, $table, 't', true);
        }

        $assignee = $isTasks ? 't.assignee_id' : 't.assigned_to';
        $requester = $isTasks ? 't.requester_id' : 't.assigned_by';
        $due = $isTasks ? 't.due_at' : 't.due_date';

        $this->applyUserScope($builder, $user, $scope, [
            'self' => [$assignee, $requester],
            'department' => [$assignee, $requester],
        ]);

        $normalized = $this->normalize($query);
        if (str_contains($normalized, 'qua han')) {
            $builder->where($due, '<', now())->whereNotIn('t.status', ['completed', 'done', 'approved']);
        } elseif ($period['explicit']) {
            $builder->whereBetween($due, [$period['from'], $period['to']]);
        }

        $keyword = $this->keyword($query, ['công việc', 'cong viec', 'task', 'việc của tôi', 'viec cua toi', 'quá hạn', 'qua han', 'deadline']);
        if ($keyword !== '') {
            $like = '%'.$keyword.'%';
            $builder->where(function (Builder $q) use ($like): void {
                $q->where('t.title', 'like', $like)->orWhere('t.description', 'like', $like);
            });
        }

        $count = (int) (clone $builder)->count('t.id');
        $overdue = (int) (clone $builder)->where($due, '<', now())->whereNotIn('t.status', ['completed', 'done', 'approved'])->count('t.id');
        $rows = $builder
            ->leftJoin('users as a', 'a.id', '=', DB::raw($assignee))
            ->select(['t.id', 't.title', 't.priority', 't.status', DB::raw($due.' as due_at'), 'a.name as assignee_name'])
            ->orderByRaw($due.' IS NULL, '.$due.' ASC')
            ->limit(15)
            ->get();

        $items = $rows->map(fn ($row) => [
            'type' => 'task',
            'icon' => 'bi-list-check',
            'title' => (string) ($row->title ?: 'Công việc #'.$row->id),
            'subtitle' => $row->assignee_name ? 'Phụ trách: '.$row->assignee_name : 'Chưa phân công',
            'meta' => $row->due_at ? 'Hạn: '.Carbon::parse($row->due_at)->format('d/m/Y H:i') : 'Chưa có hạn',
            'badge' => $this->taskStatus((string) ($row->status ?? 'new')),
            'tone' => in_array((string) $row->status, ['completed', 'done', 'approved'], true) ? 'success' : ((isset($row->due_at) && Carbon::parse($row->due_at)->isPast()) ? 'danger' : 'info'),
            'url' => $this->routeUrl('tasks.show', ['task' => $row->id], '/chat/tasks/'.$row->id),
        ])->values()->all();

        return $this->context(
            'tasks',
            'Công việc trong phạm vi tài khoản',
            $count > 0 ? "Có {$count} công việc phù hợp." : 'Không có công việc phù hợp.',
            [
                $this->metric('Tổng việc', number_format($count, 0, ',', '.'), 'bi-list-check', 'primary'),
                $this->metric('Quá hạn', number_format($overdue, 0, ',', '.'), 'bi-alarm', $overdue > 0 ? 'danger' : 'success'),
            ],
            $items,
            $scope,
            $period
        );
    }

    /** @param array<string, mixed> $period */
    private function sites(User $user, string $query, array $period, string $scope): array
    {
        if (! $this->hasTable('sites')) {
            return $this->empty('sites', 'Chưa có dữ liệu công trình.', $scope, $period);
        }

        $builder = DB::table('sites as s')->leftJoin('users as u', 'u.id', '=', 's.created_by');
        $this->applyCompany($builder, 'sites', 's');
        $this->applyUserScope($builder, $user, $scope, [
            'self' => ['s.created_by'],
            'department' => ['s.created_by'],
        ]);

        $keyword = $this->keyword($query, ['công trình', 'cong trinh', 'bảo hành', 'bao hanh', 'bảo trì', 'bao tri', 'thi công', 'thi cong', 'khảo sát', 'khao sat']);
        if ($keyword !== '') {
            $like = '%'.$keyword.'%';
            $builder->where(function (Builder $q) use ($like): void {
                $q->where('s.name', 'like', $like)
                    ->orWhere('s.contact_name', 'like', $like)
                    ->orWhere('s.contact_phone', 'like', $like)
                    ->orWhere('s.quote_no', 'like', $like);
            });
        }

        $normalized = $this->normalize($query);
        if (str_contains($normalized, 'bao hanh') && $this->hasColumn('sites', 'warranty_to')) {
            $builder->whereNotNull('s.warranty_to')->whereDate('s.warranty_to', '<=', $period['to']->toDateString());
        }

        $count = (int) (clone $builder)->count('s.id');
        $rows = $builder
            ->select(['s.id', 's.name', 's.status', 's.stage', 's.system_kwp', 's.battery_kwh', 's.warranty_to', 'u.name as creator_name'])
            ->orderByDesc('s.updated_at')
            ->limit(12)
            ->get();

        $items = $rows->map(fn ($row) => [
            'type' => 'site',
            'icon' => 'bi-building-gear',
            'title' => (string) $row->name,
            'subtitle' => trim(implode(' · ', array_filter([
                $row->system_kwp !== null ? number_format((float) $row->system_kwp, 2, ',', '.').' kWp' : null,
                $row->battery_kwh !== null ? number_format((float) $row->battery_kwh, 2, ',', '.').' kWh' : null,
            ]))),
            'meta' => $row->warranty_to ? 'Bảo hành đến '.Carbon::parse($row->warranty_to)->format('d/m/Y') : 'Chưa có hạn bảo hành',
            'badge' => (string) ($row->stage ?: $row->status ?: 'Đang cập nhật'),
            'tone' => 'info',
            'url' => $this->routeUrl('sites.show', ['id' => $row->id], '/cong-trinh/'.$row->id),
        ])->values()->all();

        return $this->context(
            'sites',
            'Công trình & bảo hành',
            $count > 0 ? "Có {$count} công trình phù hợp." : 'Không có công trình phù hợp trong phạm vi role.',
            [$this->metric('Công trình', number_format($count, 0, ',', '.'), 'bi-building-gear', 'primary')],
            $items,
            $scope,
            $period
        );
    }

    /** @param array<string, mixed> $period */
    private function paymentRequests(User $user, string $query, array $period, string $scope): array
    {
        if (! $this->hasTable('payment_requests')) {
            return $this->empty('payment_requests', 'Chưa có dữ liệu đề nghị thanh toán.', $scope, $period);
        }

        $builder = DB::table('payment_requests as p')->leftJoin('users as u', 'u.id', '=', 'p.created_by');
        $this->applyCompany($builder, 'payment_requests', 'p', true);
        $this->applyUserScope($builder, $user, $scope, [
            'self' => ['p.created_by'],
            'department' => ['p.created_by'],
        ]);

        if ($period['explicit']) {
            $builder->whereBetween('p.created_at', [$period['from'], $period['to']]);
        }

        $keyword = $this->keyword($query, ['đề nghị thanh toán', 'de nghi thanh toan', 'dntt', 'phiếu thanh toán', 'phieu thanh toan', 'chờ duyệt', 'cho duyet']);
        if ($keyword !== '') {
            $like = '%'.$keyword.'%';
            $builder->where(function (Builder $q) use ($like): void {
                $q->where('p.code', 'like', $like)
                    ->orWhere('p.receiver_name', 'like', $like)
                    ->orWhere('p.reason', 'like', $like)
                    ->orWhere('p.payment_content', 'like', $like);
            });
        }

        $normalized = $this->normalize($query);
        if (str_contains($normalized, 'cho duyet')) {
            $builder->whereIn('p.status', ['submitted', 'admin_approved']);
        }

        $count = (int) (clone $builder)->count('p.id');
        $total = (float) (clone $builder)->sum('p.amount');
        $pending = (int) (clone $builder)->whereIn('p.status', ['submitted', 'admin_approved'])->count('p.id');
        $rows = $builder
            ->select(['p.id', 'p.code', 'p.receiver_name', 'p.amount', 'p.status', 'p.payment_due_date', 'u.name as creator_name'])
            ->orderByDesc('p.created_at')
            ->limit(12)
            ->get();

        $items = $rows->map(fn ($row) => [
            'type' => 'payment_request',
            'icon' => 'bi-cash-stack',
            'title' => (string) $row->code,
            'subtitle' => 'Người nhận: '.(string) $row->receiver_name,
            'meta' => $this->money((float) $row->amount).($row->payment_due_date ? ' · Hạn '.Carbon::parse($row->payment_due_date)->format('d/m/Y') : ''),
            'badge' => $this->paymentRequestStatus((string) $row->status),
            'tone' => str_contains((string) $row->status, 'rejected') ? 'danger' : (str_contains((string) $row->status, 'approved') ? 'success' : 'warning'),
            'url' => $this->routeUrl('payment_requests.show', ['id' => $row->id], '/payment-requests/'.$row->id),
        ])->values()->all();

        return $this->context(
            'payment_requests',
            'Đề nghị thanh toán',
            $count > 0 ? "Có {$count} phiếu trong phạm vi được phép xem." : 'Không có phiếu phù hợp.',
            [
                $this->metric('Số phiếu', number_format($count, 0, ',', '.'), 'bi-file-earmark-text', 'primary'),
                $this->metric('Tổng tiền', $this->money($total), 'bi-cash-stack', 'info'),
                $this->metric('Đang chờ', number_format($pending, 0, ',', '.'), 'bi-hourglass-split', $pending > 0 ? 'warning' : 'success'),
            ],
            $items,
            $scope,
            $period
        );
    }

    /** @param array<string, mixed> $period */
    private function attendance(User $user, string $query, array $period, string $scope): array
    {
        if (! $this->hasTable('attendance_records')) {
            return $this->empty('attendance', 'Chưa có dữ liệu chấm công.', $scope, $period);
        }

        $builder = DB::table('attendance_records as a')->join('users as u', 'u.id', '=', 'a.user_id');
        $this->applyUserScope($builder, $user, $scope, [
            'self' => ['a.user_id'],
            'department' => ['a.user_id'],
        ]);
        $builder->whereBetween('a.work_date', [$period['from']->toDateString(), $period['to']->toDateString()]);

        $count = (int) (clone $builder)->distinct()->count('a.user_id');
        $checkedIn = (int) (clone $builder)->whereNotNull('a.check_in_at')->distinct()->count('a.user_id');
        $late = (int) (clone $builder)->where('a.late_minutes', '>', 0)->distinct()->count('a.user_id');
        $checkedOut = (int) (clone $builder)->whereNotNull('a.check_out_at')->distinct()->count('a.user_id');

        $rows = $builder
            ->select(['a.id', 'a.work_date', 'a.check_in_at', 'a.check_out_at', 'a.late_minutes', 'a.work_minutes', 'a.status', 'u.name as user_name'])
            ->orderByDesc('a.work_date')
            ->orderBy('u.name')
            ->limit(30)
            ->get();

        $items = $rows->map(function ($row) use ($scope): array {
            $name = $scope === 'self' ? 'Chấm công của bạn' : (string) $row->user_name;
            return [
                'type' => 'attendance',
                'icon' => 'bi-person-check',
                'title' => $name,
                'subtitle' => Carbon::parse($row->work_date)->format('d/m/Y'),
                'meta' => 'Vào: '.($row->check_in_at ? Carbon::parse($row->check_in_at)->format('H:i') : 'Chưa có')
                    .' · Ra: '.($row->check_out_at ? Carbon::parse($row->check_out_at)->format('H:i') : 'Chưa có'),
                'badge' => (int) $row->late_minutes > 0 ? 'Muộn '.(int) $row->late_minutes.' phút' : $this->attendanceStatus((string) $row->status),
                'tone' => (int) $row->late_minutes > 0 ? 'warning' : 'success',
                'url' => $this->routeUrl('hr.attendance.my', [], '/nhan-su/cham-cong'),
            ];
        })->values()->all();

        return $this->context(
            'attendance',
            'Chấm công '.$period['label'],
            $count > 0 ? "Có {$count} nhân sự có bản ghi chấm công trong đúng phạm vi role." : 'Không có bản ghi chấm công trong phạm vi và thời gian này.',
            [
                $this->metric('Có bản ghi', number_format($count, 0, ',', '.'), 'bi-person-check', 'primary'),
                $this->metric('Đã check-in', number_format($checkedIn, 0, ',', '.'), 'bi-box-arrow-in-right', 'success'),
                $this->metric('Đã check-out', number_format($checkedOut, 0, ',', '.'), 'bi-box-arrow-right', 'info'),
                $this->metric('Đi muộn', number_format($late, 0, ',', '.'), 'bi-alarm', $late > 0 ? 'warning' : 'success'),
            ],
            $items,
            $scope,
            $period
        );
    }

    /** @param array<string, mixed> $period */
    private function marketing(User $user, string $query, array $period, string $scope): array
    {
        if (! $this->hasTable('marketing_leads')) {
            return $this->empty('marketing', 'Chưa có dữ liệu lead Marketing.', $scope, $period);
        }

        $builder = DB::table('marketing_leads as m')->leftJoin('users as u', 'u.id', '=', 'm.assigned_user_id');
        $this->applyUserScope($builder, $user, $scope, [
            'self' => ['m.assigned_user_id', 'm.imported_by'],
            'department' => ['m.assigned_user_id', 'm.imported_by'],
        ]);
        $builder->whereBetween(DB::raw('COALESCE(m.import_date, DATE(m.created_at))'), [$period['from']->toDateString(), $period['to']->toDateString()]);

        $keyword = $this->keyword($query, ['marketing', 'lead marketing', 'chiến dịch', 'chien dich', 'quảng cáo', 'quang cao', 'ads', 'content', 'kpi']);
        if ($keyword !== '') {
            $like = '%'.$keyword.'%';
            $builder->where(function (Builder $q) use ($like): void {
                $q->where('m.name', 'like', $like)
                    ->orWhere('m.campaign', 'like', $like)
                    ->orWhere('m.source', 'like', $like)
                    ->orWhere('m.status', 'like', $like);
            });
        }

        $count = (int) (clone $builder)->count('m.id');
        $new = (int) (clone $builder)->where('m.status', 'new')->count('m.id');
        $assigned = (int) (clone $builder)->whereNotNull('m.assigned_user_id')->count('m.id');
        $rows = $builder
            ->select(['m.id', 'm.name', 'm.phone', 'm.source', 'm.campaign', 'm.status', 'u.name as assigned_name'])
            ->orderByDesc('m.created_at')
            ->limit(15)
            ->get();

        $items = $rows->map(fn ($row) => [
            'type' => 'marketing_lead',
            'icon' => 'bi-person-plus',
            'title' => (string) ($row->name ?: 'Lead #'.$row->id),
            'subtitle' => trim(implode(' · ', array_filter([(string) ($row->campaign ?? ''), (string) ($row->source ?? '')]))),
            'meta' => $row->assigned_name ? 'Phụ trách: '.$row->assigned_name : 'Chưa phân công',
            'badge' => (string) ($row->status ?: 'new'),
            'tone' => $row->assigned_name ? 'success' : 'warning',
            'url' => $this->routeUrl('marketing.leads.index', [], '/marketing/leads'),
        ])->values()->all();

        return $this->context(
            'marketing',
            'Marketing & lead '.$period['label'],
            $count > 0 ? "Có {$count} lead phù hợp." : 'Không có lead phù hợp trong phạm vi role.',
            [
                $this->metric('Tổng lead', number_format($count, 0, ',', '.'), 'bi-person-plus', 'primary'),
                $this->metric('Lead mới', number_format($new, 0, ',', '.'), 'bi-stars', 'info'),
                $this->metric('Đã phân công', number_format($assigned, 0, ',', '.'), 'bi-person-check', 'success'),
            ],
            $items,
            $scope,
            $period
        );
    }

    /** @param array<string, mixed> $period */
    private function hr(User $user, string $query, array $period, string $scope): array
    {
        $activeUsers = $this->hasTable('users')
            ? (int) DB::table('users')->where('is_active', true)->count()
            : 0;

        $leaveCount = 0;
        $pendingLeave = 0;
        $items = [];

        if ($this->hasTable('leave_requests')) {
            $leave = DB::table('leave_requests as l')->join('users as u', 'u.id', '=', 'l.user_id')
                ->where(function (Builder $q) use ($period): void {
                    $q->whereBetween('l.start_date', [$period['from']->toDateString(), $period['to']->toDateString()])
                        ->orWhereBetween('l.end_date', [$period['from']->toDateString(), $period['to']->toDateString()]);
                });
            $leaveCount = (int) (clone $leave)->count('l.id');
            $pendingLeave = (int) (clone $leave)->where('l.status', 'pending')->count('l.id');
            $items = $leave->select(['l.id', 'l.start_date', 'l.end_date', 'l.days', 'l.status', 'l.leave_type', 'u.name as user_name'])
                ->orderByDesc('l.created_at')->limit(10)->get()->map(fn ($row) => [
                    'type' => 'leave',
                    'icon' => 'bi-calendar2-minus',
                    'title' => (string) $row->user_name,
                    'subtitle' => Carbon::parse($row->start_date)->format('d/m/Y').' - '.Carbon::parse($row->end_date)->format('d/m/Y'),
                    'meta' => number_format((float) $row->days, 1, ',', '.').' ngày · '.(string) ($row->leave_type ?? 'Nghỉ phép'),
                    'badge' => (string) $row->status,
                    'tone' => $row->status === 'approved' ? 'success' : ($row->status === 'rejected' ? 'danger' : 'warning'),
                    'url' => $this->routeUrl('hr.leave.index', [], '/nhan-su/nghi-phep'),
                ])->values()->all();
        }

        $recruitment = 0;
        if ($this->hasTable('hr_recruitment_requests')) {
            $recruitment = (int) DB::table('hr_recruitment_requests')
                ->whereNotIn('approval_status', ['closed', 'rejected', 'completed'])
                ->count();
        }

        return $this->context(
            'hr',
            'Nhân sự & tuyển dụng',
            'Số liệu HR chỉ được trả về cho role có quyền Nhân sự.',
            [
                $this->metric('Nhân sự hoạt động', number_format($activeUsers, 0, ',', '.'), 'bi-people', 'primary'),
                $this->metric('Đơn nghỉ trong kỳ', number_format($leaveCount, 0, ',', '.'), 'bi-calendar2-minus', 'info'),
                $this->metric('Nghỉ chờ duyệt', number_format($pendingLeave, 0, ',', '.'), 'bi-hourglass-split', $pendingLeave > 0 ? 'warning' : 'success'),
                $this->metric('Nhu cầu tuyển', number_format($recruitment, 0, ',', '.'), 'bi-person-plus', 'info'),
            ],
            $items,
            $scope,
            $period
        );
    }

    /** @param array<string, mixed> $period */
    private function context(string $tool, string $title, string $summary, array $metrics, array $items, string $scope, array $period): array
    {
        return [
            'tool' => $tool,
            'title' => $title,
            'summary' => $summary,
            'metrics' => $metrics,
            'items' => $items,
            'source' => 'CRM EGO Solar',
            'scope' => $this->access->scopeLabel($scope),
            'period' => (string) ($period['label'] ?? ''),
            'period_key' => (string) ($period['key'] ?? ''),
            'updated_at' => now()->format('d/m/Y H:i'),
        ];
    }

    /** @param array<string, mixed> $period */
    private function empty(string $tool, string $summary, string $scope, array $period): array
    {
        return $this->context($tool, (string) config("ego_ai.modules.{$tool}.label", 'Dữ liệu CRM'), $summary, [], [], $scope, $period);
    }

    private function applyCompany(Builder $builder, string $table, string $alias, bool $allowNull = false): void
    {
        if (! $this->hasColumn($table, 'company_id')) {
            return;
        }

        if ($allowNull) {
            $builder->where(function (Builder $q) use ($alias): void {
                $q->whereNull($alias.'.company_id')->orWhere($alias.'.company_id', EgoCompanyLock::id());
            });
            return;
        }

        $builder->where($alias.'.company_id', EgoCompanyLock::id());
    }

    /** @param array<string, array<int, string>> $columns */
    private function applyUserScope(Builder $builder, User $user, string $scope, array $columns): void
    {
        if ($scope === 'company') {
            return;
        }

        $fields = (array) ($columns[$scope] ?? []);
        if ($fields === []) {
            $builder->whereRaw('1 = 0');
            return;
        }

        $ids = $this->access->scopedUserIds($user, $scope);
        if ($ids === []) {
            $builder->whereRaw('1 = 0');
            return;
        }

        $builder->where(function (Builder $q) use ($fields, $ids): void {
            foreach ($fields as $index => $field) {
                if ($index === 0) {
                    $q->whereIn($field, $ids);
                } else {
                    $q->orWhereIn($field, $ids);
                }
            }
        });
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

    private function normalize(string $value): string
    {
        return trim((string) preg_replace('/\s+/', ' ', mb_strtolower(Str::ascii($value), 'UTF-8')));
    }

    /** @param array<int, string> $stopWords */
    private function keyword(string $query, array $stopWords): string
    {
        $value = mb_strtolower($query, 'UTF-8');
        foreach ($stopWords as $word) {
            $value = str_ireplace($word, ' ', $value);
        }
        $value = preg_replace('/\b(hôm nay|hôm qua|hôm kia|tuần này|tuần trước|tháng này|tháng trước|năm nay|quý này)\b/ui', ' ', $value) ?? $value;
        $value = trim((string) preg_replace('/\s+/', ' ', $value));
        return mb_strlen($value) >= 2 ? Str::limit($value, 100, '') : '';
    }

    private function money(float $amount): string
    {
        return number_format($amount, 0, ',', '.').' đ';
    }

    private function maskPhone(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', $phone) ?: '';
        if (strlen($digits) < 7) {
            return $phone;
        }
        return substr($digits, 0, 3).'***'.substr($digits, -3);
    }

    private function metric(string $label, string $value, string $icon, string $tone): array
    {
        return compact('label', 'value', 'icon', 'tone');
    }

    private function routeUrl(string $route, array $parameters, string $fallback): string
    {
        try {
            return route($route, $parameters);
        } catch (\Throwable) {
            return url($fallback);
        }
    }

    private function customerStatus(string $status): string
    {
        return match ($status) {
            'member' => 'Thành viên',
            'retail' => 'Khách lẻ',
            default => 'Tiềm năng',
        };
    }

    private function taskStatus(string $status): string
    {
        return match ($status) {
            'completed', 'done', 'approved' => 'Hoàn thành',
            'in_progress', 'doing' => 'Đang làm',
            'cancelled', 'canceled' => 'Đã hủy',
            default => 'Chờ xử lý',
        };
    }

    private function paymentRequestStatus(string $status): string
    {
        return match ($status) {
            'draft' => 'Nháp',
            'submitted' => 'Chờ Giám đốc',
            'admin_approved' => 'Chờ Kế toán',
            'admin_rejected', 'accounting_rejected' => 'Từ chối',
            'accounting_approved' => 'Đã duyệt',
            default => $status,
        };
    }

    private function attendanceStatus(string $status): string
    {
        return match ($status) {
            'late' => 'Đi muộn',
            'early_leave' => 'Về sớm',
            'absent' => 'Vắng',
            default => 'Đúng giờ',
        };
    }
}
