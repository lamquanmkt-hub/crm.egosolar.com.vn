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
    $pageCopy = [
        'requests' => ['Yêu cầu tuyển dụng', 'Theo dõi nhu cầu tuyển và trạng thái phê duyệt. Mở một yêu cầu để xem hoặc chỉnh sửa.'],
        'screening' => ['Ứng viên & sàng lọc', 'Quản lý hồ sơ ứng viên, liên hệ và kết quả sàng lọc.'],
        'interviews' => ['Lịch phỏng vấn', 'Theo dõi lịch hẹn và tình trạng phỏng vấn.'],
        'evaluations' => ['Đánh giá phỏng vấn', 'Ghi nhận kết quả và nhận xét sau phỏng vấn.'],
        'offers' => ['Thư mời nhận việc', 'Theo dõi thư mời, phản hồi và ngày nhận việc.'],
        'onboarding' => ['Tiếp nhận nhân sự mới', 'Theo dõi các việc cần hoàn tất khi nhân viên nhận việc.'],
        'archives' => ['Hồ sơ ứng viên lưu trữ', 'Tra cứu hồ sơ đã lưu và lý do chưa phù hợp.'],
        'reports' => ['Tổng quan tuyển dụng', 'Theo dõi tình hình tuyển dụng theo từng bước.'],
    ];
    $currentCopy = $pageCopy[$active] ?? $pageCopy['reports'];
    $requestMetrics = [
        ['Tổng yêu cầu', $requests->count()],
        ['Chờ duyệt', $requests->where('approval_status', 'pending')->count()],
        ['Đã duyệt', $requests->where('approval_status', 'approved')->count()],
    ];
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

<div class="rec-wrap{{ $active === 'requests' ? ' rec-requests-workspace' : '' }}">
    <div class="rec-head">
        <div>
            <h1 class="rec-title">{{ $currentCopy[0] }}</h1>
            <div class="rec-sub">{{ $currentCopy[1] }}</div>
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
        <div class="rec-error" role="alert">{{ $errors->hasAny(['kpi', 'quantity', 'recruitment_request_id']) ? $errors->first() : ($active !== 'reports' ? 'Chưa lưu được. Kiểm tra các mục được đánh dấu trong form bên dưới.' : $errors->first()) }}</div>
    @endif

    @if($active === 'requests')
        <div class="rec-stats rec-request-stats">
            @foreach($requestMetrics as [$label, $value])
                <div class="rec-stat"><div class="rec-stat-label">{{ $label }}</div><div class="rec-stat-value">{{ $value }}</div></div>
            @endforeach
        </div>
    @elseif(in_array($active, ['screening','interviews','evaluations','offers','onboarding','archives'], true))
        @include('hr.recruitment.partials.workflow-metrics')
    @else
    <div class="rec-stats">
        <div class="rec-stat"><div class="rec-stat-label">Y/c tuyển dụng</div><div class="rec-stat-value">{{ $stats['requests'] ?? 0 }}</div></div>
        <div class="rec-stat"><div class="rec-stat-label">Đã duyệt</div><div class="rec-stat-value">{{ $stats['approved_requests'] ?? 0 }}</div></div>
        <div class="rec-stat"><div class="rec-stat-label">Ứng viên</div><div class="rec-stat-value">{{ $stats['candidates'] ?? 0 }}</div></div>
        <div class="rec-stat"><div class="rec-stat-label">Đã liên hệ</div><div class="rec-stat-value">{{ $stats['contacted'] ?? 0 }}</div></div>
        <div class="rec-stat"><div class="rec-stat-label">Lịch PV</div><div class="rec-stat-value">{{ $stats['interviews'] ?? 0 }}</div></div>
        <div class="rec-stat"><div class="rec-stat-label">Đã nhận việc</div><div class="rec-stat-value">{{ $stats['hired'] ?? 0 }}</div></div>
        <div class="rec-stat"><div class="rec-stat-label">Lưu / Từ chối</div><div class="rec-stat-value">{{ ($stats['archived'] ?? 0) + ($stats['rejected'] ?? 0) }}</div></div>
    </div>
    @endif

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
        @include('hr.recruitment.partials.requests-workspace')
    @endif

    @if(in_array($active, ['screening','interviews','evaluations','offers','onboarding','archives'], true))
        @include('hr.recruitment.partials.workflow-workspace')
    @endif
</div>
@endsection
