<?php
namespace App\Repositories\Interfaces;
use Illuminate\Pagination\LengthAwarePaginator;

interface ProductRepositoryInterface
{
    public function getAll(?int $warehouseId = null, ?int $categoryId = null, ?string $search = null): LengthAwarePaginator;
    public function find($id);
	public function create(array $data);
	public function update($product, array $data);
	public function delete($product);
}