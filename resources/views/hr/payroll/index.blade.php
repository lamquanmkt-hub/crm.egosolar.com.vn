@extends('layouts.app')

@section('title', 'Bảng lương tháng')

@section('content')
@php
    $vnd = static fn ($n) => number_format((float) $n, 0, ',', '.');
    $num = static fn ($n) => rtrim(rtrim(number_format((float) $n, 2, ',', '.'), '0'), ',');
    $monthLabel = \Illuminate\Support\Carbon::createFromFormat('Y-m', $month)->format('m/Y');
    $isApproved = $period && $period->status === 'approved';
@endphp

<div id="egoAttendancePromax">
    <div class="at-shell">
        <header class="at-hero at-panel">
            <div class="at-heading">
                <div class="at-heading__icon"><i class="bi bi-cash-coin"></i></div>
                <div>
                    <span>NHÂN SỰ · LƯƠNG & KPI</span>
                    <h1>Bảng lương tháng {{ $monthLabel }}</h1>
                    <p>Tự tính từ hồ sơ lương, chấm công, nghỉ phép, công tác, tạm ứng và KPI. HR rà soát, Ban Giám đốc duyệt & khoá.</p>
                </div>
            </div>
            <div class="at-actions">
                @if(! $isApproved)
                    <form method="POST" action="{{ route('hr.payroll.generate') }}">
                        @csrf
                        <input type="hidden" name="month" value="{{ $month }}">
                        <button class="at-btn at-btn--light" type="submit"><i class="bi bi-calculator"></i>{{ $period ? 'Tính lại' : 'Tạo bảng lương' }}</button>
                    </form>
                @endif
                @if($period && ! $isApproved && $canApprove)
                    <form method="POST" action="{{ route('hr.payroll.approve', $period->id) }}" onsubmit="return confirm('Duyệt và khoá bảng lương tháng {{ $monthLabel }}? Sau khi duyệt nhân viên xem được phiếu lương.')">
                        @csrf
                        <button class="at-btn at-btn--light" type="submit"><i class="bi bi-lock"></i>Duyệt & khoá</button>
                    </form>
                @endif
                @if($period)
                    <a class="at-btn at-btn--glass" href="{{ route('hr.payroll.export', ['month' => $month]) }}"><i class="bi bi-file-earmark-excel"></i>Xuất Excel</a>
                @endif
            </div>
        </header>

        @include('hr.payroll._head')

        <section class="at-panel pr-body" style="margin-bottom:12px">
            <form method="GET" class="pr-filter">
                <div>
                    <label>Kỳ lương</label>
                    <input class="pr-input" type="month" name="month" value="{{ $month }}">
                </div>
                @if($departments->count() > 1)
                    <div>
                        <label>Phòng ban</label>
                        <select class="pr-input" name="department">
                            <option value="">Tất cả phòng ban</option>
                            @foreach($departments as $d)
                                <option value="{{ $d }}" @selected($department === $d)>{{ $d }}</option>
                            @endforeach
                        </select>
                    </div>
                @endif
                <button class="at-btn at-btn--primary" type="submit"><i class="bi bi-funnel"></i>Xem</button>
                @if($period)
                    <span class="at-status-pill at-status-pill--{{ $isApproved ? 'success' : 'warning' }}">{{ $isApproved ? 'Đã duyệt & khoá' : 'Bản nháp' }}</span>
                    <span class="pr-muted">
                        Công chuẩn {{ $num($period->standard_days) }} ngày
                        @if($period->calculated_at) · Tính lúc {{ \Illuminate\Support\Carbon::parse($period->calculated_at)->format('d/m/Y H:i') }} @endif
                        @if($isApproved && $period->approved_at) · Duyệt lúc {{ \Illuminate\Support\Carbon::parse($period->approved_at)->format('d/m/Y H:i') }} @endif
                    </span>
                @endif
            </form>
        </section>

        @if(! $period)
            <section class="at-panel pr-body">
                <p class="pr-hint">
                    Chưa có bảng lương tháng {{ $monthLabel }}. Hiện có <strong>{{ $profileCount }}</strong> nhân viên có hồ sơ lương hiệu lực.
                    @if($profileCount === 0)
                        Vào <a href="{{ route('hr.payroll.profiles', ['month' => $month]) }}">Hồ sơ lương</a> nhập lương chính, hiệu suất (lương KPI), phụ cấp và bộ KPI cho từng nhân viên trước.
                    @else
                        Bấm <strong>Tạo bảng lương</strong> để hệ thống tự tính. Nên chấm KPI tháng trước khi tạo.
                    @endif
                </p>
            </section>
        @else
            <section class="at-kpis" style="grid-template-columns:repeat(5,minmax(0,1fr))">
                <article class="at-kpi at-panel"><div class="at-kpi__icon"><i class="bi bi-people"></i></div><label>Nhân viên</label><strong>{{ $lines->count() }}</strong><small>{{ $totals['warnings'] }} dòng cần kiểm tra</small></article>
                <article class="at-kpi at-panel"><div class="at-kpi__icon"><i class="bi bi-wallet2"></i></div><label>Tổng thu nhập</label><strong>{{ $vnd($totals['gross']) }}</strong><small>VNĐ</small></article>
                <article class="at-kpi at-panel"><div class="at-kpi__icon"><i class="bi bi-shield-check"></i></div><label>Bảo hiểm</label><strong>{{ $vnd($totals['insurance_employee'] + $totals['insurance_employer']) }}</strong><small>NLĐ {{ $vnd($totals['insurance_employee']) }} · Cty {{ $vnd($totals['insurance_employer']) }}</small></article>
                <article class="at-kpi at-panel"><div class="at-kpi__icon"><i class="bi bi-receipt"></i></div><label>Thuế TNCN</label><strong>{{ $vnd($totals['pit']) }}</strong><small>VNĐ</small></article>
                <article class="at-kpi at-panel"><div class="at-kpi__icon"><i class="bi bi-cash-stack"></i></div><label>Tổng thực lĩnh</label><strong>{{ $vnd($totals['net']) }}</strong><small>VNĐ chuyển khoản</small></article>
            </section>

            <section class="at-panel">
                <div class="at-card-head">
                    <div>
                        <h2><i class="bi bi-table"></i>Chi tiết lương</h2>
                        <p>Ô có dấu ✎ là giá trị HR đã sửa tay (được giữ khi Tính lại). Bấm "Sửa" để chỉnh ngày công, hệ số KPI, khoản khác, tạm ứng, trừ khác.</p>
                    </div>
                </div>
                <div class="at-table-wrap">
                    <table class="at-table pr-table" style="min-width:1450px">
                        <thead>
                            <tr>
                                <th class="pr-sticky">Nhân viên</th>
                                <th class="pr-num">Công tính lương</th>
                                <th class="pr-num">Lương chính</th>
                                <th>KPI</th>
                                <th class="pr-num">Lương KPI</th>
                                <th class="pr-num">Phụ cấp & khác</th>
                                <th class="pr-num">Tổng thu nhập</th>
                                <th class="pr-num">BH NLĐ</th>
                                <th class="pr-num">Thuế TNCN</th>
                                <th class="pr-num">Tạm ứng / trừ</th>
                                <th class="pr-num">Thực lĩnh</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($lines->groupBy(fn ($l) => \App\Http\Controllers\Hr\PayrollController::departmentOf($l)) as $deptName => $deptLines)
                            <tr class="pr-dept">
                                <td class="pr-sticky" colspan="6"><i class="bi bi-diagram-3"></i> {{ $deptName }} <span class="pr-muted">· {{ $deptLines->count() }} người</span></td>
                                <td class="pr-num">{{ $vnd($deptLines->sum('gross_income')) }}</td>
                                <td class="pr-num">{{ $vnd($deptLines->sum('insurance_employee')) }}</td>
                                <td class="pr-num">{{ $vnd($deptLines->sum('pit')) }}</td>
                                <td class="pr-num">{{ $vnd($deptLines->sum('advance') + $deptLines->sum('late_penalty') + $deptLines->sum('other_deduction')) }}</td>
                                <td class="pr-num">{{ $vnd($deptLines->sum('net_salary')) }}</td>
                                <td></td>
                            </tr>
                            @foreach($deptLines as $line)
                                @php
                                    $manual = json_decode((string) $line->manual, true) ?: [];
                                    $warnings = json_decode((string) $line->warnings, true) ?: [];
                                    $mark = static fn ($key) => array_key_exists($key, $manual) ? ' ✎' : '';
                                    $allowances = $line->decision_bonus_pay + $line->travel_pay + $line->meal_pay + $line->phone_pay + $line->trip_allowance + $line->ot_amount + $line->other_income;
                                    $deductions = $line->advance + $line->late_penalty + $line->other_deduction;
                                @endphp
                                <tr>
                                    <td class="pr-sticky">
                                        <span class="pr-strong">{{ $line->employee_name }}</span>
                                        <div class="pr-muted">{{ $line->employee_code ?: '—' }} · {{ $line->position_name ?: ($line->department_name ?: '—') }}</div>
                                        @foreach($warnings as $w)<div class="pr-warn"><i class="bi bi-exclamation-triangle"></i> {{ $w }}</div>@endforeach
                                    </td>
                                    <td class="pr-num">
                                        <span class="pr-strong">{{ $num($line->paid_days) }}{{ $mark('paid_days') }}</span> / {{ $num($line->standard_days) }}
                                        <div class="pr-muted" title="Đi làm · Phép · Lễ · Không lương">{{ $num($line->worked_days) }} · P {{ $num($line->paid_leave_days) }} · L {{ $num($line->holiday_days) }} · KL {{ $num($line->unpaid_days) }}</div>
                                    </td>
                                    <td class="pr-num">{{ $vnd($line->base_pay) }}<div class="pr-muted">HS {{ $vnd($line->base_salary) }}</div></td>
                                    <td>
                                        @if($line->kpi_percent !== null)<span class="pr-strong">{{ $num($line->kpi_percent) }}%</span>@endif
                                        @if($line->kpi_rate !== null)<span class="pr-muted"> → hưởng {{ $num($line->kpi_rate * 100) }}%{{ $mark('kpi_rate') }}</span>@endif
                                        <div class="pr-muted" style="max-width:220px;white-space:normal">{{ $line->kpi_label }}</div>
                                    </td>
                                    <td class="pr-num">{{ $vnd($line->kpi_pay) }}<div class="pr-muted">HS {{ $vnd($line->kpi_salary) }}</div></td>
                                    <td class="pr-num">{{ $vnd($allowances) }}
                                        <div class="pr-muted">CT/TC {{ $vnd($line->trip_allowance + $line->ot_amount) }}{{ $mark('trip_allowance') }}{{ $mark('ot_amount') }} · Khác {{ $vnd($line->other_income) }}{{ $mark('other_income') }}</div>
                                    </td>
                                    <td class="pr-num pr-strong">{{ $vnd($line->gross_income) }}</td>
                                    <td class="pr-num">{{ $vnd($line->insurance_employee) }}</td>
                                    <td class="pr-num">{{ $vnd($line->pit) }}</td>
                                    <td class="pr-num">{{ $vnd($deductions) }}
                                        <div class="pr-muted">TƯ {{ $vnd($line->advance) }}{{ $mark('advance') }} · Trễ {{ $line->late_count }} lần {{ $vnd($line->late_penalty) }}{{ $mark('late_penalty') }}</div>
                                    </td>
                                    <td class="pr-num pr-strong" style="color:#08745a">{{ $vnd($line->net_salary) }}</td>
                                    <td style="white-space:nowrap">
                                        @if(! $isApproved && (int) $line->user_id !== (int) auth()->id())
                                            <button type="button" class="at-btn at-btn--primary" style="min-height:30px" onclick="document.getElementById('pr-edit-{{ $line->id }}').classList.toggle('is-open')"><i class="bi bi-pencil"></i>Sửa</button>
                                        @endif
                                        <a class="at-btn at-btn--light" style="min-height:30px;border-color:var(--at-line)" href="{{ route('hr.payroll.payslip', $line->id) }}" target="_blank"><i class="bi bi-file-text"></i>Phiếu</a>
                                    </td>
                                </tr>
                                @if(! $isApproved && (int) $line->user_id !== (int) auth()->id())
                                    <tr class="pr-edit" id="pr-edit-{{ $line->id }}">
                                        <td colspan="12">
                                            <form method="POST" action="{{ route('hr.payroll.lines.update', $line->id) }}">
                                                @csrf
                                                @method('PUT')
                                                <div class="pr-grid">
                                                    @foreach($manualFields as $field => $label)
                                                        @continue($field === 'kpi_rate' && ! $canApprove)
                                                        <div class="pr-field">
                                                            <label>{{ $label }}</label>
                                                            <input class="pr-input" type="number" step="any" min="0" name="manual[{{ $field }}]" value="{{ $manual[$field] ?? '' }}"
                                                                   placeholder="Tự động: {{ $field === 'kpi_rate' ? ($line->kpi_rate !== null && ! isset($manual['kpi_rate']) ? $num($line->kpi_rate * 100) : '—') : (isset($manual[$field]) ? '—' : $num($line->{$field})) }}">
                                                        </div>
                                                    @endforeach
                                                    <div class="pr-field" style="grid-column:span 4">
                                                        <label>Ghi chú</label>
                                                        <input class="pr-input" type="text" name="note" maxlength="1000" value="{{ $line->note }}">
                                                    </div>
                                                </div>
                                                <p class="pr-muted" style="margin:8px 0">Để trống ô = dùng số tự động. Hệ số KPI nhập theo %, vd 93 = hưởng 93% lương KPI (chỉ Ban Giám đốc sửa được). Không ai sửa được dòng lương của chính mình.</p>
                                                <button class="at-btn at-btn--primary" type="submit"><i class="bi bi-check2"></i>Lưu & tính lại dòng này</button>
                                            </form>
                                        </td>
                                    </tr>
                                @endif
                            @endforeach
                            @endforeach
                        </tbody>
                        <tfoot>
                            <tr>
                                <td class="pr-sticky">Tổng cộng</td>
                                <td></td>
                                <td class="pr-num">{{ $vnd($lines->sum('base_pay')) }}</td>
                                <td></td>
                                <td class="pr-num">{{ $vnd($lines->sum('kpi_pay')) }}</td>
                                <td></td>
                                <td class="pr-num">{{ $vnd($totals['gross']) }}</td>
                                <td class="pr-num">{{ $vnd($totals['insurance_employee']) }}</td>
                                <td class="pr-num">{{ $vnd($totals['pit']) }}</td>
                                <td class="pr-num">{{ $vnd($lines->sum('advance') + $lines->sum('late_penalty') + $lines->sum('other_deduction')) }}</td>
                                <td class="pr-num">{{ $vnd($totals['net']) }}</td>
                                <td></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </section>

            @if($isApproved && $canApprove)
                <section class="at-panel pr-body pr-section">
                    <details class="pr-details">
                        <summary>Mở khoá bảng lương để sửa (ghi lại lý do)</summary>
                        <form method="POST" action="{{ route('hr.payroll.reopen', $period->id) }}" class="pr-filter" style="margin-top:10px">
                            @csrf
                            <div style="flex:1"><label>Lý do mở khoá</label><input class="pr-input" type="text" name="reason" required minlength="5" maxlength="1000"></div>
                            <button class="at-btn at-btn--danger" type="submit"><i class="bi bi-unlock"></i>Mở khoá</button>
                        </form>
                    </details>
                </section>
            @endif
            @if($period->note)
                <p class="pr-muted pr-section" style="white-space:pre-line">{{ $period->note }}</p>
            @endif
        @endif
    </div>
</div>
@endsection
