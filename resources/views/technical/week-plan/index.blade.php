@extends('layouts.app')

@section('title', 'Kế hoạch tuần')

@push('styles')
    @include('technical.partials.plan-styles')
@endpush

@section('content')
@php
    $weekParam = $weekStart->toDateString();
    $isFinalized = $plan !== null && $plan->isFinalized();
    $stateTone = $plan === null
        ? 'tp-state'
        : 'tp-state tp-state--'.($plan->status === \App\Models\Technical\TechnicalWeekPlan::STATUS_FINALIZED ? 'success' : ($plan->status === \App\Models\Technical\TechnicalWeekPlan::STATUS_ADJUSTED ? 'warning' : 'info'));
    $stateLabel = $plan === null || $itemsByDay->flatten()->isEmpty()
        ? $statusNotStarted
        : $plan->statusLabel();
@endphp

<div class="container-fluid py-3 tw-wrap">

    @include('technical.partials.plan-header', [
        'variant' => 'staff',
        'title' => 'Kế hoạch tuần',
        'subtitle' => 'Lập kế hoạch làm việc 7 ngày của bạn. Giờ làm việc chuẩn: '.$planner->minutesLabel($dailyMinutes).'/ngày.',
        'weekParam' => $weekParam,
        'guideSlug' => 'nhan-vien-ke-hoach-tuan',
        'extra' => '<a href="'.e(route('technical.daily-reports.create')).'" class="btn btn-outline-secondary btn-sm"><i class="bi bi-journal-plus"></i> Viết báo cáo</a>',
    ])

    {{-- Tab NỘI BỘ của trang Kế hoạch — không thêm mục nào vào sidebar. --}}
    @php $planTab = $tab ?? 'this'; @endphp
    <div class="tw-chips tp-tabs" role="tablist" aria-label="Các tab của trang Kế hoạch">
        <a class="tw-chip {{ $planTab === 'this' ? 'is-active' : '' }}"
           href="{{ route('technical.week-plan.index', ['tab' => 'this']) }}">Tuần này</a>
        <a class="tw-chip {{ $planTab === 'prev' ? 'is-active' : '' }}"
           href="{{ route('technical.week-plan.index', ['tab' => 'prev']) }}">Tuần trước</a>
        <a class="tw-chip {{ $planTab === 'history' ? 'is-active' : '' }}"
           href="{{ route('technical.week-plan.index', ['tab' => 'history']) }}">Lịch sử kế hoạch</a>
    </div>

    @include('technical.workboard.partials.flash')

@if($planTab === 'history')
    {{-- ---------- Tab: Lịch sử kế hoạch của chính tôi ---------- --}}
    <div class="tw-card">
        <div class="tw-card__head">
            <h2 class="tw-card__title">Lịch sử kế hoạch của tôi</h2>
        </div>
        <div class="tw-card__body tw-card__body--flush">
            @if(($history ?? collect())->isEmpty())
                <div class="tw-empty">
                    <i class="bi bi-calendar-week"></i>
                    <p>Bạn chưa lập kế hoạch tuần nào</p>
                    <small>Mở tab "Tuần này" để bắt đầu lập kế hoạch.</small>
                    <div class="mt-3">
                        <a class="btn btn-primary" href="{{ route('technical.week-plan.index', ['tab' => 'this']) }}">
                            <i class="bi bi-calendar-week"></i> Lập kế hoạch
                        </a>
                    </div>
                </div>
            @else
                <div class="tw-scroll">
                    <table class="tp-table">
                        <thead>
                            <tr>
                                <th>Tuần</th>
                                <th class="tp-col-narrow">Trạng thái</th>
                                <th class="tp-col-narrow">Số việc</th>
                                <th class="tp-col-actions">Thao tác</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($history as $row)
                                <tr>
                                    <td>{{ $row->plan->week_start->format('d/m/Y') }} – {{ $row->plan->week_start->copy()->addDays(6)->format('d/m/Y') }}</td>
                                    <td><span class="tp-state">{{ $row->plan->statusLabel() }}</span></td>
                                    <td>{{ $row->items_count }}</td>
                                    <td class="tp-table__actions">
                                        <a class="btn btn-sm btn-outline-secondary"
                                           href="{{ route('technical.week-plan.index', ['week' => $row->plan->week_start->toDateString()]) }}">
                                            <i class="bi bi-box-arrow-in-right"></i> Mở tuần này
                                        </a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="tp-cards-list">
                    @foreach($history as $row)
                        <div class="tp-jobcard">
                            <p class="tp-jobcard__title">{{ $row->plan->week_start->format('d/m/Y') }} – {{ $row->plan->week_start->copy()->addDays(6)->format('d/m/Y') }}</p>
                            <div class="tp-jobcard__row">
                                <span class="tp-jobcard__label">Trạng thái</span>
                                <span class="tp-jobcard__value">{{ $row->plan->statusLabel() }}</span>
                            </div>
                            <div class="tp-jobcard__row">
                                <span class="tp-jobcard__label">Số việc</span>
                                <span class="tp-jobcard__value">{{ $row->items_count }}</span>
                            </div>
                            <div class="tp-jobcard__actions">
                                <a class="btn btn-sm btn-outline-secondary"
                                   href="{{ route('technical.week-plan.index', ['week' => $row->plan->week_start->toDateString()]) }}">
                                    <i class="bi bi-box-arrow-in-right"></i> Mở tuần này
                                </a>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </div>
