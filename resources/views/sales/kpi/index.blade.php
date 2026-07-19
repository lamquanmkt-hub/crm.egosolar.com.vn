@extends('layouts.app')

@section('title', 'Sales KPI Performance Center')

@section('content')
@php
    $kpiSettings = $targets['settings'] ?? [];
    $enabled = $targets['enabled'] ?? [
        'posts' => true,
        'calls' => true,
        'company_data' => true,
        'follow_up' => true,
    ];

    $monthLabel = \Carbon\Carbon::createFromFormat('Y-m', $month)->format('m/Y');
    $workDateLabel = \Carbon\Carbon::parse($workDate)->format('d/m/Y');

    $avgCompletion = round((float)($monthlyOverview->avg_completion ?? 0));
    $totalMissing = (int)($monthlyOverview->total_missing_kpi ?? 0);
    $totalPenalty = (int)($monthlyOverview->total_penalty ?? 0);

    $activeCore = collect([
        ['key' => 'posts', 'label' => 'Bài đăng', 'target' => (int)($targets['posts'] ?? 0), 'unit' => 'bài', 'enabled' => (bool)($enabled['posts'] ?? true), 'icon' => 'bi-megaphone'],
        ['key' => 'calls', 'label' => 'Cuộc gọi', 'target' => (int)($targets['calls'] ?? 0), 'unit' => 'call', 'enabled' => (bool)($enabled['calls'] ?? true), 'icon' => 'bi-telephone-outbound'],
        ['key' => 'company_data', 'label' => 'Data công ty', 'target' => (int)($targets['company_data'] ?? 0), 'unit' => 'data', 'enabled' => (bool)($enabled['company_data'] ?? true), 'icon' => 'bi-database-check'],
        ['key' => 'follow_up', 'label' => 'Follow up', 'target' => null, 'unit' => 'bắt buộc', 'enabled' => (bool)($enabled['follow_up'] ?? true), 'icon' => 'bi-chat-dots'],
    ])->filter(fn($m) => $m['enabled'])->values();

    $extraModules = collect([
        ['enabled_key' => 'enable_new_leads', 'target_key' => 'new_leads_target', 'label' => 'Lead mới', 'unit' => 'lead', 'icon' => 'bi-person-plus'],
        ['enabled_key' => 'enable_quotes', 'target_key' => 'quotes_target', 'label' => 'Báo giá', 'unit' => 'báo giá', 'icon' => 'bi-file-earmark-text'],
        ['enabled_key' => 'enable_customer_care', 'target_key' => 'customer_care_target', 'label' => 'Chăm sóc khách', 'unit' => 'khách', 'icon' => 'bi-heart'],
        ['enabled_key' => 'enable_meetings', 'target_key' => 'meetings_target', 'label' => 'Meeting', 'unit' => 'lịch', 'icon' => 'bi-calendar2-check'],
        ['enabled_key' => 'enable_zalo_messages', 'target_key' => 'zalo_messages_target', 'label' => 'Tin nhắn Zalo', 'unit' => 'tin', 'icon' => 'bi-send'],
        ['enabled_key' => 'enable_debt_follow', 'target_key' => 'debt_follow_target', 'label' => 'Follow công nợ', 'unit' => 'case', 'icon' => 'bi-wallet2'],
        ['enabled_key' => 'enable_order_follow', 'target_key' => 'order_follow_target', 'label' => 'Bám đơn hàng', 'unit' => 'đơn', 'icon' => 'bi-box-seam'],
        ['enabled_key' => 'enable_technical_coordination', 'target_key' => 'technical_coordination_target', 'label' => 'Phối hợp kỹ thuật', 'unit' => 'việc', 'icon' => 'bi-tools'],
        ['enabled_key' => 'enable_overdue_tasks', 'target_key' => 'overdue_tasks_target', 'label' => 'Task quá hạn', 'unit' => 'task', 'icon' => 'bi-alarm'],
        ['enabled_key' => 'enable_training', 'target_key' => 'training_target', 'label' => 'Học sản phẩm', 'unit' => 'mục', 'icon' => 'bi-mortarboard'],
        ['enabled_key' => 'enable_quality_score', 'target_key' => 'quality_score_target', 'label' => 'Điểm chất lượng', 'unit' => 'điểm', 'icon' => 'bi-stars'],
        ['enabled_key' => 'enable_revenue_pipeline', 'target_key' => 'revenue_pipeline_target', 'label' => 'Pipeline doanh số', 'unit' => 'VNĐ', 'icon' => 'bi-graph-up-arrow'],
    ])->filter(fn($m) => (int)($kpiSettings[$m['enabled_key']] ?? 0) === 1)->values();

    $selectedName = $selectedUserId ? ($selectedUser->name ?? $authUser->name) : 'Tất cả nhân sự';
    $previewCompletion = (int)($preview['completion_percent'] ?? 0);
    $previewMissing = (int)($preview['missing_kpi_count'] ?? 0);
    $previewPenalty = (int)($preview['penalty_amount'] ?? 0);
@endphp

