@extends('layouts.app')

@section('content')
@php
    $summary = $summary ?? [];

    $accounts = $accounts ?? collect();
    $budgets = $budgets ?? collect();
    $receiptsByCategory = $receiptsByCategory ?? collect();
    $paymentsByCategory = $paymentsByCategory ?? collect();

    $money = function ($value) {
        return number_format((float) ($value ?? 0), 0, ',', '.') . ' đ';
    };

    $percent = function ($value) {
        return number_format((float) ($value ?? 0), 1, ',', '.') . '%';
    };

    $netCashFlow = $summary['net_cash_flow'] ?? 0;
    $budgetUsageRate = $summary['budget_usage_rate'] ?? 0;
    $safeBudgetRate = min(max((float) $budgetUsageRate, 0), 100);

    $paymentLabels = $paymentsByCategory->pluck('category')->map(function ($v) {
        return $v ?: 'Chưa phân loại';
    })->values();

    $paymentAmounts = $paymentsByCategory->pluck('total_amount')->map(function ($v) {
        return (float) $v;
    })->values();

    $receiptLabels = $receiptsByCategory->pluck('category')->map(function ($v) {
        return $v ?: 'Chưa phân loại';
    })->values();

    $receiptAmounts = $receiptsByCategory->pluck('total_amount')->map(function ($v) {
        return (float) $v;
    })->values();

    $budgetLabels = $budgets->pluck('category')->map(function ($v) {
        return $v ?: 'Chưa phân loại';
    })->values();

    $budgetAmounts = $budgets->pluck('budget_amount')->map(function ($v) {
        return (float) $v;
    })->values();
@endphp

