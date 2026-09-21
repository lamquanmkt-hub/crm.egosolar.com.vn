@extends('layouts.app')

@section('title', 'Việc của Kho — Bảo hành & Sửa chữa')

@section('styles')
<link rel="stylesheet" href="{{ asset('css/technical-warranty-exchange-v1.css') }}?v={{ file_exists(public_path('css/technical-warranty-exchange-v1.css')) ? filemtime(public_path('css/technical-warranty-exchange-v1.css')) : time() }}">
<link rel="stylesheet" href="{{ asset('css/warranty-workflow-v2.css') }}?v={{ file_exists(public_path('css/warranty-workflow-v2.css')) ? filemtime(public_path('css/warranty-workflow-v2.css')) : time() }}">
@endsection

@section('content')
<div class="wx-page"><div class="wx-shell">
    @include('technical.warranty._tabs')
    <header class="wx-hero" style="padding:14px 18px">
        <div><div class="wx-kicker">KHO · BẢO HÀNH & SỬA CHỮA</div><h1 style="font-size:22px">Việc của Kho</h1>
            <p>Danh sách phiếu đã được duyệt đang chờ Kho: chọn & giữ serial thay thế, xuất kho, thu hồi thiết bị lỗi, xuất/hoàn linh kiện sửa chữa.</p></div>
    </header>

    @if($notes->isNotEmpty())
        <section class="wx2-card"><h2>Thông báo mới ({{ $notes->count() }}) <a class="wx-btn tiny ghost" href="{{ route('ky-thuat.warranty-exchange.warehouse-queue', ['read' => 1]) }}">Đánh dấu đã đọc</a></h2>
            <ul class="wx2-hist">@foreach($notes as $n)<li><b>{{ $n->title }}</b><div class="meta">{{ $n->message }} · {{ \Illuminate\Support\Carbon::parse($n->created_at)->format('d/m/Y H:i') }}</div></li>@endforeach</ul></section>
    @endif

    <section class="wx2-card" style="padding:0;overflow:hidden">
        <div style="padding:12px 16px"><strong>Đổi hàng bảo hành ({{ $exchange->count() }})</strong></div>
        <table class="wx2-table"><thead><tr><th>Mã phiếu</th><th>Serial lỗi</th><th>Trạng thái</th><th>Việc của Kho</th><th>Cập nhật</th><th></th></tr></thead><tbody>
        @forelse($exchange as $c)
            <tr><td>{{ $c->claim_code }}</td><td><code>{{ $c->serial_code }}</code></td>
                <td>{{ $exchangeStatuses[$c->status] ?? $c->status }}</td>
                <td>{{ ['waiting_stock' => 'Chọn serial thay thế & giữ hàng', 'reserved' => 'Xuất kho thiết bị đã giữ', 'waiting_faulty_return' => 'Nhận thiết bị lỗi thu hồi', 'completed' => 'Thu hồi muộn (đã hoãn)'][$c->status] ?? '—' }}
                    @if($c->reserved_serial_code)<small>Đã giữ: {{ $c->reserved_serial_code }}</small>@endif</td>
                <td>{{ $c->status_changed_at ? \Illuminate\Support\Carbon::parse($c->status_changed_at)->format('d/m/Y H:i') : '' }}</td>
                <td><a class="wx-btn tiny primary" href="{{ route('ky-thuat.warranty-exchange.show', $c->id) }}">Xử lý</a></td></tr>
        @empty<tr><td colspan="6" style="color:#64748b">Không có việc đổi hàng nào đang chờ Kho.</td></tr>@endforelse
        </tbody></table>
    </section>

    <section class="wx2-card" style="padding:0;overflow:hidden">
        <div style="padding:12px 16px"><strong>Linh kiện sửa chữa tính phí ({{ $repair->count() }})</strong></div>
        <table class="wx2-table"><thead><tr><th>Mã phiếu</th><th>Serial</th><th>Trạng thái</th><th>Cập nhật</th><th></th></tr></thead><tbody>
        @forelse($repair as $c)
            <tr><td>{{ $c->claim_code }}</td><td><code>{{ $c->serial_code }}</code></td><td>{{ $repairStatuses[$c->status] ?? $c->status }}</td>
                <td>{{ $c->status_changed_at ? \Illuminate\Support\Carbon::parse($c->status_changed_at)->format('d/m/Y H:i') : '' }}</td>
                <td><a class="wx-btn tiny primary" href="{{ route('ky-thuat.repair.show', $c->id) }}">Xử lý</a></td></tr>
        @empty<tr><td colspan="5" style="color:#64748b">Không có phiếu sửa chữa nào cần Kho.</td></tr>@endforelse
        </tbody></table>
    </section>
</div></div>
@endsection
