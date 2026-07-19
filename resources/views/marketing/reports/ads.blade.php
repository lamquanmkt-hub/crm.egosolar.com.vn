@extends('layouts.app')

@section('title', 'Báo cáo ADS')

@section('content')
@php
    $filters = $filters ?? [];
    $from = $from ?? ($filters['from'] ?? now()->startOfMonth()->toDateString());
    $to = $to ?? ($filters['to'] ?? now()->toDateString());
    $channel = $channel ?? ($filters['channel'] ?? '');
    $campaign = $campaign ?? ($filters['campaign'] ?? '');
    $compare = $compare ?? ($filters['compare'] ?? '0');

    $kpi = $kpi ?? (object)['spend'=>0,'impressions'=>0,'clicks'=>0,'leads'=>0,'revenue'=>0,'roas'=>0,'cpl'=>0,'ctr'=>0,'cpc'=>0];
    $rows = $rows ?? collect();
    $daily = $daily ?? collect();
    $channels = $channels ?? ($channelOptions ?? collect());
    $campaigns = $campaigns ?? ($campaignOptions ?? collect());
    $deltas = $deltas ?? [];

    $money = fn($v) => number_format((float)($v ?? 0), 0, ',', '.') . ' đ';
    $num = fn($v) => number_format((float)($v ?? 0), 0, ',', '.');
    $rate = fn($v) => number_format((float)($v ?? 0), 2, ',', '.') . '%';

    $spendVat = ((float)($kpi->spend ?? 0)) * 1.1;
    $ctr = isset($kpi->ctr)
        ? (float)$kpi->ctr
        : (((float)($kpi->impressions ?? 0) > 0) ? round(((float)($kpi->clicks ?? 0) / (float)$kpi->impressions) * 100, 2) : 0);
    $cpc = isset($kpi->cpc)
        ? (float)$kpi->cpc
        : (((float)($kpi->clicks ?? 0) > 0) ? round(((float)($kpi->spend ?? 0) / (float)$kpi->clicks), 0) : 0);
@endphp

