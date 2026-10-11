@extends('layouts.app')

@section('content')
@php
    $fmt = fn ($t) => $t ? \Carbon\Carbon::parse($t)->format('H:i d/m/Y') : '—';
    $dt = fn ($t) => $t ? \Carbon\Carbon::parse($t)->format('Y-m-d\TH:i') : '';
    $statusLabel = ['planned' => 'Đã lập kế hoạch', 'in_progress' => 'Đang kiểm kê', 'completed' => 'Đã báo hoàn tất', 'cancelled' => 'Đã hủy'];
    $cancelled = $inv->status === 'cancelled';
    $reviewStatus = $review->status ?? 'none';
    $canWriteItems = $isHandler && ! $locked && ! $cancelled;
    $logLabels = ['created' => 'Lập đợt', 'plan_updated' => 'Sửa kế hoạch', 'items_saved' => 'Ghi kết quả', 'file_added' => 'Thêm tệp',
        'file_deleted' => 'Xóa tệp', 'completed' => 'Báo hoàn tất + gửi kiểm', 'cancelled' => 'Hủy đợt', 'review_invalidated' => 'Xác nhận/bản gửi hết hiệu lực'];
@endphp
<style>
    .ik-wrap{padding:0 4px 34px;font-family:"Be Vietnam Pro",system-ui,-apple-system,"Segoe UI",sans-serif}
    .ik-head{display:flex;justify-content:space-between;align-items:flex-end;gap:12px;flex-wrap:wrap;margin-bottom:12px}
    .ik-head h1{margin:0;font-size:22px;font-weight:950;color:#0f172a}
    .ik-head p{margin:4px 0 0;font-size:13px;color:#64748b;font-weight:700}
    .ik-btn{height:36px;border-radius:11px;padding:0 13px;border:1px solid transparent;display:inline-flex;align-items:center;gap:6px;font-size:12px;font-weight:900;text-decoration:none;cursor:pointer;background:#0f766e;color:#fff}
    .ik-btn.outline{background:#fff;color:#0f172a;border-color:#dbe3ef}
    .ik-btn.danger{background:#fff1f2;color:#be123c;border-color:#fecdd3}
    .ik-card{background:#fff;border:1px solid #e2e8f0;border-radius:18px;box-shadow:0 12px 28px rgba(15,23,42,.05);margin-bottom:14px;padding:14px 16px}
    .ik-card h2{margin:0 0 8px;font-size:15px;font-weight:950;color:#0f172a}
    .ik-alert{margin-bottom:12px;padding:10px 12px;border-radius:14px;font-size:13px;font-weight:800}
    .ik-alert.ok{border:1px solid #bbf7d0;background:#f0fdf4;color:#166534}
    .ik-alert.err{border:1px solid #fecaca;background:#fff1f2;color:#be123c}
    .ik-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:10px}
    .ik-field{display:flex;flex-direction:column;gap:5px}
    .ik-field.full{grid-column:1/-1}
    .ik-field label{font-size:12px;font-weight:900;color:#475569}
    .ik-input{width:100%;height:38px;border:1px solid #dbe3ef;border-radius:11px;padding:0 10px;font-size:13px;background:#fff}
    textarea.ik-input{height:auto;min-height:38px;padding:8px 10px}
    .ik-table-wrap{border:1px solid #e2e8f0;border-radius:14px;overflow:auto}
    .ik-table{width:100%;border-collapse:collapse;min-width:760px}
    .ik-table th{background:#f1f5f9;font-size:11px;text-transform:uppercase;font-weight:950;color:#334155;padding:9px;text-align:left}
    .ik-table td{padding:8px 9px;border-top:1px solid #edf2f7;font-size:13px;vertical-align:top}
    .ik-pill{display:inline-block;border-radius:999px;padding:2px 9px;font-size:11.5px;font-weight:900;background:#f1f5f9;color:#334155}
    .ik-pill.ok{background:#f0fdf4;color:#15803d}.ik-pill.warn{background:#fffbeb;color:#b45309}.ik-pill.bad{background:#fff1f2;color:#be123c}
    .ik-logs div{font-size:12.5px;color:#475569;padding:3px 0;border-bottom:1px dashed #eef2f7}
    .ik-items-mobile{display:none}
    @media(max-width:760px){
        .ik-grid{grid-template-columns:1fr}
        .ik-table-wrap.ik-items{display:none}
        .ik-items-mobile{display:grid;gap:8px}
        .ik-item-card{border:1px solid #e2e8f0;border-radius:12px;padding:10px;display:grid;gap:6px}
        .ik-btn{width:100%;justify-content:center}
    }
</style>

<div class="ik-wrap" data-inventory-id="{{ $inv->id }}">
    <div class="ik-head">
        <div>
            <h1>{{ $inv->code }} · {{ $inv->title }}</h1>
            <p>Kỳ {{ substr($inv->period_month, 5, 2) }}/{{ substr($inv->period_month, 0, 4) }} · {{ \App\Services\Hr\Kpi\AssetInventories::SCOPES[$inv->scope] ?? $inv->scope }} · {{ $statusLabel[$inv->status] ?? $inv->status }}</p>
        </div>
        <a class="ik-btn outline" href="{{ route('hr.operations.index', ['tab' => 'assets']) }}#kiem-ke">← Tài sản công ty</a>
    </div>

    @if(session('success'))<div class="ik-alert ok">{{ session('success') }}</div>@endif
    @if(session('error'))<div class="ik-alert err">{{ session('error') }}</div>@endif
    @if($errors->any())<div class="ik-alert err">{{ $errors->first() }}</div>@endif
    @if($cancelled)
        <div class="ik-alert err" data-ik-cancelled>Đã hủy {{ $fmt($inv->cancelled_at) }} bởi {{ $names[(int) $inv->cancelled_by] ?? '—' }}: {{ $inv->cancel_reason }}</div>
    @endif

    @include('hr.kpi._source-review-panel', ['p' => [
        'id' => 'kpi-hc06',
        'title' => 'KPI HC06 · Kiểm kê tài sản theo kỳ',
        'row' => $row, 'review' => $review, 'events' => $events, 'files' => $files, 'missing' => $missing,
        'locked' => $locked, 'isOwner' => $isHandler && ! $cancelled, 'canEdit' => $canEdit && ! $cancelled, 'canReview' => $canReview && ! $cancelled,
        'checks' => $checks,
        'facts' => [
            ['Người phụ trách (chịu KPI)', $names[(int) $inv->handler_id] ?? '—'],
            ['Người kiểm', $names[(int) $inv->reviewer_id] ?? '—'],
            ['Hạn hoàn tất', $fmt($inv->due_at)],
            ['Bắt đầu ghi kết quả', $fmt($inv->started_at)],
            ['Báo hoàn tất (lần gửi gần nhất)', $fmt($inv->completed_at)],
            ['Kết quả đã ghi', $items->whereNotNull('result')->count().'/'.$items->count().' tài sản'],
        ],
        'routes' => [
            'draft' => route('hr.asset-inventories.kpi.draft', $inv->id),
            'submit' => route('hr.asset-inventories.kpi.submit', $inv->id),
            'confirm' => route('hr.asset-inventories.kpi.confirm', $inv->id),
            'return' => route('hr.asset-inventories.kpi.return', $inv->id),
            'upload' => route('hr.asset-inventories.files.upload', $inv->id),
        ],
        'fileUrl' => fn ($f, $download) => route('hr.asset-inventories.files.show', [$inv->id, $f->id]).($download ? '?download=1' : ''),
        'deleteUrl' => fn ($f) => route('hr.asset-inventories.files.delete', [$inv->id, $f->id]),
        'ownerHint' => 'Người phụ trách ghi kết quả và gửi kiểm; người kiểm được chỉ định hoặc Ban Giám đốc xác nhận.',
        'how' => '<b>Ai làm gì:</b> người phụ trách ghi kết quả <em>từng</em> tài sản, tải biên bản/ảnh → <em>Gửi kiểm tra</em> (= báo hoàn tất); '
            .'người kiểm (khác người phụ trách) chấm <em>Kết quả đúng thực tế · Có biên bản/ảnh</em>. '
            .'<b>Khi nào tính:</b> đã được kiểm, cả 2 mục đạt và lần gửi được xác nhận ≤ hạn. Còn hạn: chưa tính; quá hạn chưa hoàn tất: không đạt; hủy trước hạn: loại. '
            .'<b>Chưa tính điểm HC06</b> khi cách gộp ba nhóm (H-D04) chưa chốt — đây là số đo nhóm kiểm kê.',
    ]])

    <div class="ik-card" data-ik-items>
        <h2>Kết quả từng tài sản ({{ $items->count() }})</h2>
        @if($canWriteItems)
            <form method="POST" action="{{ route('hr.asset-inventories.items', $inv->id) }}" id="ikItemsForm">@csrf</form>
        @endif
        <div class="ik-table-wrap ik-items">
            <table class="ik-table">
                <thead><tr><th>Mã</th><th>Tài sản</th><th>Người giữ/vị trí trên sổ</th><th>Kết quả</th><th>Ghi chú</th><th>Ghi lúc</th></tr></thead>
                <tbody>
                @foreach($items as $it)
                    <tr data-ik-item="{{ $it->id }}">
                        <td>{{ $it->asset_code ?: '—' }}</td>
                        <td><b>{{ $it->asset_name }}</b></td>
                        <td>{{ $it->expected_holder ?: '—' }}</td>
                        <td>
                            @if($canWriteItems)
                                <select class="ik-input" name="result[{{ $it->id }}]" form="ikItemsForm">
                                    <option value="">— Chưa ghi —</option>
                                    @foreach($results as $k => $label)<option value="{{ $k }}" @selected($it->result === $k)>{{ $label }}</option>@endforeach
                                </select>
                            @else
                                <span class="ik-pill {{ ['ok' => 'ok', 'moved' => 'warn', 'damaged' => 'bad', 'missing' => 'bad'][$it->result] ?? '' }}">{{ $results[$it->result] ?? 'Chưa ghi' }}</span>
                            @endif
                        </td>
                        <td>
                            @if($canWriteItems)
                                <input class="ik-input" name="item_note[{{ $it->id }}]" value="{{ $it->note }}" form="ikItemsForm" maxlength="1000" placeholder="Bắt buộc nếu đổi vị trí/hỏng/mất">
                            @else
                                {{ $it->note ?: '—' }}
                            @endif
                        </td>
                        <td>{{ $fmt($it->checked_at) }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        <div class="ik-items-mobile">
            @foreach($items as $it)
                <div class="ik-item-card" data-ik-item-mobile="{{ $it->id }}">
                    <b>{{ $it->asset_code }} · {{ $it->asset_name }}</b>
                    <small>Trên sổ: {{ $it->expected_holder ?: '—' }}</small>
                    @if($canWriteItems)
                        <select class="ik-input" data-ik-mirror="result[{{ $it->id }}]">
                            <option value="">— Chưa ghi —</option>
                            @foreach($results as $k => $label)<option value="{{ $k }}" @selected($it->result === $k)>{{ $label }}</option>@endforeach
                        </select>
                        <input class="ik-input" data-ik-mirror="item_note[{{ $it->id }}]" value="{{ $it->note }}" maxlength="1000" placeholder="Ghi chú">
                    @else
                        <span class="ik-pill">{{ $results[$it->result] ?? 'Chưa ghi' }}</span> {{ $it->note }}
                    @endif
                </div>
            @endforeach
        </div>
        @if($canWriteItems)
            <div style="margin-top:10px"><button class="ik-btn" type="submit" form="ikItemsForm">Lưu kết quả</button>
                <span style="font-size:12px;color:#64748b">Sửa kết quả sau khi đã gửi/kiểm tra → cần gửi lại.</span></div>
        @endif
    </div>

    @if($canEdit && ! $locked && ! $cancelled)
        <details class="ik-card" data-ik-plan @if($errors->hasAny(['title','handler_id','reviewer_id','due_at'])) open @endif>
            <summary style="cursor:pointer;font-weight:900;color:#0f3b78">Sửa kế hoạch (tên, người, hạn) / Hủy đợt</summary>
            <form method="POST" action="{{ route('hr.asset-inventories.update', $inv->id) }}" style="margin-top:10px">
                @csrf @method('PUT')
                <div class="ik-grid">
                    <div class="ik-field"><label>Tên đợt *</label><input class="ik-input" name="title" value="{{ old('title', $inv->title) }}" required></div>
                    <div class="ik-field"><label>Người phụ trách *</label><select class="ik-input" name="handler_id">@foreach($users as $u)<option value="{{ $u->id }}" @selected((int) old('handler_id', $inv->handler_id) === (int) $u->id)>{{ $u->name }}</option>@endforeach</select></div>
                    <div class="ik-field"><label>Người kiểm *</label><select class="ik-input" name="reviewer_id">@foreach($users as $u)<option value="{{ $u->id }}" @selected((int) old('reviewer_id', $inv->reviewer_id) === (int) $u->id)>{{ $u->name }}</option>@endforeach</select></div>
                    <div class="ik-field"><label>Hạn hoàn tất *</label><input class="ik-input" type="datetime-local" name="due_at" value="{{ old('due_at', $dt($inv->due_at)) }}" required></div>
                    <div class="ik-field full"><label>Ghi chú</label><input class="ik-input" name="note" value="{{ old('note', $inv->note) }}"></div>
                </div>
                <div style="margin-top:10px"><button class="ik-btn" type="submit">Lưu kế hoạch</button></div>
            </form>
            @if(! in_array($reviewStatus, ['submitted', 'confirmed'], true))
                <form method="POST" action="{{ route('hr.asset-inventories.cancel', $inv->id) }}" style="margin-top:14px;display:grid;gap:6px" onsubmit="return confirm('Hủy đợt kiểm kê này?')">
                    @csrf
                    <label style="font-size:12px;font-weight:900;color:#be123c">Hủy đợt (bắt buộc ghi lý do)</label>
                    <input class="ik-input" name="cancel_reason" minlength="5" required placeholder="VD: dời sang kỳ sau theo chỉ đạo">
                    <button class="ik-btn danger" type="submit">Hủy đợt kiểm kê</button>
                    <small style="color:#64748b">Hủy trước hạn: loại khỏi KPI. Hủy sau hạn khi chưa hoàn tất: vẫn tính không đạt.</small>
                </form>
            @endif
        </details>
    @endif

    @if($logs->isNotEmpty())
        <div class="ik-card ik-logs" data-ik-logs>
            <h2>Lịch sử đợt kiểm kê ({{ $logs->count() }})</h2>
            @foreach($logs as $log)
                <div>{{ $fmt($log->created_at) }} · {{ $log->actor_name ?: 'Hệ thống' }} · <b>{{ $logLabels[$log->action] ?? $log->action }}</b>@if($log->note) — {{ $log->note }}@endif</div>
            @endforeach
        </div>
    @endif
</div>

@if($canWriteItems)
<script>
(function () {
    // Trên điện thoại dùng thẻ thay bảng: đồng bộ giá trị vào ô của form chính trước khi gửi.
    var form = document.getElementById('ikItemsForm');
    if (!form) return;
    document.querySelectorAll('[data-ik-mirror]').forEach(function (el) {
        ['change', 'input'].forEach(function (evt) {
            el.addEventListener(evt, function () {
                var target = document.querySelector('.ik-items [name="' + el.getAttribute('data-ik-mirror') + '"]');
                if (target) target.value = el.value;
            });
        });
    });
})();
</script>
@endif
@endsection
