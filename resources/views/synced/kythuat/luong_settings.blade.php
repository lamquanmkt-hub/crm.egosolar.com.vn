@extends('layouts.app')

@section('content')
@php
    $allSettings = collect($settings ?? []);
    $settingMap = $allSettings->keyBy('setting_key');
    $baseSetting = $settingMap->get('base_salary_rate');
    $kpiSalarySetting = $settingMap->get('kpi_salary_rate');
    $maxSetting = $settingMap->get('kpi_max_rate');

    $defaultCriteria = collect([
        ['name'=>'Tiến độ hoàn thành lắp đặt hệ thống','unit'=>'Công trình','plan_value'=>1,'actual_value'=>1,'weight'=>0.30,'calc_type'=>'actual_div_plan','note'=>'TH / KH. TH = công trình hoàn thành đúng hạn; KH = tổng công trình đến hạn trong kỳ.','sort_order'=>1],
        ['name'=>'Chất lượng thi công & thẩm mỹ','unit'=>'Công trình','plan_value'=>1,'actual_value'=>1,'weight'=>0.25,'calc_type'=>'actual_div_plan','note'=>'TH / KH. TH = công trình nghiệm thu đạt ngay lần đầu; KH = tổng công trình nghiệm thu.','sort_order'=>2],
        ['name'=>'Khảo sát kỹ thuật & khối lượng','unit'=>'% hao hụt','plan_value'=>0,'actual_value'=>0,'weight'=>0.15,'calc_type'=>'material_waste','note'=>'0%=120%; >0–2%=100%; >2–4%=85%; >4–6%=70%; >6%=0%.','sort_order'=>3],
        ['name'=>'An toàn lao động (HSE) & vệ sinh','unit'=>'Công trình','plan_value'=>1,'actual_value'=>1,'weight'=>0.15,'calc_type'=>'actual_div_plan','note'=>'TH / KH. Vi phạm HSE nghiêm trọng có thể trừ thêm 10–20 điểm KPI.','sort_order'=>4],
        ['name'=>'Hỗ trợ thủ tục EVN & cài đặt App','unit'=>'Công trình','plan_value'=>1,'actual_value'=>1,'weight'=>0.15,'calc_type'=>'actual_div_plan','note'=>'TH / KH. Chỉ tính các công trình có yêu cầu EVN/App trong kỳ.','sort_order'=>5],
    ]);

    $allKpiRows = collect();
    if (\App\Support\SchemaCache::hasTable('technical_payroll_kpi_items')) {
        $allKpiRows = \Illuminate\Support\Facades\DB::table('technical_payroll_kpi_items')
            ->orderBy('sort_order')->orderBy('id')->get();
    }
    $enabledRows = $allKpiRows->where('is_enabled', 1)->values();
    $useDbCriteria = $enabledRows->isNotEmpty();
    $criteriaRows = $useDbCriteria ? $enabledRows : $defaultCriteria;
    $legacyRows = $allKpiRows->where('is_enabled', 0)->values();
    $criteriaIcons = [1=>'bi-stopwatch',2=>'bi-gem',3=>'bi-box-seam',4=>'bi-shield-check',5=>'bi-phone'];
    $canManageKpi = auth()->check() && auth()->user()->hasRole('admin');

    $projectSourceOptions = \App\Services\TechnicalKpi\ProjectKpiLinkService::sourceOptions();
@endphp

