<?php

namespace App\Console\Commands;

use App\Models\TechnicalKpiConfig;
use App\Services\Technical\TechnicalWorkFeedService;
use App\Services\TechnicalKpi\KpiStandardEvaluator;
use App\Support\EgoCompanyLock;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;

/**
 * Dữ liệu TEST cho checklist KPI kỹ thuật (đợt kpi-checklist-demo 07/10/2026).
 *
 * Chỉ chạy khi gọi chủ động; mặc định xem trước, `--apply` mới ghi. Thêm 5 việc trưởng phòng mẫu giao cho kỹ thuật
 * mẫu (công trình mẫu), kèm báo cáo và nhật ký gửi với mốc giờ cố định:
 *   01 nộp trước hạn · 02 nộp đúng biên 24 giờ · 03 nộp muộn 2 giờ · 04 quá hạn chưa nộp · 05 còn hạn, chỉ có nháp.
 * Chạy lại: việc đã có (theo mã TEST) được tái sử dụng nguyên trạng, không tạo trùng, không đổi giờ/nội dung.
 * Không sửa công trình, phiếu bảo hành, xác nhận, lương hoặc cấu hình; không gửi thông báo.
 */
class SeedKpiChecklistDemo extends Command
{
    protected $signature = 'kpi:demo-checklist
        {--apply : Ghi dữ liệu mẫu (mặc định chỉ kiểm tra và xem trước)}
        {--manifest= : Đường dẫn manifest JSON (mặc định outputs/kpi-checklist-demo-20261007/manifest.json)}';

    protected $description = 'Tạo 5 việc TEST minh họa checklist KPI 24 giờ cho tài khoản kỹ thuật mẫu (chỉ local/DB mẫu).';

    public const PREFIX = 'TEST-KPI-CHECKLIST-20261007-';

    public const MANAGER_EMAIL = 'techmanager.kpidemo@example.test';

    public const ENGINEER_EMAIL = 'kythuat.kpidemo@example.test';

    public const SITE_CODE = 'TEST-KPI-20261006';

    public const SCHEDULE_CODE = 'TEST-KPI-20261006-BH';

    public const COMPANY_ID = 2;

    /** DB được phép ghi theo môi trường. */
    private const ALLOWED_DATABASES = ['local' => 'crm_egosolar_test', 'testing' => 'egosolar_test'];

    /** Mốc giờ cố định (giờ app). report = null: không có báo cáo; submitted = null: chỉ có nháp. */
    public const ITEMS = [
        '01' => ['title' => 'Kiểm tra inverter và tủ điện', 'assigned' => '2026-10-02 08:00:00',
            'report' => ['created' => '2026-10-02 16:30:00', 'submitted' => '2026-10-02 17:00:00'], 'expect' => 'Đúng hạn — nhận điểm'],
        '02' => ['title' => 'Vệ sinh tấm pin dãy A', 'assigned' => '2026-10-03 08:00:00',
            'report' => ['created' => '2026-10-04 07:40:00', 'submitted' => '2026-10-04 08:00:00'], 'expect' => 'Đúng biên 24 giờ — nhận điểm'],
        '03' => ['title' => 'Đo cách điện chuỗi pin', 'assigned' => '2026-10-04 08:00:00',
            'report' => ['created' => '2026-10-05 09:30:00', 'submitted' => '2026-10-05 10:00:00'], 'expect' => 'Nộp muộn 2 giờ — mất phần điểm'],
        '04' => ['title' => 'Cập nhật ảnh hiện trạng mái', 'assigned' => '2026-10-05 08:00:00',
            'report' => null, 'expect' => 'Quá hạn chưa nộp — mất phần điểm'],
        '05' => ['title' => 'Kiểm tra kết nối App giám sát', 'assigned' => '2026-10-07 09:00:00',
            'report' => ['created' => '2026-10-07 09:30:00', 'submitted' => null], 'expect' => 'Còn hạn, chỉ có nháp — chưa tính'],
    ];

