{{--
    Bảng số liệu theo NHÂN VIÊN — dùng chung cho Tổng quan phòng, Tổng kết tuần
    và Dashboard Admin.

    $detailMode:
      - 'manager'   => link mở trang điều phối (chỉ dùng ở màn hình trưởng phòng)
      - 'readonly'  => link mở danh sách báo cáo của nhân viên (Dashboard Admin)
    Dashboard Admin KHÔNG bao giờ render form/nút thao tác ở đây.
--}}
@php
    $detailMode = $detailMode ?? 'readonly';
    $weekParam = isset($weekStart) ? $weekStart->toDateString() : null;
@endphp

<div class="tw-scroll tp-data-scroll">
    <table class="tp-data">
        <thead>
            <tr>
                <th>Nhân viên</th>
                <th>Kế hoạch tuần</th>
                <th class="tp-data__num">Công việc kế hoạch</th>
                <th class="tp-data__num">Đã hoàn thành</th>
                <th class="tp-data__num">Chưa hoàn thành</th>
                <th class="tp-data__num">Phát sinh</th>
                <th class="tp-data__num">Đúng hạn</th>
                <th class="tp-data__num">Báo cáo đã nộp</th>
                <th class="tp-data__num">Tỷ lệ hoàn thành</th>
                <th>Chi tiết</th>
            </tr>
        </thead>
        <tbody>
            @forelse($rows as $row)
                <tr>
                    <td>{{ \App\Services\Technical\TechnicalWeekPlanService::displayLabel($row['user_name']) }}</td>
                    <td>
                        @if(!$row['has_week_plan'])
                            <span class="tp-state tp-state--danger">Chưa lập</span>
                        @elseif($row['week_plan_status'] === \App\Models\Technical\TechnicalWeekPlan::STATUS_FINALIZED)
                            <span class="tp-state tp-state--success">Đã hoàn tất</span>
                        @elseif($row['week_plan_status'] === \App\Models\Technical\TechnicalWeekPlan::STATUS_ADJUSTED)
                            <span class="tp-state tp-state--warning">Đã điều chỉnh</span>
                        @else
                            <span class="tp-state tp-state--info">Đang lập</span>
                        @endif
                    </td>
                    <td class="tp-data__num">{{ $row['planned_items'] }}</td>
                    <td class="tp-data__num">{{ $row['done_items'] }}</td>
                    <td class="tp-data__num">{{ $row['not_done_items'] + $row['overdue_items'] }}</td>
                    <td class="tp-data__num">{{ $row['unplanned_items'] }}</td>
                    <td class="tp-data__num">{{ $row['on_time_items'] }}</td>
                    <td class="tp-data__num">{{ $row['reported_items'] }} / {{ $row['due_items'] }}</td>
                    <td class="tp-data__num">
                        {{ \App\Services\Technical\TechnicalPlanVsActualService::rateLabel($row['completion_rate']) }}
                    </td>
                    <td>
                        @if($detailMode === 'manager')
                            <a href="{{ route('technical.manager.detail', array_filter(['member' => $row['user_id'], 'week' => $weekParam])) }}">
                                Xem kế hoạch
                            </a>
                        @else
                            <a href="{{ route('technical.daily-reports.index', ['user_id' => $row['user_id']]) }}">
                                Xem báo cáo
                            </a>
                        @endif
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="10">
                        <div class="tw-empty">
                            <i class="bi bi-people"></i>
                            <p>Chưa có nhân sự kỹ thuật nào trong phạm vi</p>
                        </div>
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>

<div class="tp-cards-list">
    @forelse($rows as $row)
        <article class="tp-jobcard">
            <p class="tp-jobcard__title">{{ \App\Services\Technical\TechnicalWeekPlanService::displayLabel($row['user_name']) }}</p>
            <div class="tp-jobcard__row"><span class="tp-jobcard__label">Kế hoạch tuần</span><span class="tp-jobcard__value">{{ !$row['has_week_plan'] ? 'Chưa lập' : ($row['week_plan_status'] === \App\Models\Technical\TechnicalWeekPlan::STATUS_FINALIZED ? 'Đã hoàn tất' : ($row['week_plan_status'] === \App\Models\Technical\TechnicalWeekPlan::STATUS_ADJUSTED ? 'Đã điều chỉnh' : 'Đang lập')) }}</span></div>
            <div class="tp-jobcard__row"><span class="tp-jobcard__label">Kế hoạch / hoàn thành</span><span class="tp-jobcard__value">{{ $row['planned_items'] }} / {{ $row['done_items'] }}</span></div>
            <div class="tp-jobcard__row"><span class="tp-jobcard__label">Chưa xong / phát sinh</span><span class="tp-jobcard__value">{{ $row['not_done_items'] + $row['overdue_items'] }} / {{ $row['unplanned_items'] }}</span></div>
            <div class="tp-jobcard__row"><span class="tp-jobcard__label">Báo cáo đã nộp</span><span class="tp-jobcard__value">{{ $row['reported_items'] }} / {{ $row['due_items'] }}</span></div>
            <div class="tp-jobcard__row"><span class="tp-jobcard__label">Tỷ lệ hoàn thành</span><span class="tp-jobcard__value">{{ \App\Services\Technical\TechnicalPlanVsActualService::rateLabel($row['completion_rate']) }}</span></div>
            <div class="tp-jobcard__actions">
                @if($detailMode === 'manager')
                    <a class="btn btn-sm btn-outline-primary" href="{{ route('technical.manager.detail', array_filter(['member' => $row['user_id'], 'week' => $weekParam])) }}">Xem kế hoạch</a>
                @else
                    <a class="btn btn-sm btn-outline-primary" href="{{ route('technical.daily-reports.index', ['user_id' => $row['user_id']]) }}">Xem báo cáo</a>
                @endif
            </div>
        </article>
    @empty
        <div class="tw-empty"><p>Chưa có nhân sự kỹ thuật nào trong phạm vi</p></div>
    @endforelse
</div>
