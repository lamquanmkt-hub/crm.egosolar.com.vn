<?php

namespace App\Console\Commands;

use App\Models\ProjectTest\Project;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Throwable;

class PromoteProjectTestToPrimary extends Command
{
    protected $signature = 'ego:projects:promote
        {--dry-run : Chỉ kiểm tra, không thay đổi dữ liệu}
        {--delete-demos : Xóa toàn bộ hồ sơ demo trạng thái và dữ liệu con}
        {--import-legacy : Chuyển dữ liệu bảng sites sang module Công trình mới}
        {--exclude-ego-vn : Không đưa công trình EGO VN vào module mới và ẩn các bản đã có}
        {--force : Bỏ qua xác nhận tương tác}';

    protected $description = 'Đưa workflow Công trình mới thành module chính, xóa demo và nhập dữ liệu công trình cũ an toàn.';

    public function handle(): int
    {
        foreach (['project_test_projects', 'sites'] as $table) {
            if (! Schema::hasTable($table)) {
                $this->error("Thiếu bảng {$table}. Dừng để tránh làm sai dữ liệu.");
                return self::FAILURE;
            }
        }

        $demoIds = $this->demoProjectIds();
        $egoVnCompanyId = $this->egoVnCompanyId();
        $legacyQuery = DB::table('sites');
        if ($this->option('exclude-ego-vn') && $egoVnCompanyId !== null) {
            $legacyQuery->where(function ($query) use ($egoVnCompanyId): void {
                $query->whereNull('company_id')->orWhere('company_id', '<>', $egoVnCompanyId);
            });
        }
        $legacyTotal = $legacyQuery->count();
        $egoVnActive = $egoVnCompanyId !== null
            ? DB::table('project_test_projects')->where('company_id', $egoVnCompanyId)->whereNull('deleted_at')->count()
            : 0;
        $legacyImported = Schema::hasColumn('project_test_projects', 'legacy_site_id')
            ? DB::table('project_test_projects')->whereNotNull('legacy_site_id')->count()
            : 0;

        $this->newLine();
        $this->info('KIỂM TRA TRƯỚC KHI CHUYỂN MODULE');
        $this->table(
            ['Hạng mục', 'Số lượng'],
            [
                ['Hồ sơ demo sẽ xóa', $demoIds->count()],
                ['Công trình EGO VN sẽ ẩn khỏi module mới', $this->option('exclude-ego-vn') ? $egoVnActive : 0],
                ['Công trình cũ đủ điều kiện chuyển', $legacyTotal],
                ['Đã chuyển trước đó', $legacyImported],
                ['Còn cần chuyển', max(0, $legacyTotal - $legacyImported)],
            ]
        );

        if ($this->option('dry-run')) {
            $this->warn('Đang ở chế độ dry-run. Chưa thay đổi dữ liệu.');
            return self::SUCCESS;
        }

        if (! $this->option('delete-demos') && ! $this->option('import-legacy')) {
            $this->error('Phải chọn ít nhất --delete-demos hoặc --import-legacy.');
            return self::FAILURE;
        }

        if (! $this->option('force') && ! $this->confirm('Tiếp tục thực hiện giao dịch dữ liệu?', false)) {
            $this->warn('Đã hủy.');
            return self::SUCCESS;
        }

        $this->ensureRolePermissions();

        try {
            $result = DB::transaction(function () use ($demoIds, $egoVnCompanyId): array {
                $deleted = 0;
                $excludedEgoVn = 0;
                $imported = 0;
                $updated = 0;

                if ($this->option('delete-demos') && $demoIds->isNotEmpty()) {
                    Project::withTrashed()
                        ->with([
                            'survey',
                            'dailyLogs',
                            'acceptance',
                            'materialRequests.items.allocations',
                        ])
                        ->whereIn('id', $demoIds)
                        ->get()
                        ->each(function (Project $project) use (&$deleted): void {
                            $this->cleanupDemoProject($project);
                            $project->forceDelete();
                            $deleted++;
                        });
                }

                if ($this->option('exclude-ego-vn') && $egoVnCompanyId !== null) {
                    $excludedEgoVn = DB::table('project_test_projects')
                        ->where('company_id', $egoVnCompanyId)
                        ->whereNull('deleted_at')
                        ->update([
                            'deleted_at' => now(),
                            'updated_at' => now(),
                        ]);
                }

                if ($this->option('import-legacy')) {
                    [$imported, $updated] = $this->importLegacySites($this->option('exclude-ego-vn') ? $egoVnCompanyId : null);
                }

                return compact('deleted', 'excludedEgoVn', 'imported', 'updated');
            }, 3);
        } catch (Throwable $e) {
            report($e);
            $this->error('Thất bại, giao dịch đã rollback: '.$e->getMessage());
            return self::FAILURE;
        }

        $remainingDemos = $this->demoProjectIds()->count();
        $this->newLine();
        $this->info('HOÀN TẤT');
        $this->table(
            ['Kết quả', 'Số lượng'],
            [
                ['Đã xóa demo', $result['deleted']],
                ['Đã ẩn công trình EGO VN khỏi module mới', $result['excludedEgoVn']],
                ['Đã nhập công trình cũ', $result['imported']],
                ['Đã đồng bộ bản đã nhập', $result['updated']],
                ['Demo còn lại', $remainingDemos],
            ]
        );

        if ($remainingDemos > 0) {
            $this->warn('Vẫn còn dữ liệu nghi là demo. Hãy kiểm tra trước khi vận hành.');
            return self::FAILURE;
        }

        $this->line('Bảng sites và các bảng liên quan vẫn được giữ nguyên, không xóa dữ liệu gốc.');
        return self::SUCCESS;
    }


