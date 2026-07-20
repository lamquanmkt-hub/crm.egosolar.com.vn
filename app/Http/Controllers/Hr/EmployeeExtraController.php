<?php

namespace App\Http\Controllers\Hr;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

/**
 * Controller quản lý thông tin mở rộng của nhân viên (hồ sơ chi tiết và file đính kèm).
 */
class EmployeeExtraController extends Controller
{
    /**
     * Kiểm tra quyền truy cập: chỉ cho phép admin / HR / kế toán, ngược lại abort 403.
     */
    private function allow()
    {
        $user = auth()->user();
        abort_unless($user, 403);

        $roles = ['admin', 'hr', 'accounting', 'ketoan', 'ke_toan'];

        if (method_exists($user, 'hasAnyRole')) {
            abort_unless($user->hasAnyRole($roles), 403);

            return;
        }

        if (method_exists($user, 'hasRole')) {
            foreach ($roles as $role) {
                if ($user->hasRole($role)) {
                    return;
                }
            }
            abort(403);
        }

        $role = strtolower((string) ($user->role ?? ''));
        abort_unless(in_array($role, $roles, true), 403);
    }

    /**
     * Cập nhật (hoặc tạo mới) hồ sơ chi tiết của nhân viên trong bảng hr_employee_profiles.
     *
     * @param  int|string  $employee  ID nhân viên
     */
    public function update(Request $request, $employee)
    {
        $this->allow();

        abort_unless(Schema::hasTable('hr_employee_profiles'), 404);

        $fields = [
            'employee_code',
            'hire_date',
            'official_date',
            'probation_start_date',
            'probation_end_date',
            'contract_type',
            'contract_start_date',
            'contract_end_date',
            'birth_date',
            'gender',
            'id_card',
            'id_card_date',
            'id_card_place',
            'address',
            'bank_name',
            'bank_account',
            'tax_code',
            'insurance_number',
            'emergency_contact_name',
            'emergency_contact_phone',
            'hr_note',
        ];

        $data = [
            'employee_id' => (int) $employee,
            'updated_at' => now(),
        ];

        foreach ($fields as $field) {
            $value = $request->input($field);
            $data[$field] = $value === '' ? null : $value;
        }

        $exists = DB::table('hr_employee_profiles')
            ->where('employee_id', (int) $employee)
            ->exists();

        if ($exists) {
            DB::table('hr_employee_profiles')
                ->where('employee_id', (int) $employee)
                ->update($data);
        } else {
            $data['created_at'] = now();

            DB::table('hr_employee_profiles')->insert($data);
        }

        return back()->with('success', 'Đã cập nhật thông tin chi tiết nhân viên.');
    }

    /**
     * Upload file hồ sơ cho nhân viên và lưu thông tin vào bảng hr_employee_files.
     *
     * @param  int|string  $employee  ID nhân viên
     */
    public function uploadFile(Request $request, $employee)
    {
        $this->allow();

        $request->validate([
            'file' => ['required', 'file', 'max:20480'],
            'file_type' => ['nullable', 'string', 'max:100'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        abort_unless(Schema::hasTable('hr_employee_files'), 404);

        $file = $request->file('file');
        $path = $file->store('hr/employee-files/'.(int) $employee, 'public');

        DB::table('hr_employee_files')->insert([
            'employee_id' => (int) $employee,
            'file_type' => $request->input('file_type', 'Hồ sơ khác'),
            'original_name' => $file->getClientOriginalName(),
            'file_path' => $path,
            'mime_type' => $file->getClientMimeType(),
            'size_bytes' => $file->getSize(),
            'note' => $request->input('note'),
            'uploaded_by' => auth()->id(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return back()->with('success', 'Đã upload hồ sơ nhân viên.');
    }

    /**
     * Tải xuống file hồ sơ nhân viên theo ID file.
     *
     * @param  int|string  $file  ID bản ghi file
     */
    public function downloadFile($file)
    {
        $this->allow();

        $row = DB::table('hr_employee_files')->where('id', (int) $file)->first();
        abort_unless($row, 404);

        abort_unless(Storage::disk('public')->exists($row->file_path), 404);

        return Storage::disk('public')->download($row->file_path, $row->original_name ?: basename($row->file_path));
    }

    /**
     * Xoá file hồ sơ nhân viên (cả file vật lý và bản ghi DB).
     *
     * @param  int|string  $file  ID bản ghi file
     */
    public function deleteFile($file)
    {
        $this->allow();

        $row = DB::table('hr_employee_files')->where('id', (int) $file)->first();
        abort_unless($row, 404);

        if ($row->file_path && Storage::disk('public')->exists($row->file_path)) {
            Storage::disk('public')->delete($row->file_path);
        }

        DB::table('hr_employee_files')->where('id', (int) $file)->delete();

        return back()->with('success', 'Đã xoá file hồ sơ.');
    }
}
