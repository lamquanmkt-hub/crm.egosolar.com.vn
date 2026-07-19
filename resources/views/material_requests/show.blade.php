
@php
    $egoPendingOrdersCount = $egoPendingOrdersCount ?? 0;
    $egoPendingMaterialRequestsCount = $egoPendingMaterialRequestsCount ?? 0;

    try {
        $egoPendingOrdersCount = (int) \Illuminate\Support\Facades\DB::table('crm_order_approvals')
            ->where('status', 'pending')
            ->distinct()
            ->count('order_id');

        $egoPendingMaterialRequestsCount = (int) \Illuminate\Support\Facades\DB::table('material_requests')
            ->whereIn('status', ['SUBMITTED', 'ADMIN_APPROVED'])
            ->count();
    } catch (\Throwable $e) {
        $egoPendingOrdersCount = 0;
        $egoPendingMaterialRequestsCount = 0;
    }
@endphp

@extends('layouts.app')

@section('content')
@php
    use App\Enums\MaterialRequestStatus;

    $status = (string)($mr->status ?? '');

    $isDraft = $status === MaterialRequestStatus::DRAFT->value;
    $isSubmitted = $status === MaterialRequestStatus::SUBMITTED->value;
    $isAdminApproved = $status === MaterialRequestStatus::ADMIN_APPROVED->value;
    $isExported = $status === MaterialRequestStatus::EXPORTED->value;

    /* EGO_MR_SHOW_CAN_EDIT_START */
    $mrUser = auth()->user();
    $canEditMaterialRequest = $mrUser && method_exists($mrUser, 'hasRole') && (
        ($mrUser->hasRole('admin') && in_array($status, [
            MaterialRequestStatus::DRAFT->value,
            MaterialRequestStatus::SUBMITTED->value,
            MaterialRequestStatus::ADMIN_APPROVED->value,
        ], true))
        || ($mrUser->hasRole('warehouse') && in_array($status, [
            MaterialRequestStatus::SUBMITTED->value,
            MaterialRequestStatus::ADMIN_APPROVED->value,
        ], true))
        || ($mrUser->hasRole('ky_thuat') && $status === MaterialRequestStatus::DRAFT->value)
    );

    $editHistories = collect();

    try {
        if (\Illuminate\Support\Facades\Schema::hasTable('material_request_edit_histories')) {
            $editHistories = \Illuminate\Support\Facades\DB::table('material_request_edit_histories')
                ->where('material_request_id', $mr->id)
                ->orderByDesc('id')
                ->get();
        }
    } catch (\Throwable $e) {
        $editHistories = collect();
    }
    /* EGO_MR_SHOW_CAN_EDIT_END */
    $statusLabels = [
        MaterialRequestStatus::DRAFT->value => 'Nháp',
        MaterialRequestStatus::SUBMITTED->value => 'Chờ admin duyệt',
        MaterialRequestStatus::ADMIN_APPROVED->value => 'Chờ kho duyệt',
        MaterialRequestStatus::EXPORTED->value => 'Đã xuất kho',
    ];

    $statusLabel = $statusLabels[$status] ?? $status;

    $statusBadgeClass = match ($status) {
        MaterialRequestStatus::DRAFT->value => 'bg-secondary',
        MaterialRequestStatus::SUBMITTED->value => 'bg-warning text-dark',
        MaterialRequestStatus::ADMIN_APPROVED->value => 'bg-primary',
        MaterialRequestStatus::EXPORTED->value => 'bg-success',
        default => 'bg-light text-dark border',
    };

    $fmtMoney = function ($amount) {
        return number_format((float)($amount ?? 0), 0, ',', '.') . ' đ';
    };

    $fmtQty = function ($qty) {
        $qty = (float)($qty ?? 0);
        if (floor($qty) == $qty) {
            return number_format($qty, 0, ',', '.');
        }

        return number_format($qty, 2, ',', '.');
    };

    $stripPrefix = function ($note) {
        $note = (string) $note;
        $note = preg_replace('/^\[(Thiết bị chính - Trong kho|Vật tư phụ - Trong kho|Thiết bị chính - Ngoài kho|Vật tư phụ - Ngoài kho)\]\s*/u', '', $note);
        return trim($note);
    };

    $detectGroup = function ($item) {
        $note = (string)($item->note ?? '');

        if (!empty($item->product_id)) {
            if (str_contains($note, '[Vật tư phụ - Trong kho]')) {
                return 'Vật tư phụ - Trong kho';
            }

            return 'Thiết bị chính - Trong kho';
        }

        if (str_contains($note, '[Thiết bị chính - Ngoài kho]')) {
            return 'Thiết bị chính - Ngoài kho';
        }

        return 'Vật tư phụ - Ngoài kho';
    };

    $externalName = function ($item) use ($stripPrefix) {
        $note = $stripPrefix((string)($item->note ?? ''));

        $parts = array_values(array_filter(array_map('trim', explode('|', $note)), function ($x) {
            return $x !== '';
        }));

        return $parts[0] ?? 'Vật tư ngoài kho';
    };

    $externalUnitFromNote = function ($item) {
        $note = (string)($item->note ?? '');

        $parts = array_values(array_filter(array_map('trim', explode('|', $note)), function ($x) {
            return $x !== '';
        }));

        foreach ($parts as $part) {
            if (preg_match('/^ĐVT:\s*(.*)$/u', $part, $m)) {
                return trim($m[1] ?? '');
            }
        }

        return '';
    };

    $items = collect($mr->items ?? []);

    $stockItems = $items->filter(function ($item) {
        return !empty($item->product_id);
    })->values();

    $externalItems = $items->filter(function ($item) {
        return empty($item->product_id);
    })->values();

    $totalQty = $items->sum(function ($item) {
        return (float)($item->qty ?? 0);
    });

    $totalCost = $items->sum(function ($item) {
        return (float)($item->line_total ?? 0);
    });

    if ($totalCost <= 0 && isset($mr->total_cost)) {
        $totalCost = (float)$mr->total_cost;
    }

    $hasExternalItems = $externalItems->count() > 0;
    $externalMissingCost = $externalItems->filter(function ($item) {
        return (float)($item->unit_cost ?? 0) <= 0;
    })->count();
