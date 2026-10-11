@php
    $num = static fn ($n) => rtrim(rtrim(number_format((float) $n, 2, ',', '.'), '0'), ',');
    $monthLabel = \Illuminate\Support\Carbon::createFromFormat('Y-m', $month)->format('m/Y');
    $weightTotal = $criteria->sum(fn ($c) => (float) $c->weight);
    $hasMissingWeight = $criteria->contains(fn ($c) => $c->weight === null);
@endphp
@if($hasMissingWeight)
    <div class="at-alert at-alert--danger" style="margin-bottom:12px">Bộ KPI còn tiêu chí chưa có trọng số. <a href="{{ route('hr.kpi.index', ['template' => $template->code, 'tab' => 'settings', 'month' => $month]) }}">Bổ sung trọng số</a> trước khi tính điểm.</div>
@endif
@if($template->code === 'hcns' && \App\Services\Hr\Kpi\HcnsKpiRules::ready())
    <div class="at-alert" style="margin-bottom:12px" data-hk-version-banner><i class="bi bi-layers"></i>
        Bộ KPI dùng cho kỳ {{ $monthLabel }}: <strong>{{ $kpiVersion ? $kpiVersion['label'] : 'Cài đặt gốc (chưa có phiên bản được duyệt)' }}</strong>.
        @if(! $kpiVersion) Trọng số, chỉ tiêu, bậc và quy tắc nguồn mới chỉ là bản nháp — xem ở tab Cài đặt tiêu chí. @endif
    </div>
