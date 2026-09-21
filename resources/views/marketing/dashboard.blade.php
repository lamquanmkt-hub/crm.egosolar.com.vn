@extends('layouts.app')

@section('title', 'Marketing Dashboard')

@section('content')
@php
    // =========================
    // Helpers
    // =========================
    $money = fn($v) => number_format((float)$v, 0, ',', '.') . ' đ';
    $num   = fn($v) => number_format((float)$v, 0, ',', '.');

    $safeDiv = function($a, $b){
        $a = (float)$a; $b = (float)$b;
        return $b != 0 ? ($a / $b) : 0;
    };

    $pct = function($current, $prev){
        $current = (float)$current; $prev = (float)$prev;
        if ($prev == 0) return null;
        return (($current - $prev) / $prev) * 100;
    };

    $deltaBadge = function($delta){
        if ($delta === null) return '<span class="ego-delta neutral">—</span>';
        $cls = $delta >= 0 ? 'up' : 'down';
        $arrow = $delta >= 0 ? '▲' : '▼';
        return '<span class="ego-delta '.$cls.'">'.$arrow.' '.number_format(abs($delta), 1, ',', '.').'%</span>';
    };

    // =========================
    // Inputs
    // =========================
    $platform = $platform ?? '';
    $isFacebook = ($platform === 'facebook');
    $isGoogle   = ($platform === 'google' || $platform === 'google_search');
    $isSEO      = ($platform === 'seo' || $platform === 'seo_organic');

    // SEO tab: report | plan
    $seoTab = request('seo_tab', 'report');
    $isSeoPlan   = $isSEO && $seoTab === 'plan';
    $isSeoReport = $isSEO && $seoTab !== 'plan';

    // KPI hiện tại (Ads/Search)
    $k = $kpi ?? [];

    $spend  = (float)($k['spend'] ?? 0);
    $leads  = (float)($k['leads'] ?? 0);

    // KPI FB
    $reach      = (float)($k['reach'] ?? 0);
    $impr       = (float)($k['impressions'] ?? 0);
    $freq       = (float)($k['frequency'] ?? 0);
    $cpm        = (float)($k['cpm'] ?? 0);

    // KPI Google Search (Ads/Search dashboard)
    $clicks     = (float)($k['clicks'] ?? 0);
    $ctr        = (float)($k['ctr'] ?? 0);          // %
    $cpc        = (float)($k['cpc'] ?? 0);
    $convs      = (float)($k['conversions'] ?? $leads);
    $cvr        = (float)($k['cvr'] ?? ($clicks > 0 ? ($convs / $clicks) * 100 : 0)); // %

    // derived Ads/Search
    $cpl = (float)($k['cpl'] ?? ($leads > 0 ? ($spend / $leads) : 0));
    $cpa = (float)($k['cpa'] ?? ($convs > 0 ? ($spend / $convs) : 0));

    // prev period (nếu backend chưa có thì để 0)
    $kPrev = $kpi_prev ?? ($k['prev'] ?? []);
    $spendPrev = (float)($kPrev['spend'] ?? 0);
    $leadsPrev = (float)($kPrev['leads'] ?? 0);
    $cplPrev   = (float)($kPrev['cpl'] ?? ($leadsPrev > 0 ? ($spendPrev / $leadsPrev) : 0));

    $clicksPrev = (float)($kPrev['clicks'] ?? 0);
    $ctrPrev    = (float)($kPrev['ctr'] ?? 0);
    $cpcPrev    = (float)($kPrev['cpc'] ?? 0);

    $reachPrev  = (float)($kPrev['reach'] ?? 0);
    $imprPrev   = (float)($kPrev['impressions'] ?? 0);

    // VAT (Ads/Search)
    $vatRate  = 0.10;
    $spendVat = $spend * (1 + $vatRate);
    $vatMoney = $spendVat - $spend;

    // =========================
    // SEO Inputs (wireframe-ready)
    // Expect from backend:
    // $seoKpi = [
    //   'sessions' => 0,
    //   'users' => 0,
    //   'clicks' => 0,
    //   'impressions' => 0,
    //   'ctr' => 0,           // %
    //   'avg_position' => 0,  // number
    //   'leads' => 0,
    //   'cvr' => 0,           // %
    //   'top10' => 0,
    //   'top3' => 0,
    //   'value_equivalent' => 0, // optional: ước tính value traffic
    // ];
    $seoKpi = $seoKpi ?? ($seo['kpi'] ?? []);

    $seoSessions = (float)($seoKpi['sessions'] ?? 0);
    $seoUsers    = (float)($seoKpi['users'] ?? 0);
    $seoClicks   = (float)($seoKpi['clicks'] ?? 0);
    $seoImpr     = (float)($seoKpi['impressions'] ?? 0);
    $seoCtr      = (float)($seoKpi['ctr'] ?? ($seoImpr > 0 ? ($seoClicks / $seoImpr) * 100 : 0));
    $seoPos      = (float)($seoKpi['avg_position'] ?? 0);
    $seoLeads    = (float)($seoKpi['leads'] ?? 0);
    $seoCvr      = (float)($seoKpi['cvr'] ?? ($seoClicks > 0 ? ($seoLeads / $seoClicks) * 100 : 0));
    $seoTop10    = (float)($seoKpi['top10'] ?? 0);
    $seoTop3     = (float)($seoKpi['top3'] ?? 0);
    $seoValueEq  = (float)($seoKpi['value_equivalent'] ?? 0);

    $seoPrev = $seoKpi_prev ?? ($seoKpi['prev'] ?? []);
    $seoSessionsPrev = (float)($seoPrev['sessions'] ?? 0);
    $seoClicksPrev   = (float)($seoPrev['clicks'] ?? 0);
    $seoImprPrev     = (float)($seoPrev['impressions'] ?? 0);
    $seoCtrPrev      = (float)($seoPrev['ctr'] ?? 0);
    $seoPosPrev      = (float)($seoPrev['avg_position'] ?? 0);
    $seoLeadsPrev    = (float)($seoPrev['leads'] ?? 0);
    $seoTop10Prev    = (float)($seoPrev['top10'] ?? 0);

    // =========================
    // Trend data (theo ngày)
    // For Ads/Search:
    // $trend = ['labels'=>[], 'spend'=>[], 'leads'=>[], 'cpl'=>[], 'clicks'=>[], 'cpc'=>[]]
    // For SEO:
    // $seoTrend = ['labels'=>[], 'sessions'=>[], 'clicks'=>[], 'impressions'=>[], 'ctr'=>[], 'position'=>[], 'leads'=>[], 'top10'=>[]]
    // =========================
    $trend = $trend ?? [];
    $trendLabels = $trend['labels'] ?? [];
    $trendSpend  = $trend['spend'] ?? [];
    $trendLeads  = $trend['leads'] ?? [];
    $trendCpl    = $trend['cpl'] ?? [];
    $trendClicks = $trend['clicks'] ?? [];
    $trendCpc    = $trend['cpc'] ?? [];

    $seoTrend = $seoTrend ?? ($seo['trend'] ?? []);
    $seoTrendLabels  = $seoTrend['labels'] ?? [];
    $seoTrendSess    = $seoTrend['sessions'] ?? [];
    $seoTrendClicks  = $seoTrend['clicks'] ?? [];
    $seoTrendImpr    = $seoTrend['impressions'] ?? [];
    $seoTrendCtr     = $seoTrend['ctr'] ?? [];
    $seoTrendPos     = $seoTrend['position'] ?? [];
    $seoTrendLeads   = $seoTrend['leads'] ?? [];
    $seoTrendTop10   = $seoTrend['top10'] ?? [];

    // =========================
    // Funnel + Budget pacing + Insights (Ads/Search)
    // =========================
    $funnel = $funnel ?? [];
    $fImpr  = (float)($funnel['impressions'] ?? $impr);
    $fClicks= (float)($funnel['clicks'] ?? $clicks);
    $fLeads = (float)($funnel['leads'] ?? $leads);
    $fQual  = (float)($funnel['qualified'] ?? 0);
    $fAppt  = (float)($funnel['appointments'] ?? 0);
    $fDeal  = (float)($funnel['deals'] ?? 0);

    $budget = $budget ?? [];
    $monthBudget = (float)($budget['month_budget'] ?? 0);
    $spentToDate = (float)($budget['spent'] ?? $spend);
    $forecast    = (float)($budget['forecast'] ?? 0);
    $pacingPct   = $monthBudget > 0 ? min(100, ($spentToDate / $monthBudget) * 100) : 0;
    $pacingStatus= $budget['status'] ?? 'ok'; // ok|over|under
    $pacingNote  = $budget['note'] ?? null;

    $insights = $insights ?? [];

    // =========================
    // SEO Report Tables (wireframe-ready)
    // Expect:
    // $seoTopQueries = collect([{query, clicks, impressions, ctr, position, url(optional)}])
    // $seoTopPages   = collect([{url, sessions, clicks, leads, cvr}])
    // $seoIssues     = collect([{level, text}]) // warn/danger/info/ok
    // =========================
    $seoTopQueries = $seoTopQueries ?? collect();
    $seoTopPages   = $seoTopPages ?? collect();
    $seoIssues     = $seoIssues ?? collect();

    // =========================
    // SEO Plan (wireframe-ready)
    // Expect:
    // $seoPlanKpi = ['traffic_target'=>, 'lead_target'=>, 'top10_target'=>, 'revenue_target'=>, 'note'=>...]
    // $seoPlanItems = collect([{type, title, cluster, url, owner, due_date, status}])
    // =========================
    $seoPlanKpi   = $seoPlanKpi ?? ($seo['plan_kpi'] ?? []);
    $seoPlanItems = $seoPlanItems ?? collect();

    // Deep-dive tables (Ads/Search)
    $byDevice   = $byDevice ?? collect();
    $byGeo      = $byGeo ?? collect();
    $topKeywords= $topKeywords ?? collect();
    $topAds     = $topAds ?? collect();
