@extends('layouts.app')
@section('title', 'Thêm loại giá')
@section('content')
    <div class="container-fluid px-4">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h1 class="fw-bold text-uppercase text-secondary mb-0">THÊM LOẠI GIÁ</h1>
            <a href="{{ route('price-tiers.index') }}" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left"></i> Quay lại
            </a>
        </div>
        <div class="card shadow-sm">
            <div class="card-body">
                <form method="POST" action="{{ route('price-tiers.store') }}">
                    @include('price_tiers._form', ['tier' => null, 'buttonText' => 'Tạo loại giá'])
                </form>
            </div>
        </div>
    </div>
@endsection
