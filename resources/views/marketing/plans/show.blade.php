@extends('layouts.app')

@section('content')
<style>
.egomp-page{padding:18px;background:#f4f7fb;min-height:100vh}
.egomp-shell{max-width:1240px;margin:0 auto}
.egomp-hero{
    position:relative;overflow:hidden;border-radius:26px;padding:24px 24px 22px;
    background:
      radial-gradient(800px 280px at 100% 0%, rgba(56,189,248,.18), transparent 60%),
      radial-gradient(700px 240px at 0% 0%, rgba(99,102,241,.15), transparent 60%),
      linear-gradient(135deg,#ffffff,#eef6ff 60%,#edfdf7);
    border:1px solid #dbeafe;
    box-shadow:0 20px 55px rgba(15,23,42,.08);
    margin-bottom:16px;
}
.egomp-hero h1{margin:0;font-size:36px;line-height:1.05;font-weight:950;color:#0f172a;letter-spacing:-.04em}
.egomp-hero p{margin:10px 0 0;color:#475569;font-size:14px}
.egomp-top{display:flex;justify-content:space-between;gap:14px;align-items:flex-start;flex-wrap:wrap}
.egomp-badge{
    display:inline-flex;align-items:center;gap:8px;padding:7px 12px;border-radius:999px;
    font-size:12px;font-weight:800;color:#0f172a;background:#ffffff;border:1px solid #e2e8f0;
    box-shadow:0 8px 24px rgba(15,23,42,.06)
}
.egomp-actions{display:flex;gap:10px;flex-wrap:wrap}
.egomp-btn{
    border:none;border-radius:14px;padding:10px 16px;font-size:13px;font-weight:850;
    text-decoration:none;display:inline-flex;align-items:center;gap:8px;transition:.2s ease;
}
.egomp-btn:hover{transform:translateY(-1px);text-decoration:none}
.egomp-btn-primary{color:#fff;background:linear-gradient(135deg,#0ea5e9,#2563eb);box-shadow:0 12px 30px rgba(37,99,235,.22)}
.egomp-btn-dark{color:#fff;background:linear-gradient(135deg,#0f172a,#1e293b)}
.egomp-btn-soft{color:#0f172a;background:#fff;border:1px solid #dbe5f0;box-shadow:0 10px 24px rgba(15,23,42,.06)}
.egomp-btn-danger{color:#fff;background:linear-gradient(135deg,#ef4444,#dc2626)}
.egomp-grid4{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:14px;margin-bottom:16px}
.egomp-stat{
    background:#fff;border:1px solid #e7edf5;border-radius:20px;padding:16px 16px 14px;
    box-shadow:0 12px 32px rgba(15,23,42,.05)
}
.egomp-stat-label{font-size:12px;color:#64748b;font-weight:800;text-transform:uppercase;letter-spacing:.04em}
.egomp-stat-value{margin-top:7px;font-size:28px;font-weight:950;color:#0f172a;line-height:1.1}
.egomp-stat-sub{margin-top:4px;font-size:12px;color:#64748b}
.egomp-card{
    background:#fff;border:1px solid #e7edf5;border-radius:22px;box-shadow:0 12px 32px rgba(15,23,42,.05);
    overflow:hidden;margin-bottom:16px;
}
.egomp-card-head{padding:16px 18px;border-bottom:1px solid #eef2f7;font-weight:950;color:#0f172a;font-size:16px}
.egomp-card-sub{display:block;font-size:12px;font-weight:600;color:#64748b;margin-top:4px}
.egomp-card-body{padding:18px}
.egomp-filters{display:grid;grid-template-columns:2fr 1fr 1fr auto;gap:12px;align-items:end}
.egomp-field label{display:block;margin-bottom:6px;font-size:12px;font-weight:850;color:#334155}
.egomp-control{
    width:100%;min-height:44px;border:1px solid #dbe5f0;border-radius:14px;padding:10px 13px;
    font-size:14px;background:#fff;color:#0f172a;outline:none;
}
.egomp-control:focus{border-color:#38bdf8;box-shadow:0 0 0 4px rgba(56,189,248,.10)}
textarea.egomp-control{min-height:130px;resize:vertical;line-height:1.7}
.egomp-grid2{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:14px}
.egomp-full{grid-column:1/-1}
.egomp-upload{
    border:2px dashed #bfdbfe;border-radius:20px;padding:24px;text-align:center;
    background:linear-gradient(180deg,#f8fbff,#f0f9ff)
}
.egomp-upload h4{margin:6px 0 8px;font-size:20px;font-weight:950;color:#0f172a}
.egomp-upload p{margin:0 0 14px;color:#64748b;font-size:13px}
.egomp-table{width:100%;border-collapse:separate;border-spacing:0}
.egomp-table thead th{
    background:#f8fafc;padding:14px 12px;font-size:12px;font-weight:900;color:#475569;
    border-bottom:1px solid #e9eef5;white-space:nowrap;text-transform:uppercase
}
.egomp-table tbody td{
    padding:16px 12px;border-bottom:1px solid #edf2f7;vertical-align:middle;font-size:14px;color:#0f172a
}
.egomp-name{font-weight:900;color:#0f172a}
.egomp-muted{color:#64748b;font-size:12px}
.egomp-status{
    display:inline-flex;align-items:center;gap:6px;border-radius:999px;padding:6px 10px;font-size:12px;font-weight:850
}
.egomp-status.active{background:#ecfeff;color:#0f766e}
.egomp-status.draft{background:#f1f5f9;color:#475569}
.egomp-status.approved{background:#ecfdf5;color:#15803d}
.egomp-status.closed{background:#fef2f2;color:#b91c1c}
.egomp-file{
    display:flex;align-items:center;justify-content:space-between;gap:12px;border:1px solid #e8eef6;
    border-radius:16px;padding:14px;margin-bottom:10px;background:#fbfdff
}
.egomp-file-left{min-width:0}
.egomp-file-name{font-size:14px;font-weight:900;color:#0f172a;word-break:break-word}
.egomp-preview{
    width:100%;height:720px;border:1px solid #e5edf6;border-radius:18px;background:#fff
}
.egomp-empty{
    text-align:center;padding:28px;border:1px dashed #dbe5f0;border-radius:18px;background:#f8fafc;color:#64748b
}
@media (max-width: 1100px){
    .egomp-grid4{grid-template-columns:repeat(2,minmax(0,1fr))}
    .egomp-filters{grid-template-columns:1fr 1fr}
}
@media (max-width: 768px){
    .egomp-page{padding:12px}
    .egomp-grid4,.egomp-grid2,.egomp-filters{grid-template-columns:1fr}
    .egomp-hero h1{font-size:28px}
}
</style>
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