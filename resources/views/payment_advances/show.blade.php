@extends('layouts.app')
@section('title', 'Chi tiết tạm ứng & hoàn ứng')

@push('styles')
<link rel="stylesheet" href="{{ asset('css/ego-payment-advance-modules.css') }}?v=20260826v2">
@endpush
@push('scripts')
<script src="{{ asset('js/ego-payment-advance-modules.js') }}?v=20260826v2" defer></script>
@endpush

@section('content')
@php
    $pStatus = (string)$item->status;
    $sStatus = (string)($settlement->status ?? 'waiting_payment');
    $paymentLabel = $statusLabels[$pStatus] ?? $pStatus;
    $settlementLabel = $settlementLabels[$sStatus] ?? $sStatus;
    $advanceAmount = (float)($item->amount ?? 0);
    $actualAmount = (float)($settlement->actual_spent_amount ?? 0);
    $difference = (float)($settlement->difference_amount ?? 0);
    $settlementDue = $settlement->settlement_due_date ? \Carbon\Carbon::parse($settlement->settlement_due_date) : null;
    $overdueDays = ($settlementDue && $settlementDue->copy()->startOfDay()->lt(now()->startOfDay()) && in_array($sStatus,['waiting_settlement','draft','returned'],true)) ? $settlementDue->copy()->startOfDay()->diffInDays(now()->startOfDay()) : 0;
    $canSubmitAdvance = $isOwner && in_array($pStatus,['draft','admin_rejected','accounting_rejected'],true);
    $canFinanceApproveAdvance = $isFinanceManager && $pStatus === 'submitted';
    $canAccountingPay = $isAccounting && $pStatus === 'admin_approved';
    $canCreateSettlement = ($isOwner || $isFinanceManager) && $pStatus === 'accounting_approved' && in_array($sStatus,['waiting_settlement','draft','returned'],true);
    $canAccountingSettle = $isAccounting && $pStatus === 'accounting_approved' && in_array($sStatus, ['submitted','management_approved'], true);
    $canFinanceApproveSettlement = $isFinanceManager && $pStatus === 'accounting_approved' && $sStatus === 'accounting_checked';
    $createdAt = $item->created_at ? \Carbon\Carbon::parse($item->created_at)->format('d/m/Y H:i') : '—';
    $adminAt = $item->admin_approved_at ? \Carbon\Carbon::parse($item->admin_approved_at)->format('d/m/Y H:i') : null;
    $accountingAt = $item->accounting_approved_at ? \Carbon\Carbon::parse($item->accounting_approved_at)->format('d/m/Y H:i') : null;
    $settlementSubmittedAt = $settlement->submitted_at ? \Carbon\Carbon::parse($settlement->submitted_at)->format('d/m/Y H:i') : null;
    $managementAt = $settlement->management_approved_at ? \Carbon\Carbon::parse($settlement->management_approved_at)->format('d/m/Y H:i') : null;
    $settlementAccountingAt = $settlement->accounting_checked_at ? \Carbon\Carbon::parse($settlement->accounting_checked_at)->format('d/m/Y H:i') : null;
    $isImportedFromPaymentRequest = !str_starts_with((string)$item->code, 'DNTU-');
    $userName = function($id) { if(!$id) return null; try { return optional(\App\Models\User::find($id))->name; } catch(\Throwable $e) { return null; } };
@endphp

