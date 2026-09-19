<?php

namespace App\Http\Controllers\Technical;

use App\Http\Controllers\Controller;
use App\Models\SolarMaintenanceComment;
use App\Models\SolarMaintenanceSchedule;
use App\Support\SolarMaintenanceAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class SolarMaintenanceCommentController extends Controller
{
    public function store(Request $request, SolarMaintenanceSchedule $schedule): RedirectResponse
    {
        $this->authorize('view', $schedule);

        $data = $request->validate([
            'body' => ['required', 'string', 'max:5000'],
        ]);

        $schedule->comments()->create([
            'company_id' => $schedule->company_id ?: $schedule->site?->company_id,
            'user_id' => $request->user()->id,
            'comment_type' => 'internal',
            'body' => trim($data['body']),
            'mentions' => [],
        ]);

        return back()->with('success', 'Đã thêm bình luận nội bộ.');
    }

    public function destroy(
        Request $request,
        SolarMaintenanceSchedule $schedule,
        SolarMaintenanceComment $comment
    ): RedirectResponse {
        abort_unless((int) $comment->maintenance_schedule_id === (int) $schedule->id, 404);
        $this->authorize('view', $schedule);
        abort_unless(
            (int) $comment->user_id === (int) $request->user()->id
            || SolarMaintenanceAccess::isManager($request->user()),
            403
        );

        $comment->delete();

        return back()->with('success', 'Đã xóa bình luận.');
    }
}
