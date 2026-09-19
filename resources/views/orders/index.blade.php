@extends('layouts.app')
@section('title','Đơn hàng')
@section('content')
<link rel="stylesheet" href="{{ asset('css/orders-pro-v2.css') }}?v={{ @filemtime(public_path('css/orders-pro-v2.css')) ?: time() }}">
<script defer src="{{ asset('js/orders-pro-v2.js') }}?v={{ @filemtime(public_path('js/orders-pro-v2.js')) ?: time() }}"></script>
@php
    $rows = collect($orders->items());
    $totalAmount = (float)($orderSummary['total_amount'] ?? $rows->sum(fn($o)=>(float)($o->total_amount ?? 0)));
    $totalPaid = (float)($orderSummary['total_paid'] ?? $rows->sum(fn($o)=>(float)collect($o->payments ?? [])->sum('amount')));
    $totalDebt = (float)($orderSummary['total_debt'] ?? max(0,$totalAmount-$totalPaid));
    $debtOver30 = (float)($orderSummary['debt_over_30'] ?? 0);
    $pageReturns = $rows->sum(fn($o)=>collect($o->returns ?? [])->whereNotIn('status',['completed','rejected','cancelled'])->count());
    $pageIssued = $rows->where('inventory_issued',true)->count();
    $hasAdvanced = request()->hasAny(['created_by','payment_filter','from_date','to_date']);
    $deptLabels=['sales'=>'Sales','sales_manager'=>'Sales Manager','duyet1'=>'Sales Manager','accounting'=>'Kế toán','ketoan'=>'Kế toán','management'=>'Ban Giám đốc','duyet2'=>'Ban Giám đốc','warehouse'=>'Kho','kho'=>'Kho','shipping'=>'Vận chuyển','completed'=>'Hoàn tất','cancelled'=>'Đã hủy'];
