@php
    $wxActionLabels = [
        'received' => 'Tiếp nhận', 'eligibility_check' => 'Kiểm tra serial & bảo hành', 'create' => 'Tạo đề xuất', 'exception_requested' => 'Đề nghị ngoại lệ',
        'approve' => 'Duyệt', 'approve_override' => 'Duyệt (override)', 'to_warehouse' => 'Chuyển Kho', 'request_info' => 'Yêu cầu bổ sung', 'reject' => 'Từ chối',
        'resubmit' => 'Gửi duyệt lại', 'reopen' => 'Mở lại', 'cancel' => 'Hủy phiếu', 'reserve' => 'Kho giữ hàng', 'swap_reservation' => 'Đổi serial giữ hàng',
        'release_reservation' => 'Nhả hàng', 'release_reservation_detail' => 'Nhả giữ hàng (chi tiết)', 'issue' => 'Kho xuất kho', 'tech_receive' => 'Kỹ thuật nhận hàng',
        'start_replacing' => 'Bắt đầu thay', 'confirm_replaced' => 'Xác nhận đã thay', 'faulty_return' => 'Thu hồi thiết bị lỗi', 'faulty_return_late' => 'Thu hồi muộn',
        'faulty_return_deferred' => 'Hoãn thu hồi', 'complete' => 'Hoàn tất', 'evidence_upload' => 'Tải minh chứng', 'evidence_delete' => 'Gỡ minh chứng',
        'diagnosis' => 'Chẩn đoán', 'quotation_create' => 'Tạo báo giá', 'quotation_update' => 'Sửa báo giá nháp', 'quotation_new_version' => 'Báo giá version mới',
        'quotation_revise' => 'Lập lại báo giá', 'quotation_send' => 'Gửi báo giá', 'customer_approved' => 'Khách đồng ý', 'customer_rejected' => 'Khách từ chối',
        'parts_reserve' => 'Giữ linh kiện', 'parts_issue' => 'Xuất linh kiện', 'parts_return' => 'Hoàn kho linh kiện', 'parts_release' => 'Nhả linh kiện',
        'repair_start' => 'Bắt đầu sửa', 'repair_restart' => 'Sửa lại sau QA', 'repair_update' => 'Cập nhật sửa chữa', 'qa_submit' => 'Chuyển kiểm tra', 'qa_pass' => 'QA đạt',
        'qa_fail' => 'QA không đạt', 'handover' => 'Bàn giao',
    ];
@endphp
<ul class="wx2-hist">
    @forelse($history as $h)
        <li>
            <b>{{ $wxActionLabels[$h->action] ?? $h->action }}</b>
            @if($h->from_status || $h->to_status)<span class="meta"> · {{ $h->from_status ?: '—' }} → {{ $h->to_status ?: '—' }}</span>@endif
            <div class="meta">{{ $h->user_name ?: 'Hệ thống' }} · {{ \Illuminate\Support\Carbon::parse($h->created_at)->format('d/m/Y H:i') }}@if($h->ip_address) · IP {{ $h->ip_address }}@endif</div>
            @if($h->reason)<div class="rs">Lý do: {{ $h->reason }}</div>@endif
        </li>
    @empty
        <li class="meta">Chưa có lịch sử.</li>
    @endforelse
</ul>
