@extends('layouts.app')

@section('title', 'Nhập liệu ADS')

@section('content')
@php
    $campaignOptions = $campaignOptions ?? collect();
    $channelOptions = $channelOptions ?? collect(['Facebook', 'Google', 'TikTok', 'Website']);
    $recent = $recent ?? collect();
    $latestDate = $latestDate ?? null;
    $todayStats = $todayStats ?? null;

    $money = fn($v) => number_format((float)($v ?? 0), 0, ',', '.') . ' đ';
    $num = fn($v) => number_format((float)($v ?? 0), 0, ',', '.');

    $groupedRecent = $recent
        ->groupBy(fn($item) => trim((string)($item->campaign_name ?? '(no campaign)')) ?: '(no campaign)')
        ->sortByDesc(fn($items) => (float) $items->sum(fn($r) => (float)($r->spend ?? 0)));
@endphp

<style>
    .adsx-page {
        --text: #0f172a;
        --muted: #64748b;
        --line: #e5edf7;
        --blue: #2563eb;
        --green: #059669;
        --amber: #f59e0b;
        --red: #e11d48;
        padding: 24px 28px 42px;
        background: linear-gradient(180deg, #f7fbff 0%, #f8fafc 44%, #ffffff 100%);
        min-height: calc(100vh - 70px);
        color: var(--text);
    }

    .adsx-hero {
        border-radius: 28px;
        background:
            radial-gradient(circle at 8% 15%, rgba(37,99,235,.18), transparent 32%),
            radial-gradient(circle at 88% 12%, rgba(16,185,129,.14), transparent 28%),
            linear-gradient(135deg, #0f172a, #1e3a8a 58%, #2563eb);
        color: #fff;
        padding: 26px;
        box-shadow: 0 26px 70px rgba(37,99,235,.22);
        display: flex;
        justify-content: space-between;
        gap: 22px;
        align-items: center;
        margin-bottom: 18px;
    }

    .adsx-hero h1 {
        margin: 0 0 8px;
        font-weight: 950;
        letter-spacing: -.04em;
        font-size: 30px;
    }

    .adsx-hero p {
        margin: 0;
        color: rgba(255,255,255,.78);
        max-width: 760px;
    }

    .adsx-hero-actions {
        display: flex;
        gap: 10px;
        flex-wrap: wrap;
        justify-content: flex-end;
    }

    .adsx-btn {
        height: 44px;
        border: 0;
        border-radius: 14px;
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

    .adsx-btn-primary {
        background: linear-gradient(135deg, #2563eb, #1d4ed8);
        color: #fff;
        box-shadow: 0 14px 30px rgba(37,99,235,.26);
    }

    .adsx-btn-white {
        background: rgba(255,255,255,.14);
        color: #fff;
        border: 1px solid rgba(255,255,255,.22);
    }

    .adsx-btn-light {
        background: #fff;
        color: #1d4ed8;
        border: 1px solid var(--line);
    }

    .adsx-btn-danger {
        background: #fff1f2;
        color: #be123c;
        border: 1px solid #fecdd3;
    }

    .adsx-alert {
        padding: 13px 15px;
        border-radius: 16px;
        margin-bottom: 14px;
        font-weight: 800;
    }

    .adsx-alert.ok { background: #dcfce7; color: #047857; }
    .adsx-alert.err { background: #ffe4e6; color: #be123c; }
    .adsx-alert.warn { background: #fef3c7; color: #92400e; }

    .adsx-grid {
        display: grid;
        grid-template-columns: minmax(0, 1.15fr) minmax(420px, .85fr);
        gap: 18px;
    }

    .adsx-card {
        background: #fff;
        border: 1px solid var(--line);
        border-radius: 24px;
        box-shadow: 0 24px 60px rgba(15,23,42,.07);
        overflow: hidden;
    }

    .adsx-card-head {
        padding: 20px 22px;
        border-bottom: 1px solid var(--line);
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 12px;
    }

    .adsx-card-title {
        font-weight: 950;
        font-size: 19px;
        letter-spacing: -.02em;
        margin-bottom: 4px;
    }

    .adsx-card-sub {
        color: var(--muted);
        font-size: 13px;
    }

    .adsx-card-body {
        padding: 20px 22px;
    }

    .adsx-import {
        border: 1px dashed #93c5fd;
        background: linear-gradient(180deg, #eff6ff, #fff);
        border-radius: 22px;
        padding: 18px;
        margin-bottom: 16px;
    }

    .adsx-form-grid {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 12px;
    }

    .adsx-form-grid.two {
        grid-template-columns: 180px 1fr auto;
        align-items: end;
    }

    .adsx-field label {
        display: block;
        font-size: 12px;
        font-weight: 950;
        color: #475569;
        margin-bottom: 7px;
    }

    .adsx-input,
    .adsx-select {
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

    .adsx-note {
        color: var(--muted);
        font-size: 12px;
        margin-top: 8px;
        line-height: 1.45;
    }

    .adsx-stat-row {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 12px;
        margin-bottom: 16px;
    }

    .adsx-stat {
        border: 1px solid var(--line);
        border-radius: 18px;
        padding: 14px;
        background: #fbfdff;
    }

    .adsx-stat .k {
        color: var(--muted);
        font-size: 12px;
        font-weight: 900;
        margin-bottom: 5px;
    }

    .adsx-stat .v {
        font-size: 20px;
        font-weight: 950;
    }

    .adsx-table-wrap {
        max-height: 560px;
        overflow: auto;
    }

    .adsx-table {
        width: 100%;
        border-collapse: collapse;
        min-width: 680px;
    }

    .adsx-table th {
        position: sticky;
        top: 0;
        z-index: 2;
        padding: 12px 12px;
        background: #f8fafc;
        border-bottom: 1px solid var(--line);
        color: #475569;
        font-size: 12px;
        font-weight: 950;
        text-align: left;
    }

    .adsx-table td {
        padding: 12px;
        border-bottom: 1px solid #eef2f7;
        vertical-align: middle;
        font-size: 13px;
    }

    .adsx-campaign-row {
        background: #eff6ff;
        cursor: pointer;
    }

    .adsx-campaign-name {
        font-weight: 950;
        color: #0f172a;
    }

    .adsx-muted {
        color: var(--muted);
    }

    .adsx-right {
        text-align: right;
    }

    .adsx-pill {
        display: inline-flex;
        align-items: center;
        height: 26px;
        padding: 0 9px;
        border-radius: 999px;
        font-size: 12px;
        font-weight: 900;
        background: #eaf2ff;
        color: #1d4ed8;
    }

    .span-2 { grid-column: span 2; }
    .span-3 { grid-column: span 3; }

    @media (max-width: 1280px) {
        .adsx-grid { grid-template-columns: 1fr; }
    }

    @media (max-width: 768px) {
        .adsx-page { padding: 16px; }
        .adsx-hero { flex-direction: column; align-items: flex-start; }
        .adsx-form-grid,
        .adsx-form-grid.two,
        .adsx-stat-row {
            grid-template-columns: 1fr;
        }
        .span-2,
        .span-3 {
            grid-column: span 1;
        }
    }
</style>

<div class="adsx-page">
    <div class="adsx-hero">
        <div>
            <h1>Nhập liệu ADS</h1>
            <p>Upload Excel Meta Ads giống file mẫu: Tên chiến dịch, Kết quả, Số tiền đã chi tiêu, Lượt hiển thị, Người tiếp cận. Dữ liệu sẽ tự đổ về báo cáo ADS.</p>
        </div>

        <div class="adsx-hero-actions">
            <a class="adsx-btn adsx-btn-white" href="{{ url('/marketing/report/ads') }}">Về báo cáo ADS</a>
            <a class="adsx-btn adsx-btn-white" href="{{ url('/marketing/report/ads') }}?from={{ $latestDate ?: now()->toDateString() }}&to={{ $latestDate ?: now()->toDateString() }}">Xem ngày mới nhất</a>
        </div>
    </div>

    @if(session('success'))
        <div class="adsx-alert ok">{{ session('success') }}</div>
    @endif

    @if(session('error'))
        <div class="adsx-alert err">{{ session('error') }}</div>
    @endif

    @if($errors->any())
        <div class="adsx-alert warn">
            @foreach($errors->all() as $e)
                <div>{{ $e }}</div>
            @endforeach
        </div>
    @endif

    <div class="adsx-grid">
        <div>
            <div class="adsx-card">
                <div class="adsx-card-head">
                    <div>
                        <div class="adsx-card-title">Import file Excel Meta ADS</div>
                        <div class="adsx-card-sub">Hỗ trợ file .xlsx như mẫu bạn gửi. Mặc định kênh là Facebook.</div>
                    </div>
                    <span class="adsx-pill">Excel import</span>
                </div>

                <div class="adsx-card-body">
                    <div class="adsx-import">
                        <form method="POST" action="{{ url('/marketing/report/ads/import') }}" enctype="multipart/form-data" class="adsx-form-grid two">
                            @csrf

                            <div class="adsx-field">
                                <label>Kênh</label>
                                <select name="channel" class="adsx-select">
                                    @foreach($channelOptions as $c)
                                        <option value="{{ $c }}" {{ $c === 'Facebook' ? 'selected' : '' }}>{{ $c }}</option>
                                    @endforeach
                                    @if(!$channelOptions->contains('Facebook'))
                                        <option value="Facebook" selected>Facebook</option>
                                    @endif
                                </select>
                            </div>

                            <div class="adsx-field">
                                <label>File Excel / CSV</label>
                                <input type="file" name="ads_file" class="adsx-input" accept=".xlsx,.csv" required>
                                <div class="adsx-note">Cột đang đọc: Lượt bắt đầu báo cáo, Lượt kết thúc báo cáo, Tên chiến dịch, Kết quả, Số tiền đã chi tiêu (VND), Lượt hiển thị, Người tiếp cận.</div>
                            </div>

                            <button class="adsx-btn adsx-btn-primary" type="submit">Upload dữ liệu</button>
                        </form>
                    </div>

                    <div class="adsx-stat-row">
                        <div class="adsx-stat">
                            <div class="k">Ngày mới nhất</div>
                            <div class="v">{{ $latestDate ? \Carbon\Carbon::parse($latestDate)->format('d/m/Y') : '—' }}</div>
                        </div>
                        <div class="adsx-stat">
                            <div class="k">Spend ngày mới nhất</div>
                            <div class="v">{{ $money($todayStats->spend ?? 0) }}</div>
                        </div>
                        <div class="adsx-stat">
                            <div class="k">Leads ngày mới nhất</div>
                            <div class="v">{{ $num($todayStats->leads ?? 0) }}</div>
                        </div>
                    </div>

                    <div class="adsx-card-title">Nhập tay / chỉnh nhanh</div>
                    <div class="adsx-card-sub" style="margin-bottom:14px">Dùng khi cần sửa một chiến dịch theo ngày.</div>

                    <form method="POST" action="{{ url('/marketing/report/ads/input') }}" class="adsx-form-grid">
                        @csrf

                        <div class="adsx-field">
                            <label>Ngày</label>
                            <input type="date" name="date" id="date" class="adsx-input" value="{{ old('date', $date ?? now()->toDateString()) }}" required>
                        </div>

                        <div class="adsx-field">
                            <label>Kênh</label>
                            <select name="channel" id="channel" class="adsx-select" required>
                                <option value="">Chọn kênh</option>
                                @foreach($channelOptions as $c)
                                    <option value="{{ $c }}" @selected(old('channel', $channelParam ?? '') == $c)>{{ $c }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="adsx-field">
                            <label>Chiến dịch</label>
                            <input type="text" name="campaign_name" id="campaign_name" class="adsx-input" autocomplete="off" placeholder="Nhập tên chiến dịch" value="{{ old('campaign_name', $campaignName ?? '') }}" required>
                        </div>

                        <div class="adsx-field">
                            <label>Chi tiêu</label>
                            <input type="number" step="0.01" min="0" name="spend" id="spend" class="adsx-input" value="{{ old('spend', 0) }}" required>
                        </div>

                        <div class="adsx-field">
                            <label>Impressions</label>
                            <input type="number" min="0" name="impressions" id="impressions" class="adsx-input" value="{{ old('impressions', 0) }}">
                        </div>

                        <div class="adsx-field">
                            <label>Clicks</label>
                            <input type="number" min="0" name="clicks" id="clicks" class="adsx-input" value="{{ old('clicks', 0) }}">
                        </div>

                        <div class="adsx-field">
                            <label>Leads</label>
                            <input type="number" min="0" name="leads" id="leads" class="adsx-input" value="{{ old('leads', 0) }}">
                        </div>

                        <div class="adsx-field">
                            <label>Revenue</label>
                            <input type="number" step="0.01" min="0" name="revenue" id="revenue" class="adsx-input" value="{{ old('revenue', 0) }}">
                        </div>

                        <div class="adsx-field">
                            <label>CPL auto</label>
                            <input type="text" id="cpl" class="adsx-input" readonly value="0">
                        </div>

                        <div class="span-3" style="display:flex;gap:10px;flex-wrap:wrap">
                            <button type="submit" class="adsx-btn adsx-btn-primary">Lưu dữ liệu</button>
                            <a class="adsx-btn adsx-btn-light" href="{{ url('/marketing/report/ads') }}?from={{ old('date', $date ?? now()->toDateString()) }}&to={{ old('date', $date ?? now()->toDateString()) }}">Xem report ngày này</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="adsx-card">
            <div class="adsx-card-head">
                <div>
                    <div class="adsx-card-title">Dữ liệu gần đây</div>
                    <div class="adsx-card-sub">Bấm campaign để bung chi tiết, bấm dòng con để đổ lại form sửa.</div>
                </div>
                <span class="adsx-pill">{{ $recent->count() }} dòng</span>
            </div>

            <div class="adsx-table-wrap">
                <table class="adsx-table">
                    <thead>
                        <tr>
                            <th></th>
                            <th>Kênh</th>
                            <th>Campaign</th>
                            <th class="adsx-right">Spend</th>
                            <th class="adsx-right">Leads</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($groupedRecent as $campaignName => $items)
                            @php
                                $groupKey = 'grp_' . md5($campaignName);
                                $totalSpend = (float) $items->sum(fn($r) => (float)($r->spend ?? 0));
                                $totalLeads = (int) $items->sum(fn($r) => (int)($r->leads ?? 0));
                                $mainChannel = (string)($items->first()->channel ?? '—');
                            @endphp

                            <tr class="adsx-campaign-row" onclick="toggleCampaignRows('{{ $groupKey }}')">
                                <td><span id="icon-{{ $groupKey }}">▶</span></td>
                                <td><b>{{ $mainChannel }}</b></td>
                                <td><div class="adsx-campaign-name">{{ $campaignName }}</div></td>
                                <td class="adsx-right"><b>{{ $money($totalSpend) }}</b></td>
                                <td class="adsx-right"><b>{{ $num($totalLeads) }}</b></td>
                                <td class="adsx-muted">—</td>
                            </tr>

                            @foreach($items as $r)
                                <tr class="{{ $groupKey }}" style="display:none; cursor:pointer;" onclick='fillAdsForm(@json($r->date), @json($r->channel), @json($r->campaign_name), @json($r->spend), @json($r->impressions), @json($r->clicks), @json($r->leads), @json($r->revenue))'>
                                    <td>{{ \Carbon\Carbon::parse($r->date)->format('d/m') }}</td>
                                    <td>{{ $r->channel }}</td>
                                    <td><span style="padding-left:12px">{{ $r->campaign_name }}</span></td>
                                    <td class="adsx-right">{{ $money($r->spend) }}</td>
                                    <td class="adsx-right">{{ $num($r->leads) }}</td>
                                    <td>
                                        <form method="POST" action="{{ url('/marketing/report/ads/delete') }}" onsubmit="event.stopPropagation(); return confirm('Xóa dòng này?');">
                                            @csrf
                                            <input type="hidden" name="date" value="{{ $r->date }}">
                                            <input type="hidden" name="channel" value="{{ $r->channel }}">
                                            <input type="hidden" name="campaign_name" value="{{ $r->campaign_name }}">
                                            <button type="submit" class="adsx-btn adsx-btn-danger" style="height:32px;padding:0 10px">Xóa</button>
                                        </form>
                                    </td>
                                </tr>
                            @endforeach
                        @empty
                            <tr>
                                <td colspan="6" style="padding:30px;text-align:center;color:#64748b;font-weight:800">Chưa có dữ liệu.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
    function calcCpl() {
        const spend = Number(document.getElementById('spend')?.value || 0);
        const leads = Number(document.getElementById('leads')?.value || 0);
        const cpl = leads > 0 ? Math.round(spend / leads) : 0;
        const cplEl = document.getElementById('cpl');
        if (cplEl) cplEl.value = cpl.toLocaleString('vi-VN') + ' đ';
    }

    function fillAdsForm(date, channel, campaign, spend, impressions, clicks, leads, revenue) {
        document.getElementById('date').value = date || '';
        document.getElementById('channel').value = channel || '';
        document.getElementById('campaign_name').value = campaign || '';
        document.getElementById('spend').value = spend || 0;
        document.getElementById('impressions').value = impressions || 0;
        document.getElementById('clicks').value = clicks || 0;
        document.getElementById('leads').value = leads || 0;
        document.getElementById('revenue').value = revenue || 0;
        calcCpl();
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }

    function toggleCampaignRows(groupKey) {
        const rows = document.querySelectorAll('.' + groupKey);
        const icon = document.getElementById('icon-' + groupKey);
        let opened = false;

        rows.forEach(row => {
            const isHidden = row.style.display === 'none';
            row.style.display = isHidden ? 'table-row' : 'none';
            opened = isHidden;
        });

        if (icon) icon.textContent = opened ? '▼' : '▶';
    }

    ['spend', 'leads'].forEach(id => {
        const el = document.getElementById(id);
        if (el) el.addEventListener('input', calcCpl);
    });

    calcCpl();
</script>
@endsection
