@extends('layouts.app')

@section('title', 'KPI của tôi')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/ego-attendance-promax.css') }}?v={{ filemtime(public_path('css/ego-attendance-promax.css')) }}">
    <style>
        #egoAttendancePromax .pr-body{padding:14px 16px}
        #egoAttendancePromax .pr-filter{display:flex;flex-wrap:wrap;align-items:end;gap:10px}
        #egoAttendancePromax .pr-filter label{display:block;margin-bottom:4px;color:#5b7284;font-size:10px;font-weight:800;text-transform:uppercase;letter-spacing:.03em}
        #egoAttendancePromax .pr-input{border:1px solid #d2e2e8;border-radius:9px;padding:7px 9px;font-size:12px;background:#fff;color:#1d3a50}
        #egoAttendancePromax .pr-num{text-align:right;white-space:nowrap;font-variant-numeric:tabular-nums}
        #egoAttendancePromax .pr-muted{color:#7a8f9c;font-size:10px}
        #egoAttendancePromax .pr-hint{margin:12px 0 0;padding:10px 12px;border:1px dashed #b9d9e2;border-radius:10px;background:#f7fcfd;color:#35556b;font-size:11px;line-height:1.55}
        #egoAttendancePromax .pr-section{margin-top:12px}
        #egoAttendancePromax .pr-big{font-size:28px;font-weight:900;color:var(--at-ink)}
    </style>
@endpush

@section('content')
@php
    $num = static fn ($n) => rtrim(rtrim(number_format((float) $n, 2, ',', '.'), '0'), ',');
    $monthLabel = \Illuminate\Support\Carbon::createFromFormat('Y-m', $month)->format('m/Y');
    $isTechnical = $code === \App\Services\Hr\Kpi\HrKpiEvaluator::TECHNICAL;
@endphp

