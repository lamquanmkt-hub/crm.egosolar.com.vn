@extends('layouts.app')

@section('title', 'Hồ sơ Bảo trì / Bảo hành')

@section('styles')
<link rel="stylesheet" href="{{ asset('css/technical-maintenance-v3.css') }}?v={{ file_exists(public_path('css/technical-maintenance-v3.css')) ? filemtime(public_path('css/technical-maintenance-v3.css')) : time() }}">
<link rel="stylesheet" href="{{ asset('css/technical-maintenance-site-v7.css') }}?v={{ file_exists(public_path('css/technical-maintenance-site-v7.css')) ? filemtime(public_path('css/technical-maintenance-site-v7.css')) : time() }}">
@endsection

@section('content')
@php
    $statusTone = ['draft'=>'muted','scheduled'=>'info','unassigned'=>'warning','assigned'=>'info','customer_confirmed'=>'info','travelling'=>'info','in_progress'=>'info','waiting_material'=>'warning','waiting_submission'=>'warning','pending_approval'=>'warning','revision_requested'=>'danger','approved'=>'success','waiting_customer'=>'warning','completed'=>'success','postponed'=>'warning','cancelled'=>'danger'];
    $doneStatuses = ['approved', 'completed'];
    $completedCount = $schedules->whereIn('status', $doneStatuses)->count();
    $overdueCount = $schedules->filter(fn ($item) => $item->isOverdue())->count();
    $activeSchedules = $schedules->reject(fn ($item) => in_array($item->status, [...$doneStatuses, 'cancelled'], true));
    $nextSchedule = $activeSchedules->sortBy(fn ($item) => optional($item->scheduled_date)->timestamp ?: PHP_INT_MAX)->first();
    $progressPercent = $schedules->count() ? min(100, (int) round(($completedCount / $schedules->count()) * 100)) : 0;
    $sortedDates = $schedules->filter(fn ($item) => $item->scheduled_date)->sortBy('scheduled_date')->values();
    $cycleMonths = $sortedDates->count() >= 2 ? max(1, (int) round(abs($sortedDates[0]->scheduled_date->diffInMonths($sortedDates[1]->scheduled_date)))) : null;
    $cycleLabel = $cycleMonths ? $cycleMonths.' tháng/lần' : ($schedules->count() > 1 ? 'Theo lịch công trình' : 'Theo yêu cầu');
    $acceptedDate = $site->accepted_at ?? $site->installed_at ?? null;
    $mapUrl = $site->address ? 'https://www.google.com/maps/search/?api=1&query='.rawurlencode($site->address) : null;
    $documentCategories = ['contract'=>'Hợp đồng','survey'=>'Biên bản khảo sát','handover'=>'Biên bản bàn giao','acceptance'=>'Biên bản nghiệm thu','diagram'=>'Sơ đồ điện','datasheet'=>'Datasheet','warranty'=>'Phiếu bảo hành','invoice'=>'Hóa đơn','overview'=>'Ảnh tổng thể','video'=>'Video','other'=>'Khác'];
@endphp

