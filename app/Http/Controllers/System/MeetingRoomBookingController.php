<?php

namespace App\Http\Controllers\System;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Đặt phòng họp: danh sách, tạo, sửa, xóa booking và kiểm tra trùng khung giờ.
 */
class MeetingRoomBookingController extends Controller
{
    private string $table = 'meeting_room_bookings';

    /**
     * Danh sách booking với bộ lọc phòng/trạng thái/ngày, thống kê và lịch hôm nay.
     */
    public function index(Request $request)
    {
        $this->ensureTableReady();

        $today = now()->toDateString();
        $room = trim((string) $request->input('room_name', ''));
        $status = trim((string) $request->input('status', ''));
        $date = $request->input('date', $today);

        $query = DB::table($this->table)->orderByDesc('start_at');

        if ($room !== '') {
            $query->where('room_name', $room);
        }

        if ($status !== '') {
            $query->where('status', $status);
        }

        if ($date !== '') {
            $query->whereDate('start_at', $date);
        }

        $bookings = $query->paginate(20)->withQueryString();

        $rooms = ['Phòng họp lớn', 'Phòng họp nhỏ', 'Phòng đào tạo', 'Phòng tiếp khách'];
        $statuses = $this->statuses();
        $organizers = $this->organizers();

        $stats = [
            'today' => DB::table($this->table)->whereDate('start_at', $today)->count(),
            'processing' => DB::table($this->table)->whereIn('status', ['pending', 'approved'])->count(),
            'month' => DB::table($this->table)
                ->whereBetween('start_at', [now()->startOfMonth(), now()->endOfMonth()])
                ->count(),
            'cancelled' => DB::table($this->table)->where('status', 'cancelled')->count(),
        ];

        $todayBookings = DB::table($this->table)
            ->whereDate('start_at', $today)
            ->orderBy('start_at')
            ->limit(10)
            ->get();

        return view('meeting-room-bookings.index', compact('bookings', 'rooms', 'statuses', 'organizers', 'stats', 'todayBookings', 'date', 'room', 'status'));
    }

    /**
     * Tạo booking phòng họp mới, chặn nếu trùng khung giờ với booking khác.
     */
    public function store(Request $request)
    {
        $this->ensureTableReady();
        $data = $this->validatedData($request);

        if ($this->hasConflict($data['room_name'], $data['start_at'], $data['end_at'])) {
            return back()
                ->withInput()
                ->withErrors(['room_name' => 'Phòng họp này đã có lịch trong khung giờ vừa chọn. Vui lòng chọn giờ khác.']);
        }

        $data['created_by'] = auth()->id();
        $data['created_at'] = now();
        $data['updated_at'] = now();

        DB::table($this->table)->insert($data);

        return back()->with('success', 'Đã tạo booking phòng họp thành công.');
    }

    /**
     * Cập nhật booking phòng họp, chặn nếu trùng khung giờ với booking khác.
     */
    public function update(Request $request, int $booking)
    {
        $this->ensureTableReady();
        abort_unless(DB::table($this->table)->where('id', $booking)->exists(), 404);

        $data = $this->validatedData($request);

        if ($this->hasConflict($data['room_name'], $data['start_at'], $data['end_at'], $booking)) {
            return back()
                ->withInput()
                ->withErrors(['room_name' => 'Phòng họp này đã có lịch khác trùng khung giờ. Vui lòng kiểm tra lại.']);
        }

        $data['updated_at'] = now();

        DB::table($this->table)->where('id', $booking)->update($data);

        return back()->with('success', 'Đã cập nhật booking phòng họp.');
    }

    /**
     * Xóa booking phòng họp.
     */
    public function destroy(int $booking)
    {
        $this->ensureTableReady();
        DB::table($this->table)->where('id', $booking)->delete();

        return back()->with('success', 'Đã xóa booking phòng họp.');
    }

