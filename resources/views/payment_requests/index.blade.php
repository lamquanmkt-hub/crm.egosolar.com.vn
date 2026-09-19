@extends('layouts.app')

{{-- EGO_DNTT_ENTERPRISE_ASSETS_START --}}
@push('styles')
    <link
        rel="stylesheet"
        href="{{ asset('css/ego-payment-requests-enterprise.css') }}?v={{ filemtime(public_path('css/ego-payment-requests-enterprise.css')) }}"
    >
@endpush

@push('scripts')
    <script
        src="{{ asset('js/ego-payment-requests-enterprise.js') }}?v={{ filemtime(public_path('js/ego-payment-requests-enterprise.js')) }}"
        defer
    ></script>
@endpush
{{-- EGO_DNTT_ENTERPRISE_ASSETS_END --}}


@section('content')
@php
    $statusLabels = $statusLabels ?? [
        'draft' => 'Nháp',
        'submitted' => 'Đã gửi duyệt',
        'admin_approved' => 'Quản lý tài chính đã duyệt',
        'admin_rejected' => 'Quản lý tài chính từ chối',
        'accounting_approved' => 'Kế toán đã chi',
        'accounting_rejected' => 'Kế toán từ chối',
    ];

    $statusBadges = [
        'draft' => 'secondary',
        'submitted' => 'warning',
        'admin_approved' => 'info',
        'admin_rejected' => 'danger',
        'accounting_approved' => 'success',
        'accounting_rejected' => 'danger',
    ];

    $canViewAll = $canViewAll ?? false;
    $canAdminApprove = $canAdminApprove ?? false;
    $canAccountingApprove = $canAccountingApprove ?? false;
    $canBulkApprove = $canBulkApprove ?? ($canAdminApprove || $canAccountingApprove);

    $exportParams = request()->only([
        'company', 'status', 'q', 'date_from', 'date_to', 'date_preset', 'created_by'
    ]);
@endphp