<div class="ms7-page" data-ms7-root>
    <nav class="ms7-breadcrumb"><a href="{{ route('projects-unified.maintenance.index') }}">Bảo trì &amp; Bảo hành</a><i class="bi bi-chevron-right"></i><span>Hồ sơ công trình</span></nav>

    @if(session('success'))<div class="tm3-alert success"><i class="bi bi-check-circle-fill"></i><span>{{ session('success') }}</span></div>@endif
    @if(session('error'))<div class="tm3-alert danger"><i class="bi bi-exclamation-octagon-fill"></i><span>{{ session('error') }}</span></div>@endif
    @if($errors->any())<div class="tm3-alert danger"><i class="bi bi-exclamation-triangle-fill"></i><span>{{ $errors->first() }}</span></div>@endif

    <header class="ms7-hero">
        <div class="ms7-identity"><span class="ms7-eyebrow">HỒ SƠ BẢO TRÌ / BẢO HÀNH</span><h1>{{ $site->name ?: 'Công trình chưa đặt tên' }}</h1><div class="ms7-identity-meta"><span><i class="bi bi-geo-alt"></i> {{ $site->address ?: 'Chưa cập nhật địa chỉ' }}</span>@if($site->system_kwp)<span><i class="bi bi-lightning-charge"></i> {{ number_format((float) $site->system_kwp, 2, ',', '.') }} kWp</span>@endif</div></div>
        <div class="ms7-hero-actions">@if($site->contact_phone)<a class="ms7-button ghost" href="tel:{{ $site->contact_phone }}"><i class="bi bi-telephone"></i> Gọi khách</a>@endif @if($mapUrl)<a class="ms7-button ghost" href="{{ $mapUrl }}" target="_blank" rel="noopener"><i class="bi bi-geo-alt"></i> Bản đồ</a>@endif @if($permissions['manage'])<a class="ms7-button ghost" href="{{ route('sites.edit', $site->id) }}"><i class="bi bi-sliders"></i> Cấu hình</a>@endif<a class="ms7-button primary" href="{{ route('projects-unified.maintenance.index') }}"><i class="bi bi-arrow-left"></i> Danh sách</a></div>
    </header>

    <section class="ms7-project-strip"><div><small>Khách hàng</small><strong>{{ $site->contact_name ?: 'Chưa cập nhật' }}</strong><span>{{ $site->contact_phone ?: 'Chưa có số điện thoại' }}</span></div><div><small>Ngày nghiệm thu</small><strong>{{ $acceptedDate ? \Carbon\Carbon::parse($acceptedDate)->format('d/m/Y') : 'Chưa cập nhật' }}</strong><span>Ngày bàn giao công trình</span></div><div><small>Chu kỳ bảo trì</small><strong>{{ $cycleLabel }}</strong><span>{{ $cycles->count() }} chu kỳ đang theo dõi</span></div><div><small>Bảo hành đến</small><strong>{{ $site->warranty_to ? \Carbon\Carbon::parse($site->warranty_to)->format('d/m/Y') : 'Chưa cập nhật' }}</strong><span>{{ $site->system_type ?: 'Hệ thống điện mặt trời' }}</span></div><div><small>Đợt đang mở</small><strong>{{ $nextSchedule ? ($nextSchedule->round_no ?: 1).'/'.($nextSchedule->total_rounds ?: 1) : ($schedules->isEmpty() ? 'Chưa có' : 'Đã hoàn tất') }}</strong><span>{{ $nextSchedule ? (optional($nextSchedule->scheduled_date)->format('d/m/Y') ?: 'Chưa đặt ngày') : $completedCount.'/'.$schedules->count().' đợt hoàn thành' }}</span></div></section>

    <section class="ms7-metrics"><button class="ms7-metric active" type="button" data-ms7-round-filter="all"><span>Tổng số đợt</span><strong>{{ $schedules->count() }}</strong><small>{{ $cycles->count() }} chu kỳ</small></button><button class="ms7-metric info" type="button" data-ms7-round-filter="open"><span>Đang xử lý</span><strong>{{ $activeSchedules->count() }}</strong><small>{{ $nextSchedule ? 'Gần nhất '.$nextSchedule->scheduled_date?->format('d/m/Y') : 'Không có đợt mở' }}</small></button><button class="ms7-metric success" type="button" data-ms7-round-filter="done"><span>Đã hoàn thành</span><strong>{{ $completedCount }}</strong><small>{{ $progressPercent }}% tiến độ</small></button><button class="ms7-metric danger" type="button" data-ms7-round-filter="overdue"><span>Quá hạn</span><strong>{{ $overdueCount }}</strong><small>{{ $overdueCount ? 'Cần xử lý ngay' : 'Không có đợt quá hạn' }}</small></button></section>

    <div class="ms7-tabs" role="tablist"><button class="active" type="button" data-ms7-tab="rounds"><i class="bi bi-calendar2-week"></i> Chu kỳ &amp; đợt <b>{{ $schedules->count() }}</b></button><button type="button" data-ms7-tab="documents"><i class="bi bi-folder2-open"></i> Hồ sơ <b>{{ $documents->count() }}</b></button><button type="button" data-ms7-tab="equipment"><i class="bi bi-upc-scan"></i> Thiết bị <b>{{ $serials->count() }}</b></button><button type="button" data-ms7-tab="history"><i class="bi bi-clock-history"></i> Lịch sử <b>{{ $activity->count() }}</b></button></div>

    <section class="ms7-panel active" data-ms7-panel="rounds">
        @forelse($cycles as $cycle)
            <article class="ms7-cycle"><header class="ms7-cycle-header"><div><span class="ms7-eyebrow">CHU KỲ BẢO TRÌ</span><h2>{{ $cycle['title'] }}</h2><p>{{ $cycle['completed'] }}/{{ $cycle['planned'] }} đợt hoàn thành @if($cycle['next'])· Đợt tiếp theo {{ optional($cycle['next']->scheduled_date)->format('d/m/Y') ?: 'chưa đặt lịch' }}@endif</p></div><div class="ms7-cycle-progress"><strong>{{ $cycle['percent'] }}%</strong><div><span style="width:{{ $cycle['percent'] }}%"></span></div></div></header>
                <div class="ms7-round-grid">
                    @foreach($cycle['items'] as $item)
                        @php($itemDone = in_array($item->status, $doneStatuses, true))
                        @php($itemOverdue = $item->isOverdue())
                        @php($itemActive = $nextSchedule && (int) $nextSchedule->id === (int) $item->id)
                        <a class="ms7-round {{ $itemDone ? 'done' : ($itemOverdue ? 'overdue' : ($itemActive ? 'current' : '')) }}" href="{{ route('projects-unified.maintenance.show', ['schedule'=>$item->id]) }}" data-ms7-round="{{ $itemDone ? 'done' : 'open' }}" data-ms7-overdue="{{ $itemOverdue ? '1' : '0' }}"><div class="ms7-round-top"><span>ĐỢT {{ $item->round_no ?: 1 }}/{{ $item->total_rounds ?: 1 }}</span><i class="bi {{ $itemDone ? 'bi-check-circle-fill' : 'bi-calendar-event' }}"></i></div><strong>{{ optional($item->scheduled_date)->format('d/m/Y') ?: 'Chưa đặt ngày' }}</strong><span class="ms7-status {{ $statusTone[$item->status] ?? 'muted' }}">{{ $statuses[$item->status] ?? $item->status }}</span><div class="ms7-round-person"><i class="bi bi-person"></i> {{ $item->leader?->user?->name ?: ($item->assignee_names ?: 'Chưa phân công') }}</div><footer><span>{{ $item->schedule_code ?: '#'.$item->id }}</span><i class="bi bi-arrow-right"></i></footer></a>
                    @endforeach
                </div>
            </article>
        @empty<div class="ms7-empty"><i class="bi bi-calendar2-x"></i><strong>Chưa có đợt bảo trì</strong><span>Kích hoạt bảo trì sau nghiệm thu để tạo chu kỳ và các đợt công việc.</span></div>@endforelse
        <div class="ms7-filter-empty" data-ms7-filter-empty hidden>Không có đợt phù hợp bộ lọc đang chọn.</div>
    </section>

    <section class="ms7-panel" data-ms7-panel="documents"><header class="ms7-section-header"><div><h2>Hồ sơ công trình</h2><p>Hợp đồng, nghiệm thu, phiếu bảo hành và hồ sơ kỹ thuật.</p></div>@if($permissions['upload'])<button class="ms7-button primary" type="button" data-ms7-open-upload><i class="bi bi-cloud-arrow-up"></i> Tải hồ sơ</button>@endif</header><div class="ms7-document-list">@forelse($documents as $document)<article class="ms7-document"><span class="ms7-doc-icon"><i class="bi bi-file-earmark-text"></i></span><div><strong title="{{ $document->original_name }}">{{ \Illuminate\Support\Str::limit($document->original_name, 65) }}</strong><small>{{ $documentCategories[$document->category] ?? $document->category }} · {{ number_format($document->file_size / 1024, 1) }} KB · {{ $document->uploader?->name ?: 'Hệ thống' }} · {{ optional($document->created_at)->format('d/m/Y H:i') }}</small></div><div class="ms7-document-actions"><a href="{{ route('projects-unified.maintenance.site-files.preview', ['document'=>$document->id]) }}" target="_blank" title="Xem hồ sơ"><i class="bi bi-eye"></i></a><a href="{{ route('projects-unified.maintenance.site-files.download', ['document'=>$document->id]) }}" title="Tải xuống"><i class="bi bi-download"></i></a>@if($permissions['manage'] || (int) $document->uploaded_by === (int) auth()->id())<form method="POST" action="{{ route('projects-unified.maintenance.site-files.destroy', ['document'=>$document->id]) }}" onsubmit="return confirm('Xóa hồ sơ này?')">@csrf @method('DELETE')<button type="submit" title="Xóa hồ sơ"><i class="bi bi-trash3"></i></button></form>@endif</div></article>@empty<div class="ms7-empty compact"><i class="bi bi-folder2-open"></i><strong>Chưa có hồ sơ</strong><span>Tài liệu công trình sẽ xuất hiện tại đây sau khi tải lên.</span></div>@endforelse</div></section>

    <section class="ms7-panel" data-ms7-panel="equipment"><header class="ms7-section-header"><div><h2>Thiết bị &amp; serial bảo hành</h2><p>Theo dõi thiết bị, số serial và thời hạn bảo hành.</p></div></header><div class="ms7-table-wrap"><table class="ms7-table"><thead><tr><th>Thiết bị</th><th>Serial</th><th>Thời hạn bảo hành</th><th>Trạng thái</th></tr></thead><tbody>@forelse($serials as $serial)<tr><td><strong>{{ $serial->product_name ?: 'Thiết bị chưa xác định' }}</strong><small>{{ $serial->sku ?: 'Chưa có SKU' }}</small></td><td><code>{{ $serial->serial_code ?: '#'.$serial->serial_unit_id }}</code></td><td>{{ $serial->warranty_start_at ? \Carbon\Carbon::parse($serial->warranty_start_at)->format('d/m/Y') : '—' }} → {{ $serial->warranty_end_at ? \Carbon\Carbon::parse($serial->warranty_end_at)->format('d/m/Y') : '—' }}</td><td><span class="ms7-status info">{{ $serial->status ?: 'Đang bảo hành' }}</span></td></tr>@empty<tr><td colspan="4"><div class="ms7-empty compact">Chưa có thiết bị hoặc serial bảo hành liên kết.</div></td></tr>@endforelse</tbody></table></div></section>

    <section class="ms7-panel" data-ms7-panel="history"><header class="ms7-section-header"><div><h2>Lịch sử hoạt động</h2><p>Phân công, phê duyệt và thay đổi trạng thái các đợt bảo trì.</p></div></header><div class="ms7-history">@forelse($activity as $event)<article><span><i class="bi {{ $event->kind === 'approval' ? 'bi-patch-check' : 'bi-arrow-repeat' }}"></i></span><div><strong>{{ $event->kind === 'approval' ? 'Phê duyệt / phân công' : 'Cập nhật trạng thái' }}</strong><p>{{ $event->reason ?: (($statuses[$event->to_status] ?? $event->to_status) ?: 'Cập nhật dữ liệu') }}</p><small>{{ $event->actor ?: 'Hệ thống' }} · {{ $event->activity_at ? \Carbon\Carbon::parse($event->activity_at)->format('d/m/Y H:i') : '—' }}</small></div></article>@empty<div class="ms7-empty compact"><i class="bi bi-clock-history"></i><strong>Chưa có hoạt động</strong></div>@endforelse</div></section>

    @if($permissions['upload'])<div class="ms7-modal" data-ms7-upload-modal hidden><div class="ms7-modal-overlay" data-ms7-close-upload></div><section class="ms7-modal-dialog" role="dialog" aria-modal="true"><header><div><span class="ms7-eyebrow">HỒ SƠ CÔNG TRÌNH</span><h2>Tải hồ sơ lên</h2></div><button type="button" data-ms7-close-upload><i class="bi bi-x-lg"></i></button></header><form method="POST" action="{{ route('projects-unified.maintenance.site-files.store', ['site'=>$site->id]) }}" enctype="multipart/form-data">@csrf<label>Loại hồ sơ<select name="category" required>@foreach($documentCategories as $category=>$label)<option value="{{ $category }}">{{ $label }}</option>@endforeach</select></label><label>Chọn tệp<input type="file" name="files[]" multiple required accept=".jpg,.jpeg,.png,.webp,.pdf,.doc,.docx,.xls,.xlsx,.mp4"></label><label>Ghi chú<input name="description" placeholder="Mô tả nhóm hồ sơ"></label><footer><button class="ms7-button ghost" type="button" data-ms7-close-upload>Hủy</button><button class="ms7-button primary" type="submit"><i class="bi bi-cloud-arrow-up"></i> Tải lên</button></footer></form></section></div>@endif
