{{--
    Ảnh demo có thể bấm để phóng to.

    Lightbox dùng ĐÚNG Bootstrap Modal đã có sẵn trong layout `layouts.app`
    (Bootstrap 5.3) — KHÔNG thêm thư viện lightbox, KHÔNG tải CDN mới.
    Modal dùng chung nằm ở cuối `technical/guides/layout.blade.php`.

    Tham số:
      $src      đường dẫn tương đối trong public/, ví dụ 'images/guides/technical/xxx.png'
      $alt      mô tả tiếng Việt (bắt buộc — đọc màn hình và khi ảnh lỗi)
      $caption  chú thích hiển thị dưới ảnh (tuỳ chọn)
--}}
@php
    $tgSrc = (string) ($src ?? '');
    $tgAlt = (string) ($alt ?? '');
    $tgCaption = (string) ($caption ?? '');
    $tgExists = $tgSrc !== '' && file_exists(public_path($tgSrc));
@endphp

<figure class="tg-shot">
    @if($tgExists)
        <button type="button"
                class="tg-shot__frame"
                data-bs-toggle="modal"
                data-bs-target="#tgLightbox"
                data-tg-src="{{ asset($tgSrc) }}"
                data-tg-alt="{{ $tgAlt }}"
                title="Bấm để phóng to ảnh">
            <img src="{{ asset($tgSrc) }}?v={{ filemtime(public_path($tgSrc)) }}"
                 alt="{{ $tgAlt }}"
                 class="img-fluid"
                 loading="lazy">
        </button>
    @else
        <div class="tg-shot__missing">
            <i class="bi bi-image"></i> Ảnh minh hoạ chưa sẵn sàng: {{ $tgAlt !== '' ? $tgAlt : $tgSrc }}
        </div>
    @endif

    @if($tgCaption !== '')
        <figcaption class="tg-shot__caption">
            <i class="bi bi-info-circle"></i><span>{{ $tgCaption }}</span>
        </figcaption>
    @endif
</figure>