<div class="ego-pr-page">
    <header class="ego-pr-page-header ego-pr-reveal">
        <div class="ego-pr-page-heading">
            <span class="ego-pr-page-icon" aria-hidden="true">
                <i class="bi bi-wallet2"></i>
            </span>

            <div class="ego-pr-page-copy">
                <div class="ego-pr-eyebrow">TRUNG TÂM TÀI CHÍNH</div>
                <h1>Đề nghị thanh toán</h1>
                <p>Theo dõi, phê duyệt và kiểm soát toàn bộ đề nghị chi phí trong doanh nghiệp.</p>
            </div>
        </div>

        <div class="ego-pr-page-actions">
            <a class="ego-pr-button ego-pr-button--secondary" href="{{ route('payment_requests.export_excel', $exportParams) }}">
                <i class="bi bi-file-earmark-excel"></i>
                <span>Xuất Excel</span>
            </a>

            <a class="ego-pr-button ego-pr-button--secondary" href="{{ route('payment_requests.export_pdf', $exportParams) }}">
                <i class="bi bi-file-earmark-pdf"></i>
                <span>Xuất PDF</span>
            </a>

            <button class="ego-pr-button ego-pr-button--primary" type="button" data-bs-toggle="modal" data-bs-target="#createPRModal">
                <i class="bi bi-plus-lg"></i>
                <span>Tạo phiếu mới</span>
            </button>
        </div>
    </header>

    @if(session('success'))
        <div class="ego-pr-alert ego-pr-alert--success ego-pr-reveal" role="alert">
            <i class="bi bi-check-circle"></i>
            <span>{{ session('success') }}</span>
            <button type="button" data-bs-dismiss="alert" aria-label="Đóng"><i class="bi bi-x-lg"></i></button>
        </div>
    @endif

    @if(session('error'))
        <div class="ego-pr-alert ego-pr-alert--danger ego-pr-reveal" role="alert">
            <i class="bi bi-exclamation-triangle"></i>
            <span>{{ session('error') }}</span>
            <button type="button" data-bs-dismiss="alert" aria-label="Đóng"><i class="bi bi-x-lg"></i></button>
        </div>
    @endif

    @if($errors->any())
        <div class="ego-pr-alert ego-pr-alert--danger ego-pr-reveal" role="alert">
            <i class="bi bi-exclamation-triangle"></i>
            <div>
                <strong>Chưa lưu được phiếu</strong>
                <ul>
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
            <button type="button" data-bs-dismiss="alert" aria-label="Đóng"><i class="bi bi-x-lg"></i></button>
        </div>
    @endif

    <section class="ego-pr-kpis ego-pr-reveal" aria-label="Thống kê đề nghị thanh toán">
        <article class="ego-pr-kpi ego-pr-kpi--cyan">
            <div>
                <span>Tổng đề nghị</span>
                <strong>{{ number_format((int)($totalRequests ?? (method_exists($items, 'total') ? $items->total() : count($items)))) }}</strong>
                <small>phiếu trong phạm vi lọc</small>
            </div>
            <i class="bi bi-receipt"></i>
        </article>

        <article class="ego-pr-kpi ego-pr-kpi--orange">
            <div>
                <span>Chờ xử lý</span>
                <strong>{{ number_format((int)($pendingCount ?? 0)) }}</strong>
                <small>đang ở luồng phê duyệt</small>
            </div>
            <i class="bi bi-hourglass-split"></i>
        </article>

        <article class="ego-pr-kpi ego-pr-kpi--green">
            <div>
                <span>Đã hoàn tất</span>
                <strong>{{ number_format((int)($approvedCount ?? 0)) }}</strong>
                <small>kế toán đã xác nhận chi</small>
            </div>
            <i class="bi bi-check2-circle"></i>
        </article>

        <article class="ego-pr-kpi ego-pr-kpi--blue">
            <div>
                <span>Tổng giá trị đề nghị</span>
                <strong class="ego-pr-kpi-money">{{ number_format((int)($totalAmount ?? 0)) }} đ</strong>
                <small>tổng giá trị trong phạm vi lọc</small>
            </div>
            <i class="bi bi-cash-stack"></i>
        </article>
    </section>

    <div class="ego-pr-mobile-toolbar ego-pr-reveal">
        <form method="GET" action="{{ route('payment_requests.index') }}" class="ego-pr-mobile-search">
            <i class="bi bi-search"></i>
            <input type="search" name="q" value="{{ request('q') }}" placeholder="Tìm mã phiếu, người nhận..." aria-label="Tìm kiếm đề nghị thanh toán">
            @foreach(request()->except(['q', 'page']) as $key => $value)
                @if(!is_array($value))
                    <input type="hidden" name="{{ $key }}" value="{{ $value }}">
                @endif
            @endforeach
        </form>

        <button type="button" class="ego-pr-filter-open" id="egoPrFilterOpen" aria-controls="egoPrFilterPanel" aria-expanded="false">
            <i class="bi bi-sliders2"></i>
            <span>Bộ lọc</span>
        </button>
    </div>

    <div class="ego-pr-filter-backdrop" id="egoPrFilterBackdrop" aria-hidden="true"></div>

    <section class="ego-pr-filter-card ego-pr-reveal" id="egoPrFilterPanel" aria-label="Bộ lọc đề nghị thanh toán">
        <div class="ego-pr-filter-head">
            <div>
                <span class="ego-pr-filter-icon"><i class="bi bi-sliders2"></i></span>
                <div>
                    <strong>Bộ lọc & tìm kiếm</strong>
                    <small>Thu hẹp dữ liệu theo đúng nhu cầu xử lý.</small>
                </div>
            </div>
            <button type="button" class="ego-pr-filter-close" id="egoPrFilterClose" aria-label="Đóng bộ lọc"><i class="bi bi-x-lg"></i></button>
        </div>

        <form method="GET" action="{{ route('payment_requests.index') }}" class="ego-pr-filter-form">
            <input type="hidden" name="date_filter_manual" id="date_filter_manual" value="0">

            <div class="ego-pr-field ego-pr-field--search">
                <label for="egoPrSearch">Tìm kiếm</label>
                <div class="ego-pr-input-icon">
                    <i class="bi bi-search"></i>
                    <input id="egoPrSearch" type="text" name="q" value="{{ request('q') }}" placeholder="Mã phiếu / Người nhận / Nội dung / Lý do...">
                </div>
            </div>

            <div class="ego-pr-field">
                <label for="egoPrCompany">Công ty</label>
                <select id="egoPrCompany" name="company">
                    <option value="">Công ty Quốc Tế EGO</option>
                    @foreach($companyOptions ?? [] as $c)
                        <option value="{{ $c }}" @selected(request('company') === $c)>{{ $c }}</option>
                    @endforeach
                </select>
            </div>

            <div class="ego-pr-field">
                <label for="egoPrStatus">Trạng thái</label>
                <select id="egoPrStatus" name="status">
                    <option value="">Tất cả trạng thái</option>
                    @foreach(['draft','submitted','admin_approved','admin_rejected','accounting_approved','accounting_rejected'] as $st)
                        <option value="{{ $st }}" @selected(request('status') === $st)>{{ $statusLabels[$st] ?? $st }}</option>
                    @endforeach
                </select>
            </div>

            @if($canViewAll)
                <div class="ego-pr-field">
                    <label for="egoPrCreator">Người tạo</label>
                    <select id="egoPrCreator" name="created_by">
                        <option value="">Tất cả nhân sự</option>
                        @foreach(($creatorOptions ?? []) as $u)
                            <option value="{{ $u->id }}" @selected((string)request('created_by') === (string)$u->id)>{{ $u->name }}</option>
                        @endforeach
                    </select>
                </div>
            @endif

            <div class="ego-pr-field">
                <label for="date_from">Từ ngày</label>
                <input
                    type="date"
                    name="date_from"
                    id="date_from"
                    value="{{ $effectiveDateFrom ?? request('date_from') }}"
                    oninput="document.getElementById('date_preset').value='custom';document.getElementById('date_filter_manual').value='1';"
                    onchange="document.getElementById('date_preset').value='custom';document.getElementById('date_filter_manual').value='1';"
                >
            </div>

            <div class="ego-pr-field">
                <label for="date_to">Đến ngày</label>
                <input
                    type="date"
                    name="date_to"
                    id="date_to"
                    value="{{ $effectiveDateTo ?? request('date_to') }}"
                    oninput="document.getElementById('date_preset').value='custom';document.getElementById('date_filter_manual').value='1';"
                    onchange="document.getElementById('date_preset').value='custom';document.getElementById('date_filter_manual').value='1';"
                >
            </div>

            <div class="ego-pr-field">
                <label for="date_preset">Khoảng thời gian</label>
                <select name="date_preset" id="date_preset" onchange="document.getElementById('date_filter_manual').value='0';">
                    <option value="this_month" @selected(($selectedDatePreset ?? '') === 'this_month')>Tháng này</option>
                    <option value="last_month" @selected(($selectedDatePreset ?? '') === 'last_month')>Tháng trước</option>
                    <option value="this_week" @selected(($selectedDatePreset ?? '') === 'this_week')>Tuần này</option>
                    <option value="last_week" @selected(($selectedDatePreset ?? '') === 'last_week')>Tuần trước</option>
                    <option value="today" @selected(($selectedDatePreset ?? '') === 'today')>Hôm nay</option>
                    <option value="this_year" @selected(($selectedDatePreset ?? '') === 'this_year')>Năm nay</option>
                    <option value="custom" @selected(($selectedDatePreset ?? '') === 'custom')>Tùy chỉnh</option>
                    <option value="all_time" @selected(($selectedDatePreset ?? 'all_time') === 'all_time')>Tất cả thời gian</option>
                </select>
            </div>

            <div class="ego-pr-filter-footer">
                <div class="ego-pr-paid-chip">
                    <i class="bi bi-check2-circle"></i>
                    <span>Đã chi trong kỳ</span>
                    <strong>{{ number_format((int)($totalPaid ?? 0)) }} đ</strong>
                </div>

                <div class="ego-pr-filter-actions">
                    <a class="ego-pr-button ego-pr-button--ghost" href="{{ route('payment_requests.index') }}">
                        <i class="bi bi-arrow-counterclockwise"></i>
                        Xóa lọc
                    </a>
                    <button class="ego-pr-button ego-pr-button--primary" type="submit">
                        <i class="bi bi-funnel"></i>
                        Áp dụng
                    </button>
                </div>
            </div>
        </form>
    </section>

    @if($canBulkApprove)
        <section class="bulk-approval-bar ego-pr-bulk-bar ego-pr-reveal" id="prBulkApprovalBar">
            <div class="bulk-approval-summary">
                <span class="bulk-approval-title"><i class="bi bi-check2-square"></i> Duyệt hàng loạt</span>
                <span class="bulk-approval-count">Đã chọn <strong id="prBulkSelectedCount">0</strong> phiếu</span>
                <span class="bulk-approval-dot" aria-hidden="true">•</span>
                <strong id="prBulkSelectedAmount">0 đ</strong>
            </div>
            <div class="bulk-approval-actions">
                <label class="bulk-select-all-mobile">
                    <input type="checkbox" id="prBulkSelectAllMobile" class="pr-bulk-select-all">
                    Chọn tất cả
                </label>
                <button type="button" class="ego-pr-button ego-pr-button--ghost" id="prBulkClear" disabled>
                    <i class="bi bi-x-lg"></i> Bỏ chọn
                </button>
                <button type="button" class="ego-pr-button ego-pr-button--primary" id="prBulkOpenApprove" disabled>
                    <i class="bi bi-check2-all"></i> Duyệt đã chọn
                </button>
            </div>
        </section>
    @endif

    <section class="ego-pr-data-card ego-pr-reveal">
        <div class="ego-pr-data-head">
            <div>
                <strong>Danh sách đề nghị</strong>
                <span>Hiển thị {{ number_format((int)($items->count() ?? 0)) }} phiếu trên trang hiện tại</span>
            </div>
            <span class="ego-pr-data-count">{{ number_format((int)($items->total() ?? count($items))) }} phiếu</span>
        </div>

        <div class="ego-pr-desktop-table">
            <div class="ego-pr-table-scroll">
                <table class="ego-pr-table">
                    <thead>
                        <tr>
                            @if($canBulkApprove)
                                <th class="ego-pr-col-check">
                                    <input type="checkbox" id="prBulkSelectAllDesktop" class="pr-bulk-select-all" aria-label="Chọn tất cả phiếu đủ điều kiện">
                                </th>
                            @endif
                            <th class="ego-pr-col-code">Mã phiếu</th>
                            <th class="ego-pr-col-recipient">Người nhận</th>
                            <th class="ego-pr-col-content">Nội dung / Lý do</th>
                            <th class="ego-pr-col-amount">Số tiền</th>
                            <th class="ego-pr-col-status">Trạng thái</th>
                            <th class="ego-pr-col-due">Hạn thanh toán</th>
                            <th class="ego-pr-col-company">Công ty</th>
                            <th class="ego-pr-col-creator">Người tạo</th>
                            <th class="ego-pr-col-actions">Thao tác</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($items as $it)
                            @php
                                $status = $it->status ?? 'draft';
                                $label = $statusLabels[$status] ?? $status;
                                $isOwner = ((int)$it->created_by === (int)auth()->id());
                                $canEditDelete = $isOwner && in_array($status, ['draft','admin_rejected','accounting_rejected'], true);
                                $canSubmit = $canEditDelete;
                                $canAdminAction = ($status === 'submitted');
                                $canAccAction = ($status === 'admin_approved');
                                $bulkSelectable = ($canAdminApprove && $canAdminAction) || ($canAccountingApprove && $canAccAction);
                                $canDownloadPdf = ($status === 'accounting_approved');
                                $dueText = '-';
                                $isOverdue = false;
                                if (!empty($it->payment_due_date)) {
                                    try {
                                        $dueDate = \Illuminate\Support\Carbon::parse($it->payment_due_date);
                                        $dueText = $dueDate->format('d/m/Y');
                                        $isOverdue = $dueDate->isPast() && !in_array($status, ['accounting_approved','accounting_rejected'], true);
                                    } catch (\Throwable $e) {
                                        $dueText = (string)$it->payment_due_date;
                                    }
                                }
                                $contentText = trim(strip_tags((string)($it->payment_content ?: $it->reason ?: '-')));
                            @endphp

                            <tr data-payment-id="{{ $it->id }}">
                                @if($canBulkApprove)
                                    <td class="ego-pr-col-check">
                                        @if($bulkSelectable)
                                            <input
                                                type="checkbox"
                                                class="pr-bulk-checkbox js-pr-bulk-checkbox"
                                                value="{{ $it->id }}"
                                                data-id="{{ $it->id }}"
                                                data-code="{{ $it->code }}"
                                                data-amount="{{ (int)$it->amount }}"
                                                aria-label="Chọn phiếu {{ $it->code }}"
                                            >
                                        @else
                                            <span class="ego-pr-unavailable">—</span>
                                        @endif
                                    </td>
                                @endif

                                <td class="ego-pr-col-code">
                                    <a href="{{ route('payment_requests.show', $it->id) }}" class="ego-pr-code">{{ $it->code }}</a>
                                    <small>{{ optional($it->created_at)->format('d/m/Y · H:i') ?? '-' }}</small>
                                </td>

                                <td class="ego-pr-col-recipient">
                                    <strong>{{ $it->receiver_name ?: 'Chưa cập nhật' }}</strong>
                                    <small>{{ $it->department ?: 'Chưa có đơn vị' }}</small>
                                </td>

                                <td class="ego-pr-col-content">
                                    <div class="ego-pr-content-clamp" title="{{ $contentText }}">{{ $contentText }}</div>
                                </td>

                                <td class="ego-pr-col-amount"><strong>{{ number_format((int)$it->amount) }} đ</strong></td>

                                <td class="ego-pr-col-status">
                                    <span class="ego-pr-status ego-pr-status--{{ str_replace('_', '-', $status) }}">{{ $label }}</span>
                                </td>

                                <td class="ego-pr-col-due">
                                    <span>{{ $dueText }}</span>
                                    @if($isOverdue)
                                        <small class="ego-pr-overdue">Quá hạn</small>
                                    @endif
                                </td>

                                <td class="ego-pr-col-company"><div class="ego-pr-company-clamp" title="{{ $it->company ?? '-' }}">{{ $it->company ?? '-' }}</div></td>
                                <td class="ego-pr-col-creator"><strong>{{ optional($it->creator)->name ?? ($it->created_by ?? '-') }}</strong></td>

                                <td class="ego-pr-col-actions">
                                    <div class="ego-pr-row-actions">
                                        <a class="ego-pr-view-button" href="{{ route('payment_requests.show', $it->id) }}" aria-label="Xem chi tiết {{ $it->code }}" title="Xem chi tiết">
                                            <i class="bi bi-eye"></i>
                                        </a>

                                        <button class="ego-pr-menu-toggle" type="button" aria-label="Mở thao tác" aria-expanded="false" data-menu-id="egoPrActions{{ $it->id }}">
                                            <i class="bi bi-three-dots"></i>
                                        </button>

                                        <div class="ego-pr-action-menu" id="egoPrActions{{ $it->id }}" aria-hidden="true">
                                            <a href="{{ route('payment_requests.show', $it->id) }}"><i class="bi bi-eye"></i><span>Xem chi tiết</span></a>

                                            <form action="{{ route('payment_requests.copy', $it->id) }}" method="POST" class="ego-pr-confirm-form" data-confirm="Sao chép phiếu này thành phiếu mới?">
                                                @csrf
                                                <button type="submit"><i class="bi bi-files"></i><span>Sao chép phiếu</span></button>
                                            </form>

                                            @if($canEditDelete)
                                                <a href="{{ route('payment_requests.edit', $it->id) }}"><i class="bi bi-pencil"></i><span>Chỉnh sửa</span></a>
                                            @endif

                                            @if($canSubmit)
                                                <form action="{{ route('payment_requests.submit', $it->id) }}" method="POST" class="ego-pr-confirm-form" data-confirm="Gửi duyệt phiếu này?">
                                                    @csrf
                                                    <button type="submit"><i class="bi bi-send-check"></i><span>Gửi duyệt</span></button>
                                                </form>
                                            @endif

                                            @if($canAdminApprove && $canAdminAction)
                                                <form action="{{ route('payment_requests.admin_approve', $it->id) }}" method="POST" class="ego-pr-confirm-form" data-confirm="Duyệt phiếu này?">
                                                    @csrf
                                                    <button type="submit" class="is-positive"><i class="bi bi-check2-circle"></i><span>Duyệt phiếu</span></button>
                                                </form>
                                                <form action="{{ route('payment_requests.admin_reject', $it->id) }}" method="POST">
                                                    @csrf
                                                    <button type="submit" class="is-danger"><i class="bi bi-x-circle"></i><span>Từ chối</span></button>
                                                </form>
                                            @endif

                                            @if($canAccountingApprove && $canAccAction)
                                                <form action="{{ route('payment_requests.acc_approve', $it->id) }}" method="POST" class="ego-pr-confirm-form" data-confirm="Xác nhận đã chi phiếu này?">
                                                    @csrf
                                                    <button type="submit" class="is-positive"><i class="bi bi-cash-coin"></i><span>Xác nhận đã chi</span></button>
                                                </form>
                                                <form action="{{ route('payment_requests.acc_reject', $it->id) }}" method="POST">
                                                    @csrf
                                                    <button type="submit" class="is-danger"><i class="bi bi-x-circle"></i><span>Kế toán từ chối</span></button>
                                                </form>
                                            @endif

                                            @if($canDownloadPdf)
                                                <a href="{{ route('payment_requests.invoice', $it->id) }}"><i class="bi bi-filetype-pdf"></i><span>Tải PDF</span></a>
                                            @endif

                                            @if($canEditDelete)
                                                <div class="ego-pr-menu-divider"></div>
                                                <form action="{{ route('payment_requests.destroy', $it->id) }}" method="POST" class="ego-pr-confirm-form" data-confirm="Xóa phiếu này? Hành động không thể hoàn tác.">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="is-danger"><i class="bi bi-trash"></i><span>Xóa phiếu</span></button>
                                                </form>
                                            @endif
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="{{ $canBulkApprove ? 10 : 9 }}" class="ego-pr-empty-row">
                                    <i class="bi bi-inbox"></i>
                                    <strong>Chưa có đề nghị thanh toán</strong>
                                    <span>Thử thay đổi bộ lọc hoặc tạo một phiếu mới.</span>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="ego-pr-mobile-list">
            @forelse($items as $it)
                @php
                    $status = $it->status ?? 'draft';
                    $label = $statusLabels[$status] ?? $status;
                    $isOwner = ((int)$it->created_by === (int)auth()->id());
                    $canEditDelete = $isOwner && in_array($status, ['draft','admin_rejected','accounting_rejected'], true);
                    $canSubmit = $canEditDelete;
                    $canAdminAction = ($status === 'submitted');
                    $canAccAction = ($status === 'admin_approved');
                    $bulkSelectable = ($canAdminApprove && $canAdminAction) || ($canAccountingApprove && $canAccAction);
                    $canDownloadPdf = ($status === 'accounting_approved');
                    $dueText = '-';
                    if (!empty($it->payment_due_date)) {
                        try { $dueText = \Illuminate\Support\Carbon::parse($it->payment_due_date)->format('d/m/Y'); } catch (\Throwable $e) { $dueText = (string)$it->payment_due_date; }
                    }
                    $contentText = trim(strip_tags((string)($it->payment_content ?: $it->reason ?: '-')));
                @endphp

                <article class="ego-pr-mobile-card" data-payment-id="{{ $it->id }}">
                    <div class="ego-pr-mobile-card-head">
                        <div class="ego-pr-mobile-card-code">
                            @if($canBulkApprove && $bulkSelectable)
                                <input
                                    type="checkbox"
                                    class="pr-bulk-checkbox js-pr-bulk-checkbox"
                                    value="{{ $it->id }}"
                                    data-id="{{ $it->id }}"
                                    data-code="{{ $it->code }}"
                                    data-amount="{{ (int)$it->amount }}"
                                    aria-label="Chọn phiếu {{ $it->code }}"
                                >
                            @endif
                            <div>
                                <a href="{{ route('payment_requests.show', $it->id) }}">{{ $it->code }}</a>
                                <small>{{ optional($it->created_at)->format('d/m/Y · H:i') ?? '-' }}</small>
                            </div>
                        </div>

                        <div class="ego-pr-mobile-card-status">
                            <span class="ego-pr-status ego-pr-status--{{ str_replace('_', '-', $status) }}">{{ $label }}</span>
                            <button class="ego-pr-menu-toggle" type="button" aria-label="Mở thao tác" aria-expanded="false" data-menu-id="egoPrMobileActions{{ $it->id }}"><i class="bi bi-three-dots"></i></button>
                        </div>

                        <div class="ego-pr-action-menu" id="egoPrMobileActions{{ $it->id }}" aria-hidden="true">
                            <a href="{{ route('payment_requests.show', $it->id) }}"><i class="bi bi-eye"></i><span>Xem chi tiết</span></a>
                            <form action="{{ route('payment_requests.copy', $it->id) }}" method="POST" class="ego-pr-confirm-form" data-confirm="Sao chép phiếu này thành phiếu mới?">@csrf<button type="submit"><i class="bi bi-files"></i><span>Sao chép</span></button></form>
                            @if($canEditDelete)<a href="{{ route('payment_requests.edit', $it->id) }}"><i class="bi bi-pencil"></i><span>Chỉnh sửa</span></a>@endif
                            @if($canSubmit)<form action="{{ route('payment_requests.submit', $it->id) }}" method="POST" class="ego-pr-confirm-form" data-confirm="Gửi duyệt phiếu này?">@csrf<button type="submit"><i class="bi bi-send-check"></i><span>Gửi duyệt</span></button></form>@endif
                            @if($canAdminApprove && $canAdminAction)
                                <form action="{{ route('payment_requests.admin_approve', $it->id) }}" method="POST" class="ego-pr-confirm-form" data-confirm="Duyệt phiếu này?">@csrf<button type="submit" class="is-positive"><i class="bi bi-check2-circle"></i><span>Duyệt phiếu</span></button></form>
                                <form action="{{ route('payment_requests.admin_reject', $it->id) }}" method="POST">@csrf<button type="submit" class="is-danger"><i class="bi bi-x-circle"></i><span>Từ chối</span></button></form>
                            @endif
                            @if($canAccountingApprove && $canAccAction)
                                <form action="{{ route('payment_requests.acc_approve', $it->id) }}" method="POST" class="ego-pr-confirm-form" data-confirm="Xác nhận đã chi phiếu này?">@csrf<button type="submit" class="is-positive"><i class="bi bi-cash-coin"></i><span>Đã chi</span></button></form>
                                <form action="{{ route('payment_requests.acc_reject', $it->id) }}" method="POST">@csrf<button type="submit" class="is-danger"><i class="bi bi-x-circle"></i><span>Từ chối</span></button></form>
                            @endif
                            @if($canDownloadPdf)<a href="{{ route('payment_requests.invoice', $it->id) }}"><i class="bi bi-filetype-pdf"></i><span>Tải PDF</span></a>@endif
                            @if($canEditDelete)
                                <div class="ego-pr-menu-divider"></div>
                                <form action="{{ route('payment_requests.destroy', $it->id) }}" method="POST" class="ego-pr-confirm-form" data-confirm="Xóa phiếu này?">@csrf @method('DELETE')<button type="submit" class="is-danger"><i class="bi bi-trash"></i><span>Xóa phiếu</span></button></form>
                            @endif
                        </div>
                    </div>

                    <div class="ego-pr-mobile-card-body">
                        <div class="ego-pr-mobile-recipient">
                            <span>Người nhận</span>
                            <strong>{{ $it->receiver_name ?: 'Chưa cập nhật' }}</strong>
                            <small>{{ $it->department ?: 'Chưa có đơn vị' }}</small>
                        </div>

                        <div class="ego-pr-mobile-content">{{ $contentText }}</div>

                        <strong class="ego-pr-mobile-amount">{{ number_format((int)$it->amount) }} đ</strong>

                        <div class="ego-pr-mobile-meta">
                            <span><i class="bi bi-calendar3"></i> Hạn: {{ $dueText }}</span>
                            <span><i class="bi bi-person"></i> {{ optional($it->creator)->name ?? ($it->created_by ?? '-') }}</span>
                        </div>
                    </div>

                    <div class="ego-pr-mobile-card-foot">
                        <a href="{{ route('payment_requests.show', $it->id) }}"><i class="bi bi-eye"></i> Xem chi tiết</a>
                        @if(($canAdminApprove && $canAdminAction) || ($canAccountingApprove && $canAccAction))
                            <button type="button" class="ego-pr-mobile-process" data-menu-id="egoPrMobileActions{{ $it->id }}"><i class="bi bi-lightning-charge"></i> Xử lý</button>
                        @endif
                    </div>
                </article>
            @empty
                <div class="ego-pr-mobile-empty"><i class="bi bi-inbox"></i><strong>Chưa có đề nghị thanh toán</strong></div>
            @endforelse
        </div>

        <footer class="ego-pr-table-footer">
            <div>
                Hiển thị <strong>{{ number_format((int)($items->firstItem() ?? 0)) }}–{{ number_format((int)($items->lastItem() ?? 0)) }}</strong>
                trên tổng số <strong>{{ number_format((int)($items->total() ?? 0)) }}</strong> phiếu
            </div>

            <div class="ego-pr-pagination-tools">
                <form method="GET" action="{{ route('payment_requests.index') }}" class="ego-pr-per-page-form">
                    @foreach(request()->except(['per_page', 'page']) as $key => $value)
                        @if(!is_array($value))<input type="hidden" name="{{ $key }}" value="{{ $value }}">@endif
                    @endforeach
                    <label for="egoPrPerPage">Số dòng</label>
                    <select name="per_page" id="egoPrPerPage" onchange="this.form.submit()">
                        @foreach([20,50,100] as $size)
                            <option value="{{ $size }}" @selected((int)request('per_page', 20) === $size)>{{ $size }}</option>
                        @endforeach
                    </select>
                </form>
                <div class="ego-pr-pagination">{{ $items->links('pagination::bootstrap-5') }}</div>
            </div>
        </footer>
    </section>
