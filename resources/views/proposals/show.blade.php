@extends('layouts.app')

@section('title', 'Chi tiết đề xuất')

@section('content')
<link rel="stylesheet"
      href="{{ asset('css/ego-proposals-v2.css') }}?v={{ @filemtime(public_path('css/ego-proposals-v2.css')) ?: time() }}">

<div class="ep-page">
    <div class="ep-wrap">
        <div class="ep-header">
            <div class="ep-heading">
                <div class="ep-heading-icon">
                    <i class="bi bi-file-earmark-text"></i>
                </div>
                <div>
                    <div class="ep-eyebrow">Đề xuất #{{ $proposal->id }}</div>
                    <h1 class="ep-title">{{ $proposal->title }}</h1>
                    <div class="ep-sub">
                        Người đề xuất: {{ $proposal->employee_name }}
                        @if($proposal->department_name)
                            · {{ $proposal->department_name }}
                        @endif
                    </div>
                </div>
            </div>

            <div class="ep-actions">
                <a href="{{ route('de-xuat.index') }}" class="ep-btn">
                    <i class="bi bi-arrow-left"></i> Danh sách
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

        @if($errors->any())
            <div class="alert alert-danger border-0 shadow-sm rounded-4">
                @foreach($errors->all() as $error)
                    <div>{{ $error }}</div>
                @endforeach
            </div>
        @endif

        <div class="ep-info-grid">
            <div class="ep-info">
                <div class="ep-info-label">Người tạo</div>
                <div class="ep-info-value">{{ $proposal->employee_name }}</div>
            </div>

            <div class="ep-info">
                <div class="ep-info-label">Phòng ban</div>
                <div class="ep-info-value">{{ $proposal->department_name ?: '-' }}</div>
            </div>

            <div class="ep-info">
                <div class="ep-info-label">Số tiền</div>
                <div class="ep-info-value">
                    {{ number_format($proposal->amount, 0, ',', '.') }} đ
                </div>
            </div>

            <div class="ep-info">
                <div class="ep-info-label">Ngày cần xử lý</div>
                <div class="ep-info-value">{{ $proposal->needed_date ?: '-' }}</div>
            </div>

            <div class="ep-info">
                <div class="ep-info-label">Trạng thái</div>
                <div class="ep-info-value">
                    @if($proposal->status === 'approved')
                        <span class="ep-badge approved">Đã duyệt</span>
                    @elseif($proposal->status === 'rejected')
                        <span class="ep-badge rejected">Từ chối</span>
                    @else
                        <span class="ep-badge pending">Chờ duyệt</span>
                    @endif
                </div>
            </div>
        </div>

        <div class="ep-detail-grid">
            <div>
                <div class="ep-card">
                    <div class="ep-card-head">
                        <div>
                            <h2 class="ep-card-title">
                                <i class="bi bi-file-text"></i> Nội dung trình duyệt
                            </h2>
                            <div class="ep-card-sub">
                                {{ $types[$proposal->proposal_type] ?? 'Khác' }}
                                · Ưu tiên {{ $priorities[$proposal->priority ?? 'normal'] ?? 'Bình thường' }}
                            </div>
                        </div>
                    </div>

                    <div class="ep-content-block">
                        <div class="ep-content-label">Nội dung đề xuất</div>
                        <div class="ep-content-text">
                            {{ $proposal->content ?: 'Không có nội dung.' }}
                        </div>
                    </div>

                    <div class="ep-content-block">
                        <div class="ep-content-label">Lý do đề xuất</div>
                        <div class="ep-content-text">
                            {{ $proposal->reason ?: 'Không có lý do.' }}
                        </div>
                    </div>

                    <div class="ep-content-block">
                        <div class="ep-content-label">Kết quả kỳ vọng</div>
                        <div class="ep-content-text">
                            {{ $proposal->expected_result ?: 'Không có nội dung.' }}
                        </div>
                    </div>
                </div>

                <div class="ep-card">
                    <div class="ep-card-head">
                        <div>
                            <h2 class="ep-card-title">
                                <i class="bi bi-paperclip"></i> Hồ sơ đính kèm
                            </h2>
                            <div class="ep-card-sub">
                                {{ count($attachments ?? []) }} file liên quan
                            </div>
                        </div>
                    </div>

                    <div class="ep-file-list">
                        @forelse($attachments ?? [] as $file)
                            <div class="ep-file">
                                <div class="ep-file-main">
                                    <div class="ep-file-icon">
                                        @if(str_contains($file->file_mime ?? '', 'image'))
                                            <i class="bi bi-image"></i>
                                        @elseif(str_contains($file->file_mime ?? '', 'pdf'))
                                            <i class="bi bi-file-earmark-pdf"></i>
                                        @else
                                            <i class="bi bi-file-earmark"></i>
                                        @endif
                                    </div>

                                    <div style="min-width:0">
                                        <div class="ep-file-name">{{ $file->file_name }}</div>
                                        <div class="ep-file-meta">
                                            {{ $file->file_mime ?: 'file' }}
                                            · {{ number_format(($file->file_size ?? 0) / 1024, 1) }} KB
                                        </div>
                                    </div>
                                </div>

                                <a href="{{ asset('storage/' . $file->file_path) }}"
                                   target="_blank"
                                   class="ep-btn">
                                    <i class="bi bi-box-arrow-up-right"></i> Xem file
                                </a>
                            </div>
                        @empty
                            <div class="ep-empty py-4">
                                Chưa có file đính kèm.
                            </div>
                        @endforelse
                    </div>
                </div>
            </div>

            <div>
                <div class="ep-card">
                    <div class="ep-side-head">
                        <i class="bi bi-check2-square"></i> Xử lý đề xuất
                    </div>

                    <div class="ep-side-body">
                        <div class="ep-timeline mb-3">
                            <div class="mb-2">
                                <div class="text-muted small">Người xử lý</div>
                                <div class="fw-bold">{{ $proposal->approved_name ?: 'Chưa có' }}</div>
                            </div>
                            <div>
                                <div class="text-muted small">Thời gian xử lý</div>
                                <div class="fw-bold">{{ $proposal->approved_at ?: '-' }}</div>
                            </div>
                        </div>

                        @if($proposal->status === 'approved')
                            <div class="ep-alert success mb-3">
                                <strong>Đề xuất đã được duyệt.</strong>
                                <div class="mt-1">
                                    {{ $proposal->approved_note ?: 'Không có ghi chú.' }}
                                </div>
                            </div>
                        @elseif($proposal->status === 'rejected')
                            <div class="ep-alert danger mb-3">
                                <strong>Đề xuất đã bị từ chối.</strong>
                                <div class="mt-1">
                                    {{ $proposal->reject_reason ?: 'Không có lý do.' }}
                                </div>
                            </div>
                        @endif

                        @if($canApprove && $proposal->status === 'pending')
                            <form method="POST"
                                  action="{{ route('de-xuat.approve', $proposal->id) }}"
                                  class="mb-3"
                                  onsubmit="return confirm('Duyệt đề xuất này? Nếu số tiền lớn hơn 0, hệ thống sẽ tự tạo một ĐNTT nháp.')">
                                @csrf

                                <label class="ep-label">Ghi chú duyệt</label>
                                <textarea name="approved_note"
                                          class="ep-textarea mb-2"
                                          style="min-height:88px"
                                          placeholder="Ghi chú quyết định nếu cần..."></textarea>

                                <button class="ep-btn ep-btn-primary w-100">
                                    <i class="bi bi-check-circle"></i>
                                    Duyệt và tạo ĐNTT
                                </button>
                            </form>

                            <form method="POST"
                                  action="{{ route('de-xuat.reject', $proposal->id) }}">
                                @csrf

                                <label class="ep-label">Lý do từ chối</label>
                                <textarea name="reject_reason"
                                          class="ep-textarea mb-2"
                                          style="min-height:88px"
                                          placeholder="Nhập lý do từ chối..."></textarea>

                                <button class="ep-btn ep-btn-danger w-100">
                                    <i class="bi bi-x-circle"></i> Từ chối
                                </button>
                            </form>
                        @elseif(!$canApprove)
                            <div class="ep-alert info">
                                Bạn đang theo dõi trạng thái đề xuất này.
                            </div>
                        @endif
                    </div>
                </div>

                @if(($proposal->amount ?? 0) > 0)
                    <div class="ep-card">
                        <div class="ep-card-head">
                            <div>
                                <h2 class="ep-card-title">
                                    <i class="bi bi-receipt"></i> Đề nghị thanh toán
                                </h2>
                                <div class="ep-card-sub">
                                    Tự động tạo sau khi đề xuất được duyệt
                                </div>
                            </div>
                        </div>

                        <div class="ep-side-body">
                            @if($proposal->payment_request_code)
                                <div class="ep-pr-card">
                                    <div class="ep-pr-card-title">ĐNTT đã liên kết</div>
                                    <div class="ep-pr-card-code">
                                        {{ $proposal->payment_request_code }}
                                    </div>

                                    <div class="mb-3">
                                        <span class="ep-badge {{ $proposal->payment_request_status ?: 'neutral' }}">
                                            {{ $paymentStatusLabels[$proposal->payment_request_status] ?? $proposal->payment_request_status ?? 'Nháp' }}
                                        </span>
                                    </div>

                                    <div class="d-flex justify-content-between small mb-3">
                                        <span class="text-muted">Số tiền</span>
                                        <strong>
                                            {{ number_format($proposal->payment_request_amount ?? $proposal->amount, 0, ',', '.') }} đ
                                        </strong>
                                    </div>

                                    <a href="{{ route('payment_requests.show', $proposal->payment_request_id) }}"
                                       class="ep-btn ep-btn-primary w-100">
                                        <i class="bi bi-box-arrow-up-right"></i>
                                        Mở ĐNTT
                                    </a>
                                </div>
                            @elseif($proposal->status === 'approved')
                                <div class="ep-alert info">
                                    Đây là đề xuất đã duyệt trước khi tính năng tự động được cài đặt nên chưa có ĐNTT liên kết.
                                </div>
                            @else
                                <div class="ep-alert info">
                                    Khi cấp có thẩm quyền bấm duyệt, hệ thống sẽ tạo đúng một ĐNTT nháp với số tiền
                                    <strong>{{ number_format($proposal->amount, 0, ',', '.') }} đ</strong>.
                                </div>
                            @endif
                        </div>
                    </div>
                @endif

                <div class="ep-card">
                    <div class="ep-card-head">
                        <div>
                            <h2 class="ep-card-title">
                                <i class="bi bi-info-circle"></i> Thông tin nhanh
                            </h2>
                        </div>
                    </div>

                    <div class="ep-side-body">
                        <div class="d-flex justify-content-between mb-2">
                            <span class="text-muted">Ngày tạo</span>
                            <strong>{{ $proposal->created_at }}</strong>
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <span class="text-muted">Cập nhật cuối</span>
                            <strong>{{ $proposal->updated_at }}</strong>
                        </div>
                        <div class="d-flex justify-content-between">
                            <span class="text-muted">Mã đề xuất</span>
                            <strong>#{{ $proposal->id }}</strong>
                        </div>

                        @php
                            $canDelete = $canApprove
                                || (
                                    (int) $proposal->user_id === (int) auth()->id()
                                    && $proposal->status === 'pending'
                                );
                        @endphp

                        @if($canDelete && empty($proposal->payment_request_id))
                            <hr>
                            <form method="POST"
                                  action="{{ route('de-xuat.destroy', $proposal->id) }}"
                                  onsubmit="return confirm('Bạn chắc chắn muốn xóa đề xuất này?')">
                                @csrf
                                <button class="ep-btn ep-btn-danger w-100">
                                    <i class="bi bi-trash"></i> Xóa đề xuất
                                </button>
                            </form>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
