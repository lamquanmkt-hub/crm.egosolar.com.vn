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
<div class="egomp-page">
    <div class="egomp-shell">
        <div class="egomp-hero">
            <div class="egomp-top">
                <div>
                    <span class="egomp-badge">🚀 Marketing Plan Pro</span>
                    <h1>Kế hoạch Marketing</h1>
                    <p>Giao diện mới hoàn toàn: gọn, hiện đại, ưu tiên upload file và thao tác nhanh.</p>
                </div>
                <div class="egomp-actions">
                    <a href="{{ route('marketing.plan.create') }}" class="egomp-btn egomp-btn-primary">＋ Tạo kế hoạch mới</a>
                </div>
            </div>
        </div>

        @php
            $totalBudget = collect($plans->items())->sum(function($p){
                return (float)($p->total_budget ?? $p->budget_plan_total ?? 0);
            });
            $totalLeads = collect($plans->items())->sum(function($p){
                return (float)($p->target_leads ?? 0);
            });
            $activePlans = collect($plans->items())->filter(function($p){
                return ($p->status ?? '') === 'active';
            })->count();
            $totalFiles = collect($plans->items())->sum(function($p) use ($attachmentCounts){
                return (int)($attachmentCounts[$p->id] ?? 0);
            });
        @endphp

        <div class="egomp-grid4">
            <div class="egomp-stat">
                <div class="egomp-stat-label">Ngân sách</div>
                <div class="egomp-stat-value">{{ number_format($totalBudget,0,',','.') }} đ</div>
                <div class="egomp-stat-sub">Tổng ngân sách trên danh sách đang hiển thị</div>
            </div>
            <div class="egomp-stat">
                <div class="egomp-stat-label">Target Leads</div>
                <div class="egomp-stat-value">{{ number_format($totalLeads,0,',','.') }}</div>
                <div class="egomp-stat-sub">Tổng target leads</div>
            </div>
            <div class="egomp-stat">
                <div class="egomp-stat-label">Kế hoạch active</div>
                <div class="egomp-stat-value">{{ $activePlans }}</div>
                <div class="egomp-stat-sub">Đang hoạt động</div>
            </div>
            <div class="egomp-stat">
                <div class="egomp-stat-label">Tệp đính kèm</div>
                <div class="egomp-stat-value">{{ $totalFiles }}</div>
                <div class="egomp-stat-sub">Số file trên danh sách này</div>
            </div>
        </div>

        @if(session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif

        <div class="egomp-card">
            <div class="egomp-card-head">
                Bộ lọc
                <span class="egomp-card-sub">Tìm kế hoạch theo từ khóa / tháng / trạng thái</span>
            </div>
            <div class="egomp-card-body">
                <form method="GET" class="egomp-filters">
                    <div class="egomp-field">
                        <label>Từ khóa</label>
                        <input type="text" name="q" value="{{ request('q') }}" class="egomp-control" placeholder="Tên kế hoạch, ghi chú...">
                    </div>
                    <div class="egomp-field">
                        <label>Tháng</label>
                        <input type="month" name="month" value="{{ request('month') }}" class="egomp-control">
                    </div>
                    <div class="egomp-field">
                        <label>Trạng thái</label>
                        <select name="status" class="egomp-control">
                            <option value="">Tất cả trạng thái</option>
                            @foreach(['draft'=>'Nháp','active'=>'Active','approved'=>'Đã duyệt','closed'=>'Đóng'] as $k=>$v)
                                <option value="{{ $k }}" @selected(request('status')===$k)>{{ $v }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="egomp-field">
                        <label>&nbsp;</label>
                        <div class="egomp-actions">
                            <button type="submit" class="egomp-btn egomp-btn-dark">Lọc</button>
                            <a href="{{ route('marketing.plan.overview') }}" class="egomp-btn egomp-btn-soft">Reset</a>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <div class="egomp-card">
            <div class="egomp-card-head">
                Danh sách kế hoạch
                <span class="egomp-card-sub">Quản lý kế hoạch theo tháng, có file đính kèm và nút xem trực tiếp</span>
            </div>
            <div class="egomp-card-body" style="padding:0;">
                <div class="table-responsive">
                    <table class="egomp-table">
                        <thead>
                            <tr>
                                <th>Tháng</th>
                                <th>Kế hoạch</th>
                                <th>Ngân sách</th>
                                <th>Leads</th>
                                <th>ROAS</th>
                                <th>File</th>
                                <th>Trạng thái</th>
                                <th style="text-align:right;">Hành động</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($plans as $plan)
                                @php
                                    $monthText = !empty($plan->month ?? null)
                                        ? \Carbon\Carbon::parse($plan->month)->format('m/Y')
                                        : (!empty($plan->start_date ?? null) ? \Carbon\Carbon::parse($plan->start_date)->format('m/Y') : '-');
                                    $budget = (float)($plan->total_budget ?? $plan->budget_plan_total ?? 0);
                                    $fileCount = (int)($attachmentCounts[$plan->id] ?? 0);
                                    $status = strtolower((string)($plan->status ?? 'draft'));
                                    $statusClass = in_array($status, ['draft','active','approved','closed']) ? $status : 'draft';
                                @endphp
                                <tr>
                                    <td><strong>{{ $monthText }}</strong></td>
                                    <td>
                                        <div class="egomp-name">{{ $plan->name ?? ('Kế hoạch #' . $plan->id) }}</div>
                                        <div class="egomp-muted">ID #{{ $plan->id }}</div>
                                    </td>
                                    <td><strong>{{ number_format($budget,0,',','.') }} đ</strong></td>
                                    <td>{{ number_format((float)($plan->target_leads ?? 0),0,',','.') }}</td>
                                    <td>{{ $plan->target_roas ?? 0 }}</td>
                                    <td><span class="egomp-status draft">{{ $fileCount }} file</span></td>
                                    <td><span class="egomp-status {{ $statusClass }}">{{ ucfirst($statusClass) }}</span></td>
                                    <td>
                                        <div class="egomp-actions" style="justify-content:flex-end;">
                                            <a href="{{ route('marketing.plan.show',$plan->id) }}" class="egomp-btn egomp-btn-primary">Xem</a>
                                            <a href="{{ route('marketing.plan.edit',$plan->id) }}" class="egomp-btn egomp-btn-soft">Sửa</a>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="8">
                                        <div class="egomp-empty">Chưa có kế hoạch nào.</div>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        {{ $plans->links() }}
    </div>
</div>
@endsection