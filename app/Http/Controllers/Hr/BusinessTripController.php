<?php

declare(strict_types=1);

namespace App\Http\Controllers\Hr;

use App\Http\Controllers\Controller;
use App\Models\HrBusinessTrip;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

final class BusinessTripController extends Controller
{
    private const MANAGER_ROLES = [
        'admin', 'management', 'manager', 'director', 'ceo',
        'hr', 'hr_manager', 'human_resources',
    ];

    public function index(Request $request): View
    {
        $this->ensureReady();

        /** @var User $user */
        $user = $request->user();
        $canManageAll = $this->canManageAll($user);
        $today = now()->toDateString();

        $base = HrBusinessTrip::query()
            ->with([
                'employee:id,name,department_id',
                'employee.department:id,name',
                'approver:id,name',
                'approvedBy:id,name',
                'completedBy:id,name',
            ]);
        $this->applyVisibility($base, $user, $canManageAll);

        $stats = [
            'pending' => (clone $base)->where('status', 'pending')->count(),
            'approved' => (clone $base)
                ->where('status', 'approved')
                ->whereDate('start_date', '>', $today)
                ->count(),
            'in_progress' => (clone $base)
                ->where('status', 'approved')
                ->whereDate('start_date', '<=', $today)
                ->whereDate('end_date', '>=', $today)
                ->count(),
            'completed' => (clone $base)->where('status', 'completed')->count(),
        ];

        $query = clone $base;
        $keyword = trim((string) $request->query('q', ''));
        if ($keyword !== '') {
            $like = '%'.$keyword.'%';
            $query->where(function (Builder $builder) use ($like): void {
                $builder->where('code', 'like', $like)
                    ->orWhere('location', 'like', $like)
                    ->orWhere('purpose', 'like', $like)
                    ->orWhereHas('employee', fn (Builder $employee): Builder => $employee->where('name', 'like', $like));
            });
        }

        $status = trim((string) $request->query('status', ''));
        if ($status === 'in_progress') {
            $query->where('status', 'approved')
                ->whereDate('start_date', '<=', $today)
                ->whereDate('end_date', '>=', $today);
        } elseif (in_array($status, ['pending', 'approved', 'completed', 'rejected', 'cancelled'], true)) {
            $query->where('status', $status);
        }

        $trips = $query
            ->orderByRaw("CASE status WHEN 'pending' THEN 0 WHEN 'approved' THEN 1 WHEN 'completed' THEN 2 ELSE 3 END")
            ->orderBy('start_date')
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        $employees = $canManageAll
            ? User::query()->with('department:id,name')->where('is_active', 1)->orderBy('name')->get(['id', 'name', 'department_id'])
            : collect([$user->loadMissing('department:id,name')]);

        $approvers = User::query()
            ->with('department:id,name')
            ->where('is_active', 1)
            ->orderBy('name')
            ->get(['id', 'name', 'department_id']);

        return view('hr.business-trips.index', [
            'trips' => $trips,
            'stats' => $stats,
            'employees' => $employees,
            'approvers' => $approvers,
            'canManageAll' => $canManageAll,
            'currentUserId' => (int) $user->id,
            'status' => $status,
            'keyword' => $keyword,
            'today' => $today,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->ensureReady();

        /** @var User $user */
        $user = $request->user();
        $canManageAll = $this->canManageAll($user);

        $validated = $request->validate([
            'employee_id' => ['nullable', 'integer', 'exists:users,id'],
            'approver_id' => ['required', 'integer', 'exists:users,id'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'location' => ['required', 'string', 'max:500'],
            'purpose' => ['required', 'string', 'max:5000'],
            'daily_allowance' => ['nullable', 'numeric', 'min:0', 'max:999999999999.99'],
            'meal_allowance' => ['nullable', 'numeric', 'min:0', 'max:999999999999.99'],
            'hotel_allowance' => ['nullable', 'numeric', 'min:0', 'max:999999999999.99'],
            'transport_allowance' => ['nullable', 'numeric', 'min:0', 'max:999999999999.99'],
            'other_allowance' => ['nullable', 'numeric', 'min:0', 'max:999999999999.99'],
            'advance_amount' => ['nullable', 'numeric', 'min:0', 'max:999999999999.99'],
            'allowance_note' => ['nullable', 'string', 'max:5000'],
        ]);

        $employeeId = $canManageAll
            ? (int) ($validated['employee_id'] ?: $user->id)
            : (int) $user->id;

        $trip = DB::transaction(function () use ($validated, $employeeId, $user): HrBusinessTrip {
            $trip = HrBusinessTrip::query()->create([
                'user_id' => $employeeId,
                'created_by' => (int) $user->id,
                'approver_id' => (int) $validated['approver_id'],
                'start_date' => $validated['start_date'],
                'end_date' => $validated['end_date'],
                'location' => trim((string) $validated['location']),
                'purpose' => trim((string) $validated['purpose']),
                'daily_allowance' => (float) ($validated['daily_allowance'] ?? 0),
                'meal_allowance' => (float) ($validated['meal_allowance'] ?? 0),
                'hotel_allowance' => (float) ($validated['hotel_allowance'] ?? 0),
                'transport_allowance' => (float) ($validated['transport_allowance'] ?? 0),
                'other_allowance' => (float) ($validated['other_allowance'] ?? 0),
                'advance_amount' => (float) ($validated['advance_amount'] ?? 0),
                'allowance_note' => $this->nullableText($validated['allowance_note'] ?? null),
                'status' => 'pending',
            ]);

            $trip->forceFill([
                'code' => sprintf('CT-%s-%04d', now()->format('Ymd'), (int) $trip->id),
            ])->save();

            return $trip;
        });

        return redirect()
            ->route('business-trips.index')
            ->with('success', 'Đã tạo lịch công tác '.$trip->code.' và chuyển sang Chờ duyệt.');
    }

    public function approve(Request $request, HrBusinessTrip $businessTrip): RedirectResponse
    {
        $this->assertCanApprove($request->user(), $businessTrip);
        abort_unless($businessTrip->status === 'pending', 422, 'Lịch công tác này không còn ở trạng thái chờ duyệt.');

        $validated = $request->validate([
            'approval_note' => ['nullable', 'string', 'max:5000'],
        ]);

        $businessTrip->update([
            'status' => 'approved',
            'approved_by' => (int) $request->user()->id,
            'approved_at' => now(),
            'approval_note' => $this->nullableText($validated['approval_note'] ?? null),
            'rejection_reason' => null,
            'rejected_at' => null,
        ]);

        return back()->with('success', 'Đã phê duyệt lịch công tác '.$businessTrip->code.'.');
    }

    public function reject(Request $request, HrBusinessTrip $businessTrip): RedirectResponse
    {
        $this->assertCanApprove($request->user(), $businessTrip);
        abort_unless($businessTrip->status === 'pending', 422, 'Lịch công tác này không còn ở trạng thái chờ duyệt.');

        $validated = $request->validate([
            'rejection_reason' => ['required', 'string', 'max:5000'],
        ]);

        $businessTrip->update([
            'status' => 'rejected',
            'rejection_reason' => trim((string) $validated['rejection_reason']),
            'rejected_at' => now(),
        ]);

        return back()->with('success', 'Đã từ chối lịch công tác '.$businessTrip->code.'.');
    }

    public function complete(Request $request, HrBusinessTrip $businessTrip): RedirectResponse
    {
        $this->assertCanComplete($request->user(), $businessTrip);
        abort_unless($businessTrip->status === 'approved', 422, 'Chỉ lịch đã duyệt mới có thể hoàn tất.');

        $validated = $request->validate([
            'completion_note' => ['nullable', 'string', 'max:5000'],
        ]);

        $businessTrip->update([
            'status' => 'completed',
            'completed_by' => (int) $request->user()->id,
            'completed_at' => now(),
            'completion_note' => $this->nullableText($validated['completion_note'] ?? null),
        ]);

        return back()->with('success', 'Đã hoàn tất lịch công tác '.$businessTrip->code.'.');
    }

    public function cancel(Request $request, HrBusinessTrip $businessTrip): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        $canCancel = $this->canManageAll($user)
            || (int) $businessTrip->user_id === (int) $user->id
            || (int) $businessTrip->created_by === (int) $user->id;
        abort_unless($canCancel, 403, 'Bạn không có quyền hủy lịch công tác này.');
        abort_unless(in_array($businessTrip->status, ['pending', 'approved'], true), 422, 'Lịch này không thể hủy ở trạng thái hiện tại.');

        $businessTrip->update(['status' => 'cancelled']);

        return back()->with('success', 'Đã hủy lịch công tác '.$businessTrip->code.'.');
    }

    private function ensureReady(): void
    {
        abort_unless(Schema::hasTable('hr_business_trips'), 503, 'Module Lịch công tác chưa được migrate.');
    }

    private function applyVisibility(Builder $query, User $user, bool $canManageAll): void
    {
        if ($canManageAll) {
            return;
        }

        $query->where(function (Builder $builder) use ($user): void {
            $builder->where('user_id', (int) $user->id)
                ->orWhere('approver_id', (int) $user->id)
                ->orWhere('created_by', (int) $user->id);
        });
    }

    private function assertCanApprove(User $user, HrBusinessTrip $trip): void
    {
        $allowed = $this->canManageAll($user) || (int) $trip->approver_id === (int) $user->id;
        abort_unless($allowed, 403, 'Bạn không phải người phê duyệt lịch công tác này.');
    }

    private function assertCanComplete(User $user, HrBusinessTrip $trip): void
    {
        $allowed = $this->canManageAll($user)
            || (int) $trip->user_id === (int) $user->id
            || (int) $trip->approver_id === (int) $user->id;
        abort_unless($allowed, 403, 'Bạn không có quyền hoàn tất lịch công tác này.');
    }

    private function canManageAll(User $user): bool
    {
        return method_exists($user, 'hasAnyRole') && $user->hasAnyRole(self::MANAGER_ROLES);
    }

    private function nullableText(mixed $value): ?string
    {
        $value = trim((string) ($value ?? ''));

        return $value !== '' ? $value : null;
    }
}
