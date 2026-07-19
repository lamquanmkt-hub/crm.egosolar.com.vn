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

  .k-shell{ padding: 16px 16px 24px; }
  @media (max-width: 768px){ .k-shell{ padding: 12px 12px 24px; } }

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
    font-size: 28px;
    font-weight: 950;
    letter-spacing: -.03em;
    color: var(--txt);
    margin: 0;
    line-height: 1.15;
  }
  .k-sub{ color: var(--muted); margin-top: 6px; max-width: 1150px; font-weight: 700; }
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

  .grid{
    display:grid;
    grid-template-columns: 1.2fr .8fr;
    gap: 12px;
  }
  @media (max-width: 992px){
    .grid{ grid-template-columns: 1fr; }
  }

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

  .tbl{
    width:100%;
    border-collapse: separate;
    border-spacing: 0;
  }
  .tbl th{
    font-size: 12px;
    color: var(--muted);
    font-weight: 900;
    padding: 10px 10px;
    border-bottom: 1px solid rgba(0,0,0,.06);
    white-space: nowrap;
  }
  .tbl td{
    padding: 8px 10px;
    border-bottom: 1px solid rgba(0,0,0,.06);
    vertical-align: middle;
  }

  .mini{
    font-size: 12px;
    color: var(--muted);
    font-weight: 750;
  }

  .k-value{
    display:flex;
    align-items:baseline;
    justify-content:space-between;
    gap:10px;
    padding: 10px 0;
    border-bottom: 1px dashed rgba(0,0,0,.10);
  }
  .k-value:last-child{ border-bottom: 0; padding-bottom: 0; }
  .k-lb{ font-weight: 850; color: var(--muted); }
  .k-vl{ font-weight: 950; color: var(--txt); }

  .linkx{
    text-decoration:none;
    font-weight: 850;
    color: rgba(0,123,255,.92);
  }
  .linkx:hover{ text-decoration: underline; }

  .badge-soft{
    display:inline-flex;
    align-items:center;
    gap:8px;
    padding: 7px 10px;
    border-radius: 999px;
    border: 1px solid rgba(0,0,0,.08);
    background: rgba(0,0,0,.03);
    font-weight: 900;
    font-size: 12px;
  }
</style>

@php
  $setting = $setting ?? null;
  $rule = $rule ?? null;
  $bonusConfig = $bonusConfig ?? [];
  $actual = $actual ?? null;
  $payroll = $payroll ?? [];
  $targetUser = $targetUser ?? auth()->user();

  $baseSalary = (int)($setting->base_salary ?? 0);
  $pool = (int)($setting->kpi_salary_pool ?? 0);
  $tPost = (int)($setting->target_post ?? 0);
  $tAi = (int)($setting->target_video_ai ?? 0);
  $tReview = (int)($setting->target_video_review ?? 0);

  $aPost = (int)($actual->actual_post ?? 0);
  $aAi = (int)($actual->actual_video_ai ?? 0);
  $aReview = (int)($actual->actual_video_review ?? 0);

  $trendVideos = (array)($actual->trend_videos ?? []);
  $livestreams = (array)($actual->livestreams ?? []);
  $isFraud = (bool)($actual->is_fraud ?? false);
@endphp

