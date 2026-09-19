<?php
namespace App\Repositories\Interfaces;
interface UserRepositoryInterface
{
	public function paginate($limit = 20);
	public function find($id);
	public function create(array $data);
	public function update($id, array $data);
	public function delete($id);
	public function query();
}
