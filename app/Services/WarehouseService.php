<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\Repositories\WarehouseRepositoryInterface;
use App\Contracts\Services\WarehouseServiceInterface;
use App\Models\Core\Warehouse;
use App\Models\CRM\Orders\Order;
use App\Models\Inventory\Stock\ProductStock;
use App\Models\Inventory\Stock\StockMovement;
use Illuminate\Support\Facades\DB;

/**
 * Service xử lý nghiệp vụ kho hàng (Warehouse).
 */
class WarehouseService implements WarehouseServiceInterface
{
    /**
     * Khởi tạo service với repository kho hàng.
     */
    public function __construct(protected WarehouseRepositoryInterface $repo) {}

    /**
     * Lấy toàn bộ danh sách kho.
     */
    public function list()
    {
        return $this->repo->all();
    }

    /**
     * GIỮ NGUYÊN HÀNH VI CŨ:
     * options() dùng cho dropdown/select ở nhiều nơi => repo->listAll()
     * (để tránh gây lỗi 500 các trang khác)
     */
    public function options()
    {
        return $this->repo->listAll();
    }

    /**
     * MỚI: Dành riêng cho màn tạo/sửa sản phẩm (stock-table)
     * cần Warehouse models có relation companies để lọc theo công ty.
     */
    public function optionsWithCompanies()
    {
        return Warehouse::query()
            ->with('companies')   // ✅ bắt buộc
            ->orderBy('name')
            ->get();
    }

    /**
     * Tìm kho theo ID.
     */
    public function find(int $id)
    {
        return $this->repo->find($id);
    }

    /**
     * Tạo kho mới.
     */
    public function create(array $data)
    {
        return $this->repo->create($data);
    }

    /**
     * Cập nhật thông tin kho.
     */
    public function update($warehouse, array $data)
    {
        return $this->repo->update($warehouse, $data);
    }

    /**
     * Xoá kho.
     */
    public function delete($warehouse)
    {
        return $this->repo->delete($warehouse);
    }

    /**
     * Lấy danh sách kho đang hoạt động cho dropdown select
     */
    public function getActiveWarehouses()
    {
        return Warehouse::query()
            ->select('id', 'name', 'location')
            ->orderBy('name')
            ->get()
            ->map(function ($warehouse) {
                return [
                    'id' => $warehouse->id,
                    'name' => $warehouse->name,
                    'location' => $warehouse->location,
                    'display' => $warehouse->name.($warehouse->location ? ' - '.$warehouse->location : ''),
                ];
            });
    }

    /**
     * Gán quản lý cho kho
     */
    public function assignManager($warehouseId, $managerId)
    {
        return DB::transaction(function () use ($warehouseId, $managerId) {
            $warehouse = $this->find($warehouseId);
            $warehouse->update(['manager_id' => $managerId]);

            return $warehouse;
        });
    }

    /**
     * Lấy báo cáo tồn kho theo kho
     */
    public function getStockReport($warehouseId)
    {
        return ProductStock::where('warehouse_id', $warehouseId)
            ->with('product')
            ->orderBy('qty', 'desc')
            ->get();
    }

    /**
     * Lấy tổng giá trị tồn kho theo kho
     */
    public function getTotalStockValue($warehouseId)
    {
        return ProductStock::where('warehouse_id', $warehouseId)
            ->join('crm_product_catalog', 'crm_product_stock.product_id', '=', 'crm_product_catalog.id')
            ->sum(DB::raw('crm_product_stock.qty * crm_product_catalog.price'));
    }

    /**
     * Lấy số lượng sản phẩm trong kho
     */
    public function getTotalProducts($warehouseId)
    {
        return ProductStock::where('warehouse_id', $warehouseId)
            ->where('qty', '>', 0)
            ->count();
    }

    /**
     * Lấy danh sách đơn hàng xuất từ kho
     */
    public function getOrders($warehouseId, array $filters = [])
    {
        $query = Order::where('warehouse_id', $warehouseId)
            ->with(['lead.customer', 'creator']);

        if (! empty($filters['from_date'])) {
            $query->whereDate('order_date', '>=', $filters['from_date']);
        }

        if (! empty($filters['to_date'])) {
            $query->whereDate('order_date', '<=', $filters['to_date']);
        }

        return $query->latest()->paginate($filters['per_page'] ?? 20);
    }

    /**
     * Thống kê kho
     */
    public function getStatistics($warehouseId)
    {
        $stockCount = ProductStock::where('warehouse_id', $warehouseId)
            ->where('qty', '>', 0)
            ->count();

        $totalQty = ProductStock::where('warehouse_id', $warehouseId)
            ->sum('qty');

        $totalValue = $this->getTotalStockValue($warehouseId);

        $ordersCount = Order::where('warehouse_id', $warehouseId)
            ->where('current_department', 'completed')
            ->count();

        return [
            'total_products' => $stockCount,
            'total_quantity' => $totalQty,
            'total_value' => $totalValue,
            'total_orders' => $ordersCount,
        ];
    }

    /**
     * Lấy lịch sử xuất nhập kho
     */
    public function getStockMovements($warehouseId, array $filters = [])
    {
        $query = StockMovement::where('warehouse_id', $warehouseId)
            ->with(['product', 'creator']);

        if (! empty($filters['product_id'])) {
            $query->where('product_id', $filters['product_id']);
        }

        if (! empty($filters['from_date'])) {
            $query->whereDate('created_at', '>=', $filters['from_date']);
        }

        if (! empty($filters['to_date'])) {
            $query->whereDate('created_at', '<=', $filters['to_date']);
        }

        return $query->latest()->paginate($filters['per_page'] ?? 50);
    }

    /**
     * Kiểm tra kho có đủ không gian không (nếu có giới hạn)
     */
    public function hasCapacity($warehouseId, $additionalQty = 0)
    {
        $warehouse = $this->find($warehouseId);

        if (! isset($warehouse->max_capacity)) {
            return true;
        }

        $currentQty = ProductStock::where('warehouse_id', $warehouseId)
            ->sum('qty');

        return ($currentQty + $additionalQty) <= $warehouse->max_capacity;
    }
}
