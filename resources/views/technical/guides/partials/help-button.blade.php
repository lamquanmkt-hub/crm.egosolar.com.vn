{{--
    Nút "Cách sử dụng" đặt ở cụm nút đầu trang của các màn hình nghiệp vụ.

    Cố ý dùng `btn-light` + viền nhạt và đặt NGOÀI CÙNG BÊN TRÁI cụm CTA để
    không cạnh tranh thị giác với nút nghiệp vụ chính (màu primary).
    Desktop: icon + chữ. Mobile (<576px): chỉ icon, vẫn giữ vùng chạm ~40px và
    có `aria-label`/`title` để đọc màn hình vẫn hiểu.

    Tham số:
      $slug  slug bài hướng dẫn tương ứng với trang đang mở

    `?return=` luôn là đường dẫn NỘI BỘ (path + query của request hiện tại);
    controller vẫn validate lại lần nữa trước khi dùng làm href.
--}}
@php
    $tgSlug = (string) ($slug ?? '');
    $tgUrl = null;

    if ($tgSlug !== '' && \Illuminate\Support\Facades\Route::has('technical.guides.show')) {
        $tgReturn = request()->getRequestUri();          // đã là path + query, không có host
        $tgUrl = route('technical.guides.show', ['slug' => $tgSlug]).'?return='.rawurlencode($tgReturn);
    }
@endphp

@once
    @push('styles')
        <link rel="stylesheet"
              href="{{ asset('css/ego-technical-guide.css') }}?v={{ file_exists(public_path('css/ego-technical-guide.css')) ? filemtime(public_path('css/ego-technical-guide.css')) : '1.0.0' }}">
    @endpush
@endonce

@if($tgUrl)
    <a href="{{ $tgUrl }}"
       class="btn btn-light btn-sm border tg-help-btn"
       data-tg-help="{{ $tgSlug }}"
       title="Cách sử dụng trang này"
       aria-label="Cách sử dụng trang này">
        <i class="bi bi-question-circle"></i><span class="tg-help-btn__text">Cách sử dụng</span>
    </a>
@endif
