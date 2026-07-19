@extends('layouts.app')

@section('content')
@php
    $fmt = fn($n) => number_format((float) $n, 0, ',', '.');

    $statusLabels = [
        'draft' => 'Nháp',
        'sent' => 'Đã gửi',
        'approved' => 'Khách duyệt',
        'cancelled' => 'Đã hủy',
    ];

    $statusColors = [
        'draft' => ['bg' => '#e0f2fe', 'text' => '#0369a1'],
        'sent' => ['bg' => '#dcfce7', 'text' => '#166534'],
        'approved' => ['bg' => '#ede9fe', 'text' => '#6d28d9'],
        'cancelled' => ['bg' => '#fee2e2', 'text' => '#b91c1c'],
    ];
@endphp

<style>
    body {
        background: #f4f7fb;
    }

    .quote-index {
        max-width: 1320px;
        margin: 0 auto;
        padding: 16px 20px;
        font-size: 13px;
    }

    .page-head {
        background: #ffffff;
        border: 1px solid #dbe5f0;
        border-radius: 18px;
        padding: 14px 16px;
        margin-bottom: 12px;
        box-shadow: 0 8px 22px rgba(15, 23, 42, .05);
        display: flex;
        justify-content: space-between;
        gap: 12px;
        align-items: center;
    }

    .page-head h1 {
        margin: 0;
        font-size: 22px;
        font-weight: 950;
        color: #0f172a;
        line-height: 1.15;
    }

    .page-head p {
        margin: 3px 0 0;
        color: #64748b;
        font-size: 12px;
    }

    .btnx {
        border: 0;
        border-radius: 11px;
        padding: 9px 12px;
        font-weight: 900;
        font-size: 12px;
        text-decoration: none;
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 6px;
        white-space: nowrap;
    }

    .btn-primary {
        background: linear-gradient(135deg, #14b8a6, #2563eb);
        color: #fff;
        box-shadow: 0 10px 22px rgba(37,99,235,.20);
    }

    .btn-gray {
        background: #f8fafc;
        color: #334155;
        border: 1px solid #cbd5e1;
    }

    .summary-grid {
        display: grid;
        grid-template-columns: repeat(5, minmax(0, 1fr));
        gap: 10px;
        margin-bottom: 12px;
    }

    .summary-card {
        background: #fff;
        border: 1px solid #dbe5f0;
        border-radius: 15px;
        padding: 12px 13px;
        box-shadow: 0 6px 18px rgba(15,23,42,.04);
        min-height: 74px;
    }

    .summary-card small {
        display: block;
        color: #64748b;
        font-weight: 900;
        margin-bottom: 5px;
        font-size: 12px;
    }

    .summary-card b {
        display: block;
        color: #0f172a;
        font-size: 20px;
        font-weight: 1000;
        line-height: 1.05;
    }

    .summary-card.money {
        background: linear-gradient(135deg, #ecfeff, #eff6ff);
        border-color: #7dd3fc;
        text-align: right;
    }

    .summary-card.money b {
        color: #075985;
        font-size: 22px;
    }

    .panel {
        background: #fff;
        border: 1px solid #dbe5f0;
        border-radius: 18px;
        box-shadow: 0 8px 22px rgba(15,23,42,.05);
        overflow: hidden;
    }

    .filter-box {
        padding: 13px;
        border-bottom: 1px solid #dbe5f0;
        background: #fff;
    }

    .filter-row {
        display: grid;
        grid-template-columns: 1fr 145px 138px 138px auto auto;
        gap: 8px;
        align-items: center;
    }

    .filter-row input,
    .filter-row select {
        width: 100%;
        border: 1px solid #cbd5e1;
        border-radius: 11px;
        padding: 10px 11px;
        outline: none;
        background: #fff;
        color: #0f172a;
        font-size: 13px;
    }

    .quick-status {
        display: flex;
        flex-wrap: wrap;
        gap: 7px;
        margin-top: 10px;
    }

    .status-pill {
        display: inline-flex;
        align-items: center;
        padding: 6px 10px;
        border-radius: 999px;
        background: #f8fafc;
        border: 1px solid #cbd5e1;
        color: #334155;
        text-decoration: none;
        font-size: 12px;
        font-weight: 900;
    }

    .status-pill.active {
        background: #0ea5e9;
        border-color: #0ea5e9;
        color: #fff;
    }

    .table-wrap {
        overflow: auto;
    }

    .q-table {
        width: 100%;
        min-width: 1040px;
        border-collapse: separate;
        border-spacing: 0;
    }

    .q-table th {
        background: #eff6ff;
        color: #1e3a8a;
        text-align: left;
        padding: 10px 11px;
        font-size: 11px;
        text-transform: uppercase;
        letter-spacing: .04em;
        border-bottom: 1px solid #bfdbfe;
        white-space: nowrap;
    }

    .q-table td {
        padding: 11px;
        border-bottom: 1px solid #edf2f7;
        vertical-align: middle;
        font-size: 13px;
    }

    .q-table tr:hover td {
        background: #fbfdff;
    }

    .code-link {
        display: inline-flex;
        color: #0f172a;
        font-size: 14px;
        font-weight: 950;
        text-decoration: none;
        line-height: 1.15;
    }

    .code-link:hover {
        color: #0369a1;
    }

    .muted {
        color: #64748b;
        font-size: 12px;
        margin-top: 2px;
    }

    .customer-name {
        color: #0f172a;
        font-weight: 900;
    }

    .project-title {
        color: #0f172a;
        font-weight: 800;
        max-width: 280px;
    }

    .money {
        color: #0f172a;
        font-size: 15px;
        font-weight: 1000;
        text-align: right;
    }

    .badge {
        display: inline-flex;
        padding: 5px 9px;
        border-radius: 999px;
        font-size: 11px;
        font-weight: 950;
        white-space: nowrap;
    }

    .row-actions {
        display: flex;
        flex-wrap: wrap;
        gap: 5px;
        min-width: 230px;
    }

    .mini-btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        padding: 6px 8px;
        border-radius: 9px;
        font-size: 11px;
        font-weight: 900;
        text-decoration: none;
        color: #0369a1;
        background: #e0f2fe;
        border: 1px solid #bae6fd;
    }

    .mini-btn.green {
        color: #0f766e;
        background: #ccfbf1;
        border-color: #99f6e4;
    }

    .mini-btn.dark {
        color: #0f172a;
        background: #f8fafc;
        border-color: #cbd5e1;
    }

    .empty {
        padding: 36px 20px;
        text-align: center;
        color: #64748b;
    }

    .empty b {
        display: block;
        color: #0f172a;
        font-size: 16px;
        margin-bottom: 5px;
    }

    .success {
        background: #dcfce7;
        color: #166534;
        border: 1px solid #bbf7d0;
        padding: 11px 14px;
        border-radius: 14px;
        margin-bottom: 12px;
        font-weight: 900;
        font-size: 13px;
    }

    .pagination-wrap {
        padding: 12px 14px;
        border-top: 1px solid #edf2f7;
    }

    @media (max-width: 1200px) {
        .summary-grid {
            grid-template-columns: repeat(2, 1fr);
        }

        .filter-row {
            grid-template-columns: 1fr 1fr;
        }

        .page-head {
            flex-direction: column;
            align-items: flex-start;
        }
    }

    @media (max-width: 720px) {
        .quote-index {
            padding: 12px;
        }

        .summary-grid,
        .filter-row {
            grid-template-columns: 1fr;
        }
    }
</style>

<div class="quote-index">
    <div class="page-head">
        <div>
            <h1>Báo giá</h1>
            <p>Quản lý báo giá, tạo PDF/Excel gửi khách.</p>
        </div>

        <a href="{{ route('sales-quotations.create') }}" class="btnx btn-primary">+ Tạo báo giá mới</a>
    </div>

    @if(session('success'))
        <div class="success">{{ session('success') }}</div>
    @endif

    <div class="summary-grid">
        <div class="summary-card">
            <small>Tổng báo giá</small>
            <b>{{ $summary['total_count'] ?? 0 }}</b>
        </div>

        <div class="summary-card">
            <small>Báo giá nháp</small>
            <b>{{ $summary['draft_count'] ?? 0 }}</b>
        </div>

        <div class="summary-card">
            <small>Đã gửi khách</small>
            <b>{{ $summary['sent_count'] ?? 0 }}</b>
        </div>

        <div class="summary-card">
            <small>Khách duyệt</small>
            <b>{{ $summary['approved_count'] ?? 0 }}</b>
        </div>

        <div class="summary-card money">
            <small>Tổng giá trị</small>
            <b>{{ $fmt($summary['grand_total'] ?? 0) }} đ</b>
        </div>
    </div>

    <div class="panel">
        <form method="GET" class="filter-box">
            <div class="filter-row">
                <input name="q" value="{{ request('q') }}" placeholder="Tìm mã báo giá, khách hàng, SĐT, email, tên dự án...">

                <select name="status">
                    <option value="">Tất cả trạng thái</option>
                    @foreach($statusLabels as $key => $label)
                        <option value="{{ $key }}" @selected(request('status') === $key)>{{ $label }}</option>
                    @endforeach
                </select>

                <input type="date" name="date_from" value="{{ request('date_from') }}">
                <input type="date" name="date_to" value="{{ request('date_to') }}">

                <button class="btnx btn-primary" type="submit">Tìm kiếm</button>
                <a href="{{ route('sales-quotations.index') }}" class="btnx btn-gray">Xóa lọc</a>
            </div>

            <div class="quick-status">
                <a class="status-pill {{ request('status') === null || request('status') === '' ? 'active' : '' }}" href="{{ route('sales-quotations.index', request()->except('status', 'page')) }}">
                    Tất cả
                </a>

                @foreach($statusLabels as $key => $label)
                    <a class="status-pill {{ request('status') === $key ? 'active' : '' }}"
                       href="{{ route('sales-quotations.index', array_merge(request()->except('page'), ['status' => $key])) }}">
                        {{ $label }}
                    </a>
                @endforeach
            </div>
        </form>

        <div class="table-wrap">
            <table class="q-table">
                <thead>
                    <tr>
                        <th>Mã báo giá</th>
                        <th>Khách hàng</th>
                        <th>Dự án</th>
                        <th>Ngày</th>
                        <th style="text-align:right;">Tổng tiền</th>
                        <th>Trạng thái</th>
                        <th>Thao tác</th>
                    </tr>
                </thead>

                <tbody>
                    @forelse($quotations as $q)
                        @php
                            $color = $statusColors[$q->status] ?? ['bg' => '#f1f5f9', 'text' => '#334155'];
                        @endphp

                        <tr>
                            <td>
                                <a class="code-link" href="{{ route('sales-quotations.show', $q) }}">
                                    {{ $q->quote_code }}
                                </a>
                                <div class="muted">
                                    Hiệu lực: {{ optional($q->valid_until)->format('d/m/Y') ?: '—' }}
                                </div>
                            </td>

                            <td>
                                <div class="customer-name">{{ $q->customer_name ?: '—' }}</div>
                                <div class="muted">
                                    {{ $q->customer_phone ?: 'Chưa có SĐT' }}
                                    @if($q->customer_email)
                                        · {{ $q->customer_email }}
                                    @endif
                                </div>
                            </td>

                            <td>
                                <div class="project-title">{{ $q->project_name ?: '—' }}</div>
                                <div class="muted">
                                    {{ $q->system_kwp ? $q->system_kwp . ' kWp' : 'Chưa nhập công suất' }}
                                </div>
                            </td>

                            <td>
                                <b>{{ optional($q->quote_date)->format('d/m/Y') ?: '—' }}</b>
                            </td>

                            <td class="money">
                                {{ $fmt($q->grand_total) }} đ
                            </td>

                            <td>
                                <span class="badge" style="background: {{ $color['bg'] }}; color: {{ $color['text'] }};">
                                    {{ $statusLabels[$q->status] ?? $q->status }}
                                </span>
                            </td>

                            <td>
                                <div class="row-actions">
                                    <a class="mini-btn dark" href="{{ route('sales-quotations.show', $q) }}">Xem</a>
                                    <a class="mini-btn dark" href="{{ route('sales-quotations.edit', $q) }}">Sửa</a>
                                    <a class="mini-btn" href="{{ route('sales-quotations.pdf', $q) }}">Xuất PDF</a>
                                    <a class="mini-btn green" href="{{ route('sales-quotations.pdf.download', $q) }}">Tải PDF</a>
                                    <a class="mini-btn green" href="{{ route('sales-quotations.excel', $q) }}">Excel</a>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7">
                                <div class="empty">
                                    <b>Chưa có báo giá nào</b>
                                    Bấm “Tạo báo giá mới” để tạo báo giá đầu tiên.
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="pagination-wrap">
            {{ $quotations->links() }}
        </div>
    </div>
</div>
@endsection
