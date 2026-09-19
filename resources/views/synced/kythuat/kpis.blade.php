@extends('layouts.app')

@section('title', 'KPIs Kỹ thuật · EGO Solar')

@section('content')
@php
    $statusLabel = static function ($status) {
        return match ((string) $status) {
            'approved' => 'Đã duyệt',
            'draft' => 'Chờ duyệt',
            'not_scored' => 'Chưa chấm',
            default => $status ? ucfirst((string) $status) : 'Chờ duyệt',
        };
    };

    $kpiTone = static function ($rate) {
        $rate = (float) $rate;
        if ($rate >= 1.00) return 'excellent';
        if ($rate >= 0.90) return 'good';
        if ($rate >= 0.75) return 'warning';
        return 'danger';
    };

    $canManageKpi = auth()->check() && auth()->user()->hasAnyRole([
        'admin', 'manager', 'management', 'technical_manager', 'accounting'
    ]);

    $criteriaCount = count($kpiCriteria ?? []);
    $hasMaterialWaste = collect($kpiCriteria ?? [])->contains(fn ($criterion) => ($criterion['type'] ?? '') === 'material_waste');
@endphp

<style>
    .tkpi-page{
        --tkpi-bg:#f4f7fb;
        --tkpi-card:#ffffff;
        --tkpi-text:#0f172a;
        --tkpi-muted:#64748b;
        --tkpi-line:#e6edf5;
        --tkpi-navy:#0b1f38;
        --tkpi-blue:#2563eb;
        --tkpi-cyan:#0891b2;
        --tkpi-green:#16a34a;
        --tkpi-amber:#d97706;
        --tkpi-red:#dc2626;
        min-height:100vh;
        padding:20px 20px 36px;
        background:
            radial-gradient(circle at 85% 0%, rgba(14,165,233,.08), transparent 30%),
            var(--tkpi-bg);
        color:var(--tkpi-text);
    }
    .tkpi-page *{box-sizing:border-box}
    .tkpi-shell{max-width:1700px;margin:0 auto}

    .tkpi-head{
        display:flex;
        align-items:flex-start;
        justify-content:space-between;
        gap:18px;
        margin-bottom:16px;
    }
    .tkpi-breadcrumb{
        display:flex;
        align-items:center;
        gap:7px;
        color:#7c8aa0;
        font-size:11px;
        font-weight:800;
        margin-bottom:7px;
    }
    .tkpi-breadcrumb i{font-size:10px}
    .tkpi-title{
        margin:0;
        font-size:27px;
        line-height:1.15;
        font-weight:900;
        letter-spacing:-.035em;
    }
    .tkpi-title-line{
        display:flex;
        align-items:center;
        flex-wrap:wrap;
        gap:9px;
    }
    .tkpi-month-pill{
        display:inline-flex;
        align-items:center;
        gap:7px;
        padding:6px 10px;
        border:1px solid #dbe7f3;
        border-radius:999px;
        background:#fff;
        color:#31506f;
        font-size:11px;
        font-weight:850;
    }
    .tkpi-subtitle{
        margin:6px 0 0;
        color:var(--tkpi-muted);
        font-size:12px;
        line-height:1.55;
    }
    .tkpi-actions{display:flex;align-items:center;gap:8px;flex-wrap:wrap;justify-content:flex-end}
    .tkpi-btn{
        min-height:38px;
        display:inline-flex;
        align-items:center;
        justify-content:center;
        gap:7px;
        padding:0 13px;
        border-radius:11px;
        border:1px solid #dce6f0;
        background:#fff;
        color:#24415f;
        text-decoration:none;
        font-size:11.5px;
        font-weight:850;
        transition:.18s ease;
    }
    .tkpi-btn:hover{transform:translateY(-1px);box-shadow:0 8px 22px rgba(15,23,42,.08);color:#0f2945}
    .tkpi-btn.primary{background:#0d355c;border-color:#0d355c;color:#fff}

    .tkpi-summary{
        display:grid;
        grid-template-columns:repeat(6,minmax(0,1fr));
        gap:10px;
        margin-bottom:12px;
    }
    .tkpi-stat{
        position:relative;
        overflow:hidden;
        min-height:108px;
        padding:15px;
        border:1px solid var(--tkpi-line);
        border-radius:16px;
        background:var(--tkpi-card);
        box-shadow:0 9px 26px rgba(15,23,42,.045);
    }
    .tkpi-stat::after{
        content:"";
        position:absolute;
        width:70px;height:70px;
        border-radius:50%;
        right:-20px;top:-25px;
        background:rgba(37,99,235,.055);
    }
    .tkpi-stat-label{
        display:flex;
        align-items:center;
        justify-content:space-between;
        gap:8px;
        color:#60748a;
        font-size:10.5px;
        font-weight:850;
        text-transform:uppercase;
        letter-spacing:.035em;
    }
    .tkpi-stat-icon{
        width:30px;height:30px;
        display:grid;place-items:center;
        border-radius:9px;
        background:#edf5ff;
        color:#2563eb;
        font-size:14px;
    }
    .tkpi-stat-value{
        display:block;
        margin-top:12px;
        font-size:26px;
        line-height:1;
        font-weight:900;
        letter-spacing:-.04em;
        color:#0b2037;
    }
    .tkpi-stat-note{display:block;margin-top:7px;color:#8a98a8;font-size:10.5px;font-weight:650}
    .tkpi-stat.success .tkpi-stat-icon{background:#ecfdf3;color:#16a34a}
    .tkpi-stat.warning .tkpi-stat-icon{background:#fff7e6;color:#d97706}
    .tkpi-stat.danger .tkpi-stat-icon{background:#fff0f0;color:#dc2626}

    .tkpi-criteria{
        display:grid;
        grid-template-columns:repeat(auto-fit,minmax(220px,1fr));
        gap:8px;
        margin-bottom:12px;
    }
    .tkpi-criterion{
        display:flex;
        align-items:center;
        gap:10px;
        min-height:72px;
        padding:11px 12px;
        border:1px solid var(--tkpi-line);
        border-radius:14px;
        background:#fff;
    }
    .tkpi-criterion-icon{
        width:36px;height:36px;flex:0 0 36px;
        display:grid;place-items:center;
        border-radius:11px;
        background:#f0f7ff;
        color:#1764a5;
        font-size:16px;
    }
    .tkpi-criterion strong{display:block;font-size:11.5px;line-height:1.35;font-weight:850;color:#17314a}
    .tkpi-criterion small{display:block;margin-top:3px;color:#8493a5;font-size:10px;line-height:1.3}
    .tkpi-weight{
        margin-left:auto;
        flex:0 0 auto;
        min-width:38px;
        padding:5px 7px;
        text-align:center;
        border-radius:9px;
        background:#0d355c;
        color:#fff;
        font-size:10.5px;
        font-weight:900;
    }

    .tkpi-panel{
        border:1px solid var(--tkpi-line);
        border-radius:16px;
        background:#fff;
        box-shadow:0 10px 30px rgba(15,23,42,.045);
        overflow:hidden;
    }
    .tkpi-filter{
        display:grid;
        grid-template-columns:155px minmax(190px,1fr) 145px minmax(180px,1fr) auto;
        align-items:end;
        gap:9px;
        padding:12px;
        border-bottom:1px solid var(--tkpi-line);
        background:#fbfdff;
    }
    .tkpi-field label{
        display:block;
        margin:0 0 5px;
        color:#5d7085;
        font-size:9.5px;
        font-weight:900;
        text-transform:uppercase;
        letter-spacing:.04em;
    }
    .tkpi-control{
        width:100%;
        min-height:37px;
        padding:0 10px;
        border:1px solid #d9e4ef;
        border-radius:10px;
        outline:none;
        background:#fff;
        color:#18324b;
        font-size:11.5px;
        font-weight:750;
    }
    .tkpi-control:focus{border-color:#8fb8df;box-shadow:0 0 0 3px rgba(37,99,235,.08)}
    .tkpi-search{position:relative}
    .tkpi-search i{position:absolute;left:11px;top:50%;transform:translateY(-50%);color:#8ba0b4}
    .tkpi-search input{padding-left:32px}
    .tkpi-filter-actions{display:flex;gap:7px}
    .tkpi-filter-actions button,.tkpi-filter-actions a{min-height:37px}

    .tkpi-table-wrap{overflow:auto}
    .tkpi-table{
        width:100%;
        min-width:1180px;
        border-collapse:separate;
        border-spacing:0;
        font-size:11px;
    }
    .tkpi-table thead th{
        position:sticky;
        top:0;
        z-index:2;
        padding:10px 11px;
        border-bottom:1px solid #dfe8f1;
        background:#f7fafc;
        color:#60748a;
        font-size:9.5px;
        font-weight:900;
        text-transform:uppercase;
        letter-spacing:.035em;
        white-space:nowrap;
        vertical-align:middle;
    }
    .tkpi-table tbody td{
        padding:11px;
        border-bottom:1px solid #edf2f7;
        color:#29435d;
        vertical-align:middle;
        background:#fff;
    }
    .tkpi-table tbody tr:hover td{background:#fbfdff}
    .tkpi-table tbody tr:last-child td{border-bottom:0}
    .tkpi-rank{
        width:28px;height:28px;
        display:grid;place-items:center;
        border-radius:9px;
        background:#f1f5f9;
        color:#526578;
        font-size:10px;
        font-weight:900;
    }
    .tkpi-rank.top{background:#fff7dc;color:#a16207}
    .tkpi-person{display:flex;align-items:center;gap:9px;min-width:190px}
    .tkpi-avatar{
        width:35px;height:35px;flex:0 0 35px;
        display:grid;place-items:center;
        border-radius:11px;
        background:linear-gradient(145deg,#0d355c,#147b8f);
        color:#fff;
        font-size:12px;
        font-weight:900;
        box-shadow:0 6px 15px rgba(13,53,92,.16);
    }
    .tkpi-person strong{display:block;color:#142d46;font-size:11.5px;font-weight:900;white-space:nowrap}
    .tkpi-person small{display:block;margin-top:3px;color:#8897a7;font-size:9.5px;white-space:nowrap}

    .tkpi-total{min-width:125px}
    .tkpi-total-line{display:flex;align-items:baseline;justify-content:space-between;gap:8px}
    .tkpi-total-line strong{font-size:15px;font-weight:900;letter-spacing:-.025em}
    .tkpi-total-line small{font-size:9px;color:#94a3b8;font-weight:800}
    .tkpi-bar{height:5px;margin-top:6px;border-radius:999px;background:#edf2f7;overflow:hidden}
    .tkpi-bar span{display:block;height:100%;border-radius:inherit;background:#2563eb}
    .tkpi-total.excellent strong{color:#0f8a43}.tkpi-total.excellent .tkpi-bar span{background:#16a34a}
    .tkpi-total.good strong{color:#1764a5}.tkpi-total.good .tkpi-bar span{background:#2563eb}
    .tkpi-total.warning strong{color:#b66a00}.tkpi-total.warning .tkpi-bar span{background:#f59e0b}
    .tkpi-total.danger strong{color:#c92b2b}.tkpi-total.danger .tkpi-bar span{background:#ef4444}

    .tkpi-component{min-width:98px}
    .tkpi-component strong{display:block;color:#18324b;font-size:11.5px;font-weight:900}
    .tkpi-component span{display:block;margin-top:2px;color:#94a3b8;font-size:9px;font-weight:700}
    .tkpi-component.empty strong{color:#a4b1bf;font-weight:750}

    .tkpi-status{
        display:inline-flex;
        align-items:center;
        gap:6px;
        padding:6px 8px;
        border-radius:999px;
        background:#fff7e6;
        color:#9a5b00;
        font-size:9.5px;
        font-weight:900;
        white-space:nowrap;
    }
    .tkpi-status::before{content:"";width:6px;height:6px;border-radius:50%;background:#f59e0b}
    .tkpi-status.approved{background:#ecfdf3;color:#08783b}
    .tkpi-status.approved::before{background:#16a34a}
    .tkpi-status.not-scored{background:#f1f5f9;color:#64748b}
    .tkpi-status.not-scored::before{background:#94a3b8}
    .tkpi-view{
        width:32px;height:32px;
        display:grid;place-items:center;
        border:1px solid #dfe8f1;
        border-radius:9px;
        color:#43627e;
        background:#fff;
        text-decoration:none;
    }
    .tkpi-view:hover{color:#0d355c;background:#f3f8fc}

    .tkpi-empty{
        padding:56px 20px;
        text-align:center;
        color:#7b8da0;
    }
    .tkpi-empty-icon{
        width:54px;height:54px;
        display:grid;place-items:center;
        margin:0 auto 12px;
        border-radius:17px;
        background:#f1f7fb;
        color:#2c6b96;
        font-size:23px;
    }
    .tkpi-empty strong{display:block;color:#2b445d;font-size:13px;font-weight:900}
    .tkpi-empty span{display:block;margin-top:5px;font-size:11px}

    .tkpi-footer-grid{
        display:grid;
        grid-template-columns:1.05fr .95fr;
        gap:10px;
        margin-top:12px;
    }
    .tkpi-rule-card{
        border:1px solid var(--tkpi-line);
        border-radius:15px;
        background:#fff;
        padding:14px;
    }
    .tkpi-rule-head{display:flex;align-items:center;justify-content:space-between;gap:10px;margin-bottom:11px}
    .tkpi-rule-head h3{margin:0;font-size:12.5px;font-weight:900;color:#16314a}
    .tkpi-rule-head span{font-size:9.5px;color:#8a9bad}
    .tkpi-thresholds{display:grid;grid-template-columns:repeat(4,1fr);gap:7px}
    .tkpi-threshold{padding:9px;border-radius:11px;background:#f8fafc;border:1px solid #edf1f5}
    .tkpi-threshold strong{display:block;font-size:11px;font-weight:900;color:#18324b}
    .tkpi-threshold span{display:block;margin-top:3px;font-size:9px;line-height:1.35;color:#8090a2}
    .tkpi-material-scale{display:grid;grid-template-columns:repeat(5,1fr);gap:6px}
    .tkpi-scale-item{padding:8px;border-radius:10px;background:#f8fafc;border:1px solid #edf1f5;text-align:center}
    .tkpi-scale-item strong{display:block;font-size:10.5px;font-weight:900;color:#17344f}
    .tkpi-scale-item span{display:block;margin-top:3px;color:#8a9bad;font-size:8.8px;line-height:1.25}
    .tkpi-penalty-note{
        display:flex;align-items:flex-start;gap:8px;
        margin-top:9px;padding:9px 10px;border-radius:10px;
        background:#fff7f7;border:1px solid #fee2e2;color:#9f2525;
        font-size:9.5px;line-height:1.45;font-weight:700;
    }


    .tkpi-project-count{border:1px solid #cfe1f0;background:#f3f9ff;color:#155b8d;border-radius:9px;min-width:42px;height:30px;padding:0 8px;font-weight:900;font-size:10.5px;display:inline-flex;align-items:center;justify-content:center;gap:5px;cursor:pointer}
    .tkpi-project-count.has-issue{background:#fff6ed;border-color:#f5d7b5;color:#b45309}
    .tkpi-project-dialog{width:min(820px,92vw);border:0;border-radius:16px;padding:0;box-shadow:0 30px 80px rgba(15,23,42,.25)}
    .tkpi-project-dialog::backdrop{background:rgba(15,23,42,.45)}
    .tkpi-dialog-head{display:flex;justify-content:space-between;align-items:flex-start;gap:12px;padding:16px 18px;border-bottom:1px solid #e6edf5;background:#fbfdff}
    .tkpi-dialog-head h3{margin:0;font-size:16px;font-weight:950;color:#14324e}.tkpi-dialog-head p{margin:4px 0 0;font-size:10.5px;color:#75869a}
    .tkpi-dialog-close{width:34px;height:34px;border:1px solid #dbe6ef;border-radius:10px;background:#fff;color:#486078}
    .tkpi-project-list{padding:10px 18px 16px}.tkpi-project-item{display:grid;grid-template-columns:minmax(220px,1fr) 115px 115px 130px auto;gap:10px;align-items:center;padding:10px 0;border-bottom:1px solid #edf2f6}.tkpi-project-item:last-child{border-bottom:0}
    .tkpi-project-main strong{display:block;font-size:11.5px;color:#173852}.tkpi-project-main small{display:block;margin-top:3px;color:#8391a1;font-size:9.8px}.tkpi-project-meta span{display:block;font-size:9px;color:#7c8c9f}.tkpi-project-meta strong{display:block;margin-top:3px;font-size:10.5px;color:#24445f}
    .tkpi-project-issue{display:flex;gap:4px;flex-wrap:wrap}.tkpi-issue-badge{padding:4px 6px;border-radius:999px;background:#fff1e8;color:#b45309;font-size:8.8px;font-weight:850}.tkpi-ok-badge{padding:4px 6px;border-radius:999px;background:#ecfdf3;color:#15803d;font-size:8.8px;font-weight:850}.tkpi-project-open{width:31px;height:31px;border:1px solid #dbe7ef;border-radius:9px;display:grid;place-items:center;color:#17649a;text-decoration:none}
    @media(max-width:850px){.tkpi-project-item{grid-template-columns:1fr 1fr}.tkpi-project-item>*:last-child{justify-self:end}}
    @media (max-width:1350px){
        .tkpi-summary{grid-template-columns:repeat(3,1fr)}
        .tkpi-criteria{grid-template-columns:repeat(3,1fr)}
        .tkpi-filter{grid-template-columns:150px minmax(180px,1fr) 140px minmax(180px,1fr);}
        .tkpi-filter-actions{grid-column:1/-1;justify-content:flex-end}
    }
    @media (max-width:991.98px){
        .tkpi-page{padding:13px 12px 28px}
        .tkpi-head{flex-direction:column}
        .tkpi-actions{width:100%;justify-content:flex-start}
        .tkpi-summary{grid-template-columns:repeat(2,1fr)}
        .tkpi-criteria{grid-template-columns:repeat(2,1fr)}
        .tkpi-footer-grid{grid-template-columns:1fr}
        .tkpi-thresholds{grid-template-columns:repeat(2,1fr)}
    }
    @media (max-width:640px){
        .tkpi-title{font-size:23px}
        .tkpi-summary,.tkpi-criteria{grid-template-columns:1fr}
        .tkpi-filter{grid-template-columns:1fr}
        .tkpi-filter-actions{grid-column:auto;justify-content:stretch}
        .tkpi-filter-actions>*{flex:1}
        .tkpi-thresholds{grid-template-columns:1fr 1fr}
        .tkpi-material-scale{grid-template-columns:1fr 1fr}
    }
</style>

<div class="tkpi-page">
    <div class="tkpi-shell">
        <header class="tkpi-head">
            <div>
                <div class="tkpi-breadcrumb">
                    <span>Kỹ thuật</span><i class="bi bi-chevron-right"></i><span>KPIs</span>
                </div>
                <div class="tkpi-title-line">
                    <h1 class="tkpi-title">KPIs Kỹ thuật</h1>
                    <span class="tkpi-month-pill"><i class="bi bi-calendar3"></i>{{ $monthLabel }}</span>
                </div>
                <p class="tkpi-subtitle">Theo dõi hiệu suất kỹ sư theo {{ $criteriaCount }} tiêu chí đang hoạt động. Thêm, sửa hoặc xóa tiêu chí trong Cấu hình KPI sẽ đồng bộ ngay sang Dashboard và màn hình chấm KPI.</p>
            </div>

            <div class="tkpi-actions">
                @if($canManageKpi && \Illuminate\Support\Facades\Route::has('ky-thuat.luong.settings'))
                    <a href="{{ route('ky-thuat.luong.settings') }}" class="tkpi-btn">
                        <i class="bi bi-sliders"></i>Cấu hình KPI
                    </a>
                @endif
                @if(\Illuminate\Support\Facades\Route::has('ky-thuat.luong.index'))
                    <a href="{{ route('ky-thuat.luong.index') }}" class="tkpi-btn primary">
                        <i class="bi bi-plus-lg"></i>Chấm / cập nhật KPI
                    </a>
                @endif
            </div>
        </header>

        @if(session('success'))
            <div class="alert alert-success border-0 shadow-sm py-2 px-3 mb-3" style="border-radius:12px;font-size:11.5px;font-weight:700">
                <i class="bi bi-check-circle-fill me-2"></i>{{ session('success') }}
            </div>
        @endif
        @if(session('error'))
            <div class="alert alert-danger border-0 shadow-sm py-2 px-3 mb-3" style="border-radius:12px;font-size:11.5px;font-weight:700">
                <i class="bi bi-exclamation-triangle-fill me-2"></i>{{ session('error') }}
            </div>
        @endif

        @if(isset($kpiConfigValid) && !$kpiConfigValid)
            <div class="alert alert-warning border-0 shadow-sm py-2 px-3 mb-3" style="border-radius:12px;font-size:11.5px;font-weight:750">
                <i class="bi bi-exclamation-triangle-fill me-2"></i>
                Tổng trọng số KPI hiện tại là <strong>{{ number_format(($kpiConfigWeight ?? 0) * 100, 2, ',', '.') }}%</strong>. Hệ thống sẽ không cho chấm/cập nhật KPI cho tới khi tổng trọng số bằng đúng 100%.
            </div>
        @endif

        <section class="tkpi-summary">
            <article class="tkpi-stat">
                <div class="tkpi-stat-label"><span>KPI trung bình</span><span class="tkpi-stat-icon"><i class="bi bi-speedometer2"></i></span></div>
                <strong class="tkpi-stat-value">{{ number_format(($summary['avg_kpi'] ?? 0) * 100, 1, ',', '.') }}%</strong>
                <span class="tkpi-stat-note">{{ $summary['technical_people'] ?? 0 }} kỹ sư · {{ $summary['total_records'] ?? 0 }} hồ sơ KPI · {{ $summary['not_scored'] ?? 0 }} chưa chấm</span>
            </article>
            <article class="tkpi-stat success">
                <div class="tkpi-stat-label"><span>Đạt KPI</span><span class="tkpi-stat-icon"><i class="bi bi-check2-circle"></i></span></div>
                <strong class="tkpi-stat-value">{{ number_format($summary['achieved'] ?? 0, 0, ',', '.') }}</strong>
                <span class="tkpi-stat-note">Từ 90% trở lên</span>
            </article>
            <article class="tkpi-stat success">
                <div class="tkpi-stat-label"><span>Vượt KPI</span><span class="tkpi-stat-icon"><i class="bi bi-arrow-up-right-circle"></i></span></div>
                <strong class="tkpi-stat-value">{{ number_format($summary['excellent'] ?? 0, 0, ',', '.') }}</strong>
                <span class="tkpi-stat-note">Từ 100% trở lên</span>
            </article>
            <article class="tkpi-stat warning">
                <div class="tkpi-stat-label"><span>Chờ duyệt</span><span class="tkpi-stat-icon"><i class="bi bi-hourglass-split"></i></span></div>
                <strong class="tkpi-stat-value">{{ number_format($summary['pending'] ?? 0, 0, ',', '.') }}</strong>
                <span class="tkpi-stat-note">Cần trưởng bộ phận xác nhận</span>
            </article>
            <article class="tkpi-stat danger">
                <div class="tkpi-stat-label"><span>Cần cải thiện</span><span class="tkpi-stat-icon"><i class="bi bi-exclamation-circle"></i></span></div>
                <strong class="tkpi-stat-value">{{ number_format($summary['needs_improvement'] ?? 0, 0, ',', '.') }}</strong>
                <span class="tkpi-stat-note">Dưới 75% KPI</span>
            </article>
            <article class="tkpi-stat warning">
                <div class="tkpi-stat-label"><span>CT ảnh hưởng KPI</span><span class="tkpi-stat-icon"><i class="bi bi-building-exclamation"></i></span></div>
                <strong class="tkpi-stat-value">{{ number_format($summary['project_issues'] ?? 0, 0, ',', '.') }}</strong>
                <span class="tkpi-stat-note">Trễ hạn / nghiệm thu / HSE / vật tư</span>
            </article>
        </section>

        <section class="tkpi-criteria">
            @foreach($kpiCriteria as $criterion)
                <article class="tkpi-criterion" title="{{ $criterion['standard'] }}">
                    <span class="tkpi-criterion-icon"><i class="bi {{ $criterion['icon'] }}"></i></span>
                    <div>
                        <strong>{{ $criterion['name'] }}</strong>
                        <small>{{ $criterion['standard'] }}</small>
                    </div>
                    <span class="tkpi-weight">{{ $criterion['weight'] }}%</span>
                </article>
            @endforeach
        </section>

        <section class="tkpi-panel">
            <form method="GET" action="{{ route('ky-thuat.kpis.index') }}" class="tkpi-filter" id="kpiFilterForm">
                <div class="tkpi-field">
                    <label>Tháng KPI</label>
                    <input type="month" name="month" value="{{ $selectedMonth }}" class="tkpi-control">
                </div>
                <div class="tkpi-field">
                    <label>Kỹ sư</label>
                    <select name="user_id" class="tkpi-control">
                        <option value="">Tất cả kỹ sư</option>
                        @foreach($employees as $employee)
                            <option value="{{ $employee->id }}" @selected((int)$selectedUserId === (int)$employee->id)>
                                {{ $employee->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="tkpi-field">
                    <label>Trạng thái</label>
                    <select name="status" class="tkpi-control">
                        <option value="">Tất cả</option>
                        <option value="not_scored" @selected($selectedStatus === 'not_scored')>Chưa chấm</option>
                        <option value="draft" @selected($selectedStatus === 'draft')>Chờ duyệt</option>
                        <option value="approved" @selected($selectedStatus === 'approved')>Đã duyệt</option>
                    </select>
                </div>
                <div class="tkpi-field">
                    <label>Tìm nhanh</label>
                    <div class="tkpi-search">
                        <i class="bi bi-search"></i>
                        <input type="text" id="kpiLiveSearch" class="tkpi-control" placeholder="Tên kỹ sư..." autocomplete="off">
                    </div>
                </div>
                <div class="tkpi-filter-actions">
                    <button type="submit" class="tkpi-btn primary"><i class="bi bi-funnel"></i>Lọc</button>
                    <a href="{{ route('ky-thuat.kpis.index', ['month' => now()->format('Y-m')]) }}" class="tkpi-btn"><i class="bi bi-arrow-counterclockwise"></i>Đặt lại</a>
                </div>
            </form>

            <div class="tkpi-table-wrap">
                <table class="tkpi-table" id="technicalKpiTable">
                    <thead>
                        <tr>
                            <th style="width:48px">#</th>
                            <th>Kỹ sư</th>
                            <th style="width:70px;text-align:center">CT</th>
                            <th>KPI tổng</th>
                            @foreach($kpiCriteria as $criterion)
                                <th title="{{ $criterion['name'] }}">
                                    {{ \Illuminate\Support\Str::limit($criterion['subject'] ?? $criterion['name'], 26) }}
                                    <span class="text-muted">{{ number_format((float)$criterion['weight'], 0, ',', '.') }}%</span>
                                </th>
                            @endforeach
                            <th>Trạng thái</th>
                            <th style="width:58px;text-align:center">Xem</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse(($displayRows ?? $payrolls) as $index => $row)
                            @php
                                $hasKpiRecord = !empty($row->id);
                                $totalRate = $hasKpiRecord ? (float)($row->total_kpi_percent ?? 0) : null;
                                $tone = $hasKpiRecord ? $kpiTone($totalRate) : 'warning';
                                $employeeName = (string)($row->employee_name ?? ('Kỹ sư #'.($row->user_id ?? $row->id)));
                                $initial = \Illuminate\Support\Str::upper(\Illuminate\Support\Str::substr(trim($employeeName), 0, 1));
                                $positionName = (string)($row->position_name ?? 'Kỹ thuật');
                                $breakdown = $row->kpi_breakdown ?? [];
                            @endphp
                            <tr data-kpi-row data-name="{{ \Illuminate\Support\Str::lower(\Illuminate\Support\Str::ascii($employeeName)) }}">
                                <td><span class="tkpi-rank {{ $index < 3 ? 'top' : '' }}">{{ $index + 1 }}</span></td>
                                <td>
                                    <div class="tkpi-person">
                                        <span class="tkpi-avatar">{{ $initial ?: 'K' }}</span>
                                        <div>
                                            <strong>{{ $employeeName }}</strong>
                                            <small>{{ $positionName }} · {{ $row->payroll_month ?? $selectedMonth }}</small>
                                        </div>
                                    </div>
                                </td>
                                <td style="text-align:center">
                                    @if($hasKpiRecord)
                                        <button type="button" class="tkpi-project-count {{ ($row->kpi_project_issue_count ?? 0) > 0 ? 'has-issue' : '' }}" onclick="document.getElementById('kpi-projects-{{ $row->id }}').showModal()" title="Xem công trình đóng góp KPI">
                                            <i class="bi bi-building"></i>{{ (int)($row->kpi_project_count ?? 0) }}
                                        </button>
                                    @else
                                        <span class="tkpi-component empty"><strong>—</strong><span>Chưa chấm</span></span>
                                    @endif
                                </td>
                                <td>
                                    @if($hasKpiRecord)
                                        <div class="tkpi-total {{ $tone }}">
                                            <div class="tkpi-total-line">
                                                <strong>{{ number_format($totalRate * 100, 1, ',', '.') }}%</strong>
                                                <small>{{ $totalRate >= 1 ? 'Vượt' : ($totalRate >= .9 ? 'Đạt' : ($totalRate >= .75 ? 'Cải thiện' : 'Chưa đạt')) }}</small>
                                            </div>
                                            <div class="tkpi-bar"><span style="width:{{ min(100, max(0, $totalRate * 100)) }}%"></span></div>
                                        </div>
                                    @else
                                        <div class="tkpi-component empty">
                                            <strong>—</strong>
                                            <span>Chưa chấm KPI</span>
                                        </div>
                                    @endif
                                </td>

                                @foreach($kpiCriteria as $criterion)
                                    @php $component = $breakdown[$criterion['key']] ?? null; @endphp
                                    <td title="{{ $criterion['name'] }}">
                                        @if($component)
                                            <div class="tkpi-component">
                                                <strong>{{ number_format($component['rate'] ?? 0, 1, ',', '.') }}%</strong>
                                                <span>{{ number_format($component['score'] ?? 0, 1, ',', '.') }} điểm · TS {{ number_format($component['weight'] ?? $criterion['weight'], 0, ',', '.') }}%</span>
                                            </div>
                                        @else
                                            <div class="tkpi-component empty">
                                                <strong>—</strong>
                                                <span>Chưa có dữ liệu</span>
                                            </div>
                                        @endif
                                    </td>
                                @endforeach

                                <td>
                                    <span class="tkpi-status {{ ($row->status ?? '') === 'approved' ? 'approved' : (($row->status ?? '') === 'not_scored' ? 'not-scored' : '') }}">
                                        {{ $statusLabel($row->status ?? 'not_scored') }}
                                    </span>
                                </td>
                                <td style="text-align:center">
                                    @if($hasKpiRecord && \Illuminate\Support\Facades\Route::has('ky-thuat.luong.show'))
                                        <a href="{{ route('ky-thuat.luong.show', $row->id) }}" class="tkpi-view" title="Xem chi tiết KPI">
                                            <i class="bi bi-arrow-up-right"></i>
                                        </a>
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="{{ $criteriaCount + 6 }}">
                                    <div class="tkpi-empty">
                                        <span class="tkpi-empty-icon"><i class="bi bi-bar-chart-line"></i></span>
                                        <strong>Không có nhân sự Kỹ thuật phù hợp bộ lọc</strong>
                                        <span>Kiểm tra phòng ban/role nhân sự hoặc đặt lại bộ lọc.</span>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        @foreach($payrolls as $row)
            <dialog class="tkpi-project-dialog" id="kpi-projects-{{ $row->id }}">
                <div class="tkpi-dialog-head">
                    <div><h3>Công trình đóng góp KPI · {{ $row->employee_name }}</h3><p>{{ $monthLabel }} · {{ (int)($row->kpi_project_count ?? 0) }} công trình · {{ (int)($row->kpi_project_issue_count ?? 0) }} vấn đề cần chú ý</p></div>
                    <button type="button" class="tkpi-dialog-close" onclick="this.closest('dialog').close()"><i class="bi bi-x-lg"></i></button>
                </div>
                <div class="tkpi-project-list">
                    @forelse(($row->kpi_projects ?? collect()) as $projectKpi)
                        <div class="tkpi-project-item">
                            <div class="tkpi-project-main"><strong>{{ $projectKpi['code'] }} · {{ $projectKpi['name'] }}</strong><small>Vai trò: {{ $projectKpi['role'] ?? 'kỹ thuật' }} · {{ implode(', ', $projectKpi['steps'] ?? []) ?: 'Dữ liệu công trình cũ' }}</small></div>
                            <div class="tkpi-project-meta"><span>Hạn</span><strong>{{ $projectKpi['deadline'] ?? '—' }}</strong></div>
                            <div class="tkpi-project-meta"><span>Hoàn thành</span><strong>{{ $projectKpi['completed'] ?? '—' }}</strong></div>
                            <div class="tkpi-project-issue">
                                @if(!empty($projectKpi['issues']))
                                    @foreach($projectKpi['issues'] as $issue)<span class="tkpi-issue-badge">{{ $issue }}</span>@endforeach
                                @elseif(($projectKpi['on_time'] ?? null) === true)
                                    <span class="tkpi-ok-badge">Đúng hạn</span>
                                @else
                                    <span class="tkpi-ok-badge">Không có cảnh báo</span>
                                @endif
                            </div>
                            @if(\Illuminate\Support\Facades\Route::has('ky-thuat.kpis.project'))
                                <a class="tkpi-project-open" href="{{ route('ky-thuat.kpis.project', ['site'=>$projectKpi['site_id'], 'month'=>$selectedMonth]) }}" title="KPI công trình"><i class="bi bi-arrow-up-right"></i></a>
                            @endif
                        </div>
                    @empty
                        <div class="tkpi-empty"><strong>Chưa tìm thấy công trình liên quan trong kỳ.</strong><span>Hãy kiểm tra phân công workflow và ngày hoàn thành công trình.</span></div>
                    @endforelse
                </div>
            </dialog>
        @endforeach

        <section class="tkpi-footer-grid">
            <article class="tkpi-rule-card">
                <div class="tkpi-rule-head">
                    <h3>Quy đổi KPI sang mức hưởng</h3>
                    <span>Theo bảng KPI kỹ sư Solar</span>
                </div>
                <div class="tkpi-thresholds">
                    <div class="tkpi-threshold"><strong>≥ 100%</strong><span>Vượt chỉ tiêu · hưởng 110%–120% quỹ KPI</span></div>
                    <div class="tkpi-threshold"><strong>90%–&lt;100%</strong><span>Đạt yêu cầu · hưởng 100% quỹ KPI</span></div>
                    <div class="tkpi-threshold"><strong>75%–&lt;90%</strong><span>Cần cải thiện · hưởng 80% quỹ KPI</span></div>
                    <div class="tkpi-threshold"><strong>&lt; 75%</strong><span>Không đạt · không hưởng quỹ KPI tháng</span></div>
                </div>
                <div class="tkpi-penalty-note">
                    <i class="bi bi-shield-exclamation"></i>
                    <span>Vi phạm nghiêm trọng về an toàn (ví dụ không đeo dây an toàn khi lên mái) có thể bị trừ trực tiếp 10–20 điểm KPI sau khi được quản lý xác nhận.</span>
                </div>
            </article>

            @if($hasMaterialWaste)
            <article class="tkpi-rule-card">
                <div class="tkpi-rule-head">
                    <h3>Quy đổi hao hụt vật tư</h3>
                    <span>Vật tư tiêu hao = Xuất kho − Thu hồi nguyên vẹn</span>
                </div>
                <div class="tkpi-material-scale">
                    <div class="tkpi-scale-item"><strong>0%</strong><span>120% điểm<br>Xuất sắc</span></div>
                    <div class="tkpi-scale-item"><strong>&gt;0–2%</strong><span>100% điểm<br>Đạt</span></div>
                    <div class="tkpi-scale-item"><strong>&gt;2–4%</strong><span>85% điểm<br>Khá</span></div>
                    <div class="tkpi-scale-item"><strong>&gt;4–6%</strong><span>70% điểm<br>Trung bình</span></div>
                    <div class="tkpi-scale-item"><strong>&gt;6%</strong><span>0% điểm<br>Không đạt</span></div>
                </div>
            </article>
            @endif
        </section>
    </div>
</div>
@endsection

@push('scripts')
<script>
(() => {
    const input = document.getElementById('kpiLiveSearch');
    const rows = [...document.querySelectorAll('[data-kpi-row]')];
    if (!input || !rows.length) return;

    const normalize = (value) => (value || '')
        .toString()
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .toLowerCase()
        .trim();

    input.addEventListener('input', () => {
        const keyword = normalize(input.value);
        rows.forEach(row => {
            row.style.display = !keyword || normalize(row.dataset.name).includes(keyword) ? '' : 'none';
        });
    });
})();
</script>
@endpush
