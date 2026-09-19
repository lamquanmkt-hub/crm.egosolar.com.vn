@extends('layouts.app')
@section('title', ($documentTitle ?? 'Hồ sơ kỹ thuật').' • Phòng Kỹ thuật')
@push('styles')
<link rel="stylesheet" href="{{ asset('css/technical-workspace.css') }}?v={{ file_exists(public_path('css/technical-workspace.css')) ? filemtime(public_path('css/technical-workspace.css')) : time() }}">
@endpush
@section('content')
<div class="tw-page"><div class="tw-shell">
    <section class="tw-hero">
        <div class="tw-kicker">PHÒNG KỸ THUẬT · HỒ SƠ 360°</div>
        <h1>{{ $documentTitle ?? 'Hồ sơ / Biên bản' }}</h1>
        <p>Tập trung dữ liệu kỹ thuật theo đúng mã công trình, người thực hiện và thời điểm cập nhật.</p>
        @include('technical.workspace.partials-nav')
    </section>

    <section class="tw-subnav">
        <a href="{{ route('technical-workspace.documents.survey') }}" class="{{ request()->routeIs('technical-workspace.documents.survey') ? 'active' : '' }}"><i class="bi bi-images"></i>Khảo sát &amp; hình ảnh</a>
        <a href="{{ route('technical-workspace.documents.design') }}" class="{{ request()->routeIs('technical-workspace.documents.design') ? 'active' : '' }}"><i class="bi bi-badge-3d"></i>File 3D/CAD</a>
        <a href="{{ route('technical-workspace.documents.materials') }}"><i class="bi bi-box-seam"></i>Đề xuất vật tư</a>
        <a href="{{ route('technical-workspace.documents.daily-logs') }}" class="{{ request()->routeIs('technical-workspace.documents.daily-logs') ? 'active' : '' }}"><i class="bi bi-journal-text"></i>Nhật ký thi công</a>
        <a href="{{ route('technical-workspace.documents.acceptance') }}"><i class="bi bi-clipboard-check"></i>Nghiệm thu</a>
        <a href="{{ route('technical-workspace.documents.all') }}" class="{{ request()->routeIs('technical-workspace.documents.all') || request()->routeIs('technical-workspace.documents') ? 'active' : '' }}"><i class="bi bi-folder2-open"></i>Hồ sơ/Biên bản</a>
    </section>

    <section class="tw-kpis">
        <article class="tw-kpi"><span>Tổng hồ sơ</span><strong>{{ $summary['total'] }}</strong><small>Trong phạm vi được xem</small></article>
        <article class="tw-kpi"><span>Khảo sát &amp; hình ảnh</span><strong>{{ $summary['survey'] }}</strong><small>Bằng chứng hiện trường</small></article>
        <article class="tw-kpi"><span>3D/CAD</span><strong>{{ $summary['design'] }}</strong><small>Phương án kỹ thuật</small></article>
        <article class="tw-kpi"><span>Nhật ký thi công</span><strong>{{ $summary['daily_log'] }}</strong><small>Báo cáo thực thi</small></article>
        <article class="tw-kpi"><span>Nghiệm thu</span><strong>{{ $summary['acceptance'] }}</strong><small>Hồ sơ bàn giao</small></article>
    </section>

    <section class="tw-card"><div class="tw-card__body">
        <form method="GET" class="tw-filter">
            <label>Tìm hồ sơ
                <input class="tw-input" type="search" name="q" value="{{ request('q') }}" placeholder="Mã, tên công trình, nội dung...">
            </label>
            @if(!request()->routeIs('technical-workspace.documents.survey','technical-workspace.documents.design','technical-workspace.documents.daily-logs'))
                <label>Loại hồ sơ
                    <select class="tw-select" name="kind">
                        <option value="">Tất cả</option>
                        <option value="survey" @selected(request('kind')==='survey')>Khảo sát &amp; hình ảnh</option>
                        <option value="design" @selected(request('kind')==='design')>3D/CAD</option>
                        <option value="daily_log" @selected(request('kind')==='daily_log')>Nhật ký thi công</option>
                        <option value="acceptance" @selected(request('kind')==='acceptance')>Hồ sơ nghiệm thu</option>
                    </select>
                </label>
            @endif
            <button class="tw-btn"><i class="bi bi-search"></i>Lọc</button>
        </form>
    </div></section>

    <section class="tw-card"><div class="tw-card__body">
        <div class="tw-doc-grid">
            @forelse($documents as $doc)
                <article class="tw-doc">
                    <span class="tw-doc__icon"><i class="bi {{ match($doc['kind']){'survey'=>'bi-images','design'=>'bi-badge-3d','acceptance'=>'bi-clipboard-check',default=>'bi-journal-text'} }}"></i></span>
                    <div class="tw-doc__body">
                        <strong>{{ $doc['label'] }} · {{ $doc['project_code'] }}</strong>
                        <small>{{ $doc['project_name'] }}</small>
                        <small>{{ $doc['date'] }} · {{ $doc['author'] }}</small>
                        @if($doc['note'])<small>{{ $doc['note'] }}</small>@endif
                        <div class="tw-doc__actions">
                            <a href="{{ $doc['project_url'] }}">Mở công trình</a>
                            <a href="{{ $doc['url'] }}" target="_blank">Xem file</a>
                            <a href="{{ $doc['url'] }}" download>Tải file</a>
                        </div>
                    </div>
                </article>
            @empty
                <div class="tw-empty"><i class="bi bi-folder-x"></i><strong>Chưa có hồ sơ phù hợp</strong><span>Dữ liệu sẽ xuất hiện khi kỹ thuật viên tải file vào đúng công trình.</span></div>
            @endforelse
        </div>
    </div></section>
</div></div>
@endsection
