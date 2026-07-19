@extends('layouts.app')

@section('content')
@php
    use Illuminate\Support\Facades\DB;
    use Illuminate\Support\Facades\Schema;
    use Illuminate\Support\Carbon;

    $u = auth()->user();

    $canSeeCost = $u && (
        (method_exists($u, 'hasAnyRole') && $u->hasAnyRole(['admin', 'accounting', 'manager', 'management', 'warehouse']))
        || (method_exists($u, 'can') && ($u->can('finance.view') || $u->can('finance.manage')))
    );

    $q = request('q');
    $status = request('status');

    /* EGO_SITE_COMPANY_INDEX_DATA_START */
    $companyFilter = request('company_id');
    $egoSiteCompanyOptions = collect();
    $egoSiteCompanyMap = collect();

    if (Schema::hasTable('companies')) {
        $egoSiteCompanyQuery = DB::table('companies')->select('id', 'code', 'name');

        if (Schema::hasColumn('companies', 'is_active')) {
            $egoSiteCompanyQuery->where('is_active', 1);
        }

        $egoSiteCompanyOptions = $egoSiteCompanyQuery->orderBy('id')->get();
        $egoSiteCompanyMap = $egoSiteCompanyOptions->mapWithKeys(function ($c) {
            return [
                (int) $c->id => trim(($c->code ?? '') . ' - ' . ($c->name ?? '')),
            ];
        });
    }
    /* EGO_SITE_COMPANY_INDEX_DATA_END */
    $installedFrom = request('installed_from');
    $installedTo = request('installed_to');
    $completedFrom = request('completed_from');
    $completedTo = request('completed_to');

    $fmtNum = function($n){
        if ($n === null || $n === '') return null;
        $n = (float)$n;
        $s = rtrim(rtrim(number_format($n, 2), '0'), '.');
        return $s === '' ? null : $s;
    };

    $fmtMoney = function($n){
        return number_format((float)($n ?? 0), 0, ',', '.') . ' đ';
    };

    $fmtDate = function($d){
        if (empty($d)) return '—';
        try {
            return Carbon::parse($d)->format('d/m/Y');
        } catch (\Throwable $e) {
            return (string)$d;
        }
    };

    $splitNames = function($str){
        $str = (string)$str;
        return array_values(array_filter(array_map('trim', preg_split('/[,;]+/u', $str))));
    };

    $hasReceiptsSite = Schema::hasTable('receipts') && Schema::hasColumn('receipts', 'site_id');
    $hasMaterialRequestsSite = Schema::hasTable('material_requests') && Schema::hasColumn('material_requests', 'site_id');
    $hasMaterialRequestTotalCost = Schema::hasTable('material_requests') && Schema::hasColumn('material_requests', 'total_cost');
    $hasPaymentsSite = Schema::hasTable('payments') && Schema::hasColumn('payments', 'site_id');
    $hasPaymentRequestsSite = Schema::hasTable('payment_requests') && Schema::hasColumn('payment_requests', 'site_id');

    $pageTotalSites = $sites->count() ?? 0;
    $allTotalSites = method_exists($sites, 'total') ? $sites->total() : $pageTotalSites;

    $financeBySite = [];
    $totalContract = 0;
    $totalReceived = 0;
    $totalDebt = 0;
    $totalCost = 0;
    $totalProfit = 0;

    $installingCount = 0;
    $doneCount = 0;
    $wCount = 0;
    $planningCount = 0;

    foreach (($sites ?? []) as $s) {
        $siteId = (int)($s->id ?? 0);

        $st = strtolower((string)($s->status ?? ''));
        if ($st === 'planning') $planningCount++;
        if ($st === 'installing') $installingCount++;
        if ($st === 'done') $doneCount++;
        if ($st === 'warranty') $wCount++;

        $contract = (float)($s->contract_amount ?? 0);

        $received = 0;
        if ($siteId > 0 && $hasReceiptsSite) {
            $received = (float) DB::table('receipts')->where('site_id', $siteId)->sum('amount');
        }

        $materialCost = 0;
        if ($siteId > 0 && $hasMaterialRequestsSite && $hasMaterialRequestTotalCost) {
            $materialCost = (float) DB::table('material_requests')->where('site_id', $siteId)->sum('total_cost');
        }

        $paymentCost = 0;
        if ($siteId > 0 && $hasPaymentsSite) {
            $paymentCost = (float) DB::table('payments')->where('site_id', $siteId)->sum('amount');
        }

        $requestCost = 0;
        if ($siteId > 0 && $hasPaymentRequestsSite) {
            $query = DB::table('payment_requests')->where('site_id', $siteId);

            if (Schema::hasColumn('payment_requests', 'status')) {
                $query->where('status', 'accounting_approved');
            }

            $requestCost = (float) $query->sum('amount');
        }

        $laborCost = (float) ($s->labor_cost ?? 0);


        $transportCost = (float) ($s->transport_cost ?? 0);


        $siteOtherCost = (float) ($s->other_cost ?? 0);


        $siteExtraCost = $laborCost + $transportCost + $siteOtherCost;


        $cost = $materialCost + $paymentCost + $requestCost + $siteExtraCost;
        $debt = max(0, $contract - $received);
        $profit = $contract - $cost;

        $financeBySite[$siteId] = [
            'contract' => $contract,
            'received' => $received,
            'debt' => $debt,
            'material_cost' => $materialCost,
            'labor_cost' => $laborCost,
            'transport_cost' => $transportCost,
            'site_other_cost' => $siteOtherCost,
            'other_cost' => $paymentCost + $requestCost + $siteExtraCost,
            'extra_cost' => $siteExtraCost,
            'cost' => $cost,
            'profit' => $profit,
            'paid_percent' => $contract > 0 ? min(100, max(0, ($received / $contract) * 100)) : 0,
        ];

        $totalContract += $contract;
        $totalReceived += $received;
        $totalDebt += $debt;
        $totalCost += $cost;
        $totalProfit += $profit;
    }

    $totalPaidPercent = $totalContract > 0 ? min(100, max(0, ($totalReceived / $totalContract) * 100)) : 0;
