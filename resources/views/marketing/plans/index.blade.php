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