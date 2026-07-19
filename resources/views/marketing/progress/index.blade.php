@extends('layouts.app')

@push('styles')
<style>
  :root{
    --aqua:#22d3ee; --indigo:#6366f1; --emerald:#10b981; --amber:#f59e0b; --red:#ef4444;
    --stroke: rgba(15,23,42,.10);
    --soft: rgba(255,255,255,.86);
    --shadow: 0 18px 60px rgba(0,0,0,.14);
    --txt: rgba(15,23,42,.90);
    --muted: rgba(15,23,42,.62);
  }
  .wrap{
    border-radius: 18px; padding: 14px;
    background:
      radial-gradient(900px 520px at 8% 6%, rgba(34,211,238,.18), transparent 55%),
      radial-gradient(900px 520px at 92% 10%, rgba(99,102,241,.14), transparent 55%);
  }
  .cardx{
    border-radius: 18px; background: var(--soft);
    border: 1px solid var(--stroke); box-shadow: var(--shadow);
    backdrop-filter: blur(10px); overflow:hidden;
  }
  .top{
    display:flex; align-items:flex-end; justify-content:space-between; gap:12px; flex-wrap:wrap;
    margin-bottom: 12px;
  }
  .title{ font-weight:950; margin:0; color:var(--txt); }
  .sub{ margin-top:4px; font-size:13px; font-weight:650; color:var(--muted); }
  .btnx{
    border-radius:14px; padding:10px 14px; font-weight:850; white-space:nowrap;
    border:1px solid rgba(15,23,42,.10);
    background: rgba(255,255,255,.74);
    box-shadow: 0 10px 28px rgba(0,0,0,.08);
  }
  .btnx-primary{
    color:#fff !important; border:0 !important;
    background: linear-gradient(135deg, rgba(34,211,238,.96), rgba(99,102,241,.92));
    box-shadow: 0 14px 40px rgba(34,211,238,.20);
  }
  .kpis{
    display:grid; grid-template-columns: repeat(4, 1fr);
    gap:10px; padding: 12px 14px;
    border-bottom: 1px solid rgba(15,23,42,.08);
  }
  @media (max-width: 1200px){ .kpis{ grid-template-columns: repeat(2, 1fr);} }
  @media (max-width: 576px){ .kpis{ grid-template-columns: 1fr;} }

  .kpi{
    border-radius:16px; background: rgba(255,255,255,.74);
    border:1px solid rgba(15,23,42,.08); padding: 12px;
  }
  .kpi h6{ margin:0; font-weight:950; font-size:12px; text-transform:uppercase; letter-spacing:.25px; color:var(--muted);}
  .kpi .v{ margin-top:6px; font-weight:950; font-size:22px; color:var(--txt); }
  .bar{ margin-top:10px; height:10px; border-radius:999px; background: rgba(15,23,42,.08); overflow:hidden; }
  .bar > div{ height:100%; width:0%; border-radius:999px; background: linear-gradient(135deg, rgba(34,211,238,.95), rgba(99,102,241,.90)); }

  .rowx{
    display:grid; grid-template-columns: repeat(3, 1fr);
    gap:10px; padding: 12px 14px;
  }
  @media (max-width: 992px){ .rowx{ grid-template-columns: 1fr; } }

  .chan{
    border-radius: 16px; background: rgba(255,255,255,.74);
    border:1px solid rgba(15,23,42,.08); padding: 12px;
    display:flex; flex-direction:column; gap:10px;
  }
  .tag{
    display:inline-flex; align-items:center; gap:8px;
    padding:6px 10px; border-radius:999px;
    background: rgba(255,255,255,.70);
    border: 1px solid rgba(15,23,42,.10);
    font-weight:900; font-size:12px; text-transform:uppercase;
    width: fit-content;
  }
  .dot{ width:8px; height:8px; border-radius:999px; background:#94a3b8; box-shadow:0 0 0 4px rgba(148,163,184,.14); }
  .tag[data-ch="seo"] .dot{ background: var(--indigo); box-shadow:0 0 0 4px rgba(99,102,241,.14); }
  .tag[data-ch="ads"] .dot{ background: var(--amber);  box-shadow:0 0 0 4px rgba(245,158,11,.14); }
  .tag[data-ch="email"] .dot{ background: var(--emerald); box-shadow:0 0 0 4px rgba(16,185,129,.14); }

  .mini{ font-size:12px; font-weight:650; color:var(--muted); }
  .split{ display:flex; gap:10px; flex-wrap:wrap; }
  .chip{
    font-size:12px; font-weight:900; color:rgba(15,23,42,.75);
    padding:6px 10px; border-radius:999px;
    background: rgba(255,255,255,.70);
    border: 1px solid rgba(15,23,42,.10);
  }
</style>
@endpush

@section('content')
<div class="container-fluid py-3">
  <div class="wrap">

    <div class="top">
      <div>
        <h3 class="title">Tiến độ Marketing</h3>
        <div class="sub">Trang tổng quan. Bấm vào từng kênh để cập nhật chi tiết.</div>
      </div>

      <div class="d-flex gap-2 align-items-center flex-wrap">
        <form method="GET" class="d-flex gap-2 align-items-center">
          <select name="plan_id" class="form-select" style="min-width:260px;border-radius:14px;font-weight:850;">
            @foreach($plans as $p)
              <option value="{{ $p->id }}" {{ (int)$planId === (int)$p->id ? 'selected' : '' }}>
                {{ \Carbon\Carbon::parse($p->month)->format('m/Y') }} — {{ $p->name }}
              </option>
            @endforeach
          </select>
          <button class="btn btnx">Xem</button>
        </form>

        @if(!empty($planId))
          <form method="POST" action="{{ route('marketing.progress.update') }}">
            @csrf
            <input type="hidden" name="plan_id" value="{{ $planId }}">
            <input type="hidden" name="action" value="generate">
            <button class="btn btnx btnx-primary">⚡ Tạo task từ kế hoạch</button>
          </form>
        @endif
      </div>
    </div>

    @if(session('success'))
      <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    {{-- KPI overall --}}
    <div class="cardx mb-3">
      <div class="kpis">
        <div class="kpi">
          <h6>Tiến độ tổng</h6>
          <div class="v">{{ $overall['progressPct'] ?? 0 }}%</div>
          <div class="bar"><div style="width: {{ $overall['progressPct'] ?? 0 }}%"></div></div>
          <div class="mini mt-2">Tổng: <b>{{ $overall['count'] ?? 0 }}</b> task • Tổng weight: <b>{{ $overall['sumW'] ?? 0 }}</b></div>
        </div>

        <div class="kpi">
          <h6>Todo</h6>
          <div class="v">{{ $overall['countByStatus']['todo'] ?? 0 }}</div>
          <div class="mini mt-2">Chưa làm</div>
        </div>

        <div class="kpi">
          <h6>Doing</h6>
          <div class="v">{{ $overall['countByStatus']['doing'] ?? 0 }}</div>
          <div class="mini mt-2">Đang làm</div>
        </div>

        <div class="kpi">
          <h6>Done</h6>
          <div class="v">{{ $overall['countByStatus']['done'] ?? 0 }}</div>
          <div class="mini mt-2">Hoàn thành</div>
        </div>
      </div>

      {{-- Channels --}}
      <div class="rowx">
        @foreach(['seo'=>'SEO','ads'=>'ADS','email'=>'EMAIL'] as $chKey => $chLabel)
          @php $s = $channelSummary[$chKey] ?? ['progressPct'=>0,'count'=>0,'countByStatus'=>['todo'=>0,'doing'=>0,'blocked'=>0,'done'=>0]]; @endphp
          <div class="chan">
            <span class="tag" data-ch="{{ $chKey }}"><span class="dot"></span>{{ $chLabel }}</span>

            <div>
              <div style="font-weight:950;font-size:20px;color:var(--txt);">{{ $s['progressPct'] ?? 0 }}%</div>
              <div class="bar"><div style="width: {{ $s['progressPct'] ?? 0 }}%"></div></div>
              <div class="mini mt-2">Tổng: <b>{{ $s['count'] ?? 0 }}</b> task</div>
            </div>

            <div class="split">
              <span class="chip">Todo: {{ $s['countByStatus']['todo'] ?? 0 }}</span>
              <span class="chip">Doing: {{ $s['countByStatus']['doing'] ?? 0 }}</span>
              <span class="chip">Blocked: {{ $s['countByStatus']['blocked'] ?? 0 }}</span>
              <span class="chip">Done: {{ $s['countByStatus']['done'] ?? 0 }}</span>
            </div>

            <a class="btn btnx mt-1"
               href="{{ route('marketing.progress.monthly', ['plan_id'=>$planId, 'channel'=>$chKey]) }}">
              Xem chi tiết & cập nhật →
            </a>
          </div>
        @endforeach
      </div>
    </div>

  </div>
</div>
@endsection