@extends('layouts.app')

@section('title', 'Nhập KPI của tôi')

@section('content')
@php
    $kpiSettings = $targets['settings'] ?? [];
    $enabled = $targets['enabled'] ?? [
        'posts' => true,
        'calls' => true,
        'company_data' => true,
        'follow_up' => true,
    ];

    $isSettingOn = fn($key, $default = false) => (int)($kpiSettings[$key] ?? ($default ? 1 : 0)) === 1;

    $coreModules = collect([
        [
            'key' => 'posts',
            'enabled' => (bool)($enabled['posts'] ?? true),
            'input' => 'posts_count',
            'id' => 'posts_count',
            'title' => 'Bài đăng',
            'desc' => 'Group/Facebook',
            'target' => (int)($targets['posts'] ?? 0),
            'unit' => 'bài',
            'icon' => 'bi-megaphone',
            'tone' => 'blue',
            'value' => old('posts_count', (int)($currentEntry->posts_count ?? 0)),
        ],
        [
            'key' => 'calls',
            'enabled' => (bool)($enabled['calls'] ?? true),
            'input' => 'calls_answered',
            'id' => 'calls_answered',
            'title' => 'Cuộc gọi',
            'desc' => 'Tự nhập số cuộc gọi',
            'target' => (int)($targets['calls'] ?? 0),
            'unit' => 'call',
            'icon' => 'bi-telephone-outbound',
            'tone' => 'cyan',
            'value' => old('calls_answered', (int)($currentEntry->calls_answered ?? 0)),
        ],
        [
            'key' => 'company_data',
            'enabled' => (bool)($enabled['company_data'] ?? true),
            'input' => 'company_data_called',
            'id' => 'company_data_called',
            'title' => 'Data công ty',
            'desc' => 'Data đã gọi/xử lý',
            'target' => (int)($targets['company_data'] ?? 0),
            'unit' => 'data',
            'icon' => 'bi-database-check',
            'tone' => 'violet',
            'value' => old('company_data_called', (int)($currentEntry->company_data_called ?? 0)),
        ],
    ])->filter(fn($m) => $m['enabled'])->values();

    $followEnabled = (bool)($enabled['follow_up'] ?? true);
    $activeExtraModules = collect($kpiExtraModules ?? [])
        ->filter(fn($m) => $isSettingOn($m['enabled_key']))
        ->values();

    $existingFbLinks = '';
    if ($currentEntry && method_exists($currentEntry, 'relationLoaded') && $currentEntry->relationLoaded('postLinks')) {
        $existingFbLinks = $currentEntry->postLinks->pluck('post_url')->filter()->implode("\n");
    }

    $facebookLinksText = old('facebook_links_text', $existingFbLinks);
    $displayUserName = $selectedUser->name ?? $authUser->name;
    $activeCheckCount = $coreModules->count() + ($followEnabled ? 1 : 0) + $activeExtraModules->count();
    $completion = (int)($preview['completion_percent'] ?? 0);
    $missing = (int)($preview['missing_kpi_count'] ?? 0);
    $penalty = (int)($preview['penalty_amount'] ?? 0);

    $extraJsConfig = $activeExtraModules->map(function ($m) use ($kpiSettings) {
        return [
            'key' => $m['metric_key'],
            'target' => (int)($kpiSettings[$m['target_key']] ?? 0),
            'id' => 'extra_' . $m['metric_key'],
        ];
    })->values()->toArray();
@endphp

