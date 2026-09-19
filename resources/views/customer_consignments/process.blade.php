@extends('layouts.app')

@section('title', 'Quy trình ký gửi hàng hóa')

@section('styles')
<link rel="stylesheet"
      href="{{ asset('css/customer-consignments.css') }}?v={{ @filemtime(public_path('css/customer-consignments.css')) ?: time() }}">
<style>
.cc-process-image-card {
    overflow: hidden;
    background: #fff;
    border: 1px solid #dce8f1;
    border-radius: 20px;
    box-shadow: 0 12px 30px rgba(28, 72, 110, .08);
}
.cc-process-image-card img {
    display: block;
    width: 100%;
    height: auto;
}
.cc-process-note {
    margin-top: 16px;
    padding: 16px 18px;
    color: #4e687b;
    background: #f6fbfe;
    border: 1px solid #d9eaf4;
    border-radius: 15px;
    line-height: 1.65;
}
</style>
@endsection

@section('content')
<div class="cc-page">
    <div class="cc-wrap">
        <div class="cc-head">
            <div>
                <div class="cc-eyebrow">Ký gửi hàng hóa</div>
                <h1 class="cc-title">Quy trình ký gửi</h1>
                <div class="cc-sub">
                    Sales tạo đơn và chọn số lượng → Admin/Giám đốc duyệt → Kho mới được xuất hàng.
                </div>
            </div>

            <div class="cc-actions">
                <a href="{{ route('customer-consignments.index') }}" class="cc-btn">
                    <i class="bi bi-arrow-left"></i> Danh sách
                </a>

                @can('consignments.create')
                    <a href="{{ route('customer-consignments.create') }}" class="cc-btn cc-btn-primary">
                        <i class="bi bi-plus-lg"></i> Tạo đơn ký gửi
                    </a>
                @endcan
            </div>
        </div>

        <div class="cc-process-image-card">
            <img src="{{ asset('images/consignment-process-flow.png') }}"
                 alt="Quy trình ký gửi hàng hóa">
        </div>

        <div class="cc-process-note">
            <strong>Nguyên tắc bắt buộc:</strong>
            Sales chọn khách hàng, sản phẩm, kho và số lượng ký gửi.
            Đơn phải được Admin/Giám đốc phê duyệt thì Kho mới được chọn lô, serial và xác nhận xuất hàng.
            Hàng ký gửi không ghi nhận doanh thu bán hàng.
        </div>
    </div>
</div>
@endsection
