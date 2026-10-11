{{--
    Quy tắc đo nguồn KPI HCNS (HC09, HC07) theo phiên bản: bản nháp chỉ xem trước; bản đã duyệt áp dụng từ một kỳ.
    Người lập nháp: Ban Giám đốc/Admin. Người duyệt: Ban Giám đốc/Admin khác người lập.
--}}
@php
    $hrNum = static fn ($n) => rtrim(rtrim(number_format((float) $n, 2, ',', '.'), '0'), ',');
    $hrMonth = static fn ($m) => $m ? substr((string) $m, 5, 2).'/'.substr((string) $m, 0, 4) : '—';
    $hrStatus = ['draft' => 'Bản nháp', 'applied' => 'Đã duyệt áp dụng', 'archived' => 'Lưu trữ'];
    $hrLabels = ['admin_deadline' => 'HC09 · Thủ tục hành chính đúng hạn', 'documents' => 'HC07 · Phân loại/luân chuyển công văn',
        'assets' => 'HC06 · Cấp phát VPP, kiểm kê, sự cố (H-D04)', 'recruit_time' => 'HC01 · Thời gian tuyển dụng (H-D02/H-D08)',
        'recruit_cost' => 'HC02 · Chi phí tuyển dụng (H-D02/H-D08)', 'probation_quit' => 'HC03 · Nghỉ trong thử việc (H-D02/H-D08)',
        'payroll_accuracy' => 'HC04 · Độ chính xác tính lương (H-D05)'];
    $hrRuleNames = ['period_by' => 'Kỳ tính', 'measure_point' => 'Mốc so với hạn', 'deadline_basis' => 'Hạn dùng để so', 'date_only_due' => 'Hạn chỉ có ngày', 'cancelled' => 'Việc đã hủy', 'pass_rule' => 'Điều kiện đạt',
        'aggregation' => 'Gộp ba nhóm', 'incident_measure' => 'Mốc đo sự cố', 'incident_wait' => 'Thời gian chờ duyệt chi',
        'adoption' => 'Điểm chính thức', 'cohort' => 'Cohort', 'left_types' => 'Trường hợp nghỉ được tính',
        'unit' => 'Đơn vị kiểm', 'coverage' => 'Độ phủ', 'fixed_errors' => 'Lỗi đã sửa'];
