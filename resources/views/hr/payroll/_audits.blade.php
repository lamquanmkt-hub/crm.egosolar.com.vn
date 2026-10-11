{{--
    KPI HC04 — đối soát độ chính xác bảng lương ngay trong trang Bảng lương (không thêm menu).
    Biến: $period, $month, $audits, $auditUsers, $allLines, $departments.
--}}
@php
    $paStatus = \App\Services\Hr\Kpi\HcnsSourceReviews::STATUSES;
    $paMonth = \Illuminate\Support\Carbon::createFromFormat('Y-m', $month);
    $paRule = app(\App\Services\Hr\Kpi\HcnsKpiRules::class)->forMonth($paMonth->copy()->addMonthNoOverflow()->format('Y-m'));
    $paKpiMonth = \Illuminate\Support\Carbon::createFromFormat('Y-m', \App\Services\Hr\Kpi\PayrollAudits::kpiMonth($month, $paRule['rules']))->format('m/Y');
@endphp
<section class="at-panel pr-section pa-box" id="doi-soat" data-pa-audits>
    <div class="at-card-head">
        <div>
            <h2><i class="bi bi-clipboard-check"></i>Đối soát độ chính xác bảng lương (KPI HC04)</h2>
            <p>Ghi từng dòng đã kiểm: <b>đúng</b> / <b>có lỗi</b> (loại lỗi, nội dung, nguồn, chứng cứ, cách xử lý) / <b>chưa xác định</b>. Không đổi số tiền của bảng lương.</p>
        </div>
    </div>
    <p class="pr-hint" data-pa-how>
        <b>Ai nhập:</b> người nhập của đợt (HR/Kế toán) ghi kết quả rồi gửi kiểm. <b>Ai kiểm:</b> người kiểm độc lập — khác HCNS chịu KPI và khác người nhập — xác nhận hoặc trả bổ sung.
        <b>Khi nào tính:</b> chỉ phần thuộc đợt đã được xác nhận. Theo {{ $paRule['status'] === 'applied' ? $paRule['label'] : 'quy tắc nháp' }}, bảng lương kỳ {{ $paMonth->format('m/Y') }} được đo cho KPI tháng {{ $paKpiMonth }};
        @if(($paRule['rules']['payroll_accuracy']['adoption'] ?? 'preview_only') !== 'source' || $paRule['status'] !== 'applied')
            đơn vị, độ phủ và lỗi đã sửa còn chờ chốt (H-D05) nên mới là <b>số đo/xem trước</b>.
        @endif Chưa có đợt đối soát ≠ chính xác 100%; số lần sửa bảng lương không phải số lỗi.
    </p>

    @if($audits->isEmpty())
        <div class="pa-empty" data-pa-empty>Kỳ {{ $paMonth->format('m/Y') }} chưa có đợt đối soát — HC04 của HCNS phụ trách đang “chưa kiểm”.</div>
    @else
        <div class="pa-list">
            @foreach($audits as $a)
                <article class="pa-card" data-pa-audit="{{ $a->id }}">
                    <div class="pa-card-head">
                        <a href="{{ route('hr.payroll.audits.show', $a->id) }}"><b>{{ $a->code }}</b> · {{ $a->title }}</a>
                        <span class="pa-pill {{ $a->status === 'cancelled' ? 'is-muted' : (($a->review_status ?? '') === 'confirmed' ? (($a->review_result ?? '') === 'pass' ? 'is-ok' : 'is-bad') : 'is-wait') }}">
                            {{ $a->status === 'cancelled' ? 'Đã hủy' : ($paStatus[$a->review_status ?? ''] ?? 'Chưa lưu/gửi') }}
                        </span>
                    </div>
                    <div class="pa-card-meta">
                        {{ \App\Services\Hr\Kpi\PayrollAudits::SCOPES[$a->scope] ?? $a->scope }}@if($a->scope_department) “{{ $a->scope_department }}”@endif ·
                        đã kết luận {{ $a->checked }}/{{ $a->scope_count }} dòng · {{ $a->with_errors }} dòng có lỗi
                        @if($a->undetermined) · {{ $a->undetermined }} chưa xác định @endif
                    </div>
                    <div class="pa-card-meta">HCNS chịu KPI: <b>{{ $a->owner_name ?? '—' }}</b> · Người nhập: {{ $a->checker_name ?? '—' }} · Người kiểm: {{ $a->reviewer_name ?? '—' }}</div>
                </article>
            @endforeach
        </div>
    @endif

    <details class="pa-create" data-pa-create @if($errors->hasAny(['title', 'scope', 'scope_department', 'line_ids', 'owner_id', 'checker_id', 'reviewer_id'])) open @endif>
        <summary><i class="bi bi-plus-circle"></i> Lập đợt đối soát kỳ {{ $paMonth->format('m/Y') }}</summary>
        <form method="POST" action="{{ route('hr.payroll.audits.store') }}" class="pa-form">
            @csrf
            <input type="hidden" name="period_id" value="{{ $period->id }}">
            <div class="pr-grid pa-grid">
                <div class="pr-field" style="grid-column:span 2"><label>Tên đợt *</label><input class="pr-input" name="title" required maxlength="255" value="{{ old('title', 'Đối soát lương '.$paMonth->format('m/Y')) }}"></div>
                <div class="pr-field"><label>Phạm vi *</label>
                    <select class="pr-input" name="scope" data-pa-scope>
                        @foreach(\App\Services\Hr\Kpi\PayrollAudits::SCOPES as $k => $label)<option value="{{ $k }}" @selected(old('scope', 'all') === $k)>{{ $label }}</option>@endforeach
                    </select>
                </div>
                <div class="pr-field" data-pa-scope-dept><label>Phòng ban (khi chọn phòng ban)</label>
                    <select class="pr-input" name="scope_department"><option value="">—</option>@foreach($departments as $d)<option value="{{ $d }}" @selected(old('scope_department') === $d)>{{ $d }}</option>@endforeach</select>
                </div>
                <div class="pr-field"><label>HCNS chịu trách nhiệm (chịu KPI) *</label>
                    <select class="pr-input" name="owner_id" required><option value="">— Chọn —</option>@foreach($auditUsers as $u)<option value="{{ $u->id }}" @selected((int) old('owner_id') === (int) $u->id)>{{ $u->name }}</option>@endforeach</select>
                </div>
                <div class="pr-field"><label>Người nhập kết quả</label>
                    <select class="pr-input" name="checker_id">@foreach($auditUsers as $u)<option value="{{ $u->id }}" @selected((int) old('checker_id', auth()->id()) === (int) $u->id)>{{ $u->name }}</option>@endforeach</select>
                </div>
                <div class="pr-field"><label>Người kiểm độc lập *</label>
                    <select class="pr-input" name="reviewer_id" required><option value="">— Chọn —</option>@foreach($auditUsers as $u)<option value="{{ $u->id }}" @selected((int) old('reviewer_id') === (int) $u->id)>{{ $u->name }}</option>@endforeach</select>
                </div>
                <div class="pr-field"><label>Ghi chú</label><input class="pr-input" name="note" maxlength="2000" value="{{ old('note') }}"></div>
            </div>
            <details class="pa-lines" data-pa-scope-lines>
                <summary>Chọn từng dòng (khi phạm vi “Chọn từng dòng”) — {{ $allLines->count() }} dòng</summary>
                <div class="pa-line-picks">
                    @foreach($allLines as $l)
                        <label><input type="checkbox" name="line_ids[]" value="{{ $l->id }}" @checked(in_array($l->id, (array) old('line_ids', [])))> {{ $l->employee_name }} <small>{{ \App\Http\Controllers\Hr\PayrollController::departmentOf($l) }}</small></label>
                    @endforeach
                </div>
            </details>
            <button class="at-btn at-btn--primary" type="submit"><i class="bi bi-check2"></i>Lập đợt đối soát</button>
            <small class="pr-muted">Người kiểm phải khác HCNS chịu KPI và khác người nhập. Lập xong mở trang đợt để ghi kết quả từng dòng.</small>
        </form>
    </details>
