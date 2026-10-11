@extends('layouts.app')

@section('content')
@include('hr.recruitment.kpi._style')
@php
    $fmt = fn ($t) => $t ? \Carbon\Carbon::parse($t)->format('H:i d/m/Y') : '—';
    $money = fn ($v) => $v === null ? '—' : number_format((float) $v, 0, ',', '.').' đ';
    $removed = ! empty($cost->removed_at);
    $docLink = $doc && $canOpenDoc ? '<a href="'.e(url('/payment-requests/'.$doc['id'])).'" target="_blank" rel="noopener">mở chứng từ '.e($doc['code']).'</a>' : null;
@endphp
<div class="rk-wrap" data-rk-cost-page="{{ $cost->id }}">
    <div class="rk-head">
        <div>
            <h1>Chi phí tuyển dụng #{{ $cost->id }} · {{ $doc['code'] ?? 'Chứng từ #'.$cost->payment_request_id }}</h1>
            <p>{{ $req->position }} · {{ $req->department }} · {{ $doc['type_label'] ?? '—' }}</p>
        </div>
        <div class="rk-actions"><a class="rk-btn outline" href="{{ route('hr.recruitment.requests.kpi', $req->id) }}">← KPI của yêu cầu tuyển</a></div>
    </div>

    @if(session('success'))<div class="rk-alert ok">{{ session('success') }}</div>@endif
    @if(session('error'))<div class="rk-alert err">{{ session('error') }}</div>@endif
    @if($errors->any())<div class="rk-alert err" data-rk-error>{{ $errors->first() }}</div>@endif
    @if($removed)<div class="rk-alert err">Đã gỡ {{ $fmt($cost->removed_at) }} bởi {{ $names[(int) $cost->removed_by] ?? '—' }}: {{ $cost->remove_reason }}</div>@endif

    @include('hr.kpi._source-review-panel', ['p' => [
        'id' => 'kpi-hc02', 'title' => 'KPI HC02 · Chi phí tuyển dụng (khoản phân bổ)',
        'row' => $row, 'review' => $review, 'events' => $events,
        'files' => null,
        'filesNote' => 'Chứng từ gốc và tệp đính kèm nằm ở module Đề nghị thanh toán'.($docLink ? ' — '.$docLink : ' (bạn không có quyền mở chứng từ này)').'. Không tải lại hóa đơn ở đây.',
        'missing' => $missing, 'locked' => $locked,
        'isOwner' => $isOwner && ! $removed, 'canEdit' => false, 'canReview' => $canReview && ! $removed,
        'checks' => (array) config('hcns_kpi.cost_checks'),
        'facts' => [
            ['Chứng từ', ($doc['code'] ?? '—').' · '.($doc['type_label'] ?? '—')],
            ['Nội dung chứng từ', \Illuminate\Support\Str::limit($doc['content'] ?? '—', 120)],
            ['Trạng thái chứng từ', ($doc['status'] ?? '—').' · '.($doc['valid'] ?? false ? 'đã thực chi' : 'chưa thực chi')],
            ['Số đề nghị', $money($doc['amount'] ?? null)],
            ['Số thực chi hợp lệ', ($doc['valid'] ?? false) ? $money($doc['valid_amount']).' · ngày '.$fmt($doc['valid_at']) : '—'],
            ['Phân bổ cho đợt này', $money($cost->allocated_amount).(($doc['sign'] ?? 1) < 0 ? ' (hoàn tiền — trừ ra)' : '')],
            ['Đã phân bổ nơi khác', $other->isEmpty() ? 'Không' : $other->map(fn ($o) => '#'.$o->request_id.' '.$o->position.': '.$money($o->allocated_amount))->implode('; ')],
            ['HCNS phụ trách · người kiểm', ($names[(int) $cost->owner_id] ?? '—').' · '.($names[(int) $cost->reviewer_id] ?? '—')],
        ],
        'routes' => [
            'draft' => route('hr.recruitment.costs.kpi', [$cost->id, 'draft']), 'submit' => route('hr.recruitment.costs.kpi', [$cost->id, 'submit']),
            'confirm' => route('hr.recruitment.costs.kpi', [$cost->id, 'confirm']), 'return' => route('hr.recruitment.costs.kpi', [$cost->id, 'return']),
            'upload' => '#',
        ],
        'returnPlaceholder' => 'Cần sửa: chứng từ chưa thực chi, số phân bổ, trùng khoản…', 'fileUrl' => fn () => '#', 'deleteUrl' => fn () => '#',
        'ownerHint' => 'HCNS phụ trách gửi kiểm; người kiểm được chỉ định hoặc Ban Giám đốc xác nhận.',
        'how' => '<b>Ai làm gì:</b> HCNS phụ trách gắn chứng từ đã có và ghi số phân bổ cho đợt tuyển → <em>Gửi kiểm tra</em>; người kiểm xác nhận chứng từ đã thực chi, đúng nội dung và số phân bổ không trùng. '
            .'<b>Khi nào tính:</b> chỉ khoản đã thực chi và đã kiểm, theo tháng thực chi; chia cho số vị trí tuyển thành công đã xác nhận trong tháng. Chi phí đề xuất, tạm ứng chưa quyết toán không tính; hoàn tiền trừ ra. '
            .'Không có vị trí tuyển thành công → chưa đủ. <b>Chưa phải điểm chính thức</b> khi ngưỡng/cohort/quy đổi HC02 chưa chốt.',
    ]])

    @if($canEdit && ! $removed && ! $locked)
        <details class="rk-card" data-rk-cost-form @if($errors->hasAny(['allocated_amount','reviewer_id'])) open @endif>
            <summary style="cursor:pointer;font-weight:900;color:#0f3b78">Điều chỉnh số phân bổ / người kiểm</summary>
            <form method="POST" action="{{ route('hr.recruitment.costs.update', $cost->id) }}" style="margin-top:10px">
                @csrf @method('PUT')
                <div class="rk-grid">
                    <div class="rk-field"><label>Số phân bổ cho đợt này (đ) *</label><input class="rk-input" type="number" min="1" step="1" name="allocated_amount" value="{{ old('allocated_amount', (float) $cost->allocated_amount) }}" required></div>
                    <div class="rk-field"><label>Người kiểm *</label><select class="rk-input" name="reviewer_id">@foreach($users as $u)<option value="{{ $u->id }}" @selected((int) old('reviewer_id', $cost->reviewer_id) === (int) $u->id)>{{ $u->name }}</option>@endforeach</select></div>
                    <div class="rk-field"><label>Ghi chú</label><input class="rk-input" name="note" value="{{ old('note', $cost->note) }}" maxlength="2000"></div>
                </div>
                <div style="margin-top:10px"><button class="rk-btn" type="submit">Lưu điều chỉnh</button>
                    <span class="rk-note">Lịch sử điều chỉnh được ghi lại; sửa sau khi gửi/kiểm → phải gửi lại.</span></div>
            </form>
            <form method="POST" action="{{ route('hr.recruitment.costs.remove', $cost->id) }}" style="margin-top:12px;display:grid;gap:6px" onsubmit="return confirm('Gỡ khoản chi khỏi đợt tuyển?')">
                @csrf
                <input class="rk-input" name="remove_reason" minlength="5" required placeholder="Lý do gỡ (bắt buộc)">
                <button class="rk-btn danger" type="submit" style="max-width:220px">Gỡ khoản chi</button>
            </form>
        </details>
    @endif

    @if($logs->isNotEmpty())
        <div class="rk-card rk-logs" data-rk-logs>
            <h2>Lịch sử điều chỉnh ({{ $logs->count() }})</h2>
            @foreach($logs as $log)<div>{{ $fmt($log->created_at) }} · {{ $log->actor_name ?: 'Hệ thống' }} · <b>{{ \App\Services\Hr\Kpi\RecruitmentKpi::LOG_LABELS[$log->action] ?? $log->action }}</b>@if($log->note) — {{ $log->note }}@endif</div>@endforeach
        </div>
    @endif
</div>
@endsection
