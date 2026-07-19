<?php

namespace App\Http\Controllers\Marketing;

use App\Http\Controllers\Controller;
use App\Models\Marketing\MarketingBudget;
use App\Models\Marketing\MarketingCampaign;
use App\Models\Marketing\MarketingMetric;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;

class MarketingBudgetController extends Controller
{
    public function index(Request $request)
    {
        // Filters (NEW: from/to + giữ month cũ để tương thích tạm)
        $month       = $request->get('month');        // YYYY-MM (legacy)
        $fromRaw     = $request->get('from');         // dd/mm/yyyy hoặc yyyy-mm-dd
        $toRaw       = $request->get('to');           // dd/mm/yyyy hoặc yyyy-mm-dd
        $platform    = $request->get('platform');     // Facebook/Google/...
        $campaign_id = $request->get('campaign_id');  // id

        // ===== Resolve date range =====
        // Nếu UI cũ còn gửi month mà chưa gửi from/to -> map month => range tháng đó
        if ($month && !$fromRaw && !$toRaw) {
            $mStart = Carbon::createFromFormat('Y-m', $month)->startOfMonth()->startOfDay();
            $mEnd   = (clone $mStart)->endOfMonth()->endOfDay();
            $startDate = $mStart;
            $endDate   = $mEnd;
            $fromView  = $mStart->format('d/m/Y');
            $toView    = $mEnd->format('d/m/Y');
        } else {
            [$startDate, $endDate, $fromView, $toView] = $this->resolveDateRange($fromRaw, $toRaw);
        }

        $isSingleMonth = $startDate->copy()->startOfMonth()->equalTo($endDate->copy()->startOfMonth());
        $rangeLabel = $fromView . ' - ' . $toView;

        // ===== BUDGET QUERY =====
        $budgetQ = MarketingBudget::with('marketingCampaign')
            ->orderByDesc('month')
            ->orderByDesc('id');

        // Budget là dữ liệu theo THÁNG -> filter theo các tháng giao với range
        // Lấy month từ startMonth đến endMonth (inclusive)
        $startMonthDate = $startDate->copy()->startOfMonth()->toDateString(); // YYYY-MM-01
        $endMonthDate   = $endDate->copy()->startOfMonth()->toDateString();   // YYYY-MM-01

        $budgetQ->whereDate('month', '>=', $startMonthDate)
                ->whereDate('month', '<=', $endMonthDate);

        if ($platform) {
            $budgetQ->where('platform', $platform);
        }
        if ($campaign_id) {
            $budgetQ->where('campaign_id', $campaign_id);
        }

        $rows = (clone $budgetQ)->paginate(20)->withQueryString();

        $totalBudget = (clone $budgetQ)->sum('budget');
        $totalSpent  = (clone $budgetQ)->sum('actual_spent');

        $summary = (clone $budgetQ)
            ->selectRaw('month, platform, SUM(budget) as total_budget, SUM(actual_spent) as total_spent')
            ->groupBy('month', 'platform')
            ->orderByDesc('month')
            ->get();

        // ===== METRICS QUERY =====
        $metricQ = MarketingMetric::with('campaign')
            ->orderByDesc('date_to')
            ->orderByDesc('date_from');

        // overlap filter: record intersects selected range
        $metricQ->whereDate('date_from', '<=', $endDate->toDateString())
                ->whereDate('date_to', '>=', $startDate->toDateString());

        if ($platform) {
            $metricQ->where('platform', $platform);
        }

        if ($campaign_id) {
            $metricQ->where('campaign_id', $campaign_id);
        }

        $metricRows = (clone $metricQ)->limit(20)->get();

        // ===== SUM METRICS (phân bổ theo overlap trong range) =====
        $hasOrders  = Schema::hasColumn('marketing_metrics', 'orders');
        $hasRevenue = Schema::hasColumn('marketing_metrics', 'revenue');

        $sumSpend = 0;
        $sumLeads = 0;
        $sumReach = 0;
        $sumOrders = 0;
        $sumRevenue = 0;

        // ✅ chia theo số ngày overlap trong range
        $allMetrics = (clone $metricQ)->get();

        foreach ($allMetrics as $m) {
            $ratio = $this->rangeAllocationRatio($m->date_from, $m->date_to, $startDate, $endDate);
            if ($ratio <= 0) continue;

            $sumSpend += (float)($m->spend ?? 0) * $ratio;
            $sumLeads += (float)($m->leads ?? 0) * $ratio;
            $sumReach += (float)($m->reach ?? 0) * $ratio;

            if ($hasOrders) {
                $sumOrders += (float)($m->orders ?? 0) * $ratio;
            }
            if ($hasRevenue) {
                $sumRevenue += (float)($m->revenue ?? 0) * $ratio;
            }
        }

        // làm tròn đẹp
        $sumSpend   = round($sumSpend);
        $sumLeads   = round($sumLeads);
        $sumReach   = round($sumReach);
        $sumOrders  = round($sumOrders);
        $sumRevenue = round($sumRevenue);

        $cpl  = $sumLeads  > 0 ? round($sumSpend / $sumLeads) : 0;
        $cpo  = $sumOrders > 0 ? round($sumSpend / $sumOrders) : 0;
        $roas = $sumSpend  > 0 ? round($sumRevenue / $sumSpend, 2) : 0;

        // Dropdown campaigns
        $campaigns = MarketingCampaign::query()
            ->when($platform, fn($q) => $q->where('platform', $platform))
            ->orderBy('name')
            ->get();

        // ===== COMBINED (Budget + Metric) theo campaign (range) =====
        $campaignCombined = collect();

        // budget aggregate
        if ($isSingleMonth) {
            $budgetAgg = (clone $budgetQ)
                ->selectRaw('month, platform, campaign_id, SUM(budget) as budget, SUM(actual_spent) as budget_spent')
                ->groupBy('month', 'platform', 'campaign_id')
                ->get();
        } else {
            $budgetAgg = (clone $budgetQ)
                ->selectRaw('platform, campaign_id, SUM(budget) as budget, SUM(actual_spent) as budget_spent')
                ->groupBy('platform', 'campaign_id')
                ->get();
        }

        // metric allocate + group by campaign
        $metricAgg = [];
        foreach ($allMetrics as $m) {
            $ratio = $this->rangeAllocationRatio($m->date_from, $m->date_to, $startDate, $endDate);
            if ($ratio <= 0) continue;

            $key = ($m->platform ?? '-') . '|' . ((string)$m->campaign_id);

            if (!isset($metricAgg[$key])) {
                $metricAgg[$key] = [
                    'spend' => 0, 'reach' => 0, 'leads' => 0,
                    'platform' => $m->platform,
                    'campaign_id' => $m->campaign_id,
                ];
            }

            $metricAgg[$key]['spend'] += (float)($m->spend ?? 0) * $ratio;
            $metricAgg[$key]['reach'] += (float)($m->reach ?? 0) * $ratio;
            $metricAgg[$key]['leads'] += (float)($m->leads ?? 0) * $ratio;
        }

        // campaign name map
        $campaignIds = $budgetAgg->pluck('campaign_id')
            ->merge(collect($metricAgg)->pluck('campaign_id'))
            ->unique()
            ->filter();

        $campaignMap = MarketingCampaign::whereIn('id', $campaignIds)->pluck('name', 'id');

        // merge budgets first
        foreach ($budgetAgg as $b) {
            $key = ($b->platform ?? '-') . '|' . ((string)$b->campaign_id);
            $m = $metricAgg[$key] ?? null;

            $campaignCombined->push((object)[
                'month' => $isSingleMonth ? ($b->month ?? $startMonthDate) : $rangeLabel,
                'platform' => $b->platform,
                'campaign_id' => $b->campaign_id,
                'campaign_name' => $campaignMap[$b->campaign_id] ?? ($b->marketingCampaign?->name ?? '-'),
                'budget' => (float)($b->budget ?? 0),
                'budget_spent' => (float)($b->budget_spent ?? 0),
                'spend' => round((float)($m['spend'] ?? 0)),
                'reach' => round((float)($m['reach'] ?? 0)),
                'leads' => round((float)($m['leads'] ?? 0)),
            ]);

            unset($metricAgg[$key]);
        }

        // metrics only (không có budget)
        foreach ($metricAgg as $m) {
            $campaignCombined->push((object)[
                'month' => $isSingleMonth ? $startMonthDate : $rangeLabel,
                'platform' => $m['platform'],
                'campaign_id' => $m['campaign_id'],
                'campaign_name' => $campaignMap[$m['campaign_id']] ?? '-',
                'budget' => 0,
                'budget_spent' => 0,
                'spend' => round($m['spend']),
                'reach' => round($m['reach']),
                'leads' => round($m['leads']),
            ]);
        }

        $campaignCombined = $campaignCombined
            ->sortByDesc(fn($x) => ($x->budget + $x->spend))
            ->values();

        return view('marketing.budget', [
            // NEW for UI
            'from' => $fromView,
            'to'   => $toView,

            // legacy (giữ tạm cho UI cũ nếu còn dùng)
            'month' => $isSingleMonth ? $startDate->format('Y-m') : null,

            'platform'    => $platform,
            'campaign_id' => $campaign_id,

            'rows'        => $rows,
            'totalBudget' => $totalBudget,
            'totalSpent'  => $totalSpent,
            'summary'     => $summary,

            'metricRows'  => $metricRows,
            'sumSpend'    => $sumSpend,
            'sumLeads'    => $sumLeads,
            'sumReach'    => $sumReach,
            'sumOrders'   => $sumOrders,
            'sumRevenue'  => $sumRevenue,
            'cpl'         => $cpl,
            'cpo'         => $cpo,
            'roas'        => $roas,

            'campaigns'   => $campaigns,
            'campaignCombined' => $campaignCombined,
        ]);
    }

