<?php
namespace App\Repositories\Interfaces;
interface WarehouseRepositoryInterface
{
	public function all();
	public function listAll();
	public function find(int $id);
	public function create(array $data);
	public function update($warehouse, array $data);
	public function delete($warehouse);
}
