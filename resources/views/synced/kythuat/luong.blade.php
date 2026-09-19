@extends('layouts.app')

@section('content')
@php
    $baseRate = (float)($settings['base_salary_rate'] ?? 0.70);
    $kpiRate = (float)($settings['kpi_salary_rate'] ?? 0.30);
    $kpiMaxRate = (float)($settings['kpi_max_rate'] ?? 1.30);
    $qualityPenalty = (float)($settings['quality_error_penalty'] ?? 0.05);
    $safetyPenalty = (float)($settings['safety_error_penalty'] ?? 0.05);
    $criteriaCount = count($kpis ?? []);
    $criteriaIcon = static function ($kpi, $idx) {
        return match ((string)($kpi['type'] ?? '')) {
            'material_waste' => 'bi-box-seam',
            'minus_safety' => 'bi-shield-check',
            'minus_quality' => 'bi-patch-check',
            default => ['bi-stopwatch','bi-gem','bi-clipboard2-check','bi-stars','bi-phone','bi-tools','bi-graph-up-arrow'][$idx % 7],
        };
    };
@endphp

<style>
    .kpiwork-page{
        --navy:#0b3558;
        --navy-2:#0f4774;
        --cyan:#0ea5c7;
        --bg:#f2f6fb;
        --panel:#fff;
        --line:#dfe9f3;
        --text:#10233a;
        --muted:#718198;
        --green:#15a56a;
        --amber:#f59e0b;
        --red:#e54b57;
        min-height:100vh;
        background:radial-gradient(circle at 88% 0%,rgba(14,165,199,.08),transparent 28%),var(--bg);
        color:var(--text);
        padding:20px 0 46px;
        font-size:12px;
    }
    .kpiwork-shell{width:min(1540px,calc(100% - 34px));margin:0 auto}
    .kpiwork-head{display:flex;justify-content:space-between;align-items:flex-start;gap:18px;margin-bottom:14px}
    .kpiwork-breadcrumb{display:flex;gap:7px;align-items:center;color:#6d7d92;font-size:10px;font-weight:800;margin-bottom:7px}
    .kpiwork-title-line{display:flex;align-items:center;gap:10px;flex-wrap:wrap}
    .kpiwork-title{margin:0;font-size:26px;font-weight:950;letter-spacing:-.035em;color:#11243b}
    .kpiwork-month-pill{height:28px;padding:0 10px;border-radius:9px;border:1px solid #d7e4ef;background:#fff;display:inline-flex;align-items:center;gap:6px;font-weight:850;color:#2a4764}
    .kpiwork-subtitle{margin:5px 0 0;color:var(--muted);font-size:11.5px}
    .kpiwork-actions{display:flex;gap:8px;flex-wrap:wrap;justify-content:flex-end}
    .kpiwork-btn{height:36px;border-radius:10px;border:1px solid #d7e4ef;background:#fff;color:#294763;padding:0 13px;display:inline-flex;align-items:center;justify-content:center;gap:7px;font-weight:900;text-decoration:none;transition:.18s ease;white-space:nowrap}
    .kpiwork-btn:hover{transform:translateY(-1px);box-shadow:0 8px 24px rgba(16,35,58,.08);color:#143a5e}
    .kpiwork-btn.primary{background:var(--navy);border-color:var(--navy);color:#fff;box-shadow:0 8px 20px rgba(11,53,88,.16)}
    .kpiwork-criteria{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:9px;margin-bottom:12px}
    .kpiwork-criterion{background:#fff;border:1px solid var(--line);border-radius:13px;padding:11px 12px;display:grid;grid-template-columns:34px minmax(0,1fr) auto;gap:9px;align-items:center;box-shadow:0 5px 16px rgba(16,35,58,.035)}
    .kpiwork-criterion-icon{width:34px;height:34px;border-radius:10px;background:#edf7ff;color:#2583c6;display:flex;align-items:center;justify-content:center;font-size:15px}
    .kpiwork-criterion strong{display:block;font-size:11.5px;line-height:1.25}
    .kpiwork-criterion small{display:block;color:#8492a5;font-size:9.5px;line-height:1.3;margin-top:2px}
    .kpiwork-weight{background:var(--navy);color:#fff;border-radius:9px;padding:5px 7px;font-size:10px;font-weight:950}
    .kpiwork-grid{display:grid;grid-template-columns:330px minmax(0,1fr);gap:12px;align-items:start}
    .kpiwork-panel{background:#fff;border:1px solid var(--line);border-radius:15px;box-shadow:0 8px 26px rgba(16,35,58,.045);overflow:hidden}
    .kpiwork-panel + .kpiwork-panel{margin-top:12px}
    .kpiwork-panel-head{padding:12px 14px;border-bottom:1px solid var(--line);display:flex;justify-content:space-between;align-items:flex-start;gap:10px}
    .kpiwork-panel-head h3{margin:0;font-size:13.5px;font-weight:950}
    .kpiwork-panel-head p{margin:3px 0 0;color:var(--muted);font-size:10px}
    .kpiwork-panel-body{padding:13px 14px}
    .kpiwork-field{margin-bottom:11px}
    .kpiwork-field:last-child{margin-bottom:0}
    .kpiwork-field label{display:block;font-size:9.5px;font-weight:900;color:#62748b;text-transform:uppercase;letter-spacing:.04em;margin-bottom:5px}
    .kpiwork-control{width:100%;height:38px;border:1px solid #d8e4ef;border-radius:10px;background:#fbfdff;padding:0 11px;color:#18334e;font-weight:750;outline:none;transition:.18s ease}
    textarea.kpiwork-control{height:76px;padding-top:9px;resize:vertical}
    .kpiwork-control:focus{border-color:#7dc7dc;box-shadow:0 0 0 3px rgba(14,165,199,.08);background:#fff}
    .kpiwork-note{font-size:9.5px;color:#8190a3;line-height:1.4;margin-top:4px}
    .kpiwork-summary{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:9px;margin-bottom:12px}
    .kpiwork-stat{background:#fff;border:1px solid var(--line);border-radius:13px;padding:12px 13px;min-height:86px;position:relative;overflow:hidden}
    .kpiwork-stat:after{content:"";position:absolute;right:-18px;top:-30px;width:78px;height:78px;border-radius:999px;background:rgba(28,120,179,.055)}
    .kpiwork-stat span{display:block;color:#73839a;font-size:9.5px;font-weight:900;text-transform:uppercase;letter-spacing:.035em}
    .kpiwork-stat strong{display:block;margin-top:7px;font-size:23px;line-height:1;font-weight:950;color:#102b47}
    .kpiwork-stat small{display:block;margin-top:5px;color:#8594a6;font-size:9px}
    .kpiwork-stat.total strong{color:#0b7e9e}
    .kpiwork-kpi-list{display:grid;gap:9px}
    .kpiwork-kpi-card{border:1px solid var(--line);border-radius:13px;background:#fff;overflow:hidden}
    .kpiwork-kpi-top{display:grid;grid-template-columns:38px minmax(0,1fr) 66px 82px;gap:10px;align-items:center;padding:11px 12px;background:#fcfdff;border-bottom:1px solid #edf2f7}
    .kpiwork-kpi-icon{width:36px;height:36px;border-radius:11px;background:#eef7ff;color:#2583c6;display:flex;align-items:center;justify-content:center;font-size:16px}
    .kpiwork-kpi-name strong{display:block;font-size:12px;font-weight:950}
    .kpiwork-kpi-name small{display:block;color:#7c8ca1;font-size:9.5px;margin-top:2px}
    .kpiwork-kpi-rate{text-align:right}
    .kpiwork-kpi-rate span{display:block;color:#7b8ba0;font-size:9px}
    .kpiwork-kpi-rate strong{font-size:16px;color:#173c5d}
    .kpiwork-score-chip{height:30px;border-radius:9px;background:#eaf8f1;color:#17855a;display:flex;align-items:center;justify-content:center;font-weight:950;font-size:10px}
    .kpiwork-kpi-body{display:grid;grid-template-columns:1fr 1fr 1.2fr;gap:10px;padding:10px 12px}
    .kpiwork-mini label{display:block;color:#73839a;font-size:9px;font-weight:850;margin-bottom:4px}
    .kpiwork-mini input{height:34px;border-radius:9px;border:1px solid #dbe6ef;background:#fbfdff;width:100%;padding:0 9px;font-weight:850;color:#173b5c;outline:none}
    .kpiwork-mini input:focus{border-color:#7dc7dc;box-shadow:0 0 0 3px rgba(14,165,199,.07)}
    .kpiwork-rule{border-radius:10px;background:#f7fafc;border:1px solid #e6edf4;padding:8px 9px;color:#6d7e93;font-size:9.5px;line-height:1.45}
    .kpiwork-rule b{color:#38526c}
    .kpiwork-savebar{position:sticky;bottom:10px;z-index:15;margin-top:12px;background:rgba(255,255,255,.96);backdrop-filter:blur(8px);border:1px solid #dce8f2;border-radius:14px;padding:9px 10px;box-shadow:0 14px 35px rgba(16,35,58,.11);display:flex;align-items:center;justify-content:space-between;gap:12px}
    .kpiwork-saveinfo{display:flex;align-items:center;gap:18px;flex-wrap:wrap}
    .kpiwork-saveinfo div span{display:block;color:#7b8ba0;font-size:9px;font-weight:800;text-transform:uppercase}
    .kpiwork-saveinfo div strong{display:block;font-size:15px;margin-top:1px;color:#153955}
    .kpiwork-save{height:40px;border:0;border-radius:10px;background:var(--navy);color:#fff;padding:0 18px;font-weight:950;display:inline-flex;align-items:center;gap:7px;box-shadow:0 8px 20px rgba(11,53,88,.18)}
    .kpiwork-history{margin-top:12px}
    .kpiwork-table-wrap{overflow:auto}
    .kpiwork-table{width:100%;border-collapse:separate;border-spacing:0;min-width:900px}
    .kpiwork-table th{background:#f7f9fc;border-bottom:1px solid var(--line);padding:9px 10px;color:#60738b;font-size:9px;text-transform:uppercase;letter-spacing:.035em;text-align:left;white-space:nowrap}
    .kpiwork-table td{padding:9px 10px;border-bottom:1px solid #edf2f7;font-size:10.5px;vertical-align:middle}
    .kpiwork-table tr:last-child td{border-bottom:0}
    .kpiwork-person{font-weight:900;color:#173b5a}
    .kpiwork-status{display:inline-flex;align-items:center;gap:5px;border-radius:999px;padding:5px 8px;font-size:9px;font-weight:900;background:#fff4d9;color:#a66b00}
    .kpiwork-status.approved{background:#e8f7ef;color:#147d54}
    .kpiwork-empty{text-align:center;padding:26px;color:#7e8ea3}
    .kpiwork-alert{padding:9px 12px;border-radius:11px;margin-bottom:10px;font-weight:750;border:1px solid}
    .kpiwork-alert.success{background:#edf9f3;color:#16734f;border-color:#cfefdf}
    .kpiwork-alert.danger{background:#fff0f1;color:#b43d48;border-color:#f5d4d8}
    @media(max-width:1180px){.kpiwork-criteria{grid-template-columns:repeat(3,1fr)}.kpiwork-grid{grid-template-columns:1fr}.kpiwork-summary{grid-template-columns:repeat(2,1fr)}}
    @media(max-width:760px){.kpiwork-shell{width:min(100% - 20px,1540px)}.kpiwork-head{flex-direction:column}.kpiwork-actions{justify-content:flex-start}.kpiwork-criteria,.kpiwork-summary{grid-template-columns:1fr}.kpiwork-kpi-top{grid-template-columns:36px 1fr 58px}.kpiwork-score-chip{grid-column:2/4;justify-self:start;padding:0 10px}.kpiwork-kpi-body{grid-template-columns:1fr 1fr}.kpiwork-rule{grid-column:1/-1}.kpiwork-savebar{align-items:flex-end;flex-direction:column}.kpiwork-save{width:100%;justify-content:center}}
</style>

<div class="kpiwork-page">
    <div class="kpiwork-shell">
        <header class="kpiwork-head">
            <div>
                <div class="kpiwork-breadcrumb"><span>Kỹ thuật</span><i class="bi bi-chevron-right"></i><span>KPIs</span><i class="bi bi-chevron-right"></i><span>Chấm KPI</span></div>
                <div class="kpiwork-title-line">
                    <h1 class="kpiwork-title">Chấm / cập nhật KPI kỹ thuật</h1>
                    <span class="kpiwork-month-pill"><i class="bi bi-calendar3"></i><span id="headerMonthLabel">Tháng {{ now()->format('m/Y') }}</span></span>
                </div>
                <p class="kpiwork-subtitle">Đang chấm theo {{ $criteriaCount }} tiêu chí được cấu hình. Thêm/sửa/xóa tiêu chí ở Cấu hình KPI sẽ tự đồng bộ sang màn hình này.</p>
            </div>
            <div class="kpiwork-actions">
                <a href="{{ route('ky-thuat.kpis.index') }}" class="kpiwork-btn"><i class="bi bi-arrow-left"></i>Về KPIs</a>
                @hasanyrole('admin|accounting|manager')
                    <a href="{{ route('ky-thuat.luong.settings') }}" class="kpiwork-btn"><i class="bi bi-sliders"></i>Cấu hình KPI</a>
                @endhasanyrole
            </div>
        </header>

        @if(session('success'))<div class="kpiwork-alert success"><i class="bi bi-check-circle me-1"></i>{{ session('success') }}</div>@endif
        @if(session('error'))<div class="kpiwork-alert danger"><i class="bi bi-exclamation-triangle me-1"></i>{{ session('error') }}</div>@endif
        @if($errors->any())
            <div class="kpiwork-alert danger">
                <strong>Chưa thể lưu:</strong> {{ implode(' · ', $errors->all()) }}
            </div>
        @endif

        <section class="kpiwork-criteria">
            @foreach($kpis as $i => $kpi)
                <article class="kpiwork-criterion">
                    <span class="kpiwork-criterion-icon"><i class="bi {{ $criteriaIcon($kpi, $loop->index) }}"></i></span>
                    <div><strong>{{ $kpi['name'] }}</strong><small>{{ $kpi['rule'] }}</small></div>
                    <span class="kpiwork-weight">{{ number_format($kpi['weight'] * 100, 0) }}%</span>
                </article>
            @endforeach
        </section>

        @if(isset($kpiConfigValid) && !$kpiConfigValid)
            <div class="kpiwork-alert danger">
                <i class="bi bi-exclamation-triangle-fill me-1"></i>
                Tổng trọng số KPI hiện tại là <strong>{{ number_format(($kpiConfigWeight ?? 0) * 100, 2, ',', '.') }}%</strong>. Vui lòng chỉnh về đúng 100% trong Cấu hình KPI trước khi chấm.
            </div>
        @endif

        <form method="POST" action="{{ route('ky-thuat.luong.store') }}" id="payrollForm">
            @csrf
            <input type="hidden" name="month_label" id="monthLabel">
            <input type="hidden" name="feedback_bad_count" value="0">
            <input type="hidden" name="feedback_neutral_count" value="0">
            <input type="hidden" name="feedback_good_count" value="0">

            <div class="kpiwork-grid">
                <aside>
                    <section class="kpiwork-panel">
                        <div class="kpiwork-panel-head"><div><h3>Thông tin kỳ KPI</h3><p>Chọn kỹ sư và tháng cần chấm.</p></div><i class="bi bi-person-badge text-primary"></i></div>
                        <div class="kpiwork-panel-body">
                            <div class="kpiwork-field">
                                <label>Kỹ sư</label>
                                <select name="user_id" id="userId" class="kpiwork-control" required>
                                    <option value="">Chọn kỹ sư...</option>
                                    @foreach($employees ?? [] as $employee)
                                        <option value="{{ $employee->id }}" data-position="{{ $employee->position_name }}">{{ $employee->name }} — {{ $employee->position_name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="kpiwork-field">
                                <label>Chức vụ</label>
                                <input type="text" id="positionName" class="kpiwork-control" readonly placeholder="Tự động theo nhân sự">
                            </div>
                            <div class="kpiwork-field">
                                <label>Tháng KPI</label>
                                <input type="month" name="payroll_month" id="payrollMonth" class="kpiwork-control" value="{{ now()->format('Y-m') }}" required>
                            </div>
                            <div class="kpiwork-field">
                                <label>Tổng lương bậc Gross</label>
                                <input type="number" name="gross_salary" id="grossSalary" class="kpiwork-control" value="15000000" min="0" step="1000" required>
                                <div class="kpiwork-note">Hệ thống đang dùng {{ number_format($baseRate*100,0) }}% cố định + {{ number_format($kpiRate*100,0) }}% quỹ KPI.</div>
                            </div>
                            <div class="kpiwork-field">
                                <label>Điểm phạt HSE nghiêm trọng</label>
                                <select name="penalty_points" id="penaltyPoints" class="kpiwork-control">
                                    <option value="0">Không phạt</option>
                                    <option value="10">Trừ 10 điểm KPI</option>
                                    <option value="20">Trừ 20 điểm KPI</option>
                                </select>
                                <div class="kpiwork-note">Dùng cho vi phạm đã được quản lý xác nhận, ví dụ không đeo dây an toàn khi làm mái.</div>
                            </div>
                            <div class="kpiwork-field">
                                <label>Ghi chú</label>
                                <textarea name="note" class="kpiwork-control" placeholder="Ghi rõ lý do điều chỉnh, vi phạm hoặc thông tin cần lưu..."></textarea>
                            </div>
                        </div>
                    </section>
                </aside>

                <main>
                    <section class="kpiwork-summary">
                        <article class="kpiwork-stat total"><span>KPI tạm tính</span><strong id="totalKpiBox">100,0%</strong><small>Sau khi trừ điểm phạt</small></article>
                        <article class="kpiwork-stat"><span>Lương cố định</span><strong id="baseSalaryBox">0 đ</strong><small>{{ number_format($baseRate*100,0) }}% Gross</small></article>
                        <article class="kpiwork-stat"><span>Quỹ KPI</span><strong id="kpiBaseSalaryBox">0 đ</strong><small>{{ number_format($kpiRate*100,0) }}% Gross</small></article>
                        <article class="kpiwork-stat"><span>Thực lãnh dự kiến</span><strong id="totalIncomeBox">0 đ</strong><small>Tính theo KPI hiện tại</small></article>
                    </section>

                    <section class="kpiwork-kpi-list">
                        @foreach($kpis as $i => $kpi)
                            @php
                                $isMaterial = $kpi['type'] === 'material_waste';
                                $isErrorBased = in_array($kpi['type'], ['minus_quality','minus_safety'], true);
                                $defaultPlan = $kpi['default_plan'] ?? 1;
                                $defaultActual = $kpi['default_actual'] ?? 1;
                            @endphp
                            <article class="kpiwork-kpi-card kpi-row" data-type="{{ $kpi['type'] }}" data-weight="{{ $kpi['weight'] }}" data-index="{{ $i }}">
                                <div class="kpiwork-kpi-top">
                                    <span class="kpiwork-kpi-icon"><i class="bi {{ $criteriaIcon($kpi, $loop->index) }}"></i></span>
                                    <div class="kpiwork-kpi-name"><strong>{{ $kpi['subject'] ?? $kpi['name'] }}</strong><small>{{ $kpi['name'] }}</small></div>
                                    <div class="kpiwork-kpi-rate"><span>Trọng số</span><strong>{{ number_format($kpi['weight']*100,0) }}%</strong></div>
                                    <span class="kpiwork-score-chip"><span class="row-rate">100,0%</span></span>
                                </div>
                                <div class="kpiwork-kpi-body">
                                    <input type="hidden" name="kpis[{{ $i }}][definition_id]" value="{{ $kpi['definition_id'] ?? '' }}">
                                    <input type="hidden" name="kpis[{{ $i }}][name]" value="{{ $kpi['name'] }}">
                                    <input type="hidden" name="kpis[{{ $i }}][unit]" value="{{ $kpi['unit'] }}">
                                    <input type="hidden" name="kpis[{{ $i }}][weight]" value="{{ $kpi['weight'] }}">
                                    <input type="hidden" name="kpis[{{ $i }}][type]" value="{{ $kpi['type'] }}">
                                    @if($isMaterial)
                                        <input type="hidden" name="kpis[{{ $i }}][plan]" class="kpi-plan" value="0">
                                        <div class="kpiwork-mini"><label>Hao hụt vật tư thực tế (%)</label><input type="number" step="0.01" min="0" name="kpis[{{ $i }}][actual]" class="kpi-actual" value="{{ $defaultActual }}"></div>
                                        <div class="kpiwork-mini"><label>Cách quy đổi</label><input type="text" value="0%=120 · ≤2%=100 · ≤4%=85 · ≤6%=70" readonly></div>
                                    @elseif($isErrorBased)
                                        <input type="hidden" name="kpis[{{ $i }}][plan]" class="kpi-plan" value="{{ $defaultPlan }}">
                                        <div class="kpiwork-mini"><label>Số lỗi / sự cố thực tế</label><input type="number" step="1" min="0" name="kpis[{{ $i }}][actual]" class="kpi-actual" value="{{ $defaultActual }}"></div>
                                        <div class="kpiwork-mini"><label>Cách tính</label><input type="text" value="100% trừ theo số lỗi/sự cố" readonly></div>
                                    @else
                                        <div class="kpiwork-mini"><label>KH / Tổng phải đạt</label><input type="number" step="0.01" min="0" name="kpis[{{ $i }}][plan]" class="kpi-plan" value="{{ $defaultPlan }}"></div>
                                        <div class="kpiwork-mini"><label>TH / Số đã đạt</label><input type="number" step="0.01" min="0" name="kpis[{{ $i }}][actual]" class="kpi-actual" value="{{ $defaultActual }}"></div>
                                    @endif
                                    <div class="kpiwork-rule"><b>Quy tắc:</b> {{ $kpi['rule'] }}<br><b>Nguồn:</b> {{ $kpi['source'] }}</div>
                                </div>
                            </article>
                        @endforeach
                    </section>

                    <div class="kpiwork-savebar">
                        <div class="kpiwork-saveinfo">
                            <div><span>KPI tổng</span><strong id="stickyKpi">100,0%</strong></div>
                            <div><span>Điểm phạt</span><strong id="stickyPenalty">0 điểm</strong></div>
                            <div><span>Thực lãnh</span><strong id="stickyIncome">0 đ</strong></div>
                        </div>
                        <button type="submit" class="kpiwork-save" @disabled(isset($kpiConfigValid) && !$kpiConfigValid)><i class="bi bi-check2-circle"></i>Lưu KPI kỹ sư</button>
                    </div>
                </main>
            </div>
        </form>

        <section class="kpiwork-panel kpiwork-history" id="historyPayroll">
            <div class="kpiwork-panel-head"><div><h3>Lịch sử chấm KPI gần nhất</h3><p>Xem, sửa hoặc đối chiếu lại các kỳ KPI đã lưu.</p></div><span class="kpiwork-month-pill">{{ count($payrolls ?? []) }} bản ghi</span></div>
            <div class="kpiwork-table-wrap">
                <table class="kpiwork-table">
                    <thead><tr><th>Kỹ sư</th><th>Tháng</th><th>KPI tổng</th><th>Gross</th><th>Thực lãnh</th><th>Trạng thái</th><th style="text-align:right">Thao tác</th></tr></thead>
                    <tbody>
                    @forelse($payrolls ?? [] as $row)
                        @php $approved = ($row->status ?? 'draft') === 'approved'; @endphp
                        <tr>
                            <td><span class="kpiwork-person">{{ $row->employee_name ?? '—' }}</span><div class="kpiwork-note">{{ $row->position_name ?? 'Kỹ thuật' }}</div></td>
                            <td>{{ $row->month_label ?? $row->payroll_month ?? '—' }}</td>
                            <td><strong>{{ number_format(((float)($row->total_kpi_percent ?? 0))*100,1,',','.') }}%</strong></td>
                            <td>{{ number_format((float)($row->gross_salary ?? 0),0,',','.') }} đ</td>
                            <td><strong>{{ number_format((float)($row->total_income ?? 0),0,',','.') }} đ</strong></td>
                            <td><span class="kpiwork-status {{ $approved ? 'approved' : '' }}"><i class="bi {{ $approved ? 'bi-check-circle' : 'bi-hourglass-split' }}"></i>{{ $approved ? 'Đã duyệt' : 'Chờ duyệt' }}</span></td>
                            <td style="text-align:right;white-space:nowrap">
                                <a href="{{ route('ky-thuat.luong.show',$row->id) }}" class="kpiwork-btn" style="height:30px;padding:0 9px">Xem</a>
                                @if(!$approved)<a href="{{ route('ky-thuat.luong.edit',$row->id) }}" class="kpiwork-btn" style="height:30px;padding:0 9px">Sửa</a>@endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="kpiwork-empty">Chưa có dữ liệu KPI. Chọn kỹ sư và nhập các tiêu chí phía trên để tạo kỳ đầu tiên.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</div>

<script>
(function(){
    const baseRate = {{ json_encode($baseRate) }};
    const kpiRate = {{ json_encode($kpiRate) }};
    const maxRate = {{ json_encode($kpiMaxRate) }};
    const qualityPenalty = {{ json_encode($qualityPenalty) }};
    const safetyPenalty = {{ json_encode($safetyPenalty) }};
    const money = new Intl.NumberFormat('vi-VN');
    const form = document.getElementById('payrollForm');
    const month = document.getElementById('payrollMonth');
    const user = document.getElementById('userId');
    const gross = document.getElementById('grossSalary');
    const penalty = document.getElementById('penaltyPoints');

    function pct(n){ return (n*100).toLocaleString('vi-VN',{minimumFractionDigits:1,maximumFractionDigits:1})+'%'; }
    function materialRate(waste){ if(waste<=0)return 1.2;if(waste<=2)return 1;if(waste<=4)return .85;if(waste<=6)return .70;return 0; }
    function rowRate(row){
        const type=row.dataset.type;
        const plan=parseFloat(row.querySelector('.kpi-plan')?.value||0);
        const actual=parseFloat(row.querySelector('.kpi-actual')?.value||0);
        if(type==='material_waste') return materialRate(actual);
        if(type==='actual_div_plan') return plan>0 ? Math.min(1,actual/plan) : 0;
        if(type==='plan_div_actual') return actual>0 ? plan/actual : 0;
        if(type==='minus_quality') return Math.max(0, 1 - (qualityPenalty * actual));
        if(type==='minus_safety') return Math.max(0, 1 - (safetyPenalty * actual));
        return 0;
    }
    function recalc(){
        let score=0,weight=0;
        document.querySelectorAll('.kpi-row').forEach(row=>{
            const w=parseFloat(row.dataset.weight||0);
            const r=rowRate(row);
            score+=r*w;weight+=w;
            const box=row.querySelector('.row-rate'); if(box) box.textContent=pct(r);
        });
        let total=weight>0?score/weight:0;
        total=Math.min(maxRate,total);
        const penaltyValue=parseFloat(penalty?.value||0);
        total=Math.max(0,total-(penaltyValue/100));
        const grossValue=parseFloat(gross?.value||0);
        const baseSalary=grossValue*baseRate;
        const kpiBase=grossValue*kpiRate;
        const income=baseSalary+(kpiBase*total);
        document.getElementById('totalKpiBox').textContent=pct(total);
        document.getElementById('baseSalaryBox').textContent=money.format(Math.round(baseSalary))+' đ';
        document.getElementById('kpiBaseSalaryBox').textContent=money.format(Math.round(kpiBase))+' đ';
        document.getElementById('totalIncomeBox').textContent=money.format(Math.round(income))+' đ';
        document.getElementById('stickyKpi').textContent=pct(total);
        document.getElementById('stickyPenalty').textContent=penaltyValue.toLocaleString('vi-VN')+' điểm';
        document.getElementById('stickyIncome').textContent=money.format(Math.round(income))+' đ';
    }
    function updateMonth(){
        const value=month?.value||''; if(!value)return;
        const parts=value.split('-');
        const label='Tháng '+parseInt(parts[1],10)+'/'+parts[0];
        document.getElementById('monthLabel').value=label;
        document.getElementById('headerMonthLabel').textContent=label;
    }
    function updatePosition(){
        const opt=user?.options[user.selectedIndex];
        document.getElementById('positionName').value=opt?.dataset?.position||'';
    }
    form?.addEventListener('input',recalc);
    form?.addEventListener('change',recalc);
    month?.addEventListener('change',updateMonth);
    user?.addEventListener('change',updatePosition);
    updateMonth();updatePosition();recalc();
})();
</script>
@endsection