</div>


@if($canBulkApprove)
<div class="modal fade" id="prBulkApproveModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content modal-compact" style="border-radius:18px; overflow:hidden;">
            <form id="prBulkApproveForm" method="POST" action="{{ route('payment_requests.bulk_approve') }}">
                @csrf
                <div id="prBulkApproveInputs"></div>
                <div class="modal-header" style="background:linear-gradient(135deg, rgba(6,182,212,.18), rgba(59,130,246,.10));">
                    <h5 class="modal-title"><i class="bi bi-check2-all me-1"></i> Duyệt nhiều đề nghị thanh toán</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-info mb-3">
                        Bạn đang xử lý <strong id="prBulkModalCount">0</strong> phiếu,
                        tổng giá trị <strong id="prBulkModalAmount">0 đ</strong>.
                    </div>
                    <label class="form-label" for="prBulkNote">Ghi chú chung (không bắt buộc)</label>
                    <textarea name="note" id="prBulkNote" class="form-control" rows="4" maxlength="2000"
                        placeholder="Ghi chú này sẽ được lưu vào lịch sử duyệt của từng phiếu..."></textarea>
                    <div class="bulk-approval-help mt-2">
                        Hệ thống chỉ xử lý phiếu đúng trạng thái và đúng quyền. Mỗi phiếu chỉ tiến một bước duyệt trong lần này.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-soft" data-bs-dismiss="modal"><i class="bi bi-x-lg"></i> Hủy</button>
                    <button type="submit" class="btn-ego" id="prBulkSubmitButton">
                        <i class="bi bi-check-circle"></i> Xác nhận duyệt
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endif


{{-- Modal tạo phiếu --}}
<div
    class="modal fade"
    id="createPRModal"
    tabindex="-1"
    aria-hidden="true"
