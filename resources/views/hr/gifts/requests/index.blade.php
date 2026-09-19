@extends('layouts.app')
@section('title', 'Yêu cầu tặng quà')
@push('styles')<link rel="stylesheet" href="{{ asset('css/ego-gifts.css') }}?v={{ file_exists(public_path('css/ego-gifts.css')) ? filemtime(public_path('css/ego-gifts.css')) : '1.0.0' }}">@endpush
@section('content')
<div class="gift-page">
    <div class="gift-page-head"><div><div class="gift-eyebrow">QUẢN LÝ QUÀ TẶNG</div><h1>Yêu cầu tặng / Xuất kho</h1><p>Nhân viên chọn khách hàng CRM, chọn quà và gửi duyệt trước khi xuất kho.</p></div><a class="gift-btn gift-btn--primary" href="{{ route('hr.gifts.requests.create') }}"><i class="bi bi-plus-lg"></i>Tạo yêu cầu</a></div>
    @include('hr.gifts.partials.nav')
    @include('hr.gifts.partials.alerts')
    <section class="gift-card">
        <form class="gift-filter" method="get"><input class="gift-input" name="q" value="{{ request('q') }}" placeholder="Tìm mã phiếu, khách hàng, SĐT..."><select class="gift-select" name="status"><option value="">Tất cả trạng thái</option>@foreach(['draft'=>'Nháp','pending'=>'Chờ duyệt','approved'=>'Đã duyệt','preparing'=>'Đang chuẩn bị','delivering'=>'Đang giao','delivered'=>'Đã giao','failed'=>'Giao thất bại','returned'=>'Đã hoàn kho','rejected'=>'Từ chối','cancelled'=>'Đã hủy'] as $key=>$label)<option value="{{ $key }}" @selected(request('status')===$key)>{{ $label }}</option>@endforeach</select><button class="gift-btn gift-btn--secondary"><i class="bi bi-search"></i>Lọc</button></form>
        <div class="gift-table-wrap"><table class="gift-table"><thead><tr><th>Mã yêu cầu</th><th>Khách hàng</th><th>Quà tặng</th><th>Ngày dự kiến</th><th>Người tạo</th><th>Trạng thái</th><th></th></tr></thead><tbody>@forelse($requests as $giftRequest)<tr><td><a class="gift-code" href="{{ route('hr.gifts.requests.show',$giftRequest) }}">{{ $giftRequest->code }}</a><small>{{ optional($giftRequest->created_at)->format('d/m/Y H:i') }}</small></td><td><strong>{{ $giftRequest->customer_name }}</strong><small>{{ $giftRequest->customer_phone ?: 'Chưa có SĐT' }}</small></td><td>{{ $giftRequest->items->map(fn($item)=>($item->gift->name??'Quà').' × '.number_format((float)$item->quantity,3,',','.'))->join(', ') }}</td><td>{{ optional($giftRequest->expected_delivery_date)->format('d/m/Y') ?: '—' }}</td><td>{{ $giftRequest->creator->name ?? '—' }}</td><td>@include('hr.gifts.partials.status',['status'=>$giftRequest->status])</td><td class="text-end"><a class="gift-icon-btn" href="{{ route('hr.gifts.requests.show',$giftRequest) }}"><i class="bi bi-eye"></i></a></td></tr>@empty<tr><td colspan="7" class="gift-empty">Chưa có yêu cầu tặng quà.</td></tr>@endforelse</tbody></table></div><div class="gift-pagination">{{ $requests->links() }}</div>
    </section>
</div>
@endsection
