<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;

class SiteAssemblyController extends Controller
{
    public function index(Request $request)
    {
        abort_unless(Schema::hasTable('site_assemblies'), 500, 'Chưa có bảng site_assemblies. Hãy chạy migrate.');

        $q = trim((string) $request->get('q', ''));
        $status = trim((string) $request->get('status', ''));

        $query = DB::table('site_assemblies as a')
            ->leftJoin('companies as c', 'c.id', '=', 'a.company_id')
            ->leftJoin('sites as s', 's.id', '=', 'a.site_id')
            ->leftJoin('crm_product_catalog as p', 'p.id', '=', 'a.finished_product_id')
            ->leftJoin('crm_warehouses as mw', 'mw.id', '=', 'a.material_warehouse_id')
            ->leftJoin('crm_warehouses as fw', 'fw.id', '=', 'a.finished_warehouse_id')
            ->select([
                'a.*',
                'c.name as company_name',
                's.name as site_name',
                'p.name as finished_product_name',
                'p.sku as finished_product_sku',
                'mw.name as material_warehouse_name',
                'fw.name as finished_warehouse_name',
            ]);

        if ($q !== '') {
            $query->where(function ($x) use ($q) {
                $x->where('a.code', 'like', "%{$q}%")
                    ->orWhere('s.name', 'like', "%{$q}%")
                    ->orWhere('p.name', 'like', "%{$q}%")
                    ->orWhere('p.sku', 'like', "%{$q}%")
                    ->orWhere('a.note', 'like', "%{$q}%");
            });
        }

        if ($status !== '') {
            $query->where('a.status', $status);
        }

        $assemblies = $query->orderByDesc('a.id')->paginate(20)->appends($request->query());

        $stats = [
            'total' => DB::table('site_assemblies')->count(),
            'draft' => DB::table('site_assemblies')->where('status', 'draft')->count(),
            'completed' => DB::table('site_assemblies')->where('status', 'completed')->count(),
        ];

        return view('site-assemblies.index', array_merge($this->formData(), compact('assemblies', 'stats', 'q', 'status')));
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'company_id' => ['required', 'integer'],
            'site_id' => ['nullable', 'integer'],
            'material_warehouse_id' => ['required', 'integer'],
            'finished_warehouse_id' => ['required', 'integer'],
            'finished_product_id' => ['required', 'integer'],
            'finished_qty' => ['required', 'integer', 'min:1'],
            'produced_at' => ['nullable', 'date'],
            'note' => ['nullable', 'string'],
            'materials' => ['required', 'array'],
            'materials.*.product_id' => ['nullable', 'integer'],
            'materials.*.qty' => ['nullable', 'integer', 'min:1'],
            'materials.*.note' => ['nullable', 'string'],
        ]);

        $validator->validate();

        $materials = collect((array) $request->input('materials', []))
            ->map(function ($row) {
                return [
                    'product_id' => (int) ($row['product_id'] ?? 0),
                    'qty' => (int) ($row['qty'] ?? 0),
                    'note' => trim((string) ($row['note'] ?? '')),
                ];
            })
            ->filter(fn ($row) => $row['product_id'] > 0 && $row['qty'] > 0)
            ->values();

        if ($materials->isEmpty()) {
            return back()->withInput()->with('error', 'Vui lòng thêm ít nhất 1 vật tư lắp ráp.');
        }

        try {
            $this->guardCompanyWarehousesProducts(
                (int) $request->input('company_id'),
                [
                    (int) $request->input('material_warehouse_id'),
                    (int) $request->input('finished_warehouse_id'),
                ],
                array_merge(
                    $materials->pluck('product_id')->all(),
                    [(int) $request->input('finished_product_id')]
                )
            );
        } catch (\Throwable $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        $action = (string) $request->input('action', 'draft');

        try {
            $id = DB::transaction(function () use ($request, $materials, $action) {
                $now = now();

                $id = DB::table('site_assemblies')->insertGetId([
                    'code' => $this->makeCode(),
                    'company_id' => (int) $request->input('company_id'),
                    'site_id' => $request->filled('site_id') ? (int) $request->input('site_id') : null,
                    'material_warehouse_id' => (int) $request->input('material_warehouse_id'),
                    'finished_warehouse_id' => (int) $request->input('finished_warehouse_id'),
                    'finished_product_id' => (int) $request->input('finished_product_id'),
                    'finished_qty' => (int) $request->input('finished_qty'),
                    'produced_at' => $request->input('produced_at') ?: now()->toDateString(),
                    'status' => 'draft',
                    'note' => $request->input('note'),
                    'created_by' => auth()->id(),
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

                foreach ($materials as $row) {
                    DB::table('site_assembly_materials')->insert([
                        'assembly_id' => $id,
                        'product_id' => $row['product_id'],
                        'warehouse_id' => (int) $request->input('material_warehouse_id'),
                        'qty' => $row['qty'],
                        'note' => $row['note'] ?: null,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }

                if ($action === 'complete') {
                    $this->completeInsideTransaction($id);
                }

                return $id;
            });
        } catch (\Throwable $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route('site-assemblies.index')->with('success', 'Đã tạo phiếu lắp ráp #' . $id . ($action === 'complete' ? ' và cập nhật kho.' : '.'));
    }

    public function complete($id)
    {
        try {
            DB::transaction(function () use ($id) {
                $this->completeInsideTransaction((int) $id);
            });
        } catch (\Throwable $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Đã hoàn thành lắp ráp và cập nhật kho.');
    }

    public function destroy($id)
    {
        $assembly = DB::table('site_assemblies')->where('id', (int) $id)->first();
        abort_unless($assembly, 404);

        if (($assembly->status ?? '') === 'completed') {
            return back()->with('error', 'Phiếu đã hoàn thành và đã cập nhật kho nên không xóa được.');
        }

        DB::transaction(function () use ($id) {
            DB::table('site_assembly_materials')->where('assembly_id', (int) $id)->delete();
            DB::table('site_assemblies')->where('id', (int) $id)->delete();
        });

        return back()->with('success', 'Đã xóa phiếu lắp ráp nháp.');
    }

    private function completeInsideTransaction(int $id): void
    {
        $assembly = DB::table('site_assemblies')->where('id', $id)->lockForUpdate()->first();

        if (!$assembly) {
            throw new \RuntimeException('Không tìm thấy phiếu lắp ráp.');
        }

        if (($assembly->status ?? '') === 'completed') {
            throw new \RuntimeException('Phiếu này đã hoàn thành trước đó.');
        }

        $items = DB::table('site_assembly_materials')->where('assembly_id', $id)->get();

        if ($items->isEmpty()) {
            throw new \RuntimeException('Phiếu chưa có vật tư để xuất kho.');
        }

        foreach ($items as $item) {
            $currentQty = $this->stockQty((int) $item->product_id, (int) $item->warehouse_id, (int) $assembly->company_id);

            if ($currentQty < (int) $item->qty) {
                $product = DB::table('crm_product_catalog')->where('id', (int) $item->product_id)->first();
                $name = $product ? (($product->sku ? $product->sku . ' - ' : '') . $product->name) : ('SP #' . $item->product_id);

                throw new \RuntimeException('Không đủ tồn kho vật tư: ' . $name . '. Tồn hiện tại ' . $currentQty . ', cần ' . (int) $item->qty . '.');
            }
        }

        $eventId = $this->createInventoryEvent('assembly', 'Lắp ráp / sản xuất #' . $assembly->code);

        foreach ($items as $item) {
            $this->adjustStock(
                (int) $item->product_id,
                (int) $item->warehouse_id,
                (int) $assembly->company_id,
                -1 * (int) $item->qty,
                'assembly_material_issue',
                $id
            );
        }

        $this->adjustStock(
            (int) $assembly->finished_product_id,
            (int) $assembly->finished_warehouse_id,
            (int) $assembly->company_id,
            (int) $assembly->finished_qty,
            'assembly_finished_receive',
            $id
        );

        $this->createFinishedLot($assembly);
        $this->createInventoryRef($eventId, 'site_assembly', $id);

        DB::table('site_assemblies')->where('id', $id)->update([
            'status' => 'completed',
            'completed_by' => auth()->id(),
            'completed_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function formData(): array
    {
        $companies = Schema::hasTable('companies')
            ? DB::table('companies')->select('id', 'code', 'name')->where('is_active', 1)->orderBy('id')->get()
            : collect();

        $warehouses = Schema::hasTable('crm_warehouses')
            ? DB::table('crm_warehouses')->select('id', 'company_id', 'name', 'location')->orderBy('company_id')->orderBy('name')->get()
            : collect();

        $products = Schema::hasTable('crm_product_catalog')
            ? DB::table('crm_product_catalog as p')
                ->leftJoin('crm_product_stock as st', 'st.product_id', '=', 'p.id')
                ->selectRaw('p.id, p.company_id, p.sku, p.name, p.unit, p.is_active, COALESCE(SUM(st.qty),0) as stock_qty')
                ->where('p.is_active', 1)
                ->groupBy('p.id', 'p.company_id', 'p.sku', 'p.name', 'p.unit', 'p.is_active')
                ->orderBy('p.name')
                ->limit(2000)
                ->get()
            : collect();

        $sites = Schema::hasTable('sites')
            ? DB::table('sites')->select('id', 'company_id', 'name', 'contact_name', 'contact_phone', 'address')->orderByDesc('id')->limit(500)->get()
            : collect();

        return compact('companies', 'warehouses', 'products', 'sites');
    }


    private function guardCompanyWarehousesProducts(int $companyId, array $warehouseIds, array $productIds): void
    {
        if ($companyId <= 0) {
            throw new \RuntimeException('Vui lòng chọn công ty.');
        }

        $warehouseIds = array_values(array_unique(array_filter(array_map('intval', $warehouseIds))));

        if (!$warehouseIds) {
            throw new \RuntimeException('Vui lòng chọn kho.');
        }

        if (Schema::hasTable('crm_warehouses') && Schema::hasColumn('crm_warehouses', 'company_id')) {
            $badWarehouse = DB::table('crm_warehouses')
                ->whereIn('id', $warehouseIds)
                ->whereNotNull('company_id')
                ->where('company_id', '<>', $companyId)
                ->exists();

            if ($badWarehouse) {
                throw new \RuntimeException('Có kho không thuộc công ty đã chọn. Vui lòng chọn lại kho.');
            }
        }

        $productIds = array_values(array_unique(array_filter(array_map('intval', $productIds))));

        if (!$productIds) {
            throw new \RuntimeException('Vui lòng chọn sản phẩm.');
        }

        if (Schema::hasTable('crm_product_catalog') && Schema::hasColumn('crm_product_catalog', 'company_id')) {
            $badProduct = DB::table('crm_product_catalog')
                ->whereIn('id', $productIds)
                ->whereNotNull('company_id')
                ->where('company_id', '<>', $companyId)
                ->exists();

            if ($badProduct) {
                throw new \RuntimeException('Có sản phẩm không thuộc công ty đã chọn. Vui lòng chọn lại sản phẩm.');
            }
        }
    }

    private function makeCode(): string
    {
        $prefix = 'LR-' . now()->format('Ymd') . '-';
        $count = DB::table('site_assemblies')->where('code', 'like', $prefix . '%')->count() + 1;

        return $prefix . str_pad((string) $count, 4, '0', STR_PAD_LEFT);
    }

    private function stockQty(int $productId, int $warehouseId, int $companyId): int
    {
        $row = DB::table('crm_product_stock')
            ->where('product_id', $productId)
            ->where('warehouse_id', $warehouseId)
            ->where('company_id', $companyId)
            ->lockForUpdate()
            ->first();

        return $row ? (int) $row->qty : 0;
    }

    private function adjustStock(int $productId, int $warehouseId, int $companyId, int $changeQty, string $reason, int $referenceId): void
    {
        $row = DB::table('crm_product_stock')
            ->where('product_id', $productId)
            ->where('warehouse_id', $warehouseId)
            ->where('company_id', $companyId)
            ->lockForUpdate()
            ->first();

        $before = $row ? (int) $row->qty : 0;
        $after = $before + $changeQty;

        if ($after < 0) {
            throw new \RuntimeException('Tồn kho không được âm.');
        }

        if ($row) {
            DB::table('crm_product_stock')->where('id', $row->id)->update([
                'qty' => $after,
                'last_updated' => now(),
            ]);
        } else {
            DB::table('crm_product_stock')->insert([
                'product_id' => $productId,
                'warehouse_id' => $warehouseId,
                'company_id' => $companyId,
                'qty' => $after,
                'serials_json' => null,
                'last_updated' => now(),
            ]);
        }

        if (Schema::hasTable('crm_stock_movements')) {
            DB::table('crm_stock_movements')->insert([
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
            ]);
        }

        if (Schema::hasTable('crm_product_catalog') && Schema::hasColumn('crm_product_catalog', 'quantity')) {
            $total = (int) DB::table('crm_product_stock')->where('product_id', $productId)->sum('qty');

            DB::table('crm_product_catalog')->where('id', $productId)->update([
                'quantity' => $total,
                'updated_at' => now(),
            ]);
        }
    }

    private function createFinishedLot(object $assembly): void
    {
        if (!Schema::hasTable('crm_product_stock_lots')) {
            return;
        }

        DB::table('crm_product_stock_lots')->insert([
            'product_id' => (int) $assembly->finished_product_id,
            'company_id' => (int) $assembly->company_id,
            'warehouse_id' => (int) $assembly->finished_warehouse_id,
            'lot_code' => $assembly->code,
            'lot_name' => 'Thành phẩm lắp ráp ' . $assembly->code,
            'received_at' => now(),
            'qty_in' => (int) $assembly->finished_qty,
            'qty_remaining' => (int) $assembly->finished_qty,
            'cost_before_vat' => 0,
            'cost_vat_percent' => 0,
            'cost_after_vat' => 0,
            'extra_cost' => 0,
            'actual_cost_after_vat' => 0,
            'source_type' => 'site_assembly',
            'source_id' => (int) $assembly->id,
            'note' => $assembly->note,
            'created_by' => auth()->id(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function createInventoryEvent(string $type, string $note): ?int
    {
        if (!Schema::hasTable('crm_inventory_events')) {
            return null;
        }

        return (int) DB::table('crm_inventory_events')->insertGetId([
            'event_type' => $type,
            'occurred_at' => now(),
            'created_by' => auth()->id(),
            'note' => $note,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function createInventoryRef(?int $eventId, string $refType, int $refId): void
    {
        if (!$eventId || !Schema::hasTable('crm_inventory_event_refs')) {
            return;
        }

        DB::table('crm_inventory_event_refs')->insert([
            'event_id' => $eventId,
            'ref_type' => $refType,
            'ref_id' => $refId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
