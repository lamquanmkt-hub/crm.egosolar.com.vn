<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\AI\AiAccessService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

final class AiGovernanceController extends Controller
{
    public function index(Request $request, AiAccessService $access): View
    {
        $user = $request->user();
        abort_unless($access->isAdmin($user) || $user->can('ai.audit.view') || $user->can('ai.providers.manage'), 403);

        $aiPermissions = Permission::query()
            ->where('guard_name', 'web')
            ->where('name', 'like', 'ai.%')
            ->orderBy('name')
            ->get();

        $roles = Role::query()
            ->where('guard_name', 'web')
            ->with(['permissions' => fn ($query) => $query->where('name', 'like', 'ai.%')])
            ->orderByRaw("FIELD(name, 'admin','management','accounting','warehouse','sales_manager','sales','technical_manager','ky_thuat','marketing_manager','marketing','hr')")
            ->orderBy('name')
            ->get();

        $toolLogs = collect();
        if (Schema::hasTable('ai_tool_logs')) {
            $toolLogs = DB::table('ai_tool_logs as l')
                ->leftJoin('users as u', 'u.id', '=', 'l.user_id')
                ->select(['l.*', 'u.name as user_name', 'u.email as user_email'])
                ->orderByDesc('l.id')
                ->limit(100)
                ->get();
        }

        $usageSummary = Schema::hasTable('ai_usage_logs')
            ? DB::table('ai_usage_logs')
                ->whereDate('created_at', '>=', now()->subDays(30)->toDateString())
                ->selectRaw('COUNT(*) requests, SUM(total_tokens) total_tokens, SUM(status = "error") errors, ROUND(AVG(latency_ms)) avg_latency')
                ->first()
            : null;

        $moduleDefinitions = (array) config('ego_ai.modules', []);

        return view('admin.settings.ai-governance', compact(
            'roles',
            'aiPermissions',
            'toolLogs',
            'usageSummary',
            'moduleDefinitions'
        ));
    }

    public function updateRole(Request $request, Role $role): RedirectResponse
    {
        abort_unless($request->user()->hasRole('admin') || $request->user()->can('ai.providers.manage'), 403);

        $all = array_values((array) config('ego_ai.all_permissions', []));
        $validated = $request->validate([
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['string', 'in:'.implode(',', $all)],
        ]);

        $selected = array_values(array_unique((array) ($validated['permissions'] ?? [])));
        if ($role->name === 'admin') {
            $selected = $all;
        }

        DB::transaction(function () use ($role, $all, $selected): void {
            $other = $role->permissions()
                ->whereNotIn('name', $all)
                ->pluck('name')
                ->all();

            $role->syncPermissions(array_values(array_unique(array_merge($other, $selected))));
        });

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return back()->with('success', 'Đã cập nhật quyền AI cho vai trò '.$role->name.'.');
    }

    public function applyDefaults(Request $request): RedirectResponse
    {
        abort_unless($request->user()->hasRole('admin'), 403);

        $all = array_values((array) config('ego_ai.all_permissions', []));
        foreach ((array) config('ego_ai.role_permission_matrix', []) as $roleName => $permissionNames) {
            $role = Role::query()->where('name', $roleName)->where('guard_name', 'web')->first();
            if (! $role) {
                continue;
            }

            $selected = in_array('*', (array) $permissionNames, true) ? $all : (array) $permissionNames;
            $other = $role->permissions()->whereNotIn('name', $all)->pluck('name')->all();
            $role->syncPermissions(array_values(array_unique(array_merge($other, $selected))));
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return back()->with('success', 'Đã áp dụng lại ma trận quyền AI mặc định theo role.');
    }
}
