@extends('layouts.app')

@section('title', 'Đối soát lương '.$audit->code)

@section('content')
@php
    $fmt = fn ($t) => $t ? \Illuminate\Support\Carbon::parse($t)->format('H:i d/m/Y') : '—';
    $vnd = static fn ($n) => $n === null ? '—' : number_format((float) $n, 0, ',', '.');
    $num = static fn ($n) => rtrim(rtrim(number_format((float) $n, 2, ',', '.'), '0'), ',');
    $pm = substr($audit->payroll_month, 5, 2).'/'.substr($audit->payroll_month, 0, 4);
    $km = substr($kpiMonth, 5, 2).'/'.substr($kpiMonth, 0, 4);
    $cancelled = $audit->status === 'cancelled';
    $canWrite = $canEdit && ! $locked && ! $cancelled;
    $reviewStatus = $review->status ?? 'none';
    $activeErrors = $auditErrors->flatten(1)->whereNull('removed_at');
    $componentLabels = collect($components)->map(fn ($c) => $c['label'])->all();
    $stateTone = ['earned' => 'is-ok', 'lost' => 'is-bad', 'pending' => 'is-wait', 'missing' => 'is-warn', 'excluded' => 'is-muted'];
    $allLineRows = $scopeLines->map(fn ($l) => ['line' => $l, 'gone' => false])
        ->concat($goneItems->map(fn ($i) => ['line' => null, 'gone' => true, 'item' => $i]));
@endphp

