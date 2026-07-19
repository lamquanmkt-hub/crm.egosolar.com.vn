@extends('layouts.app')

@section('title', 'Cài đặt KPI Sales')

@section('content')
@php
    $enabled = fn($key) => (int)($settings[$key] ?? 0) === 1;
    $money = fn($value) => number_format((int)($value ?? 0), 0, ',', '.') . 'đ';

    $coreOn = collect($coreModules ?? [])->filter(fn($m) => $enabled($m['enabled_key']))->count();
    $suggestedOn = collect($suggestedModules ?? [])->filter(fn($m) => $enabled($m['enabled_key']))->count();

    $workload =
        (int)($settings['posts_target'] ?? 0) +
        (int)($settings['calls_target'] ?? 0) +
        (int)($settings['company_data_target'] ?? 0);

    $maxPenalty = $enabled('enable_penalty')
        ? (int)($settings['penalty_per_missing'] ?? 0) * max(1, $coreOn)
        : 0;
@endphp

<div class="container-fluid px-3 px-lg-4 py-3 sales-kpi-settings-v2">
    @if(session('success'))
        <div class="mini-alert mini-alert--success mb-3">
            <i class="bi bi-check-circle"></i>
            <div>
                <strong>Đã lưu cấu hình</strong>
                <span>{{ session('success') }}</span>
            </div>
        </div>
    @endif

    @if ($errors->any())
        <div class="mini-alert mini-alert--danger mb-3">
            <i class="bi bi-exclamation-triangle"></i>
            <div>
                <strong>Có lỗi khi lưu</strong>
                <ul class="mb-0 ps-3">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        </div>
    @endif

    <form method="POST" action="{{ route('sales.kpi.settings.save') }}" id="kpiSettingsForm">
        @csrf

        <section class="kpi-hero mb-3">
            <div class="kpi-hero__glow kpi-hero__glow--one"></div>
            <div class="kpi-hero__glow kpi-hero__glow--two"></div>

            <div class="kpi-hero__main">
                <div class="eyebrow">
                    <span></span>
                    SALES KPI POLICY BUILDER
                </div>

                <h1>Cài đặt KPI Sales</h1>

                <p>
                    Thiết kế rule KPI theo dạng module. Bật/tắt hạng mục, chỉnh target, preview mức phạt
                    và lưu chính sách vận hành cho team sales.
                </p>

                <div class="hero-actions">
                    <a href="{{ route('sales.kpi.index') }}" class="btn btn-hero btn-hero--light">
                        <i class="bi bi-speedometer2 me-1"></i>Dashboard KPI
                    </a>
                    <a href="{{ route('sales.kpi.my') }}" class="btn btn-hero btn-hero--ghost">
                        <i class="bi bi-pencil-square me-1"></i>Nhập KPI
                    </a>
                    <button type="submit" class="btn btn-hero btn-hero--primary">
                        <i class="bi bi-save2 me-1"></i>Lưu cấu hình
                    </button>
                </div>
            </div>

            <div class="kpi-hero__stats">
                <div class="hero-stat">
                    <span>Core đang bật</span>
                    <strong id="heroCoreOn">{{ $coreOn }}/{{ count($coreModules ?? []) }}</strong>
                </div>
                <div class="hero-stat">
                    <span>Gợi ý đang bật</span>
                    <strong id="heroSuggestedOn">{{ $suggestedOn }}/{{ count($suggestedModules ?? []) }}</strong>
                </div>
                <div class="hero-stat hero-stat--danger">
                    <span>Phạt tối đa/ngày</span>
                    <strong id="heroMaxPenalty">{{ $money($maxPenalty) }}</strong>
                </div>
            </div>
        </section>

        <div class="row g-3">
            <div class="col-xxl-8">
                <div class="policy-card mb-3">
                    <div class="policy-card__head">
                        <div>
                            <div class="section-chip">CORE KPI</div>
                            <h2>Hạng mục đang chấm điểm thật</h2>
                            <p>4 module này đã có dữ liệu trong form nhập KPI hiện tại. Bật/tắt ở đây sẽ ảnh hưởng cách tính % hoàn thành và khấu trừ.</p>
                        </div>

                        <div class="preset-group">
                            <button type="button" class="preset-btn" data-preset="light">Nhẹ</button>
                            <button type="button" class="preset-btn active" data-preset="standard">Chuẩn</button>
                            <button type="button" class="preset-btn" data-preset="strict">Gắt</button>
                        </div>
                    </div>

                    <div class="core-grid">
                        @foreach($coreModules as $module)
                            @php
                                $isOn = $enabled($module['enabled_key']);
                                $targetKey = $module['target_key'];
                                $targetValue = $targetKey ? (int)($settings[$targetKey] ?? 0) : 1;
                            @endphp

                            <div class="module-card module-card--{{ $module['tone'] }} {{ $isOn ? 'is-on' : 'is-off' }}" data-module-card>
                                <div class="module-card__top">
                                    <div class="module-icon">
                                        <i class="bi {{ $module['icon'] }}"></i>
                                    </div>

                                    <div class="module-copy">
                                        <strong>{{ $module['title'] }}</strong>
                                        <span>{{ $module['description'] }}</span>
                                    </div>

                                    <label class="smart-switch">
                                        <input type="hidden" name="{{ $module['enabled_key'] }}" value="0">
                                        <input type="checkbox" name="{{ $module['enabled_key'] }}" value="1" data-toggle-input {{ $isOn ? 'checked' : '' }}>
                                        <span></span>
                                    </label>
                                </div>

                                @if($targetKey)
                                    <div class="compact-field mt-3">
                                        <label>Target</label>
                                        <div class="compact-input">
                                            <input
                                                type="number"
                                                min="0"
                                                name="{{ $targetKey }}"
                                                id="{{ $targetKey }}"
                                                value="{{ $targetValue }}"
                                                data-core-target
                                            >
                                            <em>{{ $module['unit'] }}</em>
                                        </div>
                                    </div>
                                @else
                                    <div class="boolean-preview mt-3">
                                        <i class="bi bi-check2-circle"></i>
                                        Checklist bắt buộc, không cần nhập số target.
                                    </div>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </div>

                <div class="policy-card mb-3">
                    <div class="policy-card__head">
                        <div>
                            <div class="section-chip section-chip--purple">MODULE GỢI Ý</div>
                            <h2>Hạng mục nâng cấp có thể bật/tắt</h2>
                            <p>Các module này được lưu vào cấu hình để chuẩn bị nối field nhập hoặc auto-sync ở bước sau.</p>
                        </div>

                        <div class="preset-group">
                            <button type="button" class="preset-btn" id="btnEnableSuggested">Bật hết</button>
                            <button type="button" class="preset-btn" id="btnDisableSuggested">Tắt hết</button>
                        </div>
                    </div>

                    <div class="suggestion-grid">
                        @foreach($suggestedModules as $module)
                            @php
                                $isOn = $enabled($module['enabled_key']);
                                $targetKey = $module['target_key'];
                                $targetValue = (int)($settings[$targetKey] ?? 0);
                            @endphp

                            <div class="suggestion-card {{ $isOn ? 'is-on' : 'is-off' }}" data-suggested-card>
                                <div class="suggestion-card__main">
                                    <div class="suggestion-icon">
                                        <i class="bi {{ $module['icon'] }}"></i>
                                    </div>

                                    <div class="suggestion-copy">
                                        <strong>{{ $module['title'] }}</strong>
                                        <span>{{ $module['description'] }}</span>
                                    </div>

                                    <label class="smart-switch smart-switch--sm">
                                        <input type="hidden" name="{{ $module['enabled_key'] }}" value="0">
                                        <input type="checkbox" name="{{ $module['enabled_key'] }}" value="1" data-toggle-input data-suggested-toggle {{ $isOn ? 'checked' : '' }}>
                                        <span></span>
                                    </label>
                                </div>

                                <div class="suggestion-target">
                                    <label>Target</label>
                                    <div>
                                        <input type="number" min="0" name="{{ $targetKey }}" value="{{ $targetValue }}">
                                        <em>{{ $module['unit'] }}</em>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>

                <div class="policy-card">
                    <div class="policy-card__head">
                        <div>
                            <div class="section-chip section-chip--green">AUTOMATION</div>
                            <h2>Quy tắc vận hành</h2>
                            <p>Bật/tắt các rule phụ để chuẩn bị cho workflow duyệt, cảnh báo và đồng bộ dữ liệu.</p>
                        </div>
                    </div>

                    <div class="ops-grid">
                        @php
                            $ops = [
                                ['key' => 'enable_warnings', 'title' => 'Cảnh báo thiếu KPI', 'desc' => 'Hiển thị warning khi nhân viên chưa nhập hoặc thiếu KPI.', 'icon' => 'bi-bell'],
                                ['key' => 'enable_penalty', 'title' => 'Tính khấu trừ dự kiến', 'desc' => 'Tắt mục này thì KPI vẫn tính %, nhưng phạt = 0đ.', 'icon' => 'bi-cash-coin'],
                                ['key' => 'enable_manager_approval', 'title' => 'Quản lý duyệt KPI', 'desc' => 'Chuẩn bị workflow duyệt trước khi chốt lương.', 'icon' => 'bi-shield-check'],
                                ['key' => 'enable_callio_sync', 'title' => 'Đồng bộ Callio', 'desc' => 'Chuẩn bị auto-sync số cuộc gọi từ Callio.', 'icon' => 'bi-cloud-arrow-down'],
                                ['key' => 'enable_lock_after_days', 'title' => 'Khóa sửa sau số ngày', 'desc' => 'Giới hạn nhân viên sửa KPI sau ngày đã nhập.', 'icon' => 'bi-lock'],
                            ];
                        @endphp

                        @foreach($ops as $item)
                            <div class="ops-item {{ $enabled($item['key']) ? 'is-on' : 'is-off' }}">
                                <div class="ops-icon">
                                    <i class="bi {{ $item['icon'] }}"></i>
                                </div>
                                <div class="ops-copy">
                                    <strong>{{ $item['title'] }}</strong>
                                    <span>{{ $item['desc'] }}</span>
                                </div>
                                <label class="smart-switch smart-switch--sm">
                                    <input type="hidden" name="{{ $item['key'] }}" value="0">
                                    <input type="checkbox" name="{{ $item['key'] }}" value="1" data-toggle-input {{ $enabled($item['key']) ? 'checked' : '' }}>
                                    <span></span>
                                </label>
                            </div>
                        @endforeach

                        <div class="ops-item ops-item--input">
                            <div class="ops-icon">
                                <i class="bi bi-calendar2-x"></i>
                            </div>
                            <div class="ops-copy">
                                <strong>Số ngày được sửa KPI</strong>
                                <span>Ví dụ: 2 nghĩa là sau 2 ngày sẽ khóa chỉnh sửa.</span>
                            </div>
                            <input type="number" min="0" name="lock_after_days" value="{{ (int)($settings['lock_after_days'] ?? 2) }}">
                        </div>
                    </div>

                    <div class="note-box mt-3">
                        <label>Ghi chú chính sách</label>
                        <textarea name="settings_note" rows="3" placeholder="Ví dụ: Áp dụng từ tháng 05/2026, team sales C&I ưu tiên gọi data công ty...">{{ $settings['settings_note'] ?? '' }}</textarea>
                    </div>
                </div>
            </div>

            <div class="col-xxl-4">
                <div class="sticky-side">
                    <div class="side-card side-card--dark">
                        <div class="side-card__head">
                            <div>
                                <span>LIVE PREVIEW</span>
                                <strong>Policy Snapshot</strong>
                            </div>
                            <div class="pulse-dot"></div>
                        </div>

                        <div class="score-ring">
                            <div>
                                <span>Score</span>
                                <strong id="policyScore">96</strong>
                                <em id="policyLabel">Cân bằng</em>
                            </div>
                        </div>

                        <div class="snapshot-list">
                            <div>
                                <span>Core bật</span>
                                <strong id="sideCoreOn">{{ $coreOn }}</strong>
                            </div>
                            <div>
                                <span>Module gợi ý bật</span>
                                <strong id="sideSuggestedOn">{{ $suggestedOn }}</strong>
                            </div>
                            <div>
                                <span>Workload/ngày</span>
                                <strong id="sideWorkload">{{ number_format($workload, 0, ',', '.') }}</strong>
                            </div>
                            <div class="danger">
                                <span>Max phạt/ngày</span>
                                <strong id="sideMaxPenalty">{{ $money($maxPenalty) }}</strong>
                            </div>
                        </div>
                    </div>

                    <div class="side-card">
                        <div class="side-title">
                            <i class="bi bi-list-check"></i>
                            Rule đang áp dụng
                        </div>

                        <ul class="rule-list">
                            <li data-rule="posts">Bài đăng: <strong>{{ number_format((int)($settings['posts_target'] ?? 0), 0, ',', '.') }}</strong> bài/ngày</li>
                            <li data-rule="calls">Cuộc gọi: <strong>{{ number_format((int)($settings['calls_target'] ?? 0), 0, ',', '.') }}</strong> call/ngày</li>
                            <li data-rule="company">Data công ty: <strong>{{ number_format((int)($settings['company_data_target'] ?? 0), 0, ',', '.') }}</strong> data/ngày</li>
                            <li>Follow up khách cũ: <strong>bắt buộc nếu bật</strong></li>
                            <li>Phạt mỗi KPI thiếu: <strong id="sidePenalty">{{ $money($settings['penalty_per_missing'] ?? 0) }}</strong></li>
                        </ul>
                    </div>

                    <div class="save-card">
                        <div>
                            <strong>Sau khi lưu</strong>
                            <span>File cấu hình nằm tại <code>storage/app/sales_kpi_settings.json</code>.</span>
                        </div>

                        <button type="submit" class="btn btn-save w-100">
                            <i class="bi bi-lightning-charge-fill me-1"></i>Lưu toàn bộ cấu hình
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>