>
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form
                id="egoPaymentRequestCreateForm"
                method="POST"
                action="{{ route('payment_requests.store') }}"
                enctype="multipart/form-data"
                data-upload-template="{{ url('/payment-requests/__ID__/attachments') }}"
                data-max-files="15"
                data-max-file-size="20971520"
            >
                @csrf

                <div class="modal-header ego-dntt-head">
                    <div class="ego-dntt-head-icon">
                        <i class="bi bi-receipt-cutoff"></i>
                    </div>

                    <div class="ego-dntt-head-copy">
                        <div class="ego-dntt-eyebrow">
                            Tài chính nội bộ
                        </div>

                        <h5 class="modal-title">
                            Tạo đề nghị thanh toán
                        </h5>

                        <div class="ego-dntt-head-desc">
                            Phiếu được lưu nháp để bạn bổ sung và gửi duyệt sau.
                        </div>
                    </div>

                    <button
                        type="button"
                        class="ego-dntt-close"
                        data-bs-dismiss="modal"
                        aria-label="Đóng"
                    >
                        <i class="bi bi-x-lg"></i>
                    </button>
                </div>

                <div class="modal-body">
                    <div class="ego-dntt-notice">
                        <i class="bi bi-info-circle"></i>

                        <span>
                            Bạn có thể tạo phiếu nháp trước. Hãy hoàn thiện thông tin cần thiết trước khi gửi duyệt.
                        </span>
                    </div>

                    <div class="ego-dntt-layout">
                        <section class="ego-dntt-section">
                            <div class="ego-dntt-section-head">
                                <span class="ego-dntt-section-index">
                                    01
                                </span>

                                <div>
                                    <h6 class="ego-dntt-section-title">
                                        Thông tin phiếu
                                    </h6>

                                    <div class="ego-dntt-section-desc">
                                        Phân loại, người nhận và thời hạn dự kiến.
                                    </div>
                                </div>
                            </div>

                            <div class="ego-dntt-section-body">
                                <div class="ego-dntt-grid">
                                    <div class="ego-dntt-field">
                                        <label class="ego-dntt-label">
                                            <span>Công ty</span>
                                            <small class="ego-dntt-optional">Tùy chọn</small>
                                        </label>

                                        <select
                                            name="company"
                                            class="form-select"
                                        >
                                            <option value="">
                                                Theo công ty đang làm việc
                                            </option>

                                            @foreach($companyOptions ?? [] as $c)
                                                <option
                                                    value="{{ $c }}"
                                                    @selected(old('company') === $c)
                                                >
                                                    {{ $c }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>

                                    <div class="ego-dntt-field">
                                        <label class="ego-dntt-label">
                                            <span>Loại phiếu</span>
                                            <small class="ego-dntt-optional">Mặc định DNTT</small>
                                        </label>

                                        <select
                                            name="doc_type"
                                            class="form-select"
                                        >
                                            <option
                                                value="payment_request"
                                                @selected(old('doc_type', 'payment_request') === 'payment_request')
                                            >
                                                Phiếu đề nghị thanh toán
                                            </option>

                                            <option
                                                value="payment_voucher"
                                                @selected(old('doc_type') === 'payment_voucher')
                                            >
                                                Phiếu chi
                                            </option>

                                            <option
                                                value="advance"
                                                @selected(old('doc_type') === 'advance')
                                            >
                                                Phiếu đề nghị tạm ứng
                                            </option>

                                            <option
                                                value="refund_request"
                                                @selected(old('doc_type') === 'refund_request')
                                            >
                                                Đề nghị hoàn tiền
                                            </option>
                                        </select>
                                    </div>

                                    <div class="ego-dntt-field">
                                        <label class="ego-dntt-label">
                                            <span>Người nhận</span>
                                            <small class="ego-dntt-optional">Tùy chọn</small>
                                        </label>

                                        <input
                                            name="receiver_name"
                                            class="form-control"
                                            value="{{ old('receiver_name') }}"
                                            placeholder="Tên cá nhân hoặc đơn vị nhận"
                                        >
                                    </div>

                                    <div class="ego-dntt-field">
                                        <label class="ego-dntt-label">
                                            <span>Đơn vị / Phòng ban</span>
                                            <small class="ego-dntt-optional">Tùy chọn</small>
                                        </label>

                                        <input
                                            name="department"
                                            class="form-control"
                                            value="{{ old('department') }}"
                                            placeholder="VD: Marketing, Kỹ thuật..."
                                        >
                                    </div>

                                    <div class="ego-dntt-field">
                                        <label class="ego-dntt-label">
                                            <span>Số tiền dự kiến</span>
                                            <small class="ego-dntt-optional">Có thể nhập sau</small>
                                        </label>

                                        <input
                                            name="amount"
                                            type="number"
                                            min="0"
                                            inputmode="numeric"
                                            class="form-control"
                                            value="{{ old('amount') }}"
                                            placeholder="0"
                                        >
                                    </div>

                                    <div class="ego-dntt-field">
                                        <label class="ego-dntt-label">
                                            <span>Ngày phải thanh toán</span>
                                            <small class="ego-dntt-optional">Tùy chọn</small>
                                        </label>

                                        <input
                                            type="date"
                                            name="payment_due_date"
                                            class="form-control"
                                            value="{{ old('payment_due_date') }}"
                                        >
                                    </div>
                                </div>
                            </div>
                        </section>

                        <section class="ego-dntt-section">
                            <div class="ego-dntt-section-head">
                                <span class="ego-dntt-section-index">
                                    02
                                </span>

                                <div>
                                    <h6 class="ego-dntt-section-title">
                                        Nội dung & chứng từ
                                    </h6>

                                    <div class="ego-dntt-section-desc">
                                        Mô tả khoản chi, chuyển khoản và file liên quan.
                                    </div>
                                </div>
                            </div>

                            <div class="ego-dntt-section-body">
                                <div class="ego-dntt-grid">
                                    <div class="ego-dntt-field is-wide">
                                        <label class="ego-dntt-label">
                                            <span>Nội dung thanh toán</span>
                                            <small class="ego-dntt-optional">Tùy chọn</small>
                                        </label>

                                        <input
                                            name="payment_content"
                                            class="form-control"
                                            value="{{ old('payment_content') }}"
                                            placeholder="VD: Thanh toán đợt 1, tạm ứng vật tư..."
                                        >
                                    </div>

                                    <div class="ego-dntt-field is-wide">
                                        <label class="ego-dntt-label">
                                            <span>Lý do / Diễn giải</span>
                                            <small class="ego-dntt-optional">Tùy chọn</small>
                                        </label>

                                        <textarea
                                            name="reason"
                                            id="reason_editor"
                                            class="form-control"
                                            rows="4"
                                            placeholder="Mô tả mục đích và nội dung khoản thanh toán..."
                                        >{{ old('reason') }}</textarea>
                                    </div>

                                    <div class="ego-dntt-field is-wide">
                                        <label class="ego-dntt-label">
                                            <span>Thông tin chuyển khoản</span>
                                            <small class="ego-dntt-optional">Tùy chọn</small>
                                        </label>

                                        <input
                                            name="bank_info"
                                            class="form-control"
                                            value="{{ old('bank_info') }}"
                                            placeholder="Ngân hàng · Số tài khoản · Chủ tài khoản"
                                        >
                                    </div>

                                    <div class="ego-dntt-field is-wide">
                                        <label class="ego-dntt-label" for="egoPaymentRequestFiles">
                                            <span>Chứng từ đính kèm</span>
                                            <small class="ego-dntt-optional">Tối đa 15 file</small>
                                        </label>

                                        <div
                                            class="ego-dntt-upload"
                                            id="egoDnttUpload"
                                        >
                                            <input
                                                class="ego-dntt-file-input"
                                                id="egoPaymentRequestFiles"
                                                type="file"
                                                name="attachments[]"
                                                multiple
                                                accept=".jpg,.jpeg,.png,.webp,.pdf,.doc,.docx,.xls,.xlsx,image/jpeg,image/png,image/webp,application/pdf"
                                            >

                                            <label
                                                class="ego-dntt-upload-picker"
                                                for="egoPaymentRequestFiles"
                                            >
                                                <span class="ego-dntt-upload-icon">
                                                    <i class="bi bi-cloud-arrow-up"></i>
                                                </span>

                                                <span class="ego-dntt-upload-copy">
                                                    <strong>Chọn nhiều chứng từ</strong>
                                                    <small>Ảnh, PDF, Word hoặc Excel · tối đa 20MB/file</small>
                                                </span>

                                                <span class="ego-dntt-upload-button">
                                                    Chọn file
                                                </span>
                                            </label>

                                            <div
                                                class="ego-dntt-upload-summary"
                                                id="egoDnttUploadSummary"
                                                hidden
                                            >
                                                <span>
                                                    <i class="bi bi-paperclip"></i>
                                                    <strong id="egoDnttUploadCount">0 file</strong>
                                                </span>
                                                <span id="egoDnttUploadSize">0 B</span>
                                            </div>

                                            <div
                                                class="ego-dntt-file-list"
                                                id="egoDnttFileList"
                                                role="list"
                                                aria-live="polite"
                                            ></div>

                                            <div
                                                class="ego-dntt-upload-actions"
                                                id="egoDnttUploadActions"
                                                hidden
                                            >
                                                <button
                                                    type="button"
                                                    class="ego-dntt-file-action"
                                                    id="egoDnttAddFiles"
                                                >
                                                    <i class="bi bi-plus-circle"></i>
                                                    Thêm file khác
                                                </button>

                                                <button
                                                    type="button"
                                                    class="ego-dntt-file-action is-danger"
                                                    id="egoDnttClearFiles"
                                                >
                                                    <i class="bi bi-trash3"></i>
                                                    Xóa tất cả
                                                </button>
                                            </div>

                                            <div
                                                class="ego-dntt-upload-message"
                                                id="egoDnttUploadMessage"
                                                role="alert"
                                                hidden
                                            ></div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </section>
                    </div>
                </div>

                <div class="modal-footer">
                    <div class="ego-dntt-footer-note">
                        <i class="bi bi-shield-check"></i>
                        Phiếu được lưu ở trạng thái nháp
                    </div>

                    <button
                        type="button"
                        class="ego-dntt-cancel"
                        data-bs-dismiss="modal"
                    >
                        <i class="bi bi-x-lg"></i>
                        Đóng
                    </button>

                    <button
                        class="ego-dntt-save"
                        type="submit"
                        id="egoPaymentRequestCreateSubmit"
                    >
                        <i class="bi bi-check2-circle"></i>
                        Tạo phiếu nháp
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.ckeditor.com/4.25.1-lts/standard/ckeditor.js"></script>
<script>
(function () {
    function initPaymentReasonEditor() {
        var textarea = document.getElementById('reason_editor');

        if (!textarea || !window.CKEDITOR) {
            return;
        }

        var editor = CKEDITOR.instances.reason_editor;

        if (!editor) {
            editor = CKEDITOR.replace('reason_editor', {
                height: 112,
                toolbar: [
                    { name: 'basicstyles', items: ['Bold', 'Italic', 'Underline'] },
                    { name: 'paragraph', items: ['BulletedList', 'NumberedList'] },
                    { name: 'links', items: ['Link', 'Unlink'] }
                ],
                removeButtons: 'Image,Flash,Smiley,SpecialChar,About',
                resize_enabled: false
            });
        }

        function syncEditorContent() {
            try {
                editor.updateElement();
            } catch (error) {
                console.error('Không đồng bộ được nội dung lý do:', error);
            }
        }

        editor.on('instanceReady', syncEditorContent);
        editor.on('change', syncEditorContent);
        editor.on('blur', syncEditorContent);
    }

    if (document.readyState === 'loading') {
        document.addEventListener(
            'DOMContentLoaded',
            initPaymentReasonEditor
        );
    } else {
        initPaymentReasonEditor();
    }
})();
</script>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const preset = document.getElementById('date_preset');
    const from = document.getElementById('date_from');
    const to = document.getElementById('date_to');

    if (from) {
        from.addEventListener('change', function () {
            if (preset) preset.value = 'custom';
        });
    }

    if (to) {
        to.addEventListener('change', function () {
            if (preset) preset.value = 'custom';
        });
    }
});
</script>






