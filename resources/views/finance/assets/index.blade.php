@extends('layouts.app')

@section('title', 'Tài sản')

@section('content')
@php
    $money = fn($v) => number_format((float) ($v ?? 0), 0, ',', '.') . ' đ';
    $dateText = function ($v) {
        if (!$v) return '—';
        try { return \Carbon\Carbon::parse($v)->format('d/m/Y'); } catch (\Throwable $e) { return $v; }
    };
    $numInput = function ($v) {
        if ($v === null || $v === '') return '';
        $n = (float) $v;
        return abs($n - round($n)) < 0.00001 ? (string) (int) round($n) : rtrim(rtrim(number_format($n, 2, '.', ''), '0'), '.');
    };
@endphp

<style>
    .asset-page {
        --ap-text: #0f172a;
        --ap-muted: #64748b;
        --ap-border: #e5edf7;
        --ap-soft: #f8fbff;
        --ap-blue: #2563eb;
        --ap-cyan: #0891b2;
        --ap-green: #059669;
        --ap-amber: #f59e0b;
        --ap-red: #e11d48;
        min-height: 100%;
        padding: 24px 28px 42px;
        background: linear-gradient(180deg, #f7fbff 0%, #f8fafc 45%, #fff 100%);
        color: var(--ap-text);
    }
    .ap-head {display:flex;align-items:flex-start;justify-content:space-between;gap:16px;margin-bottom:18px}
    .ap-title h1{margin:0;font-size:28px;font-weight:950;letter-spacing:-.04em}
    .ap-title p{margin:7px 0 0;color:var(--ap-muted);font-size:14px}
    .ap-actions{display:flex;gap:10px;flex-wrap:wrap;justify-content:flex-end}
    .ap-btn{height:42px;border:0;border-radius:14px;padding:0 16px;font-weight:900;display:inline-flex;align-items:center;justify-content:center;gap:8px;text-decoration:none;cursor:pointer;white-space:nowrap}
    .ap-btn-primary{background:#2563eb;color:#fff;box-shadow:0 14px 30px rgba(37,99,235,.22)}
    .ap-btn-green{background:#059669;color:#fff;box-shadow:0 14px 30px rgba(5,150,105,.18)}
    .ap-btn-light{background:#fff;color:#0f172a;border:1px solid var(--ap-border)}
    .ap-btn-red{background:#fff1f2;color:#be123c;border:1px solid #fecdd3}
    .ap-card{background:#fff;border:1px solid var(--ap-border);border-radius:22px;box-shadow:0 18px 45px rgba(15,23,42,.06);overflow:hidden;margin-bottom:16px}
    .ap-card-head{display:flex;align-items:center;justify-content:space-between;gap:12px;padding:16px 18px;border-bottom:1px solid #eef2f7;background:linear-gradient(90deg,#f0fdfa,#f8fbff)}
    .ap-card-head h2{font-size:17px;margin:0;font-weight:950}.ap-sub{color:var(--ap-muted);font-size:12px;font-weight:700}
    .ap-kpis{display:grid;grid-template-columns:repeat(5,minmax(0,1fr));gap:14px;margin-bottom:16px}
    .ap-kpi{background:#fff;border:1px solid var(--ap-border);border-radius:22px;padding:18px;box-shadow:0 16px 35px rgba(15,23,42,.05)}
    .ap-kpi .label{font-size:12px;font-weight:900;color:var(--ap-muted);text-transform:uppercase;letter-spacing:.03em}.ap-kpi .val{font-size:22px;font-weight:950;margin-top:8px;letter-spacing:-.035em}.ap-kpi .hint{font-size:12px;color:var(--ap-muted);margin-top:4px;font-weight:700}
    .ap-filter{display:grid;grid-template-columns:1.3fr 150px 180px 180px 155px auto auto;gap:10px;padding:16px}
    .ap-input,.ap-select,.ap-textarea{width:100%;border:1px solid var(--ap-border);border-radius:14px;background:#fff;color:#0f172a;font-weight:700;outline:none}.ap-input,.ap-select{height:44px;padding:0 13px}.ap-textarea{min-height:76px;padding:12px 13px;resize:vertical}
    .ap-form{padding:16px;display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:12px}.ap-form .span2{grid-column:span 2}.ap-form .span4{grid-column:span 4}.ap-field label{display:block;font-size:11px;text-transform:uppercase;color:var(--ap-muted);font-weight:950;margin:0 0 6px}.ap-field small{display:block;color:var(--ap-muted);font-weight:700;margin-top:5px}
    .ap-table-wrap{overflow:auto}.ap-table{width:100%;border-collapse:separate;border-spacing:0;min-width:1280px}.ap-table th{padding:13px 12px;background:#f8fbff;color:#475569;font-size:12px;text-transform:uppercase;text-align:left;border-bottom:1px solid var(--ap-border);white-space:nowrap}.ap-table td{padding:13px 12px;border-bottom:1px solid #edf2f7;vertical-align:middle}.ap-table tr:hover td{background:#fbfdff}.ap-code{font-weight:950;color:#0f172a}.ap-name{font-weight:950}.ap-muted{color:var(--ap-muted);font-size:12px;font-weight:700}.ap-money{font-weight:950;color:#0f766e;white-space:nowrap}.ap-badge{display:inline-flex;align-items:center;justify-content:center;border-radius:999px;padding:7px 10px;font-size:12px;font-weight:950;white-space:nowrap}.ap-badge.active{background:#dcfce7;color:#047857}.ap-badge.idle{background:#f1f5f9;color:#334155}.ap-badge.repair,.ap-badge.maintenance{background:#fef3c7;color:#b45309}.ap-badge.liquidated{background:#e0f2fe;color:#0369a1}.ap-badge.lost{background:#ffe4e6;color:#be123c}.ap-dot{width:9px;height:9px;border-radius:999px;display:inline-block;margin-right:7px}.ap-progress{height:8px;border-radius:999px;background:#eef2f7;overflow:hidden;min-width:96px}.ap-progress span{display:block;height:100%;border-radius:999px;background:linear-gradient(90deg,#0ea5e9,#10b981)}
    .ap-icon{width:38px;height:38px;border-radius:12px;border:1px solid #dbeafe;background:#fff;color:#2563eb;font-weight:950;cursor:pointer}.ap-icon.red{background:#fff1f2;color:#e11d48;border-color:#fecdd3}.ap-row-actions{display:flex;gap:7px;justify-content:flex-end}.ap-detail{display:none;background:#f8fbff}.ap-detail.show{display:table-row}.ap-detail-cell{padding:18px!important}.ap-detail-grid{display:grid;grid-template-columns:1.15fr .85fr;gap:16px}.ap-edit-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:10px}.ap-edit-grid .span3{grid-column:span 3}.ap-timeline{display:grid;gap:8px;max-height:360px;overflow:auto}.ap-event{border:1px solid var(--ap-border);border-radius:15px;padding:10px 12px;background:#fff}.ap-event-top{display:flex;align-items:center;justify-content:space-between;gap:10px}.ap-files{display:flex;flex-wrap:wrap;gap:8px;margin-top:10px}.ap-file{border:1px solid #dbeafe;border-radius:999px;padding:7px 10px;background:#fff;text-decoration:none;font-size:12px;font-weight:900;color:#2563eb}.ap-event-form{display:grid;grid-template-columns:150px 140px 130px 1fr 150px auto;gap:8px;margin-top:12px}.ap-category-form{display:grid;grid-template-columns:1fr 120px 130px 100px auto;gap:8px;padding:14px 16px;border-top:1px solid #eef2f7;background:#fbfdff}.ap-alert{border-radius:14px;padding:12px 14px;font-weight:800;margin-bottom:14px}.ap-alert.ok{background:#dcfce7;color:#047857}.ap-alert.err{background:#ffe4e6;color:#be123c}.ap-pagination{padding:14px 16px}
    @media(max-width:1200px){.ap-kpis{grid-template-columns:repeat(2,1fr)}.ap-filter,.ap-form,.ap-edit-grid,.ap-event-form,.ap-category-form{grid-template-columns:1fr}.ap-form .span2,.ap-form .span4,.ap-edit-grid .span3{grid-column:span 1}.ap-detail-grid{grid-template-columns:1fr}.ap-head{display:block}.ap-actions{justify-content:flex-start;margin-top:12px}}
</style>

<div class="asset-page">
    <div class="ap-head">
        <div class="ap-title">
            <h1>💼 Tài sản</h1>
            <p>Quản lý tài sản cố định, công cụ dụng cụ, bàn giao, bảo trì, file chứng từ và khấu hao tự động.</p>
        </div>
        <div class="ap-actions">
            <a class="ap-btn ap-btn-light" href="{{ route('finance.assets.export') }}">⬇ Xuất CSV</a>
            <button class="ap-btn ap-btn-primary" type="button" onclick="toggleAssetCreate()">+ Thêm tài sản</button>
        </div>
    </div>

    @if(session('success')) <div class="ap-alert ok">{{ session('success') }}</div> @endif
    @if($errors->any()) <div class="ap-alert err">{{ $errors->first() }}</div> @endif

    <div class="ap-kpis">
        <div class="ap-kpi"><div class="label">Tổng tài sản</div><div class="val">{{ number_format($summary['count'] ?? 0) }}</div><div class="hint">{{ number_format($summary['active'] ?? 0) }} đang sử dụng</div></div>
        <div class="ap-kpi"><div class="label">Nguyên giá</div><div class="val">{{ $money($summary['total_cost'] ?? 0) }}</div><div class="hint">Tổng giá trị ghi nhận</div></div>
        <div class="ap-kpi"><div class="label">Giá trị còn lại</div><div class="val" style="color:#059669">{{ $money($summary['book_value'] ?? 0) }}</div><div class="hint">Theo khấu hao đường thẳng</div></div>
        <div class="ap-kpi"><div class="label">Khấu hao lũy kế</div><div class="val" style="color:#f59e0b">{{ $money($summary['accumulated'] ?? 0) }}</div><div class="hint">Khấu hao dự kiến hiện tại</div></div>
        <div class="ap-kpi"><div class="label">Cần chú ý</div><div class="val" style="color:#e11d48">{{ number_format($summary['maintenance_warning'] ?? 0) }}</div><div class="hint">Bảo trì/hết hạn trong 30 ngày</div></div>
    </div>

    <div class="ap-card">
        <form class="ap-filter" method="GET" action="{{ route('finance.assets.index') }}">
            <input class="ap-input" name="keyword" value="{{ $filters['keyword'] ?? '' }}" placeholder="Tìm mã, tên, serial, nhà cung cấp, vị trí...">
            <select class="ap-select" name="status"><option value="">Tất cả trạng thái</option>@foreach($statuses as $k=>$v)<option value="{{ $k }}" @selected(($filters['status'] ?? '')===$k)>{{ $v }}</option>@endforeach</select>
            <select class="ap-select" name="category_id"><option value="0">Tất cả nhóm</option>@foreach($categories as $c)<option value="{{ $c->id }}" @selected(($filters['category_id'] ?? 0)==$c->id)>{{ $c->name }}</option>@endforeach</select>
            <select class="ap-select" name="company_id"><option value="0">Công ty Quốc Tế EGO</option>@foreach($companies as $c)<option value="{{ $c->id }}" @selected(($filters['company_id'] ?? 0)==$c->id)>{{ $c->name }}</option>@endforeach</select>
            <select class="ap-select" name="condition"><option value="">Tất cả tình trạng</option>@foreach($conditions as $k=>$v)<option value="{{ $k }}" @selected(($filters['condition'] ?? '')===$k)>{{ $v }}</option>@endforeach</select>
            <button class="ap-btn ap-btn-primary" type="submit">Lọc</button>
            <a class="ap-btn ap-btn-light" href="{{ route('finance.assets.index') }}">Reset</a>
        </form>
    </div>

    <div id="assetCreateCard" class="ap-card" style="display:none">
        <div class="ap-card-head"><div><h2>Thêm tài sản mới</h2><div class="ap-sub">Có thể thêm file hóa đơn, ảnh, biên bản bàn giao.</div></div><button class="ap-btn ap-btn-light" type="button" onclick="toggleAssetCreate()">Đóng</button></div>
        <form class="ap-form" method="POST" action="{{ route('finance.assets.store') }}" enctype="multipart/form-data">
            @csrf
            @include('finance.assets.partials.form-fields', ['asset' => null])
            <div class="span4"><button class="ap-btn ap-btn-green" type="submit">Lưu tài sản</button></div>
        </form>
        <form class="ap-category-form" method="POST" action="{{ route('finance.assets.categories.store') }}">
            @csrf
            <input class="ap-input" name="name" placeholder="Thêm nhanh nhóm tài sản">
            <input class="ap-input" name="code" placeholder="Mã nhóm">
            <input class="ap-input" name="useful_life_months" type="number" min="1" placeholder="Số tháng KH">
            <input class="ap-input" name="color" value="#0ea5e9" placeholder="Màu">
            <button class="ap-btn ap-btn-light" type="submit">+ Nhóm</button>
        </form>
    </div>

    <div class="ap-card">
        <div class="ap-card-head"><div><h2>Danh sách tài sản</h2><div class="ap-sub">Bấm mắt để xem chi tiết, bút để sửa, đồng hồ để ghi lịch sử/bảo trì/bàn giao.</div></div></div>
        <div class="ap-table-wrap">
            <table class="ap-table">
                <thead>
                    <tr>
                        <th>STT</th><th>Mã / Tên</th><th>Nhóm</th><th>Công ty / Người dùng</th><th>Nguyên giá</th><th>Còn lại</th><th>KH tháng</th><th>Tiến độ</th><th>Trạng thái</th><th>Bảo trì</th><th>Thao tác</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($assets as $asset)
                        <tr>
                            <td>{{ $loop->iteration + (($assets->currentPage()-1)*$assets->perPage()) }}</td>
                            <td><div class="ap-code">{{ $asset->code }}</div><div class="ap-name">{{ $asset->name }}</div><div class="ap-muted">Serial: {{ $asset->serial_no ?: '—' }} · Vị trí: {{ $asset->location ?: '—' }}</div></td>
                            <td><span class="ap-badge" style="background:#ecfeff;color:#0e7490"><span class="ap-dot" style="background:{{ $asset->category_color ?: '#0ea5e9' }}"></span>{{ $asset->category_name ?: 'Chưa phân nhóm' }}</span></td>
                            <td><div>{{ $asset->company_name ?: '—' }}</div><div class="ap-muted">{{ $asset->assigned_name ?: 'Chưa bàn giao' }}{{ $asset->department ? ' · '.$asset->department : '' }}</div></td>
                            <td class="ap-money">{{ $money($asset->original_cost) }}</td>
                            <td><div class="ap-money">{{ $money($asset->book_value) }}</div><div class="ap-muted">Đã KH: {{ $money($asset->accumulated_depreciation) }}</div></td>
                            <td class="ap-money">{{ $money($asset->monthly_depreciation) }}</td>
                            <td><div class="ap-progress"><span style="width:{{ $asset->progress_percent }}%"></span></div><div class="ap-muted">{{ $asset->used_months }}/{{ $asset->useful_life_months }} tháng</div></td>
                            <td><span class="ap-badge {{ $asset->status }}">{{ $statuses[$asset->status] ?? $asset->status }}</span><div class="ap-muted">{{ $conditions[$asset->condition] ?? $asset->condition }}</div></td>
                            <td><div>{{ $dateText($asset->next_maintenance_date) }}</div><div class="ap-muted">BH: {{ $dateText($asset->warranty_until) }}</div></td>
                            <td><div class="ap-row-actions"><button class="ap-icon" type="button" onclick="toggleAssetDetail({{ $asset->id }})">👁</button><button class="ap-icon" type="button" onclick="toggleAssetEdit({{ $asset->id }})">✎</button><form method="POST" action="{{ route('finance.assets.destroy', $asset->id) }}" onsubmit="return confirm('Xóa tài sản này?')">@csrf @method('DELETE')<button class="ap-icon red" type="submit">×</button></form></div></td>
                        </tr>
                        <tr id="asset-detail-{{ $asset->id }}" class="ap-detail">
                            <td colspan="11" class="ap-detail-cell">
                                <div class="ap-detail-grid">
                                    <div class="ap-card" style="margin:0">
                                        <div class="ap-card-head"><div><h2>Sửa tài sản</h2><div class="ap-sub">Mã: {{ $asset->code }} · Giá trị còn lại {{ $money($asset->book_value) }}</div></div></div>
                                        <form method="POST" action="{{ route('finance.assets.update', $asset->id) }}" enctype="multipart/form-data" class="ap-form">
                                            @csrf @method('PUT')
                                            @include('finance.assets.partials.form-fields', ['asset' => $asset])
                                            <div class="span4"><button class="ap-btn ap-btn-primary" type="submit">Cập nhật tài sản</button></div>
                                        </form>
                                    </div>
                                    <div class="ap-card" style="margin:0">
                                        <div class="ap-card-head"><div><h2>Lịch sử / bảo trì</h2><div class="ap-sub">Bàn giao, điều chuyển, sửa chữa, thanh lý.</div></div></div>
                                        <div style="padding:14px">
                                            <div class="ap-timeline">
                                                @forelse($asset->events as $event)
                                                    <div class="ap-event">
                                                        <div class="ap-event-top"><b>{{ $eventTypes[$event->type] ?? $event->type }}</b><span class="ap-muted">{{ $dateText($event->event_date) }}</span></div>
                                                        <div class="ap-muted">{{ $event->note ?: '—' }}</div>
                                                        @if($event->amount)<div class="ap-money">{{ $money($event->amount) }}</div>@endif
                                                    </div>
                                                @empty
                                                    <div class="ap-muted">Chưa có lịch sử.</div>
                                                @endforelse
                                            </div>
                                            <div class="ap-files">
                                                @foreach($asset->files as $file)
                                                    <a class="ap-file" href="{{ route('finance.assets.files.download', $file->id) }}">📎 {{ $file->original_name ?: 'File' }}</a>
                                                @endforeach
                                            </div>
                                            <form method="POST" action="{{ route('finance.assets.events.store', $asset->id) }}" class="ap-event-form">
                                                @csrf
                                                <select class="ap-select" name="type">@foreach($eventTypes as $k=>$v)<option value="{{ $k }}">{{ $v }}</option>@endforeach</select>
                                                <input class="ap-input" type="date" name="event_date" value="{{ now()->format('Y-m-d') }}">
                                                <input class="ap-input" name="amount" inputmode="decimal" placeholder="Chi phí">
                                                <input class="ap-input" name="to_location" placeholder="Vị trí mới / nơi bảo trì">
                                                <select class="ap-select" name="status"><option value="">Giữ trạng thái</option>@foreach($statuses as $k=>$v)<option value="{{ $k }}">{{ $v }}</option>@endforeach</select>
                                                <button class="ap-btn ap-btn-green" type="submit">+ Ghi</button>
                                                <input class="ap-input" type="date" name="next_maintenance_date" title="Lịch bảo trì tiếp theo">
                                                <select class="ap-select" name="to_user_id"><option value="">Người nhận</option>@foreach($users as $u)<option value="{{ $u->id }}">{{ $u->name }}</option>@endforeach</select>
                                                <select class="ap-select" name="condition"><option value="">Giữ tình trạng</option>@foreach($conditions as $k=>$v)<option value="{{ $k }}">{{ $v }}</option>@endforeach</select>
                                                <input class="ap-input" name="note" placeholder="Ghi chú lịch sử" style="grid-column:span 3">
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="11" style="text-align:center;padding:36px;color:#64748b;font-weight:900">Chưa có tài sản nào.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="ap-pagination">{{ $assets->links() }}</div>
    </div>
</div>

<script>
    function toggleAssetCreate(){const el=document.getElementById('assetCreateCard'); if(el){el.style.display=el.style.display==='none'||!el.style.display?'block':'none'; if(el.style.display==='block') el.scrollIntoView({behavior:'smooth',block:'start'});}}
    function toggleAssetDetail(id){const el=document.getElementById('asset-detail-'+id); if(el) el.classList.toggle('show');}
    function toggleAssetEdit(id){const el=document.getElementById('asset-detail-'+id); if(el){el.classList.add('show'); el.scrollIntoView({behavior:'smooth',block:'center'});}}
    document.addEventListener('DOMContentLoaded', function(){
        document.querySelectorAll('[data-money]').forEach(function(input){
            input.addEventListener('input', function(){ input.value = input.value.replace(/[^0-9.,-]/g,''); });
        });
    });
</script>
@endsection
