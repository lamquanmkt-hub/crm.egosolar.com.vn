{{--
    Layout dùng chung cho MỘT bài hướng dẫn.

    Mỗi bài `technical/guides/{slug}.blade.php` chỉ cần `@extends` file này và
    đổ nội dung vào section `guide` — 11 phần của bài nằm gọn trong từng file
    riêng, không nhồi vào một view khổng lồ.

    Biến do `TechnicalGuideController@show` truyền xuống:
      $guide      metadata từ config/technical_guides.php
      $role       vai trò của người đang xem (staff|manager|admin)
      $returnUrl  URL "Quay lại trang đang sử dụng" ĐÃ được validate (chống open-redirect)
      $nextGuide  bài kế tiếp cùng nhóm (hoặc null)
--}}
@extends('layouts.app')

@section('title', $guide['title'].' — Hướng dẫn Kỹ thuật')

@push('styles')
    <link rel="stylesheet"
          href="{{ asset('css/ego-technical-guide.css') }}?v={{ file_exists(public_path('css/ego-technical-guide.css')) ? filemtime(public_path('css/ego-technical-guide.css')) : '1.0.0' }}">
@endpush

@php
    $tgRoleLabels = [
        'staff' => ['Nhân viên Kỹ thuật', ''],
        'manager' => ['Trưởng phòng Kỹ thuật', 'is-manager'],
        'admin' => ['Admin / Ban giám đốc', 'is-admin'],
    ];

    /* 11 phần cố định của mọi bài — mục lục bám nội dung ở desktop. */
    $tgToc = [
        'muc-dich' => 'Mục đích',
        'dieu-kien' => 'Điều kiện trước khi thao tác',
        'quy-trinh' => 'Quy trình tổng quan',
        'cac-buoc' => 'Hướng dẫn từng bước',
        'trang-thai' => 'Các trạng thái có thể gặp',
        'luu-y' => 'Lưu ý quan trọng',
        'loi-thuong-gap' => 'Lỗi thường gặp',
    ];
@endphp

@section('content')
<div class="container-fluid py-3 tg-wrap">

    {{-- (1) Breadcrumb --}}
    <nav class="tg-breadcrumb" aria-label="Đường dẫn">
        <a href="{{ route('ky-thuat.tong-quan') }}">Kỹ thuật</a>
        <span class="sep">/</span>
        <a href="{{ route('technical.guides.index') }}">Hướng dẫn</a>
        <span class="sep">/</span>
        <span>{{ $guide['title'] }}</span>
    </nav>

    <div class="tg-layout">
        <div>
            {{-- (2) Tiêu đề bài --}}
            <div class="tg-head">
                <h1 class="tg-head__title">{{ $guide['title'] }}</h1>

                {{-- (3) Đối tượng sử dụng --}}
                <div class="tg-roles mt-2">
                    @foreach(($guide['roles'] ?? []) as $tgRole)
                        @php([$tgLabel, $tgClass] = $tgRoleLabels[$tgRole] ?? [$tgRole, ''])
                        <span class="tg-role-badge {{ $tgClass }}">
                            <i class="bi bi-person-check"></i> {{ $tgLabel }}
                        </span>
                    @endforeach
                    <span class="tg-role-badge">
                        <i class="bi bi-clock"></i> {{ $guide['reading_minutes'] ?? 3 }} phút đọc
                    </span>
                </div>
            </div>

            @yield('guide')

            {{-- (11) Nút cuối bài --}}
            <div class="tg-footer">
                <a href="{{ $returnUrl }}" class="btn btn-primary btn-sm">
                    <i class="bi bi-arrow-left"></i> Quay lại trang đang sử dụng
                </a>
                <a href="{{ route('technical.guides.index') }}" class="btn btn-outline-secondary btn-sm">
                    <i class="bi bi-list-ul"></i> Danh mục hướng dẫn
                </a>
                @if(!empty($nextGuide))
                    <a href="{{ route('technical.guides.show', ['slug' => $nextGuide['slug']]) }}"
                       class="btn btn-outline-primary btn-sm ms-auto">
                        Xem bài tiếp theo: {{ $nextGuide['title'] }} <i class="bi bi-arrow-right"></i>
                    </a>
                @endif
            </div>
        </div>

        {{-- Mục lục bám nội dung (desktop) --}}
        <aside class="tg-toc" aria-label="Mục lục bài hướng dẫn">
            <p class="tg-toc__title">Trong bài này</p>
            <ol>
                @foreach($tgToc as $tgAnchor => $tgLabelToc)
                    <li><a href="#{{ $tgAnchor }}" data-tg-toc="{{ $tgAnchor }}">{{ $tgLabelToc }}</a></li>
                @endforeach
            </ol>
        </aside>
    </div>
</div>

{{-- Lightbox dùng chung: Bootstrap Modal có sẵn, không thư viện mới. --}}
<div class="modal fade" id="tgLightbox" tabindex="-1" aria-labelledby="tgLightboxLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header py-2">
                <h2 class="modal-title fs-6" id="tgLightboxLabel">Ảnh minh hoạ</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Đóng"></button>
            </div>
            <div class="modal-body text-center p-2">
                <img src="" alt="" id="tgLightboxImage" class="img-fluid">
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
(function () {
    'use strict';

    /* Lightbox: nạp đúng ảnh được bấm vào modal dùng chung. */
    var lightbox = document.getElementById('tgLightbox');
    if (lightbox) {
        lightbox.addEventListener('show.bs.modal', function (event) {
            var trigger = event.relatedTarget;
            if (!trigger) { return; }
            var img = document.getElementById('tgLightboxImage');
            var label = document.getElementById('tgLightboxLabel');
            if (img) {
                img.setAttribute('src', trigger.getAttribute('data-tg-src') || '');
                img.setAttribute('alt', trigger.getAttribute('data-tg-alt') || '');
            }
            if (label) {
                label.textContent = trigger.getAttribute('data-tg-alt') || 'Ảnh minh hoạ';
            }
        });
    }

    /* Scrollspy nhẹ cho mục lục — vanilla, không thư viện. */
    var links = Array.prototype.slice.call(document.querySelectorAll('[data-tg-toc]'));
    if (!links.length || !('IntersectionObserver' in window)) { return; }

    var byId = {};
    links.forEach(function (a) { byId[a.getAttribute('data-tg-toc')] = a; });

    var observer = new IntersectionObserver(function (entries) {
        entries.forEach(function (entry) {
            var link = byId[entry.target.id];
            if (!link) { return; }
            if (entry.isIntersecting) {
                links.forEach(function (a) { a.classList.remove('is-active'); });
                link.classList.add('is-active');
            }
        });
    }, { rootMargin: '-88px 0px -65% 0px', threshold: 0 });

    Object.keys(byId).forEach(function (id) {
        var section = document.getElementById(id);
        if (section) { observer.observe(section); }
    });
})();
</script>
@endpush
