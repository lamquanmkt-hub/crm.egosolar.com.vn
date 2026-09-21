@extends('layouts.app')

{{-- EGO_DNTT_ENTERPRISE_ASSETS_START --}}
@push('styles')
    <link
        rel="stylesheet"
        href="{{ asset('css/ego-payment-requests-enterprise.css') }}?v={{ filemtime(public_path('css/ego-payment-requests-enterprise.css')) }}"
    >
@endpush

@push('scripts')
    <script
        src="{{ asset('js/ego-payment-requests-enterprise.js') }}?v={{ filemtime(public_path('js/ego-payment-requests-enterprise.js')) }}"
        defer
    ></script>
@endpush
{{-- EGO_DNTT_ENTERPRISE_ASSETS_END --}}


@section('title', 'Chi tiết đề nghị thanh toán')

@section('content')
@php
    $user = auth()->user();

    $isAdmin = (method_exists($user, 'hasRole') && $user->hasRole('admin'))
        || (($user->role ?? null) === 'admin')
        || ((int)($user->is_admin ?? 0) === 1);

    $isAccounting = (method_exists($user, 'hasAnyRole') && $user->hasAnyRole(['accounting', 'ketoan', 'ke_toan']))
        || in_array(($user->role ?? ''), ['accounting', 'ketoan', 'ke_toan'], true);

    $isOwner = (int)($item->created_by ?? 0) === (int)($user->id ?? 0);

    $editableStatuses = ['draft', 'submitted', 'admin_rejected', 'accounting_rejected'];
    $canEdit = $isOwner && in_array((string)($item->status ?? ''), $editableStatuses, true);
    $canSubmit = $isOwner && in_array((string)($item->status ?? ''), ['draft', 'admin_rejected', 'accounting_rejected'], true);
    $canDelete = $isOwner && in_array((string)($item->status ?? ''), ['draft', 'admin_rejected', 'accounting_rejected'], true);
    $canAdminAction = $isAdmin && (($item->status ?? '') === 'submitted');
    $canAccAction = $isAccounting && (($item->status ?? '') === 'admin_approved');

    $statusMap = [
        'draft' => 'Nháp',
        'submitted' => 'Đã gửi duyệt',
        'admin_approved' => 'Quản lý tài chính đã duyệt',
        'admin_rejected' => 'Quản lý tài chính từ chối',
        'accounting_approved' => 'Kế toán đã chi',
        'accounting_rejected' => 'Kế toán từ chối',
    ];

    $statusToneMap = [
        'draft' => 'neutral',
        'submitted' => 'info',
        'admin_approved' => 'primary',
        'admin_rejected' => 'danger',
        'accounting_approved' => 'success',
        'accounting_rejected' => 'warning',
    ];

    $statusLabel = $statusMap[$item->status] ?? ($item->status ?? '-');
    $statusTone = $statusToneMap[$item->status] ?? 'neutral';

    $createdAt = !empty($item->created_at) ? \Carbon\Carbon::parse($item->created_at)->format('d/m/Y H:i') : '-';
    $paymentDueDate = !empty($item->payment_due_date) ? \Carbon\Carbon::parse($item->payment_due_date)->format('d/m/Y') : '-';
    $adminApprovedAt = !empty($item->admin_approved_at) ? \Carbon\Carbon::parse($item->admin_approved_at)->format('d/m/Y H:i') : '-';
    $accApprovedAt = !empty($item->accounting_approved_at) ? \Carbon\Carbon::parse($item->accounting_approved_at)->format('d/m/Y H:i') : '-';

    $adminApproverName = $item->adminApprover->name ?? (!empty($item->admin_approved_by) ? '#'.$item->admin_approved_by : '-');
    $accApproverName = $item->accountingApprover->name ?? (!empty($item->accounting_approved_by) ? '#'.$item->accounting_approved_by : '-');

    $docTypeLabel = match ($item->doc_type ?? '') {
        'payment_voucher' => 'Phiếu chi',
        'refund_request' => 'Đề nghị hoàn tiền',
        'advance' => 'Tạm ứng',
        default => 'Phiếu đề nghị thanh toán',
    };

    $attachments = $item->attachments ?? collect();
    /* EGO_ATTACHMENTS_DIRECT_QUERY_START */
    try {
        if (!empty($item->id) && \Illuminate\Support\Facades\Schema::hasTable('payment_attachments')) {
            $attachments = \Illuminate\Support\Facades\DB::table('payment_attachments')
                ->where('payment_request_id', (int) $item->id)
                ->orderBy('id', 'asc')
                ->get();
        }
    } catch (\Throwable $e) {
        $attachments = $attachments ?? collect();
    }
    /* EGO_ATTACHMENTS_DIRECT_QUERY_END */

