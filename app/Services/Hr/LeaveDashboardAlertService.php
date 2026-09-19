<?php

declare(strict_types=1);

namespace App\Services\Hr;

use App\Models\LeaveRequest;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;

final class LeaveDashboardAlertService
{
    public function __construct(
        private readonly LeaveApprovalAccessService $approvalAccess
    ) {}

    /**
     * Payload nhỏ dùng chung cho Dashboard, Sidebar và Topbar.
     * Không đưa lý do hoặc file chứng minh ra ngoài trang chi tiết đơn.
     *
     * @return array<string, mixed>
     */
    public function snapshot(?User $user): array
    {
        if (! $user || ! Schema::hasTable('leave_requests')) {
            return $this->emptyPayload();
        }

        $key = 'ego:leave-dashboard-alert:v1:user:'.(int) $user->id;

        return Cache::remember(
            $key,
            now()->addSeconds(60),
            fn (): array => $this->build($user)
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function build(User $user): array
    {
        $canReview = $this->approvalAccess->canReview($user);
        $reviewUrl = Route::has('hr.leave.index')
            ? route('hr.leave.index', ['tab' => 'approval', 'status' => 'pending'])
            : url('/nhan-su/leave-requests?tab=approval&status=pending');
        $mineUrl = Route::has('hr.leave.index')
            ? route('hr.leave.index', ['tab' => 'mine'])
            : url('/nhan-su/leave-requests?tab=mine');

        $pendingApprovalCount = 0;
        $urgentApprovalCount = 0;
        $overdueApprovalCount = 0;
        $latestReview = null;

        if ($canReview) {
            $base = LeaveRequest::query()
                ->where('status', 'pending');

            $this->approvalAccess->scopeReviewable($base, $user);

            $pendingApprovalCount = (int) (clone $base)->count();

            if ($pendingApprovalCount > 0) {
                $tomorrow = now()->addDay()->toDateString();
                $overdueAt = now()->subHours(8);

                $urgentApprovalCount = (int) (clone $base)
                    ->whereDate('start_date', '<=', $tomorrow)
                    ->count();

                $overdueApprovalCount = (int) (clone $base)
                    ->where('created_at', '<=', $overdueAt)
                    ->count();

                $latestModel = (clone $base)
                    ->with(['user.department', 'approver'])
                    ->orderBy('start_date')
                    ->orderBy('start_time')
                    ->orderBy('created_at')
                    ->first();

                if ($latestModel) {
                    $latestReview = $this->leaveItem($latestModel);
                }
            }
        }

        $minePendingCount = (int) LeaveRequest::query()
            ->where('user_id', $user->id)
            ->where('status', 'pending')
            ->count();

        $mineLatestModel = LeaveRequest::query()
            ->with('approver')
            ->where('user_id', $user->id)
            ->latest('updated_at')
            ->latest('id')
            ->first();

        $mineLatest = null;

        if ($mineLatestModel) {
            $isRecent = $mineLatestModel->status === 'pending'
                || optional($mineLatestModel->updated_at)->greaterThanOrEqualTo(now()->subDays(14));

            if ($isRecent) {
                $mineLatest = $this->leaveItem($mineLatestModel);
            }
        }

        return [
            'can_review' => $canReview,
            'pending_approval_count' => $pendingApprovalCount,
            'urgent_approval_count' => $urgentApprovalCount,
            'overdue_approval_count' => $overdueApprovalCount,
            'latest_review' => $latestReview,
            'mine_pending_count' => $minePendingCount,
            'mine_latest' => $mineLatest,
            'review_url' => $reviewUrl,
            'mine_url' => $mineUrl,
            'has_attention' => $pendingApprovalCount > 0 || $minePendingCount > 0,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function leaveItem(LeaveRequest $leave): array
    {
        $start = Carbon::parse($leave->start_date)->startOfDay();
        $end = Carbon::parse($leave->end_date)->startOfDay();

        if (! empty($leave->start_time)) {
            $time = Carbon::parse((string) $leave->start_time);
            $start->setTime($time->hour, $time->minute);
        }

        $dateLabel = $start->isSameDay($end)
            ? $start->format('d/m/Y')
            : $start->format('d/m/Y').' – '.$end->format('d/m/Y');

        if (! empty($leave->start_time)) {
            $dateLabel .= ' · '.$start->format('H:i');
        }

        $departmentName = null;
        if ($leave->relationLoaded('user') && $leave->user) {
            $departmentName = optional($leave->user->department)->name;
        }

        return [
            'id' => (int) $leave->id,
            'employee_name' => (string) optional($leave->user)->name,
            'department_name' => (string) ($departmentName ?: 'Chưa xác định phòng ban'),
            'request_type' => (string) $leave->request_type,
            'request_type_label' => (string) $leave->request_type_label,
            'leave_type_label' => (string) $leave->leave_type_label,
            'status' => (string) $leave->status,
            'status_label' => (string) $leave->status_label,
            'date_label' => $dateLabel,
            'approver_name' => (string) optional($leave->approver)->name,
            'created_label' => optional($leave->created_at)->format('d/m/Y H:i'),
            'updated_label' => optional($leave->updated_at)->format('d/m/Y H:i'),
            'starts_within_24h' => $start->lessThanOrEqualTo(now()->addDay()),
            'waiting_over_8h' => optional($leave->created_at)?->lessThanOrEqualTo(now()->subHours(8)) ?? false,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function emptyPayload(): array
    {
        return [
            'can_review' => false,
            'pending_approval_count' => 0,
            'urgent_approval_count' => 0,
            'overdue_approval_count' => 0,
            'latest_review' => null,
            'mine_pending_count' => 0,
            'mine_latest' => null,
            'review_url' => url('/nhan-su/leave-requests?tab=approval&status=pending'),
            'mine_url' => url('/nhan-su/leave-requests?tab=mine'),
            'has_attention' => false,
        ];
    }
}