@endphp

<div class="container-fluid px-4">

    {{-- HERO --}}
    <div class="ego-hero mt-3 mb-3">
        <div class="d-flex align-items-start justify-content-between flex-wrap gap-2">
            <div class="d-flex align-items-center gap-2">
                <div class="ego-hero-ic"><i class="bi bi-megaphone"></i></div>
                <div>
                    <div class="ego-hero-title">Marketing Dashboard</div>
                    <div class="ego-hero-sub">
                        @if($isSEO)
                            SEO Organic · Báo cáo & lập kế hoạch SEO ngay trên CRM
                        @else
                            FB Lead Form + Google Search · Tổng hợp ngân sách, hiệu quả & tối ưu
                        @endif
                    </div>
                </div>
            </div>

            <div class="d-flex gap-2 align-items-center flex-wrap">
                <div class="ego-seg" role="group" aria-label="Platform">
                    <a class="ego-seg-btn {{ $isFacebook ? 'active' : '' }}"
                       href="{{ route('marketing.dashboard', array_merge(request()->query(), ['platform'=>'facebook'])) }}">
                        <i class="bi bi-facebook me-1"></i> Facebook Ads
                    </a>
                    <a class="ego-seg-btn {{ $isGoogle ? 'active' : '' }}"
                       href="{{ route('marketing.dashboard', array_merge(request()->query(), ['platform'=>'google_search'])) }}">
                        <i class="bi bi-google me-1"></i> Google Search
                    </a>
                    <a class="ego-seg-btn {{ $isSEO ? 'active' : '' }}"
                       href="{{ route('marketing.dashboard', array_merge(request()->query(), ['platform'=>'seo','seo_tab'=>($seoTab ?? 'report')])) }}">
                        <i class="bi bi-search me-1"></i> SEO Organic
                    </a>
                </div>

                @if($isSEO)
                    <div class="ego-seg" role="group" aria-label="SEO Tabs">
                        <a class="ego-seg-btn {{ $isSeoReport ? 'active' : '' }}"
                           href="{{ route('marketing.dashboard', array_merge(request()->query(), ['platform'=>'seo','seo_tab'=>'report'])) }}">
                            <i class="bi bi-graph-up me-1"></i> Báo cáo
                        </a>
                        <a class="ego-seg-btn {{ $isSeoPlan ? 'active' : '' }}"
                           href="{{ route('marketing.dashboard', array_merge(request()->query(), ['platform'=>'seo','seo_tab'=>'plan'])) }}">
                            <i class="bi bi-list-check me-1"></i> Lập kế hoạch
                        </a>
                    </div>
                @endif

                @if(\Illuminate\Support\Facades\Route::has('marketing.budget'))<a class="btn btn-outline-secondary ego-btn" href="{{ route('marketing.budget') }}">
                    <i class="bi bi-wallet2 me-1"></i> Ngân sách + chỉ số
                </a>@endif
                <a class="btn btn-outline-secondary ego-btn" href="{{ route('marketing.reports.content-calendar') }}">
                    <i class="bi bi-calendar2-week me-1"></i> Lịch biên tập
                </a>
            </div>
        </div>
    </div>

    {{-- FILTER --}}
    <div class="card ego-card mb-3">
        <div class="card-body">
            <div class="d-flex align-items-center gap-2 mb-2">
                <i class="bi bi-funnel"></i>
                <div class="fw-semibold">Bộ lọc</div>
                <div class="text-muted small">Chọn khoảng thời gian & nền tảng</div>
            </div>

            <form method="GET" action="{{ route('marketing.dashboard') }}">
                <div class="row g-2 align-items-end">
                    <div class="col-md-3">
                        <label class="form-label small text-muted mb-1">Từ ngày</label>
                        <input type="date" class="form-control ego-input" name="from" value="{{ $from ?? '' }}">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small text-muted mb-1">Đến ngày</label>
                        <input type="date" class="form-control ego-input" name="to" value="{{ $to ?? '' }}">
                    </div>

                    <div class="col-md-3">
                        <label class="form-label small text-muted mb-1">Nền tảng</label>
                        <select class="form-select ego-input" name="platform">
                            <option value="">-- Tất cả --</option>
                            <option value="facebook" {{ ($platform ?? '') === 'facebook' ? 'selected' : '' }}>Facebook</option>
                            <option value="google_search" {{ ($platform ?? '') === 'google_search' ? 'selected' : '' }}>Google Search</option>
                            <option value="seo" {{ ($platform ?? '') === 'seo' ? 'selected' : '' }}>SEO Organic</option>
                            <option value="tiktok" {{ ($platform ?? '') === 'tiktok' ? 'selected' : '' }}>Tiktok</option>
                            <option value="website" {{ ($platform ?? '') === 'website' ? 'selected' : '' }}>Website</option>
                            <option value="youtube" {{ ($platform ?? '') === 'youtube' ? 'selected' : '' }}>YouTube</option>
                        </select>
                        @if($isSEO)
                            <input type="hidden" name="seo_tab" value="{{ $seoTab }}">
                        @endif
                    </div>

                    <div class="col-md-3">
                        <button class="btn btn-success w-100 ego-btn-primary">
                            <i class="bi bi-funnel me-1"></i> Lọc dữ liệu
                        </button>
                    </div>
                </div>
            </form>

            <div class="d-flex gap-2 flex-wrap mt-3">
                <span class="ego-mini-pill"><i class="bi bi-calendar3 me-1"></i> Kỳ: {{ $from ?? '—' }} → {{ $to ?? '—' }}</span>
                @if($isSEO)
                    <span class="ego-mini-pill"><i class="bi bi-search me-1"></i> Kênh: SEO Organic</span>
                    <span class="ego-mini-pill"><i class="bi bi-ui-checks me-1"></i> Chế độ: {{ $isSeoPlan ? 'Lập kế hoạch' : 'Báo cáo' }}</span>
                @elseif($isFacebook)
                    <span class="ego-mini-pill"><i class="bi bi-ui-checks me-1"></i> Mục tiêu: Lead Form</span>
                @elseif($isGoogle)
                    <span class="ego-mini-pill"><i class="bi bi-search me-1"></i> Kênh: Search</span>
                @else
                    <span class="ego-mini-pill"><i class="bi bi-stack me-1"></i> Tổng hợp đa kênh</span>
                @endif
            </div>
        </div>
    </div>

    {{-- =========================================================
         SEO MODULE (Wireframe UI)
         ========================================================= --}}
    @if($isSEO)

        @if($isSeoReport)
            {{-- SEO KPI --}}
            <div class="row g-3 mb-3">
                <div class="col-xl-3 col-lg-4 col-md-6">
                    <div class="card ego-kpi">
                        <div class="ego-kpi-head">
                            <div>
                                <div class="ego-kpi-label">Organic Sessions</div>
                                <div class="ego-kpi-value">{{ $num($seoSessions) }}</div>
                                <div class="ego-kpi-sub">
                                    Lượt truy cập tự nhiên
                                    <span class="ms-2">{!! $deltaBadge($pct($seoSessions, $seoSessionsPrev)) !!}</span>
                                </div>
                            </div>
                            <div class="ego-kpi-ic"><i class="bi bi-bar-chart"></i></div>
                        </div>
                    </div>
                </div>

                <div class="col-xl-3 col-lg-4 col-md-6">
                    <div class="card ego-kpi">
                        <div class="ego-kpi-head">
                            <div>
                                <div class="ego-kpi-label">Clicks (GSC)</div>
                                <div class="ego-kpi-value">{{ $num($seoClicks) }}</div>
                                <div class="ego-kpi-sub">
                                    Nhấp tự nhiên
                                    <span class="ms-2">{!! $deltaBadge($pct($seoClicks, $seoClicksPrev)) !!}</span>
                                </div>
                            </div>
                            <div class="ego-kpi-ic"><i class="bi bi-cursor"></i></div>
                        </div>
                    </div>
                </div>

                <div class="col-xl-3 col-lg-4 col-md-6">
                    <div class="card ego-kpi">
                        <div class="ego-kpi-head">
                            <div>
                                <div class="ego-kpi-label">Impressions (GSC)</div>
                                <div class="ego-kpi-value">{{ $num($seoImpr) }}</div>
                                <div class="ego-kpi-sub">
                                    Hiển thị tự nhiên
                                    <span class="ms-2">{!! $deltaBadge($pct($seoImpr, $seoImprPrev)) !!}</span>
                                </div>
                            </div>
                            <div class="ego-kpi-ic"><i class="bi bi-eye"></i></div>
                        </div>
                    </div>
                </div>

                <div class="col-xl-3 col-lg-4 col-md-6">
                    <div class="card ego-kpi">
                        <div class="ego-kpi-head">
                            <div>
                                <div class="ego-kpi-label">CTR (GSC)</div>
                                <div class="ego-kpi-value">{{ number_format($seoCtr, 2, ',', '.') }}%</div>
                                <div class="ego-kpi-sub">
                                    Tỉ lệ nhấp
                                    <span class="ms-2">{!! $deltaBadge($pct($seoCtr, $seoCtrPrev)) !!}</span>
                                </div>
                            </div>
                            <div class="ego-kpi-ic"><i class="bi bi-percent"></i></div>
                        </div>
                    </div>
                </div>

                <div class="col-xl-3 col-lg-4 col-md-6">
                    <div class="card ego-kpi">
                        <div class="ego-kpi-head">
                            <div>
                                <div class="ego-kpi-label">Avg Position</div>
                                <div class="ego-kpi-value">{{ number_format($seoPos, 2, ',', '.') }}</div>
                                <div class="ego-kpi-sub">
                                    Vị trí trung bình (thấp hơn là tốt)
                                    <span class="ms-2">{!! $deltaBadge($pct($seoPosPrev, $seoPos)) !!}</span>
                                </div>
                            </div>
                            <div class="ego-kpi-ic"><i class="bi bi-geo-alt"></i></div>
                        </div>
                    </div>
                </div>

                <div class="col-xl-3 col-lg-4 col-md-6">
                    <div class="card ego-kpi">
                        <div class="ego-kpi-head">
                            <div>
                                <div class="ego-kpi-label">Leads (Organic)</div>
                                <div class="ego-kpi-value">{{ $num($seoLeads) }}</div>
                                <div class="ego-kpi-sub">
                                    Lead từ SEO
                                    <span class="ms-2">{!! $deltaBadge($pct($seoLeads, $seoLeadsPrev)) !!}</span>
                                </div>
                            </div>
                            <div class="ego-kpi-ic"><i class="bi bi-person-plus"></i></div>
                        </div>
                    </div>
                </div>

                <div class="col-xl-3 col-lg-4 col-md-6">
                    <div class="card ego-kpi">
                        <div class="ego-kpi-head">
                            <div>
                                <div class="ego-kpi-label">CVR (Lead/Click)</div>
                                <div class="ego-kpi-value">{{ number_format($seoCvr, 2, ',', '.') }}%</div>
                                <div class="ego-kpi-sub">Tỉ lệ chuyển đổi tự nhiên</div>
                            </div>
                            <div class="ego-kpi-ic"><i class="bi bi-lightning-charge"></i></div>
                        </div>
                    </div>
                </div>

                <div class="col-xl-3 col-lg-4 col-md-6">
                    <div class="card ego-kpi">
                        <div class="ego-kpi-head">
                            <div>
                                <div class="ego-kpi-label">Keywords TOP 10</div>
                                <div class="ego-kpi-value">{{ $num($seoTop10) }}</div>
                                <div class="ego-kpi-sub">
                                    Độ phủ từ khóa
                                    <span class="ms-2">{!! $deltaBadge($pct($seoTop10, $seoTop10Prev)) !!}</span>
                                </div>
                            </div>
                            <div class="ego-kpi-ic"><i class="bi bi-award"></i></div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- SEO MAIN: Trend + Issues --}}
            <div class="row g-3 mb-3">
                <div class="col-lg-8">
                    <div class="card ego-card">
                        <div class="card-header ego-card-header d-flex align-items-center justify-content-between flex-wrap gap-2">
                            <div class="fw-semibold">Xu hướng theo ngày (SEO)</div>
                            <div class="d-flex gap-2 flex-wrap">
                                <span class="ego-mini-pill">Sessions · Clicks · Leads · TOP10</span>
                                <span class="ego-mini-pill text-muted">So sánh kỳ trước (nếu có)</span>
                            </div>
                        </div>
                        <div class="card-body">
                            @if(is_array($seoTrendLabels) && count($seoTrendLabels))
                                <div class="ego-chart-wrap">
                                    <canvas id="egoSeoTrendChart" height="110"></canvas>
                                </div>
                                <div class="ego-chart-legend mt-2">
                                    <span class="ego-dot"></span> Sessions
                                    <span class="ms-3"><span class="ego-dot alt"></span> Clicks</span>
                                    <span class="ms-3"><span class="ego-dot alt2"></span> Leads</span>
                                    <span class="ms-3"><span class="ego-dot alt3"></span> TOP10</span>
                                </div>
                            @else
                                <div class="ego-empty">
                                    <div class="ego-empty-ic"><i class="bi bi-graph-up"></i></div>
                                    <div class="fw-semibold">Chưa có dữ liệu xu hướng SEO</div>
                                    <div class="text-muted small">Gợi ý: đồng bộ GA4/GSC hoặc import CSV để hiển thị biểu đồ.</div>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>

                <div class="col-lg-4">
                    <div class="card ego-card mb-3">
                        <div class="card-header ego-card-header d-flex align-items-center justify-content-between">
                            <div class="fw-semibold">Technical / Content Issues</div>
                            <span class="ego-mini-pill">{{ ($seoIssues ?? collect())->count() }}</span>
                        </div>
                        <div class="card-body">
                            @if(($seoIssues ?? collect())->count())
                                <div class="ego-insights">
                                    @foreach($seoIssues as $it)
                                        @php $lv = $it['level'] ?? 'info'; @endphp
                                        <div class="ego-insight {{ $lv }}">
                                            <div class="ego-insight-ic">
                                                @if($lv==='danger')
                                                    <i class="bi bi-exclamation-octagon"></i>
                                                @elseif($lv==='warn')
                                                    <i class="bi bi-exclamation-triangle"></i>
                                                @elseif($lv==='ok')
                                                    <i class="bi bi-check2-circle"></i>
                                                @else
                                                    <i class="bi bi-info-circle"></i>
                                                @endif
                                            </div>
                                            <div class="ego-insight-txt">{{ $it['text'] ?? '' }}</div>
                                        </div>
                                    @endforeach
                                </div>
                            @else
                                <div class="text-muted small">
                                    Wireframe gợi ý các rule:
                                    <ul class="mb-0">
                                        <li>CTR giảm &gt; 15%</li>
                                        <li>Avg position xấu đi &gt; 2 bậc</li>
                                        <li>Landing có traffic tăng nhưng lead giảm</li>
                                        <li>Nhiều trang 404 / redirect chain</li>
                                    </ul>
                                </div>
                            @endif
                        </div>
                    </div>

                    <div class="card ego-card">
                        <div class="card-header ego-card-header">
                            <div class="fw-semibold">Giá trị traffic (ước tính)</div>
                        </div>
                        <div class="card-body">
                            <div class="d-flex align-items-center justify-content-between">
                                <div class="text-muted small">Value equivalent</div>
                                <div class="fw-semibold">{{ $seoValueEq > 0 ? $money($seoValueEq) : '—' }}</div>
                            </div>
                            <div class="text-muted small mt-2">
                                Wireframe: có thể tính bằng CPC trung bình Google Ads * Clicks SEO.
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- SEO TABLES: Queries + Pages --}}
            <div class="row g-3">
                <div class="col-lg-7">
                    <div class="card ego-card">
                        <div class="card-header ego-card-header d-flex align-items-center justify-content-between flex-wrap gap-2">
                            <div class="fw-semibold">Top Queries (GSC)</div>
                            <span class="ego-mini-pill">Clicks · Impr · CTR · Position</span>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-hover mb-0 align-middle ego-table">
                                    <thead>
                                    <tr>
                                        <th>Query</th>
                                        <th class="text-end" style="width:90px;">Clicks</th>
                                        <th class="text-end" style="width:110px;">Impr</th>
                                        <th class="text-end" style="width:90px;">CTR</th>
                                        <th class="text-end" style="width:110px;">Pos</th>
                                    </tr>
                                    </thead>
                                    <tbody>
                                    @if(($seoTopQueries ?? collect())->count())
                                        @foreach($seoTopQueries as $r)
                                            @php
                                                $qClicks = (float)($r->clicks ?? $r['clicks'] ?? 0);
                                                $qImpr   = (float)($r->impressions ?? $r['impressions'] ?? 0);
                                                $qCtr    = (float)($r->ctr ?? $r['ctr'] ?? ($qImpr>0 ? ($qClicks/$qImpr)*100 : 0));
                                                $qPos    = (float)($r->position ?? $r['position'] ?? 0);
                                                $qName   = ($r->query ?? $r['query'] ?? '—');
                                            @endphp
                                            <tr>
                                                <td class="fw-semibold">{{ $qName }}</td>
                                                <td class="text-end">{{ $num($qClicks) }}</td>
                                                <td class="text-end">{{ $num($qImpr) }}</td>
                                                <td class="text-end">{{ number_format($qCtr, 2, ',', '.') }}%</td>
                                                <td class="text-end">{{ number_format($qPos, 2, ',', '.') }}</td>
                                            </tr>
                                        @endforeach
                                    @else
                                        <tr><td colspan="5" class="text-center text-muted py-4">Chưa có dữ liệu query (wireframe)</td></tr>
                                    @endif
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-lg-5">
                    <div class="card ego-card">
                        <div class="card-header ego-card-header d-flex align-items-center justify-content-between flex-wrap gap-2">
                            <div class="fw-semibold">Top Landing Pages</div>
                            <span class="ego-mini-pill">Sessions · Clicks · Leads</span>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-hover mb-0 align-middle ego-table">
                                    <thead>
                                    <tr>
                                        <th>URL</th>
                                        <th class="text-end" style="width:90px;">Sessions</th>
                                        <th class="text-end" style="width:90px;">Leads</th>
                                        <th class="text-end" style="width:90px;">CVR</th>
                                    </tr>
                                    </thead>
                                    <tbody>
                                    @if(($seoTopPages ?? collect())->count())
                                        @foreach($seoTopPages as $r)
                                            @php
                                                $pUrl = ($r->url ?? $r['url'] ?? '—');
                                                $pSess= (float)($r->sessions ?? $r['sessions'] ?? 0);
                                                $pLea = (float)($r->leads ?? $r['leads'] ?? 0);
                                                $pCvr = (float)($r->cvr ?? $r['cvr'] ?? ($pSess>0 ? ($pLea/$pSess)*100 : 0));
                                            @endphp
                                            <tr>
                                                <td class="fw-semibold text-truncate" style="max-width:240px;" title="{{ $pUrl }}">{{ $pUrl }}</td>
                                                <td class="text-end">{{ $num($pSess) }}</td>
                                                <td class="text-end">{{ $num($pLea) }}</td>
                                                <td class="text-end">{{ number_format($pCvr, 2, ',', '.') }}%</td>
                                            </tr>
                                        @endforeach
                                    @else
                                        <tr><td colspan="4" class="text-center text-muted py-4">Chưa có dữ liệu landing (wireframe)</td></tr>
                                    @endif
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        @endif

        @if($isSeoPlan)
            {{-- SEO PLAN HEADER KPI --}}
            <div class="row g-3 mb-3">
                <div class="col-lg-8">
                    <div class="card ego-card">
                        <div class="card-header ego-card-header d-flex align-items-center justify-content-between flex-wrap gap-2">
                            <div class="fw-semibold">Mục tiêu SEO (Kỳ đã chọn)</div>
                            <span class="ego-mini-pill">Wireframe: editable form</span>
                        </div>
                        <div class="card-body">
                            <div class="row g-2">
                                <div class="col-md-3">
                                    <div class="ego-plan-field">
                                        <div class="ego-plan-label">Traffic target</div>
                                        <div class="ego-plan-val">{{ $num($seoPlanKpi['traffic_target'] ?? 0) }}</div>
                                        <div class="ego-plan-sub text-muted small">sessions</div>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="ego-plan-field">
                                        <div class="ego-plan-label">Leads target</div>
                                        <div class="ego-plan-val">{{ $num($seoPlanKpi['lead_target'] ?? 0) }}</div>
                                        <div class="ego-plan-sub text-muted small">leads</div>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="ego-plan-field">
                                        <div class="ego-plan-label">TOP10 target</div>
                                        <div class="ego-plan-val">{{ $num($seoPlanKpi['top10_target'] ?? 0) }}</div>
                                        <div class="ego-plan-sub text-muted small">keywords</div>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="ego-plan-field">
                                        <div class="ego-plan-label">Revenue target</div>
                                        <div class="ego-plan-val">{{ $money($seoPlanKpi['revenue_target'] ?? 0) }}</div>
                                        <div class="ego-plan-sub text-muted small">ước tính</div>
                                    </div>
                                </div>
                            </div>

                            @if(!empty($seoPlanKpi['note']))
                                <div class="mt-3 ego-note">
                                    <i class="bi bi-lightbulb me-1"></i>{{ $seoPlanKpi['note'] }}
                                </div>
                            @else
                                <div class="mt-3 text-muted small">
                                    Wireframe: nút <b>Chỉnh mục tiêu</b> (modal) để nhập target theo tháng/quý + phân bổ theo cụm keyword.
                                </div>
                            @endif
                        </div>
                    </div>
                </div>

                <div class="col-lg-4">
                    <div class="card ego-card">
                        <div class="card-header ego-card-header">
                            <div class="fw-semibold">Trạng thái triển khai</div>
                        </div>
                        <div class="card-body">
                            <div class="text-muted small">Wireframe checklist:</div>
                            <div class="mt-2 ego-checklist">
                                <div class="ego-check"><i class="bi bi-circle"></i> Audit kỹ thuật</div>
                                <div class="ego-check"><i class="bi bi-circle"></i> Mapping keyword → landing</div>
                                <div class="ego-check"><i class="bi bi-circle"></i> Content plan</div>
                                <div class="ego-check"><i class="bi bi-circle"></i> Internal link</div>
                                <div class="ego-check"><i class="bi bi-circle"></i> Backlink / Entity</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- SEO PLAN ITEMS --}}
            <div class="card ego-card mb-3">
                <div class="card-header ego-card-header d-flex align-items-center justify-content-between flex-wrap gap-2">
                    <div class="fw-semibold">Backlog công việc SEO</div>
                    <div class="d-flex gap-2 flex-wrap">
                        <span class="ego-mini-pill">Content · Landing · Technical · Backlink</span>
                        <span class="ego-mini-pill text-muted">Wireframe: filter theo status/owner</span>
                    </div>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0 align-middle ego-table">
                            <thead>
                            <tr>
                                <th style="width:110px;">Loại</th>
                                <th>Hạng mục</th>
                                <th style="width:160px;">Cluster</th>
                                <th style="width:160px;">URL</th>
                                <th style="width:120px;">Owner</th>
                                <th class="text-end" style="width:120px;">Deadline</th>
                                <th class="text-end" style="width:130px;">Trạng thái</th>
                            </tr>
                            </thead>
                            <tbody>
                            @if(($seoPlanItems ?? collect())->count())
                                @foreach($seoPlanItems as $r)
                                    @php
                                        $type = $r->type ?? $r['type'] ?? 'Content';
                                        $title= $r->title ?? $r['title'] ?? '—';
                                        $cluster= $r->cluster ?? $r['cluster'] ?? '—';
                                        $url = $r->url ?? $r['url'] ?? '—';
                                        $owner = $r->owner ?? $r['owner'] ?? '—';
                                        $due = $r->due_date ?? $r['due_date'] ?? '—';
                                        $status = $r->status ?? $r['status'] ?? 'Chưa làm';
                                    @endphp
                                    <tr>
                                        <td class="fw-semibold">{{ $type }}</td>
                                        <td class="fw-semibold">{{ $title }}</td>
                                        <td>{{ $cluster }}</td>
                                        <td class="text-truncate" style="max-width:160px;" title="{{ $url }}">{{ $url }}</td>
                                        <td>{{ $owner }}</td>
                                        <td class="text-end">{{ $due }}</td>
                                        <td class="text-end">
                                            <span class="ego-status-chip {{ \Illuminate\Support\Str::slug($status) }}">{{ $status }}</span>
                                        </td>
                                    </tr>
                                @endforeach
                            @else
                                <tr>
                                    <td colspan="7" class="text-center text-muted py-4">
                                        Wireframe: danh sách task SEO (content/landing/tech/backlink). Chưa có dữ liệu.
                                    </td>
                                </tr>
                            @endif
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            {{-- SEO PLAN: quick templates --}}
            <div class="row g-3">
                <div class="col-lg-6">
                    <div class="card ego-card">
                        <div class="card-header ego-card-header">
                            <div class="fw-semibold">Mẫu kế hoạch Content</div>
                        </div>
                        <div class="card-body">
                            <div class="text-muted small mb-2">Wireframe gợi ý cấu trúc:</div>
                            <div class="ego-note">
                                <ul class="mb-0">
                                    <li>2 Landing “Báo giá / Chi phí” + “Lắp đặt” (đẩy lead)</li>
                                    <li>4 Blog cluster cho “áp mái / gia đình / nhà xưởng / hòa lưới”</li>
                                    <li>1 Case study dự án / tuần</li>
                                    <li>FAQ schema cho 5 trang money-keyword</li>
                                </ul>
                            </div>
                            <div class="mt-3 text-muted small">Wireframe: nút “Tạo kế hoạch từ template”.</div>
                        </div>
                    </div>
                </div>
                <div class="col-lg-6">
                    <div class="card ego-card">
                        <div class="card-header ego-card-header">
                            <div class="fw-semibold">Mẫu Technical SEO</div>
                        </div>
                        <div class="card-body">
                            <div class="text-muted small mb-2">Wireframe checklist:</div>
                            <div class="ego-note">
                                <ul class="mb-0">
                                    <li>Core Web Vitals ≥ 80</li>
                                    <li>Index coverage sạch lỗi</li>
                                    <li>Sitemap/robots đúng</li>
                                    <li>Redirect chain & broken links = 0</li>
                                    <li>Structured data: Organization + FAQ + Breadcrumb</li>
                                </ul>
                            </div>
                            <div class="mt-3 text-muted small">Wireframe: nút “Tạo task kỹ thuật”.</div>
                        </div>
                    </div>
                </div>
            </div>
        @endif

    @else
    {{-- =========================================================
         EXISTING ADS/SEARCH DASHBOARD (your original)
         ========================================================= --}}

    {{-- KPI --}}
    <div class="row g-3 mb-3">
        {{-- Spend (VAT) --}}
        <div class="col-xl-3 col-lg-4 col-md-6">
            <div class="card ego-kpi">
                <div class="ego-kpi-head">
                    <div>
                        <div class="ego-kpi-label">
                            Tổng chi tiêu <span class="ego-badge-vat ms-2">+VAT 10%</span>
                        </div>
                        <div class="ego-kpi-value ego-kpi-value-strong">{{ $money($spend) }}</div>
                        <div class="ego-kpi-sub">
                            Sau VAT: <span class="ego-kpi-sub-strong">{{ $money($spendVat) }}</span>
                            · VAT: <span class="ego-kpi-sub-strong">{{ $money($vatMoney) }}</span>
                            <span class="ms-2">{!! $deltaBadge($pct($spend, $spendPrev)) !!}</span>
                        </div>
                    </div>
                    <div class="ego-kpi-ic"><i class="bi bi-cash-coin"></i></div>
                </div>
            </div>
        </div>

        {{-- Common: Leads --}}
        <div class="col-xl-3 col-lg-4 col-md-6">
            <div class="card ego-kpi">
                <div class="ego-kpi-head">
                    <div>
                        <div class="ego-kpi-label">Leads</div>
                        <div class="ego-kpi-value">{{ $num($leads) }}</div>
                        <div class="ego-kpi-sub">
                            Tổng lead form
                            <span class="ms-2">{!! $deltaBadge($pct($leads, $leadsPrev)) !!}</span>
                        </div>
                    </div>
                    <div class="ego-kpi-ic"><i class="bi bi-person-plus"></i></div>
                </div>
            </div>
        </div>

        {{-- Common: CPL/CPA --}}
        <div class="col-xl-3 col-lg-4 col-md-6">
            <div class="card ego-kpi">
                <div class="ego-kpi-head">
                    <div>
                        <div class="ego-kpi-label">{{ $isGoogle ? 'CPA' : 'CPL' }}</div>
                        <div class="ego-kpi-value">{{ $money($isGoogle ? $cpa : $cpl) }}</div>
                        <div class="ego-kpi-sub">
                            {{ $isGoogle ? 'Chi phí / chuyển đổi' : 'Chi phí / lead' }}
                            <span class="ms-2">{!! $deltaBadge($pct(($isGoogle ? $cpa : $cpl), $cplPrev)) !!}</span>
                        </div>
                    </div>
                    <div class="ego-kpi-ic"><i class="bi bi-graph-up"></i></div>
                </div>
            </div>
        </div>

        {{-- Dynamic per platform --}}
        @if($isFacebook)
            <div class="col-xl-3 col-lg-4 col-md-6">
                <div class="card ego-kpi">
                    <div class="ego-kpi-head">
                        <div>
                            <div class="ego-kpi-label">Reach</div>
                            <div class="ego-kpi-value">{{ $num($reach) }}</div>
                            <div class="ego-kpi-sub">Tiếp cận <span class="ms-2">{!! $deltaBadge($pct($reach, $reachPrev)) !!}</span></div>
                        </div>
                        <div class="ego-kpi-ic"><i class="bi bi-broadcast"></i></div>
                    </div>
                </div>
            </div>

            <div class="col-xl-3 col-lg-4 col-md-6">
                <div class="card ego-kpi">
                    <div class="ego-kpi-head">
                        <div>
                            <div class="ego-kpi-label">Impressions</div>
                            <div class="ego-kpi-value">{{ $num($impr) }}</div>
                            <div class="ego-kpi-sub">Lượt hiển thị <span class="ms-2">{!! $deltaBadge($pct($impr, $imprPrev)) !!}</span></div>
                        </div>
                        <div class="ego-kpi-ic"><i class="bi bi-eye"></i></div>
                    </div>
                </div>
            </div>

            <div class="col-xl-3 col-lg-4 col-md-6">
                <div class="card ego-kpi">
                    <div class="ego-kpi-head">
                        <div>
                            <div class="ego-kpi-label">Frequency</div>
                            <div class="ego-kpi-value">{{ number_format($freq, 2, ',', '.') }}</div>
                            <div class="ego-kpi-sub">Tần suất trung bình</div>
                        </div>
                        <div class="ego-kpi-ic"><i class="bi bi-repeat"></i></div>
                    </div>
                </div>
            </div>

            <div class="col-xl-3 col-lg-4 col-md-6">
                <div class="card ego-kpi">
                    <div class="ego-kpi-head">
                        <div>
                            <div class="ego-kpi-label">CPM</div>
                            <div class="ego-kpi-value">{{ $money($cpm) }}</div>
                            <div class="ego-kpi-sub">Chi phí / 1.000 hiển thị</div>
                        </div>
                        <div class="ego-kpi-ic"><i class="bi bi-speedometer2"></i></div>
                    </div>
                </div>
            </div>
        @elseif($isGoogle)
            <div class="col-xl-3 col-lg-4 col-md-6">
                <div class="card ego-kpi">
                    <div class="ego-kpi-head">
                        <div>
                            <div class="ego-kpi-label">Impressions</div>
                            <div class="ego-kpi-value">{{ $num($impr) }}</div>
                            <div class="ego-kpi-sub">Lượt hiển thị</div>
                        </div>
                        <div class="ego-kpi-ic"><i class="bi bi-eye"></i></div>
                    </div>
                </div>
            </div>

            <div class="col-xl-3 col-lg-4 col-md-6">
                <div class="card ego-kpi">
                    <div class="ego-kpi-head">
                        <div>
                            <div class="ego-kpi-label">Clicks</div>
                            <div class="ego-kpi-value">{{ $num($clicks) }}</div>
                            <div class="ego-kpi-sub">Lượt nhấp <span class="ms-2">{!! $deltaBadge($pct($clicks, $clicksPrev)) !!}</span></div>
                        </div>
                        <div class="ego-kpi-ic"><i class="bi bi-cursor"></i></div>
                    </div>
                </div>
            </div>

            <div class="col-xl-3 col-lg-4 col-md-6">
                <div class="card ego-kpi">
                    <div class="ego-kpi-head">
                        <div>
                            <div class="ego-kpi-label">CTR</div>
                            <div class="ego-kpi-value">{{ number_format($ctr, 2, ',', '.') }}%</div>
                            <div class="ego-kpi-sub">Tỉ lệ nhấp <span class="ms-2">{!! $deltaBadge($pct($ctr, $ctrPrev)) !!}</span></div>
                        </div>
                        <div class="ego-kpi-ic"><i class="bi bi-percent"></i></div>
                    </div>
                </div>
            </div>

            <div class="col-xl-3 col-lg-4 col-md-6">
                <div class="card ego-kpi">
                    <div class="ego-kpi-head">
                        <div>
                            <div class="ego-kpi-label">Avg CPC</div>
                            <div class="ego-kpi-value">{{ $money($cpc) }}</div>
                            <div class="ego-kpi-sub">Chi phí / click <span class="ms-2">{!! $deltaBadge($pct($cpc, $cpcPrev)) !!}</span></div>
                        </div>
                        <div class="ego-kpi-ic"><i class="bi bi-lightning-charge"></i></div>
                    </div>
                </div>
            </div>
        @else
            <div class="col-xl-3 col-lg-4 col-md-6">
                <div class="card ego-kpi">
                    <div class="ego-kpi-head">
                        <div>
                            <div class="ego-kpi-label">Impressions</div>
                            <div class="ego-kpi-value">{{ $num($impr) }}</div>
                            <div class="ego-kpi-sub">Tổng hiển thị (nếu có)</div>
                        </div>
                        <div class="ego-kpi-ic"><i class="bi bi-eye"></i></div>
                    </div>
                </div>
            </div>
        @endif
    </div>

    {{-- MAIN: Trend + Funnel + Pacing + Insights --}}
    <div class="row g-3 mb-3">
        <div class="col-lg-8">
            <div class="card ego-card">
                <div class="card-header ego-card-header d-flex align-items-center justify-content-between flex-wrap gap-2">
                    <div class="fw-semibold">Xu hướng theo ngày</div>
                    <div class="d-flex gap-2 flex-wrap">
                        <span class="ego-mini-pill">Spent · Leads{{ $isGoogle ? ' · CPC' : ' · CPL' }}</span>
                        <span class="ego-mini-pill text-muted">So sánh kỳ trước (nếu có)</span>
                    </div>
                </div>
                <div class="card-body">
                    @if(is_array($trendLabels) && count($trendLabels))
                        <div class="ego-chart-wrap">
                            <canvas id="egoTrendChart" height="110"></canvas>
                        </div>
                        <div class="ego-chart-legend mt-2">
                            <span class="ego-dot"></span> Spend
                            <span class="ms-3"><span class="ego-dot alt"></span> Leads</span>
                            @if($isGoogle)
                                <span class="ms-3"><span class="ego-dot alt2"></span> CPC</span>
                            @else
                                <span class="ms-3"><span class="ego-dot alt2"></span> CPL</span>
                            @endif
                        </div>
                    @else
                        <div class="ego-empty">
                            <div class="ego-empty-ic"><i class="bi bi-graph-up"></i></div>
                            <div class="fw-semibold">Chưa có dữ liệu xu hướng</div>
                            <div class="text-muted small">Bạn hãy thử chọn khoảng ngày khác hoặc kiểm tra đồng bộ dữ liệu.</div>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            {{-- Funnel --}}
            <div class="card ego-card mb-3">
                <div class="card-header ego-card-header">
                    <div class="fw-semibold">Phễu chuyển đổi (Form)</div>
                </div>
                <div class="card-body">
                    @php
                        $base = max(1, $fImpr ?: 1);
                        $w1 = min(100, ($fImpr / $base) * 100);
                        $w2 = min(100, ($fClicks / $base) * 100);
                        $w3 = min(100, ($fLeads / $base) * 100);
                        $w4 = $fQual > 0 ? min(100, ($fQual / $base) * 100) : 0;
                        $w5 = $fAppt > 0 ? min(100, ($fAppt / $base) * 100) : 0;

                        $rateClick = $fImpr > 0 ? ($fClicks / $fImpr) * 100 : 0;
                        $rateLead  = $fClicks > 0 ? ($fLeads / $fClicks) * 100 : 0;
                        $rateQual  = $fLeads > 0 ? ($fQual / $fLeads) * 100 : 0;
                    @endphp

                    <div class="ego-funnel">
                        <div class="ego-funnel-row">
                            <div class="ego-funnel-label">Impressions</div>
                            <div class="ego-funnel-bar"><div class="ego-funnel-fill" style="width: {{ $w1 }}%"></div></div>
                            <div class="ego-funnel-val">{{ $num($fImpr) }}</div>
                        </div>
                        <div class="ego-funnel-row">
                            <div class="ego-funnel-label">Clicks</div>
                            <div class="ego-funnel-bar"><div class="ego-funnel-fill" style="width: {{ $w2 }}%"></div></div>
                            <div class="ego-funnel-val">{{ $num($fClicks) }}</div>
                        </div>
                        <div class="ego-funnel-row">
                            <div class="ego-funnel-label">Leads</div>
                            <div class="ego-funnel-bar"><div class="ego-funnel-fill" style="width: {{ $w3 }}%"></div></div>
                            <div class="ego-funnel-val">{{ $num($fLeads) }}</div>
                        </div>

                        @if($fQual > 0)
                            <div class="ego-funnel-row">
                                <div class="ego-funnel-label">Qualified</div>
                                <div class="ego-funnel-bar"><div class="ego-funnel-fill" style="width: {{ $w4 }}%"></div></div>
                                <div class="ego-funnel-val">{{ $num($fQual) }}</div>
                            </div>
                        @endif
                        @if($fAppt > 0)
                            <div class="ego-funnel-row">
                                <div class="ego-funnel-label">Appointment</div>
                                <div class="ego-funnel-bar"><div class="ego-funnel-fill" style="width: {{ $w5 }}%"></div></div>
                                <div class="ego-funnel-val">{{ $num($fAppt) }}</div>
                            </div>
                        @endif
                    </div>

                    <div class="mt-3 d-flex gap-2 flex-wrap">
                        <span class="ego-mini-pill">Click rate: {{ number_format($rateClick, 2, ',', '.') }}%</span>
                        <span class="ego-mini-pill">Lead/Click: {{ number_format($rateLead, 2, ',', '.') }}%</span>
                        @if($fQual > 0)
                            <span class="ego-mini-pill">Qual/Lead: {{ number_format($rateQual, 2, ',', '.') }}%</span>
                        @endif
                    </div>
                </div>
            </div>

            {{-- Pacing --}}
            <div class="card ego-card mb-3">
                <div class="card-header ego-card-header">
                    <div class="fw-semibold">Pacing ngân sách</div>
                </div>
                <div class="card-body">
                    <div class="d-flex align-items-center justify-content-between">
                        <div class="text-muted small">Ngân sách tháng</div>
                        <div class="fw-semibold">{{ $monthBudget > 0 ? $money($monthBudget) : '—' }}</div>
                    </div>
                    <div class="d-flex align-items-center justify-content-between mt-1">
                        <div class="text-muted small">Đã chi</div>
                        <div class="fw-semibold">{{ $money($spentToDate) }}</div>
                    </div>

                    <div class="ego-progress mt-2">
                        <div class="ego-progress-bar {{ $pacingStatus }}" style="width: {{ $pacingPct }}%"></div>
                    </div>

                    <div class="mt-2">
                        @php
                            $statusText = $pacingStatus === 'over' ? 'Chi tiêu đang vượt tiến độ'
                                        : ($pacingStatus === 'under' ? 'Chi tiêu đang thấp hơn tiến độ' : 'Chi tiêu đang đúng tiến độ');
                        @endphp
                        <span class="ego-status {{ $pacingStatus }}"><i class="bi bi-check2-circle me-1"></i>{{ $statusText }}</span>
                        @if($forecast > 0)
                            <div class="text-muted small mt-1">Dự báo cuối kỳ: <span class="fw-semibold">{{ $money($forecast) }}</span></div>
                        @endif
                        @if($pacingNote)
                            <div class="text-muted small mt-1">{{ $pacingNote }}</div>
                        @endif
                    </div>
                </div>
            </div>

            {{-- Insights --}}
            <div class="card ego-card">
                <div class="card-header ego-card-header d-flex align-items-center justify-content-between">
                    <div class="fw-semibold">Insights & Cảnh báo</div>
                    <span class="ego-mini-pill">{{ count($insights) ? count($insights) : 0 }} mục</span>
                </div>
                <div class="card-body">
                    @if(count($insights))
                        <div class="ego-insights">
                            @foreach($insights as $it)
                                @php $lv = $it['level'] ?? 'info'; @endphp
                                <div class="ego-insight {{ $lv }}">
                                    <div class="ego-insight-ic">
                                        @if($lv==='danger')
                                            <i class="bi bi-exclamation-octagon"></i>
                                        @elseif($lv==='warn')
                                            <i class="bi bi-exclamation-triangle"></i>
                                        @elseif($lv==='ok')
                                            <i class="bi bi-check2-circle"></i>
                                        @else
                                            <i class="bi bi-info-circle"></i>
                                        @endif
                                    </div>
                                    <div class="ego-insight-txt">{{ $it['text'] ?? '' }}</div>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <div class="text-muted small">Chưa có cảnh báo. (Bạn có thể tạo rule: CPA tăng, Leads giảm, IS thấp...)</div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    {{-- BOTTOM: Tables --}}
    <div class="row g-3">
        <div class="col-lg-8">
            <div class="card ego-card mb-3">
                <div class="card-header ego-card-header d-flex align-items-center justify-content-between flex-wrap gap-2">
                    <div class="fw-semibold">Tổng quan theo tháng</div>
                    <span class="ego-mini-pill">Gợi ý: thêm so sánh kỳ trước để “đỡ trống”</span>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0 align-middle ego-table">
                            <thead>
                            <tr>
                                <th style="width: 120px;">Tháng</th>
                                <th class="text-end" style="width: 160px;">Chi tiêu</th>
                                <th class="text-end" style="width: 140px;">{{ $isGoogle ? 'Impr' : 'Reach' }}</th>
                                <th class="text-end" style="width: 120px;">Leads</th>
                                <th class="text-end" style="width: 120px;">{{ $isGoogle ? 'CPA' : 'CPL' }}</th>
                            </tr>
                            </thead>
                            <tbody>
                            @if(($byMonth ?? collect())->count())
                                @foreach($byMonth as $r)
                                    @php
                                        $mSpend = (float)($r->spend ?? 0);
                                        $mReach = (float)($r->reach ?? 0);
                                        $mImpr  = (float)($r->impressions ?? 0);
                                        $mLeads = (float)($r->leads ?? 0);
                                        $mCpl   = $mLeads > 0 ? ($mSpend / $mLeads) : 0;
                                    @endphp
                                    <tr>
                                        <td class="fw-semibold">{{ $r->month }}</td>
                                        <td class="text-end">{{ $money($mSpend) }}</td>
                                        <td class="text-end">{{ $num($isGoogle ? $mImpr : $mReach) }}</td>
                                        <td class="text-end">{{ $num($mLeads) }}</td>
                                        <td class="text-end">{{ $money($mCpl) }}</td>
                                    </tr>
                                @endforeach
                            @else
                                <tr><td colspan="5" class="text-center text-muted py-4">Chưa có dữ liệu</td></tr>
                            @endif
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="row g-3">
                <div class="col-md-6">
                    <div class="card ego-card">
                        <div class="card-header ego-card-header">
                            <div class="fw-semibold">Theo thiết bị</div>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-hover mb-0 align-middle ego-table">
                                    <thead>
                                    <tr>
                                        <th>Thiết bị</th>
                                        <th class="text-end">Chi tiêu</th>
                                        @if($isGoogle)<th class="text-end">Clicks</th>@endif
                                        <th class="text-end">Leads</th>
                                        <th class="text-end">{{ $isGoogle ? 'CPA' : 'CPL' }}</th>
                                    </tr>
                                    </thead>
                                    <tbody>
                                    @if(($byDevice ?? collect())->count())
                                        @foreach($byDevice as $r)
                                            @php
                                                $dSpend = (float)($r->spend ?? 0);
                                                $dClicks= (float)($r->clicks ?? 0);
                                                $dLeads = (float)($r->leads ?? 0);
                                                $dCpl   = $dLeads > 0 ? ($dSpend / $dLeads) : 0;
                                            @endphp
                                            <tr>
                                                <td class="fw-semibold">{{ $r->device ?? '—' }}</td>
                                                <td class="text-end">{{ $money($dSpend) }}</td>
                                                @if($isGoogle)<td class="text-end">{{ $num($dClicks) }}</td>@endif
                                                <td class="text-end">{{ $num($dLeads) }}</td>
                                                <td class="text-end">{{ $money($dCpl) }}</td>
                                            </tr>
                                        @endforeach
                                    @else
                                        <tr><td colspan="{{ $isGoogle ? 5 : 4 }}" class="text-center text-muted py-4">Chưa có dữ liệu</td></tr>
                                    @endif
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-md-6">
                    <div class="card ego-card">
                        <div class="card-header ego-card-header">
                            <div class="fw-semibold">Theo khu vực</div>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-hover mb-0 align-middle ego-table">
                                    <thead>
                                    <tr>
                                        <th>Khu vực</th>
                                        <th class="text-end">Chi tiêu</th>
                                        <th class="text-end">Leads</th>
                                        <th class="text-end">{{ $isGoogle ? 'CPA' : 'CPL' }}</th>
                                    </tr>
                                    </thead>
                                    <tbody>
                                    @if(($byGeo ?? collect())->count())
                                        @foreach($byGeo as $r)
                                            @php
                                                $gSpend = (float)($r->spend ?? 0);
                                                $gLeads = (float)($r->leads ?? 0);
                                                $gCpl   = $gLeads > 0 ? ($gSpend / $gLeads) : 0;
                                            @endphp
                                            <tr>
                                                <td class="fw-semibold">{{ $r->location ?? '—' }}</td>
                                                <td class="text-end">{{ $money($gSpend) }}</td>
                                                <td class="text-end">{{ $num($gLeads) }}</td>
                                                <td class="text-end">{{ $money($gCpl) }}</td>
                                            </tr>
                                        @endforeach
                                    @else
                                        <tr><td colspan="4" class="text-center text-muted py-4">Chưa có dữ liệu</td></tr>
                                    @endif
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Right column: Top Campaign / Keywords / Ads --}}
        <div class="col-lg-4">
            <div class="card ego-card mb-3">
                <div class="card-header ego-card-header">
                    <div class="fw-semibold">Top chiến dịch (theo chi tiêu)</div>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0 align-middle ego-table">
                            <thead>
                            <tr>
                                <th>Chiến dịch</th>
                                @if($isGoogle)
                                    <th class="text-end" style="width:100px;">Clicks</th>
                                @else
                                    <th class="text-end" style="width:100px;">Reach</th>
                                @endif
                                <th class="text-end" style="width:90px;">Leads</th>
                                <th class="text-end" style="width:130px;">Chi tiêu</th>
                            </tr>
                            </thead>
                            <tbody>
                            @if(($topCampaigns ?? collect())->count())
                                @foreach($topCampaigns as $r)
                                    <tr>
                                        <td class="fw-semibold">{{ $r->campaign_name ?? 'Không rõ' }}</td>
                                        @if($isGoogle)
                                            <td class="text-end">{{ $num($r->clicks ?? 0) }}</td>
                                        @else
                                            <td class="text-end">{{ $num($r->reach ?? 0) }}</td>
                                        @endif
                                        <td class="text-end">{{ $num($r->leads ?? 0) }}</td>
                                        <td class="text-end">{{ $money($r->spend ?? 0) }}</td>
                                    </tr>
                                @endforeach
                            @else
                                <tr><td colspan="4" class="text-center text-muted py-4">Chưa có dữ liệu campaign</td></tr>
                            @endif
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            @if($isGoogle)
                <div class="card ego-card">
                    <div class="card-header ego-card-header">
                        <div class="fw-semibold">Top từ khóa</div>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0 align-middle ego-table">
                                <thead>
                                <tr>
                                    <th>Từ khóa</th>
                                    <th class="text-end" style="width:90px;">Clicks</th>
                                    <th class="text-end" style="width:110px;">CPC</th>
                                    <th class="text-end" style="width:90px;">Leads</th>
                                </tr>
                                </thead>
                                <tbody>
                                @if(($topKeywords ?? collect())->count())
                                    @foreach($topKeywords as $r)
                                        <tr>
                                            <td class="fw-semibold">{{ $r->keyword ?? '—' }}</td>
                                            <td class="text-end">{{ $num($r->clicks ?? 0) }}</td>
                                            <td class="text-end">{{ $money($r->cpc ?? 0) }}</td>
                                            <td class="text-end">{{ $num($r->conversions ?? $r->leads ?? 0) }}</td>
                                        </tr>
                                    @endforeach
                                @else
                                    <tr><td colspan="4" class="text-center text-muted py-4">Chưa có dữ liệu từ khóa</td></tr>
                                @endif
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            @elseif($isFacebook)
                <div class="card ego-card">
                    <div class="card-header ego-card-header">
                        <div class="fw-semibold">Top mẫu quảng cáo</div>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0 align-middle ego-table">
                                <thead>
                                <tr>
                                    <th>Mẫu / QC</th>
                                    <th class="text-end" style="width:90px;">Leads</th>
                                    <th class="text-end" style="width:120px;">CPL</th>
                                    <th class="text-end" style="width:130px;">Chi tiêu</th>
                                </tr>
                                </thead>
                                <tbody>
                                @if(($topAds ?? collect())->count())
                                    @foreach($topAds as $r)
                                        @php
                                            $aSpend = (float)($r->spend ?? 0);
                                            $aLeads = (float)($r->leads ?? 0);
                                            $aCpl   = $aLeads > 0 ? ($aSpend / $aLeads) : 0;
                                        @endphp
                                        <tr>
                                            <td class="fw-semibold">{{ $r->ad_name ?? $r->creative_name ?? '—' }}</td>
                                            <td class="text-end">{{ $num($aLeads) }}</td>
                                            <td class="text-end">{{ $money($aCpl) }}</td>
                                            <td class="text-end">{{ $money($aSpend) }}</td>
                                        </tr>
                                    @endforeach
                                @else
                                    <tr><td colspan="4" class="text-center text-muted py-4">Chưa có dữ liệu mẫu</td></tr>
                                @endif
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            @endif
        </div>
    </div>
    @endif {{-- end non-SEO --}}

