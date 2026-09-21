{{--
    TAB "TỔNG HỢP TUẦN" của trang /ky-thuat/bao-cao-ngay
    =====================================================
    Thay thế trang Dashboard báo cáo cũ (/ky-thuat/dashboard/bao-cao, nay chỉ
    còn redirect 302 về đây). MỌI con số do controller lấy từ service
    (`TechnicalPlanVsActualService`, `TechnicalDashboardReadService`) — Blade
    KHÔNG tính toán, chỉ hiển thị. Mẫu số 0 hiển thị "N/A" qua `rateLabel()`.

    Chỉ có MỘT form GET (bộ lọc): không @csrf, không _method — tab này không ghi.
--}}
<!-- TECHNICAL_WEEKLY_SUMMARY_START -->

{{-- ---------- Chọn tuần ---------- --}}
<div class="tp-weekbar">
    <div class="tp-weekbar__nav">
        <a class="btn btn-sm btn-outline-secondary"
           href="{{ route('technical.daily-reports.index', array_merge(request()->except(['week', 'offset', 'page']), ['tab' => \App\Http\Controllers\Technical\TechnicalDailyReportController::TAB_WEEKLY, 'week' => $prevWeek])) }}">
            <i class="bi bi-chevron-left"></i> Tuần trước
        </a>
        <a class="btn btn-sm btn-outline-secondary"
           href="{{ route('technical.daily-reports.index', array_merge(request()->except(['week', 'offset', 'page']), ['tab' => \App\Http\Controllers\Technical\TechnicalDailyReportController::TAB_WEEKLY, 'week' => $thisWeek])) }}">
            Tuần hiện tại
        </a>
        <a class="btn btn-sm btn-outline-secondary"
           href="{{ route('technical.daily-reports.index', array_merge(request()->except(['week', 'offset', 'page']), ['tab' => \App\Http\Controllers\Technical\TechnicalDailyReportController::TAB_WEEKLY, 'week' => $nextWeek])) }}">
            Tuần sau <i class="bi bi-chevron-right"></i>
        </a>
    </div>

    <span class="tp-weekbar__range"><i class="bi bi-calendar-range"></i> {{ $rangeLabel }}</span>
</div>

