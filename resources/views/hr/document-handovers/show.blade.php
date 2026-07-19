@extends('layouts.app')

@section('content')
@include('hr.document-handovers._style')

@php
    $statusKeys = array_keys($statuses);
    $currentIndex = array_search($item->status, $statusKeys, true);
    $currentIndex = $currentIndex === false ? 0 : $currentIndex;
    $next = $nextStatuses[$item->status] ?? null;
@endphp

<div class="hs-page">
    @if(session('success'))
        <div class="alert alert-success" style="border-radius:16px;font-weight:800">{{ session('success') }}</div>
    @endif

    <div class="hs-hero">
        <div class="hs-hero-inner">
            <div>
                <div class="hs-kicker">📄 {{ $item->code }}</div>
                <h1 class="hs-title">{{ $item->title }}</h1>
                <p class="hs-sub">
                    Chi tiết xử lý hồ sơ: theo dõi tiến độ, chuyển bước, cập nhật người giữ hồ sơ, ghi chú và vị trí lưu trữ.
                </p>
            </div>
            <div style="display:flex;gap:10px;flex-wrap:wrap">
                <a class="hs-btn soft" href="{{ route('hr.document-handovers.index') }}">← Quay lại</a>
                <button type="button" class="hs-btn" onclick="openHsModal('editHsModal')">Sửa hồ sơ</button>
            </div>
        </div>
    </div>

    <div class="hs-detail-grid">
        <div class="hs-card">
            <div class="hs-card-head">
                <h3 class="hs-card-title">Tiến trình giao nhận</h3>
                <div style="display:flex;gap:8px;flex-wrap:wrap;justify-content:flex-end">
                    <span class="hs-pill status-{{ $item->status }}">{{ $statuses[$item->status] ?? $item->status }}</span>
                    <span class="hs-pill pri-{{ $item->priority }}">{{ $priorities[$item->priority] ?? $item->priority }}</span>
                </div>
            </div>

            <div class="hs-card-body">
                <div class="hs-flow">
                    @foreach($statuses as $key => $label)
                        @php
                            $idx = array_search($key, $statusKeys, true);
                            $cls = $idx < $currentIndex ? 'done' : ($idx === $currentIndex ? 'active' : '');
                        @endphp
                        <div class="hs-flow-step {{ $cls }}">
                            <div class="hs-flow-num">{{ $idx + 1 }}</div>
                            {{ $label }}
                        </div>
                    @endforeach
                </div>

                <div class="hs-info-grid">
                    <div class="hs-info"><div class="hs-info-label">Loại hồ sơ</div><div class="hs-info-value">{{ $item->document_type ?: '—' }}</div></div>
                    <div class="hs-info"><div class="hs-info-label">Khách hàng / Đối tượng</div><div class="hs-info-value">{{ $item->customer_name ?: '—' }}</div></div>
                    <div class="hs-info"><div class="hs-info-label">Phòng ban</div><div class="hs-info-value">{{ $item->department_name ?: '—' }}</div></div>
                    <div class="hs-info"><div class="hs-info-label">Người tạo</div><div class="hs-info-value">{{ $item->creator_name ?: '—' }}</div></div>
                    <div class="hs-info"><div class="hs-info-label">Người phụ trách</div><div class="hs-info-value">{{ $item->assignee_name ?: '—' }}</div></div>
                    <div class="hs-info"><div class="hs-info-label">Người đang giữ</div><div class="hs-info-value">{{ $item->current_holder ?: '—' }}</div></div>
                    <div class="hs-info"><div class="hs-info-label">Ngày tạo</div><div class="hs-info-value">{{ \Carbon\Carbon::parse($item->created_at)->format('d/m/Y H:i') }}</div></div>
                    <div class="hs-info">
                        <div class="hs-info-label">Hạn xử lý</div>
                        <div class="hs-info-value">{{ $item->due_date ? \Carbon\Carbon::parse($item->due_date)->format('d/m/Y') : '—' }}</div>
                    </div>
                </div>

                @if($item->description)
                    <div style="margin-top:14px;padding:13px;border-radius:16px;background:#f8fafc;border:1px solid #e2e8f0;font-size:13px;font-weight:750;color:#334155">
                        <strong>Mô tả:</strong> {{ $item->description }}
                    </div>
                @endif

                @if($item->note)
                    <div style="margin-top:10px;padding:13px;border-radius:16px;background:#fff7ed;border:1px solid #fed7aa;font-size:13px;font-weight:750;color:#9a3412">
                        <strong>Ghi chú:</strong> {{ $item->note }}
                    </div>
                @endif

                @if($item->storage_location)
                    <div style="margin-top:10px;padding:13px;border-radius:16px;background:#ecfdf5;border:1px solid #bbf7d0;font-size:13px;font-weight:800;color:#047857">
                        <strong>Vị trí lưu trữ:</strong> {{ $item->storage_location }}
                    </div>
                @endif
            </div>
        </div>

        <div class="hs-card">
            <div class="hs-card-head">
                <h3 class="hs-card-title">Thao tác xử lý</h3>
            </div>

            <div class="hs-card-body">
                @if($next)
                    <form class="hs-next-form" method="POST" action="{{ route('hr.document-handovers.status', $item->id) }}">
                        @csrf
                        <input type="hidden" name="status" value="{{ $next }}">

                        <div>
                            <label style="font-size:12px;font-weight:950;color:#475569;margin-bottom:7px;display:block">Bước tiếp theo</label>
                            <div class="hs-pill status-{{ $next }}">{{ $statuses[$next] }}</div>
                        </div>

                        @if($next === 'assigned')
                            <div>
                                <label style="font-size:12px;font-weight:950;color:#475569;margin-bottom:7px;display:block">Giao cho ai?</label>
                                <select class="hs-select" name="assigned_to">
                                    <option value="">Chọn người nhận HS</option>
                                    @foreach($users as $user)
                                        <option value="{{ $user->id }}">{{ $user->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                        @endif

                        @if($next === 'archived')
                            <div>
                                <label style="font-size:12px;font-weight:950;color:#475569;margin-bottom:7px;display:block">Vị trí lưu trữ</label>
                                <input class="hs-input" name="storage_location" placeholder="VD: Tủ HS A, ngăn 2">
                            </div>
                        @else
                            <div>
                                <label style="font-size:12px;font-weight:950;color:#475569;margin-bottom:7px;display:block">Ghi chú khi chuyển bước</label>
                                <textarea class="hs-textarea" name="note" placeholder="Nhập ghi chú xử lý..."></textarea>
                            </div>
                        @endif

                        <button class="hs-btn dark" type="submit">Chuyển sang: {{ $statuses[$next] }}</button>
                    </form>
                @else
                    <div class="hs-empty" style="padding:24px">
                        Hồ sơ đã kết thúc quy trình.
                    </div>
                @endif

                <div style="height:1px;background:#e2e8f0;margin:16px 0"></div>

                <form method="POST" action="{{ route('hr.document-handovers.destroy', $item->id) }}" onsubmit="return confirm('Xóa hồ sơ này?')">
                    @csrf
                    @method('DELETE')
                    <button class="hs-btn danger" type="submit">Xóa hồ sơ</button>
                </form>
            </div>
        </div>


        <div class="hs-card" style="grid-column:1 / -1">
            <div class="hs-card-head">
                <h3 class="hs-card-title">File hồ sơ đính kèm</h3>
                <button type="button" class="hs-btn soft tiny" onclick="openHsModal('uploadFileModal')">+ Thêm file</button>
            </div>

            <div class="hs-card-body">
                <div class="hs-file-list">
                    @forelse($files as $file)
                        @php
                            $ext = strtolower(pathinfo($file->original_name ?: $file->path, PATHINFO_EXTENSION));
                            $size = $file->size ? number_format($file->size / 1024, 1) . ' KB' : '';
                        @endphp

                        <div class="hs-file">
                            <div class="hs-file-ic">{{ strtoupper($ext ?: 'FILE') }}</div>
                            <div>
                                <div class="hs-file-name">{{ $file->original_name ?: basename($file->path) }}</div>
                                <div class="hs-file-meta">
                                    {{ $file->mime_type ?: 'Tệp đính kèm' }}
                                    @if($size) • {{ $size }} @endif
                                    @if($file->uploader_name) • Upload bởi {{ $file->uploader_name }} @endif
                                    • {{ \Carbon\Carbon::parse($file->created_at)->format('d/m/Y H:i') }}
                                </div>
                            </div>

                            <div class="hs-file-actions">
                                <button type="button"
                                        class="hs-btn success tiny"
                                        onclick="openHsPreview('{{ route('hr.document-handovers.files.preview', [$item->id, $file->id]) }}', '{{ addslashes($file->original_name ?: basename($file->path)) }}')">
                                    Xem trước
                                </button>

                                <a class="hs-btn soft tiny" href="{{ route('hr.document-handovers.files.download', [$item->id, $file->id]) }}">
                                    Tải xuống
                                </a>

                                <form method="POST" action="{{ route('hr.document-handovers.files.delete', [$item->id, $file->id]) }}" onsubmit="return confirm('Xóa file này?')">
                                    @csrf
                                    @method('DELETE')
                                    <button class="hs-btn danger tiny" type="submit">Xóa</button>
                                </form>
                            </div>
                        </div>
                    @empty
                        <div class="hs-empty" style="padding:24px">Chưa có file hồ sơ đính kèm.</div>
                    @endforelse
                </div>
            </div>
        </div>


        <div class="hs-card" style="grid-column:1 / -1">
            <div class="hs-card-head">
                <h3 class="hs-card-title">Lịch sử xử lý</h3>
            </div>

            <div class="hs-card-body">
                <div class="hs-history">
                    @forelse($histories as $his)
                        <div class="hs-history-item">
                            <div class="hs-history-dot"></div>
                            <div class="hs-history-title">{{ $his->action ?: ($statuses[$his->status] ?? $his->status) }}</div>
                            <div class="hs-history-meta">
                                {{ $his->user_name ?: 'Hệ thống' }} • {{ \Carbon\Carbon::parse($his->created_at)->format('d/m/Y H:i') }}
                            </div>
                            @if($his->note)
                                <div class="hs-history-note">{{ $his->note }}</div>
                            @endif
                        </div>
                    @empty
                        <div class="hs-empty" style="padding:24px">Chưa có lịch sử xử lý.</div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
</div>

<div class="hs-modal" id="editHsModal">
    <div class="hs-modal-box">
        <div class="hs-modal-head">
            <h3 class="hs-modal-title">Sửa hồ sơ {{ $item->code }}</h3>
            <button class="hs-close" type="button" onclick="closeHsModal('editHsModal')">×</button>
        </div>
        <form class="hs-form" method="POST" action="{{ route('hr.document-handovers.update', $item->id) }}" enctype="multipart/form-data">
            @csrf
            @method('PUT')
            @include('hr.document-handovers._form', ['item' => $item, 'users' => $users, 'priorities' => $priorities])
        </form>
    </div>
</div>

<script>
function openHsModal(id){const el=document.getElementById(id);if(!el)return;el.classList.add('show');document.body.style.overflow='hidden'}
function closeHsModal(id){const el=document.getElementById(id);if(!el)return;el.classList.remove('show');document.body.style.overflow=''}
function openHsPreview(url,title){const modal=document.getElementById('hsPreviewModal');const frame=document.getElementById('hsPreviewFrame');const ttl=document.getElementById('hsPreviewTitle');if(!modal||!frame)return;ttl.textContent=title||'Xem trước file';frame.src=url;modal.classList.add('show');document.body.style.overflow='hidden'}
function closeHsPreview(){const modal=document.getElementById('hsPreviewModal');const frame=document.getElementById('hsPreviewFrame');if(!modal||!frame)return;modal.classList.remove('show');frame.src='about:blank';document.body.style.overflow=''}
document.addEventListener('keydown',e=>{if(e.key==='Escape'){document.querySelectorAll('.hs-modal.show,.hs-preview-modal.show').forEach(el=>el.classList.remove('show'));const f=document.getElementById('hsPreviewFrame');if(f)f.src='about:blank';document.body.style.overflow=''}});
document.addEventListener('click',e=>{if(e.target.classList&&e.target.classList.contains('hs-modal')){e.target.classList.remove('show');document.body.style.overflow=''}if(e.target.classList&&e.target.classList.contains('hs-preview-modal')){closeHsPreview()}});
</script>
@endsection
