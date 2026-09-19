@extends('layouts.app')

@section('content')
@php
    // Khi sửa một kỳ KPI đã lưu, dùng snapshot tiêu chí của chính kỳ đó.
    // Vì vậy việc Admin thêm/sửa/xóa KPI cho tháng sau không làm lệch phiếu cũ.
    $kpis = collect($kpis ?? [])->map(function ($row) {
        return [
            'definition_id' => $row['definition_id'] ?? null,
            'name' => $row['name'] ?? 'KPI',
            'unit' => $row['unit'] ?? '',
            'plan' => $row['default_plan'] ?? 0,
            'actual' => $row['default_actual'] ?? 0,
            'weight' => ((float)($row['weight'] ?? 0)) * 100,
            'type' => $row['type'] ?? 'actual_div_plan',
            'note' => $row['rule'] ?? '',
        ];
    })->toArray();

    $selectedUserId = old('user_id', $payroll->user_id ?? '');
    $grossValue = old('gross_salary', $payroll->gross_salary ?? 15000000);
    $payrollMonthValue = old('payroll_month', $payroll->payroll_month ?? date('Y-m'));
@endphp

<style>
.techpay-edit-page{
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
    background:
        radial-gradient(circle at top right, rgba(6,182,212,.08), transparent 26%),
        var(--bg);
    color:var(--text);
    font-size:12.5px;
    padding-bottom:42px;
}
.techpay-edit-page input,
.techpay-edit-page select,
.techpay-edit-page textarea,
.techpay-edit-page button{font-size:12.5px}
.payroll-top{
    display:flex;
    align-items:center;
    justify-content:space-between;
    gap:14px;
    margin-bottom:14px;
}
.payroll-title h2{
    margin:0;
    font-size:24px;
    font-weight:950;
    letter-spacing:-.03em;
}
.payroll-title p{
    margin:3px 0 0;
    color:var(--muted);
}
.action-pill{
    min-height:38px;
    border-radius:999px;
    padding:0 14px;
    font-weight:900;
    display:inline-flex;
    align-items:center;
    justify-content:center;
}
.hero-payroll{
    position:relative;
    overflow:hidden;
    border-radius:28px;
    padding:24px;
    min-height:205px;
    color:#fff;
    background:
        radial-gradient(circle at 82% 18%, rgba(34,211,238,.28), transparent 29%),
        radial-gradient(circle at 96% 80%, rgba(124,58,237,.23), transparent 28%),
        linear-gradient(135deg,#071225 0%,#0c3159 58%,#0f766e 100%);
    box-shadow:0 26px 74px rgba(15,23,42,.20);
    animation:heroIn .45s ease both;
    margin-bottom:14px;
}
.hero-payroll:before{
    content:"";
    position:absolute;
    inset:0;
    background:
        linear-gradient(110deg, transparent 0%, rgba(255,255,255,.12) 24%, transparent 48%),
        repeating-linear-gradient(90deg, rgba(255,255,255,.035) 0 1px, transparent 1px 80px);
    transform:translateX(-75%);
    animation:shine 6s ease-in-out infinite;
}
.hero-content{position:relative;z-index:2;display:grid;grid-template-columns:1.35fr .75fr;gap:20px;align-items:center}
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
.hero-payroll h1{
    margin:0 0 8px;
    font-size:34px;
    line-height:1.05;
    font-weight:950;
    letter-spacing:-.04em;
}
.hero-payroll p{
    max-width:760px;
    color:#cbd5e1;
    margin:0;
    line-height:1.7;
}
.hero-total-box{
    padding:18px;
    border-radius:22px;
    background:rgba(255,255,255,.09);
    border:1px solid rgba(255,255,255,.11);
}
.hero-total-box span{
    display:block;
    color:#cbd5e1;
    font-size:11px;
    font-weight:850;
    margin-bottom:6px;
}
.hero-total-box strong{
    display:block;
    font-size:34px;
    line-height:1;
    font-weight:950;
    letter-spacing:-.04em;
}
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
.smart-alert.danger i{color:#dc2626}
.smart-alert strong{display:block;font-size:13px}
.smart-alert span,.smart-alert li{color:var(--muted);font-size:12px}
.layout-grid{
    display:grid;
    grid-template-columns:320px minmax(0,1fr);
    gap:14px;
    align-items:start;
}
.panel-pro{
    background:#fff;
    border:1px solid var(--line);
    border-radius:24px;
    box-shadow:0 16px 48px rgba(15,23,42,.06);
    overflow:hidden;
    animation:fadeUp .42s ease both;
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
    letter-spacing:-.015em;
}
.panel-head p{
    margin:0;
    color:var(--muted);
    font-size:12px;
}
.panel-body{padding:15px}
.form-label-pro{
    display:block;
    margin-bottom:6px;
    color:#334155;
    font-size:11px;
    font-weight:950;
}
.field-shell{position:relative}
.field-shell i{
    position:absolute;
    left:12px;
    top:50%;
    transform:translateY(-50%);
    color:#64748b;
    z-index:2;
}
.field-shell input,
.field-shell select,
.field-shell textarea{
    width:100%;
    border:1px solid #dbe6f2;
    border-radius:15px;
    min-height:39px;
    padding:0 12px 0 36px;
    background:#fbfdff;
    outline:none;
    color:#0f172a;
    font-weight:800;
}
.field-shell textarea{
    min-height:86px;
    padding-top:10px;
    resize:vertical;
}
.help-text{
    margin-top:5px;
    color:#64748b;
    font-size:11px;
    line-height:1.45;
}
.feedback-list{display:grid;gap:9px}
.feedback-mini{
    display:grid;
    grid-template-columns:38px 1fr 86px;
    align-items:center;
    gap:10px;
    padding:10px;
    border-radius:17px;
    border:1px solid var(--line);
    background:#fbfdff;
}
.feedback-icon{
    width:38px;
    height:38px;
    border-radius:14px;
    display:flex;
    align-items:center;
    justify-content:center;
    font-size:18px;
}
.feedback-mini.bad .feedback-icon{background:#fee2e2;color:#dc2626}
.feedback-mini.neutral .feedback-icon{background:#e2e8f0;color:#475569}
.feedback-mini.good .feedback-icon{background:#dcfce7;color:#16a34a}
.feedback-mini strong{display:block;font-size:12px;font-weight:950}
.feedback-mini span{display:block;color:#64748b;font-size:10.5px}
.feedback-mini input{
    border:1px solid #dbe6f2;
    border-radius:13px;
    min-height:35px;
    padding:0 9px;
    font-weight:950;
}
.satisfaction-box{
    margin-top:10px;
    padding:12px;
    border-radius:18px;
    background:linear-gradient(135deg,#ecfeff,#f0fdf4);
    border:1px solid #bae6fd;
    display:flex;
    justify-content:space-between;
    align-items:center;
    gap:10px;
}
.satisfaction-box span{display:block;color:#64748b;font-size:11px}
.satisfaction-box strong{font-size:21px;font-weight:950;color:#16a34a}
.save-sticky{
    position:sticky;
    bottom:12px;
    z-index:20;
    margin-top:10px;
}
.btn-save-main{
    min-height:48px;
    border:0;
    border-radius:18px;
    color:#fff;
    font-weight:950;
    background:linear-gradient(135deg,#f59e0b,#ea580c);
    box-shadow:0 18px 38px rgba(245,158,11,.22);
}
.summary-grid{
    display:grid;
    grid-template-columns:repeat(4,minmax(0,1fr));
    gap:10px;
    margin-bottom:14px;
}
.metric-card{
    position:relative;
    overflow:hidden;
    padding:14px;
    border-radius:20px;
    background:#fff;
    border:1px solid var(--line);
    box-shadow:0 14px 40px rgba(15,23,42,.055);
    transition:.2s ease;
}
.metric-card:hover{transform:translateY(-2px);box-shadow:0 18px 46px rgba(15,23,42,.08)}
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
.metric-card.cyan:after{background:#06b6d4}
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
.metric-card.amber strong{color:#b45309}

/* compact-kpi-table-fix */
.kpi-table{
    width:100%;
    table-layout:fixed;
}
.kpi-table th:nth-child(1),
.kpi-table td:nth-child(1){
    width:48px;
}
.kpi-table th:nth-child(2),
.kpi-table td:nth-child(2){
    width:280px;
}
.kpi-table th:nth-child(3),
.kpi-table td:nth-child(3){
    width:76px;
}
.kpi-table th:nth-child(4),
.kpi-table td:nth-child(4),
.kpi-table th:nth-child(5),
.kpi-table td:nth-child(5){
    width:112px;
}
.kpi-table th:nth-child(6),
.kpi-table td:nth-child(6),
.kpi-table th:nth-child(8),
.kpi-table td:nth-child(8){
    width:92px;
}
.kpi-table th:nth-child(7),
.kpi-table td:nth-child(7){
    width:74px;
}
.kpi-table th:nth-child(9),
.kpi-table td:nth-child(9){
    width:88px;
}
.kpi-table input{
    min-width:0 !important;
    width:100%;
}
.kpi-name{
    font-size:12px;
}
.kpi-note{
    font-size:10.5px;
    line-height:1.35;
}
.kpi-table tbody td{
    padding:8px 8px;
}
.rate-pill{
    min-width:0;
    width:100%;
    padding:5px 6px;
}
@media(max-width:1399.98px){
    .layout-grid{
        grid-template-columns:1fr;
    }
    .kpi-table{
        min-width:980px;
        table-layout:auto;
    }
}

.table-card .panel-body{padding:0}
.kpi-table{margin:0;border-collapse:separate;border-spacing:0}
.kpi-table thead th{
    position:sticky;
    top:0;
    z-index:4;
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
    padding:9px 10px;
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
.kpi-note{color:#64748b;font-size:10.8px;margin-top:2px}
.kpi-table input{
    min-width:90px;
    min-height:34px;
    border:1px solid #dbe6f2;
    border-radius:12px;
    font-weight:850;
}
.rate-pill{
    display:inline-flex;
    min-width:68px;
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
.result-panel{padding:15px}
.result-grid{
    display:grid;
    grid-template-columns:repeat(3,minmax(0,1fr));
    gap:10px;
}
.logic-box{
    margin-top:11px;
    padding:12px 14px;
    border-radius:18px;
    background:#fff7ed;
    color:#9a3412;
    border:1px solid #fed7aa;
    font-weight:800;
    line-height:1.55;
}
@keyframes heroIn{from{opacity:0;transform:translateY(10px) scale(.99)}to{opacity:1;transform:translateY(0) scale(1)}}
@keyframes fadeUp{from{opacity:0;transform:translateY(12px)}to{opacity:1;transform:translateY(0)}}
@keyframes shine{0%,100%{transform:translateX(-75%)}48%{transform:translateX(75%)}}
@media(max-width:1399.98px){
    .layout-grid{grid-template-columns:1fr}
    .summary-grid{grid-template-columns:repeat(2,minmax(0,1fr))}
    .hero-content{grid-template-columns:1fr}
}
@media(max-width:767.98px){
    .payroll-top{flex-direction:column;align-items:stretch}
    .summary-grid,.result-grid{grid-template-columns:1fr}
    .feedback-mini{grid-template-columns:36px 1fr}
    .feedback-mini input{grid-column:2}
    .hero-payroll h1{font-size:28px}
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

<div class="techpay-edit-page">
    <div class="container-fluid py-3 py-lg-4">
        <div class="payroll-top">
            <div class="payroll-title">
                <h2>Sửa bảng lương KPI kỹ thuật</h2>
                <p>{{ $payroll->employee_name ?? 'Nhân viên kỹ thuật' }} — {{ $payroll->payroll_month ?? '' }}</p>
            </div>

            <div class="d-flex flex-wrap gap-2">
                <x-ui.button href="{{ route('ky-thuat.luong.index') }}" variant="outline-secondary" size="none" class="action-pill tw:leading-[1.5]">
                    <i class="bi bi-list-ul me-1"></i>Danh sách
                </x-ui.button>
                <x-ui.button href="{{ route('ky-thuat.luong.show', $payroll->id) }}" variant="outline-primary" size="none" class="action-pill tw:leading-[1.5]">
                    <i class="bi bi-eye me-1"></i>Xem phiếu
                </x-ui.button>
            </div>
        </div>

        <section class="hero-payroll">
            <div class="hero-content">
                <div>
                    <div class="hero-chip"><span></span> EDIT TECH PAYROLL</div>
                    <h1>Sửa phiếu lương KPI</h1>
                    <p>
                        Cập nhật nhân sự, gross, feedback và bảng KPI. Hệ thống tự tính lại tổng KPI,
                        thưởng/phạt và thực lãnh trước khi lưu.
                    </p>
                </div>

                <div class="hero-total-box">
                    <span>Tổng thực lãnh sau chỉnh sửa</span>
                    <strong id="heroTotal">0 đ</strong>
                </div>
            </div>
        </section>

        @if(session('success'))
            <div class="smart-alert success">
                <i class="bi bi-check-circle fs-5"></i>
                <div><strong>Đã lưu</strong><span>{{ session('success') }}</span></div>
            </div>
        @endif

        @if($errors->any())
            <div class="smart-alert danger">
                <i class="bi bi-exclamation-triangle fs-5"></i>
                <div>
                    <strong>Chưa lưu được</strong>
                    <ul class="mb-0 mt-1">
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            </div>
        @endif

        <form method="POST" action="{{ route('ky-thuat.luong.update', $payroll->id) }}" id="payrollForm">
        @method('PUT')
            @csrf
            @method('PUT')

            <div class="layout-grid">
                <aside>
                    <div class="panel-pro mb-3">
                        <div class="panel-head">
                            <div>
                                <div class="section-chip"><i class="bi bi-person-badge"></i> NHÂN SỰ</div>
                                <h3>Thông tin nhân viên</h3>
                                <p>Chỉnh nhân viên, tháng lương và gross.</p>
                            </div>
                        </div>

                        <div class="panel-body">
                            <div class="mb-3">
                                <label class="form-label-pro">Nhân viên kỹ thuật</label>
                                <div class="field-shell">
                                    <i class="bi bi-person"></i>
                                    <select name="user_id" id="userId" required>
                                        <option value="">-- Chọn nhân viên kỹ thuật --</option>
                                        @foreach($employees ?? [] as $employee)
                                            <option value="{{ $employee->id }}"
                                                    data-name="{{ $employee->name }}"
                                                    data-position="{{ $employee->position_name }}"
                                                    {{ (string)$selectedUserId === (string)$employee->id ? 'selected' : '' }}>
                                                {{ $employee->name }} — {{ $employee->position_name }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="help-text">Danh sách tự lấy từ user có role <b>technical</b>.</div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label-pro">Chức vụ</label>
                                <div class="field-shell">
                                    <i class="bi bi-briefcase"></i>
                                    <input type="text" id="positionName" value="{{ old('position_name', $payroll->position_name ?? '') }}" readonly>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label-pro">Tháng lương</label>
                                <div class="field-shell">
                                    <i class="bi bi-calendar3"></i>
                                    <input type="month" name="payroll_month" id="payrollMonth" value="{{ $payrollMonthValue }}" required>
                                </div>
                                <input type="hidden" name="month_label" id="monthLabel" value="{{ $payroll->month_label ?? '' }}">
                            </div>

                            <div class="mb-3">
                                <label class="form-label-pro">Tổng lương bậc Gross</label>
                                <div class="field-shell">
                                    <i class="bi bi-cash-stack"></i>
                                    <input type="number" name="gross_salary" id="grossSalary" value="{{ $grossValue }}" min="0" step="any" required>
                                </div>
                            </div>

                            <div>
                                <label class="form-label-pro">Ghi chú kế toán / trưởng bộ phận</label>
                                <div class="field-shell">
                                    <i class="bi bi-journal-text"></i>
                                    <textarea name="note" rows="3" placeholder="Ví dụ: đã đối chiếu KPI, có điều chỉnh...">{{ old('note', $payroll->note ?? '') }}</textarea>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="panel-pro">
                        <div class="panel-head">
                            <div>
                                <div class="section-chip"><i class="bi bi-chat-square-heart"></i> FEEDBACK</div>
                                <h3>Feedback khách hàng</h3>
                                <p>Tự tính điểm hài lòng vào KPI.</p>
                            </div>
                        </div>

                        <div class="panel-body">
                            <div class="feedback-list">
                                <div class="feedback-mini bad">
                                    <div class="feedback-icon"><i class="bi bi-emoji-frown"></i></div>
                                    <div><strong>Feedback không tốt</strong><span>Bị trừ theo setting</span></div>
                                    <input type="number" name="feedback_bad_count" id="badFeedback" value="{{ old('feedback_bad_count', $payroll->feedback_bad_count ?? 0) }}" min="0">
                                </div>

                                <div class="feedback-mini neutral">
                                    <div class="feedback-icon"><i class="bi bi-dash-circle"></i></div>
                                    <div><strong>Không nói gì</strong><span>Trung lập</span></div>
                                    <input type="number" name="feedback_neutral_count" id="neutralFeedback" value="{{ old('feedback_neutral_count', $payroll->feedback_neutral_count ?? 0) }}" min="0">
                                </div>

                                <div class="feedback-mini good">
                                    <div class="feedback-icon"><i class="bi bi-emoji-smile"></i></div>
                                    <div><strong>Phản hồi tốt</strong><span>Được cộng theo setting</span></div>
                                    <input type="number" name="feedback_good_count" id="goodFeedback" value="{{ old('feedback_good_count', $payroll->feedback_good_count ?? 0) }}" min="0">
                                </div>
                            </div>

                            <div class="satisfaction-box">
                                <div>
                                    <b>Điểm hài lòng</b>
                                    <span>Tính từ 3 nhóm feedback</span>
                                </div>
                                <strong id="feedbackRateBox">100.00%</strong>
                            </div>
                        </div>
                    </div>

                    <div class="save-sticky">
                        <button type="submit" class="btn btn-save-main w-100">
                            <i class="bi bi-save me-1"></i>Cập nhật bảng lương KPI
                        </button>
                    </div>
                </aside>

                <main>
                    <div class="summary-grid">
                        <div class="metric-card">
                            <span>Lương cố định 70%</span>
                            <strong id="baseSalary">0 đ</strong>
                        </div>
                        <div class="metric-card cyan">
                            <span>Quỹ KPI 30%</span>
                            <strong id="kpiBaseSalary">0 đ</strong>
                        </div>
                        <div class="metric-card amber">
                            <span>Tổng KPI</span>
                            <strong id="totalKpi">0%</strong>
                        </div>
                        <div class="metric-card green">
                            <span>Thực lãnh</span>
                            <strong id="totalIncome">0 đ</strong>
                        </div>
                    </div>

                    <div class="panel-pro table-card mb-3">
                        <div class="panel-head">
                            <div>
                                <div class="section-chip"><i class="bi bi-table"></i> KPI DETAIL</div>
                                <h3>Bảng KPI chi tiết</h3>
                                <p>Nhập KH/TH, hệ thống tự tính tỷ lệ, điểm và đánh giá.</p>
                            </div>
                        </div>

                        <div class="panel-body">
                            <div class="table-responsive">
                                <table class="table table-bordered align-middle kpi-table">
                                    <thead>
                                        <tr>
                                            <th>STT</th>
                                            <th>Chỉ tiêu</th>
                                            <th>ĐVT</th>
                                            <th>KH</th>
                                            <th>TH</th>
                                            <th>Tỷ lệ</th>
                                            <th>Trọng số</th>
                                            <th>Điểm</th>
                                            <th>Đánh giá</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($kpis as $i => $kpi)
                                            <tr data-row="{{ $i }}" data-type="{{ $kpi['type'] }}" data-weight="{{ $kpi['weight'] / 100 }}">
                                                <td class="text-center"><span class="kpi-index">{{ $i }}</span></td>
                                                <td>
                                                    <div class="kpi-name">{{ $kpi['name'] }}</div>
                                                    <div class="kpi-note">{{ $kpi['note'] }}</div>
                                                    <input type="hidden" name="kpis[{{ $i }}][definition_id]" value="{{ $kpi['definition_id'] ?? '' }}">
                                                    <input type="hidden" name="kpis[{{ $i }}][name]" value="{{ $kpi['name'] }}">
                                                    <input type="hidden" name="kpis[{{ $i }}][unit]" value="{{ $kpi['unit'] }}">
                                                    <input type="hidden" name="kpis[{{ $i }}][weight]" value="{{ $kpi['weight'] / 100 }}">
                                                    <input type="hidden" name="kpis[{{ $i }}][type]" value="{{ $kpi['type'] }}">
                                                </td>
                                                <td>{{ $kpi['unit'] }}</td>
                                                <td>
                                                    <input type="number" step="0.01" name="kpis[{{ $i }}][plan]" class="form-control form-control-sm kpi-plan" value="{{ old("kpis.$i.plan", $kpi['plan']) }}" {{ $kpi['type'] === 'customer_feedback' ? 'readonly' : '' }}>
                                                </td>
                                                <td>
                                                    <input type="number" step="0.01" name="kpis[{{ $i }}][actual]" class="form-control form-control-sm kpi-actual" value="{{ old("kpis.$i.actual", $kpi['actual']) }}" {{ $kpi['type'] === 'customer_feedback' ? 'readonly' : '' }}>
                                                </td>
                                                <td class="text-end"><span class="rate-pill kpi-rate">0%</span></td>
                                                <td class="text-end fw-bold">{{ number_format($kpi['weight'], 0, ',', '.') }}%</td>
                                                <td class="text-end"><span class="rate-pill kpi-score">0%</span></td>
                                                <td class="kpi-rating">-</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <div class="panel-pro mb-3">
                        <div class="panel-head">
                            <div>
                                <div class="section-chip"><i class="bi bi-cash-coin"></i> RESULT</div>
                                <h3>Kết quả sau chỉnh sửa</h3>
                                <p>70% cố định giữ nguyên, 30% KPI thay đổi theo điểm.</p>
                            </div>
                        </div>

                        <div class="result-panel">
                            <div class="result-grid">
                                <div class="metric-card">
                                    <span>Lương KPI thực nhận</span>
                                    <strong id="realKpiSalary">0 đ</strong>
                                </div>
                                <div class="metric-card amber">
                                    <span>Thưởng / phạt KPI</span>
                                    <strong id="kpiDiff">0 đ</strong>
                                </div>
                                <div class="metric-card green">
                                    <span>Tổng thu nhập</span>
                                    <strong id="totalIncome2">0 đ</strong>
                                </div>
                            </div>

                            <div class="logic-box">
                                <i class="bi bi-info-circle me-1"></i>
                                Khi bấm cập nhật, hệ thống lưu lại phiếu lương và các dòng KPI theo số liệu mới.
                            </div>
                        </div>
                    </div>
                </main>
            </div>
        </form>
    </div>
</div>

<script>
const SETTINGS = {
    baseSalaryRate: {{ (float)($settings['base_salary_rate'] ?? 0.7) }},
    kpiSalaryRate: {{ (float)($settings['kpi_salary_rate'] ?? 0.3) }},
    badFeedbackPenalty: {{ (float)($settings['bad_feedback_penalty'] ?? 0.1) }},
    goodFeedbackBonus: {{ (float)($settings['good_feedback_bonus'] ?? 0.05) }},
    customerFeedbackMax: {{ (float)($settings['customer_feedback_max'] ?? 1.3) }},
    qualityErrorPenalty: {{ (float)($settings['quality_error_penalty'] ?? 0.05) }},
    safetyErrorPenalty: {{ (float)($settings['safety_error_penalty'] ?? 0.05) }},
    equipmentErrorPenalty: {{ (float)($settings['equipment_error_penalty'] ?? 0.05) }},
    successProjectBonus: {{ (float)($settings['success_project_bonus'] ?? 0.1) }},
    kpiMaxRate: {{ (float)($settings['kpi_max_rate'] ?? 1.3) }},
};

function money(number) {
    return new Intl.NumberFormat('vi-VN').format(Math.round(number || 0)) + ' đ';
}

function pct(number) {
    return (Math.round((number || 0) * 10000) / 100).toFixed(2) + '%';
}

function rating(rate) {
    if (rate >= 1) return '<span class="badge-rating ok">Đạt</span>';
    if (rate >= 0.9) return '<span class="badge-rating warn">Gần đạt</span>';
    return '<span class="badge-rating bad">Chưa đạt</span>';
}

function calcCustomerFeedbackRate() {
    const bad = parseFloat(document.getElementById('badFeedback').value) || 0;
    const neutral = parseFloat(document.getElementById('neutralFeedback').value) || 0;
    const good = parseFloat(document.getElementById('goodFeedback').value) || 0;
    const customerRow = document.querySelector('[data-type="customer_feedback"]');

    if (customerRow) {
        customerRow.querySelector('.kpi-plan').value = bad + neutral + good;
        customerRow.querySelector('.kpi-actual').value = good - bad;
    }

    return Math.max(0, Math.min(SETTINGS.customerFeedbackMax, 1 + (good * SETTINGS.goodFeedbackBonus) - (bad * SETTINGS.badFeedbackPenalty)));
}

function calcRate(type, plan, actual) {
    if (type === 'plan_div_actual') return actual === 0 ? 0 : plan / actual;
    if (type === 'actual_div_plan') return plan === 0 ? 0 : actual / plan;
    if (type === 'minus_quality') return Math.max(0, 1 - (SETTINGS.qualityErrorPenalty * actual));
    if (type === 'minus_safety') return Math.max(0, 1 - (SETTINGS.safetyErrorPenalty * actual));
    if (type === 'minus_equipment') return Math.max(0, 1 - (SETTINGS.equipmentErrorPenalty * actual));
    if (type === 'success_project') return Math.min(SETTINGS.kpiMaxRate, actual * SETTINGS.successProjectBonus);
    if (type === 'customer_feedback') return calcCustomerFeedbackRate();
    if (type === 'ot_rule') {
        if (actual === 0) return 1;
        if (plan === 0) return Math.max(0, 1 - (actual * 0.02));
        return plan / actual;
    }
    return 0;
}

function updateEmployeePosition() {
    const select = document.getElementById('userId');
    const option = select.options[select.selectedIndex];
    document.getElementById('positionName').value = option ? (option.dataset.position || '') : '';
}

function updateMonthLabel() {
    const value = document.getElementById('payrollMonth').value;
    if (!value) return;
    const parts = value.split('-');
    document.getElementById('monthLabel').value = 'Tháng ' + parseInt(parts[1]) + ' / ' + parts[0];
}

function calculateAll() {
    updateMonthLabel();

    let totalScore = 0;
    let totalWeight = 0;
    let feedbackRate = calcCustomerFeedbackRate();

    document.getElementById('feedbackRateBox').innerText = pct(feedbackRate);

    document.querySelectorAll('[data-row]').forEach(function(row) {
        const type = row.dataset.type;
        const weight = parseFloat(row.dataset.weight) || 0;
        const plan = parseFloat(row.querySelector('.kpi-plan').value) || 0;
        const actual = parseFloat(row.querySelector('.kpi-actual').value) || 0;
        const rate = calcRate(type, plan, actual);
        const score = rate * weight;

        totalScore += score;
        totalWeight += weight;

        row.querySelector('.kpi-rate').innerText = pct(rate);
        row.querySelector('.kpi-score').innerText = pct(score);
        row.querySelector('.kpi-rating').innerHTML = rating(rate);
    });

    let totalKpi = totalWeight > 0 ? totalScore / totalWeight : 0;
    totalKpi = Math.min(SETTINGS.kpiMaxRate, totalKpi);

    const gross = parseFloat(document.getElementById('grossSalary').value) || 0;
    const baseSalary = gross * SETTINGS.baseSalaryRate;
    const kpiBaseSalary = gross * SETTINGS.kpiSalaryRate;
    const realKpiSalary = kpiBaseSalary * totalKpi;
    const kpiDiff = realKpiSalary - kpiBaseSalary;
    const totalIncome = baseSalary + realKpiSalary;

    document.getElementById('baseSalary').innerText = money(baseSalary);
    document.getElementById('kpiBaseSalary').innerText = money(kpiBaseSalary);
    document.getElementById('totalKpi').innerText = pct(totalKpi);
    document.getElementById('realKpiSalary').innerText = money(realKpiSalary);
    document.getElementById('kpiDiff').innerText = money(kpiDiff);
    document.getElementById('totalIncome').innerText = money(totalIncome);
    document.getElementById('totalIncome2').innerText = money(totalIncome);
    document.getElementById('heroTotal').innerText = money(totalIncome);
}

document.querySelectorAll('input, select, textarea').forEach(function(el) {
    el.addEventListener('input', calculateAll);
    el.addEventListener('change', function() {
        updateEmployeePosition();
        calculateAll();
    });
});

updateEmployeePosition();
calculateAll();
</script>
@endsection
