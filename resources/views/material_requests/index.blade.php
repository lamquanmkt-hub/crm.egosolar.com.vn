@extends('layouts.app')
@section('title', 'Yêu cầu vật tư')
@push('styles')
<link rel="stylesheet" href="{{ asset('css/ego-material-workspace.css') }}?v={{ file_exists(public_path('css/ego-material-workspace.css')) ? filemtime(public_path('css/ego-material-workspace.css')) : '3' }}">
<link rel="stylesheet" href="{{ asset('css/ego-material-dispatch-tabs.css') }}?v={{ file_exists(public_path('css/ego-material-dispatch-tabs.css')) ? filemtime(public_path('css/ego-material-dispatch-tabs.css')) : '5' }}">
@endpush
@section('content')
@php
    use App\Enums\MaterialRequestStatus;
    $statusLabels = [MaterialRequestStatus::DRAFT->value => 'Nháp', MaterialRequestStatus::SUBMITTED->value => 'Chờ Admin', MaterialRequestStatus::ADMIN_APPROVED->value => 'Chờ Kho xử lý', MaterialRequestStatus::EXPORTED->value => 'Đã xuất kho', MaterialRequestStatus::REJECTED->value => 'Từ chối'];
    $statusTone = fn ($value) => match ((string) $value) { MaterialRequestStatus::SUBMITTED->value => 'amber', MaterialRequestStatus::ADMIN_APPROVED->value => 'blue', MaterialRequestStatus::EXPORTED->value => 'green', MaterialRequestStatus::REJECTED->value => 'red', default => 'neutral' };
    $fmtMoney = fn ($value) => number_format((float) ($value ?? 0), 0, ',', '.').' đ';
