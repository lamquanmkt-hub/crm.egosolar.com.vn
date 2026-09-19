@extends('layouts.app')

@section('title', 'Sự cố & Bảo hành')

@section('styles')
<link rel="stylesheet" href="{{ asset('css/technical-maintenance-v9.css') }}?v={{ file_exists(public_path('css/technical-maintenance-v9.css')) ? filemtime(public_path('css/technical-maintenance-v9.css')) : time() }}">
@endsection

@section('content')
@php
    $statusGroup = function ($status) {
        $status = strtolower((string) $status);
        if (in_array($status, ['resolved','completed','closed'], true)) return ['Hoàn tất','success'];
        if (in_array($status, ['pending_replacement','replacement_pending','approved_replacement','waiting_device'], true)) return ['Chờ thiết bị','warning'];
        if ($status === 'processing') return ['Đang xử lý','info'];
        if (in_array($status, ['rejected','cancelled'], true)) return ['Đã đóng','muted'];
        return ['Mới tiếp nhận','danger'];
    };
    $serialUrl = \Illuminate\Support\Facades\Route::has('serial-warranty.index') ? route('serial-warranty.index') : url('/serial-warranty');
@endphp

<div class="ego-container om9-page">
    <nav class="om9-breadcrumb"><a href="{{ route('dashboard') }}">Trang chủ</a><i class="bi bi-chevron-right"></i><a href="{{ route('ky-thuat.maintenance.index') }}">Bảo trì &amp; Bảo hành</a><i class="bi bi-chevron-right"></i><span>Sự cố &amp; Bảo hành</span></nav>

    @if(session('success'))<div class="om9-alert success"><i class="bi bi-check-circle-fill"></i>{{ session('success') }}</div>@endif
    @if(session('error'))<div class="om9-alert danger"><i class="bi bi-exclamation-octagon-fill"></i>{{ session('error') }}</div>@endif
    @if($errors->any())<div class="om9-alert danger"><i class="bi bi-exclamation-triangle-fill"></i><div><strong>Chưa thể lưu dữ liệu</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div></div>@endif

    <header class="om9-page-head">
        <div><span>TRUNG TÂM O&amp;M</span><h1>Sự cố &amp; Bảo hành</h1><p>Tiếp nhận, theo dõi và đóng phiếu trong cùng một luồng với lịch bảo trì.</p></div>
        <div class="om9-head-actions">
            <a class="om9-btn light" href="{{ $serialUrl }}"><i class="bi bi-upc-scan"></i> Tra cứu serial</a>
            @if($canManage)<button class="om9-btn primary" type="button" data-bs-toggle="modal" data-bs-target="#om9CreateIssue"><i class="bi bi-plus-lg"></i> Tạo phiếu</button>@endif
        </div>
    </header>

    <nav class="om9-module-tabs">
        <a href="{{ route('ky-thuat.maintenance.index', ['section'=>'overview']) }}"><i class="bi bi-grid"></i>Tổng quan</a>
        <a href="{{ route('ky-thuat.maintenance.index', ['section'=>'list','view'=>'all','month'=>'']) }}"><i class="bi bi-calendar3"></i>Lịch bảo trì</a>
        <a class="active" href="{{ route('ky-thuat.maintenance.issues') }}"><i class="bi bi-shield-exclamation"></i>Sự cố &amp; Bảo hành</a>
        <a href="{{ route('ky-thuat.maintenance.index', ['section'=>'files','view'=>'all','month'=>'']) }}"><i class="bi bi-folder2-open"></i>Hồ sơ công trình</a>
    </nav>

    <section class="om9-issue-stats">
        <a class="{{ $mode==='open' ? 'active' : '' }}" href="{{ route('ky-thuat.maintenance.issues',['mode'=>'open']) }}"><small>Đang mở</small><strong>{{ number_format($summary['open']) }}</strong><span>Cần xử lý</span></a>
        <a class="{{ $mode==='replacement' ? 'active' : '' }}" href="{{ route('ky-thuat.maintenance.issues',['mode'=>'replacement']) }}"><small>Chờ thiết bị</small><strong>{{ number_format($summary['replacement']) }}</strong><span>Phối hợp Kho</span></a>
        <a class="{{ $mode==='done' ? 'active' : '' }}" href="{{ route('ky-thuat.maintenance.issues',['mode'=>'done']) }}"><small>Hoàn tất</small><strong>{{ number_format($summary['resolved']) }}</strong><span>Đã có kết quả</span></a>
        <a class="{{ $mode==='all' ? 'active' : '' }}" href="{{ route('ky-thuat.maintenance.issues',['mode'=>'all']) }}"><small>Tất cả</small><strong>{{ number_format($summary['open']+$summary['resolved']) }}</strong><span>Toàn bộ phiếu</span></a>
    </section>

    <section class="om9-toolbar">
        <form method="GET" action="{{ route('ky-thuat.maintenance.issues') }}">
            <input type="hidden" name="mode" value="{{ $mode }}">
            <label><i class="bi bi-search"></i><input type="search" name="q" value="{{ request('q') }}" placeholder="Tìm serial, sản phẩm, khách hàng, đơn hàng..."></label>
            <button type="submit" class="om9-icon-action"><i class="bi bi-funnel"></i></button>
            @if(request('q'))<a class="om9-icon-action" href="{{ route('ky-thuat.maintenance.issues',['mode'=>$mode]) }}"><i class="bi bi-arrow-counterclockwise"></i></a>@endif
        </form>
    </section>

    <section class="om9-card om9-issue-list">
        <div class="om9-card-head"><div><span>PHIẾU SỰ CỐ</span><h2>{{ number_format($claims->total()) }} phiếu đang hiển thị</h2><p>Mỗi phiếu chỉ hiển thị trạng thái và hành động tiếp theo.</p></div></div>
        <div class="om9-issue-table-wrap">
            <table class="om9-issue-table">
                <thead><tr><th>Phiếu / Serial</th><th>Sản phẩm &amp; khách hàng</th><th>Nội dung</th><th>Trạng thái</th><th>Tiếp theo</th></tr></thead>
                <tbody>
                @forelse($claims as $claim)
                    @php([$groupLabel,$groupTone] = $statusGroup($claim->status))
                    <tr>
                        <td><strong>#{{ $claim->id }}</strong><a href="{{ $serialUrl.'?q='.urlencode((string)$claim->serial_code) }}">{{ $claim->serial_code ?: 'Chưa có serial' }}</a><small>{{ $claim->order_code ?: 'Chưa liên kết đơn hàng' }}</small></td>
                        <td><strong>{{ $claim->product_name ?: 'Chưa xác định sản phẩm' }}</strong><span>{{ $claim->customer_name ?: 'Chưa xác định khách hàng' }}</span></td>
                        <td><strong>{{ \Illuminate\Support\Str::limit((string)$claim->issue_description, 95) ?: 'Chưa có mô tả lỗi' }}</strong>@if($claim->resolution)<span>Kết quả: {{ \Illuminate\Support\Str::limit((string)$claim->resolution, 85) }}</span>@endif</td>
                        <td><span class="om9-status {{ $groupTone }}">{{ $groupLabel }}</span><small>{{ $claim->received_at ? 'Nhận '.\Carbon\Carbon::parse($claim->received_at)->format('d/m/Y') : 'Chưa có ngày tiếp nhận' }}</small></td>
                        <td>
                            @if($canManage)
                                <button class="om9-row-action" type="button" data-bs-toggle="modal" data-bs-target="#om9EditIssue"
                                    data-id="{{ $claim->id }}"
                                    data-serial="{{ $claim->serial_code }}"
                                    data-status="{{ $claim->status }}"
                                    data-resolution="{{ e((string)$claim->resolution) }}"
                                    data-cost="{{ (float)$claim->cost }}"
                                    data-update-url="{{ route('ky-thuat.maintenance.issues.update',['claim'=>$claim->id]) }}">
                                    <span>{{ in_array($claim->status,['resolved','completed','closed'],true) ? 'Xem / cập nhật' : 'Cập nhật xử lý' }}</span><i class="bi bi-arrow-right"></i>
                                </button>
                            @else
                                <span class="om9-muted">Theo dõi</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5"><div class="om9-empty"><i class="bi bi-shield-check"></i><strong>Chưa có phiếu phù hợp</strong><span>Tạo phiếu mới hoặc đổi bộ lọc.</span></div></td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        @if($claims->hasPages())<div class="om9-pagination">{{ $claims->links() }}</div>@endif
    </section>
