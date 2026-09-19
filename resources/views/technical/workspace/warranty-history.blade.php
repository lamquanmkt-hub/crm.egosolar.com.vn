@extends('layouts.app')
@section('title', 'Lịch sử xử lý bảo hành • Phòng Kỹ thuật')
@push('styles')
<link rel="stylesheet" href="{{ asset('css/technical-workspace.css') }}?v={{ file_exists(public_path('css/technical-workspace.css')) ? filemtime(public_path('css/technical-workspace.css')) : time() }}">
@endpush
@section('content')
@php
    $eventLabels = [
        'receive' => 'Nhập/tiếp nhận', 'issue' => 'Xuất thiết bị', 'transfer' => 'Chuyển kho',
        'return_stock' => 'Nhập trả kho', 'warranty_claim' => 'Tạo phiếu bảo hành',
        'remove_from_lookup' => 'Ẩn khỏi tra cứu', 'warranty_update' => 'Cập nhật bảo hành',
    ];
@endphp
<div class="tw-page"><div class="tw-shell">
    <section class="tw-hero">
        <div class="tw-kicker">PHÒNG KỸ THUẬT · BẢO TRÌ &amp; BẢO HÀNH</div>
        <h1>Lịch sử xử lý</h1>
        <p>Audit theo serial: tiếp nhận, xuất kho, chuyển kho, yêu cầu bảo hành và các thay đổi trạng thái.</p>
        @include('technical.workspace.partials-nav')
    </section>

    <section class="tw-subnav">
        <a href="{{ route('technical-workspace.warranty.maintenance') }}"><i class="bi bi-calendar2-week"></i>Lịch O&amp;M</a>
        <a href="{{ route('technical-workspace.warranty.issues') }}"><i class="bi bi-exclamation-diamond"></i>Phiếu sự cố</a>
        <a href="{{ route('technical-workspace.warranty.replacements') }}"><i class="bi bi-arrow-repeat"></i>Thiết bị cần đổi</a>
        <a href="{{ route('technical-workspace.warranty.history') }}" class="active"><i class="bi bi-clock-history"></i>Lịch sử xử lý</a>
    </section>

    <section class="tw-card"><div class="tw-card__body">
        <form method="GET" class="tw-filter">
            <label>Tìm lịch sử<input class="tw-input" type="search" name="q" value="{{ request('q') }}" placeholder="Serial, sản phẩm, khách hàng, đơn hàng..."></label>
            <label>Loại thao tác<select class="tw-select" name="event_type"><option value="">Tất cả</option>@foreach($eventTypes as $type)<option value="{{ $type }}" @selected(request('event_type')===$type)>{{ $eventLabels[$type] ?? str_replace('_',' ', $type) }}</option>@endforeach</select></label>
            <button class="tw-btn"><i class="bi bi-funnel"></i>Lọc</button>
        </form>
    </div></section>

    <section class="tw-card"><div class="tw-table-wrap"><table class="tw-table">
        <thead><tr><th>Thời gian</th><th>Serial/Sản phẩm</th><th>Thao tác</th><th>Trạng thái</th><th>Khách hàng/Đơn hàng</th><th>Người thực hiện</th></tr></thead>
        <tbody>@forelse($events as $event)<tr>
            <td><div class="tw-name">{{ $event->created_at ? \Carbon\Carbon::parse($event->created_at)->format('d/m/Y H:i') : '—' }}</div><div class="tw-sub">#{{ $event->id }}</div></td>
            <td><div class="tw-name">{{ $event->serial_code ?: 'Chưa có serial' }}</div><div class="tw-sub">{{ $event->product_name ?: 'Chưa xác định sản phẩm' }}</div></td>
            <td><span class="tw-pill">{{ $eventLabels[$event->event_type] ?? str_replace('_',' ', $event->event_type) }}</span>@if($event->note)<div class="tw-sub">{{ \Illuminate\Support\Str::limit((string) $event->note,110) }}</div>@endif</td>
            <td>{{ $event->from_state ?: '—' }} <i class="bi bi-arrow-right"></i> {{ $event->to_state ?: '—' }}</td>
            <td><div>{{ $event->customer_name ?: '—' }}</div><div class="tw-sub">{{ $event->order_code ?: 'Chưa liên kết đơn' }}</div></td>
            <td>{{ $event->creator_name ?: 'Hệ thống' }}</td>
        </tr>@empty<tr><td colspan="6"><div class="tw-empty"><i class="bi bi-clock-history"></i><strong>Chưa có lịch sử xử lý</strong><span>Dữ liệu sẽ xuất hiện khi có thao tác trên serial hoặc phiếu bảo hành.</span></div></td></tr>@endforelse</tbody>
    </table></div>@if($events->hasPages())<div class="tw-card__body">{{ $events->links() }}</div>@endif</section>
</div></div>
@endsection