</div>

{{-- CHART.JS (nếu layout chưa có) --}}
<script>
(function(){
  if (typeof Chart !== 'undefined') return;
  var s = document.createElement('script');
  s.src = 'https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js';
  s.onload = function(){
    initEgoTrendChart();
    initEgoSeoTrendChart();
  };
  document.head.appendChild(s);
})();

document.addEventListener('DOMContentLoaded', function(){
  if (typeof Chart !== 'undefined') {
    initEgoTrendChart();
    initEgoSeoTrendChart();
  }
});

function initEgoTrendChart(){
  var el = document.getElementById('egoTrendChart');
  if (!el) return;

  var labels = @json($trendLabels);
  var spend  = @json($trendSpend);
  var leads  = @json($trendLeads);
  var cpl    = @json($trendCpl);
  var clicks = @json($trendClicks);
  var cpc    = @json($trendCpc);

  if ((!cpl || !cpl.length) && spend && spend.length && leads && leads.length) {
    cpl = spend.map(function(v, i){
      var l = Number(leads[i] || 0);
      return l > 0 ? (Number(v || 0) / l) : 0;
    });
  }
  if ((!cpc || !cpc.length) && spend && spend.length && clicks && clicks.length) {
    cpc = spend.map(function(v, i){
      var c = Number(clicks[i] || 0);
      return c > 0 ? (Number(v || 0) / c) : 0;
    });
  }

  var showGoogle = {{ $isGoogle ? 'true' : 'false' }};

  new Chart(el, {
    type: 'line',
    data: {
      labels: labels,
      datasets: [
        { label: 'Spend', data: spend, tension: 0.35, yAxisID: 'y',  pointRadius: 0, borderWidth: 2 },
        { label: 'Leads', data: leads, tension: 0.35, yAxisID: 'y1', pointRadius: 0, borderWidth: 2 },
        { label: showGoogle ? 'CPC' : 'CPL', data: showGoogle ? cpc : cpl, tension: 0.35, yAxisID: 'y2', pointRadius: 0, borderWidth: 2 }
      ]
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      interaction: { mode: 'index', intersect: false },
      plugins: {
        legend: { display: false },
        tooltip: {
          callbacks: {
            label: function(ctx){
              var v = ctx.raw ?? 0;
              if (ctx.dataset.label === 'Spend' || ctx.dataset.label === 'CPC' || ctx.dataset.label === 'CPL') {
                return ctx.dataset.label + ': ' + new Intl.NumberFormat('vi-VN').format(Math.round(v)) + ' đ';
              }
              return ctx.dataset.label + ': ' + new Intl.NumberFormat('vi-VN').format(Math.round(v));
            }
          }
        }
      },
      scales: {
        y: { position: 'left',  ticks: { callback: v => new Intl.NumberFormat('vi-VN').format(v) } },
        y1:{ position: 'right', grid: { drawOnChartArea: false }, ticks: { callback: v => new Intl.NumberFormat('vi-VN').format(v) } },
        y2:{ position: 'right', grid: { drawOnChartArea: false }, ticks: { callback: v => new Intl.NumberFormat('vi-VN').format(v) } }
      }
    }
  });
}

