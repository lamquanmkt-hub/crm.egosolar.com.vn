@extends('layouts.app')

@section('content')
<style>
    :root{
        --ego:#06b6d4;
        --ego2:#0891b2;
        --ink:#0f172a;
        --muted: rgba(15,23,42,.62);
        --card: rgba(255,255,255,.90);
        --border: rgba(15,23,42,.10);
        --shadow: 0 18px 50px rgba(15,23,42,.08);
        --shadow2: 0 10px 30px rgba(15,23,42,.08);
        --radius: 18px;
    }

    .page-shell{
        background: radial-gradient(900px 300px at 15% 0%, rgba(6,182,212,.14), transparent 60%),
                    radial-gradient(900px 300px at 85% 10%, rgba(59,130,246,.10), transparent 55%),
                    linear-gradient(180deg, #f7fbff, #f7fbff);
        border-radius: 22px;
        padding: 16px;
    }

    .card-glass{
        background: var(--card);
        border: 1px solid var(--border);
        border-radius: var(--radius);
        box-shadow: var(--shadow);
        backdrop-filter: blur(10px);
        -webkit-backdrop-filter: blur(10px);
        overflow:hidden;
    }

    .hero{
        padding: 14px 16px;
        display:flex;
        align-items:flex-start;
        justify-content:space-between;
        gap: 12px;
        background:
            radial-gradient(500px 200px at 20% 0%, rgba(6,182,212,.18), transparent 60%),
            linear-gradient(135deg, rgba(255,255,255,.86), rgba(255,255,255,.74));
        border-bottom: 1px solid rgba(15,23,42,.06);
    }

    .hero-title{
        margin:0;
        font-weight: 950;
        color: var(--ink);
        letter-spacing:.2px;
        display:flex;
        align-items:center;
        gap: 10px;
        font-size: 18px;
    }

    .hero-sub{
        margin-top: 6px;
        font-weight: 700;
        color: var(--muted);
        font-size: 12px;
    }

    .title-badge{
        width: 40px; height: 40px;
        border-radius: 14px;
        display:flex; align-items:center; justify-content:center;
        background: rgba(6,182,212,.16);
        border: 1px solid rgba(6,182,212,.25);
        color: var(--ego2);
        box-shadow: var(--shadow2);
        flex: 0 0 auto;
        font-size: 18px;
    }

    .btn-ego{
        border: none;
        border-radius: 12px;
        padding: 8px 12px;
        font-weight: 950;
        background: linear-gradient(135deg, var(--ego), var(--ego2));
        box-shadow: 0 14px 34px rgba(8,145,178,.18);
        transition: .15s ease;
        color: #fff;
        text-decoration: none;
        display:inline-flex;
        align-items:center;
        gap:6px;
        white-space: nowrap;
        font-size: 13px;
    }
    .btn-ego:hover{ transform: translateY(-1px); box-shadow: 0 18px 44px rgba(8,145,178,.24); color:#fff; }

    .btn-soft{
        border-radius: 12px;
        padding: 8px 12px;
        font-weight: 900;
        border: 1px solid rgba(15,23,42,.12);
        background: rgba(255,255,255,.92);
        box-shadow: var(--shadow2);
        transition: .15s ease;
        text-decoration:none;
        color: var(--ink);
        display:inline-flex;
        align-items:center;
        gap:6px;
        font-size: 13px;
    }
    .btn-soft:hover{ transform: translateY(-1px); color: var(--ink); }

    .alert{
        border-radius: 14px;
        border: 1px solid rgba(15,23,42,.08);
        box-shadow: var(--shadow2);
        font-size: 13px;
    }

    .compact,
    .compact *{
        font-size: 13px !important;
    }
    .compact .form-label{
        font-size: 12px !important;
        margin-bottom: 4px !important;
        font-weight: 900;
        color: rgba(15,23,42,.70);
    }
    .compact .form-control,
    .compact .form-select{
        padding: 8px 10px !important;
        border-radius: 12px !important;
    }

    .table-sm-ego thead th{ font-size: 11px !important; padding: 10px 8px !important; white-space: nowrap; }
    .table-sm-ego tbody td{ font-size: 13px !important; padding: 10px 8px !important; vertical-align: middle; }
    .table-sm-ego .badge{ font-size: 12px !important; padding: 6px 9px !important; border-radius: 999px; }
    .table-sm-ego .btn-mini{ font-size: 12px !important; padding: 6px 9px !important; border-radius: 11px !important; }

    .pill{
        border-radius: 999px;
        padding: 7px 10px;
        font-weight: 900;
        border: 1px solid rgba(15,23,42,.10);
        background: rgba(255,255,255,.92);
        display:inline-flex;
        align-items:center;
        gap:6px;
        white-space: nowrap;
    }

    .table-wrap{
        border: 1px solid rgba(15,23,42,.08);
        border-radius: 16px;
        overflow: hidden;
        box-shadow: var(--shadow2);
        background: rgba(255,255,255,.92);
    }

    .table thead{
        background: #0f172a !important;
        color: #fff;
    }

    .muted{
        color: rgba(15,23,42,.62);
        font-weight: 700;
    }

    .modal-compact .modal-title{ font-size: 15px !important; font-weight: 950; }
    .modal-compact .modal-body{ padding: 14px 16px !important; }
    .modal-compact .modal-footer{ padding: 12px 16px !important; }
    .modal-compact .form-control,
    .modal-compact .form-select{ font-size: 13px !important; padding: 8px 10px !important; border-radius: 12px !important; }
    .modal-compact .form-label{ font-size: 12px !important; font-weight: 900; color: rgba(15,23,42,.70); }


    /* EGO_PAYMENT_REASON_COLUMN_CSS */
    .payment-reason-cell{
        min-width: 220px;
        max-width: 280px;
        white-space: normal !important;
        line-height: 1.45;
        font-weight: 700;
        color: #334155;
    }

    .payment-reason-text{
        display: -webkit-box;
        -webkit-line-clamp: 3;
        -webkit-box-orient: vertical;
        overflow: hidden;
    }
    /* EGO_PAYMENT_REASON_COLUMN_CSS_END */

    .amount{
        font-weight: 950;
        color: #0b3b44;
    }

    .code-link{
        font-weight: 950;
        color: #0f172a;
        text-decoration: none;
    }
    .code-link:hover{ color: #0891b2; text-decoration: underline; }

    @media (max-width: 768px){
        .hero{ flex-wrap: wrap; }
    }

    @media (max-width: 767.98px){
        .container-fluid.px-4.py-3{
            padding-left: 0 !important;
            padding-right: 0 !important;
        }

        .page-shell{
            border-radius: 0;
            padding: 12px 12px 18px;
        }

        .card-glass.mb-3{
            margin-left: 12px;
            margin-right: 12px;
        }

        .hero{
            padding: 12px 12px;
        }

        .compact .card-glass{
            border-radius: 16px;
        }

        .m-list{
            padding: 0 12px;
            display: grid;
            grid-template-columns: 1fr;
            gap: 10px;
        }

        .m-card{
            background: rgba(255,255,255,.92);
            border: 1px solid rgba(15,23,42,.10);
            border-radius: 18px;
            box-shadow: var(--shadow2);
            overflow: hidden;
        }

        .m-card-top{
            padding: 12px 12px 10px;
            border-bottom: 1px solid rgba(15,23,42,.06);
            display:flex;
            align-items:flex-start;
            justify-content:space-between;
            gap: 10px;
        }

        .m-code{
            font-weight: 950;
            color: var(--ink);
            text-decoration: none;
            font-size: 14px;
        }
        .m-code:hover{ color: var(--ego2); text-decoration: underline; }

        .m-date{
            font-size: 12px;
            font-weight: 750;
            color: rgba(15,23,42,.55);
            white-space: nowrap;
        }

        .m-body{
            padding: 10px 12px 12px;
        }

        .m-row{
            display:flex;
            justify-content:space-between;
            gap: 10px;
            padding: 7px 0;
            border-bottom: 1px dashed rgba(15,23,42,.10);
        }
        .m-row:last-child{ border-bottom: none; padding-bottom: 0; }

        .m-k{
            font-size: 11.5px;
            font-weight: 900;
            color: rgba(15,23,42,.50);
            letter-spacing: .3px;
            text-transform: uppercase;
            flex: 0 0 auto;
        }

        .m-v{
            font-size: 13.5px;
            font-weight: 800;
            color: rgba(15,23,42,.86);
            text-align: right;
            max-width: 72%;
            word-break: break-word;
        }

        .m-amount{
            font-weight: 950;
            color: #0b3b44;
            font-size: 14px;
        }

        .m-actions{
            padding: 10px 12px 12px;
            border-top: 1px solid rgba(15,23,42,.06);
            display:flex;
            gap: 8px;
            flex-wrap: nowrap;
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
            justify-content: flex-start;
        }
        .m-actions::-webkit-scrollbar{ display:none; }

        .m-btn{
            border-radius: 14px;
            padding: 9px 10px;
            font-weight: 950;
            border: 1px solid rgba(15,23,42,.10);
            background: rgba(255,255,255,.92);
            box-shadow: var(--shadow2);
            text-decoration:none;
            display:inline-flex;
            align-items:center;
            gap: 8px;
            color: rgba(15,23,42,.86);
            white-space: nowrap;
            flex: 0 0 auto;
        }
        .m-btn i{ font-size: 16px; }

        .m-btn.ok{ border-color: rgba(25,135,84,.22); color: rgba(25,135,84,.95); }
        .m-btn.warn{ border-color: rgba(255,193,7,.28); color: rgba(161,98,7,.95); }
        .m-btn.danger{ border-color: rgba(220,53,69,.22); color: rgba(220,53,69,.95); }
        .m-btn.info{ border-color: rgba(13,110,253,.22); color: rgba(13,110,253,.95); }
        .m-btn.gray{ border-color: rgba(15,23,42,.16); color: rgba(15,23,42,.90); }
    }


    /* EGO_PAYMENT_BULK_APPROVAL_START */
    .bulk-approval-bar{
        min-height: 44px;
        margin: 0 0 8px;
        padding: 6px 9px 6px 11px;
        border: 1px solid #e2e8f0;
        border-left: 3px solid #06a6c7;
        border-radius: 10px;
        background: #fff;
        box-shadow: 0 2px 8px rgba(15,23,42,.045);
        display:flex;
        align-items:center;
        justify-content:space-between;
        gap:10px;
        flex-wrap:nowrap;
    }
    .bulk-approval-summary{
        min-width:0;
        display:flex;
        align-items:center;
        gap:7px;
        color:#475569;
        font-size:13px;
        font-weight:700;
        white-space:nowrap;
    }
    .bulk-approval-title{
        display:inline-flex;
        align-items:center;
        gap:6px;
        color:#0f172a;
        font-weight:850;
    }
    .bulk-approval-title i{ color:#0891b2; font-size:15px; }
    .bulk-approval-divider{ width:1px; height:18px; background:#e2e8f0; }
    .bulk-approval-count{ color:#334155; }
    .bulk-approval-count strong,
    #prBulkSelectedAmount{ color:#0f172a; font-weight:900; }
    .bulk-approval-dot{ color:#94a3b8; }
    .bulk-approval-actions{ display:flex; align-items:center; gap:6px; flex:0 0 auto; }
    .bulk-approval-actions .btn-soft,
    .bulk-approval-actions .btn-ego{
        min-height:32px;
        padding:5px 10px;
        border-radius:8px;
        font-size:12.5px;
        line-height:1;
        gap:5px;
        box-shadow:none;
    }
    .bulk-approval-actions .btn-soft:disabled,
    .bulk-approval-actions .btn-ego:disabled{ opacity:.5; }
    .bulk-select-all-mobile{ display:none; align-items:center; gap:6px; font-size:12.5px; font-weight:800; color:#475569; cursor:pointer; }
    .pr-bulk-checkbox, .pr-bulk-select-all{ width:16px; height:16px; cursor:pointer; accent-color:#0891b2; }
    .bulk-select-cell{ text-align:center; vertical-align:middle !important; }
    .bulk-mobile-select{ display:inline-flex; align-items:center; justify-content:center; flex:0 0 auto; padding-top:1px; }
    .bulk-approval-help{ display:none !important; }
    @media (max-width: 767.98px){
        .bulk-approval-bar{
            margin:0 12px 8px;
            padding:7px 9px;
            align-items:center;
            flex-wrap:wrap;
        }
        .bulk-approval-title,
        .bulk-approval-divider{ display:none; }
        .bulk-approval-summary{ font-size:12.5px; }
        .bulk-approval-actions{ width:100%; }
        .bulk-select-all-mobile{ display:inline-flex; margin-right:auto; }
        .bulk-approval-actions .btn-soft,
        .bulk-approval-actions .btn-ego{ min-height:31px; padding:5px 8px; }
    }
    /* EGO_PAYMENT_BULK_APPROVAL_END */
</style>

@php
    $statusLabels = $statusLabels ?? [
        'draft' => 'Nháp',
        'submitted' => 'Đã gửi duyệt',
        'admin_approved' => 'Quản lý tài chính đã duyệt',
        'admin_rejected' => 'Quản lý tài chính từ chối',
        'accounting_approved' => 'Kế toán đã chi',
        'accounting_rejected' => 'Kế toán từ chối',
    ];

    $statusBadges = [
        'draft' => 'secondary',
        'submitted' => 'warning',
        'admin_approved' => 'info',
        'admin_rejected' => 'danger',
        'accounting_approved' => 'success',
        'accounting_rejected' => 'danger',
    ];

    $canViewAll = $canViewAll ?? false;
    $canAdminApprove = $canAdminApprove ?? false;
    $canAccountingApprove = $canAccountingApprove ?? false;
    $canBulkApprove = $canBulkApprove ?? ($canAdminApprove || $canAccountingApprove);

    $exportParams = request()->only([
        'company', 'status', 'q', 'date_from', 'date_to', 'date_preset', 'created_by'
    ]);
@endphp

<div class="container-fluid px-4 py-3">
    <div class="page-shell">

        <div class="card-glass mb-3">
            <div class="hero">
                <div>
                    <h3 class="hero-title">
                        <span class="title-badge"><i class="bi bi-receipt"></i></span>
                        Danh sách đề nghị thanh toán
                    </h3>
                    <div class="hero-sub">Quản lý phiếu • duyệt • kế toán chi • tải PDF • xuất tổng thể</div>
                </div>

                <div class="d-flex flex-wrap gap-2">
                    <a class="btn-soft" href="{{ route('payment_requests.export_excel', $exportParams) }}">
                        <i class="bi bi-file-earmark-excel"></i> Xuất Excel
                    </a>

                    <a class="btn-soft" href="{{ route('payment_requests.export_pdf', $exportParams) }}">
                        <i class="bi bi-file-earmark-pdf"></i> Xuất PDF
                    </a>

                    <button class="btn-ego" data-bs-toggle="modal" data-bs-target="#createPRModal">
                        <i class="bi bi-plus-lg"></i> Tạo phiếu mới
                    </button>
                </div>
            </div>

            <div class="p-3 pt-0">
                @if(session('success'))
                    <div class="alert alert-success alert-dismissible fade show mb-2" role="alert">
                        <i class="bi bi-check-circle"></i> {{ session('success') }}
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                @endif
                @if(session('error'))
                    <div class="alert alert-danger alert-dismissible fade show mb-0" role="alert">
                        <i class="bi bi-exclamation-triangle"></i> {{ session('error') }}
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                @endif
                @if($errors->any())
                    <div class="alert alert-danger alert-dismissible fade show mb-2" role="alert">
                        <div class="fw-bold mb-1"><i class="bi bi-exclamation-triangle"></i> Chưa lưu được phiếu, kiểm tra lại các lỗi sau:</div>
                        <ul class="mb-0 ps-3">
                            @foreach($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                @endif
            </div>

            <div class="px-3 pb-3 compact">
                <div class="card-glass" style="box-shadow: var(--shadow2);">
                    <div class="p-3">
                        <form method="GET" action="{{ route('payment_requests.index') }}" class="row g-2 align-items-end">
                            <input
                                type="hidden"
                                name="date_filter_manual"
                                id="date_filter_manual"
                                value="0"
                            >

                            {{-- Dòng 1: Công ty - Trạng thái - Search --}}
                            <div class="col-12 col-md-3">
                                <label class="form-label mb-1">Công ty</label>
                                <select name="company" class="form-select">
                                    <option value="">-- Tất cả --</option>
                                    @foreach($companyOptions ?? [] as $c)
                                        <option value="{{ $c }}" @selected(request('company') === $c)>
                                            {{ $c }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="col-12 col-md-3">
                                <label class="form-label mb-1">Trạng thái</label>
                                <select name="status" class="form-select">
                                    <option value="">-- Tất cả --</option>
                                    @foreach(['draft','submitted','admin_approved','admin_rejected','accounting_approved','accounting_rejected'] as $st)
                                        <option value="{{ $st }}" @selected(request('status') === $st)>
                                            {{ $statusLabels[$st] ?? $st }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="col-12 col-md-6">
                                <label class="form-label mb-1">Tìm kiếm</label>
                                <input
                                    type="text"
                                    name="q"
                                    class="form-control"
                                    value="{{ request('q') }}"
                                    placeholder="Mã phiếu / Người nhận / Nội dung / Lý do..."
                                >
                            </div>

                            {{-- Dòng 2: Từ ngày - Đến ngày - Dropdown - Người tạo --}}
                            <div class="col-6 col-md-2">
                                <label class="form-label mb-1">Từ ngày</label>
                                <input
                                    type="date"
                                    name="date_from"
                                    id="date_from"
                                    class="form-control"
                                    oninput="document.getElementById('date_preset').value='custom';document.getElementById('date_filter_manual').value='1';"
                                    onchange="document.getElementById('date_preset').value='custom';document.getElementById('date_filter_manual').value='1';"
                                    value="{{ $effectiveDateFrom ?? request('date_from') }}"
                                >
                            </div>

                            <div class="col-6 col-md-2">
                                <label class="form-label mb-1">Đến ngày</label>
                                <input
                                    type="date"
                                    name="date_to"
                                    id="date_to"
                                    class="form-control"
                                    oninput="document.getElementById('date_preset').value='custom';document.getElementById('date_filter_manual').value='1';"
                                    onchange="document.getElementById('date_preset').value='custom';document.getElementById('date_filter_manual').value='1';"
                                    value="{{ $effectiveDateTo ?? request('date_to') }}"
                                >
                            </div>

                            <div class="col-12 col-md-4">
                                <label class="form-label mb-1">Khoảng thời gian</label>
                                <select
                                    name="date_preset"
                                    id="date_preset"
                                    class="form-select"
                                    onchange="document.getElementById('date_filter_manual').value='0';"
                                >
                                    <option value="this_month" @selected(($selectedDatePreset ?? 'this_month') === 'this_month')>Tháng này</option>
                                    <option value="last_month" @selected(($selectedDatePreset ?? '') === 'last_month')>Tháng trước</option>
                                    <option value="this_week" @selected(($selectedDatePreset ?? '') === 'this_week')>Tuần này</option>
                                    <option value="last_week" @selected(($selectedDatePreset ?? '') === 'last_week')>Tuần trước</option>
                                    <option value="today" @selected(($selectedDatePreset ?? '') === 'today')>Hôm nay</option>
                                    <option value="this_year" @selected(($selectedDatePreset ?? '') === 'this_year')>Năm nay</option>
                                    <option value="custom" @selected(($selectedDatePreset ?? '') === 'custom')>Tùy chỉnh</option>
                                    <option value="all_time" @selected(($selectedDatePreset ?? '') === 'all_time')>Tất cả thời gian</option>
                                </select>
                            </div>

                            @if($canViewAll)
                                <div class="col-12 col-md-4">
                                    <label class="form-label mb-1">Người tạo</label>
                                    <select name="created_by" class="form-select">
                                        <option value="">-- Tất cả --</option>
                                        @foreach(($creatorOptions ?? []) as $u)
                                            <option value="{{ $u->id }}" @selected((string)request('created_by') === (string)$u->id)>
                                                {{ $u->name }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                            @endif

                            <div class="col-12 mt-2 d-flex flex-wrap gap-2 align-items-center">
                                <button class="btn-ego" type="submit">
                                    <i class="bi bi-funnel"></i> Lọc
                                </button>

                                @if(
                                    request('status') ||
                                    request('created_by') ||
                                    request('company') ||
                                    request('date_from') ||
                                    request('date_to') ||
                                    request('date_preset') ||
                                    request('q')
                                )
                                    <a class="btn-soft" href="{{ route('payment_requests.index') }}">
                                        <i class="bi bi-x-circle"></i> Xóa lọc
                                    </a>
                                @endif

                                <span class="pill">
                                    <i class="bi bi-cash-coin"></i>
                                    Tổng chi phí đã chi:
                                    <span class="amount">{{ number_format((int)($totalPaid ?? 0)) }} đ</span>
                                </span>

                                <span class="muted">Mặc định đang lọc: tháng này</span>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>


        @if($canBulkApprove)
            <div class="bulk-approval-bar" id="prBulkApprovalBar">
                <div class="bulk-approval-summary">
                    <span class="bulk-approval-title">
                        <i class="bi bi-check2-square"></i>
                        Duyệt hàng loạt
                    </span>
                    <span class="bulk-approval-divider" aria-hidden="true"></span>
                    <span class="bulk-approval-count">
                        Đã chọn <strong id="prBulkSelectedCount">0</strong> phiếu
                    </span>
                    <span class="bulk-approval-dot" aria-hidden="true">•</span>
                    <strong id="prBulkSelectedAmount">0 đ</strong>
                </div>
                <div class="bulk-approval-actions">
                    <label class="bulk-select-all-mobile">
                        <input type="checkbox" id="prBulkSelectAllMobile" class="pr-bulk-select-all">
                        Chọn tất cả
                    </label>
                    <button type="button" class="btn-soft" id="prBulkClear" disabled>
                        <i class="bi bi-x-lg"></i> Bỏ chọn
                    </button>
                    <button type="button" class="btn-ego" id="prBulkOpenApprove" disabled>
                        <i class="bi bi-check2-all"></i> Duyệt đã chọn
                    </button>
                </div>
            </div>
        @endif

        {{-- PC TABLE --}}
        <div class="d-none d-md-block">
            <div class="table-wrap">
                <div class="table-responsive">
                    <table class="table table-hover table-bordered mb-0 align-middle table-sm-ego">
                        <thead class="text-center">
                        <tr>
                            @if($canBulkApprove)
                                <th style="width:48px" title="Chọn tất cả phiếu đủ điều kiện trên trang">
                                    <input type="checkbox" id="prBulkSelectAllDesktop" class="pr-bulk-select-all">
                                </th>
                            @endif
                            <th style="width:60px">STT</th>
                            <th style="width:140px">Mã</th>
                            <th>Người nhận</th>
                            {{-- EGO_PAYMENT_REASON_COLUMN_HEAD --}}
                            <th style="width:260px">Lý do</th>
                            <th style="width:140px">Số tiền</th>
                            <th style="width:190px">Trạng thái</th>
                            <th style="width:170px">Ngày tạo</th>
                            <th style="width:90px">Xem</th>
                            <th style="width:180px">Công ty</th>
                            <th style="width:160px">Người tạo</th>
                            <th style="width:330px">Thao tác</th>
                        </tr>
                        </thead>

                        <tbody>
                        @forelse($items as $it)
                            @php
                                $status = $it->status ?? 'draft';
                                $badge = $statusBadges[$status] ?? 'secondary';
                                $label = $statusLabels[$status] ?? $status;

                                $isOwner = ((int)$it->created_by === (int)auth()->id());

                                $canEditDelete = $isOwner && in_array($status, ['draft','admin_rejected','accounting_rejected'], true);
                                $canSubmit = $isOwner && in_array($status, ['draft','admin_rejected','accounting_rejected'], true);

                                $canAdminAction = ($status === 'submitted');
                                $canAccAction = ($status === 'admin_approved');
                                $bulkSelectable = ($canAdminApprove && $canAdminAction)
                                    || ($canAccountingApprove && $canAccAction);

                                $canDownloadPdf = ($status === 'accounting_approved');
                            @endphp

                            <tr>
                                @if($canBulkApprove)
                                    <td class="bulk-select-cell">
                                        @if($bulkSelectable)
                                            <input
                                                type="checkbox"
                                                class="pr-bulk-checkbox js-pr-bulk-checkbox"
                                                value="{{ $it->id }}"
                                                data-id="{{ $it->id }}"
                                                data-code="{{ $it->code }}"
                                                data-amount="{{ (int)$it->amount }}"
                                                aria-label="Chọn phiếu {{ $it->code }}"
                                            >
                                        @else
                                            <span class="text-muted">—</span>
                                        @endif
                                    </td>
                                @endif
                                <td class="text-center">
                                    {{ ($items->firstItem() ?? 1) + $loop->index }}
                                </td>

                                <td>
                                    <a href="{{ route('payment_requests.show', $it->id) }}" class="code-link">
                                        {{ $it->code }}
                                    </a>
                                </td>

                                <td class="fw-semibold">{{ $it->receiver_name }}</td>

                                {{-- EGO_PAYMENT_REASON_COLUMN_CELL --}}
                                <td class="payment-reason-cell">
                                    <div class="payment-reason-text" title="{{ $it->reason ?? '-' }}">
                                        {{ $it->reason ?: '-' }}
                                    </div>
                                </td>

                                <td class="text-end">
                                    <span class="amount">{{ number_format((int)$it->amount) }} đ</span>
                                </td>

                                <td class="text-center">
                                    <span class="badge bg-{{ $badge }}">{{ $label }}</span>
                                </td>

                                <td class="text-center">
                                    <span class="muted">{{ optional($it->created_at)->format('d/m/Y H:i') ?? $it->created_at }}</span>
                                </td>

                                <td class="text-center">
                                    <a href="{{ route('payment_requests.show', $it->id) }}" class="btn btn-outline-primary btn-mini">
                                        <i class="bi bi-box-arrow-up-right"></i>
                                    </a>
                                </td>

                                <td>{{ $it->company ?? '-' }}</td>

                                <td>{{ optional($it->creator)->name ?? ($it->created_by ?? '-') }}</td>

                                <td>
                                    <div class="d-flex gap-2 flex-wrap" style="white-space:nowrap;">
                                        <form action="{{ route('payment_requests.copy', $it->id) }}" method="POST"
                                              class="d-inline" onsubmit="return confirm('Sao chép phiếu này thành phiếu mới?');">
                                            @csrf
                                            <button class="btn btn-outline-primary btn-mini" type="submit">
                                                <i class="bi bi-files"></i> Sao chép
                                            </button>
                                        </form>


                                        @if($canEditDelete)
                                            <a href="{{ route('payment_requests.edit', $it->id) }}" class="btn btn-warning btn-mini">
                                                <i class="bi bi-pencil"></i> Sửa
                                            </a>

                                            <form action="{{ route('payment_requests.destroy', $it->id) }}" method="POST"
                                                  class="d-inline" onsubmit="return confirm('Xoá phiếu này?');">
                                                @csrf
                                                @method('DELETE')
                                                <button class="btn btn-danger btn-mini">
                                                    <i class="bi bi-trash"></i> Xoá
                                                </button>
                                            </form>
                                        @endif

                                        @if($canSubmit)
                                            <form action="{{ route('payment_requests.submit', $it->id) }}" method="POST"
                                                  class="d-inline" onsubmit="return confirm('Gửi duyệt phiếu này?');">
                                                @csrf
                                                <button class="btn btn-success btn-mini">
                                                    <i class="bi bi-send-check"></i> Gửi duyệt
                                                </button>
                                            </form>
                                        @endif

                                        @role('admin')
                                            @if($canAdminAction)
                                                <form action="{{ route('payment_requests.admin_approve', $it->id) }}" method="POST"
                                                      class="d-inline" onsubmit="return confirm('Duyệt phiếu này?');">
                                                    @csrf
                                                    <button class="btn btn-success btn-mini">
                                                        <i class="bi bi-check-circle"></i> Duyệt
                                                    </button>
                                                </form>

                                                <form action="{{ route('payment_requests.admin_reject', $it->id) }}" method="POST"
                                                      class="d-inline" onsubmit="return confirm('Từ chối phiếu này?');">
                                                    @csrf
                                                    <button class="btn btn-outline-danger btn-mini">
                                                        <i class="bi bi-x-circle"></i> Từ chối
                                                    </button>
                                                </form>
                                            @endif
                                        @endrole

                                        @role('accounting')
                                            @if($canAccAction)
                                                <form action="{{ route('payment_requests.acc_approve', $it->id) }}" method="POST"
                                                      class="d-inline" onsubmit="return confirm('Xác nhận đã chi phiếu này?');">
                                                    @csrf
                                                    <button class="btn btn-success btn-mini">
                                                        <i class="bi bi-cash-coin"></i> Đã chi
                                                    </button>
                                                </form>

                                                <form action="{{ route('payment_requests.acc_reject', $it->id) }}" method="POST"
                                                      class="d-inline" onsubmit="return confirm('Kế toán từ chối phiếu này?');">
                                                    @csrf
                                                    <button class="btn btn-outline-danger btn-mini">
                                                        <i class="bi bi-x-circle"></i> Từ chối
                                                    </button>
                                                </form>
                                            @endif
                                        @endrole

                                        @if($canDownloadPdf)
                                            <a href="{{ route('payment_requests.invoice', $it->id) }}" class="btn btn-outline-secondary btn-mini">
                                                <i class="bi bi-filetype-pdf"></i> PDF
                                            </a>
                                        @endif

                                        @if(
                                            !$canEditDelete &&
                                            !$canSubmit &&
                                            !(auth()->check() && auth()->user()->hasRole('admin') && $canAdminAction) &&
                                            !(auth()->check() && auth()->user()->hasRole('accounting') && $canAccAction) &&
                                            !$canDownloadPdf &&
                                            false
                                        )
                                            <span class="text-muted">-</span>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="{{ $canBulkApprove ? 12 : 11 }}" class="text-center text-muted py-4">
                                    Chưa có phiếu nào.
                                </td>
                            </tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="p-3 d-flex justify-content-end">
                    {{ $items->links('pagination::bootstrap-5') }}
                </div>
            </div>
        </div>

        {{-- MOBILE LIST --}}
        <div class="d-block d-md-none">
            <div class="m-list">
                @forelse($items as $it)
                    @php
                        $status = $it->status ?? 'draft';
                        $badge = $statusBadges[$status] ?? 'secondary';
                        $label = $statusLabels[$status] ?? $status;

                        $isOwner = ((int)$it->created_by === (int)auth()->id());

                        $canEditDelete = $isOwner && in_array($status, ['draft','admin_rejected','accounting_rejected'], true);
                        $canSubmit = $isOwner && in_array($status, ['draft','admin_rejected','accounting_rejected'], true);

                        $canAdminAction = ($status === 'submitted');
                        $canAccAction = ($status === 'admin_approved');
                        $bulkSelectable = ($canAdminApprove && $canAdminAction)
                            || ($canAccountingApprove && $canAccAction);

                        $canDownloadPdf = ($status === 'accounting_approved');
                    @endphp

                    <div class="m-card">
                        <div class="m-card-top">
                            <div class="d-flex align-items-start gap-2">
                                @if($canBulkApprove && $bulkSelectable)
                                    <span class="bulk-mobile-select">
                                        <input
                                            type="checkbox"
                                            class="pr-bulk-checkbox js-pr-bulk-checkbox"
                                            value="{{ $it->id }}"
                                            data-id="{{ $it->id }}"
                                            data-code="{{ $it->code }}"
                                            data-amount="{{ (int)$it->amount }}"
                                            aria-label="Chọn phiếu {{ $it->code }}"
                                        >
                                    </span>
                                @endif
                                <a class="m-code" href="{{ route('payment_requests.show', $it->id) }}">
                                    #{{ $it->code }}
                                </a>
                            </div>
                            <div class="m-date">
                                {{ optional($it->created_at)->format('d/m/Y H:i') ?? $it->created_at }}
                            </div>
                        </div>

                        <div class="m-body">
                            <div class="m-row">
                                <div class="m-k">Người nhận</div>
                                <div class="m-v">{{ $it->receiver_name }}</div>
                            </div>

                            {{-- EGO_PAYMENT_REASON_MOBILE_ROW --}}
                            <div class="m-row">
                                <div class="m-k">Lý do</div>
                                <div class="m-v">{{ $it->reason ?: '-' }}</div>
                            </div>

                            <div class="m-row">
                                <div class="m-k">Số tiền</div>
                                <div class="m-v m-amount">{{ number_format((int)$it->amount) }} đ</div>
                            </div>

                            <div class="m-row">
                                <div class="m-k">Trạng thái</div>
                                <div class="m-v">
                                    <span class="badge bg-{{ $badge }}">{{ $label }}</span>
                                </div>
                            </div>

                            <div class="m-row">
                                <div class="m-k">Công ty</div>
                                <div class="m-v">{{ $it->company ?? '-' }}</div>
                            </div>

                            <div class="m-row">
                                <div class="m-k">Người tạo</div>
                                <div class="m-v">{{ optional($it->creator)->name ?? ($it->created_by ?? '-') }}</div>
                            </div>
                        </div>

                        <div class="m-actions">
                            <a href="{{ route('payment_requests.show', $it->id) }}" class="m-btn info">
                                <i class="bi bi-box-arrow-up-right"></i> Xem
                            </a>
                            <form action="{{ route('payment_requests.copy', $it->id) }}" method="POST"
                                  class="d-inline" onsubmit="return confirm('Sao chép phiếu này thành phiếu mới?');">
                                @csrf
                                <button class="m-btn info" type="submit">
                                    <i class="bi bi-files"></i> Sao chép
                                </button>
                            </form>


                            @if($canEditDelete)
                                <a href="{{ route('payment_requests.edit', $it->id) }}" class="m-btn warn">
                                    <i class="bi bi-pencil"></i> Sửa
                                </a>

                                <form action="{{ route('payment_requests.destroy', $it->id) }}" method="POST"
                                      class="d-inline" onsubmit="return confirm('Xoá phiếu này?');">
                                    @csrf
                                    @method('DELETE')
                                    <button class="m-btn danger" type="submit">
                                        <i class="bi bi-trash"></i> Xoá
                                    </button>
                                </form>
                            @endif

                            @if($canSubmit)
                                <form action="{{ route('payment_requests.submit', $it->id) }}" method="POST"
                                      class="d-inline" onsubmit="return confirm('Gửi duyệt phiếu này?');">
                                    @csrf
                                    <button class="m-btn ok" type="submit">
                                        <i class="bi bi-send-check"></i> Gửi
                                    </button>
                                </form>
                            @endif

                            @role('admin')
                                @if($canAdminAction)
                                    <form action="{{ route('payment_requests.admin_approve', $it->id) }}" method="POST"
                                          class="d-inline" onsubmit="return confirm('Duyệt phiếu này?');">
                                        @csrf
                                        <button class="m-btn ok" type="submit">
                                            <i class="bi bi-check-circle"></i> Duyệt
                                        </button>
                                    </form>

                                    <form action="{{ route('payment_requests.admin_reject', $it->id) }}" method="POST"
                                          class="d-inline" onsubmit="return confirm('Từ chối phiếu này?');">
                                        @csrf
                                        <button class="m-btn danger" type="submit">
                                            <i class="bi bi-x-circle"></i> T.chối
                                        </button>
                                    </form>
                                @endif
                            @endrole

                            @role('accounting')
                                @if($canAccAction)
                                    <form action="{{ route('payment_requests.acc_approve', $it->id) }}" method="POST"
                                          class="d-inline" onsubmit="return confirm('Xác nhận đã chi phiếu này?');">
                                        @csrf
                                        <button class="m-btn ok" type="submit">
                                            <i class="bi bi-cash-coin"></i> Đã chi
                                        </button>
                                    </form>

                                    <form action="{{ route('payment_requests.acc_reject', $it->id) }}" method="POST"
                                          class="d-inline" onsubmit="return confirm('Kế toán từ chối phiếu này?');">
                                        @csrf
                                        <button class="m-btn danger" type="submit">
                                            <i class="bi bi-x-circle"></i> T.chối
                                        </button>
                                    </form>
                                @endif
                            @endrole

                            @if($canDownloadPdf)
                                <a href="{{ route('payment_requests.invoice', $it->id) }}" class="m-btn gray">
                                    <i class="bi bi-filetype-pdf"></i> PDF
                                </a>
                            @endif

                            @if(
                                !$canEditDelete &&
                                !$canSubmit &&
                                !(auth()->check() && auth()->user()->hasRole('admin') && $canAdminAction) &&
                                !(auth()->check() && auth()->user()->hasRole('accounting') && $canAccAction) &&
                                !$canDownloadPdf &&
                                false
                            )
                                <span class="text-muted">-</span>
                            @endif
                        </div>
                    </div>
                @empty
                    <div class="text-center text-muted py-4">Chưa có phiếu nào.</div>
                @endforelse
            </div>

            <div class="p-3 d-flex justify-content-end" style="padding-left:12px;padding-right:12px;">
                {{ $items->links('pagination::bootstrap-5') }}
            </div>
        </div>

    </div>
</div>


@if($canBulkApprove)
<div class="modal fade" id="prBulkApproveModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content modal-compact" style="border-radius:18px; overflow:hidden;">
            <form id="prBulkApproveForm" method="POST" action="{{ route('payment_requests.bulk_approve') }}">
                @csrf
                <div id="prBulkApproveInputs"></div>
                <div class="modal-header" style="background:linear-gradient(135deg, rgba(6,182,212,.18), rgba(59,130,246,.10));">
                    <h5 class="modal-title"><i class="bi bi-check2-all me-1"></i> Duyệt nhiều đề nghị thanh toán</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-info mb-3">
                        Bạn đang xử lý <strong id="prBulkModalCount">0</strong> phiếu,
                        tổng giá trị <strong id="prBulkModalAmount">0 đ</strong>.
                    </div>
                    <label class="form-label" for="prBulkNote">Ghi chú chung (không bắt buộc)</label>
                    <textarea name="note" id="prBulkNote" class="form-control" rows="4" maxlength="2000"
                        placeholder="Ghi chú này sẽ được lưu vào lịch sử duyệt của từng phiếu..."></textarea>
                    <div class="bulk-approval-help mt-2">
                        Hệ thống chỉ xử lý phiếu đúng trạng thái và đúng quyền. Mỗi phiếu chỉ tiến một bước duyệt trong lần này.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-soft" data-bs-dismiss="modal"><i class="bi bi-x-lg"></i> Hủy</button>
                    <button type="submit" class="btn-ego" id="prBulkSubmitButton">
                        <i class="bi bi-check-circle"></i> Xác nhận duyệt
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endif

{{-- Modal tạo phiếu --}}
<div class="modal fade" id="createPRModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content modal-compact" style="border-radius:18px; overflow:hidden;">
            <form
                id="egoPaymentRequestCreateForm"
                method="POST"
                action="{{ route('payment_requests.store') }}"
                enctype="multipart/form-data"
            >
                @csrf

                <div class="modal-header" style="background: linear-gradient(135deg, rgba(6,182,212,.20), rgba(8,145,178,.12)); border-bottom:1px solid rgba(15,23,42,.08);">
                    <h5 class="modal-title">
                        <i class="bi bi-plus-circle me-1"></i> Tạo đề nghị thanh toán
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>

                <div class="modal-body compact">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Công ty <span class="text-danger">*</span></label>
                            <select name="company" class="form-select" required>
                                <option value="">-- Chọn công ty --</option>
                                @foreach($companyOptions ?? [] as $c)
                                    <option value="{{ $c }}">{{ $c }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Loại phiếu <span class="text-danger">*</span></label>
                            <select name="doc_type" class="form-select" required>
                                <option value="payment_request">Phiếu đề nghị thanh toán</option>
                                <option value="payment_voucher">Phiếu chi</option>
                                <option value="advance">Phiếu đề nghị tạm ứng</option>
                            <option value="refund_request">Đề nghị hoàn tiền</option>
                            </select>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Người nhận <span class="text-danger">*</span></label>
                            <input name="receiver_name" class="form-control" required>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Đơn vị</label>
                            <input name="department" class="form-control">
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Số tiền (VND) <span class="text-danger">*</span></label>
                            <input name="amount" type="number" min="1" class="form-control" required>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Nội dung thanh toán</label>
                            <input name="payment_content" class="form-control" placeholder="VD: Thanh toán đợt 1 / Tạm ứng vật tư...">
                        </div>

                        <div class="col-md-12">
                            <label class="form-label">Lý do <span class="text-danger">*</span></label>
                            <textarea name="reason" id="reason_editor" class="form-control" rows="6"></textarea>
                        </div>

                        <div class="col-md-12">
                            <label class="form-label">Thông tin chuyển khoản</label>
                            <input name="bank_info" class="form-control" placeholder="VD: MB BANK - 0123... - Nguyễn Văn A">
                        </div>

                        <div class="col-md-12">
                            <label class="form-label">Chứng từ (ảnh / file)</label>
                            <input
                                type="file"
                                name="attachments[]"
                                class="form-control"
                                multiple
                                accept="image/*,.pdf,.doc,.docx,.xls,.xlsx"
                            >
                            <div class="muted mt-1">Có thể chọn nhiều file • JPG/PNG/PDF/DOC/XLS</div>
                        </div>
                    </div>
                </div>

                <div class="modal-footer">
                    <button
                        class="btn-ego"
                        type="submit"
                        id="egoPaymentRequestCreateSubmit"
                    >
                        <i class="bi bi-save2"></i> Lưu phiếu
                    </button>
                    <button type="button" class="btn-soft" data-bs-dismiss="modal">
                        <i class="bi bi-x-lg"></i> Đóng
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.ckeditor.com/4.25.1-lts/standard/ckeditor.js"></script>
<script>
(function () {
    function initPaymentReasonEditor() {
        var textarea = document.getElementById('reason_editor');

        if (!textarea || !window.CKEDITOR) {
            return;
        }

        var editor = CKEDITOR.instances.reason_editor;

        if (!editor) {
            editor = CKEDITOR.replace('reason_editor', {
                height: 220,
                removeButtons: 'Image,Flash,Smiley,SpecialChar,About'
            });
        }

        function syncEditorContent() {
            try {
                editor.updateElement();
            } catch (error) {
                console.error('Không đồng bộ được nội dung lý do:', error);
            }
        }

        editor.on('instanceReady', syncEditorContent);
        editor.on('change', syncEditorContent);
        editor.on('blur', syncEditorContent);
    }

    if (document.readyState === 'loading') {
        document.addEventListener(
            'DOMContentLoaded',
            initPaymentReasonEditor
        );
    } else {
        initPaymentReasonEditor();
    }
})();
</script>

<script>
  document.addEventListener('DOMContentLoaded', function () {
    if (window.CKEDITOR && CKEDITOR.instances && CKEDITOR.instances.reason_editor) {
      // ok
    } else if (window.CKEDITOR) {
      CKEDITOR.replace('reason_editor');
    }

    const form = document.querySelector('#createPRModal form');

    if (form) {
      form.addEventListener('submit', function (event) {
        const textarea = form.querySelector('[name="reason"]');
        const editor = window.CKEDITOR
          && CKEDITOR.instances
          ? CKEDITOR.instances.reason_editor
          : null;

        if (editor) {
          editor.updateElement();
        }

        const htmlContent = textarea
          ? String(textarea.value || '')
          : '';

        const plainContent = htmlContent
          .replace(/<br\\s*\\/?/gi, ' ')
          .replace(/<[^>]*>/g, ' ')
          .replace(/&nbsp;/gi, ' ')
          .replace(/\\s+/g, ' ')
          .trim();

        if (!plainContent) {
          event.preventDefault();

          alert('Vui lòng nhập lý do thanh toán.');

          if (editor) {
            editor.focus();
          } else if (textarea) {
            textarea.focus();
          }

          return;
        }

        const submitButton = form.querySelector(
          'button[type="submit"]'
        );

        if (submitButton && !submitButton.disabled) {
          submitButton.disabled = true;
          submitButton.innerHTML =
            '<span class="spinner-border spinner-border-sm me-1"></span>' +
            ' Đang lưu...';
        }
      });
    }

    const preset = document.getElementById('date_preset');
    const from = document.getElementById('date_from');
    const to = document.getElementById('date_to');

    if (from) {
      from.addEventListener('change', function () {
        if (preset) preset.value = 'custom';
      });
    }

    if (to) {
      to.addEventListener('change', function () {
        if (preset) preset.value = 'custom';
      });
    }
  });
</script>






@if($canBulkApprove)
<script>
document.addEventListener('DOMContentLoaded', function () {
    const rowCheckboxes = Array.from(document.querySelectorAll('.js-pr-bulk-checkbox'));
    const selectAllDesktop = document.getElementById('prBulkSelectAllDesktop');
    const selectAllMobile = document.getElementById('prBulkSelectAllMobile');
    const clearButton = document.getElementById('prBulkClear');
    const openButton = document.getElementById('prBulkOpenApprove');
    const countText = document.getElementById('prBulkSelectedCount');
    const amountText = document.getElementById('prBulkSelectedAmount');
    const modalCount = document.getElementById('prBulkModalCount');
    const modalAmount = document.getElementById('prBulkModalAmount');
    const modalElement = document.getElementById('prBulkApproveModal');
    const bulkForm = document.getElementById('prBulkApproveForm');
    const inputsBox = document.getElementById('prBulkApproveInputs');
    const submitButton = document.getElementById('prBulkSubmitButton');

    function uniqueRows() {
        const map = new Map();
        rowCheckboxes.forEach(function (box) {
            const id = String(box.dataset.id || box.value || '');
            if (!id || map.has(id)) return;
            map.set(id, {
                id: id,
                amount: Number(box.dataset.amount || 0),
                checked: rowCheckboxes.some(function (candidate) {
                    return String(candidate.dataset.id || candidate.value || '') === id && candidate.checked;
                })
            });
        });
        return Array.from(map.values());
    }

    function selectedRows() { return uniqueRows().filter(function (row) { return row.checked; }); }
    function formatMoney(value) { return new Intl.NumberFormat('vi-VN').format(Number(value || 0)) + ' đ'; }

    function setRowChecked(id, checked) {
        rowCheckboxes.forEach(function (box) {
            if (String(box.dataset.id || box.value || '') === String(id)) box.checked = checked;
        });
    }

    function rebuildHiddenInputs(rows) {
        if (!inputsBox) return;
        inputsBox.innerHTML = '';
        rows.forEach(function (row) {
            const input = document.createElement('input');
            input.type = 'hidden'; input.name = 'ids[]'; input.value = row.id;
            inputsBox.appendChild(input);
        });
    }

    function refresh() {
        const allRows = uniqueRows();
        const selected = allRows.filter(function (row) { return row.checked; });
        const total = selected.reduce(function (sum, row) { return sum + row.amount; }, 0);
        const hasSelection = selected.length > 0;
        const allSelected = allRows.length > 0 && selected.length === allRows.length;
        const partlySelected = selected.length > 0 && selected.length < allRows.length;

        if (countText) countText.textContent = String(selected.length);
        if (amountText) amountText.textContent = formatMoney(total);
        if (modalCount) modalCount.textContent = String(selected.length);
        if (modalAmount) modalAmount.textContent = formatMoney(total);
        if (clearButton) clearButton.disabled = !hasSelection;
        if (openButton) openButton.disabled = !hasSelection;

        [selectAllDesktop, selectAllMobile].forEach(function (box) {
            if (!box) return;
            box.checked = allSelected; box.indeterminate = partlySelected; box.disabled = allRows.length === 0;
        });
        rebuildHiddenInputs(selected);
    }

    rowCheckboxes.forEach(function (box) {
        box.addEventListener('change', function () {
            setRowChecked(box.dataset.id || box.value, box.checked);
            refresh();
        });
    });

    [selectAllDesktop, selectAllMobile].forEach(function (box) {
        if (!box) return;
        box.addEventListener('change', function () {
            rowCheckboxes.forEach(function (rowBox) { rowBox.checked = box.checked; });
            refresh();
        });
    });

    if (clearButton) clearButton.addEventListener('click', function () {
        rowCheckboxes.forEach(function (box) { box.checked = false; }); refresh();
    });

    if (openButton) openButton.addEventListener('click', function () {
        const selected = selectedRows();
        if (!selected.length) { window.alert('Vui lòng chọn ít nhất một phiếu.'); return; }
        rebuildHiddenInputs(selected);
        if (modalElement && window.bootstrap && bootstrap.Modal) {
            bootstrap.Modal.getOrCreateInstance(modalElement).show();
        } else if (bulkForm && window.confirm('Duyệt ' + selected.length + ' phiếu đã chọn?')) {
            bulkForm.submit();
        }
    });

    if (bulkForm) bulkForm.addEventListener('submit', function (event) {
        const selected = selectedRows();
        if (!selected.length) { event.preventDefault(); window.alert('Danh sách chọn đang trống.'); return; }
        rebuildHiddenInputs(selected);
        if (submitButton) {
            submitButton.disabled = true;
            submitButton.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Đang xử lý...';
        }
    });

    refresh();
});
</script>
@endif

{{-- EGO_PAYMENT_INDEX_CLEAN_FIX_START --}}
@php
    $egoPaymentDueMap = collect();

    if (isset($items)) {
        $egoPaymentDueMap = $items->getCollection()->mapWithKeys(function ($x) {
            $v = '-';

            if (!empty($x->payment_due_date)) {
                try {
                    $v = \Illuminate\Support\Carbon::parse($x->payment_due_date)->format('d/m/Y');
                } catch (\Throwable $e) {
                    $v = (string) $x->payment_due_date;
                }
            }

            return [$x->code => $v];
        });
    }
@endphp

<style>
    .ego-pay-clean-wrap {
        animation: egoCleanFade .25s ease both;
    }

    @keyframes egoCleanFade {
        from {
            opacity: .92;
            transform: translateY(4px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    table.ego-pay-clean-table {
        border-radius: 14px !important;
        overflow: hidden !important;
        background: #fff !important;
    }

    table.ego-pay-clean-table thead th {
        background: #f8fbff !important;
        color: #0f172a !important;
        font-size: 12px !important;
        font-weight: 800 !important;
        padding: 10px 8px !important;
        border-bottom: 1px solid #dbe5f0 !important;
        white-space: nowrap !important;
    }

    table.ego-pay-clean-table tbody td {
        font-size: 12.5px !important;
        color: #0f172a !important;
        padding: 10px 8px !important;
        vertical-align: middle !important;
        transition: background .15s ease;
    }

    table.ego-pay-clean-table tbody tr:hover td {
        background: #f3fbff !important;
    }

    table.ego-pay-clean-table .ego-due-th {
        background: #eefcff !important;
        color: #0369a1 !important;
    }

    table.ego-pay-clean-table .ego-due-td {
        color: #0369a1 !important;
        font-weight: 800 !important;
        text-align: center !important;
        white-space: nowrap !important;
        background: #f4fdff !important;
    }

    table.ego-pay-clean-table .ego-created-td {
        color: #475569 !important;
        text-align: center !important;
        white-space: nowrap !important;
    }

    table.ego-pay-clean-table .ego-code-td {
        color: #075985 !important;
        font-weight: 900 !important;
    }

    table.ego-pay-clean-table .ego-money-td {
        color: #075985 !important;
        font-weight: 900 !important;
        white-space: nowrap !important;
    }

    table.ego-pay-clean-table .badge,
    table.ego-pay-clean-table span[class*="badge"] {
        border-radius: 999px !important;
        font-size: 11px !important;
        font-weight: 800 !important;
        padding: 5px 9px !important;
    }

    .ego-pay-clean-btn {
        border-radius: 10px !important;
        font-size: 12px !important;
        font-weight: 800 !important;
        transition: .15s ease !important;
    }

    .ego-pay-clean-btn:hover {
        transform: translateY(-1px);
    }

    .ego-payment-due-field label {
        font-size: 12px !important;
        font-weight: 700 !important;
        color: #334155 !important;
    }

    .ego-payment-due-field input {
        border-radius: 10px !important;
    }
</style>

<script>
(function () {
    var dueMap = @json($egoPaymentDueMap);

    function text(el) {
        return (el && el.textContent ? el.textContent : '').replace(/\s+/g, ' ').trim();
    }

    function lower(el) {
        return text(el).toLowerCase();
    }

    function addClass(el, className) {
        if (el && !el.classList.contains(className)) {
            el.classList.add(className);
        }
    }

    function insertAfter(parent, node, ref) {
        if (!parent || !node || !ref) return;
        parent.insertBefore(node, ref.nextSibling);
    }

    function findHeaderIndex(headers, keyword) {
        keyword = keyword.toLowerCase();

        for (var i = 0; i < headers.length; i++) {
            if (lower(headers[i]).indexOf(keyword) !== -1) return i;
        }

        return -1;
    }

    function getRowCode(row, codeIndex) {
        var cell = row.children[codeIndex];
        return cell ? text(cell) : '';
    }

    function fixDueColumn() {
        document.querySelectorAll('table').forEach(function (table) {
            var headerRow = table.querySelector('thead tr');
            var bodyRows = table.querySelectorAll('tbody tr');

            if (!headerRow || !bodyRows.length) return;

            var headers = Array.prototype.slice.call(headerRow.children);

            var codeIndex = findHeaderIndex(headers, 'mã');
            var createdIndex = findHeaderIndex(headers, 'ngày tạo');

            if (codeIndex < 0 || createdIndex < 0) return;

            addClass(table, 'ego-pay-clean-table');

            if (table.parentElement) {
                addClass(table.parentElement, 'ego-pay-clean-wrap');
            }

            var dueIndex = -1;
            headers.forEach(function (th, index) {
                var t = lower(th);
                if (t.indexOf('ngày phải') !== -1 || t.indexOf('ngày tt') !== -1) {
                    dueIndex = index;
                }
            });

            var createdTh = headerRow.children[createdIndex];
            var dueTh = null;

            if (dueIndex >= 0) {
                dueTh = headerRow.children[dueIndex];
                dueTh.textContent = 'Hạn thanh toán';

                if (createdTh && dueTh !== createdTh.nextElementSibling) {
                    insertAfter(headerRow, dueTh, createdTh);
                }

                bodyRows.forEach(function (row) {
                    var cells = Array.prototype.slice.call(row.children);
                    var dueCell = cells[dueIndex];
                    var createdCell = cells[createdIndex];

                    if (dueCell && createdCell && dueCell !== createdCell.nextElementSibling) {
                        insertAfter(row, dueCell, createdCell);
                    }
                });
            } else {
                dueTh = document.createElement('th');
                dueTh.textContent = 'Hạn thanh toán';

                insertAfter(headerRow, dueTh, createdTh);

                bodyRows.forEach(function (row) {
                    var cells = Array.prototype.slice.call(row.children);
                    var code = getRowCode(row, codeIndex);
                    var td = document.createElement('td');

                    td.textContent = dueMap[code] || '-';
                    insertAfter(row, td, cells[createdIndex]);
                });
            }

            headers = Array.prototype.slice.call(headerRow.children);

            var finalCodeIndex = findHeaderIndex(headers, 'mã');
            var finalAmountIndex = findHeaderIndex(headers, 'số tiền');
            var finalCreatedIndex = findHeaderIndex(headers, 'ngày tạo');
            var finalDueIndex = findHeaderIndex(headers, 'ngày phải');

            if (finalDueIndex >= 0) addClass(headerRow.children[finalDueIndex], 'ego-due-th');

            bodyRows.forEach(function (row) {
                var cells = row.children;

                if (finalCodeIndex >= 0 && cells[finalCodeIndex]) addClass(cells[finalCodeIndex], 'ego-code-td');
                if (finalAmountIndex >= 0 && cells[finalAmountIndex]) addClass(cells[finalAmountIndex], 'ego-money-td');
                if (finalCreatedIndex >= 0 && cells[finalCreatedIndex]) addClass(cells[finalCreatedIndex], 'ego-created-td');
                if (finalDueIndex >= 0 && cells[finalDueIndex]) addClass(cells[finalDueIndex], 'ego-due-td');
            });
        });
    }

    function addDueDateToForms() {
        document.querySelectorAll('form').forEach(function (form) {
            if (form.querySelector('[name="payment_due_date"]')) return;

            var amountInput = form.querySelector('[name="amount"]');
            if (!amountInput) return;

            var amountBox = amountInput.closest('.col-md-6, .col-md-4, .col-lg-6, .col-lg-4, .form-group, div') || amountInput.parentElement;
            if (!amountBox || !amountBox.parentNode) return;

            var box = document.createElement('div');
            box.className = (amountBox.className || 'form-group') + ' ego-payment-due-field';
            box.innerHTML =
                '<label class="form-label">Ngày phải thanh toán</label>' +
                '<input type="date" name="payment_due_date" class="form-control" value="{{ old('payment_due_date') }}">';

            amountBox.parentNode.insertBefore(box, amountBox.nextSibling);
        });
    }

    function enhanceButtons() {
        document.querySelectorAll('a, button').forEach(function (el) {
            var t = lower(el);

            if (
                t.indexOf('tạo phiếu') !== -1 ||
                t.indexOf('xuất') !== -1 ||
                t.indexOf('lọc') !== -1 ||
                t.indexOf('sửa') !== -1 ||
                t.indexOf('xóa') !== -1 ||
                t.indexOf('xoá') !== -1 ||
                t.indexOf('gửi duyệt') !== -1 ||
                t === 'pdf'
            ) {
                addClass(el, 'ego-pay-clean-btn');
            }
        });
    }

    function run() {
        fixDueColumn();
        addDueDateToForms();
        enhanceButtons();
    }

    document.addEventListener('DOMContentLoaded', run);
    setTimeout(run, 300);
})();
</script>


{{-- EGO_PAYMENT_REJECT_MODAL_START --}}
<style>
    .ego-pr-full-backdrop {
        position: fixed;
        inset: 0;
        z-index: 99999;
        display: none;
        align-items: center;
        justify-content: center;
        background: rgba(15, 23, 42, .50);
        padding: 18px;
    }

    .ego-pr-full-backdrop.show {
        display: flex;
    }

    .ego-pr-full-modal {
        width: min(980px, 100%);
        max-height: 92vh;
        overflow: hidden;
        border-radius: 24px;
        background: #f5f8fc;
        border: 1px solid rgba(226, 232, 240, .95);
        box-shadow: 0 28px 90px rgba(15, 23, 42, .32);
    }

    .ego-pr-full-head {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        gap: 14px;
        padding: 16px 18px;
        background:
            radial-gradient(circle at top right, rgba(14,165,233,.18), transparent 32%),
            linear-gradient(135deg, #fff, #eefbff);
        border-bottom: 1px solid #dcebf8;
    }

    .ego-pr-full-title {
        margin: 0;
        color: #071b33;
        font-size: 18px;
        font-weight: 950;
        letter-spacing: -.01em;
    }

    .ego-pr-full-desc {
        margin-top: 4px;
        color: #64748b;
        font-size: 12.5px;
        font-weight: 700;
    }

    .ego-pr-full-close {
        width: 34px;
        height: 34px;
        border-radius: 999px;
        border: 0;
        background: #fff;
        color: #64748b;
        font-size: 24px;
        line-height: 30px;
        cursor: pointer;
        box-shadow: 0 8px 22px rgba(15,23,42,.08);
    }

    .ego-pr-full-body {
        padding: 16px;
        max-height: calc(92vh - 76px);
        overflow: auto;
        display: grid;
        gap: 14px;
    }

    .ego-pr-loading {
        border: 1px dashed #cbd5e1;
        background: #f8fafc;
        color: #64748b;
        border-radius: 16px;
        padding: 18px;
        text-align: center;
        font-weight: 800;
    }

    .ego-pr-full-modal .payx-card {
        background: rgba(255,255,255,.96);
        border: 1px solid #e4ebf3;
        border-radius: 20px;
        box-shadow: 0 8px 24px rgba(15,23,42,.055);
        overflow: hidden;
    }

    .ego-pr-full-modal .payx-card-head {
        padding: 15px 16px 0;
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        gap: 12px;
    }

    .ego-pr-full-modal .payx-card-title {
        margin: 0;
        font-size: 14px;
        font-weight: 900;
        color: #071b33;
    }

    .ego-pr-full-modal .payx-card-desc {
        margin-top: 4px;
        font-size: 12px;
        color: #64748b;
        font-weight: 650;
    }

    .ego-pr-full-modal .payx-card-body {
        padding: 15px 16px 16px;
    }

    .ego-pr-full-modal .payx-info-grid {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: 10px;
        margin-bottom: 12px;
    }

    .ego-pr-full-modal .payx-info {
        padding: 11px 12px;
        border-radius: 14px;
        background: linear-gradient(180deg, #f8fafc, #fff);
        border: 1px solid #e8eef6;
        min-height: 70px;
    }

    .ego-pr-full-modal .payx-label {
        display: block;
        color: #64748b;
        font-size: 10.5px;
        text-transform: uppercase;
        letter-spacing: .075em;
        font-weight: 900;
        margin-bottom: 6px;
    }

    .ego-pr-full-modal .payx-value {
        color: #071b33;
        font-size: 13px;
        line-height: 1.5;
        font-weight: 800;
        word-break: break-word;
    }

    .ego-pr-full-modal .payx-value.money {
        color: #0369a1;
        font-size: 16px;
        font-weight: 950;
    }

    .ego-pr-full-modal .payx-note-grid {
        display: grid;
        grid-template-columns: 1fr;
        gap: 10px;
    }

    .ego-pr-full-modal .payx-note {
        position: relative;
        padding: 13px 14px 13px 16px;
        border-radius: 16px;
        background: #fff;
        border: 1px solid #e6edf6;
        overflow: hidden;
    }

    .ego-pr-full-modal .payx-note:before {
        content: "";
        position: absolute;
        left: 0;
        top: 12px;
        bottom: 12px;
        width: 3px;
        border-radius: 999px;
        background: linear-gradient(180deg, #0ea5e9, #06b6d4);
    }

    .ego-pr-full-modal .payx-text {
        color: #0f172a;
        font-size: 13px;
        line-height: 1.72;
        font-weight: 650;
        word-break: break-word;
    }

    .ego-pr-full-modal .payx-chip,
    .ego-pr-full-modal .badge {
        display: inline-flex;
        align-items: center;
        border-radius: 999px;
        padding: 6px 10px;
        font-size: 12px;
        font-weight: 850;
        background: #e0f2fe;
        color: #0369a1;
        border: 1px solid #bae6fd;
    }

    .ego-pr-full-modal .payx-action {
        border-radius: 20px;
        padding: 14px;
        border: 1px solid #dcebf8;
        background:
            radial-gradient(circle at top right, rgba(14,165,233,.12), transparent 30%),
            #fff;
        box-shadow: 0 8px 24px rgba(15,23,42,.055);
    }

    .ego-pr-full-modal .payx-action-title {
        font-size: 14px;
        font-weight: 950;
        margin: 0 0 4px;
        color: #071b33;
    }

    .ego-pr-full-modal .payx-action-desc {
        color: #64748b;
        font-size: 12px;
        margin-bottom: 12px;
        font-weight: 650;
    }

    .ego-pr-full-modal .payx-action-grid {
        display: grid;
        grid-template-columns: 1fr;
        gap: 10px;
    }

    .ego-pr-full-modal .payx-action-form {
        padding: 12px;
        border-radius: 16px;
        border: 1px solid #e6edf6;
        background: #fff;
    }

    .ego-pr-full-modal .payx-action-form h4 {
        margin: 0 0 8px;
        font-size: 13px;
        font-weight: 950;
        color: #071b33;
    }

    .ego-pr-full-modal .payx-action-form textarea {
        width: 100%;
        min-height: 105px;
        border: 1px solid #d8e3ef;
        border-radius: 12px;
        padding: 10px 11px;
        font-size: 13px;
        line-height: 1.55;
        outline: none;
        resize: vertical;
        margin-bottom: 9px;
        transition: .18s ease;
        font-weight: 650;
    }

    .ego-pr-full-modal .payx-action-form textarea:focus {
        border-color: #ef4444;
        box-shadow: 0 0 0 4px rgba(239,68,68,.11);
    }

    .ego-pr-full-modal .payx-btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 7px;
        border: 0;
        border-radius: 13px;
        padding: 9px 13px;
        font-size: 13px;
        font-weight: 950;
        cursor: pointer;
        text-decoration: none;
    }

    .ego-pr-full-modal .payx-btn.red {
        color: #fff;
        background: linear-gradient(135deg, #ef4444, #dc2626);
        box-shadow: 0 10px 20px rgba(220,38,38,.18);
    }

    .ego-pr-full-modal .payx-btn.red:disabled {
        opacity: .45;
        cursor: not-allowed;
        box-shadow: none;
    }

    .ego-pr-full-modal .ego-pr-cancel {
        background: #e2e8f0;
        color: #334155;
    }

    .ego-pr-full-actions {
        display: flex;
        justify-content: flex-end;
        gap: 10px;
    }

    .ego-pr-full-error {
        display: none;
        margin: -2px 0 9px;
        color: #dc2626;
        font-size: 12.5px;
        font-weight: 850;
    }

    .ego-pr-full-error.show {
        display: block;
    }


    .ego-pr-full-modal .payx-files {
        display: grid;
        gap: 8px;
    }

    .ego-pr-full-modal .payx-file {
        display: grid;
        grid-template-columns: 34px 1fr auto;
        gap: 10px;
        align-items: center;
        padding: 10px;
        border-radius: 14px;
        border: 1px solid #e6edf6;
        background: linear-gradient(180deg, #fff, #f8fafc);
        color: #0f172a;
        text-decoration: none;
        transition: .18s ease;
    }

    .ego-pr-full-modal .payx-file:hover {
        border-color: #93c5fd;
        transform: translateY(-1px);
        color: #0f172a;
        box-shadow: 0 10px 22px rgba(14,165,233,.1);
    }

    .ego-pr-full-modal .payx-file-icon {
        width: 34px;
        height: 34px;
        border-radius: 11px;
        background: #e0f2fe;
        display: grid;
        place-items: center;
        color: #0284c7;
        font-weight: 950;
        font-size: 10px;
    }

    .ego-pr-full-modal .payx-file-name {
        font-size: 13px;
        font-weight: 850;
        line-height: 1.35;
        word-break: break-word;
    }

    .ego-pr-full-modal .payx-file-meta,
    .ego-pr-full-modal .payx-file-open {
        color: #64748b;
        font-size: 11.5px;
        font-weight: 750;
    }

    .ego-pr-full-modal .payx-empty {
        border: 1px dashed #cbd5e1;
        background: #f8fafc;
        color: #64748b;
        border-radius: 14px;
        padding: 13px;
        text-align: center;
        font-weight: 750;
        font-size: 12.5px;
    }

    @media (max-width: 768px) {
        .ego-pr-full-modal .payx-info-grid {
            grid-template-columns: 1fr 1fr;
        }
    }

    @media (max-width: 520px) {
        .ego-pr-full-modal .payx-info-grid {
            grid-template-columns: 1fr;
        }

        .ego-pr-full-body {
            padding: 12px;
        }
    }
</style>

<div class="ego-pr-full-backdrop" id="egoRejectFullModal" aria-hidden="true">
    <div class="ego-pr-full-modal" role="dialog" aria-modal="true" aria-labelledby="egoRejectFullTitle">
        <div class="ego-pr-full-head">
            <div>
                <h3 class="ego-pr-full-title" id="egoRejectFullTitle">Thông tin phiếu cần từ chối</h3>
                <div class="ego-pr-full-desc">Kiểm tra thông tin thanh toán, sau đó nhập lý do từ chối.</div>
            </div>
            <button type="button" class="ego-pr-full-close" data-ego-pr-full-close aria-label="Đóng">&times;</button>
        </div>

        <div class="ego-pr-full-body">
            <div id="egoRejectFullInfo">
                <div class="ego-pr-loading">Đang tải bảng thông tin thanh toán...</div>
            </div>

            <section class="payx-action">
                <h3 class="payx-action-title" id="egoRejectFullActionTitle">Quản lý tài chính xử lý</h3>
                <div class="payx-action-desc">Bắt buộc nhập lý do từ chối, không nhập sẽ không gửi được.</div>

                <div class="payx-action-grid">
                    <form id="egoRejectFullForm" method="POST" action="" class="payx-action-form">
                        @csrf
                        <h4>Từ chối phiếu</h4>
                        <textarea id="egoRejectFullNote" name="note" required minlength="2" maxlength="2000" placeholder="Lý do từ chối..."></textarea>
                        <div class="ego-pr-full-error" id="egoRejectFullError">Vui lòng nhập lý do từ chối trước khi xác nhận.</div>

                        <div class="ego-pr-full-actions">
                            <button type="button" class="payx-btn ego-pr-cancel" data-ego-pr-full-close>Hủy</button>
                            <button type="submit" class="payx-btn red" id="egoRejectFullSubmit" disabled>Từ chối</button>
                        </div>
                    </form>
                </div>
            </section>
        </div>
    </div>
</div>

<script>
(function(){
    function ready(fn){
        document.readyState !== 'loading' ? fn() : document.addEventListener('DOMContentLoaded', fn);
    }

    ready(function(){
        var modal = document.getElementById('egoRejectFullModal');
        var form = document.getElementById('egoRejectFullForm');
        var note = document.getElementById('egoRejectFullNote');
        var submit = document.getElementById('egoRejectFullSubmit');
        var error = document.getElementById('egoRejectFullError');
        var infoBox = document.getElementById('egoRejectFullInfo');
        var actionTitle = document.getElementById('egoRejectFullActionTitle');

        if(!modal || !form || !note || !submit || !infoBox) return;

        function clean(v){
            return (v || '').replace(/\s+/g, ' ').trim();
        }

        function esc(v){
            return (v || '').replace(/[&<>"']/g, function(s){
                return ({
                    '&':'&amp;',
                    '<':'&lt;',
                    '>':'&gt;',
                    '"':'&quot;',
                    "'":'&#039;'
                })[s];
            });
        }

        function refresh(){
            var ok = clean(note.value).length >= 2;
            submit.disabled = !ok;

            if(ok && error){
                error.classList.remove('show');
            }
        }

        function fallbackInfoFromRow(srcForm){
            var wrap = srcForm.closest('tr') || srcForm.closest('.m-card');
            var code = '-';
            var receiver = '-';
            var amount = '-';
            var status = '-';
            var created = '-';
            var company = '-';
            var creator = '-';

            if(wrap && wrap.tagName && wrap.tagName.toLowerCase() === 'tr'){
                var cells = wrap.querySelectorAll('td');
                code = clean(cells[1] ? cells[1].innerText : '-');
                receiver = clean(cells[2] ? cells[2].innerText : '-');
                amount = clean(cells[3] ? cells[3].innerText : '-');
                status = clean(cells[4] ? cells[4].innerText : '-');
                created = clean(cells[5] ? cells[5].innerText : '-');
                company = clean(cells[7] ? cells[7].innerText : '-');
                creator = clean(cells[8] ? cells[8].innerText : '-');
            } else if(wrap) {
                var codeEl = wrap.querySelector('.m-code');
                code = clean(codeEl ? codeEl.innerText : '-');

                wrap.querySelectorAll('.m-row').forEach(function(row){
                    var k = clean(row.querySelector('.m-k') ? row.querySelector('.m-k').innerText : '');
                    var v = clean(row.querySelector('.m-v') ? row.querySelector('.m-v').innerText : '');

                    if(k.indexOf('Người nhận') !== -1) receiver = v;
                    if(k.indexOf('Số tiền') !== -1) amount = v;
                    if(k.indexOf('Trạng thái') !== -1) status = v;
                    if(k.indexOf('Công ty') !== -1) company = v;
                    if(k.indexOf('Người tạo') !== -1) creator = v;
                });

                var dateEl = wrap.querySelector('.m-date');
                created = clean(dateEl ? dateEl.innerText : '-');
            }

            return '' +
                '<section class="payx-card">' +
                    '<div class="payx-card-head">' +
                        '<div>' +
                            '<h2 class="payx-card-title">Thông tin thanh toán</h2>' +
                            '<div class="payx-card-desc">Thông tin lấy nhanh từ danh sách phiếu.</div>' +
                        '</div>' +
                        '<span class="payx-chip">' + esc(status) + '</span>' +
                    '</div>' +
                    '<div class="payx-card-body">' +
                        '<div class="payx-info-grid">' +
                            '<div class="payx-info"><span class="payx-label">Mã phiếu</span><div class="payx-value">' + esc(code) + '</div></div>' +
                            '<div class="payx-info"><span class="payx-label">Người nhận</span><div class="payx-value">' + esc(receiver) + '</div></div>' +
                            '<div class="payx-info"><span class="payx-label">Số tiền</span><div class="payx-value money">' + esc(amount) + '</div></div>' +
                            '<div class="payx-info"><span class="payx-label">Ngày tạo</span><div class="payx-value">' + esc(created) + '</div></div>' +
                            '<div class="payx-info"><span class="payx-label">Công ty</span><div class="payx-value">' + esc(company) + '</div></div>' +
                            '<div class="payx-info"><span class="payx-label">Người tạo</span><div class="payx-value">' + esc(creator) + '</div></div>' +
                        '</div>' +
                    '</div>' +
                '</section>';
        }

        function findShowUrl(srcForm){
            var wrap = srcForm.closest('tr') || srcForm.closest('.m-card');

            if(wrap){
                var link = wrap.querySelector('a.code-link, a.m-code, a[href*="/payment-requests/"]');

                if(link && link.href){
                    return link.href;
                }
            }

            var action = srcForm.getAttribute('action') || '';
            var m = action.match(/\/payment-requests\/(\d+)\//);

            if(m && m[1]){
                return window.location.origin + '/payment-requests/' + m[1];
            }

            return '';
        }

                function extractPaymentInfo(html){
            var doc = new DOMParser().parseFromString(html, 'text/html');
            var cards = Array.prototype.slice.call(doc.querySelectorAll('.payx-card'));

            var selectedCards = cards.filter(function(card){
                var text = clean(card.innerText || '');

                return text.indexOf('Thông tin thanh toán') !== -1
                    || text.indexOf('Chứng từ đính kèm') !== -1;
            });

            if(!selectedCards.length){
                return '';
            }

            selectedCards.forEach(function(card){
                card.querySelectorAll('script, style').forEach(function(el){
                    el.remove();
                });

                card.classList.remove('payx-animate');
                card.removeAttribute('style');
            });

            return selectedCards.map(function(card){
                return card.outerHTML;
            }).join('');
        }

        function loadInfo(showUrl, srcForm){
            infoBox.innerHTML = '<div class="ego-pr-loading">Đang tải bảng thông tin thanh toán...</div>';

            if(!showUrl){
                infoBox.innerHTML = fallbackInfoFromRow(srcForm);
                return;
            }

            fetch(showUrl, {
                credentials: 'same-origin',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
            .then(function(res){
                if(!res.ok) throw new Error('Load failed');
                return res.text();
            })
            .then(function(html){
                var extracted = extractPaymentInfo(html);

                if(extracted){
                    infoBox.innerHTML = extracted;
                } else {
                    infoBox.innerHTML = fallbackInfoFromRow(srcForm);
                }
            })
            .catch(function(){
                infoBox.innerHTML = fallbackInfoFromRow(srcForm);
            });
        }

        function openModal(srcForm){
            var action = srcForm.getAttribute('action') || '';
            var isAcc = action.indexOf('acc-reject') !== -1;

            form.setAttribute('action', action);
            actionTitle.textContent = isAcc ? 'Kế toán xử lý' : 'Quản lý tài chính xử lý';

            note.value = '';
            refresh();

            modal.classList.add('show');
            modal.setAttribute('aria-hidden', 'false');

            loadInfo(findShowUrl(srcForm), srcForm);

            setTimeout(function(){
                note.focus();
            }, 120);
        }

        function closeModal(){
            modal.classList.remove('show');
            modal.setAttribute('aria-hidden', 'true');
            form.setAttribute('action', '');
            note.value = '';
            refresh();
        }

        function bindRejectForms(){
            document.querySelectorAll('form[action*="admin-reject"], form[action*="acc-reject"]').forEach(function(srcForm){
                if(srcForm.id === 'egoRejectFullForm') return;
                if(srcForm.dataset.egoRejectFullBound === '1') return;

                srcForm.dataset.egoRejectFullBound = '1';
                srcForm.onsubmit = null;
                srcForm.removeAttribute('onsubmit');

                srcForm.addEventListener('submit', function(e){
                    e.preventDefault();
                    openModal(srcForm);
                });
            });
        }

        note.addEventListener('input', refresh);

        form.addEventListener('submit', function(e){
            if(clean(note.value).length < 2){
                e.preventDefault();

                if(error){
                    error.classList.add('show');
                }

                submit.disabled = true;
                note.focus();
            }
        });

        document.querySelectorAll('[data-ego-pr-full-close]').forEach(function(btn){
            btn.addEventListener('click', closeModal);
        });

        modal.addEventListener('click', function(e){
            if(e.target === modal){
                closeModal();
            }
        });

        document.addEventListener('keydown', function(e){
            if(e.key === 'Escape' && modal.classList.contains('show')){
                closeModal();
            }
        });

        bindRejectForms();
        setTimeout(bindRejectForms, 300);
        setTimeout(bindRejectForms, 900);
    });
})();
</script>
{{-- EGO_PAYMENT_REJECT_MODAL_END --}}
{{-- EGO_PAYMENT_INDEX_CLEAN_FIX_END --}}

@endsection
@includeIf('payment-requests._buibichthao_actions')


{{-- EGO_PR_ACTIONS_INLINE_START --}}
<style>
    /* Ép cột Thao tác ĐNTT nằm cùng 1 hàng */
    table th:last-child,
    table td:last-child {
        white-space: nowrap;
    }

    table td:last-child {
        min-width: 205px;
    }

    table td:last-child > div,
    table td:last-child .btn-group,
    table td:last-child .action-buttons,
    table td:last-child .actions {
        display: inline-flex !important;
        flex-direction: row !important;
        flex-wrap: nowrap !important;
        align-items: center !important;
        gap: 6px !important;
    }

    table td:last-child a,
    table td:last-child button,
    table td:last-child form {
        display: inline-flex !important;
        vertical-align: middle !important;
        margin-top: 0 !important;
        margin-bottom: 0 !important;
        white-space: nowrap !important;
    }

    table td:last-child form {
        margin-left: 0 !important;
        margin-right: 0 !important;
    }
</style>

<script>
(function () {
    function isActionCell(cell) {
        if (!cell) return false;

        var text = (cell.innerText || '').toLowerCase();

        return text.indexOf('pdf') !== -1
            || text.indexOf('sửa') !== -1
            || text.indexOf('xóa') !== -1
            || cell.querySelector('a[href*="/pdf"], a[href*="pdf"], a[href*="/edit"], form, button');
    }

    function normalizeActionCell(cell) {
        if (!cell || !isActionCell(cell)) return;

        if (cell.dataset.egoPrActionsInline === '1') return;
        cell.dataset.egoPrActionsInline = '1';

        var controls = Array.prototype.slice.call(cell.children).filter(function (el) {
            var tag = el.tagName ? el.tagName.toLowerCase() : '';
            return ['a', 'button', 'form', 'div', 'span'].indexOf(tag) !== -1;
        });

        if (!controls.length) return;

        var wrap = document.createElement('div');
        wrap.className = 'ego-pr-actions-inline';
        wrap.style.display = 'inline-flex';
        wrap.style.flexDirection = 'row';
        wrap.style.flexWrap = 'nowrap';
        wrap.style.alignItems = 'center';
        wrap.style.gap = '6px';

        controls.forEach(function (el) {
            wrap.appendChild(el);
        });

        cell.appendChild(wrap);

        /*
         * Xóa nút duplicate cùng loại trong cùng 1 dòng:
         * giữ PDF đầu tiên, Sửa đầu tiên, Xóa đầu tiên.
         */
        var seen = {};

        Array.prototype.slice.call(wrap.querySelectorAll('a, button')).forEach(function (btn) {
            var text = (btn.innerText || btn.textContent || '').trim().toLowerCase();
            var key = null;

            if (text.indexOf('pdf') !== -1) key = 'pdf';
            else if (text === 'sửa' || text.indexOf('sửa') !== -1) key = 'edit';
            else if (text === 'xóa' || text.indexOf('xóa') !== -1) key = 'delete';

            if (!key) return;

            if (seen[key]) {
                var holder = btn.closest('form') || btn.closest('a') || btn;
                holder.remove();
                return;
            }

            seen[key] = true;
        });
    }

    function run() {
        document.querySelectorAll('table tbody tr').forEach(function (row) {
            var cells = row.querySelectorAll('td');
            if (!cells.length) return;

            normalizeActionCell(cells[cells.length - 1]);
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        run();
        setTimeout(run, 100);
        setTimeout(run, 400);
    });
})();
</script>
{{-- EGO_PR_ACTIONS_INLINE_END --}}

{{-- EGO_HR_REFUND_SELECT_FIX_START --}}
<script>
(function(){
    function ready(fn){
        document.readyState !== 'loading' ? fn() : document.addEventListener('DOMContentLoaded', fn);
    }

    ready(function(){
        document.querySelectorAll('form[action*="payment-requests"]').forEach(function(form){
            form.addEventListener('submit', function(){
                var select = form.querySelector('select[name="doc_type"]');
                if(!select) return;

                var text = (select.options[select.selectedIndex] ? select.options[select.selectedIndex].text : '').toLowerCase();
                if(text.indexOf('hoàn tiền') !== -1 || text.indexOf('hoan tien') !== -1){
                    select.value = 'refund_request';
                }
            });
        });
    });
})();
</script>
{{-- EGO_HR_REFUND_SELECT_FIX_END --}}
@if($errors->any() && (old('receiver_name') || old('amount') || old('bank_info') || old('payment_content')))
<script>
document.addEventListener('DOMContentLoaded', function () {
    var modalEl = document.getElementById('createPRModal');
    if (modalEl && window.bootstrap && bootstrap.Modal) {
        bootstrap.Modal.getOrCreateInstance(modalEl).show();
    }
});
</script>
@endif


{{-- EGO_PR_ASYNC_CREATE_FIX_START --}}
<script>
(function () {
    function ready(callback) {
        if (document.readyState === 'loading') {
            document.addEventListener(
                'DOMContentLoaded',
                callback
            );
        } else {
            callback();
        }
    }

    function plainText(html) {
        var holder = document.createElement('div');

        holder.innerHTML = String(html || '');

        return String(
            holder.textContent ||
            holder.innerText ||
            ''
        )
            .replace(/\u00a0/g, ' ')
            .replace(/\s+/g, ' ')
            .trim();
    }

    function firstError(payload) {
        if (payload && payload.errors) {
            var keys = Object.keys(payload.errors);

            if (keys.length) {
                var value = payload.errors[keys[0]];

                if (
                    Array.isArray(value) &&
                    value.length
                ) {
                    return value[0];
                }

                if (typeof value === 'string') {
                    return value;
                }
            }
        }

        return payload && payload.message
            ? payload.message
            : 'Máy chủ không xử lý được yêu cầu.';
    }

    async function fetchWithTimeout(
        url,
        options,
        timeoutMs
    ) {
        var controller = new AbortController();

        var timer = setTimeout(function () {
            controller.abort();
        }, timeoutMs);

        options = options || {};
        options.signal = controller.signal;

        try {
            return await fetch(url, options);
        } finally {
            clearTimeout(timer);
        }
    }

    async function readJson(response) {
        var text = await response.text();

        try {
            return JSON.parse(text);
        } catch (error) {
            return {
                ok: false,
                message: text
                    ? 'Máy chủ trả về nội dung không hợp lệ.'
                    : 'Máy chủ không trả về dữ liệu.',
                raw: text
            };
        }
    }

    ready(function () {
        var form = document.getElementById(
            'egoPaymentRequestCreateForm'
        );

        if (
            !form ||
            form.dataset.egoAsyncBound === '1'
        ) {
            return;
        }

        form.dataset.egoAsyncBound = '1';

        form.addEventListener(
            'submit',
            async function (event) {
                event.preventDefault();
                event.stopImmediatePropagation();

                if (
                    window.CKEDITOR &&
                    CKEDITOR.instances &&
                    CKEDITOR.instances.reason_editor
                ) {
                    CKEDITOR.instances.reason_editor
                        .updateElement();
                }

                var reason = form.querySelector(
                    '[name="reason"]'
                );

                if (
                    !reason ||
                    !plainText(reason.value)
                ) {
                    alert(
                        'Vui lòng nhập lý do thanh toán.'
                    );

                    if (
                        window.CKEDITOR &&
                        CKEDITOR.instances &&
                        CKEDITOR.instances.reason_editor
                    ) {
                        CKEDITOR.instances.reason_editor
                            .focus();
                    } else if (reason) {
                        reason.focus();
                    }

                    return;
                }

                if (!form.checkValidity()) {
                    form.reportValidity();
                    return;
                }

                var button = document.getElementById(
                    'egoPaymentRequestCreateSubmit'
                ) || form.querySelector(
                    'button[type="submit"]'
                );

                var originalButtonHtml = button
                    ? button.innerHTML
                    : '';

                if (button) {
                    button.disabled = true;
                    button.innerHTML =
                        '<span class="' +
                        'spinner-border ' +
                        'spinner-border-sm me-1' +
                        '"></span> Đang tạo phiếu...';
                }

                try {
                    /*
                     * Chỉ gửi dữ liệu chữ trước.
                     * Tuyệt đối chưa gửi PDF/file ở bước này.
                     */
                    var allData = new FormData(form);
                    var metadata = new URLSearchParams();

                    allData.forEach(function (
                        value,
                        key
                    ) {
                        if (!(value instanceof File)) {
                            metadata.append(
                                key,
                                String(value)
                            );
                        }
                    });

                    var createResponse =
                        await fetchWithTimeout(
                            form.action,
                            {
                                method: 'POST',
                                credentials: 'same-origin',
                                headers: {
                                    'Accept':
                                        'application/json',
                                    'X-Requested-With':
                                        'XMLHttpRequest',
                                    'Content-Type':
                                        'application/' +
                                        'x-www-form-urlencoded;' +
                                        'charset=UTF-8'
                                },
                                body: metadata.toString()
                            },
                            45000
                        );

                    var createPayload =
                        await readJson(
                            createResponse
                        );

                    if (
                        !createResponse.ok ||
                        !createPayload.ok ||
                        !createPayload.id
                    ) {
                        throw new Error(
                            firstError(createPayload)
                        );
                    }

                    var paymentRequestId =
                        createPayload.id;

                    var fileInput =
                        form.querySelector(
                            'input[type="file"]' +
                            '[name="attachments[]"]'
                        );

                    var files = fileInput
                        ? Array.from(
                            fileInput.files || []
                        )
                        : [];

                    var failedFiles = [];

                    var csrf = form.querySelector(
                        'input[name="_token"]'
                    );

                    var baseUrl =
                        window.location.origin +
                        '/payment-requests';

                    /*
                     * Tải từng file riêng biệt.
                     * Một file lỗi không làm mất phiếu.
                     */
                    for (
                        var index = 0;
                        index < files.length;
                        index++
                    ) {
                        var file = files[index];

                        if (button) {
                            button.innerHTML =
                                '<span class="' +
                                'spinner-border ' +
                                'spinner-border-sm me-1' +
                                '"></span> Tải file ' +
                                (index + 1) +
                                '/' +
                                files.length +
                                '...';
                        }

                        var uploadData =
                            new FormData();

                        if (csrf) {
                            uploadData.append(
                                '_token',
                                csrf.value
                            );
                        }

                        uploadData.append(
                            'attachments[]',
                            file,
                            file.name
                        );

                        try {
                            var uploadResponse =
                                await fetchWithTimeout(
                                    baseUrl +
                                    '/' +
                                    paymentRequestId +
                                    '/attachments',
                                    {
                                        method: 'POST',
                                        credentials:
                                            'same-origin',
                                        headers: {
                                            'Accept':
                                                'application/json',
                                            'X-Requested-With':
                                                'XMLHttpRequest'
                                        },
                                        body: uploadData
                                    },
                                    120000
                                );

                            var uploadPayload =
                                await readJson(
                                    uploadResponse
                                );

                            if (
                                !uploadResponse.ok ||
                                uploadPayload.ok === false
                            ) {
                                failedFiles.push(
                                    file.name +
                                    (
                                        uploadPayload &&
                                        uploadPayload.message
                                            ? ' — ' + uploadPayload.message
                                            : ''
                                    )
                                );
                            }
                        } catch (uploadError) {
                            failedFiles.push(
                                file.name +
                                (
                                    uploadError &&
                                    uploadError.message
                                        ? ' — ' + uploadError.message
                                        : ''
                                )
                            );
                        }
                    }

                    if (failedFiles.length) {
                        alert(
                            'Phiếu đã được tạo thành công.' +
                            '\n\nChưa tải được file: ' +
                            failedFiles.join(', ') +
                            '\n\nBạn có thể mở phiếu ' +
                            'và tải lại chứng từ.'
                        );
                    }

                    window.location.href =
                        createPayload.redirect_url ||
                        baseUrl;
                } catch (error) {
                    var message =
                        error &&
                        error.name === 'AbortError'
                            ? 'Máy chủ phản hồi quá lâu.'
                            : (
                                error.message ||
                                'Mất kết nối khi tạo phiếu.'
                            );

                    alert(message);

                    if (button) {
                        button.disabled = false;
                        button.innerHTML =
                            originalButtonHtml;
                    }
                }
            },
            true
        );

        window.addEventListener(
            'pageshow',
            function () {
                var button =
                    document.getElementById(
                        'egoPaymentRequestCreateSubmit'
                    );

                if (button) {
                    button.disabled = false;
                }
            }
        );
    });
})();
</script>
{{-- EGO_PR_ASYNC_CREATE_FIX_END --}}
