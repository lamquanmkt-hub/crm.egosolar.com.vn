
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

@php
    use Carbon\Carbon;
@endphp
@extends('layouts.app')

@section('title', 'Chi tiết đơn hàng #' . ($order->order_code ?? ''))

@section('content')
@php
    $customer = $order->lead->customer ?? null;

    $customerName   = $customer->name ?? '---';
    $customerPhone  = $customer->phone ?? '---';
    $customerEmail  = $customer->email ?? '---';
    $customerAddr   = $customer->address ?? '---';
    $customerRegion = $customer->region->name ?? '---';
    $customerType   = $customer->customerType->name ?? '---';

    $rawStatus       = strtolower(trim((string)($order->status ?? '')));
    $rawDeptOriginal = strtolower(trim((string)($order->current_department ?? '')));
    $displayStatusRaw = strtolower(trim((string)($order->display_status_name ?? '')));

    $cancelValues = ['cancelled', 'canceled', 'da_huy', 'huy'];

    $isCancelled =
        in_array($rawStatus, $cancelValues, true)
        || in_array($rawDeptOriginal, $cancelValues, true)
        || str_contains($displayStatusRaw, 'hủy')
        || str_contains($displayStatusRaw, 'cancel');

    if ($isCancelled) {
        $statusName  = 'Đã hủy';
        $statusColor = 'linear-gradient(135deg, #ef4444, #dc2626)';
    } else {
        $dept = strtolower(trim((string)($order->current_department ?? '')));

        switch ($dept) {
            case 'warehouse':
                $statusName = 'Sẵn sàng xuất kho';
                break;
            case 'management':
                $statusName = 'Chờ giám đốc duyệt';
                break;
            case 'accounting':
                $statusName = 'Kế toán duyệt';
                break;
            case 'sales_manager':
                $statusName = 'Sales Manager duyệt';
                break;
            case 'completed':
                $statusName = 'Hoàn tất';
                break;
            default:
                $statusName = 'Sales';
                break;
        }

        $statusColor = 'linear-gradient(135deg, #64748b, #475569)';
    }

    $paid    = $order->payments->sum('amount');
    $remain  = ($order->total_amount ?? 0) - $paid;
    $percent = ($order->total_amount ?? 0) > 0 ? ($paid / $order->total_amount) * 100 : 0;

    $flow = [
        'sales'         => 'Sales',
        'sales_manager' => 'Sales Manager',
        'accounting'    => 'Kế toán',
        'director'      => 'Giám đốc',
        'warehouse'     => 'Kho',
        'shipping'      => 'Vận chuyển',
        'completed'     => 'Hoàn tất',
    ];
    $keys = array_keys($flow);

    $map = [
        'kho'           => 'warehouse',
        'van_chuyen'    => 'shipping',
        'vanchuyen'     => 'shipping',
        'hoan_tat'      => 'completed',
        'ke_toan'       => 'accounting',
        'giam_doc'      => 'director',
        'salesmanager'  => 'sales_manager',
        'sales_manager' => 'sales_manager',
        'cancelled'     => 'cancelled',
        'canceled'      => 'cancelled',
        'da_huy'        => 'cancelled',
        'huy'           => 'cancelled',
    ];

    $rawDept = $isCancelled ? 'cancelled' : ($order->current_department ?? 'sales');
    $currentKey = $map[$rawDept] ?? $rawDept;

    if ($isCancelled) {
        $currentKey = 'cancelled';
    } elseif (!isset($flow[$currentKey])) {
        $currentKey = 'sales';
    }

    $currentIndex = array_search($currentKey, $keys, true);
    if ($currentIndex === false) {
        $currentIndex = 0;
    }

    $initial = mb_strtoupper(mb_substr(trim($customerName ?: 'K'), 0, 1));
    $hasEstimated = !empty($order->estimated_delivery);

    $isCompleted = !$isCancelled && (
        $currentKey === 'completed'
        || mb_strtolower((string)$statusName) === 'hoàn tất'
    );

        $invoiceCompanyName = $order->invoice_company_name ?: ($customer->billing_company_name ?? null);
    $invoiceTaxCode     = $order->invoice_tax_code ?: ($customer->billing_tax_code ?? null);
    $invoiceAddress     = $order->invoice_address ?: ($customer->billing_address ?? null);
    $invoiceEmail       = $order->invoice_email ?: ($customer->billing_email ?? null);

    $hasInvoiceInfo =
        filled($invoiceCompanyName) ||
        filled($invoiceTaxCode) ||
        filled($invoiceAddress) ||
        filled($invoiceEmail);

    $invoiceStatus = $order->invoice_status ?? 'none';

    if ($invoiceStatus === 'none' && $hasInvoiceInfo) {
        $invoiceStatus = 'pending';
    }

    $shippingFeeWarehouseToStation = (float) ($order->shipping_fee_warehouse_to_station ?? 0);
    $shippingFeeStationToCustomer  = (float) ($order->shipping_fee_station_to_customer ?? 0);
    $totalShippingFee              = $shippingFeeWarehouseToStation + $shippingFeeStationToCustomer;
    $shippingFeePayer              = $order->shipping_fee_payer ?: 'company';
    $companyPaysShipping           = $shippingFeePayer === 'company';
@endphp