function initEgoSeoTrendChart(){
  var el = document.getElementById('egoSeoTrendChart');
  if (!el) return;

  var labels = @json($seoTrendLabels);
  var sessions = @json($seoTrendSess);
  var clicks   = @json($seoTrendClicks);
  var leads    = @json($seoTrendLeads);
  var top10    = @json($seoTrendTop10);

  new Chart(el, {
    type: 'line',
    data: {
      labels: labels,
      datasets: [
        { label: 'Sessions', data: sessions, tension: 0.35, yAxisID: 'y',  pointRadius: 0, borderWidth: 2 },
        { label: 'Clicks',   data: clicks,   tension: 0.35, yAxisID: 'y1', pointRadius: 0, borderWidth: 2 },
        { label: 'Leads',    data: leads,    tension: 0.35, yAxisID: 'y2', pointRadius: 0, borderWidth: 2 },
        { label: 'TOP10',    data: top10,    tension: 0.35, yAxisID: 'y3', pointRadius: 0, borderWidth: 2 }
      ]
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      interaction: { mode: 'index', intersect: false },
      plugins: { legend: { display: false } },
      scales: {
        y:  { position: 'left',  ticks: { callback: v => new Intl.NumberFormat('vi-VN').format(v) } },
        y1: { position: 'right', grid: { drawOnChartArea: false }, ticks: { callback: v => new Intl.NumberFormat('vi-VN').format(v) } },
        y2: { position: 'right', grid: { drawOnChartArea: false }, ticks: { callback: v => new Intl.NumberFormat('vi-VN').format(v) } },
        y3: { position: 'right', display: false, grid: { drawOnChartArea: false }, ticks: { callback: v => new Intl.NumberFormat('vi-VN').format(v) } }
      }
    }
  });
}
</script>

