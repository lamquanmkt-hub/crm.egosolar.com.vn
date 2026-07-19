@php
    use Carbon\Carbon;

    $dept = $currentLevel ?? ($order->current_department ?? '');
    $dept = strtolower($dept);

    // Nếu hệ thống trước đây có 'ketoan' thì vẫn support
    $isAccounting = in_array($dept, ['accounting', 'ketoan'], true);

    $levelLabel = match ($dept) {
        'sales' => 'Sales',
        'sales_manager' => 'Sales Manager',
        'accounting', 'ketoan' => 'Kế toán',
        'management' => 'Ban Giám đốc',
        'warehouse' => 'Kho vận',
        'completed' => 'Hoàn thành',
        default => ucfirst($dept),
    };

    // ✅ NEW: Tính tổng theo items.line_total (đã bao gồm giảm % / giảm tiền)
    $items = $order->items ?? collect();
    $computedTotal = $items->sum(function ($i) {
        return (float)($i->line_total ?? 0);
    });

    // (tuỳ chọn) tổng giảm tiền để debug nếu cần
    // $computedDiscountAmount = $items->sum(fn($i) => (float)($i->discount_amount ?? 0));
@endphp

@extends('layouts.app')
@section('title', 'Duyệt đơn hàng #' . $order->order_code)

