@extends('layouts.app')

@section('title', 'Bảng KPI và lương hiệu suất')

@push('styles')
    @include('technical.partials.plan-styles')
    <link rel="stylesheet"
          href="{{ asset('css/technical-kpi.css') }}?v={{ file_exists(public_path('css/technical-kpi.css')) ? filemtime(public_path('css/technical-kpi.css')) : '1.0.0' }}">
@endpush

@section('content')
@php
    $pct = static fn ($v, int $dec = 1) => $v !== null ? number_format((float) $v * 100, $dec, ',', '.').'%' : 'Chưa đủ dữ liệu';
    $money = static fn ($v) => $v !== null ? number_format((float) $v, 0, ',', '.').' đ' : 'Chưa thể tính';

    // Khởi tạo quý cho dropdown
    $quarterOptions = [];
    $cursor = now()->startOfMonth();
    for ($i = 0; $i < 8; $i++) {
        $key = $cursor->year.'-Q'.(int) ceil($cursor->month / 3);
        $quarterOptions[$key] = 'Quý '.(int) ceil($cursor->month / 3).'/'.$cursor->year;
        $cursor->subMonthsNoOverflow(3);
    }
    if (! isset($quarterOptions[$selectedQuarter])) {
        $quarterOptions[$selectedQuarter] = str_replace(['-Q'], [' · Quý '], $selectedQuarter);
    }

    // Avatar initials helper
    $getInitials = function ($name) {
        $clean = trim((string) preg_replace('/\[.*?\]/', '', (string) $name));
        $words = array_values(array_filter(explode(' ', $clean)));
        if (count($words) >= 2) {
            return mb_strtoupper(mb_substr($words[0], 0, 1) . mb_substr(end($words), 0, 1));
        }
        return mb_strtoupper(mb_substr($clean, 0, 2));
    };

    // Nhân viên đang được chọn xem chi tiết
    $hasSelected = !empty($selectedEngineer);
    $selCriteria = $hasSelected ? ($selectedEngineer['result']['criteria'] ?? []) : [];
@endphp

