<?php
namespace App\Repositories\Eloquent;

use App\Models\Payments\PaymentMethod;
use App\Repositories\Interfaces\PaymentMethodRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

class PaymentMethodRepository implements PaymentMethodRepositoryInterface
{
    public function getAll(array $filters = []): LengthAwarePaginator
    {
        $query = PaymentMethod::query();

        if (!empty($filters['keyword'])) {
            $query->where('method_name', 'like', "%{$filters['keyword']}%");
        }

        return $query->orderBy('id', 'desc')->paginate(10);
    }

    public function getActiveMethods(): Collection
    {
        return PaymentMethod::active()->get();
    }

    public function findById(int $id): ?PaymentMethod
    {
        return PaymentMethod::find($id);
    }

    public function create(array $data): PaymentMethod
    {
        return PaymentMethod::create($data);
    }

    public function update(int $id, array $data): bool
    {
        $method = $this->findById($id);
        return $method ? $method->update($data) : false;
    }

    public function delete(int $id): bool
    {
        $method = $this->findById($id);
        return $method ? $method->delete() : false;
    }
}
