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

  .tag{
    display:inline-flex; align-items:center; gap:8px;
    padding:6px 10px; border-radius:999px;
    background: rgba(255,255,255,.70);
    border: 1px solid rgba(15,23,42,.10);
    font-weight:900; font-size:12px; text-transform:uppercase;
  }
  .dot{ width:8px; height:8px; border-radius:999px; background:#94a3b8; box-shadow:0 0 0 4px rgba(148,163,184,.14); }
  .tag[data-ch="seo"] .dot{ background: var(--indigo); box-shadow:0 0 0 4px rgba(99,102,241,.14); }
  .tag[data-ch="ads"] .dot{ background: var(--amber);  box-shadow:0 0 0 4px rgba(245,158,11,.14); }
  .tag[data-ch="email"] .dot{ background: var(--emerald); box-shadow:0 0 0 4px rgba(16,185,129,.14); }

  .bar{ margin-top:10px; height:10px; border-radius:999px; background: rgba(15,23,42,.08); overflow:hidden; }
  .bar > div{ height:100%; width:0%; border-radius:999px; background: linear-gradient(135deg, rgba(34,211,238,.95), rgba(99,102,241,.90)); }

  .filters{
    display:flex; gap:8px; flex-wrap:wrap; padding: 12px 14px;
    border-bottom: 1px solid rgba(15,23,42,.08);
    align-items:center;
  }
  .pill{
    border-radius:999px; padding:8px 12px; font-weight:900; font-size:12px;
    border:1px solid rgba(15,23,42,.10);
    background: rgba(255,255,255,.70);
    cursor:pointer; user-select:none;
    color: rgba(15,23,42,.75);
  }
  .pill.active{
    background: linear-gradient(135deg, rgba(34,211,238,.16), rgba(99,102,241,.10));
    border-color: rgba(34,211,238,.22);
    color: rgba(15,23,42,.90);
  }

  .table thead th{
    font-size:12px; text-transform:uppercase; letter-spacing:.25px;
    color: var(--muted); font-weight:950;
    background: rgba(255,255,255,.55);
    border-bottom: 1px solid rgba(15,23,42,.08) !important;
    padding: 12px 12px;
  }
  .table tbody td{
    padding: 12px 12px;
    border-top: 1px solid rgba(15,23,42,.06);
    vertical-align: middle;
  }
  .statusSel{
    border-radius: 12px;
    border: 1px solid rgba(15,23,42,.12);
    background: rgba(255,255,255,.86);
    font-weight: 850;
  }
  .mini{ font-size:12px; font-weight:650; color:var(--muted); }
</style>
@endpush

@section('content')
@php
  $label = strtoupper($channel);
@endphp

<div class="container-fluid py-3">
  <div class="wrap">

    <div class="top">
      <div>
        <h3 class="title">Chi tiết tiến độ: {{ $label }}</h3>
        <div class="sub">Đổi trạng thái task sẽ tự cập nhật tiến độ.</div>
      </div>

      <div class="d-flex gap-2 align-items-center flex-wrap">
        <a class="btn btnx" href="{{ route('marketing.progress.index', ['plan_id'=>$planId]) }}">← Về tổng quan</a>

        <form method="GET" action="{{ route('marketing.progress.monthly') }}" class="d-flex gap-2 align-items-center">
          <input type="hidden" name="channel" value="{{ $channel }}">
          <select name="plan_id" class="form-select statusSel" style="min-width:260px;">
            @foreach($plans as $p)
              <option value="{{ $p->id }}" {{ (int)$planId === (int)$p->id ? 'selected' : '' }}>
                {{ \Carbon\Carbon::parse($p->month)->format('m/Y') }} — {{ $p->name }}
              </option>
            @endforeach
          </select>
          <button class="btn btnx">Xem</button>
        </form>
      </div>
    </div>

    @if(session('success'))
      <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    <div class="cardx">
      <div class="filters">
        <span class="tag" data-ch="{{ $channel }}"><span class="dot"></span>{{ $label }}</span>
        <span class="mini ms-2">Plan: <b>{{ $monthText }}</b> • Tổng: <b id="rowCount">{{ count($tasks ?? []) }}</b> task • Tiến độ: <b>{{ $summary['progressPct'] ?? 0 }}%</b></span>
        <span class="ms-auto mini">Lọc trạng thái:</span>

        <span class="pill active" data-st="todo,doing">Đang làm</span>
        <span class="pill" data-st="todo">Todo</span>
        <span class="pill" data-st="doing">Doing</span>
        <span class="pill" data-st="blocked">Blocked</span>
        <span class="pill" data-st="done">Done</span>
      </div>

      <div class="p-3">
        <div class="bar"><div style="width: {{ $summary['progressPct'] ?? 0 }}%"></div></div>
        <div class="mini mt-2">doing=50%, done=100% (theo weight)</div>
      </div>

      <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
          <thead>
            <tr>
              <th>Task</th>
              <th style="width:120px;">Due</th>
              <th style="width:220px;">Trạng thái</th>
              <th class="text-end" style="width:90px;">Weight</th>
            </tr>
          </thead>
          <tbody id="tbodyTasks">
          @forelse($tasks as $t)
            @php $st = (string)($t->status ?? 'todo'); @endphp
            <tr data-st="{{ $st }}">
              <td class="fw-semibold">{{ $t->title }}</td>
              <td>{{ $t->due_date ? \Carbon\Carbon::parse($t->due_date)->format('d/m') : '—' }}</td>
              <td>
                <form method="POST" action="{{ route('marketing.progress.tasks.status', $t->id) }}" class="js-status-form d-flex gap-2 align-items-center">
                  @csrf
                  <select name="status" class="form-select form-select-sm statusSel js-status">
                    @foreach(['todo','doing','blocked','done'] as $x)
                      <option value="{{ $x }}" {{ $st===$x?'selected':'' }}>{{ strtoupper($x) }}</option>
                    @endforeach
                  </select>
                  <span class="mini">Tự lưu</span>
                </form>
              </td>
              <td class="text-end fw-bold">{{ (int)($t->weight ?? 1) }}</td>
            </tr>
          @empty
            <tr><td colspan="4" class="text-center text-muted py-4">Chưa có task. Về tổng quan bấm “Tạo task từ kế hoạch”.</td></tr>
          @endforelse
          </tbody>
        </table>
      </div>

      <div class="p-3 mini">
        Gợi ý: Todo = chưa làm • Doing = đang làm • Done = xong. Chỉ cần đổi trạng thái là tiến độ tự chạy.
      </div>
    </div>

  </div>
</div>
@endsection

@push('scripts')
<script>
  // auto submit on status change
  document.querySelectorAll('.js-status').forEach(sel=>{
    sel.addEventListener('change', function(){
      this.closest('form').submit();
    });
  });

  // filter status pills
  const pills = document.querySelectorAll('.pill');
  const rows = document.querySelectorAll('#tbodyTasks tr[data-st]');
  let current = ['todo','doing'];

  function apply(){
    let shown = 0;
    rows.forEach(r=>{
      const st = r.getAttribute('data-st');
      const ok = current.includes(st);
      r.style.display = ok ? '' : 'none';
      if (ok) shown++;
    });
    const rc = document.getElementById('rowCount');
    if (rc) rc.innerText = shown;
  }

  pills.forEach(p=>{
    p.addEventListener('click', ()=>{
      pills.forEach(x=>x.classList.remove('active'));
      p.classList.add('active');
      current = p.getAttribute('data-st').split(',').map(x=>x.trim());
      apply();
    });
  });

  apply();
</script>
@endpush