    private function parseUiDate(?string $value): ?Carbon
    {
        $value = trim((string)$value);
        if ($value === '') return null;

        // yyyy-mm-dd (input type="date")
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return Carbon::createFromFormat('Y-m-d', $value);
        }

        // dd/mm/yyyy
        if (preg_match('/^\d{2}\/\d{2}\/\d{4}$/', $value)) {
            return Carbon::createFromFormat('d/m/Y', $value);
        }

        return null;
    }

    private function resolveDateRange(?string $fromRaw, ?string $toRaw): array
    {
        $from = $this->parseUiDate($fromRaw);
        $to   = $this->parseUiDate($toRaw);

        // DEFAULT: cả tháng hiện tại
        if (!$from && !$to) {
            $start = now()->startOfMonth()->startOfDay();
            $end   = now()->endOfMonth()->endOfDay();
            return [$start, $end, $start->format('d/m/Y'), $end->format('d/m/Y')];
        }

        $start = ($from ?: $to)->copy()->startOfDay();
        $end   = ($to ?: $from)->copy()->endOfDay();

        if ($start->gt($end)) {
            [$start, $end] = [$end, $start];
        }

        return [$start, $end, $start->format('d/m/Y'), $end->format('d/m/Y')];
    }

    /**
     * Tính tỉ lệ phân bổ vào 1 khoảng thời gian theo overlap day / total day (inclusive)
     */
    private function rangeAllocationRatio($dateFrom, $dateTo, Carbon $rangeStart, Carbon $rangeEnd): float
    {
        if (!$dateFrom || !$dateTo) return 0;

        $from = Carbon::parse($dateFrom)->startOfDay();
        $to   = Carbon::parse($dateTo)->endOfDay();

        if ($to->lt($from)) return 0;

        $totalDays = $from->copy()->startOfDay()->diffInDays($to->copy()->startOfDay()) + 1;

        $overlapStart = $from->greaterThan($rangeStart) ? $from : $rangeStart->copy();
        $overlapEnd   = $to->lessThan($rangeEnd) ? $to : $rangeEnd->copy();

        if ($overlapEnd->lt($overlapStart)) return 0;

        $overlapDays = $overlapStart->copy()->startOfDay()->diffInDays($overlapEnd->copy()->startOfDay()) + 1;

        return $totalDays > 0 ? ($overlapDays / $totalDays) : 0;
    }

    // ====== các hàm store/edit/update/destroy/clone giữ nguyên ======

    public function store(Request $request)
    {
        $data = $request->validate([
            'campaign_id'  => 'required|integer|exists:marketing_campaigns,id',
            'platform'     => 'required|string|max:50',
            'month'        => 'required|date_format:Y-m',
            'budget'       => 'required|integer|min:0',
            'actual_spent' => 'nullable|integer|min:0',
            'note'         => 'nullable|string',
        ]);

        $data['month'] = $data['month'] . '-01';
        $data['actual_spent'] = $data['actual_spent'] ?? 0;
        $data['created_by'] = Auth::id();

        MarketingBudget::create($data);

        return back()->with('success', 'Đã thêm ngân sách.');
    }

    public function edit($id)
    {
        $row = MarketingBudget::with('marketingCampaign')->findOrFail($id);
        $campaigns = MarketingCampaign::orderBy('name')->get();

        return view('marketing.budget_edit', compact('row', 'campaigns'));
    }

    public function update(Request $request, $id)
    {
        $row = MarketingBudget::findOrFail($id);

        $data = $request->validate([
            'campaign_id'  => 'required|integer|exists:marketing_campaigns,id',
            'platform'     => 'required|string|max:50',
            'month'        => 'required|date_format:Y-m',
            'budget'       => 'required|integer|min:0',
            'actual_spent' => 'nullable|integer|min:0',
            'note'         => 'nullable|string',
        ]);

        $data['month'] = $data['month'] . '-01';
        $data['actual_spent'] = $data['actual_spent'] ?? 0;

        $row->update($data);

        return redirect()->route('marketing.budget.edit', $row->id)->with('success', 'Đã cập nhật ngân sách.');
    }

    public function destroy($id)
    {
        $row = MarketingBudget::findOrFail($id);
        $row->delete();

        return back()->with('success', 'Đã xóa dòng ngân sách.');
    }

    public function cloneToNextMonth($id)
    {
        $row = MarketingBudget::findOrFail($id);

        $currentMonth = $row->month ? Carbon::parse($row->month)->startOfMonth() : now()->startOfMonth();
        $nextMonth = $currentMonth->copy()->addMonthNoOverflow()->startOfMonth();

        $exists = MarketingBudget::whereDate('month', $nextMonth->toDateString())
            ->where('platform', $row->platform)
            ->where('campaign_id', $row->campaign_id)
            ->first();

        if ($exists) {
            return redirect()
                ->route('marketing.budget.edit', $exists->id)
                ->with('success', 'Tháng sau đã tồn tại, chuyển sang dòng đó để sửa.');
        }

        $new = $row->replicate();
        $new->month = $nextMonth->toDateString();
        $new->actual_spent = 0;
        $new->created_by = Auth::id();
        $new->note = null;

        $new->save();

        return redirect()
            ->route('marketing.budget.edit', $new->id)
            ->with('success', 'Đã tạo dòng tháng sau. Bạn nhập dữ liệu cho tháng mới nhé.');
    }
}
