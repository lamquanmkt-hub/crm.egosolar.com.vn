@extends('layouts.app')

@section('content')
<style>
  :root{
    --bd: rgba(0,0,0,.08);
    --bd2: rgba(0,0,0,.06);
    --txt: rgba(0,0,0,.88);
    --muted: rgba(0,0,0,.58);
    --shadow: 0 18px 55px rgba(0,0,0,.10);
    --shadow2: 0 12px 30px rgba(0,0,0,.08);
    --r14: 14px;
    --r18: 18px;
    --r22: 22px;
  }

  .k-shell{ padding: 16px 16px 96px; }
  @media (max-width: 768px){ .k-shell{ padding: 12px 12px 120px; } }

  .k-hero{
    width: 100%;
    border-radius: var(--r22);
    border: 1px solid var(--bd2);
    background:
      radial-gradient(1200px circle at 12% 0%, rgba(0,123,255,.10), transparent 55%),
      radial-gradient(900px circle at 88% 0%, rgba(40,167,69,.09), transparent 55%),
      #fff;
    box-shadow: var(--shadow);
    overflow:hidden;
  }

  .k-hero-top{
    padding: 18px 18px 12px;
    border-bottom: 1px solid var(--bd2);
    display:flex; flex-wrap:wrap; gap:10px;
    align-items:flex-start; justify-content:space-between;
  }

  .k-title{
    font-size: 30px;
    font-weight: 950;
    letter-spacing: -.03em;
    color: var(--txt);
    margin: 0;
    line-height: 1.15;
  }

  .k-sub{ color: var(--muted); margin-top: 6px; max-width: 1150px; }

  .pill{
    font-size: 12px;
    padding: 6px 10px;
    border-radius: 999px;
    border: 1px solid rgba(0,0,0,.07);
    background: rgba(255,255,255,.78);
    backdrop-filter: blur(8px);
  }

  .k-controls{
    display:flex; flex-wrap:wrap; gap:10px;
    align-items:center; justify-content:flex-end;
  }
  .form-42{
    height: 42px;
    border-radius: var(--r14);
    border: 1px solid var(--bd);
  }

  .btn-round{
    border-radius: var(--r14);
    height:42px;
    display:inline-flex;
    align-items:center;
    gap:8px;
    font-weight: 850;
    white-space: nowrap;
  }

  .k-body{ padding: 14px 18px 18px; }
  @media (max-width: 768px){ .k-body{ padding: 12px 12px 12px; } }

  .alertx{
    border-radius: 16px;
    border: 1px solid var(--bd2);
    box-shadow: 0 10px 22px rgba(0,0,0,.06);
  }

  /* Stats */
  .stats{
    display:flex; flex-wrap:wrap; gap:10px;
    align-items:center;
    margin-bottom: 12px;
  }
  .stat{
    border-radius: 16px;
    border: 1px solid var(--bd2);
    background: rgba(255,255,255,.92);
    padding: 10px 12px;
    box-shadow: 0 10px 22px rgba(0,0,0,.06);
    min-width: 210px;
  }
  .stat .slabel{ font-size: 12px; color: var(--muted); font-weight: 850; }
  .stat .sval{ font-size: 18px; font-weight: 950; letter-spacing: -.02em; color: var(--txt); }

  /* Cards */
  .cardx{
    border-radius: 18px;
    border: 1px solid var(--bd2);
    background: rgba(255,255,255,.94);
    box-shadow: var(--shadow2);
    overflow:hidden;
  }
  .cardx .cardx-hd{
    padding: 12px 14px;
    border-bottom: 1px solid rgba(0,0,0,.06);
    display:flex; flex-wrap:wrap; gap:10px;
    align-items:center; justify-content:space-between;
    background: rgba(255,255,255,.86);
    backdrop-filter: blur(10px);
  }
  .cardx .cardx-tt{
    font-weight: 950;
    letter-spacing: -.02em;
    color: var(--txt);
    margin:0;
    font-size: 16px;
  }
  .cardx .cardx-sub{
    color: var(--muted);
    font-size: 13px;
    margin-top: 2px;
    font-weight: 700;
  }
  .cardx .cardx-bd{ padding: 14px; }

  .badge-soft{
    border-radius: 999px;
    padding: 6px 10px;
    font-weight: 900;
    border: 1px solid rgba(0,0,0,.08);
    background: rgba(255,255,255,.85);
  }

  /* Inputs */
  .inp{
    height: 42px;
    border-radius: var(--r14);
    border: 1px solid rgba(0,0,0,.10);
    background: rgba(255,255,255,.98);
    transition: box-shadow .12s ease, border-color .12s ease;
  }
  .inp:focus{
    border-color: rgba(0,123,255,.45);
    box-shadow: 0 0 0 4px rgba(0,123,255,.12);
    outline: none;
  }
  .changed{
    border-color: rgba(255,193,7,.65) !important;
    box-shadow: 0 0 0 4px rgba(255,193,7,.15) !important;
  }

  .ghost{
    color: var(--muted);
    font-size: 13px;
    font-weight: 780;
  }

  /* Tools row */
  .toolrow{
    display:flex;
    flex-wrap:wrap;
    gap:10px;
    align-items:flex-start;
    justify-content:space-between;
    margin-bottom: 12px;
  }
  .toolbox{
    border: 1px solid var(--bd2);
    border-radius: 16px;
    background: rgba(255,255,255,.94);
    box-shadow: 0 12px 30px rgba(0,0,0,.06);
    padding: 10px 12px;
    display:flex; flex-wrap:wrap; gap:10px;
    align-items:center;
  }
  .divider{ width:1px; height:28px; background: rgba(0,0,0,.08); }

  .mini-help{
    border-radius: 14px;
    border: 1px dashed rgba(0,0,0,.10);
    background: rgba(255,255,255,.78);
    padding: 10px 12px;
    color: var(--muted);
    font-size: 13px;
    font-weight: 700;
  }

  .grid-2{
    display:grid;
    grid-template-columns: 1fr 1fr;
    gap: 12px;
  }
  @media (max-width: 1200px){ .grid-2{ grid-template-columns: 1fr; } }

  .grid-3{
    display:grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 12px;
  }
  @media (max-width: 1200px){ .grid-3{ grid-template-columns: 1fr; } }

  /* Table */
  .table-wrap{
    border-radius: 18px;
    border: 1px solid var(--bd2);
    overflow:hidden;
    background:#fff;
    box-shadow: 0 14px 40px rgba(0,0,0,.08);
  }
  .table-modern{ width: 100%; margin:0; }
  .table-modern thead th{
    position: sticky;
    top: 0;
    z-index: 3;
    background: rgba(255,255,255,.96);
    backdrop-filter: blur(8px);
    border-bottom: 1px solid rgba(0,0,0,.10);
    white-space: nowrap;
  }
  .table-modern tbody tr:hover{ background: rgba(0,0,0,.02); }

  .sticky-col{
    position: sticky;
    left: 0;
    z-index: 2;
    background: rgba(255,255,255,.98);
    backdrop-filter: blur(8px);
    border-right: 1px solid rgba(0,0,0,.06);
  }
  .table-modern thead .sticky-col{ z-index: 4; }
  .name{ font-weight: 950; color: var(--txt); white-space: nowrap; }
  .id{ color: var(--muted); font-size: 12px; font-weight: 750; }
  .row-changed{ background: rgba(255,193,7,.06) !important; }

  /* Save bar */
  .savebar{
    position: fixed;
    left: 0; right: 0;
    bottom: 12px;
    z-index: 999;
    padding: 0 16px;
    pointer-events:none;
  }
  .savebar-inner{
    pointer-events:auto;
    width: 100%;
    border-radius: 18px;
    border: 1px solid rgba(0,0,0,.10);
    background: rgba(255,255,255,.92);
    backdrop-filter: blur(10px);
    box-shadow: 0 18px 60px rgba(0,0,0,.18);
    padding: 10px 12px;
    display:flex; flex-wrap:wrap; gap:10px;
    align-items:center; justify-content:space-between;
  }
  .unsaved{ display:flex; gap:10px; align-items:center; color: var(--txt); }
  .dot{
    width:10px; height:10px; border-radius: 999px;
    background: rgba(40,167,69,.75);
    box-shadow: 0 0 0 6px rgba(40,167,69,.12);
  }
  .dot.warn{
    background: rgba(255,193,7,.95);
    box-shadow: 0 0 0 6px rgba(255,193,7,.12);
  }

  /* Rule tables */
  .rule-table th, .rule-table td{ vertical-align: middle; }
  .rule-table thead th{ background: rgba(0,0,0,.02); }
  .btn-icon{
    width: 42px; height: 42px;
    border-radius: var(--r14);
    display:inline-flex; align-items:center; justify-content:center;
    font-weight: 950;
  }
