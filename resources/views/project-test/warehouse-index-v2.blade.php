@extends('layouts.app')

@section('title', 'Cấp vật tư Công trình Test')

@push('styles')
<link rel="stylesheet" href="{{ asset('css/project-test.css') }}?v={{ file_exists(public_path('css/project-test.css')) ? filemtime(public_path('css/project-test.css')) : time() }}">
@endpush

@section('content')
<div class="pt-page pt-wh-v2-page">
<div class="pt-shell">
    @if(session('success'))<div class="pt-alert pt-alert--success" style="margin-bottom:14px">{{ session('success') }}</div>@endif
    @if($errors->any())<div class="pt-alert" style="margin-bottom:14px">{{ $errors->first() }}</div>@endif

    <section class="pt-hero pt-wh-v2-hero">
        <div class="pt-hero__row">
            <div>
                <div class="pt-kicker"><i class="bi bi-box-seam"></i> Sản phẩm & Kho <span class="pt-new">V2 TEST</span></div>
                <h1>Cấp vật tư công trình</h1>
                <p>Kho chỉ xử lý 4 việc: xem Kỹ thuật cần gì, ghép SKU thật, giữ hàng và bàn giao. Không cần mở hồ sơ công trình.</p>
            </div>
            <div class="pt-actions">
                <a class="pt-btn pt-btn--light" href="{{ route('project-test.index') }}"><i class="bi bi-kanban"></i> Công Trình Test new</a>
            </div>
        </div>
    </section>

    <div class="pt-alert pt-alert--info pt-wh-v2-safe" style="margin-top:14px">
        <i class="bi bi-shield-check"></i>
        <span><strong>Chế độ Test an toàn:</strong> màn hình đọc sản phẩm và tồn thật từ kho, nhưng thao tác giữ/xuất chưa trừ <code>crm_product_stock</code>.</span>
    </div>

    <section class="pt-card pt-wh-v2-tabs" style="margin-top:14px">
        <a href="{{ route('project-test.warehouse.index', request()->except('state','page')) }}" class="pt-wh-v2-tab {{ request('state') ? '' : 'active' }}">
            <span>Tất cả</span><strong>{{ $stateCounts->sum() }}</strong>
        </a>
        @foreach($states as $key => $label)
            <a href="{{ route('project-test.warehouse.index', array_merge(request()->except('page'), ['state'=>$key])) }}" class="pt-wh-v2-tab {{ request('state')===$key ? 'active' : '' }}">
                <span>{{ $label }}</span><strong>{{ $stateCounts[$key] ?? 0 }}</strong>
            </a>
        @endforeach
    </section>

    <section class="pt-card pt-toolbar pt-wh-v2-toolbar">
        <form method="GET" class="pt-filter">
            @if(request('state'))<input type="hidden" name="state" value="{{ request('state') }}">@endif
            <div class="pt-search"><i class="bi bi-search"></i><input class="pt-input" name="q" value="{{ request('q') }}" placeholder="Tìm mã phiếu, mã hoặc tên công trình..."></div>
            <button class="pt-btn pt-btn--dark"><i class="bi bi-funnel"></i> Lọc</button>
            @if(request('q'))<a class="pt-btn pt-btn--light" href="{{ route('project-test.warehouse.index', request('state') ? ['state'=>request('state')] : []) }}">Xóa lọc</a>@endif
        </form>
    </section>

    <section class="pt-card pt-wh-v2-list" style="margin-top:14px">
        <div class="pt-wh-v2-list__head">
            <span>Phiếu / Công trình</span>
            <span>Ngày cần</span>
            <span>Nhu cầu</span>
            <span>Tình trạng cấp hàng</span>
            <span>Người nhận</span>
            <span></span>
        </div>

        @forelse($requests as $materialRequest)
            @php
                $state = $materialRequest->computed_warehouse_state;
                $summary = $materialRequest->warehouse_summary;
                $percent = $summary['total'] > 0 ? round(($summary['mapped'] / $summary['total']) * 100) : 0;
            @endphp
            <article class="pt-wh-v2-row">
                <div class="pt-wh-v2-request">
                    <span class="pt-code">{{ str_pad((string)$materialRequest->id, 2, '0', STR_PAD_LEFT) }}</span>
                    <div>
                        <strong>{{ $materialRequest->code }}</strong>
                        <a href="{{ route('project-test.show', $materialRequest->project_id) }}">{{ $materialRequest->project?->code }} · {{ $materialRequest->project?->name }}</a>
                        <small>{{ $materialRequest->project?->address }}</small>
                    </div>
                </div>
                <div class="pt-wh-v2-date">
                    <strong>{{ optional($materialRequest->needed_at)->format('d/m/Y') ?: '—' }}</strong>
                    <small>Thi công {{ optional($materialRequest->project?->proposed_installation_at)->format('d/m H:i') ?: 'chưa chốt' }}</small>
                </div>
                <div class="pt-wh-v2-need">
                    <strong>{{ $summary['total'] }} dòng</strong>
                    <small>{{ rtrim(rtrim(number_format((float)$summary['requestedQty'],3,'.',''),'0'),'.') }} đơn vị yêu cầu</small>
                </div>
                <div class="pt-wh-v2-fulfillment">
                    <span class="pt-wh-v2-state pt-wh-v2-state--{{ $state }}">{{ $states[$state] ?? $state }}</span>
                    <div class="pt-progress"><span style="width:{{ $percent }}%"></span></div>
                    <small>{{ $summary['mapped'] }}/{{ $summary['total'] }} dòng đã ghép SKU{{ $summary['shortage'] ? ' · thiếu '.$summary['shortage'].' dòng' : '' }}</small>
                </div>
                <div class="pt-wh-v2-receiver">
                    <strong>{{ $materialRequest->receiver?->name ?: $materialRequest->project?->leadTechnician?->name ?: 'Chưa phân công' }}</strong>
                    <small>{{ $materialRequest->requester?->name ? 'Kỹ thuật đề xuất: '.$materialRequest->requester->name : '—' }}</small>
                </div>
                <div><a href="{{ route('project-test.warehouse.show',$materialRequest) }}" class="pt-btn {{ $state==='issued' ? 'pt-btn--light' : 'pt-btn--brand' }} pt-btn--sm">{{ $state==='issued' ? 'Xem phiếu' : 'Xử lý' }} <i class="bi bi-arrow-right"></i></a></div>
            </article>
        @empty
            <div class="pt-empty"><i class="bi bi-box2"></i><h3>Không có phiếu phù hợp</h3><p>Phiếu được chuyển sang Kho sau khi Admin duyệt nhu cầu vật tư.</p></div>
        @endforelse

        @if($requests->hasPages())<div class="pt-pagination">{{ $requests->links() }}</div>@endif
    </section>
</div>
</div>
@endsection