    public function handle(TechnicalWorkFeedService $feed): int
    {
        $apply = (bool) $this->option('apply');
        $context = $this->guard($feed);
        if (is_string($context)) {
            $this->error('Dừng: '.$context);

            return self::FAILURE;
        }

        $this->info(($apply ? 'GHI' : 'XEM TRƯỚC').' · DB '.$context['db'].' · công ty '.$context['company_id']
            .' · trưởng phòng #'.$context['manager']->id.' · kỹ thuật #'.$context['engineer']->id
            .' · công trình #'.$context['site']->id.' · phân công nguồn #'.$context['assignment_id']);

        if ($apply) {
            // Không phát thông báo/mail trong phạm vi lệnh mẫu (ghi trực tiếp, không qua sự kiện nghiệp vụ).
            Notification::fake();
            Mail::fake();
        }

        $rows = [];
        $write = function () use ($context, $apply, &$rows): void {
            foreach (self::ITEMS as $code => $spec) {
                $rows[] = $this->seedItem($code, $spec, $context, $apply);
            }
        };
        $apply ? DB::transaction($write) : $write();

        $evaluation = $this->evaluate((int) $context['engineer']->id);
        $manifest = [
            'generated_at' => now()->toDateTimeString(),
            'mode' => $apply ? 'apply' : 'dry-run',
            'timezone' => config('app.timezone'),
            'db' => $context['db'],
            'company_id' => $context['company_id'],
            'manager_id' => (int) $context['manager']->id,
            'engineer_id' => (int) $context['engineer']->id,
            'site_id' => (int) $context['site']->id,
            'schedule_id' => (int) $context['schedule']->id,
            'source_assignment_id' => $context['assignment_id'],
            'items' => $rows,
            'pending_item_due_at' => Carbon::parse(self::ITEMS['05']['assigned'])->addHours(24)->toDateTimeString(),
            'evaluation' => $evaluation,
        ];

        $this->table(['Mã', 'Việc', 'Trạng thái', 'Giao', 'Báo cáo', 'Gửi lần đầu', 'Kỳ vọng'], array_map(fn ($r) => [
            $r['code'], $r['plan_item_id'] ?? '—', $r['action'], $r['assigned_at'], $r['report_id'] ?? '—', $r['submitted_at'] ?? '—', $r['expect'],
        ], $rows));
        $this->line('KS01 tại '.$evaluation['as_of'].': '.$evaluation['ks01']);
        $this->line('Tổng KPI kỳ 2026-10 (bộ chấm hiện tại): '.($evaluation['total_points'] === null ? 'chưa đủ dữ liệu' : $evaluation['total_points'].' điểm'));
        $this->line('Việc 05 hết hạn lúc '.$manifest['pending_item_due_at'].' (sau mốc này sẽ thành quá hạn nếu chưa nộp).');

        if ($apply) {
            $path = (string) ($this->option('manifest') ?: base_path('outputs/kpi-checklist-demo-20261007/manifest.json'));
            File::ensureDirectoryExists(dirname($path));
            File::put($path, json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            $this->info('Manifest: '.$path);
        } else {
            $this->warn('Chưa ghi gì. Thêm --apply để tạo dữ liệu mẫu.');
        }

        return self::SUCCESS;
    }

    /** @return array<string, mixed>|string lý do dừng */
    private function guard(TechnicalWorkFeedService $feed): array|string
    {
        $env = app()->environment();
        $db = (string) DB::connection()->getDatabaseName();
        $host = (string) config('database.connections.'.config('database.default').'.host');
        if (! isset(self::ALLOWED_DATABASES[$env]) || self::ALLOWED_DATABASES[$env] !== $db) {
            return 'chỉ chạy ở môi trường local với DB crm_egosolar_test (hoặc testing với egosolar_test). Hiện: '.$env.' / '.$db.'.';
        }
        if (! in_array($host, ['127.0.0.1', 'localhost', '::1'], true)) {
            return 'DB không ở máy local ('.$host.').';
        }
        if (EgoCompanyLock::id() !== self::COMPANY_ID) {
            return 'công ty đang khóa không phải công ty mẫu '.self::COMPANY_ID.'.';
        }
        $roles = fn (int $id) => DB::table('model_has_roles as m')->join('roles as r', 'r.id', '=', 'm.role_id')
            ->where('m.model_id', $id)->pluck('r.name')->all();
        $manager = DB::table('users')->where('email', self::MANAGER_EMAIL)->first();
        $engineer = DB::table('users')->where('email', self::ENGINEER_EMAIL)->first();
        if (! $manager || ! in_array('technical_manager', $roles((int) $manager->id), true) || (int) ($manager->is_active ?? 1) !== 1) {
            return 'không thấy trưởng phòng mẫu '.self::MANAGER_EMAIL.' (role technical_manager, đang hoạt động).';
        }
        if (! $engineer || ! in_array('ky_thuat', $roles((int) $engineer->id), true) || (int) ($engineer->is_active ?? 1) !== 1) {
            return 'không thấy kỹ thuật mẫu '.self::ENGINEER_EMAIL.' (role ky_thuat, đang hoạt động).';
        }
        $site = DB::table('sites')->where('project_code', self::SITE_CODE)->first();
        if (! $site || (int) $site->company_id !== self::COMPANY_ID || (int) ($site->lead_engineer_id ?? 0) !== (int) $engineer->id) {
            return 'công trình mẫu '.self::SITE_CODE.' không khớp công ty/kỹ thuật mẫu.';
        }
        $schedule = DB::table('solar_maintenance_schedules')->where('schedule_code', self::SCHEDULE_CODE)->first();
        if (! $schedule || (int) $schedule->company_id !== self::COMPANY_ID || (int) $schedule->site_id !== (int) $site->id) {
            return 'phiếu bảo hành mẫu '.self::SCHEDULE_CODE.' không khớp công trình/công ty mẫu.';
        }

        // Nguồn việc: phân công thật của kỹ thuật mẫu trên công trình mẫu, đọc qua feed như khi trưởng phòng chọn việc.
        $assignmentIds = DB::table('project_workflow_assignments as a')
            ->join('project_workflow_steps as s', 's.id', '=', 'a.workflow_step_id')
            ->where('s.site_id', $site->id)->where('a.user_id', $engineer->id)->where('a.is_active', 1)
            ->orderByRaw("FIELD(s.step_code, 'construction', 'acceptance', 'warranty') DESC")->orderByDesc('a.id')
            ->pluck('a.id');
        $source = null;
        foreach ($assignmentIds as $id) {
            if ($item = $feed->findItem('project_workflow', (int) $id, (int) $engineer->id)) {
                $source = ['id' => (int) $id, 'item' => $item];
                break;
            }
        }
        if (! $source) {
            return 'kỹ thuật mẫu không có phân công còn hiệu lực trên công trình mẫu để gắn việc.';
        }

        return [
            'db' => $db, 'company_id' => self::COMPANY_ID, 'manager' => $manager, 'engineer' => $engineer,
            'site' => $site, 'schedule' => $schedule, 'assignment_id' => $source['id'], 'source' => $source['item'],
        ];
    }

    /** @return array<string, mixed> */
    private function seedItem(string $code, array $spec, array $ctx, bool $apply): array
    {
        $engineer = $ctx['engineer'];
        $manager = $ctx['manager'];
        $title = self::PREFIX.$code.' · '.$spec['title'];
        $assigned = Carbon::parse($spec['assigned']);
        $row = [
            'code' => $code, 'title' => $title, 'assigned_at' => $assigned->toDateTimeString(),
            'due_at' => $assigned->copy()->addHours(24)->toDateTimeString(), 'expect' => $spec['expect'],
            'plan_item_id' => null, 'report_id' => null, 'report_status' => null,
            'submitted_at' => $spec['report']['submitted'] ?? null,
        ];

        $existing = DB::table('technical_plan_items')->where('user_id', $engineer->id)
            ->where('title', 'like', self::PREFIX.$code.'%')->orderBy('id')->first();
        if ($existing) {
            // Tái sử dụng nguyên trạng: không đổi giờ, nội dung hay báo cáo mà người dùng có thể đã thao tác.
            $report = DB::table('technical_daily_reports')->where('plan_item_id', $existing->id)->whereNull('deleted_at')->orderBy('id')->first();

            return array_merge($row, [
                'action' => $existing->deleted_at ? 'đã có (đã xóa) — bỏ qua' : 'đã có — giữ nguyên',
                'plan_item_id' => (int) $existing->id,
                'assigned_at' => (string) $existing->assigned_at,
                'report_id' => $report ? (int) $report->id : null,
                'report_status' => $report->status ?? null,
                'submitted_at' => $report->submitted_at ?? null,
            ]);
        }
        if (now()->lt($assigned)) {
            return array_merge($row, ['action' => 'chưa tới giờ giao mẫu — chưa tạo']);
        }
        if (! $apply) {
            return array_merge($row, ['action' => 'sẽ tạo']);
        }

        $note = 'Dữ liệu giả TEST để minh họa checklist KPI 24 giờ — không phải công việc thật.';
        $weekStart = $assigned->copy()->startOfWeek();
        $plan = DB::table('technical_week_plans')->where('user_id', $engineer->id)->whereDate('week_start', $weekStart->toDateString())->first();
        $planId = $plan ? (int) $plan->id : (int) DB::table('technical_week_plans')->insertGetId([
            'company_id' => $ctx['company_id'], 'user_id' => $engineer->id, 'user_name' => $engineer->name,
            'week_start' => $weekStart->toDateString(), 'week_end' => $weekStart->copy()->addDays(6)->toDateString(),
            'status' => 'adjusted', 'adjusted_at' => $assigned, 'adjusted_by' => $manager->id, 'adjust_note' => $note,
            'created_at' => $assigned, 'updated_at' => $assigned,
        ]);

        $source = $ctx['source'];
        $itemValues = [
            'company_id' => $ctx['company_id'], 'week_plan_id' => $planId, 'user_id' => $engineer->id, 'user_name' => $engineer->name,
            'plan_date' => $assigned->toDateString(), 'day_part' => 'full_day', 'title' => $title,
            'objective' => $spec['title'], 'note' => $note,
            'source_type' => 'project_workflow', 'source_id' => $ctx['assignment_id'],
            'site_id' => $source->siteId, 'site_name' => $source->siteName,
            'estimated_minutes' => 120, 'priority' => 'normal', 'status' => 'planned',
            'source_due_at' => $source->dueDate?->toDateTimeString(),
            'created_by' => $manager->id, 'created_by_name' => $manager->name,
            'is_manager_assigned' => 1, 'assigned_by' => $manager->id, 'assigned_at' => $assigned,
            'created_at' => $assigned, 'updated_at' => $assigned,
        ];
        $itemId = (int) DB::table('technical_plan_items')->insertGetId($itemValues);
        DB::table('technical_plan_histories')->insert([
            'company_id' => $ctx['company_id'], 'week_plan_id' => $planId, 'plan_item_id' => $itemId,
            'target_user_id' => $engineer->id, 'user_id' => $manager->id, 'user_name' => $manager->name, 'action' => 'assign',
            'changes_before' => null,
            'changes_after' => json_encode(array_intersect_key($itemValues, array_flip(['title', 'plan_date', 'source_type', 'source_id', 'site_id', 'status'])), JSON_UNESCAPED_UNICODE),
            'reason' => $note, 'created_at' => $assigned, 'updated_at' => $assigned,
        ]);
        $row['plan_item_id'] = $itemId;
        $row['action'] = 'đã tạo';

        if ($spec['report'] !== null) {
            $created = Carbon::parse($spec['report']['created']);
            $submitted = $spec['report']['submitted'] ? Carbon::parse($spec['report']['submitted']) : null;
            $reportId = (int) DB::table('technical_daily_reports')->insertGetId([
                'company_id' => $ctx['company_id'], 'user_id' => $engineer->id, 'user_name' => $engineer->name,
                'report_date' => $assigned->toDateString(), 'source_type' => 'project_workflow', 'source_id' => $ctx['assignment_id'],
                'plan_item_id' => $itemId, 'week_plan_id' => $planId, 'is_unplanned' => 0,
                'site_id' => $source->siteId, 'site_name' => $source->siteName, 'work_title' => $title,
                'content' => $note.' Nội dung báo cáo mẫu cho việc '.$code.'.',
                'result_achieved' => $submitted ? 'Hoàn thành theo yêu cầu (dữ liệu giả).' : null,
                'progress_percent' => $submitted ? 100 : 50, 'work_hours' => 2,
                'status' => $submitted ? 'submitted' : 'draft', 'submitted_at' => $submitted,
                'created_at' => $created, 'updated_at' => $submitted ?? $created,
            ]);
            $history = fn (string $action, ?string $before, string $after, Carbon $at) => DB::table('technical_daily_report_histories')->insert([
                'report_id' => $reportId, 'user_id' => $engineer->id, 'user_name' => $engineer->name, 'action' => $action,
                'status_before' => $before, 'status_after' => $after, 'note' => 'Dữ liệu giả TEST', 'created_at' => $at, 'updated_at' => $at,
            ]);
            $history('create', null, 'draft', $created);
            if ($submitted) {
                $history('submit', 'draft', 'submitted', $submitted);
            }
            $row['report_id'] = $reportId;
            $row['report_status'] = $submitted ? 'submitted' : 'draft';
        }

        return $row;
    }

    /** Kết quả bộ chấm tại thời điểm chạy (cùng cách trang KPI chọn bộ quy tắc của kỳ). */
    private function evaluate(int $engineerId): array
    {
        $month = '2026-10';
        $evaluator = app(KpiStandardEvaluator::class)->useConfig(TechnicalKpiConfig::getActiveForDate($month.'-01'), $month);
        $data = $evaluator->collect($engineerId, [$month]);
        $result = $evaluator->score($data['projects'], $data['evidence'], $data['warranty'], $data['manual'], $data['adjustments'], $data['assigned_work']);
        $timeline = collect($result['criteria'])->firstWhere('code', 'timeline');
        $points = fn ($v) => $v === null ? null : round((float) $v * 100, 2);

        return [
            'as_of' => now()->toDateTimeString(),
            'ks01' => $timeline['score'] === null
                ? 'chưa tính ('.implode(' ', $timeline['missing']).')'
                : $timeline['passed'].'/'.$timeline['total'].' việc đúng hạn = '.$points($timeline['score']).'/25 điểm',
            'criteria' => collect($result['criteria'])->mapWithKeys(fn ($c) => [$c['code'] => [
                'status' => $c['status'], 'passed' => $c['passed'], 'total' => $c['total'], 'points' => $points($c['score']),
            ]])->all(),
            'total_points' => $points($result['total']),
        ];
    }
}
