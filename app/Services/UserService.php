<?php
namespace App\Services;
use App\Repositories\Interfaces\UserRepositoryInterface;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
class UserService
{
	public function __construct(protected UserRepositoryInterface $users) {}
	public function createUser(array $data)
	{
		try {
			$data['password'] = Hash::make($data['password']);
			$user = $this->users->create($data);
			if (!empty($data['roles']) && Auth::user()->hasRole('admin')) {
				$user->syncRoles($data['roles']);
			}
			return $user;
		} catch (\Exception $e) {
			report($e);
			throw new \Exception("Không thể tạo user: ".$e->getMessage());
		}
	}
	public function updateUser($id, array $data)
	{
		if (!empty($data['password'])) {
			$data['password'] = Hash::make($data['password']);
		} else {
			unset($data['password']);
		}
		// Nếu không phải admin, bỏ role khỏi dữ liệu
		if (!Auth::user()->hasRole('admin')) {
			unset($data['roles']);
		}
		return $this->users->update($id, $data);
	}
	public function deleteUser($id)
	{
		return $this->users->delete($id);
	}
	public function getUser($id)
	{
		return $this->users->find($id);
	}
	public function paginateUsers($perPage = 15)
	{
		if (Auth::user()->hasRole('admin')) {
			return $this->users->paginate($perPage);
		}
		// Non-admin chỉ xem mình
		return $this->users->query()->where('id', Auth::id())->paginate(1);
	}
}
