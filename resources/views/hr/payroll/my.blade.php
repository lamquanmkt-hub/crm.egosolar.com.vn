@extends('layouts.app')

@section('title', 'Phiếu lương của tôi')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/ego-attendance-promax.css') }}?v={{ filemtime(public_path('css/ego-attendance-promax.css')) }}">
@endpush

@section('content')
@php $vnd = static fn ($n) => number_format((float) $n, 0, ',', '.'); @endphp

<div id="egoAttendancePromax">
    <div class="at-shell">
        <header class="at-hero at-panel">
            <div class="at-heading">
                <div class="at-heading__icon"><i class="bi bi-file-earmark-text"></i></div>
                <div>
                    <span>NHÂN SỰ · LƯƠNG</span>
                    <h1>Phiếu lương của tôi</h1>
                    <p>Phiếu lương các tháng đã được Ban Giám đốc duyệt.</p>
                </div>
            </div>
        </header>

        <section class="at-panel">
            <div class="at-table-wrap">
                <table class="at-table">
                    <thead>
                        <tr><th>Kỳ lương</th><th>Công tính lương</th><th>KPI</th><th style="text-align:right">Tổng thu nhập</th><th style="text-align:right">Giảm trừ</th><th style="text-align:right">Thực lĩnh</th><th></th></tr>
                    </thead>
                    <tbody>
                        @forelse($lines as $line)
                            <tr>
                                <td><strong>{{ \Illuminate\Support\Carbon::createFromFormat('Y-m', $line->payroll_month)->format('m/Y') }}</strong></td>
                                <td>{{ (float) $line->paid_days }} / {{ (float) $line->standard_days }}</td>
                                <td>{{ $line->kpi_percent !== null ? (float) $line->kpi_percent.'%' : '—' }}</td>
                                <td style="text-align:right">{{ $vnd($line->gross_income) }}</td>
                                <td style="text-align:right">{{ $vnd($line->insurance_employee + $line->pit + $line->advance + $line->late_penalty + $line->other_deduction) }}</td>
                                <td style="text-align:right"><strong>{{ $vnd($line->net_salary) }}</strong></td>
                                <td><a class="at-btn at-btn--primary" style="min-height:30px" href="{{ route('hr.payroll.payslip', $line->id) }}" target="_blank"><i class="bi bi-eye"></i>Xem phiếu</a></td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="at-empty">Chưa có phiếu lương nào được duyệt.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</div>
@endsection