<div class="container-fluid px-3 px-lg-4 py-3 kpi-entry-pro">
    @if(session('success'))
        <div class="smart-alert smart-alert--success mb-3">
            <i class="bi bi-check-circle"></i>
            <div>
                <strong>Đã lưu KPI</strong>
                <span>{{ session('success') }}</span>
            </div>
        </div>
    @endif

    @if ($errors->any())
        <div class="smart-alert smart-alert--danger mb-3">
            <i class="bi bi-exclamation-triangle"></i>
            <div>
                <strong>Có lỗi khi lưu KPI</strong>
                <ul class="mb-0 ps-3">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        </div>
    @endif

    <section class="entry-hero mb-3">
        <div class="hero-glow hero-glow--one"></div>
        <div class="hero-glow hero-glow--two"></div>

        <div class="entry-hero__main">
            <div class="hero-chip">
                <span></span>
                SALES DAILY KPI
            </div>

            <h1>Nhập KPI của tôi</h1>

            <p>
                Form tự nhập gọn nhẹ. Settings bật hạng mục nào thì form chỉ hiện đúng hạng mục đó.
                Không kéo Callio, không bảng phụ rối mắt, chỉ nhập số liệu và paste link FB.
            </p>

            <div class="hero-meta">
                <div>
                    <i class="bi bi-person-badge"></i>
                    {{ $displayUserName }}
                </div>
                <div>
                    <i class="bi bi-calendar3"></i>
                    {{ \Carbon\Carbon::parse($workDate)->format('d/m/Y') }}
                </div>
                <div>
                    <i class="bi bi-sliders"></i>
                    {{ $activeCheckCount }} KPI đang bật
                </div>
            </div>
        </div>

        <div class="entry-hero__score">
            <div class="score-orbit">
                <div>
                    <span>Hoàn thành</span>
                    <strong id="heroCompletion">{{ $completion }}%</strong>
                    <em id="heroStatus">
                        @if($missing === 0)
                            Đạt KPI
                        @elseif($completion >= 75)
                            Thiếu nhẹ
                        @else
                            Thiếu KPI
                        @endif
                    </em>
                </div>
            </div>

            <div class="hero-score-mini">
                <div>
                    <span>KPI thiếu</span>
                    <strong id="heroMissing">{{ $missing }}</strong>
                </div>
                <div class="danger">
                    <span>Khấu trừ</span>
                    <strong id="heroPenalty">{{ number_format($penalty, 0, ',', '.') }}đ</strong>
                </div>
            </div>
        </div>
    </section>

    <form method="GET" action="{{ route('sales.kpi.my') }}" class="filter-strip mb-3">
        <div class="filter-field">
            <label>Ngày KPI</label>
            <div>
                <i class="bi bi-calendar3"></i>
                <input type="date" name="work_date" value="{{ $workDate }}">
            </div>
        </div>

        <div class="filter-field">
            <label>Nhân viên</label>
            <div>
                <i class="bi bi-person"></i>
                @if($canSelectSalesUser)
                    <select name="user_id">
                        @foreach($salesOptions as $item)
                            <option value="{{ $item->id }}" {{ (string)$selectedUserId === (string)$item->id ? 'selected' : '' }}>
                                {{ $item->name }}
                            </option>
                        @endforeach
                    </select>
                @else
                    <input type="text" value="{{ $displayUserName }}" readonly>
                @endif
            </div>
        </div>

        <button class="btn btn-filter">
            <i class="bi bi-arrow-repeat me-1"></i>Tải ngày này
        </button>
    </form>

    <div class="row g-3">
        <div class="col-xxl-8">
            <form method="POST" action="{{ route('sales.kpi.my.store') }}" id="dailyKpiForm" class="entry-card">
                @csrf
                <input type="hidden" name="work_date" value="{{ $workDate }}">
                @if($canSelectSalesUser)
                    <input type="hidden" name="user_id" value="{{ $selectedUserId }}">
                @endif

                <div class="entry-card__head">
                    <div>
                        <div class="section-chip">SMART INPUT</div>
                        <h2>Số liệu công việc trong ngày</h2>
                        <p>Tất cả chỉ tiêu đều nhập tay. Hạng mục không bật trong Settings sẽ tự ẩn khỏi form.</p>
                    </div>

                    <button class="btn btn-save-top">
                        <i class="bi bi-lightning-charge-fill me-1"></i>Lưu KPI
                    </button>
                </div>

                @if($coreModules->count() || $followEnabled)
                    <div class="mini-section-title">KPI chính</div>

                    <div class="compact-metric-grid">
                        @foreach($coreModules as $module)
                            <div class="compact-metric compact-metric--{{ $module['tone'] }}">
                                <div class="compact-metric__top">
                                    <div class="metric-icon">
                                        <i class="bi {{ $module['icon'] }}"></i>
                                    </div>
                                    <div>
                                        <strong>{{ $module['title'] }}</strong>
                                        <span>{{ $module['desc'] }}</span>
                                    </div>
                                </div>

                                <div class="smart-input">
                                    <input
                                        type="number"
                                        min="0"
                                        name="{{ $module['input'] }}"
                                        id="{{ $module['id'] }}"
                                        value="{{ $module['value'] }}"
                                        data-core-input="{{ $module['key'] }}"
                                    >
                                    <em>/ {{ number_format($module['target'], 0, ',', '.') }} {{ $module['unit'] }}</em>
                                </div>
                            </div>
                        @endforeach

                        @if($followEnabled)
                            <div class="compact-metric compact-metric--green">
                                <div class="compact-metric__top">
                                    <div class="metric-icon">
                                        <i class="bi bi-chat-dots"></i>
                                    </div>
                                    <div>
                                        <strong>Follow up</strong>
                                        <span>Khách hôm trước</span>
                                    </div>
                                </div>

                                <div class="smart-select">
                                    <select name="followed_up_all_previous" id="followed_up_all_previous">
                                        <option value="1" {{ old('followed_up_all_previous', (int)($currentEntry->followed_up_all_previous ?? 0)) == 1 ? 'selected' : '' }}>
                                            Đã follow đủ
                                        </option>
                                        <option value="0" {{ old('followed_up_all_previous', (int)($currentEntry->followed_up_all_previous ?? 0)) == 0 ? 'selected' : '' }}>
                                            Chưa hoàn thành
                                        </option>
                                    </select>
                                </div>
                            </div>
                        @endif
                    </div>
                @endif

                @if($activeExtraModules->count())
                    <div class="mini-section-title mt-3">
                        KPI mở rộng từ Settings
                        <span>{{ $activeExtraModules->count() }} mục</span>
                    </div>

                    <div class="compact-metric-grid compact-metric-grid--extra">
                        @foreach($activeExtraModules as $module)
                            @php
                                $metricKey = $module['metric_key'];
                                $targetKey = $module['target_key'];
                                $targetValue = (int)($kpiSettings[$targetKey] ?? 0);
                                $currentValue = old("extra_metrics.$metricKey", (int)($extraMetrics[$metricKey] ?? 0));
                            @endphp

                            <div class="compact-metric compact-metric--dark">
                                <div class="compact-metric__top">
                                    <div class="metric-icon">
                                        <i class="bi {{ $module['icon'] }}"></i>
                                    </div>
                                    <div>
                                        <strong>{{ $module['title'] }}</strong>
                                        <span>{{ $module['description'] }}</span>
                                    </div>
                                </div>

                                <div class="smart-input">
                                    <input
                                        type="number"
                                        min="0"
                                        name="extra_metrics[{{ $metricKey }}]"
                                        id="extra_{{ $metricKey }}"
                                        value="{{ $currentValue }}"
                                        data-extra-input="{{ $metricKey }}"
                                    >
                                    <em>/ {{ number_format($targetValue, 0, ',', '.') }} {{ $module['unit'] }}</em>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif

                <div class="mini-section-title mt-3">Link & ghi chú</div>

                <div class="two-col-inputs">
                    <div class="text-panel">
                        <div class="text-panel__head">
                            <div>
                                <strong>Link Facebook đã đăng</strong>
                                <span>Paste nhiều link vào 1 ô, mỗi link 1 dòng.</span>
                            </div>
                            <em id="fbLinkCount">0 link</em>
                        </div>

                        <textarea
                            name="facebook_links_text"
                            id="facebook_links_text"
                            rows="6"
                            placeholder="https://www.facebook.com/...
