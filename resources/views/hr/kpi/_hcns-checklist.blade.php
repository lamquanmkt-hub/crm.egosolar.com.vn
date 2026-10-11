{{--
    Checklist nguồn KPI HCNS của một người/kỳ (App\Services\Hr\Kpi\HcnsKpiChecklist — không tự tính công thức).
    Biến: $checklist = ['rule' => …, 'blocks' => […], 'month' => 'Y-m'].
--}}
@php
    $hkNum = static fn ($n) => rtrim(rtrim(number_format((float) $n, 2, ',', '.'), '0'), ',');
    $hkFmt = static fn ($t) => $t ? \Illuminate\Support\Carbon::parse($t)->format('H:i d/m') : '—';
    $hkStates = [
        'counted' => ['được tính vào số đo', 'success'],
        'earned' => ['nhận điểm', 'success'], 'lost' => ['mất điểm', 'danger'], 'pending' => ['chờ xác nhận', 'info'],
        'waiting' => ['còn hạn', 'muted'], 'excluded' => ['loại trừ', 'muted'], 'missing' => ['thiếu dữ liệu', 'warning'],
    ];
    $hkRowState = [
        'counted' => 'Được tính vào số đo',
        'earned' => 'Được tính — đạt', 'lost' => 'Được tính — không đạt', 'pending' => 'Chưa tính — chờ xác nhận',
        'waiting' => 'Chưa tính — còn hạn', 'excluded' => 'Không tính — loại trừ', 'missing' => 'Chưa tính — thiếu dữ liệu',
    ];
    $hkMoney = static fn ($v) => number_format((float) $v, 0, ',', '.').' đ';
    $hkDay = static fn ($t) => $t ? \Illuminate\Support\Carbon::parse($t)->format('d/m/Y') : '—';
