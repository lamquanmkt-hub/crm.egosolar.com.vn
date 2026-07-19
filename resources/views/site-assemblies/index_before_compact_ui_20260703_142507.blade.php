@extends('layouts.app')

@section('content')
@php
    $statusMap = [
        'draft' => ['Nháp', 'warning'],
        'completed' => ['Đã nhập kho', 'success'],
    ];
@endphp

<style>
    .sa-wrap{max-width:1500px;margin:0 auto;padding:20px 14px 50px}
    .sa-hero{background:linear-gradient(135deg,#071735,#0f766e);border-radius:28px;color:#fff;padding:26px 28px;box-shadow:0 22px 60px rgba(15,23,42,.18);margin-bottom:18px}
    .sa-title{font-size:28px;font-weight:950;margin:0}.sa-sub{opacity:.88;font-weight:700;margin-top:6px}
    .sa-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:14px;margin:18px 0}
    .sa-stat,.sa-card{background:#fff;border:1px solid #e5edf5;border-radius:24px;box-shadow:0 10px 30px rgba(15,23,42,.06)}
    .sa-stat{padding:18px}.sa-stat b{font-size:26px;color:#0f172a}.sa-stat span{display:block;color:#64748b;font-weight:800}
    .sa-card{padding:20px;margin-bottom:18px}.sa-card h3{font-size:19px;font-weight:950;color:#0f172a;margin:0 0 14px}
    .sa-label{font-size:12px;font-weight:950;text-transform:uppercase;color:#475569;margin-bottom:6px}
    .sa-control{border:1px solid #dbe7f0;border-radius:14px;padding:10px 12px;font-weight:800;width:100%;outline:none;background:#fff}
    .sa-control:focus{border-color:#14b8a6;box-shadow:0 0 0 4px rgba(20,184,166,.11)}
    .sa-row{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:12px}.sa-row-2{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px}
    .sa-table{width:100%;border-collapse:separate;border-spacing:0 10px}.sa-table th{font-size:12px;text-transform:uppercase;color:#64748b;text-align:left;padding:0 12px}
    .sa-table td{background:#f8fafc;border-top:1px solid #e5edf5;border-bottom:1px solid #e5edf5;padding:12px;font-weight:800;color:#0f172a}
    .sa-table td:first-child{border-left:1px solid #e5edf5;border-radius:14px 0 0 14px}.sa-table td:last-child{border-right:1px solid #e5edf5;border-radius:0 14px 14px 0}
    .sa-btn{border:0;border-radius:14px;padding:10px 14px;font-weight:950;text-decoration:none;display:inline-flex;gap:7px;align-items:center;cursor:pointer}
    .sa-btn-primary{background:#10b981;color:#fff}.sa-btn-dark{background:#0f172a;color:#fff}.sa-btn-light{background:#ecfeff;color:#0f766e}.sa-btn-danger{background:#fee2e2;color:#b91c1c}
    .sa-badge{border-radius:999px;padding:6px 10px;font-size:12px;font-weight:950}.sa-badge-success{background:#dcfce7;color:#15803d}.sa-badge-warning{background:#fef3c7;color:#b45309}
    .sa-muted{color:#64748b;font-weight:800}.sa-material{display:grid;grid-template-columns:1fr 100px 1fr 44px;gap:10px;align-items:end;margin-bottom:10px;padding:12px;border:1px dashed #dbe7f0;border-radius:18px;background:#fbfdff}
    .sa-search{margin-bottom:6px}.sa-actions{display:flex;gap:10px;flex-wrap:wrap;align-items:center}.alert{border-radius:16px;font-weight:800}
    @media(max-width:900px){.sa-grid,.sa-row,.sa-row-2{grid-template-columns:1fr}.sa-material{grid-template-columns:1fr}.sa-table{font-size:13px}}
</style>

<div class="sa-wrap">
    <div class="sa-hero">
        <div class="sa-title">Lắp ráp / Sản xuất</div>
        <div class="sa-sub">Xuất vật tư lắp ráp, sau đó nhập thành phẩm vào kho theo công ty và công trình.</div>
    </div>

    @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
    @if(session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif
    @if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif

    <div class="sa-grid">
        <div class="sa-stat"><b>{{ number_format($stats['total'] ?? 0) }}</b><span>Tổng phiếu</span></div>
        <div class="sa-stat"><b>{{ number_format($stats['draft'] ?? 0) }}</b><span>Phiếu nháp</span></div>
        <div class="sa-stat"><b>{{ number_format($stats['completed'] ?? 0) }}</b><span>Đã nhập kho</span></div>
    </div>

    <div class="sa-card">
        <h3>Tạo phiếu lắp ráp mới</h3>
        <form method="POST" action="{{ route('site-assemblies.store') }}">
            @csrf

            <div class="sa-row">
                <div>
                    <div class="sa-label">Công ty</div>
                    <select class="sa-control" name="company_id" id="saCompany" required>
                        <option value="">-- Chọn công ty --</option>
                        @foreach($companies as $company)
                            <option value="{{ $company->id }}">{{ $company->code }} - {{ $company->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <div class="sa-label">Công trình nếu có</div>
                    <input class="sa-control sa-search" type="text" placeholder="Gõ tìm công trình..." oninput="saFilterSelect(this)">
                    <select class="sa-control" name="site_id" data-company-filter="1">
                        <option value="">-- Không chọn công trình --</option>
                        @foreach($sites as $site)
                            <option value="{{ $site->id }}" data-company="{{ $site->company_id }}">#{{ $site->id }} - {{ $site->name }} {{ $site->contact_phone ? '- '.$site->contact_phone : '' }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <div class="sa-label">Ngày lắp ráp</div>
                    <input class="sa-control" type="date" name="produced_at" value="{{ now()->toDateString() }}">
                </div>

                <div>
                    <div class="sa-label">Số lượng thành phẩm</div>
                    <input class="sa-control" type="number" name="finished_qty" min="1" step="1" value="1" required>
                </div>
            </div>

            <div class="sa-row" style="margin-top:12px">
                <div>
                    <div class="sa-label">Kho xuất vật tư</div>
                    <select class="sa-control" name="material_warehouse_id" data-company-filter="1" required>
                        <option value="">-- Chọn kho --</option>
                        @foreach($warehouses as $warehouse)
                            <option value="{{ $warehouse->id }}" data-company="{{ $warehouse->company_id }}">{{ $warehouse->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <div class="sa-label">Kho nhập thành phẩm</div>
                    <select class="sa-control" name="finished_warehouse_id" data-company-filter="1" required>
                        <option value="">-- Chọn kho --</option>
                        @foreach($warehouses as $warehouse)
                            <option value="{{ $warehouse->id }}" data-company="{{ $warehouse->company_id }}">{{ $warehouse->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div style="grid-column:span 2">
                    <div class="sa-label">Thành phẩm nhập kho</div>
                    <input class="sa-control sa-search" type="text" placeholder="Gõ mã hoặc tên thành phẩm..." oninput="saFilterSelect(this)">
                    <select class="sa-control" name="finished_product_id" required>
                        <option value="">-- Chọn thành phẩm --</option>
                        @foreach($products as $product)
                            <option value="{{ $product->id }}" data-company="{{ $product->company_id }}">{{ $product->sku }} - {{ $product->name }} {{ $product->unit ? '('.$product->unit.')' : '' }} - Tồn {{ number_format($product->stock_qty) }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div style="margin-top:16px">
                <div class="sa-label">Vật tư xuất để lắp ráp</div>
                <div id="saMaterials"></div>
                <button type="button" class="sa-btn sa-btn-light" onclick="saAddMaterial()">+ Thêm vật tư</button>
            </div>

            <div style="margin-top:14px">
                <div class="sa-label">Ghi chú</div>
                <textarea class="sa-control" name="note" rows="3" placeholder="Ví dụ: Xuất vật tư lắp ráp thành tủ điện / bộ kit / thành phẩm nhập kho..."></textarea>
            </div>

            <div class="sa-actions" style="margin-top:16px">
                <button class="sa-btn sa-btn-dark" name="action" value="draft" type="submit">Lưu nháp</button>
                <button class="sa-btn sa-btn-primary" name="action" value="complete" type="submit" onclick="return confirm('Xác nhận xuất vật tư và nhập thành phẩm vào kho?')">Lưu & cập nhật kho</button>
            </div>
        </form>
    </div>

    <div class="sa-card">
        <h3>Danh sách phiếu lắp ráp</h3>

        <form method="GET" class="sa-row-2" style="margin-bottom:12px">
            <input class="sa-control" name="q" value="{{ $q }}" placeholder="Tìm mã phiếu, công trình, sản phẩm...">
            <select class="sa-control" name="status" onchange="this.form.submit()">
                <option value="">Tất cả trạng thái</option>
                <option value="draft" @selected($status === 'draft')>Nháp</option>
                <option value="completed" @selected($status === 'completed')>Đã nhập kho</option>
            </select>
        </form>

        <div style="overflow:auto">
            <table class="sa-table">
                <thead>
                    <tr>
                        <th>Mã phiếu</th>
                        <th>Công ty / công trình</th>
                        <th>Thành phẩm</th>
                        <th>Kho</th>
                        <th>Trạng thái</th>
                        <th>Thao tác</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($assemblies as $row)
                        @php $st = $statusMap[$row->status] ?? [$row->status, 'warning']; @endphp
                        <tr>
                            <td><b>{{ $row->code }}</b><br><span class="sa-muted">{{ $row->produced_at ? \Illuminate\Support\Carbon::parse($row->produced_at)->format('d/m/Y') : '' }}</span></td>
                            <td>{{ $row->company_name }}<br><span class="sa-muted">{{ $row->site_name ?: 'Không gắn công trình' }}</span></td>
                            <td>{{ $row->finished_product_sku }} - {{ $row->finished_product_name }}<br><span class="sa-muted">SL: {{ number_format($row->finished_qty) }}</span></td>
                            <td><span class="sa-muted">Xuất:</span> {{ $row->material_warehouse_name }}<br><span class="sa-muted">Nhập:</span> {{ $row->finished_warehouse_name }}</td>
                            <td><span class="sa-badge sa-badge-{{ $st[1] }}">{{ $st[0] }}</span></td>
                            <td>
                                <div class="sa-actions">
                                    @if($row->status !== 'completed')
                                        <form method="POST" action="{{ route('site-assemblies.complete', $row->id) }}">
                                            @csrf
                                            <button class="sa-btn sa-btn-primary" onclick="return confirm('Cập nhật kho cho phiếu này?')">Hoàn thành</button>
                                        </form>

                                        <form method="POST" action="{{ route('site-assemblies.destroy', $row->id) }}">
                                            @csrf
                                            @method('DELETE')
                                            <button class="sa-btn sa-btn-danger" onclick="return confirm('Xóa phiếu nháp này?')">Xóa</button>
                                        </form>
                                    @else
                                        <span class="sa-muted">Đã khóa</span>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" style="text-align:center">Chưa có phiếu lắp ráp.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{ $assemblies->links() }}
    </div>
</div>

<template id="saMaterialTemplate">
    <div class="sa-material">
        <div>
            <div class="sa-label">Sản phẩm vật tư</div>
            <input class="sa-control sa-search" type="text" placeholder="Gõ tìm vật tư..." oninput="saFilterSelect(this)">
            <select class="sa-control" data-name="product_id" required>
                <option value="">-- Chọn vật tư --</option>
                @foreach($products as $product)
                    <option value="{{ $product->id }}" data-company="{{ $product->company_id }}">{{ $product->sku }} - {{ $product->name }} {{ $product->unit ? '('.$product->unit.')' : '' }} - Tồn {{ number_format($product->stock_qty) }}</option>
                @endforeach
            </select>
        </div>

        <div>
            <div class="sa-label">SL xuất</div>
            <input class="sa-control" data-name="qty" type="number" min="1" step="1" value="1" required>
        </div>

        <div>
            <div class="sa-label">Ghi chú vật tư</div>
            <input class="sa-control" data-name="note" placeholder="Không bắt buộc">
        </div>

        <button type="button" class="sa-btn sa-btn-danger" onclick="this.closest('.sa-material').remove(); saReindexMaterials();">×</button>
    </div>
</template>

<script>
function saFilterSelect(input){
    var select = input.parentElement.querySelector('select');
    if(!select){ return; }

    var q = (input.value || '').toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g,'');

    Array.prototype.forEach.call(select.options, function(opt, idx){
        if(idx === 0){ opt.hidden = false; return; }

        var text = (opt.textContent || '').toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g,'');
        opt.hidden = q && text.indexOf(q) === -1;
    });
}

function saReindexMaterials(){
    document.querySelectorAll('#saMaterials .sa-material').forEach(function(row, i){
        row.querySelectorAll('[data-name]').forEach(function(el){
            el.name = 'materials[' + i + '][' + el.dataset.name + ']';
        });
    });
}

function saAddMaterial(){
    var tpl = document.getElementById('saMaterialTemplate');
    var node = tpl.content.cloneNode(true);
    document.getElementById('saMaterials').appendChild(node);
    saReindexMaterials();
}

function saApplyCompanyFilter(){
    var companyId = document.getElementById('saCompany') ? document.getElementById('saCompany').value : '';

    document.querySelectorAll('select[data-company-filter="1"]').forEach(function(select){
        Array.prototype.forEach.call(select.options, function(opt, idx){
            if(idx === 0){ opt.hidden = false; return; }

            var c = opt.getAttribute('data-company') || '';
            opt.hidden = !!companyId && !!c && c !== companyId;
        });

        if(select.selectedOptions[0] && select.selectedOptions[0].hidden){
            select.value = '';
        }
    });
}

document.addEventListener('DOMContentLoaded', function(){
    saAddMaterial();
    saApplyCompanyFilter();

    var company = document.getElementById('saCompany');
    if(company){
        company.addEventListener('change', saApplyCompanyFilter);
    }
});
</script>
@endsection