</style>

@php
  $period = $period ?? (request('period') ?? date('Y-m'));
  $bonusConfig = $bonusConfig ?? [];
  $rule = $rule ?? null;

  // KPI weights defaults
  $wReview = old('rule.weight_review', $rule->weight_review ?? 40);
  $bReview = old('rule.bonus_over_review', $rule->bonus_over_review ?? 3);

  $wAi = old('rule.weight_ai', $rule->weight_ai ?? 30);
  $bAi = old('rule.bonus_over_ai', $rule->bonus_over_ai ?? 1);

  $wPost = old('rule.weight_post', $rule->weight_post ?? 30);
  $bPost = old('rule.bonus_over_post', $rule->bonus_over_post ?? 0.5);

  // Trend tiers (NEW) fallback from legacy m1/m2
  $trendTiers = old('bonus_config.trend_video.tiers', data_get($bonusConfig,'trend_video.tiers'));
  if (!$trendTiers || !is_array($trendTiers) || count($trendTiers) === 0) {
    $legacyM1 = data_get($bonusConfig,'trend_video.m1');
    $legacyM2 = data_get($bonusConfig,'trend_video.m2');

    if ($legacyM1 || $legacyM2) {
      $trendTiers = [];
      if ($legacyM1) $trendTiers[] = [
        'min_view' => data_get($legacyM1,'min_view',30000),
        'min_engagement' => data_get($legacyM1,'min_engagement',1000),
        'reward' => data_get($legacyM1,'reward',1000000),
      ];
      if ($legacyM2) $trendTiers[] = [
        'min_view' => data_get($legacyM2,'min_view',100000),
        'min_engagement' => data_get($legacyM2,'min_engagement',5000),
        'reward' => data_get($legacyM2,'reward',5000000),
      ];
    } else {
      $trendTiers = [
        ['min_view'=>30000,'min_engagement'=>1000,'reward'=>1000000],
        ['min_view'=>100000,'min_engagement'=>5000,'reward'=>5000000],
      ];
    }
  }
@endphp

