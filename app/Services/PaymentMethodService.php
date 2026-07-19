<?php
namespace App\Services;
use App\Repositories\Interfaces\PaymentMethodRepositoryInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Exception;
class PaymentMethodService
{
    // Dependency Injection (DIP)
    public function __construct(
        protected PaymentMethodRepositoryInterface $repository
    ) {}

    public function getList(array $filters)
    {
        return $this->repository->getAll($filters);
    }

    // Sử dụng Caching Pattern để tối ưu hiệu năng
    public function getActiveMethodsForSelect()
    {
        return Cache::remember('payment_methods_active', 3600, function () {
            return $this->repository->getActiveMethods();
        });
    }

    public function store(array $data)
    {
        return DB::transaction(function () use ($data) {
            $data['code'] = strtoupper($data['code']); // Logic format dữ liệu
            $method = $this->repository->create($data);

            $this->clearCache(); // Side effect
            return $method;
        });
    }

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

    public function delete(int $id)
    {
        $result = $this->repository->delete($id);
        $this->clearCache();
        return $result;
    }

    public function getDetail(int $id)
    {
        $method = $this->repository->findById($id);
        if (!$method) {
            throw new Exception("Phương thức thanh toán không tồn tại.");
        }
        return $method;
    }

    private function clearCache()
    {
        Cache::forget('payment_methods_active');
    }
}