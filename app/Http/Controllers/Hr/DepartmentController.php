<?php

namespace App\Http\Controllers\Hr;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Department;

class DepartmentController extends Controller
{
    public function index()
    {
        $departments = Department::query()->latest()->paginate(10);

        return view('hr.departments.index', compact('departments'));
    }

    public function create()
    {
        return view('hr.departments.create');
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['nullable', 'string', 'max:100'],
            'description' => ['nullable', 'string'],
        ]);

        Department::create([
            'name' => $request->name,
            'code' => $request->code,
            'description' => $request->description,
        ]);

        return redirect()
            ->route('hr.departments.index')
            ->with('success', 'Tạo phòng ban thành công.');
    }

    public function edit($id)
    {
        $department = Department::findOrFail($id);

        return view('hr.departments.edit', compact('department'));
    }

    public function update(Request $request, $id)
    {
        $department = Department::findOrFail($id);

        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['nullable', 'string', 'max:100'],
            'description' => ['nullable', 'string'],
        ]);

        $department->update([
            'name' => $request->name,
            'code' => $request->code,
            'description' => $request->description,
        ]);

        return redirect()
            ->route('hr.departments.index')
            ->with('success', 'Cập nhật phòng ban thành công.');
    }

    public function destroy($id)
    {
        $department = Department::findOrFail($id);
        $department->delete();

        return redirect()
            ->route('hr.departments.index')
            ->with('success', 'Xoá phòng ban thành công.');
    }
}