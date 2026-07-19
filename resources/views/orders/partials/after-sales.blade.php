<link rel="stylesheet" href="{{ asset('css/order-after-sales.css') }}">
@php
$oasReturns=\App\Models\CRM\Orders\OrderReturn::where('order_id',$order->id)->latest('id')->get();
$oasActive=$oasReturns->whereNotIn('status',['completed','rejected','cancelled'])->count();
@endphp
<div class="oas-after-sales-box">
    <div class="head"><div><strong>Đổi trả & hoàn tiền</strong><div class="oas-muted">Hủy trước xuất kho, thu hồi sau xuất kho, hoàn một phần, xử lý kho và hoàn tiền.</div></div><div class="oas-actions"><a class="oas-btn" href="{{ route('orders.returns.index',$order) }}">Xem hồ sơ</a><a class="oas-btn oas-btn-primary" href="{{ route('orders.returns.create',$order) }}">+ Tạo yêu cầu</a></div></div>
    <div class="body"><div class="oas-mini-grid"><div class="oas-mini"><strong>{{ $oasReturns->count() }}</strong><span>Tổng yêu cầu</span></div><div class="oas-mini"><strong>{{ $oasActive }}</strong><span>Đang xử lý</span></div><div class="oas-mini"><strong>{{ $order->inventory_issued?'Đã trừ tồn':'Chưa xuất' }}</strong><span>Trạng thái kho đơn gốc</span></div></div>@if($oasReturns->count())<div style="margin-top:12px">@foreach($oasReturns->take(3) as $r)<a href="{{ route('order-returns.show',$r) }}" class="oas-badge {{ $r->status==='completed'?'oas-badge-green':'oas-badge-blue' }}" style="margin:3px">{{ $r->return_code }} · {{ $r->status_label }}</a>@endforeach</div>@endif</div>
</div>
