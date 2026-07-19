@extends('layouts.app')

@section('content')
@php
    $priorities = $priorities ?? [
        'low' => 'Thấp',
        'medium' => 'Trung bình',
        'high' => 'Cao',
        'urgent' => 'Khẩn cấp',
    ];

    $statuses = $statuses ?? [
        'new' => 'Mới giao',
        'in_progress' => 'Đang làm',
        'pending' => 'Chờ xử lý',
        'completed' => 'Hoàn thành',
        'cancelled' => 'Đã hủy',
    ];

    $selectedAssignees = array_map('strval', old('assignee_ids', []));
@endphp

<style>
    .task-create-page{
        background:#f4f7fb;
        min-height:100vh;
        padding:22px;
        color:#0f172a;
        font-size:13px;
        overflow-x:hidden;
    }

    .task-create-page *{
        box-sizing:border-box;
    }

    .task-page-head{
        display:flex;
        align-items:flex-start;
        justify-content:space-between;
        gap:14px;
        margin-bottom:16px;
    }

    .task-page-title{
        margin:0;
        font-size:24px;
        line-height:1.2;
        font-weight:950;
        letter-spacing:-.03em;
        color:#0f172a;
    }

    .task-page-desc{
        margin-top:4px;
        color:#64748b;
        font-size:13px;
    }

    .task-btn-pill{
        border-radius:999px;
        font-weight:800;
        padding:8px 16px;
        white-space:nowrap;
    }

    .task-hero{
        border-radius:24px;
        padding:22px 24px;
        margin-bottom:16px;
        color:#fff;
        background:
            radial-gradient(700px 280px at 92% 0%, rgba(45,212,191,.28), transparent 62%),
            linear-gradient(135deg,#020617 0%,#075985 58%,#0f766e 100%);
        box-shadow:0 18px 44px rgba(15,23,42,.16);
    }

    .task-hero h3{
        margin:0;
        font-size:25px;
        font-weight:950;
        letter-spacing:-.035em;
    }

    .task-hero p{
        margin:6px 0 0;
        color:rgba(255,255,255,.84);
        font-size:13px;
    }

    .task-layout{
        display:grid;
        grid-template-columns:minmax(0,1fr) 340px;
        gap:16px;
        align-items:start;
        width:100%;
        max-width:100%;
    }

    .task-card{
        background:#fff;
        border:1px solid #e5eaf1;
        border-radius:22px;
        box-shadow:0 12px 32px rgba(15,23,42,.065);
        overflow:hidden;
        min-width:0;
    }

    .task-card-head{
        padding:14px 16px;
        border-bottom:1px solid #e5eaf1;
        font-size:15px;
        font-weight:950;
        color:#0f172a;
        background:#fff;
    }

    .task-card-body{
        padding:18px;
    }

    .task-create-page .form-label{
        margin-bottom:6px;
        color:#334155;
        font-size:12px;
        font-weight:850;
    }

    .task-create-page .form-control,
    .task-create-page .form-select{
        border-radius:12px;
        border-color:#dbe3ee;
        font-size:13px;
        min-height:38px;
    }

    .task-create-page textarea.form-control{
        min-height:128px;
    }

    .task-create-page .form-control:focus,
    .task-create-page .form-select:focus{
        border-color:#0ea5e9;
        box-shadow:0 0 0 .2rem rgba(14,165,233,.12);
    }

    .assignee-box{
        border:1px solid #dbe3ee;
        border-radius:18px;
        background:#fff;
        padding:14px;
    }

    .assignee-grid{
        display:grid;
        grid-template-columns:minmax(0,1fr) minmax(0,1fr);
        gap:14px;
    }

    .assignee-panel{
        min-width:0;
        border:1px solid #e5eaf1;
        border-radius:16px;
        background:#f8fafc;
        padding:10px;
    }

    .assignee-head{
        display:flex;
        align-items:center;
        justify-content:space-between;
        gap:10px;
        margin-bottom:8px;
    }

    .assignee-title{
        font-size:13px;
        font-weight:950;
        color:#0f172a;
    }

    .assignee-list{
        height:292px;
        overflow:auto;
        background:#fff;
        border:1px solid #e5eaf1;
        border-radius:14px;
        padding:8px;
    }

    .assignee-row{
        display:flex;
        align-items:center;
        justify-content:space-between;
        gap:10px;
        border:1px solid #edf2f7;
        border-radius:12px;
        padding:9px 10px;
        margin-bottom:7px;
        background:#fff;
    }

    .assignee-row:last-child{
        margin-bottom:0;
    }

    .assignee-info{
        min-width:0;
    }

    .assignee-name{
        font-size:13px;
        font-weight:900;
        color:#0f172a;
        white-space:nowrap;
        overflow:hidden;
        text-overflow:ellipsis;
    }

    .assignee-email{
        margin-top:2px;
        color:#64748b;
        font-size:12px;
        white-space:nowrap;
        overflow:hidden;
        text-overflow:ellipsis;
    }

    .upload-zone{
        border:2px dashed #cbd5e1;
        border-radius:18px;
        padding:20px;
        background:#f8fafc;
        text-align:center;
        transition:.15s ease;
    }

    .upload-zone:hover{
        background:#f0f9ff;
        border-color:#38bdf8;
    }

    .task-file-tools{
        display:flex;
        align-items:center;
        justify-content:center;
        gap:8px;
        flex-wrap:wrap;
        margin-top:12px;
    }

    .task-file-input{
        display:none;
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

    .upload-icon{
        width:54px;
        height:54px;
        display:inline-flex;
        align-items:center;
        justify-content:center;
        margin-bottom:10px;
        border-radius:18px;
        background:#e0f2fe;
        color:#0369a1;
        font-size:28px;
    }

    .side-tip{
        padding:15px;
        border-bottom:1px solid #e5eaf1;
    }

    .side-tip:last-child{
        border-bottom:0;
    }

    .side-tip-main{
        display:flex;
        align-items:center;
        gap:8px;
        font-weight:950;
        color:#0f172a;
    }

    .side-tip-num{
        width:28px;
        height:28px;
        display:inline-flex;
        align-items:center;
        justify-content:center;
        border-radius:10px;
        background:#e0f2fe;
        color:#0369a1;
        font-weight:950;
        flex:0 0 auto;
    }

    .side-tip-desc{
        color:#64748b;
        font-size:12px;
        line-height:1.55;
        margin-top:6px;
    }

    .task-actions{
        display:flex;
        align-items:center;
        justify-content:flex-end;
        gap:10px;
        margin-top:16px;
        padding:14px;
        background:#fff;
        border:1px solid #e5eaf1;
        border-radius:18px;
        box-shadow:0 10px 26px rgba(15,23,42,.055);
    }

    .task-actions .btn{
        width:auto;
        height:auto;
        min-height:38px;
        display:inline-flex;
        align-items:center;
        justify-content:center;
    }

    @media(max-width:1180px){
        .task-layout{
            grid-template-columns:1fr;
        }
    }

    @media(max-width:900px){
        .assignee-grid{
            grid-template-columns:1fr;
        }
    }

    @media(max-width:768px){
        .task-create-page{
            padding:14px;
        }

        .task-page-head{
            flex-direction:column;
        }

        .task-page-head .btn{
            width:100%;
            justify-content:center;
        }

        .task-actions{
            flex-direction:column-reverse;
            align-items:stretch;
        }

        .task-actions .btn{
            width:100%;
        }
    }
</style>

<div class="task-create-page">
    <div class="task-page-head">
        <div>
            <h4 class="task-page-title">Giao việc mới</h4>
            <div class="task-page-desc">Sếp / trưởng bộ phận giao việc cho nhân viên, kèm hạn xử lý và file liên quan.</div>
        </div>

        <a href="{{ route('tasks.index') }}" class="btn btn-outline-secondary task-btn-pill">
            <i class="bi bi-arrow-left"></i> Danh sách việc
        </a>
    </div>

    <div class="task-hero">
        <h3>Phiếu giao việc</h3>
        <p>Giao việc rõ người, rõ hạn, rõ yêu cầu để nhân viên cập nhật tiến độ và nộp kết quả.</p>
    </div>

    @if($errors->any())
        <div class="alert alert-danger border-0 shadow-sm rounded-4">
            <b>Chưa giao được việc.</b>
            <ul class="mb-0 mt-1">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ route('tasks.store') }}" enctype="multipart/form-data">
        @csrf

        <div class="task-layout">
            <div>
                <div class="task-card">
                    <div class="task-card-head">
                        <i class="bi bi-pencil-square"></i> Thông tin công việc
                    </div>

                    <div class="task-card-body">
                        <div class="mb-3">
                            <label class="form-label">Tiêu đề công việc <span class="text-danger">*</span></label>
                            <input type="text"
                                   name="title"
                                   class="form-control"
                                   value="{{ old('title') }}"
                                   required
                                   placeholder="Ví dụ: Kiểm tra tiến độ công trình A">
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Người nhận việc <span class="text-danger">*</span></label>

                            <div class="assignee-box" id="assigneeBox">
                                <div class="assignee-grid">
                                    <div class="assignee-panel">
                                        <div class="assignee-head">
                                            <div class="assignee-title">Danh sách nhân viên</div>
                                            <button type="button" class="btn btn-sm btn-outline-primary" id="addAllAssignees">Chọn tất cả</button>
                                        </div>

                                        <input type="text"
                                               class="form-control form-control-sm mb-2"
                                               id="assigneeSearch"
                                               placeholder="Tìm nhân viên theo tên hoặc email...">

                                        <div class="assignee-list" id="availableAssignees">
                                            @foreach($users as $user)
                                                <div class="assignee-row available-assignee"
                                                     data-id="{{ $user->id }}"
                                                     data-name="{{ $user->name }}"
                                                     data-email="{{ $user->email }}">
                                                    <div class="assignee-info">
                                                        <div class="assignee-name">{{ $user->name }}</div>
                                                        @if($user->email)
                                                            <div class="assignee-email">{{ $user->email }}</div>
                                                        @endif
                                                    </div>

                                                    <button type="button" class="btn btn-sm btn-outline-success add-assignee">Thêm</button>
                                                </div>
                                            @endforeach
                                        </div>
                                    </div>

                                    <div class="assignee-panel">
                                        <div class="assignee-head">
                                            <div class="assignee-title">Người đã chọn: <span id="assigneeCount">0</span></div>
                                            <button type="button" class="btn btn-sm btn-outline-secondary" id="clearAssignees">Bỏ hết</button>
                                        </div>

                                        <div class="assignee-list" id="selectedAssignees">
                                            @foreach($users as $user)
                                                @if(in_array((string) $user->id, $selectedAssignees, true))
                                                    <div class="assignee-row selected-assignee"
                                                         data-id="{{ $user->id }}"
                                                         data-name="{{ $user->name }}"
                                                         data-email="{{ $user->email }}">
                                                        <div class="assignee-info">
                                                            <div class="assignee-name">{{ $user->name }}</div>
                                                            @if($user->email)
                                                                <div class="assignee-email">{{ $user->email }}</div>
                                                            @endif
                                                        </div>

                                                        <button type="button" class="btn btn-sm btn-outline-danger remove-assignee">Xóa</button>
                                                    </div>
                                                @endif
                                            @endforeach
                                        </div>

                                        <div id="assigneeInputs"></div>
                                    </div>
                                </div>

                                <div class="form-text mt-2">Mỗi người trong bảng đã chọn sẽ được tạo một công việc riêng.</div>
                            </div>
                        </div>

                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label">Mức ưu tiên</label>
                                <select name="priority" class="form-select">
                                    @foreach($priorities as $key => $label)
                                        <option value="{{ $key }}" {{ old('priority', 'medium') === $key ? 'selected' : '' }}>
                                            {{ $label }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="col-md-4">
                                <label class="form-label">Trạng thái</label>
                                <select name="status" class="form-select">
                                    @foreach($statuses as $key => $label)
                                        <option value="{{ $key }}" {{ old('status', 'new') === $key ? 'selected' : '' }}>
                                            {{ $label }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="col-md-4">
                                <label class="form-label">Hạn hoàn thành</label>
                                <input type="date"
                                       name="due_at"
                                       class="form-control"
                                       value="{{ old('due_at') }}">
                            </div>
                        </div>

                        <div class="mt-3">
                            <label class="form-label">Link liên quan</label>
                            <input type="url"
                                   name="link_url"
                                   class="form-control"
                                   value="{{ old('link_url') }}"
                                   placeholder="https://...">
                        </div>

                        <div class="mt-3">
                            <label class="form-label">Mô tả / yêu cầu công việc</label>
                            <textarea name="description"
                                      class="form-control"
                                      rows="6"
                                      placeholder="Mô tả rõ việc cần làm, yêu cầu đầu ra, tiêu chuẩn hoàn thành...">{{ old('description') }}</textarea>
                        </div>

                        <div class="upload-zone mt-3" id="taskUploadZone">
                            <div class="upload-icon">
                                <i class="bi bi-cloud-arrow-up"></i>
                            </div>

                            <div class="fw-bold">Đính kèm file giao việc</div>
                            <div class="text-muted small">Có thể chọn nhiều file. Tối đa 20MB mỗi file.</div>

                            <div class="task-file-tools">
                                <button type="button" class="btn btn-outline-primary task-btn-pill" id="chooseTaskFiles">
                                    <i class="bi bi-paperclip"></i> Chọn / thêm file
                                </button>

                                <button type="button" class="btn btn-outline-danger task-btn-pill d-none" id="clearTaskFiles">
                                    <i class="bi bi-trash"></i> Xóa hết file
                                </button>
                            </div>

                            <input type="file"
                                   name="attachments[]"
                                   id="taskFileInput"
                                   class="task-file-input"
                                   multiple>

                            <div class="task-file-list" id="taskFileList"></div>
                        </div>
                    </div>
                </div>

                <div class="task-actions">
                    <a href="{{ route('tasks.index') }}" class="btn btn-outline-secondary task-btn-pill">
                        Hủy
                    </a>

                    <button type="submit" class="btn btn-success task-btn-pill px-5">
                        <i class="bi bi-send"></i> Giao việc
                    </button>
                </div>
            </div>

            <div class="task-card">
                <div class="task-card-head">
                    <i class="bi bi-stars"></i> Gợi ý giao việc hiệu quả
                </div>

                <div class="side-tip">
                    <div class="side-tip-main">
                        <span class="side-tip-num">1</span>
                        <span>Rõ đầu việc</span>
                    </div>
                    <div class="side-tip-desc">Tiêu đề nên ngắn, rõ kết quả cần đạt, tránh giao việc chung chung.</div>
                </div>

                <div class="side-tip">
                    <div class="side-tip-main">
                        <span class="side-tip-num">2</span>
                        <span>Có hạn hoàn thành</span>
                    </div>
                    <div class="side-tip-desc">Hạn giúp nhân viên ưu tiên và giúp sếp theo dõi việc quá hạn.</div>
                </div>

                <div class="side-tip">
                    <div class="side-tip-main">
                        <span class="side-tip-num">3</span>
                        <span>Đính kèm tài liệu</span>
                    </div>
                    <div class="side-tip-desc">Có thể kèm hình ảnh, file Excel/PDF, link drive hoặc tài liệu yêu cầu.</div>
                </div>

                <div class="side-tip">
                    <div class="side-tip-main">
                        <span class="side-tip-num">4</span>
                        <span>Theo dõi kết quả</span>
                    </div>
                    <div class="side-tip-desc">Nhân viên sẽ cập nhật tiến độ, nộp kết quả và chờ sếp duyệt hoàn thành.</div>
                </div>
            </div>
        </div>
    </form>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const box = document.getElementById('assigneeBox');
    if (!box) return;

    const available = document.getElementById('availableAssignees');
    const selected = document.getElementById('selectedAssignees');
    const inputs = document.getElementById('assigneeInputs');
    const search = document.getElementById('assigneeSearch');
    const count = document.getElementById('assigneeCount');
    const addAll = document.getElementById('addAllAssignees');
    const clearAll = document.getElementById('clearAssignees');

    function escapeHtml(value) {
        return String(value || '').replace(/[&<>"']/g, function (char) {
            return {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;'
            }[char];
        });
    }

    function getSelectedIds() {
        return Array.from(selected.querySelectorAll('.selected-assignee')).map(function (row) {
            return String(row.dataset.id);
        });
    }

    function makeSelectedRow(id, name, email) {
        const row = document.createElement('div');
        row.className = 'assignee-row selected-assignee';
        row.dataset.id = id;
        row.dataset.name = name || '';
        row.dataset.email = email || '';

        row.innerHTML =
            '<div class="assignee-info">' +
                '<div class="assignee-name">' + escapeHtml(name) + '</div>' +
                (email ? '<div class="assignee-email">' + escapeHtml(email) + '</div>' : '') +
            '</div>' +
            '<button type="button" class="btn btn-sm btn-outline-danger remove-assignee">Xóa</button>';

        return row;
    }

    function syncAssignees() {
        const ids = getSelectedIds();

        inputs.innerHTML = '';
        ids.forEach(function (id) {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'assignee_ids[]';
            input.value = id;
            inputs.appendChild(input);
        });

        available.querySelectorAll('.available-assignee').forEach(function (row) {
            const rowId = String(row.dataset.id);
            const text = row.textContent.toLowerCase();
            const keyword = search ? search.value.toLowerCase().trim() : '';
            const isSelected = ids.includes(rowId);
            const isMatch = !keyword || text.includes(keyword);

            row.style.display = (!isSelected && isMatch) ? '' : 'none';
        });

        count.textContent = ids.length;
    }

    available.addEventListener('click', function (event) {
        const button = event.target.closest('.add-assignee');
        if (!button) return;

        const row = button.closest('.available-assignee');
        if (!row) return;

        selected.appendChild(makeSelectedRow(row.dataset.id, row.dataset.name, row.dataset.email));
        syncAssignees();
    });

    selected.addEventListener('click', function (event) {
        const button = event.target.closest('.remove-assignee');
        if (!button) return;

        const row = button.closest('.selected-assignee');
        if (row) row.remove();

        syncAssignees();
    });

    if (addAll) {
        addAll.addEventListener('click', function () {
            available.querySelectorAll('.available-assignee').forEach(function (row) {
                if (row.style.display !== 'none') {
                    selected.appendChild(makeSelectedRow(row.dataset.id, row.dataset.name, row.dataset.email));
                }
            });

            syncAssignees();
        });
    }

    if (clearAll) {
        clearAll.addEventListener('click', function () {
            selected.innerHTML = '';
            syncAssignees();
        });
    }

    if (search) {
        search.addEventListener('input', syncAssignees);
    }

    syncAssignees();

    const fileInput = document.getElementById('taskFileInput');
    const chooseFiles = document.getElementById('chooseTaskFiles');
    const clearFiles = document.getElementById('clearTaskFiles');
    const fileList = document.getElementById('taskFileList');

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
        if (!fileInput) return;

        const dataTransfer = new DataTransfer();

        selectedTaskFiles.forEach(function (file) {
            dataTransfer.items.add(file);
        });

        fileInput.files = dataTransfer.files;
    }

    function refreshFileList() {
        if (!fileInput || !fileList) return;

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

    function removeFile(index) {
        selectedTaskFiles = selectedTaskFiles.filter(function (_, fileIndex) {
            return fileIndex !== index;
        });

        refreshFileList();
    }

    function clearSelectedFiles() {
        selectedTaskFiles = [];
        refreshFileList();
    }

    if (chooseFiles && fileInput) {
        chooseFiles.addEventListener('click', function () {
            fileInput.click();
        });
    }

    if (fileInput) {
        fileInput.addEventListener('change', function () {
            addFiles(Array.from(fileInput.files || []));

            /*
             * Reset value để chọn lại cùng một file vẫn trigger change.
             * Sau đó refreshFileList sẽ gán lại fileInput.files từ selectedTaskFiles.
             */
            fileInput.value = '';
            refreshFileList();
        });
    }

    if (clearFiles && fileInput) {
        clearFiles.addEventListener('click', function () {
            clearSelectedFiles();
        });
    }

    if (fileList && fileInput) {
        fileList.addEventListener('click', function (event) {
            const removeBtn = event.target.closest('[data-remove-file]');
            if (!removeBtn) return;

            removeFile(Number(removeBtn.dataset.removeFile));
        });
    }

    refreshFileList();
});
</script>
@endsection