</div>

@if($canManage)
<div class="modal fade" id="om9CreateIssue" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered"><form class="modal-content om9-modal" method="POST" action="{{ route('ky-thuat.maintenance.issues.store') }}">@csrf
        <div class="modal-header"><div><small>TẠO PHIẾU MỚI</small><h2>Tiếp nhận sự cố</h2></div><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body">
            <label>Serial thiết bị<input class="form-control" name="serial_code" value="{{ old('serial_code') }}" required placeholder="Nhập đúng serial đã bán / giao"></label>
            <div class="om9-form-help">Không nhớ serial? <a href="{{ $serialUrl }}" target="_blank">Mở Tra cứu serial</a>.</div>
            <label>Nội dung lỗi<textarea class="form-control" name="issue_description" rows="5" required placeholder="Mô tả hiện tượng, thời điểm phát sinh và thông tin khách hàng phản ánh...">{{ old('issue_description') }}</textarea></label>
        </div>
        <div class="modal-footer"><button type="button" class="om9-btn light" data-bs-dismiss="modal">Đóng</button><button class="om9-btn primary" type="submit"><i class="bi bi-check2-circle"></i> Tạo phiếu</button></div>
    </form></div>
</div>

<div class="modal fade" id="om9EditIssue" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered"><form class="modal-content om9-modal" method="POST" action="#" id="om9IssueUpdateForm">@csrf @method('PUT')
        <div class="modal-header"><div><small>XỬ LÝ SỰ CỐ</small><h2 id="om9IssueTitle">Phiếu</h2><span id="om9IssueSerial"></span></div><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body">
            <label>Trạng thái<select class="form-select" name="status" id="om9IssueStatus" required>@foreach($statusLabels as $key=>$label)<option value="{{ $key }}">{{ $label }}</option>@endforeach</select></label>
            <label>Kết quả / phương án xử lý<textarea class="form-control" name="resolution" id="om9IssueResolution" rows="5" placeholder="Đã kiểm tra gì, nguyên nhân, phương án, thiết bị cần đổi hoặc kết quả cuối..."></textarea></label>
            <label>Chi phí ghi nhận<input class="form-control" type="number" min="0" step="1000" name="cost" id="om9IssueCost" placeholder="0"></label>
        </div>
        <div class="modal-footer"><button type="button" class="om9-btn light" data-bs-dismiss="modal">Đóng</button><button class="om9-btn primary" type="submit"><i class="bi bi-save"></i> Lưu xử lý</button></div>
    </form></div>
</div>
@endif
@endsection

@section('scripts')
<script src="{{ asset('js/technical-maintenance-v9.js') }}?v={{ file_exists(public_path('js/technical-maintenance-v9.js')) ? filemtime(public_path('js/technical-maintenance-v9.js')) : time() }}"></script>
@if($errors->any() && old('serial_code'))<script>document.addEventListener('DOMContentLoaded',()=>{const el=document.getElementById('om9CreateIssue');if(el&&window.bootstrap){bootstrap.Modal.getOrCreateInstance(el).show();}});</script>@endif
@endsection
