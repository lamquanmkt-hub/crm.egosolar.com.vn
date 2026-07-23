<?php

declare(strict_types=1);

namespace App\Http\Controllers\System;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

/**
 * Booking phòng họp dạng lịch tháng.
 *
 * Chức năng duyệt đã được loại khỏi giao diện và quy trình sử dụng.
 * Booking mới/cập nhật được lưu trạng thái kỹ thuật là "approved" để tương
 * thích với cấu trúc bảng cũ. Người dùng chỉ quản lý trạng thái sử dụng:
 * chưa sử dụng / đã sử dụng.
 */
final class MeetingRoomBookingController extends Controller
{
    private string $table = 'meeting_room_bookings';

    public function index(Request $request): View
    {
        $this->ensureTableReady();

        $monthStart = $this->resolveMonth((string) $request->input('month', ''));
        $monthEnd = $monthStart->copy()->endOfMonth();
        $room = trim((string) $request->input('room_name', ''));
        $usageStatus = trim((string) $request->input('usage_status', ''));

        $query = DB::table($this->table)
            ->where('status', '!=', 'cancelled')
            ->where('start_at', '>=', $monthStart->copy()->startOfDay())
            ->where('start_at', '<', $monthEnd->copy()->addDay()->startOfDay())
            ->orderBy('start_at');

        if ($room !== '') {
            $query->where('room_name', $room);
        }

        if ($usageStatus !== '') {
            $query->where('usage_status', $usageStatus);
        }

        /** @var Collection<int, object> $monthBookings */
        $monthBookings = $query->get();

        $bookingsByDate = $monthBookings->groupBy(
            static fn (object $booking): string => Carbon::parse($booking->start_at)->toDateString()
        );

        $calendarStart = $monthStart->copy()->startOfWeek(Carbon::MONDAY);
        $calendarEnd = $monthEnd->copy()->endOfWeek(Carbon::SUNDAY);
        $calendarDays = [];

        for (
            $day = $calendarStart->copy();
            $day->lte($calendarEnd);
            $day->addDay()
        ) {
            $calendarDays[] = $day->copy();
        }

        $today = now()->toDateString();
        $stats = [
            'month_total' => $monthBookings->count(),
            'today' => $monthBookings->filter(
                static fn (object $booking): bool => Carbon::parse($booking->start_at)->toDateString() === $today
            )->count(),
            'unused' => $monthBookings->where('usage_status', 'unused')->count(),
            'used' => $monthBookings->where('usage_status', 'used')->count(),
        ];

        $rooms = [
            'Phòng họp lớn',
            'Phòng họp nhỏ',
            'Phòng đào tạo',
            'Phòng tiếp khách',
        ];

        $usageStatuses = $this->usageStatuses();
        $organizers = $this->organizers();
        $monthLabel = 'Tháng '.$monthStart->format('m/Y');
        $previousMonth = $monthStart->copy()->subMonthNoOverflow()->format('Y-m');
        $nextMonth = $monthStart->copy()->addMonthNoOverflow()->format('Y-m');
        $currentMonth = now()->format('Y-m');

        return view('meeting-room-bookings.index', compact(
            'monthBookings',
            'bookingsByDate',
            'calendarDays',
            'monthStart',
            'monthLabel',
            'previousMonth',
            'nextMonth',
            'currentMonth',
            'rooms',
            'usageStatuses',
            'organizers',
            'stats',
            'room',
            'usageStatus'
        ));
    }

    public function store(Request $request): RedirectResponse
    {
        $this->ensureTableReady();
        $data = $this->validatedData($request);
        $data['status'] = 'approved';
        $data['usage_status'] = 'unused';

        return DB::transaction(function () use ($data): RedirectResponse {
            $conflict = $this->findConflict(
                $data['room_name'],
                $data['start_at'],
                $data['end_at'],
                null,
                true
            );

            if ($conflict !== null) {
                return back()
                    ->withInput()
                    ->with('open_booking_modal', 'create')
                    ->withErrors([
                        'schedule_conflict' => $this->conflictMessage($conflict),
                    ]);
            }

            $now = now();

            DB::table($this->table)->insert(array_merge($data, [
                'created_by' => auth()->id(),
                'created_at' => $now,
                'updated_at' => $now,
            ]));

            return back()->with('success', 'Đã tạo lịch phòng họp.');
        }, 3);
    }

