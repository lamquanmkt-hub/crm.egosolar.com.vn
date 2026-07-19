@extends('layouts.app')

@php
  use Illuminate\Support\Facades\Storage;
  use Illuminate\Support\Facades\Route;

  $products   = $products ?? null;
  $warehouses = $warehouses ?? collect();
  $categories = $categories ?? collect();
  $priceTiers = $priceTiers ?? collect();
  $brands     = $brands ?? collect();

  $selectedWarehouse = request('warehouse_id');
  $selectedCategory  = request('category_id');
  $selectedBrand     = request('brand_id');
  $selectedPriceTier = request('price_tier_id');

  $currentCompanyId = request('company_id', 'all');

  $productPolicyModel = class_exists(\App\Models\Inventory\Catalog\Product::class)
    ? \App\Models\Inventory\Catalog\Product::class
    : null;

  $canManageProducts = $productPolicyModel
    ? (auth()->user()?->can('create', $productPolicyModel) ?? false)
    : false;

  $getTierRow = function($product, $tierId) {
  if (!$tierId) return null;
  if (!$product || !$product->relationLoaded('prices')) return null;
  return $product->prices->firstWhere('price_tier_id', (int)$tierId);
};

  // STT + Tên + SKU + Ghi chú + (3 cột giá bán) + SL + Danh mục + Thương hiệu + Hình ảnh
  $baseCols = 11;
  $colspan  = $baseCols + ($canManageProducts ? 1 : 0);
@endphp

