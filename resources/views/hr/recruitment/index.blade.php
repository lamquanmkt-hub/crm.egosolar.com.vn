@extends('layouts.app')

@section('content')
<style>
    .rec-wrap{padding:0 4px 32px;font-family:"Be Vietnam Pro",system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif}
    .rec-head{display:flex;align-items:flex-end;justify-content:space-between;gap:14px;margin-bottom:14px}
    .rec-title{margin:0;font-size:25px;font-weight:950;color:#0f172a;letter-spacing:-.04em}
    .rec-sub{margin-top:5px;font-size:13px;font-weight:750;color:#64748b;max-width:850px}
    .rec-head-actions{display:flex;gap:8px;flex-wrap:wrap;justify-content:flex-end}
    .rec-tabs{display:flex;flex-wrap:wrap;gap:8px;margin:12px 0}
    .rec-tab{display:inline-flex;align-items:center;gap:7px;height:38px;padding:0 14px;border-radius:999px;border:1px solid #dbe3ef;background:#fff;color:#334155;text-decoration:none;font-size:12px;font-weight:900;box-shadow:0 8px 20px rgba(15,23,42,.04)}
    .rec-tab.active{background:#0f766e;color:#fff;border-color:#0f766e;box-shadow:0 12px 26px rgba(15,118,110,.18)}
    .rec-stats{display:grid;grid-template-columns:repeat(7,minmax(0,1fr));gap:10px;margin:12px 0 14px}
    .rec-stat{position:relative;overflow:hidden;background:#fff;border:1px solid #e2e8f0;border-radius:18px;padding:14px 15px;box-shadow:0 12px 28px rgba(15,23,42,.05)}
    .rec-stat:after{content:"";position:absolute;right:-22px;top:-24px;width:78px;height:78px;border-radius:999px;background:linear-gradient(135deg,rgba(15,118,110,.12),rgba(14,165,233,.08))}
    .rec-stat-label{position:relative;z-index:1;font-size:11px;font-weight:950;text-transform:uppercase;color:#64748b}
    .rec-stat-value{position:relative;z-index:1;margin-top:7px;font-size:26px;font-weight:950;color:#0f766e}
    .rec-card{background:#fff;border:1px solid #e2e8f0;border-radius:20px;box-shadow:0 16px 36px rgba(15,23,42,.055);overflow:hidden;margin-bottom:14px}
    .rec-card-head{padding:15px 17px;border-bottom:1px solid #edf2f7;background:linear-gradient(180deg,#ffffff,#f8fafc);display:flex;justify-content:space-between;gap:12px;align-items:center}
    .rec-card-title{margin:0;font-size:16px;font-weight:950;color:#0f172a}
    .rec-card-note{margin-top:3px;font-size:12px;font-weight:700;color:#64748b}
    .rec-card-body{padding:16px 17px}
    .rec-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:10px}
    .rec-grid-2{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px}
    .rec-field{display:flex;flex-direction:column;gap:6px}
    .rec-field.full{grid-column:1/-1}
    .rec-label{font-size:12px;font-weight:900;color:#475569}
    .rec-input,.rec-select,.rec-textarea{width:100%;border:1px solid #dbe3ef;border-radius:12px;background:#fff;color:#0f172a;font-size:12px;font-weight:750;outline:none}
    .rec-input,.rec-select{height:38px;padding:0 11px}
    .rec-textarea{min-height:76px;padding:10px 11px;resize:vertical}
    .rec-input:focus,.rec-select:focus,.rec-textarea:focus{border-color:#14b8a6;box-shadow:0 0 0 3px rgba(20,184,166,.12)}
    .rec-btn,.rec-btn-outline,.rec-btn-danger{height:36px;border-radius:11px;padding:0 13px;border:1px solid transparent;display:inline-flex;align-items:center;justify-content:center;gap:6px;font-size:12px;font-weight:900;text-decoration:none;cursor:pointer;white-space:nowrap}
    .rec-btn{background:#0f766e;color:#fff;box-shadow:0 9px 18px rgba(15,118,110,.16)}
    .rec-btn-outline{background:#fff;color:#0f172a;border-color:#dbe3ef}
    .rec-btn-danger{background:#fff1f2;color:#be123c;border-color:#fecdd3}
    .rec-form-foot{margin-top:12px;display:flex;justify-content:flex-end;gap:8px;flex-wrap:wrap}
    .rec-table-wrap{border:1px solid #e2e8f0;border-radius:16px;overflow:auto;background:#fff}
    .rec-table{width:100%;border-collapse:collapse;min-width:1350px}
    .rec-table.sm{min-width:1100px}
    .rec-table th{background:#f1f5f9;color:#334155;font-size:11px;text-transform:uppercase;letter-spacing:.03em;font-weight:950;padding:10px;border-bottom:1px solid #e2e8f0;text-align:left;white-space:nowrap}
    .rec-table td{padding:9px 10px;border-bottom:1px solid #edf2f7;vertical-align:top;font-size:12px;font-weight:700;color:#0f172a}
    .rec-table tr:last-child td{border-bottom:0}
    .rec-actions{display:flex;gap:6px;flex-wrap:wrap}
    .rec-pill{display:inline-flex;align-items:center;height:27px;border-radius:999px;padding:0 10px;border:1px solid #ccfbf1;background:#ecfeff;color:#0f766e;font-size:11px;font-weight:950;white-space:nowrap}
    .rec-pill.gray{background:#f8fafc;color:#475569;border-color:#e2e8f0}
    .rec-pill.red{background:#fff1f2;color:#be123c;border-color:#fecdd3}
    .rec-pill.amber{background:#fffbeb;color:#b45309;border-color:#fde68a}
    .rec-pill.green{background:#f0fdf4;color:#15803d;border-color:#bbf7d0}
    .rec-pipeline{display:flex;flex-wrap:wrap;gap:8px;margin:8px 0 14px}
    .rec-step{display:inline-flex;align-items:center;gap:7px;border:1px solid #dbe3ef;background:#fff;border-radius:999px;padding:8px 11px;font-size:12px;font-weight:900;color:#334155}
    .rec-step span{display:inline-flex;width:22px;height:22px;border-radius:999px;background:#0f766e;color:#fff;align-items:center;justify-content:center;font-size:11px}
    .rec-alert{margin-bottom:12px;padding:10px 12px;border:1px solid #bbf7d0;background:#f0fdf4;color:#166534;border-radius:14px;font-size:12px;font-weight:850}
    .rec-error{margin-bottom:12px;padding:10px 12px;border:1px solid #fecaca;background:#fff1f2;color:#be123c;border-radius:14px;font-size:12px;font-weight:850}
    .rec-empty{padding:14px;border:1px dashed #cbd5e1;border-radius:14px;background:#f8fafc;color:#64748b;font-size:13px;font-weight:800;text-align:center}
    .rec-mini-row{display:flex;align-items:center;justify-content:space-between;gap:12px;border-bottom:1px dashed #e2e8f0;padding:10px 0;font-size:13px;font-weight:850;color:#334155}
    .rec-mini-row:last-child{border-bottom:0}
    .rec-report-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px}
    .rec-small{font-size:11px;color:#64748b;font-weight:800;line-height:1.45;margin-top:5px}
    .rec-link{color:#0f766e;font-weight:950;text-decoration:none}
    @media(max-width:1280px){.rec-stats{grid-template-columns:repeat(4,minmax(0,1fr))}.rec-grid{grid-template-columns:repeat(2,minmax(0,1fr))}}
    @media(max-width:760px){.rec-head{display:block}.rec-stats,.rec-grid,.rec-grid-2,.rec-report-grid{grid-template-columns:1fr}.rec-tab,.rec-btn,.rec-btn-outline,.rec-btn-danger{width:100%}.rec-tabs{display:grid;grid-template-columns:1fr}.rec-head-actions{margin-top:10px;justify-content:stretch}}
</style>

@php
    $active = $active ?? 'reports';
    $statusPill = [
        'new' => 'gray',
        'screening' => 'amber',
        'contacted' => '',
        'not_fit' => 'red',
        'interview_scheduled' => 'amber',
        'interviewing' => 'amber',
        'interview_passed' => 'green',
        'interview_failed' => 'red',
        'offer' => '',
        'hired' => 'green',
        'onboarding' => 'green',
        'archived' => 'gray',
        'rejected' => 'red',
    ];
    $interviewPill = [
        'scheduled' => 'amber',
        'interviewing' => 'amber',
        'rescheduled' => 'amber',
        'done' => 'green',
        'cancelled' => 'red',
        'no_show' => 'red',
    ];
    $offerPill = [
        'draft' => 'gray',
        'sent' => 'amber',
        'accepted' => 'green',
        'rejected' => 'red',
        'onboarded' => 'green',
        'archived' => 'gray',
    ];
@endphp

<div class="rec-wrap">
    <div class="rec-head">
        <div>
            <h1 class="rec-title">Tuyển dụng ứng viên</h1>
            <div class="rec-sub">Bám đúng 7 bước: yêu cầu tuyển dụng → tìm kiếm & sàng lọc → lịch phỏng vấn → đánh giá → mời nhận việc → tiếp nhận nhân sự mới → lưu hồ sơ & báo cáo.</div>
        </div>
        <div class="rec-head-actions">
            <a class="rec-btn-outline" href="{{ route('hr.operations.index') }}">← HC & Vận hành</a>
            <a class="rec-btn-outline" href="{{ route('hr.office-supply-process.index') }}">Văn phòng phẩm</a>
        </div>
    </div>

    <div class="rec-tabs">
        <a class="rec-tab {{ $active === 'requests' ? 'active' : '' }}" href="{{ route('hr.recruitment.requests') }}">1. Y/c tuyển dụng</a>
        <a class="rec-tab {{ $active === 'screening' ? 'active' : '' }}" href="{{ route('hr.recruitment.screening') }}">2. Sàng lọc ứng viên</a>
        <a class="rec-tab {{ $active === 'interviews' ? 'active' : '' }}" href="{{ route('hr.recruitment.interviews') }}">3. Lịch PV</a>
        <a class="rec-tab {{ $active === 'evaluations' ? 'active' : '' }}" href="{{ route('hr.recruitment.evaluations') }}">4. Đánh giá PV</a>
        <a class="rec-tab {{ $active === 'offers' ? 'active' : '' }}" href="{{ route('hr.recruitment.offers') }}">5. Mời nhận việc</a>
        <a class="rec-tab {{ $active === 'onboarding' ? 'active' : '' }}" href="{{ route('hr.recruitment.onboarding') }}">6. Tiếp nhận NS mới</a>
        <a class="rec-tab {{ in_array($active, ['archives','reports'], true) ? 'active' : '' }}" href="{{ route('hr.recruitment.archives') }}">7. Lưu hồ sơ & Báo cáo</a>
    </div>

    @if(session('success'))
        <div class="rec-alert">{{ session('success') }}</div>
    @endif

    @if($errors->any())
        <div class="rec-error">{{ $errors->first() }}</div>
    @endif

    <div class="rec-stats">
        <div class="rec-stat"><div class="rec-stat-label">Y/c tuyển dụng</div><div class="rec-stat-value">{{ $stats['requests'] ?? 0 }}</div></div>
        <div class="rec-stat"><div class="rec-stat-label">Đã duyệt</div><div class="rec-stat-value">{{ $stats['approved_requests'] ?? 0 }}</div></div>
        <div class="rec-stat"><div class="rec-stat-label">Ứng viên</div><div class="rec-stat-value">{{ $stats['candidates'] ?? 0 }}</div></div>
        <div class="rec-stat"><div class="rec-stat-label">Đã liên hệ</div><div class="rec-stat-value">{{ $stats['contacted'] ?? 0 }}</div></div>
        <div class="rec-stat"><div class="rec-stat-label">Lịch PV</div><div class="rec-stat-value">{{ $stats['interviews'] ?? 0 }}</div></div>
        <div class="rec-stat"><div class="rec-stat-label">Đã nhận việc</div><div class="rec-stat-value">{{ $stats['hired'] ?? 0 }}</div></div>
        <div class="rec-stat"><div class="rec-stat-label">Lưu / Từ chối</div><div class="rec-stat-value">{{ ($stats['archived'] ?? 0) + ($stats['rejected'] ?? 0) }}</div></div>
    </div>

    @if($active === 'reports')
        <div class="rec-card">
            <div class="rec-card-head">
                <div>
                    <h3 class="rec-card-title">Luồng xử lý ứng viên</h3>
                    <div class="rec-card-note">Ứng viên mới → Sàng lọc CV → Đã liên hệ → Đặt lịch PV → Đang PV → Đạt PV → Mời nhận việc → Đã nhận việc / Lưu hồ sơ / Từ chối.</div>
                </div>
            </div>
            <div class="rec-card-body">
                <div class="rec-pipeline">
                    @foreach($candidateStatuses as $key => $label)
                        <div class="rec-step"><span>{{ $statusStats[$key] ?? 0 }}</span>{{ $label }}</div>
                    @endforeach
                </div>

                <div class="rec-report-grid">
                    <div class="rec-card" style="box-shadow:none;margin:0;">
                        <div class="rec-card-head"><h3 class="rec-card-title">Theo nguồn ứng viên</h3></div>
                        <div class="rec-card-body">
                            @foreach($sources as $source)
                                <div class="rec-mini-row"><span>{{ $source }}</span><strong>{{ $sourceStats[$source] ?? 0 }}</strong></div>
                            @endforeach
                        </div>
                    </div>

                    <div class="rec-card" style="box-shadow:none;margin:0;">
                        <div class="rec-card-head"><h3 class="rec-card-title">Tổng quan nhanh</h3></div>
                        <div class="rec-card-body">
                            <div class="rec-mini-row"><span>Tổng ứng viên</span><strong>{{ $stats['candidates'] }}</strong></div>
                            <div class="rec-mini-row"><span>Đang sàng lọc</span><strong>{{ $stats['screening'] }}</strong></div>
                            <div class="rec-mini-row"><span>Đã có lịch PV</span><strong>{{ $stats['interviews'] }}</strong></div>
                            <div class="rec-mini-row"><span>Đã đánh giá PV</span><strong>{{ $stats['evaluated'] }}</strong></div>
                            <div class="rec-mini-row"><span>Đã tạo thư mời</span><strong>{{ $stats['offers'] }}</strong></div>
                            <div class="rec-mini-row"><span>Không đạt / từ chối</span><strong>{{ $stats['rejected'] }}</strong></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @endif

    @if($active === 'requests')
        <div class="rec-card">
            <div class="rec-card-head">
                <div>
                    <h3 class="rec-card-title">B1. Tạo yêu cầu tuyển dụng</h3>
                    <div class="rec-card-note">Nhập số lượng, vị trí, JD, mức lương, ngày cần và gửi duyệt.</div>
                </div>
            </div>
            <div class="rec-card-body">
                <form method="POST" action="{{ route('hr.recruitment.requests.store') }}">
                    @csrf
                    <div class="rec-grid">
                        <div class="rec-field"><label class="rec-label">Bộ phận cần tuyển *</label><input class="rec-input" name="department" required></div>
                        <div class="rec-field"><label class="rec-label">Vị trí *</label><input class="rec-input" name="position" required></div>
                        <div class="rec-field"><label class="rec-label">Số lượng *</label><input class="rec-input" type="number" min="1" name="quantity" value="1" required></div>
                        <div class="rec-field">
                            <label class="rec-label">Lý do tuyển</label>
                            <select class="rec-select" name="reason">
                                <option value="Tuyển mới">Tuyển mới</option>
                                <option value="Thay thế">Thay thế</option>
                                <option value="Bổ sung nhân sự">Bổ sung nhân sự</option>
                            </select>
                        </div>
                        <div class="rec-field"><label class="rec-label">Ngày cần nhân sự</label><input class="rec-input" type="date" name="needed_date"></div>
                        <div class="rec-field"><label class="rec-label">Mức lương dự kiến</label><input class="rec-input" name="expected_salary" placeholder="VD: 8-12 triệu"></div>
                        <div class="rec-field">
                            <label class="rec-label">Phê duyệt</label>
                            <select class="rec-select" name="approval_status">
                                @foreach($approvalStatuses as $key => $label)
                                    <option value="{{ $key }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="rec-field"><label class="rec-label">Ghi chú</label><input class="rec-input" name="note"></div>
                        <div class="rec-field full"><label class="rec-label">Yêu cầu ứng viên</label><textarea class="rec-textarea" name="requirements"></textarea></div>
                        <div class="rec-field full"><label class="rec-label">JD / Mô tả công việc</label><textarea class="rec-textarea" name="job_description"></textarea></div>
                    </div>
                    <div class="rec-form-foot"><button class="rec-btn" type="submit">+ Tạo yêu cầu</button></div>
                </form>
            </div>
        </div>

        <div class="rec-card">
            <div class="rec-card-head"><h3 class="rec-card-title">Danh sách yêu cầu tuyển dụng</h3></div>
            <div class="rec-card-body">
                @if($requests->count())
                    <div class="rec-table-wrap">
                        <table class="rec-table">
                            <thead><tr><th>Bộ phận</th><th>Vị trí</th><th>SL</th><th>Lý do</th><th>Ngày cần</th><th>Lương</th><th>Phê duyệt</th><th>Yêu cầu</th><th>JD</th><th>Thao tác</th></tr></thead>
                            <tbody>
                                @foreach($requests as $row)
                                    <tr>
                                        <td><form id="req{{ $row->id }}" method="POST" action="{{ route('hr.recruitment.requests.update', $row->id) }}">@csrf @method('PUT')<input class="rec-input" name="department" value="{{ $row->department }}" required></form></td>
                                        <td><input form="req{{ $row->id }}" class="rec-input" name="position" value="{{ $row->position }}" required></td>
                                        <td><input form="req{{ $row->id }}" class="rec-input" type="number" min="1" name="quantity" value="{{ $row->quantity }}" required></td>
                                        <td><input form="req{{ $row->id }}" class="rec-input" name="reason" value="{{ $row->reason }}"></td>
                                        <td><input form="req{{ $row->id }}" class="rec-input" type="date" name="needed_date" value="{{ $row->needed_date }}"></td>
                                        <td><input form="req{{ $row->id }}" class="rec-input" name="expected_salary" value="{{ $row->expected_salary }}"></td>
                                        <td>
                                            <select form="req{{ $row->id }}" class="rec-select" name="approval_status">
                                                @foreach($approvalStatuses as $key => $label)
                                                    <option value="{{ $key }}" @selected($row->approval_status === $key)>{{ $label }}</option>
                                                @endforeach
                                            </select>
                                        </td>
                                        <td><textarea form="req{{ $row->id }}" class="rec-textarea" name="requirements">{{ $row->requirements }}</textarea></td>
                                        <td><textarea form="req{{ $row->id }}" class="rec-textarea" name="job_description">{{ $row->job_description }}</textarea></td>
                                        <td>
                                            <div class="rec-actions">
                                                <button form="req{{ $row->id }}" class="rec-btn" type="submit">Lưu</button>
                                                <form method="POST" action="{{ route('hr.recruitment.requests.destroy', $row->id) }}" onsubmit="return confirm('Xoá yêu cầu này?')">@csrf @method('DELETE')<button class="rec-btn-danger">Xoá</button></form>
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <div class="rec-empty">Chưa có yêu cầu tuyển dụng.</div>
                @endif
            </div>
        </div>
    @endif

    @if($active === 'screening')
        <div class="rec-card">
            <div class="rec-card-head">
                <div>
                    <h3 class="rec-card-title">B2. Tìm kiếm & sàng lọc ứng viên</h3>
                    <div class="rec-card-note">Phân loại nguồn TopCV, Facebook, LinkedIn... lọc CV, liên hệ qua Phone/Mail/Zalo và đánh giá mức độ phù hợp.</div>
                </div>
            </div>
            <div class="rec-card-body">
                <form method="POST" action="{{ route('hr.recruitment.candidates.store') }}">
                    @csrf
                    <div class="rec-grid">
                        <div class="rec-field"><label class="rec-label">Họ tên *</label><input class="rec-input" name="full_name" required></div>
                        <div class="rec-field"><label class="rec-label">SĐT</label><input class="rec-input" name="phone"></div>
                        <div class="rec-field"><label class="rec-label">Email</label><input class="rec-input" name="email"></div>
                        <div class="rec-field"><label class="rec-label">Zalo</label><input class="rec-input" name="zalo"></div>
                        <div class="rec-field"><label class="rec-label">Vị trí ứng tuyển</label><input class="rec-input" name="position"></div>
                        <div class="rec-field">
                            <label class="rec-label">Nguồn ứng viên</label>
                            <select class="rec-select" name="source">
                                @foreach($sources as $source)
                                    <option value="{{ $source }}">{{ $source }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="rec-field"><label class="rec-label">Link CV / hồ sơ</label><input class="rec-input" name="cv_link" placeholder="Dán link CV nếu có"></div>
                        <div class="rec-field">
                            <label class="rec-label">Trạng thái</label>
                            <select class="rec-select" name="status">
                                @foreach($candidateStatuses as $key => $label)
                                    <option value="{{ $key }}" @selected($key === 'screening')>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="rec-field">
                            <label class="rec-label">Kênh liên hệ</label>
                            <select class="rec-select" name="contact_channel">
                                <option value="">Chưa liên hệ</option>
                                @foreach($contactChannels as $key => $label)
                                    <option value="{{ $key }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="rec-field"><label class="rec-label">Thời gian liên hệ</label><input class="rec-input" type="datetime-local" name="contacted_at"></div>
                        <div class="rec-field">
                            <label class="rec-label">Mức độ phù hợp</label>
                            <select class="rec-select" name="suitability">
                                <option value="">Chưa đánh giá</option>
                                @foreach($suitabilityLevels as $key => $label)
                                    <option value="{{ $key }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="rec-field">
                            <label class="rec-label">Yêu cầu tuyển dụng</label>
                            <select class="rec-select" name="recruitment_request_id">
                                <option value="">Không gắn</option>
                                @foreach($requests as $req)
                                    <option value="{{ $req->id }}">{{ $req->position }} - {{ $req->department }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="rec-field full"><label class="rec-label">Ghi chú sàng lọc / lý do chưa phù hợp</label><textarea class="rec-textarea" name="note"></textarea></div>
                    </div>
                    <div class="rec-form-foot"><button class="rec-btn" type="submit">+ Thêm ứng viên</button></div>
                </form>
            </div>
        </div>

        <div class="rec-card">
            <div class="rec-card-head"><h3 class="rec-card-title">Kho ứng viên & tình trạng liên hệ</h3></div>
            <div class="rec-card-body">
                @if($candidates->count())
                    <div class="rec-table-wrap">
                        <table class="rec-table">
                            <thead><tr><th>Ứng viên</th><th>Liên hệ</th><th>Vị trí</th><th>Nguồn</th><th>CV</th><th>Kênh / giờ liên hệ</th><th>Mức phù hợp</th><th>Trạng thái</th><th>Y/c</th><th>Ghi chú / lý do</th><th>Thao tác</th></tr></thead>
                            <tbody>
                                @foreach($candidates as $row)
                                    <tr>
                                        <td><form id="can{{ $row->id }}" method="POST" action="{{ route('hr.recruitment.candidates.update', $row->id) }}">@csrf @method('PUT')<input class="rec-input" name="full_name" value="{{ $row->full_name }}" required></form></td>
                                        <td>
                                            <input form="can{{ $row->id }}" class="rec-input" name="phone" value="{{ $row->phone }}" placeholder="SĐT">
                                            <div style="height:6px"></div>
                                            <input form="can{{ $row->id }}" class="rec-input" name="email" value="{{ $row->email }}" placeholder="Email">
                                            <div style="height:6px"></div>
                                            <input form="can{{ $row->id }}" class="rec-input" name="zalo" value="{{ $row->zalo ?? '' }}" placeholder="Zalo">
                                        </td>
                                        <td><input form="can{{ $row->id }}" class="rec-input" name="position" value="{{ $row->position }}"></td>
                                        <td>
                                            <select form="can{{ $row->id }}" class="rec-select" name="source">
                                                <option value="">--</option>
                                                @foreach($sources as $source)
                                                    <option value="{{ $source }}" @selected(($row->source ?? '') === $source)>{{ $source }}</option>
                                                @endforeach
                                            </select>
                                        </td>
                                        <td>
                                            <input form="can{{ $row->id }}" class="rec-input" name="cv_link" value="{{ $row->cv_link ?? '' }}" placeholder="Link CV">
                                            @if(!empty($row->cv_link))
                                                <div class="rec-small"><a class="rec-link" href="{{ $row->cv_link }}" target="_blank">Mở CV</a></div>
                                            @endif
                                        </td>
                                        <td>
                                            <select form="can{{ $row->id }}" class="rec-select" name="contact_channel">
                                                <option value="">Chưa liên hệ</option>
                                                @foreach($contactChannels as $key => $label)
                                                    <option value="{{ $key }}" @selected(($row->contact_channel ?? '') === $key)>{{ $label }}</option>
                                                @endforeach
                                            </select>
                                            <div style="height:6px"></div>
                                            <input form="can{{ $row->id }}" class="rec-input" type="datetime-local" name="contacted_at" value="{{ !empty($row->contacted_at) ? date('Y-m-d\TH:i', strtotime($row->contacted_at)) : '' }}">
                                        </td>
                                        <td>
                                            <select form="can{{ $row->id }}" class="rec-select" name="suitability">
                                                <option value="">Chưa đánh giá</option>
                                                @foreach($suitabilityLevels as $key => $label)
                                                    <option value="{{ $key }}" @selected(($row->suitability ?? '') === $key)>{{ $label }}</option>
                                                @endforeach
                                            </select>
                                        </td>
                                        <td>
                                            <select form="can{{ $row->id }}" class="rec-select" name="status">
                                                @foreach($candidateStatuses as $key => $label)
                                                    <option value="{{ $key }}" @selected(($row->status ?? '') === $key)>{{ $label }}</option>
                                                @endforeach
                                            </select>
                                        </td>
                                        <td>
                                            <select form="can{{ $row->id }}" class="rec-select" name="recruitment_request_id">
                                                <option value="">Không gắn</option>
                                                @foreach($requests as $req)
                                                    <option value="{{ $req->id }}" @selected((int)($row->recruitment_request_id ?? 0) === (int)$req->id)>{{ $req->position }} - {{ $req->department }}</option>
                                                @endforeach
                                            </select>
                                        </td>
                                        <td>
                                            <textarea form="can{{ $row->id }}" class="rec-textarea" name="note" placeholder="Ghi chú">{{ $row->note }}</textarea>
                                            <textarea form="can{{ $row->id }}" class="rec-textarea" name="reject_reason" placeholder="Lý do từ chối / chưa phù hợp" style="margin-top:6px">{{ $row->reject_reason ?? '' }}</textarea>
                                        </td>
                                        <td>
                                            <div class="rec-actions">
                                                <button form="can{{ $row->id }}" class="rec-btn" type="submit">Lưu</button>
                                                <form method="POST" action="{{ route('hr.recruitment.candidates.destroy', $row->id) }}" onsubmit="return confirm('Xoá ứng viên này?')">@csrf @method('DELETE')<button class="rec-btn-danger">Xoá</button></form>
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <div class="rec-empty">Chưa có ứng viên.</div>
                @endif
            </div>
        </div>
    @endif

    @if($active === 'interviews')
        <div class="rec-card">
            <div class="rec-card-head">
                <div>
                    <h3 class="rec-card-title">B3. Lên lịch mời phỏng vấn</h3>
                    <div class="rec-card-note">Theo dõi đặt lịch PV, đang PV, hẹn ngày khác, huỷ lịch và lý do huỷ.</div>
                </div>
            </div>
            <div class="rec-card-body">
                <form method="POST" action="{{ route('hr.recruitment.interviews.store') }}">
                    @csrf
                    <div class="rec-grid">
                        <div class="rec-field">
                            <label class="rec-label">Ứng viên *</label>
                            <select class="rec-select" name="candidate_id" required>
                                @foreach($candidates as $can)
                                    <option value="{{ $can->id }}">{{ $can->full_name }} - {{ $can->position }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="rec-field"><label class="rec-label">Ngày giờ PV</label><input class="rec-input" type="datetime-local" name="interview_at"></div>
                        <div class="rec-field"><label class="rec-label">Địa điểm / Link họp</label><input class="rec-input" name="location" placeholder="Văn phòng / Google Meet"></div>
                        <div class="rec-field"><label class="rec-label">Hình thức</label><select class="rec-select" name="interview_form"><option value="Offline">Offline</option><option value="Online">Online</option><option value="Điện thoại">Điện thoại</option></select></div>
                        <div class="rec-field"><label class="rec-label">Người phỏng vấn</label><input class="rec-input" name="interviewer"></div>
                        <div class="rec-field">
                            <label class="rec-label">Trạng thái lịch</label>
                            <select class="rec-select" name="status">
                                @foreach($interviewStatuses as $key => $label)
                                    <option value="{{ $key }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="rec-field full"><label class="rec-label">Ghi chú / lý do hủy / hẹn ngày khác</label><textarea class="rec-textarea" name="cancel_reason"></textarea></div>
                    </div>
                    <div class="rec-form-foot"><button class="rec-btn" type="submit">+ Đặt lịch PV</button></div>
                </form>
            </div>
        </div>

        <div class="rec-card">
            <div class="rec-card-head"><h3 class="rec-card-title">Danh sách lịch phỏng vấn</h3></div>
            <div class="rec-card-body">
                @if($interviews->count())
                    <div class="rec-table-wrap">
                        <table class="rec-table sm">
                            <thead><tr><th>Ứng viên</th><th>Ngày giờ</th><th>Địa điểm</th><th>Hình thức</th><th>Người PV</th><th>Trạng thái</th><th>Lý do / ghi chú</th><th>Thao tác</th></tr></thead>
                            <tbody>
                                @foreach($interviews as $row)
                                    <tr>
                                        <td>
                                            <form id="int{{ $row->id }}" method="POST" action="{{ route('hr.recruitment.interviews.update', $row->id) }}">@csrf @method('PUT')
                                                <select class="rec-select" name="candidate_id" required>
                                                    @foreach($candidates as $can)
                                                        <option value="{{ $can->id }}" @selected((int)$row->candidate_id === (int)$can->id)>{{ $can->full_name }} - {{ $can->position }}</option>
                                                    @endforeach
                                                </select>
                                            </form>
                                        </td>
                                        <td><input form="int{{ $row->id }}" class="rec-input" type="datetime-local" name="interview_at" value="{{ !empty($row->interview_at) ? date('Y-m-d\TH:i', strtotime($row->interview_at)) : '' }}"></td>
                                        <td><input form="int{{ $row->id }}" class="rec-input" name="location" value="{{ $row->location }}"></td>
                                        <td><input form="int{{ $row->id }}" class="rec-input" name="interview_form" value="{{ $row->interview_form }}"></td>
                                        <td><input form="int{{ $row->id }}" class="rec-input" name="interviewer" value="{{ $row->interviewer }}"></td>
                                        <td>
                                            <select form="int{{ $row->id }}" class="rec-select" name="status">
                                                @foreach($interviewStatuses as $key => $label)
                                                    <option value="{{ $key }}" @selected(($row->status ?? 'scheduled') === $key)>{{ $label }}</option>
                                                @endforeach
                                            </select>
                                        </td>
                                        <td>
                                            <textarea form="int{{ $row->id }}" class="rec-textarea" name="cancel_reason" placeholder="Lý do hủy / hẹn lại">{{ $row->cancel_reason ?? '' }}</textarea>
                                            <textarea form="int{{ $row->id }}" class="rec-textarea" name="note" placeholder="Ghi chú" style="margin-top:6px">{{ $row->note }}</textarea>
                                        </td>
                                        <td>
                                            <div class="rec-actions">
                                                <button form="int{{ $row->id }}" class="rec-btn" type="submit">Lưu</button>
                                                <form method="POST" action="{{ route('hr.recruitment.interviews.destroy', $row->id) }}" onsubmit="return confirm('Xoá lịch PV này?')">@csrf @method('DELETE')<button class="rec-btn-danger">Xoá</button></form>
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <div class="rec-empty">Chưa có lịch phỏng vấn.</div>
                @endif
            </div>
        </div>
    @endif

    @if($active === 'evaluations')
        <div class="rec-card">
            <div class="rec-card-head">
                <div>
                    <h3 class="rec-card-title">B4. Đánh giá sau phỏng vấn</h3>
                    <div class="rec-card-note">Ghi kết quả Đạt / Không đạt / Hẹn vòng tiếp, nhận xét và thời gian có thể nhận việc.</div>
                </div>
            </div>
            <div class="rec-card-body">
                @if($interviews->count())
                    <div class="rec-table-wrap">
                        <table class="rec-table">
                            <thead><tr><th>Ứng viên</th><th>Lịch PV</th><th>Người PV</th><th>Trạng thái</th><th>Kết quả</th><th>Thời gian nhận việc</th><th>Đánh giá / nhận xét</th><th>Thao tác</th></tr></thead>
                            <tbody>
                                @foreach($interviews as $row)
                                    <tr>
                                        <td>
                                            <form id="eva{{ $row->id }}" method="POST" action="{{ route('hr.recruitment.interviews.update', $row->id) }}">@csrf @method('PUT')
                                                <select class="rec-select" name="candidate_id" required>
                                                    @foreach($candidates as $can)
                                                        <option value="{{ $can->id }}" @selected((int)$row->candidate_id === (int)$can->id)>{{ $can->full_name }} - {{ $can->position }}</option>
                                                    @endforeach
                                                </select>
                                            </form>
                                        </td>
                                        <td><input form="eva{{ $row->id }}" class="rec-input" type="datetime-local" name="interview_at" value="{{ !empty($row->interview_at) ? date('Y-m-d\TH:i', strtotime($row->interview_at)) : '' }}"></td>
                                        <td><input form="eva{{ $row->id }}" class="rec-input" name="interviewer" value="{{ $row->interviewer }}"></td>
                                        <td>
                                            <select form="eva{{ $row->id }}" class="rec-select" name="status">
                                                @foreach($interviewStatuses as $key => $label)
                                                    <option value="{{ $key }}" @selected(($row->status ?? 'scheduled') === $key)>{{ $label }}</option>
                                                @endforeach
                                            </select>
                                        </td>
                                        <td>
                                            <select form="eva{{ $row->id }}" class="rec-select" name="evaluation_result">
                                                <option value="">Chưa chốt</option>
                                                @foreach($evaluationResults as $key => $label)
                                                    <option value="{{ $key }}" @selected(($row->evaluation_result ?? '') === $key)>{{ $label }}</option>
                                                @endforeach
                                            </select>
                                            <input form="eva{{ $row->id }}" class="rec-input" name="result" value="{{ $row->result }}" placeholder="Kết quả tóm tắt" style="margin-top:6px">
                                        </td>
                                        <td><input form="eva{{ $row->id }}" class="rec-input" type="date" name="expected_start_date" value="{{ $row->expected_start_date ?? '' }}"></td>
                                        <td>
                                            <textarea form="eva{{ $row->id }}" class="rec-textarea" name="evaluation" placeholder="Đánh giá chuyên môn / thái độ / mức phù hợp">{{ $row->evaluation ?? '' }}</textarea>
                                            <textarea form="eva{{ $row->id }}" class="rec-textarea" name="note" placeholder="Ghi chú" style="margin-top:6px">{{ $row->note }}</textarea>
                                        </td>
                                        <td><button form="eva{{ $row->id }}" class="rec-btn" type="submit">Lưu đánh giá</button></td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <div class="rec-empty">Chưa có dữ liệu phỏng vấn để đánh giá.</div>
                @endif
            </div>
        </div>
    @endif

    @if($active === 'offers')
        <div class="rec-card">
            <div class="rec-card-head">
                <div>
                    <h3 class="rec-card-title">B5. Mời nhận việc</h3>
                    <div class="rec-card-note">Tạo thư mời, lương offer, ngày nhận việc, theo dõi đồng ý / từ chối / không phản hồi.</div>
                </div>
            </div>
            <div class="rec-card-body">
                <form method="POST" action="{{ route('hr.recruitment.offers.store') }}">
                    @csrf
                    <div class="rec-grid">
                        <div class="rec-field">
                            <label class="rec-label">Ứng viên *</label>
                            <select class="rec-select" name="candidate_id" required>
                                @foreach($candidates as $can)
                                    <option value="{{ $can->id }}">{{ $can->full_name }} - {{ $can->position }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="rec-field"><label class="rec-label">Ngày gửi thư mời</label><input class="rec-input" type="date" name="offer_date"></div>
                        <div class="rec-field"><label class="rec-label">Mức lương đề nghị</label><input class="rec-input" name="salary_offer" placeholder="VD: 12 triệu"></div>
                        <div class="rec-field"><label class="rec-label">Ngày nhận việc</label><input class="rec-input" type="date" name="start_date"></div>
                        <div class="rec-field">
                            <label class="rec-label">Trạng thái phản hồi</label>
                            <select class="rec-select" name="status">
                                @foreach($offerStatuses as $key => $label)
                                    <option value="{{ $key }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="rec-field full"><label class="rec-label">Nội dung phản hồi / ghi chú</label><textarea class="rec-textarea" name="response_note"></textarea></div>
                    </div>
                    <div class="rec-form-foot"><button class="rec-btn" type="submit">+ Tạo thư mời</button></div>
                </form>
            </div>
        </div>

        <div class="rec-card">
            <div class="rec-card-head"><h3 class="rec-card-title">Danh sách thư mời nhận việc</h3></div>
            <div class="rec-card-body">
                @if($offers->count())
                    <div class="rec-table-wrap">
                        <table class="rec-table sm">
                            <thead><tr><th>Ứng viên</th><th>Ngày gửi</th><th>Lương</th><th>Ngày nhận việc</th><th>Trạng thái</th><th>Phản hồi / ghi chú</th><th>Thao tác</th></tr></thead>
                            <tbody>
                                @foreach($offers as $row)
                                    <tr>
                                        <td>
                                            <form id="off{{ $row->id }}" method="POST" action="{{ route('hr.recruitment.offers.update', $row->id) }}">@csrf @method('PUT')
                                                <select class="rec-select" name="candidate_id" required>
                                                    @foreach($candidates as $can)
                                                        <option value="{{ $can->id }}" @selected((int)$row->candidate_id === (int)$can->id)>{{ $can->full_name }} - {{ $can->position }}</option>
                                                    @endforeach
                                                </select>
                                            </form>
                                        </td>
                                        <td><input form="off{{ $row->id }}" class="rec-input" type="date" name="offer_date" value="{{ $row->offer_date }}"></td>
                                        <td><input form="off{{ $row->id }}" class="rec-input" name="salary_offer" value="{{ $row->salary_offer }}"></td>
                                        <td><input form="off{{ $row->id }}" class="rec-input" type="date" name="start_date" value="{{ $row->start_date }}"></td>
                                        <td>
                                            <select form="off{{ $row->id }}" class="rec-select" name="status">
                                                @foreach($offerStatuses as $key => $label)
                                                    <option value="{{ $key }}" @selected(($row->status ?? '') === $key)>{{ $label }}</option>
                                                @endforeach
                                            </select>
                                        </td>
                                        <td>
                                            <textarea form="off{{ $row->id }}" class="rec-textarea" name="response_note" placeholder="Phản hồi của ứng viên">{{ $row->response_note ?? '' }}</textarea>
                                            <textarea form="off{{ $row->id }}" class="rec-textarea" name="note" placeholder="Ghi chú nội bộ" style="margin-top:6px">{{ $row->note }}</textarea>
                                        </td>
                                        <td>
                                            <div class="rec-actions">
                                                <button form="off{{ $row->id }}" class="rec-btn" type="submit">Lưu</button>
                                                <form method="POST" action="{{ route('hr.recruitment.offers.destroy', $row->id) }}" onsubmit="return confirm('Xoá thư mời này?')">@csrf @method('DELETE')<button class="rec-btn-danger">Xoá</button></form>
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <div class="rec-empty">Chưa có thư mời nhận việc.</div>
                @endif
            </div>
        </div>
    @endif

    @if($active === 'onboarding')
        @php
            $onboardingOffers = $offers->filter(function($offer){
                return in_array($offer->status ?? '', ['accepted', 'onboarded'], true) || !empty($offer->onboarding_status);
            });
        @endphp
        <div class="rec-card">
            <div class="rec-card-head">
                <div>
                    <h3 class="rec-card-title">B6. Tiếp nhận nhân sự mới</h3>
                    <div class="rec-card-note">Theo dõi nhân sự đã đồng ý offer, ngày nhận việc, tình trạng bổ sung hồ sơ và ký nhận việc.</div>
                </div>
            </div>
            <div class="rec-card-body">
                @if($onboardingOffers->count())
                    <div class="rec-table-wrap">
                        <table class="rec-table sm">
                            <thead><tr><th>Nhân sự</th><th>Ngày nhận việc</th><th>Lương offer</th><th>Tình trạng tiếp nhận</th><th>Ngày tiếp nhận</th><th>Ghi chú</th><th>Thao tác</th></tr></thead>
                            <tbody>
                                @foreach($onboardingOffers as $row)
                                    <tr>
                                        <td>
                                            <form id="onb{{ $row->id }}" method="POST" action="{{ route('hr.recruitment.offers.update', $row->id) }}">@csrf @method('PUT')
                                                <select class="rec-select" name="candidate_id" required>
                                                    @foreach($candidates as $can)
                                                        <option value="{{ $can->id }}" @selected((int)$row->candidate_id === (int)$can->id)>{{ $can->full_name }} - {{ $can->position }}</option>
                                                    @endforeach
                                                </select>
                                            </form>
                                        </td>
                                        <td><input form="onb{{ $row->id }}" class="rec-input" type="date" name="start_date" value="{{ $row->start_date }}"></td>
                                        <td><input form="onb{{ $row->id }}" class="rec-input" name="salary_offer" value="{{ $row->salary_offer }}"></td>
                                        <td>
                                            <input form="onb{{ $row->id }}" type="hidden" name="status" value="onboarded">
                                            <select form="onb{{ $row->id }}" class="rec-select" name="onboarding_status">
                                                @foreach($onboardingStatuses as $key => $label)
                                                    <option value="{{ $key }}" @selected(($row->onboarding_status ?? 'pending') === $key)>{{ $label }}</option>
                                                @endforeach
                                            </select>
                                        </td>
                                        <td><input form="onb{{ $row->id }}" class="rec-input" type="date" name="onboarding_date" value="{{ $row->onboarding_date ?? '' }}"></td>
                                        <td>
                                            <textarea form="onb{{ $row->id }}" class="rec-textarea" name="note" placeholder="Hồ sơ cần bổ sung / ghi chú ngày đầu nhận việc">{{ $row->note }}</textarea>
                                            <input form="onb{{ $row->id }}" type="hidden" name="offer_date" value="{{ $row->offer_date }}">
                                            <input form="onb{{ $row->id }}" type="hidden" name="response_note" value="{{ $row->response_note ?? '' }}">
                                        </td>
                                        <td><button form="onb{{ $row->id }}" class="rec-btn" type="submit">Lưu tiếp nhận</button></td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <div class="rec-empty">Chưa có ứng viên đồng ý offer để tiếp nhận.</div>
                @endif
            </div>
        </div>
    @endif

    @if($active === 'archives')
        @php
            $archiveCandidates = $candidates->filter(function($candidate){
                return in_array($candidate->status ?? '', ['archived', 'rejected', 'not_fit', 'interview_failed'], true);
            });
        @endphp
        <div class="rec-card">
            <div class="rec-card-head">
                <div>
                    <h3 class="rec-card-title">B7. Lưu hồ sơ & báo cáo</h3>
                    <div class="rec-card-note">Chuyển các hồ sơ chưa đạt / chưa phù hợp / từ chối sang kho lưu để có thể dùng lại sau hoặc thống kê nguyên nhân.</div>
                </div>
                <a class="rec-btn-outline" href="{{ route('hr.recruitment.reports') }}">Xem báo cáo tổng</a>
            </div>
            <div class="rec-card-body">
                @if($archiveCandidates->count())
                    <div class="rec-table-wrap">
                        <table class="rec-table sm">
                            <thead><tr><th>Ứng viên</th><th>Vị trí</th><th>Nguồn</th><th>Trạng thái</th><th>Lý do / ghi chú lưu</th><th>Lưu đến ngày</th><th>Thao tác</th></tr></thead>
                            <tbody>
                                @foreach($archiveCandidates as $row)
                                    <tr>
                                        <td><form id="arc{{ $row->id }}" method="POST" action="{{ route('hr.recruitment.candidates.update', $row->id) }}">@csrf @method('PUT')<input class="rec-input" name="full_name" value="{{ $row->full_name }}" required></form></td>
                                        <td><input form="arc{{ $row->id }}" class="rec-input" name="position" value="{{ $row->position }}"></td>
                                        <td>
                                            <select form="arc{{ $row->id }}" class="rec-select" name="source">
                                                <option value="">--</option>
                                                @foreach($sources as $source)
                                                    <option value="{{ $source }}" @selected(($row->source ?? '') === $source)>{{ $source }}</option>
                                                @endforeach
                                            </select>
                                        </td>
                                        <td>
                                            <select form="arc{{ $row->id }}" class="rec-select" name="status">
                                                @foreach($candidateStatuses as $key => $label)
                                                    <option value="{{ $key }}" @selected(($row->status ?? '') === $key)>{{ $label }}</option>
                                                @endforeach
                                            </select>
                                        </td>
                                        <td>
                                            <textarea form="arc{{ $row->id }}" class="rec-textarea" name="reject_reason" placeholder="Lý do từ chối / chưa đạt">{{ $row->reject_reason ?? '' }}</textarea>
                                            <textarea form="arc{{ $row->id }}" class="rec-textarea" name="archive_note" placeholder="Ghi chú lưu hồ sơ" style="margin-top:6px">{{ $row->archive_note ?? '' }}</textarea>
                                            <input form="arc{{ $row->id }}" type="hidden" name="phone" value="{{ $row->phone }}">
                                            <input form="arc{{ $row->id }}" type="hidden" name="email" value="{{ $row->email }}">
                                            <input form="arc{{ $row->id }}" type="hidden" name="zalo" value="{{ $row->zalo ?? '' }}">
                                            <input form="arc{{ $row->id }}" type="hidden" name="cv_link" value="{{ $row->cv_link ?? '' }}">
                                            <input form="arc{{ $row->id }}" type="hidden" name="contact_channel" value="{{ $row->contact_channel ?? '' }}">
                                            <input form="arc{{ $row->id }}" type="hidden" name="contacted_at" value="{{ $row->contacted_at ?? '' }}">
                                            <input form="arc{{ $row->id }}" type="hidden" name="suitability" value="{{ $row->suitability ?? '' }}">
                                            <input form="arc{{ $row->id }}" type="hidden" name="note" value="{{ $row->note }}">
                                            <input form="arc{{ $row->id }}" type="hidden" name="recruitment_request_id" value="{{ $row->recruitment_request_id }}">
                                        </td>
                                        <td><input form="arc{{ $row->id }}" class="rec-input" type="date" name="archive_until" value="{{ $row->archive_until ?? '' }}"></td>
                                        <td><button form="arc{{ $row->id }}" class="rec-btn" type="submit">Lưu hồ sơ</button></td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <div class="rec-empty">Chưa có hồ sơ lưu trữ / từ chối.</div>
                @endif
            </div>
        </div>
    @endif
</div>
@endsection