@endphp

<div class="container-fluid px-4 py-3 ego-sites">

    {{-- HEADER --}}
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 ego-header mb-3">
        <div>
            <div class="d-flex align-items-center gap-2 mb-1">
                <span class="page-icon">
                    <i class="bi bi-buildings"></i>
                </span>
                <h4 class="fw-bold mb-0">Công trình</h4>
            </div>
            <div class="text-muted small">
                Quản lý công trình, doanh thu dự án, công nợ và tình trạng triển khai.
                @if($canSeeCost)
                    <span>Admin/kế toán/quản lý có thêm chi phí và lợi nhuận.</span>
                @endif
            </div>
        </div>

        <a href="{{ url('/cong-trinh/tao') }}" class="btn btn-ego">
            <i class="bi bi-plus-lg"></i> Tạo công trình
        </a>
    </div>

    @if(session('success'))
        <div class="alert alert-success py-2 mb-3 shadow-sm border-0" style="border-radius:14px;">
            <i class="bi bi-check-circle"></i> {{ session('success') }}
        </div>
    @endif

    @if(session('error'))
        <div class="alert alert-danger py-2 mb-3 shadow-sm border-0" style="border-radius:14px;">
            <i class="bi bi-exclamation-triangle"></i> {{ session('error') }}
        </div>
    @endif

    {{-- FILTER BAR --}}
    <div class="card border-0 shadow-ego ego-card mb-3" style="border-radius:18px;">
        <div class="card-body">
            <form method="GET" class="row g-2 align-items-end">
                <div class="col-lg-4">
                    <label class="form-label small text-muted mb-1">Tìm nhanh</label>
                    <div class="input-group ego-input-group">
                        <span class="input-group-text bg-white">
                            <i class="bi bi-search"></i>
                        </span>
                        <input type="text" name="q" value="{{ $q }}"
                               class="form-control"
                               placeholder="Tên công trình, địa chỉ, liên hệ, SĐT...">
                    </div>
                </div>

                <div class="col-lg-2">
                    <label class="form-label small text-muted mb-1">Trạng thái</label>
                    <select class="form-select" name="status" style="border-radius:13px;">
                        <option value="">-- Tất cả --</option>
                        <option value="planning"   {{ $status=='planning'?'selected':'' }}>Chuẩn bị</option>
                        <option value="installing" {{ $status=='installing'?'selected':'' }}>Đang lắp đặt</option>
                        <option value="done"       {{ $status=='done'?'selected':'' }}>Đã hoàn thành</option>
                        <option value="warranty"   {{ $status=='warranty'?'selected':'' }}>Đang bảo hành</option>
                    </select>
                </div>


                {{-- EGO_SITE_COMPANY_FILTER_START --}}
                <div class="col-lg-2">
                    <label class="form-label small text-muted mb-1">Công ty</label>
                    <select class="form-select" name="company_id" style="border-radius:13px;">
                        <option value="">-- Tất cả --</option>
                        @foreach($egoSiteCompanyOptions as $company)
                            <option value="{{ $company->id }}" {{ (string)$companyFilter === (string)$company->id ? 'selected' : '' }}>
                                {{ trim(($company->code ?? '') . ' - ' . ($company->name ?? '')) }}
                            </option>
                        @endforeach
                    </select>
                </div>
                {{-- EGO_SITE_COMPANY_FILTER_END --}}



                <div class="col-lg-2">
                    <label class="form-label small text-muted mb-1">Lắp đặt từ</label>
                    <input type="date" name="installed_from" value="{{ $installedFrom }}" class="form-control" style="border-radius:13px;">
                </div>

                <div class="col-lg-2">
                    <label class="form-label small text-muted mb-1">Lắp đặt đến</label>
                    <input type="date" name="installed_to" value="{{ $installedTo }}" class="form-control" style="border-radius:13px;">
                </div>

                <div class="col-lg-2">
                    <label class="form-label small text-muted mb-1">Hoàn thành từ</label>
                    <input type="date" name="completed_from" value="{{ $completedFrom }}" class="form-control" style="border-radius:13px;">
                </div>

                <div class="col-lg-2">
                    <label class="form-label small text-muted mb-1">Hoàn thành đến</label>
                    <input type="date" name="completed_to" value="{{ $completedTo }}" class="form-control" style="border-radius:13px;">
                </div>

                <div class="col-lg-10 d-flex gap-2 justify-content-lg-end">
                    <button class="btn btn-outline-ego" style="border-radius:13px;">
                        <i class="bi bi-funnel"></i> Lọc
                    </button>

                    <a class="btn btn-outline-secondary" href="{{ url('/cong-trinh') }}" title="Reset" style="border-radius:13px;">
                        <i class="bi bi-arrow-counterclockwise"></i>
                    </a>
                </div>
            </form>
        </div>
    </div>

    {{-- KPI FINANCE --}}
    <div class="row g-3 mb-3">
        <div class="{{ $canSeeCost ? 'col-md-3' : 'col-md-4' }}">
            <div class="card border-0 shadow-ego ego-card kpi-card kpi-finance" style="border-radius:18px;">
                <div class="card-body">
                    <div class="d-flex align-items-start justify-content-between gap-2">
                        <div>
                            <div class="text-muted small">Tổng doanh thu dự án</div>
                            <div class="fs-5 fw-black text-success">{{ $fmtMoney($totalContract) }}</div>
                            <div class="small text-muted">Giá trị hợp đồng các công trình đang hiển thị</div>
                        </div>
                        <div class="kpi-icon kpi-ego">
                            <i class="bi bi-cash-coin"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="{{ $canSeeCost ? 'col-md-3' : 'col-md-4' }}">
            <div class="card border-0 shadow-ego ego-card kpi-card" style="border-radius:18px;">
                <div class="card-body">
                    <div class="d-flex align-items-start justify-content-between gap-2">
                        <div>
                            <div class="text-muted small">Đã thu</div>
                            <div class="fs-5 fw-black text-primary">{{ $fmtMoney($totalReceived) }}</div>
                            <div class="small text-muted">{{ number_format($totalPaidPercent, 1) }}% tổng doanh thu</div>
                        </div>
                        <div class="kpi-icon kpi-info">
                            <i class="bi bi-wallet2"></i>
                        </div>
                    </div>
                    <div class="progress finance-progress mt-2">
                        <div class="progress-bar paid" style="width:{{ $totalPaidPercent }}%"></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="{{ $canSeeCost ? 'col-md-3' : 'col-md-4' }}">
            <div class="card border-0 shadow-ego ego-card kpi-card" style="border-radius:18px;">
                <div class="card-body">
                    <div class="d-flex align-items-start justify-content-between gap-2">
                        <div>
                            <div class="text-muted small">Công nợ còn lại</div>
                            <div class="fs-5 fw-black {{ $totalDebt > 0 ? 'text-danger' : 'text-success' }}">
                                {{ $fmtMoney($totalDebt) }}
                            </div>
                            <div class="small text-muted">{{ $totalDebt > 0 ? 'Cần theo dõi thu tiền' : 'Không còn công nợ' }}</div>
                        </div>
                        <div class="kpi-icon kpi-warn">
                            <i class="bi bi-exclamation-circle"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        @if($canSeeCost)
            <div class="col-md-3">
                <div class="card border-0 shadow-ego ego-card kpi-card" style="border-radius:18px;">
                    <div class="card-body">
                        <div class="d-flex align-items-start justify-content-between gap-2">
                            <div>
                                <div class="text-muted small">Lợi nhuận tạm tính</div>
                                <div class="fs-5 fw-black {{ $totalProfit >= 0 ? 'text-success' : 'text-danger' }}">
                                    {{ $fmtMoney($totalProfit) }}
                                </div>
                                <div class="small text-muted">Doanh thu - tổng chi phí</div>
                            </div>
                            <div class="kpi-icon kpi-profit">
                                <i class="bi bi-graph-up-arrow"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        @endif
    </div>

    {{-- KPI STATUS --}}
    <div class="row g-3 mb-3">
        @php
            $kpi = [
                ['label' => 'Tổng công trình',  'value' => $allTotalSites,              'icon' => 'bi-buildings',       'tone' => 'ego'],
                ['label' => 'Chuẩn bị',         'value' => $planningCount ?: '—',       'icon' => 'bi-hourglass-split', 'tone' => 'secondary'],
                ['label' => 'Đang lắp đặt',     'value' => $installingCount ?: '—',     'icon' => 'bi-tools',           'tone' => 'warn'],
                ['label' => 'Đang bảo hành',    'value' => $wCount ?: '—',              'icon' => 'bi-shield-check',    'tone' => 'info'],
            ];
        @endphp

        @foreach($kpi as $k)
            <div class="col-md-3">
                <div class="card border-0 shadow-ego ego-card kpi-card" style="border-radius:18px;">
                    <div class="card-body d-flex align-items-center justify-content-between">
                        <div>
                            <div class="text-muted small">{{ $k['label'] }}</div>
                            <div class="fs-5 fw-bold">{{ $k['value'] }}</div>
                        </div>
                        <div class="kpi-icon kpi-{{ $k['tone'] }}">
                            <i class="bi {{ $k['icon'] }}"></i>
                        </div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>
    {{-- TABLE --}}
    <div class="card border-0 shadow-ego ego-card" style="border-radius:18px;">
        <div class="card-header bg-white border-0 py-3 d-flex flex-wrap justify-content-between align-items-center gap-2"
             style="border-radius:18px 18px 0 0;">
            <div>
                <div class="fw-bold">Danh sách công trình</div>
                <div class="text-muted small">Theo dõi kỹ thuật + tài chính dự án trên cùng một bảng.</div>
            </div>

            <div class="text-muted small">
                Hiển thị: <b>{{ $sites->count() ?? 0 }}</b>
            </div>
        </div>

        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table align-middle mb-0 ego-table-modern">
                    <thead class="table-light">
                    <tr>
                        <th style="width:58px;" class="text-center">#</th>
                        <th style="min-width:310px;">Công trình</th>
                        <th style="min-width:210px;">Liên hệ</th>
                        <th style="width:170px;" class="text-center">Hệ</th>

                        <th style="min-width:220px;">Tài chính</th>
                        <th style="width:135px;" class="text-center">Lắp đặt</th>
                        <th style="width:135px;" class="text-center">BH đến</th>
                        <th style="width:140px;" class="text-center">Trạng thái</th>
                        <th style="min-width:170px;">Phụ trách</th>
                        <th style="width:88px;" class="text-center">Thao tác</th>
                    </tr>
                    </thead>

                    <tbody>
                    @forelse($sites as $i => $site)
                        @php
                            $siteId = (int)($site->id ?? 0);
                            $finance = $financeBySite[$siteId] ?? [
                                'contract' => 0,
                                'received' => 0,
                                'debt' => 0,
                                'cost' => 0,
                                'profit' => 0,
                                'paid_percent' => 0,
                            ];

                            $kwp = $fmtNum($site->system_kwp ?? null);
                            $kw  = $fmtNum($site->system_kw_ac ?? null);

                            $installedAt = $site->installed_at ?? $site->install_date ?? $site->installation_date ?? null;
                            $warrantyTo  = $site->warranty_to ?? $site->warranty_until ?? $site->warranty_end ?? null;

                            $st = strtolower((string)($site->status ?? ''));

                            $stLabel = '—';
                            $stBadge = 'secondary';
                            $stIcon  = 'bi-dot';

                            if ($st === 'planning') {
                                $stLabel='Chuẩn bị';
                                $stBadge='secondary';
                                $stIcon='bi-hourglass-split';
                            } elseif ($st === 'installing') {
                                $stLabel='Đang lắp đặt';
                                $stBadge='warning';
                                $stIcon='bi-tools';
                            } elseif ($st === 'done') {
                                $stLabel='Hoàn thành';
                                $stBadge='success';
                                $stIcon='bi-check2-circle';
                            } elseif ($st === 'warranty') {
                                $stLabel='Bảo hành';
                                $stBadge='info';
                                $stIcon='bi-shield-check';
                            }

                            $owner = $site->owner_name
                                ?? optional($site->owner)->name
                                ?? $site->technician_name
                                ?? optional($site->technician)->name
                                ?? '—';

                            $ownerChips = $splitNames($owner);
                            $rowNo = method_exists($sites, 'firstItem') && $sites->firstItem()
                                ? $sites->firstItem() + $i
                                : $i + 1;
                        @endphp

                        <tr class="ego-row">
                            <td class="text-center text-muted">{{ $rowNo }}</td>

                            <td>
                                <a href="{{ url('/cong-trinh/'.$site->id) }}" class="site-title">
                                    {{ $site->name ?? '—' }}
                                </a>


                                {{-- EGO_SITE_COMPANY_ROW_START --}}
                                @if(!empty($site->company_id))
                                    <div class="mt-1">
                                        <span class="pill-soft pill-slate">
                                            <i class="bi bi-building"></i>
                                            {{ $egoSiteCompanyMap[(int)$site->company_id] ?? ('Công ty #' . $site->company_id) }}
                                        </span>
                                    </div>
                                @else
                                    <div class="mt-1">
                                        <span class="pill-soft pill-muted">
                                            <i class="bi bi-building"></i> Chưa chọn công ty
                                        </span>
                                    </div>
                                @endif
                                {{-- EGO_SITE_COMPANY_ROW_END --}}


                                <div class="text-muted small d-flex flex-wrap gap-2 mt-1">
                                    <span class="d-inline-flex align-items-center gap-1">
                                        <i class="bi bi-geo-alt"></i> {{ $site->address ?? '—' }}
                                    </span>

                                    @if(!empty($site->note))
                                        <span class="text-truncate d-inline-flex align-items-center gap-1" style="max-width: 420px;">
                                            <i class="bi bi-journal-text"></i> {{ $site->note }}
                                        </span>
                                    @endif
                                </div>
                            </td>

                            <td>
                                <div class="fw-semibold">{{ $site->contact_name ?? '—' }}</div>
                                <div class="text-muted small d-inline-flex align-items-center gap-1 mt-1">
                                    <i class="bi bi-telephone"></i> {{ $site->contact_phone ?? '—' }}
                                </div>
                            </td>

                            {{-- HỆ --}}
                            <td class="text-center">
                                @if(!$kwp && !$kw)
                                    <span class="pill-soft pill-muted">Chưa nhập</span>
                                @else
                                    <div class="d-flex flex-column align-items-center gap-1">
                                        <span class="pill-soft pill-ego">{{ $kwp ?? '—' }} <span class="pill-unit">kWp</span></span>
                                        <span class="pill-soft pill-slate">{{ $kw ?? '—' }} <span class="pill-unit">kW</span></span>
                                    </div>
                                @endif
                            </td>

                            {{-- TÀI CHÍNH --}}
                            <td>
                                <div class="finance-cell">
                                    <div class="d-flex justify-content-between align-items-center gap-2">
                                        <span class="text-muted small">Doanh thu</span>
                                        <b class="text-success">{{ $fmtMoney($finance['contract']) }}</b>
                                    </div>

                                    <div class="d-flex justify-content-between align-items-center gap-2 mt-1">
                                        <span class="text-muted small">Đã thu</span>
                                        <b class="text-primary">{{ $fmtMoney($finance['received']) }}</b>
                                    </div>

                                    <div class="progress finance-progress mt-2">
                                        <div class="progress-bar paid" style="width:{{ $finance['paid_percent'] }}%"></div>
                                    </div>

                                    <div class="small text-muted mt-1">
                                        {{ number_format($finance['paid_percent'], 1) }}% đã thu
                                    </div>
                                </div>
                            </td>

                            <td class="text-center">
                                <span class="pill-date">{{ $fmtDate($installedAt) }}</span>
                            </td>

                            <td class="text-center">
                                <span class="pill-date">{{ $fmtDate($warrantyTo) }}</span>
                            </td>

                            <td class="text-center">
                                <span class="badge bg-{{ $stBadge }} pill-badge">
                                    <i class="bi {{ $stIcon }}"></i> {{ $stLabel }}
                                </span>
                            </td>

                            <td>
                                @if(count($ownerChips) > 0 && $ownerChips[0] !== '—')
                                    <div class="d-flex flex-wrap gap-1">
                                        @foreach($ownerChips as $oc)
                                            <span class="pill-soft pill-owner">{{ $oc }}</span>
                                        @endforeach
                                    </div>
                                @else
                                    <span class="text-muted">—</span>
                                @endif
                            </td>

                            <td class="text-center">
                                <div class="dropdown">
                                    <button class="btn btn-sm btn-action" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                        <i class="bi bi-three-dots-vertical"></i>
                                    </button>

                                    <ul class="dropdown-menu dropdown-menu-end shadow-sm" style="border-radius:14px;">
                                        <li>
                                            <a class="dropdown-item" href="{{ url('/cong-trinh/'.$site->id) }}">
                                                <i class="bi bi-eye me-2"></i> Xem chi tiết
                                            </a>
                                        </li>

                                        <li>
                                            <a class="dropdown-item" href="{{ url('/cong-trinh/'.$site->id.'/sua') }}">
                                                <i class="bi bi-pencil-square me-2"></i> Sửa
                                            </a>
                                        </li>

                                        <li>
                                            <a class="dropdown-item" href="{{ url('/don-vat-tu/tao?site_id='.$site->id) }}">
                                                <i class="bi bi-box-seam me-2"></i> Tạo đơn vật tư
                                            </a>
                                        </li>

                                        <li><hr class="dropdown-divider"></li>

                                        <li>
                                            <form method="POST"
                                                  action="{{ url('/cong-trinh/'.$site->id.'/xoa') }}"
                                                  onsubmit="return confirm('Xoá công trình này?')">
                                                @csrf
                                                <button class="dropdown-item text-danger" type="submit">
                                                    <i class="bi bi-trash me-2"></i> Xoá
                                                </button>
                                            </form>
                                        </li>
                                    </ul>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="10" class="text-center text-muted py-5">
                                <div class="empty-state">
                                    <div class="empty-icon">
                                        <i class="bi bi-buildings"></i>
                                    </div>
                                    <div class="fw-bold mt-2">Chưa có công trình nào</div>
                                    <div class="small text-muted">Tạo công trình đầu tiên để bắt đầu theo dõi doanh thu, công nợ và chi phí.</div>
                                    <a href="{{ url('/cong-trinh/tao') }}" class="btn btn-ego mt-3">
                                        <i class="bi bi-plus-lg"></i> Tạo công trình
                                    </a>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        @if(method_exists($sites, 'links'))
            <div class="card-footer bg-white border-0 py-3">
                {{ $sites->links() }}
            </div>
        @endif
    </div>

