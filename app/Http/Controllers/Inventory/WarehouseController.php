<?php

declare(strict_types=1);

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Contracts\Services\WarehouseServiceInterface;
use App\Http\Requests\WarehouseRequest;
use App\Models\Core\Company;
use App\Models\Core\Warehouse;
use App\Models\Inventory\Stock\ProductStock as CrmProductStock;

/**
 * Controller quản lý kho hàng (Warehouse) — chỉ điều phối, nghiệp vụ nằm ở service.
 */
class WarehouseController extends Controller
{
    public function __construct(protected WarehouseServiceInterface $service)
    {
        $this->authorizeResource(Warehouse::class, 'warehouse');
    }

    public function index()
    {
        // ✅ filter theo nhiều công ty (optional)
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

        // lấy company_ids từ form (multi-select)
        $companyIds = $request->input('company_ids', []);
        if (is_string($companyIds)) {
            $companyIds = array_filter(explode(',', $companyIds));
        }

        $warehouse = $this->service->create($data);

        // ✅ sync pivot many-to-many
        $warehouse->companies()->sync($companyIds);

        return redirect()
            ->route('warehouses.index')
            ->with('success', 'Tạo kho thành công');
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

        // ✅ sync pivot many-to-many
        $warehouse->companies()->sync($companyIds);

        return redirect()
            ->route('warehouses.index')
            ->with('success', 'Cập nhật kho thành công');
    }

    public function destroy(Warehouse $warehouse)
    {
        $this->service->delete($warehouse);

        return redirect()
            ->route('warehouses.index')
            ->with('success', 'Xóa kho thành công');
    }

    public function inventory(Warehouse $warehouse)
    {
        $inventory = CrmProductStock::query()
            ->with('product')
            ->where('warehouse_id', $warehouse->id)
            ->get();

        return view('warehouses.inventory', compact('warehouse', 'inventory'));
    }
}
