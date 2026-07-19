@extends('layouts.app')

@section('content')
<div class="container-fluid py-3 ego-leads">

    {{-- Header --}}
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
        <div>
            <div class="d-flex align-items-center gap-2">
                <div class="ego-dot"></div>
                <h4 class="mb-0">Danh sách Lead</h4>
                <span class="badge ego-badge-soft">Marketing</span>
            </div>
            <div class="text-muted small mt-1">
                Quản lý lead từ Ads, import theo tuần. Tìm kiếm, lọc, xem nhanh chỉ số.
            </div>
        </div>

        <div class="d-flex align-items-center gap-2">
            <a href="{{ route('marketing.leads.upload') }}" class="btn btn-primary ego-btn">
                <i class="bi bi-upload me-1"></i> Upload Lead (CSV)
            </a>
        </div>
    </div>

    {{-- Flash --}}
    @if(session('success'))
        <div class="alert alert-success border-0 shadow-sm">
            <i class="bi bi-check-circle me-1"></i> {{ session('success') }}
        </div>
    @endif

    {{-- Stats (nhẹ, chạy ngay cả khi không có data) --}}
    @php
        $total = $leads->total() ?? 0;
        $newCount = 0;
        $wonCount = 0;
        $lostCount = 0;

        foreach($leads as $l){
            if(($l->status ?? '') === \App\Models\Marketing\MarketingLead::STATUS_NEW) $newCount++;
            if(($l->status ?? '') === \App\Models\Marketing\MarketingLead::STATUS_WON) $wonCount++;
            if(($l->status ?? '') === \App\Models\Marketing\MarketingLead::STATUS_LOST) $lostCount++;
        }
    @endphp

    <div class="row g-3 mb-3">
        <div class="col-12 col-md-6 col-xl-3">
            <div class="card ego-card">
                <div class="card-body">
                    <div class="ego-kpi-title">Tổng lead</div>
                    <div class="ego-kpi-value">{{ number_format($total) }}</div>
                    <div class="ego-kpi-sub">Tổng bản ghi trong hệ thống</div>
                </div>
            </div>
        </div>
        <div class="col-12 col-md-6 col-xl-3">
            <div class="card ego-card">
                <div class="card-body">
                    <div class="ego-kpi-title">Mới (trang này)</div>
                    <div class="ego-kpi-value">{{ number_format($newCount) }}</div>
                    <div class="ego-kpi-sub">Đang hiển thị theo phân trang</div>
                </div>
            </div>
        </div>
        <div class="col-12 col-md-6 col-xl-3">
            <div class="card ego-card">
                <div class="card-body">
                    <div class="ego-kpi-title">Chốt / Hủy (trang này)</div>
                    <div class="ego-kpi-value">{{ number_format($wonCount) }} / {{ number_format($lostCount) }}</div>
                    <div class="ego-kpi-sub">Theo trạng thái</div>
                </div>
            </div>
        </div>
        <div class="col-12 col-md-6 col-xl-3">
            <div class="card ego-card">
                <div class="card-body">
                    <div class="ego-kpi-title">Gợi ý</div>
                    <div class="ego-kpi-sub mb-0">
                        Bạn có thể import theo tuần và dùng filter để xem theo nguồn/ngày.
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Filter bar (UI trước – chưa cần controller filter vẫn chạy bình thường) --}}
    <div class="card ego-card mb-3">
        <div class="card-body">
            <form class="row g-2 align-items-end" method="GET" action="{{ route('marketing.leads.index') }}">
                <div class="col-12 col-md-4">
                    <label class="form-label mb-1">Tìm kiếm</label>
                    <div class="input-group">
                        <span class="input-group-text bg-white"><i class="bi bi-search"></i></span>
                        <input type="text" name="q" value="{{ request('q') }}" class="form-control"
                               placeholder="Tên, email, SĐT, campaign...">
                    </div>
                </div>

                <div class="col-12 col-md-3">
                    <label class="form-label mb-1">Trạng thái</label>
                    <select name="status" class="form-select">
                        <option value="">Tất cả</option>
                        @foreach(\App\Models\Marketing\MarketingLead::statusOptions() as $k=>$v)
                            <option value="{{ $k }}" {{ request('status')===$k ? 'selected' : '' }}>{{ $v }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="col-12 col-md-3">
                    <label class="form-label mb-1">Nguồn</label>
                    <input type="text" name="source" value="{{ request('source') }}" class="form-control"
                           placeholder="VD: Facebook Ads">
                </div>

                <div class="col-12 col-md-2 d-flex gap-2">
                    <button class="btn btn-dark ego-btn w-100">
                        <i class="bi bi-funnel me-1"></i> Lọc
                    </button>
                    <a href="{{ route('marketing.leads.index') }}" class="btn btn-outline-secondary ego-btn w-100">
                        Xóa
                    </a>
                </div>
            </form>
        </div>
    </div>

    {{-- Table --}}
    <div class="card ego-card">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0 align-middle ego-table">
                    <thead>
                        <tr>
                            <th style="width:90px">ID</th>
                            <th>Tên</th>
                            <th style="width:140px">SĐT</th>
                            <th>Email</th>
                            <th style="width:160px">Nguồn</th>
                            <th>Campaign</th>
                            <th style="width:140px">Trạng thái</th>
                            <th style="width:130px">Ngày import</th>
                            <th style="width:160px">Người import</th>
                        </tr>
                    </thead>

                    <tbody>
                        @forelse($leads as $lead)
                            @php
                                $statusMap = \App\Models\Marketing\MarketingLead::statusOptions();
                                $statusText = $statusMap[$lead->status] ?? ($lead->status ?? '---');

                                $badgeClass = match($lead->status) {
                                    \App\Models\Marketing\MarketingLead::STATUS_NEW => 'ego-badge-new',
                                    \App\Models\Marketing\MarketingLead::STATUS_CONTACTED => 'ego-badge-contacted',
                                    \App\Models\Marketing\MarketingLead::STATUS_QUALIFIED => 'ego-badge-qualified',
                                    \App\Models\Marketing\MarketingLead::STATUS_WON => 'ego-badge-won',
                                    \App\Models\Marketing\MarketingLead::STATUS_LOST => 'ego-badge-lost',
                                    default => 'ego-badge-soft',
                                };
                            @endphp

                            <tr>
                                <td class="text-muted fw-semibold">#{{ $lead->id }}</td>

                                <td>
                                    <div class="fw-semibold">{{ $lead->name ?? '---' }}</div>
                                    @if($lead->note)
                                        <div class="text-muted small">{{ \Illuminate\Support\Str::limit($lead->note, 70) }}</div>
                                    @endif
                                </td>

                                <td>
                                    @if($lead->phone)
                                        <span class="ego-mono">{{ $lead->phone }}</span>
                                    @else
                                        <span class="text-muted">---</span>
                                    @endif
                                </td>

                                <td>
                                    @if($lead->email)
                                        <span class="ego-mono">{{ $lead->email }}</span>
                                    @else
                                        <span class="text-muted">---</span>
                                    @endif
                                </td>

                                <td>
                                    <span class="badge ego-badge-soft">{{ $lead->source ?? '---' }}</span>
                                </td>

                                <td>{{ $lead->campaign ?? '---' }}</td>

                                <td>
                                    <span class="badge {{ $badgeClass }}">{{ $statusText }}</span>
                                </td>

                                <td>
                                    {{ $lead->import_date ? \Carbon\Carbon::parse($lead->import_date)->format('d/m/Y') : '---' }}
                                </td>

                                <td>
                                    {{ $lead->importedBy?->name ?? '---' }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9" class="text-center text-muted py-5">
                                    <div class="mb-2"><i class="bi bi-inbox fs-3"></i></div>
                                    Chưa có lead nào. Bấm <b>Upload Lead (CSV)</b> để import.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        @if($leads->hasPages())
            <div class="card-footer bg-white">
                {{ $leads->links('pagination::bootstrap-5') }}
            </div>
        @endif
    </div>
</div>

{{-- CSS xịn (scope .ego-leads để không ảnh hưởng chỗ khác) --}}
<style>
.ego-leads .ego-card{
    border: 1px solid rgba(15,23,42,.08);
    border-radius: 16px;
    box-shadow: 0 10px 30px rgba(2,6,23,.06);
}
.ego-leads .ego-btn{
    border-radius: 12px;
    box-shadow: 0 10px 20px rgba(2,6,23,.10);
}
.ego-leads .ego-dot{
    width: 10px; height: 10px;
    border-radius: 999px;
    background: radial-gradient(circle at 30% 30%, #22d3ee, #0ea5e9);
    box-shadow: 0 0 0 6px rgba(34,211,238,.12);
}
.ego-leads .ego-badge-soft{
    background: rgba(2,132,199,.10);
    color: #075985;
    border: 1px solid rgba(2,132,199,.18);
    font-weight: 650;
}
.ego-leads .ego-kpi-title{
    color: rgba(15,23,42,.65);
    font-weight: 700;
    font-size: 12px;
    letter-spacing: .3px;
    text-transform: uppercase;
}
.ego-leads .ego-kpi-value{
    font-size: 28px;
    font-weight: 850;
    letter-spacing: .2px;
    margin-top: 4px;
}
.ego-leads .ego-kpi-sub{
    color: rgba(15,23,42,.55);
    font-size: 12px;
    margin-top: 2px;
}
.ego-leads .ego-table thead th{
    position: sticky;
    top: 0;
    background: linear-gradient(180deg, #ffffff, #f8fafc);
    z-index: 2;
    border-bottom: 1px solid rgba(15,23,42,.10) !important;
    font-weight: 800;
}
.ego-leads .ego-table td, .ego-leads .ego-table th{
    padding: 14px 14px;
    border-color: rgba(15,23,42,.08);
    vertical-align: middle;
}
.ego-leads .ego-mono{
    font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace;
    font-size: 12.5px;
}
.ego-leads .ego-badge-new{ background:#0ea5e9; border:1px solid rgba(14,165,233,.4); }
.ego-leads .ego-badge-contacted{ background:#6366f1; border:1px solid rgba(99,102,241,.4); }
.ego-leads .ego-badge-qualified{ background:#f59e0b; border:1px solid rgba(245,158,11,.4); }
.ego-leads .ego-badge-won{ background:#16a34a; border:1px solid rgba(22,163,74,.4); }
.ego-leads .ego-badge-lost{ background:#ef4444; border:1px solid rgba(239,68,68,.4); }
.ego-leads .ego-badge-new,
.ego-leads .ego-badge-contacted,
.ego-leads .ego-badge-qualified,
.ego-leads .ego-badge-won,
.ego-leads .ego-badge-lost{
    color: #fff;
    font-weight: 750;
}
</style>
@endsection
