@extends('layouts.app')

@section('content')
@php
    $money = function ($value) {
        return number_format((float) ($value ?? 0), 0, ',', '.') . ' đ';
    };

    $percent = function ($value) {
        return number_format((float) ($value ?? 0), 1, ',', '.') . '%';
    };

    $financeBudgets = $financeBudgets ?? collect();

    $safeUsageRate = min(max((float) ($budgetUsageRate ?? 0), 0), 100);
    $cashFlowNet = $cashFlow['net'] ?? 0;
@endphp

<style>
    .budget-v2 {
        padding: 18px;
        background:
            radial-gradient(circle at top left, rgba(59, 130, 246, .08), transparent 28%),
            radial-gradient(circle at top right, rgba(16, 185, 129, .08), transparent 24%),
            #f5f7fb;
        min-height: 100%;
        overflow-x: hidden;
    }

    .budget-v2-hero {
        border-radius: 28px;
        padding: 28px;
        color: #fff;
        background:
            linear-gradient(135deg, rgba(15, 23, 42, .96), rgba(30, 64, 175, .94)),
            radial-gradient(circle at right, rgba(56, 189, 248, .5), transparent 40%);
        box-shadow: 0 22px 55px rgba(15, 23, 42, .18);
        position: relative;
        overflow: hidden;
    }

    .budget-v2-hero::after {
        content: "";
        position: absolute;
        width: 360px;
        height: 360px;
        border-radius: 999px;
        right: -150px;
        top: -120px;
        background: rgba(255, 255, 255, .08);
    }

    .budget-v2-hero-inner {
        position: relative;
        z-index: 2;
    }

    .budget-v2-pill {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 8px 13px;
        border-radius: 999px;
        background: rgba(255,255,255,.12);
        border: 1px solid rgba(255,255,255,.2);
        font-size: 12px;
        font-weight: 800;
        letter-spacing: .04em;
    }

    .budget-v2-title {
        font-size: clamp(28px, 3vw, 42px);
        font-weight: 900;
        letter-spacing: -.04em;
        margin: 14px 0 8px;
    }

    .budget-v2-subtitle {
        max-width: 760px;
        opacity: .78;
        margin: 0;
    }

    .budget-v2-filter {
        margin-top: 22px;
        padding: 16px;
        border-radius: 22px;
        background: rgba(255,255,255,.1);
        border: 1px solid rgba(255,255,255,.18);
    }

    .budget-v2-filter label {
        font-size: 12px;
        font-weight: 800;
        color: rgba(255,255,255,.75);
        margin-bottom: 7px;
    }

    .budget-v2-filter .form-control {
        border: 0;
        border-radius: 15px;
        min-height: 45px;
    }

    .budget-v2-card {
        background: #fff;
        border: 1px solid rgba(15, 23, 42, .06);
        border-radius: 24px;
        box-shadow: 0 16px 40px rgba(15, 23, 42, .065);
    }

    .budget-v2-stat {
        padding: 20px;
        height: 100%;
        position: relative;
        overflow: hidden;
    }

    .budget-v2-stat::after {
        content: "";
        position: absolute;
        width: 100px;
        height: 100px;
        right: -35px;
        bottom: -35px;
        border-radius: 999px;
        background: #eff6ff;
    }

    .budget-v2-icon {
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

    .budget-v2-icon.green {
        background: #dcfce7;
        color: #16a34a;
    }

    .budget-v2-icon.red {
        background: #fee2e2;
        color: #dc2626;
    }

    .budget-v2-icon.amber {
        background: #fef3c7;
        color: #d97706;
    }

    .budget-v2-icon.purple {
        background: #ede9fe;
        color: #7c3aed;
    }

    .budget-v2-label {
        color: #64748b;
        font-size: 13px;
        font-weight: 800;
        margin-bottom: 6px;
    }

    .budget-v2-number {
        color: #0f172a;
        font-size: 25px;
        font-weight: 900;
        letter-spacing: -.04em;
        margin-bottom: 4px;
    }

    .budget-v2-hint {
        color: #64748b;
        font-size: 12px;
    }

    .budget-v2-section-title {
        color: #0f172a;
        font-size: 18px;
        font-weight: 900;
        margin: 0;
    }

    .budget-v2-muted {
        color: #64748b;
        font-size: 13px;
    }

    .budget-v2-form label {
        color: #334155;
        font-size: 12px;
        font-weight: 800;
        margin-bottom: 7px;
    }

    .budget-v2-form .form-control,
    .budget-v2-form .form-select {
        border-radius: 15px;
        border: 1px solid #e2e8f0;
        min-height: 45px;
    }

    .budget-v2-form .form-control:focus,
    .budget-v2-form .form-select:focus {
        border-color: #3b82f6;
        box-shadow: 0 0 0 .2rem rgba(59, 130, 246, .13);
    }

    .budget-v2-table {
        margin-bottom: 0;
    }

    .budget-v2-table thead th {
        color: #64748b;
        font-size: 12px;
        font-weight: 900;
        text-transform: uppercase;
        letter-spacing: .04em;
        border-bottom: 1px solid #e2e8f0;
        white-space: nowrap;
    }

    .budget-v2-table tbody td {
        vertical-align: middle;
        border-bottom: 1px solid #eef2f7;
    }

    .budget-v2-badge {
        display: inline-flex;
        align-items: center;
        gap: 7px;
        border-radius: 999px;
        padding: 7px 11px;
        font-size: 12px;
        font-weight: 900;
        white-space: nowrap;
    }

    .budget-v2-badge.success {
        background: #dcfce7;
        color: #166534;
    }

    .budget-v2-badge.warning {
        background: #fef3c7;
        color: #92400e;
    }

    .budget-v2-badge.danger {
        background: #fee2e2;
        color: #991b1b;
    }

    .budget-v2-badge.primary {
        background: #dbeafe;
        color: #1d4ed8;
    }

    .budget-v2-progress {
        height: 10px;
        border-radius: 999px;
        overflow: hidden;
        background: #eaf0f8;
        min-width: 120px;
    }

    .budget-v2-progress span {
        display: block;
        height: 100%;
        border-radius: 999px;
        background: linear-gradient(90deg, #2563eb, #38bdf8);
    }

    .budget-v2-progress span.success {
        background: linear-gradient(90deg, #16a34a, #86efac);
    }

    .budget-v2-progress span.warning {
        background: linear-gradient(90deg, #f59e0b, #fde68a);
    }

    .budget-v2-progress span.danger {
        background: linear-gradient(90deg, #dc2626, #fb7185);
    }

    .budget-v2-empty {
        border: 1px dashed #cbd5e1;
        border-radius: 20px;
        padding: 28px;
        background: #f8fafc;
        color: #64748b;
        text-align: center;
    }

    .budget-v2-action {
        border-radius: 999px;
        font-weight: 800;
    }

    .budget-v2-chart {
        height: 285px;
    }

    .budget-v2-quick {
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

    .budget-v2-quick:hover {
        transform: translateY(-2px);
        border-color: #bfdbfe;
        background: #eff6ff;
        color: #1d4ed8;
    }

    .modal-content {
        border: 0;
        border-radius: 24px;
        box-shadow: 0 25px 80px rgba(15, 23, 42, .25);
    }

    .modal-header {
        border-bottom: 1px solid #eef2f7;
    }

    @media (max-width: 767.98px) {
        .budget-v2 {
            padding: 12px;
        }

        .budget-v2-hero {
            padding: 22px;
            border-radius: 22px;
        }

        .budget-v2-chart {
            height: 240px;
        }
    }
</style>

<div class="budget-v2">
    @if(session('success'))
        <div class="alert alert-success border-0 rounded-4 shadow-sm mb-3">
            <i class="bi bi-check-circle-fill me-2"></i>
            {{ session('success') }}
        </div>
    @endif

    @if($errors->any())
        <div class="alert alert-danger border-0 rounded-4 shadow-sm mb-3">
            <strong>Có lỗi:</strong>
            <ul class="mb-0 mt-2">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    {{-- HERO --}}
    <div class="budget-v2-hero mb-4">
        <div class="budget-v2-hero-inner">
            <div class="d-flex flex-column flex-xl-row justify-content-between gap-3">
                <div>
                    <div class="budget-v2-pill">
                        <i class="bi bi-stars"></i>
                        FINANCE BUDGET CENTER
                    </div>

                    <h1 class="budget-v2-title">Ngân sách tài chính</h1>

                    <p class="budget-v2-subtitle">
                        Tạo ngân sách theo tháng, theo dõi số đã chi từ phiếu chi, kiểm soát phần còn lại
                        và cảnh báo khi gần vượt ngân sách.
                    </p>
                </div>

                <div class="d-flex flex-wrap gap-2 align-items-start justify-content-xl-end">
                    <a href="{{ route('finance.index') }}" class="btn btn-light rounded-pill px-4 fw-bold">
                        <i class="bi bi-speedometer2 me-1"></i>
                        Tổng quan
                    </a>

                    <a href="{{ route('finance.reports') }}" class="btn btn-outline-light rounded-pill px-4 fw-bold">
                        <i class="bi bi-graph-up-arrow me-1"></i>
                        Báo cáo
                    </a>
                </div>
            </div>

            <form method="GET" action="{{ route('finance.budget') }}" class="budget-v2-filter">
                <div class="row g-3 align-items-end">
                    <div class="col-lg-4">
                        <label>Tháng đang xem</label>
                        <input type="month" name="month" class="form-control"
                               value="{{ $month ?? now()->format('Y-m') }}">
                    </div>

                    <div class="col-lg-4">
                        <label>Khoảng dữ liệu</label>
                        <input type="text" class="form-control"
                               value="{{ isset($monthStart) ? date('d/m/Y', strtotime($monthStart)) : '' }} - {{ isset($monthEnd) ? date('d/m/Y', strtotime($monthEnd)) : '' }}"
                               readonly>
                    </div>

                    <div class="col-lg-2 d-grid">
                        <button class="btn btn-warning rounded-pill fw-bold">
                            <i class="bi bi-funnel me-1"></i>
                            Lọc tháng
                        </button>
                    </div>

                    <div class="col-lg-2 d-grid">
                        <button type="button" class="btn btn-success rounded-pill fw-bold"
                                data-bs-toggle="modal" data-bs-target="#createBudgetModal">
                            <i class="bi bi-plus-circle me-1"></i>
                            Thêm
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    {{-- KPI --}}
    <div class="row g-4 mb-4">
        <div class="col-md-6 col-xl-3">
            <div class="budget-v2-card budget-v2-stat">
                <div class="d-flex justify-content-between align-items-start position-relative" style="z-index: 2;">
                    <div>
                        <div class="budget-v2-label">Tổng ngân sách tháng</div>
                        <div class="budget-v2-number">{{ $money($totalBudget ?? 0) }}</div>
                        <div class="budget-v2-hint">Từ bảng finance_budgets</div>
                    </div>
                    <div class="budget-v2-icon">
                        <i class="bi bi-wallet2"></i>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-6 col-xl-3">
            <div class="budget-v2-card budget-v2-stat">
                <div class="d-flex justify-content-between align-items-start position-relative" style="z-index: 2;">
                    <div>
                        <div class="budget-v2-label">Đã chi theo ngân sách</div>
                        <div class="budget-v2-number text-danger">{{ $money($totalSpent ?? 0) }}</div>
                        <div class="budget-v2-hint">Khớp theo hạng mục phiếu chi</div>
                    </div>
                    <div class="budget-v2-icon red">
                        <i class="bi bi-cash-stack"></i>
                    </div>
                </div>

                <div class="budget-v2-progress mt-3 position-relative" style="z-index: 2;">
                    <span class="{{ $safeUsageRate >= 100 ? 'danger' : ($safeUsageRate >= 80 ? 'warning' : 'success') }}"
                          style="width: {{ $safeUsageRate }}%"></span>
                </div>
            </div>
        </div>

        <div class="col-md-6 col-xl-3">
            <div class="budget-v2-card budget-v2-stat">
                <div class="d-flex justify-content-between align-items-start position-relative" style="z-index: 2;">
                    <div>
                        <div class="budget-v2-label">Còn lại</div>
                        <div class="budget-v2-number {{ ($budgetRemain ?? 0) < 0 ? 'text-danger' : 'text-success' }}">
                            {{ $money($budgetRemain ?? 0) }}
                        </div>
                        <div class="budget-v2-hint">Ngân sách - đã chi</div>
                    </div>
                    <div class="budget-v2-icon green">
                        <i class="bi bi-piggy-bank"></i>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-6 col-xl-3">
            <div class="budget-v2-card budget-v2-stat">
                <div class="d-flex justify-content-between align-items-start position-relative" style="z-index: 2;">
                    <div>
                        <div class="budget-v2-label">Tỷ lệ sử dụng</div>
                        <div class="budget-v2-number">{{ $percent($budgetUsageRate ?? 0) }}</div>
                        <div class="budget-v2-hint">Cảnh báo từ 80% trở lên</div>
                    </div>
                    <div class="budget-v2-icon amber">
                        <i class="bi bi-activity"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- SECOND KPI --}}
    <div class="row g-4 mb-4">
        


        <div class="col-md-6 col-xl-3">
            <div class="budget-v2-card budget-v2-stat">
                <div class="budget-v2-label">Tổng thu trong tháng</div>
                <div class="budget-v2-number text-success">{{ $money($totalReceipts ?? 0) }}</div>
                <div class="budget-v2-hint">Từ phiếu thu</div>
            </div>
        </div>

        <div class="col-md-6 col-xl-3">
            <div class="budget-v2-card budget-v2-stat">
                <div class="budget-v2-label">Tổng chi trong tháng</div>
                <div class="budget-v2-number text-danger">{{ $money($totalPayments ?? 0) }}</div>
                <div class="budget-v2-hint">Từ phiếu chi</div>
            </div>
        </div>

        <div class="col-md-6 col-xl-3">
            <div class="budget-v2-card budget-v2-stat">
                <div class="budget-v2-label">Đề nghị chờ xử lý</div>
                <div class="budget-v2-number">{{ $pendingRequestsCount ?? 0 }}</div>
                <div class="budget-v2-hint">{{ $money($pendingRequestsAmount ?? 0) }}</div>
            </div>
        </div>
    </div>

    <div class="row g-4">
        {{-- MAIN LEFT --}}
        <div class="col-xl-8">
            {{-- FORM CREATE --}}
            <div class="budget-v2-card p-4 mb-4">
                <div class="d-flex flex-column flex-md-row justify-content-between gap-2 mb-3">
                    <div>
                        <h3 class="budget-v2-section-title">Thêm ngân sách tháng</h3>
                        <div class="budget-v2-muted">
                            Nhập ngân sách theo tháng và hạng mục. Hạng mục nên trùng với hạng mục trong phiếu chi để tự tính đã chi.
                        </div>
                    </div>
                    <span class="budget-v2-badge primary">
                        <i class="bi bi-calendar3"></i>
                        {{ $month ?? now()->format('Y-m') }}
                    </span>
                </div>

                <form class="budget-v2-form" method="POST" action="{{ route('finance.budget.store') }}">
                    @csrf

                    <div class="row g-3">
                        <div class="col-md-3">
                            <label>Tháng</label>
                            <input type="month" name="month" class="form-control"
                                   value="{{ old('month', $month ?? now()->format('Y-m')) }}" required>
                        </div>

                        <div class="col-md-3">
                            <label>Hạng mục</label>
                            <input type="text" name="category" class="form-control"
                                   placeholder="VD: Marketing, Lương, Vận hành"
                                   value="{{ old('category') }}" required>
                        </div>

                        <div class="col-md-3">
                            <label>Số tiền ngân sách</label>
                            <input type="number" name="budget_amount" class="form-control"
                                   placeholder="VD: 20000000"
                                   value="{{ old('budget_amount') }}" min="0" step="any" required>
                        </div>

                        <div class="col-md-3">
                            <label>Ghi chú</label>
                            <input type="text" name="note" class="form-control"
                                   placeholder="Ghi chú ngắn"
                                   value="{{ old('note') }}">
                        </div>

                        <div class="col-12 d-flex justify-content-end">
                            <button class="btn btn-primary budget-v2-action px-4">
                                <i class="bi bi-save me-1"></i>
                                Lưu ngân sách
                            </button>
                        </div>
                    </div>
                </form>
            </div>

            {{-- TABLE --}}
            <div class="budget-v2-card p-4 mb-4">
                <div class="d-flex flex-column flex-md-row justify-content-between gap-2 mb-3">
                    <div>
                        <h3 class="budget-v2-section-title">Danh sách ngân sách tháng</h3>
                        <div class="budget-v2-muted">
                            Có thể sửa hoặc xóa trực tiếp từng dòng ngân sách.
                        </div>
                    </div>

                    <a href="{{ route('finance.budget', ['month' => now()->format('Y-m')]) }}"
                       class="btn btn-sm btn-outline-primary rounded-pill px-3 fw-bold">
                        Tháng hiện tại
                    </a>
                </div>

                @if($financeBudgets->count())
                    <div class="table-responsive">
                        <table class="table budget-v2-table align-middle">
                            <thead>
                                <tr>
                                    <th>Hạng mục</th>
                                    <th>Ngân sách</th>
                                    <th>Đã chi</th>
                                    <th>Còn lại</th>
                                    <th style="min-width: 185px;">Tiến độ</th>
                                    <th>Trạng thái</th>
                                    <th class="text-end">Thao tác</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($financeBudgets as $item)
                                    @php
                                        $rate = min(max((float) ($item->usage_rate ?? 0), 0), 100);
                                        $cls = $item->status_class ?? 'success';
                                    @endphp

                                    <tr>
                                        <td>
                                            <div class="fw-bold text-dark">{{ $item->category }}</div>
                                            @if(!empty($item->note))
                                                <div class="budget-v2-muted">{{ $item->note }}</div>
                                            @endif
                                        </td>

                                        <td class="fw-bold">{{ $money($item->budget_amount ?? 0) }}</td>

                                        <td class="fw-bold text-danger">{{ $money($item->spent_amount ?? 0) }}</td>

                                        <td class="fw-bold {{ ($item->remain_amount ?? 0) < 0 ? 'text-danger' : 'text-success' }}">
                                            {{ $money($item->remain_amount ?? 0) }}
                                        </td>

                                        <td>
                                            <div class="d-flex align-items-center gap-2">
                                                <div class="budget-v2-progress flex-grow-1">
                                                    <span class="{{ $cls }}" style="width: {{ $rate }}%"></span>
                                                </div>
                                                <small class="fw-bold">{{ $percent($item->usage_rate ?? 0) }}</small>
                                            </div>
                                        </td>

                                        <td>
                                            <span class="budget-v2-badge {{ $cls }}">
                                                <i class="bi bi-circle-fill" style="font-size: 7px;"></i>
                                                {{ $item->status }}
                                            </span>
                                        </td>

                                        <td class="text-end">
                                            <button type="button"
                                                    class="btn btn-sm btn-outline-primary rounded-pill fw-bold"
                                                    data-bs-toggle="modal"
                                                    data-bs-target="#editBudgetModal{{ $item->id }}">
                                                <i class="bi bi-pencil-square"></i>
                                                Sửa
                                            </button>

                                            <button type="button"
                                                    class="btn btn-sm btn-outline-danger rounded-pill fw-bold"
                                                    data-bs-toggle="modal"
                                                    data-bs-target="#deleteBudgetModal{{ $item->id }}">
                                                <i class="bi bi-trash"></i>
                                                Xóa
                                            </button>
                                        </td>
                                    </tr>

                                    {{-- EDIT MODAL --}}
                                    <div class="modal fade" id="editBudgetModal{{ $item->id }}" tabindex="-1" aria-hidden="true">
                                        <div class="modal-dialog modal-dialog-centered">
                                            <form method="POST" action="{{ route('finance.budget.update', $item->id) }}" class="modal-content budget-v2-form">
                                                @csrf
                                                @method('PUT')

                                                <div class="modal-header">
                                                    <h5 class="modal-title fw-bold">
                                                        <i class="bi bi-pencil-square me-2"></i>
                                                        Sửa ngân sách
                                                    </h5>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                </div>

                                                <div class="modal-body">
                                                    <div class="mb-3">
                                                        <label>Tháng</label>
                                                        <input type="month" name="month" class="form-control"
                                                               value="{{ isset($item->month) ? date('Y-m', strtotime($item->month)) : ($month ?? now()->format('Y-m')) }}"
                                                               required>
                                                    </div>

                                                    <div class="mb-3">
                                                        <label>Hạng mục</label>
                                                        <input type="text" name="category" class="form-control"
                                                               value="{{ $item->category }}"
                                                               required>
                                                    </div>

                                                    <div class="mb-3">
                                                        <label>Số tiền ngân sách</label>
                                                        <input type="number" name="budget_amount" class="form-control"
                                                               value="{{ (float) $item->budget_amount }}"
                                                               min="0" step="any" required>
                                                    </div>

                                                    <div>
                                                        <label>Ghi chú</label>
                                                        <textarea name="note" rows="3" class="form-control">{{ $item->note }}</textarea>
                                                    </div>
                                                </div>

                                                <div class="modal-footer">
                                                    <button type="button" class="btn btn-light rounded-pill px-4" data-bs-dismiss="modal">
                                                        Hủy
                                                    </button>
                                                    <button class="btn btn-primary rounded-pill px-4 fw-bold">
                                                        Lưu thay đổi
                                                    </button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>

                                    {{-- DELETE MODAL --}}
                                    <div class="modal fade" id="deleteBudgetModal{{ $item->id }}" tabindex="-1" aria-hidden="true">
                                        <div class="modal-dialog modal-dialog-centered">
                                            <form method="POST" action="{{ route('finance.budget.destroy', $item->id) }}" class="modal-content">
                                                @csrf
                                                @method('DELETE')

                                                <div class="modal-header">
                                                    <h5 class="modal-title fw-bold text-danger">
                                                        <i class="bi bi-exclamation-triangle me-2"></i>
                                                        Xóa ngân sách?
                                                    </h5>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                </div>

                                                <div class="modal-body">
                                                    Bạn có chắc muốn xóa ngân sách:
                                                    <strong>{{ $item->category }}</strong>
                                                    với số tiền
                                                    <strong>{{ $money($item->budget_amount ?? 0) }}</strong>
                                                    không?
                                                </div>

                                                <div class="modal-footer">
                                                    <button type="button" class="btn btn-light rounded-pill px-4" data-bs-dismiss="modal">
                                                        Hủy
                                                    </button>
                                                    <button class="btn btn-danger rounded-pill px-4 fw-bold">
                                                        Xóa
                                                    </button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <div class="budget-v2-empty">
                        <i class="bi bi-folder-plus fs-1 d-block mb-2"></i>
                        <div class="fw-bold mb-1">Chưa có ngân sách cho tháng này</div>
                        <div>Hãy nhập ngân sách đầu tiên bằng form phía trên.</div>
                    </div>
                @endif
            </div>
        </div>

        {{-- RIGHT --}}
        <div class="col-xl-4">
            <div class="budget-v2-card p-4 mb-4">
                <div class="d-flex justify-content-between align-items-start mb-3">
                    <div>
                        <h3 class="budget-v2-section-title">Biểu đồ ngân sách</h3>
                        <div class="budget-v2-muted">So sánh ngân sách và đã chi.</div>
                    </div>
                    <div class="budget-v2-icon">
                        <i class="bi bi-bar-chart-line"></i>
                    </div>
                </div>

                <div class="budget-v2-chart">
                    <canvas id="budgetV2Chart"></canvas>
                </div>
            </div>

            <div class="budget-v2-card p-4 mb-4">
                <h3 class="budget-v2-section-title mb-3">Tóm tắt nhanh</h3>

                <div class="d-flex justify-content-between border-bottom py-2">
                    <span class="budget-v2-muted">Dòng tiền ròng</span>
                    <strong class="{{ $cashFlowNet >= 0 ? 'text-success' : 'text-danger' }}">
                        {{ $money($cashFlowNet) }}
                    </strong>
                </div>

                <div class="d-flex justify-content-between border-bottom py-2">
                    <span class="budget-v2-muted">Tổng thu</span>
                    <strong class="text-success">{{ $money($totalReceipts ?? 0) }}</strong>
                </div>

                <div class="d-flex justify-content-between border-bottom py-2">
                    <span class="budget-v2-muted">Tổng chi</span>
                    <strong class="text-danger">{{ $money($totalPayments ?? 0) }}</strong>
                </div>

                <div class="d-flex justify-content-between py-2">
                    <span class="budget-v2-muted">Số hạng mục ngân sách</span>
                    <strong>{{ $financeBudgets->count() }}</strong>
                </div>
            </div>

            <div class="budget-v2-card p-4">
                <h3 class="budget-v2-section-title mb-3">Liên kết tài chính</h3>

                <a href="{{ route('finance.index') }}" class="budget-v2-quick">
                    <strong><i class="bi bi-speedometer2 me-2"></i>Tổng quan tài chính</strong>
                    <div class="budget-v2-muted mt-1">Xem dashboard tổng quan.</div>
                </a>

                


                <a href="{{ url('/finance/receipts') }}" class="budget-v2-quick">
                    <strong><i class="bi bi-arrow-down-circle me-2"></i>Phiếu thu</strong>
                    <div class="budget-v2-muted mt-1">Theo dõi nguồn tiền vào.</div>
                </a>

                <a href="{{ url('/finance/payments') }}" class="budget-v2-quick">
                    <strong><i class="bi bi-arrow-up-circle me-2"></i>Phiếu chi</strong>
                    <div class="budget-v2-muted mt-1">Theo dõi khoản chi thực tế.</div>
                </a>

                <a href="{{ route('finance.reports') }}" class="budget-v2-quick mb-0">
                    <strong><i class="bi bi-graph-up-arrow me-2"></i>Báo cáo tài chính</strong>
                    <div class="budget-v2-muted mt-1">Xem báo cáo tổng hợp.</div>
                </a>
            </div>
        </div>
    </div>
</div>

{{-- CREATE MODAL --}}
<div class="modal fade" id="createBudgetModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form method="POST" action="{{ route('finance.budget.store') }}" class="modal-content budget-v2-form">
            @csrf

            <div class="modal-header">
                <h5 class="modal-title fw-bold">
                    <i class="bi bi-plus-circle me-2"></i>
                    Thêm ngân sách
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>

            <div class="modal-body">
                <div class="mb-3">
                    <label>Tháng</label>
                    <input type="month" name="month" class="form-control"
                           value="{{ $month ?? now()->format('Y-m') }}" required>
                </div>

                <div class="mb-3">
                    <label>Hạng mục</label>
                    <input type="text" name="category" class="form-control"
                           placeholder="VD: Marketing, Lương, Vận hành" required>
                </div>

                <div class="mb-3">
                    <label>Số tiền ngân sách</label>
                    <input type="number" name="budget_amount" class="form-control"
                           placeholder="VD: 20000000" min="0" step="any" required>
                </div>

                <div>
                    <label>Ghi chú</label>
                    <textarea name="note" rows="3" class="form-control"
                              placeholder="Ghi chú thêm nếu cần"></textarea>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-light rounded-pill px-4" data-bs-dismiss="modal">
                    Hủy
                </button>
                <button class="btn btn-success rounded-pill px-4 fw-bold">
                    Thêm ngân sách
                </button>
            </div>
        </form>
    </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const ctx = document.getElementById('budgetV2Chart');

    if (!ctx || typeof Chart === 'undefined') {
        return;
    }

    const labels = @json($financeBudgets->pluck('category')->values());
    const budgetData = @json($financeBudgets->pluck('budget_amount')->map(fn($v) => (float) $v)->values());
    const spentData = @json($financeBudgets->pluck('spent_amount')->map(fn($v) => (float) $v)->values());

    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: labels.length ? labels : ['Chưa có dữ liệu'],
            datasets: [
                {
                    label: 'Ngân sách',
                    data: budgetData.length ? budgetData : [0],
                    borderWidth: 1,
                    borderRadius: 12
                },
                {
                    label: 'Đã chi',
                    data: spentData.length ? spentData : [0],
                    borderWidth: 1,
                    borderRadius: 12
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom'
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            const value = Number(context.raw || 0);
                            return context.dataset.label + ': ' + value.toLocaleString('vi-VN') + ' đ';
                        }
                    }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        callback: function(value) {
                            return Number(value).toLocaleString('vi-VN') + ' đ';
                        }
                    }
                }
            }
        }
    });
});
</script>
@endpush