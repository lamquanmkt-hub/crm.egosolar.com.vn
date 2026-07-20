<?php

namespace App\Repositories\Eloquent;

use App\Models\Payments\PaymentMethod;
use App\Contracts\Repositories\PaymentMethodRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Repository Eloquent thao tác dữ liệu phương thức thanh toán.
 */
class PaymentMethodRepository implements PaymentMethodRepositoryInterface
{
    /**
     * Lấy danh sách phương thức thanh toán lọc theo từ khoá, phân trang.
     */
    public function getAll(array $filters = []): LengthAwarePaginator
    {
        $query = PaymentMethod::query();

        if (! empty($filters['keyword'])) {
            $query->where('method_name', 'like', "%{$filters['keyword']}%");
        }

        return $query->orderBy('id', 'desc')->paginate(10);
    }

    /**
     * Lấy các phương thức thanh toán đang hoạt động.
     */
    public function getActiveMethods(): Collection
    {
        return PaymentMethod::active()->get();
    }

    /**
     * Tìm phương thức thanh toán theo ID.
     */
    public function findById(int $id): ?PaymentMethod
    {
        return PaymentMethod::find($id);
    }

    /**
     * Tạo mới phương thức thanh toán.
     */
    public function create(array $data): PaymentMethod
    {
        return PaymentMethod::create($data);
    }

    /**
     * Cập nhật phương thức thanh toán.
     */
    public function update(int $id, array $data): bool
    {
        $method = $this->findById($id);

        return $method ? $method->update($data) : false;
    }

    /**
     * Xoá phương thức thanh toán.
     */
    public function delete(int $id): bool
    {
        $method = $this->findById($id);

        return $method ? $method->delete() : false;
    }
}
