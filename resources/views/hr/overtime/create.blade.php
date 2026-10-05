@extends('layouts.app')

@section('title', isset($overtime) ? 'Sửa đơn tăng ca' : 'Đăng ký tăng ca')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/ego-attendance-promax.css') }}?v={{ filemtime(public_path('css/ego-attendance-promax.css')) }}">
    <style>
        #egoAttendancePromax .ot-form{padding:18px 20px;display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:14px}
        #egoAttendancePromax .ot-field{display:flex;flex-direction:column;gap:5px}
        #egoAttendancePromax .ot-field--full{grid-column:1/-1}
        #egoAttendancePromax .ot-field label{color:#607b89;font-size:11px;font-weight:800;text-transform:uppercase;letter-spacing:.04em}
        #egoAttendancePromax .ot-field input,#egoAttendancePromax .ot-field select,#egoAttendancePromax .ot-field textarea{width:100%;border:1px solid #d2e2e8;border-radius:12px;padding:11px 12px;outline:0;font-size:13px;background:#fff}
        #egoAttendancePromax .ot-field input:focus,#egoAttendancePromax .ot-field select:focus,#egoAttendancePromax .ot-field textarea:focus{border-color:#09a7b2;box-shadow:0 0 0 3px rgba(9,167,178,.12)}
        #egoAttendancePromax .ot-field small{color:#78909c;font-size:11px;line-height:1.45}
        #egoAttendancePromax .ot-field .is-invalid{border-color:#e05266}
        #egoAttendancePromax .ot-hours{padding:12px 14px;border:1px dashed #b9d7df;border-radius:12px;background:#f7fbfc;color:#1f4a5e;font-weight:800;font-size:13px}
        #egoAttendancePromax .ot-form-actions{grid-column:1/-1;display:flex;justify-content:flex-end;gap:10px}
        @media(max-width:700px){#egoAttendancePromax .ot-form{grid-template-columns:1fr}}
    </style>
@endpush

@section('content')
@php
    $overtime = $overtime ?? null;
    $isEdit = $overtime !== null;
@endphp
<div id="egoAttendancePromax">
    <div class="at-shell" style="max-width:980px">
        @if(session('error'))
            <div class="at-alert at-alert--danger"><i class="bi bi-exclamation-circle"></i>{{ session('error') }}</div>
        @endif
        @if($errors->any())
            <div class="at-alert at-alert--danger">
                <i class="bi bi-exclamation-circle"></i>
                <div>@foreach($errors->all() as $error)<div>{{ $error }}</div>@endforeach</div>
            </div>
        @endif

        <header class="at-hero at-panel">
            <div class="at-heading">
                <div class="at-heading__icon"><i class="bi bi-moon-stars"></i></div>
                <div>
                    <span>CHẤM CÔNG · TĂNG CA</span>
                    <h1>{{ $isEdit ? 'Sửa đơn tăng ca' : 'Đăng ký tăng ca' }}</h1>
                    <p>Gửi đơn để quản lý / trưởng phòng / HR duyệt. Đơn được duyệt sẽ ghi vào chấm công và cộng vào tổng giờ công.</p>
                </div>
            </div>
            <div class="at-actions">
                <a class="at-btn at-btn--glass" href="{{ route('hr.overtime.index', ['tab' => 'mine']) }}"><i class="bi bi-list-ul"></i>Đơn tăng ca của tôi</a>
                <a class="at-btn at-btn--glass" href="{{ route('hr.attendance.my') }}"><i class="bi bi-fingerprint"></i>Chấm công của tôi</a>
            </div>
        </header>

        <section class="at-panel">
            <form method="POST" action="{{ $isEdit ? route('hr.overtime.update', $overtime) : route('hr.overtime.store') }}" class="ot-form" data-overtime-form>
                @csrf
                @if($isEdit)
                    @method('PUT')
                @endif

                <div class="ot-field">
                    <label for="ot-date">Ngày tăng ca *</label>
                    <input id="ot-date" type="date" name="overtime_date" value="{{ old('overtime_date', $isEdit ? $overtime->overtime_date->toDateString() : now()->toDateString()) }}" required>
                </div>
                <div class="ot-field">
                    <label for="ot-start">Từ giờ *</label>
                    <input id="ot-start" type="time" name="start_time" value="{{ old('start_time', $isEdit ? $overtime->start_at->format('H:i') : '18:00') }}" required data-ot-start>
                </div>
                <div class="ot-field">
                    <label for="ot-end">Đến giờ *</label>
                    <input id="ot-end" type="time" name="end_time" value="{{ old('end_time', $isEdit ? $overtime->end_at->format('H:i') : '20:00') }}" required data-ot-end>
                    <small>Giờ kết thúc nhỏ hơn giờ bắt đầu = tăng ca qua đêm. Tối đa 16 giờ/lần.</small>
                </div>

                <div class="ot-field ot-field--full">
                    <div class="ot-hours" data-ot-hours>Số giờ tăng ca: 2 giờ</div>
                </div>

                <div class="ot-field ot-field--full">
                    <label for="ot-approver">Người duyệt *</label>
                    <select id="ot-approver" name="approver_id" required class="@error('approver_id') is-invalid @enderror">
                        <option value="">— Chọn quản lý / trưởng phòng / HR —</option>
                        @foreach($approvers as $approver)
                            <option value="{{ $approver->id }}" @selected((int) old('approver_id', $overtime?->approver_id) === (int) $approver->id)>
                                {{ $approver->name }}@if($approver->email) · {{ $approver->email }}@endif
                            </option>
                        @endforeach
                    </select>
                    <small>Không thể chọn chính mình. HR / Admin vẫn xem và duyệt được mọi đơn.</small>
                </div>

                <div class="ot-field ot-field--full">
                    <label for="ot-reason">Lý do tăng ca *</label>
                    <textarea id="ot-reason" name="reason" rows="4" required minlength="5" maxlength="5000" placeholder="VD: xử lý đơn hàng gấp, hỗ trợ công trình, trực kho...">{{ old('reason', $overtime?->reason) }}</textarea>
                </div>

                <div class="ot-form-actions">
                    <a class="at-btn at-btn--light" href="{{ route('hr.overtime.index', ['tab' => 'mine']) }}">Huỷ</a>
                    <button type="submit" class="at-btn at-btn--primary">@if($isEdit)<i class="bi bi-save"></i>Lưu thay đổi @else<i class="bi bi-send"></i>Gửi đơn tăng ca @endif</button>
                </div>
            </form>
        </section>
    </div>
</div>
@endsection

@push('scripts')
<script>
    (function () {
        var form = document.querySelector('[data-overtime-form]');
        if (!form) { return; }
        var start = form.querySelector('[data-ot-start]');
        var end = form.querySelector('[data-ot-end]');
        var out = form.querySelector('[data-ot-hours]');
        function minutes(v) { var p = (v || '').split(':'); return p.length === 2 ? (+p[0]) * 60 + (+p[1]) : null; }
        function update() {
            var s = minutes(start.value), e = minutes(end.value);
            if (s === null || e === null) { out.textContent = 'Số giờ tăng ca: —'; return; }
            var diff = e > s ? e - s : e + 1440 - s;
            var h = Math.round(diff / 60 * 100) / 100;
            out.textContent = 'Số giờ tăng ca: ' + String(h).replace('.', ',') + ' giờ' + (e <= s ? ' (qua đêm)' : '') + (diff > 960 ? ' — vượt 16 giờ, không hợp lệ' : '');
        }
        start.addEventListener('input', update);
        end.addEventListener('input', update);
        update();
    })();
</script>
@endpush
