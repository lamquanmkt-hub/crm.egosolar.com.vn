@extends('layouts.app')

@section('content')
@php
    $kpiPercent = (float)($payroll->total_kpi_percent ?? 0) * 100;
    $status = $payroll->status ?? 'draft';
@endphp

<style>
.payroll-show-page{
    --bg:#f5f7fb;
    --panel:#fff;
    --line:#e5edf7;
    --text:#0f172a;
    --muted:#64748b;
    --blue:#2563eb;
    --cyan:#06b6d4;
    --green:#16a34a;
    --amber:#f59e0b;
    --red:#dc2626;
    min-height:100vh;
    background:var(--bg);
    color:var(--text);
    font-size:12.5px;
    padding-bottom:42px;
}
.payroll-top{
    display:flex;
    align-items:center;
    justify-content:space-between;
    gap:14px;
    margin-bottom:14px;
}
.payroll-top h2{
    margin:0;
    font-size:24px;
    font-weight:950;
    letter-spacing:-.03em;
}
.payroll-top p{margin:3px 0 0;color:var(--muted)}
.btn-pill{
    min-height:38px;
    border-radius:999px;
    padding:0 14px;
    font-size:12.5px;
    font-weight:900;
    display:inline-flex;
    align-items:center;
    justify-content:center;
}
.hero-slip{
    position:relative;
    overflow:hidden;
    border-radius:28px;
    padding:24px;
    color:#fff;
    background:
        radial-gradient(circle at 82% 18%, rgba(34,211,238,.28), transparent 29%),
        radial-gradient(circle at 96% 80%, rgba(124,58,237,.22), transparent 28%),
        linear-gradient(135deg,#071225 0%,#0c3159 58%,#0f766e 100%);
    box-shadow:0 26px 74px rgba(15,23,42,.20);
    margin-bottom:14px;
}
.hero-slip:before{
    content:"";
    position:absolute;
    inset:0;
    background:
        linear-gradient(110deg, transparent 0%, rgba(255,255,255,.12) 24%, transparent 48%),
        repeating-linear-gradient(90deg, rgba(255,255,255,.035) 0 1px, transparent 1px 80px);
    transform:translateX(-75%);
    animation:shine 6s ease-in-out infinite;
}
.hero-slip > *{position:relative;z-index:2}
.hero-grid{
    display:grid;
    grid-template-columns:1.35fr .75fr;
    gap:20px;
    align-items:center;
}
.hero-chip{
    display:inline-flex;
    align-items:center;
    gap:8px;
    padding:7px 11px;
    border-radius:999px;
    background:rgba(255,255,255,.1);
    border:1px solid rgba(255,255,255,.12);
    color:#bae6fd;
    font-size:10.5px;
    font-weight:950;
    letter-spacing:.08em;
    margin-bottom:12px;
}
.hero-chip span{width:7px;height:7px;border-radius:999px;background:#22c55e;box-shadow:0 0 0 6px rgba(34,197,94,.13)}
.hero-slip h1{
    margin:0 0 8px;
    font-size:34px;
    line-height:1.05;
    font-weight:950;
    letter-spacing:-.04em;
}
.hero-slip p{margin:0;color:#cbd5e1;line-height:1.7}
.hero-total{
    padding:18px;
    border-radius:22px;
    background:rgba(255,255,255,.09);
    border:1px solid rgba(255,255,255,.11);
}
.hero-total span{display:block;color:#cbd5e1;font-size:11px;font-weight:850;margin-bottom:6px}
.hero-total strong{display:block;font-size:34px;line-height:1;font-weight:950;letter-spacing:-.04em}
.smart-alert{
    display:flex;
    gap:10px;
    padding:12px 14px;
    border-radius:18px;
    border:1px solid var(--line);
    box-shadow:0 12px 34px rgba(15,23,42,.055);
    background:#fff;
    margin-bottom:12px;
}
.smart-alert.success i{color:#16a34a}
.summary-grid{
    display:grid;
    grid-template-columns:repeat(4,minmax(0,1fr));
    gap:10px;
    margin-bottom:14px;
}
.metric-card{
    position:relative;
    overflow:hidden;
    padding:15px;
    border-radius:20px;
    background:#fff;
    border:1px solid var(--line);
    box-shadow:0 14px 40px rgba(15,23,42,.055);
}
.metric-card:after{
    content:"";
    position:absolute;
    width:82px;
    height:82px;
    right:-38px;
    bottom:-45px;
    border-radius:999px;
    opacity:.10;
    background:#2563eb;
}
.metric-card.green:after{background:#16a34a}
.metric-card.amber:after{background:#f59e0b}
.metric-card span{
    display:block;
    color:#64748b;
    font-size:10.8px;
    font-weight:950;
    text-transform:uppercase;
    letter-spacing:.04em;
    margin-bottom:8px;
}
.metric-card strong{
    display:block;
    font-size:22px;
    line-height:1;
    font-weight:950;
    letter-spacing:-.03em;
}
.metric-card.green strong{color:#16a34a}
.panel-pro{
    background:#fff;
    border:1px solid var(--line);
    border-radius:24px;
    box-shadow:0 16px 48px rgba(15,23,42,.06);
    overflow:hidden;
}
.panel-head{
    display:flex;
    align-items:flex-start;
    justify-content:space-between;
    gap:12px;
    padding:15px 16px;
    border-bottom:1px solid var(--line);
}
.section-chip{
    display:inline-flex;
    align-items:center;
    gap:6px;
    padding:5px 8px;
    border-radius:999px;
    background:#eef4ff;
    color:#1d4ed8;
    font-size:10px;
    font-weight:950;
    letter-spacing:.08em;
    margin-bottom:7px;
}
.panel-head h3{
    margin:0 0 4px;
    font-size:16px;
    font-weight:950;
}
.panel-head p{
    margin:0;
    color:var(--muted);
    font-size:12px;
}
.kpi-table thead th{
    background:#0f172a;
    color:#fff;
    border:0!important;
    font-size:11px;
    font-weight:950;
    padding:10px;
    white-space:nowrap;
}
.kpi-table tbody td{
    border-color:#edf2f7!important;
    color:#0f172a;
    font-size:12px;
    vertical-align:middle;
    padding:10px;
}
.kpi-table tbody tr:hover{background:#fbfdff}
.kpi-index{
    width:27px;
    height:27px;
    display:inline-flex;
    align-items:center;
    justify-content:center;
    border-radius:10px;
    background:#eef4ff;
    color:#1d4ed8;
    font-weight:950;
}
.kpi-name{font-weight:950;line-height:1.25}
.rate-pill{
    display:inline-flex;
    min-width:72px;
    justify-content:center;
    padding:5px 8px;
    border-radius:999px;
    background:#f1f5f9;
    font-weight:950;
}
.badge-rating{
    display:inline-flex;
    padding:5px 8px;
    border-radius:999px;
    font-size:10.5px;
    font-weight:950;
}
.badge-rating.ok{background:#dcfce7;color:#166534}
.badge-rating.warn{background:#fef3c7;color:#92400e}
.badge-rating.bad{background:#fee2e2;color:#991b1b}
.status-badge{
    display:inline-flex;
    padding:6px 10px;
    border-radius:999px;
    font-size:11px;
    font-weight:950;
}
.status-draft{background:#fef3c7;color:#92400e}
.status-approved{background:#dcfce7;color:#166534}
@keyframes shine{0%,100%{transform:translateX(-75%)}48%{transform:translateX(75%)}}
@media(max-width:991.98px){
    .payroll-top{flex-direction:column;align-items:stretch}
    .hero-grid,.summary-grid{grid-template-columns:1fr}
    .hero-slip h1{font-size:28px}
}

/* ego-tech-payroll-compact-v2 */
.techpay-page,
.techpay-edit-page,
.tech-settings-page,
.payroll-show-page{
    font-size:12px !important;
}

.techpay-page input,
.techpay-page select,
.techpay-page textarea,
.techpay-page button,
.techpay-edit-page input,
.techpay-edit-page select,
.techpay-edit-page textarea,
.techpay-edit-page button,
.tech-settings-page input,
.tech-settings-page select,
.tech-settings-page textarea,
.tech-settings-page button,
.payroll-show-page input,
.payroll-show-page select,
.payroll-show-page textarea,
.payroll-show-page button{
    font-size:12px !important;
}

.payroll-top,
.topbar-settings{
    margin-bottom:10px !important;
}

.payroll-title h2,
.topbar-settings h2,
.payroll-top h2{
    font-size:20px !important;
    line-height:1.15 !important;
    letter-spacing:-.02em !important;
}

.payroll-title p,
.topbar-settings p,
.payroll-top p{
    font-size:11.5px !important;
    line-height:1.4 !important;
}

.action-pill,
.btn-pill{
    min-height:34px !important;
    padding:0 12px !important;
    font-size:11.5px !important;
    border-radius:999px !important;
}

.hero-payroll,
.settings-hero,
.hero-slip{
    min-height:145px !important;
    padding:18px 20px !important;
    border-radius:22px !important;
    margin-bottom:10px !important;
}

.hero-content,
.hero-grid{
    gap:14px !important;
}

.hero-chip{
    padding:5px 9px !important;
    font-size:9.5px !important;
    margin-bottom:9px !important;
}

.hero-payroll h1,
.settings-hero h1,
.hero-slip h1{
    font-size:27px !important;
    line-height:1.08 !important;
    margin-bottom:6px !important;
}

.hero-payroll p,
.settings-hero p,
.hero-slip p{
    font-size:11.8px !important;
    line-height:1.55 !important;
}

.hero-total-box,
.hero-total{
    padding:13px 15px !important;
    border-radius:18px !important;
}

.hero-total-box span,
.hero-total span{
    font-size:10.5px !important;
    margin-bottom:5px !important;
}

.hero-total-box strong,
.hero-total strong{
    font-size:27px !important;
    letter-spacing:-.03em !important;
}

.layout-grid{
    grid-template-columns:300px minmax(0, 1fr) !important;
    gap:10px !important;
}

.panel-pro,
.setting-panel,
.kpi-items-manager{
    border-radius:18px !important;
    box-shadow:0 10px 28px rgba(15,23,42,.055) !important;
}

.panel-head,
.setting-head,
.kpi-items-head{
    padding:11px 13px !important;
    gap:10px !important;
}

.panel-head h3,
.setting-head h3,
.kpi-items-head h3{
    font-size:14px !important;
    line-height:1.2 !important;
    margin-bottom:3px !important;
}

.panel-head p,
.setting-head p,
.kpi-items-head p{
    font-size:11px !important;
    line-height:1.35 !important;
}

.section-chip{
    padding:4px 7px !important;
    font-size:9.5px !important;
    margin-bottom:5px !important;
}

.panel-body{
    padding:11px !important;
}

.table-card .panel-body{
    padding:0 !important;
}

.summary-grid{
    gap:8px !important;
    margin-bottom:10px !important;
}

.metric-card,
.summary-card{
    padding:10px 11px !important;
    border-radius:16px !important;
    box-shadow:0 9px 24px rgba(15,23,42,.045) !important;
}

.metric-card span,
.summary-card span,
.summary-card .label{
    font-size:9.8px !important;
    line-height:1.25 !important;
    margin-bottom:6px !important;
}

.metric-card strong,
.summary-card strong,
.summary-card .value{
    font-size:18px !important;
    line-height:1.05 !important;
}

.form-label-pro{
    font-size:10.5px !important;
    margin-bottom:5px !important;
}

.field-shell i{
    left:10px !important;
    font-size:12px !important;
}

.field-shell input,
.field-shell select,
.field-shell textarea,
.form-control,
.form-select{
    min-height:33px !important;
    border-radius:11px !important;
    font-size:12px !important;
    padding-top:0 !important;
    padding-bottom:0 !important;
}

.field-shell input,
.field-shell select,
.field-shell textarea{
    padding-left:30px !important;
}

.field-shell textarea,
textarea.form-control{
    min-height:70px !important;
    padding-top:8px !important;
    line-height:1.35 !important;
}

.help-text{
    font-size:10.5px !important;
    line-height:1.35 !important;
    margin-top:4px !important;
}

.feedback-list{
    gap:7px !important;
}

.feedback-mini{
    grid-template-columns:32px 1fr 72px !important;
    gap:8px !important;
    padding:8px !important;
    border-radius:14px !important;
}

.feedback-icon{
    width:32px !important;
    height:32px !important;
    border-radius:12px !important;
    font-size:15px !important;
}

.feedback-mini strong{
    font-size:11.3px !important;
}

.feedback-mini span{
    font-size:10px !important;
}

.feedback-mini input{
    min-height:31px !important;
    border-radius:10px !important;
    padding:0 7px !important;
}

.satisfaction-box{
    margin-top:8px !important;
    padding:9px 10px !important;
    border-radius:15px !important;
}

.satisfaction-box strong{
    font-size:18px !important;
}

.save-sticky{
    bottom:9px !important;
    margin-top:8px !important;
}

.btn-save-main,
.btn-save{
    min-height:40px !important;
    border-radius:14px !important;
    font-size:12px !important;
}

.kpi-table{
    width:100% !important;
    table-layout:fixed !important;
}

.kpi-table thead th{
    font-size:10.5px !important;
    padding:7px 8px !important;
}

.kpi-table tbody td{
    font-size:11.3px !important;
    padding:7px 8px !important;
}

.kpi-table th:nth-child(1),
.kpi-table td:nth-child(1){
    width:48px !important;
}

.kpi-table th:nth-child(2),
.kpi-table td:nth-child(2){
    width:285px !important;
}

.kpi-table th:nth-child(3),
.kpi-table td:nth-child(3){
    width:75px !important;
}

.kpi-table th:nth-child(4),
.kpi-table td:nth-child(4),
.kpi-table th:nth-child(5),
.kpi-table td:nth-child(5){
    width:110px !important;
}

.kpi-table th:nth-child(6),
.kpi-table td:nth-child(6),
.kpi-table th:nth-child(8),
.kpi-table td:nth-child(8){
    width:94px !important;
}

.kpi-table th:nth-child(7),
.kpi-table td:nth-child(7){
    width:76px !important;
}

.kpi-table th:nth-child(9),
.kpi-table td:nth-child(9){
    width:86px !important;
}

.kpi-index{
    width:24px !important;
    height:24px !important;
    border-radius:9px !important;
    font-size:11px !important;
}

.kpi-name{
    font-size:11.5px !important;
    line-height:1.25 !important;
}

.kpi-note{
    font-size:10px !important;
    line-height:1.3 !important;
    margin-top:2px !important;
}

.kpi-table input{
    min-width:0 !important;
    width:100% !important;
    min-height:30px !important;
    border-radius:10px !important;
    padding:0 7px !important;
    font-size:11.5px !important;
}

.rate-pill{
    min-width:0 !important;
    width:100% !important;
    padding:4px 5px !important;
    font-size:11px !important;
}

.badge-rating,
.status-badge{
    padding:4px 7px !important;
    font-size:10px !important;
}

.result-panel{
    padding:11px !important;
}

.result-grid{
    gap:8px !important;
}

.logic-box{
    margin-top:8px !important;
    padding:9px 10px !important;
    border-radius:14px !important;
    font-size:11.3px !important;
    line-height:1.45 !important;
}

.history-table th,
.history-table td{
    font-size:11.3px !important;
    padding:8px 9px !important;
}

.setting-list{
    padding:9px !important;
    gap:7px !important;
}

.setting-item{
    padding:9px !important;
    border-radius:15px !important;
}

.setting-line-top{
    grid-template-columns:1fr 92px 30px !important;
    gap:6px !important;
    margin-bottom:6px !important;
}

.setting-name,
.setting-note,
.new-key,
.percent-wrap input{
    min-height:31px !important;
    border-radius:11px !important;
    font-size:11.5px !important;
}

.percent-wrap span{
    right:9px !important;
    font-size:10.5px !important;
}

.setting-key{
    font-size:10px !important;
    margin:5px 0 !important;
}

.delete-check,
.setting-delete{
    width:30px !important;
    height:30px !important;
    border-radius:11px !important;
}

.delete-check input,
.setting-delete input{
    width:15px !important;
    height:15px !important;
}

.btn-add,
.add-mini-btn{
    padding:5px 9px !important;
    font-size:11px !important;
    border-radius:999px !important;
}

.kpi-items-table{
    min-width:1080px !important;
}

.kpi-items-table th{
    font-size:10.3px !important;
    padding:7px !important;
}

.kpi-items-table td{
    padding:6px !important;
}

.kpi-items-table input,
.kpi-items-table select,
.kpi-items-table textarea{
    min-height:30px !important;
    border-radius:10px !important;
    font-size:11.3px !important;
    padding:0 7px !important;
}

.kpi-items-table textarea{
    padding-top:6px !important;
    line-height:1.3 !important;
}

.kpi-items-actions,
.sticky-save{
    padding:8px !important;
    gap:7px !important;
}

.empty-list,
.empty-state,
.empty-mini{
    font-size:11.5px !important;
}

@media(max-width:1399.98px){
    .layout-grid{
        grid-template-columns:1fr !important;
    }

    .kpi-table{
        min-width:980px !important;
        table-layout:auto !important;
    }

    .kpi-table th:nth-child(n),
    .kpi-table td:nth-child(n){
        width:auto !important;
    }
}

@media(max-width:767.98px){
    .hero-payroll h1,
    .settings-hero h1,
    .hero-slip h1{
        font-size:23px !important;
    }

    .summary-grid,
    .result-grid{
        grid-template-columns:1fr !important;
    }

    .setting-line-top{
        grid-template-columns:1fr !important;
    }

    .feedback-mini{
        grid-template-columns:32px 1fr !important;
    }

    .feedback-mini input{
        grid-column:2 !important;
    }
}
/* end ego-tech-payroll-compact-v2 */

</style>

<div class="payroll-show-page">
    <div class="container-fluid py-3 py-lg-4">
        <div class="payroll-top">
            <div>
                <h2>Phiếu lương KPI kỹ thuật</h2>
                <p>{{ $payroll->employee_name }} — {{ $payroll->payroll_month }}</p>
            </div>

            <div class="d-flex flex-wrap gap-2">
                <a href="{{ route('ky-thuat.luong.index') }}" class="btn btn-outline-secondary btn-pill">
                    <i class="bi bi-list-ul me-1"></i>Danh sách
                </a>
                <a href="{{ route('ky-thuat.luong.edit', $payroll->id) }}" class="btn btn-warning btn-pill">
                    <i class="bi bi-pencil-square me-1"></i>Sửa
                </a>
                @if($payroll->status !== 'approved')
                    <form method="POST" action="{{ route('ky-thuat.luong.approve', $payroll->id) }}">
                        @csrf
                        <button class="btn btn-success btn-pill">
                            <i class="bi bi-check2-circle me-1"></i>Duyệt
                        </button>
                    </form>
                @endif
            </div>
        </div>

        @if(session('success'))
            <div class="smart-alert success">
                <i class="bi bi-check-circle fs-5"></i>
                <div><strong>Đã cập nhật</strong><br>{{ session('success') }}</div>
            </div>
        @endif

        <section class="hero-slip">
            <div class="hero-grid">
                <div>
                    <div class="hero-chip"><span></span> TECH PAYROLL SLIP</div>
                    <h1>{{ $payroll->employee_name }}</h1>
                    <p>
                        Tháng lương: <b>{{ $payroll->payroll_month }}</b>.
                        Trạng thái:
                        @if($status === 'approved')
                            <b>Đã duyệt</b>
                        @else
                            <b>Nháp</b>
                        @endif
                    </p>
                </div>

                <div class="hero-total">
                    <span>Thực lãnh</span>
                    <strong>{{ number_format($payroll->total_income,0,',','.') }} đ</strong>
                </div>
            </div>
        </section>

        <div class="summary-grid">
            <div class="metric-card">
                <span>Gross</span>
                <strong>{{ number_format($payroll->gross_salary,0,',','.') }} đ</strong>
            </div>
            <div class="metric-card amber">
                <span>KPI</span>
                <strong>{{ number_format($kpiPercent,2,',','.') }}%</strong>
            </div>
            <div class="metric-card">
                <span>Thưởng / Phạt KPI</span>
                <strong>{{ number_format($payroll->kpi_difference,0,',','.') }} đ</strong>
            </div>
            <div class="metric-card green">
                <span>Thực lãnh</span>
                <strong>{{ number_format($payroll->total_income,0,',','.') }} đ</strong>
            </div>
        </div>

        <div class="panel-pro">
            <div class="panel-head">
                <div>
                    <div class="section-chip"><i class="bi bi-table"></i> KPI DETAIL</div>
                    <h3>Chi tiết KPI</h3>
                    <p>Bảng tỷ lệ, trọng số, điểm và đánh giá từng chỉ tiêu.</p>
                </div>

                @if($status === 'approved')
                    <span class="status-badge status-approved">Đã duyệt</span>
                @else
                    <span class="status-badge status-draft">Nháp</span>
                @endif
            </div>

            <div class="table-responsive">
                <table class="table table-bordered align-middle kpi-table mb-0">
                    <thead>
                        <tr>
                            <th>STT</th>
                            <th>KPI</th>
                            <th>KH</th>
                            <th>TH</th>
                            <th>Tỷ lệ</th>
                            <th>Trọng số</th>
                            <th>Điểm</th>
                            <th>Đánh giá</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($items as $item)
                            @php
                                $rate = (float)($item->achievement_rate ?? 0);
                                $ratingClass = $rate >= 1 ? 'ok' : ($rate >= .9 ? 'warn' : 'bad');
                                $ratingText = $item->rating ?: ($rate >= 1 ? 'Đạt' : ($rate >= .9 ? 'Gần đạt' : 'Chưa đạt'));
                            @endphp
                            <tr>
                                <td class="text-center"><span class="kpi-index">{{ $item->sort_order }}</span></td>
                                <td><div class="kpi-name">{{ $item->kpi_name }}</div></td>
                                <td>{{ $item->plan_value }}</td>
                                <td>{{ $item->actual_value }}</td>
                                <td class="text-end"><span class="rate-pill">{{ number_format($rate * 100,2,',','.') }}%</span></td>
                                <td class="text-end fw-bold">{{ number_format($item->weight * 100,0,',','.') }}%</td>
                                <td class="text-end"><span class="rate-pill">{{ number_format($item->kpi_score * 100,2,',','.') }}%</span></td>
                                <td><span class="badge-rating {{ $ratingClass }}">{{ $ratingText }}</span></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