</section>

@once
<style>
    #egoAttendancePromax .pa-box .pa-empty{padding:10px 12px;border:1px dashed #f3dcae;border-radius:10px;background:#fffaf0;color:#7a4a00;font-size:12px;font-weight:700}
    #egoAttendancePromax .pa-list{display:grid;grid-template-columns:repeat(auto-fill,minmax(300px,1fr));gap:10px}
    #egoAttendancePromax .pa-card{border:1px solid #dbe5f0;border-radius:12px;padding:10px 12px;background:#fff;display:grid;gap:4px;min-width:0}
    #egoAttendancePromax .pa-card-head{display:flex;justify-content:space-between;gap:8px;align-items:flex-start;flex-wrap:wrap}
    #egoAttendancePromax .pa-card-head a{font-size:12.5px;color:#0f3b78;text-decoration:none;word-break:break-word}
    #egoAttendancePromax .pa-card-meta{font-size:11px;color:#5b7284}
    #egoAttendancePromax .pa-pill{border-radius:999px;padding:2px 9px;font-size:10.5px;font-weight:800;white-space:nowrap}
    #egoAttendancePromax .pa-pill.is-ok{background:#e8f7ee;color:#15803d}#egoAttendancePromax .pa-pill.is-bad{background:#fdecec;color:#b91c1c}
    #egoAttendancePromax .pa-pill.is-wait{background:#e9f6fb;color:#0e6a8a}#egoAttendancePromax .pa-pill.is-muted{background:#f1f4f8;color:#556274}
    #egoAttendancePromax .pa-create{margin-top:12px}
    #egoAttendancePromax .pa-create summary{cursor:pointer;color:#087e92;font-weight:800;font-size:12px}
    #egoAttendancePromax .pa-form{display:grid;gap:10px;margin-top:10px}
    #egoAttendancePromax .pa-lines summary{cursor:pointer;font-size:11px;font-weight:800;color:#35556b}
    #egoAttendancePromax .pa-line-picks{display:grid;grid-template-columns:repeat(auto-fill,minmax(220px,1fr));gap:4px 12px;max-height:240px;overflow:auto;margin-top:6px;font-size:11.5px}
    #egoAttendancePromax .pa-line-picks small{color:#7a8f9c}
    @media(max-width:640px){#egoAttendancePromax .pa-grid{grid-template-columns:1fr}#egoAttendancePromax .pa-grid .pr-field{grid-column:auto!important}#egoAttendancePromax .pa-list{grid-template-columns:1fr}}
</style>
@endonce
