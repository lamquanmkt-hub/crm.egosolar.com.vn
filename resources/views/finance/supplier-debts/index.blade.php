@php
    // Admin/Giám đốc hoặc người được cấp permission `payment_requests.override_locked`.
    $financeCompletedEditor = auth()->check() && auth()->user()->canOverrideLockedFinanceRecords();
@endphp

@extends('layouts.app')

@section('title', 'Công nợ nhà cung cấp')

@section('content')
@php
    $money = fn($value) => number_format((float) ($value ?? 0), 0, ',', '.') . ' đ';
    $moneyInput = fn($value) => rtrim(rtrim(number_format((float) ($value ?? 0), 2, '.', ''), '0'), '.');
    $debts = $debts ?? collect();
    $summary = $summary ?? [];
    $period = $period ?? 'all';
    $month = $month ?? now()->format('Y-m');
    $keyword = $keyword ?? '';
    $status = $status ?? '';
    $companyOptions = $companyOptions ?? ['Công ty TNHH TMKT Quốc Tế EGO'];

    $roundStatusText = [
        'planned' => 'Dự kiến',
        'requested' => 'Đã lập ĐNTT',
        'submitted' => 'Đã gửi',
        'pending' => 'Đang chờ',
        'admin_approved' => 'Admin duyệt',
        'admin_rejected' => 'Admin từ chối',
        'accounting_approved' => 'Đã thanh toán',
        'accounting_rejected' => 'Kế toán từ chối',
        'paid' => 'Đã thanh toán',
        'cancelled' => 'Đã hủy',
        'draft' => 'Nháp',
    ];
@endphp

