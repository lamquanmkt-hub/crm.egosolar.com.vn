@extends('layouts.app')

@section('title', 'Cài đặt lương')

@section('content')
@php $paidTypes = \App\Services\Hr\Payroll\PayrollSettings::paidLeaveTypes($settings); @endphp

<div id="egoAttendancePromax">
    <div class="at-shell">
        <header class="at-hero at-panel">
            <div class="at-heading">
                <div class="at-heading__icon"><i class="bi bi-gear"></i></div>
                <div>
                    <span>NHÂN SỰ · LƯƠNG & KPI</span>
                    <h1>Cài đặt tính lương</h1>
                    <p>Giảm trừ gia cảnh, tỷ lệ bảo hiểm, trần đóng BH, quy tắc ngày công. Bảng lương đã duyệt giữ nguyên tham số lúc tính.</p>
                </div>
            </div>
        </header>

        @include('hr.payroll._head')

        <section class="at-panel pr-body">
            <form method="POST" action="{{ route('hr.payroll.settings.save') }}">
                @csrf
                <div class="pr-grid">
                    @foreach($labels as $key => $label)
                        @continue($key === 'paid_leave_types')
                        <div class="pr-field">
                            <label>{{ $label }}</label>
                            <input class="pr-input" type="number" step="any" min="0" name="{{ $key }}" value="{{ old($key, $settings[$key] + 0) }}" required>
                        </div>
                    @endforeach
                </div>

                <div class="pr-field pr-section">
                    <label>{{ $labels['paid_leave_types'] }}</label>
                    @foreach($leaveTypes as $value => $label)
                        <label style="display:inline-flex;gap:6px;margin-right:16px;text-transform:none;font-size:12px">
                            <input type="checkbox" name="paid_leave_types[]" value="{{ $value }}" @checked(in_array($value, $paidTypes, true))> {{ $label }}
                        </label>
                    @endforeach
                </div>

                <p class="pr-hint pr-section">
                    <strong>Cách tính ngày công:</strong> công chuẩn = ngày làm việc theo Cài đặt chấm công (gồm thứ 7 theo lịch) + ngày lễ có hưởng lương.
                    Mỗi ngày làm việc: có check-in / đơn làm online / công tác đã duyệt = đi làm; đơn nghỉ đã duyệt thuộc loại tích ở trên = nghỉ hưởng lương; còn lại = không lương.
                    Đơn nửa ngày tính 0,5. Đi trễ (trừ ngày có đơn xin đi trễ đã duyệt) × mức phạt trong Cài đặt chấm công.
                    <br><strong>Thuế TNCN:</strong> biểu lũy tiến 5 bậc 5% – 10% – 20% – 30% – 35% (ngưỡng 10 / 30 / 60 / 100 triệu), như công thức trong file lương cũ.
                    <br><strong>Tiền ăn, điện thoại, công tác tỉnh, tăng ca</strong> tính vào thu nhập không chịu thuế (giống file Excel).
                </p>

                <button class="at-btn at-btn--primary" type="submit"><i class="bi bi-check2"></i>Lưu cài đặt</button>
            </form>
        </section>
    </div>
</div>
@endsection
