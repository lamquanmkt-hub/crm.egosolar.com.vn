{{--
    MỘT DÒNG CÔNG VIỆC trong form "Tạo kế hoạch" (nhiều dòng).

    Dùng chung cho (a) các dòng render sẵn phía server — kể cả khi quay lại form
    vì lỗi validation, và (b) khuôn `<template>` để nút "Thêm công việc" /
    "Sao chép dòng" nhân bản. Nhờ vậy dòng mới và dòng cũ KHÔNG THỂ lệch nhau.

    Tham số:
      $i         chỉ số dòng (số nguyên) hoặc chuỗi '__INDEX__' cho khuôn
      $row       mảng giá trị đang có của dòng (old input), có thể rỗng
      $isTpl     true nếu đang render khuôn <template> (không gắn lỗi/old)
--}}
@php
    $tpRowIndex = $i;
    $tpRow = (array) ($row ?? []);
    $tpIsTpl = (bool) ($isTpl ?? false);
    $tpName = fn (string $field): string => 'items['.$tpRowIndex.']['.$field.']';
    $tpErrKey = fn (string $field): string => 'items.'.$tpRowIndex.'.'.$field;
    $tpVal = function (string $field, $fallback = '') use ($tpRow) {
        $value = $tpRow[$field] ?? null;

        return ($value === null || $value === '') ? $fallback : $value;
    };
    $tpErr = fn (string $field): ?string => $tpIsTpl ? null : ($errors->first('items.'.$tpRowIndex.'.'.$field) ?: null);
@endphp

