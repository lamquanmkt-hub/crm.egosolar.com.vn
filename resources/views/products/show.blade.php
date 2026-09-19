@extends('layouts.app')

@section('title', $product->name ?? 'Chi tiết sản phẩm')

@php
    $canViewCost = false;
    $canUpdate = false;

    try {
        $canViewCost = auth()->user()?->can('viewCost', $product) ?? false;
    } catch (\Throwable $e) {
        $canViewCost = false;
    }

    try {
        $canUpdate = auth()->user()?->can('update', $product) ?? false;
    } catch (\Throwable $e) {
        $canUpdate = false;
    }

    $stocks = collect($stocks ?? []);
    $lots = collect($lots ?? []);
    $movements = collect($movements ?? []);
    $prices = collect(
        $product->relationLoaded('prices')
            ? ($product->prices ?? [])
            : []
    );

    $totalStock = (float) $stocks->sum(function ($row) {
        return (float) ($row->qty ?? 0);
    });

    $costBefore = (float) ($product->price_agent ?? 0);
    $costVat = (float) ($product->cost_vat_percent ?? $product->vat_percent ?? 0);
    $costAfter = (float) ($product->price_agent_vat ?? ($costBefore * (1 + ($costVat / 100))));

    $retailBefore = (float) ($product->price_retail ?? $product->price ?? 0);
    $retailVat = (float) ($product->vat_percent ?? 0);
    $retailAfter = (float) ($product->price_retail_vat ?? ($retailBefore * (1 + ($retailVat / 100))));

    $formatMoney = static function ($value) {
        return number_format((float) $value, 0, ',', '.').' đ';
    };

    $formatQty = static function ($value) {
        $number = (float) $value;

        if (floor($number) === $number) {
            return number_format($number, 0, ',', '.');
        }

        return rtrim(rtrim(number_format($number, 3, ',', '.'), '0'), ',');
    };

    $formatDate = static function ($value, $withTime = false) {
        if (blank($value)) {
            return '—';
        }

        try {
            return \Illuminate\Support\Carbon::parse($value)->format(
                $withTime ? 'd/m/Y H:i' : 'd/m/Y'
            );
        } catch (\Throwable $e) {
            return (string) $value;
        }
    };

    $categoryName = data_get($product, 'category.name') ?: 'Chưa phân loại';
    $brandName = data_get($product, 'brand.name') ?: 'Chưa có thương hiệu';
    $sku = trim((string) ($product->sku ?? '')) ?: 'Chưa có SKU';
    $description = trim((string) ($product->description ?? $product->note ?? ''));

    if ($description === '') {
        $description = 'Sản phẩm chưa có mô tả hoặc ghi chú chi tiết.';
    }

    $imageUrl = null;

    try {
        $mainImage = $product->relationLoaded('mainImage') ? $product->mainImage : null;
        $media = data_get($mainImage, 'media');
        $metadata = data_get($media, 'metadata.metadata');

        if (is_string($metadata)) {
            $decoded = json_decode($metadata, true);
            $metadata = is_array($decoded) ? $decoded : [];
        }

        if (! is_array($metadata)) {
            $metadata = [];
        }

        $imageUrl = $metadata['url'] ?? null;
        $filePath = data_get($media, 'file_path');

        if (! $imageUrl && $filePath) {
            $imageUrl = \Illuminate\Support\Facades\Storage::disk('public')->url($filePath);
        }
    } catch (\Throwable $e) {
        $imageUrl = null;
    }

    if (! $imageUrl) {
        $imageUrl = $product->image_url ?? null;
    }
@endphp