@endphp

<div class="payx ego-pr-detail-page">
    @if(session('success'))
        <div class="payx-alert success payx-animate">{{ session('success') }}</div>
    @endif

    @if(session('error'))
        <div class="payx-alert danger payx-animate">{{ session('error') }}</div>
    @endif

    @if($errors->any())
        <div class="payx-alert danger payx-animate">{{ $errors->first() }}</div>
    @endif

    
    <header class="ego-pr-detail-header ego-pr-reveal">
        <div class="ego-pr-detail-heading">
            <span class="ego-pr-detail-icon"><i class="bi bi-receipt-cutoff"></i></span>
            <div>
                <div class="ego-pr-eyebrow">HỒ SƠ THANH TOÁN</div>
                <div class="ego-pr-detail-title-row">
                    <h1>{{ $item->code }}</h1>
                    <span class="ego-pr-status ego-pr-status--{{ str_replace('_', '-', (string)($item->status ?? 'draft')) }}">{{ $statusLabel }}</span>
                </div>
                <div class="ego-pr-detail-meta">
                    <span><i class="bi bi-calendar3"></i> {{ $createdAt }}</span>
                    <span><i class="bi bi-person"></i> {{ $item->creator->name ?? ('#'.$item->created_by) }}</span>
                    <span><i class="bi bi-building"></i> {{ $item->company ?: 'Chưa có công ty' }}</span>
                </div>
            </div>
        </div>

        <div class="ego-pr-detail-summary">
            <div class="ego-pr-detail-amount">
                <span>Số tiền đề nghị</span>
                <strong>{{ number_format((int)($item->amount ?? 0)) }} đ</strong>
            </div>
            <div class="ego-pr-detail-actions">
                <a href="{{ route('payment_requests.index') }}" class="ego-pr-button ego-pr-button--secondary"><i class="bi bi-arrow-left"></i><span>Quay lại</span></a>
                @if($canEdit)
                    <a href="{{ route('payment_requests.edit', $item->id) }}" class="ego-pr-button ego-pr-button--primary"><i class="bi bi-pencil"></i><span>Sửa phiếu</span></a>
                @endif
                @if(($item->status ?? '') === 'accounting_approved')
                    <a href="{{ route('payment_requests.invoice', $item->id) }}" class="ego-pr-button ego-pr-button--secondary"><i class="bi bi-filetype-pdf"></i><span>PDF</span></a>
                @endif
            </div>
        </div>
    </header>
<div class="payx-layout">
        <main class="payx-main">
            
            <section class="payx-card ego-pr-payment-info ego-pr-reveal">
                <div class="payx-card-head">
                    <div>
                        <h2 class="payx-card-title"><span class="ego-pay-section-icon"><i class="bi bi-wallet2"></i></span>Thông tin thanh toán</h2>
                        <div class="payx-card-desc">Thông tin người nhận, thời hạn, loại phiếu và dữ liệu chuyển khoản.</div>
                    </div>
                </div>

                <div class="payx-card-body">
                    <div class="ego-pr-detail-grid">
                        <div class="ego-pr-detail-field"><span>Người nhận</span><strong>{{ $item->receiver_name ?: '-' }}</strong></div>
                        <div class="ego-pr-detail-field"><span>Đơn vị</span><strong>{{ $item->department ?: '-' }}</strong></div>
                        <div class="ego-pr-detail-field"><span>Công ty</span><strong>{{ $item->company ?: '-' }}</strong></div>
                        <div class="ego-pr-detail-field ego-pr-detail-field--money"><span>Số tiền</span><strong>{{ number_format((int)($item->amount ?? 0)) }} đ</strong></div>
                        <div class="ego-pr-detail-field"><span>Hạn thanh toán</span><strong>{{ $paymentDueDate }}</strong></div>
                        <div class="ego-pr-detail-field"><span>Loại phiếu</span><strong>{{ $docTypeLabel }}</strong></div>
                        <div class="ego-pr-detail-field ego-pr-detail-field--wide"><span>Thông tin chuyển khoản</span><strong>{{ $item->bank_info ?: '-' }}</strong></div>
                    </div>

                    <div class="ego-pr-detail-notes">
                        <div><span>Nội dung thanh toán</span><p>{!! nl2br(e($item->payment_content ?: '-')) !!}</p></div>
                        <div><span>Lý do / diễn giải</span><p>{!! nl2br(e($item->reason ?: '-')) !!}</p></div>
                    </div>
                </div>
            </section>
