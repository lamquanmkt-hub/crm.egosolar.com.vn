<?php

declare(strict_types=1);

namespace App\Services\Workspace;

use App\Models\User;
use Illuminate\Support\Str;

/**
 * Nguồn sự thật duy nhất cho Workspace đang hoạt động.
 *
 * Workspace chỉ điều khiển ngữ cảnh hiển thị (Dashboard, Sidebar, App Center).
 * Quyền truy cập thật vẫn do role/permission/policy ở backend quyết định.
 */
final class WorkspaceContextService
{
    public const SESSION_KEY = 'ego.workspace.active.v1';

    /* Menu và ứng dụng được đọc từ config/ego_navigation.php. */

    /** @var array<int, array<string, array<string, mixed>>> */
    private array $availableCache = [];

    /** @var array<int, string> */
    private array $currentCache = [];

    public function __construct(
        private readonly WorkspaceProfileService $profiles,
        private readonly WorkspaceNavigationService $navigation,
        private readonly WorkspaceLevelService $levels,
    ) {
    }

    public function current(User $user): string
    {
        $userId = (int) $user->getKey();
        if (isset($this->currentCache[$userId])) {
            return $this->currentCache[$userId];
        }

        $stored = trim((string) session()->get(self::SESSION_KEY, ''));
        if ($stored !== '' && $this->canActivate($user, $stored)) {
            return $this->currentCache[$userId] = $stored;
        }

        $user->loadMissing(['roles', 'department', 'position']);

        $default = $this->default($user);
        session()->put(self::SESSION_KEY, $default);

        return $this->currentCache[$userId] = $default;
    }

    public function default(User $user): string
    {
        $available = array_keys($this->available($user));
        $user->loadMissing(['roles', 'department', 'position']);

        /*
         * Tài khoản kiêm nhiệm có thể mang nhiều role (ví dụ HR + kế toán + kho).
         * Workspace mặc định phải ưu tiên phòng ban thực tế thay vì role được đọc trước.
         */
        $departmentText = Str::of(implode(' ', [
            (string) ($user->department->code ?? ''),
            (string) ($user->department->name ?? ''),
        ]))
            ->ascii()
            ->lower()
            ->replace([' ', '-'], '_')
            ->value();

        $departmentWorkspace = match (true) {
            Str::contains($departmentText, ['hanh_chinh_nhan_su', 'phong_nhan_su', 'nhan_su', 'human_resources']) => 'hr',
            Str::contains($departmentText, ['ky_thuat', 'technical']) => 'technical',
            Str::contains($departmentText, ['kinh_doanh', 'sales']) => 'sales',
            Str::contains($departmentText, ['ke_toan', 'accounting', 'tai_chinh', 'finance']) => 'accounting',
            Str::contains($departmentText, ['kho_van', 'warehouse', 'kho']) => 'warehouse',
            Str::contains($departmentText, ['marketing']) => 'marketing',
            default => null,
        };

        if (is_string($departmentWorkspace) && in_array($departmentWorkspace, $available, true)) {
            return $departmentWorkspace;
        }

        $resolved = $this->profiles->resolveProfileKeys($user)[0] ?? 'general';

        if (in_array($resolved, $available, true)) {
            return $resolved;
        }

        return $available[0] ?? 'general';
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function available(User $user): array
    {
        $userId = (int) $user->getKey();
        if (isset($this->availableCache[$userId])) {
            return $this->availableCache[$userId];
        }

        $user->loadMissing(['roles', 'department', 'position']);
        $all = $this->profiles->profiles();

        if ($this->profiles->canManage($user)) {
            $isAdmin = $user->hasRole('admin');
            $executiveKey = $isAdmin && isset($all['admin']) ? 'admin' : 'management';
            $keys = array_values(array_unique(array_filter([
                $executiveKey,
                'technical',
                'sales',
                'accounting',
                'warehouse',
                'hr',
                'marketing',
            ])));
        } else {
            $keys = $this->profiles->resolveProfileKeys($user);
        }

        $available = [];
        foreach ($keys as $key) {
            if (isset($all[$key])) {
                $available[$key] = $all[$key];
            }
        }

        if ($available === [] && isset($all['general'])) {
            $available['general'] = $all['general'];
        }

        return $this->availableCache[$userId] = $available;
    }

    public function canActivate(User $user, string $workspace): bool
    {
        return array_key_exists($workspace, $this->available($user));
    }

    public function activate(User $user, string $workspace): string
    {
        abort_unless($this->canActivate($user, $workspace), 403, 'Bạn không được sử dụng Workspace này.');
        session()->put(self::SESSION_KEY, $workspace);
        $this->currentCache[(int) $user->getKey()] = $workspace;

        return $workspace;
    }

    public function reset(User $user): string
    {
        session()->forget(self::SESSION_KEY);
        unset($this->currentCache[(int) $user->getKey()]);

        return $this->current($user);
    }

    /**
     * @return list<array{key:string,label:string,icon:string,active:bool}>
     */
    public function options(User $user): array
    {
        $current = $this->current($user);

        return collect($this->available($user))
            ->map(fn (array $profile, string $key): array => [
                'key' => $key,
                'label' => in_array($key, ['admin', 'management'], true)
                    ? 'Ban Giám đốc'
                    : (string) ($profile['label'] ?? $key),
                'icon' => (string) ($profile['icon'] ?? 'bi-grid'),
                'active' => $key === $current,
            ])
            ->values()
            ->all();
    }

    public function label(User $user): string
    {
        return $this->labelFor($this->current($user));
    }

    public function labelFor(string $workspace): string
    {
        if (in_array($workspace, ['admin', 'management'], true)) {
            return 'Ban Giám đốc';
        }

        return (string) (config('ego_workspace.profiles.'.$workspace.'.label') ?? 'Nhân viên');
    }

    /**
     * Chuyển Workspace hiển thị sang mã Dashboard hiện có.
     */
    public function dashboardWorkspace(User $user): string
    {
        $active = $this->current($user);

        return match ($active) {
            'admin', 'management' => 'executive',
            'technical' => $this->isLeader($user, 'technical') ? 'technical_manager' : 'technical',
            'sales' => $this->isLeader($user, 'sales') ? 'sales_manager' : 'sales',
            'marketing' => $this->isLeader($user, 'marketing') ? 'marketing_manager' : 'marketing',
            'accounting' => 'finance',
            'warehouse' => 'warehouse',
            'hr' => 'hr',
            default => 'personal',
        };
    }

    /**
     * Danh sách ứng dụng được phép xuất hiện trong App Center theo Workspace.
     * Ban Giám đốc/Admin xem toàn bộ; các phòng ban chỉ thấy ứng dụng của phòng.
     *
     * @return list<string>
     */
    public function allowedAppIds(User $user): array
    {
        return $this->navigation->allowedAppIds($user, $this->current($user));
    }

    /**
     * @return list<string>
     */
    public function deniedMenuPermissions(User $user): array
    {
        return $this->navigation->deniedMenuPermissions($user, $this->current($user));
    }

    /** @return list<string> */
    public function capabilities(User $user): array
    {
        return $this->navigation->capabilities($user, $this->current($user));
    }

    public function accessLevel(User $user): string
    {
        return $this->levels->resolve($user);
    }

    public function dataScope(User $user): string
    {
        return $this->levels->scope($user);
    }

    private function isLeader(User $user, string $workspace): bool
    {
        return $this->levels->isDepartmentManager($user);
    }
}
