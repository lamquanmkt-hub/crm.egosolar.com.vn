<?php

namespace App\Http\Controllers\Marketing;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\User;
use App\Models\Marketing\MarketingKpiPaySetting;
use App\Models\Marketing\MarketingKpiPayRule;
use App\Models\Marketing\MarketingKpiPayActual;
use Carbon\Carbon;

class KpiPayrollController extends Controller
{
    public function index(Request $request)
    {
        return view('marketing.kpi_payroll.index');
    }

    // Trang settings cho marketing_manager/admin
    public function settings(Request $request)
    {
        $period = $request->get('period') ?? Carbon::now()->format('Y-m');

        $users = User::query()
            ->join('model_has_roles', 'users.id', '=', 'model_has_roles.model_id')
            ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
            ->where('model_has_roles.model_type', User::class)
            ->whereIn('roles.name', ['marketing', 'marketing_manager'])
            ->select('users.*')
            ->distinct()
            ->orderBy('users.id')
            ->get();

        $settings = MarketingKpiPaySetting::where('period', $period)
            ->get()
            ->keyBy('user_id');

        $rule = MarketingKpiPayRule::where('period', $period)->first();

        if (!$rule) {
            $rule = new MarketingKpiPayRule();
            $rule->period = $period;

            $rule->weight_review = 40;
            $rule->weight_ai = 30;
            $rule->weight_post = 30;

            $rule->bonus_over_review = 3;
            $rule->bonus_over_ai = 1;
            $rule->bonus_over_post = 0.5;

            $rule->bonus_config = [
                'trend_video' => [
                    'tiers' => [
                        ['min_view' => 30000, 'min_engagement' => 1000, 'reward' => 1000000],
                        ['min_view' => 100000, 'min_engagement' => 5000, 'reward' => 5000000],
                    ],
                ],
                'livestream' => [
                    'min_duration_min' => 30,
                    'lead_reward' => 8000,
                    'tiers' => [
                        ['min_view' => 50000, 'min_engagement' => 300, 'reward' => 1000000],
                        ['min_view' => 200000, 'min_engagement' => 1000, 'reward' => 3000000],
                    ],
                ],
                'anti_fraud' => [
                    'penalty_percent' => 70,
                    'keep_percent' => 30,
                ],
            ];
        }

        $bonusConfig = (array)($rule->bonus_config ?? []);

        // Tương thích dữ liệu cũ (livestream.m1/m2) -> livestream.tiers
        $ls = (array) data_get($bonusConfig, 'livestream', []);
        if (!isset($ls['tiers']) || !is_array($ls['tiers'])) {
            $tiers = [];

            $m1 = (array) data_get($ls, 'm1', []);
            $m2 = (array) data_get($ls, 'm2', []);

            if (!empty($m1)) {
                $tiers[] = [
                    'min_view' => (int)($m1['min_view'] ?? 0),
                    'min_engagement' => (int)($m1['min_engagement'] ?? 0),
                    'reward' => (int)($m1['reward'] ?? 0),
                ];
            }
            if (!empty($m2)) {
                $tiers[] = [
                    'min_view' => (int)($m2['min_view'] ?? 0),
                    'min_engagement' => (int)($m2['min_engagement'] ?? 0),
                    'reward' => (int)($m2['reward'] ?? 0),
                ];
            }

            if (count($tiers) === 0) {
                $tiers = [
                    ['min_view' => 50000, 'min_engagement' => 300, 'reward' => 1000000],
                    ['min_view' => 200000, 'min_engagement' => 1000, 'reward' => 3000000],
                ];
            }

            $ls['tiers'] = $tiers;
            unset($ls['m1'], $ls['m2']);
            $bonusConfig['livestream'] = $ls;
        }

        // đảm bảo trend_video.tiers có
        $tv = (array) data_get($bonusConfig, 'trend_video', []);
        if (!isset($tv['tiers']) || !is_array($tv['tiers']) || count($tv['tiers']) === 0) {
            $tv['tiers'] = [
                ['min_view' => 30000, 'min_engagement' => 1000, 'reward' => 1000000],
            ];
            $bonusConfig['trend_video'] = $tv;
        }

        return view('marketing.kpi_payroll.settings', compact(
            'period',
            'users',
            'settings',
            'rule',
            'bonusConfig'
        ));
    }