{{-- STYLE --}}
<style>
/* HERO */
.ego-hero{
    border-radius: 18px;
    padding: 18px;
    background: linear-gradient(90deg, rgba(224,242,254,.75), rgba(255,255,255,1));
    border: 1px solid rgba(15,23,42,.08);
    box-shadow: 0 10px 24px rgba(15,23,42,.06);
}
.ego-hero-ic{
    width: 44px; height: 44px;
    border-radius: 14px;
    display:flex; align-items:center; justify-content:center;
    background: rgba(56,189,248,.18);
    color: #0ea5e9;
    font-size: 20px;
}
.ego-hero-title{
    font-size: 28px;
    font-weight: 900;
    letter-spacing: .2px;
    color: #0f172a;
    line-height: 1.1;
}
.ego-hero-sub{
    margin-top: 3px;
    color: rgba(15,23,42,.65);
    font-size: 13px;
}

/* Segmented */
.ego-seg{
    display:inline-flex;
    border-radius: 14px;
    padding: 4px;
    border: 1px solid rgba(15,23,42,.10);
    background: rgba(255,255,255,.7);
    box-shadow: 0 10px 20px rgba(15,23,42,.05);
}
.ego-seg-btn{
    text-decoration:none;
    color: rgba(15,23,42,.70);
    font-weight: 800;
    font-size: 13px;
    padding: 8px 12px;
    border-radius: 12px;
    display:inline-flex;
    align-items:center;
}
.ego-seg-btn.active{
    background: #16a34a;
    color: #fff;
    box-shadow: 0 10px 20px rgba(22,163,74,.25);
}

