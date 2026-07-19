@extends('layouts.app')

@section('title', 'Hồ sơ công trình Solar')

@section('styles')
<link rel="stylesheet" href="{{ asset('css/technical-maintenance-v3.css') }}?v={{ file_exists(public_path('css/technical-maintenance-v3.css')) ? filemtime(public_path('css/technical-maintenance-v3.css')) : time() }}">
@endsection

@section('content')
@php
    $statusTone = [
        'draft'=>'muted','scheduled'=>'info','unassigned'=>'violet','assigned'=>'indigo',
        'customer_confirmed'=>'cyan','travelling'=>'cyan','in_progress'=>'warning',
        'waiting_material'=>'orange','waiting_submission'=>'slate','pending_approval'=>'pending',
        'revision_requested'=>'orange','approved'=>'success','waiting_customer'=>'violet',
        'completed'=>'success','postponed'=>'orange','cancelled'=>'danger',
    ];
    $approvalTone = ['not_submitted'=>'muted','pending'=>'pending','approved'=>'success','revision_requested'=>'orange','rejected'=>'danger'];
    $completedCount = $schedules->where('status','completed')->count();
    $progressPercent = $schedules->count() ? min(100, (int)round(($completedCount / $schedules->count()) * 100)) : 0;
    $mapUrl = $site->address ? 'https://www.google.com/maps/search/?api=1&query='.rawurlencode($site->address) : null;
@endphp

