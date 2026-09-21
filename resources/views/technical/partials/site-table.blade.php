{{-- Bảng số liệu theo CÔNG TRÌNH — dùng chung Tổng kết tuần và Dashboard. --}}
<div class="tw-scroll tp-data-scroll">
    <table class="tp-data">
        <thead>
            <tr>
                <th>Công trình</th>
                <th class="tp-data__num">Nhân sự tham gia</th>
                <th class="tp-data__num">Công việc kế hoạch</th>
                <th class="tp-data__num">Hoàn thành</th>
                <th class="tp-data__num">Trễ hạn</th>
                <th class="tp-data__num">Tiến độ kỹ thuật</th>
                <th class="tp-data__num">Vấn đề phát sinh</th>
                <th>Chi tiết</th>
            </tr>
        </thead>
        <tbody>
            @forelse($sites as $site)
                <tr>
                    <td>{{ \App\Services\Technical\TechnicalWeekPlanService::displayLabel($site['site_name']) }}</td>
                    <td class="tp-data__num">{{ $site['staff_count'] }}</td>
                    <td class="tp-data__num">{{ $site['planned_items'] }}</td>
                    <td class="tp-data__num">{{ $site['done_items'] }}</td>
                    <td class="tp-data__num">{{ $site['overdue_items'] }}</td>
                    <td class="tp-data__num">{{ $site['avg_progress'] }}%</td>
                    <td class="tp-data__num">{{ $site['issue_items'] }}</td>
                    <td>
                        @if(\Illuminate\Support\Facades\Route::has('projects-unified.show'))
                            <a href="{{ route('projects-unified.show', ['site' => $site['site_id']]) }}">Mở công trình</a>
                        @else
                            <span class="tp-data__muted">—</span>
                        @endif
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="8">
                        <div class="tw-empty">
                            <i class="bi bi-buildings"></i>
                            <p>Chưa có công trình nào trong kế hoạch của kỳ này</p>
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
            <div class="tp-jobcard__row"><span class="tp-jobcard__label">Nhân sự tham gia</span><span class="tp-jobcard__value">{{ $site['staff_count'] }}</span></div>
            <div class="tp-jobcard__row"><span class="tp-jobcard__label">Kế hoạch / hoàn thành</span><span class="tp-jobcard__value">{{ $site['planned_items'] }} / {{ $site['done_items'] }}</span></div>
            <div class="tp-jobcard__row"><span class="tp-jobcard__label">Trễ hạn / vấn đề</span><span class="tp-jobcard__value">{{ $site['overdue_items'] }} / {{ $site['issue_items'] }}</span></div>
            <div class="tp-jobcard__row"><span class="tp-jobcard__label">Tiến độ kỹ thuật</span><span class="tp-jobcard__value">{{ $site['avg_progress'] }}%</span></div>
            @if(\Illuminate\Support\Facades\Route::has('projects-unified.show'))
                <div class="tp-jobcard__actions"><a class="btn btn-sm btn-outline-primary" href="{{ route('projects-unified.show', ['site' => $site['site_id']]) }}">Mở công trình</a></div>
            @endif
        </article>
    @empty
        <div class="tw-empty"><p>Chưa có công trình nào trong kế hoạch của kỳ này</p></div>
    @endforelse
</div>
