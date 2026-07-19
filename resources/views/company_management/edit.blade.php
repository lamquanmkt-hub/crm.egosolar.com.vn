@extends('layouts.app')

@section('content')
<div class="container-fluid py-4">
    <div class="d-flex align-items-center justify-content-between mb-3">
        <div>
            <h3 class="mb-1 fw-bold">SỬA CÔNG TY</h3>
            <div class="text-muted">{{ $company->name }}</div>
        </div>

        <a href="{{ route('company-management.index') }}" class="btn btn-light">
            Quay lại
        </a>
    </div>

    @include('company_management.form', [
        'company' => $company,
        'action' => route('company-management.update', $company),
        'method' => 'PUT',
        'buttonText' => 'Lưu thông tin'
    ])
</div>
@endsection