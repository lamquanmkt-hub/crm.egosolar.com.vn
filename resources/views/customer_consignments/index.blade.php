@extends('layouts.app')
@section('title','Ký gửi hàng hóa')
@section('styles')<link rel="stylesheet" href="{{ asset('css/customer-consignments.css') }}?v={{ @filemtime(public_path('css/customer-consignments.css')) ?: time() }}">@endsection
@section('content')
@php $tone=fn($s)=>match($s){'active','completed'=>'green','pending_approval','approved'=>'amber','revision_requested'=>'blue','rejected','cancelled'=>'red',default=>'teal'}; @endphp
<div class="cc-page"><div class="cc-wrap">
    <div class="cc-head"><div><div class="cc-eyebrow">Kho &amp; Ký gửi</div><h1 class="cc-title">Ký gửi hàng hóa</h1><div class="cc-sub">Đơn ký gửi độc lập: Sales chọn hàng → Admin/Giám đốc duyệt → Kho xuất cho khách.</div></div><div class="cc-actions"><a href="{{ route('customer-consignments.process') }}" class="cc-btn"><i class="bi bi-diagram-3"></i> Quy trình ký gửi</a>@can('consignments.create')<a href="{{ route('customer-consignments.create') }}" class="cc-btn cc-btn-primary"><i class="bi bi-plus-lg"></i> Tạo đơn ký gửi</a>@endcan</div></div>
    @if(session('success'))<div class="cc-alert cc-alert-success">{{ session('success') }}</div>@endif
    @if(session('error'))<div class="cc-alert cc-alert-danger">{{ session('error') }}</div>@endif

    <div class="cc-grid-4">
        <div class="cc-stat"><div class="cc-stat-icon"><i class="bi bi-person-check"></i></div><div><div class="cc-stat-label">Chờ Admin/GĐ duyệt</div><div class="cc-stat-value">{{ number_format($stats['pending_approval']) }}</div></div></div>
        <div class="cc-stat"><div class="cc-stat-icon"><i class="bi bi-box-arrow-right"></i></div><div><div class="cc-stat-label">Đã duyệt - Chờ Kho</div><div class="cc-stat-value">{{ number_format($stats['waiting_warehouse']) }}</div></div></div>
        <div class="cc-stat"><div class="cc-stat-icon"><i class="bi bi-truck"></i></div><div><div class="cc-stat-label">Đã xuất ký gửi</div><div class="cc-stat-value">{{ number_format($stats['active']) }}</div></div></div>
        <div class="cc-stat"><div class="cc-stat-icon"><i class="bi bi-exclamation-triangle"></i></div><div><div class="cc-stat-label">Quá hạn</div><div class="cc-stat-value">{{ number_format($stats['overdue']) }}</div></div></div>
    </div>

    <form method="get" class="cc-card"><div class="cc-card-body cc-filter"><div><label class="cc-label">Tìm kiếm</label><input class="cc-input" name="q" value="{{ request('q') }}" placeholder="Mã ký gửi, khách hàng, Sales..."></div><div><label class="cc-label">Trạng thái</label><select class="cc-select" name="status"><option value="">Tất cả</option>@foreach(\App\Models\CRM\Consignments\CustomerConsignment::STATUS_LABELS as $k=>$v)<option value="{{ $k }}" @selected(request('status')===$k)>{{ $v }}</option>@endforeach</select></div><div class="cc-actions"><button class="cc-btn cc-btn-primary"><i class="bi bi-search"></i> Lọc</button><a class="cc-btn" href="{{ route('customer-consignments.index') }}"><i class="bi bi-x-lg"></i></a></div></div></form>

    <div class="cc-card"><div class="cc-card-head"><div><h2 class="cc-card-title">Danh sách đơn ký gửi</h2><div class="cc-card-sub">{{ number_format($consignments->total()) }} đơn</div></div></div><div class="cc-table-wrap"><table class="cc-table"><thead><tr><th>Mã đơn</th><th>Khách hàng</th><th>Sales</th><th>Số lượng</th><th>Giá trị tham khảo</th><th>Hạn ký gửi</th><th>Trạng thái</th><th></th></tr></thead><tbody>
        @forelse($consignments as $item)<tr>
            <td><a class="cc-code" href="{{ route('customer-consignments.show',$item) }}">{{ $item->code }}</a><div class="cc-secondary">{{ optional($item->created_at)->format('d/m/Y H:i') }}</div></td>
            <td><div class="cc-main">{{ $item->customer->name ?? 'KH #'.$item->customer_id }}</div><div class="cc-secondary">{{ $item->customer->phone ?? '' }}</div></td>
            <td>{{ $item->salesUser->name ?? $item->creator->name ?? '—' }}</td>
            <td><strong>{{ number_format((int)$item->total_quantity) }}</strong><div class="cc-secondary">Đã xuất: {{ number_format((int)$item->issued_quantity) }}</div></td>
            <td class="cc-money">{{ number_format($item->total_value,0,',','.') }} đ</td>
            <td>{{ $item->expires_at?->format('d/m/Y') ?? '—' }}@if($item->status==='active' && $item->expires_at?->isPast())<div class="cc-secondary" style="color:#d6455d">Quá hạn</div>@endif</td>
            <td><span class="cc-badge {{ $tone($item->status) }}">{{ $item->status_label }}</span></td>
            <td><a class="cc-btn cc-btn-sm" href="{{ route('customer-consignments.show',$item) }}">Xem</a></td>
        </tr>@empty<tr><td colspan="8"><div class="cc-empty">Chưa có đơn ký gửi. Bấm “Tạo đơn ký gửi” để Sales lập yêu cầu mới.</div></td></tr>@endforelse
    </tbody></table></div>{{ $consignments->links() }}</div>
</div></div>
@endsection
