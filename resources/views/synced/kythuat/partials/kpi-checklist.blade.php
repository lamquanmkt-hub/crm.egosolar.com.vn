{{--
    Checklist nguồn dưới 6 tiêu chí của người đang xem.
    Dữ liệu: App\Services\TechnicalKpi\KpiChecklistPresenter (đọc kết quả bộ chấm, không tự tính điểm).
--}}
@php
    $kclChip = fn (int $n, string $label, string $tone) => $n > 0 ? ['n' => $n, 'label' => $label, 'tone' => $tone] : null;
@endphp
<section class="kpx-cl" id="kpx-checklist" aria-label="Checklist nguồn theo tiêu chí">
    <div class="kpx-cl-intro">
        <h3 class="tw-card__title mb-1">Checklist nguồn theo tiêu chí</h3>
        <p class="text-muted small mb-0">
            Kỳ {{ $periodLabel }}. <strong>Thực hiện</strong> là trạng thái việc/hồ sơ; <strong>KPI</strong> là phần điểm theo bộ chấm.
            Việc còn hạn chưa tính và không mất điểm. Bấm tên nguồn để mở hồ sơ.
        </p>
    </div>

    @foreach($kpiChecklist as $block)
        @php
            $kclChips = array_filter([
                $kclChip($block['counts']['earned'], 'nhận điểm', 'success'),
                $kclChip($block['counts']['lost'], 'mất điểm', 'danger'),
                $kclChip($block['counts']['waiting'], 'chưa tính / còn hạn', 'muted'),
                $kclChip($block['counts']['missing'], 'thiếu dữ liệu', 'warning'),
                $kclChip($block['counts']['excluded'], 'loại trừ', 'muted'),
            ]);
            $kclVisible = array_slice($block['rows'], 0, $block['visible']);
            $kclMore = array_slice($block['rows'], $block['visible']);
        @endphp
        <article class="kpx-cl-block" id="kpx-checklist-{{ $block['code'] }}" data-kpx-checklist="{{ $block['code'] }}">
            <header class="kpx-cl-head">
                <div class="kpx-cl-title">
                    <strong>{{ $block['no'] }}. {{ $block['name'] }}</strong>
                    @if($block['weight_points'] !== null)
                        <span class="text-muted small">· tối đa {{ app(\App\Services\TechnicalKpi\KpiChecklistPresenter::class)->points($block['weight_points']) }} điểm</span>
                    @endif
                </div>
                <div class="kpx-cl-sum">
                    <span class="kpx-tag kpx-tag--{{ $block['score_points'] !== null ? 'primary' : 'muted' }}" data-kpx-checklist-score>{{ $block['score_text'] }}</span>
                    @foreach($kclChips as $chip)
                        <span class="kpx-tag kpx-tag--{{ $chip['tone'] }} kpx-tag--soft">{{ $chip['n'] }} {{ $chip['label'] }}</span>
                    @endforeach
                </div>
            </header>

            @if($block['rows'] === [])
                <div class="kpx-cl-empty">
                    <strong>Chưa có dữ liệu.</strong>
                    {{ $block['missing'] !== [] ? implode(' ', $block['missing']) : 'Không có bản ghi nguồn trong kỳ.' }}
                    @if($block['input_hint'])
                        <a href="{{ $block['input_hint']['url'] }}" target="_blank" rel="noopener">{{ $block['input_hint']['label'] }} <i class="bi bi-box-arrow-up-right" aria-hidden="true"></i></a>
                    @endif
                </div>
            @else
                @if($block['missing'] !== [])
                    <div class="kpx-cl-note"><i class="bi bi-info-circle" aria-hidden="true"></i> {{ implode(' ', $block['missing']) }}</div>
                @endif
                <ol class="kpx-cl-rows">
                    @foreach($kclVisible as $row)
                        @include('synced.kythuat.partials.kpi-checklist-row', ['row' => $row])
                    @endforeach
                </ol>
                @if($kclMore !== [])
                    <details class="kpx-cl-more">
                        <summary>Xem thêm {{ count($kclMore) }} mục</summary>
                        <ol class="kpx-cl-rows" start="{{ count($kclVisible) + 1 }}">
                            @foreach($kclMore as $row)
                                @include('synced.kythuat.partials.kpi-checklist-row', ['row' => $row])
                            @endforeach
                        </ol>
                    </details>
                @endif
            @endif
        </article>
    @endforeach
</section>

@once
<style>
    .kpx-cl{margin-top:1.25rem;display:grid;gap:.75rem}
    .kpx-cl-block{border:1px solid #e3e9f1;border-radius:10px;background:#fff;overflow:hidden}
    .kpx-cl-head{display:flex;flex-wrap:wrap;justify-content:space-between;align-items:center;gap:.5rem;padding:.6rem .85rem;background:#f8fafc;border-bottom:1px solid #edf1f6}
    .kpx-cl-title strong{font-size:.92rem;color:#17314e}
    .kpx-cl-sum{display:flex;flex-wrap:wrap;gap:.35rem}
    .kpx-tag{display:inline-flex;align-items:center;border-radius:999px;padding:.12rem .55rem;font-size:.74rem;font-weight:600;line-height:1.5;border:1px solid transparent;white-space:nowrap}
    .kpx-tag--primary{background:#e8f0ff;color:#1d4ed8;border-color:#c7d7fb}
    .kpx-tag--success{background:#e8f7ee;color:#15803d;border-color:#c3e8d1}
    .kpx-tag--danger{background:#fdecec;color:#b91c1c;border-color:#f5c9c9}
    .kpx-tag--warning{background:#fff6e5;color:#9a5b00;border-color:#f3dcae}
    .kpx-tag--info{background:#e9f6fb;color:#0e6a8a;border-color:#c6e6f2}
    .kpx-tag--muted{background:#f1f4f8;color:#556274;border-color:#e1e7ef}
    .kpx-tag--soft{font-weight:500}
    .kpx-cl-rows{list-style:none;margin:0;padding:0}
    .kpx-cl-row{display:grid;grid-template-columns:minmax(220px,1.6fr) minmax(170px,1fr) minmax(200px,1.3fr);gap:.4rem .9rem;padding:.55rem .85rem;border-top:1px dashed #edf1f6;align-items:start}
    .kpx-cl-rows .kpx-cl-row:first-child{border-top:0}
    .kpx-cl-row>div{min-width:0}
    .kpx-cl-row .kpx-tag{white-space:normal;border-radius:10px;max-width:100%}
    .kpx-cl-src a{font-weight:600;word-break:break-word}
    .kpx-cl-src small,.kpx-cl-kpi small{display:block;color:#64748b;font-size:.74rem;margin-top:.15rem}
    .kpx-cl-label{display:none;font-size:.68rem;text-transform:uppercase;letter-spacing:.03em;color:#8a97a8;margin-right:.35rem}
    .kpx-cl-empty,.kpx-cl-note{padding:.55rem .85rem;font-size:.82rem;color:#7a4a00;background:#fffaf0}
    .kpx-cl-empty a{margin-left:.25rem}
    .kpx-cl-more>summary{cursor:pointer;padding:.45rem .85rem;font-size:.8rem;color:#1d4ed8;border-top:1px solid #edf1f6}
    @media (max-width: 768px){
        .kpx-cl-row{grid-template-columns:1fr;gap:.3rem}
        .kpx-cl-label{display:inline}
        .kpx-cl-head{align-items:flex-start}
    }
</style>
@endonce
