<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Support\EgoCompanyLock;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;

/**
 * Controller phiếu nhập hàng nhà cung cấp: tạo phiếu, nhập kho, theo dõi công nợ.
 */
class ProductGoodsReceiptController extends Controller
{
    /**
     * Danh sách phiếu nhập hàng có tìm kiếm, lọc trạng thái thanh toán và thống kê.
     */
    public function index(Request $request)
    {
        abort_unless(Schema::hasTable('product_goods_receipts'), 500, 'Chưa có bảng product_goods_receipts.');

        $q = trim((string) $request->get('q', ''));
        $paymentStatus = trim((string) $request->get('payment_status', ''));
        $warehouseId = $request->filled('warehouse_id') ? (int) $request->get('warehouse_id') : null;

        $query = DB::table('product_goods_receipts as r')
            ->leftJoin('companies as c', 'c.id', '=', 'r.company_id')
            ->leftJoin('crm_warehouses as w', 'w.id', '=', 'r.warehouse_id')
            ->select([
                'r.*',
                'c.name as company_name',
                'w.name as warehouse_name',
            ])
            ->where('r.company_id', EgoCompanyLock::id());

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

        if ($warehouseId) {
            $query->where('r.warehouse_id', $warehouseId);
        }

        $receipts = $query->orderByDesc('r.id')->paginate(20)->appends($request->query());

        $stats = [
            'total' => DB::table('product_goods_receipts')->where('company_id', EgoCompanyLock::id())->count(),
            'posted' => DB::table('product_goods_receipts')->where('company_id', EgoCompanyLock::id())->where('status', 'posted')->count(),
            'unpaid' => DB::table('product_goods_receipts')->where('company_id', EgoCompanyLock::id())->whereIn('payment_status', ['unpaid', 'partial'])->sum('debt_amount'),
            'paid' => DB::table('product_goods_receipts')->where('company_id', EgoCompanyLock::id())->where('payment_status', 'paid')->sum('total_amount'),
        ];

        return view('products.goods-receipts.index', array_merge(
            $this->formData(),
            compact('receipts', 'stats', 'q', 'paymentStatus', 'warehouseId')
        ));
    }

    /**
     * Chi tiết phiếu nhập hàng và toàn bộ sản phẩm trong phiếu.
     */
    public function show($id)
    {
        abort_unless(Schema::hasTable('product_goods_receipts'), 404);
        abort_unless(Schema::hasTable('product_goods_receipt_items'), 404);

        $receipt = DB::table('product_goods_receipts as r')
            ->leftJoin('crm_warehouses as w', 'w.id', '=', 'r.warehouse_id')
            ->select([
                'r.*',
                'w.name as warehouse_name',
            ])
            ->where('r.company_id', EgoCompanyLock::id())
            ->where('r.id', (int) $id)
            ->first();

        abort_unless($receipt, 404);

        $items = DB::table('product_goods_receipt_items as i')
            ->leftJoin('crm_product_catalog as p', 'p.id', '=', 'i.product_id')
            ->select([
                'i.*',
                'p.name as product_name',
                'p.sku as product_sku',
                'p.unit as product_unit',
            ])
            ->where('i.receipt_id', (int) $receipt->id)
            ->orderBy('i.id')
            ->get();

        return view('products.goods-receipts.show', compact('receipt', 'items'));
    }

