@extends('layouts.app')

@section('title', 'Yêu cầu sửa chấm công')

@push('styles')
<style>
    .acr-page{--navy:#073b56;--teal:#079aaa;--line:#dce8ed;--muted:#68808d;color:#17394a}
    .acr-hero{display:flex;align-items:center;justify-content:space-between;gap:20px;padding:26px 28px;border-radius:24px;background:linear-gradient(135deg,#073b56,#087f91 70%,#09a8aa);color:#fff;box-shadow:0 18px 45px rgba(4,76,94,.18)}
    .acr-hero h1{margin:4px 0;font-size:28px;font-weight:900}.acr-hero p{margin:0;opacity:.78}.acr-kicker{font-size:11px;font-weight:900;letter-spacing:.12em}
    .acr-hero-actions{display:flex;flex-wrap:wrap;gap:9px}.acr-btn{display:inline-flex;align-items:center;justify-content:center;gap:7px;border:0;border-radius:12px;padding:10px 14px;font-weight:800;text-decoration:none;cursor:pointer}
    .acr-btn-light{background:#fff;color:#07556b}.acr-btn-glass{background:rgba(255,255,255,.15);color:#fff;border:1px solid rgba(255,255,255,.24)}.acr-btn-primary{background:var(--teal);color:#fff}.acr-btn-danger{background:#fff0f1;color:#b42332}.acr-btn-secondary{background:#edf4f6;color:#49636f}
    .acr-count{display:inline-flex;min-width:21px;height:21px;align-items:center;justify-content:center;border-radius:999px;background:#ffda65;color:#624800;font-size:11px}
    .acr-filter{display:flex;align-items:end;flex-wrap:wrap;gap:12px;margin:18px 0;padding:16px 18px;border:1px solid var(--line);border-radius:18px;background:#fff}.acr-filter label{display:block;margin-bottom:5px;color:var(--muted);font-size:11px;font-weight:900;text-transform:uppercase}.acr-filter select{min-width:170px;border:1px solid var(--line);border-radius:11px;padding:9px 11px;background:#fff}
    .acr-list{display:grid;gap:16px}.acr-card{border:1px solid var(--line);border-radius:20px;background:#fff;box-shadow:0 10px 28px rgba(17,63,80,.07);overflow:hidden}.acr-card-head{display:flex;justify-content:space-between;align-items:flex-start;gap:14px;padding:17px 20px;border-bottom:1px solid #e8f0f3}.acr-person{display:flex;gap:11px;align-items:center}.acr-avatar{display:flex;width:42px;height:42px;align-items:center;justify-content:center;border-radius:13px;background:#def5f7;color:#087e91;font-size:19px}.acr-person strong{display:block}.acr-person small{color:var(--muted)}
    .acr-status{display:inline-flex;border-radius:999px;padding:6px 10px;font-size:11px;font-weight:900}.acr-status-warning{background:#fff4d6;color:#8a5b00}.acr-status-success{background:#dcf8e9;color:#127343}.acr-status-danger{background:#ffe3e5;color:#a62736}.acr-status-secondary{background:#edf1f3;color:#667883}
    .acr-card-body{padding:18px 20px}.acr-times{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:12px}.acr-time-box{padding:13px 15px;border:1px solid var(--line);border-radius:15px;background:#f8fbfc}.acr-time-box.is-requested{border-color:#9ddde2;background:#effcfd}.acr-time-box.is-current{border-color:#c7d8df}.acr-time-box span{display:block;color:var(--muted);font-size:10px;font-weight:900;text-transform:uppercase}.acr-time-box strong{display:block;margin-top:6px;font-size:16px}.acr-reason{margin-top:14px;padding:12px 14px;border-left:3px solid var(--teal);border-radius:0 12px 12px 0;background:#f4fafb}.acr-reason span{display:block;color:var(--muted);font-size:10px;font-weight:900;text-transform:uppercase}.acr-reason p{margin:4px 0 0;white-space:pre-wrap}
    .acr-warning{margin-top:12px;padding:10px 12px;border-radius:11px;background:#fff7df;color:#7c5700;font-size:12px;font-weight:700}.acr-review{margin-top:15px;padding-top:15px;border-top:1px dashed var(--line)}.acr-review-grid{display:grid;grid-template-columns:1fr 1fr;gap:12px}.acr-field{margin-bottom:12px}.acr-field label{display:block;margin-bottom:5px;color:var(--muted);font-size:10px;font-weight:900;text-transform:uppercase}.acr-field input,.acr-field textarea{width:100%;border:1px solid var(--line);border-radius:11px;padding:9px 11px}.acr-actions{display:flex;flex-wrap:wrap;justify-content:flex-end;gap:9px}.acr-review-note{margin-top:13px;color:#526b77;font-size:13px}.acr-empty{padding:50px 20px;text-align:center;border:1px dashed #bfd2da;border-radius:20px;background:#fff;color:var(--muted)}
    .acr-attachments{margin-top:14px}.acr-attachments>span{display:block;margin-bottom:7px;color:var(--muted);font-size:10px;font-weight:900;text-transform:uppercase}.acr-attachment-list{display:flex;flex-wrap:wrap;gap:8px}.acr-attachment{display:inline-flex;align-items:center;gap:7px;max-width:100%;padding:8px 10px;border:1px solid #cfe1e7;border-radius:11px;background:#f7fbfc;color:#176277;text-decoration:none;font-size:12px;font-weight:700}.acr-attachment:hover{border-color:#7dcbd2;background:#ecfafb}.acr-attachment-name{max-width:240px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}.acr-attachment-size{color:#78909c;font-weight:500}
    @media(max-width:800px){.acr-hero{align-items:flex-start;flex-direction:column}.acr-times{grid-template-columns:1fr}.acr-review-grid{grid-template-columns:1fr}.acr-card-head{align-items:flex-start;flex-direction:column}}
</style>
@endpush

@section('content')
<div class="container-fluid tw:py-6 acr-page">
    @if(session('success'))
        <div class="alert alert-success border-0 rounded-4" role="alert"><i class="bi bi-check-circle me-2"></i>{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="alert alert-danger border-0 rounded-4" role="alert"><i class="bi bi-exclamation-circle me-2"></i>{{ session('error') }}</div>
    @endif
    @if($errors->any())
        <div class="alert alert-danger border-0 rounded-4" role="alert">
            @foreach($errors->all() as $error)<div><i class="bi bi-exclamation-circle me-2"></i>{{ $error }}</div>@endforeach
        </div>
    @endif

    <header class="acr-hero">
        <div>
            <div class="acr-kicker">ATTENDANCE CORRECTION WORKFLOW</div>
            <h1>Yêu cầu sửa chấm công</h1>
            <p>Đối chiếu dữ liệu gốc, giờ đề nghị và kết quả HR phê duyệt.</p>
        </div>
        <div class="acr-hero-actions">
            <a class="acr-btn {{ $tab === 'mine' ? 'acr-btn-light' : 'acr-btn-glass' }}" href="{{ route('hr.attendance-corrections.index', ['tab' => 'mine']) }}">
                <i class="bi bi-folder2-open"></i>Đơn của tôi
                @if($myPendingCount > 0)<span class="acr-count">{{ $myPendingCount }}</span>@endif
            </a>
            @if($canReview)
                <a class="acr-btn {{ $tab === 'approval' ? 'acr-btn-light' : 'acr-btn-glass' }}" href="{{ route('hr.attendance-corrections.index', ['tab' => 'approval', 'status' => 'pending']) }}">
                    <i class="bi bi-person-check"></i>HR duyệt
                    @if($pendingApprovalCount > 0)<span class="acr-count">{{ $pendingApprovalCount }}</span>@endif
                </a>
            @endif
            <a class="acr-btn acr-btn-glass" href="{{ route('hr.attendance.my') }}"><i class="bi bi-arrow-left"></i>Về chấm công</a>
        </div>
    </header>

    <form class="acr-filter" method="GET">
        <input type="hidden" name="tab" value="{{ $tab }}">
        <div>
            <label>Trạng thái</label>
            <select name="status">
                <option value="">Tất cả trạng thái</option>
                <option value="pending" @selected($status === 'pending')>Chờ HR duyệt</option>
                <option value="approved" @selected($status === 'approved')>Đã duyệt</option>
                <option value="rejected" @selected($status === 'rejected')>Từ chối</option>
                <option value="cancelled" @selected($status === 'cancelled')>Đã hủy</option>
            </select>
        </div>
        @if($tab === 'approval' && $canReview)
            <div>
                <label>Nhân viên</label>
                <select name="user_id">
                    <option value="">Tất cả nhân viên</option>
                    @foreach($employees as $employee)
                        <option value="{{ $employee->id }}" @selected((int) $userId === (int) $employee->id)>{{ $employee->name }}</option>
                    @endforeach
                </select>
            </div>
        @endif
        <button class="acr-btn acr-btn-primary" type="submit"><i class="bi bi-funnel"></i>Lọc dữ liệu</button>
    </form>

    <section class="acr-list">
        @forelse($corrections as $correction)
            @php
                $currentRecord = $correction->attendanceRecord;
                $currentIn = $currentRecord?->check_in_at?->format('Y-m-d H:i:s');
                $currentOut = $currentRecord?->check_out_at?->format('Y-m-d H:i:s');
                $originalIn = $correction->original_check_in_at?->format('Y-m-d H:i:s');
                $originalOut = $correction->original_check_out_at?->format('Y-m-d H:i:s');
                $sourceChanged = $currentRecord && ($currentIn !== $originalIn || $currentOut !== $originalOut) && $correction->status === 'pending';
            @endphp
            <article class="acr-card">
                <div class="acr-card-head">
                    <div class="acr-person">
                        <div class="acr-avatar"><i class="bi bi-person"></i></div>
                        <div>
                            <strong>{{ $correction->user?->name ?? 'Nhân viên' }}</strong>
                            <small>{{ $correction->user?->department?->name ?? 'Chưa có phòng ban' }} · Ngày công {{ $correction->work_date->format('d/m/Y') }} · Đơn #{{ $correction->id }}</small>
                        </div>
                    </div>
                    <span class="acr-status acr-status-{{ $correction->status_badge_class }}">{{ $correction->status_label }}</span>
                </div>

                <div class="acr-card-body">
                    <div class="acr-times">
                        <div class="acr-time-box">
                            <span>Dữ liệu khi gửi đơn</span>
                            <strong>{{ $correction->original_check_in_at?->format('H:i') ?? '—' }} → {{ $correction->original_check_out_at?->format('H:i') ?? '—' }}</strong>
                        </div>
                        <div class="acr-time-box is-requested">
                            <span>Nhân viên đề nghị</span>
                            <strong>{{ $correction->requested_check_in_at?->format('H:i') ?? '—' }} → {{ $correction->requested_check_out_at?->format('H:i') ?? '—' }}</strong>
                        </div>
                        <div class="acr-time-box is-current">
                            <span>{{ $correction->status === 'approved' ? 'Kết quả đã áp dụng' : 'Bản công hiện tại' }}</span>
                            <strong>{{ $currentRecord?->check_in_at?->format('H:i') ?? '—' }} → {{ $currentRecord?->check_out_at?->format('H:i') ?? '—' }}</strong>
                        </div>
                    </div>

                    <div class="acr-reason"><span>Lý do điều chỉnh</span><p>{{ $correction->reason }}</p></div>

                    @if($correction->attachments->isNotEmpty())
                        <div class="acr-attachments">
                            <span>Ảnh/file giải trình ({{ $correction->attachments->count() }})</span>
                            <div class="acr-attachment-list">
                                @foreach($correction->attachments as $attachment)
                                    <a class="acr-attachment" href="{{ route('hr.attendance-corrections.attachments.download', [$correction, $attachment]) }}">
                                        <i class="bi bi-paperclip"></i>
                                        <span class="acr-attachment-name">{{ $attachment->original_name }}</span>
                                        <span class="acr-attachment-size">{{ $attachment->file_size >= 1048576 ? number_format($attachment->file_size / 1048576, 1, ',', '.').' MB' : number_format(max($attachment->file_size / 1024, 0.1), 1, ',', '.').' KB' }}</span>
                                    </a>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    @if($sourceChanged)
                        <div class="acr-warning"><i class="bi bi-exclamation-triangle me-1"></i>Bản công đã thay đổi sau lúc nhân viên gửi đơn. HR cần đối chiếu cột “Bản công hiện tại” trước khi duyệt.</div>
                    @endif

                    @if($correction->review_note || $correction->reviewer)
                        <div class="acr-review-note">
                            <strong>HR xử lý:</strong> {{ $correction->reviewer?->name ?? '—' }}
                            @if($correction->reviewed_at) lúc {{ $correction->reviewed_at->format('H:i d/m/Y') }} @endif
                            @if($correction->review_note) · {{ $correction->review_note }} @endif
                        </div>
                    @endif

                    @if($correction->status === 'pending' && $tab === 'approval' && $canReview)
                        <div class="acr-review">
                            <form method="POST" action="{{ route('hr.attendance-corrections.approve', $correction) }}">
                                @csrf
                                <div class="acr-review-grid">
                                    <div class="acr-field">
                                        <label>Giờ check-in HR chốt *</label>
                                        <input type="time" name="approved_check_in_time" value="{{ $correction->requested_check_in_at?->format('H:i') }}" required>
                                    </div>
                                    <div class="acr-field">
                                        <label>Giờ check-out HR chốt</label>
                                        <input type="time" name="approved_check_out_time" value="{{ $correction->requested_check_out_at?->format('H:i') }}">
                                    </div>
                                </div>
                                <div class="acr-field">
                                    <label>Ghi chú duyệt</label>
                                    <textarea name="review_note" rows="2" maxlength="1500" placeholder="Không bắt buộc">{{ old('review_note') }}</textarea>
                                </div>
                                <div class="acr-actions">
                                    <button class="acr-btn acr-btn-primary" type="submit"><i class="bi bi-check2-circle"></i>Duyệt và cập nhật bảng công</button>
                                </div>
                            </form>

                            <form class="tw:mt-2" method="POST" action="{{ route('hr.attendance-corrections.reject', $correction) }}" onsubmit="return this.review_note.value.trim() !== '' || (alert('Vui lòng nhập lý do từ chối.'), false)">
                                @csrf
                                <div class="acr-field">
                                    <label>Lý do từ chối *</label>
                                    <textarea name="review_note" rows="2" maxlength="1500" required placeholder="Nhập lý do để nhân viên biết và gửi lại đúng thông tin"></textarea>
                                </div>
                                <div class="acr-actions"><button class="acr-btn acr-btn-danger" type="submit"><i class="bi bi-x-circle"></i>Từ chối yêu cầu</button></div>
                            </form>
                        </div>
                    @elseif($correction->status === 'pending' && (int) $correction->user_id === (int) auth()->id())
                        <div class="acr-actions tw:mt-4">
                            <form method="POST" action="{{ route('hr.attendance-corrections.cancel', $correction) }}" onsubmit="return confirm('Hủy yêu cầu sửa chấm công này?')">
                                @csrf
                                <button class="acr-btn acr-btn-secondary" type="submit"><i class="bi bi-x-lg"></i>Hủy yêu cầu</button>
                            </form>
                        </div>
                    @endif
                </div>
            </article>
        @empty
            <div class="acr-empty"><i class="bi bi-inbox fs-2 tw:block tw:mb-2"></i>Không có yêu cầu sửa chấm công phù hợp bộ lọc.</div>
        @endforelse
    </section>

    @if($corrections->hasPages())
        <div class="tw:mt-6">{{ $corrections->links() }}</div>
    @endif
</div>
@endsection
