{{--
    HAI DRAWER TẠO / GIAO KẾ HOẠCH — render SERVER-SIDE ngay trong trang ma trận.

    Không có bước chọn nhân viên trung gian: bấm nút ở header là thấy ngay form
    thật. Cả hai form POST về ĐÚNG MỘT endpoint ghi `technical.manager.plan.store`
    (lõi `persistPlanRows()` trong TechnicalPlanBoardController), nên không tồn
    tại nghiệp vụ ghi thứ hai và validation không thể lệch nhau.

    Trạng thái mở:
      - bấm nút                       -> data-bs-toggle="offcanvas" (Bootstrap)
      - deep-link ?open=create|assign -> class `show` + data-tp-open="1" (server)
      - lỗi validation / cần xác nhận -> mở lại đúng drawer vừa submit
    Nhờ render server-side, deep-link và fallback không-JS đều hoạt động.
--}}
@php
    $tpWarnings = $errors->get('confirm_warnings');
    $tpOldRows = old('items');
    $tpCreateRows = ($openDrawer === 'create' && is_array($tpOldRows) && $tpOldRows !== [])
        ? array_values($tpOldRows)
        : [[]];
    $tpAssignRow = ($openDrawer === 'assign' && is_array($tpOldRows) && isset($tpOldRows[0]))
        ? (array) $tpOldRows[0]
        : [];
    $tpDefaultSource = \App\Models\Technical\TechnicalPlanItem::SOURCE_MANAGER_ASSIGNED;
@endphp

{{-- ==================== DRAWER 1: TẠO KẾ HOẠCH (nhiều dòng) ==================== --}}
<div class="offcanvas offcanvas-end tp-plandrawer {{ $openDrawer === 'create' ? 'show' : '' }}"
     tabindex="-1" id="tpCreateDrawer" aria-labelledby="tpCreateDrawerLabel"
     @if($openDrawer === 'create') data-tp-open="1" @endif>

    <div class="offcanvas-header">
        <h2 class="offcanvas-title h5" id="tpCreateDrawerLabel">
            <i class="bi bi-plus-lg"></i> Tạo kế hoạch tuần
        </h2>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Đóng"></button>
    </div>

    <form method="POST" action="{{ route('technical.manager.plan.store') }}" class="tp-plandrawer__form" data-tp-plan-form="create">
        @csrf
        <input type="hidden" name="tp_form" value="create">
        <input type="hidden" name="week" value="{{ $weekStart->toDateString() }}">
        <input type="hidden" name="mode" value="assign" data-tp-mode>
        @foreach(['user_id' => $filters['user_id'] ?? '', 'site_id' => $filters['site_id'] ?? '', 'status' => $filters['status'] ?? ''] as $vk => $vv)
            <input type="hidden" name="view_{{ $vk }}" value="{{ $vv }}">
        @endforeach

        <div class="offcanvas-body tp-plandrawer__body">

            @if($openDrawer === 'create' && $tpWarnings)
                <div class="alert alert-warning" data-tp-warnings>
                    <strong><i class="bi bi-exclamation-triangle-fill"></i> Kế hoạch này có cảnh báo:</strong>
                    <ul class="mb-2 mt-1 ps-3">
                        @foreach($tpWarnings as $warning)<li>{{ $warning }}</li>@endforeach
                    </ul>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" value="1" name="confirm_warnings" id="tp-create-confirm" required>
                        <label class="form-check-label" for="tp-create-confirm">
                            Tôi xác nhận vẫn giao dù có cảnh báo
                        </label>
                    </div>
                </div>
            @endif

            <div class="tp-plandrawer__grid">
                <div>
                    <label class="form-label small" for="tp-create-user">Nhân viên nhận kế hoạch <span class="tp-req">*</span></label>
                    <select class="form-select form-select-sm {{ $openDrawer === 'create' && $errors->has('user_id') ? 'is-invalid' : '' }}"
                            id="tp-create-user" name="user_id" data-tp-member required>
                        @forelse($members as $member)
                            <option value="{{ $member->id }}"
                                @selected($openDrawer === 'create' && (int) $selectedMember === (int) $member->id || (int) $assignableFor === (int) $member->id)>
                                {{ \App\Services\Technical\TechnicalWeekPlanService::displayLabel($member->name) }}
                            </option>
                        @empty
                            <option value="">Chưa có nhân sự kỹ thuật</option>
                        @endforelse
                    </select>
                </div>

                <div>
                    <label class="form-label small" for="tp-create-week">Tuần</label>
                    <input type="text" class="form-control form-control-sm" id="tp-create-week" readonly
                           value="{{ $weekStart->format('d/m/Y') }} – {{ $weekEnd->format('d/m/Y') }}">
                </div>
            </div>

            <div class="tp-plandrawer__rows" data-tp-rows>
                @foreach($tpCreateRows as $rowIndex => $rowValues)
                    @include('technical.manager.partials.plan-row', [
                        'i' => $rowIndex,
                        'row' => (array) $rowValues,
                        'isTpl' => false,
                        'defaultSourceType' => $tpDefaultSource,
                    ])
                @endforeach
            </div>

            <div class="tp-plandrawer__rowtools">
                <button type="button" class="btn btn-sm btn-outline-primary" data-tp-add-row>
                    <i class="bi bi-plus-circle"></i> Thêm công việc
                </button>
                <span class="form-text">Có thể lập công việc cho nhiều ngày trong tuần trong cùng một lần lưu.</span>
            </div>

            <div class="tp-plandrawer__reason">
                <label class="form-label small" for="tp-create-reason">Lý do tạo/giao kế hoạch <span class="tp-req">*</span></label>
                <input type="text" class="form-control form-control-sm {{ $openDrawer === 'create' && $errors->has('reason') ? 'is-invalid' : '' }}"
                       id="tp-create-reason" name="reason" minlength="5" maxlength="2000" required
                       value="{{ $openDrawer === 'create' ? old('reason') : '' }}"
                       placeholder="Ví dụ: phân công thi công tuần theo tiến độ công trình">
                @if($openDrawer === 'create' && $errors->has('reason'))
                    <div class="invalid-feedback d-block">{{ $errors->first('reason') }}</div>
                @endif
            </div>
        </div>

        <div class="tp-plandrawer__footer">
            <button type="submit" class="btn btn-outline-secondary btn-sm" data-tp-submit-mode="draft">
                <i class="bi bi-save"></i> Lưu nháp
            </button>
            <button type="submit" class="btn btn-primary btn-sm" data-tp-submit-mode="assign">
                <i class="bi bi-send-check"></i> Lưu và giao kế hoạch
            </button>
            <button type="button" class="btn btn-link btn-sm" data-bs-dismiss="offcanvas">Hủy</button>
        </div>
    </form>