/* CARD */
.ego-card{
    border-radius: 16px;
    border: 1px solid rgba(15,23,42,.10);
    box-shadow: 0 10px 24px rgba(15,23,42,.06);
}
.ego-card-header{
    background: transparent;
    border-bottom: 1px solid rgba(15,23,42,.08);
    padding: 12px 14px;
}

/* INPUT/BUTTON */
.ego-input{ border-radius: 12px; }
.ego-btn{
    border-radius: 12px;
    padding: 10px 12px;
    font-weight: 600;
}
.ego-btn-primary{
    border-radius: 12px;
    padding: 10px 12px;
    font-weight: 800;
}

/* Pills */
.ego-mini-pill{
    display:inline-flex;
    align-items:center;
    gap:6px;
    padding: 6px 10px;
    border-radius: 999px;
    border: 1px solid rgba(15,23,42,.10);
    background: rgba(255,255,255,.7);
    font-size: 12px;
    font-weight: 700;
    color: rgba(15,23,42,.75);
}

/* KPI */
.ego-kpi{
    border-radius: 16px;
    border: 1px solid rgba(15,23,42,.10);
    box-shadow: 0 10px 24px rgba(15,23,42,.06);
    padding: 14px;
    background: #fff;
}
.ego-kpi-head{
    display:flex;
    align-items:flex-start;
    justify-content:space-between;
    gap: 12px;
}
.ego-kpi-label{
    color: rgba(15,23,42,.70);
    font-size: 13px;
    font-weight: 800;
}
.ego-kpi-value{
    margin-top: 6px;
    font-size: 22px;
    font-weight: 900;
    color: #0f172a;
}
.ego-kpi-sub{
    margin-top: 2px;
    font-size: 12px;
    color: rgba(15,23,42,.55);
}
.ego-kpi-ic{
    width: 44px; height: 44px;
    border-radius: 16px;
    display:flex; align-items:center; justify-content:center;
    background: rgba(34,197,94,.12);
    color: #16a34a;
    font-size: 20px;
}