{{-- ---------- Bộ lọc (GET) ---------- --}}
<div class="tw-card mb-3">
    <div class="tw-card__body">
        <form method="GET" action="{{ route('technical.daily-reports.index') }}" class="tp-filters">
            <input type="hidden" name="tab" value="{{ \App\Http\Controllers\Technical\TechnicalDailyReportController::TAB_WEEKLY }}">
            <input type="hidden" name="week" value="{{ $weekStart->toDateString() }}">

            @if($canManage)
                <div>
                    <label class="form-label small" for="ws-user">Nhân viên</label>
                    <select class="form-select form-select-sm" id="ws-user" name="user_id">
                        <option value="">Tất cả nhân viên</option>
                        @foreach($teamMembers as $member)
                            <option value="{{ $member->id }}" @selected((int) ($filters['user_id'] ?? 0) === (int) $member->id)>
                                {{ \App\Services\Technical\TechnicalWeekPlanService::displayLabel($member->name) }}
                            </option>
                        @endforeach
                    </select>
                </div>
            @endif

            <div>
                <label class="form-label small" for="ws-site">Công trình</label>
                <select class="form-select form-select-sm" id="ws-site" name="site_id">
                    <option value="">Tất cả công trình</option>
                    @foreach($siteOptions as $site)
                        <option value="{{ $site->id }}" @selected((int) ($filters['site_id'] ?? 0) === (int) $site->id)>
                            {{ $site->name }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="form-label small" for="ws-status">Trạng thái công việc</label>
                <select class="form-select form-select-sm" id="ws-status" name="work_status">
                    <option value="">Tất cả trạng thái</option>
                    @foreach($workStatusOptions as $key => $label)
                        <option value="{{ $key }}" @selected(($filters['status'] ?? '') === $key)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <button type="submit" class="btn btn-sm btn-primary w-100">
                    <i class="bi bi-funnel"></i> Áp dụng bộ lọc
                </button>
            </div>
        </form>
    </div>
</div>

{{-- ---------- Tám thẻ tổng hợp ---------- --}}
<div class="tp-cards mb-3">
    @foreach($cards as $card)
        <div class="tw-stat {{ $card['tone'] ?? '' }}">
            <span class="tw-stat__icon"><i class="bi {{ $card['icon'] }}"></i></span>
            <span>
                <span class="tw-stat__value d-block">{{ !empty($card['is_text']) ? $card['value'] : number_format((int) $card['value']) }}</span>
                <span class="tw-stat__label d-block">{{ $card['label'] }}</span>
            </span>
        </div>
    @endforeach
</div>

{{-- ---------- Kết quả theo nhân viên ---------- --}}
<div class="tw-card mb-3">
    <div class="tw-card__head">
        <h2 class="tw-card__title">Kết quả theo nhân viên</h2>
    </div>

    <div class="tw-scroll tp-data-scroll">
        <table class="tp-data">
            <thead>
                <tr>
                    <th>Nhân viên</th>
                    <th class="tp-data__num">Số báo cáo</th>
                    <th class="tp-data__num">Việc hoàn thành</th>
                    <th class="tp-data__num">Việc chưa hoàn thành</th>
                    <th class="tp-data__num">Việc phát sinh</th>
                    <th class="tp-data__num">Giờ thực tế</th>
                    <th class="tp-data__num">Tỷ lệ nộp báo cáo</th>
                    <th>Trạng thái</th>
                    <th>Chi tiết</th>
                </tr>
            </thead>
            <tbody>
                @forelse($rows as $row)
                    <tr>
                        <td><strong>{{ \App\Services\Technical\TechnicalWeekPlanService::displayLabel($row['user_name']) }}</strong></td>
                        <td class="tp-data__num">{{ $row['report_count'] }}</td>
                        <td class="tp-data__num">{{ $row['done_items'] }}</td>
                        <td class="tp-data__num">{{ $row['not_done_items'] + $row['overdue_items'] }}</td>
                        <td class="tp-data__num">{{ $row['unplanned_items'] }}</td>
                        <td class="tp-data__num">{{ $planner->minutesLabel($row['actual_minutes']) }}</td>
                        <td class="tp-data__num">{{ \App\Services\Technical\TechnicalPlanVsActualService::rateLabel($row['report_rate']) }}</td>
                        <td>
                            @if(!$row['has_week_plan'])
                                <span class="tp-state tp-state--danger">Chưa lập kế hoạch</span>
                            @elseif($row['week_plan_status'] === \App\Models\Technical\TechnicalWeekPlan::STATUS_FINALIZED)
                                <span class="tp-state tp-state--success">Đã hoàn tất</span>
                            @elseif($row['week_plan_status'] === \App\Models\Technical\TechnicalWeekPlan::STATUS_ADJUSTED)
                                <span class="tp-state tp-state--warning">Đã điều chỉnh</span>
                            @else
                                <span class="tp-state tp-state--info">Đang lập</span>
                            @endif
                        </td>
                        <td>
                            <a href="{{ route('technical.daily-reports.index', ['user_id' => $row['user_id'], 'from' => $from->toDateString(), 'to' => $to->toDateString()]) }}">
                                Xem báo cáo
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="9">
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
                <div class="tp-jobcard__row"><span class="tp-jobcard__label">Số báo cáo</span><span class="tp-jobcard__value">{{ $row['report_count'] }}</span></div>
                <div class="tp-jobcard__row"><span class="tp-jobcard__label">Hoàn thành / chưa xong</span><span class="tp-jobcard__value">{{ $row['done_items'] }} / {{ $row['not_done_items'] + $row['overdue_items'] }}</span></div>
                <div class="tp-jobcard__row"><span class="tp-jobcard__label">Việc phát sinh</span><span class="tp-jobcard__value">{{ $row['unplanned_items'] }}</span></div>
                <div class="tp-jobcard__row"><span class="tp-jobcard__label">Giờ thực tế</span><span class="tp-jobcard__value">{{ $planner->minutesLabel($row['actual_minutes']) }}</span></div>
                <div class="tp-jobcard__row"><span class="tp-jobcard__label">Tỷ lệ nộp báo cáo</span><span class="tp-jobcard__value">{{ \App\Services\Technical\TechnicalPlanVsActualService::rateLabel($row['report_rate']) }}</span></div>
            </article>
        @empty
            <div class="tw-empty"><p>Chưa có nhân sự kỹ thuật nào trong phạm vi</p></div>
        @endforelse
    </div>
</div>

{{-- ---------- Kết quả theo công trình ---------- --}}
<div class="tw-card">
    <div class="tw-card__head">
        <h2 class="tw-card__title">Kết quả theo công trình</h2>
    </div>

    <div class="tw-scroll tp-data-scroll">
        <table class="tp-data">
            <thead>
                <tr>
                    <th>Công trình</th>
                    <th>Người phụ trách</th>
                    <th class="tp-data__num">Tổng đầu việc báo cáo</th>
                    <th class="tp-data__num">Hoàn thành</th>
                    <th class="tp-data__num">Chưa hoàn thành</th>
                    <th class="tp-data__num">Giờ thực tế</th>
                    <th>Chi tiết</th>
                </tr>
            </thead>
            <tbody>
                @forelse($sites as $site)
                    <tr>
                        <td>{{ \App\Services\Technical\TechnicalWeekPlanService::displayLabel($site['site_name']) }}</td>
                        <td>{{ $site['lead_name'] ? \App\Services\Technical\TechnicalWeekPlanService::displayLabel($site['lead_name']) : '—' }}</td>
                        <td class="tp-data__num">{{ $site['total_items'] }}</td>
                        <td class="tp-data__num">{{ $site['done_items'] }}</td>
                        <td class="tp-data__num">{{ $site['not_done_items'] }}</td>
                        <td class="tp-data__num">{{ $planner->minutesLabel($site['actual_minutes']) }}</td>
                        <td>
                            <a href="{{ route('technical.daily-reports.index', array_merge(request()->except(['page', 'tab', 'site_id']), ['site_id' => $site['site_id'], 'tab' => \App\Http\Controllers\Technical\TechnicalDailyReportController::TAB_WEEKLY])) }}">
                                Lọc theo công trình
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7">
                            <div class="tw-empty">
                                <i class="bi bi-buildings"></i>
                                <p>Tuần này chưa có công trình nào trong báo cáo / kế hoạch</p>
                            </div>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="tp-cards-list">
        @forelse($sites as $site)
            <article class="tp-jobcard">
                <p class="tp-jobcard__title">{{ \App\Services\Technical\TechnicalWeekPlanService::displayLabel($site['site_name']) }}</p>
                <div class="tp-jobcard__row"><span class="tp-jobcard__label">Người phụ trách</span><span class="tp-jobcard__value">{{ $site['lead_name'] ?: '—' }}</span></div>
                <div class="tp-jobcard__row"><span class="tp-jobcard__label">Hoàn thành / chưa xong</span><span class="tp-jobcard__value">{{ $site['done_items'] }} / {{ $site['not_done_items'] }}</span></div>
                <div class="tp-jobcard__row"><span class="tp-jobcard__label">Giờ thực tế</span><span class="tp-jobcard__value">{{ $planner->minutesLabel($site['actual_minutes']) }}</span></div>
            </article>
        @empty
            <div class="tw-empty"><p>Tuần này chưa có công trình nào</p></div>
        @endforelse
    </div>
</div>
<!-- TECHNICAL_WEEKLY_SUMMARY_END -->
