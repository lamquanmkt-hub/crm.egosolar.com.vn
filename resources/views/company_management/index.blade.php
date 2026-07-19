@extends('layouts.app')

@section('content')
<div class="container-fluid py-4">
    <div class="d-flex align-items-center justify-content-between mb-3">
        <div>
            <h3 class="mb-1 fw-bold">QUẢN LÝ CÔNG TY</h3>
            <div class="text-muted">Thêm, sửa, xóa công ty dùng cho kho hàng và PDF đơn hàng.</div>
        </div>

        <a href="{{ route('company-management.create') }}" class="btn btn-primary">
            + Thêm công ty
        </a>
    </div>

    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    @if($errors->any())
        <div class="alert alert-danger">
            <div class="fw-semibold">Có lỗi xảy ra:</div>
            <ul class="mb-0">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
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
                            <th>Thanh toán</th>
                            <th>Trạng thái</th>
                            <th style="width:160px;" class="text-end">Hành động</th>
                        </tr>
                    </thead>

                    <tbody>
                        @forelse($companies as $company)
                            <tr>
                                <td>{{ $company->id }}</td>

                                <td>
                                    <div class="fw-semibold">{{ $company->name }}</div>
                                    <small class="text-muted">{{ $company->address ?: 'Chưa có địa chỉ' }}</small>
                                </td>

                                <td>
                                    <span class="badge bg-info text-dark">{{ $company->code }}</span>
                                </td>

                                <td>{{ $company->tax_code ?: '---' }}</td>
                                <td>{{ $company->email ?: '---' }}</td>

                                <td>
                                    <div><b>STK:</b> {{ $company->bank_account ?: '---' }}</div>
                                    <div><b>NH:</b> {{ $company->bank_name ?: '---' }}</div>
                                    <small class="text-muted">{{ $company->bank_holder ?: '' }}</small>
                                </td>

                                <td>
                                    @if($company->is_active)
                                        <span class="badge bg-success">Hoạt động</span>
                                    @else
                                        <span class="badge bg-secondary">Không hoạt động</span>
                                    @endif
                                </td>

                                <td class="text-end">
                                    <a href="{{ route('company-management.edit', $company) }}" class="btn btn-sm btn-warning">
                                        Sửa
                                    </a>

                                    <form method="POST"
                                          action="{{ route('company-management.destroy', $company) }}"
                                          class="d-inline"
                                          onsubmit="return confirm('Bạn chắc chắn muốn xóa công ty này? Nếu công ty đã có đơn/kho liên quan, hệ thống sẽ chỉ tắt hoạt động.');">
                                        @csrf
                                        @method('DELETE')

                                        <button type="submit" class="btn btn-sm btn-danger">
                                            Xóa
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="text-center text-muted py-4">
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