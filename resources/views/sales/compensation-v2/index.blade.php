@extends('layouts.app')

@section('content')
@php
    $policy = (object) ($policy ?? []);
    $permissions = $permissions ?? ['manage' => false, 'approve' => false, 'lock' => false, 'view_all' => false, 'is_sales' => false];
    $staffRows = collect($staff_rows ?? []);
    $orders = collect($orders ?? []);
    $staffSettings = collect($staff_settings ?? []);
    $salesOptions = collect($sales_options ?? []);
    $adjustments = collect($adjustments ?? []);
    $auditLogs = collect($audit_logs ?? []);
    $totals = $totals ?? [];
    $status = $policy->status ?? 'draft';
    $isLocked = $status === 'locked';
    $canEdit = ($permissions['manage'] ?? false) && !in_array($status, ['approved', 'locked'], true);
    $fmtMoney = fn ($value) => number_format((float) $value, 0, ',', '.') . ' đ';
    $fmtRate = fn ($value) => rtrim(rtrim(number_format((float) $value, 4, ',', '.'), '0'), ',') . '%';
    $fmtDate = function ($value) {
        try { return $value ? \Carbon\Carbon::parse($value)->format('d/m/Y') : '-'; }
        catch (\Throwable) { return $value ?: '-'; }
    };
    $start = \Carbon\Carbon::createFromFormat('Y-m', $month)->startOfMonth();
    $prevMonth = $start->copy()->subMonth()->format('Y-m');
    $nextMonth = $start->copy()->addMonth()->format('Y-m');
    $statusMeta = [
        'draft' => ['label' => 'Bản nháp', 'class' => 'draft', 'desc' => 'Trưởng phòng đang cấu hình'],
        'pending' => ['label' => 'Chờ duyệt', 'class' => 'pending', 'desc' => 'Đang chờ Ban Giám đốc'],
        'approved' => ['label' => 'Đã duyệt', 'class' => 'approved', 'desc' => 'Kế toán có thể khóa tháng'],
        'locked' => ['label' => 'Đã khóa', 'class' => 'locked', 'desc' => 'Số liệu đã chốt bằng snapshot'],
    ];
    $currentStatus = $statusMeta[$status] ?? $statusMeta['draft'];
@endphp

