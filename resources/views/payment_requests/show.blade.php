@extends('layouts.app')

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

    $editableStatuses = ['draft', 'admin_rejected', 'accounting_rejected'];
    $canEdit = $isOwner && in_array((string)($item->status ?? ''), $editableStatuses, true);
    $canSubmit = $canEdit;
    $canDelete = $canEdit;
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

<style>
    :root {
        --ego-ink: #071b33;
        --ego-muted: #64748b;
        --ego-soft: #f5f8fc;
        --ego-line: #e4ebf3;
        --ego-blue: #0ea5e9;
        --ego-cyan: #06b6d4;
        --ego-green: #16a34a;
        --ego-red: #dc2626;
        --ego-orange: #ea580c;
        --ego-shadow: 0 18px 50px rgba(15, 23, 42, .08);
        --ego-shadow-sm: 0 8px 24px rgba(15, 23, 42, .055);
    }

    .payx * {
        box-sizing: border-box;
    }

    .payx {
        max-width: 1180px;
        margin: 0 auto;
        padding: 18px 10px 38px;
        color: var(--ego-ink);
        font-size: 13px;
    }

    .payx a {
        text-decoration: none;
    }

    .payx-animate {
        animation: payxFadeUp .42s ease both;
    }

    @keyframes payxFadeUp {
        from { opacity: 0; transform: translateY(10px); }
        to { opacity: 1; transform: translateY(0); }
    }

    .payx-alert {
        border-radius: 14px;
        padding: 12px 14px;
        margin-bottom: 12px;
        font-size: 13px;
        font-weight: 700;
        border: 1px solid transparent;
    }

    .payx-alert.success {
        color: #166534;
        background: #ecfdf5;
        border-color: #bbf7d0;
    }

    .payx-alert.danger {
        color: #991b1b;
        background: #fef2f2;
        border-color: #fecaca;
    }

    .payx-hero {
        position: relative;
        overflow: hidden;
        border-radius: 24px;
        padding: 20px;
        color: #fff;
        background:
            radial-gradient(circle at top left, rgba(14,165,233,.35), transparent 32%),
            radial-gradient(circle at bottom right, rgba(45,212,191,.28), transparent 34%),
            linear-gradient(135deg, #071b33 0%, #0f2948 58%, #082f49 100%);
        box-shadow: var(--ego-shadow);
        margin-bottom: 14px;
    }

    .payx-hero:before {
        content: "";
        position: absolute;
        inset: 0;
        background-image:
            linear-gradient(rgba(255,255,255,.07) 1px, transparent 1px),
            linear-gradient(90deg, rgba(255,255,255,.07) 1px, transparent 1px);
        background-size: 34px 34px;
        mask-image: linear-gradient(90deg, rgba(0,0,0,.65), transparent);
        pointer-events: none;
    }

    .payx-hero-inner {
        position: relative;
        z-index: 1;
        display: grid;
        grid-template-columns: 1fr auto;
        gap: 18px;
        align-items: center;
    }

    .payx-title-row {
        display: flex;
        align-items: center;
        gap: 12px;
        min-width: 0;
    }

    .payx-symbol {
        width: 42px;
        height: 42px;
        border-radius: 14px;
        background: rgba(255,255,255,.12);
        border: 1px solid rgba(255,255,255,.18);
        display: grid;
        place-items: center;
        flex: 0 0 auto;
        box-shadow: inset 0 1px 0 rgba(255,255,255,.12);
    }

    .payx-symbol:after {
        content: "";
        width: 18px;
        height: 14px;
        border-radius: 4px;
        border: 2px solid #67e8f9;
        box-shadow: 0 5px 0 -2px rgba(103,232,249,.45);
    }

    .payx-title {
        margin: 0;
        font-size: 25px;
        line-height: 1.15;
        letter-spacing: -.025em;
        font-weight: 850;
        color: #fff;
    }

    .payx-sub {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
        margin-top: 8px;
        color: rgba(255,255,255,.76);
        font-size: 12px;
    }

    .payx-sub span {
        display: inline-flex;
        align-items: center;
        gap: 6px;
    }

    .payx-dot {
        width: 5px;
        height: 5px;
        border-radius: 50%;
        background: #67e8f9;
        box-shadow: 0 0 0 4px rgba(103,232,249,.12);
    }

    .payx-hero-side {
        display: flex;
        align-items: center;
        gap: 10px;
        flex-wrap: wrap;
        justify-content: flex-end;
    }

    .payx-amount {
        min-width: 160px;
        padding: 12px 14px;
        border-radius: 18px;
        background: rgba(255,255,255,.12);
        border: 1px solid rgba(255,255,255,.18);
        backdrop-filter: blur(10px);
    }

    .payx-amount label {
        display: block;
        color: rgba(255,255,255,.68);
        font-size: 11px;
        text-transform: uppercase;
        letter-spacing: .08em;
        margin-bottom: 5px;
    }

    .payx-amount strong {
        display: block;
        color: #fff;
        font-size: 22px;
        line-height: 1.1;
    }

    .payx-chips {
        display: flex;
        flex-wrap: wrap;
        gap: 7px;
        margin-top: 12px;
    }

    .payx-chip {
        display: inline-flex;
        align-items: center;
        gap: 7px;
        height: 28px;
        padding: 0 10px;
        border-radius: 999px;
        font-size: 11.5px;
        font-weight: 800;
        border: 1px solid transparent;
        white-space: nowrap;
    }

    .payx-chip.neutral { color: #475569; background: #f1f5f9; border-color: #e2e8f0; }
    .payx-chip.info { color: #0369a1; background: #e0f2fe; border-color: #bae6fd; }
    .payx-chip.primary { color: #0e7490; background: #cffafe; border-color: #a5f3fc; }
    .payx-chip.success { color: #166534; background: #dcfce7; border-color: #bbf7d0; }
    .payx-chip.danger { color: #991b1b; background: #fee2e2; border-color: #fecaca; }
    .payx-chip.warning { color: #9a3412; background: #ffedd5; border-color: #fed7aa; }
    .payx-hero .payx-chip {
        background: rgba(255,255,255,.13);
        color: #fff;
        border-color: rgba(255,255,255,.18);
    }

    .payx-btn {
        height: 36px;
        padding: 0 13px;
        border: 0;
        border-radius: 12px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 7px;
        font-size: 12px;
        font-weight: 850;
        cursor: pointer;
        transition: .18s ease;
        white-space: nowrap;
    }

    .payx-btn:hover {
        transform: translateY(-1px);
        filter: brightness(1.02);
    }

    .payx-btn.light {
        color: #0f172a;
        background: rgba(255,255,255,.94);
    }

    .payx-btn.blue {
        color: #fff;
        background: linear-gradient(135deg, #0ea5e9, #0284c7);
        box-shadow: 0 10px 20px rgba(14,165,233,.24);
    }

    .payx-btn.green {
        color: #fff;
        background: linear-gradient(135deg, #22c55e, #16a34a);
        box-shadow: 0 10px 20px rgba(34,197,94,.22);
    }

    .payx-btn.red {
        color: #fff;
        background: linear-gradient(135deg, #ef4444, #dc2626);
        box-shadow: 0 10px 20px rgba(239,68,68,.2);
    }

    .payx-btn.orange {
        color: #fff;
        background: linear-gradient(135deg, #f59e0b, #ea580c);
        box-shadow: 0 10px 20px rgba(245,158,11,.2);
    }

    .payx-layout {
        display: grid;
        grid-template-columns: minmax(0, 1fr) 340px;
        gap: 14px;
        align-items: start;
    }

    .payx-main,
    .payx-side {
        display: grid;
        gap: 14px;
    }

    .payx-card {
        background: rgba(255,255,255,.94);
        border: 1px solid var(--ego-line);
        border-radius: 20px;
        box-shadow: var(--ego-shadow-sm);
        overflow: hidden;
    }

    .payx-card-head {
        padding: 15px 16px 0;
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        gap: 12px;
    }

    .payx-card-title {
        margin: 0;
        font-size: 14px;
        font-weight: 850;
        color: var(--ego-ink);
        letter-spacing: -.01em;
    }

    .payx-card-desc {
        margin-top: 4px;
        font-size: 12px;
        color: var(--ego-muted);
    }

    .payx-card-body {
        padding: 15px 16px 16px;
    }

    .payx-info-grid {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: 10px;
        margin-bottom: 12px;
    }

    .payx-info {
        padding: 11px 12px;
        border-radius: 14px;
        background: linear-gradient(180deg, #f8fafc, #fff);
        border: 1px solid #e8eef6;
        min-height: 70px;
        transition: .18s ease;
    }

    .payx-info:hover {
        transform: translateY(-1px);
        border-color: #cfe0f4;
        box-shadow: 0 10px 22px rgba(15, 23, 42, .055);
    }

    .payx-label {
        display: block;
        color: #64748b;
        font-size: 10.5px;
        text-transform: uppercase;
        letter-spacing: .075em;
        font-weight: 850;
        margin-bottom: 6px;
    }

    .payx-value {
        color: var(--ego-ink);
        font-size: 13px;
        line-height: 1.5;
        font-weight: 750;
        word-break: break-word;
    }

    .payx-value.money {
        color: #0369a1;
        font-size: 16px;
        font-weight: 900;
    }

    .payx-note-grid {
        display: grid;
        grid-template-columns: 1fr;
        gap: 10px;
    }

    .payx-note {
        position: relative;
        padding: 13px 14px 13px 16px;
        border-radius: 16px;
        background: #fff;
        border: 1px solid #e6edf6;
        overflow: hidden;
    }

    .payx-note:before {
        content: "";
        position: absolute;
        left: 0;
        top: 12px;
        bottom: 12px;
        width: 3px;
        border-radius: 999px;
        background: linear-gradient(180deg, #0ea5e9, #06b6d4);
    }

    .payx-text {
        color: #0f172a;
        font-size: 13px;
        line-height: 1.72;
        font-weight: 600;
        word-break: break-word;
    }

    .payx-files {
        display: grid;
        gap: 8px;
    }

    .payx-file {
        display: grid;
        grid-template-columns: 34px 1fr auto;
        gap: 10px;
        align-items: center;
        padding: 10px;
        border-radius: 14px;
        border: 1px solid #e6edf6;
        background: linear-gradient(180deg, #fff, #f8fafc);
        color: #0f172a;
        transition: .18s ease;
    }

    .payx-file:hover {
        border-color: #93c5fd;
        transform: translateY(-1px);
        color: #0f172a;
        box-shadow: 0 10px 22px rgba(14,165,233,.1);
    }

    .payx-file-icon {
        width: 34px;
        height: 34px;
        border-radius: 11px;
        background: #e0f2fe;
        display: grid;
        place-items: center;
        color: #0284c7;
        font-weight: 900;
        font-size: 11px;
    }

    .payx-file-name {
        font-size: 13px;
        font-weight: 800;
        line-height: 1.35;
        word-break: break-word;
    }

    .payx-file-meta,
    .payx-file-open {
        color: #64748b;
        font-size: 11.5px;
        font-weight: 700;
    }

    .payx-empty {
        border: 1px dashed #cbd5e1;
        background: #f8fafc;
        color: #64748b;
        border-radius: 14px;
        padding: 13px;
        text-align: center;
        font-weight: 700;
        font-size: 12.5px;
    }

    .payx-timeline {
        position: relative;
        display: grid;
        gap: 10px;
    }

    .payx-step {
        display: grid;
        grid-template-columns: 28px 1fr;
        gap: 10px;
        position: relative;
    }

    .payx-step:not(:last-child):before {
        content: "";
        position: absolute;
        left: 13px;
        top: 32px;
        bottom: -10px;
        width: 2px;
        background: #e2e8f0;
    }

    .payx-step-num {
        width: 28px;
        height: 28px;
        border-radius: 999px;
        display: grid;
        place-items: center;
        background: #e2e8f0;
        color: #475569;
        font-size: 11px;
        font-weight: 900;
        z-index: 1;
    }

    .payx-step.done .payx-step-num {
        color: #fff;
        background: var(--ego-green);
        box-shadow: 0 0 0 5px rgba(22,163,74,.12);
    }

    .payx-step.active .payx-step-num {
        color: #fff;
        background: var(--ego-blue);
        box-shadow: 0 0 0 5px rgba(14,165,233,.13);
        animation: payxPulse 1.4s ease infinite;
    }

    @keyframes payxPulse {
        0%, 100% { box-shadow: 0 0 0 5px rgba(14,165,233,.13); }
        50% { box-shadow: 0 0 0 9px rgba(14,165,233,.07); }
    }

    .payx-step-box {
        padding: 10px 11px;
        border-radius: 14px;
        border: 1px solid #e6edf6;
        background: #fff;
    }

    .payx-step-title {
        font-size: 12.5px;
        color: #0f172a;
        font-weight: 900;
        margin-bottom: 4px;
    }

    .payx-step-text {
        color: #64748b;
        font-size: 12px;
        line-height: 1.55;
        font-weight: 600;
    }

    .payx-mini {
        display: grid;
        gap: 10px;
    }

    .payx-mini-row {
        padding: 10px;
        border-radius: 14px;
        background: #f8fafc;
        border: 1px solid #e8eef6;
    }

    .payx-action {
        border-radius: 20px;
        padding: 14px;
        border: 1px solid #dcebf8;
        background:
            radial-gradient(circle at top right, rgba(14,165,233,.12), transparent 30%),
            #fff;
        box-shadow: var(--ego-shadow-sm);
    }

    .payx-action-title {
        font-size: 14px;
        font-weight: 900;
        margin: 0 0 4px;
        color: var(--ego-ink);
    }

    .payx-action-desc {
        color: #64748b;
        font-size: 12px;
        margin-bottom: 12px;
    }

    .payx-action-grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 10px;
    }

    .payx-action-form {
        padding: 12px;
        border-radius: 16px;
        border: 1px solid #e6edf6;
        background: #fff;
    }

    .payx-action-form h4 {
        margin: 0 0 8px;
        font-size: 13px;
        font-weight: 900;
    }

    .payx-action-form textarea {
        width: 100%;
        min-height: 90px;
        border: 1px solid #d8e3ef;
        border-radius: 12px;
        padding: 10px 11px;
        font-size: 13px;
        line-height: 1.55;
        outline: none;
        resize: vertical;
        margin-bottom: 9px;
        transition: .18s ease;
    }

    .payx-action-form textarea:focus {
        border-color: #38bdf8;
        box-shadow: 0 0 0 4px rgba(14,165,233,.12);
    }

    .payx-inline-actions {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
    }

    @media (max-width: 1080px) {
        .payx-layout {
            grid-template-columns: 1fr;
        }

        .payx-side {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }
    }

    @media (max-width: 820px) {
        .payx-hero-inner,
        .payx-info-grid,
        .payx-side,
        .payx-action-grid {
            grid-template-columns: 1fr;
        }

        .payx-hero-side {
            justify-content: flex-start;
        }

        .payx-title {
            font-size: 21px;
        }
    }

    @media (max-width: 520px) {
        .payx {
            padding-left: 6px;
            padding-right: 6px;
        }

        .payx-hero {
            border-radius: 18px;
            padding: 16px;
        }

        .payx-title-row {
            align-items: flex-start;
        }

        .payx-symbol {
            width: 36px;
            height: 36px;
            border-radius: 12px;
        }
    }


    /* EGO_PR_ATTACHMENT_PREVIEW_STYLE_START */
    .payx-file-preview-card{
        cursor:pointer;
        grid-template-columns:42px minmax(0,1fr) auto !important;
    }

    .payx-file-preview-card:hover .payx-file-name{
        color:#0369a1;
    }

    .payx-file-main{
        min-width:0;
    }

    .payx-file-actions{
        display:flex;
        align-items:center;
        justify-content:flex-end;
        gap:7px;
        flex-wrap:wrap;
    }

    .payx-file-action{
        display:inline-flex;
        align-items:center;
        justify-content:center;
        border-radius:10px;
        padding:7px 10px;
        border:1px solid #dbeafe;
        background:#eff6ff;
        color:#1d4ed8;
        font-size:11.5px;
        line-height:1;
        font-weight:900;
        text-decoration:none !important;
        white-space:nowrap;
    }

    .payx-file-action.view{
        border-color:#99f6e4;
        background:#ecfeff;
        color:#047481;
    }

    .payx-file-action.download{
        border-color:#bfdbfe;
        background:#f8fbff;
        color:#1d4ed8;
    }

    .payx-file-size{
        color:#64748b;
        font-size:11.5px;
        font-weight:800;
        min-width:64px;
        text-align:right;
    }

    .payx-attachment-preview-modal{position:fixed;inset:0;z-index:999999;display:none;align-items:center;justify-content:center;padding:22px;background:rgba(15,23,42,.58);backdrop-filter:blur(6px)}

    .payx-attachment-preview-modal.show{
        display:flex;
    }

    .payx-attachment-preview-box{width:min(920px,90vw);height:min(720px,86vh);overflow:hidden;background:#fff;border-radius:18px;box-shadow:0 26px 76px rgba(15,23,42,.34);display:flex;flex-direction:column}

    .payx-attachment-preview-head{min-height:50px;display:flex;align-items:center;justify-content:space-between;gap:12px;padding:9px 12px;background:linear-gradient(90deg,#0f3b78,#0891b2);color:#fff}

    .payx-attachment-preview-title{
        font-size:14px;
        font-weight:900;
        min-width:0;
        white-space:nowrap;
        overflow:hidden;
        text-overflow:ellipsis;
    }

    .payx-attachment-preview-close{
        border:1px solid rgba(255,255,255,.38);
        border-radius:10px;
        padding:8px 12px;
        background:rgba(255,255,255,.13);
        color:#fff;
        font-weight:900;
        cursor:pointer;
    }

    .payx-attachment-preview-frame{width:100%;height:100%;border:0;background:#f8fafc;flex:1}

    @media(max-width: 768px){
        .payx-file-preview-card{
            grid-template-columns:34px 1fr !important;
        }
        .payx-file-actions{
            grid-column:1 / -1;
            justify-content:flex-start;
            padding-left:42px;
        }
        .payx-attachment-preview-modal{position:fixed;inset:0;z-index:999999;display:none;align-items:center;justify-content:center;padding:22px;background:rgba(15,23,42,.58);backdrop-filter:blur(6px)}
        .payx-attachment-preview-box{width:min(920px,90vw);height:min(720px,86vh);overflow:hidden;background:#fff;border-radius:18px;box-shadow:0 26px 76px rgba(15,23,42,.34);display:flex;flex-direction:column}
    }
    /* EGO_PR_ATTACHMENT_PREVIEW_STYLE_END */

</style>

<div class="payx">
    @if(session('success'))
        <div class="payx-alert success payx-animate">{{ session('success') }}</div>
    @endif

    @if(session('error'))
        <div class="payx-alert danger payx-animate">{{ session('error') }}</div>
    @endif

    @if($errors->any())
        <div class="payx-alert danger payx-animate">{{ $errors->first() }}</div>
    @endif

    <section class="payx-hero payx-animate">
        <div class="payx-hero-inner">
            <div>
                <div class="payx-title-row">
                    <div class="payx-symbol"></div>
                    <div>
                        <h1 class="payx-title">{{ $item->code }}</h1>
                        <div class="payx-sub">
                            <span><i class="payx-dot"></i> ID #{{ $item->id }}</span>
                            <span>Ngày tạo: {{ $createdAt }}</span>
                            <span>Hạn thanh toán: {{ $paymentDueDate }}</span>
                        </div>

                        <div class="payx-chips">
                            <span class="payx-chip {{ $statusTone }}">{{ $statusLabel }}</span>
                            <span class="payx-chip">{{ $docTypeLabel }}</span>
                            <span class="payx-chip">{{ $item->company ?: 'Chưa có công ty' }}</span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="payx-hero-side">
                <div class="payx-amount">
                    <label>Số tiền</label>
                    <strong>{{ number_format((int)($item->amount ?? 0)) }} đ</strong>
                </div>

                <a href="{{ route('payment_requests.index') }}" class="payx-btn light">Quay lại</a>

                @if($canEdit)
                    <a href="{{ route('payment_requests.edit', $item->id) }}" class="payx-btn blue">Sửa phiếu</a>
                @endif

                @if(($item->status ?? '') === 'accounting_approved')
                    <a href="{{ route('payment_requests.invoice', $item->id) }}" class="payx-btn light">Tải hóa đơn</a>
                @endif
            </div>
        </div>
    </section>

    <div class="payx-layout">
        <main class="payx-main">
            <section class="payx-card payx-animate" style="animation-delay:.03s">
                <div class="payx-card-head">
                    <div>
                        <h2 class="payx-card-title">Thông tin thanh toán</h2>
                        <div class="payx-card-desc">Tóm tắt nhanh thông tin người nhận, đơn vị và chuyển khoản.</div>
                    </div>
                    <span class="payx-chip {{ $statusTone }}">{{ $statusLabel }}</span>
                </div>

                <div class="payx-card-body">
                    <div class="payx-info-grid">
                        <div class="payx-info">
                            <span class="payx-label">Người nhận</span>
                            <div class="payx-value">{{ $item->receiver_name ?: '-' }}</div>
                        </div>

                        <div class="payx-info">
                            <span class="payx-label">Số tiền</span>
                            <div class="payx-value money">{{ number_format((int)($item->amount ?? 0)) }} đ</div>
                        </div>

                        <div class="payx-info">
                            <span class="payx-label">Đơn vị</span>
                            <div class="payx-value">{{ $item->department ?: '-' }}</div>
                        </div>

                        <div class="payx-info">
                            <span class="payx-label">Chuyển khoản</span>
                            <div class="payx-value">{{ $item->bank_info ?: '-' }}</div>
                        </div>
                    </div>

                    <div class="payx-note-grid">
                        <div class="payx-note">
                            <span class="payx-label">Nội dung thanh toán</span>
                            <div class="payx-text">{!! nl2br(e($item->payment_content ?: '-')) !!}</div>
                        </div>

                        <div class="payx-note">
                            <span class="payx-label">Lý do</span>
                            <div class="payx-text">{!! nl2br(e($item->reason ?: '-')) !!}</div>
                        </div>
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

            @if($canSubmit || $canDelete)
                <section class="payx-action payx-animate" style="animation-delay:.09s">
                    <h3 class="payx-action-title">Thao tác phiếu</h3>
                    <div class="payx-action-desc">Phiếu hiện còn trong trạng thái có thể chỉnh sửa hoặc gửi duyệt.</div>

                    <div class="payx-inline-actions">
                        @if($canSubmit)
                            <form method="POST" action="{{ route('payment_requests.submit', $item->id) }}">
                                @csrf
                                <button type="submit" class="payx-btn blue">Gửi duyệt</button>
                            </form>
                        @endif

                        @if($canDelete)
                            <form method="POST" action="{{ route('payment_requests.destroy', $item->id) }}" onsubmit="return confirm('Bạn có chắc muốn xóa phiếu này?')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="payx-btn red">Xóa phiếu</button>
                            </form>
                        @endif
                    </div>
                </section>
            @endif

            @if($canAdminAction)
                <section class="payx-action payx-animate" style="animation-delay:.09s">
                    <h3 class="payx-action-title">Quản lý tài chính xử lý</h3>
                    <div class="payx-action-desc">Nhập ghi chú nếu cần, sau đó chọn duyệt hoặc từ chối.</div>

                    <div class="payx-action-grid">
                        <form method="POST" action="{{ route('payment_requests.admin_approve', $item->id) }}" class="payx-action-form">
                            @csrf
                            <h4>Duyệt phiếu</h4>
                            <textarea name="note" placeholder="Ghi chú duyệt..."></textarea>
                            <button type="submit" class="payx-btn green">Duyệt phiếu</button>
                        </form>

                        <form method="POST" action="{{ route('payment_requests.admin_reject', $item->id) }}" class="payx-action-form">
                            @csrf
                            <h4>Từ chối phiếu</h4>
                            <textarea name="note" placeholder="Lý do từ chối..." required minlength="2" maxlength="2000"></textarea>
                            <button type="submit" class="payx-btn red">Từ chối</button>
                        </form>
                    </div>
                </section>
            @endif

            @if($canAccAction)
                <section class="payx-action payx-animate" style="animation-delay:.09s">
                    <h3 class="payx-action-title">Kế toán xử lý</h3>
                    <div class="payx-action-desc">Xác nhận đã chi hoặc từ chối phiếu thanh toán.</div>

                    <div class="payx-action-grid">
                        <form method="POST" action="{{ route('payment_requests.acc_approve', $item->id) }}" class="payx-action-form">
                            @csrf
                            <h4>Xác nhận đã chi</h4>
                            <textarea name="note" placeholder="Ghi chú chi tiền..."></textarea>
                            <button type="submit" class="payx-btn green">Xác nhận đã chi</button>
                        </form>

                        <form method="POST" action="{{ route('payment_requests.acc_reject', $item->id) }}" class="payx-action-form">
                            @csrf
                            <h4>Từ chối</h4>
                            <textarea name="note" placeholder="Lý do từ chối..." required minlength="2" maxlength="2000"></textarea>
                            <button type="submit" class="payx-btn orange">Từ chối</button>
                        </form>
                    </div>
                </section>
            @endif
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

