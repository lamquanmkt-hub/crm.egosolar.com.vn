@extends('layouts.app')

@section('content')
<style>
.tech-settings-page{
    --bg:#f5f7fb;
    --panel:#fff;
    --line:#e5edf7;
    --text:#0f172a;
    --muted:#64748b;
    --blue:#2563eb;
    --cyan:#06b6d4;
    --green:#16a34a;
    --purple:#7c3aed;
    --red:#dc2626;
    min-height:100vh;
    background:
        radial-gradient(circle at top right, rgba(6,182,212,.08), transparent 28%),
        var(--bg);
    color:var(--text);
    font-size:12.5px;
    padding-bottom:56px;
}
.tech-settings-page input,
.tech-settings-page button{font-size:12.5px}
.topbar-settings{
    display:flex;
    justify-content:space-between;
    align-items:center;
    gap:14px;
    margin-bottom:14px;
}
.topbar-settings h2{
    margin:0;
    font-size:24px;
    font-weight:950;
    letter-spacing:-.03em;
}
.topbar-settings p{margin:3px 0 0;color:var(--muted)}
.btn-pill{
    min-height:38px;
    border-radius:999px;
    padding:0 14px;
    font-weight:900;
    display:inline-flex;
    align-items:center;
    justify-content:center;
}
.settings-hero{
    position:relative;
    overflow:hidden;
    padding:22px;
    border-radius:28px;
    color:#fff;
    background:
        radial-gradient(circle at 78% 20%, rgba(34,211,238,.26), transparent 28%),
        linear-gradient(135deg,#071225 0%,#0c3159 58%,#0f766e 100%);
    box-shadow:0 26px 74px rgba(15,23,42,.20);
    margin-bottom:14px;
}
.settings-hero:before{
    content:"";
    position:absolute;
    inset:0;
    background:linear-gradient(110deg, transparent 0%, rgba(255,255,255,.12) 24%, transparent 48%);
    transform:translateX(-75%);
    animation:shine 6s ease-in-out infinite;
}
.settings-hero > *{position:relative;z-index:2}
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
.settings-hero h1{
    margin:0 0 8px;
    font-size:32px;
    font-weight:950;
    letter-spacing:-.04em;
}
.settings-hero p{
    margin:0;
    color:#cbd5e1;
    max-width:840px;
    line-height:1.7;
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
.summary-grid{
    display:grid;
    grid-template-columns:repeat(3,minmax(0,1fr));
    gap:10px;
    margin-bottom:14px;
}
.summary-card{
    position:relative;
    overflow:hidden;
    padding:15px;
    border-radius:20px;
    background:#fff;
    border:1px solid var(--line);
    box-shadow:0 14px 40px rgba(15,23,42,.055);
}
.summary-card:after{
    content:"";
    position:absolute;
    width:90px;
    height:90px;
    right:-42px;
    bottom:-48px;
    border-radius:999px;
    background:#2563eb;
    opacity:.1;
}
.summary-card.green:after{background:#16a34a}
.summary-card.purple:after{background:#7c3aed}
.summary-card span{
    display:block;
    color:#64748b;
    font-size:11px;
    font-weight:950;
    text-transform:uppercase;
    letter-spacing:.04em;
}
.summary-card strong{
    display:block;
    font-size:30px;
    line-height:1;
    margin-top:7px;
    font-weight:950;
}
.settings-layout{
    display:grid;
    grid-template-columns:1fr 1fr;
    gap:14px;
    align-items:start;
}
.setting-panel{
    background:#fff;
    border:1px solid var(--line);
    border-radius:24px;
    overflow:hidden;
    box-shadow:0 16px 48px rgba(15,23,42,.06);
    margin-bottom:14px;
    animation:fadeUp .42s ease both;
}
.setting-head{
    display:flex;
    align-items:flex-start;
    justify-content:space-between;
    gap:12px;
    padding:15px 16px;
    color:#fff;
}
.head-salary{background:linear-gradient(135deg,#2563eb,#0284c7)}
.head-feedback{background:linear-gradient(135deg,#16a34a,#0f766e)}
.head-kpi{background:linear-gradient(135deg,#0f172a,#334155)}
.head-other{background:linear-gradient(135deg,#7c3aed,#4338ca)}
.setting-head h3{
    margin:0 0 3px;
    font-size:15px;
    font-weight:950;
}
.setting-head p{
    margin:0;
    color:rgba(255,255,255,.78);
    font-size:11.5px;
}
.btn-add{
    border:0;
    border-radius:999px;
    padding:7px 10px;
    background:#fff;
    color:#0f172a;
    font-size:11.5px;
    font-weight:950;
    white-space:nowrap;
}
.setting-list{
    padding:12px;
    display:grid;
    gap:9px;
}
.setting-item{
    position:relative;
    padding:11px;
    border:1px solid var(--line);
    border-radius:18px;
    background:#fbfdff;
    transition:.2s ease;
}
.setting-item:hover{
    transform:translateY(-1px);
    box-shadow:0 12px 30px rgba(15,23,42,.06);
}
.setting-item.new-item{
    background:#f0fdf4;
    border-color:#bbf7d0;
}
.setting-line-top{
    display:grid;
    grid-template-columns:1fr 118px auto;
    gap:8px;
    align-items:center;
}
.setting-name,
.setting-note,
.new-key{
    border:1px solid #dbe6f2;
    border-radius:13px;
    min-height:35px;
    font-weight:850;
    background:#fff;
}
.percent-wrap{
    position:relative;
}
.percent-wrap input{
    border:1px solid #dbe6f2;
    border-radius:13px;
    min-height:35px;
    padding-right:30px;
    font-weight:950;
}
.percent-wrap span{
    position:absolute;
    right:11px;
    top:50%;
    transform:translateY(-50%);
    color:#64748b;
    font-size:11px;
    font-weight:950;
}
.setting-key{
    color:#64748b;
    font-family:monospace;
    font-size:10.5px;
    margin:7px 0;
    word-break:break-all;
}
.delete-check{
    width:34px;
    height:34px;
    border-radius:13px;
    background:#fff;
    border:1px solid #dbe6f2;
    display:flex;
    align-items:center;
    justify-content:center;
}
.delete-check input{width:17px;height:17px}
.empty-list{
    color:#64748b;
    font-size:12px;
    padding:10px;
    border:1px dashed #d8e2ee;
    border-radius:16px;
    text-align:center;
}
.sticky-save{
    position:sticky;
    bottom:10px;
    z-index:30;
    margin-top:14px;
    padding:10px;
    background:rgba(245,247,251,.88);
    backdrop-filter:blur(10px);
    display:flex;
    justify-content:flex-end;
    gap:8px;
}
.btn-save{
    border:0;
    border-radius:999px;
    min-height:42px;
    padding:0 22px;
    color:#fff;
    font-weight:950;
    background:linear-gradient(135deg,#16a34a,#0f766e);
    box-shadow:0 16px 32px rgba(22,163,74,.18);
}
@keyframes fadeUp{from{opacity:0;transform:translateY(12px)}to{opacity:1;transform:translateY(0)}}
@keyframes shine{0%,100%{transform:translateX(-75%)}48%{transform:translateX(75%)}}
@media(max-width:1200px){.settings-layout{grid-template-columns:1fr}}
@media(max-width:768px){
    .topbar-settings{flex-direction:column;align-items:stretch}
    .summary-grid{grid-template-columns:1fr}
    .setting-line-top{grid-template-columns:1fr}
    .sticky-save{flex-direction:column}
}

.kpi-items-manager{
    background:#fff;
    border:1px solid #e5edf7;
    border-radius:24px;
    overflow:hidden;
    box-shadow:0 16px 48px rgba(15,23,42,.06);
    margin-bottom:14px;
}
.kpi-items-head{
    display:flex;
    align-items:flex-start;
    justify-content:space-between;
    gap:12px;
    padding:15px 16px;
    color:#fff;
    background:linear-gradient(135deg,#0f172a,#334155);
}
.kpi-items-head h3{
    margin:0 0 3px;
    font-size:15px;
    font-weight:950;
}
.kpi-items-head p{
    margin:0;
    color:rgba(255,255,255,.78);
    font-size:11.5px;
}
.kpi-items-table{
    width:100%;
    min-width:1180px;
    margin:0;
}
.kpi-items-table th{
    background:#f8fafc;
    color:#334155;
    font-size:11px;
    font-weight:950;
    padding:9px;
    white-space:nowrap;
}
.kpi-items-table td{
    padding:8px;
    border-color:#edf2f7!important;
    vertical-align:middle;
}
.kpi-items-table input,
.kpi-items-table select{
    min-height:34px;
    border:1px solid #dbe6f2;
    border-radius:12px;
    font-size:12px;
    font-weight:850;
}
.kpi-items-table textarea{
    min-height:36px;
    border:1px solid #dbe6f2;
    border-radius:12px;
    font-size:12px;
    font-weight:750;
    resize:vertical;
}
.kpi-items-actions{
    display:flex;
    justify-content:flex-end;
    gap:8px;
    padding:12px;
    border-top:1px solid #e5edf7;
    background:#fbfdff;
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


/* ego-kpi-items-ui-v3 */
.kpi-items-manager{
    border-radius:18px !important;
    overflow:hidden !important;
    border:1px solid #e5edf7 !important;
    box-shadow:0 10px 28px rgba(15,23,42,.055) !important;
}
.kpi-items-head{
    padding:12px 14px !important;
    background:linear-gradient(135deg,#0f172a,#24344f) !important;
}
.kpi-items-head h3{
    font-size:14px !important;
    margin:0 0 3px !important;
    font-weight:950 !important;
}
.kpi-items-head p{
    font-size:11px !important;
    margin:0 !important;
    color:rgba(255,255,255,.78) !important;
}
.kpi-items-manager .table-responsive{
    overflow-x:auto !important;
    background:#fff !important;
}
.kpi-items-table{
    width:100% !important;
    min-width:1120px !important;
    margin:0 !important;
    table-layout:fixed !important;
}
.kpi-items-table th{
    background:#f8fafc !important;
    color:#334155 !important;
    font-size:10.5px !important;
    font-weight:950 !important;
    padding:8px 7px !important;
    border-color:#e5edf7 !important;
    white-space:nowrap !important;
}
.kpi-items-table td{
    padding:6px 7px !important;
    border-color:#edf2f7 !important;
    vertical-align:middle !important;
    background:#fff !important;
}
.kpi-items-table th:nth-child(1),
.kpi-items-table td:nth-child(1){width:58px !important;}
.kpi-items-table th:nth-child(2),
.kpi-items-table td:nth-child(2){width:210px !important;}
.kpi-items-table th:nth-child(3),
.kpi-items-table td:nth-child(3){width:82px !important;}
.kpi-items-table th:nth-child(4),
.kpi-items-table td:nth-child(4),
.kpi-items-table th:nth-child(5),
.kpi-items-table td:nth-child(5){width:92px !important;}
.kpi-items-table th:nth-child(6),
.kpi-items-table td:nth-child(6){width:96px !important;}
.kpi-items-table th:nth-child(7),
.kpi-items-table td:nth-child(7){width:175px !important;}
.kpi-items-table th:nth-child(8),
.kpi-items-table td:nth-child(8){width:auto !important;}
.kpi-items-table th:nth-child(9),
.kpi-items-table td:nth-child(9),
.kpi-items-table th:nth-child(10),
.kpi-items-table td:nth-child(10){
    width:58px !important;
    text-align:center !important;
}
.kpi-items-table input,
.kpi-items-table select,
.kpi-items-table textarea{
    width:100% !important;
    min-height:30px !important;
    border:1px solid #dbe6f2 !important;
    border-radius:10px !important;
    padding:0 8px !important;
    color:#0f172a !important;
    background:#fbfdff !important;
    font-size:11.5px !important;
    font-weight:850 !important;
    box-shadow:none !important;
}
.kpi-items-table textarea{
    min-height:34px !important;
    padding-top:6px !important;
    line-height:1.3 !important;
    resize:vertical !important;
}
.kpi-items-table select{
    padding-right:26px !important;
}
.kpi-items-table tr{
    transition:.16s ease !important;
}
.kpi-items-table tbody tr:hover td{
    background:#fbfdff !important;
}
.kpi-action-cell{
    text-align:center !important;
}
.kpi-items-table input[type="checkbox"]{
    appearance:none !important;
    -webkit-appearance:none !important;
    width:24px !important;
    height:24px !important;
    min-height:24px !important;
    padding:0 !important;
    border-radius:9px !important;
    border:1px solid #cfe0f3 !important;
    background:#fff !important;
    display:inline-grid !important;
    place-content:center !important;
    cursor:pointer !important;
    transition:.16s ease !important;
}
.kpi-items-table input[type="checkbox"]::after{
    content:"✓";
    font-size:14px;
    line-height:1;
    color:#fff;
    font-weight:950;
    transform:scale(0);
    transition:.12s ease;
}
.kpi-items-table input[type="checkbox"]:checked{
    background:#2563eb !important;
    border-color:#2563eb !important;
    box-shadow:0 8px 18px rgba(37,99,235,.22) !important;
}
.kpi-items-table input[type="checkbox"]:checked::after{
    transform:scale(1);
}
.kpi-trash-btn{
    width:28px !important;
    height:28px !important;
    border-radius:10px !important;
    border:1px solid #fecaca !important;
    background:#fff5f5 !important;
    color:#dc2626 !important;
    display:inline-flex !important;
    align-items:center !important;
    justify-content:center !important;
    font-size:13px !important;
    line-height:1 !important;
    cursor:pointer !important;
    transition:.16s ease !important;
}
.kpi-trash-btn:hover{
    transform:translateY(-1px) !important;
    background:#dc2626 !important;
    color:#fff !important;
    border-color:#dc2626 !important;
    box-shadow:0 10px 22px rgba(220,38,38,.20) !important;
}
.kpi-trash-btn.is-loading{
    pointer-events:none !important;
    opacity:.55 !important;
}
.kpi-trash-btn.is-local{
    border-color:#fed7aa !important;
    background:#fff7ed !important;
    color:#ea580c !important;
}
.kpi-items-actions{
    padding:10px 12px !important;
    gap:8px !important;
    border-top:1px solid #e5edf7 !important;
    background:#fbfdff !important;
}
.kpi-items-actions .btn,
.kpi-items-actions button{
    min-height:36px !important;
    border-radius:999px !important;
    font-size:11.5px !important;
    font-weight:950 !important;
}
@media(max-width:1399.98px){
    .kpi-items-table{
        min-width:1120px !important;
    }
}
/* end ego-kpi-items-ui-v3 */

</style>

@php
    $allSettings = collect($settings ?? []);
    $salaryKeys = ['base_salary_rate', 'kpi_salary_rate'];
    $feedbackKeys = ['bad_feedback_penalty', 'good_feedback_bonus', 'customer_feedback_max'];
    $kpiKeys = ['quality_error_penalty', 'safety_error_penalty', 'equipment_error_penalty', 'success_project_bonus', 'kpi_max_rate'];
    $usedKeys = array_merge($salaryKeys, $feedbackKeys, $kpiKeys);

    $salarySettings = $allSettings->filter(fn($s) => in_array($s->setting_key, $salaryKeys));
    $feedbackSettings = $allSettings->filter(fn($s) => in_array($s->setting_key, $feedbackKeys));
    $kpiSettings = $allSettings->filter(fn($s) => in_array($s->setting_key, $kpiKeys));
    $otherSettings = $allSettings->filter(fn($s) => !in_array($s->setting_key, $usedKeys));
@endphp


@php
    $kpiItemRows = collect();

    if (\Illuminate\Support\Facades\Schema::hasTable('technical_payroll_kpi_items')) {
        $kpiItemRows = \Illuminate\Support\Facades\DB::table('technical_payroll_kpi_items')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();
    }
@endphp

<div class="tech-settings-page">
    <div class="container-fluid py-3 py-lg-4">
        <div class="topbar-settings">
            <div>
                <h2>Cài đặt KPI kỹ thuật</h2>
                <p>Quản lý tỷ lệ lương, feedback và các rule cộng/trừ KPI.</p>
            </div>
            <a href="{{ route('ky-thuat.luong.index') }}" class="btn btn-outline-secondary btn-pill">
                <i class="bi bi-arrow-left me-1"></i>Quay lại bảng lương
            </a>
        </div>

        <section class="settings-hero">
            <div class="hero-chip"><span></span> TECH KPI SETTINGS</div>
            <h1>Thông số tính lương KPI</h1>
            <p>Nhập dạng phần trăm. Ví dụ nhập <b>70</b> nghĩa là <b>70%</b>. Khi lưu, hệ thống tự đổi về 0.7000 để tính toán.</p>
        </section>

        @if(session('success'))
            <div class="smart-alert success"><i class="bi bi-check-circle fs-5"></i><div><b>Đã lưu</b><br>{{ session('success') }}</div></div>
        @endif

        @if(session('error'))
            <div class="smart-alert danger"><i class="bi bi-exclamation-triangle fs-5"></i><div><b>Có lỗi</b><br>{{ session('error') }}</div></div>
        @endif

        
        <form method="POST" action="{{ route('ky-thuat.luong.settings.kpi-items') }}" class="kpi-items-manager" id="kpiItemsForm">
            @csrf

            <div class="kpi-items-head">
                <div>
                    <h3><i class="bi bi-table me-1"></i>Dòng KPI chi tiết</h3>
                    <p>Thêm / bớt / sửa các dòng sẽ hiển thị ở Bảng KPI chi tiết ngoài trang tính lương.</p>
                </div>

                <button type="button" class="btn-add" onclick="addKpiItemRow()">
                    + Thêm dòng KPI
                </button>
            </div>

            <div class="table-responsive">
                <table class="table table-bordered align-middle kpi-items-table">
                    <thead>
                        <tr>
                            <th style="width:70px">STT</th>
                            <th style="width:230px">Tên chỉ tiêu</th>
                            <th style="width:95px">ĐVT</th>
                            <th style="width:100px">KH mặc định</th>
                            <th style="width:100px">TH mặc định</th>
                            <th style="width:110px">Trọng số %</th>
                            <th style="width:190px">Cách tính</th>
                            <th>Ghi chú</th>
                            <th style="width:80px">Hiện</th>
                            <th style="width:80px">Xóa</th>
                        </tr>
                    </thead>

                    <tbody id="kpiItemsBody">
                        @forelse($kpiItemRows as $item)
                            <tr>
                                <td>
                                    <input type="hidden" name="kpi_items[{{ $loop->index }}][id]" value="{{ $item->id }}">
                                    <input type="number" name="kpi_items[{{ $loop->index }}][sort_order]" value="{{ $item->sort_order }}" class="form-control">
                                </td>
                                <td>
                                    <input type="text" name="kpi_items[{{ $loop->index }}][name]" value="{{ $item->name }}" class="form-control" required>
                                </td>
                                <td>
                                    <input type="text" name="kpi_items[{{ $loop->index }}][unit]" value="{{ $item->unit }}" class="form-control">
                                </td>
                                <td>
                                    <input type="number" step="0.01" name="kpi_items[{{ $loop->index }}][plan_value]" value="{{ $item->plan_value }}" class="form-control">
                                </td>
                                <td>
                                    <input type="number" step="0.01" name="kpi_items[{{ $loop->index }}][actual_value]" value="{{ $item->actual_value }}" class="form-control">
                                </td>
                                <td>
                                    <input type="number" step="0.01" name="kpi_items[{{ $loop->index }}][weight_percent]" value="{{ number_format((float)$item->weight * 100, 2, '.', '') }}" class="form-control">
                                </td>
                                <td>
                                    <select name="kpi_items[{{ $loop->index }}][calc_type]" class="form-select">
                                        @foreach([
                                            'actual_div_plan' => 'TH / KH',
                                            'plan_div_actual' => 'KH / TH',
                                            'minus_quality' => 'Trừ lỗi chất lượng',
                                            'minus_safety' => 'Trừ sự cố an toàn',
                                            'minus_equipment' => 'Trừ lỗi thiết bị',
                                            'customer_feedback' => 'Feedback khách hàng',
                                            'success_project' => 'Cộng công trình chốt',
                                            'ot_rule' => 'Quy tắc OT'
                                        ] as $key => $label)
                                            <option value="{{ $key }}" {{ $item->calc_type === $key ? 'selected' : '' }}>{{ $label }}</option>
                                        @endforeach
                                    </select>
                                </td>
                                <td>
                                    <textarea name="kpi_items[{{ $loop->index }}][note]" class="form-control">{{ $item->note }}</textarea>
                                </td>
                                <td class="text-center">
                                    <input type="hidden" name="kpi_items[{{ $loop->index }}][is_enabled]" value="0">
                                    <input type="checkbox" name="kpi_items[{{ $loop->index }}][is_enabled]" value="1" class="form-check-input" {{ $item->is_enabled ? 'checked' : '' }}>
                                </td>
                                <td class="text-center kpi-action-cell">
                                    <button
                                        type="button"
                                        class="kpi-trash-btn js-delete-kpi-item"
                                        data-id="{{ $item->id }}"
                                        data-name="{{ $item->name }}"
                                        data-url="{{ route('ky-thuat.luong.settings.kpi-items.destroy', $item->id) }}"
                                        title="Xóa ngay dòng KPI này"
                                    >
                                        <i class="bi bi-trash3"></i>
                                    </button>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="10" class="text-center text-muted py-4">Chưa có dòng KPI nào. Bấm “Thêm dòng KPI”.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="kpi-items-actions">
                <button type="button" class="btn btn-outline-primary btn-pill" onclick="addKpiItemRow()">
                    <i class="bi bi-plus-circle me-1"></i>Thêm dòng KPI
                </button>

                <button type="submit" class="btn-save">
                    <i class="bi bi-save me-1"></i>Lưu dòng KPI chi tiết
                </button>
            </div>
        </form>

<form method="POST" action="{{ route('ky-thuat.luong.settings.save') }}" id="settingsForm">
            @csrf

            <div class="summary-grid">
                <div class="summary-card">
                    <span>Lương cố định</span>
                    <strong class="text-primary" id="basePercentBox">70%</strong>
                </div>
                <div class="summary-card green">
                    <span>Lương KPI</span>
                    <strong class="text-success" id="kpiPercentBox">30%</strong>
                </div>
                <div class="summary-card purple">
                    <span>Tổng tỷ lệ lương</span>
                    <strong id="totalPercentBox">100%</strong>
                    <div class="small mt-1" id="totalPercentNote">Đúng chuẩn 100%</div>
                </div>
            </div>

            <div class="settings-layout">
                <div>
                    <div class="setting-panel">
                        <div class="setting-head head-salary">
                            <div>
                                <h3><i class="bi bi-wallet2 me-1"></i>Tỷ lệ lương</h3>
                                <p>Lương cố định + lương KPI. Tổng nên bằng 100%.</p>
                            </div>
                            <button type="button" class="btn-add" onclick="addSettingItem('salaryList', 'salary')">+ Thêm</button>
                        </div>

                        <div class="setting-list" id="salaryList">
                            @foreach($salarySettings as $setting)
                                @php $percentValue = (float) $setting->setting_value * 100; @endphp
                                <div class="setting-item">
                                    <div class="setting-line-top">
                                        <input type="text" name="settings[{{ $setting->setting_key }}][label]" value="{{ $setting->setting_label }}" class="form-control setting-name">
                                        <div class="percent-wrap">
                                            <input type="number" step="0.01" name="settings[{{ $setting->setting_key }}][value]" value="{{ number_format($percentValue, 2, '.', '') }}" class="form-control setting-percent">
                                            <span>%</span>
                                        </div>
                                        <label class="delete-check" title="Tích để xóa">
                                            <input type="checkbox" name="delete_settings[{{ $setting->setting_key }}]" value="1" class="form-check-input">
                                        </label>
                                    </div>
                                    <div class="setting-key">{{ $setting->setting_key }}</div>
                                    <input type="text" name="settings[{{ $setting->setting_key }}][note]" value="{{ $setting->note }}" class="form-control setting-note" placeholder="Ghi chú">
                                </div>
                            @endforeach
                        </div>
                    </div>

                    <div class="setting-panel">
                        <div class="setting-head head-feedback">
                            <div>
                                <h3><i class="bi bi-chat-square-heart me-1"></i>Feedback khách hàng</h3>
                                <p>Áp dụng riêng cho chỉ tiêu mức độ hài lòng khách hàng.</p>
                            </div>
                            <button type="button" class="btn-add" onclick="addSettingItem('feedbackList', 'feedback')">+ Thêm</button>
                        </div>

                        <div class="setting-list" id="feedbackList">
                            @foreach($feedbackSettings as $setting)
                                @php $percentValue = (float) $setting->setting_value * 100; @endphp
                                <div class="setting-item">
                                    <div class="setting-line-top">
                                        <input type="text" name="settings[{{ $setting->setting_key }}][label]" value="{{ $setting->setting_label }}" class="form-control setting-name">
                                        <div class="percent-wrap">
                                            <input type="number" step="0.01" name="settings[{{ $setting->setting_key }}][value]" value="{{ number_format($percentValue, 2, '.', '') }}" class="form-control setting-percent">
                                            <span>%</span>
                                        </div>
                                        <label class="delete-check" title="Tích để xóa">
                                            <input type="checkbox" name="delete_settings[{{ $setting->setting_key }}]" value="1" class="form-check-input">
                                        </label>
                                    </div>
                                    <div class="setting-key">{{ $setting->setting_key }}</div>
                                    <input type="text" name="settings[{{ $setting->setting_key }}][note]" value="{{ $setting->note }}" class="form-control setting-note" placeholder="Ghi chú">
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>

                <div>
                    <div class="setting-panel">
                        <div class="setting-head head-kpi">
                            <div>
                                <h3><i class="bi bi-sliders me-1"></i>Quy tắc KPI</h3>
                                <p>Các mức cộng/trừ trong bảng KPI kỹ thuật.</p>
                            </div>
                            <button type="button" class="btn-add" onclick="addSettingItem('kpiList', 'kpi')">+ Thêm</button>
                        </div>

                        <div class="setting-list" id="kpiList">
                            @foreach($kpiSettings as $setting)
                                @php $percentValue = (float) $setting->setting_value * 100; @endphp
                                <div class="setting-item">
                                    <div class="setting-line-top">
                                        <input type="text" name="settings[{{ $setting->setting_key }}][label]" value="{{ $setting->setting_label }}" class="form-control setting-name">
                                        <div class="percent-wrap">
                                            <input type="number" step="0.01" name="settings[{{ $setting->setting_key }}][value]" value="{{ number_format($percentValue, 2, '.', '') }}" class="form-control setting-percent">
                                            <span>%</span>
                                        </div>
                                        <label class="delete-check" title="Tích để xóa">
                                            <input type="checkbox" name="delete_settings[{{ $setting->setting_key }}]" value="1" class="form-check-input">
                                        </label>
                                    </div>
                                    <div class="setting-key">{{ $setting->setting_key }}</div>
                                    <input type="text" name="settings[{{ $setting->setting_key }}][note]" value="{{ $setting->note }}" class="form-control setting-note" placeholder="Ghi chú">
                                </div>
                            @endforeach
                        </div>
                    </div>

                    <div class="setting-panel">
                        <div class="setting-head head-other">
                            <div>
                                <h3><i class="bi bi-plus-circle me-1"></i>Mục khác</h3>
                                <p>Các thông số phát sinh thêm. Có thể thêm/xóa tự do.</p>
                            </div>
                            <button type="button" class="btn-add" onclick="addSettingItem('otherList', 'other')">+ Thêm</button>
                        </div>

                        <div class="setting-list" id="otherList">
                            @forelse($otherSettings as $setting)
                                @php $percentValue = (float) $setting->setting_value * 100; @endphp
                                <div class="setting-item">
                                    <div class="setting-line-top">
                                        <input type="text" name="settings[{{ $setting->setting_key }}][label]" value="{{ $setting->setting_label }}" class="form-control setting-name">
                                        <div class="percent-wrap">
                                            <input type="number" step="0.01" name="settings[{{ $setting->setting_key }}][value]" value="{{ number_format($percentValue, 2, '.', '') }}" class="form-control setting-percent">
                                            <span>%</span>
                                        </div>
                                        <label class="delete-check" title="Tích để xóa">
                                            <input type="checkbox" name="delete_settings[{{ $setting->setting_key }}]" value="1" class="form-check-input">
                                        </label>
                                    </div>
                                    <div class="setting-key">{{ $setting->setting_key }}</div>
                                    <input type="text" name="settings[{{ $setting->setting_key }}][note]" value="{{ $setting->note }}" class="form-control setting-note" placeholder="Ghi chú">
                                </div>
                            @empty
                                <div class="empty-list" id="emptyOtherText">Chưa có mục khác.</div>
                            @endforelse
                        </div>
                    </div>
                </div>
            </div>

            <div class="sticky-save">
                <button type="button" class="btn btn-outline-primary btn-pill" onclick="addSettingItem('otherList', 'other')">
                    <i class="bi bi-plus-circle me-1"></i>Thêm mục khác
                </button>
                <button type="submit" class="btn-save">
                    <i class="bi bi-save me-1"></i>Lưu cài đặt
                </button>
            </div>
        </form>
    </div>
</div>

<script>
let newSettingIndex = 0;

function slugify(text) {
    return text.toString()
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .toLowerCase()
        .replace(/đ/g, 'd')
        .replace(/[^a-z0-9]+/g, '_')
        .replace(/^_+|_+$/g, '');
}

function addSettingItem(listId, group) {
    newSettingIndex++;
    const list = document.getElementById(listId);
    const emptyOtherText = document.getElementById('emptyOtherText');
    if (emptyOtherText && listId === 'otherList') emptyOtherText.remove();

    const item = document.createElement('div');
    item.className = 'setting-item new-item';
    item.innerHTML = `
        <div class="setting-line-top">
            <input type="text" name="new_settings[${newSettingIndex}][label]" class="form-control setting-name new-label" placeholder="Tên thông số" oninput="autoFillKey(this, '${group}')">
            <div class="percent-wrap">
                <input type="number" step="0.01" name="new_settings[${newSettingIndex}][value]" value="0" class="form-control">
                <span>%</span>
            </div>
            <button type="button" class="btn btn-sm btn-outline-danger btn-pill" onclick="this.closest('.setting-item').remove()">Xóa</button>
        </div>
        <input type="hidden" name="new_settings[${newSettingIndex}][group]" value="${group}">
        <input type="text" name="new_settings[${newSettingIndex}][key]" class="form-control setting-note new-key my-2" placeholder="custom_ma_thong_so">
        <input type="text" name="new_settings[${newSettingIndex}][note]" class="form-control setting-note" placeholder="Ghi chú">
    `;
    list.appendChild(item);
}

function autoFillKey(input, group) {
    const item = input.closest('.setting-item');
    const keyInput = item.querySelector('.new-key');
    if (!keyInput.dataset.touched) keyInput.value = group + '_' + slugify(input.value);
}

function getSettingPercentByKey(key) {
    const input = document.querySelector(`input[name="settings[${key}][value]"]`);
    return input ? (parseFloat(input.value) || 0) : 0;
}

function updateSalaryPercentSummary() {
    const base = getSettingPercentByKey('base_salary_rate');
    const kpi = getSettingPercentByKey('kpi_salary_rate');
    const total = base + kpi;

    const baseBox = document.getElementById('basePercentBox');
    const kpiBox = document.getElementById('kpiPercentBox');
    const totalBox = document.getElementById('totalPercentBox');
    const note = document.getElementById('totalPercentNote');

    if (!baseBox || !kpiBox || !totalBox || !note) return;

    baseBox.innerText = base.toFixed(2) + '%';
    kpiBox.innerText = kpi.toFixed(2) + '%';
    totalBox.innerText = total.toFixed(2) + '%';
    totalBox.classList.remove('text-success', 'text-danger');

    if (Math.abs(total - 100) < 0.001) {
        totalBox.classList.add('text-success');
        note.innerText = 'Đúng chuẩn 100%';
        note.className = 'small mt-1 text-success fw-bold';
    } else {
        totalBox.classList.add('text-danger');
        note.innerText = 'Cần chỉnh lại để tổng = 100%';
        note.className = 'small mt-1 text-danger fw-bold';
    }
}

document.addEventListener('input', function(e) {
    if (e.target.classList.contains('new-key')) e.target.dataset.touched = '1';
    updateSalaryPercentSummary();
});

updateSalaryPercentSummary();
</script>

<script>
let kpiItemIndex = {{ max(100, $kpiItemRows->count() + 100) }};

function addKpiItemRow() {
    kpiItemIndex++;

    const body = document.getElementById('kpiItemsBody');

    if (!body) return;

    if (body.children.length === 1 && body.children[0].children.length === 1) {
        body.innerHTML = '';
    }

    const tr = document.createElement('tr');

    tr.innerHTML = `
        <td><input type="number" name="kpi_items[${kpiItemIndex}][sort_order]" value="${kpiItemIndex - 100}" class="form-control"></td>
        <td><input type="text" name="kpi_items[${kpiItemIndex}][name]" value="" class="form-control" required placeholder="Tên chỉ tiêu"></td>
        <td><input type="text" name="kpi_items[${kpiItemIndex}][unit]" value="" class="form-control" placeholder="ĐVT"></td>
        <td><input type="number" step="0.01" name="kpi_items[${kpiItemIndex}][plan_value]" value="0" class="form-control"></td>
        <td><input type="number" step="0.01" name="kpi_items[${kpiItemIndex}][actual_value]" value="0" class="form-control"></td>
        <td><input type="number" step="0.01" name="kpi_items[${kpiItemIndex}][weight_percent]" value="10" class="form-control"></td>
        <td>
            <select name="kpi_items[${kpiItemIndex}][calc_type]" class="form-select">
                <option value="actual_div_plan">TH / KH</option>
                <option value="plan_div_actual">KH / TH</option>
                <option value="minus_quality">Trừ lỗi chất lượng</option>
                <option value="minus_safety">Trừ sự cố an toàn</option>
                <option value="minus_equipment">Trừ lỗi thiết bị</option>
                <option value="customer_feedback">Feedback khách hàng</option>
                <option value="success_project">Cộng công trình chốt</option>
                <option value="ot_rule">Quy tắc OT</option>
            </select>
        </td>
        <td><textarea name="kpi_items[${kpiItemIndex}][note]" class="form-control" placeholder="Ghi chú hiển thị dưới chỉ tiêu"></textarea></td>
        <td class="text-center">
            <input type="hidden" name="kpi_items[${kpiItemIndex}][is_enabled]" value="0">
            <input type="checkbox" name="kpi_items[${kpiItemIndex}][is_enabled]" value="1" class="form-check-input" checked>
        </td>
        <td class="text-center">
            <button type="button" class="kpi-trash-btn is-local" onclick="this.closest('tr').remove()" title="Xóa dòng mới">
                <i class="bi bi-trash3"></i>
            </button>
        </td>
    `;

    body.appendChild(tr);
}
</script>


<script>
/* ego-kpi-item-delete-v1 */
document.addEventListener('click', async function (event) {
    const button = event.target.closest('.js-delete-kpi-item');

    if (!button) return;

    event.preventDefault();

    const row = button.closest('tr');
    const name = button.dataset.name || 'dòng KPI này';
    const url = button.dataset.url;

    if (!url) {
        alert('Thiếu URL xóa dòng KPI.');
        return;
    }

    const ok = confirm('Xóa ngay "' + name + '" khỏi danh sách KPI chi tiết?');

    if (!ok) return;

    const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
        || document.querySelector('input[name="_token"]')?.value
        || '';

    button.classList.add('is-loading');
    button.innerHTML = '<i class="bi bi-arrow-repeat"></i>';

    try {
        const response = await fetch(url, {
            method: 'DELETE',
            headers: {
                'X-CSRF-TOKEN': csrf,
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            }
        });

        const data = await response.json().catch(() => ({}));

        if (!response.ok || data.ok === false) {
            throw new Error(data.message || 'Không xóa được dòng KPI.');
        }

        if (row) {
            row.style.transition = '.22s ease';
            row.style.opacity = '0';
            row.style.transform = 'translateX(12px)';

            setTimeout(() => {
                row.remove();

                const body = document.getElementById('kpiItemsBody');
                if (body && body.children.length === 0) {
                    body.innerHTML = '<tr><td colspan="10" class="text-center text-muted py-4">Chưa có dòng KPI nào. Bấm “Thêm dòng KPI”.</td></tr>';
                }
            }, 220);
        }
    } catch (error) {
        alert(error.message || 'Không xóa được dòng KPI.');
        button.classList.remove('is-loading');
        button.innerHTML = '<i class="bi bi-trash3"></i>';
    }
});
/* end ego-kpi-item-delete-v1 */
</script>

@endsection
