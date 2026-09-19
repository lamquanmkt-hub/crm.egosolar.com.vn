<?php

declare(strict_types=1);

namespace App\Services\Workspace;

use App\Models\User;

final class WorkspaceNavigationService
{
    public function __construct(
        private readonly WorkspaceProfileService $profiles,
        private readonly WorkspaceLevelService $levels,
    ) {
    }

    /** @return list<string> */
    public function allowedAppIds(User $user, string $workspace): array
    {
        $allAppIds = $this->allAppIds();

        if (in_array($workspace, ['admin', 'management'], true)) {
            return $allAppIds;
        }

        /*
         * Workspace đang chọn là nguồn chính. Profile cũ chỉ dùng fallback.
         * Cách này giúp Admin chuyển sang Kỹ thuật/Kho/Sales vẫn nhìn đúng
         * ứng dụng và đúng thứ tự của phòng ban đó.
         */
        $workspaceDefaults = array_map('strval', (array) config(
            'ego_navigation.workspaces.'.$workspace.'.apps',
            []
        ));

        $profileApps = $this->profiles->profile($workspace)['apps'] ?? [];
        $profileApps = $profileApps === ['*']
            ? $allAppIds
            : array_map('strval', (array) $profileApps);

        $configuredApps = $workspaceDefaults !== [] ? $workspaceDefaults : $profileApps;
        $shared = array_map('strval', (array) config('ego_navigation.shared_apps', []));
        $levelRule = $this->levelRule($user, $workspace);
        $add = array_map('strval', (array) ($levelRule['add_apps'] ?? []));
        $remove = array_map('strval', (array) ($levelRule['remove_apps'] ?? []));

        $ordered = array_values(array_unique(array_merge($configuredApps, $shared, $add)));

        return array_values(array_filter(
            $ordered,
            static fn (string $appId): bool => in_array($appId, $allAppIds, true)
                && ! in_array($appId, $remove, true)
        ));
    }

    /** @return list<string> */
    public function allowedMenuPermissions(User $user, string $workspace): array
    {
        $all = array_keys(config('role_permissions.menu_permissions', []));

        if (in_array($workspace, ['admin', 'management'], true)) {
            return array_values($all);
        }

        $configured = (array) config('ego_navigation.workspaces.'.$workspace.'.menu_permissions', []);
        if (in_array('*', $configured, true)) {
            return array_values($all);
        }

        $shared = (array) config('ego_navigation.shared_menu_permissions', []);
        $levelRule = $this->levelRule($user, $workspace);
        $add = (array) ($levelRule['add_menu_permissions'] ?? []);
        $remove = (array) ($levelRule['remove_menu_permissions'] ?? []);

        return array_values(array_unique(array_diff(
            array_intersect($all, array_merge($configured, $shared, $add)),
            $remove
        )));
    }

    /** @return list<string> */
    public function deniedMenuPermissions(User $user, string $workspace): array
    {
        return array_values(array_diff(
            array_keys(config('role_permissions.menu_permissions', [])),
            $this->allowedMenuPermissions($user, $workspace)
        ));
    }

    /** @return list<string> */
    public function requiredPagePermissions(User $user, string $workspace): array
    {
        $appIndex = collect(config('ego_workspace.apps', []))->keyBy('id');
        $menuDefinitions = config('role_permissions.menu_permissions', []);
        $permissions = [];

        foreach ($this->allowedAppIds($user, $workspace) as $appId) {
            $app = $appIndex->get($appId);
            $permission = is_array($app) ? ($app['page_permission'] ?? null) : null;
            if (is_string($permission) && $permission !== '') {
                $permissions[] = $permission;
            }
        }

        foreach ($this->allowedMenuPermissions($user, $workspace) as $menuPermission) {
            $pagePermission = $menuDefinitions[$menuPermission]['page_permission'] ?? null;
            if (is_string($pagePermission) && $pagePermission !== '') {
                $permissions[] = $pagePermission;
            }
        }

        return array_values(array_unique($permissions));
    }

    /** @return list<string> */
    public function capabilities(User $user, string $workspace): array
    {
        return array_values(array_unique(array_map(
            'strval',
            (array) ($this->levelRule($user, $workspace)['capabilities'] ?? [])
        )));
    }

    public function summary(string $workspace): array
    {
        $definition = (array) config('ego_navigation.workspaces.'.$workspace, []);

        return [
            'key' => $workspace,
            'label' => (string) ($definition['label'] ?? $workspace),
            'apps' => array_values(array_map('strval', (array) ($definition['apps'] ?? []))),
            'menu_permissions' => array_values(array_map('strval', (array) ($definition['menu_permissions'] ?? []))),
            'level_rules' => (array) ($definition['level_rules'] ?? []),
        ];
    }

    private function levelRule(User $user, string $workspace): array
    {
        $level = $this->levels->resolve($user);
        $rules = (array) config('ego_navigation.workspaces.'.$workspace.'.level_rules', []);

        return (array) ($rules[$level] ?? []);
    }

    /** @return list<string> */
    private function allAppIds(): array
    {
        return collect(config('ego_workspace.apps', []))
            ->pluck('id')
            ->map(fn ($id): string => (string) $id)
            ->filter()
            ->unique()
            ->values()
            ->all();
    }
}