@section('content')
<style>
    :root {
        --pd-navy: #0b213b;
        --pd-navy-2: #142f4f;
        --pd-teal: #0a9f99;
        --pd-teal-2: #13b8b0;
        --pd-bg: #f2f6fa;
        --pd-card: #ffffff;
        --pd-line: #dce7ef;
        --pd-line-soft: #eaf0f5;
        --pd-muted: #6b7f92;
        --pd-green: #11894f;
        --pd-red: #d6455d;
        --pd-shadow: 0 14px 38px rgba(15, 39, 64, .075);
    }

    .pd-page {
        min-height: calc(100vh - 72px);
        padding: 18px 20px 36px;
        background:
            radial-gradient(circle at 90% 0%, rgba(17, 184, 176, .08), transparent 28%),
            var(--pd-bg);
    }

    .pd-topbar {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 18px;
        margin-bottom: 14px;
    }

    .pd-eyebrow {
        margin-bottom: 6px;
        color: var(--pd-teal);
        font-size: 10px;
        font-weight: 900;
        letter-spacing: .12em;
        text-transform: uppercase;
    }

    .pd-title {
        margin: 0;
        color: var(--pd-navy);
        font-size: 27px;
        font-weight: 950;
        letter-spacing: -.04em;
        line-height: 1.15;
    }

    .pd-subtitle {
        margin-top: 6px;
        color: var(--pd-muted);
        font-size: 12px;
        font-weight: 650;
    }

    .pd-actions {
        display: flex;
        align-items: center;
        gap: 8px;
        flex-wrap: wrap;
    }

    .pd-btn {
        min-height: 40px;
        padding: 0 14px;
        border: 1px solid var(--pd-line);
        border-radius: 12px;
        background: #fff;
        color: var(--pd-navy-2);
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        font-size: 12px;
        font-weight: 900;
        text-decoration: none;
        transition: .18s ease;
    }

    .pd-btn:hover {
        color: var(--pd-navy-2);
        transform: translateY(-1px);
        box-shadow: 0 9px 20px rgba(15, 39, 64, .09);
    }

    .pd-btn-primary {
        border-color: transparent;
        background: linear-gradient(135deg, var(--pd-teal-2), var(--pd-teal));
        color: #fff;
        box-shadow: 0 10px 22px rgba(10, 159, 153, .2);
    }

    .pd-btn-primary:hover {
        color: #fff;
    }

    .pd-hero {
        display: grid;
        grid-template-columns: 190px minmax(0, 1fr);
        gap: 18px;
        padding: 18px;
        margin-bottom: 12px;
        border: 1px solid var(--pd-line);
        border-radius: 18px;
        background: linear-gradient(135deg, #ffffff 0%, #fbfefe 62%, #f0fbfa 100%);
        box-shadow: var(--pd-shadow);
    }

    .pd-image {
        height: 170px;
        border: 1px solid var(--pd-line);
        border-radius: 16px;
        background: linear-gradient(145deg, #f7fafc, #eef5f8);
        color: #8ba2b5;
        display: flex;
        align-items: center;
        justify-content: center;
        overflow: hidden;
        font-size: 48px;
    }

    .pd-image img {
        width: 100%;
        height: 100%;
        object-fit: contain;
        background: #fff;
    }

    .pd-hero-main {
        min-width: 0;
        display: flex;
        flex-direction: column;
        justify-content: center;
    }

    .pd-sku {
        width: fit-content;
        max-width: 100%;
        padding: 6px 10px;
        border: 1px solid #d9e5ee;
        border-radius: 9px;
        background: #f5f8fb;
        color: var(--pd-navy-2);
        font-size: 11px;
        font-weight: 950;
        word-break: break-word;
    }

    .pd-meta {
        display: flex;
        flex-wrap: wrap;
        gap: 7px;
        margin-top: 11px;
    }

    .pd-pill {
        padding: 6px 10px;
        border: 1px solid #c8ebe8;
        border-radius: 999px;
        background: #edfafa;
        color: #087b76;
        display: inline-flex;
        align-items: center;
        gap: 6px;
        font-size: 10px;
        font-weight: 900;
    }

    .pd-description {
        max-width: 1000px;
        margin-top: 13px;
        color: #50677c;
        font-size: 12px;
        font-weight: 600;
        line-height: 1.65;
    }

    .pd-kpis {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: 10px;
        margin-bottom: 12px;
    }

    .pd-kpi {
        min-height: 92px;
        padding: 14px;
        border: 1px solid var(--pd-line);
        border-radius: 16px;
        background: var(--pd-card);
        display: flex;
        align-items: center;
        gap: 12px;
        box-shadow: 0 8px 24px rgba(15, 39, 64, .045);
    }

    .pd-kpi-icon {
        width: 43px;
        height: 43px;
        border-radius: 13px;
        background: #eafafa;
        color: var(--pd-teal);
        display: flex;
        align-items: center;
        justify-content: center;
        flex: 0 0 auto;
        font-size: 18px;
    }

    .pd-kpi:nth-child(2) .pd-kpi-icon { background: #eef3ff; color: #566bd3; }
    .pd-kpi:nth-child(3) .pd-kpi-icon { background: #ecfaf2; color: var(--pd-green); }
    .pd-kpi:nth-child(4) .pd-kpi-icon { background: #fff2e8; color: #d17b1f; }

    .pd-kpi-label {
        color: #718499;
        font-size: 9px;
        font-weight: 900;
        letter-spacing: .055em;
        text-transform: uppercase;
    }

    .pd-kpi-value {
        margin-top: 4px;
        color: var(--pd-navy);
        font-size: 20px;
        font-weight: 950;
        line-height: 1.1;
    }

    .pd-kpi-hint {
        margin-top: 4px;
        color: var(--pd-muted);
        font-size: 10px;
        font-weight: 650;
    }

    .pd-grid {
        display: grid;
        grid-template-columns: minmax(0, 1.15fr) minmax(350px, .85fr);
        gap: 12px;
    }

    .pd-card {
        margin-bottom: 12px;
        border: 1px solid var(--pd-line);
        border-radius: 17px;
        background: var(--pd-card);
        overflow: hidden;
        box-shadow: var(--pd-shadow);
    }

    .pd-card-head {
        min-height: 54px;
        padding: 12px 15px;
        border-bottom: 1px solid var(--pd-line);
        background: linear-gradient(135deg, #ffffff, #f3fbfb);
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 10px;
    }

    .pd-card-title {
        color: var(--pd-navy);
        font-size: 14px;
        font-weight: 950;
    }

    .pd-card-sub {
        margin-top: 2px;
        color: var(--pd-muted);
        font-size: 10px;
        font-weight: 650;
    }

    .pd-card-body {
        padding: 14px 15px;
    }

    .pd-table-wrap {
        overflow: auto;
    }

    .pd-table {
        width: 100%;
        min-width: 680px;
        border-collapse: collapse;
    }

    .pd-table th {
        padding: 10px 11px;
        border-bottom: 1px solid var(--pd-line);
        background: #f5f8fb;
        color: #61758a;
        font-size: 9px;
        font-weight: 900;
        letter-spacing: .035em;
        text-align: left;
        text-transform: uppercase;
        white-space: nowrap;
    }

    .pd-table td {
        padding: 11px;
        border-bottom: 1px solid var(--pd-line-soft);
        color: #233f5b;
        font-size: 11px;
        vertical-align: middle;
    }

    .pd-table tbody tr:last-child td {
        border-bottom: 0;
    }

    .pd-table tbody tr:hover td {
        background: #f5fbfb;
    }

    .pd-stock-badge,
    .pd-change-badge {
        min-width: 46px;
        padding: 5px 8px;
        border-radius: 999px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 10px;
        font-weight: 950;
    }

    .pd-stock-badge {
        background: #e8f8ee;
        color: var(--pd-green);
    }

    .pd-change-badge.positive {
        background: #e8f8ee;
        color: var(--pd-green);
    }

    .pd-change-badge.negative {
        background: #fff0f2;
        color: var(--pd-red);
    }

    .pd-price-row {
        padding: 11px 0;
        border-bottom: 1px solid var(--pd-line-soft);
        display: grid;
        grid-template-columns: minmax(130px, 1fr) auto;
        gap: 12px;
        align-items: center;
    }

    .pd-price-row:last-child {
        border-bottom: 0;
    }

    .pd-price-name {
        color: #3a536d;
        font-size: 11px;
        font-weight: 900;
    }

    .pd-price-value {
        color: #087e79;
        font-size: 12px;
        font-weight: 950;
        text-align: right;
    }

    .pd-price-note {
        margin-top: 3px;
        color: var(--pd-muted);
        font-size: 9px;
        font-weight: 650;
    }

    .pd-empty {
        padding: 30px 18px;
        color: #718499;
        font-size: 11px;
        font-weight: 700;
        text-align: center;
    }

    @media (max-width: 1100px) {
        .pd-kpis { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        .pd-grid { grid-template-columns: 1fr; }
    }

    @media (max-width: 720px) {
        .pd-page { padding: 13px; }
        .pd-topbar { display: block; }
        .pd-actions { margin-top: 11px; }
        .pd-hero { grid-template-columns: 1fr; }
        .pd-image { height: 210px; }
        .pd-kpis { grid-template-columns: 1fr; }
        .pd-title { font-size: 22px; }
    }
</style>

<div class="pd-page">
    <div class="pd-topbar">
        <div>
            <div class="pd-eyebrow">Kho &amp; Sản phẩm</div>
            <h1 class="pd-title">{{ $product->name }}</h1>
            <div class="pd-subtitle">Hồ sơ sản phẩm, tồn kho, giá bán, lô nhập và lịch sử biến động.</div>
        </div>

        <div class="pd-actions">
            <a href="{{ route('products.input') }}" class="pd-btn">
                <i class="bi bi-arrow-left"></i>
                Danh sách sản phẩm
            </a>

            @if ($canUpdate)
                <a href="{{ route('products.edit', $product->id) }}" class="pd-btn pd-btn-primary">
                    <i class="bi bi-pencil-square"></i>
                    Chỉnh sửa sản phẩm
                </a>
            @endif
        </div>
    </div>

    <section class="pd-hero">
        <div class="pd-image">
            @if ($imageUrl)
                <img src="{{ $imageUrl }}" alt="{{ $product->name }}">
            @else
                <i class="bi bi-box-seam"></i>
            @endif
        </div>

        <div class="pd-hero-main">
            <div class="pd-sku">SKU: {{ $sku }}</div>

            <div class="pd-meta">
                <span class="pd-pill">
                    <i class="bi bi-folder2-open"></i>
                    {{ $categoryName }}
                </span>
                <span class="pd-pill">
                    <i class="bi bi-award"></i>
                    {{ $brandName }}
                </span>
                <span class="pd-pill">
                    <i class="bi bi-upc-scan"></i>
                    {{ ! empty($product->is_serialized) ? 'Quản lý serial' : '' }}
                </span>
                <span class="pd-pill">
                    <i class="bi bi-shield-check"></i>
                    Công ty Quốc Tế EGO
                </span>
            </div>

            <div class="pd-description">{{ $description }}</div>
        </div>
    </section>

    <section class="pd-kpis">
        <div class="pd-kpi">
            <div class="pd-kpi-icon"><i class="bi bi-boxes"></i></div>
            <div>
                <div class="pd-kpi-label">Tổng tồn kho</div>
                <div class="pd-kpi-value">{{ $formatQty($totalStock) }}</div>
                <div class="pd-kpi-hint">Tổng trên các kho Quốc Tế EGO</div>
            </div>
        </div>

        <div class="pd-kpi">
            <div class="pd-kpi-icon"><i class="bi bi-cash-stack"></i></div>
            <div>
                <div class="pd-kpi-label">Giá vốn sau VAT</div>
                <div class="pd-kpi-value">
                    @if ($canViewCost)
                        {{ $formatMoney($costAfter) }}
                    @else
                        —
                    @endif
                </div>
                <div class="pd-kpi-hint">VAT {{ rtrim(rtrim(number_format($costVat, 2), '0'), '.') }}%</div>
            </div>
        </div>

        <div class="pd-kpi">
            <div class="pd-kpi-icon"><i class="bi bi-tag"></i></div>
            <div>
                <div class="pd-kpi-label">Giá bán sau VAT</div>
                <div class="pd-kpi-value">{{ $formatMoney($retailAfter) }}</div>
                <div class="pd-kpi-hint">Giá bán mặc định</div>
            </div>
        </div>

        <div class="pd-kpi">
            <div class="pd-kpi-icon"><i class="bi bi-graph-up-arrow"></i></div>
            <div>
                <div class="pd-kpi-label">Giá trị tồn kho</div>
                <div class="pd-kpi-value">
                    @if ($canViewCost)
                        {{ $formatMoney($costAfter * $totalStock) }}
                    @else
                        —
                    @endif
                </div>
                <div class="pd-kpi-hint">Theo giá vốn sau VAT</div>
            </div>
        </div>
    </section>

    <div class="pd-grid">
        <section class="pd-card">
            <div class="pd-card-head">
                <div>
                    <div class="pd-card-title">Tồn kho theo kho</div>
                    <div class="pd-card-sub">Số lượng thực tế đang ghi nhận tại từng kho.</div>
                </div>
            </div>

            <div class="pd-table-wrap">
                <table class="pd-table">
                    <thead>
                        <tr>
                            <th>Kho</th>
                            <th>Vị trí</th>
                            <th style="text-align: right">Số lượng</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($stocks as $stock)
                            @php
                                $warehouseName = data_get($stock, 'warehouse.name') ?: 'Kho #'.($stock->warehouse_id ?? '—');
                                $warehouseLocation = data_get($stock, 'warehouse.location') ?: '—';
                            @endphp
                            <tr>
                                <td><strong>{{ $warehouseName }}</strong></td>
                                <td>{{ $warehouseLocation }}</td>
                                <td style="text-align: right">
                                    <span class="pd-stock-badge">{{ $formatQty($stock->qty ?? 0) }}</span>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="3" class="pd-empty">Sản phẩm chưa có tồn kho.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        <section class="pd-card">
            <div class="pd-card-head">
                <div>
                    <div class="pd-card-title">Giá bán &amp; bảng giá đại lý</div>
                    <div class="pd-card-sub">Giá mặc định và giá theo từng cấp khách hàng.</div>
                </div>
            </div>

            <div class="pd-card-body">
                <div class="pd-price-row">
                    <div>
                        <div class="pd-price-name">Giá bán mặc định</div>
                        <div class="pd-price-note">Trước VAT {{ $formatMoney($retailBefore) }} · VAT {{ rtrim(rtrim(number_format($retailVat, 2), '0'), '.') }}%</div>
                    </div>
                    <div class="pd-price-value">{{ $formatMoney($retailAfter) }}</div>
                </div>

                @forelse ($prices as $price)
                    @php
                        $tierName = data_get($price, 'priceTier.name') ?: 'Bảng giá #'.($price->price_tier_id ?? '—');
                        $tierBefore = (float) ($price->price ?? 0);
                        $tierAfter = isset($price->price_after_vat)
                            ? (float) $price->price_after_vat
                            : $tierBefore;
                    @endphp
                    <div class="pd-price-row">
                        <div>
                            <div class="pd-price-name">{{ $tierName }}</div>
                            <div class="pd-price-note">Giá trước VAT {{ $formatMoney($tierBefore) }}</div>
                        </div>
                        <div class="pd-price-value">{{ $formatMoney($tierAfter) }}</div>
                    </div>
                @empty
                    <div class="pd-empty">Chưa thiết lập bảng giá theo cấp.</div>
                @endforelse
            </div>
        </section>
    </div>

    <section class="pd-card">
        <div class="pd-card-head">
            <div>
                <div class="pd-card-title">Các lô nhập gần nhất</div>
                <div class="pd-card-sub">Theo dõi số lượng nhập, số lượng còn lại và giá vốn thực tế.</div>
            </div>
        </div>

        <div class="pd-table-wrap">
            <table class="pd-table">
                <thead>
                    <tr>
                        <th>Ngày nhập</th>
                        <th>Kho</th>
                        <th>Mã / tên lô</th>
                        <th style="text-align: right">SL nhập</th>
                        <th style="text-align: right">Còn lại</th>
                        <th style="text-align: right">Giá thực tế</th>
                        <th>Ghi chú</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($lots as $lot)
                        @php
                            $lotName = $lot->lot_name ?? $lot->lot_code ?? null;
                            if (! $lotName) {
                                $lotName = 'Lô #'.($lot->id ?? '—');
                            }
                        @endphp
                        <tr>
                            <td>{{ $formatDate($lot->received_at ?? null) }}</td>
                            <td>{{ $lot->warehouse_name ?? '—' }}</td>
                            <td><strong>{{ $lotName }}</strong></td>
                            <td style="text-align: right">{{ $formatQty($lot->qty_in ?? 0) }}</td>
                            <td style="text-align: right">
                                <span class="pd-stock-badge">{{ $formatQty($lot->qty_remaining ?? 0) }}</span>
                            </td>
                            <td style="text-align: right">
                                @if ($canViewCost)
                                    <strong style="color: var(--pd-green)">{{ $formatMoney($lot->actual_cost_after_vat ?? 0) }}</strong>
                                @else
                                    —
                                @endif
                            </td>
                            <td>{{ $lot->note ?? '—' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="pd-empty">Chưa có lô nhập hàng.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <section class="pd-card">
        <div class="pd-card-head">
            <div>
                <div class="pd-card-title">Biến động kho gần nhất</div>
                <div class="pd-card-sub">Lịch sử tăng giảm số lượng của sản phẩm.</div>
            </div>
        </div>

        <div class="pd-table-wrap">
            <table class="pd-table">
                <thead>
                    <tr>
                        <th>Thời gian</th>
                        <th>Kho</th>
                        <th>Lý do</th>
                        <th style="text-align: right">Thay đổi</th>
                        <th style="text-align: right">Trước</th>
                        <th style="text-align: right">Sau</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($movements as $movement)
                        @php
                            $changeQty = (float) ($movement->change_qty ?? 0);
                            $changeClass = $changeQty >= 0 ? 'positive' : 'negative';
                            $changePrefix = $changeQty > 0 ? '+' : '';
                        @endphp
                        <tr>
                            <td>{{ $formatDate($movement->created_at ?? null, true) }}</td>
                            <td>{{ $movement->warehouse_name ?? '—' }}</td>
                            <td>{{ $movement->reason ?? 'Điều chỉnh kho' }}</td>
                            <td style="text-align: right">
                                <span class="pd-change-badge {{ $changeClass }}">
                                    {{ $changePrefix }}{{ $formatQty($changeQty) }}
                                </span>
                            </td>
                            <td style="text-align: right">{{ $formatQty($movement->qty_before ?? 0) }}</td>
                            <td style="text-align: right"><strong>{{ $formatQty($movement->qty_after ?? 0) }}</strong></td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="pd-empty">Chưa có lịch sử biến động kho.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
</div>
@endsection
