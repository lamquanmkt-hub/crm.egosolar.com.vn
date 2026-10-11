@extends('layouts.app')

@section('title', 'KPI phòng ban')

@section('content')
<div id="egoAttendancePromax" class="pr-workspace">
    <div class="at-shell">
        @include('hr.payroll._breadcrumb', ['pageTitle' => 'KPI phòng ban'])
        <header class="pr-page-head">
            <div class="at-heading">
                <div>
                    <h1>KPI phòng ban</h1>
                    <p>Chọn bộ KPI, xem nhân viên trong kỳ và hoàn tất phần còn thiếu.</p>
                </div>
            </div>
            <a class="at-btn at-btn--light" href="{{ route('hr.payroll.index', ['month' => $month]) }}"><i class="bi bi-table"></i>Bảng lương tháng</a>
        </header>
        @include('hr.payroll._head')
        @include('hr.kpi._department-cards')
        <section class="at-panel pk-toolbar" aria-label="Kỳ và chế độ KPI">
            <form method="GET" action="{{ route('hr.kpi.index') }}" class="pk-period">
                <input type="hidden" name="template" value="{{ $template->code }}">
                <input type="hidden" name="tab" value="{{ $tab }}">
                <label for="pk-month">Kỳ đánh giá</label>
                <input id="pk-month" class="pr-input" type="month" name="month" value="{{ $month }}" required>
                @if($tab !== 'settings' && isset($employeeOptions) && $employeeOptions->count() > 1)
                    <label for="pk-employee">Nhân viên</label>
                    <select id="pk-employee" class="pr-input" name="employee" data-pk-employee>
                        <option value="">Tất cả ({{ $employeeOptions->count() }})</option>
                        @foreach($employeeOptions as $opt)<option value="{{ $opt->id }}" @selected((int) ($employeeId ?? 0) === (int) $opt->id)>{{ $opt->name }}</option>@endforeach
                    </select>
                @endif
                <button class="at-btn at-btn--primary" type="submit"><i class="bi bi-funnel"></i>Xem kỳ</button>
            </form>
            <nav class="pk-mode" aria-label="KPI phòng ban">
                <a href="{{ route('hr.kpi.index', ['template' => $template->code, 'tab' => 'scores', 'month' => $month]) }}" @if($tab === 'scores') aria-current="page" @endif><i class="bi bi-list-check"></i>Chấm KPI</a>
                <a href="{{ route('hr.kpi.index', ['template' => $template->code, 'tab' => 'settings', 'month' => $month]) }}" @if($tab === 'settings') aria-current="page" @endif><i class="bi bi-sliders"></i>Cài đặt tiêu chí</a>
            </nav>
        </section>
        @if($tab === 'settings')
            @include('hr.kpi._settings')
        @else
            @include('hr.kpi._scores')
        @endif
    </div>
</div>
@endsection
