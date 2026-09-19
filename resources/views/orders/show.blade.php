@extends('layouts.app')
@section('title', 'Đơn hàng '.$order->order_code)
@section('content')

    @include('orders.partials.stock-shortage-warning')
<link rel="stylesheet" href="{{ asset('css/orders-one-page-v3.css') }}?v={{ @filemtime(public_path('css/orders-one-page-v3.css')) ?: time() }}">
<script defer src="{{ asset('js/orders-one-page-v3.js') }}?v={{ @filemtime(public_path('js/orders-one-page-v3.js')) ?: time() }}"></script>
@php
    use Illuminate\Support\Facades\Route;
    $customer = $order->lead?->customer ?? $order->customer ?? null;
    $paid = (float) collect($order->payments ?? [])->sum('amount');
    $total = (float) ($order->total_amount ?? 0);
    $remaining = max(0, $total - $paid);
    $paymentPercent = $total > 0 ? min(100, round(($paid / $total) * 100)) : 0;
    $returns = collect($order->returns ?? []);
    $activeReturns = $returns->whereNotIn('status', ['completed', 'rejected', 'cancelled']);
    $refunds = $returns->flatMap(fn ($return) => $return->refunds ?? []);
    $refundPaid = (float) $refunds->where('status', 'paid')->sum('amount');
    $department = strtolower((string) ($order->current_department ?? 'sales'));
    $departmentMap = [
        'sales' => 'Sales', 'sales_manager' => 'Sales Manager', 'duyet1' => 'Sales Manager',
        'accounting' => 'Kế toán', 'ketoan' => 'Kế toán', 'management' => 'Ban Giám đốc',
        'duyet2' => 'Ban Giám đốc', 'warehouse' => 'Kho', 'kho' => 'Kho',
        'shipping' => 'Vận chuyển', 'completed' => 'Hoàn tất', 'cancelled' => 'Đã hủy',
    ];
    $flowKeys = ['sales', 'sales_manager', 'accounting', 'management', 'warehouse', 'shipping', 'completed'];
    $flowLabel = ['sales' => 'Sales', 'sales_manager' => 'Sales Manager', 'accounting' => 'Kế toán', 'management' => 'Ban Giám đốc', 'warehouse' => 'Kho', 'shipping' => 'Vận chuyển', 'completed' => 'Hoàn tất'];
    $flowAlias = ['duyet1' => 'sales_manager', 'ketoan' => 'accounting', 'duyet2' => 'management', 'kho' => 'warehouse'];
    $currentFlow = $flowAlias[$department] ?? $department;
    $currentIndex = array_search($currentFlow, $flowKeys, true);
    if ($currentIndex === false) $currentIndex = 0;
    $isCancelled = in_array($department, ['cancelled', 'canceled', 'huy', 'da_huy'], true);
    $statusName = $isCancelled
        ? 'Đã hủy'
        : ($order->inventory_issued
            ? ($remaining > 0 ? 'Hoàn tất · còn công nợ' : 'Hoàn tất')
            : ($order->currentStatusType->name ?? ($departmentMap[$department] ?? ucfirst($department))));
    $dateFmt = fn ($value, $format = 'd/m/Y') => $value ? \Carbon\Carbon::parse($value)->format($format) : '—';
    $invoiceStatus = (string) ($order->invoice_status ?? 'none');
    $user = auth()->user();
    $canByRole = function (array $roles) use ($user): bool {
        return $user && method_exists($user, 'hasAnyRole') && $user->hasAnyRole($roles);
    };
    $canReturnView = ($user?->can('orders.return.view') ?? false) || $canByRole(['admin','management','accounting','warehouse','kho','sales_manager','sales']);
    $canReturnCreate = ($user?->can('orders.return.create') ?? false) || $canByRole(['admin','management','sales_manager','sales']);

    // EGO_COMPLETED_RETURN_VIEW_RULE_V1
    $canCreateCompletedReturn =
        $canReturnCreate
        && (bool) ($order->inventory_issued ?? false)
        && (string) ($department ?? '') === 'completed'
        && (int) collect(
            $returnAvailable ?? []
        )->sum() > 0;

    $canReturnApprove = ($user?->can('orders.return.approve') ?? false) || $canByRole(['admin','management','accounting','sales_manager']);
    $canReturnReceive = ($user?->can('orders.return.receive') ?? false) || $canByRole(['admin','warehouse','kho']);
    $canReturnInspect = ($user?->can('orders.return.inspect') ?? false) || $canByRole(['admin','warehouse','kho']);
    $canReturnStockIn = ($user?->can('orders.return.stock_in') ?? false) || $canByRole(['admin','warehouse','kho']);
    $canRefundCreate = ($user?->can('orders.refund.create') ?? false) || $canByRole(['admin','accounting','sales_manager']);
    $canRefundApprove = ($user?->can('orders.refund.approve') ?? false) || $canByRole(['admin','management','accounting']);
    $customerRegion = $customer->region ?? $customer->area ?? null;
    if (is_string($customerRegion)) {
        $regionDecoded = json_decode($customerRegion, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($regionDecoded)) {
            $customerRegion = $regionDecoded['name'] ?? $regionDecoded['label'] ?? $regionDecoded['title'] ?? $customerRegion;
        }
    } elseif (is_object($customerRegion)) {
        $customerRegion = $customerRegion->name ?? $customerRegion->label ?? $customerRegion->title ?? '—';
    }
    $customerRegion = $customerRegion ?: '—';
    $lastPayment = collect($order->payments ?? [])->sortByDesc(function ($payment) {
        return (string) ($payment->payment_date ?? $payment->created_at ?? '');
    })->first();
    $nextActionMap = [
        'sales' => 'Hoàn thiện và gửi đơn đi duyệt',
        'sales_manager' => 'Sales Manager kiểm tra và phê duyệt',
        'accounting' => 'Kế toán kiểm tra thanh toán và công nợ',
        'management' => 'Ban Giám đốc phê duyệt đơn hàng',
        'warehouse' => $order->inventory_issued ? 'Bàn giao đơn cho vận chuyển' : 'Kho kiểm tra tồn, serial và xuất hàng',
        'shipping' => 'Cập nhật giao hàng và xác nhận hoàn tất',
        'completed' => $activeReturns->count() ? 'Theo dõi hồ sơ hậu mãi đang xử lý' : 'Đơn đã hoàn tất, sẵn sàng xử lý hậu mãi khi cần',
    ];
    $nextActionText = $isCancelled ? 'Đơn hàng đã hủy' : ($nextActionMap[$currentFlow] ?? 'Kiểm tra trạng thái đơn hàng');
    $hasWarnings = $remaining > 0
        || (!$order->inventory_issued && $currentFlow === 'warehouse')
        || ($order->inventory_issued && !$order->is_shipped)
        || $activeReturns->count() > 0
        || ($returns->count() > 0 && !in_array($invoiceStatus, ['none', 'not_issued', ''], true));
    $activeTab = request('tab', 'approval');
    $validTabs = ['approval','payments','inventory','shipping','returns','invoice','documents','history'];
    if (!in_array($activeTab, $validTabs, true)) $activeTab = 'approval';
    $currentApprovalLevel = strtolower((string) ($currentApprovalLevel ?? $department));
    $isAccountingApproval = in_array($currentApprovalLevel, ['accounting','ketoan'], true);
@endphp

