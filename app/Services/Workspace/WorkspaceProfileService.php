<?php

declare(strict_types=1);

namespace App\Services\Workspace;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Throwable;

final class WorkspaceProfileService
{
    /** @var array<string, array<string, mixed>>|null */
    private ?array $profilesCache = null;

    public function profiles(): array
    {
        if ($this->profilesCache !== null) {
            return $this->profilesCache;
        }

        $defaults = config('ego_workspace.profiles', []);
        $stored = $this->storedProfiles();
        $profiles = [];

        foreach ($defaults as $key => $profile) {
            $override = is_array($stored[$key] ?? null) ? $stored[$key] : [];
            $navigationDefaults = config('ego_navigation.workspaces.'.$key, []);
            $defaultApps = is_array($navigationDefaults['apps'] ?? null)
                ? $navigationDefaults['apps']
                : ($profile['apps'] ?? []);
            $defaultFeatured = is_array($navigationDefaults['featured'] ?? null)
                ? $navigationDefaults['featured']
                : ($profile['featured'] ?? []);
            $apps = $this->normalizeIds($override['apps'] ?? $defaultApps, true);

            $profiles[$key] = array_merge($profile, [
                'apps' => $this->ensureRequiredApps($apps),
                'featured' => array_slice($this->normalizeIds($override['featured'] ?? $defaultFeatured), 0, 3),
                'default_category' => $this->validCategory((string) ($override['default_category'] ?? ($profile['default_category'] ?? 'all'))),
            ]);
        }

        return $this->profilesCache = $profiles;
    }

    public function profile(string $key): array
    {
        $profiles = $this->profiles();

        return $profiles[$key] ?? $profiles['general'] ?? [
            'label' => 'Nhân viên',
            'apps' => [],
            'featured' => [],
            'default_category' => 'all',
        ];
    }

    public function resolveProfileKeys(User $user): array
    {
        $profiles = $this->profiles();
        $priority = config('ego_workspace.profile_priority', array_keys($profiles));
        $roleNames = $user->roles->pluck('name')->map(fn ($value): string => (string) $value)->all();
        $departmentCode = $this->normalize((string) ($user->department->code ?? ''));
        $departmentName = $this->normalize((string) ($user->department->name ?? ''));
        $matched = [];

        // Ưu tiên role chính xác. Chỉ dùng phòng ban làm fallback khi tài khoản
        // chưa được gán role nghiệp vụ, tránh phòng "Marketing & Sales" bị cộng
        // đồng thời cả profile Sales và Marketing.
        foreach ($priority as $key) {
            if (! isset($profiles[$key]) || $key === 'general') {
                continue;
            }

            $profileRoles = array_map('strval', $profiles[$key]['role_names'] ?? []);
            if (array_intersect($roleNames, $profileRoles) !== []) {
                $matched[] = $key;
            }
        }

        if ($matched === []) {
            foreach ($priority as $key) {
                if (! isset($profiles[$key]) || $key === 'general') {
                    continue;
                }

                $profile = $profiles[$key];
                $profileCodes = array_map(fn ($value): string => $this->normalize((string) $value), $profile['department_codes'] ?? []);
                $profileNames = array_map(fn ($value): string => $this->normalize((string) $value), $profile['department_names'] ?? []);

                if (($departmentCode !== '' && in_array($departmentCode, $profileCodes, true))
                    || ($departmentName !== '' && in_array($departmentName, $profileNames, true))) {
                    $matched[] = $key;
                }
            }
        }

        if (in_array('admin', $matched, true)) {
            return ['admin'];
        }

        if (in_array('management', $matched, true)) {
            return ['management'];
        }

        return array_values(array_unique($matched !== [] ? $matched : ['general']));
    }

    public function primaryProfileKey(User $user): string
    {
        return $this->resolveProfileKeys($user)[0] ?? 'general';
    }

    public function allowedAppIds(array $profileKeys): array
    {
        $profiles = $this->profiles();
        $allAppIds = collect(config('ego_workspace.apps', []))->pluck('id')->map(fn ($id): string => (string) $id)->all();
        $allowed = [];

        foreach ($profileKeys as $key) {
            $profileApps = $profiles[$key]['apps'] ?? [];

            if (in_array('*', $profileApps, true)) {
                return $allAppIds;
            }

            $allowed = array_merge($allowed, $profileApps);
        }

        $allowed = array_merge($allowed, $this->requiredAppIds());

        return array_values(array_unique(array_intersect($allAppIds, $allowed)));
    }

