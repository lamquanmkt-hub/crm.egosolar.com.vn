@extends('layouts.app')
<?php
$isMaintenance = $calendarMode === 'maintenance';
$meta = $isMaintenance
    ? ['title' => 'Lịch bảo trì', 'desc' => 'Chỉ hiển thị các đợt O&M và bảo hành đã lên lịch.', 'icon' => 'bi-shield-check']
    : ['title' => 'Lịch thi công', 'desc' => 'Chỉ hiển thị lịch triển khai công trình và tình trạng chuẩn bị vật tư.', 'icon' => 'bi-tools'];
$previousUrl = request()->fullUrlWithQuery(['month' => $month->copy()->subMonth()->format('Y-m')]);
$nextUrl = request()->fullUrlWithQuery(['month' => $month->copy()->addMonth()->format('Y-m')]);
$resetUrl = $isMaintenance
    ? route('technical-workspace.operations.maintenance-calendar')
    : route('technical-workspace.operations.installation-calendar');
$eventStatusLabels = [
    'scheduled' => 'Đã lên lịch', 'unassigned' => 'Chưa phân công', 'assigned' => 'Đã phân công',
    'in_progress' => 'Đang thực hiện', 'completed' => 'Hoàn thành', 'approved' => 'Đã duyệt',
];
?>
@section('title', $meta['title'].' Kỹ thuật')
@push('styles')
<link rel="stylesheet" href="{{ asset('css/technical-workspace.css') }}?v={{ file_exists(public_path('css/technical-workspace.css')) ? filemtime(public_path('css/technical-workspace.css')) : time() }}">
@endpush
@section('content')
<div class="tw-page"><div class="tw-shell">
    <section class="tw-hero tw-hero--compact">
        <div class="tw-hero__row">
            <div>
                <div class="tw-kicker">V15.3 · ĐIỀU HÀNH KỸ THUẬT</div>
                <h1><i class="bi {{ $meta['icon'] }}"></i> {{ $meta['title'] }}</h1>
                <p>{{ $meta['desc'] }} Hai nguồn lịch được tách riêng để không nhầm việc thi công với bảo trì.</p>
            </div>
            <div class="tw-title-actions">
                <?php if ($isMaintenance): ?>
                    <a class="tw-btn" href="{{ route('ky-thuat.maintenance.index') }}"><i class="bi bi-box-arrow-up-right"></i>Quản lý O&amp;M</a>
                <?php else: ?>
                    <a class="tw-btn" href="{{ route('technical-workspace.materials.index') }}"><i class="bi bi-box-seam"></i>Kiểm tra vật tư</a>
                <?php endif; ?>
            </div>
        </div>
        @include('technical.workspace.partials-nav')
    </section>

    @include('technical.workspace.operations-nav')

    <section class="tw-kpis tw-kpis--5">
        <article class="tw-kpi tw-kpi--blue"><i class="bi bi-calendar2-check"></i><div><span>Tổng lịch trong tháng</span><strong>{{ $summary['total'] }}</strong><small>{{ $month->format('m/Y') }}</small></div></article>
        <article class="tw-kpi tw-kpi--green"><i class="bi bi-calendar-day"></i><div><span>Hôm nay</span><strong>{{ $summary['today'] }}</strong><small>Việc cần thực hiện</small></div></article>
        <article class="tw-kpi tw-kpi--cyan"><i class="bi bi-arrow-up-right-circle"></i><div><span>Sắp tới</span><strong>{{ $summary['upcoming'] }}</strong><small>Chưa tới thời gian</small></div></article>
        <article class="tw-kpi tw-kpi--amber"><i class="bi bi-person-exclamation"></i><div><span>Chưa phân công</span><strong>{{ $summary['unassigned'] }}</strong><small>Cần chọn nhóm thực hiện</small></div></article>
        <?php if ($isMaintenance): ?>
            <article class="tw-kpi tw-kpi--violet"><i class="bi bi-patch-check"></i><div><span>Đã hoàn thành</span><strong>{{ $summary['completed'] }}</strong><small>Trong tháng đang xem</small></div></article>
        <?php else: ?>
            <article class="tw-kpi {{ $summary['material_alerts'] > 0 ? 'tw-kpi--red' : 'tw-kpi--neutral' }}"><i class="bi bi-box2-heart"></i><div><span>Cảnh báo vật tư</span><strong>{{ $summary['material_alerts'] }}</strong><small>Thiếu hoặc đang cấp một phần</small></div></article>
        <?php endif; ?>
    </section>

    <section class="tw-card tw-calendar-filter"><div class="tw-card__body">
        <form class="tw-filter" method="GET">
            <label>Tháng<input class="tw-input" type="month" name="month" value="{{ $month->format('Y-m') }}"></label>
            <label class="tw-filter__search">Tìm công trình<input class="tw-input" name="q" value="{{ request('q') }}" placeholder="Tên, mã, địa chỉ..."></label>
            <label>Kỹ thuật viên<select class="tw-select" name="user_id"><option value="">Tất cả kỹ thuật viên</option>
                <?php foreach ($teamMembers as $member): ?>
                    <option value="{{ $member->id }}" {{ (int) $selectedUserId === (int) $member->id ? 'selected' : '' }}>{{ $member->name }}</option>
                <?php endforeach; ?>
            </select></label>
            <button class="tw-btn" type="submit"><i class="bi bi-funnel"></i>Lọc lịch</button>
            <a class="tw-btn tw-btn--soft" href="{{ $resetUrl }}">Đặt lại</a>
            <span class="tw-calendar-pager"><a href="{{ $previousUrl }}" title="Tháng trước"><i class="bi bi-chevron-left"></i></a><b>{{ $month->format('m/Y') }}</b><a href="{{ $nextUrl }}" title="Tháng sau"><i class="bi bi-chevron-right"></i></a></span>
        </form>
    </div></section>

    <div class="tw-calendar-layout">
        <section class="tw-calendar tw-calendar--separate">
            <div class="tw-calendar__weekdays">
                <?php foreach (['T2','T3','T4','T5','T6','T7','CN'] as $label): ?><div>{{ $label }}</div><?php endforeach; ?>
            </div>
            <div class="tw-calendar__grid">
                <?php foreach ($days as $day): ?>
                    <?php $dayEvents = $eventsByDate->get($day->toDateString(), collect()); ?>
                    <div class="tw-calendar__day {{ $day->month !== $month->month ? 'is-outside' : '' }} {{ $day->isToday() ? 'is-today' : '' }}">
                        <div class="tw-calendar__date"><span>{{ $day->day }}</span><small>{{ $dayEvents->count() }} lịch</small></div>
                        <div class="tw-calendar__events">
                            <?php foreach ($dayEvents as $event): ?>
                                <?php
                                $names = $event->participants->pluck('user.name')->filter()->join(', ');
                                $statusLabel = $eventStatusLabels[$event->status] ?? str_replace('_', ' ', (string) $event->status);
                                $materialWarning = ! $isMaintenance && in_array($event->material_status, ['shortage', 'waiting_import', 'partial'], true);
                                ?>
                                <article class="tw-calendar-event is-{{ $event->event_type }} {{ $materialWarning ? 'has-material-alert' : '' }}">
                                    <div class="tw-calendar-event__time"><i class="bi bi-clock"></i>{{ $event->starts_at?->format('H:i') }} · {{ $statusLabel }}</div>
                                    <strong>{{ $event->title }}</strong>
                                    <small><i class="bi bi-people"></i>{{ $names ?: 'Chưa phân công' }}</small>
                                    <?php if ($event->address): ?><small><i class="bi bi-geo-alt"></i>{{ \Illuminate\Support\Str::limit($event->address, 70) }}</small><?php endif; ?>
                                    <?php if ($materialWarning): ?><small class="tw-material-warning"><i class="bi bi-exclamation-triangle"></i>Cần kiểm tra vật tư</small><?php endif; ?>
                                    <?php if ($event->workspace_url): ?><a href="{{ $event->workspace_url }}">Mở hồ sơ <i class="bi bi-arrow-right"></i></a><?php endif; ?>
                                </article>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>

        <aside class="tw-card tw-calendar-agenda">
            <div class="tw-card__head"><div><h2>Danh sách trong tháng</h2><p>{{ $summary['total'] }} lịch theo bộ lọc hiện tại</p></div></div>
            <div class="tw-agenda-list">
                <?php foreach ($agendaEvents as $event): ?>
                    <?php $names = $event->participants->pluck('user.name')->filter()->join(', '); ?>
                    <article>
                        <time><b>{{ $event->starts_at?->format('d') }}</b><span>Th{{ $event->starts_at?->format('m') }}</span></time>
                        <div><strong>{{ $event->title }}</strong><small>{{ $event->starts_at?->format('H:i') }} · {{ $names ?: 'Chưa phân công' }}</small></div>
                        <?php if ($event->workspace_url): ?><a href="{{ $event->workspace_url }}"><i class="bi bi-arrow-up-right"></i></a><?php endif; ?>
                    </article>
                <?php endforeach; ?>
                <?php if ($agendaEvents->isEmpty()): ?><div class="tw-empty"><i class="bi bi-calendar2-x"></i><strong>Không có lịch phù hợp</strong><span>Đổi tháng hoặc điều kiện lọc để xem thêm.</span></div><?php endif; ?>
            </div>
        </aside>
    </div>
</div></div>
@endsection