@endphp
<div class="erp-page">
<div class="erp-container">
    <div class="erp-page-head">
        <div>
            <div class="erp-breadcrumb"><i class="bi bi-house-door"></i><span>/</span><span>Đơn hàng</span><span>/</span><strong>Danh sách</strong></div>
            <h1 class="erp-title">Đơn hàng</h1>
            <div class="erp-subtitle">Theo dõi duyệt, thanh toán, xuất kho, giao hàng và hậu mãi trong một màn hình.</div>
        </div>
        <div class="erp-head-actions erp-no-print">
            <a href="{{ route('order-returns.dashboard') }}" class="erp-btn"><i class="bi bi-arrow-left-right"></i> Đổi trả & hoàn tiền</a>
            <a href="{{ route('orders.export.excel', request()->query()) }}" class="erp-btn"><i class="bi bi-file-earmark-excel"></i> Xuất Excel</a>
            @can('create', App\Models\CRM\Orders\Order::class)
                <a href="{{ route('orders.create') }}" class="erp-btn erp-btn-primary"><i class="bi bi-plus-lg"></i> Tạo đơn</a>
            @endcan
        </div>
    </div>

    @if(session('success'))<div class="erp-alert erp-alert-success"><i class="bi bi-check-circle me-1"></i>{{ session('success') }}</div>@endif
    @if(session('error'))<div class="erp-alert erp-alert-danger"><i class="bi bi-exclamation-triangle me-1"></i>{{ session('error') }}</div>@endif

    <div class="erp-stats">
        <div class="erp-stat blue"><div class="erp-stat-icon"><i class="bi bi-receipt"></i></div><div><div class="erp-stat-label">Tổng doanh thu</div><div class="erp-stat-value">{{ number_format($totalAmount,0,',','.') }} đ</div><div class="erp-stat-note">Theo bộ lọc hiện tại</div></div></div>
        <div class="erp-stat"><div class="erp-stat-icon"><i class="bi bi-check2-circle"></i></div><div><div class="erp-stat-label">Đã thu</div><div class="erp-stat-value">{{ number_format($totalPaid,0,',','.') }} đ</div><div class="erp-stat-note">Tổng thanh toán ghi nhận</div></div></div>
        <div class="erp-stat red"><div class="erp-stat-icon"><i class="bi bi-wallet2"></i></div><div><div class="erp-stat-label">Công nợ</div><div class="erp-stat-value">{{ number_format($totalDebt,0,',','.') }} đ</div><div class="erp-stat-note">Quá 30 ngày: {{ number_format($debtOver30,0,',','.') }} đ</div></div></div>
        <div class="erp-stat amber"><div class="erp-stat-icon"><i class="bi bi-box-seam"></i></div><div><div class="erp-stat-label">Kho & hậu mãi</div><div class="erp-stat-value">{{ $pageIssued }} đã xuất · {{ $pageReturns }} xử lý</div><div class="erp-stat-note">Trong trang dữ liệu hiện tại</div></div></div>
    </div>

    <form action="{{ route('orders.index') }}" method="GET" class="erp-card erp-filter">
        <div class="erp-card-body">
            <div class="erp-filter-grid">
                <div><label class="erp-label">Tìm kiếm</label><input class="erp-input" name="search" value="{{ request('search') }}" placeholder="Mã đơn, khách hàng, số điện thoại..."></div>
                <div><label class="erp-label">Kho</label><select class="erp-select" name="warehouse_id"><option value="">Tất cả kho</option>@foreach($warehouses as $warehouse)<option value="{{ $warehouse->id }}" @selected((string)request('warehouse_id')===(string)$warehouse->id)>{{ $warehouse->name }}</option>@endforeach</select></div>
                <div><label class="erp-label">Trạng thái</label><select class="erp-select" name="status"><option value="">Tất cả trạng thái</option>@foreach(['sales'=>'Chờ gửi duyệt','duyet1'=>'Chờ Sales Manager','ketoan'=>'Chờ kế toán','duyet2'=>'Chờ BGĐ','kho'=>'Chờ xuất kho','completed'=>'Hoàn tất','cancelled'=>'Đã hủy'] as $k=>$v)<option value="{{ $k }}" @selected(request('status')===$k)>{{ $v }}</option>@endforeach</select></div>
                <div><label class="erp-label">Thanh toán</label><select class="erp-select" name="payment_filter"><option value="">Tất cả</option><option value="unpaid" @selected(request('payment_filter')==='unpaid')>Chưa thanh toán</option><option value="partial" @selected(request('payment_filter')==='partial')>Thanh toán một phần</option><option value="paid" @selected(request('payment_filter')==='paid')>Đã thanh toán</option></select></div>
                <div class="erp-filter-actions"><div style="display:flex;gap:7px"><button class="erp-btn erp-btn-primary" type="submit"><i class="bi bi-search"></i> Lọc</button><button class="erp-btn" type="button" data-erp-advanced="orderAdvanced"><i class="bi bi-sliders"></i></button>@if(request()->query())<a class="erp-btn" href="{{ route('orders.index') }}"><i class="bi bi-x-lg"></i></a>@endif</div></div>
            </div>
            <div id="orderAdvanced" class="erp-filter-advanced {{ $hasAdvanced?'open':'' }}">
                <div class="erp-filter-advanced-grid">
                    <div><label class="erp-label">Người tạo / Sales</label><select class="erp-select" name="created_by"><option value="">Tất cả</option>@foreach($creators as $u)<option value="{{ $u->id }}" @selected((string)request('created_by')===(string)$u->id)>{{ $u->name }}</option>@endforeach</select></div>
                    <div><label class="erp-label">Từ ngày</label><input type="date" class="erp-input" name="from_date" value="{{ request('from_date') }}"></div>
                    <div><label class="erp-label">Đến ngày</label><input type="date" class="erp-input" name="to_date" value="{{ request('to_date') }}"></div>
                    <div><label class="erp-label">Tình trạng xuất kho</label><select class="erp-select" name="inventory_filter"><option value="">Tất cả</option><option value="issued" @selected(request('inventory_filter')==='issued')>Đã trừ tồn</option><option value="not_issued" @selected(request('inventory_filter')==='not_issued')>Chưa xuất kho</option></select></div>
                </div>
            </div>
        </div>
    </form>

    <div class="erp-card erp-desktop-table">
        <div class="erp-card-head"><div><h2 class="erp-card-title">Danh sách đơn hàng</h2><div class="erp-card-sub">{{ number_format($orders->total()) }} đơn phù hợp</div></div></div>
        <div class="erp-table-wrap">
            <table class="erp-table">
                <thead><tr><th>Mã đơn & ngày</th><th>Khách hàng</th><th>Tổng tiền</th><th>Thanh toán</th><th>Kho & tồn</th><th>Trạng thái</th><th>Hậu mãi</th><th>Người tạo</th><th>Thao tác</th></tr></thead>
                <tbody>
                @forelse($orders as $order)
                    @php
                        $customer=$order->lead?->customer;
                        $paid=(float)collect($order->payments ?? [])->sum('amount');
                        $total=(float)($order->total_amount ?? 0);
                        $remain=max(0,$total-$paid);
                        $payPct=$total>0?min(100,round($paid/$total*100)):0;
                        $dept=strtolower((string)($order->current_department ?? ''));
                        $isCancelled=in_array($dept,['cancelled','canceled','huy','da_huy'],true);
                        $statusText=$isCancelled?'Đã hủy':($order->inventory_issued?($remain>0?'Còn công nợ':'Hoàn tất'):($order->currentStatusType->name ?? ($deptLabels[$dept] ?? ucfirst($dept ?: 'Chưa xác định'))));
                        $statusClass=$isCancelled?'red':($order->inventory_issued?($remain>0?'amber':'green'):'blue');
                        $activeReturns=collect($order->returns ?? [])->whereNotIn('status',['completed','rejected','cancelled']);
                        $latestReturn=collect($order->returns ?? [])->sortByDesc('id')->first();
                    @endphp
                    <tr>
                        <td><a class="erp-code" href="{{ route('orders.show',$order->id) }}">{{ $order->order_code }}</a><button class="erp-btn erp-icon-btn erp-btn-sm" type="button" data-copy="{{ $order->order_code }}" title="Sao chép"><i class="bi bi-copy"></i></button><div class="erp-secondary-text">{{ $order->order_date ? \Carbon\Carbon::parse($order->order_date)->format('d/m/Y') : '—' }}</div></td>
                        <td><div class="erp-primary-text">{{ $customer->name ?? 'Chưa có khách hàng' }}</div><div class="erp-secondary-text">{{ $customer->phone ?? '—' }}@if($customer?->area) · {{ $customer->area }}@endif</div></td>
                        <td><div class="erp-money">{{ number_format($total,0,',','.') }} đ</div><div class="erp-secondary-text">HĐ: {{ ['issued'=>'Đã xuất','pending'=>'Chờ xuất','none'=>'Không yêu cầu'][$order->invoice_status ?? 'none'] ?? 'Chưa cập nhật' }}</div></td>
                        <td><div class="erp-money {{ $remain>0?'danger':'positive' }}">{{ number_format($paid,0,',','.') }} / {{ number_format($total,0,',','.') }}</div><div class="erp-progress"><div class="erp-progress-bar" style="width:{{ $payPct }}%"></div></div><div class="erp-progress-label"><span>{{ $payPct }}%</span><span>Nợ {{ number_format($remain,0,',','.') }} đ</span></div></td>
                        <td>@if($order->inventory_issued)<span class="erp-badge green">Đã xuất kho</span><div class="erp-secondary-text">Tồn kho đã được trừ@if($order->inventory_issued_at) · {{ \Carbon\Carbon::parse($order->inventory_issued_at)->format('d/m/Y H:i') }}@endif</div>@else<span class="erp-badge gray">Chưa xuất kho</span><div class="erp-secondary-text">{{ $order->warehouse->name ?? 'Chưa chọn kho' }}</div>@endif</td>
                        <td><span class="erp-badge {{ $statusClass }}">{{ $statusText }}</span><div class="erp-secondary-text">Đang ở: {{ $deptLabels[$dept] ?? ucfirst($dept ?: 'Chưa xác định') }}</div></td>
                        <td>@if($activeReturns->count())<span class="erp-badge amber">{{ $activeReturns->count() }} đang xử lý</span><div class="erp-secondary-text">{{ $latestReturn->return_code ?? '' }} · {{ $latestReturn->status_label ?? '' }}</div>@elseif($latestReturn)<span class="erp-badge teal">Đã có hậu mãi</span><div class="erp-secondary-text">{{ $latestReturn->status_label ?? $latestReturn->status }}</div>@else<span class="erp-badge gray">Chưa có yêu cầu</span>@endif</td>
                        <td><div class="erp-primary-text">{{ $order->creator->name ?? '—' }}</div><div class="erp-secondary-text">{{ $order->company->name ?? '—' }}</div></td>
                        <td><div class="erp-actions-inline"><a class="erp-btn erp-icon-btn" href="{{ route('orders.show',$order->id) }}" title="Xem đơn"><i class="bi bi-eye"></i></a><div class="erp-menu"><button class="erp-btn erp-icon-btn" type="button" data-erp-menu><i class="bi bi-three-dots-vertical"></i></button><div class="erp-menu-panel"><a class="erp-menu-item" href="{{ route('orders.show',$order->id) }}"><i class="bi bi-eye"></i>Xem chi tiết</a><a class="erp-menu-item" href="{{ route('orders.approval-form',$order->id) }}"><i class="bi bi-check2-circle"></i>Duyệt trạng thái</a>@can('update',$order)<a class="erp-menu-item" href="{{ route('orders.edit',$order->id) }}"><i class="bi bi-pencil"></i>Chỉnh sửa</a>@endcan<a class="erp-menu-item" href="{{ route('orders.pdf',$order->id) }}" target="_blank"><i class="bi bi-filetype-pdf"></i>Xuất PDF</a>@if($order->inventory_issued)<a class="erp-menu-item" href="{{ route('orders.returns.create',$order) }}"><i class="bi bi-arrow-left-right"></i>Tạo đổi / trả hàng</a>@elseif(!$isCancelled)<a class="erp-menu-item" href="{{ route('orders.returns.create',$order) }}"><i class="bi bi-x-octagon"></i>Yêu cầu hủy</a>@endif<a class="erp-menu-item" href="{{ route('orders.returns.index',$order) }}"><i class="bi bi-clock-history"></i>Hồ sơ hậu mãi</a>

                                    {{-- ORDER_SOFT_DELETE_MENU_V1 --}}
                                    @php
                                        $currentUser = auth()->user();

                                        $canSoftDeleteOrder =
                                            (
                                                method_exists(
                                                    $currentUser,
                                                    'can'
                                                )
                                                && $currentUser->can(
                                                    'orders.delete'
                                                )
                                            )
                                            || (
                                                method_exists(
                                                    $currentUser,
                                                    'hasAnyRole'
                                                )
                                                && $currentUser->hasAnyRole([
                                                    'admin',
                                                    'super_admin',
                                                    'management',
                                                    'director',
                                                ])
                                            )
                                            || in_array(
                                                (string) (
                                                    $currentUser->role ?? ''
                                                ),
                                                [
                                                    'admin',
                                                    'super_admin',
                                                    'management',
                                                    'director',
                                                ],
                                                true
                                            );
                                    @endphp

                                    @if($canSoftDeleteOrder)
                                        <form
                                            method="POST"
                                            action="{{ route(
                                                'orders.soft-delete',
                                                $order
                                            ) }}"
                                            data-order-code="{{
                                                $order->order_code
                                                ?? $order->code
                                                ?? ('#'.$order->id)
                                            }}"
                                            onsubmit="
                                                const code =
                                                    this.dataset.orderCode;

                                                const reason =
                                                    window.prompt(
                                                        'Nhập lý do xóa đơn '
                                                        + code
                                                        + ':'
                                                    );

                                                if (
                                                    !reason
                                                    || reason.trim().length < 3
                                                ) {
                                                    return false;
                                                }

                                                this.querySelector(
                                                    '[name=delete_reason]'
                                                ).value = reason.trim();

                                                return window.confirm(
                                                    'Xóa đơn '
                                                    + code
                                                    + ' khỏi danh sách?\n\n'
                                                    + 'Đây là xóa mềm. '
                                                    + 'Dữ liệu kho, thanh toán '
                                                    + 'và lịch sử vẫn được giữ.'
                                                );
                                            "
                                            style="margin:0;"
                                        >
                                            @csrf
                                            @method('DELETE')

                                            <input
                                                type="hidden"
                                                name="delete_reason"
                                                value=""
                                            >

                                            <button
                                                type="submit"
                                                style="
                                                    width:100%;
                                                    display:flex;
                                                    align-items:center;
                                                    gap:9px;
                                                    padding:9px 13px;
                                                    border:0;
                                                    background:transparent;
                                                    color:#dc2626;
                                                    font:inherit;
                                                    text-align:left;
                                                    cursor:pointer;
                                                "
                                            >
                                                <span
                                                    aria-hidden="true"
                                                    style="
                                                        width:16px;
                                                        text-align:center;
                                                    "
                                                >
                                                    🗑
                                                </span>

                                                <span>Xóa đơn</span>
                                            </button>
                                        </form>
                                    @endif