<style>
    .adsr-page {
        --text: #0f172a;
        --muted: #64748b;
        --line: #e5edf7;
        --blue: #2563eb;
        --green: #059669;
        --amber: #f59e0b;
        --red: #e11d48;
        padding: 24px 28px 42px;
        background: linear-gradient(180deg, #f7fbff 0%, #f8fafc 44%, #fff 100%);
        min-height: calc(100vh - 70px);
        color: var(--text);
    }

    .adsr-hero {
        border-radius: 28px;
        padding: 26px;
        background:
            radial-gradient(circle at 5% 10%, rgba(37,99,235,.20), transparent 30%),
            radial-gradient(circle at 90% 10%, rgba(16,185,129,.16), transparent 28%),
            linear-gradient(135deg, #0f172a, #1e3a8a 58%, #2563eb);
        color: #fff;
        box-shadow: 0 26px 70px rgba(37,99,235,.22);
        display: grid;
        grid-template-columns: minmax(0, 1fr) auto;
        gap: 18px;
        align-items: center;
        margin-bottom: 18px;
    }

    .adsr-hero h1 {
        margin: 0 0 8px;
        font-size: 30px;
        font-weight: 950;
        letter-spacing: -.04em;
    }

    .adsr-hero p {
        margin: 0;
        color: rgba(255,255,255,.78);
    }

    .adsr-actions {
        display: flex;
        gap: 10px;
        flex-wrap: wrap;
        justify-content: flex-end;
    }

    .adsr-btn {
        height: 44px;
        border-radius: 14px;
        border: 0;
        padding: 0 16px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        font-weight: 900;
        text-decoration: none;
        cursor: pointer;
        white-space: nowrap;
    }

    .adsr-btn-primary {
        background: linear-gradient(135deg, #2563eb, #1d4ed8);
        color: #fff;
        box-shadow: 0 14px 30px rgba(37,99,235,.26);
    }

    .adsr-btn-white {
        background: rgba(255,255,255,.14);
        color: #fff;
        border: 1px solid rgba(255,255,255,.22);
    }

    .adsr-btn-light {
        background: #fff;
        color: #1d4ed8;
        border: 1px solid var(--line);
    }

    .adsr-alert {
        padding: 13px 15px;
        border-radius: 16px;
        margin-bottom: 14px;
        font-weight: 800;
        background: #dcfce7;
        color: #047857;
    }

    .adsr-filter {
        background: #fff;
        border: 1px solid var(--line);
        border-radius: 24px;
        padding: 18px;
        box-shadow: 0 20px 50px rgba(15,23,42,.06);
        margin-bottom: 18px;
    }

    .adsr-filter-grid {
        display: grid;
        grid-template-columns: 160px 160px 1fr 1fr 180px auto;
        gap: 12px;
        align-items: end;
    }

    .adsr-field label {
        display: block;
        font-size: 12px;
        font-weight: 950;
        color: #475569;
        margin-bottom: 7px;
    }

    .adsr-input,
    .adsr-select {
        width: 100%;
        height: 45px;
        border: 1px solid var(--line);
        border-radius: 14px;
        padding: 0 13px;
        outline: none;
        background: #fff;
        color: #0f172a;
        box-shadow: 0 10px 22px rgba(15,23,42,.04);
    }

    .adsr-kpis {
        display: grid;
        grid-template-columns: repeat(6, minmax(0, 1fr));
        gap: 14px;
        margin-bottom: 18px;
    }

    .adsr-kpi {
        background: #fff;
        border: 1px solid var(--line);
        border-radius: 22px;
        padding: 18px;
        box-shadow: 0 24px 54px rgba(15,23,42,.07);
        min-height: 128px;
    }

    .adsr-kpi .k {
        color: #475569;
        font-size: 12px;
        font-weight: 950;
        text-transform: uppercase;
        letter-spacing: .02em;
        margin-bottom: 8px;
    }

    .adsr-kpi .v {
        font-size: 24px;
        font-weight: 950;
        letter-spacing: -.04em;
        margin-bottom: 8px;
    }

    .adsr-kpi .s {
        color: var(--muted);
        font-size: 12px;
        line-height: 1.4;
    }

    .adsr-kpi.blue { border-top: 4px solid var(--blue); }
    .adsr-kpi.green { border-top: 4px solid var(--green); }
    .adsr-kpi.amber { border-top: 4px solid var(--amber); }
    .adsr-kpi.red { border-top: 4px solid var(--red); }

    .adsr-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 18px;
        margin-bottom: 18px;
    }

    .adsr-card {
        background: #fff;
        border: 1px solid var(--line);
        border-radius: 24px;
        box-shadow: 0 24px 56px rgba(15,23,42,.07);
        overflow: hidden;
    }

    .adsr-card-head {
        padding: 18px 20px;
        border-bottom: 1px solid var(--line);
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 12px;
    }

    .adsr-title {
        font-size: 18px;
        font-weight: 950;
        letter-spacing: -.02em;
        margin-bottom: 3px;
    }

    .adsr-sub {
        color: var(--muted);
        font-size: 13px;
    }

    .adsr-chart {
        height: 310px;
        padding: 18px;
    }

    .adsr-pill {
        display: inline-flex;
        align-items: center;
        height: 28px;
        padding: 0 10px;
        border-radius: 999px;
        background: #eaf2ff;
        color: #1d4ed8;
        font-weight: 900;
        font-size: 12px;
    }

    .adsr-table-wrap {
        overflow-x: auto;
    }

    .adsr-table {
        width: 100%;
        border-collapse: collapse;
        min-width: 980px;
    }

    .adsr-table th {
        padding: 14px 16px;
        background: #fbfdff;
        border-bottom: 1px solid var(--line);
        color: #475569;
        font-size: 12px;
        font-weight: 950;
        text-align: left;
        white-space: nowrap;
    }

    .adsr-table td {
        padding: 15px 16px;
        border-bottom: 1px solid #eef2f7;
        vertical-align: middle;
        font-size: 14px;
    }

    .adsr-table tr:hover td {
        background: #fbfdff;
    }

    .adsr-campaign {
        font-weight: 950;
        margin-bottom: 4px;
    }

    .adsr-muted {
        color: var(--muted);
        font-size: 12px;
    }

    .adsr-right {
        text-align: right;
    }

    .adsr-money {
        font-weight: 950;
        white-space: nowrap;
    }

    .adsr-money.green { color: var(--green); }
    .adsr-money.red { color: var(--red); }
    .adsr-money.amber { color: var(--amber); }

    @media (max-width: 1400px) {
        .adsr-kpis { grid-template-columns: repeat(3, minmax(0, 1fr)); }
        .adsr-filter-grid { grid-template-columns: repeat(3, minmax(0, 1fr)); }
    }

    @media (max-width: 992px) {
        .adsr-hero { grid-template-columns: 1fr; }
        .adsr-actions { justify-content: flex-start; }
        .adsr-grid { grid-template-columns: 1fr; }
        .adsr-kpis { grid-template-columns: repeat(2, minmax(0, 1fr)); }
    }

    @media (max-width: 768px) {
        .adsr-page { padding: 16px; }
        .adsr-filter-grid,
        .adsr-kpis {
            grid-template-columns: 1fr;
        }
    }
</style>

<div class="adsr-page">
    <div class="adsr-hero">
        <div>
            <h1>Báo cáo dữ liệu ADS</h1>
            <p>Theo dõi chi tiêu, leads, CPL, CTR và ROAS theo ngày / chiến dịch. Giao diện tối ưu để đọc nhanh dữ liệu import từ Excel Meta ADS.</p>
        </div>

        <div class="adsr-actions">
            <a class="adsr-btn adsr-btn-white" href="{{ url('/marketing/report/ads/input') }}">Import Excel</a>
            <a class="adsr-btn adsr-btn-white" href="{{ url('/marketing/report/ads/input') }}">Nhập liệu</a>
        </div>
    </div>

    @if(session('success'))
        <div class="adsr-alert">{{ session('success') }}</div>
    @endif

    <div class="adsr-filter">
        <form method="GET" action="{{ url('/marketing/report/ads') }}" class="adsr-filter-grid">
            <div class="adsr-field">
                <label>Từ ngày</label>
                <input type="date" name="from" value="{{ $from }}" class="adsr-input">
            </div>

            <div class="adsr-field">
                <label>Đến ngày</label>
                <input type="date" name="to" value="{{ $to }}" class="adsr-input">
            </div>

            <div class="adsr-field">
                <label>Kênh</label>
                <select name="channel" class="adsr-select">
                    <option value="">Tất cả kênh</option>
                    @foreach($channels as $c)
                        <option value="{{ $c }}" {{ $channel === (string)$c ? 'selected' : '' }}>{{ $c }}</option>
                    @endforeach
                </select>
            </div>

            <div class="adsr-field">
                <label>Chiến dịch</label>
                <select name="campaign" class="adsr-select">
                    <option value="">Tất cả chiến dịch</option>
                    @foreach($campaigns as $cp)
                        <option value="{{ $cp }}" {{ $campaign === (string)$cp ? 'selected' : '' }}>{{ $cp }}</option>
                    @endforeach
                </select>
            </div>

            <div class="adsr-field">
                <label>So sánh</label>
                <select name="compare" class="adsr-select">
                    <option value="0" {{ $compare === '0' ? 'selected' : '' }}>Không</option>
                    <option value="1" {{ $compare === '1' ? 'selected' : '' }}>So với kỳ trước</option>
                </select>
            </div>

            <div style="display:flex;gap:10px">
                <button class="adsr-btn adsr-btn-primary" type="submit">Lọc dữ liệu</button>
                <a class="adsr-btn adsr-btn-light" href="{{ url('/marketing/report/ads') }}">Reset</a>
            </div>
        </form>
    </div>

    <div class="adsr-kpis">
        <div class="adsr-kpi blue">
            <div class="k">Chi tiêu</div>
            <div class="v">{{ $money($kpi->spend ?? 0) }}</div>
            <div class="s">Sau VAT 10%: <b>{{ $money($spendVat) }}</b></div>
        </div>

        <div class="adsr-kpi green">
            <div class="k">Leads</div>
            <div class="v" style="color:#059669">{{ $num($kpi->leads ?? 0) }}</div>
            <div class="s">Khách hàng tiềm năng từ ADS.</div>
        </div>

        <div class="adsr-kpi amber">
            <div class="k">CPL</div>
            <div class="v" style="color:#f59e0b">{{ $money($kpi->cpl ?? 0) }}</div>
            <div class="s">Chi phí trung bình / lead.</div>
        </div>

        <div class="adsr-kpi blue">
            <div class="k">Impressions</div>
            <div class="v">{{ $num($kpi->impressions ?? 0) }}</div>
            <div class="s">Lượt hiển thị quảng cáo.</div>
        </div>

        <div class="adsr-kpi red">
            <div class="k">CTR</div>
            <div class="v" style="color:#e11d48">{{ $rate($ctr) }}</div>
            <div class="s">CPC: <b>{{ $money($cpc) }}</b></div>
        </div>

        <div class="adsr-kpi green">
            <div class="k">ROAS</div>
            <div class="v" style="color:#059669">{{ number_format((float)($kpi->roas ?? 0), 2) }}</div>
            <div class="s">Revenue: <b>{{ $money($kpi->revenue ?? 0) }}</b></div>
        </div>
    </div>

    <div class="adsr-grid">
        <div class="adsr-card">
            <div class="adsr-card-head">
                <div>
                    <div class="adsr-title">Chi tiêu & CPL</div>
                    <div class="adsr-sub">Bar: chi tiêu, Line: CPL theo ngày</div>
                </div>
                <span class="adsr-pill">Daily</span>
            </div>
            <div class="adsr-chart"><canvas id="chartSpendCpl"></canvas></div>
        </div>

        <div class="adsr-card">
            <div class="adsr-card-head">
                <div>
                    <div class="adsr-title">Leads theo ngày</div>
                    <div class="adsr-sub">Theo dõi số lead phát sinh</div>
                </div>
                <span class="adsr-pill">Daily</span>
            </div>
            <div class="adsr-chart"><canvas id="chartLeads"></canvas></div>
        </div>
    </div>

    <div class="adsr-card">
        <div class="adsr-card-head">
            <div>
                <div class="adsr-title">Hiệu quả theo chiến dịch</div>
                <div class="adsr-sub">Group theo campaign_name + channel</div>
            </div>
            <span class="adsr-pill">{{ is_countable($rows) ? count($rows) : 0 }} chiến dịch</span>
        </div>

        <div class="adsr-table-wrap">
            <table class="adsr-table">
                <thead>
                    <tr>
                        <th>Chiến dịch</th>
                        <th>Kênh</th>
                        <th class="adsr-right">Chi tiêu</th>
                        <th class="adsr-right">Impressions</th>
                        <th class="adsr-right">Clicks</th>
                        <th class="adsr-right">Leads</th>
                        <th class="adsr-right">CPL</th>
                        <th class="adsr-right">CTR</th>
                        <th class="adsr-right">ROAS</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($rows as $r)
                        @php
                            $sp = (float)($r->spend ?? 0);
                            $ld = (float)($r->leads ?? 0);
                            $rv = (float)($r->revenue ?? 0);
                            $imp = (float)($r->impressions ?? 0);
                            $clk = (float)($r->clicks ?? 0);
                            $cpl = $ld > 0 ? ($sp / $ld) : 0;
                            $roas = $sp > 0 ? ($rv / $sp) : 0;
                            $rowCtr = $imp > 0 ? (($clk / $imp) * 100) : 0;
                        @endphp

                        <tr>
                            <td>
                                <div class="adsr-campaign">{{ $r->campaign_name ?? '—' }}</div>
                                <div class="adsr-muted">external: {{ $r->campaign_external_id ?? '—' }}</div>
                            </td>
                            <td><span class="adsr-pill">{{ $r->channel ?? '—' }}</span></td>
                            <td class="adsr-right adsr-money">{{ $money($sp) }}</td>
                            <td class="adsr-right">{{ $num($imp) }}</td>
                            <td class="adsr-right">{{ $num($clk) }}</td>
                            <td class="adsr-right adsr-money green">{{ $num($ld) }}</td>
                            <td class="adsr-right adsr-money amber">{{ $money($cpl) }}</td>
                            <td class="adsr-right">{{ $rate($rowCtr) }}</td>
                            <td class="adsr-right">{{ number_format($roas, 2) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" style="padding:42px;text-align:center;color:#64748b;font-weight:850">
                                Chưa có dữ liệu. Vào nút Import Excel để upload file mẫu Meta ADS.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
    const daily = @json($daily ?? []);
    const labels = daily.map(x => x.date);
    const spend = daily.map(x => Number(x.spend || 0));
    const leads = daily.map(x => Number(x.leads || 0));
    const cpl = daily.map(x => Number(x.cpl || 0));

    const fmtVnd = (v) => Number(v || 0).toLocaleString('vi-VN') + ' đ';

    if (window.Chart) {
        const chartSpend = document.getElementById('chartSpendCpl');
        if (chartSpend && daily.length) {
            new Chart(chartSpend, {
                data: {
                    labels,
                    datasets: [
                        { type: 'bar', label: 'Chi tiêu', data: spend, yAxisID: 'y', borderRadius: 10 },
                        { type: 'line', label: 'CPL', data: cpl, yAxisID: 'y1', tension: .36, pointRadius: 3 }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    interaction: { mode: 'index', intersect: false },
                    plugins: {
                        legend: { position: 'top' },
                        tooltip: { callbacks: { label: ctx => `${ctx.dataset.label}: ${fmtVnd(ctx.parsed.y || 0)}` } }
                    },
                    scales: {
                        y: { beginAtZero: true },
                        y1: { beginAtZero: true, position: 'right', grid: { drawOnChartArea: false } }
                    }
                }
            });
        }

        const chartLeads = document.getElementById('chartLeads');
        if (chartLeads && daily.length) {
            new Chart(chartLeads, {
                type: 'line',
                data: {
                    labels,
                    datasets: [{ label: 'Leads', data: leads, tension: .36, pointRadius: 3 }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { position: 'top' } },
                    scales: { y: { beginAtZero: true } }
                }
            });
        }
    }
</script>
@endsection
