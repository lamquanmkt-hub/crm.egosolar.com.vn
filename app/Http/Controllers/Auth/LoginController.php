<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Str;

class LoginController extends Controller
{
    /**
     * Hiển thị form login
     */
    public function showLoginForm()
    {
        return view('auth.login');
    }

    /**
     * Xử lý đăng nhập
     */
    public function login(Request $request)
    {
        $this->ensureIsNotRateLimited($request);

        $credentials = $request->validate([
            'email'    => 'required|string|email|max:255',
            'password' => 'required|string|max:255',
        ]);

        $remember = $request->boolean('remember');

        if (! Auth::attempt([
            'email'    => $credentials['email'],
            'password' => $credentials['password'],
        ], $remember)) {

            RateLimiter::hit($this->throttleKey($request));

            throw ValidationException::withMessages([
                'login' => 'Email hoặc mật khẩu không đúng.',
            ]);
        }

        RateLimiter::clear($this->throttleKey($request));

        $request->session()->regenerate();

//        $user = Auth::user();

        // BẮT BUỘC cho CRM: email phải được xác thực
//        if (! $user->hasVerifiedEmail()) {
//            Auth::logout();
//
//            throw ValidationException::withMessages([
//                'email' => 'Tài khoản chưa xác thực email.',
//            ]);
//        }
//
//        // (Tuỳ chọn) Check trạng thái tài khoản
//        if ($user->is_active === false) {
//            Auth::logout();
//
//            throw ValidationException::withMessages([
//                'email' => 'Tài khoản đã bị vô hiệu hoá.',
//            ]);
//        }

        return redirect()->intended('/');
    }

    /**
     * Đăng xuất
     */
    public function logout(Request $request)
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }

    /**
     * Chống brute-force
     */
    protected function ensureIsNotRateLimited(Request $request): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey($request), 5)) {
            return;
        }

        throw ValidationException::withMessages([
            'email' => 'Bạn đã đăng nhập sai quá nhiều lần. Vui lòng thử lại sau 1 phút.',
        ]);
    }

    /**
     * Key throttle theo email + IP
     */
    protected function throttleKey(Request $request): string
    {
        return Str::lower($request->input('email')).'|'.$request->ip();
    }
}