@section('content')
    <div class="container-fluid px-4">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h1 class="fw-bold text-uppercase text-secondary">DUYỆT ĐƠN HÀNG #{{ $order->order_code }}</h1>
            <a href="{{ route('orders.show', $order->id) }}" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left"></i> Quay lại
            </a>
        </div>

        {{-- Errors --}}
        @if($errors->any())
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <h5 class="alert-heading"><i class="bi bi-exclamation-triangle"></i> Có lỗi xảy ra!</h5>
                <ul class="mb-0">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        @endif

        <div class="row">
            {{-- Left: Form --}}
            <div class="col-md-8">
                <div class="card shadow-sm">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0"><i class="bi bi-check-circle"></i> Xử lý duyệt đơn hàng</h5>
                    </div>

                    <div class="card-body">
                        <form action="{{ route('orders.process-approval', $order->id) }}" method="POST" id="approvalForm">
                            @csrf

                            {{-- Current info --}}
                            <div class="alert alert-info">
                                <h6 class="alert-heading"><i class="bi bi-info-circle"></i> Thông tin duyệt</h6>
                                <p class="mb-0">
                                    Bạn đang xử lý duyệt cho cấp: <strong>{{ $levelLabel }}</strong><br>
                                    Trạng thái hiện tại: <strong>{{ $order->currentStatusType->name }}</strong>
                                </p>
                            </div>

                            {{-- Order details --}}
                            {{-- ✅ UI UPDATED: card header đẹp hơn, table đẹp hơn (giữ nguyên dữ liệu) --}}
                            <div class="card shadow-sm mb-3 border-0">
                                <div class="card-header bg-white d-flex justify-content-between align-items-center py-3">
                                    <h6 class="mb-0 fw-bold text-uppercase text-secondary">
                                        <i class="bi bi-box-seam me-1"></i> Chi tiết đơn hàng
                                    </h6>
                                    <span class="badge bg-secondary">{{ $order->items->count() }} mặt hàng</span>
                                </div>

                                <div class="card-body">
                                    <div class="row mb-2">
                                        <div class="col-md-6">
                                            <p class="mb-1"><strong>Khách hàng:</strong> {{ $order->lead->customer->name }}</p>
                                            <p class="mb-1"><strong>Điện thoại:</strong> {{ $order->lead->customer->phone }}</p>
                                            <p class="mb-1"><strong>Loại khách:</strong> {{ $order->lead->customer->customerType->name ?? 'N/A' }}</p>
                                        </div>
                                        <div class="col-md-6">
                                            <p class="mb-1"><strong>Ngày đặt:</strong> {{ $order->order_date ? Carbon::parse($order->order_date)->format('d/m/Y') : '-' }}</p>
                                            @php
                                                $warehouseNames = collect($order->items ?? [])
                                                    ->map(function ($it) {
                                                        // tuỳ bạn đặt relation/field: warehouse hoặc kho
                                                        return $it->warehouse->name ?? $it->kho->name ?? null;
                                                    })
                                                    ->filter()
                                                    ->unique()
                                                    ->values();

                                                $warehouseText = $warehouseNames->isNotEmpty()
                                                    ? $warehouseNames->implode(', ')
                                                    : ($order->warehouse->name ?? 'N/A');
                                            @endphp

                                            <p class="mb-1"><strong>Kho xuất:</strong> {{ $warehouseText }}</p>
                                            <p class="mb-1"><strong>Sales:</strong> {{ $order->creator->name ?? 'N/A' }}</p>
                                        </div>
                                    </div>

                                    <div class="table-responsive">
                                        {{-- ✅ UI UPDATED: bỏ table-bordered, dùng hover + align-middle --}}
                                        <table class="table table-hover align-middle mb-0">
                                            <thead class="table-light">
                                            <tr class="small text-uppercase text-secondary">
                                                <th style="min-width:260px;">Sản phẩm</th>
                                                <th class="text-center" style="width:70px;">SL</th>
                                                <th class="text-end" style="min-width:130px;">Đơn giá</th>
                                                <th class="text-center" style="min-width:110px;">Giảm (%)</th>
                                                <th class="text-end" style="min-width:140px;">Giảm (đ)</th>
                                                <th class="text-end" style="min-width:150px;">Thành tiền</th>
                                            </tr>
                                            </thead>

                                            <tbody>
                                            @foreach($order->items as $item)
                                                @php
                                                    $qty = (int)($item->quantity ?? 0);
                                                    $price = (float)($item->unit_price ?? 0);
                                                    $subtotal = $qty * $price;

                                                    $discPercent = (float)($item->discount_percent ?? 0);
                                                    $discPercent = max(0, min(100, $discPercent));

                                                    // nếu DB có discount_amount thì lấy, không có thì tự tính từ %
                                                    $discAmount = (float)($item->discount_amount ?? 0);
                                                    if ($discAmount <= 0 && $discPercent > 0) {
                                                        $discAmount = $subtotal * $discPercent / 100;
                                                    }

                                                    $lineTotal = $subtotal - $discAmount;
                                                @endphp

                                                <tr>
                                                    <td class="fw-semibold">{{ $item->product->name ?? '-' }}</td>
                                                    <td class="text-center">
                                                        <span class="badge bg-light text-dark border">{{ $qty }}</span>
                                                    </td>
                                                    <td class="text-end">{{ number_format($item->unit_price, 0, ',', '.') }} đ</td>
                                                    <td class="text-center">
                                                        @if($discPercent > 0)
                                                            <span class="badge bg-warning text-dark">
                                                                {{ rtrim(rtrim(number_format($discPercent, 2), '0'), '.') }}%
                                                            </span>
                                                        @else
                                                            <span class="text-muted">0%</span>
                                                        @endif
                                                    </td>
                                                    <td class="text-end">
                                                        @if($discAmount > 0)
                                                            <span class="text-danger fw-semibold">
                                                                -{{ number_format($discAmount, 0, ',', '.') }} đ
                                                            </span>
                                                        @else
                                                            <span class="text-muted">0 đ</span>
                                                        @endif
                                                    </td>
                                                    <td class="text-end fw-bold">{{ number_format($item->line_total, 0, ',', '.') }} đ</td>
                                                </tr>
                                            @endforeach
                                            </tbody>

                                            <tfoot class="table-light">
                                            <tr>
                                                {{-- ✅ FIX: vì table có 6 cột => colspan 5, tổng nằm cột cuối --}}
                                                <td colspan="5" class="text-end fw-bold">TỔNG CỘNG:</td>
                                                <td class="text-end fw-bold text-primary">{{ number_format($computedTotal, 0, ',', '.') }} đ</td>
                                            </tr>
                                            </tfoot>
                                        </table>
                                    </div>
                                </div>
                            </div>

                            {{-- Debt check: Accounting only --}}
                            @if($isAccounting)
                                <div class="card mb-3 border-warning">
                                    <div class="card-header bg-warning">
                                        <h6 class="mb-0"><i class="bi bi-exclamation-triangle"></i> Kiểm tra công nợ</h6>
                                    </div>
                                    <div class="card-body">
                                        @php
                                            $paid = $order->payments->sum('amount');
                                            $total = (float)($order->total_amount ?? 0);
                                            $remainingDebt = max(0, $total - $paid);
                                        @endphp

                                        <div class="alert {{ $remainingDebt > 0 ? 'alert-danger' : 'alert-success' }}">
                                            <strong>Tổng công nợ hiện tại:</strong>
                                            <span class="fs-5">{{ number_format($remainingDebt, 0, ',', '.') }} đ</span>
                                        </div>

                                        <div class="form-check mb-2">
                                            <input class="form-check-input @error('debt_checked') is-invalid @enderror"
                                                   type="checkbox"
                                                   name="debt_checked"
                                                   id="debtChecked"
                                                   value="1"
                                                    {{ old('debt_checked') ? 'checked' : '' }}>
                                            <label class="form-check-label fw-bold" for="debtChecked">
                                                Tôi đã kiểm tra công nợ của khách hàng <span class="text-danger">*</span>
                                            </label>
                                            @error('debt_checked')
                                            <div class="invalid-feedback d-block">{{ $message }}</div>
                                            @enderror
                                        </div>

                                        <div class="mb-3">
                                            <label class="form-label">Ghi chú về công nợ (nếu có)</label>
                                            <textarea name="debt_note"
                                                      class="form-control @error('debt_note') is-invalid @enderror"
                                                      rows="2"
                                                      placeholder="VD: Khách có công nợ cũ nhưng đã cam kết thanh toán...">{{ old('debt_note') }}</textarea>
                                            @error('debt_note')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>
                                    </div>
                                </div>
                            @endif

                           {{-- Approval history --}}