<section class="payx-card payx-animate" style="animation-delay:.06s">
                <div class="payx-card-head">
                    <div>
                        <h2 class="payx-card-title">Chứng từ đính kèm</h2>
                        <div class="payx-card-desc">File hóa đơn, ảnh, PDF, Word hoặc Excel liên quan đến phiếu.</div>
                    </div>
                </div>

                <div class="payx-card-body">
                    @if(count($attachments))
                        <div class="payx-files ego-pr-preview-ready">
                            @foreach($attachments as $att)
                                @php
                                    $fileName = $att->original_name ?? basename($att->path ?? '');
                                    $fileSize = !empty($att->size) ? number_format($att->size / 1024, 1) . ' KB' : '';
                                    $fileMime = $att->mime_type ?? 'Tệp đính kèm';
                                    $downloadUrl = url('/payment-requests/' . $item->id . '/attachments-thao/' . $att->id . '/download');
                                    $previewUrl = url('/payment-requests/' . $item->id . '/attachments-thao/' . $att->id . '/preview');
                                    $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
                                    $fileBadge = in_array($ext, ['jpg','jpeg','png','gif','webp','svg'], true) ? 'ẢNH' : (strtolower($ext ?: 'FILE'));
                                @endphp

                                <div class="payx-file payx-file-preview-card" role="button" tabindex="0" data-preview-url="{{ $previewUrl }}" data-preview-title="{{ $fileName }}">
                                    <div class="payx-file-icon">{{ strtoupper($fileBadge) }}</div>
                                    <div class="payx-file-main">
                                        <div class="payx-file-name">{{ $fileName }}</div>
                                        <div class="payx-file-meta">{{ $fileMime }}</div>
                                    </div>
                                    <div class="payx-file-actions">
                                        <button type="button" class="payx-file-action view payx-preview-trigger">👁 Xem trước</button>
                                        <a class="payx-file-action download" href="{{ $downloadUrl }}" target="_blank" onclick="event.stopPropagation()">⬇ Tải xuống</a>
                                        <span class="payx-file-size">{{ $fileSize }}</span>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <div class="payx-empty">Chưa có chứng từ đính kèm.</div>
                    @endif
                </div>
            </section>


            @if($canEdit || $canSubmit || $canDelete || $canAdminAction || $canAccAction)
                <section class="ego-pr-action-center ego-pr-reveal" data-action-center>
                    <div class="ego-pr-action-center-head">
                        <div>
                            <span class="ego-pr-eyebrow">XỬ LÝ PHIẾU</span>
                            <h3>{{ $canAdminAction ? 'Quản lý tài chính xử lý' : ($canAccAction ? 'Kế toán xử lý' : 'Thao tác phiếu') }}</h3>
                            <p>Chọn hành động, nhập ghi chú và xác nhận trước khi cập nhật trạng thái.</p>
                        </div>
                    </div>

                    @if($canAdminAction || $canAccAction)
                        <div class="ego-pr-action-tabs" role="tablist">
                            <button type="button" class="is-active" data-action-tab="approve"><i class="bi bi-check2-circle"></i>Duyệt</button>
                            <button type="button" data-action-tab="reject"><i class="bi bi-x-circle"></i>Từ chối</button>
                        </div>

                        <div class="ego-pr-action-panel is-active" data-action-panel="approve">
                            <form method="POST" action="{{ $canAdminAction ? route('payment_requests.admin_approve', $item->id) : route('payment_requests.acc_approve', $item->id) }}" class="ego-pr-detail-action-form ego-pr-confirm-form" data-confirm="{{ $canAdminAction ? 'Duyệt phiếu này?' : 'Xác nhận đã chi phiếu này?' }}">
                                @csrf
                                <label for="egoPrApproveNote">Ghi chú xử lý</label>
                                <textarea id="egoPrApproveNote" name="note" placeholder="Ghi chú không bắt buộc..."></textarea>
                                <button type="submit" class="ego-pr-button ego-pr-button--success"><i class="bi bi-check2-circle"></i>{{ $canAdminAction ? 'Duyệt phiếu' : 'Xác nhận đã chi' }}</button>
                            </form>
                        </div>

                        <div class="ego-pr-action-panel" data-action-panel="reject" hidden>
                            <form method="POST" action="{{ $canAdminAction ? route('payment_requests.admin_reject', $item->id) : route('payment_requests.acc_reject', $item->id) }}" class="ego-pr-detail-action-form">
                                @csrf
                                <label for="egoPrRejectNote">Lý do từ chối</label>
                                <textarea id="egoPrRejectNote" name="note" required minlength="2" maxlength="2000" placeholder="Nhập lý do từ chối..."></textarea>
                                <button type="submit" class="ego-pr-button ego-pr-button--danger"><i class="bi bi-x-circle"></i>Từ chối phiếu</button>
                            </form>
                        </div>
                    @else
                        <div class="ego-pr-inline-actions">
                            @if($canSubmit)
                                <form method="POST" action="{{ route('payment_requests.submit', $item->id) }}" class="ego-pr-confirm-form" data-confirm="Gửi duyệt phiếu này?">
                                    @csrf
                                    <button type="submit" class="ego-pr-button ego-pr-button--primary"><i class="bi bi-send-check"></i>Gửi duyệt</button>
                                </form>
                            @endif
                            @if($canDelete)
                                <form method="POST" action="{{ route('payment_requests.destroy', $item->id) }}" class="ego-pr-confirm-form" data-confirm="Xóa phiếu này? Hành động không thể hoàn tác.">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="ego-pr-button ego-pr-button--danger"><i class="bi bi-trash"></i>Xóa phiếu</button>
                                </form>
                            @endif
                        </div>
                    @endif
                </section>
            @endif

            {{-- EGO_PR_AUDIT_TIMELINE_START
                 Nhật ký thao tác (chỉ đọc). Hiển thị cho Admin/Giám đốc (hoặc
                 người có quyền `payment_requests.override_locked`) và cho
                 chính người tạo phiếu — không mở rộng phạm vi xem sẵn có. --}}
            @php
                $egoCanSeeAuditTimeline = $isOwner
                    || (method_exists($user, 'canOverrideLockedFinanceRecords') && $user->canOverrideLockedFinanceRecords());

                $egoAuditLogs = collect();

                if ($egoCanSeeAuditTimeline && \App\Services\Payments\PaymentRequestAuditLogger::available()) {
                    try {
                        $egoAuditLogs = $item->editLogs()->with('user')->limit(200)->get();
                    } catch (\Throwable $e) {
                        $egoAuditLogs = collect();
                    }
                }
            @endphp

            @if($egoCanSeeAuditTimeline)
                <section class="payx-card payx-animate" style="animation-delay:.09s">
                    <div class="payx-card-head">
                        <div>
                            <h2 class="payx-card-title">Nhật ký thay đổi</h2>
                            <div class="payx-card-desc">Ai / lúc nào / thao tác gì / giá trị cũ → mới / lý do. Chỉ đọc, không sửa được.</div>
                        </div>
                    </div>
                    <div class="payx-card-body">
                        @if($egoAuditLogs->isEmpty())
                            <div class="payx-empty">Chưa có thay đổi nào được ghi nhận.</div>
                        @else
                            <div style="overflow-x:auto">
                                <table style="width:100%;border-collapse:collapse;font-size:13px">
                                    <thead>
                                        <tr style="text-align:left;border-bottom:1px solid #e5e7eb">
                                            <th style="padding:6px 8px;white-space:nowrap">Thời điểm</th>
                                            <th style="padding:6px 8px">Người thực hiện</th>
                                            <th style="padding:6px 8px">Thao tác</th>
                                            <th style="padding:6px 8px">Trường</th>
                                            <th style="padding:6px 8px">Giá trị cũ → mới</th>
                                            <th style="padding:6px 8px">Lý do</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($egoAuditLogs as $log)
                                            <tr style="border-bottom:1px solid #f1f5f9;vertical-align:top">
                                                <td style="padding:6px 8px;white-space:nowrap">{{ optional($log->created_at)->format('d/m/Y H:i') }}</td>
                                                <td style="padding:6px 8px">{{ $log->user->name ?? $log->user_name ?? '—' }}</td>
                                                <td style="padding:6px 8px">
                                                    {{ $log->action_label }}
                                                    @if($log->status_before || $log->status_after)
                                                        <div style="color:#64748b;font-size:12px">
                                                            {{ $statusMap[$log->status_before] ?? ($log->status_before ?: '—') }}
                                                            →
                                                            {{ $statusMap[$log->status_after] ?? ($log->status_after ?: '—') }}
                                                        </div>
                                                    @endif
                                                </td>
                                                <td style="padding:6px 8px">{{ $log->field_name ?: '—' }}</td>
                                                <td style="padding:6px 8px">
                                                    @if($log->field_name)
                                                        <span style="color:#be123c">{{ \Illuminate\Support\Str::limit((string) $log->old_value, 120) ?: '(trống)' }}</span>
                                                        <span style="color:#94a3b8"> → </span>
                                                        <span style="color:#047857">{{ \Illuminate\Support\Str::limit((string) $log->new_value, 120) ?: '(trống)' }}</span>
                                                    @else
                                                        —
                                                    @endif
                                                </td>
                                                <td style="padding:6px 8px">{{ $log->reason ?: '—' }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endif
                    </div>
                </section>
            @endif
            {{-- EGO_PR_AUDIT_TIMELINE_END --}}

        </main>

        <aside class="payx-side">
            <section class="payx-card payx-animate" style="animation-delay:.12s">
                <div class="payx-card-head">
                    <div>
                        <h2 class="payx-card-title">Luồng duyệt</h2>
                        <div class="payx-card-desc">Theo dõi tiến độ xử lý phiếu.</div>
                    </div>
                </div>

                <div class="payx-card-body">
                    <div class="payx-timeline">
                        <div class="payx-step done">
                            <div class="payx-step-num">1</div>
                            <div class="payx-step-box">
                                <div class="payx-step-title">Tạo phiếu</div>
                                <div class="payx-step-text">
                                    {{ $item->creator->name ?? ('#'.$item->created_by) }}<br>
                                    {{ $createdAt }}
                                </div>
                            </div>
                        </div>

                        <div class="payx-step {{ in_array(($item->status ?? ''), ['admin_approved','admin_rejected','accounting_approved','accounting_rejected']) ? 'done' : ((($item->status ?? '') === 'submitted') ? 'active' : '') }}">
                            <div class="payx-step-num">2</div>
                            <div class="payx-step-box">
                                <div class="payx-step-title">Quản lý tài chính</div>
                                <div class="payx-step-text">
                                    @if(in_array(($item->status ?? ''), ['admin_approved','admin_rejected','accounting_approved','accounting_rejected']))
                                        {{ $adminApproverName }}<br>{{ $adminApprovedAt }}
                                    @elseif(($item->status ?? '') === 'submitted')
                                        Đang chờ xử lý
                                    @else
                                        Chưa đến bước duyệt
                                    @endif
                                </div>
                            </div>
                        </div>

                        <div class="payx-step {{ in_array(($item->status ?? ''), ['accounting_approved','accounting_rejected']) ? 'done' : ((($item->status ?? '') === 'admin_approved') ? 'active' : '') }}">
                            <div class="payx-step-num">3</div>
                            <div class="payx-step-box">
                                <div class="payx-step-title">Kế toán</div>
                                <div class="payx-step-text">
                                    @if(in_array(($item->status ?? ''), ['accounting_approved','accounting_rejected']))
                                        {{ $accApproverName }}<br>{{ $accApprovedAt }}
                                    @elseif(($item->status ?? '') === 'admin_approved')
                                        Đang chờ kế toán
                                    @else
                                        Chưa đến bước kế toán
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <section class="payx-card payx-animate" style="animation-delay:.15s">
                <div class="payx-card-head">
                    <div>
                        <h2 class="payx-card-title">Ghi chú xử lý</h2>
                        <div class="payx-card-desc">Ghi chú của quản lý tài chính và kế toán.</div>
                    </div>
                </div>

                <div class="payx-card-body">
                    <div class="payx-mini">
                        <div class="payx-mini-row">
                            <span class="payx-label">Ghi chú Admin</span>
                            <div class="payx-text">{!! nl2br(e($item->admin_note ?: '-')) !!}</div>
                        </div>

                        <div class="payx-mini-row">
                            <span class="payx-label">Ghi chú Kế toán</span>
                            <div class="payx-text">{!! nl2br(e($item->accounting_note ?: '-')) !!}</div>
                        </div>
                    </div>
                </div>
            </section>
        </aside>
    </div>
</div>


<!-- EGO_PR_ATTACHMENT_PREVIEW_MODAL_START -->
<div class="payx-attachment-preview-modal" id="payxAttachmentPreviewModal" aria-hidden="true">
    <div class="payx-attachment-preview-box">
        <div class="payx-attachment-preview-head">
            <div class="payx-attachment-preview-title" id="payxAttachmentPreviewTitle">Xem trước chứng từ</div>
            <button type="button" class="payx-attachment-preview-close" id="payxAttachmentPreviewClose">Đóng</button>
        </div>
        <iframe class="payx-attachment-preview-frame" id="payxAttachmentPreviewFrame" src="about:blank"></iframe>
    </div>
</div>

<script>
(function(){
    var modal = document.getElementById('payxAttachmentPreviewModal');
    var frame = document.getElementById('payxAttachmentPreviewFrame');
    var title = document.getElementById('payxAttachmentPreviewTitle');
    var closeBtn = document.getElementById('payxAttachmentPreviewClose');

    if(!modal || !frame || !title || !closeBtn) return;

    function openPreview(url, fileName){
        title.textContent = fileName || 'Xem trước chứng từ';
        frame.src = url;
        modal.classList.add('show');
        modal.setAttribute('aria-hidden', 'false');
        document.body.style.overflow = 'hidden';
    }

    function closePreview(){
        modal.classList.remove('show');
        modal.setAttribute('aria-hidden', 'true');
        frame.src = 'about:blank';
        document.body.style.overflow = '';
    }

    document.addEventListener('click', function(e){
        var trigger = e.target.closest('.payx-preview-trigger');
        var card = e.target.closest('.payx-file-preview-card');

        if(trigger){
            e.preventDefault();
            e.stopPropagation();
            card = trigger.closest('.payx-file-preview-card');
        }else if(card){
            if(e.target.closest('a')) return;
            e.preventDefault();
        }else{
            return;
        }

        if(!card) return;

        openPreview(card.dataset.previewUrl, card.dataset.previewTitle);
    });

    document.addEventListener('keydown', function(e){
        if(e.key === 'Escape') closePreview();

        if((e.key === 'Enter' || e.key === ' ') && e.target.classList.contains('payx-file-preview-card')){
            e.preventDefault();
            openPreview(e.target.dataset.previewUrl, e.target.dataset.previewTitle);
        }
    });

    modal.addEventListener('click', function(e){
        if(e.target === modal) closePreview();
    });

    closeBtn.addEventListener('click', closePreview);
})();
</script>
<!-- EGO_PR_ATTACHMENT_PREVIEW_MODAL_END -->

@endsection
@includeIf('payment-requests._buibichthao_actions')

