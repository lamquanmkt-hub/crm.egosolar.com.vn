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

  $canViewCost = $productPolicyModel
    ? (auth()->user()?->can('viewCost', $productPolicyModel) ?? false)
    : false;

  $showActionsCol = true;

  $baseCols = 12;
  $colspan  = $baseCols + ($showActionsCol ? 1 : 0);

  $totalQtyAll = (int)($totalQtyAll ?? 0);
  $totalAmountAll = (float)($totalAmountAll ?? 0);
@endphp

@section('content')
<div class="container-fluid px-3 px-lg-4 mt-3 ego-products-page">

  <div class="d-flex flex-wrap justify-content-between align-items-end gap-2 mb-3">
    <div>
      <div class="d-flex align-items-center gap-2">
        <div class="ego-page-dot"></div>
        <h3 class="fw-bold mb-0">Sản phẩm đầu vào</h3>
      </div>
      <div class="text-muted small">Giá vốn trước VAT / VAT / Giá vốn sau VAT</div>
    </div>

    <div class="d-flex flex-wrap gap-2 align-items-center">
      <div class="d-flex flex-wrap gap-2 me-1">
        <div class="ego-summary-card">
          <div class="ico"><i class="bi bi-box-seam"></i></div>
          <div class="meta">
            <div class="label">Tổng sản phẩm</div>
            <div class="value">{{ number_format($totalQtyAll) }}</div>
          </div>
        </div>

        <div class="ego-summary-card is-money">
          <div class="ico"><i class="bi bi-cash-coin"></i></div>
          <div class="meta">
            <div class="label">Tổng giá tiền</div>
            <div class="value {{ $canViewCost ? 'text-success' : 'text-muted' }}">
              {{ $canViewCost ? number_format($totalAmountAll) . ' đ' : '—' }}
            </div>
          </div>
        </div>
      </div>

      <div class="input-action-buttons">
        <a href="{{ route('products.input.export.excel', request()->query()) }}" class="btn-input-excel">
          <i class="bi bi-file-earmark-excel"></i>
          Xuất Excel
        </a>

        @if($productPolicyModel && auth()->user()?->can('create', $productPolicyModel))
          <a href="{{ route('products.create', request()->query()) }}" class="btn ego-btn-primary">
            <i class="bi bi-plus-circle"></i>
            Thêm sản phẩm
          </a>
        @endif
      </div>

    </div>
  </div>

  @if(session('success'))
    <div class="alert alert-success alert-dismissible fade show py-2" role="alert">
      <i class="bi bi-check2-circle me-1"></i>
      {{ session('success') }}
      <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
  @endif

  <form method="GET" id="filterForm" class="mb-3">
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
            <label class="form-label ego-label">Công ty</label>
            <select name="company_id" class="form-select ego-select">
              <option value="all" {{ (string)$currentCompanyId === 'all' || (string)$currentCompanyId === '' ? 'selected' : '' }}>
                Tất cả công ty
              </option>
              <option value="1" {{ (string)$currentCompanyId === '1' ? 'selected' : '' }}>
                CÔNG TY TNHH EGO VIỆT NAM
              </option>
              <option value="2" {{ (string)$currentCompanyId === '2' ? 'selected' : '' }}>
                CÔNG TY TNHH EGO QUỐC TẾ
              </option>
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
            <a href="{{ route('products.input') }}" class="btn ego-btn-soft">
              <i class="bi bi-arrow-counterclockwise"></i> Reset
            </a>
          </div>

        </div>
      </div>
    </div>
  </form>

  <div class="card ego-card">
    <div class="table-responsive ego-table-wrap">
      <table class="table align-middle mb-0 ego-products-table">
        <thead>
        <tr class="text-center">
          <th style="width: 78px;">STT</th>
          <th class="text-start" style="min-width: 360px;">Tên</th>
          <th class="text-start" style="width: 170px;">SKU</th>
          <th class="text-start" style="min-width: 280px;">Ghi chú</th>
          <th class="text-end" style="width: 170px;">Giá vốn trước VAT</th>
          <th style="width: 110px;">VAT</th>
          <th class="text-end" style="width: 170px;">Giá vốn sau VAT</th>
          <th style="width: 120px;">Số lượng</th>
          <th class="text-end" style="width: 190px;">Tổng tiền</th>
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
            $vatPercent = (float)($p->vat_percent ?? 0);

            $costBefore = (float)($p->price_agent ?? 0);
            $costAfter  = (float)($p->price_agent_vat ?? ($costBefore * (1 + $vatPercent / 100)));

            $displayQty = (int)($p->stocks_sum_qty ?? 0);
            $rowAmount = $costAfter * $displayQty;

            $mainMedia = optional($p->mainImage)->media;
            $imageUrl = $mainMedia?->metadata->url
              ?? ($mainMedia?->file_path ? Storage::disk('public')->url($mainMedia->file_path) : null);

            $note = $p->note ?? $p->notes ?? $p->description ?? null;

            $warehouseStocks = collect($p->stocks ?? [])
              ->filter(fn($s) => (float)($s->qty ?? 0) > 0)
              ->groupBy('warehouse_id')
              ->map(function ($items) {
                  $first = $items->first();
                  return [
                      'name' => $first?->warehouse?->name ?? 'Kho không tên',
                      'qty'  => (float) $items->sum('qty'),
                  ];
              })
              ->sortByDesc('qty')
              ->values();
          @endphp

          <tr>
            <td class="text-center">
              <span class="ego-stt">
                {{ ($products->currentPage() - 1) * $products->perPage() + $loop->iteration }}
              </span>
            </td>

            <td class="text-start">
              <div class="fw-semibold ego-name">{{ $p->name }}</div>

              <div class="ego-stock-note mt-2">
                @if($warehouseStocks->count())
                  <div class="ego-stock-note-label">
                    <i class="bi bi-boxes me-1"></i> Tồn theo kho
                  </div>

                  <div class="ego-stock-badges">
                    @foreach($warehouseStocks as $ws)
                      <span class="ego-stock-badge">
                        <span class="warehouse">{{ $ws['name'] }}</span>
                        <span class="qty">{{ number_format($ws['qty']) }}</span>
                      </span>
                    @endforeach
                  </div>
                @else
                  <div class="ego-stock-empty">
                    <i class="bi bi-info-circle me-1"></i> Chưa có tồn kho
                  </div>
                @endif
              </div>
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

            <td class="text-end">
              <span class="ego-money {{ $canViewCost ? 'text-dark' : 'text-muted' }}">
                {{ $canViewCost ? number_format($costBefore) : '—' }}
              </span>
            </td>

            <td class="text-center">
              <div class="small text-muted">VAT</div>
              <div class="fw-bold">
                {{ rtrim(rtrim(number_format($vatPercent, 2), '0'), '.') }}%
              </div>
            </td>

            <td class="text-end">
              <span class="ego-money {{ $canViewCost ? 'text-success' : 'text-muted' }}">
                {{ $canViewCost ? number_format($costAfter) : '—' }}
              </span>
            </td>

            <td class="text-center">
              <span class="ego-qty {{ (int)$displayQty <= 0 ? 'is-zero' : '' }}">
                {{ number_format((int)$displayQty) }}
              </span>
            </td>

            <td class="text-end">
              <span class="ego-money {{ $canViewCost ? 'text-success' : 'text-muted' }}">
                {{ $canViewCost ? number_format($rowAmount) . ' đ' : '—' }}
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

        @if($products && method_exists($products, 'total') && $products->total() > 0)
          <tfoot>
          <tr class="ego-total-row">
            <td colspan="7" class="text-center">
              <span class="ego-total-title">TỔNG CỘNG</span>
            </td>

            <td class="text-center">
              <span class="ego-qty">{{ number_format($totalQtyAll) }}</span>
            </td>

            <td class="text-end">
              <span class="ego-money {{ $canViewCost ? 'text-success' : 'text-muted' }}">
                {{ $canViewCost ? number_format($totalAmountAll) . ' đ' : '—' }}
              </span>
            </td>

            <td colspan="{{ ($canManageProducts ? 4 : 3) }}"></td>
          </tr>
          </tfoot>
        @endif
      </table>
    </div>

    <div class="card-body d-flex justify-content-end py-2">
      @if($products && method_exists($products, 'links'))
        {{ $products->appends(request()->query())->links('pagination::bootstrap-5') }}
      @endif
    </div>
  </div>

