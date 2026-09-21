@extends('layouts.app')

@section('title', 'Công việc kỹ thuật của tôi')

@push('styles')
    <link rel="stylesheet"
          href="{{ asset('css/ego-technical-work.css') }}?v={{ file_exists(public_path('css/ego-technical-work.css')) ? filemtime(public_path('css/ego-technical-work.css')) : '1.0.0' }}">
@endpush

@section('content')
<div class="container-fluid py-3 tw-wrap">

    <div class="tw-head">
        <div>
            <h1 class="tw-head__title">{{ $canManage ? 'Công việc kỹ thuật' : 'Công việc của tôi' }}</h1>
            <p class="tw-head__sub">
                @if($canManage)
                    Toàn bộ công việc kỹ thuật trong công ty — chọn nhân sự để xem theo từng người.
                @else
                    Chỉ hiển thị những đầu việc được giao cho bạn. Trang này chỉ để xem và viết báo cáo, không sửa dữ liệu Công trình.
                @endif
            </p>
        </div>
        <div class="tw-head__actions">
            <a href="{{ route('technical.daily-reports.create') }}" class="btn btn-primary">
                <i class="bi bi-journal-plus"></i> Viết báo cáo ngày
            </a>
        </div>
    </div>

    @include('technical.workboard.partials.nav')
    @include('technical.workboard.partials.flash')

    <div class="tw-card">
        <div class="tw-card__body border-bottom">
            <form method="GET" action="{{ route('technical.work.my') }}" class="row g-2 align-items-end">
                <div class="col-12 col-md-3">
                    <label class="form-label small text-muted mb-1" for="f-date">Ngày</label>
                    <input type="date" id="f-date" name="date" class="form-control form-control-sm"
                           value="{{ $filters['date'] ?? '' }}">
                </div>

                <div class="col-12 col-md-3">
                    <label class="form-label small text-muted mb-1" for="f-site">Công trình</label>
                    <select id="f-site" name="site_id" class="form-select form-select-sm">
                        <option value="">Tất cả công trình</option>
                        @foreach($sites as $site)
                            <option value="{{ $site->id }}" @selected((int) ($filters['site_id'] ?? 0) === (int) $site->id)>
                                {{ $site->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="col-6 col-md-2">
                    <label class="form-label small text-muted mb-1" for="f-source">Nguồn công việc</label>
                    <select id="f-source" name="source_type" class="form-select form-select-sm">
                        <option value="">Tất cả nguồn</option>
                        @foreach($sourceOptions as $key => $label)
                            <option value="{{ $key }}" @selected(($filters['source_type'] ?? '') === $key)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="col-6 col-md-2">
                    <label class="form-label small text-muted mb-1" for="f-status">Trạng thái</label>
                    <select id="f-status" name="status_group" class="form-select form-select-sm">
                        <option value="">Tất cả trạng thái</option>
                        @foreach($statusOptions as $key => $label)
                            <option value="{{ $key }}" @selected(($filters['status_group'] ?? '') === $key)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>

                @if($canManage)
                    <div class="col-12 col-md-2">
                        <label class="form-label small text-muted mb-1" for="f-user">Nhân sự</label>
                        <select id="f-user" name="user_id" class="form-select form-select-sm">
                            <option value="">Tất cả nhân sự</option>
                            @foreach($teamMembers as $member)
                                <option value="{{ $member->id }}" @selected((int) ($filters['user_id'] ?? 0) === (int) $member->id)>
                                    {{ $member->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                @endif

                <div class="col-12 col-md-8">
                    <label class="form-label small text-muted mb-1" for="f-q">Từ khoá</label>
                    <input type="search" id="f-q" name="q" class="form-control form-control-sm"
                           placeholder="Tên việc, công trình, mô tả…" value="{{ $filters['keyword'] ?? '' }}">
                </div>

                <div class="col-12 col-md-4 d-flex gap-2">
                    <button type="submit" class="btn btn-sm btn-primary flex-grow-1">
                        <i class="bi bi-funnel"></i> Lọc
                    </button>
                    <a href="{{ route('technical.work.my') }}" class="btn btn-sm btn-outline-secondary">
                        <i class="bi bi-x-lg"></i> Xoá lọc
                    </a>
                </div>
            </form>
        </div>

        <div class="tw-card__head">
            <div class="tw-chips">
                @foreach($filterOptions as $key => $label)
                    <a class="tw-chip {{ ($filters['filter'] ?? '') === $key ? 'is-active' : '' }}"
                       href="{{ route('technical.work.my', array_merge(request()->except(['filter', 'page']), ['filter' => $key])) }}">
                        {{ $label }}
                    </a>
                @endforeach
            </div>
            <small class="text-muted">{{ number_format($items->total()) }} đầu việc</small>
        </div>

        <div class="tw-card__body tw-card__body--flush">
            @forelse($items as $item)
                @include('technical.workboard.partials.work-item', ['item' => $item])
            @empty
                <div class="tw-empty">
                    <i class="bi bi-inbox"></i>
                    <p>Chưa có công việc nào</p>
                    <small>Thử bỏ bớt bộ lọc, hoặc kiểm tra lại phân công ở module Công trình / Bảo trì.</small>
                </div>
            @endforelse
        </div>

        @if($items->hasPages())
            <div class="tw-card__body border-top">
                {{ $items->withQueryString()->links() }}
            </div>
        @endif
    </div>

</div>
@endsection
