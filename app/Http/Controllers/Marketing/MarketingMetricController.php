<?php

namespace App\Http\Controllers\Marketing;

use App\Http\Controllers\Controller;
use App\Models\Marketing\MarketingCampaign;
use App\Models\Marketing\MarketingMetric;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class MarketingMetricController extends Controller
{
    public function index(Request $request)
    {
        $month       = $request->get('month');        // YYYY-MM
        $platform    = $request->get('platform');     // Facebook/Google/...
        $campaign_id = $request->get('campaign_id');  // id
        $campaign    = $request->get('campaign');     // search by name
        $dateFrom    = $request->get('date_from');    // YYYY-MM-DD
        $dateTo      = $request->get('date_to');      // YYYY-MM-DD

        $gender   = $request->get('gender');
        $ageRange = $request->get('age_range');
        $region   = $request->get('region');

        $query = MarketingMetric::with('campaign')
            ->orderByDesc('date_to')
            ->orderByDesc('date_from');

        // Date filter: overlap
        if ($dateFrom || $dateTo) {
            $start = $dateFrom ? Carbon::parse($dateFrom)->startOfDay() : null;
            $end   = $dateTo   ? Carbon::parse($dateTo)->endOfDay()     : null;

            if ($start && $end) {
                $query->whereDate('date_from', '<=', $end)
                      ->whereDate('date_to', '>=', $start);
            } elseif ($start) {
                $query->whereDate('date_to', '>=', $start);
            } elseif ($end) {
                $query->whereDate('date_from', '<=', $end);
            }
        } elseif ($month) {
            $start = Carbon::createFromFormat('Y-m', $month)->startOfMonth();
            $end   = (clone $start)->endOfMonth();

            $query->whereDate('date_from', '<=', $end)
                  ->whereDate('date_to', '>=', $start);
        }

        if ($platform) {
            $query->where('platform', $platform);
        }

        if ($campaign_id) {
            $query->where('campaign_id', (int)$campaign_id);
        }

        if ($campaign) {
            $query->whereHas('campaign', function ($q) use ($campaign) {
                $q->where('name', 'like', '%' . $campaign . '%');
            });
        }

        if ($gender && Schema::hasColumn('marketing_metrics', 'gender')) {
            $query->where('gender', $gender);
        }
        if ($ageRange && Schema::hasColumn('marketing_metrics', 'age_range')) {
            $query->where('age_range', $ageRange);
        }
        if ($region && Schema::hasColumn('marketing_metrics', 'region')) {
            $query->where('region', 'like', '%' . $region . '%');
        }

        $rows = (clone $query)->paginate(20)->withQueryString();

        $sumSpend = (clone $query)->sum('spend');
        $sumLeads = (clone $query)->sum('leads');
        $sumReach = (clone $query)->sum('reach');

        $sumOrders  = Schema::hasColumn('marketing_metrics', 'orders')  ? (clone $query)->sum('orders')  : 0;
        $sumRevenue = Schema::hasColumn('marketing_metrics', 'revenue') ? (clone $query)->sum('revenue') : 0;

        $cpl  = $sumLeads > 0 ? round($sumSpend / $sumLeads) : 0;
        $cpo  = $sumOrders > 0 ? round($sumSpend / $sumOrders) : 0;
        $roas = $sumSpend > 0 ? round($sumRevenue / $sumSpend, 2) : 0;

        return view('marketing.metrics', compact(
            'rows',
            'month', 'platform', 'campaign', 'campaign_id',
            'dateFrom', 'dateTo',
            'gender', 'ageRange', 'region',
            'sumSpend', 'sumLeads', 'sumReach', 'sumOrders', 'sumRevenue',
            'cpl', 'cpo', 'roas'
        ));
    }

    public function create()
    {
        return view('marketing.metrics_create');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'campaign_id' => 'required|integer|exists:marketing_campaigns,id',
            'platform'    => 'required|string|max:50',
            'date_from'   => 'required|date',
            'date_to'     => 'required|date|after_or_equal:date_from',
            'reach'       => 'required|integer|min:0',
            'leads'       => 'required|integer|min:0',
            'spend'       => 'nullable|integer|min:0',
            'gender'      => 'nullable|array',
            'gender.*'    => 'integer|min:0',
            'age'         => 'nullable|array',
            'age.*'       => 'integer|min:0',
            'region'      => 'nullable|array',
            'region.*'    => 'integer|min:0',
            'note'        => 'nullable|string',
        ]);

        MarketingMetric::create([
            'campaign_id'       => $data['campaign_id'],
            'platform'          => $data['platform'],
            'date_from'         => $data['date_from'],
            'date_to'           => $data['date_to'],
            'reach'             => $data['reach'],
            'leads'             => $data['leads'],
            'spend'             => $data['spend'] ?? 0,
            'gender_breakdown'  => $data['gender'] ?? [],
            'age_breakdown'     => $data['age'] ?? [],
            'region_breakdown'  => $data['region'] ?? [],
            'note'              => $data['note'] ?? null,
            'created_by'        => auth()->id(),
        ]);

        return back()->with('success', 'Đã lưu chỉ số marketing');
    }

    public function edit($id)
    {
        $metric = MarketingMetric::with('campaign')->findOrFail($id);
        $campaigns = MarketingCampaign::orderBy('name')->get();

        return view('marketing.metrics_edit', compact('metric', 'campaigns'));
    }

    public function update(Request $request, $id)
    {
        $metric = MarketingMetric::findOrFail($id);

        $data = $request->validate([
            'campaign_id' => 'required|integer|exists:marketing_campaigns,id',
            'platform'    => 'required|string|max:50',
            'date_from'   => 'required|date',
            'date_to'     => 'required|date|after_or_equal:date_from',
            'reach'       => 'required|integer|min:0',
            'leads'       => 'required|integer|min:0',
            'spend'       => 'nullable|integer|min:0',
            'gender'      => 'nullable|array',
            'gender.*'    => 'integer|min:0',
            'age'         => 'nullable|array',
            'age.*'       => 'integer|min:0',
            'region'      => 'nullable|array',
            'region.*'    => 'integer|min:0',
            'note'        => 'nullable|string',
        ]);

        $metric->update([
            'campaign_id'       => $data['campaign_id'],
            'platform'          => $data['platform'],
            'date_from'         => $data['date_from'],
            'date_to'           => $data['date_to'],
            'reach'             => $data['reach'],
            'leads'             => $data['leads'],
            'spend'             => $data['spend'] ?? 0,
            'gender_breakdown'  => $data['gender'] ?? [],
            'age_breakdown'     => $data['age'] ?? [],
            'region_breakdown'  => $data['region'] ?? [],
            'note'              => $data['note'] ?? null,
        ]);

        return redirect()->back()->with('success', 'Đã cập nhật chỉ số marketing');
    }

    public function destroy($id)
    {
        $metric = MarketingMetric::findOrFail($id);
        $metric->delete();

        return back()->with('success', 'Đã xóa chỉ số marketing');
    }
}
