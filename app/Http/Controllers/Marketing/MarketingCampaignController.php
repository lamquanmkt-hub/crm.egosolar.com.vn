<?php

namespace App\Http\Controllers\Marketing;

use App\Http\Controllers\Controller;
use App\Models\Marketing\MarketingCampaign;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Controller quản lý chiến dịch marketing.
 */
class MarketingCampaignController extends Controller
{
    /**
     * Tạo mới chiến dịch marketing.
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'platform' => 'required|string|max:50',
            'note' => 'nullable|string|max:255',
        ]);

        $data['created_by'] = Auth::id();

        MarketingCampaign::create($data);

        return back()->with('success', 'Đã tạo chiến dịch.');
    }
}
