@extends('layouts.app')

@section('title', 'Kho & Sản phẩm')

@php
    use Illuminate\Support\Facades\Storage;
    use Illuminate\Support\Facades\Route;

    $products = $products ?? null;
    $warehouses = $warehouses ?? collect();
    $categories = $categories ?? collect();
    $brands = $brands ?? collect();

    $selectedWarehouse = request('warehouse_id');
    $selectedCategory = request('category_id');
    $selectedBrand = request('brand_id');

    $productPolicyModel = class_exists(\App\Models\Inventory\Catalog\Product::class)
        ? \App\Models\Inventory\Catalog\Product::class
        : null;

    $canManageProducts = $productPolicyModel
        ? (auth()->user()?->can('create', $productPolicyModel) ?? false)
        : false;

    $canViewCost = $productPolicyModel
        ? (auth()->user()?->can('viewCost', $productPolicyModel) ?? false)
        : false;

    $totalQtyAll = (int) ($totalQtyAll ?? 0);
    $totalAmountAll = (float) ($totalAmountAll ?? 0);
    $totalCatalog = $products && method_exists($products, 'total') ? (int) $products->total() : 0;
@endphp

@section('content')
<style>
    :root{
        --inv-navy:#0b1f38;
        --inv-teal:#079b96;
        --inv-teal-dark:#087a76;
        --inv-teal-soft:#e9fbfa;
        --inv-bg:#f3f7fb;
        --inv-card:#fff;
        --inv-text:#10243e;
        --inv-muted:#6b7d91;
        --inv-line:#dce7f0;
        --inv-danger:#e9485d;
        --inv-shadow:0 12px 34px rgba(19,42,67,.07);
    }
    .inv-page{padding:16px 18px 34px;background:var(--inv-bg);min-height:calc(100vh - 72px)}
    .inv-head{display:flex;align-items:flex-start;justify-content:space-between;gap:18px;margin-bottom:14px}
    .inv-eyebrow{font-size:10px;line-height:1;font-weight:900;text-transform:uppercase;letter-spacing:.11em;color:#06958f;margin-bottom:7px}
    .inv-title{font-size:25px;line-height:1.15;font-weight:950;letter-spacing:-.035em;color:var(--inv-navy);margin:0}
    .inv-sub{font-size:12px;color:var(--inv-muted);font-weight:650;margin-top:5px}
    .inv-actions{display:flex;gap:8px;flex-wrap:wrap;justify-content:flex-end}
    .inv-btn{height:38px;border-radius:11px;padding:0 13px;border:1px solid var(--inv-line);display:inline-flex;align-items:center;justify-content:center;gap:7px;text-decoration:none;font-size:12px;font-weight:900;white-space:nowrap;transition:.16s;background:#fff;color:var(--inv-text)}
    .inv-btn:hover{transform:translateY(-1px);box-shadow:0 8px 18px rgba(15,23,42,.08);color:var(--inv-text)}
    .inv-btn-primary{background:linear-gradient(135deg,#0aa7a1,#0c8d88);border-color:transparent;color:#fff;box-shadow:0 9px 20px rgba(7,155,150,.22)}
    .inv-btn-primary:hover{color:#fff}
    .inv-btn-navy{background:var(--inv-navy);border-color:var(--inv-navy);color:#fff}.inv-btn-navy:hover{color:#fff}
    .inv-btn-soft{background:var(--inv-teal-soft);border-color:#bdeeea;color:#087a76}
    .inv-kpis{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:10px;margin-bottom:12px}
    .inv-kpi{background:#fff;border:1px solid var(--inv-line);border-radius:15px;padding:13px 14px;box-shadow:0 7px 22px rgba(19,42,67,.045);display:flex;align-items:center;gap:12px;min-height:78px}
    .inv-kpi-icon{width:42px;height:42px;border-radius:13px;display:flex;align-items:center;justify-content:center;background:#eaf9fb;color:#078d88;font-size:18px;flex:0 0 auto}
    .inv-kpi:nth-child(2) .inv-kpi-icon{background:#eef2ff;color:#5563d8}.inv-kpi:nth-child(3) .inv-kpi-icon{background:#eefbf3;color:#168d4e}.inv-kpi:nth-child(4) .inv-kpi-icon{background:#fff6e8;color:#c37b12}
    .inv-kpi-label{font-size:10px;font-weight:900;text-transform:uppercase;letter-spacing:.06em;color:#75869a}
    .inv-kpi-value{font-size:20px;font-weight:950;color:var(--inv-navy);line-height:1.15;margin-top:3px}
    .inv-card{background:#fff;border:1px solid var(--inv-line);border-radius:16px;box-shadow:var(--inv-shadow);overflow:hidden}
    .inv-filter{padding:13px 14px;margin-bottom:12px}
    .inv-filter-grid{display:grid;grid-template-columns:1.35fr 1fr 1fr 1fr auto;gap:10px;align-items:end}
    .inv-label{display:block;font-size:10px;font-weight:900;text-transform:uppercase;letter-spacing:.055em;color:#52667c;margin-bottom:5px}
    .inv-control{height:39px;width:100%;border:1px solid var(--inv-line);border-radius:11px;background:#fff;padding:0 11px;color:#17304d;font-size:12px;font-weight:750;outline:none}
    .inv-control:focus{border-color:#6cded8;box-shadow:0 0 0 4px rgba(9,155,150,.10)}
    .inv-search{position:relative}.inv-search i{position:absolute;left:12px;top:12px;color:#7b8da1}.inv-search .inv-control{padding-left:35px}
    .inv-filter-actions{display:flex;gap:7px}
    .inv-filter-actions .inv-btn{height:39px}
    .inv-table-head{padding:13px 15px;border-bottom:1px solid var(--inv-line);display:flex;align-items:center;justify-content:space-between;gap:10px}
    .inv-table-title{font-size:15px;font-weight:950;color:var(--inv-navy)}
    .inv-table-note{font-size:11px;color:var(--inv-muted);margin-top:2px;font-weight:650}
    .inv-table-wrap{overflow:auto}
    .inv-table{width:100%;min-width:1240px;border-collapse:separate;border-spacing:0}
    .inv-table th{background:#f5f9fc;border-bottom:1px solid var(--inv-line);padding:10px 11px;font-size:10px;font-weight:900;text-transform:uppercase;letter-spacing:.05em;color:#607489;white-space:nowrap;text-align:left}
    .inv-table td{border-bottom:1px solid #edf2f6;padding:11px;vertical-align:middle;font-size:12px;color:#1a304b;background:#fff}
    .inv-table tbody tr{cursor:pointer;transition:.15s}.inv-table tbody tr:hover td{background:#f1fbfb}.inv-table tbody tr:last-child td{border-bottom:0}
    .inv-index{width:29px;height:29px;border-radius:999px;display:inline-flex;align-items:center;justify-content:center;background:#e9f7f8;color:#077d79;font-weight:950}
    .inv-product-cell{display:flex;align-items:center;gap:10px;min-width:310px}
    .inv-thumb{width:48px;height:48px;border-radius:12px;border:1px solid var(--inv-line);background:#f4f8fb;overflow:hidden;display:flex;align-items:center;justify-content:center;color:#83a0b5;flex:0 0 auto}
    .inv-thumb img{width:100%;height:100%;object-fit:cover}.inv-name{font-size:13px;font-weight:950;color:var(--inv-navy);text-decoration:none;line-height:1.35}.inv-name:hover{color:var(--inv-teal)}
    .inv-meta{display:flex;flex-wrap:wrap;gap:5px;margin-top:5px}.inv-pill{display:inline-flex;align-items:center;gap:4px;border-radius:999px;padding:4px 7px;background:#f3f7fa;border:1px solid #e2eaf1;color:#61758a;font-size:10px;font-weight:850}
    .inv-sku{display:inline-flex;padding:5px 9px;border-radius:9px;background:#f7f9fc;border:1px solid #dfe7ef;color:#18314f;font-weight:950;text-decoration:none;white-space:nowrap}.inv-sku:hover{border-color:#7fded8;color:#087a76}
    .inv-money{font-weight:950;white-space:nowrap}.inv-money-green{color:#078d52}.inv-qty{display:inline-flex;min-width:45px;justify-content:center;padding:6px 9px;border-radius:10px;background:#eaf9f1;color:#0e8b4f;font-weight:950}.inv-qty.zero{background:#fff0f1;color:#dc3d52}
    .inv-badge{display:inline-flex;border-radius:999px;padding:5px 8px;background:#e9f8fb;border:1px solid #bfebef;color:#08778a;font-size:10px;font-weight:900;white-space:nowrap}.inv-badge-brand{background:#f2efff;border-color:#ddd5ff;color:#6153c7}
    .inv-row-actions{display:flex;gap:5px;justify-content:flex-end}.inv-icon-btn{width:33px;height:33px;border:1px solid var(--inv-line);border-radius:10px;background:#fff;color:#35516e;display:inline-flex;align-items:center;justify-content:center;text-decoration:none;cursor:pointer}.inv-icon-btn:hover{background:#eefafa;color:#07817d;border-color:#bce9e6}.inv-icon-danger{color:#d64255;background:#fff8f8}
    .inv-empty{padding:42px;text-align:center;color:#6c7f92}.inv-empty i{font-size:32px;display:block;margin-bottom:8px;color:#9cb0c1}
    .inv-pagination{padding:12px 14px;border-top:1px solid var(--inv-line)}
    .inv-company-lock{display:inline-flex;align-items:center;gap:6px;font-size:10px;font-weight:900;color:#087a76;background:#e9fbfa;border:1px solid #c1efeb;border-radius:999px;padding:6px 9px}
    @media(max-width:1200px){.inv-filter-grid{grid-template-columns:repeat(2,minmax(0,1fr))}.inv-filter-actions{grid-column:1/-1}.inv-kpis{grid-template-columns:repeat(2,1fr)}}
    @media(max-width:720px){.inv-page{padding:12px}.inv-head{display:block}.inv-actions{justify-content:flex-start;margin-top:10px}.inv-filter-grid,.inv-kpis{grid-template-columns:1fr}.inv-title{font-size:21px}}
</style>

<div class="inv-page">
    <div class="inv-head">
        <div>
            <div class="inv-eyebrow">Kho & Sản phẩm</div>
            <h1 class="inv-title">Sản phẩm đầu vào</h1>
            <div class="inv-sub">Quản lý danh mục, giá vốn, tồn kho và mở trực tiếp hồ sơ từng sản phẩm.</div>
        </div>
        <div class="inv-actions">
            <span class="inv-company-lock"><i class="bi bi-shield-check"></i> Quốc Tế EGO</span>
            @if(Route::has('product-goods-receipts.index'))
                <a href="{{ route('product-goods-receipts.index') }}" class="inv-btn inv-btn-soft"><i class="bi bi-box-arrow-in-down"></i> Phiếu nhập hàng</a>
            @endif
            <a href="{{ route('products.input.export.excel', request()->query()) }}" class="inv-btn"><i class="bi bi-file-earmark-excel"></i> Xuất Excel</a>
            @if($canManageProducts)
                <a href="{{ route('products.create') }}" class="inv-btn inv-btn-primary"><i class="bi bi-plus-lg"></i> Nhập sản phẩm</a>
            @endif
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success border-0 rounded-3 shadow-sm py-2">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="alert alert-danger border-0 rounded-3 shadow-sm py-2">{{ session('error') }}</div>
    @endif

    <div class="inv-kpis">
        <div class="inv-kpi"><div class="inv-kpi-icon"><i class="bi bi-grid-3x3-gap"></i></div><div><div class="inv-kpi-label">Mã sản phẩm</div><div class="inv-kpi-value">{{ number_format($totalCatalog) }}</div></div></div>
        <div class="inv-kpi"><div class="inv-kpi-icon"><i class="bi bi-boxes"></i></div><div><div class="inv-kpi-label">Tổng tồn kho</div><div class="inv-kpi-value">{{ number_format($totalQtyAll) }}</div></div></div>
        <div class="inv-kpi"><div class="inv-kpi-icon"><i class="bi bi-cash-stack"></i></div><div><div class="inv-kpi-label">Tổng giá vốn</div><div class="inv-kpi-value">{{ $canViewCost ? number_format($totalAmountAll).' đ' : '—' }}</div></div></div>
        <div class="inv-kpi"><div class="inv-kpi-icon"><i class="bi bi-building-check"></i></div><div><div class="inv-kpi-label">Kho đang quản lý</div><div class="inv-kpi-value">{{ number_format($warehouses->count()) }}</div></div></div>
    </div>

    <form method="GET" class="inv-card inv-filter" id="inventoryFilterForm">
        <div class="inv-filter-grid">
            <div>
                <label class="inv-label">Tìm kiếm</label>
                <div class="inv-search"><i class="bi bi-search"></i><input class="inv-control" name="search" value="{{ request('search') }}" placeholder="Tên sản phẩm, SKU, mã hàng..."></div>
            </div>
            <div>
                <label class="inv-label">Kho</label>
                <select class="inv-control" name="warehouse_id">
                    <option value="">Tất cả kho Quốc Tế EGO</option>
                    @foreach($warehouses as $warehouse)
                        <option value="{{ $warehouse->id }}" @selected((string)$selectedWarehouse === (string)$warehouse->id)>{{ $warehouse->name }}{{ $warehouse->location ? ' · '.$warehouse->location : '' }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="inv-label">Danh mục</label>
                <select class="inv-control" name="category_id">
                    <option value="">Tất cả danh mục</option>
                    @foreach($categories as $category)
                        @php $prefix = str_repeat('— ', (int)($category->level ?? 0)); @endphp
                        <option value="{{ $category->id }}" @selected((string)$selectedCategory === (string)$category->id)>{{ $prefix }}{{ $category->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="inv-label">Thương hiệu</label>
                <select class="inv-control" name="brand_id">
                    <option value="">Tất cả thương hiệu</option>
                    @foreach($brands as $brand)
                        <option value="{{ $brand->id }}" @selected((string)$selectedBrand === (string)$brand->id)>{{ $brand->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="inv-filter-actions">
                <button class="inv-btn inv-btn-navy" type="submit"><i class="bi bi-funnel"></i> Lọc</button>
                <a class="inv-btn" href="{{ route('products.input') }}" title="Đặt lại"><i class="bi bi-arrow-counterclockwise"></i></a>
            </div>
        </div>
    </form>

    <div class="inv-card">
        <div class="inv-table-head">
            <div><div class="inv-table-title">Danh sách sản phẩm</div><div class="inv-table-note">Bấm vào tên, SKU, nút xem hoặc bất kỳ vùng trống nào trên dòng để mở hồ sơ sản phẩm.</div></div>
            <div class="inv-company-lock"><i class="bi bi-mouse"></i> Dòng có thể bấm</div>
        </div>
        <div class="inv-table-wrap">
            <table class="inv-table">
                <thead>
                <tr>
                    <th style="width:58px;text-align:center">STT</th>
                    <th style="min-width:330px">Sản phẩm</th>
                    <th>SKU</th>
                    <th>Giá vốn trước VAT</th>
                    <th>VAT</th>
                    <th>Giá vốn sau VAT</th>
                    <th>Tồn kho</th>
                    <th>Thành tiền</th>
                    <th>Danh mục</th>
                    <th>Thương hiệu</th>
                    <th style="text-align:right">Thao tác</th>
                </tr>
                </thead>
                <tbody>
                @forelse($products ?? collect() as $product)
                    @php
                        $vatPercent = (float) ($product->cost_vat_percent ?? $product->vat_percent ?? 0);
                        $costBefore = (float) ($product->price_agent ?? 0);
                        $costAfter = (float) ($product->price_agent_vat ?? ($costBefore * (1 + $vatPercent / 100)));
                        $qty = (int) ($product->stocks_sum_qty ?? $product->warehouse_qty ?? $product->quantity ?? 0);
                        $rowAmount = $costAfter * $qty;
                        $note = $product->note ?? $product->description ?? null;
                        $media = optional($product->mainImage)->media;
                        $imageUrl = $media?->metadata->url
                            ?? ($media?->file_path ? Storage::disk('public')->url($media->file_path) : ($product->image_url ?? null));
                        $showUrl = Route::has('products.show') ? route('products.show', $product->id) : route('products.edit', $product->id);
                    @endphp
                    <tr class="js-product-row" data-href="{{ $showUrl }}" tabindex="0">
                        <td style="text-align:center"><span class="inv-index">{{ ($products->currentPage()-1)*$products->perPage()+$loop->iteration }}</span></td>
                        <td>
                            <div class="inv-product-cell">
                                <div class="inv-thumb">
                                    @if($imageUrl)<img src="{{ $imageUrl }}" alt="{{ $product->name }}">@else<i class="bi bi-box-seam"></i>@endif
                                </div>
                                <div>
                                    <a class="inv-name" href="{{ $showUrl }}">{{ $product->name }}</a>
                                    <div class="inv-meta">
                                        <span class="inv-pill"><i class="bi bi-{{ $product->is_serialized ? 'upc-scan' : 'boxes' }}"></i>{{ $product->is_serialized ? 'Quản lý serial' : '' }}</span>
                                        @if($note)<span class="inv-pill" title="{{ $note }}"><i class="bi bi-chat-left-text"></i>{{ \Illuminate\Support\Str::limit($note, 38) }}</span>@endif
                                    </div>
                                </div>
                            </div>
                        </td>
                        <td><a class="inv-sku" href="{{ $showUrl }}">{{ $product->sku ?: 'Chưa có SKU' }}</a></td>
                        <td><span class="inv-money">{{ $canViewCost ? number_format($costBefore) : '—' }}</span></td>
                        <td><span class="inv-badge">{{ rtrim(rtrim(number_format($vatPercent,2), '0'), '.') }}%</span></td>
                        <td><span class="inv-money inv-money-green">{{ $canViewCost ? number_format($costAfter) : '—' }}</span></td>
                        <td><span class="inv-qty {{ $qty <= 0 ? 'zero' : '' }}">{{ number_format($qty) }}</span></td>
                        <td><span class="inv-money inv-money-green">{{ $canViewCost ? number_format($rowAmount).' đ' : '—' }}</span></td>
                        <td>@if($product->category)<span class="inv-badge">{{ $product->category->name }}</span>@else<span class="text-muted">—</span>@endif</td>
                        <td>@if($product->brand)<span class="inv-badge inv-badge-brand">{{ $product->brand->name }}</span>@else<span class="text-muted">—</span>@endif</td>
                        <td>
                            <div class="inv-row-actions">
                                <a class="inv-icon-btn" href="{{ $showUrl }}" title="Xem sản phẩm"><i class="bi bi-eye"></i></a>
                                @if($canManageProducts)
                                    <a class="inv-icon-btn" href="{{ route('products.edit', $product->id) }}" title="Chỉnh sửa"><i class="bi bi-pencil-square"></i></a>
                                    <form action="{{ route('products.destroy', $product->id) }}" method="POST" onclick="event.stopPropagation()">
                                        @csrf @method('DELETE')
                                        <button class="inv-icon-btn inv-icon-danger" type="submit" title="Xóa" onclick="return confirm('Bạn chắc chắn muốn xóa sản phẩm này?')"><i class="bi bi-trash3"></i></button>
                                    </form>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="11"><div class="inv-empty"><i class="bi bi-inboxes"></i>Không tìm thấy sản phẩm phù hợp.</div></td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        @if($products && method_exists($products, 'links'))
            <div class="inv-pagination">{{ $products->appends(request()->query())->links('pagination::bootstrap-5') }}</div>
        @endif
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function(){
    document.querySelectorAll('.js-product-row').forEach(function(row){
        row.addEventListener('click', function(event){
            if(event.target.closest('a,button,input,select,form,textarea')) return;
            window.location.href = row.dataset.href;
        });
        row.addEventListener('keydown', function(event){
            if((event.key === 'Enter' || event.key === ' ') && !event.target.closest('a,button,input,select,form,textarea')){
                event.preventDefault();
                window.location.href = row.dataset.href;
            }
        });
    });
});
</script>
@endsection
