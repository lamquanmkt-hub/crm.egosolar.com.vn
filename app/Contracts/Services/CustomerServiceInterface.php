<?php

declare(strict_types=1);

namespace App\Contracts\Services;

use Illuminate\Support\Collection;

/**
 * Hợp đồng service quản lý khách hàng (Customer).
 */
interface CustomerServiceInterface
{
    public function getAll(): mixed;

    public function create(array $data);

    public function update($id, array $data);

    public function delete($id);

    public function search(array $filters = []);

    public function findWithDetails($id);

    public function count();

    public function countPurchased();

    public function find($id);

    public function convertToMember($customerId, array $membershipData = []);

    public function getCustomersForSelect(): Collection;

    public function searchCustomersForOrderSelect(string $term, int $limit = 30): array;

    public function getCustomerOptionForOrderSelect(int $customerId): ?array;
}