<div class="k-shell">
  <div class="k-hero">
    <div class="k-hero-top">
      <div>
        <div class="d-flex flex-wrap gap-2 align-items-center mb-2">
          <span class="pill">KPI & Lương</span>
          <span class="pill">Kỳ: {{ $period }}</span>
          <span class="pill">Tự lấy từ lịch biên tập</span>
        </div>
        <h1 class="k-title">KPI & Lương (Show)</h1>
        <div class="k-sub">
          Trang này chỉ hiển thị. KPI thực tế được tự đồng bộ từ <b>Lịch biên tập</b> và tính theo <b>Settings</b>.
        </div>
      </div>

      {{-- GET filter: period + (manager) user --}}
      <form method="GET" action="{{ route('marketing.kpi-payroll.my') }}" class="k-controls">
        <div class="d-flex flex-column">
          <div class="mini mb-1">Kỳ</div>
          <input type="month" class="form-control form-42" name="period" value="{{ $period }}">
        </div>

        @if(!empty($users) && $users->count())
          <div class="d-flex flex-column">
            <div class="mini mb-1">Nhân viên</div>
            <select class="form-select form-42" name="user_id">
              @foreach($users as $u)
                <option value="{{ $u->id }}" {{ (int)($targetUser->id ?? auth()->id()) === (int)$u->id ? 'selected' : '' }}>
                  #{{ $u->id }} - {{ $u->name }}
                </option>
              @endforeach
            </select>
          </div>
        @endif

        <button class="btn btn-primary btn-round" type="submit">👀 Xem</button>
        <a class="btn btn-outline-secondary btn-round" href="{{ url()->previous() }}">↩ Quay lại</a>

        @if(auth()->user()->hasAnyRole(['admin','marketing_manager']))
          <a class="btn btn-outline-secondary btn-round" href="{{ route('marketing.kpi-payroll.settings', ['period' => $period]) }}">
            ⚙️ Settings
          </a>
        @endif
      </form>
    </div>

    <div class="k-body">
      <div class="stats">
        <div class="stat">
          <div class="slabel">Lương cơ bản</div>
          <div class="sval" id="stBase">{{ number_format($baseSalary) }} đ</div>
        </div>
        <div class="stat">
          <div class="slabel">Quỹ lương KPI</div>
          <div class="sval" id="stPool">{{ number_format($pool) }} đ</div>
        </div>
        <div class="stat">
          <div class="slabel">KPI% (tính)</div>
          <div class="sval"><span id="stKpiPct">{{ number_format((float)($payroll['kpi_percent'] ?? 0), 2) }}%</span></div>
        </div>
        <div class="stat">
          <div class="slabel">Thực nhận (tính)</div>
          <div class="sval" id="stTakeHome">{{ number_format((int)($payroll['take_home'] ?? $baseSalary)) }} đ</div>
        </div>
      </div>

      <div class="grid">
        {{-- LEFT --}}
        <div class="d-flex flex-column gap-3">

          <div class="cardx">
            <div class="cardx-hd">
              <div>
                <div class="cardx-tt">1) KPI thực tế (tự đồng bộ)</div>
                <div class="cardx-sub">
                  Dữ liệu lấy theo các nội dung <b>Đã đăng</b> trong tháng, assignee đúng user.
                </div>
              </div>
              <span class="pill">Target: Post {{ $tPost }} • AI {{ $tAi }} • Review {{ $tReview }}</span>
            </div>

            <div class="cardx-bd">
              <div class="k-value">
                <div class="k-lb">Bài viết thực tế</div>
                <div class="k-vl" id="valPost">{{ number_format($aPost) }}</div>
              </div>
              <div class="k-value">
                <div class="k-lb">Video AI thực tế</div>
                <div class="k-vl" id="valAi">{{ number_format($aAi) }}</div>
              </div>
              <div class="k-value">
                <div class="k-lb">Video Review thực tế</div>
                <div class="k-vl" id="valReview">{{ number_format($aReview) }}</div>
              </div>

              <div class="mini mt-2">
                Nếu thấy sai số: kiểm tra lại <b>status = posted</b>, <b>assignee_user_id</b>, và <b>content_type</b> trong Lịch biên tập.
              </div>
            </div>
          </div>

          <div class="cardx">
            <div class="cardx-hd">
              <div>
                <div class="cardx-tt">2) Trend Video (từ lịch biên tập)</div>
                <div class="cardx-sub">Chỉ hiển thị danh sách. Thưởng sẽ là 0 nếu chưa có views/tương tác.</div>
              </div>
              <span class="badge-soft">Auto</span>
            </div>

            <div class="cardx-bd p-0">
              <div class="table-responsive">
                <table class="tbl" id="tblVideos">
                  <thead>
                    <tr>
                      <th style="min-width:220px;">Tiêu đề</th>
                      <th style="min-width:240px;">Link</th>
                      <th>Views</th>
                      <th>Tương tác</th>
                      <th>Thưởng / video</th>
                    </tr>
                  </thead>
                  <tbody>
                    @if(count($trendVideos))
                      @foreach($trendVideos as $v)
                        @php
                          $title = (string)($v['title'] ?? '');
                          $url = (string)($v['url'] ?? '');
                          $views = (int)($v['views'] ?? 0);
                          $eng = (int)($v['engagement'] ?? 0);
                        @endphp
                        <tr>
                          <td class="fw-bold">{{ $title ?: '—' }}</td>
                          <td>
                            @if($url)
                              <a class="linkx" href="{{ $url }}" target="_blank" rel="noopener">Mở link</a>
                            @else
                              <span class="mini">—</span>
                            @endif
                          </td>
                          <td data-views>{{ $views }}</td>
                          <td data-eng>{{ $eng }}</td>
                          <td class="fw-bold" data-video-reward>0 đ</td>
                        </tr>
                      @endforeach
                    @else
                      <tr>
                        <td colspan="5" class="p-3 text-center mini">Không có Trend Video trong kỳ.</td>
                      </tr>
                    @endif
                  </tbody>
                </table>
              </div>
            </div>
          </div>

          <div class="cardx">
            <div class="cardx-hd">
              <div>
                <div class="cardx-tt">3) Livestream (từ lịch biên tập)</div>
                <div class="cardx-sub">Chỉ hiển thị danh sách. Thưởng sẽ là 0 nếu chưa có views/leads/thời lượng.</div>
              </div>
              <span class="badge-soft">Auto</span>
            </div>

            <div class="cardx-bd p-0">
              <div class="table-responsive">
                <table class="tbl" id="tblLives">
                  <thead>
                    <tr>
                      <th style="min-width:200px;">Tiêu đề</th>
                      <th style="min-width:240px;">Link</th>
                      <th>Phút</th>
                      <th>Views</th>
                      <th>Tương tác</th>
                      <th>Leads</th>
                      <th>Tổng thưởng / buổi</th>
                    </tr>
                  </thead>
                  <tbody>
                    @if(count($livestreams))
                      @foreach($livestreams as $l)
                        @php
                          $title = (string)($l['title'] ?? '');
                          $url = (string)($l['url'] ?? '');
                          $duration = (int)($l['duration_min'] ?? 0);
                          $views = (int)($l['views'] ?? 0);
                          $eng = (int)($l['engagement'] ?? 0);
                          $leads = (int)($l['lead_count'] ?? 0);
                        @endphp
                        <tr>
                          <td class="fw-bold">{{ $title ?: '—' }}</td>
                          <td>
                            @if($url)
                              <a class="linkx" href="{{ $url }}" target="_blank" rel="noopener">Mở link</a>
                            @else
                              <span class="mini">—</span>
                            @endif
                          </td>
                          <td data-duration>{{ $duration }}</td>
                          <td data-views>{{ $views }}</td>
                          <td data-eng>{{ $eng }}</td>
                          <td data-leads>{{ $leads }}</td>
                          <td class="fw-bold" data-live-reward>0 đ</td>
                        </tr>
                      @endforeach
                    @else
                      <tr>
                        <td colspan="7" class="p-3 text-center mini">Không có Livestream trong kỳ.</td>
                      </tr>
                    @endif
                  </tbody>
                </table>
              </div>
            </div>
          </div>

          <div class="cardx">
            <div class="cardx-hd">
              <div>
                <div class="cardx-tt">4) Anti-fraud</div>
                <div class="cardx-sub">Trạng thái hiển thị theo dữ liệu actual.</div>
              </div>
              <span class="pill" id="pillKeep"></span>
            </div>
            <div class="cardx-bd">
              <div class="d-flex flex-wrap gap-2 align-items-center">
                <span class="badge-soft">{{ $isFraud ? 'Fraud ON' : 'Fraud OFF' }}</span>
                <span class="mini">Ghi chú:</span>
                <span class="fw-bold">{{ $actual->note ?? '—' }}</span>
              </div>
            </div>
          </div>

        </div>

        {{-- RIGHT --}}
        <div class="d-flex flex-column gap-3">

          <div class="cardx">
            <div class="cardx-hd">
              <div>
                <div class="cardx-tt">Tổng hợp (tính theo rule + settings)</div>
                <div class="cardx-sub">Hiển thị số tính toán.</div>
              </div>
              <span class="pill">User: {{ $targetUser->name ?? '—' }}</span>
            </div>
            <div class="cardx-bd">
              <div class="d-flex justify-content-between mb-2">
                <div class="mini">KPI%</div>
                <div class="fw-bold" id="sumKpiPct">{{ number_format((float)($payroll['kpi_percent'] ?? 0), 2) }}%</div>
              </div>
              <div class="d-flex justify-content-between mb-2">
                <div class="mini">Lương KPI</div>
                <div class="fw-bold" id="sumKpiSalary">{{ number_format((int)($payroll['kpi_salary'] ?? 0)) }} đ</div>
              </div>

              <hr>

              <div class="d-flex justify-content-between mb-2">
                <div class="mini">Thưởng Trend Video</div>
                <div class="fw-bold" id="sumTrend">{{ number_format((int)($payroll['bonus_trend'] ?? 0)) }} đ</div>
              </div>
              <div class="d-flex justify-content-between mb-2">
                <div class="mini">Thưởng Livestream</div>
                <div class="fw-bold" id="sumLive">{{ number_format((int)($payroll['bonus_livestream'] ?? 0)) }} đ</div>
              </div>
              <div class="d-flex justify-content-between mb-2">
                <div class="mini">Thưởng Lead</div>
                <div class="fw-bold" id="sumLead">{{ number_format((int)($payroll['bonus_lead'] ?? 0)) }} đ</div>
              </div>
              <div class="d-flex justify-content-between mb-2">
                <div class="mini">Tổng thưởng</div>
                <div class="fw-bold" id="sumBonus">{{ number_format((int)($payroll['bonus_total'] ?? 0)) }} đ</div>
              </div>

              <hr>

              <div class="d-flex justify-content-between mb-2">
                <div class="mini">KPI + Thưởng</div>
                <div class="fw-bold" id="sumBeforePenalty">{{ number_format((int)($payroll['kpi_plus_bonus'] ?? 0)) }} đ</div>
              </div>
              <div class="d-flex justify-content-between mb-2">
                <div class="mini">Phạt (nếu gian lận)</div>
                <div class="fw-bold text-danger" id="sumPenalty">{{ number_format((int)($payroll['penalty'] ?? 0)) }} đ</div>
              </div>

              <hr>

              <div class="d-flex justify-content-between mb-2">
                <div class="mini">Lương cơ bản</div>
                <div class="fw-bold" id="sumBase">{{ number_format($baseSalary) }} đ</div>
              </div>
              <div class="d-flex justify-content-between">
                <div class="mini">Thực nhận</div>
                <div class="fw-bold fs-5" id="sumTakeHome">{{ number_format((int)($payroll['take_home'] ?? $baseSalary)) }} đ</div>
              </div>

              <div class="mini mt-3">
                Lưu ý: Nếu “Quỹ lương KPI” = 0 thì lương KPI = 0 (cần cấu hình pool trong Settings).
              </div>
            </div>
          </div>

        </div>
      </div>

    </div>
  </div>
