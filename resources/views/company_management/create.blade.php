@extends('layouts.app')

@section('content')
<div class="container-fluid py-4">
    <div class="d-flex align-items-center justify-content-between mb-3">
        <div>
            <h3 class="mb-1 fw-bold">THÊM CÔNG TY</h3>
            <div class="text-muted">Tạo công ty mới để gắn với kho và in PDF đơn hàng.</div>
        </div>

        <a href="{{ route('company-management.index') }}" class="btn btn-light">
            Quay lại
        </a>
    </div>

    @include('company_management.form', [
        'company' => $company,
        'action' => route('company-management.store'),
        'method' => 'POST',
        'buttonText' => 'Thêm công ty'
    ])
</div>
@endsection