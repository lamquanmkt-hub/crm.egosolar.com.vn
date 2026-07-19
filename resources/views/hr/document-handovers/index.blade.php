@extends('layouts.app')

@section('content')
@include('hr.document-handovers._style')

<div class="hs-page">
    @if(session('success'))
        <div class="alert alert-success" style="border-radius:16px;font-weight:800">{{ session('success') }}</div>
    @endif

    <div class="hs-hero">
        <div class="hs-hero-inner">
            <div>
                <div class="hs-kicker">📁 Internal Document Workflow</div>
                <h1 class="hs-title">Quy trình giao nhận hồ sơ</h1>
                <p class="hs-sub">Trang tổng quan chỉ hiển thị ý chính. Bấm xem chi tiết để xử lý flow: tạo, giao, nhận, gửi, trả, hoàn tất và lưu trữ hồ sơ.</p>
            </div>
            <button type="button" class="hs-btn" onclick="openHsModal('createHsModal')">+ Tạo hồ sơ mới</button>
        </div>
    </div>

    <div class="hs-stat-grid">
        <div class="hs-stat"><div class="hs-stat-label">Tổng hồ sơ</div><div class="hs-stat-num">{{ $stats['total'] }}</div></div>
        <div class="hs-stat"><div class="hs-stat-label">Đang xử lý</div><div class="hs-stat-num">{{ $stats['processing'] }}</div></div>
        <div class="hs-stat"><div class="hs-stat-label">Hoàn tất</div><div class="hs-stat-num">{{ $stats['completed'] }}</div></div>
        <div class="hs-stat"><div class="hs-stat-label">Đã lưu trữ</div><div class="hs-stat-num">{{ $stats['archived'] }}</div></div>
    </div>

    <form class="hs-toolbar" method="GET">
        <div>
            <strong>Danh sách hồ sơ</strong>
            <div class="hs-muted">Chỉ xem nhanh mã, tên, người giữ, trạng thái và hạn xử lý.</div>
        </div>

        <div class="hs-filter">
            <input class="hs-input" name="q" value="{{ request('q') }}" placeholder="Tìm mã, tên HS, khách hàng...">

            <select class="hs-select" name="status">
                <option value="">Tất cả trạng thái</option>
                @foreach($statuses as $key => $label)
                    <option value="{{ $key }}" @selected(request('status') === $key)>{{ $label }}</option>
                @endforeach
            </select>

            <select class="hs-select" name="priority">
                <option value="">Tất cả ưu tiên</option>
                @foreach($priorities as $key => $label)
                    <option value="{{ $key }}" @selected(request('priority') === $key)>{{ $label }}</option>
                @endforeach
            </select>

            <button class="hs-btn soft" type="submit">Lọc</button>
        </div>
    </form>

    <div class="hs-table-card">
        @if($items->count())
            <table class="hs-table">
                <thead>
                    <tr>
                        <th>Mã HS</th>
                        <th>Thông tin hồ sơ</th>
                        <th>Người đang giữ</th>
                        <th>Hạn xử lý</th>
                        <th>Trạng thái</th>
                        <th>Ưu tiên</th>
                        <th style="text-align:right">Thao tác</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($items as $item)
                        <tr>
                            <td><span class="hs-code">{{ $item->code }}</span></td>
                            <td>
                                <div class="hs-name">{{ $item->title }}</div>
                                <div class="hs-muted">
                                    {{ $item->document_type ?: 'Chưa phân loại' }}
                                    @if($item->customer_name) • {{ $item->customer_name }} @endif
                                </div>
                            </td>
                            <td>
                                <strong>{{ $item->current_holder ?: ($item->assignee_name ?: '—') }}</strong>
                                <div class="hs-muted">{{ $item->department_name ?: '—' }}</div>
                            </td>
                            <td>
                                @if($item->due_date)
                                    {{ \Carbon\Carbon::parse($item->due_date)->format('d/m/Y') }}
                                @else
                                    —
                                @endif
                            </td>
                            <td><span class="hs-pill status-{{ $item->status }}">{{ $statuses[$item->status] ?? $item->status }}</span></td>
                            <td><span class="hs-pill pri-{{ $item->priority }}">{{ $priorities[$item->priority] ?? $item->priority }}</span></td>
                            <td style="text-align:right">
                                <div style="display:flex;gap:7px;justify-content:flex-end;flex-wrap:wrap">
                                    <a class="hs-btn dark tiny" href="{{ route('hr.document-handovers.show', $item->id) }}">Chi tiết</a>
                                    <button type="button" class="hs-btn soft tiny" onclick="openHsModal('editHsModal{{ $item->id }}')">Sửa</button>
                                    <form method="POST" action="{{ route('hr.document-handovers.destroy', $item->id) }}" onsubmit="return confirm('Xóa hồ sơ này?')" style="display:inline">
                                        @csrf
                                        @method('DELETE')
                                        <button class="hs-btn danger tiny" type="submit">Xóa</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @else
            <div class="hs-empty">Chưa có hồ sơ nào. Bấm <strong>+ Tạo hồ sơ mới</strong> để bắt đầu.</div>
        @endif
    </div>

    <div style="margin-top:16px">{{ $items->links() }}</div>
</div>

<div class="hs-modal" id="createHsModal">
    <div class="hs-modal-box">
        <div class="hs-modal-head">
            <h3 class="hs-modal-title">Tạo hồ sơ mới</h3>
            <button class="hs-close" type="button" onclick="closeHsModal('createHsModal')">×</button>
        </div>
        <form class="hs-form" method="POST" action="{{ route('hr.document-handovers.store') }}" enctype="multipart/form-data">
            @csrf
            @include('hr.document-handovers._form', ['item' => null, 'users' => $users, 'priorities' => $priorities])
        </form>
    </div>
</div>

<script>
function openHsModal(id){const el=document.getElementById(id);if(!el)return;el.classList.add('show');document.body.style.overflow='hidden'}
function closeHsModal(id){const el=document.getElementById(id);if(!el)return;el.classList.remove('show');document.body.style.overflow=''}
document.addEventListener('keydown',e=>{if(e.key==='Escape'){document.querySelectorAll('.hs-modal.show').forEach(el=>el.classList.remove('show'));document.body.style.overflow=''}});
document.addEventListener('click',e=>{if(e.target.classList&&e.target.classList.contains('hs-modal')){e.target.classList.remove('show');document.body.style.overflow=''}});
</script>
@endsection
