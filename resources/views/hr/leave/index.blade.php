@extends('layouts.app')

@section('title', 'Đơn nhân sự')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/ego-leave-promax.css') }}?v={{ filemtime(public_path('css/ego-leave-promax.css')) }}">
@endpush

@section('content')
@php
    $initials = static function ($name): string {
        return collect(preg_split('/\s+/u', trim((string) $name)))
            ->filter()
            ->take(2)
            ->map(fn ($part) => mb_strtoupper(mb_substr($part, 0, 1)))
            ->implode('') ?: 'NV';
    };

    $statusClass = static function ($status): string {
        return match ((string) $status) {
            'approved' => 'approved',
            'rejected' => 'rejected',
            'cancelled' => 'cancelled',
            default => 'pending',
        };
    };

    $timeText = static function ($date, $time): string {
        if (! $date) return '—';
        $value = $date->format('d/m/Y');
        return $time ? $value.' · '.substr((string) $time, 0, 5) : $value;
    };
@endphp

<div id="egoLeavePromax">
    <div class="lv-shell">
        @if(session('success'))
            <div class="lv-alert"><i class="bi bi-check-circle"></i>{{ session('success') }}</div>
        @endif

        @if(session('error'))
            <div class="lv-alert lv-alert--danger"><i class="bi bi-exclamation-circle"></i>{{ session('error') }}</div>
        @endif

        @if($errors->any())
            <div class="lv-alert lv-alert--danger">
                <i class="bi bi-exclamation-circle"></i>
                <div>
                    @foreach($errors->all() as $error)
                        <div>{{ $error }}</div>
                    @endforeach
                </div>
            </div>
        @endif

        <header class="lv-hero lv-panel">
            <div class="lv-heading">
                <div class="lv-heading__icon"><i class="bi bi-calendar2-check"></i></div>
                <div>
                    <span>EMPLOYEE SELF-SERVICE</span>
                    <h1>Đơn nghỉ phép & làm việc</h1>
                    <p>Tạo đơn, gửi minh chứng và theo dõi quy trình phê duyệt minh bạch.</p>
                </div>
            </div>

            <div class="lv-actions">
                <a class="lv-btn lv-btn--glass" href="{{ route('hr.attendance.my') }}">
                    <i class="bi bi-fingerprint"></i>Chấm công của tôi
                </a>
                <a class="lv-btn lv-btn--light" href="{{ route('hr.leave.create') }}">
                    <i class="bi bi-plus-circle"></i>Tạo đơn mới
                </a>
            </div>
        </header>

        <section class="lv-stats">
            <article class="lv-stat lv-panel">
                <label>Tổng đơn của tôi</label>
                <strong>{{ number_format($mineCount) }}</strong>
                <small>Tất cả trạng thái</small>
            </article>
            <article class="lv-stat lv-panel">
                <label>Đang chờ duyệt</label>
                <strong>{{ number_format($minePendingCount) }}</strong>
                <small>Đơn cá nhân chưa xử lý</small>
            </article>
            <article class="lv-stat lv-panel">
                <label>Chờ tôi duyệt</label>
                <strong>{{ number_format($pendingApprovalCount) }}</strong>
                <small>Đơn thuộc phạm vi quản lý</small>
            </article>
            <article class="lv-stat lv-panel">
                <label>Đã duyệt tháng này</label>
                <strong>{{ rtrim(rtrim(number_format($approvedThisMonth, 2, '.', ''), '0'), '.') }}</strong>
                <small>Ngày nghỉ / làm việc đã duyệt</small>
            </article>
        </section>

        <nav class="lv-tabs lv-panel">
            <a href="{{ route('hr.leave.index', ['tab' => 'mine']) }}" class="lv-tab {{ $tab === 'mine' ? 'is-active' : '' }}">
                <i class="bi bi-person"></i>Đơn của tôi
                @if($minePendingCount > 0)<span class="lv-count">{{ $minePendingCount }}</span>@endif
            </a>

            @if($canReview)
                <a href="{{ route('hr.leave.index', ['tab' => 'approval', 'status' => 'pending']) }}" class="lv-tab {{ $tab === 'approval' ? 'is-active' : '' }}">
                    <i class="bi bi-check2-square"></i>Chờ tôi duyệt
                    @if($pendingApprovalCount > 0)<span class="lv-count">{{ $pendingApprovalCount }}</span>@endif
                </a>
            @endif

            @if($canManageAll)
                <a href="{{ route('hr.leave.index', ['tab' => 'all']) }}" class="lv-tab {{ $tab === 'all' ? 'is-active' : '' }}">
                    <i class="bi bi-people"></i>Tất cả đơn
                </a>
            @endif
        </nav>

        <form method="GET" action="{{ route('hr.leave.index') }}" class="lv-filter lv-panel">
            <input type="hidden" name="tab" value="{{ $tab }}">

            <div class="lv-field">
                <label>Tìm nhân viên</label>
                @if($canManageAll && $tab === 'all')
                    <select class="lv-select" name="user_id">
                        <option value="">Tất cả nhân viên</option>
                        @foreach($employees as $employee)
                            <option value="{{ $employee->id }}" @selected((int) $userId === (int) $employee->id)>{{ $employee->name }}</option>
                        @endforeach
                    </select>
                @else
                    <input class="lv-input" value="{{ $tab === 'mine' ? auth()->user()->name : 'Theo phạm vi duyệt' }}" disabled>
                @endif
            </div>

            <div class="lv-field">
                <label>Loại đơn</label>
                <select class="lv-select" name="request_type">
                    <option value="">Tất cả</option>
                    <option value="leave" @selected($requestType === 'leave')>Nghỉ phép</option>
                    <option value="wfh" @selected($requestType === 'wfh')>Làm online</option>
                    <option value="business_trip" @selected($requestType === 'business_trip')>Công tác</option>
                    <option value="late" @selected($requestType === 'late')>Đi trễ</option>
                    <option value="early_leave" @selected($requestType === 'early_leave')>Về sớm</option>
                </select>
            </div>

            <div class="lv-field">
                <label>Trạng thái</label>
                <select class="lv-select" name="status">
                    <option value="">Tất cả</option>
                    <option value="pending" @selected($status === 'pending')>Chờ duyệt</option>
                    <option value="approved" @selected($status === 'approved')>Đã duyệt</option>
                    <option value="rejected" @selected($status === 'rejected')>Từ chối</option>
                    <option value="cancelled" @selected($status === 'cancelled')>Đã hủy</option>
                </select>
            </div>

            <button class="lv-btn lv-btn--primary"><i class="bi bi-funnel"></i>Áp dụng</button>
            <a class="lv-btn lv-btn--light" href="{{ route('hr.leave.index', ['tab' => $tab]) }}"><i class="bi bi-arrow-counterclockwise"></i>Xóa lọc</a>
        </form>

        <section class="lv-list">
            @forelse($leaveRequests as $item)
                <article class="lv-card lv-panel">
                    <div class="lv-person">
                        <div class="lv-avatar">{{ $initials($item->user?->name) }}</div>
                        <div>
                            <strong>{{ $item->user?->name ?: 'Không xác định' }}</strong>
                            <small>{{ $item->user?->department?->name ?: 'Chưa có phòng ban' }} · Đơn #{{ $item->id }}</small>
                        </div>
                    </div>

                    <div>
                        <div class="lv-type-row">
                            <span class="lv-type"><i class="bi bi-calendar-event"></i>{{ $item->request_type_label }}</span>
                            <span class="lv-status lv-status--{{ $statusClass($item->status) }}">{{ $item->status_label }}</span>
                            @if($item->attachments->isNotEmpty())
                                <span class="lv-type"><i class="bi bi-paperclip"></i>{{ $item->attachments->count() }} file</span>
                            @endif
                        </div>

                        <div class="lv-reason">{{ $item->reason ?: 'Không có lý do.' }}</div>

                        <div class="lv-meta-grid">
                            <div class="lv-meta"><span>Bắt đầu</span><strong>{{ $timeText($item->start_date, $item->start_time) }}</strong></div>
                            <div class="lv-meta"><span>Kết thúc</span><strong>{{ $timeText($item->end_date, $item->end_time) }}</strong></div>
                            <div class="lv-meta"><span>Thời lượng</span><strong>{{ rtrim(rtrim(number_format((float) $item->days, 2, '.', ''), '0'), '.') }} ngày</strong></div>
                            <div class="lv-meta"><span>Loại chi tiết</span><strong>{{ $item->leave_type_label }}</strong></div>
                        </div>

                        @if($item->attachments->isNotEmpty())
                            <div class="lv-attachments">
                                @foreach($item->attachments as $attachment)
                                    <a class="lv-file" href="{{ route('hr.leave.attachments.download', [$item, $attachment]) }}" title="Tải {{ $attachment->original_name }}">
                                        <i class="bi bi-paperclip"></i><span>{{ $attachment->original_name }}</span>
                                    </a>
                                @endforeach
                            </div>
                        @endif
                    </div>

                    <div class="lv-approver">
                        <small>Người duyệt hiện tại</small>
                        <strong>{{ $item->approver?->name ?: 'Chưa phân công' }}</strong>
                        <small>Gửi lúc {{ $item->created_at?->format('d/m/Y H:i') }}</small>
                        @if($item->approval_note)
                            <small style="color:#9a6100">Ghi chú: {{ $item->approval_note }}</small>
                        @endif
                        @php($lastTransfer = $item->approvalLogs->firstWhere('action', 'transferred'))
                        @if($lastTransfer)
                            <small>Đã chuyển từ {{ $lastTransfer->fromApprover?->name ?: '—' }} lúc {{ $lastTransfer->created_at?->format('d/m H:i') }}</small>
                        @endif
                    </div>

                    <div class="lv-card-actions">
                        @if($item->_can_approve)
                            <button type="button" class="lv-btn lv-btn--success" data-leave-approval data-mode="approve" data-employee="{{ $item->user?->name }}" data-approve-url="{{ route('hr.leave.approve', $item) }}" data-reject-url="{{ route('hr.leave.reject', $item) }}">
                                <i class="bi bi-check2-circle"></i>Duyệt
                            </button>
                            <button type="button" class="lv-btn lv-btn--danger" data-leave-approval data-mode="reject" data-employee="{{ $item->user?->name }}" data-approve-url="{{ route('hr.leave.approve', $item) }}" data-reject-url="{{ route('hr.leave.reject', $item) }}">
                                <i class="bi bi-x-circle"></i>Từ chối
                            </button>
                        @endif

                        @if($item->_can_transfer)
                            <button type="button" class="lv-icon-btn" title="Chuyển người duyệt" data-leave-transfer data-employee="{{ $item->user?->name }}" data-approver="{{ $item->approver?->name }}" data-transfer-url="{{ route('hr.leave.transfer-approver', $item) }}">
                                <i class="bi bi-person-up"></i>
                            </button>
                        @endif

                        @if($item->_can_cancel)
                            <form method="POST" action="{{ route('hr.leave.cancel', $item) }}" data-loading-form>
                                @csrf
                                <button type="submit" class="lv-icon-btn" title="Hủy đơn" data-confirm="Xác nhận hủy đơn #{{ $item->id }}?"><i class="bi bi-trash"></i></button>
                            </form>
                        @endif
                    </div>
                </article>
            @empty
                <div class="lv-panel lv-empty">
                    <i class="bi bi-inbox" style="font-size:28px"></i>
                    <div style="margin-top:8px">Không có đơn phù hợp với bộ lọc.</div>
                </div>
            @endforelse
        </section>

        @if($leaveRequests->hasPages())
            <div class="lv-panel lv-pagination">{{ $leaveRequests->links('pagination::bootstrap-5') }}</div>
        @endif
    </div>
