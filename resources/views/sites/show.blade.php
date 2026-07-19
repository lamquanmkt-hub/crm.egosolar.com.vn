
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

@section('content')
{{-- EGO_CREATE_MATERIAL_REQUEST_FROM_SITE_START --}}
@php
    $egoUserCreateMr = auth()->user();
    $egoCanCreateMrFromSite = $egoUserCreateMr && method_exists($egoUserCreateMr, 'hasRole') && (
        $egoUserCreateMr->hasRole('admin')
        || $egoUserCreateMr->hasRole('ky_thuat')
        || $egoUserCreateMr->hasRole('sales')
        || $egoUserCreateMr->hasRole('warehouse')
        || $egoUserCreateMr->hasRole('kho')
    );

    $egoSiteIdForMr = $site->id
        ?? $project->id
        ?? $congTrinh->id
        ?? $id
        ?? request()->route('id')
        ?? null;
@endphp

@if($egoCanCreateMrFromSite && $egoSiteIdForMr)
    <div class="d-flex justify-content-end mb-3">
        <a href="{{ route('material-requests.create', ['site_id' => $egoSiteIdForMr]) }}"
           class="btn btn-primary"
           style="border-radius:12px;font-weight:800;">
            <i class="bi bi-plus-circle"></i> Tạo đơn vật tư
        </a>
    </div>
@endif
{{-- EGO_CREATE_MATERIAL_REQUEST_FROM_SITE_END --}}

@php
    use Illuminate\Support\Carbon;

    $actualMaterials = collect($actualMaterials ?? []);
    $paymentTerms = $paymentTerms ?? [];
    $paymentReceipts = $paymentReceipts ?? [];
    $financeSummary = $financeSummary ?? [];
    $systemSummary = $systemSummary ?? [];

    $u = auth()->user();

    $canSeeCost = $u && (
        (method_exists($u, 'hasAnyRole') && $u->hasAnyRole(['admin', 'accounting', 'manager', 'management', 'warehouse']))
        || (method_exists($u, 'can') && ($u->can('finance.view') || $u->can('finance.manage')))
    );

    $money = function ($amount) {
        return number_format((float)($amount ?? 0), 0, ',', '.') . ' đ';
    };

    $fmtQty = function ($number) {
        if ($number === null || $number === '') return '—';
        $number = (float)$number;

        if (floor($number) == $number) {
            return number_format($number, 0, ',', '.');
        }

        return number_format($number, 2, ',', '.');
    };

    $fmtDate = function ($date) {
        if (empty($date)) return '—';

        try {
            return Carbon::parse($date)->format('d/m/Y');
        } catch (\Throwable $e) {
            return (string)$date;
        }
    };

    $fmtDateTime = function ($date) {
        if (empty($date)) return '—';

        try {
            return Carbon::parse($date)->format('d/m/Y H:i');
        } catch (\Throwable $e) {
            return (string)$date;
        }
    };

    $methodLabel = function ($method) {
        return match ((string)$method) {
            'cash' => 'Tiền mặt',
            'bank_transfer' => 'Chuyển khoản',
            'card' => 'Thẻ',
            'momo' => 'MoMo',
            'vnpay' => 'VNPay',
            'other' => 'Khác',
            default => $method ?: '—',
        };
    };

    $cleanNote = function ($note) {
        $note = (string)$note;

        $note = preg_replace(
            '/\[(Thiết bị chính - Trong kho|Vật tư phụ - Trong kho|Thiết bị chính - Ngoài kho|Vật tư phụ - Ngoài kho)\]\s*/u',
            '',
            $note
        );

        return trim($note);
    };

    $materialGroup = function ($item) {
        $note = (string)($item->note ?? '');

        if (str_contains($note, '[Thiết bị chính - Trong kho]')) {
            return 'Thiết bị chính - Trong kho';
        }

        if (str_contains($note, '[Thiết bị chính - Ngoài kho]')) {
            return 'Thiết bị chính - Ngoài kho';
        }

        if (str_contains($note, '[Vật tư phụ - Trong kho]')) {
            return 'Vật tư phụ - Trong kho';
        }

        if (str_contains($note, '[Vật tư phụ - Ngoài kho]')) {
            return 'Vật tư phụ - Ngoài kho';
        }

        if (!empty($item->product_id)) {
            return 'Thiết bị chính - Trong kho';
        }

        return 'Vật tư phụ - Ngoài kho';
    };

    $groupClass = function ($group) {
        return match ($group) {
            'Thiết bị chính - Trong kho' => 'group-main-stock',
            'Thiết bị chính - Ngoài kho' => 'group-main-external',
            'Vật tư phụ - Trong kho' => 'group-sub-stock',
            'Vật tư phụ - Ngoài kho' => 'group-sub-external',
            default => 'group-muted',
        };
    };

    $materialName = function ($item) use ($cleanNote) {
        if (!empty($item->product_id)) {
            return $item->product_name ?? '—';
        }

        $note = $cleanNote($item->note ?? '');

        $parts = array_values(array_filter(array_map('trim', explode('|', $note)), function ($x) {
            return $x !== '';
        }));

        return $parts[0] ?? 'Vật tư ngoài kho';
    };

    $materialUnit = function ($item) {
        if (!empty($item->unit)) {
            return $item->unit;
        }

        if (!empty($item->product_unit)) {
            return $item->product_unit;
        }

        $note = (string)($item->note ?? '');

        $parts = array_values(array_filter(array_map('trim', explode('|', $note)), function ($x) {
            return $x !== '';
        }));

        foreach ($parts as $part) {
            if (preg_match('/^ĐVT:\s*(.*)$/u', $part, $m)) {
                return trim($m[1] ?? '');
            }
        }

        return '—';
    };

    $materialNoteOnly = function ($item) use ($cleanNote) {
        $note = $cleanNote($item->note ?? '');

        if (!empty($item->product_id)) {
            return $note !== '' ? $note : '—';
        }

        $parts = array_values(array_filter(array_map('trim', explode('|', $note)), function ($x) {
            return $x !== '';
        }));

        $result = [];

        foreach ($parts as $index => $part) {
            if ($index === 0) continue;
            if (preg_match('/^ĐVT:\s*/u', $part)) continue;

            $result[] = $part;
        }

        return count($result) ? implode(' | ', $result) : '—';
    };

    $mainMaterials = $actualMaterials->filter(function ($item) use ($materialGroup) {
        return str_starts_with($materialGroup($item), 'Thiết bị chính');
    })->values();

    $subMaterials = $actualMaterials->filter(function ($item) use ($materialGroup) {
        return str_starts_with($materialGroup($item), 'Vật tư phụ');
    })->values();

    $summaryOf = function ($items) {
        return [
            'rows' => $items->count(),
            'qty' => $items->sum(function ($item) {
                return (float)($item->qty ?? 0);
            }),
            'cost' => $items->sum(function ($item) {
                return (float)($item->line_total ?? 0);
            }),
            'stock' => $items->filter(function ($item) {
                return !empty($item->product_id);
            })->count(),
            'external' => $items->filter(function ($item) {
                return empty($item->product_id);
            })->count(),
        ];
    };

    $mainSummary = $summaryOf($mainMaterials);
    $subSummary = $summaryOf($subMaterials);

    $contractAmount = (float)($financeSummary['contract_amount'] ?? ($site->contract_amount ?? 0));
    $receivedAmount = (float)($financeSummary['received_amount'] ?? 0);
    $remainingReceivable = (float)($financeSummary['remaining_receivable'] ?? max(0, $contractAmount - $receivedAmount));

    $materialCost = (float)($financeSummary['material_cost'] ?? 0);
    $actualTotalCost = (float)$actualMaterials->sum(function ($item) {
        return (float)($item->line_total ?? 0);
    });

    if ($materialCost <= 0 && $actualTotalCost > 0) {
        $materialCost = $actualTotalCost;
    }
    $laborCost = (float)($site->labor_cost ?? 0);
    $transportCost = (float)($site->transport_cost ?? 0);
    $otherCost = (float)($site->other_cost ?? 0);
    $siteExtraCost = $laborCost + $transportCost + $otherCost;

    $totalCost = $materialCost + $laborCost + $transportCost + $otherCost;
    $grossProfit = $contractAmount - $totalCost;
    $profit = $grossProfit;

    $profit = $grossProfit;


    $paidPercent = $contractAmount > 0
        ? min(100, max(0, ($receivedAmount / $contractAmount) * 100))
        : 0;

    $costPercent = $contractAmount > 0
        ? min(100, max(0, ($totalCost / $contractAmount) * 100))
        : 0;

    $status = (string)($site->status ?? '');
    $statusInfo = match ($status) {
        'planning' => ['label' => 'Chuẩn bị', 'class' => 'status-muted', 'icon' => 'bi-hourglass-split'],
        'installing' => ['label' => 'Đang lắp đặt', 'class' => 'status-warning', 'icon' => 'bi-tools'],
        'done' => ['label' => 'Hoàn thành', 'class' => 'status-success', 'icon' => 'bi-check2-circle'],
        'warranty' => ['label' => 'Bảo hành', 'class' => 'status-info', 'icon' => 'bi-shield-check'],
        default => ['label' => $status ?: '—', 'class' => 'status-muted', 'icon' => 'bi-dot'],
    };

    $debtLabel = $remainingReceivable > 0 ? 'Còn công nợ' : 'Đã thu đủ';
    $debtClass = $remainingReceivable > 0 ? 'debt-danger' : 'debt-ok';

    $totalTermAmount = 0;
    foreach ($paymentTerms as $term) {
        $totalTermAmount += (float)($term['amount'] ?? 0);
    }

    $systemKwp = (float)($systemSummary['system_kwp'] ?? ($site->system_kwp ?? 0));
    $systemKwAc = (float)($systemSummary['system_kw_ac'] ?? ($site->system_kw_ac ?? 0));
    $pvKwp = (float)($systemSummary['pv_kwp'] ?? $systemKwp);
    $inverterKw = (float)($systemSummary['inverter_kw'] ?? $systemKwAc);
    $batteryKwh = (float)($systemSummary['battery_kwh'] ?? 0);
    $deviceCount = (int)($systemSummary['device_count'] ?? 0);