<style>
    .supplier-debt-page {
        --sd-text: #0f172a;
        --sd-muted: #64748b;
        --sd-border: #e5edf7;
        --sd-soft: #f8fbff;
        --sd-blue: #2563eb;
        --sd-green: #059669;
        --sd-amber: #f59e0b;
        --sd-red: #e11d48;
        padding: 24px 28px 36px;
        background: linear-gradient(180deg, #f7fbff 0%, #f8fafc 42%, #ffffff 100%);
        min-height: 100%;
        color: var(--sd-text);
    }

    .sd-top {
        display: grid;
        grid-template-columns: 1fr 2.4fr;
        gap: 18px;
        align-items: start;
        margin-bottom: 18px;
    }

    .sd-title h1 {
        margin: 0 0 8px;
        font-size: 26px;
        font-weight: 950;
        letter-spacing: -0.035em;
    }

    .sd-title p {
        margin: 0;
        color: var(--sd-muted);
        font-size: 14px;
    }

    .sd-filter {
        display: grid;
        grid-template-columns: 160px 160px 1fr 185px 120px;
        gap: 10px;
    }

    .sd-input,
    .sd-select {
        height: 46px;
        width: 100%;
        border: 1px solid var(--sd-border);
        border-radius: 14px;
        padding: 0 14px;
        background: #fff;
        color: #1e293b;
        outline: none;
        box-shadow: 0 10px 24px rgba(15, 23, 42, .04);
        font-size: 14px;
    }

    textarea.sd-input {
        height: auto;
        min-height: 76px;
        padding-top: 12px;
    }

    .sd-btn {
        height: 46px;
        border: 0;
        border-radius: 14px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        padding: 0 16px;
        font-weight: 900;
        text-decoration: none;
        cursor: pointer;
        white-space: nowrap;
    }

    .sd-btn-primary {
        color: #fff;
        background: linear-gradient(135deg, #2563eb, #1d4ed8);
        box-shadow: 0 16px 34px rgba(37, 99, 235, .22);
    }

    .sd-btn-light {
        color: #1d4ed8;
        background: #fff;
        border: 1px solid var(--sd-border);
    }

    .sd-btn-danger {
        color: #be123c;
        background: #fff1f2;
        border: 1px solid #fecdd3;
    }

    .sd-kpis {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: 16px;
        margin: 18px 0;
    }

    .sd-kpi {
        background: #fff;
        border: 1px solid var(--sd-border);
        border-radius: 22px;
        padding: 20px;
        display: flex;
        gap: 15px;
        align-items: center;
        box-shadow: 0 20px 48px rgba(15,23,42,.06);
    }

    .sd-kpi-icon {
        width: 56px;
        height: 56px;
        border-radius: 18px;
        display: grid;
        place-items: center;
        font-size: 24px;
        flex: 0 0 auto;
    }

    .sd-kpi.blue .sd-kpi-icon { background: #eaf2ff; color: #2563eb; }
    .sd-kpi.green .sd-kpi-icon { background: #dcfce7; color: #059669; }
    .sd-kpi.amber .sd-kpi-icon { background: #fef3c7; color: #f59e0b; }
    .sd-kpi.red .sd-kpi-icon { background: #ffe4e6; color: #e11d48; }

    .sd-kpi-label {
        color: #475569;
        font-size: 13px;
        font-weight: 900;
        margin-bottom: 6px;
    }

    .sd-kpi-value {
        font-size: 22px;
        font-weight: 950;
        letter-spacing: -0.035em;
    }

    .sd-kpi small {
        display: block;
        color: var(--sd-muted);
        margin-top: 5px;
    }

    .sd-panel {
        background: #fff;
        border: 1px solid var(--sd-border);
        border-radius: 22px;
        box-shadow: 0 24px 56px rgba(15,23,42,.07);
        overflow: hidden;
    }

    .sd-panel-head {
        padding: 20px 22px;
        border-bottom: 1px solid var(--sd-border);
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 14px;
    }

    .sd-panel-head h2 {
        margin: 0 0 4px;
        font-size: 20px;
        font-weight: 950;
        letter-spacing: -0.025em;
    }

    .sd-panel-head p {
        margin: 0;
        color: var(--sd-muted);
        font-size: 13px;
    }

    .sd-create {
        margin-bottom: 16px;
        padding: 14px 16px;
        border: 1px dashed #bfdbfe;
        border-radius: 18px;
        background: #fff;
    }

    .sd-create summary {
        cursor: pointer;
        color: #1d4ed8;
        font-weight: 950;
    }

    .sd-grid {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: 11px;
        margin-top: 14px;
    }

    .span-2 {
        grid-column: span 2;
    }

    .sd-table-wrap {
        overflow-x: auto;
    }

    .sd-table {
        width: 100%;
        border-collapse: collapse;
        min-width: 1120px;
    }

    .sd-table th {
        padding: 15px 18px;
        text-align: left;
        background: #fbfdff;
        color: #475569;
        font-size: 12px;
        font-weight: 950;
        border-bottom: 1px solid var(--sd-border);
        white-space: nowrap;
    }

    .sd-table td {
        padding: 15px 18px;
        border-bottom: 1px solid var(--sd-border);
        font-size: 14px;
        vertical-align: middle;
    }

    .sd-table tr:hover td {
        background: #fbfdff;
    }

    .sd-supplier {
        display: flex;
        align-items: center;
        gap: 13px;
        min-width: 260px;
    }

    .sd-avatar {
        width: 38px;
        height: 38px;
        border-radius: 13px;
        display: grid;
        place-items: center;
        color: #2563eb;
        background: #eaf2ff;
        font-weight: 950;
        flex: 0 0 auto;
    }

    .sd-name {
        font-weight: 950;
        color: #0f172a;
        margin-bottom: 3px;
    }

    .sd-sub {
        color: #64748b;
        font-size: 12px;
        line-height: 1.45;
    }

    .sd-money {
        font-weight: 950;
        white-space: nowrap;
    }

    .sd-money.green { color: var(--sd-green); }
    .sd-money.amber { color: var(--sd-amber); }
    .sd-money.red { color: var(--sd-red); }

    .sd-badge {
        display: inline-flex;
        align-items: center;
        height: 28px;
        padding: 0 10px;
        border-radius: 999px;
        font-size: 12px;
        font-weight: 900;
        white-space: nowrap;
    }

    .sd-badge.success { background: #dcfce7; color: #047857; }
    .sd-badge.warning { background: #fef3c7; color: #b45309; }
    .sd-badge.danger { background: #ffe4e6; color: #be123c; }
    .sd-badge.neutral { background: #f1f5f9; color: #475569; }
    .sd-badge.info { background: #eaf2ff; color: #1d4ed8; }

    .sd-row-actions {
        display: flex;
        justify-content: flex-end;
        align-items: center;
        flex-wrap: wrap;
        gap: 8px;
    }

    .sd-icon-btn {
        width: 36px;
        height: 36px;
        border-radius: 11px;
        border: 1px solid #dbe7f5;
        background: #fff;
        color: #2563eb;
        display: inline-grid;
        place-items: center;
        cursor: pointer;
        text-decoration: none;
        font-weight: 950;
    }

    .sd-icon-btn.red {
        color: #be123c;
        border-color: #fecdd3;
        background: #fff1f2;
    }

    .sd-icon-btn:disabled,
    .sd-btn:disabled {
        opacity: .55;
        cursor: not-allowed;
    }

    .sd-detail-row {
        display: none;
    }

    .sd-detail-row.show {
        display: table-row;
    }

    .sd-detail-box {
        padding: 16px;
        background: #f8fbff;
        border: 1px solid var(--sd-border);
        border-radius: 18px;
    }

    .sd-detail-grid {
        display: grid;
        grid-template-columns: .9fr 1.35fr;
        gap: 16px;
    }

    .sd-mini-card {
        background: #fff;
        border: 1px solid var(--sd-border);
        border-radius: 16px;
        padding: 15px;
    }

    .sd-mini-title {
        font-weight: 950;
        margin-bottom: 10px;
    }

    .sd-mini-table {
        width: 100%;
        border-collapse: collapse;
    }

    .sd-mini-table th,
    .sd-mini-table td {
        padding: 9px 10px;
        border-bottom: 1px solid #eef2f7;
        font-size: 13px;
        vertical-align: top;
    }

    .sd-mini-table th {
        color: #475569;
        font-weight: 950;
        text-align: left;
    }

    .sd-round-form {
        display: grid;
        grid-template-columns: 80px 150px 145px 140px minmax(150px, 1fr) minmax(150px, 1fr) auto;
        gap: 8px;
        margin-top: 12px;
        align-items: start;
    }

    .sd-round-form input,
    .sd-round-form select,
    .sd-round-form textarea,
    .sd-round-edit-form input,
    .sd-round-edit-form select,
    .sd-round-edit-form textarea {
        height: 40px;
        border: 1px solid var(--sd-border);
        border-radius: 11px;
        padding: 0 10px;
        min-width: 0;
        background: #fff;
    }

    .sd-round-form textarea,
    .sd-round-edit-form textarea {
        height: 40px;
        padding-top: 10px;
        resize: vertical;
    }

    .sd-round-actions {
        display: flex;
        gap: 6px;
        justify-content: flex-end;
        align-items: center;
        flex-wrap: wrap;
    }

    .sd-round-edit-row {
        display: none;
    }

    .sd-round-edit-row.show {
        display: table-row;
    }

    .sd-round-edit-box {
        padding: 12px;
        border: 1px dashed #bfdbfe;
        border-radius: 14px;
        background: #f8fbff;
    }

    .sd-round-edit-form {
        display: grid;
        grid-template-columns: 70px 82px 145px 135px 130px minmax(130px, 1fr) minmax(125px, 1fr) auto auto;
        gap: 8px;
        align-items: start;
    }


    .sd-bulk-trigger {
        display: flex;
        justify-content: flex-end;
        margin-top: 10px;
    }

    .sd-bulk-split[hidden] {
        display: none !important;
    }

    .sd-bulk-split {
        margin-top: 14px;
        padding: 12px;
        border: 1px dashed #bfdbfe;
        border-radius: 16px;
        background: #f8fbff;
    }

    .sd-split-head {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 10px;
        flex-wrap: wrap;
        margin-bottom: 10px;
    }

    .sd-split-tools {
        display: flex;
        gap: 8px;
        align-items: center;
        flex-wrap: wrap;
    }

    .sd-split-tools input {
        width: 74px;
        height: 38px;
        border: 1px solid var(--sd-border);
        border-radius: 11px;
        padding: 0 10px;
        background: #fff;
    }

    .sd-split-rows {
        display: grid;
        gap: 8px;
    }

    .sd-split-row {
        display: grid;
        grid-template-columns: 72px 90px 150px 145px 132px minmax(150px, 1fr) 38px;
        gap: 8px;
        align-items: start;
    }

    .sd-split-row input,
    .sd-split-row select,
    .sd-split-row textarea {
        height: 40px;
        border: 1px solid var(--sd-border);
        border-radius: 11px;
        padding: 0 10px;
        min-width: 0;
        background: #fff;
    }

    .sd-split-row textarea {
        padding-top: 10px;
        resize: vertical;
    }

    .sd-split-total {
        margin-top: 10px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 10px;
        flex-wrap: wrap;
        color: #475569;
        font-size: 13px;
        font-weight: 800;
    }

    .sd-split-total b {
        color: #0f172a;
    }

    .sd-edit-grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 9px;
    }

    .sd-empty {
        padding: 44px 20px;
        text-align: center;
        color: #64748b;
        font-weight: 800;
    }

    .sd-alert {
        margin-bottom: 14px;
        padding: 12px 14px;
        border-radius: 14px;
        font-weight: 800;
    }

    .sd-alert.ok {
        background: #dcfce7;
        color: #047857;
    }

    .sd-alert.err {
        background: #ffe4e6;
        color: #be123c;
    }

    @media (max-width: 1300px) {
        .sd-top { grid-template-columns: 1fr; }
        .sd-filter { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        .sd-kpis { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        .sd-detail-grid { grid-template-columns: 1fr; }
    }

    @media (max-width: 768px) {
        .supplier-debt-page { padding: 16px; }
        .sd-filter,
        .sd-kpis,
        .sd-grid,
        .sd-round-form,
        .sd-round-edit-form,
        .sd-split-row,
        .sd-edit-grid {
            grid-template-columns: 1fr;
        }
        .span-2 { grid-column: span 1; }
    }


    .sd-round-form {
        grid-template-columns: 68px 74px minmax(110px, 1fr) 130px 118px minmax(120px, 1fr) minmax(100px, 1fr) auto !important;
    }

    .sd-bulk-split .sd-split-tools [data-split-count],
    .sd-bulk-split .sd-split-tools [data-split-equal] {
        display: none !important;
    }

    .sd-linked-partial-card {
        margin-top: 7px;
        padding: 8px 9px;
        border: 1px solid #fed7aa;
        border-radius: 12px;
        background: linear-gradient(180deg, #fff7ed, #fff);
        color: #9a3412;
        font-size: 11px;
        font-weight: 800;
        line-height: 1.45;
        width: max-content;
        max-width: 260px;
        box-shadow: 0 8px 18px rgba(251, 146, 60, .10);
    }

    .sd-linked-partial-card .line {
        display: flex;
        justify-content: space-between;
        gap: 12px;
        min-width: 198px;
    }

    .sd-linked-partial-card b {
        color: #be123c;
    }

    .sd-linked-partial-card .hint {
        margin-top: 5px;
        padding-top: 5px;
        border-top: 1px dashed #fdba74;
        color: #64748b;
        font-size: 10px;
        font-weight: 700;
    }

    .sd-linked-partial-card form {
        margin: 7px 0 0;
    }

    .sd-linked-partial-card button {
        height: 31px !important;
        padding: 0 10px !important;
        border-color: #fed7aa !important;
        color: #c2410c !important;
        background: #fff !important;
        font-size: 11px !important;
        font-weight: 900 !important;
    }

    @media (max-width: 1000px) {
        .sd-round-form { grid-template-columns: 1fr !important; }
    }

</style>

<div class="supplier-debt-page">
    @if(session('success'))
        <div class="sd-alert ok">{{ session('success') }}</div>
    @endif

    @if($errors->any())
        <div class="sd-alert err">
            @foreach($errors->all() as $error)
                <div>{{ $error }}</div>
            @endforeach
        </div>
    @endif

    <div class="sd-top">
        <div class="sd-title">
            <h1>Công nợ nhà cung cấp</h1>
            <p>Bảng gọn, dễ rà soát. Menu và topbar giữ nguyên theo layout hiện tại.</p>
        </div>

        <form method="GET" action="{{ route('finance.supplier-debts.index') }}" class="sd-filter">
            <select name="period" class="sd-select" onchange="toggleDebtMonth(this.value)">
                <option value="all" {{ $period === 'all' ? 'selected' : '' }}>Toàn thời gian</option>
                <option value="month" {{ $period === 'month' ? 'selected' : '' }}>Theo tháng</option>
            </select>

            <input id="debtMonthFilter" type="month" name="month" class="sd-input" value="{{ $month }}">

            <input type="text" name="keyword" class="sd-input" value="{{ $keyword }}" placeholder="Tìm NCC, chứng từ, ghi chú...">

            <select name="status" class="sd-select">
                <option value="" {{ $status === '' ? 'selected' : '' }}>Tất cả trạng thái</option>
                <option value="unpaid" {{ $status === 'unpaid' ? 'selected' : '' }}>Chưa thanh toán</option>
                <option value="partial" {{ $status === 'partial' ? 'selected' : '' }}>Đang thanh toán</option>
                <option value="paid" {{ $status === 'paid' ? 'selected' : '' }}>Đã thanh toán</option>
            </select>

            <button class="sd-btn sd-btn-primary" type="submit">Lọc</button>
        </form>
    </div>

    <div class="sd-kpis">
        <div class="sd-kpi blue">
            <div class="sd-kpi-icon">▣</div>
            <div>
                <div class="sd-kpi-label">Tổng phải trả</div>
                <div class="sd-kpi-value">{{ $money($summary['total_amount'] ?? 0) }}</div>
                <small>{{ $summary['debt_count'] ?? 0 }} công nợ</small>
            </div>
        </div>

        <div class="sd-kpi green">
            <div class="sd-kpi-icon">✓</div>
            <div>
                <div class="sd-kpi-label">Đã thanh toán</div>
                <div class="sd-kpi-value" style="color:#059669">{{ $money($summary['paid_amount'] ?? 0) }}</div>
                <small>Đã duyệt / đã chi</small>
            </div>
        </div>

        <div class="sd-kpi amber">
            <div class="sd-kpi-icon">⌛</div>
            <div>
                <div class="sd-kpi-label">Đang chờ thanh toán</div>
                <div class="sd-kpi-value" style="color:#f59e0b">{{ $money($summary['pending_payment_amount'] ?? 0) }}</div>
                <small>{{ $summary['payment_round_count'] ?? 0 }} đợt</small>
            </div>
        </div>

        <div class="sd-kpi red">
            <div class="sd-kpi-icon">!</div>
            <div>
                <div class="sd-kpi-label">Còn phải trả</div>
                <div class="sd-kpi-value" style="color:#e11d48">{{ $money($summary['remain_amount'] ?? 0) }}</div>
                <small>{{ $summary['supplier_count'] ?? 0 }} nhà cung cấp</small>
            </div>
        </div>
    </div>

    <details class="sd-create">
        <summary>+ Thêm công nợ nhà cung cấp</summary>

        <form method="POST" action="{{ route('finance.supplier-debts.store') }}" enctype="multipart/form-data" class="sd-grid">
            @csrf

            <input class="sd-input" name="supplier_name" placeholder="Tên nhà cung cấp" required>

            <select class="sd-select" name="company_name" required>
                @foreach($companyOptions as $company)
                    <option value="{{ $company }}">{{ $company }}</option>
                @endforeach
            </select>

            <input class="sd-input" name="document_no" placeholder="Số chứng từ">
            <input class="sd-input" name="document_date" type="date" title="Ngày chứng từ">
            <input class="sd-input" name="debt_month" type="month" value="{{ $month }}" required>
            <input class="sd-input" name="total_amount" type="text" inputmode="decimal" data-money-input placeholder="Tổng tiền" required>
<textarea class="sd-input span-2" name="bank_info" placeholder="Thông tin ngân hàng"></textarea>
            <textarea class="sd-input span-2" name="note" placeholder="Ghi chú"></textarea>

            <button class="sd-btn sd-btn-primary" type="submit">Lưu công nợ</button>
        </form>
    </details>

    <div class="sd-panel">
        <div class="sd-panel-head">
            <div>
                <h2>Danh sách công nợ</h2>
                <p>Mặc định là toàn thời gian. Bấm mắt để xem chi tiết, bấm bút để sửa, bấm × để xóa.</p>
            </div>

            <a class="sd-btn sd-btn-light" href="{{ route('finance.supplier-debts.index') }}">Reset lọc</a>
        </div>

        <div class="sd-table-wrap">
            <table class="sd-table">
                <thead>
                    <tr>
                        <th>Nhà cung cấp</th>
                        <th>Tổng phải trả</th>
                        <th>Đã thanh toán</th>
                        <th>Đang chờ</th>
                        <th>Còn phải trả</th>
                        <th>Trạng thái</th>
                        <th style="text-align:right">Hành động</th>
                    </tr>
                </thead>

                <tbody>
                    @forelse($debts as $item)
                        @php
                            $debtHasLinkedPaymentRequest = collect($item->payment_rounds ?? [])->contains(function ($round) { return !empty($round->payment_request_id); });
                        @endphp

                        <tr>
                            <td>
                                <div class="sd-supplier">
                                    <div class="sd-avatar">{{ mb_substr($item->supplier_name ?? 'NCC', 0, 2, 'UTF-8') }}</div>
                                    <div>
                                        <div class="sd-name">{{ $item->supplier_name }}</div>
                                        <div class="sd-sub">
                                            {{ $item->document_no ?: 'Chưa có chứng từ' }}
                                            @if(!empty($item->company_name)) · {{ $item->company_name }} @endif
                                            @if(!empty($item->document_date)) · {{ date('d/m/Y', strtotime($item->document_date)) }} @endif
                                        </div>
                                    </div>
                                </div>
                            </td>

                            <td><span class="sd-money">{{ $money($item->total_amount ?? 0) }}</span></td>
                            <td><span class="sd-money green">{{ $money($item->paid_amount ?? 0) }}</span></td>
                            <td><span class="sd-money amber">{{ $money($item->pending_payment_amount ?? 0) }}</span></td>
                            <td><span class="sd-money red">{{ $money($item->remain_amount ?? 0) }}</span></td>
                            <td><span class="sd-badge {{ $item->status_class ?? 'neutral' }}">{{ $item->status_text ?? 'Chưa thanh toán' }}</span></td>
                            <td>
                                <div class="sd-row-actions">
                                    <button type="button" class="sd-icon-btn" onclick="toggleSupplierDebtDetail({{ $item->id }})" title="Xem chi tiết">&#128065;</button>
                                    <button type="button" class="sd-icon-btn" onclick="openSupplierDebtEdit({{ $item->id }})" title="Sửa công nợ">&#9998;</button>

                                    <form method="POST" action="{{ route('finance.supplier-debts.destroy', $item->id) }}" onsubmit="return confirm('Xóa công nợ này?')" style="margin:0">
                                        @csrf
                                        @method('DELETE')
                                        <button class="sd-icon-btn red" type="submit" title="{{ ($debtHasLinkedPaymentRequest && !$financeCompletedEditor) ? 'Đã có ĐNTT, không xóa trực tiếp' : 'Xóa công nợ' }}" {{ ($debtHasLinkedPaymentRequest && !$financeCompletedEditor) ? 'disabled' : '' }}>&times;</button>
                                    </form>
                                </div>
                            </td>
                        </tr>

                        <tr id="sd-detail-{{ $item->id }}" class="sd-detail-row">
                            <td colspan="7">
                                <div class="sd-detail-box">
                                    <div class="sd-detail-grid">
                                        <div class="sd-mini-card">
                                            <div class="sd-mini-title">Sửa công nợ</div>

                                            <form method="POST" action="{{ route('finance.supplier-debts.update', $item->id) }}" enctype="multipart/form-data" class="sd-edit-grid">
                                                @csrf
                                                @method('PUT')

                                                <input class="sd-input" name="supplier_name" value="{{ $item->supplier_name }}" required>

                                                <select class="sd-select" name="company_name" required>
                                                    @foreach($companyOptions as $company)
                                                        <option value="{{ $company }}" {{ ($item->company_name ?? '') === $company ? 'selected' : '' }}>{{ $company }}</option>
                                                    @endforeach
                                                </select>

                                                <input class="sd-input" name="document_no" value="{{ $item->document_no }}" placeholder="Số chứng từ">
                                                <input class="sd-input" name="document_date" type="date" value="{{ $item->document_date }}">
                                                <input class="sd-input" name="debt_month" type="month" value="{{ !empty($item->debt_month) ? date('Y-m', strtotime($item->debt_month)) : $month }}" required>
                                                <input class="sd-input" name="total_amount" type="text" inputmode="decimal" data-money-input value="{{ $moneyInput($item->total_amount ?? 0) }}" required>

                                                <textarea class="sd-input span-2" name="bank_info" placeholder="Thông tin ngân hàng">{{ $item->bank_info ?? '' }}</textarea>
                                                <textarea class="sd-input span-2" name="note" placeholder="Ghi chú">{{ $item->note ?? '' }}</textarea>

                                                <button class="sd-btn sd-btn-primary" type="submit">Cập nhật</button>
                                            </form>

                                            @includeIf('finance.supplier-debts._debt_files_manager', ['item' => $item])


                                            <form method="POST" action="{{ route('finance.supplier-debts.destroy', $item->id) }}" onsubmit="return confirm('Xóa công nợ này?')" style="margin-top:10px">
                                                @csrf
                                                @method('DELETE')
                                                <button class="sd-btn sd-btn-danger" type="submit" {{ ($debtHasLinkedPaymentRequest && !$financeCompletedEditor) ? 'disabled' : '' }}>Xóa công nợ</button>
                                            </form>

                                            @if($debtHasLinkedPaymentRequest)
                                                <div class="sd-sub" style="margin-top:6px;color:#be123c;font-weight:800">Công nợ đã có ĐNTT liên kết nên không xóa trực tiếp.</div>
                                            @endif

                                            <div class="sd-sub" style="margin-top:12px">
                                                <b>Ghi chú:</b> {!! nl2br(e($item->note ?? '—')) !!}<br>
                                                <b>Ngân hàng:</b> {!! nl2br(e($item->bank_info ?? '—')) !!}
                                            </div>
                                        </div>

                                        <div class="sd-mini-card">
                                            <div class="sd-mini-title">Đợt thanh toán</div>

                                            <table class="sd-mini-table">
                                                <thead>
                                                    <tr>
                                                        <th>Đợt</th>
                                                        <th>Số tiền</th>
                                                        <th>Ngày</th>
                                                        <th>Trạng thái</th>
                                                        <th>ĐNTT</th>
                                                        <th></th>
                                                    </tr>
                                                </thead>

                                                <tbody>
                                                    @forelse(($item->payment_rounds ?? collect()) as $round)
                                                        @php
                                                            $roundStatus = (string) ($round->status ?? 'planned');
                                                            $roundClass = in_array($roundStatus, ['paid', 'accounting_approved'], true)
                                                                ? 'success'
                                                                : (in_array($roundStatus, ['planned', 'draft', 'requested', 'submitted', 'pending', 'admin_approved'], true) ? 'warning' : 'danger');
                                                            $roundLocked = (bool) ($round->payment_request_is_locked ?? false);
                                                            $roundIsPaid = in_array($roundStatus, ['paid', 'accounting_approved'], true);
                                                            $roundHasPaymentRequest = !empty($round->payment_request_id) && empty($round->payment_request_missing);
                                                            $roundPartialPaid = (bool) ($round->is_partial_paid ?? false);
                                                            $roundPaidByRequest = (float) ($round->paid_amount_by_request ?? 0);
                                                            $roundRemainingByRequest = (float) ($round->remaining_amount_by_request ?? 0);
                                                            $roundRemainingExists = (bool) ($round->remaining_round_exists ?? false);
                                                        @endphp

                                                        <tr>
                                                            <td>Đợt {{ $round->payment_round }}</td>
                                                            <td><b>{{ $money($round->amount ?? 0) }}</b></td>
                                                            <td>{{ !empty($round->payment_date) ? date('d/m/Y', strtotime($round->payment_date)) : '—' }}</td>
                                                            <td><span class="sd-badge {{ $roundClass }}">{{ $roundStatusText[$roundStatus] ?? ($roundStatus ?: 'Dự kiến') }}</span></td>
                                                            <td>
                                                                @if($roundHasPaymentRequest && empty($round->payment_request_missing))
                                                                    <a class="sd-btn sd-btn-light" href="{{ route('payment_requests.show', $round->payment_request_id) }}" style="height:34px;padding:0 10px;text-decoration:none" title="Mở đề nghị thanh toán #{{ $round->payment_request_id }}">Xem ĐNTT</a>
                                                                    @if($roundIsPaid)
                                                                        <div class="sd-sub" style="color:#059669;font-weight:800">Kế toán đã chi</div>

                                                                        @if($roundPartialPaid && $roundRemainingByRequest > 0)
                                                                            <div class="sd-linked-partial-card">
                                                                                <div class="line"><span>Đợt cần TT</span><b>{{ $money($round->amount ?? 0) }}</b></div>
                                                                                <div class="line"><span>Đã chi ĐNTT</span><b>{{ $money($roundPaidByRequest) }}</b></div>
                                                                                <div class="line"><span>Còn thiếu</span><b>{{ $money($roundRemainingByRequest) }}</b></div>
                                                                                <div class="hint">Chỉ tạo thêm 1 đợt công nợ cho phần còn thiếu, không tự link ĐNTT khác.</div>

                                                                                @if($financeCompletedEditor && !$roundRemainingExists)
                                                                                    <form method="POST" action="{{ route('finance.supplier-debts.payment-rounds.create-remaining-round', $round->id) }}">
                                                                                        @csrf
                                                                                        <button class="sd-btn sd-btn-light" type="submit">+ Tạo đợt còn lại {{ $money($roundRemainingByRequest) }}</button>
                                                                                    </form>
                                                                                @elseif($roundRemainingExists)
                                                                                    <div class="sd-sub" style="margin-top:6px;color:#059669;font-weight:800">Đã có dòng đợt còn lại.</div>
                                                                                @endif
                                                                            </div>
                                                                        @endif
                                                                    @elseif($roundLocked)
                                                                        <div class="sd-sub">Đã gửi/duyệt, khóa sửa trực tiếp</div>
                                                                    @endif
                                                                @elseif($roundIsPaid)
                                                                    <span class="sd-badge success">Đã thanh toán</span>
                                                                    <div class="sd-sub">Không tạo ĐNTT nữa</div>
                                                                @else
                                                                    <form method="POST" action="{{ route('finance.supplier-debts.payment-rounds.create-payment-request', $round->id) }}">
                                                                        @csrf
                                                                        <button class="sd-btn sd-btn-light" type="submit" style="height:34px;padding:0 10px">Tạo ĐNTT</button>
                                                                    </form>
                                                                @endif
                                                            </td>
                                                            <td>
                                                                <div class="sd-round-actions">
                                                                    <button class="sd-icon-btn" type="button" title="{{ ($roundIsPaid && !$financeCompletedEditor) ? 'Đợt đã thanh toán, không sửa trực tiếp' : 'Sửa đợt' }}" onclick="toggleRoundEdit({{ $round->id }})" {{ (($roundLocked || $roundIsPaid) && !$financeCompletedEditor) ? 'disabled' : '' }}>✎</button>

                                                                    @if(empty($round->payment_request_id) || $financeCompletedEditor)
                                                                        <form method="POST" action="{{ route('finance.supplier-debts.payment-rounds.destroy', $round->id) }}" onsubmit="return confirm('Xóa đợt thanh toán này? Nếu có ĐNTT liên kết, phiếu ĐNTT không bị xóa, chỉ gỡ liên kết dòng công nợ.')">
                                                                            @csrf
                                                                            @method('DELETE')
                                                                            <button class="sd-icon-btn red" type="submit" title="Xóa">×</button>
                                                                        </form>
                                                                    @endif
                                                                </div>
                                                            </td>
                                                        </tr>

                                                        <tr id="sd-round-edit-{{ $round->id }}" class="sd-round-edit-row">
                                                            <td colspan="6">
                                                                <div class="sd-round-edit-box">
                                                                    <form method="POST" action="{{ route('finance.supplier-debts.payment-rounds.update', $round->id) }}" enctype="multipart/form-data" class="sd-round-edit-form">
                                                                        @csrf
                                                                        @method('PUT')

                                                                        <input name="payment_round" type="number" min="1" value="{{ $round->payment_round }}" placeholder="Đợt" required>
                                                                        @php
                                                                            $roundPercent = ((float) ($item->total_amount ?? 0) > 0)
                                                                                ? round(((float) ($round->amount ?? 0) * 100) / (float) ($item->total_amount ?? 0), 4)
                                                                                : null;
                                                                            $roundPercentText = $roundPercent === null ? '' : rtrim(rtrim(number_format($roundPercent, 4, '.', ''), '0'), '.');
                                                                        @endphp
                                                                        <input name="_percent" type="text" inputmode="decimal" data-round-percent data-round-total="{{ (float) ($item->total_amount ?? 0) }}" value="{{ $roundPercentText }}" placeholder="%" title="Phần trăm theo tổng công nợ">
                                                                        <input name="amount" type="text" inputmode="decimal" data-money-input data-round-amount data-round-total="{{ (float) ($item->total_amount ?? 0) }}" value="{{ $moneyInput($round->amount ?? 0) }}" placeholder="Số tiền" required>
                                                                        <input name="payment_date" type="date" value="{{ $round->payment_date ?? '' }}">

                                                                        <select name="status">
                                                                            <option value="planned" {{ $roundStatus === 'planned' ? 'selected' : '' }}>Dự kiến</option>
                                                                            <option value="paid" {{ $roundStatus === 'paid' ? 'selected' : '' }}>Đã thanh toán</option>
                                                                            <option value="requested" {{ $roundStatus === 'requested' ? 'selected' : '' }}>Đã lập ĐNTT</option>
                                                                        </select>

                                                                        <textarea name="note" placeholder="Ghi chú">{{ $round->note ?? '' }}</textarea>
                                                                        <input name="attachments[]" type="file" multiple>

                                                                        <button class="sd-btn sd-btn-primary" type="submit" style="height:40px">Lưu</button>
                                                                        <button class="sd-btn sd-btn-light" type="button" style="height:40px" onclick="toggleRoundEdit({{ $round->id }})">Đóng</button>
                                                                    </form>
                                                                </div>
                                                            </td>
                                                        </tr>
                                                    @empty
                                                        <tr>
                                                            <td colspan="6">Chưa có đợt thanh toán.</td>
                                                        </tr>
                                                    @endforelse
                                                </tbody>
                                            </table>

                                            <form method="POST" action="{{ route('finance.supplier-debts.payment-rounds.store', $item->id) }}" enctype="multipart/form-data" class="sd-round-form">
                                                @csrf

                                                <input name="payment_round" type="number" min="1" placeholder="Đợt">
                                                <input name="_percent" type="text" inputmode="decimal" data-round-percent data-round-total="{{ (float) ($item->total_amount ?? 0) }}" placeholder="%" title="Phần trăm theo tổng công nợ">
                                                <input name="amount" type="text" inputmode="decimal" data-money-input data-round-amount data-round-total="{{ (float) ($item->total_amount ?? 0) }}" placeholder="Số tiền" required>
                                                <input name="payment_date" type="date">

                                                <select name="status">
                                                    <option value="planned">Dự kiến</option>
                                                    <option value="paid">Đã thanh toán</option>
                                                </select>

                                                <textarea name="note" placeholder="Ghi chú"></textarea>
                                                <input name="attachments[]" type="file" multiple>

                                                <button class="sd-btn sd-btn-primary" type="submit" style="height:40px">+ Đợt</button>
                                            </form>


                                            @php
                                                $roundCollection = collect($item->payment_rounds ?? []);
                                                $nextBulkRound = ((int) ($roundCollection->max('payment_round') ?? 0)) + 1;
                                                $existingRoundAmount = (float) $roundCollection->sum('amount');
                                            @endphp

                                            <div class="sd-bulk-trigger">
                                                <button class="sd-btn sd-btn-light" type="button" style="height:40px" onclick="showDebtSplitCard(this)">+ Thêm dòng theo %</button>
                                            </div>

                                            <div class="sd-bulk-split" hidden data-split-root data-total="{{ (float) ($item->total_amount ?? 0) }}" data-existing="{{ $existingRoundAmount }}" data-next-round="{{ $nextBulkRound }}">
                                                <div class="sd-split-head">
                                                    <div>
                                                        <div class="sd-mini-title" style="margin-bottom:3px">Thêm từng đợt theo %</div>
                                                        <div class="sd-sub">Mỗi lần bấm “Thêm dòng” sẽ thêm 1 dòng. Nhập % để tự nhảy số tiền.</div>
                                                    </div>

                                                    <div class="sd-split-tools">
                                                        <input type="number" min="1" max="24" value="3" data-split-count title="Số đợt">
                                                        <button class="sd-btn sd-btn-light" type="button" style="height:38px" data-split-refresh onclick="renderDebtSplitRows(this)">Thêm dòng</button>
                                                        <button class="sd-btn sd-btn-light" type="button" style="height:38px" onclick="fillDebtSplitEqual(this)" data-split-equal>Chia đều 100%</button>
                                                    </div>
                                                </div>

                                                <form method="POST" action="{{ route('finance.supplier-debts.payment-rounds.store', $item->id) }}" class="sd-bulk-form">
                                                    @csrf
                                                    <div class="sd-split-rows" data-split-rows></div>

                                                    <div class="sd-split-total">
                                                        <span data-split-summary>Tổng dòng mới: 0 đ</span>
                                                        <button class="sd-btn sd-btn-primary" type="submit" style="height:40px">Lưu các đợt</button>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7">
                                <div class="sd-empty">Không có công nợ phù hợp bộ lọc.</div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
    function toggleSupplierDebtDetail(id) {
        const row = document.getElementById('sd-detail-' + id);
        if (row) {
            row.classList.toggle('show');
        }
    }

    function openSupplierDebtEdit(id) {
        const row = document.getElementById('sd-detail-' + id);

        if (!row) {
            return;
        }

        row.classList.add('show');
        row.scrollIntoView({ behavior: 'smooth', block: 'center' });

        window.setTimeout(function () {
            const input = row.querySelector('.sd-edit-grid input:not([type="hidden"]), .sd-edit-grid select, .sd-edit-grid textarea');
            if (input) {
                input.focus({ preventScroll: true });
            }
        }, 200);
    }

    function toggleRoundEdit(id) {
        const row = document.getElementById('sd-round-edit-' + id);
        if (row) {
            row.classList.toggle('show');
        }
    }

    function normalizeMoneyValue(value) {
        value = String(value || '').trim();

        if (!value) {
            return value;
        }

        value = value.replace(/\s+/g, '').replace(/[^0-9,.-]/g, '');

        const lastComma = value.lastIndexOf(',');
        const lastDot = value.lastIndexOf('.');

        if (lastComma !== -1 && lastDot !== -1) {
            const decimalSep = lastComma > lastDot ? ',' : '.';
            const thousandSep = decimalSep === ',' ? '.' : ',';
            value = value.split(thousandSep).join('').replace(decimalSep, '.');
        } else if (lastComma !== -1) {
            const after = value.length - lastComma - 1;
            value = (after > 2 || (value.match(/,/g) || []).length > 1)
                ? value.split(',').join('')
                : value.replace(',', '.');
        } else if (lastDot !== -1) {
            const after = value.length - lastDot - 1;
            if (after === 3 || (value.match(/\./g) || []).length > 1) {
                value = value.split('.').join('');
            }
        }

        return value;
    }

    function parseDebtMoney(value) {
        const normalized = normalizeMoneyValue(value);
        const number = parseFloat(normalized);
        return isNaN(number) ? 0 : number;
    }

    function formatDebtMoney(value) {
        const number = Number(value || 0);
        const maximumFractionDigits = Math.abs(number - Math.round(number)) > 0.001 ? 2 : 0;

        return new Intl.NumberFormat('vi-VN', {
            maximumFractionDigits: maximumFractionDigits
        }).format(number) + ' đ';
    }

    function splitRootFromElement(element) {
        return element ? element.closest('[data-split-root]') : null;
    }

    function showDebtSplitCard(element) {
        const trigger = element ? element.closest('.sd-bulk-trigger') : null;
        const root = trigger ? trigger.nextElementSibling : null;

        if (!root || !root.matches('[data-split-root]')) {
            return;
        }

        root.hidden = false;
        renderDebtSplitRows(root.querySelector('[data-split-refresh]') || root);
        root.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }

    function splitRowHtml(index, roundNo) {
        return `
            <div class="sd-split-row" data-split-row>
                <input name="bulk_rounds[${index}][payment_round]" type="number" min="1" value="${roundNo}" placeholder="Đợt">
                <input name="bulk_rounds[${index}][percent]" type="text" inputmode="decimal" data-split-percent placeholder="%">
                <input name="bulk_rounds[${index}][amount]" type="text" inputmode="decimal" data-money-input data-split-amount placeholder="Số tiền">
                <input name="bulk_rounds[${index}][payment_date]" type="date">
                <select name="bulk_rounds[${index}][status]">
                    <option value="planned">Dự kiến</option>
                    <option value="paid">Đã thanh toán</option>
                    <option value="requested">Đã lập ĐNTT</option>
                </select>
                <textarea name="bulk_rounds[${index}][note]" placeholder="Ghi chú"></textarea>
                <button class="sd-icon-btn red" type="button" onclick="removeDebtSplitRow(this)" title="Xóa dòng">×</button>
            </div>
        `;
    }

    function bindDebtSplitEvents(root) {
        if (!root) {
            return;
        }

        root.querySelectorAll('[data-split-percent]').forEach(function (input) {
            input.addEventListener('input', function () {
                const row = input.closest('[data-split-row]');
                const amountInput = row ? row.querySelector('[data-split-amount]') : null;
                const total = parseDebtMoney(root.dataset.total || '0');
                const percent = parseDebtMoney(input.value);

                if (amountInput && input.value !== '') {
                    amountInput.value = Math.round((total * percent / 100) * 100) / 100;
                }

                updateDebtSplitSummary(root);
            });
        });

        root.querySelectorAll('[data-split-amount]').forEach(function (input) {
            input.addEventListener('input', function () {
                updateDebtSplitSummary(root);
            });
        });
    }

    function renderDebtSplitRows(element) {
        const root = splitRootFromElement(element);

        if (!root) {
            return;
        }

        const rowsBox = root.querySelector('[data-split-rows]');
        const nextRound = parseInt(root.dataset.nextRound || '1', 10) || 1;
        const index = rowsBox.querySelectorAll('[data-split-row]').length;

        rowsBox.insertAdjacentHTML('beforeend', splitRowHtml(index, nextRound + index));
        bindDebtSplitEvents(root);
        updateDebtSplitSummary(root);
    }

    function fillDebtSplitEqual(element) {
        const root = splitRootFromElement(element);

        if (!root) {
            return;
        }

        if (!root.querySelector('[data-split-row]')) {
            renderDebtSplitRows(root.querySelector('[data-split-refresh]'));
        }

        const rows = Array.from(root.querySelectorAll('[data-split-row]'));
        const total = parseDebtMoney(root.dataset.total || '0');
        let usedPercent = 0;
        let usedAmount = 0;

        rows.forEach(function (row, index) {
            const percentInput = row.querySelector('[data-split-percent]');
            const amountInput = row.querySelector('[data-split-amount]');
            let percent;
            let amount;

            if (index === rows.length - 1) {
                percent = Math.round((100 - usedPercent) * 10000) / 10000;
                amount = Math.round((total - usedAmount) * 100) / 100;
            } else {
                percent = Math.floor((100 / rows.length) * 10000) / 10000;
                amount = Math.round((total * percent / 100) * 100) / 100;
                usedPercent += percent;
                usedAmount += amount;
            }

            if (percentInput) {
                percentInput.value = percent;
            }

            if (amountInput) {
                amountInput.value = amount;
            }
        });

        updateDebtSplitSummary(root);
    }

    function removeDebtSplitRow(element) {
        const root = splitRootFromElement(element);
        const row = element ? element.closest('[data-split-row]') : null;

        if (row) {
            row.remove();
        }

        if (root) {
            updateDebtSplitSummary(root);
        }
    }

    function updateDebtSplitSummary(root) {
        if (!root) {
            return;
        }

        const total = parseDebtMoney(root.dataset.total || '0');
        const existing = parseDebtMoney(root.dataset.existing || '0');
        const summary = root.querySelector('[data-split-summary]');
        let percentTotal = 0;
        let amountTotal = 0;

        root.querySelectorAll('[data-split-row]').forEach(function (row) {
            percentTotal += parseDebtMoney(row.querySelector('[data-split-percent]')?.value || '0');
            amountTotal += parseDebtMoney(row.querySelector('[data-split-amount]')?.value || '0');
        });

        const afterAll = existing + amountTotal;
        const remaining = total - afterAll;
        const warning = afterAll > total ? ' - VƯỢT tổng công nợ!' : '';

        if (summary) {
            summary.innerHTML = 'Tổng dòng mới: <b>' + formatDebtMoney(amountTotal) + '</b> / ' + percentTotal.toFixed(2).replace(/\.00$/, '') + '%'
                + ' · Đã có đợt: <b>' + formatDebtMoney(existing) + '</b>'
                + ' · Còn lại sau lưu: <b>' + formatDebtMoney(remaining) + '</b>'
                + '<span style="color:#be123c">' + warning + '</span>';
        }
    }

    function bindRoundPercentEvents() {
        document.querySelectorAll('[data-round-percent]').forEach(function (percentInput) {
            percentInput.addEventListener('input', function () {
                const form = percentInput.closest('form');
                const amountInput = form ? form.querySelector('[data-round-amount]') : null;
                const total = parseDebtMoney(percentInput.dataset.roundTotal || '0');
                const percent = parseDebtMoney(percentInput.value);

                if (amountInput && percentInput.value !== '') {
                    amountInput.value = Math.round((total * percent / 100) * 100) / 100;
                }
            });
        });

        document.querySelectorAll('[data-round-amount]').forEach(function (amountInput) {
            amountInput.addEventListener('input', function () {
                const form = amountInput.closest('form');
                const percentInput = form ? form.querySelector('[data-round-percent]') : null;
                const total = parseDebtMoney(amountInput.dataset.roundTotal || '0');
                const amount = parseDebtMoney(amountInput.value);

                if (percentInput && total > 0) {
                    const percent = Math.round((amount * 100 / total) * 10000) / 10000;
                    percentInput.value = String(percent).replace(/\.0+$/, '');
                }
            });
        });
    }

    function toggleDebtMonth(value) {
        const input = document.getElementById('debtMonthFilter');

        if (!input) {
            return;
        }

        input.disabled = value === 'all';
        input.style.opacity = value === 'all' ? '.45' : '1';
    }

    document.addEventListener('DOMContentLoaded', function () {
        toggleDebtMonth(document.querySelector('select[name="period"]')?.value || 'all');
        bindRoundPercentEvents();
        document.querySelectorAll('form').forEach(function (form) {
            form.addEventListener('submit', function () {
                form.querySelectorAll('[data-money-input]').forEach(function (input) {
                    input.value = normalizeMoneyValue(input.value);
                });
            });
        });
    });
</script>
@if($financeCompletedEditor)
<script id="EGO_FINANCE_EDITOR_UNLOCK_UI">
    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('button[disabled]').forEach(function (btn) {
            const title = (btn.getAttribute('title') || '').toLowerCase();
            if (
                title.includes('thanh toán') ||
                title.includes('đntt') ||
                title.includes('khóa') ||
                title.includes('khong') ||
                title.includes('không')
            ) {
                btn.disabled = false;
                btn.title = 'Được phép sửa/xóa (quyền Admin/Giám đốc)';
            }
        });
    });
</script>
@endif

@endsection

