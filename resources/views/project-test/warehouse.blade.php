@extends('layouts.app')

@section('title', 'Xuất kho Công Trình Test')

@push('styles')
<link rel="stylesheet" href="{{ asset('css/project-test.css') }}?v={{ file_exists(public_path('css/project-test.css')) ? filemtime(public_path('css/project-test.css')) : time() }}">
@endpush

@section('content')
<div class="pt-page">
<div class="pt-shell">
    @if(session('success'))<div class="pt-alert pt-alert--success" style="margin-bottom:14px">{{ session('success') }}</div>@endif
    @if($errors->any())<div class="pt-alert" style="margin-bottom:14px">{{ $errors->first() }}</div>@endif

    <section class="pt-hero">
        <div class="pt-hero__row">
            <div><div class="pt-kicker"><i class="bi bi-box-arrow-up-right"></i> Sản phẩm & Kho <span class="pt-new">TEST NEW</span></div><h1>Xuất kho công trình Test</h1><p>Kho xử lý phiếu đã được Admin duyệt tại trang riêng. Không cần vào module Công trình và không ảnh hưởng tồn kho của hệ thống cũ trong giai đoạn Test.</p></div>
            <div class="pt-actions"><a class="pt-btn pt-btn--light" href="{{ route('project-test.index') }}"><i class="bi bi-kanban"></i> Công Trình Test new</a></div>
        </div>
    </section>

    <div class="pt-alert" style="margin-top:14px"><strong>Chế độ kiểm thử an toàn:</strong> thao tác “Xuất kho Test” chỉ ghi vào bảng project_test_*, không trừ tồn tại crm_product_stock. Trên mỗi dòng vẫn hiển thị tồn hiện tại để đối chiếu.</div>

    <section class="pt-card pt-toolbar">
        <form method="GET" class="pt-filter">
            <div class="pt-search"><i class="bi bi-search"></i><input class="pt-input" name="q" value="{{ request('q') }}" placeholder="Mã phiếu, mã hoặc tên công trình..."></div>
            <select class="pt-select" name="status"><option value="">Tất cả</option><option value="approved" @selected(request('status')==='approved')>Chờ Kho</option><option value="preparing" @selected(request('status')==='preparing')>Đang chuẩn bị</option><option value="issued" @selected(request('status')==='issued')>Đã xuất</option></select>
            <button class="pt-btn pt-btn--dark">Lọc</button>
        </form>
    </section>

    <section class="pt-warehouse-grid" style="margin-top:14px">
        @forelse($requests as $materialRequest)
            <article class="pt-card pt-warehouse-card">
                <div class="pt-warehouse-card__top"><div><span class="pt-status">{{ $materialRequest->status }}</span><h3>{{ $materialRequest->code }}</h3><p>{{ $materialRequest->project?->code }} · {{ $materialRequest->project?->name }}</p></div><a href="{{ route('project-test.show',$materialRequest->project_id) }}" class="pt-more"><i class="bi bi-arrow-up-right"></i></a></div>
                <div class="pt-summary" style="margin-top:12px"><div><small>Ngày cần</small><strong>{{ optional($materialRequest->needed_at)->format('d/m/Y') ?: '—' }}</strong></div><div><small>Lịch thi công</small><strong>{{ optional($materialRequest->project?->proposed_installation_at)->format('d/m/Y H:i') ?: '—' }}</strong></div></div>
                <div class="pt-warehouse-items">
                    @foreach($materialRequest->items as $item)
                        <div class="pt-warehouse-item"><span><strong>{{ $item->item_name }}</strong><br>{{ rtrim(rtrim(number_format((float)$item->quantity,3,'.',''),'0'),'.') }} {{ $item->unit }}</span><span class="pt-stock">SP #{{ $item->product_id ?: 'ngoài' }}</span></div>
                    @endforeach
                </div>

                @if($materialRequest->status !== 'issued')
                    <form method="POST" action="{{ route('project-test.warehouse.issue',$materialRequest) }}" data-confirm="Xác nhận ghi nhận xuất kho Test?" style="margin-top:13px">@csrf
                        <div><label class="pt-label">Kho xuất</label><select class="pt-select" name="warehouse_id" required><option value="">-- Chọn kho --</option>@foreach($warehouses as $warehouse)<option value="{{ $warehouse->id }}">{{ $warehouse->name }}</option>@endforeach</select></div>
                        <table class="pt-material-table" style="margin-top:10px"><thead><tr><th>Vật tư</th><th>SL xuất Test</th><th>Serial</th></tr></thead><tbody>@foreach($materialRequest->items as $item)<tr><td>{{ $item->item_name }}</td><td><input class="pt-input" type="number" step="0.001" min="0" max="{{ $item->quantity }}" name="issued_quantity[{{ $item->id }}]" value="{{ $item->quantity }}" required></td><td><input class="pt-input" name="serials[{{ $item->id }}]" placeholder="Nếu có"></td></tr>@endforeach</tbody></table>
                        <div style="margin-top:10px"><label class="pt-label">Ghi chú bàn giao</label><textarea class="pt-textarea" name="issue_note"></textarea></div>
                        <button class="pt-btn pt-btn--brand" style="margin-top:10px;width:100%"><i class="bi bi-box-arrow-up-right"></i> Xác nhận xuất kho Test</button>
                    </form>
                @else
                    <div class="pt-alert pt-alert--success" style="margin-top:12px">Đã xuất Test lúc {{ optional($materialRequest->issued_at)->format('d/m/Y H:i') }}. Hồ sơ đã chuyển Kỹ thuật trưởng phân công.</div>
                @endif
            </article>
        @empty
            <article class="pt-card pt-section" style="grid-column:1/-1"><div class="pt-empty"><i class="bi bi-box2"></i><h3>Chưa có phiếu chờ Kho</h3><p>Phiếu chỉ xuất hiện sau khi Kỹ thuật đề xuất và Admin duyệt.</p></div></article>
        @endforelse
    </section>
    @if($requests->hasPages())<div class="pt-card pt-pagination" style="margin-top:14px">{{ $requests->links() }}</div>@endif
</div>
</div>
@endsection

@push('scripts')
<script src="{{ asset('js/project-test.js') }}?v={{ file_exists(public_path('js/project-test.js')) ? filemtime(public_path('js/project-test.js')) : time() }}"></script>
@endpush
