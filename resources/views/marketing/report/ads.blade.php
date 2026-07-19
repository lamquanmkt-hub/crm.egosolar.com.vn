@extends('layouts.app')

{{-- DEBUG_NEW_VERSION_999 --}}
@php
  // Hard fallback: đảm bảo $from/$to luôn tồn tại dù layout/head có gọi
  $filters = $filters ?? [];

  $from = $from ?? ($filters['from'] ?? now()->startOfMonth()->toDateString());
  $to   = $to   ?? ($filters['to'] ?? now()->toDateString());

  $channel  = $channel  ?? ($filters['channel']  ?? '');
  $campaign = $campaign ?? ($filters['campaign'] ?? '');
  $compare  = $compare  ?? ($filters['compare']  ?? '0');

  $kpi = $kpi ?? (object)[
    'spend'=>0,'impressions'=>0,'clicks'=>0,'leads'=>0,'revenue'=>0,'roas'=>0,'cpl'=>0
  ];
@endphp

@push('styles')
<style>
  :root{
    --mr-bg:#f6f8fc;
    --mr-card:#ffffff;
    --mr-text:#0f172a;
    --mr-muted:#64748b;
    --mr-line:rgba(15,23,42,.08);
    --mr-shadow:0 18px 50px rgba(15,23,42,.10);
    --mr-shadow2:0 8px 24px rgba(15,23,42,.08);
    --mr-radius:18px;
  }

  .mr-ads-wrap{ background:var(--mr-bg); border-radius:24px; padding:18px; }

  .mr-title{ font-weight:900; letter-spacing:.2px; color:var(--mr-text); margin:0; }
  .mr-sub{ color:var(--mr-muted); font-size:13px; margin-top:4px; }

  .mr-topbar{
    display:flex; align-items:center; justify-content:space-between; gap:12px; flex-wrap:wrap;
    padding:14px 14px;
    background:linear-gradient(135deg, rgba(99,102,241,.12), rgba(16,185,129,.10), rgba(56,189,248,.12));
    border:1px solid rgba(15,23,42,.08);
    border-radius:22px;
    box-shadow:var(--mr-shadow2);
  }

  .mr-actions{ display:flex; gap:10px; align-items:center; flex-wrap:wrap; }
  .mr-btn{
    border-radius:14px; padding:10px 14px; font-weight:700;
    display:inline-flex; gap:8px; align-items:center;
  }

  .mr-card{
    background:var(--mr-card);
    border:1px solid var(--mr-line);
    border-radius:var(--mr-radius);
    box-shadow:var(--mr-shadow2);
    overflow:hidden;
  }
  .mr-card-hd{
    padding:12px 14px;
    display:flex; align-items:center; justify-content:space-between; gap:10px; flex-wrap:wrap;
    background:linear-gradient(180deg, rgba(15,23,42,.02), rgba(15,23,42,0));
    border-bottom:1px solid var(--mr-line);
  }
  .mr-card-hd .t{ font-weight:800; color:var(--mr-text); }
  .mr-card-hd .s{ color:var(--mr-muted); font-size:12px; }

  .mr-filter-grid{ padding:14px; }
  .mr-filter-grid .form-label{ font-size:12px; color:var(--mr-muted); margin-bottom:6px; font-weight:700; }
  .mr-filter-grid .form-control,.mr-filter-grid .form-select{
    border-radius:14px;
    border:1px solid rgba(15,23,42,.10);
    padding:11px 12px;
  }

  .mr-pill{
    display:inline-flex; gap:8px; align-items:center;
    padding:8px 12px;
    border-radius:999px;
    border:1px solid rgba(15,23,42,.10);
    background:rgba(255,255,255,.65);
    color:var(--mr-text);
    font-weight:800;
    font-size:12px;
  }

  .mr-kpi{ padding:14px; height:100%; }
  .mr-kpi .k{ color:var(--mr-muted); font-size:12px; font-weight:800; text-transform:uppercase; letter-spacing:.4px; }
  .mr-kpi .v{ font-size:28px; font-weight:900; color:var(--mr-text); margin-top:4px; line-height:1.1; }
  .mr-kpi .sub{ margin-top:8px; color:var(--mr-muted); font-size:12px; display:flex; gap:10px; align-items:center; flex-wrap:wrap; }

  .mr-badge{
    display:inline-flex; gap:6px; align-items:center;
    padding:6px 10px;
    border-radius:999px;
    border:1px solid rgba(15,23,42,.10);
    background:rgba(15,23,42,.03);
    font-weight:800;
    font-size:12px;
    color:var(--mr-text);
  }
  .mr-badge.good{ background:rgba(16,185,129,.10); border-color:rgba(16,185,129,.25); }
  .mr-badge.bad{ background:rgba(239,68,68,.10); border-color:rgba(239,68,68,.25); }

  .mr-charts{ padding:14px; }
  .mr-chart-box{ height:320px; }

  .table thead th{
    font-size:12px; color:var(--mr-muted); font-weight:900;
    border-bottom:1px solid var(--mr-line)!important;
    text-transform:uppercase; letter-spacing:.35px;
    background:rgba(15,23,42,.02);
  }
  .table tbody td{
    border-top:1px solid rgba(15,23,42,.06)!important;
    vertical-align:middle;
  }
  .mr-td-title{ font-weight:800; color:var(--mr-text); }
  .mr-td-sub{ color:var(--mr-muted); font-size:12px; margin-top:2px; }

  .mr-empty{ padding:22px; color:var(--mr-muted); text-align:center; }

  @media (max-width: 576px){
    .mr-kpi .v{ font-size:24px; }
    .mr-chart-box{ height:260px; }
  }
