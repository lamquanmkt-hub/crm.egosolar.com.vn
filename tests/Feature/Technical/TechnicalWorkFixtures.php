<?php

declare(strict_types=1);

namespace Tests\Feature\Technical;

use App\Models\User;
use App\Support\EgoCompanyLock;
use App\Support\Technical\TechnicalWorkItem;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Dữ liệu dựng sẵn cho test module Kỹ thuật.
 *
 * Toàn bộ dòng được chèn bằng query builder (không qua Model) để tránh kích
 * hoạt observer/global scope của module Công trình — test chỉ cần dữ liệu
 * nguồn, không chạy nghiệp vụ Công trình.
 *
 * Mọi bản ghi nằm trong DatabaseTransactions nên tự rollback sau mỗi test.
 */
trait TechnicalWorkFixtures
{
    protected function companyId(): int
    {
        return EgoCompanyLock::id();
    }

    protected function technician(array $attributes = []): User
    {
        return $this->userWithRole('ky_thuat', $attributes);
    }

    protected function technicalManager(array $attributes = []): User
    {
        return $this->userWithRole('technical_manager', $attributes);
    }

    protected function admin(array $attributes = []): User
    {
        return $this->userWithRole('admin', $attributes);
    }

    /** Người ngoài phòng Kỹ thuật — dùng để kiểm chứng chặn truy cập. */
    protected function outsider(array $attributes = []): User
    {
        return $this->userWithRole('sales', $attributes);
    }

    protected function makeSite(?int $companyId = null, array $overrides = []): int
    {
        return (int) DB::table('sites')->insertGetId(array_merge([
            'name' => 'Công trình test '.Str::upper(Str::random(6)),
            'project_code' => 'CT-'.Str::upper(Str::random(6)),
            'company_id' => $companyId ?? $this->companyId(),
            'address' => 'Địa chỉ test',
            'created_at' => now(),
            'updated_at' => now(),
        ], $overrides));
    }

    /**
     * Nguồn 1: một bước quy trình Công trình + phân công cho `$userId`.
     *
     * @return array{step_id:int, assignment_id:int}
     */
    protected function makeWorkflowAssignment(int $siteId, int $userId, array $stepOverrides = [], array $assignmentOverrides = []): array
    {
        $stepId = (int) DB::table('project_workflow_steps')->insertGetId(array_merge([
            'site_id' => $siteId,
            'step_code' => 'construction',
            'sequence' => 5,
            'status' => 'in_progress',
            'due_at' => Carbon::today()->addDays(3)->setTime(17, 0),
            'requirement' => 'Thi công theo phương án đã duyệt',
            'started_at' => Carbon::today(),
            'created_at' => now(),
            'updated_at' => now(),
        ], $stepOverrides));

        $assignmentId = (int) DB::table('project_workflow_assignments')->insertGetId(array_merge([
            'workflow_step_id' => $stepId,
            'user_id' => $userId,
            'assignment_role' => 'primary',
            'status' => 'in_progress',
            'progress_percent' => 40,
            'is_active' => 1,
            'started_at' => Carbon::today(),
            'created_at' => now(),
            'updated_at' => now(),
        ], $assignmentOverrides));

        return ['step_id' => $stepId, 'assignment_id' => $assignmentId];
    }

    /** Nguồn 2: task nội bộ gắn công trình. */
    protected function makeTask(int $siteId, int $userId, array $overrides = []): int
    {
        return (int) DB::table('tasks')->insertGetId(array_merge([
            'title' => 'Task kỹ thuật '.Str::upper(Str::random(5)),
            'description' => 'Mô tả task test',
            'site_id' => $siteId,
            'company_id' => $this->companyId(),
            'assignee_id' => $userId,
            'status' => 'in_progress',
            'priority' => 'normal',
            'progress_percent' => 20,
            'due_at' => Carbon::today()->addDay()->setTime(17, 0),
            'created_at' => now(),
            'updated_at' => now(),
        ], $overrides));
    }

