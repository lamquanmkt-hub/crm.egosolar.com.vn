<?php

declare(strict_types=1);

namespace App\Services\Hr\Kpi;

use App\Support\SchemaCache;
use Illuminate\Support\Facades\DB;

/**
 * Quy tắc đo nguồn KPI HCNS theo kỳ.
 *
 *  - "applied": phiên bản đã được người có quyền duyệt, hiệu lực từ effective_month (≤ kỳ xem).
 *  - "draft"  : chưa có bản áp dụng cho kỳ → dùng bản nháp mới nhất, hoặc mặc định nháp trong config.
 *
 * Chỉ bản "applied" mới được dùng cho điểm chính thức; bản nháp chỉ để xem trước (H-D01…H-D03 chưa chốt).
 */
final class HcnsKpiRules
{
    public const TEMPLATE = 'hcns';

    /** @return array{status: string, version: ?int, id: ?int, label: string, effective_month: ?string, rules: array} */
    public function forMonth(string $month): array
    {
        $defaults = $this->defaults();

        if (SchemaCache::hasTable('hr_kpi_rule_versions')) {
            $applied = DB::table('hr_kpi_rule_versions')
                ->where('template_code', self::TEMPLATE)
                ->where('status', 'applied')
                ->whereNotNull('effective_month')
                ->where('effective_month', '<=', $month)
                ->orderByDesc('effective_month')
                ->orderByDesc('version')
                ->first();
            if ($applied) {
                return $this->wrap('applied', $applied, $defaults);
            }

            $draft = DB::table('hr_kpi_rule_versions')
                ->where('template_code', self::TEMPLATE)
                ->where('status', 'draft')
                ->orderByDesc('version')
                ->first();
            if ($draft) {
                return $this->wrap('draft', $draft, $defaults);
            }
        }

        return [
            'status' => 'draft',
            'version' => null,
            'id' => null,
            'label' => 'Quy tắc nháp mặc định (chưa có phiên bản được duyệt)',
            'effective_month' => null,
            'rules' => $defaults,
            'config' => null,
            'created_by' => null,
        ];
    }

    public static function ready(): bool
    {
        return SchemaCache::hasTable('hr_kpi_rule_versions');
    }

    /** Phiên bản có lưu trọng số/chỉ tiêu/bậc (migration nền tính lương đã áp). */
    public static function configReady(): bool
    {
        return self::ready() && SchemaCache::hasColumn('hr_kpi_rule_versions', 'config');
    }

    /** Tháng đầu tiên trong danh sách đã có bảng lương HR duyệt (kỳ KPI đã chốt), hoặc null. */
    public static function lockedMonth(array $months): ?string
    {
        $months = array_values(array_filter($months, fn ($m) => preg_match('/^\d{4}-\d{2}$/', (string) $m) === 1));
        if ($months === [] || ! SchemaCache::hasTable('hr_payroll_periods')) {
            return null;
        }

        $locked = DB::table('hr_payroll_periods')->whereIn('payroll_month', $months)->where('status', 'approved')->orderBy('payroll_month')->value('payroll_month');

        return $locked ? (string) $locked : null;
    }

    /** Các phiên bản đã lưu, mới nhất trước. */
    public function versions(): \Illuminate\Support\Collection
    {
        if (! self::ready()) {
            return collect();
        }

        return DB::table('hr_kpi_rule_versions as v')
            ->leftJoin('users as c', 'c.id', '=', 'v.created_by')
            ->leftJoin('users as a', 'a.id', '=', 'v.approved_by')
            ->where('v.template_code', self::TEMPLATE)
            ->orderByDesc('v.version')
            ->get(['v.*', 'c.name as creator_name', 'a.name as approver_name']);
    }

    /** Bản nháp đang mở (nếu có) dạng giống forMonth() để xem trước. */
    public function draft(): ?array
    {
        $row = self::ready()
            ? DB::table('hr_kpi_rule_versions')->where('template_code', self::TEMPLATE)->where('status', 'draft')->orderByDesc('version')->first()
            : null;

        return $row ? $this->wrap('draft', $row, $this->defaults()) : null;
    }

