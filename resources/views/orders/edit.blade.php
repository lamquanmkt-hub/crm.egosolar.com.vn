
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
@section('title', 'Sửa đơn hàng')

@section('content')
<link rel="stylesheet" href="{{ asset('css/ego-order.css') }}?v={{ filemtime(public_path('css/ego-order.css')) }}">

<div class="container-fluid px-4 ego-order">
  <div class="ego-topbar">
    <div class="ego-topbar__left">
      <div class="ego-topbar__icon"><i class="bi bi-receipt-cutoff"></i></div>
      <div>
        <div class="ego-topbar__title">SỬA ĐƠN HÀNG</div>
        <div class="ego-topbar__sub">Cập nhật thông tin • Chọn sản phẩm theo kho • Kiểm tra hoá đơn • Lưu</div>
      </div>
    </div>

    <div class="ego-topbar__right">
      <a href="{{ route('orders.index') }}" class="btn btn-ghost">
        <i class="bi bi-arrow-left"></i> Quay lại
      </a>

      <button type="submit" form="orderForm" name="action" value="submit" class="btn btn-ego">
        <i class="bi bi-check2-circle"></i> Lưu đơn
      </button>
    </div>
  </div>

  {{-- Alerts --}}
  @if(session('success'))
    <div class="alert alert-success ego-alert">{{ session('success') }}</div>
  @endif
  @if(session('error'))
    <div class="alert alert-danger ego-alert">{{ session('error') }}</div>
  @endif
  @if($errors->any())
    <div class="alert alert-danger ego-alert">
      <div class="fw-bold mb-2">Có lỗi xảy ra:</div>
      <ul class="mb-0">
        @foreach($errors->all() as $e) <li>{{ $e }}</li> @endforeach
      </ul>
    </div>
  @endif

  <form action="{{ route('orders.update', $order->id) }}" method="POST" id="orderForm">
    @csrf
    @method('PUT')

    <div class="ego-layout">
      <div class="ego-main">
        {{-- Thông tin đơn --}}
        @include('orders.partials.order-info', [
          'customers' => $customers ?? [],
          'priceTiers' => $priceTiers ?? [],
          'order' => $order,
          'selectedCustomer' => $selectedCustomer ?? null,
          'mode' => 'edit',
        ])

        {{-- Bảng sản phẩm --}}
        @include('orders.partials.product-table', [
          'mode' => 'edit',
          'order' => $order,
          'companies' => $companies ?? [],
          'warehouses' => $warehouses ?? [],
          'priceTiers' => $priceTiers ?? [],
        ])
      </div>

      <div class="ego-side">
        @include('orders.partials.order-summary', [
          'mode' => 'edit',
          'order' => $order,
        ])
      </div>
    </div>
  </form>
</div>
@endsection

@section('scripts')
<script>
  window.rowIndex = {{ isset($order) && isset($order->items) ? $order->items->count() : 1 }};
  window.customerTypeId = {{ (int)($order->lead?->customer?->customer_type_id ?? 0) }};
  @php
    $orderEditPriceTiersJs = collect($priceTiers ?? [])
        ->map(fn($t) => ['id' => (int) $t->id, 'code' => (string) $t->code, 'name' => (string) $t->name])
        ->values()
        ->all();
  @endphp
  window.allPriceTiers = @json($orderEditPriceTiersJs);
</script>
<script src="{{ asset('js/order-form.js') }}?v={{ filemtime(public_path('js/order-form.js')) }}"></script>

<script id="order-edit-keep-original-price">
(function(){
  function toNumber(value){
    value = (value || '').toString().trim();
    if (!value) return 0;
    value = value.replace(/\s/g, '').replace(/\./g, '').replace(/,/g, '.');
    var n = parseFloat(value);
    return isNaN(n) ? 0 : n;
  }

  function formatVND(value){
    return new Intl.NumberFormat('vi-VN').format(Math.round(value || 0));
  }

  function restoreEditPrices(){
    document.querySelectorAll('#productTableBody tr.product-row').forEach(function(row){
      var originalPrice = toNumber(row.dataset.editUnitPrice);
      var originalLineTotal = toNumber(row.dataset.editLineTotal);

      var priceInput = row.querySelector('.unit-price');
      var lineInput = row.querySelector('.line-total');
      var qtyInput = row.querySelector('.quantity');
      var discountPercentInput = row.querySelector('.discount-percent');
      var discountAmountInput = row.querySelector('.discount-per-unit');
      var productSelect = row.querySelector('.product-select');

      if (!priceInput) return;

      var currentPrice = toNumber(priceInput.value);

      // Chỉ khôi phục khi bị rỗng/0. Không ghi đè nếu người dùng đã sửa giá hợp lệ.
      if (originalPrice > 0 && currentPrice <= 0) {
        priceInput.value = Math.round(originalPrice);
        currentPrice = originalPrice;
      }

      if (productSelect && productSelect.selectedOptions && productSelect.selectedOptions[0] && originalPrice > 0) {
        productSelect.selectedOptions[0].dataset.price = Math.round(originalPrice);
        productSelect.selectedOptions[0].dataset.priceAgent = Math.round(originalPrice);
        productSelect.selectedOptions[0].dataset.priceRetail = Math.round(originalPrice);
        productSelect.selectedOptions[0].dataset.existingPrice = Math.round(originalPrice);
      }

      if (lineInput) {
        var currentLine = toNumber(lineInput.value);

        if (originalLineTotal > 0 && currentLine <= 0) {
          lineInput.value = formatVND(originalLineTotal);
          return;
        }

        if (currentPrice > 0 && currentLine <= 0) {
          var qty = Math.max(1, toNumber(qtyInput ? qtyInput.value : 1));
          var discountPercent = toNumber(discountPercentInput ? discountPercentInput.value : 0);
          var discountAmount = toNumber(discountAmountInput ? discountAmountInput.value : 0);

          var subtotal = qty * currentPrice;
          var discount = discountAmount > 0 ? discountAmount * qty : subtotal * discountPercent / 100;
          var total = Math.max(subtotal - discount, 0);

          lineInput.value = formatVND(total);
        }
      }
    });

    if (typeof window.updateSummary === 'function') {
      window.updateSummary();
    }

    if (typeof window.calculateOrderSummary === 'function') {
      window.calculateOrderSummary();
    }
  }

  document.addEventListener('DOMContentLoaded', function(){
    restoreEditPrices();
    setTimeout(restoreEditPrices, 300);
    setTimeout(restoreEditPrices, 900);
  });

  document.addEventListener('change', function(e){
    if (
      e.target.matches('.warehouse-select') ||
      e.target.matches('.product-select') ||
      e.target.matches('.price-tier-select')
    ) {
      setTimeout(restoreEditPrices, 250);
    }
  });
})();
</script>

@endsection