<div class="op-page" data-order-page data-active-tab="{{ $activeTab }}">
    <div class="op-container">
        <header class="op-header">
            <div class="op-header-main">
                <div>
                    <nav class="op-breadcrumb">
                        <a href="{{ route('orders.index') }}">Đơn hàng</a><span>/</span><strong>{{ $order->order_code }}</strong>
                    </nav>
                    <div class="op-title-line">
                        <h1>Đơn hàng #{{ $order->order_code }}</h1>
                        <span class="op-badge {{ $isCancelled ? 'danger' : ($order->inventory_issued ? 'success' : 'info') }}">{{ $statusName }}</span>
                    </div>
                    <div class="op-meta">
                        <span><i class="bi bi-person"></i>{{ $customer->name ?? 'Chưa có khách hàng' }}</span>
                        <span><i class="bi bi-calendar3"></i>{{ $dateFmt($order->order_date) }}</span>
                        <span><i class="bi bi-person-circle"></i>{{ $order->creator->name ?? '—' }}</span>
                        <span><i class="bi bi-building"></i>{{ $order->company->name ?? '—' }}</span>
                    </div>
                </div>
                <div class="op-header-actions op-no-print">
                    <a href="{{ route('orders.index') }}" class="op-btn"><i class="bi bi-arrow-left"></i> Quay lại</a>
                    <a href="{{ route('orders.pdf.preview', $order->id) }}" target="_blank" class="op-btn"><i class="bi bi-printer"></i> In</a>
                    <a href="{{ route('orders.pdf', $order->id) }}" class="op-btn"><i class="bi bi-filetype-pdf"></i> PDF</a>
                    <button type="button" class="op-btn op-btn-primary" data-op-open="quickActions"><i class="bi bi-lightning-charge"></i> Tác vụ</button>
                </div>
            </div>
        </header>

        @if(session('success'))<div class="op-alert success"><i class="bi bi-check-circle"></i><div>{{ session('success') }}</div></div>@endif
        @if(session('error'))<div class="op-alert danger"><i class="bi bi-exclamation-triangle"></i><div>{{ session('error') }}</div></div>@endif
        @if($errors->any())
            <div class="op-alert danger"><i class="bi bi-exclamation-triangle"></i><div><strong>Vui lòng kiểm tra:</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div></div>
        @endif

        {{-- V3.3 PREMIUM VAT + APPROVAL FLOW --}}
        <section class="op-summary-grid op-summary-grid-premium">
            <article class="op-summary-card op-summary-premium tone-navy">
                <span class="op-summary-icon">
                    <i class="bi bi-receipt"></i>
                </span>

                <div>
                    <span>Tổng giá trị</span>

                    <strong>
                        {{ number_format($total, 0, ',', '.') }} đ
                    </strong>

                    <small>Sau VAT và chiết khấu</small>
                </div>
            </article>

            <article class="op-summary-card op-summary-premium tone-teal">
                <span class="op-summary-icon">
                    <i class="bi bi-wallet2"></i>
                </span>

                <div>
                    <span>Đã thu</span>

                    <strong>
                        {{ number_format($paid, 0, ',', '.') }} đ
                    </strong>

                    <small>{{ $paymentPercent }}% giá trị đơn</small>
                </div>
            </article>

            <article class="op-summary-card op-summary-premium {{ $remaining > 0 ? 'tone-red' : 'tone-teal' }}">
                <span class="op-summary-icon">
                    <i class="bi bi-cash-coin"></i>
                </span>

                <div>
                    <span>Công nợ</span>

                    <strong>
                        {{ number_format($remaining, 0, ',', '.') }} đ
                    </strong>

                    <small>
                        Đã hoàn:
                        {{ number_format($refundPaid, 0, ',', '.') }} đ
                    </small>
                </div>
            </article>

            <article class="op-summary-card op-summary-premium {{ $order->inventory_issued ? 'tone-teal' : 'tone-amber' }}">
                <span class="op-summary-icon">
                    <i class="bi bi-box-seam"></i>
                </span>

                <div>
                    <span>Kho & tồn</span>

                    <strong>
                        {{ $order->inventory_issued ? 'Đã trừ tồn' : 'Chưa xuất kho' }}
                    </strong>

                    <small>
                        {{ $order->warehouse->name ?? 'Chưa chọn kho' }}

                        @if($order->inventory_issued_at)
                            · {{ $dateFmt($order->inventory_issued_at, 'd/m H:i') }}
                        @endif
                    </small>
                </div>
            </article>

            <article class="op-summary-card op-summary-premium {{ $activeReturns->count() ? 'tone-amber' : 'tone-blue' }}">
                <span class="op-summary-icon">
                    <i class="bi bi-arrow-left-right"></i>
                </span>

                <div>
                    <span>Hậu mãi</span>

                    <strong>
                        {{
                            $activeReturns->count()
                                ? $activeReturns->count().' đang xử lý'
                                : (
                                    $returns->count()
                                        ? 'Đã có hồ sơ'
                                        : 'Chưa có'
                                )
                        }}
                    </strong>

                    <small>{{ $returns->count() }} yêu cầu</small>
                </div>
            </article>
        </section>

        <section class="op-approval-strip op-no-print">
            <header class="op-approval-strip-head">
                <div>
                    <span class="op-approval-eyebrow">
                        Luồng xử lý trực tiếp
                    </span>

                    <h2>Quy trình duyệt đơn hàng</h2>

                    <p>
                        Đang ở
                        <strong>
                            {{
                                $departmentMap[$department]
                                ?? ucfirst($department)
                            }}
                        </strong>
                        · Bấm vào bước để xem lịch sử và thao tác duyệt.
                    </p>
                </div>

                <button
                    type="button"
                    class="op-btn op-btn-sm"
                    data-op-tab-target="approval"
                >
                    <i class="bi bi-clock-history"></i>
                    Chi tiết duyệt
                </button>
            </header>

            <div class="op-approval-track">
                @foreach($flowKeys as $stepIndex => $flowKey)
                    @php
                        $flowCompleted =
                            $currentFlow === 'completed';

                        $stepDone =
                            !$isCancelled
                            && (
                                $flowCompleted
                                    ? $stepIndex <= $currentIndex
                                    : $stepIndex < $currentIndex
                            );

                        $stepActive =
                            !$isCancelled
                            && !$flowCompleted
                            && $stepIndex === $currentIndex;

                        $stepClass =
                            $stepDone
                                ? 'done'
                                : (
                                    $stepActive
                                        ? 'active'
                                        : 'pending'
                                );
                    @endphp

                    <button
                        type="button"
                        class="op-approval-step {{ $stepClass }}"
                        data-op-tab-target="approval"
                    >
                        <span class="op-approval-node">
                            @if($stepDone)
                                <i class="bi bi-check-lg"></i>
                            @else
                                {{ $stepIndex + 1 }}
                            @endif
                        </span>

                        <span class="op-approval-copy">
                            <strong>
                                {{ $flowLabel[$flowKey] }}
                            </strong>

                            <small>
                                @if($stepDone)
                                    Đã xử lý
                                @elseif($stepActive)
                                    Đang xử lý
                                @else
                                    Chờ xử lý
                                @endif
                            </small>
                        </span>
                    </button>
                @endforeach
            </div>
        </section>


        <section class="op-main-stage">
            <div class="op-main-column">
                <article class="op-card op-main-card">
                    <header class="op-card-head op-main-head">
                        <div>
                            <h2><i class="bi bi-person-vcard"></i> Khách hàng & giao nhận</h2>
                            <p>Thông tin quan trọng được hiển thị trực tiếp, không cần mở tab.</p>
                        </div>
                        <span class="op-badge neutral">{{ $customerRegion }}</span>
                    </header>
                    <div class="op-card-body">
                        <div class="op-customer-grid">
                            <div class="op-customer-primary">
                                <span class="op-avatar">{{ mb_strtoupper(mb_substr($customer->name ?? 'K', 0, 1)) }}</span>
                                <div>
                                    <strong>{{ $customer->name ?? 'Chưa cập nhật khách hàng' }}</strong>
                                    <div class="op-customer-contact">
                                        <span><i class="bi bi-telephone"></i>{{ $customer->phone ?? '—' }}</span>
                                        <span><i class="bi bi-envelope"></i>{{ $customer->email ?? '—' }}</span>
                                    </div>
                                </div>
                            </div>
                            <div class="op-customer-detail">
                                <span>Địa chỉ giao hàng</span>
                                <strong>{{ $order->shipping_address ?: ($customer->address ?? '—') }}</strong>
                            </div>
                            <div class="op-customer-detail">
                                <span>Người nhận</span>
                                <strong>{{ $order->receiver_name ?: ($customer->name ?? '—') }}</strong>
                                <small>{{ $order->receiver_phone ?: ($customer->phone ?? '—') }}</small>
                            </div>
                        </div>
                    </div>
                </article>

                <article class="op-card op-main-card op-products-premium">
                    <header class="op-card-head op-main-head">
                        <div>
                            <h2>
                                <i class="bi bi-box-seam"></i>
                                Hàng hóa trong đơn
                            </h2>

                            <p>
                                Giá trước VAT, thuế VAT, giá sau VAT,
                                số lượng xuất và số lượng hoàn.
                            </p>
                        </div>

                        <span class="op-badge info">
                            {{ $order->items->count() }} mặt hàng
                        </span>
                    </header>

                    @php
                        $displayBeforeVat = 0;
                        $displayVatAmount = 0;
                        $displayAfterVat = 0;
                        $displayVatGroups = [];
                    @endphp

                    <div class="op-card-body op-table-wrap op-main-products-wrap">
                        {{-- SIMPLIFIED PRODUCT TABLE V3.4 --}}
                        @php
                            $displayBeforeVat = 0;
                            $displayVatAmount = 0;
                            $displayAfterVat = 0;
                            $displayVatGroups = [];
                        @endphp

                        <table class="op-table op-main-products-table op-product-vat-table op-product-table-v34">
                            <thead>
                                <tr>
                                    <th>Sản phẩm</th>

                                    <th>Kho</th>

                                    <th class="text-end">
                                        Giá trước VAT
                                    </th>

                                    <th class="text-center">
                                        SL
                                    </th>

                                    <th class="text-end">
                                        Thành tiền
                                    </th>
                                </tr>
                            </thead>

                            <tbody>
                                @foreach($order->items as $index => $item)
                                    @php
                                        $mainQuantity = max(
                                            1,
                                            (int) ($item->quantity ?? 1)
                                        );

                                        $mainVat = max(
                                            0,
                                            (float) (
                                                $item->vat_percent
                                                ?? $item->product?->vat_percent
                                                ?? 0
                                            )
                                        );

                                        $mainUnitAfter = max(
                                            0,
                                            (float) ($item->unit_price ?? 0)
                                        );

                                        $mainUnitBefore = $mainVat > 0
                                            ? $mainUnitAfter / (1 + $mainVat / 100)
                                            : $mainUnitAfter;

                                        $mainSubAfter =
                                            $mainUnitAfter * $mainQuantity;

                                        $mainDiscountPercent = max(
                                            0,
                                            min(
                                                100,
                                                (float) (
                                                    $item->discount_percent
                                                    ?? 0
                                                )
                                            )
                                        );

                                        $mainDiscountPerUnit = max(
                                            0,
                                            (float) (
                                                $item->discount_amount
                                                ?? 0
                                            )
                                        );

                                        $mainDiscount = $mainDiscountPerUnit > 0
                                            ? $mainDiscountPerUnit * $mainQuantity
                                            : (
                                                $mainSubAfter
                                                * $mainDiscountPercent
                                                / 100
                                            );

                                        $mainDiscount = min(
                                            $mainDiscount,
                                            $mainSubAfter
                                        );

                                        $mainLineAfter = max(
                                            0,
                                            $mainSubAfter - $mainDiscount
                                        );

                                        $mainLineBefore = $mainVat > 0
                                            ? $mainLineAfter
                                                / (1 + $mainVat / 100)
                                            : $mainLineAfter;

                                        $mainVatAmount = max(
                                            0,
                                            $mainLineAfter - $mainLineBefore
                                        );

                                        $mainVatLabel = rtrim(
                                            rtrim(
                                                number_format(
                                                    $mainVat,
                                                    2,
                                                    '.',
                                                    ''
                                                ),
                                                '0'
                                            ),
                                            '.'
                                        );

                                        $displayBeforeVat += $mainLineBefore;
                                        $displayVatAmount += $mainVatAmount;
                                        $displayAfterVat += $mainLineAfter;

                                        $displayVatGroups[$mainVatLabel] =
                                            (
                                                $displayVatGroups[
                                                    $mainVatLabel
                                                ]
                                                ?? 0
                                            )
                                            + $mainVatAmount;
                                    @endphp

                                    <tr
                                        @if($index >= 5)
                                            class="op-main-product-extra"
                                            hidden
                                        @endif
                                    >
                                        <td>
                                            <strong>
                                                {{
                                                    $item->product_name
                                                    ?: (
                                                        $item->product->name
                                                        ?? (
                                                            'Sản phẩm #'
                                                            .$item->product_id
                                                        )
                                                    )
                                                }}
                                            </strong>

                                            <small>
                                                SKU:
                                                {{
                                                    $item->product->sku
                                                    ?? '—'
                                                }}
                                            </small>
                                        </td>

                                        <td>
                                            {{
                                                $item->warehouse->name
                                                ?? $order->warehouse->name
                                                ?? '—'
                                            }}
                                        </td>

                                        <td class="text-end">
                                            {{
                                                number_format(
                                                    $mainUnitBefore,
                                                    0,
                                                    ',',
                                                    '.'
                                                )
                                            }}
                                            đ
                                        </td>

                                        <td class="text-center">
                                            <strong>
                                                {{ $mainQuantity }}
                                            </strong>
                                        </td>

                                        <td class="text-end">
                                            <strong>
                                                {{
                                                    number_format(
                                                        $mainLineAfter,
                                                        0,
                                                        ',',
                                                        '.'
                                                    )
                                                }}
                                                đ
                                            </strong>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>

                            <tfoot class="op-product-totals">
                                <tr>
                                    <td colspan="4">
                                        Tổng cộng trước VAT
                                    </td>

                                    <td class="text-end">
                                        {{
                                            number_format(
                                                $displayBeforeVat,
                                                0,
                                                ',',
                                                '.'
                                            )
                                        }}
                                        đ
                                    </td>
                                </tr>

                                @foreach(
                                    $displayVatGroups
                                    as $displayVatRate => $displayVatValue
                                )
                                    @if((float) $displayVatValue > 0)
                                        <tr class="op-vat-total-row">
                                            <td colspan="4">
                                                Thuế VAT
                                                {{ $displayVatRate }}%
                                            </td>

                                            <td class="text-end">
                                                {{
                                                    number_format(
                                                        $displayVatValue,
                                                        0,
                                                        ',',
                                                        '.'
                                                    )
                                                }}
                                                đ
                                            </td>
                                        </tr>
                                    @endif
                                @endforeach

                                <tr class="op-grand-total-row">
                                    <td colspan="4">
                                        Tổng cộng sau thuế
                                    </td>

                                    <td class="text-end">
                                        {{
                                            number_format(
                                                $displayAfterVat,
                                                0,
                                                ',',
                                                '.'
                                            )
                                        }}
                                        đ
                                    </td>
                                </tr>
                            </tfoot>
                        </table>

                        @if($order->items->count() > 5)
                            <div class="op-main-products-footer op-no-print">
                                <button
                                    type="button"
                                    class="op-btn op-btn-sm"
                                    data-op-toggle-products
                                    data-more-label="Xem toàn bộ {{ $order->items->count() }} sản phẩm"
                                    data-less-label="Thu gọn sản phẩm"
                                >
                                    <i class="bi bi-chevron-down"></i>

                                    <span>
                                        Xem toàn bộ
                                        {{ $order->items->count() }}
                                        sản phẩm
                                    </span>
                                </button>
                            </div>
                        @endif
                    </div>
                </article>

                <div class="op-main-mini-grid">
                    <article class="op-card op-mini-card">
                        <header class="op-card-head">
                            <h2><i class="bi bi-credit-card"></i> Thanh toán tóm tắt</h2>
                            @can('recordPayment', $order)
                                <button type="button" class="op-btn op-btn-primary op-btn-sm op-no-print" data-op-open="paymentModal">Ghi nhận</button>
                            @endcan
                        </header>
                        <div class="op-card-body">
                            <div class="op-mini-finance">
                                <div><span>Tổng đơn</span><strong>{{ number_format($total, 0, ',', '.') }} đ</strong></div>
                                <div><span>Đã thu</span><strong class="text-success">{{ number_format($paid, 0, ',', '.') }} đ</strong></div>
                                <div><span>Còn nợ</span><strong class="{{ $remaining > 0 ? 'text-danger' : 'text-success' }}">{{ number_format($remaining, 0, ',', '.') }} đ</strong></div>
                            </div>
                            <div class="op-progress"><span style="width:{{ $paymentPercent }}%"></span></div>
                            <p class="op-mini-note">
                                Gần nhất:
                                <strong>{{ $lastPayment ? number_format((float) $lastPayment->amount, 0, ',', '.').' đ' : 'Chưa có thanh toán' }}</strong>
                                @if($lastPayment)
                                    · {{ $dateFmt($lastPayment->payment_date ?? $lastPayment->created_at) }}
                                @endif
                            </p>
                        </div>
                    </article>

                    <article class="op-card op-mini-card">
                        <header class="op-card-head">
                            <h2><i class="bi bi-truck"></i> Kho & giao hàng</h2>
                            <button type="button" class="op-btn op-btn-sm op-no-print" data-op-tab-target="shipping">Chi tiết</button>
                        </header>
                        <div class="op-card-body op-mini-logistics">
                            <div><span>Kho xuất</span><strong>{{ $order->warehouse->name ?? 'Chưa chọn kho' }}</strong></div>
                            <div><span>Tồn kho</span><strong>{{ $order->inventory_issued ? 'Đã trừ tồn' : 'Chưa trừ tồn' }}</strong></div>
                            <div><span>Vận chuyển</span><strong>{{ $order->shipping_carrier ?: 'Chưa cập nhật' }}</strong></div>
                            <div><span>Ngày giao</span><strong>{{ $dateFmt($order->estimated_delivery) }}</strong></div>
                            <div><span>Mã vận đơn</span><strong>{{ $order->tracking_number ?: '—' }}</strong></div>
                            <div><span>Hậu mãi</span><strong>{{ $activeReturns->count() ? $activeReturns->count().' hồ sơ đang xử lý' : 'Không có yêu cầu mở' }}</strong></div>
                        </div>
                    </article>
                </div>
            </div>

            <aside class="op-command-card">
                <section class="op-command-section op-current-state">
                    <span class="op-command-eyebrow">Trạng thái hiện tại</span>
                    <div class="op-command-status">
                        <span class="op-state-icon {{ $isCancelled ? 'danger' : ($order->inventory_issued ? 'success' : 'info') }}">
                            <i class="bi {{ $isCancelled ? 'bi-x-lg' : ($order->inventory_issued ? 'bi-check-lg' : 'bi-hourglass-split') }}"></i>
                        </span>
                        <div>
                            <strong>{{ $statusName }}</strong>
                            <small>Đang ở: {{ $departmentMap[$department] ?? ucfirst($department) }}</small>
                        </div>
                    </div>
                </section>

                <section class="op-command-section">
                    <span class="op-command-eyebrow">Việc cần xử lý tiếp theo</span>
                    <h3>{{ $nextActionText }}</h3>
                    <div class="op-next-action op-no-print">
                        @if(in_array($currentFlow, ['sales','sales_manager','accounting','management'], true))
                            <button type="button" class="op-btn op-btn-primary op-btn-block" data-op-tab-target="approval">
                                <i class="bi bi-check2-square"></i> Mở quy trình duyệt
                            </button>
                        @elseif($currentFlow === 'warehouse' && !$order->inventory_issued)
                            @can('warehouseIssue', $order)
                                <button type="button" class="op-btn op-btn-primary op-btn-block" data-op-open="warehouseModal">
                                    <i class="bi bi-box-arrow-up"></i> Xử lý xuất kho
                                </button>
                            @else
                                <button type="button" class="op-btn op-btn-primary op-btn-block" data-op-tab-target="inventory">Xem kho & serial</button>
                            @endcan
                        @elseif($currentFlow === 'shipping')
                            @can('updateShippingInfo', $order)
                                <button type="button" class="op-btn op-btn-primary op-btn-block" data-op-open="shippingModal">
                                    <i class="bi bi-truck"></i> Cập nhật giao hàng
                                </button>
                            @else
                                <button type="button" class="op-btn op-btn-primary op-btn-block" data-op-tab-target="shipping">Xem vận chuyển</button>
                            @endcan
                        @elseif($canCreateCompletedReturn)
                            <button type="button" class="op-btn op-btn-primary op-btn-block" data-op-open="returnModal">
                                <i class="bi bi-arrow-counterclockwise"></i> Khách trả hàng
                            </button>
                        @else
                            <button type="button" class="op-btn op-btn-block" data-op-tab-target="history">Xem lịch sử xử lý</button>
                        @endif
                    </div>
                </section>

                <section class="op-command-section">
                    <span class="op-command-eyebrow">Cảnh báo & lưu ý</span>
                    @if($hasWarnings)
                        <ul class="op-warning-list">
                            @if($remaining > 0)
                                <li class="danger"><i class="bi bi-exclamation-circle"></i><span>Còn công nợ <strong>{{ number_format($remaining, 0, ',', '.') }} đ</strong>.</span></li>
                            @endif
                            @if(!$order->inventory_issued && $currentFlow === 'warehouse')
                                <li class="warning"><i class="bi bi-box-seam"></i><span>Đơn đang chờ kho kiểm tra tồn và serial.</span></li>
                            @endif
                            @if($order->inventory_issued && !$order->is_shipped)
                                <li class="warning"><i class="bi bi-truck"></i><span>Hàng đã trừ tồn nhưng chưa xác nhận vận chuyển.</span></li>
                            @endif
                            @if($activeReturns->count())
                                <li class="info"><i class="bi bi-arrow-counterclockwise"></i><span>Có {{ $activeReturns->count() }} hồ sơ hậu mãi đang xử lý.</span></li>
                            @endif
                            @if($returns->count() && !in_array($invoiceStatus, ['none','not_issued',''], true))
                                <li class="warning"><i class="bi bi-receipt"></i><span>Đơn đã có hóa đơn và phát sinh đổi trả; kế toán cần kiểm tra điều chỉnh hóa đơn.</span></li>
                            @endif
                        </ul>
                    @else
                        <div class="op-command-ok"><i class="bi bi-check-circle"></i><span>Không có cảnh báo cần xử lý ngay.</span></div>
                    @endif
                </section>

                <section class="op-command-section">
                    <span class="op-command-eyebrow">Thông tin nội bộ</span>
                    <div class="op-command-kv">
                        <div><span>Công ty</span><strong>{{ $order->company->name ?? '—' }}</strong></div>
                        <div><span>Người tạo</span><strong>{{ $order->creator->name ?? '—' }}</strong></div>
                        <div><span>Duyệt cuối</span><strong>{{ $order->approver->name ?? '—' }}</strong></div>
                        <div><span>Ngày giao dự kiến</span><strong>{{ $dateFmt($order->estimated_delivery) }}</strong></div>
                        <div><span>Ghi chú</span><strong>{{ $order->note ?: 'Không có ghi chú' }}</strong></div>
                    </div>
                </section>
            </aside>
        </section>

        <section class="op-shell">
            <nav class="op-tabs op-no-print" aria-label="Nghiệp vụ đơn hàng">
                @foreach([
                    'approval'=>'Quy trình duyệt','payments'=>'Thanh toán & công nợ','inventory'=>'Kho & serial',
                    'shipping'=>'Vận chuyển','returns'=>'Đổi trả & hoàn tiền','invoice'=>'Hóa đơn',
                    'documents'=>'Chứng từ','history'=>'Lịch sử'
                ] as $tabKey => $tabLabel)
                    <button type="button" class="op-tab {{ $activeTab === $tabKey ? 'active' : '' }}" data-op-tab="{{ $tabKey }}">
                        {{ $tabLabel }}
                        @if($tabKey === 'returns' && $activeReturns->count())<span class="op-count">{{ $activeReturns->count() }}</span>@endif
                    </button>
                @endforeach
            </nav>

            <div class="op-panels">

                <section class="op-panel {{ $activeTab === 'approval' ? 'active' : '' }}" data-op-panel="approval">
                    <div class="op-grid">
                        <article class="op-card op-col-8">
                            <header class="op-card-head"><h2><i class="bi bi-diagram-3"></i> Quy trình duyệt</h2></header>
                            <div class="op-card-body">
                                <div class="op-approval-list">
                                    @forelse($order->approvals ?? [] as $approval)
                                        <div class="op-approval-row"><span class="op-state-dot {{ $approval->status === 'approved' ? 'success' : ($approval->status === 'rejected' ? 'danger' : 'warning') }}"></span><div><strong>{{ ucfirst(str_replace('_',' ', (string) $approval->level)) }}</strong><small>{{ $approval->approver->name ?? 'Chưa có người xử lý' }} · {{ $dateFmt($approval->approved_at ?: $approval->created_at, 'd/m/Y H:i') }}</small></div><span class="op-badge {{ $approval->status === 'approved' ? 'success' : ($approval->status === 'rejected' ? 'danger' : 'warning') }}">{{ $approval->status }}</span></div>
                                    @empty
                                        <div class="op-empty">Chưa có lịch sử phê duyệt.</div>
                                    @endforelse
                                </div>
                            </div>
                        </article>
                        <article class="op-card op-col-4">
                            <header class="op-card-head"><h2><i class="bi bi-check2-square"></i> Thao tác duyệt</h2></header>
                            <div class="op-card-body">
                                @can('submit', $order)
                                    <form method="POST" action="{{ route('orders.submit', $order->id) }}?tab=approval" class="op-stack" data-op-confirm="Gửi đơn hàng đi duyệt?">@csrf<button class="op-btn op-btn-primary op-btn-block"><i class="bi bi-send"></i> Gửi duyệt</button></form>
                                @endcan
                                @can('approve', $order)
                                    <form method="POST" action="{{ route('orders.process-approval', $order->id) }}" class="op-stack" data-op-approval-form>
                                        @csrf
                                        <input type="hidden" name="return_tab" value="approval">
                                        @if($isAccountingApproval)
                                            <label class="op-check"><input type="checkbox" name="debt_checked" value="1"> Tôi đã kiểm tra công nợ</label>
                                            <label class="op-label">Ghi chú công nợ</label><textarea class="op-textarea" name="debt_note" rows="2"></textarea>
                                        @endif
                                        <label class="op-label">Quyết định</label>
                                        <div class="op-choice-row"><label class="op-choice success"><input type="radio" name="action" value="approve" checked> Duyệt</label><label class="op-choice danger"><input type="radio" name="action" value="reject"> Từ chối</label></div>
                                        <div data-op-reject-reason hidden><label class="op-label">Lý do từ chối</label><textarea class="op-textarea" name="rejection_reason" rows="3"></textarea></div>
                                        <label class="op-label">Ghi chú</label><textarea class="op-textarea" name="note" rows="2"></textarea>
                                        <button class="op-btn op-btn-primary op-btn-block"><i class="bi bi-check-circle"></i> Xác nhận xử lý</button>
                                    </form>
                                @else
                                    <div class="op-empty">Bạn không có thao tác duyệt ở bước hiện tại.</div>
                                @endcan
                            </div>
                        </article>
                    </div>
                </section>

                <section class="op-panel {{ $activeTab === 'payments' ? 'active' : '' }}" data-op-panel="payments">
                    <div class="op-grid">
                        <article class="op-card op-col-8">
                            <header class="op-card-head"><h2><i class="bi bi-credit-card"></i> Lịch sử thanh toán</h2>@can('recordPayment', $order)<button class="op-btn op-btn-primary op-btn-sm" type="button" data-op-open="paymentModal"><i class="bi bi-plus-lg"></i> Ghi nhận</button>@endcan</header>
                            <div class="op-card-body op-table-wrap">
                                <table class="op-table"><thead><tr><th>Ngày</th><th>Số tiền</th><th>Phương thức</th><th>Người ghi nhận</th><th>Ghi chú</th><th>Thao tác</th></tr></thead><tbody>
                                @forelse($order->payments ?? [] as $payment)
                                    <tr><td>{{ $dateFmt($payment->payment_date) }}</td><td class="text-success"><strong>+{{ number_format((float) $payment->amount, 0, ',', '.') }} đ</strong></td><td>{{ $payment->method->method_name ?? '—' }}</td><td>{{ $payment->recordedBy->name ?? '—' }}</td><td>{{ $payment->note ?: '—' }}</td><td><details class="op-inline-details"><summary>Sửa</summary><form method="POST" action="{{ route('orders.payments.update', $payment->id) }}" class="op-inline-form">@csrf @method('PUT')<input type="hidden" name="return_tab" value="payments"><input class="op-input" type="date" name="payment_date" value="{{ $payment->payment_date ? \Carbon\Carbon::parse($payment->payment_date)->format('Y-m-d') : '' }}" required><input class="op-input" type="number" name="amount" value="{{ $payment->amount }}" min="0.01" step="any" required><select class="op-select" name="method_id" required>@foreach($paymentMethods ?? [] as $method)<option value="{{ $method->id }}" @selected((int)$payment->method_id === (int)$method->id)>{{ $method->method_name }}</option>@endforeach</select><input class="op-input" name="note" value="{{ $payment->note }}"><button class="op-btn op-btn-primary op-btn-sm">Lưu</button></form>@can('recordPayment', $order)<form method="POST" action="{{ route('orders.payments.destroy', $payment->id) }}" data-op-confirm="Xóa giao dịch thanh toán này?">@csrf @method('DELETE')<button class="op-link-danger">Xóa giao dịch</button></form>@endcan</details></td></tr>
                                @empty<tr><td colspan="6"><div class="op-empty">Chưa có thanh toán.</div></td></tr>@endforelse
                                </tbody></table>
                            </div>
                        </article>
                        <article class="op-card op-col-4">
                            <header class="op-card-head"><h2>Tổng quan công nợ</h2></header>
                            <div class="op-card-body">
                                <div class="op-progress"><span style="width:{{ $paymentPercent }}%"></span></div>
                                <div class="op-kv-list"><div><span>Tổng đơn</span><strong>{{ number_format($total,0,',','.') }} đ</strong></div><div><span>Đã thu</span><strong class="text-success">{{ number_format($paid,0,',','.') }} đ</strong></div><div><span>Còn nợ</span><strong class="{{ $remaining > 0 ? 'text-danger' : 'text-success' }}">{{ number_format($remaining,0,',','.') }} đ</strong></div><div><span>Đã hoàn tiền</span><strong>{{ number_format($refundPaid,0,',','.') }} đ</strong></div></div>
                            </div>
                        </article>
                    </div>
                </section>

                <section class="op-panel {{ $activeTab === 'inventory' ? 'active' : '' }}" data-op-panel="inventory">
                    <div class="op-grid">
                        <article class="op-card op-col-8">
                            <header class="op-card-head"><h2><i class="bi bi-boxes"></i> Kho, lot và serial</h2>@can('warehouseIssue', $order)
                                @unless($order->inventory_issued)<button type="button" class="op-btn op-btn-primary op-btn-sm" data-op-open="warehouseModal"><i class="bi bi-box-arrow-up"></i> Xuất kho</button>@endunless
                                @endcan
                            </header>
                            <div class="op-card-body">
                                @if($order->inventory_issued)<div class="op-alert success"><i class="bi bi-check-circle"></i><div><strong>Đã xuất kho và trừ tồn.</strong><br>{{ $dateFmt($order->inventory_issued_at, 'd/m/Y H:i') }} · {{ $order->warehouse->name ?? 'Kho chưa xác định' }}</div></div>@else<div class="op-alert warning"><i class="bi bi-exclamation-triangle"></i><div>Đơn chưa xuất kho. Chỉ bộ phận Kho hoặc người có quyền mới được xác nhận xuất.</div></div>@endif
                                <h3 class="op-subtitle">Phân bổ lot FIFO</h3>
                                <div class="op-table-wrap"><table class="op-table"><thead><tr><th>Sản phẩm</th><th>Kho</th><th>Lot</th><th>SL xuất</th><th>Giá vốn</th><th>Tham chiếu</th></tr></thead><tbody>@forelse($stockAllocations ?? [] as $allocation)<tr><td>{{ $allocation->product_name ?? ('SP #'.$allocation->product_id) }}</td><td>{{ $allocation->warehouse_name ?? '—' }}</td><td>{{ $allocation->lot_code ?? ('#'.$allocation->stock_lot_id) }}</td><td>{{ $allocation->qty }}</td><td>{{ number_format((float)($allocation->unit_cost_after_vat ?? 0),0,',','.') }} đ</td><td>#{{ $allocation->id }}</td></tr>@empty<tr><td colspan="6"><div class="op-empty">Chưa có allocation kho.</div></td></tr>@endforelse</tbody></table></div>
                                <h3 class="op-subtitle">Serial đã gắn với đơn</h3>
                                <div class="op-table-wrap"><table class="op-table"><thead><tr><th>Sản phẩm</th><th>Serial</th><th>Trạng thái</th><th>Kho</th></tr></thead><tbody>@forelse($orderSerials ?? [] as $serial)<tr><td>{{ $serial->product_name ?? ('SP #'.$serial->product_id) }}</td><td><strong>{{ $serial->serial_code ?? ('#'.$serial->serial_unit_id) }}</strong></td><td><span class="op-badge info">{{ $serial->state ?? '—' }}</span></td><td>{{ $serial->warehouse_name ?? '—' }}</td></tr>@empty<tr><td colspan="4"><div class="op-empty">Chưa có serial gắn với đơn.</div></td></tr>@endforelse</tbody></table></div>
                            </div>
                        </article>
                        <article class="op-card op-col-4"><header class="op-card-head"><h2>Giao dịch kho</h2></header><div class="op-card-body op-activity-list">@forelse($stockMovements ?? [] as $movement)<div class="op-activity"><span class="op-state-dot {{ ($movement->change_qty ?? 0) < 0 ? 'danger' : 'success' }}"></span><div><strong>{{ $movement->reason ?? 'Biến động tồn kho' }}</strong><small>{{ $movement->product_name ?? ('SP #'.$movement->product_id) }} · {{ $movement->change_qty > 0 ? '+' : '' }}{{ $movement->change_qty }} · {{ $dateFmt($movement->created_at,'d/m H:i') }}</small></div></div>@empty<div class="op-empty">Chưa có giao dịch kho.</div>@endforelse</div></article>
                    </div>
                </section>

                <section class="op-panel {{ $activeTab === 'shipping' ? 'active' : '' }}" data-op-panel="shipping">
                    <div class="op-grid">
                        <article class="op-card op-col-8"><header class="op-card-head"><h2><i class="bi bi-truck"></i> Vận chuyển</h2>@can('updateShippingInfo', $order)<button type="button" class="op-btn op-btn-primary op-btn-sm" data-op-open="shippingModal">Cập nhật</button>@endcan</header><div class="op-card-body"><dl class="op-info-grid"><dt>Đơn vị vận chuyển</dt><dd>{{ $order->shipping_carrier ?: '—' }}</dd><dt>Mã vận đơn</dt><dd>{{ $order->tracking_number ?: '—' }}</dd><dt>Người nhận</dt><dd>{{ $order->receiver_name ?: '—' }}</dd><dt>Số điện thoại</dt><dd>{{ $order->receiver_phone ?: '—' }}</dd><dt>Địa chỉ</dt><dd>{{ $order->shipping_address ?: '—' }}</dd><dt>Ngày dự kiến</dt><dd>{{ $dateFmt($order->estimated_delivery) }}</dd><dt>Trạng thái</dt><dd>{{ $order->shipping_status ?: '—' }}</dd><dt>Ghi chú</dt><dd>{{ $order->shipping_note ?: '—' }}</dd></dl></div></article>
                        <article class="op-card op-col-4"><header class="op-card-head"><h2>Phí vận chuyển</h2></header><div class="op-card-body op-kv-list"><div><span>Kho → chành</span><strong>{{ number_format((float)($order->shipping_fee_warehouse_to_station ?? 0),0,',','.') }} đ</strong></div><div><span>Chành → khách</span><strong>{{ number_format((float)($order->shipping_fee_station_to_customer ?? 0),0,',','.') }} đ</strong></div><div><span>Người chịu phí</span><strong>{{ ($order->shipping_fee_payer ?? 'company') === 'customer' ? 'Khách chịu' : 'Công ty chịu' }}</strong></div>@can('markShipped', $order)
                                @if(!$order->is_shipped)<form method="POST" action="{{ route('orders.markShipped',$order->id) }}?tab=shipping" data-op-confirm="Xác nhận đơn đã được vận chuyển?">@csrf<button class="op-btn op-btn-primary op-btn-block">Đánh dấu đã vận chuyển</button></form>@endif
                                @endcan
                            </div></article>
                    </div>
                </section>

                <section class="op-panel {{ $activeTab === 'returns' ? 'active' : '' }}" data-op-panel="returns">
                    <div class="op-card">
                        <header class="op-card-head"><h2><i class="bi bi-arrow-left-right"></i> Đổi trả & hoàn tiền</h2>@if($canCreateCompletedReturn)<button type="button" class="op-btn op-btn-primary op-btn-sm" data-op-open="returnModal"><i class="bi bi-arrow-counterclockwise"></i> Khách trả hàng</button>@endif</header>
                        <div class="op-card-body">
                            @if($order->inventory_issued)<div class="op-alert warning"><i class="bi bi-exclamation-triangle"></i><div><strong>Đơn đã xuất kho và trừ tồn.</strong> Hàng chỉ được cộng lại sau khi kho xác nhận nhận hàng, kiểm tra và nhập hoàn.</div></div>@endif
                            @forelse($returns as $return)
                                <article class="op-return-card">
                                    <div class="op-return-head"><div><strong>{{ $return->return_code }}</strong><small>{{ ucfirst($return->type) }} · {{ $dateFmt($return->created_at,'d/m/Y H:i') }}</small></div><span class="op-badge {{ in_array($return->status,['completed','stocked_in']) ? 'success' : (str_contains((string)$return->status,'pending') ? 'warning' : 'info') }}">{{ $return->status_label ?? $return->status }}</span></div>
                                    <div class="op-return-grid"><div><span>Giá trị dự kiến</span><strong>{{ number_format((float)($return->total_return_amount ?? 0),0,',','.') }} đ</strong></div><div><span>Kho nhận</span><strong>{{ $return->receivingWarehouse->name ?? 'Chưa chọn' }}</strong></div><div><span>Sản phẩm</span><strong>{{ collect($return->items ?? [])->sum('requested_quantity') }} sản phẩm</strong></div><div><span>Hoàn tiền</span><strong>{{ number_format((float)collect($return->refunds ?? [])->sum('amount'),0,',','.') }} đ</strong></div></div>
                                    <details class="op-return-details"><summary>Xử lý hồ sơ</summary><div class="op-return-actions">
                                        @if($return->status === 'draft' || $return->status === 'revision_requested')<form method="POST" action="{{ route('order-returns.submit',$return) }}">@csrf<button class="op-btn op-btn-primary op-btn-sm">Gửi duyệt</button></form>@endif
                                        @if($canReturnApprove && str_starts_with((string)$return->status,'pending_'))<form method="POST" action="{{ route('order-returns.approve',$return) }}" class="op-inline-action">@csrf<input class="op-input" name="comment" placeholder="Ghi chú duyệt"><button class="op-btn op-btn-success op-btn-sm">Duyệt</button></form><form method="POST" action="{{ route('order-returns.revision',$return) }}" class="op-inline-action">@csrf<input class="op-input" name="comment" required placeholder="Nội dung cần sửa"><button class="op-btn op-btn-warning op-btn-sm">Yêu cầu sửa</button></form><form method="POST" action="{{ route('order-returns.reject',$return) }}" class="op-inline-action" data-op-confirm="Từ chối yêu cầu này?">@csrf<input class="op-input" name="comment" required placeholder="Lý do từ chối"><button class="op-btn op-btn-danger op-btn-sm">Từ chối</button></form>@endif
                                        @if(in_array($return->status,['approved_waiting_return'],true))<form method="POST" action="{{ route('order-returns.in-transit',$return) }}">@csrf<button class="op-btn op-btn-sm">Hàng đang thu hồi</button></form>@endif
                                        <?php if ($canReturnReceive && in_array($return->status, ['approved_waiting_return', 'return_in_transit'], true)): ?>
                                            <form method="POST"
                                                  action="{{ route('order-returns.receive', $return) }}"
                                                  class="op-action-form">
                                                @csrf

                                                <label class="op-label">Kho nhận</label>

                                                <select class="op-select"
                                                        name="receiving_warehouse_id"
                                                        required>
                                                    <?php foreach (collect($returnWarehouses ?? []) as $warehouse): ?>
                                                        <option value="{{ $warehouse->id }}"
                                                            @selected(
                                                                (int) $return->receiving_warehouse_id
                                                                === (int) $warehouse->id
                                                            )>
                                                            {{ $warehouse->name }}
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>

                                                <?php foreach (collect($return->items ?? []) as $returnItem): ?>
                                                    <label class="op-label">
                                                        {{ $returnItem->product->name ?? 'Sản phẩm' }}
                                                        · SL nhận
                                                    </label>

                                                    <input class="op-input"
                                                           type="number"
                                                           name="received[{{ $returnItem->id }}]"
                                                           min="0"
                                                           max="{{ $returnItem->requested_quantity }}"
                                                           value="{{ $returnItem->requested_quantity }}"
                                                           required>
                                                <?php endforeach; ?>

                                                <button class="op-btn op-btn-primary op-btn-sm">
                                                    Xác nhận nhận hàng
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                        <?php if ($canReturnInspect && in_array($return->status, ['received', 'inspecting'], true)): ?>
                                            <form method="POST"
                                                  action="{{ route('order-returns.inspect', $return) }}"
                                                  class="op-action-form">
                                                @csrf

                                                <?php foreach (collect($return->items ?? []) as $returnItem): ?>
                                                    <div class="op-inspect-row">
                                                        <strong>
                                                            {{ $returnItem->product->name ?? 'Sản phẩm' }}
                                                        </strong>

                                                        <input class="op-input"
                                                               type="number"
                                                               name="inspect[{{ $returnItem->id }}][accepted_quantity]"
                                                               min="0"
                                                               value="{{ $returnItem->received_quantity ?? $returnItem->requested_quantity }}"
                                                               required>

                                                        <input class="op-input"
                                                               type="number"
                                                               name="inspect[{{ $returnItem->id }}][rejected_quantity]"
                                                               min="0"
                                                               value="0">

                                                        <select class="op-select"
                                                                name="inspect[{{ $returnItem->id }}][condition]"
                                                                required>
                                                            <option value="sellable">Còn tốt</option>
                                                            <option value="opened_box">Đã mở hộp</option>
                                                            <option value="defective">Lỗi</option>
                                                            <option value="warranty_pending">
                                                                Chờ bảo hành
                                                            </option>
                                                            <option value="damaged">Hư hỏng</option>
                                                            <option value="scrap">Thanh lý</option>
                                                        </select>

                                                        <select class="op-select"
                                                                name="inspect[{{ $returnItem->id }}][resolution]">
                                                            <option value="restock">Nhập kho</option>
                                                            <option value="exchange">Đổi hàng</option>
                                                            <option value="warranty">Bảo hành</option>
                                                            <option value="refund">Hoàn tiền</option>
                                                        </select>
                                                    </div>
                                                <?php endforeach; ?>

                                                <button class="op-btn op-btn-primary op-btn-sm">
                                                    Lưu kiểm tra
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                        @if($canReturnStockIn && $return->status === 'inspected')<form method="POST" action="{{ route('order-returns.stock-in',$return) }}" data-op-confirm="Xác nhận nhập hoàn kho? Hệ thống sẽ tạo giao dịch kho đối ứng.">@csrf<button class="op-btn op-btn-success op-btn-sm">Nhập hoàn kho</button></form>@endif
                                        @if($canRefundCreate && in_array($return->status,['inspected','stocked_in','pending_refund'],true))<form method="POST" action="{{ route('order-returns.refunds.store',$return) }}" class="op-action-form">@csrf<input class="op-input" type="number" name="amount" min="1" placeholder="Số tiền hoàn" required><select class="op-select" name="method" required><option value="bank">Chuyển khoản</option><option value="cash">Tiền mặt</option><option value="debt_credit">Cấn công nợ</option><option value="exchange_credit">Cấn đơn đổi</option></select><input class="op-input" name="note" placeholder="Ghi chú"><button class="op-btn op-btn-primary op-btn-sm">Tạo phiếu hoàn tiền</button></form>@endif
                                        <?php foreach (collect($return->refunds ?? []) as $refund): ?>
                                            <div class="op-refund-row">
                                                <span>
                                                    {{ $refund->refund_code ?? ('RF#'.$refund->id) }}
                                                    ·
                                                    {{ number_format((float) $refund->amount, 0, ',', '.') }} đ
                                                    ·
                                                    {{ $refund->status }}
                                                </span>

                                                <div>
                                                    <?php if (
                                                        $canRefundApprove
                                                        && $refund->status === 'pending'
                                                    ): ?>
                                                        <form method="POST"
                                                              action="{{ route('order-refunds.approve', $refund) }}"
                                                              style="display:inline">
                                                            @csrf

                                                            <button class="op-btn op-btn-success op-btn-sm">
                                                                Duyệt hoàn
                                                            </button>
                                                        </form>
                                                    <?php endif; ?>

                                                    <?php if (
                                                        $canByRole(['admin', 'accounting'])
                                                        && in_array(
                                                            $refund->status,
                                                            ['approved', 'processing'],
                                                            true
                                                        )
                                                    ): ?>
                                                        <form method="POST"
                                                              action="{{ route('order-refunds.process', $refund) }}"
                                                              style="display:inline">
                                                            @csrf

                                                            <button class="op-btn op-btn-primary op-btn-sm">
                                                                Đã xử lý tiền
                                                            </button>
                                                        </form>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                        <form method="POST" action="{{ route('order-returns.upload',$return) }}" enctype="multipart/form-data" class="op-action-form">@csrf<input class="op-input" type="file" name="attachments[]" multiple><input class="op-input" name="category" value="evidence" placeholder="Loại hồ sơ"><button class="op-btn op-btn-sm">Tải hồ sơ</button></form>
                                    </div></details>
                                </article>
                            @empty
                                <div class="op-empty"><i class="bi bi-arrow-left-right"></i><strong>Chưa có yêu cầu đổi trả</strong><span>Tạo yêu cầu hoàn hàng, đổi hàng hoặc thu hồi ngay trong trang này.</span></div>
                            @endforelse
                        </div>
                    </div>
                </section>

                <section class="op-panel {{ $activeTab === 'invoice' ? 'active' : '' }}" data-op-panel="invoice">
                    <div class="op-grid"><article class="op-card op-col-8"><header class="op-card-head"><h2><i class="bi bi-receipt"></i> Thông tin hóa đơn</h2><button type="button" class="op-btn op-btn-primary op-btn-sm" data-op-open="invoiceModal">Cập nhật</button></header><div class="op-card-body"><dl class="op-info-grid"><dt>Trạng thái</dt><dd>{{ ['none'=>'Không yêu cầu','pending'=>'Chờ xuất','issued'=>'Đã xuất'][$invoiceStatus] ?? $invoiceStatus }}</dd><dt>Tên công ty</dt><dd>{{ $order->invoice_company_name ?: '—' }}</dd><dt>Mã số thuế</dt><dd>{{ $order->invoice_tax_code ?: '—' }}</dd><dt>Địa chỉ</dt><dd>{{ $order->invoice_address ?: '—' }}</dd><dt>Email</dt><dd>{{ $order->invoice_email ?: '—' }}</dd><dt>File hóa đơn</dt><dd>@if($order->invoice_file)<a href="{{ asset('storage/'.$order->invoice_file) }}" target="_blank">Xem file</a>@else—@endif</dd></dl></div></article><article class="op-card op-col-4"><header class="op-card-head"><h2>Lưu ý</h2></header><div class="op-card-body">@if($invoiceStatus === 'issued' && $returns->count())<div class="op-alert warning"><i class="bi bi-exclamation-triangle"></i><div>Đơn đã xuất hóa đơn và có hồ sơ đổi trả. Kế toán cần xử lý hóa đơn điều chỉnh hoặc thay thế.</div></div>@else<div class="op-empty">Chưa có yêu cầu điều chỉnh hóa đơn.</div>@endif</div></article></div>
                </section>

                <section class="op-panel {{ $activeTab === 'documents' ? 'active' : '' }}" data-op-panel="documents">
                    @include('ego_order_documents.order_box')
                </section>

                <section class="op-panel {{ $activeTab === 'history' ? 'active' : '' }}" data-op-panel="history">
                    <div class="op-grid"><article class="op-card op-col-8"><header class="op-card-head"><h2><i class="bi bi-clock-history"></i> Nhật ký xử lý</h2></header><div class="op-card-body op-activity-list">@forelse($timeline as $event)<div class="op-activity"><span class="op-state-dot info"></span><div><strong>{{ $event->statusType->name ?? $event->status ?? 'Cập nhật đơn hàng' }}</strong><small>{{ $event->note ?? '' }} · {{ $event->changedBy->name ?? 'Hệ thống' }} · {{ $dateFmt($event->changed_at ?? $event->created_at,'d/m/Y H:i') }}</small></div></div>@empty<div class="op-empty">Chưa có nhật ký.</div>@endforelse</div></article><article class="op-card op-col-4"><header class="op-card-head"><h2>Lịch sử chỉnh sửa</h2><span class="op-badge neutral">{{ collect($editHistories ?? [])->count() }} lần</span></header><div class="op-card-body op-activity-list">@forelse(collect($editHistories ?? [])->take(20) as $history)<div class="op-activity"><span class="op-state-dot warning"></span><div><strong>{{ $history->user_name ?? 'Không rõ' }}</strong><small>{{ $dateFmt($history->created_at,'d/m H:i') }} · Chi tiết đơn hàng đã thay đổi</small></div></div>@empty<div class="op-empty">Chưa có chỉnh sửa.</div>@endforelse</div></article></div>
                </section>
            </div>
        </section>
    </div>
