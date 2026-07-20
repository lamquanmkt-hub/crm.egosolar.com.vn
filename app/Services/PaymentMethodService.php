<?php

declare(strict_types=1);

namespace App\Services;
use App\Contracts\Services\PaymentMethodServiceInterface;
use App\Repositories\Interfaces\PaymentMethodRepositoryInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Exception;

/**
 * Service xử lý nghiệp vụ phương thức thanh toán (PaymentMethod).
 */
class PaymentMethodService implements PaymentMethodServiceInterface
{
    // Dependency Injection (DIP)
    /**
     * Khởi tạo service với repository phương thức thanh toán.
     */
    public function __construct(
        protected PaymentMethodRepositoryInterface $repository
    ) {}

    /**
     * Lấy danh sách phương thức thanh toán theo bộ lọc.
     */
    public function getList(array $filters)
    {
        return $this->repository->getAll($filters);
    }

    // Sử dụng Caching Pattern để tối ưu hiệu năng
    /**
     * Lấy các phương thức đang hoạt động cho dropdown (cache 1 giờ).
     */
    public function getActiveMethodsForSelect()
    {
        return Cache::remember('payment_methods_active', 3600, function () {
            return $this->repository->getActiveMethods();
        });
    }

    /**
     * Tạo mới phương thức thanh toán (chuẩn hoá code viết hoa) và xoá cache.
     */
    public function store(array $data)
    {
        return DB::transaction(function () use ($data) {
            $data['code'] = strtoupper($data['code']); // Logic format dữ liệu
            $method = $this->repository->create($data);

            $this->clearCache(); // Side effect
            return $method;
        });
    }

    /**
     * Cập nhật phương thức thanh toán và xoá cache.
     */
    public function update(int $id, array $data)
    {
        return DB::transaction(function () use ($id, $data) {
            if (isset($data['code'])) {
                $data['code'] = strtoupper($data['code']);
            }

            $result = $this->repository->update($id, $data);

            $this->clearCache();
            return $result;
        });
    }

    /**
     * Xoá phương thức thanh toán và xoá cache.
     */
    public function delete(int $id)
    {
        $result = $this->repository->delete($id);
        $this->clearCache();
        return $result;
    }

    /**
     * Lấy chi tiết phương thức thanh toán, ném ngoại lệ nếu không tồn tại.
     */
    public function getDetail(int $id)
    {
        $method = $this->repository->findById($id);
        if (!$method) {
            throw new Exception("Phương thức thanh toán không tồn tại.");
        }
        return $method;
    }

    /**
     * Xoá cache danh sách phương thức đang hoạt động.
     */
    private function clearCache()
    {
        Cache::forget('payment_methods_active');
    }
}