<style>
.sales-kpi-settings-v2{
    --text:#0f172a;
    --muted:#64748b;
    --line:#e6edf6;
    --panel:#fff;
    --blue:#2563eb;
    --cyan:#06b6d4;
    --purple:#7c3aed;
    --green:#16a34a;
    --red:#dc2626;
    --amber:#f59e0b;
    font-size:12.5px;
    color:var(--text);
}

.sales-kpi-settings-v2 input,
.sales-kpi-settings-v2 textarea,
.sales-kpi-settings-v2 button,
.sales-kpi-settings-v2 select{
    font-size:12.5px;
}

.mini-alert{
    display:flex;
    gap:10px;
    align-items:flex-start;
    padding:11px 13px;
    border-radius:16px;
    background:#fff;
    border:1px solid var(--line);
    box-shadow:0 10px 30px rgba(15,23,42,.05);
}
.mini-alert i{font-size:18px;}
.mini-alert strong{display:block;font-size:13px;font-weight:900;}
.mini-alert span,.mini-alert li{font-size:12px;color:var(--muted);}
.mini-alert--success i{color:#16a34a;}
.mini-alert--danger i{color:#dc2626;}

.kpi-hero{
    position:relative;
    overflow:hidden;
    display:grid;
    grid-template-columns:1.4fr .8fr;
    gap:16px;
    padding:22px;
    border-radius:26px;
    background:
        radial-gradient(circle at 10% 10%, rgba(37,99,235,.35), transparent 30%),
        linear-gradient(135deg,#071225 0%,#101d37 52%,#16345d 100%);
    color:#fff;
    box-shadow:0 22px 60px rgba(15,23,42,.18);
    animation:fadeUp .45s ease both;
}
.kpi-hero__glow{
    position:absolute;
    border-radius:999px;
    filter:blur(2px);
    opacity:.7;
    animation:floaty 7s ease-in-out infinite;
}
.kpi-hero__glow--one{width:90px;height:90px;background:rgba(6,182,212,.18);right:32%;top:25px;}
.kpi-hero__glow--two{width:130px;height:130px;background:rgba(124,58,237,.16);right:45px;bottom:18px;animation-delay:-3s;}
.kpi-hero__main,.kpi-hero__stats{position:relative;z-index:2;}
.eyebrow{
    display:inline-flex;
    align-items:center;
    gap:8px;
    padding:7px 10px;
    border-radius:999px;
    border:1px solid rgba(255,255,255,.12);
    background:rgba(255,255,255,.08);
    font-size:10px;
    font-weight:900;
    letter-spacing:.08em;
    color:#dbeafe;
}
.eyebrow span{
    width:7px;
    height:7px;
    border-radius:999px;
    background:#22c55e;
    box-shadow:0 0 0 6px rgba(34,197,94,.13);
}
.kpi-hero h1{
    margin:13px 0 8px;
    font-size:34px;
    line-height:1.05;
    letter-spacing:-.035em;
    font-weight:950;
}
.kpi-hero p{
    max-width:760px;
    margin:0;
    color:#cbd5e1;
    font-size:12.5px;
    line-height:1.7;
}
.hero-actions{
    display:flex;
    flex-wrap:wrap;
    gap:8px;
    margin-top:16px;
}
.btn-hero{
    min-height:38px;
    border-radius:13px;
    padding:0 13px;
    font-weight:850;
    display:inline-flex;
    align-items:center;
    justify-content:center;
}
.btn-hero--light{background:#fff;color:#0f172a;border:0;}
.btn-hero--ghost{background:rgba(255,255,255,.08);color:#fff;border:1px solid rgba(255,255,255,.12);}
.btn-hero--primary{background:linear-gradient(135deg,#2563eb,#06b6d4);color:#fff;border:0;}

.kpi-hero__stats{
    display:grid;
    grid-template-columns:1fr;
    gap:9px;
    align-content:center;
}
.hero-stat{
    padding:14px;
    border-radius:18px;
    background:rgba(255,255,255,.08);
    border:1px solid rgba(255,255,255,.1);
}
.hero-stat span{
    display:block;
    color:#cbd5e1;
    font-size:11px;
    font-weight:800;
    margin-bottom:4px;
}
.hero-stat strong{
    font-size:24px;
    font-weight:950;
    letter-spacing:-.03em;
}
.hero-stat--danger strong{color:#fecaca;}

.policy-card,.side-card,.save-card{
    background:#fff;
    border:1px solid var(--line);
    border-radius:22px;
    box-shadow:0 14px 42px rgba(15,23,42,.055);
}
.policy-card{
    padding:18px;
    animation:fadeUp .45s ease both;
}
.policy-card__head{
    display:flex;
    align-items:flex-start;
    justify-content:space-between;
    gap:14px;
    margin-bottom:15px;
}
.section-chip{
    display:inline-flex;
    align-items:center;
    padding:5px 8px;
    border-radius:999px;
    background:#eef4ff;
    color:#1d4ed8;
    font-size:10px;
    font-weight:950;
    letter-spacing:.08em;
    margin-bottom:7px;
}
.section-chip--purple{background:#f5f3ff;color:#6d28d9;}
.section-chip--green{background:#ecfdf5;color:#15803d;}
.policy-card h2{
    margin:0 0 5px;
    font-size:17px;
    font-weight:950;
    letter-spacing:-.015em;
}
.policy-card p{
    margin:0;
    color:var(--muted);
    font-size:12px;
    line-height:1.55;
}
.preset-group{
    display:flex;
    flex-wrap:wrap;
    gap:6px;
    justify-content:flex-end;
}
.preset-btn{
    border:1px solid var(--line);
    background:#fff;
    color:#334155;
    padding:7px 9px;
    border-radius:999px;
    font-size:11.5px;
    font-weight:850;
}
.preset-btn.active,.preset-btn:hover{
    background:#0f172a;
    color:#fff;
    border-color:#0f172a;
}

.core-grid{
    display:grid;
    grid-template-columns:repeat(2,minmax(0,1fr));
    gap:12px;
}
.module-card{
    position:relative;
    overflow:hidden;
    padding:14px;
    border-radius:20px;
    border:1px solid var(--line);
    background:linear-gradient(180deg,#fff,#fbfdff);
    transition:.2s ease;
}
.module-card:hover{transform:translateY(-2px);box-shadow:0 15px 34px rgba(15,23,42,.07);}
.module-card.is-off{opacity:.68;background:#f8fafc;}
.module-card__top{
    display:grid;
    grid-template-columns:40px 1fr auto;
    gap:10px;
    align-items:start;
}
.module-icon,.suggestion-icon,.ops-icon{
    width:40px;
    height:40px;
    border-radius:14px;
    display:flex;
    align-items:center;
    justify-content:center;
    color:#fff;
    font-size:18px;
}
.module-card--blue .module-icon{background:linear-gradient(135deg,#2563eb,#60a5fa);}
.module-card--cyan .module-icon{background:linear-gradient(135deg,#0891b2,#22d3ee);}
.module-card--violet .module-icon{background:linear-gradient(135deg,#7c3aed,#a78bfa);}
.module-card--green .module-icon{background:linear-gradient(135deg,#16a34a,#4ade80);}
.module-copy strong,.suggestion-copy strong,.ops-copy strong{
    display:block;
    font-size:13px;
    font-weight:950;
    margin-bottom:3px;
}
.module-copy span,.suggestion-copy span,.ops-copy span{
    display:block;
    color:var(--muted);
    font-size:11.5px;
    line-height:1.45;
}

.smart-switch{
    position:relative;
    display:inline-flex;
    width:44px;
    height:25px;
    cursor:pointer;
}
.smart-switch input{display:none;}
.smart-switch span{
    position:absolute;
    inset:0;
    border-radius:999px;
    background:#cbd5e1;
    transition:.18s ease;
}
.smart-switch span:after{
    content:"";
    position:absolute;
    width:19px;
    height:19px;
    left:3px;
    top:3px;
    border-radius:999px;
    background:#fff;
    transition:.18s ease;
    box-shadow:0 2px 8px rgba(15,23,42,.18);
}
.smart-switch input:checked + span{background:linear-gradient(90deg,#2563eb,#06b6d4);}
.smart-switch input:checked + span:after{left:22px;}
.smart-switch--sm{width:38px;height:22px;}
.smart-switch--sm span:after{width:16px;height:16px;}
.smart-switch--sm input:checked + span:after{left:19px;}

.compact-field label,.suggestion-target label,.note-box label{
    display:block;
    margin-bottom:5px;
    font-size:11px;
    color:#475569;
    font-weight:900;
}
.compact-input,.suggestion-target > div{
    display:grid;
    grid-template-columns:1fr auto;
    align-items:center;
    overflow:hidden;
    border:1px solid #dbe6f2;
    border-radius:14px;
    background:#fff;
}
.compact-input input,.suggestion-target input{
    min-height:38px;
    border:0;
    outline:0;
    padding:0 11px;
    font-size:16px;
    font-weight:950;
    color:#0f172a;
    background:transparent;
}
.compact-input em,.suggestion-target em{
    padding:0 10px;
    color:#64748b;
    font-size:11px;
    font-style:normal;
    font-weight:800;
    white-space:nowrap;
}
.boolean-preview{
    min-height:38px;
    display:flex;
    align-items:center;
    gap:7px;
    padding:0 11px;
    border-radius:14px;
    background:#ecfdf5;
    color:#166534;
    font-size:11.5px;
    font-weight:850;
}

.suggestion-grid{
    display:grid;
    grid-template-columns:repeat(3,minmax(0,1fr));
    gap:10px;
}
.suggestion-card{
    padding:12px;
    border:1px solid var(--line);
    border-radius:18px;
    background:#fff;
    transition:.18s ease;
}
.suggestion-card.is-off{background:#f8fafc;opacity:.68;}
.suggestion-card:hover{transform:translateY(-2px);box-shadow:0 12px 28px rgba(15,23,42,.06);}
.suggestion-card__main{
    display:grid;
    grid-template-columns:36px 1fr auto;
    gap:9px;
    align-items:start;
}
.suggestion-icon{
    width:36px;
    height:36px;
    border-radius:13px;
    background:linear-gradient(135deg,#334155,#64748b);
    font-size:16px;
}
.suggestion-target{margin-top:10px;}
.suggestion-target input{min-height:34px;font-size:14px;}

.ops-grid{
    display:grid;
    grid-template-columns:repeat(2,minmax(0,1fr));
    gap:10px;
}
.ops-item{
    display:grid;
    grid-template-columns:38px 1fr auto;
    gap:9px;
    align-items:center;
    padding:12px;
    border:1px solid var(--line);
    border-radius:18px;
    background:#fbfdff;
}
.ops-item.is-off{opacity:.68;background:#f8fafc;}
.ops-icon{
    width:38px;
    height:38px;
    border-radius:13px;
    background:linear-gradient(135deg,#2563eb,#06b6d4);
    font-size:16px;
}
.ops-item--input input{
    width:70px;
    min-height:32px;
    border-radius:11px;
    border:1px solid #dbe6f2;
    padding:0 8px;
    font-weight:900;
}
.note-box textarea{
    width:100%;
    border:1px solid #dbe6f2;
    border-radius:16px;
    padding:10px 12px;
    outline:0;
    resize:vertical;
    color:#0f172a;
}

.sticky-side{
    position:sticky;
    top:14px;
    display:flex;
    flex-direction:column;
    gap:12px;
}
.side-card,.save-card{padding:16px;}
.side-card--dark{
    color:#fff;
    background:
        radial-gradient(circle at top right, rgba(6,182,212,.22), transparent 32%),
        linear-gradient(135deg,#0b1223,#17233f);
    border-color:rgba(255,255,255,.08);
}
.side-card__head{
    display:flex;
    justify-content:space-between;
    gap:10px;
}
.side-card__head span{
    display:block;
    color:#94a3b8;
    font-size:10px;
    font-weight:950;
    letter-spacing:.09em;
    margin-bottom:4px;
}
.side-card__head strong{
    display:block;
    font-size:16px;
    font-weight:950;
}
.pulse-dot{
    width:11px;
    height:11px;
    border-radius:999px;
    background:#22c55e;
    box-shadow:0 0 0 0 rgba(34,197,94,.35);
    animation:pulse 1.5s ease infinite;
}
.score-ring{
    width:145px;
    height:145px;
    margin:16px auto;
    padding:9px;
    border-radius:999px;
    background:conic-gradient(#22c55e,#06b6d4,#2563eb,#22c55e);
    animation:spin 8s linear infinite;
}
.score-ring > div{
    width:100%;
    height:100%;
    border-radius:999px;
    background:#0d172b;
    display:flex;
    flex-direction:column;
    align-items:center;
    justify-content:center;
}
.score-ring span{
    color:#94a3b8;
    font-size:11px;
    font-weight:850;
}
.score-ring strong{
    font-size:38px;
    line-height:1;
    font-weight:950;
}
.score-ring em{
    color:#67e8f9;
    font-size:11px;
    font-style:normal;
    font-weight:900;
}
.snapshot-list{
    display:grid;
    grid-template-columns:1fr 1fr;
    gap:8px;
}
.snapshot-list > div{
    padding:10px;
    border-radius:14px;
    background:rgba(255,255,255,.08);
    border:1px solid rgba(255,255,255,.08);
}
.snapshot-list span{
    display:block;
    color:#cbd5e1;
    font-size:10.5px;
    font-weight:850;
    margin-bottom:4px;
}
.snapshot-list strong{
    font-size:15px;
    font-weight:950;
}
.snapshot-list .danger strong{color:#fecaca;}
.side-title{
    display:flex;
    align-items:center;
    gap:8px;
    font-size:14px;
    font-weight:950;
    margin-bottom:10px;
}
.side-title i{color:#2563eb;}
.rule-list{
    margin:0;
    padding-left:17px;
    color:#334155;
    font-size:12px;
    line-height:1.9;
}
.rule-list strong{color:#0f172a;}
.save-card strong{
    display:block;
    font-size:13px;
    font-weight:950;
}
.save-card span{
    display:block;
    color:#64748b;
    font-size:11.5px;
    line-height:1.55;
    margin:4px 0 12px;
}
.save-card code{
    font-size:11px;
}
.btn-save{
    min-height:42px;
    border:0;
    border-radius:14px;
    color:#fff;
    font-size:12.5px;
    font-weight:950;
    background:linear-gradient(135deg,#2563eb,#06b6d4);
    box-shadow:0 14px 28px rgba(37,99,235,.2);
}
.btn-save:hover{color:#fff;transform:translateY(-1px);}

@keyframes fadeUp{
    from{opacity:0;transform:translateY(10px);}
    to{opacity:1;transform:translateY(0);}
}
@keyframes floaty{
    0%,100%{transform:translate3d(0,0,0);}
    50%{transform:translate3d(8px,-12px,0);}
}
@keyframes pulse{
    0%{box-shadow:0 0 0 0 rgba(34,197,94,.35);}
    70%{box-shadow:0 0 0 10px rgba(34,197,94,0);}
    100%{box-shadow:0 0 0 0 rgba(34,197,94,0);}
}
@keyframes spin{to{transform:rotate(360deg);}}

@media (max-width:1399.98px){
    .kpi-hero{grid-template-columns:1fr;}
    .kpi-hero__stats{grid-template-columns:repeat(3,1fr);}
    .sticky-side{position:static;}
    .suggestion-grid{grid-template-columns:repeat(2,minmax(0,1fr));}
}
@media (max-width:991.98px){
    .core-grid,.ops-grid,.suggestion-grid{grid-template-columns:1fr;}
    .policy-card__head{flex-direction:column;}
    .kpi-hero__stats{grid-template-columns:1fr;}
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const nf = new Intl.NumberFormat('vi-VN');

    const posts = document.getElementById('posts_target');
    const calls = document.getElementById('calls_target');
    const company = document.getElementById('company_data_target');
    const penalty = document.getElementById('penalty_per_missing');

    const coreSwitches = Array.from(document.querySelectorAll('input[name="enable_posts"], input[name="enable_calls"], input[name="enable_company_data"], input[name="enable_follow_up"]'));
    const suggestedSwitches = Array.from(document.querySelectorAll('[data-suggested-toggle]'));

    function money(value) {
        return nf.format(Math.max(0, parseInt(value || 0, 10))) + 'đ';
    }

    function number(value) {
        return nf.format(Math.max(0, parseInt(value || 0, 10)));
    }

    function setText(id, value) {
        const el = document.getElementById(id);
        if (el) el.textContent = value;
    }

    function recalc() {
        document.querySelectorAll('[data-module-card], [data-suggested-card], .ops-item').forEach(function (card) {
            const checkbox = card.querySelector('input[type="checkbox"]');
            if (!checkbox) return;
            card.classList.toggle('is-on', checkbox.checked);
            card.classList.toggle('is-off', !checkbox.checked);
        });

        const coreOn = coreSwitches.filter(el => el.checked).length;
        const suggestedOn = suggestedSwitches.filter(el => el.checked).length;

        const p = parseInt(posts?.value || 0, 10);
        const c = parseInt(calls?.value || 0, 10);
        const d = parseInt(company?.value || 0, 10);
        const pen = parseInt(penalty?.value || 0, 10);

        const workload = Math.max(0, p + c + d);
        const penaltyEnabled = document.querySelector('input[name="enable_penalty"][type="checkbox"]')?.checked ?? true;
        const maxPenalty = penaltyEnabled ? pen * Math.max(1, coreOn) : 0;

        setText('heroCoreOn', coreOn + '/' + coreSwitches.length);
        setText('sideCoreOn', coreOn);
        setText('heroSuggestedOn', suggestedOn + '/' + suggestedSwitches.length);
        setText('sideSuggestedOn', suggestedOn);
        setText('heroMaxPenalty', money(maxPenalty));
        setText('sideMaxPenalty', money(maxPenalty));
        setText('sideWorkload', number(workload));
        setText('sidePenalty', money(pen));

        const rulePosts = document.querySelector('[data-rule="posts"] strong');
        const ruleCalls = document.querySelector('[data-rule="calls"] strong');
        const ruleCompany = document.querySelector('[data-rule="company"] strong');
        if (rulePosts) rulePosts.textContent = number(p);
        if (ruleCalls) ruleCalls.textContent = number(c);
        if (ruleCompany) ruleCompany.textContent = number(d);

        let score = 96;
        let label = 'Cân bằng';

        if (coreOn === 0) {
            score = 50;
            label = 'Chưa bật core';
        } else if (workload > 230 || pen >= 250000) {
            score = 84;
            label = 'Rất gắt';
        } else if (workload < 80 || pen < 50000) {
            score = 76;
            label = 'Khá nhẹ';
        }

        setText('policyScore', score);
        setText('policyLabel', label);
    }

    document.querySelectorAll('input').forEach(function (el) {
        el.addEventListener('input', recalc);
        el.addEventListener('change', recalc);
    });

    document.querySelectorAll('[data-preset]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            document.querySelectorAll('[data-preset]').forEach(x => x.classList.remove('active'));
            btn.classList.add('active');

            const preset = btn.dataset.preset;
            if (preset === 'light') {
                posts.value = 60;
                calls.value = 35;
                company.value = 10;
                penalty.value = 50000;
            }
            if (preset === 'standard') {
                posts.value = 100;
                calls.value = 60;
                company.value = 20;
                penalty.value = 100000;
            }
            if (preset === 'strict') {
                posts.value = 120;
                calls.value = 80;
                company.value = 30;
                penalty.value = 150000;
            }

            recalc();
        });
    });

    document.getElementById('btnEnableSuggested')?.addEventListener('click', function () {
        suggestedSwitches.forEach(el => el.checked = true);
        recalc();
    });

    document.getElementById('btnDisableSuggested')?.addEventListener('click', function () {
        suggestedSwitches.forEach(el => el.checked = false);
        recalc();
    });

    recalc();
});
</script>
@endsection