    private function egoVnCompanyId(): ?int
    {
        if (! Schema::hasTable('companies')) {
            return null;
        }

        $id = DB::table('companies')->where('code', 'EGO_VN')->value('id');
        if ($id) {
            return (int) $id;
        }

        $id = DB::table('companies')
            ->where(function ($query): void {
                $query->where('name', 'like', '%EGO VIỆT NAM%')
                    ->orWhere('name', 'like', '%EGO VIET NAM%');
            })
            ->value('id');

        return $id ? (int) $id : null;
    }

    private function demoProjectIds()
    {
        return DB::table('project_test_projects')
            ->where(function ($query): void {
                $query->where('name', 'like', '[DEMO TRẠNG THÁI]%')
                    ->orWhere('note', 'like', '%Công trình Demo tự động%')
                    ->orWhere(function ($demoSignature): void {
                        $demoSignature
                            ->where('customer_need', 'like', '%Dữ liệu mô phỏng để kiểm tra%')
                            ->where('contact_phone', 'like', '09091000%');
                    });
            })
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();
    }

    private function cleanupDemoProject(Project $project): void
    {
        $paths = collect([
            $project->survey?->design_3d_file,
            $project->survey?->attachment_file,
            $project->acceptance?->report_file,
        ])->merge($project->dailyLogs->pluck('attachment_file'))
            ->filter(fn ($path): bool => is_string($path) && trim($path) !== '')
            ->unique()
            ->values();

        foreach ($paths as $path) {
            Storage::disk('public')->delete($path);
        }

        if (! Schema::hasTable('crm_serial_unit_states')) {
            return;
        }

        foreach ($project->materialRequests as $materialRequest) {
            foreach ($materialRequest->items as $item) {
                foreach ($item->allocations as $allocation) {
                    if (! $allocation->is_serialized || $allocation->status !== 'reserved') {
                        continue;
                    }

                    $ids = collect($allocation->selected_serial_unit_ids ?? [])
                        ->map(fn ($id): int => (int) $id)
                        ->filter()
                        ->unique()
                        ->values()
                        ->all();

                    if ($ids === []) {
                        continue;
                    }

                    $payload = [
                        'state' => 'in_stock',
                        'warehouse_id' => $allocation->warehouse_id,
                        'synced_at' => now(),
                    ];

                    if (Schema::hasColumn('crm_serial_unit_states', 'company_id')) {
                        $payload['company_id'] = DB::table('crm_warehouses')
                            ->where('id', $allocation->warehouse_id)
                            ->value('company_id');
                    }

                    DB::table('crm_serial_unit_states')
                        ->whereIn('serial_unit_id', $ids)
                        ->where('state', 'reserved')
                        ->update($payload);
                }
            }
        }
    }

