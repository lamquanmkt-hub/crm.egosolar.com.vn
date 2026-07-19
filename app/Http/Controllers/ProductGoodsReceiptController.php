<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;

class ProductGoodsReceiptController extends Controller
{
    public function index(Request $request)
    {
        abort_unless(Schema::hasTable('product_goods_receipts'), 500, 'Chưa có bảng product_goods_receipts.');

        $q = trim((string) $request->get('q', ''));
        $paymentStatus = trim((string) $request->get('payment_status', ''));

        $query = DB::table('product_goods_receipts as r')
            ->leftJoin('companies as c', 'c.id', '=', 'r.company_id')
            ->leftJoin('crm_warehouses as w', 'w.id', '=', 'r.warehouse_id')
            ->select([
                'r.*',
                'c.name as company_name',
                'w.name as warehouse_name',
            ]);

        if ($q !== '') {
            $query->where(function ($x) use ($q) {
                $x->where('r.code', 'like', "%{$q}%")
                    ->orWhere('r.supplier_name', 'like', "%{$q}%")
                    ->orWhere('r.supplier_phone', 'like', "%{$q}%")
                    ->orWhere('r.invoice_no', 'like', "%{$q}%")
                    ->orWhere('r.note', 'like', "%{$q}%");
            });
        }

        if ($paymentStatus !== '') {
            $query->where('r.payment_status', $paymentStatus);
        }

        $receipts = $query->orderByDesc('r.id')->paginate(20)->appends($request->query());

        $stats = [
            'total' => DB::table('product_goods_receipts')->count(),
            'posted' => DB::table('product_goods_receipts')->where('status', 'posted')->count(),
            'unpaid' => DB::table('product_goods_receipts')->whereIn('payment_status', ['unpaid', 'partial'])->sum('debt_amount'),
            'paid' => DB::table('product_goods_receipts')->where('payment_status', 'paid')->sum('total_amount'),
        ];

        return view('products.goods-receipts.index', array_merge(
            $this->formData(),
            compact('receipts', 'stats', 'q', 'paymentStatus')
        ));
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'company_id' => ['required', 'integer'],
            'warehouse_id' => ['required', 'integer'],
            'supplier_name' => ['required', 'string', 'max:255'],
            'supplier_phone' => ['nullable', 'string', 'max:80'],
            'supplier_tax_code' => ['nullable', 'string', 'max:80'],
            'supplier_address' => ['nullable', 'string', 'max:500'],
            'invoice_no' => ['nullable', 'string', 'max:120'],
            'invoice_date' => ['nullable', 'date'],
            'payment_status' => ['required', 'in:unpaid,partial,paid'],
            'payment_due_date' => ['nullable', 'date'],
            'paid_amount' => ['nullable', 'numeric', 'min:0'],
            'note' => ['nullable', 'string'],
            'items' => ['required', 'array'],
            'items.*.product_id' => ['nullable', 'integer'],
            'items.*.qty' => ['nullable', 'numeric', 'min:0.0001'],
            'items.*.unit_price' => ['nullable', 'numeric', 'min:0'],
            'items.*.vat_percent' => ['nullable', 'numeric', 'min:0'],
            'items.*.note' => ['nullable', 'string'],
        ]);

        $validator->validate();

        $items = collect((array) $request->input('items', []))
            ->map(function ($row) {
                $qty = (float) ($row['qty'] ?? 0);
                $price = (float) ($row['unit_price'] ?? 0);
                $vat = (float) ($row['vat_percent'] ?? 0);
                $amount = $qty * $price;
                $vatAmount = $amount * $vat / 100;

                return [
                    'product_id' => (int) ($row['product_id'] ?? 0),
                    'qty' => $qty,
                    'unit_price' => $price,
                    'vat_percent' => $vat,
                    'amount' => $amount + $vatAmount,
                    'note' => trim((string) ($row['note'] ?? '')),
                ];
            })
            ->filter(fn ($row) => $row['product_id'] > 0 && $row['qty'] > 0)
            ->values();

        if ($items->isEmpty()) {
            return back()->withInput()->with('error', 'Vui lòng thêm ít nhất 1 hàng hóa nhập kho.');
        }

        try {
            $this->guardCompanyWarehouseProducts(
                (int) $request->input('company_id'),
                (int) $request->input('warehouse_id'),
                $items->pluck('product_id')->all()
            );
        } catch (\Throwable $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        $action = (string) $request->input('action', 'draft');

        try {
            DB::transaction(function () use ($request, $items, $action) {
                $now = now();
                $totalAmount = (float) $items->sum('amount');
                $paidAmount = (float) $request->input('paid_amount', 0);

                if ($request->input('payment_status') === 'paid') {
                    $paidAmount = $totalAmount;
                }

                $id = DB::table('product_goods_receipts')->insertGetId([
                    'code' => $this->makeCode(),
                    'company_id' => (int) $request->input('company_id'),
                    'warehouse_id' => (int) $request->input('warehouse_id'),
                    'supplier_name' => $request->input('supplier_name'),
                    'supplier_phone' => $request->input('supplier_phone'),
                    'supplier_tax_code' => $request->input('supplier_tax_code'),
                    'supplier_address' => $request->input('supplier_address'),
                    'invoice_no' => $request->input('invoice_no'),
                    'invoice_date' => $request->input('invoice_date') ?: now()->toDateString(),
                    'payment_status' => $request->input('payment_status'),
                    'payment_due_date' => $request->input('payment_due_date'),
                    'total_amount' => $totalAmount,
                    'paid_amount' => $paidAmount,
                    'debt_amount' => max(0, $totalAmount - $paidAmount),
                    'status' => 'draft',
                    'note' => $request->input('note'),
                    'created_by' => auth()->id(),
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

                foreach ($items as $row) {
                    DB::table('product_goods_receipt_items')->insert([
                        'receipt_id' => $id,
                        'product_id' => $row['product_id'],
                        'qty' => $row['qty'],
                        'unit_price' => $row['unit_price'],
                        'vat_percent' => $row['vat_percent'],
                        'amount' => $row['amount'],
                        'note' => $row['note'] ?: null,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }

                if ($action === 'post') {
                    $this->postInsideTransaction($id);
                }
            });
        } catch (\Throwable $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route('product-goods-receipts.index')
            ->with('success', $action === 'post' ? 'Đã nhập hàng và cập nhật kho.' : 'Đã lưu phiếu nháp.');
    }

    public function post($id)
    {
        try {
            DB::transaction(function () use ($id) {
                $this->postInsideTransaction((int) $id);
            });
        } catch (\Throwable $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Đã cập nhật kho cho phiếu nhập hàng.');
    }

    public function destroy($id)
    {
        $row = DB::table('product_goods_receipts')->where('id', (int) $id)->first();
        abort_unless($row, 404);

        if (($row->status ?? '') === 'posted') {
            return back()->with('error', 'Phiếu đã nhập kho nên không được xóa để tránh lệch tồn kho.');
        }

        DB::transaction(function () use ($id) {
            DB::table('product_goods_receipt_items')->where('receipt_id', (int) $id)->delete();
            DB::table('product_goods_receipts')->where('id', (int) $id)->delete();
        });

        return back()->with('success', 'Đã xóa phiếu nháp.');
    }

    private function postInsideTransaction(int $id): void
    {
        $receipt = DB::table('product_goods_receipts')->where('id', $id)->lockForUpdate()->first();

        if (!$receipt) {
            throw new \RuntimeException('Không tìm thấy phiếu nhập hàng.');
        }

        if (($receipt->status ?? '') === 'posted') {
            throw new \RuntimeException('Phiếu này đã nhập kho trước đó.');
        }

        $items = DB::table('product_goods_receipt_items')->where('receipt_id', $id)->get();

        if ($items->isEmpty()) {
            throw new \RuntimeException('Phiếu chưa có hàng hóa.');
        }

        $eventId = $this->createInventoryEvent('goods_receipt', 'Nhập hàng NCC #' . $receipt->code);

        foreach ($items as $item) {
            $this->adjustStock(
                (int) $item->product_id,
                (int) $receipt->warehouse_id,
                (int) $receipt->company_id,
                (float) $item->qty,
                'goods_receipt',
                $id
            );

            $this->createStockLot($receipt, $item);
        }

        $this->createInventoryRef($eventId, 'product_goods_receipt', $id);

        DB::table('product_goods_receipts')->where('id', $id)->update([
            'status' => 'posted',
            'posted_by' => auth()->id(),
            'posted_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function formData(): array
    {
        $companies = collect();
        if (Schema::hasTable('companies')) {
            $q = DB::table('companies')->select('id', 'code', 'name');

            if (Schema::hasColumn('companies', 'is_active')) {
                $q->where('is_active', 1);
            }

            $companies = $q->orderBy('id')->get();
        }

        $warehouses = collect();
        if (Schema::hasTable('crm_warehouses')) {
            $warehouses = DB::table('crm_warehouses')
                ->select('id', 'company_id', 'name', 'location')
                ->orderBy('company_id')
                ->orderBy('name')
                ->get();
        }

        $products = collect();
        if (Schema::hasTable('crm_product_catalog')) {
            $q = DB::table('crm_product_catalog as p')
                ->leftJoin('crm_product_stock as st', 'st.product_id', '=', 'p.id')
                ->selectRaw('p.id, p.company_id, p.sku, p.name, p.unit, COALESCE(SUM(st.qty),0) as stock_qty')
                ->groupBy('p.id', 'p.company_id', 'p.sku', 'p.name', 'p.unit')
                ->orderBy('p.name')
                ->limit(3000);

            if (Schema::hasColumn('crm_product_catalog', 'is_active')) {
                $q->where('p.is_active', 1);
            }

            $products = $q->get();
        }

        return compact('companies', 'warehouses', 'products');
    }


    private function guardCompanyWarehouseProducts(int $companyId, int $warehouseId, array $productIds): void
    {
        if ($companyId <= 0) {
            throw new \RuntimeException('Vui lòng chọn công ty.');
        }

        if ($warehouseId <= 0) {
            throw new \RuntimeException('Vui lòng chọn kho nhập hàng.');
        }

        if (Schema::hasTable('crm_warehouses') && Schema::hasColumn('crm_warehouses', 'company_id')) {
            $warehouse = DB::table('crm_warehouses')->where('id', $warehouseId)->first();

            if (!$warehouse) {
                throw new \RuntimeException('Kho nhập hàng không tồn tại.');
            }

            if (!empty($warehouse->company_id) && (int) $warehouse->company_id !== $companyId) {
                throw new \RuntimeException('Kho nhập hàng không thuộc công ty đã chọn.');
            }
        }

        $productIds = array_values(array_unique(array_filter(array_map('intval', $productIds))));

        if (!$productIds) {
            throw new \RuntimeException('Vui lòng chọn hàng hóa nhập kho.');
        }

        if (Schema::hasTable('crm_product_catalog') && Schema::hasColumn('crm_product_catalog', 'company_id')) {
            $bad = DB::table('crm_product_catalog')
                ->whereIn('id', $productIds)
                ->whereNotNull('company_id')
                ->where('company_id', '<>', $companyId)
                ->exists();

            if ($bad) {
                throw new \RuntimeException('Có sản phẩm không thuộc công ty đã chọn. Vui lòng chọn lại sản phẩm.');
            }
        }
    }

    private function makeCode(): string
    {
        $prefix = 'NH-' . now()->format('Ymd') . '-';
        $count = DB::table('product_goods_receipts')->where('code', 'like', $prefix . '%')->count() + 1;

        return $prefix . str_pad((string) $count, 4, '0', STR_PAD_LEFT);
    }

    private function adjustStock(int $productId, int $warehouseId, int $companyId, float $changeQty, string $reason, int $referenceId): void
    {
        $stockTable = 'crm_product_stock';

        $query = DB::table($stockTable)
            ->where('product_id', $productId)
            ->where('warehouse_id', $warehouseId);

        if (Schema::hasColumn($stockTable, 'company_id')) {
            $query->where('company_id', $companyId);
        }

        $row = $query->lockForUpdate()->first();

        $before = $row ? (float) $row->qty : 0;
        $after = $before + $changeQty;

        if ($row) {
            $data = ['qty' => $after];

            if (Schema::hasColumn($stockTable, 'last_updated')) {
                $data['last_updated'] = now();
            }

            if (Schema::hasColumn($stockTable, 'updated_at')) {
                $data['updated_at'] = now();
            }

            DB::table($stockTable)->where('id', $row->id)->update($data);
        } else {
            $data = [
                'product_id' => $productId,
                'warehouse_id' => $warehouseId,
                'qty' => $after,
            ];

            if (Schema::hasColumn($stockTable, 'company_id')) {
                $data['company_id'] = $companyId;
            }

            if (Schema::hasColumn($stockTable, 'serials_json')) {
                $data['serials_json'] = null;
            }

            if (Schema::hasColumn($stockTable, 'last_updated')) {
                $data['last_updated'] = now();
            }

            if (Schema::hasColumn($stockTable, 'created_at')) {
                $data['created_at'] = now();
            }

            if (Schema::hasColumn($stockTable, 'updated_at')) {
                $data['updated_at'] = now();
            }

            DB::table($stockTable)->insert($data);
        }

        if (Schema::hasTable('crm_stock_movements')) {
            $cols = Schema::getColumnListing('crm_stock_movements');

            $data = [
                'product_id' => $productId,
                'warehouse_id' => $warehouseId,
                'change_qty' => $changeQty,
                'qty_before' => $before,
                'qty_after' => $after,
                'reason' => $reason,
                'reference_id' => $referenceId,
                'created_by' => auth()->id(),
                'created_at' => now(),
                'updated_at' => now(),
            ];

            if (in_array('company_id', $cols, true)) {
                $data['company_id'] = $companyId;
            }

            DB::table('crm_stock_movements')->insert(array_intersect_key($data, array_flip($cols)));
        }

        if (Schema::hasTable('crm_product_catalog') && Schema::hasColumn('crm_product_catalog', 'quantity')) {
            $total = (float) DB::table('crm_product_stock')->where('product_id', $productId)->sum('qty');

            DB::table('crm_product_catalog')->where('id', $productId)->update([
                'quantity' => $total,
                'updated_at' => now(),
            ]);
        }
    }

    private function createStockLot(object $receipt, object $item): void
    {
        if (!Schema::hasTable('crm_product_stock_lots')) {
            return;
        }

        $cols = Schema::getColumnListing('crm_product_stock_lots');
        $unitCost = (float) $item->unit_price;
        $qty = (float) $item->qty;

        $data = [
            'product_id' => (int) $item->product_id,
            'company_id' => (int) $receipt->company_id,
            'warehouse_id' => (int) $receipt->warehouse_id,
            'lot_code' => $receipt->code,
            'lot_name' => 'Nhập hàng NCC ' . $receipt->code,
            'received_at' => now(),
            'qty_in' => $qty,
            'qty_remaining' => $qty,
            'cost_before_vat' => $unitCost,
            'cost_vat_percent' => (float) $item->vat_percent,
            'cost_after_vat' => $qty > 0 ? ((float) $item->amount / $qty) : $unitCost,
            'extra_cost' => 0,
            'actual_cost_after_vat' => $qty > 0 ? ((float) $item->amount / $qty) : $unitCost,
            'source_type' => 'product_goods_receipt',
            'source_id' => (int) $receipt->id,
            'note' => $receipt->supplier_name . ' - HĐ: ' . $receipt->invoice_no,
            'created_by' => auth()->id(),
            'created_at' => now(),
            'updated_at' => now(),
        ];

        DB::table('crm_product_stock_lots')->insert(array_intersect_key($data, array_flip($cols)));
    }

    private function createInventoryEvent(string $type, string $note): ?int
    {
        if (!Schema::hasTable('crm_inventory_events')) {
            return null;
        }

        $cols = Schema::getColumnListing('crm_inventory_events');

        $data = [
            'event_type' => $type,
            'occurred_at' => now(),
            'created_by' => auth()->id(),
            'note' => $note,
            'created_at' => now(),
            'updated_at' => now(),
        ];

        return (int) DB::table('crm_inventory_events')->insertGetId(array_intersect_key($data, array_flip($cols)));
    }

    private function createInventoryRef(?int $eventId, string $refType, int $refId): void
    {
        if (!$eventId || !Schema::hasTable('crm_inventory_event_refs')) {
            return;
        }

        $cols = Schema::getColumnListing('crm_inventory_event_refs');

        $data = [
            'event_id' => $eventId,
            'ref_type' => $refType,
            'ref_id' => $refId,
            'created_at' => now(),
            'updated_at' => now(),
        ];

        DB::table('crm_inventory_event_refs')->insert(array_intersect_key($data, array_flip($cols)));
    }
}