<!-- TECHNICAL_KPI_CONTENT_START -->
<div class="container-fluid py-3 tw-wrap">

    {{-- Breadcrumb dùng chung cho khu vực Kỹ thuật --}}
    @include('technical.partials.dashboard-breadcrumb', [
        'trail' => [
            'Kỹ thuật' => route('technical.dashboard'),
            'KPI' => null,
        ],
    ])

    {{-- Header chuẩn giao diện Kỹ thuật --}}
    <div class="tw-head">
        <div>
            <h1 class="tw-head__title">Bảng KPI và lương hiệu suất</h1>
            <p class="tw-head__sub">
                Đối soát tiến độ % KPI gắn với quỹ lương hiệu suất trước khi chốt kỳ lương.
            </p>
        </div>
        <div class="tw-head__actions">
            @if($canInputKpi ?? false)
                <a href="{{ route('ky-thuat.kpis.input', ['month' => $selectedMonth]) }}" class="btn btn-sm btn-primary">
                    <i class="bi bi-pencil-square"></i> Nhập số liệu tháng
                </a>
            @endif
            @if($canConfigureKpi)
                <a href="{{ route('ky-thuat.kpis.config') }}" class="btn btn-sm btn-outline-secondary">
                    <i class="bi bi-gear"></i> Cài đặt KPI
                </a>
            @endif
        </div>
    </div>

    {{-- Cảnh báo ngắn nếu chưa có cấu hình lương --}}
    @if(!$hasConfig)
        <div class="alert alert-warning py-2 mb-3" role="alert">
            <i class="bi bi-exclamation-triangle me-1"></i> Chưa thiết lập công thức KPI áp dụng cho kỳ này.
        </div>
    @endif

    {{-- Thẻ tổng quan (Dùng đúng component tw-stat / tp-cards của Dashboard Kỹ thuật) --}}
    <div class="tp-cards mb-3">
        <div class="tw-stat">
            <span class="tw-stat__icon"><i class="bi bi-people"></i></span>
            <span>
                <span class="tw-stat__value d-block">{{ $payrollSummary['total_engineers'] }}</span>
                <span class="tw-stat__label d-block">Tổng nhân viên</span>
            </span>
        </div>

        <div class="tw-stat">
            <span class="tw-stat__icon"><i class="bi bi-cash-stack"></i></span>
            <span>
                <span class="tw-stat__value d-block">
                    {{ $payrollSummary['total_base_salary'] !== null ? number_format($payrollSummary['total_base_salary'], 0, ',', '.') . ' đ' : 'Chưa đủ dữ liệu' }}
                </span>
                <span class="tw-stat__label d-block">Tổng quỹ lương cơ bản</span>
            </span>
        </div>

        <div class="tw-stat tw-stat--warn">
            <span class="tw-stat__icon"><i class="bi bi-graph-up-arrow"></i></span>
            <span>
                <span class="tw-stat__value d-block">
                    {{ $payrollSummary['total_kpi_salary'] !== null ? number_format($payrollSummary['total_kpi_salary'], 0, ',', '.') . ' đ' : 'Chưa đủ dữ liệu' }}
                </span>
                <span class="tw-stat__label d-block">Tổng tiền KPI</span>
            </span>
        </div>

        <div class="tw-stat tw-stat--ok">
            <span class="tw-stat__icon"><i class="bi bi-wallet2"></i></span>
            <span>
                <span class="tw-stat__value d-block">
                    {{ $payrollSummary['total_projected_income'] !== null ? number_format($payrollSummary['total_projected_income'], 0, ',', '.') . ' đ' : 'Chưa đủ dữ liệu' }}
                </span>
                <span class="tw-stat__label d-block">Tổng thu nhập dự kiến</span>
            </span>
        </div>

        <div class="tw-stat">
            <span class="tw-stat__icon"><i class="bi bi-hourglass-split"></i></span>
            <span>
                <span class="tw-stat__value d-block">{{ $payrollSummary['pending_approval_count'] }}</span>
                <span class="tw-stat__label d-block">Bảng lương chờ duyệt</span>
            </span>
        </div>
    </div>

    {{-- Bộ lọc (Cùng kiểu form filter với Kế hoạch & Giao việc) --}}
    <div class="tw-card mb-3">
        <div class="tw-card__body">
            <form method="GET" action="{{ route('ky-thuat.kpis.index') }}" class="tp-filters">
                @if($hasSelected)
                    <input type="hidden" name="user_id" value="{{ $selectedEngineer['id'] }}">
                @endif

                <div>
                    <label class="form-label small" for="kpi-period">Chế độ kỳ</label>
                    <select class="form-select form-select-sm" id="kpi-period" name="period" onchange="togglePeriodInputs(this.value)">
                        <option value="month" @selected($periodType === 'month')>Theo tháng</option>
                        <option value="quarter" @selected($periodType === 'quarter')>Theo quý</option>
                    </select>
                </div>

                <div id="filter-month-box" @if($periodType !== 'month') style="display:none;" @endif>
                    <label class="form-label small" for="kpi-month">Chọn tháng</label>
                    <input type="month" class="form-control form-control-sm" id="kpi-month" name="month" value="{{ $selectedMonth }}">
                </div>

                <div id="filter-quarter-box" @if($periodType !== 'quarter') style="display:none;" @endif>
                    <label class="form-label small" for="kpi-quarter">Chọn quý</label>
                    <select class="form-select form-select-sm" id="kpi-quarter" name="quarter">
                        @foreach($quarterOptions as $value => $label)
                            <option value="{{ $value }}" @selected($selectedQuarter === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>

                @if($canViewList)
                <div>
                    <label class="form-label small" for="kpi-dept">Phòng ban</label>
                    <select class="form-select form-select-sm" id="kpi-dept" name="department_id">
                        <option value="">Tất cả phòng ban</option>
                        @foreach($departments as $dept)
                            <option value="{{ $dept->id }}" @selected((int) $selectedDepartmentId === (int) $dept->id)>
                                {{ $dept->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="form-label small" for="kpi-user">Nhân viên</label>
                    <select class="form-select form-select-sm" id="kpi-user" name="user_id">
                        <option value="">Tất cả nhân viên</option>
                        @foreach($allEngineers as $eng)
                            <option value="{{ $eng['id'] }}" @selected((int) $selectedUserId === (int) $eng['id'])>
                                {{ $eng['name'] }} ({{ $eng['code'] }})
                            </option>
                        @endforeach
                    </select>
                </div>
                @endif

                <div>
                    <label class="form-label small" for="kpi-status">Trạng thái duyệt</label>
                    <select class="form-select form-select-sm" id="kpi-status" name="approval_status">
                        <option value="">Tất cả trạng thái</option>
                        <option value="pending_calc" @selected($selectedApprovalStatus === 'pending_calc')>Chưa tính</option>
                        <option value="draft" @selected($selectedApprovalStatus === 'draft')>Chờ duyệt</option>
                        <option value="approved" @selected($selectedApprovalStatus === 'approved')>Đã duyệt</option>
                    </select>
                </div>

                <div class="tp-filters__actions">
                    <button type="submit" class="btn btn-sm btn-primary">
                        <i class="bi bi-funnel"></i> Áp dụng bộ lọc
                    </button>
                    @if(request()->hasAny(['department_id', 'approval_status', 'site_id', 'user_id']) || ($periodType === 'quarter') || ($selectedMonth !== now()->format('Y-m')))
                        <a href="{{ route('ky-thuat.kpis.index') }}" class="btn btn-sm btn-outline-secondary">
                            Đặt lại
                        </a>
                    @endif
                </div>
            </form>
        </div>
    </div>

    {{-- Bảng danh sách nhân viên (Dùng đúng table component hiện có) --}}
    <div class="tw-card mb-3">
        <div class="tw-card__head">
            <h2 class="tw-card__title">Bảng lương hiệu suất nhân viên (Kỳ: {{ $periodLabel }})</h2>
            <span class="text-muted small">{{ count($engineers) }} bản ghi</span>
        </div>

        @if($engineers->isEmpty())
            <div class="tw-card__body text-center py-4 text-muted">
                Không tìm thấy nhân viên kỹ thuật nào phù hợp với bộ lọc hiện tại.
            </div>
        @else
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0 tp-table">
                    <thead>
                        <tr>
                            <th>Nhân viên</th>
                            <th>Mã NV</th>
                            <th class="text-end">Lương cơ bản</th>
                            <th class="text-end">Mức lương KPI</th>
                            <th class="text-center">KPI đạt (%)</th>
                            <th class="text-end">Tiền KPI</th>
                            <th class="text-end">Tổng lương dự kiến</th>
                            <th class="text-center">Trạng thái</th>
                            <th class="text-end">Thao tác</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($engineers as $idx => $e)
                        @php
                            $isSelected = $hasSelected && ($selectedEngineer['id'] === $e['id']);
                            $kpiPct = $e['kpi_percent'];
                            $kpiMoney = $e['real_kpi_salary'];
                            $totIncome = $e['total_income'];
                            $pStatus = $e['payroll_status'];
                            $initials = $getInitials($e['name']);
                        @endphp
                        <tr class="{{ $isSelected ? 'is-selected' : '' }}" style="cursor: pointer;"
                            onclick="window.location='{{ route('ky-thuat.kpis.index', array_merge(request()->query(), ['user_id' => $e['id']])) }}#kpi-detail-view'">
                            <td>
                                <div class="tp-person">
                                    <span class="tp-person__avatar">{{ $initials }}</span>
                                    <span>
                                        <span class="tp-table__title mb-0">{{ $e['name'] }}</span>
                                        <span class="tp-table__sub">{{ $e['position'] }} · {{ $e['department'] }}</span>
                                    </span>
                                </div>
                            </td>
                            <td>
                                <span class="badge bg-light text-dark border">{{ $e['code'] }}</span>
                            </td>
                            <td class="text-end">
                                @if($e['base_salary'] !== null)
                                    <span class="fw-semibold">{{ number_format($e['base_salary'], 0, ',', '.') }} đ</span>
                                @else
                                    <span class="text-muted small">Chưa thể tính</span>
                                @endif
                            </td>
                            <td class="text-end">
                                @if($e['kpi_base_salary'] !== null)
                                    <span>{{ number_format($e['kpi_base_salary'], 0, ',', '.') }} đ</span>
                                @else
                                    <span class="text-muted small">Chưa thể tính</span>
                                @endif
                            </td>
                            <td class="text-center">
                                @if($kpiPct !== null)
                                    <span class="fw-bold {{ $kpiPct > 1 + 1e-9 ? 'text-success' : 'text-primary' }}">{{ $pct($kpiPct) }}</span>
                                    @if(($e['result']['adjustment_points'] ?? 0) != 0)
                                        <div class="small text-muted">gồm {{ $e['result']['adjustment_points'] > 0 ? '+' : '' }}{{ rtrim(rtrim(number_format($e['result']['adjustment_points'], 2, ',', '.'), '0'), ',') }} điểm điều chỉnh</div>
                                    @endif
                                @else
                                    <span class="text-muted small">Chưa đủ dữ liệu</span>
                                @endif
                                @if(! empty($e['missing']))
                                    <div class="small text-warning-emphasis mt-1" title="Cần bổ sung để tính được KPI và lương">
                                        Thiếu: {{ implode(', ', $e['missing']) }}
                                    </div>
                                @endif
                            </td>
                            <td class="text-end">
                                @if($kpiMoney !== null)
                                    <span class="fw-semibold text-success">{{ number_format($kpiMoney, 0, ',', '.') }} đ</span>
                                @else
                                    <span class="text-muted small">Chưa thể tính</span>
                                @endif
                            </td>
                            <td class="text-end">
                                @if($totIncome !== null)
                                    <span class="fw-bold text-dark">{{ number_format($totIncome, 0, ',', '.') }} đ</span>
                                @else
                                    <span class="text-muted small">Chưa thể tính</span>
                                @endif
                            </td>
                            <td class="text-center">
                                @if($pStatus === 'approved')
                                    <span class="tp-state tp-state--success">Đã duyệt</span>
                                @elseif($pStatus === 'draft')
                                    <span class="tp-state tp-state--warning">Chờ duyệt</span>
                                @else
                                    <span class="tp-state">Chưa tính</span>
                                @endif
                            </td>
                            <td class="text-end" onclick="event.stopPropagation();">
                                @if($isSelected)
                                    <span class="btn btn-sm btn-primary disabled">Đang xem</span>
                                @else
                                    <a href="{{ route('ky-thuat.kpis.index', array_merge(request()->query(), ['user_id' => $e['id']])) }}#kpi-detail-view"
                                       class="btn btn-sm btn-outline-primary">
                                        Xem chi tiết <i class="bi bi-chevron-right"></i>
                                    </a>
                                @endif
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    {{-- Chi tiết KPI nhân viên được chọn --}}
    @if($hasSelected)
    @php
        $selName = $selectedEngineer['name'];
        $selCode = $selectedEngineer['code'];
        $selPos = $selectedEngineer['position'];
        $selDept = $selectedEngineer['department'];
        $selAgreedSalary = $selectedEngineer['agreed_salary'];
        $selBaseRate = $selectedEngineer['base_rate'];
        $selKpiRate = $selectedEngineer['kpi_rate'];
        $selBaseSalary = $selectedEngineer['base_salary'];
        $selKpiBaseSalary = $selectedEngineer['kpi_base_salary'];
        $selKpiPct = $selectedEngineer['kpi_percent'];
        $selKpiMoney = $selectedEngineer['real_kpi_salary'];
        $selTotIncome = $selectedEngineer['total_income'];
        $selStatus = $selectedEngineer['payroll_status'];
        $selTierLabel = $selectedEngineer['tier_label'] ?? null;
    @endphp
    <div class="tw-card mb-3" id="kpi-detail-view">
        <div class="tw-card__head">
            <div>
                <h2 class="tw-card__title">
                    Chi tiết KPI & lương: {{ $selName }}
                    <span class="badge bg-light text-primary border ms-2">{{ $selCode }}</span>
                </h2>
                <div class="tw-head__sub">
                    {{ $selPos }} · {{ $selDept }} · Kỳ: {{ $periodLabel }}
                </div>
            </div>
            <div class="d-flex align-items-center gap-2">
                @if($selStatus === 'approved')
                    <span class="tp-state tp-state--success">Đã duyệt</span>
                @elseif($selStatus === 'draft')
                    <span class="tp-state tp-state--warning">Chờ duyệt</span>
                @else
                    <span class="tp-state">Chưa tính</span>
                @endif

                @if($canViewList)
                    <a href="{{ route('ky-thuat.kpis.index', request()->except('user_id')) }}" class="btn btn-sm btn-outline-secondary">
                        <i class="bi bi-x-lg"></i> Đóng chi tiết
                    </a>
                @endif
            </div>
        </div>

        <div class="tw-card__body">
            {{-- Dòng tính lương: 5-6 khối nhỏ đồng bộ với summary card hiện tại, không dùng gradient hay emoji --}}
            <div class="tw-kpi-flow">
                <div class="tw-kpi-step">
                    <div class="tw-kpi-step__header">
                        <span class="tw-kpi-step__num">Bước 1</span>
                    </div>
                    <div class="tw-kpi-step__title">Lương thỏa thuận</div>
                    <div class="tw-kpi-step__val {{ $selAgreedSalary === null ? 'tw-kpi-step__val--muted' : '' }}">
                        {{ $selAgreedSalary !== null ? number_format($selAgreedSalary, 0, ',', '.') . ' đ' : 'Chưa có dữ liệu' }}
                    </div>
                    <div class="tw-kpi-step__desc">
                        Hợp đồng lao động hoặc thỏa thuận
                    </div>
                </div>

                <div class="tw-kpi-step">
                    <div class="tw-kpi-step__header">
                        <span class="tw-kpi-step__num">Bước 2</span>
                        <span class="tw-kpi-step__badge">
                            {{ $selBaseRate !== null ? number_format($selBaseRate * 100, 0) . '%' : 'Chờ cấu hình' }}
                        </span>
                    </div>
                    <div class="tw-kpi-step__title">Lương cố định</div>
                    <div class="tw-kpi-step__val {{ $selBaseSalary === null ? 'tw-kpi-step__val--muted' : '' }}">
                        {{ $selBaseSalary !== null ? number_format($selBaseSalary, 0, ',', '.') . ' đ' : 'Chưa thể tính' }}
                    </div>
                    <div class="tw-kpi-step__desc">
                        = Lương thỏa thuận × Tỷ lệ cố định
                    </div>
                </div>

                <div class="tw-kpi-step">
                    <div class="tw-kpi-step__header">
                        <span class="tw-kpi-step__num">Bước 3</span>
                        <span class="tw-kpi-step__badge">
                            {{ $selKpiRate !== null ? number_format($selKpiRate * 100, 0) . '%' : 'Chờ cấu hình' }}
                        </span>
                    </div>
                    <div class="tw-kpi-step__title">Quỹ lương KPI</div>
                    <div class="tw-kpi-step__val {{ $selKpiBaseSalary === null ? 'tw-kpi-step__val--muted' : '' }}">
                        {{ $selKpiBaseSalary !== null ? number_format($selKpiBaseSalary, 0, ',', '.') . ' đ' : 'Chưa thể tính' }}
                    </div>
                    <div class="tw-kpi-step__desc">
                        = Lương thỏa thuận × Tỷ lệ quỹ KPI
                    </div>
                </div>

                <div class="tw-kpi-step">
                    <div class="tw-kpi-step__header">
                        <span class="tw-kpi-step__num">Bước 4</span>
                        @if($selTierLabel)
                            <span class="badge bg-light text-success border">{{ $selTierLabel }}</span>
                        @endif
                    </div>
                    <div class="tw-kpi-step__title">% KPI đạt</div>
                    <div class="tw-kpi-step__val {{ $selKpiPct === null ? 'tw-kpi-step__val--muted' : '' }}">
                        {{ $selKpiPct !== null ? $pct($selKpiPct) : 'Chưa đủ dữ liệu' }}
                    </div>
                    <div class="tw-kpi-step__desc">
                        Tổng hợp các tiêu chí kỹ thuật
                    </div>
                </div>

                <div class="tw-kpi-step">
                    <div class="tw-kpi-step__header">
                        <span class="tw-kpi-step__num">Bước 5</span>
                    </div>
                    <div class="tw-kpi-step__title">Tiền KPI</div>
                    <div class="tw-kpi-step__val {{ $selKpiMoney === null ? 'tw-kpi-step__val--muted' : '' }}">
                        {{ $selKpiMoney !== null ? number_format($selKpiMoney, 0, ',', '.') . ' đ' : 'Chưa thể tính' }}
                    </div>
                    <div class="tw-kpi-step__desc">
                        = Quỹ KPI × Tỷ lệ quy đổi hiệu suất
                    </div>
                </div>

                <div class="tw-kpi-step tw-kpi-step--total">
                    <div class="tw-kpi-step__header">
                        <span class="tw-kpi-step__num text-primary">Bước 6</span>
                    </div>
                    <div class="tw-kpi-step__title text-primary">Tổng dự kiến</div>
                    <div class="tw-kpi-step__val {{ $selTotIncome === null ? 'tw-kpi-step__val--muted' : '' }}">
                        {{ $selTotIncome !== null ? number_format($selTotIncome, 0, ',', '.') . ' đ' : 'Chưa thể tính' }}
                    </div>
                    <div class="tw-kpi-step__desc">
                        = Lương cố định + Tiền KPI
                    </div>
                </div>
            </div>

            {{-- Bảng giải thích tiến độ & đóng góp các tiêu chí KPI --}}
            <h3 class="tw-card__title mb-2 mt-4">Tiến độ và điểm đóng góp các tiêu chí KPI</h3>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0 tp-table">
                    <thead>
                        <tr>
                            <th>Tiêu chí</th>
                            <th style="width: 100px;">Trọng số</th>
                            <th>Kết quả thực tế</th>
                            <th style="width: 110px;" class="text-center">Tỷ lệ đạt</th>
                            <th style="width: 120px;" class="text-center">Điểm đóng góp</th>
                            <th style="width: 120px;" class="text-center">Trạng thái dữ liệu</th>
                            <th style="width: 120px;" class="text-end">Thao tác</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($selCriteria as $c)
                        @php
                            $cCode = $c['code'] ?? '';
                            $isWarranty = ($cCode === 'warranty');
                            $cWeight = $c['weight'] ?? null;
                            $cRate = $c['rate'] ?? null;
                            $cScore = $c['score'] ?? null;
                            $cStatus = $c['status'] ?? 'pending';
                            
                            $actualResultText = 'Chưa ghi nhận dữ liệu';
                            if ($cCode === 'timeline' && isset($c['detail'])) {
                                $actualResultText = ($c['detail']['on_time'] ?? 0) . '/' . ($c['detail']['total'] ?? 0) . ' hệ thống đúng hạn';
                            } elseif ($cCode === 'quality' && isset($c['detail'])) {
                                $actualResultText = ($c['detail']['passed'] ?? 0) . '/' . ($c['detail']['total'] ?? 0) . ' nghiệm thu lần đầu';
                            } elseif ($cCode === 'survey' && isset($c['detail'])) {
                                $actualResultText = 'Sai số: ' . ($c['detail']['error_rate'] ?? '0') . '% (Ngưỡng < 3%)';
                            } elseif ($cCode === 'hse' && isset($c['detail'])) {
                                $actualResultText = ($c['detail']['violations'] ?? 0) . ' sự cố an toàn';
                            } elseif ($cCode === 'evn_app' && isset($c['detail'])) {
                                $actualResultText = ($c['detail']['completed'] ?? 0) . '/' . ($c['detail']['total'] ?? 0) . ' cài đặt App';
                            } elseif ($isWarranty) {
                                $actualResultText = 'Chờ lãnh đạo xác nhận';
                            }
                        @endphp
                        <tr>
                            <td>
                                <span class="fw-semibold d-block">{{ $c['no'] ?? '—' }}. {{ $c['name'] }}</span>
                                <span class="text-muted small">Mã: {{ $cCode }}</span>
                            </td>
                            <td>
                                @if($cWeight !== null && !$isWarranty)
                                    <span>{{ number_format($cWeight * 100, 0) }}%</span>
                                @else
                                    <span class="text-muted">—</span>
                                @endif
                            </td>
                            <td>
                                <span>{{ $actualResultText }}</span>
                            </td>
                            <td class="text-center">
                                @if($cRate !== null && !$isWarranty)
                                    <span class="fw-semibold text-primary">{{ number_format($cRate * 100, 1) }}%</span>
                                @elseif($isWarranty)
                                    <span class="text-muted">—</span>
                                @else
                                    <span class="text-muted small">Chưa đủ</span>
                                @endif
                            </td>
                            <td class="text-center">
                                @if($cScore !== null && !$isWarranty)
                                    <span class="fw-semibold text-success">{{ number_format($cScore * 100, 1) }}%</span>
                                @elseif($isWarranty)
                                    <span class="text-muted">—</span>
                                @else
                                    <span class="text-muted">—</span>
                                @endif
                            </td>
                            <td class="text-center">
                                @if($isWarranty)
                                    <span class="tp-state">Chờ xác nhận</span>
                                @elseif($cStatus === 'pass')
                                    <span class="tp-state tp-state--success">Đạt</span>
                                @elseif($cStatus === 'fail')
                                    <span class="tp-state tp-state--danger">Chưa đạt</span>
                                @else
                                    <span class="tp-state tp-state--warning">Thiếu dữ liệu</span>
                                @endif
                            </td>
                            <td class="text-end">
                                <button type="button" class="btn btn-sm btn-outline-secondary" onclick="openCriteriaModal('{{ $cCode }}')">
                                    Xem cách tính
                                </button>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    @endif

</div>
<!-- TECHNICAL_KPI_CONTENT_END -->

{{-- Modal Xem cách tính & Minh chứng (Dùng Modal Bootstrap 5 chuẩn CRM) --}}
<div class="modal fade" id="criteriaModal" tabindex="-1" aria-labelledby="criteriaModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fs-6 fw-bold" id="criteriaModalLabel">Chi tiết cách tính tiêu chí</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Đóng"></button>
            </div>
            <div class="modal-body">
                <div id="criteriaModalBody">
                    {{-- Nội dung tiêu chí nạp động qua JavaScript --}}
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Đóng</button>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    function togglePeriodInputs(type) {
        const isMonth = type === 'month';
        const monthBox = document.getElementById('filter-month-box');
        const quarterBox = document.getElementById('filter-quarter-box');
        if (monthBox) monthBox.style.display = isMonth ? '' : 'none';
        if (quarterBox) quarterBox.style.display = isMonth ? 'none' : '';
    }

    // Dữ liệu tiêu chí nạp vào modal
    const criteriaDataMap = {
        @if($hasSelected)
        @foreach($selCriteria as $c)
        '{{ $c['code'] }}': {
            no: '{{ $c['no'] ?? '' }}',
            name: {!! json_encode($c['name'] ?? '') !!},
            weight: '{{ $c['weight'] !== null ? number_format($c['weight'] * 100, 0) . '%' : '—' }}',
            measure: {!! json_encode($c['measure'] ?? $c['formula'] ?? '') !!},
            target: {!! json_encode($c['target'] ?? $c['threshold'] ?? '') !!},
            source: {!! json_encode($c['source'] ?? 'Hệ thống CRM EGO Solar') !!},
            missing: {!! json_encode($c['missing'] ?? []) !!},
            isWarranty: {{ ($c['code'] ?? '') === 'warranty' ? 'true' : 'false' }}
        },
        @endforeach
        @endif
    };

    function openCriteriaModal(code) {
        const item = criteriaDataMap[code];
        if (!item) return;

        document.getElementById('criteriaModalLabel').textContent = item.no + '. ' + item.name + ' (Trọng số: ' + item.weight + ')';

        let html = '';

        if (item.isWarranty) {
            html += '<div class="alert alert-secondary py-2 small mb-3">';
            html += '<strong>Trạng thái: Nháp — Chờ lãnh đạo xác nhận</strong><br>';
            html += 'Tiêu chí theo dõi thời gian tiếp nhận và xử lý bảo hành. Công thức và quy trình xác nhận chính thức đang chờ Ban lãnh đạo phê duyệt. Tiêu chí không tính vào tổng điểm KPI và không tính vào lương hiệu suất.';
            html += '</div>';
        }

        html += '<dl class="row small mb-2">';
        html += '<dt class="col-sm-4 text-muted">Mục tiêu đạt:</dt><dd class="col-sm-8 fw-semibold">' + (item.target || 'Đang cập nhật') + '</dd>';
        html += '</dl>';

        // Accordion đóng mặc định cho Công thức và Nguồn CRM
        html += '<div class="accordion tw-kpi-accordion mb-3" id="kpiCritAccordion">';
        html += '  <div class="accordion-item">';
        html += '    <h2 class="accordion-header" id="headingOne">';
        html += '      <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseOne" aria-expanded="false" aria-controls="collapseOne">';
        html += '        Công thức tính & Nguồn dữ liệu CRM';
        html += '      </button>';
        html += '    </h2>';
        html += '    <div id="collapseOne" class="accordion-collapse collapse" aria-labelledby="headingOne" data-bs-parent="#kpiCritAccordion">';
        html += '      <div class="accordion-body">';
        html += '        <div class="mb-2"><strong>Công thức:</strong> ' + (item.measure || 'Đang cập nhật') + '</div>';
        html += '        <div><strong>Nguồn dữ liệu:</strong> ' + (item.source || 'CRM EGO Solar') + '</div>';
        html += '      </div>';
        html += '    </div>';
        html += '  </div>';
        html += '</div>';

        if (item.missing && item.missing.length > 0) {
            html += '<div class="alert alert-warning py-2 small mb-0">';
            html += '<strong>Lý do chưa đủ dữ liệu:</strong><ul class="mb-0 ps-3">';
            item.missing.forEach(m => {
                html += '<li>' + m + '</li>';
            });
            html += '</ul></div>';
        } else if (!item.isWarranty) {
            html += '<div class="alert alert-success py-2 small mb-0">';
            html += '<i class="bi bi-check-circle me-1"></i> Tiêu chí đã đối soát đủ dữ liệu hợp lệ trong kỳ đánh giá.';
            html += '</div>';
        }

        document.getElementById('criteriaModalBody').innerHTML = html;

        const modalEl = document.getElementById('criteriaModal');
        if (window.bootstrap && window.bootstrap.Modal) {
            const modal = window.bootstrap.Modal.getOrCreateInstance(modalEl);
            modal.show();
        } else {
            $(modalEl).modal('show');
        }
    }
</script>
@endpush
