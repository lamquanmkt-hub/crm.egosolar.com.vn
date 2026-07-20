<?php

namespace App\Repositories\Eloquent;

use App\Models\Core\Warehouse;
use App\Repositories\Interfaces\WarehouseRepositoryInterface;

/**
 * Repository Eloquent thao tác dữ liệu kho.
 */
class WarehouseRepository implements WarehouseRepositoryInterface
{
    /**
     * Lấy danh sách kho phân trang, sắp theo tên.
     */
    public function all()
    {
        return Warehouse::orderBy('name')->paginate(20);
    }

    /**
     * Lấy toàn bộ kho không phân trang.
     */
    public function listAll()
    {
        return Warehouse::orderBy('name')->get();
    }

    /**
     * Tìm kho theo ID.
     */
    public function find(int $id)
    {
        return Warehouse::findOrFail($id);
    }

    /**
     * Tạo mới kho.
     */
    public function create(array $data)
    {
        return Warehouse::create($data);
    }

    /**
     * Cập nhật kho.
     */
    public function update($warehouse, array $data)
    {
        $warehouse->update($data);

        return $warehouse;
    }

    /**
     * Xoá kho.
     */
    public function delete($warehouse)
    {
        return $warehouse->delete();
    }
}