</div>

<div class="op-drawer-backdrop op-no-print" id="quickActions" data-op-overlay>
    <aside class="op-drawer op-drawer-sm"><header><div><small>Tác vụ đơn hàng</small><h3>{{ $order->order_code }}</h3></div><button type="button" class="op-icon-btn" data-op-close><i class="bi bi-x-lg"></i></button></header><div class="op-drawer-body op-action-list">
        <button type="button" class="op-action-button" data-op-tab-target="approval" data-op-close><i class="bi bi-diagram-3"></i><span>Quy trình duyệt<small>Gửi duyệt, phê duyệt hoặc từ chối</small></span></button>
        <button type="button" class="op-action-button" data-op-tab-target="payments" data-op-close><i class="bi bi-cash-stack"></i><span>Thanh toán & công nợ<small>Ghi nhận hoặc điều chỉnh thanh toán</small></span></button>
        <button type="button" class="op-action-button" data-op-tab-target="inventory" data-op-close><i class="bi bi-boxes"></i><span>Kho & serial<small>Xuất kho, lot và serial</small></span></button>
        <button type="button" class="op-action-button" data-op-tab-target="shipping" data-op-close><i class="bi bi-truck"></i><span>Vận chuyển<small>Cập nhật giao hàng</small></span></button>
        <button type="button" class="op-action-button" data-op-tab-target="returns" data-op-close><i class="bi bi-arrow-left-right"></i><span>Đổi trả & hoàn tiền<small>Xử lý hậu mãi</small></span></button>
        <button type="button" class="op-action-button" data-op-tab-target="documents" data-op-close><i class="bi bi-folder2-open"></i><span>Chứng từ<small>Upload và quản lý tài liệu</small></span></button>
    </div></aside>
