{{--
    KPI HC07 — kiểm tra phân loại/luân chuyển của một hồ sơ giao nhận.
    Người xử lý: Lưu nháp / Gửi kiểm tra. Người kiểm (khác người xử lý): chấm 3 mục, xác nhận hoặc trả bổ sung.
--}}
@php
    $kpiStatus = $kpiReview->status ?? 'none';
    $kpiFmt = fn ($t) => $t ? \Carbon\Carbon::parse($t)->format('H:i d/m/Y') : '—';
    $kpiStateLabel = [
        'earned' => ['Kiểm tra đạt — được tính', 'ok'],
        'lost' => ['Kiểm tra không đạt', 'bad'],
        'pending' => ['Chờ kiểm tra / gửi lại', 'wait'],
        'missing' => ['Chưa gửi kiểm tra', 'warn'],
    ][$kpiRow['kpi_state'] ?? 'missing'] ?? ['—', 'muted'];
    $kpiCanEditDraft = $kpiIsProcessor && in_array($kpiStatus, ['none', 'draft', 'returned', 'changed'], true);
@endphp
<div class="hs-card hs-kpi-card" style="grid-column:1 / -1" id="kpi-hc07" data-kpi-review-status="{{ $kpiStatus }}">
    <div class="hs-card-head">
        <h3 class="hs-card-title">Kiểm tra phân loại & luân chuyển · KPI HC07</h3>
        <span class="hs-kpi-pill is-{{ $kpiStateLabel[1] }}">{{ $kpiStateLabel[0] }}</span>
    </div>
    <div class="hs-card-body">
        <div class="hs-info-grid">
            <div class="hs-info"><div class="hs-info-label">Người xử lý (chịu KPI)</div><div class="hs-info-value">{{ $kpiRow['owner_name'] ?? '—' }}</div></div>
            <div class="hs-info"><div class="hs-info-label">Người kiểm</div><div class="hs-info-value">{{ $kpiRow['reviewer_name'] ?? 'Chưa chọn' }}</div></div>
            <div class="hs-info"><div class="hs-info-label">Phân loại</div><div class="hs-info-value">{{ $kpiRow['classification'] ?? '—' }}</div></div>
            <div class="hs-info"><div class="hs-info-label">Luân chuyển đến</div><div class="hs-info-value">{{ trim($kpiRow['routed_to'] ?? '') ?: '—' }}</div></div>
            <div class="hs-info"><div class="hs-info-label">Trạng thái kiểm tra</div><div class="hs-info-value">{{ $kpiRow['review_label'] ?? '—' }}</div></div>
            <div class="hs-info"><div class="hs-info-label">Gửi lần đầu · số lần</div><div class="hs-info-value">{{ $kpiFmt($kpiReview->first_submitted_at ?? null) }} · {{ (int) ($kpiReview->submission_count ?? 0) }}</div></div>
            <div class="hs-info"><div class="hs-info-label">Người/giờ xác nhận</div><div class="hs-info-value">{{ $kpiRow['confirmed_by_name'] ? $kpiRow['confirmed_by_name'].' · '.$kpiFmt($kpiReview->confirmed_at) : '—' }}</div></div>
            <div class="hs-info"><div class="hs-info-label">Kết quả từng mục</div><div class="hs-info-value">
                @forelse($kpiRow['checks'] ?? [] as $key => $value)
                    <span class="hs-kpi-check is-{{ $value }}">{{ $documentChecks[$key] ?? $key }}: {{ $value === 'pass' ? 'Đạt' : 'Không đạt' }}</span>
                @empty — @endforelse
            </div></div>
        </div>
        <p class="hs-kpi-reason"><strong>KPI:</strong> {{ $kpiRow['reason'] ?? '' }}</p>
        @if($kpiStatus === 'returned' && $kpiReview->return_reason)
            <div class="hs-kpi-alert"><strong>Trả bổ sung:</strong> {{ $kpiReview->return_reason }}</div>
        @endif

        @if($kpiCanEditDraft)
            <form method="POST" class="hs-kpi-form" data-kpi-processor-form>
                @csrf
                <label>Ghi chú cho người kiểm (không bắt buộc)</label>
                <textarea class="hs-textarea" name="note" maxlength="2000" placeholder="VD: đã chuyển bản gốc cho Kế toán, bản sao lưu tủ HS A"></textarea>
                @if($kpiMissing !== [])
                    <div class="hs-kpi-alert">Còn thiếu để gửi kiểm: {{ implode(', ', $kpiMissing) }}. Bấm <b>Sửa hồ sơ</b> để bổ sung.</div>
                @endif
                <div class="hs-kpi-actions">
                    <button class="hs-btn soft" type="submit" formaction="{{ route('hr.document-handovers.kpi.draft', $item->id) }}">Lưu nháp</button>
                    <button class="hs-btn dark" type="submit" formaction="{{ route('hr.document-handovers.kpi.submit', $item->id) }}" @disabled($kpiMissing !== [])>Gửi kiểm tra</button>
                </div>
            </form>
        @elseif($kpiIsProcessor && $kpiStatus === 'submitted')
            <div class="hs-muted">Đã gửi, chờ người kiểm. Nếu sửa nội dung hoặc tệp, bản đã gửi hết hiệu lực và cần gửi lại.</div>
        @endif

        @if($kpiCanReview && in_array($kpiStatus, ['submitted', 'confirmed'], true))
            <div class="hs-kpi-review">
                @if($kpiStatus === 'submitted')
                    <form method="POST" action="{{ route('hr.document-handovers.kpi.confirm', $item->id) }}" class="hs-kpi-form" data-kpi-confirm-form>
                        @csrf
                        <h4>Người kiểm chấm từng mục</h4>
                        @foreach($documentChecks as $key => $label)
                            <div class="hs-kpi-row">
                                <span>{{ $label }}</span>
                                <label><input type="radio" name="checks[{{ $key }}]" value="pass" required> Đạt</label>
                                <label><input type="radio" name="checks[{{ $key }}]" value="fail"> Không đạt</label>
                            </div>
                        @endforeach
                        <textarea class="hs-textarea" name="note" maxlength="2000" placeholder="Ghi rõ lỗi nếu có mục không đạt"></textarea>
                        <button class="hs-btn dark" type="submit">Xác nhận kết quả kiểm tra</button>
                    </form>
                @endif
                <form method="POST" action="{{ route('hr.document-handovers.kpi.return', $item->id) }}" class="hs-kpi-form" data-kpi-return-form>
                    @csrf
                    <h4>Trả bổ sung</h4>
                    <textarea class="hs-textarea" name="reason" required minlength="5" maxlength="2000" placeholder="Nội dung cần sửa: phân loại, nơi nhận, thiếu giấy tờ…"></textarea>
                    <button class="hs-btn danger" type="submit">Trả bổ sung</button>
                </form>
            </div>
        @elseif(! $kpiIsProcessor && ! $kpiCanReview)
            <div class="hs-muted">Bạn chỉ xem. Người xử lý gửi kiểm; người kiểm được chỉ định hoặc Ban Giám đốc xác nhận.</div>
        @endif

        <div class="hs-kpi-how">
            <b>Ai làm gì:</b> người xử lý chọn phân loại, nơi nhận → <em>Gửi kiểm tra</em>; người kiểm (khác người xử lý) chấm
            <em>Phân loại đúng · Luân chuyển đúng nơi nhận · Đủ giấy tờ</em>. <b>Khi nào tính:</b> chỉ hồ sơ đã được kiểm; đạt khi cả 3 mục đạt.
            Trạng thái “Hoàn tất” hay có tệp không tự thành đạt. Kỳ theo tháng gửi kiểm lần đầu — quy tắc nháp, chưa duyệt.
        </div>

        @if($kpiEvents->isNotEmpty())
            <details class="hs-kpi-events"><summary>Lịch sử kiểm tra ({{ $kpiEvents->count() }})</summary>
                @foreach($kpiEvents as $event)
                    <div>{{ $kpiFmt($event->created_at) }} · {{ $event->actor_name ?: 'Hệ thống' }} · {{ [
                        'draft_saved' => 'Lưu nháp', 'submitted' => 'Gửi kiểm tra', 'resubmitted' => 'Gửi lại', 'confirmed' => 'Xác nhận',
                        'returned' => 'Trả bổ sung', 'invalidated' => 'Xác nhận/bản gửi hết hiệu lực do sửa',
                    ][$event->action] ?? $event->action }} (bản {{ $event->revision }})@if($event->note) — {{ $event->note }}@endif</div>
                @endforeach
            </details>
        @endif
    </div>
