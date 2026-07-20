<?php

namespace App\Http\Controllers\Marketing;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Controller tiến độ kế hoạch marketing: sinh task tự động từ kế hoạch và theo dõi trạng thái.
 */
class MarketingProgressController extends Controller
{
    // =========================================================
    // Helpers
    // =========================================================
    /**
     * Kiểm tra bảng tồn tại (an toàn với exception).
     */
    private function safeTable(string $table): bool
    {
        try {
            return Schema::hasTable($table);
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Quy đổi trạng thái task sang tỉ lệ tiến độ (done=1, doing=0.5, còn lại=0).
     */
    private function statusPct(string $st): float
    {
        // weight-based progress rule
        return match ($st) {
            'done' => 1.0,
            'doing' => 0.5,
            'blocked' => 0.0,
            default => 0.0, // todo
        };
    }

    /**
     * Chuẩn hoá kênh về seo/ads/email, các giá trị khác thành other.
     */
    private function normalizeChannel(?string $ch): string
    {
        $ch = strtolower(trim((string) $ch));

        return in_array($ch, ['seo', 'ads', 'email']) ? $ch : 'other';
    }

    /**
     * Generate tasks from plan (SEO/Ads/Email)
     * Deletes old AUTO tasks, keeps manual tasks.
     */
    private function generateFromPlan(int $planId): void
    {
        $plan = DB::table('mkt_plans')->where('id', $planId)->first();
        if (! $plan) {
            return;
        }
        if (! $this->safeTable('mkt_plan_tasks')) {
            return;
        }

        $monthStart = Carbon::parse($plan->month)->startOfMonth();
        $monthEnd = Carbon::parse($plan->month)->endOfMonth();

        DB::table('mkt_plan_tasks')->where('plan_id', $planId)->where('is_auto', 1)->delete();

        $ins = [];

        // SEO content -> 1 row = 1 task
        if ($this->safeTable('mkt_seo_contents')) {
            $seoContents = DB::table('mkt_seo_contents')->where('plan_id', $planId)->orderBy('week_no')->orderBy('id')->get();
            foreach ($seoContents as $c) {
                $topic = trim((string) ($c->topic ?? ''));
                $kw = trim((string) ($c->main_keyword ?? ''));
                if ($topic === '' && $kw === '') {
                    continue;
                }

                $due = null;
                if (! empty($c->publish_date)) {
                    $due = Carbon::parse($c->publish_date)->toDateString();
                } else {
                    $w = max(1, (int) ($c->week_no ?? 1));
                    $due = $monthStart->copy()->addDays(min(27, ($w - 1) * 7 + 6))->toDateString();
                }

                $title = 'SEO - Viết bài: '.($topic !== '' ? $topic : $kw);
                if ($kw !== '' && $topic !== '' && stripos($title, $kw) === false) {
                    $title .= ' ('.$kw.')';
                }

                $ins[] = [
                    'plan_id' => $planId,
                    'channel' => 'seo',
                    'title' => $title,
                    'due_date' => $due,
                    'status' => 'todo',
                    'weight' => 2,
                    'is_auto' => 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
        }

        // SEO target -> 1 website = 1 task
        if ($this->safeTable('mkt_seo_targets')) {
            $seoTargets = DB::table('mkt_seo_targets')->where('plan_id', $planId)->orderBy('id')->get();
            foreach ($seoTargets as $t) {
                $site = trim((string) ($t->website ?? ''));
                if ($site === '') {
                    continue;
                }

                $ins[] = [
                    'plan_id' => $planId,
                    'channel' => 'seo',
                    'title' => 'SEO - Đạt KPI tháng cho: '.$site,
                    'due_date' => $monthEnd->toDateString(),
                    'status' => 'todo',
                    'weight' => 3,
                    'is_auto' => 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
        }

        // Ads plans -> 3 tasks/campaign
        if ($this->safeTable('mkt_ads_plans')) {
            $adsPlans = DB::table('mkt_ads_plans')->where('plan_id', $planId)->orderBy('id')->get();
            foreach ($adsPlans as $a) {
                $name = trim((string) ($a->campaign_name ?? ''));
                $plat = trim((string) ($a->platform ?? 'Ads'));
                if ($name === '' && $plat === '') {
                    continue;
                }

                $label = ($plat !== '' ? $plat : 'Ads').' - '.($name !== '' ? $name : 'Campaign');

                $ins[] = [
                    'plan_id' => $planId,
                    'channel' => 'ads',
                    'title' => 'Ads - Setup: '.$label,
                    'due_date' => $monthStart->copy()->addDays(2)->toDateString(),
                    'status' => 'todo',
                    'weight' => 2,
                    'is_auto' => 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
                $ins[] = [
                    'plan_id' => $planId,
                    'channel' => 'ads',
                    'title' => 'Ads - Optimize: '.$label,
                    'due_date' => $monthStart->copy()->addDays(14)->toDateString(),
                    'status' => 'todo',
                    'weight' => 3,
                    'is_auto' => 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
                $ins[] = [
                    'plan_id' => $planId,
                    'channel' => 'ads',
                    'title' => 'Ads - Report: '.$label,
                    'due_date' => $monthEnd->toDateString(),
                    'status' => 'todo',
                    'weight' => 1,
                    'is_auto' => 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
        }

        // Email plans -> 1 plan = 1 task
        if ($this->safeTable('mkt_email_plans')) {
            $emailPlans = DB::table('mkt_email_plans')->where('plan_id', $planId)->orderBy('id')->get();
            foreach ($emailPlans as $e) {
                $name = trim((string) ($e->campaign_name ?? ''));
                if ($name === '') {
                    continue;
                }

                $due = ! empty($e->send_date)
                    ? Carbon::parse($e->send_date)->toDateString()
                    : $monthStart->copy()->addDays(19)->toDateString();

                $ins[] = [
                    'plan_id' => $planId,
                    'channel' => 'email',
                    'title' => 'Email - Gửi campaign: '.$name,
                    'due_date' => $due,
                    'status' => 'todo',
                    'weight' => 2,
                    'is_auto' => 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
        }

        if (! empty($ins)) {
            DB::table('mkt_plan_tasks')->insert($ins);
        }
    }

    /**
     * Summary helper for a task list
     */
    private function summarize($tasks): array
    {
        $sumW = 0;
        $sumDone = 0;
        $countByStatus = ['todo' => 0, 'doing' => 0, 'blocked' => 0, 'done' => 0];

        foreach ($tasks as $t) {
            $w = (int) ($t->weight ?? 1);
            $st = (string) ($t->status ?? 'todo');
            $sumW += $w;
            $sumDone += $w * $this->statusPct($st);

            if (! isset($countByStatus[$st])) {
                $countByStatus[$st] = 0;
            }
            $countByStatus[$st] += 1;
        }

        $progressPct = $sumW > 0 ? round(($sumDone / $sumW) * 100, 1) : 0;

        return [
            'sumW' => $sumW,
            'progressPct' => $progressPct,
            'countByStatus' => $countByStatus,
            'count' => is_countable($tasks) ? count($tasks) : 0,
        ];
    }

    // =========================================================
    // Routes
    // =========================================================

    /**
     * GET /marketing/progress  (OVERVIEW)
     */
    public function index(Request $request)
    {
        $plans = DB::table('mkt_plans')->orderByDesc('month')->get();
        $planId = (int) ($request->get('plan_id') ?: ($plans->first()->id ?? 0));
        $plan = $planId ? DB::table('mkt_plans')->where('id', $planId)->first() : null;

        $tasks = collect();
        if ($plan && $this->safeTable('mkt_plan_tasks')) {
            $tasks = DB::table('mkt_plan_tasks')
                ->where('plan_id', $planId)
                ->orderBy('due_date')
                ->orderBy('id')
                ->get();
        }

        $monthText = $plan ? Carbon::parse($plan->month)->format('m/Y') : '';

        $overall = $this->summarize($tasks);

        // Summary per channel
        $channels = ['seo', 'ads', 'email'];
        $channelSummary = [];
        foreach ($channels as $ch) {
            $subset = $tasks->filter(fn ($t) => $this->normalizeChannel($t->channel ?? null) === $ch)->values();
            $channelSummary[$ch] = $this->summarize($subset);
        }

        return view('marketing.progress.index', compact(
            'plans', 'planId', 'plan', 'monthText', 'overall', 'channelSummary'
        ));
    }

    /**
     * GET /marketing/progress/monthly?plan_id=...&channel=seo|ads|email (DETAIL)
     */
    public function monthly(Request $request)
    {
        $plans = DB::table('mkt_plans')->orderByDesc('month')->get();
        $planId = (int) ($request->get('plan_id') ?: ($plans->first()->id ?? 0));
        $channel = $this->normalizeChannel($request->get('channel'));
        if ($channel === 'other') {
            $channel = 'ads';
        } // default friendly

        $plan = $planId ? DB::table('mkt_plans')->where('id', $planId)->first() : null;
        $monthText = $plan ? Carbon::parse($plan->month)->format('m/Y') : '';

        $tasks = collect();
        if ($plan && $this->safeTable('mkt_plan_tasks')) {
            $tasks = DB::table('mkt_plan_tasks')
                ->where('plan_id', $planId)
                ->where('channel', $channel)
                ->orderByRaw("FIELD(status,'doing','todo','blocked','done')")
                ->orderBy('due_date')
                ->orderBy('id')
                ->get();
        }

        $summary = $this->summarize($tasks);

        return view('marketing.progress.detail', compact(
            'plans', 'planId', 'plan', 'monthText', 'channel', 'tasks', 'summary'
        ));
    }

    /**
     * POST /marketing/progress/update  (GENERATE)
     */
    public function update(Request $request)
    {
        $request->validate([
            'plan_id' => ['required', 'integer', 'min:1'],
            'action' => ['required', 'in:generate'],
        ]);

        $planId = (int) $request->plan_id;

        DB::beginTransaction();
        try {
            if ($request->action === 'generate') {
                $this->generateFromPlan($planId);
            }
            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }

        return redirect()
            ->route('marketing.progress.index', ['plan_id' => $planId])
            ->with('success', 'Đã tạo task từ kế hoạch.');
    }

    /**
     * POST /marketing/progress/tasks/{id}/status (UPDATE STATUS)
     */
    public function updateStatus(Request $request, int $id)
    {
        $request->validate([
            'status' => ['required', 'in:todo,doing,blocked,done'],
        ]);

        if (! $this->safeTable('mkt_plan_tasks')) {
            return back()->withErrors(['status' => 'Thiếu bảng mkt_plan_tasks']);
        }

        $task = DB::table('mkt_plan_tasks')->where('id', $id)->first();
        if (! $task) {
            return back()->withErrors(['status' => 'Task không tồn tại.']);
        }

        DB::table('mkt_plan_tasks')->where('id', $id)->update([
            'status' => $request->status,
            'updated_at' => now(),
        ]);

        // quay lại trang hiện tại
        return back()->with('success', 'Đã cập nhật trạng thái.');
    }
}