@endphp
<main class="mrw-page">
    <header class="mrw-header">
        <div><span class="mrw-eyebrow">CÔNG TRÌNH · BẢO TRÌ · BẢO HÀNH</span><h1>Yêu cầu vật tư</h1><p>Danh sách phiếu kỹ thuật chuyển Kho xử lý.</p></div>
        @if(auth()->user()?->hasAnyRole(['admin', 'technical', 'ky_thuat', 'sales', 'warehouse', 'kho']))
            <a class="mrw-btn primary" href="{{ route('material-requests.create') }}"><i class="bi bi-plus-circle"></i> Tạo đơn vật tư</a>
        @endif
    </header>
    @if(session('success'))<div class="mrw-alert success">{{ session('success') }}</div>@endif
    @if(session('error'))<div class="mrw-alert danger">{{ session('error') }}</div>@endif

    <nav class="mrw-category-tabs" aria-label="Phân loại phiếu vật tư">
        <a href="{{ route('material-requests.index', array_merge(request()->except(['page', 'tab', 'source_type']), ['tab' => 'construction'])) }}" class="{{ $activeTab === 'construction' ? 'active construction' : '' }}"><i class="bi bi-buildings"></i><span>Công trình mới</span><b>{{ number_format($tabCounts['construction'] ?? 0) }}</b></a>
        <a href="{{ route('material-requests.index', array_merge(request()->except(['page', 'tab', 'source_type']), ['tab' => 'warranty'])) }}" class="{{ $activeTab === 'warranty' ? 'active warranty' : '' }}"><i class="bi bi-tools"></i><span>Bảo hành / Bảo trì</span><b>{{ number_format($tabCounts['warranty'] ?? 0) }}</b></a>
        <a href="{{ route('material-requests.index', array_merge(request()->except(['page', 'tab', 'source_type']), ['tab' => 'dispatch'])) }}" class="{{ $activeTab === 'dispatch' ? 'active dispatch' : '' }}"><i class="bi bi-file-earmark-check"></i><span>Phiếu xuất kho</span><b>{{ number_format($tabCounts['dispatch'] ?? 0) }}</b></a>
    </nav>

    <section class="mrw-summary-bar">
        <div><span>Tất cả</span><strong>{{ number_format($summary['total'] ?? 0) }}</strong></div>
        <div class="amber"><span>Chờ Admin</span><strong>{{ number_format($summary['pending_admin'] ?? 0) }}</strong></div>
        <div class="blue"><span>Kho xử lý</span><strong>{{ number_format($summary['pending_warehouse'] ?? 0) }}</strong></div>
        <div class="green"><span>Đã xuất</span><strong>{{ number_format($summary['exported'] ?? 0) }}</strong></div>
    </section>

    <form method="GET" action="{{ route('material-requests.index') }}" class="mrw-toolbar">
        <input type="hidden" name="tab" value="{{ $activeTab }}">
        <label class="mrw-search"><i class="bi bi-search"></i><input name="q" value="{{ request('q') }}" placeholder="Tìm mã phiếu, công trình..."></label>
        <select name="source_type"><option value="">Tất cả loại vật tư</option><option value="initial" @selected(request('source_type') === 'initial')>Công trình mới</option><option value="additional" @selected(request('source_type') === 'additional')>Vật tư phát sinh</option><option value="maintenance" @selected(request('source_type') === 'maintenance')>Bảo trì</option><option value="warranty" @selected(request('source_type') === 'warranty')>Bảo hành / thay thế</option></select>
        <select name="status"><option value="">Tất cả trạng thái</option>@foreach($statusLabels as $value => $label)<option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>@endforeach</select>
        <input class="mrw-site-filter" type="number" name="site_id" value="{{ request('site_id') }}" placeholder="Mã CT">
        <button class="mrw-btn primary"><i class="bi bi-funnel"></i> Lọc</button><a href="{{ route('material-requests.index', ['tab' => $activeTab]) }}" class="mrw-btn icon" title="Bỏ bộ lọc"><i class="bi bi-arrow-clockwise"></i></a>
    </form>

    <section class="mrw-card"><header class="mrw-card-head"><h2>{{ $activeTab === 'dispatch' ? 'Chứng từ đã xuất kho' : ($activeTab === 'warranty' ? 'Yêu cầu vật tư bảo hành / bảo trì' : 'Yêu cầu vật tư công trình mới') }}</h2><span>{{ $requests->total() }} phiếu</span></header>
        <div class="mrw-table-scroll"><table class="mrw-table mrw-list-table"><thead><tr><th>Mã phiếu</th><th>Công trình / Người đề xuất</th><th>Loại vật tư</th><th>Vật tư</th><th>Kho xuất</th><th>Trạng thái</th>@if($canViewCost)<th>Giá vốn</th>@endif<th></th></tr></thead><tbody>
            @forelse($requests as $mr)
                @php $source = $requestSources[$mr->id] ?? ['label' => 'Công trình mới', 'tone' => 'teal', 'requester_name' => $mr->creator->name ?? '—']; $status = (string) $mr->status; $lineCount = collect($mr->items)->count(); $matchedCount = collect($mr->items)->filter(fn ($item) => ! empty($item->product_id))->count(); @endphp
                <tr><td><a class="mrw-code" href="{{ route('material-requests.show', $mr) }}">{{ $activeTab === 'dispatch' ? 'PXK-' : 'VT-' }}{{ str_pad((string) $mr->id, 5, '0', STR_PAD_LEFT) }}</a><small>{{ optional($mr->created_at)->format('d/m/Y H:i') }}</small></td><td><strong>{{ $mr->site->name ?? 'Chưa xác định công trình' }}</strong><small>{{ $source['requester_name'] ?? '—' }} · CT #{{ $mr->site_id }}</small></td><td><span class="mrw-source {{ $source['tone'] ?? 'teal' }}">{{ $source['label'] }}</span></td><td><strong>{{ $lineCount }} dòng</strong><small>{{ $matchedCount }}/{{ $lineCount }} đã ghép</small></td><td>@if($mr->warehouse)<strong>{{ $mr->warehouse->name }}</strong>@else<span class="mrw-muted">Chưa chọn kho</span>@endif</td><td><span class="mrw-status {{ $statusTone($status) }}">{{ $statusLabels[$status] ?? $status }}</span></td>@if($canViewCost)<td><strong class="mrw-money">{{ $fmtMoney($mr->total_cost ?? collect($mr->items)->sum('line_total')) }}</strong></td>@endif<td><div class="mrw-list-actions">@if($activeTab === 'dispatch' && $canViewCost)<a class="mrw-file-link pdf" href="{{ route('material-requests.dispatch.pdf', $mr) }}" title="Tải phiếu xuất kho PDF"><i class="bi bi-file-earmark-pdf"></i> PDF</a><a class="mrw-file-link excel" href="{{ route('material-requests.dispatch.excel', $mr) }}" title="Tải phiếu xuất kho Excel"><i class="bi bi-file-earmark-excel"></i> Excel</a>@endif <a class="mrw-row-action" href="{{ route('material-requests.show', $mr) }}">{{ $status === MaterialRequestStatus::ADMIN_APPROVED->value && $canViewCost ? 'Xử lý' : 'Xem' }} <i class="bi bi-arrow-right"></i></a></div></td></tr>
            @empty<tr><td colspan="{{ $canViewCost ? 8 : 7 }}"><div class="mrw-empty">Không có yêu cầu vật tư phù hợp.</div></td></tr>@endforelse
        </tbody></table></div>
        @if($requests->hasPages())<div class="mrw-pagination">{{ $requests->links() }}</div>@endif
    </section>
</main>
@endsection