@endphp

<style>
    :root{
        --ego:#0BC9AA;
        --ego-dark:#08a88f;
        --ego-soft:#ddfbf5;
        --ego-bg:#f3fffc;
        --ink:#0f172a;
        --muted:#64748b;
        --line:#e5f4f0;
    }

    .ego-page{
        background:
            radial-gradient(circle at top left, rgba(11,201,170,.16), transparent 30%),
            linear-gradient(180deg, var(--ego-bg), #fff 55%);
        min-height: calc(100vh - 64px);
    }

    .ego-hero{
        border:1px solid rgba(11,201,170,.24);
        background:
            radial-gradient(900px 420px at 5% 0%, rgba(11,201,170,.22), transparent 55%),
            radial-gradient(700px 360px at 95% 0%, rgba(59,130,246,.10), transparent 58%),
            linear-gradient(180deg, #fff, rgba(243,255,252,.82));
        border-radius:24px;
        box-shadow:0 18px 42px rgba(2,44,34,.08);
        overflow:hidden;
    }

    .ego-title{
        color:var(--ink);
        letter-spacing:-.5px;
        font-weight:900;
    }

    .ego-sub{
        color:var(--muted);
    }

    .ego-chip{
        display:inline-flex;
        gap:7px;
        align-items:center;
        background:rgba(11,201,170,.12);
        border:1px solid rgba(11,201,170,.24);
        padding:7px 11px;
        border-radius:999px;
        color:#0f766e;
        font-weight:800;
        font-size:12px;
    }

    .status-pill{
        display:inline-flex;
        align-items:center;
        gap:6px;
        padding:7px 11px;
        border-radius:999px;
        font-size:12px;
        font-weight:800;
        border:1px solid rgba(15,23,42,.08);
        background:#fff;
    }

    .status-muted{ color:#475569; background:#f8fafc; border-color:#e2e8f0; }
    .status-warning{ color:#b45309; background:#fffbeb; border-color:#fde68a; }
    .status-success{ color:#047857; background:#ecfdf5; border-color:#bbf7d0; }
    .status-info{ color:#1d4ed8; background:#eff6ff; border-color:#bfdbfe; }
    .debt-danger{ color:#dc2626; background:#fff1f2; border-color:#fecdd3; }
    .debt-ok{ color:#047857; background:#ecfdf5; border-color:#bbf7d0; }

    .btn-ego{
        background:linear-gradient(135deg, var(--ego), var(--ego-dark)) !important;
        border:0 !important;
        color:#fff !important;
        border-radius:14px !important;
        box-shadow:0 12px 24px rgba(11,201,170,.24);
        font-weight:800;
    }

    .btn-ego-outline{
        border-color:rgba(11,201,170,.40) !important;
        color:#0f766e !important;
        border-radius:14px !important;
        font-weight:800;
        background:rgba(255,255,255,.78);
    }

    .btn-ego-outline:hover{ background:var(--ego-soft) !important; }

    .kpi{
        border:1px solid var(--line);
        border-radius:18px;
        background:rgba(255,255,255,.95);
        height:100%;
        box-shadow:0 8px 20px rgba(2,44,34,.045);
    }

    .kpi .label{ color:var(--muted); font-size:12px; }
    .kpi .value{ font-weight:900; letter-spacing:-.5px; }
    .kpi .sub{ color:var(--muted); font-size:12px; }

    .ego-card{
        border:1px solid var(--line) !important;
        border-radius:20px !important;
        box-shadow:0 12px 30px rgba(2,44,34,.07) !important;
        overflow:hidden;
        background:rgba(255,255,255,.96);
    }

    .ego-card .card-body{ padding:20px !important; }

    .nav-pills .nav-link{
        border-radius:14px;
        color:#0b3b36;
        font-weight:900;
        background:rgba(255,255,255,.72);
        border:1px solid rgba(15,23,42,.06);
    }

    .nav-pills .nav-link.active{
        background:rgba(11,201,170,.15) !important;
        color:#0f766e !important;
        border:1px solid rgba(11,201,170,.30);
    }

    .info-grid{
        display:grid;
        grid-template-columns:repeat(2, minmax(0, 1fr));
        gap:12px;
    }

    .info-item{
        border:1px solid rgba(15,23,42,.06);
        background:#fff;
        border-radius:16px;
        padding:13px;
    }

    .info-item .label{ color:var(--muted); font-size:12px; margin-bottom:4px; }
    .info-item .value{ font-weight:850; color:var(--ink); }

    .system-card{
        border:1px solid rgba(11,201,170,.20);
        background:linear-gradient(135deg, rgba(11,201,170,.10), rgba(255,255,255,.95));
        border-radius:18px;
        padding:15px;
        height:100%;
    }

    .system-card .icon{
        width:38px;
        height:38px;
        border-radius:14px;
        display:flex;
        align-items:center;
        justify-content:center;
        background:rgba(11,201,170,.14);
        color:#0f766e;
        margin-bottom:8px;
    }

    .system-card .label{ color:var(--muted); font-size:12px; }
    .system-card .value{ font-weight:950; font-size:22px; color:var(--ink); letter-spacing:-.4px; }

    .finance-main{
        border-radius:20px;
        padding:18px;
        background:
            radial-gradient(circle at top right, rgba(11,201,170,.24), transparent 42%),
            linear-gradient(135deg, rgba(11,201,170,.12), rgba(255,255,255,.96));
        border:1px solid rgba(11,201,170,.22);
    }

    .money-big{
        font-size:32px;
        font-weight:950;
        color:#0f766e;
        letter-spacing:-.9px;
    }

    .finance-box,
    .summary-box{
        border:1px solid rgba(15,23,42,.07);
        background:rgba(248,250,252,.90);
        border-radius:17px;
        padding:14px;
        height:100%;
    }

    .finance-box .amount,
    .summary-box .amount{
        font-weight:950;
        font-size:18px;
        letter-spacing:-.3px;
    }

    .progress-soft{
        height:11px;
        background:rgba(15,23,42,.08);
        border-radius:999px;
        overflow:hidden;
    }

    .progress-soft .bar{
        height:100%;
        border-radius:999px;
        background:linear-gradient(90deg, var(--ego), #10b981);
    }

    .progress-soft.cost .bar{
        background:linear-gradient(90deg, #f59e0b, #ef4444);
    }

    .material-summary{
        border:1px solid rgba(11,201,170,.20);
        background:rgba(11,201,170,.07);
        border-radius:18px;
        padding:14px;
        height:100%;
    }

    .material-summary .label{ color:var(--muted); font-size:12px; }
    .material-summary .value{ font-weight:950; font-size:22px; color:var(--ink); }

    .payment-form{
        border:1px solid rgba(11,201,170,.24);
        background:linear-gradient(135deg, rgba(11,201,170,.10), rgba(255,255,255,.96));
        border-radius:18px;
        padding:16px;
    }

    .form-control,
    .form-select{
        border-radius:13px;
        border-color:rgba(15,23,42,.12);
    }

    .form-control:focus,
    .form-select:focus{
        border-color:rgba(11,201,170,.7);
        box-shadow:0 0 0 .2rem rgba(11,201,170,.12);
    }

    .table thead th{
        background:var(--ego-soft) !important;
        border-bottom:1px solid var(--line) !important;
        color:#0b3b36 !important;
        font-weight:900 !important;
        white-space:nowrap;
    }

    .table td{
        border-color:var(--line) !important;
        vertical-align:middle;
    }

    .table-hover tbody tr:hover{ background:rgba(11,201,170,.055) !important; }

    .group-badge{
        display:inline-flex;
        align-items:center;
        gap:6px;
        border-radius:999px;
        padding:6px 10px;
        font-size:12px;
        font-weight:900;
        border:1px solid rgba(15,23,42,.08);
        white-space:nowrap;
    }

    .group-main-stock{ color:#1d4ed8; background:#eff6ff; border-color:#bfdbfe; }
    .group-main-external{ color:#7c3aed; background:#f5f3ff; border-color:#ddd6fe; }
    .group-sub-stock{ color:#047857; background:#ecfdf5; border-color:#bbf7d0; }
    .group-sub-external{ color:#b45309; background:#fffbeb; border-color:#fde68a; }
    .group-muted{ color:#475569; background:#f8fafc; border-color:#e2e8f0; }

    .actual-name{ font-weight:900; color:var(--ink); }
    .actual-sub{ color:var(--muted); font-size:12px; }

    @media (max-width: 991.98px){
        .sticky-top{ position:static !important; }
    }

    @media (max-width: 575.98px){
        .info-grid{ grid-template-columns:1fr; }
        .money-big{ font-size:25px; }
    }
</style>

<div class="container-fluid ego-page p-3 p-md-4">

    @if(session('success'))
        <div class="alert alert-success border-0 shadow-sm" style="border-radius:16px;">
            <i class="bi bi-check-circle"></i> {{ session('success') }}
        </div>
    @endif

    @if(session('error'))
        <div class="alert alert-danger border-0 shadow-sm" style="border-radius:16px;">
            <i class="bi bi-exclamation-triangle"></i> {{ session('error') }}
        </div>
    @endif

    @if ($errors->any())
        <div class="alert alert-danger border-0 shadow-sm" style="border-radius:16px;">
            <div class="fw-bold mb-1">
                <i class="bi bi-exclamation-triangle"></i> Vui lòng kiểm tra lại:
            </div>
            <ul class="mb-0">
                @foreach ($errors->all() as $e)
                    <li>{{ $e }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="ego-hero p-3 p-md-4 mb-3">
        <div class="d-flex flex-wrap justify-content-between align-items-start gap-2">
            <div>
                <div class="d-flex flex-wrap align-items-center gap-2 mb-1">
                    <h3 class="ego-title mb-0">Chi tiết công trình</h3>
                    <span class="ego-chip">#{{ $site->id }}</span>

                    @if($status)
                        <span class="status-pill {{ $statusInfo['class'] }}">
                            <i class="bi {{ $statusInfo['icon'] }}"></i> {{ $statusInfo['label'] }}
                        </span>
                    @endif

                    @if($canSeeCost)
                        <span class="status-pill {{ $debtClass }}">
                            <i class="bi {{ $remainingReceivable > 0 ? 'bi-exclamation-circle' : 'bi-check-circle' }}"></i>
                            {{ $debtLabel }}
                        </span>
                    @endif
                </div>

                <div class="ego-sub fw-semibold">{{ $site->name ?? '—' }}</div>

                <div class="small ego-sub mt-1">
                    <i class="bi bi-geo-alt"></i> {{ $site->address ?? '—' }}
                </div>
            </div>

            <div class="d-flex flex-wrap gap-2">
                <a href="{{ url('/cong-trinh/'.$site->id.'/sua') }}" class="btn btn-ego">
                    <i class="bi bi-pencil-square"></i> Sửa
                </a>

                <a href="{{ url('/cong-trinh') }}" class="btn btn-ego-outline">
                    <i class="bi bi-arrow-left"></i> Quay lại
                </a>
            </div>
        </div>

        <div class="row g-3 mt-2">
            <div class="col-12 col-md-3">
                <div class="kpi p-3">
                    <div class="label">Hệ DC</div>
                    <div class="value h4 mb-0 text-success">{{ $fmtQty($systemKwp) }} kWp</div>
                    <div class="sub">Công suất tấm pin</div>
                </div>
            </div>

            <div class="col-12 col-md-3">
                <div class="kpi p-3">
                    <div class="label">Hệ AC / Inverter</div>
                    <div class="value h4 mb-0 text-primary">{{ $fmtQty($inverterKw) }} kW</div>
                    <div class="sub">Công suất inverter</div>
                </div>
            </div>

            <div class="col-12 col-md-3">
                <div class="kpi p-3">
                    <div class="label">Lưu trữ</div>
                    <div class="value h4 mb-0 text-warning">{{ $fmtQty($batteryKwh) }} kWh</div>
                    <div class="sub">Dung lượng pin lưu trữ</div>
                </div>
            </div>

            <div class="col-12 col-md-3">
                <div class="kpi p-3">
                    <div class="label">Bảo hành đến</div>
                    <div class="value h4 mb-0 text-info">{{ $fmtDate($systemSummary['warranty_to'] ?? ($site->warranty_to ?? null)) }}</div>
                    <div class="sub">Kỹ thuật: {{ $systemSummary['technician_name'] ?: ($site->technician_name ?? '—') }}</div>
                </div>
            </div>
        </div>
    </div>

    <ul class="nav nav-pills gap-2 mb-3" id="siteTabs" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active" id="tab-overview" data-bs-toggle="pill" data-bs-target="#pane-overview" type="button" role="tab">
                <i class="bi bi-grid-1x2"></i> Tổng quan
            </button>
        </li>

        <li class="nav-item" role="presentation">
            <button class="nav-link" id="tab-finance" data-bs-toggle="pill" data-bs-target="#pane-finance" type="button" role="tab">
                <i class="bi bi-cash-coin"></i> Tài chính
            </button>
        </li>

        <li class="nav-item" role="presentation">
            <button class="nav-link" id="tab-main-materials" data-bs-toggle="pill" data-bs-target="#pane-main-materials" type="button" role="tab">
                <i class="bi bi-cpu"></i> Thiết bị chính
            </button>
        </li>

        <li class="nav-item" role="presentation">
            <button class="nav-link" id="tab-sub-materials" data-bs-toggle="pill" data-bs-target="#pane-sub-materials" type="button" role="tab">
                <i class="bi bi-tools"></i> Vật tư phụ
            </button>
        </li>
    </ul>

    <div class="tab-content" id="siteTabsContent">

        <div class="tab-pane fade show active" id="pane-overview" role="tabpanel">
            <div class="row g-3">
                <div class="col-12 col-lg-8">
                    <div class="card ego-card mb-3">
                        <div class="card-body">
                            <h5 class="mb-3">
                                <i class="bi bi-lightning-charge"></i> Tổng quan hệ thống
                            </h5>

                            <div class="row g-3">
                                <div class="col-6 col-md-3">
                                    <div class="system-card">
                                        <div class="icon"><i class="bi bi-sun"></i></div>
                                        <div class="label">PV / DC</div>
                                        <div class="value">{{ $fmtQty($pvKwp) }}</div>
                                        <div class="small text-muted">kWp</div>
                                    </div>
                                </div>

                                <div class="col-6 col-md-3">
                                    <div class="system-card">
                                        <div class="icon"><i class="bi bi-cpu"></i></div>
                                        <div class="label">Inverter / AC</div>
                                        <div class="value">{{ $fmtQty($inverterKw) }}</div>
                                        <div class="small text-muted">kW</div>
                                    </div>
                                </div>

                                <div class="col-6 col-md-3">
                                    <div class="system-card">
                                        <div class="icon"><i class="bi bi-battery-charging"></i></div>
                                        <div class="label">Pin lưu trữ</div>
                                        <div class="value">{{ $fmtQty($batteryKwh) }}</div>
                                        <div class="small text-muted">kWh</div>
                                    </div>
                                </div>

                                <div class="col-6 col-md-3">
                                    <div class="system-card">
                                        <div class="icon"><i class="bi bi-hdd-stack"></i></div>
                                        <div class="label">Thiết bị cấu hình</div>
                                        <div class="value">{{ $deviceCount }}</div>
                                        <div class="small text-muted">dòng</div>
                                    </div>
                                </div>
                            </div>

                            <hr>

                            <div class="info-grid">
                                <div class="info-item">
                                    <div class="label">Loại hệ</div>
                                    <div class="value">{{ ['on_grid'=>'On-grid','hybrid'=>'Hybrid','off_grid'=>'Off-grid','other'=>'Khác'][$systemSummary['system_type'] ?: ($site->system_type ?? '')] ?? ($systemSummary['system_type'] ?: ($site->system_type ?? '—')) }}</div>
                                </div>

                                <div class="info-item">
                                    <div class="label">Pha / Điện áp</div>
                                    <div class="value">{{ $systemSummary['phase'] ?: ($site->phase ?? '—') }}</div>
                                </div>

                                <div class="info-item">
                                    <div class="label">Ngày lắp đặt</div>
                                    <div class="value">{{ $fmtDate($systemSummary['installed_at'] ?? ($site->installed_at ?? null)) }}</div>
                                </div>

                                <div class="info-item">
                                    <div class="label">Bảo hành đến</div>
                                    <div class="value">{{ $fmtDate($systemSummary['warranty_to'] ?? ($site->warranty_to ?? null)) }}</div>
                                </div>

                                <div class="info-item">
                                    <div class="label">Người phụ trách kỹ thuật</div>
                                    <div class="value">{{ $systemSummary['technician_name'] ?: ($site->technician_name ?? '—') }}</div>
                                </div>

                                <div class="info-item">
                                    <div class="label">Giai đoạn</div>
                                    <div class="value">{{ $systemSummary['stage'] ?: ($site->stage ?? '—') }}</div>
                                </div>

                                <div class="info-item">
                                    <div class="label">Người liên hệ</div>
                                    <div class="value">{{ $site->contact_name ?? '—' }}</div>
                                </div>

                                <div class="info-item">
                                    <div class="label">SĐT liên hệ</div>
                                    <div class="value">{{ $site->contact_phone ?? '—' }}</div>
                                </div>

                                <div class="info-item" style="grid-column:1/-1;">
                                    <div class="label">Monitoring</div>

                                    <div class="d-flex flex-wrap gap-2 align-items-center">
                                        @if(!empty($systemSummary['monitoring_link']) || !empty($site->monitoring_link))
                                            <a href="{{ $systemSummary['monitoring_link'] ?: $site->monitoring_link }}" target="_blank" class="btn btn-sm btn-ego-outline">
                                                <i class="bi bi-box-arrow-up-right"></i> Mở monitoring
                                            </a>
                                        @endif

                                        <span class="small text-muted">Tài khoản:</span>
                                        <span class="fw-bold">{{ $systemSummary['monitoring_account'] ?: ($site->monitoring_account ?? '—') }}</span>
                                    </div>
                                </div>

                                <div class="info-item" style="grid-column:1/-1;">
                                    <div class="label">Ghi chú</div>
                                    <div class="value">{{ $site->note ?? '—' }}</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="card ego-card">
                        <div class="card-body">
                            <h5 class="mb-3">
                                <i class="bi bi-diagram-3"></i> Vật tư theo đơn đã xuất kho
                            </h5>

                            <div class="row g-2">
                                <div class="col-md-6">
                                    <div class="summary-box">
                                        <div class="text-muted small">Thiết bị chính</div>
                                        <div class="amount">{{ $mainSummary['rows'] }} dòng</div>
                                        <div class="small text-muted">
                                            {{ $mainSummary['stock'] }} trong kho / {{ $mainSummary['external'] }} ngoài kho
                                        </div>

                                        @if($canSeeCost)
                                            <div class="fw-bold text-success mt-1">{{ $money($mainSummary['cost']) }}</div>
                                        @endif

                                        <button class="btn btn-sm btn-ego-outline mt-2" type="button"
                                                onclick="bootstrap.Tab.getOrCreateInstance(document.querySelector('#tab-main-materials')).show()">
                                            Xem thiết bị chính
                                        </button>
                                    </div>
                                </div>

                                <div class="col-md-6">
                                    <div class="summary-box">
                                        <div class="text-muted small">Vật tư phụ</div>
                                        <div class="amount">{{ $subSummary['rows'] }} dòng</div>
                                        <div class="small text-muted">
                                            {{ $subSummary['stock'] }} trong kho / {{ $subSummary['external'] }} ngoài kho
                                        </div>

                                        @if($canSeeCost)
                                            <div class="fw-bold text-success mt-1">{{ $money($subSummary['cost']) }}</div>
                                        @endif

                                        <button class="btn btn-sm btn-ego-outline mt-2" type="button"
                                                onclick="bootstrap.Tab.getOrCreateInstance(document.querySelector('#tab-sub-materials')).show()">
                                            Xem vật tư phụ
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <div class="small text-muted mt-3">
                                Dữ liệu 2 tab này lấy từ các <strong>Đơn vật tư đã kho duyệt / xuất kho</strong> của công trình.
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-12 col-lg-4">
                    <div class="card ego-card mb-3">
                        <div class="card-body">
                            <h5 class="mb-3">
                                <i class="bi bi-cash-coin"></i> Doanh thu công trình
                            </h5>

                            <div class="finance-main mb-3">
                                <div class="text-muted small">Tổng doanh thu dự án</div>
                                <div class="money-big">{{ $money($contractAmount) }}</div>

                                <div class="d-flex justify-content-between small mt-2">
                                    <span class="text-muted">Đã thu</span>
                                    <strong>{{ $money($receivedAmount) }}</strong>
                                </div>

                                <div class="progress-soft mt-2">
                                    <div class="bar" style="width:{{ $paidPercent }}%"></div>
                                </div>
                            </div>

                            @if($canSeeCost)
                                <div class="row g-2">
                                    <div class="col-6">
                                        <div class="finance-box">
                                            <div class="text-muted small">Công nợ</div>
                                            <div class="amount {{ $remainingReceivable > 0 ? 'text-danger' : 'text-success' }}">
                                                {{ $money($remainingReceivable) }}
                                            </div>
                                        </div>
                                    </div>

                                    <div class="col-6">
                                        <div class="finance-box">
                                            <div class="text-muted small">Chi phí vật tư</div>
                                            <div class="amount">{{ $money($materialCost) }}</div>
                                        </div>
                                    </div>

                                    <div class="col-6">
                                        <div class="finance-box">
                                            <div class="text-muted small">Nhân công</div>
                                            <div class="amount">{{ $money($laborCost) }}</div>
                                        </div>
                                    </div>

                                    <div class="col-6">
                                        <div class="finance-box">
                                            <div class="text-muted small">Vận chuyển</div>
                                            <div class="amount">{{ $money($transportCost) }}</div>
                                        </div>
                                    </div>

                                    <div class="col-6">
                                        <div class="finance-box">
                                            <div class="text-muted small">Chi phí khác</div>
                                            <div class="amount">{{ $money($otherCost) }}</div>
                                        </div>
                                    </div>

                                    <div class="col-6">
                                        <div class="finance-box">
                                            <div class="text-muted small">Tổng chi phí</div>
                                            <div class="amount">{{ $money($totalCost) }}</div>
                                        </div>
                                    </div>

                                    <div class="col-12">
                                        <div class="finance-box">
                                            <div class="text-muted small">Lợi nhuận</div>
                                            <div class="amount {{ $grossProfit >= 0 ? 'text-success' : 'text-danger' }}">
                                                {{ $money($grossProfit) }}
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            @endif

                            <button class="btn btn-sm btn-ego mt-3 w-100" type="button"
                                    onclick="bootstrap.Tab.getOrCreateInstance(document.querySelector('#tab-finance')).show()">
                                <i class="bi bi-plus-circle"></i> Ghi nhận thanh toán
                            </button>
                        </div>
                    </div>

                    <div class="card ego-card">
                        <div class="card-body">
                            <h5 class="mb-3">
                                <i class="bi bi-person-badge"></i> Phụ trách & vận hành
                            </h5>

                            <div class="info-item mb-2">
                                <div class="label">Kỹ thuật phụ trách</div>
                                <div class="value">{{ $systemSummary['technician_name'] ?: ($site->technician_name ?? '—') }}</div>
                            </div>

                            <div class="info-item mb-2">
                                <div class="label">Ngày lắp / bảo hành</div>
                                <div class="value">
                                    {{ $fmtDate($systemSummary['installed_at'] ?? ($site->installed_at ?? null)) }}
                                    →
                                    {{ $fmtDate($systemSummary['warranty_to'] ?? ($site->warranty_to ?? null)) }}
                                </div>
                            </div>

                            <div class="info-item">
                                <div class="label">Liên hệ khách hàng</div>
                                <div class="value">
                                    {{ $site->contact_name ?? '—' }}
                                    <div class="small text-muted">{{ $site->contact_phone ?? '—' }}</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="tab-pane fade" id="pane-finance" role="tabpanel">
            <div class="row g-3">
                <div class="col-12 col-lg-5">
                    <div class="card ego-card mb-3">
                        <div class="card-body">
                            <h5 class="mb-3">
                                <i class="bi bi-cash-coin"></i> Tổng quan tài chính
                            </h5>

                            <div class="finance-main mb-3">
                                <div class="text-muted small">Tổng doanh thu dự án / Giá trị hợp đồng</div>
                                <div class="money-big">{{ $money($contractAmount) }}</div>

                                <div class="small text-muted mt-2">
                                    Tổng các đợt thanh toán: <b>{{ $money($totalTermAmount) }}</b>
                                </div>

                                <div class="small text-muted mt-1">
                                    Đã thu: <b class="text-primary">{{ $money($receivedAmount) }}</b>
                                    · Còn lại:
                                    <b class="{{ $remainingReceivable > 0 ? 'text-danger' : 'text-success' }}">
                                        {{ $money($remainingReceivable) }}
                                    </b>
                                </div>

                                <div class="progress-soft mt-2">
                                    <div class="bar" style="width:{{ $paidPercent }}%"></div>
                                </div>
                            </div>

                            <div class="row g-2">
                                <div class="col-6">
                                    <div class="finance-box">
                                        <div class="text-muted small">Đã thu</div>
                                        <div class="amount text-primary">{{ $money($receivedAmount) }}</div>
                                    </div>
                                </div>

                                <div class="col-6">
                                    <div class="finance-box">
                                        <div class="text-muted small">Công nợ</div>
                                        <div class="amount {{ $remainingReceivable > 0 ? 'text-danger' : 'text-success' }}">
                                            {{ $money($remainingReceivable) }}
                                        </div>
                                    </div>
                                </div>

                                @if($canSeeCost)
                                    <div class="col-6">
                                        <div class="finance-box">
                                            <div class="text-muted small">Chi phí vật tư</div>
                                            <div class="amount">{{ $money($materialCost) }}</div>
                                        </div>
                                    </div>

                                    <div class="col-6">
                                        <div class="finance-box">
                                            <div class="text-muted small">Chi phí nhân công</div>
                                            <div class="amount">{{ $money($laborCost) }}</div>
                                        </div>
                                    </div>

                                    <div class="col-6">
                                        <div class="finance-box">
                                            <div class="text-muted small">Chi phí vận chuyển</div>
                                            <div class="amount">{{ $money($transportCost) }}</div>
                                        </div>
                                    </div>

                                    <div class="col-6">
                                        <div class="finance-box">
                                            <div class="text-muted small">Chi phí khác</div>
                                            <div class="amount">{{ $money($otherCost) }}</div>
                                        </div>
                                    </div>

                                    <div class="col-6">
                                        <div class="finance-box">
                                            <div class="text-muted small">Tổng chi phí</div>
                                            <div class="amount">{{ $money($totalCost) }}</div>
                                            <div class="progress-soft cost mt-2">
                                                <div class="bar" style="width:{{ $costPercent }}%"></div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="col-6">
                                        <div class="finance-box">
                                            <div class="text-muted small">Lợi nhuận tạm tính</div>
                                            <div class="amount {{ $grossProfit >= 0 ? 'text-success' : 'text-danger' }}">
                                                {{ $money($grossProfit) }}
                                            </div>
                                        </div>
                                    </div>
                                @endif
                            </div>

                            @if($canSeeCost)
                                <div class="small text-muted mt-3">
                                    <b>Công thức:</b> Lợi nhuận = Tổng doanh thu dự án - Tổng chi phí.
                                </div>
                            @endif
                        </div>
                    </div>

                    <div class="card ego-card" id="ghi-nhan-thanh-toan">
                        <div class="card-body">
                            <h5 class="mb-3">
                                <i class="bi bi-receipt"></i> Ghi nhận thanh toán
                            </h5>

                            @if(session('success'))
                                <div class="alert alert-success py-2 mb-3">
                                    <i class="bi bi-check-circle"></i> {{ session('success') }}
                                </div>
                            @endif

                            @if(session('error'))
                                <div class="alert alert-danger py-2 mb-3">
                                    <i class="bi bi-exclamation-triangle"></i> {{ session('error') }}
                                </div>
                            @endif

                            @if($errors->any())
                                <div class="alert alert-danger py-2 mb-3">
                                    <div class="fw-bold mb-1">Chưa ghi nhận được thanh toán:</div>
                                    @foreach($errors->all() as $error)
                                        <div>• {{ $error }}</div>
                                    @endforeach
                                </div>
                            @endif

                            <form method="POST"
                                  action="{{ route('sites.record-payment', ['id' => $site->id]) }}"
                                  class="payment-form">
                                @csrf

                                <div class="mb-3">
                                    <label class="form-label">Thanh toán cho đợt</label>
                                    <select name="site_payment_term_id" id="paymentTermSelect" class="form-select" required>
                                        @foreach($paymentTerms as $term)
                                            @php
                                                $termAmount = (float)($term['amount'] ?? 0);
                                                $termPaid = (float)($term['paid_amount'] ?? 0);
                                                $termRemain = (float)($term['remaining_amount'] ?? max(0, $termAmount - $termPaid));
                                            @endphp
                                            <option value="{{ $term['id'] ?? '' }}"
                                                    data-amount="{{ $termRemain > 0 ? $termRemain : $termAmount }}"
                                                    {{ (string)old('site_payment_term_id') === (string)($term['id'] ?? '') ? 'selected' : '' }}>
                                                {{ $term['name'] ?? 'Đợt thanh toán' }}
                                                - Còn: {{ $money($termRemain) }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">Số tiền thanh toán <span class="text-danger">*</span></label>
                                    <input type="text"
                                           name="amount"
                                           id="paymentAmountInput"
                                           class="form-control text-end"
                                           value="{{ old('amount') }}"
                                           placeholder="VD: 50000000 hoặc 50.000.000"
                                           required>
                                </div>

                                <div class="row g-2">
                                    <div class="col-md-6">
                                        <label class="form-label">Hình thức <span class="text-danger">*</span></label>
                                        <select name="payment_method" class="form-select" required>
                                            <option value="">-- Chọn --</option>
                                            <option value="cash" {{ old('payment_method') === 'cash' ? 'selected' : '' }}>Tiền mặt</option>
                                            <option value="bank_transfer" {{ old('payment_method') === 'bank_transfer' ? 'selected' : '' }}>Chuyển khoản</option>
                                            <option value="card" {{ old('payment_method') === 'card' ? 'selected' : '' }}>Thẻ</option>
                                            <option value="momo" {{ old('payment_method') === 'momo' ? 'selected' : '' }}>MoMo</option>
                                            <option value="vnpay" {{ old('payment_method') === 'vnpay' ? 'selected' : '' }}>VNPay</option>
                                            <option value="other" {{ old('payment_method') === 'other' ? 'selected' : '' }}>Khác</option>
                                        </select>
                                    </div>

                                    <div class="col-md-6">
                                        <label class="form-label">Ngày thanh toán</label>
                                        <input type="date"
                                               name="payment_date"
                                               class="form-control"
                                               value="{{ old('payment_date', now()->toDateString()) }}"
                                               required>
                                    </div>
                                </div>

                                <div class="mt-3">
                                    <label class="form-label">Ghi chú</label>
                                    <textarea name="note"
                                              class="form-control"
                                              rows="2"
                                              placeholder="VD: Khách chuyển khoản đợt 1, mã giao dịch...">{{ old('note') }}</textarea>
                                </div>

                                <button type="submit" class="btn btn-ego w-100 mt-3"
                                        onclick="return confirm('Xác nhận ghi nhận thanh toán cho công trình này?')">
                                    <i class="bi bi-check2-circle"></i> Ghi nhận thanh toán
                                </button>
                            </form>
                        </div>
                    </div>
                </div>

                <div class="col-12 col-lg-7">
                    <div class="card ego-card mb-3">
                        <div class="card-body">
                            <h5 class="mb-3">
                                <i class="bi bi-calendar-check"></i> Các đợt thanh toán
                            </h5>

                            <div class="table-responsive">
                                <table class="table table-hover align-middle mb-0">
                                    <thead>
                                    <tr>
                                        <th style="width:52px">#</th>
                                        <th>Đợt thanh toán</th>
                                        <th class="text-end" style="width:90px">%</th>
                                        <th class="text-end" style="width:150px">Kế hoạch</th>
                                        <th class="text-end" style="width:150px">Đã thu</th>
                                        <th class="text-end" style="width:150px">Còn lại</th>
                                        <th style="width:130px">Ngày dự kiến</th>
                                        <th style="width:110px">Trạng thái</th>
                                    </tr>
                                    </thead>

                                    <tbody>
                                    @forelse($paymentTerms as $i => $term)
                                        @php
                                            $termAmount = (float)($term['amount'] ?? 0);
                                            $termPaid = (float)($term['paid_amount'] ?? 0);
                                            $termRemain = (float)($term['remaining_amount'] ?? max(0, $termAmount - $termPaid));
                                            $termStatus = (string)($term['computed_status'] ?? 'pending');

                                            $termStatusLabel = match ($termStatus) {
                                                'paid' => 'Đã thu đủ',
                                                'partial' => 'Thu một phần',
                                                default => 'Chưa thu',
                                            };

                                            $termStatusClass = match ($termStatus) {
                                                'paid' => 'bg-success',
                                                'partial' => 'bg-warning text-dark',
                                                default => 'bg-secondary',
                                            };
                                        @endphp

                                        <tr>
                                            <td class="fw-bold">{{ $i + 1 }}</td>

                                            <td>
                                                <div class="fw-semibold">{{ $term['name'] ?? 'Đợt thanh toán' }}</div>
                                                @if(!empty($term['note']))
                                                    <div class="small text-muted">{{ $term['note'] }}</div>
                                                @endif
                                            </td>

                                            <td class="text-end">
                                                {{ isset($term['percent']) && $term['percent'] !== '' ? number_format((float)$term['percent'], 2) . '%' : '—' }}
                                            </td>

                                            <td class="text-end fw-bold">
                                                {{ $money($termAmount) }}
                                            </td>

                                            <td class="text-end text-primary fw-bold">
                                                {{ $money($termPaid) }}
                                            </td>

                                            <td class="text-end fw-bold {{ $termRemain > 0 ? 'text-danger' : 'text-success' }}">
                                                {{ $money($termRemain) }}
                                            </td>

                                            <td>{{ $fmtDate($term['due_date'] ?? null) }}</td>

                                            <td>
                                                <span class="badge {{ $termStatusClass }}">
                                                    {{ $termStatusLabel }}
                                                </span>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="8" class="text-center text-muted py-4">
                                                Chưa có đợt thanh toán.
                                            </td>
                                        </tr>
                                    @endforelse
                                    </tbody>

                                    <tfoot>
                                    <tr>
                                        <th colspan="3">Tổng</th>
                                        <th class="text-end">{{ $money($totalTermAmount) }}</th>
                                        <th class="text-end text-primary">{{ $money($receivedAmount) }}</th>
                                        <th class="text-end {{ $remainingReceivable > 0 ? 'text-danger' : 'text-success' }}">
                                            {{ $money($remainingReceivable) }}
                                        </th>
                                        <th colspan="2"></th>
                                    </tr>
                                    </tfoot>
                                </table>
                            </div>
                        </div>
                    </div>

                    <div class="card ego-card">
                        <div class="card-body">
                            <h5 class="mb-3">
                                <i class="bi bi-clock-history"></i> Lịch sử thanh toán
                            </h5>

                            <div class="table-responsive">
                                <table class="table table-hover align-middle mb-0">
                                    <thead>
                                    <tr>
                                        <th style="width:52px">#</th>
                                        <th>Đợt</th>
                                        <th class="text-end" style="width:160px">Số tiền</th>
                                        <th style="width:150px">Hình thức</th>
                                        <th style="width:140px">Ngày thu</th>
                                        <th>Ghi chú</th>
                                    </tr>
                                    </thead>

                                    <tbody>
                                    @forelse($paymentReceipts as $i => $receipt)
                                        <tr>
                                            <td class="fw-bold">{{ $i + 1 }}</td>

                                            <td>{{ $receipt['term_name'] ?: 'Thanh toán chung' }}</td>

                                            <td class="text-end fw-bold text-primary">
                                                {{ $money($receipt['amount'] ?? 0) }}
                                            </td>

                                            <td>
                                                <span class="badge bg-light text-dark border">
                                                    {{ $methodLabel($receipt['payment_method'] ?? '') }}
                                                </span>
                                            </td>

                                            <td>{{ $fmtDate($receipt['paid_at'] ?? ($receipt['created_at'] ?? null)) }}</td>

                                            <td class="text-muted">{{ $receipt['note'] ?: '—' }}</td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="6" class="text-center text-muted py-4">
                                                Chưa có lịch sử thanh toán.
                                            </td>
                                        </tr>
                                    @endforelse
                                    </tbody>
                                </table>
                            </div>

                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="tab-pane fade" id="pane-main-materials" role="tabpanel">
            <div class="card ego-card">
                <div class="card-body">
                    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
                        <div>
                            <h5 class="mb-1">
                                <i class="bi bi-cpu"></i> Thiết bị chính
                            </h5>
                            <div class="small text-muted">
                                Lấy từ đơn vật tư: inverter, pin, tấm pin, smart meter... trong kho hoặc ngoài kho.
                            </div>
                        </div>

                        <div class="d-flex flex-wrap gap-2">
                            <input class="form-control form-control-sm material-search"
                                   style="width:260px; border-radius:12px;"
                                   data-target="mainMaterialTable"
                                   placeholder="Tìm thiết bị chính...">
                            <span class="badge bg-light text-dark border">{{ $mainSummary['rows'] }} dòng</span>
                        </div>
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-6 col-md-3">
                            <div class="material-summary">
                                <div class="label">Tổng dòng</div>
                                <div class="value">{{ $mainSummary['rows'] }}</div>
                            </div>
                        </div>

                        <div class="col-6 col-md-3">
                            <div class="material-summary">
                                <div class="label">Tổng số lượng</div>
                                <div class="value">{{ $fmtQty($mainSummary['qty']) }}</div>
                            </div>
                        </div>

                        <div class="col-6 col-md-3">
                            <div class="material-summary">
                                <div class="label">Trong kho / Ngoài kho</div>
                                <div class="value">{{ $mainSummary['stock'] }} / {{ $mainSummary['external'] }}</div>
                            </div>
                        </div>

                        @if($canSeeCost)
                            <div class="col-6 col-md-3">
                                <div class="material-summary">
                                    <div class="label">Tổng giá vốn</div>
                                    <div class="value text-success">{{ $money($mainSummary['cost']) }}</div>
                                </div>
                            </div>
                        @endif
                    </div>

                    <div class="table-responsive">
                        <table class="table table-hover align-middle" id="mainMaterialTable">
                            <thead>
                            <tr>
                                <th style="width:52px">#</th>
                                <th style="width:110px">Đơn VT</th>
                                <th style="min-width:260px">Thiết bị</th>
                                <th style="width:150px">SKU / Mã SP</th>
                                <th style="width:170px">Nhóm</th>
                                <th class="text-end" style="width:100px">SL</th>
                                <th style="width:90px">ĐVT</th>

                                @if($canSeeCost)
                                    <th class="text-end" style="width:140px">Giá vốn</th>
                                    <th class="text-end" style="width:90px">VAT</th>
                                    <th class="text-end" style="width:150px">Thành tiền</th>
                                @endif

                                <th style="min-width:220px">Ghi chú</th>
                            </tr>
                            </thead>

                            <tbody>
                            @forelse($mainMaterials as $i => $item)
                                @php
                                    $group = $materialGroup($item);
                                    $name = $materialName($item);
                                    $unit = $materialUnit($item);
                                    $note = $materialNoteOnly($item);
                                    $sku = $item->product_sku ?? null;
                                @endphp

                                <tr>
                                    <td class="fw-bold">{{ $i + 1 }}</td>

                                    <td>
                                        <a href="{{ url('/don-vat-tu/'.$item->material_request_id) }}" class="btn btn-sm btn-ego-outline">
                                            #{{ $item->material_request_id }}
                                        </a>
                                        <div class="small text-muted mt-1">{{ $fmtDate($item->request_created_at ?? null) }}</div>
                                    </td>

                                    <td>
                                        <div class="actual-name">{{ $name }}</div>
                                        <div class="actual-sub">{{ !empty($item->product_id) ? 'Trong catalog' : 'Ngoài kho / phát sinh' }}</div>
                                    </td>

                                    <td>
                                        @if($sku)
                                            <span class="badge bg-light text-dark border">{{ $sku }}</span>
                                        @elseif(!empty($item->product_id))
                                            <span class="badge bg-light text-dark border">ID: {{ $item->product_id }}</span>
                                        @else
                                            <span class="text-muted">—</span>
                                        @endif
                                    </td>

                                    <td>
                                        <span class="group-badge {{ $groupClass($group) }}">{{ $group }}</span>
                                    </td>

                                    <td class="text-end fw-bold">{{ $fmtQty($item->qty ?? 0) }}</td>
                                    <td>{{ $unit }}</td>

                                    @if($canSeeCost)
                                        <td class="text-end fw-bold text-success">{{ $money($item->unit_cost ?? 0) }}</td>
                                        <td class="text-end">{{ $fmtQty($item->vat_percent ?? 0) }}%</td>
                                        <td class="text-end fw-bold">{{ $money($item->line_total ?? 0) }}</td>
                                    @endif

                                    <td class="text-muted">{{ $note }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="{{ $canSeeCost ? 11 : 8 }}" class="text-center text-muted py-4">
                                        Chưa có thiết bị chính đã xuất kho.
                                    </td>
                                </tr>
                            @endforelse
                            </tbody>

                            @if($mainSummary['rows'] > 0)
                                <tfoot>
                                <tr>
                                    <th colspan="5">Tổng cộng</th>
                                    <th class="text-end">{{ $fmtQty($mainSummary['qty']) }}</th>

                                    @if($canSeeCost)
                                        <th colspan="3"></th>
                                        <th class="text-end text-success">{{ $money($mainSummary['cost']) }}</th>
                                        <th></th>
                                    @else
                                        <th colspan="2"></th>
                                    @endif
                                </tr>
                                </tfoot>
                            @endif
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <div class="tab-pane fade" id="pane-sub-materials" role="tabpanel">
            <div class="card ego-card">
                <div class="card-body">
                    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
                        <div>
                            <h5 class="mb-1">
                                <i class="bi bi-tools"></i> Vật tư phụ
                            </h5>
                            <div class="small text-muted">
                                Lấy từ đơn vật tư: dây, CB, rail, ống gen, phụ kiện... trong kho hoặc ngoài kho.
                            </div>
                        </div>

                        <div class="d-flex flex-wrap gap-2">
                            <input class="form-control form-control-sm material-search"
                                   style="width:260px; border-radius:12px;"
                                   data-target="subMaterialTable"
                                   placeholder="Tìm vật tư phụ...">
                            <span class="badge bg-light text-dark border">{{ $subSummary['rows'] }} dòng</span>
                        </div>
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-6 col-md-3">
                            <div class="material-summary">
                                <div class="label">Tổng dòng</div>
                                <div class="value">{{ $subSummary['rows'] }}</div>
                            </div>
                        </div>

                        <div class="col-6 col-md-3">
                            <div class="material-summary">
                                <div class="label">Tổng số lượng</div>
                                <div class="value">{{ $fmtQty($subSummary['qty']) }}</div>
                            </div>
                        </div>

                        <div class="col-6 col-md-3">
                            <div class="material-summary">
                                <div class="label">Trong kho / Ngoài kho</div>
                                <div class="value">{{ $subSummary['stock'] }} / {{ $subSummary['external'] }}</div>
                            </div>
                        </div>

                        @if($canSeeCost)
                            <div class="col-6 col-md-3">
                                <div class="material-summary">
                                    <div class="label">Tổng giá vốn</div>
                                    <div class="value text-success">{{ $money($subSummary['cost']) }}</div>
                                </div>
                            </div>
                        @endif
                    </div>

                    <div class="table-responsive">
                        <table class="table table-hover align-middle" id="subMaterialTable">
                            <thead>
                            <tr>
                                <th style="width:52px">#</th>
                                <th style="width:110px">Đơn VT</th>
                                <th style="min-width:260px">Vật tư phụ</th>
                                <th style="width:150px">SKU / Mã SP</th>
                                <th style="width:170px">Nhóm</th>
                                <th class="text-end" style="width:100px">SL</th>
                                <th style="width:90px">ĐVT</th>

                                @if($canSeeCost)
                                    <th class="text-end" style="width:140px">Giá vốn</th>
                                    <th class="text-end" style="width:90px">VAT</th>
                                    <th class="text-end" style="width:150px">Thành tiền</th>
                                @endif

                                <th style="min-width:220px">Ghi chú</th>
                            </tr>
                            </thead>

                            <tbody>
                            @forelse($subMaterials as $i => $item)
                                @php
                                    $group = $materialGroup($item);
                                    $name = $materialName($item);
                                    $unit = $materialUnit($item);
                                    $note = $materialNoteOnly($item);
                                    $sku = $item->product_sku ?? null;
                                @endphp

                                <tr>
                                    <td class="fw-bold">{{ $i + 1 }}</td>

                                    <td>
                                        <a href="{{ url('/don-vat-tu/'.$item->material_request_id) }}" class="btn btn-sm btn-ego-outline">
                                            #{{ $item->material_request_id }}
                                        </a>
                                        <div class="small text-muted mt-1">{{ $fmtDate($item->request_created_at ?? null) }}</div>
                                    </td>

                                    <td>
                                        <div class="actual-name">{{ $name }}</div>
                                        <div class="actual-sub">{{ !empty($item->product_id) ? 'Trong catalog' : 'Ngoài kho / phát sinh' }}</div>
                                    </td>

                                    <td>
                                        @if($sku)
                                            <span class="badge bg-light text-dark border">{{ $sku }}</span>
                                        @elseif(!empty($item->product_id))
                                            <span class="badge bg-light text-dark border">ID: {{ $item->product_id }}</span>
                                        @else
                                            <span class="text-muted">—</span>
                                        @endif
                                    </td>

                                    <td>
                                        <span class="group-badge {{ $groupClass($group) }}">{{ $group }}</span>
                                    </td>

                                    <td class="text-end fw-bold">{{ $fmtQty($item->qty ?? 0) }}</td>
                                    <td>{{ $unit }}</td>

                                    @if($canSeeCost)
                                        <td class="text-end fw-bold text-success">{{ $money($item->unit_cost ?? 0) }}</td>
                                        <td class="text-end">{{ $fmtQty($item->vat_percent ?? 0) }}%</td>
                                        <td class="text-end fw-bold">{{ $money($item->line_total ?? 0) }}</td>
                                    @endif

                                    <td class="text-muted">{{ $note }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="{{ $canSeeCost ? 11 : 8 }}" class="text-center text-muted py-4">
                                        Chưa có vật tư phụ đã xuất kho.
                                    </td>
                                </tr>
                            @endforelse
                            </tbody>

                            @if($subSummary['rows'] > 0)
                                <tfoot>
                                <tr>
                                    <th colspan="5">Tổng cộng</th>
                                    <th class="text-end">{{ $fmtQty($subSummary['qty']) }}</th>

                                    @if($canSeeCost)
                                        <th colspan="3"></th>
                                        <th class="text-end text-success">{{ $money($subSummary['cost']) }}</th>
                                        <th></th>
                                    @else
                                        <th colspan="2"></th>
                                    @endif
                                </tr>
                                </tfoot>
                            @endif
                        </table>
                    </div>
                </div>
            </div>
        </div>

    </div>
</div>

<script>
(function(){
    document.querySelectorAll('.material-search').forEach(function(input){
        input.addEventListener('input', function(){
            const tableId = input.dataset.target;
            const table = document.getElementById(tableId);

            if (!table) return;

            const q = (input.value || '').toLowerCase().trim();
            const rows = table.querySelectorAll('tbody tr');

            rows.forEach(function(row){
                const text = (row.innerText || '').toLowerCase();
                row.style.display = (!q || text.includes(q)) ? '' : 'none';
            });
        });
    });

    const termSelect = document.getElementById('paymentTermSelect');
    const amountInput = document.getElementById('paymentAmountInput');

    if (termSelect && amountInput) {
        termSelect.addEventListener('change', function(){
            const option = termSelect.options[termSelect.selectedIndex];

            if (!option) return;

            const amount = option.dataset.amount || '';

            if (amount && Number(amount) > 0) {
                amountInput.value = amount;
            }
        });
    }
})();
</script>
@endsection

{{-- EGO_SITE_PAYMENT_TERM_ONLY_VIEW_START --}}
@php
    $egoSiteIdForPayment = $site->id ?? request()->route('id');

    $egoReceiptSource = $receipts
        ?? $siteReceipts
        ?? $paymentReceipts
        ?? $paymentHistory
        ?? [];

    $egoTermSource = $paymentTerms
        ?? $sitePaymentTerms
        ?? [];

    $egoPaymentRows = collect($egoReceiptSource)->values()->map(function ($r) {
        $id = data_get($r, 'id');
        return [
            'id' => $id,
            'term_id' => data_get($r, 'site_payment_term_id'),
            'amount' => (float) data_get($r, 'amount', 0),
            'payment_method' => data_get($r, 'payment_method', 'cash'),
            'receipt_date' => data_get($r, 'receipt_date') ?: data_get($r, 'payment_date'),
            'note' => data_get($r, 'note'),
        ];
    })->filter(function ($r) {
        return !empty($r['id']);
    })->values();

    $egoPaymentTerms = collect($egoTermSource)->values()->map(function ($t) {
        return [
            'id' => data_get($t, 'id'),
            'name' => data_get($t, 'name'),
            'amount' => (float) data_get($t, 'amount', 0),
        ];
    })->filter(function ($t) {
        return !empty($t['id']);
    })->values();

    if ($egoPaymentTerms->isEmpty() && isset($site) && method_exists($site, 'paymentTerms')) {
        $egoPaymentTerms = $site->paymentTerms()->get()->map(function ($t) {
            return [
                'id' => $t->id,
                'name' => $t->name,
                'amount' => (float) $t->amount,
            ];
        })->values();
    }
@endphp

<style>
    .ego-pay-actions{display:flex;gap:6px;align-items:center;justify-content:flex-start}
    .ego-pay-btn{border:0;border-radius:8px;padding:6px 9px;font-weight:800;font-size:12px;cursor:pointer}
    .ego-pay-btn-edit{background:#e0f2fe;color:#075985}
    .ego-pay-btn-delete{background:#fee2e2;color:#991b1b}
    .ego-pay-modal-mask{position:fixed;inset:0;background:rgba(15,23,42,.45);z-index:9998;display:none;align-items:center;justify-content:center;padding:16px}
    .ego-pay-modal{width:min(540px,100%);background:#fff;border-radius:16px;box-shadow:0 24px 80px rgba(0,0,0,.22);padding:18px}
    .ego-pay-modal h3{margin:0 0 14px;font-size:18px;font-weight:900}
    .ego-pay-grid{display:grid;grid-template-columns:1fr 1fr;gap:12px}
    .ego-pay-field{display:flex;flex-direction:column;gap:6px;margin-bottom:12px}
    .ego-pay-field label{font-size:13px;font-weight:800;color:#334155}
    .ego-pay-field input,.ego-pay-field select,.ego-pay-field textarea{border:1px solid #cbd5e1;border-radius:10px;padding:9px 10px;width:100%}
    .ego-pay-modal-actions{display:flex;justify-content:flex-end;gap:8px;margin-top:12px}
    .ego-pay-modal-actions button{border:0;border-radius:10px;padding:9px 14px;font-weight:900;cursor:pointer}
    .ego-pay-cancel{background:#e5e7eb;color:#111827}
    .ego-pay-save{background:#10b981;color:#fff}
    @media(max-width:640px){.ego-pay-grid{grid-template-columns:1fr}}
</style>

<div class="ego-pay-modal-mask" id="egoPaymentEditModal">
    <div class="ego-pay-modal">
        <h3>Sửa thanh toán</h3>

        <form method="POST" id="egoPaymentEditForm">
            @csrf
            @method('PUT')

            <div class="ego-pay-field">
                <label>Thanh toán cho đợt *</label>
                <select name="site_payment_term_id" id="egoPaymentTermId" required>
                    @foreach($egoPaymentTerms as $term)
                        <option value="{{ $term['id'] }}">
                            {{ $term['name'] }} - {{ number_format($term['amount'], 0, ',', '.') }} đ
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="ego-pay-grid">
                <div class="ego-pay-field">
                    <label>Số tiền *</label>
                    <input name="amount" id="egoPaymentAmount" inputmode="decimal" required>
                </div>

                <div class="ego-pay-field">
                    <label>Ngày thanh toán *</label>
                    <input type="date" name="payment_date" id="egoPaymentDate" required>
                </div>
            </div>

            <div class="ego-pay-field">
                <label>Hình thức *</label>
                <select name="payment_method" id="egoPaymentMethod" required>
                    <option value="cash">Tiền mặt</option>
                    <option value="bank_transfer">Chuyển khoản</option>
                    <option value="card">Thẻ</option>
                    <option value="other">Khác</option>
                </select>
            </div>

            <div class="ego-pay-field">
                <label>Ghi chú</label>
                <textarea name="note" id="egoPaymentNote" rows="3"></textarea>
            </div>

            <div class="ego-pay-modal-actions">
                <button type="button" class="ego-pay-cancel" id="egoPaymentCloseBtn">Đóng</button>
                <button type="submit" class="ego-pay-save">Lưu cập nhật</button>
            </div>
        </form>
    </div>
</div>

<script>
(function () {
    var receipts = @json($egoPaymentRows);
    var baseUrl = @json(url('/cong-trinh/' . $egoSiteIdForPayment . '/thanh-toan'));
    var csrf = @json(csrf_token());

    function findPaymentTable() {
        var tables = Array.prototype.slice.call(document.querySelectorAll('table'));

        return tables.find(function (table) {
            var text = (table.innerText || '').toLowerCase();
            return text.indexOf('số tiền') !== -1
                && text.indexOf('hình thức') !== -1
                && (text.indexOf('ngày thu') !== -1 || text.indexOf('ngày thanh toán') !== -1);
        });
    }

    function money(v) {
        v = Number(v || 0);
        return v.toLocaleString('vi-VN');
    }

    function openEdit(row) {
        var modal = document.getElementById('egoPaymentEditModal');
        var form = document.getElementById('egoPaymentEditForm');

        form.action = baseUrl + '/' + row.id + '/cap-nhat';
        document.getElementById('egoPaymentTermId').value = row.term_id || '';
        document.getElementById('egoPaymentAmount').value = money(row.amount);
        document.getElementById('egoPaymentDate').value = (row.receipt_date || '').substring(0, 10);
        document.getElementById('egoPaymentMethod').value = row.payment_method || 'cash';
        document.getElementById('egoPaymentNote').value = row.note || '';

        modal.style.display = 'flex';
    }

    function deletePayment(row) {
        if (!confirm('Xóa thanh toán này?')) {
            return;
        }

        var form = document.createElement('form');
        form.method = 'POST';
        form.action = baseUrl + '/' + row.id + '/xoa';
        form.innerHTML =
            '<input type="hidden" name="_token" value="' + csrf + '">' +
            '<input type="hidden" name="_method" value="DELETE">';

        document.body.appendChild(form);
        form.submit();
    }

    function enhanceTable() {
        if (!receipts.length) {
            return;
        }

        var table = findPaymentTable();

        if (!table) {
            return;
        }

        var headerRow = table.querySelector('thead tr') || table.querySelector('tr');

        if (headerRow && !headerRow.querySelector('[data-ego-pay-actions-head]')) {
            var th = document.createElement('th');
            th.textContent = 'Thao tác';
            th.setAttribute('data-ego-pay-actions-head', '1');
            headerRow.appendChild(th);
        }

        var rows = Array.prototype.slice.call(table.querySelectorAll('tbody tr'));

        if (!rows.length) {
            rows = Array.prototype.slice.call(table.querySelectorAll('tr')).slice(1);
        }

        rows.forEach(function (tr, index) {
            if (tr.querySelector('[data-ego-pay-actions-cell]')) {
                return;
            }

            var row = receipts[index];

            if (!row || !row.id) {
                return;
            }

            var td = document.createElement('td');
            td.setAttribute('data-ego-pay-actions-cell', '1');

            var wrap = document.createElement('div');
            wrap.className = 'ego-pay-actions';

            var edit = document.createElement('button');
            edit.type = 'button';
            edit.className = 'ego-pay-btn ego-pay-btn-edit';
            edit.textContent = 'Sửa';
            edit.addEventListener('click', function () {
                openEdit(row);
            });

            var del = document.createElement('button');
            del.type = 'button';
            del.className = 'ego-pay-btn ego-pay-btn-delete';
            del.textContent = 'Xóa';
            del.addEventListener('click', function () {
                deletePayment(row);
            });

            wrap.appendChild(edit);
            wrap.appendChild(del);
            td.appendChild(wrap);
            tr.appendChild(td);
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        enhanceTable();

        var closeBtn = document.getElementById('egoPaymentCloseBtn');
        var modal = document.getElementById('egoPaymentEditModal');

        if (closeBtn && modal) {
            closeBtn.addEventListener('click', function () {
                modal.style.display = 'none';
            });

            modal.addEventListener('click', function (e) {
                if (e.target === modal) {
                    modal.style.display = 'none';
                }
            });
        }
    });
})();
</script>
{{-- EGO_SITE_PAYMENT_TERM_ONLY_VIEW_END --}}


{{-- EGO_FIX_PAYMENT_HISTORY_SHOW_DATE_START --}}
@php
    $egoFixSiteId = (int) data_get($site ?? null, 'id', request()->route('id'));

    $egoFixMethodLabels = [
        'cash' => 'Tiền mặt',
        'bank_transfer' => 'Chuyển khoản',
        'transfer' => 'Chuyển khoản',
        'bank' => 'Chuyển khoản',
        'card' => 'Thẻ',
        'other' => 'Khác',
    ];

    $egoFixTerms = \Illuminate\Support\Facades\DB::table('site_payment_terms')
        ->where('site_id', $egoFixSiteId)
        ->orderBy('id')
        ->get();

    $egoFixPayments = \Illuminate\Support\Facades\DB::table('receipts as r')
        ->leftJoin('site_payment_terms as t', 't.id', '=', 'r.site_payment_term_id')
        ->leftJoin('users as u', 'u.id', '=', 'r.created_by')
        ->where('r.site_id', $egoFixSiteId)
        ->whereNotNull('r.site_payment_term_id')
        ->orderBy('r.receipt_date')
        ->orderBy('r.id')
        ->get([
            'r.id',
            'r.amount',
            'r.payment_method',
            'r.receipt_date',
            'r.created_at',
            'r.note',
            'r.created_by',
            'r.site_payment_term_id',
            't.name as term_name',
            'u.name as creator_name',
            'u.email as creator_email',
        ])
        ->map(function ($r) use ($egoFixMethodLabels) {
            $dateValue = '';
            $dateText = '—';

            if (!empty($r->receipt_date) && $r->receipt_date !== '0000-00-00') {
                try {
                    $dateValue = \Carbon\Carbon::parse($r->receipt_date)->format('Y-m-d');
                    $dateText = \Carbon\Carbon::parse($r->receipt_date)->format('d/m/Y');
                } catch (\Throwable $e) {
                    $dateValue = (string) $r->receipt_date;
                    $dateText = (string) $r->receipt_date;
                }
            } elseif (!empty($r->created_at)) {
                try {
                    $dateValue = \Carbon\Carbon::parse($r->created_at)->format('Y-m-d');
                    $dateText = \Carbon\Carbon::parse($r->created_at)->format('d/m/Y');
                } catch (\Throwable $e) {
                    $dateValue = '';
                    $dateText = '—';
                }
            }

            $method = $r->payment_method ?: 'cash';

            return [
                'id' => (int) $r->id,
                'term_id' => $r->site_payment_term_id ? (int) $r->site_payment_term_id : null,
                'term_name' => $r->term_name ?: 'Chưa gắn đợt',
                'amount' => (float) $r->amount,
                'amount_text' => number_format((float) $r->amount, 0, ',', '.') . ' đ',
                'payment_method' => $method,
                'payment_method_text' => $egoFixMethodLabels[$method] ?? $method,
                'receipt_date' => $dateValue,
                'receipt_date_text' => $dateText,
                'creator_text' => $r->creator_name ?: ($r->creator_email ?: ($r->created_by ? ('User #' . $r->created_by) : '—')),
                'note' => $r->note ?: '—',
            ];
        })
        ->values();

    $egoFixTermOptions = $egoFixTerms
        ->map(function ($t) {
            return [
                'id' => (int) $t->id,
                'name' => $t->name,
                'amount_text' => number_format((float) $t->amount, 0, ',', '.') . ' đ',
            ];
        })
        ->values();
@endphp

<style>
    .ego-fix-pay-actions{display:flex;gap:6px;align-items:center;justify-content:flex-start}
    .ego-fix-pay-btn{border:0;border-radius:8px;padding:6px 9px;font-weight:800;font-size:12px;cursor:pointer;white-space:nowrap}
    .ego-fix-pay-edit{background:#e0f2fe;color:#075985}
    .ego-fix-pay-delete{background:#fee2e2;color:#991b1b}
    .ego-fix-pay-money{font-weight:900;color:#0066ff;white-space:nowrap}
    .ego-fix-pay-badge{display:inline-flex;align-items:center;border:1px solid #dbe3ef;border-radius:999px;padding:4px 8px;font-size:12px;font-weight:800;background:#fff;white-space:nowrap}
    .ego-fix-pay-table th,.ego-fix-pay-table td{vertical-align:middle}
    .ego-fix-pay-modal-mask{position:fixed;inset:0;background:rgba(15,23,42,.45);z-index:9999;display:none;align-items:center;justify-content:center;padding:16px}
    .ego-fix-pay-modal{width:min(560px,100%);background:#fff;border-radius:16px;box-shadow:0 24px 80px rgba(0,0,0,.22);padding:18px}
    .ego-fix-pay-modal h3{margin:0 0 14px;font-size:18px;font-weight:900}
    .ego-fix-pay-grid{display:grid;grid-template-columns:1fr 1fr;gap:12px}
    .ego-fix-pay-field{display:flex;flex-direction:column;gap:6px;margin-bottom:12px}
    .ego-fix-pay-field label{font-size:13px;font-weight:800;color:#334155}
    .ego-fix-pay-field input,.ego-fix-pay-field select,.ego-fix-pay-field textarea{border:1px solid #cbd5e1;border-radius:10px;padding:9px 10px;width:100%}
    .ego-fix-pay-modal-actions{display:flex;justify-content:flex-end;gap:8px;margin-top:12px}
    .ego-fix-pay-modal-actions button{border:0;border-radius:10px;padding:9px 14px;font-weight:900;cursor:pointer}
    .ego-fix-pay-cancel{background:#e5e7eb;color:#111827}
    .ego-fix-pay-save{background:#10b981;color:#fff}
    @media(max-width:640px){.ego-fix-pay-grid{grid-template-columns:1fr}}
</style>

<div class="ego-fix-pay-modal-mask" id="egoFixPayModal">
    <div class="ego-fix-pay-modal">
        <h3>Sửa thanh toán</h3>

        <form method="POST" id="egoFixPayForm">
            @csrf
            @method('PUT')

            <div class="ego-fix-pay-field">
                <label>Thanh toán cho đợt *</label>
                <select name="site_payment_term_id" id="egoFixPayTerm" required>
                    @foreach($egoFixTermOptions as $term)
                        <option value="{{ $term['id'] }}">{{ $term['name'] }} - {{ $term['amount_text'] }}</option>
                    @endforeach
                </select>
            </div>

            <div class="ego-fix-pay-grid">
                <div class="ego-fix-pay-field">
                    <label>Số tiền *</label>
                    <input name="amount" id="egoFixPayAmount" required>
                </div>

                <div class="ego-fix-pay-field">
                    <label>Ngày thu *</label>
                    <input type="date" name="payment_date" id="egoFixPayDate" required>
                </div>
            </div>

            <div class="ego-fix-pay-field">
                <label>Hình thức *</label>
                <select name="payment_method" id="egoFixPayMethod" required>
                    <option value="cash">Tiền mặt</option>
                    <option value="bank_transfer">Chuyển khoản</option>
                    <option value="transfer">Chuyển khoản</option>
                    <option value="card">Thẻ</option>
                    <option value="other">Khác</option>
                </select>
            </div>

            <div class="ego-fix-pay-field">
                <label>Ghi chú</label>
                <textarea name="note" id="egoFixPayNote" rows="3"></textarea>
            </div>

            <div class="ego-fix-pay-modal-actions">
                <button type="button" class="ego-fix-pay-cancel" id="egoFixPayClose">Đóng</button>
                <button type="submit" class="ego-fix-pay-save">Lưu cập nhật</button>
            </div>
        </form>
    </div>
</div>

<script>
(function () {
    var rows = @json($egoFixPayments);
    var siteId = @json($egoFixSiteId);
    var csrf = @json(csrf_token());
    var baseUrl = @json(url('/cong-trinh/' . $egoFixSiteId . '/thanh-toan'));

    function escapeHtml(value) {
        return String(value == null ? '' : value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function findPaymentHistoryTable() {
        var tables = Array.prototype.slice.call(document.querySelectorAll('table'));

        return tables.find(function (table) {
            var text = (table.innerText || '').toLowerCase();

            return text.indexOf('số tiền') !== -1
                && text.indexOf('hình thức') !== -1
                && text.indexOf('ngày thu') !== -1
                && text.indexOf('thao tác') !== -1;
        });
    }

    function openEdit(row) {
        var modal = document.getElementById('egoFixPayModal');
        var form = document.getElementById('egoFixPayForm');

        form.action = baseUrl + '/' + row.id + '/cap-nhat';

        document.getElementById('egoFixPayTerm').value = row.term_id || '';
        document.getElementById('egoFixPayAmount').value = Number(row.amount || 0).toLocaleString('vi-VN');
        document.getElementById('egoFixPayDate').value = row.receipt_date || '';
        document.getElementById('egoFixPayMethod').value = row.payment_method || 'bank_transfer';
        document.getElementById('egoFixPayNote').value = row.note === '—' ? '' : (row.note || '');

        modal.style.display = 'flex';
    }

    function submitDelete(row) {
        if (!confirm('Xóa thanh toán #' + row.id + '?')) {
            return;
        }

        var form = document.createElement('form');
        form.method = 'POST';
        form.action = baseUrl + '/' + row.id + '/xoa';
        form.innerHTML =
            '<input type="hidden" name="_token" value="' + csrf + '">' +
            '<input type="hidden" name="_method" value="DELETE">';

        document.body.appendChild(form);
        form.submit();
    }

    function renderPaymentHistory() {
        var table = findPaymentHistoryTable();

        if (!table) {
            return;
        }

        table.classList.add('ego-fix-pay-table');

        table.innerHTML =
            '<thead>' +
                '<tr>' +
                    '<th>#</th>' +
                    '<th>Đợt</th>' +
                    '<th>Số tiền</th>' +
                    '<th>Hình thức</th>' +
                    '<th>Ngày thu</th>' +
                    '<th>Người ghi nhận</th>' +
                    '<th>Ghi chú</th>' +
                    '<th>Thao tác</th>' +
                '</tr>' +
            '</thead>' +
            '<tbody></tbody>';

        var tbody = table.querySelector('tbody');

        if (!rows.length) {
            tbody.innerHTML =
                '<tr><td colspan="8" style="text-align:center;color:#64748b;padding:14px">Chưa có thanh toán theo đợt.</td></tr>';
            return;
        }

        rows.forEach(function (row, index) {
            var tr = document.createElement('tr');

            tr.innerHTML =
                '<td>' + (index + 1) + '</td>' +
                '<td>' + escapeHtml(row.term_name) + '</td>' +
                '<td class="ego-fix-pay-money">' + escapeHtml(row.amount_text) + '</td>' +
                '<td><span class="ego-fix-pay-badge">' + escapeHtml(row.payment_method_text) + '</span></td>' +
                '<td>' + escapeHtml(row.receipt_date_text) + '</td>' +
                '<td>' + escapeHtml(row.creator_text) + '</td>' +
                '<td>' + escapeHtml(row.note) + '</td>' +
                '<td><div class="ego-fix-pay-actions">' +
                    '<button type="button" class="ego-fix-pay-btn ego-fix-pay-edit">Sửa</button>' +
                    '<button type="button" class="ego-fix-pay-btn ego-fix-pay-delete">Xóa</button>' +
                '</div></td>';

            tr.querySelector('.ego-fix-pay-edit').addEventListener('click', function () {
                openEdit(row);
            });

            tr.querySelector('.ego-fix-pay-delete').addEventListener('click', function () {
                submitDelete(row);
            });

            tbody.appendChild(tr);
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        renderPaymentHistory();
        setTimeout(renderPaymentHistory, 80);
        setTimeout(renderPaymentHistory, 300);

        var modal = document.getElementById('egoFixPayModal');
        var closeBtn = document.getElementById('egoFixPayClose');

        if (modal && closeBtn) {
            closeBtn.addEventListener('click', function () {
                modal.style.display = 'none';
            });

            modal.addEventListener('click', function (event) {
                if (event.target === modal) {
                    modal.style.display = 'none';
                }
            });
        }
    });
})();
</script>
{{-- EGO_FIX_PAYMENT_HISTORY_SHOW_DATE_END --}}