<div class="ego-adv-page">
    <header class="ego-adv-header">
        <div class="ego-adv-heading">
            <div class="ego-adv-head-icon"><i class="bi bi-cash-coin"></i></div>
            <div>
                <div class="ego-adv-eyebrow">{{ $isImportedFromPaymentRequest ? 'HỒ SƠ HOÀN ỨNG • NGUỒN ĐNTT' : 'HỒ SƠ TẠM ỨNG • QUYẾT TOÁN' }}</div>
                <h1>{{ $item->code }}</h1>@if($isImportedFromPaymentRequest)<div><span class="ego-adv-stage blue">Tạo hoàn ứng từ ĐNTT</span></div>@endif
                <div class="ego-adv-progress">
                    <span class="done">Tạo phiếu</span><span class="{{ in_array($pStatus,['admin_approved','accounting_approved'],true)?'done':($pStatus==='submitted'?'active':'') }}">QL tài chính duyệt</span><span class="{{ $pStatus==='accounting_approved'?'done':($pStatus==='admin_approved'?'active':'') }}">Kế toán chi</span><span class="{{ in_array($sStatus,['submitted','accounting_checked','management_approved','completed'],true)?'done':($pStatus==='accounting_approved'?'active':'') }}">Hoàn ứng</span><span class="{{ in_array($sStatus,['accounting_checked','completed'],true)?'done':($sStatus==='submitted'?'active':'') }}">Kế toán đối soát</span><span class="{{ $sStatus==='completed'?'done':($sStatus==='accounting_checked'?'active':'') }}">QL tài chính duyệt</span><span class="{{ $sStatus==='completed'?'done':'' }}">Hoàn tất</span>
                </div>
            </div>
        </div>
        <div style="display:flex;align-items:center;gap:9px"><div class="ego-adv-summary-box"><span>Số tiền tạm ứng</span><strong class="ego-adv-money">{{ number_format($advanceAmount,0,',','.') }} đ</strong></div><a href="{{ route('payment_advances.index') }}" class="ego-adv-btn"><i class="bi bi-arrow-left"></i>Quay lại</a></div>
    </header>

    @if(session('success'))<div class="ego-adv-alert success">{{ session('success') }}</div>@endif
    @if(session('error'))<div class="ego-adv-alert danger">{{ session('error') }}</div>@endif
    @if($errors->any())<div class="ego-adv-alert danger"><strong>Chưa xử lý được:</strong> {{ $errors->first() }}</div>@endif

    @if($pStatus === 'accounting_approved' && in_array($sStatus,['waiting_settlement','draft','returned'],true))
        <div class="ego-adv-alert {{ $overdueDays>0?'danger':'info' }}" id="hoan-ung">
            <div style="display:flex;align-items:center;justify-content:space-between;gap:14px;flex-wrap:wrap"><div><strong>{{ $overdueDays>0 ? 'Khoản tạm ứng đã quá hạn hoàn ứng '.$overdueDays.' ngày' : 'Khoản tạm ứng này đang cần hoàn ứng' }}</strong><div style="margin-top:3px;font-size:11px">Tạm ứng {{ number_format($advanceAmount,0,',','.') }} đ @if($settlementDue) • Hạn {{ $settlementDue->format('d/m/Y') }} @endif</div>@if($sStatus==='returned' && $settlement->return_note)<div style="margin-top:5px;font-size:11px">Yêu cầu bổ sung: {{ $settlement->return_note }}</div>@endif</div>@if($canCreateSettlement)<button class="ego-adv-btn primary" data-bs-toggle="modal" data-bs-target="#settlementModal"><i class="bi bi-arrow-return-left"></i>{{ $sStatus==='draft'?'Tiếp tục hoàn ứng':'Tạo hoàn ứng' }}</button>@endif</div>
        </div>
    @endif

    <div class="ego-adv-detail">
        <main>
            <section class="ego-adv-card" style="margin-top:0">
                <div class="ego-adv-card-head"><div><h2>Thông tin tạm ứng</h2><p>Thông tin người nhận, thời hạn và tài khoản nhận tiền.</p></div><span class="ego-adv-stage {{ in_array($pStatus,['admin_rejected','accounting_rejected'],true)?'danger':($pStatus==='accounting_approved'?'success':'wait') }}">{{ $paymentLabel }}</span></div>
                <div class="ego-adv-card-body"><div class="ego-adv-info-grid">
                    <div class="ego-adv-info"><small>Người nhận tạm ứng</small><strong>{{ $item->receiver_name ?: '—' }}</strong></div>
                    <div class="ego-adv-info"><small>Công ty</small><strong>{{ $item->company ?: '—' }}</strong></div>
                    <div class="ego-adv-info"><small>Số tiền</small><strong class="ego-adv-money">{{ number_format($advanceAmount,0,',','.') }} đ</strong></div>
                    <div class="ego-adv-info"><small>Ngày cần tạm ứng</small><strong>{{ $item->payment_due_date ? \Carbon\Carbon::parse($item->payment_due_date)->format('d/m/Y') : '—' }}</strong></div>
                    <div class="ego-adv-info"><small>Hạn hoàn ứng</small><strong>{{ $settlementDue ? $settlementDue->format('d/m/Y') : '—' }}</strong></div>
                    <div class="ego-adv-info"><small>Ngày tạo</small><strong>{{ $createdAt }}</strong></div>
                    <div class="ego-adv-info wide"><small>Nội dung / lý do tạm ứng</small><strong>{{ $item->payment_content ?: $item->reason ?: '—' }}</strong></div>
                </div></div>
            </section>

            <section class="ego-adv-card">
                <div class="ego-adv-card-head"><div><h2>Thông tin nhận tiền</h2><p>Thông tin ngân hàng phục vụ việc chi tạm ứng.</p></div></div>
                <div class="ego-adv-card-body"><div class="ego-adv-info-grid"><div class="ego-adv-info wide"><small>Ngân hàng / tài khoản</small><strong>{{ $item->bank_info ?: '—' }}</strong></div></div></div>
            </section>

            @if($pStatus === 'accounting_approved' && !in_array($sStatus,['waiting_payment'],true))
            <section class="ego-adv-card">
                <div class="ego-adv-card-head"><div><h2>Hoàn ứng / Quyết toán</h2><p>{{ $settlementLabel }}</p></div><span class="ego-adv-stage {{ $sStatus==='completed'?'success':($sStatus==='returned'?'danger':'wait') }}">{{ $settlementLabel }}</span></div>
                <div class="ego-adv-card-body">
                    <div class="ego-adv-summary-strip">
                        <div class="ego-adv-summary-box"><span>Đã tạm ứng</span><strong>{{ number_format($advanceAmount,0,',','.') }} đ</strong></div>
                        <div class="ego-adv-summary-box"><span>Đã chi thực tế</span><strong>{{ number_format($actualAmount,0,',','.') }} đ</strong></div>
                        <div class="ego-adv-summary-box"><span>Đối soát</span><strong>@if($difference>0)Nhân viên hoàn {{ number_format($difference,0,',','.') }} đ @elseif($difference<0)Công ty trả thêm {{ number_format(abs($difference),0,',','.') }} đ @elseif($actualAmount>0)Đã cân bằng @else Chưa nhập @endif</strong></div>
                    </div>
                    @if($settlement->settlement_reason)<div class="ego-adv-info-grid" style="margin-top:10px"><div class="ego-adv-info wide"><small>Nội dung / lý do quyết toán</small><strong>{{ $settlement->settlement_reason }}</strong></div></div>@endif
                    @if($settlement->settlement_note)<div class="ego-adv-info-grid" style="margin-top:10px"><div class="ego-adv-info wide"><small>Ghi chú</small><strong>{{ $settlement->settlement_note }}</strong></div></div>@endif
                    @if($settlementAttachments->count())<div class="ego-adv-files" style="margin-top:10px">@foreach($settlementAttachments as $f)<div class="ego-adv-file"><a target="_blank" href="{{ asset('storage/'.ltrim($f['path'] ?? '','/')) }}"><i class="bi bi-paperclip"></i> {{ $f['name'] ?? 'Chứng từ' }}</a><small>{{ isset($f['size']) ? number_format(((int)$f['size'])/1024,1).' KB' : '' }}</small></div>@endforeach</div>@endif
                </div>
            </section>
            @endif

            @if($attachments->count())
            <section class="ego-adv-card"><div class="ego-adv-card-head"><div><h2>Chứng từ tạm ứng</h2><p>Tệp đính kèm khi tạo phiếu.</p></div></div><div class="ego-adv-card-body"><div class="ego-adv-files">@foreach($attachments as $f)<div class="ego-adv-file"><a target="_blank" href="{{ asset('storage/'.ltrim($f->path,'/')) }}"><i class="bi bi-paperclip"></i> {{ $f->original_name ?: 'Xem file' }}</a><small>{{ !empty($f->size) ? number_format(((int)$f->size)/1024,1).' KB' : '' }}</small></div>@endforeach</div></div></section>
            @endif

            <section class="ego-adv-card">
                <div class="ego-adv-card-head"><div><h2>Xử lý phê duyệt</h2><p>Chỉ hiển thị hành động đúng với giai đoạn và quyền hiện tại.</p></div></div>
                <div class="ego-adv-card-body"><div class="ego-adv-approval-box"><div class="ego-adv-approval-copy"><strong>Giai đoạn hiện tại: {{ $pStatus==='accounting_approved' ? $settlementLabel : $paymentLabel }}</strong><span>@if(!$canSubmitAdvance && !$canFinanceApproveAdvance && !$canAccountingPay && !$canCreateSettlement && !$canFinanceApproveSettlement && !$canAccountingSettle)Đang chờ người có quyền xử lý hoặc hồ sơ đã hoàn tất.@else Chọn hành động phù hợp để chuyển hồ sơ sang bước tiếp theo.@endif</span></div><div class="ego-adv-approval-actions">
                    @if($canSubmitAdvance)<form method="POST" action="{{ route('payment_requests.submit',$item->id) }}">@csrf<button class="ego-adv-btn primary"><i class="bi bi-send"></i>Gửi Kế toán đối soát</button></form>@endif
                    @if($canFinanceApproveAdvance)<form method="POST" action="{{ route('payment_requests.admin_approve',$item->id) }}">@csrf<button class="ego-adv-btn success"><i class="bi bi-check2-circle"></i>QL tài chính duyệt</button></form><button class="ego-adv-btn danger" data-bs-toggle="modal" data-bs-target="#advanceManagementRejectModal"><i class="bi bi-x-circle"></i>Từ chối</button>@endif
                    @if($canAccountingPay)<form method="POST" action="{{ route('payment_requests.acc_approve',$item->id) }}" onsubmit="return confirm('Xác nhận đã chi {{ number_format($advanceAmount,0,',','.') }} đ?')">@csrf<button class="ego-adv-btn success"><i class="bi bi-cash-stack"></i>Kế toán xác nhận đã chi</button></form><button class="ego-adv-btn danger" data-bs-toggle="modal" data-bs-target="#advanceAccountingRejectModal">Từ chối</button>@endif
                    @if($canCreateSettlement)<button class="ego-adv-btn primary" data-bs-toggle="modal" data-bs-target="#settlementModal"><i class="bi bi-arrow-return-left"></i>{{ $sStatus==='draft'?'Tiếp tục hoàn ứng':'Hoàn ứng' }}</button>@endif
                    @if($canAccountingSettle)<form method="POST" action="{{ route('payment_advances.settlement.accounting_check',$item->id) }}" onsubmit="return confirm('Xác nhận đã đối soát hồ sơ và chuyển Quản lý tài chính duyệt?')">@csrf<button class="ego-adv-btn success"><i class="bi bi-calculator"></i>Kế toán đối soát</button></form><button class="ego-adv-btn danger" data-bs-toggle="modal" data-bs-target="#settlementAccountingReturnModal">Trả bổ sung</button>@endif
                    @if($canFinanceApproveSettlement)<form method="POST" action="{{ route('payment_advances.settlement.management_approve',$item->id) }}" onsubmit="return confirm('Xác nhận duyệt hoàn ứng và hoàn tất hồ sơ?')">@csrf<button class="ego-adv-btn success"><i class="bi bi-person-check"></i>QL tài chính duyệt hoàn ứng</button></form><button class="ego-adv-btn danger" data-bs-toggle="modal" data-bs-target="#settlementManagementRejectModal">Từ chối</button>@endif
                </div></div></div>
            </section>
        </main>

        <aside>
            <section class="ego-adv-card" style="margin-top:0"><div class="ego-adv-card-head"><div><h2>Tiến độ hồ sơ</h2><p>Một luồng từ tạm ứng đến quyết toán.</p></div></div><div class="ego-adv-card-body">
                <div class="ego-adv-phase">TẠM ỨNG</div><div class="ego-adv-timeline">
                    <div class="ego-adv-step done"><div class="ego-adv-step-num">1</div><div class="ego-adv-step-box"><div class="ego-adv-step-title">Tạo phiếu</div><div class="ego-adv-step-text">{{ $item->creator_name ?: $item->receiver_name }}<br>{{ $createdAt }}</div></div></div>
                    <div class="ego-adv-step {{ in_array($pStatus,['admin_approved','accounting_approved','accounting_rejected'],true)?'done':($pStatus==='submitted'?'active':'') }}"><div class="ego-adv-step-num">2</div><div class="ego-adv-step-box"><div class="ego-adv-step-title">QL tài chính duyệt</div><div class="ego-adv-step-text">@if($adminAt){{ $userName($item->admin_approved_by) ?: 'Đã duyệt' }}<br>{{ $adminAt }}@elseif($pStatus==='submitted')Đang chờ duyệt@elseif($pStatus==='admin_rejected')Đã từ chối@else Chưa đến bước duyệt @endif</div></div></div>
                    <div class="ego-adv-step {{ $pStatus==='accounting_approved'?'done':($pStatus==='admin_approved'?'active':'') }}"><div class="ego-adv-step-num">3</div><div class="ego-adv-step-box"><div class="ego-adv-step-title">Kế toán chi</div><div class="ego-adv-step-text">@if($accountingAt){{ $userName($item->accounting_approved_by) ?: 'Kế toán' }}<br>{{ $accountingAt }}@elseif($pStatus==='admin_approved')Đang chờ kế toán chi@elseif($pStatus==='accounting_rejected')Đã từ chối chi@else Chưa đến bước kế toán @endif</div></div></div>
                </div>
                <div class="ego-adv-phase" style="margin-top:15px">HOÀN ỨNG / QUYẾT TOÁN</div><div class="ego-adv-timeline">
                    <div class="ego-adv-step {{ in_array($sStatus,['submitted','accounting_checked','management_approved','completed'],true)?'done':($pStatus==='accounting_approved'?'active':'') }}"><div class="ego-adv-step-num">4</div><div class="ego-adv-step-box"><div class="ego-adv-step-title">Nhân viên hoàn ứng</div><div class="ego-adv-step-text">@if($settlementSubmittedAt)Đã gửi hồ sơ<br>{{ $settlementSubmittedAt }}@elseif($pStatus==='accounting_approved'){{ $overdueDays>0?'Quá hạn '.$overdueDays.' ngày':'Đang chờ hoàn ứng' }}@else Chưa đến bước hoàn ứng @endif</div></div></div>
                    <div class="ego-adv-step {{ in_array($sStatus,['accounting_checked','completed'],true)?'done':($sStatus==='submitted'?'active':'') }}"><div class="ego-adv-step-num">5</div><div class="ego-adv-step-box"><div class="ego-adv-step-title">Kế toán đối soát</div><div class="ego-adv-step-text">@if($settlementAccountingAt){{ $userName($settlement->accounting_checked_by) ?: 'Kế toán' }}<br>{{ $settlementAccountingAt }}@elseif($sStatus==='submitted')Đang chờ đối soát@elseif($sStatus==='management_approved')Đã đối soát theo luồng cũ@else Chưa đến bước kế toán @endif</div></div></div>
                    <div class="ego-adv-step {{ $sStatus==='completed'?'done':($sStatus==='accounting_checked'?'active':'') }}"><div class="ego-adv-step-num">6</div><div class="ego-adv-step-box"><div class="ego-adv-step-title">QL tài chính duyệt hoàn ứng</div><div class="ego-adv-step-text">@if($managementAt){{ $userName($settlement->management_approved_by) ?: 'Đã duyệt' }}<br>{{ $managementAt }}@elseif($sStatus==='accounting_checked')Đang chờ duyệt@elseif($sStatus==='returned' && $settlement->management_note)Đã trả bổ sung@else Chưa đến bước duyệt @endif</div></div></div>
                    <div class="ego-adv-step {{ $sStatus==='completed'?'done':'' }}"><div class="ego-adv-step-num">7</div><div class="ego-adv-step-box"><div class="ego-adv-step-title">Hoàn tất</div><div class="ego-adv-step-text">{{ $sStatus==='completed'?'Đã quyết toán & đóng hồ sơ':'Chưa hoàn tất' }}</div></div></div>
                </div>
            </div></section>

            <section class="ego-adv-card"><div class="ego-adv-card-head"><div><h2>Tóm tắt hồ sơ</h2><p>Thông tin nhanh của khoản tạm ứng.</p></div></div><div class="ego-adv-card-body"><div class="ego-adv-files"><div class="ego-adv-file"><span>Trạng thái</span><strong>{{ $pStatus==='accounting_approved'?$settlementLabel:$paymentLabel }}</strong></div><div class="ego-adv-file"><span>Tạm ứng</span><strong>{{ number_format($advanceAmount,0,',','.') }} đ</strong></div><div class="ego-adv-file"><span>Hạn hoàn ứng</span><strong>{{ $settlementDue?$settlementDue->format('d/m/Y'):'—' }}</strong></div>@if($actualAmount>0)<div class="ego-adv-file"><span>Đã chi</span><strong>{{ number_format($actualAmount,0,',','.') }} đ</strong></div>@endif</div></div></section>
        </aside>
    </div>
