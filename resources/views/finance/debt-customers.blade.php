@extends('layouts.app')

@section('content')
@php
    $pagePaidTotal = (float) ($fullSummary['paid_amount'] ?? 0);
    $pageDebtTotal = (float) ($fullSummary['debt_amount'] ?? 0);
    $pageRevenueTotal = (float) ($fullSummary['total_amount'] ?? 0);
    $totalCustomersAll = (int) ($fullSummary['total_customers'] ?? $customers->total());
@endphp

<div class="finance-modern-page">
    <div class="finance-hero-card">
        <div class="finance-hero-card__bg"></div>

        <div class="finance-modern-hero">
            <div>
                <div class="finance-modern-kicker">Customer Debt Management</div>
                <h1 class="finance-modern-title">Công nợ khách hàng</h1>
                <p class="finance-modern-subtitle">
                    Gộp theo khách hàng, lọc nhanh dữ liệu, theo dõi tổng tiền, số đã thanh toán và phần công nợ còn lại.
                </p>
            </div>

            <div class="finance-modern-actions">
                <a href="{{ route('finance.customer-debts.by-customer', request()->query()) }}" class="finance-btn finance-btn--ghost">
                    <i class="bi bi-bar-chart"></i>
                    <span>Bảng tổng hợp</span>
                </a>
                <a href="{{ route('finance.customer-debts.payment-history', request()->query()) }}" class="finance-btn finance-btn--primary">
                    <i class="bi bi-clock-history"></i>
                    <span>Lịch sử thanh toán</span>
                </a>
            </div>
        </div>

        <form method="GET" class="finance-filter-card">
            <div class="finance-filter-grid">
                <div class="finance-filter-item finance-filter-item--wide">
                    <label>Từ khóa</label>
                    <div class="finance-input-wrap">
                        <i class="bi bi-search"></i>
                        <input
                            type="text"
                            name="keyword"
                            value="{{ request('keyword') }}"
                            placeholder="Tìm mã đơn, tên khách hàng, người nhận...">
                    </div>
                </div>

                <div class="finance-filter-item">
                    <label>Từ ngày</label>
                    <input type="date" name="from_date" value="{{ request('from_date') }}">
                </div>

                <div class="finance-filter-item">
                    <label>Đến ngày</label>
                    <input type="date" name="to_date" value="{{ request('to_date') }}">
                </div>

                <div class="finance-filter-item">
                    <label>Trạng thái</label>
                    <select name="payment_status">
                        <option value="">-- Tất cả --</option>
                        <option value="paid" {{ request('payment_status') === 'paid' ? 'selected' : '' }}>Đã hoàn thành</option>
                        <option value="unpaid" {{ request('payment_status') === 'unpaid' ? 'selected' : '' }}>Công nợ</option>
                    </select>
                </div>
            </div>

            <div class="finance-filter-actions">
                <button type="submit" class="finance-btn finance-btn--primary">
                    <i class="bi bi-funnel"></i>
                    <span>Lọc dữ liệu</span>
                </button>

                <a href="{{ route('finance.customer-debts.index') }}" class="finance-btn finance-btn--ghost">
                    <i class="bi bi-arrow-counterclockwise"></i>
                    <span>Đặt lại</span>
                </a>
            </div>
        </form>
    </div>

    <div class="finance-summary-row">
        <div class="finance-summary-card finance-summary-card--blue">
            <div class="finance-summary-card__icon">
                <i class="bi bi-people"></i>
            </div>
            <div>
                <span>Tổng khách hàng</span>
                <strong>{{ number_format($totalCustomersAll) }}</strong>
            </div>
        </div>

        <div class="finance-summary-card finance-summary-card--violet">
            <div class="finance-summary-card__icon">
                <i class="bi bi-eye"></i>
            </div>
            <div>
                <span>Đang xem</span>
                <strong>{{ $customers->count() }}</strong>
            </div>
        </div>

        <div class="finance-summary-card finance-summary-card--green">
            <div class="finance-summary-card__icon">
                <i class="bi bi-cash-stack"></i>
            </div>
            <div>
                <span>Đã thanh toán</span>
                <strong>{{ number_format($pagePaidTotal, 0, ',', '.') }} đ</strong>
            </div>
        </div>

        <div class="finance-summary-card finance-summary-card--red">
            <div class="finance-summary-card__icon">
                <i class="bi bi-exclamation-diamond"></i>
            </div>
            <div>
                <span>Tổng công nợ còn lại</span>
                <strong>{{ number_format($pageDebtTotal, 0, ',', '.') }} đ</strong>
            </div>
        </div>
    </div>

    <div class="finance-overview-strip">
        <div class="finance-overview-pill">
            <span class="finance-overview-pill__label">Tổng doanh số sau lọc</span>
            <span class="finance-overview-pill__value">{{ number_format($pageRevenueTotal, 0, ',', '.') }} đ</span>
        </div>

        <div class="finance-overview-pill finance-overview-pill--success">
            <span class="finance-overview-pill__label">Đã thanh toán</span>
            <span class="finance-overview-pill__value">{{ number_format($pagePaidTotal, 0, ',', '.') }} đ</span>
        </div>

        <div class="finance-overview-pill finance-overview-pill--danger">
            <span class="finance-overview-pill__label">Còn phải thu</span>
            <span class="finance-overview-pill__value">{{ number_format($pageDebtTotal, 0, ',', '.') }} đ</span>
        </div>
    </div>

    <div class="finance-table-card">
        <div class="finance-table-head">
            <div>
                <h3>Danh sách công nợ gộp theo khách hàng</h3>
                <p>Bấm dấu cộng để mở danh sách đơn hàng của từng khách.</p>
            </div>
        </div>

        <div class="table-responsive">
            <table class="table finance-table align-middle mb-0">
                <thead>
                    <tr>
                        <th style="width: 56px;"></th>
                        <th>Khách hàng</th>
                        <th class="text-end">Số đơn</th>
                        <th class="text-end">Tổng tiền</th>
                        <th class="text-end">Đã thanh toán</th>
                        <th class="text-end">Còn nợ</th>
                        <th>Trạng thái</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($customers as $customer)
                        @php
                            $customerDebt = (float) ($customer->debt_amount ?? 0);
                            $customerStatusClass = $customerDebt > 0
                                ? 'finance-status finance-status--danger'
                                : 'finance-status finance-status--success';

                            $customerStatusText = $customerDebt > 0 ? 'Công nợ' : 'Đã hoàn thành';
                        @endphp

                        <tr class="finance-group-row">
                            <td>
                                <button class="finance-toggle-btn"
                                        type="button"
                                        data-target="detail-{{ md5($customer->group_key) }}">
                                    <i class="bi bi-plus"></i>
                                </button>
                            </td>

                            <td>
                                <div class="finance-customer-block">
                                    <div class="finance-customer-avatar">
                                        {{ mb_substr($customer->customer_name, 0, 1) }}
                                    </div>
                                    <div>
                                        <div class="finance-customer-name">{{ $customer->customer_name }}</div>
                                        <div class="finance-customer-sub">Khách hàng công nợ</div>
                                    </div>
                                </div>
                            </td>

                            <td class="text-end">
                                <span class="finance-chip finance-chip--neutral">{{ number_format($customer->total_orders) }} đơn</span>
                            </td>

                            <td class="text-end finance-money finance-money--dark">
                                {{ number_format($customer->total_amount, 0, ',', '.') }} đ
                            </td>

                            <td class="text-end finance-money finance-money--success">
                                {{ number_format($customer->paid_amount, 0, ',', '.') }} đ
                            </td>

                            <td class="text-end finance-money finance-money--danger">
                                {{ number_format($customer->debt_amount, 0, ',', '.') }} đ
                            </td>

                            <td>
                                <span class="{{ $customerStatusClass }}">
                                    <span class="finance-status__dot"></span>
                                    {{ $customerStatusText }}
                                </span>
                            </td>
                        </tr>

                        <tr id="detail-{{ md5($customer->group_key) }}" class="finance-detail-row" style="display:none;">
                            <td colspan="7" class="p-0">
                                <div class="finance-detail-panel">
                                    <div class="table-responsive">
                                        <table class="table finance-detail-table align-middle mb-0">
                                            <thead>
                                                <tr>
                                                    <th>Mã đơn</th>
                                                    <th class="text-end">Tổng tiền</th>
                                                    <th class="text-end">Đã thanh toán</th>
                                                    <th class="text-end">Còn nợ</th>
                                                    <th>Trạng thái</th>
                                                    <th>Ngày tạo</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @foreach($customer->orders as $order)
                                                    @php
                                                        $totalAmount = (float) ($order->total_amount ?? 0);
                                                        $debtAmount = (float) ($order->debt_amount ?? 0);

                                                        $isCompleted = $totalAmount <= 0
                                                            || $debtAmount <= 0
                                                            || (($order->payment_recorded ?? 0) == 1);

                                                        $statusClass = $isCompleted
                                                            ? 'finance-status finance-status--success'
                                                            : 'finance-status finance-status--danger';

                                                        $statusText = $isCompleted ? 'Đã hoàn thành' : 'Công nợ';
                                                    @endphp

                                                    <tr>
                                                        <td>
                                                            <a href="{{ route('orders.show', $order->id) }}"
                                                               class="finance-order-link">
                                                                {{ $order->order_code }}
                                                            </a>
                                                        </td>

                                                        <td class="text-end finance-money finance-money--dark">
                                                            {{ number_format($order->total_amount, 0, ',', '.') }} đ
                                                        </td>

                                                        <td class="text-end finance-money finance-money--success">
                                                            {{ number_format($order->paid_amount, 0, ',', '.') }} đ
                                                        </td>

                                                        <td class="text-end finance-money finance-money--danger">
                                                            {{ number_format($order->debt_amount, 0, ',', '.') }} đ
                                                        </td>

                                                        <td>
                                                            <span class="{{ $statusClass }}">
                                                                <span class="finance-status__dot"></span>
                                                                {{ $statusText }}
                                                            </span>
                                                        </td>

                                                        <td>{{ optional($order->created_at)->format('d/m/Y H:i') }}</td>
                                                    </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center text-muted py-5">Không có dữ liệu phù hợp bộ lọc.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="finance-pagination-wrap">
            {{ $customers->links() }}
        </div>
    </div>