    public function featuredAppIds(array $profileKeys): array
    {
        $profiles = $this->profiles();
        $featured = [];

        foreach ($profileKeys as $key) {
            $featured = array_merge($featured, $profiles[$key]['featured'] ?? []);
        }

        return array_slice(array_values(array_unique($featured)), 0, 3);
    }

    public function defaultCategory(array $profileKeys): string
    {
        $profiles = $this->profiles();

        foreach ($profileKeys as $key) {
            $category = (string) ($profiles[$key]['default_category'] ?? 'all');
            if ($category !== 'all') {
                return $this->validCategory($category);
            }
        }

        return 'all';
    }

    public function canManage(User $user): bool
    {
        return $user->hasAnyRole(['admin', 'management', 'manager', 'director', 'ceo']);
    }

    public function save(array $input): void
    {
        if (! Schema::hasTable('ego_system_settings')) {
            throw new \RuntimeException('Bảng ego_system_settings chưa tồn tại.');
        }

        $defaults = config('ego_workspace.profiles', []);
        $allAppIds = collect(config('ego_workspace.apps', []))->pluck('id')->map(fn ($id): string => (string) $id)->all();
        $payload = [];

        foreach ($defaults as $key => $profile) {
            $submitted = is_array($input[$key] ?? null) ? $input[$key] : [];
            $apps = $this->normalizeIds($submitted['apps'] ?? []);
            $featured = array_slice($this->normalizeIds($submitted['featured'] ?? []), 0, 3);

            $apps = array_values(array_intersect($allAppIds, $apps));
            $apps = $this->ensureRequiredApps($apps);

            $featured = array_values(array_intersect($allAppIds, $featured));
            $featured = array_values(array_intersect($apps === ['*'] ? $allAppIds : $apps, $featured));

            $payload[$key] = [
                'apps' => $apps,
                'featured' => $featured,
                'default_category' => $this->validCategory((string) ($submitted['default_category'] ?? 'all')),
            ];
        }

        $this->storeProfiles($payload);
        $this->profilesCache = null;
    }

    public function reset(): void
    {
        if (! Schema::hasTable('ego_system_settings')) {
            return;
        }

        DB::table('ego_system_settings')
            ->where('key', (string) config('ego_workspace.setting_key'))
            ->delete();

        $this->profilesCache = null;
    }

    private function storedProfiles(): array
    {
        try {
            if (! Schema::hasTable('ego_system_settings')) {
                return [];
            }

            $value = DB::table('ego_system_settings')
                ->where('key', (string) config('ego_workspace.setting_key'))
                ->value('value');

            if (! is_string($value) || trim($value) === '') {
                return [];
            }

            $decoded = json_decode($value, true, 512, JSON_THROW_ON_ERROR);

            return is_array($decoded) ? $decoded : [];
        } catch (Throwable) {
            return [];
        }
    }

    private function storeProfiles(array $payload): void
    {
        $key = (string) config('ego_workspace.setting_key');
        $value = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        $exists = DB::table('ego_system_settings')->where('key', $key)->exists();

        if ($exists) {
            DB::table('ego_system_settings')->where('key', $key)->update([
                'value' => $value,
                'updated_at' => now(),
            ]);
        } else {
            DB::table('ego_system_settings')->insert([
                'key' => $key,
                'value' => $value,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    private function normalizeIds(mixed $values, bool $allowWildcard = false): array
    {
        if (! is_array($values)) {
            return [];
        }

        $normalized = collect($values)
            ->map(fn ($value): string => trim((string) $value))
            ->filter(fn (string $value): bool => $value !== '' && ($allowWildcard || $value !== '*'))
            ->unique()
            ->values()
            ->all();

        if ($allowWildcard && in_array('*', $values, true)) {
            return ['*'];
        }

        return $normalized;
    }

    private function requiredAppIds(): array
    {
        $allAppIds = collect(config('ego_workspace.apps', []))
            ->pluck('id')
            ->map(fn ($id): string => (string) $id)
            ->all();

        return array_values(array_unique(array_intersect(
            $allAppIds,
            $this->normalizeIds(config('ego_workspace.required_apps', []))
        )));
    }

    private function ensureRequiredApps(array $apps): array
    {
        if (in_array('*', $apps, true)) {
            return ['*'];
        }

        return array_values(array_unique(array_merge($apps, $this->requiredAppIds())));
    }

    private function validCategory(string $category): string
    {
        return array_key_exists($category, config('ego_workspace.categories', [])) ? $category : 'all';
    }

    private function normalize(string $value): string
    {
        return Str::of($value)->ascii()->lower()->replace(['_', '-'], ' ')->squish()->value();
    }
}
