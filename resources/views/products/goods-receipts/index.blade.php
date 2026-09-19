@extends('layouts.app')

@section('title', 'Nhập sản phẩm')

@php
    $payMap = [
        'unpaid' => ['Chưa thanh toán', 'danger'],
        'partial' => ['Thanh toán một phần', 'warning'],
        'paid' => ['Đã thanh toán', 'success'],
    ];

    $statusMap = [
        'draft' => ['Phiếu nháp', 'warning'],
        'posted' => ['Đã nhập kho', 'success'],
    ];

    $warehouseId = $warehouseId ?? request('warehouse_id');
    $q = $q ?? request('q', '');
    $paymentStatus = $paymentStatus ?? request('payment_status', '');
@endphp

@section('content')
<style>
    :root {
        --gi-navy: #0a203a;
        --gi-navy-2: #153553;
        --gi-teal: #079b96;
        --gi-teal-2: #16b9b1;
        --gi-bg: #f2f6fa;
        --gi-card: #ffffff;
        --gi-line: #dbe6ee;
        --gi-line-soft: #eaf0f4;
        --gi-muted: #6b7e91;
        --gi-green: #11884e;
        --gi-red: #d6455d;
        --gi-orange: #c57b1c;
        --gi-shadow: 0 14px 38px rgba(15, 39, 64, .075);
    }

    .gi-page {
        min-height: calc(100vh - 72px);
        padding: 18px 20px 36px;
        background:
            radial-gradient(circle at 94% 0%, rgba(22, 185, 177, .08), transparent 29%),
            var(--gi-bg);
    }

    .gi-header {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        gap: 18px;
        margin-bottom: 14px;
    }

    .gi-eyebrow {
        margin-bottom: 6px;
        color: var(--gi-teal);
        font-size: 10px;
        font-weight: 900;
        letter-spacing: .12em;
        text-transform: uppercase;
    }

    .gi-title {
        margin: 0;
        color: var(--gi-navy);
        font-size: 27px;
        font-weight: 950;
        letter-spacing: -.04em;
        line-height: 1.15;
    }

    .gi-subtitle {
        margin-top: 6px;
        color: var(--gi-muted);
        font-size: 12px;
        font-weight: 650;
    }

    .gi-actions {
        display: flex;
        align-items: center;
        gap: 8px;
        flex-wrap: wrap;
    }

    .gi-btn {
        min-height: 40px;
        padding: 0 14px;
        border: 1px solid var(--gi-line);
        border-radius: 12px;
        background: #fff;
        color: var(--gi-navy-2);
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        font-size: 12px;
        font-weight: 900;
        text-decoration: none;
        cursor: pointer;
        transition: .18s ease;
        white-space: nowrap;
    }

    .gi-btn:hover {
        color: var(--gi-navy-2);
        transform: translateY(-1px);
        box-shadow: 0 9px 20px rgba(15, 39, 64, .09);
    }

    .gi-btn-primary {
        border-color: transparent;
        background: linear-gradient(135deg, var(--gi-teal-2), var(--gi-teal));
        color: #fff;
        box-shadow: 0 10px 22px rgba(7, 155, 150, .2);
    }

    .gi-btn-primary:hover,
    .gi-btn-navy:hover {
        color: #fff;
    }

    .gi-btn-navy {
        border-color: var(--gi-navy);
        background: var(--gi-navy);
        color: #fff;
    }

    .gi-btn-soft {
        border-color: #c8ebe8;
        background: #edfafa;
        color: #087b76;
    }

    .gi-btn-danger {
        border-color: #ffd0d7;
        background: #fff1f3;
        color: var(--gi-red);
    }

    .gi-btn-xs {
        min-height: 31px;
        padding: 0 9px;
        border-radius: 9px;
        font-size: 10px;
    }

    .gi-kpis {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: 10px;
        margin-bottom: 12px;
    }

    .gi-kpi {
        min-height: 88px;
        padding: 13px 14px;
        border: 1px solid var(--gi-line);
        border-radius: 16px;
        background: #fff;
        display: flex;
        align-items: center;
        gap: 12px;
        box-shadow: 0 8px 24px rgba(15, 39, 64, .045);
    }

    .gi-kpi-icon {
        width: 42px;
        height: 42px;
        border-radius: 13px;
        background: #eafafa;
        color: var(--gi-teal);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 18px;
    }

    .gi-kpi:nth-child(2) .gi-kpi-icon { background: #eef3ff; color: #566bd3; }
    .gi-kpi:nth-child(3) .gi-kpi-icon { background: #fff0f2; color: var(--gi-red); }
    .gi-kpi:nth-child(4) .gi-kpi-icon { background: #eaf9f0; color: var(--gi-green); }

    .gi-kpi-label {
        color: #718499;
        font-size: 9px;
        font-weight: 900;
        letter-spacing: .055em;
        text-transform: uppercase;
    }

    .gi-kpi-value {
        margin-top: 4px;
        color: var(--gi-navy);
        font-size: 20px;
        font-weight: 950;
        line-height: 1.1;
    }

    .gi-card {
        margin-bottom: 12px;
        border: 1px solid var(--gi-line);
        border-radius: 18px;
        background: var(--gi-card);
        overflow: hidden;
        box-shadow: var(--gi-shadow);
    }

    .gi-card-head {
        min-height: 58px;
        padding: 12px 15px;
        border-bottom: 1px solid var(--gi-line);
        background: linear-gradient(135deg, #ffffff, #f2fbfa);
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
    }

    .gi-card-title {
        color: var(--gi-navy);
        font-size: 15px;
        font-weight: 950;
    }

    .gi-card-sub {
        margin-top: 3px;
        color: var(--gi-muted);
        font-size: 10px;
        font-weight: 650;
    }

    .gi-company-lock {
        min-height: 36px;
        padding: 0 11px;
        border: 1px solid #c4ebe8;
        border-radius: 11px;
        background: #edfafa;
        color: #087b76;
        display: inline-flex;
        align-items: center;
        gap: 7px;
        font-size: 10px;
        font-weight: 900;
        white-space: nowrap;
    }

    .gi-form-layout {
        display: grid;
        grid-template-columns: minmax(0, 1fr) 310px;
        align-items: start;
    }

    .gi-form-main {
        padding: 15px;
        border-right: 1px solid var(--gi-line);
    }

    .gi-form-aside {
        position: sticky;
        top: 78px;
        padding: 15px;
        background: linear-gradient(180deg, #fbfefe, #f6fafc);
    }

    .gi-section {
        margin-bottom: 15px;
    }

    .gi-section:last-child {
        margin-bottom: 0;
    }

    .gi-section-title {
        margin-bottom: 9px;
        color: var(--gi-navy-2);
        display: flex;
        align-items: center;
        gap: 8px;
        font-size: 11px;
        font-weight: 950;
        letter-spacing: .035em;
        text-transform: uppercase;
    }

    .gi-section-title::before {
        content: '';
        width: 4px;
        height: 15px;
        border-radius: 999px;
        background: linear-gradient(180deg, var(--gi-teal-2), var(--gi-teal));
    }

    .gi-grid-4 {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: 10px;
    }

    .gi-grid-3 {
        display: grid;
        grid-template-columns: 1.25fr 1fr 1fr;
        gap: 10px;
    }

    .gi-grid-2 {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 10px;
    }

    .gi-field label {
        display: block;
        margin-bottom: 6px;
        color: #52687d;
        font-size: 9px;
        font-weight: 900;
        letter-spacing: .055em;
        text-transform: uppercase;
    }

    .gi-control {
        width: 100%;
        height: 42px;
        padding: 0 12px;
        border: 1px solid #d7e3ec;
        border-radius: 12px;
        background: #fff;
        color: var(--gi-navy-2);
        outline: none;
        font-size: 12px;
        font-weight: 750;
        transition: .16s ease;
    }

    .gi-control:hover {
        border-color: #c5d6e2;
    }

    .gi-control:focus {
        border-color: #55d2cc;
        box-shadow: 0 0 0 4px rgba(7, 155, 150, .105);
    }

    textarea.gi-control {
        min-height: 78px;
        padding: 10px 12px;
        resize: vertical;
        line-height: 1.5;
    }

    .gi-items-wrap {
        padding: 11px;
        border: 1px solid #d8e5ed;
        border-radius: 15px;
        background: #f8fbfd;
    }

    .gi-item {
        position: relative;
        display: grid;
        grid-template-columns: minmax(300px, 1.55fr) 82px 116px 82px 126px minmax(150px, .75fr) 38px;
        gap: 8px;
        align-items: end;
        padding: 12px 11px 11px;
        margin-bottom: 9px;
        border: 1px solid #dce7ef;
        border-radius: 14px;
        background: #fff;
        box-shadow: 0 7px 20px rgba(15, 39, 64, .045);
    }

    .gi-item:last-child {
        margin-bottom: 0;
    }

    .gi-line-number {
        position: absolute;
        top: -8px;
        left: 12px;
        min-width: 26px;
        height: 22px;
        padding: 0 7px;
        border-radius: 999px;
        background: var(--gi-navy);
        color: #fff;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 9px;
        font-weight: 950;
        box-shadow: 0 5px 12px rgba(10, 32, 58, .18);
    }

    .gi-product-box {
        display: grid;
        grid-template-columns: minmax(0, 1fr) 42px;
        gap: 7px;
    }

    .gi-product-search {
        margin-bottom: 6px;
        background: #fbfdff;
    }

    .gi-open-product {
        width: 42px;
        min-height: 42px;
        padding: 0;
    }

    .gi-total-control {
        background: #eafafa;
        color: #087b76;
        font-weight: 950;
        text-align: right;
    }

    .gi-remove {
        width: 38px;
        height: 42px;
        border: 1px solid #ffd0d7;
        border-radius: 11px;
        background: #fff1f3;
        color: var(--gi-red);
        cursor: pointer;
        font-size: 17px;
        font-weight: 950;
        transition: .16s ease;
    }

    .gi-remove:hover {
        transform: translateY(-1px);
        background: #ffe8ec;
    }

    .gi-add-line {
        margin-top: 10px;
    }

    .gi-summary-title {
        margin-bottom: 12px;
        color: var(--gi-navy);
        font-size: 14px;
        font-weight: 950;
    }

    .gi-summary-row {
        min-height: 43px;
        padding: 9px 0;
        border-bottom: 1px solid var(--gi-line-soft);
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
    }

    .gi-summary-row:last-of-type {
        border-bottom: 0;
    }

    .gi-summary-label {
        color: #64788c;
        font-size: 10px;
        font-weight: 800;
    }

    .gi-summary-value {
        color: var(--gi-navy);
        font-size: 12px;
        font-weight: 950;
        text-align: right;
    }

    .gi-summary-total {
        margin: 11px 0;
        padding: 13px;
        border: 1px solid #c8ebe8;
        border-radius: 13px;
        background: #edfafa;
    }

    .gi-summary-total .gi-summary-label {
        color: #087b76;
    }

    .gi-summary-total .gi-summary-value {
        margin-top: 4px;
        color: #067b76;
        font-size: 22px;
    }

    .gi-summary-debt {
        color: var(--gi-red);
    }

    .gi-aside-actions {
        display: grid;
        gap: 8px;
        margin-top: 12px;
    }

    .gi-aside-actions .gi-btn {
        width: 100%;
    }

    .gi-list-body {
        padding: 13px 15px;
    }

    .gi-list-filter {
        display: grid;
        grid-template-columns: 1.35fr 1fr 1fr auto;
        gap: 9px;
        align-items: end;
    }

    .gi-table-wrap {
        overflow: auto;
    }

    .gi-table {
        width: 100%;
        min-width: 1080px;
        border-collapse: collapse;
    }

    .gi-table th {
        padding: 10px 11px;
        border-bottom: 1px solid var(--gi-line);
        background: #f5f8fb;
        color: #61758a;
        font-size: 9px;
        font-weight: 900;
        letter-spacing: .035em;
        text-align: left;
        text-transform: uppercase;
        white-space: nowrap;
    }

    .gi-table td {
        padding: 12px 11px;
        border-bottom: 1px solid var(--gi-line-soft);
        color: #213d59;
        font-size: 11px;
        vertical-align: middle;
    }

    .gi-table tbody tr:hover td {
        background: #f3fbfb;
    }

    .gi-code {
        color: var(--gi-navy);
        font-size: 11px;
        font-weight: 950;
    }

    .gi-muted {
        margin-top: 3px;
        color: var(--gi-muted);
        font-size: 9px;
        font-weight: 700;
    }

    .gi-badge {
        padding: 5px 8px;
        border-radius: 999px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 9px;
        font-weight: 950;
        white-space: nowrap;
    }

    .gi-badge-success { background: #e7f8ed; color: var(--gi-green); }
    .gi-badge-warning { background: #fff4dc; color: #a96808; }
    .gi-badge-danger { background: #fff0f2; color: var(--gi-red); }

    .gi-row-actions {
        display: flex;
        gap: 5px;
        flex-wrap: wrap;
    }

    .gi-empty {
        padding: 36px 18px !important;
        color: #718499 !important;
        font-size: 11px !important;
        font-weight: 700;
        text-align: center;
    }

    .gi-pagination {
        padding: 12px 15px;
        border-top: 1px solid var(--gi-line);
    }

    @media (max-width: 1280px) {
        .gi-form-layout { grid-template-columns: 1fr; }
        .gi-form-main { border-right: 0; border-bottom: 1px solid var(--gi-line); }
        .gi-form-aside { position: static; }
        .gi-aside-actions { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        .gi-item { grid-template-columns: repeat(3, minmax(0, 1fr)); padding-top: 17px; }
        .gi-remove { justify-self: end; }
    }

    @media (max-width: 1000px) {
        .gi-kpis, .gi-grid-4 { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        .gi-list-filter { grid-template-columns: repeat(2, minmax(0, 1fr)); }
    }

    @media (max-width: 720px) {
        .gi-page { padding: 13px; }
        .gi-header { display: block; }
        .gi-actions { margin-top: 11px; }
        .gi-kpis, .gi-grid-4, .gi-grid-3, .gi-grid-2, .gi-list-filter, .gi-item { grid-template-columns: 1fr; }
        .gi-aside-actions { grid-template-columns: 1fr; }
        .gi-title { font-size: 22px; }
    }
.gi-code-link{text-decoration:none;color:var(--gi-navy);font-weight:950}.gi-code-link:hover{color:var(--gi-teal);text-decoration:underline}


    .gi-supplier-control {
        display: grid;
        grid-template-columns: minmax(0, 1fr) auto;
        gap: 7px;
    }

    .gi-create-supplier {
        min-height: 42px;
        padding-inline: 11px;
    }

    .gi-product-picker-wrap {
        display: grid;
        grid-template-columns: minmax(0, 1fr) 42px;
        gap: 7px;
    }

    .gi-product-picker {
        width: 100%;
        min-height: 48px;
        padding: 6px 10px;
        border: 1px solid #ccdbe6;
        border-radius: 12px;
        background: #fff;
        color: var(--gi-navy-2);
        display: flex;
        align-items: center;
        gap: 10px;
        text-align: left;
        cursor: pointer;
        transition: .16s ease;
    }

    .gi-product-picker:hover,
    .gi-product-picker:focus {
        border-color: #55d2cc;
        box-shadow: 0 0 0 4px rgba(7, 155, 150, .09);
        outline: none;
    }

    .gi-product-picker.is-selected {
        border-color: #8eddd8;
        background: #f4fdfc;
    }

    .gi-product-picker-icon {
        width: 34px;
        height: 34px;
        border-radius: 10px;
        background: #e9f9f8;
        color: var(--gi-teal);
        display: inline-flex;
        align-items: center;
        justify-content: center;
        flex: 0 0 auto;
        font-size: 15px;
    }

    .gi-product-picker-copy {
        min-width: 0;
        flex: 1;
        display: block;
    }

    .gi-product-picker-copy strong,
    .gi-product-picker-copy small {
        display: block;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .gi-product-picker-copy strong {
        color: var(--gi-navy);
        font-size: 11px;
        font-weight: 950;
    }

    .gi-product-picker-copy small {
        margin-top: 2px;
        color: var(--gi-muted);
        font-size: 9px;
        font-weight: 750;
    }

    .gi-product-picker-arrow {
        color: #7a8ca0;
        flex: 0 0 auto;
    }

    .gi-modal {
        position: fixed;
        inset: 0;
        z-index: 99999;
        display: none;
        align-items: center;
        justify-content: center;
        padding: 18px;
        background: rgba(4, 18, 34, .56);
        backdrop-filter: blur(5px);
    }

    .gi-modal.is-open { display: flex; }

    .gi-modal-dialog {
        width: min(760px, 100%);
        max-height: min(760px, calc(100vh - 36px));
        border: 1px solid rgba(255, 255, 255, .65);
        border-radius: 20px;
        background: #fff;
        box-shadow: 0 28px 90px rgba(5, 25, 45, .28);
        overflow: hidden;
        display: flex;
        flex-direction: column;
    }

    .gi-modal-dialog.gi-modal-sm { width: min(610px, 100%); }

    .gi-modal-head {
        padding: 15px 17px;
        border-bottom: 1px solid var(--gi-line);
        background: linear-gradient(135deg, #fff, #effbf9);
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 12px;
    }

    .gi-modal-title {
        color: var(--gi-navy);
        font-size: 17px;
        font-weight: 950;
    }

    .gi-modal-sub {
        margin-top: 3px;
        color: var(--gi-muted);
        font-size: 10px;
        font-weight: 700;
    }

    .gi-modal-close {
        width: 36px;
        height: 36px;
        border: 1px solid var(--gi-line);
        border-radius: 11px;
        background: #fff;
        color: var(--gi-navy);
        font-size: 20px;
        cursor: pointer;
    }

    .gi-modal-body {
        padding: 15px 17px;
        overflow: auto;
    }

    .gi-modal-foot {
        padding: 12px 17px;
        border-top: 1px solid var(--gi-line);
        background: #f8fbfd;
        display: flex;
        justify-content: flex-end;
        gap: 8px;
    }

    .gi-product-modal-search {
        position: sticky;
        top: 0;
        z-index: 2;
        padding-bottom: 11px;
        background: #fff;
    }

    .gi-product-results {
        display: grid;
        gap: 8px;
    }

    .gi-product-result {
        width: 100%;
        padding: 11px 12px;
        border: 1px solid var(--gi-line);
        border-radius: 13px;
        background: #fff;
        display: grid;
        grid-template-columns: minmax(0, 1fr) auto;
        gap: 12px;
        align-items: center;
        text-align: left;
        cursor: pointer;
        transition: .15s ease;
    }

    .gi-product-result:hover {
        border-color: #8eddd8;
        background: #f3fcfb;
        transform: translateY(-1px);
    }

    .gi-product-result-name {
        color: var(--gi-navy);
        font-size: 12px;
        font-weight: 950;
    }

    .gi-product-result-meta {
        margin-top: 4px;
        color: var(--gi-muted);
        font-size: 9px;
        font-weight: 750;
    }

    .gi-product-result-stock {
        padding: 6px 9px;
        border-radius: 999px;
        background: #eafafa;
        color: #087b76;
        font-size: 9px;
        font-weight: 950;
        white-space: nowrap;
    }

    .gi-modal-empty {
        padding: 35px 15px;
        color: var(--gi-muted);
        text-align: center;
        font-size: 11px;
        font-weight: 750;
    }

    .gi-modal-error {
        display: none;
        margin-bottom: 10px;
        padding: 9px 11px;
        border: 1px solid #ffd0d7;
        border-radius: 10px;
        background: #fff1f3;
        color: var(--gi-red);
        font-size: 10px;
        font-weight: 800;
    }

    .gi-modal-error.is-visible { display: block; }

    @media (max-width: 720px) {
        .gi-supplier-control { grid-template-columns: 1fr; }
        .gi-create-supplier { width: 100%; }
        .gi-modal { padding: 8px; }
        .gi-modal-dialog { max-height: calc(100vh - 16px); border-radius: 15px; }
    }

</style>

<div class="gi-page">
    <header class="gi-header">
        <div>
            <div class="gi-eyebrow">Kho &amp; Sản phẩm</div>
            <h1 class="gi-title">Nhập sản phẩm</h1>
            <div class="gi-subtitle">Tạo phiếu nhập chuyên nghiệp, ghi nhận thanh toán và nhập trực tiếp vào kho Quốc Tế EGO.</div>
        </div>

        <div class="gi-actions">
            <a href="{{ route('products.input') }}" class="gi-btn">
                <i class="bi bi-box-seam"></i>
                Danh sách sản phẩm
            </a>
            <a href="{{ route('products.create') }}" class="gi-btn gi-btn-soft">
                <i class="bi bi-plus-lg"></i>
                Tạo sản phẩm
            </a>
        </div>
    </header>

    @if (session('success'))
        <div class="alert alert-success border-0 rounded-3 shadow-sm py-2">{{ session('success') }}</div>
    @endif

    @if (session('error'))
        <div class="alert alert-danger border-0 rounded-3 shadow-sm py-2">{{ session('error') }}</div>
    @endif

    @if ($errors->any())
        <div class="alert alert-danger border-0 rounded-3 shadow-sm py-2">{{ $errors->first() }}</div>
    @endif

    <section class="gi-kpis">
        <div class="gi-kpi">
            <div class="gi-kpi-icon"><i class="bi bi-receipt"></i></div>
            <div>
                <div class="gi-kpi-label">Tổng phiếu</div>
                <div class="gi-kpi-value">{{ number_format($stats['total'] ?? 0) }}</div>
            </div>
        </div>
        <div class="gi-kpi">
            <div class="gi-kpi-icon"><i class="bi bi-box-arrow-in-down"></i></div>
            <div>
                <div class="gi-kpi-label">Đã nhập kho</div>
                <div class="gi-kpi-value">{{ number_format($stats['posted'] ?? 0) }}</div>
            </div>
        </div>
        <div class="gi-kpi">
            <div class="gi-kpi-icon"><i class="bi bi-wallet2"></i></div>
            <div>
                <div class="gi-kpi-label">Công nợ phải trả</div>
                <div class="gi-kpi-value">{{ number_format($stats['unpaid'] ?? 0) }} đ</div>
            </div>
        </div>
        <div class="gi-kpi">
            <div class="gi-kpi-icon"><i class="bi bi-check2-circle"></i></div>
            <div>
                <div class="gi-kpi-label">Đã thanh toán</div>
                <div class="gi-kpi-value">{{ number_format($stats['paid'] ?? 0) }} đ</div>
            </div>
        </div>
    </section>

    <section class="gi-card">
        <div class="gi-card-head">
            <div>
                <div class="gi-card-title">Tạo phiếu nhập hàng</div>
                <div class="gi-card-sub">Công ty được khóa ngầm; chỉ chọn kho nhận hàng và nhập thông tin nhà cung cấp.</div>
            </div>
            <div class="gi-company-lock">
                <i class="bi bi-shield-check"></i>
                Công ty Quốc Tế EGO
            </div>
        </div>

        <form method="POST" action="{{ route('product-goods-receipts.store') }}" id="goodsReceiptForm">
            @csrf
            <input type="hidden" name="company_id" value="2">

            <div class="gi-form-layout">
                <div class="gi-form-main">
                    <div class="gi-section">
                        <div class="gi-section-title">Nhà cung cấp &amp; kho nhận hàng</div>
                        <div class="gi-grid-4">
                            <div class="gi-field">
                                <label>Kho nhập hàng *</label>
                                <select class="gi-control" name="warehouse_id" id="giWarehouse" required>
                                    <option value="">Chọn kho nhập hàng</option>
                                    @foreach ($warehouses as $warehouse)
                                        <option value="{{ $warehouse->id }}" @selected((string) old('warehouse_id') === (string) $warehouse->id)>
                                            {{ $warehouse->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="gi-field">
                                <label>Nhà cung cấp *</label>
                                <div class="gi-supplier-control">
                                    <select class="gi-control" name="supplier_id" id="giSupplier" required onchange="giSupplierChanged(this)">
                                        <option value="">Chọn nhà cung cấp</option>
                                        @foreach ($suppliers as $supplier)
                                            <option
                                                value="{{ $supplier->id }}"
                                                data-name="{{ $supplier->name }}"
                                                data-phone="{{ $supplier->phone }}"
                                                data-tax-code="{{ $supplier->tax_code }}"
                                                data-address="{{ $supplier->address }}"
                                                @selected((string) old('supplier_id') === (string) $supplier->id)
                                            >{{ $supplier->name }}</option>
                                        @endforeach
                                    </select>
                                    <button type="button" class="gi-btn gi-btn-soft gi-create-supplier" onclick="giOpenSupplierModal()">
                                        <i class="bi bi-plus-lg"></i> Tạo NCC
                                    </button>
                                </div>
                                <input type="hidden" name="supplier_name" id="giSupplierName" value="{{ old('supplier_name') }}">
                            </div>

                            <div class="gi-field">
                                <label>Số điện thoại NCC</label>
                                <input class="gi-control" name="supplier_phone" id="giSupplierPhone" value="{{ old('supplier_phone') }}" placeholder="Không bắt buộc">
                            </div>

                            <div class="gi-field">
                                <label>Mã số thuế NCC</label>
                                <input class="gi-control" name="supplier_tax_code" id="giSupplierTaxCode" value="{{ old('supplier_tax_code') }}" placeholder="Không bắt buộc">
                            </div>
                        </div>

                        <div class="gi-grid-2" style="margin-top: 10px">
                            <div class="gi-field">
                                <label>Địa chỉ NCC</label>
                                <input class="gi-control" name="supplier_address" id="giSupplierAddress" value="{{ old('supplier_address') }}" placeholder="Địa chỉ giao dịch hoặc xuất hóa đơn">
                            </div>

                            <div class="gi-field">
                                <label>Số hóa đơn</label>
                                <input class="gi-control" name="invoice_no" value="{{ old('invoice_no') }}" placeholder="VD: HD001 / VAT001">
                            </div>
                        </div>
                    </div>

                    <div class="gi-section">
                        <div class="gi-section-title">Hàng hóa nhập kho</div>
                        <div class="gi-items-wrap">
                            <div id="giItems"></div>
                            <button class="gi-btn gi-btn-soft gi-add-line" type="button" onclick="giAddItem()">
                                <i class="bi bi-plus-lg"></i>
                                Thêm dòng hàng hóa
                            </button>
                        </div>
                    </div>

                    <div class="gi-section">
                        <div class="gi-section-title">Ghi chú phiếu nhập</div>
                        <div class="gi-field">
                            <textarea class="gi-control" name="note" placeholder="Nguồn nhập, điều kiện thanh toán, lưu ý giao nhận...">{{ old('note') }}</textarea>
                        </div>
                    </div>
                </div>

                <aside class="gi-form-aside">
                    <div class="gi-summary-title">Thông tin thanh toán</div>

                    <div class="gi-grid-2">
                        <div class="gi-field">
                            <label>Ngày hóa đơn</label>
                            <input class="gi-control" type="date" name="invoice_date" value="{{ old('invoice_date', now()->toDateString()) }}">
                        </div>

                        <div class="gi-field">
                            <label>Ngày dự kiến TT</label>
                            <input class="gi-control" type="date" name="payment_due_date" value="{{ old('payment_due_date') }}">
                        </div>
                    </div>

                    <div class="gi-field" style="margin-top: 10px">
                        <label>Trạng thái thanh toán</label>
                        <select class="gi-control" name="payment_status" required>
                            <option value="unpaid" @selected(old('payment_status', 'unpaid') === 'unpaid')>Chưa thanh toán</option>
                            <option value="partial" @selected(old('payment_status') === 'partial')>Thanh toán một phần</option>
                            <option value="paid" @selected(old('payment_status') === 'paid')>Đã thanh toán</option>
                        </select>
                    </div>

                    <div class="gi-field" style="margin-top: 10px">
                        <label>Số tiền đã thanh toán</label>
                        <input class="gi-control" id="giPaidAmount" type="text" name="paid_amount" value="{{ old('paid_amount', 0) }}" oninput="giCalcSummary()" inputmode="decimal" autocomplete="off">
                    </div>

                    <div class="gi-summary-row">
                        <span class="gi-summary-label">Kho nhận hàng</span>
                        <span class="gi-summary-value" id="giSummaryWarehouse">Chưa chọn kho</span>
                    </div>

                    <div class="gi-summary-row">
                        <span class="gi-summary-label">Số dòng hàng hóa</span>
                        <span class="gi-summary-value" id="giSummaryLines">0</span>
                    </div>

                    <div class="gi-summary-total">
                        <div class="gi-summary-label">Tổng giá trị phiếu</div>
                        <div class="gi-summary-value" id="giSummaryTotal">0 đ</div>
                    </div>

                    <div class="gi-summary-row">
                        <span class="gi-summary-label">Đã thanh toán</span>
                        <span class="gi-summary-value" id="giSummaryPaid">0 đ</span>
                    </div>

                    <div class="gi-summary-row">
                        <span class="gi-summary-label">Công nợ còn lại</span>
                        <span class="gi-summary-value gi-summary-debt" id="giSummaryDebt">0 đ</span>
                    </div>

                    <div class="gi-aside-actions">
                        <button class="gi-btn gi-btn-navy" name="action" value="draft">
                            <i class="bi bi-save"></i>
                            Lưu phiếu nháp
                        </button>
                        <button class="gi-btn gi-btn-primary" name="action" value="post" onclick="return confirm('Xác nhận lưu phiếu và nhập hàng vào kho?')">
                            <i class="bi bi-box-arrow-in-down"></i>
                            Lưu &amp; nhập kho
                        </button>
                    </div>
                </aside>
            </div>
        </form>
    </section>

    <section class="gi-card">
        <div class="gi-card-head">
            <div>
                <div class="gi-card-title">Danh sách phiếu nhập hàng</div>
                <div class="gi-card-sub">Tìm theo mã phiếu, nhà cung cấp, hóa đơn, kho và tình trạng thanh toán.</div>
            </div>
        </div>

        <div class="gi-list-body">
            <form method="GET" class="gi-list-filter">
                <div class="gi-field">
                    <label>Tìm kiếm</label>
                    <input class="gi-control" name="q" value="{{ $q }}" placeholder="Mã phiếu, NCC, số hóa đơn...">
                </div>

                <div class="gi-field">
                    <label>Kho</label>
                    <select class="gi-control" name="warehouse_id">
                        <option value="">Tất cả kho Quốc Tế EGO</option>
                        @foreach ($warehouses as $warehouse)
                            <option value="{{ $warehouse->id }}" @selected((string) $warehouseId === (string) $warehouse->id)>
                                {{ $warehouse->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="gi-field">
                    <label>Thanh toán</label>
                    <select class="gi-control" name="payment_status">
                        <option value="">Tất cả trạng thái</option>
                        <option value="unpaid" @selected($paymentStatus === 'unpaid')>Chưa thanh toán</option>
                        <option value="partial" @selected($paymentStatus === 'partial')>Thanh toán một phần</option>
                        <option value="paid" @selected($paymentStatus === 'paid')>Đã thanh toán</option>
                    </select>
                </div>

                <div class="gi-actions">
                    <button class="gi-btn gi-btn-navy">
                        <i class="bi bi-funnel"></i>
                        Lọc
                    </button>
                    <a href="{{ route('product-goods-receipts.index') }}" class="gi-btn" title="Đặt lại bộ lọc">
                        <i class="bi bi-arrow-counterclockwise"></i>
                    </a>
                </div>
            </form>
        </div>

        <div class="gi-table-wrap">
            <table class="gi-table">
                <thead>
                    <tr>
                        <th>Mã phiếu</th>
                        <th>Nhà cung cấp / hóa đơn</th>
                        <th>Kho nhập</th>
                        <th>Tổng tiền</th>
                        <th>Công nợ</th>
                        <th>Thanh toán</th>
                        <th>Trạng thái</th>
                        <th>Thao tác</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($receipts as $row)
                        @php
                            $pay = $payMap[$row->payment_status] ?? [$row->payment_status, 'warning'];
                            $status = $statusMap[$row->status] ?? [$row->status, 'warning'];
                        @endphp
                        <tr>
                            <td>
                                <a class="gi-code gi-code-link" href="{{ route('product-goods-receipts.show', $row->id) }}" title="Xem chi tiết phiếu và sản phẩm">{{ $row->code }}</a>
                                <div class="gi-muted">
                                    {{ $row->invoice_date ? \Illuminate\Support\Carbon::parse($row->invoice_date)->format('d/m/Y') : 'Chưa có ngày HĐ' }}
                                </div>
                            </td>
                            <td>
                                <strong>{{ $row->supplier_name }}</strong>
                                <div class="gi-muted">HĐ: {{ $row->invoice_no ?: 'Chưa nhập' }}</div>
                            </td>
                            <td>
                                <strong>{{ $row->warehouse_name ?: 'Chưa xác định' }}</strong>
                                <div class="gi-muted">Công ty Quốc Tế EGO</div>
                            </td>
                            <td><strong>{{ number_format($row->total_amount) }} đ</strong></td>
                            <td>
                                <strong style="color: var(--gi-red)">{{ number_format($row->debt_amount) }} đ</strong>
                                <div class="gi-muted">
                                    Dự kiến: {{ $row->payment_due_date ? \Illuminate\Support\Carbon::parse($row->payment_due_date)->format('d/m/Y') : 'Chưa có' }}
                                </div>
                            </td>
                            <td><span class="gi-badge gi-badge-{{ $pay[1] }}">{{ $pay[0] }}</span></td>
                            <td><span class="gi-badge gi-badge-{{ $status[1] }}">{{ $status[0] }}</span></td>
                            <td>
                                <div class="gi-row-actions">
                                    <a class="gi-btn gi-btn-xs" href="{{ route('product-goods-receipts.show', $row->id) }}" title="Xem chi tiết phiếu và sản phẩm">
                                        <i class="bi bi-eye"></i> Xem phiếu
                                    </a>

                                    @if ($row->status !== 'posted')
                                        <form method="POST" action="{{ route('product-goods-receipts.post', $row->id) }}">
                                            @csrf
                                            <button class="gi-btn gi-btn-primary gi-btn-xs" onclick="return confirm('Nhập kho phiếu này?')">
                                                Nhập kho
                                            </button>
                                        </form>

                                        <form method="POST" action="{{ route('product-goods-receipts.destroy', $row->id) }}">
                                            @csrf
                                            @method('DELETE')
                                            <button class="gi-btn gi-btn-danger gi-btn-xs" onclick="return confirm('Xóa phiếu nháp này?')">
                                                Xóa
                                            </button>
                                        </form>
                                    @else
                                        <span class="gi-muted">Đã khóa sau nhập kho</span>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="gi-empty">Chưa có phiếu nhập hàng phù hợp.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="gi-pagination">
            {{ $receipts->appends(request()->query())->links('pagination::bootstrap-5') }}
        </div>
    </section>
</div>



<div class="gi-modal" id="giSupplierModal" aria-hidden="true" onclick="giModalBackdrop(event, 'giSupplierModal')">
    <div class="gi-modal-dialog gi-modal-sm" role="dialog" aria-modal="true" aria-labelledby="giSupplierModalTitle">
        <div class="gi-modal-head">
            <div>
                <div class="gi-modal-title" id="giSupplierModalTitle">Tạo nhà cung cấp</div>
                <div class="gi-modal-sub">Lưu vào danh mục để lần sau chỉ cần chọn, không nhập lại bằng tay.</div>
            </div>
            <button type="button" class="gi-modal-close" onclick="giCloseModal('giSupplierModal')">×</button>
        </div>
        <form id="giSupplierCreateForm" onsubmit="giCreateSupplier(event)">
            <div class="gi-modal-body">
                <div class="gi-modal-error" id="giSupplierModalError"></div>
                <div class="gi-grid-2">
                    <div class="gi-field">
                        <label>Tên nhà cung cấp *</label>
                        <input class="gi-control" name="name" maxlength="191" required placeholder="VD: Công ty TNHH ABC">
                    </div>
                    <div class="gi-field">
                        <label>Số điện thoại</label>
                        <input class="gi-control" name="phone" maxlength="80" placeholder="Không bắt buộc">
                    </div>
                    <div class="gi-field">
                        <label>Mã số thuế</label>
                        <input class="gi-control" name="tax_code" maxlength="80" placeholder="Không bắt buộc">
                    </div>
                    <div class="gi-field">
                        <label>Địa chỉ</label>
                        <input class="gi-control" name="address" maxlength="500" placeholder="Địa chỉ giao dịch / xuất hóa đơn">
                    </div>
                </div>
            </div>
            <div class="gi-modal-foot">
                <button type="button" class="gi-btn" onclick="giCloseModal('giSupplierModal')">Hủy</button>
                <button type="submit" class="gi-btn gi-btn-primary" id="giSupplierSaveButton">
                    <i class="bi bi-check2-circle"></i> Lưu nhà cung cấp
                </button>
            </div>
        </form>
    </div>
</div>

<div class="gi-modal" id="giProductModal" aria-hidden="true" onclick="giModalBackdrop(event, 'giProductModal')">
    <div class="gi-modal-dialog" role="dialog" aria-modal="true" aria-labelledby="giProductModalTitle">
        <div class="gi-modal-head">
            <div>
                <div class="gi-modal-title" id="giProductModalTitle">Chọn sản phẩm nhập kho</div>
                <div class="gi-modal-sub">Tìm theo tên hoặc SKU, sau đó bấm một lần để chọn.</div>
            </div>
            <button type="button" class="gi-modal-close" onclick="giCloseModal('giProductModal')">×</button>
        </div>
        <div class="gi-modal-body">
            <div class="gi-product-modal-search">
                <input class="gi-control" id="giProductModalSearch" autocomplete="off" placeholder="Nhập tên sản phẩm hoặc SKU..." oninput="giRenderProducts(this.value)">
            </div>
            <div class="gi-product-results" id="giProductResults"></div>
        </div>
        <div class="gi-modal-foot">
            <span class="gi-muted" id="giProductResultCount" style="margin-right:auto"></span>
            <button type="button" class="gi-btn" onclick="giCloseModal('giProductModal')">Đóng</button>
        </div>
    </div>
</div>


<template id="giItemTemplate">
    <div class="gi-item">
        <span class="gi-line-number">#1</span>

        <div class="gi-field">
            <label>Sản phẩm *</label>
            <input type="hidden" class="js-product-id" data-name="product_id" value="">
            <div class="gi-product-picker-wrap">
                <button type="button" class="gi-product-picker" onclick="giOpenProductModal(this)">
                    <span class="gi-product-picker-icon"><i class="bi bi-box-seam"></i></span>
                    <span class="gi-product-picker-copy">
                        <strong class="js-product-name">Chọn sản phẩm</strong>
                        <small class="js-product-meta">Bấm để tìm theo tên hoặc SKU</small>
                    </span>
                    <i class="bi bi-chevron-down gi-product-picker-arrow"></i>
                </button>
                <a class="gi-btn gi-btn-soft gi-open-product js-open-product" href="#" target="_blank" rel="noopener" title="Mở hồ sơ sản phẩm" style="display:none">
                    <i class="bi bi-box-arrow-up-right"></i>
                </a>
            </div>
        </div>

        <div class="gi-field">
            <label>Số lượng</label>
            <input class="gi-control" data-name="qty" type="number" min="0.001" step="0.001" value="1" required oninput="giCalcRow(this)">
        </div>

        <div class="gi-field">
            <label>Đơn giá</label>
            <input class="gi-control" data-name="unit_price" type="text" inputmode="decimal" autocomplete="off" value="0" oninput="giCalcRow(this)">
        </div>

        <div class="gi-field">
            <label>VAT %</label>
            <input class="gi-control" data-name="vat_percent" type="number" min="0" max="100" step="1" value="0" oninput="giCalcRow(this)">
        </div>

        <div class="gi-field">
            <label>Thành tiền</label>
            <input class="gi-control gi-total-control" data-total readonly value="0 đ">
        </div>

        <div class="gi-field">
            <label>Ghi chú</label>
            <input class="gi-control" data-name="note" placeholder="Không bắt buộc">
        </div>

        <button type="button" class="gi-remove" title="Xóa dòng" onclick="giRemoveItem(this)">×</button>
    </div>
</template>


@php
    $giProductPickerData = $products->map(function ($product) {
        return [
            'id' => (int) $product->id,
            'sku' => (string) ($product->sku ?? ''),
            'name' => (string) ($product->name ?? ''),
            'unit' => (string) ($product->unit ?? ''),
            'stock' => (float) ($product->stock_qty ?? 0),
            'url' => route('products.show', $product->id),
        ];
    })->values();
@endphp

<script>
    const giProducts = @json($giProductPickerData);
    const giSupplierStoreUrl = @json(route('product-goods-receipts.suppliers.store'));
    let giActiveProductRow = null;

    function giNormalize(value) {
        return (value || '')
            .toString()
            .toLowerCase()
            .normalize('NFD')
            .replace(/[\u0300-\u036f]/g, '');
    }

    function giParseNumber(value) {
        if (typeof value === 'number') {
            return Number.isFinite(value) ? value : 0;
        }

        let raw = String(value ?? '')
            .trim()
            .replace(/\s+/g, '')
            .replace(/đ/gi, '')
            .replace(/[^0-9,.\-]/g, '');

        if (!raw) {
            return 0;
        }

        const negative = raw.startsWith('-');
        raw = raw.replace(/-/g, '');

        const commaCount = (raw.match(/,/g) || []).length;
        const dotCount = (raw.match(/\./g) || []).length;
        const lastComma = raw.lastIndexOf(',');
        const lastDot = raw.lastIndexOf('.');

        if (commaCount && dotCount) {
            // Có cả . và ,: dấu xuất hiện cuối cùng là dấu thập phân,
            // dấu còn lại là phân cách hàng nghìn.
            // 34.000.000,56 -> 34000000.56
            // 34,000,000.56 -> 34000000.56
            if (lastComma > lastDot) {
                raw = raw.replace(/\./g, '').replace(',', '.');
            } else {
                raw = raw.replace(/,/g, '');
            }
        } else if (commaCount) {
            const parts = raw.split(',');

            if (commaCount > 1) {
                // 1,250,000 -> 1250000
                raw = parts.join('');
            } else if (
                (parts[1] || '').length === 3
                && (parts[0] || '').length <= 3
            ) {
                // 340,000 => 340000 (phan cach hang nghin).
                // 150000000,000 => 150000000.000 (3 so le).
                raw = parts.join('');
            } else {
                // 34000000,56 -> 34000000.56
                raw = parts[0] + '.' + (parts[1] || '');
            }
        } else if (dotCount) {
            const parts = raw.split('.');

            if (dotCount > 1) {
                // 1.250.000 -> 1250000
                raw = parts.join('');
            } else if (
                (parts[1] || '').length === 3
                && (parts[0] || '').length <= 3
            ) {
                // 340.000 => 340000.
                // 150000000.000 => 150000000.000.
                raw = parts.join('');
            }
            // Còn lại giữ dấu . làm thập phân: 34000000.56
        }

        const number = Number.parseFloat((negative ? '-' : '') + raw);

        return Number.isFinite(number) ? number : 0;
    }

    function giMoney(value) {
        const number = Math.max(0, giParseNumber(value));

        return number.toLocaleString('vi-VN', {
            minimumFractionDigits: 0,
            maximumFractionDigits: 4
        }) + ' đ';
    }

    function giCalcRow(element) {
        const row = element.closest('.gi-item');

        if (!row) {
            return;
        }

        const qty = giParseNumber(
            row.querySelector('[data-name="qty"]')?.value
        );

        const price = giParseNumber(
            row.querySelector('[data-name="unit_price"]')?.value
        );

        const vat = giParseNumber(
            row.querySelector('[data-name="vat_percent"]')?.value
        );

        const total = qty * price * (1 + (vat / 100));
        const totalInput = row.querySelector('[data-total]');

        if (totalInput) {
            totalInput.dataset.value = String(total);
            totalInput.value = giMoney(total);
        }

        giCalcSummary();
    }

    function giOpenModal(id) {
        const modal = document.getElementById(id);
        if (!modal) return;
        modal.classList.add('is-open');
        modal.setAttribute('aria-hidden', 'false');
        document.body.style.overflow = 'hidden';
    }

    function giCloseModal(id) {
        const modal = document.getElementById(id);
        if (!modal) return;
        modal.classList.remove('is-open');
        modal.setAttribute('aria-hidden', 'true');
        document.body.style.overflow = '';
        if (id === 'giProductModal') giActiveProductRow = null;
    }

    function giModalBackdrop(event, id) {
        if (event.target && event.target.id === id) giCloseModal(id);
    }

    function giOpenSupplierModal() {
        const form = document.getElementById('giSupplierCreateForm');
        const error = document.getElementById('giSupplierModalError');
        if (form) form.reset();
        if (error) {
            error.textContent = '';
            error.classList.remove('is-visible');
        }
        giOpenModal('giSupplierModal');
        setTimeout(function () {
            form?.querySelector('[name="name"]')?.focus();
        }, 80);
    }

    function giSupplierChanged(select) {
        const option = select && select.options ? select.options[select.selectedIndex] : null;
        const setValue = function (id, value) {
            const element = document.getElementById(id);
            if (element) element.value = value || '';
        };

        setValue('giSupplierName', option?.dataset?.name || '');
        setValue('giSupplierPhone', option?.dataset?.phone || '');
        setValue('giSupplierTaxCode', option?.dataset?.taxCode || '');
        setValue('giSupplierAddress', option?.dataset?.address || '');
    }

    async function giCreateSupplier(event) {
        event.preventDefault();
        const form = event.currentTarget;
        const button = document.getElementById('giSupplierSaveButton');
        const error = document.getElementById('giSupplierModalError');
        const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

        if (error) {
            error.textContent = '';
            error.classList.remove('is-visible');
        }
        if (button) {
            button.disabled = true;
            button.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Đang lưu';
        }

        try {
            const response = await fetch(giSupplierStoreUrl, {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': token,
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: new FormData(form)
            });
            const payload = await response.json();
            if (!response.ok || !payload.ok || !payload.supplier) {
                throw new Error(payload.message || 'Không thể tạo nhà cung cấp.');
            }

            const supplier = payload.supplier;
            const select = document.getElementById('giSupplier');
            let option = Array.from(select.options).find(function (item) {
                return String(item.value) === String(supplier.id);
            });

            if (!option) {
                option = document.createElement('option');
                option.value = supplier.id;
                select.appendChild(option);
            }

            option.textContent = supplier.name;
            option.dataset.name = supplier.name || '';
            option.dataset.phone = supplier.phone || '';
            option.dataset.taxCode = supplier.tax_code || '';
            option.dataset.address = supplier.address || '';
            select.value = String(supplier.id);
            giSupplierChanged(select);
            giCloseModal('giSupplierModal');
        } catch (exception) {
            if (error) {
                error.textContent = exception.message || 'Có lỗi khi tạo nhà cung cấp.';
                error.classList.add('is-visible');
            }
        } finally {
            if (button) {
                button.disabled = false;
                button.innerHTML = '<i class="bi bi-check2-circle"></i> Lưu nhà cung cấp';
            }
        }
    }

    function giOpenProductModal(button) {
        giActiveProductRow = button.closest('.gi-item');
        const search = document.getElementById('giProductModalSearch');
        if (search) search.value = '';
        giRenderProducts('');
        giOpenModal('giProductModal');
        setTimeout(function () { search?.focus(); }, 80);
    }

    function giRenderProducts(keyword) {
        const target = document.getElementById('giProductResults');
        const count = document.getElementById('giProductResultCount');
        if (!target) return;

        const normalized = giNormalize(keyword);
        const matches = giProducts.filter(function (product) {
            return !normalized || giNormalize(product.name + ' ' + product.sku).includes(normalized);
        });
        const visible = matches.slice(0, 120);
        target.innerHTML = '';

        if (!visible.length) {
            const empty = document.createElement('div');
            empty.className = 'gi-modal-empty';
            empty.textContent = 'Không tìm thấy sản phẩm phù hợp.';
            target.appendChild(empty);
        } else {
            visible.forEach(function (product) {
                const item = document.createElement('button');
                item.type = 'button';
                item.className = 'gi-product-result';
                item.addEventListener('click', function () { giChooseProduct(product.id); });

                const copy = document.createElement('span');
                const name = document.createElement('span');
                const meta = document.createElement('span');
                const stock = document.createElement('span');
                name.className = 'gi-product-result-name';
                meta.className = 'gi-product-result-meta';
                stock.className = 'gi-product-result-stock';
                name.textContent = product.name || ('Sản phẩm #' + product.id);
                meta.textContent = (product.sku || 'Chưa có SKU') + (product.unit ? ' · ĐVT: ' + product.unit : '');
                stock.textContent = 'Tồn ' + Number(product.stock || 0).toLocaleString('vi-VN');
                copy.appendChild(name);
                copy.appendChild(meta);
                item.appendChild(copy);
                item.appendChild(stock);
                target.appendChild(item);
            });
        }

        if (count) {
            count.textContent = matches.length > visible.length
                ? 'Hiển thị ' + visible.length + '/' + matches.length + ' sản phẩm — nhập thêm từ khóa để thu hẹp.'
                : matches.length + ' sản phẩm phù hợp';
        }
    }

    function giChooseProduct(productId) {
        const product = giProducts.find(function (item) {
            return String(item.id) === String(productId);
        });
        const row = giActiveProductRow;
        if (!product || !row) return;

        const idInput = row.querySelector('.js-product-id');
        const picker = row.querySelector('.gi-product-picker');
        const name = row.querySelector('.js-product-name');
        const meta = row.querySelector('.js-product-meta');
        const link = row.querySelector('.js-open-product');

        if (idInput) idInput.value = product.id;
        if (name) name.textContent = product.name || ('Sản phẩm #' + product.id);
        if (meta) {
            meta.textContent = (product.sku || 'Chưa có SKU')
                + ' · Tồn ' + Number(product.stock || 0).toLocaleString('vi-VN')
                + (product.unit ? ' ' + product.unit : '');
        }
        if (picker) picker.classList.add('is-selected');
        if (link) {
            link.href = product.url;
            link.style.display = 'inline-flex';
        }

        giCloseModal('giProductModal');
    }

    function giResetProductRow(row) {
        if (!row) return;
        const idInput = row.querySelector('.js-product-id');
        const picker = row.querySelector('.gi-product-picker');
        const name = row.querySelector('.js-product-name');
        const meta = row.querySelector('.js-product-meta');
        const link = row.querySelector('.js-open-product');
        if (idInput) idInput.value = '';
        if (name) name.textContent = 'Chọn sản phẩm';
        if (meta) meta.textContent = 'Bấm để tìm theo tên hoặc SKU';
        if (picker) picker.classList.remove('is-selected');
        if (link) {
            link.href = '#';
            link.style.display = 'none';
        }
    }

    function giReindexItems() {
        document.querySelectorAll('#giItems .gi-item').forEach(function (row, index) {
            const number = row.querySelector('.gi-line-number');

            if (number) {
                number.textContent = '#' + (index + 1);
            }

            row.querySelectorAll('[data-name]').forEach(function (element) {
                element.name = 'items[' + index + '][' + element.dataset.name + ']';
            });
        });

        giCalcSummary();
    }

    function giAddItem() {
        const template = document.getElementById('giItemTemplate');
        const target = document.getElementById('giItems');

        if (!template || !target) {
            return;
        }

        target.appendChild(template.content.cloneNode(true));
        giReindexItems();
    }

    function giRemoveItem(button) {
        const rows = document.querySelectorAll('#giItems .gi-item');

        if (rows.length <= 1) {
            const row = button.closest('.gi-item');

            if (row) {
                row.querySelectorAll('input').forEach(function (input) {
                    if (input.hasAttribute('readonly')) {
                        input.value = '0 đ';
                        input.dataset.value = '0';
                    } else if (input.type === 'number') {
                        input.value = input.dataset.name === 'qty' ? '1' : '0';
                    } else {
                        input.value = '';
                    }
                });

                giResetProductRow(row);
            }
        } else {
            button.closest('.gi-item')?.remove();
        }

        giReindexItems();
    }

    function giCalcSummary() {
        let total = 0;
        const rows = document.querySelectorAll('#giItems .gi-item');

        rows.forEach(function (row) {
            total += giParseNumber(
                row.querySelector('[data-total]')?.dataset.value
            );
        });

        const paidInput = document.getElementById('giPaidAmount');
        const paid = Math.max(
            0,
            giParseNumber(paidInput?.value)
        );

        const debt = Math.max(0, total - paid);
        const totalElement = document.getElementById('giSummaryTotal');
        const paidElement = document.getElementById('giSummaryPaid');
        const debtElement = document.getElementById('giSummaryDebt');
        const linesElement = document.getElementById('giSummaryLines');

        if (totalElement) totalElement.textContent = giMoney(total);
        if (paidElement) paidElement.textContent = giMoney(paid);
        if (debtElement) debtElement.textContent = giMoney(debt);
        if (linesElement) linesElement.textContent = String(rows.length);
    }

    function giSyncWarehouseSummary() {
        const select = document.getElementById('giWarehouse');
        const target = document.getElementById('giSummaryWarehouse');

        if (!select || !target) {
            return;
        }

        const option = select.options[select.selectedIndex];
        target.textContent = select.value ? option.textContent.trim() : 'Chưa chọn kho';
    }

    document.addEventListener('DOMContentLoaded', function () {
        document.addEventListener('blur', function (event) {
            const input = event.target;

            if (!input || !input.matches('[data-name="unit_price"]')) {
                return;
            }

            const number = giParseNumber(input.value);

            input.value = number.toLocaleString('vi-VN', {
                minimumFractionDigits: 0,
                maximumFractionDigits: 4
            });

            giCalcRow(input);
        }, true);

        document.addEventListener('input', function (event) {
            const input = event.target;

            if (!input || !input.matches('[data-name="unit_price"]')) {
                return;
            }

            input.value = input.value.replace(/[^0-9,.]/g, '');
        });

        giAddItem();
        giSyncWarehouseSummary();

        const warehouse = document.getElementById('giWarehouse');
        if (warehouse) {
            warehouse.addEventListener('change', giSyncWarehouseSummary);
        }

        const supplier = document.getElementById('giSupplier');
        if (supplier && supplier.value) giSupplierChanged(supplier);

        const form = document.getElementById('goodsReceiptForm');
        if (form) {
            form.addEventListener('submit', function (event) {
                form.querySelectorAll('[data-name="unit_price"]').forEach(function (input) {
                    input.value = String(giParseNumber(input.value));
                });

                const paidInput = document.getElementById('giPaidAmount');

                if (paidInput) {
                    paidInput.value = String(
                        giParseNumber(paidInput.value)
                    );
                }

                const missingProduct = Array.from(document.querySelectorAll('#giItems .js-product-id')).some(function (input) {
                    return !input.value;
                });
                if (missingProduct) {
                    event.preventDefault();
                    alert('Vui lòng chọn sản phẩm cho tất cả các dòng hàng hóa.');
                }
            });
        }

        document.addEventListener('keydown', function (event) {
            if (event.key !== 'Escape') return;
            giCloseModal('giSupplierModal');
            giCloseModal('giProductModal');
        });

        giCalcSummary();
    });
</script>
@endsection

<script>
/* EGO_PAID_AMOUNT_DECIMAL_FIX_V1 */
document.addEventListener('DOMContentLoaded', function () {
    const paidInput = document.getElementById('giPaidAmount');

    if (!paidInput) {
        return;
    }

    /*
     * Cho phép:
     * 34000000,56
     * 34.000.000,56
     * 34000000.56
     */
    paidInput.addEventListener('input', function () {
        this.value = this.value.replace(/[^0-9,.]/g, '');

        if (typeof giCalcSummary === 'function') {
            giCalcSummary();
        }
    });

    /*
     * Khi rời ô:
     * 34000000,56 -> 34.000.000,56
     */
    paidInput.addEventListener('blur', function () {
        if (typeof giParseNumber !== 'function') {
            return;
        }

        const value = giParseNumber(this.value);

        this.value = value.toLocaleString('vi-VN', {
            minimumFractionDigits: 0,
            maximumFractionDigits: 4
        });

        if (typeof giCalcSummary === 'function') {
            giCalcSummary();
        }
    });

    /*
     * Trước khi submit:
     * 34.000.000,56 -> 34000000.56
     */
    const form = paidInput.closest('form');

    if (form) {
        form.addEventListener('submit', function () {
            if (typeof giParseNumber === 'function') {
                paidInput.value = String(
                    giParseNumber(paidInput.value)
                );
            }
        }, true);
    }
});
</script>