<div class="card mb-3">
    <div class="card-header bg-light">
        <h6 class="mb-0"><i class="bi bi-clock-history"></i> Lịch sử duyệt</h6>
    </div>
    <div class="card-body">
        @php
            $approvalSteps = [
                'sales' => 'Sales',
                'sales_manager' => 'Sales_manager',
                'accounting' => 'Accounting',
            ];

            $approvalMap = collect($order->approvals ?? [])->keyBy(function ($approval) {
                return strtolower($approval->level ?? '');
            });
        @endphp

        <div class="table-responsive">
            <table class="table table-sm">
                <thead>
                <tr>
                    <th>Cấp duyệt</th>
                    <th>Người duyệt</th>
                    <th>Thời gian</th>
                    <th>Trạng thái</th>
                </tr>
                </thead>
                <tbody>
                @foreach($approvalSteps as $levelKey => $levelLabel)
                    @php
                        $approval = $approvalMap->get($levelKey);

                        $approverName = $approval->approver->name ?? '-';

                        $displayTime = '-';
                        if ($approval) {
                            $displayTime = $approval->approved_at
                                ? $approval->approved_at->format('d/m/Y H:i')
                                : ($approval->created_at ? $approval->created_at->format('d/m/Y H:i') : '-');
                        }

                        if ($levelKey === 'sales') {
                            $badgeClass = 'bg-info';
                            $statusText = 'Gửi duyệt';
                        } elseif ($approval && $approval->status === 'approved') {
                            $badgeClass = 'bg-success';
                            $statusText = 'Đã duyệt';
                        } elseif ($approval && $approval->status === 'rejected') {
                            $badgeClass = 'bg-danger';
                            $statusText = 'Từ chối';
                        } else {
                            $badgeClass = 'bg-warning';
                            $statusText = 'Chờ xử lý';
                        }
                    @endphp

                    <tr>
                        <td>{{ $levelLabel }}</td>
                        <td>{{ $approverName }}</td>
                        <td>{{ $displayTime }}</td>
                        <td>
                            <span class="badge {{ $badgeClass }}">{{ $statusText }}</span>
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>

                            {{-- Decision --}}
                            <div class="card mb-3 border-primary">
                                <div class="card-header bg-primary text-white">
                                    <h6 class="mb-0"><i class="bi bi-pencil-square"></i> Quyết định của bạn</h6>
                                </div>
                                <div class="card-body">
                                    <div class="mb-3">
                                        <label class="form-label fw-bold">Chọn hành động <span class="text-danger">*</span></label>
                                        <div class="d-flex gap-3">
                                            <div class="form-check form-check-inline">
                                                <input class="form-check-input @error('action') is-invalid @enderror"
                                                       type="radio"
                                                       name="action"
                                                       id="actionApprove"
                                                       value="approve"
                                                       {{ old('action') === 'approve' ? 'checked' : '' }}
                                                       required>
                                                <label class="form-check-label text-success fw-bold" for="actionApprove">
                                                    <i class="bi bi-check-circle"></i> Duyệt đơn
                                                </label>
                                            </div>

                                            <div class="form-check form-check-inline">
                                                <input class="form-check-input @error('action') is-invalid @enderror"
                                                       type="radio"
                                                       name="action"
                                                       id="actionReject"
                                                       value="reject"
                                                       {{ old('action') === 'reject' ? 'checked' : '' }}
                                                       required>
                                                <label class="form-check-label text-danger fw-bold" for="actionReject">
                                                    <i class="bi bi-x-circle"></i> Từ chối
                                                </label>
                                            </div>
                                        </div>

                                        @error('action')
                                        <div class="text-danger small mt-1">{{ $message }}</div>
                                        @enderror
                                    </div>

                                    <div class="mb-3" id="rejectionReasonGroup" style="display: {{ old('action') === 'reject' ? 'block' : 'none' }};">
                                        <label class="form-label fw-bold">Lý do từ chối <span class="text-danger">*</span></label>
                                        <textarea name="rejection_reason"
                                                  id="rejectionReason"
                                                  class="form-control @error('rejection_reason') is-invalid @enderror"
                                                  rows="3"
                                                  placeholder="Nhập lý do từ chối đơn hàng...">{{ old('rejection_reason') }}</textarea>
                                        @error('rejection_reason')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                        <div class="form-text">Lý do này sẽ được gửi cho Sales</div>
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label">Ghi chú thêm (không bắt buộc)</label>
                                        <textarea name="note"
                                                  class="form-control @error('note') is-invalid @enderror"
                                                  rows="2"
                                                  placeholder="Ghi chú thêm về quyết định của bạn...">{{ old('note') }}</textarea>
                                        @error('note')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>

                                    <div class="alert alert-warning">
                                        <i class="bi bi-exclamation-triangle"></i>
                                        <strong>Lưu ý:</strong> Quyết định của bạn sẽ ảnh hưởng trực tiếp đến quy trình xử lý đơn hàng.
                                        Vui lòng kiểm tra kỹ trước khi xác nhận.
                                    </div>
                                </div>
                            </div>

                            {{-- Actions --}}
                            <div class="d-flex justify-content-end gap-2">
                                <a href="{{ route('orders.show', $order->id) }}" class="btn btn-secondary">
                                    <i class="bi bi-x"></i> Hủy
                                </a>
                                <button type="submit" class="btn btn-success btn-lg" id="submitBtn">
                                    <i class="bi bi-check-circle"></i> Xác nhận duyệt
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            {{-- Right: Sidebar --}}
            <div class="col-md-4">
                <div class="card shadow-sm mb-3">
                    <div class="card-header bg-dark text-white">
                        <h5 class="mb-0"><i class="bi bi-info-circle"></i> Tóm tắt</h5>
                    </div>
                    <div class="card-body">
                        <table class="table table-sm">
                            <tr>
                                <td>Tổng sản phẩm:</td>
                                <td class="text-end fw-bold">{{ $order->items->count() }}</td>
                            </tr>
                            <tr>
                                <td>Tổng số lượng:</td>
                                <td class="text-end fw-bold">{{ $order->items->sum('quantity') }}</td>
                            </tr>
                            <tr class="table-primary">
                                <td>Tổng tiền:</td>
                                {{-- ✅ FIX: dùng tổng theo line_total --}}
                                <td class="text-end fw-bold fs-5">{{ number_format($computedTotal, 0, ',', '.') }} đ</td>
                            </tr>
                        </table>
                    </div>
                </div>

                <div class="card shadow-sm border-info">
                    <div class="card-header bg-info text-white">
                        <h5 class="mb-0"><i class="bi bi-lightbulb"></i> Hướng dẫn</h5>
                    </div>
                    <div class="card-body">
                        <h6 class="text-info">Trách nhiệm của {{ $levelLabel }}:</h6>
                        <ul class="small">
                            @if($isAccounting)
                                <li>Kiểm tra công nợ khách hàng</li>
                                <li>Xác minh thông tin thanh toán</li>
                                <li>Đảm bảo đơn hàng hợp lệ</li>
                            @elseif($dept === 'sales_manager')
                                <li>Kiểm tra tính hợp lý của đơn hàng</li>
                                <li>Xác nhận số lượng và giá trị</li>
                                <li>Đánh giá rủi ro</li>
                            @elseif($dept === 'management')
                                <li>Duyệt cuối cùng trước khi xuất kho</li>
                                <li>Quyết định chiến lược</li>
                                <li>Phê duyệt đơn hàng lớn</li>
                            @elseif($dept === 'warehouse')
                                <li>Chuẩn bị hàng hóa</li>
                                <li>Xuất kho theo đơn</li>
                                <li>Hoàn tất giao hàng</li>
                            @endif
                        </ul>
                        <hr>
                        <p class="small mb-0 text-muted">
                            <i class="bi bi-clock"></i> Sau khi duyệt, đơn hàng sẽ chuyển sang bộ phận tiếp theo trong quy trình.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- JS: only one block --}}
    <script>
        const isAccounting = @json($isAccounting);

        function syncUIByAction() {
            const approve = document.getElementById('actionApprove');
            const reject = document.getElementById('actionReject');
            const rejectionGroup = document.getElementById('rejectionReasonGroup');
            const rejectionInput = document.getElementById('rejectionReason');
            const submitBtn = document.getElementById('submitBtn');

            if (reject && reject.checked) {
                rejectionGroup.style.display = 'block';
                rejectionInput.required = true;
                submitBtn.innerHTML = '<i class="bi bi-x-circle"></i> Xác nhận từ chối';
                submitBtn.className = 'btn btn-danger btn-lg';
                return;
            }

            // default approve
            rejectionGroup.style.display = 'none';
            rejectionInput.required = false;
            submitBtn.innerHTML = '<i class="bi bi-check-circle"></i> Xác nhận duyệt';
            submitBtn.className = 'btn btn-success btn-lg';
        }

        document.addEventListener('DOMContentLoaded', function () {
            syncUIByAction();

            document.getElementById('actionApprove')?.addEventListener('change', syncUIByAction);
            document.getElementById('actionReject')?.addEventListener('change', syncUIByAction);

            document.getElementById('approvalForm').addEventListener('submit', function (e) {
                const action = document.querySelector('input[name="action"]:checked');
                if (!action) {
                    e.preventDefault();
                    alert('Vui lòng chọn hành động (Duyệt hoặc Từ chối)');
                    return;
                }

                // Require debt_checked for accounting when approve
                if (isAccounting && action.value === 'approve') {
                    const debtCheckbox = document.getElementById('debtChecked');
                    if (!debtCheckbox || !debtCheckbox.checked) {
                        e.preventDefault();
                        alert('Bạn phải xác nhận đã kiểm tra công nợ.');
                        debtCheckbox?.scrollIntoView({ behavior: 'smooth', block: 'center' });
                        return;
                    }
                }

                // Require rejection reason
                if (action.value === 'reject') {
                    const reason = document.getElementById('rejectionReason')?.value?.trim();
                    if (!reason) {
                        e.preventDefault();
                        alert('Vui lòng nhập lý do từ chối.');
                        document.getElementById('rejectionReason')?.focus();
                        return;
                    }
                }

                const actionText = action.value === 'approve' ? 'DUYỆT' : 'TỪ CHỐI';
                if (!confirm(`Bạn có chắc chắn muốn ${actionText} đơn hàng này?`)) {
                    e.preventDefault();
                }
            });
        });
    </script>
@endsection