<div class="container-fluid k-shell">

  <div class="k-hero">
    <div class="k-hero-top">
      <div>
        <div class="d-flex flex-wrap gap-2 mb-2">
          <span class="pill">KPI & Lương</span>
          <span class="pill">Thiết lập</span>
          <span class="pill">Kỳ: {{ $period }}</span>
          <span class="pill">Ctrl + S lưu nhanh</span>
        </div>
        <h2 class="k-title">Thiết lập KPI & Lương</h2>
        <div class="k-sub">
          Thiết lập lương cơ bản, quỹ KPI, target theo nhân viên + cấu hình thưởng nóng/penalty theo kỳ.
        </div>
      </div>

      <form method="GET" action="{{ route('marketing.kpi-payroll.settings') }}" class="k-controls">
        <label class="ghost mb-0">Kỳ</label>
        <input type="month" name="period" value="{{ $period }}" class="form-control form-42" style="width: 190px;">
        <button class="btn btn-primary btn-round" type="submit">👀 Xem</button>
        <a class="btn btn-outline-secondary btn-round" href="{{ route('marketing.kpi-payroll.index', ['period' => $period]) }}">↩️ Quay lại</a>
      </form>
    </div>

    <div class="k-body">

      @if(session('success'))
        <div class="alert alert-success alertx mb-3">{{ session('success') }}</div>
      @endif

      @if($errors->any())
        <div class="alert alert-danger alertx mb-3">
          <div style="font-weight:900;">Có lỗi:</div>
          <ul class="mb-0">
            @foreach($errors->all() as $e)
              <li>{{ $e }}</li>
            @endforeach
          </ul>
        </div>
      @endif

      {{-- STATS --}}
      <div class="stats">
        <div class="stat">
          <div class="slabel">Tổng lương cơ bản (realtime)</div>
          <div class="sval" id="sumBase">0 đ</div>
        </div>
        <div class="stat">
          <div class="slabel">Tổng quỹ KPI (realtime)</div>
          <div class="sval" id="sumPool">0 đ</div>
        </div>
        <div class="stat">
          <div class="slabel">Số nhân viên (đang hiển thị)</div>
          <div class="sval" id="visibleCount">0</div>
        </div>
        <div class="stat">
          <div class="slabel">Tổng tỷ trọng KPI%</div>
          <div class="sval"><span id="weightTotalBadge" class="badge bg-success" style="border-radius:999px; padding:6px 10px; font-weight:950;">100%</span></div>
        </div>
      </div>

      <form method="POST" action="{{ route('marketing.kpi-payroll.settings.save') }}" id="settingsForm">
        @csrf
        <input type="hidden" name="period" value="{{ $period }}"/>

        {{-- ===========================
            IV. THƯỞNG NÓNG VIDEO XU HƯỚNG (DYNAMIC TIERS)
            =========================== --}}
        <div class="cardx mb-3" id="trendCard">
          <div class="cardx-hd">
            <div>
              <div class="cardx-tt">IV. Thưởng nóng video lên xu hướng (TikTok / Facebook Reel)</div>
              <div class="cardx-sub">Đạt <b>đủ cả View</b> và <b>Tương tác</b> (like + comment + share). Có thể tạo nhiều mốc (1,2,3,4...).</div>
            </div>
            <div class="d-flex gap-2 align-items-center">
              <span class="badge-soft">/ video</span>
              <button type="button" class="btn btn-outline-primary btn-round" id="addTrendTier">➕ Thêm mốc</button>
            </div>
          </div>

          <div class="cardx-bd">
            <div class="mini-help mb-3">
              Quy tắc: nếu video đạt nhiều mốc thì hệ thống xét <b>mốc cao nhất</b> đạt được trong tháng (theo từng video).
            </div>

            <div class="table-responsive">
              <table class="table table-sm rule-table mb-0" id="trendTierTable">
                <thead>
                  <tr>
                    <th style="width:110px;">Mốc</th>
                    <th style="min-width:180px;">Min view</th>
                    <th style="min-width:200px;">Min tương tác</th>
                    <th style="min-width:220px;">Thưởng (VND) / video</th>
                    <th style="width:80px;" class="text-end">Xoá</th>
                  </tr>
                </thead>
                <tbody id="trendTierTbody">
                  @foreach($trendTiers as $i => $t)
                    <tr class="trend-tier-row" data-idx="{{ $i }}">
                      <td class="fw-bold">Mốc <span class="trend-tier-no">{{ $i+1 }}</span></td>
                      <td>
                        <input type="number" min="0" class="form-control inp"
                          name="bonus_config[trend_video][tiers][{{ $i }}][min_view]"
                          value="{{ data_get($t,'min_view',0) }}">
                      </td>
                      <td>
                        <input type="number" min="0" class="form-control inp"
                          name="bonus_config[trend_video][tiers][{{ $i }}][min_engagement]"
                          value="{{ data_get($t,'min_engagement',0) }}">
                      </td>
                      <td>
                        <input inputmode="numeric" class="form-control inp money"
                          name="bonus_config[trend_video][tiers][{{ $i }}][reward]"
                          value="{{ data_get($t,'reward',0) }}">
                      </td>
                      <td class="text-end">
                        <button type="button" class="btn btn-outline-danger btn-icon removeTrendTier" title="Xoá mốc">✕</button>
                      </td>
                    </tr>
                  @endforeach
                </tbody>
              </table>
            </div>

            <template id="trendTierTpl">
              <tr class="trend-tier-row" data-idx="__IDX__">
                <td class="fw-bold">Mốc <span class="trend-tier-no">__NO__</span></td>
                <td>
                  <input type="number" min="0" class="form-control inp"
                    name="bonus_config[trend_video][tiers][__IDX__][min_view]"
                    value="0">
                </td>
                <td>
                  <input type="number" min="0" class="form-control inp"
                    name="bonus_config[trend_video][tiers][__IDX__][min_engagement]"
                    value="0">
                </td>
                <td>
                  <input inputmode="numeric" class="form-control inp money"
                    name="bonus_config[trend_video][tiers][__IDX__][reward]"
                    value="0">
                </td>
                <td class="text-end">
                  <button type="button" class="btn btn-outline-danger btn-icon removeTrendTier" title="Xoá mốc">✕</button>
                </td>
              </tr>
            </template>

          </div>
        </div>

        {{-- ===========================
            VII. LIVESTREAM
            =========================== --}}
@php
  $ls = (array) data_get($bonusConfig, 'livestream', []);
  $lsMin = (int) data_get($ls, 'min_duration_min', 30);
  $lsLead = (int) data_get($ls, 'lead_reward', 8000);

  $lsTiers = data_get($ls, 'tiers', []);
  if (!is_array($lsTiers) || count($lsTiers) === 0) {
    $lsTiers = [
      ['min_view' => 50000, 'min_engagement' => 300, 'reward' => 1000000],
      ['min_view' => 200000, 'min_engagement' => 1000, 'reward' => 3000000],
    ];
  }
@endphp

