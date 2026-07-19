@extends('layouts.app')
@section('title', 'Thêm phương thức thanh toán')
@section('content')
    <div class="container-fluid px-4 mt-4">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card shadow-sm">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0"><i class="bi bi-plus-circle"></i> Thêm phương thức mới</h5>
                    </div>
                    <div class="card-body">
                        <form action="{{ route('payment-methods.store') }}" method="POST">
                            @csrf

                            {{-- Include Form Partial --}}
                            @include('payment_methods._form')

                            <div class="d-flex justify-content-end gap-2 mt-4">
                                <a href="{{ route('payment-methods.index') }}" class="btn btn-secondary">Quay lại</a>
                                <button type="submit" class="btn btn-primary"><i class="bi bi-save"></i> Lưu lại</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection