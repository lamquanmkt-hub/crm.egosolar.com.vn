<?php
namespace App\Repositories\Interfaces;
interface PaymentMethodRepositoryInterface
{
    public function getAll(array $filters = []);
    public function getActiveMethods();
    public function findById(int $id);
    public function create(array $data);
    public function update(int $id, array $data);
    public function delete(int $id);
}