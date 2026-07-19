@extends('layouts.app')

@section('content')
@php
    $fmtMoney = function ($v) {
        return number_format((float)($v ?? 0), 0, ',', '.') . ' đ';
    };

    $fmtPercent = function ($v) {
        return number_format((float)($v ?? 0), 2, ',', '.') . '%';
    };

    $rules = collect($rules ?? [])->values();
    $salesUsers = collect($salesUsers ?? [])->values();
    $salarySettings = collect($salarySettings ?? []);
    $kpiTiers = collect($kpiTiers ?? [])->values();

    $salesNameMap = $salesUsers->mapWithKeys(fn($u) => [(int)$u->id => $u->name])->toArray();

    $typeLabel = [
        'project' => 'Công trình',
        'trade_product' => 'Thương mại',
        'solar_panel' => 'Tấm pin',
    ];

    $customerStatusLabel = [
        'all' => 'Tất cả khách',
        'lead' => 'Lead / Khách ADS',
        'member' => 'Member / Tự phát triển',
        'retail' => 'Khách lẻ',
    ];

    $targetLabel = [
        'all' => 'Tất cả',
        'customer_status' => 'Trạng thái khách',
        'product' => 'Sản phẩm',
        'category' => 'Danh mục',
        'brand' => 'Thương hiệu',
        'keyword' => 'Từ khóa / model',
    ];

    $calcLabel = [
        'percent' => '% doanh thu',
        'fixed_per_item' => 'Tiền / sản phẩm',
        'fixed_per_kwp' => 'Tiền / kWp',
        'fixed_per_order' => 'Cố định / đơn',
    ];

    $baseLabel = [
        'revenue_before_vat' => 'Doanh thu trước VAT',
        'revenue_after_vat' => 'Doanh thu sau VAT',
        'gross_profit' => 'Lợi nhuận gộp',
        'quantity' => 'Số lượng',
        'kwp' => 'kWp',
    ];

    $bonusTypeLabel = [
        'fixed' => 'Thưởng cố định',
        'percent_revenue' => '% doanh số',
        'percent_commission' => '% hoa hồng',
        'salary_percent' => '% lương cứng',
    ];

    $activeRules = $rules->where('is_active', 1)->count();
    $totalBaseSalary = $salarySettings->where('is_active', 1)->sum('base_salary');
    $totalTargetRevenue = $salarySettings->where('is_active', 1)->sum('target_revenue');
@endphp

