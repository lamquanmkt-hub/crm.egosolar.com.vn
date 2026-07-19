<?php

namespace App\Http\Controllers\Technical;

use App\Http\Controllers\Controller;
use App\Http\Requests\Technical\SolarMaintenanceApprovalRequest;
use App\Models\SolarMaintenanceSchedule;
use App\Services\SolarMaintenanceApprovalService;
use Illuminate\Http\RedirectResponse;

class SolarMaintenanceApprovalController extends Controller
{
    public function __construct(private readonly SolarMaintenanceApprovalService $service)
    {
    }

    public function submit(SolarMaintenanceApprovalRequest $request, SolarMaintenanceSchedule $schedule): RedirectResponse
    {
        $this->authorize('submitForApproval', $schedule);
        $this->service->submit($schedule, $request->user(), $request->validated('comment'));
        return back()->with('success', 'Đã gửi Trưởng phòng kỹ thuật phê duyệt.');
    }

    public function approve(SolarMaintenanceApprovalRequest $request, SolarMaintenanceSchedule $schedule): RedirectResponse
    {
        $this->authorize('approve', $schedule);
        $this->service->approve($schedule, $request->user(), $request->validated('comment'));
        return back()->with('success', 'Đã phê duyệt kết quả kỹ thuật.');
    }

    public function requestRevision(SolarMaintenanceApprovalRequest $request, SolarMaintenanceSchedule $schedule): RedirectResponse
    {
        $this->authorize('requestRevision', $schedule);
        $comment = trim((string) $request->input('comment'));
        $this->service->requestRevision($schedule, $request->user(), $comment);
        return back()->with('success', 'Đã trả lại và yêu cầu kỹ thuật viên chỉnh sửa.');
    }

    public function reject(SolarMaintenanceApprovalRequest $request, SolarMaintenanceSchedule $schedule): RedirectResponse
    {
        $this->authorize('reject', $schedule);
        $comment = trim((string) $request->input('comment'));
        $this->service->reject($schedule, $request->user(), $comment);
        return back()->with('success', 'Đã từ chối kết quả và ghi lịch sử phê duyệt.');
    }

    public function reopen(SolarMaintenanceApprovalRequest $request, SolarMaintenanceSchedule $schedule): RedirectResponse
    {
        $this->authorize('reopen', $schedule);
        $comment = trim((string) $request->input('comment'));
        $this->service->reopen($schedule, $request->user(), $comment);
        return back()->with('success', 'Đã mở lại công việc để tiếp tục xử lý.');
    }
}