<article class="tp-rowcard" data-tp-row>
    <header class="tp-rowcard__head">
        <span class="tp-rowcard__badge">Công việc <span data-tp-row-no>{{ $tpIsTpl ? '' : ((int) $tpRowIndex) + 1 }}</span></span>
        <span class="tp-rowcard__tools">
            <button type="button" class="btn btn-sm btn-outline-secondary" data-tp-copy-row>
                <i class="bi bi-files"></i> Sao chép dòng
            </button>
            <button type="button" class="btn btn-sm btn-outline-danger" data-tp-remove-row>
                <i class="bi bi-trash"></i> Xóa dòng chưa lưu
            </button>
        </span>
    </header>

    <div class="tp-rowcard__grid">
        <div>
            <label class="form-label small">Ngày thực hiện <span class="tp-req">*</span></label>
            <select class="form-select form-select-sm {{ $tpErr('plan_date') ? 'is-invalid' : '' }}"
                    name="{{ $tpName('plan_date') }}" data-tp-field="plan_date" required>
                @foreach($days as $day)
                    <option value="{{ $day->toDateString() }}"
                        @selected((string) $tpVal('plan_date', $selectedDate ?: $days[0]->toDateString()) === $day->toDateString())>
                        {{ $planner->weekdayLabel($day) }} — {{ $day->format('d/m') }}
                    </option>
                @endforeach
            </select>
            @if($tpErr('plan_date'))<div class="invalid-feedback d-block">{{ $tpErr('plan_date') }}</div>@endif
        </div>

        <div>
            <label class="form-label small">Buổi <span class="tp-req">*</span></label>
            <select class="form-select form-select-sm {{ $tpErr('day_part') ? 'is-invalid' : '' }}"
                    name="{{ $tpName('day_part') }}" data-tp-field="day_part" required>
                @foreach($dayPartOptions as $key => $label)
                    <option value="{{ $key }}" @selected((string) $tpVal('day_part', 'full_day') === (string) $key)>{{ $label }}</option>
                @endforeach
            </select>
            @if($tpErr('day_part'))<div class="invalid-feedback d-block">{{ $tpErr('day_part') }}</div>@endif
        </div>

        <div>
            <label class="form-label small">Giờ bắt đầu</label>
            <input type="time" class="form-control form-control-sm {{ $tpErr('start_time') ? 'is-invalid' : '' }}"
                   name="{{ $tpName('start_time') }}" data-tp-field="start_time"
                   value="{{ $tpVal('start_time') }}">
            @if($tpErr('start_time'))<div class="invalid-feedback d-block">{{ $tpErr('start_time') }}</div>@endif
        </div>

        <div>
            <label class="form-label small">Giờ kết thúc</label>
            <input type="time" class="form-control form-control-sm {{ $tpErr('end_time') ? 'is-invalid' : '' }}"
                   name="{{ $tpName('end_time') }}" data-tp-field="end_time"
                   value="{{ $tpVal('end_time') }}">
            @if($tpErr('end_time'))<div class="invalid-feedback d-block">{{ $tpErr('end_time') }}</div>@endif
        </div>

        <div>
            <label class="form-label small">Thời gian dự kiến (phút)</label>
            <input type="number" class="form-control form-control-sm {{ $tpErr('estimated_minutes') ? 'is-invalid' : '' }}"
                   name="{{ $tpName('estimated_minutes') }}" data-tp-field="estimated_minutes"
                   min="15" step="15" value="{{ $tpVal('estimated_minutes', $defaultMinutes) }}">
            @if($tpErr('estimated_minutes'))<div class="invalid-feedback d-block">{{ $tpErr('estimated_minutes') }}</div>@endif
        </div>

        <div>
            <label class="form-label small">Mức độ ưu tiên <span class="tp-req">*</span></label>
            <select class="form-select form-select-sm {{ $tpErr('priority') ? 'is-invalid' : '' }}"
                    name="{{ $tpName('priority') }}" data-tp-field="priority" required>
                @foreach($priorityOptions as $key => $label)
                    <option value="{{ $key }}" @selected((string) $tpVal('priority', 'normal') === (string) $key)>{{ $label }}</option>
                @endforeach
            </select>
            @if($tpErr('priority'))<div class="invalid-feedback d-block">{{ $tpErr('priority') }}</div>@endif
        </div>

        <div>
            <label class="form-label small">Nguồn công việc <span class="tp-req">*</span></label>
            <select class="form-select form-select-sm {{ $tpErr('source_type') ? 'is-invalid' : '' }}"
                    name="{{ $tpName('source_type') }}" data-tp-field="source_type" required>
                @foreach($sourceOptions as $key => $label)
                    <option value="{{ $key }}" @selected((string) $tpVal('source_type', $defaultSourceType) === (string) $key)>{{ $label }}</option>
                @endforeach
            </select>
            @if($tpErr('source_type'))<div class="invalid-feedback d-block">{{ $tpErr('source_type') }}</div>@endif
        </div>

        <div>
            <label class="form-label small">Đầu việc</label>
            <select class="form-select form-select-sm {{ $tpErr('source_id') ? 'is-invalid' : '' }}"
                    name="{{ $tpName('source_id') }}" data-tp-field="source_id">
                <option value="">— Không gắn đầu việc có sẵn —</option>
                @foreach($assignableItems as $workItem)
                    <option value="{{ $workItem->sourceId }}"
                            data-source-type="{{ $workItem->sourceType }}"
                            data-site="{{ \App\Services\Technical\TechnicalWeekPlanService::displayLabel((string) ($workItem->siteName ?? '')) }}"
                            data-title="{{ \App\Services\Technical\TechnicalWeekPlanService::displayLabel($workItem->title) }}"
                        @selected((string) $tpVal('source_id') === (string) $workItem->sourceId && (string) $tpVal('source_type') === (string) $workItem->sourceType)>
                        [{{ $workItem->sourceLabel() }}] {{ \App\Services\Technical\TechnicalWeekPlanService::displayLabel($workItem->title) }}
                    </option>
                @endforeach
            </select>
            @if($tpErr('source_id'))<div class="invalid-feedback d-block">{{ $tpErr('source_id') }}</div>@endif
        </div>

        <div>
            <label class="form-label small">Công trình liên quan</label>
            <input type="text" class="form-control form-control-sm" data-tp-field="site_name"
                   value="" readonly placeholder="Theo đầu việc đã chọn">
        </div>

        <div class="tp-rowcard__full">
            <label class="form-label small">Nội dung công việc <span class="tp-req">*</span></label>
            <input type="text" class="form-control form-control-sm {{ $tpErr('title') ? 'is-invalid' : '' }}"
                   name="{{ $tpName('title') }}" data-tp-field="title" maxlength="255"
                   value="{{ $tpVal('title') }}" required>
            @if($tpErr('title'))<div class="invalid-feedback d-block">{{ $tpErr('title') }}</div>@endif
        </div>

        <div class="tp-rowcard__full">
            <label class="form-label small">Mục tiêu cần đạt</label>
            <textarea class="form-control form-control-sm {{ $tpErr('objective') ? 'is-invalid' : '' }}"
                      name="{{ $tpName('objective') }}" data-tp-field="objective" rows="2">{{ $tpVal('objective') }}</textarea>
            @if($tpErr('objective'))<div class="invalid-feedback d-block">{{ $tpErr('objective') }}</div>@endif
        </div>

        <div class="tp-rowcard__full">
            <label class="form-label small">Ghi chú</label>
            <textarea class="form-control form-control-sm {{ $tpErr('note') ? 'is-invalid' : '' }}"
                      name="{{ $tpName('note') }}" data-tp-field="note" rows="2">{{ $tpVal('note') }}</textarea>
            @if($tpErr('note'))<div class="invalid-feedback d-block">{{ $tpErr('note') }}</div>@endif
        </div>
    </div>
</article>