@endphp
<section class="hk-cl" aria-label="Checklist nguồn KPI HCNS" data-hk-checklist>
    <div class="hk-cl-intro">
        <h3>Checklist nguồn theo tiêu chí</h3>
        <p>
            Kỳ {{ \Illuminate\Support\Carbon::parse($checklist['month'].'-01')->format('m/Y') }} ·
            <strong>{{ $checklist['rule']['status'] === 'applied' ? $checklist['rule']['label'] : 'Quy tắc nháp — chưa duyệt' }}</strong>.
            @if(! empty($checklist['from_snapshot']))<strong class="hk-cl-snap">Ảnh chụp lúc chốt kỳ lương — không đổi theo nguồn hiện tại.</strong>@endif
            <strong>Thực hiện</strong> là trạng thái hồ sơ; <strong>KPI</strong> cho biết đã được tính chưa và vì sao. Bấm tên hồ sơ để mở nguồn.
            @if($checklist['rule']['status'] !== 'applied')
                Điểm chính thức vẫn là bảng nhập tay phía trên; số dưới đây chỉ xem trước theo công thức baseline (trọng số chia đều tạm, chưa duyệt).
            @endif
        </p>
    </div>

    @foreach($checklist['blocks'] as $block)
        <article class="hk-cl-block {{ $block['auto'] ? 'is-auto' : 'is-manual' }}" id="hk-{{ $block['code'] }}" data-hk-block="{{ $block['code'] }}">
            <header class="hk-cl-head">
                <div class="hk-cl-title">
                    <strong>{{ $block['no'] }}. {{ $block['name'] }}</strong>
                    <span>{{ $block['doc_code'] }}@if($block['target_text']) · mục tiêu {{ $block['target_text'] }}@endif @if($block['weight'] !== null) · trọng số tạm {{ $hkNum($block['weight']) }}@endif</span>
                </div>
                <div class="hk-cl-sum">
                    @if($block['auto'])
                        @php $sum = $block['summary']; @endphp
                        @foreach($hkStates as $state => [$label, $tone])
                            @if(($sum['counts'][$state] ?? 0) > 0)
                                <span class="hk-tag hk-tag--{{ $tone }} hk-tag--soft">{{ $sum['counts'][$state] }} {{ $label }}</span>
                            @endif
                        @endforeach
                    @else
                        <span class="hk-tag hk-tag--muted">Chấm tay</span>
                    @endif
                </div>
            </header>
            {{-- Ba lớp số tách bạch: số đo nghiệp vụ → điểm xem trước theo công thức → điểm chính thức của bộ chấm. --}}
            <dl class="hk-cl-layers" data-hk-layers>
                @if($block['auto'])
                    <div>
                        <dt>Số đo</dt>
                        <dd data-hk-measure>
                            @if(! empty($sum['measure_text']))
                                {{ $sum['measure_text'] }}
                            @elseif(! empty($sum['groups']))
                                @foreach($sum['groups'] as $g)
                                    <span class="hk-cl-group-measure">{{ $g['label'] }}: {{ ! empty($g['measure_text']) ? $g['measure_text'] : ($g['value'] !== null ? $g['numerator'].'/'.$g['denominator'].' = '.$hkNum($g['value']).'%' : 'chưa có số đo') }}</span>
                                @endforeach
                            @elseif($sum['value'] !== null)
                                {{ $sum['numerator'] }}/{{ $sum['denominator'] }} = {{ $hkNum($sum['value']) }}%
                            @else
                                Chưa có số đo
                            @endif
                        </dd>
                    </div>
                    <div>
                        <dt>Xem trước</dt>
                        <dd data-hk-preview>
                            @if($block['preview_achievement'] !== null)
                                {{ $hkNum($block['preview_achievement']) }}% đạt @if($block['preview_points'] !== null) · {{ $hkNum($block['preview_points']) }} điểm @endif <small>(công thức tạm, chưa duyệt)</small>
                            @else
                                — <small>{{ $sum['combined_reason'] ?? 'chưa có số đo' }}</small>
                            @endif
                        </dd>
                    </div>
                @endif
                <div>
                    <dt>Chính thức</dt>
                    <dd @if(($block['official_score']['from'] ?? null) === 'source' && ($block['official_score']['achievement'] ?? null) !== null) data-hk-official @endif data-hk-official-score>
                        {{ $block['official_score']['text'] ?? 'Đang xem trước bản nháp — xem tổng chính thức ở cột bên' }}
                    </dd>
                </div>
            </dl>

            @if(! $block['auto'])
                <div class="hk-cl-note">
                    <i class="bi bi-pencil-square" aria-hidden="true"></i> {{ $block['note'] }}
                </div>
            @else
                <div class="hk-cl-note">
                    <i class="bi bi-info-circle" aria-hidden="true"></i> {{ $block['note'] }}
                    Nguồn: {{ $block['source_label'] }}.
                    @if($block['manual'] && ! $block['manual_ignored'])
                        Bảng trên đang có số nhập tay cho tiêu chí này — điểm chính thức vẫn theo nhập tay đến khi quy tắc nguồn được duyệt.
                    @elseif($block['manual_ignored'])
                        Số nhập tay cũ không dùng (nguồn tự động đã áp dụng), không cộng hai lần.
                    @endif
                    <details class="hk-cl-rules"><summary>Quy tắc đang xem</summary>
                        <ul>@foreach($block['rule_lines'] as $key => $line)<li>{{ $line }}</li>@endforeach</ul>
                    </details>
                </div>
                @php
                    // HC06 giữ ba nhóm riêng (cấp phát, kiểm kê, sự cố); HC02 tách chi phí / vị trí tuyển thành công; tiêu chí khác một danh sách.
                    $hkSections = ! empty($block['summary']['groups'])
                        ? collect($block['summary']['groups'] ?? [])->map(fn ($g) => ['group' => $g, 'rows' => array_values(array_filter($block['rows'], fn ($r) => ($r['group'] ?? '') === $g['key']))])->all()
                        : [['group' => null, 'rows' => $block['rows']]];
                    $hkReview = \App\Services\Hr\Kpi\HcnsSourceReviews::STATUSES;
                @endphp
                @if($block['rows'] === [])
                    <div class="hk-cl-empty"><strong>Chưa có hồ sơ nguồn trong kỳ.</strong> Không phát sinh chưa được xác nhận nên không tự thành 100%.</div>
                @else
                    @foreach($hkSections as $hkSection)
                    @if($hkSection['group'])
                        <h4 class="hk-cl-group" data-hk-group="{{ $hkSection['group']['key'] }}">
                            {{ $hkSection['group']['label'] }}
                            <small>{{ ! empty($hkSection['group']['measure_text']) ? $hkSection['group']['measure_text'] : ($hkSection['group']['value'] !== null ? $hkSection['group']['numerator'].'/'.$hkSection['group']['denominator'].' đúng hạn = '.$hkNum($hkSection['group']['value']).'%' : 'chưa có số đo') }}
                                · {{ count($hkSection['rows']) }} hồ sơ</small>
                        </h4>
                        @if($hkSection['rows'] === [])
                            <div class="hk-cl-empty hk-cl-empty--group">Không có hồ sơ nhóm này trong kỳ.</div>
                        @endif
                    @endif
                    @if($hkSection['rows'] !== [])
                    <ol class="hk-cl-rows">
                        @foreach($hkSection['rows'] as $row)
                            <li class="hk-cl-row" data-hk-row="{{ $row['kpi_state'] }}">
                                <div class="hk-cl-src">
                                    <span class="hk-cl-label">Nguồn</span>
                                    @if($row['can_open'])
                                        <a href="{{ $row['url'] }}" target="_blank" rel="noopener">{{ $row['title'] }} <i class="bi bi-box-arrow-up-right" aria-hidden="true"></i></a>
                                    @else
                                        <span class="hk-cl-locked" title="Bạn không có quyền mở hồ sơ này">{{ $row['title'] }} <i class="bi bi-lock" aria-hidden="true"></i></span>
                                    @endif
                                    <small>
                                        {{ $row['procedure'] ?? $row['classification'] ?? '' }} · Người chịu: {{ $row['owner_name'] }}
                                        @if(! empty($row['reviewer_name'])) · Người kiểm: {{ $row['reviewer_name'] }}@endif
                                    </small>
                                </div>
                                <div class="hk-cl-work">
                                    <span class="hk-cl-label">Thực hiện</span>
                                    <span class="hk-tag hk-tag--soft hk-tag--muted">{{ $row['work_label'] }}</span>
                                    <small>
                                        @if($block['code'] === 'admin_deadline')
                                            Hạn đo {{ $hkFmt($row['deadline']) }}
                                            @if($row['due_changed'])
                                                @if($row['deadline'] && $row['original_due_at'] && \Illuminate\Support\Carbon::parse($row['deadline'])->equalTo(\Illuminate\Support\Carbon::parse($row['original_due_at'])))
                                                    (hạn hiện tại {{ $hkFmt($row['due_at'] ?? null) }} không dùng để đo theo quy tắc đổi hạn)
                                                @else
                                                    (gốc {{ $hkFmt($row['original_due_at']) }}, đã gia hạn)
                                                @endif
                                            @endif
                                            · gửi đầu {{ $hkFmt($row['first_submitted_at']) }}@if($row['submission_count'] > 1) · {{ $row['submission_count'] }} lần gửi @endif
                                            @if($row['approved_by_name']) · xác nhận {{ $row['approved_by_name'] }} {{ $hkFmt($row['approved_at']) }}@endif
                                        @elseif($block['code'] === 'assets')
                                            Hạn {{ $hkFmt($row['deadline'] ?? null) }} · thực tế {{ $hkFmt($row['actual_at'] ?? null) }}
                                            @if(isset($row['review_status'])) · {{ $hkReview[$row['review_status']] ?? 'Chưa gửi kiểm' }}@endif
                                            @if(! empty($row['confirmed_by_name'])) · {{ ($row['group'] ?? '') === 'incident' ? 'nghiệm thu' : 'kiểm' }} {{ $row['confirmed_by_name'] }} {{ $hkFmt($row['confirmed_at'] ?? null) }}@endif
                                        @elseif($block['code'] === 'payroll_accuracy')
                                            Kỳ lương {{ substr($row['payroll_month'], 5, 2) }}/{{ substr($row['payroll_month'], 0, 4) }} · {{ $row['audit_code'] }}
                                            @if(! empty($row['components_text'])) · {{ $row['components_text'] }}@endif
                                            @if(! empty($row['checked_at'])) · kiểm dòng {{ $hkFmt($row['checked_at']) }}@endif
                                            @if(! empty($row['checker_name'])) · người nhập {{ $row['checker_name'] }}@endif
                                            · {{ $hkReview[$row['review_status'] ?? ''] ?? 'Chưa gửi kiểm' }}
                                            @if(! empty($row['confirmed_by_name'])) · xác nhận {{ $row['confirmed_by_name'] }} {{ $hkFmt($row['confirmed_at'] ?? null) }}@endif
                                        @elseif(in_array($block['code'], ['recruit_time', 'recruit_cost', 'probation_quit'], true))
                                            @if(($row['group'] ?? null) === 'cost')
                                                Thực chi {{ $hkFmt($row['actual_at'] ?? null) }} · phân bổ {{ $hkMoney($row['amount'] ?? 0) }}
                                                @if(! empty($row['doc_can_open'])) · <a href="{{ url('/payment-requests/'.$row['doc_id']) }}" target="_blank" rel="noopener" data-hk-doc-link>mở chứng từ</a>@else · chứng từ: không có quyền xem @endif
                                            @elseif($block['code'] === 'probation_quit')
                                                Thử việc {{ $hkDay($row['started_at'] ?? null) }} → {{ $hkDay($row['deadline'] ?? null) }}@if(! empty($row['actual_at'])) · nghỉ {{ $hkDay($row['actual_at']) }}@endif
                                            @else
                                                Tiếp nhận {{ $hkFmt($row['started_at'] ?? null) }} → nhận việc thực tế {{ $hkDay($row['actual_at'] ?? null) }}@if(isset($row['days']) && $row['days'] !== null) · {{ $row['days'] }} ngày @endif
                                            @endif
                                            · {{ $hkReview[$row['review_status'] ?? ''] ?? 'Chưa gửi kiểm' }}
                                            @if(! empty($row['confirmed_by_name'])) · kiểm {{ $row['confirmed_by_name'] }} {{ $hkFmt($row['confirmed_at'] ?? null) }}@endif
                                        @else
                                            {{ $row['review_label'] }} · gửi đầu {{ $hkFmt($row['first_submitted_at']) }}
                                            @if($row['confirmed_by_name']) · kiểm {{ $row['confirmed_by_name'] }} {{ $hkFmt($row['confirmed_at']) }}@endif
                                        @endif
                                    </small>
                                </div>
                                <div class="hk-cl-kpi">
                                    <span class="hk-cl-label">KPI</span>
                                    <span class="hk-tag hk-tag--{{ $hkStates[$row['kpi_state']][1] }}">{{ $row['state_label'] ?? $hkRowState[$row['kpi_state']] }}</span>
                                    <small>{{ $row['reason'] }}</small>
                                </div>
                            </li>
                        @endforeach
                    </ol>
                    @endif
                    @endforeach
                @endif
            @endif
        </article>
    @endforeach