@section('content')
<div class="container-fluid px-3 px-lg-4 mt-3 ego-products-page">

  {{-- HEADER --}}
  <div class="d-flex flex-wrap justify-content-between align-items-end gap-2 mb-3">
    <div>
      <div class="d-flex align-items-center gap-2">
        <div class="ego-page-dot"></div>
        <h3 class="fw-bold mb-0">Sản phẩm đầu ra</h3>
      </div>
      <div class="text-muted small">Giá bán trước thuế / VAT / Giá bán sau thuế</div>
    </div>

    <div class="d-flex flex-wrap gap-2">
      @if(Route::has('products.export'))
        <a href="{{ route('products.export', request()->query()) }}" class="btn ego-btn-soft">
          <i class="bi bi-file-earmark-excel"></i> Tải Excel
        </a>
      @endif

      @if($productPolicyModel && auth()->user()?->can('create', $productPolicyModel))
        <a href="{{ route('products.create', request()->query()) }}" class="btn ego-btn-primary">
          <i class="bi bi-plus-circle"></i> Thêm sản phẩm
        </a>
      @endif
    </div>
  </div>

  {{-- MESSAGE --}}
  @if(session('success'))
    <div class="alert alert-success alert-dismissible fade show py-2" role="alert">
      <i class="bi bi-check2-circle me-1"></i>
      {{ session('success') }}
      <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
  @endif

  @if(session('error'))
    <div class="alert alert-danger alert-dismissible fade show py-2" role="alert">
      <i class="bi bi-exclamation-triangle me-1"></i>
      {{ session('error') }}
      <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
  @endif

  {{-- 3 TAB --}}
  <div class="row g-3 mb-3">
    <div class="col-12 col-md-4">
      <div class="ego-company-tab {{ (string)$currentCompanyId === '1' ? 'active' : '' }}"
           onclick="selectCompany('1')">
        <div class="icon-box"><i class="bi bi-building-check"></i></div>
        <div class="info flex-grow-1">
          <div class="title">CÔNG TY TNHH EGO VIET NAM</div>
          <div class="desc">Quản lý kho & sản phẩm nội địa</div>
        </div>
        @if((string)$currentCompanyId === '1')
          <div class="check-mark"><i class="bi bi-check-circle-fill"></i></div>
        @endif
      </div>
    </div>

    <div class="col-12 col-md-4">
      <div class="ego-company-tab {{ (string)$currentCompanyId === '2' ? 'active' : '' }}"
           onclick="selectCompany('2')">
        <div class="icon-box"><i class="bi bi-globe-asia-australia"></i></div>
        <div class="info flex-grow-1">
          <div class="title">CÔNG TY TNHH TMKT QUỐC TẾ EGO</div>
          <div class="desc">Xuất nhập khẩu & Thương mại quốc tế</div>
        </div>
        @if((string)$currentCompanyId === '2')
          <div class="check-mark"><i class="bi bi-check-circle-fill"></i></div>
        @endif
      </div>
    </div>

    <div class="col-12 col-md-4">
      <div class="ego-company-tab {{ (string)$currentCompanyId === 'all' ? 'active' : '' }}"
           onclick="selectCompany('all')">
        <div class="icon-box"><i class="bi bi-collection"></i></div>
        <div class="info flex-grow-1">
          <div class="title">CẢ 2 CÔNG TY</div>
          <div class="desc">Gộp tồn kho của 2 công ty</div>
        </div>
        @if((string)$currentCompanyId === 'all')
          <div class="check-mark"><i class="bi bi-check-circle-fill"></i></div>
        @endif
      </div>
    </div>
  </div>

  {{-- FILTER FORM --}}
  <form method="GET" id="filterForm" class="mb-3">
    <input type="hidden" name="company_id" id="company_id_input" value="{{ $currentCompanyId }}">
    <input type="hidden" name="price_tier_id" id="price_tier_id" value="{{ $selectedPriceTier }}">

    <div class="card ego-card">
      <div class="card-body ego-card-body">
        <div class="row g-2 align-items-end">

          <div class="col-12 col-lg-3">
            <label class="form-label ego-label">Tìm kiếm</label>
            <div class="input-group ego-inputgroup">
              <span class="input-group-text"><i class="bi bi-search"></i></span>
              <input type="text" name="search" class="form-control"
                     placeholder="Tên sản phẩm, SKU..."
                     value="{{ request('search') }}">
            </div>
          </div>

          <div class="col-12 col-lg-3">
            <label class="form-label ego-label">Kho</label>
            <select name="warehouse_id" class="form-select ego-select">
              <option value="">Tất cả kho</option>
              @foreach($warehouses as $w)
                <option value="{{ $w->id }}" {{ (string)$selectedWarehouse === (string)$w->id ? 'selected' : '' }}>
                  {{ $w->name }}
                </option>
              @endforeach
            </select>
          </div>

          <div class="col-12 col-lg-3">
            <label class="form-label ego-label">Danh mục</label>
            <select name="category_id" class="form-select ego-select">
              <option value="">Tất cả danh mục</option>
              @foreach($categories as $c)
                @php $prefix = str_repeat('— ', (int)($c->level ?? 0)); @endphp
                <option value="{{ $c->id }}" {{ (string)$selectedCategory === (string)$c->id ? 'selected' : '' }}>
                  {{ $prefix }}{{ $c->name }}
                </option>
              @endforeach
            </select>
          </div>

          <div class="col-12 col-lg-3">
            <label class="form-label ego-label">Thương hiệu</label>
            <select name="brand_id" class="form-select ego-select">
              <option value="">Tất cả thương hiệu</option>
              @foreach($brands as $b)
                <option value="{{ $b->id }}" {{ (string)$selectedBrand === (string)$b->id ? 'selected' : '' }}>
                  {{ $b->name }}
                </option>
              @endforeach
            </select>
          </div>

          <div class="col-12 d-flex flex-wrap gap-2 mt-2">
            <button type="submit" class="btn ego-btn-primary">
              <i class="bi bi-funnel"></i> Lọc
            </button>
            <a href="{{ route('products.output', ['company_id' => $currentCompanyId]) }}" class="btn ego-btn-soft">
              <i class="bi bi-arrow-counterclockwise"></i> Reset
            </a>
          </div>

        </div>
      </div>
    </div>
  </form>

  {{-- TABLE --}}
  <div class="card ego-card">
    <div class="table-responsive ego-table-wrap">
      <table class="table align-middle mb-0 ego-products-table">
        <thead>
        <tr class="text-center">
          <th style="width: 78px;">STT</th>
          <th class="text-start" style="min-width: 320px;">Tên</th>
          <th class="text-start" style="width: 170px;">SKU</th>
          <th class="text-start" style="min-width: 280px;">Ghi chú</th>

          {{-- Giá bán trước VAT: có dropdown tier --}}
          <th style="width: 210px;">
            <div class="ego-pricehead">
              <select class="form-select form-select-sm ego-tier-select"
                      onchange="
                        document.getElementById('price_tier_id').value=this.value;
                        document.getElementById('filterForm').submit();
                      ">
                <option value="">Giá mặc định</option>
                @foreach($priceTiers as $t)
                  <option value="{{ $t->id }}" {{ (string)$selectedPriceTier === (string)$t->id ? 'selected' : '' }}>
                    {{ $t->name }}
                  </option>
                @endforeach
              </select>
            </div>
            <div class="small text-muted mt-1">Giá bán trước VAT</div>
          </th>

          <th style="width: 110px;">VAT</th>
          <th class="text-end" style="width: 180px;">Giá bán sau VAT</th>

          <th style="width: 120px;">Số lượng</th>
          <th style="width: 170px;">Danh mục</th>
          <th style="width: 170px;">Thương hiệu</th>
          <th style="width: 110px;">Hình ảnh</th>

          @if($canManageProducts)
            <th style="width: 190px;">Hành động</th>
          @endif
        </tr>
        </thead>

        <tbody>
        @php $rows = ($products && method_exists($products, 'items')) ? $products : null; @endphp

        @forelse(($rows ? $products : collect()) as $p)
          @php
            $isLotRow = !empty($p->stock_lot_id);
            $lotTitle = $p->stock_lot_name ?? $p->stock_lot_code ?? null;
            $tierRow = $selectedPriceTier ? $getTierRow($p, $selectedPriceTier) : null;

