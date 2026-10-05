{{-- Dùng chung cho các trang Lương & KPI: CSS bổ sung trên nền giao diện Chấm công + thanh tab điều hướng. --}}
@push('styles')
    <link rel="stylesheet" href="{{ asset('css/ego-attendance-promax.css') }}?v={{ filemtime(public_path('css/ego-attendance-promax.css')) }}">
    <style>
        #egoAttendancePromax .pr-tabs{display:flex;flex-wrap:wrap;gap:6px;margin:0 0 12px}
        #egoAttendancePromax .pr-tabs a{padding:8px 12px;border:1px solid var(--at-line);border-radius:10px;background:#fff;color:#304b5e;font-size:11px;font-weight:800;text-decoration:none}
        #egoAttendancePromax .pr-tabs a.active{border-color:transparent;color:#fff;background:linear-gradient(135deg,var(--at-cyan),var(--at-teal))}
        #egoAttendancePromax .pr-body{padding:14px 16px}
        #egoAttendancePromax .pr-filter{display:flex;flex-wrap:wrap;align-items:end;gap:10px}
        #egoAttendancePromax .pr-filter label,#egoAttendancePromax .pr-field label{display:block;margin-bottom:4px;color:#5b7284;font-size:10px;font-weight:800;text-transform:uppercase;letter-spacing:.03em}
        #egoAttendancePromax .pr-input{width:100%;min-width:0;border:1px solid #d2e2e8;border-radius:9px;padding:7px 9px;font-size:12px;background:#fff;color:#1d3a50}
        #egoAttendancePromax .pr-input--sm{width:96px;padding:5px 7px;font-size:11px}
        #egoAttendancePromax .pr-input--xs{width:70px;padding:5px 6px;font-size:11px}
        #egoAttendancePromax .pr-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:10px}
        #egoAttendancePromax .pr-num{text-align:right;white-space:nowrap;font-variant-numeric:tabular-nums}
        #egoAttendancePromax .pr-muted{color:#7a8f9c;font-size:10px}
        #egoAttendancePromax .pr-strong{font-weight:900;color:var(--at-ink)}
        #egoAttendancePromax .pr-warn{color:#9a6100;font-size:10px;font-weight:700}
        #egoAttendancePromax .pr-table td,#egoAttendancePromax .pr-table th{font-size:11px}
        #egoAttendancePromax .pr-table tfoot td{font-weight:900;background:#f4f9fb}
        #egoAttendancePromax .pr-table tr.pr-dept td{font-weight:900;background:#e9f5f7;color:#0f4c5c;border-top:2px solid #b9d9e2}
        #egoAttendancePromax .pr-sticky{position:sticky;left:0;z-index:1;background:#fff}
        #egoAttendancePromax .pr-table tr:hover td.pr-sticky{background:#f7fcfd}
        #egoAttendancePromax .pr-edit{display:none}
        #egoAttendancePromax .pr-edit.is-open{display:table-row}
        #egoAttendancePromax .pr-edit td{background:#f7fbfc}
        #egoAttendancePromax .pr-hint{margin:0 0 12px;padding:10px 12px;border:1px dashed #b9d9e2;border-radius:10px;background:#f7fcfd;color:#35556b;font-size:11px;line-height:1.55}
        #egoAttendancePromax .pr-section{margin-top:12px}
        #egoAttendancePromax details.pr-details summary{cursor:pointer;color:#087e92;font-weight:800;font-size:11px}
        @media(max-width:1050px){#egoAttendancePromax .pr-grid{grid-template-columns:repeat(2,minmax(0,1fr))}}
    </style>
@endpush

@php
    $prTabs = [
        ['hr.payroll.index', 'Bảng lương', 'bi-cash-coin'],
        ['hr.payroll.profiles', 'Hồ sơ lương', 'bi-person-vcard'],
        ['hr.kpi.index', 'Chấm KPI', 'bi-speedometer2'],
        ['hr.kpi.settings', 'Cài đặt KPI', 'bi-sliders'],
        ['hr.payroll.settings', 'Cài đặt lương', 'bi-gear'],
    ];
@endphp
<nav class="pr-tabs">
    @foreach($prTabs as [$route, $label, $icon])
        <a href="{{ route($route, request()->only('month')) }}" class="{{ request()->routeIs($route) ? 'active' : '' }}"><i class="bi {{ $icon }}"></i> {{ $label }}</a>
    @endforeach
    @if(\Illuminate\Support\Facades\Route::has('ky-thuat.kpis.input'))
        <a href="{{ route('ky-thuat.kpis.input', request()->only('month')) }}"><i class="bi bi-tools"></i> KPI Kỹ sư (Kỹ thuật)</a>
    @endif
</nav>

@if(session('success'))
    <div class="at-alert"><i class="bi bi-check-circle"></i>{{ session('success') }}</div>
@endif
@if(session('error'))
    <div class="at-alert at-alert--danger"><i class="bi bi-exclamation-circle"></i>{{ session('error') }}</div>
@endif
@if($errors->any())
    <div class="at-alert at-alert--danger"><i class="bi bi-exclamation-circle"></i>{{ $errors->first() }}</div>
@endif
