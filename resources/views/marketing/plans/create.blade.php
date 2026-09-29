@extends('layouts.app')

@section('content')
@push('styles')
    @vite('resources/css/crm-marketing-plans.css')
@endpush
<div class="egomp-page">
    <div class="egomp-shell">
        <div class="egomp-hero">
            <div class="egomp-top">
                <div>
                    <span class="egomp-badge">✨ Tạo mới</span>
                    <h1>Tạo kế hoạch Marketing</h1>
                    <p>Form mới hoàn toàn. Ưu tiên gọn, dễ nhập, upload file nhanh.</p>
                </div>
                <div class="egomp-actions">
                    <a href="{{ route('marketing.plan.overview') }}" class="egomp-btn egomp-btn-soft">← Quay lại</a>
                </div>
            </div>
        </div>

        @if($errors->any())
            <div class="alert alert-danger">{{ $errors->first() }}</div>
        @endif

        <form method="POST" action="{{ route('marketing.plan.store') }}" enctype="multipart/form-data">
            @csrf

            <div class="egomp-card">
                <div class="egomp-card-head">Thông tin kế hoạch</div>
                <div class="egomp-card-body">
                    <div class="egomp-grid2">
    <div class="egomp-field">
        <label>Tháng kế hoạch *</label>
        <input type="month" name="month" class="egomp-control" value="{{ old('month', $monthValue ?? now()->format('Y-m')) }}" required>
    </div>
    <div class="egomp-field">
        <label>Trạng thái</label>
        <select name="status" class="egomp-control">
            @foreach(['draft'=>'Nháp','active'=>'Active','approved'=>'Đã duyệt','closed'=>'Đóng'] as $k=>$v)
                <option value="{{ $k }}" @selected(old('status', $statusValue ?? 'active')===$k)>{{ $v }}</option>
            @endforeach
        </select>
    </div>

    <div class="egomp-field egomp-full">
        <label>Tên kế hoạch</label>
        <input type="text" name="name" class="egomp-control" value="{{ old('name', $nameValue ?? '') }}" placeholder="Ví dụ: Kế hoạch Marketing tháng 05/2026">
    </div>

    <div class="egomp-field">
        <label>Ngân sách</label>
        <input type="number" min="0" step="1" name="total_budget" class="egomp-control" value="{{ old('total_budget', $budgetValue ?? 0) }}">
    </div>

    <div class="egomp-field">
        <label>Target Leads</label>
        <input type="number" min="0" step="1" name="target_leads" class="egomp-control" value="{{ old('target_leads', $leadValue ?? 0) }}">
    </div>

    <div class="egomp-field">
        <label>Target ROAS</label>
        <input type="number" min="0" step="0.01" name="target_roas" class="egomp-control" value="{{ old('target_roas', $roasValue ?? 0) }}">
    </div>

    <div class="egomp-field egomp-full">
        <label>Ghi chú</label>
        <textarea name="note" class="egomp-control" placeholder="Mô tả ngắn cho kế hoạch...">{{ old('note', $noteValue ?? '') }}</textarea>
    </div>
</div>

<div class="egomp-upload" style="margin-top:14px;">
    <div style="font-size:34px;">📎</div>
    <h4>Upload file kế hoạch</h4>
    <p>Hỗ trợ nhiều file. Có thể xem trực tiếp trong CRM mà không cần tải về.</p>
    <input type="file" name="attachments[]" multiple class="form-control">
</div>
                </div>
            </div>

            <div class="egomp-actions" style="justify-content:flex-end;">
                <a href="{{ route('marketing.plan.overview') }}" class="egomp-btn egomp-btn-soft">Huỷ</a>
                <button type="submit" class="egomp-btn egomp-btn-primary">Lưu kế hoạch</button>
            </div>
        </form>
    </div>
</div>
@endsection