<div class="card border-0 shadow-sm mb-3" style="border-radius:16px; overflow:hidden;">
  <div class="card-body">
    <div class="d-flex align-items-start justify-content-between flex-wrap gap-2">
      <div>
        <div class="fw-bold" style="font-size:18px;">VII. Thưởng theo Livestream (Facebook / TikTok)</div>
        <div class="text-muted">
          Chỉ tính <b>1 mốc cao nhất</b> mỗi buổi live + thưởng lead (nếu áp dụng). Có thể thêm nhiều mốc.
        </div>
      </div>

      <div class="d-flex align-items-center gap-2">
        <span class="badge bg-light text-dark" style="font-size:13px;">/ buổi</span>
        <button type="button" class="btn btn-outline-primary btn-sm" id="lsAddTier">
          + Thêm mốc
        </button>
      </div>
    </div>

    <div class="row g-3 mt-2">
      <div class="col-12 col-lg-6">
        <label class="form-label small text-muted mb-1">Thời lượng tối thiểu (phút)</label>
        <input type="number" min="0" class="form-control"
               name="bonus_config[livestream][min_duration_min]"
               value="{{ old('bonus_config.livestream.min_duration_min', $lsMin) }}">
      </div>

      <div class="col-12 col-lg-6">
        <label class="form-label small text-muted mb-1">Thưởng / lead hợp lệ (VND)</label>
        <input type="number" min="0" class="form-control moneyish"
               name="bonus_config[livestream][lead_reward]"
               value="{{ old('bonus_config.livestream.lead_reward', $lsLead) }}">
      </div>
    </div>

    <div class="mt-3 p-2 border rounded-4">
      <div class="table-responsive">
        <table class="table align-middle mb-0">
          <thead>
            <tr class="text-muted small">
              <th style="width:110px;">Mốc</th>
              <th>Min view</th>
              <th>Min tương tác</th>
              <th>Thưởng (VND)</th>
              <th style="width:70px;"></th>
            </tr>
          </thead>
          <tbody id="lsTierBody">
            @foreach($lsTiers as $i => $t)
              <tr data-ls-tier-row>
                <td class="fw-semibold text-muted">Mốc <span class="ls-idx">{{ $i+1 }}</span></td>
                <td>
                  <input type="number" min="0" class="form-control"
                         name="bonus_config[livestream][tiers][{{ $i }}][min_view]"
                         value="{{ (int)($t['min_view'] ?? 0) }}">
                </td>
                <td>
                  <input type="number" min="0" class="form-control"
                         name="bonus_config[livestream][tiers][{{ $i }}][min_engagement]"
                         value="{{ (int)($t['min_engagement'] ?? 0) }}">
                </td>
                <td>
                  <input type="number" min="0" class="form-control moneyish"
                         name="bonus_config[livestream][tiers][{{ $i }}][reward]"
                         value="{{ (int)($t['reward'] ?? 0) }}">
                </td>
                <td class="text-end">
                  <button type="button" class="btn btn-outline-danger btn-sm" data-ls-remove>✕</button>
                </td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>

      <div class="small text-muted mt-2" id="lsPreview"></div>
    </div>

    <div class="alert alert-light mt-3 mb-0" style="border-radius:14px;">
      <div class="small">
        Điều kiện hợp lệ: ≥ <b>{{ $lsMin }}</b> phút + đúng định hướng + CTA + có link + nhập số liệu trong 24h.
        Tương tác = like + comment + share.
      </div>
    </div>
  </div>
</div>

<template id="lsTierTpl">
  <tr data-ls-tier-row>
    <td class="fw-semibold text-muted">Mốc <span class="ls-idx">X</span></td>
    <td><input type="number" min="0" class="form-control" name="__NAME__[min_view]" value="0"></td>
    <td><input type="number" min="0" class="form-control" name="__NAME__[min_engagement]" value="0"></td>
    <td><input type="number" min="0" class="form-control moneyish" name="__NAME__[reward]" value="0"></td>
    <td class="text-end"><button type="button" class="btn btn-outline-danger btn-sm" data-ls-remove>✕</button></td>
  </tr>