    public function saveSettings(Request $request)
    {
        $request->validate([
            'period' => ['required', 'regex:/^\d{4}-\d{2}$/'],
            'rows'   => ['nullable', 'array'],

            'rule' => ['nullable', 'array'],
            'rule.weight_review' => ['nullable', 'numeric', 'min:0'],
            'rule.weight_ai'     => ['nullable', 'numeric', 'min:0'],
            'rule.weight_post'   => ['nullable', 'numeric', 'min:0'],

            'rule.bonus_over_review' => ['nullable', 'numeric', 'min:0'],
            'rule.bonus_over_ai'     => ['nullable', 'numeric', 'min:0'],
            'rule.bonus_over_post'   => ['nullable', 'numeric', 'min:0'],

            'bonus_config' => ['nullable', 'array'],
        ]);

        $period = $request->input('period');

        $rows = $request->input('rows', []);
        $ruleInput  = $request->input('rule', []);
        $bonusInput = $request->input('bonus_config', []);

        $ruleData = [
            'weight_review'      => (float)($ruleInput['weight_review'] ?? 40),
            'weight_ai'          => (float)($ruleInput['weight_ai'] ?? 30),
            'weight_post'        => (float)($ruleInput['weight_post'] ?? 30),

            'bonus_over_review'  => (float)($ruleInput['bonus_over_review'] ?? 3),
            'bonus_over_ai'      => (float)($ruleInput['bonus_over_ai'] ?? 1),
            'bonus_over_post'    => (float)($ruleInput['bonus_over_post'] ?? 0.5),
        ];

        $bonusConfig = [];

        // TREND VIDEO (tiers dynamic)
        $tiers = data_get($bonusInput, 'trend_video.tiers', []);
        $tiersClean = [];
        if (is_array($tiers)) {
            foreach ($tiers as $t) {
                $minView = (int)($t['min_view'] ?? 0);
                $minEng  = (int)($t['min_engagement'] ?? 0);
                $reward  = (int)($t['reward'] ?? 0);
                if ($minView === 0 && $minEng === 0 && $reward === 0) continue;

                $tiersClean[] = [
                    'min_view' => max(0, $minView),
                    'min_engagement' => max(0, $minEng),
                    'reward' => max(0, $reward),
                ];
            }
        }
        if (count($tiersClean) === 0) {
            $tiersClean = [
                ['min_view' => 30000, 'min_engagement' => 1000, 'reward' => 1000000],
            ];
        }
        usort($tiersClean, fn($a,$b) => ($a['min_view'] <=> $b['min_view']));

        $bonusConfig['trend_video'] = [
            'tiers' => array_values($tiersClean),
        ];

        // LIVESTREAM (tiers dynamic)
        $lsMinDuration = (int) data_get($bonusInput, 'livestream.min_duration_min', 30);
        $lsLeadReward  = (int) data_get($bonusInput, 'livestream.lead_reward', 8000);

        $lsTiers = data_get($bonusInput, 'livestream.tiers', null);

        // tương thích post cũ m1/m2
        if ($lsTiers === null) {
            $m1 = (array) data_get($bonusInput, 'livestream.m1', []);
            $m2 = (array) data_get($bonusInput, 'livestream.m2', []);
            $lsTiers = [];
            if (!empty($m1)) $lsTiers[] = $m1;
            if (!empty($m2)) $lsTiers[] = $m2;
        }

        $lsTiersClean = [];
        if (is_array($lsTiers)) {
            foreach ($lsTiers as $t) {
                $minView = (int)($t['min_view'] ?? 0);
                $minEng  = (int)($t['min_engagement'] ?? 0);
                $reward  = (int)($t['reward'] ?? 0);
                if ($minView === 0 && $minEng === 0 && $reward === 0) continue;

                $lsTiersClean[] = [
                    'min_view' => max(0, $minView),
                    'min_engagement' => max(0, $minEng),
                    'reward' => max(0, $reward),
                ];
            }
        }
        if (count($lsTiersClean) === 0) {
            $lsTiersClean = [
                ['min_view' => 50000, 'min_engagement' => 300, 'reward' => 1000000],
            ];
        }
        usort($lsTiersClean, fn($a,$b) => ($a['min_view'] <=> $b['min_view']));

        $bonusConfig['livestream'] = [
            'min_duration_min' => max(0, $lsMinDuration),
            'lead_reward'      => max(0, $lsLeadReward),
            'tiers'            => array_values($lsTiersClean),
        ];

        // ANTI FRAUD
        $penalty = (int) data_get($bonusInput, 'anti_fraud.penalty_percent', 70);
        $keep    = (int) data_get($bonusInput, 'anti_fraud.keep_percent', 30);

        $penalty = max(0, min(100, $penalty));
        $keep    = max(0, min(100, $keep));
        if ($keep + $penalty !== 100) {
            $keep = 100 - $penalty;
            $keep = max(0, min(100, $keep));
        }

        $bonusConfig['anti_fraud'] = [
            'penalty_percent' => $penalty,
            'keep_percent'    => $keep,
        ];

        DB::transaction(function () use ($period, $rows, $ruleData, $bonusConfig) {
            foreach ($rows as $userId => $row) {
                $userId = (int)$userId;

                $baseSalary = (int)($row['base_salary'] ?? 0);
                $kpiPool    = (int)($row['kpi_salary_pool'] ?? 0);
                $tPost      = (int)($row['target_post'] ?? 0);
                $tAi        = (int)($row['target_video_ai'] ?? 0);
                $tReview    = (int)($row['target_video_review'] ?? 0);

                if ($baseSalary === 0 && $kpiPool === 0 && $tPost === 0 && $tAi === 0 && $tReview === 0) {
                    continue;
                }

                MarketingKpiPaySetting::updateOrCreate(
                    ['period' => $period, 'user_id' => $userId],
                    [
                        'base_salary' => max(0, $baseSalary),
                        'kpi_salary_pool' => max(0, $kpiPool),
                        'target_post' => max(0, $tPost),
                        'target_video_ai' => max(0, $tAi),
                        'target_video_review' => max(0, $tReview),
                    ]
                );
            }

            MarketingKpiPayRule::updateOrCreate(
                ['period' => $period],
                array_merge($ruleData, [
                    'bonus_config' => $bonusConfig,
                ])
            );
        });

        return redirect()
            ->route('marketing.kpi-payroll.settings', ['period' => $period])
            ->with('success', 'Đã lưu thiết lập KPI & Lương!');
    }

