@extends('layouts.app')

@section('content')
@include('hr.recruitment.kpi._style')
@php
    $fmt = fn ($t) => $t ? \Carbon\Carbon::parse($t)->format('H:i d/m/Y') : '—';
    $fmtD = fn ($t) => $t ? \Carbon\Carbon::parse($t)->format('d/m/Y') : '—';
    $d = fn ($t) => $t ? \Carbon\Carbon::parse($t)->toDateString() : '';
    $statuses = (array) config('hcns_kpi.hire_statuses');
    $outcomes = (array) config('hcns_kpi.probation_outcomes');
    $leftTypes = (array) config('hcns_kpi.probation_left_types');
    $cancelled = $hire->status === 'cancelled';
    $days = \App\Services\Hr\Kpi\RecruitmentKpi::recruitDays($req->request_received_at, $hire->actual_start_date);
    $profileMismatch = $profile && ($profile->probation_start_date || $profile->probation_end_date)
        && ((string) $profile->probation_start_date !== (string) $hire->probation_start_date || (string) $profile->probation_end_date !== (string) $hire->probation_end_date);
    $fileUrl = fn ($f, $download) => route('hr.recruitment.attachments.'.($download ? 'download' : 'content'), ['hires', $hire->id, $f->id]);
    $previewPage = fn ($f) => route('hr.recruitment.attachments.preview', ['hires', $hire->id, $f->id]);
    $deleteUrl = fn ($f) => route('hr.recruitment.hires.files.delete', [$hire->id, $f->id]);
    $routes = fn ($c) => [
        'draft' => route('hr.recruitment.hires.kpi', [$hire->id, $c, 'draft']), 'submit' => route('hr.recruitment.hires.kpi', [$hire->id, $c, 'submit']),
        'confirm' => route('hr.recruitment.hires.kpi', [$hire->id, $c, 'confirm']), 'return' => route('hr.recruitment.hires.kpi', [$hire->id, $c, 'return']),
        'upload' => route('hr.recruitment.hires.files.upload', $hire->id),
    ];
