<?php

namespace App\Http\Controllers\Admin;

use App\Contracts\Services\PageAccessServiceInterface;
use App\Http\Controllers\Controller;
use App\Models\RolePermissionAudit;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Trung tâm Cài đặt vai trò và phân quyền.
 *
 * Tách rõ ba lớp:
 * - page.*: quyền truy cập URL/backend.
 * - menu.*: quyền nhìn thấy mục trong sidebar.
 * - quyền nghiệp vụ: create/update/delete/approve...
 */
class RolePermissionController extends Controller
{
    public function __construct(private readonly PageAccessServiceInterface $pageAccess) {}

    public function overview(Request $request)
    {
        return $this->renderSettings($request, 'overview');
    }

    public function roles(Request $request)
    {
        return $this->renderSettings($request, 'roles');
    }

    public function pages(Request $request)
    {
        return $this->renderSettings($request, 'pages');
    }

    public function menus(Request $request)
    {
        return $this->renderSettings($request, 'menus');
    }

    public function actions(Request $request)
    {
        return $this->renderSettings($request, 'actions');
    }

    public function audit(Request $request)
    {
        return $this->renderSettings($request, 'audit');
    }

    /**
     * Route cũ vẫn hoạt động và mở thẳng màn hình Vai trò & nhân sự.
     */
    public function index(Request $request)
    {
        return $this->roles($request);
    }

    private function renderSettings(Request $request, string $section)
    {
        $modelRoleTable = config('permission.table_names.model_has_roles', 'model_has_roles');

        $roleUserCounts = DB::table($modelRoleTable)
            ->where('model_type', User::class)
            ->selectRaw('role_id, COUNT(*) AS total')
            ->groupBy('role_id')
            ->pluck('total', 'role_id');

        $roles = Role::query()
            ->with('permissions')
            ->orderByRaw("CASE WHEN name = 'admin' THEN 0 WHEN name = 'management' THEN 1 ELSE 2 END")
            ->orderByRaw('COALESCE(display_name, name)')
            ->get()
            ->each(function (Role $role) use ($roleUserCounts) {
                $role->setAttribute('users_count', (int) ($roleUserCounts[$role->id] ?? 0));
                $role->setAttribute('ui_name', $this->pageAccess->displayRoleName($role));
            });

        $selectedRole = $roles->firstWhere('id', (int) $request->integer('role'))
            ?? $roles->firstWhere('name', 'admin')
            ?? $roles->first();

        $permissions = Permission::query()->orderBy('name')->get();
        $pageDefinitions = config('role_permissions.page_permissions', []);
        $menuDefinitions = config('role_permissions.menu_permissions', []);
        $pageNames = array_keys($pageDefinitions);
        $menuNames = array_keys($menuDefinitions);

        $pagePermissions = $permissions->whereIn('name', $pageNames)->values();
        $menuPermissions = $permissions->whereIn('name', $menuNames)->values();
        $businessPermissions = $permissions
            ->reject(fn (Permission $permission): bool =>
                in_array($permission->name, $pageNames, true)
                || in_array($permission->name, $menuNames, true)
                || str_starts_with($permission->name, 'settings.')
            )
            ->values();

        $businessGroups = $this->pageAccess->permissionGroups($businessPermissions);

        $selectedPermissionNames = $selectedRole
            ? $selectedRole->permissions->pluck('name')->all()
            : [];

        $selectedPageNames = array_values(array_intersect($selectedPermissionNames, $pageNames));
        $selectedMenuNames = array_values(array_intersect($selectedPermissionNames, $menuNames));
        $selectedBusinessNames = array_values(array_diff(
            $selectedPermissionNames,
            $pageNames,
            $menuNames,
            collect($selectedPermissionNames)
                ->filter(fn (string $name): bool => str_starts_with($name, 'settings.'))
                ->all()
        ));

        $users = User::query()
            ->with(['roles', 'permissions'])
            ->orderBy('name')
            ->get();

        $audits = Schema::hasTable('role_permission_audits')
            ? RolePermissionAudit::query()->with('actor')->latest()->limit(100)->get()
            : collect();

        $stats = [
            'roles' => $roles->count(),
            'permissions' => $permissions->count(),
            'page_permissions' => count($pageNames),
            'menu_permissions' => count($menuNames),
            'users' => $users->count(),
            'unassigned_users' => $users->filter(fn (User $user) => $user->roles->isEmpty())->count(),
            'managed_roles' => $roles->filter(fn (Role $role) => (bool) ($role->page_access_enabled ?? false))->count(),
        ];

        return view('admin.settings.index', compact(
            'section',
            'roles',
            'selectedRole',
            'permissions',
            'pageDefinitions',
            'menuDefinitions',
            'pagePermissions',
            'menuPermissions',
            'businessPermissions',
            'businessGroups',
            'selectedPermissionNames',
            'selectedPageNames',
            'selectedMenuNames',
            'selectedBusinessNames',
            'users',
            'audits',
            'stats'
        ));
    }

