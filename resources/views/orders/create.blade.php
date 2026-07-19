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

    $orderCreatePriceTiersJs = collect($priceTiers ?? [])
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
@section('title', 'Tạo đơn hàng mới')

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
                <span class="oc-eyebrow">Đơn hàng mới</span>
                <h1>Tạo đơn hàng</h1>
                <p>Chọn khách hàng và sản phẩm để tạo đơn.</p>
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
                    value="save_draft"
                    class="oc-btn oc-btn-outline"
                    data-submit-button
                >
                    <i class="bi bi-save"></i>
                    <span>Lưu nháp</span>
                </button>
            </div>
        </header>

        @if(session('success'))
            <div class="oc-alert oc-alert-success">{{ session('success') }}</div>
        @endif

        @if(session('error'))
            <div class="oc-alert oc-alert-danger">{{ session('error') }}</div>
        @endif

        @if($errors->any())
            <div class="oc-alert oc-alert-danger">
                <strong>Vui lòng kiểm tra lại dữ liệu.</strong>
                <ul>
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form
            action="{{ route('orders.store') }}"
            method="POST"
            id="orderForm"
            novalidate
            data-order-form
        >
            @csrf

            <div class="oc-layout">
                <main class="oc-main">
                    @include('orders.partials.order-info', [
                        'customers' => $customers ?? [],
                        'priceTiers' => $priceTiers ?? [],
                        'mode' => 'create',
                    ])

                    @include('orders.partials.product-table', [
                        'mode' => 'create',
                        'warehouses' => $warehouses ?? [],
                        'priceTiers' => $priceTiers ?? [],
                    ])
                </main>

                <aside class="oc-side">
                    @include('orders.partials.order-summary', [
                        'mode' => 'create',
                    ])
                </aside>
            </div>
        </form>
    </div>
@endsection

@section('scripts')
    <script>
        window.customerTypeId = null;
        window.allPriceTiers = {!! json_encode(
            $orderCreatePriceTiersJs,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        ) !!};
    </script>
    <script src="{{ asset('js/order-form.js') }}?v={{ filemtime(public_path('js/order-form.js')) }}"></script>
@endsection
