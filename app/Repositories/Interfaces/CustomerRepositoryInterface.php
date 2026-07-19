<?php
namespace App\Repositories\Interfaces;
interface CustomerRepositoryInterface
{
	public function all();
	public function getAllWithRelations();
	public function find($id);
	public function findWithDetails($id);
	public function search(array $filters = []);
	public function create(array $data);
	public function update($id, array $data);
	public function delete($id);
	public function count();
	public function countPurchased();
}
