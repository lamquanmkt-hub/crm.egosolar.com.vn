<?php

namespace App\Http\Controllers\Hr;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Position;

/**
 * Controller quản lý chức vụ (Position) trong module HR.
 */
class PositionController extends Controller
{
    /**
     * Hiển thị danh sách chức vụ có phân trang.
     */
    public function index()
    {
        $positions = Position::latest()->paginate(10);

        return view('hr.positions.index', compact('positions'));
    }

    /**
     * Hiển thị form tạo chức vụ mới.
     */
    public function create()
    {
        return view('hr.positions.create');
    }

    /**
     * Lưu chức vụ mới sau khi validate.
     */
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|max:255',
        ]);

        Position::create([
            'name' => $request->name,
            'code' => $request->code,
            'description' => $request->description,
        ]);

        return redirect()->route('hr.positions.index')
            ->with('success', 'Tạo chức vụ thành công');
    }

    /**
     * Hiển thị form chỉnh sửa chức vụ.
     *
     * @param int $id ID chức vụ
     */
    public function edit($id)
    {
        $position = Position::findOrFail($id);

        return view('hr.positions.edit', compact('position'));
    }

    /**
     * Cập nhật thông tin chức vụ.
     *
     * @param int $id ID chức vụ
     */
    public function update(Request $request, $id)
    {
        $position = Position::findOrFail($id);

        $request->validate([
            'name' => 'required|max:255',
        ]);

        $position->update([
            'name' => $request->name,
            'code' => $request->code,
            'description' => $request->description,
        ]);

        return redirect()->route('hr.positions.index')
            ->with('success', 'Cập nhật thành công');
    }

    /**
     * Xoá chức vụ theo ID.
     *
     * @param int $id ID chức vụ
     */
    public function destroy($id)
    {
        Position::findOrFail($id)->delete();

        return back()->with('success', 'Đã xoá');
    }
}