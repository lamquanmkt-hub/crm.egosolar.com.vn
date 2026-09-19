<?php

namespace App\Http\Controllers\Technical;

use App\Http\Controllers\Controller;
use App\Models\SolarMaintenanceSchedule;
use App\Services\Technical\SolarMaintenanceAssignmentApprovalService;
use App\Support\SolarMaintenanceAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class SolarMaintenanceAssignmentApprovalController extends Controller
{
    public function __construct(
        private readonly SolarMaintenanceAssignmentApprovalService $service
    ) {}

    public function submit(Request $request, SolarMaintenanceSchedule $schedule): RedirectResponse
    {
        abort_unless(SolarMaintenanceAccess::canAssign($request->user()), 403);
        $data = $request->validate(['comment' => ['nullable', 'string', 'max:2000']]);
        $this->service->submit($schedule, $request->user(), $data['comment'] ?? null);

        return back()->with('success', 'Đã gửi phân công cho sếp duyệt. Chưa thể bắt đầu công việc cho tới khi được duyệt.');
    }

    public function approve(Request $request, SolarMaintenanceSchedule $schedule): RedirectResponse
    {
        $this->authorize('approve', $schedule);
        $data = $request->validate(['comment' => ['nullable', 'string', 'max:2000']]);
        $paymentRequestId = $this->service->approve($schedule, $request->user(), $data['comment'] ?? null);

        if ($paymentRequestId) {
            return redirect()
                ->route('payment_requests.show', $paymentRequestId)
                ->with('success', 'Đã duyệt phân công và tự tạo ĐNTT nhân công ngoài. Sau khi xử lý ĐNTT, quay lại O&M để bắt đầu thực hiện.');
        }

        return back()->with('success', 'Đã duyệt phân công. Bước Thực hiện đã được mở.');
    }

    public function requestRevision(Request $request, SolarMaintenanceSchedule $schedule): RedirectResponse
    {
        $this->authorize('requestRevision', $schedule);
        $data = $request->validate(['comment' => ['required', 'string', 'max:2000']]);
        $this->service->requestRevision($schedule, $request->user(), $data['comment']);

        return back()->with('success', 'Đã trả lại phân công để chỉnh sửa.');
    }

    public function reject(Request $request, SolarMaintenanceSchedule $schedule): RedirectResponse
    {
        $this->authorize('reject', $schedule);
        $data = $request->validate(['comment' => ['required', 'string', 'max:2000']]);
        $this->service->reject($schedule, $request->user(), $data['comment']);

        return back()->with('success', 'Đã từ chối phân công.');
    }
}