    public function update(Request $request, int $booking): RedirectResponse
    {
        $this->ensureTableReady();

        $existing = DB::table($this->table)->where('id', $booking)->first();
        abort_unless($existing !== null, 404);

        if ($request->boolean('_quick_usage')) {
            $quick = $request->validate([
                'usage_status' => ['required', 'string', 'in:unused,used'],
            ]);

            DB::table($this->table)
                ->where('id', $booking)
                ->update([
                    'usage_status' => $quick['usage_status'],
                    'status' => 'approved',
                    'updated_at' => now(),
                ]);

            $label = $quick['usage_status'] === 'used'
                ? 'Đã sử dụng'
                : 'Chưa sử dụng';

            return back()->with('success', 'Đã chuyển lịch sang “'.$label.'”.');
        }

        $data = $this->validatedData($request);
        $data['status'] = 'approved';
        $data['usage_status'] = $request->validate([
            'usage_status' => ['nullable', 'string', 'in:unused,used'],
        ])['usage_status'] ?? (string) ($existing->usage_status ?? 'unused');

        return DB::transaction(function () use ($booking, $data): RedirectResponse {
            $conflict = $this->findConflict(
                $data['room_name'],
                $data['start_at'],
                $data['end_at'],
                $booking,
                true
            );

            if ($conflict !== null) {
                return back()
                    ->withInput()
                    ->with('open_booking_modal', 'edit')
                    ->with('editing_booking_id', $booking)
                    ->withErrors([
                        'schedule_conflict' => $this->conflictMessage($conflict),
                    ]);
            }

            DB::table($this->table)
                ->where('id', $booking)
                ->update(array_merge($data, [
                    'updated_at' => now(),
                ]));

            return back()->with('success', 'Đã cập nhật lịch phòng họp.');
        }, 3);
    }