https://www.facebook.com/...
https://www.facebook.com/..."
                        >{{ $facebookLinksText }}</textarea>
                    </div>

                    <div class="text-panel">
                        <div class="text-panel__head">
                            <div>
                                <strong>Ghi chú công việc</strong>
                                <span>Tóm tắt nhanh vấn đề, khách tiềm năng, việc cần bám tiếp.</span>
                            </div>
                        </div>

                        <textarea
                            name="notes"
                            rows="6"
                            placeholder="Ví dụ: Khách A cần báo giá 10kW, khách B hẹn gọi lại..."
                        >{{ old('notes', $currentEntry->notes ?? '') }}</textarea>
                    </div>
                </div>

                <div class="bottom-action-bar">
                    <a href="{{ route('sales.kpi.my', ['work_date' => $workDate]) }}" class="btn btn-soft">
                        <i class="bi bi-arrow-clockwise me-1"></i>Làm mới
                    </a>

                    <button class="btn btn-main-save">
                        <i class="bi bi-save2 me-1"></i>Lưu KPI hôm nay
                    </button>
                </div>
            </form>
        </div>

        <div class="col-xxl-4">
            <div class="side-stack">
                <div class="side-card side-card--preview">
                    <div class="side-card__head">
                        <div>
                            <span>LIVE CHECK</span>
                            <strong>Đánh giá tự động</strong>
                        </div>
                        <div class="pulse-dot"></div>
                    </div>

                    <div class="progress-shell">
                        <div class="progress-line">
                            <div id="previewBar" style="width: {{ min(100, $completion) }}%"></div>
                        </div>
                        <div class="progress-meta">
                            <strong id="previewCompletion">{{ $completion }}%</strong>
                            <span id="previewStatus">
                                @if($missing === 0)
                                    Đạt KPI
                                @elseif($completion >= 75)
                                    Thiếu nhẹ
                                @else
                                    Thiếu KPI
                                @endif
                            </span>
                        </div>
                    </div>

                    <div class="preview-grid">
                        <div>
                            <span>KPI đang bật</span>
                            <strong id="previewTotal">{{ $activeCheckCount }}</strong>
                        </div>
                        <div>
                            <span>KPI thiếu</span>
                            <strong id="previewMissing">{{ $missing }}</strong>
                        </div>
                        <div class="danger">
                            <span>Khấu trừ</span>
                            <strong id="previewPenalty">{{ number_format($penalty, 0, ',', '.') }}đ</strong>
                        </div>
                        <div>
                            <span>FB links</span>
                            <strong id="previewLinks">0</strong>
                        </div>
                    </div>
                </div>

                <div class="side-card">
                    <div class="side-title">
                        <i class="bi bi-sliders"></i>
                        Chính sách đang yêu cầu
                    </div>

                    <div class="policy-list">
                        @foreach($coreModules as $module)
                            <div>
                                <span>{{ $module['title'] }}</span>
                                <strong>{{ number_format($module['target'], 0, ',', '.') }} {{ $module['unit'] }}</strong>
                            </div>
                        @endforeach

                        @if($followEnabled)
                            <div>
                                <span>Follow up</span>
                                <strong>Bắt buộc</strong>
                            </div>
                        @endif

                        @foreach($activeExtraModules as $module)
                            <div>
                                <span>{{ $module['title'] }}</span>
                                <strong>{{ number_format((int)($kpiSettings[$module['target_key']] ?? 0), 0, ',', '.') }} {{ $module['unit'] }}</strong>
                            </div>
                        @endforeach
                    </div>
                </div>

                <div class="side-card">
                    <div class="side-title">
                        <i class="bi bi-clock-history"></i>
                        Lịch sử gần đây
                    </div>

                    <div class="history-mini">
                        @forelse($history as $row)
                            <div class="history-item">
                                <div>
                                    <strong>{{ \Carbon\Carbon::parse($row->work_date)->format('d/m') }}</strong>
                                    <span>{{ (int)$row->completion_percent }}% hoàn thành</span>
                                </div>

                                @if($row->status === 'completed')
                                    <em class="ok">Đạt</em>
                                @elseif($row->status === 'warning')
                                    <em class="warn">Thiếu nhẹ</em>
                                @else
                                    <em class="bad">Thiếu</em>
                                @endif
                            </div>
                        @empty
                            <div class="empty-mini">
                                <i class="bi bi-inbox"></i>
                                Chưa có dữ liệu KPI.
                            </div>
                        @endforelse
                    </div>

                    <div class="mt-2">
                        {{ $history->links() }}
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.kpi-entry-pro{
    --text:#0f172a;
    --muted:#64748b;
    --line:#e6edf6;
    --panel:#ffffff;
    --blue:#2563eb;
    --cyan:#06b6d4;
    --violet:#7c3aed;
    --green:#16a34a;
    --red:#dc2626;
    font-size:12.5px;
    color:var(--text);
}

