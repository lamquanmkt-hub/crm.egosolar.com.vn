


@extends('layouts.app')

@section('content')
<style>
    .edit-task-page{
        min-height:100vh;
        background:#f4f7fb;
        padding-bottom:48px;
        font-size:13px;
        font-family:inherit;
    }

    .page-shell{padding:22px}

    .page-head{
        display:flex;
        justify-content:space-between;
        align-items:center;
        gap:12px;
        margin-bottom:16px;
    }

    .page-head h4{
        margin:0;
        font-size:22px;
        font-weight:950;
        color:#0f172a;
    }

    .page-head .desc{
        color:#64748b;
        font-size:12px;
        margin-top:2px;
    }

    .btn-pill{
        border-radius:999px;
        font-weight:800;
        font-size:13px;
        padding:8px 16px;
    }

    .hero{
        border-radius:24px;
        padding:22px;
        color:#fff;
        background:
            radial-gradient(650px 260px at 90% 0%, rgba(34,211,238,.23), transparent 60%),
            linear-gradient(135deg,#020617,#075985 55%,#0f766e);
        box-shadow:0 18px 44px rgba(15,23,42,.18);
        margin-bottom:16px;
    }

    .hero h3{
        font-size:24px;
        font-weight:950;
        margin:0;
        letter-spacing:-.03em;
    }

    .hero p{
        margin:5px 0 0;
        opacity:.82;
        font-size:13px;
    }

    .layout{
        display:grid;
        grid-template-columns:1fr 360px;
        gap:16px;
        align-items:start;
    }

    .soft-card{
        background:#fff;
        border:1px solid #e5eaf1;
        border-radius:22px;
        overflow:hidden;
        box-shadow:0 12px 32px rgba(15,23,42,.065);
    }

    .card-head{
        padding:14px 16px;
        border-bottom:1px solid #e5eaf1;
        background:#fff;
        font-size:15px;
        font-weight:950;
        color:#0f172a;
    }

    .form-body{padding:18px}

    .form-label{
        color:#334155;
        font-size:12px;
        font-weight:850;
        margin-bottom:5px;
    }

    .form-control,
    .form-select{
        border-radius:12px;
        border-color:#dbe3ee;
        font-size:13px;
    }

    .form-control:focus,
    .form-select:focus{
        border-color:#0ea5e9;
        box-shadow:0 0 0 .2rem rgba(14,165,233,.12);
    }

    .upload-zone{
        border:2px dashed #cbd5e1;
        border-radius:18px;
        padding:20px;
        text-align:center;
        background:#f8fafc;
        transition:.15s ease;
    }

    .upload-zone:hover{
        border-color:#38bdf8;
        background:#f0f9ff;
    }

    .upload-icon{
        width:54px;
        height:54px;
        display:inline-flex;
        align-items:center;
        justify-content:center;
        border-radius:18px;
        background:#e0f2fe;
        color:#0369a1;
        font-size:27px;
        margin-bottom:10px;
    }

    .info-line{
        padding:14px 15px;
        border-bottom:1px solid #e5eaf1;
    }

    .info-line:last-child{border-bottom:0}

    .info-label{
        color:#64748b;
        font-size:12px;
        font-weight:800;
    }

    .info-value{
        color:#0f172a;
        font-weight:950;
        margin-top:3px;
    }

    .soft-badge{
        display:inline-flex;
        align-items:center;
        gap:4px;
        border-radius:999px;
        padding:5px 10px;
        font-size:12px;
        font-weight:900;
        white-space:nowrap;
    }

    .st-new{background:#e0f2fe;color:#075985}
    .st-in_progress{background:#fef3c7;color:#92400e}
    .st-submitted{background:#ede9fe;color:#5b21b6}
    .st-approved{background:#dcfce7;color:#166534}

    .sticky-actions{
        position:sticky;
        bottom:12px;
        z-index:30;
        margin-top:14px;
        padding:12px;
        background:rgba(244,247,251,.88);
        backdrop-filter:blur(10px);
        display:flex;
        justify-content:flex-end;
        gap:8px;
    }

    @media(max-width:1100px){
        .layout{grid-template-columns:1fr}
    }

    @media(max-width:768px){
        .page-shell{padding:14px}
        .page-head{flex-direction:column;align-items:flex-start}
    }


.ego-assignee-box{
    border:1px solid #dbe3ee;
    border-radius:16px;
    background:#fff;
    padding:14px;
}
.ego-assignee-grid{
    display:grid;
    grid-template-columns:1fr 1fr;
    gap:14px;
}
.ego-assignee-panel{
    border:1px solid #e5eaf1;
    border-radius:14px;
    background:#f8fafc;
    padding:10px;
}
.ego-assignee-head{
    display:flex;
    align-items:center;
    justify-content:space-between;
    gap:8px;
    margin-bottom:8px;
}
.ego-assignee-title{
    font-weight:900;
    color:#0f172a;
    font-size:13px;
}
.ego-assignee-list{
    height:270px;
    overflow:auto;
    background:#fff;
    border:1px solid #e5eaf1;
    border-radius:12px;
    padding:8px;
}
.ego-assignee-row{
    display:flex;
    align-items:center;
    justify-content:space-between;
    gap:8px;
    border:1px solid #edf2f7;
    border-radius:10px;
    padding:8px 10px;
    margin-bottom:6px;
    background:#fff;
}
.ego-assignee-name{
    font-weight:850;
    color:#0f172a;
    font-size:13px;
}
.ego-assignee-email{
    color:#64748b;
    font-size:12px;
    margin-top:2px;
}
@media(max-width:900px){
    .ego-assignee-grid{
        grid-template-columns:1fr;
    }
}


/* EGO_EDIT_TASK_FILE_UI_START */
.task-file-tools{
    display:flex;
    align-items:center;
    justify-content:center;
    gap:8px;
    flex-wrap:wrap;
    margin-top:12px;
}

.task-file-input{
    display:none !important;
}

.task-file-list{
    margin-top:14px;
    display:none;
    text-align:left;
    border:1px solid #dbeafe;
    background:#fff;
    border-radius:14px;
    padding:10px;
}

.task-file-list.active{
    display:block;
}

.task-file-row{
    display:flex;
    align-items:center;
    justify-content:space-between;
    gap:10px;
    padding:9px 10px;
    border:1px solid #edf2f7;
    border-radius:12px;
    background:#f8fafc;
    margin-bottom:8px;
}

.task-file-row:last-child{
    margin-bottom:0;
}

.task-file-info{
    min-width:0;
    display:flex;
    align-items:center;
    gap:9px;
}

.task-file-icon{
    width:34px;
    height:34px;
    border-radius:10px;
    display:inline-flex;
    align-items:center;
    justify-content:center;
    background:#e0f2fe;
    color:#0369a1;
    flex:0 0 auto;
}

.task-file-name{
    font-size:13px;
    font-weight:850;
    color:#0f172a;
    white-space:nowrap;
    overflow:hidden;
    text-overflow:ellipsis;
    max-width:620px;
}

.task-file-size{
    font-size:12px;
    color:#64748b;
    margin-top:1px;
}
/* EGO_EDIT_TASK_FILE_UI_END */

</style>

<div class="edit-task-page">
    <div class="page-shell">

        <div class="page-head">
            <div>
                <h4>Sửa công việc</h4>
                <div class="desc">{{ $task->title }}</div>
            </div>

            <div class="d-flex gap-2 flex-wrap">
                <a href="{{ route('tasks.show', $task) }}" class="btn btn-outline-secondary btn-pill">
                    <i class="bi bi-arrow-left"></i> Quay lại chi tiết
                </a>

                <a href="{{ route('tasks.index') }}" class="btn btn-outline-primary btn-pill">
                    Danh sách việc
                </a>
            </div>
        </div>

        <div class="hero">
            <h3>Cập nhật phiếu giao việc</h3>
            <p>Chỉnh người nhận, trạng thái, hạn xử lý, mô tả hoặc bổ sung file giao việc.</p>
        </div>

        @if($errors->any())
            <div class="alert alert-danger border-0 shadow-sm rounded-4">
                <b>Chưa cập nhật được.</b>
                <ul class="mb-0 mt-1">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="POST" action="{{ route('tasks.update', $task) }}" enctype="multipart/form-data">
            @csrf
            @method('PUT')

            <div class="layout">
                <div class="soft-card">
                    <div class="card-head">
                        <i class="bi bi-pencil-square"></i> Thông tin công việc
                    </div>

                    <div class="form-body">
                        @include('tasks.partials.technical-context')

                        <div class="mb-3">
                            <label class="form-label">Tiêu đề công việc <span class="text-danger">*</span></label>
                            <input type="text"
                                   name="title"
                                   class="form-control"
                                   value="{{ old('title', $task->title) }}"
                                   required>
                        </div>

                        
                        <div class="row g-3">
                            <div class="col-12 mb-3">
                                <label class="form-label">Người nhận việc <span class="text-danger">*</span></label>

                                <div class="ego-assignee-box">
                                    @php
                                        $selectedAssignees = array_map('strval', old('assignee_ids', [$task->assignee_id]));
                                    @endphp

                                    <div class="ego-assignee-grid">
                                        <div class="ego-assignee-panel">
                                            <div class="ego-assignee-head">
                                                <div class="ego-assignee-title">Danh sách nhân viên</div>
                                                <button type="button" class="btn btn-sm btn-outline-primary ego-add-all">Chọn tất cả</button>
                                            </div>

                                            <input type="text" class="form-control form-control-sm mb-2 ego-assignee-search" placeholder="Tìm nhân viên theo tên hoặc email...">

                                            <div class="ego-assignee-list ego-assignee-available">
                                                @foreach($users as $user)
                                                    <div
                                                        class="ego-assignee-row ego-available-row"
                                                        data-id="{{ $user->id }}"
                                                        data-name="{{ $user->name }}"
                                                        data-email="{{ $user->email }}"
                                                    >
                                                        <div>
                                                            <div class="ego-assignee-name">{{ $user->name }}</div>
                                                            @if($user->email)
                                                                <div class="ego-assignee-email">{{ $user->email }}</div>
                                                            @endif
                                                        </div>

                                                        <button type="button" class="btn btn-sm btn-outline-success ego-add-assignee">Thêm</button>
                                                    </div>
                                                @endforeach
                                            </div>
                                        </div>

                                        <div class="ego-assignee-panel">
                                            <div class="ego-assignee-head">
                                                <div class="ego-assignee-title">Người đã chọn: <span class="ego-assignee-count">0</span></div>
                                                <button type="button" class="btn btn-sm btn-outline-secondary ego-clear-all">Bỏ hết</button>
                                            </div>

                                            <div class="ego-assignee-list ego-assignee-selected">
                                                @foreach($users as $user)
                                                    @if(in_array((string)$user->id, $selectedAssignees, true))
                                                        <div
                                                            class="ego-assignee-row ego-selected-row"
                                                            data-id="{{ $user->id }}"
                                                            data-name="{{ $user->name }}"
                                                            data-email="{{ $user->email }}"
                                                        >
                                                            <div>
                                                                <div class="ego-assignee-name">{{ $user->name }}</div>
                                                                @if($user->email)
                                                                    <div class="ego-assignee-email">{{ $user->email }}</div>
                                                                @endif
                                                            </div>

                                                            <button type="button" class="btn btn-sm btn-outline-danger ego-remove-assignee">Xóa</button>
                                                        </div>
                                                    @endif
                                                @endforeach
                                            </div>

                                            <div class="ego-assignee-inputs"></div>
                                        </div>
                                    </div>

                                    <div class="form-text mt-2">Người đầu tiên trong bảng đã chọn sẽ cập nhật phiếu hiện tại, các người còn lại sẽ được tạo thêm phiếu giao việc riêng.</div>
                                </div>
                            </div>

                            <div class="col-md-6 mb-3">
                                <label class="form-label">Mức ưu tiên</label>
                                <select name="priority" class="form-select">
                                    @foreach($priorities as $key => $label)
                                        <option value="{{ $key }}" {{ old('priority', $task->priority ?? null) === $key ? 'selected' : '' }}>
                                            {{ $label }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="col-md-6 mb-3">
                                <label class="form-label">Trạng thái</label>
                                <select name="status" class="form-select">
                                    @foreach($statuses as $key => $label)
                                        <option value="{{ $key }}" {{ old('status', $task->status ?? 'new') === $key ? 'selected' : '' }}>
                                            {{ $label }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

<div class="row g-3">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Hạn hoàn thành</label>
                                <input type="date"
                                       name="due_at"
                                       class="form-control"
                                       value="{{ old('due_at', $task->due_at ? \Illuminate\Support\Carbon::parse($task->due_at)->format('Y-m-d') : '') }}">
                            </div>

                            <div class="col-md-6 mb-3">
                                <label class="form-label">Link liên quan</label>
                                <input type="url"
                                       name="link_url"
                                       class="form-control"
                                       value="{{ old('link_url', $task->link_url) }}"
                                       placeholder="https://...">
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Mô tả / yêu cầu công việc</label>
                            <textarea name="description"
                                      rows="6"
                                      class="form-control"
                                      placeholder="Mô tả rõ việc cần làm, yêu cầu đầu ra, tiêu chuẩn hoàn thành...">{{ old('description', $task->description) }}</textarea>
                        </div>

                        <div class="upload-zone">
                            <div class="upload-icon">
                                <i class="bi bi-cloud-arrow-up"></i>
                            </div>

                            <div class="fw-bold">Bổ sung file giao việc</div>
                            <div class="text-muted small mb-3">
                                Có thể chọn nhiều file. File cũ vẫn giữ nguyên.
                            </div>

                            <div class="task-file-tools">
    <button type="button" class="btn btn-outline-primary task-btn-pill" id="chooseEditTaskFiles">
        <i class="bi bi-paperclip"></i> Chọn / thêm file
    </button>

    <button type="button" class="btn btn-outline-danger task-btn-pill d-none" id="clearEditTaskFiles">
        <i class="bi bi-trash"></i> Xóa hết file
    </button>
</div>

<input type="file"
       name="attachments[]"
       id="editTaskFileInput"
       class="task-file-input"
       multiple>

<div class="task-file-list" id="editTaskFileList"></div>
                        </div>
                    </div>
                </div>

                <div class="soft-card">
                    <div class="card-head">
                        <i class="bi bi-info-circle"></i> Thông tin hiện tại
                    </div>

                    <div class="info-line">
                        <div class="info-label">Mã công việc</div>
                        <div class="info-value">#{{ $task->id }}</div>
                    </div>

                    <div class="info-line">
                        <div class="info-label">Trạng thái</div>
                        <div class="info-value">
                            <span class="soft-badge st-{{ $task->status ?? 'new' }}">
                                {{ $statuses[$task->status ?? 'new'] ?? ($task->status ?? 'new') }}
                            </span>
                        </div>
                    </div>

                    <div class="info-line">
                        <div class="info-label">Tiến độ</div>
                        <div class="info-value">{{ (int)($task->progress_percent ?? 0) }}%</div>
                    </div>

                    <div class="info-line">
                        <div class="info-label">Ngày giao</div>
                        <div class="info-value">{{ $task->created_at }}</div>
                    </div>

                    <div class="info-line">
                        <div class="info-label">Cập nhật cuối</div>
                        <div class="info-value">{{ $task->updated_at }}</div>
                    </div>

                    <div class="info-line">
                        <div class="info-label">Ngày nộp kết quả</div>
                        <div class="info-value">{{ $task->completed_at ?: '-' }}</div>
                    </div>
                </div>
            </div>

            <div class="sticky-actions">
                <a href="{{ route('tasks.show', $task) }}" class="btn btn-outline-secondary btn-pill">
                    Hủy
                </a>

                <button type="submit" class="btn btn-success btn-pill px-5">
                    <i class="bi bi-save"></i> Lưu thay đổi
                </button>
            </div>
        </form>

    </div>
</div>

<!-- EGO_EDIT_TASK_FILE_JS_DONE -->

<script>
document.addEventListener('DOMContentLoaded', function () {
    const fileInput = document.getElementById('editTaskFileInput');
    const chooseFiles = document.getElementById('chooseEditTaskFiles');
    const clearFiles = document.getElementById('clearEditTaskFiles');
    const fileList = document.getElementById('editTaskFileList');

    if (!fileInput || !chooseFiles || !fileList) return;

    let selectedTaskFiles = [];

    function formatFileSize(bytes) {
        if (!bytes && bytes !== 0) return '';
        const units = ['B', 'KB', 'MB', 'GB'];
        let size = bytes;
        let unit = 0;

        while (size >= 1024 && unit < units.length - 1) {
            size = size / 1024;
            unit++;
        }

        return size.toFixed(unit === 0 ? 0 : 1) + ' ' + units[unit];
    }

    function fileKey(file) {
        return [file.name, file.size, file.lastModified, file.type].join('|');
    }

    function syncFileInput() {
        const dataTransfer = new DataTransfer();

        selectedTaskFiles.forEach(function (file) {
            dataTransfer.items.add(file);
        });

        fileInput.files = dataTransfer.files;
    }

    function refreshFileList() {
        syncFileInput();
        fileList.innerHTML = '';

        if (!selectedTaskFiles.length) {
            fileList.classList.remove('active');
            if (clearFiles) clearFiles.classList.add('d-none');
            return;
        }

        fileList.classList.add('active');
        if (clearFiles) clearFiles.classList.remove('d-none');

        selectedTaskFiles.forEach(function (file, index) {
            const row = document.createElement('div');
            row.className = 'task-file-row';

            row.innerHTML =
                '<div class="task-file-info">' +
                    '<span class="task-file-icon"><i class="bi bi-file-earmark"></i></span>' +
                    '<div class="min-w-0">' +
                        '<div class="task-file-name"></div>' +
                        '<div class="task-file-size"></div>' +
                    '</div>' +
                '</div>' +
                '<button type="button" class="btn btn-sm btn-outline-danger" data-remove-file="' + index + '">' +
                    '<i class="bi bi-x-lg"></i> Xóa' +
                '</button>';

            row.querySelector('.task-file-name').textContent = file.name;
            row.querySelector('.task-file-size').textContent = formatFileSize(file.size);

            fileList.appendChild(row);
        });
    }

    function addFiles(files) {
        const existing = new Set(selectedTaskFiles.map(fileKey));

        files.forEach(function (file) {
            const key = fileKey(file);

            if (!existing.has(key)) {
                selectedTaskFiles.push(file);
                existing.add(key);
            }
        });

        refreshFileList();
    }

    chooseFiles.addEventListener('click', function () {
        fileInput.click();
    });

    fileInput.addEventListener('change', function () {
        addFiles(Array.from(fileInput.files || []));
        fileInput.value = '';
        refreshFileList();
    });

    if (clearFiles) {
        clearFiles.addEventListener('click', function () {
            selectedTaskFiles = [];
            refreshFileList();
        });
    }

    fileList.addEventListener('click', function (event) {
        const removeBtn = event.target.closest('[data-remove-file]');
        if (!removeBtn) return;

        const removeIndex = Number(removeBtn.dataset.removeFile);

        selectedTaskFiles = selectedTaskFiles.filter(function (_, index) {
            return index !== removeIndex;
        });

        refreshFileList();
    });

    refreshFileList();
});
</script>

@endsection







<script>
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.ego-assignee-box').forEach(function (box) {
        const available = box.querySelector('.ego-assignee-available');
        const selected = box.querySelector('.ego-assignee-selected');
        const inputs = box.querySelector('.ego-assignee-inputs');
        const search = box.querySelector('.ego-assignee-search');
        const count = box.querySelector('.ego-assignee-count');

        function esc(s) {
            return String(s || '').replace(/[&<>"']/g, function (m) {
                return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[m];
            });
        }

        function selectedIds() {
            return Array.from(selected.querySelectorAll('.ego-selected-row')).map(function (row) {
                return row.dataset.id;
            });
        }

        function makeSelectedRow(id, name, email) {
            const row = document.createElement('div');
            row.className = 'ego-assignee-row ego-selected-row';
            row.dataset.id = id;
            row.dataset.name = name;
            row.dataset.email = email || '';

            row.innerHTML =
                '<div>' +
                    '<div class="ego-assignee-name">' + esc(name) + '</div>' +
                    (email ? '<div class="ego-assignee-email">' + esc(email) + '</div>' : '') +
                '</div>' +
                '<button type="button" class="btn btn-sm btn-outline-danger ego-remove-assignee">Xóa</button>';

            return row;
        }

        function sync() {
            const ids = selectedIds();

            inputs.innerHTML = '';
            ids.forEach(function (id) {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'assignee_ids[]';
                input.value = id;
                inputs.appendChild(input);
            });

            available.querySelectorAll('.ego-available-row').forEach(function (row) {
                row.style.display = ids.includes(row.dataset.id) ? 'none' : '';
            });

            count.textContent = ids.length;
        }

        box.addEventListener('click', function (e) {
            const addBtn = e.target.closest('.ego-add-assignee');
            const removeBtn = e.target.closest('.ego-remove-assignee');
            const addAllBtn = e.target.closest('.ego-add-all');
            const clearBtn = e.target.closest('.ego-clear-all');

            if (addBtn) {
                const row = addBtn.closest('.ego-available-row');
                selected.appendChild(makeSelectedRow(row.dataset.id, row.dataset.name, row.dataset.email));
                sync();
            }

            if (removeBtn) {
                removeBtn.closest('.ego-selected-row').remove();
                sync();
            }

            if (addAllBtn) {
                available.querySelectorAll('.ego-available-row').forEach(function (row) {
                    if (row.style.display !== 'none') {
                        selected.appendChild(makeSelectedRow(row.dataset.id, row.dataset.name, row.dataset.email));
                    }
                });
                sync();
            }

            if (clearBtn) {
                selected.innerHTML = '';
                sync();
            }
        });

        if (search) {
            search.addEventListener('input', function () {
                const kw = search.value.toLowerCase().trim();
                const ids = selectedIds();

                available.querySelectorAll('.ego-available-row').forEach(function (row) {
                    const txt = row.textContent.toLowerCase();
                    row.style.display = (!ids.includes(row.dataset.id) && txt.includes(kw)) ? '' : 'none';
                });
            });
        }

        sync();
    });
});
</script>