@else

    @if($plan && $plan->update_requested)
        <div class="alert alert-warning d-flex align-items-start gap-2" role="alert">
            <i class="bi bi-arrow-repeat"></i>
            <div>
                <strong>Trưởng phòng yêu cầu cập nhật lại kế hoạch tuần này.</strong><br>
                <span class="small">{{ $plan->update_request_note }}</span>
            </div>
        </div>
    @endif

    @if($plan && $plan->status === \App\Models\Technical\TechnicalWeekPlan::STATUS_ADJUSTED && $plan->adjust_note)
        <div class="alert alert-info d-flex align-items-start gap-2" role="alert">
            <i class="bi bi-pencil-square"></i>
            <div>
                <strong>Kế hoạch đã được Trưởng phòng điều chỉnh.</strong><br>
                <span class="small">Lý do: {{ $plan->adjust_note }}</span>
            </div>
        </div>
    @endif

    {{-- ---------- Thanh chọn tuần ---------- --}}
    <div class="tp-weekbar">
        <div class="tp-weekbar__nav">
            <a class="btn btn-sm btn-outline-secondary" href="{{ route('technical.week-plan.index', ['week' => $prevWeek]) }}">
                <i class="bi bi-chevron-left"></i> Tuần trước
            </a>
            <a class="btn btn-sm {{ $weekParam === $thisWeek ? 'btn-primary' : 'btn-outline-secondary' }}"
               href="{{ route('technical.week-plan.index', ['week' => $thisWeek]) }}">
                Tuần hiện tại
            </a>
            <a class="btn btn-sm btn-outline-secondary" href="{{ route('technical.week-plan.index', ['week' => $nextWeek]) }}">
                Tuần sau <i class="bi bi-chevron-right"></i>
            </a>
        </div>

        <span class="tp-weekbar__range">
            <i class="bi bi-calendar-range"></i>
            {{ $weekStart->format('d/m/Y') }} – {{ $weekEnd->format('d/m/Y') }}
        </span>

        <span class="{{ $stateTone }}">{{ $stateLabel }}</span>

        <span class="tp-weekbar__spacer"></span>

        <div class="tp-weekbar__actions">
            <form method="POST" action="{{ route('technical.week-plan.copy-previous') }}">
                @csrf
                <input type="hidden" name="week" value="{{ $weekParam }}">
                <button type="submit" class="btn btn-sm btn-outline-secondary">
                    <i class="bi bi-files"></i> Sao chép tuần trước
                </button>
            </form>

            <form method="POST" action="{{ route('technical.week-plan.save-draft') }}">
                @csrf
                <input type="hidden" name="week" value="{{ $weekParam }}">
                <button type="submit" class="btn btn-sm btn-outline-secondary">
                    <i class="bi bi-save"></i> Lưu nháp
                </button>
            </form>

            <form method="POST" action="{{ route('technical.week-plan.finalize') }}">
                @csrf
                <input type="hidden" name="week" value="{{ $weekParam }}">
                <button type="submit" class="btn btn-sm btn-success" @disabled(!empty($check['errors']))>
                    <i class="bi bi-check2-circle"></i> Hoàn tất kế hoạch tuần
                </button>
            </form>
        </div>
    </div>

    @include('technical.partials.week-checks', ['check' => $check])

    {{-- ---------- Thêm công việc ---------- --}}
    @if(!$isFinalized || true)
    {{-- `?add=1` (nút "Tạo kế hoạch của tôi" ở header) mở sẵn khung thêm việc. --}}
    <details class="tw-card mb-3" id="tp-add-form" @if($errors->any() || request()->boolean('add')) open @endif>
        <summary class="tw-card__head" style="cursor: pointer;">
            <h2 class="tw-card__title"><i class="bi bi-plus-circle"></i> Thêm công việc vào kế hoạch</h2>
        </summary>

        <div class="tw-card__body">
            <form method="POST" action="{{ route('technical.week-plan.items.store') }}" class="tp-form">
                @csrf
                <input type="hidden" name="week" value="{{ $weekParam }}">

                <div>
                    <label class="form-label small" for="tp-plan-date">Ngày thực hiện</label>
                    <select class="form-select form-select-sm" id="tp-plan-date" name="plan_date" required>
                        @foreach($days as $day)
                            @php
                                $tpRequestedDay = (string) request()->query('day', '');
                                $tpDaySelected = old('plan_date') === $day->toDateString()
                                    || (! old('plan_date') && $tpRequestedDay === $day->toDateString())
                                    || (! old('plan_date') && $tpRequestedDay === '' && $day->isToday());
                            @endphp
                            <option value="{{ $day->toDateString() }}" @selected($tpDaySelected)>
                                {{ $planner->weekdayLabel($day) }} — {{ $day->format('d/m') }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="form-label small" for="tp-day-part">Buổi</label>
                    <select class="form-select form-select-sm" id="tp-day-part" name="day_part" required>
                        @foreach($dayPartOptions as $key => $label)
                            <option value="{{ $key }}" @selected(old('day_part', 'full_day') === $key)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="form-label small" for="tp-start">Giờ bắt đầu (nếu chọn "Giờ cụ thể")</label>
                    <input type="time" class="form-control form-control-sm" id="tp-start" name="start_time" value="{{ old('start_time') }}">
                </div>

                <div>
                    <label class="form-label small" for="tp-end">Giờ kết thúc</label>
                    <input type="time" class="form-control form-control-sm" id="tp-end" name="end_time" value="{{ old('end_time') }}">
                </div>

                <div class="tp-form__full">
                    <label class="form-label small" for="tp-source">Nguồn công việc</label>
                    <select class="form-select form-select-sm" id="tp-source" name="work_item_key">
                        <option value="{{ \App\Models\Technical\TechnicalPlanItem::SOURCE_PERSONAL }}|">
                            Việc nội bộ cá nhân (tự nhập nội dung)
                        </option>
                        @foreach($assignableItems as $workItem)
                            <option value="{{ $workItem->sourceType }}|{{ $workItem->sourceId }}"
                                    @selected(old('work_item_key') === $workItem->sourceType.'|'.$workItem->sourceId)>
                                [{{ $workItem->sourceLabel() }}] {{ \App\Services\Technical\TechnicalWeekPlanService::displayLabel($workItem->title) }}@if($workItem->siteName) — {{ \App\Services\Technical\TechnicalWeekPlanService::displayLabel($workItem->siteName) }}@endif
                            </option>
                        @endforeach
                    </select>
                    @if($assignableItems->isEmpty())
                        <div class="form-text">
                            Hiện chưa có công việc nào từ Công trình / Task / Bảo trì được giao cho bạn.
                            Bạn vẫn có thể thêm việc nội bộ cá nhân.
                        </div>
                    @endif
                </div>

                <div class="tp-form__full">
                    <label class="form-label small" for="tp-title">Nội dung công việc</label>
                    <input type="text" class="form-control form-control-sm" id="tp-title" name="title"
                           maxlength="255" required value="{{ old('title') }}"
                           placeholder="Ví dụ: Lắp đặt inverter tầng mái">
                </div>

                <div class="tp-form__full">
                    <label class="form-label small" for="tp-objective">Mục tiêu cần đạt</label>
                    <textarea class="form-control form-control-sm" id="tp-objective" name="objective" rows="2"
                              placeholder="Kết quả cụ thể phải đạt trong ngày">{{ old('objective') }}</textarea>
                </div>

                <div>
                    <label class="form-label small" for="tp-minutes">Thời gian dự kiến (phút)</label>
                    <input type="number" class="form-control form-control-sm" id="tp-minutes" name="estimated_minutes"
                           min="15" step="15" value="{{ old('estimated_minutes', config('technical.week_plan.default_item_minutes')) }}">
                </div>

                <div>
                    <label class="form-label small" for="tp-priority">Mức ưu tiên</label>
                    <select class="form-select form-select-sm" id="tp-priority" name="priority" required>
                        @foreach($priorityOptions as $key => $label)
                            <option value="{{ $key }}" @selected(old('priority', 'normal') === $key)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="tp-form__full">
                    <label class="form-label small" for="tp-note">Ghi chú</label>
                    <input type="text" class="form-control form-control-sm" id="tp-note" name="note"
                           maxlength="2000" value="{{ old('note') }}">
                </div>

                <div class="tp-form__actions">
                    <button type="submit" class="btn btn-primary btn-sm">
                        <i class="bi bi-plus-lg"></i> Thêm vào kế hoạch
                    </button>
                </div>
            </form>
        </div>
    </details>
    @endif

    {{-- ---------- 7 ngày ---------- --}}
    @foreach($days as $day)
        @php
            $key = $day->toDateString();
            $dayItems = $itemsByDay->get($key, collect());
            $mark = $dayMarks->get($key);
            $dayStat = $check['daily'][$key] ?? ['minutes' => 0, 'overloaded' => false];
        @endphp

        <details class="tp-day {{ $day->isToday() ? 'tp-day--today' : '' }} {{ !empty($dayStat['overloaded']) ? 'tp-day--overload' : '' }}"
                 @if($day->isToday() || $dayItems->isNotEmpty()) open @endif>
            <summary class="tp-day__head">
                <span class="tp-day__title">{{ $planner->weekdayLabel($day) }}</span>
                <span class="tp-day__date">{{ $day->format('d/m/Y') }}</span>

                <span class="tp-day__meta">
                    <span class="tp-state">{{ $dayItems->count() }} việc</span>
                    <span class="tp-state {{ !empty($dayStat['overloaded']) ? 'tp-state--warning' : '' }}">
                        {{ $planner->minutesLabel((int) ($dayStat['minutes'] ?? 0)) }}
                    </span>
                    @if($mark)
                        <span class="tp-state tp-state--info">{{ $mark->markLabel() }}</span>
                    @endif
                    <a class="btn btn-sm btn-outline-primary tp-day__add"
                       href="{{ route('technical.week-plan.index', ['week' => $weekParam, 'add' => 1, 'day' => $key]) }}#tp-add-form">
                        <i class="bi bi-plus-lg"></i> Thêm công việc
                    </a>
                </span>
            </summary>

            <div class="tp-day__body">
                @if($dayItems->isEmpty())
                    <div class="tw-empty">
                        <i class="bi bi-calendar-plus"></i>
                        <p>Chưa có kế hoạch cho ngày này</p>
                        <small>Thêm việc ở khung "Thêm công việc vào kế hoạch", hoặc đánh dấu ngày bên dưới.</small>

                        <form method="POST" action="{{ route('technical.week-plan.mark-day') }}"
                              class="d-flex flex-wrap gap-2 justify-content-center mt-3">
                            @csrf
                            <input type="hidden" name="week" value="{{ $weekParam }}">
                            <input type="hidden" name="plan_date" value="{{ $key }}">
                            <label class="visually-hidden" for="mark-{{ $key }}">Đánh dấu ngày {{ $day->format('d/m') }}</label>
                            <select class="form-select form-select-sm w-auto" id="mark-{{ $key }}" name="mark">
                                @foreach($markOptions as $mkey => $mlabel)
                                    <option value="{{ $mkey }}">{{ $mlabel }}</option>
                                @endforeach
                            </select>
                            <input type="text" class="form-control form-control-sm w-auto" name="reason"
                                   maxlength="500" placeholder="Lý do (không bắt buộc)">
                            <button type="submit" class="btn btn-sm btn-outline-secondary">Đánh dấu</button>
                        </form>
                    </div>
                @else
                    <table class="tp-table">
                        <thead>
                            <tr>
                                <th class="tp-col-narrow">Ngày</th>
                                <th class="tp-col-narrow">Buổi</th>
                                <th>Nội dung công việc</th>
                                <th class="tp-col-mid">Công trình</th>
                                <th>Mục tiêu cần đạt</th>
                                <th class="tp-col-narrow">Thời gian dự kiến</th>
                                <th class="tp-col-narrow">Ưu tiên</th>
                                <th class="tp-col-mid">Ghi chú</th>
                                <th class="tp-col-narrow">Trạng thái</th>
                                <th class="tp-col-actions">Thao tác</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($dayItems as $item)
                                <tr>
                                    <td>{{ $item->plan_date->format('d/m') }}</td>
                                    <td>{{ $item->dayPartLabel() }}</td>
                                    <td>
                                        <span class="tp-table__title">{{ \App\Services\Technical\TechnicalWeekPlanService::displayLabel($item->title) }}</span>
                                        <span class="tp-table__sub">{{ $item->sourceLabel() }}</span>
                                    </td>
                                    <td>{{ \App\Services\Technical\TechnicalWeekPlanService::displayLabel($item->site_name ?? '—') }}</td>
                                    <td>{{ \App\Services\Technical\TechnicalWeekPlanService::displayLabel($item->objective ?: '—') }}</td>
                                    <td>{{ $planner->minutesLabel($item->estimated_minutes) }}</td>
                                    <td>{{ $item->priorityLabel() }}</td>
                                    <td>{{ $item->note ?: '—' }}</td>
                                    <td>
                                        <span class="tp-state tp-state--{{ $item->statusTone() === 'secondary' ? 'info' : ($item->statusTone() === 'success' ? 'success' : ($item->statusTone() === 'danger' ? 'danger' : 'warning')) }}">
                                            {{ $item->statusLabel() }}
                                        </span>
                                    </td>
                                    <td class="tp-table__actions">
                                        <a class="btn btn-sm btn-outline-secondary"
                                           href="#tp-edit-{{ $item->id }}"
                                           data-bs-toggle="collapse" role="button"
                                           aria-expanded="false" aria-controls="tp-edit-{{ $item->id }}">
                                            <i class="bi bi-pencil"></i> Sửa
                                        </a>
                                    </td>
                                </tr>
                                <tr class="collapse" id="tp-edit-{{ $item->id }}">
                                    <td colspan="10">
                                        @include('technical.week-plan.partials.item-editor', [
                                            'item' => $item,
                                            'days' => $days,
                                            'planner' => $planner,
                                            'dayPartOptions' => $dayPartOptions,
                                            'priorityOptions' => $priorityOptions,
                                            'statusOptions' => $statusOptions,
                                            'weekParam' => $weekParam,
                                            'hasReport' => isset($reportMap[$item->id]),
                                        ])
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>

                    {{-- Mobile: mỗi việc là một thẻ --}}
                    <div class="tp-cards-list">
                        @foreach($dayItems as $item)
                            <div class="tp-jobcard">
                                <p class="tp-jobcard__title">{{ \App\Services\Technical\TechnicalWeekPlanService::displayLabel($item->title) }}</p>
                                <div class="tp-jobcard__row">
                                    <span class="tp-jobcard__label">Buổi</span>
                                    <span class="tp-jobcard__value">{{ $item->dayPartLabel() }}</span>
                                </div>
                                <div class="tp-jobcard__row">
                                    <span class="tp-jobcard__label">Nguồn</span>
                                    <span class="tp-jobcard__value">{{ $item->sourceLabel() }}</span>
                                </div>
                                <div class="tp-jobcard__row">
                                    <span class="tp-jobcard__label">Công trình</span>
                                    <span class="tp-jobcard__value">{{ \App\Services\Technical\TechnicalWeekPlanService::displayLabel($item->site_name ?? '—') }}</span>
                                </div>
                                <div class="tp-jobcard__row">
                                    <span class="tp-jobcard__label">Mục tiêu</span>
                                    <span class="tp-jobcard__value">{{ \App\Services\Technical\TechnicalWeekPlanService::displayLabel($item->objective ?: '—') }}</span>
                                </div>
                                <div class="tp-jobcard__row">
                                    <span class="tp-jobcard__label">Dự kiến</span>
                                    <span class="tp-jobcard__value">{{ $planner->minutesLabel($item->estimated_minutes) }}</span>
                                </div>
                                <div class="tp-jobcard__row">
                                    <span class="tp-jobcard__label">Ưu tiên</span>
                                    <span class="tp-jobcard__value">{{ $item->priorityLabel() }}</span>
                                </div>
                                <div class="tp-jobcard__row">
                                    <span class="tp-jobcard__label">Trạng thái</span>
                                    <span class="tp-jobcard__value">{{ $item->statusLabel() }}</span>
                                </div>
                                <div class="tp-jobcard__actions">
                                    <a class="btn btn-sm btn-outline-secondary" href="#tp-medit-{{ $item->id }}"
                                       data-bs-toggle="collapse" role="button"
                                       aria-expanded="false" aria-controls="tp-medit-{{ $item->id }}">
                                        <i class="bi bi-pencil"></i> Sửa / chuyển ngày
                                    </a>
                                </div>
                                <div class="collapse mt-2" id="tp-medit-{{ $item->id }}">
                                    @include('technical.week-plan.partials.item-editor', [
                                        'item' => $item,
                                        'days' => $days,
                                        'planner' => $planner,
                                        'dayPartOptions' => $dayPartOptions,
                                        'priorityOptions' => $priorityOptions,
                                        'statusOptions' => $statusOptions,
                                        'weekParam' => $weekParam,
                                        'hasReport' => isset($reportMap[$item->id]),
                                        'idPrefix' => 'm',
                                    ])
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </details>
    @endforeach

@endif

</div>
@endsection
