<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Workspace\WorkspaceProfileService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Throwable;

final class WorkspaceSettingsController extends Controller
{
    public function __construct(private readonly WorkspaceProfileService $profiles)
    {
        $this->middleware('auth');
    }

    public function index(Request $request): View
    {
        $this->authorizeManager($request);

        $profiles = $this->profiles->profiles();
        $apps = collect(config('ego_workspace.apps', []))
            ->map(fn (array $app): array => array_merge($app, [
                'category_label' => (string) (config('ego_workspace.categories.'.$app['category']) ?? $app['category']),
            ]))
            ->values();

        return view('admin.settings.workspace', [
            'profiles' => $profiles,
            'apps' => $apps,
            'categories' => config('ego_workspace.categories', []),
            'requiredAppIds' => collect(config('ego_workspace.required_apps', []))
                ->map(fn ($id): string => (string) $id)
                ->values()
                ->all(),
            'profileUserCounts' => $this->profileUserCounts($profiles),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $this->authorizeManager($request);

        $validated = $request->validate([
            'profiles' => ['nullable', 'array'],
            'profiles.*.apps' => ['nullable', 'array'],
            'profiles.*.apps.*' => ['string', 'max:80'],
            'profiles.*.featured' => ['nullable', 'array', 'max:3'],
            'profiles.*.featured.*' => ['nullable', 'string', 'max:80'],
            'profiles.*.default_category' => ['nullable', 'string', 'max:80'],
        ]);

        try {
            $this->profiles->save($validated['profiles'] ?? []);
        } catch (Throwable $exception) {
            report($exception);

            return back()
                ->withInput()
                ->with('error', 'Không thể lưu ma trận ứng dụng: '.$exception->getMessage());
        }

        return back()->with('success', 'Đã lưu ma trận ứng dụng theo vai trò. Nhân viên tải lại Workspace sẽ nhận cấu hình mới.');
    }

    public function reset(Request $request): RedirectResponse
    {
        $this->authorizeManager($request);
        $this->profiles->reset();

        return back()->with('success', 'Đã khôi phục ma trận Workspace mặc định.');
    }

    private function authorizeManager(Request $request): void
    {
        /** @var User|null $user */
        $user = $request->user();
        abort_unless($user && $this->profiles->canManage($user), 403);
    }

    private function profileUserCounts(array $profiles): array
    {
        $counts = [];

        foreach ($profiles as $key => $profile) {
            $roleNames = collect($profile['role_names'] ?? [])
                ->map(fn ($name): string => (string) $name)
                ->filter()
                ->values();

            if ($roleNames->isEmpty()) {
                $counts[$key] = null;
                continue;
            }

            try {
                $counts[$key] = User::query()
                    ->whereHas('roles', fn ($query) => $query->whereIn('name', $roleNames->all()))
                    ->distinct()
                    ->count('users.id');
            } catch (Throwable) {
                $counts[$key] = null;
            }
        }

        return $counts;
    }
}