</div>
@endsection

@section('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    const root = document.querySelector('[data-ms7-root]');
    if (!root) return;
    const tabs = [...root.querySelectorAll('[data-ms7-tab]')];
    const openTab = name => {
        tabs.forEach(tab => tab.classList.toggle('active', tab.dataset.ms7Tab === name));
        root.querySelectorAll('[data-ms7-panel]').forEach(panel => panel.classList.toggle('active', panel.dataset.ms7Panel === name));
    };
    tabs.forEach(tab => tab.addEventListener('click', () => openTab(tab.dataset.ms7Tab)));
    const filters = [...root.querySelectorAll('[data-ms7-round-filter]')];
    filters.forEach(button => button.addEventListener('click', () => {
        filters.forEach(item => item.classList.toggle('active', item === button));
        openTab('rounds');
        const filter = button.dataset.ms7RoundFilter;
        const rounds = [...root.querySelectorAll('[data-ms7-round]')];
        let visible = 0;
        rounds.forEach(round => { const show = filter === 'all' || (filter === 'overdue' ? round.dataset.ms7Overdue === '1' : round.dataset.ms7Round === filter); round.hidden = !show; if (show) visible++; });
        root.querySelectorAll('.ms7-cycle').forEach(cycle => { cycle.hidden = !cycle.querySelector('[data-ms7-round]:not([hidden])'); });
        const empty = root.querySelector('[data-ms7-filter-empty]');
        if (empty) empty.hidden = visible > 0 || rounds.length === 0;
    }));
    const modal = root.querySelector('[data-ms7-upload-modal]');
    if (!modal) return;
    root.querySelectorAll('[data-ms7-open-upload]').forEach(button => button.addEventListener('click', () => { modal.hidden = false; document.body.classList.add('ms7-modal-open'); }));
    root.querySelectorAll('[data-ms7-close-upload]').forEach(button => button.addEventListener('click', () => { modal.hidden = true; document.body.classList.remove('ms7-modal-open'); }));
    document.addEventListener('keydown', event => { if (event.key === 'Escape') { modal.hidden = true; document.body.classList.remove('ms7-modal-open'); } });
});
</script>
@endsection
