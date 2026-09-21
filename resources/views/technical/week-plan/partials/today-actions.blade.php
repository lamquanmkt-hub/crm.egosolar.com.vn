{{--
    Thao tác trên MỘT dòng kế hoạch trong ngày.
    Tất cả chỉ ghi vào `technical_plan_items` — không đụng workflow Công trình.
--}}
@php $p = ($idPrefix ?? '').$item->id; @endphp

<div class="row g-3">
    <div class="col-12 col-lg-6">
        <form method="POST" action="{{ route('technical.week-plan.items.status', $item) }}" class="tp-form">
            @csrf
            <div>
                <label class="form-label small" for="t-status-{{ $p }}">Trạng thái</label>
                <select class="form-select form-select-sm" id="t-status-{{ $p }}" name="status">
                    @foreach($statusOptions as $key => $label)
                        <option value="{{ $key }}" @selected($item->status === $key)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="form-label small" for="t-prog-{{ $p }}">Tiến độ (%)</label>
                <input type="number" class="form-control form-control-sm" id="t-prog-{{ $p }}"
                       name="progress_percent" min="0" max="100" value="{{ $item->progress_percent }}">
            </div>
            <div class="tp-form__full">
                <label class="form-label small" for="t-note-{{ $p }}">Ghi chú cập nhật (không bắt buộc)</label>
                <input type="text" class="form-control form-control-sm" id="t-note-{{ $p }}" name="reason" maxlength="2000">
            </div>
            <div class="tp-form__actions">
                <button type="submit" class="btn btn-sm btn-primary">
                    <i class="bi bi-arrow-repeat"></i> Cập nhật tiến độ
                </button>
                <button type="submit" class="btn btn-sm btn-outline-secondary"
                        name="status" value="{{ \App\Models\Technical\TechnicalPlanItem::STATUS_IN_PROGRESS }}">
                    <i class="bi bi-play-circle"></i> Bắt đầu công việc
                </button>
            </div>
        </form>
    </div>

    <div class="col-12 col-lg-6">
        <div class="d-flex flex-column gap-2">
            <div class="d-flex flex-wrap gap-2">
                <a class="btn btn-sm btn-primary"
                   href="{{ route('technical.daily-reports.create', ['plan_item_id' => $item->id]) }}">
                    <i class="bi bi-journal-plus"></i> Viết báo cáo
                </a>
                @if($report)
                    <a class="btn btn-sm btn-outline-secondary"
                       href="{{ route('technical.daily-reports.show', $report->id) }}">
                        <i class="bi bi-journal-text"></i> Xem báo cáo
                    </a>
                @endif
                <a class="btn btn-sm btn-outline-secondary"
                   href="{{ route('technical.daily-reports.create', ['plan_item_id' => $item->id, 'is_unplanned' => 1]) }}">
                    <i class="bi bi-lightning-charge"></i> Báo phát sinh
                </a>
            </div>

            <form method="POST" action="{{ route('technical.week-plan.items.defer', $item) }}" class="d-flex gap-2 align-items-end">
                @csrf
                <div class="flex-grow-1">
                    <label class="form-label small" for="t-defer-{{ $p }}">Lý do chuyển sang ngày sau</label>
                    <input type="text" class="form-control form-control-sm" id="t-defer-{{ $p }}"
                           name="reason" minlength="5" maxlength="2000" required
                           placeholder="Ví dụ: chờ vật tư về kho">
                </div>
                <button type="submit" class="btn btn-sm btn-outline-warning">
                    <i class="bi bi-arrow-right-circle"></i> Đề xuất chuyển
                </button>
            </form>
        </div>
    </div>
</div>