<div class="container-fluid px-3 px-lg-4 py-3 kpi-command-center">

    <section class="kpi-hero mb-3">
        <div class="hero-noise"></div>
        <div class="hero-orb hero-orb--one"></div>
        <div class="hero-orb hero-orb--two"></div>

        <div class="hero-main">
            <div class="hero-chip">
                <span></span>
                SALES PERFORMANCE CENTER
            </div>

            <h1>Dashboard KPI đội Sales</h1>

            <p>
                Trung tâm theo dõi hiệu suất sales theo tháng/ngày: KPI đạt, KPI thiếu, khấu trừ dự kiến,
                ranking nhân sự và snapshot từng người.
            </p>

            <div class="hero-actions">
                <a href="{{ route('sales.kpi.my', ['work_date' => $workDate, 'user_id' => $selectedUserId ?: $authUser->id]) }}" class="btn hero-btn hero-btn--light">
                    <i class="bi bi-pencil-square me-1"></i>
                    @if($selectedUserId)
                        Cập nhật KPI hôm nay
                    @else
                        Cập nhật KPI của tôi
                    @endif
                </a>

                @if($isPrivileged)
                    <a href="{{ route('sales.kpi.settings') }}" class="btn hero-btn hero-btn--ghost">
                        <i class="bi bi-sliders me-1"></i>Cài đặt KPI
                    </a>
                @endif
            </div>
        </div>

        <div class="hero-score">
            <div class="score-ring">
                <div>
                    <span>Team AVG</span>
                    <strong>{{ $avgCompletion }}%</strong>
                    <em>
                        @if($avgCompletion >= 90)
                            Xuất sắc
                        @elseif($avgCompletion >= 75)
                            Ổn định
                        @elseif($avgCompletion > 0)
                            Cần bám sát
                        @else
                            Chưa có dữ liệu
                        @endif
                    </em>
                </div>
            </div>

            <div class="hero-mini">
                <div>
                    <span>Tháng</span>
                    <strong>{{ $monthLabel }}</strong>
                </div>
                <div class="danger">
                    <span>Khấu trừ</span>
                    <strong>{{ number_format($totalPenalty, 0, ',', '.') }}đ</strong>
                </div>
            </div>
        </div>
    </section>

    <form method="GET" action="{{ route('sales.kpi.index') }}" class="filter-console mb-3">
        <div class="filter-field">
            <label>Tháng tổng hợp</label>
            <div>
                <i class="bi bi-calendar2-month"></i>
                <input type="month" name="month" value="{{ $month }}">
            </div>
        </div>

        <div class="filter-field">
            <label>Ngày snapshot</label>
            <div>
                <i class="bi bi-calendar3"></i>
                <input type="date" name="work_date" value="{{ $workDate }}">
            </div>
        </div>

        <div class="filter-field filter-field--wide">
            <label>Nhân viên</label>
            <div>
                <i class="bi bi-person-badge"></i>
                @if($isPrivileged)
                    <select name="user_id">
                        <option value="" {{ empty($selectedUserId) ? "selected" : "" }}>Tất cả nhân sự</option>
                        @foreach($salesOptions as $item)
                            <option value="{{ $item->id }}" {{ (string)$selectedUserId === (string)$item->id ? 'selected' : '' }}>
                                {{ $item->name }}
                                @if(!empty($item->role_names))
                                    — {{ str_replace('_', ' ', ucwords($item->role_names, '_')) }}
                                @endif
                            </option>
                        @endforeach
                    </select>
                @else
                    <input type="text" value="{{ $authUser->name }}" readonly>
                @endif
            </div>
        </div>

        <button class="btn btn-filter">
            <i class="bi bi-funnel me-1"></i>Lọc dashboard
        </button>
    </form>

    <div class="quick-entry-card mb-3">
        <div class="quick-entry-card__left">
            <div class="quick-entry-icon">
                <i class="bi bi-pencil-square"></i>
            </div>
            <div>
                <div class="quick-entry-title">
                    @if($isPrivileged && !$selectedUserId)
                        Đang xem tổng quan toàn bộ nhân sự
                    @elseif($isPrivileged)
                        Cập nhật KPI cho: {{ $selectedName }}
                    @else
                        Nhân viên tự cập nhật KPI của mình tại đây
                    @endif
                </div>
                <div class="quick-entry-sub">
                    @if($isPrivileged && !$selectedUserId)
                        Muốn nhập hộ nhân sự nào thì chọn nhân sự ở dropdown, hoặc bấm nút bên phải để cập nhật KPI của chính bạn.
                    @else
                        Chọn đúng ngày snapshot, sau đó bấm nút bên phải để nhập số liệu công việc, link Facebook và ghi chú.
                    @endif
                </div>
            </div>
        </div>

        <a href="{{ route('sales.kpi.my', ['work_date' => $workDate, 'user_id' => $selectedUserId ?: $authUser->id]) }}" class="btn quick-entry-btn">
            <i class="bi bi-lightning-charge-fill me-1"></i>
            @if($isPrivileged && $selectedUserId)
                Mở form nhập KPI nhân sự này
            @else
                Cập nhật KPI của tôi
            @endif
        </a>
    </div>

    <div class="metric-grid mb-3">
        <div class="metric-card metric-card--blue">
            <div class="metric-card__top">
                <span>Nhân sự có dữ liệu</span>
                <i class="bi bi-people"></i>
            </div>
            <strong data-count="{{ (int)($monthlyOverview->total_staff ?? 0) }}">0</strong>
            <em>Trong tháng {{ $monthLabel }}</em>
        </div>

        <div class="metric-card metric-card--cyan">
            <div class="metric-card__top">
                <span>Tổng bài đăng</span>
                <i class="bi bi-megaphone"></i>
            </div>
            <strong data-count="{{ (int)($monthlyOverview->total_posts ?? 0) }}">0</strong>
            <em>Toàn team</em>
        </div>

        <div class="metric-card metric-card--violet">
            <div class="metric-card__top">
                <span>Cuộc gọi nghe máy</span>
                <i class="bi bi-telephone-outbound"></i>
            </div>
            <strong data-count="{{ (int)($monthlyOverview->total_calls ?? 0) }}">0</strong>
            <em>Tự nhập / tổng hợp</em>
        </div>

        <div class="metric-card metric-card--green">
            <div class="metric-card__top">
                <span>Data công ty</span>
                <i class="bi bi-database-check"></i>
            </div>
            <strong data-count="{{ (int)($monthlyOverview->total_company_data ?? 0) }}">0</strong>
            <em>Data đã gọi/xử lý</em>
        </div>

        <div class="metric-card metric-card--amber">
            <div class="metric-card__top">
                <span>KPI thiếu</span>
                <i class="bi bi-exclamation-triangle"></i>
            </div>
            <strong data-count="{{ $totalMissing }}">0</strong>
            <em>Cộng dồn tháng</em>
        </div>

        <div class="metric-card metric-card--red">
            <div class="metric-card__top">
                <span>Khấu trừ dự kiến</span>
                <i class="bi bi-cash-coin"></i>
            </div>
            <strong>{{ number_format($totalPenalty, 0, ',', '.') }}đ</strong>
            <em>TB hoàn thành {{ $avgCompletion }}%</em>
        </div>
    </div>

    <div class="row g-3">
        <div class="col-xxl-8">
            <div class="panel-pro h-100">
                <div class="panel-pro__head">
                    <div>
                        <div class="section-chip">MONTHLY RANKING</div>
                        <h2>Xếp hạng hiệu suất từng nhân sự</h2>
                        <p>Sắp xếp theo tỷ lệ hoàn thành trung bình trong tháng.</p>
                    </div>

                    <span class="panel-badge">
                        <i class="bi bi-trophy"></i>
                        {{ $staffMonthlyRows->count() }} nhân sự
                    </span>
                </div>

                <div class="table-responsive">
                    <table class="table table-sm align-middle table-pro mb-0">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Nhân sự</th>
                                <th>Bài</th>
                                <th>Call</th>
                                <th>Data</th>
                                <th>Ngày đạt</th>
                                <th>Ngày thiếu</th>
                                <th>Hoàn thành</th>
                                <th>KPI thiếu</th>
                                <th>Khấu trừ</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($staffMonthlyRows as $index => $row)
                                @php
                                    $rowPercent = round((float)($row->avg_completion ?? 0));
                                    $rankClass = $index === 0 ? 'rank-gold' : ($index === 1 ? 'rank-silver' : ($index === 2 ? 'rank-bronze' : ''));
                                @endphp
                                <tr>
                                    <td>
                                        <span class="rank-pill {{ $rankClass }}">{{ $index + 1 }}</span>
                                    </td>
                                    <td>
                                        <div class="person-cell">
                                            <div class="avatar-dot">{{ mb_substr($row->user->name ?? 'N', 0, 1) }}</div>
                                            <div>
                                                <strong>{{ $row->user->name ?? 'N/A' }}</strong>
                                                <span>{{ (int)($row->working_days ?? 0) }} ngày có dữ liệu</span>
                                            </div>
                                        </div>
                                    </td>
                                    <td>{{ number_format((int)($row->total_posts ?? 0), 0, ',', '.') }}</td>
                                    <td>{{ number_format((int)($row->total_calls ?? 0), 0, ',', '.') }}</td>
                                    <td>{{ number_format((int)($row->total_company_data ?? 0), 0, ',', '.') }}</td>
                                    <td><span class="status-pill ok">{{ (int)($row->completed_days ?? 0) }}</span></td>
                                    <td><span class="status-pill bad">{{ (int)($row->incomplete_days ?? 0) }}</span></td>
                                    <td>
                                        <div class="progress-cell">
                                            <div class="progress-track">
                                                <div style="width: {{ min(100, $rowPercent) }}%"></div>
                                            </div>
                                            <strong class="{{ $rowPercent >= 75 ? 'text-success' : 'text-danger' }}">{{ $rowPercent }}%</strong>
                                        </div>
                                    </td>
                                    <td class="fw-bold text-danger">{{ number_format((int)($row->total_missing_kpi ?? 0), 0, ',', '.') }}</td>
                                    <td class="fw-bold text-danger">{{ number_format((int)($row->total_penalty ?? 0), 0, ',', '.') }}đ</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="10">
                                        <div class="empty-state">
                                            <i class="bi bi-inbox"></i>
                                            <strong>Chưa có dữ liệu tháng</strong>
                                            <span>Chọn tháng khác hoặc nhập KPI cho nhân sự.</span>
                                        </div>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-xxl-4">
            <div class="side-stack">
                <div class="panel-pro panel-pro--dark">
                    <div class="panel-pro__head panel-pro__head--dark">
                        <div>
                            <div class="section-chip section-chip--dark">DAILY SNAPSHOT</div>
                            <h2>{{ $workDateLabel }}</h2>
                            <p>Tổng hợp nhanh ngày đang lọc.</p>
                        </div>
                        <div class="pulse-dot"></div>
                    </div>

                    <div class="daily-grid">
                        <div>
                            <span>Dòng dữ liệu</span>
                            <strong>{{ (int)($dailySummary->total_rows ?? 0) }}</strong>
                        </div>
                        <div>
                            <span>Tổng bài</span>
                            <strong>{{ number_format((int)($dailySummary->total_posts ?? 0), 0, ',', '.') }}</strong>
                        </div>
                        <div>
                            <span>Tổng call</span>
                            <strong>{{ number_format((int)($dailySummary->total_calls ?? 0), 0, ',', '.') }}</strong>
                        </div>
                        <div>
                            <span>Tổng data</span>
                            <strong>{{ number_format((int)($dailySummary->total_company_data ?? 0), 0, ',', '.') }}</strong>
                        </div>
                        <div class="danger">
                            <span>KPI thiếu</span>
                            <strong>{{ number_format((int)($dailySummary->total_missing_kpi ?? 0), 0, ',', '.') }}</strong>
                        </div>
                        <div>
                            <span>TB hoàn thành</span>
                            <strong>{{ round((float)($dailySummary->avg_completion ?? 0)) }}%</strong>
                        </div>
                    </div>
                </div>

                <div class="panel-pro">
                    <div class="panel-pro__head compact">
                        <div>
                            <div class="section-chip">USER FOCUS</div>
                            <h2>{{ $selectedName }}</h2>
                            <p>Preview KPI ngày của user đang chọn.</p>
                        </div>
                    </div>

                    <div class="focus-board">
                        <div class="focus-progress">
                            <div style="width: {{ min(100, $previewCompletion) }}%"></div>
                        </div>

                        <div class="focus-grid">
                            <div>
                                <span>Bài đăng</span>
                                <strong>{{ (int)($currentEntry->posts_count ?? 0) }}/{{ (int)($targets['posts'] ?? 0) }}</strong>
                            </div>
                            <div>
                                <span>Cuộc gọi</span>
                                <strong>{{ (int)($currentEntry->calls_answered ?? 0) }}/{{ (int)($targets['calls'] ?? 0) }}</strong>
                            </div>
                            <div>
                                <span>Data</span>
                                <strong>{{ (int)($currentEntry->company_data_called ?? 0) }}/{{ (int)($targets['company_data'] ?? 0) }}</strong>
                            </div>
                            <div>
                                <span>Hoàn thành</span>
                                <strong>{{ $previewCompletion }}%</strong>
                            </div>
                            <div class="danger">
                                <span>KPI thiếu</span>
                                <strong>{{ $previewMissing }}</strong>
                            </div>
                            <div class="danger">
                                <span>Khấu trừ</span>
                                <strong>{{ number_format($previewPenalty, 0, ',', '.') }}đ</strong>
                            </div>
                        </div>

                        <a href="{{ route('sales.kpi.my', ['work_date' => $workDate, 'user_id' => $selectedUserId ?: $authUser->id]) }}" class="btn btn-open-user w-100">
                            <i class="bi bi-pencil-square me-1"></i>
                            @if($selectedUserId)
                                Mở form nhập KPI user này
                            @else
                                Cập nhật KPI của tôi
                            @endif
                        </a>
                    </div>
                </div>

                <div class="panel-pro">
                    <div class="panel-pro__head compact">
                        <div>
                            <div class="section-chip">POLICY</div>
                            <h2>Chính sách đang bật</h2>
                            <p>Đọc từ trang cài đặt KPI.</p>
                        </div>
                    </div>

                    <div class="policy-list">
                        @foreach($activeCore as $item)
                            <div>
                                <i class="bi {{ $item['icon'] }}"></i>
                                <span>{{ $item['label'] }}</span>
                                <strong>
                                    @if($item['target'] === null)
                                        {{ $item['unit'] }}
                                    @else
                                        {{ number_format($item['target'], 0, ',', '.') }} {{ $item['unit'] }}
                                    @endif
                                </strong>
                            </div>
                        @endforeach

                        @foreach($extraModules as $item)
                            <div>
                                <i class="bi {{ $item['icon'] }}"></i>
                                <span>{{ $item['label'] }}</span>
                                <strong>{{ number_format((int)($kpiSettings[$item['target_key']] ?? 0), 0, ',', '.') }} {{ $item['unit'] }}</strong>
                            </div>
                        @endforeach

                        @if($activeCore->count() === 0 && $extraModules->count() === 0)
                            <div class="empty-policy">Chưa bật hạng mục KPI nào.</div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="panel-pro mt-3">
        <div class="panel-pro__head">
            <div>
                <div class="section-chip">DAILY TEAM</div>
                <h2>Team snapshot theo ngày</h2>
                <p>Danh sách KPI trong ngày đang lọc.</p>
            </div>
            <span class="panel-badge">{{ $dailyRows->total() ?? $dailyRows->count() }} dòng</span>
        </div>

        <div class="table-responsive">
            <table class="table table-sm align-middle table-pro mb-0">
                <thead>
                    <tr>
                        <th>Nhân viên</th>
                        <th>Bài</th>
                        <th>Call</th>
                        <th>Data</th>
                        <th>Follow up</th>
                        <th>%</th>
                        <th>Thiếu KPI</th>
                        <th>Khấu trừ</th>
                        <th>Trạng thái</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($dailyRows as $row)
                        <tr>
                            <td>
                                <div class="person-cell">
                                    <div class="avatar-dot small">{{ mb_substr($row->user->name ?? 'N', 0, 1) }}</div>
                                    <strong>{{ $row->user->name ?? 'N/A' }}</strong>
                                </div>
                            </td>
                            <td>{{ (int)($row->posts_count ?? 0) }}</td>
                            <td>{{ (int)($row->calls_answered ?? 0) }}</td>
                            <td>{{ (int)($row->company_data_called ?? 0) }}</td>
                            <td>
                                @if($row->followed_up_all_previous)
                                    <span class="status-pill ok">Đã xong</span>
                                @else
                                    <span class="status-pill warn">Chưa đủ</span>
                                @endif
                            </td>
                            <td>
                                <div class="progress-cell short">
                                    <div class="progress-track">
                                        <div style="width: {{ min(100, (int)($row->completion_percent ?? 0)) }}%"></div>
                                    </div>
                                    <strong>{{ (int)($row->completion_percent ?? 0) }}%</strong>
                                </div>
                            </td>
                            <td class="text-danger fw-bold">{{ (int)($row->missing_kpi_count ?? 0) }}</td>
                            <td class="text-danger fw-bold">{{ number_format((int)($row->penalty_amount ?? 0), 0, ',', '.') }}đ</td>
                            <td>
                                @if($row->status === 'completed')
                                    <span class="status-pill ok">Đạt KPI</span>
                                @elseif($row->status === 'warning')
                                    <span class="status-pill warn">Thiếu nhẹ</span>
                                @else
                                    <span class="status-pill bad">Thiếu KPI</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9">
                                <div class="empty-state">
                                    <i class="bi bi-calendar-x"></i>
                                    <strong>Không có dữ liệu ngày này</strong>
                                    <span>Chọn ngày khác hoặc nhập KPI mới.</span>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="p-3">
            {{ $dailyRows->links() }}
        </div>
    </div>

    <div class="row g-3 mt-0">
        <div class="col-xl-4">
            <div class="panel-pro h-100">
                <div class="panel-pro__head compact">
                    <div>
                        <div class="section-chip">TREND</div>
                        <h2>Trend 7 bản ghi</h2>
                        <p>Theo user đang chọn.</p>
                    </div>
                </div>

                <div class="trend-list">
                    @forelse($latestRows as $item)
                        <div class="trend-item">
                            <span>{{ \Carbon\Carbon::parse($item->work_date)->format('d/m') }}</span>
                            <div class="trend-track">
                                <div style="width: {{ min(100, (int)($item->completion_percent ?? 0)) }}%"></div>
                            </div>
                            <strong>{{ (int)($item->completion_percent ?? 0) }}%</strong>
                        </div>
                    @empty
                        <div class="empty-mini">
                            <i class="bi bi-graph-up"></i>
                            Chưa có dữ liệu trend.
                        </div>
                    @endforelse
                </div>
            </div>
        </div>

        <div class="col-xl-8">
            <div class="panel-pro h-100">
                <div class="panel-pro__head compact">
                    <div>
                        <div class="section-chip">HISTORY</div>
                        <h2>Lịch sử KPI user đang chọn</h2>
                        <p>Các bản ghi gần nhất.</p>
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="table table-sm align-middle table-pro mb-0">
                        <thead>
                            <tr>
                                <th>Ngày</th>
                                <th>Nhân viên</th>
                                <th>Bài</th>
                                <th>Call</th>
                                <th>Data</th>
                                <th>%</th>
                                <th>Thiếu KPI</th>
                                <th>Khấu trừ</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($history as $row)
                                <tr>
                                    <td>{{ \Carbon\Carbon::parse($row->work_date)->format('d/m/Y') }}</td>
                                    <td>{{ $row->user->name ?? 'N/A' }}</td>
                                    <td>{{ (int)($row->posts_count ?? 0) }}</td>
                                    <td>{{ (int)($row->calls_answered ?? 0) }}</td>
                                    <td>{{ (int)($row->company_data_called ?? 0) }}</td>
                                    <td>{{ (int)($row->completion_percent ?? 0) }}%</td>
                                    <td class="text-danger fw-bold">{{ (int)($row->missing_kpi_count ?? 0) }}</td>
                                    <td class="text-danger fw-bold">{{ number_format((int)($row->penalty_amount ?? 0), 0, ',', '.') }}đ</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="8">
                                        <div class="empty-state">
                                            <i class="bi bi-clock-history"></i>
                                            <strong>Chưa có lịch sử KPI</strong>
                                            <span>User này chưa có bản ghi.</span>
                                        </div>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="p-3">
                    {{ $history->appends(request()->except('history_page'))->links() }}
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.kpi-command-center{
    --text:#0f172a;
    --muted:#64748b;
    --line:#e6edf6;
    --panel:#fff;
    --blue:#2563eb;
    --cyan:#06b6d4;
    --violet:#7c3aed;
    --green:#16a34a;
    --amber:#f59e0b;
    --red:#dc2626;
    color:var(--text);
    font-size:12.5px;
}
.kpi-command-center input,
.kpi-command-center select,
.kpi-command-center button{
    font-size:12.5px;
}