<div id="egoAttendancePromax">
    <div class="at-shell">
        <header class="at-hero at-panel">
            <div class="at-heading">
                <div class="at-heading__icon"><i class="bi bi-person-check"></i></div>
                <div>
                    <span>NHÂN SỰ · LƯƠNG & KPI</span>
                    <h1>KPI của tôi — tháng {{ $monthLabel }}</h1>
                    <p>Điểm KPI do Ban Giám đốc / Trưởng phòng chấm và % lương KPI được hưởng. Có thắc mắc vui lòng báo Hành chính Nhân sự trước ngày 8.</p>
                </div>
            </div>
        </header>

        <section class="at-panel pr-body">
            <form method="GET" class="pr-filter">
                <div><label>Tháng</label><input class="pr-input" type="month" name="month" value="{{ $month }}"></div>
                <button class="at-btn at-btn--primary" type="submit"><i class="bi bi-funnel"></i>Xem</button>
            </form>

            @if($code === null || $code === '')
                <p class="pr-hint">Tháng {{ $monthLabel }} bạn chưa được áp dụng bộ KPI nào — lương hiệu suất được hưởng đủ theo ngày công.</p>
            @else
                <div class="pr-section" style="display:flex;flex-wrap:wrap;gap:28px;align-items:flex-end">
                    <div>
                        <div class="pr-muted">ĐIỂM KPI</div>
                        <div class="pr-big">{{ $summary['percent'] !== null ? $num($summary['percent']).'%' : '—' }}</div>
                    </div>
                    <div>
                        <div class="pr-muted">ĐƯỢC HƯỞNG</div>
                        <div class="pr-big">{{ $summary['rate'] !== null ? $num($summary['rate'] * 100).'%' : '—' }}</div>
                        <div class="pr-muted">lương KPI</div>
                    </div>
                    <div>
                        <span class="at-status-pill at-status-pill--{{ $locked ? 'success' : 'warning' }}">{{ $locked ? 'Đã chốt (bảng lương đã duyệt)' : 'Tạm tính — có thể còn thay đổi' }}</span>
                        <div class="pr-muted" style="margin-top:6px">{{ $template->name ?? ($isTechnical ? 'KPI Kỹ sư dân dụng' : '') }} · {{ $summary['label'] }}</div>
                    </div>
                </div>

                @if($isTechnical)
                    <p class="pr-hint">KPI kỹ sư tính từ số liệu công trình (tiến độ, nghiệm thu, vật tư, an toàn, EVN) và điểm cộng / trừ của Trưởng phòng Kỹ thuật.
                        @if(\Illuminate\Support\Facades\Route::has('ky-thuat.kpis.index'))
                            Xem chi tiết ở <a href="{{ route('ky-thuat.kpis.index', ['month' => $month]) }}">KPI kỹ thuật</a>.
                        @endif
                    </p>
                @endif
            @endif
        </section>

        @if($result)
            <section class="at-panel pr-section">
                <div class="at-table-wrap">
                    <table class="at-table" style="min-width:900px">
                        <thead>
                            <tr><th>Tiêu chí</th><th>Chỉ tiêu</th><th class="pr-num">Trọng số</th><th class="pr-num">Thực tế</th><th class="pr-num">% đạt</th><th class="pr-num">Điểm</th><th>Ghi chú của người chấm</th></tr>
                        </thead>
                        <tbody>
                            @foreach($result['rows'] as $row)
                                @php $c = $row['criterion']; $s = $row['score']; @endphp
                                <tr>
                                    <td><strong>{{ $c->name }}</strong>@if($c->group_name)<div class="pr-muted">{{ $c->group_name }}</div>@endif</td>
                                    <td>{{ $c->target_text ?: '—' }}</td>
                                    <td class="pr-num">{{ $c->weight !== null ? $num($c->weight) : '—' }}</td>
                                    <td class="pr-num">{{ $s && $s->actual_value !== null ? $num($s->actual_value).' '.($c->unit ?? '') : '—' }}</td>
                                    <td class="pr-num">
                                        @if($row['not_applicable']) <span class="pr-muted">Không phát sinh</span>
                                        @elseif($row['achievement'] !== null) {{ $num($row['achievement']) }}%
                                        @else <span class="pr-muted">Chưa chấm</span>
                                        @endif
                                    </td>
                                    <td class="pr-num">{{ $row['points'] !== null && ! $row['not_applicable'] ? $num($row['points']) : '—' }}</td>
                                    <td>{{ $s->note ?? '' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </section>
        @endif

        <section class="at-panel pr-section">
            <div class="at-card-head"><div><h2><i class="bi bi-clock-history"></i>6 tháng gần nhất</h2></div></div>
            <div class="at-table-wrap">
                <table class="at-table">
                    <thead><tr><th>Tháng</th><th class="pr-num">Điểm KPI</th><th class="pr-num">Được hưởng</th><th>Xếp loại</th><th>Trạng thái</th><th></th></tr></thead>
                    <tbody>
                        @foreach($history as $h)
                            <tr>
                                <td><strong>{{ \Illuminate\Support\Carbon::createFromFormat('Y-m', $h['month'])->format('m/Y') }}</strong></td>
                                @if(! $h['code'])
                                    <td colspan="3" class="pr-muted">Không áp dụng KPI</td>
                                @elseif(! $h['summary'])
                                    <td colspan="3" class="pr-muted">KPI kỹ sư — bấm Xem</td>
                                @else
                                    <td class="pr-num">{{ $h['summary']['percent'] !== null ? $num($h['summary']['percent']).'%' : '—' }}</td>
                                    <td class="pr-num">{{ $h['summary']['rate'] !== null ? $num($h['summary']['rate'] * 100).'%' : '—' }}</td>
                                    <td>{{ $h['summary']['label'] }}</td>
                                @endif
                                <td>{{ $h['locked'] ? 'Đã chốt' : 'Tạm tính' }}</td>
                                <td><a class="at-btn at-btn--light" style="min-height:28px" href="{{ route('hr.kpi-my', ['month' => $h['month']]) }}">Xem</a></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</div>
@endsection
