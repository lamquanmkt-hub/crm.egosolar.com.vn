@extends('layouts.app')

@section('content')
<style>
  .kpi-shell{ padding: 18px 18px 28px; }
  @media (max-width: 768px){ .kpi-shell{ padding: 12px; } }

  .kpi-hero{
    border-radius: 22px;
    border: 1px solid rgba(0,0,0,.06);
    background:
      radial-gradient(1200px circle at 12% 10%, rgba(0,123,255,.16), transparent 52%),
      radial-gradient(1000px circle at 88% 18%, rgba(40,167,69,.14), transparent 52%),
      linear-gradient(180deg, rgba(15,23,42,.03), rgba(15,23,42,0)),
      #fff;
    box-shadow: 0 18px 55px rgba(0,0,0,.08);
    overflow:hidden;
  }
  .kpi-hero::after{
    content:"";
    position:absolute; inset:0;
    background:
      radial-gradient(900px circle at 40% 120%, rgba(255,193,7,.08), transparent 50%);
    pointer-events:none;
  }
  .kpi-hero-inner{ position: relative; z-index: 1; padding: 26px; }
  @media (max-width: 768px){ .kpi-hero-inner{ padding: 18px; } }

  .pill{
    font-size: 12px;
    padding: 6px 10px;
    border-radius: 999px;
    border: 1px solid rgba(0,0,0,.07);
    background: rgba(255,255,255,.7);
    backdrop-filter: blur(6px);
  }
  .muted{ color: rgba(0,0,0,.60); }

  .kpi-grid{ display:grid; grid-template-columns: repeat(12, 1fr); gap: 14px; }
  @media (max-width: 992px){ .kpi-grid{ grid-template-columns: repeat(6, 1fr); } }
  @media (max-width: 576px){ .kpi-grid{ grid-template-columns: repeat(1, 1fr); } }

  .cardx{
    grid-column: span 6;
    border-radius: 18px;
    border: 1px solid rgba(0,0,0,.06);
    background: rgba(255,255,255,.92);
    box-shadow: 0 14px 40px rgba(0,0,0,.08);
    transition: transform .12s ease, box-shadow .12s ease, border-color .12s ease;
    overflow:hidden;
  }
  .cardx:hover{
    transform: translateY(-2px);
    box-shadow: 0 18px 55px rgba(0,0,0,.10);
    border-color: rgba(0,0,0,.10);
  }
  .cardx a{ color: inherit; text-decoration: none; display:block; padding: 18px; }
  .icon{
    width: 46px; height: 46px;
    border-radius: 16px;
    display:flex; align-items:center; justify-content:center;
    border: 1px solid rgba(0,0,0,.06);
    background: rgba(0,0,0,.03);
    font-size: 18px;
  }
  .cta{
    display:flex; align-items:center; justify-content:space-between;
    margin-top: 14px;
    padding-top: 14px;
    border-top: 1px dashed rgba(0,0,0,.10);
  }

  .topbar{
    display:flex; flex-wrap:wrap; gap:10px;
    align-items:center; justify-content:space-between;
    margin-top: 14px;
  }
  .monthbox{
    border-radius: 16px;
    border: 1px solid rgba(0,0,0,.08);
    background: rgba(255,255,255,.88);
    padding: 10px;
    display:flex; gap:10px; align-items:center;
  }
  .btn-round{ border-radius: 14px; }
  .form-42{ height: 42px; border-radius: 14px; border: 1px solid rgba(0,0,0,.10); }
</style>

@php
  $period = request('period') ?? date('Y-m');
  $isPrivileged = auth()->user()->hasAnyRole(['admin','marketing_manager']);
@endphp

<div class="container-fluid kpi-shell">
  <div class="kpi-hero position-relative">
    <div class="kpi-hero-inner">
      <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
        <span class="pill">Marketing</span>
        <span class="pill">KPI & Lương</span>
        <span class="pill">Kỳ: {{ $period }}</span>
        @if($isPrivileged)
          <span class="pill">Admin/Manager</span>
        @endif
      </div>

      <div class="d-flex flex-column flex-lg-row align-items-start align-items-lg-center justify-content-between gap-3">
        <div>
          <div style="font-size: 30px; font-weight: 900; letter-spacing:-.03em;">KPI & Lương</div>
          <div class="muted" style="max-width: 880px;">
            Trung tâm theo dõi KPI theo tháng. Manager nhập lương/target; nhân viên xem KPI & lương của mình. Admin/Manager có thể xem tổng hợp.
          </div>
        </div>

        <form method="GET" action="{{ route('marketing.kpi-payroll.index') }}" class="monthbox">
          <span class="muted" style="font-size: 13px;">Chọn kỳ</span>
          <input type="month" name="period" value="{{ $period }}" class="form-control form-42" style="min-width: 190px;">
          <button class="btn btn-primary btn-round" style="height:42px;">Xem</button>
        </form>
      </div>

      <div class="topbar">
        <div class="muted" style="font-size: 13px;">
          * KPI% & lương KPI thực nhận sẽ tính tự động theo nội dung đã đăng & được duyệt (bước tiếp theo).
        </div>

        @if($isPrivileged)
          <a class="btn btn-outline-primary btn-round" style="height:42px; display:inline-flex; align-items:center; gap:8px;"
             href="{{ route('marketing.kpi-payroll.my', ['period' => $period, 'user_id' => 'all']) }}">
            📊 Tổng hợp tất cả
          </a>
        @endif
      </div>
    </div>
  </div>

  <div class="kpi-grid mt-3">
    <div class="cardx">
      <a href="{{ route('marketing.kpi-payroll.my', ['period' => $period]) }}">
        <div class="d-flex align-items-start justify-content-between gap-3">
          <div>
            <div class="icon mb-2">👤</div>
            <div style="font-weight: 900; font-size: 18px;">Lương & KPI của tôi</div>
            <div class="muted">Xem lương cơ bản, quỹ KPI và target theo tháng.</div>
          </div>
          <span class="pill">My</span>
        </div>
        <div class="cta">
          <span class="muted" style="font-size: 13px;">Mở trang cá nhân</span>
          <span style="font-weight: 900;">Mở →</span>
        </div>
      </a>
    </div>

    @if($isPrivileged)
    <div class="cardx">
      <a href="{{ route('marketing.kpi-payroll.settings', ['period' => $period]) }}">
        <div class="d-flex align-items-start justify-content-between gap-3">
          <div>
            <div class="icon mb-2">🛠️</div>
            <div style="font-weight: 900; font-size: 18px;">Thiết lập KPI & Lương</div>
            <div class="muted">Nhập lương cơ bản, quỹ KPI và target cho từng nhân viên.</div>
          </div>
          <span class="pill">Manager</span>
        </div>
        <div class="cta">
          <span class="muted" style="font-size: 13px;">Mở bảng thiết lập</span>
          <span style="font-weight: 900;">Mở →</span>
        </div>
      </a>
    </div>
    @endif
  </div>
</div>
@endsection
