@extends('layouts.app')

@section('title', 'Chấm KPI nhân sự')

@section('content')
@php
    $num = static fn ($n) => rtrim(rtrim(number_format((float) $n, 2, ',', '.'), '0'), ',');
    $monthLabel = \Illuminate\Support\Carbon::createFromFormat('Y-m', $month)->format('m/Y');
    $weightTotal = $criteria->sum(fn ($c) => (float) $c->weight);
    $hasMissingWeight = $criteria->contains(fn ($c) => $c->weight === null);
@endphp

<div id="egoAttendancePromax">
    <div class="at-shell">
        <header class="at-hero at-panel">
            <div class="at-heading">
                <div class="at-heading__icon"><i class="bi bi-speedometer2"></i></div>
                <div>
                    <span>NHÂN SỰ · LƯƠNG & KPI</span>
                    <h1>Chấm KPI tháng {{ $monthLabel }}</h1>
                    <p>Nhập Thực tế (hệ thống tự quy ra % đạt theo chỉ tiêu) hoặc nhập thẳng % đạt. Tiêu chí tháng này không phát sinh thì tích "Không phát sinh".</p>
                </div>
            </div>
        </header>

        @include('hr.payroll._head')

        <section class="at-panel pr-body" style="margin-bottom:12px">
            <form method="GET" class="pr-filter">
                <div>
                    <label>Bộ KPI</label>
                    <select class="pr-input" name="template">
                        @foreach($templates as $t)
                            <option value="{{ $t->code }}" @selected($t->code === $template->code)>{{ $t->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div><label>Tháng</label><input class="pr-input" type="month" name="month" value="{{ $month }}"></div>
                <button class="at-btn at-btn--primary" type="submit"><i class="bi bi-funnel"></i>Xem</button>
            </form>
            <p class="pr-hint" style="margin:12px 0 0">
                <strong>{{ $template->name }}</strong> — {{ $template->position_label }}. Tổng trọng số: {{ $num($weightTotal) }} điểm.
                Quy đổi lương KPI:
                @foreach($template->payout_tiers as $tier)
                    {{ $tier['label'] ?? '' }}{{ ($tier['mode'] ?? '') === 'proportional' ? '' : ' → '.$num(($tier['rate'] ?? 0) * 100).'%' }}@if(! $loop->last); @endif
                @endforeach
                @if($template->max_payout_rate !== null) · Tối đa {{ $num($template->max_payout_rate * 100) }}% lương KPI. @endif
                @if($technicalCount > 0 && \Illuminate\Support\Facades\Route::has('ky-thuat.kpis.input'))
                    <br>{{ $technicalCount }} kỹ sư dùng KPI Kỹ thuật: chấm ở <a href="{{ route('ky-thuat.kpis.input', ['month' => $month]) }}">Nhập số liệu KPI kỹ thuật</a>.
                @endif
            </p>
            @if($hasMissingWeight)
                <div class="at-alert at-alert--danger" style="margin:12px 0 0"><i class="bi bi-exclamation-triangle"></i>Bộ KPI này còn tiêu chí chưa có trọng số nên chưa tính được % KPI. Ban Giám đốc nhập trọng số ở <a href="{{ route('hr.kpi.settings', ['template' => $template->code]) }}">Cài đặt KPI</a>.</div>
            @endif
        </section>

        @if($rows->isEmpty())
            <section class="at-panel pr-body">
                <p class="pr-hint">Chưa có nhân viên nào được gán bộ KPI này trong tháng {{ $monthLabel }}. Gán ở <a href="{{ route('hr.payroll.profiles', ['month' => $month]) }}">Hồ sơ lương</a> (cột Bộ KPI).</p>
            </section>
        @else
            <form method="POST" action="{{ route('hr.kpi.store') }}">
                @csrf
                <input type="hidden" name="month" value="{{ $month }}">
                <input type="hidden" name="template" value="{{ $template->code }}">

                @foreach($rows as $row)
                    @php
                        $employee = $row['employee'];
                        $summary = $row['summary'];
                        $isSelf = (int) $employee->id === (int) auth()->id();
                        $locked = ! $canEvaluate || $isSelf;
                    @endphp
                    <section class="at-panel pr-section">
                        <div class="at-card-head">
                            <div>
                                <h2><i class="bi bi-person"></i>{{ $employee->name }}</h2>
                                <p>
                                    @if($summary['percent'] !== null)
                                        KPI <strong>{{ $num($summary['percent']) }}%</strong> → hưởng <strong>{{ $num($summary['rate'] * 100) }}%</strong> lương KPI · {{ $summary['label'] }}
                                    @else
                                        {{ $summary['label'] }}@if($row['result']['missing']): {{ implode(', ', $row['result']['missing']) }}@endif
                                    @endif
                                    @if($isSelf) · <em>Bạn không thể tự chấm KPI của mình.</em>@endif
                                </p>
                            </div>
                            <span class="at-status-pill at-status-pill--{{ $summary['percent'] !== null ? 'success' : 'warning' }}">{{ $summary['percent'] !== null ? 'Đã đủ' : 'Chưa đủ' }}</span>
                        </div>
                        <div class="at-table-wrap">
                            <table class="at-table pr-table" style="min-width:1100px">
                                <thead>
                                    <tr>
                                        <th>Tiêu chí</th>
                                        <th>Chỉ tiêu</th>
                                        <th class="pr-num">Trọng số</th>
                                        <th>Thực tế</th>
                                        <th>% đạt</th>
                                        <th>Không phát sinh</th>
                                        <th>Ghi chú / minh chứng</th>
                                        <th class="pr-num">Điểm</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($row['result']['rows'] as $r)
                                        @php
                                            $c = $r['criterion'];
                                            $s = $r['score'];
                                            $base = "scores[{$employee->id}][{$c->id}]";
                                            $auto = $s && $s->achievement === null ? $r['achievement'] : null;
                                        @endphp
                                        <tr>
                                            <td style="max-width:320px;white-space:normal">
                                                @if($c->group_name)<div class="pr-muted">{{ $c->group_name }}</div>@endif
                                                <span class="pr-strong">{{ $c->name }}</span>
                                                @if($c->description)<div class="pr-muted">{{ $c->description }}</div>@endif
                                                @if(isset($autoData[$c->code]))<div class="pr-warn"><i class="bi bi-cpu"></i> Số liệu phần mềm: {{ $autoData[$c->code] }}</div>@endif
                                            </td>
                                            <td style="white-space:normal;max-width:180px">{{ $c->target_text }}<div class="pr-muted">{{ $calcTypes[$c->calc_type] ?? $c->calc_type }}</div></td>
                                            <td class="pr-num">{{ $c->weight !== null ? $num($c->weight) : '—' }}</td>
                                            <td>
                                                @if($c->calc_type !== 'manual')
                                                    <input class="pr-input pr-input--sm" type="number" step="any" min="0" name="{{ $base }}[actual]" value="{{ $s?->actual_value !== null ? (float) $s->actual_value : '' }}" @disabled($locked)>
                                                    <span class="pr-muted">{{ $c->unit }}</span>
                                                @else
                                                    <span class="pr-muted">—</span>
                                                @endif
                                            </td>
                                            <td>
                                                <input class="pr-input pr-input--xs" type="number" step="any" min="0" max="200" name="{{ $base }}[achievement]" value="{{ $s?->achievement !== null ? (float) $s->achievement : '' }}" placeholder="{{ $auto !== null ? $num($auto) : '' }}" @disabled($locked)> %
                                                @if($auto !== null)<div class="pr-muted">tự tính {{ $num($auto) }}%</div>@endif
                                            </td>
                                            <td style="text-align:center"><input type="checkbox" name="{{ $base }}[na]" value="1" @checked($s && $s->not_applicable) @disabled($locked)></td>
                                            <td><input class="pr-input" type="text" maxlength="1000" name="{{ $base }}[note]" value="{{ $s->note ?? '' }}" @disabled($locked)></td>
                                            <td class="pr-num">{{ $r['points'] !== null ? $num($r['points']) : '—' }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </section>
                @endforeach

                @if($canEvaluate)
                    <div class="pr-section"><button class="at-btn at-btn--primary" type="submit"><i class="bi bi-check2"></i>Lưu KPI tháng {{ $monthLabel }}</button></div>
                @elseif($monthLocked)
                    <p class="pr-hint pr-section">Bảng lương tháng {{ $monthLabel }} đã duyệt nên KPI tháng này đã khoá. Giám đốc mở khoá bảng lương (ghi lý do) nếu cần sửa.</p>
                @else
                    <p class="pr-hint pr-section">Chỉ Ban Giám đốc hoặc Admin được chấm KPI. Bạn đang xem ở chế độ chỉ đọc.</p>
                @endif
            </form>
        @endif
    </div>
</div>
@endsection
