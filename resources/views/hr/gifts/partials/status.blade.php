@php
    $giftStatusMap = [
        'draft' => ['Nháp', 'muted'],
        'pending' => ['Chờ duyệt', 'warning'],
        'approved' => ['Đã duyệt', 'success'],
        'rejected' => ['Từ chối', 'danger'],
        'cancelled' => ['Đã hủy', 'muted'],
        'preparing' => ['Đang chuẩn bị', 'info'],
        'delivering' => ['Đang giao', 'primary'],
        'delivered' => ['Đã giao', 'success'],
        'failed' => ['Giao thất bại', 'danger'],
        'returned' => ['Đã hoàn kho', 'muted'],
    ];
    [$giftStatusLabel, $giftStatusTone] = $giftStatusMap[$status] ?? [$status, 'muted'];
@endphp
<span class="gift-status gift-status--{{ $giftStatusTone }}">{{ $giftStatusLabel }}</span>
