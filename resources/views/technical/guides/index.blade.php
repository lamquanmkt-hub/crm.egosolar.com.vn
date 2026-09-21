{{--
    Danh mục hướng dẫn module Kỹ thuật.

    Danh sách đã được LỌC THEO QUYỀN ở controller/registry trước khi render:
    nhân viên không hề có card nhóm Trưởng phòng / Admin trong HTML (không phải
    chỉ ẩn bằng CSS), và backend vẫn chặn 403 nếu họ gõ thẳng slug quản lý.

    Ô tìm nhanh chỉ LỌC CLIENT-SIDE trên danh sách đã render — không gọi API,
    không thêm dependency.
--}}
@extends('layouts.app')

@section('title', 'Hướng dẫn sử dụng module Kỹ thuật')

@push('styles')
    <link rel="stylesheet"
          href="{{ asset('css/ego-technical-guide.css') }}?v={{ file_exists(public_path('css/ego-technical-guide.css')) ? filemtime(public_path('css/ego-technical-guide.css')) : '1.0.0' }}">
@endpush

@section('content')
<div class="container-fluid py-3 tg-wrap">

    <nav class="tg-breadcrumb" aria-label="Đường dẫn">
        <a href="{{ route('ky-thuat.tong-quan') }}">Kỹ thuật</a>
        <span class="sep">/</span>
        <span>Hướng dẫn</span>
    </nav>

    <div class="tg-head">
        <h1 class="tg-head__title">Hướng dẫn sử dụng module Kỹ thuật</h1>
        <p class="tg-head__sub">
            Các bài hướng dẫn có ảnh chụp màn hình thật của hệ thống, đi đúng theo thao tác trên
            máy: lập kế hoạch, giao việc, báo cáo, duyệt báo cáo và KPI.
            Bạn đang thấy {{ $guideCount }} bài phù hợp với vai trò của mình.
        </p>
    </div>

    <div class="tg-search">
        <label class="form-label small text-muted mb-1" for="tgSearchInput">Tìm nhanh bài hướng dẫn</label>
        <div class="input-group input-group-sm">
            <span class="input-group-text bg-white"><i class="bi bi-search"></i></span>
            <input type="search"
                   class="form-control"
                   id="tgSearchInput"
                   placeholder="Ví dụ: giao việc, báo cáo phát sinh, KPI…"
                   autocomplete="off">
        </div>
        <p class="tg-search__empty d-none" id="tgSearchEmpty">
            <i class="bi bi-emoji-neutral"></i> Không có bài hướng dẫn nào khớp với từ khoá này.
        </p>
    </div>

    @forelse($groups as $group)
        <section class="tg-group" data-tg-group>
            <div class="tg-group__head">
                <span class="tg-group__icon"><i class="bi {{ $group['icon'] }}"></i></span>
                <h2 class="tg-group__title">{{ $group['label'] }}</h2>
            </div>
            @if($group['description'] !== '')
                <p class="tg-group__desc">{{ $group['description'] }}</p>
            @endif

            <div class="tg-cards">
                @foreach($group['guides'] as $item)
                    <article class="tg-card"
                             data-tg-card
                             data-title="{{ \Illuminate\Support\Str::lower(\Illuminate\Support\Str::ascii($item['title'])) }}"
                             data-summary="{{ \Illuminate\Support\Str::lower(\Illuminate\Support\Str::ascii($item['summary'])) }}">
                        <span class="tg-card__icon"><i class="bi {{ $item['icon'] }}"></i></span>
                        <h3 class="tg-card__title">{{ $item['title'] }}</h3>
                        <p class="tg-card__summary">{{ $item['summary'] }}</p>
                        <span class="tg-card__meta">
                            <i class="bi bi-clock"></i> {{ $item['reading_minutes'] }} phút đọc
                        </span>
                        <div class="tg-card__cta">
                            <a href="{{ route('technical.guides.show', ['slug' => $item['slug']]) }}"
                               class="btn btn-sm btn-outline-primary">
                                <i class="bi bi-book"></i> Xem hướng dẫn
                            </a>
                        </div>
                    </article>
                @endforeach
            </div>
        </section>
    @empty
        <div class="alert alert-info">
            <i class="bi bi-info-circle"></i> Chưa có bài hướng dẫn nào dành cho vai trò của bạn.
        </div>
    @endforelse
</div>
@endsection

@push('scripts')
<script>
(function () {
    'use strict';

    var input = document.getElementById('tgSearchInput');
    if (!input) { return; }

    var cards = Array.prototype.slice.call(document.querySelectorAll('[data-tg-card]'));
    var groups = Array.prototype.slice.call(document.querySelectorAll('[data-tg-group]'));
    var empty = document.getElementById('tgSearchEmpty');

    function normalise(value) {
        var text = (value || '').toLowerCase();
        if (text.normalize) {
            text = text.normalize('NFD').replace(/[̀-ͯ]/g, '').replace(/đ/g, 'd');
        }
        return text;
    }

    input.addEventListener('input', function () {
        var needle = normalise(input.value.trim());
        var shown = 0;

        cards.forEach(function (card) {
            var haystack = (card.getAttribute('data-title') || '') + ' ' + (card.getAttribute('data-summary') || '');
            var match = needle === '' || normalise(haystack).indexOf(needle) !== -1;
            card.hidden = !match;
            if (match) { shown++; }
        });

        groups.forEach(function (group) {
            var visible = group.querySelectorAll('[data-tg-card]:not([hidden])').length;
            group.hidden = visible === 0;
        });

        if (empty) { empty.classList.toggle('d-none', shown !== 0); }
    });
})();
</script>
@endpush
