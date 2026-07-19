@extends('layouts.app')

@section('content')
@php
    $filters = $filters ?? [];
    $fmt = fn($n) => number_format((float) ($n ?? 0), 0, ',', '.');
    $stateLabels = $stateLabels ?? [];

    $statusBadge = function ($state, $orderId = null, $warrantyOrderId = null) {
        $state = $state ?: 'unknown';
        if (in_array($state, ['sold','delivered','shipped','issued','out'], true) || $orderId || $warrantyOrderId) {
            return ['Đã bán / đã xuất', 'ego-badge-warning'];
        }

        return match ($state) {
            'in_stock' => ['Đang tồn', 'ego-badge-success'],
            'reserved' => ['Đã giữ', 'ego-badge-purple'],
            'returned' => ['Trả kho', 'ego-badge-info'],
            'damaged' => ['Hư hỏng', 'ego-badge-danger'],
            'scrap' => ['Thanh lý', 'ego-badge-dark'],
            default => ['Chưa rõ', 'ego-badge-muted'],
        };
    };
@endphp

<style>
    .ego-serial-page{--ego-navy:#071735;--ego-cyan:#0fb6c9;--ego-mint:#e9fbff;--ego-soft:#f4fbff;--ego-line:#d9edf6;color:#102033}
    .ego-serial-hero{background:radial-gradient(circle at top left, rgba(15,182,201,.26), transparent 34%),linear-gradient(135deg,#fff 0%,#eefcff 58%,#f8fbff 100%);border:1px solid #d7edf6;border-radius:24px;box-shadow:0 18px 45px rgba(7,23,53,.08);padding:22px;overflow:hidden;position:relative}
    .ego-serial-hero:after{content:"";position:absolute;width:220px;height:220px;right:-70px;top:-80px;border-radius:999px;background:rgba(15,182,201,.14);filter:blur(3px)}
    .ego-serial-title{font-weight:900;letter-spacing:-.03em;color:var(--ego-navy);margin:0;position:relative;z-index:1}
    .ego-serial-sub{color:#607089;font-weight:600;position:relative;z-index:1}
    .ego-stat-card{border:1px solid #d8edf6;border-radius:20px;background:#fff;box-shadow:0 12px 28px rgba(7,23,53,.055);padding:18px;height:100%;transition:.22s ease}
    .ego-stat-card:hover{transform:translateY(-3px);box-shadow:0 18px 36px rgba(7,23,53,.09)}
    .ego-stat-label{font-size:12px;text-transform:uppercase;letter-spacing:.06em;color:#6b7890;font-weight:900}
    .ego-stat-value{font-size:28px;font-weight:950;color:#071735;line-height:1.05;margin-top:7px}
    .ego-stat-icon{width:42px;height:42px;border-radius:14px;display:grid;place-items:center;background:#e9fbff;color:#0397ac;font-size:18px}
    .ego-filter-card,.ego-table-card{border:1px solid #dbeef7;border-radius:22px;background:#fff;box-shadow:0 16px 38px rgba(7,23,53,.06);overflow:hidden}
    .ego-filter-card .form-label{font-size:12px;font-weight:900;color:#34445a;text-transform:uppercase;letter-spacing:.04em;margin-bottom:6px}
    .ego-control{border:1px solid #d4e6ef;border-radius:13px;font-weight:700;min-height:44px;color:#14253b;background:#fff}
    .ego-control:focus{border-color:#0fb6c9;box-shadow:0 0 0 .2rem rgba(15,182,201,.12)}
    .ego-btn-primary{border:0;border-radius:13px;background:linear-gradient(135deg,#0bb4c8,#068ea0);color:#fff;font-weight:900;box-shadow:0 12px 22px rgba(15,182,201,.22);min-height:44px;padding:0 18px}
    .ego-btn-primary:hover{color:#fff;filter:brightness(.98);transform:translateY(-1px)}
    .ego-btn-soft{border:1px solid #d4e6ef;border-radius:13px;background:#fff;color:#22304a;font-weight:900;min-height:44px;padding:0 16px;display:inline-flex;align-items:center;gap:7px;text-decoration:none}
    .ego-btn-soft:hover{background:#f3fbff;color:#071735}
    .ego-table{margin:0;vertical-align:middle}
    .ego-table thead th{background:#f5faff;color:#38506c;text-transform:uppercase;font-size:11px;letter-spacing:.06em;border-bottom:1px solid #dceaf2;padding:14px 12px;white-space:nowrap}
    .ego-table tbody td{padding:16px 12px;border-bottom:1px solid #eef4f8;font-weight:700;color:#17243a}
    .ego-table tbody tr:hover{background:#fbfeff}
    .ego-serial-pill{display:inline-flex;align-items:center;gap:7px;background:#edf8ff;border:1px solid #d4ecff;color:#071735;border-radius:999px;padding:8px 11px;font-weight:950;font-size:13px;white-space:nowrap}
    .ego-badge{display:inline-flex;align-items:center;justify-content:center;border-radius:999px;padding:7px 10px;font-weight:950;font-size:12px;white-space:nowrap}
    .ego-badge-success{background:#dcfce7;color:#166534}
    .ego-badge-warning{background:#fff0c2;color:#9a5b00}
    .ego-badge-purple{background:#ede9fe;color:#5b21b6}
    .ego-badge-info{background:#dff7ff;color:#03657a}
    .ego-badge-danger{background:#ffe4e6;color:#be123c}
    .ego-badge-dark{background:#e5e7eb;color:#111827}
    .ego-badge-muted{background:#f1f5f9;color:#64748b}
    .ego-mini{font-size:12px;color:#65758d;font-weight:800}
    .ego-link-action{display:inline-flex;align-items:center;justify-content:center;gap:6px;border-radius:11px;padding:8px 11px;font-weight:900;font-size:12px;text-decoration:none;border:1px solid #d8e8f0;background:#fff;color:#102033;margin:2px}
    .ego-link-action:hover{background:#eafbff;color:#078ca0}
    .ego-link-action.dark{background:#071735;color:#fff;border-color:#071735}
    .ego-link-action.dark:hover{color:#fff;filter:brightness(1.08)}
    .ego-link-action.green{background:#0aa892;color:#fff;border-color:#0aa892}
    .ego-empty{padding:50px 15px;text-align:center;color:#6b7890;font-weight:800}
</style>

<div class="container-fluid px-3 px-lg-4 py-3 ego-serial-page">
    <div class="ego-serial-hero mb-3">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
            <div>
                <h3 class="ego-serial-title">Quản lý Seri / IMEI sản phẩm</h3>
                <div class="ego-serial-sub mt-1">Gom seri đang tồn, seri đã bán / đã xuất, khách hàng, đơn hàng và bảo hành trong 1 trang.</div>
            </div>

            <div class="d-flex flex-wrap gap-2 position-relative" style="z-index:1">
                @if(\Illuminate\Support\Facades\Route::has('products.input'))
                    <a href="{{ route('products.input') }}" class="ego-btn-soft"><i class="bi bi-box-seam"></i> Sản phẩm đầu vào</a>
                @endif

                @if(\Illuminate\Support\Facades\Route::has('serial-warranty.index'))
                    <a href="{{ route('serial-warranty.index') }}" class="ego-btn-soft"><i class="bi bi-shield-check"></i> Tra cứu bảo hành</a>
                @endif

                <a href="{{ route('products.serials.export', request()->query()) }}" class="ego-btn-primary text-decoration-none d-inline-flex align-items-center gap-2">
                    <i class="bi bi-file-earmark-spreadsheet"></i> Xuất Excel
                </a>
            </div>
        </div>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-6 col-xl-2">
            <div class="ego-stat-card">
                <div class="d-flex justify-content-between gap-2">
                    <div>
                        <div class="ego-stat-label">Tổng seri</div>
                        <div class="ego-stat-value">{{ $fmt($stats['total'] ?? 0) }}</div>
                    </div>
                    <div class="ego-stat-icon"><i class="bi bi-upc-scan"></i></div>
                </div>
            </div>
        </div>

        <div class="col-6 col-xl-2">
            <div class="ego-stat-card">
                <div class="d-flex justify-content-between gap-2">
                    <div>
                        <div class="ego-stat-label">Đang tồn</div>
                        <div class="ego-stat-value">{{ $fmt($stats['in_stock'] ?? 0) }}</div>
                    </div>
                    <div class="ego-stat-icon"><i class="bi bi-box2-heart"></i></div>
                </div>
            </div>
        </div>

        <div class="col-6 col-xl-2">
            <div class="ego-stat-card">
                <div class="d-flex justify-content-between gap-2">
                    <div>
                        <div class="ego-stat-label">Tồn/giữ/trả</div>
                        <div class="ego-stat-value">{{ $fmt($stats['stock_all'] ?? 0) }}</div>
                    </div>
                    <div class="ego-stat-icon"><i class="bi bi-archive"></i></div>
                </div>
            </div>
        </div>

        <div class="col-6 col-xl-2">
            <div class="ego-stat-card">
                <div class="d-flex justify-content-between gap-2">
                    <div>
                        <div class="ego-stat-label">Đã bán/xuất</div>
                        <div class="ego-stat-value">{{ $fmt($stats['sold'] ?? 0) }}</div>
                    </div>
                    <div class="ego-stat-icon"><i class="bi bi-bag-check"></i></div>
                </div>
            </div>
        </div>

        <div class="col-6 col-xl-2">
            <div class="ego-stat-card">
                <div class="d-flex justify-content-between gap-2">
                    <div>
                        <div class="ego-stat-label">Còn bảo hành</div>
                        <div class="ego-stat-value">{{ $fmt($stats['warranty_active'] ?? 0) }}</div>
                    </div>
                    <div class="ego-stat-icon"><i class="bi bi-shield-check"></i></div>
                </div>
            </div>
        </div>

        <div class="col-6 col-xl-2">
            <div class="ego-stat-card">
                <div class="d-flex justify-content-between gap-2">
                    <div>
                        <div class="ego-stat-label">Chưa rõ</div>
                        <div class="ego-stat-value">{{ $fmt($stats['unknown'] ?? 0) }}</div>
                    </div>
                    <div class="ego-stat-icon"><i class="bi bi-question-circle"></i></div>
                </div>
            </div>
        </div>
    </div>

    <form method="GET" class="ego-filter-card mb-3">
        <div class="p-3 p-lg-4">
            <div class="row g-3 align-items-end">
                <div class="col-12 col-xl-3">
                    <label class="form-label">Tìm kiếm</label>
                    <input type="text" name="q" class="form-control ego-control" value="{{ $filters['q'] ?? '' }}" placeholder="Serial, sản phẩm, SKU, khách, SĐT, đơn hàng...">
                </div>

                <div class="col-12 col-md-6 col-xl-2">
                    <label class="form-label">Trạng thái</label>
                    <select name="status" class="form-select ego-control">
                        <option value="">-- Tất cả --</option>
                        <option value="available" {{ ($filters['status'] ?? '') === 'available' ? 'selected' : '' }}>Đang tồn bán được</option>
                        <option value="stock" {{ ($filters['status'] ?? '') === 'stock' ? 'selected' : '' }}>Tất cả seri trong kho</option>
                        <option value="sold" {{ ($filters['status'] ?? '') === 'sold' ? 'selected' : '' }}>Đã bán / theo đơn</option>
                        <option value="exported" {{ ($filters['status'] ?? '') === 'exported' ? 'selected' : '' }}>Đã xuất kho</option>
                        <option value="unknown" {{ ($filters['status'] ?? '') === 'unknown' ? 'selected' : '' }}>Chưa rõ</option>
                    </select>
                </div>

                <div class="col-12 col-md-6 col-xl-2">
                    <label class="form-label">Sản phẩm</label>
                    <select name="product_id" class="form-select ego-control">
                        <option value="">-- Tất cả --</option>
                        @foreach($products as $p)
                            <option value="{{ $p->id }}" {{ (int)($filters['product_id'] ?? 0) === (int)$p->id ? 'selected' : '' }}>
                                {{ $p->name }} @if($p->sku) - {{ $p->sku }} @endif
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="col-12 col-md-6 col-xl-2">
                    <label class="form-label">Kho</label>
                    <select name="warehouse_id" class="form-select ego-control">
                        <option value="">-- Tất cả --</option>
                        @foreach($warehouses as $w)
                            <option value="{{ $w->id }}" {{ (int)($filters['warehouse_id'] ?? 0) === (int)$w->id ? 'selected' : '' }}>
                                {{ $w->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="col-12 col-md-6 col-xl-1">
                    <label class="form-label">BH</label>
                    <select name="warranty" class="form-select ego-control">
                        <option value="">Tất cả</option>
                        <option value="active" {{ ($filters['warranty'] ?? '') === 'active' ? 'selected' : '' }}>Còn</option>
                        <option value="expired" {{ ($filters['warranty'] ?? '') === 'expired' ? 'selected' : '' }}>Hết</option>
                        <option value="none" {{ ($filters['warranty'] ?? '') === 'none' ? 'selected' : '' }}>Chưa có</option>
                    </select>
                </div>

                <div class="col-12 col-md-6 col-xl-2">
                    <label class="form-label">Công ty</label>
                    <select name="company_id" class="form-select ego-control">
                        <option value="">-- Tất cả / kể cả chưa gán --</option>
                        @foreach($companies as $c)
                            <option value="{{ $c->id }}" {{ request()->has('company_id') && (int)($filters['company_id'] ?? 0) === (int)$c->id ? 'selected' : '' }}>
                                {{ $c->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="col-12 d-flex flex-wrap gap-2">
                    <button class="ego-btn-primary" type="submit"><i class="bi bi-funnel"></i> Lọc seri</button>
                    <a href="{{ route('products.serials.index') }}" class="ego-btn-soft"><i class="bi bi-arrow-clockwise"></i> Xem tất cả seri</a>
                </div>
            </div>
        </div>
    </form>

    <div class="ego-table-card">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 p-3 border-bottom" style="border-color:#e7f1f6!important">
            <div>
                <h5 class="mb-0" style="font-weight:950;color:#071735">Danh sách seri</h5>
                <div class="ego-mini">Hiển thị cả seri còn trong kho và seri đã bán theo đơn / khách hàng.</div>
            </div>
            <div class="ego-mini">{{ $serials->total() }} kết quả</div>
        </div>

        <div class="table-responsive">
            <table class="table ego-table">
                <thead>
                    <tr>
                        <th>Serial</th>
                        <th>Sản phẩm</th>
                        <th>Trạng thái</th>
                        <th>Kho / Công ty</th>
                        <th>Khách hàng</th>
                        <th>Đơn hàng</th>
                        <th>Bảo hành</th>
                        <th>Ghi chú</th>
                        <th class="text-end">Thao tác</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($serials as $row)
                        @php
                            [$label, $badgeClass] = $statusBadge($row->state, $row->order_id, $row->warranty_order_id);
                            $warrantyEnd = $row->warranty_end_at ? \Carbon\Carbon::parse($row->warranty_end_at)->endOfDay() : null;
                            $daysLeft = $warrantyEnd ? now()->startOfDay()->diffInDays($warrantyEnd, false) : null;
                            $warrantyActive = $warrantyEnd && $daysLeft >= 0;
                        @endphp

                        <tr>
                            <td>
                                <span class="ego-serial-pill"><i class="bi bi-upc-scan"></i>{{ $row->serial_code ?: 'Chưa có mã' }}</span>
                                <div class="ego-mini mt-1">ID: #{{ $row->id }}</div>
                            </td>

                            <td style="min-width:260px">
                                <div class="fw-bold">{{ $row->product_name ?: 'Chưa rõ sản phẩm' }}</div>
                                <div class="ego-mini">{{ $row->product_sku ?: 'Chưa có SKU' }}</div>
                            </td>

                            <td>
                                <span class="ego-badge {{ $badgeClass }}">{{ $label }}</span>
                                @if($row->synced_at)
                                    <div class="ego-mini mt-1">Cập nhật: {{ \Carbon\Carbon::parse($row->synced_at)->format('d/m/Y H:i') }}</div>
                                @endif
                            </td>

                            <td style="min-width:190px">
                                <div class="fw-bold">{{ $row->warehouse_name ?: 'Không nằm trong kho' }}</div>
                                <div class="ego-mini">{{ $row->company_name ?: 'Chưa gán công ty' }}</div>
                            </td>

                            <td style="min-width:220px">
                                <div class="fw-bold">{{ $row->customer_name ?: 'Chưa gán khách hàng' }}</div>
                                @if($row->customer_phone)
                                    <div class="ego-mini">{{ $row->customer_phone }}</div>
                                @endif
                            </td>

                            <td>
                                @if($row->order_code)
                                    @if(\Illuminate\Support\Facades\Route::has('orders.show') && $row->order_id)
                                        <a class="ego-link-action dark" href="{{ route('orders.show', $row->order_id) }}">
                                            <i class="bi bi-receipt"></i>{{ $row->order_code }}
                                        </a>
                                    @else
                                        <span class="ego-serial-pill">{{ $row->order_code }}</span>
                                    @endif
                                @elseif($row->warranty_order_id)
                                    <span class="ego-serial-pill">Đơn #{{ $row->warranty_order_id }}</span>
                                @else
                                    <span class="ego-mini">-</span>
                                @endif

                                @if($row->sold_at)
                                    <div class="ego-mini mt-1">Bán: {{ \Carbon\Carbon::parse($row->sold_at)->format('d/m/Y') }}</div>
                                @elseif($row->order_date)
                                    <div class="ego-mini mt-1">Ngày đơn: {{ \Carbon\Carbon::parse($row->order_date)->format('d/m/Y') }}</div>
                                @endif
                            </td>

                            <td style="min-width:160px">
                                @if($warrantyEnd)
                                    <span class="ego-badge {{ $warrantyActive ? 'ego-badge-success' : 'ego-badge-danger' }}">
                                        {{ $warrantyActive ? 'Còn BH' : 'Hết BH' }}
                                    </span>
                                    <div class="ego-mini mt-1">đến {{ $warrantyEnd->format('d/m/Y') }}</div>
                                    @if($warrantyActive)
                                        <div class="ego-mini">còn {{ number_format($daysLeft, 0, ',', '.') }} ngày</div>
                                    @endif
                                @else
                                    <span class="ego-badge ego-badge-muted">Chưa có BH</span>
                                @endif
                            </td>

                            <td style="min-width:180px">
                                <div class="ego-mini">{{ $row->note ?: ($row->warranty_note ?: '-') }}</div>
                            </td>

                            <td class="text-end" style="min-width:210px">
                                @if(\Illuminate\Support\Facades\Route::has('serial-warranty.index') && $row->serial_code)
                                    <a class="ego-link-action green" href="{{ route('serial-warranty.index', ['q' => $row->serial_code]) }}">
                                        <i class="bi bi-shield-check"></i> BH
                                    </a>
                                @endif

                                @if(\Illuminate\Support\Facades\Route::has('products.edit') && $row->product_id)
                                    <a class="ego-link-action" href="{{ route('products.edit', $row->product_id) }}">
                                        <i class="bi bi-pencil-square"></i> SP
                                    </a>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9">
                                <div class="ego-empty">
                                    <i class="bi bi-inbox fs-1 d-block mb-2"></i>
                                    Chưa có seri nào theo bộ lọc hiện tại.
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="p-3 border-top" style="border-color:#e7f1f6!important">
            {{ $serials->links() }}
        </div>
    </div>
</div>
@endsection