</div>

<style>
    .ego-header{
        margin-top: 6px;
    }

    .ego-sites{
        background:
            radial-gradient(circle at top left, rgba(11, 201, 170, .13), transparent 26%),
            linear-gradient(180deg,
                rgba(11, 201, 170, .10) 0%,
                rgba(11, 201, 170, .06) 22%,
                rgba(255,255,255,0) 70%);
        border-radius: 22px;
        padding-top: 18px;
        padding-bottom: 18px;
    }

    .page-icon{
        width: 42px;
        height: 42px;
        border-radius: 16px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        background: linear-gradient(135deg, rgba(11,201,170,.20), rgba(59,130,246,.13));
        color: #0f766e;
        border: 1px solid rgba(11,201,170,.22);
        box-shadow: 0 12px 26px rgba(2,44,34,.08);
    }

    .ego-card{
        background: rgba(255,255,255,.95);
        backdrop-filter: blur(8px);
        border: 1px solid rgba(15, 118, 110, .07) !important;
    }

    .shadow-ego{
        box-shadow: 0 14px 38px rgba(2, 44, 34, 0.08) !important;
    }

    .fw-black{
        font-weight: 900;
    }

    .btn-ego{
        background: linear-gradient(135deg, #0BC9AA, #08b79b);
        border: 0;
        color: #fff;
        border-radius: 14px;
        padding: 10px 14px;
        font-weight: 800;
        box-shadow: 0 10px 22px rgba(11,201,170,.24);
    }

    .btn-ego:hover{
        color: #fff;
        transform: translateY(-1px);
        box-shadow: 0 14px 28px rgba(11,201,170,.32);
    }

    .btn-outline-ego{
        border-color: rgba(11, 201, 170, .55);
        color: #0f766e;
        background: rgba(11, 201, 170, .10);
        border-radius: 13px;
        font-weight: 700;
    }

    .btn-outline-ego:hover{
        border-color: rgba(11, 201, 170, .75);
        background: rgba(11, 201, 170, .16);
        color: #0f766e;
    }

    .ego-input-group .input-group-text{
        border-radius: 13px 0 0 13px;
        border-color: rgba(15,23,42,.12);
    }

    .ego-input-group .form-control{
        border-radius: 0 13px 13px 0;
        border-color: rgba(15,23,42,.12);
    }

    .form-control:focus,
    .form-select:focus{
        border-color: rgba(11,201,170,.7);
        box-shadow: 0 0 0 .2rem rgba(11,201,170,.12);
    }

    /* KPI */
    .kpi-card{
        border: 1px solid rgba(11,201,170,.14) !important;
        min-height: 112px;
    }

    .kpi-finance{
        background:
            radial-gradient(circle at top right, rgba(11,201,170,.18), transparent 42%),
            rgba(255,255,255,.95);
    }

    .kpi-icon{
        width: 46px;
        height: 46px;
        border-radius: 15px;
        display:flex;
        align-items:center;
        justify-content:center;
        font-size: 18px;
        border: 1px solid rgba(0,0,0,.06);
        flex: 0 0 auto;
    }

    .kpi-warn{
        background: rgba(245,158,11,.12);
        color: #b45309;
    }

    .kpi-info{
        background: rgba(59,130,246,.10);
        color: #1d4ed8;
    }

    .kpi-ego{
        background: rgba(11,201,170,.12);
        color: #0f766e;
    }

    .kpi-secondary{
        background: rgba(100,116,139,.10);
        color: #475569;
    }

    .kpi-profit{
        background: rgba(34,197,94,.10);
        color: #047857;
    }

    .finance-progress{
        height: 8px;
        border-radius: 999px;
        background: rgba(15,23,42,.08);
        overflow: hidden;
    }

    .finance-progress .progress-bar{
        border-radius: 999px;
        transition: width .25s ease;
    }

    .finance-progress .progress-bar.paid{
        background: linear-gradient(90deg, #0BC9AA, #10b981);
    }

    /* TABLE modern */
    .ego-table-modern thead th{
        font-size: .82rem;
        color: #334155;
        white-space: nowrap;
        vertical-align: middle;
        border-bottom: 1px solid rgba(0,0,0,.06) !important;
        background: #f8fafc;
    }

    .ego-table-modern tbody td{
        padding-top: 1rem;
        padding-bottom: 1rem;
        vertical-align: middle;
        border-top: 1px solid rgba(0,0,0,.04) !important;
    }

    .ego-row:hover{
        background: rgba(11,201,170,.05);
        transition: .15s ease;
    }

    .site-title{
        color: #0f172a;
        font-weight: 800;
        text-decoration: none;
    }

    .site-title:hover{
        color: #0f766e;
    }

    /* Pills */
    .pill-soft{
        display:inline-flex;
        align-items:center;
        gap: 6px;
        padding: 6px 10px;
        border-radius: 999px;
        border: 1px solid rgba(0,0,0,.06);
        font-size: 12px;
        line-height: 1;
        white-space: nowrap;
        font-weight: 700;
    }

    .pill-unit{
        opacity: .75;
        font-weight: 600;
    }

    .pill-ego{
        background: rgba(11,201,170,.10);
        color: #0f766e;
        border-color: rgba(11,201,170,.22);
    }

    .pill-slate{
        background: rgba(100,116,139,.10);
        color: #334155;
        border-color: rgba(100,116,139,.18);
    }

    .pill-muted{
        background: rgba(148,163,184,.12);
        color: #64748b;
        border-color: rgba(148,163,184,.22);
    }

    .pill-owner{
        background: rgba(59,130,246,.08);
        color: #1d4ed8;
        border-color: rgba(59,130,246,.18);
    }

    .pill-date{
        display:inline-flex;
        align-items:center;
        padding: 6px 10px;
        border-radius: 999px;
        background: rgba(255,255,255,.9);
        border: 1px solid rgba(0,0,0,.06);
        font-size: 12px;
        line-height: 1;
        color: #0f172a;
        white-space: nowrap;
    }

    .pill-badge{
        border-radius: 999px;
        padding: 7px 10px;
        font-weight: 700;
        font-size: 12px;
        white-space: nowrap;
    }

    .finance-cell{
        min-width: 200px;
        border: 1px solid rgba(15,23,42,.06);
        background: rgba(248,250,252,.75);
        border-radius: 14px;
        padding: 10px;
    }

    .debt-box{
        border-radius: 14px;
        padding: 9px 10px;
        border: 1px solid rgba(15,23,42,.06);
        min-width: 120px;
    }

    .debt-danger{
        background: #fff1f2;
        border-color: #fecdd3;
        color: #dc2626;
    }

    .debt-ok{
        background: #ecfdf5;
        border-color: #bbf7d0;
        color: #047857;
    }

    /* Modern action button */
    .btn-action{
        border: 1px solid rgba(0,0,0,.08);
        background: rgba(255,255,255,.95);
        border-radius: 12px;
        padding: 6px 8px;
    }

    .btn-action:hover{
        background: rgba(11,201,170,.10);
        border-color: rgba(11,201,170,.35);
        color: #0f766e;
    }

    .empty-state{
        padding: 20px;
    }

    .empty-icon{
        width: 60px;
        height: 60px;
        margin: 0 auto;
        border-radius: 20px;
        background: rgba(11,201,170,.10);
        color: #0f766e;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 26px;
    }

    @media (max-width: 992px){
        .ego-sites{
            padding-left: 0 !important;
            padding-right: 0 !important;
        }

        .finance-cell{
            min-width: 220px;
        }
    }
</style>
@endsection