<style>
    .finance-report-page {
        padding: 18px;
        background:
            radial-gradient(circle at top left, rgba(59, 130, 246, .08), transparent 30%),
            radial-gradient(circle at top right, rgba(16, 185, 129, .08), transparent 26%),
            #f5f7fb;
        min-height: 100%;
        overflow-x: hidden;
    }

    .fr-hero {
        position: relative;
        overflow: hidden;
        border-radius: 30px;
        padding: 30px;
        color: #fff;
        background:
            radial-gradient(circle at top right, rgba(56,189,248,.38), transparent 36%),
            linear-gradient(135deg, #071124 0%, #102a63 52%, #2563eb 100%);
        box-shadow: 0 24px 60px rgba(37, 99, 235, .22);
    }

    .fr-hero:after {
        content: "";
        position: absolute;
        width: 360px;
        height: 360px;
        border-radius: 999px;
        right: -150px;
        bottom: -160px;
        background: rgba(255,255,255,.08);
    }

    .fr-hero-inner {
        position: relative;
        z-index: 2;
    }

    .fr-pill {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 8px 13px;
        border-radius: 999px;
        background: rgba(255,255,255,.13);
        border: 1px solid rgba(255,255,255,.22);
        font-size: 12px;
        font-weight: 900;
        letter-spacing: .04em;
    }

    .fr-title {
        font-size: clamp(28px, 3vw, 42px);
        font-weight: 900;
        letter-spacing: -.04em;
        margin: 14px 0 8px;
    }

    .fr-subtitle {
        max-width: 840px;
        opacity: .78;
        margin: 0;
    }

    .fr-filter {
        margin-top: 22px;
        padding: 16px;
        border-radius: 22px;
        background: rgba(255,255,255,.11);
        border: 1px solid rgba(255,255,255,.18);
    }

    .fr-filter label {
        font-size: 12px;
        font-weight: 800;
        color: rgba(255,255,255,.76);
        margin-bottom: 7px;
    }

    .fr-filter .form-control {
        border: 0;
        border-radius: 15px;
        min-height: 45px;
    }

    .fr-card {
        background: #fff;
        border: 1px solid rgba(15,23,42,.06);
        border-radius: 24px;
        box-shadow: 0 16px 40px rgba(15,23,42,.065);
    }

    .fr-stat {
        height: 100%;
        padding: 20px;
        overflow: hidden;
        position: relative;
    }

    .fr-stat:after {
        content: "";
        position: absolute;
        width: 100px;
        height: 100px;
        border-radius: 999px;
        right: -34px;
        bottom: -36px;
        background: #eff6ff;
    }

    .fr-stat-content {
        position: relative;
        z-index: 2;
    }

    .fr-label {
        color: #64748b;
        font-size: 13px;
        font-weight: 800;
        margin-bottom: 6px;
    }

    .fr-number {
        color: #0f172a;
        font-size: 25px;
        font-weight: 900;
        letter-spacing: -.04em;
        margin-bottom: 4px;
    }

    .fr-hint {
        color: #64748b;
        font-size: 12px;
    }

    .fr-icon {
        width: 46px;
        height: 46px;
        border-radius: 17px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 21px;
        background: #eff6ff;
        color: #2563eb;
    }

    .fr-icon.green { background: #dcfce7; color: #16a34a; }
    .fr-icon.red { background: #fee2e2; color: #dc2626; }
    .fr-icon.amber { background: #fef3c7; color: #d97706; }
    .fr-icon.purple { background: #ede9fe; color: #7c3aed; }

    .fr-section-title {
        color: #0f172a;
        font-size: 18px;
        font-weight: 900;
        margin: 0;
    }

    .fr-muted {
        color: #64748b;
        font-size: 13px;
    }

    .fr-progress {
        height: 10px;
        border-radius: 999px;
        background: #eaf0f8;
        overflow: hidden;
    }

    .fr-progress span {
        display: block;
        height: 100%;
        border-radius: 999px;
        background: linear-gradient(90deg, #2563eb, #38bdf8);
    }

    .fr-progress span.success { background: linear-gradient(90deg, #16a34a, #86efac); }
    .fr-progress span.warning { background: linear-gradient(90deg, #f59e0b, #fde68a); }
    .fr-progress span.danger { background: linear-gradient(90deg, #dc2626, #fb7185); }

    .fr-table {
        margin-bottom: 0;
    }

    .fr-table thead th {
        color: #64748b;
        font-size: 12px;
        font-weight: 900;
        text-transform: uppercase;
        letter-spacing: .04em;
        border-bottom: 1px solid #e2e8f0;
        white-space: nowrap;
    }

    .fr-table tbody td {
        vertical-align: middle;
        border-bottom: 1px solid #eef2f7;
    }

    .fr-badge {
        display: inline-flex;
        align-items: center;
        gap: 7px;
        border-radius: 999px;
        padding: 7px 11px;
        font-size: 12px;
        font-weight: 900;
        white-space: nowrap;
    }

    .fr-badge.success { background: #dcfce7; color: #166534; }
    .fr-badge.warning { background: #fef3c7; color: #92400e; }
    .fr-badge.danger { background: #fee2e2; color: #991b1b; }
    .fr-badge.primary { background: #dbeafe; color: #1d4ed8; }

    .fr-chart {
        height: 300px;
    }

    .fr-empty {
        border: 1px dashed #cbd5e1;
        border-radius: 20px;
        padding: 28px;
        background: #f8fafc;
        color: #64748b;
        text-align: center;
    }

    .fr-quick {
        display: block;
        text-decoration: none;
        padding: 14px;
        border-radius: 18px;
        border: 1px solid #e2e8f0;
        background: #f8fafc;
        color: #0f172a;
        transition: all .18s ease;
        margin-bottom: 12px;
    }

    .fr-quick:hover {
        transform: translateY(-2px);
        border-color: #bfdbfe;
        background: #eff6ff;
        color: #1d4ed8;
    }

    @media print {
        .ego-sidebar,
        .ego-topbar,
        .fr-filter,
        .fr-print-hide,
        #chat-widget,
        .chat-widget {
            display: none !important;
        }

        .finance-report-page {
            padding: 0;
            background: #fff;
        }

        .fr-card,
        .fr-hero {
            box-shadow: none;
        }
    }

    @media (max-width: 767.98px) {
        .finance-report-page {
            padding: 12px;
        }

        .fr-hero {
            padding: 22px;
            border-radius: 22px;
        }

        .fr-chart {
            height: 240px;
        }
    }
</style>

<div class="finance-report-page">
    {{-- HERO --}}
    <div class="fr-hero mb-4">
        <div class="fr-hero-inner">
            <div class="d-flex flex-column flex-xl-row justify-content-between gap-3">
                <div>
                    <div class="fr-pill">
                        <i class="bi bi-graph-up-arrow"></i>
                        FINANCE REPORT CENTER
                    </div>

                    <h1 class="fr-title">Báo cáo tài chính</h1>

                    <p class="fr-subtitle">
                        Tổng hợp doanh thu, chi phí, dòng tiền, ngân sách, đề nghị thanh toán và công nợ
                        theo tháng để theo dõi tình hình tài chính nhanh hơn.
                    </p>
                </div>

                <div class="d-flex flex-wrap gap-2 align-items-start justify-content-xl-end fr-print-hide">
                    <a href="{{ route('finance.index') }}" class="btn btn-light rounded-pill px-4 fw-bold">
                        <i class="bi bi-speedometer2 me-1"></i>
                        Tổng quan
                    </a>

                    <a href="{{ route('finance.budget') }}" class="btn btn-outline-light rounded-pill px-4 fw-bold">
                        <i class="bi bi-wallet2 me-1"></i>
                        Ngân sách
                    </a>

                    <button type="button" onclick="window.print()" class="btn btn-warning rounded-pill px-4 fw-bold">
                        <i class="bi bi-printer me-1"></i>
                        In báo cáo
                    </button>
                </div>
            </div>

            <form method="GET" action="{{ route('finance.reports') }}" class="fr-filter fr-print-hide">
                <div class="row g-3 align-items-end">
                    <div class="col-lg-4">
                        <label>Tháng báo cáo</label>
                        <input type="month" name="month" class="form-control"
                               value="{{ $month ?? now()->format('Y-m') }}">
                    </div>

                    <div class="col-lg-5">
                        <label>Khoảng dữ liệu</label>
                        <input type="text" class="form-control"
                               value="{{ isset($monthStart) ? date('d/m/Y', strtotime($monthStart)) : '' }} - {{ isset($monthEnd) ? date('d/m/Y', strtotime($monthEnd)) : '' }}"
                               readonly>
                    </div>

                    <div class="col-lg-3 d-grid">
                        <button class="btn btn-warning rounded-pill fw-bold">
                            <i class="bi bi-funnel me-1"></i>
                            Lọc báo cáo
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    {{-- KPI --}}
    <div class="row g-4 mb-4">
        <div class="col-md-6 col-xl-3">
            <div class="fr-card fr-stat">
                <div class="fr-stat-content d-flex justify-content-between align-items-start">
                    <div>
                        <div class="fr-label">Tổng thu</div>
                        <div class="fr-number text-success">{{ $money($summary['total_receipts'] ?? 0) }}</div>
                        <div class="fr-hint">{{ $summary['receipt_count'] ?? 0 }} phiếu thu trong tháng</div>
                    </div>
                    <div class="fr-icon green">
                        <i class="bi bi-arrow-down-circle"></i>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-6 col-xl-3">
            <div class="fr-card fr-stat">
                <div class="fr-stat-content d-flex justify-content-between align-items-start">
                    <div>
                        <div class="fr-label">Tổng chi</div>
                        <div class="fr-number text-danger">{{ $money($summary['total_payments'] ?? 0) }}</div>
                        <div class="fr-hint">{{ $summary['payment_count'] ?? 0 }} phiếu chi trong tháng</div>
                    </div>
                    <div class="fr-icon red">
                        <i class="bi bi-arrow-up-circle"></i>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-6 col-xl-3">
            <div class="fr-card fr-stat">
                <div class="fr-stat-content d-flex justify-content-between align-items-start">
                    <div>
                        <div class="fr-label">Dòng tiền ròng</div>
                        <div class="fr-number {{ $netCashFlow >= 0 ? 'text-success' : 'text-danger' }}">
                            {{ $money($netCashFlow) }}
                        </div>
                        <div class="fr-hint">Tổng thu - Tổng chi</div>
                    </div>
                    <div class="fr-icon {{ $netCashFlow >= 0 ? 'green' : 'red' }}">
                        <i class="bi bi-activity"></i>
                    </div>
                </div>
            </div>
        </div>

        

    </div>

    {{-- KPI 2 --}}
    <div class="row g-4 mb-4">
        <div class="col-md-6 col-xl-3">
            <div class="fr-card fr-stat">
                <div class="fr-label">Ngân sách tháng</div>
                <div class="fr-number">{{ $money($summary['total_budget'] ?? 0) }}</div>
                <div class="fr-hint">{{ $summary['budget_count'] ?? 0 }} hạng mục ngân sách</div>

                <div class="fr-progress mt-3">
                    <span class="{{ $safeBudgetRate >= 100 ? 'danger' : ($safeBudgetRate >= 80 ? 'warning' : 'success') }}"
                          style="width: {{ $safeBudgetRate }}%"></span>
                </div>
            </div>
        </div>

        <div class="col-md-6 col-xl-3">
            <div class="fr-card fr-stat">
                <div class="fr-label">Còn lại so với ngân sách</div>
                <div class="fr-number {{ ($summary['budget_remain'] ?? 0) < 0 ? 'text-danger' : 'text-success' }}">
                    {{ $money($summary['budget_remain'] ?? 0) }}
                </div>
                <div class="fr-hint">Tỷ lệ dùng: {{ $percent($summary['budget_usage_rate'] ?? 0) }}</div>
            </div>
        </div>

        <div class="col-md-6 col-xl-3">
            <div class="fr-card fr-stat">
                <div class="fr-label">Đề nghị chờ xử lý</div>
                <div class="fr-number">{{ $summary['pending_requests_count'] ?? 0 }}</div>
                <div class="fr-hint">{{ $money($summary['pending_requests_amount'] ?? 0) }}</div>
            </div>
        </div>

        <div class="col-md-6 col-xl-3">
            <div class="fr-card fr-stat">
                <div class="fr-label">Công nợ còn lại</div>
                <div class="fr-number text-danger">{{ $money($summary['customer_remain_total'] ?? 0) }}</div>
                <div class="fr-hint">Tổng đơn hàng - đã thanh toán</div>
            </div>
        </div>
    </div>

    <div class="row g-4">
        {{-- MAIN LEFT --}}
        <div class="col-xl-8">
            <div class="fr-card p-4 mb-4">
                <div class="d-flex flex-column flex-md-row justify-content-between gap-2 mb-3">
                    <div>
                        <h3 class="fr-section-title">Biểu đồ thu - chi - ngân sách</h3>
                        <div class="fr-muted">So sánh nhanh dữ liệu tài chính trong tháng {{ $month ?? now()->format('Y-m') }}.</div>
                    </div>

                    <span class="fr-badge primary">
                        <i class="bi bi-calendar3"></i>
                        {{ $month ?? now()->format('Y-m') }}
                    </span>
                </div>

                <div class="fr-chart">
                    <canvas id="financeSummaryChart"></canvas>
                </div>
            </div>

            <div class="row g-4 mb-4">
                <div class="col-lg-6">
                    <div class="fr-card p-4 h-100">
                        <div class="mb-3">
                            <h3 class="fr-section-title">Thu theo hạng mục</h3>
                            <div class="fr-muted">Tổng hợp từ phiếu thu.</div>
                        </div>

                        @if($receiptsByCategory->count())
                            <div class="table-responsive">
                                <table class="table fr-table align-middle">
                                    <thead>
                                        <tr>
                                            <th>Hạng mục</th>
                                            <th>Số phiếu</th>
                                            <th class="text-end">Tổng thu</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($receiptsByCategory as $item)
                                            <tr>
                                                <td><strong>{{ $item->category ?: 'Chưa phân loại' }}</strong></td>
                                                <td>{{ $item->total_count ?? 0 }}</td>
                                                <td class="text-end fw-bold text-success">{{ $money($item->total_amount ?? 0) }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @else
                            <div class="fr-empty">
                                <i class="bi bi-inbox fs-1 d-block mb-2"></i>
                                Chưa có dữ liệu phiếu thu trong tháng này.
                            </div>
                        @endif
                    </div>
                </div>

                <div class="col-lg-6">
                    <div class="fr-card p-4 h-100">
                        <div class="mb-3">
                            <h3 class="fr-section-title">Chi theo hạng mục</h3>
                            <div class="fr-muted">Tổng hợp từ phiếu chi.</div>
                        </div>

                        @if($paymentsByCategory->count())
                            <div class="table-responsive">
                                <table class="table fr-table align-middle">
                                    <thead>
                                        <tr>
                                            <th>Hạng mục</th>
                                            <th>Số phiếu</th>
                                            <th class="text-end">Tổng chi</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($paymentsByCategory as $item)
                                            <tr>
                                                <td><strong>{{ $item->category ?: 'Chưa phân loại' }}</strong></td>
                                                <td>{{ $item->total_count ?? 0 }}</td>
                                                <td class="text-end fw-bold text-danger">{{ $money($item->total_amount ?? 0) }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @else
                            <div class="fr-empty">
                                <i class="bi bi-receipt fs-1 d-block mb-2"></i>
                                Chưa có dữ liệu phiếu chi trong tháng này.
                            </div>
                        @endif
                    </div>
                </div>
            </div>

            <div class="fr-card p-4">
                <div class="d-flex flex-column flex-md-row justify-content-between gap-2 mb-3">
                    <div>
                        <h3 class="fr-section-title">Ngân sách trong tháng</h3>
                        <div class="fr-muted">Lấy từ bảng ngân sách tài chính bạn vừa tạo.</div>
                    </div>

                    <a href="{{ route('finance.budget', ['month' => $month ?? now()->format('Y-m')]) }}"
                       class="btn btn-sm btn-primary rounded-pill px-3 fw-bold fr-print-hide">
                        Quản lý ngân sách
                    </a>
                </div>

                @if($budgets->count())
                    <div class="table-responsive">
                        <table class="table fr-table align-middle">
                            <thead>
                                <tr>
                                    <th>Hạng mục</th>
                                    <th>Ngân sách</th>
                                    <th>Ghi chú</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($budgets as $item)
                                    <tr>
                                        <td><strong>{{ $item->category }}</strong></td>
                                        <td class="fw-bold">{{ $money($item->budget_amount ?? 0) }}</td>
                                        <td class="fr-muted">{{ $item->note ?: '-' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <div class="fr-empty">
                        <i class="bi bi-wallet2 fs-1 d-block mb-2"></i>
                        Chưa có ngân sách cho tháng này.
                    </div>
                @endif
            </div>
        </div>

        {{-- RIGHT --}}
        <div class="col-xl-4">
            <div class="fr-card p-4 mb-4">
                <div class="d-flex justify-content-between align-items-start mb-3">
                    <div>
                        <h3 class="fr-section-title">Cơ cấu chi phí</h3>
                        <div class="fr-muted">Biểu đồ theo hạng mục chi.</div>
                    </div>
                    <div class="fr-icon red">
                        <i class="bi bi-pie-chart"></i>
                    </div>
                </div>

                <div class="fr-chart">
                    <canvas id="paymentPieChart"></canvas>
                </div>
            </div>

            <div class="fr-card p-4 mb-4">
                <h3 class="fr-section-title mb-3">Tóm tắt công nợ</h3>

                <div class="d-flex justify-content-between border-bottom py-2">
                    <span class="fr-muted">Tổng giá trị đơn hàng</span>
                    <strong>{{ $money($summary['customer_debt_total'] ?? 0) }}</strong>
                </div>

                <div class="d-flex justify-content-between border-bottom py-2">
                    <span class="fr-muted">Đã thanh toán</span>
                    <strong class="text-success">{{ $money($summary['customer_paid_total'] ?? 0) }}</strong>
                </div>

                <div class="d-flex justify-content-between py-2">
                    <span class="fr-muted">Còn phải thu</span>
                    <strong class="text-danger">{{ $money($summary['customer_remain_total'] ?? 0) }}</strong>
                </div>
            </div>

            <div class="fr-card p-4 mb-4">
                <h3 class="fr-section-title mb-3">Đề nghị thanh toán</h3>

                <div class="d-flex justify-content-between border-bottom py-2">
                    <span class="fr-muted">Chờ xử lý</span>
                    <strong>{{ $summary['pending_requests_count'] ?? 0 }} phiếu</strong>
                </div>

                <div class="d-flex justify-content-between border-bottom py-2">
                    <span class="fr-muted">Giá trị chờ xử lý</span>
                    <strong class="text-warning">{{ $money($summary['pending_requests_amount'] ?? 0) }}</strong>
                </div>

                <div class="d-flex justify-content-between border-bottom py-2">
                    <span class="fr-muted">Đã duyệt trong tháng</span>
                    <strong>{{ $summary['approved_requests_count'] ?? 0 }} phiếu</strong>
                </div>

                <div class="d-flex justify-content-between py-2">
                    <span class="fr-muted">Giá trị đã duyệt</span>
                    <strong class="text-success">{{ $money($summary['approved_requests_amount'] ?? 0) }}</strong>
                </div>
            </div>

            <div class="fr-card p-4">
                <h3 class="fr-section-title mb-3">Đi nhanh</h3>

                <a href="{{ route('finance.index') }}" class="fr-quick">
                    <strong><i class="bi bi-speedometer2 me-2"></i>Tổng quan tài chính</strong>
                    <div class="fr-muted mt-1">Quay lại dashboard tổng quan.</div>
                </a>

                <a href="{{ route('finance.budget') }}" class="fr-quick">
                    <strong><i class="bi bi-wallet2 me-2"></i>Ngân sách</strong>
                    <div class="fr-muted mt-1">Tạo và quản lý ngân sách tháng.</div>
                </a>

                <a href="{{ route('payment_requests.index') }}" class="fr-quick mb-0">
                    <strong><i class="bi bi-receipt me-2"></i>Đề nghị thanh toán</strong>
                    <div class="fr-muted mt-1">Xem các đề nghị đang chờ duyệt.</div>
                </a>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const moneyFormat = function(value) {
        return Number(value || 0).toLocaleString('vi-VN') + ' đ';
    };

    const summaryCtx = document.getElementById('financeSummaryChart');

    if (summaryCtx && typeof Chart !== 'undefined') {
        new Chart(summaryCtx, {
            type: 'bar',
            data: {
                labels: ['Tổng thu', 'Tổng chi', 'Ngân sách', 'Dòng tiền ròng'],
                datasets: [{
                    label: 'Số tiền',
                    data: [
                        {{ (float) ($summary['total_receipts'] ?? 0) }},
                        {{ (float) ($summary['total_payments'] ?? 0) }},
                        {{ (float) ($summary['total_budget'] ?? 0) }},
                        {{ (float) ($summary['net_cash_flow'] ?? 0) }}
                    ],
                    borderWidth: 1,
                    borderRadius: 14
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return moneyFormat(context.raw);
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return moneyFormat(value);
                            }
                        }
                    }
                }
            }
        });
    }

    const pieCtx = document.getElementById('paymentPieChart');

    if (pieCtx && typeof Chart !== 'undefined') {
        const labels = @json($paymentLabels);
        const data = @json($paymentAmounts);

        new Chart(pieCtx, {
            type: 'doughnut',
            data: {
                labels: labels.length ? labels : ['Chưa có dữ liệu'],
                datasets: [{
                    data: data.length ? data : [1],
                    borderWidth: 2
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '62%',
                plugins: {
                    legend: {
                        position: 'bottom'
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                if (!data.length) {
                                    return 'Chưa có dữ liệu';
                                }

                                return context.label + ': ' + moneyFormat(context.raw);
                            }
                        }
                    }
                }
            }
        });
    }
});
</script>
@endpush
@includeIf('payment-requests._buibichthao_actions')

