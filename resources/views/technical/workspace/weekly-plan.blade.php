@extends('layouts.app')
@section('title', 'Kế hoạch tuần Kỹ thuật')
@push('styles')
<link rel="stylesheet" href="{{ asset('css/technical-workspace.css') }}?v={{ file_exists(public_path('css/technical-workspace.css')) ? filemtime(public_path('css/technical-workspace.css')) : time() }}">
@endpush
<?php
    $typeLabels = ['survey' => 'Khảo sát', 'installation' => 'Thi công', 'maintenance' => 'O&M'];
    $keptFilters = array_filter([
        'type' => $selectedType,
        'user_id' => $selectedUserId ?: null,
        'q' => trim((string) request('q')) ?: null,
    ], fn ($value) => $value !== null && $value !== '');
    $previousWeek = array_merge($keptFilters, ['week' => $weekStart->copy()->subWeek()->toDateString()]);
    $nextWeek = array_merge($keptFilters, ['week' => $weekStart->copy()->addWeek()->toDateString()]);
    $currentWeek = array_merge($keptFilters, ['week' => today()->startOfWeek(\Carbon\Carbon::MONDAY)->toDateString()]);
?>
@section('content')
<div class="tw-page"><div class="tw-shell">
    <section class="tw-hero tw-hero--compact">
        <div class="tw-hero__row">
            <div>
                <div class="tw-kicker">PHÒNG KỸ THUẬT · KẾ HOẠCH LIÊN KẾT</div>
                <h1>Kế hoạch tuần {{ $weekStart->format('d/m') }} – {{ $weekEnd->format('d/m/Y') }}</h1>
                <p>Lịch khảo sát, thi công và O&amp;M được đọc trực tiếp từ hồ sơ nguồn; không nhập lại và không sửa dữ liệu tại màn hình này.</p>
            </div>
            <div class="tw-title-actions">
                <a class="tw-btn tw-btn--soft" href="{{ route('technical-workspace.operations.weekly-plan', $previousWeek) }}"><i class="bi bi-chevron-left"></i>Tuần trước</a>
                <a class="tw-btn tw-btn--soft" href="{{ route('technical-workspace.operations.weekly-plan', $currentWeek) }}">Tuần này</a>
                <a class="tw-btn" href="{{ route('technical-workspace.operations.weekly-plan', $nextWeek) }}">Tuần sau<i class="bi bi-chevron-right"></i></a>
            </div>
        </div>
        @include('technical.workspace.partials-nav')
    </section>

    <section class="tw-source-strip" aria-label="Nguồn dữ liệu kế hoạch tuần">
        <div class="tw-source-strip__title"><i class="bi bi-arrow-repeat"></i><span>Đồng bộ lúc <strong>{{ $generatedAt->format('H:i') }}</strong></span></div>
        <div><span>Hồ sơ công trình</span><strong>{{ $sourceSummary['project'] }}</strong></div>
        <div><span>Lịch thi công</span><strong>{{ $summary['installation'] }}</strong></div>
        <div><span>Lịch O&amp;M</span><strong>{{ $summary['maintenance'] }}</strong></div>
        <div><span>Lịch khảo sát</span><strong>{{ $summary['survey'] }}</strong></div>
    </section>

    <section class="tw-kpis tw-kpis--5">
        <article class="tw-kpi tw-kpi--blue"><i class="bi bi-calendar2-week"></i><div><span>Tổng lịch trong tuần</span><strong>{{ $summary['total'] }}</strong><small>Sau khi áp dụng bộ lọc</small></div></article>
        <article class="tw-kpi tw-kpi--cyan"><i class="bi bi-tools"></i><div><span>Thi công</span><strong>{{ $summary['installation'] }}</strong><small>Liên kết hồ sơ công trình</small></div></article>
        <article class="tw-kpi tw-kpi--violet"><i class="bi bi-shield-check"></i><div><span>Bảo trì O&amp;M</span><strong>{{ $summary['maintenance'] }}</strong><small>Liên kết từng đợt bảo trì</small></div></article>
        <article class="tw-kpi {{ $summary['unassigned'] ? 'tw-kpi--amber' : 'tw-kpi--green' }}"><i class="bi bi-person-plus"></i><div><span>Chưa phân công</span><strong>{{ $summary['unassigned'] }}</strong><small>Lịch cần chọn nhóm thực hiện</small></div></article>
        <article class="tw-kpi {{ $summary['conflicts'] ? 'tw-kpi--red' : 'tw-kpi--neutral' }}"><i class="bi bi-calendar2-x"></i><div><span>Trùng thời gian</span><strong>{{ $summary['conflicts'] }}</strong><small>So sánh giờ theo từng nhân sự</small></div></article>
    </section>

    <section class="tw-card tw-week-filter"><div class="tw-card__body">
        <form class="tw-filter" method="GET">
            <label>Tuần bắt đầu
                <input class="tw-input" type="date" name="week" value="{{ $weekStart->toDateString() }}">
            </label>
            <label>Loại lịch
                <select class="tw-select" name="type">
                    <option value="">Tất cả lịch</option>
                    <?php foreach ($typeLabels as $value => $label): ?>
                        <option value="{{ $value }}" {{ $selectedType === $value ? 'selected' : '' }}>{{ $label }}</option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>Nhân sự
                <select class="tw-select" name="user_id">
                    <option value="">Tất cả nhân sự</option>
                    <?php foreach ($teamMembers as $member): ?>
                        <option value="{{ $member->id }}" {{ $selectedUserId === (int) $member->id ? 'selected' : '' }}>{{ $member->name }}</option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label class="tw-filter__search">Tìm công trình
                <input class="tw-input" name="q" value="{{ request('q') }}" placeholder="Tên, mã, địa điểm, nhân sự...">
            </label>
            <button class="tw-btn" type="submit"><i class="bi bi-funnel"></i>Lọc kế hoạch</button>
            <a class="tw-btn tw-btn--soft" href="{{ route('technical-workspace.operations.weekly-plan', ['week' => $weekStart->toDateString()]) }}">Xóa lọc</a>
        </form>
    </div></section>

    <?php if ($conflicts->isNotEmpty()): ?>
        <section class="tw-alert tw-alert--danger">
            <i class="bi bi-exclamation-triangle"></i>
            <div><strong>Có {{ $conflicts->count() }} cặp lịch trùng thời gian</strong><span>Kiểm tra các lịch viền đỏ bên dưới hoặc mở hồ sơ nguồn để điều chỉnh.</span></div>
        </section>
    <?php endif; ?>

    <section class="tw-week-board" aria-label="Lịch Kỹ thuật 7 ngày">
        <?php foreach ($days as $day): ?>
            <?php $dayEvents = $eventsByDate->get($day->toDateString(), collect()); ?>
            <article class="tw-week-day {{ $day->isToday() ? 'is-today' : '' }}">
                <header>
                    <div><span>{{ ['Mon'=>'Thứ Hai','Tue'=>'Thứ Ba','Wed'=>'Thứ Tư','Thu'=>'Thứ Năm','Fri'=>'Thứ Sáu','Sat'=>'Thứ Bảy','Sun'=>'Chủ Nhật'][$day->format('D')] ?? $day->format('D') }}</span><strong>{{ $day->format('d/m') }}</strong></div>
                    <b>{{ $dayEvents->count() }}</b>
                </header>
                <div class="tw-week-day__body">
                    <?php if ($dayEvents->isNotEmpty()): ?>
                        <?php foreach ($dayEvents as $event): ?>
                            <?php
                                $isConflict = $conflictEventIds->contains((int) $event->id);
                                $sourceUrl = null;
                                if ($event->source_type === 'maintenance' && \Illuminate\Support\Facades\Route::has('ky-thuat.maintenance.show')) {
                                    $sourceUrl = route('ky-thuat.maintenance.show', $event->source_id);
                                } elseif ($event->project_id && \Illuminate\Support\Facades\Route::has('project-test.show')) {
                                    $sourceUrl = route('project-test.show', $event->project_id);
                                }
                            ?>
                            <div class="tw-linked-event is-{{ $event->event_type }} {{ $isConflict ? 'has-conflict' : '' }}">
                                <div class="tw-linked-event__top">
                                    <span class="tw-event-type is-{{ $event->event_type }}">{{ $typeLabels[$event->event_type] ?? $event->event_type }}</span>
                                    <time>{{ $event->starts_at?->format('H:i') }}–{{ $event->ends_at?->format('H:i') ?: '—' }}</time>
                                </div>
                                <strong>{{ $event->title }}</strong>
                                <?php if ($event->project?->code): ?><small class="tw-linked-event__code">{{ $event->project->code }}</small><?php endif; ?>
                                <small><i class="bi bi-geo-alt"></i>{{ $event->address ?: 'Chưa có địa điểm' }}</small>
                                <small><i class="bi bi-people"></i>{{ $event->participants->pluck('user.name')->filter()->join(', ') ?: 'Chưa phân công' }}</small>
                                <?php if ($event->event_type === 'installation'): ?>
                                    <small class="tw-material-state"><i class="bi bi-box-seam"></i>Vật tư: {{ $event->material_status ? str_replace('_', ' ', $event->material_status) : 'Chưa có đề xuất' }}</small>
                                <?php endif; ?>
                                <div class="tw-linked-event__footer">
                                    <span><i class="bi bi-link-45deg"></i>{{ $event->source_type === 'maintenance' ? 'Đợt O&M' : 'Hồ sơ công trình' }}</span>
                                    <?php if ($sourceUrl): ?><a href="{{ $sourceUrl }}">Mở hồ sơ<i class="bi bi-arrow-up-right"></i></a><?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="tw-day__empty"><i class="bi bi-calendar2-check"></i><span>Chưa có lịch</span></div>
                    <?php endif; ?>
                </div>
            </article>
        <?php endforeach; ?>
    </section>

    <?php if ($conflicts->isNotEmpty()): ?>
        <section class="tw-card">
            <div class="tw-card__head"><div><h2>Chi tiết lịch trùng</h2><p>Mỗi dòng là một cặp lịch thực sự giao nhau.</p></div><span class="tw-pill danger">{{ $conflicts->count() }} cặp</span></div>
            <div class="tw-conflict-list">
                <?php foreach ($conflicts as $conflict): ?>
                    <div><i class="bi bi-person-exclamation"></i><span><strong>{{ $conflict['user_name'] }}</strong><small>{{ \Carbon\Carbon::parse($conflict['date'])->format('d/m/Y') }} lúc {{ $conflict['starts_at']->format('H:i') }}</small></span><b>{{ $conflict['events']->pluck('title')->join(' ↔ ') }}</b></div>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endif; ?>
</div></div>
@endsection
