@extends('layouts.app')

@section('title', 'Sửa sản phẩm')

@php
    use Illuminate\Support\Facades\DB;
    use Illuminate\Support\Facades\Schema;

    $categories = $categories ?? collect();
    $brands = $brands ?? collect();
    $companies = $companies ?? collect();
    $companyWarehouses = $companyWarehouses ?? [];
    $priceTiers = $priceTiers ?? collect();

    $productTable = $product->getTable();
    $productId = (int) ($product->id ?? 0);

    $companyOptions = $companies->map(fn($c) => ['id' => $c->id, 'name' => $c->name])->values();

    $warehouseOptions = collect($companyWarehouses)->flatMap(function ($list, $companyId) {
        return collect($list)->map(fn($w) => [
            'id' => $w->id,
            'name' => $w->name,
            'company_id' => (string) $companyId,
        ]);
    })->values();

    $costExpr = "COALESCE(NULLIF(l.actual_cost_after_vat,0), NULLIF(l.cost_after_vat,0), COALESCE(l.cost_before_vat,p.price_agent,0) * (1 + COALESCE(l.cost_vat_percent,p.cost_vat_percent,p.vat_percent,0) / 100), COALESCE(p.price_agent_vat,0), 0)";

    $groupRows = collect();

    if (Schema::hasTable('crm_product_stock_lots')) {
        $q = DB::table($productTable . ' as p')
            ->leftJoin('crm_product_stock_lots as l', 'l.product_id', '=', 'p.id')
            ->leftJoin('crm_warehouses as w', 'w.id', '=', 'l.warehouse_id')
            ->leftJoin('companies as c', 'c.id', '=', 'l.company_id')
            ->where('p.name', $product->name);

        if (Schema::hasColumn($productTable, 'is_active')) {
            $q->where(function ($qq) {
                $qq->where('p.is_active', 1)->orWhereNull('p.is_active');
            });
        }

        $groupRows = $q->select([
                'p.id as product_id',
                'p.name as product_name',
                'p.sku',
                'p.price_agent',
                'p.price_agent_vat',
                'p.vat_percent',
                DB::raw('COALESCE(p.cost_vat_percent, p.vat_percent, 0) as product_cost_vat'),
                'l.id as stock_lot_id',
                'l.company_id',
                'l.warehouse_id',
                'l.received_at',
                'l.qty_in',
                'l.qty_remaining',
                'l.cost_before_vat',
                'l.cost_vat_percent',
                'l.cost_after_vat',
                'l.extra_cost',
                'l.actual_cost_after_vat',
                'l.note as lot_note',
                'l.lot_name',
                'w.name as warehouse_name',
                'c.name as company_name',
                DB::raw($costExpr . ' as actual_cost_calc'),
            ])
            ->orderBy('p.sku')
            ->orderByRaw('COALESCE(l.received_at, l.created_at) ASC')
            ->orderBy('l.id')
            ->get();
    }

    if ($groupRows->isEmpty()) {
        $groupRows = collect([(object)[
            'product_id' => $product->id,
            'product_name' => $product->name,
            'sku' => $product->sku,
            'price_agent' => $product->price_agent ?? 0,
            'product_cost_vat' => $product->cost_vat_percent ?? $product->vat_percent ?? 0,
            'stock_lot_id' => null,
            'company_id' => null,
            'warehouse_id' => null,
            'received_at' => date('Y-m-d'),
            'qty_in' => 1,
            'qty_remaining' => 1,
            'cost_before_vat' => $product->price_agent ?? 0,
            'cost_vat_percent' => $product->cost_vat_percent ?? $product->vat_percent ?? 0,
            'extra_cost' => 0,
            'lot_note' => '',
            'actual_cost_calc' => $product->price_agent_vat ?? 0,
            'company_name' => '',
            'warehouse_name' => '',
        ]]);
    }

    $currentStockQty = (float) $groupRows->sum(fn($r) => (float) ($r->qty_remaining ?? 0));
    $currentStockValue = (float) $groupRows->sum(function ($r) {
        return (float) ($r->qty_remaining ?? 0) * (float) ($r->actual_cost_calc ?? 0);
    });

    $initialLines = $groupRows->map(function ($r, $idx) {
        // EGO FIX: Form sửa phải hiển thị tồn hiện tại, không ép tồn 0 thành 1.
        $qtyRemain = (float) ($r->qty_remaining ?? 0);
        $qtyIn = (float) ($r->qty_in ?? $qtyRemain);
        $qtySold = max(0, $qtyIn - $qtyRemain);

        $cost = (float) ($r->cost_before_vat ?? $r->price_agent ?? 0);
        $vat = (float) ($r->cost_vat_percent ?? $r->product_cost_vat ?? 0);

        return [
            'product_id' => (int) ($r->product_id ?? 0),
            'stock_lot_id' => (int) ($r->stock_lot_id ?? 0),
            'display_name' => (string) (($r->lot_name ?? '') ?: ('Dòng tồn / SKU ' . ($idx + 1))),
            'sku' => (string) ($r->sku ?? ''),
            'cost' => $cost,
            'vat' => $vat,
            'qty' => $qtyRemain,
            'qty_in_original' => $qtyIn,
            'qty_sold' => $qtySold,
            'date' => $r->received_at ? date('Y-m-d', strtotime($r->received_at)) : date('Y-m-d'),
            'extra' => (float) ($r->extra_cost ?? 0),
            'note' => (string) ($r->lot_note ?? ''),
            'company_id' => (string) ($r->company_id ?? ''),
            'warehouse_id' => (string) ($r->warehouse_id ?? ''),
        ];
    })->values();

    // EGO FIX: Lịch sử phải gồm cả nhập kho và xuất kho, không chỉ dòng tồn hiện tại.
    $stockHistoryRows = collect();
    $historyProductIds = $groupRows->pluck('product_id')->map(fn($id) => (int) $id)->filter()->unique()->values();

    if ($historyProductIds->isNotEmpty()) {
        $stockHistoryRows = $groupRows->filter(fn($r) => !empty($r->stock_lot_id))->map(function ($r) {
            $qtyIn = (float) ($r->qty_in ?? 0);
            $cost = (float) ($r->cost_before_vat ?? $r->price_agent ?? 0);
            $vat = (float) ($r->cost_vat_percent ?? $r->product_cost_vat ?? 0);
            $after = (float) ($r->actual_cost_calc ?? ($cost * (1 + $vat / 100)));

            return [
                'type' => 'Nhập kho',
                'sign' => '+',
                'product_id' => (int) ($r->product_id ?? 0),
                'product_name' => (string) ($r->product_name ?? ''),
                'sku' => (string) ($r->sku ?? ''),
                'company_name' => (string) ($r->company_name ?? ''),
                'warehouse_name' => (string) ($r->warehouse_name ?? ''),
                'date' => $r->received_at ? date('Y-m-d', strtotime($r->received_at)) : '',
                'sort_at' => $r->received_at ? strtotime($r->received_at) : 0,
                'cost' => $cost,
                'vat' => $vat,
                'after' => $after,
                'qty' => $qtyIn,
                'extra' => (float) ($r->extra_cost ?? 0),
                'actual' => $after,
                'total' => $after * $qtyIn,
                'note' => (string) (($r->lot_note ?? '') ?: 'Nhập kho'),
            ];
        });

        if (Schema::hasTable('crm_order_item_stock_allocations')) {
            $allocRows = DB::table('crm_order_item_stock_allocations as a')
                ->leftJoin('crm_orders as o', 'o.id', '=', 'a.order_id')
                ->leftJoin('crm_order_items as oi', 'oi.id', '=', 'a.order_item_id')
                ->leftJoin($productTable . ' as p', 'p.id', '=', 'a.product_id')
                ->leftJoin('crm_warehouses as w', 'w.id', '=', 'a.warehouse_id')
                ->leftJoin('companies as c', 'c.id', '=', 'a.company_id')
                ->whereIn('a.product_id', $historyProductIds->all())
                ->select([
                    'a.product_id',
                    'a.qty',
                    'a.unit_cost_before_vat',
                    'a.unit_cost_after_vat',
                    'a.total_cost_after_vat',
                    'a.created_at',
                    'p.name as product_name',
                    'p.sku',
                    'w.name as warehouse_name',
                    'c.name as company_name',
                    'o.order_code',
                    'oi.vat_percent as order_vat_percent',
                ])
                ->get()
                ->map(function ($a) {
                    $qty = (float) ($a->qty ?? 0);
                    $before = (float) ($a->unit_cost_before_vat ?? 0);
                    $after = (float) ($a->unit_cost_after_vat ?? 0);
                    $vat = (float) ($a->order_vat_percent ?? 0);

                    if ($vat <= 0 && $before > 0 && $after > $before) {
                        $vat = round((($after / $before) - 1) * 100, 2);
                    }

                    return [
                        'type' => 'Xuất kho đơn ' . (string) ($a->order_code ?? ''),
                        'sign' => '-',
                        'product_id' => (int) ($a->product_id ?? 0),
                        'product_name' => (string) ($a->product_name ?? ''),
                        'sku' => (string) ($a->sku ?? ''),
                        'company_name' => (string) ($a->company_name ?? ''),
                        'warehouse_name' => (string) ($a->warehouse_name ?? ''),
                        'date' => $a->created_at ? date('Y-m-d H:i', strtotime($a->created_at)) : '',
                        'sort_at' => $a->created_at ? strtotime($a->created_at) : 0,
                        'cost' => $before,
                        'vat' => $vat,
                        'after' => $after,
                        'qty' => -1 * abs($qty),
                        'extra' => 0,
                        'actual' => $after,
                        'total' => -1 * abs((float) ($a->total_cost_after_vat ?? ($after * $qty))),
                        'note' => 'Xuất kho bán hàng',
                    ];
                });

            $stockHistoryRows = $stockHistoryRows->concat($allocRows);
        }

        $stockHistoryRows = $stockHistoryRows->sortBy('sort_at')->values();
    }


    $serialRowsByProduct = collect();

    if (
        Schema::hasTable('crm_serial_units') &&
        Schema::hasTable('crm_serial_unit_identifiers') &&
        Schema::hasTable('crm_serial_identifiers') &&
        Schema::hasTable('crm_serial_unit_states')
    ) {
        $serialProductIds = $initialLines->pluck('product_id')->map(fn($id) => (int) $id)->filter()->unique()->values();

        if ($serialProductIds->isNotEmpty()) {
            $serialRows = DB::table('crm_serial_units as su')
                ->join('crm_serial_unit_identifiers as sui', function ($join) {
                    $join->on('sui.serial_unit_id', '=', 'su.id')->where('sui.is_primary', 1);
                })
                ->join('crm_serial_identifiers as si', 'si.id', '=', 'sui.serial_identifier_id')
                ->leftJoin('crm_serial_unit_states as st', 'st.serial_unit_id', '=', 'su.id')
                ->leftJoin('crm_warehouses as w', 'w.id', '=', 'st.warehouse_id')
                ->leftJoin('crm_serial_warranties as wa', 'wa.serial_unit_id', '=', 'su.id')
                ->whereIn('su.product_id', $serialProductIds)
                ->select([
                    'su.id',
                    'su.product_id',
                    'si.code',
                    'st.state',
                    'st.warehouse_id',
                    'w.name as warehouse_name',
                    'wa.order_id',
                    'wa.warranty_end_at',
                ])
                ->orderBy('si.code')
                ->get();

            $serialRowsByProduct = $serialRows
                ->groupBy('product_id')
                ->map(fn($rows) => $rows->values())
                ->toArray();
        }
    }

    $tierPriceMap = collect($tierPrices ?? []);
    $savedTierPrices = collect($formData['tierPrices'] ?? []);

    $fmtInput = function ($value) {
        if ($value === null || $value === '') {
            return '';
        }

        $number = (float) $value;

        if (abs($number - round($number)) < 0.00001) {
            return (string) (int) round($number);
        }

        return rtrim(rtrim(number_format($number, 2, '.', ''), '0'), '.');
    };
