@extends('layouts.app')
@section('title', 'Danh sách khách hàng')

@section('content')
@php
    /** @var \App\Models\User $authUser */
    $authUser = auth()->user();

    $canFilterOwner = $authUser->canAny(['customer.view_all', 'customer.view_sales_all', '*']);

    $customerPageItems = method_exists($customers, 'getCollection') ? $customers->getCollection() : collect($customers);
    $totalCustomers = method_exists($customers, 'total') ? $customers->total() : $customerPageItems->count();

    $leadCount = $customerPageItems->where('customer_status', 'lead')->count();
    $memberCount = $customerPageItems->where('customer_status', 'member')->count();
    $retailCount = $customerPageItems->where('customer_status', 'retail')->count();
    $potentialCount = $customerPageItems->where('is_potential', true)->count();

    $activeFilterCount = collect([
        request('search'),
        request('region_id'),
        request('customer_type_id'),
        request('customer_status'),
        request('owner_id'),
        request('has_order'),
    ])->filter(fn($v) => $v !== null && $v !== '')->count();

    $statusBadge = function ($status) {
        return match ((string)$status) {
            'member' => ['Member', 'vip-member'],
            'retail' => ['Khách lẻ', 'vip-retail'],
            default => ['Lead', 'vip-lead'],
        };
    };

    $commissionText = function ($status) {
        return match ((string)$status) {
            'member' => '1%',
            'retail' => '2%',
            default => '0,5%',
        };
    };

    $initials = function ($name) {
        $name = trim((string)$name);
        if ($name === '') return 'KH';

        return collect(preg_split('/\s+/u', $name))
            ->filter()
            ->take(2)
            ->map(fn($part) => mb_strtoupper(mb_substr($part, 0, 1)))
            ->implode('') ?: 'KH';
    };
@endphp