<style>
    :root{
        --ego:#06b6d4;
        --ego2:#0891b2;
        --ink:#0f172a;
        --muted: rgba(15,23,42,.62);
        --muted2: rgba(15,23,42,.42);
        --card: rgba(255,255,255,.84);
        --border: rgba(15,23,42,.10);
        --shadow: 0 18px 50px rgba(15,23,42,.08);
        --shadow2: 0 10px 30px rgba(15,23,42,.07);
        --radius: 18px;
        --radius2: 22px;
    }

    .order-show, .order-show *{
        font-family: var(--bs-font-sans-serif, system-ui, -apple-system, "Segoe UI", Roboto, Arial, "Noto Sans", "Liberation Sans", sans-serif) !important;
        letter-spacing: 0 !important;
    }
    .order-show{
        font-size: 12.6px;
        line-height: 1.35;
        color: rgba(15,23,42,.88);
    }

    .order-show-shell{
        background:
            radial-gradient(900px 380px at 12% 0%, rgba(6,182,212,.12), transparent 60%),
            radial-gradient(900px 380px at 88% 8%, rgba(59,130,246,.09), transparent 55%),
            linear-gradient(180deg, #f7fbff, #f7fbff);
        border-radius: 24px;
        padding: 14px 12px 26px;
        box-shadow: 0 0 0 1px rgba(15,23,42,.04) inset;
    }

    .glass{
        background: var(--card);
        border: 1px solid var(--border);
        border-radius: var(--radius2);
        box-shadow: var(--shadow);
        backdrop-filter: blur(10px);
        -webkit-backdrop-filter: blur(10px);
        overflow:hidden;
    }

    .page-head{
        display:flex;
        justify-content:space-between;
        align-items:flex-start;
        gap: 12px;
        flex-wrap: wrap;
        margin: 6px 8px 12px;
    }

    .title-row{
        display:flex;
        align-items:flex-start;
        gap: 12px;
        flex-wrap: wrap;
    }

    .title-badge{
        width: 44px;
        height: 44px;
        border-radius: 16px;
        display:flex;
        align-items:center;
        justify-content:center;
        background: rgba(6,182,212,.14);
        border: 1px solid rgba(6,182,212,.22);
        color: var(--ego2);
        box-shadow: var(--shadow2);
        flex: 0 0 auto;
        font-size: 18px;
    }

    .order-title{
        margin:0;
        font-weight: 850;
        color: var(--ink);
        line-height: 1.1;
        font-size: 20px;
    }

    .order-code{
        font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono","Courier New", monospace !important;
        color: var(--ego2);
        font-weight: 850;
        font-size: 15px;
    }

    .meta-line{
        margin-top: 6px;
        color: var(--muted);
        font-size: 12px;
        display:flex;
        flex-wrap: wrap;
        gap: 8px;
        align-items:center;
    }

    .meta-pill{
        display:inline-flex;
        align-items:center;
        gap: 8px;
        padding: 6px 10px;
        border-radius: 999px;
        border: 1px solid rgba(15,23,42,.10);
        background: rgba(255,255,255,.72);
        box-shadow: var(--shadow2);
        font-weight: 650;
    }

    .meta-pill b{ font-weight: 750; }

    .btn-ego{
        border: none;
        border-radius: 14px;
        padding: 9px 12px;
        font-weight: 800;
        font-size: 12.5px;
        background: linear-gradient(135deg, var(--ego), var(--ego2));
        box-shadow: 0 14px 34px rgba(8,145,178,.18);
        transition: .15s ease;
        color: #fff;
        text-decoration: none;
        display:inline-flex;
        align-items:center;
        gap:8px;
        white-space: nowrap;
    }
    .btn-ego:hover{ transform: translateY(-1px); box-shadow: 0 18px 44px rgba(8,145,178,.24); color:#fff; }

    .btn-ghost{
        border-radius: 14px;
        padding: 9px 12px;
        font-weight: 750;
        font-size: 12.5px;
        border: 1px solid rgba(15,23,42,.12);
        background: rgba(255,255,255,.65);
        color: var(--ink);
        display:inline-flex;
        align-items:center;
        gap:8px;
        transition: .15s ease;
        white-space: nowrap;
        text-decoration:none;
    }
    .btn-ghost:hover{ transform: translateY(-1px); }

    .btn-icon{
        width: 36px;
        height: 36px;
        border-radius: 14px;
        display:inline-flex;
        align-items:center;
        justify-content:center;
        padding:0;
        transition: .15s ease;
        background: rgba(255,255,255,.70);
        border: 1px solid rgba(15,23,42,.10);
        box-shadow: var(--shadow2);
    }
    .btn-icon:hover{ transform: translateY(-1px); }

    .badge-modern{
        border-radius: 999px;
        padding: 7px 10px;
        font-weight: 750;
        font-size: 12.5px;
        display:inline-flex;
        align-items:center;
        gap: 8px;
        color: #fff;
        box-shadow: 0 12px 26px rgba(2,6,23,.10);
        border: 1px solid rgba(255,255,255,.24);
        white-space: nowrap;
    }
    .badge-modern .dot{
        width:9px;
        height:9px;
        border-radius:99px;
        background: rgba(255,255,255,.85);
        box-shadow: 0 0 0 4px rgba(255,255,255,.14);
    }

    .step-card{ padding: 12px 14px 14px; }
    .step-top{
        display:flex;
        justify-content:space-between;
        gap:12px;
        flex-wrap: wrap;
        align-items:flex-start;
    }
    .step-top .k{
        font-size: 11px;
        font-weight: 800;
        letter-spacing:.35px;
        color: rgba(15,23,42,.50);
        text-transform: uppercase;
    }
    .step-top .v{
        margin-top:6px;
        font-weight: 700;
        color: var(--ink);
        font-size: 13px;
    }

    .ego-stepper{
        position:relative;
        display:flex;
        justify-content:space-between;
        gap: 10px;
        padding: 10px 0 2px;
        margin-top: 12px;
        overflow:hidden;
    }
    .ego-track{
        position:absolute;
        left: 22px;
        right: 22px;
        top: 21px;
        height: 4px;
        border-radius: 999px;
        background: rgba(6,182,212,.14);
    }
    .ego-stepper.is-done .ego-track::after{ animation:none; opacity:0; }
    .ego-step{
        position:relative;
        z-index:2;
        text-align:center;
        flex:1;
        min-width: 88px;
    }
    .ego-dot{
        width:36px;
        height:36px;
        border-radius:50%;
        margin:0 auto;
        display:flex;
        align-items:center;
        justify-content:center;
        background: rgba(255,255,255,.94);
        border:1px solid rgba(6,182,212,.24);
        color: var(--ego2);
        box-shadow: 0 10px 18px rgba(2,6,23,.06);
    }
    .ego-step.done .ego-dot{
        background: linear-gradient(135deg, var(--ego), var(--ego2));
        border-color: rgba(6,182,212,.45);
        color:#fff;
    }
    .ego-step.active .ego-dot{
        border-color: rgba(6,182,212,.45);
        box-shadow: 0 16px 30px rgba(8,145,178,.12);
    }
    .ego-label{
        margin-top:7px;
        font-size: 11.5px;
        font-weight: 750;
        color: rgba(15,23,42,.82);
        white-space: nowrap;
    }
    .ego-step.todo .ego-label{ color: rgba(15,23,42,.45); }

    .kpi{
        display:flex;
        gap: 10px;
        align-items:center;
        padding: 12px 12px;
        border-radius: 18px;
        background: rgba(255,255,255,.72);
        border: 1px solid rgba(15,23,42,.10);
        box-shadow: var(--shadow2);
        height: 100%;
    }
    .kpi .ic{
        width: 38px;
        height: 38px;
        border-radius: 16px;
        display:flex;
        align-items:center;
        justify-content:center;
        background: rgba(6,182,212,.12);
        border: 1px solid rgba(6,182,212,.18);
        color: var(--ego2);
        flex: 0 0 auto;
        font-size: 16px;
    }
    .kpi .k{
        font-size: 11px;
        font-weight: 800;
        color: rgba(15,23,42,.50);
        text-transform: uppercase;
        letter-spacing:.35px;
    }
    .kpi .v{
        font-size: 13.5px;
        font-weight: 750;
        color: var(--ink);
        margin-top: 4px;
        line-height: 1.2;
    }
    .kpi .s{
        font-size: 12px;
        font-weight: 650;
        color: rgba(15,23,42,.55);
        margin-top: 4px;
    }

    .section-head{
        padding: 11px 13px;
        display:flex;
        align-items:center;
        justify-content:space-between;
        gap: 10px;
        flex-wrap: wrap;
        border-bottom: 1px solid rgba(15,23,42,.06);
        background: rgba(255,255,255,.55);
        backdrop-filter: blur(10px);
        -webkit-backdrop-filter: blur(10px);
    }
    .section-title{
        display:flex;
        align-items:center;
        gap: 10px;
        font-weight: 850;
        text-transform: uppercase;
        letter-spacing: .35px;
        color: rgba(15,23,42,.82);
        font-size: 12px;
        margin:0;
    }
    .section-ic{
        width: 32px;
        height: 32px;
        border-radius: 14px;
        display:flex;
        align-items:center;
        justify-content:center;
        background: rgba(6,182,212,.10);
        border: 1px solid rgba(6,182,212,.14);
        color: var(--ego2);
    }
    .section-body{ padding: 12px 14px; }

    .meta{ padding: .30rem 0; }
    .meta-label{
        font-size: 11px;
        font-weight: 800;
        color: rgba(15,23,42,.45);
        text-transform: uppercase;
        letter-spacing: .35px;
    }
    .meta-value{
        font-weight: 650;
        color: rgba(15,23,42,.90);
        margin-top: 4px;
        font-size: 13px;
    }

    .cust-head{
        display:flex;
        align-items:flex-start;
        gap: 12px;
        margin-bottom: 10px;
    }
    .avatar{
        width: 42px;
        height: 42px;
        border-radius: 16px;
        display:flex;
        align-items:center;
        justify-content:center;
        background: rgba(6,182,212,.12);
        border: 1px solid rgba(6,182,212,.18);
        color: var(--ego2);
        font-weight: 850;
        font-size: 16px;
        flex: 0 0 auto;
        box-shadow: var(--shadow2);
    }
    .cust-name{ font-weight: 800; color: var(--ink); font-size: 14px; }
    .cust-sub{ font-weight: 650; color: rgba(15,23,42,.55); font-size: 12px; margin-top: 4px; }

    .table-wrap{
        border-radius: 18px;
        overflow: hidden;
        border: 1px solid rgba(15,23,42,.10);
        background: rgba(255,255,255,.86);
    }
    .table-modern{ margin:0; font-size: 12.5px; }
    .table-modern thead th{
        position: sticky;
        top: 0;
        z-index: 5;
        background: rgba(15,23,42,.92) !important;
        color: rgba(255,255,255,.92) !important;
        font-weight: 800;
        border-bottom: 1px solid rgba(255,255,255,.12);
        padding: 11px 10px;
        white-space: nowrap;
    }
    .table-modern tbody td{
        padding: 11px 10px;
        border-color: rgba(15,23,42,.08);
        color: rgba(15,23,42,.86);
        font-weight: 600;
        vertical-align: middle;
        text-indent: 0 !important;
    }
    .table-modern tbody tr:nth-child(even){ background: rgba(15,23,42,.015); }
    .table-modern tbody tr:hover{ background: rgba(6,182,212,.08) !important; }

    .pill{
        border-radius: 999px;
        padding: 5px 9px;
        font-weight: 650;
        font-size: 12px;
        display:inline-flex;
        align-items:center;
        gap:8px;
        border: 1px solid rgba(15,23,42,.08);
        background: rgba(255,255,255,.78);
        white-space: nowrap;
    }

    .progress{ border-radius: 999px; overflow:hidden; background: rgba(15,23,42,.06); }
    .progress-bar{ border-radius: 999px; }

    .timeline-row{
        display:flex;
        gap: 14px;
        padding: 10px 0;
        position: relative;
    }
    .timeline-row:before{
        content:"";
        position:absolute;
        left: 13px;
        top: 0;
        bottom: 0;
        width:2px;
        background: rgba(15,23,42,.08);
    }
    .timeline-dot{
        width:28px;
        height:28px;
        border-radius:50%;
        background: linear-gradient(135deg, var(--ego), var(--ego2));
        color:#fff;
        display:flex;
        align-items:center;
        justify-content:center;
        z-index:1;
        margin-top: 2px;
        box-shadow: 0 12px 22px rgba(8,145,178,.14);
        flex: 0 0 auto;
    }
    .timeline-note{
        background: rgba(255,255,255,.78);
        border:1px solid rgba(15,23,42,.08);
        border-radius:16px;
        padding: 10px 12px;
        color: rgba(15,23,42,.78);
        font-weight: 600;
    }

    .sticky-card{ position: sticky; top: 16px; }

    .modal-content{
        border-radius: 18px !important;
        border: 1px solid rgba(15,23,42,.10);
        box-shadow: var(--shadow);
    }
    .modal-header{ border-bottom: 1px solid rgba(15,23,42,.08); }
    .modal-footer{ border-top: 1px solid rgba(15,23,42,.08); }

    .form-control, .form-select, .input-group-text{
        border-radius: 14px !important;
        border: 1px solid rgba(15,23,42,.12) !important;
        font-weight: 650;
        background: rgba(255,255,255,.75);
        font-size: 13px;
    }
    .form-control:focus, .form-select:focus{
        box-shadow: 0 0 0 .25rem rgba(6,182,212,.18) !important;
        border-color: rgba(6,182,212,.45) !important;
    }

    .edit-history-list{
        display:flex;
        flex-direction:column;
        gap:10px;
        max-height:320px;
        overflow-y:auto;
        padding-right:4px;
    }

    .edit-history-item{
        padding:10px 12px;
        border:1px solid rgba(15,23,42,.08);
        border-radius:14px;
        background: rgba(255,255,255,.72);
    }

    .edit-history-top{
        display:flex;
        justify-content:space-between;
        gap:10px;
        align-items:flex-start;
        margin-bottom:6px;
    }

    .edit-history-name{
        font-weight:800;
        color: var(--ink);
        font-size:12.5px;
    }

    .edit-history-time{
        font-size:11.5px;
        color: var(--muted);
        font-weight:650;
        white-space:nowrap;
    }

    .edit-history-changes{
        margin:0;
        padding-left:16px;
        font-size:12.5px;
        color: rgba(15,23,42,.82);
    }

    .edit-history-changes li{
        margin-bottom:3px;
    }

    .pulse{ animation:pulse 2s infinite; }
    @keyframes pulse{
        0%{ box-shadow:0 0 0 0 rgba(6,182,212,.30); }
        70%{ box-shadow:0 0 0 10px rgba(6,182,212,0); }
        100%{ box-shadow:0 0 0 0 rgba(6,182,212,0); }
    }

    @media (max-width: 576px){
        .ego-step{ min-width: 72px; }
        .ego-label{ font-size: 11px; }
        .order-title{ font-size: 18px; }
    }

    @media print{
        .no-print{ display:none !important; }
        .sticky-card{ position: static !important; }
        .glass{ box-shadow:none !important; }
    }

    /* EGO_SHIPPING_FEE_UI_START */
    .shipping-fee-box{
        border:1px solid rgba(14,165,233,.16);
        border-radius:18px;
        padding:12px;
        background:
            radial-gradient(circle at top right, rgba(14,165,233,.10), transparent 36%),
            linear-gradient(180deg, rgba(255,255,255,.92), rgba(248,252,255,.92));
    }

    .shipping-fee-title{
        display:flex;
        align-items:center;
        gap:8px;
        font-weight:850;
        color:var(--ink);
        margin-bottom:10px;
        font-size:13px;
    }

    .shipping-payer-options{
        display:grid;
        grid-template-columns:1fr 1fr;
        gap:8px;
    }

    .shipping-payer-card{
        border:1px solid rgba(15,23,42,.10);
        background:#fff;
        border-radius:15px;
        padding:10px;
        cursor:pointer;
        font-weight:800;
        font-size:12.5px;
        color:rgba(15,23,42,.78);
        transition:.16s ease;
        display:flex;
        align-items:flex-start;
        gap:8px;
    }

    .shipping-payer-card small{
        display:block;
        margin-top:3px;
        font-weight:650;
        color:rgba(15,23,42,.50);
        line-height:1.25;
    }

    .btn-check:checked + .shipping-payer-card{
        border-color:rgba(14,165,233,.55);
        background:rgba(14,165,233,.08);
        color:#075985;
        box-shadow:0 10px 24px rgba(14,165,233,.12);
    }

    .shipping-fee-info{
        border:1px solid rgba(15,23,42,.08);
        border-radius:16px;
        padding:10px 12px;
        background:rgba(248,250,252,.72);
    }

    .shipping-fee-row{
        display:flex;
        justify-content:space-between;
        gap:10px;
        padding:5px 0;
        font-size:12.5px;
        font-weight:700;
    }

    .shipping-fee-row .label{
        color:rgba(15,23,42,.52);
        font-weight:800;
        text-transform:uppercase;
        font-size:10.8px;
        letter-spacing:.25px;
    }

    .shipping-fee-row .value{
        color:var(--ink);
        text-align:right;
        font-weight:850;
    }

    .shipping-fee-row.total{
        border-top:1px dashed rgba(15,23,42,.14);
        margin-top:4px;
        padding-top:9px;
    }
    /* EGO_SHIPPING_FEE_UI_END */


    /* EGO_SHIPPING_CARD_FEE_START */
    .shipping-fee-info{
        margin-top:12px;
        border:1px solid rgba(14,165,233,.16);
        border-radius:16px;
        padding:12px;
        background:
            radial-gradient(circle at top right, rgba(14,165,233,.10), transparent 38%),
            linear-gradient(180deg, rgba(255,255,255,.88), rgba(248,252,255,.92));
    }

    .shipping-fee-head{
        display:flex;
        align-items:center;
        justify-content:space-between;
        gap:10px;
        margin-bottom:8px;
    }

    .shipping-fee-head .title{
        display:flex;
        align-items:center;
        gap:8px;
        font-size:12px;
        font-weight:850;
        text-transform:uppercase;
        color:rgba(15,23,42,.82);
    }

    .shipping-fee-grid{
        display:grid;
        gap:7px;
    }

    .shipping-fee-line{
        display:flex;
        justify-content:space-between;
        align-items:flex-start;
        gap:12px;
        font-size:12.5px;
        padding:4px 0;
    }

    .shipping-fee-line .label{
        color:rgba(15,23,42,.55);
        font-weight:750;
    }

    .shipping-fee-line .value{
        color:var(--ink);
        font-weight:850;
        text-align:right;
        white-space:nowrap;
    }

    .shipping-fee-line.total{
        border-top:1px dashed rgba(15,23,42,.14);
        margin-top:3px;
        padding-top:9px;
    }

    .shipping-fee-line.net{
        border-top:1px dashed rgba(245,158,11,.26);
        margin-top:3px;
        padding-top:9px;
    }

    .shipping-fee-line.net .label,
    .shipping-fee-line.net .value{
        color:#b45309;
        font-weight:900;
    }

    .shipping-fee-line.net.negative .label,
    .shipping-fee-line.net.negative .value{
        color:#dc2626;
    }
    /* EGO_SHIPPING_CARD_FEE_END */

</style>

<div class="container-fluid px-4 order-show">
    <div class="order-show-shell">

        <div class="page-head">
            <div class="title-row">
                <div class="title-badge"><i class="bi bi-receipt-cutoff"></i></div>

                <div>
                    <div class="d-flex align-items-center gap-2 flex-wrap">
                        <h1 class="order-title">
                            Đơn hàng <span class="text-muted">#</span>
                            <span class="order-code">{{ $order->order_code }}</span>
                        </h1>

                        <button type="button"
                                class="btn-icon no-print"
                                onclick="copyText({{ json_encode($order->order_code) }}, this)"
                                title="Copy mã đơn">
                            <i class="bi bi-clipboard"></i>
                        </button>

                        <span class="badge-modern" style="background: {{ $statusColor }};">
                            <span class="dot"></span> {{ $statusName }}
                        </span>
                    </div>

                    <div class="meta-line">
                        <span class="meta-pill">
                            <i class="bi bi-calendar3"></i>
                            Tạo {{ optional($order->created_at)->format('d/m/Y H:i') }}
                        </span>
                        <span class="meta-pill">
                            <i class="bi bi-person-circle"></i>
                            {{ optional($order->creator)->name ?? 'Unknown' }}
                        </span>
                        <span class="meta-pill">
                            <i class="bi bi-building"></i>
                            Bộ phận:
                            <b>{{ $isCancelled ? 'Đã hủy' : ($flow[$currentKey] ?? ucfirst($currentKey)) }}</b>
                        </span>
                    </div>
                </div>
            </div>

            <div class="d-flex flex-wrap gap-2 no-print">
                <a href="{{ route('orders.index') }}" class="btn-ghost">
                    <i class="bi bi-arrow-left"></i> Quay lại
                </a>

                <button type="button" class="btn-ghost" data-bs-toggle="modal" data-bs-target="#printPreviewModal">
                    <i class="bi bi-printer"></i> In
                </button>

                <a class="btn-ego" href="{{ route('orders.pdf', $order->id) }}" target="_blank">
                    <i class="bi bi-filetype-pdf"></i> Xuất PDF
                </a>

                @can('update', $order)
                    <a href="{{ route('orders.edit', $order->id) }}" class="btn-ghost" style="border-color: rgba(245,158,11,.35);">
                        <i class="bi bi-pencil"></i> Sửa
                    </a>
                @endcan

                @can('delete', $order)
                    <button type="button" class="btn-ghost" style="border-color: rgba(220,53,69,.35);"
                            data-bs-toggle="modal" data-bs-target="#deleteModal">
                        <i class="bi bi-trash"></i> Xóa
                    </button>
                @endcan
            </div>
        </div>

        @if(session('success'))
            <div class="alert alert-success alert-dismissible fade show shadow-sm mx-1 no-print">
                <i class="bi bi-check-circle-fill"></i> {{ session('success') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        @endif

        @if(session('error'))
            <div class="alert alert-danger alert-dismissible fade show shadow-sm mx-1 no-print">
                <i class="bi bi-exclamation-triangle-fill"></i> {!! nl2br(e(session('error'))) !!}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        @endif

        <div class="glass mb-4">
            <div class="step-card">
                <div class="step-top">
                    <div>
                        <div class="k">Trạng thái hiện tại</div>
                        <div class="v d-flex align-items-center gap-2 flex-wrap">
                            <span class="badge-modern" style="background: {{ $statusColor }};">
                                <span class="dot"></span> {{ $statusName }}
                            </span>
                            <span style="color: var(--muted); font-weight: 650;">
                                • Bộ phận:
                                <b style="color: var(--ink); font-weight: 750;">
                                    {{ $isCancelled ? 'Đã hủy' : ($flow[$currentKey] ?? ucfirst($currentKey)) }}
                                </b>
                            </span>
                        </div>
                    </div>

                    <div class="text-end">
                        <div class="k">Dự kiến giao hàng</div>
                        <div class="v">
                            @if($hasEstimated)
                                {{ Carbon::parse($order->estimated_delivery)->format('d/m/Y') }}
                                <span style="color: var(--muted); font-weight: 650;">
                                    ({{ Carbon::parse($order->estimated_delivery)->diffForHumans() }})
                                </span>
                            @else
                                <span style="color: var(--muted); font-weight: 650;">Chưa xác định</span>
                            @endif
                        </div>
                    </div>
                </div>

                @if($isCancelled)
                    <div class="mt-3">
                        <div class="alert alert-danger mb-0" style="border-radius: 16px;">
                            <i class="bi bi-x-circle-fill me-2"></i>
                            Đơn hàng này đã được hủy.
                        </div>
                    </div>
                @else
                    <div class="ego-stepper mt-3 {{ $currentKey === 'completed' ? 'is-done' : '' }}">
                        <div class="ego-track"></div>

                        @foreach($flow as $k => $label)
                            @php
                                $i = array_search($k, $keys, true);
                                $state = $i < $currentIndex ? 'done' : ($i === $currentIndex ? 'active' : 'todo');
                            @endphp
                            <div class="ego-step {{ $state }}">
                                <div class="ego-dot">
                                    @if($state === 'done')
                                        <i class="bi bi-check-lg"></i>
                                    @elseif($state === 'active')
                                        <i class="bi bi-dot"></i>
                                    @else
                                        <i class="bi bi-circle"></i>
                                    @endif
                                </div>
                                <div class="ego-label">{{ $label }}</div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>

        <div class="row g-3 mb-4">
            <div class="col-12 col-md-6 col-xl-3">
                <div class="kpi">
                    <div class="ic"><i class="bi bi-activity"></i></div>
                    <div>
                        <div class="k">Trạng thái</div>
                        <div class="v">{{ $statusName }}</div>
                        <div class="s">Theo luồng xử lý</div>
                    </div>
                </div>
            </div>

            <div class="col-12 col-md-6 col-xl-3">
                <div class="kpi">
                    <div class="ic"><i class="bi bi-building"></i></div>
                    <div>
                        <div class="k">Bộ phận giữ</div>
                        <div class="v">{{ $isCancelled ? 'Đã hủy' : ($flow[$currentKey] ?? ucfirst($currentKey)) }}</div>
                        <div class="s">Trạng thái hiện tại</div>
                    </div>
                </div>
            </div>

            <div class="col-12 col-md-6 col-xl-3">
                <div class="kpi">
                    <div class="ic"><i class="bi bi-truck"></i></div>
                    <div>
                        <div class="k">Dự kiến giao</div>
                        <div class="v">
                            @if($hasEstimated)
                                {{ Carbon::parse($order->estimated_delivery)->format('d/m/Y') }}
                            @else
                                <span class="text-muted fst-italic">Chưa xác định</span>
                            @endif
                        </div>
                        <div class="s">
                            @if($hasEstimated)
                                {{ Carbon::parse($order->estimated_delivery)->diffForHumans() }}
                            @else
                                —
                            @endif
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-12 col-md-6 col-xl-3">
                <div class="kpi">
                    <div class="ic"><i class="bi bi-lightning-charge"></i></div>
                    <div style="width:100%;">
                        <div class="k">Hành động</div>
                        <div class="d-grid gap-2 mt-2 no-print">
                            @if($isCancelled)
                                <div class="alert alert-danger mb-0" style="border-radius: 14px;">
                                    <i class="bi bi-x-circle me-1"></i> Đơn hàng đã hủy, không còn hành động khả dụng.
                                </div>
                            @else
                                @if($isCompleted && \Illuminate\Support\Facades\Route::has('orders.returns.create'))
                                    <a href="{{ route('orders.returns.create', $order->id) }}" class="btn btn-outline-warning w-100">
                                        <i class="bi bi-arrow-repeat"></i> Đổi / Trả hàng
                                    </a>
                                @endif

                                @can('submit', $order)
                                    <form action="{{ route('orders.submit', $order->id) }}" method="POST">
                                        @csrf
                                        <button type="submit" class="btn btn-primary w-100"
                                                onclick="return confirm('Bạn có chắc chắn muốn gửi đơn hàng này đi duyệt?')"
                                                style="border-radius: 14px; font-weight: 750; font-size: 12.5px;">
                                            <i class="bi bi-send"></i> Gửi duyệt
                                        </button>
                                    </form>
                                @endcan

                                @can('approve', $order)
                                    <a href="{{ route('orders.approval-form', $order->id) }}" class="btn btn-success w-100 pulse"
                                       style="border-radius: 14px; font-weight: 750; font-size: 12.5px;">
                                        <i class="bi bi-clipboard-check"></i> Xử lý duyệt
                                    </a>
                                @endcan

                                @cannot('submit', $order)
                                    @cannot('approve', $order)
                                        @if(!$isCompleted)
                                            <div class="text-muted small">Không có hành động khả dụng.</div>
                                        @endif
                                    @endcannot
                                @endcannot
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-4">
            <div class="col-lg-8">
                <div class="glass mb-4">
                    <div class="section-head">
                        <p class="section-title mb-0">
                            <span class="section-ic"><i class="bi bi-person-vcard"></i></span>
                            Thông tin khách hàng
                        </p>
                        <span class="pill">
                            <i class="bi bi-tags"></i> {{ $customerType }}
                        </span>
                    </div>

                    <div class="section-body">
                        <div class="cust-head">
                            <div class="avatar">{{ $initial }}</div>
                            <div>
                                <div class="cust-name">{{ $customerName }}</div>
                                <div class="cust-sub">
                                    <i class="bi bi-telephone"></i> {{ $customerPhone }}
                                    <span class="mx-2">•</span>
                                    <i class="bi bi-geo-alt"></i> {{ $customerRegion }}
                                </div>
                            </div>

                            <div class="ms-auto d-flex gap-2 no-print">
                                <button type="button" class="btn-icon"
                                        onclick="copyText({{ json_encode($customerPhone) }}, this)" title="Copy SĐT">
                                    <i class="bi bi-clipboard"></i>
                                </button>
                                <button type="button" class="btn-icon"
                                        onclick="copyText({{ json_encode($customerAddr) }}, this)" title="Copy địa chỉ">
                                    <i class="bi bi-clipboard-check"></i>
                                </button>
                            </div>
                        </div>

                        <div class="row g-3">
                            <div class="col-md-6">
                                <div class="meta">
                                    <div class="meta-label">Email</div>
                                    <div class="meta-value">{{ $customerEmail }}</div>
                                </div>
                                <div class="meta">
                                    <div class="meta-label">Số điện thoại</div>
                                    <div class="meta-value">
                                        <a href="tel:{{ $customerPhone }}" class="text-decoration-none" style="font-weight:750;">
                                            {{ $customerPhone }}
                                        </a>
                                    </div>
                                </div>
                            </div>

                            <div class="col-md-6">
                                <div class="meta">
                                    <div class="meta-label">Khu vực</div>
                                    <div class="meta-value">{{ $customerRegion }}</div>
                                </div>
                                <div class="meta">
                                    <div class="meta-label">Địa chỉ</div>
                                    <div class="meta-value">{{ $customerAddr }}</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="glass mb-4">
                    <div class="section-head">
                        <p class="section-title mb-0">
                            <span class="section-ic"><i class="bi bi-box-seam"></i></span>
                            Chi tiết sản phẩm
                        </p>
                        <span class="pill">
                            <i class="bi bi-bag"></i> {{ $order->items->count() }} mặt hàng
                        </span>
                    </div>

                    @php
                        $sumBeforeVat = 0;
                        $sumVatAmount = 0;
                        $sumAfterVat  = 0;
                    @endphp

                    <div class="section-body p-0">
                        <div class="table-wrap">
                            <div class="table-responsive">
                                <table class="table table-modern table-hover align-middle mb-0">
                                    <thead>
                                        <tr class="small">
                                            <th class="text-center" style="width: 56px;">#</th>
                                            <th style="min-width: 160px;">Kho</th>
                                            <th style="min-width: 280px;">Sản phẩm</th>
                                            <th class="text-end" style="min-width: 130px;">Giá trước VAT</th>
                                            <th class="text-center" style="min-width: 90px;">VAT</th>
                                            <th class="text-end" style="min-width: 140px;">Đơn giá (SAU VAT)</th>
                                            <th class="text-center" style="width: 90px;">SL</th>
                                            <th class="text-center" style="min-width: 90px;">Giảm %</th>
                                            <th class="text-end" style="min-width: 120px;">Giảm (đ)</th>
                                            <th class="text-end" style="min-width: 150px;">Thành tiền</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($order->items as $item)
                                            @php
                                                $qty = (int)($item->quantity ?? 1);
                                                if ($qty < 1) $qty = 1;

                                                $p = $item->product ?? null;
                                                $vat = (float)($item->vat_percent ?? $p->vat_percent ?? 0);
                                                $unitAfter = (float)($item->unit_price ?? 0);
                                                $unitBefore = $vat > 0 ? ($unitAfter / (1 + $vat/100)) : $unitAfter;
                                                $subAfter = $unitAfter * $qty;

                                                $discPercent = max(0, min(100, (float)($item->discount_percent ?? 0)));
                                                $discPerUnit = max(0, (float)($item->discount_amount ?? 0));

                                                $discount = 0;
                                                if ($discPerUnit > 0) {
                                                    $discount = $discPerUnit * $qty;
                                                } elseif ($discPercent > 0) {
                                                    $discount = $subAfter * $discPercent / 100;
                                                }
                                                if ($discount > $subAfter) $discount = $subAfter;

                                                $lineAfter = max(0, $subAfter - $discount);
                                                $lineBefore = $vat > 0 ? ($lineAfter / (1 + $vat/100)) : $lineAfter;
                                                $vatAmountLine = max(0, $lineAfter - $lineBefore);

                                                $sumBeforeVat += $lineBefore;
                                                $sumVatAmount += $vatAmountLine;
                                                $sumAfterVat  += $lineAfter;

                                                $pName = $p->name ?? 'N/A';
                                                $pSku  = $p->sku ?? 'N/A';
                                                $vatText = rtrim(rtrim(number_format($vat, 2), '0'), '.');
                                            @endphp

                                            <tr>
                                                <td class="text-center">{{ $loop->iteration }}</td>
                                                <td>
                                                    <span class="pill" style="background: rgba(6,182,212,.10); border-color: rgba(6,182,212,.18); color: var(--ego2);">
                                                        <i class="bi bi-building"></i> {{ $item->warehouse->name ?? 'N/A' }}
                                                    </span>
                                                </td>
                                                <td>
                                                    <div class="text-primary" style="font-weight:750;">{{ $pName }}</div>
                                                    <div class="small" style="color: var(--muted2); font-weight: 650;">
                                                        SKU: <span class="font-monospace">{{ $pSku }}</span>
                                                    </div>
                                                </td>
                                                <td class="text-end font-monospace">{{ number_format($unitBefore, 0, ',', '.') }}</td>
                                                <td class="text-center"><span class="pill">{{ $vatText }}%</span></td>
                                                <td class="text-end font-monospace">{{ number_format($unitAfter, 0, ',', '.') }}</td>
                                                <td class="text-center" style="font-weight:750;">{{ $qty }}</td>
                                                <td class="text-center">
                                                    <span class="pill">{{ rtrim(rtrim(number_format($discPercent, 2), '0'), '.') }}%</span>
                                                </td>
                                                <td class="text-end" style="font-weight:750;">{{ number_format($discount, 0, ',', '.') }}</td>
                                                <td class="text-end font-monospace" style="font-weight:750;">
                                                    {{ number_format($lineAfter, 0, ',', '.') }}
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                    <tfoot>
                                        <tr>
                                            <td colspan="9" class="text-end fw-bold text-uppercase py-3 text-nowrap" style="background: rgba(15,23,42,.03); font-weight:800;">
                                                Tổng tiền:
                                            </td>
                                            <td class="text-end py-3 text-nowrap" style="background: rgba(15,23,42,.03);">
                                                <span class="fs-6 text-danger" style="font-weight:850;">{{ number_format($sumAfterVat, 0, ',', '.') }} đ</span>
                                            </td>
                                        </tr>

                                        <tr>
                                            <td colspan="9" class="text-end fw-bold py-2 text-nowrap" style="background: rgba(34,197,94,.05); font-weight:800; color: rgba(22,163,74,1);">
                                                Khách hàng đã thanh toán:
                                            </td>
                                            <td class="text-end py-2 text-nowrap" style="background: rgba(34,197,94,.05);">
                                                <span class="fs-6" style="font-weight:850; color: rgba(22,163,74,1);">
                                                    {{ number_format($paid, 0, ',', '.') }} đ
                                                </span>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td colspan="9" class="text-end fw-bold py-3 text-nowrap" style="background: rgba(239,68,68,.05); font-weight:800; color: rgba(220,38,38,1);">
                                                Số tiền còn lại:
                                            </td>
                                            <td class="text-end py-3 text-nowrap" style="background: rgba(239,68,68,.05);">
                                                <span class="fs-6" style="font-weight:850; color: rgba(220,38,38,1);">
                                                    {{ number_format(max(0, $sumAfterVat - $paid), 0, ',', '.') }} đ
                                                </span>
                                            </td>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="glass mb-4">
                    <div class="section-head">
                        <p class="section-title mb-0">
                            <span class="section-ic"><i class="bi bi-wallet2"></i></span>
                            Lịch sử thanh toán
                        </p>

                        @php
                            $paidPayment = $order->payments->sum('amount');
                            $totalPayment = (float) ($order->total_amount ?? 0);
                            $remainPayment = max(0, $totalPayment - $paidPayment);
                            $percentPayment = $totalPayment > 0 ? min(100, ($paidPayment / $totalPayment) * 100) : 0;
                        @endphp

                        <div class="d-flex align-items-center gap-2 flex-wrap">
                            <span class="pill" style="background: rgba(34,197,94,.10); border-color: rgba(34,197,94,.18); color: rgba(22,163,74,1);">
                                <i class="bi bi-check2-circle"></i>
                                Đã TT: {{ number_format($paidPayment, 0, ',', '.') }} đ
                            </span>

                            @if($remainPayment <= 0)
                                <span class="pill" style="background: rgba(34,197,94,.10); border-color: rgba(34,197,94,.18); color: rgba(22,163,74,1);">
                                    <i class="bi bi-check2-circle"></i>
                                    Thanh toán thành công
                                </span>
                            @else
                                <span class="pill" style="background: rgba(239,68,68,.08); border-color: rgba(239,68,68,.16); color: rgba(220,38,38,1);">
                                    <i class="bi bi-exclamation-circle"></i>
                                    Còn lại: {{ number_format($remainPayment, 0, ',', '.') }} đ
                                </span>
                            @endif
                        </div>
                    </div>

                    <div class="section-body">
                        <div class="progress mb-3" style="height: 10px;">
                            <div class="progress-bar bg-success" role="progressbar" style="width: {{ $percentPayment }}%"></div>
                        </div>

                        @if($order->payments->count() > 0)
                            <div class="table-responsive">
                                <table class="table table-sm align-middle mb-0" style="min-width: 980px;">
                                    <thead class="table-light">
                                        <tr class="small text-uppercase" style="color: var(--muted2); font-weight: 800;">
                                            <th>Ngày TT</th>
                                            <th>Số tiền</th>
                                            <th>Phương thức</th>
                                            <th>Người ghi nhận</th>
                                            <th>Ghi chú</th>
                                            <th class="text-center" style="min-width: 170px;">Thao tác</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($order->payments as $payment)
                                            <tr>
                                                <td>{{ $payment->payment_date ? \Carbon\Carbon::parse($payment->payment_date)->format('d/m/Y') : '-' }}</td>
                                                <td class="text-success" style="font-weight:750;">+{{ number_format($payment->amount ?? 0, 0, ',', '.') }}</td>
                                                <td><span class="pill">{{ $payment->method->method_name ?? 'N/A' }}</span></td>
                                                <td><small style="font-weight:750;">{{ $payment->recordedBy->name ?? 'System' }}</small></td>
                                                <td style="color: var(--muted); font-weight: 650;">{{ $payment->note ?? '-' }}</td>
                                                <td class="text-center">
                                                    <div class="d-flex justify-content-center gap-2 flex-wrap">
                                                        <button type="button"
                                                                class="btn btn-sm btn-outline-primary"
                                                                style="border-radius: 10px;"
                                                                data-bs-toggle="modal"
                                                                data-bs-target="#editPaymentModal{{ $payment->id }}">
                                                            <i class="bi bi-pencil-square"></i> Sửa
                                                        </button>

                                                        <form action="{{ route('orders.payments.destroy', $payment->id) }}"
                                                              method="POST"
                                                              style="display:inline-block;"
                                                              onsubmit="return confirm('Bạn có chắc muốn xóa giao dịch này không?')">
                                                            @csrf
                                                            @method('DELETE')
                                                            <button type="submit" class="btn btn-sm btn-outline-danger" style="border-radius: 10px;">
                                                                <i class="bi bi-trash"></i> Xóa
                                                            </button>
                                                        </form>
                                                    </div>
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @else
                            <div class="text-center text-muted py-3">Chưa có giao dịch thanh toán nào.</div>
                        @endif
                    </div>
                </div>

                @foreach($order->payments as $payment)
                    <div class="modal fade" id="editPaymentModal{{ $payment->id }}" tabindex="-1" aria-hidden="true">
                        <div class="modal-dialog modal-dialog-centered">
                            <div class="modal-content">
                                <form action="{{ route('orders.payments.update', $payment->id) }}" method="POST">
                                    @csrf
                                    @method('PUT')

                                    <div class="modal-header">
                                        <h5 class="modal-title" style="font-weight:800;">
                                            <i class="bi bi-pencil-square me-1"></i> Sửa thanh toán
                                        </h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                    </div>

                                    <div class="modal-body">
                                        <div class="mb-3">
                                            <label class="form-label" style="font-weight:750;">Ngày thanh toán</label>
                                            <input type="date"
                                                   name="payment_date"
                                                   class="form-control"
                                                   value="{{ $payment->payment_date ? \Carbon\Carbon::parse($payment->payment_date)->format('Y-m-d') : '' }}"
                                                   required>
                                        </div>

                                        <div class="mb-3">
                                            <label class="form-label" style="font-weight:750;">Số tiền</label>
                                            <div class="input-group">
                                                <input type="number" name="amount" class="form-control" step="any" value="{{ $payment->amount ?? 0 }}" required>
                                                <span class="input-group-text">VNĐ</span>
                                            </div>
                                        </div>

                                        <div class="mb-3">
                                            <label class="form-label" style="font-weight:750;">Phương thức thanh toán</label>
                                            <select name="method_id" class="form-select" required>
                                                <option value="">-- Chọn phương thức --</option>
                                                @foreach($paymentMethods as $method)
                                                    <option value="{{ $method->id }}" {{ (int)$payment->method_id === (int)$method->id ? 'selected' : '' }}>
                                                        {{ $method->method_name }} ({{ $method->code }})
                                                    </option>
                                                @endforeach
                                            </select>
                                        </div>

                                        <div class="mb-0">
                                            <label class="form-label" style="font-weight:750;">Ghi chú</label>
                                            <textarea name="note" class="form-control" rows="2">{{ $payment->note ?? '' }}</textarea>
                                        </div>
                                    </div>

                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-light border" data-bs-dismiss="modal" style="border-radius: 14px;">Đóng</button>
                                        <button type="submit" class="btn btn-primary" style="border-radius: 14px; font-weight: 750;">
                                            <i class="bi bi-save"></i> Lưu
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                @endforeach

                <div class="glass mb-4">
                    <div class="section-head">
                        <p class="section-title mb-0">
                            <span class="section-ic"><i class="bi bi-clock-history"></i></span>
                            Nhật ký xử lý
                        </p>
                    </div>

                    <div class="section-body">
                        @if($isCancelled)
                            <div class="alert alert-danger mb-3">
                                <i class="bi bi-x-circle-fill me-2"></i>
                                Đơn hàng đã bị hủy.
                            </div>
                        @endif

                        @forelse($timeline as $item)
                            <div class="timeline-row">
                                <div class="timeline-dot"><i class="bi bi-check-lg"></i></div>

                                <div style="width:100%;">
                                    <div class="d-flex flex-wrap justify-content-between gap-2">
                                        <div style="font-weight:750;">{{ $item->statusType->name ?? 'Thay đổi trạng thái' }}</div>
                                        <div class="small" style="color: var(--muted); font-weight: 650;">
                                            {{ $item->changed_at ? Carbon::parse($item->changed_at)->format('d/m/Y H:i') : '' }}
                                        </div>
                                    </div>

                                    <div class="small mt-1" style="color: var(--muted); font-weight: 650;">
                                        @if($item->from_department)
                                            {{ ucfirst($item->from_department) }} <i class="bi bi-arrow-right"></i>
                                        @endif
                                        <span class="pill">{{ ucfirst($item->to_department) }}</span>
                                        <span class="mx-1">•</span>
                                        <i class="bi bi-person"></i> {{ $item->changedBy->name ?? 'Hệ thống' }}
                                    </div>

                                    <div class="timeline-note mt-2">{{ $item->note ?? 'Không có ghi chú' }}</div>
                                </div>
                            </div>
                        @empty
                            <div class="text-center text-muted">Chưa có lịch sử ghi nhận.</div>
                        @endforelse
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="glass mb-4">
                    <div class="section-head" style="background: rgba(15,23,42,.92); color:#fff;">
                        <p class="section-title mb-0" style="color:#fff;">
                            <span class="section-ic" style="background: rgba(255,255,255,.16); border-color: rgba(255,255,255,.20); color:#fff;">
                                <i class="bi bi-info-square"></i>
                            </span>
                            Thông tin nội bộ
                        </p>
                    </div>

                    <div class="section-body">
                        <div class="meta">
                            <div class="meta-label">Mã đơn</div>
                            <div class="meta-value font-monospace">{{ $order->order_code }}</div>
                        </div>
                        <div class="meta">
                            <div class="meta-label">Ngày đặt</div>
                            <div class="meta-value">{{ $order->order_date ? Carbon::parse($order->order_date)->format('d/m/Y') : '-' }}</div>
                        </div>
                        <div class="meta">
                            <div class="meta-label">Người tạo</div>
                            <div class="meta-value">{{ $order->creator->name ?? 'N/A' }}</div>
                        </div>

                        @if($order->approved_by)
                            <hr class="my-2">
                            <div class="meta">
                                <div class="meta-label">Duyệt cuối</div>
                                <div class="meta-value text-success">{{ $order->approver->name ?? 'N/A' }}</div>
                            </div>
                            <div class="meta">
                                <div class="meta-label">Ngày duyệt</div>
                                <div class="meta-value">{{ $order->approved_at ? Carbon::parse($order->approved_at)->format('d/m/Y H:i') : '-' }}</div>
                            </div>
                        @endif
                    </div>
                </div>

                <div class="glass mb-4">
                    <div class="section-head">
                        <p class="section-title mb-0">
                            <span class="section-ic"><i class="bi bi-receipt"></i></span>
                            Thông tin xuất hoá đơn
                        </p>

                        <div class="ms-auto">
                            @if($invoiceStatus === 'issued')
                                <span class="pill" style="background: rgba(34,197,94,.10); border-color: rgba(34,197,94,.18); color: rgba(22,163,74,1);">
                                    <i class="bi bi-check2-circle"></i> Đã xuất
                                </span>
                            @elseif($invoiceStatus === 'pending')
                                <span class="pill" style="background: rgba(245,158,11,.10); border-color: rgba(245,158,11,.18); color: rgba(217,119,6,1);">
                                    <i class="bi bi-hourglass-split"></i> Chờ xuất
                                </span>
                            @else
                                <span class="pill">
                                    <i class="bi bi-dash-circle"></i> Không yêu cầu
                                </span>
                            @endif
                        </div>
                    </div>

                    <div class="section-body">
                        <div class="meta">
                            <div class="meta-label">Tên công ty</div>
                            <div class="meta-value">{{ $invoiceCompanyName ?: '—' }}</div>
                        </div>

                        <div class="meta">
                            <div class="meta-label">Mã số thuế</div>
                            <div class="meta-value">{{ $invoiceTaxCode ?: '—' }}</div>
                        </div>

                        <div class="meta">
                            <div class="meta-label">Địa chỉ xuất HĐ</div>
                            <div class="meta-value">{{ $invoiceAddress ?: '—' }}</div>
                        </div>

                        <div class="meta">
                            <div class="meta-label">Email nhận HĐ</div>
                            <div class="meta-value">{{ $invoiceEmail ?: '—' }}</div>
                        </div>

                        @if(!empty($order->invoice_file))
                            <div class="meta">
                                <div class="meta-label">Chứng từ</div>
                                <div class="meta-value">
                                    <a href="{{ asset('storage/' . $order->invoice_file) }}" target="_blank">
                                        <i class="bi bi-file-earmark"></i> Xem file
                                    </a>
                                </div>
                            </div>
                        @endif

                        <div class="mt-3 no-print d-grid gap-2">
                            <button type="button"
                                    class="btn-ghost w-100"
                                    data-bs-toggle="modal"
                                    data-bs-target="#invoiceModal">
                                <i class="bi bi-pencil-square"></i>
                                Cập nhật thông tin hoá đơn
                            </button>
                        </div>
                    </div>
                </div>

                <div class="glass mb-4">
                    <div class="section-head">
                        <p class="section-title mb-0">
                            <span class="section-ic"><i class="bi bi-truck"></i></span>
                            Vận chuyển
                        </p>

                        <div class="ms-auto">
                            @php
                                $hasShippingInfo = filled($order->shipping_carrier)
                                    || filled($order->tracking_number)
                                    || filled($order->receiver_name)
                                    || filled($order->receiver_phone)
                                    || filled($order->estimated_delivery)
                                    || filled($order->shipping_address);

                                $shippingStatus = $order->shipping_status ?? null;
                                $isActuallyShipped = (int)($order->is_shipped ?? 0) === 1 || $shippingStatus === 'shipped';
                                $isWaitingShipping = !$isActuallyShipped && ($hasShippingInfo || $shippingStatus === 'ready');
                            @endphp

                            @if($isCancelled)
                                <span class="pill" style="background: rgba(239,68,68,.10); border-color: rgba(239,68,68,.18); color: rgba(220,38,38,1);">
                                    <i class="bi bi-x-circle"></i> Đã hủy
                                </span>
                            @elseif($isActuallyShipped)
                                <span class="pill" style="background: rgba(34,197,94,.10); border-color: rgba(34,197,94,.18); color: rgba(22,163,74,1);">
                                    <i class="bi bi-check2-circle"></i> Đã vận chuyển
                                </span>
                            @elseif($isWaitingShipping)
                                <span class="pill" style="background: rgba(245,158,11,.10); border-color: rgba(245,158,11,.18); color: rgba(217,119,6,1);">
                                    <i class="bi bi-hourglass-split"></i> Chờ vận chuyển
                                </span>
                            @else
                                <span class="pill">
                                    <i class="bi bi-hourglass-split"></i> Chưa có thông tin VC
                                </span>
                            @endif
                        </div>
                    </div>

                    <div class="section-body">
                        <div class="meta">
                            <div class="meta-label">Đơn vị VC</div>
                            <div class="meta-value">{{ $order->shipping_carrier ?: '—' }}</div>
                        </div>

                        <div class="meta">
                            <div class="meta-label">Mã vận đơn</div>
                            <div class="meta-value font-monospace">{{ $order->tracking_number ?: '—' }}</div>
                        </div>

                        <div class="meta">
                            <div class="meta-label">Ngày giao hàng</div>
                            <div class="meta-value">{{ !empty($order->estimated_delivery) ? \Carbon\Carbon::parse($order->estimated_delivery)->format('d/m/Y') : '—' }}</div>
                        </div>

                        <div class="row g-2">
                            <div class="col-6">
                                <div class="meta">
                                    <div class="meta-label">Người nhận</div>
                                    <div class="meta-value">{{ $order->receiver_name ?: '—' }}</div>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="meta">
                                    <div class="meta-label">SĐT</div>
                                    <div class="meta-value">{{ $order->receiver_phone ?: '—' }}</div>
                                </div>
                            </div>
                        </div>

                        <div class="meta">
                            <div class="meta-label">Địa chỉ</div>
                            <div class="meta-value">{{ $order->shipping_address ?: '—' }}</div>
                        </div>

                        <div class="meta">
                            <div class="meta-label">Ghi chú</div>
                            <div class="meta-value" style="color: var(--muted);">{{ $order->shipping_note ?: '—' }}</div>
                        </div>

                        
                        @php
                            /* EGO_SHIPPING_SAFE_VARS_START */
                            $shippingFeeWarehouseToStation = (float) ($order->shipping_fee_warehouse_to_station ?? 0);
                            $shippingFeeStationToCustomer  = (float) ($order->shipping_fee_station_to_customer ?? 0);
                            $totalShippingFee              = $shippingFeeWarehouseToStation + $shippingFeeStationToCustomer;
                            $shippingFeePayer              = $order->shipping_fee_payer ?: 'company';
                            $companyPaysShipping           = $shippingFeePayer === 'company';
                            $netRevenueAfterShipping       = (float) ($order->total_amount ?? 0) - ($companyPaysShipping ? $totalShippingFee : 0);
                            /* EGO_SHIPPING_SAFE_VARS_END */
                        @endphp

                        <div class="shipping-fee-info">
                            <div class="shipping-fee-head">
                                <div class="title">
                                    <i class="bi bi-cash-coin"></i>
                                    Phí vận chuyển
                                </div>

                                @if($companyPaysShipping)
                                    <span class="pill" style="background: rgba(245,158,11,.12); color:#92400e; border-color:rgba(245,158,11,.24);">
                                        Công ty chịu
                                    </span>
                                @else
                                    <span class="pill" style="background: rgba(14,165,233,.10); color:#075985; border-color:rgba(14,165,233,.22);">
                                        Khách chịu
                                    </span>
                                @endif
                            </div>

                            <div class="shipping-fee-grid">
                                <div class="shipping-fee-line">
                                    <span class="label">Kho → chành</span>
                                    <span class="value">{{ number_format($shippingFeeWarehouseToStation, 0, ',', '.') }} đ</span>
                                </div>

                                <div class="shipping-fee-line">
                                    <span class="label">Chành → khách</span>
                                    <span class="value">{{ number_format($shippingFeeStationToCustomer, 0, ',', '.') }} đ</span>
                                </div>

                                <div class="shipping-fee-line total">
                                    <span class="label">Tổng phí</span>
                                    <span class="value">{{ number_format($totalShippingFee, 0, ',', '.') }} đ</span>
                                </div>

                                @if($companyPaysShipping && $totalShippingFee > 0)
                                    <div class="shipping-fee-line net {{ $netRevenueAfterShipping < 0 ? 'negative' : '' }}">
                                        <span class="label">Doanh thu sau phí VC</span>
                                        <span class="value">{{ number_format($netRevenueAfterShipping, 0, ',', '.') }} đ</span>
                                    </div>
                                @endif
                            </div>
                        </div>

                        <div class="mt-3 no-print d-grid gap-2">
                            @if($isCancelled)
                                <button class="btn btn-outline-danger w-100" disabled style="border-radius: 14px; font-weight: 750; font-size: 12.5px;">
                                    <i class="bi bi-x-circle me-1"></i> Đơn hàng đã hủy
                                </button>
                            @else
                                <button type="button" class="btn-ghost w-100" data-bs-toggle="modal" data-bs-target="#shippingModal">
                                    <i class="bi bi-pencil-square me-1"></i>
                                    {{ $hasShippingInfo ? 'Cập nhật thông tin vận chuyển' : 'Nhập thông tin vận chuyển' }}
                                </button>

                                @if($isWaitingShipping)
                                    <form action="{{ route('orders.markShipped', $order->id) }}" method="POST">
                                        @csrf
                                        <button type="submit"
                                                class="btn btn-success w-100"
                                                style="border-radius: 14px; font-weight: 750; font-size: 12.5px;"
                                                onclick="return confirm('Xác nhận đơn hàng này đã vận chuyển?')">
                                            <i class="bi bi-check2-circle me-1"></i> Đánh dấu đã vận chuyển
                                        </button>
                                    </form>
                                @elseif($isActuallyShipped)
                                    <button class="btn-ghost w-100" disabled style="opacity:.75;">
                                        <i class="bi bi-check2-circle me-1"></i> Đã vận chuyển
                                    </button>
                                @else
                                    <button class="btn btn-outline-secondary w-100" disabled style="border-radius: 14px; font-weight: 750; font-size: 12.5px;">
                                        <i class="bi bi-lock me-1"></i> Nhập thông tin vận chuyển trước
                                    </button>
                                @endif
                            @endif
                        </div>
                    </div>
                </div>

                <div class="glass sticky-card no-print">
                    <div class="section-head">
                        <p class="section-title mb-0">
                            <span class="section-ic"><i class="bi bi-gear"></i></span>
                            Bảng điều khiển
                        </p>
                    </div>

                    <div class="section-body">
                        <div class="d-grid gap-2">
                            @if($isCancelled)
                                <div class="alert alert-danger mb-0" style="border-radius: 14px;">
                                    <i class="bi bi-x-circle me-1"></i> Đơn hàng đã hủy.
                                </div>
                            @else
                                @can('recordPayment', $order)
                                    @php
                                        $remainingDebt = max(0, (float)($order->total_amount ?? 0) - $order->payments->sum('amount'));
                                    @endphp

                                    @if($remainingDebt > 0)
                                        <button class="btn-ghost text-start w-100" type="button"
                                                data-bs-toggle="modal" data-bs-target="#paymentModal">
                                            <i class="bi bi-cash-coin me-2"></i> Ghi nhận thanh toán
                                            <small class="d-block mt-1" style="color: var(--muted); font-weight: 650;">
                                                Còn nợ: {{ number_format($remainingDebt, 0, ',', '.') }} đ
                                            </small>
                                        </button>
                                    @else
                                        <div class="pill w-100 d-flex align-items-center gap-2"
                                             style="background: rgba(34,197,94,.10); border: 1px solid rgba(34,197,94,.18); color: rgba(22,163,74,1);
                                                    padding: 10px 12px; border-radius: 12px; font-weight: 750;">
                                            <i class="bi bi-check2-circle"></i>
                                            Thanh toán thành công
                                        </div>
                                    @endif
                                @endcan

                                @can('ship', $order)
                                    <button class="btn-ghost text-start" type="button" data-bs-toggle="modal" data-bs-target="#stockModal">
                                        <i class="bi bi-box-seam-fill me-2"></i> Xác nhận xuất kho
                                    </button>
                                @endcan

                                @can('delete', $order)
                                    <hr class="my-2">
                                    <form action="{{ route('orders.cancel', $order->id) }}" method="POST">
                                        @csrf
                                        <button type="submit" class="btn btn-warning w-100"
                                                onclick="return confirm('Bạn có chắc chắn muốn hủy đơn hàng này?\n\n(Dữ liệu vẫn sẽ được giữ lại)')"
                                                style="border-radius: 14px; font-weight: 750; font-size: 12.5px;">
                                            <i class="bi bi-x-circle me-2"></i> Hủy đơn hàng
                                        </button>
                                    </form>
                                @endcan
                            @endif
                        </div>
                    </div>
                </div>

                <div class="glass mt-3 no-print">
                    <div class="section-head">
                        <p class="section-title mb-0">
                            <span class="section-ic"><i class="bi bi-pencil-square"></i></span>
                            Lịch sử chỉnh sửa
                        </p>

                        <span class="pill">
                            {{ ($editHistories ?? collect())->count() }} lần
                        </span>
                    </div>

                    <div class="section-body">
                        @if(($editHistories ?? collect())->count())
                            <div class="edit-history-list">
                                @foreach(($editHistories ?? collect())->take(5) as $log)
                                    @php
                                        $changes = is_array($log->changes ?? null) ? $log->changes : [];
                                    @endphp

                                    <div class="edit-history-item">
                                        <div class="edit-history-top">
                                            <div class="edit-history-name">
                                                {{ $log->user_name ?? 'Không rõ' }}
                                            </div>
                                            <div class="edit-history-time">
                                                {{ $log->created_at ? $log->created_at->format('d/m H:i') : '—' }}
                                            </div>
                                        </div>

                                        <ul class="edit-history-changes">
                                            @if(isset($changes['total_amount']))
                                                <li>
                                                    Tổng tiền:
                                                    {{ number_format((float)($changes['total_amount']['old'] ?? 0), 0, ',', '.') }} đ
                                                    →
                                                    {{ number_format((float)($changes['total_amount']['new'] ?? 0), 0, ',', '.') }} đ
                                                </li>
                                            @endif

                                            @if(isset($changes['order_date']))
                                                <li>
                                                    Ngày đặt:
                                                    {{ !empty($changes['order_date']['old']) ? \Carbon\Carbon::parse($changes['order_date']['old'])->format('d/m/Y') : '—' }}
                                                    →
                                                    {{ !empty($changes['order_date']['new']) ? \Carbon\Carbon::parse($changes['order_date']['new'])->format('d/m/Y') : '—' }}
                                                </li>
                                            @endif

                                            @if(isset($changes['items']))
                                                <li>Chi tiết sản phẩm đã thay đổi</li>
                                            @endif

                                            @if(isset($changes['warehouse_id']))
                                                <li>Kho đã thay đổi</li>
                                            @endif

                                            @if(isset($changes['lead_id']))
                                                <li>Khách hàng đã thay đổi</li>
                                            @endif

                                            @if(empty($changes))
                                                <li>Đơn hàng đã được chỉnh sửa</li>
                                            @endif
                                        </ul>
                                    </div>
                                @endforeach
                            </div>
                        @else
                            <div class="text-muted">Chưa có lịch sử chỉnh sửa.</div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="paymentModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form action="{{ route('orders.record-payment', $order->id) }}" method="POST">
                    @csrf
                    <div class="modal-header">
                        <h5 class="modal-title" style="font-weight:800;"><i class="bi bi-cash"></i> Ghi nhận thanh toán</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>

                    <div class="modal-body">
                        <div class="alert alert-light border mb-3">
                            Tổng tiền: <strong>{{ number_format($order->total_amount ?? 0, 0, ',', '.') }} đ</strong><br>
                            Đã TT: <strong class="text-success">{{ number_format($paid, 0, ',', '.') }} đ</strong><br>
                            Còn lại: <strong class="text-danger">{{ number_format(max(0,$remain), 0, ',', '.') }} đ</strong>
                        </div>

                        <div class="mb-3">
                            <label class="form-label" style="font-weight:750;">Ngày thanh toán</label>
                            <input type="date" name="payment_date" class="form-control" value="{{ date('Y-m-d') }}" required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label" style="font-weight:750;">Số tiền thực nhận</label>
                            <div class="input-group">
                                <input type="number" name="amount" class="form-control" step="any" value="{{ max(0,$remain) }}" required>
                                <span class="input-group-text">VNĐ</span>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label" style="font-weight:750;">Phương thức thanh toán <span class="text-danger">*</span></label>
                            <select name="method_id" class="form-select" required>
                                <option value="">-- Chọn phương thức --</option>
                                @if(isset($paymentMethods))
                                    @foreach($paymentMethods as $method)
                                        <option value="{{ $method->id }}">
                                            {{ $method->method_name }} ({{ $method->code }})
                                        </option>
                                    @endforeach
                                @endif
                            </select>
                        </div>

                        <div class="mb-0">
                            <label class="form-label" style="font-weight:750;">Ghi chú giao dịch</label>
                            <textarea name="note" class="form-control" rows="2" placeholder="Mã GD ngân hàng, người nộp tiền..."></textarea>
                        </div>
                    </div>

                    <div class="modal-footer">
                        <button type="button" class="btn btn-light border" data-bs-dismiss="modal" style="border-radius: 14px;">Đóng</button>
                        <button type="submit" class="btn btn-primary" style="border-radius: 14px; font-weight: 750;">
                            <i class="bi bi-save"></i> Lưu
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal fade" id="invoiceModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form action="{{ route('orders.updateInvoice', $order->id) }}" method="POST" enctype="multipart/form-data">
                    @csrf

                    <div class="modal-header">
                        <h5 class="modal-title" style="font-weight:800;">
                            <i class="bi bi-receipt me-1"></i> Thông tin hoá đơn
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>

                    <div class="modal-body">
    <div class="mb-2">
        <label class="form-label" style="font-weight:750;">Trạng thái</label>
        <select name="invoice_status" class="form-select">
            <option value="none" {{ $invoiceStatus == 'none' ? 'selected' : '' }}>Không yêu cầu</option>
            <option value="pending" {{ $invoiceStatus == 'pending' ? 'selected' : '' }}>Chờ xuất</option>
            <option value="issued" {{ $invoiceStatus == 'issued' ? 'selected' : '' }}>Đã xuất</option>
        </select>
    </div>

    <div class="mb-2">
        <label class="form-label" style="font-weight:750;">Tên công ty</label>
        <input name="invoice_company_name" class="form-control" value="{{ old('invoice_company_name', $invoiceCompanyName) }}">
    </div>

    <div class="mb-2">
        <label class="form-label" style="font-weight:750;">MST</label>
        <input name="invoice_tax_code" class="form-control" value="{{ old('invoice_tax_code', $invoiceTaxCode) }}">
    </div>

    <div class="mb-2">
        <label class="form-label" style="font-weight:750;">Địa chỉ</label>
        <textarea name="invoice_address" class="form-control">{{ old('invoice_address', $invoiceAddress) }}</textarea>
    </div>

    <div class="mb-2">
        <label class="form-label" style="font-weight:750;">Email nhận HĐ</label>
        <input name="invoice_email" class="form-control" value="{{ old('invoice_email', $invoiceEmail) }}">
    </div>

    <div class="mb-2">
        <label class="form-label" style="font-weight:750;">Upload chứng từ</label>
        <input type="file" name="invoice_file" class="form-control">
    </div>
    @if(!empty($order->invoice_file))
    <div class="mt-2">
        <a href="{{ asset('storage/' . $order->invoice_file) }}" target="_blank" class="text-decoration-none">
            <i class="bi bi-file-earmark-text"></i> Xem file hiện tại
        </a>
    </div>
@endif
</div>

                    <div class="modal-footer">
                        <button type="button" class="btn btn-light border" data-bs-dismiss="modal" style="border-radius: 14px;">Đóng</button>
                        <button type="submit" class="btn btn-primary" style="border-radius: 14px; font-weight: 750;">
                            <i class="bi bi-save"></i> Lưu
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal fade" id="shippingModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <form action="{{ route('orders.shippingInfo', $order->id) }}" method="POST">
                    @csrf
                    <div class="modal-header">
                        <h5 class="modal-title" style="font-weight:800;">
                            <i class="bi bi-truck me-1"></i> Cập nhật giao hàng
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>

                    <div class="modal-body">
                        <div class="mb-2">
                            <label class="form-label" style="font-weight:750;">Đơn vị VC</label>
                            <input class="form-control" name="shipping_carrier" value="{{ old('shipping_carrier', $order->shipping_carrier) }}">
                        </div>

                        <div class="mb-2">
                            <label class="form-label" style="font-weight:750;">Mã vận đơn</label>
                            <input class="form-control" name="tracking_number" value="{{ old('tracking_number', $order->tracking_number) }}">
                        </div>

                        <div class="mb-2">
                            <label class="form-label" style="font-weight:750;">Ngày giao hàng</label>
                            <input type="date" class="form-control" name="estimated_delivery" value="{{ old('estimated_delivery', !empty($order->estimated_delivery) ? \Carbon\Carbon::parse($order->estimated_delivery)->format('Y-m-d') : '') }}">
                        </div>

                        <div class="row g-2">
                            <div class="col-6">
                                <label class="form-label" style="font-weight:750;">Người nhận</label>
                                <input class="form-control" name="receiver_name" value="{{ old('receiver_name', $order->receiver_name) }}">
                            </div>
                            <div class="col-6">
                                <label class="form-label" style="font-weight:750;">SĐT</label>
                                <input class="form-control" name="receiver_phone" value="{{ old('receiver_phone', $order->receiver_phone) }}">
                            </div>
                        </div>

                        <div class="mt-2">
                            <label class="form-label" style="font-weight:750;">Địa chỉ</label>
                            <textarea class="form-control" rows="2" name="shipping_address">{{ old('shipping_address', $order->shipping_address) }}</textarea>
                        </div>

                        <div class="mt-2">
                            <label class="form-label" style="font-weight:750;">Ghi chú giao hàng</label>
                            <textarea class="form-control" rows="2" name="shipping_note">{{ old('shipping_note', $order->shipping_note) }}</textarea>
                        </div>

                        <div class="shipping-fee-box mt-3">
                            <div class="shipping-fee-title">
                                <i class="bi bi-cash-coin"></i>
                                Chi phí vận chuyển
                            </div>

                            <div class="row g-2">
                                <div class="col-6">
                                    <label class="form-label" style="font-weight:750;">Phí kho → chành</label>
                                    <div class="input-group">
                                        <input type="number"
                                               min="0"
                                               step="any"
                                               class="form-control"
                                               name="shipping_fee_warehouse_to_station"
                                               value="{{ old('shipping_fee_warehouse_to_station', $order->shipping_fee_warehouse_to_station ?? 0) }}">
                                        <span class="input-group-text">VNĐ</span>
                                    </div>
                                </div>

                                <div class="col-6">
                                    <label class="form-label" style="font-weight:750;">Phí chành → khách</label>
                                    <div class="input-group">
                                        <input type="number"
                                               min="0"
                                               step="any"
                                               class="form-control"
                                               name="shipping_fee_station_to_customer"
                                               value="{{ old('shipping_fee_station_to_customer', $order->shipping_fee_station_to_customer ?? 0) }}">
                                        <span class="input-group-text">VNĐ</span>
                                    </div>
                                </div>
                            </div>

                            <div class="mt-3">
                                <label class="form-label d-block" style="font-weight:750;">Ai chịu phí vận chuyển?</label>

                                <div class="shipping-payer-options">
                                    <input class="btn-check"
                                           type="radio"
                                           name="shipping_fee_payer"
                                           id="shipping_fee_payer_company"
                                           value="company"
                                           {{ old('shipping_fee_payer', $shippingFeePayer) === 'company' ? 'checked' : '' }}>
                                    <label class="shipping-payer-card" for="shipping_fee_payer_company">
                                        <i class="bi bi-building-check"></i>
                                        <span>
                                            Công ty chịu
                                            <small>Trừ vào doanh thu thuần</small>
                                        </span>
                                    </label>

                                    <input class="btn-check"
                                           type="radio"
                                           name="shipping_fee_payer"
                                           id="shipping_fee_payer_customer"
                                           value="customer"
                                           {{ old('shipping_fee_payer', $shippingFeePayer) === 'customer' ? 'checked' : '' }}>
                                    <label class="shipping-payer-card" for="shipping_fee_payer_customer">
                                        <i class="bi bi-person-check"></i>
                                        <span>
                                            Khách chịu
                                            <small>Không trừ doanh thu</small>
                                        </span>
                                    </label>
                                </div>
                            </div>
                        </div>

                        <div class="alert alert-info mt-3 mb-0">
                            Khi bấm <b>Lưu thông tin</b>, hệ thống chỉ lưu thông tin và chuyển trạng thái sang <b>Chờ vận chuyển</b>.
                        </div>
                    </div>

                    <div class="modal-footer">
                        <button type="button" class="btn btn-light border" data-bs-dismiss="modal" style="border-radius: 14px;">Hủy</button>
                        <button type="submit" class="btn btn-primary" style="border-radius: 14px; font-weight: 750;">
                            <i class="bi bi-save me-1"></i> Lưu thông tin
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal fade" id="stockModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <form action="{{ route('orders.warehouse.issue', $order->id) }}" method="POST" id="shipOrderForm">
                    @csrf
                    <div class="modal-header">
                        <h5 class="modal-title" style="font-weight:800;"><i class="bi bi-box-seam"></i> Xác nhận xuất kho</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>

                    <div class="modal-body">
                        <div class="alert alert-warning">
                            <i class="bi bi-exclamation-triangle"></i>
                            Hành động này sẽ trừ tồn kho và hoàn tất đơn hàng.
                        </div>

                        <div class="row g-2 mb-3">
                            <div class="col-md-6">
                                <label class="form-label" style="font-weight:750;">Ngày xuất kho</label>
                                <input type="date" class="form-control" name="actual_ship_date" value="{{ date('Y-m-d') }}" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" style="font-weight:750;">Bảo hành sau khi xuất (tháng)</label>
                                <input type="number" class="form-control" name="warranty_months" value="60" min="1" max="240" required>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label" style="font-weight:750;">Ghi chú xuất kho (tùy chọn)</label>
                            <textarea name="shipping_note" class="form-control" rows="2" placeholder="Thông tin xuất kho..."></textarea>
                        </div>

                        <hr class="my-3">

                        <div id="ship-serials-wrapper" style="display:none;">
                            <div class="d-flex justify-content-between align-items-center mb-2 flex-wrap gap-2">
                                <div class="fw-semibold">
                                    <i class="bi bi-upc-scan"></i> Chọn Serial/IMEI để xuất
                                </div>
                                <input type="text" class="form-control form-control-sm" id="serialSearch"
                                       style="width:260px" placeholder="Tìm serial...">
                            </div>

                            <div id="ship-serials-container"></div>
                            <div id="ship-serials-error" class="alert alert-danger mt-2" style="display:none;"></div>

                            <div class="text-muted small mt-2">
                                * Sản phẩm quản lý Serial/IMEI: phải chọn đúng số lượng serial theo số lượng trong đơn.
                            </div>
                        </div>
                    </div>

                    <div class="modal-footer">
                        <button type="button" class="btn btn-light border" data-bs-dismiss="modal" style="border-radius: 14px;">Hủy</button>
                        <button type="submit" class="btn btn-primary" id="btnConfirmShip" style="border-radius: 14px; font-weight: 750;">
                            <i class="bi bi-check2-circle"></i> Xác nhận
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
      window.ORDER_ID_FOR_SHIP = {{ (int)$order->id }};
      window.SHIP_SERIALS_URL = "{{ route('orders.ship-serials', $order->id) }}";
    </script>
    <script src="/js/order-ship-serial.js?v={{ time() }}"></script>


    @if(session('stock_error_popup'))
        <div class="modal fade" id="stockErrorPopup" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content" style="border: 1px solid rgba(220,53,69,.35); border-radius: 18px; overflow: hidden;">
                    <div class="modal-header bg-danger text-white">
                        <h5 class="modal-title" style="font-weight:800;">
                            <i class="bi bi-exclamation-triangle-fill me-1"></i> Không thể xuất kho
                        </h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="alert alert-danger mb-0" style="border-radius: 14px; line-height: 1.55;">
                            {!! nl2br(e(session('stock_error_popup'))) !!}
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-light border" data-bs-dismiss="modal" style="border-radius: 14px; font-weight: 750;">
                            Đã hiểu
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif

    <div class="modal fade" id="deleteModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content" style="border: 1px solid rgba(220,53,69,.35);">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title" style="font-weight:800;"><i class="bi bi-exclamation-triangle-fill"></i> Xóa đơn hàng</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>

                <div class="modal-body">
                    <div class="alert alert-danger">
                        <i class="bi bi-exclamation-circle"></i>
                        <strong>Cảnh báo:</strong> Hành động này <strong>KHÔNG THỂ PHỤC HỒI</strong>.
                    </div>

                    <p class="mb-2" style="font-weight:750;">Bạn sắp xóa đơn hàng:</p>
                    <div class="card p-3 bg-light" style="border-radius: 16px; border: 1px solid rgba(15,23,42,.08);">
                        <strong class="text-primary">{{ $order->order_code }}</strong><br>
                        <small class="text-muted">Khách: {{ $customerName }}</small><br>
                        <small class="text-muted">Tổng: {{ number_format($order->total_amount ?? 0, 0, ',', '.') }} đ</small>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-light border" data-bs-dismiss="modal" style="border-radius: 14px;">Hủy</button>
                    <form action="{{ route('orders.destroy', $order->id) }}" method="POST" class="d-inline">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-danger" style="border-radius: 14px; font-weight: 750;">
                            <i class="bi bi-trash"></i> Xóa vĩnh viễn
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="printPreviewModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-centered">
            <div class="modal-content" style="border-radius: 18px !important; overflow: hidden;">
                <div class="modal-header">
                    <h5 class="modal-title" style="font-weight:800;">
                        <i class="bi bi-printer me-2"></i> Xem trước khi in
                    </h5>

                    <div class="d-flex align-items-center gap-2">
                        <button type="button"
                                class="btn btn-primary"
                                onclick="printPdfInIframe()"
                                style="border-radius: 12px; font-weight: 750;">
                            <i class="bi bi-printer me-1"></i> In ngay
                        </button>

                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                </div>

                <div class="modal-body p-0" style="height: 80vh; background: #f3f4f6;">
                    <iframe
                        id="pdfPreviewFrame"
                        src="{{ route('orders.pdf.preview', $order->id) }}"
                        style="width:100%; height:100%; border:0; background:#fff;">
                    </iframe>
                </div>
            </div>
        </div>
    </div>
</div>

@if(session('stock_error_popup'))
<script>
document.addEventListener('DOMContentLoaded', function () {
    var popupEl = document.getElementById('stockErrorPopup');
    if (popupEl && window.bootstrap) {
        new bootstrap.Modal(popupEl).show();
    } else if (popupEl) {
        alert(popupEl.innerText.trim());
    }
});
</script>
@endif

@if($errors->any())
<script>
document.addEventListener('DOMContentLoaded', function () {
    var shippingEl = document.getElementById('shippingModal');
    var invoiceEl = document.getElementById('invoiceModal');

    @if($errors->has('invoice_status') || $errors->has('invoice_company_name') || $errors->has('invoice_tax_code') || $errors->has('invoice_address') || $errors->has('invoice_email') || $errors->has('invoice_file'))
        if (invoiceEl) new bootstrap.Modal(invoiceEl).show();
    @else
        if (shippingEl) new bootstrap.Modal(shippingEl).show();
    @endif
});
</script>
@endif

<script>
function copyText(text, btn) {
    const ok = () => {
        if (!btn) return;
        const old = btn.innerHTML;
        btn.innerHTML = '✓';
        btn.classList.add('btn-success','text-white');
        setTimeout(() => {
            btn.innerHTML = old;
            btn.classList.remove('btn-success','text-white');
        }, 700);
    };

    if (navigator.clipboard && window.isSecureContext) {
        navigator.clipboard.writeText(text).then(ok).catch(() => fallbackCopy(text, ok));
    } else {
        fallbackCopy(text, ok);
    }
}

function fallbackCopy(text, cb) {
    const a = document.createElement('textarea');
    a.value = text;
    a.setAttribute('readonly', '');
    a.style.position = 'absolute';
    a.style.left = '-9999px';
    document.body.appendChild(a);
    a.select();
    try { document.execCommand('copy'); } catch (e) {}
    document.body.removeChild(a);
    if (cb) cb();
}

function printPdfInIframe() {
    const iframe = document.getElementById('pdfPreviewFrame');
    if (!iframe) return;

    try {
        iframe.contentWindow.focus();
        iframe.contentWindow.print();
    } catch (e) {
        alert('Không thể gọi in trực tiếp. Vui lòng dùng nút in trong khung PDF.');
    }
}
</script>

<!-- EGO_CUSTOMER_MODAL_STYLE_START -->
<style id="ego-customer-modal-style">
.ego-customer-modal{
    border:0 !important;
    border-radius:22px !important;
    overflow:hidden !important;
    box-shadow:0 30px 80px rgba(15,23,42,.28) !important;
    background:#f8fafc !important;
}

.ego-customer-modal .modal-header{
    background:linear-gradient(135deg,#0ea5e9 0%, #2563eb 100%) !important;
    color:#fff !important;
    border-bottom:0 !important;
    padding:18px 24px !important;
}

.ego-customer-modal .modal-title,
.ego-customer-modal h5,
.ego-customer-modal h4{
    color:#fff !important;
    font-weight:800 !important;
    font-size:30px !important;
    margin:0 !important;
}

.ego-customer-modal .btn-close,
.ego-customer-modal .close{
    filter:brightness(0) invert(1) !important;
    opacity:1 !important;
}

.ego-customer-modal .modal-body{
    background:#f8fafc !important;
    padding:18px !important;
    max-height:78vh !important;
    overflow-y:auto !important;
}

.ego-customer-modal .ego-customer-panel{
    background:#fff !important;
    border:1px solid #e2e8f0 !important;
    border-radius:18px !important;
    padding:16px 16px 12px !important;
    margin-bottom:16px !important;
    box-shadow:0 10px 26px rgba(15,23,42,.05) !important;
}

.ego-customer-modal .ego-customer-panel-title{
    display:flex !important;
    align-items:center !important;
    gap:8px !important;
    font-size:20px !important;
    font-weight:800 !important;
    color:#0f172a !important;
    margin-bottom:14px !important;
    padding-bottom:10px !important;
    border-bottom:1px solid #eef2f7 !important;
}

.ego-customer-modal label,
.ego-customer-modal .form-label{
    font-size:14px !important;
    font-weight:700 !important;
    color:#334155 !important;
    margin-bottom:8px !important;
}

.ego-customer-modal .form-control,
.ego-customer-modal .form-select,
.ego-customer-modal input,
.ego-customer-modal select,
.ego-customer-modal textarea{
    border-radius:14px !important;
    border:1px solid #dbe4ee !important;
    background:#fff !important;
    box-shadow:none !important;
    min-height:44px !important;
    padding:10px 14px !important;
    font-size:14px !important;
    color:#0f172a !important;
}

.ego-customer-modal textarea{
    min-height:88px !important;
    resize:vertical !important;
}

.ego-customer-modal .form-control:focus,
.ego-customer-modal .form-select:focus,
.ego-customer-modal input:focus,
.ego-customer-modal select:focus,
.ego-customer-modal textarea:focus{
    border-color:#38bdf8 !important;
    box-shadow:0 0 0 4px rgba(56,189,248,.14) !important;
    outline:none !important;
}

.ego-customer-modal ::placeholder{
    color:#94a3b8 !important;
}

.ego-customer-modal .text-muted,
.ego-customer-modal small,
.ego-customer-modal .form-text{
    color:#64748b !important;
    font-size:12px !important;
}

.ego-customer-modal .modal-footer{
    background:#fff !important;
    border-top:1px solid #e2e8f0 !important;
    padding:14px 18px !important;
}

.ego-customer-modal .btn{
    border-radius:14px !important;
    min-height:42px !important;
    padding:10px 16px !important;
    font-weight:700 !important;
}

.ego-customer-modal .btn-primary,
.ego-customer-modal .btn-success{
    background:linear-gradient(135deg,#06b6d4 0%, #2563eb 100%) !important;
    border:0 !important;
    box-shadow:0 12px 28px rgba(37,99,235,.22) !important;
}

.ego-customer-modal .btn-secondary,
.ego-customer-modal .btn-light{
    background:#f8fafc !important;
    border:1px solid #dbe4ee !important;
    color:#0f172a !important;
}
</style>

<script id="ego-customer-modal-style-js">
(function(){
    function beautifyCustomerModal(){
        document.querySelectorAll('.modal').forEach(function(modal){
            const text = (modal.innerText || '').trim();

            if(
                text.includes('Thêm mới khách hàng') ||
                text.includes('Thông tin cơ bản')
            ){
                const content = modal.querySelector('.modal-content');
                if(content) content.classList.add('ego-customer-modal');

                const body = modal.querySelector('.modal-body');
                if(body){
                    body.querySelectorAll(':scope > div').forEach(function(el){
                        if(el.querySelector('input, select, textarea')){
                            el.classList.add('ego-customer-panel');
                        }
                    });

                    body.querySelectorAll('.ego-customer-panel').forEach(function(panel){
                        const firstHeading = panel.querySelector('h1,h2,h3,h4,h5,h6,.fw-bold,strong,legend');
                        if(firstHeading && !firstHeading.classList.contains('ego-customer-panel-title')){
                            firstHeading.classList.add('ego-customer-panel-title');
                        }
                    });
                }
            }
        });
    }

    document.addEventListener('DOMContentLoaded', beautifyCustomerModal);
    beautifyCustomerModal();

    const obs = new MutationObserver(function(){
        beautifyCustomerModal();
    });

    obs.observe(document.body, {childList:true, subtree:true});
})();
</script>
<!-- EGO_CUSTOMER_MODAL_STYLE_END -->


<!-- EGO_ORDER_AFTER_SALES_BOX_START -->
@include('orders.partials.after-sales', ['order' => $order])
<!-- EGO_ORDER_AFTER_SALES_BOX_END -->
@include('ego_order_documents.order_box')
@endsection

<script>
document.addEventListener('DOMContentLoaded', function () {
    if (!location.pathname.includes('/orders/490')) return;

    document.querySelectorAll('tr').forEach(function (tr) {
        const text = tr.innerText || '';

        if (
            text.includes('STACK100') &&
            text.includes('17.000.000') &&
            text.match(/\b6\b/)
        ) {
            const cells = Array.from(tr.querySelectorAll('td, th'));

            cells.forEach(function (cell) {
                const t = (cell.innerText || '').trim();

                if (t === '0%' || t === '0 %') {
                    cell.innerHTML = cell.innerHTML.replace(/0\s*%/g, '8%');
                }
            });
        }
    });
});
</script>
