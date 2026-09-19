{{-- EGO_MATERIAL_STEP_WORKSPACE_WAREHOUSE_FIRST_V1 --}}
@php
    $materialStepStatus = (string) ($materialRequest->status ?? '');
    $isWarehouseChecking = in_array($materialStepStatus, ['warehouse_check', 'preparing'], true);
    $isPendingManagerApproval = $materialStepStatus === 'pending_manager';
    $isMaterialRevision = $materialStepStatus === 'revision';
    $isApprovedForIssue = $materialStepStatus === 'approved';
    $isIssued = $materialStepStatus === 'issued';
@endphp

<article class="pt-card pt-material-review-card" data-material-review-workspace>
    <header class="pt-material-review-card__bar">
        <div>
            <i class="bi bi-shield-check"></i>
            <strong>Kiểm tra tồn kho & phê duyệt vật tư</strong>
        </div>
        <span>
            @if($isWarehouseChecking) KHO ĐANG KIỂM TRA
            @elseif($isPendingManagerApproval) CHỜ QUẢN LÝ PHÊ DUYỆT
            @elseif($isApprovedForIssue) ĐÃ DUYỆT XUẤT KHO
            @elseif($isIssued) ĐÃ XUẤT KHO
            @else THEO DÕI TRẠNG THÁI
            @endif
        </span>
    </header>

    <div class="pt-material-review-card__body">
        @if($isWarehouseChecking)
            <div class="ego-wh-lock-message">
                <i class="bi bi-box-seam"></i>
                <div>
                    <strong>Kho đang kiểm tra tồn đúng mã hàng Kỹ thuật đã đề nghị</strong>
                    <p>Kho chỉ cập nhật tồn, số lượng chuẩn bị, vị trí kho và trạng thái. Kho không được đổi thương hiệu, model hoặc chủng loại.</p>
                    @if($can['warehouse'])
                        <a href="{{ route('project-test.warehouse.show', $materialRequest) }}" class="pt-btn pt-btn--brand" style="margin-top:10px">
                            <i class="bi bi-box-arrow-up-right"></i> Mở màn hình kiểm tra tồn kho
                        </a>
                    @endif
                </div>
            </div>
        @elseif($isPendingManagerApproval)
            @if($can['admin'])
                <div class="pt-section__head pt-material-review-card__head">
                    <div>
                        <h2>Quản lý phê duyệt chuyển xuất kho</h2>
                        <p>Kiểm tra đúng mã hàng, số lượng, tồn khả dụng, vị trí kho và giá vốn Kho đã cập nhật.</p>
                    </div>
                    <span class="pt-status">Chờ Quản lý duyệt</span>
                </div>
                <form
                    method="POST"
                    action="{{ route('project-test.materials.review', [$project, $materialRequest]) }}"
                    class="pt-form-grid pt-material-review-form"
                    data-confirm="Xác nhận quyết định đối với phiếu vật tư này?"
                >
                    @csrf
                    <div>
                        <label class="pt-label">Quyết định</label>
                        <select class="pt-select" name="decision" required>
                            <option value="approve">Phê duyệt · Chuyển xuất kho</option>
                            <option value="return_warehouse">Trả Kho kiểm tra lại</option>
                            <option value="return_technical">Trả Sales/Kỹ thuật điều chỉnh</option>
                        </select>
                    </div>
                    <div>
                        <label class="pt-label">Phiếu</label>
                        <input class="pt-input" value="{{ $materialRequest->code }}" disabled>
                    </div>
                    <div class="pt-field--full">
                        <label class="pt-label">Ghi chú / lý do trả lại</label>
                        <textarea class="pt-textarea" name="review_note" placeholder="Bắt buộc nhập khi trả lại Kho hoặc Sales/Kỹ thuật."></textarea>
                    </div>
                    <div class="pt-field--full pt-material-review-card__actions">
                        <button class="pt-btn pt-btn--brand" type="submit">
                            <i class="bi bi-shield-check"></i> Xác nhận quyết định
                        </button>
                    </div>
                </form>
            @else
                <div class="ego-wh-lock-message">
                    <i class="bi bi-hourglass-split"></i>
                    <div>
                        <strong>Phiếu đang chờ Quản lý phê duyệt</strong>
                        <p>Kho đã hoàn tất kiểm tra tồn và tạm khóa cập nhật cho tới khi Quản lý xử lý.</p>
                    </div>
                </div>
            @endif
        @elseif($isMaterialRevision)
            <div class="pt-alert">
                <strong><i class="bi bi-arrow-counterclockwise"></i> Quản lý đã trả Sales/Kỹ thuật điều chỉnh.</strong>
                <div class="pt-material-review-note">{{ $materialRequest->review_note ?: 'Chưa có nội dung phản hồi.' }}</div>
            </div>
        @elseif($isApprovedForIssue)
            <div class="pt-alert pt-alert--success pt-material-approved-state">
                <i class="bi bi-check-circle"></i>
                <div>
                    <strong>Quản lý đã phê duyệt chuyển xuất kho.</strong>
                    <p>{{ $materialRequest->review_note ?: 'Kho được phép giữ hàng và thực hiện xuất kho.' }}</p>
                </div>
            </div>
            @if($can['warehouse'])
                <div class="pt-material-review-card__actions" style="margin-top:12px">
                    <a href="{{ route('project-test.warehouse.show', $materialRequest) }}" class="pt-btn pt-btn--brand">
                        <i class="bi bi-box-arrow-up-right"></i> Mở bước xuất kho
                    </a>
                </div>
            @endif
        @elseif($isIssued)
            <div class="pt-alert pt-alert--success"><strong>Phiếu đã xuất kho.</strong></div>
        @else
            <div class="pt-alert pt-alert--info">Phiếu vật tư đang ở trạng thái <strong>{{ $materialStepStatus ?: 'chưa xác định' }}</strong>.</div>
        @endif
    </div>
</article>