<style>
    #vipCustomers{
        --aqua:#06b6d4;
        --aqua2:#0891b2;
        --aqua3:#67e8f9;
        --ink:#0f172a;
        --muted:#64748b;
        --line:#e6f0f5;
        --soft:#f6fdff;
        --card:#ffffff;
        --shadow:0 18px 46px rgba(15,23,42,.075);
        --shadow2:0 10px 26px rgba(15,23,42,.065);
        min-height:calc(100vh - 70px);
        padding:18px 22px 28px;
        background:
            radial-gradient(900px 260px at 10% -5%, rgba(6,182,212,.16), transparent 62%),
            radial-gradient(820px 260px at 90% 0%, rgba(14,165,233,.10), transparent 58%),
            linear-gradient(180deg,#f7fcff 0%,#fff 72%);
        color:var(--ink);
        position:relative;
        overflow:hidden;
    }

    #vipCustomers *{
        box-sizing:border-box;
    }

    #vipCustomers::before{
        content:"";
        position:absolute;
        inset:0;
        pointer-events:none;
        background:
            radial-gradient(circle at var(--mx, 20%) var(--my, 15%), rgba(6,182,212,.10), transparent 22%),
            radial-gradient(circle at 80% 20%, rgba(103,232,249,.08), transparent 28%);
        transition:background .15s ease;
    }

    #vipCustomers > *{
        position:relative;
        z-index:1;
    }

    #vipCustomers .vip-hero{
        border:1px solid rgba(6,182,212,.20);
        border-radius:26px;
        padding:22px;
        background:
            linear-gradient(135deg, rgba(255,255,255,.92), rgba(236,253,255,.86)),
            radial-gradient(circle at 0% 0%, rgba(6,182,212,.18), transparent 45%);
        box-shadow:var(--shadow);
        overflow:hidden;
        margin-bottom:16px;
    }

    #vipCustomers .vip-hero-top{
        display:flex;
        justify-content:space-between;
        align-items:flex-start;
        gap:16px;
        flex-wrap:wrap;
    }

    #vipCustomers .vip-title-wrap{
        display:flex;
        gap:14px;
        align-items:center;
        min-width:0;
    }

    #vipCustomers .vip-title-icon{
        width:58px;
        height:58px;
        border-radius:20px;
        display:grid;
        place-items:center;
        color:#0e7490;
        font-size:25px;
        background:linear-gradient(135deg, rgba(6,182,212,.16), rgba(103,232,249,.18));
        border:1px solid rgba(6,182,212,.24);
        box-shadow:0 12px 28px rgba(6,182,212,.12);
        flex:0 0 auto;
    }

    #vipCustomers .vip-title{
        margin:0;
        font-size:30px;
        line-height:1.08;
        letter-spacing:-.6px;
        font-weight:950;
        color:var(--ink);
    }

    #vipCustomers .vip-subtitle{
        margin-top:6px;
        color:var(--muted);
        font-size:13px;
        font-weight:750;
    }

    #vipCustomers .vip-actions{
        display:flex;
        align-items:center;
        gap:10px;
        flex-wrap:wrap;
    }

    #vipCustomers .vip-btn,
    #vipCustomers .vip-btn-soft,
    #vipCustomers .vip-btn-ghost{
        min-height:42px;
        border-radius:15px;
        padding:10px 14px;
        font-weight:900;
        font-size:13px;
        display:inline-flex;
        align-items:center;
        justify-content:center;
        gap:7px;
        text-decoration:none;
        transition:transform .18s ease, box-shadow .18s ease, border-color .18s ease, background .18s ease;
    }

    #vipCustomers .vip-btn{
        border:0;
        color:#fff;
        background:linear-gradient(135deg,var(--aqua),var(--aqua2));
        box-shadow:0 14px 30px rgba(6,182,212,.18);
    }

    #vipCustomers .vip-btn:hover{
        color:#fff;
        transform:translateY(-2px);
        box-shadow:0 18px 38px rgba(6,182,212,.23);
    }

    #vipCustomers .vip-btn-soft{
        border:1px solid rgba(6,182,212,.22);
        color:#0e7490;
        background:rgba(255,255,255,.84);
    }

    #vipCustomers .vip-btn-soft:hover,
    #vipCustomers .vip-btn-ghost:hover{
        color:#0e7490;
        background:#ecfeff;
        border-color:rgba(6,182,212,.34);
        transform:translateY(-1px);
    }

    #vipCustomers .vip-btn-ghost{
        border:1px solid var(--line);
        color:var(--ink);
        background:#fff;
    }

    #vipCustomers .vip-kpi-grid{
        display:grid;
        grid-template-columns:repeat(4,minmax(0,1fr));
        gap:12px;
        margin-top:18px;
    }

    #vipCustomers .vip-kpi{
        border:1px solid rgba(6,182,212,.15);
        border-radius:20px;
        padding:14px;
        background:rgba(255,255,255,.76);
        box-shadow:0 10px 24px rgba(15,23,42,.045);
        min-height:92px;
        display:flex;
        justify-content:space-between;
        gap:12px;
        overflow:hidden;
        position:relative;
    }

    #vipCustomers .vip-kpi::after{
        content:"";
        position:absolute;
        right:-40px;
        top:-48px;
        width:118px;
        height:118px;
        border-radius:999px;
        background:var(--glow, rgba(6,182,212,.12));
    }

    #vipCustomers .vip-kpi-label{
        color:var(--muted);
        font-size:12px;
        font-weight:850;
    }

    #vipCustomers .vip-kpi-value{
        margin-top:7px;
        font-size:24px;
        font-weight:950;
        line-height:1;
        color:var(--ink);
    }

    #vipCustomers .vip-kpi-note{
        margin-top:7px;
        color:var(--muted);
        font-size:11.5px;
        font-weight:750;
    }

    #vipCustomers .vip-kpi-icon{
        position:relative;
        z-index:1;
        width:42px;
        height:42px;
        border-radius:15px;
        display:grid;
        place-items:center;
        color:#0e7490;
        background:rgba(255,255,255,.82);
        border:1px solid rgba(6,182,212,.18);
        flex:0 0 auto;
    }

    #vipCustomers .vip-panel{
        border:1px solid var(--line);
        border-radius:24px;
        background:rgba(255,255,255,.92);
        box-shadow:var(--shadow);
        overflow:hidden;
    }

    #vipCustomers .vip-panel-head{
        padding:16px 18px 12px;
        border-bottom:1px solid var(--line);
        display:flex;
        align-items:flex-start;
        justify-content:space-between;
        gap:12px;
        flex-wrap:wrap;
    }

    #vipCustomers .vip-panel-title{
        margin:0;
        font-size:17px;
        font-weight:950;
        color:var(--ink);
        display:flex;
        align-items:center;
        gap:9px;
    }

    #vipCustomers .vip-panel-sub{
        margin-top:4px;
        color:var(--muted);
        font-size:12px;
        font-weight:750;
    }

    #vipCustomers .vip-filter-body{
        padding:16px 18px 18px;
    }

    #vipCustomers .vip-filter-grid{
        display:grid;
        grid-template-columns:2fr 1fr 1fr 1fr auto;
        gap:10px;
        align-items:end;
    }

    #vipCustomers .vip-filter-label{
        color:#334155;
        font-size:12px;
        font-weight:850;
        margin-bottom:6px;
    }

    #vipCustomers .vip-control{
        width:100%;
        min-height:44px;
        border:1px solid #dbe7ef;
        border-radius:15px;
        background:#fff;
        padding:10px 12px;
        color:var(--ink);
        font-size:13.5px;
        font-weight:750;
        outline:none;
        transition:border-color .18s ease, box-shadow .18s ease, transform .18s ease;
    }

    #vipCustomers .vip-control:focus{
        border-color:rgba(6,182,212,.55);
        box-shadow:0 0 0 4px rgba(6,182,212,.10);
        transform:translateY(-1px);
    }

    #vipCustomers .vip-advanced{
        margin-top:10px;
    }

    #vipCustomers .vip-advanced summary{
        list-style:none;
        cursor:pointer;
        display:inline-flex;
        align-items:center;
        gap:7px;
        color:#0891b2;
        font-weight:900;
        font-size:13px;
        padding:7px 0;
    }

    #vipCustomers .vip-advanced summary::-webkit-details-marker{
        display:none;
    }

    #vipCustomers .vip-advanced-grid{
        margin-top:10px;
        padding-top:12px;
        border-top:1px dashed #d7e5ee;
        display:grid;
        grid-template-columns:repeat(4,minmax(0,1fr));
        gap:10px;
        align-items:end;
    }

    #vipCustomers .vip-filter-note{
        margin-top:12px;
        color:var(--muted);
        font-size:12px;
        font-weight:750;
    }

    #vipCustomers .vip-table-shell{
        margin-top:16px;
    }

    #vipCustomers .vip-table-top{
        display:flex;
        justify-content:space-between;
        align-items:center;
        gap:12px;
        margin-bottom:10px;
        flex-wrap:wrap;
    }

    #vipCustomers .vip-table-title{
        font-size:16px;
        font-weight:950;
        color:var(--ink);
        display:flex;
        align-items:center;
        gap:8px;
    }

    #vipCustomers .vip-table-count{
        color:var(--muted);
        font-size:12px;
        font-weight:800;
    }

    #vipCustomers .vip-table-wrap{
        border:1px solid var(--line);
        border-radius:22px;
        box-shadow:var(--shadow);
        background:#fff;
        overflow:hidden;
    }

    #vipCustomers .vip-table-responsive{
        overflow:auto;
        max-height:none;
    }

    #vipCustomers .vip-table{
        width:100%;
        margin:0;
        border-collapse:separate;
        border-spacing:0;
        font-size:13px;
        white-space:nowrap;
    }

    #vipCustomers .vip-table thead th{
        position:sticky;
        top:0;
        z-index:6;
        background:#e9fbff;
        color:#0f3f53;
        font-size:11px;
        text-transform:uppercase;
        letter-spacing:.035em;
        font-weight:950;
        padding:12px 11px;
        border-bottom:1px solid #d9eef5;
    }

    #vipCustomers .vip-table tbody td{
        padding:12px 11px;
        border-bottom:1px solid #edf3f7;
        vertical-align:middle;
        font-weight:700;
        color:#1e293b;
    }

    #vipCustomers .vip-table tbody tr{
        transition:background .18s ease, transform .18s ease;
    }

    #vipCustomers .vip-table tbody tr:hover{
        background:#f1fdff;
    }

    #vipCustomers .vip-table tbody tr:last-child td{
        border-bottom:0;
    }

    #vipCustomers .vip-check{
        width:16px;
        height:16px;
        accent-color:#06b6d4;
    }

    #vipCustomers .vip-customer{
        display:flex;
        align-items:center;
        gap:10px;
        min-width:260px;
        text-align:left;
    }

    #vipCustomers .vip-avatar{
        width:42px;
        height:42px;
        border-radius:15px;
        display:grid;
        place-items:center;
        flex:0 0 auto;
        color:#fff;
        font-size:13px;
        font-weight:950;
        background:linear-gradient(135deg,#06b6d4,#38bdf8);
        box-shadow:0 10px 22px rgba(6,182,212,.18);
    }

    #vipCustomers .vip-name{
        font-weight:950;
        color:var(--ink);
        line-height:1.25;
        white-space:normal;
    }

    #vipCustomers .vip-subline{
        margin-top:3px;
        color:var(--muted);
        font-size:12px;
        font-weight:700;
        white-space:normal;
    }

    #vipCustomers .vip-phone{
        font-weight:950;
        color:#0f172a;
    }

    #vipCustomers .vip-note{
        max-width:280px;
        min-width:220px;
        white-space:normal;
        color:#475569;
        font-size:12.5px;
        line-height:1.45;
    }

    #vipCustomers .vip-badge{
        display:inline-flex;
        align-items:center;
        gap:5px;
        padding:6px 9px;
        border-radius:999px;
        font-size:11px;
        font-weight:950;
        line-height:1;
        border:1px solid transparent;
    }

    #vipCustomers .vip-lead{
        background:#fff7ed;
        color:#b45309;
        border-color:#fed7aa;
    }

    #vipCustomers .vip-member{
        background:#ecfdf5;
        color:#047857;
        border-color:#bbf7d0;
    }

    #vipCustomers .vip-retail{
        background:#ecfeff;
        color:#0891b2;
        border-color:#a5f3fc;
    }

    #vipCustomers .vip-type{
        background:#f8fafc;
        color:#334155;
        border-color:#e2e8f0;
    }

    #vipCustomers .vip-potential{
        color:#b45309;
        font-weight:950;
    }

    #vipCustomers .vip-actions-cell{
        display:flex;
        align-items:center;
        justify-content:center;
        gap:7px;
    }

    #vipCustomers .vip-icon-btn{
        width:35px;
        height:35px;
        border-radius:13px;
        display:inline-flex;
        align-items:center;
        justify-content:center;
        padding:0;
        border:1px solid #dbe7ef;
        background:#fff;
        color:#0f172a;
        transition:transform .16s ease, background .16s ease, border-color .16s ease;
    }

    #vipCustomers .vip-icon-btn:hover{
        transform:translateY(-1px);
        background:#ecfeff;
        border-color:#a5f3fc;
        color:#0891b2;
    }

    #vipCustomers .vip-icon-btn.view{
        color:#0891b2;
    }

    #vipCustomers .vip-icon-btn.edit{
        color:#d97706;
    }

    #vipCustomers .vip-icon-btn.delete{
        color:#e11d48;
    }

    #vipCustomers .vip-mobile-list{
        display:none;
        margin-top:14px;
        gap:12px;
    }

    #vipCustomers .vip-mobile-card{
        border:1px solid var(--line);
        border-radius:22px;
        background:rgba(255,255,255,.94);
        box-shadow:var(--shadow2);
        padding:14px;
        overflow:hidden;
    }

    #vipCustomers .vip-mobile-top{
        display:flex;
        gap:11px;
        align-items:flex-start;
    }

    #vipCustomers .vip-mobile-info{
        min-width:0;
        flex:1;
    }

    #vipCustomers .vip-mobile-name{
        font-size:15px;
        font-weight:950;
        color:var(--ink);
        line-height:1.3;
    }

    #vipCustomers .vip-mobile-phone{
        margin-top:3px;
        color:#0f172a;
        font-size:13px;
        font-weight:850;
    }

    #vipCustomers .vip-mobile-meta{
        display:grid;
        grid-template-columns:1fr 1fr;
        gap:8px;
        margin-top:12px;
    }

    #vipCustomers .vip-mobile-meta > div{
        border:1px solid #edf3f7;
        background:#f8fafc;
        border-radius:14px;
        padding:9px;
        min-width:0;
    }

    #vipCustomers .vip-mobile-label{
        color:var(--muted);
        font-size:11px;
        font-weight:850;
        margin-bottom:3px;
    }

    #vipCustomers .vip-mobile-value{
        color:var(--ink);
        font-size:13px;
        font-weight:900;
        word-break:break-word;
    }

    #vipCustomers .vip-mobile-actions{
        display:grid;
        grid-template-columns:repeat(3,minmax(0,1fr));
        gap:8px;
        margin-top:12px;
    }

    #vipCustomers .vip-mobile-actions a,
    #vipCustomers .vip-mobile-actions button{
        width:100%;
        min-height:38px;
        border-radius:14px;
        border:1px solid #dbe7ef;
        background:#fff;
        color:#0f172a;
        font-size:12px;
        font-weight:900;
        display:flex;
        align-items:center;
        justify-content:center;
        gap:6px;
        text-decoration:none;
    }

    #vipCustomers .vip-mobile-actions a:hover,
    #vipCustomers .vip-mobile-actions button:hover{
        background:#ecfeff;
        color:#0891b2;
    }

    #vipCustomers .vip-empty{
        padding:38px 14px;
        text-align:center;
        color:var(--muted);
        font-weight:850;
    }

    #vipCustomers .vip-pagination{
        margin-top:16px;
    }

    #vipCustomers .vip-reveal{
        opacity:0;
        transform:translateY(14px);
        transition:opacity .45s ease, transform .45s ease;
    }

    #vipCustomers .vip-reveal.show{
        opacity:1;
        transform:none;
    }

    #vipCustomers .vip-ripple{
        position:relative;
        overflow:hidden;
    }

    #vipCustomers .vip-ripple-dot{
        position:absolute;
        border-radius:999px;
        background:rgba(255,255,255,.55);
        pointer-events:none;
        transform:scale(0);
        animation:vipRipple .65s linear;
    }

    @keyframes vipRipple{
        to{
            transform:scale(4);
            opacity:0;
        }
    }

    @media(max-width:1400px){
        #vipCustomers .vip-kpi-grid{
            grid-template-columns:repeat(2,minmax(0,1fr));
        }

        #vipCustomers .vip-filter-grid{
            grid-template-columns:1.5fr 1fr 1fr auto;
        }
    }

    @media(max-width:992px){
        #vipCustomers{
            padding:14px;
        }

        #vipCustomers .vip-title{
            font-size:25px;
        }

        #vipCustomers .vip-filter-grid{
            grid-template-columns:1fr 1fr;
        }

        #vipCustomers .vip-filter-grid .vip-filter-action{
            grid-column:span 2;
        }

        #vipCustomers .vip-advanced-grid{
            grid-template-columns:1fr 1fr;
        }
    }

    @media(max-width:768px){
        #vipCustomers{
            padding:12px;
            background:
                radial-gradient(560px 220px at 0% -4%, rgba(6,182,212,.14), transparent 62%),
                linear-gradient(180deg,#f7fcff,#fff);
        }

        #vipCustomers .vip-hero{
            padding:16px;
            border-radius:22px;
        }

        #vipCustomers .vip-title-wrap{
            align-items:flex-start;
        }

        #vipCustomers .vip-title-icon{
            width:48px;
            height:48px;
            border-radius:17px;
            font-size:21px;
        }

        #vipCustomers .vip-title{
            font-size:22px;
        }

        #vipCustomers .vip-subtitle{
            font-size:12px;
        }

        #vipCustomers .vip-actions{
            width:100%;
        }

        #vipCustomers .vip-actions .vip-btn,
        #vipCustomers .vip-actions .vip-btn-soft{
            flex:1;
        }

        #vipCustomers .vip-kpi-grid{
            grid-template-columns:1fr 1fr;
            gap:9px;
            margin-top:14px;
        }

        #vipCustomers .vip-kpi{
            min-height:86px;
            padding:12px;
            border-radius:18px;
        }

        #vipCustomers .vip-kpi-value{
            font-size:20px;
        }

        #vipCustomers .vip-kpi-icon{
            width:36px;
            height:36px;
            border-radius:13px;
        }

        #vipCustomers .vip-panel{
            border-radius:21px;
        }

        #vipCustomers .vip-panel-head{
            padding:14px;
        }

        #vipCustomers .vip-filter-body{
            padding:14px;
        }

        #vipCustomers .vip-filter-grid{
            grid-template-columns:1fr;
        }

        #vipCustomers .vip-filter-grid .vip-filter-action{
            grid-column:auto;
        }

        #vipCustomers .vip-advanced-grid{
            grid-template-columns:1fr;
        }

        #vipCustomers .vip-table-wrap{
            display:none;
        }

        #vipCustomers .vip-mobile-list{
            display:grid;
        }

        #vipCustomers .vip-table-top{
            margin-top:15px;
        }
    }

    @media(max-width:420px){
        #vipCustomers{
            padding:10px;
        }

        #vipCustomers .vip-kpi-grid{
            grid-template-columns:1fr;
        }

        #vipCustomers .vip-mobile-meta{
            grid-template-columns:1fr;
        }

        #vipCustomers .vip-mobile-actions{
            grid-template-columns:1fr;
        }
    }
