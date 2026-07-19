
@php
    $egoPendingOrdersCount = $egoPendingOrdersCount ?? 0;
    $egoPendingMaterialRequestsCount = $egoPendingMaterialRequestsCount ?? 0;

    try {
        $egoPendingOrdersCount = (int) \Illuminate\Support\Facades\DB::table('crm_order_approvals')
            ->where('status', 'pending')
            ->distinct()
            ->count('order_id');

        $egoPendingMaterialRequestsCount = (int) \Illuminate\Support\Facades\DB::table('material_requests')
            ->whereIn('status', ['SUBMITTED', 'ADMIN_APPROVED'])
            ->count();
    } catch (\Throwable $e) {
        $egoPendingOrdersCount = 0;
        $egoPendingMaterialRequestsCount = 0;
    }
@endphp

@extends('layouts.app')
@section('title', 'Chi tiết khách hàng')

@section('content')
@php
    $orders = $customer->orders ?? collect();

    $totalOrders = $orders->count();

    // Tổng tiền đã mua (ưu tiên total_amount, không có thì tính tạm theo items)
    $totalAmount = $orders->sum(function ($o) {
        if (!is_null($o->total_amount)) return (float) $o->total_amount;

        $items = $o->orderItems ?? collect();
        return (float) $items->sum(function($it){
            return ((float)($it->quantity ?? 0)) * ((float)($it->price ?? 0));
        });
    });

    // Tổng số lượng sản phẩm đã mua
    $totalProducts = $orders->sum(function ($o) {
        $items = $o->orderItems ?? collect();
        return (int) $items->sum('quantity');
    });

    // Tạm thời: đếm theo tên sản phẩm có chứa chữ (có thể đổi sang field category sau)
    $warrantyInverter = $orders->sum(function ($o) {
        $items = $o->orderItems ?? collect();
        return $items->filter(function ($it) {
            $name = strtolower($it->product->name ?? '');
            return str_contains($name, 'inverter');
        })->sum('quantity');
    });

    $warrantyBattery = $orders->sum(function ($o) {
        $items = $o->orderItems ?? collect();
        return $items->filter(function ($it) {
            $name = strtolower($it->product->name ?? '');
            return str_contains($name, 'pin') || str_contains($name, 'battery');
        })->sum('quantity');
    });

    $tx = [
        'total_orders' => $totalOrders ?: 0,
        'total_products' => $totalProducts ?: 0,
        'total_amount' => number_format($totalAmount, 0, ',', '.') . ' đ',
        'warranty_inverter' => $warrantyInverter ?: 0,
        'warranty_battery' => $warrantyBattery ?: 0,
    ];
@endphp