@if($canBulkApprove)
<script>
document.addEventListener('DOMContentLoaded', function () {
    const rowCheckboxes = Array.from(document.querySelectorAll('.js-pr-bulk-checkbox'));
    const selectAllDesktop = document.getElementById('prBulkSelectAllDesktop');
    const selectAllMobile = document.getElementById('prBulkSelectAllMobile');
    const clearButton = document.getElementById('prBulkClear');
    const openButton = document.getElementById('prBulkOpenApprove');
    const countText = document.getElementById('prBulkSelectedCount');
    const amountText = document.getElementById('prBulkSelectedAmount');
    const modalCount = document.getElementById('prBulkModalCount');
    const modalAmount = document.getElementById('prBulkModalAmount');
    const modalElement = document.getElementById('prBulkApproveModal');
    const bulkForm = document.getElementById('prBulkApproveForm');
    const inputsBox = document.getElementById('prBulkApproveInputs');
    const submitButton = document.getElementById('prBulkSubmitButton');

    function uniqueRows() {
        const map = new Map();
        rowCheckboxes.forEach(function (box) {
            const id = String(box.dataset.id || box.value || '');
            if (!id || map.has(id)) return;
            map.set(id, {
                id: id,
                amount: Number(box.dataset.amount || 0),
                checked: rowCheckboxes.some(function (candidate) {
                    return String(candidate.dataset.id || candidate.value || '') === id && candidate.checked;
                })
            });
        });
        return Array.from(map.values());
    }

    function selectedRows() { return uniqueRows().filter(function (row) { return row.checked; }); }
    function formatMoney(value) { return new Intl.NumberFormat('vi-VN').format(Number(value || 0)) + ' đ'; }

    function setRowChecked(id, checked) {
        rowCheckboxes.forEach(function (box) {
            if (String(box.dataset.id || box.value || '') === String(id)) box.checked = checked;
        });
    }

    function rebuildHiddenInputs(rows) {
        if (!inputsBox) return;
        inputsBox.innerHTML = '';
        rows.forEach(function (row) {
            const input = document.createElement('input');
            input.type = 'hidden'; input.name = 'ids[]'; input.value = row.id;
            inputsBox.appendChild(input);
        });
    }

    function refresh() {
        const allRows = uniqueRows();
        const selected = allRows.filter(function (row) { return row.checked; });
        const total = selected.reduce(function (sum, row) { return sum + row.amount; }, 0);
        const hasSelection = selected.length > 0;
        const allSelected = allRows.length > 0 && selected.length === allRows.length;
        const partlySelected = selected.length > 0 && selected.length < allRows.length;

        if (countText) countText.textContent = String(selected.length);
        if (amountText) amountText.textContent = formatMoney(total);
        if (modalCount) modalCount.textContent = String(selected.length);
        if (modalAmount) modalAmount.textContent = formatMoney(total);
        if (clearButton) clearButton.disabled = !hasSelection;
        if (openButton) openButton.disabled = !hasSelection;

        [selectAllDesktop, selectAllMobile].forEach(function (box) {
            if (!box) return;
            box.checked = allSelected; box.indeterminate = partlySelected; box.disabled = allRows.length === 0;
        });
        rebuildHiddenInputs(selected);
    }

    rowCheckboxes.forEach(function (box) {
        box.addEventListener('change', function () {
            setRowChecked(box.dataset.id || box.value, box.checked);
            refresh();
        });
    });

    [selectAllDesktop, selectAllMobile].forEach(function (box) {
        if (!box) return;
        box.addEventListener('change', function () {
            rowCheckboxes.forEach(function (rowBox) { rowBox.checked = box.checked; });
            refresh();
        });
    });

    if (clearButton) clearButton.addEventListener('click', function () {
        rowCheckboxes.forEach(function (box) { box.checked = false; }); refresh();
    });

    if (openButton) openButton.addEventListener('click', function () {
        const selected = selectedRows();
        if (!selected.length) { window.alert('Vui lòng chọn ít nhất một phiếu.'); return; }
        rebuildHiddenInputs(selected);
        if (modalElement && window.bootstrap && bootstrap.Modal) {
            bootstrap.Modal.getOrCreateInstance(modalElement).show();
        } else if (bulkForm && window.confirm('Duyệt ' + selected.length + ' phiếu đã chọn?')) {
            bulkForm.submit();
        }
    });

    if (bulkForm) bulkForm.addEventListener('submit', function (event) {
        const selected = selectedRows();
        if (!selected.length) { event.preventDefault(); window.alert('Danh sách chọn đang trống.'); return; }
        rebuildHiddenInputs(selected);
        if (submitButton) {
            submitButton.disabled = true;
            submitButton.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Đang xử lý...';
        }
    });

    refresh();
});
</script>
@endif

{{-- EGO_PAYMENT_INDEX_CLEAN_FIX_START --}}
@php
    $egoPaymentDueMap = collect();

    if (isset($items)) {
        $egoPaymentDueMap = $items->getCollection()->mapWithKeys(function ($x) {
            $v = '-';

            if (!empty($x->payment_due_date)) {
                try {
                    $v = \Illuminate\Support\Carbon::parse($x->payment_due_date)->format('d/m/Y');
                } catch (\Throwable $e) {
                    $v = (string) $x->payment_due_date;
                }
            }

            return [$x->code => $v];
        });
    }
@endphp

<script>
(function () {
    var dueMap = @json($egoPaymentDueMap);

    function text(el) {
        return (el && el.textContent ? el.textContent : '').replace(/\s+/g, ' ').trim();
    }

    function lower(el) {
        return text(el).toLowerCase();
    }

    function addClass(el, className) {
        if (el && !el.classList.contains(className)) {
            el.classList.add(className);
        }
    }

    function insertAfter(parent, node, ref) {
        if (!parent || !node || !ref) return;
        parent.insertBefore(node, ref.nextSibling);
    }

    function findHeaderIndex(headers, keyword) {
        keyword = keyword.toLowerCase();

        for (var i = 0; i < headers.length; i++) {
            if (lower(headers[i]).indexOf(keyword) !== -1) return i;
        }

        return -1;
    }

    function getRowCode(row, codeIndex) {
        var cell = row.children[codeIndex];
        return cell ? text(cell) : '';
    }

    function fixDueColumn() {
        document.querySelectorAll('table').forEach(function (table) {
            var headerRow = table.querySelector('thead tr');
            var bodyRows = table.querySelectorAll('tbody tr');

            if (!headerRow || !bodyRows.length) return;

            var headers = Array.prototype.slice.call(headerRow.children);

            var codeIndex = findHeaderIndex(headers, 'mã');
            var createdIndex = findHeaderIndex(headers, 'ngày tạo');

            if (codeIndex < 0 || createdIndex < 0) return;

            addClass(table, 'ego-pay-clean-table');

            if (table.parentElement) {
                addClass(table.parentElement, 'ego-pay-clean-wrap');
            }

            var dueIndex = -1;
            headers.forEach(function (th, index) {
                var t = lower(th);
                if (t.indexOf('ngày phải') !== -1 || t.indexOf('ngày tt') !== -1) {
                    dueIndex = index;
                }
            });

            var createdTh = headerRow.children[createdIndex];
            var dueTh = null;

            if (dueIndex >= 0) {
                dueTh = headerRow.children[dueIndex];
                dueTh.textContent = 'Hạn thanh toán';

                if (createdTh && dueTh !== createdTh.nextElementSibling) {
                    insertAfter(headerRow, dueTh, createdTh);
                }

                bodyRows.forEach(function (row) {
                    var cells = Array.prototype.slice.call(row.children);
                    var dueCell = cells[dueIndex];
                    var createdCell = cells[createdIndex];

                    if (dueCell && createdCell && dueCell !== createdCell.nextElementSibling) {
                        insertAfter(row, dueCell, createdCell);
                    }
                });
            } else {
                dueTh = document.createElement('th');
                dueTh.textContent = 'Hạn thanh toán';

                insertAfter(headerRow, dueTh, createdTh);

                bodyRows.forEach(function (row) {
                    var cells = Array.prototype.slice.call(row.children);
                    var code = getRowCode(row, codeIndex);
                    var td = document.createElement('td');

                    td.textContent = dueMap[code] || '-';
                    insertAfter(row, td, cells[createdIndex]);
                });
            }

            headers = Array.prototype.slice.call(headerRow.children);

            var finalCodeIndex = findHeaderIndex(headers, 'mã');
            var finalAmountIndex = findHeaderIndex(headers, 'số tiền');
            var finalCreatedIndex = findHeaderIndex(headers, 'ngày tạo');
            var finalDueIndex = findHeaderIndex(headers, 'ngày phải');

            if (finalDueIndex >= 0) addClass(headerRow.children[finalDueIndex], 'ego-due-th');

            bodyRows.forEach(function (row) {
                var cells = row.children;

                if (finalCodeIndex >= 0 && cells[finalCodeIndex]) addClass(cells[finalCodeIndex], 'ego-code-td');
                if (finalAmountIndex >= 0 && cells[finalAmountIndex]) addClass(cells[finalAmountIndex], 'ego-money-td');
                if (finalCreatedIndex >= 0 && cells[finalCreatedIndex]) addClass(cells[finalCreatedIndex], 'ego-created-td');
                if (finalDueIndex >= 0 && cells[finalDueIndex]) addClass(cells[finalDueIndex], 'ego-due-td');
            });
        });
    }

    function addDueDateToForms() {
        document.querySelectorAll('form').forEach(function (form) {
            if (form.querySelector('[name="payment_due_date"]')) return;

            var amountInput = form.querySelector('[name="amount"]');
            if (!amountInput) return;

            var amountBox = amountInput.closest('.col-md-6, .col-md-4, .col-lg-6, .col-lg-4, .form-group, div') || amountInput.parentElement;
            if (!amountBox || !amountBox.parentNode) return;

            var box = document.createElement('div');
            box.className = (amountBox.className || 'form-group') + ' ego-payment-due-field';
            box.innerHTML =
                '<label class="form-label">Ngày phải thanh toán</label>' +
                '<input type="date" name="payment_due_date" class="form-control" value="{{ old('payment_due_date') }}">';

            amountBox.parentNode.insertBefore(box, amountBox.nextSibling);
        });
    }

    function enhanceButtons() {
        document.querySelectorAll('a, button').forEach(function (el) {
            var t = lower(el);

            if (
                t.indexOf('tạo phiếu') !== -1 ||
                t.indexOf('xuất') !== -1 ||
                t.indexOf('lọc') !== -1 ||
                t.indexOf('sửa') !== -1 ||
                t.indexOf('xóa') !== -1 ||
                t.indexOf('xoá') !== -1 ||
                t.indexOf('gửi duyệt') !== -1 ||
                t === 'pdf'
            ) {
                addClass(el, 'ego-pay-clean-btn');
            }
        });
    }

    function run() {
        fixDueColumn();
        addDueDateToForms();
        enhanceButtons();
    }

    document.addEventListener('DOMContentLoaded', run);
    setTimeout(run, 300);
})();
</script>


