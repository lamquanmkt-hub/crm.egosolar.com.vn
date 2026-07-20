<?php

namespace App\Http\Controllers\Hr;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\Position;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

/**
 * Controller quản lý nhân viên: CRUD, lương, sơ đồ tổ chức và nhóm phòng ban.
 */
class EmployeeController extends Controller
{
    /**
     * Tự thêm các cột lương (official/probation/internship) vào bảng users nếu chưa có.
     */
    private function ensureEmployeeSalaryColumns(): void
    {
        try {
            if (! \Illuminate\Support\Facades\Schema::hasTable('users')) {
                return;
            }

            \Illuminate\Support\Facades\Schema::table('users', function (\Illuminate\Database\Schema\Blueprint $table) {
                if (! \Illuminate\Support\Facades\Schema::hasColumn('users', 'official_salary')) {
                    $table->decimal('official_salary', 15, 2)->nullable()->after('is_active');
                }

                if (! \Illuminate\Support\Facades\Schema::hasColumn('users', 'probation_salary')) {
                    $table->decimal('probation_salary', 15, 2)->nullable()->after('official_salary');
                }

                if (! \Illuminate\Support\Facades\Schema::hasColumn('users', 'internship_salary')) {
                    $table->decimal('internship_salary', 15, 2)->nullable()->after('probation_salary');
                }
            });
        } catch (\Throwable $e) {
            // Nếu DB user chưa có quyền ALTER TABLE thì không làm sập trang HR.
        }
    }

    /**
     * Chuẩn hoá chuỗi lương nhập vào (bỏ đ, dấu chấm, phẩy, khoảng trắng) về số float không âm.
     *
     * @param  mixed  $value  Giá trị lương đầu vào
     */
    private function normalizeSalaryInput($value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        $value = trim((string) $value);
        $value = str_replace([' ', 'đ', 'Đ', ','], ['', '', '', ''], $value);
        $value = str_replace('.', '', $value);

        if ($value === '' || ! is_numeric($value)) {
            return null;
        }

        return max(0, (float) $value);
    }

    /**
     * Trả về rule validate cho các trường lương.
     */
    private function salaryValidationRules(): array
    {
        return [
            'official_salary' => ['nullable', 'string', 'max:50'],
            'probation_salary' => ['nullable', 'string', 'max:50'],
            'internship_salary' => ['nullable', 'string', 'max:50'],
        ];
    }

    /**
     * Gán các mức lương đã chuẩn hoá từ request vào model nhân viên.
     */
    private function fillEmployeeSalary(User $employee, Request $request): void
    {
        $employee->official_salary = $this->normalizeSalaryInput($request->input('official_salary'));
        $employee->probation_salary = $this->normalizeSalaryInput($request->input('probation_salary'));
        $employee->internship_salary = $this->normalizeSalaryInput($request->input('internship_salary'));
    }

    /**
     * Hiển thị danh sách nhân viên với bộ lọc, thống kê và nhóm theo ban giám đốc / phòng ban.
     */
    public function index(Request $request)
    {
        $this->ensureEmployeeSalaryColumns();
        $query = User::query()
            ->with(['department', 'position', 'roles', 'avatar'])
            ->orderBy('name');

        if ($request->filled('keyword')) {
            $keyword = trim($request->keyword);
            $query->where(function ($q) use ($keyword) {
                $q->where('name', 'like', "%{$keyword}%")
                    ->orWhere('email', 'like', "%{$keyword}%")
                    ->orWhere('phone_number', 'like', "%{$keyword}%");
            });
        }

        if ($request->filled('department_id')) {
            $query->where('department_id', $request->department_id);
        }

        if ($request->filled('position_id')) {
            $query->where('position_id', $request->position_id);
        }

        if ($request->filled('is_active')) {
            $query->where('is_active', $request->is_active);
        }

        if ($request->filled('role')) {
            $query->role($request->role);
        }

        $employees = $query->get();

        $departments = Department::withCount('users')->orderBy('name')->get();
        $positions = Position::withCount('users')->orderBy('name')->get();
        $roles = Role::orderBy('name')->get();

        $totalEmployees = User::count();
        $activeEmployees = User::where('is_active', 1)->count();
        $inactiveEmployees = User::where('is_active', 0)->count();
        $departmentCount = Department::count();

        $inactiveEmployeesList = $employees
            ->filter(fn ($user) => (int) $user->is_active !== 1)
            ->values();

        $activeEmployeesOnly = $employees
            ->filter(fn ($user) => (int) $user->is_active === 1)
            ->values();

        $boardUsers = $activeEmployeesOnly
            ->filter(fn ($user) => $this->isBoardMember($user))
            ->sortBy(fn ($user) => [
                ! $this->isLikelyLeader($user),
                strtolower($user->name ?? ''),
            ])
            ->values();

        $departmentGroups = $this->buildDepartmentGroups($activeEmployeesOnly, $boardUsers);

        return view('hr.employees.index', compact(
            'departments',
            'positions',
            'roles',
            'totalEmployees',
            'activeEmployees',
            'inactiveEmployees',
            'departmentCount',
            'boardUsers',
            'departmentGroups',
            'inactiveEmployeesList'
        ));
    }

