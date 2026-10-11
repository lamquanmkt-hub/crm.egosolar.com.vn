@extends('layouts.app')

@section('title', 'Hồ sơ lương')

@section('content')
@php
    $vnd = static fn ($n) => number_format((float) $n, 0, ',', '.');
    $fields = [
        'base_salary' => 'Lương chính',
        'kpi_salary' => 'Hiệu suất công việc (lương KPI 100%)',
        'decision_bonus' => 'Thưởng theo QĐ',
        'travel_allowance' => 'Hỗ trợ đi lại',
        'phone_allowance' => 'Hỗ trợ điện thoại',
        'meal_allowance' => 'Tiền ăn',
    ];
@endphp

<div id="egoAttendancePromax">
    <div class="at-shell">
        <header class="at-hero at-panel">
            <div class="at-heading">
                <div class="at-heading__icon"><i class="bi bi-person-vcard"></i></div>
                <div>
                    <span>NHÂN SỰ · LƯƠNG & KPI</span>
                    <h1>Hồ sơ lương nhân viên</h1>
                    <p>Mức lương hợp đồng theo tháng hiệu lực (thay sheet "Bảng theo dõi lao động"). Đổi lương = lưu bản mới với tháng áp dụng mới, lịch sử được giữ.</p>
                </div>
            </div>
        </header>

        @include('hr.payroll._head')

        <section class="at-panel pr-body" style="margin-bottom:12px">
            <form method="GET" class="pr-filter">
                <div><label>Xem hồ sơ hiệu lực tại tháng</label><input class="pr-input" type="month" name="month" value="{{ $month }}"></div>
                <button class="at-btn at-btn--primary" type="submit"><i class="bi bi-funnel"></i>Xem</button>
                <span class="pr-muted">{{ $profiles->count() }} / {{ $employees->count() }} nhân viên đã có hồ sơ lương → có trong bảng lương tháng này.</span>
            </form>
            <p class="pr-hint" style="margin:12px 0 0">
                <strong>Hiệu suất công việc</strong> là phần lương KPI khi đạt 100%; thực nhận = Hiệu suất × tỷ lệ ngày công × hệ số KPI của bộ KPI được gán.
                Nhân viên không gán bộ KPI hưởng đủ hiệu suất như file Excel cũ. Lương đóng BH để trống = bằng lương chính.
            </p>
        </section>

        <section class="at-panel">
            <div class="at-table-wrap">
                <table class="at-table pr-table" style="min-width:1250px">
                    <thead>
                        <tr>
                            <th class="pr-sticky">Nhân viên</th>
                            <th>Áp dụng từ</th>
                            <th class="pr-num">Lương chính</th>
                            <th class="pr-num">Hiệu suất (KPI)</th>
                            <th class="pr-num">Thưởng QĐ</th>
                            <th class="pr-num">Đi lại</th>
                            <th class="pr-num">Điện thoại</th>
                            <th class="pr-num">Tiền ăn</th>
                            <th class="pr-num">Lương đóng BH</th>
                            <th>NPT</th>
                            <th>Bộ KPI</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($employees as $employee)
                            @php $p = $profiles->get($employee->id); @endphp
                            <tr>
                                <td class="pr-sticky"><span class="pr-strong">{{ $employee->name }}</span></td>
                                @if($p)
                                    <td>{{ $p->effective_month }}</td>
                                    <td class="pr-num">{{ $vnd($p->base_salary) }}</td>
                                    <td class="pr-num">{{ $vnd($p->kpi_salary) }}</td>
                                    <td class="pr-num">{{ $vnd($p->decision_bonus) }}</td>
                                    <td class="pr-num">{{ $vnd($p->travel_allowance) }}</td>
                                    <td class="pr-num">{{ $vnd($p->phone_allowance) }}</td>
                                    <td class="pr-num">{{ $vnd($p->meal_allowance) }}</td>
                                    <td class="pr-num">{{ $p->insurance_enabled ? $vnd($p->insurance_salary ?? $p->base_salary) : 'Không đóng' }}</td>
                                    <td>{{ $p->dependents }}</td>
                                    <td>{{ $p->kpi_template ? ($kpiTemplates[$p->kpi_template] ?? $p->kpi_template) : '—' }}</td>
                                @else
                                    <td colspan="10" class="pr-muted">Chưa có hồ sơ lương — không có trong bảng lương.</td>
                                @endif
                                <td><button type="button" class="at-btn at-btn--primary" style="min-height:30px" onclick="document.getElementById('pf-{{ $employee->id }}').classList.toggle('is-open')"><i class="bi bi-pencil"></i>{{ $p ? 'Sửa' : 'Nhập' }}</button></td>
                            </tr>
                            <tr class="pr-edit {{ old('user_id') == $employee->id ? 'is-open' : '' }}" id="pf-{{ $employee->id }}">
                                <td colspan="12">
                                    <form method="POST" action="{{ route('hr.payroll.profiles.store') }}">
                                        @csrf
                                        <input type="hidden" name="user_id" value="{{ $employee->id }}">
                                        <div class="pr-grid">
                                            <div class="pr-field"><label>Áp dụng từ tháng</label><input class="pr-input" type="month" name="effective_month" value="{{ $month }}" required></div>
                                            @foreach($fields as $field => $label)
                                                <div class="pr-field"><label>{{ $label }}</label><input class="pr-input" type="number" min="0" step="1000" name="{{ $field }}" value="{{ $p ? (float) $p->{$field} : 0 }}" required></div>
                                            @endforeach
                                            <div class="pr-field"><label>Lương đóng BH (trống = lương chính)</label><input class="pr-input" type="number" min="0" step="1000" name="insurance_salary" value="{{ $p && $p->insurance_salary !== null ? (float) $p->insurance_salary : '' }}"></div>
                                            <div class="pr-field"><label>Số người phụ thuộc</label><input class="pr-input" type="number" min="0" max="20" name="dependents" value="{{ $p->dependents ?? 0 }}" required></div>
                                            <div class="pr-field">
                                                <label>Thuế TNCN</label>
                                                <select class="pr-input" name="pit_mode">
                                                    @foreach($pitModes as $value => $label)
                                                        <option value="{{ $value }}" @selected(($p->pit_mode ?? 'progressive') === $value)>{{ $label }}</option>
                                                    @endforeach
                                                </select>
                                            </div>
                                            <div class="pr-field">
                                                <label>Bộ KPI</label>
                                                <select class="pr-input" name="kpi_template">
                                                    <option value="">Không áp dụng (hưởng đủ hiệu suất)</option>
                                                    @foreach($kpiTemplates as $code => $label)
                                                        <option value="{{ $code }}" @selected(($p->kpi_template ?? null) === $code)>{{ $label }}</option>
                                                    @endforeach
                                                </select>
                                            </div>
                                            <div class="pr-field"><label>&nbsp;</label><label style="text-transform:none;font-size:12px"><input type="checkbox" name="insurance_enabled" value="1" @checked(! $p || $p->insurance_enabled)> Có đóng BHXH/BHYT/BHTN</label></div>
                                            <div class="pr-field" style="grid-column:span 2"><label>Ghi chú</label><input class="pr-input" type="text" name="note" maxlength="500" value="{{ $p->note ?? '' }}"></div>
                                        </div>
                                        <button class="at-btn at-btn--primary" type="submit" style="margin-top:10px"><i class="bi bi-check2"></i>Lưu hồ sơ lương</button>
                                        @if($history->has($employee->id) && $history->get($employee->id)->count() > 1)
                                            <span class="pr-muted" style="margin-left:10px">Lịch sử:
                                                @foreach($history->get($employee->id) as $h)
                                                    {{ $h->effective_month }}: {{ $vnd($h->base_salary) }} + {{ $vnd($h->kpi_salary) }}@if(! $loop->last); @endif
                                                @endforeach
                                            </span>
                                        @endif
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</div>
@endsection