</div>

@once
<style>
    .hs-kpi-pill{border-radius:999px;padding:4px 11px;font-size:12px;font-weight:900;border:1px solid transparent}
    .hs-kpi-pill.is-ok{background:#e8f7ee;color:#15803d;border-color:#c3e8d1}
    .hs-kpi-pill.is-bad{background:#fdecec;color:#b91c1c;border-color:#f5c9c9}
    .hs-kpi-pill.is-wait{background:#e9f6fb;color:#0e6a8a;border-color:#c6e6f2}
    .hs-kpi-pill.is-warn{background:#fff6e5;color:#9a5b00;border-color:#f3dcae}
    .hs-kpi-pill.is-muted{background:#f1f4f8;color:#556274;border-color:#e1e7ef}
    .hs-kpi-check{display:inline-block;margin:0 4px 4px 0;border-radius:8px;padding:2px 7px;font-size:12px}
    .hs-kpi-check.is-pass{background:#e8f7ee;color:#15803d}.hs-kpi-check.is-fail{background:#fdecec;color:#b91c1c}
    .hs-kpi-reason{margin:12px 0 8px;font-size:13px;color:#334155}
    .hs-kpi-alert{margin:8px 0;padding:10px 12px;border-radius:12px;background:#fff7ed;border:1px solid #fed7aa;color:#9a3412;font-size:13px;font-weight:700}
    .hs-kpi-form{display:grid;gap:8px;margin-top:10px}
    .hs-kpi-form label{font-size:12px;font-weight:900;color:#475569}
    .hs-kpi-form h4{margin:0;font-size:14px;color:#0f3b78}
    .hs-kpi-actions{display:flex;gap:8px;flex-wrap:wrap}
    .hs-kpi-review{display:grid;grid-template-columns:minmax(0,1.3fr) minmax(0,1fr);gap:12px;margin-top:6px}
    .hs-kpi-review .hs-kpi-form{border:1px solid #dbe5f0;border-radius:14px;padding:12px;background:#fbfdff}
    .hs-kpi-row{display:flex;flex-wrap:wrap;gap:12px;align-items:center;font-size:13px}
    .hs-kpi-row span{flex:1 1 180px;font-weight:800}
    .hs-kpi-how{margin-top:12px;font-size:12.5px;color:#475569;background:#f8fafc;border:1px dashed #d3dce8;border-radius:12px;padding:9px 11px}
    .hs-kpi-events{margin-top:10px;font-size:12.5px;color:#475569}
    .hs-kpi-events summary{cursor:pointer;font-weight:800}
    @media(max-width:768px){.hs-kpi-review{grid-template-columns:1fr}}
</style>
@endonce