</section>

@once
<style>
    .hk-cl{margin-top:14px;display:grid;gap:10px}
    .hk-cl-intro h3{margin:0 0 4px;font-size:15px;font-weight:800;color:#17314e}
    .hk-cl-intro p{margin:0;font-size:12.5px;color:#64748b}
    .hk-cl-block{border:1px solid #e3e9f1;border-radius:10px;background:#fff;overflow:hidden}
    .hk-cl-block.is-manual{background:#fbfcfe}
    .hk-cl-head{display:flex;flex-wrap:wrap;justify-content:space-between;align-items:center;gap:8px;padding:9px 13px;background:#f8fafc;border-bottom:1px solid #edf1f6}
    .hk-cl-title strong{display:block;font-size:13.5px;color:#17314e}
    .hk-cl-title span{font-size:12px;color:#64748b}
    .hk-cl-sum{display:flex;flex-wrap:wrap;gap:5px}
    .hk-tag{display:inline-flex;align-items:center;border-radius:999px;padding:2px 9px;font-size:11.5px;font-weight:600;line-height:1.5;border:1px solid transparent;white-space:nowrap}
    .hk-tag--primary{background:#e8f0ff;color:#1d4ed8;border-color:#c7d7fb}
    .hk-tag--success{background:#e8f7ee;color:#15803d;border-color:#c3e8d1}
    .hk-tag--danger{background:#fdecec;color:#b91c1c;border-color:#f5c9c9}
    .hk-tag--warning{background:#fff6e5;color:#9a5b00;border-color:#f3dcae}
    .hk-tag--info{background:#e9f6fb;color:#0e6a8a;border-color:#c6e6f2}
    .hk-tag--muted{background:#f1f4f8;color:#556274;border-color:#e1e7ef}
    .hk-tag--soft{font-weight:500}
    .hk-cl-layers{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:0;margin:0;border-bottom:1px solid #edf1f6}
    .hk-cl-layers>div{padding:7px 13px;border-right:1px solid #edf1f6;min-width:0}
    .hk-cl-layers>div:last-child{border-right:0}
    .hk-cl-layers dt{font-size:10.5px;text-transform:uppercase;letter-spacing:.03em;color:#8a97a8;font-weight:700}
    .hk-cl-layers dd{margin:2px 0 0;font-size:12.5px;color:#17314e;font-weight:600}
    .hk-cl-layers dd small{font-weight:400;color:#64748b}
    .hk-cl-group-measure{display:block;font-weight:600}
    .hk-cl-block.is-manual .hk-cl-layers{grid-template-columns:1fr}
    .hk-cl-note,.hk-cl-empty{padding:8px 13px;font-size:12.5px;color:#475569}
    .hk-cl-empty{color:#7a4a00;background:#fffaf0}
    .hk-cl-empty--group{color:#64748b;background:#f8fafc}
    .hk-cl-group{margin:0;padding:8px 13px 4px;font-size:12.5px;font-weight:800;color:#17314e;border-top:1px solid #edf1f6;background:#fbfdff}
    .hk-cl-group small{font-weight:500;color:#64748b;margin-left:6px}
    .hk-cl-rules{margin-top:4px}
    .hk-cl-rules summary{cursor:pointer;color:#1d4ed8}
    .hk-cl-rules ul{margin:4px 0 0 18px;padding:0}
    .hk-cl-rows{list-style:none;margin:0;padding:0}
    .hk-cl-row{display:grid;grid-template-columns:minmax(220px,1.5fr) minmax(190px,1.1fr) minmax(220px,1.4fr);gap:6px 14px;padding:9px 13px;border-top:1px dashed #edf1f6;align-items:start}
    .hk-cl-row>div{min-width:0}
    .hk-cl-row small{display:block;color:#64748b;font-size:11.5px;margin-top:2px}
    .hk-cl-row .hk-tag{white-space:normal;border-radius:10px}
    .hk-cl-src a{font-weight:600;word-break:break-word}
    .hk-cl-locked{color:#475569;font-weight:600}
    .hk-cl-label{display:none;font-size:10.5px;text-transform:uppercase;letter-spacing:.03em;color:#8a97a8;margin-right:6px}
    @media (max-width: 768px){
        .hk-cl-row{grid-template-columns:1fr;gap:4px}
        .hk-cl-label{display:inline}
        .hk-cl-head{align-items:flex-start}
        .hk-cl-layers{grid-template-columns:1fr}
        .hk-cl-layers>div{border-right:0;border-bottom:1px dashed #edf1f6}
    }
</style>
@endonce