</div>

<div class="op-modal-backdrop op-no-print" id="paymentModal" data-op-overlay><div class="op-modal"><form action="{{ route('orders.record-payment',$order->id) }}?tab=payments" method="POST">@csrf<header><h3>Ghi nhận thanh toán</h3><button type="button" class="op-icon-btn" data-op-close><i class="bi bi-x-lg"></i></button></header><div class="op-modal-body"><div class="op-alert info"><i class="bi bi-info-circle"></i><div>Tổng: <strong>{{ number_format($total,0,',','.') }} đ</strong> · Còn lại: <strong>{{ number_format($remaining,0,',','.') }} đ</strong></div></div><div class="op-form-grid"><label>Ngày thanh toán<input class="op-input" type="date" name="payment_date" value="{{ date('Y-m-d') }}" required></label><label>Số tiền thực nhận<input class="op-input" type="number" name="amount" value="{{ $remaining }}" min="0.01" step="any" required></label><label class="op-full">Phương thức<select class="op-select" name="method_id" required><option value="">Chọn phương thức</option>@foreach($paymentMethods ?? [] as $method)<option value="{{ $method->id }}">{{ $method->method_name }} ({{ $method->code }})</option>@endforeach</select></label><label class="op-full">Ghi chú<textarea class="op-textarea" name="note" rows="3"></textarea></label></div></div><footer><button type="button" class="op-btn" data-op-close>Đóng</button><button class="op-btn op-btn-primary">Lưu thanh toán</button></footer></form></div></div>