<div id="egoAttendancePromax" class="pr-workspace">
    <div class="at-shell">
        @include('hr.payroll._breadcrumb', ['pageTitle' => 'Đối soát '.$audit->code])
        <header class="pr-page-head">
            <div class="at-heading">
                <div>
                    <h1>{{ $audit->code }} · {{ $audit->title }}</h1>
                    <p>Bảng lương kỳ {{ $pm }} ({{ $period && $period->status === 'approved' ? 'đã duyệt & khoá' : 'bản nháp' }}) · đo cho KPI HC04 tháng {{ $km }} · {{ \App\Services\Hr\Kpi\PayrollAudits::SCOPES[$audit->scope] ?? $audit->scope }}@if($audit->scope_department) “{{ $audit->scope_department }}”@endif</p>
                </div>
            </div>
            <div class="at-actions">
                <a class="at-btn at-btn--glass" href="{{ route('hr.payroll.index', ['month' => $audit->payroll_month]) }}#doi-soat"><i class="bi bi-arrow-left"></i>Bảng lương {{ $pm }}</a>
                <a class="at-btn at-btn--glass" href="{{ route('hr.kpi.index', ['template' => 'hcns', 'month' => $kpiMonth, 'employee' => $audit->owner_id]) }}#hk-payroll_accuracy"><i class="bi bi-list-check"></i>KPI HC04 của người chịu KPI</a>
            </div>
        </header>

        @include('hr.payroll._head')

        @if($cancelled)
            <div class="at-alert at-alert--danger" data-pa-cancelled><i class="bi bi-x-circle"></i>Đã hủy {{ $fmt($audit->cancelled_at) }} bởi {{ $names[(int) $audit->cancelled_by] ?? '—' }}: {{ $audit->cancel_reason }}</div>
        @endif

        @include('hr.kpi._source-review-panel', ['p' => [
            'id' => 'kpi-hc04',
            'title' => 'KPI HC04 · Độ chính xác tính lương — đợt '.$audit->code,
            'row' => $panelRow, 'review' => $review, 'events' => $events, 'files' => $files, 'missing' => $missing,
            'locked' => $locked, 'isOwner' => $canEdit && ! $cancelled, 'canEdit' => $canEdit && ! $cancelled, 'canReview' => $canReview && ! $cancelled,
            'checks' => $checks,
            'facts' => [
                ['HCNS chịu trách nhiệm (chịu KPI)', $names[(int) $audit->owner_id] ?? '—'],
                ['Người nhập kết quả', $names[(int) $audit->checker_id] ?? '—'],
                ['Người kiểm độc lập', $names[(int) $audit->reviewer_id] ?? '—'],
                ['Phạm vi', $scopeLines->count().' dòng lương'],
                ['Đã kết luận', $items->whereIn('result', ['ok', 'error'])->count().' dòng · '.$items->where('result', 'error')->count().' có lỗi · '.$items->where('result', 'undetermined')->count().' chưa xác định'],
                ['Lỗi đang ghi', $activeErrors->count().' lỗi ('.$auditErrors->flatten(1)->whereNotNull('removed_at')->count().' đã gỡ)'],
                ['Số đo của đợt', $summary['measure_text']],
                ['Quy tắc', ($rule['status'] === 'applied' ? $rule['label'] : 'Nháp — chưa duyệt (H-D05)').' · '.(($rule['rules']['payroll_accuracy']['unit'] ?? 'line') === 'component' ? 'đơn vị khoản' : 'đơn vị dòng')],
            ],
            'routes' => [
                'draft' => route('hr.payroll.audits.kpi.draft', $audit->id),
                'submit' => route('hr.payroll.audits.kpi.submit', $audit->id),
                'confirm' => route('hr.payroll.audits.kpi.confirm', $audit->id),
                'return' => route('hr.payroll.audits.kpi.return', $audit->id),
                'upload' => route('hr.payroll.audits.files.upload', $audit->id),
            ],
            'fileUrl' => fn ($f, $download) => route('hr.payroll.audits.files.show', [$audit->id, $f->id]).($download ? '?download=1' : ''),
            'deleteUrl' => fn ($f) => route('hr.payroll.audits.files.delete', [$audit->id, $f->id]),
            'uploadHint' => 'Bảng đối chiếu/biên bản đối soát (ảnh, PDF, Excel) ≤ 20MB.',
            'ownerHint' => 'Người nhập ghi kết quả và gửi kiểm; người kiểm độc lập (khác HCNS chịu KPI và người nhập) xác nhận.',
            'returnPlaceholder' => 'Nội dung cần sửa: dòng/khoản kết luận sai, thiếu chứng cứ lỗi, thiếu dòng trong phạm vi…',
            'how' => '<b>Ai làm gì:</b> người nhập chọn từng dòng <em>Đúng / Có lỗi / Chưa xác định</em>, đánh dấu khoản đã kiểm, ghi lỗi (khoản, loại, nội dung, nguồn, chứng cứ, cách xử lý) → <em>Gửi kiểm tra</em>; '
                .'người kiểm độc lập chấm <em>Phạm vi · Kết quả · Lỗi</em> rồi xác nhận hoặc trả bổ sung. '
                .'<b>Khi nào tính:</b> chỉ khi đợt được xác nhận; dòng chưa kiểm/chưa xác định hoặc dòng lương đổi sau khi kiểm là phần thiếu. '
                .'Lỗi đã sửa vẫn giữ lịch sử; có tính hay không theo quy tắc “Lỗi đã sửa” (nháp: mọi lỗi đã xác nhận đều tính). '
                .'<b>Chưa có điểm chính thức</b> khi H-D05 chưa chốt — đây là số đo, không phải công thức quy đổi điểm.',
        ]])

        <section class="at-panel pr-section pa-lines-box" data-pa-lines>
            <div class="at-card-head">
                <div>
                    <h2><i class="bi bi-list-check"></i>Các dòng trong phạm vi ({{ $scopeLines->count() }})</h2>
                    <p>Mỗi dòng = một nhân viên trong kỳ. Kết quả “Đúng” chỉ cho phần khoản đã đánh dấu kiểm. Sửa sau khi gửi/kiểm tra → cần gửi lại.</p>
                </div>
            </div>
            @if($canWrite)
                <form method="POST" action="{{ route('hr.payroll.audits.items', $audit->id) }}" id="paItemsForm">@csrf</form>
                <div class="pa-save-top"><button class="at-btn at-btn--primary" type="submit" form="paItemsForm"><i class="bi bi-save"></i>Lưu kết quả các dòng</button></div>
            @endif

            <div class="pa-lines">
                @foreach($allLineRows as $entry)
                    @php
                        $line = $entry['line'];
                        $item = $entry['gone'] ? $entry['item'] : $items->get($line->id);
                        $lineId = $line->id ?? $item->line_id;
                        $now = $lineNow->get($lineId);
                        $itemErrors = $item ? $auditErrors->get($item->id, collect()) : collect();
                        $checkedComponents = $item ? (json_decode((string) $item->components, true) ?: []) : array_keys($components);
                        $row = $item ? $rowsByItem->get($item->id) : null;
                        $stale = $item && in_array($item->result, ['ok', 'error'], true) && (! $now || \App\Services\Hr\Kpi\PayrollAudits::lineFingerprint($now) !== $item->line_fingerprint);
                        $snap = $item ? (json_decode((string) $item->line_snapshot, true) ?: []) : [];
                        $show = $line ?? $now;
                    @endphp
                    <article class="pa-line" id="pa-line-{{ $lineId }}" data-pa-line="{{ $lineId }}" data-pa-result="{{ $item->result ?? 'none' }}" data-pa-state="{{ $row['kpi_state'] ?? ($item ? 'missing' : 'unchecked') }}">
                        <header class="pa-line-head">
                            <div>
                                <b>{{ $line->employee_name ?? $item->employee_name }}</b>
                                <small>{{ $line ? \App\Http\Controllers\Hr\PayrollController::departmentOf($line) : ($item->department_name ?: '—') }}
                                    @if($show) · thực lĩnh {{ $vnd($show->net_salary) }} · tổng thu nhập {{ $vnd($show->gross_income) }}@endif
                                    @if($show) · <a href="{{ route('hr.payroll.payslip', $show->id) }}" target="_blank" rel="noopener">phiếu lương</a>@endif
                                </small>
                            </div>
                            <span class="pa-tag {{ $row ? ($stateTone[$row['kpi_state']] ?? '') : ($item ? 'is-warn' : 'is-muted') }}" data-pa-line-state>
                                {{ $row['state_label'] ?? match (true) { $row !== null => ['pending' => 'Chưa tính — chờ xác nhận', 'missing' => 'Chưa tính — thiếu dữ liệu', 'excluded' => 'Loại trừ'][$row['kpi_state']] ?? $row['kpi_state'], $item !== null => 'Chưa tính', default => 'Chưa kiểm' } }}
                            </span>
                        </header>
                        @if($row)<p class="pa-reason" data-pa-reason>{{ $row['reason'] }}</p>@endif
                        @if($entry['gone'])
                            <div class="pa-warn">Dòng lương này không còn trong phạm vi/kỳ (bảng lương đã tính lại hoặc đổi phạm vi). Kết quả và lỗi giữ làm lịch sử.</div>
                        @elseif($stale)
                            <div class="pa-warn" data-pa-stale>Dòng lương đã đổi sau lần kiểm {{ $fmt($item->checked_at) }}: thực lĩnh lúc kiểm {{ $vnd($snap['net_salary'] ?? null) }} → nay {{ $vnd($now->net_salary ?? null) }}.
                                @if($canWrite)<label><input type="checkbox" name="recheck[{{ $lineId }}]" value="1" form="paItemsForm"> Đã kiểm lại số mới (lưu để chụp lại số tiền)</label>@endif
                            </div>
                        @endif

                        <div class="pa-line-body">
                            <div class="pa-field">
                                <label>Kết quả</label>
                                @if($canWrite && ! $entry['gone'])
                                    <select class="pr-input" name="result[{{ $lineId }}]" form="paItemsForm" data-pa-result-select>
                                        <option value="">— Chưa kiểm —</option>
                                        @foreach($results as $k => $label)<option value="{{ $k }}" @selected(($item->result ?? '') === $k)>{{ $label }}</option>@endforeach
                                    </select>
                                @else
                                    <b>{{ $results[$item->result ?? ''] ?? 'Chưa kiểm' }}</b>
                                @endif
                            </div>
                            <div class="pa-field pa-field--wide">
                                <label>Ghi chú</label>
                                @if($canWrite && ! $entry['gone'])
                                    <input class="pr-input" name="item_note[{{ $lineId }}]" value="{{ $item->note ?? '' }}" maxlength="1000" form="paItemsForm" placeholder="VD: đã đối chiếu chấm công, hồ sơ lương, KPI">
                                @else
                                    <span>{{ $item->note ?? '—' }}</span>
                                @endif
                            </div>
                            <details class="pa-components" @if(! $item) data-pa-default-all @endif>
                                <summary>Khoản đã kiểm: {{ $item ? count($checkedComponents).'/'.count($components) : 'mặc định toàn bộ '.count($components).' khoản' }}</summary>
                                <div class="pa-component-grid">
                                    @foreach($components as $ck => $cdef)
                                        <label>
                                            @if($canWrite && ! $entry['gone'])
                                                <input type="checkbox" name="components[{{ $lineId }}][]" value="{{ $ck }}" form="paItemsForm" @checked(in_array($ck, $checkedComponents, true))>
                                            @else
                                                {{ in_array($ck, $checkedComponents, true) ? '✓' : '·' }}
                                            @endif
                                            {{ $cdef['label'] }}
                                            @if($show)<small>{{ collect($cdef['fields'])->map(fn ($f) => in_array($f, ['paid_days', 'standard_days', 'kpi_rate'], true) ? $num($show->{$f} ?? 0) : $vnd($show->{$f} ?? 0))->implode(' · ') }}</small>@endif
                                        </label>
                                    @endforeach
                                </div>
                            </details>
                        </div>
                        @if($item && $item->checked_by)
                            <small class="pa-meta">Ghi bởi {{ $names[(int) $item->checked_by] ?? '—' }} · {{ $fmt($item->checked_at) }}</small>
                        @endif

                        @if($itemErrors->isNotEmpty())
                            <div class="pa-errors" data-pa-errors>
                                @foreach($itemErrors as $e)
                                    <div class="pa-error {{ $e->removed_at ? 'is-removed' : '' }}" data-pa-error="{{ $e->id }}">
                                        <div class="pa-error-head">
                                            <b>Lỗi #{{ $e->id }} · {{ $componentLabels[$e->component] ?? $e->component }}</b>
                                            <span class="pa-tag {{ $e->removed_at ? 'is-muted' : ($e->resolution_status === 'fixed' ? 'is-ok' : 'is-bad') }}">{{ $e->removed_at ? 'Đã gỡ' : ($resolutions[$e->resolution_status] ?? $e->resolution_status) }}</span>
                                        </div>
                                        <div>{{ $errorTypes[$e->error_type] ?? $e->error_type }} — {{ $e->description }}</div>
                                        <small>
                                            Số đúng {{ $vnd($e->expected_amount) }} · số đã ghi {{ $vnd($e->recorded_amount) }}
                                            @if($e->source_url) · nguồn: <a href="{{ $e->source_url }}" target="_blank" rel="noopener noreferrer">{{ \Illuminate\Support\Str::limit($e->source_url, 60) }}</a>@endif
                                            · ghi bởi {{ $names[(int) $e->created_by] ?? '—' }} {{ $fmt($e->created_at) }}
                                            @if($e->fixed_at) · đã sửa {{ $fmt($e->fixed_at) }} ({{ $names[(int) $e->fixed_by] ?? '—' }})@endif
                                        </small>
                                        @if($e->resolution)<small>Cách xử lý: {{ $e->resolution }}</small>@endif
                                        @if($e->removed_at)<small>Gỡ {{ $fmt($e->removed_at) }} bởi {{ $names[(int) $e->removed_by] ?? '—' }}: {{ $e->remove_reason }}</small>@endif
                                        @php $ef = $errorFiles->get($e->id, collect()); @endphp
                                        @if($ef->isNotEmpty())
                                            <div class="pa-files">
                                                @foreach($ef as $f)
                                                    <span>{{ $f->original_name }}
                                                        @if(str_starts_with((string) $f->mime_type, 'image/') || $f->mime_type === 'application/pdf')
                                                            <button type="button" class="hk-src-btn soft" data-hk-preview="{{ route('hr.payroll.audits.files.show', [$audit->id, $f->id]) }}" data-hk-preview-kind="{{ str_starts_with((string) $f->mime_type, 'image/') ? 'image' : 'pdf' }}" data-hk-preview-name="{{ $f->original_name }}">Xem</button>
                                                        @endif
                                                        <a class="hk-src-btn soft" href="{{ route('hr.payroll.audits.files.show', [$audit->id, $f->id]) }}?download=1">Tải</a>
                                                        @if($canWrite)
                                                            <form method="POST" action="{{ route('hr.payroll.audits.files.delete', [$audit->id, $f->id]) }}" onsubmit="return confirm('Xóa tệp chứng cứ? Nếu đã gửi kiểm, cần gửi lại.')">@csrf @method('DELETE')<button class="hk-src-btn danger" type="submit">Xóa</button></form>
                                                        @endif
                                                    </span>
                                                @endforeach
                                            </div>
                                        @endif
                                        @if($canWrite && ! $e->removed_at)
                                            <details class="pa-mini" data-pa-error-edit>
                                                <summary>Sửa lỗi / cập nhật cách xử lý</summary>
                                                <form method="POST" action="{{ route('hr.payroll.audits.errors.update', [$audit->id, $e->id]) }}" class="pa-error-form">
                                                    @csrf @method('PUT')
                                                    @include('hr.payroll._audit-error-fields', ['e' => $e, 'allowed' => $checkedComponents])
                                                    <button class="at-btn at-btn--primary" type="submit">Lưu lỗi</button>
                                                </form>
                                                <form method="POST" action="{{ route('hr.payroll.audits.errors.files.upload', [$audit->id, $e->id]) }}" enctype="multipart/form-data" class="pa-inline">
                                                    @csrf
                                                    <input type="file" name="files[]" multiple accept=".jpg,.jpeg,.png,.webp,.heic,.pdf,.doc,.docx,.xls,.xlsx,.csv" required>
                                                    <button class="hk-src-btn" type="submit">Thêm chứng cứ</button>
                                                </form>
                                                <form method="POST" action="{{ route('hr.payroll.audits.errors.remove', [$audit->id, $e->id]) }}" class="pa-inline" onsubmit="return confirm('Gỡ lỗi này? Lỗi vẫn giữ trong lịch sử.')">
                                                    @csrf
                                                    <input class="pr-input" name="remove_reason" required minlength="5" maxlength="2000" placeholder="Lý do gỡ (ghi nhầm, trùng…)">
                                                    <button class="hk-src-btn danger" type="submit">Gỡ lỗi</button>
                                                </form>
                                            </details>
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                        @endif

                        @if($canWrite && $item && ! $entry['gone'] && $item->result !== 'undetermined')
                            <details class="pa-mini" data-pa-error-add @if($item->result === 'error' && $itemErrors->whereNull('removed_at')->isEmpty()) open @endif>
                                <summary><i class="bi bi-plus-circle"></i> Thêm lỗi cho dòng này</summary>
                                <form method="POST" action="{{ route('hr.payroll.audits.errors.store', [$audit->id, $item->id]) }}" enctype="multipart/form-data" class="pa-error-form">
                                    @csrf
                                    @include('hr.payroll._audit-error-fields', ['e' => null, 'allowed' => $checkedComponents])
                                    <div class="pa-field pa-field--wide"><label>Chứng cứ (ảnh/PDF/Excel)</label><input type="file" name="files[]" multiple accept=".jpg,.jpeg,.png,.webp,.heic,.pdf,.doc,.docx,.xls,.xlsx,.csv"></div>
                                    <button class="at-btn at-btn--primary" type="submit">Ghi lỗi</button>
                                    <small class="pr-muted">Dòng tự chuyển “Có lỗi”. Lỗi chỉ gỡ được bằng lý do và vẫn nằm trong lịch sử.</small>
                                </form>
                            </details>
                        @elseif($canWrite && ! $item && ! $entry['gone'])
                            <small class="pa-meta">Chọn kết quả và bấm “Lưu kết quả các dòng” trước, sau đó mới thêm lỗi chi tiết.</small>
                        @endif
                    </article>
                @endforeach
            </div>

            @if($canWrite)
                <div class="pa-save-bottom"><button class="at-btn at-btn--primary" type="submit" form="paItemsForm"><i class="bi bi-save"></i>Lưu kết quả các dòng</button></div>
            @endif
        </section>

        @if($canWrite)
            <section class="at-panel pr-section pr-body">
                <details class="pr-details" data-pa-plan @if($errors->hasAny(['title', 'owner_id', 'checker_id', 'reviewer_id', 'scope', 'scope_department', 'line_ids'])) open @endif>
                    <summary>Sửa đợt (tên, người, phạm vi) / Hủy đợt</summary>
                    <form method="POST" action="{{ route('hr.payroll.audits.update', $audit->id) }}" class="pa-form">
                        @csrf @method('PUT')
                        <div class="pr-grid pa-grid">
                            <div class="pr-field" style="grid-column:span 2"><label>Tên đợt *</label><input class="pr-input" name="title" value="{{ old('title', $audit->title) }}" required maxlength="255"></div>
                            <div class="pr-field"><label>Phạm vi *</label><select class="pr-input" name="scope">@foreach(\App\Services\Hr\Kpi\PayrollAudits::SCOPES as $k => $label)<option value="{{ $k }}" @selected(old('scope', $audit->scope) === $k)>{{ $label }}</option>@endforeach</select></div>
                            <div class="pr-field"><label>Phòng ban</label><select class="pr-input" name="scope_department"><option value="">—</option>@foreach($departments as $d)<option value="{{ $d }}" @selected(old('scope_department', $audit->scope_department) === $d)>{{ $d }}</option>@endforeach</select></div>
                            <div class="pr-field"><label>HCNS chịu KPI *</label><select class="pr-input" name="owner_id">@foreach($users as $u)<option value="{{ $u->id }}" @selected((int) old('owner_id', $audit->owner_id) === (int) $u->id)>{{ $u->name }}</option>@endforeach</select></div>
                            <div class="pr-field"><label>Người nhập *</label><select class="pr-input" name="checker_id">@foreach($users as $u)<option value="{{ $u->id }}" @selected((int) old('checker_id', $audit->checker_id) === (int) $u->id)>{{ $u->name }}</option>@endforeach</select></div>
                            <div class="pr-field"><label>Người kiểm độc lập *</label><select class="pr-input" name="reviewer_id">@foreach($users as $u)<option value="{{ $u->id }}" @selected((int) old('reviewer_id', $audit->reviewer_id) === (int) $u->id)>{{ $u->name }}</option>@endforeach</select></div>
                            <div class="pr-field"><label>Ghi chú</label><input class="pr-input" name="note" value="{{ is_string(old('note')) ? old('note') : $audit->note }}" maxlength="2000"></div>
                        </div>
                        @php $pickIds = json_decode((string) $audit->scope_line_ids, true) ?: []; @endphp
                        <details class="pa-lines-pick"><summary>Chọn từng dòng (phạm vi “Chọn từng dòng”)</summary>
                            <div class="pa-component-grid">
                                @foreach(\Illuminate\Support\Facades\DB::table('hr_payroll_lines')->where('period_id', $audit->period_id)->orderBy('employee_name')->get(['id', 'employee_name']) as $l)
                                    <label><input type="checkbox" name="line_ids[]" value="{{ $l->id }}" @checked(in_array($l->id, $pickIds))> {{ $l->employee_name }}</label>
                                @endforeach
                            </div>
                        </details>
                        <button class="at-btn at-btn--primary" type="submit"><i class="bi bi-check2"></i>Lưu đợt</button>
                    </form>
                    @if(! in_array($reviewStatus, ['submitted', 'confirmed'], true))
                        <form method="POST" action="{{ route('hr.payroll.audits.cancel', $audit->id) }}" class="pa-form" onsubmit="return confirm('Hủy đợt đối soát này?')" style="margin-top:12px">
                            @csrf
                            <label class="pr-muted" style="font-weight:800;color:#be123c">Hủy đợt (bắt buộc ghi lý do) — đợt đã hủy không vào KPI, lịch sử giữ nguyên</label>
                            <input class="pr-input" name="cancel_reason" minlength="5" required placeholder="VD: lập nhầm kỳ">
                            <button class="at-btn at-btn--danger" type="submit">Hủy đợt đối soát</button>
                        </form>
                    @endif
                </details>
            </section>
        @endif

        @if($logs->isNotEmpty())
            <section class="at-panel pr-section pr-body pa-logs" data-pa-logs>
                <h2 style="font-size:13px;margin:0 0 6px">Lịch sử đợt đối soát ({{ $logs->count() }})</h2>
                @foreach($logs as $log)
                    <div>{{ $fmt($log->created_at) }} · {{ $log->actor_name ?: 'Hệ thống' }} · <b>{{ \App\Services\Hr\Kpi\PayrollAudits::LOG_LABELS[$log->action] ?? $log->action }}</b>@if($log->note) — {{ $log->note }}@endif</div>
                @endforeach
            </section>
        @endif
    </div>