    private function importLegacySites(?int $excludedCompanyId = null): array
    {
        if (! Schema::hasColumn('project_test_projects', 'legacy_site_id')) {
            throw new \RuntimeException('Chưa có cột legacy_site_id. Hãy chạy migration trước.');
        }

        $fallbackUserId = $this->fallbackUserId();
        if ($fallbackUserId <= 0) {
            throw new \RuntimeException('Không tìm thấy tài khoản hợp lệ để gán người tạo cho dữ liệu cũ.');
        }

        $imported = 0;
        $updated = 0;
        $sitesQuery = DB::table('sites')->orderBy('id');
        if ($excludedCompanyId !== null) {
            $sitesQuery->where(function ($query) use ($excludedCompanyId): void {
                $query->whereNull('company_id')->orWhere('company_id', '<>', $excludedCompanyId);
            });
        }
        $sites = $sitesQuery->get();

        foreach ($sites as $site) {
            $legacyId = (int) $site->id;
            $existing = DB::table('project_test_projects')->where('legacy_site_id', $legacyId)->first();
            $status = $this->mapLegacyStatus($site);
            $statusInfo = $this->statusInfo($status);
            $createdBy = $this->validUserId((int) ($site->created_by ?? 0)) ?: $fallbackUserId;
            $payload = $this->legacyPayload($legacyId, $site);

            $noteParts = array_filter([
                trim((string) ($site->note ?? '')),
                'Dữ liệu kế thừa từ module Công trình cũ, mã nguồn cũ đã ngừng vận hành.',
            ]);

            $data = [
                'legacy_site_id' => $legacyId,
                'code' => $existing?->code ?: $this->legacyCode($site),
                'company_id' => (int) ($site->company_id ?? 0) ?: null,
                'customer_id' => null,
                'created_by' => $createdBy,
                'sales_user_id' => $createdBy,
                'technical_manager_id' => null,
                'lead_technician_id' => $this->matchTechnicianId((string) ($site->technician_name ?? '')),
                'name' => (string) $site->name,
                'address' => $site->address,
                'contact_name' => $site->contact_name,
                'contact_phone' => $site->contact_phone,
                'customer_need' => $this->legacyCustomerNeed($site),
                'system_type' => $site->system_type,
                'estimated_kwp' => $site->system_kwp,
                'priority' => 'normal',
                'status' => $status,
                'current_owner_role' => $statusInfo['owner'],
                'proposed_survey_at' => null,
                'survey_confirmed_at' => null,
                'proposed_installation_at' => $this->asDateTime($site->installed_at ?? null),
                'installation_confirmed_at' => $this->asDateTime($site->installed_at ?? null),
                'target_completion_at' => $this->asDate($site->completed_at ?? $site->installed_at ?? null),
                'progress' => $statusInfo['progress'],
                'note' => implode("\n\n", $noteParts),
                'legacy_payload_json' => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'contract_amount' => (float) ($site->contract_amount ?? 0),
                'installed_at' => $this->asDate($site->installed_at ?? null),
                'warranty_to' => $this->asDate($site->warranty_to ?? null),
                'monitoring_link' => $site->monitoring_link,
                'monitoring_account' => $site->monitoring_account,
                'imported_from_legacy_at' => now(),
                'updated_at' => $site->updated_at ?: now(),
                'deleted_at' => null,
            ];

            if ($existing) {
                DB::table('project_test_projects')->where('id', $existing->id)->update($data);
                $projectId = (int) $existing->id;
                $updated++;
            } else {
                $desiredId = $legacyId > 0 && ! DB::table('project_test_projects')->where('id', $legacyId)->exists()
                    ? $legacyId
                    : null;

                $insert = array_merge($data, [
                    'created_at' => $site->created_at ?: now(),
                ]);

                if ($desiredId !== null) {
                    $insert['id'] = $desiredId;
                    DB::table('project_test_projects')->insert($insert);
                    $projectId = $desiredId;
                } else {
                    $projectId = (int) DB::table('project_test_projects')->insertGetId($insert);
                }
                $imported++;
            }

            $this->syncLegacyWarranty($projectId, $site, $status);
            $this->syncImportHistory($projectId, $legacyId, $createdBy, $status);
        }

        return [$imported, $updated];
    }

    private function legacyPayload(int $siteId, object $site): array
    {
        $payload = ['site' => (array) $site, 'related' => []];
        $relatedTables = [
            'site_devices',
            'site_payment_terms',
            'site_planned_materials',
            'site_assemblies',
            'solar_site_documents',
            'solar_maintenance_schedules',
            'crm_serial_warranties',
            'material_requests',
            'payment_requests',
            'payments',
            'receipts',
        ];

        foreach ($relatedTables as $table) {
            if (Schema::hasTable($table) && Schema::hasColumn($table, 'site_id')) {
                $payload['related'][$table] = DB::table($table)->where('site_id', $siteId)->get()->map(fn ($row) => (array) $row)->all();
            }
        }

        return $payload;
    }