<style>
    :root{
        --ego:#06b6d4;
        --ego2:#0891b2;
        --ink:#0f172a;
        --muted: rgba(15,23,42,.62);
        --card: rgba(255,255,255,.86);
        --border: rgba(15,23,42,.10);
        --shadow: 0 18px 50px rgba(15,23,42,.08);
        --shadow2: 0 10px 30px rgba(15,23,42,.08);
        --radius: 18px;
    }

    .page-shell{
        background: radial-gradient(900px 300px at 15% 0%, rgba(6,182,212,.14), transparent 60%),
                    radial-gradient(900px 300px at 85% 10%, rgba(59,130,246,.10), transparent 55%),
                    linear-gradient(180deg, #f7fbff, #f7fbff);
        border-radius: 22px;
        padding: 10px 6px 22px;
    }

    .page-head{
        display:flex;
        align-items:flex-start;
        justify-content:space-between;
        gap: 12px;
        margin: 6px 6px 14px;
        flex-wrap: wrap;
    }

    .page-title{
        display:flex;
        align-items:center;
        gap: 12px;
    }

    .title-badge{
        width: 44px; height: 44px;
        border-radius: 16px;
        display:flex; align-items:center; justify-content:center;
        background: rgba(6,182,212,.16);
        border: 1px solid rgba(6,182,212,.25);
        color: var(--ego2);
        box-shadow: var(--shadow2);
        flex: 0 0 auto;
        font-size: 20px;
    }

    .page-title h1{
        margin:0;
        font-weight: 950;
        letter-spacing:.2px;
        color: var(--ink);
        line-height: 1.15;
    }

    .subtitle{
        margin-top: 6px;
        font-weight: 700;
        color: var(--muted);
        font-size: 13px;
    }

    .btn-ego{
        border: none;
        border-radius: 14px;
        padding: 10px 14px;
        font-weight: 950;
        background: linear-gradient(135deg, var(--ego), var(--ego2));
        box-shadow: 0 14px 34px rgba(8,145,178,.18);
        transition: .15s ease;
        color: #fff;
    }
    .btn-ego:hover{ transform: translateY(-1px); box-shadow: 0 18px 44px rgba(8,145,178,.24); color:#fff; }

    .btn-ghost{
        border-radius: 14px;
        padding: 10px 12px;
        font-weight: 900;
        border: 1px solid rgba(15,23,42,.12);
        background: transparent;
    }

    .btn-soft{
        border-radius: 14px;
        padding: 10px 12px;
        font-weight: 900;
        border: 1px solid rgba(15,23,42,.12);
        background: rgba(255,255,255,.80);
        box-shadow: var(--shadow2);
        transition: .15s ease;
    }
    .btn-soft:hover{ transform: translateY(-1px); }

    .card-glass{
        background: var(--card);
        border: 1px solid var(--border);
        border-radius: var(--radius);
        box-shadow: var(--shadow);
        backdrop-filter: blur(10px);
        -webkit-backdrop-filter: blur(10px);
        overflow:hidden;
    }

    .card-head{
        padding: 14px 16px 10px;
        display:flex;
        align-items:flex-start;
        justify-content:space-between;
        gap: 10px;
        border-bottom: 1px solid rgba(15,23,42,.06);
        flex-wrap: wrap;
    }

    .card-title{
        font-weight: 950;
        color: var(--ink);
        letter-spacing: .2px;
        margin:0;
        display:flex;
        gap:10px;
        align-items:center;
    }

    .card-sub{
        color: var(--muted);
        font-weight: 700;
        font-size: 12px;
        margin-top: 4px;
    }

    .card-body-modern{ padding: 14px 16px; }

    .kpi-label{ font-size: 12px; font-weight: 850; color: rgba(15,23,42,.62); }
    .kpi-value{ font-size: 26px; font-weight: 950; letter-spacing: .2px; color: var(--ink); line-height: 1.1; }
    .kpi-sub{ font-size: 12px; font-weight: 750; color: rgba(15,23,42,.55); margin-top: 4px; }
    .kpi-chip{
        display:inline-flex; align-items:center; gap:6px;
        padding: 6px 10px; border-radius: 999px;
        border: 1px solid rgba(6,182,212,.25);
        background: rgba(6,182,212,.10);
        font-weight: 900; color: #075985;
        font-size: 12px; white-space: nowrap;
    }

    .badge-ego{
        background: rgba(6,182,212,.16);
        color: #075985;
        border: 1px solid rgba(6,182,212,.30);
        font-weight: 900;
    }
    .badge-lead{ background: rgba(245,158,11,.18); border: 1px solid rgba(245,158,11,.35); color: #92400e; font-weight: 900; }
    .badge-member{ background: rgba(34,197,94,.18); border: 1px solid rgba(34,197,94,.35); color: #166534; font-weight: 900; }

    .info-table{
        margin:0;
        font-size: 13px;
    }
    .info-table th{
        width: 42%;
        color: rgba(15,23,42,.62);
        font-weight: 900;
        padding: 10px 8px;
        border-top: 1px dashed rgba(15,23,42,.10);
        vertical-align: top;
    }
    .info-table td{
        color: rgba(15,23,42,.85);
        font-weight: 750;
        padding: 10px 8px;
        border-top: 1px dashed rgba(15,23,42,.10);
    }
    .info-table tr:first-child th,
    .info-table tr:first-child td{
        border-top: none;
    }

    .table-wrap{
        border-radius: var(--radius);
        overflow: hidden;
        border: 1px solid rgba(15,23,42,.10);
        box-shadow: var(--shadow);
        background: rgba(255,255,255,.88);
    }
    .table-modern{
        margin: 0;
        font-size: 12px;
        white-space: nowrap;
    }
    .table-modern thead th{
        position: sticky;
        top: 0;
        z-index: 5;
        background: rgba(15,23,42,.92) !important;
        color: rgba(255,255,255,.92) !important;
        font-weight: 900;
        border-bottom: 1px solid rgba(255,255,255,.12);
        padding: 12px 10px;
        vertical-align: middle;
    }
    .table-modern tbody td{
        vertical-align: middle;
        padding: 10px 10px;
        border-color: rgba(15,23,42,.08);
        color: rgba(15,23,42,.82);
        font-weight: 650;
    }
    .table-modern tbody tr:hover{
        background: rgba(6,182,212,.08) !important;
    }

    .nav-pills .nav-link{
        border-radius: 14px;
        font-weight: 950;
        border: 1px solid rgba(15,23,42,.10);
        background: rgba(255,255,255,.65);
        color: rgba(15,23,42,.72);
        padding: 10px 14px;
    }
    .nav-pills .nav-link.active{
        background: linear-gradient(135deg, rgba(6,182,212,.20), rgba(8,145,178,.18));
        border-color: rgba(6,182,212,.35);
        color: #075985;
    }

    .cell-left{ text-align:left !important; white-space: normal; min-width: 240px; }
    .cell-note{ text-align:left !important; white-space: normal; min-width: 320px; }

    .modal-ego .modal-content{
        border-radius: 18px;
        border: 1px solid rgba(15,23,42,.10);
        box-shadow: 0 24px 80px rgba(15,23,42,.18);
        overflow: hidden;
    }
    .modal-ego .modal-header{
        background: linear-gradient(135deg, rgba(6,182,212,.14), rgba(59,130,246,.10));
        border-bottom: 1px solid rgba(15,23,42,.08);
    }
    .modal-ego .modal-title{
        font-weight: 950;
        color: var(--ink);
    }
    .tx-muted{ color: rgba(15,23,42,.62); font-weight: 750; }

    @media (max-width: 768px){
        .page-shell{ padding: 10px 0 22px; border-radius: 14px; }
        .page-head{ margin: 6px 0 14px; }
    }
</style>

<div class="container-fluid px-4">
    <div class="page-shell">

        {{-- HEADER --}}
        <div class="page-head">
            <div class="page-title">
                <div class="title-badge"><i class="bi bi-person-vcard"></i></div>
                <div>
                    <h1>CHI TIẾT KHÁCH HÀNG</h1>
                    <div class="subtitle">
                        {{ $customer->name ?? '—' }}
                        @if(!empty($customer->phone)) • {{ $customer->phone }} @endif
                    </div>
                </div>
            </div>

            <div class="d-flex gap-2">
                {{-- Bạn đang redirect edit về index, nên giữ nút theo route cũ --}}
                <a href="{{ route('customers.edit', $customer->id) }}" class="btn btn-soft">
                    <i class="bi bi-pencil me-1"></i> Sửa
                </a>
                <a href="{{ route('customers.index') }}" class="btn btn-ghost">
                    <i class="bi bi-arrow-left me-1"></i> Quay lại
                </a>
            </div>
        </div>
<div class="row g-2 px-2 mb-3">
    <div class="col-12">
        <div class="card-glass">
            <div class="card-head">
                <div>
                    <p class="card-title mb-0"><i class="bi bi-receipt"></i> Thông tin hoá đơn</p>
                    <div class="card-sub">Thông tin xuất hoá đơn mặc định của khách hàng</div>
                </div>
            </div>
            <div class="card-body-modern">
                <div class="row g-3">
                    <div class="col-12 col-lg-6">
                        <table class="table table-borderless info-table">
                            <tr>
                                <th>Tên công ty / Cá nhân</th>
                                <td>{{ $customer->billing_company_name ?: '-' }}</td>
                            </tr>
                            <tr>
                                <th>Mã số thuế</th>
                                <td>{{ $customer->billing_tax_code ?: '-' }}</td>
                            </tr>
                        </table>
                    </div>

                    <div class="col-12 col-lg-6">
                        <table class="table table-borderless info-table">
                            <tr>
                                <th>Email nhận hoá đơn</th>
                                <td>{{ $customer->billing_email ?: '-' }}</td>
                            </tr>
                            <tr>
                                <th>Địa chỉ xuất hoá đơn</th>
                                <td>{{ $customer->billing_address ?: '-' }}</td>
                            </tr>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
        {{-- KPI --}}
        <div class="row g-2 px-2 mb-3">
            <div class="col-12 col-md-6 col-xl-3">
                <div class="card-glass p-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="kpi-label">Tổng số đơn</div>
                            <div class="kpi-value">{{ $tx['total_orders'] }}</div>
                            <div class="kpi-sub">Tổng đơn hàng của khách</div>
                        </div>
                        <div class="title-badge"><i class="bi bi-receipt"></i></div>
                    </div>
                </div>
            </div>

            <div class="col-12 col-md-6 col-xl-3">
                <div class="card-glass p-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="kpi-label">Tổng sản phẩm</div>
                            <div class="kpi-value">{{ $tx['total_products'] }}</div>
                            <div class="kpi-sub">Tổng số lượng đã mua</div>
                        </div>
                        <div class="title-badge"><i class="bi bi-box-seam"></i></div>
                    </div>
                </div>
            </div>

            <div class="col-12 col-md-6 col-xl-3">
                <div class="card-glass p-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="kpi-label">Số tiền đã mua</div>
                            <div class="kpi-value">{{ $tx['total_amount'] }}</div>
                            <div class="kpi-sub">Tổng giá trị giao dịch</div>
                        </div>
                        <div class="title-badge"><i class="bi bi-cash-coin"></i></div>
                    </div>
                </div>
            </div>

            <div class="col-12 col-md-6 col-xl-3">
                <div class="card-glass p-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="kpi-label">Sản phẩm bảo hành</div>
                            <div class="d-flex flex-wrap gap-1 mt-2">
                                <span class="kpi-chip"><i class="bi bi-lightning-charge"></i> Inverter: {{ $tx['warranty_inverter'] }}</span>
                                <span class="kpi-chip"><i class="bi bi-battery-charging"></i> Pin: {{ $tx['warranty_battery'] }}</span>
                            </div>
                            <div class="kpi-sub mt-2">Tách theo nhóm bảo hành</div>
                        </div>
                        <div class="title-badge"><i class="bi bi-shield-check"></i></div>
                    </div>
                </div>
            </div>
        </div>

        {{-- INFO CARDS --}}
        <div class="row g-2 px-2 mb-3">
            <div class="col-12 col-lg-6">
                <div class="card-glass">
                    <div class="card-head">
                        <div>
                            <p class="card-title mb-0"><i class="bi bi-card-text"></i> Thông tin cơ bản</p>
                            <div class="card-sub">Thông tin định danh & phân loại khách hàng</div>
                        </div>
                        <span class="badge badge-ego">
                            ID: {{ $customer->id }}
                        </span>
                    </div>
                    <div class="card-body-modern">
                        <table class="table table-borderless info-table">
                            <tr>
                                <th>Tên khách hàng</th>
                                <td>{{ $customer->name ?? '-' }}</td>
                            </tr>
                            <tr>
                                <th>Biệt danh</th>
                                <td>{{ $customer->nickname ?? '-' }}</td>
                            </tr>
                            <tr>
                                <th>Số điện thoại</th>
                                <td>{{ $customer->phone ?? '-' }}</td>
                            </tr>
                            <tr>
                                <th>Email</th>
                                <td>{{ $customer->email ?? '-' }}</td>
                            </tr>
                            <tr>
                                <th>Địa chỉ</th>
                                <td>{{ $customer->address ?? '-' }}</td>
                            </tr>
                            <tr>
                                <th>Khu vực</th>
                                <td>{{ $customer->region->name ?? '-' }}</td>
                            </tr>
                            <tr>
                                <th>Loại khách</th>
                                <td>{{ $customer->customerType->name ?? '-' }}</td>
                            </tr>
                            <tr>
                                <th>Trạng thái</th>
                                <td>
                                    @if($customer->customer_status === 'member')
                                        <span class="badge badge-member">Member</span>
                                    @else
                                        <span class="badge badge-lead">Lead</span>
                                    @endif
                                </td>
                            </tr>
                            <tr>
                                <th>Đánh giá</th>
                                <td>
                                    @if($customer->is_potential)
                                        <span class="badge bg-info">⭐ Tiềm năng</span>
                                    @else
                                        -
                                    @endif
                                </td>
                            </tr>
                        </table>
                    </div>
                </div>
            </div>

            <div class="col-12 col-lg-6">
                <div class="card-glass">
                    <div class="card-head">
                        <div>
                            <p class="card-title mb-0"><i class="bi bi-telephone"></i> Thông tin liên hệ</p>
                            <div class="card-sub">Kênh liên hệ, người phụ trách, log thời gian</div>
                        </div>
                        <div class="d-flex gap-2">
                            @if(!empty($customer->facebook_link))
                                <a href="{{ $customer->facebook_link }}" target="_blank" class="btn btn-soft btn-sm">
                                    <i class="bi bi-facebook me-1"></i> Facebook
                                </a>
                            @endif
                        </div>
                    </div>
                    <div class="card-body-modern">
                        <table class="table table-borderless info-table">
                            <tr>
                                <th>Facebook</th>
                                <td>
                                    @if($customer->facebook_link)
                                        <a href="{{ $customer->facebook_link }}" target="_blank">
                                            {{ $customer->facebook_name ?? 'Link Facebook' }}
                                        </a>
                                    @else
                                        {{ $customer->facebook_name ?? '-' }}
                                    @endif
                                </td>
                            </tr>
                            <tr>
                                <th>Zalo ID</th>
                                <td>{{ $customer->zalo_id ?? '-' }}</td>
                            </tr>
                            <tr>
                                <th>Người phụ trách</th>
                                <td>{{ $customer->assignedUser->name ?? 'Chưa gán' }}</td>
                            </tr>
                            <tr>
                                <th>Ghi chú nhóm</th>
                                <td>{{ $customer->group_note ?? '-' }}</td>
                            </tr>
                            <tr>
                                <th>Ngày tạo</th>
                                <td>{{ $customer->created_at?->format('d/m/Y H:i') ?? '-' }}</td>
                            </tr>
                            <tr>
                                <th>Người tạo</th>
                                <td>{{ $customer->creator->name ?? '-' }}</td>
                            </tr>
                            <tr>
                                <th>Cập nhật lần cuối</th>
                                <td>{{ $customer->updated_at?->format('d/m/Y H:i') ?? '-' }}</td>
                            </tr>
                            @if($customer->converted_to_member_at)
                                <tr>
                                    <th>Chuyển thành hội viên</th>
                                    <td>{{ $customer->converted_to_member_at?->format('d/m/Y H:i') ?? '-' }}</td>
                                </tr>
                            @endif
                        </table>
                    </div>
                </div>
            </div>
        </div>

        {{-- TABS --}}
        <div class="row g-2 px-2">
            <div class="col-12">
                <div class="card-glass">
                    <div class="card-head">
                        <div>
                            <p class="card-title mb-0"><i class="bi bi-layout-text-sidebar-reverse"></i> Dữ liệu chi tiết</p>
                            <div class="card-sub">Lead • Giao dịch • Sản phẩm bảo hành</div>
                        </div>

                        <ul class="nav nav-pills" id="customerTabs" role="tablist">
                            <li class="nav-item" role="presentation">
                                <button class="nav-link active" id="tab-tx" data-bs-toggle="pill" data-bs-target="#pane-tx" type="button" role="tab">
                                    <i class="bi bi-receipt-cutoff me-1"></i> Giao dịch
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="tab-leads" data-bs-toggle="pill" data-bs-target="#pane-leads" type="button" role="tab">
                                    <i class="bi bi-chat-dots me-1"></i> Leads
                                    @if(!empty($customer->leads)) ({{ $customer->leads->count() }}) @endif
                                </button>
                            </li>
                        </ul>
                    </div>

                    <div class="card-body-modern">
                        <div class="tab-content" id="customerTabsContent">

                            {{-- ===== TAB: GIAO DỊCH ===== --}}
                            <div class="tab-pane fade show active" id="pane-tx" role="tabpanel">

                                {{-- “Đơn hàng mới nhất” dạng card nhỏ --}}
                                <div class="row g-2 mb-3">
                                    <div class="col-12 col-lg-6">
                                        <div class="card-glass p-3">
                                            <div class="d-flex justify-content-between align-items-center">
                                                <div>
                                                    <div class="kpi-label">Đơn hàng mới nhất</div>
                                                    <div class="mt-1 fw-bold" style="font-size:14px;">
                                                        @if($customer->latestOrder)
                                                            {{ $customer->latestOrder->order_code ?? '-' }}
                                                        @else
                                                            Chưa có đơn hàng
                                                        @endif
                                                    </div>
                                                    <div class="kpi-sub">
                                                        @if($customer->latestOrder?->order_date)
                                                            Ngày đặt: {{ \Carbon\Carbon::parse($customer->latestOrder->order_date)->format('d/m/Y') }}
                                                        @else
                                                            Ngày đặt: -
                                                        @endif
                                                    </div>
                                                </div>
                                                <div class="title-badge"><i class="bi bi-bag-check"></i></div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="col-12 col-lg-6">
                                        <div class="card-glass p-3">
                                            <div class="d-flex justify-content-between align-items-center">
                                                <div>
                                                    <div class="kpi-label">Tổng tiền đơn gần nhất</div>
                                                    <div class="kpi-value">
                                                        @if($customer->latestOrder)
                                                            {{ number_format($customer->latestOrder->total_amount ?? 0, 0, ',', '.') }} đ
                                                        @else
                                                            --
                                                        @endif
                                                    </div>
                                                    <div class="kpi-sub">Hiển thị đơn gần nhất (đang có)</div>
                                                </div>
                                                <div class="title-badge"><i class="bi bi-cash-stack"></i></div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                {{-- LỊCH SỬ GIAO DỊCH (UI-only, backend map sau) --}}
                                <div class="d-flex justify-content-between align-items-center mb-2 flex-wrap gap-2">
                                    <div>
                                        <div class="fw-black" style="font-weight:950; color:var(--ink);">
                                            Lịch sử giao dịch
                                        </div>
                                        <div class="tx-muted">Danh sách đơn + sản phẩm + bảo hành</div>
                                    </div>
                                    <button class="btn btn-soft" type="button" onclick="openTxDetailModal()">
                                        <i class="bi bi-clock-history me-1"></i> Xem chi tiết (UI)
                                    </button>
                                </div>

                                <div class="table-wrap">
                                    <div class="table-responsive">
                                        <table class="table table-bordered table-hover align-middle table-modern text-center">
                                            <thead class="small">
                                            <tr>
                                                <th>Mã đơn</th>
                                                <th>Ngày đặt</th>
                                                <th class="cell-left">Sản phẩm</th>
                                                <th>Số lượng</th>
                                                <th>Tổng tiền</th>
                                                <th>Bảo hành (Inverter / Pin)</th>
                                            </tr>
                                            </thead>
                                           <tbody>
@php
    $orders = $customer->orders ?? collect();
@endphp

@if($orders->count() > 0)
    @foreach($orders as $o)
        @php
            // items: nếu quan hệ tên khác thì bạn báo mình, mình đổi đúng
            $items = $o->orderItems ?? collect();

            $productNames = $items->pluck('product.name')->filter()->unique()->values()->implode(', ');
            $qtyTotal = (int) $items->sum('quantity');

            // tổng tiền: ưu tiên total_amount, nếu không có thì tính tạm = sum(qty*price)
            $total = $o->total_amount
                ?? $items->sum(function($it){
                    return ((float)($it->quantity ?? 0)) * ((float)($it->price ?? 0));
                });

            $orderCode = $o->order_code ?? $o->code ?? ('#'.$o->id);
            $orderDate = $o->order_date ?? $o->created_at;
        @endphp

        <tr>
            <td class="fw-bold">{{ $orderCode }}</td>
            <td>
                {{ $orderDate ? \Carbon\Carbon::parse($orderDate)->format('d/m/Y') : '-' }}
            </td>
            <td class="cell-left">{{ $productNames ?: '-' }}</td>
            <td>{{ $qtyTotal ?: '-' }}</td>
            <td class="fw-bold text-success">{{ number_format((float)$total, 0, ',', '.') }} đ</td>
            <td>-</td>
        </tr>
    @endforeach
@else
    <tr>
        <td colspan="6" class="text-muted py-4">
            Chưa có đơn hàng / giao dịch nào cho khách này.
        </td>
    </tr>
@endif
</tbody>
                                        </table>
                                    </div>
                                </div>

                                <div class="small text-muted mt-2">
                                    Gợi ý backend: join orders → order_items → products, group theo order để hiển thị sản phẩm và đếm Inverter/Pin.
                                </div>
                            </div>

                            {{-- ===== TAB: LEADS ===== --}}
                            <div class="tab-pane fade" id="pane-leads" role="tabpanel">
                                @if($customer->latestLead)
                                    <div class="card-glass p-3 mb-3">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <div>
                                                <div class="kpi-label">Lead mới nhất</div>
                                                <div class="fw-bold" style="font-size:14px;">
                                                    {{ $customer->latestLead->status->name ?? '—' }}
                                                </div>
                                                <div class="kpi-sub">
                                                    Ngày liên hệ:
                                                    {{ $customer->latestLead->contact_date ? \Carbon\Carbon::parse($customer->latestLead->contact_date)->format('d/m/Y') : '-' }}
                                                    • Nguồn: {{ $customer->latestLead->source->name ?? '-' }}
                                                </div>
                                            </div>
                                            <div class="title-badge"><i class="bi bi-chat-left-text"></i></div>
                                        </div>
                                    </div>
                                @endif

                                @if($customer->leads && $customer->leads->count() > 0)
                                    <div class="table-wrap">
                                        <div class="table-responsive">
                                            <table class="table table-bordered table-hover align-middle table-modern text-center">
                                                <thead class="small">
                                                <tr>
                                                    <th>Ngày liên hệ</th>
                                                    <th>Nguồn</th>
                                                    <th>Trạng thái</th>
                                                    <th class="cell-note">Ghi chú</th>
                                                </tr>
                                                </thead>
                                                <tbody>
                                                @foreach($customer->leads as $lead)
                                                    <tr>
                                                        <td>{{ $lead->contact_date ? \Carbon\Carbon::parse($lead->contact_date)->format('d/m/Y') : '-' }}</td>
                                                        <td>{{ $lead->source->name ?? '-' }}</td>
                                                        <td>{{ $lead->status->name ?? '-' }}</td>
                                                        <td class="cell-note">{{ \Illuminate\Support\Str::limit($lead->note, 120) }}</td>
                                                    </tr>
                                                @endforeach
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                @else
                                    <div class="text-muted py-4">
                                        Chưa có lịch sử leads.
                                    </div>
                                @endif
                            </div>

                        </div>
                    </div>

                </div>
            </div>
        </div>

    </div>
</div>

{{-- MODAL: Giao dịch (UI-only) --}}
<div class="modal fade modal-ego" id="txDetailModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <div class="d-flex flex-column">
                    <h5 class="modal-title mb-1">
                        <i class="bi bi-receipt-cutoff me-1"></i> Giao dịch • Chi tiết
                    </h5>
                    <div class="tx-muted">
                        Khách: <b>{{ $customer->name ?? '—' }}</b>
                        @if(!empty($customer->phone)) • {{ $customer->phone }} @endif
                    </div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <div class="modal-body">
                <div class="card-glass p-3 mb-3">
                    <div class="d-flex flex-wrap gap-2 align-items-center">
                        <span class="kpi-chip"><i class="bi bi-receipt"></i> Tổng đơn: {{ $tx['total_orders'] }}</span>
                        <span class="kpi-chip"><i class="bi bi-box-seam"></i> Tổng SP: {{ $tx['total_products'] }}</span>
                        <span class="kpi-chip"><i class="bi bi-cash-coin"></i> Tổng tiền: {{ $tx['total_amount'] }}</span>
                        <span class="kpi-chip"><i class="bi bi-lightning-charge"></i> BH Inverter: {{ $tx['warranty_inverter'] }}</span>
                        <span class="kpi-chip"><i class="bi bi-battery-charging"></i> BH Pin: {{ $tx['warranty_battery'] }}</span>
                    </div>
                </div>

                <div class="table-wrap">
                    <div class="table-responsive">
                        <table class="table table-bordered table-hover align-middle table-modern text-center">
                            <thead class="small">
                            <tr>
                                <th>Mã đơn</th>
                                <th>Ngày đặt</th>
                                <th class="cell-left">Sản phẩm</th>
                                <th>Số lượng</th>
                                <th>Tổng tiền</th>
                                <th>Bảo hành</th>
                            </tr>
                            </thead>
                            <tbody>
                            <tr>
                                <td colspan="6" class="text-muted py-4">
                                    UI-only. Sau này bạn load dữ liệu thật để render bảng này (orders + items).
                                </td>
                            </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="small text-muted mt-2">
                    Tip backend: trả JSON gồm orders[{code,date,total,items[{name,qty,warranty_group}]}] để build “Sản phẩm” + “Bảo hành”.
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-ghost" data-bs-dismiss="modal">Đóng</button>
            </div>
        </div>
    </div>
</div>

<script>
    function openTxDetailModal(){
        const el = document.getElementById('txDetailModal');
        const modal = new bootstrap.Modal(el);
        modal.show();
    }
</script>

<!-- EGO_CUSTOMER_MODAL_STYLE_START -->
<style id="ego-customer-modal-style">
.ego-customer-modal{
    border:0 !important;
    border-radius:22px !important;
    overflow:hidden !important;
    box-shadow:0 30px 80px rgba(15,23,42,.28) !important;
    background:#f8fafc !important;
}

.ego-customer-modal .modal-header{
    background:linear-gradient(135deg,#0ea5e9 0%, #2563eb 100%) !important;
    color:#fff !important;
    border-bottom:0 !important;
    padding:18px 24px !important;
}

.ego-customer-modal .modal-title,
.ego-customer-modal h5,
.ego-customer-modal h4{
    color:#fff !important;
    font-weight:800 !important;
    font-size:30px !important;
    margin:0 !important;
}

.ego-customer-modal .btn-close,
.ego-customer-modal .close{
    filter:brightness(0) invert(1) !important;
    opacity:1 !important;
}

.ego-customer-modal .modal-body{
    background:#f8fafc !important;
    padding:18px !important;
    max-height:78vh !important;
    overflow-y:auto !important;
}

.ego-customer-modal .ego-customer-panel{
    background:#fff !important;
    border:1px solid #e2e8f0 !important;
    border-radius:18px !important;
    padding:16px 16px 12px !important;
    margin-bottom:16px !important;
    box-shadow:0 10px 26px rgba(15,23,42,.05) !important;
}

.ego-customer-modal .ego-customer-panel-title{
    display:flex !important;
    align-items:center !important;
    gap:8px !important;
    font-size:20px !important;
    font-weight:800 !important;
    color:#0f172a !important;
    margin-bottom:14px !important;
    padding-bottom:10px !important;
    border-bottom:1px solid #eef2f7 !important;
}

.ego-customer-modal label,
.ego-customer-modal .form-label{
    font-size:14px !important;
    font-weight:700 !important;
    color:#334155 !important;
    margin-bottom:8px !important;
}

.ego-customer-modal .form-control,
.ego-customer-modal .form-select,
.ego-customer-modal input,
.ego-customer-modal select,
.ego-customer-modal textarea{
    border-radius:14px !important;
    border:1px solid #dbe4ee !important;
    background:#fff !important;
    box-shadow:none !important;
    min-height:44px !important;
    padding:10px 14px !important;
    font-size:14px !important;
    color:#0f172a !important;
}

.ego-customer-modal textarea{
    min-height:88px !important;
    resize:vertical !important;
}

.ego-customer-modal .form-control:focus,
.ego-customer-modal .form-select:focus,
.ego-customer-modal input:focus,
.ego-customer-modal select:focus,
.ego-customer-modal textarea:focus{
    border-color:#38bdf8 !important;
    box-shadow:0 0 0 4px rgba(56,189,248,.14) !important;
    outline:none !important;
}

.ego-customer-modal ::placeholder{
    color:#94a3b8 !important;
}

.ego-customer-modal .text-muted,
.ego-customer-modal small,
.ego-customer-modal .form-text{
    color:#64748b !important;
    font-size:12px !important;
}

.ego-customer-modal .modal-footer{
    background:#fff !important;
    border-top:1px solid #e2e8f0 !important;
    padding:14px 18px !important;
}

.ego-customer-modal .btn{
    border-radius:14px !important;
    min-height:42px !important;
    padding:10px 16px !important;
    font-weight:700 !important;
}

.ego-customer-modal .btn-primary,
.ego-customer-modal .btn-success{
    background:linear-gradient(135deg,#06b6d4 0%, #2563eb 100%) !important;
    border:0 !important;
    box-shadow:0 12px 28px rgba(37,99,235,.22) !important;
}

.ego-customer-modal .btn-secondary,
.ego-customer-modal .btn-light{
    background:#f8fafc !important;
    border:1px solid #dbe4ee !important;
    color:#0f172a !important;
}
</style>

<script id="ego-customer-modal-style-js">
(function(){
    function beautifyCustomerModal(){
        document.querySelectorAll('.modal').forEach(function(modal){
            const text = (modal.innerText || '').trim();

            if(
                text.includes('Thêm mới khách hàng') ||
                text.includes('Thông tin cơ bản')
            ){
                const content = modal.querySelector('.modal-content');
                if(content) content.classList.add('ego-customer-modal');

                const body = modal.querySelector('.modal-body');
                if(body){
                    body.querySelectorAll(':scope > div').forEach(function(el){
                        if(el.querySelector('input, select, textarea')){
                            el.classList.add('ego-customer-panel');
                        }
                    });

                    body.querySelectorAll('.ego-customer-panel').forEach(function(panel){
                        const firstHeading = panel.querySelector('h1,h2,h3,h4,h5,h6,.fw-bold,strong,legend');
                        if(firstHeading && !firstHeading.classList.contains('ego-customer-panel-title')){
                            firstHeading.classList.add('ego-customer-panel-title');
                        }
                    });
                }
            }
        });
    }

    document.addEventListener('DOMContentLoaded', beautifyCustomerModal);
    beautifyCustomerModal();

    const obs = new MutationObserver(function(){
        beautifyCustomerModal();
    });

    obs.observe(document.body, {childList:true, subtree:true});
})();
</script>
<!-- EGO_CUSTOMER_MODAL_STYLE_END -->

@endsection
