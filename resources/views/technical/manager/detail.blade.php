@extends('layouts.app')

@section('title', 'Kế hoạch của '.\App\Services\Technical\TechnicalWeekPlanService::displayLabel($member->name))

@push('styles')
    @include('technical.partials.plan-styles')
@endpush

@section('content')
<div class="container-fluid py-3 tw-wrap">

    @include('technical.partials.plan-header', [
        'variant' => 'manager',
        'title' => 'Kế hoạch của '.\App\Services\Technical\TechnicalWeekPlanService::displayLabel($member->name),
        'subtitle' => 'Tuần '.$weekStart->format('d/m/Y').' – '.$weekEnd->format('d/m/Y').'. Mọi điều chỉnh đều bắt buộc nhập lý do và được ghi nhật ký.',
        'weekParam' => $weekStart->toDateString(),
        'memberId' => $member->id,
        {{-- Không override CTA nữa: hai nút mở thẳng drawer Tạo kế hoạch /
             Giao việc ở trang ma trận, với nhân viên và tuần này chọn sẵn. --}}
        'extra' =>'<a href="'.e(route('technical.manager.board', ['week' => $weekStart->toDateString()])).'" class="btn btn-outline-secondary btn-sm"><i class="bi bi-grid-3x3"></i> Về ma trận</a>'
            .'<a href="'.e(route('technical.daily-reports.index', ['user_id' => $member->id])).'" class="btn btn-outline-secondary btn-sm"><i class="bi bi-journal-text"></i> Báo cáo của nhân viên</a>',
    ])

    @include('technical.workboard.partials.flash')

    <div class="tp-weekbar">
        <div class="tp-weekbar__nav">
            <a class="btn btn-sm btn-outline-secondary" href="{{ route('technical.manager.detail', ['member' => $member->id, 'week' => $prevWeek]) }}">
                <i class="bi bi-chevron-left"></i> Tuần trước
            </a>
            <a class="btn btn-sm btn-outline-secondary" href="{{ route('technical.manager.detail', ['member' => $member->id, 'week' => $nextWeek]) }}">
                Tuần sau <i class="bi bi-chevron-right"></i>
            </a>
        </div>

        <span class="tp-state {{ $plan === null ? 'tp-state--danger' : 'tp-state--info' }}">
            {{ $plan === null ? 'Chưa lập kế hoạch' : $plan->statusLabel() }}
        </span>

        <span class="tp-weekbar__spacer"></span>

        <form method="POST" action="{{ route('technical.manager.request-update', $member) }}"
              class="d-flex gap-2 align-items-end">
            @csrf
            <input type="hidden" name="week" value="{{ $weekStart->toDateString() }}">
            <div>
                <label class="form-label small mb-1" for="req-reason">Yêu cầu nhân viên cập nhật (bắt buộc lý do)</label>
                <input type="text" class="form-control form-control-sm" id="req-reason" name="reason"
                       minlength="5" maxlength="2000" required placeholder="Lý do yêu cầu cập nhật">
            </div>
            <button type="submit" class="btn btn-sm btn-outline-warning">
                <i class="bi bi-send"></i> Gửi yêu cầu
            </button>
        </form>
    </div>

    @include('technical.partials.week-checks', ['check' => $check])

    {{-- ---------- Giao thêm việc ---------- --}}
    <details class="tw-card mb-3" id="tp-assign" @if($errors->any() || request()->query('focus') === 'assign') open @endif>
        <summary class="tw-card__head" style="cursor: pointer;">
            <h2 class="tw-card__title"><i class="bi bi-person-plus"></i> Giao thêm việc cho {{ \App\Services\Technical\TechnicalWeekPlanService::displayLabel($member->name) }}</h2>
        </summary>

        <div class="tw-card__body">
            <form method="POST" action="{{ route('technical.manager.assign', $member) }}" class="tp-form">
                @csrf

                <div>
                    <label class="form-label small" for="a-date">Ngày thực hiện</label>
                    <select class="form-select form-select-sm" id="a-date" name="plan_date" required>
                        @foreach($days as $day)
                            <option value="{{ $day->toDateString() }}" @selected(old('plan_date', (string) request()->query('day', '')) === $day->toDateString())>{{ $planner->weekdayLabel($day) }} — {{ $day->format('d/m') }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="form-label small" for="a-part">Buổi</label>
                    <select class="form-select form-select-sm" id="a-part" name="day_part" required>
                        @foreach($dayPartOptions as $key => $label)
                            <option value="{{ $key }}" @selected($key === 'full_day')>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="form-label small" for="a-min">Thời gian dự kiến (phút)</label>
                    <input type="number" class="form-control form-control-sm" id="a-min" name="estimated_minutes"
                           min="15" step="15" value="{{ config('technical.week_plan.default_item_minutes') }}">
                </div>

                <div>
                    <label class="form-label small" for="a-pri">Ưu tiên</label>
                    <select class="form-select form-select-sm" id="a-pri" name="priority" required>
                        @foreach($priorityOptions as $key => $label)
                            <option value="{{ $key }}" @selected($key === 'normal')>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="tp-form__full">
                    <label class="form-label small" for="a-source">Gắn với công việc nguồn (tuỳ chọn)</label>
                    <select class="form-select form-select-sm" id="a-source" name="work_item_key">
                        <option value="{{ \App\Models\Technical\TechnicalPlanItem::SOURCE_MANAGER_ASSIGNED }}|">
                            Không gắn nguồn — việc trưởng phòng giao thêm
                        </option>
                        @foreach($assignableItems as $workItem)
                            <option value="{{ $workItem->sourceType }}|{{ $workItem->sourceId }}">
                                [{{ $workItem->sourceLabel() }}] {{ \App\Services\Technical\TechnicalWeekPlanService::displayLabel($workItem->title) }}@if($workItem->siteName) — {{ \App\Services\Technical\TechnicalWeekPlanService::displayLabel($workItem->siteName) }}@endif
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="tp-form__full">
                    <label class="form-label small" for="a-title">Nội dung công việc</label>
                    <input type="text" class="form-control form-control-sm" id="a-title" name="title" maxlength="255" required>
                </div>

                <div class="tp-form__full">
                    <label class="form-label small" for="a-obj">Mục tiêu cần đạt</label>
                    <textarea class="form-control form-control-sm" id="a-obj" name="objective" rows="2"></textarea>
                </div>

                <div class="tp-form__full">
                    <label class="form-label small" for="a-reason">Lý do giao việc (bắt buộc)</label>
                    <input type="text" class="form-control form-control-sm" id="a-reason" name="reason"
                           minlength="5" maxlength="2000" required>
                </div>

                <div class="tp-form__actions">
                    <button type="submit" class="btn btn-primary btn-sm">
                        <i class="bi bi-plus-lg"></i> Giao việc
                    </button>
                </div>
            </form>
        </div>
    </details>

    {{-- ---------- Chi tiết theo ngày ---------- --}}
    <span id="tp-adjust"></span>
    @foreach($days as $day)
        @php
            $key = $day->toDateString();
            $dayItems = $itemsByDay->get($key, collect());
            $mark = $dayMarks->get($key);
        @endphp

        <div class="tp-day {{ $day->isToday() ? 'tp-day--today' : '' }}" id="day-{{ $key }}">
            <div class="tp-day__head">
                <span class="tp-day__title">{{ $planner->weekdayLabel($day) }}</span>
                <span class="tp-day__date">{{ $day->format('d/m/Y') }}</span>
                <span class="tp-day__meta">
                    <span class="tp-state">{{ $dayItems->count() }} việc</span>
                    @if($mark)<span class="tp-state tp-state--info">{{ $mark->markLabel() }}</span>@endif
                </span>
            </div>

            <div class="tp-day__body">
                @if($dayItems->isEmpty())
                    <div class="tw-empty"><i class="bi bi-dash-circle"></i><p>Không có việc trong ngày này</p></div>
                @else
                    <table class="tp-table">
                        <thead>
                            <tr>
                                <th class="tp-col-narrow">Ngày</th>
                                <th>Nội dung</th>
                                <th class="tp-col-mid">Công trình</th>
                                <th>Mục tiêu</th>
                                <th class="tp-col-narrow">Thời gian</th>
                                <th class="tp-col-mid">Nguồn</th>
                                <th class="tp-col-narrow">Trạng thái</th>
                                <th class="tp-col-mid">Ghi chú</th>
                                <th class="tp-col-actions">Thao tác</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($dayItems as $item)
                                <tr>
                                    <td>{{ $item->plan_date->format('d/m') }}</td>
                                    <td>
                                        <span class="tp-table__title">{{ \App\Services\Technical\TechnicalWeekPlanService::displayLabel($item->title) }}</span>
                                        <span class="tp-table__sub">
                                            {{ $item->dayPartLabel() }} · {{ $item->priorityLabel() }}
                                            {{-- Phân biệt dòng ĐÃ GIAO với dòng quản lý mới LƯU NHÁP. --}}
                                            @if($item->is_manager_assigned)
                                                <span class="tp-state tp-state--warning">Đã giao</span>
                                            @elseif((int) $item->created_by !== (int) $item->user_id)
                                                <span class="tp-state tp-state--info">Nháp (quản lý lập)</span>
                                            @endif
                                        </span>
                                    </td>
                                    <td>{{ \App\Services\Technical\TechnicalWeekPlanService::displayLabel($item->site_name ?? '—') }}</td>
                                    <td>{{ \App\Services\Technical\TechnicalWeekPlanService::displayLabel($item->objective ?: '—') }}</td>
                                    <td>{{ $planner->minutesLabel($item->estimated_minutes) }}</td>
                                    <td>{{ $item->sourceLabel() }}</td>
                                    <td><span class="tp-state">{{ $item->statusLabel() }}</span></td>
                                    <td>{{ $item->note ?: '—' }}</td>
                                    <td class="tp-table__actions">
                                        <a class="btn btn-sm btn-outline-secondary" href="#adj-{{ $item->id }}"
                                           data-bs-toggle="collapse" role="button"
                                           aria-expanded="false" aria-controls="adj-{{ $item->id }}">
                                            <i class="bi bi-sliders"></i> Điều chỉnh
                                        </a>
                                    </td>
                                </tr>
                                <tr class="collapse {{ (int) request()->query('item') === (int) $item->id ? 'show' : '' }}" id="adj-{{ $item->id }}">
                                    <td colspan="9">
                                        <form method="POST" action="{{ route('technical.manager.items.adjust', $item) }}" class="tp-form">
                                            @csrf
                                            @method('PUT')

                                            <div>
                                                <label class="form-label small" for="adj-date-{{ $item->id }}">Ngày thực hiện</label>
                                                <select class="form-select form-select-sm" id="adj-date-{{ $item->id }}" name="plan_date">
                                                    @foreach($days as $d)
                                                        <option value="{{ $d->toDateString() }}" @selected($d->toDateString() === $item->plan_date->toDateString())>
                                                            {{ $planner->weekdayLabel($d) }} — {{ $d->format('d/m') }}
                                                        </option>
                                                    @endforeach
                                                </select>
                                            </div>

                                            <div>
                                                <label class="form-label small" for="adj-part-{{ $item->id }}">Buổi</label>
                                                <select class="form-select form-select-sm" id="adj-part-{{ $item->id }}" name="day_part">
                                                    @foreach($dayPartOptions as $k => $l)
                                                        <option value="{{ $k }}" @selected($item->day_part === $k)>{{ $l }}</option>
                                                    @endforeach
                                                </select>
                                            </div>

                                            <div>
                                                <label class="form-label small" for="adj-min-{{ $item->id }}">Thời gian dự kiến (phút)</label>
                                                <input type="number" class="form-control form-control-sm" id="adj-min-{{ $item->id }}"
                                                       name="estimated_minutes" min="15" step="15" value="{{ $item->estimated_minutes }}">
                                            </div>

                                            <div>
                                                <label class="form-label small" for="adj-pri-{{ $item->id }}">Ưu tiên</label>
                                                <select class="form-select form-select-sm" id="adj-pri-{{ $item->id }}" name="priority">
                                                    @foreach($priorityOptions as $k => $l)
                                                        <option value="{{ $k }}" @selected($item->priority === $k)>{{ $l }}</option>
                                                    @endforeach
                                                </select>
                                            </div>

                                            <div class="tp-form__full">
                                                <label class="form-label small" for="adj-title-{{ $item->id }}">Nội dung</label>
                                                <input type="text" class="form-control form-control-sm" id="adj-title-{{ $item->id }}"
                                                       name="title" maxlength="255" value="{{ $item->title }}" required>
                                            </div>

                                            <div class="tp-form__full">
                                                <label class="form-label small" for="adj-obj-{{ $item->id }}">Mục tiêu</label>
                                                <textarea class="form-control form-control-sm" id="adj-obj-{{ $item->id }}"
                                                          name="objective" rows="2">{{ $item->objective }}</textarea>
                                            </div>

                                            <div class="tp-form__full">
                                                <label class="form-label small" for="adj-reason-{{ $item->id }}">Lý do điều chỉnh (bắt buộc)</label>
                                                <input type="text" class="form-control form-control-sm" id="adj-reason-{{ $item->id }}"
                                                       name="reason" minlength="5" maxlength="2000" required>
                                            </div>

                                            <div class="tp-form__actions">
                                                <button type="submit" class="btn btn-sm btn-primary">
                                                    <i class="bi bi-check-lg"></i> Lưu điều chỉnh
                                                </button>
                                            </div>
                                        </form>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>

                    <div class="tp-cards-list">
                        @foreach($dayItems as $item)
                            <div class="tp-jobcard">
                                <p class="tp-jobcard__title">{{ \App\Services\Technical\TechnicalWeekPlanService::displayLabel($item->title) }}</p>
                                <div class="tp-jobcard__row"><span class="tp-jobcard__label">Buổi</span><span class="tp-jobcard__value">{{ $item->dayPartLabel() }}</span></div>
                                <div class="tp-jobcard__row"><span class="tp-jobcard__label">Công trình</span><span class="tp-jobcard__value">{{ \App\Services\Technical\TechnicalWeekPlanService::displayLabel($item->site_name ?? '—') }}</span></div>
                                <div class="tp-jobcard__row"><span class="tp-jobcard__label">Thời gian</span><span class="tp-jobcard__value">{{ $planner->minutesLabel($item->estimated_minutes) }}</span></div>
                                <div class="tp-jobcard__row"><span class="tp-jobcard__label">Nguồn</span><span class="tp-jobcard__value">{{ $item->sourceLabel() }}</span></div>
                                <div class="tp-jobcard__row"><span class="tp-jobcard__label">Trạng thái</span><span class="tp-jobcard__value">{{ $item->statusLabel() }}</span></div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>
    @endforeach

    {{-- ---------- Lịch sử điều chỉnh ---------- --}}
    <div class="tw-card">
        <div class="tw-card__head">
            <h2 class="tw-card__title">Lịch sử điều chỉnh</h2>
        </div>
        <div class="tw-scroll">
            <table class="tp-data">
                <thead>
                    <tr>
                        <th>Thời gian</th>
                        <th>Người thực hiện</th>
                        <th>Hành động</th>
                        <th>Trước</th>
                        <th>Sau</th>
                        <th>Lý do</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($histories as $history)
                        <tr>
                            <td>{{ $history->created_at?->format('d/m/Y H:i') }}</td>
                            <td>{{ \App\Services\Technical\TechnicalWeekPlanService::displayLabel($history->user_name ?? '—') }}</td>
                            <td>{{ $history->actionLabel() }}</td>
                            <td class="tp-data__muted">
                                @foreach($history->formattedBefore() as $field => $value)
                                    <div><strong>{{ $field }}:</strong> {{ $value }}</div>
                                @endforeach
                            </td>
                            <td class="tp-data__muted">
                                @foreach($history->formattedAfter() as $field => $value)
                                    <div><strong>{{ $field }}:</strong> {{ $value }}</div>
                                @endforeach
                            </td>
                            <td>{{ \App\Services\Technical\TechnicalWeekPlanService::displayLabel($history->reason ?: '—') }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="tp-data__muted">Chưa có điều chỉnh nào trong tuần này.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

</div>
@endsection
