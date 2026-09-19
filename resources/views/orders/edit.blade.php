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

    $orderEditPriceTiersJs = collect($priceTiers ?? [])
        ->map(function ($tier) {
            return [
                'id' => (int) ($tier->id ?? 0),
                'code' => (string) ($tier->code ?? ''),
                'name' => (string) ($tier->name ?? ''),
            ];
        })
        ->values()
        ->all();
@endphp

@extends('layouts.app')
@section('title', 'Sửa đơn hàng')

@section('content')
    <link rel="stylesheet" href="{{ asset('css/ego-order.css') }}?v={{ filemtime(public_path('css/ego-order.css')) }}">
    <link rel="stylesheet" href="{{ asset('css/order-create-pro-v4.css') }}?v={{ filemtime(public_path('css/order-create-pro-v4.css')) }}">

    @once
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/css/tom-select.bootstrap5.min.css">
        <script src="https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/js/tom-select.complete.min.js"></script>
    @endonce

    <div class="container-fluid ego-order oc-shell">
        <header class="oc-header">
            <div class="oc-header-copy">
                <span class="oc-eyebrow">Chỉnh sửa đơn hàng</span>
                <h1>Sửa đơn hàng</h1>
                <p>
                    Cập nhật thông tin, sản phẩm và kho xuất của
                    {{ $order->order_code ?? ('đơn #'.$order->id) }}.
                </p>
            </div>

            <div class="oc-header-actions">
                <a href="{{ route('orders.index') }}" class="oc-btn oc-btn-secondary">
                    <i class="bi bi-arrow-left"></i>
                    Quay lại
                </a>

                <button
                    type="submit"
                    form="orderForm"
                    name="action"
                    value="submit"
                    class="oc-btn oc-btn-primary"
                    data-submit-button
                >
                    <i class="bi bi-save2"></i>
                    <span>Lưu đơn</span>
                </button>
            </div>
        </header>

        @if(session('success'))
            <div class="oc-alert oc-alert-success">
                {{ session('success') }}
            </div>
        @endif

        @if(session('error'))
            <div class="oc-alert oc-alert-danger">
                {{ session('error') }}
            </div>
        @endif

        @if($errors->any())
            <div class="oc-alert oc-alert-danger">
                <strong>Vui lòng kiểm tra lại:</strong>
                <ul class="mb-0 mt-2">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form
            action="{{ route('orders.update', $order->id) }}"
            method="POST"
            id="orderForm"
            novalidate
            data-order-form
        >
            @csrf
            @method('PUT')

            <div class="oc-layout">
                <main class="oc-main">
                    @include('orders.partials.order-info', [
                        'customers' => $customers ?? [],
                        'priceTiers' => $priceTiers ?? [],
                        'order' => $order,
                        'selectedCustomer' => $selectedCustomer ?? null,
                        'mode' => 'edit',
                    ])

                    @include('orders.partials.product-table', [
                        'mode' => 'edit',
                        'order' => $order,
                        'companies' => $companies ?? [],
                        'warehouses' => $warehouses ?? [],
                        'priceTiers' => $priceTiers ?? [],
                    ])
                </main>

                <aside class="oc-side">
                    @include('orders.partials.order-summary', [
                        'mode' => 'edit',
                        'order' => $order,
                    ])
                </aside>
            </div>
        </form>
    </div>
@endsection

@section('scripts')
    <script>
        window.customerTypeId = {{ (int)($order->lead?->customer?->customer_type_id ?? 0) }};
        window.allPriceTiers = @json($orderEditPriceTiersJs);
    </script>

    <script src="{{ asset('js/order-form.js') }}?v={{ filemtime(public_path('js/order-form.js')) }}"></script>
@endsection
