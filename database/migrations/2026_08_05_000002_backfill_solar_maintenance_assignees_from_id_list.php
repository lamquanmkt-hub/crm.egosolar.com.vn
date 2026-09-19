<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Chuẩn hoá 1NF: đưa `solar_maintenance_schedules.assigned_user_ids` (danh sách
 * id nhét trong MỘT cột) về bảng quan hệ `solar_maintenance_assignees`.
 *
 * Bảng quan hệ đã có sẵn và code mới đã ghi vào đó (`SolarMaintenanceService::
 * syncAssignees`), nhưng các lịch tạo TRƯỚC đợt nâng cấp chỉ còn dữ liệu ở cột
 * danh sách. Hệ quả: mọi truy vấn "lịch của tôi" buộc phải quét chuỗi bằng
 * `LIKE '%id%'` — vừa chậm vừa SAI (user 5 khớp nhầm 15/25/50).
 *
 * Migration này chỉ THÊM dòng còn thiếu vào bảng con, KHÔNG xoá cột cũ
 * (cột vẫn được ghi song song để bản deploy cũ không vỡ). Sau khi backfill,
 * bảng con là nguồn dữ liệu đúng và có index.
 *
 * An toàn: idempotent (bỏ qua cặp lịch–user đã có), không sửa dữ liệu sẵn có,
 * xử lý theo lô nên không giữ transaction dài.
 */
return new class extends Migration
{
    /** Số lịch xử lý mỗi lô. */
    private const CHUNK = 500;

    public function up(): void
    {
        if (! $this->tablesReady()) {
            return;
        }

        DB::table('solar_maintenance_schedules')
            ->select('id', 'assigned_user_ids', 'created_by')
            ->whereNotNull('assigned_user_ids')
            ->where('assigned_user_ids', '<>', '')
            ->where('assigned_user_ids', '<>', '[]')
            ->orderBy('id')
            ->chunk(self::CHUNK, function ($schedules): void {
                $rows = [];

                foreach ($schedules as $schedule) {
                    $userIds = $this->parseIds($schedule->assigned_user_ids);

                    foreach ($userIds as $index => $userId) {
                        $rows[] = [
                            'maintenance_schedule_id' => (int) $schedule->id,
                            'user_id' => $userId,
                            // Quy ước sẵn có: người đầu danh sách là trưởng nhóm.
                            'role' => $index === 0 ? 'leader' : 'member',
                            'assignment_role' => $index === 0 ? 'leader' : 'member',
                            'is_leader' => $index === 0 ? 1 : 0,
                            'assigned_by' => $schedule->created_by ?? null,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ];
                    }
                }

                $this->insertMissing($rows);
            });
    }

    /**
     * Không rollback dữ liệu: bảng con là nguồn đúng, xoá đi sẽ mất phân công.
     * Cột cũ vẫn còn nguyên nên `down()` không cần làm gì.
     */
    public function down(): void
    {
        // Cố ý để trống — xem PHPDoc.
    }

    /** Đủ bảng và cột để chạy backfill. */
    private function tablesReady(): bool
    {
        return Schema::hasTable('solar_maintenance_schedules')
            && Schema::hasTable('solar_maintenance_assignees')
            && Schema::hasColumn('solar_maintenance_schedules', 'assigned_user_ids');
    }

    /**
     * Đọc danh sách id từ chuỗi JSON hoặc CSV, bỏ trùng và giữ nguyên thứ tự.
     *
     * @return list<int>
     */
    private function parseIds(?string $raw): array
    {
        $raw = trim((string) $raw);

        if ($raw === '') {
            return [];
        }

        $decoded = json_decode($raw, true);
        $values = is_array($decoded) ? $decoded : explode(',', $raw);

        $ids = [];

        foreach ($values as $value) {
            $id = (int) trim((string) $value);

            if ($id > 0 && ! in_array($id, $ids, true)) {
                $ids[] = $id;
            }
        }

        return $ids;
    }

    /**
     * Chèn các dòng chưa có (theo cặp lịch–user), bỏ qua user không còn tồn tại.
     *
     * @param  list<array<string, mixed>>  $rows
     */
    private function insertMissing(array $rows): void
    {
        if ($rows === []) {
            return;
        }

        $scheduleIds = array_values(array_unique(array_column($rows, 'maintenance_schedule_id')));
        $userIds = array_values(array_unique(array_column($rows, 'user_id')));

        $existing = DB::table('solar_maintenance_assignees')
            ->whereIn('maintenance_schedule_id', $scheduleIds)
            ->get(['maintenance_schedule_id', 'user_id'])
            ->map(static fn ($row): string => $row->maintenance_schedule_id.':'.$row->user_id)
            ->flip();

        $knownUsers = DB::table('users')->whereIn('id', $userIds)->pluck('id')->flip();

        $insertable = array_values(array_filter(
            $rows,
            static fn (array $row): bool => isset($knownUsers[$row['user_id']])
                && ! isset($existing[$row['maintenance_schedule_id'].':'.$row['user_id']])
        ));

        foreach (array_chunk($insertable, self::CHUNK) as $batch) {
            // insertOrIgnore chứ KHÔNG insert: lọc theo $existing ở trên là kiểu
            // đọc-rồi-ghi, vẫn hở nếu có tiến trình khác chèn xen giữa hai bước
            // (UNIQUE maintenance_schedule_id+user_id sẽ ném lỗi). Để chính DB
            // bảo đảm idempotent là chắc chắn nhất.
            DB::table('solar_maintenance_assignees')->insertOrIgnore($batch);
        }
    }
};
