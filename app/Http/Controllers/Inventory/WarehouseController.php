<?php

declare(strict_types=1);

namespace App\Http\Controllers\Inventory;

use App\Contracts\Services\WarehouseServiceInterface;
use App\Http\Controllers\Controller;
use App\Http\Requests\WarehouseRequest;
use App\Models\Core\Company;
use App\Models\Core\Warehouse;
use App\Models\Inventory\Catalog\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Controller quan ly kho hang (Warehouse) — chi dieu phoi, nghiep vu nam o service.
 */
class WarehouseController extends Controller
{
    public function __construct(protected WarehouseServiceInterface $service)
    {
        $this->authorizeResource(Warehouse::class, 'warehouse');
    }

    public function index()
    {
        $companies = Company::query()->orderBy('name')->get();
        $query = Warehouse::query()->with(['companies', 'manager'])->orderByDesc('id');

        $selectedCompanyIds = request()->input('company_ids', []);
        if (is_string($selectedCompanyIds)) {
            $selectedCompanyIds = array_filter(explode(',', $selectedCompanyIds));
        }

        if (! empty($selectedCompanyIds) && is_array($selectedCompanyIds)) {
            $query->whereHas('companies', function ($q) use ($selectedCompanyIds) {
                $q->whereIn('companies.id', $selectedCompanyIds);
            });
        }

        $warehouses = $query->paginate(20)->withQueryString();

        return view('warehouses.index', compact('warehouses', 'companies', 'selectedCompanyIds'));
    }

    public function create()
    {
        $companies = Company::query()->orderBy('name')->get();

        return view('warehouses.create', compact('companies'));
    }

    public function store(WarehouseRequest $request)
    {
        $data = $request->validated();
        $companyIds = $request->input('company_ids', []);
        if (is_string($companyIds)) {
            $companyIds = array_filter(explode(',', $companyIds));
        }

        $warehouse = $this->service->create($data);
        $warehouse->companies()->sync($companyIds);

        return redirect()->route('warehouses.index')->with('success', 'Tạo kho thành công');
    }

    public function edit(Warehouse $warehouse)
    {
        $companies = Company::query()->orderBy('name')->get();
        $warehouse->load('companies');

        return view('warehouses.edit', compact('warehouse', 'companies'));
    }

    public function update(WarehouseRequest $request, Warehouse $warehouse)
    {
        $data = $request->validated();
        $companyIds = $request->input('company_ids', []);
        if (is_string($companyIds)) {
            $companyIds = array_filter(explode(',', $companyIds));
        }

        $this->service->update($warehouse, $data);
        $warehouse->companies()->sync($companyIds);

        return redirect()->route('warehouses.index')->with('success', 'Cập nhật kho thành công');
    }

    public function destroy(Warehouse $warehouse)
    {
        $this->service->delete($warehouse);

        return redirect()->route('warehouses.index')->with('success', 'Xóa kho thành công');
    }

    /**
     * EGO V10: Danh sach ton cua mot kho phai lay catalog san pham lam bang goc.
     * San pham khong co dong crm_product_stock (hoac qty = 0) van phai hien.
     */
    public function inventory(Request $request, Warehouse $warehouse)
    {
        $keyword = trim((string) $request->get('search', ''));
        $product = new Product;
        $productTable = $product->getTable();

        $query = Product::query();

        if (Schema::hasColumn($productTable, 'is_active')) {
            $query->where(function ($q) {
                $q->where('is_active', 1)->orWhereNull('is_active');
            });
        }

        if ($keyword !== '') {
            $query->where(function ($q) use ($keyword, $productTable) {
                if (Schema::hasColumn($productTable, 'name')) {
                    $q->where($productTable.'.name', 'like', '%'.$keyword.'%');
                }

                if (Schema::hasColumn($productTable, 'sku')) {
                    $q->orWhere($productTable.'.sku', 'like', '%'.$keyword.'%');
                }
            });
        }

        $query->select($productTable.'.*')
            ->selectSub(function ($sub) use ($warehouse, $productTable) {
                $sub->from('crm_product_stock as s')
                    ->selectRaw('COALESCE(SUM(s.qty), 0)')
                    ->whereColumn('s.product_id', $productTable.'.id')
                    ->where('s.warehouse_id', $warehouse->id);
            }, 'warehouse_qty');

        if (Schema::hasColumn('crm_product_stock', 'updated_at')) {
            $query->selectSub(function ($sub) use ($warehouse, $productTable) {
                $sub->from('crm_product_stock as s')
                    ->selectRaw('MAX(s.updated_at)')
                    ->whereColumn('s.product_id', $productTable.'.id')
                    ->where('s.warehouse_id', $warehouse->id);
            }, 'last_stock_updated');
        } elseif (Schema::hasColumn('crm_product_stock', 'last_updated')) {
            $query->selectSub(function ($sub) use ($warehouse, $productTable) {
                $sub->from('crm_product_stock as s')
                    ->selectRaw('MAX(s.last_updated)')
                    ->whereColumn('s.product_id', $productTable.'.id')
                    ->where('s.warehouse_id', $warehouse->id);
            }, 'last_stock_updated');
        } else {
            $query->selectRaw('NULL as last_stock_updated');
        }

        $inventory = $query
            ->orderByRaw('CASE WHEN COALESCE(warehouse_qty, 0) > 0 THEN 0 ELSE 1 END')
            ->orderBy($productTable.'.name')
            ->paginate(100)
            ->withQueryString();

        return view('warehouses.inventory', compact('warehouse', 'inventory', 'keyword'));
    }
}