    /** Nguồn 3: lịch bảo trì / bảo hành + người được phân công. */
    protected function makeMaintenance(int $siteId, int $userId, array $overrides = []): int
    {
        $scheduleId = (int) DB::table('solar_maintenance_schedules')->insertGetId(array_merge([
            'schedule_code' => 'BT-'.Str::upper(Str::random(6)),
            'site_id' => $siteId,
            'company_id' => $this->companyId(),
            'type' => 'periodic',
            'status' => 'assigned',
            'priority' => 'normal',
            'scheduled_date' => Carbon::today()->toDateString(),
            'issue_note' => 'Kiểm tra định kỳ',
            'created_at' => now(),
            'updated_at' => now(),
        ], $overrides));

        DB::table('solar_maintenance_assignees')->insert([
            'maintenance_schedule_id' => $scheduleId,
            'user_id' => $userId,
            'role' => 'member',
            'assignment_role' => 'member',
            'is_leader' => 0,
            'assigned_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $scheduleId;
    }

    /** Một đầu việc QUÁ HẠN: hạn ở quá khứ và trạng thái còn đang làm. */
    protected function makeOverdueWorkflowAssignment(int $siteId, int $userId): array
    {
        return $this->makeWorkflowAssignment($siteId, $userId, [
            'due_at' => Carbon::today()->subDays(5)->setTime(17, 0),
            'status' => 'in_progress',
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Giai đoạn 2 — kế hoạch tuần
    |--------------------------------------------------------------------------
    */

    /** Thứ Hai của tuần hiện tại — chuẩn dùng chung cho mọi test kế hoạch. */
    protected function currentWeekStart(): Carbon
    {
        return Carbon::today()->startOfWeek(Carbon::MONDAY)->startOfDay();
    }

    /** Tạo kế hoạch tuần trống cho `$owner`. */
    protected function makeWeekPlan(User $owner, ?Carbon $weekStart = null, array $overrides = []): int
    {
        $weekStart ??= $this->currentWeekStart();

        return (int) DB::table('technical_week_plans')->insertGetId(array_merge([
            'company_id' => $this->companyId(),
            'user_id' => $owner->id,
            'user_name' => $owner->name,
            'week_start' => $weekStart->toDateString(),
            'week_end' => $weekStart->copy()->addDays(6)->toDateString(),
            'status' => 'draft',
            'update_requested' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ], $overrides));
    }

    /** Tạo một dòng kế hoạch trực tiếp trong DB (bỏ qua HTTP). */
    protected function makePlanItem(User $owner, int $weekPlanId, array $overrides = []): int
    {
        return (int) DB::table('technical_plan_items')->insertGetId(array_merge([
            'company_id' => $this->companyId(),
            'week_plan_id' => $weekPlanId,
            'user_id' => $owner->id,
            'user_name' => $owner->name,
            'plan_date' => Carbon::today()->toDateString(),
            'day_part' => 'full_day',
            'title' => 'Việc kế hoạch '.Str::upper(Str::random(5)),
            'source_type' => 'personal',
            'source_id' => null,
            'estimated_minutes' => 240,
            'priority' => 'normal',
            'status' => 'planned',
            'progress_percent' => 0,
            'created_by' => $owner->id,
            'created_by_name' => $owner->name,
            'is_manager_assigned' => 0,
            'active_flag' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ], $overrides));
    }

    /**
     * Payload tối thiểu, hợp lệ cho form thêm một dòng kế hoạch.
     *
     * @return array<string, mixed>
     */
    protected function planItemPayload(array $overrides = []): array
    {
        return array_merge([
            'plan_date' => Carbon::today()->toDateString(),
            'day_part' => 'full_day',
            'title' => 'Thi công hệ thống mái',
            'objective' => 'Hoàn thành lắp 10 tấm pin',
            'estimated_minutes' => 240,
            'priority' => 'normal',
            'source_type' => 'personal',
        ], $overrides);
    }

    /** Tạo báo cáo ngày trực tiếp trong DB (bỏ qua HTTP) để dựng trạng thái. */
    protected function makeDailyReport(User $owner, array $overrides = []): int
    {
        return (int) DB::table('technical_daily_reports')->insertGetId(array_merge([
            'company_id' => $this->companyId(),
            'user_id' => $owner->id,
            'user_name' => $owner->name,
            'report_date' => Carbon::today()->toDateString(),
            'source_type' => TechnicalWorkItem::SOURCE_TASK,
            'source_id' => 1,
            'site_id' => null,
            'site_name' => 'Công trình test',
            'work_title' => 'Đầu việc test',
            'content' => 'Nội dung báo cáo test đủ dài.',
            'progress_percent' => 50,
            'status' => 'draft',
            'created_at' => now(),
            'updated_at' => now(),
        ], $overrides));
    }
}
