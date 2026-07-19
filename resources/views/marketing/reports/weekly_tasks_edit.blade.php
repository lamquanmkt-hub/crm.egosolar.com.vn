@extends('layouts.app')

@section('title', 'Sửa công việc')

@section('content')
@php
    // Normalize assignees
    $assignees = old('assignees', $task->assignees ?? []);
    if (!is_array($assignees)) $assignees = [];

    if (empty($assignees) && !empty($task->assignee)) {
        $assignees = array_values(array_filter(array_map('trim', explode(',', $task->assignee))));
    }

    // Normalize links
    $links = old('links', $task->links ?? []);
    if (!is_array($links)) $links = [];

    // Existing attachments (meta: path,name,mime,size,uploaded_at)
    $attachments = $task->attachments ?? [];
    if (!is_array($attachments)) $attachments = [];
@endphp

<div class="container-fluid px-4 mt-3 weekly-task-create">

    {{-- Header --}}
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <div>
            <h3 class="fw-bold mb-1">Sửa công việc</h3>
            <div class="text-muted">Cập nhật công việc hàng tuần #{{ $task->id }}</div>
        </div>

        <a href="{{ route('marketing.reports.weekly-tasks') }}" class="btn btn-ego-soft wt-btn">
            <i class="bi bi-arrow-left"></i> Quay lại
        </a>
    </div>

    <div class="card wt-card">
        <div class="card-body p-0">

            <form method="POST"
                  action="{{ route('marketing.reports.weekly-tasks.update', $task->id) }}"
                  enctype="multipart/form-data"
                  class="p-3 p-md-4">
                @csrf
                @method('PUT')

                {{-- ====== Thông tin task ====== --}}
                <div class="wt-section mb-3">
                    <div class="wt-section-head">
                        <div class="wt-section-title">
                            <i class="bi bi-clipboard-check"></i>
                            Thông tin công việc
                        </div>
                        <div class="wt-section-sub text-muted">Chỉnh nội dung và mốc thời gian</div>
                    </div>

                    <div class="row g-3 mt-1">
                        <div class="col-md-6">
                            <label class="form-label">Tên công việc <span class="text-danger">*</span></label>
                            <input name="title"
                                   class="form-control wt-control @error('title') is-invalid @enderror"
                                   value="{{ old('title', $task->title) }}"
                                   required>
                            @error('title') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>

                        <div class="col-md-3">
                            <label class="form-label">Priority <span class="text-danger">*</span></label>
                            <select name="priority"
                                    class="form-select wt-control @error('priority') is-invalid @enderror"
                                    required>
                                <option value="high" {{ old('priority', $task->priority)==='high'?'selected':'' }}>High</option>
                                <option value="medium" {{ old('priority', $task->priority)==='medium'?'selected':'' }}>Medium</option>
                                <option value="low" {{ old('priority', $task->priority)==='low'?'selected':'' }}>Low</option>
                            </select>
                            @error('priority') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>

                        <div class="col-md-3">
                            <label class="form-label">Hạng mục</label>
                            <input name="category"
                                   class="form-control wt-control @error('category') is-invalid @enderror"
                                   value="{{ old('category', $task->category) }}">
                            @error('category') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>

                        <div class="col-md-3">
                            <label class="form-label">Ngày bắt đầu</label>
                            <input type="date"
                                   name="start_date"
                                   class="form-control wt-control @error('start_date') is-invalid @enderror"
                                   value="{{ old('start_date', optional($task->start_date)->format('Y-m-d')) }}">
                            @error('start_date') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>

                        <div class="col-md-3">
                            <label class="form-label">Hạn</label>
                            <input type="date"
                                   name="due_date"
                                   class="form-control wt-control @error('due_date') is-invalid @enderror"
                                   value="{{ old('due_date', optional($task->due_date)->format('Y-m-d')) }}">
                            @error('due_date') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>

                        <div class="col-md-3">
                            <label class="form-label">Trạng thái <span class="text-danger">*</span></label>
                            <select name="status"
                                    class="form-select wt-control @error('status') is-invalid @enderror"
                                    required>
                                <option value="pending" {{ old('status', $task->status)==='pending'?'selected':'' }}>Todo</option>
                                <option value="doing" {{ old('status', $task->status)==='doing'?'selected':'' }}>Doing</option>
                                <option value="done" {{ old('status', $task->status)==='done'?'selected':'' }}>Done</option>
                            </select>
                            @error('status') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>

                        <div class="col-md-3">
                            <label class="form-label">Tiến độ (%)</label>
                            <input type="number"
                                   name="progress"
                                   class="form-control wt-control @error('progress') is-invalid @enderror"
                                   min="0" max="100"
                                   value="{{ old('progress', $task->progress ?? 0) }}">
                            @error('progress') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            <div class="form-text">0–100</div>
                        </div>
                    </div>
                </div>

                {{-- ====== Người + Link ====== --}}
                <div class="row g-3">

                    {{-- Assignees --}}
                    <div class="col-lg-6">
                        <div class="wt-section h-100">
                            <div class="wt-section-head">
                                <div class="wt-section-title">
                                    <i class="bi bi-people"></i>
                                    Chịu trách nhiệm
                                </div>
                                <div class="wt-section-sub text-muted">Thêm 2–3 người (hoặc nhiều hơn)</div>
                            </div>

                            <label class="form-label mt-2">Danh sách người phụ trách</label>

                            <div class="input-group">
                                <input type="text"
                                       id="assigneeInput"
                                       class="form-control wt-control"
                                       placeholder="Nhập tên... (Enter để thêm)">
                                <button type="button" id="addAssigneeBtn" class="btn btn-ego-soft wt-btn">
                                    <i class="bi bi-plus-lg"></i> Thêm người
                                </button>
                            </div>

                            <div id="assigneeChips" class="wt-chips mt-3"></div>

                            <div id="assigneeHidden"></div>
                            <input type="hidden" name="assignee" id="assigneeJoined" value="{{ old('assignee', $task->assignee) }}">
                        </div>
                    </div>

                    {{-- Links --}}
                    <div class="col-lg-6">
                        <div class="wt-section h-100">
                            <div class="wt-section-head">
                                <div class="wt-section-title">
                                    <i class="bi bi-link-45deg"></i>
                                    Link liên quan
                                </div>
                                <div class="wt-section-sub text-muted">Nhiều link, xoá dễ dàng</div>
                            </div>

                            <label class="form-label mt-2">Thêm link</label>

                            <div class="input-group">
                                <input type="url"
                                       id="linkInput"
                                       class="form-control wt-control"
                                       placeholder="https://... (Enter để thêm)">
                                <button type="button" id="addLinkBtn" class="btn btn-ego-soft wt-btn">
                                    <i class="bi bi-plus-lg"></i> Thêm link
                                </button>
                            </div>

                            <div id="linkChips" class="wt-chips mt-3"></div>
                            <div id="linkHidden"></div>
                        </div>
                    </div>

                    {{-- Existing attachments --}}
                    <div class="col-12">
                        <div class="wt-section">
                            <div class="wt-section-head">
                                <div class="wt-section-title">
                                    <i class="bi bi-paperclip"></i>
                                    File đã đính kèm
                                </div>
                                <div class="wt-section-sub text-muted">Hiện file cũ + upload thêm file mới</div>
                            </div>

                            <div class="row g-3 mt-1">
                                <div class="col-lg-6">
                                    <label class="form-label">Upload thêm file</label>
                                    <input type="file"
                                           name="attachments[]"
                                           id="attachments"
                                           class="form-control wt-control"
                                           multiple
                                           accept="image/*,.pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.zip,.rar,.txt">
                                    <div class="form-text">
                                        File mới sẽ được thêm vào danh sách (không xoá file cũ).
                                    </div>
                                </div>

                                <div class="col-lg-6">
                                    <label class="form-label">Danh sách file mới đã chọn</label>
                                    <div id="fileList" class="wt-filelist">
                                        <div class="text-muted small">Chưa chọn file nào.</div>
                                    </div>
                                </div>

                                <div class="col-12">
                                    <label class="form-label">File đang có</label>

                                    @if(count($attachments) === 0)
                                        <div class="text-muted small">Chưa có file đính kèm.</div>
                                    @else
                                        <div class="wt-existing">
                                            @foreach($attachments as $a)
                                                @php
                                                    $name = $a['name'] ?? ($a['path'] ?? 'file');
                                                    $path = $a['path'] ?? null;
                                                    $isImg = isset($a['mime']) && str_starts_with($a['mime'], 'image/');
                                                @endphp
                                                <div class="wt-existing-item">
                                                    <div class="meta">
                                                        <div class="name">{{ $name }}</div>
                                                        <div class="sub text-muted small">
                                                            {{ $a['mime'] ?? 'file' }} • {{ isset($a['size']) ? round(($a['size']/1024),1).' KB' : '' }}
                                                        </div>
                                                    </div>

                                                    @if($path)
                                                        <a class="btn btn-ego-soft wt-mini"
                                                           target="_blank"
                                                           href="{{ asset('storage/'.$path) }}">
                                                            <i class="bi bi-box-arrow-up-right"></i> Mở
                                                        </a>
                                                    @endif
                                                </div>
                                            @endforeach
                                        </div>
                                    @endif
                                </div>

                                <div class="col-12">
                                    <label class="form-label">Preview ảnh mới</label>
                                    <div id="imagePreview" class="wt-preview"></div>
                                </div>
                            </div>
                        </div>
                    </div>

                </div>

                {{-- Note --}}
                <div class="wt-section mt-3">
                    <div class="wt-section-head">
                        <div class="wt-section-title">
                            <i class="bi bi-journal-text"></i>
                            Ghi chú
                        </div>
                        <div class="wt-section-sub text-muted">Thông tin thêm (nếu có)</div>
                    </div>

                    <div class="mt-2">
                        <textarea name="note"
                                  class="form-control wt-control"
                                  rows="4"
                                  placeholder="Ghi chú...">{{ old('note', $task->note) }}</textarea>
                    </div>
                </div>

                {{-- Actions --}}
                <div class="d-flex flex-wrap gap-2 mt-4">
                    <button class="btn btn-ego wt-btn">
                        <i class="bi bi-check2-circle"></i> Lưu thay đổi
                    </button>

                    <a href="{{ route('marketing.reports.weekly-tasks') }}" class="btn btn-ego-soft wt-btn">
                        Hủy
                    </a>
                </div>

            </form>
        </div>
    </div>

