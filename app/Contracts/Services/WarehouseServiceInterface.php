<?php

declare(strict_types=1);

namespace App\Contracts\Services;

/**
 * Hợp đồng service quản lý kho (Warehouse).
 */
interface WarehouseServiceInterface
{
    public function list();

    public function options();

    public function optionsWithCompanies();

    public function find(int $id);

    public function create(array $data);

    public function update($warehouse, array $data);

    public function delete($warehouse);

    public function getActiveWarehouses();

    public function assignManager($warehouseId, $managerId);

    public function getStockReport($warehouseId);

    public function getTotalStockValue($warehouseId);

    public function getTotalProducts($warehouseId);

    public function getOrders($warehouseId, array $filters = []);

    public function getStatistics($warehouseId);

    public function getStockMovements($warehouseId, array $filters = []);

    public function hasCapacity($warehouseId, $additionalQty = 0);
}
