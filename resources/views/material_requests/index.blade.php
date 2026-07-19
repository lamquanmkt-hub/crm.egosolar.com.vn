@extends('layouts.app')

@section('content')
@php
    use App\Enums\MaterialRequestStatus;

    $statusOptions = [
        MaterialRequestStatus::DRAFT->value => 'Nháp',
        MaterialRequestStatus::SUBMITTED->value => 'Chờ admin duyệt',
        MaterialRequestStatus::ADMIN_APPROVED->value => 'Chờ kho duyệt',
        MaterialRequestStatus::EXPORTED->value => 'Đã xuất kho',
    ];

    $statusBadgeClass = function ($status) {
        return match ((string) $status) {
            MaterialRequestStatus::DRAFT->value => 'bg-secondary',
            MaterialRequestStatus::SUBMITTED->value => 'bg-warning text-dark',
            MaterialRequestStatus::ADMIN_APPROVED->value => 'bg-primary',
            MaterialRequestStatus::EXPORTED->value => 'bg-success',
            default => 'bg-light text-dark border',
        };
    };

    $statusLabel = function ($status) use ($statusOptions) {
        return $statusOptions[(string) $status] ?? (string) $status;
    };

    $fmtMoney = function ($amount) {
        return number_format((float)($amount ?? 0), 0, ',', '.') . ' đ';
    };
@endphp