</div>
@endsection

@push('styles')
<style>
/* Reuse style giống create */
.weekly-task-create{
    --ego: #0E7C86;
    --ego2: #0B5E66;
    --border: rgba(12, 92, 100, .12);
    --muted: #64748b;
    --text: #0f172a;
}

.weekly-task-create .wt-card{
    border: 1px solid var(--border);
    border-radius: 18px;
    overflow: hidden;
    background: #fff;
    box-shadow: 0 12px 30px rgba(15, 23, 42, .04);
}

.weekly-task-create .wt-btn{
    height: 42px;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    border-radius: 12px;
    font-weight: 900;
    padding: 0 14px;
}

.weekly-task-create .btn-ego{
    background: linear-gradient(135deg, var(--ego), var(--ego2));
    border: none;
    color: #fff;
    box-shadow: 0 10px 22px rgba(14, 124, 134, .18);
}
.weekly-task-create .btn-ego:hover{ filter: brightness(.98); color:#fff; }

.weekly-task-create .btn-ego-soft{
    background: rgba(14, 124, 134, .10);
    border: 1px solid var(--border);
    color: var(--ego2);
    border-radius: 12px;
    font-weight: 900;
}

.weekly-task-create .wt-control{
    border-radius: 12px;
    border: 1px solid var(--border);
    min-height: 42px;
}

.weekly-task-create .wt-section{
    border: 1px solid var(--border);
    border-radius: 16px;
    padding: 14px;
    background: linear-gradient(135deg, rgba(14,124,134,.06), rgba(14,124,134,.02));
}

.weekly-task-create .wt-section-head{
    display:flex;
    align-items:flex-start;
    justify-content:space-between;
    gap: 10px;
}
.weekly-task-create .wt-section-title{
    font-weight: 950;
    color: var(--text);
    display:flex;
    align-items:center;
    gap:10px;
}
.weekly-task-create .wt-section-title i{
    width: 34px; height: 34px;
    border-radius: 12px;
    display:grid;
    place-items:center;
    background: rgba(14,124,134,.12);
    color: var(--ego2);
}
.weekly-task-create .wt-section-sub{
    font-size: 12px;
    margin-top: 4px;
}

.weekly-task-create .wt-chips{
    display:flex;
    flex-wrap:wrap;
    gap:10px;
}
.weekly-task-create .wt-chip{
    display:inline-flex;
    align-items:center;
    gap:10px;
    padding: 8px 10px;
    border-radius: 999px;
    background: #fff;
    border: 1px solid var(--border);
    box-shadow: 0 10px 18px rgba(15, 23, 42, .04);
    font-weight: 800;
    color: var(--text);
}
.weekly-task-create .wt-chip button{
    border:none;
    width: 26px; height: 26px;
    border-radius: 999px;
    background: rgba(220, 53, 69, .10);
    color: #b4232c;
    display:grid;
    place-items:center;
    font-weight: 900;
}

.weekly-task-create .wt-filelist{
    min-height: 42px;
    border-radius: 12px;
    border: 1px dashed rgba(12, 92, 100, .25);
    background: #fff;
    padding: 10px;
}
.weekly-task-create .wt-fileitem{
    display:flex;
    align-items:center;
    justify-content:space-between;
    gap: 10px;
    padding: 8px 10px;
    border-radius: 12px;
    border: 1px solid rgba(15,23,42,.06);
    margin-bottom: 8px;
}
.weekly-task-create .wt-fileitem:last-child{ margin-bottom: 0; }
.weekly-task-create .wt-filemeta{
    display:flex;
    flex-direction:column;
    gap: 2px;
}
.weekly-task-create .wt-filemeta .name{
    font-weight: 900;
    color: var(--text);
    font-size: 13px;
}
.weekly-task-create .wt-filemeta .size{
    font-size: 12px;
    color: var(--muted);
    font-weight: 800;
}
.weekly-task-create .wt-fileitem button{
    border:none;
    height: 32px;
    border-radius: 10px;
    padding: 0 10px;
    font-weight: 900;
    background: rgba(220, 53, 69, .10);
    color: #b4232c;
}

.weekly-task-create .wt-preview{
    display:flex;
    flex-wrap:wrap;
    gap: 12px;
    min-height: 70px;
    padding: 10px;
    border-radius: 12px;
    border: 1px dashed rgba(12, 92, 100, .25);
    background: #fff;
}

.weekly-task-create .wt-existing{
    margin-top: 8px;
    display:flex;
    flex-direction:column;
    gap: 10px;
}
.weekly-task-create .wt-existing-item{
    background:#fff;
    border: 1px solid rgba(15,23,42,.06);
    border-radius: 14px;
    padding: 10px 12px;
    display:flex;
    justify-content:space-between;
    gap: 12px;
    align-items:center;
}
.weekly-task-create .wt-existing-item .name{
    font-weight: 900;
    color: var(--text);
    font-size: 13px;
}
.weekly-task-create .wt-mini{
    height: 36px;
    padding: 0 12px;
    display:inline-flex;
    align-items:center;
    gap: 8px;
    border-radius: 12px;
    font-weight: 900;
}
</style>
@endpush

@push('scripts')
<script>
(function () {
    const $ = (id) => document.getElementById(id);

    const uniq = (arr) => {
        const seen = new Set();
        return arr.filter(x => {
            const k = (x || '').trim();
            if (!k) return false;
            const key = k.toLowerCase();
            if (seen.has(key)) return false;
            seen.add(key);
            return true;
        });
    };

    // ===== Assignees init from server =====
    const assigneeInput = $('assigneeInput');
    const addAssigneeBtn = $('addAssigneeBtn');
    const assigneeChips = $('assigneeChips');
    const assigneeHidden = $('assigneeHidden');
    const assigneeJoined = $('assigneeJoined');

    let assignees = @json($assignees);

    function escapeHtml(str) {
        return (str || '').replace(/[&<>"']/g, (m) => ({
            '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'
        }[m]));
    }

    function renderAssignees() {
        assignees = uniq(assignees);
        assigneeChips.innerHTML = '';
        assigneeHidden.innerHTML = '';

        if (assignees.length === 0) {
            assigneeChips.innerHTML = `<div class="text-muted small">Chưa có người phụ trách.</div>`;
        } else {
            assignees.forEach((name, idx) => {
                const chip = document.createElement('div');
                chip.className = 'wt-chip';
                chip.innerHTML = `<span>${escapeHtml(name)}</span><button type="button" title="Xóa">×</button>`;
                chip.querySelector('button').addEventListener('click', () => {
                    assignees.splice(idx, 1);
                    renderAssignees();
                });
                assigneeChips.appendChild(chip);

                const hidden = document.createElement('input');
                hidden.type = 'hidden';
                hidden.name = 'assignees[]';
                hidden.value = name;
                assigneeHidden.appendChild(hidden);
            });
        }

        assigneeJoined.value = assignees.join(', ');
    }

    function addAssignee() {
        const val = (assigneeInput.value || '').trim();
        if (!val) return;
        assignees.push(val);
        assigneeInput.value = '';
        renderAssignees();
    }

    addAssigneeBtn?.addEventListener('click', addAssignee);
    assigneeInput?.addEventListener('keydown', (e) => {
        if (e.key === 'Enter') {
            e.preventDefault();
            addAssignee();
        }
    });

    // ===== Links init =====
    const linkInput = $('linkInput');
    const addLinkBtn = $('addLinkBtn');
    const linkChips = $('linkChips');
    const linkHidden = $('linkHidden');

    let links = @json($links);

    function isValidUrl(str) {
        try {
            const u = new URL(str);
            return u.protocol === 'http:' || u.protocol === 'https:';
        } catch (e) { return false; }
    }

    function renderLinks() {
        links = uniq(links);
        linkChips.innerHTML = '';
        linkHidden.innerHTML = '';

        if (links.length === 0) {
            linkChips.innerHTML = `<div class="text-muted small">Chưa có link.</div>`;
        } else {
            links.forEach((url, idx) => {
                const chip = document.createElement('div');
                chip.className = 'wt-chip';
                chip.innerHTML = `<span style="max-width:420px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">${escapeHtml(url)}</span>
                                  <button type="button" title="Xóa">×</button>`;
                chip.querySelector('button').addEventListener('click', () => {
                    links.splice(idx, 1);
                    renderLinks();
                });
                linkChips.appendChild(chip);

                const hidden = document.createElement('input');
                hidden.type = 'hidden';
                hidden.name = 'links[]';
                hidden.value = url;
                linkHidden.appendChild(hidden);
            });
        }
    }

    function addLink() {
        const val = (linkInput.value || '').trim();
        if (!val) return;
        const url = val.startsWith('http') ? val : `https://${val}`;
        if (!isValidUrl(url)) {
            linkInput.focus();
            linkInput.classList.add('is-invalid');
            setTimeout(() => linkInput.classList.remove('is-invalid'), 1200);
            return;
        }
        links.push(url);
        linkInput.value = '';
        renderLinks();
    }

    addLinkBtn?.addEventListener('click', addLink);
    linkInput?.addEventListener('keydown', (e) => {
        if (e.key === 'Enter') {
            e.preventDefault();
            addLink();
        }
    });

    // ===== Attachments new preview/remove =====
    const attachments = $('attachments');
    const fileList = $('fileList');
    const imagePreview = $('imagePreview');

    const humanSize = (bytes) => {
        const units = ['B','KB','MB','GB'];
        let b = bytes, i = 0;
        while (b >= 1024 && i < units.length - 1) { b /= 1024; i++; }
        return `${b.toFixed(i === 0 ? 0 : 1)} ${units[i]}`;
    };

    let dt = new DataTransfer();

    function renderFiles() {
        const files = Array.from(dt.files || []);
        fileList.innerHTML = '';
        imagePreview.innerHTML = '';

        if (files.length === 0) {
            fileList.innerHTML = `<div class="text-muted small">Chưa chọn file nào.</div>`;
            imagePreview.innerHTML = `<div class="text-muted small">Chưa có ảnh.</div>`;
            return;
        }

        files.forEach((f, idx) => {
            const item = document.createElement('div');
            item.className = 'wt-fileitem';
            item.innerHTML = `
                <div class="wt-filemeta">
                    <div class="name">${escapeHtml(f.name)}</div>
                    <div class="size">${humanSize(f.size)}</div>
                </div>
                <button type="button"><i class="bi bi-trash"></i> Xóa</button>
            `;
            item.querySelector('button').addEventListener('click', () => {
                const next = new DataTransfer();
                files.forEach((ff, j) => { if (j !== idx) next.items.add(ff); });
                dt = next;
                attachments.files = dt.files;
                renderFiles();
            });
            fileList.appendChild(item);

            if (f.type && f.type.startsWith('image/')) {
                const thumb = document.createElement('div');
                thumb.className = 'wt-thumb';
                const img = document.createElement('img');
                thumb.appendChild(img);
                const tag = document.createElement('span');
                tag.textContent = 'Ảnh';
                thumb.appendChild(tag);

                const reader = new FileReader();
                reader.onload = (e) => { img.src = e.target.result; };
                reader.readAsDataURL(f);
                imagePreview.appendChild(thumb);
            }
        });

        if (imagePreview.children.length === 0) {
            imagePreview.innerHTML = `<div class="text-muted small">Không có file ảnh trong danh sách đã chọn.</div>`;
        }
    }

    attachments?.addEventListener('change', () => {
        const chosen = Array.from(attachments.files || []);
        chosen.forEach(f => dt.items.add(f));
        attachments.files = dt.files;
        renderFiles();
    });

    // init
    renderAssignees();
    renderLinks();
    renderFiles();
})();
</script>
@endpush