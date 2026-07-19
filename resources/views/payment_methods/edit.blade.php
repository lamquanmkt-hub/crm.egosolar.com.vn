@extends('layouts.app')
@section('title', 'Chỉnh sửa phương thức thanh toán')
@section('content')
    <div class="container-fluid px-4 mt-4">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card shadow-sm">
                    <div class="card-header bg-warning text-dark">
                        <h5 class="mb-0"><i class="bi bi-pencil-square"></i> Chỉnh sửa phương thức</h5>
                    </div>
                    <div class="card-body">
                        <form action="{{ route('payment-methods.update', $method->id) }}" method="POST">
                            @csrf
                            @method('PUT')

                            {{-- Include Form Partial --}}
                            @include('payment_methods._form', ['method' => $method])

                            <div class="d-flex justify-content-end gap-2 mt-4">
                                <a href="{{ route('payment-methods.index') }}" class="btn btn-secondary">Quay lại</a>
                                <button type="submit" class="btn btn-primary"><i class="bi bi-save"></i> Cập nhật</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection