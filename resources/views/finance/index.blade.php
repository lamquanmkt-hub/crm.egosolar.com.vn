
@php
    $egoPendingOrdersCount = $egoPendingOrdersCount ?? 0;
    $egoPendingMaterialRequestsCount = $egoPendingMaterialRequestsCount ?? 0;

    try {
        $egoPendingOrdersCount = (int) \Illuminate\Support\Facades\DB::table('crm_order_approvals')
            ->where('status', 'pending')
            ->distinct()
            ->count('order_id');

        $egoPendingMaterialRequestsCount = (int) \Illuminate\Support\Facades\DB::table('material_requests')
            ->whereIn('status', ['SUBMITTED', 'ADMIN_APPROVED'])
            ->count();
    } catch (\Throwable $e) {
        $egoPendingOrdersCount = 0;
        $egoPendingMaterialRequestsCount = 0;
    }
@endphp

@extends('layouts.app')

@section('content')
@php
    $money = function ($value) {
        return number_format((float)($value ?? 0), 0, ',', '.') . ' đ';
    };
@endphp

<div class="finance-exec-page">
    {{-- HERO --}}
    <section class="finance-hero">
        <div class="finance-hero__left">
            <div class="finance-chip">Executive Finance Dashboard</div>
            <h1 class="finance-title">Tổng quan tài chính</h1>
            <p class="finance-subtitle">
                Theo dõi doanh thu, giá vốn, lợi nhuận, công nợ và dòng tiền theo góc nhìn quản trị.
            </p>

            <div class="finance-hero-stats">
                <div class="finance-hero-stat">
                    <span>Tỷ lệ thu hồi</span>
                    <strong>{{ number_format($collectionRate ?? 0, 1) }}%</strong>
                </div>
                <div class="finance-hero-stat">
                    <span>Đơn hàng</span>
                    <strong>{{ number_format($totalOrders ?? 0) }}</strong>
                </div>
                <div class="finance-hero-stat">
                    <span>Phiếu chờ xử lý</span>
                    <strong>{{ number_format($pendingPaymentRequests ?? 0) }}</strong>
                </div>
            </div>
        </div>

        <div class="finance-hero__right">
            <a href="{{ route('finance.reports') }}" class="finance-btn finance-btn--ghost">
                <i class="bi bi-bar-chart-line"></i>
                <span>Xem báo cáo</span>
            </a>

            <a href="{{ route('finance.payment-request') }}" class="finance-btn finance-btn--primary">
                <i class="bi bi-send-check"></i>
                <span>Đề nghị thanh toán</span>
            </a>
        </div>
    </section>

    {{-- FILTER --}}
    <section class="finance-filter-card">
        <form method="GET" action="{{ route('finance.index') }}" class="finance-filter-form">
            <div class="finance-filter-grid">
                <div class="finance-field">
                    <label>Từ khóa</label>
                    <input
                        type="text"
                        name="keyword"
                        value="{{ $filters['keyword'] ?? '' }}"
                        placeholder="Mã đơn, tên khách hàng..."
                    >
                </div>

                <div class="finance-field">
                    <label>Từ ngày</label>
                    <input
                        type="date"
                        name="from_date"
                        value="{{ $filters['from_date'] ?? '' }}"
                    >
                </div>

                <div class="finance-field">
                    <label>Đến ngày</label>
                    <input
                        type="date"
                        name="to_date"
                        value="{{ $filters['to_date'] ?? '' }}"
                    >
                </div>

                <div class="finance-field">
                    <label>Trạng thái đơn</label>
                    <select name="order_status">
                        <option value="">-- Tất cả --</option>
                        @foreach($orderStatuses ?? [] as $status)
                            <option value="{{ $status }}" {{ (($filters['order_status'] ?? '') === $status) ? 'selected' : '' }}>
                                {{ $status }}
                            </option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="finance-filter-actions">
                <button type="submit" class="finance-btn finance-btn--primary finance-btn--sm">
                    <i class="bi bi-funnel"></i>
                    <span>Lọc dữ liệu</span>
                </button>

                <a href="{{ route('finance.index') }}" class="finance-btn finance-btn--light finance-btn--sm">
                    <i class="bi bi-arrow-counterclockwise"></i>
                    <span>Đặt lại</span>
                </a>
            </div>
        </form>
    </section>

    {{-- KHỐI 1: DOANH THU - GIÁ VỐN - LỢI NHUẬN --}}
    <section class="finance-section">
        <div class="finance-section__head">
            <div>
                <div class="finance-section__eyebrow">Profit Overview</div>
                <h3>Doanh thu - Giá vốn - Lợi nhuận</h3>
            </div>
        </div>

        <div class="finance-kpi-grid finance-kpi-grid--4">
            <article class="finance-kpi finance-kpi--blue">
                <div class="finance-kpi__top">
                    <span class="finance-kpi__label">Tổng doanh thu</span>
                    <i class="bi bi-graph-up-arrow"></i>
                </div>
                <div class="finance-kpi__value">{{ $money($totalRevenue ?? 0) }}</div>
                <div class="finance-kpi__foot">Tổng giá trị bán ra từ đơn hàng</div>
            </article>

            <article class="finance-kpi finance-kpi--amber">
                <div class="finance-kpi__top">
                    <span class="finance-kpi__label">Tổng giá vốn</span>
                    <i class="bi bi-box-seam"></i>
                </div>
                <div class="finance-kpi__value">{{ $money($totalCost ?? 0) }}</div>
                <div class="finance-kpi__foot">Tính theo số lượng × giá agent của sản phẩm</div>
            </article>

            <article class="finance-kpi finance-kpi--green">
                <div class="finance-kpi__top">
                    <span class="finance-kpi__label">Lợi nhuận gộp</span>
                    <i class="bi bi-cash-stack"></i>
                </div>
                <div class="finance-kpi__value {{ ($grossProfit ?? 0) >= 0 ? 'text-success' : 'text-danger' }}">
                    {{ $money($grossProfit ?? 0) }}
                </div>
                <div class="finance-kpi__foot">Doanh thu - Giá vốn</div>
            </article>

            <article class="finance-kpi finance-kpi--violet">
                <div class="finance-kpi__top">
                    <span class="finance-kpi__label">Biên lợi nhuận gộp</span>
                    <i class="bi bi-percent"></i>
                </div>
                <div class="finance-kpi__value">{{ number_format($grossMargin ?? 0, 2) }}%</div>
                <div class="finance-kpi__foot">Lợi nhuận gộp / Doanh thu</div>
            </article>
        </div>
    </section>

    {{-- KHỐI 2: CÔNG NỢ --}}
    <section class="finance-section">
        <div class="finance-section__head">
            <div>
                <div class="finance-section__eyebrow">Debt Overview</div>
                <h3>Công nợ phải thu</h3>
            </div>
        </div>

        <div class="finance-kpi-grid finance-kpi-grid--4">
            <article class="finance-kpi finance-kpi--slate">
                <div class="finance-kpi__top">
                    <span class="finance-kpi__label">Tổng phải thu</span>
                    <i class="bi bi-journal-text"></i>
                </div>
                <div class="finance-kpi__value">{{ $money($totalReceivableBase ?? 0) }}</div>
                <div class="finance-kpi__foot">Tổng công nợ gốc theo bảng debt</div>
            </article>

            <article class="finance-kpi finance-kpi--green">
                <div class="finance-kpi__top">
                    <span class="finance-kpi__label">Đã thu</span>
                    <i class="bi bi-wallet2"></i>
                </div>
                <div class="finance-kpi__value">{{ $money($collectedAmount ?? 0) }}</div>
                <div class="finance-kpi__foot">Tổng tiền đã thu theo debt</div>
            </article>

            <article class="finance-kpi finance-kpi--red">
                <div class="finance-kpi__top">
                    <span class="finance-kpi__label">Còn nợ</span>
                    <i class="bi bi-hourglass-split"></i>
                </div>
                <div class="finance-kpi__value">{{ $money($receivableAmount ?? 0) }}</div>
                <div class="finance-kpi__foot">Số dư nợ còn lại</div>
            </article>

            <article class="finance-kpi finance-kpi--amber">
                <div class="finance-kpi__top">
                    <span class="finance-kpi__label">Công nợ quá hạn</span>
                    <i class="bi bi-exclamation-triangle"></i>
                </div>
                <div class="finance-kpi__value">{{ $money($overdueReceivables ?? 0) }}</div>
                <div class="finance-kpi__foot">Các khoản overdue</div>
            </article>
        </div>
    </section>

    {{-- KHỐI 3: DÒNG TIỀN --}}
    <section class="finance-main-grid">
        <div class="finance-card">
            <div class="finance-card__head">
                <div>
                    <div class="finance-section__eyebrow">Cashflow Snapshot</div>
                    <h3>Dòng tiền theo bộ lọc</h3>
                </div>
                <span class="finance-badge {{ ($netCashFlow ?? 0) >= 0 ? 'finance-badge--success' : 'finance-badge--danger' }}">
                    {{ ($netCashFlow ?? 0) >= 0 ? 'Dương' : 'Âm' }}
                </span>
            </div>

            <div class="finance-summary-grid">
                <div class="finance-summary-box">
                    <span>Thu trong kỳ lọc</span>
                    <strong>{{ $money($cashInPeriod ?? 0) }}</strong>
                </div>

                <div class="finance-summary-box">
                    <span>Chi trong kỳ lọc</span>
                    <strong>{{ $money($cashOutPeriod ?? 0) }}</strong>
                </div>

                <div class="finance-summary-box">
                    <span>Dòng tiền ròng</span>
                    <strong class="{{ ($netCashFlow ?? 0) >= 0 ? 'text-success' : 'text-danger' }}">
                        {{ $money($netCashFlow ?? 0) }}
                    </strong>
                </div>

                <div class="finance-summary-box">
                    <span>Chi chờ duyệt / giải ngân</span>
                    <strong>{{ $money($pendingDisbursement ?? 0) }}</strong>
                </div>
            </div>
        </div>

        <div class="finance-card">
            <div class="finance-card__head">
                <div>
                    <div class="finance-section__eyebrow">Business Navigation</div>
                    <h3>Điều hướng tài chính</h3>
                </div>
            </div>

            <div class="finance-nav-grid">
                <a href="{{ route('finance.customer-debts.index') }}" class="finance-nav-item">
                    <i class="bi bi-people"></i>
                    <div>
                        <strong>Công nợ khách hàng</strong>
                        <span>Theo dõi khoản phải thu</span>
                    </div>
                </a>

                <a href="{{ route('finance.customer-debts.payment-history') }}" class="finance-nav-item">
                    <i class="bi bi-clock-history"></i>
                    <div>
                        <strong>Lịch sử thanh toán</strong>
                        <span>Xem các lần thu tiền</span>
                    </div>
                </a>

                <a href="{{ route('finance.payments.index') }}" class="finance-nav-item">
                    <i class="bi bi-credit-card-2-front"></i>
                    <div>
                        <strong>Phiếu chi</strong>
                        <span>Kiểm soát dòng tiền ra</span>
                    </div>
                </a>

                <a href="{{ route('finance.receipts.index') }}" class="finance-nav-item">
                    <i class="bi bi-cash-coin"></i>
                    <div>
                        <strong>Phiếu thu</strong>
                        <span>Kiểm soát dòng tiền vào</span>
                    </div>
                </a>

                <a href="{{ route('finance.accounts.index') }}" class="finance-nav-item">
                    <i class="bi bi-bank"></i>
                    <div>
                        <strong>Quỹ & Tài khoản</strong>
                        <span>Số dư theo quỹ / ngân hàng</span>
                    </div>
                </a>

                <a href="{{ route('finance.reports') }}" class="finance-nav-item">
                    <i class="bi bi-bar-chart"></i>
                    <div>
                        <strong>Báo cáo</strong>
                        <span>Xem biểu đồ và tổng hợp</span>
                    </div>
                </a>
            </div>
        </div>
    </section>

    {{-- BẢNG LỢI NHUẬN ĐƠN HÀNG --}}
    <section class="finance-card finance-mt-16">
        <div class="finance-card__head">
            <div>
                <div class="finance-section__eyebrow">Order Profit</div>
                <h3>Đơn hàng lợi nhuận gần đây</h3>
            </div>
        </div>

        <div class="finance-note">
            Bộ lọc phía trên sẽ áp dụng cho KPI, công nợ, dòng tiền và danh sách đơn hàng lợi nhuận.
        </div>

        <div class="finance-table-wrap">
            <table class="finance-table">
                <thead>
                    <tr>
                        <th>Mã đơn</th>
                        <th>Ngày</th>
                        <th>Doanh thu</th>
                        <th>Giá vốn</th>
                        <th>Lợi nhuận gộp</th>
                        <th>Biên lợi nhuận</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($recentOrderProfits ?? [] as $row)
                        <tr>
                            <td><strong>{{ $row->order_code ?? ('#' . ($row->order_id ?? '')) }}</strong></td>
                            <td>
                                {{ !empty($row->order_date) ? \Carbon\Carbon::parse($row->order_date)->format('d/m/Y') : '--' }}
                            </td>
                            <td>{{ $money($row->sale_amount ?? 0) }}</td>
                            <td>{{ $money($row->cost_amount ?? 0) }}</td>
                            <td class="{{ ($row->gross_profit ?? 0) >= 0 ? 'text-success' : 'text-danger' }}">
                                {{ $money($row->gross_profit ?? 0) }}
                            </td>
                            <td>
                                <span class="finance-margin {{ ($row->margin_percent ?? 0) >= 0 ? 'finance-margin--good' : 'finance-margin--bad' }}">
                                    {{ number_format((float)($row->margin_percent ?? 0), 2) }}%
                                </span>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="finance-empty">
                                Không có dữ liệu phù hợp với bộ lọc.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
</div>

<style>
:root{
    --bg:#f4f7fb;
    --card:#ffffff;
    --line:#e7edf5;
    --text:#0f172a;
    --muted:#64748b;
    --muted2:#94a3b8;
    --blue:#2563eb;
    --green:#16a34a;
    --red:#dc2626;
    --amber:#d97706;
    --violet:#7c3aed;
    --slate:#475569;
    --shadow:0 12px 28px rgba(15,23,42,.06);
}

.finance-exec-page{
    padding:18px;
    background:var(--bg);
}

.finance-hero{
    display:flex;
    justify-content:space-between;
    gap:18px;
    padding:22px 24px;
    border-radius:24px;
    background:
        radial-gradient(circle at top right, rgba(59,130,246,.22), transparent 28%),
        linear-gradient(135deg,#071224 0%,#0b1730 45%,#16346e 100%);
    color:#fff;
    box-shadow:0 18px 42px rgba(2,6,23,.18);
    margin-bottom:16px;
}

.finance-hero__left{
    flex:1 1 auto;
}

.finance-chip{
    display:inline-flex;
    align-items:center;
    padding:5px 10px;
    border-radius:999px;
    background:rgba(255,255,255,.08);
    border:1px solid rgba(255,255,255,.14);
    color:#dbeafe;
    font-size:11px;
    font-weight:800;
    letter-spacing:.08em;
    text-transform:uppercase;
    margin-bottom:10px;
}

.finance-title{
    margin:0;
    font-size:34px;
    line-height:1.05;
    font-weight:800;
    letter-spacing:-.03em;
}

.finance-subtitle{
    margin:10px 0 0;
    color:rgba(255,255,255,.78);
    font-size:14px;
    line-height:1.6;
    max-width:760px;
}

.finance-hero-stats{
    display:grid;
    grid-template-columns:repeat(3,minmax(0,1fr));
    gap:10px;
    margin-top:18px;
}

.finance-hero-stat{
    padding:12px 14px;
    border-radius:14px;
    background:rgba(255,255,255,.06);
    border:1px solid rgba(255,255,255,.10);
}

.finance-hero-stat span{
    display:block;
    color:rgba(255,255,255,.66);
    font-size:12px;
    margin-bottom:6px;
}

.finance-hero-stat strong{
    font-size:20px;
    font-weight:800;
    color:#fff;
}

.finance-hero__right{
    display:flex;
    flex-direction:column;
    gap:10px;
    min-width:210px;
}

.finance-btn{
    display:inline-flex;
    align-items:center;
    justify-content:center;
    gap:8px;
    padding:11px 14px;
    border-radius:12px;
    font-size:14px;
    text-decoration:none;
    font-weight:700;
    transition:.2s ease;
    border:1px solid transparent;
}

.finance-btn:hover{
    transform:translateY(-1px);
}

.finance-btn--ghost{
    color:#fff;
    background:rgba(255,255,255,.08);
    border-color:rgba(255,255,255,.14);
}

.finance-btn--ghost:hover{
    color:#fff;
}

.finance-btn--primary{
    color:#fff;
    background:linear-gradient(135deg,#3b82f6,#2563eb);
    box-shadow:0 12px 24px rgba(37,99,235,.22);
}

.finance-btn--primary:hover{
    color:#fff;
}

.finance-btn--light{
    color:var(--text);
    background:#fff;
    border-color:var(--line);
}

.finance-btn--light:hover{
    color:var(--text);
}

.finance-btn--sm{
    padding:10px 13px;
    font-size:13px;
}

.finance-filter-card{
    background:var(--card);
    border:1px solid var(--line);
    border-radius:18px;
    padding:16px;
    box-shadow:var(--shadow);
    margin-bottom:16px;
}

.finance-filter-form{
    display:flex;
    flex-direction:column;
    gap:14px;
}

.finance-filter-grid{
    display:grid;
    grid-template-columns:2fr 1fr 1fr 1fr;
    gap:12px;
}

.finance-field label{
    display:block;
    font-size:12px;
    font-weight:700;
    color:var(--muted);
    margin-bottom:6px;
}

.finance-field input,
.finance-field select{
    width:100%;
    height:42px;
    border:1px solid #dbe4ee;
    border-radius:12px;
    padding:0 12px;
    font-size:13px;
    color:var(--text);
    background:#fff;
    outline:none;
}

.finance-field input:focus,
.finance-field select:focus{
    border-color:#93c5fd;
    box-shadow:0 0 0 3px rgba(59,130,246,.10);
}

.finance-filter-actions{
    display:flex;
    gap:10px;
    flex-wrap:wrap;
}

.finance-section{
    margin-bottom:16px;
}

.finance-section__head{
    display:flex;
    justify-content:space-between;
    align-items:flex-end;
    gap:12px;
    margin-bottom:10px;
}

.finance-section__eyebrow{
    font-size:11px;
    color:var(--muted2);
    font-weight:800;
    letter-spacing:.08em;
    text-transform:uppercase;
    margin-bottom:4px;
}

.finance-section__head h3{
    margin:0;
    font-size:20px;
    font-weight:800;
    color:var(--text);
}

.finance-kpi-grid{
    display:grid;
    gap:14px;
}

.finance-kpi-grid--4{
    grid-template-columns:repeat(4,minmax(0,1fr));
}

.finance-kpi{
    background:var(--card);
    border:1px solid var(--line);
    border-radius:18px;
    padding:16px;
    box-shadow:var(--shadow);
    position:relative;
    overflow:hidden;
}

.finance-kpi::after{
    content:"";
    position:absolute;
    right:-20px;
    bottom:-20px;
    width:90px;
    height:90px;
    border-radius:50%;
    opacity:.08;
}

.finance-kpi--blue::after{ background:var(--blue); }
.finance-kpi--green::after{ background:var(--green); }
.finance-kpi--red::after{ background:var(--red); }
.finance-kpi--amber::after{ background:var(--amber); }
.finance-kpi--violet::after{ background:var(--violet); }
.finance-kpi--slate::after{ background:var(--slate); }

.finance-kpi__top{
    display:flex;
    justify-content:space-between;
    align-items:center;
    margin-bottom:12px;
}

.finance-kpi__label{
    color:var(--muted);
    font-size:13px;
    font-weight:600;
}

.finance-kpi__top i{
    color:var(--muted2);
    font-size:18px;
}

.finance-kpi__value{
    color:var(--text);
    font-size:24px;
    font-weight:800;
    line-height:1.15;
    margin-bottom:8px;
    letter-spacing:-.02em;
}

.finance-kpi__foot{
    color:var(--muted);
    font-size:12px;
    line-height:1.5;
}

.finance-main-grid{
    display:grid;
    grid-template-columns:1.05fr .95fr;
    gap:14px;
    margin-bottom:14px;
}

.finance-card{
    background:var(--card);
    border:1px solid var(--line);
    border-radius:20px;
    padding:18px;
    box-shadow:var(--shadow);
}

.finance-card__head{
    display:flex;
    justify-content:space-between;
    align-items:flex-start;
    gap:12px;
    margin-bottom:14px;
}

.finance-card__head h3{
    margin:0;
    font-size:20px;
    color:var(--text);
    font-weight:800;
}

.finance-badge{
    display:inline-flex;
    align-items:center;
    padding:7px 10px;
    border-radius:999px;
    font-size:11px;
    font-weight:800;
    border:1px solid var(--line);
    white-space:nowrap;
}

.finance-badge--success{
    background:rgba(22,163,74,.10);
    color:var(--green);
    border-color:rgba(22,163,74,.16);
}

.finance-badge--danger{
    background:rgba(220,38,38,.10);
    color:var(--red);
    border-color:rgba(220,38,38,.16);
}

.finance-summary-grid{
    display:grid;
    grid-template-columns:repeat(2,minmax(0,1fr));
    gap:12px;
}

.finance-summary-box{
    padding:14px;
    border-radius:14px;
    background:#f8fafc;
    border:1px solid #edf2f7;
}

.finance-summary-box span{
    display:block;
    color:var(--muted);
    font-size:12px;
    margin-bottom:6px;
}

.finance-summary-box strong{
    color:var(--text);
    font-size:18px;
    font-weight:800;
}

.finance-nav-grid{
    display:grid;
    grid-template-columns:repeat(2,minmax(0,1fr));
    gap:10px;
}

.finance-nav-item{
    display:flex;
    align-items:flex-start;
    gap:12px;
    padding:14px;
    border-radius:14px;
    background:#fbfdff;
    border:1px solid var(--line);
    text-decoration:none;
    color:inherit;
    transition:.2s ease;
}

.finance-nav-item:hover{
    transform:translateY(-1px);
    box-shadow:0 10px 22px rgba(15,23,42,.06);
    color:inherit;
}

.finance-nav-item i{
    width:38px;
    height:38px;
    flex:0 0 auto;
    border-radius:12px;
    display:flex;
    align-items:center;
    justify-content:center;
    background:#eff6ff;
    color:var(--blue);
    font-size:16px;
}

.finance-nav-item strong{
    display:block;
    font-size:14px;
    font-weight:800;
    color:var(--text);
    margin-bottom:3px;
}

.finance-nav-item span{
    color:var(--muted);
    font-size:12px;
    line-height:1.5;
}

.finance-note{
    margin-bottom:12px;
    padding:12px 14px;
    border-radius:12px;
    background:#f8fafc;
    border:1px solid #edf2f7;
    color:var(--muted);
    font-size:13px;
    line-height:1.6;
}

.finance-table-wrap{
    overflow-x:auto;
}

.finance-table{
    width:100%;
    border-collapse:separate;
    border-spacing:0;
    min-width:860px;
}

.finance-table thead th{
    text-align:left;
    padding:12px;
    font-size:12px;
    font-weight:800;
    text-transform:uppercase;
    letter-spacing:.04em;
    color:var(--muted2);
    border-bottom:1px solid var(--line);
}

.finance-table tbody td{
    padding:12px;
    font-size:13px;
    color:var(--text);
    border-bottom:1px solid #eef2f7;
    vertical-align:middle;
}

.finance-table tbody tr:hover{
    background:#fafcff;
}

.finance-empty{
    text-align:center;
    color:var(--muted);
    padding:22px !important;
}

.finance-margin{
    display:inline-flex;
    align-items:center;
    padding:6px 9px;
    border-radius:999px;
    font-size:12px;
    font-weight:800;
}

.finance-margin--good{
    background:rgba(22,163,74,.10);
    color:var(--green);
}

.finance-margin--bad{
    background:rgba(220,38,38,.10);
    color:var(--red);
}

.finance-mt-16{
    margin-top:16px;
}

.text-success{
    color:var(--green) !important;
}

.text-danger{
    color:var(--red) !important;
}

@media (max-width: 1400px){
    .finance-kpi-grid--4{
        grid-template-columns:repeat(2,minmax(0,1fr));
    }

    .finance-main-grid{
        grid-template-columns:1fr;
    }

    .finance-filter-grid{
        grid-template-columns:repeat(2,minmax(0,1fr));
    }
}

@media (max-width: 991px){
    .finance-exec-page{
        padding:14px;
    }

    .finance-hero{
        flex-direction:column;
        padding:18px;
    }

    .finance-title{
        font-size:28px;
    }

    .finance-hero-stats,
    .finance-kpi-grid--4,
    .finance-summary-grid,
    .finance-nav-grid,
    .finance-filter-grid{
        grid-template-columns:1fr;
    }

    .finance-hero__right{
        min-width:unset;
        width:100%;
    }
}
</style>

@endsection