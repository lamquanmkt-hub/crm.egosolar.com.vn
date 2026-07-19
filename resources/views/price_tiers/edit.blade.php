@extends('layouts.app')
@section('title', 'Cập nhật loại giá')
@section('content')
    <div class="container-fluid px-4">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h1 class="fw-bold text-uppercase text-secondary mb-0">CẬP NHẬT LOẠI GIÁ</h1>
            <a href="{{ route('price-tiers.index') }}" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left"></i> Quay lại
            </a>
        </div>
        <div class="card shadow-sm">
            <div class="card-body">
                <form method="POST" action="{{ route('price-tiers.update', $tier) }}">
                    @method('PUT')
                    @include('price_tiers._form', ['tier' => $tier, 'buttonText' => 'Lưu thay đổi'])
                </form>
            </div>
        </div>
    </div>
@endsection