</template>

        {{-- ===========================
            VI. ANTI FRAUD
            =========================== --}}
        <div class="cardx mb-3">
          <div class="cardx-hd">
            <div>
              <div class="cardx-tt">VI. Quy định chống gian lận (bắt buộc)</div>
              <div class="cardx-sub">Nếu phát hiện gian lận: trừ % lương tháng vi phạm.</div>
            </div>
            <span class="badge-soft">Penalty</span>
          </div>
          <div class="cardx-bd">
            <div class="row g-3">
              <div class="col-12 col-lg-6">
                <label class="ghost mb-1">Mức trừ (%)</label>
                <input type="number" min="0" max="100" class="form-control inp" id="penaltyPct"
                  name="bonus_config[anti_fraud][penalty_percent]"
                  value="{{ old('bonus_config.anti_fraud.penalty_percent', data_get($bonusConfig,'anti_fraud.penalty_percent',70)) }}">
              </div>
              <div class="col-12 col-lg-6">
                <label class="ghost mb-1">Giữ lại (%)</label>
                <input type="number" min="0" max="100" class="form-control inp" id="keepPct"
                  name="bonus_config[anti_fraud][keep_percent]"
                  value="{{ old('bonus_config.anti_fraud.keep_percent', data_get($bonusConfig,'anti_fraud.keep_percent',30)) }}">
              </div>
              <div class="col-12">
                <div class="mini-help" id="fraudPreview">
                  Lương thực nhận sau phạt = (Lương KPI + Thưởng nóng + Thưởng TikTok) × 30%
                </div>
              </div>
              <div class="col-12">
                <div class="mini-help">
                  Nghiêm cấm buff tương tác ảo (mua view/like/comment/share, tool/traffic không hợp lệ...). Nếu vi phạm, áp dụng penalty theo % ở trên.
                </div>
              </div>
            </div>
          </div>
        </div>

        {{-- ===========================
            KPI FORMULA
            =========================== --}}
        <div class="cardx mb-3">
          <div class="cardx-hd">
            <div>
              <div class="cardx-tt">Công thức KPI% (theo kỳ)</div>
              <div class="cardx-sub">Tỷ trọng (%) + % thưởng vượt (mỗi nội dung vượt target). KPI% có thể > 100%.</div>
            </div>
            <span class="badge-soft">KPI%</span>
          </div>
          <div class="cardx-bd">
            <div class="grid-3">
              <div class="cardx" style="box-shadow:none;">
                <div class="cardx-hd">
                  <div class="cardx-tt">Video Review</div>
                  <span class="badge-soft">KPI</span>
                </div>
                <div class="cardx-bd">
                  <div class="row g-2">
                    <div class="col-6">
                      <label class="ghost mb-1">Tỷ trọng (%)</label>
                      <input type="number" step="0.01" class="form-control inp kpi-weight"
                        name="rule[weight_review]" value="{{ $wReview }}">
                    </div>
                    <div class="col-6">
                      <label class="ghost mb-1">Vượt target (+%/video)</label>
                      <input type="number" step="0.01" class="form-control inp"
                        name="rule[bonus_over_review]" value="{{ $bReview }}">
                    </div>
                  </div>
                </div>
              </div>

              <div class="cardx" style="box-shadow:none;">
                <div class="cardx-hd">
                  <div class="cardx-tt">Video AI</div>
                  <span class="badge-soft">KPI</span>
                </div>
                <div class="cardx-bd">
                  <div class="row g-2">
                    <div class="col-6">
                      <label class="ghost mb-1">Tỷ trọng (%)</label>
                      <input type="number" step="0.01" class="form-control inp kpi-weight"
                        name="rule[weight_ai]" value="{{ $wAi }}">
                    </div>
                    <div class="col-6">
                      <label class="ghost mb-1">Vượt target (+%/video)</label>
                      <input type="number" step="0.01" class="form-control inp"
                        name="rule[bonus_over_ai]" value="{{ $bAi }}">
                    </div>
                  </div>
                </div>
              </div>

              <div class="cardx" style="box-shadow:none;">
                <div class="cardx-hd">
                  <div class="cardx-tt">Bài viết</div>
                  <span class="badge-soft">KPI</span>
                </div>
                <div class="cardx-bd">
                  <div class="row g-2">
                    <div class="col-6">
                      <label class="ghost mb-1">Tỷ trọng (%)</label>
                      <input type="number" step="0.01" class="form-control inp kpi-weight"
                        name="rule[weight_post]" value="{{ $wPost }}">
                    </div>
                    <div class="col-6">
                      <label class="ghost mb-1">Vượt target (+%/bài)</label>
                      <input type="number" step="0.01" class="form-control inp"
                        name="rule[bonus_over_post]" value="{{ $bPost }}">
                    </div>
                  </div>
                </div>
              </div>
            </div>

            <div class="alert alert-info mt-3 mb-0" style="border-radius:14px;">
              <div class="fw-bold">Gợi ý theo quy định hiện tại</div>
              <div class="small">
                Review 40% (+3%/video vượt), AI 30% (+1%/video vượt), Bài viết 30% (+0.5%/bài vượt).
              </div>
            </div>
          </div>
        </div>

        {{-- ===========================
            TOOLS
            =========================== --}}
        <div class="toolrow">
          <div class="toolbox">
            <div class="ghost">Tìm</div>
            <input id="rowSearch" class="form-control form-42" style="width: 260px;" placeholder="Tìm nhân viên / ID...">

            <div class="divider"></div>

            <div class="form-check form-switch m-0">
              <input class="form-check-input" type="checkbox" id="onlyChanged">
              <label class="form-check-label ghost" for="onlyChanged">Chỉ dòng đã sửa</label>
            </div>

            <div class="divider"></div>

            <div class="ghost">Bulk</div>
            <select id="bulkField" class="form-control form-42" style="width: 170px;">
              <option value="base_salary">Lương cơ bản</option>
              <option value="kpi_salary_pool">Quỹ KPI</option>
              <option value="target_post">Target bài</option>
              <option value="target_video_ai">Target AI</option>
              <option value="target_video_review">Target Review</option>
            </select>
            <input id="bulkValue" class="form-control form-42" style="width: 160px;" placeholder="Giá trị">
            <button type="button" id="applyBulk" class="btn btn-outline-primary btn-round" style="height:42px;">⚡ Áp dụng</button>

            <div class="divider"></div>

            <button type="button" id="restoreDraft" class="btn btn-outline-success btn-round" style="height:42px;">🧠 Khôi phục nháp</button>
            <button type="button" id="clearDraft" class="btn btn-outline-danger btn-round" style="height:42px;">🗑️ Xoá nháp</button>
          </div>

          <div class="mini-help" style="max-width:520px;">
            Tip: Nhập tiền không cần dấu phẩy. Hệ thống tự format khi rời ô. Nháp lưu local trên máy bạn theo từng kỳ.
          </div>
        </div>

        {{-- ===========================
            EMPLOYEE SETTINGS TABLE
            =========================== --}}
        <div class="table-responsive table-wrap">
          <table class="table table-modern table-hover align-middle">
            <thead>
              <tr>
                <th class="sticky-col" style="min-width: 280px;">Nhân viên</th>
                <th style="min-width: 200px;">Lương cơ bản</th>
                <th style="min-width: 200px;">Quỹ lương KPI</th>
                <th style="min-width: 150px;">Target bài</th>
                <th style="min-width: 150px;">Target AI</th>
                <th style="min-width: 170px;">Target Review</th>
              </tr>
            </thead>
            <tbody id="tb">
              @foreach(($users ?? []) as $u)
                @php
                  $s = isset($settings) ? ($settings[$u->id] ?? null) : null;
                  $displayName = $u->name ?? $u->username ?? $u->email ?? ('User#'.$u->id);
                @endphp
                <tr class="row-item" data-user="{{ $u->id }}">
                  <td class="sticky-col">
                    <div class="name">{{ $displayName }}</div>
                    <div class="id">ID: {{ $u->id }}</div>
                  </td>

                  <td>
                    <input class="form-control inp money" inputmode="numeric" data-field="base_salary"
                      name="rows[{{ $u->id }}][base_salary]" value="{{ $s->base_salary ?? 0 }}">
                  </td>

                  <td>
                    <input class="form-control inp money" inputmode="numeric" data-field="kpi_salary_pool"
                      name="rows[{{ $u->id }}][kpi_salary_pool]" value="{{ $s->kpi_salary_pool ?? 0 }}">
                  </td>

                  <td>
                    <input class="form-control inp num" type="number" min="0" data-field="target_post"
                      name="rows[{{ $u->id }}][target_post]" value="{{ $s->target_post ?? 0 }}">
                  </td>

                  <td>
                    <input class="form-control inp num" type="number" min="0" data-field="target_video_ai"
                      name="rows[{{ $u->id }}][target_video_ai]" value="{{ $s->target_video_ai ?? 0 }}">
                  </td>

                  <td>
                    <input class="form-control inp num" type="number" min="0" data-field="target_video_review"
                      name="rows[{{ $u->id }}][target_video_review]" value="{{ $s->target_video_review ?? 0 }}">
                  </td>
                </tr>
              @endforeach
            </tbody>
          </table>
        </div>

      </form>

    </div>
  </div>

  {{-- Save Bar --}}
  <div class="savebar">
    <div class="savebar-inner">
      <div class="unsaved">
        <span id="dot" class="dot"></span>
        <div>
          <div style="font-weight: 950; letter-spacing:-.02em;">Trạng thái</div>
          <div class="ghost" id="statusText">Đã đồng bộ (không có thay đổi)</div>
        </div>
      </div>

      <div class="d-flex flex-wrap gap-2">
        <button type="button" id="saveBtn" class="btn btn-success btn-round" style="min-width: 170px;">
          💾 Lưu thiết lập
        </button>
        <a class="btn btn-outline-secondary btn-round" href="{{ route('marketing.kpi-payroll.index', ['period' => $period]) }}">
          ↩️ Quay lại
        </a>
      </div>
    </div>
  </div>

