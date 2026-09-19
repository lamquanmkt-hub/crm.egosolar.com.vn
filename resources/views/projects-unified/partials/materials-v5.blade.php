@php
    $matStatus = [
        'DRAFT'=>'Nháp','SUBMITTED'=>'Chờ duyệt','NEEDS_REVISION'=>'Cần bổ sung','TECHNICAL_APPROVED'=>'Đã duyệt kỹ thuật',
        'PARTIALLY_ALLOCATED'=>'Kho đang xử lý','WAREHOUSE_ALLOCATED'=>'Kho đã chọn hàng','ADMIN_APPROVED'=>'Đã duyệt','READY_FOR_EXPORT'=>'Sẵn sàng xuất','EXPORTED'=>'Đã xuất kho'
    ];
@endphp
<div class="ego-step-unit">
    <div class="ego-step-unit-head">
        <div><span>CÔNG TRÌNH - BƯỚC 3</span><h3>Đề xuất Vật tư</h3><p>Lập danh sách vật tư cần cấp cho công trình và gửi duyệt trước khi chuyển Kho xử lý.</p></div>
        <div class="ego-step-status">{{ $materialProposals->isNotEmpty() ? ($matStatus[(string)$materialProposals->first()->status] ?? $materialProposals->first()->status) : 'Chưa có đề xuất' }}</div>
    </div>

    <section class="ego-form-section">
        <h4>CHỌN NHÂN SỰ PHỤ TRÁCH</h4>
        <div class="ego-person-chips"><span><i class="bi bi-person-check"></i>{{ $project['lead_engineer'] ?? 'Chưa phân công' }}<small>Phụ trách Công trình</small></span></div>
    </section>

    @if($canProposeMaterials)
    <form method="POST" action="{{ route('projects-unified.materials.proposal.store', $site) }}" enctype="multipart/form-data" data-material-form-v5>
        @csrf
        <section class="ego-form-section">
            <h4>DANH SÁCH VẬT TƯ</h4>
            <div class="ego-grid-form cols-3">
                <label><span>Loại đề xuất</span><select name="proposal_type"><option value="INITIAL">Vật tư ban đầu</option><option value="ADDITIONAL">Vật tư phát sinh</option><option value="REPLACEMENT">Vật tư thay thế bảo hành</option></select></label>
                <label><span>Ngày cần</span><input type="date" name="needed_at"></label>
                <label><span>Ưu tiên</span><select name="priority"><option value="normal">Bình thường</option><option value="high">Cao</option><option value="urgent">Khẩn cấp</option></select></label>
                <label class="full"><span>Mục đích / hạng mục</span><input name="purpose" placeholder="Ví dụ: Vật tư thi công hệ thống DC, AC..."></label>
            </div>

            <div class="ego-material-table" data-material-rows>
                <div class="head"><span>Tên vật tư</span><span>Model / Quy cách</span><span>Số lượng</span><span>Đơn vị</span><span>Ghi chú</span><span></span></div>
                <div class="row" data-material-row>
                    <input name="items[0][name]" required placeholder="Tên vật tư">
                    <input name="items[0][spec]" placeholder="Model / quy cách">
                    <input type="number" step="0.01" min="0.01" name="items[0][qty]" required placeholder="SL">
                    <input name="items[0][unit]" required placeholder="tấm / bộ / mét">
                    <input name="items[0][note]" placeholder="Ghi chú">
                    <button type="button" data-remove-material title="Xóa"><i class="bi bi-trash3"></i></button>
                </div>
            </div>
            <button type="button" class="ego-doc-btn soft" data-add-material><i class="bi bi-plus-lg"></i> Thêm vật tư</button>
        </section>

        <section class="ego-form-section">
            <h4>HỒ SƠ ĐỀ XUẤT</h4>
            <div class="ego-grid-form cols-2">
                <label><span>File đính kèm</span><input type="file" name="attachment"></label>
                <label class="full"><span>Ghi chú</span><textarea name="note" rows="3"></textarea></label>
            </div>
            <div class="ego-actions"><button class="ego-doc-btn primary" type="submit"><i class="bi bi-send-check"></i> Gửi duyệt</button></div>
        </section>
    </form>
    @endif

    <section class="ego-form-section">
        <div class="ego-section-title-row"><h4>LỊCH SỬ ĐỀ XUẤT VẬT TƯ</h4><span>{{ $materialProposals->count() }} đề xuất</span></div>
        @forelse($materialProposals as $proposal)
            <div class="ego-proposal-card">
                <div><strong>Đề xuất #{{ $proposal->id }}</strong><small>{{ $proposal->purpose ?: 'Vật tư Công trình' }} · {{ optional($proposal->created_at ? \Illuminate\Support\Carbon::parse($proposal->created_at) : null)->format('d/m/Y H:i') }}</small></div>
                <span class="ego-state-pill">{{ $matStatus[(string)$proposal->status] ?? $proposal->status }}</span>
                <div class="ego-proposal-items">
                    @foreach(($materialProposalItems[$proposal->id] ?? collect()) as $item)
                        <span>{{ $item->requested_name }}{{ $item->requested_spec ? ' · '.$item->requested_spec : '' }} — <b>{{ number_format((float)$item->requested_qty, 2, ',', '.') }} {{ $item->requested_unit }}</b></span>
                    @endforeach
                </div>
                <div class="ego-actions wrap">
                    @if($canApproveMaterials && in_array((string)$proposal->status, ['SUBMITTED','NEEDS_REVISION'], true))
                        <form method="POST" action="{{ route('projects-unified.materials.proposal.approve', [$site,$proposal->id]) }}">@csrf<input name="approval_note" placeholder="Ghi chú"><button class="ego-doc-btn success" type="submit">Duyệt kỹ thuật</button></form>
                        <form method="POST" action="{{ route('projects-unified.materials.proposal.return', [$site,$proposal->id]) }}">@csrf<input name="revision_note" required placeholder="Nội dung cần bổ sung"><button class="ego-doc-btn danger" type="submit">Trả lại</button></form>
                    @endif
                </div>
            </div>
        @empty
            <div class="ego-empty-cell">Chưa có đề xuất vật tư nào.</div>
        @endforelse
    </section>
</div>