</div>

<style>
.finance-modern-page{
    padding:24px;
    background:
        radial-gradient(900px 500px at 0% 0%, rgba(59,130,246,.08), transparent 55%),
        radial-gradient(900px 500px at 100% 0%, rgba(139,92,246,.08), transparent 55%);
}

.finance-hero-card{
    position:relative;
    overflow:hidden;
    background:linear-gradient(135deg,#0f172a 0%, #1e3a8a 45%, #2563eb 100%);
    border-radius:28px;
    padding:24px;
    margin-bottom:20px;
    box-shadow:0 24px 60px rgba(37,99,235,.22);
}

.finance-hero-card__bg{
    position:absolute;
    inset:0;
    background:
        radial-gradient(circle at 15% 20%, rgba(255,255,255,.16), transparent 25%),
        radial-gradient(circle at 85% 15%, rgba(255,255,255,.14), transparent 20%),
        radial-gradient(circle at 70% 80%, rgba(255,255,255,.10), transparent 18%);
    pointer-events:none;
}

.finance-modern-hero{
    position:relative;
    z-index:1;
    display:flex;
    justify-content:space-between;
    align-items:flex-start;
    gap:16px;
    flex-wrap:wrap;
    margin-bottom:20px;
}

.finance-modern-kicker{
    display:inline-block;
    padding:7px 12px;
    border-radius:999px;
    background:rgba(255,255,255,.14);
    color:#dbeafe;
    font-size:12px;
    font-weight:800;
    text-transform:uppercase;
    letter-spacing:.08em;
    margin-bottom:12px;
    border:1px solid rgba(255,255,255,.18);
    backdrop-filter:blur(8px);
}

.finance-modern-title{
    margin:0;
    font-size:46px;
    line-height:1.02;
    font-weight:900;
    color:#fff;
    letter-spacing:-.02em;
}

.finance-modern-subtitle{
    margin:12px 0 0;
    color:rgba(255,255,255,.84);
    max-width:760px;
    font-size:16px;
}

.finance-modern-actions{
    display:flex;
    gap:10px;
    flex-wrap:wrap;
}

.finance-btn{
    display:inline-flex;
    align-items:center;
    gap:8px;
    border-radius:16px;
    padding:12px 18px;
    text-decoration:none;
    font-weight:800;
    border:1px solid transparent;
    transition:.22s ease;
}

.finance-btn--primary{
    background:linear-gradient(135deg,#38bdf8,#2563eb);
    color:#fff;
    box-shadow:0 12px 28px rgba(2,132,199,.28);
}

.finance-btn--primary:hover{
    color:#fff;
    transform:translateY(-2px);
    box-shadow:0 18px 36px rgba(2,132,199,.35);
}

.finance-btn--ghost{
    background:rgba(255,255,255,.12);
    color:#fff;
    border-color:rgba(255,255,255,.18);
    backdrop-filter:blur(10px);
}

.finance-btn--ghost:hover{
    color:#fff;
    background:rgba(255,255,255,.18);
}

.finance-filter-card{
    position:relative;
    z-index:1;
    background:rgba(255,255,255,.96);
    border:1px solid rgba(255,255,255,.35);
    border-radius:24px;
    box-shadow:0 14px 40px rgba(15,23,42,.10);
    padding:18px;
}

.finance-filter-grid{
    display:grid;
    grid-template-columns:2fr 1fr 1fr 1fr;
    gap:14px;
}

.finance-filter-item{
    display:flex;
    flex-direction:column;
    gap:8px;
}

.finance-filter-item label{
    font-size:13px;
    font-weight:800;
    color:#334155;
}

.finance-filter-item input,
.finance-filter-item select{
    height:48px;
    border-radius:16px;
    border:1px solid #d7e3f0;
    background:linear-gradient(180deg,#ffffff,#f8fbff);
    padding:0 14px;
    outline:none;
    transition:.18s ease;
}

.finance-filter-item input:focus,
.finance-filter-item select:focus{
    border-color:#60a5fa;
    box-shadow:0 0 0 4px rgba(96,165,250,.16);
}

.finance-input-wrap{
    position:relative;
}

.finance-input-wrap i{
    position:absolute;
    left:14px;
    top:50%;
    transform:translateY(-50%);
    color:#94a3b8;
}

.finance-input-wrap input{
    width:100%;
    padding-left:40px;
}

.finance-filter-actions{
    display:flex;
    gap:10px;
    flex-wrap:wrap;
    margin-top:16px;
}

.finance-summary-row{
    display:grid;
    grid-template-columns:repeat(4, minmax(0, 1fr));
    gap:14px;
    margin-bottom:18px;
}

.finance-summary-card{
    min-width:0;
    display:flex;
    align-items:center;
    gap:14px;
    border-radius:22px;
    padding:16px 18px;
    color:#fff;
    box-shadow:0 14px 36px rgba(15,23,42,.12);
}

.finance-summary-card--blue{
    background:linear-gradient(135deg,#0ea5e9,#2563eb);
}

.finance-summary-card--violet{
    background:linear-gradient(135deg,#8b5cf6,#6366f1);
}

.finance-summary-card--green{
    background:linear-gradient(135deg,#10b981,#059669);
}

.finance-summary-card--red{
    background:linear-gradient(135deg,#ef4444,#dc2626);
}

.finance-summary-card__icon{
    width:48px;
    height:48px;
    border-radius:16px;
    display:flex;
    align-items:center;
    justify-content:center;
    background:rgba(255,255,255,.16);
    border:1px solid rgba(255,255,255,.18);
    font-size:20px;
    flex:0 0 auto;
}

.finance-summary-card span{
    display:block;
    font-size:13px;
    opacity:.92;
    margin-bottom:4px;
}

.finance-summary-card strong{
    font-size:24px;
    color:#fff;
    line-height:1.1;
    word-break:break-word;
}

.finance-overview-strip{
    display:flex;
    gap:12px;
    flex-wrap:wrap;
    margin-bottom:18px;
}

.finance-overview-pill{
    display:flex;
    align-items:center;
    justify-content:space-between;
    gap:16px;
    min-width:250px;
    padding:14px 16px;
    border-radius:18px;
    background:#ffffff;
    border:1px solid #e6edf5;
    box-shadow:0 10px 24px rgba(15,23,42,.05);
}

.finance-overview-pill--success{
    background:linear-gradient(180deg,#ecfdf5,#d1fae5);
    border-color:#a7f3d0;
}

.finance-overview-pill--danger{
    background:linear-gradient(180deg,#fef2f2,#fee2e2);
    border-color:#fecaca;
}

.finance-overview-pill__label{
    font-size:13px;
    font-weight:700;
    color:#475569;
}

.finance-overview-pill__value{
    font-size:18px;
    font-weight:900;
    color:#0f172a;
    white-space:nowrap;
}

.finance-table-card{
    background:linear-gradient(180deg,#ffffff,#fbfdff);
    border:1px solid #e6edf5;
    border-radius:28px;
    overflow:hidden;
    box-shadow:0 18px 44px rgba(15,23,42,.08);
}

.finance-table-head{
    padding:20px 22px;
    border-bottom:1px solid #eef3f8;
    background:
        radial-gradient(800px 300px at 0% 0%, rgba(37,99,235,.06), transparent 50%),
        linear-gradient(180deg,#ffffff,#fbfdff);
}

.finance-table-head h3{
    margin:0 0 6px;
    font-size:22px;
    font-weight:900;
    color:#0f172a;
}

.finance-table-head p{
    margin:0;
    color:#64748b;
}

.finance-table thead th{
    position:sticky;
    top:0;
    background:linear-gradient(180deg,#f8fbff,#f1f6fc);
    z-index:2;
    border-bottom:1px solid #e6edf5;
    color:#475569;
    font-size:12px;
    font-weight:900;
    text-transform:uppercase;
    letter-spacing:.08em;
    padding:16px;
}

.finance-table tbody td{
    padding:16px;
    border-bottom:1px solid #eff4f8;
    vertical-align:middle;
}

.finance-group-row{
    background:#fff;
    transition:.18s ease;
}

.finance-group-row:hover{
    background:#f8fbff;
}

.finance-customer-block{
    display:flex;
    align-items:center;
    gap:12px;
}

.finance-customer-avatar{
    width:42px;
    height:42px;
    border-radius:14px;
    display:flex;
    align-items:center;
    justify-content:center;
    font-weight:900;
    font-size:16px;
    color:#1d4ed8;
    background:linear-gradient(135deg,rgba(59,130,246,.14),rgba(14,165,233,.16));
    border:1px solid rgba(59,130,246,.14);
    text-transform:uppercase;
    flex:0 0 auto;
}

.finance-customer-name{
    font-size:16px;
    font-weight:900;
    color:#0f172a;
}

.finance-customer-sub{
    font-size:12px;
    color:#64748b;
    margin-top:2px;
}

.finance-money{
    font-weight:900;
    white-space:nowrap;
    font-size:15px;
}

.finance-money--dark{ color:#0f172a; }
.finance-money--success{ color:#059669; }
.finance-money--danger{ color:#ef4444; }

.finance-chip{
    display:inline-flex;
    align-items:center;
    padding:8px 12px;
    border-radius:999px;
    font-size:12px;
    font-weight:900;
    line-height:1;
    white-space:nowrap;
    box-shadow:inset 0 1px 0 rgba(255,255,255,.5);
}

.finance-chip--neutral{
    background:linear-gradient(180deg,#f1f5f9,#e2e8f0);
    color:#334155;
}

.finance-toggle-btn{
    width:36px;
    height:36px;
    border-radius:999px;
    border:1px solid #c7d6e5;
    background:linear-gradient(180deg,#ffffff,#f1f6fc);
    color:#475569;
    display:flex;
    align-items:center;
    justify-content:center;
    transition:.2s ease;
    box-shadow:0 4px 10px rgba(15,23,42,.06);
}

.finance-toggle-btn:hover{
    background:linear-gradient(180deg,#eff6ff,#dbeafe);
    border-color:#93c5fd;
    color:#1d4ed8;
    transform:scale(1.04);
}

.finance-detail-panel{
    background:
        radial-gradient(600px 200px at 0% 0%, rgba(37,99,235,.05), transparent 40%),
        linear-gradient(180deg,#fcfdff,#f6faff);
    border-top:1px solid #e6edf5;
    padding:14px 18px 18px;
}

.finance-detail-table thead th{
    background:transparent;
    position:static;
    border-bottom:1px solid #e8eef5;
    font-size:12px;
    color:#64748b;
    padding:12px;
}

.finance-detail-table tbody td{
    padding:12px;
    border-bottom:1px solid #edf2f7;
}

.finance-order-link{
    color:#2563eb;
    font-weight:900;
    text-decoration:none;
}

.finance-order-link:hover{
    color:#1d4ed8;
    text-decoration:underline;
}

.finance-status{
    display:inline-flex;
    align-items:center;
    gap:8px;
    padding:8px 12px;
    border-radius:999px;
    font-size:12px;
    font-weight:900;
    line-height:1;
    white-space:nowrap;
    border:1px solid transparent;
    box-shadow:0 6px 16px rgba(15,23,42,.08);
}

.finance-status__dot{
    width:8px;
    height:8px;
    border-radius:999px;
    display:inline-block;
}

.finance-status--success{
    background:linear-gradient(180deg,#dcfce7,#bbf7d0);
    color:#047857;
    border-color:#86efac;
}

.finance-status--success .finance-status__dot{
    background:#10b981;
    box-shadow:0 0 0 4px rgba(16,185,129,.16);
}

.finance-status--danger{
    background:linear-gradient(180deg,#fee2e2,#fecaca);
    color:#b91c1c;
    border-color:#fca5a5;
}

.finance-status--danger .finance-status__dot{
    background:#ef4444;
    box-shadow:0 0 0 4px rgba(239,68,68,.16);
}

.finance-pagination-wrap{
    padding:18px 22px 22px;
    border-top:1px solid #eef3f8;
    background:#fff;
}

@media (max-width: 1400px){
    .finance-summary-row{
        grid-template-columns:repeat(2, minmax(0, 1fr));
    }
}

@media (max-width: 1200px){
    .finance-filter-grid{
        grid-template-columns:1fr 1fr;
    }
}

@media (max-width: 768px){
    .finance-modern-page{
        padding:16px;
    }

    .finance-modern-title{
        font-size:32px;
    }

    .finance-filter-grid{
        grid-template-columns:1fr;
    }

    .finance-summary-row{
        grid-template-columns:1fr;
    }

    .finance-overview-pill{
        min-width:100%;
    }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.finance-toggle-btn').forEach(function (button) {
        button.addEventListener('click', function () {
            const targetId = this.getAttribute('data-target');
            const row = document.getElementById(targetId);
            if (!row) return;

            const icon = this.querySelector('i');
            const isHidden = row.style.display === 'none' || row.style.display === '';

            row.style.display = isHidden ? 'table-row' : 'none';

            if (icon) {
                icon.className = isHidden ? 'bi bi-dash' : 'bi bi-plus';
            }
        });
    });
});
</script>
@endsection