</div>

{{-- ==================== DRAWER 2: GIAO VIỆC NHANH (một dòng) ==================== --}}
<div class="offcanvas offcanvas-end tp-plandrawer {{ $openDrawer === 'assign' ? 'show' : '' }}"
     tabindex="-1" id="tpAssignDrawer" aria-labelledby="tpAssignDrawerLabel"
     @if($openDrawer === 'assign') data-tp-open="1" @endif>

    <div class="offcanvas-header">
        <h2 class="offcanvas-title h5" id="tpAssignDrawerLabel">
            <i class="bi bi-person-plus"></i> Giao việc cho nhân viên
        </h2>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Đóng"></button>
    </div>

    <form method="POST" action="{{ route('technical.manager.plan.store') }}" class="tp-plandrawer__form" data-tp-plan-form="assign">
        @csrf
        <input type="hidden" name="tp_form" value="assign">
        <input type="hidden" name="week" value="{{ $weekStart->toDateString() }}">
        <input type="hidden" name="mode" value="assign">
        @foreach(['user_id' => $filters['user_id'] ?? '', 'site_id' => $filters['site_id'] ?? '', 'status' => $filters['status'] ?? ''] as $vk => $vv)
            <input type="hidden" name="view_{{ $vk }}" value="{{ $vv }}">
        @endforeach

        <div class="offcanvas-body tp-plandrawer__body">

            @if($openDrawer === 'assign' && $tpWarnings)
                <div class="alert alert-warning" data-tp-warnings>
                    <strong><i class="bi bi-exclamation-triangle-fill"></i> Công việc này có cảnh báo:</strong>
                    <ul class="mb-2 mt-1 ps-3">
                        @foreach($tpWarnings as $warning)<li>{{ $warning }}</li>@endforeach
                    </ul>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" value="1" name="confirm_warnings" id="tp-assign-confirm" required>
                        <label class="form-check-label" for="tp-assign-confirm">
                            Tôi xác nhận vẫn giao dù có cảnh báo
                        </label>
                    </div>
                </div>
            @endif

            <div class="tp-plandrawer__grid">
                <div>
                    <label class="form-label small" for="tp-assign-user">Nhân viên <span class="tp-req">*</span></label>
                    <select class="form-select form-select-sm" id="tp-assign-user" name="user_id" data-tp-member required>
                        @forelse($members as $member)
                            <option value="{{ $member->id }}"
                                @selected((int) $selectedMember === (int) $member->id || ($selectedMember === 0 && (int) $assignableFor === (int) $member->id))>
                                {{ \App\Services\Technical\TechnicalWeekPlanService::displayLabel($member->name) }}
                            </option>
                        @empty
                            <option value="">Chưa có nhân sự kỹ thuật</option>
                        @endforelse
                    </select>
                </div>

                <div>
                    <label class="form-label small" for="tp-assign-week">Tuần</label>
                    <input type="text" class="form-control form-control-sm" id="tp-assign-week" readonly
                           value="{{ $weekStart->format('d/m/Y') }} – {{ $weekEnd->format('d/m/Y') }}">
                </div>
            </div>

            <div class="tp-plandrawer__rows" data-tp-rows>
                @include('technical.manager.partials.plan-row', [
                    'i' => 0,
                    'row' => $tpAssignRow,
                    'isTpl' => false,
                    'defaultSourceType' => $tpDefaultSource,
                ])
            </div>

            <div class="tp-plandrawer__reason">
                <label class="form-label small" for="tp-assign-reason">Lý do giao việc <span class="tp-req">*</span></label>
                <input type="text" class="form-control form-control-sm {{ $openDrawer === 'assign' && $errors->has('reason') ? 'is-invalid' : '' }}"
                       id="tp-assign-reason" name="reason" minlength="5" maxlength="2000" required
                       value="{{ $openDrawer === 'assign' ? old('reason') : '' }}"
                       placeholder="Ví dụ: bổ sung nhân lực cho công trình đang gấp">
                @if($openDrawer === 'assign' && $errors->has('reason'))
                    <div class="invalid-feedback d-block">{{ $errors->first('reason') }}</div>
                @endif
            </div>
        </div>

        <div class="tp-plandrawer__footer">
            <button type="submit" class="btn btn-primary btn-sm">
                <i class="bi bi-person-plus"></i> Giao việc
            </button>
            <button type="button" class="btn btn-link btn-sm" data-bs-dismiss="offcanvas">Hủy</button>
        </div>
    </form>
