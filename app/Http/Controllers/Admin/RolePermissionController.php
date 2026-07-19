<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\RolePermissionAudit;
use App\Models\User;
use App\Services\RolePermission\PageAccessService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolePermissionController extends Controller
{
    public function __construct(private readonly PageAccessService $pageAccess)
    {
    }

    public function index(Request $request)
    {
        $roleTable = config('permission.table_names.roles', 'roles');
        $modelRoleTable = config('permission.table_names.model_has_roles', 'model_has_roles');

        $roleUserCounts = DB::table($modelRoleTable)
            ->where('model_type', User::class)
            ->selectRaw('role_id, COUNT(*) AS total')
            ->groupBy('role_id')
            ->pluck('total', 'role_id');

        $roles = Role::query()
            ->with('permissions')
            ->orderByRaw("CASE WHEN name = 'admin' THEN 0 ELSE 1 END")
            ->orderByRaw("COALESCE(display_name, name)")
            ->get()
            ->each(function (Role $role) use ($roleUserCounts) {
                $role->setAttribute('users_count', (int) ($roleUserCounts[$role->id] ?? 0));
                $role->setAttribute('ui_name', $this->pageAccess->displayRoleName($role));
            });

        $selectedRole = $roles->firstWhere('id', (int) $request->integer('role'))
            ?? $roles->first();

        $permissions = Permission::query()->orderBy('name')->get();
        $permissionGroups = $this->pageAccess->permissionGroups($permissions);
        $selectedPermissionNames = $selectedRole
            ? $selectedRole->permissions->pluck('name')->all()
            : [];

        $users = User::query()
            ->with(['roles', 'permissions'])
            ->orderBy('name')
            ->get();

        $audits = Schema::hasTable('role_permission_audits')
            ? RolePermissionAudit::query()->with('actor')->latest()->limit(60)->get()
            : collect();

        $stats = [
            'roles' => $roles->count(),
            'permissions' => $permissions->count(),
            'users' => $users->count(),
            'unassigned_users' => $users->filter(fn (User $user) => $user->roles->isEmpty())->count(),
            'managed_roles' => $roles->filter(fn (Role $role) => (bool) ($role->page_access_enabled ?? false))->count(),
        ];

        return view('admin.role-permissions.index', compact(
            'roles',
            'selectedRole',
            'permissions',
            'permissionGroups',
            'selectedPermissionNames',
            'users',
            'audits',
            'stats',
            'roleTable'
        ));
    }

    public function storeRole(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'display_name' => ['required', 'string', 'max:120'],
            'name' => ['nullable', 'string', 'max:120', 'regex:/^[a-zA-Z0-9._-]+$/', Rule::unique('roles', 'name')->where('guard_name', 'web')],
            'description' => ['nullable', 'string', 'max:1000'],
            'clone_from' => ['nullable', 'integer', 'exists:roles,id'],
        ], [
            'name.regex' => 'Mã role chỉ dùng chữ không dấu, số, dấu chấm, gạch ngang hoặc gạch dưới.',
        ]);

        $code = $validated['name'] ?: Str::slug($validated['display_name'], '_');
        $code = trim($code, '._-');

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

            if (!empty($validated['clone_from'])) {
                $source = Role::with('permissions')->findOrFail($validated['clone_from']);
                $role->syncPermissions($source->permissions->pluck('name')->all());
            } else {
                $role->givePermissionTo('page.dashboard');
            }

            return $role;
        });

        $this->flushPermissionCache();
        $this->audit('role.created', $role, null, $this->roleSnapshot($role->fresh('permissions')));

        return redirect()
            ->route('admin.role-permissions.index', ['role' => $role->id])
            ->with('success', 'Đã tạo vai trò mới.');
    }

    public function updateRole(Request $request, Role $role): RedirectResponse
    {
        $isSystem = (bool) ($role->is_system ?? false) || in_array($role->name, config('role_permissions.protected_roles', []), true);

        $rules = [
            'display_name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:1000'],
        ];

        if (!$isSystem) {
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

        if (!$isSystem) {
            $role->name = $validated['name'];
        }

        $role->save();
        $this->flushPermissionCache();
        $this->audit('role.updated', $role, $before, $this->roleSnapshot($role->fresh('permissions')));

        return back()->with('success', 'Đã cập nhật thông tin vai trò.');
    }

    public function syncRolePermissions(Request $request, Role $role): RedirectResponse
    {
        $validated = $request->validate([
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['string', 'exists:permissions,name'],
            'page_access_enabled' => ['nullable', 'boolean'],
        ]);

        $before = $this->roleSnapshot($role->load('permissions'));
        $permissions = collect($validated['permissions'] ?? [])->unique()->values();
        $pageEnabled = $request->boolean('page_access_enabled');

        if ($pageEnabled && !$permissions->contains('page.dashboard')) {
            $permissions->push('page.dashboard');
        }

        if ($role->name === 'admin') {
            $permissions = $permissions->merge([
                'page.dashboard',
                'page.settings',
                'settings.roles.view',
                'settings.roles.manage',
                'settings.users.manage',
            ])->unique()->values();
            $pageEnabled = true;
        }

        DB::transaction(function () use ($role, $permissions, $pageEnabled) {
            $role->syncPermissions($permissions->all());
            DB::table(config('permission.table_names.roles', 'roles'))
                ->where('id', $role->id)
                ->update([
                    'page_access_enabled' => $pageEnabled,
                    'updated_at' => now(),
                ]);
        });

        $this->flushPermissionCache();
        $role = $role->fresh('permissions');
        $this->audit('role.permissions_synced', $role, $before, $this->roleSnapshot($role));

        return back()->with('success', 'Đã lưu ma trận phân quyền cho vai trò.');
    }

    public function cloneRole(Request $request, Role $role): RedirectResponse
    {
        $validated = $request->validate([
            'display_name' => ['nullable', 'string', 'max:120'],
        ]);

        $baseCode = $role->name . '_copy';
        $code = $baseCode;
        $suffix = 2;

        while (Role::where('name', $code)->where('guard_name', 'web')->exists()) {
            $code = $baseCode . '_' . $suffix++;
        }

        $clone = DB::transaction(function () use ($role, $validated, $code) {
            $clone = Role::create([
                'name' => $code,
                'guard_name' => 'web',
                'display_name' => $validated['display_name'] ?: $this->pageAccess->displayRoleName($role) . ' - Bản sao',
                'description' => $role->description,
                'is_system' => false,
                'page_access_enabled' => (bool) ($role->page_access_enabled ?? false),
            ]);

            $clone->syncPermissions($role->permissions()->pluck('name')->all());

            return $clone;
        });

        $this->flushPermissionCache();
        $this->audit('role.cloned', $clone, null, $this->roleSnapshot($clone->fresh('permissions')));

        return redirect()
            ->route('admin.role-permissions.index', ['role' => $clone->id])
            ->with('success', 'Đã sao chép vai trò.');
    }

    public function destroyRole(Role $role): RedirectResponse
    {
        $isSystem = (bool) ($role->is_system ?? false) || in_array($role->name, config('role_permissions.protected_roles', []), true);

        abort_if($isSystem, 422, 'Không thể xóa role hệ thống vì source đang tham chiếu trực tiếp tên role này.');

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

        return redirect()->route('admin.role-permissions.index')->with('success', 'Đã xóa vai trò.');
    }

    public function syncUserRoles(Request $request, User $user): RedirectResponse
    {
        $validated = $request->validate([
            'roles' => ['required', 'array', 'min:1'],
            'roles.*' => ['integer', 'exists:roles,id'],
        ]);

        $roles = Role::whereIn('id', $validated['roles'])->get();
        $before = [
            'roles' => $user->roles()->pluck('name')->all(),
        ];

        if ($user->is(auth()->user()) && $user->hasRole('admin') && !$roles->contains('name', 'admin')) {
            return back()->withErrors(['roles' => 'Bạn không thể tự gỡ role admin khỏi tài khoản đang đăng nhập.']);
        }

        $user->syncRoles($roles);
        $this->flushPermissionCache();
        $this->audit('user.roles_synced', $user, $before, [
            'roles' => $user->fresh()->roles()->pluck('name')->all(),
        ]);

        return back()->with('success', 'Đã cập nhật vai trò cho ' . $user->name . '.');
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
        $this->audit('user.direct_permissions_synced', $user, $before, [
            'direct_permissions' => $user->fresh()->permissions()->pluck('name')->all(),
        ]);

        return back()->with('success', 'Đã cập nhật quyền riêng cho ' . $user->name . '.');
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

    private function audit(string $action, $subject, ?array $before, ?array $after): void
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
        if (!Schema::hasTable('role_permission_audits')) {
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
