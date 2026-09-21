<?php

declare(strict_types=1);

namespace App\Http\Controllers\Technical;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Technical\Concerns\AuthorizesTechnicalDashboard;
use App\Models\Technical\TechnicalPlanItem;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * ROUTE CŨ `/ky-thuat/dashboard/bao-cao` — NAY CHỈ CÒN CHUYỂN HƯỚNG.
 *
 * Nghiệp vụ mới (2026-09): báo cáo ngày và tổng hợp tuần dùng CHUNG một trang
 * `/ky-thuat/bao-cao-ngay` với hai tab, cho cả ba vai trò. Vì vậy không còn
 * Dashboard báo cáo riêng của Admin — nếu giữ, hệ thống sẽ có HAI trang cùng
 * hiển thị "tổng hợp tuần" cạnh nhau.
 *
 * Toàn bộ phép tính công phu của trang cũ KHÔNG bị bỏ đi: chúng nằm ở
 * `TechnicalPlanVsActualService` và `TechnicalDashboardReadService`, nay được
 * tab "Tổng hợp tuần" của `TechnicalDailyReportController` gọi lại nguyên vẹn.
 *
 * Quy tắc chuyển hướng:
 *   - Kiểm QUYỀN TRƯỚC: người không có quyền vẫn nhận 403, KHÔNG dùng redirect
 *     để che lỗi phân quyền.
 *   - 302 MỘT CHIỀU sang `technical.daily-reports.index?tab=weekly-summary`.
 *     Route đích KHÔNG bao giờ chuyển hướng ngược lại => không có vòng lặp.
 *   - Chuyển tiếp các tham số tương thích (xem `mapQuery()`).
 */
class TechnicalDashboardReportController extends Controller
{
    use AuthorizesTechnicalDashboard;

    public const PERIOD_WEEK = 'week';

    public const PERIOD_MONTH = 'month';

    public function index(Request $request): RedirectResponse
    {
        $this->authorizeTechnicalDashboard($request);

        return redirect()->route(
            'technical.daily-reports.index',
            $this->mapQuery($request),
            302,
        );
    }

    /**
     * Ánh xạ query của trang cũ sang tên query THẬT của trang mới.
     *
     * Trang mới (`/ky-thuat/bao-cao-ngay`, tab Tổng hợp tuần) nhận:
     *   tab, week, user_id, site_id, work_status, from, to.
     *
     * Trang cũ dùng: period|mode, week|date, month, user_id, site_id, status.
     *
     * Lưu ý quan trọng: `status` của trang cũ là trạng thái CÔNG VIỆC
     * (planned/done/...), còn `status` của trang mới là trạng thái DUYỆT báo cáo
     * (draft/submitted/...). Vì vậy ánh xạ sang `work_status`, KHÔNG giữ nguyên
     * tên — nếu giữ nguyên sẽ lọc nhầm sang một bộ giá trị khác hẳn.
     *
     * `period=month` không có trên trang mới (trang mới theo TUẦN): lấy tuần
     * chứa ngày đầu tháng được yêu cầu để người dùng không bị mất ngữ cảnh.
     *
     * @return array<string, string|int>
     */
    private function mapQuery(Request $request): array
    {
        $params = ['tab' => TechnicalDailyReportController::TAB_WEEKLY];

        $week = $this->resolveWeekParam($request);

        if ($week !== null) {
            $params['week'] = $week;
        }

        foreach (['user_id', 'site_id'] as $key) {
            if ($request->filled($key)) {
                $params[$key] = (int) $request->query($key);
            }
        }

        if ($request->filled('status')
            && array_key_exists((string) $request->query('status'), TechnicalPlanItem::STATUS_LABELS)) {
            $params['work_status'] = (string) $request->query('status');
        }

        foreach (['from', 'to'] as $key) {
            if ($request->filled($key)) {
                $params[$key] = (string) $request->query($key);
            }
        }

        return $params;
    }

    private function resolveWeekParam(Request $request): ?string
    {
        $period = (string) ($request->query('period', '') ?: $request->query('mode', ''));

        if ($period === self::PERIOD_MONTH && $request->filled('month')) {
            try {
                return Carbon::createFromFormat('Y-m', (string) $request->query('month'))
                    ->startOfMonth()
                    ->toDateString();
            } catch (\Throwable) {
                return null;
            }
        }

        $raw = (string) ($request->query('week', '') ?: $request->query('date', ''));

        if ($raw === '') {
            return null;
        }

        try {
            return Carbon::parse($raw)->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }
}