</div>

{{-- Khuôn dòng công việc cho nút "Thêm công việc" / "Sao chép dòng". --}}
<template id="tpPlanRowTemplate">
    @include('technical.manager.partials.plan-row', [
        'i' => '__INDEX__',
        'row' => [],
        'isTpl' => true,
        'defaultSourceType' => $tpDefaultSource,
    ])
</template>

@push('scripts')
<script>
(function () {
    'use strict';

    var WORK_ITEMS_URL = @json(route('technical.manager.work-items'));

    /* ---------- Mở sẵn drawer theo deep-link / lỗi validation ---------- */
    function syncOpenDrawers() {
        document.querySelectorAll('.offcanvas[data-tp-open="1"]').forEach(function (el) {
            try { bootstrap.Offcanvas.getOrCreateInstance(el).show(); } catch (e) { /* không-JS vẫn thấy form */ }
        });
    }

    /* ---------- Đánh số lại các dòng trong một form ---------- */
    function reindex(form) {
        var rows = form.querySelectorAll('[data-tp-row]');
        rows.forEach(function (row, index) {
            row.querySelectorAll('[name]').forEach(function (field) {
                field.name = field.name.replace(/items\[[^\]]*\]/, 'items[' + index + ']');
            });
            var no = row.querySelector('[data-tp-row-no]');
            if (no) { no.textContent = String(index + 1); }
            var remove = row.querySelector('[data-tp-remove-row]');
            if (remove) { remove.disabled = rows.length <= 1; }
        });
    }

    function buildRow() {
        var tpl = document.getElementById('tpPlanRowTemplate');
        var wrap = document.createElement('div');
        wrap.innerHTML = tpl.innerHTML.split('__INDEX__').join('0');
        return wrap.querySelector('[data-tp-row]');
    }

    function copyValues(from, to) {
        from.querySelectorAll('[data-tp-field]').forEach(function (field) {
            var twin = to.querySelector('[data-tp-field="' + field.dataset.tpField + '"]');
            if (twin) { twin.value = field.value; }
        });
    }

    /* ---------- Nạp "Đầu việc" theo nhân viên (endpoint CHỈ ĐỌC) ---------- */
    function refreshWorkItems(form) {
        var member = form.querySelector('[data-tp-member]');
        if (!member || !member.value) { return; }

        fetch(WORK_ITEMS_URL + '?user_id=' + encodeURIComponent(member.value), {
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin'
        })
            .then(function (res) { return res.ok ? res.json() : null; })
            .then(function (data) {
                if (!data || !Array.isArray(data.items)) { return; }
                form.querySelectorAll('[data-tp-field="source_id"]').forEach(function (select) {
                    var keep = select.value;
                    select.innerHTML = '<option value="">— Không gắn đầu việc có sẵn —</option>';
                    data.items.forEach(function (item) {
                        var opt = document.createElement('option');
                        opt.value = item.source_id;
                        opt.textContent = '[' + item.source_label + '] ' + item.title;
                        opt.dataset.sourceType = item.source_type;
                        opt.dataset.site = item.site_name || '';
                        opt.dataset.title = item.title;
                        select.appendChild(opt);
                    });
                    select.value = keep;
                    syncRowFromSource(select.closest('[data-tp-row]'));
                });
            })
            .catch(function () { /* giữ nguyên danh sách đang có */ });
    }

    /* ---------- Chọn đầu việc -> điền Công trình + Nguồn + Nội dung ---------- */
    function syncRowFromSource(row) {
        if (!row) { return; }
        var select = row.querySelector('[data-tp-field="source_id"]');
        var site = row.querySelector('[data-tp-field="site_name"]');
        var type = row.querySelector('[data-tp-field="source_type"]');
        var title = row.querySelector('[data-tp-field="title"]');
        if (!select) { return; }

        var opt = select.options[select.selectedIndex];
        if (!opt || !opt.value) {
            if (site) { site.value = ''; }
            return;
        }
        if (site) { site.value = opt.dataset.site || ''; }
        if (type && opt.dataset.sourceType) { type.value = opt.dataset.sourceType; }
        if (title && !title.value) { title.value = opt.dataset.title || ''; }
    }

    document.addEventListener('DOMContentLoaded', function () {
        syncOpenDrawers();

        document.querySelectorAll('[data-tp-plan-form]').forEach(function (form) {
            reindex(form);
            form.querySelectorAll('[data-tp-row]').forEach(syncRowFromSource);

            var member = form.querySelector('[data-tp-member]');
            if (member) {
                member.addEventListener('change', function () { refreshWorkItems(form); });
            }
        });
    });

    document.addEventListener('change', function (event) {
        var field = event.target.closest('[data-tp-field="source_id"]');
        if (field) { syncRowFromSource(field.closest('[data-tp-row]')); }
    });

    document.addEventListener('click', function (event) {
        /* Thêm / sao chép / xoá dòng */
        var add = event.target.closest('[data-tp-add-row]');
        if (add) {
            var addForm = add.closest('[data-tp-plan-form]');
            addForm.querySelector('[data-tp-rows]').appendChild(buildRow());
            reindex(addForm);
            return;
        }

        var copy = event.target.closest('[data-tp-copy-row]');
        if (copy) {
            var src = copy.closest('[data-tp-row]');
            var clone = buildRow();
            src.parentNode.insertBefore(clone, src.nextSibling);
            copyValues(src, clone);
            reindex(copy.closest('[data-tp-plan-form]'));
            return;
        }

        var remove = event.target.closest('[data-tp-remove-row]');
        if (remove) {
            var rmForm = remove.closest('[data-tp-plan-form]');
            if (rmForm.querySelectorAll('[data-tp-row]').length <= 1) { return; }
            remove.closest('[data-tp-row]').remove();
            reindex(rmForm);
            return;
        }

        /* Lưu nháp / Lưu và giao: cùng một form, khác `mode` */
        var submit = event.target.closest('[data-tp-submit-mode]');
        if (submit) {
            var modeInput = submit.closest('form').querySelector('[data-tp-mode]');
            if (modeInput) { modeInput.value = submit.dataset.tpSubmitMode; }
            return;
        }

        /* Ô NGÀY / menu ba chấm -> mở drawer đúng loại, chọn sẵn nhân viên + ngày */
        var cell = event.target.closest('[data-tp-assign-cell]');
        if (cell) {
            event.preventDefault();
            var drawer = document.getElementById(
                cell.dataset.tpOpenCreate === '1' ? 'tpCreateDrawer' : 'tpAssignDrawer'
            );
            var aForm = drawer.querySelector('[data-tp-plan-form]');
            var user = aForm.querySelector('[data-tp-member]');
            var date = aForm.querySelector('[data-tp-field="plan_date"]');
            if (user && cell.dataset.tpUser) { user.value = cell.dataset.tpUser; refreshWorkItems(aForm); }
            if (date && cell.dataset.tpDate) { date.value = cell.dataset.tpDate; }
            try { bootstrap.Offcanvas.getOrCreateInstance(drawer).show(); } catch (e) { window.location = cell.href; }
        }
    });
})();
</script>
@endpush