<div class="op-drawer-backdrop op-no-print" id="warehouseModal" data-op-overlay><aside class="op-drawer"><form action="{{ route('orders.warehouse.issue',$order->id) }}?tab=inventory" method="POST" id="shipOrderForm">@csrf<header><div><small>Kho & serial</small><h3>Xác nhận xuất kho</h3></div><button type="button" class="op-icon-btn" data-op-close><i class="bi bi-x-lg"></i></button></header><div class="op-drawer-body"><div class="op-alert warning"><i class="bi bi-exclamation-triangle"></i><div>Hành động này sẽ trừ tồn kho và hoàn tất đơn hàng. Chỉ xác nhận sau khi đã kiểm tra đúng kho, lot và serial.</div></div><div class="op-form-grid"><label>Ngày xuất kho<input class="op-input" type="date" name="actual_ship_date" value="{{ date('Y-m-d') }}" required></label><label>Bảo hành (tháng)<input class="op-input" type="number" name="warranty_months" min="1" max="240" value="60" required></label><label class="op-full">Ghi chú<textarea class="op-textarea" name="shipping_note" rows="3"></textarea></label></div><div class="op-serial-tools"><h4>Chọn Serial/IMEI</h4><input class="op-input" id="serialSearch" placeholder="Tìm serial..."></div><div id="ship-serials-container"><div class="op-empty">Danh sách serial sẽ được tải khi mở cửa sổ này.</div></div><div id="ship-serials-error" class="op-alert danger" hidden></div></div><footer><button type="button" class="op-btn" data-op-close>Đóng</button><button class="op-btn op-btn-primary" id="btnConfirmShip">Xác nhận xuất kho</button></footer></form></aside></div>

