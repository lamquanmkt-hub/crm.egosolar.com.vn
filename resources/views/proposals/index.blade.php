@extends('layouts.app')

@section('title', 'Đề xuất nội bộ')

@section('content')
<link rel="stylesheet"
      href="{{ asset('css/ego-proposals-v2.css') }}?v={{ @filemtime(public_path('css/ego-proposals-v2.css')) ?: time() }}">

<div class="ep-page">
    <div class="ep-wrap">
        <div class="ep-header">
            <div class="ep-heading">
                <div class="ep-heading-icon">
                    <i class="bi bi-lightbulb"></i>
                </div>
                <div>
                    <div class="ep-eyebrow">Trung tâm điều hành nội bộ</div>
                    <h1 class="ep-title">Đề xuất nội bộ</h1>
                    <div class="ep-sub">
                        Tạo đề xuất, theo dõi phê duyệt và tự động sinh ĐNTT khi đề xuất có ngân sách được duyệt.
                    </div>
                </div>
            </div>

            <div class="ep-actions">
                <a href="{{ route('de-xuat.create') }}" class="ep-btn ep-btn-primary">
                    <i class="bi bi-plus-lg"></i> Tạo đề xuất
                </a>
            </div>
        </div>

        @if(session('success'))
            <div class="alert alert-success border-0 shadow-sm rounded-4">
                {{ session('success') }}
            </div>
        @endif

        @if(session('error'))
            <div class="alert alert-danger border-0 shadow-sm rounded-4">
                {{ session('error') }}
            </div>
        @endif

        <div class="ep-kpis">
            <div class="ep-kpi blue">
                <div class="ep-kpi-label">Tổng đề xuất</div>
                <div class="ep-kpi-value">{{ $summary['total'] ?? 0 }}</div>
                <div class="ep-kpi-note">
                    {{ ($canViewAll ?? false) ? 'Toàn bộ đề xuất công ty' : 'Trong phạm vi được phép xem' }}
                </div>
            </div>

            <div class="ep-kpi amber">
                <div class="ep-kpi-label">Chờ duyệt</div>
                <div class="ep-kpi-value">{{ $summary['pending'] ?? 0 }}</div>
                <div class="ep-kpi-note">
                    {{ number_format($summary['amount_pending'] ?? 0, 0, ',', '.') }} đ đang chờ
                </div>
            </div>

            <div class="ep-kpi green">
                <div class="ep-kpi-label">Đã duyệt</div>
                <div class="ep-kpi-value">{{ $summary['approved'] ?? 0 }}</div>
                <div class="ep-kpi-note">Đã có quyết định xử lý</div>
            </div>

            <div class="ep-kpi red">
                <div class="ep-kpi-label">Từ chối</div>
                <div class="ep-kpi-value">{{ $summary['rejected'] ?? 0 }}</div>
                <div class="ep-kpi-note">Không được phê duyệt</div>
            </div>

            <div class="ep-kpi cyan">
                <div class="ep-kpi-label">ĐNTT tự động</div>
                <div class="ep-kpi-value">{{ $summary['auto_payment'] ?? 0 }}</div>
                <div class="ep-kpi-note">Tạo từ đề xuất đã duyệt</div>
            </div>
        </div>

        <div class="ep-card">
            <div class="ep-card-head">
                <div>
                    <h2 class="ep-card-title">
                        <i class="bi bi-list-check"></i> Danh sách đề xuất
                    </h2>
                    <div class="ep-card-sub">
                        {{ ($canViewAll ?? false)
                            ? 'Đang hiển thị toàn bộ đề xuất công ty; quyền duyệt vẫn theo phân quyền hiện tại.'
                            : 'Nhân viên xem đề xuất của mình; người có quyền xem toàn bộ sẽ thấy dữ liệu công ty.' }}
                    </div>
                </div>

                <a href="{{ route('de-xuat.create') }}" class="ep-btn ep-btn-primary">
                    <i class="bi bi-plus-lg"></i> Tạo mới
                </a>
            </div>

            <form method="GET"
                  action="{{ route('de-xuat.index') }}"
                  class="ep-filter">
                <input type="text"
                       name="q"
                       value="{{ request('q') }}"
                       class="ep-input"
                       placeholder="Tìm tiêu đề, nội dung hoặc người tạo...">

                <select name="status" class="ep-select">
                    <option value="">Tất cả trạng thái</option>
                    <option value="pending" {{ request('status') === 'pending' ? 'selected' : '' }}>Chờ duyệt</option>
                    <option value="approved" {{ request('status') === 'approved' ? 'selected' : '' }}>Đã duyệt</option>
                    <option value="rejected" {{ request('status') === 'rejected' ? 'selected' : '' }}>Từ chối</option>
                </select>

                <select name="type" class="ep-select">
                    <option value="">Tất cả loại</option>
                    @foreach($types as $key => $label)
                        <option value="{{ $key }}" {{ request('type') === $key ? 'selected' : '' }}>
                            {{ $label }}
                        </option>
                    @endforeach
                </select>

                <select name="priority" class="ep-select">
                    <option value="">Tất cả ưu tiên</option>
                    @foreach($priorities as $key => $label)
                        <option value="{{ $key }}" {{ request('priority') === $key ? 'selected' : '' }}>
                            {{ $label }}
                        </option>
                    @endforeach
                </select>

                <button class="ep-btn ep-btn-primary">
                    <i class="bi bi-funnel"></i> Áp dụng
                </button>

                @if(request()->hasAny(['q','status','type','priority']))
                    <a href="{{ route('de-xuat.index') }}" class="ep-btn">
                        <i class="bi bi-arrow-counterclockwise"></i> Xóa lọc
                    </a>
                @endif
            </form>

            <div class="ep-table-wrap">
                <table class="ep-table">
                    <thead>
                        <tr>
                            <th>Thông tin đề xuất</th>
                            <th class="text-end">Số tiền</th>
                            <th>Ngày cần xử lý</th>
                            <th>Phê duyệt</th>
                            <th>Đề nghị thanh toán</th>
                            <th class="text-end">Thao tác</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($proposals as $proposal)
                            @php
                                $statusClass = $proposal->status === 'approved'
                                    ? 'approved'
                                    : ($proposal->status === 'rejected' ? 'rejected' : 'pending');

                                $statusText = $proposal->status === 'approved'
                                    ? 'Đã duyệt'
                                    : ($proposal->status === 'rejected' ? 'Từ chối' : 'Chờ duyệt');

                                $priorityText = $priorities[$proposal->priority ?? 'normal'] ?? 'Bình thường';
                                $paymentStatus = $proposal->payment_request_status ?? null;
                            @endphp

                            <tr>
                                <td>
                                    <div class="ep-main-cell">
                                        <div class="ep-row-icon">
                                            <i class="bi bi-lightbulb"></i>
                                        </div>

                                        <div>
                                            <a href="{{ route('de-xuat.show', $proposal->id) }}"
                                               class="ep-row-title">
                                                {{ $proposal->title }}
                                            </a>

                                            <div class="ep-meta">
                                                <span class="ep-chip">
                                                    <i class="bi bi-person"></i>
                                                    {{ $proposal->employee_name }}
                                                </span>

                                                <span class="ep-chip">
                                                    <i class="bi bi-building"></i>
                                                    {{ $proposal->department_name ?: '-' }}
                                                </span>

                                                <span class="ep-chip">
                                                    <i class="bi bi-tag"></i>
                                                    {{ $types[$proposal->proposal_type] ?? 'Khác' }}
                                                </span>

                                                <span class="ep-chip">
                                                    <i class="bi bi-flag"></i>
                                                    {{ $priorityText }}
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                </td>

                                <td class="text-end">
                                    <div class="ep-money">
                                        {{ number_format($proposal->amount, 0, ',', '.') }} đ
                                    </div>
                                </td>

                                <td>
                                    <span class="ep-chip">
                                        <i class="bi bi-calendar3"></i>
                                        {{ $proposal->needed_date ?: 'Không đặt ngày' }}
                                    </span>
                                </td>

                                <td>
                                    <span class="ep-badge {{ $statusClass }}">
                                        {{ $statusText }}
                                    </span>
                                </td>

                                <td>
                                    @if(($proposal->amount ?? 0) <= 0)
                                        <span class="ep-badge neutral">
                                            Không phát sinh
                                        </span>
                                    @elseif($proposal->payment_request_code)
                                        <div class="ep-pr-link">
                                            <a href="{{ route('payment_requests.show', $proposal->payment_request_id) }}"
                                               class="ep-pr-code">
                                                {{ $proposal->payment_request_code }}
                                            </a>
                                            <span class="ep-badge {{ $paymentStatus ?: 'neutral' }}">
                                                {{ $paymentStatusLabels[$paymentStatus] ?? $paymentStatus ?? 'Nháp' }}
                                            </span>
                                        </div>
                                    @elseif($proposal->status === 'approved')
                                        <span class="ep-badge neutral">
                                            Đề xuất cũ – chưa liên kết
                                        </span>
                                    @else
                                        <span class="ep-badge neutral">
                                            Tạo sau khi duyệt
                                        </span>
                                    @endif
                                </td>

                                <td class="text-end">
                                    <a href="{{ route('de-xuat.show', $proposal->id) }}"
                                       class="ep-btn">
                                        <i class="bi bi-eye"></i> Xem chi tiết
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6">
                                    <div class="ep-empty">
                                        <div class="ep-empty-icon">
                                            <i class="bi bi-inbox"></i>
                                        </div>
                                        <div class="fw-bold text-dark">Chưa có đề xuất phù hợp</div>
                                        <div class="mt-1">Tạo đề xuất đầu tiên hoặc thay đổi bộ lọc.</div>
                                        <a href="{{ route('de-xuat.create') }}"
                                           class="ep-btn ep-btn-primary mt-3">
                                            <i class="bi bi-plus-lg"></i> Tạo đề xuất
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
