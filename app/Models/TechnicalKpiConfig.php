<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

class TechnicalKpiConfig extends Model
{
    use HasFactory;

    protected $table = 'technical_kpi_configs';

    protected $fillable = [
        'version',
        'version_name',
        'status',
        'is_current',
        'effective_date',
        'salary_structure',
        'criteria_config',
        'payout_tiers',
        'notes',
        'created_by',
        'updated_by',
        'approved_by',
        'approved_at',
    ];

    protected $casts = [
        'version' => 'integer',
        'is_current' => 'boolean',
        'effective_date' => 'date',
        'salary_structure' => 'array',
        'criteria_config' => 'array',
        'payout_tiers' => 'array',
        'approved_at' => 'datetime',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /** Trạng thái của phiên bản từng được Ban lãnh đạo duyệt (đang áp dụng hoặc đã bị thay thế). */
    public const APPROVED_STATUSES = ['applied', 'archived'];

    /**
     * Lấy cấu hình KPI đã duyệt và có hiệu lực cho tháng chứa $date.
     *
     * Khi duyệt phiên bản mới, phiên bản cũ chuyển "archived" nhưng vẫn là quy tắc của các kỳ trước
     * ngày hiệu lực mới — nên được chọn lại cho kỳ cũ. Phiên bản có ngày hiệu lực sau kỳ KHÔNG bao giờ
     * được dùng hồi tố; không có phiên bản phù hợp thì trả null để màn hình báo "chưa có cấu hình".
     */
    public static function getActiveForDate($date = null): ?self
    {
        $targetDate = $date ? Carbon::parse($date)->endOfMonth() : now()->endOfMonth();

        return static::query()
            ->whereIn('status', self::APPROVED_STATUSES)
            ->whereNotNull('approved_at')
            ->where(function ($q) use ($targetDate) {
                $q->whereNull('effective_date')
                    ->orWhere('effective_date', '<=', $targetDate->toDateString());
            })
            // Có ngày hiệu lực được ưu tiên hơn bản không ghi ngày; cùng ngày thì bản duyệt sau thắng.
            ->orderByRaw('effective_date IS NULL')
            ->orderByDesc('effective_date')
            ->orderByDesc('approved_at')
            ->orderByDesc('version')
            ->first();
    }

    /** Nhãn ngắn của bộ quy tắc để ghi kèm kết quả KPI. */
    public function ruleLabel(): string
    {
        return 'v'.$this->version
            .($this->version_name ? ' — '.$this->version_name : '')
            .($this->effective_date ? ' (hiệu lực từ '.$this->effective_date->format('d/m/Y').')' : '');
    }

    /** Cách quy đổi KPI ra tiền: nhân thẳng % KPI thực đạt. */
    public const PAYOUT_PROPORTIONAL = 'proportional';

    /** Cách quy đổi KPI ra tiền: hệ số theo bậc (< 75%, 75–90%, 90–100%, ≥ 100%). */
    public const PAYOUT_TIERED = 'tiered';

    public const PAYOUT_MODE_LABELS = [
        self::PAYOUT_PROPORTIONAL => 'Theo % KPI thực đạt',
        self::PAYOUT_TIERED => 'Theo bậc quy đổi',
    ];

    /** Cấu hình lịch sử không ghi chế độ = trả theo bậc như lúc được duyệt. */
    public function payoutMode(): string
    {
        $mode = (string) (($this->salary_structure ?? [])['payout_mode'] ?? '');

        return array_key_exists($mode, self::PAYOUT_MODE_LABELS) ? $mode : self::PAYOUT_TIERED;
    }

    /**
     * Tính toán chi tiết các cấu phần lương theo cấu hình này.
     *
     * Tiền KPI = Quỹ KPI × hệ số từ payoutRate() (chế độ tỷ lệ: hệ số = % KPI thực đạt; chế độ bậc: hệ số của bậc).
     * Trả về cả số trước làm tròn (`*_raw`) và sau làm tròn theo quy tắc của cấu hình.
     */
    public function computePayout(?float $agreedSalary, ?float $kpiPercent): array
    {
        $struct = $this->salary_structure ?? [];
        $baseRate = isset($struct['base_salary_rate']) ? (float) $struct['base_salary_rate'] : null;
        $kpiRate = isset($struct['kpi_salary_rate']) ? (float) $struct['kpi_salary_rate'] : null;
        $roundRule = (string) ($struct['round_rule'] ?? '1000');

        $baseSalary = null;
        $kpiBaseSalary = null;
        $realKpiSalary = null;
        $totalIncome = null;
        $appliedTierRate = null;
        $tierLabel = null;
        $baseRaw = null;
        $realRaw = null;
        $totalRaw = null;

        if ($agreedSalary !== null && $baseRate !== null) {
            $baseRaw = $agreedSalary * $baseRate;
            $baseSalary = $this->applyRounding($baseRaw, $roundRule);
        }

        if ($agreedSalary !== null && $kpiRate !== null) {
            $kpiBaseSalary = $this->applyRounding($agreedSalary * $kpiRate, $roundRule);
        }

        // null (thiếu dữ liệu KPI) khác 0%: không tự thành 0 đồng.
        if ($kpiBaseSalary !== null && $kpiPercent !== null) {
            ['rate' => $appliedTierRate, 'label' => $tierLabel] = $this->payoutRate($kpiPercent);

            $realRaw = $kpiBaseSalary * $appliedTierRate;
            $realKpiSalary = $this->applyRounding($realRaw, $roundRule);
        }

        if ($baseSalary !== null && $realKpiSalary !== null) {
            $totalIncome = $baseSalary + $realKpiSalary;
            $totalRaw = $baseRaw + $realRaw;
        }

        return [
            'payout_mode' => $this->payoutMode(),
            'payout_mode_label' => self::PAYOUT_MODE_LABELS[$this->payoutMode()],
            'base_rate' => $baseRate,
            'kpi_rate' => $kpiRate,
            'base_salary' => $baseSalary,
            'kpi_base_salary' => $kpiBaseSalary,
            // Hệ số nhân với Quỹ KPI (giữ tên cũ để tương thích).
            'applied_tier_rate' => $appliedTierRate,
            'payout_rate' => $appliedTierRate,
            'tier_label' => $tierLabel,
            'real_kpi_salary_raw' => $realRaw,
            'real_kpi_salary' => $realKpiSalary,
            'total_income_raw' => $totalRaw,
            'total_income' => $totalIncome,
            'round_rule' => $roundRule,
        ];
    }

    /**
     * Hệ số lương KPI được hưởng theo % KPI (dạng hệ số, 0.875 = 87,5%) — điểm quy đổi DUY NHẤT, dùng chung cho
     * trang KPI kỹ thuật, xem trước cấu hình và Bảng lương nhân sự.
     *
     * - Chế độ tỷ lệ: hệ số = % KPI thực đạt (0–100%); trên 100% theo cờ/trần vượt đã cấu hình, không thêm thưởng.
     * - Chế độ bậc (mặc định của cấu hình lịch sử): hệ số theo bậc như trước.
     *
     * @return array{rate: float, label: string}
     */
    public function payoutRate(float $kpiPercent): array
    {
        $struct = $this->salary_structure ?? [];
        $tiers = $this->payout_tiers ?? [];
        $allowExceed = !empty($struct['allow_exceed_100']);
        // Trần hệ số khi vượt 100%: null/0 = KHÔNG giới hạn (vượt bao nhiêu trả bấy nhiêu).
        $maxKpiRate = array_key_exists('max_kpi_rate', $struct)
            ? (($struct['max_kpi_rate'] !== null && (float) $struct['max_kpi_rate'] > 0) ? (float) $struct['max_kpi_rate'] : null)
            : 1.0;

        if ($this->payoutMode() === self::PAYOUT_PROPORTIONAL) {
            $rate = max(0.0, $kpiPercent);
            if ($rate > 1.0) {
                $rate = $allowExceed ? ($maxKpiRate !== null ? min($maxKpiRate, $rate) : $rate) : 1.0;
            }
            $pct = rtrim(rtrim(number_format($kpiPercent * 100, 2, ',', '.'), '0'), ',');

            return ['rate' => $rate, 'label' => 'Theo % KPI thực đạt ('.$pct.'%)'];
        }

        if ($kpiPercent < 0.75) {
            $tierInfo = $tiers['under_75'] ?? ['rate' => 0.0, 'label' => 'Không đạt (< 75%)'];

            return ['rate' => (float) ($tierInfo['rate'] ?? 0.0), 'label' => $tierInfo['label'] ?? 'Không đạt'];
        }

        if ($kpiPercent < 0.90) {
            $tierInfo = $tiers['from_75_to_90'] ?? ['rate' => 0.80, 'label' => 'Cần cải thiện (75% - 90%)'];

            return ['rate' => (float) ($tierInfo['rate'] ?? 0.80), 'label' => $tierInfo['label'] ?? 'Cần cải thiện'];
        }

        if ($kpiPercent < 1.00) {
            $tierInfo = $tiers['from_90_to_100'] ?? ['rate' => 1.00, 'label' => 'Đạt yêu cầu (90% - 100%)'];

            return ['rate' => (float) ($tierInfo['rate'] ?? 1.00), 'label' => $tierInfo['label'] ?? 'Đạt yêu cầu'];
        }

        $tierInfo = $tiers['above_100'] ?? ['rate' => 1.00, 'label' => 'Vượt chỉ tiêu (≥ 100%)'];
        $baseMultiplier = (float) ($tierInfo['rate'] ?? 1.00);
        if ($allowExceed) {
            $rate = $kpiPercent * $baseMultiplier;
            if ($maxKpiRate !== null) {
                $rate = min($maxKpiRate, $rate);
            }
        } else {
            $rate = min(1.0, $baseMultiplier);
        }

        return ['rate' => $rate, 'label' => $tierInfo['label'] ?? 'Vượt chỉ tiêu'];
    }

    /**
     * Quy tắc làm tròn số tiền.
     */
    public function applyRounding(float $amount, string $rule): float
    {
        return match ($rule) {
            '1000' => (float) (round($amount / 1000) * 1000),
            '10000' => (float) (round($amount / 10000) * 10000),
            'ceil_1000' => (float) (ceil($amount / 1000) * 1000),
            default => (float) round($amount),
        };
    }
}
