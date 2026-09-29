@extends('layouts.app')

@section('content')
@push('styles')
    @vite('resources/css/crm-marketing-plans.css')
@endpush
@php
    $monthValue = !empty($plan->month ?? null) ? \Carbon\Carbon::parse($plan->month)->format('Y-m') : (!empty($plan->start_date ?? null) ? \Carbon\Carbon::parse($plan->start_date)->format('Y-m') : now()->format('Y-m'));
    $statusValue = $plan->status ?? 'active';
    $nameValue = $plan->name ?? '';
    $budgetValue = $plan->total_budget ?? $plan->budget_plan_total ?? 0;
    $leadValue = $plan->target_leads ?? 0;
    $roasValue = $plan->target_roas ?? 0;
    $noteValue = $plan->note ?? $plan->objective ?? '';
@endphp

<div class="egomp-page">
    <div class="egomp-shell">
        <div class="egomp-hero">
            <div class="egomp-top">
                <div>
                    <span class="egomp-badge">🛠 Chỉnh sửa</span>
                    <h1>Sửa kế hoạch Marketing</h1>
                    <p>Form edit giờ follow cùng style với form tạo mới.</p>
                </div>
                <div class="egomp-actions">
                    <a href="{{ route('marketing.plan.show',$plan->id) }}" class="egomp-btn egomp-btn-soft">← Quay lại chi tiết</a>
                </div>
            </div>
        </div>

        @if($errors->any())
            <div class="alert alert-danger">{{ $errors->first() }}</div>
        @endif

        <form method="POST" action="{{ route('marketing.plan.update',$plan->id) }}" enctype="multipart/form-data">
            @csrf
            @method('PUT')

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

            <div class="egomp-card">
                <div class="egomp-card-head">File hiện có</div>
                <div class="egomp-card-body">
                    @forelse($attachments as $file)
                        <div class="egomp-file">
                            <div class="egomp-file-left">
                                <div class="egomp-file-name">{{ $file->file_name }}</div>
                                <div class="egomp-muted">
                                    {{ $file->file_mime ?: 'file' }} · {{ number_format(($file->file_size ?? 0) / 1024, 1) }} KB
                                </div>
                            </div>
                            <div class="egomp-actions">
                                <a href="{{ route('marketing.plan.file',$file->id) }}" target="_blank" class="egomp-btn egomp-btn-primary">Xem file</a>
                                <label class="egomp-btn egomp-btn-danger" style="cursor:pointer;">
                                    <input type="checkbox" name="delete_files[]" value="{{ $file->id }}" style="margin-right:6px;">
                                    Xoá
                                </label>
                            </div>
                        </div>
                    @empty
                        <div class="egomp-empty">Chưa có file đính kèm.</div>
                    @endforelse
                </div>
            </div>

            <div class="egomp-actions" style="justify-content:flex-end;">
                <a href="{{ route('marketing.plan.show',$plan->id) }}" class="egomp-btn egomp-btn-soft">Huỷ</a>
                <button type="submit" class="egomp-btn egomp-btn-primary">Lưu thay đổi</button>
            </div>
        </form>
    </div>
</div>
@endsection