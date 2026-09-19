@extends('layouts.app')
@section('title', ($replacementOnly ? 'Thiết bị cần đổi' : 'Phiếu sự cố').' • Phòng Kỹ thuật')
@push('styles')
<link rel="stylesheet" href="{{ asset('css/technical-workspace.css') }}?v={{ file_exists(public_path('css/technical-workspace.css')) ? filemtime(public_path('css/technical-workspace.css')) : time() }}">
@endpush
@section('content')
@php
    $title = $replacementOnly ? 'Thiết bị cần đổi' : 'Phiếu sự cố';
    $subtitle = $replacementOnly
        ? 'Tập trung các phiếu chưa hoàn tất có dấu hiệu cần thay/đổi thiết bị để Kỹ thuật phối hợp Kho xử lý.'
        : 'Theo dõi yêu cầu lỗi thiết bị từ lúc tiếp nhận đến khi có phương án và kết quả xử lý.';
    $statusLabels = [
        'received' => 'Mới tiếp nhận', 'processing' => 'Đang xử lý',
        'pending_replacement' => 'Chờ đổi thiết bị', 'replacement_pending' => 'Chờ thiết bị thay thế',
        'approved_replacement' => 'Đã duyệt đổi', 'waiting_device' => 'Đang chờ thiết bị',
        'resolved' => 'Đã xử lý', 'completed' => 'Hoàn tất', 'closed' => 'Đã đóng',
        'rejected' => 'Từ chối', 'cancelled' => 'Đã hủy',
    ];
@endphp
<div class="tw-page"><div class="tw-shell">
    <section class="tw-hero">
        <div class="tw-hero__row">
            <div>
                <div class="tw-kicker">PHÒNG KỸ THUẬT · BẢO TRÌ &amp; BẢO HÀNH</div>
                <h1>{{ $title }}</h1>
                <p>{{ $subtitle }}</p>
            </div>
            @if(\Illuminate\Support\Facades\Route::has('serial-warranty.index'))
                <a class="tw-btn tw-btn--light" href="{{ route('serial-warranty.index') }}"><i class="bi bi-plus-circle"></i>Mở quản lý Serial/Bảo hành</a>
            @endif
        </div>
        @include('technical.workspace.partials-nav')
    </section>

    <section class="tw-subnav">
        <a href="{{ route('technical-workspace.warranty.maintenance') }}"><i class="bi bi-calendar2-week"></i>Lịch O&amp;M</a>
        <a href="{{ route('technical-workspace.warranty.issues') }}" class="{{ !$replacementOnly ? 'active' : '' }}"><i class="bi bi-exclamation-diamond"></i>Phiếu sự cố</a>
        <a href="{{ route('technical-workspace.warranty.replacements') }}" class="{{ $replacementOnly ? 'active' : '' }}"><i class="bi bi-arrow-repeat"></i>Thiết bị cần đổi</a>
        <a href="{{ route('technical-workspace.warranty.history') }}"><i class="bi bi-clock-history"></i>Lịch sử xử lý</a>
    </section>

    <section class="tw-kpis tw-kpis--4">
        <article class="tw-kpi"><span>Phiếu đang mở</span><strong>{{ number_format($summary['open']) }}</strong><small>Chưa đóng hoặc hoàn tất</small></article>
        <article class="tw-kpi"><span>Đang xử lý/Chờ đổi</span><strong>{{ number_format($summary['processing']) }}</strong><small>Cần phối hợp Kỹ thuật - Kho</small></article>
        <article class="tw-kpi"><span>Đã xử lý</span><strong>{{ number_format($summary['resolved']) }}</strong><small>Đã có kết quả cuối</small></article>
        <article class="tw-kpi"><span>Chi phí ghi nhận</span><strong>{{ number_format($summary['cost'],0,',','.') }} đ</strong><small>Chi phí trên phiếu bảo hành</small></article>
    </section>

    <section class="tw-card"><div class="tw-card__body">
        <form method="GET" class="tw-filter">
            <label>Tìm phiếu
                <input class="tw-input" type="search" name="q" value="{{ request('q') }}" placeholder="Serial, sản phẩm, khách hàng, đơn hàng...">
            </label>
            <label>Trạng thái
                <select class="tw-select" name="status">
                    <option value="">Tất cả</option>
                    @foreach($statusLabels as $key => $label)
                        <option value="{{ $key }}" @selected(request('status')===$key)>{{ $label }}</option>
                    @endforeach
                </select>
            </label>
            <button class="tw-btn"><i class="bi bi-funnel"></i>Lọc</button>
        </form>
    </div></section>

    <section class="tw-card"><div class="tw-table-wrap"><table class="tw-table">
        <thead><tr><th>Phiếu/Serial</th><th>Sản phẩm &amp; khách hàng</th><th>Nội dung lỗi</th><th>Trạng thái</th><th>Tiếp nhận/Kết thúc</th><th>Chi phí</th></tr></thead>
        <tbody>
        @forelse($claims as $claim)
            @php
                $statusKey = strtolower((string) $claim->status);
                $isClosed = in_array($statusKey, ['resolved','completed','closed'], true);
            @endphp
            <tr>
                <td><div class="tw-name">#{{ $claim->id }} · {{ $claim->serial_code ?: 'Chưa có serial' }}</div><div class="tw-sub">{{ $claim->order_code ?: 'Chưa liên kết đơn hàng' }}</div></td>
                <td><div class="tw-name">{{ $claim->product_name ?: 'Chưa xác định sản phẩm' }}</div><div class="tw-sub">{{ $claim->customer_name ?: 'Chưa xác định khách hàng' }}</div></td>
                <td><div>{{ \Illuminate\Support\Str::limit((string) $claim->issue_description, 120) ?: 'Chưa có mô tả lỗi' }}</div>@if($claim->resolution)<div class="tw-sub">Xử lý: {{ \Illuminate\Support\Str::limit((string) $claim->resolution, 100) }}</div>@endif</td>
                <td><span class="tw-pill {{ $isClosed ? '' : 'warning' }}">{{ $statusLabels[$statusKey] ?? str_replace('_',' ', $claim->status) }}</span></td>
                <td><div>{{ $claim->received_at ? \Carbon\Carbon::parse($claim->received_at)->format('d/m/Y') : '—' }}</div><div class="tw-sub">{{ $claim->resolved_at ? 'Xong: '.\Carbon\Carbon::parse($claim->resolved_at)->format('d/m/Y') : 'Chưa hoàn tất' }}</div></td>
                <td><div class="tw-name">{{ number_format((float) $claim->cost,0,',','.') }} đ</div><div class="tw-sub">{{ $claim->creator_name ?: 'Không rõ người tạo' }}</div></td>
            </tr>
        @empty
            <tr><td colspan="6"><div class="tw-empty"><i class="bi bi-shield-check"></i><strong>Chưa có dữ liệu phù hợp</strong><span>{{ $replacementOnly ? 'Không có thiết bị nào đang chờ đổi.' : 'Chưa có phiếu sự cố trong bộ lọc này.' }}</span></div></td></tr>
        @endforelse
        </tbody>
    </table></div>
    @if($claims->hasPages())<div class="tw-card__body">{{ $claims->links() }}</div>@endif
    </section>
</div></div>
@endsection
