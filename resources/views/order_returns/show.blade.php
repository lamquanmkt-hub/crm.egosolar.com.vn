@extends('layouts.app')
@section('title',$return->return_code ?: 'Phiếu đổi trả')
@section('content')
<link rel="stylesheet" href="{{ asset('css/orders-pro-v2.css') }}?v={{ @filemtime(public_path('css/orders-pro-v2.css')) ?: time() }}">
<script defer src="{{ asset('js/orders-pro-v2.js') }}?v={{ @filemtime(public_path('js/orders-pro-v2.js')) ?: time() }}"></script>
<style>
.ego-v6-action{background:#fff;border:1px solid #dbe5ef;border-radius:16px;box-shadow:0 8px 24px rgba(15,23,42,.05);margin:14px 0;overflow:hidden}
.ego-v6-action-head{display:flex;align-items:flex-start;justify-content:space-between;gap:18px;padding:16px 18px;border-bottom:1px solid #e8eef5;background:linear-gradient(180deg,#fbfdff,#f8fbff)}
.ego-v6-action-title{font-size:16px;font-weight:900;color:#0f172a;margin:0 0 4px}.ego-v6-action-owner{font-size:12px;font-weight:800;color:#2563eb}.ego-v6-action-sub{font-size:13px;color:#64748b;line-height:1.45;margin-top:4px}
.ego-v6-action-body{padding:16px 18px}.ego-v6-action-grid{display:grid;grid-template-columns:minmax(0,1fr) minmax(300px,.8fr);gap:16px;align-items:start}
.ego-v6-context{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:10px}.ego-v6-context-box{border:1px solid #e5ebf2;border-radius:12px;padding:11px 12px;background:#fbfdff}.ego-v6-context-k{font-size:10px;font-weight:900;text-transform:uppercase;letter-spacing:.04em;color:#64748b}.ego-v6-context-v{font-size:14px;font-weight:900;color:#0f172a;margin-top:3px;line-height:1.35}
.ego-v6-actions{border-left:1px solid #e7edf4;padding-left:16px}.ego-v6-actions .erp-btn{min-height:38px}.ego-v6-action-buttons{display:flex;gap:8px;justify-content:flex-end;flex-wrap:wrap;margin-top:10px}
.ego-v6-panel-title{font-size:14px;font-weight:900;color:#0f172a;margin:0}.ego-v6-note{font-size:12px;color:#64748b;line-height:1.5;margin-top:6px}
.ego-v6-main-grid{display:grid;grid-template-columns:minmax(0,1.45fr) minmax(330px,.75fr);gap:14px;margin-top:14px}.ego-v6-main-grid .erp-section{height:100%}
.ego-v6-quick-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:0 18px}.ego-v6-quick-row{display:grid;grid-template-columns:150px minmax(0,1fr);gap:12px;padding:9px 0;border-bottom:1px dashed #e5eaf0}.ego-v6-quick-row span{font-size:11px;font-weight:900;color:#64748b;text-transform:uppercase}.ego-v6-quick-row strong,.ego-v6-quick-row div{font-size:13px;color:#0f172a;line-height:1.4}
.ego-v6-section-gap{margin-top:14px}.ego-v6-fin-grid{display:grid;grid-template-columns:repeat(6,minmax(0,1fr));gap:10px}.ego-v6-fin-box{padding:12px;border:1px solid #e3eaf2;border-radius:12px;background:#fbfdff}.ego-v6-fin-k{font-size:10px;font-weight:900;text-transform:uppercase;color:#64748b}.ego-v6-fin-v{font-size:15px;font-weight:900;color:#0f172a;margin-top:4px}.ego-v6-refund-row{display:flex;align-items:center;justify-content:space-between;gap:12px;padding:10px 0;border-bottom:1px dashed #e5eaf0}.ego-v6-refund-row:last-child{border-bottom:0}
.ego-v6-bottom-grid{display:grid;grid-template-columns:minmax(0,1fr) minmax(0,1fr);gap:14px;margin-top:14px}
.ego-v6-current{display:inline-flex;align-items:center;gap:6px;padding:5px 9px;border-radius:999px;background:#eff6ff;color:#1d4ed8;font-size:11px;font-weight:900}.ego-v6-dot{width:7px;height:7px;border-radius:50%;background:#2563eb}
.ego-v6-inline-form{display:grid;grid-template-columns:1fr 1fr;gap:10px}.ego-v6-inline-form .full{grid-column:1/-1}
.ego-v6-refund-action{border:1px solid #e5eaf0;border-radius:12px;padding:12px;margin-top:10px;background:#fbfdff}
.ego-v7-approval-flow{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:9px;margin:0 0 14px;grid-column:1/-1}
.ego-v7-approval-step{position:relative;border:1px solid #e2e8f0;border-radius:12px;padding:10px 11px;background:#fff;min-height:70px}
.ego-v7-approval-step.done{border-color:#a7f3d0;background:#f0fdf4}.ego-v7-approval-step.current{border-color:#93c5fd;background:#eff6ff;box-shadow:0 0 0 2px rgba(37,99,235,.08)}.ego-v7-approval-step.pending{background:#f8fafc}.ego-v7-approval-step.skipped{background:#f8fafc;opacity:.72}
.ego-v7-approval-status{display:inline-flex;align-items:center;gap:5px;font-size:10px;font-weight:900;text-transform:uppercase;letter-spacing:.03em}.ego-v7-approval-step.done .ego-v7-approval-status{color:#047857}.ego-v7-approval-step.current .ego-v7-approval-status{color:#1d4ed8}.ego-v7-approval-step.pending .ego-v7-approval-status,.ego-v7-approval-step.skipped .ego-v7-approval-status{color:#64748b}
.ego-v7-approval-name{font-size:13px;font-weight:900;color:#0f172a;margin-top:4px}.ego-v7-approval-meta{font-size:11px;color:#64748b;margin-top:3px;line-height:1.35}.ego-v7-approval-help{grid-column:1/-1;padding:9px 11px;border-radius:10px;background:#fffbeb;border:1px solid #fde68a;color:#92400e;font-size:12px;line-height:1.45}
@media(max-width:1100px){.ego-v6-action-grid,.ego-v6-main-grid,.ego-v6-bottom-grid{grid-template-columns:1fr}.ego-v6-actions{border-left:0;border-top:1px solid #e7edf4;padding:14px 0 0}.ego-v6-fin-grid{grid-template-columns:repeat(3,minmax(0,1fr))}}
@media(max-width:720px){.ego-v6-context,.ego-v6-fin-grid,.ego-v6-quick-grid,.ego-v6-inline-form,.ego-v7-approval-flow{grid-template-columns:1fr}.ego-v6-quick-row{grid-template-columns:120px minmax(0,1fr)}.ego-v6-action-head{flex-direction:column}.ego-v6-action-buttons{justify-content:stretch}.ego-v6-action-buttons .erp-btn{flex:1}}
@media print{.erp-head-actions,.ego-v6-action-buttons,.ego-v6-actions form,.ego-v6-upload-form{display:none!important}.erp-page{background:#fff!important}.ego-v6-action,.erp-section{box-shadow:none!important}}
</style>
@php
$user=auth()->user();
$hasRole=function(array $roles) use($user){
    if(method_exists($user,'hasAnyRole')) return $user->hasAnyRole($roles);
    if(method_exists($user,'hasRole')) return $user->hasRole($roles);
    return in_array((string)($user->role??''),$roles,true);
};
$canDo=function(array $permissions,array $roles) use($user,$hasRole){
    foreach($permissions as $permission){
        if(method_exists($user,'can') && $user->can($permission)) return true;
    }
    return $hasRole($roles);
};
$canApprove=($return->status==='pending_sales_manager'&&$hasRole(['admin','sales_manager']))
    ||($return->status==='pending_accounting'&&$hasRole(['admin','accounting','ke_toan']))
    ||($return->status==='pending_management'&&$hasRole(['admin','management']));
$canMarkTransit=$canDo(['orders.return.update'],['admin','sales','sales_manager','warehouse','kho']);
$canReceive=$canDo(['orders.return.receive'],['admin','warehouse','kho']);
$canInspect=$canDo(['orders.return.inspect'],['admin','warehouse','kho']);
$canStockIn=$canDo(['orders.return.stock_in'],['admin','warehouse','kho']);
$canCreateRefund=$canDo(['orders.refund.create'],['admin','accounting','sales_manager']);
$canApproveRefund=$canDo(['orders.refund.approve'],['admin','management','accounting']);
$canProcessRefund=$canDo(['orders.refund.process'],['admin','accounting']);
$canUpload=$hasRole(['admin','management','sales_manager']) || ((int)$return->requested_by===(int)$user->id && in_array($return->status,['draft','revision_requested'],true));

$typeLabels=['return'=>'Hoàn trả hàng','exchange'=>'Đổi hàng','recall'=>'Thu hồi hàng','cancel'=>'Hủy trước xuất kho'];
$statusClass=in_array($return->status,['completed','stocked_in'])?'green':(str_contains((string)$return->status,'pending')?'amber':(in_array($return->status,['rejected','cancelled'])?'red':'blue'));
$currentStep=match($return->status){
    'draft','revision_requested'=>0,
    'pending_sales_manager','pending_accounting','pending_management','rejected'=>1,
    'approved_waiting_return','return_in_transit'=>2,
    'received','inspecting','inspected'=>3,
    'stocked_in','pending_refund','completed'=>4,
    default=>0,
};
$steps=['Tạo yêu cầu','Phê duyệt','Thu hồi hàng','Kho nhận & kiểm tra','Hoàn tất'];

$financeMeta=is_array($return->metadata)?$return->metadata:[];
$hasFinancialPlan=!empty($financeMeta['financial_reconciled_v4_at']);
$acceptedValue=(float)($financeMeta['accepted_return_amount']??$return->total_return_amount);
$netCredit=(float)($financeMeta['net_return_credit']??0);
$livePaidAmount=(float)($return->order?->payments?->sum('amount')??0);
$paidAmount=(float)($financeMeta['payment_snapshot']??$livePaidAmount);
$debtAdjusted=(float)($financeMeta['debt_adjustment_amount']??0);
$orderObligationAfter=(float)($financeMeta['order_obligation_after_return']??0);
$orderTotal=(float)($financeMeta['order_total_snapshot']??($return->order?->total_amount??0));
$plannedRefund=(float)$return->refund_amount;
$refundReserved=(float)$return->refunds->whereNotIn('status',['rejected','cancelled'])->sum('amount');
$refundPaid=(float)$return->refunds->where('status','paid')->sum('amount');
$refundRemaining=max(0,$plannedRefund-$refundReserved);
$orderQtyTotal=(int)($return->order?->items?->sum('quantity')??0);
$currentReturnQtyTotal=(int)$return->items->sum('requested_quantity');
$currentAcceptedQtyTotal=(int)$return->items->sum('accepted_quantity');
$outsideCurrentReturnQty=max(0,$orderQtyTotal-$currentReturnQtyTotal);
$returnItemByOrderItem=$return->items->keyBy('order_item_id');
$paymentLabel=$paidAmount<=0.01?'Chưa thanh toán':($orderTotal>0 && $paidAmount+0.01<$orderTotal?'Thanh toán một phần':'Đã thanh toán');

if($return->type==='exchange'){
    $financeLabel='Không bắt buộc hoàn tiền';
    $financeSub='Tiếp tục nghiệp vụ xuất hàng đổi';
}elseif(!$hasFinancialPlan && $return->inventory_status!=='posted'){
    $financeLabel='Chờ kho đối chiếu';
    $financeSub='Chốt sau khi kho nhận và kiểm tra';
}elseif($return->financial_status==='not_required'){
    $financeLabel='Không cần hoàn tiền';
    $financeSub=$debtAdjusted>0?'Đã giảm công nợ '.number_format($debtAdjusted,0,',','.').' đ':'Không phát sinh tiền trả lại';
}elseif($return->financial_status==='paid'){
    $financeLabel='Đã hoàn tiền';
    $financeSub=number_format($refundPaid,0,',','.').' đ đã xử lý';
}elseif($return->financial_status==='partial'){
    $financeLabel='Đang hoàn một phần';
    $financeSub='Còn '.number_format(max(0,$plannedRefund-$refundPaid),0,',','.').' đ';
}else{
    $financeLabel='Chờ xử lý tài chính';
    $financeSub='Cần hoàn/cấn '.number_format($plannedRefund,0,',','.').' đ';
}

$approvalActor=match($return->status){
    'pending_sales_manager'=>'Sales Manager',
    'pending_accounting'=>'Kế toán',
    'pending_management'=>'Ban Giám đốc',
    default=>'—',
};
$approvalNeedsManagement=((float)$return->refund_amount>=10000000 || $return->type==='exchange');
$approvalHistory=$return->approvals->where('action','approve')->keyBy('level');
$approvalStages=[
    ['key'=>'pending_sales_manager','label'=>'Sales Manager','required'=>true],
    ['key'=>'pending_accounting','label'=>'Kế toán','required'=>true],
    ['key'=>'pending_management','label'=>'Ban Giám đốc','required'=>$approvalNeedsManagement],
];
$approvalButtonLabel=match($return->status){
    'pending_sales_manager'=>'Duyệt Sales Manager → chuyển Kế toán',
    'pending_accounting'=>$approvalNeedsManagement?'Duyệt Kế toán → chuyển Ban Giám đốc':'Duyệt Kế toán → chuyển Thu hồi hàng',
    'pending_management'=>'Ban Giám đốc duyệt → chuyển Thu hồi hàng',
    default=>'Phê duyệt',
};
$actionOwner=match($return->status){
    'draft','revision_requested'=>'Người tạo phiếu',
    'pending_sales_manager','pending_accounting','pending_management'=>$approvalActor,
    'approved_waiting_return'=>'Sales / Kho phối hợp',
    'return_in_transit'=>'Kho nhận hàng',
    'received','inspecting','inspected'=>'Kho',
    'pending_refund'=>'Kế toán',
    'stocked_in'=>$return->type==='exchange'?'Sales / Kho xuất hàng đổi':'Hệ thống',
    'completed'=>'Hoàn tất',
    'rejected'=>'Đã từ chối',
    default=>'Bộ phận phụ trách',
};
$actionTitle=match($return->status){
    'draft'=>'Hoàn thiện yêu cầu và gửi duyệt',
    'revision_requested'=>'Chỉnh sửa theo yêu cầu và gửi duyệt lại',
    'pending_sales_manager','pending_accounting','pending_management'=>'Phê duyệt yêu cầu hoàn trả · '.$approvalActor,
    'approved_waiting_return'=>'Tổ chức thu hồi / tiếp nhận hàng',
    'return_in_transit'=>'Xác nhận hàng đã về kho',
    'received','inspecting'=>'Kiểm tra và phân loại hàng hoàn',
    'inspected'=>'Xác nhận nhập hoàn kho',
    'pending_refund'=>'Xử lý hoàn tiền / cấn trừ',
    'stocked_in'=>$return->type==='exchange'?'Tiếp tục xuất hàng đổi':'Đã chốt kho',
    'completed'=>'Phiếu đã hoàn tất',
    'rejected'=>'Phiếu đã bị từ chối',
    default=>'Theo dõi xử lý phiếu',
};
$actionSub=match($return->status){
    'draft'=>'Phiếu đang ở bản nháp. Kiểm tra sản phẩm, số lượng và lý do trước khi gửi.',
    'revision_requested'=>'Phiếu đã được trả lại để chỉnh sửa. Hoàn thiện nội dung rồi gửi lại quy trình duyệt.',
    'pending_sales_manager','pending_accounting','pending_management'=>'Mỗi lần bấm chỉ duyệt đúng cấp đang chờ. Chuỗi duyệt bên dưới cho biết cấp nào đã xong và cấp nào tiếp theo.',
    'approved_waiting_return'=>'Yêu cầu đã duyệt. Có thể cập nhật đang vận chuyển hoặc kho xác nhận nhận hàng trực tiếp.',
    'return_in_transit'=>'Hàng đang trên đường về. Kho nhập số lượng thực nhận và chọn kho nhận tại đây.',
    'received','inspecting'=>'Kho ghi số lượng đạt/từ chối, tình trạng và phương án xử lý ngay tại đây.',
    'inspected'=>'Kết quả kiểm tra đã có. Nhập hoàn kho để hệ thống tự đối chiếu tài chính.',
    'pending_refund'=>'Kho đã xong. Chỉ xử lý số tiền thực tế khách đã trả dư sau khi đối chiếu.',
    'stocked_in'=>'Kho đã xử lý hàng. Theo dõi bước nghiệp vụ tiếp theo theo loại phiếu.',
    'completed'=>'Không còn thao tác bắt buộc. Có thể xem chứng từ và lịch sử ở phía dưới.',
    'rejected'=>'Không còn thao tác xử lý trên phiếu này.',
    default=>'Theo dõi trạng thái và người phụ trách ngay trên màn hình.',
};
@endphp
<div class="erp-page"><div class="erp-container">
    <div class="erp-detail-header">
        <div class="erp-detail-row">
            <div>
                <div class="erp-breadcrumb"><a href="{{ route('order-returns.dashboard') }}">Đổi trả & hoàn tiền</a><span>/</span><a href="{{ route('orders.show',$return->order_id) }}">{{ $return->order->order_code ?? '#'.$return->order_id }}</a><span>/</span><strong>{{ $return->return_code }}</strong></div>
                <div style="display:flex;gap:9px;align-items:center;flex-wrap:wrap"><h1 class="erp-detail-title">{{ $return->return_code }}</h1><span class="erp-badge {{ $statusClass }}">{{ $return->status_label }}</span></div>
                <div class="erp-detail-meta"><span><i class="bi bi-arrow-left-right"></i>{{ $typeLabels[$return->type] ?? $return->type }}</span><span><i class="bi bi-calendar3"></i>{{ optional($return->created_at)->format('d/m/Y H:i') }}</span><span><i class="bi bi-person"></i>{{ $return->requester->name ?? '—' }}</span></div>
            </div>
            <div class="erp-head-actions">
                <a class="erp-btn" href="{{ route('order-returns.dashboard') }}"><i class="bi bi-arrow-left"></i> Danh sách</a>
                <a class="erp-btn" href="{{ route('orders.show',$return->order_id) }}"><i class="bi bi-receipt"></i> Đơn gốc</a>
                <button type="button" class="erp-btn" onclick="window.print()"><i class="bi bi-printer"></i> In phiếu</button>
            </div>
        </div>
    </div>

    @if(session('success'))<div class="erp-alert erp-alert-success">{{ session('success') }}</div>@endif
    @if(session('error'))<div class="erp-alert erp-alert-danger">{{ session('error') }}</div>@endif
    @if($errors->any())<div class="erp-alert erp-alert-danger"><strong>Vui lòng kiểm tra:</strong><ul>@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>@endif

    {{-- V7 ACTION CENTER: hiện rõ chuỗi phê duyệt và cấp tiếp theo. --}}
    <section class="ego-v6-action" id="xu-ly-ngay">
        <div class="ego-v6-action-head">
            <div>
                <div class="ego-v6-current"><span class="ego-v6-dot"></span> Hành động cần xử lý</div>
                <h2 class="ego-v6-action-title">{{ $actionTitle }}</h2>
                <div class="ego-v6-action-owner">Đang chờ: {{ $actionOwner }}</div>
                <div class="ego-v6-action-sub">{{ $actionSub }}</div>
            </div>
            <span class="erp-badge {{ $statusClass }}">{{ $return->status_label }}</span>
        </div>
        <div class="ego-v6-action-body">
            <div class="ego-v6-action-grid">
                @if(in_array($return->status,['pending_sales_manager','pending_accounting','pending_management']) || $return->approvals->where('action','approve')->isNotEmpty())
                    <div class="ego-v7-approval-flow">
                        @foreach($approvalStages as $approvalStage)
                            @php
                                $approvalRecord=$approvalHistory->get($approvalStage['key']);
                                $approvalState=!$approvalStage['required']?'skipped':($approvalRecord?'done':($return->status===$approvalStage['key']?'current':'pending'));
                                $approvalStateText=match($approvalState){'done'=>'Đã duyệt','current'=>'Đang chờ','skipped'=>'Không yêu cầu',default=>'Chưa đến'};
                            @endphp
                            <div class="ego-v7-approval-step {{ $approvalState }}">
                                <div class="ego-v7-approval-status">
                                    @if($approvalState==='done')<i class="bi bi-check-circle-fill"></i>@elseif($approvalState==='current')<i class="bi bi-arrow-right-circle-fill"></i>@else<i class="bi bi-circle"></i>@endif
                                    {{ $approvalStateText }}
                                </div>
                                <div class="ego-v7-approval-name">{{ $approvalStage['label'] }}</div>
                                <div class="ego-v7-approval-meta">
                                    @if($approvalRecord)
                                        {{ $approvalRecord->approver->name ?? '—' }} · {{ optional($approvalRecord->created_at)->format('d/m H:i') }}
                                    @elseif($approvalState==='current')
                                        Cấp phê duyệt hiện tại
                                    @elseif($approvalState==='skipped')
                                        Không bắt buộc với phiếu này
                                    @else
                                        Chờ cấp trước hoàn tất
                                    @endif
                                </div>
                            </div>
                        @endforeach
                        @if(in_array($return->status,['pending_sales_manager','pending_accounting','pending_management']))
                            <div class="ego-v7-approval-help"><strong>Lưu ý:</strong> Bước lớn “Phê duyệt” chỉ chuyển sang “Thu hồi hàng” sau khi các cấp bắt buộc phía trên đã duyệt xong. Bấm nút bên phải sẽ chuyển ngay sang cấp kế tiếp và thông báo rõ trạng thái mới.</div>
                        @endif
                    </div>
                @endif
                <div class="ego-v6-context">
                    <div class="ego-v6-context-box"><div class="ego-v6-context-k">Khách hàng</div><div class="ego-v6-context-v">{{ $return->order->lead->customer->name ?? '—' }}</div></div>
                    <div class="ego-v6-context-box"><div class="ego-v6-context-k">Yêu cầu</div><div class="ego-v6-context-v">Hoàn {{ $currentReturnQtyTotal }}/{{ $orderQtyTotal }} sản phẩm</div></div>
                    <div class="ego-v6-context-box"><div class="ego-v6-context-k">Giá trị dự kiến</div><div class="ego-v6-context-v">{{ number_format((float)$return->total_return_amount,0,',','.') }} đ</div></div>
                    <div class="ego-v6-context-box"><div class="ego-v6-context-k">Lý do</div><div class="ego-v6-context-v">{{ $return->reason_detail ?: $return->reason }}</div></div>
                    <div class="ego-v6-context-box"><div class="ego-v6-context-k">Kho nhận</div><div class="ego-v6-context-v">{{ $return->receivingWarehouse->name ?? 'Chưa chọn' }}</div></div>
                    <div class="ego-v6-context-box"><div class="ego-v6-context-k">Thanh toán</div><div class="ego-v6-context-v">{{ $paymentLabel }} · {{ number_format($paidAmount,0,',','.') }} đ</div></div>
                </div>
                <div class="ego-v6-actions">
                    @if(in_array($return->status,['draft','revision_requested']))
                        <h3 class="ego-v6-panel-title">Gửi vào quy trình duyệt</h3>
                        <div class="ego-v6-note">Kiểm tra số lượng hoàn {{ $currentReturnQtyTotal }}/{{ $orderQtyTotal }} và thông tin lý do trước khi gửi.</div>
                        <form method="post" action="{{ route('order-returns.submit',$return) }}" style="margin-top:10px">@csrf<button class="erp-btn erp-btn-primary" style="width:100%"><i class="bi bi-send"></i> Gửi duyệt</button></form>
                    @elseif(in_array($return->status,['pending_sales_manager','pending_accounting','pending_management']))
                        <h3 class="ego-v6-panel-title">Ý kiến phê duyệt</h3>
                        @if($canApprove)
                            <form method="post" action="{{ route('order-returns.approve',$return) }}" style="margin-top:8px">
                                @csrf
                                <textarea class="erp-textarea" name="comment" placeholder="Nhập ý kiến duyệt hoặc lý do khi yêu cầu chỉnh sửa / từ chối"></textarea>
                                <div class="ego-v6-action-buttons">
                                    <button class="erp-btn erp-btn-warning" formaction="{{ route('order-returns.revision',$return) }}" onclick="this.form.querySelector('[name=comment]').required=true" data-confirm="Gửi yêu cầu chỉnh sửa phiếu này?">Yêu cầu chỉnh sửa</button>
                                    <button class="erp-btn erp-btn-danger" formaction="{{ route('order-returns.reject',$return) }}" onclick="this.form.querySelector('[name=comment]').required=true" data-confirm="Xác nhận từ chối phiếu này?">Từ chối</button>
                                    <button type="submit" class="erp-btn erp-btn-primary" onclick="this.form.querySelector('[name=comment]').required=false"><i class="bi bi-check2-circle"></i> {{ $approvalButtonLabel }}</button>
                                </div>
                            </form>
                        @else
                            <div class="erp-alert erp-alert-warning" style="margin:8px 0 0">Phiếu đang chờ <strong>{{ $approvalActor }}</strong>. Tài khoản của bạn chỉ xem, chưa có quyền duyệt ở bước này.</div>
                        @endif
                    @elseif($return->status==='approved_waiting_return')
                        <h3 class="ego-v6-panel-title">Thu hồi / nhận hàng</h3>
                        @if($canMarkTransit)
                            <form method="post" action="{{ route('order-returns.in-transit',$return) }}" style="margin-top:8px">@csrf<button class="erp-btn" style="width:100%"><i class="bi bi-truck"></i> Đánh dấu hàng đang vận chuyển về</button></form>
                        @endif
                        @if($canReceive)
                            <form method="post" action="{{ route('order-returns.receive',$return) }}" style="margin-top:10px">@csrf
                                <div class="ego-v6-inline-form">
                                    <div class="full"><label class="erp-label">Kho nhận</label><select class="erp-select" name="receiving_warehouse_id" required><option value="">Chọn kho</option>@foreach($warehouses as $w)<option value="{{ $w->id }}" @selected($return->receiving_warehouse_id==$w->id)>{{ $w->name }}</option>@endforeach</select></div>
                                    @foreach($return->items as $item)<div><label class="erp-label">{{ $item->orderItem->product_name ?? ('SP #'.$item->product_id) }} · thực nhận</label><input class="erp-input" type="number" min="0" max="{{ $item->requested_quantity }}" name="received[{{ $item->id }}]" value="{{ $item->requested_quantity }}"></div>@endforeach
                                </div>
                                <button class="erp-btn erp-btn-primary" style="width:100%;margin-top:10px">Xác nhận kho đã nhận</button>
                            </form>
                        @elseif(!$canMarkTransit)
                            <div class="erp-alert erp-alert-warning" style="margin:8px 0 0">Chờ Sales/Kho phối hợp thu hồi hàng.</div>
                        @endif
                    @elseif($return->status==='return_in_transit')
                        <h3 class="ego-v6-panel-title">Kho xác nhận thực nhận</h3>
                        @if($canReceive)
                            <form method="post" action="{{ route('order-returns.receive',$return) }}" style="margin-top:8px">@csrf
                                <div class="ego-v6-inline-form">
                                    <div class="full"><label class="erp-label">Kho nhận</label><select class="erp-select" name="receiving_warehouse_id" required><option value="">Chọn kho</option>@foreach($warehouses as $w)<option value="{{ $w->id }}" @selected($return->receiving_warehouse_id==$w->id)>{{ $w->name }}</option>@endforeach</select></div>
                                    @foreach($return->items as $item)<div><label class="erp-label">{{ $item->orderItem->product_name ?? ('SP #'.$item->product_id) }} · thực nhận</label><input class="erp-input" type="number" min="0" max="{{ $item->requested_quantity }}" name="received[{{ $item->id }}]" value="{{ $item->requested_quantity }}"></div>@endforeach
                                </div>
                                <button class="erp-btn erp-btn-primary" style="width:100%;margin-top:10px">Xác nhận đã nhận hàng</button>
                            </form>
                        @else<div class="erp-alert erp-alert-warning" style="margin:8px 0 0">Hàng đang vận chuyển. Chờ tài khoản Kho xác nhận nhận hàng.</div>@endif
                    @elseif(in_array($return->status,['received','inspecting']))
                        <h3 class="ego-v6-panel-title">Kiểm tra & phân loại hàng</h3>
                        @if($canInspect)
                            <form method="post" action="{{ route('order-returns.inspect',$return) }}" style="margin-top:8px">@csrf
                                @foreach($return->items as $item)
                                    <div class="ego-v6-refund-action">
                                        <strong>{{ $item->orderItem->product_name ?? ('SP #'.$item->product_id) }} · đã nhận {{ $item->received_quantity }}</strong>
                                        <div class="ego-v6-inline-form" style="margin-top:8px">
                                            <div><label class="erp-label">SL chấp nhận</label><input class="erp-input" type="number" min="0" max="{{ $item->received_quantity }}" name="inspect[{{ $item->id }}][accepted_quantity]" value="{{ $item->received_quantity }}"></div>
                                            <div><label class="erp-label">SL từ chối</label><input class="erp-input" type="number" min="0" max="{{ $item->received_quantity }}" name="inspect[{{ $item->id }}][rejected_quantity]" value="0"></div>
                                            <div><label class="erp-label">Tình trạng</label><select class="erp-select" name="inspect[{{ $item->id }}][condition]"><option value="sellable">Còn tốt - bán lại</option><option value="opened_box">Đã mở hộp</option><option value="defective">Lỗi</option><option value="warranty_pending">Chờ bảo hành</option><option value="damaged">Hư hỏng</option><option value="scrap">Thanh lý</option></select></div>
                                            <div><label class="erp-label">Phương án</label><select class="erp-select" name="inspect[{{ $item->id }}][resolution]"><option value="restock">Nhập kho</option><option value="exchange">Đổi hàng</option><option value="warranty">Bảo hành</option><option value="refund">Hoàn tiền</option><option value="reject">Từ chối nhận</option></select></div>
                                        </div>
                                    </div>
                                @endforeach
                                <button class="erp-btn erp-btn-primary" style="width:100%;margin-top:10px">Lưu kết quả kiểm tra</button>
                            </form>
                        @else<div class="erp-alert erp-alert-warning" style="margin:8px 0 0">Kho đang kiểm tra hàng. Tài khoản của bạn không có quyền cập nhật bước này.</div>@endif
                    @elseif($return->status==='inspected')
                        <h3 class="ego-v6-panel-title">Nhập hoàn kho</h3>
                        <div class="ego-v6-note">Chỉ hàng đạt chuẩn bán lại mới được cộng tồn. Sau bước này hệ thống tự đối chiếu tiền đã thanh toán.</div>
                        @if($canStockIn)<form method="post" action="{{ route('order-returns.stock-in',$return) }}" data-confirm="Xác nhận nhập hoàn kho? Thao tác này được chống ghi nhận hai lần." style="margin-top:10px">@csrf<button class="erp-btn erp-btn-primary" style="width:100%">Xác nhận nhập hoàn kho</button></form>@else<div class="erp-alert erp-alert-warning" style="margin:8px 0 0">Chờ Kho xác nhận nhập hoàn.</div>@endif
                    @elseif($return->status==='pending_refund')
                        <h3 class="ego-v6-panel-title">Tài chính cần xử lý</h3>
                        <div class="ego-v6-note">Khách đã thanh toán {{ number_format($paidAmount,0,',','.') }} đ · cần hoàn/cấn {{ number_format($plannedRefund,0,',','.') }} đ.</div>
                        @if($canCreateRefund && $refundRemaining>0.01)
                            <form method="post" action="{{ route('order-returns.refunds.store',$return) }}" style="margin-top:10px">@csrf
                                <div class="ego-v6-inline-form"><div><label class="erp-label">Số tiền</label><input class="erp-input" type="number" min="1" max="{{ $refundRemaining }}" name="amount" value="{{ $refundRemaining }}"></div><div><label class="erp-label">Hình thức</label><select class="erp-select" name="method"><option value="bank">Chuyển khoản</option><option value="cash">Tiền mặt</option><option value="debt_credit">Cấn trừ công nợ</option><option value="exchange_credit">Cấn đơn đổi</option></select></div><div class="full"><label class="erp-label">Ghi chú</label><textarea class="erp-textarea" name="note"></textarea></div></div>
                                <button class="erp-btn erp-btn-primary" style="width:100%;margin-top:8px">Tạo phiếu tài chính</button>
                            </form>
                        @endif
                        @foreach($return->refunds as $refund)
                            @if($refund->status==='pending_accounting'&&$canApproveRefund)
                                <div class="ego-v6-refund-action"><strong>{{ $refund->refund_code }}</strong> · {{ number_format((float)$refund->amount,0,',','.') }} đ<form method="post" action="{{ route('order-refunds.approve',$refund) }}" style="margin-top:8px">@csrf<button class="erp-btn erp-btn-primary" style="width:100%">Duyệt phiếu tài chính</button></form></div>
                            @elseif($refund->status==='approved'&&$canProcessRefund)
                                <div class="ego-v6-refund-action"><strong>{{ $refund->refund_code }}</strong> · {{ number_format((float)$refund->amount,0,',','.') }} đ<form method="post" action="{{ route('order-refunds.process',$refund) }}" enctype="multipart/form-data" style="margin-top:8px">@csrf<input class="erp-input" type="file" name="attachment"><button class="erp-btn erp-btn-primary" style="width:100%;margin-top:8px">Xác nhận đã hoàn/cấn tiền</button></form></div>
                            @endif
                        @endforeach
                        @if(!$canCreateRefund && !$canApproveRefund && !$canProcessRefund)<div class="erp-alert erp-alert-warning" style="margin:8px 0 0">Chờ Kế toán xử lý tài chính.</div>@endif
                    @elseif($return->status==='stocked_in' && $return->type==='exchange')
                        <div class="erp-alert erp-alert-success" style="margin:0"><strong>Kho đã nhận hàng thu hồi.</strong><br>Phiếu đổi hàng không bắt buộc hoàn tiền. Tiếp tục xử lý xuất hàng đổi từ đơn gốc.</div>
                        <a class="erp-btn erp-btn-primary" href="{{ route('orders.show',$return->order_id) }}" style="width:100%;margin-top:10px">Mở đơn gốc để tiếp tục</a>
                    @elseif($return->status==='completed')
                        <div class="erp-alert erp-alert-success" style="margin:0"><strong>Đã hoàn tất.</strong><br>{{ $financeSub }}</div>
                    @elseif($return->status==='rejected')
                        <div class="erp-alert erp-alert-danger" style="margin:0"><strong>Phiếu đã bị từ chối.</strong> Xem lịch sử phê duyệt bên dưới để biết lý do.</div>
                    @else
                        <div class="erp-alert erp-alert-warning" style="margin:0">Không có thao tác bắt buộc cho tài khoản này ở trạng thái hiện tại.</div>
                    @endif
                </div>
            </div>
        </div>
    </section>

    <div class="erp-stepper"><div class="erp-return-flow">@foreach($steps as $idx=>$label)<div class="erp-return-step {{ $idx<$currentStep?'done':($idx===$currentStep?'current':'') }}"><div class="erp-return-step-dot">{{ $idx<$currentStep?'✓':($idx+1) }}</div><div class="erp-return-step-label">{{ $label }}</div></div>@endforeach</div></div>

    <div class="erp-summary">
        <div class="erp-summary-item"><div class="erp-summary-k">Đơn gốc</div><div class="erp-summary-v">{{ $orderQtyTotal }} sản phẩm</div><div class="erp-summary-s">{{ $return->order->order_code ?? '#'.$return->order_id }}</div></div>
        <div class="erp-summary-item"><div class="erp-summary-k">Hoàn lần này</div><div class="erp-summary-v">{{ $currentReturnQtyTotal }} sản phẩm</div><div class="erp-summary-s">Ngoài phiếu {{ $outsideCurrentReturnQty }} sản phẩm</div></div>
        <div class="erp-summary-item"><div class="erp-summary-k">Giá trị hàng hoàn</div><div class="erp-summary-v">{{ number_format($hasFinancialPlan?$acceptedValue:(float)$return->total_return_amount,0,',','.') }} đ</div><div class="erp-summary-s">{{ $hasFinancialPlan?'Theo kho chấp nhận':'Tạm tính theo yêu cầu' }}</div></div>
        <div class="erp-summary-item"><div class="erp-summary-k">Thanh toán</div><div class="erp-summary-v">{{ $paymentLabel }}</div><div class="erp-summary-s">Đã thu {{ number_format($paidAmount,0,',','.') }} / {{ number_format($orderTotal,0,',','.') }} đ</div></div>
        <div class="erp-summary-item"><div class="erp-summary-k">Tài chính</div><div class="erp-summary-v">{{ $financeLabel }}</div><div class="erp-summary-s">{{ $financeSub }}</div></div>
    </div>

    <div class="ego-v6-main-grid">
        <section class="erp-section">
            <div class="erp-section-head"><h2 class="erp-section-title">Thông tin chính của phiếu</h2><span class="erp-badge gray">Hiện trực tiếp</span></div>
            <div class="erp-section-body"><div class="ego-v6-quick-grid">
                <div class="ego-v6-quick-row"><span>Đơn hàng gốc</span><strong><a href="{{ route('orders.show',$return->order_id) }}">{{ $return->order->order_code }}</a></strong></div>
                <div class="ego-v6-quick-row"><span>Khách hàng</span><strong>{{ $return->order->lead->customer->name ?? '—' }}</strong></div>
                <div class="ego-v6-quick-row"><span>Người tạo</span><strong>{{ $return->requester->name ?? '—' }}</strong></div>
                <div class="ego-v6-quick-row"><span>Ngày tạo</span><strong>{{ optional($return->created_at)->format('d/m/Y H:i') }}</strong></div>
                <div class="ego-v6-quick-row"><span>Loại xử lý</span><strong>{{ $typeLabels[$return->type] ?? $return->type }}</strong></div>
                <div class="ego-v6-quick-row"><span>Trạng thái</span><strong>{{ $return->status_label }}</strong></div>
                <div class="ego-v6-quick-row"><span>Lý do</span><div>{{ $return->reason_detail ?: $return->reason }}</div></div>
                <div class="ego-v6-quick-row"><span>Kho dự kiến</span><strong>{{ $return->receivingWarehouse->name ?? 'Chưa chọn' }}</strong></div>
                <div class="ego-v6-quick-row"><span>Ghi chú</span><div>{{ $return->note ?: '—' }}</div></div>
                <div class="ego-v6-quick-row"><span>Hồ sơ</span><strong>{{ $return->attachments->count() }} file · {{ $return->histories->count() }} sự kiện</strong></div>
            </div></div>
        </section>
        <section class="erp-section">
            <div class="erp-section-head"><h2 class="erp-section-title">Tình trạng xử lý</h2></div>
            <div class="erp-section-body">
                <div class="erp-kv-line"><span>Người/bộ phận đang chờ</span><strong>{{ $actionOwner }}</strong></div>
                <div class="erp-kv-line"><span>Trạng thái kho</span><strong>{{ $return->inventory_status ?? '—' }}</strong></div>
                <div class="erp-kv-line"><span>Kho nhận</span><strong>{{ $return->receivingWarehouse->name ?? 'Chưa chọn' }}</strong></div>
                <div class="erp-kv-line"><span>Trạng thái tài chính</span><strong>{{ $financeLabel }}</strong></div>
                <div class="erp-kv-line"><span>Điều chỉnh hóa đơn</span><strong>{{ $return->invoice_adjustment_status ?? '—' }}</strong></div>
                <div class="erp-alert erp-alert-warning" style="margin:12px 0 0"><strong>Kiểm soát tồn kho:</strong> không cộng tồn trực tiếp. Chỉ nhập kho sau khi xác nhận nhận hàng, kiểm tra và tạo transaction đối ứng.</div>
            </div>
        </section>
    </div>

    <section class="erp-section ego-v6-section-gap" id="san-pham-hoan">
        <div class="erp-section-head"><h2 class="erp-section-title">Sản phẩm: đơn gốc và số lượng hoàn</h2><span class="erp-badge blue">Đơn {{ $orderQtyTotal }} · Hoàn {{ $currentReturnQtyTotal }}</span></div>
        <div class="erp-table-wrap"><table class="erp-table" style="min-width:1050px"><thead><tr><th>Sản phẩm</th><th>SL đơn gốc</th><th>Hoàn lần này</th><th>Không hoàn</th><th>Serial hoàn</th><th>Đã nhận</th><th>Chấp nhận</th><th>Tình trạng / phương án</th><th>Giá trị hoàn</th></tr></thead><tbody>
            @forelse($return->order->items as $orderItem)
                @php
                    $ri=$returnItemByOrderItem->get($orderItem->id);
                    $originalQty=(int)$orderItem->quantity;
                    $requestedQty=(int)($ri->requested_quantity??0);
                    $outsideQty=max(0,$originalQty-$requestedQty);
                @endphp
                <tr>
                    <td><div class="erp-primary-text">{{ $orderItem->product_name ?? $orderItem->product->name ?? ('SP #'.$orderItem->product_id) }}</div><div class="erp-secondary-text">Dòng đơn #{{ $orderItem->id }}</div></td>
                    <td><strong>{{ $originalQty }}</strong></td><td><strong>{{ $requestedQty }}</strong></td><td>{{ $outsideQty }}</td>
                    <td>@if($ri && $ri->serials->count())@foreach($ri->serials as $s)<span class="erp-badge gray" style="margin:2px">{{ $s->serialUnit->primary_code ?? '#'.$s->serial_unit_id }}</span>@endforeach @else — @endif</td>
                    <td>{{ (int)($ri->received_quantity??0) }}</td><td>{{ (int)($ri->accepted_quantity??0) }}</td>
                    <td>@if($ri)<span class="erp-badge {{ $ri->condition==='sellable'?'green':($ri->condition?'amber':'gray') }}">{{ $ri->condition ?: 'Chờ kiểm tra' }}</span><div class="erp-secondary-text" style="margin-top:4px">{{ $ri->resolution ?: '—' }}</div>@else<span class="erp-badge gray">Không hoàn phiếu này</span>@endif</td>
                    <td class="erp-money">{{ number_format((float)($ri->return_amount??0),0,',','.') }} đ</td>
                </tr>
            @empty<tr><td colspan="9"><div class="erp-empty">Đơn gốc không có dòng sản phẩm.</div></td></tr>@endforelse
        </tbody></table></div>
    </section>

    <section class="erp-section ego-v6-section-gap" id="tai-chinh">
        <div class="erp-section-head"><h2 class="erp-section-title">Tài chính & công nợ</h2><span class="erp-badge {{ $return->financial_status==='not_required'||$return->financial_status==='paid'?'green':'amber' }}">{{ $financeLabel }}</span></div>
        <div class="erp-section-body">
            @if(!$hasFinancialPlan && $return->inventory_status!=='posted')<div class="erp-alert erp-alert-warning" style="margin:0 0 12px"><strong>Hoàn hàng không phụ thuộc việc khách đã thanh toán hay chưa.</strong> Sau khi kho nhập hoàn, hệ thống mới chốt giảm công nợ hoặc số tiền phải hoàn thực tế.</div>
            @elseif($return->financial_status==='not_required')<div class="erp-alert erp-alert-success" style="margin:0 0 12px"><strong>Không cần hoàn tiền.</strong> {{ $debtAdjusted>0?'Đã giảm công nợ theo hàng thực nhận.':'Không phát sinh tiền trả ngược cho khách.' }}</div>
            @elseif($return->type==='exchange')<div class="erp-alert erp-alert-success" style="margin:0 0 12px"><strong>Đổi hàng:</strong> không bắt buộc hoàn tiền trong module này.</div>
            @endif
            <div class="ego-v6-fin-grid">
                <div class="ego-v6-fin-box"><div class="ego-v6-fin-k">Giá trị đơn</div><div class="ego-v6-fin-v">{{ number_format($orderTotal,0,',','.') }} đ</div></div>
                <div class="ego-v6-fin-box"><div class="ego-v6-fin-k">Khách đã thanh toán</div><div class="ego-v6-fin-v">{{ number_format($paidAmount,0,',','.') }} đ</div></div>
                <div class="ego-v6-fin-box"><div class="ego-v6-fin-k">Hàng kho chấp nhận</div><div class="ego-v6-fin-v">{{ number_format($hasFinancialPlan?$acceptedValue:(float)$return->total_return_amount,0,',','.') }} đ</div></div>
                <div class="ego-v6-fin-box"><div class="ego-v6-fin-k">Giảm công nợ</div><div class="ego-v6-fin-v">{{ number_format($debtAdjusted,0,',','.') }} đ</div></div>
                <div class="ego-v6-fin-box"><div class="ego-v6-fin-k">Cần hoàn / cấn</div><div class="ego-v6-fin-v">{{ number_format($plannedRefund,0,',','.') }} đ</div></div>
                <div class="ego-v6-fin-box"><div class="ego-v6-fin-k">Đã hoàn / cấn</div><div class="ego-v6-fin-v">{{ number_format($refundPaid,0,',','.') }} đ</div></div>
            </div>
            @if($hasFinancialPlan && $orderObligationAfter>0)<div class="erp-kv-line" style="margin-top:10px"><span>Nghĩa vụ đơn sau hoàn</span><strong>{{ number_format($orderObligationAfter,0,',','.') }} đ</strong></div>@endif
            <div style="margin-top:12px">
                @forelse($return->refunds as $refund)<div class="ego-v6-refund-row"><div><strong>{{ $refund->refund_code }}</strong><div class="erp-muted">{{ $refund->method }} · {{ optional($refund->created_at)->format('d/m/Y H:i') }}</div></div><div style="text-align:right"><span class="erp-badge {{ $refund->status==='paid'?'green':'amber' }}">{{ $refund->status }}</span><div class="erp-money" style="margin-top:4px">{{ number_format((float)$refund->amount,0,',','.') }} đ</div></div></div>@empty<div class="erp-muted">Chưa có phiếu hoàn tiền/cấn trừ. Nếu khách chưa thanh toán dư thì đây là trạng thái bình thường.</div>@endforelse
            </div>
        </div>
    </section>

    <section class="erp-section ego-v6-section-gap" id="phe-duyet">
        <div class="erp-section-head"><h2 class="erp-section-title">Lịch sử phê duyệt</h2><span class="erp-badge gray">{{ $return->approvals->count() }} lượt</span></div>
        <div class="erp-section-body"><div class="erp-timeline">@forelse($return->approvals as $a)<div class="erp-event"><div class="erp-event-title">{{ $a->action ?? $a->status }}</div><div>{{ $a->comment ?? '—' }}</div><div class="erp-event-meta">{{ $a->approver->name ?? '—' }} · {{ optional($a->created_at)->format('d/m/Y H:i') }}</div></div>@empty<div class="erp-muted">Chưa có lịch sử phê duyệt.</div>@endforelse</div></div>
    </section>

    <div class="ego-v6-bottom-grid">
        <section class="erp-section" id="chung-tu">
            <div class="erp-section-head"><h2 class="erp-section-title">Chứng từ & hồ sơ</h2><span class="erp-badge gray">{{ $return->attachments->count() }} file</span></div>
            <div class="erp-section-body">
                @if($canUpload)<form class="ego-v6-upload-form" method="post" action="{{ route('order-returns.upload',$return) }}" enctype="multipart/form-data" style="margin-bottom:12px">@csrf<div class="ego-v6-inline-form"><div><label class="erp-label">Loại file</label><select class="erp-select" name="category"><option value="evidence">Bằng chứng</option><option value="shipping">Vận chuyển</option><option value="inspection">Kiểm tra</option><option value="invoice">Hóa đơn</option><option value="refund">Hoàn tiền</option><option value="other">Khác</option></select></div><div><label class="erp-label">Chọn file</label><input class="erp-input" type="file" name="files[]" multiple required></div></div><button class="erp-btn erp-btn-primary" style="margin-top:8px">Tải hồ sơ lên</button></form>@endif
                <div class="erp-list">@forelse($return->attachments as $file)<div class="erp-list-item"><div class="erp-list-top"><div><strong>{{ $file->original_name }}</strong><div class="erp-muted">{{ $file->category }} · {{ number_format(($file->file_size??0)/1024,1) }} KB</div></div><a class="erp-btn erp-btn-sm" href="{{ route('order-returns.attachments.download',$file) }}"><i class="bi bi-download"></i> Tải</a></div></div>@empty<div class="erp-muted">Chưa có file.</div>@endforelse</div>
            </div>
        </section>
        <section class="erp-section" id="lich-su">
            <div class="erp-section-head"><h2 class="erp-section-title">Lịch sử xử lý</h2><span class="erp-badge gray">{{ $return->histories->count() }} sự kiện</span></div>
            <div class="erp-section-body"><div class="erp-timeline">@forelse($return->histories as $h)<div class="erp-event"><div class="erp-event-title">{{ $h->from_status ? $h->from_status.' → ' : '' }}{{ $h->to_status }}</div><div>{{ $h->note }}</div><div class="erp-event-meta">{{ $h->user->name ?? 'Hệ thống' }} · {{ optional($h->created_at)->format('d/m/Y H:i') }}</div></div>@empty<div class="erp-muted">Chưa có lịch sử.</div>@endforelse</div></div>
        </section>
    </div>
</div></div>
@endsection
