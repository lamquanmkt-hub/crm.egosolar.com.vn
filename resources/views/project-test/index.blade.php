@extends('layouts.app')

@section('title', 'Công Trình Test new')

@push('styles')
<link rel="stylesheet" href="{{ asset('css/project-test.css') }}?v={{ file_exists(public_path('css/project-test.css')) ? filemtime(public_path('css/project-test.css')) : time() }}">
@endpush

@section('content')
@php
    $flow = [
        ['Sales tạo', 'bi-person-plus'], ['Duyệt khảo sát', 'bi-calendar-check'], ['Khảo sát & 3D', 'bi-badge-3d'],
        ['Sales chốt', 'bi-hand-thumbs-up'], ['Duyệt lịch thi công', 'bi-calendar2-week'], ['Vật tư & Admin', 'bi-box-seam'],
        ['Kho xuất', 'bi-box-arrow-up-right'], ['Thi công & nghiệm thu', 'bi-tools'], ['Bảo hành', 'bi-shield-check'],
    ];
@endphp
<div class="pt-page">
    <div class="pt-shell">
        <section class="pt-hero">
            <div class="pt-hero__row">
                <div>
                    <div class="pt-kicker"><i class="bi bi-diagram-3"></i> Workflow liên phòng ban <span class="pt-new">TEST NEW</span></div>
                    <h1>Công Trình Test new</h1>
                    <p>Module mới độc lập hoàn toàn với Công trình cũ. Sales tạo hồ sơ → Kỹ thuật duyệt lịch → khảo sát & mô phỏng 3D → vật tư → Kho → điều phối → nghiệm thu → tự động bảo hành.</p>
                </div>
                <div class="pt-actions">
                    @can('project-test.warehouse')
                        <a href="{{ route('project-test.warehouse.index') }}" class="pt-btn pt-btn--light"><i class="bi bi-box-arrow-up-right"></i> Xuất kho Test</a>
                    @endcan
                    @can('project-test.create')
                        <a href="{{ route('project-test.create') }}" class="pt-btn pt-btn--light"><i class="bi bi-plus-circle"></i> Sales tạo công trình</a>
                    @endcan
                </div>
            </div>
        </section>

        <div class="pt-flow" aria-label="Quy trình Công trình Test new">
            @foreach($flow as $index => $step)
                <div class="pt-flow__step">
                    <i class="bi {{ $step[1] }}"></i>
                    <div><strong>{{ $step[0] }}</strong><small>Bước {{ $index + 1 }}</small></div>
                </div>
            @endforeach
        </div>

        <section class="pt-kpis">
            <article class="pt-kpi" data-tone="teal"><span class="pt-kpi__icon"><i class="bi bi-kanban"></i></span><div><small>Tổng hồ sơ được phép xem</small><strong data-pt-counter>{{ $kpis['total'] }}</strong></div></article>
            <article class="pt-kpi" data-tone="blue"><span class="pt-kpi__icon"><i class="bi bi-person-check"></i></span><div><small>Liên quan đến tôi</small><strong data-pt-counter>{{ $kpis['mine'] }}</strong></div></article>
            <article class="pt-kpi" data-tone="orange"><span class="pt-kpi__icon"><i class="bi bi-hourglass-split"></i></span><div><small>Đang chờ xử lý</small><strong data-pt-counter>{{ $kpis['waiting'] }}</strong></div></article>
            <article class="pt-kpi" data-tone="purple"><span class="pt-kpi__icon"><i class="bi bi-tools"></i></span><div><small>Thi công / nghiệm thu</small><strong data-pt-counter>{{ $kpis['installing'] }}</strong></div></article>
            <article class="pt-kpi" data-tone="red"><span class="pt-kpi__icon"><i class="bi bi-shield-check"></i></span><div><small>Đang bảo hành</small><strong data-pt-counter>{{ $kpis['warranty'] }}</strong></div></article>
        </section>

        <section class="pt-card pt-toolbar">
            <form method="GET" class="pt-filter">
                <div class="pt-search"><i class="bi bi-search"></i><input class="pt-input" type="search" name="q" value="{{ request('q') }}" placeholder="Tìm mã, tên công trình, khách hàng, địa chỉ..."></div>
                <select class="pt-select" name="status">
                    <option value="">Tất cả trạng thái</option>
                    @foreach($statuses as $key => $info)
                        <option value="{{ $key }}" @selected(request('status') === $key)>{{ $info['label'] }}</option>
                    @endforeach
                </select>
                <button class="pt-btn pt-btn--dark" type="submit"><i class="bi bi-funnel"></i> Lọc</button>
                @if(request()->hasAny(['q','status']))<a href="{{ route('project-test.index') }}" class="pt-btn pt-btn--soft"><i class="bi bi-arrow-counterclockwise"></i></a>@endif
            </form>
            <span class="pt-status"><i class="bi bi-person-badge"></i>{{ $roleLabel }}</span>
        </section>

        <section class="pt-card pt-list">
            <div class="pt-list__head"><span>Hồ sơ công trình</span><span>Giai đoạn & tiến độ</span><span>Người phụ trách</span><span>Mốc gần nhất</span><span></span></div>
            @forelse($projects as $project)
                @php($info = $statuses[$project->status] ?? ['label' => $project->status, 'group' => 'Khác'])
                <article class="pt-row" data-filter-row="{{ mb_strtolower($project->code.' '.$project->name.' '.$project->address.' '.$project->contact_name) }}">
                    <div class="pt-project-title">
                        <span class="pt-code">{{ str_pad((string)$project->id, 2, '0', STR_PAD_LEFT) }}</span>
                        <div><strong>{{ $project->name }}</strong><small>{{ $project->code }} · {{ $project->address ?: 'Chưa có địa chỉ' }}</small></div>
                    </div>
                    <div>
                        <span class="pt-status">{{ $info['label'] }}</span>
                        <div class="pt-progress"><span style="width:{{ $project->progress }}%"></span></div>
                    </div>
                    <div class="pt-owner"><strong>{{ $project->leadTechnician?->name ?: $project->salesUser?->name ?: 'Chưa phân công' }}</strong><small>Sales: {{ $project->salesUser?->name ?: '—' }} · Chủ việc: {{ str_replace('_',' ', $project->current_owner_role) }}</small></div>
                    <div class="pt-date">
                        {{ optional($project->proposed_installation_at ?: $project->proposed_survey_at)->format('d/m/Y H:i') ?: 'Chưa có lịch' }}
                        <small>{{ $project->latestMaterialRequest?->code ? 'VT: '.$project->latestMaterialRequest->code : 'Chưa có phiếu vật tư' }}</small>
                    </div>
                    <a class="pt-more" href="{{ route('project-test.show', $project) }}" title="Mở hồ sơ"><i class="bi bi-arrow-right"></i></a>
                </article>
            @empty
                <div class="pt-empty"><i class="bi bi-inboxes"></i><h3>Chưa có hồ sơ Công Trình Test new</h3><p>Sales tạo công trình đầu tiên để bắt đầu workflow mới.</p></div>
            @endforelse
            @if($projects->hasPages())<div class="pt-pagination">{{ $projects->links() }}</div>@endif
        </section>
    </div>
</div>
@endsection

@push('scripts')
<script src="{{ asset('js/project-test.js') }}?v={{ file_exists(public_path('js/project-test.js')) ? filemtime(public_path('js/project-test.js')) : time() }}"></script>
@endpush