    /**
     * Validate dữ liệu booking, mặc định 1 người tham dự và người tổ chức là user hiện tại.
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
            'status' => ['required', 'string', 'in:pending,approved,done,cancelled'],
            'note' => ['nullable', 'string', 'max:2000'],
        ], [
            'room_name.required' => 'Vui lòng chọn phòng họp.',
            'title.required' => 'Vui lòng nhập nội dung cuộc họp.',
            'start_at.required' => 'Vui lòng chọn thời gian bắt đầu.',
            'end_at.required' => 'Vui lòng chọn thời gian kết thúc.',
            'end_at.after' => 'Giờ kết thúc phải lớn hơn giờ bắt đầu.',
        ]);

        $data['attendees'] = $data['attendees'] ?? 1;
        $data['organizer_name'] = $data['organizer_name'] ?? optional(auth()->user())->name;

        return $data;
    }

    /**
     * Kiểm tra phòng có booking khác (chờ duyệt/đã duyệt) trùng khung giờ hay không.
     */
    private function hasConflict(string $roomName, string $startAt, string $endAt, ?int $ignoreId = null): bool
    {
        $query = DB::table($this->table)
            ->where('room_name', $roomName)
            ->whereIn('status', ['pending', 'approved'])
            ->where('start_at', '<', Carbon::parse($endAt))
            ->where('end_at', '>', Carbon::parse($startAt));

        if ($ignoreId) {
            $query->where('id', '!=', $ignoreId);
        }

        return $query->exists();
    }

    /**
     * Danh sách người tổ chức khả dụng (tên user kèm role/email), luôn có user hiện tại đứng đầu.
     */
    private function organizers(): array
    {
        $items = [];
        $roleByUserId = [];

        if (
            Schema::hasTable('roles') &&
            Schema::hasTable('model_has_roles') &&
            Schema::hasColumn('roles', 'name') &&
            Schema::hasColumn('model_has_roles', 'model_id') &&
            Schema::hasColumn('model_has_roles', 'role_id')
        ) {
            try {
                $roleRows = DB::table('model_has_roles')
                    ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
                    ->select('model_has_roles.model_id', DB::raw('GROUP_CONCAT(roles.name SEPARATOR ", ") as role_names'))
                    ->groupBy('model_has_roles.model_id')
                    ->get();

                foreach ($roleRows as $row) {
                    $roleByUserId[(int) $row->model_id] = (string) $row->role_names;
                }
            } catch (\Throwable $e) {
                $roleByUserId = [];
            }
        }

        if (Schema::hasTable('users')) {
            $columns = Schema::getColumnListing('users');

            $select = ['id'];

            foreach (['name', 'email', 'role'] as $col) {
                if (in_array($col, $columns, true)) {
                    $select[] = $col;
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
                $role = trim((string) ($user->role ?? ($roleByUserId[(int) $user->id] ?? '')));

                $value = $name !== '' ? $name : ($email !== '' ? $email : ('User #'.$user->id));
                $label = $value;

                if ($role !== '') {
                    $label .= ' - '.$role;
                } elseif ($email !== '' && $email !== $value) {
                    $label .= ' - '.$email;
                }

                $items[$value] = $label;
            }
        }

        $authName = trim((string) (optional(auth()->user())->name ?: optional(auth()->user())->email));

        if ($authName !== '' && ! array_key_exists($authName, $items)) {
            $items = [$authName => $authName] + $items;
        }

        return $items;
    }

    /**
     * Bảng nhãn trạng thái booking tiếng Việt.
     */
    private function statuses(): array
    {
        return [
            'pending' => 'Chờ duyệt',
            'approved' => 'Đã duyệt',
            'done' => 'Hoàn thành',
            'cancelled' => 'Đã hủy',
        ];
    }

    /**
     * Chặn 500 nếu chưa có bảng meeting_room_bookings.
     */
    private function ensureTableReady(): void
    {
        abort_unless(Schema::hasTable($this->table), 500, 'Chưa có bảng meeting_room_bookings. Vui lòng chạy php artisan migrate --force.');
    }
}
