@extends('layouts.app')

@section('title', 'Dashboard điều hành')

@push('styles')
<link rel="stylesheet" href="{{ asset('css/executive-dashboard.css') }}?v={{ filemtime(public_path('css/executive-dashboard.css')) }}">
@endpush

@section('content')
@php
    $data = $executive;
    $money = static fn ($value) => number_format((float) $value, 0, ',', '.') . ' đ';
    $number = static fn ($value) => number_format((float) $value, 0, ',', '.');
    $percent = static fn ($value) => number_format((float) $value, 1, ',', '.') . '%';
    $changeLabel = static function ($value): string {
        if ($value === null) return 'Chưa có kỳ so sánh';
        $prefix = (float) $value > 0 ? '+' : '';
        return $prefix . number_format((float) $value, 1, ',', '.') . '%';
    };
    $changeClass = static function ($value): string {
        if ($value === null || abs((float) $value) < 0.05) return 'neutral';
        return (float) $value > 0 ? 'positive' : 'negative';
    };
    $alertIcons = [
        'management' => 'bi-shield-check',
        'warehouse' => 'bi-box-seam',
        'late_shipping' => 'bi-truck',
        'debt_30' => 'bi-exclamation-octagon',
        'returns' => 'bi-arrow-counterclockwise',
        'refunds' => 'bi-cash-coin',
        'maintenance' => 'bi-tools',
        'payment_requests' => 'bi-receipt',
        'low_stock' => 'bi-box2',
    ];
    $activityIcons = [
        'order' => 'bi-receipt-cutoff',
        'payment' => 'bi-wallet2',
        'return' => 'bi-arrow-counterclockwise',
        'maintenance' => 'bi-tools',
    ];
@endphp