if ($selectedPriceTier && $tierRow) {
    $sellBefore = (float)($tierRow->price ?? 0);
    $vatPercent = (float)($tierRow->vat_percent ?? 0);
    $sellAfter  = (float)($tierRow->price_after_vat ?? ($sellBefore * (1 + $vatPercent / 100)));
} else {
    $sellBefore = (float)($p->price_retail ?? $p->price ?? 0);
    $vatPercent = (float)($p->vat_percent ?? 0);
    $sellAfter  = (float)($sellBefore * (1 + $vatPercent / 100));
}

            $displayQty = (int)($p->stocks_sum_qty ?? 0);

            $mainMedia = optional($p->mainImage)->media;
            $imageUrl = $mainMedia?->metadata->url
              ?? ($mainMedia?->file_path ? Storage::disk('public')->url($mainMedia->file_path) : null);

            $note = $p->note ?? $p->notes ?? $p->description ?? null;
          @endphp

          <tr>
            <td class="text-center">
              <span class="ego-stt">
                {{ ($products->currentPage() - 1) * $products->perPage() + $loop->iteration }}
              </span>
            </td>

            <td class="text-start">
              <div class="fw-semibold ego-name">{{ $p->name }}</div>
              @if($lotTitle)
                <div class="small text-muted mt-1">
                  <i class="bi bi-box-seam me-1"></i>{{ $lotTitle }}
                  @if(!empty($p->warehouse_name)) · {{ $p->warehouse_name }} @endif
                </div>
              @endif
            </td>

            <td class="text-start">
              @if(!empty($p->sku))
                <span class="ego-sku">{{ $p->sku }}</span>
              @else
                <span class="text-muted">—</span>
              @endif
            </td>

            <td class="text-start">
              <div class="ego-note {{ $note ? '' : 'text-muted' }}" title="{{ $note ?? '' }}">
                {{ $note ?: '—' }}
              </div>
            </td>

            {{-- Giá bán trước VAT --}}
            <td class="text-end">
              <span class="ego-money text-dark">{{ number_format($sellBefore) }}</span>
              @if($selectedPriceTier)
                <div class="small text-muted">Theo tier</div>
              @endif
            </td>

            {{-- VAT --}}
            <td class="text-center">
              <div class="small text-muted">VAT</div>
              <div class="fw-bold">
                {{ rtrim(rtrim(number_format($vatPercent, 2), '0'), '.') }}%
              </div>
            </td>

            {{-- Giá bán sau VAT --}}
            <td class="text-end">
              <span class="ego-money text-success">{{ number_format($sellAfter) }}</span>
            </td>

            <td class="text-center">
              <span class="ego-qty {{ (int)$displayQty <= 0 ? 'is-zero' : '' }}">
                {{ number_format((int)$displayQty) }}
              </span>
            </td>

            <td class="text-center">
              @if($p->category)
                <span class="ego-badge">{{ $p->category->name }}</span>
              @else
                <span class="text-muted">—</span>
              @endif
            </td>

            <td class="text-center">
              @if($p->brand)
                <span class="ego-badge ego-badge-brand">{{ $p->brand->name }}</span>
              @else
                <span class="text-muted">—</span>
              @endif
            </td>

            <td class="text-center">
              @if($imageUrl)
                <a href="{{ $imageUrl }}" target="_blank" class="ego-thumb" title="Mở ảnh">
                  <img src="{{ $imageUrl }}"
                       alt="{{ $p->name }}"
                       width="42" height="42"
                       class="rounded-3 border object-fit-cover">
                </a>
              @else
                <span class="text-muted small">Không có</span>
              @endif
            </td>

            @if($canManageProducts)
              <td class="text-center">
                <div class="d-inline-flex gap-2">
                  @if(!empty($p->id))
                    <a href="{{ route('products.edit', ['product' => $p->id] + request()->query()) }}" class="btn btn-sm ego-btn-warn">
                      <i class="bi bi-pencil-square"></i> Sửa
                    </a>
                  @endif

                  @if(!empty($p->id))
                    <form action="{{ route('products.destroy', $p->id) }}" method="POST" class="d-inline">
                      @csrf
                      @method('DELETE')
                      <button class="btn btn-sm ego-btn-danger"
                              onclick="return confirm('Bạn chắc chắn muốn xóa?')">
                        <i class="bi bi-trash"></i> Xóa
                      </button>
                    </form>
                  @endif
                </div>
              </td>
            @endif
          </tr>
        @empty
          <tr>
            <td colspan="{{ $colspan }}" class="text-center text-muted py-4">
              Không có dữ liệu
            </td>
          </tr>
        @endforelse
        </tbody>
      </table>
    </div>

    <div class="card-body d-flex justify-content-end py-2">
      @if($products && method_exists($products, 'links'))
        {{ $products->appends(request()->query())->links('pagination::bootstrap-5') }}
      @endif
    </div>
  </div>

