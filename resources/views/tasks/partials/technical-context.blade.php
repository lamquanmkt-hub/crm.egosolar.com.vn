@if($isTechnicalTaskContext ?? false)
    @php
        $selectedProjectId = (string) old('project_id', $task->project_id ?? request('project_id'));
        $selectedApproverId = (string) old('approver_id', $task->approver_id ?? '');
        $defaultLinkType = old(
            'project_link_type',
            $selectedProjectId !== '' ? 'existing' : 'none'
        );
    @endphp

    <div class="technical-task-context mb-3" id="technicalTaskContext">
        <div class="technical-task-context__head">
            <div>
                <span>NGHIỆP VỤ PHÒNG KỸ THUẬT</span>
                <strong>Liên kết công việc</strong>
                <small>Có thể giao việc độc lập hoặc liên kết với một hồ sơ công trình.</small>
            </div>
            <i class="bi bi-buildings"></i>
        </div>

        <input type="hidden" name="task_type" value="technical">

        <div class="task-project-mode" role="radiogroup" aria-label="Cách liên kết công trình">
            <label class="task-project-mode__item">
                <input type="radio" name="project_link_type" value="none" @checked($defaultLinkType === 'none')>
                <span class="task-project-mode__icon"><i class="bi bi-list-check"></i></span>
                <span>
                    <strong>Không gắn công trình</strong>
                    <small>Giao việc độc lập trong phòng Kỹ thuật</small>
                </span>
            </label>

            <label class="task-project-mode__item">
                <input type="radio" name="project_link_type" value="existing" @checked($defaultLinkType === 'existing')>
                <span class="task-project-mode__icon"><i class="bi bi-buildings"></i></span>
                <span>
                    <strong>Chọn công trình có sẵn</strong>
                    <small>Liên kết với hồ sơ đang quản lý</small>
                </span>
            </label>

            <label class="task-project-mode__item">
                <input type="radio" name="project_link_type" value="new" @checked($defaultLinkType === 'new')>
                <span class="task-project-mode__icon"><i class="bi bi-plus-square"></i></span>
                <span>
                    <strong>Tạo công trình mới</strong>
                    <small>Tạo nhanh mà không rời phiếu giao việc</small>
                </span>
            </label>
        </div>

        <div class="row g-3 mt-1" data-project-existing>
            <div class="col-lg-6">
                <label class="form-label">Công trình</label>
                <div class="input-group">
                    <select name="project_id" id="technicalProjectSelect" class="form-select">
                        <option value="">Tìm và chọn công trình</option>
                        @foreach($technicalProjects ?? [] as $projectItem)
                            <option value="{{ $projectItem->id }}"
                                    data-address="{{ $projectItem->address }}"
                                    @selected($selectedProjectId === (string) $projectItem->id)>
                                {{ $projectItem->code }} · {{ $projectItem->name }}
                            </option>
                        @endforeach
                    </select>
                    <button class="btn btn-outline-success" type="button" data-open-quick-project>
                        <i class="bi bi-plus-lg"></i> Tạo mới
                    </button>
                </div>
            </div>

            <div class="col-lg-6">
                <label class="form-label" data-work-item-label>Hạng mục thực hiện</label>
                <input type="text" name="work_item" class="form-control"
                       value="{{ old('work_item', $task->work_item ?? '') }}"
                       placeholder="Ví dụ: Khảo sát mái, lập bản vẽ, kiểm tra thiết bị...">
            </div>

            <div class="col-lg-8">
                <label class="form-label" data-work-location-label>Địa điểm thực hiện</label>
                <input type="text" name="work_location" id="technicalWorkLocation" class="form-control"
                       value="{{ old('work_location', $task->work_location ?? '') }}"
                       placeholder="Địa chỉ công trình hoặc khu vực làm việc">
            </div>

            <div class="col-lg-4">
                <label class="form-label">Người duyệt kết quả <span class="text-danger">*</span></label>
                <select name="approver_id" class="form-select" required>
                    <option value="">Chọn người duyệt</option>
                    @foreach($technicalApprovers ?? [] as $approverItem)
                        <option value="{{ $approverItem->id }}" @selected($selectedApproverId === (string) $approverItem->id)>
                            {{ $approverItem->name }}
                        </option>
                    @endforeach
                </select>
            </div>
        </div>

        <div class="technical-task-context__note" data-project-note></div>
    </div>

    <div class="task-quick-project" id="taskQuickProject" aria-hidden="true">
        <button type="button" class="task-quick-project__backdrop" data-close-quick-project aria-label="Đóng"></button>
        <aside class="task-quick-project__drawer" role="dialog" aria-modal="true" aria-labelledby="quickProjectTitle">
            <div class="task-quick-project__header">
                <div>
                    <span>TẠO NHANH</span>
                    <h3 id="quickProjectTitle">Công trình Kỹ thuật mới</h3>
                    <p>Tạo hồ sơ tối thiểu, sau đó tiếp tục giao việc ngay.</p>
                </div>
                <button type="button" class="btn-close" data-close-quick-project aria-label="Đóng"></button>
            </div>

            <div class="task-quick-project__form">
                <input type="hidden" name="_token" value="{{ csrf_token() }}" form="taskQuickProjectForm">
                <div class="task-quick-project__body">
                    <div class="alert alert-danger d-none" data-quick-project-errors></div>

                    <div class="mb-3">
                        <label class="form-label">Tên công trình / công việc <span class="text-danger">*</span></label>
                        <input form="taskQuickProjectForm" type="text" name="name" class="form-control" maxlength="255"
                               placeholder="Ví dụ: Khảo sát hệ thống kho EGO – Bình Dương" data-quick-project-required="1">
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Địa điểm thực hiện <span class="text-danger">*</span></label>
                        <input form="taskQuickProjectForm" type="text" name="address" class="form-control" maxlength="700" data-quick-project-required="1">
                    </div>

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Đơn vị / người yêu cầu</label>
                            <input form="taskQuickProjectForm" type="text" name="contact_name" class="form-control" maxlength="255">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Số điện thoại</label>
                            <input form="taskQuickProjectForm" type="text" name="contact_phone" class="form-control" maxlength="60">
                        </div>
                    </div>

                    <div class="mt-3">
                        <label class="form-label">Nội dung yêu cầu <span class="text-danger">*</span></label>
                        <textarea form="taskQuickProjectForm" name="customer_need" class="form-control" rows="5" maxlength="5000"
                                  placeholder="Mô tả lý do tạo công trình và đầu việc cần tiếp nhận..." data-quick-project-required="1"></textarea>
                    </div>

                    <div class="row g-3 mt-0">
                        <div class="col-md-6">
                            <label class="form-label">Mức ưu tiên</label>
                            <select form="taskQuickProjectForm" name="priority" class="form-select">
                                <option value="normal">Bình thường</option>
                                <option value="high">Cao</option>
                                <option value="urgent">Khẩn cấp</option>
                                <option value="low">Thấp</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Mục tiêu hoàn thành</label>
                            <input form="taskQuickProjectForm" type="date" name="target_completion_at" class="form-control">
                        </div>
                    </div>
                </div>

                <div class="task-quick-project__footer">
                    <button type="button" class="btn btn-outline-secondary" data-close-quick-project>Hủy</button>
                    <button form="taskQuickProjectForm" type="submit" class="btn btn-success" data-quick-project-submit>
                        <i class="bi bi-plus-circle"></i> Tạo và chọn công trình
                    </button>
                </div>
            </div>
        </aside>
    </div>

    @once
        <style>
            .technical-task-context{border:1px solid #bfe5df;border-radius:18px;padding:15px;background:linear-gradient(135deg,#f3fffc,#f1f9ff)}
            .technical-task-context__head{display:flex;align-items:center;justify-content:space-between;gap:12px;margin-bottom:13px}
            .technical-task-context__head span{display:block;color:#087f72;font-size:10px;font-weight:950;letter-spacing:.09em}
            .technical-task-context__head strong{display:block;margin-top:2px;color:#123047;font-size:14px}
            .technical-task-context__head small{display:block;margin-top:3px;color:#698094;font-size:11px}
            .technical-task-context__head>i{width:38px;height:38px;display:grid;place-items:center;border-radius:12px;background:#d9f6ef;color:#087f72;font-size:18px}
            .task-project-mode{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:9px}
            .task-project-mode__item{position:relative;display:flex;align-items:center;gap:10px;min-height:72px;padding:11px;border:1px solid #dbe6ef;border-radius:14px;background:#fff;cursor:pointer;transition:.18s ease}
            .task-project-mode__item:hover{border-color:#72cfc1;transform:translateY(-1px);box-shadow:0 8px 18px rgba(15,118,110,.08)}
            .task-project-mode__item:has(input:checked){border-color:#0f9c88;background:#edfffa;box-shadow:0 0 0 3px rgba(15,156,136,.08)}
            .task-project-mode__item input{position:absolute;opacity:0;pointer-events:none}
            .task-project-mode__icon{width:34px;height:34px;display:grid;place-items:center;border-radius:11px;background:#edf6fb;color:#0d7490;flex:0 0 auto}
            .task-project-mode__item strong{display:block;color:#153149;font-size:12px}
            .task-project-mode__item small{display:block;margin-top:2px;color:#768a9b;font-size:10px;line-height:1.35}
            .technical-task-context__note{margin-top:11px;padding:9px 11px;border-radius:11px;background:rgba(8,127,114,.07);color:#4a6475;font-size:11px;line-height:1.5}
            .task-quick-project{position:fixed;inset:0;z-index:1085;display:none}
            .task-quick-project.is-open{display:block}
            .task-quick-project__backdrop{position:absolute;inset:0;border:0;background:rgba(15,23,42,.42);backdrop-filter:blur(2px)}
            .task-quick-project__drawer{position:absolute;top:0;right:0;width:min(520px,100%);height:100%;display:flex;flex-direction:column;background:#fff;box-shadow:-18px 0 48px rgba(15,23,42,.18);animation:taskDrawerIn .18s ease-out}
            .task-quick-project__header{display:flex;justify-content:space-between;gap:16px;padding:20px;border-bottom:1px solid #e5eaf1}
            .task-quick-project__header span{color:#0f8b78;font-size:10px;font-weight:900;letter-spacing:.1em}
            .task-quick-project__header h3{margin:3px 0 0;color:#132d45;font-size:20px;font-weight:900}
            .task-quick-project__header p{margin:4px 0 0;color:#718397;font-size:12px}
            .task-quick-project__body{padding:20px;overflow:auto}
            .task-quick-project__footer{margin-top:auto;display:flex;justify-content:flex-end;gap:9px;padding:16px 20px;border-top:1px solid #e5eaf1;background:#fff}
            @keyframes taskDrawerIn{from{transform:translateX(22px);opacity:.55}to{transform:none;opacity:1}}
            @media(max-width:900px){.task-project-mode{grid-template-columns:1fr}.task-project-mode__item{min-height:60px}}
            @media(prefers-reduced-motion:reduce){.task-project-mode__item,.task-quick-project__drawer{transition:none;animation:none}}
        </style>

        <script>
            document.addEventListener('DOMContentLoaded', function () {
                const context = document.getElementById('technicalTaskContext');
                if (!context) return;

                const project = document.getElementById('technicalProjectSelect');
                const location = document.getElementById('technicalWorkLocation');
                const modeInputs = Array.from(context.querySelectorAll('input[name="project_link_type"]'));
                const existingArea = context.querySelector('[data-project-existing]');
                const note = context.querySelector('[data-project-note]');
                const workItemLabel = context.querySelector('[data-work-item-label]');
                const workLocationLabel = context.querySelector('[data-work-location-label]');
                const quickPanel = document.getElementById('taskQuickProject');
                const quickForm = document.getElementById('taskQuickProjectForm');
                const quickErrors = quickPanel ? quickPanel.querySelector('[data-quick-project-errors]') : null;
                const quickSubmit = quickPanel ? quickPanel.querySelector('[data-quick-project-submit]') : null;

                function currentMode() {
                    return (modeInputs.find(input => input.checked) || {}).value || 'none';
                }

                function updateMode() {
                    const mode = currentMode();
                    const needsProject = mode === 'existing' || mode === 'new';
                    project.required = needsProject;

                    if (mode === 'none') {
                        existingArea.querySelector('.col-lg-6:first-child').style.display = 'none';
                        if (workItemLabel) workItemLabel.textContent = 'Nội dung / nhóm công việc';
                        if (workLocationLabel) workLocationLabel.textContent = 'Địa điểm thực hiện (không bắt buộc)';
                        note.textContent = 'Công việc độc lập vẫn được giao bình thường. Tiêu đề, người nhận, người duyệt và hạn hoàn thành là các thông tin chính.';
                    } else {
                        existingArea.querySelector('.col-lg-6:first-child').style.display = '';
                        if (workItemLabel) workItemLabel.textContent = 'Hạng mục thực hiện';
                        if (workLocationLabel) workLocationLabel.textContent = 'Địa điểm thực hiện';
                        note.textContent = mode === 'new'
                            ? 'Bấm “Tạo mới” để tạo nhanh công trình; hồ sơ vừa tạo sẽ được chọn tự động.'
                            : 'Chọn công trình để liên kết công việc với hồ sơ, tiến độ và lịch sử liên quan.';
                    }

                    if (mode === 'new') openQuickProject();
                }

                function openQuickProject() {
                    if (!quickPanel) return;
                    quickPanel.classList.add('is-open');
                    quickPanel.setAttribute('aria-hidden', 'false');
                    document.body.style.overflow = 'hidden';
                    setTimeout(() => quickPanel?.querySelector('[name="name"]')?.focus(), 30);
                }

                function closeQuickProject() {
                    if (!quickPanel) return;
                    quickPanel.classList.remove('is-open');
                    quickPanel.setAttribute('aria-hidden', 'true');
                    document.body.style.overflow = '';
                    if (currentMode() === 'new' && !project.value) {
                        const existing = modeInputs.find(input => input.value === 'existing');
                        if (existing) existing.checked = true;
                        updateMode();
                    }
                }

                modeInputs.forEach(input => input.addEventListener('change', updateMode));
                context.querySelector('[data-open-quick-project]')?.addEventListener('click', openQuickProject);
                quickPanel?.querySelectorAll('[data-close-quick-project]').forEach(button => button.addEventListener('click', closeQuickProject));

                project?.addEventListener('change', function () {
                    const option = project.options[project.selectedIndex];
                    if (option && option.dataset.address && location && !location.value.trim()) {
                        location.value = option.dataset.address;
                    }
                });

                quickForm?.addEventListener('submit', async function (event) {
                    event.preventDefault();
                    quickErrors?.classList.add('d-none');
                    if (quickSubmit) {
                        quickSubmit.disabled = true;
                        quickSubmit.dataset.original = quickSubmit.innerHTML;
                        quickSubmit.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Đang tạo...';
                    }

                    try {
                        const response = await fetch(@json(route('tasks.projects.quick')), {
                            method: 'POST',
                            body: new FormData(quickForm),
                            headers: {'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest'}
                        });
                        const payload = await response.json();
                        if (!response.ok || !payload.ok) {
                            const messages = payload.errors
                                ? Object.values(payload.errors).flat()
                                : [payload.message || 'Không tạo được công trình.'];
                            throw new Error(messages.join('\n'));
                        }

                        const item = payload.project;
                        const option = new Option(item.code + ' · ' + item.name, item.id, true, true);
                        option.dataset.address = item.address || '';
                        project.add(option);
                        const existing = modeInputs.find(input => input.value === 'existing');
                        if (existing) existing.checked = true;
                        if (location && !location.value.trim()) location.value = item.address || '';
                        closeQuickProject();
                        updateMode();
                        quickForm.reset();
                    } catch (error) {
                        if (quickErrors) {
                            quickErrors.textContent = error.message || 'Không tạo được công trình.';
                            quickErrors.classList.remove('d-none');
                        }
                    } finally {
                        if (quickSubmit) {
                            quickSubmit.disabled = false;
                            quickSubmit.innerHTML = quickSubmit.dataset.original || 'Tạo và chọn công trình';
                        }
                    }
                });

                updateMode();
            });
        </script>
    @endonce
@endif

{{-- EGO_TASK_HIDDEN_VALIDATION_FIX_START --}}
<script>
document.addEventListener('DOMContentLoaded', function () {
    const panel = document.getElementById('taskQuickProject');
    const quickForm = document.getElementById('taskQuickProjectForm');
    const taskForm =
        document.getElementById('taskCreateForm')
        || document.querySelector('form[action*="tasks"]');

    if (!panel) return;

    const controls = Array.from(
        panel.querySelectorAll('input[name], select[name], textarea[name]')
    ).filter(function (control) {
        return control.name !== '_token';
    });

    const requiredControls = controls.filter(function (control) {
        return control.hasAttribute('data-quick-project-required')
            || ['name', 'address', 'customer_need'].includes(control.name);
    });

    function panelIsOpen() {
        return panel.classList.contains('is-open')
            && panel.getAttribute('aria-hidden') !== 'true';
    }

    function setQuickProjectEnabled(enabled) {
        controls.forEach(function (control) {
            control.disabled = !enabled;
        });

        requiredControls.forEach(function (control) {
            control.required = enabled;
        });
    }

    /*
     * Mặc định drawer đang đóng:
     * disable toàn bộ field để không tham gia validation form Giao việc.
     */
    setQuickProjectEnabled(panelIsOpen());

    /*
     * Mở drawer: bật field và required.
     */
    document.addEventListener('click', function (event) {
        const openButton = event.target.closest('[data-open-quick-project]');

        if (openButton) {
            window.setTimeout(function () {
                setQuickProjectEnabled(true);
            }, 0);

            return;
        }

        const closeButton = event.target.closest('[data-close-quick-project]');

        if (closeButton) {
            window.setTimeout(function () {
                setQuickProjectEnabled(false);
            }, 0);
        }
    }, true);

    /*
     * Quan trọng:
     * Tắt field drawer trước lúc trình duyệt chạy native validation
     * cho nút Giao việc.
     */
    document.addEventListener('click', function (event) {
        const quickSubmit = event.target.closest('[data-quick-project-submit]');

        if (quickSubmit) {
            setQuickProjectEnabled(true);
            return;
        }

        const mainSubmit = event.target.closest(
            '#taskCreateSubmit, '
            + 'button[type="submit"][form="taskCreateForm"], '
            + '#taskCreateForm button[type="submit"]:not([data-quick-project-submit])'
        );

        if (mainSubmit) {
            setQuickProjectEnabled(false);
        }
    }, true);

    /*
     * Bảo vệ thêm khi form chính được submit bằng Enter hoặc requestSubmit().
     */
    if (taskForm) {
        taskForm.addEventListener('submit', function () {
            setQuickProjectEnabled(false);
        }, true);
    }

    /*
     * Khi tạo nhanh công trình, bật lại control trước validation và fetch.
     */
    if (quickForm) {
        quickForm.addEventListener('submit', function () {
            setQuickProjectEnabled(true);
        }, true);
    }

    /*
     * Đồng bộ khi code cũ mở/đóng drawer bằng class hoặc aria-hidden.
     */
    const observer = new MutationObserver(function () {
        setQuickProjectEnabled(panelIsOpen());
    });

    observer.observe(panel, {
        attributes: true,
        attributeFilter: ['class', 'aria-hidden']
    });
});
</script>
{{-- EGO_TASK_HIDDEN_VALIDATION_FIX_END --}}