@endif
@if($rows->isEmpty())
            @include('hr.kpi._empty')
        @else
            <div class="pk-score-summary">
                <div><strong>{{ config('hr_payroll_departments.'.$template->code.'.label', $template->name) }}</strong><span> · Tháng {{ $monthLabel }}</span></div>
                <div class="pk-counts"><span>{{ $rows->count() }} nhân viên</span><span>{{ $rows->filter(fn ($r) => $r['summary']['percent'] !== null)->count() }} đủ dữ liệu</span><span>{{ $rows->filter(fn ($r) => $r['summary']['percent'] === null)->count() }} cần bổ sung</span></div>
            </div>
            <form method="POST" action="{{ route('hr.kpi.store') }}">
                @csrf
                <input type="hidden" name="month" value="{{ $month }}">
                <input type="hidden" name="template" value="{{ $template->code }}">

                @foreach($rows as $row)
                    @php
                        $employee = $row['employee'];
                        $summary = $row['summary'];
                        $isSelf = (int) $employee->id === (int) auth()->id();
                        $locked = ! $canEvaluate || $isSelf;
                    @endphp
                    <section class="at-panel pr-section">
                        <div class="at-card-head">
                            <div>
                                <h2><i class="bi bi-person"></i>{{ $employee->name }}</h2>
                                <p>
                                    @if($summary['percent'] !== null)
                                        KPI <strong>{{ $num($summary['percent']) }}%</strong> → hưởng <strong>{{ $num($summary['rate'] * 100) }}%</strong> lương KPI · {{ $summary['label'] }}
                                    @else
                                        {{ $summary['label'] }}@if($row['result']['missing']): {{ implode(', ', $row['result']['missing']) }}@endif
                                    @endif
                                    @if($isSelf) · <em>Bạn không thể tự chấm KPI của mình.</em>@endif
                                    @if(! empty($row['snapshot'])) <br><span class="pr-warn" data-hk-snapshot><i class="bi bi-camera"></i> Kỳ đã chốt — hiển thị ảnh chụp lúc duyệt lương {{ \Illuminate\Support\Carbon::parse($row['snapshot']['created_at'])->format('H:i d/m/Y') }} ({{ $row['snapshot']['version_label'] }}).</span>@endif
                                </p>
                            </div>
                            <span class="at-status-pill at-status-pill--{{ $summary['percent'] !== null ? 'success' : 'warning' }}">{{ $summary['percent'] !== null ? 'Đã đủ' : 'Chưa đủ' }}</span>
                        </div>
                        <div class="at-table-wrap">
                            <table class="at-table pr-table" style="min-width:1100px">
                                <thead>
                                    <tr>
                                        <th>Tiêu chí</th>
                                        <th>Chỉ tiêu</th>
                                        <th class="pr-num">Trọng số</th>
                                        <th>Thực tế</th>
                                        <th>% đạt</th>
                                        <th>Không phát sinh</th>
                                        <th>Ghi chú / minh chứng</th>
                                        <th class="pr-num">Điểm</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($row['result']['rows'] as $r)
                                        @php
                                            $c = $r['criterion'];
                                            $s = $r['score'];
                                            $base = "scores[{$employee->id}][{$c->id}]";
                                            $auto = $s && $s->achievement === null ? $r['achievement'] : null;
                                        @endphp
                                        <tr>
                                            <td style="max-width:320px;white-space:normal">
                                                @if($c->group_name)<div class="pr-muted">{{ $c->group_name }}</div>@endif
                                                <span class="pr-strong">{{ $c->name }}</span>
                                                @if($c->description)<div class="pr-muted">{{ $c->description }}</div>@endif
                                                @if(isset($autoData[$c->code]))<div class="pr-warn"><i class="bi bi-cpu"></i> Số liệu phần mềm: {{ $autoData[$c->code] }}</div>@endif
                                            </td>
                                            <td style="white-space:normal;max-width:180px">{{ $c->target_text }}<div class="pr-muted">{{ $calcTypes[$c->calc_type] ?? $c->calc_type }}</div></td>
                                            <td class="pr-num">{{ $c->weight !== null ? $num($c->weight) : '—' }}</td>
                                            @if(! empty($r['source']))
                                                {{-- Nguồn tự động theo quy tắc đã duyệt: không nhập tay Thực tế/% đạt (tránh đếm hai lần). --}}
                                                <td data-hk-source-cell>
                                                    <span class="pr-strong">{{ $r['source']['actual'] !== null ? $num($r['source']['actual']).'%' : '—' }}</span>
                                                    <div class="pr-muted">Nguồn tự động · {{ $r['source']['rule'] }}</div>
                                                </td>
                                                <td>
                                                    {{ $r['achievement'] !== null ? $num($r['achievement']).' %' : 'Chưa đủ' }}
                                                    <div class="pr-muted">{{ $r['source']['reason'] }}</div>
                                                </td>
                                            @else
                                            <td>
                                                @if($c->calc_type !== 'manual')
                                                    <input class="pr-input pr-input--sm" type="number" step="any" min="0" name="{{ $base }}[actual]" value="{{ $s?->actual_value !== null ? (float) $s->actual_value : '' }}" @disabled($locked)>
                                                    <span class="pr-muted">{{ $c->unit }}</span>
                                                @else
                                                    <span class="pr-muted">—</span>
                                                @endif
                                            </td>
                                            <td>
                                                <input class="pr-input pr-input--xs" type="number" step="any" min="0" max="200" name="{{ $base }}[achievement]" value="{{ $s?->achievement !== null ? (float) $s->achievement : '' }}" placeholder="{{ $auto !== null ? $num($auto) : '' }}" @disabled($locked)> %
                                                @if($auto !== null)<div class="pr-muted">tự tính {{ $num($auto) }}%</div>@endif
                                            </td>
                                            @endif
                                            <td style="text-align:center"><input type="checkbox" name="{{ $base }}[na]" value="1" @checked($s && $s->not_applicable) @disabled($locked)></td>
                                            <td><input class="pr-input" type="text" maxlength="1000" name="{{ $base }}[note]" value="{{ $s->note ?? '' }}" @disabled($locked)></td>
                                            <td class="pr-num">{{ $r['points'] !== null ? $num($r['points']) : '—' }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        @if(! empty($row['checklist']))
                            @include('hr.kpi._hcns-checklist', ['checklist' => $row['checklist']])
                        @endif
                    </section>
                @endforeach

                @if($canEvaluate)
                    <div class="pr-section"><button class="at-btn at-btn--primary" type="submit"><i class="bi bi-check2"></i>Lưu KPI tháng {{ $monthLabel }}</button></div>
                @elseif($monthLocked)
                    <p class="pr-hint pr-section">Bảng lương tháng {{ $monthLabel }} đã duyệt nên KPI tháng này đã khoá. Giám đốc mở khoá bảng lương (ghi lý do) nếu cần sửa.</p>
                @else
                    <p class="pr-hint pr-section">Chỉ Ban Giám đốc hoặc Admin được chấm KPI. Bạn đang xem ở chế độ chỉ đọc.</p>
                @endif
            </form>
        @endif