</div>

<script>
(function(){
  const period = @json($period);
  const form = document.getElementById('settingsForm');
  const tbody = document.getElementById('tb');
  const rows = () => Array.from(tbody.querySelectorAll('tr.row-item'));

  const rowSearch = document.getElementById('rowSearch');
  const onlyChanged = document.getElementById('onlyChanged');

  const sumBaseEl = document.getElementById('sumBase');
  const sumPoolEl = document.getElementById('sumPool');
  const visibleCountEl = document.getElementById('visibleCount');

  const bulkField = document.getElementById('bulkField');
  const bulkValue = document.getElementById('bulkValue');
  const applyBulk = document.getElementById('applyBulk');

  const restoreDraft = document.getElementById('restoreDraft');
  const clearDraft = document.getElementById('clearDraft');

  const saveBtn = document.getElementById('saveBtn');
  const statusText = document.getElementById('statusText');
  const dot = document.getElementById('dot');

  const weightInputs = document.querySelectorAll('.kpi-weight');
  const weightBadge = document.getElementById('weightTotalBadge');

  const penaltyPct = document.getElementById('penaltyPct');
  const keepPct = document.getElementById('keepPct');
  const fraudPreview = document.getElementById('fraudPreview');

  // Trend tiers dynamic
  const addTrendTierBtn = document.getElementById('addTrendTier');
  const trendTierTbody = document.getElementById('trendTierTbody');
  const trendTierTpl = document.getElementById('trendTierTpl');

  const DRAFT_KEY = 'kpi_payroll_settings_draft_' + period;

  // Helpers
  const onlyDigits = (s) => String(s ?? '').replace(/[^\d]/g, '');
  const formatMoney = (s) => {
    const n = onlyDigits(s);
    if (!n) return '0';
    return n.replace(/\B(?=(\d{3})+(?!\d))/g, ',');
  };
  const parseMoney = (s) => Number(onlyDigits(s) || 0);

  let dirty = false;
  let original = {};

  function setDirty(v){
    dirty = v;
    if (dirty){
      statusText.textContent = 'Có thay đổi chưa lưu • Ctrl+S để lưu nhanh';
      dot.classList.add('warn');
    }else{
      statusText.textContent = 'Đã đồng bộ (không có thay đổi)';
      dot.classList.remove('warn');
    }
  }

  function getVisibleRows(){
    return rows().filter(r => r.style.display !== 'none');
  }

  function recalcTotals(){
    let base = 0, pool = 0, vis = 0;
    getVisibleRows().forEach(r => {
      vis++;
      const baseInp = r.querySelector('input[data-field="base_salary"]');
      const poolInp = r.querySelector('input[data-field="kpi_salary_pool"]');
      base += parseMoney(baseInp?.value);
      pool += parseMoney(poolInp?.value);
    });
    sumBaseEl.textContent = base.toLocaleString('en-US') + ' đ';
    sumPoolEl.textContent = pool.toLocaleString('en-US') + ' đ';
    visibleCountEl.textContent = String(vis);
  }

  function snapshotOriginal(){
    const inputs = form.querySelectorAll('input, select, textarea');
    inputs.forEach(inp => {
      if (!inp.name) return;
      if (inp.type === 'checkbox') {
        original[inp.name] = inp.checked ? '1' : '0';
        return;
      }
      original[inp.name] = (inp.classList.contains('money') ? onlyDigits(inp.value) : String(inp.value ?? ''));
    });
  }

  function markChanged(inp){
    if (!inp.name) return;

    let curr;
    if (inp.type === 'checkbox') curr = inp.checked ? '1' : '0';
    else curr = inp.classList.contains('money') ? onlyDigits(inp.value) : String(inp.value ?? '');

    const orig = original[inp.name];
    const isChanged = (curr !== orig);

    inp.classList.toggle('changed', isChanged);

    const tr = inp.closest('tr');
    if (tr){
      const anyChanged = !!tr.querySelector('input.changed, select.changed, textarea.changed');
      tr.classList.toggle('row-changed', anyChanged);
    }

    if (isChanged) setDirty(true);
  }

  function applyFilters(){
    const q = (rowSearch.value || '').toLowerCase().trim();
    rows().forEach(r => {
      const text = r.innerText.toLowerCase();
      let ok = !q || text.includes(q);

      if (onlyChanged.checked){
        ok = ok && r.classList.contains('row-changed');
      }

      r.style.display = ok ? '' : 'none';
    });
    recalcTotals();
  }

  // KPI weights total
  function refreshWeightTotal(){
    let total = 0;
    weightInputs.forEach(i => total += (parseFloat(i.value || '0') || 0));
    const t = Math.round(total * 100) / 100;

    weightBadge.textContent = t + '%';
    weightBadge.classList.remove('bg-success','bg-warning','bg-danger');

    if (Math.abs(t - 100) < 0.001) weightBadge.classList.add('bg-success');
    else if (t >= 95 && t <= 105) weightBadge.classList.add('bg-warning');
    else weightBadge.classList.add('bg-danger');
  }

  // Anti-fraud preview
  function refreshFraudPreview(){
    const keep = parseInt(keepPct?.value || '0', 10) || 0;
    fraudPreview.textContent = `Lương thực nhận sau phạt = (Lương KPI + Thưởng nóng + Thưởng TikTok) × ${keep}%`;
  }

  function initMoneyInput(inp){
    inp.value = formatMoney(inp.value);

    inp.addEventListener('focus', () => {
      inp.value = onlyDigits(inp.value);
      if (inp.value === '0') inp.select();
    });

    inp.addEventListener('input', () => {
      inp.value = onlyDigits(inp.value);
      markChanged(inp);
      recalcTotals();
      autosaveDraft();
    });

    inp.addEventListener('blur', () => {
      inp.value = formatMoney(inp.value);
      markChanged(inp);
      recalcTotals();
      autosaveDraft();
    });
  }

  // Init money inputs existing
  form.querySelectorAll('input.money').forEach(initMoneyInput);

  // Other inputs tracking
  form.querySelectorAll('input:not(.money), select, textarea').forEach(inp => {
    if (!inp.name) return;
    inp.addEventListener('change', () => { markChanged(inp); autosaveDraft(); });
    inp.addEventListener('input', () => { markChanged(inp); autosaveDraft(); });
  });

  // num in table
  tbody.querySelectorAll('input.num').forEach(inp => {
    inp.addEventListener('input', () => {
      markChanged(inp);
      autosaveDraft();
    });
  });

  // Weight inputs
  weightInputs.forEach(i => i.addEventListener('input', () => {
    refreshWeightTotal();
    markChanged(i);
    autosaveDraft();
  }));

  // Anti fraud inputs
  [penaltyPct, keepPct].forEach(el => {
    if (!el) return;
    el.addEventListener('input', () => {
      refreshFraudPreview();
      markChanged(el);
      autosaveDraft();
    });
  });

  refreshWeightTotal();
  refreshFraudPreview();

  snapshotOriginal();
  recalcTotals();
  setDirty(false);

  // Search & toggle
  rowSearch.addEventListener('input', applyFilters);
  onlyChanged.addEventListener('change', applyFilters);

  // Bulk apply (only visible rows)
  applyBulk.addEventListener('click', () => {
    const field = bulkField.value;
    let val = bulkValue.value;

    if (field === 'base_salary' || field === 'kpi_salary_pool'){
      val = onlyDigits(val);
      if (val === '') val = '0';
    } else {
      val = String(val || '0');
      if (!/^\d+$/.test(val)) val = '0';
    }

    getVisibleRows().forEach(r => {
      const inp = r.querySelector(`input[data-field="${field}"]`);
      if (!inp) return;

      if (inp.classList.contains('money')){
        inp.value = val;
        inp.dispatchEvent(new Event('input', {bubbles:true}));
        inp.value = formatMoney(inp.value);
        inp.dispatchEvent(new Event('blur', {bubbles:true}));
      } else {
        inp.value = val;
        inp.dispatchEvent(new Event('input', {bubbles:true}));
      }
    });

    bulkValue.value = '';
    applyFilters();
  });

  // Draft autosave (localStorage)
  let draftTimer = null;
  function autosaveDraft(){
    if (draftTimer) clearTimeout(draftTimer);
    draftTimer = setTimeout(() => {
      const payload = {};

      form.querySelectorAll('input, select, textarea').forEach(inp => {
        if (!inp.name) return;
        if (inp.type === 'checkbox') payload[inp.name] = inp.checked ? '1' : '0';
        else if (inp.classList.contains('money')) payload[inp.name] = onlyDigits(inp.value);
        else payload[inp.name] = String(inp.value ?? '');
      });

      try{ localStorage.setItem(DRAFT_KEY, JSON.stringify(payload)); }catch(e){}
    }, 250);
  }

  function ensureTrendRowsForDraftKeys(keys){
    // Find max index in keys like bonus_config[trend_video][tiers][N][...]
    let maxIdx = -1;
    keys.forEach(k => {
      const m = k.match(/bonus_config\[trend_video\]\[tiers\]\[(\d+)\]\[/);
      if (m) maxIdx = Math.max(maxIdx, parseInt(m[1], 10));
    });

    if (maxIdx < 0) return;

    const current = trendTierTbody.querySelectorAll('tr.trend-tier-row').length;
    for (let i = current; i <= maxIdx; i++){
      addTrendTierRow(false);
    }
    renumberTrendTiers();
  }

  restoreDraft.addEventListener('click', () => {
    const raw = localStorage.getItem(DRAFT_KEY);
    if (!raw){ alert('Không có nháp để khôi phục.'); return; }
    let data = null;
    try{ data = JSON.parse(raw); }catch(e){ alert('Nháp bị lỗi.'); return; }

    ensureTrendRowsForDraftKeys(Object.keys(data));

    form.querySelectorAll('input, select, textarea').forEach(inp => {
      if (!inp.name) return;
      if (data[inp.name] === undefined) return;

      if (inp.type === 'checkbox'){
        inp.checked = (String(data[inp.name]) === '1');
      } else if (inp.classList.contains('money')){
        inp.value = String(data[inp.name] ?? '0');
        inp.dispatchEvent(new Event('input', {bubbles:true}));
        inp.value = formatMoney(inp.value);
      } else {
        inp.value = String(data[inp.name] ?? '');
      }

      markChanged(inp);
    });

    refreshWeightTotal();
    refreshFraudPreview();
    recalcTotals();
    setDirty(true);
    applyFilters();
  });

  clearDraft.addEventListener('click', () => {
    localStorage.removeItem(DRAFT_KEY);
    alert('Đã xoá nháp của kỳ ' + period);
  });

  // Submit: strip commas for all money fields
  function prepareSubmit(){
    form.querySelectorAll('input.money').forEach(inp => inp.value = onlyDigits(inp.value));
  }

  function doSubmit(){
    if (!dirty){
      alert('Không có thay đổi để lưu.');
      return;
    }
    prepareSubmit();
    form.submit();
  }

  saveBtn.addEventListener('click', doSubmit);

  // Ctrl+S quick save
  document.addEventListener('keydown', (e) => {
    if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 's'){
      e.preventDefault();
      doSubmit();
    }
  });

  // Warn before leaving
  window.addEventListener('beforeunload', (e) => {
    if (!dirty) return;
    e.preventDefault();
    e.returnValue = '';
  });

  // ==========================
  // Trend tiers dynamic add/remove
  // ==========================
  function renumberTrendTiers(){
    const trs = Array.from(trendTierTbody.querySelectorAll('tr.trend-tier-row'));
    trs.forEach((tr, idx) => {
      tr.dataset.idx = String(idx);
      const noEl = tr.querySelector('.trend-tier-no');
      if (noEl) noEl.textContent = String(idx + 1);

      // rewrite names to correct index
      tr.querySelectorAll('input').forEach(inp => {
        if (!inp.name) return;
        inp.name = inp.name
          .replace(/bonus_config\[trend_video\]\[tiers\]\[\d+\]\[/, `bonus_config[trend_video][tiers][${idx}][`);
      });
    });
  }

  function addTrendTierRow(setDirtyFlag = true){
    const current = trendTierTbody.querySelectorAll('tr.trend-tier-row').length;
    const html = trendTierTpl.innerHTML
      .replaceAll('__IDX__', String(current))
      .replaceAll('__NO__', String(current + 1));

    const tmp = document.createElement('tbody');
    tmp.innerHTML = html.trim();
    const tr = tmp.firstElementChild;

    // init money input inside new row
    const moneyInp = tr.querySelector('input.money');
    if (moneyInp) initMoneyInput(moneyInp);

    // bind change tracking for new inputs
    tr.querySelectorAll('input:not(.money)').forEach(inp => {
      inp.addEventListener('input', () => { markChanged(inp); autosaveDraft(); });
      inp.addEventListener('change', () => { markChanged(inp); autosaveDraft(); });
    });

    trendTierTbody.appendChild(tr);
    renumberTrendTiers();

    if (setDirtyFlag){
      setDirty(true);
      autosaveDraft();
    }
  }

  addTrendTierBtn.addEventListener('click', () => addTrendTierRow(true));

  trendTierTbody.addEventListener('click', (e) => {
    const btn = e.target.closest('.removeTrendTier');
    if (!btn) return;

    const tr = btn.closest('tr.trend-tier-row');
    if (!tr) return;

    // do not allow removing last row
    const count = trendTierTbody.querySelectorAll('tr.trend-tier-row').length;
    if (count <= 1){
      alert('Phải có ít nhất 1 mốc.');
      return;
    }

    tr.remove();
    renumberTrendTiers();
    setDirty(true);
    autosaveDraft();
  });

})();
</script>
<script>
(function(){
  const body = document.getElementById('lsTierBody');
  const addBtn = document.getElementById('lsAddTier');
  const tpl = document.getElementById('lsTierTpl');
  const preview = document.getElementById('lsPreview');

  if (!body || !addBtn || !tpl) return;

  function fmt(n){
    n = parseInt(n||0,10)||0;
    return n.toLocaleString('en-US');
  }

  function renumber(){
    const rows = Array.from(body.querySelectorAll('tr[data-ls-tier-row]'));
    rows.forEach((tr, idx) => {
      const i = idx;
      const idxSpan = tr.querySelector('.ls-idx');
      if (idxSpan) idxSpan.textContent = (i+1);

      tr.querySelectorAll('input').forEach(inp => {
        // bonus_config[livestream][tiers][{i}][field]
        const field = inp.name.split(']').pop().replace('[','').replace(']',''); // fallback
        // safer: detect last [...]
        const m = inp.name.match(/\[(min_view|min_engagement|reward)\]$/);
        const f = m ? m[1] : field;

        inp.name = `bonus_config[livestream][tiers][${i}][${f}]`;
      });
    });

    // preview text
    const lines = rows.map((tr, idx) => {
      const ins = tr.querySelectorAll('input');
      const minView = ins[0]?.value ?? 0;
      const minEng  = ins[1]?.value ?? 0;
      const reward  = ins[2]?.value ?? 0;
      return `Mốc ${idx+1}: ≥ ${fmt(minView)} view & ≥ ${fmt(minEng)} tương tác → ${fmt(reward)}đ / buổi`;
    });

    preview.textContent = lines.length ? lines.join(' • ') : '';
  }

  function addRow(){
    const node = tpl.content.cloneNode(true);
    const tr = node.querySelector('tr');

    const idx = body.querySelectorAll('tr[data-ls-tier-row]').length;
    tr.querySelector('.ls-idx').textContent = (idx+1);

    // set correct names
    tr.querySelectorAll('input').forEach(inp => {
      const raw = inp.name; // __NAME__[min_view]...
      const f = raw.match(/\[(min_view|min_engagement|reward)\]/)?.[1] || 'min_view';
      inp.name = `bonus_config[livestream][tiers][${idx}][${f}]`;
    });

    body.appendChild(node);
    renumber();
  }

  body.addEventListener('click', (e) => {
    const btn = e.target.closest('[data-ls-remove]');
    if (!btn) return;

    const tr = btn.closest('tr[data-ls-tier-row]');
    if (!tr) return;

    // giữ lại tối thiểu 1 mốc
    const rows = body.querySelectorAll('tr[data-ls-tier-row]');
    if (rows.length <= 1) {
      alert('Phải có ít nhất 1 mốc livestream.');
      return;
    }

    tr.remove();
    renumber();
  });

  addBtn.addEventListener('click', addRow);

  body.addEventListener('input', () => renumber());
  renumber();
})();
</script>
@endsection