    /**
     * Lưu bản nháp (cập nhật bản nháp đang mở hoặc tạo phiên bản mới). Chỉ nhận phương án có trong config.
     * Khóa không gửi lên (form cũ chưa có nhóm quy tắc mới) giữ giá trị của bản nháp đang mở, hoặc mặc định nháp.
     *
     * @param  array<string, array<string, string>>  $input
     */
    public function saveDraft(array $input, int $userId, ?string $note): int
    {
        $rules = [];
        $current = $this->draft()['rules'] ?? $this->defaults();
        foreach ((array) config('hcns_kpi.rules', []) as $criterion => $definitions) {
            foreach ((array) $definitions as $key => $definition) {
                $value = (string) ($input[$criterion][$key] ?? $current[$criterion][$key] ?? '');
                if (! array_key_exists($value, (array) ($definition['options'] ?? []))) {
                    throw \Illuminate\Validation\ValidationException::withMessages(["rules.$criterion.$key" => 'Phương án không hợp lệ.']);
                }
                $rules[$criterion][$key] = $value;
            }
        }

        return DB::transaction(function () use ($rules, $userId, $note) {
            $draft = DB::table('hr_kpi_rule_versions')->where('template_code', self::TEMPLATE)->where('status', 'draft')->lockForUpdate()->orderByDesc('version')->first();
            if ($draft) {
                DB::table('hr_kpi_rule_versions')->where('id', $draft->id)->update([
                    'rules' => json_encode($rules), 'note' => $note, 'created_by' => $userId, 'updated_at' => now(),
                ]);

                return (int) $draft->id;
            }

            return (int) DB::table('hr_kpi_rule_versions')->insertGetId([
                'template_code' => self::TEMPLATE,
                'version' => (int) DB::table('hr_kpi_rule_versions')->where('template_code', self::TEMPLATE)->max('version') + 1,
                'status' => 'draft', 'rules' => json_encode($rules), 'note' => $note, 'created_by' => $userId,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        });
    }

    /**
     * Ảnh chụp trọng số/chỉ tiêu/bậc trả tiền từ cài đặt gốc của bộ KPI (dùng làm điểm xuất phát cho bản nháp).
     */
    public function configFromTemplate(object $template, \Illuminate\Support\Collection $criteria): array
    {
        $out = ['criteria' => [], 'tiers' => array_values((array) $template->payout_tiers),
            'max_achievement' => (float) ($template->max_achievement ?? 120),
            'max_payout_rate' => $template->max_payout_rate !== null ? (float) $template->max_payout_rate : null];
        foreach ($criteria as $c) {
            $out['criteria'][(string) $c->id] = [
                'code' => (string) $c->code, 'name' => (string) $c->name, 'group_name' => $c->group_name,
                'target_text' => $c->target_text, 'weight' => $c->weight !== null ? (float) $c->weight : null,
                'calc_type' => (string) $c->calc_type, 'target_value' => $c->target_value !== null ? (float) $c->target_value : null,
                'unit' => $c->unit, 'is_active' => (bool) ($c->is_active ?? true),
            ];
        }

        return $out;
    }

    /**
     * Áp cấu hình phiên bản lên bộ KPI: trả [template, criteria] với trọng số/chỉ tiêu/bậc của phiên bản.
     * $config null → giữ nguyên cài đặt gốc. Tiêu chí không có trong phiên bản không được tính (bộ của phiên bản là chuẩn).
     *
     * @param  \Illuminate\Support\Collection  $allCriteria  tất cả tiêu chí của bộ (kể cả đang tắt)
     * @return array{0: object, 1: \Illuminate\Support\Collection}
     */
    public function overlay(object $template, \Illuminate\Support\Collection $allCriteria, ?array $config): array
    {
        if (! $config) {
            return [$template, $allCriteria->filter(fn ($c) => (bool) ($c->is_active ?? true))->values()];
        }

        $tpl = clone $template;
        $tpl->payout_tiers = array_values((array) ($config['tiers'] ?? []));
        $tpl->max_achievement = (float) ($config['max_achievement'] ?? $template->max_achievement ?? 120);
        $tpl->max_payout_rate = array_key_exists('max_payout_rate', $config) ? $config['max_payout_rate'] : $template->max_payout_rate;

        $live = $allCriteria->keyBy(fn ($c) => (string) $c->id);
        $criteria = collect((array) ($config['criteria'] ?? []))
            ->filter(fn ($c) => (bool) ($c['is_active'] ?? true))
            ->map(function (array $c, $id) use ($live, $template) {
                $base = $live->has((string) $id) ? clone $live->get((string) $id) : (object) ['id' => (int) $id, 'template_id' => $template->id, 'description' => null, 'sort_order' => 0];
                foreach (['code', 'name', 'group_name', 'target_text', 'weight', 'calc_type', 'target_value', 'unit'] as $key) {
                    $base->{$key} = $c[$key] ?? null;
                }
                $base->is_active = true;

                return $base;
            })
            ->sortBy(fn ($c) => [(int) ($c->sort_order ?? 0), (int) $c->id])
            ->values();

        return [$tpl, $criteria];
    }

    /**
     * Lưu trọng số/chỉ tiêu/bậc vào bản nháp đang mở (tạo bản nháp mới nếu chưa có, giữ quy tắc nguồn hiện có).
     */
    public function saveDraftConfig(array $config, int $userId, string $month): int
    {
        return DB::transaction(function () use ($config, $userId, $month) {
            $draft = DB::table('hr_kpi_rule_versions')->where('template_code', self::TEMPLATE)->where('status', 'draft')->lockForUpdate()->orderByDesc('version')->first();
            if ($draft) {
                DB::table('hr_kpi_rule_versions')->where('id', $draft->id)->update([
                    'config' => json_encode($config, JSON_UNESCAPED_UNICODE), 'created_by' => $userId, 'updated_at' => now(),
                ]);

                return (int) $draft->id;
            }

            return (int) DB::table('hr_kpi_rule_versions')->insertGetId([
                'template_code' => self::TEMPLATE,
                'version' => (int) DB::table('hr_kpi_rule_versions')->where('template_code', self::TEMPLATE)->max('version') + 1,
                'status' => 'draft', 'rules' => json_encode($this->forMonth($month)['rules']),
                'config' => json_encode($config, JSON_UNESCAPED_UNICODE), 'created_by' => $userId,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        });
    }

    /** Mặc định nháp từ config/hcns_kpi.php: [criterion => [rule => value]]. */
    public function defaults(): array
    {
        $out = [];
        foreach ((array) config('hcns_kpi.rules', []) as $criterion => $rules) {
            foreach ((array) $rules as $key => $definition) {
                $out[$criterion][$key] = $definition['draft_default'] ?? array_key_first((array) ($definition['options'] ?? []));
            }
        }

        return $out;
    }

    /** Nhãn phương án đang chọn để hiện cho người đọc. */
    public function describe(string $criterion, array $rules): array
    {
        $lines = [];
        foreach ((array) config('hcns_kpi.rules.'.$criterion, []) as $key => $definition) {
            $value = $rules[$criterion][$key] ?? null;
            $lines[$key] = (string) (($definition['options'] ?? [])[$value] ?? $value ?? '—');
        }

        return $lines;
    }

    private function wrap(string $status, object $row, array $defaults): array
    {
        $stored = json_decode((string) $row->rules, true) ?: [];

        return [
            'status' => $status,
            'version' => (int) $row->version,
            'id' => (int) $row->id,
            'label' => ($status === 'applied' ? 'Phiên bản áp dụng v' : 'Bản nháp v').$row->version
                .($row->effective_month ? ' (từ '.substr((string) $row->effective_month, 5, 2).'/'.substr((string) $row->effective_month, 0, 4).')' : ''),
            'effective_month' => $row->effective_month,
            // Thiếu khóa trong bản lưu thì lấy mặc định để không đổi nghĩa âm thầm khi thêm phương án mới.
            'rules' => array_replace_recursive($defaults, array_intersect_key($stored, $defaults)),
            // Trọng số/chỉ tiêu/bậc của phiên bản (null = dùng cài đặt gốc của bộ KPI).
            'config' => property_exists($row, 'config') && $row->config ? (json_decode((string) $row->config, true) ?: null) : null,
            'created_by' => $row->created_by ? (int) $row->created_by : null,
        ];
    }
}
