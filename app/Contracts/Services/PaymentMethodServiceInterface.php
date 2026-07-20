<?php

declare(strict_types=1);

namespace App\Contracts\Services;

/**
 * Hợp đồng service quản lý phương thức thanh toán (PaymentMethod).
 */
interface PaymentMethodServiceInterface
{
    public function getList(array $filters);

    public function getActiveMethodsForSelect();

    public function store(array $data);

    public function update(int $id, array $data);

    public function delete(int $id);

    public function getDetail(int $id);
}