    private function syncLegacyWarranty(int $projectId, object $site, string $status): void
    {
        if (! Schema::hasTable('project_test_warranties') || empty($site->warranty_to)) {
            return;
        }

        $startsAt = $this->asDate($site->installed_at ?? $site->contract_signed_at ?? $site->created_at ?? now());
        $endsAt = $this->asDate($site->warranty_to);
        if (! $startsAt || ! $endsAt) {
            return;
        }

        $nextMaintenance = $this->firstValidDate([
            $site->warranty_reminder_1_at ?? null,
            $site->warranty_reminder_2_at ?? null,
            $site->warranty_reminder_3_at ?? null,
        ]);

        DB::table('project_test_warranties')->updateOrInsert(
            ['project_id' => $projectId],
            [
                'starts_at' => $startsAt,
                'ends_at' => $endsAt,
                'next_maintenance_at' => $nextMaintenance,
                'assigned_to' => $this->matchTechnicianId((string) ($site->technician_name ?? '')),
                'status' => Carbon::parse($endsAt)->endOfDay()->isFuture() ? 'active' : 'completed',
                'note' => 'Kế thừa từ dữ liệu bảo hành của Công trình cũ.',
                'created_at' => $site->created_at ?: now(),
                'updated_at' => now(),
            ]
        );
    }

