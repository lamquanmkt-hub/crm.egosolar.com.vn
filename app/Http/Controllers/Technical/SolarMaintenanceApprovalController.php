<?php

namespace App\Http\Controllers\Technical;

use App\Http\Controllers\Controller;
use App\Http\Requests\Technical\SolarMaintenanceApprovalRequest;
use App\Models\SolarMaintenanceSchedule;
use App\Services\Technical\SolarMaintenanceApprovalService;
use Illuminate\Http\RedirectResponse;

/**
 * Xử lý luồng phê duyệt kết quả bảo trì điện mặt trời (gửi duyệt, duyệt, trả lại, từ chối, mở lại).
 */
class SolarMaintenanceApprovalController extends Controller
{
    /**
     * Khởi tạo controller với service phê duyệt bảo trì.
     */
    public function __construct(private readonly SolarMaintenanceApprovalService $service) {}

    /**
     * V3: endpoint cũ /gui-duyet được giữ để tương thích cache/tab cũ.
     * Thao tác này nay hoàn tất trực tiếp đợt bảo trì, không tạo bước phê duyệt cuối.
     */
    public function submit(SolarMaintenanceApprovalRequest $request, SolarMaintenanceSchedule $schedule): RedirectResponse
    {
        $this->authorize('submitForApproval', $schedule);
        $this->service->submit($schedule, $request->user(), $request->validated('comment'));

        return back()->with('success', 'Đã hoàn tất đợt bảo trì. Không cần gửi duyệt.');
    }

    /**
     * Phê duyệt kết quả kỹ thuật của đợt bảo trì.
     */
    public function approve(SolarMaintenanceApprovalRequest $request, SolarMaintenanceSchedule $schedule): RedirectResponse
    {
        $this->authorize('approve', $schedule);
        $this->service->approve($schedule, $request->user(), $request->validated('comment'));

        return back()->with(
            'success',
            'Đã phê duyệt riêng đợt '.((int) ($schedule->round_no ?: 1)).'/'.((int) ($schedule->total_rounds ?: 1)).'. Các đợt khác không thay đổi.'
        );
    }

    /**
     * Trả lại kết quả và yêu cầu kỹ thuật viên chỉnh sửa.
     */
    public function requestRevision(SolarMaintenanceApprovalRequest $request, SolarMaintenanceSchedule $schedule): RedirectResponse
    {
        $this->authorize('requestRevision', $schedule);
        $comment = trim((string) $request->input('comment'));
        $this->service->requestRevision($schedule, $request->user(), $comment);

        return back()->with(
            'success',
            'Đã yêu cầu bổ sung riêng hồ sơ đợt '.((int) ($schedule->round_no ?: 1)).'/'.((int) ($schedule->total_rounds ?: 1)).'.'
        );
    }

    /**
     * Từ chối kết quả bảo trì và ghi lịch sử phê duyệt.
     */
    public function reject(SolarMaintenanceApprovalRequest $request, SolarMaintenanceSchedule $schedule): RedirectResponse
    {
        $this->authorize('reject', $schedule);
        $comment = trim((string) $request->input('comment'));
        $this->service->reject($schedule, $request->user(), $comment);

        return back()->with('success', 'Đã từ chối kết quả và ghi lịch sử phê duyệt.');
    }

    /**
     * Mở lại công việc bảo trì đã đóng để tiếp tục xử lý.
     */
    public function reopen(SolarMaintenanceApprovalRequest $request, SolarMaintenanceSchedule $schedule): RedirectResponse
    {
        $this->authorize('reopen', $schedule);
        $comment = trim((string) $request->input('comment'));
        $this->service->reopen($schedule, $request->user(), $comment);

        return back()->with('success', 'Đã mở lại công việc để tiếp tục xử lý.');
    }
}
