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

    /**
     * Lấy cấu hình KPI đã duyệt và có hiệu lực cho một thời điểm cụ thể.
     */
    public static function getActiveForDate($date = null): ?self
    {
        $targetDate = $date ? Carbon::parse($date)->endOfMonth() : now()->endOfMonth();

        $config = static::query()
            ->where('status', 'applied')
            ->where(function ($q) use ($targetDate) {
                $q->whereNull('effective_date')
                    ->orWhere('effective_date', '<=', $targetDate->toDateString());
            })
            ->orderByDesc('effective_date')
            ->orderByDesc('version')
            ->first();

        if (!$config) {
            $config = static::query()
                ->where('status', 'applied')
                ->where('is_current', true)
                ->first();
        }

        return $config;
    }

    /**
     * Tính toán chi tiết các cấu phần lương theo cấu hình này.
     */
    public function computePayout(?float $agreedSalary, ?float $kpiPercent): array
    {
        $struct = $this->salary_structure ?? [];
        $baseRate = isset($struct['base_salary_rate']) ? (float) $struct['base_salary_rate'] : null;
        $kpiRate = isset($struct['kpi_salary_rate']) ? (float) $struct['kpi_salary_rate'] : null;
        $allowExceed = !empty($struct['allow_exceed_100']);
        // Trần hệ số khi vượt 100%: null/0 = KHÔNG giới hạn (vượt bao nhiêu trả bấy nhiêu).
        $maxKpiRate = array_key_exists('max_kpi_rate', $struct)
            ? (($struct['max_kpi_rate'] !== null && (float) $struct['max_kpi_rate'] > 0) ? (float) $struct['max_kpi_rate'] : null)
            : 1.0;
        $roundRule = (string) ($struct['round_rule'] ?? '1000');

        $baseSalary = null;
        $kpiBaseSalary = null;
        $realKpiSalary = null;
        $totalIncome = null;
        $appliedTierRate = null;
        $tierLabel = null;

        if ($agreedSalary !== null && $baseRate !== null) {
            $baseSalary = $this->applyRounding($agreedSalary * $baseRate, $roundRule);
        }

        if ($agreedSalary !== null && $kpiRate !== null) {
            $kpiBaseSalary = $this->applyRounding($agreedSalary * $kpiRate, $roundRule);
        }

        if ($kpiBaseSalary !== null && $kpiPercent !== null) {
            $tiers = $this->payout_tiers ?? [];
            if ($kpiPercent < 0.75) {
                $tierInfo = $tiers['under_75'] ?? ['rate' => 0.0, 'label' => 'Không đạt (< 75%)'];
                $appliedTierRate = (float) ($tierInfo['rate'] ?? 0.0);
                $tierLabel = $tierInfo['label'] ?? 'Không đạt';
            } elseif ($kpiPercent < 0.90) {
                $tierInfo = $tiers['from_75_to_90'] ?? ['rate' => 0.80, 'label' => 'Cần cải thiện (75% - 90%)'];
                $appliedTierRate = (float) ($tierInfo['rate'] ?? 0.80);
                $tierLabel = $tierInfo['label'] ?? 'Cần cải thiện';
            } elseif ($kpiPercent < 1.00) {
                $tierInfo = $tiers['from_90_to_100'] ?? ['rate' => 1.00, 'label' => 'Đạt yêu cầu (90% - 100%)'];
                $appliedTierRate = (float) ($tierInfo['rate'] ?? 1.00);
                $tierLabel = $tierInfo['label'] ?? 'Đạt yêu cầu';
            } else {
                $tierInfo = $tiers['above_100'] ?? ['rate' => 1.00, 'label' => 'Vượt chỉ tiêu (≥ 100%)'];
                $baseMultiplier = (float) ($tierInfo['rate'] ?? 1.00);
                if ($allowExceed) {
                    $appliedTierRate = $kpiPercent * $baseMultiplier;
                    if ($maxKpiRate !== null) {
                        $appliedTierRate = min($maxKpiRate, $appliedTierRate);
                    }
                } else {
                    $appliedTierRate = min(1.0, $baseMultiplier);
                }
                $tierLabel = $tierInfo['label'] ?? 'Vượt chỉ tiêu';
            }

            $rawReal = $kpiBaseSalary * $appliedTierRate;
            $realKpiSalary = $this->applyRounding($rawReal, $roundRule);
        }

        if ($baseSalary !== null && $realKpiSalary !== null) {
            $totalIncome = $baseSalary + $realKpiSalary;
        }

        return [
            'base_rate' => $baseRate,
            'kpi_rate' => $kpiRate,
            'base_salary' => $baseSalary,
            'kpi_base_salary' => $kpiBaseSalary,
            'applied_tier_rate' => $appliedTierRate,
            'tier_label' => $tierLabel,
            'real_kpi_salary' => $realKpiSalary,
            'total_income' => $totalIncome,
            'round_rule' => $roundRule,
        ];
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