    public function destroy(int $booking): RedirectResponse
    {
        $this->ensureTableReady();

        DB::table($this->table)->where('id', $booking)->delete();

        return back()->with('success', 'Đã xóa lịch phòng họp.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedData(Request $request): array
    {
        $data = $request->validate([
            'room_name' => ['required', 'string', 'max:120'],
            'title' => ['required', 'string', 'max:255'],
            'organizer_name' => ['nullable', 'string', 'max:160'],
            'department' => ['nullable', 'string', 'max:160'],
            'attendees' => ['nullable', 'integer', 'min:1', 'max:500'],
            'start_at' => ['required', 'date'],
            'end_at' => ['required', 'date', 'after:start_at'],
            'note' => ['nullable', 'string', 'max:2000'],
        ], [
            'room_name.required' => 'Vui lòng chọn phòng họp.',
            'title.required' => 'Vui lòng nhập nội dung cuộc họp.',
            'start_at.required' => 'Vui lòng chọn thời gian bắt đầu.',
            'end_at.required' => 'Vui lòng chọn thời gian kết thúc.',
            'end_at.after' => 'Giờ kết thúc phải lớn hơn giờ bắt đầu.',
        ]);

        $data['attendees'] = $data['attendees'] ?? 1;
        $data['organizer_name'] = $data['organizer_name']
            ?? optional(auth()->user())->name;

        return $data;
    }

    private function resolveMonth(string $month): Carbon
    {
        if (preg_match('/^\d{4}-\d{2}$/', $month) === 1) {
            try {
                return Carbon::createFromFormat('Y-m', $month)->startOfMonth();
            } catch (\Throwable) {
                // Dùng tháng hiện tại khi query không hợp lệ.
            }
        }

        return now()->startOfMonth();
    }

    private function findConflict(
        string $roomName,
        string $startAt,
        string $endAt,
        ?int $ignoreId = null,
        bool $lock = false
    ): ?object {
        $query = DB::table($this->table)
            ->where('room_name', $roomName)
            ->where('status', '!=', 'cancelled')
            ->where('start_at', '<', Carbon::parse($endAt))
            ->where('end_at', '>', Carbon::parse($startAt))
            ->orderBy('start_at');

        if ($ignoreId !== null) {
            $query->where('id', '!=', $ignoreId);
        }

        if ($lock) {
            $query->lockForUpdate();
        }

        return $query->first();
    }

    private function conflictMessage(object $conflict): string
    {
        $start = Carbon::parse($conflict->start_at)->format('d/m/Y H:i');
        $end = Carbon::parse($conflict->end_at)->format('H:i');
        $title = trim((string) ($conflict->title ?? 'Lịch đã đặt'));
        $organizer = trim((string) ($conflict->organizer_name ?? ''));

        $message = sprintf(
            '%s đã có lịch “%s” từ %s đến %s',
            (string) $conflict->room_name,
            $title,
            $start,
            $end
        );

        if ($organizer !== '') {
            $message .= ' do '.$organizer.' đặt';
        }

        return $message.'. Không thể tạo lịch trùng.';
    }

    /**
     * @return array<string, string>
     */
    private function organizers(): array
    {
        $items = [];
        $roleByUserId = [];

        if (
            Schema::hasTable('roles')
            && Schema::hasTable('model_has_roles')
            && Schema::hasColumn('roles', 'name')
            && Schema::hasColumn('model_has_roles', 'model_id')
            && Schema::hasColumn('model_has_roles', 'role_id')
        ) {
            try {
                $roleRows = DB::table('model_has_roles')
                    ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
                    ->select(
                        'model_has_roles.model_id',
                        DB::raw('GROUP_CONCAT(roles.name SEPARATOR ", ") as role_names')
                    )
                    ->groupBy('model_has_roles.model_id')
                    ->get();

                foreach ($roleRows as $row) {
                    $roleByUserId[(int) $row->model_id] = (string) $row->role_names;
                }
            } catch (\Throwable) {
                $roleByUserId = [];
            }
        }

        if (Schema::hasTable('users')) {
            $columns = Schema::getColumnListing('users');
            $select = ['id'];

            foreach (['name', 'email', 'role'] as $column) {
                if (in_array($column, $columns, true)) {
                    $select[] = $column;
                }
            }

            $query = DB::table('users');

            if (in_array('deleted_at', $columns, true)) {
                $query->whereNull('deleted_at');
            }

            if (in_array('name', $columns, true)) {
                $query->orderBy('name');
            } elseif (in_array('email', $columns, true)) {
                $query->orderBy('email');
            } else {
                $query->orderBy('id');
            }

            foreach ($query->limit(300)->get($select) as $user) {
                $name = trim((string) ($user->name ?? ''));
                $email = trim((string) ($user->email ?? ''));
                $role = trim((string) (
                    $user->role
                    ?? ($roleByUserId[(int) $user->id] ?? '')
                ));

                $value = $name !== ''
                    ? $name
                    : ($email !== '' ? $email : 'User #'.$user->id);

                $label = $value;

                if ($role !== '') {
                    $label .= ' - '.$role;
                } elseif ($email !== '' && $email !== $value) {
                    $label .= ' - '.$email;
                }

                $items[$value] = $label;
            }
        }

        $authName = trim((string) (
            optional(auth()->user())->name
            ?: optional(auth()->user())->email
        ));

        if ($authName !== '' && ! array_key_exists($authName, $items)) {
            $items = [$authName => $authName] + $items;
        }

        return $items;
    }

    /**
     * @return array<string, string>
     */
    private function usageStatuses(): array
    {
        return [
            'unused' => 'Chưa sử dụng',
            'used' => 'Đã sử dụng',
        ];
    }

    private function ensureTableReady(): void
    {
        abort_unless(
            Schema::hasTable($this->table),
            500,
            'Chưa có bảng meeting_room_bookings. Vui lòng chạy migration.'
        );

        abort_unless(
            Schema::hasColumn($this->table, 'usage_status'),
            500,
            'Bảng meeting_room_bookings chưa có cột usage_status. Vui lòng chạy migration.'
        );
    }
}