    public function storeRole(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'display_name' => ['required', 'string', 'max:120'],
            'name' => [
                'nullable',
                'string',
                'max:120',
                'regex:/^[a-zA-Z0-9._-]+$/',
                Rule::unique('roles', 'name')->where('guard_name', 'web'),
            ],
            'description' => ['nullable', 'string', 'max:1000'],
            'clone_from' => ['nullable', 'integer', 'exists:roles,id'],
        ], [
            'name.regex' => 'Mã role chỉ dùng chữ không dấu, số, dấu chấm, gạch ngang hoặc gạch dưới.',
        ]);

        $code = trim($validated['name'] ?: Str::slug($validated['display_name'], '_'), '._-');

        if ($code === '') {
            return back()->withErrors(['name' => 'Không thể tạo mã role từ tên đã nhập.'])->withInput();
        }

        if (Role::where('name', $code)->where('guard_name', 'web')->exists()) {
            return back()->withErrors(['name' => 'Mã role đã tồn tại.'])->withInput();
        }

        $role = DB::transaction(function () use ($validated, $code) {
            $role = Role::create([
                'name' => $code,
                'guard_name' => 'web',
                'display_name' => $validated['display_name'],
                'description' => $validated['description'] ?? null,
                'is_system' => false,
                'page_access_enabled' => true,
            ]);

            if (! empty($validated['clone_from'])) {
                $source = Role::with('permissions')->findOrFail($validated['clone_from']);
                $role->syncPermissions($source->permissions->pluck('name')->all());
            } else {
                $defaults = [
                    'page.dashboard',
                    'page.booking',
                    'page.payment_requests',
                    'page.proposals',
                    'page.tasks',
                    'page.company',
                    'menu.dashboard',
                    'menu.booking',
                    'menu.payment_requests',
                    'menu.proposals',
                    'menu.tasks',
                    'menu.company',
                ];

                $role->syncPermissions(
                    Permission::query()->whereIn('name', $defaults)->pluck('name')->all()
                );
            }

            return $role;
        });

        $this->flushPermissionCache();
        $this->recordAudit('role.created', $role, null, $this->roleSnapshot($role->fresh('permissions')));

        return redirect()
            ->route('admin.settings.roles', ['role' => $role->id])
            ->with('success', 'Đã tạo vai trò mới.');
    }

    public function updateRole(Request $request, Role $role): RedirectResponse
    {
        $isSystem = $this->isSystemRole($role);

        $rules = [
            'display_name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:1000'],
        ];

        if (! $isSystem) {
            $rules['name'] = [
                'required',
                'string',
                'max:120',
                'regex:/^[a-zA-Z0-9._-]+$/',
                Rule::unique('roles', 'name')->where('guard_name', 'web')->ignore($role->id),
            ];
        }

        $validated = $request->validate($rules);
        $before = $this->roleSnapshot($role->load('permissions'));

        $role->display_name = $validated['display_name'];
        $role->description = $validated['description'] ?? null;

        if (! $isSystem) {
            $role->name = $validated['name'];
        }

        $role->save();
        $this->flushPermissionCache();
        $this->recordAudit('role.updated', $role, $before, $this->roleSnapshot($role->fresh('permissions')));

        return back()->with('success', 'Đã cập nhật thông tin vai trò.');
    }

    public function syncRolePagePermissions(Request $request, Role $role): RedirectResponse
    {
        $allowed = array_keys(config('role_permissions.page_permissions', []));
        $selected = $this->validatedPermissionNames($request, $allowed);

        if ($role->name !== 'admin') {
            $selected = $selected->reject(fn (string $name): bool => $name === 'page.settings');
        }

        if (! $selected->contains('page.dashboard')) {
            $selected->push('page.dashboard');
        }

        $this->syncPermissionCategory(
            $role,
            $selected,
            $allowed,
            'role.page_permissions_synced',
            true
        );

        return back()->with('success', 'Đã lưu quyền truy cập trang. Thay đổi có hiệu lực ngay.');
    }

    public function syncRoleMenuPermissions(Request $request, Role $role): RedirectResponse
    {
        $allowed = array_keys(config('role_permissions.menu_permissions', []));
        $selected = $this->validatedPermissionNames($request, $allowed);

        if ($role->name !== 'admin') {
            $selected = $selected->reject(fn (string $name): bool => $name === 'menu.settings');
        }

        if (! $selected->contains('menu.dashboard')) {
            $selected->push('menu.dashboard');
        }

        $this->syncPermissionCategory(
            $role,
            $selected,
            $allowed,
            'role.menu_permissions_synced'
        );

        return back()->with('success', 'Đã lưu quyền hiển thị menu. Sidebar sẽ cập nhật ở lần tải trang kế tiếp.');
    }

    public function syncRoleBusinessPermissions(Request $request, Role $role): RedirectResponse
    {
        $pageNames = array_keys(config('role_permissions.page_permissions', []));
        $menuNames = array_keys(config('role_permissions.menu_permissions', []));

        $allowed = Permission::query()
            ->pluck('name')
            ->reject(fn (string $name): bool =>
                in_array($name, $pageNames, true)
                || in_array($name, $menuNames, true)
                || str_starts_with($name, 'settings.')
            )
            ->values()
            ->all();

        $selected = $this->validatedPermissionNames($request, $allowed);

        $this->syncPermissionCategory(
            $role,
            $selected,
            $allowed,
            'role.business_permissions_synced'
        );

        return back()->with('success', 'Đã lưu quyền thao tác nghiệp vụ.');
    }

    /**
     * Tương thích route/form cũ: xem như lưu quyền thao tác nghiệp vụ.
     */
    public function syncRolePermissions(Request $request, Role $role): RedirectResponse
    {
        return $this->syncRoleBusinessPermissions($request, $role);
    }

    public function cloneRole(Request $request, Role $role): RedirectResponse
    {
        $validated = $request->validate([
            'display_name' => ['nullable', 'string', 'max:120'],
        ]);

        $baseCode = $role->name.'_copy';
        $code = $baseCode;
        $suffix = 2;

        while (Role::where('name', $code)->where('guard_name', 'web')->exists()) {
            $code = $baseCode.'_'.$suffix++;
        }

        $clone = DB::transaction(function () use ($role, $validated, $code) {
            $clone = Role::create([
                'name' => $code,
                'guard_name' => 'web',
                'display_name' => $validated['display_name'] ?: $this->pageAccess->displayRoleName($role).' - Bản sao',
                'description' => $role->description,
                'is_system' => false,
                'page_access_enabled' => true,
            ]);

            $clone->syncPermissions($role->permissions()->pluck('name')->all());

            return $clone;
        });

        $this->flushPermissionCache();
        $this->recordAudit('role.cloned', $clone, null, $this->roleSnapshot($clone->fresh('permissions')));

        return redirect()
            ->route('admin.settings.roles', ['role' => $clone->id])
            ->with('success', 'Đã sao chép vai trò.');
    }

    public function destroyRole(Role $role): RedirectResponse
    {
        abort_if($this->isSystemRole($role), 422, 'Không thể xóa role hệ thống vì source đang tham chiếu trực tiếp tên role này.');

        $assigned = DB::table(config('permission.table_names.model_has_roles', 'model_has_roles'))
            ->where('role_id', $role->id)
            ->count();

        abort_if($assigned > 0, 422, 'Role đang được gán cho nhân viên. Hãy chuyển role trước khi xóa.');

        $before = $this->roleSnapshot($role->load('permissions'));
        $roleName = $role->name;
        $roleId = $role->id;
        $role->delete();
        $this->flushPermissionCache();
        $this->auditRaw('role.deleted', 'role', $roleId, $roleName, $before, null);

        return redirect()->route('admin.settings.roles')->with('success', 'Đã xóa vai trò.');
    }

    public function syncUserRoles(Request $request, User $user): RedirectResponse
    {
        $validated = $request->validate([
            'roles' => ['required', 'array', 'min:1'],
            'roles.*' => ['integer', 'exists:roles,id'],
        ]);

        $roles = Role::whereIn('id', $validated['roles'])->get();
        $before = ['roles' => $user->roles()->pluck('name')->all()];

        if ($user->is(auth()->user()) && $user->hasRole('admin') && ! $roles->contains('name', 'admin')) {
            return back()->withErrors(['roles' => 'Bạn không thể tự gỡ role admin khỏi tài khoản đang đăng nhập.']);
        }

        $user->syncRoles($roles);
        $this->flushPermissionCache();
        $this->recordAudit('user.roles_synced', $user, $before, [
            'roles' => $user->fresh()->roles()->pluck('name')->all(),
        ]);

        return back()->with('success', 'Đã cập nhật vai trò cho '.$user->name.'.');
    }

    public function syncUserPermissions(Request $request, User $user): RedirectResponse
    {
        $validated = $request->validate([
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['string', 'exists:permissions,name'],
        ]);

        $before = [
            'direct_permissions' => $user->permissions()->pluck('name')->all(),
        ];

        $user->syncPermissions($validated['permissions'] ?? []);
        $this->flushPermissionCache();
        $this->recordAudit('user.direct_permissions_synced', $user, $before, [
            'direct_permissions' => $user->fresh()->permissions()->pluck('name')->all(),
        ]);

        return back()->with('success', 'Đã cập nhật quyền riêng cho '.$user->name.'.');
    }

    private function validatedPermissionNames(Request $request, array $allowed): Collection
    {
        $validated = $request->validate([
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['string', Rule::in($allowed)],
        ]);

        return collect($validated['permissions'] ?? [])
            ->filter(fn ($name): bool => is_string($name) && in_array($name, $allowed, true))
            ->unique()
            ->values();
    }

    private function syncPermissionCategory(
        Role $role,
        Collection $selected,
        array $categoryNames,
        string $auditAction,
        bool $enablePageControl = false
    ): void {
        $before = $this->roleSnapshot($role->load('permissions'));
        $current = $role->permissions->pluck('name');
        $next = $current
            ->reject(fn (string $name): bool => in_array($name, $categoryNames, true))
            ->merge($selected)
            ->unique()
            ->values();

        if ($role->name === 'admin') {
            $next = Permission::query()->pluck('name')->unique()->values();
            $enablePageControl = true;
        }

        DB::transaction(function () use ($role, $next, $enablePageControl) {
            $role->syncPermissions($next->all());

            if ($enablePageControl || $role->name === 'admin') {
                DB::table(config('permission.table_names.roles', 'roles'))
                    ->where('id', $role->id)
                    ->update([
                        'page_access_enabled' => true,
                        'updated_at' => now(),
                    ]);
            }
        });

        $this->flushPermissionCache();
        $fresh = $role->fresh('permissions');
        $this->recordAudit($auditAction, $fresh, $before, $this->roleSnapshot($fresh));
    }

    private function isSystemRole(Role $role): bool
    {
        return (bool) ($role->is_system ?? false)
            || in_array($role->name, config('role_permissions.protected_roles', []), true);
    }

    private function roleSnapshot(Role $role): array
    {
        return [
            'id' => $role->id,
            'name' => $role->name,
            'display_name' => $role->display_name ?? null,
            'description' => $role->description ?? null,
            'is_system' => (bool) ($role->is_system ?? false),
            'page_access_enabled' => (bool) ($role->page_access_enabled ?? false),
            'permissions' => $role->permissions->pluck('name')->sort()->values()->all(),
        ];
    }

    private function recordAudit(string $action, $subject, ?array $before, ?array $after): void
    {
        $this->auditRaw(
            $action,
            class_basename($subject),
            $subject->getKey(),
            $subject->name ?? $subject->email ?? null,
            $before,
            $after
        );
    }

    private function auditRaw(
        string $action,
        string $subjectType,
        int|string|null $subjectId,
        ?string $subjectName,
        ?array $before,
        ?array $after
    ): void {
        if (! Schema::hasTable('role_permission_audits')) {
            return;
        }

        RolePermissionAudit::create([
            'actor_id' => auth()->id(),
            'action' => $action,
            'subject_type' => $subjectType,
            'subject_id' => $subjectId,
            'subject_name' => $subjectName,
            'before_data' => $before,
            'after_data' => $after,
            'ip_address' => request()->ip(),
            'user_agent' => Str::limit((string) request()->userAgent(), 1000, ''),
        ]);
    }

    private function flushPermissionCache(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