</div>

@if($canReview)
<div class="modal fade lv-modal" id="leaveApprovalModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="lv-modal__head">
                <div><h2 data-approval-title>Duyệt đơn nhân sự</h2><p>Nhân viên: <span data-approval-person></span></p></div>
                <button type="button" data-bs-dismiss="modal"><i class="bi bi-x-lg"></i></button>
            </div>
            <form method="POST" data-loading-form>
                @csrf
                <div class="lv-modal__body">
                    <div class="lv-field">
                        <label>Ghi chú xử lý</label>
                        <textarea class="lv-textarea" name="approval_note" rows="5"></textarea>
                    </div>
                    <div class="lv-modal__actions">
                        <button type="button" class="lv-btn lv-btn--light" data-bs-dismiss="modal">Hủy</button>
                        <button type="submit" class="lv-btn lv-btn--success"><i class="bi bi-check2-circle"></i>Xác nhận</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade lv-modal" id="leaveTransferModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="lv-modal__head">
                <div><h2>Chuyển người duyệt</h2><p><span data-transfer-person></span> · hiện tại: <span data-current-approver></span></p></div>
                <button type="button" data-bs-dismiss="modal"><i class="bi bi-x-lg"></i></button>
            </div>
            <form method="POST" data-loading-form>
                @csrf
                <div class="lv-modal__body">
                    <div class="lv-field">
                        <label>Người duyệt mới <em>*</em></label>
                        <select class="lv-select" name="approver_id" required>
                            <option value="">Chọn người duyệt</option>
                            @foreach($approvers as $approver)
                                <option value="{{ $approver->id }}">{{ $approver->name }}{{ $approver->department?->name ? ' · '.$approver->department->name : '' }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="lv-field" style="margin-top:10px">
                        <label>Lý do chuyển <em>*</em></label>
                        <textarea class="lv-textarea" name="transfer_note" rows="5" required placeholder="Nêu rõ lý do chuyển người duyệt..."></textarea>
                    </div>
                    <div class="lv-modal__actions">
                        <button type="button" class="lv-btn lv-btn--light" data-bs-dismiss="modal">Hủy</button>
                        <button type="submit" class="lv-btn lv-btn--warning"><i class="bi bi-arrow-left-right"></i>Chuyển người duyệt</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>
@endif
@endsection

@push('scripts')
    <script src="{{ asset('js/ego-leave-promax.js') }}?v={{ filemtime(public_path('js/ego-leave-promax.js')) }}" defer></script>
@endpush
