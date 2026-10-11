<?php

namespace App\Console\Commands;

use App\Models\Projects\Site;
use App\Services\Projects\ProjectWorkflowV2Service;
use App\Support\EgoCompanyLock;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

/**
 * Công trình TEST mới để thử luồng Nghiệm thu → KPI → lương (đợt kpi-salary-closeout 07/10/2026).
 *
 * Chỉ chạy khi gọi; mặc định xem trước, `--apply` mới ghi. Tạo một công trình giả `TEST-KPI-LUONG-20261007-01`
 * cho kỹ thuật mẫu (trưởng phòng mẫu giao Nghiệm thu), đủ file hồ sơ giả, quyết định cần đấu nối = Có (dữ liệu
 * giả). KHÔNG nhập kết quả KPI, KHÔNG xác nhận, KHÔNG duyệt nghiệm thu — các bước đó làm trên giao diện theo vai trò.
 * Chạy lại: công trình đã có được giữ nguyên. Không sửa công trình/phiếu/việc mẫu cũ, không gửi thông báo.
 */
class SeedKpiSalaryDemoSite extends Command
{
    protected $signature = 'kpi:demo-salary-site
        {--apply : Ghi dữ liệu mẫu (mặc định chỉ xem trước)}
        {--manifest= : Đường dẫn manifest JSON}';

    protected $description = 'Tạo 1 công trình TEST mới cho tài khoản kỹ thuật mẫu để thử luồng Nghiệm thu → KPI → lương (chỉ local).';

    public const CODE = 'TEST-KPI-LUONG-20261007-01';

    private const ALLOWED_DATABASES = ['local' => 'crm_egosolar_test', 'testing' => 'egosolar_test'];