    /**
     * Hiển thị form thêm nhân viên mới.
     */
    public function create()
    {
        $this->ensureEmployeeSalaryColumns();
        $departments = Department::orderBy('name')->get();
        $positions = Position::orderBy('name')->get();
        $roles = Role::orderBy('name')->get();

        return view('hr.employees.create', compact('departments', 'positions', 'roles'));
    }

    /**
     * Tạo nhân viên mới kèm lương và gán vai trò nếu có.
     */
    public function store(Request $request)
    {
        $this->ensureEmployeeSalaryColumns();
        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'phone_number' => ['nullable', 'string', 'max:30'],
            'department_id' => ['nullable', 'exists:departments,id'],
            'position_id' => ['nullable', 'exists:positions,id'],
            'password' => ['required', 'string', 'min:6', 'confirmed'],
            'is_active' => ['required', 'in:0,1'],
            'role' => ['nullable', 'exists:roles,name'],
        ] + $this->salaryValidationRules(), [
            'name.required' => 'Vui lòng nhập họ tên.',
            'email.required' => 'Vui lòng nhập email.',
            'email.unique' => 'Email đã tồn tại.',
            'password.required' => 'Vui lòng nhập mật khẩu.',
            'password.confirmed' => 'Xác nhận mật khẩu không khớp.',
            'role.exists' => 'Vai trò không hợp lệ.',
        ]);

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'phone_number' => $request->phone_number,
            'department_id' => $request->department_id,
            'position_id' => $request->position_id,
            'is_active' => $request->is_active,
            'password' => Hash::make($request->password),
        ]);

        $this->fillEmployeeSalary($user, $request);
        $user->save();

        if ($request->filled('role')) {
            $user->syncRoles([$request->role]);
        }

        return redirect()
            ->route('hr.employees.index')
            ->with('success', 'Thêm nhân viên thành công.');
    }

    /**
     * Hiển thị chi tiết một nhân viên.
     *
     * @param  string  $id  ID nhân viên
     */
    public function show(string $id)
    {
        $this->ensureEmployeeSalaryColumns();
        $employee = User::with(['department', 'position', 'roles', 'avatar'])->findOrFail($id);

        return view('hr.employees.show', compact('employee'));
    }

    /**
     * Hiển thị form chỉnh sửa nhân viên.
     *
     * @param  string  $id  ID nhân viên
     */
    public function edit(string $id)
    {
        $this->ensureEmployeeSalaryColumns();
        $employee = User::with(['roles', 'avatar'])->findOrFail($id);
        $departments = Department::orderBy('name')->get();
        $positions = Position::orderBy('name')->get();
        $roles = Role::orderBy('name')->get();

        return view('hr.employees.edit', compact('employee', 'departments', 'positions', 'roles'));
    }

    /**
     * Cập nhật thông tin nhân viên, lương và vai trò.
     *
     * @param  string  $id  ID nhân viên
     */
    public function update(Request $request, string $id)
    {
        $this->ensureEmployeeSalaryColumns();
        $employee = User::findOrFail($id);

        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', \Illuminate\Validation\Rule::unique('users', 'email')->ignore($employee->id)],
            'phone_number' => ['nullable', 'string', 'max:30'],
            'department_id' => ['nullable', 'exists:departments,id'],
            'position_id' => ['nullable', 'exists:positions,id'],
            'is_active' => ['required', 'in:0,1'],
            'role' => ['nullable', 'exists:roles,name'],
        ] + $this->salaryValidationRules());

        $employee->name = $request->name;
        $employee->email = $request->email;
        $employee->phone_number = $request->phone_number;
        $employee->department_id = $request->department_id;
        $employee->position_id = $request->position_id;
        $employee->is_active = $request->is_active;
        $this->fillEmployeeSalary($employee, $request);
        $employee->save();

        if ($request->filled('role')) {
            $employee->syncRoles([$request->role]);
        }

        return redirect()
            ->route('hr.employees.index')
            ->with('success', 'Cập nhật nhân viên thành công.');
    }

    /**
     * Xoá nhân viên: xoá cứng nếu không còn ràng buộc dữ liệu quan trọng, ngược lại xoá mềm (ẩn + khoá tài khoản).
     *
     * @param  int|string|object  $employee  ID hoặc model nhân viên
     */
    public function destroy($employee)
    {
        try {
            $id = is_object($employee) ? ($employee->id ?? null) : $employee;
            $id = (int) $id;

            if (! $id) {
                return redirect()->route('hr.employees.index')->withErrors('Không xác định được nhân viên cần xóa.');
            }

            if ((int) auth()->id() === $id) {
                return redirect()->route('hr.employees.index')->withErrors('Không thể xóa tài khoản đang đăng nhập.');
            }

            $cleanupTables = [
                'model_has_roles',
                'model_has_permissions',
                'sessions',
                'personal_access_tokens',
                'hr_employee_profiles',
                'hr_employee_files',
            ];

            // Xóa dữ liệu phụ không ảnh hưởng lịch sử duyệt
            if (\Illuminate\Support\Facades\Schema::hasTable('model_has_roles')) {
                \Illuminate\Support\Facades\DB::table('model_has_roles')->where('model_id', $id)->delete();
            }

            if (\Illuminate\Support\Facades\Schema::hasTable('model_has_permissions')) {
                \Illuminate\Support\Facades\DB::table('model_has_permissions')->where('model_id', $id)->delete();
            }

            if (\Illuminate\Support\Facades\Schema::hasTable('sessions')) {
                \Illuminate\Support\Facades\DB::table('sessions')->where('user_id', $id)->delete();
            }

            if (\Illuminate\Support\Facades\Schema::hasTable('personal_access_tokens')) {
                \Illuminate\Support\Facades\DB::table('personal_access_tokens')
                    ->where('tokenable_id', $id)
                    ->delete();
            }

            if (\Illuminate\Support\Facades\Schema::hasTable('hr_employee_profiles')) {
                \Illuminate\Support\Facades\DB::table('hr_employee_profiles')->where('employee_id', $id)->delete();
            }

            if (\Illuminate\Support\Facades\Schema::hasTable('hr_employee_files')) {
                $files = \Illuminate\Support\Facades\DB::table('hr_employee_files')->where('employee_id', $id)->get();

                foreach ($files as $file) {
                    if (! empty($file->file_path) && \Illuminate\Support\Facades\Storage::disk('public')->exists($file->file_path)) {
                        \Illuminate\Support\Facades\Storage::disk('public')->delete($file->file_path);
                    }
                }

                \Illuminate\Support\Facades\DB::table('hr_employee_files')->where('employee_id', $id)->delete();
            }

            $hasImportantReferences = false;

            if (\Illuminate\Support\Facades\Schema::hasTable('users')) {
                try {
                    $references = \Illuminate\Support\Facades\DB::select("
                        SELECT TABLE_NAME, COLUMN_NAME
                        FROM information_schema.KEY_COLUMN_USAGE
                        WHERE REFERENCED_TABLE_SCHEMA = DATABASE()
                          AND REFERENCED_TABLE_NAME = 'users'
                          AND REFERENCED_COLUMN_NAME = 'id'
                    ");

                    foreach ($references as $ref) {
                        $table = $ref->TABLE_NAME;
                        $column = $ref->COLUMN_NAME;

                        if (in_array($table, $cleanupTables, true)) {
                            continue;
                        }

                        if (! \Illuminate\Support\Facades\Schema::hasTable($table)) {
                            continue;
                        }

                        $exists = \Illuminate\Support\Facades\DB::table($table)
                            ->where($column, $id)
                            ->exists();

                        if ($exists) {
                            $hasImportantReferences = true;
                            break;
                        }
                    }
                } catch (\Throwable $e) {
                    // Nếu không đọc được information_schema thì dùng phương án an toàn: xóa mềm.
                    $hasImportantReferences = true;
                }
            }

            // Nếu không có ràng buộc quan trọng thì được phép xóa cứng.
            if (! $hasImportantReferences) {
                if (is_object($employee) && method_exists($employee, 'delete')) {
                    $employee->delete();

                    return redirect()->route('hr.employees.index')->with('success', 'Đã xóa nhân viên khỏi hệ thống.');
                }

                if (\Illuminate\Support\Facades\Schema::hasTable('users')) {
                    \Illuminate\Support\Facades\DB::table('users')->where('id', $id)->delete();

                    return redirect()->route('hr.employees.index')->with('success', 'Đã xóa user khỏi hệ thống.');
                }
            }

            // Có lịch sử liên quan: không delete cứng, chỉ ẩn + khóa tài khoản
            if (\Illuminate\Support\Facades\Schema::hasTable('users')) {
                $columns = \Illuminate\Support\Facades\Schema::getColumnListing('users');
                $data = [];

                if (in_array('status', $columns, true)) {
                    $data['status'] = 'deleted';
                }

                if (in_array('employment_status', $columns, true)) {
                    $data['employment_status'] = 'deleted';
                }

                if (in_array('is_active', $columns, true)) {
                    $data['is_active'] = 0;
                }

                if (in_array('active', $columns, true)) {
                    $data['active'] = 0;
                }

                if (in_array('deleted_at', $columns, true)) {
                    $data['deleted_at'] = now();
                }

                if (in_array('deleted_by', $columns, true)) {
                    $data['deleted_by'] = auth()->id();
                }

                if (in_array('email', $columns, true)) {
                    $data['email'] = 'deleted_user_'.$id.'_'.time().'@deleted.local';
                }

                if (in_array('phone', $columns, true)) {
                    $data['phone'] = null;
                }

                if (in_array('password', $columns, true)) {
                    $data['password'] = \Illuminate\Support\Facades\Hash::make(\Illuminate\Support\Str::random(32));
                }

                if (in_array('name', $columns, true)) {
                    $oldName = \Illuminate\Support\Facades\DB::table('users')->where('id', $id)->value('name');
                    $data['name'] = '[Đã xóa] '.($oldName ?: 'User '.$id);
                }

                if (in_array('full_name', $columns, true)) {
                    $oldFullName = \Illuminate\Support\Facades\DB::table('users')->where('id', $id)->value('full_name');
                    $data['full_name'] = '[Đã xóa] '.($oldFullName ?: 'User '.$id);
                }

                if (in_array('updated_at', $columns, true)) {
                    $data['updated_at'] = now();
                }

                if (! empty($data)) {
                    \Illuminate\Support\Facades\DB::table('users')->where('id', $id)->update($data);
                }
            }

            return redirect()
                ->route('hr.employees.index')
                ->with('success', 'Đã xóa khỏi danh sách nhân sự và khóa tài khoản. Lịch sử duyệt/đề nghị thanh toán được giữ để không lỗi dữ liệu.');
        } catch (\Throwable $e) {
            report($e);

            return redirect()
                ->route('hr.employees.index')
                ->withErrors('Không xóa được nhân viên: '.$e->getMessage());
        }
    }

    /**
     * Hiển thị sơ đồ tổ chức (dùng chung dữ liệu với trang danh sách nhân viên).
     */
    public function orgChart(Request $request)
    {
        return $this->index($request);
    }

    /**
     * Gom nhân viên đang hoạt động thành các nhóm phòng ban (gộp Marketing & Sales, Kế toán & Kho) kèm leader.
     *
     * @param  \Illuminate\Support\Collection  $employees  Danh sách nhân viên
     * @param  \Illuminate\Support\Collection|null  $boardUsers  Danh sách ban giám đốc
     */
    private function buildDepartmentGroups($employees, $boardUsers = null)
    {
        $grouped = [];
        $boardUserIds = collect($boardUsers)->pluck('id')->all();

        foreach ($employees as $employee) {
            if ((int) $employee->is_active !== 1) {
                continue;
            }

            $departmentNames = $this->extractDepartmentNames($employee);

            if (empty($departmentNames)) {
                $departmentNames = ['Chưa gán phòng ban'];
            }

            foreach ($departmentNames as $departmentName) {
                $normalized = mb_strtolower(trim($departmentName));

                if ($this->isMarketingSales($normalized)) {
                    $key = 'marketing-sales';
                    $name = 'Marketing & Sales';
                    $subtitle = '1 trưởng nhóm chung, 2 nhánh nhân sự: Marketing và Sales';
                } elseif ($this->isAccountingWarehouse($normalized)) {
                    $key = 'accounting-warehouse';
                    $name = 'Kế toán & Kho';
                    $subtitle = 'Nhóm kế toán, tài chính nội bộ và kho vận';
                } elseif ($normalized === 'chưa gán phòng ban') {
                    $key = 'unassigned';
                    $name = 'Chưa gán phòng ban';
                    $subtitle = 'Nhân sự chưa được phân về bộ phận cụ thể';
                } else {
                    $key = 'department-'.md5($normalized);
                    $name = $departmentName;
                    $subtitle = 'Nhân sự thuộc '.$departmentName;
                }

                if (! isset($grouped[$key])) {
                    $grouped[$key] = [
                        'key' => $key,
                        'name' => $name,
                        'subtitle' => $subtitle,
                        'members' => collect(),
                        'leader' => null,
                    ];
                }

                if (! $grouped[$key]['members']->contains(fn ($member) => $member->id === $employee->id)) {
                    $grouped[$key]['members']->push($employee);
                }
            }
        }

        $collection = collect($grouped)
            ->map(function ($group) use ($boardUserIds) {
                $members = $group['members']
                    ->sortBy(fn ($user) => [
                        in_array($user->id, $boardUserIds, true) ? 0 : 1,
                        ! $this->isLikelyLeader($user),
                        strtolower($user->name ?? ''),
                    ])
                    ->values();

                $leader = $members->first(fn ($user) => $this->isLikelyLeader($user))
                    ?? $members->first();

                return [
                    'key' => $group['key'],
                    'name' => $group['name'],
                    'subtitle' => $group['subtitle'],
                    'leader' => $leader,
                    'members' => $members,
                ];
            })
            ->sortBy(function ($group) {
                $priority = [
                    'marketing-sales' => 1,
                    'accounting-warehouse' => 2,
                    'unassigned' => 99,
                ];

                return [
                    $priority[$group['key']] ?? 10,
                    strtolower($group['name']),
                ];
            })
            ->values();

        return $collection;
    }

    /**
     * Suy ra danh sách tên phòng ban của user từ department, position và role.
     */
    private function extractDepartmentNames(User $user): array
    {
        $names = [];

        if (! empty(optional($user->department)->name)) {
            $names[] = trim($user->department->name);
        }

        $positionName = trim((string) optional($user->position)->name);
        $roleName = trim((string) optional($user->roles->first())->name);
        $departmentName = trim((string) optional($user->department)->name);

        if (
            $this->isMarketingSales(mb_strtolower($positionName)) ||
            $this->isMarketingSales(mb_strtolower($roleName)) ||
            $this->isMarketingSales(mb_strtolower($departmentName))
        ) {
            $names[] = 'Marketing & Sales';
        }

        if (
            $this->isAccountingWarehouse(mb_strtolower($positionName)) ||
            $this->isAccountingWarehouse(mb_strtolower($roleName)) ||
            $this->isAccountingWarehouse(mb_strtolower($departmentName))
        ) {
            $names[] = 'Kế toán & Kho';
        }

        return array_values(array_unique(array_filter($names)));
    }

    /**
     * Kiểm tra user có thuộc ban giám đốc (theo role, chức vụ hoặc phòng ban).
     */
    private function isBoardMember(User $user): bool
    {
        $role = mb_strtolower((string) optional($user->roles->first())->name);
        $position = mb_strtolower((string) optional($user->position)->name);
        $department = mb_strtolower((string) optional($user->department)->name);

        return in_array($role, ['admin', 'management', 'super-admin', 'director'], true)
            || str_contains($position, 'giám đốc')
            || str_contains($position, 'director')
            || str_contains($position, 'ceo')
            || str_contains($position, 'founder')
            || str_contains($department, 'ban giám đốc');
    }

    /**
     * Kiểm tra user có khả năng là trưởng nhóm / quản lý (theo role hoặc tên chức vụ).
     */
    private function isLikelyLeader(User $user): bool
    {
        $role = mb_strtolower((string) optional($user->roles->first())->name);
        $position = mb_strtolower((string) optional($user->position)->name);

        return in_array($role, ['admin', 'management', 'sales_manager', 'manager', 'leader', 'lead'], true)
            || str_contains($position, 'trưởng')
            || str_contains($position, 'phó')
            || str_contains($position, 'manager')
            || str_contains($position, 'lead')
            || str_contains($position, 'head')
            || str_contains($position, 'giám đốc');
    }

    /**
     * Kiểm tra chuỗi có thuộc nhóm Marketing / Sales / kinh doanh.
     */
    private function isMarketingSales(string $text): bool
    {
        return str_contains($text, 'marketing')
            || str_contains($text, 'sale')
            || str_contains($text, 'sales')
            || str_contains($text, 'kinh doanh');
    }

    /**
     * Kiểm tra chuỗi có thuộc nhóm Kế toán / Kho.
     */
    private function isAccountingWarehouse(string $text): bool
    {
        return str_contains($text, 'kế toán')
            || str_contains($text, 'ke toan')
            || str_contains($text, 'accounting')
            || str_contains($text, 'kho')
            || str_contains($text, 'warehouse');
    }
}