@endphp

@section('content')
<style>
    :root{
        --ego-bg:#f4f8fb;
        --ego-card:#fff;
        --ego-line:#dbe7ef;
        --ego-text:#0f172a;
        --ego-muted:#64748b;
        --ego-main:#12aaa6;
        --ego-main2:#0f766e;
        --ego-danger:#ef4444;
        --ego-soft:#ecfeff;
        --ego-soft-2:#f0fdfa;
        --ego-shadow:0 12px 34px rgba(15,23,42,.06);
    }
    .ego-page{padding:14px;background:var(--ego-bg);min-height:calc(100vh - 80px)}
    .ego-head{display:flex;align-items:center;justify-content:space-between;gap:12px;margin-bottom:12px}
    .ego-head h1{font-size:22px;font-weight:900;margin:0;color:var(--ego-text)}
    .ego-head p{font-size:12px;margin:3px 0 0;color:var(--ego-muted)}
    .ego-actions{display:flex;gap:8px;flex-wrap:wrap}
    .ego-btn{
        border:0;border-radius:11px;padding:8px 12px;font-size:12px;font-weight:800;text-decoration:none;
        display:inline-flex;align-items:center;justify-content:center;gap:6px;cursor:pointer;transition:.15s
    }
    .ego-btn:hover{transform:translateY(-1px)}
    .ego-btn-main{background:linear-gradient(135deg,var(--ego-main),#16c6bf);color:#fff;box-shadow:0 10px 22px rgba(18,170,166,.18)}
    .ego-btn-light{background:#fff;color:var(--ego-text);border:1px solid var(--ego-line)}
    .ego-btn-danger{background:#fff1f2;color:#be123c;border:1px solid #fecdd3}
    .ego-btn-soft{background:#f8fafc;color:#334155;border:1px solid #dbe7ef}
    .ego-layout{display:grid;grid-template-columns:minmax(0,1fr) 270px;gap:12px;align-items:start}
    .ego-card{background:#fff;border:1px solid var(--ego-line);border-radius:18px;box-shadow:var(--ego-shadow);overflow:hidden}
    .ego-card-head{display:flex;align-items:center;justify-content:space-between;gap:10px;padding:10px 12px;border-bottom:1px solid var(--ego-line);background:linear-gradient(135deg,#f8ffff,#effafa)}
    .ego-card-head h2{font-size:15px;font-weight:900;margin:0;color:var(--ego-text)}
    .ego-card-head p{font-size:11px;color:var(--ego-muted);margin:2px 0 0}
    .ego-body{padding:12px}
    .ego-label{font-size:10px;font-weight:900;text-transform:uppercase;color:#334155;margin-bottom:4px;display:block;letter-spacing:.02em}
    .ego-label b{color:var(--ego-danger)}
    .ego-input,.ego-select,.ego-textarea{
        width:100%;height:38px;border:1px solid var(--ego-line);border-radius:11px;background:#fff;
        padding:8px 10px;color:var(--ego-text);outline:none;font-size:13px;font-weight:600;transition:.15s;
    }
    .ego-input:focus,.ego-select:focus,.ego-textarea:focus{border-color:#67e8f9;box-shadow:0 0 0 4px rgba(103,232,249,.14)}
    .ego-textarea{height:68px;resize:vertical}
    .top-grid{display:grid;grid-template-columns:1.5fr 1fr 1fr;gap:10px}
    .line-card{border:1px solid var(--ego-line);border-radius:16px;background:#fff;margin-top:10px;overflow:hidden}
    .line-top{
        display:flex;justify-content:space-between;align-items:center;gap:10px;
        padding:9px 12px;background:linear-gradient(135deg,#f8fafc,#f0fdfa);border-bottom:1px solid var(--ego-line)
    }
    .line-title{font-size:13px;font-weight:900;color:var(--ego-text);display:flex;align-items:center;gap:8px}
    .line-title-input{
        height:32px;
        min-width:190px;
        max-width:320px;
        border:1px solid transparent;
        background:#fff;
        border-radius:10px;
        padding:6px 10px;
        font-size:13px;
        font-weight:900;
        color:#0f172a;
        outline:none;
    }
    .line-title-input:focus{
        border-color:#67e8f9;
        box-shadow:0 0 0 4px rgba(103,232,249,.15);
    }
    .line-no{width:26px;height:26px;border-radius:999px;background:#ccfbf1;color:#0f766e;display:inline-flex;align-items:center;justify-content:center;font-weight:900}
    .line-top-right{display:flex;align-items:center;gap:8px;flex-wrap:wrap;justify-content:flex-end}
    .line-mini-badges{display:flex;align-items:center;gap:6px;flex-wrap:wrap}
    .mini-badge{
        display:inline-flex;align-items:center;gap:5px;
        padding:6px 10px;border-radius:999px;
        border:1px solid #d7efe9;background:#fff;
        font-size:11px;font-weight:800;color:#0f172a;white-space:nowrap
    }
    .mini-badge b{color:#0f766e;font-weight:900}
    .line-body{padding:11px}
    .line-card.collapsed .line-body{display:none}
    .line-grid-1{display:grid;grid-template-columns:1.2fr .9fr .55fr .95fr .65fr;gap:8px;align-items:end}
    .line-grid-2{display:grid;grid-template-columns:.9fr .75fr 1.35fr;gap:8px;align-items:end;margin-top:8px}
    .line-grid-3{display:grid;grid-template-columns:1fr 1fr;gap:8px;align-items:end;margin-top:8px;padding-top:8px;border-top:1px dashed #cbd5e1}
    .serial-manager{margin-top:9px;border:1px dashed #99f6e4;border-radius:14px;background:#f8ffff;padding:9px}
    .serial-manager-head{display:flex;align-items:center;justify-content:space-between;gap:8px;margin-bottom:8px}
    .serial-manager-title{font-size:11px;font-weight:900;color:#0f766e;text-transform:uppercase}
    .serial-manager-note{font-size:10px;color:#64748b;font-weight:700}
    .serial-list{display:grid;gap:6px;margin-bottom:8px}
    .serial-item{display:grid;grid-template-columns:minmax(0,1fr) auto auto auto;gap:6px;align-items:center;background:#fff;border:1px solid #dbe7ef;border-radius:11px;padding:6px}
    .serial-item input{height:30px;font-size:12px;border:1px solid #dbe7ef;border-radius:9px;padding:5px 8px;font-weight:800}
    .serial-state{font-size:10px;font-weight:900;border-radius:999px;padding:5px 7px;background:#eef7f7;color:#0f766e;white-space:nowrap}
    .serial-state.sold{background:#fff7df;color:#92400e}
    .serial-state.removed{background:#fff1f2;color:#991b1b}
    .serial-btn{height:30px;border:0;border-radius:9px;padding:0 9px;font-size:11px;font-weight:900;cursor:pointer}
    .serial-btn-save{background:#0f8b8d;color:#fff}
    .serial-btn-delete{background:#fff1f2;color:#be123c;border:1px solid #fecdd3}
    .serial-add textarea{width:100%;height:72px;border:1px solid #dbe7ef;border-radius:11px;padding:8px;font-size:12px;font-family:ui-monospace,SFMono-Regular,Menlo,Consolas,monospace}
    .serial-add-row{display:flex;align-items:center;justify-content:space-between;gap:8px;margin-top:6px}
    .serial-empty{font-size:11px;color:#64748b;font-weight:700;background:#fff;border:1px dashed #cbd5e1;border-radius:10px;padding:8px}
    .readonly-box{
        height:38px;background:var(--ego-soft);border:1px solid #99f6e4;color:#0f766e;border-radius:11px;
        padding:8px 10px;text-align:right;font-weight:900;font-size:13px
    }
    .line-foot{text-align:right;font-size:11px;color:var(--ego-muted);margin-top:6px}
    .money{font-weight:900;color:var(--ego-main2)}
    .summary-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:9px;margin-top:10px}
    .summary-item{background:#fff;border:1px solid var(--ego-line);border-radius:15px;padding:10px;text-align:center}
    .summary-item span{display:block;font-size:10px;font-weight:900;text-transform:uppercase;color:var(--ego-muted)}
    .summary-item b{display:block;margin-top:3px;font-size:16px;color:var(--ego-text)}
    .price-card{position:sticky;top:86px}
    .price-card .ego-body{padding:9px}
    .price-row{border:1px solid var(--ego-line);border-radius:12px;padding:8px;background:#fff;margin-bottom:7px}
    .tier-price-row{background:#fcfeff}
    .price-card .ego-card-head{padding:10px 12px}
    .price-card .ego-card-head h2{font-size:14px}
    .price-card .ego-card-head p{font-size:11px}
    .price-title{font-weight:900;color:var(--ego-text);font-size:11px;margin-bottom:5px}
    .price-grid{display:grid;grid-template-columns:1fr .5fr 1fr;gap:5px}
    .price-grid small{font-size:8px;color:var(--ego-muted);font-weight:900;text-transform:uppercase;display:block;margin-bottom:2px}
    .price-card .ego-input{height:32px;padding:6px 8px;font-size:12px;border-radius:10px}
    .history-card{margin-top:12px}
    .table-wrap{overflow:auto}
    .history-table{width:100%;min-width:1080px;border-collapse:separate;border-spacing:0}
    .history-table th{background:#f1f5f9;color:#334155;font-size:10px;font-weight:900;text-transform:uppercase;padding:8px;border-bottom:1px solid var(--ego-line)}
    .history-table td{padding:8px;border-bottom:1px solid #e2e8f0;vertical-align:middle;font-size:12px}
    .tag{display:inline-flex;border-radius:999px;padding:4px 8px;background:#ecfeff;border:1px solid #99f6e4;color:#0f766e;font-weight:900;font-size:11px}
    .bottom-actions{position:sticky;bottom:0;z-index:5;display:flex;justify-content:flex-end;gap:8px;padding:10px 0 0;margin-top:8px;background:rgba(244,248,251,.92);backdrop-filter:blur(10px)}
    .text-end{text-align:right}
    @media(max-width:1200px){.ego-layout{grid-template-columns:1fr}.price-card{position:relative;top:auto}}
    @media(max-width:992px){.top-grid,.line-grid-1,.line-grid-2,.line-grid-3,.summary-grid,.price-grid{grid-template-columns:1fr}}
</style>

<div class="container-fluid ego-page">
    <div class="ego-head">
        <div>
            <h1>Sửa sản phẩm</h1>
            <p>Đang gom {{ $groupRows->pluck('product_id')->unique()->count() }} SKU cùng tên: {{ $product->name }}</p>
        </div>
        <div class="ego-actions">
            <a href="{{ route('products.input') }}" class="ego-btn ego-btn-light">Quay lại</a>
            <button type="submit" form="productEditForm" class="ego-btn ego-btn-main">Lưu thay đổi</button>
        </div>
    </div>

    @if(session('success')) <div class="alert alert-success">{{ session('success') }}</div> @endif
    @if(session('error')) <div class="alert alert-danger">{{ session('error') }}</div> @endif
    @if($errors->any())
        <div class="alert alert-danger">
            <b>Có lỗi:</b>
            <ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
        </div>
    @endif

    <form id="productEditForm" action="{{ route('products.update', $product->id) }}" method="POST">
        @csrf
        @method('PUT')

        <input type="hidden" name="group_edit_mode" value="1">
        <input type="hidden" name="sku" id="mainSku" value="{{ $product->sku }}">
        <input type="hidden" name="price_agent" id="mainCost" value="{{ $product->price_agent ?? 0 }}">
        <input type="hidden" name="cost_vat_percent" id="mainVat" value="{{ $product->cost_vat_percent ?? $product->vat_percent ?? 0 }}">

        <div class="ego-layout">
            <div>
                <div class="ego-card">
                    <div class="ego-card-head">
                        <div>
                            <h2>Thông tin sản phẩm</h2>
                            <p>Mỗi dòng bên dưới là một SKU / dòng tồn riêng trong cùng nhóm.</p>
                        </div>
                        <button type="button" id="addLineBtn" class="ego-btn ego-btn-main">+ Thêm ô</button>
                    </div>

                    <div class="ego-body">
                        <div class="top-grid">
                            <div>
                                <label class="ego-label">Tên sản phẩm <b>*</b></label>
                                <input class="ego-input" name="name" id="productName" value="{{ old('name', $product->name) }}" required>
                            </div>
                            <div>
                                <label class="ego-label">Danh mục</label>
                                <select class="ego-select" name="category_id">
                                    <option value="">Chọn danh mục</option>
                                    @foreach($categories as $cat)
                                        <option value="{{ $cat->id }}" {{ (string) old('category_id', $product->category_id) === (string) $cat->id ? 'selected' : '' }}>{{ $cat->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label class="ego-label">Thương hiệu</label>
                                <select class="ego-select" name="brand_id">
                                    <option value="">Chọn thương hiệu</option>
                                    @foreach($brands as $brand)
                                        <option value="{{ $brand->id }}" {{ (string) old('brand_id', $product->brand_id) === (string) $brand->id ? 'selected' : '' }}>{{ $brand->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <div id="linesContainer"></div>

                        <div class="summary-grid">
                            <div class="summary-item"><span>Tổng số dòng</span><b id="sumLines">0</b></div>
                            <div class="summary-item"><span>Tổng tồn nhập</span><b id="sumQty">0</b></div>
                            <div class="summary-item"><span>Tổng vốn nhập</span><b id="sumAmount">0 đ</b></div>
                            <div class="summary-item"><span>Tồn hiện tại</span><b>{{ number_format($currentStockQty) }}</b></div>
                        </div>
                    </div>
                </div>

                <div class="ego-card history-card">
                    <div class="ego-card-head">
                        




{{-- EGO_FORCE_SERIAL_PANEL_START --}}
@php
    $egoSerialProductIds = collect();

    if (isset($initialLines)) {
        $egoSerialProductIds = collect($initialLines)
            ->map(function ($line) {
                return is_array($line) ? ($line['product_id'] ?? null) : ($line->product_id ?? null);
            })
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();
    }

    if ($egoSerialProductIds->isEmpty() && isset($groupRows)) {
        $egoSerialProductIds = collect($groupRows)
            ->pluck('product_id')
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();
    }

    if ($egoSerialProductIds->isEmpty() && isset($product)) {
        $egoSerialProductIds = collect([(int) $product->id])->filter()->values();
    }

    $egoSerialProducts = collect();

    if ($egoSerialProductIds->isNotEmpty() && \Illuminate\Support\Facades\Schema::hasTable('crm_product_catalog')) {
        $egoSerialProducts = \Illuminate\Support\Facades\DB::table('crm_product_catalog')
            ->whereIn('id', $egoSerialProductIds)
            ->select('id', 'name', 'sku')
            ->get()
            ->keyBy('id');
    }

    $egoSerialRowsByProduct = collect();

    if (
        $egoSerialProductIds->isNotEmpty() &&
        \Illuminate\Support\Facades\Schema::hasTable('crm_serial_units') &&
        \Illuminate\Support\Facades\Schema::hasTable('crm_serial_unit_identifiers') &&
        \Illuminate\Support\Facades\Schema::hasTable('crm_serial_identifiers') &&
        \Illuminate\Support\Facades\Schema::hasTable('crm_serial_unit_states')
    ) {
        $egoSerialRowsByProduct = \Illuminate\Support\Facades\DB::table('crm_serial_units as su')
            ->join('crm_serial_unit_identifiers as sui', function ($join) {
                $join->on('sui.serial_unit_id', '=', 'su.id')->where('sui.is_primary', 1);
            })
            ->join('crm_serial_identifiers as si', 'si.id', '=', 'sui.serial_identifier_id')
            ->leftJoin('crm_serial_unit_states as st', 'st.serial_unit_id', '=', 'su.id')
            ->leftJoin('crm_warehouses as w', 'w.id', '=', 'st.warehouse_id')
            ->leftJoin('crm_serial_warranties as wa', 'wa.serial_unit_id', '=', 'su.id')
            ->whereIn('su.product_id', $egoSerialProductIds)
            ->select([
                'su.id',
                'su.product_id',
                'si.code',
                'st.state',
                'st.warehouse_id',
                'w.name as warehouse_name',
                'wa.order_id',
                'wa.warranty_end_at',
            ])
            ->orderBy('si.code')
            ->get()
            ->groupBy('product_id');
    }

    $egoSerialWarehouses = \Illuminate\Support\Facades\Schema::hasTable('crm_warehouses')
        ? \Illuminate\Support\Facades\DB::table('crm_warehouses')->select('id', 'name')->orderBy('name')->get()
        : collect();

    $egoStateLabels = [
        'in_stock' => 'Trong kho',
        'sold' => 'Đã bán',
        'delivered' => 'Đã giao',
        'returned' => 'Hàng trả',
        'removed' => 'Đã xóa',
    ];
@endphp

<style>
    .ego-serial-clean{
        width:100%;
        margin:14px 0;
        background:#fff;
        border:1px solid #dbe7ef;
        border-radius:18px;
        overflow:hidden;
        box-shadow:0 10px 26px rgba(15,23,42,.05);
        clear:both;
    }

    .ego-serial-clean-head{
        padding:12px 15px;
        border-bottom:1px solid #dbe7ef;
        background:linear-gradient(90deg,#ecfeff,#ffffff);
    }

    .ego-serial-clean-title{
        font-size:15px;
        font-weight:950;
        color:#0f172a;
    }

    .ego-serial-clean-sub{
        margin-top:3px;
        font-size:11.5px;
        font-weight:700;
        color:#64748b;
    }

    .ego-serial-clean-body{
        padding:14px 15px 16px;
    }

    .ego-serial-product-name{
        font-size:13.5px;
        font-weight:950;
        color:#0f172a;
        margin-bottom:10px;
    }

    .ego-serial-product-name span{
        font-size:11.5px;
        color:#64748b;
        font-weight:800;
    }

    .ego-serial-table{
        width:100%;
        border:1px solid #e2e8f0;
        border-radius:14px;
        overflow:hidden;
        background:#fff;
        margin-bottom:12px;
    }

    .ego-serial-table-head,
    .ego-serial-line{
        display:grid;
        grid-template-columns:minmax(190px,1.2fr) 110px minmax(220px,1fr) 135px 72px 72px;
        align-items:center;
    }

    .ego-serial-table-head{
        background:#f8fafc;
        border-bottom:1px solid #e2e8f0;
    }

    .ego-serial-th{
        padding:9px 10px;
        font-size:10.5px;
        font-weight:950;
        color:#475569;
        text-transform:uppercase;
        letter-spacing:.04em;
        white-space:nowrap;
    }

    .ego-serial-line{
        border-bottom:1px solid #edf2f7;
    }

    .ego-serial-line:last-child{
        border-bottom:0;
    }

    .ego-serial-td{
        padding:8px 10px;
        min-width:0;
    }

    .ego-serial-input,
    .ego-serial-select,
    .ego-serial-add textarea,
    .ego-serial-add select{
        width:100%;
        border:1px solid #dbe7ef;
        border-radius:10px;
        background:#fff;
        padding:7px 9px;
        min-height:34px;
        font-size:12px;
        font-weight:750;
        outline:none;
    }

    .ego-serial-input{
        font-family:ui-monospace,SFMono-Regular,Menlo,Consolas,monospace;
        font-size:11.5px;
    }

    .ego-serial-input:focus,
    .ego-serial-select:focus,
    .ego-serial-add textarea:focus,
    .ego-serial-add select:focus{
        border-color:#0f8b8d;
        box-shadow:0 0 0 3px rgba(15,139,141,.08);
    }

    .ego-serial-badge{
        display:inline-flex;
        align-items:center;
        justify-content:center;
        border-radius:999px;
        padding:5px 8px;
        background:#e8fff7;
        color:#047857;
        font-size:10.5px;
        font-weight:950;
        white-space:nowrap;
    }

    .ego-serial-badge.sold{
        background:#fff7df;
        color:#92400e;
    }

    .ego-serial-warranty{
        font-size:11px;
        color:#64748b;
        font-weight:750;
    }

    .ego-serial-btn{
        width:100%;
        border:0;
        border-radius:10px;
        min-height:34px;
        padding:7px 9px;
        font-size:11.5px;
        font-weight:900;
        cursor:pointer;
        white-space:nowrap;
    }

    .ego-serial-btn.save{
        background:#0f8b8d;
        color:#fff;
        box-shadow:0 8px 16px rgba(15,139,141,.12);
    }

    .ego-serial-btn.del{
        background:#fff1f2;
        color:#be123c;
        border:1px solid #fecdd3;
    }

    .ego-serial-btn.del:disabled,
    .ego-serial-select:disabled{
        opacity:.5;
        cursor:not-allowed;
    }

    .ego-serial-empty{
        padding:13px;
        background:#f8fafc;
        border:1px dashed #cbd5e1;
        border-radius:12px;
        color:#64748b;
        font-size:12px;
        font-weight:700;
        margin-bottom:12px;
    }

    .ego-serial-add{
        background:#f8ffff;
        border:1px dashed #99f6e4;
        border-radius:14px;
        padding:11px;
    }

    .ego-serial-add-grid{
        display:grid;
        grid-template-columns:230px minmax(340px,1fr) 140px;
        gap:10px;
        align-items:end;
    }

    .ego-serial-add label{
        display:block;
        margin-bottom:5px;
        font-size:11px;
        font-weight:950;
        color:#334155;
    }

    .ego-serial-add textarea{
        height:72px;
        resize:vertical;
        font-family:ui-monospace,SFMono-Regular,Menlo,Consolas,monospace;
        line-height:1.45;
    }

    @media(max-width:1100px){
        .ego-serial-table{
            overflow:auto;
        }

        .ego-serial-table-head,
        .ego-serial-line{
            min-width:900px;
        }

        .ego-serial-add-grid{
            grid-template-columns:1fr;
        }
    }
</style>

<div id="egoSerialCleanPanel" class="ego-serial-clean">
    <div class="ego-serial-clean-head">
        <div class="ego-serial-clean-title">Serial / IMEI của sản phẩm</div>
        <div class="ego-serial-clean-sub">Sửa mã serial, đổi kho nếu nhập lộn kho, xóa serial chưa bán và thêm serial mới.</div>
    </div>

    <div class="ego-serial-clean-body">
        @forelse($egoSerialProductIds as $egoPid)
            @php
                $egoProduct = $egoSerialProducts[$egoPid] ?? null;
                $egoRows = collect($egoSerialRowsByProduct[$egoPid] ?? []);
            @endphp

            <div class="ego-serial-product-name">
                {{ $egoProduct->name ?? ('Sản phẩm #' . $egoPid) }}
                @if(!empty($egoProduct->sku))
                    <span>— {{ $egoProduct->sku }}</span>
                @endif
            </div>

            @if($egoRows->isNotEmpty())
                <div class="ego-serial-table">
                    <div class="ego-serial-table-head">
                        <div class="ego-serial-th">Mã serial</div>
                        <div class="ego-serial-th">Trạng thái</div>
                        <div class="ego-serial-th">Kho hiện tại / đổi kho</div>
                        <div class="ego-serial-th">Bảo hành</div>
                        <div class="ego-serial-th">Sửa</div>
                        <div class="ego-serial-th">Xóa</div>
                    </div>

                    @foreach($egoRows as $sr)
                        @php
                            $state = $sr->state ?: 'unknown';
                            $locked = in_array($state, ['sold', 'delivered', 'warranty', 'warranty_claim'], true);
                        @endphp

                        <div class="ego-serial-line" data-serial-row="{{ $sr->id }}">
                            <div class="ego-serial-td">
                                <input class="ego-serial-input" data-serial-code value="{{ $sr->code }}">
                            </div>

                            <div class="ego-serial-td">
                                <span class="ego-serial-badge {{ $locked ? 'sold' : '' }}">
                                    {{ $egoStateLabels[$state] ?? $state }}
                                </span>
                            </div>

                            <div class="ego-serial-td">
                                <select class="ego-serial-select" data-serial-warehouse @disabled($locked)>
                                    <option value="">-- Chọn kho --</option>
                                    @foreach($egoSerialWarehouses as $wh)
                                        <option value="{{ $wh->id }}" @selected((int)$sr->warehouse_id === (int)$wh->id)>
                                            {{ $wh->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="ego-serial-td">
                                @if(!empty($sr->warranty_end_at))
                                    <span class="ego-serial-warranty">đến {{ \Carbon\Carbon::parse($sr->warranty_end_at)->format('d/m/Y') }}</span>
                                @else
                                    <span class="ego-serial-warranty">Chưa kích hoạt</span>
                                @endif
                            </div>

                            <div class="ego-serial-td">
                                <button type="button" class="ego-serial-btn save" onclick="egoSerialUpdateClean({{ $sr->id }}, this)">Sửa</button>
                            </div>

                            <div class="ego-serial-td">
                                <button type="button" class="ego-serial-btn del" onclick="egoSerialDeleteClean({{ $sr->id }}, this)" @disabled($locked)>Xóa</button>
                            </div>
                        </div>
                    @endforeach
                </div>
            @else
                <div class="ego-serial-empty">Chưa có serial nào cho sản phẩm này.</div>
            @endif

            <div class="ego-serial-add">
                <div class="ego-serial-add-grid">
                    <div>
                        <label>Kho nhập cho serial mới</label>
                        <select id="ego-add-wh-{{ $egoPid }}">
                            <option value="">Chọn kho</option>
                            @foreach($egoSerialWarehouses as $wh)
                                <option value="{{ $wh->id }}">{{ $wh->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label>Serial mới, mỗi dòng 1 mã</label>
                        <textarea id="ego-add-serials-{{ $egoPid }}" placeholder="SN001&#10;SN002&#10;SN003"></textarea>
                    </div>

                    <div>
                        <label>&nbsp;</label>
                        <button type="button" class="ego-serial-btn save" onclick="egoSerialAddClean({{ $egoPid }}, this)">+ Thêm serial</button>
                    </div>
                </div>
            </div>
        @empty
            <div class="ego-serial-empty">Không xác định được sản phẩm để hiển thị serial.</div>
        @endforelse
    </div>
</div>

<script>
(function(){
    window.egoSerialCsrf = @json(csrf_token());
    window.egoSerialAddUrl = @json(url('/serial-warranty/product/__PRODUCT__/serials'));
    window.egoSerialUpdateUrl = @json(url('/serial-warranty/serial/__SERIAL__/update'));
    window.egoSerialDeleteUrl = @json(url('/serial-warranty/serial/__SERIAL__/delete'));

    function post(url, data, btn){
        const old = btn ? btn.textContent : '';

        if (btn) {
            btn.disabled = true;
            btn.textContent = '...';
        }

        return fetch(url, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': window.egoSerialCsrf,
                'Accept': 'application/json',
                'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8'
            },
            body: new URLSearchParams(data || {})
        }).then(async function(res){
            const json = await res.json().catch(function(){ return {}; });

            if (!res.ok || json.ok === false) {
                throw new Error(json.message || 'Có lỗi xảy ra.');
            }

            return json;
        }).finally(function(){
            if (btn) {
                btn.disabled = false;
                btn.textContent = old;
            }
        });
    }

    window.egoSerialUpdateClean = function(serialId, btn){
        const row = btn.closest('[data-serial-row]');
        const code = row.querySelector('[data-serial-code]').value.trim();
        const wh = row.querySelector('[data-serial-warehouse]') ? row.querySelector('[data-serial-warehouse]').value : '';

        if (!code) {
            alert('Vui lòng nhập mã serial.');
            return;
        }

        post(window.egoSerialUpdateUrl.replace('__SERIAL__', serialId), {
            code: code,
            warehouse_id: wh
        }, btn).then(function(json){
            alert(json.message || 'Đã cập nhật serial.');
            location.reload();
        }).catch(function(err){
            alert(err.message);
        });
    };

    window.egoSerialDeleteClean = function(serialId, btn){
        if (!confirm('Xóa serial này? Chỉ xóa serial nhập sai và chưa bán.')) return;

        post(window.egoSerialDeleteUrl.replace('__SERIAL__', serialId), {}, btn)
            .then(function(json){
                alert(json.message || 'Đã xóa serial.');
                location.reload();
            })
            .catch(function(err){
                alert(err.message);
            });
    };

    window.egoSerialAddClean = function(productId, btn){
        const wh = document.getElementById('ego-add-wh-' + productId).value;
        const serials = document.getElementById('ego-add-serials-' + productId).value.trim();

        if (!wh) {
            alert('Vui lòng chọn kho.');
            return;
        }

        if (!serials) {
            alert('Vui lòng nhập serial.');
            return;
        }

        post(window.egoSerialAddUrl.replace('__PRODUCT__', productId), {
            warehouse_id: wh,
            serials: serials,
            note: 'Thêm từ màn sửa sản phẩm'
        }, btn).then(function(json){
            alert(json.message || 'Đã thêm serial.');
            location.reload();
        }).catch(function(err){
            alert(err.message);
        });
    };

    document.addEventListener('DOMContentLoaded', function(){
        const panel = document.getElementById('egoSerialCleanPanel');

        if (!panel) return;

        const all = Array.from(document.querySelectorAll('div,h1,h2,h3,h4,section'));
        const historyTitle = all.find(function(el){
            const text = (el.textContent || '').trim();
            return text.indexOf('Lịch sử / Dashboard dòng nhập') === 0 || text.indexOf('Lịch sử nhập / xuất kho sản phẩm') === 0;
        });

        if (historyTitle) {
            let card = historyTitle.closest('.ego-card') || historyTitle.closest('.card') || historyTitle.parentElement;

            if (card && card.parentNode && card.contains(panel)) {
                card.parentNode.insertBefore(panel, card);
            }
        }
    });
})();
</script>
{{-- EGO_FORCE_SERIAL_PANEL_END --}}

<div>
                            <h2>Lịch sử nhập / xuất kho sản phẩm</h2>
                            <p>Hiển thị đúng nhập kho, xuất đơn hàng, xuất công trình / đơn vật tư và điều chỉnh kho.</p>
                        </div>
                    </div>
                    <div class="ego-body">
                        <div class="table-wrap">
                            <table class="history-table">
                                <thead>
                                    <tr>
                                        <th>Nguồn</th>
                                        <th>Kho</th>
                                        <th>Thời gian</th>
                                        <th class="text-end">Trước</th>
                                        <th class="text-end">Thay đổi</th>
                                        <th class="text-end">Sau</th>
                                        <th>Người nhập / xuất</th>
                                        <th>Ghi chú</th>
                                    </tr>
                                </thead>
                                <tbody id="stockMovementHistoryBody">
                                    @forelse($productStockLogs as $log)
                                        @php
                                            $changeQty = (int)($log->change_qty ?? 0);
                                            $reason = (string)($log->reason ?? '');
                                            $noteRaw = (string)($log->note_safe ?? $log->note ?? '');
                                            $refType = (string)($log->reference_type_safe ?? $log->reference_type ?? '');

                                            $reasonLower = mb_strtolower($reason, 'UTF-8');
                                            $noteLower = mb_strtolower($noteRaw, 'UTF-8');
                                            $refTypeLower = mb_strtolower($refType, 'UTF-8');

                                            if (!empty($log->order_code) || str_contains($refTypeLower, 'order')) {
                                                $sourceLabel = 'Đơn hàng ' . ($log->order_code ?: ('#' . ($log->reference_id ?? '—')));
                                                $sourceClass = 'is-order';
                                                $sourceIcon = 'bi-receipt-cutoff';
                                            } elseif (!empty($log->site_name) || str_contains($refTypeLower, 'material') || str_contains($reasonLower, 'vật tư') || str_contains($reasonLower, 'công trình')) {
                                                $sourceLabel = !empty($log->site_name)
                                                    ? ('Công trình: ' . $log->site_name)
                                                    : ('Đơn vật tư #' . ($log->reference_id ?? '—'));
                                                $sourceClass = 'is-site';
                                                $sourceIcon = 'bi-kanban';
                                            } elseif (
                                                str_contains($refTypeLower, 'manual') ||
                                                str_contains($reasonLower, 'manual') ||
                                                str_contains($reasonLower, 'nhập tay') ||
                                                str_contains($reasonLower, 'nhập kho') ||
                                                str_contains($reasonLower, 'sản phẩm đầu vào') ||
                                                str_contains($noteLower, 'nhập tay')
                                            ) {
                                                $sourceLabel = $changeQty >= 0 ? 'Nhập kho / nhập tay' : 'Điều chỉnh tay';
                                                $sourceClass = 'is-other';
                                                $sourceIcon = 'bi-pencil-square';
                                            } else {
                                                $sourceLabel = $changeQty >= 0 ? 'Nhập kho' : 'Xuất kho / Điều chỉnh';
                                                $sourceClass = 'is-other';
                                                $sourceIcon = $changeQty >= 0 ? 'bi-box-arrow-in-down' : 'bi-arrow-left-right';
                                            }

                                            $before = $log->qty_before_safe ?? null;
                                            $after = $log->qty_after_safe ?? null;
                                            $createdAtText = !empty($log->created_at)
                                                ? \Carbon\Carbon::parse($log->created_at)->format('d/m/Y H:i')
                                                : '—';
                                        @endphp

                                        <tr>
                                            <td>
                                                <span class="tag {{ $sourceClass }}">
                                                    <i class="bi {{ $sourceIcon }}"></i>
                                                    {{ $sourceLabel }}
                                                </span>
                                            </td>
                                            <td>{{ $log->warehouse_name ?? '—' }}</td>
                                            <td>{{ $createdAtText }}</td>
                                            <td class="text-end">
                                                <span class="tag">{{ $before !== null ? number_format((int)$before) : '—' }}</span>
                                            </td>
                                            <td class="text-end">
                                                <span class="tag {{ $changeQty < 0 ? 'text-danger' : 'text-success' }}">
                                                    {{ $changeQty > 0 ? '+' : '' }}{{ number_format($changeQty) }}
                                                </span>
                                            </td>
                                            <td class="text-end">
                                                <span class="tag">{{ $after !== null ? number_format((int)$after) : '—' }}</span>
                                            </td>
                                            <td>{{ $log->user_name ?? '—' }}</td>
                                            <td>{{ $noteRaw !== '' ? $noteRaw : ($reason !== '' ? $reason : '—') }}</td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="8" class="text-center text-muted py-4">
                                                Chưa có lịch sử nhập / xuất kho cho sản phẩm này.
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>

                            <table style="display:none">
                                <tbody id="historyBody"></tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <div class="bottom-actions">
                    <a href="{{ route('products.input') }}" class="ego-btn ego-btn-light">Hủy</a>
                    <button type="submit" class="ego-btn ego-btn-main">Lưu thay đổi</button>
                </div>
            </div>

            <div>
                <div class="ego-card price-card">
                    <div class="ego-card-head">
                        <div>
                            <h2>Giá bán / Giá đại lý</h2>
                            <p>Trước VAT | VAT | Sau VAT</p>
                        </div>
                    </div>
                    <div class="ego-body">
                        <div class="price-row sale-row">
                            <div class="price-title">Giá bán mặc định</div>
                            <div class="price-grid">
                                <div>
                                    <small>Trước VAT</small>
                                    <input class="ego-input text-end sale-before" type="number" min="0" step="0.01" name="price_retail" value="{{ old('price_retail', $fmtInput($product->price_retail ?? 0)) }}">
                                </div>
                                <div>
                                    <small>VAT</small>
                                    <input class="ego-input text-end sale-vat" type="number" min="0" max="100" step="0.01" name="vat_percent" value="{{ old('vat_percent', $fmtInput($product->vat_percent ?? 0)) }}">
                                </div>
                                <div>
                                    <small>Sau VAT</small>
                                    <input class="ego-input text-end sale-after" type="text" readonly value="0 đ">
                                </div>
                            </div>
                        </div>

                        @foreach($priceTiers as $tier)
                            @php
                                $saved = $savedTierPrices->get((int) $tier->id, []);
                                $tierBefore = old('prices.' . $tier->id . '.before_vat', $fmtInput(data_get($saved, 'before_vat', data_get($saved, 'price', ''))));
                                $tierVat = old('prices.' . $tier->id . '.vat_percent', $fmtInput(data_get($saved, 'vat_percent', $product->vat_percent ?? 0)));
                            @endphp
                            <div class="price-row sale-row tier-price-row">
                                <div class="price-title">{{ $tier->name }}</div>
                                <div class="price-grid">
                                    <div>
                                        <small>Trước VAT</small>
                                        <input class="ego-input text-end sale-before" type="number" min="0" step="0.01" name="prices[{{ $tier->id }}][before_vat]" value="{{ $tierBefore }}" placeholder="0">
                                    </div>
                                    <div>
                                        <small>VAT</small>
                                        <input class="ego-input text-end sale-vat" type="number" min="0" max="100" step="0.01" name="prices[{{ $tier->id }}][vat_percent]" value="{{ $tierVat }}" placeholder="0">
                                    </div>
                                    <div>
                                        <small>Sau VAT</small>
                                        <input class="ego-input text-end sale-after" type="text" readonly value="0 đ">
                                    </div>
                                </div>
                            </div>
                        @endforeach

                        <div class="price-row">
                            <div class="price-title">Ghi chú chung</div>
                            <textarea class="ego-textarea" name="note" placeholder="Ghi chú sản phẩm...">{{ old('note', $product->note ?? '') }}</textarea>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>

<script>
(function(){
    const companies = @json($companyOptions);
    const warehouses = @json($warehouseOptions);
    const initialLines = @json($initialLines);
    const stockHistoryRows = @json($stockHistoryRows ?? collect());
    const serialRowsByProduct = @json($serialRowsByProduct);
    const serialCsrf = @json(csrf_token());
    const serialAddUrlTemplate = @json(url('/serial-warranty/product/__PRODUCT__/serials'));
    const serialUpdateUrlTemplate = @json(url('/serial-warranty/serial/__SERIAL__/update'));
    const serialDeleteUrlTemplate = @json(url('/serial-warranty/serial/__SERIAL__/delete'));
    const linesContainer = document.getElementById('linesContainer');
    const addLineBtn = document.getElementById('addLineBtn');
    const historyBody = document.getElementById('historyBody');

    function n(v){
        let s = String(v || '').trim().replace(/\s/g, '').replace(/đ/gi, '');
        if (!s) return 0;
        if (s.includes(',') && s.includes('.')) s = s.replace(/\./g, '').replace(',', '.');
        else if ((s.match(/\./g) || []).length > 1) s = s.replace(/\./g, '');
        else if (s.includes(',')) s = s.replace(',', '.');
        return Number(s) || 0;
    }
    function money(v){ return Math.round(Number(v || 0)).toLocaleString('vi-VN') + ' đ'; }
    function esc(v){ return String(v || '').replace(/[&<>"']/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[m])); }

    function calcSaleRows(){
        document.querySelectorAll('.sale-row').forEach(row => {
            const before = n(row.querySelector('.sale-before')?.value);
            const vat = n(row.querySelector('.sale-vat')?.value);
            const out = row.querySelector('.sale-after');
            if (out) out.value = money(before * (1 + vat / 100));
        });
    }

    function companyOptionsHtml(selected){
        return '<option value="">Chọn công ty</option>' + companies.map(c => `<option value="${esc(c.id)}" ${String(selected) === String(c.id) ? 'selected' : ''}>${esc(c.name)}</option>`).join('');
    }

    function warehouseOptionsHtml(companyId, selected){
        let html = '<option value="">Chọn kho</option>';
        warehouses.forEach(w => {
            if (!companyId || String(w.company_id) === String(companyId)) {
                html += `<option value="${esc(w.id)}" ${String(selected) === String(w.id) ? 'selected' : ''}>${esc(w.name)}</option>`;
            }
        });
        return html;
    }


    function serialStateLabel(state){
        const map = {
            in_stock: 'Trong kho',
            sold: 'Đã bán',
            delivered: 'Đã giao',
            returned: 'Hàng trả',
            removed: 'Đã xóa'
        };
        return map[state] || state || 'Không rõ';
    }

    function serialManagerHtml(index, val){
        const productId = Number(val.product_id || 0);
        const rows = serialRowsByProduct[String(productId)] || serialRowsByProduct[productId] || [];

        const listHtml = rows.length ? rows.map(item => {
            const state = item.state || 'unknown';
            const isSold = ['sold', 'delivered', 'warranty', 'warranty_claim'].includes(state);
            return `
                <div class="serial-item" data-serial-id="${esc(item.id)}">
                    <input class="serial-code-input" value="${esc(item.code || '')}" placeholder="Mã serial">
                    <span class="serial-state ${esc(state)}">${esc(serialStateLabel(state))}</span>
                    <button type="button" class="serial-btn serial-btn-save" onclick="egoSerialUpdate(${Number(item.id)}, this)">Sửa</button>
                    <button type="button" class="serial-btn serial-btn-delete" ${isSold ? 'disabled title="Serial đã bán không được xóa"' : ''} onclick="egoSerialDelete(${Number(item.id)}, this)">Xóa</button>
                </div>
            `;
        }).join('') : '<div class="serial-empty">Chưa có serial cho dòng SKU này. Có thể thêm ở ô bên dưới.</div>';

        return `
            <div class="serial-manager" data-product-id="${productId}">
                <div class="serial-manager-head">
                    <div>
                        <div class="serial-manager-title">Serial / IMEI của SKU này</div>
                        <div class="serial-manager-note">Sửa/xóa/thêm serial. Serial đã bán sẽ bị khóa xóa.</div>
                    </div>
                </div>

                <div class="serial-list">${listHtml}</div>

                <div class="serial-add">
                    <label class="ego-label">Thêm serial mới</label>
                    <textarea class="serial-add-text" placeholder="Mỗi dòng 1 serial. VD:&#10;SN001&#10;SN002"></textarea>
                    <div class="serial-add-row">
                        <span class="serial-manager-note">Serial mới sẽ vào kho đang chọn của dòng này.</span>
                        <button type="button" class="serial-btn serial-btn-save" onclick="egoSerialAdd(${index}, this)" ${productId > 0 ? '' : 'disabled'}>+ Thêm</button>
                    </div>
                </div>
            </div>
        `;
    }

    function egoSerialPost(url, data, btn){
        const oldText = btn ? btn.textContent : '';
        if (btn) {
            btn.disabled = true;
            btn.textContent = 'Đang lưu...';
        }

        return fetch(url, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': serialCsrf,
                'Accept': 'application/json',
                'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8'
            },
            body: new URLSearchParams(data)
        })
        .then(async res => {
            const json = await res.json().catch(() => ({}));
            if (!res.ok || json.ok === false) {
                throw new Error(json.message || 'Có lỗi xảy ra.');
            }
            return json;
        })
        .finally(() => {
            if (btn) {
                btn.disabled = false;
                btn.textContent = oldText;
            }
        });
    }

    window.egoSerialUpdate = function(serialId, btn){
        const row = btn.closest('.serial-item');
        const input = row.querySelector('.serial-code-input');
        const code = (input.value || '').trim();

        if (!code) {
            alert('Vui lòng nhập mã serial.');
            return;
        }

        const url = serialUpdateUrlTemplate.replace('__SERIAL__', serialId);

        egoSerialPost(url, {code}, btn)
            .then(json => {
                alert(json.message || 'Đã sửa serial.');
                location.reload();
            })
            .catch(err => alert(err.message));
    };

    window.egoSerialDelete = function(serialId, btn){
        if (!confirm('Xóa serial này? Chỉ nên xóa serial nhập sai và chưa bán.')) {
            return;
        }

        const url = serialDeleteUrlTemplate.replace('__SERIAL__', serialId);

        egoSerialPost(url, {}, btn)
            .then(json => {
                alert(json.message || 'Đã xóa serial.');
                location.reload();
            })
            .catch(err => alert(err.message));
    };

    window.egoSerialAdd = function(index, btn){
        const card = document.querySelector(`.line-card[data-index="${index}"]`);
        if (!card) return;

        const productId = Number(card.querySelector('[name^="v2_lines"][name$="[product_id]"]')?.value || 0);
        const warehouseId = Number(card.querySelector('.line-warehouse')?.value || 0);
        const companyId = Number(card.querySelector('.line-company')?.value || 0);
        const serials = (card.querySelector('.serial-add-text')?.value || '').trim();

        if (!productId) {
            alert('Dòng này chưa có product_id. Hãy lưu sản phẩm trước rồi thêm serial.');
            return;
        }

        if (!warehouseId) {
            alert('Vui lòng chọn kho trước khi thêm serial.');
            return;
        }

        if (!serials) {
            alert('Vui lòng nhập serial cần thêm.');
            return;
        }

        const url = serialAddUrlTemplate.replace('__PRODUCT__', productId);

        egoSerialPost(url, {
            serials,
            warehouse_id: warehouseId,
            company_id: companyId,
            note: 'Thêm từ màn sửa sản phẩm'
        }, btn)
            .then(json => {
                alert(json.message || 'Đã thêm serial.');
                location.reload();
            })
            .catch(err => alert(err.message));
    };


    function rowHtml(index, line){
        const val = line || {
            product_id: 0,
            stock_lot_id: 0,
            display_name: 'Dòng tồn / SKU',
            sku: '',
            cost: 0,
            vat: 0,
            qty: 0,
            date: '{{ date('Y-m-d') }}',
            extra: 0,
            note: '',
            company_id: '',
            warehouse_id: ''
        };

        return `
            <div class="line-card" data-index="${index}">
                <input type="hidden" name="v2_lines[${index}][product_id]" value="${esc(val.product_id || 0)}">
                <input type="hidden" name="v2_lines[${index}][stock_lot_id]" value="${esc(val.stock_lot_id || 0)}">

                <div class="line-top">
                    <div class="line-title">
                        <span class="line-no">${index + 1}</span>
                        <input class="line-title-input" name="v2_lines[${index}][lot_name]" value="${esc(val.display_name || ('Dòng tồn / SKU ' + (index + 1)))}" placeholder="Tên dòng tồn">
                    </div>

                    <div class="line-top-right">
                        <div class="line-mini-badges">
                            <span class="mini-badge">Tồn: <b class="meta-qty">0</b></span>
                            <span class="mini-badge">Sau VAT: <b class="meta-after">0 đ</b></span>
                            <span class="mini-badge">Tổng: <b class="meta-total">0 đ</b></span>
                        </div>
                        <button type="button" class="ego-btn ego-btn-soft btn-toggle-line">Thu gọn</button>
                        <button type="button" class="ego-btn ego-btn-danger btn-remove-line">Xóa</button>
                    </div>
                </div>

                <div class="line-body">
                    <div class="line-grid-1">
                        <div><label class="ego-label">Mã SKU <b>*</b></label><input class="ego-input line-sku" name="v2_lines[${index}][sku]" required value="${esc(val.sku || '')}"></div>
                        <div><label class="ego-label">Giá trước VAT</label><input class="ego-input text-end line-cost" type="number" min="0" step="0.01" name="v2_lines[${index}][cost_before_vat]" value="${esc(val.cost || 0)}"></div>
                        <div><label class="ego-label">VAT</label><input class="ego-input text-end line-vat" type="number" min="0" max="100" step="0.01" name="v2_lines[${index}][cost_vat_percent]" value="${esc(val.vat || 0)}"></div>
                        <div><label class="ego-label">Giá sau VAT</label><div class="readonly-box line-after">0 đ</div></div>
                        <div><label class="ego-label">Tồn hiện tại</label><input class="ego-input text-end line-qty" type="number" min="0" step="1" name="v2_lines[${index}][qty_in]" value="${esc(val.qty ?? 0)}"></div>
                    </div>

                    <div class="line-grid-2">
                        <div><label class="ego-label">Ngày nhập</label><input class="ego-input line-date" type="date" name="v2_lines[${index}][received_at]" value="${esc(val.date || '{{ date('Y-m-d') }}')}"></div>
                        <div><label class="ego-label">Chi phí +/-</label><input class="ego-input text-end line-extra" type="number" step="0.01" name="v2_lines[${index}][extra_cost]" value="${esc(val.extra || 0)}"></div>
                        <div><label class="ego-label">Ghi chú</label><input class="ego-input line-note" type="text" name="v2_lines[${index}][note]" value="${esc(val.note || '')}" placeholder="VD: vận chuyển, bốc xếp..."></div>
                    </div>

                    <div class="line-grid-3">
                        <div><label class="ego-label">Chọn công ty <b>*</b></label><select class="ego-select line-company" name="v2_lines[${index}][company_id]" required>${companyOptionsHtml(val.company_id || '')}</select></div>
                        <div><label class="ego-label">Chọn kho <b>*</b></label><select class="ego-select line-warehouse" name="v2_lines[${index}][warehouse_id]" required>${warehouseOptionsHtml(val.company_id || '', val.warehouse_id || '')}</select></div>
                    </div>

                    <div class="line-foot">
                        Giá vốn thực tế / cái: <span class="money line-actual">0 đ</span> · Tổng dòng: <span class="money line-total">0 đ</span>
                    </div>
                </div>
            </div>
        `;
    }

    function refreshIndexes(){
        document.querySelectorAll('.line-card').forEach((row, idx) => {
            row.dataset.index = idx;
            row.querySelector('.line-no').textContent = idx + 1;
            const titleInput = row.querySelector('.line-title-input');
            if (titleInput && !titleInput.value.trim()) {
                titleInput.value = 'Dòng tồn / SKU ' + (idx + 1);
            }
            row.querySelectorAll('[name]').forEach(input => input.name = input.name.replace(/v2_lines\[\d+\]/, 'v2_lines[' + idx + ']'));
        });
    }

    function recalcLine(row){
        const cost = n(row.querySelector('.line-cost').value);
        const vat = n(row.querySelector('.line-vat').value);
        const qty = Math.max(0, n(row.querySelector('.line-qty').value));
        const extra = n(row.querySelector('.line-extra').value);
        const after = cost * (1 + vat / 100);
        const actual = after + (qty > 0 ? extra / qty : 0);
        const total = actual * qty;

        row.dataset.after = after;
        row.dataset.actual = actual;
        row.dataset.total = total;

        row.querySelector('.line-after').textContent = money(after);
        row.querySelector('.line-actual').textContent = money(actual);
        row.querySelector('.line-total').textContent = money(total);

        row.querySelector('.meta-qty').textContent = qty.toLocaleString('vi-VN');
        row.querySelector('.meta-after').textContent = money(after);
        row.querySelector('.meta-total').textContent = money(total);
    }

    function syncMain(){
        const first = document.querySelector('.line-card');
        if (!first) return;
        document.getElementById('mainSku').value = first.querySelector('.line-sku').value || '';
        document.getElementById('mainCost').value = first.querySelector('.line-cost').value || 0;
        document.getElementById('mainVat').value = first.querySelector('.line-vat').value || 0;
    }

    function updateHistory(){
        const productName = document.getElementById('productName').value || '';
        const rows = document.querySelectorAll('.line-card');
        historyBody.innerHTML = '';
        let totalQty = 0, totalAmount = 0;

        rows.forEach(row => recalcLine(row));

        const historyRows = Array.isArray(stockHistoryRows) && stockHistoryRows.length ? stockHistoryRows : [];

        if (historyRows.length) {
            historyRows.forEach((item, idx) => {
                // EGO FIX: Không hiển thị lịch sử xuất kho theo đơn trong màn sửa sản phẩm
                if (String(item.type || item.note || '').includes('Xuất kho đơn')) {
                    return;
                }

                const qty = Number(item.qty || 0);
                const tr = document.createElement('tr');

                tr.innerHTML = `
                    <td><span class="tag">${idx + 1}</span></td>
                    <td>${esc(item.product_name || productName || '-')}</td>
                    <td><b>${esc(item.sku || '-')}</b></td>
                    <td>${esc(item.company_name || '-')}</td>
                    <td>${esc(item.warehouse_name || '-')}</td>
                    <td>${esc(item.date || '-')}</td>
                    <td class="text-end">${Number(item.cost || 0).toLocaleString('vi-VN')}</td>
                    <td class="text-end">${Number(item.vat || 0)}%</td>
                    <td class="text-end"><span class="money">${money(item.after || 0)}</span></td>
                    <td class="text-end"><b class="${qty < 0 ? 'text-danger' : 'text-success'}">${qty > 0 ? '+' : ''}${qty.toLocaleString('vi-VN')}</b></td>
                    <td class="text-end">${Number(item.extra || 0).toLocaleString('vi-VN')}</td>
                    <td class="text-end"><span class="money">${money(item.actual || 0)}</span></td>
                    <td class="text-end"><span class="money ${Number(item.total || 0) < 0 ? 'text-danger' : ''}">${money(item.total || 0)}</span></td>
                    <td>${esc(item.type || item.note || '-')}</td>
                `;

                historyBody.appendChild(tr);

                totalQty += qty;
                totalAmount += Number(item.total || 0);
            });
        } else {
            rows.forEach((row, idx) => {
                const sku = row.querySelector('.line-sku').value || '';
                const companySelect = row.querySelector('.line-company');
                const warehouseSelect = row.querySelector('.line-warehouse');
                const companyName = companySelect.options[companySelect.selectedIndex]?.text || '-';
                const warehouseName = warehouseSelect.options[warehouseSelect.selectedIndex]?.text || '-';
                const date = row.querySelector('.line-date').value || '-';
                const cost = n(row.querySelector('.line-cost').value);
                const vat = n(row.querySelector('.line-vat').value);
                const qty = Math.max(0, n(row.querySelector('.line-qty').value));
                const extra = n(row.querySelector('.line-extra').value);
                const after = Number(row.dataset.after || 0);
                const actual = Number(row.dataset.actual || 0);
                const total = Number(row.dataset.total || 0);
                const note = row.querySelector('.line-note').value || '';

                totalQty += qty;
                totalAmount += total;

                const tr = document.createElement('tr');
                tr.innerHTML = `
                    <td><span class="tag">${idx + 1}</span></td>
                    <td>${esc(productName || '-')}</td>
                    <td><b>${esc(sku || '-')}</b></td>
                    <td>${esc(companyName)}</td>
                    <td>${esc(warehouseName)}</td>
                    <td>${esc(date)}</td>
                    <td class="text-end">${cost.toLocaleString('vi-VN')}</td>
                    <td class="text-end">${vat}%</td>
                    <td class="text-end"><span class="money">${money(after)}</span></td>
                    <td class="text-end"><b>${qty.toLocaleString('vi-VN')}</b></td>
                    <td class="text-end">${extra.toLocaleString('vi-VN')}</td>
                    <td class="text-end"><span class="money">${money(actual)}</span></td>
                    <td class="text-end"><span class="money">${money(total)}</span></td>
                    <td>${esc(note || '-')}</td>
                `;
                historyBody.appendChild(tr);
            });
        }

        document.getElementById('sumLines').textContent = rows.length;
        document.getElementById('sumQty').textContent = totalQty.toLocaleString('vi-VN');
        document.getElementById('sumAmount').textContent = money(totalAmount);

        syncMain();
        calcSaleRows();
    }

    function bindLine(row){
        row.querySelectorAll('input,select').forEach(el => {
            el.addEventListener('input', updateHistory);
            el.addEventListener('change', updateHistory);
        });

        row.querySelector('.line-company').addEventListener('change', function(){
            row.querySelector('.line-warehouse').innerHTML = warehouseOptionsHtml(this.value || '', '');
            updateHistory();
        });

        row.querySelector('.btn-remove-line').addEventListener('click', function(){
            if (document.querySelectorAll('.line-card').length <= 1) {
                alert('Phải có ít nhất 1 dòng tồn.');
                return;
            }
            row.remove();
            refreshIndexes();
            updateHistory();
        });

        row.querySelector('.btn-toggle-line').addEventListener('click', function(){
            row.classList.toggle('collapsed');
            this.textContent = row.classList.contains('collapsed') ? 'Mở rộng' : 'Thu gọn';
        });
    }

    function addLine(line){
        const index = document.querySelectorAll('.line-card').length;
        linesContainer.insertAdjacentHTML('beforeend', rowHtml(index, line || null));
        bindLine(linesContainer.querySelectorAll('.line-card')[index]);
        updateHistory();
    }

    addLineBtn.addEventListener('click', () => addLine(null));
    document.getElementById('productName').addEventListener('input', updateHistory);
    document.querySelectorAll('.sale-before,.sale-vat').forEach(el => el.addEventListener('input', calcSaleRows));

    document.getElementById('productEditForm').addEventListener('submit', function(e){
        for (const row of document.querySelectorAll('.line-card')) {
            if (!row.querySelector('.line-sku').value.trim() || !row.querySelector('.line-company').value || !row.querySelector('.line-warehouse').value) {
                e.preventDefault();
                alert('Mỗi dòng SKU phải nhập đủ Mã SKU, Công ty và Kho.');
                return false;
            }
        }
        syncMain();
    });

    if (initialLines.length > 0) {
        initialLines.forEach(line => addLine(line));
    } else {
        addLine(null);
    }

    calcSaleRows();
})();
</script>
@endsection
