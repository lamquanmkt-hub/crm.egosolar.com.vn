<?php
namespace App\Repositories\Eloquent;
use App\Models\User;
use App\Repositories\Interfaces\UserRepositoryInterface;

class UserRepository implements UserRepositoryInterface
{
	public function paginate($limit = 20)
	{
		return User::with('roles')->paginate($limit);
	}
	public function find($id)
	{
		return User::with('roles')->findOrFail($id);
	}
	public function create(array $data)
	{
		$user = User::create($data);
		if (!empty($data['roles'])) {
			$user->syncRoles($data['roles']);
		}
		return $user;
	}
	public function update($id, array $data)
	{
		$user = $this->find($id);
		$user->update($data);
		if (isset($data['roles'])) {
			$user->syncRoles($data['roles']);
		}
		return $user;
	}
	public function delete($id)
	{
		return $this->find($id)->delete();
	}
	public function query()
	{
		return User::query();
	}
}