<style>
    .scv2{--teal:#0f766e;--cyan:#06b6d4;--navy:#0f172a;--muted:#64748b;--line:#e2e8f0;--soft:#f8fafc;--green:#16a34a;--orange:#ea580c;--red:#dc2626;font-family:"Be Vietnam Pro",system-ui,-apple-system,sans-serif;color:#172033}
    .scv2 *{box-sizing:border-box}.scv2 a{text-decoration:none}.scv2 button,.scv2 input,.scv2 select,.scv2 textarea{font:inherit}
    .scv2-shell{padding:14px 16px 36px;max-width:1900px;margin:0 auto}.scv2-hero{background:linear-gradient(120deg,#0f4c5c 0%,#0f766e 55%,#06b6d4 100%);color:#fff;border-radius:24px;padding:22px 24px;display:flex;justify-content:space-between;gap:24px;align-items:center;box-shadow:0 18px 42px rgba(15,118,110,.18);position:relative;overflow:hidden}.scv2-hero:after{content:"";position:absolute;width:320px;height:320px;border-radius:50%;right:-100px;top:-190px;background:rgba(255,255,255,.1)}
    .scv2-eyebrow{display:inline-flex;align-items:center;gap:8px;font-size:11px;font-weight:800;letter-spacing:.08em;text-transform:uppercase;background:rgba(255,255,255,.13);border:1px solid rgba(255,255,255,.22);padding:7px 11px;border-radius:999px}.scv2-title{font-size:28px;line-height:1.2;margin:10px 0 5px;font-weight:850}.scv2-sub{font-size:13px;opacity:.88;max-width:720px}.scv2-hero-actions{display:flex;gap:9px;align-items:center;z-index:1;flex-wrap:wrap;justify-content:flex-end}.scv2-btn{border:0;border-radius:12px;padding:10px 14px;font-size:12px;font-weight:800;display:inline-flex;gap:7px;align-items:center;justify-content:center;cursor:pointer;transition:.18s}.scv2-btn:hover{transform:translateY(-1px)}.scv2-btn-white{background:#fff;color:#0f766e}.scv2-btn-glass{background:rgba(255,255,255,.14);color:#fff;border:1px solid rgba(255,255,255,.28)}.scv2-btn-primary{background:#0f766e;color:#fff}.scv2-btn-dark{background:#0f172a;color:#fff}.scv2-btn-danger{background:#fee2e2;color:#b91c1c}.scv2-btn-soft{background:#eef2f7;color:#334155}.scv2-btn-sm{padding:7px 10px;border-radius:9px;font-size:11px}
    .scv2-toolbar{margin-top:12px;background:#fff;border:1px solid var(--line);border-radius:17px;padding:10px;display:flex;align-items:center;justify-content:space-between;gap:12px;box-shadow:0 8px 24px rgba(15,23,42,.045)}.scv2-month-nav,.scv2-filters{display:flex;align-items:center;gap:8px;flex-wrap:wrap}.scv2-icon-btn{width:36px;height:36px;border:1px solid var(--line);border-radius:10px;background:#fff;color:#334155;font-weight:900;display:grid;place-items:center}.scv2-control{height:38px;border:1px solid #dbe3ec;border-radius:10px;background:#fff;padding:0 11px;color:#334155;font-size:12px;outline:none}.scv2-control:focus{border-color:#14b8a6;box-shadow:0 0 0 3px rgba(20,184,166,.1)}.scv2-search{min-width:240px}.scv2-status{display:inline-flex;align-items:center;gap:7px;padding:8px 11px;border-radius:999px;font-size:11px;font-weight:850}.scv2-status:before{content:"";width:8px;height:8px;border-radius:50%;background:currentColor}.scv2-status.draft{background:#f1f5f9;color:#475569}.scv2-status.pending{background:#fff7ed;color:#c2410c}.scv2-status.approved{background:#ecfdf5;color:#15803d}.scv2-status.locked{background:#eff6ff;color:#1d4ed8}
    .scv2-tabs{display:flex;gap:6px;margin:14px 0;background:#eef2f7;padding:5px;border-radius:14px;width:max-content;max-width:100%}.scv2-tab{display:flex;align-items:center;gap:8px;padding:9px 15px;border-radius:10px;font-weight:800;font-size:12px;color:#64748b}.scv2-tab.active{background:#fff;color:#0f766e;box-shadow:0 3px 12px rgba(15,23,42,.08)}.scv2-count{min-width:20px;height:20px;padding:0 6px;border-radius:999px;background:#dff8f4;color:#0f766e;display:inline-grid;place-items:center;font-size:10px}
    .scv2-alert{padding:12px 14px;border-radius:13px;margin:12px 0;font-size:12px;font-weight:700;display:flex;justify-content:space-between;gap:12px}.scv2-alert.success{background:#ecfdf5;color:#166534;border:1px solid #bbf7d0}.scv2-alert.error{background:#fef2f2;color:#991b1b;border:1px solid #fecaca}.scv2-alert.info{background:#eff6ff;color:#1e40af;border:1px solid #bfdbfe}
    .scv2-grid-cards{display:grid;grid-template-columns:repeat(8,minmax(135px,1fr));gap:10px}.scv2-card{background:#fff;border:1px solid var(--line);border-radius:16px;padding:14px;min-width:0;box-shadow:0 6px 18px rgba(15,23,42,.035)}.scv2-card-label{font-size:10px;text-transform:uppercase;letter-spacing:.06em;color:#64748b;font-weight:850}.scv2-card-value{font-size:19px;font-weight:900;color:#0f172a;margin-top:6px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}.scv2-card-note{font-size:10px;color:#94a3b8;margin-top:4px}.scv2-card.emphasis{background:#0f172a;border-color:#0f172a}.scv2-card.emphasis .scv2-card-label,.scv2-card.emphasis .scv2-card-note{color:#94a3b8}.scv2-card.emphasis .scv2-card-value{color:#fff}
    .scv2-section{background:#fff;border:1px solid var(--line);border-radius:18px;margin-top:12px;overflow:hidden;box-shadow:0 6px 18px rgba(15,23,42,.035)}.scv2-section-head{padding:14px 16px;border-bottom:1px solid var(--line);display:flex;justify-content:space-between;gap:14px;align-items:center}.scv2-section-title{font-size:14px;font-weight:900;color:#0f172a}.scv2-section-desc{font-size:11px;color:#64748b;margin-top:3px}.scv2-section-body{padding:16px}.scv2-table-wrap{overflow:auto}.scv2-table{width:100%;border-collapse:separate;border-spacing:0;font-size:11px;min-width:1050px}.scv2-table th{position:sticky;top:0;z-index:2;background:#f8fafc;color:#64748b;text-transform:uppercase;letter-spacing:.04em;font-size:9px;font-weight:900;padding:10px;border-bottom:1px solid var(--line);text-align:left;white-space:nowrap}.scv2-table td{padding:10px;border-bottom:1px solid #edf1f5;vertical-align:middle}.scv2-table tbody tr:hover{background:#fbfefd}.scv2-table tbody tr:last-child td{border-bottom:0}.scv2-name{font-weight:850;color:#172033}.scv2-muted{font-size:10px;color:#7b8798}.scv2-money{font-weight:850;white-space:nowrap}.scv2-money.green{color:#15803d}.scv2-money.red{color:#dc2626}.scv2-badge{display:inline-flex;align-items:center;padding:4px 7px;border-radius:999px;font-size:9px;font-weight:850;white-space:nowrap}.scv2-badge.lead{background:#fff7ed;color:#c2410c}.scv2-badge.member{background:#ecfdf5;color:#15803d}.scv2-badge.retail{background:#eff6ff;color:#1d4ed8}.scv2-badge.other{background:#f1f5f9;color:#475569}.scv2-badge.project{background:#f3e8ff;color:#7e22ce}.scv2-badge.distribution{background:#e6fffb;color:#0f766e}.scv2-progress{height:7px;background:#e8eef4;border-radius:999px;overflow:hidden;min-width:110px}.scv2-progress>span{display:block;height:100%;background:linear-gradient(90deg,#14b8a6,#06b6d4);border-radius:999px;max-width:100%}.scv2-details summary{cursor:pointer;list-style:none}.scv2-details summary::-webkit-details-marker{display:none}.scv2-details[open] summary .scv2-chevron{transform:rotate(180deg)}.scv2-chevron{transition:.18s}.scv2-detail-row td{background:#f8fafc;padding:0!important}.scv2-detail-box{padding:14px 16px;border-top:1px dashed #cbd5e1}.scv2-form-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:12px}.scv2-field label{display:block;font-size:10px;font-weight:850;color:#475569;margin-bottom:5px}.scv2-field input,.scv2-field select,.scv2-field textarea{width:100%;border:1px solid #dbe3ec;border-radius:10px;padding:9px 10px;background:#fff;color:#172033;font-size:12px}.scv2-field textarea{min-height:74px;resize:vertical}.scv2-field.full{grid-column:1/-1}.scv2-check{display:flex;align-items:flex-start;gap:8px;font-size:11px;color:#475569}.scv2-check input{margin-top:2px}.scv2-actions{display:flex;gap:8px;align-items:center;flex-wrap:wrap}
    .scv2-policy-layout{display:grid;grid-template-columns:minmax(0,1fr) 340px;gap:12px}.scv2-policy-block{border:1px solid var(--line);border-radius:15px;padding:15px;margin-bottom:12px}.scv2-policy-block h3{font-size:13px;margin:0 0 4px;color:#0f172a}.scv2-policy-block p{font-size:10px;color:#64748b;margin:0 0 13px}.scv2-workflow{display:grid;gap:9px}.scv2-step{border:1px solid var(--line);border-radius:12px;padding:11px;display:flex;gap:10px}.scv2-step-no{width:28px;height:28px;border-radius:9px;background:#eef2f7;display:grid;place-items:center;font-weight:900;color:#475569;flex:none}.scv2-step.active{border-color:#5eead4;background:#f0fdfa}.scv2-step.active .scv2-step-no{background:#0f766e;color:#fff}.scv2-step-title{font-size:11px;font-weight:900}.scv2-step-desc{font-size:9px;color:#64748b;margin-top:2px}.scv2-sticky-side{position:sticky;top:12px;align-self:start}.scv2-warning-list{display:grid;gap:7px;margin-top:10px}.scv2-warning{font-size:10px;padding:8px 9px;border-radius:9px;background:#fff7ed;color:#9a3412}
    .scv2-empty{text-align:center;padding:32px 15px;color:#64748b}.scv2-empty strong{display:block;color:#334155;margin-bottom:5px}.scv2-audit{display:grid;gap:8px}.scv2-audit-item{border-left:3px solid #cbd5e1;padding:7px 10px;background:#f8fafc;border-radius:0 10px 10px 0}.scv2-audit-action{font-size:10px;font-weight:900;color:#334155}.scv2-audit-meta{font-size:9px;color:#94a3b8;margin-top:2px}
    @media(max-width:1600px){.scv2-grid-cards{grid-template-columns:repeat(4,1fr)}}@media(max-width:1450px){.scv2-grid-cards{grid-template-columns:repeat(4,1fr)}}@media(max-width:1050px){.scv2-policy-layout{grid-template-columns:1fr}.scv2-sticky-side{position:static}.scv2-form-grid{grid-template-columns:repeat(2,1fr)}.scv2-hero{align-items:flex-start;flex-direction:column}.scv2-hero-actions{justify-content:flex-start}.scv2-toolbar{align-items:flex-start;flex-direction:column}}@media(max-width:650px){.scv2-shell{padding:10px}.scv2-hero{border-radius:18px;padding:18px}.scv2-title{font-size:22px}.scv2-grid-cards{grid-template-columns:1fr 1fr}.scv2-form-grid{grid-template-columns:1fr}.scv2-search{min-width:100%;width:100%}.scv2-tabs{width:100%}.scv2-tab{flex:1;justify-content:center;padding:9px 8px}.scv2-tab .tab-text{display:none}}
</style>

<div class="scv2">
    <div class="scv2-shell">
        <section class="scv2-hero">
            <div style="z-index:1">
                <span class="scv2-eyebrow">Sales Compensation 2.0</span>
                <h1 class="scv2-title">Thu nhập & hoa hồng Kinh doanh</h1>
                <div class="scv2-sub">Một công thức duy nhất cho Dashboard, Excel và PDF. Trưởng phòng chỉnh theo từng tháng; Giám đốc duyệt; Kế toán khóa số liệu.</div>
            </div>
            <div class="scv2-hero-actions">
                <span class="scv2-status {{ $currentStatus['class'] }}">{{ $currentStatus['label'] }}</span>
                <a class="scv2-btn scv2-btn-glass" href="{{ route('sales.commissions.export.excel', ['month' => $month]) }}">Xuất Excel</a>
                <a class="scv2-btn scv2-btn-glass" href="{{ route('sales.commissions.export.pdf', ['month' => $month]) }}">Xuất PDF</a>
                @if($permissions['manage'] ?? false)
                    <a class="scv2-btn scv2-btn-white" href="{{ route('sales.commissions.index', ['month' => $month, 'tab' => 'policy']) }}">Cấu hình tháng</a>
                @endif
            </div>
        </section>

        @if(session('success'))<div class="scv2-alert success"><span>{{ session('success') }}</span><span>✓</span></div>@endif
        @if(session('error'))<div class="scv2-alert error"><span>{{ session('error') }}</span><span>!</span></div>@endif
        @if($is_snapshot ?? false)<div class="scv2-alert info"><span>Đang hiển thị snapshot đã khóa của tháng {{ $month }}. Số liệu không đổi dù đơn hàng hoặc chính sách sau đó thay đổi.</span><span>🔒</span></div>@endif

        <div class="scv2-toolbar">
            <div class="scv2-month-nav">
                <a class="scv2-icon-btn" href="{{ route('sales.commissions.index', array_merge(request()->except('month'), ['month' => $prevMonth])) }}">←</a>
                <form method="GET" action="{{ route('sales.commissions.index') }}" class="scv2-month-nav">
                    <input type="hidden" name="tab" value="{{ $tab }}">
                    <input class="scv2-control" type="month" name="month" value="{{ $month }}" onchange="this.form.submit()">
                </form>
                <a class="scv2-icon-btn" href="{{ route('sales.commissions.index', array_merge(request()->except('month'), ['month' => $nextMonth])) }}">→</a>
                <span class="scv2-status {{ $currentStatus['class'] }}" title="{{ $currentStatus['desc'] }}">{{ $currentStatus['label'] }} · v{{ $policy->version ?? 1 }}</span>
            </div>
            @if($tab === 'overview')
                <form method="GET" action="{{ route('sales.commissions.index') }}" class="scv2-filters">
                    <input type="hidden" name="month" value="{{ $month }}"><input type="hidden" name="tab" value="overview">
                    <input class="scv2-control scv2-search" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Tìm mã đơn, khách hàng, sản phẩm...">
                    @if($permissions['view_all'] ?? false)
                    <select class="scv2-control" name="sales_id">
                        <option value="">Tất cả nhân viên</option>
                        @foreach($salesOptions as $option)<option value="{{ $option['id'] }}" @selected((int)($filters['sales_id'] ?? 0)===(int)$option['id'])>{{ $option['name'] }}</option>@endforeach
                    </select>
                    @endif
                    <select class="scv2-control" name="order_type"><option value="">Mọi loại đơn</option><option value="distribution" @selected(($filters['order_type'] ?? '')==='distribution')>Phân phối</option><option value="project" @selected(($filters['order_type'] ?? '')==='project')>Công trình</option></select>
                    <select class="scv2-control" name="customer_group"><option value="">Mọi nhóm khách</option><option value="lead" @selected(($filters['customer_group'] ?? '')==='lead')>Lead/ADS</option><option value="member" @selected(($filters['customer_group'] ?? '')==='member')>Member</option><option value="retail" @selected(($filters['customer_group'] ?? '')==='retail')>Khách lẻ</option><option value="other" @selected(($filters['customer_group'] ?? '')==='other')>Khác</option></select>
                    <button class="scv2-btn scv2-btn-dark" type="submit">Lọc</button>
                </form>
            @endif
        </div>

        <nav class="scv2-tabs">
            <a class="scv2-tab {{ $tab==='overview'?'active':'' }}" href="{{ route('sales.commissions.index', ['month'=>$month,'tab'=>'overview']) }}"><span>▦</span><span class="tab-text">Tổng quan</span><span class="scv2-count">{{ count($orders) }}</span></a>
            <a class="scv2-tab {{ $tab==='policy'?'active':'' }}" href="{{ route('sales.commissions.index', ['month'=>$month,'tab'=>'policy']) }}"><span>⚙</span><span class="tab-text">Chính sách tháng</span></a>
            <a class="scv2-tab {{ $tab==='adjustments'?'active':'' }}" href="{{ route('sales.commissions.index', ['month'=>$month,'tab'=>'adjustments']) }}"><span>±</span><span class="tab-text">Điều chỉnh & lịch sử</span><span class="scv2-count">{{ count($adjustments) }}</span></a>
        </nav>

        @if($tab === 'overview')
            <div class="scv2-grid-cards">
                <div class="scv2-card"><div class="scv2-card-label">Tổng doanh thu</div><div class="scv2-card-value">{{ $fmtMoney($totals['total_order_revenue'] ?? $totals['order_total'] ?? 0) }}</div><div class="scv2-card-note">Giá trị đơn phát sinh trong tháng</div></div>
                <div class="scv2-card"><div class="scv2-card-label">Tổng thu tháng</div><div class="scv2-card-value">{{ $fmtMoney($totals['paid_in_period'] ?? 0) }}</div><div class="scv2-card-note">Theo phiếu thu thực tế</div></div>
                <div class="scv2-card"><div class="scv2-card-label">Tổng công nợ</div><div class="scv2-card-value">{{ $fmtMoney($totals['debt'] ?? 0) }}</div><div class="scv2-card-note">Còn phải thu trên các đơn đang hiển thị</div></div>
                <div class="scv2-card"><div class="scv2-card-label">Cơ sở hoa hồng</div><div class="scv2-card-value">{{ $fmtMoney($totals['commission_base'] ?? $totals['eligible_before_vat'] ?? 0) }}</div><div class="scv2-card-note">{{ ($policy->calculate_on ?? '') === 'paid_before_vat' ? 'Tiền thu quy đổi trước VAT' : 'Theo cấu hình tháng' }}</div></div>
                <div class="scv2-card"><div class="scv2-card-label">Tổng hoa hồng</div><div class="scv2-card-value">{{ $fmtMoney($totals['commission'] ?? 0) }}</div><div class="scv2-card-note">Cá nhân + nhóm</div></div>
                <div class="scv2-card"><div class="scv2-card-label">Thu nhập cố định</div><div class="scv2-card-value">{{ $fmtMoney($totals['fixed_income'] ?? 0) }}</div><div class="scv2-card-note">Lương + phụ cấp đủ điều kiện</div></div>
                <div class="scv2-card"><div class="scv2-card-label">Thưởng / Điều chỉnh</div><div class="scv2-card-value">{{ $fmtMoney(($totals['dealer_bonus'] ?? 0)+($totals['adjustments'] ?? 0)) }}</div><div class="scv2-card-note">Đại lý, thưởng, trừ, truy lĩnh</div></div>
                <div class="scv2-card emphasis"><div class="scv2-card-label">Tổng thu nhập</div><div class="scv2-card-value">{{ $fmtMoney($totals['total_income'] ?? 0) }}</div><div class="scv2-card-note">{{ $totals['staff_count'] ?? 0 }} nhân sự</div></div>
            </div>

            <section class="scv2-section">
                <div class="scv2-section-head"><div><div class="scv2-section-title">Bảng thu nhập nhân sự</div><div class="scv2-section-desc">Sale và Team Leader được tính theo đúng chính sách của tháng đang chọn.</div></div></div>
                <div class="scv2-table-wrap">
                    <table class="scv2-table">
                        <thead><tr><th>#</th><th>Nhân sự</th><th>Vai trò</th><th>Tổng doanh thu</th><th>Thu tháng</th><th>Công nợ</th><th>Cơ sở HH</th><th>Target / Đạt</th><th>Lương cứng</th><th>Phụ cấp</th><th>Hoa hồng</th><th>Thưởng/Điều chỉnh</th><th>Tổng thu nhập</th></tr></thead>
                        <tbody>
                        @forelse($staffRows as $idx => $row)
                            @php $ach = min(100, max(0, (float)$row['achievement_percent'])); @endphp
                            <tr>
                                <td><span class="scv2-badge {{ $idx<3?'member':'other' }}">#{{ $idx+1 }}</span></td>
                                <td><div class="scv2-name">{{ $row['name'] }}</div><div class="scv2-muted">{{ $row['email'] }}</div></td>
                                <td><span class="scv2-badge {{ $row['role_type']==='leader'?'project':'distribution' }}">{{ $row['role_type']==='leader'?'Team Leader':'Sale' }}</span>@if(($row['team_members'] ?? 0)>0)<div class="scv2-muted">{{ $row['team_members'] }} thành viên</div>@endif</td>
                                <td><div class="scv2-money">{{ $fmtMoney($row['total_order_revenue'] ?? 0) }}</div><div class="scv2-muted">{{ $row['role_type']==='leader' ? 'Cá nhân · Nhóm '.$fmtMoney($row['team_order_revenue'] ?? 0) : 'Đơn phát sinh trong tháng' }}</div></td>
                                <td><div class="scv2-money green">{{ $fmtMoney($row['paid_in_period'] ?? 0) }}</div><div class="scv2-muted">{{ $row['role_type']==='leader' ? 'Nhóm '.$fmtMoney($row['team_paid_in_period'] ?? 0) : 'Lũy kế '.$fmtMoney($row['paid_lifetime'] ?? 0) }}</div></td>
                                <td><div class="scv2-money {{ ($row['debt'] ?? 0)>0?'red':'green' }}">{{ $fmtMoney($row['debt'] ?? 0) }}</div><div class="scv2-muted">{{ $row['role_type']==='leader' ? 'Nhóm '.$fmtMoney($row['team_debt'] ?? 0) : 'Còn phải thu' }}</div></td>
                                <td><div class="scv2-money">{{ $fmtMoney($row['commission_base'] ?? 0) }}</div><div class="scv2-muted">PP {{ $fmtMoney($row['distribution_revenue'] ?? 0) }} · CT {{ $fmtMoney($row['project_revenue'] ?? 0) }}</div></td>
                                <td style="min-width:180px"><div style="display:flex;justify-content:space-between;gap:8px"><span class="scv2-muted">{{ $fmtMoney($row['target_revenue']) }}</span><strong>{{ number_format($row['achievement_percent'],1,',','.') }}%</strong></div><div class="scv2-progress"><span style="width:{{ $ach }}%"></span></div><div class="scv2-muted" style="margin-top:4px">Cơ sở KPI {{ $fmtMoney($row['kpi_revenue'] ?? 0) }}</div></td>
                                <td class="scv2-money">{{ $fmtMoney($row['base_salary']) }}</td>
                                <td><div class="scv2-money">{{ $fmtMoney($row['responsibility_allowance']+$row['travel_allowance']) }}</div><div class="scv2-muted">KPI {{ number_format($row['kpi_percent'],0) }}% {{ $row['went_to_market']?'· Có đi thị trường':'· Không đi thị trường' }}</div></td>
                                <td><div class="scv2-money green">{{ $fmtMoney($row['personal_commission']+$row['team_commission']) }}</div>@if($row['held_commission']>0)<div class="scv2-muted" style="color:#c2410c">Giữ {{ $fmtMoney($row['held_commission']) }}</div>@endif</td>
                                <td><div class="scv2-money {{ ($row['adjustment_total']+$row['dealer_bonus'])<0?'red':'green' }}">{{ $fmtMoney($row['adjustment_total']+$row['dealer_bonus']) }}</div><div class="scv2-muted">Đại lý {{ $fmtMoney($row['dealer_bonus']) }}</div></td>
                                <td class="scv2-money" style="font-size:13px;color:#0f766e">{{ $fmtMoney($row['total_income']) }}</td>
                            </tr>
                        @empty<tr><td colspan="13"><div class="scv2-empty"><strong>Chưa có nhân sự được cấu hình</strong>Vào tab Chính sách tháng để thiết lập.</div></td></tr>@endforelse
                        </tbody>
                    </table>
                </div>
            </section>

            <section class="scv2-section">
                <div class="scv2-section-head"><div><div class="scv2-section-title">Chi tiết đơn hàng & tiền thu</div><div class="scv2-section-desc">Hoa hồng được tính theo khoản tiền thực thu trong tháng; không cộng mặc định 15.000đ/tấm pin.</div></div><div class="scv2-muted">{{ count($orders) }} đơn</div></div>
                <div class="scv2-table-wrap">
                    <table class="scv2-table" style="min-width:1750px">
                        <thead><tr><th>Đơn hàng</th><th>Khách / Sale</th><th>Loại</th><th>Giá trị đơn</th><th>Trước VAT</th><th>Đã thu lũy kế</th><th>Thu tháng</th><th>Công nợ</th><th>Cơ sở HH</th><th>Cơ sở KPI</th><th>Tỷ lệ</th><th>Hoa hồng</th><th>Trạng thái</th><th></th></tr></thead>
                        <tbody>
                        @forelse($orders as $row)
                            <tr>
                                <td><div class="scv2-name">{{ $row['order_code'] }}</div><div class="scv2-muted">{{ $fmtDate($row['activity_date']) }} · {{ $row['product_count'] }} SP</div></td>
                                <td><div class="scv2-name">{{ $row['customer_name'] }}</div><div class="scv2-muted">{{ $row['sales_name'] }}</div></td>
                                <td><span class="scv2-badge {{ $row['order_type'] }}">{{ $row['order_type']==='project'?'Công trình':'Phân phối' }}</span><br><span class="scv2-badge {{ $row['customer_group'] }}" style="margin-top:4px">{{ $row['customer_group']==='lead'?'Lead/ADS':($row['customer_group']==='member'?'Member':($row['customer_group']==='retail'?'Khách lẻ':'Khác')) }}</span></td>
                                <td><div class="scv2-money">{{ $fmtMoney($row['order_total']) }}</div><div class="scv2-muted">{{ $row['is_order_in_period']?'Doanh thu tháng':'Đơn tháng trước' }}</div></td>
                                <td class="scv2-money">{{ $fmtMoney($row['order_before_vat']) }}</td>
                                <td><div class="scv2-money green">{{ $fmtMoney($row['paid_lifetime']) }}</div><div class="scv2-muted">Tổng đã thu của đơn</div></td>
                                <td><div class="scv2-money green">{{ $fmtMoney($row['paid_in_period']) }}</div><div class="scv2-muted">Hợp lệ {{ $fmtMoney($row['eligible_paid']) }}</div></td>
                                <td><div class="scv2-money {{ $row['debt']>0?'red':'green' }}">{{ $fmtMoney($row['debt']) }}</div></td>
                                <td class="scv2-money">{{ $fmtMoney($row['commission_base']) }}</td>
                                <td><div class="scv2-money">{{ $fmtMoney($row['kpi_base'] ?? 0) }}</div><div class="scv2-muted">Theo cấu hình KPI tháng</div></td>
                                <td><div class="scv2-money">{{ $fmtRate($row['rate_percent']) }}</div>@if($row['rate_override_percent']!==null)<div class="scv2-muted">Tỷ lệ chỉnh riêng</div>@endif</td>
                                <td><div class="scv2-money green">{{ $fmtMoney($row['commission']) }}</div>@if($row['dealer_bonus']>0)<div class="scv2-muted">+ {{ $fmtMoney($row['dealer_bonus']) }} mở đại lý</div>@endif</td>
                                <td><div class="scv2-muted" style="max-width:220px">{{ $row['commission_note'] }}</div></td>
                                <td>
                                    @if(($permissions['manage'] ?? false) && !$isLocked)
                                        <details class="scv2-details"><summary class="scv2-btn scv2-btn-soft scv2-btn-sm">Điều chỉnh <span class="scv2-chevron">⌄</span></summary>
                                            <div style="position:absolute;right:24px;z-index:20;width:min(720px,90vw);background:#fff;border:1px solid #dbe3ec;border-radius:14px;padding:14px;box-shadow:0 18px 48px rgba(15,23,42,.18)">
                                                <form method="POST" action="{{ route('sales.commissions.overrides.save', $row['order_id']) }}">@csrf
                                                    <input type="hidden" name="period_month" value="{{ $month }}"><input type="hidden" name="sales_id" value="{{ $row['sales_id'] }}">
                                                    <div class="scv2-form-grid">
                                                        <div class="scv2-field"><label>Loại đơn</label><select name="order_type"><option value="distribution" @selected($row['order_type']==='distribution')>Phân phối sản phẩm</option><option value="project" @selected($row['order_type']==='project')>Công trình nhà dân</option></select></div>
                                                        <div class="scv2-field"><label>Nhóm khách</label><select name="customer_group"><option value="lead" @selected($row['customer_group']==='lead')>Lead / ADS</option><option value="member" @selected($row['customer_group']==='member')>Member / Đại lý cũ</option><option value="retail" @selected($row['customer_group']==='retail')>Khách lẻ</option><option value="other" @selected($row['customer_group']==='other')>Khác</option></select></div>
                                                        <div class="scv2-field"><label>Tỷ lệ riêng (%)</label><input type="number" step="0.0001" name="rate_override_percent" value="{{ $row['rate_override_percent'] }}" placeholder="Để trống theo chính sách"></div>
                                                        <div class="scv2-field"><label>Hoa hồng cố định</label><input type="number" step="1000" name="commission_override_amount" value="{{ $row['commission_override_amount'] }}" placeholder="Để trống"></div>
                                                        <div class="scv2-field"><label>Thưởng đại lý riêng</label><input type="number" step="1000" name="new_dealer_bonus_override" value="{{ $row['new_dealer_bonus_override'] }}" placeholder="Theo chính sách"></div>
                                                        <div class="scv2-field" style="display:flex;gap:16px;align-items:end;padding-bottom:8px"><label class="scv2-check"><input type="checkbox" name="is_new_dealer" value="1" @checked($row['is_new_dealer'])> Đại lý mới</label><label class="scv2-check"><input type="checkbox" name="is_excluded" value="1" @checked($row['is_excluded'])> Loại khỏi tính</label></div>
                                                        <div class="scv2-field full"><label>Lý do điều chỉnh *</label><textarea name="reason" required>{{ $row['override_reason'] }}</textarea></div>
                                                    </div>
                                                    <div class="scv2-actions" style="margin-top:10px"><button class="scv2-btn scv2-btn-primary" type="submit">Lưu điều chỉnh</button></div>
                                                </form>
                                                @if($row['override_reason'])<form method="POST" action="{{ route('sales.commissions.overrides.delete', $row['order_id']) }}" style="margin-top:8px">@csrf @method('DELETE')<input type="hidden" name="period_month" value="{{ $month }}"><button class="scv2-btn scv2-btn-danger scv2-btn-sm" type="submit" onclick="return confirm('Xóa điều chỉnh riêng của đơn này?')">Khôi phục mặc định</button></form>@endif
                                            </div>
                                        </details>
                                    @else <span class="scv2-muted">—</span>@endif
                                </td>
                            </tr>
                        @empty<tr><td colspan="14"><div class="scv2-empty"><strong>Không có đơn hàng phù hợp</strong>Thử đổi bộ lọc hoặc kiểm tra dữ liệu phiếu thu.</div></td></tr>@endforelse
                        </tbody>
                    </table>
                </div>
            </section>
        @endif

        @if($tab === 'policy')
            <div class="scv2-policy-layout">
                <div>
                    <form method="POST" action="{{ route('sales.commissions.settings.save') }}">@csrf
                        <input type="hidden" name="period_month" value="{{ $month }}">
                        <section class="scv2-section" style="margin-top:0">
                            <div class="scv2-section-head"><div><div class="scv2-section-title">Chính sách thu nhập tháng {{ $month }}</div><div class="scv2-section-desc">Mọi thay đổi chỉ áp dụng cho tháng này và được lưu phiên bản.</div></div>@if(!$canEdit)<span class="scv2-status {{ $currentStatus['class'] }}">Chỉ xem</span>@endif</div>
                            <div class="scv2-section-body">
                                <div class="scv2-policy-block"><h3>1. Chính sách nhân viên Sale</h3><p>Theo tài liệu: 7 triệu lương cơ bản, 3 triệu phụ cấp trách nhiệm, 2 triệu đi thị trường; dưới 80% không hưởng hoa hồng phân phối.</p>
                                    <div class="scv2-form-grid">
                                        <div class="scv2-field"><label>Lương cơ bản</label><input name="sales_base_salary" type="number" step="1000" value="{{ $policy->sales_base_salary }}" @disabled(!$canEdit)></div>
                                        <div class="scv2-field"><label>Phụ cấp trách nhiệm tối đa</label><input name="sales_responsibility_allowance" type="number" step="1000" value="{{ $policy->sales_responsibility_allowance }}" @disabled(!$canEdit)></div>
                                        <div class="scv2-field"><label>Phụ cấp đi thị trường</label><input name="sales_travel_allowance" type="number" step="1000" value="{{ $policy->sales_travel_allowance }}" @disabled(!$canEdit)></div>
                                        <div class="scv2-field"><label>Target mặc định</label><input name="sales_target_revenue" type="number" step="1000000" value="{{ $policy->sales_target_revenue }}" @disabled(!$canEdit)></div>
                                        <div class="scv2-field"><label>Ngưỡng bắt đầu hưởng (%)</label><input name="sales_threshold_percent" type="number" step="0.01" value="{{ $policy->sales_threshold_percent }}" @disabled(!$canEdit)></div>
                                        <div class="scv2-field"><label>Lead / ADS (%)</label><input name="lead_rate_percent" type="number" step="0.0001" value="{{ $policy->lead_rate_percent }}" @disabled(!$canEdit)></div>
                                        <div class="scv2-field"><label>Member / Đại lý cũ (%)</label><input name="member_rate_percent" type="number" step="0.0001" value="{{ $policy->member_rate_percent }}" @disabled(!$canEdit)></div>
                                        <div class="scv2-field"><label>Khách lẻ (%)</label><input name="retail_rate_percent" type="number" step="0.0001" value="{{ $policy->retail_rate_percent }}" @disabled(!$canEdit)></div>
                                        <div class="scv2-field"><label>Trạng thái khác (%)</label><input name="other_rate_percent" type="number" step="0.0001" value="{{ $policy->other_rate_percent }}" @disabled(!$canEdit)></div>
                                        <div class="scv2-field"><label>Công trình nhà dân (%)</label><input name="project_rate_percent" type="number" step="0.0001" value="{{ $policy->project_rate_percent }}" @disabled(!$canEdit)></div>
                                        <div class="scv2-field"><label>Phần vượt target (%)</label><input name="over_target_rate_percent" type="number" step="0.0001" value="{{ $policy->over_target_rate_percent }}" @disabled(!$canEdit)></div>
                                    </div>
                                </div>
                                <div class="scv2-policy-block"><h3>2. Thưởng phát triển đại lý</h3><p>Chỉ tính khi đơn được đánh dấu “Đại lý mới”, đạt số tiền và số lượng sản phẩm tối thiểu.</p><div class="scv2-form-grid"><div class="scv2-field"><label>Thưởng / đại lý</label><input name="new_dealer_bonus" type="number" step="1000" value="{{ $policy->new_dealer_bonus }}" @disabled(!$canEdit)></div><div class="scv2-field"><label>Đơn tối thiểu</label><input name="new_dealer_min_order" type="number" step="1000000" value="{{ $policy->new_dealer_min_order }}" @disabled(!$canEdit)></div><div class="scv2-field"><label>Số sản phẩm tối thiểu</label><input name="new_dealer_min_products" type="number" min="0" value="{{ $policy->new_dealer_min_products }}" @disabled(!$canEdit)></div></div></div>
                                <div class="scv2-policy-block"><h3>3. Chính sách Team Leader</h3><p>Hoa hồng nhóm tính trên doanh số thu tiền của các Sale được gán cho Team Leader.</p>
                                    <div class="scv2-form-grid">
                                        <div class="scv2-field"><label>Lương cơ bản</label><input name="leader_base_salary" type="number" step="1000" value="{{ $policy->leader_base_salary }}" @disabled(!$canEdit)></div>
                                        <div class="scv2-field"><label>Phụ cấp quản lý tối đa</label><input name="leader_management_allowance" type="number" step="1000" value="{{ $policy->leader_management_allowance }}" @disabled(!$canEdit)></div>
                                        <div class="scv2-field"><label>Target nhóm mặc định</label><input name="leader_target_revenue" type="number" step="1000000" value="{{ $policy->leader_target_revenue }}" @disabled(!$canEdit)></div>
                                        <div class="scv2-field"><label>Ngưỡng nhóm (%)</label><input name="leader_threshold_percent" type="number" step="0.01" value="{{ $policy->leader_threshold_percent }}" @disabled(!$canEdit)></div>
                                        <div class="scv2-field"><label>Hoa hồng nhóm (%)</label><input name="leader_team_rate_percent" type="number" step="0.0001" value="{{ $policy->leader_team_rate_percent }}" @disabled(!$canEdit)></div>
                                        <div class="scv2-field"><label>Phần nhóm vượt target (%)</label><input name="leader_over_target_rate_percent" type="number" step="0.0001" value="{{ $policy->leader_over_target_rate_percent }}" @disabled(!$canEdit)></div>
                                        <div class="scv2-field"><label>Kiểu phụ cấp thị trường</label><select name="leader_travel_mode" @disabled(!$canEdit)><option value="percent" @selected($policy->leader_travel_mode==='percent')>Phần trăm doanh số nhóm</option><option value="fixed" @selected($policy->leader_travel_mode==='fixed')>Số tiền cố định</option></select></div>
                                        <div class="scv2-field"><label>Giá trị phụ cấp</label><input name="leader_travel_value" type="number" step="0.0001" value="{{ $policy->leader_travel_value }}" @disabled(!$canEdit)></div>
                                        <div class="scv2-field full"><label class="scv2-check"><input name="leader_personal_commission" type="checkbox" value="1" @checked($policy->leader_personal_commission) @disabled(!$canEdit)> Team Leader vẫn hưởng hoa hồng trên đơn hàng cá nhân ngoài hoa hồng nhóm.</label></div>
                                    </div>
                                </div>
                                <div class="scv2-policy-block"><h3>4. Điều kiện tính</h3><p>Khuyến nghị giữ mặc định “Tiền thực thu quy đổi trước VAT” và cho phép thanh toán từng phần.</p>
                                    <div class="scv2-form-grid"><div class="scv2-field"><label>KPI doanh thu tính theo</label><select name="kpi_calculate_on" @disabled(!$canEdit)><option value="order_revenue" @selected(($policy->kpi_calculate_on ?? 'commission_base')==='order_revenue')>Tổng doanh thu đơn phát sinh</option><option value="paid_in_period" @selected(($policy->kpi_calculate_on ?? 'commission_base')==='paid_in_period')>Tổng tiền thực thu trong tháng</option><option value="commission_base" @selected(($policy->kpi_calculate_on ?? 'commission_base')==='commission_base')>Cơ sở hoa hồng trước VAT</option></select></div><div class="scv2-field"><label>Cơ sở tính hoa hồng</label><select name="calculate_on" @disabled(!$canEdit)><option value="paid_before_vat" @selected($policy->calculate_on==='paid_before_vat')>Tiền thực thu quy đổi trước VAT</option><option value="paid_after_vat" @selected($policy->calculate_on==='paid_after_vat')>Tiền thực thu sau VAT</option><option value="order_before_vat" @selected($policy->calculate_on==='order_before_vat')>Toàn bộ giá trị đơn trước VAT</option></select></div><div class="scv2-field full"><div style="display:grid;gap:9px"><label class="scv2-check"><input name="include_partial_payment" type="checkbox" value="1" @checked($policy->include_partial_payment) @disabled(!$canEdit)> Tính theo từng khoản thanh toán trong tháng, không bắt buộc thu đủ 100% đơn.</label><label class="scv2-check"><input name="hold_if_debt" type="checkbox" value="1" @checked($policy->hold_if_debt) @disabled(!$canEdit)> Giữ toàn bộ hoa hồng nếu đơn vẫn còn công nợ.</label><label class="scv2-check"><input name="require_approved_policy" type="checkbox" value="1" @checked($policy->require_approved_policy) @disabled(!$canEdit)> Chỉ dùng chính sách sau khi Ban Giám đốc duyệt.</label></div></div><div class="scv2-field full"><label>Ghi chú chính sách</label><textarea name="note" @disabled(!$canEdit)>{{ $policy->note }}</textarea></div><input type="hidden" name="name" value="{{ $policy->name }}"></div>
                                </div>
                                @if($canEdit)<button class="scv2-btn scv2-btn-primary" type="submit">Lưu chính sách & tạo phiên bản mới</button>@endif
                            </div>
                        </section>
                    </form>

                    <section class="scv2-section">
                        <div class="scv2-section-head"><div><div class="scv2-section-title">Cấu hình từng nhân sự</div><div class="scv2-section-desc">Trưởng phòng được chỉnh lương, target, KPI, đi thị trường và gán Sale vào Team Leader theo từng tháng.</div></div></div>
                        <form method="POST" action="{{ route('sales.commissions.staff.save') }}">@csrf<input type="hidden" name="period_month" value="{{ $month }}">
                            <div class="scv2-table-wrap"><table class="scv2-table" style="min-width:1450px"><thead><tr><th>Nhân sự</th><th>Vai trò</th><th>Team Leader</th><th>Lương cứng</th><th>Phụ cấp KPI</th><th>Phụ cấp đi thị trường</th><th>Target</th><th>KPI đạt</th><th>Đi thị trường</th><th>Áp dụng</th><th>Ghi chú</th></tr></thead><tbody>
                            @foreach($staffSettings as $staff)
                                <tr><td><div class="scv2-name">{{ $staff['name'] }}</div><div class="scv2-muted">{{ $staff['email'] }}</div></td>
                                    <td><select class="scv2-control" name="staff[{{ $staff['user_id'] }}][role_type]" @disabled(!$canEdit)><option value="sale" @selected($staff['role_type']==='sale')>Sale</option><option value="leader" @selected($staff['role_type']==='leader')>Team Leader</option></select></td>
                                    <td><select class="scv2-control" name="staff[{{ $staff['user_id'] }}][manager_id]" @disabled(!$canEdit)><option value="">—</option>@foreach($staffSettings->where('role_type','leader') as $leader)<option value="{{ $leader['user_id'] }}" @selected((int)$staff['manager_id']===(int)$leader['user_id'])>{{ $leader['name'] }}</option>@endforeach</select></td>
                                    <td><input class="scv2-control" style="width:135px" type="number" step="1000" name="staff[{{ $staff['user_id'] }}][base_salary]" value="{{ $staff['base_salary'] }}" @disabled(!$canEdit)></td>
                                    <td><input class="scv2-control" style="width:135px" type="number" step="1000" name="staff[{{ $staff['user_id'] }}][responsibility_allowance]" value="{{ $staff['responsibility_allowance'] }}" @disabled(!$canEdit)></td>
                                    <td><input class="scv2-control" style="width:135px" type="number" step="1000" name="staff[{{ $staff['user_id'] }}][travel_allowance]" value="{{ $staff['travel_allowance'] }}" @disabled(!$canEdit)></td>
                                    <td><input class="scv2-control" style="width:150px" type="number" step="1000000" name="staff[{{ $staff['user_id'] }}][target_revenue]" value="{{ $staff['target_revenue'] }}" @disabled(!$canEdit)></td>
                                    <td><input class="scv2-control" style="width:85px" type="number" min="0" max="100" step="0.01" name="staff[{{ $staff['user_id'] }}][kpi_percent]" value="{{ $staff['kpi_percent'] }}" @disabled(!$canEdit)></td>
                                    <td style="text-align:center"><input type="checkbox" name="staff[{{ $staff['user_id'] }}][went_to_market]" value="1" @checked($staff['went_to_market']) @disabled(!$canEdit)></td>
                                    <td style="text-align:center"><input type="checkbox" name="staff[{{ $staff['user_id'] }}][is_active]" value="1" @checked($staff['is_active']) @disabled(!$canEdit)></td>
                                    <td><input class="scv2-control" style="width:210px" name="staff[{{ $staff['user_id'] }}][note]" value="{{ $staff['note'] }}" @disabled(!$canEdit)></td>
                                </tr>
                            @endforeach
                            </tbody></table></div>
                            @if($canEdit)<div class="scv2-section-body" style="border-top:1px solid #e2e8f0"><button class="scv2-btn scv2-btn-primary" type="submit">Lưu cấu hình nhân sự</button></div>@endif
                        </form>
                    </section>
                </div>

                <aside class="scv2-sticky-side">
                    <section class="scv2-section" style="margin-top:0"><div class="scv2-section-head"><div><div class="scv2-section-title">Quy trình phê duyệt</div><div class="scv2-section-desc">{{ $currentStatus['desc'] }}</div></div></div><div class="scv2-section-body">
                        <div class="scv2-workflow">
                            @foreach([['draft','1','Trưởng phòng cấu hình','Chỉnh tỷ lệ, target và nhân sự'],['pending','2','Gửi Ban Giám đốc','Kiểm tra và phê duyệt'],['approved','3','Kế toán đối chiếu','Kiểm tra tiền thu và công nợ'],['locked','4','Khóa bảng thu nhập','Lưu snapshot, không tự thay đổi']] as $step)
                            <div class="scv2-step {{ $status===$step[0]?'active':'' }}"><div class="scv2-step-no">{{ $step[1] }}</div><div><div class="scv2-step-title">{{ $step[2] }}</div><div class="scv2-step-desc">{{ $step[3] }}</div></div></div>
                            @endforeach
                        </div>
                        <div class="scv2-actions" style="margin-top:14px">
                            @if(($permissions['manage'] ?? false) && $status==='draft')
                                <form method="POST" action="{{ route('sales.commissions.policy.copy') }}">@csrf<input type="hidden" name="period_month" value="{{ $month }}"><button class="scv2-btn scv2-btn-soft scv2-btn-sm" onclick="return confirm('Sao chép chính sách tháng trước?')">Sao chép tháng trước</button></form>
                                <form method="POST" action="{{ route('sales.commissions.policy.submit') }}">@csrf<input type="hidden" name="period_month" value="{{ $month }}"><button class="scv2-btn scv2-btn-primary scv2-btn-sm">Gửi duyệt</button></form>
                            @endif
                            @if(($permissions['approve'] ?? false) && $status==='pending')<form method="POST" action="{{ route('sales.commissions.policy.approve') }}">@csrf<input type="hidden" name="period_month" value="{{ $month }}"><button class="scv2-btn scv2-btn-primary scv2-btn-sm">Phê duyệt</button></form>@endif
                            @if((($permissions['approve'] ?? false)||($permissions['lock'] ?? false)) && in_array($status,['pending','approved']))
                                <details class="scv2-details"><summary class="scv2-btn scv2-btn-danger scv2-btn-sm">Trả về nháp</summary><form method="POST" action="{{ route('sales.commissions.policy.reject') }}" style="margin-top:8px">@csrf<input type="hidden" name="period_month" value="{{ $month }}"><textarea class="scv2-control" style="height:70px;width:100%" name="reason" required placeholder="Lý do trả lại"></textarea><button class="scv2-btn scv2-btn-danger scv2-btn-sm" style="margin-top:6px">Xác nhận</button></form></details>
                            @endif
                            @if(($permissions['lock'] ?? false) && $status==='approved')<form method="POST" action="{{ route('sales.commissions.policy.lock') }}">@csrf<input type="hidden" name="period_month" value="{{ $month }}"><button class="scv2-btn scv2-btn-dark scv2-btn-sm" onclick="return confirm('Khóa tháng và lưu snapshot thu nhập?')">Khóa tháng</button></form>@endif
                            @if(($permissions['lock'] ?? false) && $status==='locked')<details class="scv2-details"><summary class="scv2-btn scv2-btn-danger scv2-btn-sm">Mở khóa</summary><form method="POST" action="{{ route('sales.commissions.policy.unlock') }}" style="margin-top:8px">@csrf<input type="hidden" name="period_month" value="{{ $month }}"><textarea class="scv2-control" style="height:70px;width:100%" name="reason" required placeholder="Lý do mở khóa"></textarea><button class="scv2-btn scv2-btn-danger scv2-btn-sm" style="margin-top:6px">Mở khóa</button></form></details>@endif
                        </div>
                    </div></section>
                    <section class="scv2-section"><div class="scv2-section-head"><div class="scv2-section-title">Kiểm tra chính sách</div></div><div class="scv2-section-body"><div class="scv2-warning-list">
                        @if((float)$policy->lead_rate_percent !== .5)<div class="scv2-warning">Lead đang khác mức tham chiếu 0,5%.</div>@endif
                        @if((float)$policy->member_rate_percent !== 1 || (float)$policy->retail_rate_percent !== 1)<div class="scv2-warning">Member/Khách lẻ đang khác mức tham chiếu 1%.</div>@endif
                        @if((float)$policy->project_rate_percent !== 3)<div class="scv2-warning">Công trình đang khác mức tham chiếu 3%.</div>@endif
                        @if($policy->calculate_on==='order_before_vat')<div class="scv2-warning">Đang tính toàn bộ giá trị đơn dù chưa thu tiền.</div>@endif
                        @if(!$policy->include_partial_payment)<div class="scv2-warning">Chỉ tính khi đơn được thu đủ 100%.</div>@endif
                        @if(!$auditLogs->count())<div class="scv2-muted">Chưa có cảnh báo hoặc lịch sử sửa đổi.</div>@endif
                    </div></div></section>
                </aside>
            </div>
        @endif

        @if($tab === 'adjustments')
            <div class="scv2-policy-layout">
                <div>
                    <section class="scv2-section" style="margin-top:0"><div class="scv2-section-head"><div><div class="scv2-section-title">Thưởng, khấu trừ & truy lĩnh</div><div class="scv2-section-desc">Mỗi khoản đều bắt buộc có lý do và lưu người thực hiện.</div></div></div>
                        @if(($permissions['manage'] ?? false) && !$isLocked)
                        <div class="scv2-section-body" style="border-bottom:1px solid #e2e8f0"><form method="POST" action="{{ route('sales.commissions.adjustments.store') }}">@csrf<input type="hidden" name="period_month" value="{{ $month }}"><div class="scv2-form-grid"><div class="scv2-field"><label>Nhân sự</label><select name="user_id" required><option value="">Chọn nhân sự</option>@foreach($salesOptions as $option)<option value="{{ $option['id'] }}">{{ $option['name'] }}</option>@endforeach</select></div><div class="scv2-field"><label>Loại điều chỉnh</label><select name="adjustment_type"><option value="bonus">Thưởng thêm</option><option value="allowance">Phụ cấp bổ sung</option><option value="deduction">Khấu trừ</option><option value="correction">Truy lĩnh/Điều chỉnh</option></select></div><div class="scv2-field"><label>Số tiền</label><input type="number" step="1000" name="amount" required></div><div class="scv2-field"><label>Tiêu đề</label><input name="title" required placeholder="Ví dụ: Thưởng mở đại lý đặc biệt"></div><div class="scv2-field full"><label>Lý do *</label><textarea name="reason" required></textarea></div></div><button class="scv2-btn scv2-btn-primary" type="submit">Thêm điều chỉnh</button></form></div>
                        @endif
                        <div class="scv2-table-wrap"><table class="scv2-table"><thead><tr><th>Ngày</th><th>Nhân sự</th><th>Loại</th><th>Nội dung</th><th>Lý do</th><th>Số tiền</th><th></th></tr></thead><tbody>
                        @forelse($adjustments as $row)<tr><td>{{ $fmtDate($row['created_at']) }}</td><td class="scv2-name">{{ $row['user_name'] ?? '-' }}</td><td><span class="scv2-badge {{ $row['adjustment_type']==='deduction'?'lead':'member' }}">{{ ['bonus'=>'Thưởng','deduction'=>'Khấu trừ','allowance'=>'Phụ cấp','correction'=>'Điều chỉnh'][$row['adjustment_type']] ?? $row['adjustment_type'] }}</span></td><td>{{ $row['title'] }}</td><td class="scv2-muted">{{ $row['reason'] }}</td><td class="scv2-money {{ $row['adjustment_type']==='deduction'?'red':'green' }}">{{ $row['adjustment_type']==='deduction'?'-':'+' }}{{ $fmtMoney($row['amount']) }}</td><td>@if(($permissions['manage']??false)&&!$isLocked)<form method="POST" action="{{ route('sales.commissions.adjustments.destroy', $row['id']) }}">@csrf @method('DELETE')<input type="hidden" name="period_month" value="{{ $month }}"><button class="scv2-btn scv2-btn-danger scv2-btn-sm" onclick="return confirm('Xóa khoản điều chỉnh này?')">Xóa</button></form>@endif</td></tr>@empty<tr><td colspan="7"><div class="scv2-empty"><strong>Chưa có khoản điều chỉnh</strong>Thu nhập đang được tính hoàn toàn theo chính sách tháng.</div></td></tr>@endforelse
                        </tbody></table></div>
                    </section>
                </div>
                <aside class="scv2-sticky-side"><section class="scv2-section" style="margin-top:0"><div class="scv2-section-head"><div><div class="scv2-section-title">Lịch sử thao tác</div><div class="scv2-section-desc">100 hoạt động gần nhất</div></div></div><div class="scv2-section-body"><div class="scv2-audit">
                    @forelse($auditLogs as $log)<div class="scv2-audit-item"><div class="scv2-audit-action">{{ str_replace(['policy.','staff.','override.','adjustment.'],['Chính sách: ','Nhân sự: ','Đơn hàng: ','Điều chỉnh: '],$log['action']) }}</div><div class="scv2-audit-meta">{{ $log['created_by_name'] ?? 'Hệ thống' }} · {{ $fmtDate($log['created_at']) }} @if($log['note']) · {{ $log['note'] }} @endif</div></div>@empty<div class="scv2-empty"><strong>Chưa có lịch sử</strong>Các thao tác mới sẽ xuất hiện tại đây.</div>@endforelse
                </div></div></section></aside>
            </div>
        @endif
    </div>
</div>
@endsection
