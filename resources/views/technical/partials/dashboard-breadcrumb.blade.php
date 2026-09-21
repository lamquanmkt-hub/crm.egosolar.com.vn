{{--
    Breadcrumb dùng chung cho bốn trang của khu Dashboard Kỹ thuật.

    Mỗi trang truyền một $trail KHÁC NHAU, ví dụ:
        ['Kỹ thuật' => route('technical.dashboard'), 'Kế hoạch' => null]
    Phần tử có URL thì thành liên kết; phần tử cuối (URL null) là trang hiện tại.
    Kiểu dáng lấy đúng theo breadcrumb đang dùng ở module Bảo trì (om9-breadcrumb).
--}}
@php
    $trail = (array) ($trail ?? []);
@endphp

<nav class="tp-breadcrumb" aria-label="Đường dẫn">
    <a href="{{ route('dashboard') }}">Trang chủ</a>
    @foreach($trail as $label => $url)
        <i class="bi bi-chevron-right"></i>
        @if($url)
            <a href="{{ $url }}">{{ $label }}</a>
        @else
            <span aria-current="page">{{ $label }}</span>
        @endif
    @endforeach
</nav>