@endphp
<section class="at-panel pr-section hk-rules" id="hcns-source-rules" data-hk-rules>
    <div class="at-card-head">
        <div>
            <h2><i class="bi bi-diagram-3"></i>Quy tắc nguồn KPI HCNS (tự động từ hồ sơ)</h2>
            <p>
                Kỳ {{ $hrMonth($month) }} đang dùng: <strong>{{ $ruleCurrent['status'] === 'applied' ? $ruleCurrent['label'] : 'chưa có phiên bản được duyệt — điểm chính thức vẫn nhập tay' }}</strong>.
                Bản nháp chỉ để xem trước tác động; khi được duyệt, tiêu chí có nguồn lấy điểm từ hồ sơ và bỏ qua số nhập tay (không cộng hai lần).
                Trọng số, ngày hiệu lực và cách quy đổi (H-D01…H-D03) vẫn cần người có quyền chốt.
            </p>
        </div>
    </div>

    @if($ruleVersions->isNotEmpty())
        <div class="at-table-wrap">
            <table class="at-table pr-table">
                <thead><tr><th>Phiên bản</th><th>Trạng thái</th><th>Áp dụng từ</th><th>Người lập</th><th>Người duyệt</th><th>Ghi chú</th></tr></thead>
                <tbody>
                    @foreach($ruleVersions as $v)
                        <tr data-hk-version="{{ $v->status }}">
                            <td>v{{ $v->version }}</td>
                            <td>{{ $hrStatus[$v->status] ?? $v->status }}</td>
                            <td>{{ $hrMonth($v->effective_month) }}</td>
                            <td>{{ $v->creator_name ?? '—' }}</td>
                            <td>{{ $v->approver_name ? $v->approver_name.' · '.\Illuminate\Support\Carbon::parse($v->approved_at)->format('H:i d/m/Y') : '—' }}</td>
                            <td style="white-space:normal;max-width:320px">{{ $v->note }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    <form method="POST" action="{{ route('hr.kpi.rules.draft') }}" class="hk-rules-form">
        @csrf
        <input type="hidden" name="month" value="{{ $month }}">
        <fieldset @disabled(! $canEditRules) style="border:0;padding:0;margin:0">
            <div class="hk-rules-grid">
                @foreach($ruleOptions as $criterion => $definitions)
                    <div class="hk-rules-col">
                        <h3>{{ $hrLabels[$criterion] ?? $criterion }}</h3>
                        @foreach($definitions as $key => $definition)
                            <label>{{ $hrRuleNames[$key] ?? $key }}
                                <select class="pr-input" name="rules[{{ $criterion }}][{{ $key }}]">
                                    @foreach($definition['options'] as $value => $label)
                                        <option value="{{ $value }}" @selected(($ruleValues[$criterion][$key] ?? '') === $value)>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </label>
                        @endforeach
                    </div>
                @endforeach
            </div>
            <label class="hk-rules-note">Ghi chú bản nháp
                <input class="pr-input" type="text" name="note" maxlength="2000" value="{{ $ruleDraft ? (\Illuminate\Support\Facades\DB::table('hr_kpi_rule_versions')->where('id', $ruleDraft['id'])->value('note')) : '' }}" placeholder="VD: đề xuất theo cuộc họp ngày…">
            </label>
            @if($canEditRules)
                <button class="at-btn at-btn--light" type="submit"><i class="bi bi-save"></i>{{ $ruleDraft ? 'Cập nhật bản nháp v'.$ruleDraft['version'] : 'Lưu thành bản nháp' }}</button>
            @else
                <p class="pr-hint">Chỉ Ban Giám đốc hoặc Admin được sửa quy tắc. Bạn đang xem chỉ đọc.</p>
            @endif
        </fieldset>
    </form>

    @if($ruleDraft && $canApproveRules)
        <form method="POST" action="{{ route('hr.kpi.rules.approve', $ruleDraft['id']) }}" class="hk-rules-approve" onsubmit="return confirm('Duyệt áp dụng quy tắc nguồn này? Tiêu chí có nguồn sẽ lấy điểm từ hồ sơ từ kỳ đã chọn.')">
            @csrf
            <label>Áp dụng từ kỳ <input class="pr-input" type="month" name="effective_month" required value="{{ $month }}"></label>
            <button class="at-btn at-btn--primary" type="submit"><i class="bi bi-patch-check"></i>Duyệt áp dụng bản nháp v{{ $ruleDraft['version'] }}</button>
            <small class="pr-hint">Người lập bản nháp không tự duyệt. Không áp vào kỳ đã chốt lương.</small>
        </form>
    @elseif($ruleDraft && ! empty($ruleDraftOwn))
        <p class="pr-hint" data-hk-own-draft>Bạn lập bản nháp v{{ $ruleDraft['version'] }} — cần một người khác trong Ban Giám đốc/Admin duyệt áp dụng.</p>
    @endif

    <div class="at-table-wrap" style="margin-top:10px">
        <table class="at-table pr-table" data-hk-preview-table>
            <thead><tr><th>Nhân viên (bộ HCNS kỳ {{ $hrMonth($month) }})</th><th>Tổng KPI chính thức hiện tại</th><th>Tổng nếu duyệt bản nháp</th><th>HC09 — số đo / xem trước</th><th>HC07 — số đo / xem trước</th><th>HC06 — số đo từng nhóm</th><th>HC01–HC04 — số đo tuyển dụng, đối soát lương</th></tr></thead>
            <tbody>
                @forelse($rulePreview as $p)
                    @php $blocks = collect($p['checklist']['blocks'])->keyBy('code'); @endphp
                    <tr>
                        <td>{{ $p['name'] }}</td>
                        @foreach(['official', 'preview'] as $hrKey)
                            <td style="white-space:normal" data-hk-total="{{ $hrKey }}">
                                @if($p[$hrKey]['percent'] !== null)
                                    <strong>{{ $hrNum($p[$hrKey]['percent']) }}%</strong> → hệ số {{ $hrNum($p[$hrKey]['rate'] * 100) }}%
                                    <div class="pr-muted">{{ $p[$hrKey]['label'] }}</div>
                                @else
                                    Chưa đủ<div class="pr-muted">{{ $p[$hrKey]['label'] }}</div>
                                @endif
                            </td>
                        @endforeach
                        @foreach(['admin_deadline', 'documents'] as $code)
                            @php $blk = $blocks->get($code); @endphp
                            <td style="white-space:normal">
                                @if($blk && $blk['auto'])
                                    {{ $blk['summary']['value'] !== null ? $blk['summary']['numerator'].'/'.$blk['summary']['denominator'].' = '.$hrNum($blk['summary']['value']).'%' : 'Chưa có số đo' }}
                                    @if($blk['preview_achievement'] !== null) → {{ $hrNum($blk['preview_achievement']) }}% đạt @endif
                                    <div class="pr-muted">{{ ['no_items' => 'không có hồ sơ', 'incomplete' => 'còn chờ xác nhận', 'open' => 'kỳ chưa kết thúc', 'complete' => 'đủ dữ liệu'][$blk['summary']['data_status']] ?? '' }}</div>
                                @else
                                    —
                                @endif
                            </td>
                        @endforeach
                        @php $blk = $blocks->get('assets'); @endphp
                        <td style="white-space:normal" data-hk-preview-assets>
                            @if($blk && $blk['auto'])
                                @foreach($blk['summary']['groups'] ?? [] as $g)
                                    <div>{{ $g['label'] }}: {{ $g['value'] !== null ? $g['numerator'].'/'.$g['denominator'].' = '.$hrNum($g['value']).'%' : 'chưa có số đo' }}</div>
                                @endforeach
                                <div class="pr-muted">{{ $blk['summary']['value'] !== null ? 'Gộp: '.$hrNum($blk['summary']['value']).'%' : ($blk['summary']['combined_reason'] ?? '') }}</div>
                            @else
                                —
                            @endif
                        </td>
                        <td style="white-space:normal" data-hk-preview-recruit>
                            @foreach(['recruit_time' => 'HC01', 'recruit_cost' => 'HC02', 'probation_quit' => 'HC03', 'payroll_accuracy' => 'HC04'] as $rc => $rl)
                                @php $rb = $blocks->get($rc); @endphp
                                <div>{{ $rl }}: {{ $rb && $rb['auto'] ? ($rb['summary']['measure_text'] ?? '—') : '—' }}@if($rb && $rb['auto'] && $rb['preview_achievement'] !== null) → {{ $hrNum($rb['preview_achievement']) }}% đạt @endif</div>
                            @endforeach
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7">Chưa có nhân viên gán bộ KPI HCNS trong Hồ sơ lương kỳ này.</td></tr>
                @endforelse
            </tbody>
        </table>
        <p class="pr-hint">Xem trước theo {{ $ruleDraft ? 'bản nháp v'.$ruleDraft['version'] : 'mặc định nháp (chưa lưu)' }} — chưa áp dụng, không đổi điểm hay lương.</p>
    </div>
</section>

@once
<style>
    .hk-rules-form{display:grid;gap:10px;margin-top:10px}
    .hk-rules-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px}
    .hk-rules-col{border:1px solid #e3e9f1;border-radius:10px;padding:10px 12px;display:grid;gap:8px;align-content:start;background:#fbfdff}
    .hk-rules-col h3{margin:0;font-size:13px;font-weight:800;color:#17314e}
    .hk-rules-col label,.hk-rules-note,.hk-rules-approve label{display:grid;gap:4px;font-size:11px;font-weight:700;color:#5b7284}
    .hk-rules-approve{display:flex;flex-wrap:wrap;gap:10px;align-items:end;margin-top:10px;padding:10px 12px;border:1px dashed #c7d7fb;border-radius:10px;background:#f6f9ff}
    @media (max-width: 768px){.hk-rules-grid{grid-template-columns:1fr}}
</style>
@endonce
