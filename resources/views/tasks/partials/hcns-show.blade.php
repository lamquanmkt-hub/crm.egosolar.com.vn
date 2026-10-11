{{--
    Thủ tục hành chính HCNS (KPI HC09) trên trang chi tiết công việc.
    Trạng thái KPI dùng đúng bộ đo của checklist (AdminDeadlineSource), theo quy tắc của kỳ hạn.
--}}
@php
    $hcnsMonth = $task->due_at ? \Illuminate\Support\Carbon::parse($task->due_at)->format('Y-m') : now()->format('Y-m');
    $hcnsRules = app(\App\Services\Hr\Kpi\HcnsKpiRules::class)->forMonth($hcnsMonth);
    $hcnsNames = \Illuminate\Support\Facades\DB::table('users')
        ->whereIn('id', array_filter([$task->assignee_id, $task->approver_id, $task->approved_by, $task->cancelled_by]))
        ->pluck('name', 'id')->all();
    $hcnsRow = app(\App\Services\Hr\Kpi\Sources\AdminDeadlineSource::class)->evaluate($task, $hcnsRules['rules'], now(), null, $hcnsNames);
    $hcnsFmt = fn ($t) => $t ? \Illuminate\Support\Carbon::parse($t)->format('H:i d/m/Y') : '—';
    $hcnsStateLabel = [
        'earned' => ['Đúng hạn — được tính', 'ok'],
        'lost' => ['Không đúng hạn', 'bad'],
        'pending' => ['Chờ xác nhận', 'wait'],
        'waiting' => ['Còn hạn — chưa tính', 'muted'],
        'excluded' => ['Loại khỏi mẫu số', 'muted'],
        'missing' => ['Thiếu dữ liệu', 'warn'],
    ][$hcnsRow['kpi_state']];
@endphp
<div class="soft-card mb-3 hcns-show" data-hcns-show data-hcns-state="{{ $hcnsRow['kpi_state'] }}">
    <div class="card-head" style="background:#f2f7ff;color:#123a64">
        <i class="bi bi-bank"></i> Thủ tục hành chính HCNS · KPI HC09
    </div>
    <div class="card-body-custom">
        <div class="info-grid mb-2">
            <div class="info-box"><div class="info-label">Loại thủ tục</div><div class="info-value">{{ $hcnsRow['procedure'] }}</div></div>
            <div class="info-box"><div class="info-label">Hạn hiện tại</div><div class="info-value">{{ $hcnsFmt($hcnsRow['due_at']) }}</div></div>
            <div class="info-box"><div class="info-label">Hạn gốc lúc giao</div><div class="info-value">{{ $hcnsFmt($hcnsRow['original_due_at']) }}@if($hcnsRow['due_changed']) <small class="text-warning">· đã đổi hạn</small>@endif</div></div>
            @if($hcnsRow['due_changed'])
                <div class="info-box" data-hcns-measure-deadline><div class="info-label">Hạn dùng để đo KPI</div><div class="info-value">{{ $hcnsFmt($hcnsRow['deadline']) }}
                    <small class="text-muted">· {{ $hcnsRow['deadline'] && $hcnsRow['original_due_at'] && $hcnsRow['deadline']->equalTo($hcnsRow['original_due_at']) ? 'đổi hạn khi đã quá hạn — giữ hạn gốc' : 'gia hạn trước hạn — dùng hạn mới' }} (quy tắc nháp)</small></div></div>
            @endif
            <div class="info-box"><div class="info-label">Người kiểm</div><div class="info-value">{{ $hcnsRow['reviewer_name'] ?? 'Chưa chọn' }}</div></div>
            <div class="info-box"><div class="info-label">Gửi lần đầu</div><div class="info-value">{{ $hcnsFmt($hcnsRow['first_submitted_at']) }}</div></div>
            <div class="info-box"><div class="info-label">Gửi gần nhất · số lần</div><div class="info-value">{{ $hcnsFmt($hcnsRow['last_submitted_at']) }} · {{ $hcnsRow['submission_count'] }}</div></div>
            <div class="info-box"><div class="info-label">Xác nhận</div><div class="info-value">{{ $hcnsRow['approved_by_name'] ? $hcnsRow['approved_by_name'].' · '.$hcnsFmt($hcnsRow['approved_at']) : 'Chưa xác nhận' }}</div></div>
            <div class="info-box"><div class="info-label">KPI HC09 (xem trước)</div><div class="info-value"><span class="hcns-kpi-pill is-{{ $hcnsStateLabel[1] }}">{{ $hcnsStateLabel[0] }}</span></div></div>
        </div>
        <p class="hcns-show__reason mb-2"><i class="bi bi-calculator"></i> {{ $hcnsRow['reason'] }}</p>
        @if($task->status === 'cancelled')
            <p class="hcns-show__reason mb-2"><i class="bi bi-x-octagon"></i> Hủy bởi {{ $hcnsNames[$task->cancelled_by] ?? '—' }} lúc {{ $hcnsFmt($task->cancelled_at) }}: {{ $task->cancel_reason }}</p>
        @endif
        <div class="hcns-show__how">
            <b>Ai làm gì:</b> người nhận việc <em>Lưu nháp</em> hoặc <em>Nộp kết quả</em> kèm minh chứng →
            người kiểm (khác người làm) <em>Duyệt hoàn thành</em> hoặc <em>Trả bổ sung</em>.
            <b>Khi nào tính:</b> việc đã được xác nhận, hoặc đã quá hạn mà chưa có bản được chấp nhận.
            Mốc đo: {{ $hcnsRow['measure_label'] }} so với hạn — không dùng giờ duyệt.
            <span class="text-muted">{{ $hcnsRules['status'] === 'applied' ? $hcnsRules['label'] : 'Quy tắc nháp, chưa duyệt — chỉ xem trước, chưa phải điểm chính thức.' }}</span>
        </div>
    </div>
</div>

@once
    @push('styles')
        <style>
            .hcns-kpi-pill{display:inline-block;border-radius:999px;padding:2px 10px;font-size:12px;font-weight:700;border:1px solid transparent}
            .hcns-kpi-pill.is-ok{background:#e8f7ee;color:#15803d;border-color:#c3e8d1}
            .hcns-kpi-pill.is-bad{background:#fdecec;color:#b91c1c;border-color:#f5c9c9}
            .hcns-kpi-pill.is-wait{background:#e9f6fb;color:#0e6a8a;border-color:#c6e6f2}
            .hcns-kpi-pill.is-warn{background:#fff6e5;color:#9a5b00;border-color:#f3dcae}
            .hcns-kpi-pill.is-muted{background:#f1f4f8;color:#556274;border-color:#e1e7ef}
            .hcns-show__reason{font-size:13px;color:#334155}
            .hcns-show__how{font-size:12.5px;color:#475569;background:#f8fafc;border:1px dashed #d3dce8;border-radius:10px;padding:8px 10px}
        </style>
    @endpush
@endonce
