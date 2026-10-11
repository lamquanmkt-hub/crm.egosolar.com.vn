@extends('layouts.app')

@section('title', 'Cài đặt KPI nhân sự')

@section('content')
@php
    $num = static fn ($n) => rtrim(rtrim(number_format((float) $n, 2, '.', ''), '0'), '.');
    $tiers = array_values((array) $template->payout_tiers);
    $tiers = array_merge($tiers, array_fill(0, 2, ['min' => null, 'mode' => 'fixed', 'rate' => null, 'label' => '']));
    $weightTotal = $criteria->where('is_active', true)->sum(fn ($c) => (float) $c->weight);
@endphp

<div id="egoAttendancePromax">
    <div class="at-shell">
        <header class="at-hero at-panel">
            <div class="at-heading">
                <div class="at-heading__icon"><i class="bi bi-sliders"></i></div>
                <div>
                    <span>NHÂN SỰ · LƯƠNG & KPI</span>
                    <h1>Cài đặt {{ $template->name }}</h1>
                    <p>Tiêu chí, trọng số, chỉ tiêu và bậc quy đổi % KPI ra % lương KPI được hưởng. Chỉ Ban Giám đốc / Admin được sửa.</p>
                </div>
            </div>
        </header>

        @include('hr.payroll._head')

        <section class="at-panel pr-body" style="margin-bottom:12px">
            <form method="GET" class="pr-filter">
                <div>
                    <label>Bộ KPI</label>
                    <select class="pr-input" name="template" onchange="this.form.submit()">
                        @foreach($templates as $t)
                            <option value="{{ $t->code }}" @selected($t->code === $template->code)>{{ $t->name }}</option>
                        @endforeach
                    </select>
                </div>
            </form>
            @if($template->description)<p class="pr-hint" style="margin:12px 0 0">{{ $template->description }}</p>@endif
        </section>

        <form method="POST" action="{{ route('hr.kpi.settings.save') }}">
            @csrf
            <input type="hidden" name="template" value="{{ $template->code }}">
            <fieldset @disabled(! $canEdit) style="border:0;padding:0;margin:0">

            <section class="at-panel">
                <div class="at-card-head">
                    <div>
                        <h2><i class="bi bi-list-ol"></i>Tiêu chí ({{ $criteria->count() }}) — tổng trọng số đang dùng: {{ $num($weightTotal) }}</h2>
                        <p>% KPI = Σ(trọng số × % đạt) ÷ Σ trọng số các tiêu chí có phát sinh. Trọng số không bắt buộc cộng đủ 100. Tiêu chí còn trống trọng số => chưa tính được KPI.</p>
                    </div>
                </div>
                <div class="at-table-wrap">
                    <table class="at-table pr-table" style="min-width:1200px">
                        <thead>
                            <tr><th>Nhóm</th><th>Tên tiêu chí</th><th>Chỉ tiêu (mô tả)</th><th>Trọng số</th><th>Cách tính % đạt</th><th>Chỉ tiêu (số)</th><th>Đơn vị</th><th>Dùng</th></tr>
                        </thead>
                        <tbody>
                            @foreach($criteria as $c)
                                @php $base = "criteria[{$c->id}]"; @endphp
                                <tr>
                                    <td><input class="pr-input" style="min-width:150px" type="text" name="{{ $base }}[group_name]" value="{{ old("criteria.{$c->id}.group_name", $c->group_name) }}"></td>
                                    <td><input class="pr-input" style="min-width:260px" type="text" name="{{ $base }}[name]" value="{{ old("criteria.{$c->id}.name", $c->name) }}" required></td>
                                    <td><input class="pr-input" style="min-width:170px" type="text" name="{{ $base }}[target_text]" value="{{ old("criteria.{$c->id}.target_text", $c->target_text) }}"></td>
                                    <td><input class="pr-input pr-input--xs" type="number" step="any" min="0" max="100" name="{{ $base }}[weight]" value="{{ old("criteria.{$c->id}.weight", $c->weight !== null ? $num($c->weight) : '') }}"></td>
                                    <td>
                                        <select class="pr-input" name="{{ $base }}[calc_type]">
                                            @foreach($calcTypes as $value => $label)
                                                <option value="{{ $value }}" @selected($c->calc_type === $value)>{{ $label }}</option>
                                            @endforeach
                                        </select>
                                    </td>
                                    <td><input class="pr-input pr-input--sm" type="number" step="any" min="0" name="{{ $base }}[target_value]" value="{{ old("criteria.{$c->id}.target_value", $c->target_value !== null ? $num($c->target_value) : '') }}"></td>
                                    <td><input class="pr-input pr-input--xs" type="text" name="{{ $base }}[unit]" value="{{ $c->unit }}"></td>
                                    <td style="text-align:center"><input type="checkbox" name="{{ $base }}[is_active]" value="1" @checked($c->is_active)></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </section>

            <section class="at-panel pr-section">
                <div class="at-card-head">
                    <div>
                        <h2><i class="bi bi-bar-chart-steps"></i>Quy đổi % KPI → % lương KPI được hưởng</h2>
                        <p>Xét từ ngưỡng cao xuống: bậc đầu tiên có % KPI ≥ ngưỡng được áp dụng. "Theo % KPI" = hưởng đúng bằng % KPI đạt × hệ số. Để trống ngưỡng để xoá bậc.</p>
                    </div>
                </div>
                <div class="at-table-wrap">
                    <table class="at-table pr-table" style="min-width:800px">
                        <thead><tr><th>Từ % KPI ≥</th><th>Cách hưởng</th><th>% lương KPI (cố định) / hệ số (theo % KPI)</th><th>Diễn giải</th></tr></thead>
                        <tbody>
                            @foreach($tiers as $i => $tier)
                                <tr>
                                    <td><input class="pr-input pr-input--sm" type="number" step="any" min="0" max="200" name="tiers[{{ $i }}][min]" value="{{ $tier['min'] !== null ? $num($tier['min']) : '' }}"></td>
                                    <td>
                                        <select class="pr-input" name="tiers[{{ $i }}][mode]">
                                            <option value="fixed" @selected(($tier['mode'] ?? 'fixed') === 'fixed')>Cố định</option>
                                            <option value="proportional" @selected(($tier['mode'] ?? '') === 'proportional')>Theo % KPI</option>
                                        </select>
                                    </td>
                                    <td><input class="pr-input pr-input--sm" type="number" step="any" min="0" max="500" name="tiers[{{ $i }}][rate]" value="{{ $tier['rate'] !== null ? $num($tier['rate'] * 100) : '' }}"> %</td>
                                    <td><input class="pr-input" style="min-width:300px" type="text" maxlength="190" name="tiers[{{ $i }}][label]" value="{{ $tier['label'] ?? '' }}"></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="pr-body pr-grid">
                    <div class="pr-field"><label>Trần lương KPI được hưởng (%) — trống = không giới hạn</label><input class="pr-input" type="number" step="any" min="0" max="500" name="max_payout_rate" value="{{ $template->max_payout_rate !== null ? $num($template->max_payout_rate * 100) : '' }}"></div>
                    <div class="pr-field"><label>Trần % đạt của một tiêu chí</label><input class="pr-input" type="number" step="any" min="100" max="200" name="max_achievement" value="{{ $num($template->max_achievement) }}" required></div>
                </div>
            </section>

            @if($canEdit)
                <div class="pr-section"><button class="at-btn at-btn--primary" type="submit"><i class="bi bi-check2"></i>Lưu cài đặt KPI</button></div>
            @else
                <p class="pr-hint pr-section">Chỉ Ban Giám đốc hoặc Admin được sửa cài đặt KPI.</p>
            @endif
            </fieldset>
        </form>
    </div>
</div>
@endsection