@endphp

<div class="container-fluid px-4 py-3 material-show-page">

    {{-- HEADER --}}
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
        <div>
            <div class="d-flex align-items-center gap-2 mb-1">
                <span class="page-icon">
                    <i class="bi bi-clipboard-check"></i>
                </span>

                <div>
                    <h4 class="fw-bold mb-0">Chi tiết đơn vật tư #{{ $mr->id }}</h4>
                    <div class="text-muted small">
                        Công trình: <strong>{{ $mr->site->name ?? '—' }}</strong>
                    </div>
                </div>
            </div>
        </div>

        <div class="d-flex flex-wrap gap-2">
            <a href="{{ route('material-requests.index') }}" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left"></i> Quay lại
            </a>

            <a href="{{ route('material-requests.export.excel', $mr->id) }}"
               class="btn btn-success btn-export-material-request">
                <i class="bi bi-file-earmark-excel"></i> Xuất Excel
            </a>

            @if($canEditMaterialRequest)
                <a href="{{ route('material-requests.edit', $mr->id) }}" class="btn btn-outline-warning">
                    <i class="bi bi-pencil-square"></i> Sửa
                </a>
            @endif
        </div>
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

    <div class="row g-3">

        {{-- LEFT --}}
        <div class="col-lg-8">

            {{-- THÔNG TIN --}}
            <div class="card border-0 shadow-sm info-card">
                <div class="card-header bg-white border-0 py-3">
                    <div class="d-flex align-items-center gap-2">
                        <span class="section-icon">
                            <i class="bi bi-info-circle"></i>
                        </span>

                        <div>
                            <div class="fw-bold">Thông tin đơn</div>
                            <div class="text-muted small">Thông tin công trình và trạng thái xử lý.</div>
                        </div>
                    </div>
                </div>

                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <div class="text-muted small">Công trình</div>
                            <div class="fw-bold">{{ $mr->site->name ?? '—' }}</div>
                            <div class="small text-muted">Mã công trình: {{ $mr->site_id ?? '—' }}</div>
                        </div>

                        <div class="col-md-6">
                            <div class="text-muted small">Trạng thái</div>
                            <span class="badge rounded-pill px-3 py-2 {{ $statusBadgeClass }}">
                                {{ $statusLabel }}
                            </span>
                            <div class="small text-muted mt-1">Raw: {{ $status ?: '—' }}</div>
                        </div>

                        <div class="col-md-6">
                            <div class="text-muted small">Ngày tạo</div>
                            <div class="fw-semibold">
                                {{ $mr->created_at ? $mr->created_at->format('d/m/Y H:i') : '—' }}
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="text-muted small">Người tạo</div>
                            <div class="fw-semibold">
                                {{ $mr->creator->name ?? '—' }}
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- VẬT TƯ TRONG KHO --}}
            <div class="card border-0 shadow-sm info-card mt-3">
                <div class="card-header bg-white border-0 py-3 d-flex flex-wrap justify-content-between align-items-center gap-2">
                    <div class="d-flex align-items-center gap-2">
                        <span class="section-icon stock">
                            <i class="bi bi-box-seam"></i>
                        </span>

                        <div>
                            <div class="fw-bold">Vật tư / thiết bị trong kho</div>
                            <div class="text-muted small">Giá vốn tự lấy từ kho/catalog, không nhập tay ở bước kho duyệt.</div>
                        </div>
                    </div>

                    <span class="badge bg-light text-dark border">
                        {{ $stockItems->count() }} dòng
                    </span>
                </div>

                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th style="width:60px" class="text-center">#</th>
                                <th style="min-width:260px">Vật tư</th>
                                <th style="width:100px">Mã SP</th>
                                <th style="width:120px" class="text-end">Số lượng</th>
                                <th style="width:120px">ĐVT</th>
                                <th style="width:160px" class="text-end">Giá vốn</th>
                                <th style="width:170px" class="text-end">Thành tiền</th>
                                <th style="min-width:220px">Nhóm / Ghi chú</th>
                            </tr>
                        </thead>

                        <tbody>
                            @forelse($stockItems as $index => $item)
                                <tr>
                                    <td class="text-center">{{ $index + 1 }}</td>

                                    <td>
                                        <div class="fw-bold">
                                            {{ $item->product->name ?? '—' }}
                                        </div>
                                    </td>

                                    <td>
                                        <span class="badge bg-light text-dark border">
                                            {{ $item->product_id ?? '—' }}
                                        </span>
                                    </td>

                                    <td class="text-end fw-semibold">
                                        {{ $fmtQty($item->qty ?? 0) }}
                                    </td>

                                    <td>
                                        {{ $item->unit ?: ($item->product->unit ?? '—') }}
                                    </td>

                                    <td class="text-end text-success fw-bold">
                                        {{ $fmtMoney($item->unit_cost ?? 0) }}
                                    </td>

                                    <td class="text-end fw-bold">
                                        {{ $fmtMoney($item->line_total ?? 0) }}
                                    </td>

                                    <td>
                                        <span class="badge bg-primary-subtle text-primary border">
                                            {{ $detectGroup($item) }}
                                        </span>

                                        @if($stripPrefix($item->note ?? '') !== '')
                                            <div class="small text-muted mt-1">
                                                {{ $stripPrefix($item->note ?? '') }}
                                            </div>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="8" class="text-center text-muted py-4">
                                        Chưa có vật tư trong kho.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            {{-- VẬT TƯ NGOÀI KHO --}}
            <div class="card border-0 shadow-sm info-card mt-3">
                <div class="card-header bg-white border-0 py-3 d-flex flex-wrap justify-content-between align-items-center gap-2">
                    <div class="d-flex align-items-center gap-2">
                        <span class="section-icon external">
                            <i class="bi bi-tools"></i>
                        </span>

                        <div>
                            <div class="fw-bold">Vật tư / thiết bị ngoài kho</div>
                            <div class="text-muted small">Warehouse nhập giá vốn tại bước kho duyệt / xuất.</div>
                        </div>
                    </div>

                    <span class="badge bg-light text-dark border">
                        {{ $externalItems->count() }} dòng
                    </span>
                </div>

                @if($isAdminApproved && $hasExternalItems)
                    <form method="POST"
                          action="{{ route('material-requests.warehouse-approve', $mr->id) }}"
                          id="warehouseApproveForm"
                          onsubmit="return confirm('Xác nhận kho duyệt / xuất đơn vật tư #{{ $mr->id }}?')">
                        @csrf
                @endif

                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th style="width:60px" class="text-center">#</th>
                                <th style="min-width:260px">Tên vật tư ngoài kho</th>
                                <th style="width:120px" class="text-end">Số lượng</th>
                                <th style="width:130px">ĐVT</th>
                                <th style="width:180px" class="text-end">Giá vốn / đơn vị</th>
                                <th style="width:120px" class="text-end">VAT %</th>
                                <th style="width:170px" class="text-end">Thành tiền</th>
                                <th style="min-width:220px">Nhóm / Ghi chú</th>
                            </tr>
                        </thead>

                        <tbody>
                            @forelse($externalItems as $index => $item)
                                @php
                                    $externalUnit = $item->unit ?: $externalUnitFromNote($item);
                                    $unitCost = (float)($item->unit_cost ?? 0);
                                    $vatPercent = (float)($item->vat_percent ?? 0);
                                    $lineTotal = (float)($item->line_total ?? 0);
                                @endphp

                                <tr class="{{ $unitCost <= 0 && $isAdminApproved ? 'table-warning' : '' }}">
                                    <td class="text-center">{{ $index + 1 }}</td>

                                    <td>
                                        <div class="fw-bold">
                                            {{ $externalName($item) }}
                                        </div>

                                        <div class="small text-muted">
                                            Mã dòng: {{ $item->id }}
                                        </div>
                                    </td>

                                    <td class="text-end fw-semibold">
                                        {{ $fmtQty($item->qty ?? 0) }}
                                    </td>

                                    <td>
                                        @if($isAdminApproved)
                                            <input type="text"
                                                   name="costs[{{ $item->id }}][unit]"
                                                   class="form-control form-control-sm"
                                                   value="{{ old('costs.' . $item->id . '.unit', $externalUnit) }}"
                                                   placeholder="m/cái/bộ">
                                        @else
                                            {{ $externalUnit ?: '—' }}
                                        @endif
                                    </td>

                                    <td>
                                        @if($isAdminApproved)
                                           <input type="number"
       name="costs[{{ $item->id }}][unit_cost]"
       class="form-control form-control-sm text-end external-cost-input"
       min="0"
       step="any"
       value="{{ old('costs.' . $item->id . '.unit_cost', $unitCost > 0 ? $unitCost : '') }}"
       placeholder="Nhập giá vốn"
       data-qty="{{ (float)($item->qty ?? 0) }}"
       required>
                                        @else
                                            <div class="text-end fw-bold {{ $unitCost > 0 ? 'text-success' : 'text-muted' }}">
                                                {{ $unitCost > 0 ? $fmtMoney($unitCost) : 'Chưa nhập' }}
                                            </div>
                                        @endif
                                    </td>

                                    <td>
                                        @if($isAdminApproved)
                                            <input type="number"
                                                   name="costs[{{ $item->id }}][vat_percent]"
                                                   class="form-control form-control-sm text-end"
                                                   min="0"
                                                   step="0.01"
                                                   value="{{ old('costs.' . $item->id . '.vat_percent', $vatPercent) }}"
                                                   placeholder="0">
                                        @else
                                            <div class="text-end">
                                                {{ $fmtQty($vatPercent) }}%
                                            </div>
                                        @endif
                                    </td>

                                    <td class="text-end">
                                        @if($isAdminApproved)
                                            <span class="external-line-preview fw-bold">
                                                {{ $fmtMoney($lineTotal) }}
                                            </span>
                                        @else
                                            <span class="fw-bold">
                                                {{ $fmtMoney($lineTotal) }}
                                            </span>
                                        @endif
                                    </td>

                                    <td>
                                        <span class="badge bg-warning-subtle text-warning border">
                                            {{ $detectGroup($item) }}
                                        </span>

                                        @php
                                            $cleanNote = $stripPrefix($item->note ?? '');
                                            $parts = array_values(array_filter(array_map('trim', explode('|', $cleanNote)), fn($x) => $x !== ''));
                                            $noteOnly = collect($parts)->filter(function ($part, $i) {
                                                return $i > 0 && !str_starts_with($part, 'ĐVT:');
                                            })->implode(' | ');
                                        @endphp

                                        @if($noteOnly !== '')
                                            <div class="small text-muted mt-1">
                                                {{ $noteOnly }}
                                            </div>
                                        @endif

                                        @if($isAdminApproved)
    @php
        $warehousesForAdd = \Illuminate\Support\Facades\DB::table('crm_warehouses')
            ->select('id', 'name')
            ->orderBy('name')
            ->get();
    @endphp

    <div class="mt-2 add-stock-box">
        <input type="hidden"
               class="add-to-stock-hidden"
               name="costs[{{ $item->id }}][add_to_catalog]"
               value="0">

        <button type="button"
                class="btn btn-sm btn-outline-primary btnAddExternalToStock">
            <i class="bi bi-plus-circle"></i> Thêm vào kho
        </button>

        <div class="add-stock-panel mt-2 d-none">
            <label class="form-label small mb-1">Chọn kho để nhập rồi xuất/trừ tồn</label>

            <select name="costs[{{ $item->id }}][warehouse_id]"
                    class="form-select form-select-sm add-stock-warehouse">
                <option value="">-- Chọn kho --</option>
                @foreach($warehousesForAdd as $warehouse)
                    <option value="{{ $warehouse->id }}">
                        {{ $warehouse->name }}
                    </option>
                @endforeach
            </select>

            <div class="small text-muted mt-1">
                Khi bấm duyệt kho, hệ thống sẽ tạo sản phẩm nếu chưa có, nhập vào kho này rồi xuất/trừ tồn cho công trình.
            </div>
        </div>
    </div>
@endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="8" class="text-center text-muted py-4">
                                        Không có vật tư ngoài kho.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                @if($isAdminApproved && $hasExternalItems)
                    <div class="card-footer bg-white border-0 p-3">
                        <div class="alert alert-warning border-0 mb-3" style="border-radius:14px;">
                            <div class="fw-bold">
                                <i class="bi bi-exclamation-circle"></i> Cần nhập giá vốn cho vật tư ngoài kho
                            </div>
                            <div class="small">
                                Sau khi bấm <strong>Kho duyệt / Xuất kho</strong>, hệ thống sẽ cộng giá vốn này vào chi phí công trình.
                                Checkbox “Ghi nhớ vào danh mục/kho sau” hiện dùng để đánh dấu, bước sau mình sẽ nối logic tạo sản phẩm/kho nếu anh muốn.
                            </div>
                        </div>

                        <button type="submit" class="btn btn-success btn-lg w-100">
                            <i class="bi bi-box-arrow-up"></i> Nhập giá vốn & Kho duyệt / Xuất kho
                        </button>
                    </div>

                    </form>
                @endif

                @if($isAdminApproved && !$hasExternalItems)
                    <div class="card-footer bg-white border-0 p-3">
                        <form method="POST"
                              action="{{ route('material-requests.warehouse-approve', $mr->id) }}"
                              onsubmit="return confirm('Xác nhận kho duyệt / xuất đơn vật tư #{{ $mr->id }}?')">
                            @csrf

                            <button type="submit" class="btn btn-success btn-lg w-100">
                                <i class="bi bi-box-arrow-up"></i> Kho duyệt / Xuất kho
                            </button>
                        </form>
                    </div>
                @endif
            </div>

            {{-- GHI CHÚ --}}
            <div class="card border-0 shadow-sm info-card mt-3">
                <div class="card-header bg-white border-0 py-3">
                    <div class="d-flex align-items-center gap-2">
                        <span class="section-icon">
                            <i class="bi bi-journal-text"></i>
                        </span>

                        <div class="fw-bold">Thông tin nội bộ</div>
                    </div>
                </div>

                <div class="card-body">
                    <div class="text-muted small">Ghi chú</div>
                    <div class="fw-semibold">
                        {{ $mr->note ?: '—' }}
                    </div>
                </div>
            </div>
        </div>

        {{-- RIGHT --}}
        <div class="col-lg-4">
            <div class="sticky-top" style="top:90px;">

                {{-- HÀNH ĐỘNG --}}
                <div class="card border-0 shadow-sm side-card mb-3">
                    <div class="card-header bg-white border-0 py-3">
                        <div class="d-flex align-items-center gap-2">
                            <span class="section-icon action">
                                <i class="bi bi-lightning-charge"></i>
                            </span>

                            <div class="fw-bold">Hành động</div>
                        </div>
                    </div>

                    <div class="card-body">
                        {{-- EGO_MR_SIDE_EDIT_BUTTON --}}
                        @if($canEditMaterialRequest && !$isDraft)
                            <div class="d-grid gap-2 mb-3">
                                <a href="{{ route('material-requests.edit', $mr->id) }}" class="btn btn-outline-warning">
                                    <i class="bi bi-pencil-square"></i> Sửa đơn
                                </a>
                            </div>
                        @endif

                        @if($isDraft)
                            <div class="d-grid gap-2">
                                <a href="{{ route('material-requests.edit', $mr->id) }}" class="btn btn-outline-warning">
                                    <i class="bi bi-pencil-square"></i> Sửa đơn
                                </a>

                                <form method="POST"
                                      action="{{ route('material-requests.submit', $mr->id) }}"
                                      onsubmit="return confirm('Gửi đơn vật tư #{{ $mr->id }} cho admin duyệt?')">
                                    @csrf

                                    <button type="submit" class="btn btn-primary w-100">
                                        <i class="bi bi-send-check"></i> Gửi admin duyệt
                                    </button>
                                </form>
                            </div>
                        @elseif($isSubmitted)
                            <form method="POST"
                                  action="{{ route('material-requests.admin-approve', $mr->id) }}"
                                  onsubmit="return confirm('Admin duyệt đơn vật tư #{{ $mr->id }}?')">
                                @csrf

                                <button type="submit" class="btn btn-warning w-100">
                                    <i class="bi bi-shield-check"></i> Admin duyệt
                                </button>
                            </form>
                        @elseif($isAdminApproved)
                            <div class="alert alert-info border-0 mb-0" style="border-radius:14px;">
                                <div class="fw-bold">
                                    <i class="bi bi-box-arrow-up"></i> Đang chờ kho duyệt
                                </div>
                                <div class="small">
                                    Nhập giá vốn cho hàng ngoài kho ở bảng bên trái rồi bấm nút xuất kho.
                                </div>
                            </div>
                        @elseif($isExported)
                            <div class="alert alert-success border-0 mb-0" style="border-radius:14px;">
                                <div class="fw-bold">
                                    <i class="bi bi-check-circle"></i> Đã xuất kho
                                </div>
                                <div class="small">
                                    Chi phí đã được ghi nhận vào công trình.
                                </div>
                            </div>
                        @else
                            <div class="text-muted">Không có hành động.</div>
                        @endif
                    </div>
                </div>

                {{-- TRẠNG THÁI --}}
                <div class="card border-0 shadow-sm side-card mb-3">
                    <div class="card-header bg-white border-0 py-3">
                        <div class="d-flex align-items-center gap-2">
                            <span class="section-icon">
                                <i class="bi bi-activity"></i>
                            </span>

                            <div>
                                <div class="fw-bold">Trạng thái hiện tại</div>
                                <div class="text-muted small">Theo dõi tiến độ xử lý đơn.</div>
                            </div>
                        </div>
                    </div>

                    <div class="card-body">
                        <div class="timeline-item done">
                            <span>1</span>
                            <strong>Kỹ thuật tạo đơn</strong>
                        </div>

                        <div class="timeline-item {{ $isSubmitted || $isAdminApproved || $isExported ? 'done' : '' }}">
                            <span>2</span>
                            <strong>Admin duyệt</strong>
                        </div>

                        <div class="timeline-item {{ $isAdminApproved || $isExported ? 'done' : '' }}">
                            <span>3</span>
                            <strong>Kho duyệt / Xuất kho</strong>
                        </div>

                        <div class="timeline-item {{ $isExported ? 'done' : '' }}">
                            <span>4</span>
                            <strong>Hoàn tất</strong>
                        </div>

                        <hr>

                        <div class="d-flex justify-content-between">
                            <span class="text-muted">Trạng thái</span>
                            <strong>{{ $statusLabel }}</strong>
                        </div>

                        <div class="d-flex justify-content-between mt-2">
                            <span class="text-muted">Raw</span>
                            <strong>{{ $status ?: '—' }}</strong>
                        </div>
                    </div>
                </div>

                {{-- TÓM TẮT --}}
                <div class="card border-0 shadow-sm side-card">
                    <div class="card-header bg-white border-0 py-3">
                        <div class="d-flex align-items-center gap-2">
                            <span class="section-icon">
                                <i class="bi bi-card-checklist"></i>
                            </span>

                            <div class="fw-bold">Tóm tắt</div>
                        </div>
                    </div>

                    <div class="card-body">
                        <div class="summary-line">
                            <span>Số dòng vật tư</span>
                            <strong>{{ $items->count() }}</strong>
                        </div>

                        <div class="summary-line">
                            <span>Trong kho</span>
                            <strong>{{ $stockItems->count() }}</strong>
                        </div>

                        <div class="summary-line">
                            <span>Ngoài kho</span>
                            <strong>{{ $externalItems->count() }}</strong>
                        </div>

                        <div class="summary-line">
                            <span>Tổng số lượng</span>
                            <strong>{{ $fmtQty($totalQty) }}</strong>
                        </div>

                        <div class="summary-line">
                            <span>Thiếu giá vốn ngoài kho</span>
                            <strong class="{{ $externalMissingCost > 0 ? 'text-danger' : 'text-success' }}">
                                {{ $externalMissingCost }}
                            </strong>
                        </div>

                        <hr>

                        <div class="summary-line">
                            <span>Tổng giá vốn</span>
                            <strong class="text-success">{{ $fmtMoney($totalCost) }}</strong>
                        </div>

                        <div class="summary-line">
                            <span>Công trình</span>
                            <strong class="text-end">{{ $mr->site->name ?? '—' }}</strong>
                        </div>
                    </div>
                </div>

            </div>
        </div>

    </div>
    {    {{-- EGO_MR_HISTORY_BLOCK_START --}}
    @php
        $realHistories = collect($editHistories ?? [])->filter(function ($history) {
            return ($history->user_name ?? '') !== 'SYSTEM TEST';
        })->values();

        $statusMap = [
            'DRAFT' => 'Nháp',
            'SUBMITTED' => 'Chờ admin duyệt',
            'ADMIN_APPROVED' => 'Kho duyệt',
            'EXPORTED' => 'Hoàn tất',
            'TEST' => 'Kiểm tra',
        ];

        $statusLabel = function ($status) use ($statusMap) {
            $status = (string) $status;
            return $statusMap[$status] ?? ($status !== '' ? $status : '—');
        };
    @endphp

    <style>
        .mr-history-wrap{
            border-radius:18px;
            overflow:hidden;
        }

        .mr-history-header{
            display:flex;
            justify-content:space-between;
            align-items:center;
            gap:12px;
            flex-wrap:wrap;
        }

        .mr-history-count{
            display:inline-flex;
            align-items:center;
            gap:6px;
            padding:8px 12px;
            border-radius:999px;
            background:#eef4ff;
            color:#1d4ed8;
            font-size:13px;
            font-weight:700;
        }

        .mr-history-item{
            border:1px solid rgba(15,23,42,.08);
            border-radius:16px;
            background:#fff;
            padding:16px;
            margin-bottom:14px;
            box-shadow:0 4px 14px rgba(15,23,42,.04);
        }

        .mr-history-top{
            display:flex;
            justify-content:space-between;
            gap:12px;
            align-items:flex-start;
            flex-wrap:wrap;
        }

        .mr-history-user{
            font-size:18px;
            font-weight:800;
            color:#0f172a;
            margin:0;
        }

        .mr-history-role{
            display:inline-block;
            margin-top:4px;
            padding:4px 10px;
            border-radius:999px;
            background:#f1f5f9;
            color:#475569;
            font-size:12px;
            font-weight:700;
        }

        .mr-history-time{
            color:#64748b;
            font-size:13px;
            font-weight:600;
            white-space:nowrap;
        }

        .mr-history-status{
            display:flex;
            align-items:center;
            gap:8px;
            flex-wrap:wrap;
            margin-top:10px;
        }

        .mr-pill{
            display:inline-flex;
            align-items:center;
            padding:6px 10px;
            border-radius:999px;
            font-size:12px;
            font-weight:700;
        }

        .mr-pill-from{
            background:#f8fafc;
            color:#334155;
            border:1px solid #cbd5e1;
        }

        .mr-pill-to{
            background:#dbeafe;
            color:#1d4ed8;
            border:1px solid #93c5fd;
        }

        .mr-history-note{
            margin-top:12px;
            padding:12px 14px;
            border-radius:12px;
            background:#f8fafc;
            color:#334155;
            font-size:14px;
        }

        .mr-history-highlights{
            display:flex;
            gap:8px;
            flex-wrap:wrap;
            margin-top:12px;
        }

        .mr-highlight{
            display:inline-flex;
            align-items:center;
            padding:7px 10px;
            border-radius:10px;
            background:#fef3c7;
            color:#92400e;
            font-size:12px;
            font-weight:700;
        }

        .mr-history-summary{
            display:grid;
            grid-template-columns:repeat(auto-fit, minmax(180px, 1fr));
            gap:10px;
            margin-top:14px;
        }

        .mr-summary-box{
            border:1px solid rgba(15,23,42,.08);
            background:#fff;
            border-radius:14px;
            padding:12px;
        }

        .mr-summary-label{
            color:#64748b;
            font-size:12px;
            margin-bottom:6px;
        }

        .mr-summary-value{
            color:#0f172a;
            font-size:16px;
            font-weight:800;
        }

        .mr-history-details{
            margin-top:14px;
            border-top:1px dashed #e2e8f0;
            padding-top:12px;
        }

        .mr-history-details summary{
            cursor:pointer;
            list-style:none;
            color:#2563eb;
            font-weight:700;
        }

        .mr-history-details summary::-webkit-details-marker{
            display:none;
        }

        .mr-json-grid{
            display:grid;
            grid-template-columns:repeat(auto-fit, minmax(280px, 1fr));
            gap:12px;
            margin-top:12px;
        }

        .mr-json-card{
            border:1px solid rgba(15,23,42,.08);
            background:#f8fafc;
            border-radius:14px;
            overflow:hidden;
        }

        .mr-json-title{
            padding:10px 12px;
            font-weight:800;
            color:#0f172a;
            background:#eef2ff;
            border-bottom:1px solid rgba(15,23,42,.08);
        }

        .mr-json-card pre{
            margin:0;
            padding:12px;
            white-space:pre-wrap;
            font-size:12px;
            max-height:320px;
            overflow:auto;
        }

        .mr-history-empty{
            border:1px dashed #cbd5e1;
            border-radius:16px;
            padding:28px 20px;
            text-align:center;
            background:linear-gradient(180deg, #ffffff 0%, #f8fafc 100%);
        }

        .mr-history-empty-icon{
            width:54px;
            height:54px;
            border-radius:50%;
            display:inline-flex;
            align-items:center;
            justify-content:center;
            background:#eef2ff;
            color:#4f46e5;
            font-size:24px;
            margin-bottom:12px;
        }

        .mr-history-empty-title{
            font-size:18px;
            font-weight:800;
            color:#0f172a;
            margin-bottom:6px;
        }

        .mr-history-empty-text{
            color:#64748b;
            font-size:14px;
        }
    </style>

    <div class="card border-0 shadow-sm info-card mt-4 mr-history-wrap">
        <div class="card-header bg-white border-0 py-3">
            <div class="mr-history-header">
                <div class="d-flex align-items-center gap-2">
                    <span class="section-icon">
                        <i class="bi bi-clock-history"></i>
                    </span>

                    <div>
                        <div class="fw-bold fs-5">Lịch sử chỉnh sửa</div>
                        <div class="text-muted small">Theo dõi admin / kho / kỹ thuật đã chỉnh sửa đơn vật tư như thế nào.</div>
                    </div>
                </div>

                <div class="mr-history-count">
                    <i class="bi bi-activity"></i>
                    {{ $realHistories->count() }} lần chỉnh sửa
                </div>
            </div>
        </div>

        <div class="card-body">
            @forelse($realHistories as $history)
                @php
                    $changes = json_decode($history->changes ?? '{}', true);
                    if (!is_array($changes)) {
                        $changes = [];
                    }

                    $beforeRequest = data_get($changes, 'before.request', []);
                    $afterRequest = data_get($changes, 'after.request', []);
                    $beforeItems = data_get($changes, 'before.items', []);
                    $afterItems = data_get($changes, 'after.items', []);

                    $beforeTotal = (float) ($beforeRequest['total_cost'] ?? 0);
                    $afterTotal = (float) ($afterRequest['total_cost'] ?? 0);

                    $beforeItemCount = is_array($beforeItems) ? count($beforeItems) : 0;
                    $afterItemCount = is_array($afterItems) ? count($afterItems) : 0;

                    $beforeQty = 0;
                    if (is_array($beforeItems)) {
                        foreach ($beforeItems as $it) {
                            $beforeQty += (float) ($it['qty'] ?? 0);
                        }
                    }

                    $afterQty = 0;
                    if (is_array($afterItems)) {
                        foreach ($afterItems as $it) {
                            $afterQty += (float) ($it['qty'] ?? 0);
                        }
                    }

                    $highlights = [];

                    if (($beforeRequest['note'] ?? null) !== ($afterRequest['note'] ?? null)) {
                        $highlights[] = 'Cập nhật ghi chú';
                    }

                    if ($beforeTotal !== $afterTotal) {
                        $highlights[] = 'Thay đổi tổng giá vốn';
                    }

                    if ($beforeItemCount !== $afterItemCount) {
                        $highlights[] = 'Thay đổi số dòng vật tư';
                    }

                    if ($beforeQty !== $afterQty) {
                        $highlights[] = 'Thay đổi tổng số lượng';
                    }

                    if (empty($highlights)) {
                        $highlights[] = 'Đã cập nhật thông tin đơn';
                    }
                @endphp

                <div class="mr-history-item">
                    <div class="mr-history-top">
                        <div>
                            <div class="mr-history-user">{{ $history->user_name ?? 'Không rõ người sửa' }}</div>

                            @if(!empty($history->user_role))
                                <div class="mr-history-role">{{ $history->user_role }}</div>
                            @endif
                        </div>

                        <div class="mr-history-time">
                            <i class="bi bi-calendar3"></i>
                            {{ !empty($history->created_at) ? \Illuminate\Support\Carbon::parse($history->created_at)->format('d/m/Y H:i') : '—' }}
                        </div>
                    </div>

                    <div class="mr-history-status">
                        <span class="mr-pill mr-pill-from">{{ $statusLabel($history->status_before ?? '') }}</span>
                        <span class="text-muted fw-bold">→</span>
                        <span class="mr-pill mr-pill-to">{{ $statusLabel($history->status_after ?? '') }}</span>
                    </div>

                    <div class="mr-history-note">
                        {{ $history->note ?? 'Chỉnh sửa đơn vật tư' }}
                    </div>

                    <div class="mr-history-highlights">
                        @foreach($highlights as $hl)
                            <span class="mr-highlight">{{ $hl }}</span>
                        @endforeach
                    </div>

                    <div class="mr-history-summary">
                        <div class="mr-summary-box">
                            <div class="mr-summary-label">Tổng giá vốn</div>
                            <div class="mr-summary-value">{{ number_format($afterTotal, 0, ',', '.') }} đ</div>
                        </div>

                        <div class="mr-summary-box">
                            <div class="mr-summary-label">Số dòng vật tư</div>
                            <div class="mr-summary-value">{{ $afterItemCount }}</div>
                        </div>

                        <div class="mr-summary-box">
                            <div class="mr-summary-label">Tổng số lượng</div>
                            <div class="mr-summary-value">{{ number_format($afterQty, 0, ',', '.') }}</div>
                        </div>
                    </div>

                    <details class="mr-history-details">
                        <summary>
                            <i class="bi bi-chevron-down"></i> Xem chi tiết thay đổi
                        </summary>

                        <div class="mr-json-grid">
                            <div class="mr-json-card">
                                <div class="mr-json-title">Trước khi sửa</div>
                                <pre>{{ json_encode(data_get($changes, 'before', []), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
                            </div>

                            <div class="mr-json-card">
                                <div class="mr-json-title">Sau khi sửa</div>
                                <pre>{{ json_encode(data_get($changes, 'after', []), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
                            </div>
                        </div>
                    </details>
                </div>
            @empty
                <div class="mr-history-empty">
                    <div class="mr-history-empty-icon">
                        <i class="bi bi-clock-history"></i>
                    </div>
                    <div class="mr-history-empty-title">Chưa có lịch sử chỉnh sửa</div>
                    <div class="mr-history-empty-text">
                        Khi admin, kho hoặc kỹ thuật chỉnh sửa đơn vật tư và bấm lưu, lịch sử sẽ hiển thị tại đây.
                    </div>
                </div>
            @endforelse
        </div>
    </div>
    {{-- EGO_MR_HISTORY_BLOCK_END --}}}

</div>

<script>
(function(){
    function money(v){
        const n = Number(v || 0);
        return n.toLocaleString('vi-VN') + ' đ';
    }
document.querySelectorAll('.btnAddExternalToStock').forEach(function(button){
    button.addEventListener('click', function(){
        const box = button.closest('.add-stock-box');
        const panel = box ? box.querySelector('.add-stock-panel') : null;
        const hidden = box ? box.querySelector('.add-to-stock-hidden') : null;

        if (!box || !panel || !hidden) {
            return;
        }

        hidden.value = '1';
        panel.classList.remove('d-none');

        button.classList.remove('btn-outline-primary');
        button.classList.add('btn-success');
        button.innerHTML = '<i class="bi bi-check-circle"></i> Đã thêm';
    });
});
    document.querySelectorAll('.external-cost-input').forEach(function(input){
        function updateLine(){
            const qty = Number(input.dataset.qty || 0);
            const cost = Number(input.value || 0);
            const row = input.closest('tr');
            const target = row ? row.querySelector('.external-line-preview') : null;

            if (target) {
                target.textContent = money(qty * cost);
            }
        }

        input.addEventListener('input', updateLine);
        updateLine();
    });
})();
</script>

<style>
    .material-show-page{
        background:
            radial-gradient(circle at top left, rgba(11,201,170,.10), transparent 28%),
            linear-gradient(180deg, rgba(11,201,170,.06), rgba(255,255,255,0) 60%);
        border-radius:22px;
    }

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

    .info-card,
    .side-card{
        border-radius:18px;
        overflow:hidden;
    }

    .section-icon{
        width:34px;
        height:34px;
        border-radius:12px;
        display:inline-flex;
        align-items:center;
        justify-content:center;
        background:rgba(37,99,235,.10);
        color:#2563eb;
        border:1px solid rgba(37,99,235,.16);
        flex:0 0 auto;
    }

    .section-icon.stock{
        background:rgba(16,185,129,.10);
        color:#059669;
        border-color:rgba(16,185,129,.18);
    }

    .section-icon.external{
        background:rgba(245,158,11,.12);
        color:#b45309;
        border-color:rgba(245,158,11,.22);
    }

    .section-icon.action{
        background:rgba(99,102,241,.10);
        color:#4f46e5;
        border-color:rgba(99,102,241,.18);
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
        background:rgba(11,201,170,.045);
    }

    .form-control,
    .btn{
        border-radius:12px;
    }

    .timeline-item{
        display:flex;
        align-items:center;
        gap:10px;
        margin-bottom:14px;
        color:#64748b;
    }

    .timeline-item span{
        width:28px;
        height:28px;
        border-radius:999px;
        display:inline-flex;
        align-items:center;
        justify-content:center;
        background:#f1f5f9;
        color:#64748b;
        border:1px solid rgba(0,0,0,.08);
        font-size:13px;
        font-weight:700;
        flex:0 0 auto;
    }

    .timeline-item.done{
        color:#0f172a;
    }

    .timeline-item.done span{
        background:#16a34a;
        color:#fff;
        border-color:#16a34a;
    }

    .summary-line{
        display:flex;
        justify-content:space-between;
        gap:12px;
        margin-bottom:12px;
    }

    .summary-line span{
        color:#64748b;
    }

    .summary-line strong{
        color:#0f172a;
    }

    .history-item{
        border:1px solid rgba(15,23,42,.08);
        border-radius:14px;
        padding:12px;
        margin-bottom:12px;
        background:#fff;
    }

    .history-json{
        margin-top:8px;
        white-space:pre-wrap;
        background:#f8fafc;
        border:1px solid rgba(15,23,42,.08);
        border-radius:12px;
        padding:12px;
        font-size:12px;
        max-height:360px;
        overflow:auto;
    }

    @media (max-width: 991.98px){
        .sticky-top{
            position:static !important;
        }
    }
</style>

@endsection