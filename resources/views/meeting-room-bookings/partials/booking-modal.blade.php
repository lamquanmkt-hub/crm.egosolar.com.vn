<div
    class="modal fade mrb3-modal"
    id="{{ $modalId }}"
    tabindex="-1"
    aria-hidden="true"
>
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="mrb3-modal__header">
                <div>
                    <span class="mrb3-modal__eyebrow">
                        <i class="bi bi-calendar-plus"></i>
                        {{ $isEdit ? 'Chi tiết lịch họp' : 'Booking phòng họp' }}
                    </span>
                    <h2>{{ $title }}</h2>
                </div>

                <button
                    type="button"
                    class="mrb3-modal__close"
                    data-bs-dismiss="modal"
                    aria-label="Đóng"
                >
                    <i class="bi bi-x-lg"></i>
                </button>
            </div>

            <form
                id="{{ $formId }}"
                action="{{ $action }}"
                method="POST"
                data-booking-form
            >
                @csrf
                @if($method !== 'POST')
                    @method($method)
                @endif

                <div class="mrb3-modal__body">
                    @if($errors->any())
                        <div class="mrb3-form-errors">
                            <i class="bi bi-exclamation-triangle-fill"></i>
                            <div>
                                @foreach($errors->all() as $error)
                                    <p>{{ $error }}</p>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    <div class="mrb3-form-grid">
                        <label class="mrb3-field mrb3-field--span-5">
                            <span>Phòng họp <b>*</b></span>
                            <div class="mrb3-control">
                                <i class="bi bi-door-open"></i>
                                <select name="room_name" required>
                                    <option value="">Chọn phòng</option>
                                    @foreach($rooms as $roomOption)
                                        <option
                                            value="{{ $roomOption }}"
                                            @selected(!$isEdit && old('room_name') === $roomOption)
                                        >
                                            {{ $roomOption }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        </label>

                        <label class="mrb3-field mrb3-field--span-7">
                            <span>Nội dung cuộc họp <b>*</b></span>
                            <div class="mrb3-control">
                                <i class="bi bi-chat-square-text"></i>
                                <input
                                    type="text"
                                    name="title"
                                    maxlength="255"
                                    value="{{ !$isEdit ? old('title') : '' }}"
                                    placeholder="Ví dụ: Họp kế hoạch tuần"
                                    required
                                >
                            </div>
                        </label>

                        <label class="mrb3-field mrb3-field--span-6">
                            <span>Bắt đầu <b>*</b></span>
                            <div class="mrb3-control">
                                <i class="bi bi-clock"></i>
                                <input
                                    type="datetime-local"
                                    name="start_at"
                                    value="{{ !$isEdit ? old('start_at') : '' }}"
                                    required
                                >
                            </div>
                        </label>

                        <label class="mrb3-field mrb3-field--span-6">
                            <span>Kết thúc <b>*</b></span>
                            <div class="mrb3-control">
                                <i class="bi bi-clock-fill"></i>
                                <input
                                    type="datetime-local"
                                    name="end_at"
                                    value="{{ !$isEdit ? old('end_at') : '' }}"
                                    required
                                >
                            </div>
                        </label>

                        <label class="mrb3-field mrb3-field--span-6">
                            <span>Người đặt</span>
                            <div class="mrb3-control">
                                <i class="bi bi-person"></i>
                                <input
                                    type="text"
                                    name="organizer_name"
                                    list="mrbOrganizerList"
                                    value="{{ !$isEdit ? old('organizer_name', optional(auth()->user())->name) : '' }}"
                                    maxlength="160"
                                    placeholder="Tên người đặt"
                                >
                                <datalist id="mrbOrganizerList">
                                    @foreach($organizers as $organizerValue => $organizerLabel)
                                        <option value="{{ $organizerValue }}">{{ $organizerLabel }}</option>
                                    @endforeach
                                </datalist>
                            </div>
                        </label>

                        <label class="mrb3-field mrb3-field--span-4">
                            <span>Bộ phận</span>
                            <div class="mrb3-control">
                                <i class="bi bi-building"></i>
                                <input
                                    type="text"
                                    name="department"
                                    value="{{ !$isEdit ? old('department') : '' }}"
                                    maxlength="160"
                                    placeholder="Phòng ban"
                                >
                            </div>
                        </label>

                        <label class="mrb3-field mrb3-field--span-2">
                            <span>Số người</span>
                            <div class="mrb3-control">
                                <i class="bi bi-people"></i>
                                <input
                                    type="number"
                                    name="attendees"
                                    min="1"
                                    max="500"
                                    value="{{ !$isEdit ? old('attendees', 1) : 1 }}"
                                >
                            </div>
                        </label>

                        @if($isEdit)
                            <fieldset class="mrb3-field mrb3-field--span-12">
                                <legend>Trạng thái sử dụng</legend>
                                <div class="mrb3-usage-switch">
                                    <label>
                                        <input type="radio" name="usage_status" value="unused" checked>
                                        <span>
                                            <i class="bi bi-clock-history"></i>
                                            Chưa sử dụng
                                        </span>
                                    </label>
                                    <label>
                                        <input type="radio" name="usage_status" value="used">
                                        <span>
                                            <i class="bi bi-check2-circle"></i>
                                            Đã sử dụng
                                        </span>
                                    </label>
                                </div>
                            </fieldset>
                        @endif

                        <label class="mrb3-field mrb3-field--span-12">
                            <span>Ghi chú</span>
                            <div class="mrb3-control mrb3-control--textarea">
                                <i class="bi bi-journal-text"></i>
                                <textarea
                                    name="note"
                                    rows="3"
                                    maxlength="2000"
                                    placeholder="Thiết bị cần chuẩn bị, yêu cầu đặc biệt..."
                                >{{ !$isEdit ? old('note') : '' }}</textarea>
                            </div>
                        </label>
                    </div>
                </div>
            </form>

            <div class="mrb3-modal__footer">
                @if($isEdit)
                    <button
                        type="button"
                        class="mrb3-btn mrb3-btn--danger-ghost"
                        data-delete-booking
                    >
                        <i class="bi bi-trash3"></i>
                        Xóa
                    </button>
                @else
                    <span></span>
                @endif

                <div class="mrb3-modal__footer-actions">
                    <button
                        type="button"
                        class="mrb3-btn mrb3-btn--ghost"
                        data-bs-dismiss="modal"
                    >
                        Hủy
                    </button>

                    <button
                        type="submit"
                        class="mrb3-btn mrb3-btn--primary"
                        form="{{ $formId }}"
                        data-submit-booking
                    >
                        <span class="mrb3-submit-label">
                            <i class="bi bi-check2"></i>
                            {{ $submitLabel }}
                        </span>
                        <span class="mrb3-submit-loading">
                            <span class="spinner-border spinner-border-sm" aria-hidden="true"></span>
                            Đang kiểm tra lịch...
                        </span>
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>