.kpi-entry-pro input,
.kpi-entry-pro select,
.kpi-entry-pro textarea,
.kpi-entry-pro button{
    font-size:12.5px;
}

.smart-alert{
    display:flex;
    gap:10px;
    align-items:flex-start;
    padding:11px 13px;
    border-radius:16px;
    background:#fff;
    border:1px solid var(--line);
    box-shadow:0 10px 30px rgba(15,23,42,.055);
    animation:dropIn .36s ease both;
}
.smart-alert i{font-size:18px;}
.smart-alert strong{display:block;font-size:13px;font-weight:900;}
.smart-alert span,.smart-alert li{font-size:12px;color:var(--muted);}
.smart-alert--success i{color:#16a34a;}
.smart-alert--danger i{color:#dc2626;}

.entry-hero{
    position:relative;
    overflow:hidden;
    display:grid;
    grid-template-columns:1.45fr .7fr;
    gap:18px;
    padding:22px;
    border-radius:26px;
    background:
        radial-gradient(circle at 8% 15%, rgba(37,99,235,.36), transparent 32%),
        radial-gradient(circle at 80% 18%, rgba(6,182,212,.24), transparent 28%),
        linear-gradient(135deg,#071225 0%,#101d37 52%,#15345e 100%);
    color:#fff;
    box-shadow:0 24px 70px rgba(15,23,42,.2);
    animation:heroIn .48s ease both;
}
.entry-hero:before{
    content:"";
    position:absolute;
    inset:0;
    background:linear-gradient(110deg, transparent 0%, rgba(255,255,255,.12) 25%, transparent 48%);
    transform:translateX(-70%);
    animation:heroShine 5.8s ease-in-out infinite;
}
.hero-glow{
    position:absolute;
    border-radius:999px;
    filter:blur(2px);
    opacity:.7;
    animation:floaty 7s ease-in-out infinite;
}
.hero-glow--one{width:90px;height:90px;right:35%;top:24px;background:rgba(6,182,212,.18);}
.hero-glow--two{width:130px;height:130px;right:50px;bottom:16px;background:rgba(124,58,237,.15);animation-delay:-3s;}
.entry-hero__main,.entry-hero__score{position:relative;z-index:2;}
.hero-chip{
    display:inline-flex;
    align-items:center;
    gap:8px;
    padding:7px 10px;
    border-radius:999px;
    border:1px solid rgba(255,255,255,.13);
    background:rgba(255,255,255,.08);
    color:#dbeafe;
    font-size:10px;
    font-weight:950;
    letter-spacing:.09em;
}
.hero-chip span{
    width:7px;
    height:7px;
    border-radius:999px;
    background:#22c55e;
    box-shadow:0 0 0 6px rgba(34,197,94,.13);
}
.entry-hero h1{
    margin:13px 0 8px;
    font-size:34px;
    line-height:1.05;
    letter-spacing:-.04em;
    font-weight:950;
}
.entry-hero p{
    max-width:760px;
    margin:0;
    color:#cbd5e1;
    font-size:12.5px;
    line-height:1.7;
}
.hero-meta{
    display:flex;
    flex-wrap:wrap;
    gap:8px;
    margin-top:15px;
}
.hero-meta div{
    display:inline-flex;
    align-items:center;
    gap:6px;
    padding:8px 10px;
    border-radius:999px;
    background:rgba(255,255,255,.08);
    border:1px solid rgba(255,255,255,.1);
    color:#e2e8f0;
    font-size:11.5px;
    font-weight:800;
}
.entry-hero__score{
    display:flex;
    flex-direction:column;
    justify-content:center;
    gap:12px;
}
.score-orbit{
    width:150px;
    height:150px;
    margin:0 auto;
    padding:9px;
    border-radius:999px;
    background:conic-gradient(from 180deg,#22c55e,#06b6d4,#2563eb,#22c55e);
    animation:spin 8s linear infinite;
}
.score-orbit > div{
    width:100%;
    height:100%;
    border-radius:999px;
    background:#0b1428;
    display:flex;
    flex-direction:column;
    align-items:center;
    justify-content:center;
}
.score-orbit span{font-size:11px;color:#94a3b8;font-weight:850;}
.score-orbit strong{font-size:36px;line-height:1;font-weight:950;}
.score-orbit em{font-size:11px;color:#67e8f9;font-style:normal;font-weight:950;margin-top:4px;}
.hero-score-mini{
    display:grid;
    grid-template-columns:1fr 1fr;
    gap:8px;
}
.hero-score-mini div{
    padding:11px;
    border-radius:15px;
    background:rgba(255,255,255,.08);
    border:1px solid rgba(255,255,255,.1);
}
.hero-score-mini span{display:block;color:#cbd5e1;font-size:10.5px;font-weight:850;margin-bottom:4px;}
.hero-score-mini strong{font-size:15px;font-weight:950;}
.hero-score-mini .danger strong{color:#fecaca;}

.filter-strip{
    display:grid;
    grid-template-columns:1fr 1fr 160px;
    gap:10px;
    padding:12px;
    border-radius:20px;
    background:#fff;
    border:1px solid var(--line);
    box-shadow:0 12px 34px rgba(15,23,42,.055);
}
.filter-field label{
    display:block;
    margin-bottom:5px;
    color:#475569;
    font-size:11px;
    font-weight:900;
}
.filter-field div{
    position:relative;
}
.filter-field i{
    position:absolute;
    left:12px;
    top:50%;
    transform:translateY(-50%);
    color:#64748b;
}
.filter-field input,
.filter-field select{
    width:100%;
    min-height:38px;
    border:1px solid #dbe6f2;
    border-radius:14px;
    padding:0 12px 0 35px;
    outline:0;
    background:#fbfdff;
    color:#0f172a;
    font-weight:850;
}
.btn-filter{
    align-self:end;
    min-height:38px;
    border:0;
    border-radius:14px;
    color:#fff;
    font-weight:900;
    background:linear-gradient(135deg,#2563eb,#06b6d4);
}

.entry-card,.side-card{
    background:#fff;
    border:1px solid var(--line);
    border-radius:24px;
    box-shadow:0 16px 48px rgba(15,23,42,.06);
}
.entry-card{
    padding:18px;
    animation:fadeUp .42s ease both;
}
.entry-card__head{
    display:flex;
    align-items:flex-start;
    justify-content:space-between;
    gap:12px;
    margin-bottom:15px;
}
.section-chip{
    display:inline-flex;
    padding:5px 8px;
    border-radius:999px;
    background:#eef4ff;
    color:#1d4ed8;
    font-size:10px;
    font-weight:950;
    letter-spacing:.08em;
    margin-bottom:7px;
}
.entry-card h2{
    margin:0 0 5px;
    font-size:17px;
    font-weight:950;
    letter-spacing:-.015em;
}
.entry-card p{
    margin:0;
    color:var(--muted);
    font-size:12px;
}
.btn-save-top{
    min-height:36px;
    border:0;
    border-radius:13px;
    color:#fff;
    padding:0 12px;
    font-weight:950;
    background:linear-gradient(135deg,#0f172a,#2563eb);
}
.mini-section-title{
    display:flex;
    align-items:center;
    gap:8px;
    color:#0f172a;
    font-size:12px;
    font-weight:950;
    margin-bottom:9px;
}
.mini-section-title span{
    padding:4px 8px;
    border-radius:999px;
    background:#f1f5f9;
    color:#475569;
    font-size:10.5px;
}

.compact-metric-grid{
    display:grid;
    grid-template-columns:repeat(4,minmax(0,1fr));
    gap:10px;
}
.compact-metric-grid--extra{
    grid-template-columns:repeat(3,minmax(0,1fr));
}
.compact-metric{
    position:relative;
    overflow:hidden;
    padding:12px;
    border-radius:19px;
    border:1px solid var(--line);
    background:linear-gradient(180deg,#fff,#fbfdff);
    transition:.22s ease;
}
.compact-metric:hover{
    transform:translateY(-2px);
    box-shadow:0 16px 34px rgba(15,23,42,.07);
}
.compact-metric:after{
    content:"";
    position:absolute;
    width:80px;
    height:80px;
    right:-35px;
    bottom:-42px;
    border-radius:999px;
    opacity:.1;
}
.compact-metric--blue:after{background:#2563eb;}
.compact-metric--cyan:after{background:#06b6d4;}
.compact-metric--violet:after{background:#7c3aed;}
.compact-metric--green:after{background:#16a34a;}
.compact-metric--dark:after{background:#0f172a;}
.compact-metric__top{
    display:grid;
    grid-template-columns:34px 1fr;
    gap:9px;
    align-items:start;
    margin-bottom:10px;
}
.metric-icon{
    width:34px;
    height:34px;
    border-radius:13px;
    display:flex;
    align-items:center;
    justify-content:center;
    color:#fff;
    font-size:15px;
    background:linear-gradient(135deg,#2563eb,#06b6d4);
}
.compact-metric--violet .metric-icon{background:linear-gradient(135deg,#7c3aed,#a78bfa);}
.compact-metric--green .metric-icon{background:linear-gradient(135deg,#16a34a,#4ade80);}
.compact-metric--dark .metric-icon{background:linear-gradient(135deg,#0f172a,#475569);}
.compact-metric strong{
    display:block;
    font-size:12.5px;
    font-weight:950;
    margin-bottom:2px;
}
.compact-metric span{
    display:block;
    color:#64748b;
    font-size:11px;
    line-height:1.35;
    font-weight:650;
}
.smart-input{
    display:grid;
    grid-template-columns:1fr auto;
    align-items:center;
    overflow:hidden;
    border:1px solid #dbe6f2;
    border-radius:14px;
    background:#fff;
}
.smart-input input{
    width:100%;
    min-height:36px;
    border:0;
    outline:0;
    padding:0 10px;
    color:#0f172a;
    font-size:16px;
    font-weight:950;
    background:transparent;
}
.smart-input em{
    padding:0 9px;
    color:#64748b;
    font-size:10.5px;
    font-style:normal;
    font-weight:850;
    white-space:nowrap;
}
.smart-select select{
    width:100%;
    min-height:36px;
    border:1px solid #dbe6f2;
    border-radius:14px;
    padding:0 10px;
    color:#0f172a;
    font-weight:900;
    outline:0;
    background:#fff;
}

.two-col-inputs{
    display:grid;
    grid-template-columns:1fr 1fr;
    gap:10px;
}
.text-panel{
    border:1px solid var(--line);
    border-radius:19px;
    padding:12px;
    background:#fbfdff;
}
.text-panel__head{
    display:flex;
    align-items:flex-start;
    justify-content:space-between;
    gap:10px;
    margin-bottom:8px;
}
.text-panel strong{
    display:block;
    font-size:12.5px;
    font-weight:950;
}
.text-panel span{
    display:block;
    color:#64748b;
    font-size:11px;
    margin-top:2px;
}
.text-panel em{
    flex:0 0 auto;
    padding:4px 8px;
    border-radius:999px;
    background:#eef4ff;
    color:#1d4ed8;
    font-size:10.5px;
    font-style:normal;
    font-weight:900;
}
.text-panel textarea{
    width:100%;
    border:1px solid #dbe6f2;
    border-radius:15px;
    padding:10px;
    outline:0;
    resize:vertical;
    background:#fff;
    color:#0f172a;
    line-height:1.5;
}

.bottom-action-bar{
    display:flex;
    align-items:center;
    justify-content:flex-end;
    gap:9px;
    margin-top:14px;
    padding-top:14px;
    border-top:1px solid var(--line);
}
.btn-soft{
    min-height:39px;
    display:inline-flex;
    align-items:center;
    border:0;
    border-radius:14px;
    padding:0 13px;
    color:#0f172a;
    font-weight:900;
    background:#eef2f7;
}
.btn-main-save{
    min-height:39px;
    display:inline-flex;
    align-items:center;
    border:0;
    border-radius:14px;
    padding:0 15px;
    color:#fff;
    font-weight:950;
    background:linear-gradient(135deg,#2563eb,#06b6d4);
    box-shadow:0 14px 28px rgba(37,99,235,.2);
}

.side-stack{
    position:sticky;
    top:14px;
    display:flex;
    flex-direction:column;
    gap:10px;
}
.side-card{
    padding:15px;
}
.side-card--preview{
    color:#fff;
    background:
        radial-gradient(circle at top right, rgba(6,182,212,.22), transparent 34%),
        linear-gradient(135deg,#0b1223,#17233f);
    border-color:rgba(255,255,255,.08);
}
.side-card__head{
    display:flex;
    justify-content:space-between;
    align-items:flex-start;
    gap:10px;
    margin-bottom:14px;
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
    font-size:16px;
    font-weight:950;
}
.pulse-dot{
    width:11px;
    height:11px;
    border-radius:999px;
    background:#22c55e;
    animation:pulse 1.5s ease infinite;
}
.progress-shell{
    padding:12px;
    border-radius:17px;
    background:rgba(255,255,255,.08);
    border:1px solid rgba(255,255,255,.08);
}
.progress-line{
    height:9px;
    border-radius:999px;
    background:rgba(255,255,255,.14);
    overflow:hidden;
}
.progress-line div{
    height:100%;
    width:0;
    border-radius:999px;
    background:linear-gradient(90deg,#22c55e,#06b6d4,#60a5fa);
    transition:.25s ease;
}
.progress-meta{
    display:flex;
    justify-content:space-between;
    align-items:center;
    margin-top:9px;
}
.progress-meta strong{font-size:22px;font-weight:950;}
.progress-meta span{font-size:11px;color:#67e8f9;font-weight:950;}
.preview-grid{
    display:grid;
    grid-template-columns:1fr 1fr;
    gap:8px;
    margin-top:10px;
}
.preview-grid div{
    padding:10px;
    border-radius:14px;
    background:rgba(255,255,255,.08);
    border:1px solid rgba(255,255,255,.08);
}
.preview-grid span{
    display:block;
    color:#cbd5e1;
    font-size:10.5px;
    font-weight:850;
    margin-bottom:4px;
}
.preview-grid strong{
    font-size:15px;
    font-weight:950;
}
.preview-grid .danger strong{color:#fecaca;}
.side-title{
    display:flex;
    align-items:center;
    gap:8px;
    font-size:14px;
    font-weight:950;
    margin-bottom:10px;
}
.side-title i{color:#2563eb;}
.policy-list{
    display:flex;
    flex-direction:column;
    gap:7px;
}
.policy-list div{
    display:flex;
    justify-content:space-between;
    gap:8px;
    padding:8px 9px;
    border-radius:13px;
    background:#fbfdff;
    border:1px solid var(--line);
}
.policy-list span{
    color:#64748b;
    font-size:11.5px;
    font-weight:800;
}
.policy-list strong{
    color:#0f172a;
    font-size:11.5px;
    font-weight:950;
    text-align:right;
}
.history-mini{
    display:flex;
    flex-direction:column;
    gap:7px;
}
.history-item{
    display:flex;
    align-items:center;
    justify-content:space-between;
    gap:8px;
    padding:8px 9px;
    border:1px solid var(--line);
    border-radius:13px;
    background:#fbfdff;
}
.history-item strong{
    display:block;
    font-size:12px;
    font-weight:950;
}
.history-item span{
    display:block;
    color:#64748b;
    font-size:11px;
}
.history-item em{
    flex:0 0 auto;
    padding:4px 7px;
    border-radius:999px;
    font-style:normal;
    font-size:10.5px;
    font-weight:950;
}
.history-item .ok{background:#dcfce7;color:#166534;}
.history-item .warn{background:#fef3c7;color:#92400e;}
.history-item .bad{background:#fee2e2;color:#991b1b;}
.empty-mini{
    display:flex;
    align-items:center;
    justify-content:center;
    gap:7px;
    min-height:80px;
    border:1px dashed #d8e2ee;
    border-radius:14px;
    color:#64748b;
    font-size:12px;
    font-weight:850;
}

@keyframes heroIn{
    from{opacity:0;transform:translateY(10px) scale(.99);}
    to{opacity:1;transform:translateY(0) scale(1);}
}
@keyframes fadeUp{
    from{opacity:0;transform:translateY(12px);}
    to{opacity:1;transform:translateY(0);}
}
@keyframes dropIn{
    from{opacity:0;transform:translateY(-8px);}
    to{opacity:1;transform:translateY(0);}
}
@keyframes heroShine{
    0%,100%{transform:translateX(-70%);}
    48%{transform:translateX(70%);}
}
@keyframes floaty{
    0%,100%{transform:translate3d(0,0,0);}
    50%{transform:translate3d(8px,-12px,0);}
}
@keyframes spin{to{transform:rotate(360deg);}}
@keyframes pulse{
    0%{box-shadow:0 0 0 0 rgba(34,197,94,.35);}
    70%{box-shadow:0 0 0 10px rgba(34,197,94,0);}
    100%{box-shadow:0 0 0 0 rgba(34,197,94,0);}
}

@media (max-width:1399.98px){
    .entry-hero{grid-template-columns:1fr;}
    .entry-hero__score{max-width:420px;}
    .compact-metric-grid,.compact-metric-grid--extra{grid-template-columns:repeat(2,minmax(0,1fr));}
    .side-stack{position:static;}
}
@media (max-width:991.98px){
    .filter-strip{grid-template-columns:1fr;}
    .compact-metric-grid,.compact-metric-grid--extra,.two-col-inputs{grid-template-columns:1fr;}
    .entry-card__head{flex-direction:column;}
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const nf = new Intl.NumberFormat('vi-VN');

    const config = {
        penalty: {{ (int)($targets['penalty_per_missing'] ?? 0) }},
        penaltyEnabled: @json((int)($kpiSettings['enable_penalty'] ?? 1) === 1),
        core: {
            posts: {
                enabled: @json((bool)($enabled['posts'] ?? true)),
                target: {{ (int)($targets['posts'] ?? 0) }},
                id: 'posts_count'
            },
            calls: {
                enabled: @json((bool)($enabled['calls'] ?? true)),
                target: {{ (int)($targets['calls'] ?? 0) }},
                id: 'calls_answered'
            },
            company_data: {
                enabled: @json((bool)($enabled['company_data'] ?? true)),
                target: {{ (int)($targets['company_data'] ?? 0) }},
                id: 'company_data_called'
            },
            follow_up: {
                enabled: @json($followEnabled),
                id: 'followed_up_all_previous'
            }
        },
        extra: @json($extraJsConfig)
    };

    function money(value) {
        return nf.format(Math.max(0, parseInt(value || 0, 10))) + 'đ';
    }

    function val(id) {
        const el = document.getElementById(id);
        return Math.max(0, parseInt(el?.value || 0, 10));
    }

    function setText(id, value) {
        const el = document.getElementById(id);
        if (el) el.textContent = value;
    }

    function countLinks() {
        const box = document.getElementById('facebook_links_text');
        const links = (box?.value || '')
            .split(/\r?\n/)
            .map(x => x.trim())
            .filter(Boolean);

        setText('fbLinkCount', links.length + ' link');
        setText('previewLinks', links.length);

        return links.length;
    }

    function recalc() {
        let passed = 0;
        let total = 0;

        if (config.core.posts.enabled) {
            total++;
            if (val(config.core.posts.id) >= config.core.posts.target) passed++;
        }

        if (config.core.calls.enabled) {
            total++;
            if (val(config.core.calls.id) >= config.core.calls.target) passed++;
        }

        if (config.core.company_data.enabled) {
            total++;
            if (val(config.core.company_data.id) >= config.core.company_data.target) passed++;
        }

        if (config.core.follow_up.enabled) {
            total++;
            const follow = parseInt(document.getElementById(config.core.follow_up.id)?.value || 0, 10) === 1;
            if (follow) passed++;
        }

        config.extra.forEach(function (item) {
            total++;
            if (val(item.id) >= parseInt(item.target || 0, 10)) passed++;
        });

        const missing = total > 0 ? Math.max(0, total - passed) : 0;
        const completion = total > 0 ? Math.round((passed / total) * 100) : 100;
        const penalty = config.penaltyEnabled ? missing * config.penalty : 0;

        let status = 'Đạt KPI';
        if (missing > 0 && completion >= 75) status = 'Thiếu nhẹ';
        if (missing > 0 && completion < 75) status = 'Thiếu KPI';

        setText('heroCompletion', completion + '%');
        setText('heroStatus', status);
        setText('heroMissing', missing);
        setText('heroPenalty', money(penalty));

        setText('previewCompletion', completion + '%');
        setText('previewStatus', status);
        setText('previewMissing', missing);
        setText('previewPenalty', money(penalty));
        setText('previewTotal', total);

        const bar = document.getElementById('previewBar');
        if (bar) bar.style.width = Math.min(100, completion) + '%';

        countLinks();
    }

    document.querySelectorAll('#dailyKpiForm input, #dailyKpiForm select, #dailyKpiForm textarea').forEach(function (el) {
        el.addEventListener('input', recalc);
        el.addEventListener('change', recalc);
    });

    countLinks();
    recalc();
});
</script>
@endsection
