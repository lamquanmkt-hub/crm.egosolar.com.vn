<?php
namespace App\Http\Controllers;
use App\Models\Marketing\MarketingCampaign;
use App\Models\Marketing\MarketingMetric;
use Illuminate\Http\Request;
class MarketingMetricController extends Controller
{
    public function store(Request $request)
    {
        $data = $request->validate([
            'date_from'   => ['required','date'],
            'date_to'     => ['required','date','after_or_equal:date_from'],
            'platform'    => ['required','string'],
            'campaign_id' => ['required','integer'],
            'reach'       => ['required','integer','min:0'],
            'leads'       => ['required','integer','min:0'],
            'spend'       => ['nullable','integer','min:0'],
            'note'        => ['nullable','string'],
            'gender'      => ['nullable','array'],
            'gender.*'    => ['nullable','integer','min:0'],
            'age'         => ['nullable','array'],
            'age.*'       => ['nullable','integer','min:0'],
            'region'      => ['nullable','array'],
            'region.*'    => ['nullable','integer','min:0'],
        ]);
        $m = new MarketingMetric();
        $m->date_from = $data['date_from'];
        $m->date_to   = $data['date_to'];
        $m->platform  = $data['platform'];
        $m->campaign_id = $data['campaign_id'];
        $m->reach     = (int)$data['reach'];
        $m->leads     = (int)$data['leads'];
        $m->spend     = (int)($data['spend'] ?? 0);
        $m->note      = $data['note'] ?? null;
        $m->gender_breakdown = $request->input('gender', []);
        $m->age_breakdown    = $request->input('age', []);
        $m->region_breakdown = $request->input('region', []);
        $m->save();
        return back()->with('success', 'Đã thêm chỉ số marketing.');
    }
    public function edit($id)
{
    $m = MarketingMetric::with('campaign')->findOrFail($id);
    $campaigns = MarketingCampaign::orderBy('name')->get();
    return view('marketing.metrics.edit', compact('m', 'campaigns'));
}
    public function update(Request $request, $id)
    {
        $data = $request->validate([
            'date_from'   => ['required','date'],
            'date_to'     => ['required','date','after_or_equal:date_from'],
            'platform'    => ['required','string'],
            'campaign_id' => ['required','integer'],
            'reach'       => ['required','integer','min:0'],
            'leads'       => ['required','integer','min:0'],
            'spend'       => ['nullable','integer','min:0'],
            'note'        => ['nullable','string'],
            'gender'      => ['nullable','array'],
            'gender.*'    => ['nullable','integer','min:0'],
            'age'         => ['nullable','array'],
            'age.*'       => ['nullable','integer','min:0'],
            'region'      => ['nullable','array'],
            'region.*'    => ['nullable','integer','min:0'],
        ]);
        $m = MarketingMetric::findOrFail($id);
        $m->date_from = $data['date_from'];
        $m->date_to   = $data['date_to'];
        $m->platform  = $data['platform'];
        $m->campaign_id = $data['campaign_id'];
        $m->reach     = (int)$data['reach'];
        $m->leads     = (int)$data['leads'];
        $m->spend     = (int)($data['spend'] ?? 0);
        $m->note      = $data['note'] ?? null;
        $m->gender_breakdown = $request->input('gender', []);
        $m->age_breakdown    = $request->input('age', []);
        $m->region_breakdown = $request->input('region', []);
        $m->save();
        return redirect()->route('marketing.budget')->with('success', 'Đã cập nhật chỉ số marketing.');
    }
    public function destroy($id)
    {
        $m = MarketingMetric::findOrFail($id);
        $m->delete();
        return back()->with('success', 'Đã xóa chỉ số marketing.');
    }
}