<div class="op-drawer-backdrop op-no-print" id="shippingModal" data-op-overlay><aside class="op-drawer"><form action="{{ route('orders.shippingInfo',$order->id) }}?tab=shipping" method="POST">@csrf<header><div><small>Vận chuyển</small><h3>Cập nhật giao hàng</h3></div><button type="button" class="op-icon-btn" data-op-close><i class="bi bi-x-lg"></i></button></header><div class="op-drawer-body">
        <div class="op-alert warning">
            <i class="bi bi-exclamation-triangle"></i>

            <div>
                <strong>
                    Đơn đã xuất kho và đã trừ tồn.
                </strong>

                <br>

                Việc tạo phiếu trả hàng chưa làm cộng lại tồn kho.
                Kho phải nhận hàng, kiểm tra tình trạng và xác nhận
                nhập hoàn trước khi số lượng được cộng lại.
            </div>
        </div>

        <div class="op-return-quick-tools">
            <div>
                <strong>Chọn số lượng khách trả</strong>

                <small>
                    Có thể trả toàn bộ hoặc chỉ một phần đơn hàng.
                </small>
            </div>

            <div class="op-return-quick-actions">
                <button
                    type="button"
                    class="op-btn op-btn-sm"
                    data-return-fill-all
                >
                    Trả toàn bộ
                </button>

                <button
                    type="button"
                    class="op-btn op-btn-sm"
                    data-return-clear
                >
                    Xóa số lượng
                </button>
            </div>
        </div>

        <div class="op-form-grid"><label>Đơn vị vận chuyển<input class="op-input" name="shipping_carrier" value="{{ $order->shipping_carrier }}"></label><label>Mã vận đơn<input class="op-input" name="tracking_number" value="{{ $order->tracking_number }}"></label><label>Người nhận<input class="op-input" name="receiver_name" value="{{ $order->receiver_name }}"></label><label>Số điện thoại<input class="op-input" name="receiver_phone" value="{{ $order->receiver_phone }}"></label><label class="op-full">Địa chỉ<input class="op-input" name="shipping_address" value="{{ $order->shipping_address }}"></label><label>Ngày giao dự kiến<input class="op-input" type="date" name="estimated_delivery" value="{{ $order->estimated_delivery ? \Carbon\Carbon::parse($order->estimated_delivery)->format('Y-m-d') : '' }}"></label><label>Kho → chành<input class="op-input" type="number" min="0" name="shipping_fee_warehouse_to_station" value="{{ $order->shipping_fee_warehouse_to_station ?? 0 }}"></label><label>Chành → khách<input class="op-input" type="number" min="0" name="shipping_fee_station_to_customer" value="{{ $order->shipping_fee_station_to_customer ?? 0 }}"></label><label>Người chịu phí<select class="op-select" name="shipping_fee_payer"><option value="company" @selected(($order->shipping_fee_payer ?? 'company') === 'company')>Công ty chịu</option><option value="customer" @selected(($order->shipping_fee_payer ?? '') === 'customer')>Khách chịu</option></select></label><label class="op-full">Ghi chú<textarea class="op-textarea" name="shipping_note" rows="3">{{ $order->shipping_note }}</textarea></label></div></div><footer><button type="button" class="op-btn" data-op-close>Đóng</button><button class="op-btn op-btn-primary">Lưu vận chuyển</button></footer></form></aside></div>