</div>

@if($canCreateSettlement)
<div class="modal fade ego-adv-modal" id="settlementModal" tabindex="-1"><div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable"><div class="modal-content"><form method="POST" action="{{ route('payment_advances.settlement.store',$item->id) }}" enctype="multipart/form-data">@csrf
    <div class="modal-header"><div><div class="ego-adv-eyebrow">QUYẾT TOÁN TẠM ỨNG</div><h5 class="modal-title mb-0">Hoàn ứng {{ $item->code }}</h5><small class="text-muted">Sau khi gửi: Kế toán đối soát → Quản lý tài chính duyệt → Hoàn tất.</small></div><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body"><div class="ego-adv-modal-summary"><div><span>Người tạm ứng</span><strong>{{ $item->receiver_name }}</strong></div><div><span>Số tiền tạm ứng</span><strong>{{ number_format($advanceAmount,0,',','.') }} đ</strong></div><div><span>Hạn hoàn ứng</span><strong>{{ $settlementDue?$settlementDue->format('d/m/Y'):'—' }}</strong></div></div><div class="ego-adv-form-grid">
        <div class="wide"><label>Số tiền đã chi thực tế *</label><input class="ego-adv-input" type="text" inputmode="numeric" data-money-input data-settlement-actual name="actual_spent_amount" value="{{ old('actual_spent_amount',$actualAmount>0?$actualAmount:'') }}" required><div class="ego-adv-balance" data-settlement-result data-advance="{{ (int)$advanceAmount }}">Nhân viên hoàn lại: <strong>{{ number_format($advanceAmount,0,',','.') }} đ</strong></div></div>
        <div class="wide"><label>Nội dung / lý do quyết toán *</label><textarea class="ego-adv-textarea" rows="3" name="settlement_reason" required>{{ old('settlement_reason',$settlement->settlement_reason) }}</textarea></div>
        <div class="wide"><label>Chứng từ *</label><input class="ego-adv-input" type="file" name="attachments[]" multiple accept=".jpg,.jpeg,.png,.webp,.pdf,.doc,.docx,.xls,.xlsx"><div class="ego-adv-help">Tối đa 10 file, 10MB/file. Nếu đã lưu nháp có chứng từ, có thể giữ nguyên file cũ.</div></div>
        <div class="wide"><label>Ghi chú</label><textarea class="ego-adv-textarea" rows="2" name="settlement_note">{{ old('settlement_note',$settlement->settlement_note) }}</textarea></div>
    </div></div>
    <div class="modal-footer"><button type="button" class="ego-adv-btn" data-bs-dismiss="modal">Hủy</button><button class="ego-adv-btn" name="submit_now" value="0"><i class="bi bi-save"></i>Lưu nháp</button><button class="ego-adv-btn primary" name="submit_now" value="1"><i class="bi bi-send"></i>Gửi Kế toán đối soát</button></div>