<div class="container-fluid px-4 py-3">

    {{-- HEADER --}}
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
        <div>
            <div class="d-flex align-items-center gap-2 mb-1">
                <span class="page-icon">
                    <i class="bi bi-clipboard-check"></i>
                </span>

                <div>
                    <h4 class="fw-bold mb-0">Đơn vật tư</h4>
                    <div class="text-muted small">
                        Kỹ thuật tạo đơn • Admin duyệt • Kho duyệt / Xuất kho
                    </div>
                </div>
            </div>
        </div>

        <a href="{{ route('material-requests.create') }}" class="btn btn-primary btn-create">
            <i class="bi bi-plus-circle"></i> Tạo đơn vật tư
        </a>
    </div>

    {{-- ALERT --}}
    @if(session('success'))
        <div class="alert alert-success border-0 shadow-sm" style="border-radius:16px;">
            <i class="bi bi-check-circle"></i> {{ session('success') }}
        </div>
    @endif

    @if(session('error'))
        <div class="alert alert-danger border-0 shadow-sm" style="border-radius:16px;">
            <i class="bi bi-exclamation-triangle"></i> {{ session('error') }}
        </div>
    @endif

    @if ($errors->any())
        <div class="alert alert-danger border-0 shadow-sm" style="border-radius:16px;">
            <div class="fw-semibold mb-1">
                <i class="bi bi-exclamation-triangle"></i> Vui lòng kiểm tra lại:
            </div>

            <ul class="mb-0">
                @foreach ($errors->all() as $e)
                    <li>{{ $e }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    {{-- FILTER --}}
    <div class="card border-0 shadow-sm mb-3 filter-card">
        <div class="card-body">
            <form method="GET" action="{{ route('material-requests.index') }}">
                <div class="row g-2 align-items-end">
                    <div class="col-lg-4 col-md-6">
                        <label class="form-label small text-muted">Tìm nhanh</label>
                        <div class="input-group">
                            <span class="input-group-text bg-white">
                                <i class="bi bi-search"></i>
                            </span>

                            <input type="text"
                                   name="q"
                                   value="{{ request('q') }}"
                                   class="form-control"
                                   placeholder="Tìm theo mã đơn, ghi chú, công trình...">
                        </div>
                    </div>

                    <div class="col-lg-3 col-md-6">
                        <label class="form-label small text-muted">Trạng thái</label>
                        <select name="status" class="form-select">
                            <option value="">-- Tất cả --</option>
                            @foreach($statusOptions as $value => $label)
                                <option value="{{ $value }}" {{ (string)request('status') === (string)$value ? 'selected' : '' }}>
                                    {{ $label }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-lg-3 col-md-6">
                        <label class="form-label small text-muted">Công trình (ID)</label>
                        <input type="number"
                               name="site_id"
                               value="{{ request('site_id') }}"
                               class="form-control"
                               placeholder="VD: 10">
                    </div>

                    <div class="col-lg-2 col-md-6">
                        <div class="d-flex gap-2">
                            <button class="btn btn-outline-primary flex-fill">
                                <i class="bi bi-funnel"></i> Lọc
                            </button>

                            <a href="{{ route('material-requests.index') }}" class="btn btn-outline-secondary">
                                <i class="bi bi-arrow-clockwise"></i>
                            </a>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>

    {{-- TABLE --}}
    <div class="card border-0 shadow-sm list-card">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th style="width:70px;">#</th>
                        <th style="min-width:300px;">Công trình</th>
                        <th style="width:190px;">Trạng thái</th>
                        <th style="min-width:220px;">Ghi chú</th>
                        <th style="width:170px;">Ngày tạo</th>
                        <th style="min-width:330px;">Thao tác</th>
                    </tr>
                </thead>

                <tbody>
                    @forelse($requests as $mr)
                        @php
                            $status = (string)($mr->status ?? '');
                            $isDraft = $status === MaterialRequestStatus::DRAFT->value;
                            $isSubmitted = $status === MaterialRequestStatus::SUBMITTED->value;
                            $isAdminApproved = $status === MaterialRequestStatus::ADMIN_APPROVED->value;
                            $isExported = $status === MaterialRequestStatus::EXPORTED->value;
                        @endphp

                        <tr>
                            <td>
                                <a href="{{ route('material-requests.show', $mr->id) }}"
                                   class="fw-bold text-decoration-none">
                                    {{ $mr->id }}
                                </a>
                            </td>

                            <td>
                                <div class="fw-bold">
                                    {{ $mr->site->name ?? '—' }}
                                </div>

                                <div class="small text-muted">
                                    Mã công trình: {{ $mr->site_id ?? '—' }}
                                </div>

                                @if(isset($mr->total_cost))
                                    <div class="small text-success fw-semibold mt-1">
                                        Giá vốn: {{ $fmtMoney($mr->total_cost) }}
                                    </div>
                                @endif
                            </td>

                            <td>
                                <span class="badge rounded-pill px-3 py-2 {{ $statusBadgeClass($status) }}">
                                    {{ $statusLabel($status) }}
                                </span>
                            </td>

                            <td>
                                @if(!empty($mr->note))
                                    <span>{{ $mr->note }}</span>
                                @else
                                    <span class="text-muted">—</span>
                                @endif
                            </td>

                            <td>
                                @if($mr->created_at)
                                    <div>{{ $mr->created_at->format('d/m/Y H:i') }}</div>
                                    <div class="small text-muted">
                                        {{ $mr->created_at->diffForHumans() }}
                                    </div>
                                @else
                                    <span class="text-muted">—</span>
                                @endif
                            </td>

                            <td>
                                <div class="d-flex flex-wrap gap-1 align-items-center">

                                    {{-- XEM --}}
                                    <a href="{{ route('material-requests.show', $mr->id) }}"
                                       class="btn btn-sm btn-outline-secondary">
                                        <i class="bi bi-eye"></i> Xem
                                    </a>

                                    {{-- EGO_MR_INDEX_EDIT_BUTTON --}}
                                    @php
    /* EGO_MR_INDEX_RUNTIME_CAN_EDIT_FIX */
    $mrUserForEdit = auth()->user();

    $canEditMaterialRequest = $mrUserForEdit && method_exists($mrUserForEdit, 'hasRole') && (
        ($mrUserForEdit->hasRole('admin') && in_array($status, [
            \App\Enums\MaterialRequestStatus::DRAFT->value,
            \App\Enums\MaterialRequestStatus::SUBMITTED->value,
            \App\Enums\MaterialRequestStatus::ADMIN_APPROVED->value,
        ], true))
        || ($mrUserForEdit->hasRole('warehouse') && in_array($status, [
            \App\Enums\MaterialRequestStatus::SUBMITTED->value,
            \App\Enums\MaterialRequestStatus::ADMIN_APPROVED->value,
        ], true))
        || ($mrUserForEdit->hasRole('ky_thuat') && $status === \App\Enums\MaterialRequestStatus::DRAFT->value)
    );
@endphp
@if($canEditMaterialRequest && !$isDraft)
                                        <a href="{{ route('material-requests.edit', $mr->id) }}"
                                           class="btn btn-sm btn-outline-warning">
                                            <i class="bi bi-pencil-square"></i> Sửa
                                        </a>
                                    @endif

                                    {{-- NHÁP: Sửa / Gửi admin / Xóa --}}
                                    @if($isDraft)
                                        <a href="{{ route('material-requests.edit', $mr->id) }}"
                                           class="btn btn-sm btn-outline-warning">
                                            <i class="bi bi-pencil-square"></i> Sửa
                                        </a>

                                        <form method="POST"
                                              action="{{ route('material-requests.submit', $mr->id) }}"
                                              class="d-inline"
                                              onsubmit="return confirm('Gửi đơn vật tư #{{ $mr->id }} cho admin duyệt?')">
                                            @csrf

                                            <button type="submit" class="btn btn-sm btn-primary">
                                                <i class="bi bi-send-check"></i> Gửi admin duyệt
                                            </button>
                                        </form>

                                        <form method="POST"
                                              action="{{ route('material-requests.destroy', $mr->id) }}"
                                              class="d-inline"
                                              onsubmit="return confirm('Xóa đơn vật tư nháp #{{ $mr->id }}?')">
                                            @csrf
                                            @method('DELETE')

                                            <button type="submit" class="btn btn-sm btn-outline-danger">
                                                <i class="bi bi-trash"></i> Xóa
                                            </button>
                                        </form>
                                    @endif

                                    {{-- CHỜ ADMIN DUYỆT --}}
                                    @if($isSubmitted)
                                        @if(auth()->user() && method_exists(auth()->user(), 'hasRole') && auth()->user()->hasRole('admin'))
                                            <form method="POST"
                                                  action="{{ route('material-requests.admin-approve', $mr->id) }}"
                                                  class="d-inline"
                                                  onsubmit="return confirm('Admin duyệt đơn vật tư #{{ $mr->id }}?')">
                                                @csrf

                                                <button type="submit" class="btn btn-sm btn-warning">
                                                    <i class="bi bi-shield-check"></i> Admin duyệt
                                                </button>
                                            </form>

                                            <form method="POST"
                                                  action="{{ route('material-requests.destroy', $mr->id) }}"
                                                  class="d-inline"
                                                  onsubmit="return confirm('Xóa đơn vật tư chờ admin duyệt #{{ $mr->id }}?')">
                                                @csrf
                                                @method('DELETE')

                                                <button type="submit" class="btn btn-sm btn-outline-danger">
                                                    <i class="bi bi-trash"></i> Xóa
                                                </button>
                                            </form>
                                        @endif
                                    @endif

                                    {{-- CHỜ KHO DUYỆT --}}
                                    @if($isAdminApproved)
                                        <a href="{{ route('material-requests.show', $mr->id) }}"
                                           class="btn btn-sm btn-success">
                                            <i class="bi bi-box-arrow-up"></i> Kho duyệt / xuất
                                        </a>
                                    @endif

                                    {{-- ĐÃ XUẤT --}}
                                    @if($isExported)
                                        <span class="badge bg-success-subtle text-success border">
                                            <i class="bi bi-check2-circle"></i> Đã hoàn tất
                                        </span>
                                    @endif

                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center py-5">
                                <div class="empty-box mx-auto">
                                    <div class="empty-icon mb-2">
                                        <i class="bi bi-inbox"></i>
                                    </div>

                                    <div class="fw-bold">Chưa có đơn vật tư</div>
                                    <div class="text-muted small mb-3">
                                        Bấm “Tạo đơn vật tư” để tạo đơn đầu tiên.
                                    </div>

                                    <a href="{{ route('material-requests.create') }}" class="btn btn-primary">
                                        <i class="bi bi-plus-circle"></i> Tạo đơn vật tư
                                    </a>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if(method_exists($requests, 'links'))
            <div class="card-footer bg-white border-0 py-3">
                {{ $requests->links() }}
            </div>
        @endif
    </div>
</div>

<style>
    .page-icon{
        width:42px;
        height:42px;
        border-radius:14px;
        display:inline-flex;
        align-items:center;
        justify-content:center;
        background:rgba(37,99,235,.12);
        color:#2563eb;
        border:1px solid rgba(37,99,235,.18);
    }

    .btn-create{
        border-radius:14px;
        font-weight:700;
        padding:10px 16px;
        box-shadow:0 8px 18px rgba(37,99,235,.20);
    }

    .filter-card,
    .list-card{
        border-radius:18px;
        overflow:hidden;
    }

    .form-control,
    .form-select,
    .input-group-text{
        border-radius:12px;
    }

    .input-group .input-group-text{
        border-top-right-radius:0;
        border-bottom-right-radius:0;
    }

    .input-group .form-control{
        border-top-left-radius:0;
        border-bottom-left-radius:0;
    }

    .table thead th{
        font-size:.85rem;
        color:#334155;
        white-space:nowrap;
        vertical-align:middle;
        border-bottom:1px solid rgba(0,0,0,.08);
    }

    .table tbody td{
        vertical-align:middle;
        border-top:1px solid rgba(0,0,0,.045);
    }

    .table-hover tbody tr:hover{
        background:rgba(37,99,235,.035);
    }

    .btn-sm{
        border-radius:10px;
        font-weight:600;
    }

    .empty-box{
        max-width:360px;
    }

    .empty-icon{
        width:52px;
        height:52px;
        border-radius:18px;
        margin:0 auto;
        display:flex;
        align-items:center;
        justify-content:center;
        background:rgba(15,23,42,.06);
        color:#64748b;
        font-size:24px;
    }

    @media (max-width: 767.98px){
        .btn-create{
            width:100%;
        }
    }
</style>
@endsection

{{-- EGO_MR_ADMIN_DELETE_COMPLETED_BUTTON_START --}}
@php
    $egoMrUser = auth()->user();
    $egoMrIsAdmin = false;

    if ($egoMrUser) {
        if (method_exists($egoMrUser, 'hasRole')) {
            $egoMrIsAdmin = $egoMrUser->hasRole('admin');
        } elseif (method_exists($egoMrUser, 'hasAnyRole')) {
            $egoMrIsAdmin = $egoMrUser->hasAnyRole(['admin', 'warehouse']);
        } elseif (isset($egoMrUser->role)) {
            $egoMrIsAdmin = (string) $egoMrUser->role === 'admin';
        }
    }
@endphp

@if($egoMrIsAdmin)
<style>
    .ego-mr-delete-completed-btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border: 0;
        border-radius: 8px;
        padding: 6px 10px;
        margin-left: 6px;
        background: #fee2e2;
        color: #991b1b;
        font-size: 12px;
        font-weight: 900;
        cursor: pointer;
        white-space: nowrap;
    }
</style>

<script>
(function () {
    var csrf = @json(csrf_token());

    function rowIsCompleted(row) {
        var text = (row.innerText || '').toLowerCase();

        return text.indexOf('đã hoàn tất') !== -1
            || text.indexOf('hoàn tất') !== -1
            || text.indexOf('đã xuất kho') !== -1
            || text.indexOf('xuat kho') !== -1
            || text.indexOf('exported') !== -1
            || text.indexOf('completed') !== -1;
    }

    function getRequestId(row) {
        var firstCell = row.querySelector('td, th');

        if (!firstCell) {
            return null;
        }

        var text = (firstCell.innerText || '').trim();
        var match = text.match(/\d+/);

        return match ? match[0] : null;
    }

    function addDeleteButton(row) {
        if (!rowIsCompleted(row)) {
            return;
        }

        if (row.querySelector('[data-ego-mr-delete-completed]')) {
            return;
        }

        var id = getRequestId(row);

        if (!id) {
            return;
        }

        var cells = row.querySelectorAll('td');

        if (!cells.length) {
            return;
        }

        var actionCell = cells[cells.length - 1];

        var form = document.createElement('form');
        form.method = 'POST';
        form.action = '/don-vat-tu/' + id + '/xoa';
        form.style.display = 'inline';
        form.setAttribute('data-ego-mr-delete-completed', '1');

        form.innerHTML =
            '<input type="hidden" name="_token" value="' + csrf + '">' +
            '<input type="hidden" name="_method" value="DELETE">' +
            '<button type="submit" class="ego-mr-delete-completed-btn">Xóa</button>';

        form.addEventListener('submit', function (e) {
            if (!confirm('Admin xóa đơn vật tư #' + id + ' đã hoàn tất?')) {
                e.preventDefault();
            }
        });

        actionCell.appendChild(form);
    }

    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('table tbody tr').forEach(addDeleteButton);
    });
})();
</script>
@endif
{{-- EGO_MR_ADMIN_DELETE_COMPLETED_BUTTON_END --}}