</div></div></div></td>
                    </tr>
                @empty
                    <tr><td colspan="9"><div class="erp-empty"><div class="erp-empty-icon"><i class="bi bi-inbox"></i></div>Không có đơn hàng phù hợp.</div></td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        @if($orders->hasPages())<div class="erp-pagination">{{ $orders->appends(request()->query())->links('pagination::bootstrap-5') }}</div>@endif
    </div>

    <div class="erp-mobile-list">
        @forelse($orders as $order)
            @php $customer=$order->lead?->customer;$paid=(float)collect($order->payments ?? [])->sum('amount');$total=(float)($order->total_amount??0);$remain=max(0,$total-$paid);$returnsCount=collect($order->returns??[])->whereNotIn('status',['completed','rejected','cancelled'])->count(); @endphp
            <div class="erp-mobile-card"><div class="erp-mobile-top"><div><a class="erp-code" href="{{ route('orders.show',$order->id) }}">{{ $order->order_code }}</a><div class="erp-secondary-text">{{ $order->order_date?\Carbon\Carbon::parse($order->order_date)->format('d/m/Y'):'—' }}</div></div><span class="erp-badge {{ $order->inventory_issued?'green':'gray' }}">{{ $order->inventory_issued?'Đã xuất kho':'Chưa xuất' }}</span></div><div class="erp-primary-text" style="margin-top:10px">{{ $customer->name ?? 'Chưa có khách hàng' }}</div><div class="erp-secondary-text">{{ $customer->phone ?? '—' }}</div><div class="erp-mobile-grid"><div><div class="erp-mobile-k">Tổng tiền</div><div class="erp-mobile-v">{{ number_format($total,0,',','.') }} đ</div></div><div><div class="erp-mobile-k">Công nợ</div><div class="erp-mobile-v" style="color:{{ $remain>0?'var(--erp-red)':'var(--erp-teal)' }}">{{ number_format($remain,0,',','.') }} đ</div></div><div><div class="erp-mobile-k">Bộ phận</div><div class="erp-mobile-v">{{ $deptLabels[strtolower((string)$order->current_department)] ?? $order->current_department }}</div></div><div><div class="erp-mobile-k">Hậu mãi</div><div class="erp-mobile-v">{{ $returnsCount?$returnsCount.' đang xử lý':'Chưa có' }}</div></div></div><div style="display:flex;gap:8px;margin-top:12px"><a class="erp-btn erp-btn-primary" style="flex:1" href="{{ route('orders.show',$order->id) }}">Xem đơn</a><a class="erp-btn" href="{{ route('orders.returns.index',$order) }}"><i class="bi bi-arrow-left-right"></i></a></div></div>
        @empty<div class="erp-empty">Không có đơn hàng phù hợp.</div>@endforelse
        @if($orders->hasPages())<div class="erp-pagination">{{ $orders->appends(request()->query())->links('pagination::bootstrap-5') }}</div>@endif
    </div>
</div>
</div>
@endsection