</div>

<style>
    #egoAttendancePromax .pa-lines{display:grid;gap:10px}
    #egoAttendancePromax .pa-line{border:1px solid #dbe5f0;border-radius:12px;padding:10px 12px;background:#fff;display:grid;gap:6px;min-width:0;scroll-margin-top:90px}
    #egoAttendancePromax .pa-line:target{box-shadow:0 0 0 3px #bae6fd}
    #egoAttendancePromax .pa-line-head{display:flex;justify-content:space-between;gap:8px;align-items:flex-start;flex-wrap:wrap}
    #egoAttendancePromax .pa-line-head b{font-size:13px;color:#17314e}
    #egoAttendancePromax .pa-line-head small{display:block;color:#64748b;font-size:11px}
    #egoAttendancePromax .pa-tag{border-radius:10px;padding:2px 9px;font-size:11px;font-weight:700;background:#f1f4f8;color:#556274;max-width:100%;white-space:normal}
    #egoAttendancePromax .pa-tag.is-ok{background:#e8f7ee;color:#15803d}#egoAttendancePromax .pa-tag.is-bad{background:#fdecec;color:#b91c1c}
    #egoAttendancePromax .pa-tag.is-wait{background:#e9f6fb;color:#0e6a8a}#egoAttendancePromax .pa-tag.is-warn{background:#fff6e5;color:#9a5b00}
    #egoAttendancePromax .pa-reason{margin:0;font-size:11.5px;color:#334155}
    #egoAttendancePromax .pa-warn{font-size:11.5px;background:#fff7ed;border:1px solid #fed7aa;color:#9a3412;border-radius:10px;padding:6px 9px;display:grid;gap:4px}
    #egoAttendancePromax .pa-line-body{display:grid;grid-template-columns:minmax(150px,220px) minmax(0,1fr);gap:8px 12px;align-items:start}
    #egoAttendancePromax .pa-field label{display:block;margin-bottom:3px;color:#5b7284;font-size:10px;font-weight:800;text-transform:uppercase}
    #egoAttendancePromax .pa-components{grid-column:1/-1}
    #egoAttendancePromax .pa-components summary,#egoAttendancePromax .pa-mini summary,#egoAttendancePromax .pa-lines-pick summary{cursor:pointer;font-size:11px;font-weight:800;color:#087e92}
    #egoAttendancePromax .pa-component-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(220px,1fr));gap:4px 12px;margin-top:6px;font-size:11.5px}
    #egoAttendancePromax .pa-component-grid small{display:block;color:#7a8f9c;font-size:10px;margin-left:18px}
    #egoAttendancePromax .pa-meta{color:#7a8f9c;font-size:10.5px}
    #egoAttendancePromax .pa-errors{display:grid;gap:6px}
    #egoAttendancePromax .pa-error{border:1px solid #f5c9c9;background:#fffafa;border-radius:10px;padding:7px 9px;font-size:12px;display:grid;gap:3px;min-width:0;word-break:break-word}
    #egoAttendancePromax .pa-error.is-removed{border-color:#e1e7ef;background:#f8fafc;color:#64748b;text-decoration:line-through}
    #egoAttendancePromax .pa-error.is-removed small,#egoAttendancePromax .pa-error.is-removed .pa-error-head{text-decoration:none}
    #egoAttendancePromax .pa-error small{color:#64748b;font-size:10.5px}
    #egoAttendancePromax .pa-error-head{display:flex;justify-content:space-between;gap:6px;flex-wrap:wrap}
    #egoAttendancePromax .pa-files{display:flex;flex-wrap:wrap;gap:6px}
    #egoAttendancePromax .pa-files span{display:inline-flex;gap:4px;align-items:center;flex-wrap:wrap;font-size:11px}
    #egoAttendancePromax .pa-files form,#egoAttendancePromax .pa-inline{display:flex;gap:6px;align-items:center;flex-wrap:wrap;margin:6px 0 0}
    #egoAttendancePromax .pa-error-form{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:8px;margin-top:6px;align-items:end}
    #egoAttendancePromax .pa-field--wide{grid-column:span 2}
    #egoAttendancePromax .pa-form{display:grid;gap:10px}
    #egoAttendancePromax .pa-save-top{margin:0 0 10px}#egoAttendancePromax .pa-save-bottom{margin-top:10px}
    #egoAttendancePromax .pa-logs div{font-size:11.5px;color:#475569;padding:3px 0;border-bottom:1px dashed #eef2f7}
    @media(max-width:760px){
        #egoAttendancePromax .pa-line-body,#egoAttendancePromax .pa-error-form{grid-template-columns:1fr}
        #egoAttendancePromax .pa-field--wide{grid-column:auto}
        #egoAttendancePromax .pa-grid{grid-template-columns:1fr}#egoAttendancePromax .pa-grid .pr-field{grid-column:auto!important}
        #egoAttendancePromax .at-btn{width:100%;justify-content:center}
    }
</style>
@endsection
