<?php

namespace App\Repositories\Eloquent;

use App\Models\Inventory\Catalog\Product;
use App\Repositories\Interfaces\ProductRepositoryInterface;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Repository Eloquent thao tác dữ liệu sản phẩm.
 */
class ProductRepository implements ProductRepositoryInterface
{
    /**
     * Lấy danh sách sản phẩm kèm quan hệ, lọc theo kho/danh mục/từ khoá, phân trang.
     */
    public function getAll(?int $warehouseId = null, ?int $categoryId = null, ?string $search = null): LengthAwarePaginator
    {
        $query = Product::query()
            ->with([
                'category',
                'mainImage.media.metadata',

                // ✅ Load bảng giá theo loại giá (PriceTier)
                'prices.priceTier',

                // ✅ Load tồn kho (lọc theo kho nếu có chọn)
                'stocks' => function ($q) use ($warehouseId) {
                    if ($warehouseId) {
                        $q->where('warehouse_id', $warehouseId);
                    }
                },
            ]);

        // ✅ Lọc danh mục
        if (! empty($categoryId)) {
            $query->where('category_id', $categoryId);
        }

        // ✅ Tìm kiếm theo tên hoặc SKU
        if (! empty($search)) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('sku', 'like', "%{$search}%");
            });
        }

        // ✅ Tồn kho theo kho (nếu chọn kho) hoặc tổng tồn tất cả kho
        if (! empty($warehouseId)) {
            // chỉ lấy sản phẩm có tồn ở kho đó (qty > 0 nếu bạn muốn)
            $query->whereHas('stocks', function ($q) use ($warehouseId) {
                $q->where('warehouse_id', $warehouseId);
                // nếu muốn chỉ hiện SP có tồn > 0 thì mở dòng dưới
                // $q->where('qty', '>', 0);
            });

            $query->withSum(
                ['stocks as warehouse_qty' => fn ($q) => $q->where('warehouse_id', $warehouseId)],
                'qty'
            );
        } else {
            $query->withSum('stocks', 'qty'); // => stocks_sum_qty
        }

        return $query->paginate(20);
    }

    /**
     * Tìm sản phẩm theo ID.
     */
    public function find($id)
    {
        return Product::findOrFail($id);
    }

    /**
     * Tạo mới sản phẩm.
     */
    public function create(array $data)
    {
        return Product::create($data);
    }

    /**
     * Cập nhật sản phẩm.
     */
    public function update($product, array $data)
    {
        $product->update($data);

        return $product;
    }

    /**
     * Xoá sản phẩm.
     */
    public function delete($product)
    {
        return $product->delete();
    }
}