<div class="op-drawer-backdrop op-no-print" id="invoiceModal" data-op-overlay><aside class="op-drawer"><form action="{{ route('orders.updateInvoice',$order->id) }}?tab=invoice" method="POST" enctype="multipart/form-data">@csrf<header><div><small>Hóa đơn</small><h3>Cập nhật thông tin hóa đơn</h3></div><button type="button" class="op-icon-btn" data-op-close><i class="bi bi-x-lg"></i></button></header><div class="op-drawer-body"><div class="op-form-grid"><label class="op-full">Trạng thái<select class="op-select" name="invoice_status"><option value="none" @selected($invoiceStatus === 'none')>Không yêu cầu</option><option value="pending" @selected($invoiceStatus === 'pending')>Chờ xuất</option><option value="issued" @selected($invoiceStatus === 'issued')>Đã xuất</option></select></label><label>Tên công ty<input class="op-input" name="invoice_company_name" value="{{ $order->invoice_company_name }}"></label><label>Mã số thuế<input class="op-input" name="invoice_tax_code" value="{{ $order->invoice_tax_code }}"></label><label class="op-full">Địa chỉ<input class="op-input" name="invoice_address" value="{{ $order->invoice_address }}"></label><label>Email<input class="op-input" type="email" name="invoice_email" value="{{ $order->invoice_email }}"></label><label>File hóa đơn<input class="op-input" type="file" name="invoice_file"></label></div></div><footer><button type="button" class="op-btn" data-op-close>Đóng</button><button class="op-btn op-btn-primary">Lưu hóa đơn</button></footer></form></aside></div>