</div>

<style>
  .ego-products-page{
    --ego:#0E7C86;
    --ego2:#0B5E66;
    --border: rgba(12, 92, 100, .12);
    --muted:#64748b;
    --text:#0f172a;
    --soft:#f8fafc;
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

  .ego-summary-card{
    background:#fff;
    border:1px solid var(--border);
    border-radius:16px;
    padding:10px 14px;
    display:flex;
    align-items:center;
    gap:10px;
    box-shadow:0 10px 24px rgba(15,23,42,.04);
    min-width:220px;
  }
  .ego-summary-card .ico{
    width:42px;height:42px;border-radius:12px;
    background:rgba(14,124,134,.10);
    color:var(--ego2);
    display:flex;align-items:center;justify-content:center;
    font-size:1.2rem;
    flex-shrink:0;
  }
  .ego-summary-card.is-money .ico{
    background:rgba(34,197,94,.12);
    color:#0f7a3a;
  }
  .ego-summary-card .label{
    font-size:11px;
    font-weight:900;
    letter-spacing:.35px;
    color:var(--muted);
    text-transform:uppercase;
    line-height:1.1;
  }
  .ego-summary-card .value{
    font-weight:900;
    font-size:1.05rem;
    margin-top:2px;
    color:#0f172a;
  }

  .ego-total-row td{
    background:rgba(14,124,134,.06) !important;
    border-top:1px solid var(--border);
    padding:14px 10px;
  }
  .ego-total-title{
    font-weight:900;
    letter-spacing:.6px;
    padding:6px 14px;
    border-radius:999px;
    border:1px dashed rgba(14,124,134,.35);
    color:var(--ego2);
    background:rgba(255,255,255,.65);
    display:inline-flex;
  }

  .ego-products-table{
    width:max-content;
    min-width:1280px;
    table-layout:auto;
  }

  .ego-page-dot{
    width:9px;height:9px;border-radius:999px;
    background:linear-gradient(135deg,var(--ego),var(--ego2));
    box-shadow:0 8px 18px rgba(14,124,134,.22);
  }

  .ego-card{
    border:1px solid var(--border);
    border-radius:16px;
    overflow:hidden;
    background:#fff;
    box-shadow:0 10px 24px rgba(15,23,42,.04);
  }

  .ego-card-body{ padding:14px; }

  .ego-label{
    font-size:11px;
    font-weight:900;
    letter-spacing:.35px;
    color:var(--muted);
    text-transform:uppercase;
    margin-bottom:6px;
  }

  .ego-inputgroup .input-group-text{
    border-radius:12px 0 0 12px;
    border:1px solid var(--border);
    background:rgba(14,124,134,.06);
    color:var(--ego2);
    padding:.45rem .6rem;
  }

  .ego-inputgroup .form-control{
    border-radius:0 12px 12px 0;
    border:1px solid var(--border);
    padding:.45rem .7rem;
    font-size:.92rem;
  }

  .ego-select{
    border-radius:12px;
    border:1px solid var(--border);
    padding:.45rem .7rem;
    font-size:.92rem;
  }

  .ego-btn-primary{
    background:linear-gradient(135deg,var(--ego),var(--ego2));
    border:none;
    color:#fff;
    border-radius:12px;
    padding:8px 12px;
    font-weight:900;
    box-shadow:0 10px 22px rgba(14,124,134,.16);
    display:inline-flex;
    align-items:center;
    gap:8px;
    font-size:.92rem;
  }
  .ego-btn-primary:hover{ filter:brightness(.98);color:#fff; }

  .ego-btn-soft{
    background:rgba(14,124,134,.10);
    border:1px solid var(--border);
    color:var(--ego2);
    border-radius:12px;
    padding:8px 12px;
    font-weight:900;
    display:inline-flex;
    align-items:center;
    gap:8px;
    font-size:.92rem;
  }

  .ego-products-table thead th{
    background:linear-gradient(135deg, rgba(14,124,134,.10), rgba(14,124,134,.03));
    border-bottom:1px solid var(--border);
    font-size:11px;
    font-weight:900;
    letter-spacing:.35px;
    color:var(--muted);
    text-transform:uppercase;
    white-space:nowrap;
    vertical-align:middle;
    padding:12px 10px;
  }

  .ego-products-table tbody td{
    border-top:1px solid rgba(15,23,42,.06);
    padding:12px 10px;
    vertical-align:middle;
    background:#fff;
    font-size:.93rem;
  }

  .ego-products-table tbody tr:nth-child(2n) td{
    background:rgba(15,23,42,.015);
  }

  .ego-products-table tbody tr:hover td{
    background:rgba(14,124,134,.05);
    transition:background .15s ease;
  }

  .ego-stt{
    display:inline-flex;
    align-items:center;
    justify-content:center;
    min-width:34px;
    height:28px;
    padding:0 10px;
    border-radius:999px;
    background:rgba(14,124,134,.10);
    color:var(--ego2);
    font-weight:900;
    font-size:.85rem;
  }

  .ego-name{
    color:var(--text);
    line-height:1.2;
    font-size:1rem;
  }

  .ego-stock-note{
    margin-top:8px;
  }

  .ego-stock-note-label{
    display:flex;
    align-items:center;
    font-size:12px;
    font-weight:800;
    color:var(--muted);
    margin-bottom:6px;
  }

  .ego-stock-badges{
    display:flex;
    flex-wrap:wrap;
    gap:6px;
  }

  .ego-stock-badge{
    display:inline-flex;
    align-items:center;
    gap:8px;
    padding:5px 10px;
    border-radius:999px;
    background:linear-gradient(135deg, rgba(14,124,134,.10), rgba(14,124,134,.05));
    border:1px solid rgba(14,124,134,.16);
    color:#0b5e66;
    font-size:12px;
    font-weight:800;
    line-height:1;
  }

  .ego-stock-badge .warehouse{
    max-width:180px;
    overflow:hidden;
    text-overflow:ellipsis;
    white-space:nowrap;
  }

  .ego-stock-badge .qty{
    display:inline-flex;
    align-items:center;
    justify-content:center;
    min-width:24px;
    height:24px;
    padding:0 8px;
    border-radius:999px;
    background:#fff;
    color:#0f172a;
    font-weight:900;
    box-shadow:0 2px 8px rgba(15,23,42,.06);
  }

  .ego-stock-empty{
    display:inline-flex;
    align-items:center;
    gap:4px;
    color:#94a3b8;
    font-size:12px;
    font-weight:700;
    background:#f8fafc;
    border:1px dashed rgba(148,163,184,.35);
    border-radius:999px;
    padding:6px 10px;
  }

  .ego-note{
    color:#111827;
    font-weight:500;
    display:-webkit-box;
    -webkit-line-clamp:2;
    -webkit-box-orient:vertical;
    overflow:hidden;
    line-height:1.25rem;
    max-width:720px;
  }

  .ego-money{
    font-weight:900;
    letter-spacing:.2px;
  }

  .ego-qty{
    display:inline-flex;
    padding:5px 9px;
    border-radius:999px;
    border:1px solid rgba(15,23,42,.08);
    background:#fff;
    font-weight:900;
    min-width:58px;
    justify-content:center;
    font-size:.9rem;
  }

  .ego-qty.is-zero{
    color:#b4232c;
    border-color:rgba(220,53,69,.25);
    background:rgba(220,53,69,.06);
  }

  .ego-badge{
    display:inline-flex;
    padding:5px 9px;
    border-radius:999px;
    background:rgba(13,202,240,.14);
    color:#075d6d;
    border:1px solid rgba(13,202,240,.22);
    font-weight:900;
    font-size:11px;
    white-space:nowrap;
  }

  .ego-badge-brand{
    background:rgba(99,102,241,.12);
    border:1px solid rgba(99,102,241,.20);
    color:#3730a3;
  }

  .ego-thumb img{ transition:transform .15s ease; }
  .ego-thumb:hover img{ transform:scale(1.06); }

  .ego-btn-warn{
    border-radius:12px;
    font-weight:900;
    border:1px solid rgba(255,193,7,.35);
    background:rgba(255,193,7,.12);
    color:#7a5b00;
    display:inline-flex;
    align-items:center;
    gap:6px;
    padding:7px 10px;
    font-size:.88rem;
    white-space:nowrap;
  }

  .ego-btn-danger{
    border-radius:12px;
    font-weight:900;
    border:1px solid rgba(220,53,69,.35);
    background:rgba(220,53,69,.10);
    color:#b4232c;
    display:inline-flex;
    align-items:center;
    gap:6px;
    padding:7px 10px;
    font-size:.88rem;
    white-space:nowrap;
  }

  .ego-sku{
    display:inline-flex;
    padding:5px 10px;
    border-radius:999px;
    border:1px solid rgba(15,23,42,.10);
    background:rgba(255,255,255,.85);
    font-weight:900;
    font-size:12px;
    color:#0f172a;
    white-space:nowrap;
  }

  @media (max-width: 991.98px){
    .ego-products-table thead th{ position:static; }
    .ego-products-table{ min-width:1080px; }
    .ego-summary-card{ min-width:190px; }
  }

    .btn-input-excel{
        border: none;
        border-radius: 14px;
        padding: 10px 14px;
        font-weight: 950;
        background: linear-gradient(135deg, #16a34a, #15803d);
        box-shadow: 0 14px 34px rgba(22,163,74,.18);
        transition: .15s ease;
        color: #fff;
        text-decoration: none;
        display:inline-flex;
        align-items:center;
        gap:6px;
    }

    .btn-input-excel:hover{
        transform: translateY(-1px);
        box-shadow: 0 18px 44px rgba(22,163,74,.24);
        color:#fff;
    }


  .input-action-buttons{
    display:flex;
    align-items:center;
    justify-content:flex-end;
    gap:10px;
    flex-wrap:wrap;
  }

  .input-action-buttons a{
    white-space:nowrap;
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


@push('styles')
<style>
/* EGO_PRODUCT_INPUT_COMPACT_UI_START */
body.ego-product-input-page{
    --ego-pi-ink:#0f172a;
    --ego-pi-muted:#64748b;
    --ego-pi-cyan:#0891b2;
}

/* Font tổng thể của trang sản phẩm đầu vào nhỏ hơn nhẹ */
body.ego-product-input-page .ego-page__body,
body.ego-product-input-page .container-fluid,
body.ego-product-input-page .card,
body.ego-product-input-page table,
body.ego-product-input-page .table,
body.ego-product-input-page input,
body.ego-product-input-page select,
body.ego-product-input-page button{
    font-size:12.5px !important;
}

/* Tiêu đề trang vẫn nổi bật nhưng gọn hơn */
body.ego-product-input-page h1,
body.ego-product-input-page h2,
body.ego-product-input-page .page-title,
body.ego-product-input-page .fw-bold{
    letter-spacing:-.025em;
}

/* Header "Sản phẩm đầu vào" nhỏ lại vừa phải */
body.ego-product-input-page h1{
    font-size:28px !important;
    line-height:1.12;
}

body.ego-product-input-page h2,
body.ego-product-input-page h3{
    font-size:20px !important;
}

/* Các ô tổng quan phía trên */
body.ego-product-input-page .card,
body.ego-product-input-page .card-glass,
body.ego-product-input-page .summary-card,
body.ego-product-input-page .filter-card{
    border-radius:18px !important;
    transition:
        transform .28s cubic-bezier(.22,1,.36,1),
        box-shadow .28s cubic-bezier(.22,1,.36,1),
        border-color .28s ease,
        background .28s ease;
}

/* Hover nhẹ, không nhảy mạnh */
body.ego-product-input-page .card:hover,
body.ego-product-input-page .card-glass:hover,
body.ego-product-input-page .summary-card:hover,
body.ego-product-input-page .filter-card:hover{
    transform:translateY(-2px);
    box-shadow:0 18px 42px rgba(15,23,42,.09) !important;
}

/* Bộ lọc gọn hơn */
body.ego-product-input-page label,
body.ego-product-input-page .form-label{
    font-size:11px !important;
    font-weight:850 !important;
    color:rgba(15,23,42,.62) !important;
    text-transform:uppercase;
    letter-spacing:.025em;
}

body.ego-product-input-page .form-control,
body.ego-product-input-page .form-select{
    min-height:38px !important;
    height:38px !important;
    border-radius:13px !important;
    font-size:12.5px !important;
    font-weight:700;
}

/* Button nhỏ gọn hơn */
body.ego-product-input-page .btn{
    min-height:36px;
    border-radius:12px !important;
    font-size:12.5px !important;
    font-weight:850 !important;
    transition:
        transform .18s cubic-bezier(.22,1,.36,1),
        box-shadow .18s ease,
        filter .18s ease;
}

body.ego-product-input-page .btn:hover{
    transform:translateY(-1px);
    filter:brightness(1.03);
}

/* Bảng nhỏ gọn hơn */
body.ego-product-input-page table thead th{
    font-size:10.8px !important;
    letter-spacing:.035em;
    text-transform:uppercase;
    color:rgba(15,23,42,.60) !important;
    padding:11px 10px !important;
    white-space:nowrap;
}

body.ego-product-input-page table tbody td{
    font-size:12.2px !important;
    padding:12px 10px !important;
    vertical-align:middle !important;
}

body.ego-product-input-page table tbody td strong,
body.ego-product-input-page table tbody td .fw-bold{
    font-size:13px !important;
}

/* Badge / pill nhỏ lại */
body.ego-product-input-page .badge,
body.ego-product-input-page .pill,
body.ego-product-input-page [class*="badge"],
body.ego-product-input-page [class*="pill"]{
    font-size:10.8px !important;
    font-weight:850 !important;
}

/* Hover dòng bảng đẹp hơn, không bị gắt */
body.ego-product-input-page table tbody tr{
    transition:
        transform .22s cubic-bezier(.22,1,.36,1),
        box-shadow .22s ease,
        background .22s ease;
}

body.ego-product-input-page table tbody tr:hover{
    transform:translateY(-1px);
    background:
        linear-gradient(90deg, rgba(14,165,233,.055), rgba(255,255,255,.92)) !important;
    box-shadow:inset 3px 0 0 rgba(14,165,233,.70);
}

/* Hiệu ứng vào trang */
body.ego-product-input-page .ego-pi-animate{
    opacity:0;
    transform:translateY(12px);
    animation:egoPiFadeUp .56s cubic-bezier(.22,1,.36,1) forwards;
}

body.ego-product-input-page .ego-pi-row-animate{
    opacity:0;
    transform:translateY(8px);
    animation:egoPiRowIn .42s cubic-bezier(.22,1,.36,1) forwards;
}

@keyframes egoPiFadeUp{
    to{
        opacity:1;
        transform:translateY(0);
    }
}

@keyframes egoPiRowIn{
    to{
        opacity:1;
        transform:translateY(0);
    }
}

/* Số tiền gọn và nét */
body.ego-product-input-page table .text-success,
body.ego-product-input-page table .text-primary,
body.ego-product-input-page table .text-danger{
    font-weight:900 !important;
}

/* Giảm khoảng cách tổng thể nhẹ */
body.ego-product-input-page .ego-container,
body.ego-product-input-page .container-fluid{
    padding-top:16px !important;
}

/* Mobile */
@media(max-width: 768px){
    body.ego-product-input-page h1{
        font-size:24px !important;
    }

    body.ego-product-input-page table tbody td{
        font-size:11.8px !important;
    }
}
/* EGO_PRODUCT_INPUT_COMPACT_UI_END */
</style>
@endpush



@push('scripts')
<script>
/* EGO_PRODUCT_INPUT_COMPACT_UI_JS_START */
document.addEventListener('DOMContentLoaded', function(){
    if (!location.pathname.includes('/products/input')) return;

    document.body.classList.add('ego-product-input-page');

    const animatedBlocks = [
        ...document.querySelectorAll('.ego-page__body > .container-fluid > *'),
        ...document.querySelectorAll('.summary-card, .card-glass, .filter-card')
    ];

    const seen = new Set();
    animatedBlocks.forEach((el, index) => {
        if (!el || seen.has(el)) return;
        seen.add(el);

        el.classList.add('ego-pi-animate');
        el.style.animationDelay = Math.min(index * 55, 360) + 'ms';
    });

    const rows = document.querySelectorAll('table tbody tr');
    rows.forEach((row, index) => {
        row.classList.add('ego-pi-row-animate');
        row.style.animationDelay = Math.min(220 + index * 28, 760) + 'ms';
    });
});
/* EGO_PRODUCT_INPUT_COMPACT_UI_JS_END */
</script>
@endpush