{{-- EGO_PAYMENT_REJECT_MODAL_START --}}
<div class="ego-pr-full-backdrop" id="egoRejectFullModal" aria-hidden="true">
    <div class="ego-pr-full-modal" role="dialog" aria-modal="true" aria-labelledby="egoRejectFullTitle">
        <div class="ego-pr-full-head">
            <div>
                <h3 class="ego-pr-full-title" id="egoRejectFullTitle">Thông tin phiếu cần từ chối</h3>
                <div class="ego-pr-full-desc">Kiểm tra thông tin thanh toán, sau đó nhập lý do từ chối.</div>
            </div>
            <button type="button" class="ego-pr-full-close" data-ego-pr-full-close aria-label="Đóng">&times;</button>
        </div>

        <div class="ego-pr-full-body">
            <div id="egoRejectFullInfo">
                <div class="ego-pr-loading">Đang tải bảng thông tin thanh toán...</div>
            </div>

            <section class="payx-action">
                <h3 class="payx-action-title" id="egoRejectFullActionTitle">Quản lý tài chính xử lý</h3>
                <div class="payx-action-desc">Bắt buộc nhập lý do từ chối, không nhập sẽ không gửi được.</div>

                <div class="payx-action-grid">
                    <form id="egoRejectFullForm" method="POST" action="" class="payx-action-form">
                        @csrf
                        <h4>Từ chối phiếu</h4>
                        <textarea id="egoRejectFullNote" name="note" required minlength="2" maxlength="2000" placeholder="Lý do từ chối..."></textarea>
                        <div class="ego-pr-full-error" id="egoRejectFullError">Vui lòng nhập lý do từ chối trước khi xác nhận.</div>

                        <div class="ego-pr-full-actions">
                            <button type="button" class="payx-btn ego-pr-cancel" data-ego-pr-full-close>Hủy</button>
                            <button type="submit" class="payx-btn red" id="egoRejectFullSubmit" disabled>Từ chối</button>
                        </div>
                    </form>
                </div>
            </section>
        </div>
    </div>
</div>

