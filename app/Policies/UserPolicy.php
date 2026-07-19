<?php
namespace App\Policies;
use App\Models\User;
class UserPolicy
{
    /**
     * Create a new policy instance.
     */
    public function __construct()
    {
        //
    }
	public function create(User $user): bool
	{
		return $user->hasRole('admin');
	}
	public function viewAny($user)
	{
		return $user->hasRole('admin');
	}
	public function view($user, $target): bool {
		return $user->hasRole('admin') || $user->id === $target->id;
	}
	public function update(User $user, User $target): bool
	{
		return $user->hasRole('admin') || $user->id === $target->id;
	}
	public function delete($user, $target): bool {
		return $user->hasRole('admin') && $user->id !== $target->id;
	}
}
