@extends('layouts.app')

@section('title', 'Hồ sơ O&M công trình')
@section('styles')
<link rel="stylesheet" href="{{ asset('css/technical-maintenance-v10.css') }}?v={{ file_exists(public_path('css/technical-maintenance-v10.css')) ? filemtime(public_path('css/technical-maintenance-v10.css')) : time() }}">
@endsection

@section('content')
@php
    $completedCount=$schedules->filter(fn($item) => $item->status === 'completed'
        && $item->approval_status === 'approved'
        && $item->approved_at)->count();
    $total=$schedules->count();
    $pct=$total?round($completedCount/$total*100):0;
@endphp
<div class="ego-container om10-page">
    <nav class="om10-breadcrumb"><a href="{{ route('ky-thuat.maintenance.index') }}">Bảo hành & O&M</a><i class="bi bi-chevron-right"></i><span>Hồ sơ công trình</span></nav>
    @if(session('success'))<div class="om10-alert success"><i class="bi bi-check-circle-fill"></i><span>{{ session('success') }}</span></div>@endif
    @if(session('error'))<div class="om10-alert danger"><i class="bi bi-exclamation-octagon-fill"></i><span>{{ session('error') }}</span></div>@endif

    <section class="om10-detail-head om10-site-head">
        <div class="om10-detail-title"><div class="om10-badges"><span>HỒ SƠ O&M</span></div><h1>{{ $site->name }}</h1><p>{{ $site->address ?: 'Chưa cập nhật địa chỉ' }}</p></div>
        <div class="om10-detail-facts">
            <div><small>Khách hàng</small><strong>{{ $site->contact_name ?: '—' }}</strong></div>
            <div><small>Công suất</small><strong>{{ $site->system_kwp ? number_format((float)$site->system_kwp,2).' kWp' : '—' }}</strong></div>
            <div><small>Bảo hành đến</small><strong>{{ $site->warranty_to ? \Carbon\Carbon::parse($site->warranty_to)->format('d/m/Y') : '—' }}</strong></div>
            <div><small>Tiến độ</small><strong>{{ $completedCount }}/{{ $total }} đợt</strong></div>
        </div>
        <div class="om10-detail-actions"><a class="om10-btn light" href="{{ route('ky-thuat.maintenance.index') }}"><i class="bi bi-arrow-left"></i> Quay lại</a></div>
    </section>

    <div class="om10-site-grid">
        <section class="om10-card">
            <div class="om10-section-head"><div><span>LỊCH SỬ O&M</span><h2>Các đợt bảo trì</h2><p>{{ $cycles->count() }} chu kỳ · {{ $schedules->count() }} đợt</p></div><strong>{{ $pct }}%</strong></div>
            <div class="om10-project-list">
                @forelse($cycles as $cycle)
                    <article class="om10-cycle-simple"><div><strong>{{ $cycle['title'] }}</strong><span>{{ $cycle['completed'] }}/{{ $cycle['planned'] }} hoàn thành</span></div><div class="om10-progress"><span style="width:{{ $cycle['percent'] }}%"></span></div>
                        @foreach($cycle['items'] as $item)
                            @php($validCompletion = $item->status === 'completed' && $item->approval_status === 'approved' && $item->approved_at)
                            <a class="om10-round-line" href="{{ route('ky-thuat.maintenance.show',$item) }}"><span class="om10-round-no">Đợt {{ $item->round_no ?: 1 }}/{{ $item->total_rounds ?: 1 }}</span><strong>{{ $item->schedule_code }}</strong><span>{{ optional($item->scheduled_date)->format('d/m/Y') }}</span><span>{{ $item->assignee_names }}</span><span class="om10-status {{ $validCompletion?'done':($item->status==='completed'?'revision':($item->status==='pending_approval'?'approval':($item->status==='in_progress'?'doing':'plan'))) }}">{{ $item->status === 'completed' && !$validCompletion ? 'Thiếu phê duyệt' : ($statuses[$item->status] ?? $item->status) }}</span><i class="bi bi-arrow-right"></i></a>
                        @endforeach
                    </article>
                @empty<div class="om10-empty">Chưa có lịch bảo trì.</div>@endforelse
            </div>
        </section>

        <aside>
            <section class="om10-card om10-info-card"><strong>Thiết bị & serial</strong><dl><div><dt>Serial liên kết</dt><dd>{{ $serials->count() }}</dd></div><div><dt>Hồ sơ</dt><dd>{{ $documents->count() }} file</dd></div><div><dt>Lắp đặt</dt><dd>{{ $site->installed_at ? \Carbon\Carbon::parse($site->installed_at)->format('d/m/Y') : '—' }}</dd></div></dl></section>
            @if($serials->count())<section class="om10-card om10-serial-list"><div class="om10-side-head"><strong>Serial bảo hành</strong></div>@foreach($serials->take(8) as $serial)<div><strong>{{ $serial->product_name ?: 'Thiết bị' }}</strong><code>{{ $serial->serial_code ?: '#'.$serial->serial_unit_id }}</code><small>{{ $serial->warranty_end_at ? 'BH đến '.\Carbon\Carbon::parse($serial->warranty_end_at)->format('d/m/Y') : 'Chưa có hạn BH' }}</small></div>@endforeach</section>@endif
        </aside>
    </div>

    <section class="om10-card">
        <div class="om10-section-head"><div><span>HỒ SƠ CÔNG TRÌNH</span><h2>Biên bản, ảnh & tài liệu</h2><p>Tài liệu dùng chung cho toàn bộ vòng đời bảo hành.</p></div></div>
        @if($permissions['upload'])<form class="om10-file-upload" method="POST" action="{{ route('ky-thuat.maintenance.site-files.store',['site'=>$site->id]) }}" enctype="multipart/form-data">@csrf<select name="category" required><option value="contract">Hợp đồng</option><option value="survey">Khảo sát</option><option value="handover">Bàn giao</option><option value="acceptance">Nghiệm thu</option><option value="diagram">Sơ đồ</option><option value="datasheet">Datasheet</option><option value="warranty">Bảo hành</option><option value="other">Khác</option></select><input type="file" name="files[]" multiple required><input name="description" placeholder="Ghi chú"><button class="om10-btn primary" type="submit">Tải lên</button></form>@endif
        <div class="om10-files-grid">@forelse($documents as $doc)<article><div class="om10-file-icon"><i class="bi bi-file-earmark"></i></div><div><strong>{{ $doc->original_name }}</strong><span>{{ strtoupper($doc->category) }} · {{ number_format(($doc->file_size??0)/1024,1) }} KB</span><small>{{ $doc->uploader?->name ?: 'Hệ thống' }} · {{ optional($doc->created_at)->format('d/m/Y H:i') }}</small></div><div><a href="{{ route('ky-thuat.maintenance.site-files.preview',$doc) }}" target="_blank"><i class="bi bi-eye"></i></a><a href="{{ route('ky-thuat.maintenance.site-files.download',$doc) }}"><i class="bi bi-download"></i></a></div></article>@empty<div class="om10-empty">Chưa có hồ sơ.</div>@endforelse</div>
    </section>

    <section class="om10-card"><div class="om10-section-head"><div><span>LỊCH SỬ</span><h2>Hoạt động gần nhất</h2></div></div><div class="om10-timeline">@forelse($activity as $event)<article><span></span><div><strong>{{ $event->kind==='approval'?'Phê duyệt':'Cập nhật trạng thái' }}</strong><p>{{ $event->reason ?: ($statuses[$event->to_status] ?? $event->to_status) }}</p><small>{{ $event->actor ?: 'Hệ thống' }} · {{ $event->activity_at ? \Carbon\Carbon::parse($event->activity_at)->format('d/m/Y H:i') : '—' }}</small></div></article>@empty<div class="om10-empty">Chưa có hoạt động.</div>@endforelse</div></section>
</div>
@endsection