    private function syncImportHistory(int $projectId, int $legacyId, int $userId, string $status): void
    {
        if (! Schema::hasTable('project_test_histories')) {
            return;
        }

        $exists = DB::table('project_test_histories')
            ->where('project_id', $projectId)
            ->where('action', 'Chuyển dữ liệu từ Công trình cũ')
            ->exists();

        if (! $exists) {
            DB::table('project_test_histories')->insert([
                'project_id' => $projectId,
                'user_id' => $userId,
                'action' => 'Chuyển dữ liệu từ Công trình cũ',
                'from_status' => null,
                'to_status' => $status,
                'note' => 'Đã chuyển an toàn từ sites #'.$legacyId.'. Bảng dữ liệu gốc vẫn được giữ nguyên.',
                'meta' => json_encode(['legacy_site_id' => $legacyId, 'migration' => 'promote_project_test_primary_v1']),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }


    private function ensureRolePermissions(): void
    {
        if (! class_exists(Permission::class) || ! Schema::hasTable('permissions') || ! Schema::hasTable('roles')) {
            $this->warn('Không tìm thấy Spatie Permission; bỏ qua bước đồng bộ quyền.');
            return;
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $allPermissions = [
            'project-test.access',
            'project-test.create',
            'project-test.sales',
            'project-test.technical',
            'project-test.technical-manager',
            'project-test.admin',
            'project-test.warehouse',
            'project-test.acceptance',
            'menu.project_test',
        ];

        foreach ($allPermissions as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }

        $matrix = [
            'admin' => $allPermissions,
            'management' => $allPermissions,
            'sales_manager' => ['project-test.access', 'project-test.create', 'project-test.sales', 'menu.project_test'],
            'sales' => ['project-test.access', 'project-test.create', 'project-test.sales', 'menu.project_test'],
            'technical_manager' => ['project-test.access', 'project-test.technical', 'project-test.technical-manager', 'project-test.acceptance', 'menu.project_test'],
            'ky_thuat' => ['project-test.access', 'project-test.technical', 'project-test.acceptance', 'menu.project_test'],
            'technical' => ['project-test.access', 'project-test.technical', 'project-test.acceptance', 'menu.project_test'],
            'technician' => ['project-test.access', 'project-test.technical', 'project-test.acceptance', 'menu.project_test'],
            'technical_staff' => ['project-test.access', 'project-test.technical', 'project-test.acceptance', 'menu.project_test'],
            'technical_leader' => ['project-test.access', 'project-test.technical', 'project-test.technical-manager', 'project-test.acceptance', 'menu.project_test'],
            'warehouse' => ['project-test.access', 'project-test.warehouse', 'menu.project_test'],
            'kho' => ['project-test.access', 'project-test.warehouse', 'menu.project_test'],
            'accounting' => ['project-test.access', 'menu.project_test'],
        ];

        foreach ($matrix as $roleName => $permissions) {
            $role = Role::query()->where('name', $roleName)->where('guard_name', 'web')->first();
            if ($role) {
                $role->givePermissionTo($permissions);
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->line('Đã đồng bộ quyền Công trình chính theo role hiện tại.');
    }

    private function mapLegacyStatus(object $site): string
    {
        $status = strtolower(trim((string) ($site->status ?? '')));
        $stage = strtolower(trim((string) ($site->stage ?? '')));

        if (in_array($status, ['cancelled', 'canceled'], true)) {
            return 'cancelled';
        }
        $warrantyTo = $this->asDate($site->warranty_to ?? null);
        if ($warrantyTo && Carbon::parse($warrantyTo)->endOfDay()->isFuture()) {
            return 'warranty_active';
        }
        if (in_array($status, ['done', 'completed', 'complete'], true) || ! empty($site->completed_at)) {
            return 'completed';
        }
        if (in_array($stage, ['installation', 'deployment'], true) || ! empty($site->deployment_started_at)) {
            return 'installing';
        }
        if (in_array($stage, ['design', 'survey'], true)) {
            return 'survey_in_progress';
        }

        return 'survey_pending';
    }

    private function statusInfo(string $status): array
    {
        return match ($status) {
            'survey_in_progress' => ['owner' => 'technical', 'progress' => 20],
            'installing' => ['owner' => 'technical', 'progress' => 82],
            'warranty_active' => ['owner' => 'technical', 'progress' => 100],
            'completed' => ['owner' => 'none', 'progress' => 100],
            'cancelled' => ['owner' => 'none', 'progress' => 0],
            default => ['owner' => 'technical', 'progress' => 8],
        };
    }

    private function legacyCode(object $site): string
    {
        $base = trim((string) ($site->quote_no ?? ''));
        if ($base !== '' && ! DB::table('project_test_projects')->where('code', $base)->exists()) {
            return mb_substr($base, 0, 40);
        }

        return 'CT-OLD-'.str_pad((string) $site->id, 6, '0', STR_PAD_LEFT);
    }

    private function legacyCustomerNeed(object $site): string
    {
        $parts = array_filter([
            $site->quote_config_summary ?? null,
            $site->quote_application_note ?? null,
            $site->quote_scope ?? null,
            $site->system_type ? 'Loại hệ thống: '.$site->system_type : null,
            $site->phase ? 'Pha: '.$site->phase : null,
        ]);

        return $parts ? implode("\n", $parts) : 'Dữ liệu công trình lịch sử được chuyển từ hệ thống cũ.';
    }

    private function fallbackUserId(): int
    {
        $rolesTable = config('permission.table_names.roles', 'roles');
        $modelHasRolesTable = config('permission.table_names.model_has_roles', 'model_has_roles');

        if (Schema::hasTable($rolesTable) && Schema::hasTable($modelHasRolesTable)) {
            $adminId = DB::table('users as u')
                ->join($modelHasRolesTable.' as mr', function ($join): void {
                    $join->on('mr.model_id', '=', 'u.id')->where('mr.model_type', '=', 'App\\Models\\User');
                })
                ->join($rolesTable.' as r', 'r.id', '=', 'mr.role_id')
                ->where('r.name', 'admin')
                ->when(Schema::hasColumn('users', 'is_active'), fn ($q) => $q->where('u.is_active', 1))
                ->orderBy('u.id')
                ->value('u.id');

            if ($adminId) {
                return (int) $adminId;
            }
        }

        return (int) DB::table('users')
            ->when(Schema::hasColumn('users', 'is_active'), fn ($q) => $q->where('is_active', 1))
            ->orderBy('id')
            ->value('id');
    }

    private function validUserId(int $id): ?int
    {
        if ($id <= 0) {
            return null;
        }

        return DB::table('users')->where('id', $id)->exists() ? $id : null;
    }

    private function matchTechnicianId(string $names): ?int
    {
        $candidate = collect(preg_split('/[,;\\\\]+/u', $names))
            ->map(fn ($name) => trim((string) $name))
            ->filter()
            ->first();

        if (! $candidate) {
            return null;
        }

        $id = DB::table('users')->whereRaw('LOWER(name) = ?', [mb_strtolower($candidate)])->value('id');
        return $id ? (int) $id : null;
    }

    private function asDate($value): ?string
    {
        if (! $value) {
            return null;
        }

        try {
            $date = Carbon::parse($value);
            if ((int) $date->year < 2000) {
                return null;
            }
            return $date->format('Y-m-d');
        } catch (Throwable) {
            return null;
        }
    }

    private function asDateTime($value): ?string
    {
        $date = $this->asDate($value);
        return $date ? $date.' 08:00:00' : null;
    }

    private function firstValidDate(array $values): ?string
    {
        foreach ($values as $value) {
            if ($date = $this->asDate($value)) {
                return $date;
            }
        }
        return null;
    }
}