@endphp
<div class="rk-wrap" data-rk-hire-page="{{ $hire->id }}">
    <div class="rk-head">
        <div>
            <h1>Lượt tuyển #{{ $hire->id }} · {{ $candidate->full_name ?? 'Chưa chọn ứng viên' }}</h1>
            <p>{{ $req->position }} · {{ $req->department }} · {{ $statuses[$hire->status] ?? $hire->status }}</p>
        </div>
        <div class="rk-actions"><a class="rk-btn outline" href="{{ route('hr.recruitment.requests.kpi', $req->id) }}">← KPI của yêu cầu tuyển</a></div>
    </div>

    @if(session('success'))<div class="rk-alert ok">{{ session('success') }}</div>@endif
    @if(session('error'))<div class="rk-alert err">{{ session('error') }}</div>@endif
    @if($errors->any())<div class="rk-alert err" data-rk-error>{{ $errors->first() }}</div>@endif
    @if($cancelled)<div class="rk-alert err">Đã hủy {{ $fmt($hire->cancelled_at) }} bởi {{ $names[(int) $hire->cancelled_by] ?? '—' }}: {{ $hire->cancel_reason }}</div>@endif

    @if($timeRow)
        @include('hr.kpi._source-review-panel', ['p' => [
            'id' => 'kpi-hc01', 'title' => 'KPI HC01 · Thời gian tuyển dụng (nhận việc thực tế)',
            'row' => $timeRow, 'review' => $timeReview, 'events' => $timeReview ? app(\App\Services\Hr\Kpi\HcnsSourceReviews::class)->events((int) $timeReview->id) : collect(),
            'files' => $files, 'missing' => $timeMissing, 'locked' => $timeLocked,
            'isOwner' => $isOwner && ! $cancelled, 'canEdit' => $canEdit && ! $cancelled, 'canReview' => $canReviewTime && ! $cancelled,
            'checks' => (array) config('hcns_kpi.hire_checks'),
            'facts' => [
                ['Yêu cầu tuyển', '#'.$req->id.' · '.$req->position.' ('.$req->quantity.' người)'],
                ['HCNS tiếp nhận yêu cầu', $fmt($req->request_received_at)],
                ['Thư mời · ngày dự kiến', $offer ? '#'.$offer->id.' · '.$fmtD($offer->start_date).' (không dùng để tính)' : '—'],
                ['Ngày nhận việc THỰC TẾ', $fmtD($hire->actual_start_date)],
                ['Số ngày tuyển', $days === null ? '—' : $days.' ngày'],
                ['Tài khoản nhân viên', $names[(int) $hire->employee_user_id] ?? 'Chưa nối'],
                ['HCNS phụ trách (chịu KPI)', $names[(int) $hire->owner_id] ?? '—'],
                ['Người kiểm', $names[(int) $hire->reviewer_id] ?? '—'],
            ],
            'routes' => $routes('recruit_time'), 'returnPlaceholder' => 'Cần sửa: ngày nhận việc thực tế, tài khoản nhân viên, chứng cứ…', 'fileUrl' => $fileUrl, 'deleteUrl' => $deleteUrl, 'previewPage' => $previewPage,
            'uploadField' => 'attachments', 'accept' => '.pdf,.jpg,.jpeg,.png,.webp,.docx,.xlsx,.xls,.csv,.txt',
            'uploadHint' => 'PDF/ảnh/Word DOCX/Excel ≤ '.(\App\Services\Hr\RecruitmentAttachmentService::maxKilobytes() / 1024).' MB/tệp, tối đa 8 tệp/lần (kho tệp tuyển dụng).',
            'ownerHint' => 'HCNS phụ trách gửi kiểm; người kiểm được chỉ định hoặc Ban Giám đốc xác nhận (không phải chính nhân viên mới).',
            'how' => '<b>Ai làm gì:</b> HCNS phụ trách ghi trạng thái “Đã nhận việc thực tế”, <em>ngày đi làm thật</em>, nối tài khoản nhân viên, tải HĐ/biên bản/quyết định nhận việc → <em>Gửi kiểm tra</em>; '
                .'người kiểm chấm 3 mục. <b>Khi nào tính:</b> lượt đã xác nhận vào bình quân số ngày của tháng nhận việc. Lượt “không nhận việc”/“hủy” không vào bình quân. '
                .'<b>Chưa phải điểm chính thức</b> khi ngưỡng/cohort/quy đổi HC01 chưa chốt.',
        ]])
    @endif

    @if($canEdit && ! $cancelled && ! $timeLocked)
        <details class="rk-card" data-rk-hire-form @if($errors->hasAny(['candidate_id','employee_user_id','status','actual_start_date','cancel_reason','reviewer_id','owner_id'])) open @endif>
            <summary style="cursor:pointer;font-weight:900;color:#0f3b78">Sửa lượt tuyển (nhận việc thực tế, nhân viên, người)</summary>
            <form method="POST" action="{{ route('hr.recruitment.hires.update', $hire->id) }}" style="margin-top:10px">
                @csrf @method('PUT')
                <div class="rk-grid">
                    <div class="rk-field"><label>Ứng viên (của yêu cầu này) *</label><select class="rk-input" name="candidate_id">@foreach($candidates as $c)<option value="{{ $c->id }}" @selected((int) old('candidate_id', $hire->candidate_id) === (int) $c->id)>{{ $c->full_name }}</option>@endforeach</select></div>
                    <div class="rk-field"><label>Trạng thái *</label><select class="rk-input" name="status">@foreach(['pending','started','no_show'] as $s)<option value="{{ $s }}" @selected(old('status', $hire->status) === $s)>{{ $statuses[$s] }}</option>@endforeach</select></div>
                    <div class="rk-field"><label>Ngày nhận việc THỰC TẾ</label><input class="rk-input" type="date" name="actual_start_date" value="{{ old('actual_start_date', $d($hire->actual_start_date)) }}"></div>
                    <div class="rk-field"><label>Tài khoản nhân viên (nối hồ sơ bằng ID)</label><select class="rk-input" name="employee_user_id"><option value="">-- Chưa nối --</option>@foreach($users as $u)<option value="{{ $u->id }}" @selected((int) old('employee_user_id', $hire->employee_user_id) === (int) $u->id)>{{ $u->name }} (#{{ $u->id }})</option>@endforeach</select></div>
                    <div class="rk-field"><label>HCNS phụ trách *</label><select class="rk-input" name="owner_id">@foreach($users as $u)<option value="{{ $u->id }}" @selected((int) old('owner_id', $hire->owner_id) === (int) $u->id)>{{ $u->name }}</option>@endforeach</select></div>
                    <div class="rk-field"><label>Người kiểm *</label><select class="rk-input" name="reviewer_id">@foreach($users as $u)<option value="{{ $u->id }}" @selected((int) old('reviewer_id', $hire->reviewer_id) === (int) $u->id)>{{ $u->name }}</option>@endforeach</select></div>
                    <div class="rk-field full"><label>Lý do không nhận việc (khi chọn “Không nhận việc”)</label><input class="rk-input" name="cancel_reason" value="{{ old('cancel_reason', $hire->status === 'no_show' ? $hire->cancel_reason : '') }}" maxlength="2000"></div>
                    <div class="rk-field full"><label>Ghi chú thay đổi</label><input class="rk-input" name="note" maxlength="2000"></div>
                </div>
                <div style="margin-top:10px"><button class="rk-btn" type="submit">Lưu lượt tuyển</button>
                    <span class="rk-note">Ngày dự kiến trong thư mời không dùng để tính. Sửa sau khi gửi/kiểm → phải gửi lại.</span></div>
            </form>
        </details>
    @endif

    @if($hire->status === 'started')
        @include('hr.kpi._source-review-panel', ['p' => [
            'id' => 'kpi-hc03', 'title' => 'KPI HC03 · Kết quả thử việc',
            'row' => $probRow, 'review' => $probReview, 'events' => $probReview ? app(\App\Services\Hr\Kpi\HcnsSourceReviews::class)->events((int) $probReview->id) : collect(),
            'files' => null, 'filesNote' => 'Chứng cứ (quyết định đạt thử việc/đơn nghỉ/biên bản) dùng chung kho tệp ở khối HC01 phía trên; thêm/xóa tệp → cả hai phần phải gửi lại.',
            'missing' => $probMissing, 'locked' => $probLocked,
            'isOwner' => $isOwner, 'canEdit' => $canEdit, 'canReview' => $canReviewProb,
            'checks' => (array) config('hcns_kpi.probation_checks'),
            'facts' => [
                ['Nhân viên', $names[(int) $hire->employee_user_id] ?? 'Chưa nối'],
                ['Thử việc', $fmtD($hire->probation_start_date).' → '.$fmtD($hire->probation_end_date)],
                ['Kết quả', $outcomes[$hire->probation_outcome ?? ''] ?? 'Chưa xác định'],
                ['Ngày nghỉ thực · hình thức', $hire->probation_outcome === 'left' ? $fmtD($hire->probation_left_date).' · '.($leftTypes[$hire->probation_left_type] ?? '—') : '—'],
                ['Lý do', $hire->probation_reason ?: '—'],
                ['Hồ sơ nhân viên ghi thử việc', $profile ? $fmtD($profile->probation_start_date).' → '.$fmtD($profile->probation_end_date).($profileMismatch ? ' (khác lượt tuyển — kiểm tra lại)' : '') : 'Chưa có hồ sơ chi tiết'],
            ],
            'routes' => $routes('probation_quit'), 'returnPlaceholder' => 'Cần sửa: ngày thử việc, kết quả, ngày nghỉ/lý do, chứng cứ…', 'fileUrl' => $fileUrl, 'deleteUrl' => $deleteUrl,
            'ownerHint' => 'HCNS phụ trách gửi kiểm; người kiểm được chỉ định hoặc Ban Giám đốc xác nhận (không phải chính nhân viên).',
            'how' => '<b>Ai làm gì:</b> HCNS phụ trách ghi ngày thử việc và kết quả (đang thử việc / đạt / nghỉ trong thử việc kèm ngày nghỉ thực, hình thức, lý do) → <em>Gửi kiểm tra</em>. '
                .'<b>Khi nào tính:</b> người đã có kết quả và được kiểm vào cohort tháng kết thúc thử việc (hoặc tháng nghỉ thực). Đang thử việc: chưa tính; hết thử việc mà chưa xác định: thiếu dữ liệu — '
                .'<b>không suy “nghỉ” từ tài khoản ngừng hoạt động hay thiếu chấm công</b>. Cohort/ngưỡng chưa chốt → chỉ xem trước.',
        ]])

        @if($canEdit && ! $probLocked)
            <details class="rk-card" data-rk-probation-form @if($errors->hasAny(['probation_start_date','probation_end_date','probation_outcome','probation_left_date','probation_left_type','probation_reason'])) open @endif>
                <summary style="cursor:pointer;font-weight:900;color:#0f3b78">Ghi / sửa kết quả thử việc</summary>
                <form method="POST" action="{{ route('hr.recruitment.hires.probation', $hire->id) }}" style="margin-top:10px">
                    @csrf @method('PUT')
                    <div class="rk-grid">
                        <div class="rk-field"><label>Bắt đầu thử việc *</label><input class="rk-input" type="date" name="probation_start_date" value="{{ old('probation_start_date', $d($hire->probation_start_date ?? $profile?->probation_start_date ?? $hire->actual_start_date)) }}" required></div>
                        <div class="rk-field"><label>Kết thúc thử việc *</label><input class="rk-input" type="date" name="probation_end_date" value="{{ old('probation_end_date', $d($hire->probation_end_date ?? $profile?->probation_end_date)) }}" required></div>
                        <div class="rk-field"><label>Kết quả</label><select class="rk-input" name="probation_outcome"><option value="">Chưa xác định</option>@foreach($outcomes as $k => $l)<option value="{{ $k }}" @selected(old('probation_outcome', $hire->probation_outcome) === $k)>{{ $l }}</option>@endforeach</select></div>
                        <div class="rk-field"><label>Ngày nghỉ thực (nếu nghỉ)</label><input class="rk-input" type="date" name="probation_left_date" value="{{ old('probation_left_date', $d($hire->probation_left_date)) }}"></div>
                        <div class="rk-field"><label>Hình thức nghỉ</label><select class="rk-input" name="probation_left_type"><option value="">—</option>@foreach($leftTypes as $k => $l)<option value="{{ $k }}" @selected(old('probation_left_type', $hire->probation_left_type) === $k)>{{ $l }}</option>@endforeach</select></div>
                        <div class="rk-field"><label>Lý do nghỉ / ghi chú kết quả</label><input class="rk-input" name="probation_reason" value="{{ old('probation_reason', $hire->probation_reason) }}" maxlength="2000"></div>
                    </div>
                    <div style="margin-top:10px"><button class="rk-btn" type="submit">Lưu kết quả thử việc</button></div>
                </form>
            </details>
        @endif
    @endif

    @if($canEdit && ! $cancelled && ! $timeLocked && ! in_array($timeReview->status ?? null, ['submitted', 'confirmed'], true))
        <details class="rk-card" data-rk-hire-cancel>
            <summary style="cursor:pointer;font-weight:900;color:#be123c">Hủy lượt tuyển</summary>
            <form method="POST" action="{{ route('hr.recruitment.hires.cancel', $hire->id) }}" style="margin-top:10px;display:grid;gap:6px" onsubmit="return confirm('Hủy lượt tuyển này?')">
                @csrf
                <input class="rk-input" name="cancel_reason" minlength="5" required placeholder="Lý do hủy (bắt buộc)">
                <button class="rk-btn danger" type="submit" style="max-width:220px">Hủy lượt tuyển</button>
            </form>
        </details>
    @endif

    @if($logs->isNotEmpty())
        <div class="rk-card rk-logs" data-rk-logs>
            <h2>Lịch sử lượt tuyển ({{ $logs->count() }})</h2>
            @foreach($logs as $log)<div>{{ $fmt($log->created_at) }} · {{ $log->actor_name ?: 'Hệ thống' }} · <b>{{ \App\Services\Hr\Kpi\RecruitmentKpi::LOG_LABELS[$log->action] ?? $log->action }}</b>@if($log->note) — {{ $log->note }}@endif</div>@endforeach
        </div>
    @endif
</div>
@endsection
