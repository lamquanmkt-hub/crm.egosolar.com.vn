@extends('layouts.app')

@section('title', 'Thêm Brand')

@section('content')
    <div class="container-fluid px-4">

        <div class="d-flex justify-content-between align-items-center mb-3">
            <h1 class="fw-bold text-uppercase text-secondary mb-0">THÊM BRAND</h1>
            <a href="{{ route('brands.index') }}" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left"></i> Quay lại
            </a>
        </div>

        <div class="card shadow-sm">
            <div class="card-body">
                <form method="POST" action="{{ route('brands.store') }}">
                    @include('brands._form', ['brand' => null, 'buttonText' => 'Tạo brand'])
                </form>
            </div>
        </div>

    </div>
@endsection
