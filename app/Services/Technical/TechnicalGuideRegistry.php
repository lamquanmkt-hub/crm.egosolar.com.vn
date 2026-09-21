<?php

declare(strict_types=1);

namespace App\Services\Technical;

use App\Models\User;
use Illuminate\Support\Facades\Route;

/**
 * Registry các bài "Cách sử dụng" của module Kỹ thuật.
 *
 * Nguồn dữ liệu là `config/technical_guides.php` — NỘI DUNG TĨNH trong source,
 * không có bảng DB, không có migration. Lớp này chỉ làm ba việc:
 *   - trả về danh sách bài đã LỌC theo vai trò người dùng hiện tại,
 *   - trả về đúng một bài theo slug,
 *   - quy ra URL "quay lại trang đang sử dụng" AN TOÀN cho từng bài.
 *
 * Vai trò được quyết định bởi `TechnicalAccess` (không hardcode email):
 *   admin   -> $user->isAdmin()
 *   manager -> canManage() nhưng không phải admin
 *   staff   -> isScopedToSelf()
 */
final class TechnicalGuideRegistry
{
    public const ROLE_STAFF = 'staff';

    public const ROLE_MANAGER = 'manager';

    public const ROLE_ADMIN = 'admin';

    public function __construct(private readonly TechnicalAccess $access) {}

    /** Vai trò hướng dẫn của người dùng, hoặc null nếu không thuộc module Kỹ thuật. */
    public function roleFor(?User $user): ?string
    {
        if (! $this->access->canUseModule($user)) {
            return null;
        }

        if ($user !== null && $user->isAdmin()) {
            return self::ROLE_ADMIN;
        }

        return $this->access->canManage($user) ? self::ROLE_MANAGER : self::ROLE_STAFF;
    }

    /** @return array<int, array<string, mixed>> Toàn bộ bài, chưa lọc quyền. */
    public function all(): array
    {
        $guides = (array) config('technical_guides.guides', []);

        return array_values(array_filter($guides, static fn ($g) => is_array($g) && isset($g['slug'])));
    }

    /** @return array<string, array<string, mixed>> */
    public function groups(): array
    {
        return (array) config('technical_guides.groups', []);
    }

    /** @return array<string, mixed>|null */
    public function find(string $slug): ?array
    {
        foreach ($this->all() as $guide) {
            if ((string) $guide['slug'] === $slug) {
                return $guide;
            }
        }

        return null;
    }

    /** Bài này có cho vai trò `$role` xem không? */
    public function isVisibleTo(array $guide, ?string $role): bool
    {
        if ($role === null) {
            return false;
        }

        return in_array($role, (array) ($guide['roles'] ?? []), true);
    }

    /**
     * Danh sách bài mà `$role` được xem, giữ nguyên thứ tự khai báo.
     *
     * @return array<int, array<string, mixed>>
     */
    public function visibleFor(?string $role): array
    {
        return array_values(array_filter(
            $this->all(),
            fn (array $guide): bool => $this->isVisibleTo($guide, $role)
        ));
    }

    /**
     * Bài đã nhóm theo `group`, chỉ gồm nhóm còn bài sau khi lọc quyền.
     *
     * @return array<int, array{key:string, label:string, description:string, icon:string, guides:array<int, array<string, mixed>>}>
     */
    public function groupedFor(?string $role): array
    {
        $visible = $this->visibleFor($role);
        $result = [];

        foreach ($this->groups() as $key => $meta) {
            $guides = array_values(array_filter(
                $visible,
                static fn (array $g): bool => (string) ($g['group'] ?? '') === (string) $key
            ));

            if ($guides === []) {
                continue;
            }

            $result[] = [
                'key' => (string) $key,
                'label' => (string) ($meta['label'] ?? $key),
                'description' => (string) ($meta['description'] ?? ''),
                'icon' => (string) ($meta['icon'] ?? 'bi-book'),
                'guides' => $guides,
            ];
        }

        return $result;
    }

    /**
     * Bài kế tiếp trong CÙNG nhóm mà `$role` cũng được xem (null nếu là bài cuối).
     *
     * @return array<string, mixed>|null
     */
    public function nextInGroup(array $guide, ?string $role): ?array
    {
        $group = (string) ($guide['group'] ?? '');
        $siblings = array_values(array_filter(
            $this->visibleFor($role),
            static fn (array $g): bool => (string) ($g['group'] ?? '') === $group
        ));

        foreach ($siblings as $index => $sibling) {
            if ((string) $sibling['slug'] === (string) $guide['slug']) {
                return $siblings[$index + 1] ?? null;
            }
        }

        return null;
    }

    /** URL trang nghiệp vụ mặc định của bài (fallback cho nút "Quay lại"). */
    public function routeHintUrl(array $guide): string
    {
        $name = (string) ($guide['route_hint'] ?? '');
        $params = (array) ($guide['route_hint_params'] ?? []);

        if ($name !== '' && Route::has($name)) {
            return route($name, $params);
        }

        return route('technical.guides.index');
    }

    /**
     * Chỉ chấp nhận đường dẫn NỘI BỘ cho tham số `?return=`.
     *
     * Chống open-redirect: phải bắt đầu bằng đúng MỘT dấu `/`, không được là
     * URL protocol-relative (`//evil.com`), không có scheme (`https://`,
     * `javascript:`…) và không có backslash (một số trình duyệt coi `\` như `/`).
     */
    public function safeReturnUrl(?string $return, array $guide): string
    {
        $candidate = trim((string) $return);

        if ($candidate === '' || ! str_starts_with($candidate, '/')) {
            return $this->routeHintUrl($guide);
        }

        if (str_starts_with($candidate, '//') || str_starts_with($candidate, '/\\')) {
            return $this->routeHintUrl($guide);
        }

        if (str_contains($candidate, '\\') || preg_match('#^/[^/]*:#', $candidate) === 1) {
            return $this->routeHintUrl($guide);
        }

        // Không cho ký tự điều khiển / xuống dòng lọt vào thuộc tính href.
        if (preg_match('/[\x00-\x1F\x7F]/', $candidate) === 1) {
            return $this->routeHintUrl($guide);
        }

        return $candidate;
    }
}