/* Delta badge */
.ego-delta{
    display:inline-flex;
    align-items:center;
    padding: 2px 8px;
    border-radius: 999px;
    font-size: 11px;
    font-weight: 900;
    border: 1px solid rgba(15,23,42,.08);
}
.ego-delta.up{ background: rgba(34,197,94,.12); color:#16a34a; border-color: rgba(34,197,94,.25); }
.ego-delta.down{ background: rgba(239,68,68,.12); color:#dc2626; border-color: rgba(239,68,68,.25); }
.ego-delta.neutral{ background: rgba(148,163,184,.15); color: rgba(15,23,42,.60); }

/* VAT UI */
.ego-kpi .ego-kpi-value-strong{
    font-size: 24px !important;
    font-weight: 900 !important;
    color: #0f172a !important;
}
.ego-badge-vat{
    display: inline-flex;
    align-items: center;
    padding: 3px 8px;
    border-radius: 999px;
    font-size: 11px;
    font-weight: 900;
    line-height: 1;
    border: 1px solid rgba(34,197,94,.25);
    background: rgba(34,197,94,.12);
    color: #16a34a;
}
.ego-kpi-sub-strong{
    font-weight: 900;
    color: rgba(15,23,42,.78);
}

/* Chart */
.ego-chart-wrap{
    position: relative;
    height: 280px;
}
.ego-chart-legend{
    font-size: 12px;
    font-weight: 800;
    color: rgba(15,23,42,.60);
}
.ego-dot{
    display:inline-block;
    width:10px; height:10px;
    border-radius:999px;
    background: rgba(2,132,199,.8);
    margin-right:6px;
    vertical-align:middle;
}
.ego-dot.alt{ background: rgba(22,163,74,.8); }
.ego-dot.alt2{ background: rgba(245,158,11,.85); }
.ego-dot.alt3{ background: rgba(99,102,241,.85); }

/* Empty */
.ego-empty{
    border: 1px dashed rgba(15,23,42,.18);
    border-radius: 16px;
    padding: 22px;
    text-align:center;
    background: rgba(248,250,252,.8);
}
.ego-empty-ic{
    width: 52px; height: 52px;
    margin: 0 auto 8px;
    border-radius: 18px;
    display:flex; align-items:center; justify-content:center;
    background: rgba(56,189,248,.16);
    color:#0284c7;
    font-size: 22px;
}

/* Funnel */
.ego-funnel-row{
    display:grid;
    grid-template-columns: 92px 1fr 72px;
    gap: 10px;
    align-items:center;
    margin-bottom: 10px;
}
.ego-funnel-label{
    font-size: 12px;
    font-weight: 900;
    color: rgba(15,23,42,.70);
}
.ego-funnel-bar{
    height: 10px;
    border-radius: 999px;
    background: rgba(148,163,184,.18);
    overflow:hidden;
}
.ego-funnel-fill{
    height: 100%;
    border-radius: 999px;
    background: rgba(22,163,74,.85);
}
.ego-funnel-val{
    text-align:right;
    font-size: 12px;
    font-weight: 900;
    color: rgba(15,23,42,.80);
}

/* Pacing */
.ego-progress{
    height: 10px;
    border-radius: 999px;
    background: rgba(148,163,184,.18);
    overflow:hidden;
}
.ego-progress-bar{
    height:100%;
    border-radius:999px;
    background: rgba(22,163,74,.9);
}
.ego-progress-bar.over{ background: rgba(239,68,68,.85); }
.ego-progress-bar.under{ background: rgba(245,158,11,.90); }

.ego-status{
    display:inline-flex;
    align-items:center;
    padding: 6px 10px;
    border-radius: 999px;
    font-size: 12px;
    font-weight: 900;
    border: 1px solid rgba(15,23,42,.08);
}
.ego-status.ok{ background: rgba(34,197,94,.12); color:#16a34a; border-color: rgba(34,197,94,.25); }
.ego-status.over{ background: rgba(239,68,68,.12); color:#dc2626; border-color: rgba(239,68,68,.25); }
.ego-status.under{ background: rgba(245,158,11,.12); color:#b45309; border-color: rgba(245,158,11,.25); }

/* Insights */
.ego-insights{ display:flex; flex-direction:column; gap:10px; }
.ego-insight{
    display:flex; gap:10px; align-items:flex-start;
    padding: 10px 10px;
    border-radius: 14px;
    border: 1px solid rgba(15,23,42,.08);
    background: rgba(248,250,252,.85);
}
.ego-insight-ic{
    width: 34px; height: 34px;
    border-radius: 12px;
    display:flex; align-items:center; justify-content:center;
    font-size: 16px;
}
.ego-insight-txt{ font-size: 12.5px; font-weight: 800; color: rgba(15,23,42,.75); line-height: 1.35; }

.ego-insight.info .ego-insight-ic{ background: rgba(56,189,248,.18); color:#0284c7; }
.ego-insight.ok .ego-insight-ic{ background: rgba(34,197,94,.14); color:#16a34a; }
.ego-insight.warn .ego-insight-ic{ background: rgba(245,158,11,.14); color:#b45309; }
.ego-insight.danger .ego-insight-ic{ background: rgba(239,68,68,.14); color:#dc2626; }

/* TABLE */
.ego-table thead th{
    font-size: 12px;
    color: rgba(15,23,42,.65);
    font-weight: 900;
    border-top: 0;
}
.ego-table tbody td{ font-size: 13px; }
.ego-table tbody tr:hover{ background: rgba(2,132,199,.05); }

/* SEO Plan small UI */
.ego-plan-field{
    border: 1px solid rgba(15,23,42,.08);
    background: rgba(248,250,252,.85);
    border-radius: 14px;
    padding: 10px 12px;
}
.ego-plan-label{ font-size: 12px; font-weight: 900; color: rgba(15,23,42,.70); }
.ego-plan-val{ margin-top: 4px; font-size: 18px; font-weight: 900; color: #0f172a; }
.ego-plan-sub{ margin-top: 2px; }

.ego-note{
    border: 1px dashed rgba(15,23,42,.18);
    background: rgba(248,250,252,.9);
    border-radius: 14px;
    padding: 10px 12px;
    font-size: 13px;
    color: rgba(15,23,42,.75);
}
.ego-checklist{ display:flex; flex-direction:column; gap:8px; }
.ego-check{ font-weight: 800; color: rgba(15,23,42,.70); display:flex; align-items:center; gap:8px; }
.ego-check i{ color: rgba(15,23,42,.35); }

.ego-status-chip{
    display:inline-flex;
    align-items:center;
    padding: 6px 10px;
    border-radius: 999px;
    font-size: 12px;
    font-weight: 900;
    border: 1px solid rgba(15,23,42,.08);
    background: rgba(148,163,184,.12);
    color: rgba(15,23,42,.75);
}

/* MOBILE */
@media (max-width: 991px){
    .ego-hero-title{ font-size: 22px; }
    .ego-hero{ padding: 14px; }
    .ego-seg-btn{ padding: 8px 10px; }
    .ego-chart-wrap{ height: 240px; }
}
</style>
@endsection