<?php

declare(strict_types=1);

namespace App\Services\Workspace;

use App\Models\User;
use Illuminate\Support\Str;

final class WorkspaceLevelService
{
    public function resolve(User $user): string
    {
        $user->loadMissing(['roles', 'position']);

        $roleLevels = config('ego_navigation.role_levels', []);
        $roleNames = $user->roles
            ->pluck('name')
            ->map(fn ($name): string => Str::lower(trim((string) $name)))
            ->values();

        foreach (['director', 'department_head', 'team_lead', 'staff'] as $level) {
            foreach ($roleNames as $roleName) {
                if (($roleLevels[$roleName] ?? null) === $level) {
                    return $level;
                }
            }
        }

        $positionText = $this->normalize(implode(' ', [
            (string) ($user->position->code ?? ''),
            (string) ($user->position->name ?? ''),
        ]));

        foreach (config('ego_navigation.position_rules', []) as $level => $patterns) {
            foreach ((array) $patterns as $pattern) {
                if ($positionText !== '' && Str::contains($positionText, $this->normalize((string) $pattern))) {
                    return (string) $level;
                }
            }
        }

        return 'staff';
    }

    public function definition(User|string $userOrLevel): array
    {
        $level = $userOrLevel instanceof User ? $this->resolve($userOrLevel) : $userOrLevel;

        return config('ego_navigation.levels.'.$level, config('ego_navigation.levels.staff', [
            'label' => 'Nhân viên',
            'rank' => 100,
            'scope' => 'own',
            'icon' => 'bi-person-fill',
        ]));
    }

    public function label(User|string $userOrLevel): string
    {
        return (string) ($this->definition($userOrLevel)['label'] ?? 'Nhân viên');
    }

    public function scope(User|string $userOrLevel): string
    {
        return (string) ($this->definition($userOrLevel)['scope'] ?? 'own');
    }

    public function rank(User|string $userOrLevel): int
    {
        return (int) ($this->definition($userOrLevel)['rank'] ?? 100);
    }

    public function isAtLeast(User $user, string $level): bool
    {
        return $this->rank($user) >= $this->rank($level);
    }

    public function isDepartmentManager(User $user): bool
    {
        return $this->isAtLeast($user, 'department_head');
    }

    private function normalize(string $value): string
    {
        return Str::of($value)
            ->ascii()
            ->lower()
            ->replace([' ', '-'], '_')
            ->replaceMatches('/_+/', '_')
            ->trim('_')
            ->value();
    }
}