</style>

<div id="vipCustomers">
    <div class="vip-hero vip-reveal">
        <div class="vip-hero-top">
            <div class="vip-title-wrap">
                <div class="vip-title-icon">
                    <i class="bi bi-people"></i>
                </div>

                <div>
                    <h1 class="vip-title">Khách hàng</h1>
                    <div class="vip-subtitle">
                        Quản lý hồ sơ, phân loại, chăm sóc và lịch sử mua hàng.
                        @if($activeFilterCount > 0)
                            <span class="ms-1 text-info fw-bold">• {{ $activeFilterCount }} bộ lọc đang áp dụng</span>
                        @endif
                    </div>
                </div>
            </div>

            <div class="vip-actions">
                <button type="button" class="vip-btn-soft vip-ripple" onclick="window.print()">
                    <i class="bi bi-printer"></i> In
                </button>

                @can('create', \App\Models\CRM\Customer::class)
                    <button type="button" class="vip-btn vip-ripple" onclick="openCustomerForm()">
                        <i class="bi bi-plus-lg"></i> Thêm khách
                    </button>
                @endcan
            </div>
        </div>

        <div class="vip-kpi-grid">
            <div class="vip-kpi" style="--glow:rgba(6,182,212,.12)">
                <div>
                    <div class="vip-kpi-label">Tổng khách hàng</div>
                    <div class="vip-kpi-value js-count" data-count="{{ $totalCustomers }}">0</div>
                    <div class="vip-kpi-note">Theo bộ lọc hiện tại</div>
                </div>
                <div class="vip-kpi-icon"><i class="bi bi-database"></i></div>
            </div>

            <div class="vip-kpi" style="--glow:rgba(245,158,11,.12)">
                <div>
                    <div class="vip-kpi-label">Lead trang này</div>
                    <div class="vip-kpi-value js-count" data-count="{{ $leadCount }}">0</div>
                    <div class="vip-kpi-note">Cần chăm sóc tiếp</div>
                </div>
                <div class="vip-kpi-icon"><i class="bi bi-lightning-charge"></i></div>
            </div>

            <div class="vip-kpi" style="--glow:rgba(34,197,94,.12)">
                <div>
                    <div class="vip-kpi-label">Member trang này</div>
                    <div class="vip-kpi-value js-count" data-count="{{ $memberCount }}">0</div>
                    <div class="vip-kpi-note">Khách giá trị cao</div>
                </div>
                <div class="vip-kpi-icon"><i class="bi bi-gem"></i></div>
            </div>

            <div class="vip-kpi" style="--glow:rgba(14,165,233,.12)">
                <div>
                    <div class="vip-kpi-label">Tiềm năng</div>
                    <div class="vip-kpi-value js-count" data-count="{{ $potentialCount }}">0</div>
                    <div class="vip-kpi-note">Trong trang hiện tại</div>
                </div>
                <div class="vip-kpi-icon"><i class="bi bi-star"></i></div>
            </div>
        </div>
    </div>

    <form action="{{ route('customers.index') }}" method="GET" class="vip-panel vip-reveal">
        <div class="vip-panel-head">
            <div>
                <h2 class="vip-panel-title">
                    <i class="bi bi-funnel"></i> Bộ lọc thông minh
                </h2>
                <div class="vip-panel-sub">Tìm nhanh, lọc theo khu vực, loại khách, trạng thái và người phụ trách.</div>
            </div>

            <div class="d-flex flex-wrap gap-2">
                @if(request()->hasAny(['search', 'region_id', 'customer_type_id', 'customer_status', 'owner_id', 'has_order']))
                    <a href="{{ route('customers.index') }}" class="vip-btn-ghost vip-ripple">
                        <i class="bi bi-x-circle"></i> Xóa lọc
                    </a>
                @endif

                <button type="submit" class="vip-btn vip-ripple">
                    <i class="bi bi-search"></i> Lọc
                </button>
            </div>
        </div>

        <div class="vip-filter-body">
            <div class="vip-filter-grid">
                <div>
                    <label class="vip-filter-label">Tìm kiếm</label>
                    <input type="text"
                           name="search"
                           class="vip-control"
                           value="{{ request('search') }}"
                           placeholder="Tên, SĐT, Email...">
                </div>

                <div>
                    <label class="vip-filter-label">Khu vực</label>
                    <select name="region_id" class="vip-control">
                        <option value="">Tất cả</option>
                        @foreach($regions as $region)
                            <option value="{{ $region->id }}" {{ request('region_id') == $region->id ? 'selected' : '' }}>
                                {{ $region->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="vip-filter-label">Loại khách</label>
                    <select name="customer_type_id" class="vip-control">
                        <option value="">Tất cả</option>
                        @foreach($customerTypes as $type)
                            <option value="{{ $type->id }}" {{ request('customer_type_id') == $type->id ? 'selected' : '' }}>
                                {{ $type->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="vip-filter-label">Trạng thái</label>
                    <select name="customer_status" class="vip-control">
                        <option value="">Tất cả</option>
                        <option value="lead" {{ request('customer_status') == 'lead' ? 'selected' : '' }}>Lead</option>
                        <option value="member" {{ request('customer_status') == 'member' ? 'selected' : '' }}>Member</option>
                        <option value="retail" {{ request('customer_status') == 'retail' ? 'selected' : '' }}>Khách lẻ</option>
                    </select>
                </div>

                <div class="vip-filter-action">
                    <button type="submit" class="vip-btn w-100 vip-ripple">
                        <i class="bi bi-funnel"></i> Lọc
                    </button>
                </div>
            </div>

            <details class="vip-advanced">
                <summary>
                    <i class="bi bi-sliders"></i> Bộ lọc nâng cao
                </summary>

                <div class="vip-advanced-grid">
                    <div>
                        <label class="vip-filter-label">Khách đã mua hàng</label>
                        <select name="has_order" class="vip-control">
                            <option value="">Tất cả</option>
                            <option value="1" {{ request('has_order') == '1' ? 'selected' : '' }}>Đã mua hàng</option>
                            <option value="0" {{ request('has_order') == '0' ? 'selected' : '' }}>Chưa mua</option>
                        </select>
                    </div>

                    @if($canFilterOwner)
                        <div>
                            <label class="vip-filter-label">Người phụ trách</label>
                            <select name="owner_id" class="vip-control">
                                <option value="">Tất cả</option>
                                @foreach($users as $u)
                                    <option value="{{ $u->id }}" {{ request('owner_id') == $u->id ? 'selected' : '' }}>
                                        {{ $u->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    @else
                        <input type="hidden" name="owner_id" value="{{ $authUser->id }}">
                    @endif
                </div>
            </details>

            @if(request()->hasAny(['search', 'region_id', 'customer_type_id', 'customer_status', 'owner_id', 'has_order']))
                <div class="vip-filter-note">
                    <i class="bi bi-info-circle"></i>
                    Đang áp dụng bộ lọc. Nhấn <b>Xóa lọc</b> để xem tất cả.
                </div>
            @endif
        </div>
    </form>

    <div class="vip-table-shell vip-reveal">
        <div class="vip-table-top">
            <div>
                <div class="vip-table-title">
                    <i class="bi bi-table"></i> Danh sách khách hàng
                </div>
                <div class="vip-table-count">
                    Hiển thị {{ $customerPageItems->count() }} / {{ $totalCustomers }} khách hàng.
                </div>
            </div>

            <div class="d-flex gap-2 flex-wrap justify-content-end">
                <button type="submit" form="bulkAgentProfileForm" class="vip-btn" onclick="return confirm('Chuyển các khách hàng đã tích sang hồ sơ đại lý?')">
                    <i class="bi bi-check2-square"></i> Chuyển sang hồ sơ đại lý
                </button>
                <a href="{{ route('customer-profiles.index') }}" class="vip-btn-soft">
                    <i class="bi bi-card-list"></i> Danh sách đại lý
                </a>
            </div>
        </div>

        <form id="bulkAgentProfileForm" method="POST" action="{{ route('customer-profiles.sync-customers') }}">
            @csrf
        </form>

        <div class="vip-table-wrap">
            <div class="vip-table-responsive">
                <table class="vip-table">
                    <thead>
                    <tr>
                        <th class="text-center" style="width:44px">
                            <input type="checkbox" id="selectAllAgentCustomers" class="form-check-input" title="Chọn tất cả trang này">
                        </th>
                        <th>Khách hàng</th>
                        <th>Số điện thoại</th>
                        <th>Loại</th>
                        <th>Trạng thái</th>
                        <th>Hoa hồng</th>
                        <th>Ghi chú</th>
                        <th>Phụ trách</th>
                        <th>Đánh giá</th>
                        <th>Khu vực</th>
                        <th>Cập nhật</th>
                        <th class="text-center" style="width:120px">Thao tác</th>
                    </tr>
                    </thead>

                    <tbody>
                    @forelse ($customers as $c)
                        @php
                            [$statusText, $statusClass] = $statusBadge($c->customer_status);
                            $customerInitials = $initials($c->name ?? '');
                        @endphp

                        <tr data-owner-id="{{ $c->owner_id }}" data-user-id="{{ $authUser->id }}">

                            <td class="text-center">
                                <input type="checkbox" name="customer_ids[]" value="{{ $c->id }}" form="bulkAgentProfileForm" class="form-check-input customer-profile-check">
                            </td>

                            <td>
                                <div class="vip-customer">
                                    <div class="vip-avatar">{{ $customerInitials }}</div>
                                    <div>
                                        <div class="vip-name">{{ $c->name ?? '-' }}</div>
                                        <div class="vip-subline">
                                            {{ $c->latestLead?->source?->name ?? 'Chưa có nguồn' }}
                                            @if(!empty($c->facebook_link))
                                                · <a href="{{ $c->facebook_link }}" target="_blank" rel="noopener" class="text-info text-decoration-none fw-bold">Facebook</a>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            </td>

                            <td>
                                <span class="vip-phone">{{ $c->phone ?? '-' }}</span>
                            </td>

                            <td>
                                <span class="vip-badge vip-type">
                                    {{ $c->customerType?->name ?? 'N/A' }}
                                </span>
                            </td>

                            <td>
                                <span class="vip-badge {{ $statusClass }}">
                                    {{ $statusText }}
                                </span>
                            </td>

                            <td class="fw-bold">
                                {{ $commissionText($c->customer_status) }}
                            </td>

                            <td>
                                <div class="vip-note">
                                    {{ $c->latestLead?->note ?? '-' }}
                                </div>
                            </td>

                            <td>{{ $c->assignedUser?->name ?? 'Chưa gán' }}</td>

                            <td>
                                @if($c->is_potential)
                                    <span class="vip-potential">⭐ Tiềm năng</span>
                                @else
                                    <span class="text-muted">-</span>
                                @endif
                            </td>

                            <td>{{ $c->region?->name ?? '-' }}</td>

                            <td>{{ $c->updated_at?->format('d/m/Y H:i') ?? '-' }}</td>

                            <td>
                                <div class="vip-actions-cell">
                                    @can('view', $c)
                                        <a href="{{ route('customers.show', $c->id) }}"
                                           class="vip-icon-btn view"
                                           title="Xem chi tiết">
                                            <i class="bi bi-eye-fill"></i>
                                        </a>
                                    @endcan

                                    @can('update', $c)
                                        <button type="button"
                                                class="vip-icon-btn edit"
                                                onclick="openCustomerForm('{{ route('customers.popup-form', $c->id) }}')"
                                                title="Sửa">
                                            <i class="bi bi-pencil"></i>
                                        </button>
                                    @endcan

                                    @can('delete', $c)
                                        <form action="{{ route('customers.destroy', $c->id) }}"
                                              method="POST"
                                              class="d-inline-block"
                                              onsubmit="return confirm('Xóa khách hàng này?')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="vip-icon-btn delete" title="Xóa">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </form>
                                    @endcan
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="12">
                                <div class="vip-empty">
                                    <i class="bi bi-inbox"></i> Không có dữ liệu khách hàng.
                                </div>
                            </td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="vip-mobile-list">
            @forelse ($customers as $c)
                @php
                    [$statusText, $statusClass] = $statusBadge($c->customer_status);
                    $customerInitials = $initials($c->name ?? '');
                @endphp

                <div class="vip-mobile-card">
                    <label class="d-inline-flex align-items-center gap-2 mb-2 fw-bold">
                        <input type="checkbox" name="customer_ids[]" value="{{ $c->id }}" form="bulkAgentProfileForm" class="form-check-input customer-profile-check">
                        Chọn làm đại lý
                    </label>
                    <div class="vip-mobile-top">
                        <div class="vip-avatar">{{ $customerInitials }}</div>

                        <div class="vip-mobile-info">
                            <div class="d-flex justify-content-between gap-2 align-items-start">
                                <div>
                                    <div class="vip-mobile-name">{{ $c->name ?? '-' }}</div>
                                    <div class="vip-mobile-phone">
                                        <i class="bi bi-telephone"></i> {{ $c->phone ?? '-' }}
                                    </div>
                                </div>

                                <span class="vip-badge {{ $statusClass }}">
                                    {{ $statusText }}
                                </span>
                            </div>
                        </div>
                    </div>

                    <div class="vip-mobile-meta">
                        <div>
                            <div class="vip-mobile-label">Loại khách</div>
                            <div class="vip-mobile-value">{{ $c->customerType?->name ?? 'N/A' }}</div>
                        </div>

                        <div>
                            <div class="vip-mobile-label">Phụ trách</div>
                            <div class="vip-mobile-value">{{ $c->assignedUser?->name ?? 'Chưa gán' }}</div>
                        </div>

                        <div>
                            <div class="vip-mobile-label">Khu vực</div>
                            <div class="vip-mobile-value">{{ $c->region?->name ?? '-' }}</div>
                        </div>

                        <div>
                            <div class="vip-mobile-label">Cập nhật</div>
                            <div class="vip-mobile-value">{{ $c->updated_at?->format('d/m/Y H:i') ?? '-' }}</div>
                        </div>
                    </div>

                    @if(!empty($c->latestLead?->note))
                        <div class="vip-note mt-3">
                            <b>Ghi chú:</b> {{ $c->latestLead?->note }}
                        </div>
                    @endif

                    <div class="vip-mobile-actions">
                        @can('view', $c)
                            <a href="{{ route('customers.show', $c->id) }}">
                                <i class="bi bi-eye"></i> Xem
                            </a>
                        @endcan

                        @can('update', $c)
                            <button type="button" onclick="openCustomerForm('{{ route('customers.popup-form', $c->id) }}')">
                                <i class="bi bi-pencil"></i> Sửa
                            </button>
                        @endcan

                        @can('delete', $c)
                            <form action="{{ route('customers.destroy', $c->id) }}"
                                  method="POST"
                                  onsubmit="return confirm('Xóa khách hàng này?')">
                                @csrf
                                @method('DELETE')
                                <button type="submit">
                                    <i class="bi bi-trash"></i> Xóa
                                </button>
                            </form>
                        @endcan
                    </div>
                </div>
            @empty
                <div class="vip-mobile-card text-center text-muted fw-bold">
                    Không có dữ liệu khách hàng.
                </div>
            @endforelse
        </div>

        <div class="vip-pagination px-1">
            {{ $customers->links('pagination::bootstrap-5') }}
        </div>
    </div>
</div>

<div class="modal fade" id="customerModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <div class="modal-content" id="customerModalContent"></div>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const root = document.getElementById('vipCustomers');

    if (root) {
        root.addEventListener('mousemove', function (event) {
            const rect = root.getBoundingClientRect();
            root.style.setProperty('--mx', (((event.clientX - rect.left) / Math.max(1, rect.width)) * 100).toFixed(2) + '%');
            root.style.setProperty('--my', (((event.clientY - rect.top) / Math.max(1, rect.height)) * 100).toFixed(2) + '%');
        }, { passive: true });

        root.querySelectorAll('.vip-reveal').forEach(function (el, index) {
            setTimeout(function () {
                el.classList.add('show');
            }, 70 + index * 90);
        });

        root.querySelectorAll('.js-count[data-count]').forEach(function (el) {
            const target = Number(el.dataset.count || 0);
            if (!Number.isFinite(target)) return;

            let start = null;
            const duration = 700;

            function tick(timestamp) {
                if (!start) start = timestamp;
                const progress = Math.min(1, (timestamp - start) / duration);
                const eased = 1 - Math.pow(1 - progress, 3);
                el.textContent = new Intl.NumberFormat('vi-VN').format(Math.round(target * eased));

                if (progress < 1) {
                    requestAnimationFrame(tick);
                } else {
                    el.textContent = new Intl.NumberFormat('vi-VN').format(target);
                }
            }

            requestAnimationFrame(tick);
        });

        root.querySelectorAll('.vip-ripple').forEach(function (btn) {
            btn.addEventListener('click', function (event) {
                const rect = btn.getBoundingClientRect();
                const dot = document.createElement('span');
                const size = Math.max(rect.width, rect.height);

                dot.className = 'vip-ripple-dot';
                dot.style.width = size + 'px';
                dot.style.height = size + 'px';
                dot.style.left = (event.clientX - rect.left - size / 2) + 'px';
                dot.style.top = (event.clientY - rect.top - size / 2) + 'px';

                btn.appendChild(dot);
                setTimeout(function () {
                    dot.remove();
                }, 700);
            });
        });
    }
});

/** ===== Toast ===== */
function showToast(message, isSuccess = false) {
  const toastEl = document.getElementById('egoToast');
  const msgEl = document.getElementById('egoToastMsg');

  if (!toastEl || !msgEl || !window.bootstrap) {
    alert(message);
    return;
  }

  toastEl.classList.remove('text-bg-danger', 'text-bg-success');
  toastEl.classList.add(isSuccess ? 'text-bg-success' : 'text-bg-danger');
  msgEl.textContent = message;

  const toast = bootstrap.Toast.getOrCreateInstance(toastEl, { delay: 3500 });
  toast.show();
}

/** ===== Open modal form (create/edit) ===== */
async function openCustomerForm(url) {
  const modalEl = document.getElementById('customerModal');
  const contentEl = document.getElementById('customerModalContent');
  const fetchUrl = url || "{{ route('customers.popup-form') }}";

  contentEl.innerHTML = `
    <div class="p-4 text-center">
        <div class="spinner-border text-info mb-3" role="status"></div>
        <div class="fw-bold text-muted">Đang tải form khách hàng...</div>
    </div>
  `;

  const res = await fetch(fetchUrl, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
  contentEl.innerHTML = await res.text();

  if (window.bootstrap) new bootstrap.Modal(modalEl).show();
}

/** ===== Helpers ===== */
const vnPhoneRegex = /^0(3|5|7|8|9)\d{8}$/;

function normalizeDigits(val){
    return (val || '').replace(/\D/g,'').slice(0,10);
}

function clearServerErrors(form) {
  form.querySelectorAll('.invalid-feedback.d-block.js-server-error').forEach(el => el.remove());
  form.querySelectorAll('.is-invalid').forEach(el => el.classList.remove('is-invalid'));
}

function setFieldError(input, message) {
  if (!input) return;
  input.classList.add('is-invalid');

  const old = input.parentElement.querySelector('.invalid-feedback.d-block.js-server-error');
  if (old) old.remove();

  const div = document.createElement('div');
  div.className = 'invalid-feedback d-block js-server-error';
  div.textContent = message;
  input.parentElement.appendChild(div);
}

document.addEventListener('submit', async function (e) {
  const form = e.target;
  if (!form || form.id !== 'customerForm') return;

  e.preventDefault();
  clearServerErrors(form);

  const phoneInput = form.querySelector('input[name="phone"]');
  const nameInput  = form.querySelector('input[name="name"]');
  const submitBtn  = form.querySelector('button[type="submit"]');

  const name = (nameInput?.value || '').trim();
  if (!name) {
    setFieldError(nameInput, 'Vui lòng nhập tên khách hàng!');
    showToast('Vui lòng nhập tên khách hàng!');
    return;
  }

  phoneInput.value = normalizeDigits(phoneInput.value);
  if (!vnPhoneRegex.test(phoneInput.value)) {
    setFieldError(phoneInput, 'SĐT không hợp lệ (10 số, bắt đầu 03/05/07/08/09).');
    showToast('SĐT không hợp lệ (10 số, bắt đầu 03/05/07/08/09).');
    return;
  }

  if (submitBtn) submitBtn.disabled = true;

  try {
    const action = form.getAttribute('action');
    const fd = new FormData(form);

    const res = await fetch(action, {
      method: 'POST',
      headers: {
        'Accept': 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
        'X-CSRF-TOKEN': form.querySelector('input[name="_token"]')?.value || ''
      },
      body: fd
    });

    if (res.status === 422) {
      const data = await res.json().catch(() => ({}));
      const errors = data.errors || {};

      if (errors.phone?.length) {
        setFieldError(phoneInput, errors.phone[0]);
        showToast(errors.phone[0]);
      } else {
        const k = Object.keys(errors)[0];
        const msg = k ? (errors[k]?.[0] || 'Dữ liệu không hợp lệ') : 'Dữ liệu không hợp lệ';
        showToast(msg);
      }
      return;
    }

    if (!res.ok) {
      showToast('Có lỗi hệ thống, vui lòng thử lại!');
      return;
    }

    const data = await res.json().catch(() => ({}));
    showToast(data.message || 'Lưu khách hàng thành công!', true);

    const modalEl = form.closest('.modal');
    if (modalEl && window.bootstrap) {
      const modal = bootstrap.Modal.getInstance(modalEl) || new bootstrap.Modal(modalEl);
      modal.hide();
    }

    window.location.reload();

  } finally {
    if (submitBtn) submitBtn.disabled = false;
  }
});
</script>
<script>
document.addEventListener('change', function (event) {
    if (event.target && event.target.id === 'selectAllAgentCustomers') {
        document.querySelectorAll('.customer-profile-check').forEach(function (checkbox) {
            checkbox.checked = event.target.checked;
        });
    }
});
</script>

@endsection