<main class="exec-dashboard" id="executiveDashboard">
    <div class="exec-dashboard__container">
        <header class="exec-header">
            <div class="exec-header__copy">
                <div class="exec-eyebrow">TRUNG TÂM ĐIỀU HÀNH</div>
                <div class="exec-header__title-row">
                    <h1>Dashboard Giám đốc</h1>
                    <span class="exec-scope-chip">
                        <i class="bi bi-building"></i>
                        {{ $data['company']['name'] ?: $data['access']['scope_label'] }}
                    </span>
                </div>
                <p>
                    Doanh thu, dòng tiền, công nợ và các vấn đề cần xử lý trong một màn hình.
                    <span>Cập nhật {{ $data['generated_at'] }}</span>
                </p>
            </div>

            <div class="exec-header__actions">
                <button type="button" class="exec-icon-button" id="execRefresh" title="Tải lại số liệu" aria-label="Tải lại số liệu">
                    <i class="bi bi-arrow-clockwise"></i>
                </button>
                <button type="button" class="exec-button exec-button--secondary" id="execDarkMode">
                    <i class="bi bi-moon-stars"></i>
                    <span>Dark mode</span>
                </button>
            </div>
        </header>

        <form class="exec-filter" method="GET" action="{{ route('dashboard') }}" id="executiveFilterForm">
            <div class="exec-filter__quick" role="group" aria-label="Chọn kỳ nhanh">
                @foreach([
                    'today' => 'Hôm nay',
                    'week' => 'Tuần này',
                    'month' => 'Tháng này',
                    'quarter' => 'Quý này',
                    'year' => 'Năm nay',
                ] as $periodKey => $periodLabel)
                    <button
                        type="button"
                        data-period="{{ $periodKey }}"
                        class="exec-period-button {{ $data['range']['period'] === $periodKey ? 'is-active' : '' }}"
                    >
                        {{ $periodLabel }}
                    </button>
                @endforeach
            </div>

            <div class="exec-filter__fields">
                <label class="exec-field exec-field--date">
                    <span>Từ ngày</span>
                    <input type="date" name="from" value="{{ $data['range']['from'] }}">
                </label>

                <label class="exec-field exec-field--date">
                    <span>Đến ngày</span>
                    <input type="date" name="to" value="{{ $data['range']['to'] }}">
                </label>

                @if($data['access']['can_filter_sales'])
                    <label class="exec-field exec-field--sales">
                        <span>Sales phụ trách</span>
                        <select name="sales_id">
                            <option value="">Tất cả nhân sự</option>
                            @foreach($data['sales_users'] as $salesUser)
                                <option value="{{ $salesUser['id'] }}" {{ (string) $data['filters']['sales_id'] === (string) $salesUser['id'] ? 'selected' : '' }}>
                                    {{ $salesUser['name'] }}
                                </option>
                            @endforeach
                        </select>
                    </label>
                @endif

                <label class="exec-field exec-field--source">
                    <span>Nguồn dữ liệu</span>
                    <select name="source">
                        <option value="all" {{ $data['filters']['source'] === 'all' ? 'selected' : '' }}>Tất cả nguồn</option>
                        <option value="orders" {{ $data['filters']['source'] === 'orders' ? 'selected' : '' }}>Đơn hàng thương mại</option>
                        <option value="sites" {{ $data['filters']['source'] === 'sites' ? 'selected' : '' }}>Công trình</option>
                    </select>
                </label>

                <input type="hidden" name="period" value="custom">

                <button type="submit" class="exec-button exec-button--primary exec-filter__submit">
                    <i class="bi bi-funnel"></i>
                    Áp dụng
                </button>

                <a href="{{ route('dashboard') }}" class="exec-icon-button exec-filter__reset" title="Đặt lại bộ lọc" aria-label="Đặt lại bộ lọc">
                    <i class="bi bi-x-lg"></i>
                </a>
            </div>
        </form>

        <section class="exec-kpis" aria-label="Chỉ số điều hành chính">
            <article class="exec-kpi exec-kpi--revenue">
                <div class="exec-kpi__top">
                    <span class="exec-kpi__label">Doanh thu</span>
                    <span class="exec-change {{ $changeClass($data['kpis']['revenue']['change']) }}">
                        <i class="bi {{ ($data['kpis']['revenue']['change'] ?? 0) >= 0 ? 'bi-arrow-up-right' : 'bi-arrow-down-right' }}"></i>
                        {{ $changeLabel($data['kpis']['revenue']['change']) }}
                    </span>
                </div>
                <strong class="exec-kpi__value">{{ $money($data['kpis']['revenue']['value']) }}</strong>
                <div class="exec-kpi__split">
                    <span>Thương mại <b>{{ $money($data['kpis']['revenue']['commercial']) }}</b></span>
                    <span>Công trình <b>{{ $money($data['kpis']['revenue']['project']) }}</b></span>
                </div>
                <small>Kỳ trước: {{ $money($data['kpis']['revenue']['previous']) }}</small>
            </article>

            <article class="exec-kpi exec-kpi--collected">
                <div class="exec-kpi__top">
                    <span class="exec-kpi__label">Tiền đã thu</span>
                    <span class="exec-change {{ $changeClass($data['kpis']['collected']['change']) }}">
                        <i class="bi {{ ($data['kpis']['collected']['change'] ?? 0) >= 0 ? 'bi-arrow-up-right' : 'bi-arrow-down-right' }}"></i>
                        {{ $changeLabel($data['kpis']['collected']['change']) }}
                    </span>
                </div>
                <strong class="exec-kpi__value">{{ $money($data['kpis']['collected']['value']) }}</strong>
                <div class="exec-kpi__progress" aria-label="Tỷ lệ thu tiền">
                    <span style="width: {{ min(100, $data['kpis']['collected']['rate']) }}%"></span>
                </div>
                <div class="exec-kpi__meta">
                    <span>Tỷ lệ thu {{ $percent($data['kpis']['collected']['rate']) }}</span>
                    <span>Kỳ trước {{ $money($data['kpis']['collected']['previous']) }}</span>
                </div>
            </article>

            <article class="exec-kpi exec-kpi--debt">
                <div class="exec-kpi__top">
                    <span class="exec-kpi__label">Công nợ hiện tại</span>
                    @if($data['kpis']['receivable']['overdue'] > 0)
                        <span class="exec-change negative">
                            <i class="bi bi-exclamation-circle"></i>
                            Có quá hạn
                        </span>
                    @else
                        <span class="exec-change positive"><i class="bi bi-check2-circle"></i> An toàn</span>
                    @endif
                </div>
                <strong class="exec-kpi__value">{{ $money($data['kpis']['receivable']['value']) }}</strong>
                <div class="exec-kpi__split">
                    <span>Chưa đến hạn <b>{{ $money($data['kpis']['receivable']['not_due']) }}</b></span>
                    <span>Quá hạn <b class="text-danger">{{ $money($data['kpis']['receivable']['overdue']) }}</b></span>
                </div>
                <small>Trên 30 ngày: {{ $money($data['kpis']['receivable']['overdue_30']) }}</small>
            </article>

            <article class="exec-kpi exec-kpi--operations">
                <div class="exec-kpi__top">
                    <span class="exec-kpi__label">Đơn hàng & công trình</span>
                    <span class="exec-change {{ $data['kpis']['operations']['at_risk'] > 0 ? 'negative' : 'positive' }}">
                        <i class="bi {{ $data['kpis']['operations']['at_risk'] > 0 ? 'bi-exclamation-triangle' : 'bi-check2-circle' }}"></i>
                        {{ $number($data['kpis']['operations']['at_risk']) }} cần xử lý
                    </span>
                </div>
                <strong class="exec-kpi__value exec-kpi__value--count">
                    {{ $number($data['kpis']['operations']['orders']) }} đơn · {{ $number($data['kpis']['operations']['sites']) }} CT
                </strong>
                <div class="exec-kpi__split">
                    <span>Đơn hàng <b>{{ $number($data['kpis']['operations']['orders']) }}</b></span>
                    <span>Công trình <b>{{ $number($data['kpis']['operations']['sites']) }}</b></span>
                </div>
                <small>Giá trị liên quan rủi ro: {{ $money($data['kpis']['operations']['at_risk_value']) }}</small>
            </article>
        </section>

        <section class="exec-section exec-alert-center">
            <div class="exec-section__header">
                <div>
                    <div class="exec-eyebrow">ACTION CENTER</div>
                    <h2>Việc cần Giám đốc xử lý</h2>
                    <p>Cảnh báo tài chính và vận hành được ưu tiên theo mức độ.</p>
                </div>
                <span class="exec-section__badge">{{ count($data['alerts']) }} nhóm cảnh báo</span>
            </div>

            @if(count($data['alerts']))
                <div class="exec-alert-grid">
                    @foreach($data['alerts'] as $alert)
                        <article class="exec-alert exec-alert--{{ $alert['severity'] }}">
                            <div class="exec-alert__icon">
                                <i class="bi {{ $alertIcons[$alert['key']] ?? 'bi-exclamation-circle' }}"></i>
                            </div>
                            <div class="exec-alert__content">
                                <div class="exec-alert__title-row">
                                    <h3>{{ $alert['title'] }}</h3>
                                    <span>{{ $number($alert['count']) }}</span>
                                </div>
                                <p>{{ $alert['description'] }}</p>
                                @if($alert['value'] > 0)
                                    <strong>{{ $money($alert['value']) }}</strong>
                                @endif
                            </div>
                            @if($alert['url'])
                                <a href="{{ $alert['url'] }}" class="exec-alert__link" aria-label="Xem {{ $alert['title'] }}">
                                    <i class="bi bi-arrow-up-right"></i>
                                </a>
                            @endif
                        </article>
                    @endforeach
                </div>
            @else
                <div class="exec-empty exec-empty--success">
                    <i class="bi bi-check2-circle"></i>
                    <div>
                        <strong>Không có cảnh báo quan trọng</strong>
                        <span>Các quy trình chính hiện chưa phát sinh vấn đề cần xử lý ngay.</span>
                    </div>
                </div>
            @endif
        </section>

        <section class="exec-grid exec-grid--chart">
            <article class="exec-section exec-chart-card">
                <div class="exec-section__header exec-section__header--compact">
                    <div>
                        <h2>Xu hướng doanh thu & thu tiền</h2>
                        <p>{{ $data['range']['label'] }} · so sánh nguồn thương mại và công trình.</p>
                    </div>
                    <div class="exec-chart-legend">
                        <span><i class="dot dot--blue"></i>Thương mại</span>
                        <span><i class="dot dot--violet"></i>Công trình</span>
                        <span><i class="dot dot--green"></i>Đã thu</span>
                    </div>
                </div>
                <div class="exec-chart exec-chart--main">
                    <canvas id="executiveTrendChart"></canvas>
                </div>
            </article>

            <article class="exec-section exec-chart-card">
                <div class="exec-section__header exec-section__header--compact">
                    <div>
                        <h2>Cơ cấu doanh thu</h2>
                        <p>Tỷ trọng theo nguồn phát sinh.</p>
                    </div>
                </div>
                <div class="exec-chart exec-chart--donut">
                    <canvas id="executiveCompositionChart"></canvas>
                    <div class="exec-donut-total">
                        <span>Tổng</span>
                        <strong>{{ $money($data['kpis']['revenue']['value']) }}</strong>
                    </div>
                </div>
                <div class="exec-composition-list">
                    <div><span><i class="dot dot--blue"></i>Đơn hàng</span><b>{{ $money($data['kpis']['revenue']['commercial']) }}</b></div>
                    <div><span><i class="dot dot--violet"></i>Công trình</span><b>{{ $money($data['kpis']['revenue']['project']) }}</b></div>
                </div>
            </article>
        </section>

        <section class="exec-grid exec-grid--finance">
            <article class="exec-section">
                <div class="exec-section__header exec-section__header--compact">
                    <div>
                        <h2>Tuổi công nợ</h2>
                        <p>Phân loại các khoản phải thu theo ngày đến hạn.</p>
                    </div>
                </div>
                <div class="exec-chart exec-chart--aging">
                    <canvas id="executiveDebtChart"></canvas>
                </div>
            </article>

            <article class="exec-section">
                <div class="exec-section__header exec-section__header--compact">
                    <div>
                        <h2>Khách hàng nợ lớn</h2>
                        <p>Ưu tiên theo tổng dư nợ hiện tại.</p>
                    </div>
                    @if(Route::has('orders.index'))
                        <a href="{{ route('orders.index', ['payment_filter' => 'debt']) }}" class="exec-text-link">Xem công nợ</a>
                    @endif
                </div>
                @if(count($data['top_debtors']))
                    <div class="exec-debtor-list">
                        @foreach($data['top_debtors'] as $index => $debtor)
                            <div class="exec-debtor-row">
                                <span class="exec-rank">{{ $index + 1 }}</span>
                                <div class="exec-debtor-row__name">
                                    <strong>{{ $debtor['name'] }}</strong>
                                    <span>Quá hạn {{ $money($debtor['overdue']) }}</span>
                                </div>
                                <b>{{ $money($debtor['debt']) }}</b>
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="exec-empty exec-empty--small">Chưa có dữ liệu công nợ khách hàng.</div>
                @endif
            </article>

            <article class="exec-section">
                <div class="exec-section__header exec-section__header--compact">
                    <div>
                        <h2>Tiền dự kiến thu</h2>
                        <p>Theo ngày đến hạn của công nợ và đợt thanh toán.</p>
                    </div>
                </div>
                <div class="exec-forecast">
                    <div>
                        <span>Hôm nay</span>
                        <strong>{{ $money($data['cash_forecast']['today']) }}</strong>
                    </div>
                    <div>
                        <span>7 ngày tới</span>
                        <strong>{{ $money($data['cash_forecast']['next_7_days']) }}</strong>
                    </div>
                    <div>
                        <span>30 ngày tới</span>
                        <strong>{{ $money($data['cash_forecast']['next_30_days']) }}</strong>
                    </div>
                </div>
                <div class="exec-note">
                    <i class="bi bi-info-circle"></i>
                    Đây là lịch dự kiến thu, không phải số tiền đã nhận thực tế.
                </div>
            </article>
        </section>

        <section class="exec-section exec-pipeline-section">
            <div class="exec-section__header">
                <div>
                    <div class="exec-eyebrow">ORDER FLOW</div>
                    <h2>Pipeline vận hành đơn hàng</h2>
                    <p>Số lượng, giá trị và đơn quá hạn ở từng bước xử lý.</p>
                </div>
            </div>
            <div class="exec-pipeline">
                @foreach($data['pipeline'] as $step)
                    <a href="{{ $step['url'] ?: '#' }}" class="exec-pipeline__step {{ $step['key'] === 'completed' ? 'is-complete' : '' }}">
                        <div class="exec-pipeline__node">{{ $loop->iteration }}</div>
                        <div class="exec-pipeline__content">
                            <span>{{ $step['label'] }}</span>
                            <strong>{{ $number($step['count']) }} đơn</strong>
                            <small>{{ $money($step['value']) }}</small>
                            @if($step['overdue'] > 0)
                                <em>{{ $number($step['overdue']) }} quá hạn</em>
                            @endif
                        </div>
                    </a>
                @endforeach
            </div>
        </section>

        <section class="exec-grid exec-grid--operations">
            <article class="exec-section">
                <div class="exec-section__header exec-section__header--compact">
                    <div>
                        <h2>Kho & hàng hóa</h2>
                        <p>Tồn hiện tại, giữ chỗ và sản phẩm dưới ngưỡng.</p>
                    </div>
                    @if($data['inventory']['url'])
                        <a href="{{ $data['inventory']['url'] }}" class="exec-text-link">Mở kho</a>
                    @endif
                </div>
                <div class="exec-mini-kpis">
                    <div><span>Tổng tồn</span><strong>{{ $number($data['inventory']['total_qty']) }}</strong></div>
                    <div><span>SKU có tồn</span><strong>{{ $number($data['inventory']['sku_count']) }}</strong></div>
                    <div><span>Đang giữ</span><strong>{{ $number($data['inventory']['reserved_qty']) }}</strong></div>
                    <div class="is-warning"><span>Sắp hết / hết</span><strong>{{ $number($data['inventory']['low_count'] + $data['inventory']['out_count']) }}</strong></div>
                </div>
                @if(count($data['inventory']['low_stock']))
                    <div class="exec-stock-list">
                        @foreach($data['inventory']['low_stock'] as $stock)
                            <div class="exec-stock-row">
                                <div>
                                    <strong>{{ $stock['name'] }}</strong>
                                    <span>{{ $stock['sku'] }} · {{ $stock['warehouse'] }}</span>
                                </div>
                                <b class="{{ $stock['qty'] <= 0 ? 'is-out' : '' }}">{{ $number($stock['qty']) }}</b>
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="exec-empty exec-empty--small">Không có sản phẩm dưới ngưỡng cảnh báo.</div>
                @endif
            </article>

            <article class="exec-section">
                <div class="exec-section__header exec-section__header--compact">
                    <div>
                        <h2>Hậu mãi & kỹ thuật</h2>
                        <p>Đổi trả, hoàn tiền và lịch bảo trì cần theo dõi.</p>
                    </div>
                </div>
                <div class="exec-after-sales">
                    <a href="{{ $data['after_sales']['returns_url'] ?: '#' }}">
                        <span class="icon tone-orange"><i class="bi bi-arrow-counterclockwise"></i></span>
                        <div><span>Đổi trả đang mở</span><strong>{{ $number($data['after_sales']['returns_open']) }}</strong><small>{{ $money($data['after_sales']['returns_value']) }}</small></div>
                    </a>
                    <a href="{{ $data['after_sales']['returns_url'] ?: '#' }}">
                        <span class="icon tone-red"><i class="bi bi-cash-coin"></i></span>
                        <div><span>Hoàn tiền chờ xử lý</span><strong>{{ $number($data['after_sales']['refunds_pending']) }}</strong><small>{{ $money($data['after_sales']['refunds_value']) }}</small></div>
                    </a>
                    <a href="{{ $data['after_sales']['maintenance_url'] ?: '#' }}">
                        <span class="icon tone-blue"><i class="bi bi-calendar-check"></i></span>
                        <div><span>Bảo trì hôm nay</span><strong>{{ $number($data['after_sales']['maintenance_today']) }}</strong><small>7 ngày tới: {{ $number($data['after_sales']['maintenance_next_7']) }}</small></div>
                    </a>
                    <a href="{{ $data['after_sales']['maintenance_url'] ?: '#' }}">
                        <span class="icon tone-red"><i class="bi bi-exclamation-triangle"></i></span>
                        <div><span>Bảo trì quá hạn</span><strong>{{ $number($data['after_sales']['maintenance_overdue']) }}</strong><small>Cần điều phối kỹ thuật</small></div>
                    </a>
                </div>
            </article>
        </section>

        <section class="exec-grid exec-grid--bottom">
            <article class="exec-section">
                <div class="exec-section__header exec-section__header--compact">
                    <div>
                        <h2>Hiệu quả đội ngũ</h2>
                        <p>So sánh doanh thu, thu tiền và công nợ phát sinh.</p>
                    </div>
                </div>
                @if(count($data['team']))
                    <div class="exec-team-table-wrap">
                        <table class="exec-team-table">
                            <thead>
                                <tr>
                                    <th>Nhân sự</th>
                                    <th>Đơn</th>
                                    <th>Doanh thu</th>
                                    <th>Đã thu</th>
                                    <th>Công nợ</th>
                                    <th>Tỷ lệ thu</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($data['team'] as $member)
                                    <tr>
                                        <td>
                                            <span class="exec-avatar">{{ mb_strtoupper(mb_substr($member['name'], 0, 1)) }}</span>
                                            <strong>{{ $member['name'] }}</strong>
                                        </td>
                                        <td>{{ $number($member['orders']) }}</td>
                                        <td>{{ $money($member['revenue']) }}</td>
                                        <td class="text-success">{{ $money($member['collected']) }}</td>
                                        <td class="{{ $member['debt'] > 0 ? 'text-danger' : '' }}">{{ $money($member['debt']) }}</td>
                                        <td>
                                            <div class="exec-rate">
                                                <span style="width: {{ min(100, $member['collection_rate']) }}%"></span>
                                            </div>
                                            <small>{{ $percent($member['collection_rate']) }}</small>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <div class="exec-empty exec-empty--small">Không có dữ liệu nhân sự trong kỳ đã chọn.</div>
                @endif
            </article>

            <article class="exec-section">
                <div class="exec-section__header exec-section__header--compact">
                    <div>
                        <h2>Hoạt động gần đây</h2>
                        <p>Các thay đổi nghiệp vụ mới nhất trong công ty.</p>
                    </div>
                </div>
                @if(count($data['activities']))
                    <div class="exec-activity-list">
                        @foreach($data['activities'] as $activity)
                            <a href="{{ $activity['url'] ?: '#' }}" class="exec-activity">
                                <span class="exec-activity__icon"><i class="bi {{ $activityIcons[$activity['type']] ?? 'bi-activity' }}"></i></span>
                                <div>
                                    <strong>{{ $activity['title'] }}</strong>
                                    <span>{{ $activity['description'] }} · {{ $activity['actor'] }}</span>
                                </div>
                                <time>{{ $activity['time_label'] }}</time>
                            </a>
                        @endforeach
                    </div>
                @else
                    <div class="exec-empty exec-empty--small">Chưa có hoạt động mới.</div>
                @endif
            </article>
        </section>
    </div>
</main>
@endsection

@push('scripts')
<script>
window.EGO_EXECUTIVE_DASHBOARD = {{ Illuminate\Support\Js::from([
    'chart' => $data['chart'],
    'composition' => $data['composition'],
    'debtAging' => $data['debt_aging'],
]) }};
</script>
<script src="{{ asset('js/executive-dashboard.js') }}?v={{ filemtime(public_path('js/executive-dashboard.js')) }}"></script>
@endpush