<style>
    .kpiconfig-page{
        --navy:#0b3558;--cyan:#0ea5c7;--bg:#f2f6fb;--panel:#fff;--line:#dfe9f3;--text:#10233a;--muted:#718198;--green:#16a36c;--amber:#f5a209;--red:#e14f5a;
        min-height:100vh;background:radial-gradient(circle at 90% 0%,rgba(14,165,199,.08),transparent 28%),var(--bg);color:var(--text);padding:20px 0 46px;font-size:12px;
    }
    .kpiconfig-shell{width:min(1540px,calc(100% - 34px));margin:0 auto}
    .kpiconfig-head{display:flex;justify-content:space-between;align-items:flex-start;gap:18px;margin-bottom:14px}
    .kpiconfig-breadcrumb{display:flex;align-items:center;gap:7px;color:#6e7f94;font-size:10px;font-weight:800;margin-bottom:7px}
    .kpiconfig-title{margin:0;font-size:26px;font-weight:950;letter-spacing:-.035em}
    .kpiconfig-subtitle{margin:5px 0 0;color:var(--muted);font-size:11.5px}
    .kpiconfig-actions{display:flex;gap:8px;flex-wrap:wrap;justify-content:flex-end}
    .kpiconfig-btn{height:36px;border:1px solid #d7e4ef;border-radius:10px;background:#fff;color:#294763;padding:0 13px;display:inline-flex;align-items:center;justify-content:center;gap:7px;font-weight:900;text-decoration:none;white-space:nowrap;transition:.18s ease}
    .kpiconfig-btn:hover{transform:translateY(-1px);box-shadow:0 8px 24px rgba(16,35,58,.08);color:#183d5e}
    .kpiconfig-btn.primary{background:var(--navy);border-color:var(--navy);color:#fff}
    .kpiconfig-summary{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:9px;margin-bottom:12px}
    .kpiconfig-stat{background:#fff;border:1px solid var(--line);border-radius:13px;padding:12px 13px;min-height:88px;position:relative;overflow:hidden}
    .kpiconfig-stat:after{content:"";position:absolute;right:-20px;top:-30px;width:80px;height:80px;border-radius:999px;background:rgba(14,165,199,.06)}
    .kpiconfig-stat span{display:block;color:#73839a;font-size:9.5px;text-transform:uppercase;font-weight:900;letter-spacing:.035em}
    .kpiconfig-stat strong{display:block;margin-top:7px;font-size:24px;line-height:1;font-weight:950;color:#11324f}
    .kpiconfig-stat small{display:block;margin-top:5px;color:#8594a6;font-size:9px}
    .kpiconfig-panel{background:#fff;border:1px solid var(--line);border-radius:15px;box-shadow:0 8px 26px rgba(16,35,58,.045);overflow:hidden;margin-bottom:12px}
    .kpiconfig-panel-head{padding:12px 14px;border-bottom:1px solid var(--line);display:flex;justify-content:space-between;align-items:flex-start;gap:10px}
    .kpiconfig-panel-head h3{margin:0;font-size:13.5px;font-weight:950}.kpiconfig-panel-head p{margin:3px 0 0;color:var(--muted);font-size:10px}
    .kpiconfig-panel-body{padding:13px 14px}
    .kpiconfig-notice{display:flex;gap:8px;align-items:flex-start;padding:9px 11px;border-radius:10px;background:#eef8ff;border:1px solid #d6edf8;color:#45657d;font-size:10px;margin-bottom:11px}
    .kpiconfig-notice.warning{background:#fff8e8;border-color:#f4e2ad;color:#7a5b16}
    .kpiconfig-table-wrap{overflow:auto}
    .kpiconfig-table{width:100%;border-collapse:separate;border-spacing:0;min-width:1050px}
    .kpiconfig-table th{background:#f7f9fc;border-bottom:1px solid var(--line);padding:8px 8px;color:#60738b;font-size:8.8px;text-transform:uppercase;letter-spacing:.035em;text-align:left;white-space:nowrap}
    .kpiconfig-table td{padding:8px;border-bottom:1px solid #edf2f7;vertical-align:middle}
    .kpiconfig-table tr:last-child td{border-bottom:0}
    .kpiconfig-index{display:flex;align-items:center;gap:8px;font-weight:950;color:#2d4c67}
    .kpiconfig-icon{width:32px;height:32px;border-radius:9px;background:#edf7ff;color:#2883c4;display:flex;align-items:center;justify-content:center;font-size:14px;flex:0 0 32px}
    .kpiconfig-control{width:100%;height:34px;border:1px solid #d9e4ee;border-radius:9px;background:#fbfdff;padding:0 9px;color:#173a58;font-weight:750;outline:none}
    textarea.kpiconfig-control{height:52px;padding-top:7px;resize:vertical;line-height:1.35}
    .kpiconfig-control:focus{border-color:#7dc7dc;box-shadow:0 0 0 3px rgba(14,165,199,.07);background:#fff}
    .kpiconfig-weight{max-width:84px}.kpiconfig-order{max-width:60px}.kpiconfig-unit{max-width:110px}.kpiconfig-small{max-width:95px}
    .kpiconfig-footer{padding:10px 14px;border-top:1px solid var(--line);background:#fcfdff;display:flex;justify-content:space-between;align-items:center;gap:10px;flex-wrap:wrap}
    .kpiconfig-weight-note{font-size:10px;color:#6f8094}.kpiconfig-weight-note strong{font-size:13px;color:#133d5e}
    .kpiconfig-save{height:38px;border:0;border-radius:10px;background:var(--navy);color:#fff;padding:0 16px;font-weight:950;display:inline-flex;align-items:center;gap:7px;box-shadow:0 8px 20px rgba(11,53,88,.16)}
    .kpiconfig-grid{display:grid;grid-template-columns:1fr 1fr;gap:12px}
    .kpiconfig-setting-list{display:grid;gap:8px}
    .kpiconfig-setting{display:grid;grid-template-columns:minmax(0,1fr) 120px;gap:10px;align-items:center;padding:10px 11px;border:1px solid #e2ebf3;border-radius:11px;background:#fcfdff}
    .kpiconfig-setting strong{display:block;font-size:11px}.kpiconfig-setting small{display:block;color:#8391a3;font-size:9px;margin-top:2px}
    .kpiconfig-percent{position:relative}.kpiconfig-percent input{padding-right:28px}.kpiconfig-percent span{position:absolute;right:10px;top:50%;transform:translateY(-50%);color:#789;font-weight:900}
    .kpiconfig-scale{display:grid;grid-template-columns:repeat(4,1fr);gap:8px}
    .kpiconfig-scale-card{border:1px solid #e2ebf3;border-radius:11px;background:#f9fbfd;padding:10px;text-align:center}
    .kpiconfig-scale-card strong{display:block;font-size:12px;color:#173c5d}.kpiconfig-scale-card span{display:block;color:#8492a4;font-size:9px;margin-top:3px}
    .kpiconfig-material{grid-template-columns:repeat(5,1fr)}
    .kpiconfig-alert{padding:9px 12px;border-radius:11px;margin-bottom:10px;font-weight:750;border:1px solid}
    .kpiconfig-alert.success{background:#edf9f3;color:#16734f;border-color:#cfefdf}.kpiconfig-alert.danger{background:#fff0f1;color:#b43d48;border-color:#f5d4d8}
    .kpiconfig-row-actions{display:flex;align-items:center;gap:6px;white-space:nowrap}.kpiconfig-action{width:32px;height:32px;border-radius:9px;border:1px solid #dce7f0;background:#fff;display:inline-flex;align-items:center;justify-content:center;cursor:pointer;transition:.15s ease}.kpiconfig-action.edit{color:#176da6}.kpiconfig-action.delete{color:#cf4450;background:#fff7f8;border-color:#f1d8db}.kpiconfig-action:hover{transform:translateY(-1px);box-shadow:0 5px 14px rgba(16,35,58,.10)}.kpiconfig-readonly{font-size:10px;color:#8391a3;font-weight:800}.kpiconfig-table tr.is-deleted{display:none}.kpiconfig-table tr.is-editing td{background:#f4fbff}.kpiconfig-btn[type=button]{cursor:pointer}.kpiconfig-empty td{text-align:center;padding:25px;color:#8492a4}
    @media(max-width:1100px){.kpiconfig-summary{grid-template-columns:repeat(2,1fr)}.kpiconfig-grid{grid-template-columns:1fr}.kpiconfig-material{grid-template-columns:repeat(3,1fr)}}
    @media(max-width:720px){.kpiconfig-shell{width:min(100% - 20px,1540px)}.kpiconfig-head{flex-direction:column}.kpiconfig-actions{justify-content:flex-start}.kpiconfig-summary,.kpiconfig-scale,.kpiconfig-material{grid-template-columns:1fr 1fr}.kpiconfig-setting{grid-template-columns:1fr}}
</style>

<div class="kpiconfig-page">
    <div class="kpiconfig-shell">
        <header class="kpiconfig-head">
            <div>
                <div class="kpiconfig-breadcrumb"><span>Kỹ thuật</span><i class="bi bi-chevron-right"></i><span>KPIs</span><i class="bi bi-chevron-right"></i><span>Cấu hình</span></div>
                <h1 class="kpiconfig-title">Cấu hình KPIs Kỹ thuật</h1>
                <p class="kpiconfig-subtitle">Quản trị viên có thể thêm, sửa, xóa và sắp xếp các dòng KPI. Tổng trọng số của các tiêu chí đang dùng phải bằng 100%.</p>
            </div>
            <div class="kpiconfig-actions">
                <a href="{{ route('ky-thuat.kpis.index') }}" class="kpiconfig-btn"><i class="bi bi-arrow-left"></i>Về KPIs</a>
                <a href="{{ route('ky-thuat.luong.index') }}" class="kpiconfig-btn primary"><i class="bi bi-pencil-square"></i>Chấm KPI</a>
            </div>
        </header>

        @if(session('success'))<div class="kpiconfig-alert success"><i class="bi bi-check-circle me-1"></i>{{ session('success') }}</div>@endif
        @if(session('error'))<div class="kpiconfig-alert danger"><i class="bi bi-exclamation-triangle me-1"></i>{{ session('error') }}</div>@endif

        <section class="kpiconfig-summary">
            <article class="kpiconfig-stat"><span>Số tiêu chí</span><strong id="criteriaCountSummary">{{ $criteriaRows->count() }}</strong><small>Dòng KPI đang sử dụng</small></article>
            <article class="kpiconfig-stat"><span>Tổng trọng số</span><strong id="totalWeightSummary">100%</strong><small>Phải bằng 100%</small></article>
            <article class="kpiconfig-stat"><span>Lương cố định</span><strong id="baseRateSummary">{{ number_format(((float)($baseSetting->setting_value ?? .70))*100,0) }}%</strong><small>Tỷ lệ trên Gross</small></article>
            <article class="kpiconfig-stat"><span>Quỹ lương KPI</span><strong id="kpiRateSummary">{{ number_format(((float)($kpiSalarySetting->setting_value ?? .30))*100,0) }}%</strong><small>Tỷ lệ trên Gross</small></article>
        </section>

        <form method="POST" action="{{ route('ky-thuat.luong.settings.kpi-items') }}" id="kpiItemsForm" class="kpiconfig-panel">
            @csrf
            <div class="kpiconfig-panel-head">
                <div><h3>Danh sách tiêu chí KPI kỹ thuật</h3><p>Sửa trực tiếp từng dòng, thêm tiêu chí mới hoặc đánh dấu xóa rồi bấm Lưu cấu hình KPI.</p></div>
                <div class="kpiconfig-actions">
                    @if($canManageKpi)
                        <button type="button" class="kpiconfig-btn primary" id="addKpiRow"><i class="bi bi-plus-lg"></i>Thêm dòng</button>
                    @else
                        <span class="kpiconfig-btn" style="height:30px;pointer-events:none"><i class="bi bi-lock"></i>Chỉ Admin được sửa</span>
                    @endif
                </div>
            </div>
            <div class="kpiconfig-panel-body">
                <div class="kpiconfig-notice"><i class="bi bi-info-circle"></i><div>Mỗi dòng là một tiêu chí KPI. Bạn có thể đổi tên, đơn vị, KH/TH mặc định, trọng số, cách tính, <b>nguồn dữ liệu Công trình</b>, ghi chú và thứ tự. <b>Tổng trọng số phải bằng 100%</b> trước khi lưu.</div></div>
            </div>

            @foreach($legacyRows as $legacy)
                <input type="hidden" name="kpi_items[legacy_{{ $legacy->id }}][id]" value="{{ $legacy->id }}">
                <input type="hidden" name="kpi_items[legacy_{{ $legacy->id }}][sort_order]" value="{{ $legacy->sort_order ?? 99 }}">
                <input type="hidden" name="kpi_items[legacy_{{ $legacy->id }}][name]" value="{{ $legacy->name }}">
                <input type="hidden" name="kpi_items[legacy_{{ $legacy->id }}][unit]" value="{{ $legacy->unit }}">
                <input type="hidden" name="kpi_items[legacy_{{ $legacy->id }}][plan_value]" value="{{ $legacy->plan_value }}">
                <input type="hidden" name="kpi_items[legacy_{{ $legacy->id }}][actual_value]" value="{{ $legacy->actual_value }}">
                <input type="hidden" name="kpi_items[legacy_{{ $legacy->id }}][weight_percent]" value="{{ (float)$legacy->weight * 100 }}">
                <input type="hidden" name="kpi_items[legacy_{{ $legacy->id }}][calc_type]" value="{{ $legacy->calc_type }}">
                <input type="hidden" name="kpi_items[legacy_{{ $legacy->id }}][source_code]" value="{{ $legacy->source_code ?? 'manual' }}">
                <input type="hidden" name="kpi_items[legacy_{{ $legacy->id }}][note]" value="{{ $legacy->note }}">
                <input type="hidden" name="kpi_items[legacy_{{ $legacy->id }}][is_enabled]" value="0">
            @endforeach

            <div class="kpiconfig-table-wrap">
                <table class="kpiconfig-table">
                    <thead><tr><th>STT</th><th>Tiêu chí</th><th>ĐVT</th><th>KH mặc định</th><th>TH mặc định</th><th>Trọng số</th><th>Cách tính</th><th>Nguồn dữ liệu</th><th>Quy tắc / ghi chú</th><th>Thao tác</th></tr></thead>
                    <tbody id="kpiRowsBody">
                        @foreach($criteriaRows as $idx => $item)
                            @php
                                $isDb = is_object($item);
                                $id = $isDb ? ($item->id ?? null) : null;
                                $name = $isDb ? $item->name : $item['name'];
                                $unit = $isDb ? $item->unit : $item['unit'];
                                $plan = $isDb ? $item->plan_value : $item['plan_value'];
                                $actual = $isDb ? $item->actual_value : $item['actual_value'];
                                $weight = ($isDb ? (float)$item->weight : (float)$item['weight']) * 100;
                                $type = $isDb ? $item->calc_type : $item['calc_type'];
                                $sourceCode = $isDb ? ($item->source_code ?? 'manual') : ($item['source_code'] ?? 'manual');
                                $note = $isDb ? $item->note : $item['note'];
                                $order = $isDb ? ($item->sort_order ?? ($idx+1)) : $item['sort_order'];
                                $key = 'solar_'.$idx;
                            @endphp
                            <tr>
                                <td>
                                    @if($id)<input type="hidden" class="js-row-id" name="kpi_items[{{ $key }}][id]" value="{{ $id }}">@endif
                                    <input type="hidden" name="kpi_items[{{ $key }}][is_enabled]" value="1">
                                    <input type="hidden" class="js-delete-flag" name="kpi_items[{{ $key }}][delete]" value="0">
                                    <div class="kpiconfig-index"><span class="kpiconfig-icon"><i class="bi {{ $criteriaIcons[$idx+1] ?? 'bi-bar-chart' }}"></i></span><input type="number" name="kpi_items[{{ $key }}][sort_order]" value="{{ $order }}" class="kpiconfig-control kpiconfig-order" @disabled(!$canManageKpi)></div>
                                </td>
                                <td><input type="text" name="kpi_items[{{ $key }}][name]" value="{{ $name }}" class="kpiconfig-control js-kpi-name" required @disabled(!$canManageKpi)></td>
                                <td><input type="text" name="kpi_items[{{ $key }}][unit]" value="{{ $unit }}" class="kpiconfig-control kpiconfig-unit" @disabled(!$canManageKpi)></td>
                                <td><input type="number" step="0.01" min="0" name="kpi_items[{{ $key }}][plan_value]" value="{{ $plan }}" class="kpiconfig-control kpiconfig-small" @disabled(!$canManageKpi)></td>
                                <td><input type="number" step="0.01" min="0" name="kpi_items[{{ $key }}][actual_value]" value="{{ $actual }}" class="kpiconfig-control kpiconfig-small" @disabled(!$canManageKpi)></td>
                                <td><input type="number" step="0.01" min="0" max="100" name="kpi_items[{{ $key }}][weight_percent]" value="{{ number_format($weight,2,'.','') }}" class="kpiconfig-control kpiconfig-weight js-kpi-weight" @disabled(!$canManageKpi)></td>
                                <td>
                                    <select name="kpi_items[{{ $key }}][calc_type]" class="kpiconfig-control" @disabled(!$canManageKpi)>
                                        <option value="actual_div_plan" @selected($type==='actual_div_plan')>TH / KH (tối đa 100%)</option>
                                        <option value="plan_div_actual" @selected($type==='plan_div_actual')>KH / TH</option>
                                        <option value="material_waste" @selected($type==='material_waste')>Quy đổi hao hụt vật tư</option>
                                        <option value="minus_quality" @selected($type==='minus_quality')>Trừ theo lỗi chất lượng</option>
                                        <option value="minus_safety" @selected($type==='minus_safety')>Trừ theo sự cố HSE</option>
                                    </select>
                                </td>
                                <td>
                                    <select name="kpi_items[{{ $key }}][source_code]" class="kpiconfig-control" @disabled(!$canManageKpi)>
                                        @foreach($projectSourceOptions as $sourceValue => $sourceLabel)
                                            <option value="{{ $sourceValue }}" @selected($sourceCode === $sourceValue)>{{ $sourceLabel }}</option>
                                        @endforeach
                                    </select>
                                </td>
                                <td><textarea name="kpi_items[{{ $key }}][note]" class="kpiconfig-control" @disabled(!$canManageKpi)>{{ $note }}</textarea></td>
                                <td>
                                    @if($canManageKpi)
                                        <div class="kpiconfig-row-actions">
                                            <button type="button" class="kpiconfig-action edit js-edit-row" title="Sửa dòng"><i class="bi bi-pencil-square"></i></button>
                                            <button type="button" class="kpiconfig-action delete js-delete-row" title="Xóa dòng"><i class="bi bi-trash3"></i></button>
                                        </div>
                                    @else
                                        <span class="kpiconfig-readonly"><i class="bi bi-eye"></i> Xem</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="kpiconfig-footer">
                <div class="kpiconfig-weight-note">Tổng trọng số hiện tại: <strong id="weightTotalText">100%</strong> <span id="weightStatus">· Đúng chuẩn</span></div>
                @if($canManageKpi)
                    <button type="submit" class="kpiconfig-save" id="saveKpiItems"><i class="bi bi-save"></i>Lưu cấu hình KPI</button>
                @else
                    <span class="kpiconfig-weight-note"><i class="bi bi-lock me-1"></i>Chỉ Quản trị viên có quyền thay đổi cấu hình.</span>
                @endif
            </div>
        </form>

        <div class="kpiconfig-grid">
            <form method="POST" action="{{ route('ky-thuat.luong.settings.save') }}" class="kpiconfig-panel" id="settingsForm">
                @csrf
                <div class="kpiconfig-panel-head"><div><h3>Tỷ lệ lương & giới hạn KPI</h3><p>Giữ cấu hình tài chính tách biệt với các tiêu chí đánh giá.</p></div><i class="bi bi-wallet2 text-primary"></i></div>
                <div class="kpiconfig-panel-body">
                    <div class="kpiconfig-setting-list">
                        <div class="kpiconfig-setting">
                            <div><strong>Lương cố định</strong><small>{{ $baseSetting->note ?? 'Phần lương cố định trên Gross.' }}</small></div>
                            <div class="kpiconfig-percent"><input type="number" step="0.01" min="0" max="100" name="settings[base_salary_rate][value]" value="{{ number_format(((float)($baseSetting->setting_value ?? .70))*100,2,'.','') }}" class="kpiconfig-control js-salary-rate" @disabled(!$canManageKpi)><span>%</span></div>
                            <input type="hidden" name="settings[base_salary_rate][label]" value="{{ $baseSetting->setting_label ?? 'Tỷ lệ lương cố định' }}"><input type="hidden" name="settings[base_salary_rate][note]" value="{{ $baseSetting->note ?? 'Phần lương cố định trên Gross.' }}">
                        </div>
                        <div class="kpiconfig-setting">
                            <div><strong>Quỹ lương KPI</strong><small>{{ $kpiSalarySetting->note ?? 'Phần lương biến đổi theo KPI.' }}</small></div>
                            <div class="kpiconfig-percent"><input type="number" step="0.01" min="0" max="100" name="settings[kpi_salary_rate][value]" value="{{ number_format(((float)($kpiSalarySetting->setting_value ?? .30))*100,2,'.','') }}" class="kpiconfig-control js-salary-rate" @disabled(!$canManageKpi)><span>%</span></div>
                            <input type="hidden" name="settings[kpi_salary_rate][label]" value="{{ $kpiSalarySetting->setting_label ?? 'Tỷ lệ lương KPI' }}"><input type="hidden" name="settings[kpi_salary_rate][note]" value="{{ $kpiSalarySetting->note ?? 'Phần lương biến đổi theo KPI.' }}">
                        </div>
                        <div class="kpiconfig-setting">
                            <div><strong>Trần KPI tổng</strong><small>{{ $maxSetting->note ?? 'Giới hạn KPI tối đa dùng khi tính lương.' }}</small></div>
                            <div class="kpiconfig-percent"><input type="number" step="0.01" min="100" max="200" name="settings[kpi_max_rate][value]" value="{{ number_format(((float)($maxSetting->setting_value ?? 1.30))*100,2,'.','') }}" class="kpiconfig-control" @disabled(!$canManageKpi)><span>%</span></div>
                            <input type="hidden" name="settings[kpi_max_rate][label]" value="{{ $maxSetting->setting_label ?? 'Trần KPI tổng' }}"><input type="hidden" name="settings[kpi_max_rate][note]" value="{{ $maxSetting->note ?? 'Giới hạn KPI tối đa dùng khi tính lương.' }}">
                        </div>
                    </div>
                    <div class="kpiconfig-notice" id="salaryTotalNotice" style="margin-top:10px;margin-bottom:0"><i class="bi bi-calculator"></i><div>Lương cố định + quỹ KPI = <b id="salaryTotalText">100%</b>. Tổng hai tỷ lệ nên bằng 100%.</div></div>
                </div>
                <div class="kpiconfig-footer"><span class="kpiconfig-weight-note">Các thông số cũ khác vẫn được giữ trong database, trang này chỉ hiển thị phần đang dùng.</span>@if($canManageKpi)<button type="submit" class="kpiconfig-save"><i class="bi bi-save"></i>Lưu tỷ lệ lương</button>@else<span class="kpiconfig-weight-note"><i class="bi bi-lock me-1"></i>Chỉ Admin được sửa</span>@endif</div>
            </form>

            <section class="kpiconfig-panel">
                <div class="kpiconfig-panel-head"><div><h3>Quy tắc hiển thị trên Dashboard</h3><p>Cùng chuẩn với trang KPIs Kỹ thuật hiện tại.</p></div><i class="bi bi-speedometer2 text-primary"></i></div>
                <div class="kpiconfig-panel-body">
                    <div class="kpiconfig-scale">
                        <div class="kpiconfig-scale-card"><strong>≥ 100%</strong><span>Vượt KPI · quỹ KPI 110–120%</span></div>
                        <div class="kpiconfig-scale-card"><strong>90–&lt;100%</strong><span>Đạt KPI · quỹ KPI 100%</span></div>
                        <div class="kpiconfig-scale-card"><strong>75–&lt;90%</strong><span>Cần cải thiện · quỹ KPI 80%</span></div>
                        <div class="kpiconfig-scale-card"><strong>&lt; 75%</strong><span>Không đạt · quỹ KPI 0%</span></div>
                    </div>
                    <div style="height:10px"></div>
                    <div class="kpiconfig-scale kpiconfig-material">
                        <div class="kpiconfig-scale-card"><strong>0%</strong><span>120% điểm vật tư</span></div>
                        <div class="kpiconfig-scale-card"><strong>&gt;0–2%</strong><span>100% điểm</span></div>
                        <div class="kpiconfig-scale-card"><strong>&gt;2–4%</strong><span>85% điểm</span></div>
                        <div class="kpiconfig-scale-card"><strong>&gt;4–6%</strong><span>70% điểm</span></div>
                        <div class="kpiconfig-scale-card"><strong>&gt;6%</strong><span>0% điểm</span></div>
                    </div>
                    <div class="kpiconfig-notice warning" style="margin-top:10px;margin-bottom:0"><i class="bi bi-shield-exclamation"></i><div>Vi phạm an toàn nghiêm trọng có thể trừ trực tiếp <b>10 hoặc 20 điểm KPI</b> tại màn hình Chấm KPI.</div></div>
                </div>
            </section>
        </div>
    </div>
</div>

<script>
(function(){
    const canManage = @json($canManageKpi);
    const tbody = document.getElementById('kpiRowsBody');
    const save = document.getElementById('saveKpiItems');
    const addButton = document.getElementById('addKpiRow');
    let rowCounter = Date.now();

    function activeRows(){
        return [...tbody.querySelectorAll('tr')].filter(row => !row.classList.contains('is-deleted') && !row.classList.contains('kpiconfig-empty'));
    }

    function weightSummary(){
        const rows = activeRows();
        const total = rows.reduce((sum,row)=>sum+(parseFloat(row.querySelector('.js-kpi-weight')?.value)||0),0);
        const text = total.toLocaleString('vi-VN',{minimumFractionDigits:0,maximumFractionDigits:2})+'%';
        document.getElementById('weightTotalText').textContent = text;
        document.getElementById('totalWeightSummary').textContent = text;
        document.getElementById('criteriaCountSummary').textContent = rows.length;
        const ok = rows.length > 0 && Math.abs(total-100)<0.001;
        const status = document.getElementById('weightStatus');
        status.textContent = ok ? '· Đúng chuẩn' : (rows.length ? '· Cần chỉnh về 100%' : '· Cần ít nhất 1 tiêu chí');
        status.style.color = ok ? '#16845a' : '#d34a55';
        if (save) {
            save.disabled = !ok;
            save.style.opacity = ok ? '1' : '.55';
            save.title = ok ? '' : 'Cần ít nhất 1 tiêu chí và tổng trọng số phải bằng 100%';
        }
    }

    function bindRow(row){
        row.querySelector('.js-kpi-weight')?.addEventListener('input', weightSummary);
        row.querySelector('.js-edit-row')?.addEventListener('click', function(){
            row.classList.add('is-editing');
            const input = row.querySelector('.js-kpi-name');
            input?.focus();
            input?.select();
            setTimeout(()=>row.classList.remove('is-editing'), 1200);
        });
        row.querySelector('.js-delete-row')?.addEventListener('click', function(){
            if (!confirm('Xóa dòng KPI này? Thay đổi sẽ được áp dụng khi bấm Lưu cấu hình KPI.')) return;
            const id = row.querySelector('.js-row-id')?.value;
            if (id) {
                const flag = row.querySelector('.js-delete-flag');
                if (flag) flag.value = '1';
                row.classList.add('is-deleted');
            } else {
                row.remove();
            }
            weightSummary();
        });
    }

    function addRow(){
        if (!canManage) return;
        const key = 'new_' + (rowCounter++);
        const order = activeRows().length + 1;
        const tr = document.createElement('tr');
        tr.innerHTML = `
            <td>
                <input type="hidden" name="kpi_items[${key}][is_enabled]" value="1">
                <input type="hidden" class="js-delete-flag" name="kpi_items[${key}][delete]" value="0">
                <div class="kpiconfig-index"><span class="kpiconfig-icon"><i class="bi bi-plus-circle"></i></span><input type="number" min="1" name="kpi_items[${key}][sort_order]" value="${order}" class="kpiconfig-control kpiconfig-order"></div>
            </td>
            <td><input type="text" name="kpi_items[${key}][name]" value="" class="kpiconfig-control js-kpi-name" placeholder="Tên tiêu chí KPI" required></td>
            <td><input type="text" name="kpi_items[${key}][unit]" value="Công trình" class="kpiconfig-control kpiconfig-unit"></td>
            <td><input type="number" step="0.01" min="0" name="kpi_items[${key}][plan_value]" value="1" class="kpiconfig-control kpiconfig-small"></td>
            <td><input type="number" step="0.01" min="0" name="kpi_items[${key}][actual_value]" value="1" class="kpiconfig-control kpiconfig-small"></td>
            <td><input type="number" step="0.01" min="0" max="100" name="kpi_items[${key}][weight_percent]" value="0" class="kpiconfig-control kpiconfig-weight js-kpi-weight"></td>
            <td>
                <select name="kpi_items[${key}][calc_type]" class="kpiconfig-control">
                    <option value="actual_div_plan">TH / KH (tối đa 100%)</option>
                    <option value="plan_div_actual">KH / TH</option>
                    <option value="material_waste">Quy đổi hao hụt vật tư</option>
                    <option value="minus_quality">Trừ theo lỗi chất lượng</option>
                    <option value="minus_safety">Trừ theo sự cố HSE</option>
                </select>
            </td>
            <td><select name="kpi_items[${key}][source_code]" class="kpiconfig-control"><option value="manual">Nhập tay</option><option value="project_timeline">Công trình · Tiến độ / deadline</option><option value="project_quality">Công trình · Nghiệm thu / chất lượng</option><option value="project_material_waste">Công trình · Hao hụt vật tư</option><option value="project_hse">Công trình · HSE / vệ sinh</option><option value="project_evn_app">Công trình · EVN / App</option></select></td>
            <td><textarea name="kpi_items[${key}][note]" class="kpiconfig-control" placeholder="Quy tắc / ghi chú"></textarea></td>
            <td><div class="kpiconfig-row-actions"><button type="button" class="kpiconfig-action edit js-edit-row" title="Sửa dòng"><i class="bi bi-pencil-square"></i></button><button type="button" class="kpiconfig-action delete js-delete-row" title="Xóa dòng"><i class="bi bi-trash3"></i></button></div></td>
        `;
        tbody.appendChild(tr);
        bindRow(tr);
        weightSummary();
        tr.querySelector('.js-kpi-name')?.focus();
    }

    tbody.querySelectorAll('tr').forEach(bindRow);
    addButton?.addEventListener('click', addRow);
    weightSummary();

    const salaryInputs=[...document.querySelectorAll('.js-salary-rate')];
    function salarySummary(){
        const base=parseFloat(salaryInputs[0]?.value||0), kpi=parseFloat(salaryInputs[1]?.value||0), total=base+kpi;
        document.getElementById('baseRateSummary').textContent=base.toLocaleString('vi-VN',{maximumFractionDigits:2})+'%';
        document.getElementById('kpiRateSummary').textContent=kpi.toLocaleString('vi-VN',{maximumFractionDigits:2})+'%';
        document.getElementById('salaryTotalText').textContent=total.toLocaleString('vi-VN',{maximumFractionDigits:2})+'%';
        const notice=document.getElementById('salaryTotalNotice');
        const ok=Math.abs(total-100)<0.001;
        notice.classList.toggle('warning',!ok);
    }
    salaryInputs.forEach(i=>i.addEventListener('input',salarySummary));
    salarySummary();
})();
</script>
@endsection