</div>

<script>
(function(){
  const fmtVND = (n) => new Intl.NumberFormat('vi-VN').format(Math.round(n||0)) + ' đ';
  const toInt = (v) => {
    const s = String(v ?? '').replace(/[^\d\-]/g,'');
    const x = parseInt(s || '0', 10);
    return isNaN(x) ? 0 : x;
  };

  const cfg = {
    base_salary: {{ (int)$baseSalary }},
    pool: {{ (int)$pool }},
    targets: {
      post: {{ (int)$tPost }},
      ai: {{ (int)$tAi }},
      review: {{ (int)$tReview }},
    },
    rule: {
      weight_review: {{ (float)($rule->weight_review ?? 40) }},
      weight_ai: {{ (float)($rule->weight_ai ?? 30) }},
      weight_post: {{ (float)($rule->weight_post ?? 30) }},
      bonus_over_review: {{ (float)($rule->bonus_over_review ?? 3) }},
      bonus_over_ai: {{ (float)($rule->bonus_over_ai ?? 1) }},
      bonus_over_post: {{ (float)($rule->bonus_over_post ?? 0.5) }},
    },
    bonus: @json($bonusConfig),
    actual: {
      post: {{ (int)$aPost }},
      ai: {{ (int)$aAi }},
      review: {{ (int)$aReview }},
      is_fraud: {{ $isFraud ? 'true' : 'false' }},
    }
  };

  function normalizeWeights(wr, wa, wp){
    const sum = (wr+wa+wp);
    if(sum<=0) return [40,30,30];
    wr = wr*100/sum;
    wa = wa*100/sum;
    wp = 100 - wr - wa;
    return [wr,wa,wp];
  }

  function pickTierReward(tiers, views, eng){
    let best = 0;
    (tiers||[]).forEach(t=>{
      const mv = toInt(t.min_view ?? 0);
      const me = toInt(t.min_engagement ?? 0);
      const rw = toInt(t.reward ?? 0);
      if(views>=mv && eng>=me) best = Math.max(best, rw);
    });
    return best;
  }

  function calcAndRender(){
    const [wReview,wAi,wPost] = normalizeWeights(
      cfg.rule.weight_review, cfg.rule.weight_ai, cfg.rule.weight_post
    );

    const calcOne = (actual, target, weight, bonusOver)=>{
      if(target<=0) return {base:0, over:0, ratio:0};
      const ratio = actual/target;
      const base = Math.min(1, ratio) * (weight/100);
      const over = Math.max(0, ratio-1) * bonusOver * (weight/100);
      return {base, over, ratio};
    };

    const rReview = calcOne(cfg.actual.review, cfg.targets.review, wReview, cfg.rule.bonus_over_review);
    const rAi = calcOne(cfg.actual.ai, cfg.targets.ai, wAi, cfg.rule.bonus_over_ai);
    const rPost = calcOne(cfg.actual.post, cfg.targets.post, wPost, cfg.rule.bonus_over_post);

    let kpiRate = rReview.base + rAi.base + rPost.base + rReview.over + rAi.over + rPost.over;
    kpiRate = Math.max(0, Math.min(2, kpiRate));
    const kpiSalary = Math.round(cfg.pool * kpiRate);

    // Trend
    const tvTiers = (cfg.bonus?.trend_video?.tiers || []);
    let bonusTrend = 0;
    document.querySelectorAll('#tblVideos tbody tr').forEach(tr=>{
      const viewsEl = tr.querySelector('[data-views]');
      const engEl = tr.querySelector('[data-eng]');
      if(!viewsEl || !engEl) return;

      const views = toInt(viewsEl.textContent);
      const eng = toInt(engEl.textContent);
      const rw = pickTierReward(tvTiers, views, eng);
      bonusTrend += rw;

      const cell = tr.querySelector('[data-video-reward]');
      if(cell) cell.textContent = fmtVND(rw);
    });

    // Live
    const lsMin = toInt(cfg.bonus?.livestream?.min_duration_min || 0);
    const leadReward = toInt(cfg.bonus?.livestream?.lead_reward || 0);
    const lsTiers = (cfg.bonus?.livestream?.tiers || []);

    let bonusLive = 0, bonusLead = 0;

    document.querySelectorAll('#tblLives tbody tr').forEach(tr=>{
      const durEl = tr.querySelector('[data-duration]');
      const viewsEl = tr.querySelector('[data-views]');
      const engEl = tr.querySelector('[data-eng]');
      const leadsEl = tr.querySelector('[data-leads]');
      if(!durEl || !viewsEl || !engEl || !leadsEl) return;

      const duration = toInt(durEl.textContent);
      const views = toInt(viewsEl.textContent);
      const eng = toInt(engEl.textContent);
      const leads = toInt(leadsEl.textContent);

      let rwTier = 0, rwLead = 0;
      if(duration >= lsMin){
        rwTier = pickTierReward(lsTiers, views, eng);
        rwLead = leads * leadReward;
      }
      bonusLive += rwTier;
      bonusLead += rwLead;

      const cell = tr.querySelector('[data-live-reward]');
      if(cell) cell.textContent = fmtVND(rwTier + rwLead);
    });

    const bonusTotal = bonusTrend + bonusLive + bonusLead;
    const beforePenalty = kpiSalary + bonusTotal;

    const keepPercent = toInt(cfg.bonus?.anti_fraud?.keep_percent ?? 100);
    const keep = Math.max(0, Math.min(100, keepPercent));

    let after = beforePenalty;
    let penalty = 0;
    if(cfg.actual.is_fraud){
      after = Math.round(beforePenalty * keep / 100);
      penalty = beforePenalty - after;
    }
    const takeHome = cfg.base_salary + after;

    // Update UI (override display)
    const kpiPct = (kpiRate * 100).toFixed(2) + '%';

    document.getElementById('stKpiPct') && (document.getElementById('stKpiPct').textContent = kpiPct);
    document.getElementById('stTakeHome') && (document.getElementById('stTakeHome').textContent = fmtVND(takeHome));

    document.getElementById('sumKpiPct') && (document.getElementById('sumKpiPct').textContent = kpiPct);
    document.getElementById('sumKpiSalary') && (document.getElementById('sumKpiSalary').textContent = fmtVND(kpiSalary));

    document.getElementById('sumTrend') && (document.getElementById('sumTrend').textContent = fmtVND(bonusTrend));
    document.getElementById('sumLive') && (document.getElementById('sumLive').textContent = fmtVND(bonusLive));
    document.getElementById('sumLead') && (document.getElementById('sumLead').textContent = fmtVND(bonusLead));
    document.getElementById('sumBonus') && (document.getElementById('sumBonus').textContent = fmtVND(bonusTotal));

    document.getElementById('sumBeforePenalty') && (document.getElementById('sumBeforePenalty').textContent = fmtVND(beforePenalty));
    document.getElementById('sumPenalty') && (document.getElementById('sumPenalty').textContent = fmtVND(penalty));
    document.getElementById('sumTakeHome') && (document.getElementById('sumTakeHome').textContent = fmtVND(takeHome));

    const pillKeep = document.getElementById('pillKeep');
    if(pillKeep){
      pillKeep.textContent = cfg.actual.is_fraud ? `Fraud ON • Keep ${keep}%` : `Fraud OFF`;
    }
  }

  calcAndRender();
})();
</script>
@endsection