{{--
    Khối sửa / chuyển ngày / sao chép / xoá cho MỘT dòng kế hoạch của nhân viên.
    Dùng chung cho bảng desktop và thẻ mobile (khác nhau ở $idPrefix).
--}}
@php $p = ($idPrefix ?? '').$item->id; @endphp

<div class="row g-3">
    <div class="col-12 col-lg-7">
        <form method="POST" action="{{ route('technical.week-plan.items.update', $item) }}" class="tp-form">
            @csrf
            @method('PUT')
            <input type="hidden" name="week" value="{{ $weekParam }}">

            <div>
                <label class="form-label small" for="e-date-{{ $p }}">Ngày thực hiện</label>
                <select class="form-select form-select-sm" id="e-date-{{ $p }}" name="plan_date">
                    @foreach($days as $day)
                        <option value="{{ $day->toDateString() }}" @selected($day->toDateString() === $item->plan_date->toDateString())>
                            {{ $planner->weekdayLabel($day) }} — {{ $day->format('d/m') }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="form-label small" for="e-part-{{ $p }}">Buổi</label>
                <select class="form-select form-select-sm" id="e-part-{{ $p }}" name="day_part">
                    @foreach($dayPartOptions as $key => $label)
                        <option value="{{ $key }}" @selected($item->day_part === $key)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="form-label small" for="e-start-{{ $p }}">Giờ bắt đầu</label>
                <input type="time" class="form-control form-control-sm" id="e-start-{{ $p }}" name="start_time"
                       value="{{ $item->start_time ? substr((string) $item->start_time, 0, 5) : '' }}">
            </div>

            <div>
                <label class="form-label small" for="e-end-{{ $p }}">Giờ kết thúc</label>
                <input type="time" class="form-control form-control-sm" id="e-end-{{ $p }}" name="end_time"
                       value="{{ $item->end_time ? substr((string) $item->end_time, 0, 5) : '' }}">
            </div>

            <div class="tp-form__full">
                <label class="form-label small" for="e-title-{{ $p }}">Nội dung công việc</label>
                <input type="text" class="form-control form-control-sm" id="e-title-{{ $p }}" name="title"
                       maxlength="255" value="{{ $item->title }}" required>
            </div>

            <div class="tp-form__full">
                <label class="form-label small" for="e-obj-{{ $p }}">Mục tiêu cần đạt</label>
                <textarea class="form-control form-control-sm" id="e-obj-{{ $p }}" name="objective" rows="2">{{ $item->objective }}</textarea>
            </div>

            <div>
                <label class="form-label small" for="e-min-{{ $p }}">Thời gian dự kiến (phút)</label>
                <input type="number" class="form-control form-control-sm" id="e-min-{{ $p }}" name="estimated_minutes"
                       min="15" step="15" value="{{ $item->estimated_minutes }}">
            </div>

            <div>
                <label class="form-label small" for="e-pri-{{ $p }}">Ưu tiên</label>
                <select class="form-select form-select-sm" id="e-pri-{{ $p }}" name="priority">
                    @foreach($priorityOptions as $key => $label)
                        <option value="{{ $key }}" @selected($item->priority === $key)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="form-label small" for="e-status-{{ $p }}">Trạng thái</label>
                <select class="form-select form-select-sm" id="e-status-{{ $p }}" name="status">
                    @foreach($statusOptions as $key => $label)
                        <option value="{{ $key }}" @selected($item->status === $key)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>

            <div class="tp-form__full">
                <label class="form-label small" for="e-note-{{ $p }}">Ghi chú</label>
                <input type="text" class="form-control form-control-sm" id="e-note-{{ $p }}" name="note"
                       maxlength="2000" value="{{ $item->note }}">
            </div>

            <div class="tp-form__actions">
                <button type="submit" class="btn btn-sm btn-primary">
                    <i class="bi bi-check-lg"></i> Lưu thay đổi
                </button>
            </div>
        </form>
    </div>

    <div class="col-12 col-lg-5">
        <div class="d-flex flex-column gap-2">
            <form method="POST" action="{{ route('technical.week-plan.items.copy', $item) }}" class="d-flex gap-2 align-items-end">
                @csrf
                <div class="flex-grow-1">
                    <label class="form-label small" for="c-date-{{ $p }}">Sao chép sang ngày</label>
                    <select class="form-select form-select-sm" id="c-date-{{ $p }}" name="plan_date">
                        @foreach($days as $day)
                            <option value="{{ $day->toDateString() }}">{{ $planner->weekdayLabel($day) }} — {{ $day->format('d/m') }}</option>
                        @endforeach
                    </select>
                </div>
                <button type="submit" class="btn btn-sm btn-outline-secondary">
                    <i class="bi bi-files"></i> Sao chép
                </button>
            </form>

            <div class="tw-def small">
                <dt>Người tạo</dt>
                <dd>{{ $item->created_by_name ?? $item->user_name }}</dd>
                <dt>Nguồn</dt>
                <dd>{{ $item->sourceLabel() }}</dd>
                @if($item->is_manager_assigned)
                    <dt>Trưởng phòng giao</dt>
                    <dd>{{ optional($item->assigned_at)->format('d/m/Y H:i') }}</dd>
                @endif
            </div>

            @if($hasReport)
                <div class="alert alert-info py-2 px-3 mb-0 small">
                    <i class="bi bi-journal-check"></i>
                    Công việc này đã có báo cáo — không thể xoá khỏi kế hoạch.
                </div>
            @else
                <form method="POST" action="{{ route('technical.week-plan.items.destroy', $item) }}"
                      onsubmit="return confirm('Xoá công việc này khỏi kế hoạch?');">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn-sm btn-outline-danger">
                        <i class="bi bi-trash"></i> Xoá khỏi kế hoạch
                    </button>
                </form>
            @endif
        </div>
    </div>
</div>