    /**
     * Trang "KPI & Lương (Show)" - TỰ ĐỘNG LẤY TỪ LỊCH BIÊN TẬP + WEEKLY METRICS
     * GET /marketing/kpi-payroll/my?period=YYYY-MM&user_id=ID
     */
    public function my(Request $request)
    {
        $period = $request->get('period') ?? Carbon::now()->format('Y-m');

        $viewer = auth()->user();
        $targetUserId = $viewer->id;

        $users = collect();
        $targetUser = $viewer;

        if ($viewer->hasAnyRole(['admin', 'marketing_manager'])) {
            $users = User::query()
                ->join('model_has_roles', 'users.id', '=', 'model_has_roles.model_id')
                ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
                ->where('model_has_roles.model_type', User::class)
                ->whereIn('roles.name', ['marketing', 'marketing_manager'])
                ->select('users.*')
                ->distinct()
                ->orderBy('users.id')
                ->get();

            if ($request->filled('user_id')) {
                $targetUserId = (int)$request->get('user_id');
                $targetUser = User::findOrFail($targetUserId);
            }
        }

        $setting = MarketingKpiPaySetting::query()
            ->where('period', $period)
            ->where('user_id', $targetUserId)
            ->first();

        $rule = MarketingKpiPayRule::where('period', $period)->first();
        if (!$rule) {
            $rule = new MarketingKpiPayRule([
                'period' => $period,
                'weight_review' => 40,
                'weight_ai' => 30,
                'weight_post' => 30,
                'bonus_over_review' => 3,
                'bonus_over_ai' => 1,
                'bonus_over_post' => 0.5,
                'bonus_config' => [],
            ]);
        }

        $bonusConfig = (array)($rule->bonus_config ?? []);

        // ✅ Lấy fraud/note từ DB nếu có (còn KPI thực tế sẽ tính tự động)
        $actualDb = MarketingKpiPayActual::firstOrNew([
            'period' => $period,
            'user_id' => $targetUserId,
        ]);

        if ($actualDb->is_fraud === null) $actualDb->is_fraud = false;

        // ✅ Tính actual từ content_calendars + content_calendar_weekly_metrics (AUTO)
        $computed = $this->pullFromCalendarWithMetrics($period, $targetUserId);

        $actualDb->actual_post = (int)($computed['actual_post'] ?? 0);
        $actualDb->actual_video_ai = (int)($computed['actual_video_ai'] ?? 0);
        $actualDb->actual_video_review = (int)($computed['actual_video_review'] ?? 0);
        $actualDb->trend_videos = $computed['trend_videos'] ?? [];
        $actualDb->livestreams = $computed['livestreams'] ?? [];

        $payroll = $this->calcPayroll($setting, $rule, $bonusConfig, $actualDb);

        return view('marketing.kpi_payroll.my', compact(
            'period',
            'setting',
            'rule',
            'bonusConfig',
            'actualDb',
            'payroll',
            'users',
            'targetUser'
        ))->with('actual', $actualDb);
    }

