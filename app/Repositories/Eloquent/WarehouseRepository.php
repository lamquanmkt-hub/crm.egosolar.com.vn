<?php
namespace App\Repositories\Eloquent;
use App\Models\Core\Warehouse;
use App\Repositories\Interfaces\WarehouseRepositoryInterface;

class WarehouseRepository implements WarehouseRepositoryInterface
{
	public function all()
	{
		return Warehouse::orderBy('name')->paginate(20);
	}
	public function listAll()
	{
		return Warehouse::orderBy('name')->get();
	}
	public function find(int $id)
	{
		return Warehouse::findOrFail($id);
	}
	public function create(array $data)
	{
		return Warehouse::create($data);
	}
	public function update($warehouse, array $data)
	{
		$warehouse->update($data);
		return $warehouse;
	}
	public function delete($warehouse)
	{
		return $warehouse->delete();
	}
}
