<?php

namespace App\Http\Controllers\Synced\Technical;

use App\Http\Controllers\Controller;
use App\Http\Requests\Synced\Technical\SolarMaintenanceApprovalRequest;
use App\Models\SolarMaintenanceSchedule;
use App\Services\Synced\Technical\SolarMaintenanceApprovalService;
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
     * Gửi kết quả bảo trì cho Trưởng phòng kỹ thuật phê duyệt.
     */
    public function submit(SolarMaintenanceApprovalRequest $request, SolarMaintenanceSchedule $schedule): RedirectResponse
    {
        $this->authorize('submitForApproval', $schedule);
        $this->service->submit($schedule, $request->user(), $request->validated('comment'));

        return back()->with('success', 'Đã gửi Trưởng phòng kỹ thuật phê duyệt.');
    }

    /**
     * Phê duyệt kết quả kỹ thuật của đợt bảo trì.
     */
    public function approve(SolarMaintenanceApprovalRequest $request, SolarMaintenanceSchedule $schedule): RedirectResponse
    {
        $this->authorize('approve', $schedule);
        $this->service->approve($schedule, $request->user(), $request->validated('comment'));

        return back()->with('success', 'Đã phê duyệt kết quả kỹ thuật.');
    }

    /**
     * Đóng công việc sau khi kết quả kỹ thuật đã được phê duyệt.
     */
    public function complete(SolarMaintenanceApprovalRequest $request, SolarMaintenanceSchedule $schedule): RedirectResponse
    {
        $this->authorize('approve', $schedule);
        $this->service->complete($schedule, $request->user(), $request->validated('comment'));

        return back()->with('success', 'Đã hoàn thành và đóng hồ sơ đợt bảo trì.');
    }

    /**
     * Trả lại kết quả và yêu cầu kỹ thuật viên chỉnh sửa.
     */
    public function requestRevision(SolarMaintenanceApprovalRequest $request, SolarMaintenanceSchedule $schedule): RedirectResponse
    {
        $this->authorize('requestRevision', $schedule);
        $comment = trim((string) $request->input('comment'));
        $this->service->requestRevision($schedule, $request->user(), $comment);

        return back()->with('success', 'Đã trả lại và yêu cầu kỹ thuật viên chỉnh sửa.');
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
