<?php

declare(strict_types=1);

namespace App\Services\Technical;

use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * "Nhân viên trong phạm vi quản lý" của trưởng phòng Kỹ thuật.
 *
 * GIẢ ĐỊNH (đã kiểm chứng trên schema thật):
 * - Bảng `users` KHÔNG có cột `company_id`, và bảng `departments` hiện KHÔNG có
 *   dòng nào => không tồn tại cây tổ chức đủ dùng để suy ra "đội của tôi".
 * - Vì vậy phạm vi quản lý = MỌI nhân sự kỹ thuật đang hoạt động, trong phạm vi
 *   công ty đang làm việc (company scope được áp ở tầng dữ liệu: kế hoạch, báo
 *   cáo và feed công việc đều lọc theo `company_id`). Admin/Giám đốc và các role
 *   quản lý chung không phải là "nhân viên kỹ thuật" nên không được trộn vào
 *   danh sách chỉ vì họ có quyền xem/quản trị module.
 * - Khi hệ thống có dữ liệu phòng ban thật, chỉ cần bổ sung điều kiện
 *   `department_id` ở đây; mọi nơi khác gọi qua service này nên không phải sửa.
 *
 * Nhận diện nhân sự kỹ thuật bằng ROLE có sẵn (config/technical.php) hoặc tên
 * phòng ban/chức danh chứa từ khoá kỹ thuật — KHÔNG hardcode email.
 */
class TechnicalTeamService
{
    /**
     * Danh sách nhân sự kỹ thuật (id, name) — MỘT truy vấn, đã sắp xếp.
     *
     * @return Collection<int, object>
     */
    public function members(): Collection
    {
        $query = User::query()
            ->select('users.id', 'users.name')
            ->when(
                Schema::hasColumn('users', 'is_active'),
                fn ($q) => $q->where(function ($inner): void {
                    $inner->whereNull('users.is_active')->orWhere('users.is_active', 1);
                }),
            )
            ->where(function ($outer): void {
                $this->applyTechnicalRoleCondition($outer);
                $this->applyTechnicalOrganisationCondition($outer);
            })
            ->orderBy('users.name');

        return $query->get();
    }

    /** @return array<int, int> */
    public function memberIds(): array
    {
        return $this->members()->pluck('id')->map(fn ($id): int => (int) $id)->all();
    }

    /** Trưởng phòng có được xem dữ liệu của `$userId` hay không. */
    public function manages(int $userId): bool
    {
        return in_array($userId, $this->memberIds(), true);
    }

    /** Nhân sự kỹ thuật theo id — dùng để hiển thị tên mà không N+1. */
    public function membersById(): Collection
    {
        return $this->members()->keyBy(fn (object $row): int => (int) $row->id);
    }

    private function applyTechnicalRoleCondition($query): void
    {
        $rolesTable = config('permission.table_names.roles', 'roles');
        $modelHasRoles = config('permission.table_names.model_has_roles', 'model_has_roles');

        if (! Schema::hasTable($rolesTable) || ! Schema::hasTable($modelHasRoles)) {
            return;
        }

        $roleNames = array_values(array_unique(
            (array) config('technical.staff_roles', []),
        ));

        if ($roleNames === []) {
            return;
        }

        $query->orWhereExists(function ($sub) use ($rolesTable, $modelHasRoles, $roleNames): void {
            $sub->selectRaw('1')
                ->from($modelHasRoles.' as mhr')
                ->join($rolesTable.' as r', 'r.id', '=', 'mhr.role_id')
                ->whereColumn('mhr.model_id', 'users.id')
                ->where('mhr.model_type', User::class)
                ->whereIn('r.name', $roleNames);
        });
    }

    /** Tên phòng ban / chức danh chứa từ khoá kỹ thuật. */
    private function applyTechnicalOrganisationCondition($query): void
    {
        foreach ([['departments', 'department_id'], ['positions', 'position_id']] as [$table, $column]) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn('users', $column)) {
                continue;
            }

            $query->orWhereExists(function ($sub) use ($table, $column): void {
                $sub->selectRaw('1')
                    ->from($table.' as o')
                    ->whereColumn('o.id', 'users.'.$column)
                    ->where(function ($inner): void {
                        foreach ((array) config('technical.department_keywords', []) as $keyword) {
                            $needle = '%'.str_replace('_', '%', (string) $keyword).'%';
                            $inner->orWhere('o.name', 'like', $needle)
                                ->orWhere('o.code', 'like', $needle);
                        }
                    });
            });
        }

        // Không có bảng phòng ban thì điều kiện này đơn giản là không được thêm;
        // danh sách khi đó hoàn toàn dựa vào role — vẫn đúng và không rỗng.
        unset($query);
    }

    /** Tổng số nhân sự kỹ thuật — đếm ở DB, không tải collection. */
    public function memberCount(): int
    {
        return $this->members()->count();
    }

    /** Hàm trợ giúp: ép một danh sách id về đúng phạm vi quản lý. */
    public function filterToScope(array $userIds): array
    {
        $allowed = $this->memberIds();

        return array_values(array_intersect(
            array_map('intval', $userIds),
            $allowed,
        ));
    }

    /** Bảng tra id => tên, lấy thẳng từ users (dùng khi hiển thị nhật ký). */
    public function nameMap(array $userIds): array
    {
        $userIds = array_values(array_unique(array_map('intval', $userIds)));

        if ($userIds === []) {
            return [];
        }

        return DB::table('users')
            ->whereIn('id', $userIds)
            ->pluck('name', 'id')
            ->map(fn ($name): string => (string) $name)
            ->all();
    }
}
