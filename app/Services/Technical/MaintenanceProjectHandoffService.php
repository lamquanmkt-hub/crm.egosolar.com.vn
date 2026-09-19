<?php

namespace App\Services\Technical;

use App\Models\SolarMaintenanceProfile;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class MaintenanceProjectHandoffService
{
    private static bool $booted = false;

    public function bootObservers(): void
    {
        if (self::$booted) {
            return;
        }
        self::$booted = true;

        foreach ([
            \App\Models\ProjectTest\Project::class,
            \App\Models\Projects\Site::class,
            \App\Models\Site::class,
        ] as $class) {
            if (! class_exists($class) || ! is_subclass_of($class, Model::class)) {
                continue;
            }

            $class::saved(function (Model $model): void {
                try {
                    app(self::class)->syncModel($model);
                } catch (\Throwable $e) {
                    report($e);
                }
            });
        }
    }

    public function syncModel(Model $model): ?SolarMaintenanceProfile
    {
        $attributes = $model->getAttributes();
        $table = $model->getTable();

        $sourceType = str_contains($table, 'project_test') ? 'project_test' : 'site';
        return $this->syncArray($sourceType, (array) $attributes, $table);
    }


    public function syncExistingCompletedThrottled(): void
    {
        try {
            if (Cache::add('om_v10_handoff_sync_lock', 1, now()->addMinutes(2))) {
                $this->syncExistingCompleted();
            }
        } catch (\Throwable $e) {
            report($e);
        }
    }

    public function syncExistingCompleted(): array
    {
        $stats = ['project_test' => 0, 'site' => 0, 'skipped' => 0];

        foreach ([
            ['table' => 'project_test_projects', 'type' => 'project_test'],
            ['table' => 'sites', 'type' => 'site'],
        ] as $source) {
            if (! Schema::hasTable($source['table'])) {
                continue;
            }

            DB::table($source['table'])
                ->orderBy('id')
                ->chunkById(200, function ($rows) use (&$stats, $source): void {
                    foreach ($rows as $row) {
                        try {
                            $profile = $this->syncArray($source['type'], (array) $row, $source['table']);
                            if ($profile) {
                                $stats[$source['type']]++;
                            } else {
                                $stats['skipped']++;
                            }
                        } catch (\Throwable $e) {
                            $stats['skipped']++;
                            report($e);
                        }
                    }
                });
        }

        return $stats;
    }

    public function ensureForSiteId(int $siteId, ?int $actorId = null): ?SolarMaintenanceProfile
    {
        if ($siteId <= 0 || ! Schema::hasTable('sites')) {
            return null;
        }

        $row = DB::table('sites')->where('id', $siteId)->first();
        if (! $row) {
            return null;
        }

        return $this->upsertProfile('site', (array) $row, 'sites', true, $actorId);
    }

    private function syncArray(string $sourceType, array $row, string $table): ?SolarMaintenanceProfile
    {
        if (! $this->isCompleted($row)) {
            return null;
        }

        return $this->upsertProfile($sourceType, $row, $table, false, null);
    }

    private function upsertProfile(
        string $sourceType,
        array $row,
        string $table,
        bool $manualFallback,
        ?int $actorId
    ): SolarMaintenanceProfile {
        $sourceId = (int) ($row['id'] ?? 0);
        $projectId = $sourceType === 'project_test' ? $sourceId : null;
        $siteId = $sourceType === 'site'
            ? $sourceId
            : ((int) ($row['legacy_site_id'] ?? $row['site_id'] ?? 0) ?: null);

        $existing = SolarMaintenanceProfile::query()
            ->where(function ($q) use ($projectId, $siteId, $sourceType, $sourceId) {
                $hasCondition = false;
                if ($projectId) {
                    $q->where('project_id', $projectId);
                    $hasCondition = true;
                }
                if ($siteId) {
                    $method = $hasCondition ? 'orWhere' : 'where';
                    $q->{$method}('site_id', $siteId);
                    $hasCondition = true;
                }
                if ($sourceId) {
                    $method = $hasCondition ? 'orWhere' : 'where';
                    $q->{$method}(function ($source) use ($sourceType, $sourceId) {
                        $source->where('source_type', $sourceType)->where('source_id', $sourceId);
                    });
                }
            })
            ->first();

        $siteRow = null;
        if ($siteId && Schema::hasTable('sites')) {
            $siteRow = DB::table('sites')->where('id', $siteId)->first();
        }
        $siteData = $siteRow ? (array) $siteRow : [];

        $status = $this->stringValue($row, ['status','stage','current_stage','progress_status']);
        $completedAt = $this->value($row, ['completed_at','accepted_at','acceptance_at','handover_at','handed_over_at','warranty_started_at'])
            ?: $this->value($siteData, ['completed_at','accepted_at','handover_at']);

        $data = [
            'company_id' => $this->intValue($row, ['company_id']) ?: $this->intValue($siteData, ['company_id']) ?: null,
            'source_type' => $sourceType,
            'source_id' => $sourceId ?: null,
            'project_id' => $projectId,
            'site_id' => $siteId,
            'source_status' => $status ?: null,
            'site_name' => $this->stringValue($row, ['name','project_name','title'])
                ?: $this->stringValue($siteData, ['name'])
                ?: 'Công trình chưa đặt tên',
            'customer_name' => $this->stringValue($row, ['customer_name','contact_name','client_name'])
                ?: $this->stringValue($siteData, ['customer_name','contact_name']),
            'customer_phone' => $this->stringValue($row, ['customer_phone','contact_phone','phone'])
                ?: $this->stringValue($siteData, ['contact_phone','phone']),
            'address' => $this->stringValue($row, ['address','project_address','site_address'])
                ?: $this->stringValue($siteData, ['address']),
            'system_kwp' => $this->numericValue($row, ['system_kwp','capacity_kwp','kwp'])
                ?: $this->numericValue($siteData, ['system_kwp','capacity_kwp']),
            'inverter_info' => $this->stringValue($row, ['inverter_info','inverter','device_info'])
                ?: $this->stringValue($siteData, ['inverter_info']),
            'handover_date' => $this->dateValue($row, ['handover_date','accepted_at','acceptance_at','completed_at'])
                ?: $this->dateValue($siteData, ['completed_at','installed_at']),
            'warranty_start_date' => $this->dateValue($row, ['warranty_start_date','warranty_from','warranty_started_at'])
                ?: $this->dateValue($siteData, ['warranty_from','installed_at']),
            'warranty_end_date' => $this->dateValue($row, ['warranty_end_date','warranty_to','warranty_expired_at'])
                ?: $this->dateValue($siteData, ['warranty_to']),
            'source_completed_at' => $completedAt ?: now(),
            'created_by' => $actorId ?: $this->intValue($row, ['created_by','sales_id','owner_id']) ?: null,
        ];

        if ($existing) {
            $existing->fill(array_filter($data, fn ($v) => $v !== null));
            if (! $existing->status) {
                $existing->status = 'waiting_plan';
            }
            $existing->save();
            $this->adoptExistingSchedules($existing);
            return $existing->fresh();
        }

        $data['status'] = 'waiting_plan';
        if ($manualFallback) {
            $data['source_type'] = 'site';
        }

        $profile = SolarMaintenanceProfile::create($data);
        $this->adoptExistingSchedules($profile);
        return $profile->fresh();
    }

    private function adoptExistingSchedules(SolarMaintenanceProfile $profile): void
    {
        if (! Schema::hasTable('solar_maintenance_schedules') || ! Schema::hasColumn('solar_maintenance_schedules', 'maintenance_profile_id')) {
            return;
        }

        $query = DB::table('solar_maintenance_schedules')->whereNull('maintenance_profile_id');
        $query->where(function ($q) use ($profile) {
            $has = false;
            if ($profile->project_id && Schema::hasColumn('solar_maintenance_schedules', 'project_id')) {
                $q->orWhere('project_id', $profile->project_id);
                $has = true;
            }
            if ($profile->site_id && Schema::hasColumn('solar_maintenance_schedules', 'site_id')) {
                $q->orWhere('site_id', $profile->site_id);
                $has = true;
            }
            if (! $has) {
                $q->whereRaw('1 = 0');
            }
        });

        $count = (clone $query)->count();
        if ($count <= 0) {
            return;
        }

        $query->update(['maintenance_profile_id' => $profile->id, 'updated_at' => now()]);
        $profile->forceFill(['status' => 'active', 'planned_at' => $profile->planned_at ?: now()])->save();
    }

    private function isCompleted(array $row): bool
    {
        foreach (['completed_at','accepted_at','acceptance_at','handover_at','handed_over_at','warranty_started_at'] as $field) {
            if (! empty($row[$field])) {
                return true;
            }
        }

        $status = $this->normalize($this->stringValue($row, [
            'status','stage','current_stage','progress_status','project_status','technical_status',
        ]));

        if ($status === '') {
            return false;
        }

        foreach ([
            'completed','complete','done','closed','finished',
            'hoan_thanh','da_hoan_thanh','da_nghiem_thu','nghiem_thu_hoan_thanh',
            'ban_giao','da_ban_giao','handover','handed_over','warranty','bao_hanh',
        ] as $needle) {
            if ($status === $needle || str_contains($status, $needle)) {
                return true;
            }
        }

        return false;
    }

    private function value(array $row, array $fields): mixed
    {
        foreach ($fields as $field) {
            if (array_key_exists($field, $row) && $row[$field] !== null && $row[$field] !== '') {
                return $row[$field];
            }
        }
        return null;
    }

    private function stringValue(array $row, array $fields): string
    {
        $value = $this->value($row, $fields);
        return is_scalar($value) ? trim((string) $value) : '';
    }

    private function intValue(array $row, array $fields): int
    {
        return (int) ($this->value($row, $fields) ?: 0);
    }

    private function numericValue(array $row, array $fields): ?float
    {
        $value = $this->value($row, $fields);
        return is_numeric($value) ? (float) $value : null;
    }

    private function dateValue(array $row, array $fields): ?string
    {
        $value = $this->value($row, $fields);
        if (! $value) {
            return null;
        }
        try {
            return \Carbon\Carbon::parse($value)->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }

    private function normalize(string $value): string
    {
        $value = Str::ascii(mb_strtolower(trim($value), 'UTF-8'));
        $value = preg_replace('/[^a-z0-9]+/', '_', $value) ?: '';
        return trim($value, '_');
    }
}
