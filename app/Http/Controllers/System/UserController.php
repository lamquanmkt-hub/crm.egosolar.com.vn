<?php

namespace App\Http\Controllers\System;

use App\Http\Controllers\Controller;
use App\Http\Requests\UserStoreRequest;
use App\Http\Requests\UserUpdateRequest;
use App\Models\User;
use App\Services\UserService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

/**
 * Controller quản lý người dùng và hồ sơ cá nhân.
 */
class UserController extends Controller
{
    /**
     * Khởi tạo controller với UserService và yêu cầu đăng nhập.
     */
    public function __construct(protected UserService $service)
    {
        $this->middleware('auth');
    }

    /**
     * Hiển thị danh sách người dùng có phân trang.
     */
    public function index()
    {
        $this->authorize('viewAny', User::class);

        $users = $this->service->paginateUsers(15);

        return view('users.index', compact('users'));
    }

    /**
     * Hiển thị form tạo người dùng.
     */
    public function create()
    {
        $this->authorize('create', User::class);

        return view('users.create');
    }

    /**
     * Tạo người dùng mới.
     */
    public function store(UserStoreRequest $request)
    {
        $this->authorize('create', User::class);

        $this->service->createUser($request->validated());

        return redirect()
            ->route('users.index')
            ->with('success', 'Tạo người dùng thành công.');
    }

    /**
     * Hiển thị chi tiết người dùng.
     */
    public function show(User $user)
    {
        return view('users.show', compact('user'));
    }

    /**
     * Hiển thị form sửa người dùng.
     */
    public function edit(User $user)
    {
        $this->authorize('update', $user);

        return view('users.edit', compact('user'));
    }

    /**
     * Cập nhật thông tin người dùng.
     */
    public function update(UserUpdateRequest $request, User $user)
    {
        $this->authorize('update', $user);

        $this->service->updateUser($user->id, $request->validated());

        return redirect()
            ->route('users.index')
            ->with('success', 'Cập nhật người dùng thành công.');
    }

    /**
     * Xóa người dùng.
     */
    public function destroy(User $user)
    {
        $this->authorize('delete', $user);

        $this->service->deleteUser($user->id);

        return redirect()
            ->route('users.index')
            ->with('success', 'Xoá người dùng thành công.');
    }

    /**
     * Hiển thị trang hồ sơ cá nhân.
     */
    public function profile()
    {
        $user = auth()->user();

        if (! $user) {
            abort(403);
        }

        return view('users.profile', compact('user'));
    }

    /**
     * Hiển thị form sửa hồ sơ cá nhân.
     */
    public function editProfile()
    {
        $user = auth()->user();

        if (! $user) {
            abort(403);
        }

        return view('users.profile-edit', compact('user'));
    }

    /**
     * Cập nhật hồ sơ cá nhân: tên, email và mật khẩu nếu có.
     */
    public function updateProfile(Request $request)
    {
        $user = auth()->user();

        if (! $user) {
            abort(403);
        }

        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email,'.$user->id],
            'password' => ['nullable', 'string', 'min:6', 'confirmed'],
        ], [
            'name.required' => 'Vui lòng nhập họ và tên.',
            'email.required' => 'Vui lòng nhập email.',
            'email.email' => 'Email không hợp lệ.',
            'email.unique' => 'Email này đã được sử dụng.',
            'password.min' => 'Mật khẩu tối thiểu 6 ký tự.',
            'password.confirmed' => 'Xác nhận mật khẩu không khớp.',
        ]);

        $user->name = $request->name;
        $user->email = $request->email;

        if ($request->filled('password')) {
            $user->password = Hash::make($request->password);
        }

        $user->save();

        return redirect()
            ->route('users.profile-edit')
            ->with('success', 'Cập nhật hồ sơ thành công.');
    }

    /**
     * Cập nhật ảnh đại diện, hỗ trợ lưu qua Media hoặc cột avatar.
     */
    public function updateAvatar(Request $request)
    {
        $user = auth()->user();

        if (! $user) {
            abort(403);
        }

        $request->validate([
            'avatar' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ], [
            'avatar.required' => 'Vui lòng chọn ảnh đại diện.',
            'avatar.image' => 'Tệp tải lên phải là hình ảnh.',
            'avatar.mimes' => 'Avatar chỉ hỗ trợ JPG, JPEG, PNG, WEBP.',
            'avatar.max' => 'Dung lượng ảnh tối đa là 2MB.',
        ]);

        $file = $request->file('avatar');
        $path = $file->store('avatars', 'public');

        if (Schema::hasColumn('users', 'avatar_id') && class_exists(\App\Models\Media::class)) {
            try {
                if ($user->avatar && is_object($user->avatar) && ! empty($user->avatar->file_path)) {
                    Storage::disk('public')->delete($user->avatar->file_path);
                }
            } catch (\Throwable $e) {
                //
            }

            $media = \App\Models\Media::create([
                'file_name' => $file->getClientOriginalName(),
                'file_path' => $path,
                'mime_type' => $file->getClientMimeType(),
                'size' => $file->getSize(),
                'uploaded_by' => $user->id,
            ]);

            $user->avatar_id = $media->id;
            $user->save();

            return back()->with('success', 'Cập nhật avatar thành công.');
        }

        if (Schema::hasColumn('users', 'avatar')) {
            if (! empty($user->avatar) && is_string($user->avatar)) {
                Storage::disk('public')->delete($user->avatar);
            }

            $user->avatar = $path;
            $user->save();

            return back()->with('success', 'Cập nhật avatar thành công.');
        }

        Storage::disk('public')->delete($path);

        return back()->with('error', 'Hệ thống chưa cấu hình nơi lưu avatar.');
    }
}