<style>
.sales-policy-pro{
    --ink:#07111f;
    --muted:#64748b;
    --line:#dbe4ee;
    --soft:#f8fafc;
    --cyan:#06b6d4;
    --teal:#0f766e;
    --green:#16a34a;
    --red:#e11d48;
    --amber:#f59e0b;
    font-size:13px;
}
.sales-policy-pro *{letter-spacing:-.01em}
.spp-hero{
    position:relative;
    overflow:hidden;
    border-radius:28px;
    padding:22px;
    color:#fff;
    background:
        radial-gradient(circle at 10% 15%, rgba(34,211,238,.38), transparent 28%),
        radial-gradient(circle at 92% 0%, rgba(16,185,129,.35), transparent 30%),
        linear-gradient(135deg,#06111f 0%,#0f766e 58%,#05bcd4 100%);
    box-shadow:0 28px 80px rgba(15,23,42,.22);
}
.spp-hero:before,
.spp-hero:after{
    content:"";
    position:absolute;
    width:260px;
    height:260px;
    border-radius:999px;
    background:rgba(255,255,255,.10);
    animation:sppFloat 9s ease-in-out infinite;
}
.spp-hero:before{right:-90px;top:-140px}
.spp-hero:after{left:44%;bottom:-210px;animation-delay:1.5s}
@keyframes sppFloat{
    0%,100%{transform:translate3d(0,0,0) scale(1)}
    50%{transform:translate3d(20px,-18px,0) scale(1.08)}
}
.spp-hero-inner{
    position:relative;
    z-index:2;
    display:flex;
    justify-content:space-between;
    align-items:flex-start;
    gap:16px;
    flex-wrap:wrap;
}
.spp-kicker{
    display:inline-flex;
    align-items:center;
    height:26px;
    padding:0 11px;
    border-radius:999px;
    background:rgba(255,255,255,.16);
    border:1px solid rgba(255,255,255,.22);
    color:#e0fcff;
    font-size:11px;
    font-weight:950;
}
.spp-hero h1{
    margin:9px 0 0;
    font-size:34px;
    font-weight:950;
    letter-spacing:-.06em;
}
.spp-hero p{
    margin:6px 0 0;
    color:rgba(255,255,255,.78);
    font-weight:750;
}
.spp-actions{display:flex;gap:9px;flex-wrap:wrap;justify-content:flex-end}
.spp-btn{
    height:38px;
    border:0;
    border-radius:14px;
    padding:0 14px;
    display:inline-flex;
    align-items:center;
    justify-content:center;
    gap:7px;
    font-weight:950;
    text-decoration:none;
    white-space:nowrap;
    transition:.2s ease;
    cursor:pointer;
}
.spp-btn:hover{transform:translateY(-2px)}
.spp-btn-white{background:#fff;color:#0f766e}
.spp-btn-glass{background:rgba(255,255,255,.15);color:#fff;border:1px solid rgba(255,255,255,.22)}
.spp-btn-main{background:linear-gradient(135deg,#06b6d4,#16a34a);color:#fff;box-shadow:0 14px 30px rgba(6,182,212,.25)}
.spp-btn-dark{background:#07111f;color:#fff}
.spp-btn-soft{background:#fff;color:#07111f;border:1px solid var(--line)}
.spp-btn-red{background:#fff1f2;color:#e11d48;border:1px solid #fecdd3}
.spp-stat-grid{
    display:grid;
    grid-template-columns:repeat(6,minmax(0,1fr));
    gap:12px;
    margin-top:15px;
}
.spp-stat{
    position:relative;
    overflow:hidden;
    border-radius:22px;
    background:#fff;
    border:1px solid var(--line);
    padding:14px;
    box-shadow:0 16px 42px rgba(15,23,42,.07);
    animation:sppUp .45s ease both;
}
.spp-stat:after{
    content:"";
    position:absolute;
    width:90px;
    height:90px;
    border-radius:999px;
    right:-34px;
    top:-42px;
    background:linear-gradient(135deg,rgba(6,182,212,.19),rgba(16,185,129,.16));
}
.spp-stat.dark{background:#07111f;border-color:#07111f}
.spp-stat span{
    display:block;
    color:#64748b;
    font-size:10px;
    text-transform:uppercase;
    font-weight:950;
}
.spp-stat.dark span{color:rgba(255,255,255,.65)}
.spp-stat b{
    display:block;
    margin-top:5px;
    color:#07111f;
    font-size:20px;
    font-weight:950;
}
.spp-stat.dark b{color:#fff}
@keyframes sppUp{
    from{opacity:0;transform:translateY(14px)}
    to{opacity:1;transform:translateY(0)}
}

.spp-monthbar{
    margin-top:14px;
    border:1px solid var(--line);
    background:rgba(255,255,255,.92);
    backdrop-filter:blur(16px);
    border-radius:22px;
    padding:12px;
    display:flex;
    justify-content:space-between;
    align-items:center;
    gap:12px;
    box-shadow:0 16px 42px rgba(15,23,42,.07);
}
.spp-monthbar-left{
    display:flex;
    align-items:center;
    gap:10px;
}
.spp-monthbar-icon{
    width:42px;
    height:42px;
    border-radius:16px;
    display:flex;
    align-items:center;
    justify-content:center;
    background:linear-gradient(135deg,#ecfeff,#d1fae5);
    color:#0f766e;
    font-size:20px;
}
.spp-monthbar-title{
    color:#07111f;
    font-weight:950;
    font-size:15px;
}
.spp-monthbar-sub{
    color:#64748b;
    font-size:12px;
    font-weight:750;
    margin-top:2px;
}
.spp-monthbar-form{
    display:flex;
    align-items:center;
    gap:8px;
    flex-wrap:wrap;
}
.spp-monthbar-form input[type=month]{
    height:38px;
    min-width:170px;
    border:1px solid var(--line);
    border-radius:14px;
    padding:0 12px;
    color:#07111f;
    font-size:13px;
    font-weight:900;
    background:#fff;
    outline:none;
}
.spp-monthbar-form input[type=month]:focus{
    border-color:#06b6d4;
    box-shadow:0 0 0 4px rgba(6,182,212,.13);
}
@media(max-width:900px){
    .spp-monthbar{
        align-items:flex-start;
        flex-direction:column;
    }
    .spp-monthbar-form{
        width:100%;
    }
    .spp-monthbar-form input[type=month],
    .spp-monthbar-form .spp-btn{
        flex:1;
    }
}

.spp-nav{
    margin-top:14px;
    display:flex;
    gap:8px;
    flex-wrap:wrap;
}
.spp-nav button{
    height:36px;
    border-radius:999px;
    border:1px solid var(--line);
    background:#fff;
    color:#0f172a;
    font-weight:900;
    padding:0 14px;
}
.spp-nav button.active{
    background:#07111f;
    color:#fff;
    border-color:#07111f;
}
.spp-layout{
    display:grid;
    grid-template-columns:minmax(0,1fr) 350px;
    gap:16px;
    margin-top:16px;
}
.spp-section{
    border-radius:24px;
    background:#fff;
    border:1px solid var(--line);
    box-shadow:0 16px 46px rgba(15,23,42,.07);
    overflow:hidden;
    margin-bottom:16px;
}
.spp-head{
    padding:15px 17px;
    border-bottom:1px solid var(--line);
    background:linear-gradient(135deg,#fff,#f8fafc);
    display:flex;
    justify-content:space-between;
    align-items:center;
    gap:12px;
    flex-wrap:wrap;
}
.spp-title{margin:0;font-size:19px;color:#07111f;font-weight:950}
.spp-sub{margin-top:3px;color:#64748b;font-weight:750;font-size:12px}
.spp-table-wrap{overflow:auto}
.spp-table{
    width:100%;
    min-width:1080px;
    border-collapse:separate;
    border-spacing:0;
}
.spp-table th{
    background:#f8fafc;
    color:#475569;
    font-size:10px;
    text-transform:uppercase;
    font-weight:950;
    padding:11px 12px;
    border-bottom:1px solid var(--line);
    white-space:nowrap;
}
.spp-table td{
    padding:12px;
    border-bottom:1px solid #eef2f7;
    vertical-align:middle;
}
.spp-table tr:hover td{background:#fbfdff}
.spp-name{
    color:#07111f;
    font-weight:950;
    line-height:1.25;
}
.spp-meta{
    color:#64748b;
    font-size:12px;
    font-weight:750;
    margin-top:2px;
}
.spp-chip{
    display:inline-flex;
    align-items:center;
    min-height:25px;
    padding:0 10px;
    border-radius:999px;
    background:#ecfeff;
    color:#0e7490;
    border:1px solid #a5f3fc;
    font-size:11px;
    font-weight:950;
    white-space:nowrap;
}
.spp-chip.green{background:#dcfce7;color:#166534;border-color:#bbf7d0}
.spp-chip.gray{background:#f1f5f9;color:#64748b;border-color:#e2e8f0}
.spp-chip.dark{background:#07111f;color:#fff;border-color:#07111f}
.spp-chip.amber{background:#fff7ed;color:#c2410c;border-color:#fed7aa}
.spp-hidden{display:none}
.spp-quick-grid{
    padding:14px;
    display:grid;
    grid-template-columns:repeat(3,minmax(0,1fr));
    gap:12px;
}
.spp-quick-card{
    border:1px solid var(--line);
    border-radius:20px;
    padding:14px;
    background:
        radial-gradient(circle at 100% 0%, rgba(6,182,212,.10), transparent 35%),
        #fff;
}
.spp-quick-card span{
    display:block;
    font-size:10px;
    color:#64748b;
    font-weight:950;
    text-transform:uppercase;
}
.spp-quick-card b{
    display:block;
    margin-top:5px;
    font-size:22px;
    color:#07111f;
    font-weight:950;
}
.spp-quick-card small{
    display:block;
    margin-top:3px;
    color:#64748b;
    font-weight:750;
}
.spp-condition-grid{
    padding:14px;
    display:grid;
    grid-template-columns:repeat(4,minmax(0,1fr));
    gap:10px;
}
.spp-condition{
    border:1px solid var(--line);
    border-radius:18px;
    padding:12px;
    background:#f8fafc;
    display:flex;
    align-items:center;
    gap:10px;
}
.spp-condition-dot{
    width:14px;
    height:14px;
    border-radius:999px;
    background:#cbd5e1;
}
.spp-condition.active .spp-condition-dot{
    background:#16a34a;
    box-shadow:0 0 0 5px rgba(22,163,74,.12);
}
.spp-condition b{
    display:block;
    color:#07111f;
    font-weight:950;
}
.spp-condition span{
    display:block;
    color:#64748b;
    font-size:12px;
    font-weight:750;
}
.spp-side{
    display:grid;
    gap:16px;
    align-content:start;
}
.spp-side-card{
    border-radius:24px;
    overflow:hidden;
    background:#07111f;
    color:#fff;
    box-shadow:0 18px 48px rgba(15,23,42,.16);
}
.spp-side-card .inner{padding:16px}
.spp-side-card h3{margin:0;font-size:19px;font-weight:950}
.spp-side-card p{color:rgba(255,255,255,.68);font-weight:750;font-size:12px;margin:5px 0 0}
.spp-help-list{display:grid;gap:8px;margin-top:14px}
.spp-help-item{
    border-radius:15px;
    background:rgba(255,255,255,.08);
    border:1px solid rgba(255,255,255,.10);
    padding:10px;
    font-size:12px;
    color:rgba(255,255,255,.78);
    font-weight:750;
}
.spp-help-item b{display:block;color:#fff;margin-bottom:3px}
.spp-empty{
    padding:28px;
    text-align:center;
    color:#64748b;
    font-weight:800;
}
.spp-modal .modal-content{
    border:0;
    border-radius:26px;
    overflow:hidden;
    box-shadow:0 28px 90px rgba(15,23,42,.30);
}
.spp-modal .modal-header{
    background:linear-gradient(135deg,#07111f,#0f766e);
    color:#fff;
    border:0;
    padding:18px 20px;
}
.spp-modal .modal-title{font-weight:950}
.spp-modal .modal-body{padding:18px;background:#f8fafc}
.spp-form-grid{
    display:grid;
    grid-template-columns:repeat(12,minmax(0,1fr));
    gap:10px;
}
.spp-field{display:flex;flex-direction:column;gap:5px}
.spp-field label{
    color:#475569;
    font-size:10px;
    text-transform:uppercase;
    font-weight:950;
}
.spp-field input,
.spp-field select,
.spp-field textarea{
    width:100%;
    height:39px;
    border:1px solid var(--line);
    border-radius:13px;
    padding:0 11px;
    color:#07111f;
    font-size:13px;
    font-weight:850;
    outline:none;
    background:#fff;
}
.spp-field textarea{height:74px;padding:10px 11px}
.spp-field input:focus,
.spp-field select:focus,
.spp-field textarea:focus{
    border-color:#06b6d4;
    box-shadow:0 0 0 4px rgba(6,182,212,.13);
}
.spp-span-2{grid-column:span 2}
.spp-span-3{grid-column:span 3}
.spp-span-4{grid-column:span 4}
.spp-span-5{grid-column:span 5}
.spp-span-6{grid-column:span 6}
.spp-span-8{grid-column:span 8}
.spp-span-12{grid-column:span 12}
.spp-form-section{
    grid-column:span 12;
    padding:12px;
    border-radius:18px;
    background:#fff;
    border:1px solid var(--line);
}
.spp-form-section-title{
    color:#07111f;
    font-size:14px;
    font-weight:950;
    margin-bottom:10px;
}
.spp-checks{
    display:flex;
    flex-wrap:wrap;
    gap:14px;
}
.spp-checks input[type=checkbox]{
    width:18px;
    height:18px;
    accent-color:#06b6d4;
}
.spp-checks label{
    font-weight:850;
    color:#07111f;
}
.spp-modal .modal-footer{
    border:0;
    padding:14px 18px 18px;
    background:#f8fafc;
}
.spp-save-bar{
    position:sticky;
    bottom:10px;
    z-index:30;
    margin-top:16px;
    display:flex;
    justify-content:flex-end;
    gap:10px;
    border:1px solid var(--line);
    border-radius:20px;
    background:rgba(255,255,255,.88);
    backdrop-filter:blur(15px);
    padding:10px;
    box-shadow:0 20px 55px rgba(15,23,42,.13);
}
@media(max-width:1280px){
    .spp-layout{grid-template-columns:1fr}
    .spp-stat-grid{grid-template-columns:repeat(2,minmax(0,1fr))}
    .spp-condition-grid{grid-template-columns:repeat(2,minmax(0,1fr))}
    .spp-quick-grid{grid-template-columns:1fr}
}
@media(max-width:900px){
    .spp-form-grid{grid-template-columns:1fr}
    .spp-span-2,.spp-span-3,.spp-span-4,.spp-span-5,.spp-span-6,.spp-span-8,.spp-span-12{grid-column:span 1}
}
</style>

<div class="container-fluid sales-policy-pro">
    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    @if(session('error'))
        <div class="alert alert-danger">{{ session('error') }}</div>
    @endif

    @if($errors->any())
        <div class="alert alert-danger">
            <strong>Có lỗi:</strong>
            <ul class="mb-0 mt-2">
                @foreach($errors->all() as $e)
                    <li>{{ $e }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="spp-hero">
        <div class="spp-hero-inner">
            <div>
                <span class="spp-kicker">Sales Commission Policy</span>
                <h1>Chính sách hoa hồng</h1>
                <p>Thiết lập rõ ràng: trạng thái khách, mốc doanh thu, lương cứng Sales, KPI và hoa hồng theo từng nhóm.</p>
            </div>

            <div class="spp-actions">
                <a class="spp-btn spp-btn-glass" href="{{ route('sales.commissions.index', ['month' => $month]) }}">Dashboard</a>
                <a class="spp-btn spp-btn-glass" href="{{ route('sales.commissions.settings', ['month' => $month, 'copy_previous' => 1]) }}">Copy tháng trước</a>
                <button type="button" class="spp-btn spp-btn-white" data-bs-toggle="modal" data-bs-target="#policyModal">Sửa chính sách chung</button>
            </div>
        </div>
    </div>

    <div class="spp-monthbar">
        <div class="spp-monthbar-left">
            <div class="spp-monthbar-icon">
                <i class="bi bi-calendar2-week"></i>
            </div>
            <div>
                <div class="spp-monthbar-title">Chọn tháng áp dụng chính sách</div>
                <div class="spp-monthbar-sub">Mỗi tháng có thể có lương cứng, KPI và rule hoa hồng khác nhau.</div>
            </div>
        </div>

        <form class="spp-monthbar-form" method="GET" action="{{ route('sales.commissions.settings') }}">
            <input type="month"
                   name="month"
                   id="pageMonthPicker"
                   value="{{ $policy->period_month ?? $month }}">
            <button type="submit" class="spp-btn spp-btn-dark">
                <i class="bi bi-search"></i> Xem tháng
            </button>
            <a class="spp-btn spp-btn-soft"
               href="{{ route('sales.commissions.settings', ['month' => now()->format('Y-m')]) }}">
                Tháng hiện tại
            </a>
        </form>
    </div>

    <div class="spp-stat-grid">
        <div class="spp-stat"><span>Tháng áp dụng</span><b id="policyMonthText">{{ $policy->period_month ?? $month }}</b></div>
        <div class="spp-stat"><span>Sales áp dụng</span><b>{{ $salesUsers->count() }}</b></div>
        <div class="spp-stat"><span>Tổng lương cứng</span><b id="totalBaseSalaryText">{{ $fmtMoney($totalBaseSalary) }}</b></div>
        <div class="spp-stat"><span>Target doanh số</span><b id="totalTargetRevenueText">{{ $fmtMoney($totalTargetRevenue) }}</b></div>
        <div class="spp-stat"><span>Bậc KPI</span><b id="kpiCountText">{{ $kpiTiers->count() }}</b></div>
        <div class="spp-stat dark"><span>Rule hoa hồng bật</span><b id="activeRuleCount">{{ $activeRules }}</b></div>
    </div>

    <div class="spp-nav">
        <button type="button" class="active" data-scroll="#policyOverview">Tổng quan</button>
        <button type="button" data-scroll="#ruleSection">Rule hoa hồng</button>
        <button type="button" data-scroll="#salarySection">Lương Sales</button>
        <button type="button" data-scroll="#kpiSection">KPI doanh số</button>
    </div>

    <form id="commissionSettingsForm" method="POST" action="{{ route('sales.commissions.settings.save') }}">
        @csrf

        <input type="hidden" name="period_month" id="policy_period_month" value="{{ $policy->period_month ?? $month }}">
        <input type="hidden" name="name" id="policy_name" value="{{ $policy->name ?? '' }}">
        <input type="hidden" name="status" id="policy_status" value="{{ $policy->status ?? 'active' }}">
        <input type="hidden" name="project_rate_percent" id="policy_project_rate_percent" value="{{ $policy->project_rate_percent ?? 4 }}">
        <input type="hidden" name="trade_rate_percent" id="policy_trade_rate_percent" value="{{ $policy->trade_rate_percent ?? 1 }}">
        <input type="hidden" name="panel_fixed_amount" id="policy_panel_fixed_amount" value="{{ $policy->panel_fixed_amount ?? 15000 }}">
        <input type="hidden" name="is_active" id="policy_is_active" value="{{ !empty($policy->is_active) ? 1 : 0 }}">
        <input type="hidden" name="note" id="policy_note" value="{{ $policy->note ?? '' }}">
        <input type="hidden" name="only_paid" id="policy_only_paid" value="{{ !empty($policy->only_paid) ? 1 : 0 }}">
        <input type="hidden" name="only_shipped" id="policy_only_shipped" value="{{ !empty($policy->only_shipped) ? 1 : 0 }}">
        <input type="hidden" name="only_completed" id="policy_only_completed" value="{{ !empty($policy->only_completed) ? 1 : 0 }}">
        <input type="hidden" name="hold_if_debt" id="policy_hold_if_debt" value="{{ !empty($policy->hold_if_debt) ? 1 : 0 }}">

        <div class="spp-layout">
            <div>
                <div class="spp-section" id="policyOverview">
                    <div class="spp-head">
                        <div>
                            <h2 class="spp-title">1. Tổng quan chính sách</h2>
                            <div class="spp-sub">Chỉ hiển thị các thông số quan trọng. Muốn chỉnh thì mở popup.</div>
                        </div>

                        <button type="button" class="spp-btn spp-btn-soft" data-bs-toggle="modal" data-bs-target="#policyModal">Sửa chính sách chung</button>
                    </div>

                    <div class="spp-quick-grid">
                        <div class="spp-quick-card">
                            <span>Công trình mặc định</span>
                            <b id="policyProjectCard">{{ $fmtPercent($policy->project_rate_percent ?? 4) }}</b>
                            <small>Thường dùng 3% - 5%</small>
                        </div>

                        <div class="spp-quick-card">
                            <span>Thương mại mặc định</span>
                            <b id="policyTradeCard">{{ $fmtPercent($policy->trade_rate_percent ?? 1) }}</b>
                            <small>Thường dùng 0.3% - 2%</small>
                        </div>

                        <div class="spp-quick-card">
                            <span>Tấm pin mặc định</span>
                            <b id="policyPanelCard">{{ $fmtMoney($policy->panel_fixed_amount ?? 15000) }}</b>
                            <small>Tính theo từng sản phẩm / model</small>
                        </div>
                    </div>

                    <div class="spp-condition-grid">
                        <div class="spp-condition {{ !empty($policy->only_paid) ? 'active' : '' }}" id="pillOnlyPaid">
                            <span class="spp-condition-dot"></span>
                            <div><b>Đã thanh toán</b><span>Chỉ tính HH khi đơn có thanh toán</span></div>
                        </div>

                        <div class="spp-condition {{ !empty($policy->only_shipped) ? 'active' : '' }}" id="pillOnlyShipped">
                            <span class="spp-condition-dot"></span>
                            <div><b>Đã xuất kho</b><span>Chỉ tính khi hàng đã xuất</span></div>
                        </div>

                        <div class="spp-condition {{ !empty($policy->only_completed) ? 'active' : '' }}" id="pillOnlyCompleted">
                            <span class="spp-condition-dot"></span>
                            <div><b>Hoàn tất đơn</b><span>Chỉ tính khi đơn hoàn thành</span></div>
                        </div>

                        <div class="spp-condition {{ !empty($policy->hold_if_debt) ? 'active' : '' }}" id="pillHoldDebt">
                            <span class="spp-condition-dot"></span>
                            <div><b>Giữ nếu còn công nợ</b><span>Chưa trả đủ thì giữ HH</span></div>
                        </div>
                    </div>
                </div>

                <div class="spp-section" id="ruleSection">
                    <div class="spp-head">
                        <div>
                            <h2 class="spp-title">2. Rule hoa hồng theo khách & doanh thu</h2>
                            <div class="spp-sub">Chọn trạng thái khách, mốc doanh thu tối thiểu, kiểu tính hoa hồng. Bấm sửa để mở popup.</div>
                        </div>

                        <div class="spp-actions">
                            <button type="button" class="spp-btn spp-btn-soft js-add-rule" data-type="lead_trade">+ Lead SP 0.5%</button>
                            <button type="button" class="spp-btn spp-btn-soft js-add-rule" data-type="lead_project">+ Lead CT 3%</button>
                            <button type="button" class="spp-btn spp-btn-main js-add-rule" data-type="solar_panel">+ Tấm pin</button>
                        </div>
                    </div>

                    <div class="spp-table-wrap">
                        <table class="spp-table">
                            <thead>
                            <tr>
                                <th>Nhóm HH</th>
                                <th>Trạng thái khách</th>
                                <th>Áp dụng cho</th>
                                <th>Mốc doanh thu</th>
                                <th>Cách tính</th>
                                <th>Giá trị</th>
                                <th>Ưu tiên</th>
                                <th>Trạng thái</th>
                                <th style="width:210px;">Thao tác</th>
                            </tr>
                            </thead>

                            <tbody id="ruleList">
                            @forelse($rules as $idx => $rule)
                                @php
                                    $targetText = $rule->target_text ?: ($rule->target_id ? '#'.$rule->target_id : '');
                                    $customerStatus = ($rule->target_type ?? '') === 'customer_status' ? ($targetText ?: 'all') : 'all';
                                    $applyText = ($rule->target_type ?? 'all') === 'customer_status'
                                        ? 'Theo trạng thái khách'
                                        : (($targetLabel[$rule->target_type ?? 'all'] ?? 'Tất cả') . ($targetText ? ': ' . $targetText : ''));

                                    $fromAmount = $rule->from_amount ?? '';
                                    $toAmount = $rule->to_amount ?? '';

                                    if ($fromAmount !== '' && $toAmount !== '') {
                                        $revenueRange = $fmtMoney($fromAmount) . ' - ' . $fmtMoney($toAmount);
                                    } elseif ($fromAmount !== '') {
                                        $revenueRange = 'Từ ' . $fmtMoney($fromAmount);
                                    } elseif ($toAmount !== '') {
                                        $revenueRange = 'Đến ' . $fmtMoney($toAmount);
                                    } else {
                                        $revenueRange = 'Không giới hạn';
                                    }

                                    $fixedAmount = (float)($rule->fixed_amount ?: ($rule->amount_per_unit ?? 0));
                                    $calc = $rule->calculation_type ?? 'percent';
                                    if ($calc === 'fixed_per_item') {
                                        $valueText = $fmtMoney($fixedAmount) . '/SP';
                                    } elseif ($calc === 'fixed_per_kwp') {
                                        $valueText = $fmtMoney($rule->amount_per_kwp ?? 0) . '/kWp';
                                    } elseif ($calc === 'fixed_per_order') {
                                        $valueText = $fmtMoney($fixedAmount) . '/đơn';
                                    } else {
                                        $valueText = $fmtPercent($rule->rate_percent ?? 0);
                                    }

                                    $isActive = !empty($rule->is_active);
                                @endphp

                                <tr class="js-rule-row" data-rule-index="{{ $idx }}">
                                    <td>
                                        <div class="spp-hidden">
                                            <input data-field="commission_type" name="rules[{{ $idx }}][commission_type]" value="{{ $rule->commission_type ?? 'trade_product' }}">
                                            <input data-field="target_type" name="rules[{{ $idx }}][target_type]" value="{{ $rule->target_type ?? 'all' }}">
                                            <input data-field="target_text" name="rules[{{ $idx }}][target_text]" value="{{ $targetText }}">
                                            <input data-field="target_id" name="rules[{{ $idx }}][target_id]" value="{{ $rule->target_id ?? '' }}">
                                            <input data-field="base_type" name="rules[{{ $idx }}][base_type]" value="{{ $rule->base_type ?? 'revenue_before_vat' }}">
                                            <input data-field="calculation_type" name="rules[{{ $idx }}][calculation_type]" value="{{ $calc }}">
                                            <input data-field="rate_percent" name="rules[{{ $idx }}][rate_percent]" value="{{ $rule->rate_percent ?? 0 }}">
                                            <input data-field="fixed_amount" name="rules[{{ $idx }}][fixed_amount]" value="{{ $fixedAmount }}">
                                            <input data-field="amount_per_unit" name="rules[{{ $idx }}][amount_per_unit]" value="{{ $rule->amount_per_unit ?: $fixedAmount }}">
                                            <input data-field="amount_per_kwp" name="rules[{{ $idx }}][amount_per_kwp]" value="{{ $rule->amount_per_kwp ?? 0 }}">
                                            <input data-field="from_amount" name="rules[{{ $idx }}][from_amount]" value="{{ $rule->from_amount ?? '' }}">
                                            <input data-field="to_amount" name="rules[{{ $idx }}][to_amount]" value="{{ $rule->to_amount ?? '' }}">
                                            <input data-field="from_qty" name="rules[{{ $idx }}][from_qty]" value="{{ $rule->from_qty ?? '' }}">
                                            <input data-field="to_qty" name="rules[{{ $idx }}][to_qty]" value="{{ $rule->to_qty ?? '' }}">
                                            <input data-field="priority" name="rules[{{ $idx }}][priority]" value="{{ $rule->priority ?? 10 }}">
                                            <input data-field="is_active" name="rules[{{ $idx }}][is_active]" value="{{ $isActive ? 1 : 0 }}">
                                            <input data-field="note" name="rules[{{ $idx }}][note]" value="{{ $rule->note ?? '' }}">
                                        </div>

                                        <span class="spp-chip js-rule-type">{{ $typeLabel[$rule->commission_type ?? 'trade_product'] ?? 'Thương mại' }}</span>
                                    </td>

                                    <td>
                                        <div class="spp-name js-rule-customer">{{ $customerStatusLabel[$customerStatus] ?? $customerStatus }}</div>
                                        <div class="spp-meta">Điều kiện khách hàng</div>
                                    </td>

                                    <td>
                                        <div class="spp-name js-rule-apply">{{ $applyText }}</div>
                                        <div class="spp-meta js-rule-base">{{ $baseLabel[$rule->base_type ?? 'revenue_before_vat'] ?? 'Doanh thu trước VAT' }}</div>
                                    </td>

                                    <td>
                                        <div class="spp-name js-rule-revenue">{{ $revenueRange }}</div>
                                        <div class="spp-meta js-rule-qty">
                                            @if(($rule->from_qty ?? '') !== '' || ($rule->to_qty ?? '') !== '')
                                                SL: {{ $rule->from_qty ?? 0 }} - {{ $rule->to_qty ?? '∞' }}
                                            @else
                                                Không giới hạn SL
                                            @endif
                                        </div>
                                    </td>

                                    <td><span class="spp-chip amber js-rule-calc">{{ $calcLabel[$calc] ?? '%' }}</span></td>
                                    <td><div class="spp-name js-rule-value">{{ $valueText }}</div></td>
                                    <td><div class="spp-name js-rule-priority">{{ $rule->priority ?? 10 }}</div></td>
                                    <td><span class="spp-chip {{ $isActive ? 'green' : 'gray' }} js-rule-status">{{ $isActive ? 'Đang bật' : 'Tắt' }}</span></td>
                                    <td>
                                        <div class="d-flex gap-2">
                                            <button type="button" class="spp-btn spp-btn-dark js-edit-rule">Sửa</button>
                                            <button type="button" class="spp-btn spp-btn-soft js-clone-rule">Nhân bản</button>
                                            <button type="button" class="spp-btn spp-btn-red js-delete-rule">Xóa</button>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="9">
                                        <div class="spp-empty">Chưa có rule hoa hồng. Bấm “+ Lead SP 0.5%” hoặc “+ Tấm pin” để thêm.</div>
                                    </td>
                                </tr>
                            @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="spp-section" id="salarySection">
                    <div class="spp-head">
                        <div>
                            <h2 class="spp-title">3. Lương cứng Sales</h2>
                            <div class="spp-sub">Chỉ lấy nhân viên có role Sales. Set lương cứng, target doanh số, target hoa hồng theo tháng.</div>
                        </div>
                    </div>

                    <div class="spp-table-wrap">
                        <table class="spp-table" style="min-width:900px;">
                            <thead>
                            <tr>
                                <th>Nhân viên</th>
                                <th>Lương cứng</th>
                                <th>Target doanh số</th>
                                <th>Target hoa hồng</th>
                                <th>Trạng thái</th>
                                <th style="width:130px;">Thao tác</th>
                            </tr>
                            </thead>

                            <tbody id="employeeList">
                            @forelse($salesUsers as $u)
                                @php
                                    $salary = $salarySettings->get((int)$u->id);
                                    $baseSalary = (float)($salary->base_salary ?? 7000000);
                                    $targetRevenue = (float)($salary->target_revenue ?? 500000000);
                                    $targetCommission = (float)($salary->target_commission ?? 0);
                                    $isActive = !empty($salary->is_active);
                                @endphp

                                <tr class="js-employee-row" data-sales-id="{{ $u->id }}">
                                    <td>
                                        <div class="spp-hidden">
                                            <input data-field="sales_id" name="salary_settings[{{ $u->id }}][sales_id]" value="{{ $u->id }}">
                                            <input data-field="base_salary" name="salary_settings[{{ $u->id }}][base_salary]" value="{{ $baseSalary }}">
                                            <input data-field="target_revenue" name="salary_settings[{{ $u->id }}][target_revenue]" value="{{ $targetRevenue }}">
                                            <input data-field="target_commission" name="salary_settings[{{ $u->id }}][target_commission]" value="{{ $targetCommission }}">
                                            <input data-field="is_active" name="salary_settings[{{ $u->id }}][is_active]" value="{{ $isActive ? 1 : 0 }}">
                                            <input data-field="note" name="salary_settings[{{ $u->id }}][note]" value="{{ $salary->note ?? '' }}">
                                        </div>

                                        <div class="spp-name js-employee-name">{{ $u->name }}</div>
                                        <div class="spp-meta">{{ $u->email ?? '' }}</div>
                                    </td>

                                    <td><div class="spp-name js-base-salary">{{ $fmtMoney($baseSalary) }}</div></td>
                                    <td><div class="spp-name js-target-revenue">{{ $fmtMoney($targetRevenue) }}</div></td>
                                    <td><div class="spp-name js-target-commission">{{ $fmtMoney($targetCommission) }}</div></td>
                                    <td><span class="spp-chip {{ $isActive ? 'green' : 'gray' }} js-employee-status">{{ $isActive ? 'Đang tính' : 'Tắt' }}</span></td>
                                    <td><button type="button" class="spp-btn spp-btn-dark js-edit-employee">Sửa</button></td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6">
                                        <div class="spp-empty">Chưa tìm thấy nhân viên có role Sales.</div>
                                    </td>
                                </tr>
                            @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="spp-section" id="kpiSection">
                    <div class="spp-head">
                        <div>
                            <h2 class="spp-title">4. Bậc KPI doanh số</h2>
                            <div class="spp-sub">Ví dụ: đạt 500 triệu thì thưởng thêm tiền cố định, % doanh số, % hoa hồng hoặc % lương cứng.</div>
                        </div>

                        <button type="button" class="spp-btn spp-btn-main js-add-kpi">+ Thêm bậc KPI</button>
                    </div>

                    <div class="spp-table-wrap">
                        <table class="spp-table" style="min-width:980px;">
                            <thead>
                            <tr>
                                <th>Bậc KPI</th>
                                <th>Áp dụng Sales</th>
                                <th>Mốc doanh thu</th>
                                <th>Kiểu thưởng</th>
                                <th>Giá trị thưởng</th>
                                <th>Trạng thái</th>
                                <th style="width:180px;">Thao tác</th>
                            </tr>
                            </thead>

                            <tbody id="kpiList">
                            @forelse($kpiTiers as $idx => $tier)
                                @php
                                    $isActive = !empty($tier->is_active);
                                    $salesName = !empty($tier->sales_id) ? ($salesNameMap[(int)$tier->sales_id] ?? ('Sales #' . $tier->sales_id)) : 'Tất cả Sales';

                                    if (!empty($tier->to_revenue)) {
                                        $range = $fmtMoney($tier->from_revenue ?? 0) . ' - ' . $fmtMoney($tier->to_revenue);
                                    } else {
                                        $range = 'Từ ' . $fmtMoney($tier->from_revenue ?? 0);
                                    }

                                    $bonusValue = ($tier->bonus_type ?? 'fixed') === 'fixed'
                                        ? $fmtMoney($tier->bonus_amount ?? 0)
                                        : $fmtPercent($tier->bonus_amount ?? 0);
                                @endphp

                                <tr class="js-kpi-row" data-kpi-index="{{ $idx }}">
                                    <td>
                                        <div class="spp-hidden">
                                            <input data-field="sales_id" name="kpi_tiers[{{ $idx }}][sales_id]" value="{{ $tier->sales_id ?? '' }}">
                                            <input data-field="tier_name" name="kpi_tiers[{{ $idx }}][tier_name]" value="{{ $tier->tier_name ?? '' }}">
                                            <input data-field="from_revenue" name="kpi_tiers[{{ $idx }}][from_revenue]" value="{{ $tier->from_revenue ?? 0 }}">
                                            <input data-field="to_revenue" name="kpi_tiers[{{ $idx }}][to_revenue]" value="{{ $tier->to_revenue ?? '' }}">
                                            <input data-field="bonus_type" name="kpi_tiers[{{ $idx }}][bonus_type]" value="{{ $tier->bonus_type ?? 'fixed' }}">
                                            <input data-field="bonus_amount" name="kpi_tiers[{{ $idx }}][bonus_amount]" value="{{ $tier->bonus_amount ?? 0 }}">
                                            <input data-field="priority" name="kpi_tiers[{{ $idx }}][priority]" value="{{ $tier->priority ?? 10 }}">
                                            <input data-field="is_active" name="kpi_tiers[{{ $idx }}][is_active]" value="{{ $isActive ? 1 : 0 }}">
                                            <input data-field="note" name="kpi_tiers[{{ $idx }}][note]" value="{{ $tier->note ?? '' }}">
                                        </div>

                                        <div class="spp-name js-kpi-name">{{ $tier->tier_name ?? 'Bậc KPI' }}</div>
                                        <div class="spp-meta js-kpi-note">{{ $tier->note ?? '' }}</div>
                                    </td>

                                    <td><span class="spp-chip js-kpi-sales">{{ $salesName }}</span></td>
                                    <td><div class="spp-name js-kpi-range">{{ $range }}</div></td>
                                    <td><span class="spp-chip amber js-kpi-type">{{ $bonusTypeLabel[$tier->bonus_type ?? 'fixed'] ?? 'Thưởng cố định' }}</span></td>
                                    <td><div class="spp-name js-kpi-bonus">{{ $bonusValue }}</div></td>
                                    <td><span class="spp-chip {{ $isActive ? 'green' : 'gray' }} js-kpi-status">{{ $isActive ? 'Đang bật' : 'Tắt' }}</span></td>
                                    <td>
                                        <div class="d-flex gap-2">
                                            <button type="button" class="spp-btn spp-btn-dark js-edit-kpi">Sửa</button>
                                            <button type="button" class="spp-btn spp-btn-red js-delete-kpi">Xóa</button>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7">
                                        <div class="spp-empty">Chưa có bậc KPI. Bấm “+ Thêm bậc KPI” để tạo mốc doanh thu.</div>
                                    </td>
                                </tr>
                            @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="spp-side">
                <div class="spp-side-card">
                    <div class="inner">
                        <h3>Luồng tính hoa hồng</h3>
                        <p>Rule phải dễ hiểu để Sales biết vì sao được hoặc chưa được nhận hoa hồng.</p>

                        <div class="spp-help-list">
                            <div class="spp-help-item"><b>1. Trạng thái khách</b>Lead / Member / Khách lẻ có thể hưởng mức khác nhau.</div>
                            <div class="spp-help-item"><b>2. Mốc doanh thu</b>Ví dụ đạt từ 500tr mới kích hoạt hoa hồng.</div>
                            <div class="spp-help-item"><b>3. Nhóm hoa hồng</b>Công trình 3-5%, thương mại 0.3-2%, tấm pin theo model.</div>
                            <div class="spp-help-item"><b>4. Lương & KPI</b>Lương cứng riêng, KPI doanh số riêng, hoa hồng đơn hàng riêng.</div>
                        </div>
                    </div>
                </div>

                <div class="spp-section">
                    <div class="spp-head">
                        <div>
                            <h2 class="spp-title">Tạo nhanh</h2>
                            <div class="spp-sub">Các rule thường dùng.</div>
                        </div>
                    </div>

                    <div class="p-3 d-grid gap-2">
                        <button type="button" class="spp-btn spp-btn-soft js-add-rule" data-type="lead_trade">+ Lead sản phẩm 0.5%</button>
                        <button type="button" class="spp-btn spp-btn-soft js-add-rule" data-type="lead_project">+ Lead công trình 3%</button>
                        <button type="button" class="spp-btn spp-btn-soft js-add-rule" data-type="trade_product">+ Thương mại 1%</button>
                        <button type="button" class="spp-btn spp-btn-main js-add-rule" data-type="solar_panel">+ Tấm pin 15.000đ</button>
                        <button type="button" class="spp-btn spp-btn-dark js-add-kpi">+ KPI đạt 500tr</button>
                    </div>
                </div>
            </div>
        </div>

        <div class="spp-save-bar">
            <a href="{{ route('sales.commissions.index', ['month' => $month]) }}" class="spp-btn spp-btn-soft">Quay lại</a>
            <button type="submit" class="spp-btn spp-btn-main">Lưu toàn bộ chính sách</button>
        </div>
    </form>
</div>

{{-- Modal chính sách chung --}}
<div class="modal fade spp-modal" id="policyModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Sửa chính sách chung</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>

            <div class="modal-body">
                <div class="spp-form-grid">
                    <div class="spp-form-section">
                        <div class="spp-form-section-title">Thông tin tháng</div>
                        <div class="spp-form-grid">
                            <div class="spp-field spp-span-3"><label>Tháng</label><input type="month" id="modal_period_month" value="{{ $policy->period_month ?? $month }}"></div>
                            <div class="spp-field spp-span-6"><label>Tên chính sách</label><input type="text" id="modal_policy_name" value="{{ $policy->name ?? '' }}"></div>
                            <div class="spp-field spp-span-3">
                                <label>Trạng thái</label>
                                <select id="modal_policy_status">
                                    <option value="active" {{ ($policy->status ?? '') === 'active' ? 'selected' : '' }}>Đang áp dụng</option>
                                    <option value="draft" {{ ($policy->status ?? '') === 'draft' ? 'selected' : '' }}>Nháp</option>
                                    <option value="locked" {{ ($policy->status ?? '') === 'locked' ? 'selected' : '' }}>Khóa</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="spp-form-section">
                        <div class="spp-form-section-title">Mức mặc định</div>
                        <div class="spp-form-grid">
                            <div class="spp-field spp-span-4"><label>Công trình mặc định (%)</label><input type="number" step="0.01" id="modal_project_rate" value="{{ $policy->project_rate_percent ?? 4 }}"></div>
                            <div class="spp-field spp-span-4"><label>Thương mại mặc định (%)</label><input type="number" step="0.01" id="modal_trade_rate" value="{{ $policy->trade_rate_percent ?? 1 }}"></div>
                            <div class="spp-field spp-span-4"><label>Tấm pin mặc định / tấm</label><input type="number" step="1" id="modal_panel_amount" value="{{ $policy->panel_fixed_amount ?? 15000 }}"></div>
                        </div>
                    </div>

                    <div class="spp-form-section">
                        <div class="spp-form-section-title">Điều kiện tính hoa hồng chung</div>
                        <div class="spp-checks">
                            <label><input type="checkbox" id="modal_only_paid" {{ !empty($policy->only_paid) ? 'checked' : '' }}> Chỉ tính khi đã thanh toán</label>
                            <label><input type="checkbox" id="modal_only_shipped" {{ !empty($policy->only_shipped) ? 'checked' : '' }}> Chỉ tính khi đã xuất kho</label>
                            <label><input type="checkbox" id="modal_only_completed" {{ !empty($policy->only_completed) ? 'checked' : '' }}> Chỉ tính khi hoàn tất</label>
                            <label><input type="checkbox" id="modal_hold_debt" {{ !empty($policy->hold_if_debt) ? 'checked' : '' }}> Giữ hoa hồng nếu còn công nợ</label>
                            <label><input type="checkbox" id="modal_policy_active" {{ !empty($policy->is_active) ? 'checked' : '' }}> Bật chính sách</label>
                        </div>
                    </div>

                    <div class="spp-field spp-span-12"><label>Ghi chú</label><textarea id="modal_policy_note">{{ $policy->note ?? '' }}</textarea></div>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="spp-btn spp-btn-soft" data-bs-dismiss="modal">Đóng</button>
                <button type="button" class="spp-btn spp-btn-main" id="savePolicyModal">Lưu vào giao diện</button>
            </div>
        </div>
    </div>
</div>

{{-- Modal rule hoa hồng --}}
<div class="modal fade spp-modal" id="ruleModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Sửa rule hoa hồng</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>

            <div class="modal-body">
                <div class="spp-form-grid">
                    <div class="spp-form-section">
                        <div class="spp-form-section-title">1. Rule này áp dụng cho ai?</div>
                        <div class="spp-form-grid">
                            <div class="spp-field spp-span-3">
                                <label>Nhóm hoa hồng</label>
                                <select id="rule_commission_type">
                                    @foreach($typeLabel as $key => $label)
                                        <option value="{{ $key }}">{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="spp-field spp-span-3">
                                <label>Trạng thái khách</label>
                                <select id="rule_customer_status">
                                    @foreach($customerStatusLabel as $key => $label)
                                        <option value="{{ $key }}">{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="spp-field spp-span-3">
                                <label>Áp dụng cho</label>
                                <select id="rule_apply_type">
                                    <option value="all">Tất cả</option>
                                    <option value="keyword">Từ khóa / model</option>
                                    <option value="product">Sản phẩm ID</option>
                                    <option value="category">Danh mục ID</option>
                                    <option value="brand">Thương hiệu ID</option>
                                </select>
                            </div>

                            <div class="spp-field spp-span-3">
                                <label>Target</label>
                                <input type="text" id="rule_apply_text" placeholder="VD: JA Solar / #123">
                            </div>
                        </div>
                    </div>

                    <div class="spp-form-section">
                        <div class="spp-form-section-title">2. Điều kiện để được hoa hồng</div>
                        <div class="spp-form-grid">
                            <div class="spp-field spp-span-3"><label>Doanh thu từ</label><input type="number" step="1" id="rule_from_amount" placeholder="VD: 500000000"></div>
                            <div class="spp-field spp-span-3"><label>Doanh thu đến</label><input type="number" step="1" id="rule_to_amount" placeholder="Bỏ trống nếu không giới hạn"></div>
                            <div class="spp-field spp-span-3"><label>Số lượng từ</label><input type="number" step="1" id="rule_from_qty"></div>
                            <div class="spp-field spp-span-3"><label>Số lượng đến</label><input type="number" step="1" id="rule_to_qty"></div>
                        </div>
                    </div>

                    <div class="spp-form-section">
                        <div class="spp-form-section-title">3. Cách tính hoa hồng</div>
                        <div class="spp-form-grid">
                            <div class="spp-field spp-span-3">
                                <label>Nền tính</label>
                                <select id="rule_base_type">
                                    @foreach($baseLabel as $key => $label)
                                        <option value="{{ $key }}">{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="spp-field spp-span-3">
                                <label>Cách tính</label>
                                <select id="rule_calculation_type">
                                    @foreach($calcLabel as $key => $label)
                                        <option value="{{ $key }}">{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="spp-field spp-span-2"><label>%</label><input type="number" step="0.01" id="rule_rate_percent"></div>
                            <div class="spp-field spp-span-2"><label>Tiền/SP</label><input type="number" step="1" id="rule_fixed_amount"></div>
                            <div class="spp-field spp-span-2"><label>Tiền/kWp</label><input type="number" step="1" id="rule_amount_per_kwp"></div>
                        </div>
                    </div>

                    <div class="spp-form-section">
                        <div class="spp-form-section-title">4. Quản trị</div>
                        <div class="spp-form-grid">
                            <div class="spp-field spp-span-2"><label>Ưu tiên</label><input type="number" id="rule_priority"></div>
                            <div class="spp-field spp-span-2">
                                <label>Trạng thái</label>
                                <select id="rule_is_active">
                                    <option value="1">Bật</option>
                                    <option value="0">Tắt</option>
                                </select>
                            </div>
                            <div class="spp-field spp-span-8"><label>Ghi chú</label><input type="text" id="rule_note" placeholder="VD: Lead ADS sản phẩm đạt 500tr mới nhận 0.5%"></div>
                        </div>
                    </div>

                    <div class="alert alert-info spp-span-12 mb-0">
                        <strong>Lưu ý:</strong> Nếu chọn “Trạng thái khách” khác “Tất cả”, hệ thống sẽ ưu tiên rule theo trạng thái khách như Lead / Member / Khách lẻ. Nếu muốn rule theo model sản phẩm như JA Solar thì để trạng thái khách là “Tất cả khách”, rồi chọn “Từ khóa / model”.
                    </div>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="spp-btn spp-btn-soft" data-bs-dismiss="modal">Đóng</button>
                <button type="button" class="spp-btn spp-btn-main" id="saveRuleModal">Lưu rule</button>
            </div>
        </div>
    </div>
</div>

{{-- Modal lương sales --}}
<div class="modal fade spp-modal" id="employeeModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Sửa lương cứng & target Sales</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>

            <div class="modal-body">
                <div class="spp-form-grid">
                    <div class="spp-field spp-span-12"><label>Nhân viên</label><input type="text" id="employee_name" disabled></div>
                    <div class="spp-field spp-span-4"><label>Lương cứng</label><input type="number" step="1" id="employee_base_salary"></div>
                    <div class="spp-field spp-span-4"><label>Target doanh số</label><input type="number" step="1" id="employee_target_revenue"></div>
                    <div class="spp-field spp-span-4"><label>Target hoa hồng</label><input type="number" step="1" id="employee_target_commission"></div>
                    <div class="spp-field spp-span-3">
                        <label>Trạng thái</label>
                        <select id="employee_is_active">
                            <option value="1">Đang tính</option>
                            <option value="0">Tắt</option>
                        </select>
                    </div>
                    <div class="spp-field spp-span-9"><label>Ghi chú</label><input type="text" id="employee_note"></div>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="spp-btn spp-btn-soft" data-bs-dismiss="modal">Đóng</button>
                <button type="button" class="spp-btn spp-btn-main" id="saveEmployeeModal">Lưu nhân viên</button>
            </div>
        </div>
    </div>
</div>

{{-- Modal KPI --}}
<div class="modal fade spp-modal" id="kpiModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Sửa bậc KPI doanh số</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>

            <div class="modal-body">
                <div class="spp-form-grid">
                    <div class="spp-field spp-span-6"><label>Tên bậc</label><input type="text" id="kpi_tier_name" placeholder="VD: Bậc 1 - Đạt 500 triệu"></div>
                    <div class="spp-field spp-span-6">
                        <label>Áp dụng cho Sales</label>
                        <select id="kpi_sales_id">
                            <option value="">Tất cả Sales</option>
                            @foreach($salesUsers as $u)
                                <option value="{{ $u->id }}">{{ $u->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="spp-field spp-span-4"><label>Từ doanh số</label><input type="number" step="1" id="kpi_from_revenue"></div>
                    <div class="spp-field spp-span-4"><label>Đến doanh số</label><input type="number" step="1" id="kpi_to_revenue" placeholder="Bỏ trống nếu trở lên"></div>
                    <div class="spp-field spp-span-4">
                        <label>Kiểu thưởng</label>
                        <select id="kpi_bonus_type">
                            <option value="fixed">Thưởng cố định</option>
                            <option value="percent_revenue">% doanh số</option>
                            <option value="percent_commission">% hoa hồng</option>
                            <option value="salary_percent">% lương cứng</option>
                        </select>
                    </div>

                    <div class="spp-field spp-span-4"><label>Giá trị thưởng</label><input type="number" step="0.01" id="kpi_bonus_amount"></div>
                    <div class="spp-field spp-span-4"><label>Ưu tiên</label><input type="number" id="kpi_priority"></div>
                    <div class="spp-field spp-span-4">
                        <label>Trạng thái</label>
                        <select id="kpi_is_active">
                            <option value="1">Bật</option>
                            <option value="0">Tắt</option>
                        </select>
                    </div>

                    <div class="spp-field spp-span-12"><label>Ghi chú</label><textarea id="kpi_note"></textarea></div>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="spp-btn spp-btn-soft" data-bs-dismiss="modal">Đóng</button>
                <button type="button" class="spp-btn spp-btn-main" id="saveKpiModal">Lưu KPI</button>
            </div>
        </div>
    </div>
</div>

<template id="ruleRowTemplate">
    <tr class="js-rule-row" data-rule-index="">
        <td>
            <div class="spp-hidden">
                <input data-field="commission_type" value="trade_product">
                <input data-field="target_type" value="all">
                <input data-field="target_text" value="">
                <input data-field="target_id" value="">
                <input data-field="base_type" value="revenue_before_vat">
                <input data-field="calculation_type" value="percent">
                <input data-field="rate_percent" value="1">
                <input data-field="fixed_amount" value="0">
                <input data-field="amount_per_unit" value="0">
                <input data-field="amount_per_kwp" value="0">
                <input data-field="from_amount" value="">
                <input data-field="to_amount" value="">
                <input data-field="from_qty" value="">
                <input data-field="to_qty" value="">
                <input data-field="priority" value="20">
                <input data-field="is_active" value="1">
                <input data-field="note" value="">
            </div>
            <span class="spp-chip js-rule-type">Thương mại</span>
        </td>
        <td><div class="spp-name js-rule-customer">Tất cả khách</div><div class="spp-meta">Điều kiện khách hàng</div></td>
        <td><div class="spp-name js-rule-apply">Tất cả</div><div class="spp-meta js-rule-base">Doanh thu trước VAT</div></td>
        <td><div class="spp-name js-rule-revenue">Không giới hạn</div><div class="spp-meta js-rule-qty">Không giới hạn SL</div></td>
        <td><span class="spp-chip amber js-rule-calc">% doanh thu</span></td>
        <td><div class="spp-name js-rule-value">1%</div></td>
        <td><div class="spp-name js-rule-priority">20</div></td>
        <td><span class="spp-chip green js-rule-status">Đang bật</span></td>
        <td>
            <div class="d-flex gap-2">
                <button type="button" class="spp-btn spp-btn-dark js-edit-rule">Sửa</button>
                <button type="button" class="spp-btn spp-btn-soft js-clone-rule">Nhân bản</button>
                <button type="button" class="spp-btn spp-btn-red js-delete-rule">Xóa</button>
            </div>
        </td>
    </tr>
</template>

<template id="kpiRowTemplate">
    <tr class="js-kpi-row" data-kpi-index="">
        <td>
            <div class="spp-hidden">
                <input data-field="sales_id" value="">
                <input data-field="tier_name" value="Bậc 1 - Đạt 500 triệu">
                <input data-field="from_revenue" value="500000000">
                <input data-field="to_revenue" value="">
                <input data-field="bonus_type" value="fixed">
                <input data-field="bonus_amount" value="1000000">
                <input data-field="priority" value="50">
                <input data-field="is_active" value="1">
                <input data-field="note" value="Đạt 500 triệu doanh số thì thưởng KPI">
            </div>
            <div class="spp-name js-kpi-name">Bậc 1 - Đạt 500 triệu</div>
            <div class="spp-meta js-kpi-note">Đạt 500 triệu doanh số thì thưởng KPI</div>
        </td>
        <td><span class="spp-chip js-kpi-sales">Tất cả Sales</span></td>
        <td><div class="spp-name js-kpi-range">Từ 500.000.000 đ</div></td>
        <td><span class="spp-chip amber js-kpi-type">Thưởng cố định</span></td>
        <td><div class="spp-name js-kpi-bonus">1.000.000 đ</div></td>
        <td><span class="spp-chip green js-kpi-status">Đang bật</span></td>
        <td>
            <div class="d-flex gap-2">
                <button type="button" class="spp-btn spp-btn-dark js-edit-kpi">Sửa</button>
                <button type="button" class="spp-btn spp-btn-red js-delete-kpi">Xóa</button>
            </div>
        </td>
    </tr>
</template>

<script>
(function(){
    const salesNameMap = @json($salesNameMap);

    const typeLabel = {
        project:'Công trình',
        trade_product:'Thương mại',
        solar_panel:'Tấm pin'
    };

    const customerStatusLabel = {
        all:'Tất cả khách',
        lead:'Lead / Khách ADS',
        member:'Member / Tự phát triển',
        retail:'Khách lẻ'
    };

    const targetLabel = {
        all:'Tất cả',
        customer_status:'Trạng thái khách',
        product:'Sản phẩm',
        category:'Danh mục',
        brand:'Thương hiệu',
        keyword:'Từ khóa / model'
    };

    const baseLabel = {
        revenue_before_vat:'Doanh thu trước VAT',
        revenue_after_vat:'Doanh thu sau VAT',
        gross_profit:'Lợi nhuận gộp',
        quantity:'Số lượng',
        kwp:'kWp'
    };

    const calcLabel = {
        percent:'% doanh thu',
        fixed_per_item:'Tiền / sản phẩm',
        fixed_per_kwp:'Tiền / kWp',
        fixed_per_order:'Cố định / đơn'
    };

    const bonusTypeLabel = {
        fixed:'Thưởng cố định',
        percent_revenue:'% doanh số',
        percent_commission:'% hoa hồng',
        salary_percent:'% lương cứng'
    };

    const money = value => Math.round(Number(value || 0)).toLocaleString('vi-VN') + ' đ';
    const percent = value => Number(value || 0).toLocaleString('vi-VN', {maximumFractionDigits: 2}) + '%';

    const getField = (row, field) => row.querySelector(`[data-field="${field}"]`)?.value ?? '';
    const setField = (row, field, value) => {
        const el = row.querySelector(`[data-field="${field}"]`);
        if(el) el.value = value ?? '';
    };

    let activeRuleRow = null;
    let activeEmployeeRow = null;
    let activeKpiRow = null;

    function revenueRange(from, to){
        if(from && to) return money(from) + ' - ' + money(to);
        if(from) return 'Từ ' + money(from);
        if(to) return 'Đến ' + money(to);
        return 'Không giới hạn';
    }

    function qtyRange(from, to){
        if(from || to) return 'SL: ' + (from || 0) + ' - ' + (to || '∞');
        return 'Không giới hạn SL';
    }

    function ruleValue(row){
        const calc = getField(row, 'calculation_type') || 'percent';
        if(calc === 'fixed_per_item') return money(getField(row, 'fixed_amount')) + '/SP';
        if(calc === 'fixed_per_kwp') return money(getField(row, 'amount_per_kwp')) + '/kWp';
        if(calc === 'fixed_per_order') return money(getField(row, 'fixed_amount')) + '/đơn';
        return percent(getField(row, 'rate_percent'));
    }

    function updateStats(){
        let activeRules = 0;
        document.querySelectorAll('.js-rule-row').forEach(row => {
            if(getField(row, 'is_active') === '1') activeRules++;
        });

        let salary = 0;
        let target = 0;
        document.querySelectorAll('.js-employee-row').forEach(row => {
            if(getField(row, 'is_active') === '1'){
                salary += Number(getField(row, 'base_salary') || 0);
                target += Number(getField(row, 'target_revenue') || 0);
            }
        });

        document.getElementById('activeRuleCount').textContent = activeRules;
        document.getElementById('totalBaseSalaryText').textContent = money(salary);
        document.getElementById('totalTargetRevenueText').textContent = money(target);
        document.getElementById('kpiCountText').textContent = document.querySelectorAll('.js-kpi-row').length;
    }

    function refreshRuleRow(row){
        const type = getField(row, 'commission_type') || 'trade_product';
        const targetType = getField(row, 'target_type') || 'all';
        const targetText = getField(row, 'target_text') || '';
        const active = getField(row, 'is_active') === '1';

        let customer = 'all';
        let apply = targetLabel[targetType] || targetType;

        if(targetType === 'customer_status'){
            customer = targetText || 'all';
            apply = 'Theo trạng thái khách';
        } else if(targetText) {
            apply += ': ' + targetText;
        }

        row.querySelector('.js-rule-type').textContent = typeLabel[type] || type;
        row.querySelector('.js-rule-customer').textContent = customerStatusLabel[customer] || customer;
        row.querySelector('.js-rule-apply').textContent = apply;
        row.querySelector('.js-rule-base').textContent = baseLabel[getField(row, 'base_type')] || 'Doanh thu trước VAT';
        row.querySelector('.js-rule-revenue').textContent = revenueRange(getField(row, 'from_amount'), getField(row, 'to_amount'));
        row.querySelector('.js-rule-qty').textContent = qtyRange(getField(row, 'from_qty'), getField(row, 'to_qty'));
        row.querySelector('.js-rule-calc').textContent = calcLabel[getField(row, 'calculation_type')] || '% doanh thu';
        row.querySelector('.js-rule-value').textContent = ruleValue(row);
        row.querySelector('.js-rule-priority').textContent = getField(row, 'priority') || '10';
        row.querySelector('.js-rule-status').textContent = active ? 'Đang bật' : 'Tắt';
        row.querySelector('.js-rule-status').classList.toggle('green', active);
        row.querySelector('.js-rule-status').classList.toggle('gray', !active);

        updateStats();
    }

    function refreshEmployeeRow(row){
        const active = getField(row, 'is_active') === '1';
        row.querySelector('.js-base-salary').textContent = money(getField(row, 'base_salary'));
        row.querySelector('.js-target-revenue').textContent = money(getField(row, 'target_revenue'));
        row.querySelector('.js-target-commission').textContent = money(getField(row, 'target_commission'));
        row.querySelector('.js-employee-status').textContent = active ? 'Đang tính' : 'Tắt';
        row.querySelector('.js-employee-status').classList.toggle('green', active);
        row.querySelector('.js-employee-status').classList.toggle('gray', !active);

        updateStats();
    }

    function refreshKpiRow(row){
        const active = getField(row, 'is_active') === '1';
        const salesId = getField(row, 'sales_id');
        const bonusType = getField(row, 'bonus_type') || 'fixed';
        const bonusAmount = getField(row, 'bonus_amount');

        row.querySelector('.js-kpi-name').textContent = getField(row, 'tier_name') || 'Bậc KPI';
        row.querySelector('.js-kpi-note').textContent = getField(row, 'note') || '';
        row.querySelector('.js-kpi-sales').textContent = salesId ? (salesNameMap[salesId] || ('Sales #' + salesId)) : 'Tất cả Sales';
        row.querySelector('.js-kpi-range').textContent = revenueRange(getField(row, 'from_revenue'), getField(row, 'to_revenue'));
        row.querySelector('.js-kpi-type').textContent = bonusTypeLabel[bonusType] || 'Thưởng cố định';
        row.querySelector('.js-kpi-bonus').textContent = bonusType === 'fixed' ? money(bonusAmount) : percent(bonusAmount);
        row.querySelector('.js-kpi-status').textContent = active ? 'Đang bật' : 'Tắt';
        row.querySelector('.js-kpi-status').classList.toggle('green', active);
        row.querySelector('.js-kpi-status').classList.toggle('gray', !active);

        updateStats();
    }

    function reindexRules(){
        document.querySelectorAll('.js-rule-row').forEach((row, index) => {
            row.dataset.ruleIndex = index;
            row.querySelectorAll('[data-field]').forEach(input => {
                input.name = `rules[${index}][${input.dataset.field}]`;
            });
        });
    }

    function reindexKpi(){
        document.querySelectorAll('.js-kpi-row').forEach((row, index) => {
            row.dataset.kpiIndex = index;
            row.querySelectorAll('[data-field]').forEach(input => {
                input.name = `kpi_tiers[${index}][${input.dataset.field}]`;
            });
        });
    }

    function openRuleModal(row){
        activeRuleRow = row;

        const targetType = getField(row, 'target_type');
        const targetText = getField(row, 'target_text');

        let customerStatus = 'all';
        let applyType = targetType || 'all';
        let applyText = targetText || '';

        if(targetType === 'customer_status'){
            customerStatus = targetText || 'lead';
            applyType = 'all';
            applyText = '';
        }

        document.getElementById('rule_commission_type').value = getField(row, 'commission_type') || 'trade_product';
        document.getElementById('rule_customer_status').value = customerStatus;
        document.getElementById('rule_apply_type').value = applyType;
        document.getElementById('rule_apply_text').value = applyText;
        document.getElementById('rule_base_type').value = getField(row, 'base_type') || 'revenue_before_vat';
        document.getElementById('rule_calculation_type').value = getField(row, 'calculation_type') || 'percent';
        document.getElementById('rule_rate_percent').value = getField(row, 'rate_percent');
        document.getElementById('rule_fixed_amount').value = getField(row, 'fixed_amount');
        document.getElementById('rule_amount_per_kwp').value = getField(row, 'amount_per_kwp');
        document.getElementById('rule_from_amount').value = getField(row, 'from_amount');
        document.getElementById('rule_to_amount').value = getField(row, 'to_amount');
        document.getElementById('rule_from_qty').value = getField(row, 'from_qty');
        document.getElementById('rule_to_qty').value = getField(row, 'to_qty');
        document.getElementById('rule_priority').value = getField(row, 'priority') || 10;
        document.getElementById('rule_is_active').value = getField(row, 'is_active') || 1;
        document.getElementById('rule_note').value = getField(row, 'note') || '';

        bootstrap.Modal.getOrCreateInstance(document.getElementById('ruleModal')).show();
    }

    function createRule(type){
        const template = document.getElementById('ruleRowTemplate').content.cloneNode(true);
        const row = template.querySelector('.js-rule-row');

        if(type === 'lead_trade'){
            setField(row, 'commission_type', 'trade_product');
            setField(row, 'target_type', 'customer_status');
            setField(row, 'target_text', 'lead');
            setField(row, 'base_type', 'revenue_before_vat');
            setField(row, 'calculation_type', 'percent');
            setField(row, 'rate_percent', '0.5');
            setField(row, 'fixed_amount', '0');
            setField(row, 'amount_per_unit', '0');
            setField(row, 'from_amount', '500000000');
            setField(row, 'priority', '90');
            setField(row, 'note', 'Lead / Khách ADS: sản phẩm đạt doanh thu tối thiểu mới nhận 0.5%');
        }

        if(type === 'lead_project'){
            setField(row, 'commission_type', 'project');
            setField(row, 'target_type', 'customer_status');
            setField(row, 'target_text', 'lead');
            setField(row, 'base_type', 'revenue_before_vat');
            setField(row, 'calculation_type', 'percent');
            setField(row, 'rate_percent', '3');
            setField(row, 'fixed_amount', '0');
            setField(row, 'amount_per_unit', '0');
            setField(row, 'from_amount', '500000000');
            setField(row, 'priority', '95');
            setField(row, 'note', 'Lead / Khách ADS: công trình đạt doanh thu tối thiểu mới nhận 3%');
        }

        if(type === 'trade_product'){
            setField(row, 'commission_type', 'trade_product');
            setField(row, 'target_type', 'all');
            setField(row, 'target_text', '');
            setField(row, 'base_type', 'revenue_before_vat');
            setField(row, 'calculation_type', 'percent');
            setField(row, 'rate_percent', '1');
            setField(row, 'from_amount', '500000000');
            setField(row, 'priority', '40');
            setField(row, 'note', 'Thương mại sản phẩm đạt doanh thu tối thiểu mới nhận 1%');
        }

        if(type === 'solar_panel'){
            setField(row, 'commission_type', 'solar_panel');
            setField(row, 'target_type', 'keyword');
            setField(row, 'target_text', 'JA Solar');
            setField(row, 'base_type', 'quantity');
            setField(row, 'calculation_type', 'fixed_per_item');
            setField(row, 'rate_percent', '0');
            setField(row, 'fixed_amount', '15000');
            setField(row, 'amount_per_unit', '15000');
            setField(row, 'priority', '100');
            setField(row, 'note', 'Tấm pin theo model tháng này');
        }

        const empty = document.querySelector('#ruleList .spp-empty');
        if(empty) empty.closest('tr')?.remove();

        document.getElementById('ruleList').prepend(row);
        reindexRules();
        refreshRuleRow(row);
        openRuleModal(row);
    }

    function cloneRule(row){
        const clone = row.cloneNode(true);
        row.after(clone);
        reindexRules();
        refreshRuleRow(clone);
        openRuleModal(clone);
    }

    function openEmployeeModal(row){
        activeEmployeeRow = row;
        document.getElementById('employee_name').value = row.querySelector('.js-employee-name')?.textContent.trim() || '';
        document.getElementById('employee_base_salary').value = getField(row, 'base_salary');
        document.getElementById('employee_target_revenue').value = getField(row, 'target_revenue');
        document.getElementById('employee_target_commission').value = getField(row, 'target_commission');
        document.getElementById('employee_is_active').value = getField(row, 'is_active');
        document.getElementById('employee_note').value = getField(row, 'note');

        bootstrap.Modal.getOrCreateInstance(document.getElementById('employeeModal')).show();
    }

    function openKpiModal(row){
        activeKpiRow = row;
        ['tier_name','sales_id','from_revenue','to_revenue','bonus_type','bonus_amount','priority','is_active','note'].forEach(field => {
            const el = document.getElementById('kpi_' + field);
            if(el) el.value = getField(row, field);
        });

        bootstrap.Modal.getOrCreateInstance(document.getElementById('kpiModal')).show();
    }

    function createKpi(){
        const template = document.getElementById('kpiRowTemplate').content.cloneNode(true);
        const row = template.querySelector('.js-kpi-row');

        const empty = document.querySelector('#kpiList .spp-empty');
        if(empty) empty.closest('tr')?.remove();

        document.getElementById('kpiList').prepend(row);
        reindexKpi();
        refreshKpiRow(row);
        openKpiModal(row);
    }

    document.addEventListener('click', function(e){
        const scrollBtn = e.target.closest('[data-scroll]');
        if(scrollBtn){
            document.querySelectorAll('.spp-nav button').forEach(btn => btn.classList.remove('active'));
            scrollBtn.classList.add('active');
            document.querySelector(scrollBtn.dataset.scroll)?.scrollIntoView({behavior:'smooth', block:'start'});
        }

        const addRule = e.target.closest('.js-add-rule');
        if(addRule) createRule(addRule.dataset.type || 'trade_product');

        const editRule = e.target.closest('.js-edit-rule');
        if(editRule) openRuleModal(editRule.closest('.js-rule-row'));

        const cloneRuleBtn = e.target.closest('.js-clone-rule');
        if(cloneRuleBtn) cloneRule(cloneRuleBtn.closest('.js-rule-row'));

        const deleteRule = e.target.closest('.js-delete-rule');
        if(deleteRule){
            deleteRule.closest('.js-rule-row')?.remove();
            reindexRules();
            updateStats();
        }

        const editEmployee = e.target.closest('.js-edit-employee');
        if(editEmployee) openEmployeeModal(editEmployee.closest('.js-employee-row'));

        const addKpi = e.target.closest('.js-add-kpi');
        if(addKpi) createKpi();

        const editKpi = e.target.closest('.js-edit-kpi');
        if(editKpi) openKpiModal(editKpi.closest('.js-kpi-row'));

        const deleteKpi = e.target.closest('.js-delete-kpi');
        if(deleteKpi){
            deleteKpi.closest('.js-kpi-row')?.remove();
            reindexKpi();
            updateStats();
        }
    });


    document.getElementById('pageMonthPicker')?.addEventListener('change', function(){
        const hiddenMonth = document.getElementById('policy_period_month');
        const modalMonth = document.getElementById('modal_period_month');

        if(hiddenMonth) hiddenMonth.value = this.value;
        if(modalMonth) modalMonth.value = this.value;
    });

    document.getElementById('savePolicyModal')?.addEventListener('click', function(){
        const period = document.getElementById('modal_period_month').value;
        const projectRate = document.getElementById('modal_project_rate').value || 0;
        const tradeRate = document.getElementById('modal_trade_rate').value || 0;
        const panelAmount = document.getElementById('modal_panel_amount').value || 0;

        document.getElementById('policy_period_month').value = period;
        document.getElementById('policy_name').value = document.getElementById('modal_policy_name').value;
        document.getElementById('policy_status').value = document.getElementById('modal_policy_status').value;
        document.getElementById('policy_project_rate_percent').value = projectRate;
        document.getElementById('policy_trade_rate_percent').value = tradeRate;
        document.getElementById('policy_panel_fixed_amount').value = panelAmount;
        document.getElementById('policy_is_active').value = document.getElementById('modal_policy_active').checked ? 1 : 0;
        document.getElementById('policy_note').value = document.getElementById('modal_policy_note').value;
        document.getElementById('policy_only_paid').value = document.getElementById('modal_only_paid').checked ? 1 : 0;
        document.getElementById('policy_only_shipped').value = document.getElementById('modal_only_shipped').checked ? 1 : 0;
        document.getElementById('policy_only_completed').value = document.getElementById('modal_only_completed').checked ? 1 : 0;
        document.getElementById('policy_hold_if_debt').value = document.getElementById('modal_hold_debt').checked ? 1 : 0;

        document.getElementById('policyMonthText').textContent = period;
        document.getElementById('policyProjectCard').textContent = percent(projectRate);
        document.getElementById('policyTradeCard').textContent = percent(tradeRate);
        document.getElementById('policyPanelCard').textContent = money(panelAmount);

        document.getElementById('pillOnlyPaid').classList.toggle('active', document.getElementById('modal_only_paid').checked);
        document.getElementById('pillOnlyShipped').classList.toggle('active', document.getElementById('modal_only_shipped').checked);
        document.getElementById('pillOnlyCompleted').classList.toggle('active', document.getElementById('modal_only_completed').checked);
        document.getElementById('pillHoldDebt').classList.toggle('active', document.getElementById('modal_hold_debt').checked);

        bootstrap.Modal.getOrCreateInstance(document.getElementById('policyModal')).hide();
    });

    document.getElementById('saveRuleModal')?.addEventListener('click', function(){
        if(!activeRuleRow) return;

        const customerStatus = document.getElementById('rule_customer_status').value;
        const applyType = document.getElementById('rule_apply_type').value;
        const applyText = document.getElementById('rule_apply_text').value;

        setField(activeRuleRow, 'commission_type', document.getElementById('rule_commission_type').value);
        setField(activeRuleRow, 'base_type', document.getElementById('rule_base_type').value);
        setField(activeRuleRow, 'calculation_type', document.getElementById('rule_calculation_type').value);
        setField(activeRuleRow, 'rate_percent', document.getElementById('rule_rate_percent').value);
        setField(activeRuleRow, 'fixed_amount', document.getElementById('rule_fixed_amount').value);
        setField(activeRuleRow, 'amount_per_unit', document.getElementById('rule_fixed_amount').value);
        setField(activeRuleRow, 'amount_per_kwp', document.getElementById('rule_amount_per_kwp').value);
        setField(activeRuleRow, 'from_amount', document.getElementById('rule_from_amount').value);
        setField(activeRuleRow, 'to_amount', document.getElementById('rule_to_amount').value);
        setField(activeRuleRow, 'from_qty', document.getElementById('rule_from_qty').value);
        setField(activeRuleRow, 'to_qty', document.getElementById('rule_to_qty').value);
        setField(activeRuleRow, 'priority', document.getElementById('rule_priority').value);
        setField(activeRuleRow, 'is_active', document.getElementById('rule_is_active').value);
        setField(activeRuleRow, 'note', document.getElementById('rule_note').value);

        if(customerStatus !== 'all'){
            setField(activeRuleRow, 'target_type', 'customer_status');
            setField(activeRuleRow, 'target_text', customerStatus);
            setField(activeRuleRow, 'target_id', '');
        } else {
            setField(activeRuleRow, 'target_type', applyType);
            setField(activeRuleRow, 'target_text', applyText);

            const m = (applyText || '').match(/^#?(\d+)$/);
            if(['product','category','brand'].includes(applyType) && m){
                setField(activeRuleRow, 'target_id', m[1]);
            } else {
                setField(activeRuleRow, 'target_id', '');
            }
        }

        refreshRuleRow(activeRuleRow);
        reindexRules();
        bootstrap.Modal.getOrCreateInstance(document.getElementById('ruleModal')).hide();
    });

    document.getElementById('saveEmployeeModal')?.addEventListener('click', function(){
        if(!activeEmployeeRow) return;

        setField(activeEmployeeRow, 'base_salary', document.getElementById('employee_base_salary').value);
        setField(activeEmployeeRow, 'target_revenue', document.getElementById('employee_target_revenue').value);
        setField(activeEmployeeRow, 'target_commission', document.getElementById('employee_target_commission').value);
        setField(activeEmployeeRow, 'is_active', document.getElementById('employee_is_active').value);
        setField(activeEmployeeRow, 'note', document.getElementById('employee_note').value);

        refreshEmployeeRow(activeEmployeeRow);
        bootstrap.Modal.getOrCreateInstance(document.getElementById('employeeModal')).hide();
    });

    document.getElementById('saveKpiModal')?.addEventListener('click', function(){
        if(!activeKpiRow) return;

        ['tier_name','sales_id','from_revenue','to_revenue','bonus_type','bonus_amount','priority','is_active','note'].forEach(field => {
            const el = document.getElementById('kpi_' + field);
            if(el) setField(activeKpiRow, field, el.value);
        });

        refreshKpiRow(activeKpiRow);
        reindexKpi();
        bootstrap.Modal.getOrCreateInstance(document.getElementById('kpiModal')).hide();
    });

    document.querySelectorAll('.js-rule-row').forEach(refreshRuleRow);
    document.querySelectorAll('.js-employee-row').forEach(refreshEmployeeRow);
    document.querySelectorAll('.js-kpi-row').forEach(refreshKpiRow);
    reindexRules();
    reindexKpi();
    updateStats();

    document.getElementById('commissionSettingsForm')?.addEventListener('submit', function(){
        reindexRules();
        reindexKpi();
    });
})();
</script>
@endsection
