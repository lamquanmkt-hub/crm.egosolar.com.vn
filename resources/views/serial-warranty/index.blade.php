@extends('layouts.app')

@section('content')
@php
    $today = now()->startOfDay();
    $user = auth()->user();

    $roleText = strtolower(implode(' ', array_filter([
        $user->role ?? null,
        $user->role_name ?? null,
        $user->department ?? null,
        $user->current_department ?? null,
        $user->position ?? null,
        $user->type ?? null,
        $user->permission ?? null,
        $user->email ?? null,
        $user->name ?? null,
    ])));

    $canManageWarranty = $user && (
        !empty($user->is_admin)
        || !empty($user->is_super_admin)
        || str_contains($roleText, 'admin')
        || str_contains($roleText, 'kho')
        || str_contains($roleText, 'warehouse')
        || str_contains($roleText, 'lamquanmkt')
        || str_contains($roleText, 'lâm quân')
        || str_contains($roleText, 'lam quân')
    );

    $totalSerial = (int) ($stats['total'] ?? 0);
    $soldSerial = (int) ($stats['sold'] ?? 0);
    $activeWarranty = (int) ($stats['warranty_active'] ?? 0);
    $q = $q ?? request('q', '');
    $productId = $productId ?? (int) request('product_id', 0);
@endphp

