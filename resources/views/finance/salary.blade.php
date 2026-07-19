@extends('layouts.app')

@section('title', 'Bảng lương nhân viên')

@section('content')
<style>
    .payroll-page {
        --bg: #f5f7fb;
        --card: #ffffff;
        --ink: #0f172a;
        --muted: #64748b;
        --line: #e2e8f0;
        --blue: #2563eb;
        --green: #16a34a;
        --red: #dc2626;
        --amber: #f59e0b;
        background: var(--bg);
        min-height: 100vh;
        padding: 18px;
        font-size: 13px;
        color: var(--ink);
    }

    .payroll-hero {
        border-radius: 24px;
        padding: 24px;
        color: #fff;
        background:
            radial-gradient(800px 320px at 85% 0%, rgba(34,211,238,.28), transparent 60%),
            linear-gradient(135deg, #020617 0%, #0f3c85 55%, #0f766e 100%);
        box-shadow: 0 18px 45px rgba(15, 23, 42, .16);
        margin-bottom: 16px;
    }

    .payroll-hero h2 {
        margin: 0;
        font-size: 28px;
        font-weight: 950;
        letter-spacing: -.03em;
    }

    .payroll-hero p {
        margin: 6px 0 0;
        opacity: .86;
        font-size: 13px;
    }

    .payroll-pill {
        display: inline-flex;
        align-items: center;
        gap: 7px;
        border-radius: 999px;
        padding: 8px 12px;
        background: rgba(255, 255, 255, .14);
        color: #fff;
        font-weight: 850;
        font-size: 12px;
    }

    .payroll-card {
        background: var(--card);
        border: 1px solid rgba(148, 163, 184, .18);
        border-radius: 22px;
        box-shadow: 0 10px 30px rgba(15, 23, 42, .055);
    }

    .filter-card {
        padding: 16px;
        margin-bottom: 16px;
    }

    .filter-label {
        display: block;
        margin-bottom: 6px;
        color: var(--muted);
        font-weight: 850;
        font-size: 12px;
    }

    .payroll-page .form-control,
    .payroll-page .form-select {
        border-radius: 14px;
        border-color: #dbe4f0;
        font-size: 13px;
        min-height: 42px;
    }

    .payroll-btn {
        border: 0;
        border-radius: 14px;
        min-height: 42px;
        padding: 0 16px;
        font-weight: 900;
        font-size: 13px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 7px;
        text-decoration: none;
        white-space: nowrap;
    }

    .payroll-btn-primary {
        background: var(--blue);
        color: #fff;
    }

    .payroll-btn-primary:hover {
        background: #1d4ed8;
        color: #fff;
    }


    .payroll-btn-success {
        background: #16a34a;
        color: #fff;
    }

    .payroll-btn-success:hover {
        background: #15803d;
        color: #fff;
    }

    .payroll-btn-dark {
        background: #0f172a;
        color: #fff;
    }

    .payroll-btn-dark:hover {
        background: #1e293b;
        color: #fff;
    }

    .payroll-btn-light {
        background: #f1f5f9;
        color: #0f172a;
        border: 1px solid #e2e8f0;
    }

    .payroll-btn-light:hover {
        background: #e2e8f0;
        color: #0f172a;
    }

    .summary-grid {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: 14px;
        margin-bottom: 16px;
    }

    .summary-card {
        padding: 16px;
    }

    .summary-card .label {
        color: var(--muted);
        font-weight: 850;
        font-size: 12px;
        margin-bottom: 8px;
    }

    .summary-card .value {
        font-size: 26px;
        line-height: 1;
        font-weight: 950;
        letter-spacing: -.03em;
    }

    .summary-card .hint {
        margin-top: 8px;
        color: var(--muted);
        font-size: 12px;
    }

    .employee-list-card {
        overflow: hidden;
    }

    .list-head {
        padding: 16px 18px;
        border-bottom: 1px solid var(--line);
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 12px;
        flex-wrap: wrap;
    }

    .list-head h4 {
        margin: 0;
        font-size: 18px;
        font-weight: 950;
    }

    .list-head p {
        margin: 4px 0 0;
        color: var(--muted);
        font-size: 12px;
    }

    .payroll-row {
        display: grid;
        grid-template-columns: 300px 135px 155px 145px 160px 155px 145px 120px;
        gap: 12px;
        align-items: center;
        padding: 14px 18px;
        border-bottom: 1px solid var(--line);
        background: #fff;
    }

    .payroll-row:nth-child(even) {
        background: #fcfdff;
    }

    .payroll-row:hover {
        background: #f8fbff;
    }

    .payroll-row.header {
        background: #f8fafc;
        color: #475569;
        font-size: 11px;
        font-weight: 950;
        text-transform: uppercase;
        letter-spacing: .03em;
        position: sticky;
        top: 0;
        z-index: 3;
    }

    .employee-box {
        display: flex;
        align-items: center;
        gap: 10px;
        min-width: 0;
    }

    .avatar {
        width: 42px;
        height: 42px;
        border-radius: 16px;
        background: linear-gradient(135deg, #2563eb, #0ea5e9);
        color: #fff;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 950;
        flex: 0 0 auto;
        box-shadow: 0 8px 18px rgba(37, 99, 235, .18);
    }

    .employee-name {
        font-weight: 950;
        color: #0f172a;
        line-height: 1.2;
    }

    .employee-meta {
        color: #64748b;
        font-size: 12px;
        margin-top: 3px;
        line-height: 1.35;
    }

    .metric {
        border-radius: 16px;
        padding: 10px 12px;
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        min-height: 58px;
    }

    .metric .label {
        color: #64748b;
        font-size: 11px;
        font-weight: 800;
        margin-bottom: 4px;
    }

    .metric .value {
        font-size: 15px;
        font-weight: 950;
        color: #0f172a;
        line-height: 1.2;
    }

    .metric.net {
        background: #f0fdf4;
        border-color: #bbf7d0;
    }

    .metric.net .value {
        color: #15803d;
        font-size: 16px;
    }

    .metric.deduct {
        background: #fff7ed;
        border-color: #fed7aa;
    }

    .metric.deduct .value {
        color: #c2410c;
    }

    .late-line,
    .salary-line {
        margin-top: 5px;
        font-size: 11px;
        line-height: 1.35;
        font-weight: 850;
    }

    .late-line {
        color: #c2410c;
    }

    .late-line.empty {
        color: #94a3b8;
        font-weight: 750;
    }

    .salary-line {
        color: #475569;
    }

    .status-pill {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        border-radius: 999px;
        padding: 7px 10px;
        font-size: 12px;
        font-weight: 900;
        background: #eff6ff;
        color: #1d4ed8;
        white-space: nowrap;
    }

    .status-pill.saved {
        background: #dcfce7;
        color: #15803d;
    }

    .row-actions {
        display: flex;
        flex-direction: column;
        gap: 7px;
    }

    .row-actions .payroll-btn {
        min-height: 34px;
        border-radius: 999px;
        font-size: 12px;
        width: 100%;
    }

    .sticky-save-bar {
        position: sticky;
        bottom: 12px;
        z-index: 20;
        margin-top: 14px;
        padding: 13px 15px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 12px;
        flex-wrap: wrap;
    }

    .modal-content {
        border: 0;
        border-radius: 24px;
        overflow: hidden;
        box-shadow: 0 24px 70px rgba(15, 23, 42, .26);
    }

    .modal-header-pro {
        background: linear-gradient(135deg, #020617, #0f3c85 55%, #0f766e);
        color: #fff;
        padding: 18px 20px;
    }

    .modal-header-pro h5 {
        margin: 0;
        font-size: 18px;
        font-weight: 950;
    }

    .modal-header-pro .sub {
        margin-top: 4px;
        font-size: 12px;
        opacity: .8;
    }

    .modal-section {
        border: 1px solid #e2e8f0;
        border-radius: 18px;
        padding: 14px;
        background: #fff;
        height: 100%;
    }

    .modal-section-title {
        font-weight: 950;
        margin-bottom: 12px;
        font-size: 14px;
        color: #0f172a;
        display: flex;
        align-items: center;
        gap: 7px;
    }

    .modal-section-title.income {
        color: #15803d;
    }

    .modal-section-title.deduct {
        color: #c2410c;
    }

    .modal-summary {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 10px;
        margin-top: 12px;
    }

    .modal-summary .metric {
        min-height: 70px;
    }

    .empty-state {
        padding: 46px 20px;
        text-align: center;
        color: #64748b;
    }

    @media (max-width: 1500px) {
        .payroll-row {
            grid-template-columns: 280px 125px 145px 135px 150px 145px 135px 110px;
            gap: 10px;
        }
    }

    @media (max-width: 1300px) {
        .payroll-list-scroll {
            overflow-x: auto;
        }

        .payroll-row {
            min-width: 1320px;
        }
    }

    @media (max-width: 900px) {
        .payroll-page {
            padding: 12px;
        }

        .payroll-hero {
            padding: 18px;
            border-radius: 20px;
        }

        .payroll-hero h2 {
            font-size: 23px;
        }

        .summary-grid {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }

        .modal-summary {
            grid-template-columns: 1fr;
        }
    }

    @media (max-width: 560px) {
        .summary-grid {
            grid-template-columns: 1fr;
        }

        .sticky-save-bar {
            align-items: stretch;
        }

        .sticky-save-bar .d-flex {
            width: 100%;
        }

        .sticky-save-bar .payroll-btn {
            width: 100%;
        }
    }
</style>

@php
    $departments = $departments ?? collect();
    $employees = $employees ?? collect();

    $currentMonthLabel = $month ?? now()->format('Y-m');

    try {
        $salaryMonthStart = \Carbon\Carbon::createFromFormat('Y-m', $currentMonthLabel)->startOfMonth();
    } catch (\Throwable $e) {
        $salaryMonthStart = now()->startOfMonth();
        $currentMonthLabel = $salaryMonthStart->format('Y-m');
    }

    $salaryMonthEnd = (clone $salaryMonthStart)->endOfMonth();

    $globalLatePenaltyPerTime = 0;

    try {
        if (class_exists(\App\Models\AttendanceSetting::class)) {
            $globalAttendanceSetting = \App\Models\AttendanceSetting::first();
            $globalLatePenaltyPerTime = (float) ($globalAttendanceSetting->late_penalty_per_time ?? 0);
        }
    } catch (\Throwable $e) {
        $globalLatePenaltyPerTime = 0;
    }
@endphp

<div class="payroll-page">
    <div class="payroll-hero">
        <div class="d-flex justify-content-between align-items-start flex-wrap gap-3">
            <div>
                <div class="payroll-pill mb-2">
                    <i class="bi bi-stars"></i>
                    Finance Payroll
                </div>
                <h2>Bảng lương nhân viên</h2>
                <p>
                    Bảng chính gọn, nhập chi tiết bằng popup. Lương tháng tự chia theo ngày công thực tế.
                </p>
            </div>

            <div class="d-flex flex-wrap gap-2">
                <a href="{{ route('finance.salary.my', ['month' => $currentMonthLabel]) }}" class="payroll-btn payroll-btn-light">
                    <i class="bi bi-person-badge"></i>
                    Lương của tôi
                </a>


                <a href="{{ route('finance.salary.export.excel', request()->query()) }}" class="payroll-btn payroll-btn-success">
                    <i class="bi bi-file-earmark-excel"></i>
                    Xuất Excel
                </a>

                <button type="button" class="payroll-btn payroll-btn-dark" onclick="window.print()">
                    <i class="bi bi-printer"></i>
                    In
                </button>
            </div>
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success border-0 shadow-sm rounded-4">
            {{ session('success') }}
        </div>
    @endif

    @if(session('error'))
        <div class="alert alert-danger border-0 shadow-sm rounded-4">
            {{ session('error') }}
        </div>
    @endif

    <div class="payroll-card filter-card">
        <form method="GET" action="{{ route('finance.salary') }}">
            <div class="row g-3 align-items-end">
                <div class="col-xl-3 col-md-6">
                    <label class="filter-label">Kỳ lương</label>
                    <input type="month" name="month" value="{{ $currentMonthLabel }}" class="form-control">
                </div>

                <div class="col-xl-3 col-md-6">
                    <label class="filter-label">Phòng ban</label>
                    <select name="department_id" class="form-select">
                        <option value="">Tất cả phòng ban</option>
                        @foreach($departments as $department)
                            <option value="{{ $department->id }}" {{ request('department_id') == $department->id ? 'selected' : '' }}>
                                {{ $department->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="col-xl-4 col-md-8">
                    <label class="filter-label">Tìm nhân viên</label>
                    <input type="text" name="keyword" value="{{ request('keyword') }}" class="form-control" placeholder="Nhập tên nhân viên...">
                </div>

                <div class="col-xl-2 col-md-4 d-grid">
                    <button type="submit" class="payroll-btn payroll-btn-primary">
                        <i class="bi bi-funnel"></i>
                        Lọc dữ liệu
                    </button>
                </div>
            </div>
        </form>
    </div>

    <form method="POST" action="{{ route('finance.salary.save') }}" id="salaryForm">
        @csrf
        <input type="hidden" name="month" value="{{ $currentMonthLabel }}">

        <div class="summary-grid">
            <div class="payroll-card summary-card">
                <div class="label">Kỳ lương</div>
                <div class="value">{{ $currentMonthLabel }}</div>
                <div class="hint">Theo bộ lọc hiện tại</div>
            </div>

            <div class="payroll-card summary-card">
                <div class="label">Tổng nhân viên</div>
                <div class="value">{{ $employees->count() }}</div>
                <div class="hint">Số dòng đang hiển thị</div>
            </div>

            <div class="payroll-card summary-card">
                <div class="label">Tổng khấu trừ</div>
                <div class="value" id="sumDeduction">0đ</div>
                <div class="hint">BH + thuế + tạm ứng + đi trễ + khác</div>
            </div>

            <div class="payroll-card summary-card">
                <div class="label">Tổng thực lĩnh</div>
                <div class="value text-success" id="sumNet">0đ</div>
                <div class="hint">Tự cập nhật khi nhập chi tiết</div>
            </div>
        </div>

        <div class="payroll-card employee-list-card">
            <div class="list-head">
                <div>
                    <h4>Danh sách lương</h4>
                    <p>Lương tháng được tính theo công thức: lương tháng / ngày công chuẩn × ngày công thực tế.</p>
                </div>

                <div class="d-flex gap-2 flex-wrap">
                    <span class="status-pill">
                        <i class="bi bi-lightning-charge"></i>
                        Tự tính thực lĩnh
                    </span>
                    <span class="status-pill saved">
                        <i class="bi bi-clock-history"></i>
                        Có phạt đi trễ
                    </span>
                </div>
            </div>

            <div class="payroll-list-scroll">
                <div class="payroll-row header">
                    <div>Nhân viên</div>
                    <div>Ngày công</div>
                    <div>Lương tháng</div>
                    <div>Tổng phụ cấp</div>
                    <div>Tổng khấu trừ</div>
                    <div>Thực lĩnh</div>
                    <div>Trạng thái</div>
                    <div>Thao tác</div>
                </div>

                @forelse($employees as $index => $employee)
                    @php
                        $salaryNoteRaw = old("rows.$index.note", $employee->salary_note ?? '');
                        $salaryMeta = is_string($salaryNoteRaw) ? json_decode($salaryNoteRaw, true) : null;
                        $salaryMeta = is_array($salaryMeta) ? $salaryMeta : [];

                        $income = $salaryMeta['income_breakdown'] ?? [];
                        $deduction = $salaryMeta['deduction_breakdown'] ?? [];
                        $noteText = $salaryMeta['note_text'] ?? (is_string($salaryNoteRaw) ? $salaryNoteRaw : '');

                        $standardDays = (float) old("rows.$index.standard_days", $employee->standard_days ?? 26);
                        $workingDays = (float) old("rows.$index.working_days", $employee->working_days ?? ($attendanceMap[$employee->id] ?? 0));

                        $basicSalary = (float) old("rows.$index.basic_salary", $employee->basic_salary ?? 0);

                        $businessTrip = (float) ($income['business_trip'] ?? 0);
                        $meal = (float) ($income['meal'] ?? 0);
                        $phone = (float) ($income['phone'] ?? 0);
                        $housing = (float) ($income['housing'] ?? 0);
                        $fuel = (float) ($income['fuel'] ?? 0);
                        $child = (float) ($income['child'] ?? 0);
                        $province = (float) ($income['province'] ?? 0);

                        $commission = (float) old("rows.$index.commission", $employee->commission ?? 0);
                        $bonus = (float) old("rows.$index.bonus", $employee->bonus ?? 0);

                        $bhxh = (float) ($deduction['bhxh'] ?? 0);
                        $bhyt = (float) ($deduction['bhyt'] ?? 0);
                        $bhtn = (float) ($deduction['bhtn'] ?? 0);
                        $pit = (float) ($deduction['pit'] ?? 0);
                        $otherOnly = (float) ($deduction['other'] ?? 0);

                        $advance = (float) old("rows.$index.advance", $employee->advance ?? 0);

                        $latePenaltyPerTime = (float) ($employee->late_penalty_per_time ?? $globalLatePenaltyPerTime ?? 0);
                        $lateCount = (int) ($employee->late_count ?? 0);

                        if ($lateCount <= 0) {
                            try {
                                if (class_exists(\App\Models\AttendanceRecord::class)) {
                                    $lateCount = \App\Models\AttendanceRecord::query()
                                        ->where('user_id', $employee->id)
                                        ->whereBetween('work_date', [$salaryMonthStart->toDateString(), $salaryMonthEnd->toDateString()])
                                        ->where('late_minutes', '>', 0)
                                        ->count();
                                }
                            } catch (\Throwable $e) {
                                $lateCount = 0;
                            }
                        }

                        $latePenalty = (float) ($employee->late_penalty_total ?? 0);

                        if ($latePenalty <= 0 && $lateCount > 0 && $latePenaltyPerTime > 0) {
                            $latePenalty = $lateCount * $latePenaltyPerTime;
                        }

                        $isTechAuto = !empty($employee->is_technical_salary_auto);
                        $techTotalSalary = (float) ($employee->technical_salary_total ?? 0);

                        if ($isTechAuto && $techTotalSalary > 0) {
                            $basicSalary = $techTotalSalary;
                        }

                        $allowanceTotal = $businessTrip + $meal + $phone + $housing + $fuel + $child + $province;
                        $salaryByDays = $standardDays > 0 ? (($basicSalary / $standardDays) * $workingDays) : 0;
                        $grossTotal = $salaryByDays + $allowanceTotal + $commission + $bonus;

                        $otherDeductionHidden = $bhxh + $bhyt + $bhtn + $pit + $otherOnly + $latePenalty;
                        $totalDeduction = $advance + $otherDeductionHidden;
                        $netSalary = $grossTotal - $totalDeduction;

                        $saved = !empty($employee->payroll_saved)
                            || (($employee->net_salary ?? 0) > 0)
                            || (($employee->basic_salary ?? 0) > 0)
                            || ($latePenalty > 0);
                    @endphp

                    <div class="payroll-row salary-row" data-row="{{ $index }}">
                        <div class="employee-box">
                            <div class="avatar">
                                {{ \Illuminate\Support\Str::upper(\Illuminate\Support\Str::substr($employee->name ?? 'N', 0, 1)) }}
                            </div>

                            <div class="min-w-0">
                                <div class="employee-name">{{ $employee->name ?? '-' }}</div>
                                <div class="employee-meta">
                                    ID: {{ $employee->id }} · {{ optional($employee->department)->name ?? 'Chưa có phòng ban' }}<br>
                                    {{ optional($employee->position)->name ?? 'Chưa có chức vụ' }}
                                </div>
                            </div>
                        </div>

                        <div class="metric">
                            <div class="label">Thực tế / chuẩn</div>
                            <div class="value js-view-days">{{ $workingDays }} / {{ $standardDays }}</div>
                        </div>

                        <div class="metric">
                            <div class="label">Lương tháng</div>
                            <div class="value js-view-basic">{{ number_format($basicSalary, 0, ',', '.') }}đ</div>
                            <div class="salary-line js-view-salary-by-days">
                                Theo công: {{ number_format($salaryByDays, 0, ',', '.') }}đ
                            </div>
                        </div>

                        <div class="metric">
                            <div class="label">Phụ cấp + thưởng</div>
                            <div class="value js-view-income">{{ number_format($allowanceTotal + $commission + $bonus, 0, ',', '.') }}đ</div>
                        </div>

                        <div class="metric deduct">
                            <div class="label">Khấu trừ</div>
                            <div class="value js-view-deduction">{{ number_format($totalDeduction, 0, ',', '.') }}đ</div>

                            <div class="late-line {{ $latePenalty > 0 ? '' : 'empty' }} js-view-late-note">
                                @if($latePenalty > 0)
                                    Đi trễ: {{ $lateCount }} lần × {{ number_format($latePenaltyPerTime, 0, ',', '.') }}đ
                                @else
                                    Không có phạt đi trễ
                                @endif
                            </div>
                        </div>

                        <div class="metric net">
                            <div class="label">Thực lĩnh</div>
                            <div class="value js-view-net">{{ number_format($netSalary, 0, ',', '.') }}đ</div>
                        </div>

                        <div>
                            <span class="status-pill {{ $saved ? 'saved' : '' }} js-row-status">
                                <i class="bi {{ $saved ? 'bi-check2-circle' : 'bi-pencil-square' }}"></i>
                                {{ $saved ? 'Đã có dữ liệu' : 'Chưa nhập' }}
                            </span>
                        </div>

                        <div class="row-actions">
                            <button type="button" class="payroll-btn payroll-btn-primary js-edit-row" data-row="{{ $index }}">
                                Nhập chi tiết
                            </button>

                            <a href="{{ route('finance.salary.detail', ['user' => $employee->id, 'month' => $currentMonthLabel]) }}"
                               class="payroll-btn payroll-btn-light">
                                Phiếu lương
                            </a>
                        </div>

                        <input type="hidden" name="rows[{{ $index }}][user_id]" value="{{ $employee->id }}">

                        <input type="hidden" name="rows[{{ $index }}][standard_days]" class="js-standard-days" value="{{ $standardDays }}">
                        <input type="hidden" name="rows[{{ $index }}][working_days]" class="js-working-days" value="{{ $workingDays }}">
                        <input type="hidden" name="rows[{{ $index }}][basic_salary]" class="js-basic-salary" value="{{ $basicSalary }}">

                        <input type="hidden" name="rows[{{ $index }}][allowance]" class="js-allowance" value="{{ $allowanceTotal }}">
                        <input type="hidden" name="rows[{{ $index }}][commission]" class="js-commission" value="{{ $commission }}">
                        <input type="hidden" name="rows[{{ $index }}][bonus]" class="js-bonus" value="{{ $bonus }}">
                        <input type="hidden" name="rows[{{ $index }}][advance]" class="js-advance" value="{{ $advance }}">
                        <input type="hidden" name="rows[{{ $index }}][other_deduction]" class="js-other-deduction" value="{{ $otherDeductionHidden }}">
                        <input type="hidden" name="rows[{{ $index }}][net_salary]" class="js-net-salary" value="{{ round($netSalary) }}">
                        <input type="hidden" name="rows[{{ $index }}][note]" class="js-note" value="{{ e($salaryNoteRaw) }}">

                        <input type="hidden" class="js-business-trip" value="{{ $businessTrip }}">
                        <input type="hidden" class="js-meal" value="{{ $meal }}">
                        <input type="hidden" class="js-phone" value="{{ $phone }}">
                        <input type="hidden" class="js-housing" value="{{ $housing }}">
                        <input type="hidden" class="js-fuel" value="{{ $fuel }}">
                        <input type="hidden" class="js-child" value="{{ $child }}">
                        <input type="hidden" class="js-province" value="{{ $province }}">

                        <input type="hidden" class="js-bhxh" value="{{ $bhxh }}">
                        <input type="hidden" class="js-bhyt" value="{{ $bhyt }}">
                        <input type="hidden" class="js-bhtn" value="{{ $bhtn }}">
                        <input type="hidden" class="js-pit" value="{{ $pit }}">
                        <input type="hidden" class="js-other-only" value="{{ $otherOnly }}">
                        <input type="hidden" class="js-note-text" value="{{ e($noteText) }}">

                        <input type="hidden" class="js-late-penalty" value="{{ $latePenalty }}">
                        <input type="hidden" class="js-late-count" value="{{ $lateCount }}">
                        <input type="hidden" class="js-late-penalty-per-time" value="{{ $latePenaltyPerTime }}">
                        <input type="hidden" class="js-is-tech-auto" value="{{ $isTechAuto ? 1 : 0 }}">
                        <input type="hidden" class="js-tech-total-salary" value="{{ $techTotalSalary }}">
                    </div>
                @empty
                    <div class="empty-state">
                        <i class="bi bi-inbox fs-1 d-block mb-2"></i>
                        Chưa có nhân viên nào theo bộ lọc hiện tại.
                    </div>
                @endforelse
            </div>
        </div>

        @if($employees->count())
            <div class="payroll-card sticky-save-bar">
                <div>
                    <div class="fw-bold">Sẵn sàng lưu bảng lương</div>
                    <div class="text-muted small">
                        Lương đã được tính theo ngày công thực tế. Tiền đi trễ đã được cộng vào khấu trừ.
                    </div>
                </div>

                <div class="d-flex gap-2 flex-wrap">
                    <a href="{{ route('finance.salary', ['month' => $currentMonthLabel]) }}" class="payroll-btn payroll-btn-light">
                        <i class="bi bi-arrow-clockwise"></i>
                        Tải lại
                    </a>

                    <button type="submit" class="payroll-btn payroll-btn-primary">
                        <i class="bi bi-floppy"></i>
                        Lưu bảng lương
                    </button>
                </div>
            </div>
        @endif
    </form>
</div>

<div class="modal fade" id="salaryEditModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header-pro">
                <div class="d-flex justify-content-between align-items-start gap-3">
                    <div>
                        <h5 id="modalEmployeeName">Nhập chi tiết lương</h5>
                        <div class="sub" id="modalEmployeeSub">Cập nhật ngày công, thu nhập, khấu trừ và ghi chú.</div>
                    </div>

                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Đóng"></button>
                </div>
            </div>

            <div class="modal-body p-3 p-md-4">
                <input type="hidden" id="modalRowIndex">

                <div class="row g-3">
                    <div class="col-lg-4">
                        <div class="modal-section">
                            <div class="modal-section-title">
                                <i class="bi bi-calendar-check"></i>
                                Ngày công & lương cơ bản
                            </div>

                            <div class="row g-3">
                                <div class="col-6">
                                    <label class="filter-label">Ngày công chuẩn</label>
                                    <input type="number" min="0" step="0.5" id="m_standard_days" class="form-control salary-modal-calc">
                                </div>

                                <div class="col-6">
                                    <label class="filter-label">Ngày công thực tế</label>
                                    <input type="number" min="0" step="0.5" id="m_working_days" class="form-control salary-modal-calc">
                                </div>

                                <div class="col-12">
                                    <label class="filter-label">Lương tháng</label>
                                    <input type="number" min="0" step="any" id="m_basic_salary" class="form-control salary-modal-calc">
                                </div>
                            </div>

                            <div class="modal-summary">
                                <div class="metric">
                                    <div class="label">Lương theo công</div>
                                    <div class="value" id="m_salary_by_days">0đ</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-4">
                        <div class="modal-section">
                            <div class="modal-section-title income">
                                <i class="bi bi-plus-circle"></i>
                                Thu nhập / phụ cấp
                            </div>

                            <div class="row g-3">
                                <div class="col-6">
                                    <label class="filter-label">Công tác phí</label>
                                    <input type="number" min="0" step="any" id="m_business_trip" class="form-control salary-modal-calc">
                                </div>

                                <div class="col-6">
                                    <label class="filter-label">Tiền ăn</label>
                                    <input type="number" min="0" step="any" id="m_meal" class="form-control salary-modal-calc">
                                </div>

                                <div class="col-6">
                                    <label class="filter-label">Điện thoại</label>
                                    <input type="number" min="0" step="any" id="m_phone" class="form-control salary-modal-calc">
                                </div>

                                <div class="col-6">
                                    <label class="filter-label">Nhà ở</label>
                                    <input type="number" min="0" step="any" id="m_housing" class="form-control salary-modal-calc">
                                </div>

                                <div class="col-6">
                                    <label class="filter-label">Xăng xe</label>
                                    <input type="number" min="0" step="any" id="m_fuel" class="form-control salary-modal-calc">
                                </div>

                                <div class="col-6">
                                    <label class="filter-label">Nuôi con nhỏ</label>
                                    <input type="number" min="0" step="any" id="m_child" class="form-control salary-modal-calc">
                                </div>

                                <div class="col-6">
                                    <label class="filter-label">Công tác tỉnh</label>
                                    <input type="number" min="0" step="any" id="m_province" class="form-control salary-modal-calc">
                                </div>

                                <div class="col-6">
                                    <label class="filter-label">OT / Hoa hồng</label>
                                    <input type="number" min="0" step="any" id="m_commission" class="form-control salary-modal-calc">
                                </div>

                                <div class="col-12">
                                    <label class="filter-label">Thưởng</label>
                                    <input type="number" min="0" step="any" id="m_bonus" class="form-control salary-modal-calc">
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-4">
                        <div class="modal-section">
                            <div class="modal-section-title deduct">
                                <i class="bi bi-dash-circle"></i>
                                Khấu trừ
                            </div>

                            <div class="row g-3">
                                <div class="col-6">
                                    <label class="filter-label">BHXH</label>
                                    <input type="number" min="0" step="any" id="m_bhxh" class="form-control salary-modal-calc">
                                </div>

                                <div class="col-6">
                                    <label class="filter-label">BHYT</label>
                                    <input type="number" min="0" step="any" id="m_bhyt" class="form-control salary-modal-calc">
                                </div>

                                <div class="col-6">
                                    <label class="filter-label">BHTN</label>
                                    <input type="number" min="0" step="any" id="m_bhtn" class="form-control salary-modal-calc">
                                </div>

                                <div class="col-6">
                                    <label class="filter-label">Thuế TNCN</label>
                                    <input type="number" min="0" step="any" id="m_pit" class="form-control salary-modal-calc">
                                </div>

                                <div class="col-6">
                                    <label class="filter-label">Tạm ứng</label>
                                    <input type="number" min="0" step="any" id="m_advance" class="form-control salary-modal-calc">
                                </div>

                                <div class="col-6">
                                    <label class="filter-label">Khấu trừ khác</label>
                                    <input type="number" min="0" step="any" id="m_other_only" class="form-control salary-modal-calc">
                                </div>

                                <div class="col-12">
                                    <label class="filter-label">Phạt đi trễ</label>
                                    <input type="number" min="0" step="any" id="m_late_penalty" class="form-control salary-modal-calc bg-light" readonly>
                                    <div class="small text-muted mt-1" id="m_late_penalty_note">
                                        Tự lấy từ số lần đi trễ × mức phạt trong cài đặt chấm công.
                                    </div>
                                </div>

                                <div class="col-12">
                                    <label class="filter-label">Ghi chú kế toán</label>
                                    <textarea id="m_note_text" rows="3" class="form-control salary-modal-calc" placeholder="Ví dụ: đã đối chiếu công, tạm ứng tháng này..."></textarea>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="modal-summary">
                    <div class="metric">
                        <div class="label">Tổng thu nhập</div>
                        <div class="value" id="m_gross_total">0đ</div>
                    </div>

                    <div class="metric deduct">
                        <div class="label">Tổng khấu trừ</div>
                        <div class="value" id="m_total_deduction">0đ</div>
                    </div>

                    <div class="metric net">
                        <div class="label">Thực lĩnh</div>
                        <div class="value" id="m_net_salary">0đ</div>
                    </div>
                </div>
            </div>

            <div class="modal-footer bg-light">
                <button type="button" class="payroll-btn payroll-btn-light" data-bs-dismiss="modal">
                    Đóng
                </button>

                <button type="button" class="payroll-btn payroll-btn-primary" id="saveModalSalary">
                    <i class="bi bi-check2-circle"></i>
                    Cập nhật dòng lương
                </button>
            </div>
        </div>
    </div>
</div>

<script>
(function () {
    let currentRow = null;
    let salaryModal = null;

    function num(value) {
        if (value === null || value === undefined || value === '') return 0;
        const n = parseFloat(String(value).replace(/,/g, '').trim());
        return isNaN(n) ? 0 : n;
    }

    function money(value) {
        return new Intl.NumberFormat('vi-VN').format(Math.round(value || 0)) + 'đ';
    }

    function get(row, selector) {
        return row.querySelector(selector);
    }

    function getVal(row, selector) {
        return num(get(row, selector)?.value);
    }

    function setVal(row, selector, value) {
        const el = get(row, selector);
        if (el) el.value = value;
    }

    function modalVal(id) {
        return num(document.getElementById(id)?.value);
    }

    function setModalVal(id, value) {
        const el = document.getElementById(id);
        if (el) el.value = value ?? 0;
    }

    function calcDataFromValues(data) {
        const allowance =
            data.businessTrip +
            data.meal +
            data.phone +
            data.housing +
            data.fuel +
            data.child +
            data.province;

        const salaryByDays = data.standardDays > 0
            ? (data.basicSalary / data.standardDays) * data.workingDays
            : 0;

        const gross = salaryByDays + allowance + data.commission + data.bonus;

        const otherDeduction =
            data.bhxh +
            data.bhyt +
            data.bhtn +
            data.pit +
            data.otherOnly +
            data.latePenalty;

        const totalDeduction = otherDeduction + data.advance;
        const net = gross - totalDeduction;

        return {
            allowance,
            salaryByDays,
            gross,
            otherDeduction,
            totalDeduction,
            net
        };
    }

    function getRowData(row) {
        return {
            standardDays: getVal(row, '.js-standard-days'),
            workingDays: getVal(row, '.js-working-days'),
            basicSalary: getVal(row, '.js-basic-salary'),

            businessTrip: getVal(row, '.js-business-trip'),
            meal: getVal(row, '.js-meal'),
            phone: getVal(row, '.js-phone'),
            housing: getVal(row, '.js-housing'),
            fuel: getVal(row, '.js-fuel'),
            child: getVal(row, '.js-child'),
            province: getVal(row, '.js-province'),

            commission: getVal(row, '.js-commission'),
            bonus: getVal(row, '.js-bonus'),

            bhxh: getVal(row, '.js-bhxh'),
            bhyt: getVal(row, '.js-bhyt'),
            bhtn: getVal(row, '.js-bhtn'),
            pit: getVal(row, '.js-pit'),
            advance: getVal(row, '.js-advance'),
            otherOnly: getVal(row, '.js-other-only'),

            latePenalty: getVal(row, '.js-late-penalty'),
            lateCount: getVal(row, '.js-late-count'),
            latePenaltyPerTime: getVal(row, '.js-late-penalty-per-time'),

            isTechAuto: getVal(row, '.js-is-tech-auto'),
            techTotalSalary: getVal(row, '.js-tech-total-salary'),

            noteText: get(row, '.js-note-text')?.value || ''
        };
    }

    function setRowData(row, data) {
        const calc = calcDataFromValues(data);

        setVal(row, '.js-standard-days', data.standardDays);
        setVal(row, '.js-working-days', data.workingDays);
        setVal(row, '.js-basic-salary', data.basicSalary);

        setVal(row, '.js-business-trip', data.businessTrip);
        setVal(row, '.js-meal', data.meal);
        setVal(row, '.js-phone', data.phone);
        setVal(row, '.js-housing', data.housing);
        setVal(row, '.js-fuel', data.fuel);
        setVal(row, '.js-child', data.child);
        setVal(row, '.js-province', data.province);

        setVal(row, '.js-commission', data.commission);
        setVal(row, '.js-bonus', data.bonus);

        setVal(row, '.js-bhxh', data.bhxh);
        setVal(row, '.js-bhyt', data.bhyt);
        setVal(row, '.js-bhtn', data.bhtn);
        setVal(row, '.js-pit', data.pit);
        setVal(row, '.js-advance', data.advance);
        setVal(row, '.js-other-only', data.otherOnly);

        setVal(row, '.js-late-penalty', data.latePenalty);
        setVal(row, '.js-late-count', data.lateCount);
        setVal(row, '.js-late-penalty-per-time', data.latePenaltyPerTime);

        setVal(row, '.js-is-tech-auto', data.isTechAuto);
        setVal(row, '.js-tech-total-salary', data.techTotalSalary);

        setVal(row, '.js-allowance', Math.round(calc.allowance));
        setVal(row, '.js-other-deduction', Math.round(calc.otherDeduction));
        setVal(row, '.js-net-salary', Math.round(calc.net));

        const noteTextEl = get(row, '.js-note-text');
        if (noteTextEl) noteTextEl.value = data.noteText || '';

        const noteJson = {
            note_text: data.noteText || '',
            income_breakdown: {
                business_trip: data.businessTrip,
                meal: data.meal,
                phone: data.phone,
                housing: data.housing,
                fuel: data.fuel,
                child: data.child,
                province: data.province
            },
            deduction_breakdown: {
                bhxh: data.bhxh,
                bhyt: data.bhyt,
                bhtn: data.bhtn,
                pit: data.pit,
                late_penalty: data.latePenalty,
                late_count: data.lateCount,
                late_penalty_per_time: data.latePenaltyPerTime,
                other: data.otherOnly
            }
        };

        const noteHidden = get(row, '.js-note');
        if (noteHidden) noteHidden.value = JSON.stringify(noteJson);

        get(row, '.js-view-days').textContent = data.workingDays + ' / ' + data.standardDays;
        get(row, '.js-view-basic').textContent = money(data.basicSalary);

        const salaryByDaysEl = get(row, '.js-view-salary-by-days');
        if (salaryByDaysEl) {
            salaryByDaysEl.textContent = 'Theo công: ' + money(calc.salaryByDays);
        }

        get(row, '.js-view-income').textContent = money(calc.allowance + data.commission + data.bonus);
        get(row, '.js-view-deduction').textContent = money(calc.totalDeduction);
        get(row, '.js-view-net').textContent = money(calc.net);

        const lateNote = get(row, '.js-view-late-note');
        if (lateNote) {
            if (data.latePenalty > 0) {
                lateNote.classList.remove('empty');
                lateNote.textContent = 'Đi trễ: ' + data.lateCount + ' lần × ' + money(data.latePenaltyPerTime);
            } else {
                lateNote.classList.add('empty');
                lateNote.textContent = 'Không có phạt đi trễ';
            }
        }

        const status = get(row, '.js-row-status');
        if (status) {
            const hasData =
                data.basicSalary > 0 ||
                data.latePenalty > 0 ||
                calc.allowance > 0 ||
                data.commission > 0 ||
                data.bonus > 0 ||
                data.advance > 0 ||
                calc.net !== 0;

            if (hasData) {
                status.classList.add('saved');
                status.innerHTML = '<i class="bi bi-check2-circle"></i> Đã có dữ liệu';
            } else {
                status.classList.remove('saved');
                status.innerHTML = '<i class="bi bi-pencil-square"></i> Chưa nhập';
            }
        }

        return calc;
    }

    function recalcRow(row) {
        return setRowData(row, getRowData(row));
    }

    function recalcAll() {
        let totalDeduction = 0;
        let totalNet = 0;

        document.querySelectorAll('.salary-row').forEach(function (row) {
            const calc = recalcRow(row);
            totalDeduction += calc.totalDeduction;
            totalNet += calc.net;
        });

        const sumDeduction = document.getElementById('sumDeduction');
        const sumNet = document.getElementById('sumNet');

        if (sumDeduction) sumDeduction.textContent = money(totalDeduction);
        if (sumNet) sumNet.textContent = money(totalNet);
    }

    function getModalData() {
        return {
            standardDays: modalVal('m_standard_days'),
            workingDays: modalVal('m_working_days'),
            basicSalary: modalVal('m_basic_salary'),

            businessTrip: modalVal('m_business_trip'),
            meal: modalVal('m_meal'),
            phone: modalVal('m_phone'),
            housing: modalVal('m_housing'),
            fuel: modalVal('m_fuel'),
            child: modalVal('m_child'),
            province: modalVal('m_province'),

            commission: modalVal('m_commission'),
            bonus: modalVal('m_bonus'),

            bhxh: modalVal('m_bhxh'),
            bhyt: modalVal('m_bhyt'),
            bhtn: modalVal('m_bhtn'),
            pit: modalVal('m_pit'),
            advance: modalVal('m_advance'),
            otherOnly: modalVal('m_other_only'),

            latePenalty: modalVal('m_late_penalty'),
            lateCount: currentRow ? getVal(currentRow, '.js-late-count') : 0,
            latePenaltyPerTime: currentRow ? getVal(currentRow, '.js-late-penalty-per-time') : 0,

            isTechAuto: currentRow ? getVal(currentRow, '.js-is-tech-auto') : 0,
            techTotalSalary: currentRow ? getVal(currentRow, '.js-tech-total-salary') : 0,

            noteText: document.getElementById('m_note_text')?.value || ''
        };
    }

    function recalcModal() {
        const data = getModalData();
        const calc = calcDataFromValues(data);

        document.getElementById('m_salary_by_days').textContent = money(calc.salaryByDays);
        document.getElementById('m_gross_total').textContent = money(calc.gross);
        document.getElementById('m_total_deduction').textContent = money(calc.totalDeduction);
        document.getElementById('m_net_salary').textContent = money(calc.net);
    }

    function fillModal(row) {
        const data = getRowData(row);

        setModalVal('m_standard_days', data.standardDays);
        setModalVal('m_working_days', data.workingDays);
        setModalVal('m_basic_salary', data.basicSalary);

        setModalVal('m_business_trip', data.businessTrip);
        setModalVal('m_meal', data.meal);
        setModalVal('m_phone', data.phone);
        setModalVal('m_housing', data.housing);
        setModalVal('m_fuel', data.fuel);
        setModalVal('m_child', data.child);
        setModalVal('m_province', data.province);

        setModalVal('m_commission', data.commission);
        setModalVal('m_bonus', data.bonus);

        setModalVal('m_bhxh', data.bhxh);
        setModalVal('m_bhyt', data.bhyt);
        setModalVal('m_bhtn', data.bhtn);
        setModalVal('m_pit', data.pit);
        setModalVal('m_advance', data.advance);
        setModalVal('m_other_only', data.otherOnly);
        setModalVal('m_late_penalty', data.latePenalty);

        const lateNote = document.getElementById('m_late_penalty_note');
        if (lateNote) {
            if (data.latePenalty > 0) {
                lateNote.textContent = 'Tự tính: ' + data.lateCount + ' lần đi trễ × ' + money(data.latePenaltyPerTime) + ' / lần.';
            } else {
                lateNote.textContent = 'Nhân viên này chưa có phạt đi trễ trong kỳ lương.';
            }
        }

        const basicInput = document.getElementById('m_basic_salary');
        if (basicInput) {
            if (data.isTechAuto && data.techTotalSalary > 0) {
                basicInput.value = data.techTotalSalary;
                basicInput.readOnly = true;
                basicInput.classList.add('bg-light');
            } else {
                basicInput.readOnly = false;
                basicInput.classList.remove('bg-light');
            }
        }

        const note = document.getElementById('m_note_text');
        if (note) note.value = data.noteText || '';

        const employeeName = row.querySelector('.employee-name')?.textContent || 'Nhân viên';
        const employeeSub = row.querySelector('.employee-meta')?.innerText || '';

        document.getElementById('modalEmployeeName').textContent = employeeName;
        document.getElementById('modalEmployeeSub').textContent = employeeSub;

        recalcModal();
    }

    document.addEventListener('click', function (event) {
        const btn = event.target.closest('.js-edit-row');
        if (!btn) return;

        currentRow = btn.closest('.salary-row');
        if (!currentRow) return;

        fillModal(currentRow);

        const modalEl = document.getElementById('salaryEditModal');
        salaryModal = salaryModal || new bootstrap.Modal(modalEl);
        salaryModal.show();
    });

    document.querySelectorAll('.salary-modal-calc').forEach(function (input) {
        input.addEventListener('input', recalcModal);
    });

    const saveModalBtn = document.getElementById('saveModalSalary');
    if (saveModalBtn) {
        saveModalBtn.addEventListener('click', function () {
            if (!currentRow) return;

            setRowData(currentRow, getModalData());
            recalcAll();

            if (salaryModal) salaryModal.hide();
        });
    }

    const form = document.getElementById('salaryForm');
    if (form) {
        form.addEventListener('submit', function () {
            recalcAll();
        });
    }

    document.addEventListener('DOMContentLoaded', recalcAll);
    recalcAll();
})();
</script>
@endsection