</style>
@endpush

@section('content')
@php
  $fmtMoney = fn($v) => number_format((float)($v ?? 0),0,',','.') . ' đ';
  $fmtInt   = fn($v) => number_format((float)($v ?? 0),0,',','.');

  $cmp = $compareKpi ?? null;

  $delta = function($cur, $prev){
    $cur=(float)$cur; $prev=(float)$prev;
    if ($prev <= 0) return null;
    return round((($cur-$prev)/$prev)*100, 1);
  };

  $dSpend = $cmp ? $delta($kpi->spend ?? 0, $cmp->spend ?? 0) : null;
  $dImp   = $cmp ? $delta($kpi->impressions ?? 0, $cmp->impressions ?? 0) : null;
  $dClk   = $cmp ? $delta($kpi->clicks ?? 0, $cmp->clicks ?? 0) : null;
  $dLead  = $cmp ? $delta($kpi->leads ?? 0, $cmp->leads ?? 0) : null;
  $dRoas  = $cmp ? $delta($kpi->roas ?? 0, $cmp->roas ?? 0) : null;

  $rangeText = \Carbon\Carbon::parse($from)->format('d/m/Y') . ' đến ' . \Carbon\Carbon::parse($to)->format('d/m/Y');
@endphp

<div class="container-fluid py-3">
  <div class="mr-ads-wrap">

    <div class="mr-topbar mb-3">
      <div>
        <div class="d-flex align-items-center gap-2 flex-wrap">
          <h4 class="mr-title">Báo cáo quảng cáo</h4>
          <span class="mr-pill">📅 {{ $rangeText }}</span>
          <span class="mr-pill">Nguồn: mkt_actual_kpi_daily</span>
        </div>
        <div class="mr-sub">Chỉ hiển thị dữ liệu (không nhập tay) • Lọc theo ngày/kênh/chiến dịch • Có biểu đồ</div>
      </div>

      <div class="mr-actions">
        <a class="btn btn-outline-secondary mr-btn" href="{{ url()->current() . '?' . http_build_query(array_merge(request()->all(), ['export'=>'pdf'])) }}">
          <i class="bi bi-file-earmark-pdf"></i> Xuất PDF
        </a>
        <button type="submit" form="mrFilterForm" class="btn btn-success mr-btn">
          <i class="bi bi-funnel"></i> Lọc dữ liệu
        </button>
      </div>
    </div>

    {{-- FILTERS --}}
    <div class="mr-card mb-3">
      <div class="mr-card-hd">
        <div>
          <div class="t">Bộ lọc</div>
          <div class="s">Chọn khoảng ngày + kênh + chiến dịch (campaign_name)</div>
        </div>
        <div class="d-flex gap-2 flex-wrap align-items-center">
          <span class="mr-badge">So sánh kỳ trước: {{ $compare==='1' ? 'Bật' : 'Tắt' }}</span>
        </div>
      </div>

      <div class="mr-filter-grid">
        <form id="mrFilterForm" method="GET" class="row g-2">
          <div class="col-12 col-md-3">
            <label class="form-label">Từ ngày</label>
            <input type="date" name="from" class="form-control" value="{{ $from }}">
          </div>

          <div class="col-12 col-md-3">
            <label class="form-label">Đến ngày</label>
            <input type="date" name="to" class="form-control" value="{{ $to }}">
          </div>

          <div class="col-12 col-md-3">
            <label class="form-label">Kênh</label>
            <select name="channel" class="form-select">
              <option value="">Tất cả kênh</option>
              @foreach(($channels ?? []) as $ch)
                <option value="{{ $ch }}" {{ $channel===(string)$ch ? 'selected' : '' }}>{{ $ch }}</option>
              @endforeach
            </select>
          </div>

          <div class="col-12 col-md-3">
            <label class="form-label">Chiến dịch</label>
            <select name="campaign" class="form-select">
              <option value="">Tất cả chiến dịch</option>
              @foreach(($campaigns ?? []) as $cp)
                <option value="{{ $cp }}" {{ $campaign===(string)$cp ? 'selected' : '' }}>{{ $cp }}</option>
              @endforeach
            </select>
          </div>

          <div class="col-12 col-md-3">
            <label class="form-label">So sánh</label>
            <select name="compare" class="form-select">
              <option value="0" {{ $compare==='0' ? 'selected' : '' }}>Không</option>
              <option value="1" {{ $compare==='1' ? 'selected' : '' }}>So với kỳ trước (cùng số ngày)</option>
            </select>
          </div>

          <div class="col-12 col-md-9 d-flex align-items-end gap-2 flex-wrap">
            <a class="btn btn-outline-secondary mr-btn" href="{{ url()->current() }}">
              <i class="bi bi-arrow-counterclockwise"></i> Reset
            </a>
            <div class="text-muted small">
              Tip: nếu “Chiến dịch” không thấy dữ liệu → kiểm tra cột <b>campaign_name</b> trong bảng actual.
            </div>
          </div>
        </form>
      </div>
    </div>

    {{-- KPI --}}
    <div class="row g-3 mb-3">
      <div class="col-12 col-md-4 col-xl-3">
        <div class="mr-card mr-kpi">
          <div class="k">Chi tiêu</div>
          <div class="v">{{ $fmtMoney($kpi->spend ?? 0) }}</div>
          <div class="sub">
            @if($compare==='1' && $dSpend !== null)
              <span class="mr-badge {{ $dSpend>=0 ? 'good' : 'bad' }}">▲ {{ $dSpend }}%</span>
              <span>kỳ trước: <b>{{ $fmtMoney($cmp->spend ?? 0) }}</b></span>
            @else
              <span class="mr-badge">—</span>
              <span>không so sánh</span>
            @endif
          </div>
        </div>
      </div>

      <div class="col-12 col-md-4 col-xl-3">
        <div class="mr-card mr-kpi">
          <div class="k">Impressions</div>
          <div class="v">{{ $fmtInt($kpi->impressions ?? 0) }}</div>
          <div class="sub">
            @if($compare==='1' && $dImp !== null)
              <span class="mr-badge {{ $dImp>=0 ? 'good' : 'bad' }}">▲ {{ $dImp }}%</span>
              <span>kỳ trước: <b>{{ $fmtInt($cmp->impressions ?? 0) }}</b></span>
            @else
              <span class="mr-badge">—</span>
              <span>không so sánh</span>
            @endif
          </div>
        </div>
      </div>

      <div class="col-12 col-md-4 col-xl-2">
        <div class="mr-card mr-kpi">
          <div class="k">Clicks</div>
          <div class="v">{{ $fmtInt($kpi->clicks ?? 0) }}</div>
          <div class="sub">
            @if($compare==='1' && $dClk !== null)
              <span class="mr-badge {{ $dClk>=0 ? 'good' : 'bad' }}">▲ {{ $dClk }}%</span>
              <span>kỳ trước: <b>{{ $fmtInt($cmp->clicks ?? 0) }}</b></span>
            @else
              <span class="mr-badge">—</span>
              <span>không so sánh</span>
            @endif
          </div>
        </div>
      </div>

      <div class="col-12 col-md-6 col-xl-2">
        <div class="mr-card mr-kpi">
          <div class="k">Leads</div>
          <div class="v">{{ $fmtInt($kpi->leads ?? 0) }}</div>
          <div class="sub">
            @if($compare==='1' && $dLead !== null)
              <span class="mr-badge {{ $dLead>=0 ? 'good' : 'bad' }}">▲ {{ $dLead }}%</span>
              <span>kỳ trước: <b>{{ $fmtInt($cmp->leads ?? 0) }}</b></span>
            @else
              <span class="mr-badge">—</span>
              <span>không so sánh</span>
            @endif
          </div>
        </div>
      </div>

      <div class="col-12 col-md-6 col-xl-2">
        <div class="mr-card mr-kpi">
          <div class="k">ROAS</div>
          <div class="v">{{ number_format((float)($kpi->roas ?? 0),2) }}</div>
          <div class="sub">
            <span class="mr-badge">CPL: {{ $fmtMoney($kpi->cpl ?? 0) }}</span>
            @if($compare==='1' && $dRoas !== null)
              <span class="mr-badge {{ $dRoas>=0 ? 'good' : 'bad' }}">▲ {{ $dRoas }}%</span>
            @endif
          </div>
        </div>
      </div>
    </div>

    {{-- Charts --}}
    <div class="row g-3 mb-3">
      <div class="col-12 col-lg-6">
        <div class="mr-card">
          <div class="mr-card-hd">
            <div>
              <div class="t">Chi tiêu & CPL</div>
              <div class="s">Bar: Chi tiêu • Line: CPL</div>
            </div>
            <span class="mr-badge">Daily</span>
          </div>
          <div class="mr-charts">
            <div class="mr-chart-box">
              <canvas id="chartSpendCpl"></canvas>
            </div>
          </div>
        </div>
      </div>

      <div class="col-12 col-lg-6">
        <div class="mr-card">
          <div class="mr-card-hd">
            <div>
              <div class="t">Leads theo ngày</div>
              <div class="s">Line chart</div>
            </div>
            <span class="mr-badge">Daily</span>
          </div>
          <div class="mr-charts">
            <div class="mr-chart-box">
              <canvas id="chartLeads"></canvas>
            </div>
          </div>
        </div>
      </div>
    </div>

    {{-- Table --}}
    <div class="mr-card">
      <div class="mr-card-hd">
        <div>
          <div class="t">Chiến dịch quảng cáo</div>
          <div class="s">Group theo campaign_name + channel</div>
        </div>
        <span class="mr-badge">{{ is_countable($rows ?? []) ? count($rows) : 0 }} dòng</span>
      </div>

      <div class="table-responsive">
        <table class="table table-hover mb-0 align-middle">
          <thead>
            <tr>
              <th>Chiến dịch</th>
              <th>Kênh</th>
              <th class="text-end">Chi tiêu</th>
              <th class="text-end">Impressions</th>
              <th class="text-end">Clicks</th>
              <th class="text-end">Leads</th>
              <th class="text-end">CPL</th>
              <th class="text-end">ROAS</th>
            </tr>
          </thead>
          <tbody>
            @forelse(($rows ?? []) as $r)
              @php
                $sp = (float)($r->spend ?? 0);
                $ld = (float)($r->leads ?? 0);
                $rv = (float)($r->revenue ?? 0);
                $cpl = $ld>0 ? ($sp/$ld) : 0;
                $roas = $sp>0 ? ($rv/$sp) : 0;
              @endphp
              <tr>
                <td>
                  <div class="mr-td-title">{{ $r->campaign_name ?? '—' }}</div>
                  <div class="mr-td-sub">external: {{ $r->campaign_external_id ?? '—' }}</div>
                </td>
                <td class="fw-semibold">{{ $r->channel ?? '—' }}</td>
                <td class="text-end fw-semibold">{{ $fmtMoney($sp) }}</td>
                <td class="text-end">{{ $fmtInt($r->impressions ?? 0) }}</td>
                <td class="text-end">{{ $fmtInt($r->clicks ?? 0) }}</td>
                <td class="text-end fw-semibold">{{ $fmtInt($ld) }}</td>
                <td class="text-end">{{ $fmtMoney($cpl) }}</td>
                <td class="text-end">{{ number_format($roas,2) }}</td>
              </tr>
            @empty
              <tr>
                <td colspan="8" class="mr-empty">
                  Chưa có dữ liệu theo bộ lọc hiện tại.
                </td>
              </tr>
            @endforelse
          </tbody>
        </table>
      </div>
    </div>

  </div>