</div>

<script>
  function selectCompany(id) {
    const url = new URL(window.location.href);
    url.searchParams.set('company_id', id);
    url.searchParams.delete('warehouse_id');
    url.searchParams.delete('category_id');
    url.searchParams.delete('brand_id');
    window.location.href = url.toString();
  }
</script>

<style>
  .ego-products-page{
    --ego:#0E7C86;
    --ego2:#0B5E66;
    --border: rgba(12, 92, 100, .12);
    --muted:#64748b;
  }

  .ego-table-wrap{
    overflow-x:auto;
    overflow-y:visible;
  }

  .ego-products-table thead th{
    position:sticky;
    top:0;
    z-index:3;
  }

  .ego-tier-select{
    position:relative;
    z-index:10;
  }

  /* ✅ FULL CSS (đồng bộ như input) */
  .ego-company-tab{ background:#fff;border:1px solid var(--border);border-radius:16px;padding:16px 20px;cursor:pointer;display:flex;align-items:center;gap:16px;position:relative;transition:all .2s ease;box-shadow:0 4px 12px rgba(15,23,42,.03);height:100%; }
  .ego-company-tab:hover{ transform:translateY(-2px);box-shadow:0 8px 20px rgba(14,124,134,.15);border-color:var(--ego); }
  .ego-company-tab.active{ background:linear-gradient(135deg, rgba(14,124,134,0.06), rgba(14,124,134,0.01));border:2px solid var(--ego); }
  .ego-company-tab .icon-box{ width:50px;height:50px;border-radius:14px;background:rgba(14,124,134,.08);color:var(--ego2);display:flex;align-items:center;justify-content:center;font-size:1.5rem;flex-shrink:0; }
  .ego-company-tab.active .icon-box{ background:var(--ego);color:#fff;box-shadow:0 4px 10px rgba(14,124,134,.25); }
  .ego-company-tab .info .title{ font-weight:800;color:#0f172a;font-size:.95rem;text-transform:uppercase;margin-bottom:2px;line-height:1.3; }
  .ego-company-tab.active .info .title{ color:var(--ego2); }
  .ego-company-tab .info .desc{ font-size:.8rem;color:var(--muted); }
  .ego-company-tab .check-mark{ position:absolute;top:10px;right:12px;color:var(--ego);font-size:1.2rem; }

  .ego-products-table{ width: max-content; min-width: 1200px; table-layout: auto; }
  .ego-page-dot{ width:9px;height:9px;border-radius:999px;background:linear-gradient(135deg,var(--ego),var(--ego2));box-shadow:0 8px 18px rgba(14,124,134,.22); }
  .ego-card{ border:1px solid var(--border);border-radius:16px;overflow:hidden;background:#fff;box-shadow:0 10px 24px rgba(15,23,42,.04); }
  .ego-card-body{ padding:14px; }
  .ego-label{ font-size:11px;font-weight:900;letter-spacing:.35px;color:var(--muted);text-transform:uppercase;margin-bottom:6px; }
  .ego-inputgroup .input-group-text{ border-radius:12px 0 0 12px;border:1px solid var(--border);background:rgba(14,124,134,.06);color:var(--ego2);padding:.45rem .6rem; }
  .ego-inputgroup .form-control{ border-radius:0 12px 12px 0;border:1px solid var(--border);padding:.45rem .7rem;font-size:.92rem; }
  .ego-select{ border-radius:12px;border:1px solid var(--border);padding:.45rem .7rem;font-size:.92rem; }
  .ego-btn-primary{ background:linear-gradient(135deg,var(--ego),var(--ego2));border:none;color:#fff;border-radius:12px;padding:8px 12px;font-weight:900;box-shadow:0 10px 22px rgba(14,124,134,.16);display:inline-flex;align-items:center;gap:8px;font-size:.92rem; }
  .ego-btn-primary:hover{ filter:brightness(.98);color:#fff; }
  .ego-btn-soft{ background:rgba(14,124,134,.10);border:1px solid var(--border);color:var(--ego2);border-radius:12px;padding:8px 12px;font-weight:900;display:inline-flex;align-items:center;gap:8px;font-size:.92rem; }

  .ego-products-table thead th{ background:linear-gradient(135deg, rgba(14,124,134,.10), rgba(14,124,134,.03));border-bottom:1px solid var(--border);font-size:11px;font-weight:900;letter-spacing:.35px;color:var(--muted);text-transform:uppercase;white-space:nowrap;vertical-align:middle;padding:12px 10px; }
  .ego-products-table tbody td{ border-top:1px solid rgba(15,23,42,.06);padding:10px 10px;vertical-align:middle;background:#fff;font-size:.93rem; }
  .ego-products-table tbody tr:nth-child(2n) td{ background:rgba(15,23,42,.015); }
  .ego-products-table tbody tr:hover td{ background:rgba(14,124,134,.05);transition:background .15s ease; }

  .ego-stt{ display:inline-flex;align-items:center;justify-content:center;min-width:34px;height:28px;padding:0 10px;border-radius:999px;background:rgba(14,124,134,.10);color:var(--ego2);font-weight:900;font-size:.85rem; }
  .ego-name{ color:#0f172a;line-height:1.2; }
  .ego-note{ color:#111827;font-weight:500;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;line-height:1.25rem;max-width:720px; }
  .ego-money{ font-weight:900;letter-spacing:.2px; }
  .ego-qty{ display:inline-flex;padding:5px 9px;border-radius:999px;border:1px solid rgba(15,23,42,.08);background:#fff;font-weight:900;min-width:58px;justify-content:center;font-size:.9rem; }
  .ego-qty.is-zero{ color:#b4232c;border-color:rgba(220,53,69,.25);background:rgba(220,53,69,.06); }
  .ego-badge{ display:inline-flex;padding:5px 9px;border-radius:999px;background:rgba(13,202,240,.14);color:#075d6d;border:1px solid rgba(13,202,240,.22);font-weight:900;font-size:11px;white-space:nowrap; }
  .ego-badge-brand{ background:rgba(99,102,241,.12);border:1px solid rgba(99,102,241,.20);color:#3730a3; }
  .ego-thumb img{ transition:transform .15s ease; }
  .ego-thumb:hover img{ transform:scale(1.06); }
  .ego-btn-warn{ border-radius:12px;font-weight:900;border:1px solid rgba(255,193,7,.35);background:rgba(255,193,7,.12);color:#7a5b00;display:inline-flex;align-items:center;gap:6px;padding:7px 10px;font-size:.88rem;white-space:nowrap; }
  .ego-btn-danger{ border-radius:12px;font-weight:900;border:1px solid rgba(220,53,69,.35);background:rgba(220,53,69,.10);color:#b4232c;display:inline-flex;align-items:center;gap:6px;padding:7px 10px;font-size:.88rem;white-space:nowrap; }
  .ego-sku{ display:inline-flex;padding:5px 10px;border-radius:999px;border:1px solid rgba(15,23,42,.10);background:rgba(255,255,255,.85);font-weight:900;font-size:12px;color:#0f172a;white-space:nowrap; }

  @media (max-width: 991.98px){
    .ego-products-table thead th{ position:static; }
    .ego-products-table{ min-width:980px; }
  }


  /* EGO_FLOAT_TABLE_SCROLL_START */
  .ego-floating-table-scroll{
    position: fixed;
    left: 260px;
    right: 24px;
    bottom: 18px;
    z-index: 9999;
    height: 22px;
    overflow-x: auto;
    overflow-y: hidden;
    background: rgba(255,255,255,.96);
    border: 1px solid rgba(14,124,134,.22);
    border-radius: 999px;
    box-shadow: 0 12px 34px rgba(15,23,42,.18);
    backdrop-filter: blur(10px);
    display: none;
  }

  .ego-floating-table-scroll-inner{
    height: 1px;
  }

  .ego-floating-table-scroll::-webkit-scrollbar{
    height: 16px;
  }

  .ego-floating-table-scroll::-webkit-scrollbar-track{
    background: #e2e8f0;
    border-radius: 999px;
  }

  .ego-floating-table-scroll::-webkit-scrollbar-thumb{
    background: #0E7C86;
    border-radius: 999px;
    border: 3px solid #e2e8f0;
  }

  .ego-floating-table-scroll::-webkit-scrollbar-thumb:hover{
    background: #0B5E66;
  }

  @media (max-width: 991.98px){
    .ego-floating-table-scroll{
      left: 16px;
      right: 16px;
      bottom: 14px;
    }
  }
  /* EGO_FLOAT_TABLE_SCROLL_END */

</style>


<script>
document.addEventListener('DOMContentLoaded', function () {
    const wraps = Array.from(document.querySelectorAll('.ego-table-wrap'));
    if (!wraps.length) return;

    let activeWrap = null;
    let activeTable = null;
    let syncing = false;

    const floating = document.createElement('div');
    floating.className = 'ego-floating-table-scroll';
    floating.innerHTML = '<div class="ego-floating-table-scroll-inner"></div>';
    document.body.appendChild(floating);

    const inner = floating.querySelector('.ego-floating-table-scroll-inner');

    function pickActiveWrap() {
        let best = null;
        let bestScore = -Infinity;

        wraps.forEach(function (wrap) {
            const rect = wrap.getBoundingClientRect();
            const visible = rect.bottom > 120 && rect.top < window.innerHeight - 80;

            if (!visible) return;

            const score = Math.min(rect.bottom, window.innerHeight) - Math.max(rect.top, 0);

            if (score > bestScore) {
                bestScore = score;
                best = wrap;
            }
        });

        activeWrap = best;
        activeTable = activeWrap ? activeWrap.querySelector('table') : null;
    }

    function updateFloating() {
        pickActiveWrap();

        if (!activeWrap || !activeTable) {
            floating.style.display = 'none';
            return;
        }

        const needScroll = activeTable.scrollWidth > activeWrap.clientWidth + 8;

        if (!needScroll) {
            floating.style.display = 'none';
            return;
        }

        const rect = activeWrap.getBoundingClientRect();

        floating.style.display = 'block';
        floating.style.left = Math.max(rect.left, 12) + 'px';
        floating.style.width = Math.min(rect.width, window.innerWidth - Math.max(rect.left, 12) - 24) + 'px';

        inner.style.width = activeTable.scrollWidth + 'px';

        if (!syncing) {
            floating.scrollLeft = activeWrap.scrollLeft;
        }
    }

    wraps.forEach(function (wrap) {
        wrap.addEventListener('scroll', function () {
            if (syncing) return;
            if (wrap !== activeWrap) updateFloating();

            syncing = true;
            floating.scrollLeft = wrap.scrollLeft;
            syncing = false;
        });
    });

    floating.addEventListener('scroll', function () {
        if (syncing || !activeWrap) return;

        syncing = true;
        activeWrap.scrollLeft = floating.scrollLeft;
        syncing = false;
    });

    window.addEventListener('scroll', updateFloating, { passive: true });
    window.addEventListener('resize', updateFloating);

    updateFloating();
    setTimeout(updateFloating, 300);
    setTimeout(updateFloating, 1000);
});
</script>

@endsection