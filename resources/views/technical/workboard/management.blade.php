@extends('layouts.app')

@section('title', 'Quản lý kỹ thuật')

@push('styles')
    <link rel="stylesheet"
          href="{{ asset('css/ego-technical-work.css') }}?v={{ file_exists(public_path('css/ego-technical-work.css')) ? filemtime(public_path('css/ego-technical-work.css')) : '1.0.0' }}">
@endpush

@section('content')
<div class="container-fluid py-3 tw-wrap">

    <div class="tw-head">
        <div>
            <h1 class="tw-head__title">Quản lý kỹ thuật</h1>
            <p class="tw-head__sub">Khối lượng công việc theo từng nhân sự và hàng đợi báo cáo chờ duyệt.</p>
        </div>
    </div>

    @include('technical.workboard.partials.nav', ['canManage' => true])
    @include('technical.workboard.partials.flash')

    <div class="tw-stats">
        <div class="tw-stat">
            <span class="tw-stat__icon"><i class="bi bi-list-task"></i></span>
            <span>
                <span class="tw-stat__value d-block">{{ number_format($summary['total_items']) }}</span>
                <span class="tw-stat__label d-block">Tổng đầu việc</span>
            </span>
        </div>
        <div class="tw-stat tw-stat--danger">
            <span class="tw-stat__icon"><i class="bi bi-exclamation-octagon"></i></span>
            <span>
                <span class="tw-stat__value d-block">{{ number_format($summary['overdue_items']) }}</span>
                <span class="tw-stat__label d-block">Việc quá hạn</span>
            </span>
        </div>
        <div class="tw-stat tw-stat--warn">
            <span class="tw-stat__icon"><i class="bi bi-inbox"></i></span>
            <span>
                <span class="tw-stat__value d-block">{{ number_format($summary['pending_reports']) }}</span>
                <span class="tw-stat__label d-block">Báo cáo chờ duyệt</span>
            </span>
        </div>
        <div class="tw-stat tw-stat--ok">
            <span class="tw-stat__icon"><i class="bi bi-buildings"></i></span>
            <span>
                <span class="tw-stat__value d-block">{{ number_format($summary['active_sites']) }}</span>
                <span class="tw-stat__label d-block">Công trình đang chạy</span>
            </span>
        </div>
    </div>

    <div class="tw-card">
        <div class="tw-card__head">
            <h2 class="tw-card__title">Khối lượng theo nhân sự</h2>
        </div>

        <div class="tw-card__body tw-card__body--flush">
            @if($rows->isEmpty())
                <div class="tw-empty">
                    <i class="bi bi-people"></i>
                    <p>Chưa có nhân sự nào được giao việc</p>
                    <small>Phân công được lấy từ bước quy trình Công trình, Task gắn công trình và lịch Bảo trì.</small>
                </div>
            @else
                <div class="tw-scroll">
                    <table class="table tw-table">
                        <thead>
                            <tr>
                                <th>Nhân sự</th>
                                <th class="text-end">Tổng việc</th>
                                <th class="text-end">Đang làm</th>
                                <th class="text-end">Quá hạn</th>
                                <th class="text-end">Hoàn thành</th>
                                <th class="text-end">Công trình</th>
                                <th class="text-end">Xem</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($rows as $row)
                                <tr>
                                    <td class="fw-semibold">{{ $row->user_name ?: 'Nhân sự #'.$row->user_id }}</td>
                                    <td class="text-end">{{ number_format((int) $row->total_items) }}</td>
                                    <td class="text-end">{{ number_format((int) $row->in_progress_items) }}</td>
                                    <td class="text-end {{ (int) $row->overdue_items > 0 ? 'tw-overdue' : 'text-muted' }}">
                                        {{ number_format((int) $row->overdue_items) }}
                                    </td>
                                    <td class="text-end">{{ number_format((int) $row->done_items) }}</td>
                                    <td class="text-end">{{ number_format((int) $row->site_count) }}</td>
                                    <td class="text-end">
                                        <a class="btn btn-sm btn-outline-secondary"
                                           href="{{ route('technical.work.my', ['user_id' => $row->user_id]) }}">
                                            <i class="bi bi-list-check"></i>
                                        </a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>

    <div class="tw-card">
        <div class="tw-card__head">
            <h2 class="tw-card__title">Báo cáo chờ duyệt</h2>
            <a href="{{ route('technical.daily-reports.index', ['status' => \App\Models\Technical\TechnicalDailyReport::STATUS_SUBMITTED]) }}"
               class="btn btn-sm btn-outline-secondary">
                Xem tất cả
            </a>
        </div>

        <div class="tw-card__body tw-card__body--flush">
            @if($pendingReports->isEmpty())
                <div class="tw-empty">
                    <i class="bi bi-check2-circle"></i>
                    <p>Không có báo cáo nào đang chờ duyệt</p>
                    <small>Báo cáo sẽ xuất hiện ở đây ngay khi kỹ thuật viên bấm Gửi duyệt.</small>
                </div>
            @else
                <div class="tw-scroll">
                    <table class="table tw-table">
                        <thead>
                            <tr>
                                <th>Ngày</th>
                                <th>Người báo cáo</th>
                                <th>Công trình</th>
                                <th>Đầu việc</th>
                                <th class="text-end">Tiến độ</th>
                                <th class="text-end">Xử lý</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($pendingReports as $report)
                                <tr>
                                    <td>{{ optional($report->report_date)->format('d/m/Y') }}</td>
                                    <td>{{ $report->user?->name ?: $report->user_name }}</td>
                                    <td>{{ $report->site_name ?: '—' }}</td>
                                    <td>{{ $report->work_title ?: $report->sourceLabel() }}</td>
                                    <td class="text-end">{{ (int) $report->progress_percent }}%</td>
                                    <td class="text-end">
                                        <a class="btn btn-sm btn-primary"
                                           href="{{ route('technical.daily-reports.show', $report) }}">
                                            <i class="bi bi-eye"></i> Duyệt
                                        </a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>

    <div class="tw-note">
        <i class="bi bi-info-circle"></i>
        <div>
            <strong>KPI tạm tính</strong><br>
            KPI tự động sẽ được kết nối ở giai đoạn tiếp theo.
        </div>
    </div>

</div>
@endsection