</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
  const daily = @json($daily ?? []);
  const labels = daily.map(x => x.date);
  const spend  = daily.map(x => Number(x.spend || 0));
  const leads  = daily.map(x => Number(x.leads || 0));
  const cpl    = daily.map(x => Number(x.cpl || 0));

  const fmtVnd = (v) => (Number(v||0)).toLocaleString('vi-VN') + ' đ';

  (function(){
    const el = document.getElementById('chartSpendCpl');
    if (!el || !daily || daily.length === 0) return;

    new Chart(el, {
      data: {
        labels,
        datasets: [
          { type: 'bar', label: 'Chi tiêu', data: spend, yAxisID: 'y', borderWidth: 0, borderRadius: 10 },
          { type: 'line', label: 'CPL', data: cpl, yAxisID: 'y1', tension: 0.35, pointRadius: 3 }
        ]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        interaction: { mode: 'index', intersect: false },
        plugins: {
          legend: { position: 'top' },
          tooltip: { callbacks: { label: (ctx) => `${ctx.dataset.label}: ${fmtVnd(ctx.parsed.y ?? 0)}` } }
        },
        scales: {
          y:  { beginAtZero: true, ticks: { callback: (v)=> (Number(v)).toLocaleString('vi-VN') } },
          y1: { beginAtZero: true, position: 'right', grid: { drawOnChartArea: false },
                ticks: { callback: (v)=> (Number(v)).toLocaleString('vi-VN') } }
        }
      }
    });
  })();

  (function(){
    const el = document.getElementById('chartLeads');
    if (!el || !daily || daily.length === 0) return;

    new Chart(el, {
      type: 'line',
      data: { labels, datasets: [{ label: 'Leads', data: leads, tension: 0.35, pointRadius: 3 }] },
      options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'top' } }, scales: { y: { beginAtZero: true } } }
    });
  })();
</script>
@endpush