<script>
(function(){
    function ready(fn){
        document.readyState !== 'loading' ? fn() : document.addEventListener('DOMContentLoaded', fn);
    }

    ready(function(){
        var modal = document.getElementById('egoRejectFullModal');
        var form = document.getElementById('egoRejectFullForm');
        var note = document.getElementById('egoRejectFullNote');
        var submit = document.getElementById('egoRejectFullSubmit');
        var error = document.getElementById('egoRejectFullError');
        var infoBox = document.getElementById('egoRejectFullInfo');
        var actionTitle = document.getElementById('egoRejectFullActionTitle');

        if(!modal || !form || !note || !submit || !infoBox) return;

        function clean(v){
            return (v || '').replace(/\s+/g, ' ').trim();
        }

        function esc(v){
            return (v || '').replace(/[&<>"']/g, function(s){
                return ({
                    '&':'&amp;',
                    '<':'&lt;',
                    '>':'&gt;',
                    '"':'&quot;',
                    "'":'&#039;'
                })[s];
            });
        }

        function refresh(){
            var ok = clean(note.value).length >= 2;
            submit.disabled = !ok;

            if(ok && error){
                error.classList.remove('show');
            }
        }

        function fallbackInfoFromRow(srcForm){
            var wrap = srcForm.closest('tr') || srcForm.closest('.m-card');
            var code = '-';
            var receiver = '-';
            var amount = '-';
            var status = '-';
            var created = '-';
            var company = '-';
            var creator = '-';

            if(wrap && wrap.tagName && wrap.tagName.toLowerCase() === 'tr'){
                var cells = wrap.querySelectorAll('td');
                code = clean(cells[1] ? cells[1].innerText : '-');
                receiver = clean(cells[2] ? cells[2].innerText : '-');
                amount = clean(cells[3] ? cells[3].innerText : '-');
                status = clean(cells[4] ? cells[4].innerText : '-');
                created = clean(cells[5] ? cells[5].innerText : '-');
                company = clean(cells[7] ? cells[7].innerText : '-');
                creator = clean(cells[8] ? cells[8].innerText : '-');
            } else if(wrap) {
                var codeEl = wrap.querySelector('.m-code');
                code = clean(codeEl ? codeEl.innerText : '-');

                wrap.querySelectorAll('.m-row').forEach(function(row){
                    var k = clean(row.querySelector('.m-k') ? row.querySelector('.m-k').innerText : '');
                    var v = clean(row.querySelector('.m-v') ? row.querySelector('.m-v').innerText : '');

                    if(k.indexOf('Người nhận') !== -1) receiver = v;
                    if(k.indexOf('Số tiền') !== -1) amount = v;
                    if(k.indexOf('Trạng thái') !== -1) status = v;
                    if(k.indexOf('Công ty') !== -1) company = v;
                    if(k.indexOf('Người tạo') !== -1) creator = v;
                });

                var dateEl = wrap.querySelector('.m-date');
                created = clean(dateEl ? dateEl.innerText : '-');
            }

            return '' +
                '<section class="payx-card">' +
                    '<div class="payx-card-head">' +
                        '<div>' +
                            '<h2 class="payx-card-title">Thông tin thanh toán</h2>' +
                            '<div class="payx-card-desc">Thông tin lấy nhanh từ danh sách phiếu.</div>' +
                        '</div>' +
                        '<span class="payx-chip">' + esc(status) + '</span>' +
                    '</div>' +
                    '<div class="payx-card-body">' +
                        '<div class="payx-info-grid">' +
                            '<div class="payx-info"><span class="payx-label">Mã phiếu</span><div class="payx-value">' + esc(code) + '</div></div>' +
                            '<div class="payx-info"><span class="payx-label">Người nhận</span><div class="payx-value">' + esc(receiver) + '</div></div>' +
                            '<div class="payx-info"><span class="payx-label">Số tiền</span><div class="payx-value money">' + esc(amount) + '</div></div>' +
                            '<div class="payx-info"><span class="payx-label">Ngày tạo</span><div class="payx-value">' + esc(created) + '</div></div>' +
                            '<div class="payx-info"><span class="payx-label">Công ty</span><div class="payx-value">' + esc(company) + '</div></div>' +
                            '<div class="payx-info"><span class="payx-label">Người tạo</span><div class="payx-value">' + esc(creator) + '</div></div>' +
                        '</div>' +
                    '</div>' +
                '</section>';
        }

        function findShowUrl(srcForm){
            var wrap = srcForm.closest('tr') || srcForm.closest('.m-card');

            if(wrap){
                var link = wrap.querySelector('a.code-link, a.m-code, a[href*="/payment-requests/"]');

                if(link && link.href){
                    return link.href;
                }
            }

            var action = srcForm.getAttribute('action') || '';
            var m = action.match(/\/payment-requests\/(\d+)\//);

            if(m && m[1]){
                return window.location.origin + '/payment-requests/' + m[1];
            }

            return '';
        }

                function extractPaymentInfo(html){
            var doc = new DOMParser().parseFromString(html, 'text/html');
            var cards = Array.prototype.slice.call(doc.querySelectorAll('.payx-card'));

            var selectedCards = cards.filter(function(card){
                var text = clean(card.innerText || '');

                return text.indexOf('Thông tin thanh toán') !== -1
                    || text.indexOf('Chứng từ đính kèm') !== -1;
            });

            if(!selectedCards.length){
                return '';
            }

            selectedCards.forEach(function(card){
                card.querySelectorAll('script, style').forEach(function(el){
                    el.remove();
                });

                card.classList.remove('payx-animate');
                card.removeAttribute('style');
            });

            return selectedCards.map(function(card){
                return card.outerHTML;
            }).join('');
        }

        function loadInfo(showUrl, srcForm){
            infoBox.innerHTML = '<div class="ego-pr-loading">Đang tải bảng thông tin thanh toán...</div>';

            if(!showUrl){
                infoBox.innerHTML = fallbackInfoFromRow(srcForm);
                return;
            }

            fetch(showUrl, {
                credentials: 'same-origin',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
            .then(function(res){
                if(!res.ok) throw new Error('Load failed');
                return res.text();
            })
            .then(function(html){
                var extracted = extractPaymentInfo(html);

                if(extracted){
                    infoBox.innerHTML = extracted;
                } else {
                    infoBox.innerHTML = fallbackInfoFromRow(srcForm);
                }
            })
            .catch(function(){
                infoBox.innerHTML = fallbackInfoFromRow(srcForm);
            });
        }

        function openModal(srcForm){
            var action = srcForm.getAttribute('action') || '';
            var isAcc = action.indexOf('acc-reject') !== -1;

            form.setAttribute('action', action);
            actionTitle.textContent = isAcc ? 'Kế toán xử lý' : 'Quản lý tài chính xử lý';

            note.value = '';
            refresh();

            modal.classList.add('show');
            modal.setAttribute('aria-hidden', 'false');

            loadInfo(findShowUrl(srcForm), srcForm);

            setTimeout(function(){
                note.focus();
            }, 120);
        }

        function closeModal(){
            modal.classList.remove('show');
            modal.setAttribute('aria-hidden', 'true');
            form.setAttribute('action', '');
            note.value = '';
            refresh();
        }

        function bindRejectForms(){
            document.querySelectorAll('form[action*="admin-reject"], form[action*="acc-reject"]').forEach(function(srcForm){
                if(srcForm.id === 'egoRejectFullForm') return;
                if(srcForm.dataset.egoRejectFullBound === '1') return;

                srcForm.dataset.egoRejectFullBound = '1';
                srcForm.onsubmit = null;
                srcForm.removeAttribute('onsubmit');

                srcForm.addEventListener('submit', function(e){
                    e.preventDefault();
                    openModal(srcForm);
                });
            });
        }

        note.addEventListener('input', refresh);

        form.addEventListener('submit', function(e){
            if(clean(note.value).length < 2){
                e.preventDefault();

                if(error){
                    error.classList.add('show');
                }

                submit.disabled = true;
                note.focus();
            }
        });

        document.querySelectorAll('[data-ego-pr-full-close]').forEach(function(btn){
            btn.addEventListener('click', closeModal);
        });

        modal.addEventListener('click', function(e){
            if(e.target === modal){
                closeModal();
            }
        });

        document.addEventListener('keydown', function(e){
            if(e.key === 'Escape' && modal.classList.contains('show')){
                closeModal();
            }
        });

        bindRejectForms();
        setTimeout(bindRejectForms, 300);
        setTimeout(bindRejectForms, 900);
    });
})();
</script>
{{-- EGO_PAYMENT_REJECT_MODAL_END --}}
{{-- EGO_PAYMENT_INDEX_CLEAN_FIX_END --}}

@endsection
@includeIf('payment-requests._buibichthao_actions')


{{-- EGO_PR_ACTIONS_INLINE_START --}}
<script>
(function () {
    function isActionCell(cell) {
        if (!cell) return false;

        var text = (cell.innerText || '').toLowerCase();

        return text.indexOf('pdf') !== -1
            || text.indexOf('sửa') !== -1
            || text.indexOf('xóa') !== -1
            || cell.querySelector('a[href*="/pdf"], a[href*="pdf"], a[href*="/edit"], form, button');
    }

    function normalizeActionCell(cell) {
        if (!cell || !isActionCell(cell)) return;

        if (cell.dataset.egoPrActionsInline === '1') return;
        cell.dataset.egoPrActionsInline = '1';

        var controls = Array.prototype.slice.call(cell.children).filter(function (el) {
            var tag = el.tagName ? el.tagName.toLowerCase() : '';
            return ['a', 'button', 'form', 'div', 'span'].indexOf(tag) !== -1;
        });

        if (!controls.length) return;

        var wrap = document.createElement('div');
        wrap.className = 'ego-pr-actions-inline';
        wrap.style.display = 'inline-flex';
        wrap.style.flexDirection = 'row';
        wrap.style.flexWrap = 'nowrap';
        wrap.style.alignItems = 'center';
        wrap.style.gap = '6px';

        controls.forEach(function (el) {
            wrap.appendChild(el);
        });

        cell.appendChild(wrap);

        /*
         * Xóa nút duplicate cùng loại trong cùng 1 dòng:
         * giữ PDF đầu tiên, Sửa đầu tiên, Xóa đầu tiên.
         */
        var seen = {};

        Array.prototype.slice.call(wrap.querySelectorAll('a, button')).forEach(function (btn) {
            var text = (btn.innerText || btn.textContent || '').trim().toLowerCase();
            var key = null;

            if (text.indexOf('pdf') !== -1) key = 'pdf';
            else if (text === 'sửa' || text.indexOf('sửa') !== -1) key = 'edit';
            else if (text === 'xóa' || text.indexOf('xóa') !== -1) key = 'delete';

            if (!key) return;

            if (seen[key]) {
                var holder = btn.closest('form') || btn.closest('a') || btn;
                holder.remove();
                return;
            }

            seen[key] = true;
        });
    }

    function run() {
        document.querySelectorAll('table tbody tr').forEach(function (row) {
            var cells = row.querySelectorAll('td');
            if (!cells.length) return;

            normalizeActionCell(cells[cells.length - 1]);
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        run();
        setTimeout(run, 100);
        setTimeout(run, 400);
    });
})();
</script>
{{-- EGO_PR_ACTIONS_INLINE_END --}}

{{-- EGO_HR_REFUND_SELECT_FIX_START --}}
<script>
(function(){
    function ready(fn){
        document.readyState !== 'loading' ? fn() : document.addEventListener('DOMContentLoaded', fn);
    }

    ready(function(){
        document.querySelectorAll('form[action*="payment-requests"]').forEach(function(form){
            form.addEventListener('submit', function(){
                var select = form.querySelector('select[name="doc_type"]');
                if(!select) return;

                var text = (select.options[select.selectedIndex] ? select.options[select.selectedIndex].text : '').toLowerCase();
                if(text.indexOf('hoàn tiền') !== -1 || text.indexOf('hoan tien') !== -1){
                    select.value = 'refund_request';
                }
            });
        });
    });
})();
</script>
{{-- EGO_HR_REFUND_SELECT_FIX_END --}}
@if($errors->any() && (old('receiver_name') || old('amount') || old('bank_info') || old('payment_content')))
<script>
document.addEventListener('DOMContentLoaded', function () {
    var modalEl = document.getElementById('createPRModal');
    if (modalEl && window.bootstrap && bootstrap.Modal) {
        bootstrap.Modal.getOrCreateInstance(modalEl).show();
    }
});
</script>
@endif


{{-- EGO_DNTT_MULTI_FILE_QUEUE_V3_START --}}
<script>
(function () {
    'use strict';

    function ready(callback) {
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', callback);
        } else {
            callback();
        }
    }

    function formatBytes(bytes) {
        var value = Number(bytes || 0);
        var units = ['B', 'KB', 'MB', 'GB'];
        var unitIndex = 0;

        while (value >= 1024 && unitIndex < units.length - 1) {
            value /= 1024;
            unitIndex++;
        }

        return (unitIndex === 0 ? value : value.toFixed(value >= 10 ? 1 : 2)) + ' ' + units[unitIndex];
    }

    function fileKey(file) {
        return [
            String(file.name || '').toLowerCase(),
            Number(file.size || 0),
            Number(file.lastModified || 0)
        ].join('::');
    }

    function extensionOf(fileName) {
        var parts = String(fileName || '').toLowerCase().split('.');
        return parts.length > 1 ? parts.pop() : '';
    }

    function fileIconClass(file) {
        var extension = extensionOf(file.name);

        if (['jpg', 'jpeg', 'png', 'webp'].indexOf(extension) !== -1) {
            return 'bi-file-earmark-image';
        }

        if (extension === 'pdf') {
            return 'bi-file-earmark-pdf';
        }

        if (['xls', 'xlsx'].indexOf(extension) !== -1) {
            return 'bi-file-earmark-spreadsheet';
        }

        return 'bi-file-earmark-text';
    }

    ready(function () {
        var form = document.getElementById('egoPaymentRequestCreateForm');
        var input = document.getElementById('egoPaymentRequestFiles');
        var upload = document.getElementById('egoDnttUpload');
        var list = document.getElementById('egoDnttFileList');
        var summary = document.getElementById('egoDnttUploadSummary');
        var countNode = document.getElementById('egoDnttUploadCount');
        var sizeNode = document.getElementById('egoDnttUploadSize');
        var actions = document.getElementById('egoDnttUploadActions');
        var addButton = document.getElementById('egoDnttAddFiles');
        var clearButton = document.getElementById('egoDnttClearFiles');
        var message = document.getElementById('egoDnttUploadMessage');
        var modal = document.getElementById('createPRModal');

        if (!form || !input || !upload || !list) {
            return;
        }

        var maximumFiles = Number(form.dataset.maxFiles || 15);
        var maximumFileSize = Number(form.dataset.maxFileSize || 20971520);
        var allowedExtensions = [
            'jpg', 'jpeg', 'png', 'webp', 'pdf', 'doc', 'docx', 'xls', 'xlsx'
        ];
        var selectedFiles = [];

        function showMessage(text, type) {
            if (!message) {
                return;
            }

            message.hidden = !text;
            message.textContent = text || '';
            message.classList.toggle('is-error', type === 'error');
            message.classList.toggle('is-info', type !== 'error');
        }

        function syncInputFiles() {
            if (typeof DataTransfer === 'undefined') {
                return;
            }

            var transfer = new DataTransfer();

            selectedFiles.forEach(function (file) {
                transfer.items.add(file);
            });

            input.files = transfer.files;
        }

        function renderFiles() {
            list.innerHTML = '';

            selectedFiles.forEach(function (file, index) {
                var item = document.createElement('div');
                var icon = document.createElement('span');
                var copy = document.createElement('span');
                var name = document.createElement('strong');
                var meta = document.createElement('small');
                var remove = document.createElement('button');

                item.className = 'ego-dntt-file-item';
                item.setAttribute('role', 'listitem');

                icon.className = 'ego-dntt-file-type';
                icon.innerHTML = '<i class="bi ' + fileIconClass(file) + '"></i>';

                copy.className = 'ego-dntt-file-copy';
                name.textContent = file.name;
                meta.textContent = formatBytes(file.size);
                copy.appendChild(name);
                copy.appendChild(meta);

                remove.type = 'button';
                remove.className = 'ego-dntt-file-remove';
                remove.setAttribute('aria-label', 'Xóa file ' + file.name);
                remove.dataset.index = String(index);
                remove.innerHTML = '<i class="bi bi-x-lg"></i>';

                item.appendChild(icon);
                item.appendChild(copy);
                item.appendChild(remove);
                list.appendChild(item);
            });

            var totalSize = selectedFiles.reduce(function (sum, file) {
                return sum + Number(file.size || 0);
            }, 0);
            var hasFiles = selectedFiles.length > 0;

            if (summary) {
                summary.hidden = !hasFiles;
            }

            if (actions) {
                actions.hidden = !hasFiles;
            }

            if (countNode) {
                countNode.textContent = selectedFiles.length + ' file';
            }

            if (sizeNode) {
                sizeNode.textContent = formatBytes(totalSize);
            }

            upload.classList.toggle('has-files', hasFiles);
            syncInputFiles();
        }

        function addFiles(files) {
            var incoming = Array.from(files || []);

            if (!incoming.length) {
                return;
            }

            var existingKeys = new Set(selectedFiles.map(fileKey));
            var errors = [];
            var added = 0;

            incoming.forEach(function (file) {
                var extension = extensionOf(file.name);
                var key = fileKey(file);

                if (selectedFiles.length >= maximumFiles) {
                    errors.push('Chỉ được chọn tối đa ' + maximumFiles + ' file.');
                    return;
                }

                if (existingKeys.has(key)) {
                    errors.push('Đã bỏ qua file trùng: ' + file.name);
                    return;
                }

                if (allowedExtensions.indexOf(extension) === -1) {
                    errors.push('Sai định dạng: ' + file.name);
                    return;
                }

                if (Number(file.size || 0) <= 0) {
                    errors.push('File không có dữ liệu: ' + file.name);
                    return;
                }

                if (Number(file.size || 0) > maximumFileSize) {
                    errors.push('Vượt quá 20MB: ' + file.name);
                    return;
                }

                selectedFiles.push(file);
                existingKeys.add(key);
                added++;
            });

            renderFiles();

            if (errors.length) {
                showMessage(errors.join(' · '), 'error');
            } else if (added > 0) {
                showMessage('Đã thêm ' + added + ' file vào phiếu.', 'info');
            }

            input.value = '';
            syncInputFiles();
        }

        input.addEventListener('change', function () {
            addFiles(input.files);
        });

        list.addEventListener('click', function (event) {
            var button = event.target.closest('.ego-dntt-file-remove');

            if (!button) {
                return;
            }

            var index = Number(button.dataset.index);

            if (!Number.isInteger(index) || index < 0 || index >= selectedFiles.length) {
                return;
            }

            selectedFiles.splice(index, 1);
            showMessage('', 'info');
            renderFiles();
        });

        if (addButton) {
            addButton.addEventListener('click', function () {
                input.click();
            });
        }

        if (clearButton) {
            clearButton.addEventListener('click', function () {
                selectedFiles = [];
                input.value = '';
                showMessage('', 'info');
                renderFiles();
            });
        }

        ['dragenter', 'dragover'].forEach(function (eventName) {
            upload.addEventListener(eventName, function (event) {
                event.preventDefault();
                upload.classList.add('is-dragging');
            });
        });

        ['dragleave', 'drop'].forEach(function (eventName) {
            upload.addEventListener(eventName, function (event) {
                event.preventDefault();
                upload.classList.remove('is-dragging');
            });
        });

        upload.addEventListener('drop', function (event) {
            addFiles(event.dataTransfer ? event.dataTransfer.files : []);
        });

        form.egoSelectedPaymentFiles = function () {
            return selectedFiles.slice();
        };

        form.egoResetPaymentFiles = function () {
            selectedFiles = [];
            input.value = '';
            showMessage('', 'info');
            renderFiles();
        };

        if (modal) {
            modal.addEventListener('hidden.bs.modal', function () {
                if (form.dataset.submitting === '1') {
                    return;
                }

                form.reset();
                form.egoResetPaymentFiles();

                if (
                    window.CKEDITOR &&
                    CKEDITOR.instances &&
                    CKEDITOR.instances.reason_editor
                ) {
                    CKEDITOR.instances.reason_editor.setData('');
                }
            });
        }

        renderFiles();
    });
})();
</script>
{{-- EGO_DNTT_MULTI_FILE_QUEUE_V3_END --}}

{{-- EGO_PR_ASYNC_CREATE_FIX_START --}}
<script>
(function () {
    function ready(callback) {
        if (document.readyState === 'loading') {
            document.addEventListener(
                'DOMContentLoaded',
                callback
            );
        } else {
            callback();
        }
    }

    function plainText(html) {
        var holder = document.createElement('div');

        holder.innerHTML = String(html || '');

        return String(
            holder.textContent ||
            holder.innerText ||
            ''
        )
            .replace(/\u00a0/g, ' ')
            .replace(/\s+/g, ' ')
            .trim();
    }

    function firstError(payload) {
        if (payload && payload.errors) {
            var keys = Object.keys(payload.errors);

            if (keys.length) {
                var value = payload.errors[keys[0]];

                if (
                    Array.isArray(value) &&
                    value.length
                ) {
                    return value[0];
                }

                if (typeof value === 'string') {
                    return value;
                }
            }
        }

        return payload && payload.message
            ? payload.message
            : 'Máy chủ không xử lý được yêu cầu.';
    }

    async function fetchWithTimeout(
        url,
        options,
        timeoutMs
    ) {
        var controller = new AbortController();

        var timer = setTimeout(function () {
            controller.abort();
        }, timeoutMs);

        options = options || {};
        options.signal = controller.signal;

        try {
            return await fetch(url, options);
        } finally {
            clearTimeout(timer);
        }
    }

    async function readJson(response) {
        var text = await response.text();

        try {
            return JSON.parse(text);
        } catch (error) {
            return {
                ok: false,
                message: text
                    ? 'Máy chủ trả về nội dung không hợp lệ.'
                    : 'Máy chủ không trả về dữ liệu.',
                raw: text
            };
        }
    }

    ready(function () {
        var form = document.getElementById(
            'egoPaymentRequestCreateForm'
        );

        if (
            !form ||
            form.dataset.egoAsyncBound === '1'
        ) {
            return;
        }

        form.dataset.egoAsyncBound = '1';

        form.addEventListener(
            'submit',
            async function (event) {
                event.preventDefault();
                event.stopImmediatePropagation();

                if (form.dataset.submitting === '1') {
                    return;
                }

                form.dataset.submitting = '1';

                if (
                    window.CKEDITOR &&
                    CKEDITOR.instances &&
                    CKEDITOR.instances.reason_editor
                ) {
                    CKEDITOR.instances.reason_editor
                        .updateElement();
                }

                if (!form.checkValidity()) {
                    form.dataset.submitting = '0';
                    form.reportValidity();
                    return;
                }

                var button = document.getElementById(
                    'egoPaymentRequestCreateSubmit'
                ) || form.querySelector(
                    'button[type="submit"]'
                );

                var originalButtonHtml = button
                    ? button.innerHTML
                    : '';

                if (button) {
                    button.disabled = true;
                    button.innerHTML =
                        '<span class="' +
                        'spinner-border ' +
                        'spinner-border-sm me-1' +
                        '"></span> Đang tạo phiếu...';
                }

                try {
                    /*
                     * Chỉ gửi dữ liệu chữ trước.
                     * Tuyệt đối chưa gửi PDF/file ở bước này.
                     */
                    var allData = new FormData(form);
                    var metadata = new URLSearchParams();

                    allData.forEach(function (
                        value,
                        key
                    ) {
                        if (!(value instanceof File)) {
                            metadata.append(
                                key,
                                String(value)
                            );
                        }
                    });

                    var createResponse =
                        await fetchWithTimeout(
                            form.action,
                            {
                                method: 'POST',
                                credentials: 'same-origin',
                                headers: {
                                    'Accept':
                                        'application/json',
                                    'X-Requested-With':
                                        'XMLHttpRequest',
                                    'Content-Type':
                                        'application/' +
                                        'x-www-form-urlencoded;' +
                                        'charset=UTF-8'
                                },
                                body: metadata.toString()
                            },
                            45000
                        );

                    var createPayload =
                        await readJson(
                            createResponse
                        );

                    if (
                        !createResponse.ok ||
                        !createPayload.ok ||
                        !createPayload.id
                    ) {
                        throw new Error(
                            firstError(createPayload)
                        );
                    }

                    var paymentRequestId =
                        createPayload.id;

                    var files = typeof form.egoSelectedPaymentFiles === 'function'
                        ? form.egoSelectedPaymentFiles()
                        : Array.from(
                            (form.querySelector('#egoPaymentRequestFiles') || {}).files || []
                        );

                    var failedFiles = [];
                    var uploadedCount = 0;

                    var csrf = form.querySelector(
                        'input[name="_token"]'
                    );

                    var baseUrl =
                        window.location.origin +
                        '/payment-requests';
                    var uploadTemplate =
                        form.dataset.uploadTemplate ||
                        (baseUrl + '/__ID__/attachments');
                    var uploadUrl = uploadTemplate.replace(
                        '__ID__',
                        String(paymentRequestId)
                    );

                    /*
                     * Người dùng chọn nhiều file một lần.
                     * Hệ thống gom file nhỏ thành từng lô an toàn để tránh vượt
                     * post_max_size của hosting; nếu một lô lỗi, phiếu vẫn còn.
                     */
                    var batches = [];
                    var currentBatch = [];
                    var currentBytes = 0;
                    var maximumBatchFiles = 5;
                    var maximumBatchBytes = 24 * 1024 * 1024;

                    files.forEach(function (file) {
                        var fileBytes = Number(file.size || 0);
                        var mustFlush = currentBatch.length > 0 && (
                            currentBatch.length >= maximumBatchFiles ||
                            currentBytes + fileBytes > maximumBatchBytes
                        );

                        if (mustFlush) {
                            batches.push(currentBatch);
                            currentBatch = [];
                            currentBytes = 0;
                        }

                        currentBatch.push(file);
                        currentBytes += fileBytes;
                    });

                    if (currentBatch.length) {
                        batches.push(currentBatch);
                    }

                    for (
                        var batchIndex = 0;
                        batchIndex < batches.length;
                        batchIndex++
                    ) {
                        var batch = batches[batchIndex];

                        if (button) {
                            button.innerHTML =
                                '<span class="spinner-border spinner-border-sm me-1"></span>' +
                                ' Đang tải ' +
                                Math.min(uploadedCount + batch.length, files.length) +
                                '/' +
                                files.length +
                                ' file...';
                        }

                        var uploadData = new FormData();

                        if (csrf) {
                            uploadData.append('_token', csrf.value);
                        }

                        batch.forEach(function (file) {
                            uploadData.append(
                                'attachments[]',
                                file,
                                file.name
                            );
                        });

                        try {
                            var uploadResponse =
                                await fetchWithTimeout(
                                    uploadUrl,
                                    {
                                        method: 'POST',
                                        credentials: 'same-origin',
                                        headers: {
                                            'Accept': 'application/json',
                                            'X-Requested-With': 'XMLHttpRequest'
                                        },
                                        body: uploadData
                                    },
                                    180000
                                );

                            var uploadPayload = await readJson(uploadResponse);

                            if (
                                !uploadResponse.ok ||
                                uploadPayload.ok === false
                            ) {
                                batch.forEach(function (file) {
                                    failedFiles.push(
                                        file.name +
                                        (uploadPayload && uploadPayload.message
                                            ? ' — ' + uploadPayload.message
                                            : '')
                                    );
                                });
                            } else {
                                uploadedCount += batch.length;
                            }
                        } catch (uploadError) {
                            batch.forEach(function (file) {
                                failedFiles.push(
                                    file.name +
                                    (uploadError && uploadError.message
                                        ? ' — ' + uploadError.message
                                        : '')
                                );
                            });
                        }
                    }

                    if (failedFiles.length) {
                        alert(
                            'Phiếu đã được tạo thành công.' +
                            '\n\nChưa tải được file: ' +
                            failedFiles.join(', ') +
                            '\n\nBạn có thể mở phiếu ' +
                            'và tải lại chứng từ.'
                        );
                    }

                    window.location.href =
                        createPayload.redirect_url ||
                        baseUrl;
                } catch (error) {
                    var message =
                        error &&
                        error.name === 'AbortError'
                            ? 'Máy chủ phản hồi quá lâu.'
                            : (
                                error.message ||
                                'Mất kết nối khi tạo phiếu.'
                            );

                    alert(message);
                    form.dataset.submitting = '0';

                    if (button) {
                        button.disabled = false;
                        button.innerHTML =
                            originalButtonHtml;
                    }
                }
            },
            true
        );

        window.addEventListener(
            'pageshow',
            function () {
                var button =
                    document.getElementById(
                        'egoPaymentRequestCreateSubmit'
                    );

                form.dataset.submitting = '0';

                if (button) {
                    button.disabled = false;
                }
            }
        );
    });
})();
</script>
{{-- EGO_PR_ASYNC_CREATE_FIX_END --}}