    public function handle(ProjectWorkflowV2Service $workflow): int
    {
        $env = app()->environment();
        $db = (string) DB::connection()->getDatabaseName();
        $host = (string) config('database.connections.'.config('database.default').'.host');
        if ((self::ALLOWED_DATABASES[$env] ?? null) !== $db || ! in_array($host, ['127.0.0.1', 'localhost', '::1'], true)) {
            $this->error('Dừng: chỉ chạy ở local với DB crm_egosolar_test (hoặc testing với egosolar_test). Hiện: '.$env.' / '.$db.' @ '.$host.'.');

            return self::FAILURE;
        }
        if (EgoCompanyLock::id() !== SeedKpiChecklistDemo::COMPANY_ID) {
            $this->error('Dừng: công ty đang khóa không phải công ty mẫu.');

            return self::FAILURE;
        }
        $roles = fn (int $id) => DB::table('model_has_roles as m')->join('roles as r', 'r.id', '=', 'm.role_id')->where('m.model_id', $id)->pluck('r.name')->all();
        $manager = DB::table('users')->where('email', SeedKpiChecklistDemo::MANAGER_EMAIL)->first();
        $engineer = DB::table('users')->where('email', SeedKpiChecklistDemo::ENGINEER_EMAIL)->first();
        if (! $manager || ! in_array('technical_manager', $roles((int) $manager->id), true)
            || ! $engineer || ! in_array('ky_thuat', $roles((int) $engineer->id), true)) {
            $this->error('Dừng: không thấy tài khoản mẫu trưởng phòng/kỹ thuật với đúng role.');

            return self::FAILURE;
        }

        $existing = DB::table('sites')->where('project_code', self::CODE)->first();
        if ($existing) {
            $this->info('Đã có công trình '.self::CODE.' (#'.$existing->id.') — giữ nguyên, không đổi mốc/dữ liệu.');
            $this->report((int) $existing->id);

            return self::SUCCESS;
        }
        if (! $this->option('apply')) {
            $this->warn('XEM TRƯỚC: sẽ tạo công trình '.self::CODE.' (công ty '.SeedKpiChecklistDemo::COMPANY_ID.', kỹ sư chính #'.$engineer->id
                .', trưởng phòng #'.$manager->id.' giao Nghiệm thu, 15 file hồ sơ giả). Thêm --apply để ghi.');

            return self::SUCCESS;
        }

        Notification::fake();
        Mail::fake();
        $now = now();
        $note = 'Dữ liệu giả TEST để thử luồng Nghiệm thu → KPI → lương — không phải công trình thật.';
        $siteId = DB::transaction(function () use ($workflow, $manager, $engineer, $now, $note): int {
            $siteId = (int) DB::table('sites')->insertGetId([
                'name' => self::CODE.' — Công trình mới thử nghiệm thu (giả)', 'project_code' => self::CODE,
                'company_id' => SeedKpiChecklistDemo::COMPANY_ID, 'address' => 'Địa chỉ giả — dữ liệu TEST',
                'lead_engineer_id' => $engineer->id, 'workflow_current_step' => 'acceptance',
                'created_at' => $now, 'updated_at' => $now,
            ]);
            // Dùng chính cơ chế khởi tạo quy trình: các bước trước Nghiệm thu được đánh dấu đã qua như hồ sơ chuyển đổi.
            $workflow->ensure(Site::query()->findOrFail($siteId));
            $steps = DB::table('project_workflow_steps')->where('site_id', $siteId)->pluck('id', 'step_code');
            DB::table('project_workflow_steps')->where('id', $steps['legal'])->update(['data' => json_encode([
                'step_note' => $note, 'requires_grid_connection' => true, 'requires_grid_connection_decided_by' => null,
                'requires_grid_connection_decided_at' => $now->toDateTimeString(),
            ], JSON_UNESCAPED_UNICODE)]);
            DB::table('project_workflow_steps')->where('id', $steps['acceptance'])->update([
                'status' => 'in_progress', 'assigned_by' => $manager->id, 'started_at' => $now, 'due_at' => $now->copy()->addDays(3)->setTime(17, 0),
                'requirement' => 'Nghiệm thu, bàn giao và nhập kết quả KPI (TEST)', 'data' => json_encode(['step_note' => $note], JSON_UNESCAPED_UNICODE),
                'updated_at' => $now,
            ]);
            DB::table('project_workflow_assignments')->insert([
                'workflow_step_id' => $steps['acceptance'], 'user_id' => $engineer->id, 'assignment_role' => 'primary', 'status' => 'in_progress',
                'is_active' => 1, 'assigned_by' => $manager->id, 'progress_percent' => 0, 'created_at' => $now, 'updated_at' => $now,
            ]);

            $pdf = "%PDF-1.4\n1 0 obj<</Type/Catalog/Pages 2 0 R>>endobj 2 0 obj<</Type/Pages/Count 0/Kids[]>>endobj\ntrailer<</Root 1 0 R>>\n%%EOF\n";
            $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==');
            $docs = ['customer_acceptance' => ['pdf', 1], 'as_built_drawing' => ['pdf', 1], 'test_certificates' => ['pdf', 1],
                'handover_minutes' => ['pdf', 1], 'material_reconciliation' => ['pdf', 1], 'completion_photos' => ['png', 10]];
            foreach ($docs as $code => [$ext, $count]) {
                for ($i = 1; $i <= $count; $i++) {
                    $path = 'project-workflow/'.$siteId.'/acceptance/'.$code.'/TEST-'.$code.'-'.$i.'.'.$ext;
                    // Qua disk "public" (test dùng Storage::fake nên không ghi ra thư mục dùng chung).
                    Storage::disk('public')->put($path, $ext === 'pdf' ? $pdf : $png);
                    DB::table('project_workflow_documents')->insert([
                        'workflow_step_id' => $steps['acceptance'], 'site_id' => $siteId, 'document_code' => $code,
                        'title' => $code.' (TEST)', 'path' => $path, 'original_name' => 'TEST-'.$code.'-'.$i.'.'.$ext,
                        'mime_type' => $ext === 'pdf' ? 'application/pdf' : 'image/png', 'file_size' => 100, 'version' => 1,
                        'uploaded_by' => $engineer->id, 'created_at' => $now, 'updated_at' => $now,
                    ]);
                }
            }

            return $siteId;
        });

        $this->info('Đã tạo công trình '.self::CODE.' (#'.$siteId.'). Chưa nhập/ xác nhận KPI, chưa duyệt nghiệm thu.');
        $this->report($siteId, (string) ($this->option('manifest') ?: base_path('outputs/claude-kpi-agent/salary-closeout-20261007/demo-site-manifest.json')));

        return self::SUCCESS;
    }

    private function report(int $siteId, ?string $manifestPath = null): void
    {
        $steps = DB::table('project_workflow_steps')->where('site_id', $siteId)->orderBy('sequence')->get(['id', 'step_code', 'status', 'approved_at']);
        $data = [
            'generated_at' => Carbon::now()->toDateTimeString(),
            'site_id' => $siteId, 'project_code' => self::CODE,
            'steps' => $steps->map(fn ($s) => (array) $s)->all(),
            'documents' => DB::table('project_workflow_documents')->where('site_id', $siteId)->count(),
            'assignments' => DB::table('project_workflow_assignments')->whereIn('workflow_step_id', $steps->pluck('id'))->get(['workflow_step_id', 'user_id', 'status'])->map(fn ($a) => (array) $a)->all(),
            'url' => '/du-an/'.$siteId.'?step=acceptance#acceptance-kpi',
        ];
        $this->line('Bước: '.$steps->map(fn ($s) => $s->step_code.'='.$s->status)->implode(', ').' · hồ sơ: '.$data['documents'].' · URL: '.$data['url']);
        if ($manifestPath) {
            File::ensureDirectoryExists(dirname($manifestPath));
            File::put($manifestPath, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            $this->info('Manifest: '.$manifestPath);
        }
    }
}