<div class="ego-container tm3-page">
    <nav class="tm3-breadcrumb"><a href="{{ route('ky-thuat.maintenance.index') }}">Bảo trì &amp; Bảo hành</a><i class="bi bi-chevron-right"></i><span>Hồ sơ công trình</span></nav>

    @if(session('success'))<div class="tm3-alert success"><i class="bi bi-check-circle-fill"></i><span>{{ session('success') }}</span></div>@endif
    @if(session('error'))<div class="tm3-alert danger"><i class="bi bi-exclamation-octagon-fill"></i><span>{{ session('error') }}</span></div>@endif
    @if($errors->any())<div class="tm3-alert danger align-start"><i class="bi bi-exclamation-triangle-fill"></i><div><strong>Chưa thể thực hiện</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div></div>@endif

    <header class="tm3-page-header site-header">
        <div>
            <div class="tm3-title-line"><span class="tm3-kicker">Hồ sơ công trình Solar</span><span class="tm3-status {{ $site->status === 'warranty' ? 'success' : 'info' }}">{{ $site->status ?: 'Đang vận hành' }}</span></div>
            <h1>{{ $site->name }}</h1>
            <p><i class="bi bi-geo-alt"></i> {{ $site->address ?: 'Chưa cập nhật địa chỉ' }}</p>
        </div>
        <div class="tm3-header-actions">
            @if($site->contact_phone)<a class="btn btn-light" href="tel:{{ $site->contact_phone }}"><i class="bi bi-telephone"></i> Gọi khách</a>@endif
            @if($mapUrl)<a class="btn btn-light" href="{{ $mapUrl }}" target="_blank"><i class="bi bi-map"></i> Bản đồ</a>@endif
            @if($site->monitoring_link)<a class="btn btn-light" href="{{ $site->monitoring_link }}" target="_blank"><i class="bi bi-activity"></i> Giám sát</a>@endif
            @if($permissions['manage'])<a class="btn btn-primary" href="{{ route('sites.edit', $site->id) }}"><i class="bi bi-pencil-square"></i> Chỉnh hồ sơ</a>@endif
        </div>
    </header>

    <section class="tm3-metric-grid">
        <div class="tm3-metric"><small>Khách hàng</small><strong>{{ $site->contact_name ?: 'Chưa cập nhật' }}</strong><span>{{ $site->contact_phone ?: 'Chưa có số điện thoại' }}</span></div>
        <div class="tm3-metric"><small>Công suất</small><strong>{{ $site->system_kwp ? number_format((float)$site->system_kwp,2).' kWp' : '—' }}</strong><span>AC: {{ $site->system_kw_ac ? number_format((float)$site->system_kw_ac,2).' kW' : '—' }}</span></div>
        <div class="tm3-metric"><small>Hệ thống</small><strong>{{ $site->system_type ?: '—' }}</strong><span>{{ $site->battery_kwh ? number_format((float)$site->battery_kwh,2).' kWh lưu trữ' : 'Không có dữ liệu pin' }}</span></div>
        <div class="tm3-metric"><small>Bảo hành đến</small><strong>{{ $site->warranty_to ? \Carbon\Carbon::parse($site->warranty_to)->format('d/m/Y') : '—' }}</strong><span>Lắp đặt: {{ $site->installed_at ? \Carbon\Carbon::parse($site->installed_at)->format('d/m/Y') : '—' }}</span></div>
        <div class="tm3-metric"><small>Tiến độ O&amp;M</small><strong>{{ $completedCount }}/{{ $schedules->count() }} đợt</strong><div class="tm3-progress"><span style="width:{{ $progressPercent }}%"></span></div></div>
    </section>

    <nav class="tm3-section-nav">
        <a href="#tm3Cycles" class="active"><i class="bi bi-calendar2-week"></i> Các đợt bảo trì</a>
        <a href="#tm3Serials"><i class="bi bi-upc-scan"></i> Thiết bị &amp; serial</a>
        <a href="#tm3Documents"><i class="bi bi-folder2-open"></i> Hồ sơ</a>
        <a href="#tm3Activity"><i class="bi bi-clock-history"></i> Lịch sử</a>
    </nav>

    <section class="tm3-card" id="tm3Cycles">
        <div class="tm3-section-head"><div><h2>Các đợt bảo trì / bảo hành</h2><p>{{ $cycles->count() }} chu kỳ · {{ $schedules->count() }} đợt</p></div></div>
        @forelse($cycles as $cycle)
            <article class="tm3-cycle-block">
                <div class="tm3-cycle-head">
                    <div><h3>{{ $cycle['title'] }}</h3><p>{{ $cycle['completed'] }}/{{ $cycle['planned'] }} hoàn thành · {{ $cycle['approved'] }} đã duyệt</p></div>
                    <div class="tm3-cycle-progress"><span>{{ $cycle['percent'] }}%</span><div class="tm3-progress"><span style="width:{{ $cycle['percent'] }}%"></span></div></div>
                </div>
                <div class="tm3-table-wrap">
                    <table class="tm3-table compact">
                        <thead><tr><th>Đợt</th><th>Ngày dự kiến</th><th>Người phụ trách</th><th>Trạng thái</th><th>Phê duyệt</th><th class="text-end">Thao tác</th></tr></thead>
                        <tbody>
                        @foreach($cycle['items'] as $item)
                            <tr>
                                <td><a class="tm3-code" href="{{ route('ky-thuat.maintenance.show', ['schedule'=>$item->id]) }}">Đợt {{ $item->round_no ?: 1 }}/{{ $item->total_rounds ?: 1 }}</a><small class="tm3-block">{{ $item->schedule_code }}</small></td>
                                <td><strong>{{ optional($item->scheduled_date)->format('d/m/Y') ?: '—' }}</strong>@if($item->isOverdue())<small class="tm3-block text-danger">Quá hạn</small>@endif</td>
                                <td>{{ $item->leader?->user?->name ?: $item->assignee_names }}</td>
                                <td><span class="tm3-status {{ $statusTone[$item->status] ?? 'muted' }}">{{ $statuses[$item->status] ?? $item->status }}</span></td>
                                <td><span class="tm3-status {{ $approvalTone[$item->approval_status] ?? 'muted' }}">{{ $approvalStatuses[$item->approval_status] ?? $item->approval_status }}</span></td>
                                <td><div class="tm3-row-actions"><a class="tm3-icon-btn" href="{{ route('ky-thuat.maintenance.show', ['schedule'=>$item->id]) }}" title="Xem đợt"><i class="bi bi-eye"></i></a></div></td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            </article>
        @empty
            <div class="tm3-empty"><i class="bi bi-calendar2-x"></i><h3>Chưa có đợt bảo trì</h3><p>Tạo lịch mới từ trang điều phối để bắt đầu theo dõi.</p></div>
        @endforelse
    </section>

    <section class="tm3-card" id="tm3Serials">
        <div class="tm3-section-head"><div><h2>Thiết bị &amp; serial</h2><p>{{ $serials->count() }} thiết bị bảo hành liên kết</p></div></div>
        <div class="tm3-table-wrap"><table class="tm3-table compact"><thead><tr><th>Thiết bị</th><th>Serial</th><th>Thời hạn bảo hành</th><th>Trạng thái</th></tr></thead><tbody>
        @forelse($serials as $serial)
            <tr><td><strong>{{ $serial->product_name ?: 'Thiết bị chưa xác định' }}</strong><small class="tm3-block">{{ $serial->sku ?: '—' }}</small></td><td><code>{{ $serial->serial_code ?: '#'.$serial->serial_unit_id }}</code></td><td>{{ $serial->warranty_start_at ? \Carbon\Carbon::parse($serial->warranty_start_at)->format('d/m/Y') : '—' }} → {{ $serial->warranty_end_at ? \Carbon\Carbon::parse($serial->warranty_end_at)->format('d/m/Y') : '—' }}</td><td><span class="tm3-status info">{{ $serial->status }}</span></td></tr>
        @empty<tr><td colspan="4"><div class="tm3-empty compact">Chưa có serial bảo hành liên kết.</div></td></tr>@endforelse
        </tbody></table></div>
    </section>

    <section class="tm3-card" id="tm3Documents">
        <div class="tm3-section-head"><div><h2>Hồ sơ công trình</h2><p>Hợp đồng, biên bản, datasheet, ảnh và video</p></div><span class="tm3-count">{{ $documents->count() }} file</span></div>
        @if($permissions['upload'])
            <form class="tm3-upload-bar" method="POST" action="{{ route('ky-thuat.maintenance.site-files.store', ['site'=>$site->id]) }}" enctype="multipart/form-data">@csrf
                <select class="form-select" name="category" required><option value="contract">Hợp đồng</option><option value="survey">Biên bản khảo sát</option><option value="handover">Biên bản bàn giao</option><option value="acceptance">Biên bản nghiệm thu</option><option value="diagram">Sơ đồ điện</option><option value="datasheet">Datasheet</option><option value="warranty">Phiếu bảo hành</option><option value="invoice">Hóa đơn</option><option value="overview">Ảnh tổng thể</option><option value="video">Video</option><option value="other">Khác</option></select>
                <input class="form-control" type="file" name="files[]" multiple required accept=".jpg,.jpeg,.png,.webp,.pdf,.doc,.docx,.xls,.xlsx,.mp4">
                <input class="form-control" name="description" placeholder="Ghi chú cho nhóm file">
                <button class="btn btn-primary" type="submit"><i class="bi bi-cloud-arrow-up"></i> Tải lên</button>
            </form>
        @endif
        <div class="tm3-file-grid">
        @forelse($documents as $document)
            <article class="tm3-file-card"><span class="tm3-file-icon"><i class="bi bi-file-earmark-text"></i></span><div class="tm3-file-info"><strong title="{{ $document->original_name }}">{{ \Illuminate\Support\Str::limit($document->original_name,45) }}</strong><small>{{ strtoupper($document->category) }} · {{ number_format($document->file_size/1024,1) }} KB</small><small>{{ $document->uploader?->name ?: 'Hệ thống' }} · {{ optional($document->created_at)->format('d/m/Y H:i') }}</small></div><div class="tm3-file-actions"><a href="{{ route('ky-thuat.maintenance.site-files.preview', ['document'=>$document->id]) }}" target="_blank" title="Xem"><i class="bi bi-eye"></i></a><a href="{{ route('ky-thuat.maintenance.site-files.download', ['document'=>$document->id]) }}" title="Tải"><i class="bi bi-download"></i></a>@if($permissions['manage'] || (int)$document->uploaded_by === (int)auth()->id())<form method="POST" action="{{ route('ky-thuat.maintenance.site-files.destroy', ['document'=>$document->id]) }}" onsubmit="return confirm('Xóa hồ sơ này?')">@csrf @method('DELETE')<button type="submit"><i class="bi bi-trash3"></i></button></form>@endif</div></article>
        @empty<div class="tm3-empty compact">Chưa có hồ sơ nào được tải lên.</div>@endforelse
        </div>
    </section>

    <section class="tm3-card" id="tm3Activity">
        <div class="tm3-section-head"><div><h2>Lịch sử hoạt động</h2><p>Các lần đổi trạng thái và phê duyệt gần nhất</p></div></div>
        <div class="tm3-timeline">
        @forelse($activity as $event)
            <div class="tm3-timeline-item"><span></span><div><strong>{{ $event->kind === 'approval' ? 'Phê duyệt kỹ thuật' : 'Đổi trạng thái' }}</strong><p>{{ $event->reason ?: (($statuses[$event->to_status] ?? $event->to_status) ?: 'Cập nhật dữ liệu') }}</p><small>{{ $event->actor ?: 'Hệ thống' }} · {{ $event->activity_at ? \Carbon\Carbon::parse($event->activity_at)->format('d/m/Y H:i') : '—' }}</small></div></div>
        @empty<div class="tm3-empty compact">Chưa có hoạt động.</div>@endforelse
        </div>
    </section>
</div>
@endsection
