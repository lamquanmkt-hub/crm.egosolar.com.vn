@extends('layouts.app')

@section('title', ($canManage ?? false) ? 'Báo cáo Kỹ thuật' : 'Báo cáo của tôi')

@push('styles')
    <link rel="stylesheet"
          href="{{ asset('css/ego-technical-work.css') }}?v={{ file_exists(public_path('css/ego-technical-work.css')) ? filemtime(public_path('css/ego-technical-work.css')) : '1.0.0' }}">
    @include('technical.partials.plan-styles')
    <style>
        /* Chỉ dẫn cuộn ngang cho bảng báo cáo — chỉ hiện khi bảng thật sự bị cắt. */
        .dr-scroll-hint{display:none;margin:10px 14px 0;color:#60748a;font-size:12px;font-weight:600}
        @media (max-width:991.98px){.dr-scroll-hint{display:flex;align-items:center;gap:6px}}
    </style>
@endpush

@section('content')
{{--
    TRANG BÁO CÁO NGÀY / TUẦN — DÙNG CHUNG cho cả ba vai trò
    ========================================================
    Một route (`technical.daily-reports.index`), một mục sidebar ("Báo cáo
    ngày/tuần"), HAI TAB nội bộ:
      (1) "Báo cáo ngày"   — danh sách báo cáo (mặc định)
      (2) "Tổng hợp tuần"  — ?tab=weekly-summary
    Nội dung khác nhau theo quyền, nhưng mục sidebar active thì KHÔNG đổi khi
    chuyển tab (vẫn cùng tên route).
--}}
<!-- TECHNICAL_DAILY_REPORTS_CONTENT_START -->
<div class="container-fluid py-3 tw-wrap">

    @php
        $drIsWeekly = (string) ($tab ?? '') === \App\Http\Controllers\Technical\TechnicalDailyReportController::TAB_WEEKLY;
        $drBaseParams = array_diff_key(request()->except(['page']), array_flip(['tab']));
    @endphp

    @include('technical.partials.dashboard-breadcrumb', [
        'trail' => [
            'Kỹ thuật' => \Illuminate\Support\Facades\Route::has('technical.dashboard') && auth()->user()?->isAdmin()
                ? route('technical.dashboard')
                : route('ky-thuat.tong-quan'),
            'Báo cáo ngày/tuần' => null,
        ],
    ])

    <div class="tw-head">
        <div>
            <h1 class="tw-head__title">{{ $canManage ? 'Báo cáo Kỹ thuật' : 'Báo cáo của tôi' }}</h1>
            <p class="tw-head__sub">
                Theo dõi báo cáo ngày và tổng hợp kết quả làm việc theo tuần.
            </p>
        </div>
        <div class="tw-head__actions">
            {{--
                Trang dùng chung cho ba vai trò: nhân viên được dẫn tới bài
                "Viết và gửi báo cáo ngày", người có quyền quản lý được dẫn tới
                bài "Duyệt và yêu cầu sửa báo cáo".
            --}}
            @include('technical.guides.partials.help-button', [
                'slug' => $canManage ? 'truong-phong-duyet-bao-cao' : 'nhan-vien-bao-cao-ngay',
            ])
            <a href="{{ route('technical.daily-reports.create') }}" class="btn btn-primary">
                <i class="bi bi-journal-plus"></i> Viết báo cáo
            </a>
            <a href="{{ route('technical.daily-reports.create', ['mode' => 'phat-sinh']) }}" class="btn btn-outline-primary">
                <i class="bi bi-lightning-charge"></i> Báo cáo việc phát sinh
            </a>
        </div>
    </div>

    {{-- ---------- HAI TAB CHÍNH của trang (cùng route, đổi ?tab=) ---------- --}}
    <div class="tw-chips tp-tabs" role="tablist" aria-label="Chế độ xem của trang Báo cáo">
        <a class="tw-chip {{ $drIsWeekly ? '' : 'is-active' }}"
           data-dr-tab="daily"
           href="{{ route('technical.daily-reports.index', array_diff_key($drBaseParams, array_flip(['week', 'offset', 'work_status']))) }}">
            Báo cáo ngày
        </a>
        <a class="tw-chip {{ $drIsWeekly ? 'is-active' : '' }}"
           data-dr-tab="weekly"
           href="{{ route('technical.daily-reports.index', array_merge(array_diff_key($drBaseParams, array_flip(['status', 'from', 'to'])), ['tab' => \App\Http\Controllers\Technical\TechnicalDailyReportController::TAB_WEEKLY])) }}">
            Tổng hợp tuần
        </a>
    </div>

    @include('technical.workboard.partials.flash')

@if($drIsWeekly)
    @include('technical.daily-reports.partials.weekly-summary')
@else

    {{-- Tab NỘI BỘ theo trạng thái — chỉ thuộc tab "Báo cáo ngày". --}}
    @php
        $reportTabs = [
            '' => $canManage ? 'Báo cáo nhân viên' : 'Báo cáo của tôi',
            \App\Models\Technical\TechnicalDailyReport::STATUS_DRAFT => 'Nháp',
            \App\Models\Technical\TechnicalDailyReport::STATUS_SUBMITTED => 'Chờ duyệt',
            \App\Models\Technical\TechnicalDailyReport::STATUS_APPROVED => 'Đã duyệt',
            \App\Models\Technical\TechnicalDailyReport::STATUS_REVISION => 'Yêu cầu sửa',
        ];
        $activeReportTab = (string) request('status', '');
    @endphp
    <div class="tw-chips tp-tabs" role="tablist" aria-label="Lọc nhanh theo trạng thái báo cáo">
        @foreach($reportTabs as $tabKey => $tabLabel)
            <a class="tw-chip {{ $activeReportTab === (string) $tabKey ? 'is-active' : '' }}"
               href="{{ route('technical.daily-reports.index', array_merge(request()->except(['status', 'page', 'tab']), $tabKey === '' ? [] : ['status' => $tabKey])) }}">
                {{ $tabLabel }}
            </a>
        @endforeach
    </div>

    @include('technical.workboard.partials.nav')

    <div class="tw-card">
        <div class="tw-card__body border-bottom">
            <form method="GET" action="{{ route('technical.daily-reports.index') }}" class="row g-2 align-items-end">
                <div class="col-6 col-md-2">
                    <label class="form-label small text-muted mb-1" for="r-from">Từ ngày</label>
                    <input type="date" id="r-from" name="from" class="form-control form-control-sm" value="{{ request('from') }}">
                </div>
                <div class="col-6 col-md-2">
                    <label class="form-label small text-muted mb-1" for="r-to">Đến ngày</label>
                    <input type="date" id="r-to" name="to" class="form-control form-control-sm" value="{{ request('to') }}">
                </div>
                <div class="col-12 col-md-3">
                    <label class="form-label small text-muted mb-1" for="r-status">Trạng thái</label>
                    <select id="r-status" name="status" class="form-select form-select-sm">
                        <option value="">Tất cả trạng thái</option>
                        @foreach($statusOptions as $key => $label)
                            <option value="{{ $key }}" @selected(request('status') === $key)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>

                @if($canManage)
                    <div class="col-12 col-md-3">
                        <label class="form-label small text-muted mb-1" for="r-user">Nhân sự</label>
                        <select id="r-user" name="user_id" class="form-select form-select-sm">
                            <option value="">Tất cả nhân sự</option>
                            @foreach($teamMembers as $member)
                                <option value="{{ $member->id }}" @selected((int) $selectedUserId === (int) $member->id)>
                                    {{ $member->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                @endif

                <div class="col-12 col-md-2 d-flex gap-2">
                    <button type="submit" class="btn btn-sm btn-primary flex-grow-1"><i class="bi bi-funnel"></i> Lọc</button>
                    <a href="{{ route('technical.daily-reports.index') }}" class="btn btn-sm btn-outline-secondary">
                        <i class="bi bi-x-lg"></i>
                    </a>
                </div>
            </form>
        </div>

        <div class="tw-card__body tw-card__body--flush">
            @if($reports->isEmpty())
                <div class="tw-empty">
                    <i class="bi bi-journal-text"></i>
                    <p>Chưa có báo cáo nào</p>
                    <small>Hôm nay chưa có công việc trong kế hoạch. Bạn vẫn có thể báo cáo việc phát sinh hoặc lập kế hoạch mới.</small>
                    <div class="tw-empty__actions mt-3">
                        <a href="{{ route('technical.daily-reports.create', ['mode' => 'phat-sinh']) }}" class="btn btn-primary">
                            <i class="bi bi-lightning-charge"></i> Báo cáo việc phát sinh
                        </a>
                        <a href="{{ route('technical.week-plan.index') }}" class="btn btn-outline-secondary">
                            <i class="bi bi-calendar-week"></i> Lập kế hoạch
                        </a>
                    </div>
                </div>
            @else
                {{-- Bảng báo cáo rộng: trên tablet/mobile vẫn cuộn ngang trong khung,
                     nhưng có chỉ dẫn rõ thay vì cắt cột câm lặng. --}}
                <p class="dr-scroll-hint">
                    <i class="bi bi-arrow-left-right"></i>
                    Vuốt ngang trong bảng để xem đủ các cột.
                </p>
                <div class="tw-scroll">
                    <table class="table tw-table">
                        <thead>
                            <tr>
                                <th>Ngày</th>
                                @if($canManage)<th>Người báo cáo</th>@endif
                                <th>Công trình</th>
                                <th>Đầu việc</th>
                                <th>Nguồn</th>
                                <th class="text-end">Tiến độ</th>
                                <th class="text-end">File</th>
                                <th>Trạng thái</th>
                                <th class="text-end"></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($reports as $report)
                                <tr>
                                    <td class="text-nowrap">{{ optional($report->report_date)->format('d/m/Y') }}</td>
                                    @if($canManage)
                                        <td>{{ $report->user?->name ?: $report->user_name }}</td>
                                    @endif
                                    <td>{{ $report->site_name ?: '—' }}</td>
                                    <td>{{ $report->work_title ?: '—' }}</td>
                                    <td><span class="tw-source tw-source--{{ $report->source_type === 'task' ? 'task' : ($report->source_type === 'maintenance' ? 'maintenance' : 'project') }}">{{ $report->sourceLabel() }}</span></td>
                                    <td class="text-end">{{ (int) $report->progress_percent }}%</td>
                                    <td class="text-end">{{ (int) $report->files_count }}</td>
                                    <td>
                                        <span class="badge text-bg-{{ $report->statusTone() }}">{{ $report->statusLabel() }}</span>
                                    </td>
                                    <td class="text-end">
                                        <a class="btn btn-sm btn-outline-secondary" href="{{ route('technical.daily-reports.show', $report) }}">
                                            <i class="bi bi-eye"></i>
                                        </a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>

        @if($reports->hasPages())
            <div class="tw-card__body border-top">
                {{ $reports->links() }}
            </div>
        @endif
    </div>
@endif

</div>
<!-- TECHNICAL_DAILY_REPORTS_CONTENT_END -->
@endsection