    /**
     * Tạo nhanh nhà cung cấp từ popup của phiếu nhập hàng.
     */
    public function storeSupplier(Request $request)
    {
        abort_unless(Schema::hasTable('product_suppliers'), 500, 'Chưa có bảng danh mục nhà cung cấp.');

        $data = Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:191'],
            'phone' => ['nullable', 'string', 'max:80'],
            'tax_code' => ['nullable', 'string', 'max:80'],
            'address' => ['nullable', 'string', 'max:500'],
            'note' => ['nullable', 'string', 'max:1000'],
        ])->validate();

        $name = trim((string) $data['name']);
        $companyId = EgoCompanyLock::id();

        try {
            $existing = DB::table('product_suppliers')
                ->where('company_id', $companyId)
                ->where('name', $name)
                ->first();

            $payload = [
                'name' => $name,
                'phone' => trim((string) ($data['phone'] ?? '')) ?: null,
                'tax_code' => trim((string) ($data['tax_code'] ?? '')) ?: null,
                'address' => trim((string) ($data['address'] ?? '')) ?: null,
                'note' => trim((string) ($data['note'] ?? '')) ?: null,
                'is_active' => 1,
                'updated_at' => now(),
            ];

            if ($existing) {
                DB::table('product_suppliers')->where('id', $existing->id)->update($payload);
                $id = (int) $existing->id;
            } else {
                $id = (int) DB::table('product_suppliers')->insertGetId(array_merge($payload, [
                    'company_id' => $companyId,
                    'created_by' => auth()->id(),
                    'created_at' => now(),
                ]));
            }

            $supplier = DB::table('product_suppliers')->where('id', $id)->first();

            return response()->json([
                'ok' => true,
                'supplier' => $supplier,
                'message' => $existing ? 'Đã cập nhật nhà cung cấp.' : 'Đã tạo nhà cung cấp mới.',
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'ok' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Tạo phiếu nhập hàng NCC kèm dòng hàng; nhập kho luôn nếu action=post.
     */
    public function store(Request $request)
    {
        // Luôn khóa phiếu vào Công ty Quốc Tế EGO, không tin company_id từ trình duyệt.
        $request->merge(['company_id' => EgoCompanyLock::id()]);

        // Nhà cung cấp được chọn từ danh mục. Dữ liệu tên luôn lấy lại từ server.
        if ($request->filled('supplier_id')) {
            abort_unless(Schema::hasTable('product_suppliers'), 500, 'Chưa có bảng danh mục nhà cung cấp.');

            $supplier = DB::table('product_suppliers')
                ->where('company_id', EgoCompanyLock::id())
                ->where('is_active', 1)
                ->where('id', (int) $request->input('supplier_id'))
                ->first();

            if (! $supplier) {
                return back()->withInput()->with('error', 'Nhà cung cấp không tồn tại hoặc đã ngừng sử dụng.');
            }

            $request->merge([
                'supplier_name' => $supplier->name,
                'supplier_phone' => $request->filled('supplier_phone') ? $request->input('supplier_phone') : $supplier->phone,
                'supplier_tax_code' => $request->filled('supplier_tax_code') ? $request->input('supplier_tax_code') : $supplier->tax_code,
                'supplier_address' => $request->filled('supplier_address') ? $request->input('supplier_address') : $supplier->address,
            ]);
        }

        // Chuẩn hóa trường tiền trước khi validate để chống mất 3 số 0 khi người dùng
        // nhập theo định dạng VN: 30,000 / 30.000 / 1.250.000 / 34.000.000,56.
        $normalizedItems = (array) $request->input('items', []);

        foreach ($normalizedItems as $index => $row) {
            if (! is_array($row)) {
                continue;
            }

            if (array_key_exists('unit_price', $row)) {
                $normalizedItems[$index]['unit_price'] = $this->parseMoneyInput($row['unit_price']);
            }
        }

        $request->merge([
            'paid_amount' => $this->parseMoneyInput($request->input('paid_amount', 0)),
            'items' => $normalizedItems,
        ]);

        $validator = Validator::make($request->all(), [
            'company_id' => ['required', 'integer'],
            'warehouse_id' => ['required', 'integer'],
            'supplier_id' => ['required', 'integer'],
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
                    'company_id' => EgoCompanyLock::id(),
                    'warehouse_id' => (int) $request->input('warehouse_id'),
                    'supplier_id' => (int) $request->input('supplier_id'),
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

    /**
     * Ghi nhận nhập kho cho phiếu trong transaction.
     */
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

    /**
     * Xóa phiếu nháp (phiếu đã nhập kho thì không xóa).
     */
    public function destroy($id)
    {
        $row = DB::table('product_goods_receipts')->where('company_id', EgoCompanyLock::id())->where('id', (int) $id)->first();
        abort_unless($row, 404);

        if (($row->status ?? '') === 'posted') {
            return back()->with('error', 'Phiếu đã nhập kho nên không được xóa để tránh lệch tồn kho.');
        }

        DB::transaction(function () use ($id) {
            DB::table('product_goods_receipt_items')->where('receipt_id', (int) $id)->delete();
            DB::table('product_goods_receipts')->where('company_id', EgoCompanyLock::id())->where('id', (int) $id)->delete();
        });

        return back()->with('success', 'Đã xóa phiếu nháp.');
    }

    /**
     * Xử lý nhập kho: cộng tồn từng dòng hàng, tạo lot giá vốn, sự kiện kho và chuyển trạng thái posted.
     */
    private function postInsideTransaction(int $id): void
    {
        $receipt = DB::table('product_goods_receipts')->where('company_id', EgoCompanyLock::id())->where('id', $id)->lockForUpdate()->first();

        if (! $receipt) {
            throw new \RuntimeException('Không tìm thấy phiếu nhập hàng.');
        }

        if (($receipt->status ?? '') === 'posted') {
            throw new \RuntimeException('Phiếu này đã nhập kho trước đó.');
        }

        $items = DB::table('product_goods_receipt_items')->where('receipt_id', $id)->get();

        if ($items->isEmpty()) {
            throw new \RuntimeException('Phiếu chưa có hàng hóa.');
        }

        $eventId = $this->createInventoryEvent('goods_receipt', 'Nhập hàng NCC #'.$receipt->code);

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

        DB::table('product_goods_receipts')->where('company_id', EgoCompanyLock::id())->where('id', $id)->update([
            'status' => 'posted',
            'posted_by' => auth()->id(),
            'posted_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Dữ liệu dropdown cho form: công ty, kho, sản phẩm kèm tồn hiện tại.
     */
    private function formData(): array
    {
        $companies = collect();
        if (Schema::hasTable('companies')) {
            $q = DB::table('companies')->select('id', 'code', 'name')->where('id', EgoCompanyLock::id());

            if (Schema::hasColumn('companies', 'is_active')) {
                $q->where('is_active', 1);
            }

            $companies = $q->orderBy('id')->get();
        }

        $warehouses = collect();
        if (Schema::hasTable('crm_warehouses')) {
            $warehouses = DB::table('crm_warehouses')
                ->select('id', 'company_id', 'name', 'location')
                ->where('company_id', EgoCompanyLock::id())
                ->orderBy('company_id')
                ->orderBy('name')
                ->get();
        }

        $suppliers = collect();
        if (Schema::hasTable('product_suppliers')) {
            $suppliers = DB::table('product_suppliers')
                ->select('id', 'name', 'phone', 'tax_code', 'address')
                ->where('company_id', EgoCompanyLock::id())
                ->where('is_active', 1)
                ->orderBy('name')
                ->get();
        }

        $products = collect();
        if (Schema::hasTable('crm_product_catalog')) {
            $q = DB::table('crm_product_catalog as p')
                ->leftJoin('crm_product_stock as st', function ($join) {
                    $join->on('st.product_id', '=', 'p.id')
                        ->where('st.company_id', EgoCompanyLock::id());
                })
                ->selectRaw('p.id, p.company_id, p.sku, p.name, p.unit, COALESCE(SUM(st.qty),0) as stock_qty')
                ->groupBy('p.id', 'p.company_id', 'p.sku', 'p.name', 'p.unit')
                ->orderBy('p.name')
                ->limit(3000);

            if (Schema::hasColumn('crm_product_catalog', 'is_active')) {
                $q->where('p.is_active', 1);
            }

            if (Schema::hasColumn('crm_product_catalog', 'company_id')) {
                $q->where(function ($query) {
                    $query->where('p.company_id', EgoCompanyLock::id())->orWhereNull('p.company_id');
                });
            }

            $products = $q->get();
        }

        return compact('companies', 'warehouses', 'suppliers', 'products');
    }

    /**
     * Chuẩn hóa chuỗi tiền VN/US về số thập phân chuẩn để lưu DB.
     *
     * Ví dụ:
     * - 30,000 / 30.000     => 30000
     * - 1,250,000 / 1.250.000 => 1250000
     * - 34.000.000,56       => 34000000.56
     * - 34,000,000.56       => 34000000.56
     * - 34000000,56         => 34000000.56
     * - 34000000.56         => 34000000.56
     * - 150000000,000        => 150000000 (khong bi x1000)
     */
    private function parseMoneyInput($value): float
    {
        if (is_int($value) || is_float($value)) {
            return max(0, (float) $value);
        }

        $raw = trim((string) $value);
        $raw = preg_replace('/\s+/u', '', $raw) ?? '';
        $raw = preg_replace('/[^0-9,.\-]/u', '', $raw) ?? '';

        if ($raw === '') {
            return 0.0;
        }

        $negative = substr($raw, 0, 1) === '-';
        $raw = str_replace('-', '', $raw);

        $commaCount = substr_count($raw, ',');
        $dotCount = substr_count($raw, '.');
        $lastComma = strrpos($raw, ',');
        $lastDot = strrpos($raw, '.');

        if ($commaCount > 0 && $dotCount > 0) {
            if ($lastComma > $lastDot) {
                $raw = str_replace('.', '', $raw);
                $raw = preg_replace('/,/', '.', $raw, 1) ?? $raw;
            } else {
                $raw = str_replace(',', '', $raw);
            }
        } elseif ($commaCount > 0) {
            $parts = explode(',', $raw);

            if ($commaCount > 1 || (strlen($parts[1] ?? '') === 3 && strlen($parts[0] ?? '') <= 3)) {
                $raw = implode('', $parts);
            } else {
                $raw = ($parts[0] ?? '0').'.'.($parts[1] ?? '');
            }
        } elseif ($dotCount > 0) {
            $parts = explode('.', $raw);

            if ($dotCount > 1 || (strlen($parts[1] ?? '') === 3 && strlen($parts[0] ?? '') <= 3)) {
                $raw = implode('', $parts);
            }
        }

        $number = is_numeric($raw) ? (float) $raw : 0.0;

        if ($negative) {
            $number *= -1;
        }

        return max(0, $number);
    }

    /**
     * Kiểm tra kho và sản phẩm phải thuộc công ty đã chọn, sai thì ném exception.
     */
    private function guardCompanyWarehouseProducts(int $companyId, int $warehouseId, array $productIds): void
    {
        $companyId = EgoCompanyLock::id();

        if ($warehouseId <= 0) {
            throw new \RuntimeException('Vui lòng chọn kho nhập hàng.');
        }

        if (Schema::hasTable('crm_warehouses') && Schema::hasColumn('crm_warehouses', 'company_id')) {
            $warehouse = DB::table('crm_warehouses')->where('company_id', EgoCompanyLock::id())->where('id', $warehouseId)->first();

            if (! $warehouse) {
                throw new \RuntimeException('Kho nhập hàng không tồn tại.');
            }

            if (! empty($warehouse->company_id) && (int) $warehouse->company_id !== $companyId) {
                throw new \RuntimeException('Kho nhập hàng không thuộc công ty đã chọn.');
            }
        }

        $productIds = array_values(array_unique(array_filter(array_map('intval', $productIds))));

        if (! $productIds) {
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

    /**
     * Sinh mã phiếu nhập hàng dạng NH-Ymd-XXXX.
     */
    private function makeCode(): string
    {
        $prefix = 'NH-'.now()->format('Ymd').'-';
        $count = DB::table('product_goods_receipts')->where('company_id', EgoCompanyLock::id())->where('code', 'like', $prefix.'%')->count() + 1;

        return $prefix.str_pad((string) $count, 4, '0', STR_PAD_LEFT);
    }

    /**
     * Cộng/trừ tồn kho theo cột thực có, ghi stock movement và đồng bộ tổng lên catalog.
     */
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
            $total = (float) DB::table('crm_product_stock')->where('company_id', EgoCompanyLock::id())->where('product_id', $productId)->sum('qty');

            DB::table('crm_product_catalog')->where('id', $productId)->update([
                'quantity' => $total,
                'updated_at' => now(),
            ]);
        }
    }

    /**
     * Tạo lot tồn kho với giá vốn trước/sau VAT cho dòng hàng nhập.
     */
    private function createStockLot(object $receipt, object $item): void
    {
        if (! Schema::hasTable('crm_product_stock_lots')) {
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
            'lot_name' => 'Nhập hàng NCC '.$receipt->code,
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
            'note' => $receipt->supplier_name.' - HĐ: '.$receipt->invoice_no,
            'created_by' => auth()->id(),
            'created_at' => now(),
            'updated_at' => now(),
        ];

        DB::table('crm_product_stock_lots')->insert(array_intersect_key($data, array_flip($cols)));
    }

    /**
     * Tạo sự kiện kho, trả về id hoặc null nếu thiếu bảng.
     */
    private function createInventoryEvent(string $type, string $note): ?int
    {
        if (! Schema::hasTable('crm_inventory_events')) {
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

    /**
     * Gắn tham chiếu nguồn cho sự kiện kho.
     */
    private function createInventoryRef(?int $eventId, string $refType, int $refId): void
    {
        if (! $eventId || ! Schema::hasTable('crm_inventory_event_refs')) {
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