    /**
     * ✅ AUTO: Lấy KPI + thưởng trend/live từ weekly_metrics
     */
    private function pullFromCalendarWithMetrics(string $period, int $userId): array
    {
        $start = Carbon::createFromFormat('Y-m', $period)->startOfMonth()->toDateString();
        $end   = Carbon::createFromFormat('Y-m', $period)->endOfMonth()->toDateString();

        // chỉ lấy bài đã đăng
        $rows = DB::table('content_calendars as c')
            ->leftJoin('content_calendar_weekly_metrics as m', function ($join) {
                $join->on('m.content_calendar_id', '=', 'c.id');
                $join->whereRaw("m.week_start = DATE_SUB(DATE(c.publish_date), INTERVAL WEEKDAY(DATE(c.publish_date)) DAY)");
            })
            ->where('c.assignee_user_id', $userId)
            ->whereBetween('c.publish_date', [$start, $end])
            ->where('c.status', 'posted')
            ->selectRaw('
                c.id, c.title, c.link, c.content_type, c.publish_date,
                COALESCE(m.views,0) as views,
                COALESCE(m.likes,0) as likes,
                COALESCE(m.comments,0) as comments,
                COALESCE(m.shares,0) as shares,
                COALESCE(m.leads,0) as leads,
                COALESCE(m.duration_min,0) as duration_min
            ')
            ->orderBy('c.publish_date')
            ->get();

        $actual_post = 0;
        $actual_ai = 0;
        $actual_review = 0;

        $trend_videos = [];
        $livestreams = [];

        foreach ($rows as $r) {
            $ct = mb_strtolower(trim((string)($r->content_type ?? '')));

            // --- COUNT KPI ---
            if (str_contains($ct, 'bài')) {
                $actual_post++;
            } elseif (str_contains($ct, 'review')) {
                $actual_review++;
            } elseif (str_contains($ct, 'ai')) {
                $actual_ai++;
            } else {
                if ($ct === 'video' || str_contains($ct, 'video')) {
                    $actual_ai++;
                }
            }

            $views = (int)($r->views ?? 0);
            $eng = (int)($r->likes ?? 0) + (int)($r->comments ?? 0) + (int)($r->shares ?? 0);
            $durationMin = (int)($r->duration_min ?? 0);

            // livestream
            if (str_contains($ct, 'live')) {
                $livestreams[] = [
                    'title' => (string)($r->title ?? ''),
                    'url' => (string)($r->link ?? ''),
                    'duration_min' => $durationMin, // ✅ lấy từ weekly_metrics
                    'views' => $views,
                    'engagement' => $eng,
                    'lead_count' => (int)($r->leads ?? 0),
                ];
                continue;
            }

            // trend video: mọi video (bao gồm Video Review) đều được xét theo tier
            if (str_contains($ct, 'video') || str_contains($ct, 'review') || str_contains($ct, 'ai')) {
                $trend_videos[] = [
                    'title' => (string)($r->title ?? ''),
                    'url' => (string)($r->link ?? ''),
                    'views' => $views,
                    'engagement' => $eng,
                ];
            }
        }

        return [
            'actual_post' => $actual_post,
            'actual_video_ai' => $actual_ai,
            'actual_video_review' => $actual_review,
            'trend_videos' => $trend_videos,
            'livestreams' => $livestreams,
        ];
    }

    // =========================
    // Helpers: tính lương
    // =========================

    private function normalizeWeights($wReview, $wAi, $wPost): array
    {
        $wReview = (float)$wReview;
        $wAi = (float)$wAi;
        $wPost = (float)$wPost;

        $sum = $wReview + $wAi + $wPost;
        if ($sum <= 0) return [40, 30, 30];

        $wReview = round($wReview * 100 / $sum, 4);
        $wAi = round($wAi * 100 / $sum, 4);
        $wPost = round(100 - $wReview - $wAi, 4);

        return [$wReview, $wAi, $wPost];
    }

    private function pickTierReward(array $tiers, int $views, int $engagement): int
    {
        $best = 0;
        foreach ($tiers as $t) {
            $minView = (int)($t['min_view'] ?? 0);
            $minEng = (int)($t['min_engagement'] ?? 0);
            $reward = (int)($t['reward'] ?? 0);

            if ($views >= $minView && $engagement >= $minEng) {
                $best = max($best, $reward);
            }
        }
        return $best;
    }

    private function calcPayroll($setting, $rule, array $bonusConfig, MarketingKpiPayActual $actual): array
    {
        $baseSalary = (int)($setting->base_salary ?? 0);
        $pool = (int)($setting->kpi_salary_pool ?? 0);

        $tPost = (int)($setting->target_post ?? 0);
        $tAi = (int)($setting->target_video_ai ?? 0);
        $tReview = (int)($setting->target_video_review ?? 0);

        $aPost = (int)($actual->actual_post ?? 0);
        $aAi = (int)($actual->actual_video_ai ?? 0);
        $aReview = (int)($actual->actual_video_review ?? 0);

        $wReview = (float)($rule->weight_review ?? 40);
        $wAi = (float)($rule->weight_ai ?? 30);
        $wPost = (float)($rule->weight_post ?? 30);

        [$wReview, $wAi, $wPost] = $this->normalizeWeights($wReview, $wAi, $wPost);

        $boReview = (float)($rule->bonus_over_review ?? 3);
        $boAi = (float)($rule->bonus_over_ai ?? 1);
        $boPost = (float)($rule->bonus_over_post ?? 0.5);

        $calcOne = function (int $actualVal, int $targetVal, float $weight, float $bonusOver) {
            if ($targetVal <= 0) {
                return ['base' => 0.0, 'over' => 0.0, 'ratio' => 0.0];
            }
            $ratio = $actualVal / $targetVal;
            $base = min(1.0, $ratio) * ($weight / 100.0);

            $overRatio = max(0.0, $ratio - 1.0);
            $over = $overRatio * $bonusOver * ($weight / 100.0);

            return ['base' => $base, 'over' => $over, 'ratio' => $ratio];
        };

        $rReview = $calcOne($aReview, $tReview, $wReview, $boReview);
        $rAi = $calcOne($aAi, $tAi, $wAi, $boAi);
        $rPost = $calcOne($aPost, $tPost, $wPost, $boPost);

        $kpiRate = $rReview['base'] + $rAi['base'] + $rPost['base']
            + $rReview['over'] + $rAi['over'] + $rPost['over'];

        $kpiRate = max(0.0, min(2.0, $kpiRate));
        $kpiSalary = (int)round($pool * $kpiRate);

        // ✅ BONUS TREND
        $tvTiers = (array)data_get($bonusConfig, 'trend_video.tiers', []);
        $bonusTrend = 0;
        foreach ((array)($actual->trend_videos ?? []) as $v) {
            $views = (int)($v['views'] ?? $v['view'] ?? 0);
            $eng = (int)($v['engagement'] ?? $v['engagements'] ?? 0);
            $bonusTrend += $this->pickTierReward($tvTiers, $views, $eng);
        }

        // ✅ LIVESTREAM: dùng duration_min từ weekly_metrics
        $lsMinDuration = (int)data_get($bonusConfig, 'livestream.min_duration_min', 0);
        $lsLeadReward = (int)data_get($bonusConfig, 'livestream.lead_reward', 0);
        $lsTiers = (array)data_get($bonusConfig, 'livestream.tiers', []);

        $bonusLive = 0;
        $bonusLead = 0;

        foreach ((array)($actual->livestreams ?? []) as $l) {
            $duration = (int)($l['duration_min'] ?? $l['duration'] ?? 0);
            if ($duration < $lsMinDuration) continue;

            $views = (int)($l['views'] ?? $l['view'] ?? 0);
            $eng = (int)($l['engagement'] ?? $l['engagements'] ?? 0);

            $bonusLive += $this->pickTierReward($lsTiers, $views, $eng);

            $leadCount = (int)($l['lead_count'] ?? $l['leads'] ?? $l['lead'] ?? 0);
            if ($leadCount > 0) $bonusLead += $leadCount * $lsLeadReward;
        }

        $bonusTotal = $bonusTrend + $bonusLive + $bonusLead;

        $keepPercent = (int)data_get($bonusConfig, 'anti_fraud.keep_percent', 100);
        $keepPercent = max(0, min(100, $keepPercent));

        $kpiPlusBonus = $kpiSalary + $bonusTotal;

        $kpiPlusBonusAfter = $kpiPlusBonus;
        $penaltyAmount = 0;

        if ((bool)$actual->is_fraud) {
            $kpiPlusBonusAfter = (int)round($kpiPlusBonus * $keepPercent / 100);
            $penaltyAmount = $kpiPlusBonus - $kpiPlusBonusAfter;
        }

        $takeHome = $baseSalary + $kpiPlusBonusAfter;

        return [
            'kpi_rate' => round($kpiRate, 6),
            'kpi_percent' => round($kpiRate * 100, 2),
            'kpi_salary' => $kpiSalary,

            'bonus_trend' => $bonusTrend,
            'bonus_livestream' => $bonusLive,
            'bonus_lead' => $bonusLead,
            'bonus_total' => $bonusTotal,

            'kpi_plus_bonus' => $kpiPlusBonus,
            'keep_percent' => $keepPercent,
            'penalty' => $penaltyAmount,
            'kpi_plus_bonus_after_penalty' => $kpiPlusBonusAfter,

            'base_salary' => $baseSalary,
            'take_home' => $takeHome,

            'breakdown' => [
                'review' => $rReview,
                'ai' => $rAi,
                'post' => $rPost,
            ],
        ];
    }

    public function saveMy(Request $request)
    {
        return redirect()->back();
    }
}