<style>
    :root{
        --sw-navy:#071735;
        --sw-ink:#0f172a;
        --sw-muted:#64748b;
        --sw-line:#dce8f1;
        --sw-soft:#f5f8fc;
        --sw-card:#ffffff;
        --sw-teal:#12a8aa;
        --sw-blue:#2563eb;
        --sw-green:#16a34a;
        --sw-amber:#f59e0b;
        --sw-red:#ef4444;
        --sw-shadow:0 18px 48px rgba(15,23,42,.08);
    }

    .sw-page{
        padding:20px;
        background:
            radial-gradient(circle at top left, rgba(18,168,170,.14), transparent 34%),
            radial-gradient(circle at top right, rgba(37,99,235,.10), transparent 28%),
            linear-gradient(180deg,#f8fbff 0%,#f1f6fb 100%);
        min-height:calc(100vh - 80px);
    }

    .sw-wrap{
        max-width:1560px;
        margin:0 auto;
    }

    .sw-hero{
        overflow:hidden;
        border-radius:26px;
        padding:22px;
        margin-bottom:16px;
        background:linear-gradient(135deg,rgba(7,23,53,.98),rgba(9,79,104,.96) 58%,rgba(18,168,170,.94));
        box-shadow:0 22px 60px rgba(7,23,53,.18);
        color:#fff;
    }

    .sw-hero-grid{
        display:grid;
        grid-template-columns:minmax(0,1fr) 460px;
        gap:18px;
        align-items:stretch;
    }

    .sw-kicker{
        display:inline-flex;
        align-items:center;
        gap:8px;
        padding:8px 12px;
        border-radius:999px;
        background:rgba(255,255,255,.12);
        border:1px solid rgba(255,255,255,.22);
        color:#d7fffb;
        font-size:12px;
        font-weight:950;
        text-transform:uppercase;
        margin-bottom:12px;
    }

    .sw-title{
        margin:0;
        font-size:36px;
        line-height:1.06;
        font-weight:950;
        letter-spacing:-.045em;
        color:#fff;
    }

    .sw-sub{
        max-width:850px;
        margin:10px 0 0;
        color:#d6edf4;
        font-size:14px;
        font-weight:750;
        line-height:1.55;
    }

    .sw-role{
        display:inline-flex;
        align-items:center;
        gap:8px;
        margin-top:16px;
        background:rgba(255,255,255,.14);
        border:1px solid rgba(255,255,255,.22);
        color:#fff;
        border-radius:999px;
        padding:9px 13px;
        font-size:12px;
        font-weight:900;
    }

    .sw-hero-stats{
        display:grid;
        grid-template-columns:repeat(3,1fr);
        gap:10px;
    }

    .sw-hero-stat{
        padding:16px;
        border-radius:20px;
        background:rgba(255,255,255,.12);
        border:1px solid rgba(255,255,255,.20);
    }

    .sw-hero-stat span{
        display:block;
        color:#c6f7f1;
        font-size:11px;
        font-weight:950;
        text-transform:uppercase;
    }

    .sw-hero-stat b{
        display:block;
        margin-top:8px;
        font-size:30px;
        line-height:1;
        color:#fff;
    }

    .sw-card{
        background:rgba(255,255,255,.96);
        border:1px solid rgba(220,232,241,.95);
        border-radius:24px;
        box-shadow:var(--sw-shadow);
        backdrop-filter:blur(10px);
    }

    .sw-search{
        padding:18px;
        margin-bottom:14px;
        background:linear-gradient(135deg,#ffffff 0%,#f8ffff 100%);
    }

    .sw-section-head{
        display:flex;
        align-items:flex-start;
        justify-content:space-between;
        gap:14px;
        margin-bottom:14px;
    }

    .sw-section-title{
        display:flex;
        align-items:center;
        gap:10px;
        margin:0;
        color:var(--sw-ink);
        font-size:19px;
        font-weight:950;
        letter-spacing:-.025em;
    }

    .sw-section-icon{
        width:38px;
        height:38px;
        border-radius:15px;
        display:inline-flex;
        align-items:center;
        justify-content:center;
        color:#fff;
        background:linear-gradient(135deg,var(--sw-teal),var(--sw-blue));
        box-shadow:0 12px 24px rgba(18,168,170,.20);
    }

    .sw-section-desc{
        margin:6px 0 0;
        color:var(--sw-muted);
        font-size:13px;
        font-weight:750;
        line-height:1.5;
    }

    .sw-search-form{
        display:grid;
        grid-template-columns:minmax(280px,1fr) auto auto;
        gap:10px;
        align-items:end;
    }

    .sw-label{
        display:block;
        margin-bottom:6px;
        font-size:11px;
        font-weight:950;
        color:#52657d;
        text-transform:uppercase;
        letter-spacing:.065em;
    }

    .sw-input,
    .sw-select,
    .sw-textarea{
        width:100%;
        border:1px solid var(--sw-line);
        background:#fff;
        color:#0f172a;
        outline:none;
        font-weight:800;
    }

    .sw-input,
    .sw-select{
        height:42px;
        border-radius:13px;
        padding:0 12px;
        font-size:13px;
    }

    .sw-textarea{
        min-height:96px;
        border-radius:14px;
        padding:12px;
        font-size:13px;
        line-height:1.45;
        resize:vertical;
    }

    .sw-input:focus,
    .sw-select:focus,
    .sw-textarea:focus{
        border-color:#14b8a6;
        box-shadow:0 0 0 4px rgba(20,184,166,.12);
    }

    .sw-btn{
        height:42px;
        border:0;
        border-radius:13px;
        padding:0 15px;
        display:inline-flex;
        align-items:center;
        justify-content:center;
        gap:8px;
        cursor:pointer;
        text-decoration:none !important;
        font-size:13px;
        font-weight:950;
        white-space:nowrap;
        background:#12a8aa;
        color:#fff !important;
        box-shadow:0 12px 24px rgba(18,168,170,.16);
        transition:.15s ease;
    }

    .sw-btn:hover{
        transform:translateY(-1px);
        filter:saturate(1.05);
    }

    .sw-btn.light{
        background:#fff;
        color:#0f766e !important;
        border:1px solid #bde8e5;
        box-shadow:none;
    }

    .sw-btn.green{
        background:linear-gradient(135deg,#16a34a,#22c55e);
    }

    .sw-btn.dark{
        background:#071735;
    }

    .sw-btn.danger{
        background:#fff1f2;
        color:#e11d48 !important;
        border:1px solid #fecdd3;
        box-shadow:none;
    }

    .sw-btn.mini{
        height:36px;
        border-radius:11px;
        padding:0 12px;
        font-size:12px;
    }

    .sw-mini-note{
        margin-top:12px;
        padding:10px 12px;
        border:1px dashed #9ee4df;
        border-radius:14px;
        background:#effffc;
        color:#0f766e;
        font-size:12px;
        font-weight:800;
    }

    .sw-toolbar{
        display:flex;
        justify-content:flex-end;
        margin:0 0 14px;
    }

    .sw-table-card{
        width:100%;
        overflow:hidden;
    }

    .sw-table-head{
        padding:18px;
        display:flex;
        align-items:center;
        justify-content:space-between;
        gap:12px;
        border-bottom:1px solid #e5eef6;
    }

    .sw-table-title{
        margin:0;
        color:var(--sw-ink);
        font-size:22px;
        font-weight:950;
        letter-spacing:-.03em;
    }

    .sw-table-sub{
        margin-top:4px;
        color:#607087;
        font-size:13px;
        font-weight:800;
    }

    .sw-table-wrap{
        width:100%;
        overflow:auto;
    }

    .sw-table{
        width:100%;
        min-width:1320px;
        border-collapse:separate;
        border-spacing:0;
        background:#fff;
    }

    .sw-table thead th{
        position:sticky;
        top:0;
        z-index:2;
        background:#f8fafc;
        color:#52657d;
        font-size:11px;
        font-weight:950;
        text-align:left;
        padding:13px 14px;
        text-transform:uppercase;
        letter-spacing:.055em;
        border-bottom:1px solid #e5eef6;
    }

    .sw-table tbody td{
        padding:14px;
        border-bottom:1px solid #eef4f8;
        vertical-align:middle;
        color:#0f172a;
    }

    .sw-table tbody tr:hover{
        background:#fbfdff;
    }

    .sw-code{
        display:inline-flex;
        align-items:center;
        max-width:180px;
        padding:7px 10px;
        border-radius:999px;
        background:#eff6ff;
        border:1px solid #dbeafe;
        color:#0f172a;
        font-size:11px;
        font-weight:950;
        white-space:nowrap;
        overflow:hidden;
        text-overflow:ellipsis;
    }

    .sw-product{
        font-size:15px;
        font-weight:950;
        color:#0f172a;
        line-height:1.35;
    }

    .sw-name{
        font-size:14px;
        font-weight:950;
        color:#0f172a;
        line-height:1.35;
    }

    .sw-muted{
        margin-top:4px;
        color:#64748b;
        font-size:12px;
        font-weight:800;
    }

    .sw-note{
        max-width:240px;
        color:#334155;
        font-size:13px;
        font-weight:800;
        line-height:1.45;
        white-space:normal;
    }

    .sw-badge{
        display:inline-flex;
        align-items:center;
        justify-content:center;
        padding:6px 10px;
        border-radius:999px;
        font-size:11px;
        font-weight:950;
        white-space:nowrap;
    }

    .sw-badge.ok{
        background:#dcfce7;
        color:#15803d;
    }

    .sw-badge.warn{
        background:#fef3c7;
        color:#b45309;
    }

    .sw-badge.bad{
        background:#fee2e2;
        color:#b91c1c;
    }

    .sw-order-link{
        display:inline-flex;
        align-items:center;
        gap:7px;
        max-width:180px;
        padding:8px 10px;
        border-radius:999px;
        background:linear-gradient(135deg,#eff6ff,#ecfeff);
        border:1px solid #bfdbfe;
        color:#1d4ed8 !important;
        text-decoration:none !important;
        font-size:12px;
        font-weight:950;
        line-height:1.15;
        box-shadow:0 8px 18px rgba(37,99,235,.10);
    }

    .sw-order-text{
        overflow:hidden;
        text-overflow:ellipsis;
        white-space:nowrap;
    }

    .sw-order-empty{
        display:inline-flex;
        align-items:center;
        justify-content:center;
        width:28px;
        height:28px;
        border-radius:999px;
        background:#f1f5f9;
        color:#64748b;
        font-weight:950;
    }

    .sw-actions{
        display:flex;
        align-items:center;
        gap:8px;
        flex-wrap:wrap;
    }

    .sw-empty{
        padding:54px 20px;
        text-align:center;
        color:#64748b;
        font-size:14px;
        font-weight:750;
    }

    .sw-empty b{
        display:block;
        color:#0f172a;
        font-size:18px;
        margin-bottom:6px;
    }

    .sw-alert{
        padding:12px 14px;
        border-radius:16px;
        margin-bottom:12px;
        font-size:13px;
        font-weight:850;
    }

    .sw-alert.success{
        background:#ecfdf5;
        color:#047857;
        border:1px solid #a7f3d0;
    }

    .sw-alert.error{
        background:#fef2f2;
        color:#b91c1c;
        border:1px solid #fecaca;
    }

    .sw-modal-backdrop{
        position:fixed;
        inset:0;
        z-index:9990;
        display:none;
        background:rgba(15,23,42,.58);
        backdrop-filter:blur(4px);
    }

    .sw-modal-backdrop.show{
        display:block;
    }

    .sw-modal{
        position:fixed;
        top:50%;
        left:50%;
        z-index:9991;
        width:min(860px, calc(100vw - 28px));
        max-height:calc(100vh - 42px);
        overflow:auto;
        transform:translate(-50%,-50%);
        display:none;
        background:#fff;
        border:1px solid #dbeafe;
        border-radius:26px;
        padding:22px;
        box-shadow:0 34px 100px rgba(15,23,42,.34);
    }

    .sw-modal.show{
        display:block;
    }

    .sw-modal-head{
        display:flex;
        align-items:flex-start;
        justify-content:space-between;
        gap:14px;
        margin-bottom:16px;
        padding-bottom:14px;
        border-bottom:1px solid #e2e8f0;
    }

    .sw-modal-title{
        margin:0;
        font-size:20px;
        font-weight:950;
        color:#0f172a;
    }

    .sw-modal-sub{
        margin-top:5px;
        color:#64748b;
        font-size:13px;
        font-weight:800;
    }

    .sw-modal-close{
        width:38px;
        height:38px;
        border-radius:999px;
        border:1px solid #dbeafe;
        background:#fff;
        color:#0f172a;
        font-size:24px;
        line-height:1;
        font-weight:950;
        cursor:pointer;
        box-shadow:0 10px 22px rgba(15,23,42,.10);
    }

    .sw-form-grid{
        display:grid;
        grid-template-columns:repeat(2,minmax(0,1fr));
        gap:13px;
    }

    .sw-form-grid .wide{
        grid-column:1 / -1;
    }

    .sw-modal-actions{
        display:flex;
        justify-content:flex-end;
        gap:10px;
        margin-top:16px;
        padding-top:14px;
        border-top:1px solid #e2e8f0;
    }

    body.sw-modal-open{
        overflow:hidden;
    }

    @media(max-width:1200px){
        .sw-hero-grid{
            grid-template-columns:1fr;
        }

        .sw-hero-stats{
            grid-template-columns:repeat(3,1fr);
        }
    }

    @media(max-width:820px){
        .sw-page{
            padding:12px;
        }

        .sw-title{
            font-size:28px;
        }

        .sw-hero-stats,
        .sw-search-form,
        .sw-form-grid{
            grid-template-columns:1fr;
        }

        .sw-btn{
            width:100%;
        }

        .sw-table-head,
        .sw-modal-actions{
            flex-direction:column;
            align-items:stretch;
        }
    }
</style>

<div class="sw-page">
    <div class="sw-wrap">
        <div class="sw-hero">
            <div class="sw-hero-grid">
                <div>
                    <div class="sw-kicker">⚡ EGO Solar Warranty Center</div>
                    <h1 class="sw-title">Tra cứu Serial / Bảo hành</h1>
                    <p class="sw-sub">
                        Quản lý serial đã bán, thời hạn bảo hành và bổ sung nhanh serial bị quên nhập kho.
                        Bảng danh sách đã được làm rộng full khung, form thêm/sửa dùng popup nhẹ để không lag.
                    </p>
                    <div class="sw-role">
                        {{ $canManageWarranty ? '✅ Quyền Admin / Kho' : '🔎 Quyền Sales tra cứu' }}
                    </div>
                </div>

                <div class="sw-hero-stats">
                    <div class="sw-hero-stat">
                        <span>Serial đã bán</span>
                        <b>{{ number_format($totalSerial) }}</b>
                    </div>
                    <div class="sw-hero-stat">
                        <span>Đã bán / đã xuất</span>
                        <b>{{ number_format($soldSerial) }}</b>
                    </div>
                    <div class="sw-hero-stat">
                        <span>Còn bảo hành</span>
                        <b>{{ number_format($activeWarranty) }}</b>
                    </div>
                </div>
            </div>
        </div>

        @if(session('success'))
            <div class="sw-alert success">{{ session('success') }}</div>
        @endif

        @if(session('error'))
            <div class="sw-alert error">{{ session('error') }}</div>
        @endif

        @if($errors->any())
            <div class="sw-alert error">
                @foreach($errors->all() as $error)
                    <div>{{ $error }}</div>
                @endforeach
            </div>
        @endif

        <div class="sw-card sw-search">
            <div class="sw-section-head">
                <div>
                    <h2 class="sw-section-title">
                        <span class="sw-section-icon">🔍</span>
                        Nhập serial cần kiểm tra
                    </h2>
                    <p class="sw-section-desc">
                        Có thể tra theo serial, mã sản phẩm, tên khách hàng, số điện thoại hoặc mã đơn hàng.
                    </p>
                </div>
            </div>

            <form method="GET" action="{{ route('serial-warranty.index') }}" class="sw-search-form">
                <div>
                    <label class="sw-label">Mã serial / khách hàng / đơn hàng</label>
                    <input class="sw-input" type="text" name="q" value="{{ $q }}" placeholder="VD: SN01230, INV123456, Đạt 123456...">
                </div>

                <button class="sw-btn" type="submit">Tra cứu</button>
                <a class="sw-btn light" href="{{ route('serial-warranty.index') }}">Xóa lọc</a>
            </form>

            <div class="sw-mini-note">
                Mẹo: Sales chỉ thấy serial đã bán / đã xuất. Admin/Kho có quyền sửa bảo hành, xóa khỏi tra cứu và bổ sung serial thủ công.
            </div>
        </div>

        @if($canManageWarranty)
            <div class="sw-toolbar">
                <button type="button" class="sw-btn green" onclick="openAddSerialModal()">＋ Thêm serial bảo hành</button>
            </div>
        @endif

        <div class="sw-card sw-table-card">
            <div class="sw-table-head">
                <div>
                    <h2 class="sw-table-title">Danh sách kết quả</h2>
                    <div class="sw-table-sub">{{ $serials->total() }} kết quả đã bán / đã xuất</div>
                </div>
                <a class="sw-btn light" href="{{ route('serial-warranty.index') }}">Làm mới</a>
            </div>

            <div class="sw-table-wrap">
                <table class="sw-table">
                    <thead>
                        <tr>
                            <th>Serial</th>
                            <th>Sản phẩm</th>
                            <th>Trạng thái</th>
                            <th>Khách hàng</th>
                            <th>Đơn hàng</th>
                            <th>Bảo hành</th>
                            <th>Ghi chú</th>
                            @if($canManageWarranty)
                                <th>Thao tác</th>
                            @endif
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($serials as $row)
                            @php
                                $end = $row->warranty_end_at ? \Carbon\Carbon::parse($row->warranty_end_at)->startOfDay() : null;
                                $daysLeft = $end ? $today->diffInDays($end, false) : null;
                                $orderLabel = $row->order_code ?: ($row->order_id ? ('Đơn #'.$row->order_id) : '—');
                            @endphp

                            <tr>
                                <td>
                                    <span class="sw-code">{{ $row->serial_code ?: ('#'.$row->id) }}</span>
                                </td>

                                <td>
                                    <div class="sw-product">{{ $row->product_name ?: '—' }}</div>
                                    <div class="sw-muted">{{ $row->product_sku ?: 'Chưa có SKU' }}</div>
                                </td>

                                <td>
                                    <span class="sw-badge warn">Đã bán / đã xuất</span>
                                </td>

                                <td>
                                    @if($row->customer_name)
                                        <div class="sw-name">{{ $row->customer_name }}</div>
                                        <div class="sw-muted">{{ $row->customer_phone }}</div>
                                    @else
                                        <div class="sw-muted">—</div>
                                    @endif
                                </td>

                                <td>
                                    @if($row->order_id && \Illuminate\Support\Facades\Route::has('orders.show'))
                                        <a class="sw-order-link" href="{{ route('orders.show', $row->order_id) }}" title="Mở đơn hàng {{ $orderLabel }}">
                                            <span>↗</span>
                                            <span class="sw-order-text">{{ $orderLabel }}</span>
                                        </a>
                                    @elseif($row->order_id)
                                        <a class="sw-order-link" href="{{ url('/orders/'.$row->order_id) }}" title="Mở đơn hàng {{ $orderLabel }}">
                                            <span>↗</span>
                                            <span class="sw-order-text">{{ $orderLabel }}</span>
                                        </a>
                                    @else
                                        <span class="sw-order-empty">—</span>
                                    @endif
                                </td>

                                <td>
                                    @if($end)
                                        <span class="sw-badge {{ $daysLeft >= 0 ? 'ok' : 'bad' }}">
                                            @if($daysLeft >= 0)
                                                Còn {{ number_format($daysLeft) }} ngày
                                            @else
                                                Hết hạn {{ number_format(abs($daysLeft)) }} ngày
                                            @endif
                                        </span>

                                        <div class="sw-muted">
                                            {{ $row->warranty_start_at ? \Carbon\Carbon::parse($row->warranty_start_at)->format('d/m/Y') : '—' }}
                                            → {{ \Carbon\Carbon::parse($row->warranty_end_at)->format('d/m/Y') }}
                                        </div>
                                    @else
                                        <span class="sw-badge warn">Chưa kích hoạt</span>
                                    @endif
                                </td>

                                <td>
                                    @if(trim((string)($row->note ?? '')) !== '')
                                        <div class="sw-note">{{ $row->note }}</div>
                                    @else
                                        <span class="sw-muted">—</span>
                                    @endif
                                </td>

                                @if($canManageWarranty)
                                    <td>
                                        <div class="sw-actions">
                                            <a class="sw-btn mini dark" href="{{ route('serial-warranty.index', ['q' => $row->serial_code]) }}">Xem</a>

                                            <button
                                                type="button"
                                                class="sw-btn mini"
                                                data-id="{{ $row->id }}"
                                                data-serial="{{ e($row->serial_code ?: ('#'.$row->id)) }}"
                                                data-product-id="{{ $row->product_id }}"
                                                data-customer-id="{{ $row->customer_id }}"
                                                data-order-id="{{ $row->order_id }}"
                                                data-sold-at="{{ $row->sold_at ? \Carbon\Carbon::parse($row->sold_at)->format('Y-m-d') : '' }}"
                                                data-start="{{ $row->warranty_start_at ? \Carbon\Carbon::parse($row->warranty_start_at)->format('Y-m-d') : now()->format('Y-m-d') }}"
                                                data-months="{{ $row->warranty_months ?: 60 }}"
                                                data-end="{{ $row->warranty_end_at ? \Carbon\Carbon::parse($row->warranty_end_at)->format('Y-m-d') : '' }}"
                                                data-note="{{ e($row->note ?? '') }}"
                                                onclick="openEditWarrantyModal(this)"
                                            >
                                                Sửa BH
                                            </button>

                                            <form method="POST" action="{{ route('serial-warranty.serial.remove-from-lookup', $row->id) }}" onsubmit="return confirm('Xóa serial này khỏi trang tra cứu?')">
                                                @csrf
                                                <button type="submit" class="sw-btn mini danger">Xóa</button>
                                            </form>
                                        </div>
                                    </td>
                                @endif
                            </tr>
                        @empty
                            <tr>
                                <td colspan="{{ $canManageWarranty ? 8 : 7 }}">
                                    <div class="sw-empty">
                                        @if($q !== '')
                                            <b>Không tìm thấy serial phù hợp</b>
                                            Thử kiểm tra lại mã serial, mã đơn hàng, số điện thoại hoặc tên khách hàng.
                                        @else
                                            <b>Nhập serial để bắt đầu tra cứu</b>
                                            Dữ liệu hiển thị là serial đã bán / đã xuất.
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        @if(method_exists($serials, 'links'))
            <div style="margin-top:14px">
                {{ $serials->links() }}
            </div>
        @endif
    </div>
</div>

@if($canManageWarranty)
    <div id="swModalBackdrop" class="sw-modal-backdrop" onclick="closeAllSerialModals()"></div>

    <div id="swAddSerialModal" class="sw-modal" role="dialog" aria-modal="true" aria-label="Thêm serial bảo hành">
        <div class="sw-modal-head">
            <div>
                <h3 class="sw-modal-title">Thêm serial bảo hành</h3>
                <div class="sw-modal-sub">Dùng khi đã bán/xuất hàng nhưng trước đó quên nhập serial trong kho.</div>
            </div>
            <button type="button" class="sw-modal-close" onclick="closeAllSerialModals()">×</button>
        </div>

        <form method="POST" action="{{ route('serial-warranty.manual-add') }}">
            @csrf

            <div class="sw-form-grid">
                <div class="wide">
                    <label class="sw-label">Serial cần thêm</label>
                    <textarea class="sw-textarea" name="serials" placeholder="Mỗi dòng 1 serial. Có thể dán nhiều serial cùng lúc." required>{{ old('serials') }}</textarea>
                </div>

                <div class="wide">
                    <label class="sw-label">Sản phẩm</label>
                    <select class="sw-select" name="product_id" required>
                        <option value="">-- Chọn sản phẩm --</option>
                        @foreach($products as $product)
                            <option value="{{ $product->id }}">{{ $product->sku ? $product->sku.' - ' : '' }}{{ $product->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="sw-label">Khách hàng</label>
                    <select class="sw-select" name="customer_id">
                        <option value="">-- Chọn khách hàng nếu có --</option>
                        @foreach($customers as $customer)
                            <option value="{{ $customer->id }}">{{ $customer->name }}{{ $customer->phone ? ' - '.$customer->phone : '' }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="sw-label">Đơn hàng</label>
                    <select class="sw-select" name="order_id">
                        <option value="">-- Chọn đơn nếu có --</option>
                        @foreach($orders as $order)
                            <option value="{{ $order->id }}">{{ $order->order_code ?: ('Đơn #'.$order->id) }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="sw-label">Ngày bán/xuất</label>
                    <input class="sw-input" type="date" name="sold_at" value="{{ now()->format('Y-m-d') }}">
                </div>

                <div>
                    <label class="sw-label">Bắt đầu BH</label>
                    <input class="sw-input" type="date" name="warranty_start_at" value="{{ now()->format('Y-m-d') }}">
                </div>

                <div>
                    <label class="sw-label">Số tháng BH</label>
                    <input class="sw-input" type="number" name="warranty_months" min="1" max="240" value="60">
                </div>

                <div>
                    <label class="sw-label">Kết thúc BH</label>
                    <input class="sw-input" type="date" name="warranty_end_at">
                </div>

                <div class="wide">
                    <label class="sw-label">Ghi chú</label>
                    <input class="sw-input" type="text" name="note" value="{{ old('note', 'Quên nhập kho') }}" placeholder="Ghi chú bảo hành">
                </div>
            </div>

            <div class="sw-modal-actions">
                <button type="button" class="sw-btn light" onclick="closeAllSerialModals()">Đóng</button>
                <button class="sw-btn green" type="submit">Thêm serial bảo hành</button>
            </div>
        </form>
    </div>

    <div id="swEditWarrantyModal" class="sw-modal" role="dialog" aria-modal="true" aria-label="Sửa serial bảo hành">
        <div class="sw-modal-head">
            <div>
                <h3 class="sw-modal-title">Sửa serial bảo hành</h3>
                <div id="swEditSerialText" class="sw-modal-sub">Serial</div>
            </div>
            <button type="button" class="sw-modal-close" onclick="closeAllSerialModals()">×</button>
        </div>

        <form id="swEditWarrantyForm" method="POST" data-action-template="{{ url('/serial-warranty/serial/__ID__/warranty') }}">
            @csrf

            <div class="sw-form-grid">
                <div class="wide">
                    <label class="sw-label">Sản phẩm</label>
                    <select class="sw-select" name="product_id" id="edit_product_id">
                        <option value="">-- Chọn sản phẩm --</option>
                        @foreach($products as $product)
                            <option value="{{ $product->id }}">{{ $product->sku ? $product->sku.' - ' : '' }}{{ $product->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="sw-label">Khách hàng</label>
                    <select class="sw-select" name="customer_id" id="edit_customer_id">
                        <option value="">-- Chọn khách hàng nếu có --</option>
                        @foreach($customers as $customer)
                            <option value="{{ $customer->id }}">{{ $customer->name }}{{ $customer->phone ? ' - '.$customer->phone : '' }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="sw-label">Đơn hàng</label>
                    <select class="sw-select" name="order_id" id="edit_order_id">
                        <option value="">-- Chọn đơn nếu có --</option>
                        @foreach($orders as $order)
                            <option value="{{ $order->id }}">{{ $order->order_code ?: ('Đơn #'.$order->id) }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="sw-label">Ngày bán/xuất</label>
                    <input class="sw-input" type="date" name="sold_at" id="edit_sold_at">
                </div>

                <div>
                    <label class="sw-label">Bắt đầu BH</label>
                    <input class="sw-input" type="date" name="warranty_start_at" id="edit_start_at">
                </div>

                <div>
                    <label class="sw-label">Số tháng BH</label>
                    <input class="sw-input" type="number" name="warranty_months" min="1" max="240" id="edit_months">
                </div>

                <div>
                    <label class="sw-label">Kết thúc BH</label>
                    <input class="sw-input" type="date" name="warranty_end_at" id="edit_end_at">
                </div>

                <div class="wide">
                    <label class="sw-label">Ghi chú</label>
                    <input class="sw-input" type="text" name="note" id="edit_note" placeholder="Ghi chú bảo hành">
                </div>
            </div>

            <div class="sw-modal-actions">
                <button type="button" class="sw-btn light" onclick="closeAllSerialModals()">Đóng</button>
                <button class="sw-btn green" type="submit">Lưu thay đổi</button>
            </div>
        </form>
    </div>
@endif

<script>
(function(){
    const backdrop = document.getElementById('swModalBackdrop');
    const addModal = document.getElementById('swAddSerialModal');
    const editModal = document.getElementById('swEditWarrantyModal');

    function showBackdrop(){
        if(backdrop){
            backdrop.classList.add('show');
        }
        document.body.classList.add('sw-modal-open');
    }

    window.closeAllSerialModals = function(){
        if(backdrop){
            backdrop.classList.remove('show');
        }

        if(addModal){
            addModal.classList.remove('show');
        }

        if(editModal){
            editModal.classList.remove('show');
        }

        document.body.classList.remove('sw-modal-open');
    };

    window.openAddSerialModal = function(){
        if(addModal){
            addModal.classList.add('show');
            showBackdrop();
        }
    };

    function setValue(id, value){
        const el = document.getElementById(id);
        if(el){
            el.value = value || '';
        }
    }

    window.openEditWarrantyModal = function(button){
        if(!button || !editModal){
            return;
        }

        const id = button.dataset.id || '';
        const form = document.getElementById('swEditWarrantyForm');
        const title = document.getElementById('swEditSerialText');

        if(form){
            const template = form.dataset.actionTemplate || '/serial-warranty/serial/__ID__/warranty';
            form.action = template.replace('__ID__', id);
        }

        if(title){
            title.textContent = 'Serial: ' + (button.dataset.serial || ('#' + id));
        }

        setValue('edit_product_id', button.dataset.productId);
        setValue('edit_customer_id', button.dataset.customerId);
        setValue('edit_order_id', button.dataset.orderId);
        setValue('edit_sold_at', button.dataset.soldAt);
        setValue('edit_start_at', button.dataset.start);
        setValue('edit_months', button.dataset.months || '60');
        setValue('edit_end_at', button.dataset.end);
        setValue('edit_note', button.dataset.note);

        editModal.classList.add('show');
        showBackdrop();
    };

    document.addEventListener('keydown', function(event){
        if(event.key === 'Escape'){
            window.closeAllSerialModals();
        }
    });
})();
</script>


<!-- EGO_SEARCHABLE_SERIAL_SELECTS_START -->
<style>
    .sw-native-hidden-for-search{
        position:absolute !important;
        left:-99999px !important;
        width:1px !important;
        height:1px !important;
        opacity:0 !important;
        pointer-events:none !important;
    }

    .sw-search-select{
        position:relative;
        width:100%;
    }

    .sw-search-select::after{
        content:"⌄";
        position:absolute;
        right:14px;
        top:50%;
        transform:translateY(-50%);
        color:#64748b;
        font-weight:950;
        pointer-events:none;
        font-size:15px;
    }

    .sw-search-input{
        width:100%;
        height:42px;
        border:1px solid var(--sw-line, #dce8f1);
        background:#fff;
        color:#0f172a;
        outline:none;
        font-weight:800;
        border-radius:13px;
        padding:0 38px 0 12px;
        font-size:13px;
    }

    .sw-search-input:focus{
        border-color:#14b8a6;
        box-shadow:0 0 0 4px rgba(20,184,166,.12);
    }

    .sw-search-select.invalid .sw-search-input{
        border-color:#ef4444;
        box-shadow:0 0 0 4px rgba(239,68,68,.12);
    }

    .sw-search-menu{
        position:absolute;
        left:0;
        right:0;
        top:calc(100% + 6px);
        z-index:10050;
        display:none;
        max-height:280px;
        overflow:auto;
        background:#fff;
        border:1px solid #dbeafe;
        border-radius:14px;
        box-shadow:0 18px 48px rgba(15,23,42,.20);
        padding:6px;
    }

    .sw-search-select.open .sw-search-menu{
        display:block;
    }

    .sw-search-option{
        display:block;
        width:100%;
        border:0;
        background:#fff;
        text-align:left;
        padding:10px 11px;
        border-radius:10px;
        cursor:pointer;
        color:#0f172a;
        font-size:13px;
        font-weight:800;
        line-height:1.35;
    }

    .sw-search-option:hover,
    .sw-search-option.active{
        background:#ecfeff;
        color:#0f766e;
    }

    .sw-search-option.empty{
        color:#64748b;
        font-weight:850;
    }

    .sw-search-no-result{
        padding:12px;
        color:#64748b;
        font-size:13px;
        font-weight:850;
        text-align:center;
    }
</style>

<script>
(function(){
    'use strict';

    function normalizeText(value){
        return (value || '')
            .toString()
            .toLowerCase()
            .normalize('NFD')
            .replace(/[\u0300-\u036f]/g, '');
    }

    function closeAllSearchMenus(except){
        document.querySelectorAll('.sw-search-select.open').forEach(function(box){
            if(box !== except){
                box.classList.remove('open');
            }
        });
    }

    function initOneSearchableSelect(select){
        if(!select || select.dataset.swSearchableReady === '1'){
            return;
        }

        select.dataset.swSearchableReady = '1';

        if(select.hasAttribute('required')){
            select.dataset.swSearchRequired = '1';
            select.removeAttribute('required');
        }

        select.classList.add('sw-native-hidden-for-search');
        select.tabIndex = -1;

        var placeholder = '';
        if(select.options.length){
            placeholder = select.options[0].textContent.trim();
        }
        placeholder = placeholder || 'Gõ để tìm kiếm...';

        var box = document.createElement('div');
        box.className = 'sw-search-select';

        var input = document.createElement('input');
        input.type = 'text';
        input.className = 'sw-search-input';
        input.autocomplete = 'off';
        input.placeholder = placeholder;

        var menu = document.createElement('div');
        menu.className = 'sw-search-menu';

        select.parentNode.insertBefore(box, select.nextSibling);
        box.appendChild(input);
        box.appendChild(menu);

        var activeIndex = -1;

        function getOptions(){
            return Array.prototype.slice.call(select.options || []);
        }

        function syncInput(){
            var opt = select.options[select.selectedIndex];
            if(opt && opt.value !== ''){
                input.value = opt.textContent.trim();
            }else{
                input.value = '';
            }

            if(select.value){
                box.classList.remove('invalid');
            }
        }

        function setActive(index){
            var items = Array.prototype.slice.call(menu.querySelectorAll('.sw-search-option'));
            items.forEach(function(item){
                item.classList.remove('active');
            });

            if(!items.length){
                activeIndex = -1;
                return;
            }

            if(index < 0){
                index = items.length - 1;
            }

            if(index >= items.length){
                index = 0;
            }

            activeIndex = index;
            items[activeIndex].classList.add('active');
            items[activeIndex].scrollIntoView({block:'nearest'});
        }

        function chooseOption(opt){
            if(!opt){
                return;
            }

            select.value = opt.value;
            syncInput();

            try{
                select.dispatchEvent(new Event('change', {bubbles:true}));
            }catch(e){
                var evt = document.createEvent('HTMLEvents');
                evt.initEvent('change', true, false);
                select.dispatchEvent(evt);
            }

            box.classList.remove('open');
        }

        function renderMenu(keyword){
            var q = normalizeText(keyword);
            var options = getOptions();
            var shown = 0;
            var maxShown = 120;

            menu.innerHTML = '';
            activeIndex = -1;

            options.forEach(function(opt){
                if(shown >= maxShown){
                    return;
                }

                var label = opt.textContent.trim();
                var value = opt.value || '';
                var haystack = normalizeText(label + ' ' + value);

                var isPlaceholder = value === '';
                var match = !q || haystack.indexOf(q) !== -1;

                if(isPlaceholder || match){
                    var item = document.createElement('button');
                    item.type = 'button';
                    item.className = 'sw-search-option' + (isPlaceholder ? ' empty' : '');
                    item.textContent = label;

                    item.addEventListener('mousedown', function(e){
                        e.preventDefault();
                        chooseOption(opt);
                    });

                    menu.appendChild(item);
                    shown++;
                }
            });

            if(!shown){
                var empty = document.createElement('div');
                empty.className = 'sw-search-no-result';
                empty.textContent = 'Không tìm thấy dữ liệu phù hợp';
                menu.appendChild(empty);
            }

            box.classList.add('open');
            closeAllSearchMenus(box);
            setActive(0);
        }

        input.addEventListener('focus', function(){
            renderMenu('');
            setTimeout(function(){
                input.select();
            }, 0);
        });

        input.addEventListener('click', function(){
            renderMenu('');
            setTimeout(function(){
                input.select();
            }, 0);
        });

        input.addEventListener('input', function(){
            renderMenu(input.value);
        });

        input.addEventListener('keydown', function(e){
            var items = Array.prototype.slice.call(menu.querySelectorAll('.sw-search-option'));

            if(e.key === 'ArrowDown'){
                e.preventDefault();
                if(!box.classList.contains('open')){
                    renderMenu(input.value);
                }else{
                    setActive(activeIndex + 1);
                }
            }

            if(e.key === 'ArrowUp'){
                e.preventDefault();
                setActive(activeIndex - 1);
            }

            if(e.key === 'Enter'){
                if(box.classList.contains('open') && items[activeIndex]){
                    e.preventDefault();
                    items[activeIndex].dispatchEvent(new MouseEvent('mousedown', {bubbles:true}));
                }
            }

            if(e.key === 'Escape'){
                box.classList.remove('open');
                syncInput();
            }
        });

        input.addEventListener('blur', function(){
            setTimeout(function(){
                if(!box.classList.contains('open')){
                    syncInput();
                }
            }, 160);
        });

        select.addEventListener('change', syncInput);
        select._swSearchSync = syncInput;

        syncInput();
    }

    function initSearchableSelects(){
        document.querySelectorAll('#swAddSerialModal select.sw-select, #swEditWarrantyModal select.sw-select').forEach(initOneSearchableSelect);
    }

    function syncSearchableSelects(){
        initSearchableSelects();

        document.querySelectorAll('#swAddSerialModal select.sw-select, #swEditWarrantyModal select.sw-select').forEach(function(select){
            if(typeof select._swSearchSync === 'function'){
                select._swSearchSync();
            }
        });
    }

    window.swSyncSearchableSerialSelects = syncSearchableSelects;

    document.addEventListener('click', function(e){
        if(!e.target.closest('.sw-search-select')){
            closeAllSearchMenus(null);
        }
    });

    document.addEventListener('submit', function(e){
        var form = e.target;
        if(!form){
            return;
        }

        var invalid = null;

        form.querySelectorAll('select.sw-select[data-sw-search-required="1"]').forEach(function(select){
            if(!invalid && !select.value){
                invalid = select;
            }
        });

        if(invalid){
            e.preventDefault();

            var box = invalid.nextElementSibling;
            if(box && box.classList.contains('sw-search-select')){
                box.classList.add('invalid');
                var input = box.querySelector('.sw-search-input');
                if(input){
                    input.focus();
                }
            }

            alert('Vui lòng chọn sản phẩm trước khi lưu.');
        }
    }, true);

    document.addEventListener('DOMContentLoaded', syncSearchableSelects);
    syncSearchableSelects();

    var oldOpenAdd = window.openAddSerialModal;
    if(typeof oldOpenAdd === 'function' && !oldOpenAdd._swSearchWrapped){
        window.openAddSerialModal = function(){
            var result = oldOpenAdd.apply(this, arguments);
            setTimeout(syncSearchableSelects, 0);
            return result;
        };
        window.openAddSerialModal._swSearchWrapped = true;
    }

    var oldOpenEdit = window.openEditWarrantyModal;
    if(typeof oldOpenEdit === 'function' && !oldOpenEdit._swSearchWrapped){
        window.openEditWarrantyModal = function(){
            var result = oldOpenEdit.apply(this, arguments);
            setTimeout(syncSearchableSelects, 0);
            return result;
        };
        window.openEditWarrantyModal._swSearchWrapped = true;
    }
})();
</script>
<!-- EGO_SEARCHABLE_SERIAL_SELECTS_END -->


@endsection
