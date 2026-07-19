@extends('layouts.app')

@section('content')
<div class="container-fluid py-4">
    <div class="d-flex align-items-center justify-content-between mb-3">
        <div>
            <h4 class="mb-1">Thông tin công ty</h4>
            <div class="text-muted">Quản lý thông tin dùng để in PDF đơn hàng.</div>
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    <div class="card shadow-sm border-0">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th style="width:70px;">ID</th>
                            <th>Tên công ty</th>
                            <th>Mã</th>
                            <th>MST</th>
                            <th>Email</th>
                            <th>Ngân hàng</th>
                            <th style="width:120px;" class="text-end">Hành động</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($companies as $company)
                            <tr>
                                <td>{{ $company->id }}</td>
                                <td>
                                    <div class="fw-semibold">{{ $company->name }}</div>
                                    @if(!$company->is_active)
                                        <span class="badge bg-secondary">Đã tắt</span>
                                    @endif
                                </td>
                                <td><span class="badge bg-info text-dark">{{ $company->code }}</span></td>
                                <td>{{ $company->tax_code ?: '---' }}</td>
                                <td>{{ $company->email ?: '---' }}</td>
                                <td>
                                    <div>{{ $company->bank_account ?: '---' }}</div>
                                    <small class="text-muted">{{ $company->bank_name ?: '' }}</small>
                                </td>
                                <td class="text-end">
                                    <a href="{{ route('companies.edit', $company) }}" class="btn btn-sm btn-warning">
                                        Sửa
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center text-muted py-4">
                                    Chưa có công ty.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        @if($companies->hasPages())
            <div class="card-footer bg-white">
                {{ $companies->links() }}
            </div>
        @endif
    </div>
</div>
@endsection