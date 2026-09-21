<?php

declare(strict_types=1);

namespace App\Http\Controllers\Technical;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Technical\Concerns\AuthorizesTechnicalDashboard;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * TRANG "KPI THAM KHẢO" (đợt V4) ĐÃ ĐƯỢC THAY THẾ.
 *
 * KPI chính thức của phòng Kỹ thuật là `/ky-thuat/kpis`
 * (`ky-thuat.kpis.index` — khung 5 tiêu chí 30/25/15/15/15, có workflow chấm và
 * duyệt, có cấu hình trọng số). Trang tham khảo cũ chỉ tổng hợp lại kế hoạch và
 * báo cáo ngày nên TRÙNG CHỨC NĂNG và dễ bị hiểu nhầm là điểm KPI thật.
 *
 * Hai URL cũ được giữ để link cũ không gãy, nhưng chỉ còn chuyển hướng 302.
 * QUY TẮC PHÂN QUYỀN KHÔNG ĐỔI: kiểm `AuthorizesTechnicalDashboard` TRƯỚC khi
 * chuyển hướng, nên nhân viên / trưởng phòng / người ngoài vẫn nhận 403 chứ
 * không bị đẩy sang trang khác.
 */
class TechnicalDashboardKpiController extends Controller
{
    use AuthorizesTechnicalDashboard;

    public function index(Request $request): RedirectResponse
    {
        return $this->toOfficialKpi($request);
    }

    public function show(Request $request, User $member): RedirectResponse
    {
        return $this->toOfficialKpi($request);
    }

    private function toOfficialKpi(Request $request): RedirectResponse
    {
        $this->authorizeTechnicalDashboard($request);

        return redirect()->route('ky-thuat.kpis.index');
    }
}
