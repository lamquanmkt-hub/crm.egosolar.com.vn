<?php
namespace App\Http\Controllers\Auth;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
class RegisterController extends Controller
{
	public function showRegistrationForm(): \Illuminate\Contracts\View\View {
		return view('auth.register');
	}
	public function register(Request $request): \Illuminate\Http\RedirectResponse {
		$validated = $request->validate([
			'name' => 'required|string|max:255',
			'email' => 'required|email|unique:users,email',
			'password' => 'required|min:6|confirmed',
		]);
		$user = User::create([
			'name' => $validated['name'],
			'email' => $validated['email'],
			'password' => Hash::make($validated['password']),
		]);
		Auth::login($user);
		return redirect()->route('customers.index');
	}
}