<div class="op-drawer-backdrop op-no-print" id="returnModal" data-op-overlay><aside class="op-drawer op-drawer-wide"><form method="POST" action="{{ route('orders.returns.store',$order) }}" enctype="multipart/form-data" id="completedReturnForm" data-completed-return-form>@csrf<input type="hidden"
               name="from_order_page"
               value="1">

        {{-- EGO_COMPLETED_RETURN_FORM_V1 --}}<header><div><small>Đơn đã hoàn thành</small><h3>Khách trả hàng</h3></div><button type="button" class="op-icon-btn" data-op-close><i class="bi bi-x-lg"></i></button></header><div class="op-drawer-body"><div class="op-form-grid"><label>
            Hình thức xử lý

            <select
                class="op-select"
                name="type"
                required
            >
                <option value="return">
                    Khách hoàn trả hàng
                </option>

                <option value="exchange">
                    Khách đổi sang sản phẩm khác
                </option>

                <option value="recall">
                    Thu hồi hàng đã giao
                </option>
            </select>
        </label><label>Kho dự kiến nhận<select class="op-select" name="receiving_warehouse_id"><option value="">Chọn sau</option>@foreach($returnWarehouses ?? [] as $warehouse)<option value="{{ $warehouse->id }}">{{ $warehouse->name }}</option>@endforeach</select></label><label>Lý do<select class="op-select" name="reason_code" required><option value="customer_change">Khách thay đổi nhu cầu</option><option value="wrong_item">Giao sai hàng</option><option value="technical_fault">Lỗi kỹ thuật</option><option value="shipping_damage">Hư hỏng vận chuyển</option><option value="warranty">Thu hồi bảo hành</option><option value="other">Khác</option></select></label><label>Phương án tài chính<select class="op-select" name="refund_method"><option value="none">Chờ kiểm tra</option><option value="bank">Hoàn chuyển khoản</option><option value="cash">Hoàn tiền mặt</option><option value="debt_credit">Cấn trừ công nợ</option><option value="exchange_credit">Cấn sang đơn đổi</option></select></label><label class="op-full">Mô tả chi tiết<textarea class="op-textarea" name="reason_detail" rows="3" required></textarea></label></div><div class="op-return-items">@foreach($order->items as $item)<article class="op-return-item"><div class="op-return-item-head"><div><strong>{{ $item->product_name ?: ($item->product->name ?? 'Sản phẩm') }}</strong><small>Đã giao {{ $item->quantity }} · Có thể yêu cầu {{ $returnAvailable[$item->id] ?? 0 }}</small></div><input class="op-input op-qty" type="number" data-return-qty min="0" max="{{ $returnAvailable[$item->id] ?? 0 }}" name="items[{{ $item->id }}][quantity]" value="0"></div><div class="op-form-grid"><label>Tình trạng dự kiến<select class="op-select" name="items[{{ $item->id }}][condition]"><option value="sellable">Còn tốt</option><option value="opened_box">Đã mở hộp</option><option value="defective">Lỗi</option><option value="warranty_pending">Chờ bảo hành</option><option value="damaged">Hư hỏng</option></select></label><label>Phương án<select class="op-select" name="items[{{ $item->id }}][resolution]"><option value="inspect">Chờ kiểm tra</option><option value="restock">Nhập lại kho</option><option value="exchange">Đổi sản phẩm</option><option value="warranty">Chuyển bảo hành</option><option value="refund">Hoàn tiền</option></select></label></div>@if(collect($returnSerialsByItem[$item->id] ?? [])->count())<div class="op-serial-checks">@foreach($returnSerialsByItem[$item->id] as $serial)<label><input type="checkbox" name="items[{{ $item->id }}][serial_ids][]" value="{{ $serial->id }}"> {{ $serial->code }} <small>{{ $serial->state }}</small></label>@endforeach</div>@endif</article>@endforeach</div><div class="op-form-grid"><label>Phí xử lý<input class="op-input" type="number" min="0" name="restocking_fee" value="0"></label><label>Phí vận chuyển trừ vào hoàn<input class="op-input" type="number" min="0" name="shipping_fee" value="0"></label><label class="op-full">Ảnh, video, biên bản<input class="op-input" type="file" name="attachments[]" multiple></label><label class="op-full">Ghi chú nội bộ<textarea class="op-textarea" name="note" rows="3"></textarea></label></div></div><footer><button type="button" class="op-btn" data-op-close>Đóng</button><button class="op-btn op-btn-primary" type="submit" data-return-submit>Tạo phiếu trả hàng</button></footer></form></aside></div>

<script>
window.ORDER_ONE_PAGE = {
    orderId: {{ (int) $order->id }},
    serialUrl: @json(route('orders.ship-serials', $order->id)),
    activeTab: @json($activeTab)
};
</script>
<script>
/* EGO_COMPLETED_RETURN_JS_V1 */
document.addEventListener('DOMContentLoaded', function () {
    const form = document.querySelector(
        '[data-completed-return-form]'
    );

    if (!form) {
        return;
    }

    const quantities = Array.from(
        form.querySelectorAll('[data-return-qty]')
    );

    const fillAll = form.querySelector(
        '[data-return-fill-all]'
    );

    const clearAll = form.querySelector(
        '[data-return-clear]'
    );

    const submitButton = form.querySelector(
        '[data-return-submit]'
    );

    if (fillAll) {
        fillAll.addEventListener('click', function () {
            quantities.forEach(function (input) {
                const maximum = Number(
                    input.getAttribute('max') || 0
                );

                input.value = maximum;
                input.dispatchEvent(
                    new Event('change', {
                        bubbles: true
                    })
                );
            });
        });
    }

    if (clearAll) {
        clearAll.addEventListener('click', function () {
            quantities.forEach(function (input) {
                input.value = 0;
                input.dispatchEvent(
                    new Event('change', {
                        bubbles: true
                    })
                );
            });
        });
    }

    form.addEventListener('submit', function (event) {
        const totalQuantity = quantities.reduce(
            function (total, input) {
                return total + Number(input.value || 0);
            },
            0
        );

        if (totalQuantity <= 0) {
            event.preventDefault();

            alert(
                'Vui lòng chọn ít nhất một sản phẩm '
                + 'và nhập số lượng khách trả.'
            );

            const firstInput = quantities.find(
                function (input) {
                    return Number(
                        input.getAttribute('max') || 0
                    ) > 0;
                }
            );

            if (firstInput) {
                firstInput.focus();
            }

            return;
        }

        if (
            !window.confirm(
                'Xác nhận tạo phiếu khách trả '
                + totalQuantity
                + ' sản phẩm?\n\n'
                + 'Tồn kho chưa được cộng lại ở bước này.'
            )
        ) {
            event.preventDefault();
            return;
        }

        if (submitButton) {
            submitButton.disabled = true;
            submitButton.textContent =
                'Đang tạo phiếu...';
        }
    });
});
</script>

@endsection
