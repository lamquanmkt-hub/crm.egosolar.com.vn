@extends('layouts.app')

@section('content')
@push('styles')
    @vite('resources/css/crm-marketing-plans.css')
@endpush
@php
    $monthText = !empty($plan->month ?? null) ? \Carbon\Carbon::parse($plan->month)->format('m/Y') : (!empty($plan->start_date ?? null) ? \Carbon\Carbon::parse($plan->start_date)->format('m/Y') : '-');
    $budget = (float)($plan->total_budget ?? $plan->budget_plan_total ?? 0);
    $firstAttachment = count($attachments) ? $attachments->first() : null;
@endphp

<div class="egomp-page">
    <div class="egomp-shell">
        <div class="egomp-hero">
            <div class="egomp-top">
                <div>
                    <span class="egomp-badge">📁 Chi tiết kế hoạch</span>
                    <h1>{{ $plan->name ?? ('Kế hoạch #' . $plan->id) }}</h1>
                    <p>Tháng {{ $monthText }} · Trạng thái: <strong>{{ $plan->status ?? 'draft' }}</strong></p>
                </div>
                <div class="egomp-actions">
                    <a href="{{ route('marketing.plan.overview') }}" class="egomp-btn egomp-btn-soft">← Quay lại</a>
                    <a href="{{ route('marketing.plan.edit',$plan->id) }}" class="egomp-btn egomp-btn-primary">Sửa kế hoạch</a>
                </div>
            </div>
        </div>

        @if(session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif

        <div class="egomp-grid4">
            <div class="egomp-stat">
                <div class="egomp-stat-label">Ngân sách</div>
                <div class="egomp-stat-value">{{ number_format($budget,0,',','.') }} đ</div>
                <div class="egomp-stat-sub">Ngân sách kế hoạch</div>
            </div>
            <div class="egomp-stat">
                <div class="egomp-stat-label">Target Leads</div>
                <div class="egomp-stat-value">{{ number_format((float)($plan->target_leads ?? 0),0,',','.') }}</div>
                <div class="egomp-stat-sub">Số leads mục tiêu</div>
            </div>
            <div class="egomp-stat">
                <div class="egomp-stat-label">Target ROAS</div>
                <div class="egomp-stat-value">{{ $plan->target_roas ?? 0 }}</div>
                <div class="egomp-stat-sub">Hiệu quả kỳ vọng</div>
            </div>
            <div class="egomp-stat">
                <div class="egomp-stat-label">File đính kèm</div>
                <div class="egomp-stat-value">{{ count($attachments) }}</div>
                <div class="egomp-stat-sub">Có thể xem trực tiếp</div>
            </div>
        </div>

        <div class="egomp-grid2">
            <div class="egomp-card">
                <div class="egomp-card-head">Ghi chú</div>
                <div class="egomp-card-body" style="white-space:pre-line;line-height:1.8;color:#334155;">
                    {{ $plan->note ?? $plan->objective ?? 'Chưa có ghi chú.' }}
                </div>
            </div>

            <div class="egomp-card">
                <div class="egomp-card-head">File kế hoạch</div>
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
                                <a href="{{ route('marketing.plan.file.preview',$file->id) . '?' . http_build_query(['plan_id' => $plan->id ?? null, 'name' => $file->original_name ?? $file->file_name ?? $file->filename ?? $file->name ?? $file->title ?? null]) /* EGO_PREVIEW_QUERY_NAME_PATCH */ }}" target="_blank" class="egomp-btn egomp-btn-primary">Xem</a>
                            </div>
                        </div>
                    @empty
                        <div class="egomp-empty">Chưa có file đính kèm.</div>
                    @endforelse
                </div>
            </div>
        </div>

        @if($firstAttachment)
            <div class="egomp-card">
                <div class="egomp-card-head">Xem nhanh file đầu tiên</div>
                <div class="egomp-card-body">
                    <iframe class="egomp-preview" src="{{ route('marketing.plan.file.preview',$firstAttachment->id) . '?' . http_build_query(['plan_id' => $plan->id ?? null, 'name' => $firstAttachment->original_name ?? $firstAttachment->file_name ?? $firstAttachment->filename ?? $firstAttachment->name ?? $firstAttachment->title ?? null]) /* EGO_PREVIEW_QUERY_NAME_PATCH */ }}"></iframe>
                </div>
            </div>
        @endif
    </div>
</div>
@endsection