</form></div></div></div>
@endif

<div class="modal fade" id="advanceManagementRejectModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content"><form method="POST" action="{{ route('payment_requests.admin_reject',$item->id) }}">@csrf<div class="modal-header"><h5 class="modal-title">QL tài chính từ chối tạm ứng</h5><button class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body"><textarea class="form-control" required name="note" rows="4" placeholder="Lý do từ chối"></textarea></div><div class="modal-footer"><button class="ego-adv-btn danger">Xác nhận từ chối</button></div></form></div></div></div>
<div class="modal fade" id="advanceAccountingRejectModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content"><form method="POST" action="{{ route('payment_requests.acc_reject',$item->id) }}">@csrf<div class="modal-header"><h5 class="modal-title">Kế toán từ chối chi</h5><button class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body"><textarea class="form-control" required name="note" rows="4" placeholder="Lý do"></textarea></div><div class="modal-footer"><button class="ego-adv-btn danger">Xác nhận</button></div></form></div></div></div>
<div class="modal fade" id="settlementManagementRejectModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content"><form method="POST" action="{{ route('payment_advances.settlement.management_reject',$item->id) }}">@csrf<div class="modal-header"><h5 class="modal-title">QL tài chính trả hồ sơ hoàn ứng</h5><button class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body"><textarea class="form-control" required name="note" rows="4" placeholder="Nội dung cần bổ sung"></textarea></div><div class="modal-footer"><button class="ego-adv-btn danger">Trả bổ sung</button></div></form></div></div></div>
<div class="modal fade" id="settlementAccountingReturnModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content"><form method="POST" action="{{ route('payment_advances.settlement.accounting_return',$item->id) }}">@csrf<div class="modal-header"><h5 class="modal-title">Kế toán trả hồ sơ hoàn ứng</h5><button class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body"><textarea class="form-control" required name="note" rows="4" placeholder="Nội dung cần bổ sung"></textarea></div><div class="modal-footer"><button class="ego-adv-btn danger">Trả bổ sung</button></div></form></div></div></div>

@if($errors->any() && $canCreateSettlement)
<script>document.addEventListener('DOMContentLoaded',function(){if(window.bootstrap){new bootstrap.Modal(document.getElementById('settlementModal')).show();}});</script>
@endif
@endsection
