<?php

namespace App\Console\Commands;

use App\Http\Controllers\Synced\TechnicalKpi\TechnicalPayrollController;
use App\Models\TechnicalKpiConfig;
use App\Services\TechnicalKpi\KpiStandardEvaluator;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Chuẩn bị (KHÔNG duyệt) bản nháp cấu hình KPI kỹ thuật trả tiền theo % KPI thực đạt, hiệu lực 01/10/2026.
 *
 * Kế thừa nguyên bản đang áp dụng cho kỳ 10/2026: tỷ lệ cố định/quỹ KPI, tiêu chí & trọng số, làm tròn, cờ/trần vượt
 * và bảng bậc (chỉ để tham chiếu). Chỉ đổi `payout_mode`. Mặc định xem trước; `--apply` mới ghi bản nháp.
 * Chạy lại không tạo trùng (nhận bản nháp cũ theo mã đánh dấu). Duyệt áp dụng do người có quyền bấm trên trang Cài đặt KPI.
 */
class PrepareKpiPayoutDraft extends Command
{
    protected $signature = 'kpi:payout-draft
        {--apply : Ghi bản nháp (mặc định chỉ xem trước)}
        {--effective=2026-10-01 : Ngày hiệu lực của bản nháp}';

    protected $description = 'Chuẩn bị bản nháp cấu hình KPI kỹ thuật trả tiền theo % thực đạt, kế thừa bản đang áp dụng (không duyệt).';

    public const MARKER = 'KPI-SALARY-CLOSEOUT-20261007';

    private const ALLOWED_DATABASES = ['local' => 'crm_egosolar_test', 'testing' => 'egosolar_test'];

    public function handle(): int
    {
        $env = app()->environment();
        $db = (string) DB::connection()->getDatabaseName();
        $host = (string) config('database.connections.'.config('database.default').'.host');
        if ((self::ALLOWED_DATABASES[$env] ?? null) !== $db || ! in_array($host, ['127.0.0.1', 'localhost', '::1'], true)) {
            $this->error('Dừng: chỉ chạy ở local với DB crm_egosolar_test (hoặc testing với egosolar_test). Hiện: '.$env.' / '.$db.' @ '.$host.'.');

            return self::FAILURE;
        }

        $effective = (string) $this->option('effective');
        $base = TechnicalKpiConfig::getActiveForDate($effective);
        if (! $base) {
            $this->error('Dừng: chưa có cấu hình đã duyệt có hiệu lực tại '.$effective.' để kế thừa.');

            return self::FAILURE;
        }
        if ($base->payoutMode() === TechnicalKpiConfig::PAYOUT_PROPORTIONAL) {
            $this->info('Bản đang áp dụng v'.$base->version.' đã trả theo % thực đạt — không cần bản nháp mới.');

            return self::SUCCESS;
        }

        $existing = TechnicalKpiConfig::query()->where('status', 'draft')->where('notes', 'like', '%'.self::MARKER.'%')->orderByDesc('id')->first();
        $struct = (array) ($base->salary_structure ?? []);
        $struct['payout_mode'] = TechnicalKpiConfig::PAYOUT_PROPORTIONAL;
        $struct['effective_date'] = $effective;

        $draft = $existing ?? new TechnicalKpiConfig([
            'version' => ((int) TechnicalKpiConfig::max('version')) + 1,
            'version_name' => 'Trả tiền KPI theo % thực đạt (từ '.date('m/Y', strtotime($effective)).')',
            'status' => 'draft',
            'is_current' => false,
            'effective_date' => $effective,
            'salary_structure' => $struct,
            'criteria_config' => $base->criteria_config,
            'payout_tiers' => $base->payout_tiers,
            'notes' => '['.self::MARKER.'] Kế thừa v'.$base->version.' ('.$base->version_name.'): giữ tỷ lệ cố định/quỹ KPI, tiêu chí & trọng số, '
                .'làm tròn, cờ/trần vượt; chỉ đổi cách trả tiền sang Quỹ KPI × % KPI thực đạt. Bản nháp chờ Ban lãnh đạo duyệt.',
        ]);

        $weightsOk = $this->weightsMatch((array) $draft->criteria_config, substr($effective, 0, 7));
        $this->info(($existing ? 'Đã có bản nháp v'.$existing->version.' (#'.$existing->id.') — giữ nguyên.' : 'Bản nháp mới v'.$draft->version.'.')
            .' Kế thừa v'.$base->version.' (#'.$base->id.', '.$base->status.'). Trọng số tiêu chí khớp bộ kỳ: '.($weightsOk ? 'có' : 'KHÔNG'));
        $diff = array_diff_assoc(
            array_map('json_encode', (array) $draft->salary_structure),
            array_map('json_encode', (array) $base->salary_structure)
        );
        $this->line('Khác bản gốc ở salary_structure: '.json_encode(array_keys($diff)));

        $agreed = TechnicalPayrollController::kpiAgreedSalary(98, substr($effective, 0, 7)) ?? 15000000.0;
        foreach ([0.875, 0.75, 0.6] as $pct) {
            $old = $base->computePayout($agreed, $pct);
            $new = $draft->computePayout($agreed, $pct);
            $this->line(sprintf('Lương %s · KPI %s%%: bản gốc %s đ (hệ số %s) → bản nháp %s đ trước làm tròn %s đ (hệ số %s)',
                number_format($agreed, 0, ',', '.'), $pct * 100, number_format((float) $old['real_kpi_salary'], 0, ',', '.'), $old['payout_rate'],
                number_format((float) $new['real_kpi_salary'], 0, ',', '.'), number_format((float) $new['real_kpi_salary_raw'], 2, ',', '.'), $new['payout_rate']));
        }

        if (! $existing && $this->option('apply')) {
            if (! $weightsOk) {
                $this->error('Dừng: tiêu chí kế thừa không khớp bộ trọng số của kỳ; không tạo bản nháp sai.');

                return self::FAILURE;
            }
            $draft->save();
            $this->info('Đã lưu bản nháp v'.$draft->version.' (#'.$draft->id.'). Chưa duyệt — trang chính vẫn dùng v'.$base->version.'.');
        } elseif (! $existing) {
            $this->warn('Chưa ghi gì. Thêm --apply để lưu bản nháp.');
        }

        return self::SUCCESS;
    }

    private function weightsMatch(array $criteria, string $month): bool
    {
        $active = collect($criteria)->filter(fn ($c) => ! empty($c['is_calculated']) && ($c['status'] ?? '') === 'applied')->keyBy('code');
        $expected = collect(app(KpiStandardEvaluator::class)->forMonth($month)->criteria())->filter(fn ($c) => $c['weight'] !== null);

        return $active->count() === $expected->count()
            && $expected->every(fn ($c) => $active->has($c['code']) && abs((float) $active[$c['code']]['weight'] - (float) $c['weight']) < 0.0001);
    }
}