.kpi-hero{
    position:relative;
    overflow:hidden;
    display:grid;
    grid-template-columns:1.45fr .68fr;
    gap:18px;
    padding:24px;
    min-height:260px;
    border-radius:28px;
    color:#fff;
    background:
        radial-gradient(circle at 8% 12%, rgba(37,99,235,.38), transparent 32%),
        radial-gradient(circle at 80% 20%, rgba(6,182,212,.28), transparent 30%),
        linear-gradient(135deg,#071225 0%,#101d37 50%,#15345e 100%);
    box-shadow:0 26px 75px rgba(15,23,42,.21);
    animation:heroIn .5s ease both;
}
.hero-noise{
    position:absolute;
    inset:0;
    background:
        linear-gradient(110deg, transparent 0%, rgba(255,255,255,.12) 25%, transparent 50%),
        repeating-linear-gradient(90deg, rgba(255,255,255,.035) 0 1px, transparent 1px 72px);
    transform:translateX(-70%);
    animation:shine 6s ease-in-out infinite;
}
.hero-orb{
    position:absolute;
    border-radius:999px;
    filter:blur(2px);
    opacity:.7;
    animation:floaty 7s ease-in-out infinite;
}
.hero-orb--one{width:90px;height:90px;right:34%;top:28px;background:rgba(6,182,212,.18);}
.hero-orb--two{width:140px;height:140px;right:45px;bottom:18px;background:rgba(124,58,237,.16);animation-delay:-3s;}
.hero-main,.hero-score{position:relative;z-index:2;}
.hero-chip{
    display:inline-flex;
    align-items:center;
    gap:8px;
    padding:7px 10px;
    border-radius:999px;
    background:rgba(255,255,255,.08);
    border:1px solid rgba(255,255,255,.12);
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
.kpi-hero h1{
    margin:14px 0 8px;
    font-size:38px;
    line-height:1.04;
    letter-spacing:-.04em;
    font-weight:950;
}
.kpi-hero p{
    max-width:780px;
    margin:0;
    color:#cbd5e1;
    font-size:12.5px;
    line-height:1.75;
}
.hero-actions{
    display:flex;
    flex-wrap:wrap;
    gap:9px;
    margin-top:17px;
}
.hero-btn{
    min-height:39px;
    border-radius:14px;
    display:inline-flex;
    align-items:center;
    padding:0 14px;
    font-weight:900;
}
.hero-btn--light{background:#fff;color:#0f172a;border:0;}
.hero-btn--ghost{background:rgba(255,255,255,.08);border:1px solid rgba(255,255,255,.12);color:#fff;}

.hero-score{
    display:flex;
    flex-direction:column;
    justify-content:center;
    gap:12px;
}
.score-ring{
    width:156px;
    height:156px;
    margin:0 auto;
    border-radius:999px;
    padding:9px;
    background:conic-gradient(from 180deg,#22c55e,#06b6d4,#2563eb,#22c55e);
    animation:spin 8s linear infinite;
}
.score-ring > div{
    width:100%;
    height:100%;
    border-radius:999px;
    background:#0b1428;
    display:flex;
    flex-direction:column;
    align-items:center;
    justify-content:center;
}
.score-ring span{font-size:11px;color:#94a3b8;font-weight:850;}
.score-ring strong{font-size:38px;line-height:1;font-weight:950;}
.score-ring em{font-size:11px;color:#67e8f9;font-style:normal;font-weight:950;margin-top:4px;}
.hero-mini{
    display:grid;
    grid-template-columns:1fr 1fr;
    gap:8px;
}
.hero-mini > div{
    padding:11px;
    border-radius:16px;
    background:rgba(255,255,255,.08);
    border:1px solid rgba(255,255,255,.1);
}
.hero-mini span{display:block;color:#cbd5e1;font-size:10.5px;font-weight:850;margin-bottom:4px;}
.hero-mini strong{font-size:15px;font-weight:950;}
.hero-mini .danger strong{color:#fecaca;}

.filter-console{
    display:grid;
    grid-template-columns:1fr 1fr 1.45fr 160px;
    gap:10px;
    padding:12px;
    border-radius:21px;
    background:#fff;
    border:1px solid var(--line);
    box-shadow:0 12px 36px rgba(15,23,42,.055);
}
.filter-field label{
    display:block;
    margin-bottom:5px;
    color:#475569;
    font-size:11px;
    font-weight:900;
}
.filter-field > div{
    position:relative;
}
.filter-field i{
    position:absolute;
    left:12px;
    top:50%;
    transform:translateY(-50%);
    color:#64748b;
    z-index:2;
}
.filter-field input,
.filter-field select{
    width:100%;
    min-height:38px;
    border:1px solid #dbe6f2;
    border-radius:14px;
    padding:0 12px 0 35px;
    background:#fbfdff;
    color:#0f172a;
    font-weight:850;
    outline:0;
}
.btn-filter{
    align-self:end;
    min-height:38px;
    border:0;
    border-radius:14px;
    color:#fff;
    font-weight:950;
    background:linear-gradient(135deg,#2563eb,#06b6d4);
    box-shadow:0 12px 26px rgba(37,99,235,.18);
}

.quick-entry-card{
    position:relative;
    overflow:hidden;
    display:flex;
    align-items:center;
    justify-content:space-between;
    gap:14px;
    padding:15px 16px;
    border-radius:22px;
    border:1px solid #dbeafe;
    background:
        radial-gradient(circle at top right, rgba(37,99,235,.12), transparent 30%),
        linear-gradient(135deg,#ffffff,#f8fbff);
    box-shadow:0 14px 40px rgba(37,99,235,.08);
    animation:fadeUp .44s ease both;
}
.quick-entry-card:before{
    content:"";
    position:absolute;
    inset:0;
    background:linear-gradient(110deg, transparent 0%, rgba(37,99,235,.08) 28%, transparent 48%);
    transform:translateX(-70%);
    animation:shine 6s ease-in-out infinite;
    pointer-events:none;
}
.quick-entry-card__left{
    position:relative;
    z-index:2;
    display:flex;
    align-items:center;
    gap:12px;
}
.quick-entry-icon{
    width:46px;
    height:46px;
    border-radius:17px;
    display:flex;
    align-items:center;
    justify-content:center;
    color:#fff;
    font-size:20px;
    background:linear-gradient(135deg,#2563eb,#06b6d4);
    box-shadow:0 12px 28px rgba(37,99,235,.18);
}
.quick-entry-title{
    color:#0f172a;
    font-size:14px;
    font-weight:950;
    margin-bottom:3px;
}
.quick-entry-sub{
    color:#64748b;
    font-size:12px;
    line-height:1.5;
}
.quick-entry-btn{
    position:relative;
    z-index:2;
    min-height:42px;
    flex:0 0 auto;
    display:inline-flex;
    align-items:center;
    justify-content:center;
    border:0;
    border-radius:15px;
    padding:0 16px;
    color:#fff;
    font-size:12.5px;
    font-weight:950;
    background:linear-gradient(135deg,#2563eb,#06b6d4);
    box-shadow:0 14px 28px rgba(37,99,235,.22);
}
.quick-entry-btn:hover{
    color:#fff;
    transform:translateY(-1px);
}

.metric-grid{
    display:grid;
    grid-template-columns:repeat(6,minmax(0,1fr));
    gap:10px;
}
.metric-card{
    position:relative;
    overflow:hidden;
    padding:14px;
    border-radius:20px;
    background:#fff;
    border:1px solid var(--line);
    box-shadow:0 14px 40px rgba(15,23,42,.055);
    transition:.2s ease;
    animation:fadeUp .44s ease both;
}
.metric-card:hover{
    transform:translateY(-2px);
    box-shadow:0 18px 46px rgba(15,23,42,.08);
}
.metric-card:after{
    content:"";
    position:absolute;
    width:90px;
    height:90px;
    right:-45px;
    bottom:-48px;
    border-radius:999px;
    opacity:.11;
}
.metric-card--blue:after{background:#2563eb;}
.metric-card--cyan:after{background:#06b6d4;}
.metric-card--violet:after{background:#7c3aed;}
.metric-card--green:after{background:#16a34a;}
.metric-card--amber:after{background:#f59e0b;}
.metric-card--red:after{background:#dc2626;}
.metric-card__top{
    display:flex;
    align-items:center;
    justify-content:space-between;
    gap:8px;
    color:#64748b;
    font-size:11.5px;
    font-weight:900;
    margin-bottom:9px;
}
.metric-card__top i{font-size:18px;}
.metric-card strong{
    display:block;
    color:#0f172a;
    font-size:25px;
    line-height:1;
    font-weight:950;
    letter-spacing:-.035em;
    margin-bottom:7px;
}
.metric-card em{
    color:#64748b;
    font-size:11px;
    font-style:normal;
    font-weight:700;
}

.panel-pro{
    overflow:hidden;
    background:#fff;
    border:1px solid var(--line);
    border-radius:24px;
    box-shadow:0 16px 48px rgba(15,23,42,.06);
    animation:fadeUp .44s ease both;
}
.panel-pro--dark{
    color:#fff;
    background:
        radial-gradient(circle at top right, rgba(6,182,212,.23), transparent 32%),
        linear-gradient(135deg,#0b1223,#17233f);
    border-color:rgba(255,255,255,.08);
}
.panel-pro__head{
    display:flex;
    align-items:flex-start;
    justify-content:space-between;
    gap:14px;
    padding:16px;
    border-bottom:1px solid var(--line);
}
.panel-pro__head.compact{
    padding-bottom:10px;
}
.panel-pro__head--dark{
    border-bottom-color:rgba(255,255,255,.08);
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
.section-chip--dark{
    background:rgba(255,255,255,.1);
    color:#bae6fd;
}
.panel-pro h2{
    margin:0 0 5px;
    font-size:17px;
    font-weight:950;
    letter-spacing:-.015em;
}
.panel-pro p{
    margin:0;
    color:#64748b;
    font-size:12px;
}
.panel-pro--dark p{
    color:#cbd5e1;
}
.panel-badge{
    display:inline-flex;
    align-items:center;
    gap:6px;
    padding:7px 10px;
    border-radius:999px;
    background:#f1f5f9;
    color:#334155;
    font-size:11px;
    font-weight:900;
    white-space:nowrap;
}
.table-pro thead th{
    background:#f8fafc;
    border-bottom:1px solid var(--line);
    color:#475569;
    font-size:11.5px;
    font-weight:950;
    padding:11px 12px;
    white-space:nowrap;
}
.table-pro tbody td{
    border-bottom:1px solid #eef2f7;
    color:#0f172a;
    font-size:12px;
    padding:11px 12px;
    vertical-align:middle;
    white-space:nowrap;
}
.table-pro tbody tr:hover{
    background:#fbfdff;
}
.person-cell{
    display:flex;
    align-items:center;
    gap:9px;
}
.avatar-dot{
    width:34px;
    height:34px;
    border-radius:13px;
    display:flex;
    align-items:center;
    justify-content:center;
    color:#fff;
    font-size:13px;
    font-weight:950;
    background:linear-gradient(135deg,#2563eb,#06b6d4);
}
.avatar-dot.small{
    width:28px;
    height:28px;
    border-radius:10px;
    font-size:12px;
}
.person-cell strong{
    display:block;
    font-size:12.5px;
    font-weight:950;
}
.person-cell span{
    display:block;
    color:#64748b;
    font-size:10.5px;
}
.rank-pill{
    width:28px;
    height:28px;
    border-radius:10px;
    display:inline-flex;
    align-items:center;
    justify-content:center;
    background:#eef2f7;
    color:#334155;
    font-size:12px;
    font-weight:950;
}
.rank-gold{background:#fef3c7;color:#92400e;}
.rank-silver{background:#e2e8f0;color:#334155;}
.rank-bronze{background:#ffedd5;color:#9a3412;}
.status-pill{
    display:inline-flex;
    align-items:center;
    justify-content:center;
    padding:5px 8px;
    border-radius:999px;
    font-size:10.5px;
    font-weight:950;
}
.status-pill.ok{background:#dcfce7;color:#166534;}
.status-pill.warn{background:#fef3c7;color:#92400e;}
.status-pill.bad{background:#fee2e2;color:#991b1b;}
.progress-cell{
    display:flex;
    align-items:center;
    gap:8px;
    min-width:120px;
}
.progress-cell.short{
    min-width:100px;
}
.progress-track{
    width:76px;
    height:8px;
    border-radius:999px;
    background:#eaf0f6;
    overflow:hidden;
}
.progress-track div{
    height:100%;
    border-radius:999px;
    background:linear-gradient(90deg,#22c55e,#06b6d4,#2563eb);
}
.progress-cell strong{
    font-size:11.5px;
    font-weight:950;
}
.side-stack{
    position:sticky;
    top:14px;
    display:flex;
    flex-direction:column;
    gap:10px;
}
.daily-grid{
    display:grid;
    grid-template-columns:repeat(2,minmax(0,1fr));
    gap:8px;
    padding:16px;
}
.daily-grid div{
    padding:11px;
    border-radius:15px;
    background:rgba(255,255,255,.08);
    border:1px solid rgba(255,255,255,.08);
}
.daily-grid span{
    display:block;
    color:#cbd5e1;
    font-size:10.5px;
    font-weight:850;
    margin-bottom:4px;
}
.daily-grid strong{
    font-size:16px;
    font-weight:950;
}
.daily-grid .danger strong{
    color:#fecaca;
}
.pulse-dot{
    width:11px;
    height:11px;
    border-radius:999px;
    background:#22c55e;
    animation:pulse 1.5s ease infinite;
}
.focus-board{
    padding:16px;
}
.focus-progress{
    height:9px;
    border-radius:999px;
    background:#eaf0f6;
    overflow:hidden;
    margin-bottom:10px;
}
.focus-progress div{
    height:100%;
    border-radius:999px;
    background:linear-gradient(90deg,#22c55e,#06b6d4,#2563eb);
}
.focus-grid{
    display:grid;
    grid-template-columns:repeat(2,minmax(0,1fr));
    gap:8px;
    margin-bottom:10px;
}
.focus-grid div{
    padding:10px;
    border-radius:14px;
    border:1px solid var(--line);
    background:#fbfdff;
}
.focus-grid span{
    display:block;
    color:#64748b;
    font-size:10.5px;
    font-weight:850;
    margin-bottom:4px;
}
.focus-grid strong{
    font-size:14px;
    font-weight:950;
}
.focus-grid .danger strong{
    color:#dc2626;
}
.btn-open-user{
    min-height:39px;
    border:0;
    border-radius:14px;
    color:#fff;
    font-weight:950;
    background:linear-gradient(135deg,#2563eb,#06b6d4);
}
.policy-list{
    display:flex;
    flex-direction:column;
    gap:7px;
    padding:16px;
}
.policy-list div{
    display:grid;
    grid-template-columns:26px 1fr auto;
    gap:8px;
    align-items:center;
    padding:8px 9px;
    border-radius:14px;
    border:1px solid var(--line);
    background:#fbfdff;
}
.policy-list i{
    color:#2563eb;
    font-size:15px;
}
.policy-list span{
    color:#64748b;
    font-size:11.5px;
    font-weight:850;
}
.policy-list strong{
    color:#0f172a;
    font-size:11.5px;
    font-weight:950;
    text-align:right;
}
.empty-policy{
    display:flex !important;
    grid-template-columns:1fr !important;
    justify-content:center;
    color:#64748b;
}
.trend-list{
    padding:16px;
}
.trend-item{
    display:grid;
    grid-template-columns:45px 1fr 42px;
    gap:9px;
    align-items:center;
    margin-bottom:10px;
}
.trend-item span{
    color:#334155;
    font-size:11.5px;
    font-weight:850;
}
.trend-track{
    height:8px;
    border-radius:999px;
    background:#eaf0f6;
    overflow:hidden;
}
.trend-track div{
    height:100%;
    border-radius:999px;
    background:linear-gradient(90deg,#22c55e,#06b6d4,#2563eb);
}
.trend-item strong{
    text-align:right;
    font-size:11.5px;
    font-weight:950;
}
.empty-state{
    min-height:120px;
    display:flex;
    flex-direction:column;
    align-items:center;
    justify-content:center;
    gap:5px;
    color:#64748b;
    text-align:center;
}
.empty-state i{
    font-size:28px;
    color:#94a3b8;
}
.empty-state strong{
    color:#334155;
    font-size:13px;
    font-weight:950;
}
.empty-state span{
    font-size:12px;
}
.empty-mini{
    min-height:110px;
    display:flex;
    align-items:center;
    justify-content:center;
    gap:7px;
    color:#64748b;
    font-size:12px;
    font-weight:850;
    border:1px dashed #d8e2ee;
    border-radius:16px;
}

@keyframes heroIn{
    from{opacity:0;transform:translateY(10px) scale(.99);}
    to{opacity:1;transform:translateY(0) scale(1);}
}
@keyframes fadeUp{
    from{opacity:0;transform:translateY(12px);}
    to{opacity:1;transform:translateY(0);}
}
@keyframes shine{
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
    .metric-grid{grid-template-columns:repeat(3,minmax(0,1fr));}
    .kpi-hero{grid-template-columns:1fr;}
    .hero-score{max-width:430px;}
    .side-stack{position:static;}
    .filter-console{grid-template-columns:repeat(2,minmax(0,1fr));}
}
@media (max-width:991.98px){
    .metric-grid,.filter-console{grid-template-columns:1fr;}
    .quick-entry-card{
        flex-direction:column;
        align-items:stretch;
    }
    .quick-entry-btn{
        width:100%;
    }
    .daily-grid,.focus-grid{grid-template-columns:1fr;}
    .panel-pro__head{flex-direction:column;}
    .kpi-hero h1{font-size:30px;}
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const nf = new Intl.NumberFormat('vi-VN');

    document.querySelectorAll('[data-count]').forEach(function (el) {
        const target = parseInt(el.dataset.count || '0', 10);
        const duration = 650;
        const start = performance.now();

        function tick(now) {
            const progress = Math.min(1, (now - start) / duration);
            const value = Math.round(target * progress);
            el.textContent = nf.format(value);

            if (progress < 1) {
                requestAnimationFrame(tick);
            }
        }

        requestAnimationFrame(tick);
    });
});

<script>
document.addEventListener('DOMContentLoaded', function () {
    const selectedUserId = @json($selectedUserId);
    const userSelect = document.querySelector('select[name="user_id"]');

    if (userSelect && (selectedUserId === null || selectedUserId === '' || selectedUserId === 0)) {
        userSelect.value = '';
    }
});
</script>
@endsection
